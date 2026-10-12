<?php
/*
Plugin Name: CUB SC Updater
Description: Busca automaticamente o valor do CUB no site do Sinduscon-Fpolis e exibe em /cub-sc/ via shortcode [cub_sc]. Guarda histórico e atualiza via WP-Cron uma vez por dia.
Version: 1.0
Author: George Becker & Sofia (ChatGPT)
*/

if ( ! defined( 'ABSPATH' ) ) exit;

class Cub_SC_Updater {

    private static $option_key = 'cub_sc_updater_data';
    private static $page_slug = 'cub-sc';
    private static $cron_hook = 'cub_sc_updater_daily_event';

    public static function init() {
        add_action('init', array(__CLASS__, 'maybe_create_page'));
        add_action('admin_menu', array(__CLASS__, 'admin_menu'));
        add_shortcode('cub_sc', array(__CLASS__, 'shortcode_display'));
        add_action(self::$cron_hook, array(__CLASS__, 'fetch_and_store'));
        add_action('wp_ajax_cub_sc_manual_fetch', array(__CLASS__, 'ajax_manual_fetch'));
        register_activation_hook(__FILE__, array(__CLASS__, 'activate'));
        register_deactivation_hook(__FILE__, array(__CLASS__, 'deactivate'));
    }

    public static function activate() {
        if ( ! wp_next_scheduled( self::$cron_hook ) ) {
            wp_schedule_event( time() + 10, 'daily', self::$cron_hook );
        }
        self::fetch_and_store();
    }

    public static function deactivate() {
        $timestamp = wp_next_scheduled( self::$cron_hook );
        if ( $timestamp ) wp_unschedule_event( $timestamp, self::$cron_hook );
    }

    public static function maybe_create_page() {
        $slug = self::$page_slug;
        $page = get_page_by_path( $slug );
        if ( ! $page ) {
            wp_insert_post( array(
                'post_title' => 'CUB - Santa Catarina',
                'post_name' => $slug,
                'post_content' => '[cub_sc]',
                'post_status' => 'publish',
                'post_type' => 'page'
            ));
        }
    }

    public static function admin_menu() {
        add_menu_page('CUB SC', 'CUB SC', 'manage_options', 'cub-sc-updater', array(__CLASS__, 'admin_page'));
    }

    public static function admin_page() {
        if ( ! current_user_can('manage_options') ) return;
        $data = get_option(self::$option_key, array());
        ?>
        <div class="wrap">
            <h1>CUB SC Updater</h1>
            <p>Última atualização automática: <?php echo isset($data['last_checked']) ? date('Y-m-d H:i:s', intval($data['last_checked'])) : 'Nunca'; ?></p>
            <p><button id="cub-sc-fetch" class="button button-primary">Buscar agora</button> <span id="cub-sc-status"></span></p>
            <h2>Últimos registros</h2>
            <table class="widefat">
                <thead><tr><th>Data</th><th>Referencia</th><th>Residencial</th><th>Comercial</th></tr></thead>
                <tbody>
                <?php
                $history = isset($data['history']) ? $data['history'] : array();
                if ( $history ) {
                    foreach ( $history as $row ) {
                        echo '<tr><td>'.esc_html(date('Y-m-d H:i:s', intval($row['time']))).'</td><td>'.esc_html($row['reference']).'</td><td>'.esc_html($row['residential']).'</td><td>'.esc_html($row['commercial']).'</td></tr>';
                    }
                } else {
                    echo '<tr><td colspan="4">Nenhum registro</td></tr>';
                }
                ?>
                </tbody>
            </table>
        </div>
        <script>
        (function(){
            var btn = document.getElementById('cub-sc-fetch');
            var status = document.getElementById('cub-sc-status');
            btn.addEventListener('click', function(){
                status.textContent = 'Buscando...';
                var xhr = new XMLHttpRequest();
                xhr.open('POST', ajaxurl);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
                xhr.onload = function(){
                    if (xhr.status === 200) {
                        status.textContent = 'Pronto';
                        location.reload();
                    } else {
                        status.textContent = 'Erro';
                    }
                };
                xhr.send('action=cub_sc_manual_fetch');
            });
        })();
        </script>
        <?php
    }

    public static function sanitize_price($str) {
        $clean = preg_replace('/[^0-9.,%-]/u', '', $str);
        return trim($clean);
    }

    public static function parse_html_for_cub($html) {
        $result = array(
            'reference' => '',
            'use_for' => '',
            'residential' => '',
            'commercial' => '',
        );

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        if ( !@$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html) ) {
            libxml_clear_errors();
        } else {
            $xpath = new DOMXPath($dom);
            $res_nodes = $xpath->query("//*[contains(text(),'Residencial') or contains(text(),'Residencial Médio')]");
            $com_nodes = $xpath->query("//*[contains(text(),'Comercial') or contains(text(),'Comercial Médio')]");
            if ($res_nodes->length) {
                $node = $res_nodes->item(0);
                $val = self::find_price_near_node($xpath, $node);
                if ($val) $result['residential'] = $val;
            }
            if ($com_nodes->length) {
                $node = $com_nodes->item(0);
                $val = self::find_price_near_node($xpath, $node);
                if ($val) $result['commercial'] = $val;
            }
            $ref_nodes = $xpath->query("//*[contains(text(), 'Mês de Referência') or contains(text(),'Mês de referência')]");
            if ($ref_nodes->length) {
                $txt = $ref_nodes->item(0)->textContent;
                $s = $ref_nodes->item(0)->nextSibling;
                if ($s) $result['reference'] = trim($s->textContent);
                else {
                    $parent = $ref_nodes->item(0)->parentNode;
                    $result['reference'] = trim($parent->textContent);
                }
            }
        }

