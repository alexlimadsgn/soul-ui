<?php
/**
 * Module: Simple Cookie Consent
 * Description: Plugin simples para gestão de consentimento de cookies com design premium.
 * Version: 3.0.0
 * Author: Alex Lima Dsgn
 * License: MIT
 */

if (!defined('ABSPATH')) {
    exit;
}

class SimpleCookieConsent
{
    private $settings = null;

    public function __construct()
    {
        // Frontend
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_footer', array($this, 'render_cookie_banner'));

        // Backend (Admin)
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Shortcode para exibir configurações
        add_shortcode('cookie_settings_link', array($this, 'cookie_settings_shortcode'));

        // Ações AJAX para salvar preferências
        add_action('wp_ajax_scc_save_consent', array($this, 'save_consent_ajax'));
        add_action('wp_ajax_nopriv_scc_save_consent', array($this, 'save_consent_ajax'));

        // Inicializar configurações padrão
        add_action('init', array($this, 'initialize_defaults'));
    }

    /**
     * Inicializa valores padrão
     */
    public function initialize_defaults()
    {
        if (get_option('scc_initialized_5') !== 'yes') {
            // Cores base (Branco e Preto)
            update_option('scc_bg_color', '#ffffff');
            update_option('scc_text_color', '#1d2327');
            update_option('scc_modal_bg_color', '#ffffff');
            update_option('scc_modal_text_color', '#1d2327');
            
            // Botões e Detalhes
            update_option('scc_primary_color', '#000000');
            update_option('scc_secondary_color', '#ffffff');
            update_option('scc_secondary_hover_color', '#f3f4f6');
            update_option('scc_btn_secondary_text_color', '#1d2327');
            
            // Configurações de cor do modal
            update_option('scc_modal_title_color_type', 'primary');
            update_option('scc_modal_option_title_color_type', 'primary');

            // Marcar como inicializado (v5)
            update_option('scc_initialized_5', 'yes');
        }
    }

