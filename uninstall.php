<?php
/**
 * Limpeza executada quando o plugin e EXCLUIDO no WordPress (nao na desativacao).
 *
 * Decisao do projeto: limpeza total. Sao removidos o historico (option), a pagina
 * /cub-sc/, os agendamentos do WP-Cron e a trava por transient. Em multisite, a
 * limpeza e repetida em cada site da rede.
 */

if ( ! defined('WP_UNINSTALL_PLUGIN') ) {
    exit;
}

/**
 * Limpa os dados de um site (o atual ou um da rede, ja sob switch_to_blog()).
 */
function cub_sc_uninstall_site($option_key, $page_slug, $cron_hooks, $lock_key) {
    foreach ( $cron_hooks as $hook ) {
        wp_clear_scheduled_hook($hook);
    }

    delete_transient($lock_key);

    $page = get_page_by_path($page_slug, OBJECT, 'page');
    if ( $page && ! empty($page->ID) ) {
        // forca a exclusao: na lixeira a pagina continuaria ocupando o slug
        wp_delete_post($page->ID, true);
    }

    delete_option($option_key);
}

$cub_sc_option_key = 'cub_sc_updater_data';
$cub_sc_page_slug = 'cub-sc';
$cub_sc_cron_hooks = array('cub_sc_updater_daily_event', 'cub_sc_updater_first_fetch');
$cub_sc_lock_key = 'cub_sc_updater_lock';

if ( is_multisite() ) {
    foreach ( get_sites(array('fields' => 'ids')) as $cub_sc_site_id ) {
        switch_to_blog($cub_sc_site_id);
        cub_sc_uninstall_site($cub_sc_option_key, $cub_sc_page_slug, $cub_sc_cron_hooks, $cub_sc_lock_key);
        restore_current_blog();
    }
} else {
    cub_sc_uninstall_site($cub_sc_option_key, $cub_sc_page_slug, $cub_sc_cron_hooks, $cub_sc_lock_key);
}
