<?php
defined('ABSPATH') || exit;

class WP_Admin_UI_Sidebar
{

    private static array $primary = [];
    private static array $secondary = [];

    public static function init(): void
    {
        // High priority to catch late-injected menus from other plugins
        add_action('admin_menu', [__CLASS__, 'collect_menu'], 999999);
        add_action('admin_body_class', [__CLASS__, 'body_class']);
        add_action('admin_body_open', [__CLASS__, 'render_sidebar'], 5);
        add_action('in_admin_header', [__CLASS__, 'render_sidebar'], 5);
    }

    // ─── Collect WP menu items ───────────────────────────────────────────────

    public static function collect_menu(): void
    {
        global $menu, $submenu;

        $content_slugs = ['edit.php', 'upload.php', 'edit-comments.php', 'post-new.php'];
        $admin_slugs = ['themes.php', 'plugins.php', 'users.php', 'tools.php', 'options-general.php'];

        self::$primary = [
            'Principal' => [],
            'Conteúdo' => [],
            'Funcionalidades' => [],
            'Administração' => [],
        ];

        if (!is_array($menu))
            return;

        foreach ((array) $menu as $item) {
            if (empty($item[0]) || empty($item[2]))
                continue;
            if (!current_user_can($item[1]))
                continue;

            $slug = $item[2];
            $url = self::resolve_url($slug);
            $raw = (string) $item[0];

            // Extract count
            $count = 0;
            if (preg_match('/<span[^>]*>.*?([\d]+).*?<\/span>/s', $raw, $m)) {
                $count = (int) $m[1];
            }

            // Clean label
            $label = preg_replace('/<span[^>]*>.*<\/span>/si', '', $raw);
            $label = wp_strip_all_tags($label);
            $label = trim($label);

            $data = [
                'label'    => $label,
                'slug'     => $slug,
                'url'      => $url,
                'count'    => $count,
                'icon'     => self::get_icon($slug, $item[6] ?? '', $label),
                'children' => self::collect_children($slug, $submenu),
            ];

            // ── Garantir submenus de "Adicionar" para Temas e Plugins ────────
            if ( $slug === 'themes.php' && current_user_can( 'install_themes' ) ) {
                if ( WP_Admin_UI_Access::is_menu_allowed( 'themes.php::theme-install.php' ) ) {
                    $add_url = admin_url( 'theme-install.php' );
                    $already = array_filter( $data['children'], fn($c) => rtrim( parse_url($c['url'], PHP_URL_PATH), '/' ) === rtrim( parse_url($add_url, PHP_URL_PATH), '/' ) );
                    if ( empty( $already ) ) {
                        array_unshift( $data['children'], [
                            'label' => 'Adicionar Tema',
                            'url'   => $add_url,
                            'count' => 0,
                        ] );
                    }
                }
            }

            if ( $slug === 'plugins.php' && current_user_can( 'install_plugins' ) ) {
                if ( WP_Admin_UI_Access::is_menu_allowed( 'plugins.php::plugin-install.php' ) ) {
                    $add_url = admin_url( 'plugin-install.php' );
                    $already = array_filter( $data['children'], fn($c) => rtrim( parse_url($c['url'], PHP_URL_PATH), '/' ) === rtrim( parse_url($add_url, PHP_URL_PATH), '/' ) );
                    if ( empty( $already ) ) {
                        array_unshift( $data['children'], [
                            'label' => 'Adicionar Plugin',
                            'url'   => $add_url,
                            'count' => 0,
                        ] );
                    }
                }
            }

            // Normalize slug for comparison (ignore query args for core check)
            $base_slug = explode('?', $slug)[0];

            // Categorize
            if ($slug === 'index.php' || $slug === 'separator1') {
                if ($slug !== 'separator1')
                    self::$primary['Principal'][] = $data;
            } elseif (in_array($base_slug, $content_slugs, true) || strpos($slug, 'edit.php?post_type=') === 0) {
                self::$primary['Conteúdo'][] = $data;
            } elseif (in_array($base_slug, $admin_slugs, true)) {
                self::$primary['Administração'][] = $data;
            } else {
                // All other items and custom plugin top-level menus
                if (strpos($slug, 'separator') === false) {
                    self::$primary['Funcionalidades'][] = $data;
                }
            }
        }
    }

