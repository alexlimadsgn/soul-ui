<?php
defined( 'ABSPATH' ) || exit;

class WP_Admin_UI_Options {

    public static function init(): void {
        add_action( 'admin_menu', [ __CLASS__, 'add_submenu' ] );
    }

    public static function add_submenu(): void {
        add_submenu_page(
            'options-general.php',
            'Sidebar Icons',
            'Sidebar Icons',
            'manage_options',
            'wn-sidebar-icons',
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function render_page(): void {
        global $menu;
        
        // Handle saving
        if ( isset( $_POST['wn_save_icons'] ) && check_admin_referer( 'wn_icons_nonce' ) ) {
            $icons = [];
            if ( isset( $_POST['wn_icons'] ) && is_array( $_POST['wn_icons'] ) ) {
                foreach ( $_POST['wn_icons'] as $slug => $svg ) {
                    $slug = sanitize_text_field( $slug );
                    $svg = trim($svg);
                    if ( ! empty( $svg ) ) {
                        // We allow basic SVG tags for non-admin but here it's manage_options
                        // Still, sanitize broadly to avoid breaking the UI
                        $icons[ $slug ] = self::sanitize_svg( $svg );
                    }
                }
            }
            update_option( 'wn_custom_icons', $icons );
            echo '<div class="notice notice-success is-dismissible"><p>Ícones da barra lateral atualizados com sucesso!</p></div>';
        }

        $custom_icons = get_option( 'wn_custom_icons', [] );
        ?>
        <style>
            .wn-icons-table textarea { width: 100%; font-family: monospace; font-size: 11px; padding: 8px; border-radius: 4px; background: #fff; }
            .wn-icons-preview {
                width: 32px;
                height: 32px;
                display: flex;
                align-items: center;
                justify-content: center;
                background: #f0f0f1;
                border-radius: 4px;
                color: #3c434a;
                cursor: pointer;
                transition: background 0.2s, border-color 0.2s;
                border: 1px solid #dcdcde;
            }
            .wn-icons-preview:hover {
                background: #e4e4e6;
                border-color: #8c8f94;
                color: #3858e9;
            }
            .wn-icons-preview svg { width: 20px; height: 20px; }
            .wn-icons-table td { vertical-align: middle !important; }

            /* Modal Picker Styles */
            .wn-picker-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.4);
                backdrop-filter: blur(2px);
                z-index: 99999;
                display: flex;
                align-items: center;
                justify-content: center;
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.25s ease;
            }
            .wn-picker-overlay.wn-active {
                opacity: 1;
                pointer-events: auto;
            }
            .wn-picker-content {
                background: #fff;
                width: 90%;
                max-width: 550px;
                max-height: 80vh;
                border-radius: 12px;
                box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
                border: 1px solid #c3c4c7;
                display: flex;
                flex-direction: column;
                overflow: hidden;
                transform: scale(0.92);
                transition: transform 0.25s ease;
            }
            .wn-picker-overlay.wn-active .wn-picker-content {
                transform: scale(1);
            }
            .wn-picker-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 16px 20px;
                border-bottom: 1px solid #f0f0f1;
            }
            .wn-picker-header h3 {
                margin: 0;
                font-size: 16px;
                font-weight: 600;
                color: #1d2327;
            }
            .wn-picker-close {
                background: none;
                border: none;
                font-size: 24px;
                color: #646970;
                cursor: pointer;
                padding: 0;
                line-height: 1;
                transition: color 0.15s;
            }
            .wn-picker-close:hover {
                color: #d63638;
            }
            .wn-picker-search-container {
                padding: 15px 20px 10px;
            }
            .wn-picker-search {
                width: 100%;
                height: 40px;
                padding: 0 15px;
                border: 1px solid #8c8f94;
                border-radius: 6px;
                font-size: 14px;
                box-sizing: border-box;
                color: #2c3338;
                background: #fff;
                box-shadow: none;
                outline: none;
            }
            .wn-picker-search:focus {
                border-color: #3858e9;
                box-shadow: 0 0 0 2px rgba(56, 88, 233, 0.2);
            }
            .wn-picker-grid {
                flex: 1;
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(76px, 1fr));
                gap: 8px;
                padding: 10px 20px 20px;
                overflow-y: auto;
                background: #f6f7f7;
                max-height: 50vh;
            }
            .wn-picker-item {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                background: #fff;
                border: 1px solid #dcdcde;
                border-radius: 8px;
                padding: 12px 6px;
                cursor: pointer;
                transition: all 0.15s ease-in-out;
            }
            .wn-picker-item:hover {
                border-color: #3858e9;
                background: #f0f4fe;
                transform: translateY(-2px);
                box-shadow: 0 4px 10px rgba(56, 88, 233, 0.1);
            }
            .wn-picker-item i,
            .wn-picker-item svg {
                width: 20px;
                height: 20px;
                color: #2c3338;
                display: block;
            }
            .wn-picker-item:hover i,
            .wn-picker-item:hover svg {
                color: #3858e9;
            }
            .wn-picker-item span {
                font-size: 10px;
                color: #646970;
                margin-top: 6px;
                text-align: center;
                word-break: break-word;
                display: block;
                max-width: 100%;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            .wn-picker-item:hover span {
                color: #3858e9;
            }
            .wn-picker-no-results {
                grid-column: 1 / -1;
                text-align: center;
                padding: 40px 0;
                color: #646970;
                font-size: 14px;
            }
        </style>

        <div class="wrap">
            <h1>Sidebar Icons</h1>
            <p>Personalize os ícones da barra lateral do Notion UI. Você pode colar o código <code>&lt;svg&gt;</code> completo ou apenas o nome do ícone do <a href="https://lucide.dev/icons" target="_blank">Lucide Icons</a> (ex: <code>users</code>, <code>settings</code>, <code>package</code>).</p>
            
            <form method="post" action="">
                <?php wp_nonce_field( 'wn_icons_nonce' ); ?>
                <table class="wp-list-table widefat fixed striped wn-icons-table">
                    <thead>
                        <tr>
                            <th width="150">Menu</th>
                            <th>Código SVG ou Nome Lucide</th>
                            <th width="100">Pré-visualização</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        foreach ( (array) $menu as $item ) : 
                            $raw   = (string) $item[0];
                            // Remove span tags (notification counters and accessibility text)
                            $label = preg_replace( '/<span[^>]*>.*<\/span>/si', '', $raw );
                            $label = wp_strip_all_tags( $label );
                            $label = trim( $label );
                            
                            if ( empty( $label ) ) continue;
                            
                            $slug = $item[2];
                            $svg  = $custom_icons[ $slug ] ?? '';
                        ?>
                            <tr>
                                <td><strong><?php echo esc_html( $label ); ?></strong></td>
                                <td>
                                    <textarea name="wn_icons[<?php echo esc_attr( $slug ); ?>]" 
                                              rows="2" placeholder="ex: users ou <svg ...>"><?php echo esc_textarea( $svg ); ?></textarea>
                                </td>
                                <td>
                                    <div class="wn-icons-preview" title="<?php esc_attr_e( 'Clique para selecionar um ícone', 'admin-ui' ); ?>">
                                        <?php 
                                        if ( ! empty( $svg ) ) {
                                            $svg = trim($svg);
                                            if ( strpos( $svg, '<svg' ) === 0 ) {
                                                echo $svg;
                                            } else {
                                                echo '<i data-lucide="' . esc_attr( $svg ) . '"></i>';
                                            }
                                        } else {
                                            echo '<span style="color:#ccc">---</span>';
                                        }
                                        ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <p class="submit">
                    <input type="submit" name="wn_save_icons" id="submit" class="button button-primary" value="Salvar Ícones Personalizados">
                </p>
            </form>
        </div>

        <!-- HTML Estrutural do Modal Picker -->
        <div class="wn-picker-overlay">
            <div class="wn-picker-content">
                <div class="wn-picker-header">
                    <h3>Escolher Ícone Lucide</h3>
                    <button type="button" class="wn-picker-close">&times;</button>
                </div>
                <div class="wn-picker-search-container">
                    <input type="text" class="wn-picker-search" placeholder="Pesquisar ícones (ex: user, home, lock)...">
                </div>
                <div class="wn-picker-grid">
                    <!-- Gerado dinamicamente via JS -->
                </div>
            </div>
        </div>

        <!-- Script de Interatividade do Modal -->
        <script>
        jQuery(document).ready(function($) {
            var $overlay = $('.wn-picker-overlay');
            var $search = $('.wn-picker-search');
            var $grid = $('.wn-picker-grid');
            var $activeTextarea = null;
            var $activePreview = null;
            
            // Lista de ícones populares para carregamento inicial rápido
            var popularIcons = [
                'home', 'layout-dashboard', 'settings', 'cog', 'user', 'users', 'lock', 'key', 'shield', 'eye', 
                'folder', 'folder-open', 'file', 'file-text', 'image', 'images', 'video', 'music', 'volume-2', 
                'mail', 'send', 'phone', 'map-pin', 'globe', 'link', 'external-link', 'share-2', 
                'shopping-bag', 'shopping-cart', 'package', 'tag', 'credit-card', 'dollar-sign', 
                'calendar', 'clock', 'activity', 'heart', 'star', 'bookmark', 'bell', 'gift', 
                'list', 'grid', 'table', 'database', 'terminal', 'code', 'cpu', 'hard-drive', 
                'plus', 'minus', 'edit', 'edit-3', 'trash', 'trash-2', 'search', 'check', 'x', 
                'alert-circle', 'help-circle', 'info', 'check-circle', 'play', 'pause', 'refresh-cw', 
                'download', 'upload', 'copy', 'clipboard', 'book', 'book-open', 'award', 'briefcase', 
                'compass', 'layers', 'sliders', 'bar-chart', 'bar-chart-2', 'pie-chart', 'trending-up', 
                'zap', 'sun', 'moon', 'cloud', 'wifi', 'power', 'menu', 'more-horizontal'
            ];

            // Lista completa carregada a partir do Lucide
            var allIcons = [];
            if (window.lucide && window.lucide.icons) {
                var rawIcons = Object.keys(window.lucide.icons).map(function(camelKey) {
                    return camelKey
                        .replace(/([a-z0-9])([A-Z])/g, '$1-$2')
                        .replace(/([A-Z])([A-Z][a-z])/g, '$1-$2')
                        .replace(/([a-zA-Z])([0-9])/g, '$1-$2')
                        .toLowerCase();
                });
                // Remove duplicidades (ex: aliases como FileAxis3D vs FileAxis3d) e ordena
                allIcons = Array.from(new Set(rawIcons)).sort();
            }

            function renderIcons(iconsList) {
                $grid.empty();
                if (iconsList.length === 0) {
                    $grid.append('<div class="wn-picker-no-results">Nenhum ícone encontrado.</div>');
                    return;
                }

                var fragment = document.createDocumentFragment();
                iconsList.forEach(function(icon) {
                    var item = document.createElement('div');
                    item.className = 'wn-picker-item';
                    item.setAttribute('data-icon', icon);
                    item.title = icon;
                    
                    var iTag = document.createElement('i');
                    iTag.setAttribute('data-lucide', icon);
                    
                    var spanTag = document.createElement('span');
                    spanTag.textContent = icon;
                    
                    item.appendChild(iTag);
                    item.appendChild(spanTag);
                    fragment.appendChild(item);
                });
                
                $grid[0].appendChild(fragment);
                
                if (window.lucide) {
                    window.lucide.createIcons();
                }
            }

            // Abrir Modal
            $('.wn-icons-preview').on('click', function() {
                $activePreview = $(this);
                $activeTextarea = $(this).closest('tr').find('textarea');
                
                $search.val('');
                $overlay.addClass('wn-active');
                $search.focus();
                
                renderIcons(popularIcons);
            });

            // Campo de Busca
            $search.on('input', function() {
                var query = $(this).val().toLowerCase().trim();
                if (query === '') {
                    renderIcons(popularIcons);
                    return;
                }

                var filtered = allIcons.filter(function(icon) {
                    return icon.indexOf(query) !== -1;
                });
                renderIcons(filtered);
            });

            // Fechar Modal
            function closeModal() {
                $overlay.removeClass('wn-active');
                $activeTextarea = null;
                $activePreview = null;
            }

            $('.wn-picker-close, .wn-picker-overlay').on('click', function(e) {
                if ($(e.target).hasClass('wn-picker-overlay') || $(e.target).hasClass('wn-picker-close')) {
                    closeModal();
                }
            });

            $('.wn-picker-content').on('click', function(e) {
                e.stopPropagation();
            });

            // Selecionar Ícone
            $grid.on('click', '.wn-picker-item', function() {
                var selectedIcon = $(this).attr('data-icon');
                if ($activeTextarea && $activePreview) {
                    $activeTextarea.val(selectedIcon);
                    $activePreview.empty().html('<i data-lucide="' + selectedIcon + '"></i>');
                    if (window.lucide) {
                        window.lucide.createIcons();
                    }
                }
                closeModal();
            });

            // Fechar com ESC
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $overlay.hasClass('wn-active')) {
                    closeModal();
                }
            });
        });
        </script>
        <?php
    }

    private static function sanitize_svg( string $svg ): string {
        // Simple sanitization for SVG code
        $allowed_tags = [
            'svg'    => [ 'viewBox' => 1, 'viewbox' => 1, 'xmlns' => 1, 'fill' => 1, 'stroke' => 1, 'stroke-width' => 1, 'stroke-linecap' => 1, 'stroke-linejoin' => 1, 'width' => 1, 'height' => 1, 'class' => 1, 'style' => 1 ],
            'path'   => [ 'd' => 1, 'fill' => 1, 'stroke' => 1, 'stroke-width' => 1, 'fill-rule' => 1, 'clip-rule' => 1, 'stroke-linecap' => 1, 'stroke-linejoin' => 1, 'class' => 1, 'style' => 1 ],
            'circle' => [ 'cx' => 1, 'cy' => 1, 'r' => 1, 'fill' => 1, 'stroke' => 1, 'stroke-width' => 1, 'class' => 1, 'style' => 1 ],
            'rect'   => [ 'x' => 1, 'y' => 1, 'width' => 1, 'height' => 1, 'rx' => 1, 'ry' => 1, 'fill' => 1, 'stroke' => 1, 'stroke-width' => 1, 'class' => 1, 'style' => 1 ],
            'line'   => [ 'x1' => 1, 'y1' => 1, 'x2' => 1, 'y2' => 1, 'stroke' => 1, 'stroke-width' => 1, 'stroke-linecap' => 1, 'stroke-linejoin' => 1, 'class' => 1, 'style' => 1 ],
            'polyline' => [ 'points' => 1, 'fill' => 1, 'stroke' => 1, 'stroke-width' => 1, 'stroke-linecap' => 1, 'stroke-linejoin' => 1, 'class' => 1, 'style' => 1 ],
            'polygon'  => [ 'points' => 1, 'fill' => 1, 'stroke' => 1, 'stroke-width' => 1, 'stroke-linecap' => 1, 'stroke-linejoin' => 1, 'class' => 1, 'style' => 1 ],
        ];
        return wp_kses( $svg, $allowed_tags );
    }
}


