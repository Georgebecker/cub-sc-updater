<?php
/*
Plugin Name: CUB SC Updater
Description: Busca o valor do CUB no site do Sinduscon-Fpolis e publica em /cub-sc/ via shortcode [cub_sc]. Atualiza no dia 5 de cada mês (ou pelo botão Buscar agora) e guarda o histórico das mudanças.
Version: 1.4
Author: George H. Becker
Author URI: https://www.linkedin.com/in/georgehbecker/
License: MIT
License URI: https://opensource.org/licenses/MIT
Requires at least: 5.3
Requires PHP: 7.4
Text Domain: cub-sc
Domain Path: /languages
*/

if ( ! defined( 'ABSPATH' ) ) exit;

class Cub_SC_Updater {

    const VERSION = '1.4';
    const TEXT_DOMAIN = 'cub-sc';
    const SOURCE_URL = 'https://sinduscon-fpolis.org.br/servico/cub-mensal/';
    const HISTORY_MAX = 36;
    const USER_AGENT = 'Cub-SC-Updater/1.4';
    const FETCH_TIMEOUT = 20;
    const LOCK_TTL = 60;
    // o CUB do mes passa a valer por volta do dia 5: a busca automatica so age a partir dai
    const FETCH_DAY = 5;
    const FETCH_HOUR = 9;
    // abreviacoes (sem acento) usadas para reconhecer o mes que o Sinduscon publica
    const MESES_ABREV = array('jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez');
    // distancia maxima, depois do rotulo, onde um valor ainda conta como do mesmo bloco
    const VALUE_WINDOW = 300;
    // rotulo de verdade e um elemento curto; paragrafos que citam a palavra sao ignorados
    const LABEL_MAX_LEN = 80;

    /** Formatos aceitos para preco (BR) e para mes de referencia. */
    const PRICE_PATTERNS = array(
        '/R\$\s*(\d{1,3}(?:\.\d{3})*,\d{2})/u',
        '/(\d{1,3}(?:\.\d{3})*,\d{2})/u',
    );
    const MONTH_PATTERNS = array(
        '/([A-Za-zÀ-ÖØ-öø-ÿ]{3,}\s*\/\s*\d{4})/u',
        '/(\d{1,2}\s*\/\s*\d{4})/u',
    );

    private static $option_key = 'cub_sc_updater_data';
    private static $page_slug = 'cub-sc';
    private static $cron_hook = 'cub_sc_updater_daily_event';
    private static $first_hook = 'cub_sc_updater_first_fetch';
    private static $lock_key = 'cub_sc_updater_lock';