    private static function collect_children(string $parent_slug, array $submenu): array
    {
        $children = [];
        if (empty($submenu[$parent_slug]))
            return $children;

        foreach ($submenu[$parent_slug] as $sub) {
            if (!current_user_can($sub[1]))
                continue;

            $raw = (string) $sub[0];

            // Extract count
            $count = 0;
            if (preg_match('/<span[^>]*>.*?([\d]+).*?<\/span>/s', $raw, $m)) {
                $count = (int) $m[1];
            }

            // Clean label
            $label = preg_replace('/<span[^>]*>.*<\/span>/si', '', $raw);
            $label = wp_strip_all_tags($label);
            $clean_label = trim($label);
            $sub_slug = (string) ($sub[2] ?? '');

            // Filtra 'Tipos de letra' / 'Font Library' e 'Padrões' / 'Patterns' no menu Aparência
            if ($parent_slug === 'themes.php') {
                $sub_slug_lower = strtolower($sub_slug);
                $label_lower = strtolower($clean_label);
                if (
                    strpos($sub_slug_lower, 'font-library') !== false ||
                    strpos($sub_slug_lower, 'wp_block') !== false ||
                    strpos($sub_slug_lower, 'patterns') !== false ||
                    $label_lower === 'tipos de letra' ||
                    $label_lower === 'tipo de letra' ||
                    $label_lower === 'fonts' ||
                    $label_lower === 'font library' ||
                    $label_lower === 'padrões' ||
                    $label_lower === 'padroes' ||
                    $label_lower === 'patterns'
                ) {
                    continue;
                }
            }

            $children[] = [
                'label' => $clean_label,
                'url' => self::resolve_url($sub[2], $parent_slug),
                'count' => $count,
            ];
        }
        return $children;
    }


    private static function resolve_url(string $slug, string $parent = ''): string
    {
        // Garante que o Personalizador nunca herde páginas de update/instalação como URL de retorno
        if (strpos($slug, 'customize.php') !== false) {
            return admin_url('customize.php?return=' . rawurlencode(admin_url('themes.php')));
        }
        if (strpos($slug, 'http') === 0)
            return $slug;
        if (strpos($slug, '.php') !== false)
            return admin_url($slug);
        if ($parent && strpos($parent, '.php') !== false) {
            return admin_url($parent . '?page=' . $slug);
        }
        return admin_url('admin.php?page=' . $slug);
    }

    // ─── Render ─────────────────────────────────────────────────────────────

