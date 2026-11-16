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
    var targetSlug = null;

    function toast(msg, isError) {
        var $t = $('<div class="wn-tt-toast' + (isError ? ' is-error' : '') + '"></div>').text(msg).appendTo(document.body);
        setTimeout(function () { $t.addClass('is-in'); }, 10);
        setTimeout(function () { $t.removeClass('is-in'); setTimeout(function () { $t.remove(); }, 250); }, 2600);
    }

    function button(slug) {
        return $('<button type="button" class="wn-tt-btn"></button>')
            .attr({ title: T.title, 'data-slug': slug })
            .html(ICON + '<span>' + T.button + '</span>');
    }

    // Slug do tema aberto no modal de detalhes (a URL fica themes.php?theme=slug).
    function overlaySlug() {
        var $active = $('.themes .theme.active, .themes .theme:focus').first();
        return new URLSearchParams(window.location.search).get('theme') || $active.data('slug') || '';
    }

    // Cards e modal são renderizados pelo Backbone e re-renderizados ao buscar/filtrar/navegar.
    function addButtons() {
        $('.themes .theme[data-slug]').each(function () {
            var $shot = $(this).find('.theme-screenshot');
            if (!$shot.length || $shot.find('.wn-tt-btn').length) return;
            button($(this).data('slug')).appendTo($shot);
        });

        var $modal = $('.theme-overlay .theme-screenshots');
        var slug = overlaySlug();
        if ($modal.length && slug) {
            var $btn = $modal.find('.wn-tt-btn');
            if (!$btn.length) button(slug).appendTo($modal);
            else if ($btn.attr('data-slug') !== slug) $btn.attr('data-slug', slug);
        }
    }

    // Fase de captura: o clique é tratado antes de chegar ao card, que abriria o modal.
    document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('.wn-tt-btn');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        if (btn.disabled) return;
        targetSlug = btn.getAttribute('data-slug');
        $input.val('').trigger('click');
    }, true);

    $input.on('change', function () {
        var file = this.files && this.files[0];
        if (!file || !targetSlug) return;

        if (['image/png', 'image/jpeg', 'image/webp'].indexOf(file.type) === -1) {
            toast(T.badType, true);
            return;
        }
        if (file.size > D.maxSize) {
            toast(T.tooBig, true);
            return;
        }

        var slug = targetSlug;
        var $btns = $('.wn-tt-btn').filter(function () { return this.getAttribute('data-slug') === slug; })
            .addClass('is-loading').prop('disabled', true);

        var data = new FormData();
        data.append('action', 'wn_theme_thumb_upload');
        data.append('nonce', D.nonce);
        data.append('theme', slug);
        data.append('file', file);

        $.ajax({ url: D.ajaxUrl, method: 'POST', data: data, processData: false, contentType: false })
            .done(function (res) {
                if (!res || !res.success) {
                    toast((res && res.data && res.data.message) || T.error, true);
                    return;
                }
                var url = res.data.url;

                // Card
                var $shot = $('.themes .theme').filter(function () { return $(this).data('slug') === slug; }).find('.theme-screenshot');
                $shot.removeClass('blank');
                var $img = $shot.find('img');
                if (!$img.length) $img = $('<img alt="">').prependTo($shot);
                $img.attr('src', url);

                // Modal aberto do mesmo tema
                if (overlaySlug() === slug) {
                    var $modal = $('.theme-overlay .theme-screenshots');
                    var $mimg = $modal.find('.screenshot img');
                    if ($mimg.length) {
                        $mimg.attr('src', url);
                    } else {
                        $modal.find('.screenshot').removeClass('blank').append($('<img alt="">').attr('src', url));
                    }
                }

                // Atualiza o modelo do Backbone para o modal mostrar a nova thumb ao reabrir.
                var model = window.wp && wp.themes && wp.themes.Run && wp.themes.Run.themes && wp.themes.Run.themes.get(slug);
                if (model) model.set('screenshot', [url]);

                toast(T.success);
            })
            .fail(function (xhr) {
                var msg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message;
                toast(msg || T.error, true);
            })
            .always(function () {
                $btns.removeClass('is-loading').prop('disabled', false);
            });
    });

    $(addButtons);
    if (window.MutationObserver) {
        var pending = false;
        new MutationObserver(function () {
            if (pending) return;
            pending = true;
            requestAnimationFrame(function () { pending = false; addButtons(); });
        }).observe(document.querySelector('.wrap') || document.body, { childList: true, subtree: true });
    }
})(jQuery);
