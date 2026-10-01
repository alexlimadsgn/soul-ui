<?php
defined( 'ABSPATH' ) || exit;

class WP_Admin_UI_Adminbar {

    public static function init(): void {
        add_action( 'admin_body_open', [ __CLASS__, 'render' ], 10 );
        add_action( 'in_admin_header', [ __CLASS__, 'render' ], 10 );
        add_filter( 'show_admin_bar', '__return_false' );
        add_action( 'wp_ajax_wn_set_admin_color', [ __CLASS__, 'ajax_set_admin_color' ] );
        add_action( 'wp_ajax_wn_set_posts_per_page', [ __CLASS__, 'ajax_set_posts_per_page' ] );
    }

    public static function hide_native_bar(): void {
        ?>
        <style id="wn-hide-adminbar">
            body.wn-active #wpadminbar { display: none !important; }
            body.wn-active.admin-bar   { padding-top: 0 !important; }
        </style>
        <?php
    }

    public static function ajax_set_posts_per_page(): void {
        check_ajax_referer( 'wp_admin_ui', 'nonce' );

        if ( ! current_user_can( 'read' ) ) {
            wp_send_json_error( [ 'message' => 'Sem permissão.' ] );
        }

        $per_page = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 20;
        if ( $per_page < 1 ) {
            $per_page = 20;
        }

        $screen_id = isset( $_POST['screen_id'] ) ? sanitize_key( $_POST['screen_id'] ) : '';
        $user_id   = get_current_user_id();

        if ( ! empty( $screen_id ) ) {
            $option_name = str_replace( '-', '_', $screen_id ) . '_per_page';
            update_user_meta( $user_id, $option_name, $per_page );
        }

        // Também salva como padrão para telas comuns caso não especificado
        update_user_meta( $user_id, 'edit_post_per_page', $per_page );
        update_user_meta( $user_id, 'edit_page_per_page', $per_page );

        wp_send_json_success( [
            'per_page' => $per_page,
            'message'  => 'Quantidade de itens atualizada.',
        ] );
    }

    public static function ajax_set_admin_color(): void {
        check_ajax_referer( 'wp_admin_ui', 'nonce' );

        if ( ! current_user_can( 'read' ) ) {
            wp_send_json_error( [ 'message' => 'Sem permissão.' ] );
        }

        $scheme = isset( $_POST['scheme'] ) ? sanitize_key( $_POST['scheme'] ) : '';
        if ( empty( $scheme ) ) {
            wp_send_json_error( [ 'message' => 'Esquema inválido.' ] );
        }

        $user_id = get_current_user_id();
        update_user_option( $user_id, 'admin_color', $scheme, true );
        update_user_meta( $user_id, 'admin_color', $scheme );

        wp_send_json_success( [
            'scheme'  => $scheme,
            'message' => 'Tema atualizado com sucesso.',
        ] );

    }

    public static function render(): void {
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

        $page_title = self::page_title( $screen );
        $user       = wp_get_current_user();
        $user_scheme= get_user_option( 'admin_color' );
        if ( empty( $user_scheme ) ) {
            $user_scheme = 'fresh';
        }

        $theme_schemes = [
            'fresh'     => [ 'name' => 'WordPress (Padrão)', 'color' => '#2271B1' ],
            'modern'    => [ 'name' => 'Moderno',            'color' => '#3858E9' ],
            'blue'      => [ 'name' => 'Azul',               'color' => '#52ACCC' ],
            'coffee'    => [ 'name' => 'Café',               'color' => '#C7A589' ],
            'ectoplasm' => [ 'name' => 'Ectoplasma',         'color' => '#A3B745' ],
            'midnight'  => [ 'name' => 'Meia-noite',         'color' => '#E14D43' ],
            'ocean'     => [ 'name' => 'Oceano',             'color' => '#9EBAA0' ],
            'sunrise'   => [ 'name' => 'Nascer do Sol',      'color' => '#DD582E' ],
            'light'     => [ 'name' => 'Verde / Fresh',      'color' => '#00A32A' ],
        ];
        ?>
        <div id="wn-adminbar" role="banner">

            <!-- Hamburger / collapse mirror -->
            <button class="wn-bar-toggle" id="wn-bar-toggle" aria-label="Alternar barra lateral">
                <?php echo self::icon_menu(); ?>
            </button>

            <!-- Left links (Clean View Site Button) -->
            <div class="wn-bar-left">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="wn-bar-view-site" target="_blank" title="Visualizar site">
                    <?php echo self::icon_home(); ?>
                    <span><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
                    <?php echo self::icon_external_link_small(); ?>
                </a>

                <?php if ( get_option( 'wpmc_active' ) ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'options-general.php?page=wp-maintenance-contacts' ) ); ?>" class="wn-maintenance-badge" title="Modo de Manutenção Ativo — Clique para gerenciar">
                        <span class="wn-maintenance-dot"></span>
                        <span>Manutenção Ativa</span>
                    </a>
                <?php endif; ?>
            </div>