    public static function render_sidebar(): void
    {
        static $rendered = false;
        if ( $rendered ) {
            return;
        }

        global $pagenow;
        if ( 'customize.php' === $pagenow ) {
            return;
        }

        $screen = get_current_screen();
        if ( $screen && $screen->is_block_editor() ) {
            return;
        }

        $rendered = true;

        $current_url = (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $site_name = get_bloginfo('name');
        ?>
        <div id="wn-sidebar" role="navigation" aria-label="Navegação principal">

            <div class="wn-site-header">
                <div class="wn-site-icon">
                    <?php echo self::icon_globe(); ?>
                </div>
                <span class="wn-site-name"><?php echo esc_html($site_name ?: 'WP-Press'); ?></span>
            </div>

            <div class="wn-nav-scroll" id="wn-nav-primary">
                <?php foreach (self::$primary as $group_name => $items):
                    if (empty($items))
                        continue; ?>
                    <div class="wn-group-label"><?php echo esc_html($group_name); ?></div>
                    <?php foreach ($items as $item) {
                        self::render_item($item, $current_url);
                    }
                endforeach; ?>
            </div>
            
            <script>
                if (window.lucide) {
                    window.lucide.createIcons();
                }
            </script>
        </div>
        <?php
    }

    private static function render_item(array $item, string $current_url): void
    {
        $has_kids = !empty($item['children']);
        $active   = self::is_active($item['url'], $current_url);
        $has_active_child = false;

        if ( $has_kids ) {
            foreach ( $item['children'] as $child ) {
                if ( self::is_active( $child['url'], $current_url ) ) {
                    $active = true;
                    $has_active_child = true;
                    break;
                }
            }
        }
        ?>
        <div class="wn-group<?php echo $has_kids ? ' has-children' : ''; ?><?php echo $has_active_child ? ' has-active-child' : ''; ?>">
            <a href="<?php echo esc_url($item['url']); ?>" class="wn-item<?php echo $active ? ' is-active' : ''; ?>">
                <span class="wn-item-icon"><?php echo $item['icon']; ?></span>
                <span class="wn-item-label"><?php echo esc_html($item['label']); ?></span>

                <?php if (!empty($item['count']) && $item['count'] > 0): ?>
                    <span class="wn-badge"><?php echo (int) $item['count']; ?></span>
                <?php endif; ?>

                <?php if ($has_kids): ?>
                    <span class="wn-chevron"><?php echo self::icon_chevron_right(); ?></span>
                <?php endif; ?>
            </a>

            <?php if ($has_kids): ?>
                <div class="wn-flyout" role="menu">
                    <div class="wn-flyout-inner">
                        <?php foreach ($item['children'] as $child): 
                            $sub_active = self::is_active($child['url'], $current_url);
                        ?>
                            <a href="<?php echo esc_url($child['url']); ?>"
                                class="wn-child-item<?php echo $sub_active ? ' is-active' : ''; ?>" role="menuitem">
                                <span class="wn-child-label"><?php echo esc_html($child['label']); ?></span>
                                <?php if (!empty($child['count']) && $child['count'] > 0): ?>
                                    <span class="wn-badge"><?php echo (int) $child['count']; ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private static function is_active(string $url, string $current_url): bool
    {
        $u1 = parse_url($url);
        $u2 = parse_url($current_url);

        // O caminho deve ser o mesmo (ex: wp-admin/edit.php)
        if (rtrim($u1['path'] ?? '', '/') !== rtrim($u2['path'] ?? '', '/')) {
            return false;
        }

        parse_str($u1['query'] ?? '', $q1);
        parse_str($u2['query'] ?? '', $q2);

        // Chaves que definem a identidade da "página" no WordPress e devem bater exatamente
        $critical_keys = ['post_type', 'page', 'taxonomy', 'post_status'];

        foreach ($critical_keys as $key) {
            $val1 = $q1[$key] ?? null;
            $val2 = $q2[$key] ?? null;

            // No WordPress, a ausência de 'post_type' em edit.php geralmente significa 'post'
            if ($key === 'post_type' && strpos($u1['path'], 'edit.php') !== false) {
                $val1 = $val1 ?: 'post';
                $val2 = $val2 ?: 'post';
            }

            if ($val1 !== $val2) {
                return false;
            }
        }

        // Para outras chaves presentes no item do menu, elas devem existir na URL atual
        // (Isso evita falsos positivos em menus genéricos)
        foreach ($q1 as $k => $v) {
            if (!in_array($k, $critical_keys) && (!isset($q2[$k]) || $q2[$k] != $v)) {
                return false;
            }
        }

        return true;
    }

    private static function current_role(): string
    {
        $user = wp_get_current_user();
        $roles = (array) $user->roles;
        return !empty($roles) ? ucfirst($roles[0]) : 'Usuário';
    }

    public static function body_class(string $classes): string
    {
        global $pagenow;
        if ( 'customize.php' === $pagenow ) {
            return $classes;
        }

        $screen = get_current_screen();
        if ( $screen && $screen->is_block_editor() ) {
            return $classes;
        }

        return $classes . ' wn-active';
    }

    public static function hide_native_sidebar(): void
    {
        ?>
        <style id="wn-hide-native">
            body.wn-active #adminmenuwrap,
            body.wn-active #adminmenuback,
            body.wn-active #wpfooter {
                display: none !important;
            }

            body.wn-active #wpcontent {
                padding-left: 0 !important;
            }
        </style>
        <?php
    }

    // ─── Icon map ────────────────────────────────────────────────────────────

