/**
 * Soul UI — Thumb dos Temas
 * Adiciona o botão "Thumb" nos cards de Aparência → Temas.
 */
(function ($) {
    'use strict';

    var D = window.wnThemeThumb;
    if (!D) return;
    var T = D.i18n;

    var ICON = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>';

    var $input = $('<input type="file" accept="image/png,image/jpeg,image/webp" hidden>').appendTo(document.body);
    var target = null;

    function toast(msg, isError) {
        var $t = $('<div class="wn-tt-toast' + (isError ? ' is-error' : '') + '"></div>').text(msg).appendTo(document.body);
        setTimeout(function () { $t.addClass('is-in'); }, 10);
        setTimeout(function () { $t.removeClass('is-in'); setTimeout(function () { $t.remove(); }, 250); }, 2600);
    }

    // Os cards são renderizados pelo Backbone e re-renderizados ao buscar/filtrar.
    function addButtons() {
        $('.themes .theme[data-slug]').each(function () {
            var $shot = $(this).find('.theme-screenshot');
            if (!$shot.length || $shot.find('.wn-tt-btn').length) return;
            $('<button type="button" class="wn-tt-btn"></button>')
                .attr('title', T.title)
                .html(ICON + '<span>' + T.button + '</span>')
                .appendTo($shot);
        });
    }

    $(document).on('click', '.wn-tt-btn', function (e) {
        // Impede que o clique abra o modal de detalhes do tema.
        e.preventDefault();
        e.stopPropagation();
        target = $(this).closest('.theme');
        $input.val('').trigger('click');
    });

    $input.on('change', function () {
        var file = this.files && this.files[0];
        if (!file || !target) return;

        if (['image/png', 'image/jpeg', 'image/webp'].indexOf(file.type) === -1) {
            toast(T.badType, true);
            return;
        }
        if (file.size > D.maxSize) {
            toast(T.tooBig, true);
            return;
        }

        var $theme = target;
        var $btn = $theme.find('.wn-tt-btn').addClass('is-loading').prop('disabled', true);

        var data = new FormData();
        data.append('action', 'wn_theme_thumb_upload');
        data.append('nonce', D.nonce);
        data.append('theme', $theme.data('slug'));
        data.append('file', file);

        $.ajax({ url: D.ajaxUrl, method: 'POST', data: data, processData: false, contentType: false })
            .done(function (res) {
                if (!res || !res.success) {
                    toast((res && res.data && res.data.message) || T.error, true);
                    return;
                }
                var $shot = $theme.find('.theme-screenshot');
                $shot.removeClass('blank');
                var $img = $shot.find('img');
                if (!$img.length) $img = $('<img alt="">').prependTo($shot);
                $img.attr('src', res.data.url);
                toast(T.success);
            })
            .fail(function (xhr) {
                var msg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message;
                toast(msg || T.error, true);
            })
            .always(function () {
                $btn.removeClass('is-loading').prop('disabled', false);
            });
    });

    $(addButtons);
    var list = document.querySelector('.themes');
    if (list && window.MutationObserver) {
        new MutationObserver(addButtons).observe(list, { childList: true, subtree: true });
    }
})(jQuery);
