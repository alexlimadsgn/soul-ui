<?php
/**
 * WP Notion UI — Security & Metadata Handling
 * Handles restrictions like disabling file editing and intercepting plugin details/updates.
 */

defined('ABSPATH') || exit;

class WP_Admin_UI_Security {

    public static function init(): void {
        // Desativar Gutenberg (Block Editor) por padrão
        add_filter('use_block_editor_for_post', '__return_false', 100);
        add_filter('use_block_editor_for_post_type', '__return_false', 100);
        add_filter('use_widgets_block_editor', '__return_false', 100);

        // Remove "Edit" link from the plugins list for this plugin
        add_filter('plugin_action_links', [__CLASS__, 'remove_edit_link'], 10, 2);

        // Hide this plugin from the Plugin File Editor dropdown
        add_filter('all_plugins', [__CLASS__, 'hide_from_editor']);

        // Override Plugin Information modal (Ver detalhes)
        add_filter('plugins_api', [__CLASS__, 'override_plugins_api'], 10, 3);

        // Ocultar texto do rodapé do WordPress ("Obrigado por criar com o WordPress" e versão)
        add_filter('admin_footer_text', '__return_empty_string', 100);
        add_filter('update_footer', '__return_empty_string', 100);

        // Desativar submenus indesejados de Aparência (Tipos de Letra e Padrões)
        add_action('admin_menu', [__CLASS__, 'clean_appearance_submenus'], 999);

        // Prevent update checks against WordPress.org for this custom plugin
        add_filter('site_transient_update_plugins', [__CLASS__, 'disable_external_update_checks']);

        // Sanitiza o retorno do Personalizador para evitar redirecionamentos a update.php
        add_filter('wp_redirect', [__CLASS__, 'sanitize_customizer_return_redirect'], 10, 2);
        add_action('admin_init', [__CLASS__, 'intercept_invalid_theme_update_request']);
    }

    /**
     * Remove submenus indesejados de Aparência (themes.php).
     */
    public static function clean_appearance_submenus(): void {
        // Remove 'Tipos de letra' / Font Library
        remove_submenu_page('themes.php', 'font-library.php');
        remove_submenu_page('themes.php', 'site-editor.php?path=%2Ffont-library');
        remove_submenu_page('themes.php', 'admin.php?page=font-library');

        // Remove 'Padrões' / Patterns (wp_block / site-editor)
        remove_submenu_page('themes.php', 'edit.php?post_type=wp_block');
        remove_submenu_page('themes.php', 'site-editor.php?path=%2Fpatterns');
    }




    /**
     * Remove the "Edit" link in the plugins list.
     */
    public static function remove_edit_link(array $actions, string $plugin_file): array {
        if (strpos($plugin_file, 'admin-ui.php') !== false || strpos($plugin_file, 'soul-ui.php') !== false) {
            unset($actions['edit']);
        }
        return $actions;
    }

    /**
     * Hide the plugin from the File Editor selection dropdown.
     */
    public static function hide_from_editor(array $plugins): array {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        
        if ($screen && 'plugin-editor' === $screen->base) {
            // Find our plugin in the list and remove it
            foreach ($plugins as $file => $data) {
                if (strpos($file, 'admin-ui.php') !== false || strpos($file, 'soul-ui.php') !== false) {
                    unset($plugins[$file]);
                    break;
                }
            }
        }
        
        return $plugins;
    }

    /**
     * Override plugin details modal (Ver detalhes) to prevent loading another plugin from WordPress.org.
     */
    public static function override_plugins_api($res, string $action, object $args) {
        if ('plugin_information' === $action && isset($args->slug)) {
            $slugs = ['admin-ui', 'soul-ui', 'admin-ui/admin-ui.php', 'soul-ui/soul-ui.php'];
            if (in_array($args->slug, $slugs, true)) {
                $res = new stdClass();
                $res->name          = 'Soul UI';
                $res->slug          = 'soul-ui';
                $res->version       = WP_ADMIN_UI_VERSION;
                $res->author        = '<a href="https://alexlimadsgn.framer.ai/" target="_blank" rel="noopener noreferrer">Alex Lima</a>';
                $res->homepage      = 'https://alexlimadsgn.framer.ai/';
                $res->requires      = '6.0';
                $res->tested        = '6.6';
                $res->requires_php  = '7.4';
                $res->downloaded    = 0;
                $res->last_updated  = '2026-08-10';
                $res->sections      = [
                    'description' => 'Transforma o painel do WordPress em uma interface moderna e minimalista com controle de acessos integrado, editor Monaco e analytics.',
                    'changelog'   => '<h4>2.2.0</h4><ul><li>Interface moderna com controle de acessos integrado, Editor Monaco e WP Analytics.</li></ul>',
                ];
                return $res;
            }
        }
        return $res;
    }

    /**
     * Prevent WordPress.org repository from offering update package for this private plugin.
     */
    public static function disable_external_update_checks($transient) {
        if (is_object($transient)) {
            $plugin_files = ['admin-ui/admin-ui.php', 'soul-ui/soul-ui.php', 'soul-ui/admin-ui.php'];
            foreach ($plugin_files as $plugin_file) {
                if (isset($transient->response[$plugin_file])) {
                    unset($transient->response[$plugin_file]);
                }
                if (isset($transient->no_update[$plugin_file])) {
                    unset($transient->no_update[$plugin_file]);
                }
            }
        }
        return $transient;
    }

    /**
     * Sanitiza qualquer redirecionamento originado pelo Personalizador ou que aponte para update.php
     */
    public static function sanitize_customizer_return_redirect(string $location, int $status): string {
        if (strpos($location, 'update.php') !== false || strpos($location, 'action=upload-theme') !== false) {
            return admin_url('themes.php');
        }
        return $location;
    }

    /**
     * Intercepta acessos GET inválidos em update.php para upload de tema sem pacote anexado
     * e redireciona o usuário em segurança de volta para a tela de Temas.
     */
    public static function intercept_invalid_theme_update_request(): void {
        global $pagenow;
        if ('update.php' === $pagenow) {
            $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
            // Se for tentativa de upload sem envio via POST multipart
            if ('upload-theme' === $action && empty($_FILES) && empty($_POST)) {
                wp_safe_redirect(admin_url('themes.php'));
                exit;
            }
        }
    }
}


