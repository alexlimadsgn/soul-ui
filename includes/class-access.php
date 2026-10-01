<?php
defined('ABSPATH') || exit;

class WP_Admin_UI_Access
{
    private static $instance = null;
    private $table_name;
    private $original_menu = array();
    private $original_submenu = array();

    public static function init()
    {
        self::get_instance();
    }

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'user_menu_access';

        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_save_user_menu_access', array($this, 'ajax_save_user_menu_access'));
        
        // Filtro para modificar menus no admin
        add_action('admin_menu', array($this, 'filter_admin_menus'), 999);

        // Validação de acesso direto via URL no admin_init
        add_action('admin_init', array($this, 'check_admin_page_access'));
    }

    /**
     * Normaliza slugs para evitar inconsistências (ex: customize.php com query args)
     */
    public static function normalize_slug($slug)
    {
        if (strpos($slug, 'customize.php') === 0) {
            return 'customize.php';
        }
        return $slug;
    }

    /**
     * Obtém os menus permitidos para um determinado usuário
     */
    public static function get_user_allowed_menus($user_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'user_menu_access';

        $allowed = $wpdb->get_col($wpdb->prepare(
            "SELECT menu_slug FROM {$table_name} WHERE user_id = %d",
            $user_id
        ));

        return is_array($allowed) ? $allowed : array();
    }

    /**
     * Verifica se um menu pai (grupo) é permitido
     */
    public static function is_parent_allowed($parent_slug, $allowed_menus)
    {
        $norm_parent = self::normalize_slug($parent_slug);

        if (in_array('parent:' . $norm_parent, $allowed_menus, true)) {
            return true;
        }

        // Compatibilidade com formato antigo sem prefixo
        if (in_array($norm_parent, $allowed_menus, true)) {
            return true;
        }

        // Se houver qualquer submenu do formato parent_slug::child_slug permitido
        foreach ($allowed_menus as $m) {
            if (strpos($m, $norm_parent . '::') === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verifica se um item/submenu específico é permitido para o usuário
     */
    public static function is_menu_allowed($item_key, $user_id = 0)
    {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // Administrador principal (ID 1) sempre tem acesso total
        if ($user_id === 1) {
            return true;
        }

        $allowed_menus = self::get_user_allowed_menus($user_id);

        if (empty($allowed_menus)) {
            return true;
        }

        $normalized_key = self::normalize_slug($item_key);

        // 1. Verificação exata da chave salva
        if (in_array($normalized_key, $allowed_menus, true)) {
            return true;
        }

        // 2. Chave composta 'parent_slug::child_slug'
        if (strpos($normalized_key, '::') !== false) {
            list($parent_slug, $child_slug) = explode('::', $normalized_key, 2);
            $norm_child = self::normalize_slug($child_slug);
            $norm_parent = self::normalize_slug($parent_slug);

            // Se o filho isolado estiver em allowed_menus (formato antigo)
            if (in_array($norm_child, $allowed_menus, true)) {
                return true;
            }

            // Se parent e child tem o mesmo slug no formato antigo (ex: themes.php)
            if ($norm_child === $norm_parent && in_array($norm_parent, $allowed_menus, true)) {
                $has_new_format = false;
                foreach ($allowed_menus as $m) {
                    if (strpos($m, $norm_parent . '::') === 0 || strpos($m, 'parent:' . $norm_parent) === 0) {
                        $has_new_format = true;
                        break;
                    }
                }
                if (!$has_new_format) {
                    return true;
                }
            }
        } else {
            // Chave simples sem '::' (ex: 'parent:themes.php')
            if (strpos($normalized_key, 'parent:') === 0) {
                $raw_parent = substr($normalized_key, 7);
                if (in_array($raw_parent, $allowed_menus, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Verifica se o acesso a um slug requisitado via URL é permitido
     */
    private static function is_menu_allowed_for_request($slug, $user_id, $allowed_menus)
    {
        $norm_slug = self::normalize_slug($slug);

        if (in_array($norm_slug, $allowed_menus, true)) {
            return true;
        }

        if (in_array('parent:' . $norm_slug, $allowed_menus, true)) {
            return true;
        }

        foreach ($allowed_menus as $allowed) {
            if (strpos($allowed, '::') !== false) {
                list($p, $c) = explode('::', $allowed, 2);
                if (self::normalize_slug($c) === $norm_slug) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Adiciona menu no admin
     */
    public function add_admin_menu()
    {
        add_menu_page(
            __('Gerir Acessos', 'admin-ui'),
            __('Gerir Acessos', 'admin-ui'),
            'manage_options',
            'gerir-acessos',
            array($this, 'render_admin_page'),
            'dashicons-groups',
            30
        );
    }

    /**
     * Carrega scripts e estilos
     */
    public function enqueue_admin_scripts($hook)
    {
        if ('toplevel_page_gerir-acessos' !== $hook && strpos($hook, 'gerir-acessos') === false) {
            return;
        }

        wp_enqueue_style('ga-admin-style', WP_ADMIN_UI_URL . 'assets/admin-style.css', array(), WP_ADMIN_UI_VERSION);
        wp_enqueue_script('ga-admin-script', WP_ADMIN_UI_URL . 'assets/admin-script.js', array('jquery'), WP_ADMIN_UI_VERSION, true);

        wp_localize_script('ga-admin-script', 'ga_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ga_ajax_nonce')
        ));
    }

    /**
     * Obtém todos os menus do admin em estrutura hierárquica
     * Retorna array de grupos: cada grupo tem 'parent' e 'children'
     */
    private function get_all_admin_menus()
    {
        global $menu, $submenu;

        $groups = array();

        if (empty($menu)) {
            return $groups;
        }

        foreach ($menu as $item) {
            if (empty($item[0]) || empty($item[2])) {
                continue;
            }

            if (!current_user_can($item[1])) {
                continue;
            }

            $parent_slug = self::normalize_slug($item[2]);

            $parent = array(
                'slug'  => $parent_slug,
                'key'   => 'parent:' . $parent_slug,
                'title' => wp_strip_all_tags($item[0]),
                'icon'  => isset($item[6]) ? $item[6] : 'dashicons-admin-generic',
            );

            $children = array();

            if (isset($submenu[$item[2]]) && is_array($submenu[$item[2]])) {
                foreach ($submenu[$item[2]] as $subitem) {
                    if (empty($subitem[0]) || empty($subitem[2])) {
                        continue;
                    }
                    $child_slug = self::normalize_slug($subitem[2]);
                    $children[] = array(
                        'slug'  => $child_slug,
                        'key'   => $parent_slug . '::' . $child_slug,
                        'title' => wp_strip_all_tags($subitem[0]),
                    );
                }
            }

            // Submenus de "Adicionar" para Temas e Plugins
            if ($parent_slug === 'themes.php' && current_user_can('install_themes')) {
                $add_key = 'themes.php::theme-install.php';
                $exists = array_filter($children, fn($c) => $c['key'] === $add_key);
                if (empty($exists)) {
                    array_unshift($children, array(
                        'slug'  => 'theme-install.php',
                        'key'   => $add_key,
                        'title' => __('Adicionar Tema', 'admin-ui'),
                    ));
                }
            }

            if ($parent_slug === 'plugins.php' && current_user_can('install_plugins')) {
                $add_key = 'plugins.php::plugin-install.php';
                $exists = array_filter($children, fn($c) => $c['key'] === $add_key);
                if (empty($exists)) {
                    array_unshift($children, array(
                        'slug'  => 'plugin-install.php',
                        'key'   => $add_key,
                        'title' => __('Adicionar Plugin', 'admin-ui'),
                    ));
                }
            }

            $groups[] = array(
                'parent'   => $parent,
                'children' => $children,
            );
        }

        return $groups;
    }

    /**
     * Renderiza página de administração
     */
    public function render_admin_page()
    {
        global $wpdb;

        $users        = get_users(array('orderby' => 'display_name'));
        $menu_groups  = $this->get_all_admin_menus();
        $selected_user = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

        $user_menus_array = array();
        if ($selected_user > 0) {
            $user_menus = $wpdb->get_results($wpdb->prepare(
                "SELECT menu_slug FROM {$this->table_name} WHERE user_id = %d",
                $selected_user
            ), ARRAY_A);
            foreach ($user_menus as $row) {
                $user_menus_array[] = $row['menu_slug'];
            }
        }

?>
        <div class="wrap ga-wrap">
            <h1><?php _e('Gerir Acessos', 'admin-ui'); ?></h1>

            <div class="ga-container">

                <!-- Seletor de usuário -->
                <div class="ga-user-selector">
                    <div class="ga-user-selector-inner">
                        <div class="ga-user-field">
                            <label for="ga-user-select"><?php _e('Usuário', 'admin-ui'); ?></label>
                            <select id="ga-user-select" name="user_id">
                                <option value=""><?php _e('— Selecione um usuário —', 'admin-ui'); ?></option>
                                <?php foreach ($users as $user) : ?>
                                    <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($selected_user, $user->ID); ?>>
                                        <?php echo esc_html($user->display_name) . ' (' . esc_html($user->user_email) . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button id="ga-load-menu-access" class="button button-primary">
                                <?php _e('Carregar Acessos', 'admin-ui'); ?>
                            </button>
                        </div>
                        <?php if ($selected_user > 0) : ?>
                        <div class="ga-user-badge">
                            <span class="dashicons dashicons-admin-users"></span>
                            <?php
                            $u = get_userdata($selected_user);
                            echo esc_html($u->display_name);
                            ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($selected_user > 0) : ?>

                <form id="ga-menu-access-form">
                    <input type="hidden" name="user_id" value="<?php echo esc_attr($selected_user); ?>">
                    <?php wp_nonce_field('save_menu_access', 'ga_nonce'); ?>

                    <!-- Barra de ações superiores -->
                    <div class="ga-toolbar">
                        <div class="ga-toolbar-left">
                            <span class="ga-section-title"><?php _e('Permissões de Menu', 'admin-ui'); ?></span>
                            <span class="ga-badge" id="ga-count-badge">0 selecionados</span>
                        </div>
                        <div class="ga-toolbar-right">
                            <button type="button" class="ga-btn-text" id="ga-select-all"><?php _e('Selecionar tudo', 'admin-ui'); ?></button>
                            <span class="ga-divider">|</span>
                            <button type="button" class="ga-btn-text" id="ga-deselect-all"><?php _e('Limpar', 'admin-ui'); ?></button>
                            <span class="ga-divider">|</span>
                            <button type="button" class="ga-btn-text" id="ga-expand-all"><?php _e('Expandir tudo', 'admin-ui'); ?></button>
                            <span class="ga-divider">|</span>
                            <button type="button" class="ga-btn-text" id="ga-collapse-all"><?php _e('Recolher tudo', 'admin-ui'); ?></button>
                        </div>
                    </div>

                    <!-- Grupos de menus -->
                    <div class="ga-groups">
                    <?php foreach ($menu_groups as $group) :
                        $parent   = $group['parent'];
                        $children = $group['children'];

                        $parent_checked   = self::is_menu_allowed($parent['key'], $selected_user);
                        $children_checked = array();
                        foreach ($children as $child) {
                            $children_checked[$child['key']] = self::is_menu_allowed($child['key'], $selected_user);
                        }
                        $any_child_checked = !empty(array_filter($children_checked));

                        $group_open = ($parent_checked || $any_child_checked) ? ' ga-group--open' : '';
                    ?>
                        <div class="ga-group<?php echo $group_open; ?>">

                            <!-- Cabeçalho do grupo (menu pai) -->
                            <div class="ga-group-header">
                                <label class="ga-parent-label">
                                    <input type="checkbox"
                                           class="ga-parent-check"
                                           name="menus[]"
                                           value="<?php echo esc_attr($parent['key']); ?>"
                                           <?php checked($parent_checked); ?>
                                           data-group="<?php echo esc_attr($parent['slug']); ?>">
                                    <span class="ga-parent-icon">
                                        <?php
                                        $icon = $parent['icon'];
                                        if (strpos($icon, 'dashicons-') === 0) {
                                            echo '<span class="dashicons ' . esc_attr($icon) . '"></span>';
                                        } elseif (strpos($icon, 'data:image') === 0) {
                                            echo '<img src="' . esc_attr($icon) . '" width="16" height="16" />';
                                        } else {
                                            echo '<span class="dashicons dashicons-admin-generic"></span>';
                                        }
                                        ?>
                                    </span>
                                    <span class="ga-parent-title"><?php echo esc_html($parent['title']); ?></span>
                                </label>

                                <?php if (!empty($children)) : ?>
                                <button type="button" class="ga-toggle-btn" title="Expandir/Recolher">
                                    <span class="ga-toggle-icon">&#9658;</span>
                                    <span class="ga-submenu-count"><?php echo count($children); ?> submenu<?php echo count($children) !== 1 ? 's' : ''; ?></span>
                                </button>
                                <?php endif; ?>
                            </div>

                            <!-- Submenus -->
                            <?php if (!empty($children)) : ?>
                            <div class="ga-group-children">
                                <?php foreach ($children as $child) : ?>
                                <label class="ga-child-label">
                                    <input type="checkbox"
                                           class="ga-child-check"
                                           name="menus[]"
                                           value="<?php echo esc_attr($child['key']); ?>"
                                           <?php checked($children_checked[$child['key']]); ?>
                                           data-parent="<?php echo esc_attr($parent['slug']); ?>">
                                    <span class="ga-child-title"><?php echo esc_html($child['title']); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>

                        </div>
                    <?php endforeach; ?>
                    </div>

                    <!-- Ações de salvar -->
                    <div class="ga-footer">
                        <div id="ga-message" class="ga-message"></div>
                        <button type="submit" class="button button-primary ga-save-btn" id="ga-save-access">
                            <span class="dashicons dashicons-saved"></span>
                            <?php _e('Salvar Acessos', 'admin-ui'); ?>
                        </button>
                    </div>

                </form>

                <?php else : ?>
                <div class="ga-empty-state">
                    <span class="dashicons dashicons-groups"></span>
                    <p><?php _e('Selecione um usuário acima para gerir os seus acessos.', 'admin-ui'); ?></p>
                </div>
                <?php endif; ?>

            </div>
        </div>
        <?php
    }

    /**
     * Salva acessos via AJAX
     */
    public function ajax_save_user_menu_access()
    {
        check_ajax_referer('ga_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Permissão negada');
        }

        global $wpdb;

        $user_id = intval($_POST['user_id']);
        $menus = isset($_POST['menus']) ? array_map('sanitize_text_field', $_POST['menus']) : array();

        // Remover acessos antigos
        $wpdb->delete($this->table_name, array('user_id' => $user_id), array('%d'));

        // Inserir novos acessos
        foreach ($menus as $menu_slug) {
            $menu_title = $this->get_menu_title_by_slug($menu_slug);

            $wpdb->insert(
                $this->table_name,
                array(
                    'user_id' => $user_id,
                    'menu_slug' => $menu_slug,
                    'menu_title' => $menu_title
                ),
                array('%d', '%s', '%s')
            );
        }

        wp_send_json_success(array(
            'message' => __('Acessos salvos com sucesso!', 'admin-ui')
        ));
    }

    /**
     * Busca título do menu pelo slug ou chave composta
     */
    private function get_menu_title_by_slug($slug)
    {
        global $menu, $submenu;

        if ($slug === 'themes.php::theme-install.php') {
            return __('Adicionar Tema', 'admin-ui');
        }
        if ($slug === 'plugins.php::plugin-install.php') {
            return __('Adicionar Plugin', 'admin-ui');
        }

        $raw_slug = $slug;
        if (strpos($slug, 'parent:') === 0) {
            $raw_slug = substr($slug, 7);
        } elseif (strpos($slug, '::') !== false) {
            list($p, $c) = explode('::', $slug, 2);
            $raw_slug = $c;
        }

        foreach ($menu as $item) {
            if (self::normalize_slug($item[2]) === self::normalize_slug($raw_slug)) {
                return wp_strip_all_tags($item[0]);
            }
        }

        foreach ($submenu as $parent => $items) {
            foreach ($items as $item) {
                if (self::normalize_slug($item[2]) === self::normalize_slug($raw_slug)) {
                    return wp_strip_all_tags($item[0]);
                }
            }
        }

        return $slug;
    }

    /**
     * Filtra menus no admin baseado nos acessos do usuário
     */
    public function filter_admin_menus()
    {
        global $menu, $submenu, $wpdb;

        $this->original_menu = $menu;
        $this->original_submenu = $submenu;

        $user_id = get_current_user_id();

        // NUNCA filtrar o administrador principal (ID 1)
        if ($user_id === 1) {
            return;
        }

        $allowed_menus = self::get_user_allowed_menus($user_id);

        if (!empty($allowed_menus)) {
            // Filtrar menus principais (pais)
            foreach ($menu as $key => $item) {
                if (empty($item[2])) {
                    continue;
                }
                $parent_slug = self::normalize_slug($item[2]);

                if (!self::is_parent_allowed($parent_slug, $allowed_menus)) {
                    unset($menu[$key]);
                }
            }

            // Filtrar submenus (filhos)
            foreach ($submenu as $parent => $items) {
                $parent_slug = self::normalize_slug($parent);
                foreach ($items as $key => $item) {
                    if (empty($item[2])) {
                        continue;
                    }
                    $child_slug = self::normalize_slug($item[2]);
                    $child_key = $parent_slug . '::' . $child_slug;

                    if (!self::is_menu_allowed($child_key, $user_id)) {
                        unset($submenu[$parent][$key]);
                    }
                }

                if (empty($submenu[$parent])) {
                    unset($submenu[$parent]);
                }
            }
        }
    }

    /**
     * Obtém todos os slugs de menus e submenus originais mapeados
     */
    private function get_all_original_slugs()
    {
        $slugs = array();
        
        if (is_array($this->original_menu)) {
            foreach ($this->original_menu as $item) {
                if (!empty($item[2])) {
                    $slugs[] = self::normalize_slug($item[2]);
                }
            }
        }
        
        if (is_array($this->original_submenu)) {
            foreach ($this->original_submenu as $parent => $items) {
                if (is_array($items)) {
                    foreach ($items as $item) {
                        if (!empty($item[2])) {
                            $slugs[] = self::normalize_slug($item[2]);
                        }
                    }
                }
            }
        }

        $slugs[] = 'theme-install.php';
        $slugs[] = 'plugin-install.php';
        
        return array_unique($slugs);
    }

    /**
     * Identifica os slugs de menus candidatos associados à requisição atual do admin
     */
    private function get_current_request_slugs()
    {
        global $pagenow;
        
        $slugs_to_check = array();
        
        if (isset($_GET['page'])) {
            $slugs_to_check[] = sanitize_text_field($_GET['page']);
            return $slugs_to_check;
        }
        
        if ($pagenow === 'post.php' || $pagenow === 'revision.php') {
            $post_id = 0;
            if (isset($_GET['post'])) {
                $post_id = intval($_GET['post']);
            } elseif (isset($_POST['post_ID'])) {
                $post_id = intval($_POST['post_ID']);
            }
            
            $post_type = 'post';
            if ($post_id > 0) {
                $post_type = get_post_type($post_id);
            } elseif (isset($_GET['post_type'])) {
                $post_type = sanitize_text_field($_GET['post_type']);
            }
            
            if ($post_type === 'post') {
                $slugs_to_check[] = 'edit.php';
                $slugs_to_check[] = 'post-new.php';
            } else {
                $slugs_to_check[] = 'edit.php?post_type=' . $post_type;
                $slugs_to_check[] = 'post-new.php?post_type=' . $post_type;
            }
            return $slugs_to_check;
        }
        
        if ($pagenow === 'edit-tags.php' || $pagenow === 'term.php') {
            $taxonomy = isset($_GET['taxonomy']) ? sanitize_text_field($_GET['taxonomy']) : '';
            $post_type = isset($_GET['post_type']) ? sanitize_text_field($_GET['post_type']) : '';
            
            if (!empty($taxonomy)) {
                $slug = 'edit-tags.php?taxonomy=' . $taxonomy;
                if (!empty($post_type) && $post_type !== 'post') {
                    $slug .= '&post_type=' . $post_type;
                }
                $slugs_to_check[] = $slug;
            }
            return $slugs_to_check;
        }
        
        $current_slug = $pagenow;
        
        $query_params = array();
        if (isset($_GET['post_type'])) {
            $query_params['post_type'] = sanitize_text_field($_GET['post_type']);
        }
        if (isset($_GET['taxonomy'])) {
            $query_params['taxonomy'] = sanitize_text_field($_GET['taxonomy']);
        }
        
        if (!empty($query_params)) {
            $current_slug .= '?' . http_build_query($query_params);
        }
        
        $slugs_to_check[] = $current_slug;
        return $slugs_to_check;
    }

    /**
     * Valida o acesso direto via URL às páginas de menu do painel administrativo
     */
    public function check_admin_page_access()
    {
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }

        global $pagenow;
        
        $whitelist = array(
            'admin-ajax.php',
            'admin-post.php',
            'async-upload.php',
        );

        if (in_array($pagenow, $whitelist)) {
            return;
        }

        $user_id = get_current_user_id();

        if ($user_id === 1) {
            return;
        }

        $allowed_menus = self::get_user_allowed_menus($user_id);

        if (empty($allowed_menus)) {
            return;
        }

        $request_slugs = $this->get_current_request_slugs();
        $original_slugs = $this->get_all_original_slugs();

        $is_registered_menu = false;
        $has_permission = false;

        foreach ($request_slugs as $slug) {
            $norm_slug = self::normalize_slug($slug);

            foreach ($original_slugs as $orig) {
                $norm_orig = self::normalize_slug($orig);
                if ($norm_slug === $norm_orig) {
                    $is_registered_menu = true;
                    if (self::is_menu_allowed_for_request($norm_slug, $user_id, $allowed_menus)) {
                        $has_permission = true;
                        break 2;
                    }
                }
            }
        }

        if ($is_registered_menu && !$has_permission) {
            wp_die(
                __('Você não tem permissão para acessar esta página.', 'admin-ui'),
                __('Acesso Negado', 'admin-ui'),
                array('response' => 403)
            );
        }
    }
}