    public static function init() {
        add_action('init', array(__CLASS__, 'load_textdomain'));
        add_action('admin_menu', array(__CLASS__, 'admin_menu'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'admin_assets'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_front_assets'));
        add_shortcode('cub_sc', array(__CLASS__, 'shortcode_display'));
        add_action(self::$cron_hook, array(__CLASS__, 'fetch_mensal'));
        add_action(self::$first_hook, array(__CLASS__, 'fetch_and_store'));
        add_action('wp_ajax_cub_sc_manual_fetch', array(__CLASS__, 'ajax_manual_fetch'));
        register_activation_hook(__FILE__, array(__CLASS__, 'activate'));
        register_deactivation_hook(__FILE__, array(__CLASS__, 'deactivate'));
    }

    /** Traducoes de /languages. As strings-fonte ja sao PT-BR. */
    public static function load_textdomain() {
        load_plugin_textdomain(self::TEXT_DOMAIN, false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public static function activate() {
        self::maybe_create_page();
        // evento diario, mas fetch_mensal() so age do dia 5 em diante e enquanto faltar o mes
        if ( ! wp_next_scheduled( self::$cron_hook ) ) {
            wp_schedule_event( self::proxima_corrida(), 'daily', self::$cron_hook );
        }
        // primeira busca fora da requisicao de ativacao: nao trava a tela do admin
        if ( ! wp_next_scheduled( self::$first_hook ) ) {
            wp_schedule_single_event( time() + 10, self::$first_hook );
        }
    }

    public static function deactivate() {
        wp_clear_scheduled_hook( self::$cron_hook );
        wp_clear_scheduled_hook( self::$first_hook );
        delete_transient( self::$lock_key );
    }

    /**
     * Corrida do cron: busca so a partir do dia 5 e enquanto o mes corrente nao
     * estiver guardado. Assim o site do Sinduscon e visitado uma vez por mes, e uma
     * falha no dia 5 e tentada de novo nos dias seguintes (em vez de esperar um mes).
     */
    public static function fetch_mensal() {
        if ( ! self::deve_buscar() ) {
            return;
        }
        self::fetch_and_store();
    }

    /** Diz se vale chamar o Sinduscon agora (guardado o dia minimo e o mes corrente). */
    public static function deve_buscar() {
        $dia_minimo = (int) apply_filters('cub_sc_fetch_day', self::FETCH_DAY);
        $dia_minimo = max(1, min(28, $dia_minimo)); // 28 evita dia inexistente em fevereiro
        if ( (int) wp_date('j') < $dia_minimo ) {
            return false;
        }
        $ultimo = self::get_latest();
        if ( ! $ultimo ) {
            return true; // nunca buscou
        }
        // "para ser usado em" é o mes em que o valor vale: se ja e o mes corrente, nada a fazer.
        // registro antigo sem esse campo cai aqui como pendente, o que forca uma nova leitura.
        return self::chave_do_mes(self::campo($ultimo, 'use_for')) !== self::mes_corrente();
    }

    /** Proximo FETCH_HOUR no fuso do site (usado para ancorar a serie diaria do cron). */
    public static function proxima_corrida() {
        $fuso = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
        $hora = (int) apply_filters('cub_sc_fetch_hour', self::FETCH_HOUR);
        $agora = new DateTimeImmutable('now', $fuso);
        $alvo = $agora->setTime(max(0, min(23, $hora)), 0, 0);
        if ( $alvo <= $agora ) {
            $alvo = $alvo->modify('+1 day');
        }
        return $alvo->getTimestamp();
    }

    /** Mes corrente no fuso do site, no formato 'n/Y' (ex.: '10/2026'). */
    private static function mes_corrente() {
        return sprintf('%02d/%s', (int) wp_date('n'), wp_date('Y'));
    }

    /** 'Outubro/2026' (ou 'Out/2026') -> '10/2026'; vazio quando nao reconhece. */
    private static function chave_do_mes($texto) {
        $texto = strtolower((string) $texto);
        if ( ! preg_match('/(\d{4})/', $texto, $m) ) {
            return '';
        }
        foreach ( self::MESES_ABREV as $indice => $abreviacao ) {
            if ( false !== strpos($texto, $abreviacao) ) {
                return sprintf('%02d/%s', $indice + 1, $m[1]);
            }
        }
        return '';
    }

    /**
     * Cria a pagina /cub-sc/ se ela nao existir.
     * Chamado na ativacao (nao em toda requisicao).
     */
    public static function maybe_create_page() {
        $slug = self::$page_slug;
        $page = get_page_by_path( $slug, OBJECT, 'page' );
        if ( $page && 'trash' !== $page->post_status ) {
            return $page->ID;
        }
        if ( $page ) {
            // existe na lixeira: restaura em vez de criar outra com o mesmo slug
            wp_untrash_post( $page->ID );
            return $page->ID;
        }
        return wp_insert_post( array(
            'post_title' => 'CUB - Santa Catarina',
            'post_name' => $slug,
            'post_content' => '[cub_sc]',
            'post_status' => 'publish',
            'post_type' => 'page'
        ));
    }

    public static function admin_menu() {
        add_menu_page('CUB SC', 'CUB SC', 'manage_options', 'cub-sc-updater', array(__CLASS__, 'admin_page'));
    }

    /**
     * O CSS do shortcode fica registrado no front; o enfileiramento acontece
     * dentro do proprio shortcode, entao paginas sem [cub_sc] nao baixam o arquivo.
     */
    public static function enqueue_front_assets() {
        wp_register_style('cub-sc', plugins_url('assets/cub-sc.css', __FILE__), array(), self::VERSION);
    }

    /** JS do admin em arquivo proprio; nonce e textos vao em um inline anterior. */
    public static function admin_assets($hook) {
        // hook fixo do add_menu_page() acima: toplevel_page_ + slug
        if ( 'toplevel_page_cub-sc-updater' !== $hook ) {
            return;
        }
        wp_enqueue_script('cub-sc-admin', plugins_url('assets/cub-sc-admin.js', __FILE__), array(), self::VERSION, true);
        wp_add_inline_script('cub-sc-admin', 'window.cubScAdmin = ' . wp_json_encode(array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'action' => 'cub_sc_manual_fetch',
            'nonce' => wp_create_nonce('cub_sc_manual_fetch'),
            'i18n' => array(
                'searching' => __('Buscando...', self::TEXT_DOMAIN),
                'reloading' => __('Pronto, recarregando...', self::TEXT_DOMAIN),
                'errorPrefix' => __('Erro:', self::TEXT_DOMAIN),
                'networkError' => __('Erro de rede.', self::TEXT_DOMAIN),
            ),
        )) . ';', 'before');
    }

    public static function admin_page() {
        if ( ! current_user_can('manage_options') ) return;
        $data = get_option(self::$option_key, array());
        if ( ! is_array($data) ) {
            $data = array();
        }
        $history = ( isset($data['history']) && is_array($data['history']) ) ? $data['history'] : array();
        $erro = isset($data['last_error']) ? (string) $data['last_error'] : '';
        $dominio = self::TEXT_DOMAIN;
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('CUB SC Updater', $dominio); ?></h1>
            <p>
                <?php esc_html_e('Última tentativa:', $dominio); ?>
                <strong><?php echo esc_html(self::data_hora(isset($data['last_attempt']) ? $data['last_attempt'] : 0) ?: __('Nunca', $dominio)); ?></strong>
                &nbsp;|&nbsp;
                <?php esc_html_e('Última atualização:', $dominio); ?>
                <strong><?php echo esc_html(self::data_hora(isset($data['last_checked']) ? $data['last_checked'] : 0) ?: __('Nunca', $dominio)); ?></strong>
            </p>
            <?php if ( '' !== $erro ) : ?>
                <div class="notice notice-error inline"><p><strong><?php esc_html_e('A última busca falhou:', $dominio); ?></strong> <?php echo esc_html($erro); ?></p></div>
            <?php endif; ?>
            <p><button id="cub-sc-fetch" class="button button-primary"><?php esc_html_e('Buscar agora', $dominio); ?></button> <span id="cub-sc-status"></span></p>
            <h2><?php esc_html_e('Últimos registros', $dominio); ?></h2>
            <table class="widefat">
                <thead><tr>
                    <th><?php esc_html_e('Data', $dominio); ?></th>
                    <th><?php esc_html_e('Referência', $dominio); ?></th>
                    <th><?php esc_html_e('Uso', $dominio); ?></th>
                    <th><?php esc_html_e('Residencial', $dominio); ?></th>
                    <th><?php esc_html_e('Comercial', $dominio); ?></th>
                </tr></thead>
                <tbody>
                <?php
                if ( $history ) {
                    foreach ( $history as $row ) {
                        if ( ! is_array($row) ) {
                            continue;
                        }
                        echo '<tr><td>'.esc_html(self::data_hora(self::campo($row, 'time'))).'</td>'
                            . '<td>'.esc_html(self::campo($row, 'reference')).'</td>'
                            . '<td>'.esc_html(self::campo($row, 'use_for')).'</td>'
                            . '<td>'.esc_html(self::exibir_preco(self::campo($row, 'residential'))).'</td>'
                            . '<td>'.esc_html(self::exibir_preco(self::campo($row, 'commercial'))).'</td></tr>';
                    }
                } else {
                    echo '<tr><td colspan="5">'.esc_html__('Nenhum registro', $dominio).'</td></tr>';
                }
                ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Extrai o PRIMEIRO preco de um texto.
     * A versao anterior removia todo o resto, o que colava numeros
     * ("2.500,00 a 2.600,00" virava "2.500,002.600,00").
     */
    public static function sanitize_price($str) {
        $str = (string) $str;
        if ( preg_match('/\d{1,3}(?:\.\d{3})*,\d{2}/', $str, $m) ) {
            return $m[0];
        }
        if ( preg_match('/\d+(?:[.,]\d+)?/', $str, $m) ) {
            return $m[0];
        }
        return '';
    }

    /** 'R$ 3.171,96' ou '3.171,96' -> 3171.96 (null se nao houver numero). */
    public static function to_number($str) {
        $limpo = self::sanitize_price($str);
        if ( '' === $limpo ) {
            return null;
        }
        $numero = str_replace(',', '.', str_replace('.', '', $limpo));
        return is_numeric($numero) ? (float) $numero : null;
    }

    /** Formata para exibicao: 'R$ 3.171,96'. */
    public static function format_price($str) {
        $numero = self::to_number($str);
        return null === $numero ? '' : 'R$ ' . number_format($numero, 2, ',', '.');
    }

    /**
     * Le o HTML do Sinduscon e devolve referencia, "para ser usado em",
     * residencial e comercial.
     *
     * A extracao trabalha por blocos: acha o rotulo, sobe no DOM ate o elemento
     * que contem o valor e aceita apenas numero que apareca logo depois do rotulo.
     * Sem isso, um "Comercial" citado no meio do texto da pagina ja fez o plugin
     * publicar o valor do residencial.
     *
     * @param string $html    HTML da pagina.
     * @param string $charset Charset informado no cabecalho HTTP (opcional).
     */
    public static function parse_html_for_cub($html, $charset = '') {
        $result = array(
            'reference' => '',
            'use_for' => '',
            'residential' => '',
            'commercial' => '',
        );

        $html = (string) $html;
        $charset = self::detect_charset($html, $charset);
        if ( 'UTF-8' !== strtoupper($charset) && function_exists('mb_convert_encoding') ) {
            $html = mb_convert_encoding($html, 'UTF-8', $charset);
        }

        $dom = self::load_dom($html);
        if ( $dom ) {
            $xpath = new DOMXPath($dom);
            $result['residential'] = self::value_after_label($xpath, array('Residencial Médio', 'Residencial'), self::PRICE_PATTERNS);
            $result['commercial'] = self::value_after_label($xpath, array('Comercial Médio', 'Comercial'), self::PRICE_PATTERNS);
            $result['reference'] = self::value_after_label($xpath, array('Mês de Referência', 'Mês de referência'), self::MONTH_PATTERNS);
            $result['use_for'] = self::value_after_label($xpath, array('Para ser usado em'), self::MONTH_PATTERNS);
        }

        // rede de seguranca (layout inesperado): mesma ideia, direto no texto,
        // e sempre olhando so a janela que vem depois do rotulo
        if ( '' === $result['residential'] ) {
            $result['residential'] = self::value_after_label_in_text($html, array('Residencial Médio', 'Residencial'), self::PRICE_PATTERNS);
        }
        if ( '' === $result['commercial'] ) {
            $result['commercial'] = self::value_after_label_in_text($html, array('Comercial Médio', 'Comercial'), self::PRICE_PATTERNS);
        }
        if ( '' === $result['reference'] ) {
            $result['reference'] = self::value_after_label_in_text($html, array('Mês de Referência', 'Mês de referência'), self::MONTH_PATTERNS);
        }
        if ( '' === $result['use_for'] ) {
            $result['use_for'] = self::value_after_label_in_text($html, array('Para ser usado em'), self::MONTH_PATTERNS);
        }

        $result['residential'] = self::sanitize_price($result['residential']);
        $result['commercial'] = self::sanitize_price($result['commercial']);
        $result['reference'] = trim($result['reference']);
        $result['use_for'] = trim($result['use_for']);

        return $result;
    }

    private static function detect_charset($html, $from_header = '') {
        if ( $from_header ) {
            return trim($from_header);
        }
        if ( preg_match('/charset=["\']?([\w-]+)/i', $html, $m) ) {
            return $m[1];
        }
        return 'UTF-8';
    }

    /** Carrega o DOM deixando o estado do libxml como estava. */
    private static function load_dom($html) {
        if ( ! class_exists('DOMDocument') ) {
            return null;
        }
        $estado_anterior = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $dom = new DOMDocument();
        $ok = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($estado_anterior);
        return $ok ? $dom : null;
    }

    /**
     * Acha o rotulo no DOM e sobe no maximo 5 niveis ate o bloco que contem o valor.
     * Rotulos longos (paragrafos que apenas citam a palavra) sao descartados.
     */
    private static function value_after_label(DOMXPath $xpath, array $labels, array $patterns) {
        foreach ( $labels as $label ) {
            $nodes = $xpath->query('//*[contains(text(), ' . self::xpath_literal($label) . ')]');
            if ( ! $nodes ) {
                continue;
            }
            foreach ( $nodes as $node ) {
                if ( mb_strlen(trim($node->textContent)) > self::LABEL_MAX_LEN ) {
                    continue;
                }
                $bloco = $node;
                for ( $nivel = 0; $nivel < 5 && $bloco; $nivel++ ) {
                    $valor = self::value_after_label_in_text($bloco->textContent, array($label), $patterns);
                    if ( '' !== $valor ) {
                        return $valor;
                    }
                    $bloco = $bloco->parentNode;
                }
            }
        }
        return '';
    }

    /** Procura o rotulo no texto e aceita valor somente dentro da janela seguinte. */
    private static function value_after_label_in_text($texto, array $labels, array $patterns, $janela = self::VALUE_WINDOW) {
        $texto = (string) $texto;
        foreach ( $labels as $label ) {
            $posicao = mb_stripos($texto, $label);
            if ( false === $posicao ) {
                continue;
            }
            $trecho = mb_substr($texto, $posicao + mb_strlen($label), $janela);
            foreach ( $patterns as $pattern ) {
                if ( preg_match($pattern, $trecho, $m) ) {
                    return $m[1];
                }
            }
        }
        return '';
    }

    private static function xpath_literal($texto) {
        return ( false === strpos($texto, "'") ) ? "'" . $texto . "'" : '"' . $texto . '"';
    }

    public static function fetch_and_store() {
        if ( get_transient(self::$lock_key) ) {
            return new WP_Error('cub_sc_locked', __('Já existe uma busca em andamento.', self::TEXT_DOMAIN));
        }
        set_transient(self::$lock_key, 1, self::LOCK_TTL);

        $data = get_option(self::$option_key, array());
        if ( ! is_array($data) ) {
            $data = array();
        }
        $data['last_attempt'] = time();

        try {
            $url = apply_filters('cub_sc_source_url', self::SOURCE_URL);
            $response = wp_remote_get($url, array(
                'timeout' => self::FETCH_TIMEOUT,
                'user-agent' => self::USER_AGENT,
                'headers' => array(
                    'Accept' => 'text/html,application/xhtml+xml',
                    'Accept-Language' => 'pt-BR,pt;q=0.9',
                ),
            ));

            if ( is_wp_error($response) ) {
                return self::registrar_falha($data, sprintf(__('falha de conexão: %s', self::TEXT_DOMAIN), $response->get_error_message()));
            }
            $codigo = (int) wp_remote_retrieve_response_code($response);
            if ( 200 !== $codigo ) {
                return self::registrar_falha($data, sprintf(__('o site respondeu HTTP %d', self::TEXT_DOMAIN), $codigo));
            }
            $corpo = (string) wp_remote_retrieve_body($response);
            if ( '' === trim($corpo) ) {
                return self::registrar_falha($data, __('o site respondeu vazio', self::TEXT_DOMAIN));
            }

            $parsed = self::parse_html_for_cub($corpo, self::charset_do_cabecalho($response));
            if ( '' === $parsed['residential'] && '' === $parsed['commercial'] ) {
                return self::registrar_falha($data, __('não encontrei os valores na página (o layout do site mudou?)', self::TEXT_DOMAIN));
            }

            $linha = array(
                'time' => time(),
                'reference' => $parsed['reference'],
                'use_for' => $parsed['use_for'],
                'residential' => $parsed['residential'],
                'commercial' => $parsed['commercial'],
                'residential_value' => self::to_number($parsed['residential']),
                'commercial_value' => self::to_number($parsed['commercial']),
            );

            if ( ! isset($data['history']) || ! is_array($data['history']) ) {
                $data['history'] = array();
            }
            // o registro mais recente e o indice 0: comparar com ele (e nao com end(),
            // que devolvia o mais antigo) evita gravar um mes inteiro de duplicatas
            if ( isset($data['history'][0]) && is_array($data['history'][0]) && self::mesma_leitura($data['history'][0], $linha) ) {
                $data['history'][0]['time'] = $linha['time'];
                $data['history'][0]['use_for'] = $linha['use_for'];
            } else {
                array_unshift($data['history'], $linha);
            }

            $limite = (int) apply_filters('cub_sc_history_max', self::HISTORY_MAX);
            if ( $limite > 0 && count($data['history']) > $limite ) {
                $data['history'] = array_slice($data['history'], 0, $limite);
            }

            $data['last_checked'] = time();
            $data['last_success'] = time();
            $data['last_error'] = '';
            update_option(self::$option_key, $data);
            return true;
        } finally {
            delete_transient(self::$lock_key);
        }
    }

    /** Guarda o motivo da falha para o admin mostrar (antes o erro era engolido). */
    private static function registrar_falha(array $data, $mensagem) {
        $data['last_error'] = $mensagem;
        update_option(self::$option_key, $data);
        return new WP_Error('cub_sc_fetch', $mensagem);
    }

    private static function charset_do_cabecalho($response) {
        $tipo = wp_remote_retrieve_header($response, 'content-type');
        if ( $tipo && preg_match('/charset=["\']?([\w-]+)/i', $tipo, $m) ) {
            return $m[1];
        }
        return '';
    }

    private static function mesma_leitura(array $a, array $b) {
        foreach ( array('reference', 'use_for', 'residential', 'commercial') as $campo ) {
            $valor_a = isset($a[$campo]) ? (string) $a[$campo] : '';
            $valor_b = isset($b[$campo]) ? (string) $b[$campo] : '';
            if ( $valor_a !== $valor_b ) {
                return false;
            }
        }
        return true;
    }

    public static function ajax_manual_fetch() {
        check_ajax_referer('cub_sc_manual_fetch', 'nonce');
        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error(array('message' => __('Sem permissão para atualizar.', self::TEXT_DOMAIN)), 403);
        }
        $resultado = self::fetch_and_store();
        if ( is_wp_error($resultado) ) {
            wp_send_json_error(array('message' => $resultado->get_error_message()), 500);
        }
        wp_send_json_success(array('message' => __('Atualizado.', self::TEXT_DOMAIN), 'latest' => self::get_latest()));
    }

    public static function get_latest() {
        $data = get_option(self::$option_key, array());
        if ( isset($data['history'][0]) && is_array($data['history'][0]) ) {
            return $data['history'][0];
        }
        return null;
    }

    /** Le um campo do registro sem disparar aviso quando ele nao existe. */
    private static function campo(array $linha, $chave) {
        return isset($linha[$chave]) ? (string) $linha[$chave] : '';
    }

    /** Data/hora no fuso do site (date() usava o fuso do servidor, quase sempre UTC). */
    private static function data_hora($timestamp, $formato = 'Y-m-d H:i:s') {
        $timestamp = (int) $timestamp;
        if ( $timestamp <= 0 ) {
            return '';
        }
        return function_exists('wp_date') ? wp_date($formato, $timestamp) : date_i18n($formato, $timestamp);
    }

    /** Mostra o preco formatado; se vier em formato antigo, devolve como esta. */
    private static function exibir_preco($valor) {
        $formatado = self::format_price($valor);
        return '' !== $formatado ? $formatado : (string) $valor;
    }

    public static function shortcode_display($atts = array()) {
        wp_enqueue_style('cub-sc');

        $latest = self::get_latest();
        $data = get_option(self::$option_key, array());
        $history = ( isset($data['history']) && is_array($data['history']) ) ? $data['history'] : array();
        $dominio = self::TEXT_DOMAIN;

        ob_start();
        if ( $latest ) {
            $referencia = self::campo($latest, 'reference');
            $use_for = self::campo($latest, 'use_for');
            echo '<div class="cub-sc-box">';
            echo '<h2>'.esc_html__('CUB - Santa Catarina', $dominio).'</h2>';
            if ( '' !== $referencia ) {
                echo '<p><strong>'.esc_html__('Mês de referência:', $dominio).'</strong> '.esc_html($referencia).'</p>';
            }
            if ( '' !== $use_for ) {
                echo '<p><strong>'.esc_html__('Para ser usado em:', $dominio).'</strong> '.esc_html($use_for).'</p>';
            }
            echo '<table class="cub-sc-table">';
            echo '<thead><tr><th>'.esc_html__('Tipo', $dominio).'</th><th>'.esc_html__('Valor', $dominio).'</th></tr></thead>';
            echo '<tbody>';
            echo '<tr><td>'.esc_html__('Residencial', $dominio).'</td><td>'.esc_html(self::exibir_preco(self::campo($latest, 'residential'))).'</td></tr>';
            echo '<tr><td>'.esc_html__('Comercial', $dominio).'</td><td>'.esc_html(self::exibir_preco(self::campo($latest, 'commercial'))).'</td></tr>';
            echo '</tbody></table>';
            echo '<p><small>'.sprintf(esc_html__('Atualizado em %s', $dominio), esc_html(self::data_hora(self::campo($latest, 'time')))).'</small></p>';
            echo '</div>';
        } else {
            echo '<p>'.sprintf(
                esc_html__('Dados do CUB ainda não disponíveis. Clique em %s (requer login).', $dominio),
                '<a href="'.esc_url(admin_url('admin.php?page=cub-sc-updater')).'">'.esc_html__('Buscar agora', $dominio).'</a>'
            ).'</p>';
        }

        if ( $history ) {
            echo '<h3>'.esc_html__('Histórico', $dominio).'</h3>';
            echo '<ul>';
            foreach ( $history as $row ) {
                if ( ! is_array($row) ) {
                    continue;
                }
                echo '<li>'.esc_html(self::data_hora(self::campo($row, 'time'), 'Y-m')).' — '.esc_html(self::campo($row, 'reference')).' — '.esc_html(self::exibir_preco(self::campo($row, 'residential'))).' / '.esc_html(self::exibir_preco(self::campo($row, 'commercial'))).'</li>';
            }
            echo '</ul>';
        }

        return ob_get_clean();
    }

}

Cub_SC_Updater::init();