            <!-- Page title badge -->
            <div class="wn-bar-title">
                <span class="wn-bar-badge-page"><?php echo esc_html( $page_title ); ?></span>
            </div>

            <!-- Right actions -->
            <div class="wn-bar-right">
                <!-- Inline search / Ctrl K trigger -->
                <div class="wn-bar-search" id="wn-search-trigger-pill">
                    <?php echo self::icon_search(); ?>
                    <span class="wn-bar-search-kbd">Ctrl K</span>
                </div>

                <!-- Color Theme Selector Dropdown -->
                <div class="wn-bar-theme-group" data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_admin_ui' ) ); ?>">
                    <button class="wn-bar-action wn-theme-trigger" aria-haspopup="true" aria-expanded="false" title="Cor do Tema">
                        <?php echo self::icon_palette(); ?>
                    </button>
                    <div class="wn-bar-theme-dropdown" role="menu">
                        <div class="wn-dropdown-theme-header">
                            <?php echo self::icon_palette(); ?>
                            <span class="wn-dropdown-theme-title">Cor do Tema</span>
                        </div>
                        <div class="wn-theme-palette-grid">
                            <?php foreach ( $theme_schemes as $scheme_key => $scheme_info ) : 
                                $is_active = ( $user_scheme === $scheme_key );
                            ?>
                                <button type="button" class="wn-theme-palette-btn <?php echo $is_active ? 'is-active' : ''; ?>" data-scheme="<?php echo esc_attr( $scheme_key ); ?>">
                                    <span class="wn-palette-color-dot" style="background-color: <?php echo esc_attr( $scheme_info['color'] ); ?>;">
                                        <?php if ( $is_active ) : ?>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                        <?php endif; ?>
                                    </span>
                                    <span class="wn-palette-label"><?php echo esc_html( $scheme_info['name'] ); ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>


                <!-- User -->

                <?php
                $role_names = [
                    'administrator' => 'Administrador',
                    'editor'        => 'Editor',
                    'author'        => 'Autor',
                    'contributor'   => 'Colaborador',
                    'subscriber'    => 'Assinante',
                ];
                $user_role = !empty( $user->roles ) ? ( $role_names[ $user->roles[0] ] ?? ucfirst( $user->roles[0] ) ) : 'Usuário';
                ?>
                <div class="wn-bar-user-group">
                    <button class="wn-bar-action wn-user-trigger" aria-haspopup="true" aria-expanded="false">
                        <div class="wn-bar-avatar">
                            <?php echo self::render_avatar( $user, 28 ); ?>
                        </div>
                        <span class="wn-bar-user-greeting"><?php echo esc_html( 'Olá, ' . strtolower( $user->display_name ) ); ?></span>
                        <svg class="wn-user-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div class="wn-bar-user-dropdown" role="menu">
                        <!-- Perfil Header Detalhado -->
                        <div class="wn-dropdown-profile-header">
                            <div class="wn-dropdown-profile-avatar">
                                <?php echo self::render_avatar( $user, 40 ); ?>
                            </div>
                            <div class="wn-dropdown-profile-info">
                                <span class="wn-dropdown-display-name"><?php echo esc_html( $user->first_name && $user->last_name ? $user->first_name . ' ' . $user->last_name : $user->display_name ); ?></span>
                                <span class="wn-dropdown-user-email"><?php echo esc_html( $user->user_email ); ?></span>
                                <span class="wn-dropdown-user-role"><?php echo esc_html( $user_role ); ?></span>
                            </div>
                        </div>
                        
