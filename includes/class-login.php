<?php
defined( 'ABSPATH' ) || exit;

class WP_Admin_UI_Login {

    public static function init(): void {
        add_action( 'login_enqueue_scripts', [ __CLASS__, 'login_styles' ] );
        add_filter( 'login_headerurl',       [ __CLASS__, 'login_url' ] );
        add_filter( 'login_headertext',      [ __CLASS__, 'login_title' ] );
        add_filter( 'login_message',         [ __CLASS__, 'login_message' ] );
    }

    public static function login_message( string $message ): string {
        $title = 'Entre na sua conta';
        
        $html = '<div class="wn-login-header">';
        $html .= '<h2 class="wn-login-title">' . esc_html( $title ) . '</h2>';
        $html .= '</div>';
        
        return $html . $message;
    }

    public static function login_url(): string {
        return home_url();
    }

    public static function login_title(): string {
        return get_bloginfo( 'name' );
    }

    public static function login_styles(): void {
        wp_enqueue_style(
            'wp-admin-login',
            WP_ADMIN_UI_URL . 'assets/admin-login.css',
            [],
            WP_ADMIN_UI_VERSION
        );

        $logo_id  = get_theme_mod( 'custom_logo' );
        $logo_url = '';

        if ( $logo_id ) {
            $logo_data = wp_get_attachment_image_src( $logo_id, 'full' );
            if ( $logo_data ) {
                $logo_url = $logo_data[0];
            }
        }

        if ( $logo_url ) {
            $custom_css = ".login h1 a { background-image: url('" . esc_url( $logo_url ) . "') !important; }";
            wp_add_inline_style( 'wp-admin-login', $custom_css );
        } else {
            wp_add_inline_style( 'wp-admin-login', ".login h1 { display: none; }" );
        }
    }
}


