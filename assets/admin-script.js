jQuery(document).ready(function($) {
    
    function initAccessPage() {
        var $form = $('#ga-menu-access-form');
        if (!$form.length) return;

        updateSelectedCount();
    }

    function updateGroupStyle($group) {
        var hasSelections = $group.find('input[type="checkbox"]:checked').length > 0;
        if (hasSelections) {
            $group.addClass('ga-group--selected');
        } else {
            $group.removeClass('ga-group--selected');
        }
    }

    function updateSelectedCount() {
        var $form = $('#ga-menu-access-form');
        var $countBadge = $('#ga-count-badge');
        if (!$form.length) return;

        var count = $form.find('input[name="menus[]"]:checked').length;
        var text = count === 1 ? '1 selecionado' : count + ' selecionados';
        $countBadge.text(text);
        
        $('.ga-group').each(function() {
            updateGroupStyle($(this));
        });
    }

    // --- Inicialização Automática (Load e PJAX) ---
    initAccessPage();

    $(document).on('wp-pjax-loaded', function() {
        initAccessPage();
    });

    // --- Seleção de Usuário (Delegado) ---
    $(document).on('click', '#ga-load-menu-access', function() {
        var userId = $('#ga-user-select').val();
        if (userId) {
            window.location.href = 'admin.php?page=gerir-acessos&user_id=' + userId;
        } else {
            alert('Por favor, selecione um usuário.');
        }
    });

    // Permitir submeter seletor com Enter (Delegado)
    $(document).on('keypress', '#ga-user-select', function(e) {
        if (e.which === 13) {
            $('#ga-load-menu-access').click();
        }
    });

    // --- Controle de Grupos (Expandir/Recolher - Delegado) ---
    $(document).on('click', '.ga-toggle-btn', function(e) {
        e.stopPropagation();
        var $group = $(this).closest('.ga-group');
        $group.toggleClass('ga-group--open');
    });

    $(document).on('click', '.ga-group-header', function(e) {
        if (!$(e.target).is('input') && !$(e.target).is('.ga-parent-label') && !$(e.target).is('.ga-toggle-btn')) {
            $(this).find('.ga-toggle-btn').click();
        }
    });

    $(document).on('click', '#ga-expand-all', function() {
        $('.ga-group').addClass('ga-group--open');
    });

    $(document).on('click', '#ga-collapse-all', function() {
        $('.ga-group').removeClass('ga-group--open');
    });

    // --- Lógica de Seleção (Ancestrais/Descendentes - Delegados) ---
    $(document).on('change', '.ga-parent-check', function() {
        var isChecked = $(this).is(':checked');
        var $group = $(this).closest('.ga-group');
        
        $group.find('.ga-child-check').prop('checked', isChecked);
        
        updateGroupStyle($group);
        updateSelectedCount();
    });

    $(document).on('change', '.ga-child-check', function() {
        var $group = $(this).closest('.ga-group');
        var $parentCheck = $group.find('.ga-parent-check');
        
        if ($(this).is(':checked')) {
            $parentCheck.prop('checked', true);
        }
        
        updateGroupStyle($group);
        updateSelectedCount();
    });

    $(document).on('click', '#ga-select-all', function() {
        var $form = $('#ga-menu-access-form');
        $form.find('input[type="checkbox"]').prop('checked', true);
        $('.ga-group').each(function() {
            updateGroupStyle($(this));
        });
        updateSelectedCount();
    });

    $(document).on('click', '#ga-deselect-all', function() {
        var $form = $('#ga-menu-access-form');
        $form.find('input[type="checkbox"]').prop('checked', false);
        $('.ga-group').each(function() {
            updateGroupStyle($(this));
        });
        updateSelectedCount();
    });

    // --- Salvar Dados via AJAX (Delegado) ---
    $(document).on('submit', '#ga-menu-access-form', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $messageBox = $('#ga-message');
        var userId = $form.find('input[name="user_id"]').val();
        var selectedMenus = $form.find('input[name="menus[]"]:checked').map(function() {
            return $(this).val();
        }).get();
        
        $form.addClass('ga-loading');
        $messageBox.removeClass('success error').hide();

        var ajaxUrl = (typeof ga_ajax !== 'undefined' && ga_ajax.ajax_url) ? ga_ajax.ajax_url : ((typeof wpNotionUI !== 'undefined') ? wpNotionUI.ajaxUrl : 'admin-ajax.php');
        var nonce = (typeof ga_ajax !== 'undefined' && ga_ajax.nonce) ? ga_ajax.nonce : '';
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'save_user_menu_access',
                user_id: userId,
                menus: selectedMenus,
                nonce: nonce
            },
            success: function(response) {
                $form.removeClass('ga-loading');
                
                if (response.success) {
                    $messageBox.addClass('success').html(response.data.message).fadeIn();
                    setTimeout(function() {
                        $messageBox.fadeOut();
                    }, 4000);
                } else {
                    $messageBox.addClass('error').html('Erro: ' + (response.data.message || 'Não foi possível salvar.')).show();
                }
            },
            error: function() {
                $form.removeClass('ga-loading');
                $messageBox.addClass('error').html('Erro de conexão com o servidor.').show();
            }
        });
    });

    // --- Lógica de Seleção de Checkboxes na Tabela de Listagem (Suporte PJAX) ---
    // Delegado no document para garantir funcionamento mesmo após navegação assíncrona (PJAX)
    $(document).on('change', '.wp-list-table thead .column-cb input[type="checkbox"], .wp-list-table tfoot .column-cb input[type="checkbox"]', function() {
        var isChecked = $(this).prop('checked');
        var $table = $(this).closest('.wp-list-table');
        
        // Marca/desmarca todos os checkboxes no corpo da tabela
        $table.find('tbody .column-cb input[type="checkbox"], tbody .check-column input[type="checkbox"]').prop('checked', isChecked).trigger('change');
        
        // Sincroniza o outro checkbox de "selecionar todos" (cabeçalho/rodapé)
        $table.find('thead .column-cb input[type="checkbox"], tfoot .column-cb input[type="checkbox"]').not(this).prop('checked', isChecked);
    });

    $(document).on('change', '.wp-list-table tbody .column-cb input[type="checkbox"], .wp-list-table tbody .check-column input[type="checkbox"]', function() {
        var $table = $(this).closest('.wp-list-table');
        var $allCheckboxes = $table.find('thead .column-cb input[type="checkbox"], tfoot .column-cb input[type="checkbox"]');
        var $rowCheckboxes = $table.find('tbody .column-cb input[type="checkbox"], tbody .check-column input[type="checkbox"]');
        
        var allChecked = $rowCheckboxes.length > 0 && $rowCheckboxes.length === $rowCheckboxes.filter(':checked').length;
        $allCheckboxes.prop('checked', allChecked);
    });

});