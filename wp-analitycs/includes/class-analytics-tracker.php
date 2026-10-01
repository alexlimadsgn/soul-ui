<?php
if (!defined('ABSPATH')) {
    exit;
}

class WP_Analytics_Tracker {

    public static function init(): void {
        add_action('template_redirect', [__CLASS__, 'track_page_view'], 10);
        add_action('wp_footer', [__CLASS__, 'render_event_tracker_script'], 99);
    }

    public static function get_client_ip(): string {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'REMOTE_ADDR'
        ];
        foreach ($headers as $h) {
            if (!empty($_SERVER[$h])) {
                $ips = explode(',', $_SERVER[$h]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '127.0.0.1';
    }

    public static function normalize_url(string $url): string {
        $parsed = parse_url($url);
        if (!$parsed || empty($parsed['path'])) {
            return '/';
        }
        $path = trim($parsed['path']);
        $path = preg_replace('#/+#', '/', $path);
        $path = rtrim($path, '/');
        $path = strtolower($path);
        return empty($path) ? '/' : $path;
    }

    public static function get_visitor_hash(): string {
        $ip = self::get_client_ip();
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $salt = wp_salt('auth') . date('Y-m-d');
        return hash('sha256', $ip . $ua . $salt);
    }

    public static function get_country_by_ip(string $ip): array {
        if (in_array($ip, ['127.0.0.1', '::1', 'localhost']) || strpos($ip, '192.168.') === 0 || strpos($ip, '10.') === 0) {
            return ['code' => 'LOCAL', 'name' => 'Rede Local'];
        }

        if (!empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
            return ['code' => strtoupper(sanitize_text_field($_SERVER['HTTP_CF_IPCOUNTRY'])), 'name' => ''];
        }

        $transient_key = 'wpa_geo_' . md5($ip);
        $cached = get_transient($transient_key);
        if ($cached !== false && is_array($cached)) {
            return $cached;
        }

        $response = wp_remote_get("http://ip-api.com/json/{$ip}?fields=status,country,countryCode", ['timeout' => 2]);
        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (!empty($body) && isset($body['status']) && $body['status'] === 'success') {
                $result = [
                    'code' => strtoupper($body['countryCode'] ?? 'OUTRO'),
                    'name' => $body['country'] ?? ''
                ];
                set_transient($transient_key, $result, DAY_IN_SECONDS * 7);
                return $result;
            }
        }

        return ['code' => 'OUTRO', 'name' => 'Outro'];
    }

    public static function track_page_view(): void {
        if (defined('DOING_AJAX') && DOING_AJAX) return;
        if (defined('DOING_CRON') && DOING_CRON) return;
        if (defined('REST_REQUEST') && REST_REQUEST) return;
        if (is_admin()) return;
        if (is_404() || is_feed() || is_trackback() || is_robots() || is_favicon()) return;

        $req_uri = $_SERVER['REQUEST_URI'] ?? '';
        if (preg_match('#^/(admin|login|wp-login\.php|wp-admin|wp-json|wp-cron\.php|\.well-known|xmlrpc\.php)#i', $req_uri)) {
            return;
        }

        // Ignora extensões de arquivos estáticos ou técnicos comuns
        if (preg_match('/\.(xml|txt|json|css|js|map|ico|png|jpg|jpeg|gif|webp|svg|woff|woff2|ttf|eot|pdf|zip|tar|gz)$/i', parse_url($req_uri, PHP_URL_PATH) ?? '')) {
            return;
        }

        $settings = WP_Analytics_Settings::get_settings();

        if (!empty($settings['exclude_admins']) && is_user_logged_in() && current_user_can('manage_options')) {
            return;
        }

        $ip = self::get_client_ip();
        if (!empty($settings['excluded_ips']) && WP_Analytics_Settings::is_ip_excluded($ip, $settings['excluded_ips'])) {
            return;
        }

        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (empty($ua)) return;

        // Ignora bots, crawlers, spiders, agentes de IA e ferramentas automatizadas
        $bot_regex = '/bot|crawl|spider|slurp|facebookexternalhit|whatsapp|preview|lighthouse|uptime|pingdom|headlesschrome|gptbot|chatgpt|claudebot|anthropic|bytespider|perplexity|google-extended|applebot|bingbot|yandexbot|duckduckbot|baiduspider|semrushbot|ahrefsbot|dotbot|rogerbot|exabot|mj12bot|curl|wget|python|postman|insomnia|go-http-client/i';
        if (preg_match($bot_regex, $ua)) {
            return;
        }

        // Dispositivo
        $device_type = 'desktop';
        if (preg_match('/tablet|ipad|playbook|silk/i', $ua)) {
            $device_type = 'tablet';
        } elseif (preg_match('/mobile|iphone|ipod|android|blackberry|opera mini|iemobile/i', $ua)) {
            $device_type = 'mobile';
        }

        // Navegador
        $browser = 'Outro';
        if (preg_match('/edg/i', $ua)) {
            $browser = 'Edge';
        } elseif (preg_match('/chrome/i', $ua)) {
            $browser = 'Chrome';
        } elseif (preg_match('/safari/i', $ua)) {
            $browser = 'Safari';
        } elseif (preg_match('/firefox/i', $ua)) {
            $browser = 'Firefox';
        } elseif (preg_match('/opera|opr/i', $ua)) {
            $browser = 'Opera';
        }

        // Sistema Operacional
        $os = 'Outro';
        if (preg_match('/windows/i', $ua)) {
            $os = 'Windows';
        } elseif (preg_match('/macintosh|mac os x/i', $ua)) {
            $os = 'macOS';
        } elseif (preg_match('/android/i', $ua)) {
            $os = 'Android';
        } elseif (preg_match('/iphone|ipad|ipod/i', $ua)) {
            $os = 'iOS';
        } elseif (preg_match('/linux/i', $ua)) {
            $os = 'Linux';
        }

        $geo = self::get_country_by_ip($ip);
        $visitor_hash = self::get_visitor_hash();

        // Origem (Referrer)
        $referrer = esc_url_raw($_SERVER['HTTP_REFERER'] ?? '');
        $referrer_domain = 'Direto';
        if (!empty($referrer)) {
            $host = parse_url($referrer, PHP_URL_HOST);
            $current_host = $_SERVER['HTTP_HOST'] ?? '';
            if ($host && !hash_equals($current_host, $host)) {
                $referrer_domain = preg_replace('/^www\./', '', strtolower($host));
            }
        }

        // Página & Título Canônicos
        $post_id = get_queried_object_id() ?: 0;
        $page_title = '';
        $post_type = 'page';
        $page_url = '/';

        if (is_front_page() || is_home()) {
            $page_title = get_bloginfo('name') . ' (Início)';
            $post_type = 'page';
            $page_url = '/';
        } elseif (is_singular() && $post_id > 0) {
            $page_title = get_the_title($post_id);
            $post_type = get_post_type($post_id) ?: 'page';
            $permalink = get_permalink($post_id);
            $page_url = $permalink ? self::normalize_url(wp_make_link_relative($permalink)) : self::normalize_url($req_uri);
        } elseif (is_category() || is_tag() || is_tax()) {
            $page_title = single_term_title('', false);
            $post_type = 'taxonomy';
            $term_link = get_term_link(get_queried_object());
            $page_url = !is_wp_error($term_link) ? self::normalize_url(wp_make_link_relative($term_link)) : self::normalize_url($req_uri);
        } elseif (is_archive()) {
            $page_title = get_the_archive_title();
            $post_type = 'archive';
            $page_url = self::normalize_url($req_uri);
        } elseif (is_search()) {
            $page_title = 'Pesquisa: ' . get_search_query();
            $post_type = 'search';
            $page_url = self::normalize_url($req_uri);
        } else {
            $page_title = wp_title('', false) ?: get_bloginfo('name');
            $page_url = self::normalize_url($req_uri);
        }

        $page_title = wp_strip_all_tags($page_title);

        global $wpdb;
        $table_name = WP_Analytics_DB::get_hits_table();

        try {
            $wpdb->insert(
                $table_name,
                [
                    'post_id'         => intval($post_id),
                    'page_url'        => $page_url,
                    'page_title'      => mb_substr($page_title, 0, 255),
                    'post_type'       => mb_substr($post_type, 0, 50),
                    'visitor_hash'    => $visitor_hash,
                    'referrer'        => mb_substr($referrer, 0, 500),
                    'referrer_domain' => mb_substr($referrer_domain, 0, 255),
                    'country_code'    => $geo['code'],
                    'country_name'    => $geo['name'],
                    'device_type'     => $device_type,
                    'browser'         => $browser,
                    'os'              => $os,
                    'created_at'      => current_time('mysql'),
                ],
                ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
            );
        } catch (\Throwable $e) {}
    }

    public static function render_event_tracker_script(): void {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) return;

        $settings = WP_Analytics_Settings::get_settings();
        if (!empty($settings['exclude_admins']) && is_user_logged_in() && current_user_can('manage_options')) {
            return;
        }

        $ip = self::get_client_ip();
        if (!empty($settings['excluded_ips']) && WP_Analytics_Settings::is_ip_excluded($ip, $settings['excluded_ips'])) {
            return;
        }

        $endpoint = esc_url_raw(rest_url('wp-analytics/v1/event'));
        $visitor_hash = esc_js(self::get_visitor_hash());
        ?>
        <script id="wp-analytics-tracker">
        (function() {
            var endpoint = '<?php echo $endpoint; ?>';
            var visitorHash = '<?php echo $visitor_hash; ?>';

            function trackEvent(payload) {
                if (!payload || !payload.event_name) return;
                payload.page_url = window.location.href;
                payload.page_title = document.title;
                payload.visitor_hash = visitorHash;

                var dataStr = JSON.stringify(payload);
                if (navigator.sendBeacon) {
                    var blob = new Blob([dataStr], { type: 'application/json' });
                    navigator.sendBeacon(endpoint, blob);
                } else {
                    fetch(endpoint, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: dataStr,
                        keepalive: true
                    }).catch(function() {});
                }
            }

            // Captura cliques em elementos com data-track
            document.addEventListener('click', function(e) {
                var target = e.target.closest('[data-track]');
                if (!target) return;

                var eventName = (target.getAttribute('data-track') || '').trim();
                if (!eventName) return;

                var elId = target.id || '';
                var text = (target.innerText || target.value || target.getAttribute('aria-label') || '').trim().substring(0, 100);

                trackEvent({
                    event_name: eventName,
                    element_id: elId,
                    element_class: (typeof target.className === 'string') ? target.className : '',
                    element_text: text
                });
            }, true);
        })();
        </script>
        <?php
    }
}
