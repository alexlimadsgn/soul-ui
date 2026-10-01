<?php
/**
 * Template Name: Página de Manutenção
 * Description: Template simples e premium para exibir os contatos do site em modo de manutenção.
 */

if (!defined('ABSPATH')) {
    exit; // Impede acesso direto
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html(get_bloginfo('name')) . ' - ' . __('Manutenção', 'wp-maintenance-contacts'); ?></title>
    
    <?php
    // Recupera opções com fallbacks
    $bg_start = get_option('wpmc_bg_start', '#0E2E3F');
    $bg_end = get_option('wpmc_bg_end', '#081C27');
    $text_primary = get_option('wpmc_text_primary', '#ffffff');
    $text_secondary = get_option('wpmc_text_secondary', '#94a3b8');
    $accent_color = get_option('wpmc_accent_color', '#6366f1');
    $card_bg = get_option('wpmc_card_bg', 'rgba(15, 23, 42, 0.55)');
    $font_family = get_option('wpmc_font_family', 'Outfit');

    $font_weights = '300;400;600;800';
    if ($font_family === 'Roboto') {
        $font_weights = '300;400;500;700';
    }
    $fonts_url = "https://fonts.googleapis.com/css2?family=" . urlencode($font_family) . ":wght@" . $font_weights . "&display=swap";
    ?>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="<?php echo esc_url($fonts_url); ?>" rel="stylesheet">
    
    <!-- Variáveis de Estilo Dinâmicas -->
    <style>
        :root {
            --wpmc-bg-start: <?php echo esc_attr($bg_start); ?>;
            --wpmc-bg-end: <?php echo esc_attr($bg_end); ?>;
            --wpmc-text-primary: <?php echo esc_attr($text_primary); ?>;
            --wpmc-text-secondary: <?php echo esc_attr($text_secondary); ?>;
            --wpmc-accent-color: <?php echo esc_attr($accent_color); ?>;
            --wpmc-card-bg: <?php echo esc_attr($card_bg); ?>;
            --wpmc-font-family: '<?php echo esc_attr($font_family); ?>', sans-serif;
        }
    </style>
    
    <!-- Estilos do Plugin -->
    <link rel="stylesheet" href="<?php echo esc_url(plugins_url('../assets/css/maintenance.css', __FILE__)); ?>">
</head>
<body class="wpmc-maintenance-body">

    <div class="form-body">
        <div class="row">
            <!-- Coluna da Esquerda - Imagem SVG -->
            <div class="img-holder on-top">
                <div class="info-holder">
                    <img src="<?php echo esc_url(plugins_url('../assets/images/graphic.svg', __FILE__)); ?>" alt="<?php esc_attr_e('Estamos em manutenção', 'wp-maintenance-contacts'); ?>">
                </div>
            </div>

            <!-- Coluna da Direita - Conteúdo e Contatos -->
            <div class="form-holder custom-bg">
                <div class="form-content">
                    <div class="form-items">
                        
                        <!-- Logotipo -->
                        <div class="maintenance-logo">
                            <?php 
                            if (has_custom_logo()) {
                                the_custom_logo();
                            } else {
                                echo '<span class="maintenance-logo-text">' . esc_html(get_bloginfo('name')) . '</span>';
                            }
                            ?>
                        </div>

                        <!-- Mensagem de Manutenção -->
                        <div class="maintenance-content">
                            <h1><?php _e('Estamos em manutenção', 'wp-maintenance-contacts'); ?></h1>
                            <?php 
                            $phone   = get_option('wpmc_phone');
                            $email   = get_option('wpmc_email');
                            $address = get_option('wpmc_address');
                            $has_contacts = (!empty($phone) || !empty($email) || !empty($address));
                            ?>
                            <p><?php echo $has_contacts ? __('O nosso website está a passar por atualizações para melhorar a sua experiência. Entretanto, ainda pode entrar em contacto connosco através dos canais abaixo:', 'wp-maintenance-contacts') : __('O nosso website está a passar por atualizações para melhorar a sua experiência. Voltaremos em breve com novidades!', 'wp-maintenance-contacts'); ?></p>
                        </div>

                        <?php if ($has_contacts) : ?>
                        <!-- Grade de Contatos -->
                        <div class="contacts-grid">
                            
                            <?php if (!empty($phone)) : ?>
                                <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $phone)); ?>" class="contact-card">
                                    <div class="contact-icon-wrapper">
                                        <!-- Icone Telefone SVG -->
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                        </svg>
                                    </div>
                                    <div class="contact-details">
                                        <span class="contact-label"><?php _e('Telefone', 'wp-maintenance-contacts'); ?></span>
                                        <span class="contact-value"><?php echo esc_html($phone); ?></span>
                                    </div>
                                </a>
                            <?php endif; ?>

                            <?php if (!empty($email)) : ?>
                                <a href="mailto:<?php echo esc_attr($email); ?>" class="contact-card">
                                    <div class="contact-icon-wrapper">
                                        <!-- Icone Email SVG -->
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                            <polyline points="22,6 12,13 2,6"></polyline>
                                        </svg>
                                    </div>
                                    <div class="contact-details">
                                        <span class="contact-label"><?php _e('E-mail', 'wp-maintenance-contacts'); ?></span>
                                        <span class="contact-value"><?php echo esc_html($email); ?></span>
                                    </div>
                                </a>
                            <?php endif; ?>

                            <?php if (!empty($address)) : ?>
                                <div class="contact-card address-card">
                                    <div class="contact-icon-wrapper">
                                        <!-- Icone Endereço SVG -->
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                            <circle cx="12" cy="10" r="3"></circle>
                                        </svg>
                                    </div>
                                    <div class="contact-details">
                                        <span class="contact-label"><?php _e('Morada', 'wp-maintenance-contacts'); ?></span>
                                        <span class="contact-value"><?php echo nl2br(esc_html($address)); ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>

                        </div>
                        <?php endif; ?>

                        <!-- Rodapé -->
                        <footer class="maintenance-footer">
                            <p class="footer-copy">
                                <?php 
                                echo 'Copyright &copy; ' . date('Y') . ' ' . esc_html(get_bloginfo('name')) . '. ' . __('Todos os direitos reservados.', 'wp-maintenance-contacts');
                                ?>
                            </p>
                            <p class="footer-attribution">
                                <?php _e('Desenvolvido por', 'wp-maintenance-contacts'); ?> <a href="https://agilstore.pt" target="_blank" rel="noopener noreferrer">Agilstore</a>
                            </p>
                        </footer>

                    </div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