                        <?php 
                        $show_users    = self::has_menu_access( 'users.php' );
                        $show_settings = self::has_menu_access( 'options-general.php' );
                        ?>

                        <div class="wn-dropdown-divider"></div>

                        <!-- Itens do Menu -->
                        <?php if ( $show_users ) : ?>
                            <a href="<?php echo esc_url( admin_url( 'profile.php' ) ); ?>" role="menuitem">
                                <?php echo self::icon_user_menu(); ?>
                                Perfil e Utilizadores
                            </a>
                        <?php endif; ?>
                        
                        <?php if ( $show_settings ) : ?>
                            <a href="<?php echo esc_url( admin_url( 'options-general.php' ) ); ?>" role="menuitem">
                                <?php echo self::icon_settings(); ?>
                                Configurações do Site
                            </a>
                        <?php endif; ?>
                        
                        <div class="wn-dropdown-divider"></div>

                        <!-- Atalho para o Site e Logout -->
                        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" role="menuitem" class="wn-dropdown-view-site">
                            <?php echo self::icon_external_link(); ?>
                            Ver Site
                        </a>
                        <a href="<?php echo esc_url( wp_logout_url() ); ?>" role="menuitem" class="wn-logout">
                            <?php echo self::icon_logout(); ?>
                            Sair
                        </a>
                    </div>
                </div>
            </div>

        </div>
        <?php

    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private static function has_menu_access( string $slug ): bool {
        global $menu;
        if ( ! is_array( $menu ) ) {
            return false;
        }
        foreach ( $menu as $item ) {
            if ( isset( $item[2] ) && $item[2] === $slug ) {
                return true;
            }
        }
        return false;
    }

    private static function page_title( ?WP_Screen $screen ): string {
        if ( ! $screen ) return get_admin_page_title();

        if ( in_array( $screen->base, [ 'post', 'page' ], true ) ) {
            $post = get_post();
            if ( $post && $post->post_title ) return $post->post_title;
            return 'Novo ' . ( $screen->post_type ?: 'post' );
        }

        return get_admin_page_title() ?: ucfirst( str_replace( '-', ' ', $screen->base ) );
    }

    public static function render_avatar( WP_User $user, int $size = 28 ): string {
        $name = trim( $user->first_name . ' ' . $user->last_name );
        if ( empty( $name ) ) {
            $name = $user->display_name ?: $user->user_login;
        }

        $words = preg_split( '/\s+/', trim( $name ) );
        if ( count( $words ) >= 2 ) {
            $initials = mb_substr( $words[0], 0, 1 ) . mb_substr( $words[ count( $words ) - 1 ], 0, 1 );
        } else {
            $initials = mb_substr( $name, 0, 2 );
        }
        $initials = mb_strtoupper( $initials );

        $preset_index = abs( crc32( (string) $user->ID . $user->user_email ) ) % 8;
        $preset_class = 'wn-avatar-preset-' . $preset_index;

        $avatar_img = get_avatar( $user->ID, $size, '404', esc_attr( $name ), [
            'class' => 'wn-avatar-img',
            'extra_attr' => 'onerror="this.style.display=\'none\';"'
        ] );

        $size_class = $size >= 36 ? 'wn-avatar-lg' : 'wn-avatar-sm';

        $html  = '<div class="wn-avatar-wrap ' . esc_attr( $size_class ) . '">';
        if ( $avatar_img ) {
            $html .= $avatar_img;
        }
        $html .= '<span class="wn-avatar-initials ' . esc_attr( $preset_class ) . '">' . esc_html( $initials ) . '</span>';
        $html .= '</div>';

        return $html;
    }

    // ─── Icons ───────────────────────────────────────────────────────────────

    private static function icon_menu(): string {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/></svg>';
    }
    private static function icon_home(): string {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>';
    }
    private static function icon_search(): string {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>';
    }
    private static function icon_palette(): string {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/></svg>';
    }
    private static function icon_logout(): string {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>';
    }
    private static function icon_user_menu(): string {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';
    }
    private static function icon_settings(): string {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>';
    }
    private static function icon_external_link(): string {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14 21 3"/></svg>';
    }
    private static function icon_external_link_small(): string {
        return '<svg class="wn-icon-external" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.5;"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14 21 3"/></svg>';
    }
}



