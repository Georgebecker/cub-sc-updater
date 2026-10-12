<?php
/**
 * Testes locais do CUB SC Updater — rodam SEM WordPress.
 *
 * Usa os HTML reais guardados em tests/fixtures/ para checar o parser,
 * o histórico e o tratamento de falhas. Serve para provar que uma mudança
 * não quebrou a leitura da página.
 *
 *   php tests/test-cub-sc.php
 *
 * Sai com codigo 0 quando tudo passa e 1 quando algo falha.
 */

define('ABSPATH', dirname(__DIR__));

// ---------------------------------------------------------------- stubs do WordPress
$GLOBALS['wp_opcoes'] = array();
$GLOBALS['wp_transients'] = array();
$GLOBALS['wp_http'] = null;
$GLOBALS['wp_json'] = null;
$GLOBALS['wp_nonce_verificado'] = null;
$GLOBALS['wp_styles_registrados'] = array();
$GLOBALS['wp_styles_enfileirados'] = array();
$GLOBALS['wp_scripts_enfileirados'] = array();
$GLOBALS['wp_inline_scripts'] = array();
$GLOBALS['wp_textdomain'] = null;
$GLOBALS['wp_agora'] = null;

function get_option($nome, $padrao = false) { return isset($GLOBALS['wp_opcoes'][$nome]) ? $GLOBALS['wp_opcoes'][$nome] : $padrao; }
function update_option($nome, $valor) { $GLOBALS['wp_opcoes'][$nome] = $valor; return true; }
function get_transient($nome) { return isset($GLOBALS['wp_transients'][$nome]) ? $GLOBALS['wp_transients'][$nome] : false; }
function set_transient($nome, $valor, $ttl = 0) { $GLOBALS['wp_transients'][$nome] = $valor; return true; }
function delete_transient($nome) { unset($GLOBALS['wp_transients'][$nome]); return true; }
function apply_filters($tag, $valor) { return $valor; }
function wp_date($formato, $timestamp = null, $timezone = null) {
    // relogio controlavel: $GLOBALS['wp_agora'] (timestamp) ou null para a hora real
    $quando = ($timestamp === null) ? ($GLOBALS['wp_agora'] ?: time()) : $timestamp;
    return date($formato, $quando);
}
function wp_timezone() { return new DateTimeZone('America/Sao_Paulo'); }
function date_i18n($formato, $timestamp = null) {
    $quando = ($timestamp === null) ? ($GLOBALS['wp_agora'] ?: time()) : $timestamp;
    return date($formato, $quando);
}
function esc_html($texto) { return htmlspecialchars((string) $texto, ENT_QUOTES); }
function esc_attr($texto) { return htmlspecialchars((string) $texto, ENT_QUOTES); }
function esc_url($url) { return $url; }
function admin_url($caminho = '') { return 'https://exemplo.test/wp-admin/' . $caminho; }
function wp_create_nonce($acao) { return 'nonce-de-teste'; }
function check_ajax_referer($acao, $campo = false) { $GLOBALS['wp_nonce_verificado'] = $acao; return true; }
function current_user_can($capacidade) { return true; }
function wp_send_json_success($dados = null) { $GLOBALS['wp_json'] = array('success', $dados); }
function wp_send_json_error($dados = null, $status = null) { $GLOBALS['wp_json'] = array('error', $dados, $status); }
function add_action(...$args) {}
function add_shortcode(...$args) {}
function register_activation_hook(...$args) {}
function register_deactivation_hook(...$args) {}
function wp_next_scheduled(...$args) { return false; }
function wp_schedule_event(...$args) {}
function wp_schedule_single_event(...$args) {}
function wp_clear_scheduled_hook(...$args) {}
function wp_remote_get($url, $args = array()) { return $GLOBALS['wp_http']; }
function wp_remote_retrieve_response_code($r) { return is_array($r) && isset($r['response']['code']) ? $r['response']['code'] : 0; }
function wp_remote_retrieve_body($r) { return is_array($r) && isset($r['body']) ? $r['body'] : ''; }
function wp_remote_retrieve_header($r, $nome) { return is_array($r) && isset($r['headers'][$nome]) ? $r['headers'][$nome] : ''; }
function is_wp_error($coisa) { return $coisa instanceof WP_Error; }

