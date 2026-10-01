<?php
if (!defined('ABSPATH')) {
    exit;
}

class WP_Analytics_REST {

    public static function init(): void {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes(): void {
        $namespace = 'wp-analytics/v1';

        // Registrar evento (público / sem autenticação para capturar ações do site)
        register_rest_route($namespace, '/event', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'record_event'],
            'permission_callback' => '__return_true',
        ]);

        // Obter dados analíticos (apenas administradores)
        register_rest_route($namespace, '/data', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_data'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ]);
    }

    public static function record_event($request) {
        $ip = WP_Analytics_Tracker::get_client_ip();

        // Rate-limit por IP
        $rate_key   = 'wpa_rl_' . md5($ip);
        $rate_count = (int) get_transient($rate_key);
        if ($rate_count >= 100) {
            return rest_ensure_response(['success' => true, 'skipped' => 'rate_limit']);
        }
        set_transient($rate_key, $rate_count + 1, 60);

        $data = $request->get_json_params();
        if (!is_array($data)) $data = [];

        $event_name = sanitize_text_field($data['event_name'] ?? '');
        if (empty($event_name)) {
            return new WP_Error('no_event', 'Nome do evento não especificado', ['status' => 400]);
        }

        // Validação do nome do evento
        if (!preg_match('/^[a-zA-Z0-9_\-]{1,50}$/', $event_name)) {
            return new WP_Error('invalid_event', 'Nome do evento inválido', ['status' => 400]);
        }

        $settings = WP_Analytics_Settings::get_settings();
        if (!empty($settings['exclude_admins']) && is_user_logged_in() && current_user_can('manage_options')) {
            return rest_ensure_response(['success' => true, 'skipped' => 'admin_excluded']);
        }

        if (!empty($settings['excluded_ips']) && WP_Analytics_Settings::is_ip_excluded($ip, $settings['excluded_ips'])) {
            return rest_ensure_response(['success' => true, 'skipped' => 'ip_excluded']);
        }

        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $device_type = 'desktop';
        if (preg_match('/tablet|ipad|playbook|silk/i', $ua)) {
            $device_type = 'tablet';
        } elseif (preg_match('/mobile|iphone|ipod|android|blackberry|opera mini|iemobile/i', $ua)) {
            $device_type = 'mobile';
        }

        $visitor_hash = !empty($data['visitor_hash']) ? sanitize_text_field($data['visitor_hash']) : WP_Analytics_Tracker::get_visitor_hash();

        global $wpdb;
        $table_name = WP_Analytics_DB::get_events_table();

        $wpdb->insert($table_name, [
            'event_name'    => substr($event_name, 0, 100),
            'element_id'    => substr(sanitize_text_field($data['element_id'] ?? ''), 0, 100),
            'element_class' => substr(sanitize_text_field($data['element_class'] ?? ''), 0, 255),
            'element_text'  => substr(sanitize_text_field($data['element_text'] ?? ''), 0, 255),
            'page_url'      => WP_Analytics_Tracker::normalize_url($data['page_url'] ?? ''),
            'page_title'    => substr(sanitize_text_field($data['page_title'] ?? ''), 0, 255),
            'visitor_hash'  => $visitor_hash,
            'device_type'   => $device_type,
            'created_at'    => current_time('mysql'),
        ]);

        return rest_ensure_response(['success' => true]);
    }

    public static function get_data($request) {
        $range = $request->get_param('range') ?: '7d';
        $data = WP_Analytics_DB::get_analytics_data($range);
        return rest_ensure_response($data);
    }
}
