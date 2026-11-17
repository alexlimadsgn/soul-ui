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

    var ICON_UPDATE = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/></svg>';

    var $input = $('<input type="file" accept="image/png,image/jpeg,image/webp" hidden>').appendTo(document.body);
    var $zipInput = $('<input type="file" accept=".zip,application/zip" hidden>').appendTo(document.body);
    var targetSlug = null;
    var zipSlug = null;

    function toast(msg, isError) {
        var $t = $('<div class="wn-tt-toast' + (isError ? ' is-error' : '') + '"></div>').text(msg).appendTo(document.body);
        setTimeout(function () { $t.addClass('is-in'); }, 10);
        setTimeout(function () { $t.removeClass('is-in'); setTimeout(function () { $t.remove(); }, 250); }, 2600);
    }

    function actions(slug) {
        var $wrap = $('<div class="wn-tt-actions"></div>').attr('data-slug', slug);
        $('<button type="button" class="wn-tt-btn"></button>')
            .attr({ title: T.title, 'data-slug': slug })
            .html(ICON + '<span>' + T.button + '</span>')
            .appendTo($wrap);
        if (D.canZip) {
            $('<button type="button" class="wn-tt-btn wn-tt-update"></button>')
                .attr({ title: T.updateTitle, 'data-slug': slug })
                .html(ICON_UPDATE + '<span>' + T.update + '</span>')
                .appendTo($wrap);
        }
        return $wrap;
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
            if (!$shot.length || $shot.find('.wn-tt-actions').length) return;
            actions($(this).data('slug')).appendTo($shot);
        });

        var $modal = $('.theme-overlay .theme-screenshots');
        var slug = overlaySlug();
        if ($modal.length && slug) {
            var $wrap = $modal.find('.wn-tt-actions');
            if ($wrap.length && $wrap.attr('data-slug') !== slug) $wrap.remove();
            if (!$modal.find('.wn-tt-actions').length) actions(slug).appendTo($modal);
        }
    }

    // Fase de captura: o clique é tratado antes de chegar ao card, que abriria o modal.
    document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('.wn-tt-btn');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        if (btn.disabled) return;
        if (btn.classList.contains('wn-tt-update')) {
            zipSlug = btn.getAttribute('data-slug');
            $zipInput.val('').trigger('click');
            return;
        }
        targetSlug = btn.getAttribute('data-slug');
        $input.val('').trigger('click');
    }, true);

    function themeName(slug) {
        var model = window.wp && wp.themes && wp.themes.Run && wp.themes.Run.themes && wp.themes.Run.themes.get(slug);
        return (model && model.get('name')) || slug;
    }

    $zipInput.on('change', function () {
        var file = this.files && this.files[0];
        if (!file || !zipSlug) return;

        if (!/\.zip$/i.test(file.name)) {
            toast(T.zipOnly, true);
            return;
        }
        if (file.size > D.maxZip) {
            toast(T.zipTooBig, true);
            return;
        }

        var slug = zipSlug;
        if (!window.confirm(T.updateConfirm.replace('%s', themeName(slug)))) return;

        var $btns = $('.wn-tt-btn').prop('disabled', true);
        $('.wn-tt-update').filter(function () { return this.getAttribute('data-slug') === slug; }).addClass('is-loading');
        toast(T.updating);

        var data = new FormData();
        data.append('action', 'wn_theme_zip_update');
        data.append('nonce', D.nonce);
        data.append('theme', slug);
        data.append('file', file);

        $.ajax({ url: D.ajaxUrl, method: 'POST', data: data, processData: false, contentType: false })
            .done(function (res) {
                if (!res || !res.success) {
                    toast((res && res.data && res.data.message) || T.updateError, true);
                    $btns.prop('disabled', false).removeClass('is-loading');
                    return;
                }
                toast(T.updated);
                // Recarrega sem ?theme= para a lista mostrar versão e thumb novas.
                setTimeout(function () { window.location.href = window.location.pathname; }, 900);
            })
            .fail(function (xhr) {
                var msg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message;
                toast(msg || T.updateError, true);
                $btns.prop('disabled', false).removeClass('is-loading');
            });
    });

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