// i18n: os stubs devolvem o texto-fonte (as strings ja sao PT-BR)
function __($texto, $dominio = null) { return $texto; }
function _e($texto, $dominio = null) { echo $texto; }
function esc_html__($texto, $dominio = null) { return htmlspecialchars((string) $texto, ENT_QUOTES); }
function esc_attr__($texto, $dominio = null) { return htmlspecialchars((string) $texto, ENT_QUOTES); }
function esc_html_e($texto, $dominio = null) { echo htmlspecialchars((string) $texto, ENT_QUOTES); }
function esc_attr_e($texto, $dominio = null) { echo htmlspecialchars((string) $texto, ENT_QUOTES); }
function load_plugin_textdomain($dominio, $depreciado = false, $caminho = '') { $GLOBALS['wp_textdomain'] = $caminho; return true; }
function plugin_basename($arquivo) { return 'cub-sc/' . basename($arquivo); }

// assets
function plugins_url($caminho = '', $arquivo = '') { return 'https://exemplo.test/wp-content/plugins/cub-sc/' . ltrim($caminho, '/'); }
function wp_register_style($handle, $src = false, $deps = array(), $ver = false, $media = 'all') { $GLOBALS['wp_styles_registrados'][$handle] = $src; }
function wp_enqueue_style($handle, $src = '', $deps = array(), $ver = false, $media = 'all') { $GLOBALS['wp_styles_enfileirados'][$handle] = $handle; }
function wp_enqueue_script($handle, $src = '', $deps = array(), $ver = false, $no_footer = false) { $GLOBALS['wp_scripts_enfileirados'][$handle] = $src; }
function wp_add_inline_script($handle, $dados, $posicao = 'after') { $GLOBALS['wp_inline_scripts'][$handle] = $dados; return true; }
function wp_json_encode($dado) { return json_encode($dado); }

class WP_Error {
    private $codigo;
    private $mensagem;
    public function __construct($codigo = '', $mensagem = '') {
        $this->codigo = $codigo;
        $this->mensagem = $mensagem;
    }
    public function get_error_code() { return $this->codigo; }
    public function get_error_message() { return $this->mensagem; }
}

require dirname(__DIR__) . '/cub-sc-updater.php';

// ---------------------------------------------------------------- micro framework
$GLOBALS['total'] = 0;
$GLOBALS['falhas'] = 0;

function verifica($descricao, $obtido, $esperado) {
    $GLOBALS['total']++;
    if ($obtido === $esperado) {
        echo "  ok     $descricao\n";
        return;
    }
    $GLOBALS['falhas']++;
    printf("  FALHA  %s\n           esperado: %s\n           obtido:   %s\n", $descricao, var_export($esperado, true), var_export($obtido, true));
}

function fixture($arquivo) {
    return file_get_contents(__DIR__ . '/fixtures/' . $arquivo);
}

/** Timestamp de uma data/hora no fuso do site (para controlar o "agora" dos testes). */
function agora_local($texto) {
    return (new DateTimeImmutable($texto, new DateTimeZone('America/Sao_Paulo')))->getTimestamp();
}

function resposta($corpo, $codigo = 200) {
    return array(
        'response' => array('code' => $codigo),
        'headers' => array('content-type' => 'text/html; charset=UTF-8'),
        'body' => $corpo,
    );
}

// ---------------------------------------------------------------- 1. parser com HTML real
foreach (array('cub-mensal-2026-10-12.html', 'cub-home-2026-10-12.html') as $arquivo) {
    echo "\n[parser] $arquivo\n";
    $lido = Cub_SC_Updater::parse_html_for_cub(fixture($arquivo), 'UTF-8');
    verifica('referencia', $lido['reference'], 'Setembro/2026');
    verifica('para ser usado em', $lido['use_for'], 'Outubro/2026');
    verifica('residencial', $lido['residential'], '3.171,96');
    verifica('comercial', $lido['commercial'], '3.374,19');
}

// ---------------------------------------------------------------- 2. valores
echo "\n[valores]\n";
verifica('sanitize_price nao cola dois numeros', Cub_SC_Updater::sanitize_price('2.500,00 a 2.600,00'), '2.500,00');
verifica('sanitize_price em faixa', Cub_SC_Updater::sanitize_price('R$ 2.500,00 - R$ 2.600,00'), '2.500,00');
verifica('sanitize_price sem numero', Cub_SC_Updater::sanitize_price('indefinido'), '');
verifica('to_number converte', Cub_SC_Updater::to_number('R$ 3.171,96'), 3171.96);
verifica('format_price formata', Cub_SC_Updater::format_price('3.171,96'), 'R$ 3.171,96');

