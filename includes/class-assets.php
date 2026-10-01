<?php
defined( 'ABSPATH' ) || exit;

class WP_Admin_UI_Assets {

    public static function init(): void {
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
        add_action( 'admin_head', [ __CLASS__, 'render_state_restorer' ], 1 );
        add_action( 'admin_body_open', [ __CLASS__, 'render_backgrounds' ], 1 );
        add_action( 'in_admin_header', [ __CLASS__, 'render_backgrounds' ], 1 );
    }

    /**
     * Renders the solid background divs for sidebar and topbar at the very top of body,
     * outside of sidebar and topbar DOM elements to prevent flickering during reload/transitions.
     */
    public static function render_backgrounds(): void {
        static $rendered = false;
        if ( $rendered ) {
            return;
        }

        global $pagenow;
        if ( 'customize.php' === $pagenow ) {
            return;
        }

        $screen = get_current_screen();
        if ( $screen && $screen->is_block_editor() ) {
            return;
        }

        $rendered = true;
        ?>
        <div id="wn-sidebar-bg"></div>
        <div id="wn-adminbar-bg"></div>
        <?php
    }

    /**
     * Injects a tiny JS block in the head to restore the sidebar's collapsed state
     * before the DOM renders to prevent the 'flash' effect.
     */
    public static function render_state_restorer(): void {
        $screen = get_current_screen();
        if ( $screen && $screen->is_block_editor() ) {
            return;
        }
        ?><script id="wn-state-restorer">
            (function(html){
                html.classList.add('wn-render');
                var collapsed = localStorage.getItem('wn-collapsed') === '1';
                if (collapsed) {
                    html.classList.add('wn-collapsed');
                    html.style.setProperty('--wn-w', '56px');
                } else {
                    html.style.setProperty('--wn-w', '200px');
                }

                // Fallback de segurança para garantir a exibição se o JS do rodapé falhar
                setTimeout(function(){
                    html.classList.remove('wn-render');
                }, 800);
            })(document.documentElement);
        </script>
        <?php
    }

