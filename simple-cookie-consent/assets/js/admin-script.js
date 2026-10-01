jQuery(document).ready(function ($) {
    // ── Campos de Cor Nativos (input[type="color"] + valor hex) ────────────
    // Sincroniza o hex visível quando a cor muda no color picker nativo
    $(document).on('input change', '.scc-native-color', function () {
        $(this).siblings('.scc-hex-value').val($(this).val().toLowerCase());
        updateLivePreview();
    });

    // Sincroniza o color picker quando o hex é editado manualmente
    $(document).on('input', '.scc-hex-value', function () {
        let val = $(this).val().trim().replace(/^#/, '');
        if (!/^[0-9a-fA-F]{6}$/.test(val)) return;
        const $color = $(this).siblings('.scc-native-color');
        $color.val('#' + val.toLowerCase());
        updateLivePreview();
    });

    // "Contraste automático": desativa o campo de cor (não é enviado no form)
    $(document).on('change', '.scc-auto input[type="checkbox"]', function () {
        $(this).closest('.scc-cfield').find('.scc-native-color').prop('disabled', this.checked);
        updateLivePreview();
    });

    // Mover elementos de preview para a raiz do <body> para garantir z-index e posicionamento fixo corretos
    function movePreviewsToBody() {
        $('.scc-admin-preview#scc-cookie-banner, .scc-admin-preview#scc-cookie-modal').appendTo('body');
    }
    movePreviewsToBody();

    // Suporte ao carregamento assíncrono via PJAX
    $(document).on('wp-pjax-loaded', function () {
        movePreviewsToBody();
        // Reinicializa campos e preview se a página do cookie foi carregada
        if ($('.scc-admin-page').length) {
            toggleCustomColorFields();
            updateLivePreview();
        }
    });

    // Validação do formulário
    $('form').on('submit', function () {
        const delay = $('input[name="scc_display_delay"]').val();
        if (delay < 0) {
            alert('O delay não pode ser negativo.');
            return false;
        }
    });

    // Controle dos campos de cor personalizada do modal
    function toggleCustomColorFields() {
        const modalTitleType = $('#scc_modal_title_color_type').val();
        const modalOptionType = $('#scc_modal_option_title_color_type').val();

        // Título do modal
        $('.scc-cfield[data-field="scc_modal_title_custom_color"]').toggle(modalTitleType === 'custom');

        // Títulos das opções
        $('.scc-cfield[data-field="scc_modal_option_custom_color"]').toggle(modalOptionType === 'custom');
    }

    // Event listeners para seletores de tipo de cor
    $('#scc_modal_title_color_type, #scc_modal_option_title_color_type').on('change', function () {
        toggleCustomColorFields();
        updateLivePreview();
    });

    // Preview dinâmico das cores
    // Preview dinâmico do Banner e Modal (Live Preview)
    function updateLivePreview() {
        // --- Cores ---
        const primaryColor = $('input[name="scc_primary_color"]').val() || '#000000';
        const primaryHover = $('input[name="scc_primary_hover_color"]').val() || primaryColor;
        const secondaryColor = $('input[name="scc_secondary_color"]').val() || '#ffffff';
        const secondaryHover = $('input[name="scc_secondary_hover_color"]').val() || '#f3f4f6';
        const bgColor = $('input[name="scc_bg_color"]').val() || '#ffffff';
        const textColor = $('input[name="scc_text_color"]').val() || '#1d2327';
        const modalBg = $('input[name="scc_modal_bg_color"]').val() || '#ffffff';
        
        function getContrastColor(hexcolor) {
            if (!hexcolor) return '#ffffff';
            hexcolor = hexcolor.replace('#', '');
            if (hexcolor.length === 3) hexcolor = hexcolor[0] + hexcolor[0] + hexcolor[1] + hexcolor[1] + hexcolor[2] + hexcolor[2];
            const r = parseInt(hexcolor.substr(0, 2), 16), g = parseInt(hexcolor.substr(2, 2), 16), b = parseInt(hexcolor.substr(4, 2), 16);
            const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
            return luminance > 0.5 ? '#000000' : '#ffffff';
        }

        const customModalText = $('input[name="scc_modal_text_color"]').val();
        const modalText = customModalText || getContrastColor(modalBg);
        
        const modalTitleType = $('#scc_modal_title_color_type').val() || 'primary';
        const modalOptionType = $('#scc_modal_option_title_color_type').val() || 'primary';

        let modalTitleColor = primaryColor;
        if (modalTitleType === 'secondary') modalTitleColor = secondaryColor;
        else if (modalTitleType === 'custom') modalTitleColor = $('input[name="scc_modal_title_custom_color"]').val() || primaryColor;

        let modalOptionColor = primaryColor;
        if (modalOptionType === 'secondary') modalOptionColor = secondaryColor;
        else if (modalOptionType === 'custom') modalOptionColor = $('input[name="scc_modal_option_custom_color"]').val() || primaryColor;

        // --- Textos com Fallbacks ---
        const bannerTitle = $('input[name="scc_banner_title"]').val() || 'Valorizamos a sua privacidade';
        const bannerMsg = $('textarea[name="scc_banner_message"]').val() || 'Utilizamos cookies para melhorar a sua experiência...';
        const btnConfigText = $('input[name="scc_configure_text"]').val() || 'Configurar';
        const btnAcceptText = $('input[name="scc_accept_text"]').val() || 'Aceitar Todos';
        
        const modalTitle = $('input[name="scc_modal_title"]').val() || 'Preferências de Cookies';
        const optNecessaryTitle = $('input[name="scc_necessary_title"]').val() || 'Necessários';
        const optNecessaryDesc = $('textarea[name="scc_necessary_desc"]').val() || 'Estes cookies são essenciais para o funcionamento...';
        const btnSaveText = $('input[name="scc_save_text"]').val() || 'Guardar';
        const btnAcceptAllModal = $('input[name="scc_accept_all_text"]').val() || 'Aceitar Todos';

        const optAnalyticsTitle = $('input[name="scc_analytics_title"]').val() || 'Analíticos';
        const optAnalyticsDesc = $('textarea[name="scc_analytics_desc"]').val() || 'Ajudam-nos a entender...';
        const optMarketingTitle = $('input[name="scc_marketing_title"]').val() || 'Marketing';
        const optMarketingDesc = $('textarea[name="scc_marketing_desc"]').val() || 'Utilizados para apresentar...';

        const borderRadius = $('input[name="scc_border_radius"]').val() || 12;
        const btnRadius = $('input[name="scc_btn_radius"]').val() || 8;
        const modalRadius = $('input[name="scc_modal_radius"]').val() || 16;

        // --- Bordas ---
        const bannerBorderEnabled = $('input[name="scc_enable_banner_border"]').is(':checked');
        const bannerBorderColor = $('input[name="scc_banner_border_color"]').val() || '#ffffff33';
        const bannerBorder = bannerBorderEnabled ? `1px solid ${bannerBorderColor}` : 'none';

        const btnBorderEnabled = $('input[name="scc_enable_btn_border"]').is(':checked');
        const btnBorderColor = $('input[name="scc_btn_border_color"]').val() || '#ffffff33';
        const btnBorder = btnBorderEnabled ? `1px solid ${btnBorderColor}` : 'none';

        // --- Atualizar Variáveis CSS no Root do Admin ---
        const root = document.documentElement;
        
        // Variáveis padrão (--scc-)
        root.style.setProperty('--scc-primary-color', primaryColor);
        root.style.setProperty('--scc-primary-hover', primaryHover);
        root.style.setProperty('--scc-secondary-color', secondaryColor);
        root.style.setProperty('--scc-secondary-hover', secondaryHover);
        root.style.setProperty('--scc-bg-color', bgColor);
        root.style.setProperty('--scc-text-color', textColor);
        root.style.setProperty('--scc-modal-bg', modalBg);
        root.style.setProperty('--scc-modal-text', modalText);
        root.style.setProperty('--scc-border-radius', borderRadius + 'px');
        root.style.setProperty('--scc-btn-radius', btnRadius + 'px');
        root.style.setProperty('--scc-modal-radius', modalRadius + 'px');
        root.style.setProperty('--scc-modal-title-color', modalTitleColor);
        root.style.setProperty('--scc-option-title-color', modalOptionColor);

        // Cores de contraste para os botões do preview
        const customTextP = $('input[name="scc_btn_primary_text_color"]');
        const textOnPrimary = (customTextP.length && !customTextP.prop('disabled')) ? customTextP.val() : getContrastColor(primaryColor);
        const customTextS = $('input[name="scc_btn_secondary_text_color"]');
        const textOnSecondary = (customTextS.length && !customTextS.prop('disabled')) ? customTextS.val() : getContrastColor(secondaryColor);

        root.style.setProperty('--scc-text-on-primary', textOnPrimary);
        root.style.setProperty('--scc-text-on-secondary', textOnSecondary);
        root.style.setProperty('--scc-banner-border', bannerBorder);
        root.style.setProperty('--scc-btn-border', btnBorder);

        // --- Atualizar Conteúdo Textual do Banner e Modal ---
        $('#scc-banner-title-text').text(bannerTitle);
        $('#scc-banner-message-text').html(bannerMsg.replace(/\n/g, '<br>'));
        $('#scc-configure').text(btnConfigText);
        $('#scc-accept-all').text(btnAcceptText);
        $('#scc-modal-title-text').text(modalTitle);
        $('#scc-necessary-title-text').text(optNecessaryTitle);
        $('#scc-necessary-desc-text').text(optNecessaryDesc);
        
        $('#scc-analytics-title-text').text(optAnalyticsTitle);
        $('#scc-analytics-desc-text').text(optAnalyticsDesc);
        $('#scc-marketing-title-text').text(optMarketingTitle);
        $('#scc-marketing-desc-text').text(optMarketingDesc);

        $('#scc-save-preferences').text(btnSaveText);
        $('#scc-accept-all-modal').text(btnAcceptAllModal);

        // --- Mostrar/Ocultar Opções Conforme Checkbox ---
        const enableAnalytics = $('input[name="scc_enable_analytics"]').is(':checked');
        const enableMarketing = $('input[name="scc_enable_marketing"]').is(':checked');

        if (enableAnalytics) {
            $('.scc-analytics-option').removeClass('scc-option-hidden');
        } else {
            $('.scc-analytics-option').addClass('scc-option-hidden');
        }

        if (enableMarketing) {
            $('.scc-marketing-option').removeClass('scc-option-hidden');
        } else {
            $('.scc-marketing-option').addClass('scc-option-hidden');
        }

        // --- Mostrar/Ocultar Link de Privacidade Conforme Seleção ---
        const privacyPage = $('select[name="scc_privacy_page"]').val();
        if (privacyPage) {
            $('#scc-banner-privacy-link').removeClass('scc-option-hidden').show();
        } else {
            $('#scc-banner-privacy-link').addClass('scc-option-hidden').hide();
        }
    }

    // Ouvintes para todos os campos relevantes
    $('input, textarea, select').on('input change', function () {
        if ($(this).hasClass('scc-hex-value') || $(this).hasClass('scc-native-color')) {
            return;
        }
        updateLivePreview();
    });

    // Quando cor primária muda, atualizar hover se necessário
    $('input[name="scc_primary_color"]').on('change', function () {
        const primaryHover = $('input[name="scc_primary_hover_color"]').val();
        if (!primaryHover) {
            const darker = adjustColor($(this).val() || '#0f3460', -40);
            $('input[name="scc_primary_hover_color"]').val(darker).trigger('change');
        }
        updateLivePreview();
    });

    // Quando cor secundária muda, atualizar hover se necessário
    $('input[name="scc_secondary_color"]').on('change', function () {
        const secondaryHover = $('input[name="scc_secondary_hover_color"]').val();
        if (!secondaryHover) {
            const darker = adjustColor($(this).val() || '#ffffff', -10);
            $('input[name="scc_secondary_hover_color"]').val(darker).trigger('change');
        }
        updateLivePreview();
    });

    // Função para ajustar cor (clarear/escurecer)
    function adjustColor(hex, lum) {
        hex = String(hex).replace(/[^0-9a-f]/gi, '');
        if (hex.length < 6) {
            hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
        }
        lum = lum || 0;

        let rgb = "#", c, i;
        for (i = 0; i < 3; i++) {
            c = parseInt(hex.substr(i * 2, 2), 16);
            c = Math.round(Math.min(Math.max(0, c + (c * lum) / 100), 255)).toString(16);
            rgb += ("00" + c).substr(c.length);
        }

        return rgb;
    }

    // --- Lógica de Simulação Interativa (Preview) ---
    // Gatilho do olho ou botão da sidebar para abrir a simulação
    $(document).on('click', '#scc-admin-preview-trigger, #scc-sidebar-preview-btn', function(e) {
        e.preventDefault();
        
        // Atualiza as variáveis CSS e textos antes de mostrar
        updateLivePreview();

        // Aplicar a posição do banner dinamicamente para a simulação
        const position = $('select[name="scc_banner_position"]').val() || 'bottom';
        $('.scc-admin-preview#scc-cookie-banner')
            .removeClass('top bottom')
            .addClass(position)
            .fadeIn();
    });

    // Atualizar indicadores da sidebar de publicação
    $(document).on('change', 'select[name="scc_banner_position"]', function() {
        const pos = $(this).val();
        $('#scc-sidebar-position-val').text(pos === 'top' ? 'Superior' : 'Inferior');
    });

    $(document).on('change', 'input[name="scc_enable_banner"]', function() {
        const active = $(this).is(':checked');
        const $badge = $('.scc-status-badge');
        if (active) {
            $badge.removeClass('inactive').addClass('active').html('<span class="scc-status-dot"></span> Ativo');
        } else {
            $badge.removeClass('active').addClass('inactive').html('<span class="scc-status-dot"></span> Inativo');
        }
    });

    // Configurar (abrir modal no preview)
    $(document).on('click', '.scc-admin-preview #scc-configure', function(e) {
        e.preventDefault();
        $('.scc-admin-preview#scc-cookie-modal').addClass('active');
        $('body').css('overflow', 'hidden');
    });

    // Fechar Banner (botão X) na simulação
    $(document).on('click', '.scc-admin-preview#scc-close-banner, .scc-admin-preview #scc-close-banner', function(e) {
        e.preventDefault();
        $('.scc-admin-preview#scc-cookie-banner').fadeOut();
    });

    // Aceitar Todos no Banner na simulação
    $(document).on('click', '.scc-admin-preview #scc-accept-all', function(e) {
        e.preventDefault();
        $('.scc-admin-preview#scc-cookie-banner').fadeOut();
    });

    // Fechar Modal (botão X) na simulação
    $(document).on('click', '.scc-admin-preview .scc-close', function(e) {
        e.preventDefault();
        $('.scc-admin-preview#scc-cookie-modal').removeClass('active');
        $('body').css('overflow', '');
    });

    // Guardar Preferências no modal na simulação
    $(document).on('click', '.scc-admin-preview #scc-save-preferences', function(e) {
        e.preventDefault();
        $('.scc-admin-preview#scc-cookie-modal').removeClass('active');
        $('.scc-admin-preview#scc-cookie-banner').fadeOut();
        $('body').css('overflow', '');
    });

    // Aceitar Todos no modal na simulação
    $(document).on('click', '.scc-admin-preview #scc-accept-all-modal', function(e) {
        e.preventDefault();
        $('.scc-admin-preview#scc-cookie-modal').removeClass('active');
        $('.scc-admin-preview#scc-cookie-banner').fadeOut();
        $('body').css('overflow', '');
    });

    // Fechar modal ao clicar fora da área de conteúdo na simulação
    $(document).on('click', '.scc-admin-preview#scc-cookie-modal', function(e) {
        if ($(e.target).is('.scc-admin-preview#scc-cookie-modal')) {
            $(this).removeClass('active');
            $('body').css('overflow', '');
        }
    });

    // Inicializar campos de cor personalizada e preview
    toggleCustomColorFields();
    updateLivePreview();
});