    private static function get_icon(string $slug, string $dashicon, string $label): string
    {
        // ── Ícones fixos por slug de menu (prioridade máxima) ────────────────
        $slug_icons = [
            'gerir-acessos'         => 'user-lock',
            'simple-cookie-consent' => 'cookie',
            'code-editor-ide'       => 'code',
            'wp-analytics'          => 'bar-chart-3',
        ];
        if (isset($slug_icons[$slug])) {
            return "<i data-lucide=\"" . esc_attr($slug_icons[$slug]) . "\"></i>";
        }

        $custom_icons = get_option('wn_custom_icons', []);
        if (!empty($custom_icons[$slug])) {
            $icon = trim($custom_icons[$slug]);
            if (strpos($icon, '<svg') === 0) {
                return $icon;
            }
            return "<i data-lucide=\"" . esc_attr($icon) . "\"></i>";
        }

        // Standard Lucide-style attributes
        $attr = 'viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';

        $map = [
            'dashicons-dashboard' => "<svg $attr><rect width='7' height='9' x='3' y='3' rx='1'/><rect width='7' height='5' x='14' y='3' rx='1'/><rect width='7' height='9' x='14' y='12' rx='1'/><rect width='7' height='5' x='3' y='16' rx='1'/></svg>",
            'dashicons-admin-post' => "<svg $attr><path d='M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z'/><polyline points='14 2 14 8 20 8'/><line x1='16' y1='13' x2='8' y2='13'/><line x1='16' y1='17' x2='8' y2='17'/><line x1='10' y1='9' x2='8' y2='9'/></svg>",
            'dashicons-admin-media' => "<svg $attr><rect width='18' height='18' x='3' y='3' rx='2' ry='2'/><circle cx='9' cy='9' r='2'/><path d='m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21'/></svg>",
            'dashicons-admin-page' => "<svg $attr><path d='M15.5 2H8.6c-.4 0-.8.2-1.1.5-.3.3-.5.7-.5 1.1v12.8c0 .4.2.8.5 1.1.3.3.7.5 1.1.5h9.8c.4 0 .8-.2 1.1-.5.3-.3.5-.7.5-1.1V6.5L15.5 2z'/><path d='M3 7.6v12.8c0 .4.2.8.5 1.1.3.3.7.5 1.1.5h9.8'/><path d='M15 2v5h5'/></svg>",
            'dashicons-admin-comments' => "<svg $attr><path d='M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z'/></svg>",
            'dashicons-admin-appearance' => "<svg $attr><circle cx='13.5' cy='6.5' r='.5'/><circle cx='17.5' cy='10.5' r='.5'/><circle cx='8.5' cy='7.5' r='.5'/><circle cx='6.5' cy='12.5' r='.5'/><path d='M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.92 0 1.76-.74 1.76-1.67 0-.42-.15-.81-.42-1.15-.28-.33-.42-.73-.42-1.18 0-.92.75-1.67 1.67-1.67h1.91c2.75 0 5-2.25 5-5 0-5.28-4.28-9.5-9.5-9.5z'/></svg>",
            'dashicons-admin-plugins' => "<svg $attr><path d='M12 22v-5'/><path d='M15 8V2'/><path d='M17 8a1 1 0 0 1 1 1v4a4 4 0 0 1-4 4h-4a4 4 0 0 1-4-4V9a1 1 0 0 1 1-1z'/><path d='M9 8V2'/></svg>",
            'dashicons-admin-users' => "<svg $attr><path d='M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2'/><circle cx='9' cy='7' r='4'/><path d='M22 21v-2a4 4 0 0 0-3-3.87'/><path d='M16 3.13a4 4 0 0 1 0 7.75'/></svg>",
            'dashicons-admin-tools' => "<svg $attr><path d='M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z'/><circle cx='12' cy='12' r='4'/></svg>",
            'dashicons-admin-settings' => "<svg $attr><path d='M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.38a2 2 0 0 0-.73-2.73l-.15-.1a2 2 0 0 1-1-1.72v-.51a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z'/><circle cx='12' cy='12' r='3'/></svg>",
        ];

        if (isset($map[$dashicon]))
            return $map[$dashicon];

        // Auto-detect icon by post type slug or label keywords (Lucide)
        $lucide = self::match_lucide_icon($slug, $label);
        if ($lucide) {
            return "<i data-lucide=\"" . esc_attr($lucide) . "\"></i>";
        }

        // Letter fallback
        $letter = mb_strtoupper(mb_substr($label, 0, 1));
        return "<svg viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><text x='50%' y='50%' dominant-baseline='central' text-anchor='middle' font-size='12' font-family='inherit' font-weight='600' fill='currentColor'>$letter</text></svg>";
    }