    public static function enqueue(): void {
        global $pagenow;
        if ( 'customize.php' === $pagenow ) {
            return;
        }

        $screen = get_current_screen();
        if ( $screen && $screen->is_block_editor() ) {
            return;
        }

        // ── Material Symbols (Icons) ────────────────────────────────────────
        wp_enqueue_style(
            'material-symbols-rounded',
            'https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0',
            [],
            null
        );

        // ── Core Theme & Shell (Layout, Reset, Sidebar, Topbar) ──────────────
        wp_enqueue_style(
            'wp-admin-core',
            WP_ADMIN_UI_URL . 'assets/admin-core.css',
            [ 'material-symbols-rounded' ],
            WP_ADMIN_UI_VERSION
        );


        // ── List Tables & Content Components ─────────────────────────────────
        wp_enqueue_style(
            'wp-admin-listtables',
            WP_ADMIN_UI_URL . 'assets/admin-listtables.css',
            [ 'wp-admin-core' ],
            WP_ADMIN_UI_VERSION
        );


        // ── Editor (Theme/Plugin) ───────────────────────────────────────────
        if ( in_array( $pagenow, [ 'theme-editor.php', 'plugin-editor.php' ] ) ) {
            wp_enqueue_style(
                'wp-admin-editor',
                WP_ADMIN_UI_URL . 'assets/admin-editor.css',
                [ 'wp-admin-sidebar' ],
                WP_ADMIN_UI_VERSION
            );
        }

        // ── JS ───────────────────────────────────────────────────────────────
        wp_enqueue_script(
            'lucide-icons',
            WP_ADMIN_UI_URL . 'assets/lucide.min.js',
            [],
            WP_ADMIN_UI_VERSION,
            false
        );

        wp_enqueue_script(
            'wp-admin-sidebar',
            WP_ADMIN_UI_URL . 'assets/admin-sidebar.js',
            [ 'jquery', 'lucide-icons' ],
            WP_ADMIN_UI_VERSION,
            true
        );

        wp_enqueue_script(
            'wp-admin-pjax',
            WP_ADMIN_UI_URL . 'assets/admin-pjax.js',
            [ 'wp-admin-sidebar', 'lucide-icons' ],
            WP_ADMIN_UI_VERSION,
            true
        );

        // ── Gerir Acessos (Suporte PJAX) ────────────────────────────────────
        wp_enqueue_style(
            'ga-admin-style',
            WP_ADMIN_UI_URL . 'assets/admin-style.css',
            [ 'wp-admin-core' ],
            WP_ADMIN_UI_VERSION
        );

        wp_enqueue_script(
            'ga-admin-script',
            WP_ADMIN_UI_URL . 'assets/admin-script.js',
            [ 'jquery', 'wp-admin-pjax' ],
            WP_ADMIN_UI_VERSION,
            true
        );

        wp_localize_script( 'ga-admin-script', 'ga_ajax', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'ga_ajax_nonce' ),
        ] );

        // ── Modo de Manutenção (Color Picker e Live Preview) ───────────────
        wp_enqueue_style(
            'wpmc-admin-css',
            WP_ADMIN_UI_URL . 'wp-maintenance-contacts/assets/css/admin.css',
            [ 'wp-admin-core' ],
            WP_ADMIN_UI_VERSION
        );

        wp_enqueue_script(
            'wpmc-admin-js',
            WP_ADMIN_UI_URL . 'wp-maintenance-contacts/assets/js/admin.js',
            [ 'jquery', 'wp-admin-pjax' ],
            WP_ADMIN_UI_VERSION,
            true
        );

        wp_localize_script( 'wpmc-admin-js', 'wpmcData', [
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'wpmc_get_ip_nonce' ),
            'serverIp' => function_exists( 'wpmc_get_visitor_ip' ) ? wpmc_get_visitor_ip() : '',
        ] );

        $screen_id = $screen ? $screen->id : '';
        $current_per_page = 20;
        if ( $screen_id ) {
            $option_name = str_replace( '-', '_', $screen_id ) . '_per_page';
            $user_per_page = get_user_meta( get_current_user_id(), $option_name, true );
            if ( ! empty( $user_per_page ) ) {
                $current_per_page = absint( $user_per_page );
            }
        }

        wp_localize_script( 'wp-admin-sidebar', 'wpNotionUI', [
            'adminUrl'   => admin_url(),
            'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'wp_admin_ui' ),
            'siteTitle'  => get_bloginfo( 'name' ),
            'wFull'      => '200',
            'wCollapsed' => '56',
            'screenId'   => $screen_id,
            'perPage'    => $current_per_page,
        ] );


        // ── User Personalization ───────────────────────────────────────────
        self::inject_user_colors();
    }

    /**
     * Injects CSS variables for the sidebar colors based on the current WP Admin Color Scheme.
     *
     * - Fresh (padrão): mantém as cores originais do plugin.
     * - Outros schemes: extrai cores do objeto $_wp_admin_css_colors.
     *
     * Mapeamento:
     *   $colors[0] → --wn-bg / --wn-sidebar-bg
     *   $colors[1] → --wn-hover (fundo de hover, NÃO border)
     *   $colors[2] → --wn-active / --wn-accent
     *   icon_colors → --wn-icon-color
     *   --wn-border é derivado do bg por contraste
     *   --wn-text / --wn-text-hi por contraste com o bg
     */
    private static function inject_user_colors(): void {
        global $_wp_admin_css_colors;

        $user_scheme = get_user_option( 'admin_color' );
        if ( empty( $user_scheme ) ) {
            $user_scheme = 'fresh';
        }

        $preset_schemes = [
            'fresh' => [
                'bg'      => '#1d2327',
                'hover'   => '#2c3338',
                'accent'  => '#2271b1',
                'text'    => '#a7aaad',
                'text_hi' => '#f0f0f1',
            ],
            'light' => [
                'bg'      => '#f6f7f7',
                'hover'   => '#e2e8f0',
                'accent'  => '#04a4cc',
                'text'    => '#50575e',
                'text_hi' => '#1d2327',
            ],
            'blue' => [
                'bg'      => '#096484',
                'hover'   => '#257a9f',
                'accent'  => '#0073aa',
                'text'    => '#e1f0f5',
                'text_hi' => '#ffffff',
            ],
            'coffee' => [
                'bg'      => '#46403c',
                'hover'   => '#59524c',
                'accent'  => '#c7a589',
                'text'    => '#cdcfc2',
                'text_hi' => '#ffffff',
            ],
            'ectoplasm' => [
                'bg'      => '#413256',
                'hover'   => '#523f6d',
                'accent'  => '#a3b745',
                'text'    => '#d0c6dd',
                'text_hi' => '#ffffff',
            ],
            'midnight' => [
                'bg'      => '#26292c',
                'hover'   => '#363b3f',
                'accent'  => '#e14d43',
                'text'    => '#a7aaad',
                'text_hi' => '#ffffff',
            ],
            'ocean' => [
                'bg'      => '#627c83',
                'hover'   => '#738e96',
                'accent'  => '#9ebaa0',
                'text'    => '#d5e0e3',
                'text_hi' => '#ffffff',
            ],
            'sunrise' => [
                'bg'      => '#b43c38',
                'hover'   => '#cf4944',
                'accent'  => '#dd582e',
                'text'    => '#f3d3d1',
                'text_hi' => '#ffffff',
            ],
            'modern' => [
                'bg'      => '#1e1e1e',
                'hover'   => '#2d2d2d',
                'accent'  => '#3858e9',
                'text'    => '#a7aaad',
                'text_hi' => '#f0f0f1',
            ],
        ];

        if ( isset( $preset_schemes[ $user_scheme ] ) ) {
            $preset  = $preset_schemes[ $user_scheme ];
            $bg      = $preset['bg'];
            $hover   = $preset['hover'];
            $accent  = $preset['accent'];
            $text    = $preset['text'];
            $text_hi = $preset['text_hi'];
        } elseif ( ! empty( $_wp_admin_css_colors[ $user_scheme ] ) ) {
            $scheme = $_wp_admin_css_colors[ $user_scheme ];
            $colors = $scheme->colors;
            $icons  = $scheme->icon_colors ?? [];
            $bg     = $colors[3] ?? ( $colors[0] ?? '#1e1e1e' );
            $hover  = $colors[1] ?? self::adjust_brightness( $bg, 12 );
            $accent = $colors[2] ?? '#3858e9';
            $is_dark= self::is_dark( $bg );
            $text   = ! empty( $icons['base'] ) ? $icons['base'] : ( $is_dark ? '#a7aaad' : '#50575e' );
            $text_hi= ! empty( $icons['focus'] ) ? $icons['focus'] : ( $is_dark ? '#f0f0f1' : '#1d2327' );
        } else {
            // Esquema não registado (ex.: 'wn-custom' removido) → usa o esquema padrão.
            $user_scheme = 'fresh';
            $preset      = $preset_schemes['fresh'];
            $bg          = $preset['bg'];
            $hover       = $preset['hover'];
            $accent      = $preset['accent'];
            $text        = $preset['text'];
            $text_hi     = $preset['text_hi'];
        }

        $is_dark_bg = self::is_dark( $bg );

        $css  = ":root {\n";
        $css .= "    --wn-bg: {$bg};\n";
        $css .= "    --wn-sidebar-bg: {$bg};\n";
        $css .= "    --wn-hover: {$hover};\n";
        $css .= "    --wn-active: {$accent};\n";
        $css .= "    --wn-accent: {$accent};\n";
        $css .= "    --wn-border: " . ( $is_dark_bg ? self::hex_to_rgba( '#ffffff', 0.10 ) : self::hex_to_rgba( '#000000', 0.08 ) ) . ";\n";
        $css .= "    --wn-text: {$text};\n";
        $css .= "    --wn-text-hi: {$text_hi};\n";
        $css .= "    --wn-text-sub: " . ( $is_dark_bg ? 'rgba(240, 240, 241, 0.5)' : 'rgba(29, 35, 39, 0.5)' ) . ";\n";
        $css .= "    --wn-label: " . ( $is_dark_bg ? 'rgba(255, 255, 255, 0.4)' : 'rgba(0, 0, 0, 0.4)' ) . ";\n";
        $css .= "    --wn-scroll: " . ( $is_dark_bg ? 'rgba(255, 255, 255, 0.2)' : 'rgba(0, 0, 0, 0.2)' ) . ";\n";
        $css .= "    --wn-text-act: " . ( self::is_dark( $accent ) ? '#ffffff' : '#1d2327' ) . ";\n";
        $css .= "}\n";

        wp_add_inline_style( 'wp-admin-core', $css );
    }


    /**
     * Converte um hex para rgba.
     */
    private static function hex_to_rgba( string $hex, float $alpha = 1 ): string {
        $hex = str_replace( '#', '', $hex );
        if ( strlen( $hex ) === 3 ) {
            $r = hexdec( substr( $hex, 0, 1 ) . substr( $hex, 0, 1 ) );
            $g = hexdec( substr( $hex, 1, 1 ) . substr( $hex, 1, 1 ) );
            $b = hexdec( substr( $hex, 2, 1 ) . substr( $hex, 2, 1 ) );
        } else {
            $r = hexdec( substr( $hex, 0, 2 ) );
            $g = hexdec( substr( $hex, 2, 2 ) );
            $b = hexdec( substr( $hex, 4, 2 ) );
        }
        return "rgba($r, $g, $b, $alpha)";
    }

    /**
     * Retorna true se a cor for considerada escura (brightness < 160).
     */
    private static function is_dark( string $hex ): bool {
        $hex = str_replace( '#', '', $hex );
        if ( strlen( $hex ) === 3 ) {
            $r = hexdec( substr( $hex, 0, 1 ) . substr( $hex, 0, 1 ) );
            $g = hexdec( substr( $hex, 1, 1 ) . substr( $hex, 1, 1 ) );
            $b = hexdec( substr( $hex, 2, 1 ) . substr( $hex, 2, 1 ) );
        } else {
            $r = hexdec( substr( $hex, 0, 2 ) );
            $g = hexdec( substr( $hex, 2, 2 ) );
            $b = hexdec( substr( $hex, 4, 2 ) );
        }
        $brightness = ( ( $r * 299 ) + ( $g * 587 ) + ( $b * 114 ) ) / 1000;
        return $brightness < 160;
    }

    /**
     * Clareia (+) ou escurece (-) uma cor hex em X% mantendo o formato #rrggbb.
     * Ex: adjust_brightness('#1e1e1e', 12) → clareia 12%
     */
    private static function adjust_brightness( string $hex, int $percent ): string {
        $hex = str_replace( '#', '', $hex );
        if ( strlen( $hex ) === 3 ) {
            $r = hexdec( substr( $hex, 0, 1 ) . substr( $hex, 0, 1 ) );
            $g = hexdec( substr( $hex, 1, 1 ) . substr( $hex, 1, 1 ) );
            $b = hexdec( substr( $hex, 2, 1 ) . substr( $hex, 2, 1 ) );
        } else {
            $r = hexdec( substr( $hex, 0, 2 ) );
            $g = hexdec( substr( $hex, 2, 2 ) );
            $b = hexdec( substr( $hex, 4, 2 ) );
        }

        $factor = ( 255 * $percent / 100 );
        if ( $percent >= 0 ) {
            $r = min( 255, $r + $factor );
            $g = min( 255, $g + $factor );
            $b = min( 255, $b + $factor );
        } else {
            $r = max( 0, $r + $factor );
            $g = max( 0, $g + $factor );
            $b = max( 0, $b + $factor );
        }

        return sprintf( '#%02x%02x%02x', (int) $r, (int) $g, (int) $b );
    }
}



