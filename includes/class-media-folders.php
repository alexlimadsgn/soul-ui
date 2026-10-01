<?php
/**
 * Soul UI — Pastas da Biblioteca de Mídia
 * Organiza anexos em pastas (taxonomia hierárquica), com filtro, drag & drop,
 * upload direto para a pasta atual e cards de pastas no topo da biblioteca.
 */

defined('ABSPATH') || exit;

class WP_Admin_UI_Media_Folders
{
    const TAX    = 'wn_media_folder';
    const NONCE  = 'wn_media_folders';
    const COLORS = ['#3858e9', '#22a06b', '#8b5cf6', '#e8a33d', '#e5484d', '#0ea5e9', '#ec4899', '#64748b'];

    public static function init(): void
    {
        add_action('init', [__CLASS__, 'register_taxonomy']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue']);

        // Filtros (modo lista e modo grade)
        add_action('pre_get_posts', [__CLASS__, 'filter_list_query']);
        add_filter('ajax_query_attachments_args', [__CLASS__, 'filter_grid_query']);

        // Upload direto na pasta atual
        add_action('add_attachment', [__CLASS__, 'assign_on_upload']);

        // Campo "Pasta" nos detalhes do anexo
        add_filter('attachment_fields_to_edit', [__CLASS__, 'attachment_field'], 10, 2);
        add_filter('attachment_fields_to_save', [__CLASS__, 'attachment_field_save'], 10, 2);

        // AJAX
        foreach (['create', 'rename', 'delete', 'update', 'move', 'assign', 'list'] as $action) {
            add_action('wp_ajax_wn_mf_' . $action, [__CLASS__, 'ajax_' . $action]);
        }
    }

    public static function register_taxonomy(): void
    {
        register_taxonomy(self::TAX, 'attachment', [
            'hierarchical'          => true,
            'public'                => false,
            'show_ui'               => false,
            'show_in_rest'          => false,
            'query_var'             => false,
            'rewrite'               => false,
            // Anexos têm status "inherit": a contagem padrão os ignoraria.
            'update_count_callback' => '_update_generic_term_count',
            'labels'                => ['name' => __('Pastas', 'soul-ui')],
        ]);
    }

    /* ── Dados ─────────────────────────────────────────────────────────── */

    public static function get_folders(): array
    {
        $terms = get_terms([
            'taxonomy'   => self::TAX,
            'hide_empty' => false,
            'orderby'    => 'name',
        ]);
        if (is_wp_error($terms)) {
            return [];
        }

        $folders = [];
        foreach ($terms as $i => $t) {
            $color = get_term_meta($t->term_id, 'wn_color', true);
            $folders[] = [
                'id'      => (int) $t->term_id,
                'name'    => $t->name,
                'parent'  => (int) $t->parent,
                'count'   => (int) $t->count,
                'color'   => $color ?: self::COLORS[$i % count(self::COLORS)],
                'starred' => (bool) get_term_meta($t->term_id, 'wn_starred', true),
            ];
        }
        return $folders;
    }

    private static function counts(): array
    {
        $total = (int) array_sum((array) wp_count_attachments());
        $trash = (int) (wp_count_attachments()->trash ?? 0);
        $total -= $trash;

        $none = new WP_Query([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit,private',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'tax_query'      => [['taxonomy' => self::TAX, 'operator' => 'NOT EXISTS']],
        ]);

        return ['all' => $total, 'none' => (int) $none->found_posts];
    }

    private static function payload(): array
    {
        return ['folders' => self::get_folders(), 'counts' => self::counts()];
    }

    /* ── Assets ────────────────────────────────────────────────────────── */

    public static function enqueue(): void
    {
        global $pagenow;
        if ('upload.php' !== $pagenow || !current_user_can('upload_files')) {
            return;
        }

        wp_enqueue_style(
            'wn-media-folders',
            WP_ADMIN_UI_URL . 'assets/admin-media-folders.css',
            ['wp-admin-core'],
            WP_ADMIN_UI_VERSION
        );

        wp_enqueue_script(
            'wn-media-folders',
            WP_ADMIN_UI_URL . 'assets/admin-media-folders.js',
            ['jquery'],
            WP_ADMIN_UI_VERSION,
            true
        );

        $current = isset($_GET['wn_folder']) ? sanitize_key(wp_unslash($_GET['wn_folder'])) : 'all';

        wp_localize_script('wn-media-folders', 'wnMediaFolders', array_merge(self::payload(), [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce(self::NONCE),
            'current' => $current,
            'mode'    => get_user_option('media_library_mode') ?: 'grid',
            'colors'  => self::COLORS,
            'i18n'    => [
                'folders'      => __('Pastas', 'soul-ui'),
                'allFiles'     => __('Todos os arquivos', 'soul-ui'),
                'uncategorized'=> __('Sem pasta', 'soul-ui'),
                'newFolder'    => __('Nova pasta', 'soul-ui'),
                'newSub'       => __('Nova subpasta', 'soul-ui'),
                'rename'       => __('Renomear', 'soul-ui'),
                'delete'       => __('Excluir', 'soul-ui'),
                'star'         => __('Favoritar', 'soul-ui'),
                'unstar'       => __('Remover dos favoritos', 'soul-ui'),
                'color'        => __('Cor', 'soul-ui'),
                'search'       => __('Buscar pastas…', 'soul-ui'),
                'namePrompt'   => __('Nome da pasta:', 'soul-ui'),
                'confirmDel'   => __('Excluir a pasta "%s"? Os arquivos não serão apagados, apenas voltarão para a pasta superior.', 'soul-ui'),
                'moveTo'       => __('Mover para pasta…', 'soul-ui'),
                'moved'        => __('%d arquivo(s) movido(s).', 'soul-ui'),
                'dragHint'     => __('Arraste arquivos para uma pasta', 'soul-ui'),
                'subfolders'   => __('Pastas', 'soul-ui'),
                'error'        => __('Algo deu errado. Tente novamente.', 'soul-ui'),
            ],
        ]));
    }

    /* ── Filtros de consulta ──────────────────────────────────────────── */

    private static function tax_query_for(string $folder): ?array
    {
        if ('' === $folder || 'all' === $folder) {
            return null;
        }
        if ('none' === $folder) {
            return [['taxonomy' => self::TAX, 'operator' => 'NOT EXISTS']];
        }
        return [[
            'taxonomy'         => self::TAX,
            'field'            => 'term_id',
            'terms'            => [(int) $folder],
            'include_children' => false,
        ]];
    }

    public static function filter_list_query(WP_Query $q): void
    {
        global $pagenow;
        if (!is_admin() || 'upload.php' !== $pagenow || !$q->is_main_query() || empty($_GET['wn_folder'])) {
            return;
        }
        $tax = self::tax_query_for(sanitize_key(wp_unslash($_GET['wn_folder'])));
        if ($tax) {
            $q->set('tax_query', $tax);
        }
    }

    public static function filter_grid_query(array $query): array
    {
        // phpcs:ignore WordPress.Security.NonceVerification -- core já valida a requisição de query-attachments.
        $folder = isset($_REQUEST['query']['wn_folder']) ? sanitize_key(wp_unslash($_REQUEST['query']['wn_folder'])) : '';
        $tax    = self::tax_query_for($folder);
        if ($tax) {
            $query['tax_query'] = $tax;
        }
        return $query;
    }

    public static function assign_on_upload(int $post_id): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification -- upload já validado pelo core.
        $folder = isset($_REQUEST['wn_folder']) ? absint($_REQUEST['wn_folder']) : 0;
        if ($folder && term_exists($folder, self::TAX)) {
            wp_set_object_terms($post_id, [$folder], self::TAX);
        }
    }