    /**
     * Tenta encontrar um ícone Lucide adequado analisando o slug do post type
     * e o label do menu em busca de palavras-chave em português e inglês.
     */
    private static function match_lucide_icon(string $slug, string $label): string
    {
        $keywords = [
            // Produtos / E-commerce
            'produto'        => 'package',
            'product'        => 'package',
            'carrinho'       => 'shopping-cart',
            'cart'           => 'shopping-cart',
            'sacola'         => 'shopping-bag',
            'pedido'         => 'clipboard-list',
            'order'          => 'clipboard-list',
            'venda'          => 'trending-up',
            'sale'           => 'trending-up',
            'checkout'       => 'credit-card',
            'estoque'        => 'warehouse',
            'stock'          => 'warehouse',

            // Eventos / Agenda
            'evento'         => 'calendar',
            'event'          => 'calendar',
            'agenda'         => 'calendar',
            'schedule'       => 'calendar',
            'calendário'     => 'calendar',
            'calendar'       => 'calendar',
            'reserva'        => 'calendar-check',
            'booking'        => 'calendar-check',

            // Portfólio / Mídia
            'portfólio'      => 'briefcase',
            'portfolio'      => 'briefcase',
            'galeria'        => 'images',
            'gallery'        => 'images',
            'imagem'         => 'image',
            'image'          => 'image',
            'foto'           => 'camera',
            'photo'          => 'camera',
            'vídeo'          => 'video',
            'video'          => 'video',
            'banner'         => 'image',
            'slide'          => 'image',
            'slider'         => 'image',

            // Depoimentos / Avaliações
            'depoimento'     => 'message-square',
            'testimonial'    => 'message-square',
            'avaliação'      => 'star',
            'review'         => 'star',
            'feedback'       => 'message-circle',
            'comentário'     => 'message-circle',
            'comment'        => 'message-circle',

            // FAQ / Ajuda
            'faq'            => 'help-circle',
            'pergunta'       => 'help-circle',
            'question'       => 'help-circle',
            'dúvida'         => 'help-circle',

            // Usuários / Equipe
            'usuário'        => 'user',
            'user'           => 'user',
            'membro'         => 'users',
            'member'         => 'users',
            'equipe'         => 'users',
            'team'           => 'users',
            'cliente'        => 'user-check',
            'client'         => 'user-check',
            'parceiro'       => 'handshake',
            'partner'        => 'handshake',

            // Cursos / Aulas
            'curso'          => 'book-open',
            'course'         => 'book-open',
            'aula'           => 'book',
            'lesson'         => 'book',
            'turma'          => 'graduation-cap',
            'class'          => 'graduation-cap',
            'certificado'    => 'award',
            'certificate'    => 'award',

            // Documentos / Arquivos
            'documento'      => 'file-text',
            'document'       => 'file-text',
            'arquivo'        => 'file',
            'file'           => 'file',
            'pdf'            => 'file-text',
            'planilha'       => 'table',
            'spreadsheet'    => 'table',

            // Contato / Mensagens
            'contato'        => 'mail',
            'contact'        => 'mail',
            'mensagem'       => 'message-square',
            'message'        => 'message-square',
            'newsletter'     => 'mail',
            'assinante'      => 'mail',
            'subscriber'     => 'mail',

            // Notícias / Blog
            'notícia'        => 'newspaper',
            'news'           => 'newspaper',
            'artigo'         => 'file-text',
            'article'        => 'file-text',
            'blog'           => 'edit-3',

            // Serviços e Manutenção
            'serviço'        => 'wrench',
            'service'        => 'wrench',
            'manutenção'     => 'shield-alert',
            'maintenance'    => 'shield-alert',

            // Localização
            'local'          => 'map-pin',
            'location'       => 'map-pin',
            'endereço'       => 'map-pin',
            'address'        => 'map-pin',
            'mapa'           => 'map',
            'map'            => 'map',

            // Categorias / Tags
            'categoria'      => 'tag',
            'category'       => 'tag',
            'tag'            => 'tags',

            // Financeiro
            'receita'        => 'dollar-sign',
            'revenue'        => 'dollar-sign',
            'despesa'        => 'credit-card',
            'expense'        => 'credit-card',
            'pagamento'      => 'dollar-sign',
            'payment'        => 'dollar-sign',
            'fatura'         => 'file-text',
            'invoice'        => 'file-text',

            // Relatórios / Estatísticas
            'relatório'      => 'bar-chart',
            'report'         => 'bar-chart',
            'estatística'    => 'bar-chart-2',
            'statistics'     => 'bar-chart-2',
            'analytics'      => 'trending-up',
            'dashboard'      => 'layout-dashboard',

            // Configurações / Ferramentas
            'config'         => 'settings',
            'configuração'   => 'settings',
            'setting'        => 'settings',
            'ferramenta'     => 'tool',
            'tool'           => 'tool',
        ];

        // 1. Tenta extrair o post type do slug (ex: edit.php?post_type=product)
        if (preg_match('/[?&]post_type=([^&]+)/', $slug, $m)) {
            $pt = mb_strtolower($m[1]);
            foreach ($keywords as $word => $icon) {
                if (mb_strpos($pt, $word) !== false) {
                    return $icon;
                }
            }
        }

        // 2. Tenta pelo label do menu (separa em palavras e busca correspondência)
        $normalized = preg_replace('/[^a-zA-Z0-9à-üÀ-Ü\-]/u', ' ', $label);
        $words = preg_split('/\s+/', $normalized);
        foreach ($words as $word) {
            $word = mb_strtolower(trim($word));
            if ($word === '') continue;
            if (isset($keywords[$word])) {
                return $keywords[$word];
            }
        }

        return '';
    }

    private static function icon_globe(): string
    {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>';
    }

    private static function icon_chevron_left(): string
    {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>';
    }

    private static function icon_chevron_right(): string
    {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>';
    }
}


