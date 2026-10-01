<?php
if (!defined('ABSPATH')) {
    exit;
}

class WP_Analytics_Admin {

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'register_admin_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
        add_action('admin_post_wp_analytics_clear_data', [__CLASS__, 'handle_clear_data']);
        add_action('admin_post_wp_analytics_clear_events', [__CLASS__, 'handle_clear_events']);
    }

    public static function register_admin_menu(): void {
        add_menu_page(
            __('WP Analytics', 'wp-analytics'),
            __('Analytics', 'wp-analytics'),
            'manage_options',
            'wp-analytics',
            [__CLASS__, 'render_admin_page'],
            'dashicons-chart-bar',
            30
        );
    }

    public static function enqueue_admin_assets(string $hook): void {
        if ($hook !== 'toplevel_page_wp-analytics') {
            return;
        }

        // Chart.js CDN leve para os gráficos nativos
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
            [],
            '4.4.1',
            false // no <head>, para o PJAX do Soul UI conseguir carregar
        );

        wp_enqueue_style(
            'wp-analytics-admin-css',
            WP_ANALYTICS_PLUGIN_URL . 'assets/css/admin.css',
            [],
            WP_ANALYTICS_VERSION
        );

        wp_enqueue_script(
            'wp-analytics-admin-js',
            WP_ANALYTICS_PLUGIN_URL . 'assets/js/admin-dashboard.js',
            ['jquery', 'chartjs'],
            WP_ANALYTICS_VERSION,
            false // no <head>, para o PJAX do Soul UI conseguir carregar
        );
    }

    public static function handle_clear_data(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Sem permissão');
        }
        check_admin_referer('wp_analytics_clear_data_nonce');
        WP_Analytics_DB::clear_data();
        wp_safe_redirect(add_query_arg(['page' => 'wp-analytics', 'tab' => 'settings', 'cleared' => 'hits'], admin_url('admin.php')));
        exit;
    }

    public static function handle_clear_events(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Sem permissão');
        }
        check_admin_referer('wp_analytics_clear_events_nonce');
        WP_Analytics_DB::clear_events();
        wp_safe_redirect(add_query_arg(['page' => 'wp-analytics', 'tab' => 'settings', 'cleared' => 'events'], admin_url('admin.php')));
        exit;
    }

    public static function render_admin_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $current_tab = sanitize_key($_GET['tab'] ?? 'overview');
        $current_range = sanitize_key($_GET['range'] ?? '7d');

        $ranges = [
            'today'     => __('Hoje', 'wp-analytics'),
            'yesterday' => __('Ontem', 'wp-analytics'),
            '7d'        => __('Últimos 7 dias', 'wp-analytics'),
            '30d'       => __('Últimos 30 dias', 'wp-analytics'),
            '90d'       => __('Últimos 90 dias', 'wp-analytics'),
            'year'      => __('Último Ano', 'wp-analytics'),
        ];

        $data = WP_Analytics_DB::get_analytics_data($current_range);
        $events_data = WP_Analytics_DB::get_events_data($current_range);
        $settings = WP_Analytics_Settings::get_settings();
        $client_ip = WP_Analytics_Tracker::get_client_ip();

        ?>
        <div class="wrap wp-analytics-wrap">
            <h1 class="wp-heading-inline"><?php _e('WP Analytics', 'wp-analytics'); ?></h1>
            <hr class="wp-header-end">

            <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true'): ?>
                <div class="notice notice-success is-dismissible"><p><?php _e('Configurações salvas com sucesso.', 'wp-analytics'); ?></p></div>
            <?php endif; ?>

            <?php if (isset($_GET['cleared'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <?php echo $_GET['cleared'] === 'hits' 
                            ? __('Histórico de visualizações de página limpo com sucesso.', 'wp-analytics') 
                            : __('Histórico de eventos limpo com sucesso.', 'wp-analytics'); ?>
                    </p>
                </div>
            <?php endif; ?>

            <!-- Navegação por Abas Nativas do WordPress -->
            <nav class="nav-tab-wrapper wp-clearfix">
                <a href="<?php echo esc_url(add_query_arg(['page' => 'wp-analytics', 'tab' => 'overview', 'range' => $current_range], admin_url('admin.php'))); ?>" 
                   class="nav-tab <?php echo $current_tab === 'overview' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Visão Geral', 'wp-analytics'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg(['page' => 'wp-analytics', 'tab' => 'pages', 'range' => $current_range], admin_url('admin.php'))); ?>" 
                   class="nav-tab <?php echo $current_tab === 'pages' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Páginas & Conteúdo', 'wp-analytics'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg(['page' => 'wp-analytics', 'tab' => 'referrers', 'range' => $current_range], admin_url('admin.php'))); ?>" 
                   class="nav-tab <?php echo $current_tab === 'referrers' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Fontes de Tráfego', 'wp-analytics'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg(['page' => 'wp-analytics', 'tab' => 'events', 'range' => $current_range], admin_url('admin.php'))); ?>" 
                   class="nav-tab <?php echo $current_tab === 'events' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Eventos Rastreados (data-track)', 'wp-analytics'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg(['page' => 'wp-analytics', 'tab' => 'settings'], admin_url('admin.php'))); ?>" 
                   class="nav-tab <?php echo $current_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Configurações', 'wp-analytics'); ?>
                </a>
            </nav>

            <div class="wp-analytics-content">
                <?php if ($current_tab !== 'settings'): ?>
                    <!-- Barra de Filtro de Período -->
                    <div class="tablenav top wp-analytics-tablenav-top">
                        <div class="alignleft actions">
                            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                                <input type="hidden" name="page" value="wp-analytics" />
                                <input type="hidden" name="tab" value="<?php echo esc_attr($current_tab); ?>" />
                                <label for="filter-range" class="screen-reader-text"><?php _e('Filtrar por período', 'wp-analytics'); ?></label>
                                <select name="range" id="filter-range" onchange="this.form.submit()">
                                    <?php foreach ($ranges as $key => $label): ?>
                                        <option value="<?php echo esc_attr($key); ?>" <?php selected($current_range, $key); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="submit" class="button" value="<?php esc_attr_e('Filtrar', 'wp-analytics'); ?>" />
                            </form>
                        </div>
                        <div class="tablenav-pages one-page">
                            <span class="displaying-num"><?php printf(__('Período: %s a %s', 'wp-analytics'), date_i18n('d/m/Y', strtotime($data['start_date'])), date_i18n('d/m/Y', strtotime($data['end_date']))); ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <?php
                switch ($current_tab) {
                    case 'pages':
                        self::render_tab_pages($data);
                        break;
                    case 'referrers':
                        self::render_tab_referrers($data);
                        break;
                    case 'events':
                        self::render_tab_events($events_data);
                        break;
                    case 'settings':
                        self::render_tab_settings($settings, $client_ip);
                        break;
                    case 'overview':
                    default:
                        self::render_tab_overview($data);
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    private static function render_tab_overview(array $data): void {
        ?>
        <!-- Cartões de Métricas Principais -->
        <div class="wp-analytics-cards">
            <div class="postbox wp-analytics-stat-card">
                <div class="postbox-header">
                    <h2><?php _e('Visualizações de Página', 'wp-analytics'); ?></h2>
                </div>
                <div class="inside">
                    <div class="wp-analytics-stat-value"><?php echo number_format_i18n($data['total_views']); ?></div>
                    <div class="wp-analytics-stat-diff <?php echo $data['views_diff'] >= 0 ? 'diff-up' : 'diff-down'; ?>">
                        <?php echo ($data['views_diff'] >= 0 ? '+' : '') . $data['views_diff'] . '% vs período anterior'; ?>
                    </div>
                </div>
            </div>

            <div class="postbox wp-analytics-stat-card">
                <div class="postbox-header">
                    <h2><?php _e('Visitantes Únicos', 'wp-analytics'); ?></h2>
                </div>
                <div class="inside">
                    <div class="wp-analytics-stat-value"><?php echo number_format_i18n($data['total_visitors']); ?></div>
                    <div class="wp-analytics-stat-diff <?php echo $data['visitors_diff'] >= 0 ? 'diff-up' : 'diff-down'; ?>">
                        <?php echo ($data['visitors_diff'] >= 0 ? '+' : '') . $data['visitors_diff'] . '% vs período anterior'; ?>
                    </div>
                </div>
            </div>

            <div class="postbox wp-analytics-stat-card">
                <div class="postbox-header">
                    <h2><?php _e('Média de Páginas / Visitante', 'wp-analytics'); ?></h2>
                </div>
                <div class="inside">
                    <div class="wp-analytics-stat-value">
                        <?php echo $data['total_visitors'] > 0 ? round($data['total_views'] / $data['total_visitors'], 1) : '0'; ?>
                    </div>
                    <div class="wp-analytics-stat-diff diff-neutral">
                        <?php _e('Páginas navegadas por sessão', 'wp-analytics'); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráfico de Evolução -->
        <div class="postbox wp-analytics-mt-15">
            <div class="postbox-header">
                <h2><?php _e('Evolução do Tráfego', 'wp-analytics'); ?></h2>
            </div>
            <div class="inside wp-analytics-p-15">
                <div class="wp-analytics-chart-container">
                    <canvas id="wpAnalyticsChart"></canvas>
                </div>
                <script>
                    window.wpAnalyticsChartData = <?php echo json_encode($data['chart_data']); ?>;
                </script>
            </div>
        </div>

        <!-- Grade com 2 Colunas: Top Páginas e Top Referrers -->
        <div class="wp-analytics-grid-2">
            <div class="postbox">
                <div class="postbox-header">
                    <h2><?php _e('Páginas Mais Acessadas', 'wp-analytics'); ?></h2>
                </div>
                <div class="inside wp-analytics-p-0-m-0">
                    <?php if (!empty($data['top_pages'])): ?>
                        <table class="wp-list-table widefat striped table-view-list wp-analytics-table-clean">
                            <thead>
                                <tr>
                                    <th><?php _e('Página', 'wp-analytics'); ?></th>
                                    <th class="wp-analytics-w-100 wp-analytics-text-right"><?php _e('Visualizações', 'wp-analytics'); ?></th>
                                    <th class="wp-analytics-w-80 wp-analytics-text-right"><?php _e('%', 'wp-analytics'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($data['top_pages'], 0, 5) as $page): ?>
                                    <tr>
                                        <td>
                                            <div class="wp-analytics-page-title">
                                                <strong><?php echo esc_html($page['title']); ?></strong>
                                                <span class="wp-analytics-page-url"><a href="<?php echo esc_url($page['url']); ?>" target="_blank"><?php echo esc_html($page['url']); ?></a></span>
                                            </div>
                                        </td>
                                        <td class="wp-analytics-text-right wp-analytics-font-bold"><?php echo number_format_i18n($page['views']); ?></td>
                                        <td class="wp-analytics-text-right wp-analytics-text-muted"><?php echo $page['percentage']; ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="wp-analytics-p-15 wp-analytics-text-muted"><?php _e('Nenhum dado registrado para este período.', 'wp-analytics'); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="postbox">
                <div class="postbox-header">
                    <h2><?php _e('Principais Origens de Tráfego', 'wp-analytics'); ?></h2>
                </div>
                <div class="inside wp-analytics-p-0-m-0">
                    <?php if (!empty($data['top_referrers'])): ?>
                        <table class="wp-list-table widefat striped table-view-list wp-analytics-table-clean">
                            <thead>
                                <tr>
                                    <th><?php _e('Origem', 'wp-analytics'); ?></th>
                                    <th class="wp-analytics-w-100 wp-analytics-text-right"><?php _e('Acessos', 'wp-analytics'); ?></th>
                                    <th class="wp-analytics-w-80 wp-analytics-text-right"><?php _e('%', 'wp-analytics'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($data['top_referrers'], 0, 5) as $ref): ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($ref['domain']); ?></strong></td>
                                        <td class="wp-analytics-text-right wp-analytics-font-bold"><?php echo number_format_i18n($ref['count']); ?></td>
                                        <td class="wp-analytics-text-right wp-analytics-text-muted"><?php echo $ref['percentage']; ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="wp-analytics-p-15 wp-analytics-text-muted"><?php _e('Nenhum dado registrado para este período.', 'wp-analytics'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Grade com 2 Colunas: Dispositivos & Países -->
        <div class="wp-analytics-grid-2 wp-analytics-mt-15">
            <div class="postbox">
                <div class="postbox-header">
                    <h2><?php _e('Dispositivos & Navegadores', 'wp-analytics'); ?></h2>
                </div>
                <div class="inside wp-analytics-p-15">
                    <div class="wp-analytics-devices-container">
                        <div>
                            <span class="dashicons dashicons-desktop wp-analytics-device-icon wp-analytics-icon-desktop"></span>
                            <div class="wp-analytics-device-val"><?php echo number_format_i18n($data['devices']['desktop'] ?? 0); ?></div>
                            <span class="wp-analytics-device-label"><?php _e('Computador', 'wp-analytics'); ?></span>
                        </div>
                        <div>
                            <span class="dashicons dashicons-smartphone wp-analytics-device-icon wp-analytics-icon-mobile"></span>
                            <div class="wp-analytics-device-val"><?php echo number_format_i18n($data['devices']['mobile'] ?? 0); ?></div>
                            <span class="wp-analytics-device-label"><?php _e('Mobile', 'wp-analytics'); ?></span>
                        </div>
                        <div>
                            <span class="dashicons dashicons-tablet wp-analytics-device-icon wp-analytics-icon-tablet"></span>
                            <div class="wp-analytics-device-val"><?php echo number_format_i18n($data['devices']['tablet'] ?? 0); ?></div>
                            <span class="wp-analytics-device-label"><?php _e('Tablet', 'wp-analytics'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="postbox">
                <div class="postbox-header">
                    <h2><?php _e('Principais Países', 'wp-analytics'); ?></h2>
                </div>
                <div class="inside wp-analytics-p-0-m-0">
                    <?php if (!empty($data['top_countries'])): ?>
                        <table class="wp-list-table widefat striped table-view-list wp-analytics-table-clean">
                            <tbody>
                                <?php foreach (array_slice($data['top_countries'], 0, 5) as $country): ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($country['name']); ?></strong> (<?php echo esc_html($country['code']); ?>)</td>
                                        <td class="wp-analytics-text-right wp-analytics-font-bold"><?php echo number_format_i18n($country['count']); ?></td>
                                        <td class="wp-analytics-text-right wp-analytics-text-muted"><?php echo $country['percentage']; ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="wp-analytics-p-15 wp-analytics-text-muted"><?php _e('Nenhum dado registrado para este período.', 'wp-analytics'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    private static function render_tab_pages(array $data): void {
        ?>
        <div class="postbox">
            <div class="postbox-header">
                <h2><?php _e('Todas as Páginas & Posts Acessados', 'wp-analytics'); ?></h2>
            </div>
            <div class="inside wp-analytics-p-0-m-0">
                <?php if (!empty($data['top_pages'])): ?>
                    <table class="wp-list-table widefat fixed striped table-view-list">
                        <thead>
                            <tr>
                                <th><?php _e('Título da Página', 'wp-analytics'); ?></th>
                                <th><?php _e('URL', 'wp-analytics'); ?></th>
                                <th class="wp-analytics-w-120"><?php _e('Tipo', 'wp-analytics'); ?></th>
                                <th class="wp-analytics-w-140 wp-analytics-text-right"><?php _e('Visualizações', 'wp-analytics'); ?></th>
                                <th class="wp-analytics-w-100 wp-analytics-text-right"><?php _e('Proporção', 'wp-analytics'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['top_pages'] as $page): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($page['title']); ?></strong></td>
                                    <td><a href="<?php echo esc_url($page['url']); ?>" target="_blank"><?php echo esc_html($page['url']); ?></a></td>
                                    <td><span class="post-state wp-analytics-post-state-clean"><?php echo esc_html($page['type']); ?></span></td>
                                    <td class="wp-analytics-text-right wp-analytics-font-bold"><?php echo number_format_i18n($page['views']); ?></td>
                                    <td class="wp-analytics-text-right wp-analytics-text-muted"><?php echo $page['percentage']; ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="wp-analytics-p-20 wp-analytics-text-muted"><?php _e('Nenhum acesso registrado no período selecionado.', 'wp-analytics'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private static function render_tab_referrers(array $data): void {
        ?>
        <div class="postbox">
            <div class="postbox-header">
                <h2><?php _e('Origens & Fontes de Tráfego', 'wp-analytics'); ?></h2>
            </div>
            <div class="inside wp-analytics-p-0-m-0">
                <?php if (!empty($data['top_referrers'])): ?>
                    <table class="wp-list-table widefat fixed striped table-view-list">
                        <thead>
                            <tr>
                                <th><?php _e('Domínio de Origem', 'wp-analytics'); ?></th>
                                <th class="wp-analytics-w-140 wp-analytics-text-right"><?php _e('Total de Acessos', 'wp-analytics'); ?></th>
                                <th class="wp-analytics-w-100 wp-analytics-text-right"><?php _e('Proporção', 'wp-analytics'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['top_referrers'] as $ref): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($ref['domain']); ?></strong></td>
                                    <td class="wp-analytics-text-right wp-analytics-font-bold"><?php echo number_format_i18n($ref['count']); ?></td>
                                    <td class="wp-analytics-text-right wp-analytics-text-muted"><?php echo $ref['percentage']; ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="wp-analytics-p-20 wp-analytics-text-muted"><?php _e('Nenhuma origem de tráfego registrada no período selecionado.', 'wp-analytics'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private static function render_tab_events(array $events_data): void {
        ?>
        <div class="postbox">
            <div class="postbox-header">
                <h2><?php _e('Resumo dos Eventos (data-track)', 'wp-analytics'); ?></h2>
            </div>
            <div class="inside wp-analytics-p-0-m-0">
                <?php if (!empty($events_data['top_events'])): ?>
                    <table class="wp-list-table widefat fixed striped table-view-list">
                        <thead>
                            <tr>
                                <th><?php _e('Nome do Evento', 'wp-analytics'); ?></th>
                                <th class="wp-analytics-w-150 wp-analytics-text-right"><?php _e('Total de Cliques', 'wp-analytics'); ?></th>
                                <th class="wp-analytics-w-150 wp-analytics-text-right"><?php _e('Usuários Únicos', 'wp-analytics'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($events_data['top_events'] as $evt): ?>
                                <tr>
                                    <td><code><?php echo esc_html($evt->event_name); ?></code></td>
                                    <td class="wp-analytics-text-right wp-analytics-font-bold"><?php echo number_format_i18n($evt->count); ?></td>
                                    <td class="wp-analytics-text-right wp-analytics-text-muted"><?php echo number_format_i18n($evt->unique_users); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="wp-analytics-p-20 wp-analytics-text-muted">
                        <p><?php _e('Nenhum evento registrado ainda.', 'wp-analytics'); ?></p>
                        <p><strong><?php _e('Como rastrear ações e cliques?', 'wp-analytics'); ?></strong><br />
                        <?php _e('Basta adicionar o atributo <code>data-track="nome_do_evento"</code> em qualquer botão, link ou formulário no seu site.', 'wp-analytics'); ?><br />
                        Exemplo: <code>&lt;a href="/contato" data-track="clique_contato"&gt;Fale Conosco&lt;/a&gt;</code></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($events_data['recent_events'])): ?>
            <div class="postbox wp-analytics-mt-20">
                <div class="postbox-header">
                    <h2><?php _e('Últimos Eventos Registrados', 'wp-analytics'); ?></h2>
                </div>
                <div class="inside wp-analytics-p-0-m-0">
                    <table class="wp-list-table widefat fixed striped table-view-list">
                        <thead>
                            <tr>
                                <th class="wp-analytics-w-150"><?php _e('Evento', 'wp-analytics'); ?></th>
                                <th><?php _e('Texto do Botão / Elemento', 'wp-analytics'); ?></th>
                                <th><?php _e('Página', 'wp-analytics'); ?></th>
                                <th class="wp-analytics-w-100"><?php _e('Dispositivo', 'wp-analytics'); ?></th>
                                <th class="wp-analytics-w-160"><?php _e('Data / Hora', 'wp-analytics'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($events_data['recent_events'] as $rev): ?>
                                <tr>
                                    <td><code><?php echo esc_html($rev->event_name); ?></code></td>
                                    <td><?php echo esc_html($rev->element_text ?: '—'); ?></td>
                                    <td><a href="<?php echo esc_url($rev->page_url); ?>" target="_blank"><?php echo esc_html($rev->page_title ?: $rev->page_url); ?></a></td>
                                    <td><?php echo esc_html(ucfirst($rev->device_type)); ?></td>
                                    <td><?php echo date_i18n('d/m/Y H:i:s', strtotime($rev->created_at)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
        <?php
    }

    private static function render_tab_settings(array $settings, string $client_ip): void {
        ?>
        <div class="postbox">
            <div class="postbox-header">
                <h2><?php _e('Opções de Rastreamento', 'wp-analytics'); ?></h2>
            </div>
            <div class="inside wp-analytics-p-20">
                <form method="post" action="options.php">
                    <?php settings_fields('wp_analytics_options_group'); ?>

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php _e('Excluir Administradores', 'wp-analytics'); ?></th>
                            <td>
                                <label for="exclude_admins">
                                    <input name="<?php echo WP_Analytics_Settings::OPTION_KEY; ?>[exclude_admins]" type="checkbox" id="exclude_admins" value="1" <?php checked(1, $settings['exclude_admins']); ?> />
                                    <?php _e('Não contabilizar visitas e eventos de administradores conectados.', 'wp-analytics'); ?>
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><label for="excluded_ips"><?php _e('IPs a Ignorar', 'wp-analytics'); ?></label></th>
                            <td>
                                <textarea name="<?php echo WP_Analytics_Settings::OPTION_KEY; ?>[excluded_ips]" id="excluded_ips" rows="5" class="large-text code"><?php echo esc_textarea($settings['excluded_ips']); ?></textarea>
                                <p class="description">
                                    <?php _e('Insira um endereço IP por linha para ignorar do rastreamento (ex: o IP do seu escritório).', 'wp-analytics'); ?><br />
                                    <?php printf(__('Seu endereço IP atual detectado é: <code>%s</code>', 'wp-analytics'), esc_html($client_ip)); ?>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button(__('Salvar Configurações', 'wp-analytics')); ?>
                </form>
            </div>
        </div>

        <!-- Zona de Limpeza de Dados -->
        <div class="postbox wp-analytics-danger-box">
            <div class="postbox-header wp-analytics-danger-header">
                <h2 class="wp-analytics-danger-title"><?php _e('Gerenciamento de Dados', 'wp-analytics'); ?></h2>
            </div>
            <div class="inside wp-analytics-p-20">
                <p><?php _e('Você pode redefinir ou limpar os dados acumulados nas tabelas de banco de dados do WP Analytics.', 'wp-analytics'); ?></p>
                <div class="wp-analytics-btn-group">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Tem certeza que deseja apagar todo o histórico de visualizações de página? Esta ação não pode ser desfeita.');">
                        <input type="hidden" name="action" value="wp_analytics_clear_data" />
                        <?php wp_nonce_field('wp_analytics_clear_data_nonce'); ?>
                        <button type="submit" class="button button-secondary wp-analytics-danger-btn">
                            <?php _e('Limpar Histórico de Visualizações', 'wp-analytics'); ?>
                        </button>
                    </form>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Tem certeza que deseja apagar todos os eventos registrados?');">
                        <input type="hidden" name="action" value="wp_analytics_clear_events" />
                        <?php wp_nonce_field('wp_analytics_clear_events_nonce'); ?>
                        <button type="submit" class="button button-secondary">
                            <?php _e('Limpar Histórico de Eventos', 'wp-analytics'); ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
}