    /* ── Campo nos detalhes do anexo ──────────────────────────────────── */

    public static function attachment_field(array $fields, WP_Post $post): array
    {
        $current = wp_get_object_terms($post->ID, self::TAX, ['fields' => 'ids']);
        $current = is_wp_error($current) || empty($current) ? 0 : (int) $current[0];

        $html = wp_dropdown_categories([
            'taxonomy'         => self::TAX,
            'hide_empty'       => false,
            'hierarchical'     => true,
            'name'             => "attachments[{$post->ID}][wn_folder]",
            'id'               => "attachments-{$post->ID}-wn_folder",
            'selected'         => $current,
            'show_option_none' => __('— Sem pasta —', 'soul-ui'),
            'option_none_value'=> 0,
            'echo'             => false,
        ]);

        $fields['wn_folder'] = [
            'label' => __('Pasta', 'soul-ui'),
            'input' => 'html',
            'html'  => $html,
        ];
        return $fields;
    }

    public static function attachment_field_save(array $post, array $attachment): array
    {
        if (isset($attachment['wn_folder'])) {
            $folder = absint($attachment['wn_folder']);
            wp_set_object_terms((int) $post['ID'], $folder ? [$folder] : [], self::TAX);
        }
        return $post;
    }

    /* ── AJAX ─────────────────────────────────────────────────────────── */

