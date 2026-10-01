<?php
/**
 * Plugin Name:       Soul UI
 * Plugin URI:        https://alexlimadsgn.framer.ai/
 * Description:       Transforma o painel do WordPress em uma interface moderna e minimalista com controle de acessos integrado, editor Monaco e analytics.
 * Version:           2.2.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Alex Lima
 * Author URI:        https://alexlimadsgn.framer.ai/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       soul-ui
 */

defined('ABSPATH') || exit;

if (!defined('WP_ADMIN_UI_DIR')) {
    define('WP_ADMIN_UI_DIR', plugin_dir_path(__FILE__));
    define('WP_ADMIN_UI_URL', plugin_dir_url(__FILE__));
    define('WP_ADMIN_UI_VERSION', '2.2.0');

    // Lógica de gerenciamento de acessos (herdado do wp-acessos)
    require_once WP_ADMIN_UI_DIR . 'includes/class-access.php';

    // Componentes da interface moderna
    require_once WP_ADMIN_UI_DIR . 'includes/class-assets.php';
    require_once WP_ADMIN_UI_DIR . 'includes/class-sidebar.php';
    require_once WP_ADMIN_UI_DIR . 'includes/class-adminbar.php';
    require_once WP_ADMIN_UI_DIR . 'includes/class-options.php';
    require_once WP_ADMIN_UI_DIR . 'includes/class-login.php';
    require_once WP_ADMIN_UI_DIR . 'includes/class-security.php';
    require_once WP_ADMIN_UI_DIR . 'includes/class-dashboard.php';

    // Módulos integrados adicionais (Cookie Consent, Modo de Manutenção, Editor Monaco e WP Analytics)
    require_once WP_ADMIN_UI_DIR . 'simple-cookie-consent/simple-cookie-consent.php';
    require_once WP_ADMIN_UI_DIR . 'wp-maintenance-contacts/wp-maintenance-contacts.php';
    require_once WP_ADMIN_UI_DIR . 'editor-monaco/editor-monaco.php';
    require_once WP_ADMIN_UI_DIR . 'wp-analitycs/wp-analitycs.php';

    // Gancho de ativação para a tabela de gerenciamento de acessos e analytics
    register_activation_hook(__FILE__, function () {
        global $wpdb;
        $table_name = $wpdb->prefix . 'user_menu_access';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            menu_slug varchar(255) NOT NULL,
            menu_title varchar(255) NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            UNIQUE KEY user_menu (user_id, menu_slug)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        add_option('wp_admin_ui_access_version', '1.0.0');

        // Criação de tabelas do Analytics
        if (class_exists('WP_Analytics_DB')) {
            WP_Analytics_DB::create_tables();
        }
    });

    // Inicialização dos módulos do plugin
    add_action('plugins_loaded', function () {
        WP_Admin_UI_Access::init();
        WP_Admin_UI_Assets::init();
        WP_Admin_UI_Sidebar::init();
        WP_Admin_UI_Adminbar::init();
        WP_Admin_UI_Options::init();
        WP_Admin_UI_Login::init();
        WP_Admin_UI_Dashboard::init();
        WP_Admin_UI_Security::init();
    });

    // Garantir que as tabelas de analytics existam na inicialização do painel administrativo
    add_action('admin_init', function () {
        if (class_exists('WP_Analytics_DB') && get_option('wp_admin_ui_analytics_db_version') !== '1.0.1') {
            WP_Analytics_DB::create_tables();
            update_option('wp_admin_ui_analytics_db_version', '1.0.1');
        }
    });
}