// ---------------------------------------------------------------- 3. historico
echo "\n[historico]\n";
$GLOBALS['wp_opcoes'] = array();
$GLOBALS['wp_http'] = resposta(fixture('cub-mensal-2026-10-12.html'));
for ($dia = 1; $dia <= 7; $dia++) {
    Cub_SC_Updater::fetch_and_store();
}
$guardado = $GLOBALS['wp_opcoes']['cub_sc_updater_data'];
verifica('7 buscas iguais => 1 registro', count($guardado['history']), 1);
verifica('ultima tentativa registrada', $guardado['last_attempt'] > 0, true);
verifica('sem erro registrado', $guardado['last_error'], '');

$GLOBALS['wp_http'] = resposta(str_replace('Setembro/2026', 'Outubro/2026', fixture('cub-mensal-2026-10-12.html')));
Cub_SC_Updater::fetch_and_store();
$guardado = $GLOBALS['wp_opcoes']['cub_sc_updater_data'];
verifica('mudou a referencia => 2 registros', count($guardado['history']), 2);
verifica('o mais novo fica no indice 0', $guardado['history'][0]['reference'], 'Outubro/2026');
verifica('valor numerico guardado', $guardado['history'][0]['residential_value'], 3171.96);
verifica('valor antigo preservado', $guardado['history'][1]['reference'], 'Setembro/2026');

// ---------------------------------------------------------------- 4. falhas
echo "\n[falhas]\n";
$GLOBALS['wp_http'] = resposta('erro interno', 500);
$resultado = Cub_SC_Updater::fetch_and_store();
verifica('HTTP 500 devolve WP_Error', is_wp_error($resultado), true);
verifica('motivo guardado para o admin', $GLOBALS['wp_opcoes']['cub_sc_updater_data']['last_error'], 'o site respondeu HTTP 500');
verifica('historico preservado na falha', count($GLOBALS['wp_opcoes']['cub_sc_updater_data']['history']), 2);

$GLOBALS['wp_http'] = resposta('<html><body>pagina sem os valores do CUB</body></html>');
$resultado = Cub_SC_Updater::fetch_and_store();
verifica('layout diferente => erro explicito', $resultado->get_error_message(), 'não encontrei os valores na página (o layout do site mudou?)');

// ---------------------------------------------------------------- 5. travas
echo "\n[travas]\n";
$GLOBALS['wp_transients']['cub_sc_updater_lock'] = 1;
$resultado = Cub_SC_Updater::fetch_and_store();
verifica('busca simultanea e bloqueada', $resultado->get_error_code(), 'cub_sc_locked');
verifica('trava liberada ao final', get_transient('cub_sc_updater_lock'), 1);
unset($GLOBALS['wp_transients']['cub_sc_updater_lock']);

// ---------------------------------------------------------------- 6. ajax
echo "\n[ajax]\n";
$GLOBALS['wp_http'] = resposta(fixture('cub-mensal-2026-10-12.html'));
$GLOBALS['wp_json'] = null;
Cub_SC_Updater::ajax_manual_fetch();
verifica('ajax confere o nonce', $GLOBALS['wp_nonce_verificado'], 'cub_sc_manual_fetch');
verifica('ajax responde sucesso', $GLOBALS['wp_json'][0], 'success');
verifica('ajax devolve os dados', is_array($GLOBALS['wp_json'][1]['latest']), true);

// ---------------------------------------------------------------- 7. agenda (dia 5)
echo "\n[agenda]\n";
$GLOBALS['wp_opcoes'] = array();

$GLOBALS['wp_agora'] = agora_local('2026-10-03 10:00:00');
verifica('antes do dia 5 nao busca, mesmo sem dados', Cub_SC_Updater::deve_buscar(), false);

$GLOBALS['wp_agora'] = agora_local('2026-10-05 10:00:00');
verifica('no dia 5, sem dados, busca', Cub_SC_Updater::deve_buscar(), true);

