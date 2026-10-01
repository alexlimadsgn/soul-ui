<?php
/**
 * Module Name:       Análise de Tráfego e Eventos (WP Analytics)
 * Description:       Rastreamento leve e independente de visualizações de página (Page Views) e tracking de eventos (data-track) com interface nativa do WordPress.
 * Version:           1.0.1
 * Author:            Alex Lima
 * License:           MIT
 * Text Domain:       wp-analytics
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WP_ANALYTICS_VERSION', '1.0.1');
define('WP_ANALYTICS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_ANALYTICS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Inclusão de classes do plugin
require_once WP_ANALYTICS_PLUGIN_DIR . 'includes/class-analytics-db.php';
require_once WP_ANALYTICS_PLUGIN_DIR . 'includes/class-analytics-tracker.php';
require_once WP_ANALYTICS_PLUGIN_DIR . 'includes/class-analytics-rest.php';
require_once WP_ANALYTICS_PLUGIN_DIR . 'includes/class-analytics-settings.php';
require_once WP_ANALYTICS_PLUGIN_DIR . 'includes/class-analytics-admin.php';

// Ativação do plugin: cria tabelas no banco de dados
register_activation_hook(__FILE__, function() {
    WP_Analytics_DB::create_tables();
});

// Inicialização dos módulos
add_action('plugins_loaded', function() {
    WP_Analytics_Tracker::init();
    WP_Analytics_REST::init();
    WP_Analytics_Admin::init();
    WP_Analytics_Settings::init();
});
