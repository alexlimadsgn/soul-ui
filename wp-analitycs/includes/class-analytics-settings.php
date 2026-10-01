<?php
if (!defined('ABSPATH')) {
    exit;
}

class WP_Analytics_Settings {

    const OPTION_KEY = 'wp_analytics_settings';

    public static function init(): void {
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    public static function register_settings(): void {
        register_setting('wp_analytics_options_group', self::OPTION_KEY, [
            'type'              => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize_settings'],
            'default'           => self::get_defaults(),
        ]);
    }

    public static function get_defaults(): array {
        return [
            'exclude_admins' => 1,
            'excluded_ips'   => '',
        ];
    }

    public static function get_settings(): array {
        $saved = get_option(self::OPTION_KEY, []);
        return wp_parse_args($saved, self::get_defaults());
    }

    public static function sanitize_settings($input): array {
        $output = [];
        $output['exclude_admins'] = !empty($input['exclude_admins']) ? 1 : 0;
        $output['excluded_ips']   = !empty($input['excluded_ips']) ? sanitize_textarea_field($input['excluded_ips']) : '';
        return $output;
    }

    public static function is_ip_excluded(string $ip, string $excluded_ips_str): bool {
        if (empty($excluded_ips_str)) return false;
        $list = array_filter(array_map('trim', explode("\n", str_replace("\r", "", $excluded_ips_str))));
        foreach ($list as $excluded) {
            if ($excluded === $ip) return true;
            // Suporte para CIDR básico (ex: 192.168.1.0/24) ou curinga (ex: 192.168.*)
            if (strpos($excluded, '*') !== false) {
                $pattern = '/^' . str_replace('\*', '[0-9]+', preg_quote($excluded, '/')) . '$/';
                if (preg_match($pattern, $ip)) return true;
            }
        }
        return false;
    }
}
