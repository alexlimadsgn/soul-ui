<?php
/**
 * Soul UI — Thumb dos Temas
 * Botão nos cards de Aparência → Temas para enviar a imagem de pré-visualização
 * (screenshot.png/jpg/webp) de um tema instalado.
 */

defined('ABSPATH') || exit;

class WP_Admin_UI_Theme_Thumb
{
    const NONCE    = 'wn_theme_thumb';
    const MAX_SIZE = 5 * MB_IN_BYTES;
    const TYPES    = [
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
    ];

    public static function init(): void
    {
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue']);
        add_action('wp_ajax_wn_theme_thumb_upload', [__CLASS__, 'ajax_upload']);
    }

    private static function can_upload(): bool
    {
        // install_themes já respeita DISALLOW_FILE_MODS.
        return current_user_can('install_themes');
    }

    public static function enqueue(): void
    {
        global $pagenow;
        if ('themes.php' !== $pagenow || !self::can_upload()) {
            return;
        }

        wp_enqueue_style(
            'wn-theme-thumb',
            WP_ADMIN_UI_URL . 'assets/admin-theme-thumb.css',
            ['wp-admin-core'],
            WP_ADMIN_UI_VERSION
        );

        wp_enqueue_script(
            'wn-theme-thumb',
            WP_ADMIN_UI_URL . 'assets/admin-theme-thumb.js',
            ['jquery'],
            WP_ADMIN_UI_VERSION,
            true
        );

        wp_localize_script('wn-theme-thumb', 'wnThemeThumb', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce(self::NONCE),
            'maxSize' => self::MAX_SIZE,
            'i18n'    => [
                'button'  => __('Thumb', 'soul-ui'),
                'title'   => __('Enviar imagem de pré-visualização do tema', 'soul-ui'),
                'tooBig'  => __('A imagem deve ter no máximo 5 MB.', 'soul-ui'),
                'badType' => __('Use uma imagem PNG, JPG ou WebP.', 'soul-ui'),
                'success' => __('Thumb atualizada.', 'soul-ui'),
                'error'   => __('Não foi possível enviar a imagem.', 'soul-ui'),
            ],
        ]);
    }

    public static function ajax_upload(): void
    {
        check_ajax_referer(self::NONCE, 'nonce');
        if (!self::can_upload()) {
            wp_send_json_error(['message' => __('Sem permissão.', 'soul-ui')], 403);
        }

        $slug  = isset($_POST['theme']) ? sanitize_text_field(wp_unslash($_POST['theme'])) : '';
        $theme = wp_get_theme($slug);
        if ('' === $slug || !$theme->exists()) {
            wp_send_json_error(['message' => __('Tema inválido.', 'soul-ui')], 400);
        }

        $file = $_FILES['file'] ?? null;
        if (!$file || UPLOAD_ERR_OK !== $file['error'] || !is_uploaded_file($file['tmp_name'])) {
            wp_send_json_error(['message' => __('Nenhuma imagem recebida.', 'soul-ui')], 400);
        }
        if ($file['size'] > self::MAX_SIZE) {
            wp_send_json_error(['message' => __('A imagem deve ter no máximo 5 MB.', 'soul-ui')], 400);
        }

        // Valida pelo conteúdo real do arquivo, não só pela extensão.
        $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], self::TYPES);
        $ext   = $check['ext'] ? strtolower($check['ext']) : '';
        if (!$ext || !isset(self::TYPES[$ext]) || !@getimagesize($file['tmp_name'])) {
            wp_send_json_error(['message' => __('Use uma imagem PNG, JPG ou WebP.', 'soul-ui')], 400);
        }
        if ('jpeg' === $ext) {
            $ext = 'jpg';
        }

        $dir = $theme->get_stylesheet_directory();
        if (!wp_is_writable($dir)) {
            wp_send_json_error(['message' => __('A pasta do tema não tem permissão de escrita.', 'soul-ui')], 500);
        }

        // Remove thumbs antigas em outros formatos para o WordPress não continuar usando a anterior.
        foreach (['png', 'gif', 'jpg', 'jpeg', 'webp', 'avif'] as $old) {
            $path = $dir . '/screenshot.' . $old;
            if (file_exists($path)) {
                wp_delete_file($path);
            }
        }

        $target = $dir . '/screenshot.' . $ext;
        if (!@move_uploaded_file($file['tmp_name'], $target)) {
            wp_send_json_error(['message' => __('Não foi possível gravar a imagem na pasta do tema.', 'soul-ui')], 500);
        }
        @chmod($target, 0644);

        // Limpa o cache para o WordPress reconhecer o novo screenshot.
        $theme->cache_delete();
        wp_clean_themes_cache();

        wp_send_json_success([
            'url' => wp_get_theme($slug)->get_screenshot() . '?v=' . time(),
        ]);
    }
}