    private static function guard(): void
    {
        check_ajax_referer(self::NONCE, 'nonce');
        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => __('Sem permissão.', 'soul-ui')], 403);
        }
    }

    private static function term_id(): int
    {
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if (!$id || !term_exists($id, self::TAX)) {
            wp_send_json_error(['message' => __('Pasta inválida.', 'soul-ui')], 400);
        }
        return $id;
    }

    public static function ajax_list(): void
    {
        self::guard();
        wp_send_json_success(self::payload());
    }

    public static function ajax_create(): void
    {
        self::guard();
        $name   = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $parent = isset($_POST['parent']) ? absint($_POST['parent']) : 0;
        if ('' === $name) {
            wp_send_json_error(['message' => __('Informe um nome.', 'soul-ui')], 400);
        }
        if ($parent && !term_exists($parent, self::TAX)) {
            $parent = 0;
        }

        // Slug único para permitir nomes iguais em pastas diferentes.
        $res = wp_insert_term($name, self::TAX, [
            'parent' => $parent,
            'slug'   => sanitize_title($name) . '-' . wp_generate_password(6, false, false),
        ]);
        if (is_wp_error($res)) {
            wp_send_json_error(['message' => $res->get_error_message()], 400);
        }

        $count = count(get_terms(['taxonomy' => self::TAX, 'hide_empty' => false, 'fields' => 'ids']));
        update_term_meta($res['term_id'], 'wn_color', self::COLORS[($count - 1) % count(self::COLORS)]);

        wp_send_json_success(array_merge(self::payload(), ['id' => (int) $res['term_id']]));
    }

    public static function ajax_rename(): void
    {
        self::guard();
        $id   = self::term_id();
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        if ('' === $name) {
            wp_send_json_error(['message' => __('Informe um nome.', 'soul-ui')], 400);
        }
        $res = wp_update_term($id, self::TAX, ['name' => $name]);
        if (is_wp_error($res)) {
            wp_send_json_error(['message' => $res->get_error_message()], 400);
        }
        wp_send_json_success(self::payload());
    }

    public static function ajax_update(): void
    {
        self::guard();
        $id = self::term_id();

        if (isset($_POST['color'])) {
            $color = sanitize_hex_color(wp_unslash($_POST['color']));
            if ($color) {
                update_term_meta($id, 'wn_color', $color);
            }
        }
        if (isset($_POST['starred'])) {
            if ('1' === $_POST['starred']) {
                update_term_meta($id, 'wn_starred', 1);
            } else {
                delete_term_meta($id, 'wn_starred');
            }
        }
        wp_send_json_success(self::payload());
    }

    public static function ajax_move(): void
    {
        self::guard();
        $id     = self::term_id();
        $parent = isset($_POST['parent']) ? absint($_POST['parent']) : 0;

        // Impede mover uma pasta para dentro dela mesma ou de uma descendente.
        if ($parent && ($parent === $id || in_array($parent, get_term_children($id, self::TAX), true))) {
            wp_send_json_error(['message' => __('Não é possível mover uma pasta para dentro dela mesma.', 'soul-ui')], 400);
        }

        $res = wp_update_term($id, self::TAX, ['parent' => $parent]);
        if (is_wp_error($res)) {
            wp_send_json_error(['message' => $res->get_error_message()], 400);
        }
        wp_send_json_success(self::payload());
    }

    public static function ajax_delete(): void
    {
        self::guard();
        $id     = self::term_id();
        $term   = get_term($id, self::TAX);
        $parent = (int) $term->parent;

        // Arquivos sobem para a pasta superior (ou ficam sem pasta).
        $ids = get_objects_in_term($id, self::TAX);
        if (!is_wp_error($ids)) {
            foreach ($ids as $post_id) {
                wp_set_object_terms((int) $post_id, $parent ? [$parent] : [], self::TAX);
            }
        }

        // Subpastas sobem um nível (wp_delete_term já faz isso para hierárquicas).
        wp_delete_term($id, self::TAX);
        wp_send_json_success(self::payload());
    }

    public static function ajax_assign(): void
    {
        self::guard();
        $folder = isset($_POST['folder']) ? absint($_POST['folder']) : 0;
        $ids    = isset($_POST['ids']) ? array_filter(array_map('absint', (array) $_POST['ids'])) : [];

        if ($folder && !term_exists($folder, self::TAX)) {
            wp_send_json_error(['message' => __('Pasta inválida.', 'soul-ui')], 400);
        }

        $moved = 0;
        foreach ($ids as $post_id) {
            if ('attachment' !== get_post_type($post_id) || !current_user_can('edit_post', $post_id)) {
                continue;
            }
            wp_set_object_terms($post_id, $folder ? [$folder] : [], self::TAX);
            $moved++;
        }

        wp_send_json_success(array_merge(self::payload(), ['moved' => $moved]));
    }
}
