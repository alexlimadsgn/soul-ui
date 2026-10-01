<?php
/**
 * Module Name: Editor Monaco
 * Description: Substitui o editor de temas e plugins padrão do WordPress por uma interface IDE moderna com Monaco Editor em menu dedicado.
 * Version: 2.1.0
 * Author: Alex Lima
 * License: GPL2
 * Text Domain: editor-monaco
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_Admin_UI_Editor_Monaco')) {

    class WP_Admin_UI_Editor_Monaco {

        public static function init(): void {
            add_action('admin_menu', [__CLASS__, 'remove_default_editors'], 999);
            add_action('admin_menu', [__CLASS__, 'register_custom_editor_menu']);
            add_action('admin_init', [__CLASS__, 'redirect_default_editors']);
            add_action('admin_head', [__CLASS__, 'dns_prefetch_cdn']);
            add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);

            // Endpoints AJAX
            add_action('wp_ajax_ide_get_files', [__CLASS__, 'ajax_get_files']);
            add_action('wp_ajax_ide_get_file_content', [__CLASS__, 'ajax_get_file_content']);
            add_action('wp_ajax_ide_save_file', [__CLASS__, 'ajax_save_file']);
            add_action('wp_ajax_ide_download_zip', [__CLASS__, 'ajax_download_zip']);
            add_action('wp_ajax_ide_download_theme', [__CLASS__, 'ajax_download_zip']);
            add_action('wp_ajax_ide_create_file', [__CLASS__, 'ajax_create_file']);
            add_action('wp_ajax_ide_create_directory', [__CLASS__, 'ajax_create_directory']);
            add_action('wp_ajax_ide_upload_file', [__CLASS__, 'ajax_upload_file']);
            add_action('wp_ajax_ide_download_file', [__CLASS__, 'ajax_download_file']);
            add_action('wp_ajax_ide_delete_file', [__CLASS__, 'ajax_delete_file']);
        }

        public static function remove_default_editors(): void {
            remove_submenu_page('themes.php', 'theme-editor.php');
            remove_submenu_page('plugins.php', 'plugin-editor.php');
        }

        public static function register_custom_editor_menu(): void {
            $can_themes = current_user_can('edit_themes');
            $can_plugins = current_user_can('edit_plugins');

            if (!$can_themes && !$can_plugins) {
                return;
            }

            $main_cap = $can_themes ? 'edit_themes' : 'edit_plugins';

            // Menu Principal na seção de Funcionalidades
            add_menu_page(
                __('File Editor', 'code-editor'),
                __('File Editor', 'code-editor'),
                $main_cap,
                'code-editor-ide',
                [__CLASS__, 'render_editor_page'],
                'dashicons-editor-code',
                65
            );

            // Submenu 1: Editor de Temas
            if ($can_themes) {
                add_submenu_page(
                    'code-editor-ide',
                    __('Editor de Temas', 'code-editor'),
                    __('Editor de Temas', 'code-editor'),
                    'edit_themes',
                    'code-editor-themes',
                    [__CLASS__, 'render_themes_editor_page']
                );
            }

            // Submenu 2: Editor de Plugins
            if ($can_plugins) {
                add_submenu_page(
                    'code-editor-ide',
                    __('Editor de Plugins', 'code-editor'),
                    __('Editor de Plugins', 'code-editor'),
                    'edit_plugins',
                    'code-editor-plugins',
                    [__CLASS__, 'render_plugins_editor_page']
                );
            }

            // Remove o link automático do menu pai que o WordPress insere como primeiro submenu
            // (evita o item "File Editor" duplicado no flyout da sidebar).
            remove_submenu_page('code-editor-ide', 'code-editor-ide');
        }

        public static function redirect_default_editors(): void {
            global $pagenow;
            if ($pagenow === 'theme-editor.php') {
                wp_safe_redirect(admin_url('admin.php?page=code-editor-themes'));
                exit;
            }
            if ($pagenow === 'plugin-editor.php') {
                wp_safe_redirect(admin_url('admin.php?page=code-editor-plugins'));
                exit;
            }
        }

        public static function dns_prefetch_cdn(): void {
            global $current_screen;
            if ($current_screen && in_array($current_screen->id, [
                'toplevel_page_code-editor-ide',
                'file-editor_page_code-editor-themes',
                'file-editor_page_code-editor-plugins',
                'appearance_page_code-editor-ide',
                'plugins_page_code-editor-plugins'
            ], true)) {
                echo '<link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>' . "\n";
                echo '<link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">' . "\n";
                echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
                echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
            }
        }

        public static function get_themes_list(): array {
            $themes = wp_get_themes();
            $current_theme_slug = get_stylesheet();
            $themes_data = [];

            foreach ($themes as $slug => $theme) {
                $themes_data[] = [
                    'slug'   => $slug,
                    'name'   => $theme->get('Name') . ($slug === $current_theme_slug ? ' (' . __('Ativo', 'code-editor') . ')' : ''),
                    'active' => ($slug === $current_theme_slug)
                ];
            }

            return $themes_data;
        }

        public static function get_plugins_list(): array {
            if (!function_exists('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $all_plugins = get_plugins();
            $plugins_data = [];
            $seen_slugs = [];

            foreach ($all_plugins as $plugin_file => $plugin_info) {
                $folder = dirname($plugin_file);
                $slug = ($folder !== '.') ? $folder : $plugin_file;

                if (isset($seen_slugs[$slug])) {
                    continue;
                }
                $seen_slugs[$slug] = true;

                $plugins_data[] = [
                    'slug' => $slug,
                    'name' => $plugin_info['Name'],
                    'file' => $plugin_file
                ];
            }

            usort($plugins_data, function($a, $b) {
                return strcasecmp($a['name'], $b['name']);
            });

            return $plugins_data;
        }

        public static function resolve_target_root(string $type, string $slug) {
            $type = ($type === 'plugin') ? 'plugin' : 'theme';
            $slug = sanitize_text_field(wp_unslash($slug));
            $slug = str_replace(['../', '..\\', './', '.\\'], '', $slug);
            $slug = trim($slug, '/\\');

            if ($type === 'plugin') {
                if (!current_user_can('edit_plugins')) {
                    return false;
                }
                $plugin_dir_root = realpath(WP_PLUGIN_DIR);
                if (!$plugin_dir_root) {
                    return false;
                }

                if (empty($slug)) {
                    $plugins = self::get_plugins_list();
                    $slug = !empty($plugins) ? $plugins[0]['slug'] : '';
                }

                if (empty($slug)) {
                    return false;
                }

                $target_path = realpath(WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $slug);
                if ($target_path === false || strpos($target_path, $plugin_dir_root) !== 0) {
                    return false;
                }

                return [
                    'type'   => 'plugin',
                    'slug'   => $slug,
                    'path'   => $target_path,
                    'is_dir' => is_dir($target_path),
                    'url'    => plugins_url($slug),
                    'name'   => is_dir($target_path) ? basename($target_path) : basename($slug)
                ];
            } else {
                if (!current_user_can('edit_themes')) {
                    return false;
                }

                if (empty($slug)) {
                    $slug = get_stylesheet();
                }

                $theme = wp_get_theme($slug);
                if (!$theme->exists()) {
                    $theme = wp_get_theme();
                    $slug = get_stylesheet();
                }

                $theme_dir = realpath($theme->get_stylesheet_directory());
                $theme_root = realpath(get_theme_root());

                if (!$theme_dir || !$theme_root || strpos($theme_dir, $theme_root) !== 0) {
                    return false;
                }

                return [
                    'type'   => 'theme',
                    'slug'   => $slug,
                    'path'   => $theme_dir,
                    'is_dir' => true,
                    'url'    => $theme->get_stylesheet_directory_uri(),
                    'name'   => $theme->get('Name')
                ];
            }
        }

        public static function enqueue_assets(string $hook): void {
            if (!in_array($hook, [
                'toplevel_page_code-editor-ide',
                'file-editor_page_code-editor-themes',
                'file-editor_page_code-editor-plugins',
                'toplevel_page_code-editor-plugins',
                'appearance_page_code-editor-ide',
                'plugins_page_code-editor-plugins'
            ], true)) {
                return;
            }

            $current_page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
            $initial_type = isset($_GET['type']) ? sanitize_key($_GET['type']) : '';
            if (empty($initial_type)) {
                $initial_type = ($current_page === 'code-editor-plugins') ? 'plugin' : 'theme';
            }
            $initial_slug = isset($_GET['slug']) ? sanitize_text_field(wp_unslash($_GET['slug'])) : '';

            $target_info = self::resolve_target_root($initial_type, $initial_slug);
            if (!$target_info) {
                $initial_type = 'theme';
                $target_info = self::resolve_target_root($initial_type, '');
            }

            // Google Fonts
            wp_enqueue_style(
                'ide-google-font',
                'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Fira+Code:wght@400;500&display=swap',
                [],
                null
            );

            // Folha de estilos
            wp_enqueue_style(
                'ide-editor-style',
                plugins_url('assets/css/editor.css', __FILE__),
                [],
                '2.1.0'
            );

            // Loader do Monaco Editor
            wp_enqueue_script(
                'monaco-editor-loader',
                'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.39.0/min/vs/loader.min.js',
                [],
                '0.39.0',
                true
            );

            // Script da IDE
            wp_enqueue_script(
                'ide-editor-script',
                plugins_url('assets/js/editor.js', __FILE__),
                ['jquery', 'monaco-editor-loader'],
                '2.1.0',
                true
            );

            wp_localize_script('ide-editor-script', 'ide_params', [
                'ajax_url'            => admin_url('admin-ajax.php'),
                'nonce'               => wp_create_nonce('code-editor-nonce'),
                'download_nonce'      => wp_create_nonce('ide-download-zip-nonce'),
                'current_type'        => $target_info ? $target_info['type'] : 'theme',
                'current_slug'        => $target_info ? $target_info['slug'] : get_stylesheet(),
                'target_name'         => $target_info ? $target_info['name'] : '',
                'target_url'          => $target_info ? $target_info['url'] : '',
                'themes_list'         => self::get_themes_list(),
                'plugins_list'        => self::get_plugins_list(),
                'can_edit_themes'     => current_user_can('edit_themes'),
                'can_edit_plugins'    => current_user_can('edit_plugins')
            ]);
        }

        public static function get_files_tree(string $dir, string $base_dir = ''): array {
            if (empty($base_dir)) {
                $base_dir = $dir;
            }
            $result = [];

            if (!is_dir($dir)) {
                return $result;
            }

            $items = scandir($dir);
            if (!$items) {
                return $result;
            }

            $exclude = [
                '.', '..', '.git', '.github', '.gitignore', 'node_modules', 
                'vendor', '.DS_Store', 'package-lock.json', 'package.json',
                '.vscode', '.idea'
            ];

            $dirs = [];
            $files = [];

            foreach ($items as $item) {
                if (in_array($item, $exclude, true)) {
                    continue;
                }

                $full_path = $dir . DIRECTORY_SEPARATOR . $item;
                $relative_path = str_replace($base_dir . DIRECTORY_SEPARATOR, '', $full_path);
                $relative_path = str_replace('\\', '/', $relative_path);

                if (is_dir($full_path)) {
                    $dirs[] = [
                        'name'     => $item,
                        'path'     => $relative_path,
                        'type'     => 'directory',
                        'children' => self::get_files_tree($full_path, $base_dir)
                    ];
                } else {
                    $files[] = [
                        'name' => $item,
                        'path' => $relative_path,
                        'type' => 'file'
                    ];
                }
            }

            usort($dirs, function($a, $b) {
                return strcasecmp($a['name'], $b['name']);
            });
            usort($files, function($a, $b) {
                return strcasecmp($a['name'], $b['name']);
            });

            return array_merge($dirs, $files);
        }

        public static function ajax_get_files(): void {
            check_ajax_referer('code-editor-nonce', 'nonce');

            $type = isset($_POST['type']) ? sanitize_key($_POST['type']) : 'theme';
            $slug = isset($_POST['slug']) ? sanitize_text_field(wp_unslash($_POST['slug'])) : '';

            $target = self::resolve_target_root($type, $slug);
            if (!$target) {
                wp_send_json_error(['message' => __('Diretório não encontrado ou permissão negada.', 'code-editor')]);
            }

            if (!$target['is_dir']) {
                $tree = [
                    [
                        'name' => basename($target['slug']),
                        'path' => basename($target['slug']),
                        'type' => 'file'
                    ]
                ];
            } else {
                $tree = self::get_files_tree($target['path']);
            }

            wp_send_json_success([
                'target_name' => $target['name'],
                'target_type' => $target['type'],
                'target_slug' => $target['slug'],
                'target_url'  => $target['url'],
                'tree'        => $tree
            ]);
        }

        public static function ajax_get_file_content(): void {
            check_ajax_referer('code-editor-nonce', 'nonce');

            $type = isset($_POST['type']) ? sanitize_key($_POST['type']) : 'theme';
            $slug = isset($_POST['slug']) ? sanitize_text_field(wp_unslash($_POST['slug'])) : '';

            $target = self::resolve_target_root($type, $slug);
            if (!$target) {
                wp_send_json_error(['message' => __('Permissão negada ou destino inválido.', 'code-editor')]);
            }

            if (empty($_POST['file_path'])) {
                wp_send_json_error(['message' => __('Caminho do arquivo não especificado.', 'code-editor')]);
            }

            $file_path = sanitize_text_field(wp_unslash($_POST['file_path']));
            $file_path = str_replace(['../', '..\\'], '', $file_path);
            $file_path = ltrim($file_path, '/\\');

            $base_dir = $target['is_dir'] ? $target['path'] : dirname($target['path']);
            $requested_file = realpath($base_dir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file_path));

            if ($requested_file === false || strpos($requested_file, $base_dir) !== 0) {
                wp_send_json_error(['message' => __('Acesso não autorizado ou arquivo inexistente.', 'code-editor')]);
            }

            if (is_dir($requested_file)) {
                wp_send_json_error(['message' => __('O caminho especificado é um diretório.', 'code-editor')]);
            }

            $content = file_get_contents($requested_file);
            if ($content === false) {
                wp_send_json_error(['message' => __('Falha ao ler o conteúdo do arquivo.', 'code-editor')]);
            }

            wp_send_json_success([
                'content'   => $content,
                'file_path' => $file_path
            ]);
        }

        public static function ajax_save_file(): void {
            check_ajax_referer('code-editor-nonce', 'nonce');

            $type = isset($_POST['type']) ? sanitize_key($_POST['type']) : 'theme';
            $slug = isset($_POST['slug']) ? sanitize_text_field(wp_unslash($_POST['slug'])) : '';

            $target = self::resolve_target_root($type, $slug);
            if (!$target) {
                wp_send_json_error(['message' => __('Permissão negada ou destino inválido.', 'code-editor')]);
            }

            if (empty($_POST['file_path'])) {
                wp_send_json_error(['message' => __('Caminho do arquivo não especificado.', 'code-editor')]);
            }

            $file_path = sanitize_text_field(wp_unslash($_POST['file_path']));
            $file_path = str_replace(['../', '..\\'], '', $file_path);
            $file_path = ltrim($file_path, '/\\');

            $base_dir = $target['is_dir'] ? $target['path'] : dirname($target['path']);
            $target_file = realpath($base_dir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file_path));

            if ($target_file === false) {
                $parent_dir = realpath(dirname($base_dir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file_path)));
                if ($parent_dir === false || strpos($parent_dir, $base_dir) !== 0) {
                    wp_send_json_error(['message' => __('Caminho de arquivo inválido.', 'code-editor')]);
                }
                $target_file = $parent_dir . DIRECTORY_SEPARATOR . basename($file_path);
            } else {
                if (strpos($target_file, $base_dir) !== 0) {
                    wp_send_json_error(['message' => __('Acesso não autorizado fora do diretório permitido.', 'code-editor')]);
                }
            }

            if (is_dir($target_file)) {
                wp_send_json_error(['message' => __('Não é possível gravar dados em um diretório.', 'code-editor')]);
            }

            $content = isset($_POST['content']) ? wp_unslash($_POST['content']) : '';
            $written = file_put_contents($target_file, $content);

            if ($written === false) {
                wp_send_json_error(['message' => __('Não foi possível gravar no arquivo. Verifique permissões de escrita.', 'code-editor')]);
            }

            wp_send_json_success(['message' => __('Arquivo salvo com sucesso!', 'code-editor')]);
        }

        public static function ajax_download_zip(): void {
            if (!isset($_GET['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'])), 'ide-download-zip-nonce')) {
                if (!isset($_GET['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'])), 'ide-download-theme-nonce')) {
                    wp_die(__('Nonce inválido ou expirado. Recarregue a página.', 'code-editor'));
                }
            }

            $type = isset($_GET['type']) ? sanitize_key($_GET['type']) : 'theme';
            $slug = isset($_GET['slug']) ? sanitize_text_field(wp_unslash($_GET['slug'])) : '';

            $target = self::resolve_target_root($type, $slug);
            if (!$target) {
                wp_die(__('Permissão negada ou destino inválido.', 'code-editor'));
            }

            if (!class_exists('ZipArchive')) {
                wp_die(__('A extensão ZipArchive do PHP não está disponível neste servidor.', 'code-editor'));
            }

            $target_dir = $target['path'];
            $zip_slug = sanitize_file_name($target['slug']);

            $tmp_file = tempnam(sys_get_temp_dir(), 'wp_ide_') . '.zip';
            $zip = new ZipArchive();
            if ($zip->open($tmp_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                wp_die(__('Não foi possível criar o arquivo ZIP.', 'code-editor'));
            }

            $exclude = ['.git', '.github', '.gitignore', 'node_modules', 'vendor', '.DS_Store', '.vscode', '.idea'];

            if ($target['is_dir']) {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($target_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST
                );

                foreach ($iterator as $file) {
                    $file_path = $file->getRealPath();
                    $relative  = substr($file_path, strlen($target_dir) + 1);
                    $relative  = str_replace(DIRECTORY_SEPARATOR, '/', $relative);

                    $skip = false;
                    foreach ($exclude as $ex) {
                        if (strpos($relative, $ex) === 0 || strpos($relative, '/' . $ex . '/') !== false) {
                            $skip = true;
                            break;
                        }
                    }
                    if ($skip) {
                        continue;
                    }

                    if ($file->isDir()) {
                        $zip->addEmptyDir($zip_slug . '/' . $relative);
                    } elseif ($file->isFile()) {
                        $zip->addFile($file_path, $zip_slug . '/' . $relative);
                    }
                }
            } else {
                $zip->addFile($target_dir, basename($target_dir));
            }

            $zip->close();

            $zip_name = $zip_slug . '.zip';
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $zip_name . '"');
            header('Content-Length: ' . filesize($tmp_file));
            header('Pragma: no-cache');
            header('Expires: 0');

            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            readfile($tmp_file);
            @unlink($tmp_file);
            exit;
        }

        public static function ajax_create_file(): void {
            check_ajax_referer('code-editor-nonce', 'nonce');

            $type = isset($_POST['type']) ? sanitize_key($_POST['type']) : 'theme';
            $slug = isset($_POST['slug']) ? sanitize_text_field(wp_unslash($_POST['slug'])) : '';

            $target = self::resolve_target_root($type, $slug);
            if (!$target || !$target['is_dir']) {
                wp_send_json_error(['message' => __('Permissão negada ou destino inválido.', 'code-editor')]);
            }

            if (empty($_POST['file_path'])) {
                wp_send_json_error(['message' => __('Caminho do arquivo não especificado.', 'code-editor')]);
            }

            $file_path = sanitize_text_field(wp_unslash($_POST['file_path']));
            $file_path = str_replace(['../', '..\\'], '', $file_path);
            $file_path = ltrim($file_path, '/\\');

            $base_dir = $target['path'];
            $target_file = $base_dir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file_path);

            $parent_dir = dirname($target_file);
            $real_parent = realpath($parent_dir);

            if ($real_parent !== false && strpos($real_parent, $base_dir) !== 0) {
                wp_send_json_error(['message' => __('Caminho fora do diretório permitido.', 'code-editor')]);
            }

            if (file_exists($target_file)) {
                wp_send_json_error(['message' => __('O arquivo já existe.', 'code-editor')]);
            }

            if (!is_dir($parent_dir)) {
                if (!wp_mkdir_p($parent_dir)) {
                    wp_send_json_error(['message' => __('Falha ao criar subdiretórios.', 'code-editor')]);
                }
            }

            if (file_put_contents($target_file, '') === false) {
                wp_send_json_error(['message' => __('Não foi possível criar o arquivo. Verifique permissões.', 'code-editor')]);
            }

            wp_send_json_success([
                'message'   => __('Arquivo criado com sucesso!', 'code-editor'),
                'file_path' => $file_path
            ]);
        }

        public static function ajax_create_directory(): void {
            check_ajax_referer('code-editor-nonce', 'nonce');

            $type = isset($_POST['type']) ? sanitize_key($_POST['type']) : 'theme';
            $slug = isset($_POST['slug']) ? sanitize_text_field(wp_unslash($_POST['slug'])) : '';

            $target = self::resolve_target_root($type, $slug);
            if (!$target || !$target['is_dir']) {
                wp_send_json_error(['message' => __('Permissão negada ou destino inválido.', 'code-editor')]);
            }

            if (empty($_POST['dir_path'])) {
                wp_send_json_error(['message' => __('Caminho do diretório não especificado.', 'code-editor')]);
            }

            $dir_path = sanitize_text_field(wp_unslash($_POST['dir_path']));
            $dir_path = str_replace(['../', '..\\'], '', $dir_path);
            $dir_path = ltrim($dir_path, '/\\');

            $base_dir = $target['path'];
            $target_dir = $base_dir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $dir_path);

            if (is_dir($target_dir)) {
                wp_send_json_error(['message' => __('A pasta já existe.', 'code-editor')]);
            }

            if (!wp_mkdir_p($target_dir)) {
                wp_send_json_error(['message' => __('Não foi possível criar a pasta. Verifique permissões.', 'code-editor')]);
            }

            wp_send_json_success(['message' => __('Pasta criada com sucesso!', 'code-editor')]);
        }

        public static function ajax_upload_file(): void {
            check_ajax_referer('code-editor-nonce', 'nonce');

            $type = isset($_POST['type']) ? sanitize_key($_POST['type']) : 'theme';
            $slug = isset($_POST['slug']) ? sanitize_text_field(wp_unslash($_POST['slug'])) : '';

            $target = self::resolve_target_root($type, $slug);
            if (!$target || !$target['is_dir']) {
                wp_send_json_error(['message' => __('Permissão negada ou destino inválido.', 'code-editor')]);
            }

            if (empty($_FILES['files'])) {
                wp_send_json_error(['message' => __('Nenhum arquivo enviado.', 'code-editor')]);
            }

            $destination = isset($_POST['destination_path']) ? sanitize_text_field(wp_unslash($_POST['destination_path'])) : '';
            $destination = str_replace(['../', '..\\'], '', $destination);
            $destination = ltrim($destination, '/\\');

            $base_dir = $target['path'];
            $target_dir = $base_dir;
            if (!empty($destination)) {
                $target_dir .= DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $destination);
            }

            $target_dir = realpath($target_dir);
            if ($target_dir === false || strpos($target_dir, $base_dir) !== 0) {
                wp_send_json_error(['message' => __('Diretório de destino inválido.', 'code-editor')]);
            }

            $files = $_FILES['files'];
            $uploaded_count = 0;
            $errors = [];

            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                    $errors[] = sprintf(__('Erro no arquivo %s: código %d', 'code-editor'), $files['name'][$i], $files['error'][$i]);
                    continue;
                }

                $file_name = sanitize_file_name($files['name'][$i]);
                $target_file = $target_dir . DIRECTORY_SEPARATOR . $file_name;

                if (move_uploaded_file($files['tmp_name'][$i], $target_file)) {
                    $uploaded_count++;
                } else {
                    $errors[] = sprintf(__('Falha ao gravar arquivo %s.', 'code-editor'), $files['name'][$i]);
                }
            }

            if ($uploaded_count > 0) {
                $msg = sprintf(__('%d arquivo(s) enviados com sucesso.', 'code-editor'), $uploaded_count);
                if (!empty($errors)) {
                    $msg .= ' ' . implode(' | ', $errors);
                }
                wp_send_json_success(['message' => $msg]);
            } else {
                wp_send_json_error(['message' => __('Nenhum arquivo pôde ser salvo: ', 'code-editor') . implode(' | ', $errors)]);
            }
        }

        public static function ajax_download_file(): void {
            if (!isset($_GET['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'])), 'code-editor-nonce')) {
                wp_die(__('Nonce inválido ou expirado.', 'code-editor'));
            }

            $type = isset($_GET['type']) ? sanitize_key($_GET['type']) : 'theme';
            $slug = isset($_GET['slug']) ? sanitize_text_field(wp_unslash($_GET['slug'])) : '';

            $target = self::resolve_target_root($type, $slug);
            if (!$target) {
                wp_die(__('Permissão negada ou destino inválido.', 'code-editor'));
            }

            if (empty($_GET['file_path'])) {
                wp_die(__('Caminho do arquivo não especificado.', 'code-editor'));
            }

            $file_path = sanitize_text_field(wp_unslash($_GET['file_path']));
            $file_path = str_replace(['../', '..\\'], '', $file_path);
            $file_path = ltrim($file_path, '/\\');

            $base_dir = $target['is_dir'] ? $target['path'] : dirname($target['path']);
            $target_file = realpath($base_dir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file_path));

            if ($target_file === false || strpos($target_file, $base_dir) !== 0 || is_dir($target_file)) {
                wp_die(__('Arquivo inválido ou inexistente.', 'code-editor'));
            }

            $file_name = basename($target_file);
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $file_name . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($target_file));

            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            readfile($target_file);
            exit;
        }

        public static function ajax_delete_file(): void {
            check_ajax_referer('code-editor-nonce', 'nonce');

            $type = isset($_POST['type']) ? sanitize_key($_POST['type']) : 'theme';
            $slug = isset($_POST['slug']) ? sanitize_text_field(wp_unslash($_POST['slug'])) : '';

            $target = self::resolve_target_root($type, $slug);
            if (!$target) {
                wp_send_json_error(['message' => __('Permissão negada ou destino inválido.', 'code-editor')]);
            }

            if (empty($_POST['file_path'])) {
                wp_send_json_error(['message' => __('Caminho do arquivo não especificado.', 'code-editor')]);
            }

            $file_path = sanitize_text_field(wp_unslash($_POST['file_path']));
            $file_path = str_replace(['../', '..\\'], '', $file_path);
            $file_path = ltrim($file_path, '/\\');

            $base_dir = $target['is_dir'] ? $target['path'] : dirname($target['path']);
            $target_file = realpath($base_dir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file_path));

            if ($target_file === false || strpos($target_file, $base_dir) !== 0) {
                wp_send_json_error(['message' => __('Acesso negado ou arquivo inválido.', 'code-editor')]);
            }

            if (is_dir($target_file)) {
                wp_send_json_error(['message' => __('A exclusão de diretórios não é permitida por segurança.', 'code-editor')]);
            }

            if (!file_exists($target_file)) {
                wp_send_json_error(['message' => __('O arquivo não existe no servidor.', 'code-editor')]);
            }

            if (unlink($target_file)) {
                wp_send_json_success(['message' => __('Arquivo excluído com sucesso!', 'code-editor')]);
            } else {
                wp_send_json_error(['message' => __('Não foi possível excluir o arquivo. Verifique permissões.', 'code-editor')]);
            }
        }

        public static function render_themes_editor_page(): void {
            $_GET['type'] = 'theme';
            self::render_editor_page('theme');
        }

        public static function render_plugins_editor_page(): void {
            $_GET['type'] = 'plugin';
            self::render_editor_page('plugin');
        }

        public static function render_editor_page(string $context_type = ''): void {
            $can_themes = current_user_can('edit_themes');
            $can_plugins = current_user_can('edit_plugins');

            if (!$can_themes && !$can_plugins) {
                wp_die(__('Você não tem permissão para acessar esta página.', 'code-editor'));
            }

            if (empty($context_type)) {
                $current_page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
                $context_type = isset($_GET['type']) ? sanitize_key($_GET['type']) : '';
                if (empty($context_type)) {
                    $context_type = ($current_page === 'code-editor-plugins') ? 'plugin' : ($can_themes ? 'theme' : 'plugin');
                }
            }

            if ($context_type === 'plugin' && !$can_plugins) {
                $context_type = 'theme';
            } elseif ($context_type === 'theme' && !$can_themes) {
                $context_type = 'plugin';
            }

            $themes = self::get_themes_list();
            $plugins = self::get_plugins_list();
            $current_theme_name = wp_get_theme()->get('Name');
            $page_title = ($context_type === 'plugin') ? __('Editor de Plugins', 'code-editor') : __('Editor de Temas', 'code-editor');
            ?>
            <div class="wrap ide-wrap">
                <div class="ide-header">
                    <div class="ide-logo-area">
                        <h1 class="ide-title"><?php echo esc_html($page_title); ?></h1>
                    </div>
                    
                    <div class="ide-active-file-info">
                        <span class="ide-icon-file-status">📄</span>
                        <span id="ide-active-filename" class="ide-active-filename"><?php _e('Nenhum arquivo selecionado', 'code-editor'); ?></span>
                        <span id="ide-modified-dot" class="ide-modified-dot is-hidden">●</span>
                    </div>

                    <div class="ide-header-actions">
                        <button type="button" id="ide-undo-button" class="ide-btn ide-btn-secondary ide-btn-icon-only" title="<?php esc_attr_e('Desfazer (Ctrl+Z)', 'code-editor'); ?>" disabled>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 5.5 5.5a5.5 5.5 0 0 1-5.5 5.5H11"/></svg>
                        </button>
                        <button type="button" id="ide-redo-button" class="ide-btn ide-btn-secondary ide-btn-icon-only" title="<?php esc_attr_e('Refazer (Ctrl+Y)', 'code-editor'); ?>" disabled>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 14 5-5-5-5"/><path d="M20 9H9.5A5.5 5.5 0 0 0 4 14.5A5.5 5.5 0 0 0 9.5 20H13"/></svg>
                        </button>
                        <button type="button" id="ide-wordwrap-button" class="ide-btn ide-btn-secondary ide-btn-icon-only" title="<?php esc_attr_e('Alternar Quebra de Linha', 'code-editor'); ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M3 12h15a3 3 0 0 1 0 6h-4"/><path d="m14 15-2 2 2 2"/><path d="M3 18h7"/></svg>
                        </button>
                        <button type="button" id="ide-download-zip-button" class="ide-btn ide-btn-download" title="<?php esc_attr_e('Fazer download do pacote completo como ZIP', 'code-editor'); ?>">
                            <svg class="ide-btn-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            <span class="ide-btn-label"><?php _e('Download ZIP', 'code-editor'); ?></span>
                            <span class="ide-btn-loader ide-btn-loader--dark is-hidden"></span>
                        </button>
                        <button type="button" id="ide-save-button" class="ide-btn ide-btn-primary" disabled>
                            <span class="ide-btn-label"><?php _e('Salvar Alterações', 'code-editor'); ?></span>
                            <span class="ide-btn-loader is-hidden"></span>
                        </button>
                    </div>
                </div>

                <div class="ide-body">
                    <!-- Painel do Editor (Central/Esquerda) -->
                    <div class="ide-editor-container">
                        <!-- Subheader do Editor: Contexto e Seletor -->
                        <div class="ide-editor-nav">
                            <div class="ide-editor-nav-left">
                                <div class="ide-nav-context-badge">
                                    <?php if ($context_type === 'plugin'): ?>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4"/><path d="m4.93 10.93 2.83-2.83"/><path d="M2 18h4"/><path d="M20 6v4"/><path d="m19.07 19.07-2.83-2.83"/><path d="M22 18h-4"/><path d="m16 8 2-2"/><path d="m6 16-2 2"/><rect width="8" height="8" x="8" y="8" rx="2"/></svg>
                                        <span class="ide-nav-context-text"><?php _e('Plugin Selecionado:', 'code-editor'); ?></span>
                                    <?php else: ?>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/></svg>
                                        <span class="ide-nav-context-text"><?php _e('Tema Selecionado:', 'code-editor'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="ide-editor-nav-right">
                                <?php if ($context_type === 'theme' && $can_themes): ?>
                                    <div class="ide-select-wrapper ide-theme-select-wrap">
                                        <select id="ide-theme-select" class="ide-target-select ide-theme-select-box">
                                            <?php foreach ($themes as $t): ?>
                                                <option value="<?php echo esc_attr($t['slug']); ?>" <?php selected($t['active']); ?>>
                                                    <?php echo esc_html($t['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php elseif ($context_type === 'plugin' && $can_plugins): ?>
                                    <div class="ide-select-wrapper ide-plugin-select-wrap">
                                        <select id="ide-plugin-select" class="ide-target-select ide-plugin-select-box">
                                            <?php foreach ($plugins as $p): ?>
                                                <option value="<?php echo esc_attr($p['slug']); ?>">
                                                    <?php echo esc_html($p['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Área Principal do Editor -->
                        <div class="ide-editor-main-area">
                            <div id="ide-monaco-editor" class="ide-monaco-editor"></div>
                            
                            <!-- Preview de imagem -->
                            <div id="ide-image-preview" class="ide-image-preview is-hidden">
                                <div class="ide-image-preview-wrapper">
                                    <div class="ide-image-preview-box">
                                        <img id="ide-preview-img" src="" alt="Preview" />
                                    </div>
                                    <div class="ide-image-info">
                                        <span class="ide-image-dimension-label"><?php _e('Dimensões:', 'code-editor'); ?></span>
                                        <span id="ide-image-dimensions" class="ide-image-info-val">-</span>
                                        <span class="ide-image-path-label"><?php _e('Caminho:', 'code-editor'); ?></span>
                                        <span id="ide-image-path" class="ide-image-info-val">-</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Tela inicial quando não há nenhum arquivo aberto -->
                            <div id="ide-welcome-screen" class="ide-welcome-screen">
                                <div class="ide-welcome-content">
                                    <div class="ide-welcome-icon">⚡</div>
                                    <h2 id="ide-welcome-title"><?php _e('Editor de Código', 'code-editor'); ?></h2>
                                    <p id="ide-welcome-desc"><?php printf(__('Selecione um arquivo na barra lateral à direita para começar a editar <strong id="ide-current-target-name">%s</strong>.', 'code-editor'), esc_html($current_theme_name)); ?></p>
                                    <div class="ide-welcome-shortcuts">
                                        <div class="ide-shortcut-row">
                                            <kbd>Ctrl</kbd> + <kbd>S</kbd> <span><?php _e('Salvar arquivo atual', 'code-editor'); ?></span>
                                        </div>
                                        <div class="ide-shortcut-row">
                                            <kbd>Ctrl</kbd> + <kbd>F</kbd> <span><?php _e('Buscar texto no arquivo', 'code-editor'); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Barra Lateral de Arquivos (Direita) -->
                    <div class="ide-sidebar">
                        <div class="ide-sidebar-header">
                            <span id="ide-sidebar-header-title"><?php _e('Arquivos', 'code-editor'); ?></span>
                            <div class="ide-sidebar-actions">
                                <!-- Atualizar -->
                                <button type="button" id="ide-refresh-tree" class="ide-sidebar-refresh-btn" title="<?php esc_attr_e('Atualizar árvore de arquivos', 'code-editor'); ?>">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
                                </button>

                                <!-- Menu Dropdown (More Options) -->
                                <div class="ide-dropdown-container">
                                    <button type="button" id="ide-more-btn" class="ide-sidebar-btn" title="<?php esc_attr_e('Mais opções', 'code-editor'); ?>">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"/><circle cx="12" cy="5" r="1"/><circle cx="12" cy="19" r="1"/></svg>
                                    </button>
                                    <div id="ide-sidebar-dropdown" class="ide-dropdown-menu">
                                        <a href="#" id="ide-upload-file-btn" class="ide-dropdown-item">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                            <span><?php _e('Upload de Arquivos', 'code-editor'); ?></span>
                                        </a>
                                        <a href="#" id="ide-download-file-btn" class="ide-dropdown-item disabled">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                            <span><?php _e('Download Arquivo Atual', 'code-editor'); ?></span>
                                        </a>
                                        <div class="ide-dropdown-divider"></div>
                                        <a href="#" id="ide-new-file-btn" class="ide-dropdown-item">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
                                            <span><?php _e('Novo Arquivo', 'code-editor'); ?></span>
                                        </a>
                                        <a href="#" id="ide-new-folder-btn" class="ide-dropdown-item">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/><line x1="12" y1="14" x2="12" y2="10"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
                                            <span><?php _e('Criar Pasta', 'code-editor'); ?></span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <!-- Input oculto para Upload de Arquivos -->
                            <input type="file" id="ide-file-uploader" class="ide-hidden-uploader" multiple />
                        </div>
                        <div class="ide-sidebar-content">
                            <div id="ide-files-tree" class="ide-files-tree-list">
                                <div class="ide-tree-loading">
                                    <span class="ide-tree-spinner"></span>
                                    <span><?php _e('Carregando arquivos...', 'code-editor'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal de Confirmação de Exclusão -->
                <div id="ide-delete-modal" class="ide-modal-overlay is-hidden">
                    <div class="ide-modal-box">
                        <div class="ide-modal-header">
                            <h3><?php _e('Excluir Arquivo', 'code-editor'); ?></h3>
                        </div>
                        <div class="ide-modal-body">
                            <p><?php _e('Tem certeza que deseja excluir permanentemente o arquivo', 'code-editor'); ?> <strong id="ide-delete-file-display"></strong>?</p>
                            <p class="ide-modal-warning"><?php _e('Esta ação não pode ser desfeita.', 'code-editor'); ?></p>
                        </div>
                        <div class="ide-modal-footer">
                            <button type="button" id="ide-delete-cancel-btn" class="ide-btn ide-btn-secondary"><?php _e('Cancelar', 'code-editor'); ?></button>
                            <button type="button" id="ide-delete-confirm-btn" class="ide-btn ide-btn-danger"><?php _e('Excluir', 'code-editor'); ?></button>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }
    }

    WP_Admin_UI_Editor_Monaco::init();
}
