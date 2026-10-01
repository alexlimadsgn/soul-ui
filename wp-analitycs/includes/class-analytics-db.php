<?php
if (!defined('ABSPATH')) {
    exit;
}

class WP_Analytics_DB {

    public static function get_hits_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'wp_analytics_hits';
    }

    public static function get_events_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'wp_analytics_events';
    }

    public static function create_tables(): void {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $table_hits = self::get_hits_table();
        $sql_hits = "CREATE TABLE $table_hits (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned DEFAULT 0,
            page_url text NOT NULL,
            page_title varchar(255) DEFAULT '',
            post_type varchar(50) DEFAULT 'page',
            visitor_hash varchar(64) NOT NULL,
            referrer varchar(500) DEFAULT '',
            referrer_domain varchar(255) DEFAULT '',
            country_code varchar(10) DEFAULT '',
            country_name varchar(100) DEFAULT '',
            device_type varchar(20) DEFAULT 'desktop',
            browser varchar(50) DEFAULT '',
            os varchar(50) DEFAULT '',
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY created_at (created_at),
            KEY post_id (post_id),
            KEY visitor_hash (visitor_hash),
            KEY country_code (country_code),
            KEY referrer_domain (referrer_domain)
        ) $charset_collate;";
        dbDelta($sql_hits);

        $table_events = self::get_events_table();
        $sql_events = "CREATE TABLE $table_events (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_name varchar(100) NOT NULL,
            element_id varchar(100) DEFAULT '',
            element_class varchar(255) DEFAULT '',
            element_text varchar(255) DEFAULT '',
            page_url text NOT NULL,
            page_title varchar(255) DEFAULT '',
            visitor_hash varchar(64) NOT NULL,
            device_type varchar(20) DEFAULT 'desktop',
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY event_name (event_name),
            KEY element_id (element_id),
            KEY created_at (created_at),
            KEY visitor_hash (visitor_hash)
        ) $charset_collate;";
        dbDelta($sql_events);
    }

    public static function get_analytics_data(string $range = '7d'): array {
        global $wpdb;
        $table_name = self::get_hits_table();
        self::create_tables();

        $now = current_time('timestamp');

        switch ($range) {
            case 'today':
                $start_time = strtotime('today 00:00:00', $now);
                $end_time   = $now;
                $prev_start = strtotime('-1 day 00:00:00', $now);
                $prev_end   = strtotime('-1 day 23:59:59', $now);
                $group_by_hour = true;
                break;
            case 'yesterday':
                $start_time = strtotime('-1 day 00:00:00', $now);
                $end_time   = strtotime('-1 day 23:59:59', $now);
                $prev_start = strtotime('-2 days 00:00:00', $now);
                $prev_end   = strtotime('-2 days 23:59:59', $now);
                $group_by_hour = true;
                break;
            case '30d':
                $start_time = strtotime('-29 days 00:00:00', $now);
                $end_time   = strtotime('today 23:59:59', $now);
                $prev_start = strtotime('-59 days 00:00:00', $now);
                $prev_end   = strtotime('-30 days 23:59:59', $now);
                $group_by_hour = false;
                break;
            case '90d':
                $start_time = strtotime('-89 days 00:00:00', $now);
                $end_time   = strtotime('today 23:59:59', $now);
                $prev_start = strtotime('-179 days 00:00:00', $now);
                $prev_end   = strtotime('-90 days 23:59:59', $now);
                $group_by_hour = false;
                break;
            case 'year':
                $start_time = strtotime('-364 days 00:00:00', $now);
                $end_time   = strtotime('today 23:59:59', $now);
                $prev_start = strtotime('-729 days 00:00:00', $now);
                $prev_end   = strtotime('-365 days 23:59:59', $now);
                $group_by_hour = false;
                break;
            case '7d':
            default:
                $start_time = strtotime('-6 days 00:00:00', $now);
                $end_time   = strtotime('today 23:59:59', $now);
                $prev_start = strtotime('-13 days 00:00:00', $now);
                $prev_end   = strtotime('-7 days 23:59:59', $now);
                $group_by_hour = false;
                break;
        }

        $start_mysql = date('Y-m-d H:i:s', $start_time);
        $end_mysql   = date('Y-m-d H:i:s', $end_time);
        $prev_start_mysql = date('Y-m-d H:i:s', $prev_start);
        $prev_end_mysql   = date('Y-m-d H:i:s', $prev_end);

        // 1. Totais
        $total_views = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE created_at BETWEEN %s AND %s",
            $start_mysql, $end_mysql
        ));

        $total_visitors = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT visitor_hash) FROM {$table_name} WHERE created_at BETWEEN %s AND %s",
            $start_mysql, $end_mysql
        ));

        $prev_views = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE created_at BETWEEN %s AND %s",
            $prev_start_mysql, $prev_end_mysql
        ));

        $prev_visitors = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT visitor_hash) FROM {$table_name} WHERE created_at BETWEEN %s AND %s",
            $prev_start_mysql, $prev_end_mysql
        ));

        $views_diff = $prev_views > 0 ? round((($total_views - $prev_views) / $prev_views) * 100, 1) : ($total_views > 0 ? 100 : 0);
        $visitors_diff = $prev_visitors > 0 ? round((($total_visitors - $prev_visitors) / $prev_visitors) * 100, 1) : ($total_visitors > 0 ? 100 : 0);

        // 2. Gráfico
        $chart_data = [];
        if ($group_by_hour) {
            $raw_points = $wpdb->get_results($wpdb->prepare(
                "SELECT HOUR(created_at) as h, COUNT(*) as views, COUNT(DISTINCT visitor_hash) as visitors
                 FROM {$table_name}
                 WHERE created_at BETWEEN %s AND %s
                 GROUP BY h ORDER BY h ASC",
                $start_mysql, $end_mysql
            ));

            $db_points = [];
            if (!empty($raw_points)) {
                foreach ($raw_points as $rp) {
                    $db_points[(int)$rp->h] = $rp;
                }
            }

            for ($h = 0; $h <= 23; $h++) {
                $label = sprintf('%02d:00', $h);
                $v = isset($db_points[$h]) ? (int) $db_points[$h]->views : 0;
                $u = isset($db_points[$h]) ? (int) $db_points[$h]->visitors : 0;
                $chart_data[] = [
                    'label'    => $label,
                    'views'    => $v,
                    'visitors' => $u,
                ];
            }
        } else {
            $raw_points = $wpdb->get_results($wpdb->prepare(
                "SELECT DATE(created_at) as d, COUNT(*) as views, COUNT(DISTINCT visitor_hash) as visitors
                 FROM {$table_name}
                 WHERE created_at BETWEEN %s AND %s
                 GROUP BY d ORDER BY d ASC",
                $start_mysql, $end_mysql
            ));

            $db_points = [];
            if (!empty($raw_points)) {
                foreach ($raw_points as $rp) {
                    $db_points[$rp->d] = $rp;
                }
            }

            $curr = $start_time;
            while ($curr <= $end_time) {
                $date_key = date('Y-m-d', $curr);
                $label = date_i18n('d/m', $curr);
                $v = isset($db_points[$date_key]) ? (int) $db_points[$date_key]->views : 0;
                $u = isset($db_points[$date_key]) ? (int) $db_points[$date_key]->visitors : 0;
                $chart_data[] = [
                    'label'    => $label,
                    'views'    => $v,
                    'visitors' => $u,
                ];
                $curr = strtotime('+1 day', $curr);
            }
        }

        // 3. Top Páginas
        $max_page_views = $total_views > 0 ? $total_views : 1;
        $raw_pages = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                CASE WHEN page_url = '' OR page_url IS NULL THEN '/' ELSE page_url END as page_url,
                MAX(page_title) as page_title,
                MAX(post_type) as post_type,
                COUNT(*) as views
             FROM {$table_name}
             WHERE created_at BETWEEN %s AND %s
             GROUP BY CASE WHEN page_url = '' OR page_url IS NULL THEN '/' ELSE page_url END
             ORDER BY views DESC
             LIMIT 10",
            $start_mysql, $end_mysql
        ));

        $top_pages = [];
        if (!empty($raw_pages)) {
            foreach ($raw_pages as $p) {
                $views = (int) $p->views;
                $top_pages[] = [
                    'title'      => !empty($p->page_title) ? $p->page_title : '(Início)',
                    'url'        => $p->page_url,
                    'type'       => $p->post_type,
                    'views'      => $views,
                    'percentage' => round(($views / $max_page_views) * 100),
                ];
            }
        }

        // 4. Top Referrers
        $raw_referrers = $wpdb->get_results($wpdb->prepare(
            "SELECT referrer_domain, COUNT(*) as count
             FROM {$table_name}
             WHERE created_at BETWEEN %s AND %s
             GROUP BY referrer_domain
             ORDER BY count DESC
             LIMIT 10",
            $start_mysql, $end_mysql
        ));

        $top_referrers = [];
        if (!empty($raw_referrers)) {
            foreach ($raw_referrers as $r) {
                $count = (int) $r->count;
                $top_referrers[] = [
                    'domain'     => !empty($r->referrer_domain) ? $r->referrer_domain : 'Acesso Direto',
                    'count'      => $count,
                    'percentage' => round(($count / $max_page_views) * 100),
                ];
            }
        }

        // 5. Top Países
        $country_names = [
            'PT'    => 'Portugal',
            'BR'    => 'Brasil',
            'ES'    => 'Espanha',
            'FR'    => 'França',
            'GB'    => 'Reino Unido',
            'UK'    => 'Reino Unido',
            'DE'    => 'Alemanha',
            'IT'    => 'Itália',
            'CH'    => 'Suíça',
            'BE'    => 'Bélgica',
            'LU'    => 'Luxemburgo',
            'NL'    => 'Países Baixos',
            'IE'    => 'Irlanda',
            'US'    => 'Estados Unidos',
            'CA'    => 'Canadá',
            'AO'    => 'Angola',
            'MZ'    => 'Moçambique',
            'CV'    => 'Cabo Verde',
            'GW'    => 'Guiné-Bissau',
            'ST'    => 'São Tomé e Príncipe',
            'TL'    => 'Timor-Leste',
            'LOCAL' => 'Rede Local (Desenvolvimento)',
            'OUTRO' => 'Outro / Não Identificado',
        ];

        $raw_countries = $wpdb->get_results($wpdb->prepare(
            "SELECT country_code, COUNT(*) as count
             FROM {$table_name}
             WHERE created_at BETWEEN %s AND %s AND country_code != ''
             GROUP BY country_code
             ORDER BY count DESC
             LIMIT 10",
            $start_mysql, $end_mysql
        ));

        $top_countries = [];
        if (!empty($raw_countries)) {
            foreach ($raw_countries as $c) {
                $code = strtoupper($c->country_code);
                $count = (int) $c->count;
                $top_countries[] = [
                    'code'       => $code,
                    'name'       => $country_names[$code] ?? $code,
                    'count'      => $count,
                    'percentage' => round(($count / $max_page_views) * 100),
                ];
            }
        }

        // 6. Dispositivos & Navegadores
        $raw_devices = $wpdb->get_results($wpdb->prepare(
            "SELECT device_type, COUNT(*) as count
             FROM {$table_name}
             WHERE created_at BETWEEN %s AND %s
             GROUP BY device_type",
            $start_mysql, $end_mysql
        ));

        $devices = ['desktop' => 0, 'mobile' => 0, 'tablet' => 0];
        if (!empty($raw_devices)) {
            foreach ($raw_devices as $d) {
                $dt = strtolower($d->device_type);
                if (isset($devices[$dt])) {
                    $devices[$dt] = (int) $d->count;
                }
            }
        }

        $raw_browsers = $wpdb->get_results($wpdb->prepare(
            "SELECT browser, COUNT(*) as count
             FROM {$table_name}
             WHERE created_at BETWEEN %s AND %s AND browser != ''
             GROUP BY browser
             ORDER BY count DESC
             LIMIT 5",
            $start_mysql, $end_mysql
        ));

        return [
            'range'          => $range,
            'start_date'     => $start_mysql,
            'end_date'       => $end_mysql,
            'total_views'    => $total_views,
            'total_visitors' => $total_visitors,
            'views_diff'     => $views_diff,
            'visitors_diff'  => $visitors_diff,
            'chart_data'     => $chart_data,
            'top_pages'      => $top_pages,
            'top_referrers'  => $top_referrers,
            'top_countries'  => $top_countries,
            'devices'        => $devices,
            'browsers'       => $raw_browsers ?: [],
        ];
    }

    public static function get_events_data(string $range = '7d'): array {
        global $wpdb;
        $table_name = self::get_events_table();
        self::create_tables();
        $now = current_time('timestamp');

        switch ($range) {
            case 'today':
                $start_time = strtotime('today 00:00:00', $now);
                break;
            case 'yesterday':
                $start_time = strtotime('-1 day 00:00:00', $now);
                break;
            case '30d':
                $start_time = strtotime('-29 days 00:00:00', $now);
                break;
            case '90d':
                $start_time = strtotime('-89 days 00:00:00', $now);
                break;
            case 'year':
                $start_time = strtotime('-364 days 00:00:00', $now);
                break;
            case '7d':
            default:
                $start_time = strtotime('-6 days 00:00:00', $now);
                break;
        }

        $start_mysql = date('Y-m-d H:i:s', $start_time);
        $end_mysql   = date('Y-m-d H:i:s', $now);

        $total_events = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE created_at BETWEEN %s AND %s",
            $start_mysql, $end_mysql
        ));

        $top_events = $wpdb->get_results($wpdb->prepare(
            "SELECT event_name, COUNT(*) as count, COUNT(DISTINCT visitor_hash) as unique_users
             FROM {$table_name}
             WHERE created_at BETWEEN %s AND %s
             GROUP BY event_name
             ORDER BY count DESC
             LIMIT 15",
            $start_mysql, $end_mysql
        ));

        $recent_events = $wpdb->get_results($wpdb->prepare(
            "SELECT event_name, element_id, element_text, page_title, page_url, device_type, created_at
             FROM {$table_name}
             WHERE created_at BETWEEN %s AND %s
             ORDER BY id DESC
             LIMIT 25",
            $start_mysql, $end_mysql
        ));

        return [
            'total_events'  => $total_events,
            'top_events'    => $top_events ?: [],
            'recent_events' => $recent_events ?: [],
        ];
    }

    public static function clear_data(): bool {
        global $wpdb;
        $table_hits = self::get_hits_table();
        $wpdb->query("TRUNCATE TABLE {$table_hits}");
        return true;
    }

    public static function clear_events(): bool {
        global $wpdb;
        $table_events = self::get_events_table();
        $wpdb->query("TRUNCATE TABLE {$table_events}");
        return true;
    }
}
