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

    /**
     * Menor valor entre o limite do plugin e o do servidor (upload_max_filesize / post_max_size).
     */
    private static function max_size(): int
    {
        return (int) min(self::MAX_SIZE, wp_max_upload_size());
    }

    private static function too_big_message(): string
    {
        /* translators: %s: tamanho máximo, ex. "2 MB" */
        return sprintf(__('A imagem deve ter no máximo %s.', 'soul-ui'), size_format(self::max_size()));
    }

    private static function upload_error_message(int $code): string
    {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                /* translators: %s: limite do servidor, ex. "2 MB" */
                return sprintf(__('A imagem passa do limite de upload do servidor (%s).', 'soul-ui'), size_format(wp_max_upload_size()));
            case UPLOAD_ERR_PARTIAL:
                return __('O envio foi interrompido. Tente novamente.', 'soul-ui');
            case UPLOAD_ERR_NO_TMP_DIR:
            case UPLOAD_ERR_CANT_WRITE:
                return __('O servidor não conseguiu gravar o arquivo temporário.', 'soul-ui');
            default:
                return __('Nenhuma imagem recebida.', 'soul-ui');
        }
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
            'maxSize' => self::max_size(),
            'i18n'    => [
                'button'  => __('Thumb', 'soul-ui'),
                'title'   => __('Enviar imagem de pré-visualização do tema', 'soul-ui'),
                'tooBig'  => self::too_big_message(),
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
        if (!$file) {
            wp_send_json_error(['message' => __('Nenhuma imagem recebida.', 'soul-ui')], 400);
        }
        if (UPLOAD_ERR_OK !== $file['error']) {
            wp_send_json_error(['message' => self::upload_error_message((int) $file['error'])], 400);
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            wp_send_json_error(['message' => __('Nenhuma imagem recebida.', 'soul-ui')], 400);
        }
        if ($file['size'] > self::max_size()) {
            wp_send_json_error(['message' => self::too_big_message()], 400);
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
