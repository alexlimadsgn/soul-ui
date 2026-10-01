<?php
/**
 * WP Notion UI — Dashboard
 * Replaces default dashboard with a modern KPI-driven interface matching the Paper design.
 */

defined('ABSPATH') || exit;

class WP_Admin_UI_Dashboard
{

    public static function init(): void
    {
        add_action('wp_dashboard_setup', [__CLASS__, 'remove_dashboard_widgets'], 999);
        add_action('welcome_panel', [__CLASS__, 'render_custom_dashboard']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_filter('get_user_metadata', [__CLASS__, 'force_welcome_panel'], 10, 4);
    }

    public static function force_welcome_panel($value, $object_id, $meta_key, $single)
    {
        if ($meta_key === 'show_welcome_panel') {
            return 1;
        }
        return $value;
    }

    public static function remove_dashboard_widgets(): void
    {
        global $wp_meta_boxes;
        $wp_meta_boxes['dashboard'] = [];
    }

    public static function enqueue_assets(): void
    {
        $screen = get_current_screen();
        if ($screen && $screen->id === 'dashboard') {
            wp_enqueue_style(
                'wp-admin-dashboard',
                WP_ADMIN_UI_URL . 'assets/admin-dashboard.css',
                [],
                WP_ADMIN_UI_VERSION
            );
        }
    }

    public static function render_custom_dashboard(): void
    {
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'dashboard') {
            return;
        }

        // ── Data Gathering ───────────────────────────────────────────────────
        $user = wp_get_current_user();
        $greeting = self::get_greeting($user->display_name);
        $date = date_i18n('l, j \d\e F');

        $count_posts = wp_count_posts('post')->publish ?? 0;
        $count_pages = wp_count_posts('page')->publish ?? 0;
        $count_products = post_type_exists('product') ? (wp_count_posts('product')->publish ?? 0) : 0;

        // Access Control
        $can_see_posts = self::is_menu_available('edit.php');
        $can_see_pages = self::is_menu_available('edit.php?post_type=page');
        $can_see_media = self::is_menu_available('upload.php');
        $can_see_plugins = self::is_menu_available('plugins.php');
        $can_see_themes = self::is_menu_available('themes.php');
        $can_see_options = self::is_menu_available('options-general.php');

        // Health Status logic
        $health_score = self::get_site_health_score();
        $health_label = self::get_site_health_label($health_score);

        // Public Post Types
        $public_types = get_post_types(['public' => true], 'objects');
        $allowed_types = [];

        foreach ($public_types as $type_obj) {
            $handle = ($type_obj->name === 'post') ? 'edit.php' : 'edit.php?post_type=' . $type_obj->name;
            if (self::is_menu_available($handle)) {
                $allowed_types[] = $type_obj->name;
            }
        }

        $recent_posts = [];
        if (!empty($allowed_types)) {
            $recent_posts = get_posts([
                'post_type' => $allowed_types,
                'post_status' => 'publish',
                'posts_per_page' => 5,
                'orderby' => 'modified',
            ]);
        }

        // Custom Post Types (excluindo os nativos post, page, attachment, product)
        $custom_cpts = [];
        $excluded_cpts = ['post', 'page', 'attachment', 'product'];
        foreach ($public_types as $type_obj) {
            if (in_array($type_obj->name, $excluded_cpts, true)) {
                continue;
            }
            $handle = 'edit.php?post_type=' . $type_obj->name;
            if (self::is_menu_available($handle)) {
                $count_obj = wp_count_posts($type_obj->name);
                $custom_cpts[] = [
                    'slug'          => $type_obj->name,
                    'label'         => $type_obj->labels->name ?: $type_obj->label,
                    'singular_name' => $type_obj->labels->singular_name ?: $type_obj->label,
                    'count'         => (int) ($count_obj->publish ?? 0),
                    'list_url'      => admin_url('edit.php?post_type=' . $type_obj->name),
                    'add_url'       => admin_url('post-new.php?post_type=' . $type_obj->name),
                ];
            }
        }
        ?>
        <div id="wn-dashboard-wrap">

            <!-- ── Top Greeting Header ───────────────────────────────────── -->
            <div class="wn-dash-header-row">
                <div class="wn-dash-greeting-wrap">
                    <h1 class="wn-dash-greeting"><?php echo esc_html($greeting); ?></h1>
                    <p class="wn-dash-date"><?php echo esc_html($date); ?></p>
                </div>
                <button type="button" class="wn-dash-filter-btn" title="Configurações do painel">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#646970" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="4" x2="4" y1="21" y2="14" />
                        <line x1="4" x2="4" y1="10" y2="3" />
                        <line x1="12" x2="12" y1="21" y2="12" />
                        <line x1="12" x2="12" y1="8" y2="3" />
                        <line x1="20" x2="20" y1="21" y2="16" />
                        <line x1="20" x2="20" y1="12" y2="3" />
                        <line x1="2" x2="6" y1="14" y2="14" />
                        <line x1="10" x2="14" y1="8" y2="8" />
                        <line x1="18" x2="22" y1="16" y2="16" />
                    </svg>
                </button>
            </div>

            <!-- ── KPIs Row ──────────────────────────────────────────────── -->
            <div class="wn-dash-kpis-grid">
                
                <!-- Health Card -->
                <a href="<?php echo esc_url(admin_url('site-health.php')); ?>" class="wn-kpi-card wn-kpi-card--health">
                    <div class="wn-kpi-chart-box">
                        <svg viewBox="0 0 36 36" class="wn-kpi-circular-svg">
                            <path class="wn-circle-bg" d="M18 2.084 a 15.915 15.915 0 0 1 0 31.831 a 15.915 15.915 0 0 1 0 -31.831" fill="none" stroke="rgba(0, 0, 0, 0.08)" stroke-width="3.2" />
                            <path class="wn-circle-val" d="M18 2.084 a 15.915 15.915 0 0 1 0 31.831 a 15.915 15.915 0 0 1 0 -31.831" fill="none" stroke="#22C55E" stroke-width="3.2" stroke-linecap="round" stroke-dasharray="<?php echo (int) $health_score; ?>, 100" />
                        </svg>
                        <div class="wn-kpi-chart-center">
                            <span><?php echo (int) $health_score; ?>%</span>
                        </div>
                    </div>
                    <div class="wn-kpi-health-meta">
                        <div class="wn-kpi-title">Saúde do Site</div>
                        <div class="wn-kpi-desc"><?php echo esc_html($health_label); ?></div>
                        <div class="wn-kpi-status-tag">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#22C55E" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21.801 10A10 10 0 1 1 17 3.335" />
                                <path d="m9 11 3 3L22 4" />
                            </svg>
                            <span>12</span>
                        </div>
                    </div>
                    <svg class="wn-kpi-arrow-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#8C8F94" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 12h14" />
                        <path d="m12 5 7 7-7 7" />
                    </svg>
                </a>

                <!-- Posts KPI -->
                <?php if ($can_see_posts): ?>
                    <a href="<?php echo esc_url(admin_url('edit.php')); ?>" class="wn-kpi-card wn-kpi-card--stat">
                        <div class="wn-stat-icon-wrap wn-stat-icon-wrap--posts">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2271B1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z" />
                                <path d="M14 2v4a2 2 0 0 0 2 2h4" />
                                <path d="M10 9H8" />
                                <path d="M16 13H8" />
                                <path d="M16 17H8" />
                            </svg>
                        </div>
                        <div class="wn-stat-meta">
                            <span class="wn-stat-value"><?php echo (int) $count_posts; ?></span>
                            <span class="wn-stat-label">Artigos</span>
                        </div>
                    </a>
                <?php endif; ?>

                <!-- Pages KPI -->
                <?php if ($can_see_pages): ?>
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=page')); ?>" class="wn-kpi-card wn-kpi-card--stat">
                        <div class="wn-stat-icon-wrap wn-stat-icon-wrap--pages">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#72AEE6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect width="18" height="18" x="3" y="3" rx="2" ry="2" />
                                <circle cx="9" cy="9" r="2" />
                                <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21" />
                            </svg>
                        </div>
                        <div class="wn-stat-meta">
                            <span class="wn-stat-value"><?php echo (int) $count_pages; ?></span>
                            <span class="wn-stat-label">Páginas</span>
                        </div>
                    </a>
                <?php endif; ?>

            </div>

            <!-- ── Main Columns Grid ─────────────────────────────────────── -->
            <div class="wn-dash-columns-grid">

                <!-- Left Column: Quick Access & Theme CPTs -->
                <div class="wn-dash-main-column">

                    <!-- Acesso Rápido -->
                    <div class="wn-dash-section-card">
                        <div class="wn-dash-section-head">
                            <h2 class="wn-dash-section-title">Acesso Rápido</h2>
                            <p class="wn-dash-section-sub">Ferramentas e áreas essenciais do seu painel</p>
                        </div>
                        <div class="wn-quick-access-grid">

                            <?php if ($can_see_posts): ?>
                                <a href="<?php echo esc_url(admin_url('post-new.php')); ?>" class="wn-quick-item">
                                    <div class="wn-quick-icon-wrap wn-quick-icon--posts">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2271B1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z" />
                                            <path d="M14 2v4a2 2 0 0 0 2 2h4" />
                                            <path d="M10 9H8" />
                                            <path d="M16 13H8" />
                                            <path d="M16 17H8" />
                                        </svg>
                                    </div>
                                    <div class="wn-quick-text">
                                        <span class="wn-quick-title">Novo Artigo</span>
                                        <span class="wn-quick-desc">Publicar novo artigo</span>
                                    </div>
                                    <svg class="wn-quick-arrow" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#8C8F94" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M5 12h14" />
                                        <path d="m12 5 7 7-7 7" />
                                    </svg>
                                </a>
                            <?php endif; ?>

                            <?php if ($can_see_pages): ?>
                                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=page')); ?>" class="wn-quick-item">
                                    <div class="wn-quick-icon-wrap wn-quick-icon--pages">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#72AEE6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <rect width="18" height="18" x="3" y="3" rx="2" ry="2" />
                                            <circle cx="9" cy="9" r="2" />
                                            <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21" />
                                        </svg>
                                    </div>
                                    <div class="wn-quick-text">
                                        <span class="wn-quick-title">Nova Página</span>
                                        <span class="wn-quick-desc">Criar nova página</span>
                                    </div>
                                    <svg class="wn-quick-arrow" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#8C8F94" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M5 12h14" />
                                        <path d="m12 5 7 7-7 7" />
                                    </svg>
                                </a>
                            <?php endif; ?>

                            <?php if ($can_see_media): ?>
                                <a href="<?php echo esc_url(admin_url('upload.php')); ?>" class="wn-quick-item">
                                    <div class="wn-quick-icon-wrap wn-quick-icon--media">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#00A32A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <rect width="18" height="18" x="3" y="3" rx="2" ry="2" />
                                            <circle cx="9" cy="9" r="2" />
                                            <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21" />
                                        </svg>
                                    </div>
                                    <div class="wn-quick-text">
                                        <span class="wn-quick-title">Biblioteca de Mídia</span>
                                        <span class="wn-quick-desc">Gerir imagens e ficheiros</span>
                                    </div>
                                    <svg class="wn-quick-arrow" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#8C8F94" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M5 12h14" />
                                        <path d="m12 5 7 7-7 7" />
                                    </svg>
                                </a>
                            <?php endif; ?>

                            <?php if ($can_see_plugins): ?>
                                <a href="<?php echo esc_url(admin_url('plugins.php')); ?>" class="wn-quick-item">
                                    <div class="wn-quick-icon-wrap wn-quick-icon--plugins">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#A3B745" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M12 22v-5" />
                                            <path d="M9 8V2" />
                                            <path d="M15 8V2" />
                                            <path d="M18 8v5a4 4 0 0 1-4 4h-4a4 4 0 0 1-4-4V8Z" />
                                        </svg>
                                    </div>
                                    <div class="wn-quick-text">
                                        <span class="wn-quick-title">Plugins</span>
                                        <span class="wn-quick-desc">Gerir extensões ativas</span>
                                    </div>
                                    <svg class="wn-quick-arrow" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#8C8F94" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M5 12h14" />
                                        <path d="m12 5 7 7-7 7" />
                                    </svg>
                                </a>
                            <?php endif; ?>

                            <?php if ($can_see_themes): ?>
                                <a href="<?php echo esc_url(admin_url('themes.php')); ?>" class="wn-quick-item">
                                    <div class="wn-quick-icon-wrap wn-quick-icon--themes">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#D63638" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="13.5" cy="6.5" r=".5" fill="#D63638" stroke="#D63638" stroke-width="2" />
                                            <circle cx="17.5" cy="10.5" r=".5" fill="#D63638" stroke="#D63638" stroke-width="2" />
                                            <circle cx="8.5" cy="7.5" r=".5" fill="#D63638" stroke="#D63638" stroke-width="2" />
                                            <circle cx="6.5" cy="12.5" r=".5" fill="#D63638" stroke="#D63638" stroke-width="2" />
                                            <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z" />
                                        </svg>
                                    </div>
                                    <div class="wn-quick-text">
                                        <span class="wn-quick-title">Aparência & Temas</span>
                                        <span class="wn-quick-desc">Personalizar design</span>
                                    </div>
                                    <svg class="wn-quick-arrow" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#8C8F94" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M5 12h14" />
                                        <path d="m12 5 7 7-7 7" />
                                    </svg>
                                </a>
                            <?php endif; ?>

                            <?php if ($can_see_options): ?>
                                <a href="<?php echo esc_url(admin_url('options-general.php')); ?>" class="wn-quick-item">
                                    <div class="wn-quick-icon-wrap wn-quick-icon--settings">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#646970" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z" />
                                            <circle cx="12" cy="12" r="3" />
                                        </svg>
                                    </div>
                                    <div class="wn-quick-text">
                                        <span class="wn-quick-title">Configurações</span>
                                        <span class="wn-quick-desc">Definições do site</span>
                                    </div>
                                    <svg class="wn-quick-arrow" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#8C8F94" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M5 12h14" />
                                        <path d="m12 5 7 7-7 7" />
                                    </svg>
                                </a>
                            <?php endif; ?>

                        </div>
                    </div>

                    <!-- Tipos de Conteúdo do Tema -->
                    <?php if (!empty($custom_cpts)): ?>
                        <div class="wn-dash-section-card">
                            <div class="wn-dash-section-head">
                                <h2 class="wn-dash-section-title">Tipos de Conteúdo do Tema</h2>
                                <p class="wn-dash-section-sub">Módulos e conteúdos personalizados registrados no site</p>
                            </div>
                            <div class="wn-cpt-cards-grid">
                                <?php foreach ($custom_cpts as $cpt): ?>
                                    <div class="wn-cpt-item-card">
                                        <a href="<?php echo esc_url($cpt['list_url']); ?>" class="wn-cpt-item-left">
                                            <div class="wn-cpt-item-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#06B6D4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="m6 14 1.5-2.9A2 2 0 0 1 9.24 10H20a2 2 0 0 1 1.94 2.5l-1.54 6a2 2 0 0 1-1.95 1.5H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h3.9a2 2 0 0 1 1.69.9l.81 1.2a2 2 0 0 0 1.67.9H18a2 2 0 0 1 2 2v2" />
                                                </svg>
                                            </div>
                                            <div class="wn-cpt-item-info">
                                                <span class="wn-cpt-item-title"><?php echo esc_html($cpt['label']); ?></span>
                                                <span class="wn-cpt-item-count"><?php echo esc_html($cpt['count'] . ' ' . ($cpt['count'] === 1 ? 'item' : 'items')); ?></span>
                                            </div>
                                        </a>
                                        <a href="<?php echo esc_url($cpt['add_url']); ?>" class="wn-cpt-add-btn" title="Adicionar <?php echo esc_attr($cpt['singular_name']); ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#1D2327" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M5 12h14" />
                                                <path d="M12 5v14" />
                                            </svg>
                                            <span>Novo</span>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>

                <!-- Right Column: Recent Activity -->
                <div class="wn-dash-side-column">
                    <div class="wn-dash-section-card wn-dash-activity-card">
                        <div class="wn-dash-section-head">
                            <h2 class="wn-dash-section-title">Atividade Recente</h2>
                        </div>
                        <div class="wn-dash-activity-list">
                            <?php if (!empty($recent_posts)): ?>
                                <?php foreach ($recent_posts as $p): ?>
                                    <a href="<?php echo get_edit_post_link($p->ID); ?>" class="wn-activity-item-row">
                                        <div class="wn-activity-row-icon">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#3858E9" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z" />
                                                <path d="M14 2v4a2 2 0 0 0 2 2h4" />
                                                <path d="M10 9H8" />
                                                <path d="M16 13H8" />
                                                <path d="M16 17H8" />
                                            </svg>
                                        </div>
                                        <div class="wn-activity-row-meta">
                                            <span class="wn-activity-row-title"><?php echo esc_html($p->post_title ?: '(Sem título)'); ?></span>
                                            <span class="wn-activity-row-time"><?php echo esc_html(human_time_diff(get_post_modified_time('U', false, $p)) . ' atrás'); ?></span>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="wn-no-activity">Nenhuma atividade recente registrada.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>

        </div>
        <?php
    }