        if ( empty($result['residential']) ) {
            if ( preg_match('/Residencial\s+M[eê]dio[\s\S]*?R\$\s*([0-9\.,]+)/i', $html, $m) ) {
                $result['residential'] = 'R$ ' . $m[1];
            } elseif (preg_match('/Residencial[\s\S]*?R\$\s*([0-9\.,]+)/i', $html, $m2)) {
                $result['residential'] = 'R$ ' . $m2[1];
            }
        }
        if ( empty($result['commercial']) ) {
            if ( preg_match('/Comercial\s+M[eê]dio[\s\S]*?R\$\s*([0-9\.,]+)/i', $html, $m) ) {
                $result['commercial'] = 'R$ ' . $m[1];
            } elseif (preg_match('/Comercial[\s\S]*?R\$\s*([0-9\.,]+)/i', $html, $m2)) {
                $result['commercial'] = 'R$ ' . $m2[1];
            }
        }
        if ( empty($result['reference']) ) {
            if ( preg_match('/M[eê]s de Refer[eê]ncia[:\s]*([A-Za-z0-9\/-]+)/i', $html, $r) ) {
                $result['reference'] = trim($r[1]);
            }
        }

        $result['residential'] = self::sanitize_price($result['residential']);
        $result['commercial'] = self::sanitize_price($result['commercial']);
        $result['reference'] = trim($result['reference']);

        return $result;
    }

    private static function find_price_near_node($xpath, $node) {
        $el = $node;
        $desc = $xpath->query('.//text()', $el);
        foreach ($desc as $d) {
            if (preg_match('/R\$\s*[0-9\.,]+/', $d->textContent, $m)) {
                return trim($m[0]);
            }
        }
        $sib = $el->nextSibling;
        while ($sib) {
            if ($sib->nodeType === XML_TEXT_NODE) {
                if (preg_match('/R\$\s*[0-9\.,]+/', $sib->textContent, $m)) return trim($m[0]);
            } else {
                $txt = $sib->textContent;
                if (preg_match('/R\$\s*[0-9\.,]+/', $txt, $m)) return trim($m[0]);
            }
            $sib = $sib->nextSibling;
        }
        return '';
    }

    public static function fetch_and_store() {
        $url = 'https://sinduscon-fpolis.org.br/';
        $response = wp_remote_get( $url, array('timeout'=>20, 'headers'=>array('User-Agent'=>'IMoxs-CUB-Updater/1.0')) );
        if ( is_wp_error($response) ) return false;
        $code = wp_remote_retrieve_response_code($response);
        if ($code != 200) return false;
        $body = wp_remote_retrieve_body($response);
        $parsed = self::parse_html_for_cub($body);
        if ( empty($parsed['residential']) && empty($parsed['commercial']) ) return false;
        $data = get_option(self::$option_key, array());
        if ( ! isset($data['history']) ) $data['history'] = array();
        $row = array(
            'time' => time(),
            'reference' => $parsed['reference'],
            'residential' => $parsed['residential'],
            'commercial' => $parsed['commercial'],
        );
        $last = end($data['history']);
        if ( $last && isset($last['reference']) && $last['reference'] === $row['reference'] ) {
            array_pop($data['history']);
        }
        array_unshift($data['history'], $row);
        if ( count($data['history']) > 36 ) $data['history'] = array_slice($data['history'], 0, 36);
        $data['last_checked'] = time();
        update_option(self::$option_key, $data);
        return true;
    }

    public static function ajax_manual_fetch() {
        if ( ! current_user_can('manage_options') ) {
            wp_die('no');
        }
        $ok = self::fetch_and_store();
        if ( $ok ) wp_send_json_success('ok');
        wp_send_json_error('fail');
    }

    public static function get_latest() {
        $data = get_option(self::$option_key, array());
        if ( isset($data['history'][0]) ) return $data['history'][0];
        return null;
    }

    public static function shortcode_display($atts = array()) {
        $latest = self::get_latest();
        $data = get_option(self::$option_key, array());
        $history = isset($data['history']) ? $data['history'] : array();

        ob_start();
        if ( $latest ) {
            echo '<div class="cub-sc-box">';
            echo '<h2>CUB - Santa Catarina</h2>';
            echo '<p><strong>Referência:</strong> '.esc_html($latest['reference']).'</p>';
            echo '<table class="cub-sc-table" style="width:100%;max-width:720px;border-collapse:collapse">';
            echo '<thead><tr><th style="text-align:left;padding:8px;border-bottom:1px solid #ddd">Tipo</th><th style="text-align:left;padding:8px;border-bottom:1px solid #ddd">Valor</th></tr></thead>';
            echo '<tbody>';
            echo '<tr><td style="padding:8px;border-bottom:1px solid #eee">Residencial</td><td style="padding:8px;border-bottom:1px solid #eee">'.esc_html($latest['residential']).'</td></tr>';
            echo '<tr><td style="padding:8px;border-bottom:1px solid #eee">Comercial</td><td style="padding:8px;border-bottom:1px solid #eee">'.esc_html($latest['commercial']).'</td></tr>';
            echo '</tbody></table>';
            echo '</div>';
        } else {
            echo '<p>Dados do CUB ainda não disponíveis. Clique em <a href="'.admin_url('admin.php?page=cub-sc-updater').'">Buscar agora</a> (requer login).</p>';
        }

        if ( $history ) {
            echo '<h3>Histórico</h3>';
            echo '<ul>';
            foreach ( $history as $row ) {
                echo '<li>'.esc_html(date('Y-m', intval($row['time']))).' — '.esc_html($row['reference']).' — '.esc_html($row['residential']).' / '.esc_html($row['commercial']).'</li>';
            }
            echo '</ul>';
        }

        return ob_get_clean();
    }

}

Cub_SC_Updater::init();