$GLOBALS['wp_http'] = resposta(fixture('cub-mensal-2026-10-12.html'));
Cub_SC_Updater::fetch_mensal();
$guardado = $GLOBALS['wp_opcoes']['cub_sc_updater_data'];
verifica('fetch_mensal grava a leitura do mes', count($guardado['history']), 1);
verifica('"para ser usado em" gravado', $guardado['history'][0]['use_for'], 'Outubro/2026');
verifica('com o mes corrente guardado, nao busca de novo', Cub_SC_Updater::deve_buscar(), false);

$GLOBALS['wp_agora'] = agora_local('2026-10-20 10:00:00');
verifica('passado o dia 5, o mes ja guardado continua parado', Cub_SC_Updater::deve_buscar(), false);

$GLOBALS['wp_agora'] = agora_local('2026-11-05 10:00:00');
verifica('mes novo => busca outra vez', Cub_SC_Updater::deve_buscar(), true);

$GLOBALS['wp_opcoes']['cub_sc_updater_data']['history'][0]['use_for'] = '';
verifica('registro antigo sem "para ser usado em" => busca (auto-cura)', Cub_SC_Updater::deve_buscar(), true);

// falha no dia 5 nao pode custar um mes inteiro: no dia seguinte tenta de novo
$GLOBALS['wp_opcoes'] = array();
$GLOBALS['wp_agora'] = agora_local('2026-10-05 10:00:00');
$GLOBALS['wp_http'] = resposta('erro interno', 500);
Cub_SC_Updater::fetch_mensal();
$GLOBALS['wp_agora'] = agora_local('2026-10-06 10:00:00');
verifica('falhou no dia 5 => tenta de novo no dia 6', Cub_SC_Updater::deve_buscar(), true);

$proxima = Cub_SC_Updater::proxima_corrida();
$local = (new DateTimeImmutable('@' . $proxima))->setTimezone(new DateTimeZone('America/Sao_Paulo'));
verifica('proxima corrida no futuro', $proxima > time(), true);
verifica('proxima corrida e as 09:00 do site', $local->format('H:i'), '09:00');
$GLOBALS['wp_agora'] = null;

// ---------------------------------------------------------------- 8. assets e i18n
echo "\n[assets]\n";
Cub_SC_Updater::load_textdomain();
verifica('textdomain aponta para languages/', $GLOBALS['wp_textdomain'], 'cub-sc/languages');

Cub_SC_Updater::enqueue_front_assets();
verifica('css do shortcode registrado', isset($GLOBALS['wp_styles_registrados']['cub-sc']), true);
verifica('css usa a pasta assets/', false !== strpos($GLOBALS['wp_styles_registrados']['cub-sc'], 'assets/cub-sc.css'), true);

Cub_SC_Updater::admin_assets('outra_pagina_qualquer');
verifica('script nao carrega fora da pagina do plugin', $GLOBALS['wp_scripts_enfileirados'], array());

Cub_SC_Updater::admin_assets('toplevel_page_cub-sc-updater');
verifica('script do admin enfileirado', isset($GLOBALS['wp_scripts_enfileirados']['cub-sc-admin']), true);
$inline = isset($GLOBALS['wp_inline_scripts']['cub-sc-admin']) ? $GLOBALS['wp_inline_scripts']['cub-sc-admin'] : '';
verifica('inline leva o nonce', false !== strpos($inline, 'nonce-de-teste'), true);
verifica('inline leva a action', false !== strpos($inline, 'cub_sc_manual_fetch'), true);
verifica('inline leva os textos traduzidos', false !== strpos($inline, 'Buscando...'), true);
verifica('inline leva a url do ajax', false !== strpos($inline, 'admin-ajax.php'), true);

$GLOBALS['wp_styles_enfileirados'] = array();
// o shortcode precisa de um registro guardado: monta um limpo em vez de herdar o da secao anterior
$GLOBALS['wp_opcoes'] = array();
$GLOBALS['wp_http'] = resposta(fixture('cub-mensal-2026-10-12.html'));
Cub_SC_Updater::fetch_and_store();
$saida = Cub_SC_Updater::shortcode_display();
verifica('shortcode enfileira o css', isset($GLOBALS['wp_styles_enfileirados']['cub-sc']), true);
verifica('shortcode sem estilo inline', false !== strpos($saida, 'style="'), false);
verifica('shortcode usa a classe do css', false !== strpos($saida, 'class="cub-sc-table"'), true);
verifica('shortcode mostra o mes de referencia', false !== strpos($saida, 'Setembro/2026'), true);