    private static function get_greeting(string $name): string
    {
        $hour = (int) current_time('H');
        if ($hour < 12)
            return "Bom dia, $name!";
        if ($hour < 18)
            return "Boa tarde, $name!";
        return "Boa noite, $name!";
    }

    private static function get_site_health_score(): int
    {
        if (!class_exists('WP_Site_Health')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
        }

        $get_results = get_transient('health-check-site-status-result');
        if (false !== $get_results) {
            $results = json_decode($get_results, true);
        } else {
            return 100;
        }

        if (empty($results))
            return 100;

        $critical = isset($results['critical']) ? (int) $results['critical'] : 0;
        $recommended = isset($results['recommended']) ? (int) $results['recommended'] : 0;

        $score = 100 - ($critical * 12) - ($recommended * 4);
        return max(0, min(100, $score));
    }

    private static function get_site_health_label(int $score): string
    {
        if ($score >= 80)
            return 'O seu site está em ótimas condições.';
        if ($score >= 50)
            return 'O seu site precisa de atenção.';
        return 'Saúde do site está crítica.';
    }

    private static function is_menu_available(string $handle): bool
    {
        global $menu, $submenu;

        if (empty($menu))
            return false;

        foreach ($menu as $m) {
            if ($m[2] === $handle)
                return true;
        }

        if (!empty($submenu)) {
            foreach ($submenu as $parent => $items) {
                foreach ($items as $s) {
                    if ($s[2] === $handle)
                        return true;
                }
            }
        }

        return false;
    }
}



