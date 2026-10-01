<?php
/**
 * Module Name: Modo de Manutenção com Contatos
 * Description: Ativa uma página de manutenção elegante que exibe o logotipo do site (Customizer) e contatos personalizados (telefone, e-mail, morada).
 * Version: 1.0.0
 * Author: Alex Lima
 * Text Domain: wp-maintenance-contacts
 * License: MIT
 */

if (!defined('ABSPATH')) {
    exit; // Impede acesso direto
}

// Inicializa o plugin
function wpmc_init() {
    // Registrar as configurações
    register_setting('wpmc_settings_group', 'wpmc_active');
    register_setting('wpmc_settings_group', 'wpmc_phone');
    register_setting('wpmc_settings_group', 'wpmc_email');
    register_setting('wpmc_settings_group', 'wpmc_address');

    // Configurações de Design
    register_setting('wpmc_settings_group', 'wpmc_bg_start');
    register_setting('wpmc_settings_group', 'wpmc_bg_end');
    register_setting('wpmc_settings_group', 'wpmc_text_primary');
    register_setting('wpmc_settings_group', 'wpmc_text_secondary');
    register_setting('wpmc_settings_group', 'wpmc_accent_color');
    register_setting('wpmc_settings_group', 'wpmc_card_bg');
    register_setting('wpmc_settings_group', 'wpmc_font_family');

    // Lista de IPs permitidos
    register_setting('wpmc_settings_group', 'wpmc_allowed_ips', array(
        'sanitize_callback' => 'wpmc_sanitize_allowed_ips',
    ));
}
add_action('admin_init', 'wpmc_init');

// Enfileira os scripts e estilos necessários para o Color Picker no admin
function wpmc_admin_enqueue_scripts($hook) {
    // Permite carregamento no admin para garantir compatibilidade direta e via PJAX
    wp_enqueue_style('wpmc-admin-css', WP_ADMIN_UI_URL . 'wp-maintenance-contacts/assets/css/admin.css', array(), WP_ADMIN_UI_VERSION);
    wp_enqueue_script('wpmc-admin-js', WP_ADMIN_UI_URL . 'wp-maintenance-contacts/assets/js/admin.js', array('jquery'), WP_ADMIN_UI_VERSION, true);

    wp_localize_script('wpmc-admin-js', 'wpmcData', array(
        'ajaxUrl'  => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('wpmc_get_ip_nonce'),
        'serverIp' => wpmc_get_visitor_ip(),
    ));
}
add_action('admin_enqueue_scripts', 'wpmc_admin_enqueue_scripts');

// Adiciona o menu de configurações do WordPress
function wpmc_add_admin_menu() {
    add_options_page(
        __('Modo de Manutenção', 'wp-maintenance-contacts'),
        __('Modo de Manutenção', 'wp-maintenance-contacts'),
        'manage_options',
        'wp-maintenance-contacts',
        'wpmc_options_page'
    );
}
add_action('admin_menu', 'wpmc_add_admin_menu');

// Sanitiza a lista de IPs permitidos (remove entradas vazias e inválidas)
function wpmc_sanitize_allowed_ips($value) {
    $lines = preg_split('/\r\n|\r|\n/', $value);
    $clean = array();
    foreach ($lines as $line) {
        $ip = trim($line);
        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
            $clean[] = $ip;
        }
    }
    return implode("\n", array_unique($clean));
}

// Limpa o cache do objeto do WordPress ao atualizar ou adicionar a opção de IPs permitidos
// Garante que a remoção de IPs seja imediatamente efetiva
function wpmc_flush_ips_cache() {
    wp_cache_delete('wpmc_allowed_ips', 'options');
}
add_action('updated_option_wpmc_allowed_ips', 'wpmc_flush_ips_cache');
add_action('added_option_wpmc_allowed_ips', 'wpmc_flush_ips_cache');