foreach ( array('assets/cub-sc.css', 'assets/cub-sc-admin.js', 'uninstall.php', 'readme.txt', 'languages/README.md', 'build.ps1') as $arquivo ) {
    verifica("$arquivo existe", file_exists(dirname(__DIR__) . '/' . $arquivo), true);
}

// ---------------------------------------------------------------- 9. coerencia para o wp.org
// O revisor do wp.org recusa quando o Text Domain nao casa com o slug (pasta do zip), quando a versao
// do cabecalho diverge do Stable tag e quando o dominio de traducao nao e texto literal (padrao WPCS).
echo "\n[wp.org]\n";

function cabecalho_de($fonte, $campo) {
    return preg_match('/^' . preg_quote($campo, '/') . ':\s*(.+)$/mi', $fonte, $m) ? trim($m[1]) : '';
}

$fonte_plugin = file_get_contents(dirname(__DIR__) . '/cub-sc-updater.php');
$text_domain_cabecalho = cabecalho_de($fonte_plugin, 'Text Domain');
$versao_cabecalho = cabecalho_de($fonte_plugin, 'Version');
$build = file_get_contents(dirname(__DIR__) . '/build.ps1');
$slug = preg_match('/\$pasta\s*=\s*[\'"]([^\'"]+)[\'"]/', $build, $m) ? $m[1] : '';
$readme = file_get_contents(dirname(__DIR__) . '/readme.txt');
$stable = preg_match('/^Stable tag:\s*(\S+)/mi', $readme, $m) ? trim($m[1]) : '';

verifica('cabecalho declara Text Domain', $text_domain_cabecalho, 'cub-sc-updater');
verifica('slug do pacote igual ao Text Domain', $slug, $text_domain_cabecalho);
verifica('const TEXT_DOMAIN igual ao cabecalho', Cub_SC_Updater::TEXT_DOMAIN, $text_domain_cabecalho);
verifica('versao do cabecalho igual a const VERSION', $versao_cabecalho, Cub_SC_Updater::VERSION);
verifica('Stable tag do readme igual a versao do plugin', $stable, $versao_cabecalho);
verifica('readme tem as secoes do wp.org', (bool) preg_match('/== Description ==.*== Installation ==.*== Frequently Asked Questions ==.*== Changelog ==/s', $readme), true);
verifica('traducao sempre com dominio literal', preg_match('/\b(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\((?:[^;]|\n)*?(?:\$dominio|self::TEXT_DOMAIN)/', $fonte_plugin), 0);

// descricao curta = primeira linha depois do bloco de cabecalho; o wp.org limita a 150 caracteres
$descricao_curta = '';
foreach ( preg_split('/\r?\n/', $readme) as $indice => $linha ) {
    $linha = trim($linha);
    if ( 0 === $indice || '' === $linha ) {
        continue;
    }
    if ( preg_match('/^[A-Za-z][A-Za-z ]*:/', $linha) ) {
        continue; // campo do cabecalho
    }
    $descricao_curta = $linha;
    break;
}
verifica('descricao curta presente', $descricao_curta, 'Mantém o CUB (Custo Unitário Básico) de Santa Catarina atualizado: busca no Sinduscon-Fpolis e publica em /cub-sc/ pelo shortcode [cub_sc].');
verifica('descricao curta com ate 150 caracteres', mb_strlen($descricao_curta) <= 150, true);
verifica('readme declara os servicos externos', false !== strpos($readme, '== External services =='), true);
verifica('readme tem o campo Donate link', (bool) preg_match('/^Donate link:\s*https?:\/\/\S+/mi', $readme), true);
verifica('licenca do readme igual a do cabecalho', cabecalho_de($readme, 'License'), cabecalho_de($fonte_plugin, 'License'));
verifica('URI da licenca igual no readme e no PHP', cabecalho_de($readme, 'License URI'), cabecalho_de($fonte_plugin, 'License URI'));
verifica('cabecalho PHP sem "Tested up to"', (bool) preg_match('/^Tested up to:/mi', $fonte_plugin), false);

// ---------------------------------------------------------------- resumo
printf("\n%d verificacoes, %d falha(s)\n", $GLOBALS['total'], $GLOBALS['falhas']);
exit($GLOBALS['falhas'] > 0 ? 1 : 0);