    /**
     * Enfileira scripts e estilos para o frontend
     */
    public function enqueue_assets()
    {
        wp_enqueue_style(
            'scc-style',
            plugins_url('assets/css/style.css', __FILE__),
            array(),
            '3.0.0'
        );

        wp_enqueue_script(
            'scc-script',
            plugins_url('assets/js/script.js', __FILE__),
            array(),
            '3.0.0',
            true
        );

        // Gera CSS dinâmico com variáveis
        $this->output_dynamic_css();

        // Passa configurações para o JavaScript
        wp_localize_script('scc-script', 'scc_settings', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'delay' => get_option('scc_display_delay', 1000),
            'position' => get_option('scc_banner_position', 'bottom'),
            'enable_banner' => get_option('scc_enable_banner', 'yes'),
            'enable_analytics' => get_option('scc_enable_analytics', 'yes'),
            'enable_marketing' => get_option('scc_enable_marketing', 'yes'),
            'privacy_page' => get_permalink(get_option('scc_privacy_page', '')),
            'privacy_link_text' => get_option('scc_privacy_link_text', 'Política de Privacidade'),
            'nonce' => wp_create_nonce('scc_nonce')
        ));
    }

    /**
     * Gera CSS dinâmico com variáveis baseadas nas configurações
     */
    private function output_dynamic_css()
    {
        $primary_color = $this->get_opt('scc_primary_color', '#000000');
        $primary_hover_color = $this->get_opt('scc_primary_hover_color', $this->adjust_brightness($primary_color, 40));
        $secondary_color = $this->get_opt('scc_secondary_color', '#ffffff');
        $secondary_hover_color = $this->get_opt('scc_secondary_hover_color', $this->adjust_brightness($secondary_color, -10));
        $bg_color = $this->get_opt('scc_bg_color', '#ffffff');
        $text_color = $this->get_opt('scc_text_color', '#1d2327');
        $modal_bg_color = $this->get_opt('scc_modal_bg_color', '#ffffff');
        
        // Cor do texto do modal: se não definida, usa contraste do fundo
        $custom_modal_text = get_option('scc_modal_text_color', '');
        $modal_text_color = !empty($custom_modal_text) ? $custom_modal_text : $this->get_contrast_color($modal_bg_color);

        // Cores de título do modal
        $modal_title_color_type = $this->get_opt('scc_modal_title_color_type', 'primary');
        $modal_option_title_color_type = $this->get_opt('scc_modal_option_title_color_type', 'primary');

        // Determinar cores dos títulos baseado no tipo
        $modal_title_color = $this->get_color_by_type($modal_title_color_type, $primary_color, $secondary_color);
        $modal_option_title_color = $this->get_color_by_type($modal_option_title_color_type, $primary_color, $secondary_color);

        // Cores de texto baseadas no contraste ou campo personalizado
        $custom_text_primary = get_option('scc_btn_primary_text_color', '');
        $text_on_primary = !empty($custom_text_primary) ? $custom_text_primary : $this->get_contrast_color($primary_color);

        $custom_text_secondary = get_option('scc_btn_secondary_text_color', '');
        $text_on_secondary = !empty($custom_text_secondary) ? $custom_text_secondary : $this->get_contrast_color($secondary_color);

        $text_on_bg = $this->get_contrast_color($bg_color);
        $text_on_modal = $this->get_contrast_color($modal_bg_color);

        $css_variables = array();

        // Cores principais
        $css_variables[] = '--scc-primary-color: ' . esc_attr($primary_color) . ';';
        $css_variables[] = '--scc-primary-hover: ' . esc_attr($primary_hover_color) . ';';
        $css_variables[] = '--scc-text-on-primary: ' . esc_attr($text_on_primary) . ';';

        $css_variables[] = '--scc-secondary-color: ' . esc_attr($secondary_color) . ';';
        $css_variables[] = '--scc-secondary-hover: ' . esc_attr($secondary_hover_color) . ';';
        $css_variables[] = '--scc-text-on-secondary: ' . esc_attr($text_on_secondary) . ';';

        $css_variables[] = '--scc-bg-color: ' . esc_attr($bg_color) . ';';
        $css_variables[] = '--scc-text-color: ' . esc_attr($text_color) . ';';
        $css_variables[] = '--scc-text-on-bg: ' . esc_attr($text_on_bg) . ';';

        // Modal
        $css_variables[] = '--scc-modal-bg: ' . esc_attr($modal_bg_color) . ';';
        $css_variables[] = '--scc-modal-text: ' . esc_attr($modal_text_color) . ';';
        $css_variables[] = '--scc-text-on-modal: ' . esc_attr($text_on_modal) . ';';
        $css_variables[] = '--scc-modal-title-color: ' . esc_attr($modal_title_color) . ';';
        $css_variables[] = '--scc-option-title-color: ' . esc_attr($modal_option_title_color) . ';';

        // Design
        $css_variables[] = '--scc-border-radius: ' . esc_attr($this->get_opt('scc_border_radius', 12)) . 'px;';
        $css_variables[] = '--scc-btn-radius: ' . esc_attr($this->get_opt('scc_btn_radius', 8)) . 'px;';
        $css_variables[] = '--scc-modal-radius: ' . esc_attr($this->get_opt('scc_modal_radius', 16)) . 'px;';

        // Bordas
        $banner_border = $this->get_opt('scc_enable_banner_border', 'no') === 'yes' ? '1px solid ' . $this->get_opt('scc_banner_border_color', '#ffffff33') : 'none';
        $btn_border = $this->get_opt('scc_enable_btn_border', 'no') === 'yes' ? '1px solid ' . $this->get_opt('scc_btn_border_color', '#ffffff33') : 'none';
        $css_variables[] = '--scc-banner-border: ' . esc_attr($banner_border) . ';';
        $css_variables[] = '--scc-btn-border: ' . esc_attr($btn_border) . ';';

        // Adiciona CSS inline com as variáveis
        wp_add_inline_style('scc-style', "
            :root {
                " . implode("\n", $css_variables) . "
            }
        ");
    }

    /**
     * Retorna cor baseada no tipo selecionado
     */
    private function get_color_by_type($type, $primary_color, $secondary_color)
    {
        switch ($type) {
            case 'primary':
                return $primary_color;
            case 'secondary':
                return $secondary_color;
            case 'custom':
                return get_option('scc_modal_title_custom_color', $primary_color);
            default:
                return $primary_color;
        }
    }

    /**
     * Retorna uma opção com fallback para valor padrão se estiver vazia
     */
    private function get_opt($option, $default)
    {
        $value = get_option($option, $default);
        return !empty($value) ? $value : $default;
    }

    /**
     * Normaliza um valor de cor para o formato #rrggbb (usado nos input[type="color"]).
     */
    private function normalize_hex($value, $default)
    {
        $value = trim((string) $value);
        $value = ltrim($value, '#');
        if (preg_match('/^[0-9a-fA-F]{6}$/', $value)) {
            return '#' . strtolower($value);
        }
        if (preg_match('/^[0-9a-fA-F]{3}$/', $value)) {
            return '#' . strtolower($value[0] . $value[0] . $value[1] . $value[1] . $value[2] . $value[2]);
        }
        return $default;
    }

    /**
     * Campo compacto de cor nativo (color picker + valor hex).
     */
    private function color_field($label, $name, $value, $allow_auto = false)
    {
        $auto_mode = $allow_auto && empty($value);
        $hex = $this->normalize_hex($value, $auto_mode ? '#000000' : '#ffffff');
        ob_start();
        ?>
        <div class="scc-cfield" data-field="<?php echo esc_attr($name); ?>">
            <label><?php echo esc_html($label); ?></label>
            <div class="scc-cfield-row">
                <input type="color" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($hex); ?>" class="scc-native-color"<?php echo $auto_mode ? ' disabled' : ''; ?>>
                <input type="text" class="scc-hex-value" value="<?php echo esc_attr($hex); ?>" spellcheck="false">
            </div>
            <?php if ($allow_auto): ?>
                <label class="scc-auto">
                    <input type="checkbox" value="yes"<?php echo $auto_mode ? ' checked' : ''; ?>>
                    Contraste automático
                </label>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Campo compacto de seleção.
     */
    private function select_field($label, $name, $options, $value)
    {
        ob_start();
        ?>
        <div class="scc-cfield" data-field="<?php echo esc_attr($name); ?>">
            <label><?php echo esc_html($label); ?></label>
            <select name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($name); ?>" class="scc-select-field">
                <?php foreach ($options as $val => $text): ?>
                    <option value="<?php echo esc_attr($val); ?>" <?php selected($value, $val); ?>><?php echo esc_html($text); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Função auxiliar para ajustar brilho das cores
     */
    private function adjust_brightness($hex, $steps)
    {
        $steps = max(-255, min(255, $steps));

        // Normaliza formato
        $hex = str_replace('#', '', $hex);
        if (strlen($hex) == 3) {
            $hex = str_repeat(substr($hex, 0, 1), 2) .
                str_repeat(substr($hex, 1, 1), 2) .
                str_repeat(substr($hex, 2, 1), 2);
        }

        // Divide em componentes
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        // Ajusta cada componente
        $r = max(0, min(255, $r + $steps));
        $g = max(0, min(255, $g + $steps));
        $b = max(0, min(255, $b + $steps));

        // Retorna no formato hex
        $r_hex = str_pad(dechex($r), 2, '0', STR_PAD_LEFT);
        $g_hex = str_pad(dechex($g), 2, '0', STR_PAD_LEFT);
        $b_hex = str_pad(dechex($b), 2, '0', STR_PAD_LEFT);

        return '#' . $r_hex . $g_hex . $b_hex;
    }

    /**
     * Determina cor do texto baseada no fundo
     */
    private function get_contrast_color($hexcolor)
    {
        $hexcolor = str_replace('#', '', $hexcolor);

        if (strlen($hexcolor) == 3) {
            $hexcolor = str_repeat(substr($hexcolor, 0, 1), 2) .
                str_repeat(substr($hexcolor, 1, 1), 2) .
                str_repeat(substr($hexcolor, 2, 1), 2);
        }

        $r = hexdec(substr($hexcolor, 0, 2));
        $g = hexdec(substr($hexcolor, 1, 2));
        $b = hexdec(substr($hexcolor, 2, 2));

        // Fórmula de luminância relativa
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        return $luminance > 0.5 ? '#000000' : '#ffffff';
    }

    /**
     * Enfileira scripts e estilos para o admin
     */
    public function enqueue_admin_assets($hook)
    {

        wp_enqueue_style(
            'scc-style',
            plugins_url('assets/css/style.css', __FILE__),
            array(),
            '3.0.0'
        );

        $asset_version = defined('WP_ADMIN_UI_VERSION') ? WP_ADMIN_UI_VERSION : '2.2.0';

        wp_enqueue_style(
            'scc-admin-style',
            plugins_url('assets/css/admin-style.css', __FILE__),
            array('scc-style'),
            $asset_version
        );

        // Enfileirar Scripts do Admin
        wp_enqueue_script(
            'scc-admin-script',
            plugins_url('assets/js/admin-script.js', __FILE__),
            array('jquery'),
            $asset_version,
            true
        );
    }

    /**
     * Renderiza o banner de cookies
     */
    /**
     * Renderiza o banner de cookies no frontend
     */
    public function render_cookie_banner()
    {
        $show_banner = get_option('scc_enable_banner', 'yes');

        if ('yes' !== $show_banner) {
            return;
        }
        $this->render_cookie_banner_html(false);
    }

    /**
     * Renderiza a estrutura HTML do banner e modal (frontend ou preview admin)
     */
    public function render_cookie_banner_html($is_admin = false)
    {
        $admin_class = $is_admin ? ' scc-admin-preview' : '';
        ?>
        <!-- Cookie Banner -->
        <div id="scc-cookie-banner" class="<?php echo esc_attr($admin_class); ?>">
            <button type="button" id="scc-close-banner" class="scc-close-banner" aria-label="Fechar e aceitar apenas essenciais">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
            <div class="scc-content">
                <div class="scc-text">
                    <h3 id="scc-banner-title-text"><?php echo esc_html($this->get_opt('scc_banner_title', 'Valorizamos a sua privacidade')); ?></h3>
                    <p class="scc-banner-message-wrapper">
                        <span id="scc-banner-message-text"><?php
                        $message = $this->get_opt(
                            'scc_banner_message',
                            'Utilizamos cookies para melhorar a sua experiência de navegação e analisar o nosso tráfego. Ao clicar em "Aceitar Todos", concorda com a utilização de cookies.'
                        );
                        echo nl2br(esc_html($message));
                        ?></span><?php
                        $privacy_page_id = get_option('scc_privacy_page', '');
                        if ($privacy_page_id || $is_admin):
                            $privacy_url = $privacy_page_id ? get_permalink($privacy_page_id) : '#';
                            $hide_class = ($is_admin && !$privacy_page_id) ? ' scc-option-hidden' : '';
                            ?>
                            <a href="<?php echo esc_url($privacy_url); ?>" class="scc-privacy-link<?php echo esc_attr($hide_class); ?>" target="_blank" id="scc-banner-privacy-link">
                                <?php echo esc_html($this->get_opt('scc_privacy_link_text', 'Política de Privacidade')); ?>
                            </a>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="scc-buttons">
                    <button type="button" id="scc-configure" class="scc-btn scc-btn-config">
                        <?php echo esc_html($this->get_opt('scc_configure_text', 'Configurar')); ?>
                    </button>
                    <button type="button" id="scc-accept-all" class="scc-btn scc-btn-accept">
                        <?php echo esc_html($this->get_opt('scc_accept_text', 'Aceitar Todos')); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Configuration Modal -->
        <div id="scc-cookie-modal" class="<?php echo esc_attr($admin_class); ?>">
            <div class="scc-modal-content">
                <div class="scc-modal-header">
                    <h2 id="scc-modal-title-text"><?php echo esc_html($this->get_opt('scc_modal_title', 'Preferências de Cookies')); ?></h2>
                    <button type="button" class="scc-close" aria-label="Fechar">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    </button>
                </div>

                <div class="scc-modal-body">
                    <div class="scc-option">
                        <div class="scc-option-info">
                            <h4 id="scc-necessary-title-text"><?php echo esc_html($this->get_opt('scc_necessary_title', 'Necessários')); ?></h4>
                            <p id="scc-necessary-desc-text"><?php echo esc_html($this->get_opt('scc_necessary_desc', 'Estes cookies são essenciais para o funcionamento do website e não podem ser desligados.')); ?>
                            </p>
                        </div>
                        <div class="scc-switch-wrapper">
                            <label class="scc-switch">
                                <input type="checkbox" checked disabled>
                                <span class="scc-slider"></span>
                            </label>
                        </div>
                    </div>

                    <?php 
                    $analytics_active = get_option('scc_enable_analytics', 'yes') === 'yes';
                    $analytics_class = $analytics_active ? 'scc-option scc-analytics-option' : 'scc-option scc-analytics-option scc-option-hidden';
                    ?>
                    <div class="<?php echo esc_attr($analytics_class); ?>">
                        <div class="scc-option-info">
                            <h4 id="scc-analytics-title-text"><?php echo esc_html($this->get_opt('scc_analytics_title', 'Analíticos')); ?></h4>
                            <p id="scc-analytics-desc-text"><?php echo esc_html($this->get_opt('scc_analytics_desc', 'Ajudam-nos a entender como os visitantes interagem com o site, recolhendo informações de forma anónima.')); ?>
                            </p>
                        </div>
                        <div class="scc-switch-wrapper">
                            <label class="scc-switch">
                                <input type="checkbox" id="scc-analytics">
                                <span class="scc-slider"></span>
                            </label>
                        </div>
                    </div>

                    <?php 
                    $marketing_active = get_option('scc_enable_marketing', 'yes') === 'yes';
                    $marketing_class = $marketing_active ? 'scc-option scc-marketing-option' : 'scc-option scc-marketing-option scc-option-hidden';
                    ?>
                    <div class="<?php echo esc_attr($marketing_class); ?>">
                        <div class="scc-option-info">
                            <h4 id="scc-marketing-title-text"><?php echo esc_html($this->get_opt('scc_marketing_title', 'Marketing')); ?></h4>
                            <p id="scc-marketing-desc-text"><?php echo esc_html($this->get_opt('scc_marketing_desc', 'Utilizados para apresentar anúncios relevantes e personalizados aos visitantes.')); ?>
                            </p>
                        </div>
                        <div class="scc-switch-wrapper">
                            <label class="scc-switch">
                                <input type="checkbox" id="scc-marketing">
                                <span class="scc-slider"></span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="scc-modal-footer">
                    <button type="button" id="scc-save-preferences" class="scc-btn scc-btn-config">
                        <?php echo esc_html($this->get_opt('scc_save_text', 'Guardar')); ?>
                    </button>
                    <button type="button" id="scc-accept-all-modal" class="scc-btn scc-btn-accept">
                        <?php echo esc_html($this->get_opt('scc_accept_all_text', 'Aceitar Todos')); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Adiciona menu de administração
     */
    public function add_admin_menu()
    {
        add_menu_page(
            'Simple Cookie Consent',
            'Cookie Consent',
            'manage_options',
            'simple-cookie-consent',
            array($this, 'render_admin_page'),
            'dashicons-shield-alt',
            100
        );
    }

    /**
     * Registra as configurações
     */
    public function register_settings()
    {
        // Configurações Gerais
        register_setting('scc_settings_group', 'scc_enable_banner');
        register_setting('scc_settings_group', 'scc_banner_position');
        register_setting('scc_settings_group', 'scc_display_delay');
        register_setting('scc_settings_group', 'scc_privacy_page');
        register_setting('scc_settings_group', 'scc_privacy_link_text');

        // Textos do Banner
        register_setting('scc_settings_group', 'scc_banner_title');
        register_setting('scc_settings_group', 'scc_banner_message');
        register_setting('scc_settings_group', 'scc_configure_text');
        register_setting('scc_settings_group', 'scc_accept_text');
        register_setting('scc_settings_group', 'scc_modal_title');
        register_setting('scc_settings_group', 'scc_save_text');
        register_setting('scc_settings_group', 'scc_accept_all_text');

        // Tipos de Cookies
        register_setting('scc_settings_group', 'scc_enable_analytics');
        register_setting('scc_settings_group', 'scc_enable_marketing');
        register_setting('scc_settings_group', 'scc_necessary_title');
        register_setting('scc_settings_group', 'scc_necessary_desc');
        register_setting('scc_settings_group', 'scc_analytics_title');
        register_setting('scc_settings_group', 'scc_analytics_desc');
        register_setting('scc_settings_group', 'scc_marketing_title');
        register_setting('scc_settings_group', 'scc_marketing_desc');

        // Cores principais
        register_setting('scc_settings_group', 'scc_primary_color');
        register_setting('scc_settings_group', 'scc_primary_hover_color');
        register_setting('scc_settings_group', 'scc_btn_primary_text_color');
        register_setting('scc_settings_group', 'scc_secondary_color');
        register_setting('scc_settings_group', 'scc_secondary_hover_color');
        register_setting('scc_settings_group', 'scc_btn_secondary_text_color');
        register_setting('scc_settings_group', 'scc_bg_color');
        register_setting('scc_settings_group', 'scc_text_color');

        // Cores do modal
        register_setting('scc_settings_group', 'scc_modal_bg_color');
        register_setting('scc_settings_group', 'scc_modal_text_color');
        register_setting('scc_settings_group', 'scc_modal_title_color_type');
        register_setting('scc_settings_group', 'scc_modal_title_custom_color');
        register_setting('scc_settings_group', 'scc_modal_option_title_color_type');
        register_setting('scc_settings_group', 'scc_modal_option_custom_color');

        // Design
        register_setting('scc_settings_group', 'scc_border_radius');
        register_setting('scc_settings_group', 'scc_btn_radius');
        register_setting('scc_settings_group', 'scc_modal_radius');

        // Configurações de Borda
        register_setting('scc_settings_group', 'scc_enable_banner_border');
        register_setting('scc_settings_group', 'scc_banner_border_color');
        register_setting('scc_settings_group', 'scc_enable_btn_border');
        register_setting('scc_settings_group', 'scc_btn_border_color');
    }

    public function render_admin_page()
    {
        $pages = get_pages(array('post_status' => 'publish'));
        $banner_active = get_option('scc_enable_banner', 'yes') === 'yes';
        $active_tab = isset($_GET['tab']) && in_array($_GET['tab'], ['geral', 'textos', 'cookies', 'cores'], true) ? sanitize_key($_GET['tab']) : 'geral';
        ?>
        <div class="wrap scc-admin-page">
            <h1 class="wp-heading-inline">Simple Cookie Consent</h1>
            <hr class="wp-header-end">

            <?php if (isset($_GET['settings-updated'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Configurações guardadas com sucesso!', 'simple-cookie-consent'); ?></p>
                </div>
            <?php endif; ?>

            <!-- Navegação por Abas Nativas (Padrão URL &tab=) -->
            <nav class="nav-tab-wrapper wp-clearfix">
                <a href="<?php echo esc_url(add_query_arg(['page' => 'simple-cookie-consent', 'tab' => 'geral'], admin_url('admin.php'))); ?>" 
                   class="nav-tab <?php echo $active_tab === 'geral' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Configurações Gerais', 'simple-cookie-consent'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg(['page' => 'simple-cookie-consent', 'tab' => 'textos'], admin_url('admin.php'))); ?>" 
                   class="nav-tab <?php echo $active_tab === 'textos' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Textos e Mensagens', 'simple-cookie-consent'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg(['page' => 'simple-cookie-consent', 'tab' => 'cookies'], admin_url('admin.php'))); ?>" 
                   class="nav-tab <?php echo $active_tab === 'cookies' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Tipos de Cookies', 'simple-cookie-consent'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg(['page' => 'simple-cookie-consent', 'tab' => 'cores'], admin_url('admin.php'))); ?>" 
                   class="nav-tab <?php echo $active_tab === 'cores' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Cores e Design', 'simple-cookie-consent'); ?>
                </a>
            </nav>

            <form method="post" action="options.php" class="scc-admin-form">
                <?php settings_fields('scc_settings_group'); ?>

                <?php
                // Carregar valores para o preview inicial
                $p_primary = get_option('scc_primary_color', '#2271b1');
                $p_primary_h = get_option('scc_primary_hover_color', $this->adjust_brightness($p_primary, -40));
                $p_secondary = get_option('scc_secondary_color', '#ffffff');
                $p_secondary_h = get_option('scc_secondary_hover_color', $this->adjust_brightness($p_secondary, -10));
                $p_bg = get_option('scc_bg_color', '#ffffff');
                $p_text = get_option('scc_text_color', '#1d2327');
                $p_modal_bg = get_option('scc_modal_bg_color', '#ffffff');
                $p_modal_text = get_option('scc_modal_text_color', '#1d2327');
                $p_radius = get_option('scc_border_radius', 12);
                $p_btn_radius = get_option('scc_btn_radius', 8);
                $p_modal_radius = get_option('scc_modal_radius', 16);

                $p_modal_title_type = get_option('scc_modal_title_color_type', 'primary');
                $p_modal_opt_type = get_option('scc_modal_option_title_color_type', 'primary');
                
                $p_modal_title_color = ($p_modal_title_type === 'custom') ? get_option('scc_modal_title_custom_color', $p_primary) : ($p_modal_title_type === 'secondary' ? $p_secondary : $p_primary);
                $p_modal_opt_color = ($p_modal_opt_type === 'custom') ? get_option('scc_modal_option_custom_color', $p_primary) : ($p_modal_opt_type === 'secondary' ? $p_secondary : $p_primary);

                $p_banner_border = get_option('scc_enable_banner_border', 'no') === 'yes' ? '1px solid ' . get_option('scc_banner_border_color', '#e5e5e5') : '1px solid #e5e5e5';
                $p_btn_border = get_option('scc_enable_btn_border', 'no') === 'yes' ? '1px solid ' . get_option('scc_btn_border_color', '#e5e5e5') : 'none';

                $t_on_primary = $this->get_contrast_color($p_primary);
                $t_on_secondary = $this->get_contrast_color($p_secondary);
                ?>
                <style>
                    :root {
                        --p-primary: <?php echo $p_primary; ?>;
                        --p-primary-h: <?php echo $p_primary_h; ?>;
                        --p-secondary: <?php echo $p_secondary; ?>;
                        --p-secondary-h: <?php echo $p_secondary_h; ?>;
                        --p-bg: <?php echo $p_bg; ?>;
                        --p-text: <?php echo $p_text; ?>;
                        --p-modal-bg: <?php echo $p_modal_bg; ?>;
                        --p-modal-text: <?php echo $p_modal_text; ?>;
                        --p-radius: <?php echo $p_radius; ?>px;
                        --p-btn-radius: <?php echo $p_btn_radius; ?>px;
                        --p-modal-radius: <?php echo $p_modal_radius; ?>px;
                        --p-modal-title: <?php echo $p_modal_title_color; ?>;
                        --p-modal-opt: <?php echo $p_modal_opt_color; ?>;
                        --p-on-primary: <?php echo $t_on_primary; ?>;
                        --p-on-secondary: <?php echo $t_on_secondary; ?>;
                        --p-banner-border: <?php echo $p_banner_border; ?>;
                        --p-btn-border: <?php echo $p_btn_border; ?>;
                    }
                </style>

                <div id="poststuff" class="scc-poststuff">
                    <div id="post-body" class="metabox-holder columns-2">
                        
                        <!-- Coluna Principal (Esquerda/Centro) -->
                        <div id="post-body-content" class="scc-post-body-content">
                            
                            <!-- Seção Geral -->
                            <div id="geral" class="postbox scc-admin-section <?php echo $active_tab === 'geral' ? 'active' : ''; ?>">
                                <div class="postbox-header">
                                    <h2 class="hndle">
                                        <span><?php _e('Configurações Gerais', 'simple-cookie-consent'); ?></span>
                                    </h2>
                                </div>
                                <div class="inside">
                                    <table class="form-table">
                                        <tr>
                                            <th scope="row">Ativar Banner</th>
                                            <td>
                                                <div class="scc-form-control-row">
                                                    <label class="scc-toggle-switch">
                                                        <input type="checkbox" name="scc_enable_banner" value="yes" <?php checked(get_option('scc_enable_banner', 'yes'), 'yes'); ?>>
                                                        <span class="scc-toggle-slider"></span>
                                                    </label>
                                                    <span class="description">Exibir banner de consentimento no frontend do website</span>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">Posição</th>
                                            <td>
                                                <select name="scc_banner_position" class="scc-select-field">
                                                    <option value="bottom" <?php selected(get_option('scc_banner_position', 'bottom'), 'bottom'); ?>>Inferior Direito</option>
                                                    <option value="top" <?php selected(get_option('scc_banner_position', 'bottom'), 'top'); ?>>Superior Direito</option>
                                                </select>
                                                <p class="description">Define onde o aviso de cookies irá flutuar na tela.</p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">Página de Privacidade</th>
                                            <td>
                                                <select name="scc_privacy_page" class="scc-select-field">
                                                    <option value="">-- Selecione a Página --</option>
                                                    <?php foreach ($pages as $page): ?>
                                                        <option value="<?php echo $page->ID; ?>" <?php selected(get_option('scc_privacy_page', ''), $page->ID); ?>><?php echo esc_html($page->post_title); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <p class="description">Link exibido dentro da mensagem do banner para política completa.</p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">Delay de Exibição (ms)</th>
                                            <td>
                                                <input type="number" name="scc_display_delay" value="<?php echo esc_attr(get_option('scc_display_delay', 1000)); ?>" class="small-text scc-input-number">
                                                <span class="description">Tempo em milissegundos para o banner surgir (ex: 1000ms = 1s).</span>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <!-- Seção Textos -->
                            <div id="textos" class="postbox scc-admin-section <?php echo $active_tab === 'textos' ? 'active' : ''; ?>">
                                <div class="postbox-header">
                                    <h2 class="hndle">
                                        <span><?php _e('Textos e Mensagens', 'simple-cookie-consent'); ?></span>
                                    </h2>
                                </div>
                                <div class="inside">
                                    <table class="form-table">
                                        <tr>
                                            <th scope="row">Título do Banner</th>
                                            <td><input type="text" name="scc_banner_title" value="<?php echo esc_attr(get_option('scc_banner_title', 'Valorizamos a sua privacidade')); ?>" class="regular-text scc-input-text"></td>
                                        </tr>
                                        <tr>
                                            <th scope="row">Mensagem do Banner</th>
                                            <td>
                                                <textarea name="scc_banner_message" rows="4" class="large-text scc-textarea-field"><?php echo esc_textarea(get_option('scc_banner_message', 'Utilizamos cookies para melhorar a sua experiência de navegação e analisar o nosso tráfego. Ao clicar em "Aceitar Todos", concorda com a utilização de cookies.')); ?></textarea>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">Botão "Aceitar Todos"</th>
                                            <td><input type="text" name="scc_accept_text" value="<?php echo esc_attr(get_option('scc_accept_text', 'Aceitar Todos')); ?>" class="regular-text scc-input-text"></td>
                                        </tr>
                                        <tr>
                                            <th scope="row">Botão "Configurar"</th>
                                            <td><input type="text" name="scc_configure_text" value="<?php echo esc_attr(get_option('scc_configure_text', 'Configurar')); ?>" class="regular-text scc-input-text"></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <!-- Seção Cookies -->
                            <div id="cookies" class="postbox scc-admin-section <?php echo $active_tab === 'cookies' ? 'active' : ''; ?>">
                                <div class="postbox-header">
                                    <h2 class="hndle">
                                        <span><?php _e('Tipos de Cookies e Categorias', 'simple-cookie-consent'); ?></span>
                                    </h2>
                                </div>
                                <div class="inside">
                                    <table class="form-table">
                                        <tr>
                                            <th scope="row">Cookies Analíticos</th>
                                            <td>
                                                <div class="scc-form-control-row">
                                                    <label class="scc-toggle-switch">
                                                        <input type="checkbox" name="scc_enable_analytics" value="yes" <?php checked(get_option('scc_enable_analytics', 'yes'), 'yes'); ?>>
                                                        <span class="scc-toggle-slider"></span>
                                                    </label>
                                                    <span class="description">Permitir que usuários ativem/desativem cookies de análise de tráfego</span>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">Cookies de Marketing</th>
                                            <td>
                                                <div class="scc-form-control-row">
                                                    <label class="scc-toggle-switch">
                                                        <input type="checkbox" name="scc_enable_marketing" value="yes" <?php checked(get_option('scc_enable_marketing', 'yes'), 'yes'); ?>>
                                                        <span class="scc-toggle-slider"></span>
                                                    </label>
                                                    <span class="description">Permitir que usuários ativem/desativem cookies para anúncios direcionados</span>
                                                </div>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <!-- Seção Cores -->
                            <div id="cores" class="postbox scc-admin-section <?php echo $active_tab === 'cores' ? 'active' : ''; ?>">
                                <div class="postbox-header">
                                    <h2 class="hndle">
                                        <span><?php _e('Cores e Estilo Visual', 'simple-cookie-consent'); ?></span>
                                    </h2>
                                </div>
                                <div class="inside">
                                    <div class="scc-cfields">

                                        <h4 class="scc-cgroup-title">Banner</h4>
                                        <div class="scc-cgrid">
                                            <?php echo $this->color_field('Fundo', 'scc_bg_color', get_option('scc_bg_color', '#ffffff')); ?>
                                            <?php echo $this->color_field('Texto', 'scc_text_color', get_option('scc_text_color', '#1d2327')); ?>
                                        </div>

                                        <h4 class="scc-cgroup-title">Botões</h4>
                                        <div class="scc-cgrid">
                                            <?php echo $this->color_field('Cor Primária', 'scc_primary_color', get_option('scc_primary_color', '#2271b1')); ?>
                                            <?php echo $this->color_field('Hover Primária', 'scc_primary_hover_color', get_option('scc_primary_hover_color', $this->adjust_brightness($p_primary, -40))); ?>
                                            <?php echo $this->color_field('Texto no Primário', 'scc_btn_primary_text_color', get_option('scc_btn_primary_text_color', ''), true); ?>
                                            <?php echo $this->color_field('Cor Secundária', 'scc_secondary_color', get_option('scc_secondary_color', '#ffffff')); ?>
                                            <?php echo $this->color_field('Hover Secundária', 'scc_secondary_hover_color', get_option('scc_secondary_hover_color', $this->adjust_brightness($p_secondary, -10))); ?>
                                            <?php echo $this->color_field('Texto no Secundário', 'scc_btn_secondary_text_color', get_option('scc_btn_secondary_text_color', ''), true); ?>
                                        </div>

                                        <h4 class="scc-cgroup-title">Modal</h4>
                                        <div class="scc-cgrid">
                                            <?php echo $this->color_field('Fundo', 'scc_modal_bg_color', get_option('scc_modal_bg_color', '#ffffff')); ?>
                                            <?php echo $this->color_field('Texto', 'scc_modal_text_color', get_option('scc_modal_text_color', '#1d2327')); ?>
                                            <?php echo $this->select_field('Cor do Título', 'scc_modal_title_color_type', array('primary' => 'Primária', 'secondary' => 'Secundária', 'custom' => 'Personalizada'), get_option('scc_modal_title_color_type', 'primary')); ?>
                                            <?php echo $this->color_field('Título (Personalizada)', 'scc_modal_title_custom_color', get_option('scc_modal_title_custom_color', '#2271b1')); ?>
                                            <?php echo $this->select_field('Cor dos Títulos das Opções', 'scc_modal_option_title_color_type', array('primary' => 'Primária', 'secondary' => 'Secundária', 'custom' => 'Personalizada'), get_option('scc_modal_option_title_color_type', 'primary')); ?>
                                            <?php echo $this->color_field('Opções (Personalizada)', 'scc_modal_option_custom_color', get_option('scc_modal_option_custom_color', '#2271b1')); ?>
                                        </div>

                                        <h4 class="scc-cgroup-title">Arredondamentos (px)</h4>
                                        <div class="scc-cgrid scc-cgrid-3">
                                            <div class="scc-cfield">
                                                <label>Banner</label>
                                                <input type="number" name="scc_border_radius" value="<?php echo esc_attr(get_option('scc_border_radius', 12)); ?>" min="0" max="50" class="scc-radius-input">
                                            </div>
                                            <div class="scc-cfield">
                                                <label>Botões</label>
                                                <input type="number" name="scc_btn_radius" value="<?php echo esc_attr(get_option('scc_btn_radius', 8)); ?>" min="0" max="50" class="scc-radius-input">
                                            </div>
                                            <div class="scc-cfield">
                                                <label>Modal</label>
                                                <input type="number" name="scc_modal_radius" value="<?php echo esc_attr(get_option('scc_modal_radius', 16)); ?>" min="0" max="50" class="scc-radius-input">
                                            </div>
                                        </div>

                                        <h4 class="scc-cgroup-title">Bordas</h4>
                                        <div class="scc-cgrid">
                                            <div class="scc-cfield">
                                                <label>Borda no Banner</label>
                                                <div class="scc-cfield-row">
                                                    <label class="scc-toggle-switch">
                                                        <input type="checkbox" name="scc_enable_banner_border" value="yes" <?php checked(get_option('scc_enable_banner_border', 'no'), 'yes'); ?>>
                                                        <span class="scc-toggle-slider"></span>
                                                    </label>
                                                    <input type="color" name="scc_banner_border_color" value="<?php echo esc_attr($this->normalize_hex(get_option('scc_banner_border_color', '#e5e5e5'), '#e5e5e5')); ?>" class="scc-native-color">
                                                    <input type="text" class="scc-hex-value" value="<?php echo esc_attr($this->normalize_hex(get_option('scc_banner_border_color', '#e5e5e5'), '#e5e5e5')); ?>" spellcheck="false">
                                                </div>
                                            </div>
                                                                                 <div class="scc-cfield">
                                                <label>Borda nos Botões</label>
                                                <div class="scc-cfield-row">
                                                    <label class="scc-toggle-switch">
                                                        <input type="checkbox" name="scc_enable_btn_border" value="yes" <?php checked(get_option('scc_enable_btn_border', 'no'), 'yes'); ?>>
                                                        <span class="scc-toggle-slider"></span>
                                                    </label>
                                                    <input type="color" name="scc_btn_border_color" value="<?php echo esc_attr($this->normalize_hex(get_option('scc_btn_border_color', '#e5e5e5'), '#e5e5e5')); ?>" class="scc-native-color">
                                                    <input type="text" class="scc-hex-value" value="<?php echo esc_attr($this->normalize_hex(get_option('scc_btn_border_color', '#e5e5e5'), '#e5e5e5')); ?>" spellcheck="false">
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>

                        </div><!-- /#post-body-content -->
                            
                        <!-- Coluna Lateral (Direita) - Widget Padrão WP Publicar -->
                        <div id="postbox-container-1" class="postbox-container scc-postbox-sidebar">
                            
                            <div id="submitdiv" class="postbox">
                                <div class="postbox-header">
                                    <h2 class="hndle">
                                        <span><?php _e('Publicar', 'simple-cookie-consent'); ?></span>
                                    </h2>
                                </div>
                                <div class="inside">
                                    <div class="submitbox" id="submitbox">
                                        <div id="minor-publishing">
                                            <div id="misc-publishing-actions">
                                                <div class="misc-pub-section misc-pub-post-status">
                                                    Status: <strong><?php echo $banner_active ? __('Ativo', 'simple-cookie-consent') : __('Inativo', 'simple-cookie-consent'); ?></strong>
                                                </div>
                                                <div class="misc-pub-section">
                                                    Posição: <strong><?php echo get_option('scc_banner_position', 'bottom') === 'top' ? __('Superior Direito', 'simple-cookie-consent') : __('Inferior Direito', 'simple-cookie-consent'); ?></strong>
                                                </div>
                                                <div class="misc-pub-section misc-pub-section-last">
                                                    Versão: <strong>3.0.0</strong>
                                                </div>
                                            </div>
                                            <div class="clear"></div>
                                        </div>
                                        <div id="major-publishing-actions">
                                            <div id="publishing-action">
                                                <input type="submit" name="submit" id="publish" class="button button-primary button-large scc-btn-save-main" value="<?php esc_attr_e('Guardar Alterações', 'simple-cookie-consent'); ?>">
                                            </div>
                                            <button type="button" class="button scc-btn-preview-secondary" id="scc-sidebar-preview-btn">
                                                <span class="dashicons dashicons-visibility"></span> <?php _e('Simular Banner', 'simple-cookie-consent'); ?>
                                            </button>
                                            <div class="clear"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div><!-- /#postbox-container-1 -->

                    </div><!-- /#post-body -->
                </div><!-- /#poststuff -->

            </form>
            <?php $this->render_cookie_banner_html(true); ?>
        </div>
        <?php
    }

    /**
     * Shortcode para link de configuração de cookies
     */
    public function cookie_settings_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'text' => 'Configurar Cookies',
            'class' => 'scc-settings-link',
        ), $atts, 'cookie_settings_link');

        return '<a href="#" id="scc-open-settings" class="' . esc_attr($atts['class']) . '">' . esc_html($atts['text']) . '</a>';
    }

    /**
     * Handler AJAX para salvar consentimento
     */
    public function save_consent_ajax()
    {
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'scc_nonce')) {
            wp_die('Security check failed', 403);
        }

        $consent_data = array(
            'necessary' => true,
            'analytics' => isset($_POST['analytics']) ? filter_var($_POST['analytics'], FILTER_VALIDATE_BOOLEAN) : false,
            'marketing' => isset($_POST['marketing']) ? filter_var($_POST['marketing'], FILTER_VALIDATE_BOOLEAN) : false,
            'timestamp' => current_time('mysql'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        );

        // Salvar no banco de dados (opcional)
        $this->save_consent_to_db($consent_data);

        wp_send_json_success(array(
            'message' => 'Consentimento salvo com sucesso!',
            'data' => $consent_data
        ));
    }

    /**
     * Salva consentimento no banco de dados (opcional)
     */
    private function save_consent_to_db($consent_data)
    {
        // Pode-se implementar o salvamento em uma tabela personalizada
        // ou usar transients para armazenamento temporário
        set_transient('scc_consent_' . md5($consent_data['ip'] . $consent_data['user_agent']), $consent_data, 30 * DAY_IN_SECONDS);
    }
}

new SimpleCookieConsent();