// Detecta o IP real do visitante (com suporte a proxy/Cloudflare)
function wpmc_get_visitor_ip() {
    $headers = array(
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR',
    );
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = trim(explode(',', $_SERVER[$header])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '';
}

// Retorna o IP via AJAX (para o painel admin) — permite fallback via JS quando o servidor retorna IP local
function wpmc_ajax_get_ip() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 403);
    }
    $ip = wpmc_get_visitor_ip();
    wp_send_json_success(array('ip' => $ip));
}
add_action('wp_ajax_wpmc_get_ip', 'wpmc_ajax_get_ip');

// Passa os dados necessários para o JS admin
function wpmc_localize_admin_script() {
    if (!is_admin()) return;
    $hook = get_current_screen();
    if (!$hook || strpos($hook->id, 'wp-maintenance-contacts') === false) return;
    wp_localize_script('wpmc-admin-js', 'wpmcData', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('wpmc_get_ip_nonce'),
        'serverIp' => wpmc_get_visitor_ip(),
    ));
}
add_action('admin_enqueue_scripts', 'wpmc_localize_admin_script', 20);

// Renderiza a página de configurações
function wpmc_options_page() {
    // Obter opções com fallbacks
    $bg_start     = get_option('wpmc_bg_start', '#0E2E3F');
    $bg_end       = get_option('wpmc_bg_end', '#081C27');
    $text_primary = get_option('wpmc_text_primary', '#ffffff');
    $text_secondary = get_option('wpmc_text_secondary', '#94a3b8');
    $accent_color = get_option('wpmc_accent_color', '#6366f1');
    $card_bg      = get_option('wpmc_card_bg', 'rgba(15, 23, 42, 0.55)');
    $font_family  = get_option('wpmc_font_family', 'Outfit');
    $is_active    = get_option('wpmc_active');

    $visitor_ip  = wpmc_get_visitor_ip();
    $allowed_ips = get_option('wpmc_allowed_ips', '');
    $ip_count    = 0;
    if (!empty($allowed_ips)) {
        $ip_count = count(array_filter(array_map('trim', explode("\n", $allowed_ips))));
    }

    $fonts = array(
        'Outfit'     => 'Outfit',
        'Inter'      => 'Inter',
        'Poppins'    => 'Poppins',
        'Roboto'     => 'Roboto',
        'Montserrat' => 'Montserrat'
    );
    $active_tab = isset($_GET['tab']) && in_array($_GET['tab'], ['general', 'design', 'ips'], true) ? sanitize_key($_GET['tab']) : 'general';
    ?>
    <div class="wrap wpmc-admin-page">

        <h1 class="wp-heading-inline"><?php _e('Modo de Manutenção', 'wp-maintenance-contacts'); ?></h1>
        <hr class="wp-header-end">

        <?php if (isset($_GET['settings-updated'])): ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e('Configurações guardadas com sucesso!', 'wp-maintenance-contacts'); ?></p>
            </div>
        <?php endif; ?>

        <!-- Navegação por Abas Nativas (Padrão URL &tab=) -->
        <nav class="nav-tab-wrapper wp-clearfix">
            <a href="<?php echo esc_url(add_query_arg(['page' => 'wp-maintenance-contacts', 'tab' => 'general'], admin_url('options-general.php'))); ?>" 
               class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                <?php _e('Informações e Contatos', 'wp-maintenance-contacts'); ?>
            </a>
            <a href="<?php echo esc_url(add_query_arg(['page' => 'wp-maintenance-contacts', 'tab' => 'design'], admin_url('options-general.php'))); ?>" 
               class="nav-tab <?php echo $active_tab === 'design' ? 'nav-tab-active' : ''; ?>">
                <?php _e('Design e Aparência', 'wp-maintenance-contacts'); ?>
            </a>
            <a href="<?php echo esc_url(add_query_arg(['page' => 'wp-maintenance-contacts', 'tab' => 'ips'], admin_url('options-general.php'))); ?>" 
               class="nav-tab <?php echo $active_tab === 'ips' ? 'nav-tab-active' : ''; ?>">
                <?php _e('IPs Permitidos', 'wp-maintenance-contacts'); ?>
            </a>
        </nav>

        <form method="post" action="options.php" class="wpmc-admin-form">
            <?php settings_fields('wpmc_settings_group'); ?>

            <div id="poststuff" class="wpmc-poststuff">
                <div id="post-body" class="metabox-holder columns-2">

                    <!-- COLUNA PRINCIPAL -->
                    <div id="post-body-content" class="wpmc-post-body-content">

                        <!-- ABA: INFORMAÇÕES E CONTATOS -->
                        <div id="tab-general" class="postbox wpmc-admin-section <?php echo $active_tab === 'general' ? 'active' : ''; ?>">
                            <div class="postbox-header">
                                <h2 class="hndle"><span><?php _e('Informações e Contatos', 'wp-maintenance-contacts'); ?></span></h2>
                            </div>
                            <div class="inside">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><?php _e('Ativar Modo de Manutenção', 'wp-maintenance-contacts'); ?></th>
                                        <td>
                                            <div class="wpmc-form-control-row">
                                                <label class="wpmc-toggle-switch">
                                                    <input type="checkbox" name="wpmc_active" value="1" <?php checked(1, get_option('wpmc_active'), true); ?>>
                                                    <span class="wpmc-toggle-slider"></span>
                                                </label>
                                                <span class="description"><?php _e('Quando ativo, apenas a página de manutenção será exibida para os visitantes.', 'wp-maintenance-contacts'); ?></span>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr valign="top">
                                        <th scope="row"><?php _e('Telefone de Contato', 'wp-maintenance-contacts'); ?></th>
                                        <td>
                                            <input type="text" name="wpmc_phone" value="<?php echo esc_attr(get_option('wpmc_phone')); ?>" class="regular-text" placeholder="+351 912 000 000" />
                                        </td>
                                    </tr>
                                    <tr valign="top">
                                        <th scope="row"><?php _e('E-mail de Contato', 'wp-maintenance-contacts'); ?></th>
                                        <td>
                                            <input type="email" name="wpmc_email" value="<?php echo esc_attr(get_option('wpmc_email')); ?>" class="regular-text" placeholder="geral@empresa.pt" />
                                        </td>
                                    </tr>
                                    <tr valign="top">
                                        <th scope="row"><?php _e('Morada / Endereço', 'wp-maintenance-contacts'); ?></th>
                                        <td>
                                            <textarea name="wpmc_address" class="large-text" rows="3" placeholder="<?php esc_attr_e("Rua Exemplo, 123\n1000-001 Lisboa", 'wp-maintenance-contacts'); ?>"><?php echo esc_textarea(get_option('wpmc_address')); ?></textarea>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div><!-- /#tab-general -->

                        <!-- ABA: DESIGN E APARÊNCIA -->
                        <div id="tab-design" class="postbox wpmc-admin-section <?php echo $active_tab === 'design' ? 'active' : ''; ?>">
                            <div class="postbox-header">
                                <h2 class="hndle"><span><?php _e('Design e Aparência', 'wp-maintenance-contacts'); ?></span></h2>
                            </div>
                            <div class="inside">
                                <div class="wpmc-design-grid-layout">
                                    
                                    <!-- Coluna da Esquerda: Controles de Estilo -->
                                    <div class="wpmc-design-controls-col">
                                        <table class="form-table wpmc-design-table">
                                            <tr valign="top">
                                                <th scope="row"><?php _e('Fonte Principal', 'wp-maintenance-contacts'); ?></th>
                                                <td>
                                                    <select name="wpmc_font_family">
                                                        <?php foreach ($fonts as $key => $name) : ?>
                                                            <option value="<?php echo esc_attr($key); ?>" <?php selected($font_family, $key); ?>><?php echo esc_html($name); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <p class="description"><?php _e('Fonte carregada do Google Fonts.', 'wp-maintenance-contacts'); ?></p>
                                                </td>
                                            </tr>
                                            <tr valign="top">
                                                <th scope="row"><?php _e('Fundo (Início)', 'wp-maintenance-contacts'); ?></th>
                                                <td>
                                                    <div class="wpmc-picker-wrapper">
                                                        <div class="wpmc-picker-preview-button" style="background-color: <?php echo esc_attr($bg_start); ?>;"></div>
                                                        <input type="text" name="wpmc_bg_start" value="<?php echo esc_attr($bg_start); ?>" class="wpmc-color-picker" readonly />
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr valign="top">
                                                <th scope="row"><?php _e('Fundo (Fim)', 'wp-maintenance-contacts'); ?></th>
                                                <td>
                                                    <div class="wpmc-picker-wrapper">
                                                        <div class="wpmc-picker-preview-button" style="background-color: <?php echo esc_attr($bg_end); ?>;"></div>
                                                        <input type="text" name="wpmc_bg_end" value="<?php echo esc_attr($bg_end); ?>" class="wpmc-color-picker" readonly />
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr valign="top">
                                                <th scope="row"><?php _e('Fundo do Painel', 'wp-maintenance-contacts'); ?></th>
                                                <td>
                                                    <div class="wpmc-picker-wrapper">
                                                        <div class="wpmc-picker-preview-button" style="background-color: <?php echo esc_attr($card_bg); ?>;"></div>
                                                        <input type="text" name="wpmc_card_bg" value="<?php echo esc_attr($card_bg); ?>" class="wpmc-color-picker" readonly />
                                                    </div>
                                                    <p class="description"><?php _e('Cor do fundo do container central.', 'wp-maintenance-contacts'); ?></p>
                                                </td>
                                            </tr>
                                            <tr valign="top">
                                                <th scope="row"><?php _e('Texto Principal', 'wp-maintenance-contacts'); ?></th>
                                                <td>
                                                    <div class="wpmc-picker-wrapper">
                                                        <div class="wpmc-picker-preview-button" style="background-color: <?php echo esc_attr($text_primary); ?>;"></div>
                                                        <input type="text" name="wpmc_text_primary" value="<?php echo esc_attr($text_primary); ?>" class="wpmc-color-picker" readonly />
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr valign="top">
                                                <th scope="row"><?php _e('Texto Secundário', 'wp-maintenance-contacts'); ?></th>
                                                <td>
                                                    <div class="wpmc-picker-wrapper">
                                                        <div class="wpmc-picker-preview-button" style="background-color: <?php echo esc_attr($text_secondary); ?>;"></div>
                                                        <input type="text" name="wpmc_text_secondary" value="<?php echo esc_attr($text_secondary); ?>" class="wpmc-color-picker" readonly />
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr valign="top">
                                                <th scope="row"><?php _e('Cor de Destaque', 'wp-maintenance-contacts'); ?></th>
                                                <td>
                                                    <div class="wpmc-picker-wrapper">
                                                        <div class="wpmc-picker-preview-button" style="background-color: <?php echo esc_attr($accent_color); ?>;"></div>
                                                        <input type="text" name="wpmc_accent_color" value="<?php echo esc_attr($accent_color); ?>" class="wpmc-color-picker" readonly />
                                                    </div>
                                                    <p class="description"><?php _e('Ícones, links e efeitos hover.', 'wp-maintenance-contacts'); ?></p>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>

                                    <!-- Coluna da Direita: Pré-visualização em Tempo Real -->
                                    <div class="wpmc-design-preview-col">
                                        <div class="wpmc-live-preview-card">
                                            <div class="wpmc-live-preview-header">
                                                <span class="wpmc-live-preview-badge">
                                                    <span class="wpmc-live-preview-dot"></span> <?php _e('Pré-visualização em tempo real', 'wp-maintenance-contacts'); ?>
                                                </span>
                                            </div>
                                            
                                            <div class="wpmc-preview-container">
                                                <div class="wpmc-preview-page">
                                                    <div class="wpmc-preview-logo">
                                                        <?php
                                                        if (has_custom_logo()) {
                                                            $logo_img = wp_get_attachment_image_src(get_theme_mod('custom_logo'), 'full');
                                                            if ($logo_img) {
                                                                echo '<img src="' . esc_url($logo_img[0]) . '" alt="Logo" class="wpmc-preview-logo-img" />';
                                                            } else {
                                                                echo '<span class="wpmc-preview-logo-text">' . esc_html(get_bloginfo('name')) . '</span>';
                                                            }
                                                        } else {
                                                            echo '<span class="wpmc-preview-logo-text">' . esc_html(get_bloginfo('name')) . '</span>';
                                                        }
                                                        ?>
                                                    </div>
                                                    <div class="wpmc-preview-content">
                                                        <h3 class="wpmc-preview-h1"><?php _e('Estamos em manutenção', 'wp-maintenance-contacts'); ?></h3>
                                                        <?php
                                                        $preview_phone   = get_option('wpmc_phone');
                                                        $preview_email   = get_option('wpmc_email');
                                                        $preview_address = get_option('wpmc_address');
                                                        $preview_has_contacts = (!empty($preview_phone) || !empty($preview_email) || !empty($preview_address));
                                                        ?>
                                                        <p class="wpmc-preview-desc"><?php echo $preview_has_contacts ? __('O nosso website está a passar por atualizações...', 'wp-maintenance-contacts') : __('O nosso website está a passar por atualizações. Voltaremos em breve!', 'wp-maintenance-contacts'); ?></p>
                                                    </div>

                                                    <?php if ($preview_has_contacts) : ?>
                                                    <div class="wpmc-preview-contacts-grid">
                                                        <?php if (!empty($preview_phone)) : ?>
                                                        <div class="wpmc-preview-contact-item">
                                                            <div class="wpmc-preview-icon-wrapper">
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                                                            </div>
                                                            <div class="wpmc-preview-contact-details">
                                                                <span class="wpmc-preview-contact-label"><?php _e('Telefone', 'wp-maintenance-contacts'); ?></span>
                                                                <span class="wpmc-preview-contact-value"><?php echo esc_html($preview_phone); ?></span>
                                                            </div>
                                                        </div>
                                                        <?php endif; ?>

                                                        <?php if (!empty($preview_email)) : ?>
                                                        <div class="wpmc-preview-contact-item">
                                                            <div class="wpmc-preview-icon-wrapper">
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                                                            </div>
                                                            <div class="wpmc-preview-contact-details">
                                                                <span class="wpmc-preview-contact-label"><?php _e('E-mail', 'wp-maintenance-contacts'); ?></span>
                                                                <span class="wpmc-preview-contact-value"><?php echo esc_html($preview_email); ?></span>
                                                            </div>
                                                        </div>
                                                        <?php endif; ?>

                                                        <?php if (!empty($preview_address)) : ?>
                                                        <div class="wpmc-preview-contact-item">
                                                            <div class="wpmc-preview-icon-wrapper">
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                                            </div>
                                                            <div class="wpmc-preview-contact-details">
                                                                <span class="wpmc-preview-contact-label"><?php _e('Morada', 'wp-maintenance-contacts'); ?></span>
                                                                <span class="wpmc-preview-contact-value"><?php echo esc_html($preview_address); ?></span>
                                                            </div>
                                                        </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php endif; ?>

                                                    <div class="wpmc-preview-footer-attribution">
                                                        <span>Copyright &copy; <?php echo date('Y'); ?> <?php echo esc_html(get_bloginfo('name')); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                </div><!-- /.wpmc-design-grid-layout -->
                            </div>
                        </div><!-- /#tab-design -->

                        <!-- ABA: IPs PERMITIDOS -->
                        <div id="tab-ips" class="postbox wpmc-admin-section <?php echo $active_tab === 'ips' ? 'active' : ''; ?>">
                            <div class="postbox-header">
                                <h2 class="hndle">
                                    <span><?php _e('IPs Permitidos', 'wp-maintenance-contacts'); ?></span>
                                </h2>
                            </div>
                            <div class="inside">
                                <div class="wpmc-ip-current-card">
                                    <div class="wpmc-ip-current-label"><?php _e('Seu IP Atual detectado:', 'wp-maintenance-contacts'); ?></div>
                                    <div class="wpmc-ip-current-value"><?php echo esc_html($visitor_ip ?: __('Detectando...', 'wp-maintenance-contacts')); ?></div>
                                    <button type="button" id="wpmc-add-my-ip" class="button button-secondary" data-ip="<?php echo esc_attr($visitor_ip); ?>" <?php echo $visitor_ip ? '' : 'style="display:none;"'; ?>>
                                        <?php _e('+ Adicionar meu IP à lista', 'wp-maintenance-contacts'); ?>
                                    </button>
                                </div>

                                <table class="form-table">
                                    <tr valign="top">
                                        <th scope="row">
                                            <?php _e('Lista de IPs', 'wp-maintenance-contacts'); ?>
                                            <?php if ($ip_count > 0) : ?>
                                                <br><span class="wpmc-ip-count-badge"><?php echo sprintf(__('%d autorizado(s)', 'wp-maintenance-contacts'), $ip_count); ?></span>
                                            <?php endif; ?>
                                        </th>
                                        <td>
                                            <textarea
                                                id="wpmc-allowed-ips"
                                                name="wpmc_allowed_ips"
                                                class="large-text"
                                                rows="8"
                                                placeholder="<?php esc_attr_e("Um endereço IP por linha\nEx: 192.168.1.1\n    203.0.113.42", 'wp-maintenance-contacts'); ?>"
                                            ><?php echo esc_textarea($allowed_ips); ?></textarea>
                                            <p class="description">
                                                <?php _e('Os visitantes com estes IPs podem ver o site normalmente, mesmo com manutenção ativa. <strong>Administradores sempre têm acesso.</strong>', 'wp-maintenance-contacts'); ?>
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div><!-- /#tab-ips -->

                    </div><!-- /#post-body-content -->

                    <!-- SIDEBAR -->
                    <div id="postbox-container-1" class="postbox-container wpmc-postbox-sidebar">

                        <!-- Card: Publicar -->
                        <div id="submitdiv" class="postbox">
                            <div class="postbox-header">
                                <h2 class="hndle"><span><?php _e('Publicar', 'wp-maintenance-contacts'); ?></span></h2>
                            </div>
                            <div class="inside">
                                <div class="submitbox" id="submitbox">
                                    <div id="minor-publishing">
                                        <div id="misc-publishing-actions">
                                            <div class="misc-pub-section misc-pub-post-status">
                                                Status: <strong><?php echo $is_active ? __('Manutenção Ativa', 'wp-maintenance-contacts') : __('Site Online', 'wp-maintenance-contacts'); ?></strong>
                                            </div>
                                            <div class="misc-pub-section">
                                                IPs autorizados: <strong><?php echo $ip_count; ?></strong>
                                            </div>
                                            <div class="misc-pub-section misc-pub-section-last">
                                                Versão: <strong>1.0.0</strong>
                                            </div>
                                        </div>
                                        <div class="clear"></div>
                                    </div>
                                    <div id="major-publishing-actions">
                                        <div id="publishing-action">
                                            <input type="submit" name="submit" id="publish" class="button button-primary button-large wpmc-btn-block" value="<?php esc_attr_e('Guardar alterações', 'wp-maintenance-contacts'); ?>">
                                        </div>
                                        <div class="clear"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Card: Acesso Rápido -->
                        <div id="wpmc-quickaccess" class="postbox">
                            <div class="postbox-header">
                                <h2 class="hndle"><span><?php _e('Acesso Rápido', 'wp-maintenance-contacts'); ?></span></h2>
                            </div>
                            <div class="inside">
                                <p><a href="<?php echo esc_url(home_url('/')); ?>" target="_blank" class="button button-secondary wpmc-btn-block">
                                    <span class="dashicons dashicons-external"></span> <?php _e('Ver Site', 'wp-maintenance-contacts'); ?>
                                </a></p>
                                <p><a href="<?php echo esc_url(admin_url('customize.php')); ?>" class="button button-secondary wpmc-btn-block">
                                    <span class="dashicons dashicons-art"></span> <?php _e('Personalizador', 'wp-maintenance-contacts'); ?>
                                </a></p>
                            </div>
                        </div>

                    </div><!-- /.wpmc-postbox-sidebar -->

                </div><!-- /#post-body -->
            </div><!-- /#poststuff -->

        </form>
    </div>
    <?php
}

// Adiciona o aviso de manutenção flutuante (snackbar) no rodapé do site e do admin
function wpmc_render_maintenance_snackbar() {
    // Só exibe se o modo de manutenção estiver ativo e o usuário for administrador
    if (get_option('wpmc_active') && current_user_can('manage_options')) {
        ?>
        <div class="wpmc-maintenance-snackbar">
            <span class="wpmc-snackbar-icon">🛠️</span>
            <div class="wpmc-snackbar-content">
                <span class="wpmc-snackbar-title"><?php _e('Modo de Manutenção Ativo', 'wp-maintenance-contacts'); ?></span>
                <a href="<?php echo admin_url('options-general.php?page=wp-maintenance-contacts'); ?>" class="wpmc-snackbar-link"><?php _e('Configurar', 'wp-maintenance-contacts'); ?></a>
            </div>
        </div>
        <style>
            .wpmc-maintenance-snackbar {
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: #0f172a;
                color: #ffffff;
                padding: 10px 16px;
                border-radius: 10px;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
                display: flex;
                align-items: center;
                gap: 8px;
                z-index: 999999;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                border: 1px solid rgba(255, 255, 255, 0.1);
                font-size: 13px;
                line-height: 1;
                animation: wpmc-snackbar-slidein 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            }
            .wpmc-maintenance-snackbar .wpmc-snackbar-icon {
                font-size: 14px;
                display: inline-flex;
                align-items: center;
            }
            .wpmc-maintenance-snackbar .wpmc-snackbar-content {
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .wpmc-maintenance-snackbar .wpmc-snackbar-title {
                font-weight: 600;
                color: #ffffff;
            }
            .wpmc-maintenance-snackbar .wpmc-snackbar-link {
                color: #38bdf8;
                text-decoration: none;
                font-weight: 600;
                border-left: 1px solid rgba(255, 255, 255, 0.2);
                padding-left: 8px;
                display: inline-block;
                transition: color 0.2s ease;
            }
            .wpmc-maintenance-snackbar .wpmc-snackbar-link:hover {
                color: #7dd3fc;
                text-decoration: underline;
            }
            @keyframes wpmc-snackbar-slidein {
                from {
                    transform: translateY(30px);
                    opacity: 0;
                }
                to {
                    transform: translateY(0);
                    opacity: 1;
                }
            }
        </style>
        <?php
    }
}
add_action('wp_footer', 'wpmc_render_maintenance_snackbar');
add_action('admin_footer', 'wpmc_render_maintenance_snackbar');

// Intercepta a requisição do frontend e serve o template de manutenção
function wpmc_intercept_template($template) {
    // Verifica se o modo de manutenção está ativo
    if (get_option('wpmc_active')) {
        // Ignora se o usuário logado for administrador (gerencia opções)
        if (is_user_logged_in() && current_user_can('manage_options')) {
            return $template;
        }

        // Verifica se o IP do visitante está na lista de permitidos
        // Força leitura do banco ignorando o object cache para garantir
        // que alterações em IPs sejam refletidas em tempo real
        wp_cache_delete('wpmc_allowed_ips', 'options');
        $allowed_ips_raw = get_option('wpmc_allowed_ips', '');
        if (!empty($allowed_ips_raw)) {
            $allowed_list = array_filter(array_map('trim', explode("\n", $allowed_ips_raw)));
            $visitor_ip   = wpmc_get_visitor_ip();
            if ($visitor_ip && in_array($visitor_ip, $allowed_list, true)) {
                return $template;
            }
        }

        $custom_template = plugin_dir_path(__FILE__) . 'templates/maintenance-page.php';
        if (file_exists($custom_template)) {
            // Configurar cabeçalho 503 para SEO
            nocache_headers();
            header('HTTP/1.1 503 Service Unavailable');
            header('Status: 503 Service Unavailable');
            header('Retry-After: 3600'); // 1 hora
            return $custom_template;
        }
    }
    return $template;
}
add_filter('template_include', 'wpmc_intercept_template', 99);

