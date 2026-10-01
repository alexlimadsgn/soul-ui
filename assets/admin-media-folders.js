/* ─────────────────────────────────────────────────────────────────────────────
   Soul UI — Pastas da Biblioteca de Mídia
   Painel lateral de pastas, cards de pastas, filtro, drag & drop e upload
   direto na pasta atual. Funciona nos modos grade e lista de upload.php.
───────────────────────────────────────────────────────────────────────────── */
(function ($) {
    'use strict';

    var D = window.wnMediaFolders;
    if (!D) return;

    var T = D.i18n;
    var state = {
        folders: D.folders || [],
        counts: D.counts || { all: 0, none: 0 },
        current: String(D.current || 'all'),
        search: '',
        collapsed: load('wn-mf-collapsed', {})
    };
    var isGrid = !!document.getElementById('wp-media-grid');

    /* ── Utilidades ─────────────────────────────────────────────────────── */

    function load(key, fallback) {
        try { return JSON.parse(localStorage.getItem(key)) || fallback; } catch (e) { return fallback; }
    }
    function save(key, val) {
        try { localStorage.setItem(key, JSON.stringify(val)); } catch (e) { /* noop */ }
    }
    function esc(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function byId(id) {
        id = parseInt(id, 10);
        for (var i = 0; i < state.folders.length; i++) if (state.folders[i].id === id) return state.folders[i];
        return null;
    }
    function children(parent) {
        return state.folders
            .filter(function (f) { return f.parent === parent; })
            .sort(function (a, b) {
                if (a.starred !== b.starred) return a.starred ? -1 : 1;
                return a.name.localeCompare(b.name, undefined, { numeric: true, sensitivity: 'base' });
            });
    }
    function ancestors(id) {
        var out = [], f = byId(id);
        while (f) { out.unshift(f); f = f.parent ? byId(f.parent) : null; }
        return out;
    }

    function api(action, data) {
        return $.post(D.ajaxUrl, $.extend({ action: 'wn_mf_' + action, nonce: D.nonce }, data || {}))
            .then(function (res) {
                if (!res || !res.success) return $.Deferred().reject(res && res.data && res.data.message);
                state.folders = res.data.folders;
                state.counts = res.data.counts;
                render();
                return res.data;
            })
            .fail(function (msg) { toast(typeof msg === 'string' ? msg : T.error, true); });
    }

    function toast(msg, isError) {
        var $t = $('<div class="wn-mf-toast' + (isError ? ' is-error' : '') + '">').text(msg).appendTo('body');
        setTimeout(function () { $t.addClass('is-in'); }, 10);
        setTimeout(function () { $t.removeClass('is-in'); setTimeout(function () { $t.remove(); }, 250); }, 2600);
    }

    var ICON_FOLDER = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6.5A2.5 2.5 0 0 1 5.5 4h3.9c.6 0 1.2.25 1.6.7l1.2 1.3h6.3A2.5 2.5 0 0 1 21 8.5v9A2.5 2.5 0 0 1 18.5 20h-13A2.5 2.5 0 0 1 3 17.5z"/></svg>';
    var ICON_STAR = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/></svg>';
    var ICON_CHEV = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>';
    var ICON_DOTS = '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/></svg>';
    var ICON_ALL = '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>';
    var ICON_NONE = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h10"/></svg>';

    /* ── Estrutura ──────────────────────────────────────────────────────── */

    var $wrap = $('#wpbody-content > .wrap').first();
    if (!$wrap.length) return;

    $('body').addClass('wn-mf-on');

    // Layout em duas colunas: painel de pastas + conteúdo original da página
    var $layout = $('<div class="wn-mf-layout"><div class="wn-mf-main"></div></div>');
    var $main = $layout.find('.wn-mf-main');
    $wrap.children().appendTo($main);
    $layout.appendTo($wrap);

    var $side = $(
        '<aside id="wn-mf-sidebar" aria-label="' + esc(T.folders) + '">' +
            '<div class="wn-mf-head">' +
                '<strong>' + esc(T.folders) + '</strong>' +
                '<button type="button" class="wn-mf-btn wn-mf-new" data-parent="0">+ ' + esc(T.newFolder) + '</button>' +
            '</div>' +
            '<input type="search" class="wn-mf-search" placeholder="' + esc(T.search) + '">' +
            '<div class="wn-mf-tree" role="tree"></div>' +
            '<p class="wn-mf-hint">' + esc(T.dragHint) + '</p>' +
        '</aside>'
    ).prependTo($layout);

    var $panel = $('<section id="wn-mf-panel"></section>');
    /**
     * Ordem desejada: cabeçalho → barra de filtros → pastas → arquivos.
     * Modo grade: o frame do core é renderizado depois dentro de #wp-media-grid
     * (que é o próprio .wrap), então o trazemos para a coluna principal.
     */
    function placePanel() {
        if (isGrid) {
            var $frame = $wrap.children('.media-frame');
            if ($frame.length) $frame.appendTo($main);
            $main.find('.media-toolbar input.search').attr('placeholder', function (i, v) { return v || 'Buscar mídia…'; });
            var $grid = $main.find('.attachments-browser .attachments-wrapper').first();
            if (!$grid.length) $grid = $main.find('.attachments-browser ul.attachments').first();
            if ($grid.length) {
                if ($panel.next()[0] !== $grid[0]) $panel.insertBefore($grid);
                return true;
            }
            if (!$panel.parent().length) $panel.appendTo($main);
            return false;
        }
        var $after = $main.find('.wp-filter').first();
        if (!$after.length) $after = $main.find('.wp-header-end').first();
        $after.length ? $panel.insertAfter($after) : $panel.prependTo($main);
        return true;
    }
    placePanel();

    /* ── Render ─────────────────────────────────────────────────────────── */

    function rowHtml(f, depth) {
        var kids = children(f.id);
        var open = !state.collapsed[f.id];
        var html =
            '<div class="wn-mf-row' + (state.current === String(f.id) ? ' is-active' : '') + '" role="treeitem" ' +
                'data-folder="' + f.id + '" draggable="true" style="--depth:' + depth + '">' +
                (kids.length
                    ? '<button type="button" class="wn-mf-toggle' + (open ? ' is-open' : '') + '" aria-label="toggle">' + ICON_CHEV + '</button>'
                    : '<span class="wn-mf-toggle-sp"></span>') +
                '<span class="wn-mf-dot" style="background:' + esc(f.color) + '"></span>' +
                '<span class="wn-mf-name">' + esc(f.name) + '</span>' +
                (f.starred ? '<span class="wn-mf-star">' + ICON_STAR + '</span>' : '') +
                '<span class="wn-mf-count">' + f.count + '</span>' +
                '<button type="button" class="wn-mf-more" aria-label="menu">' + ICON_DOTS + '</button>' +
            '</div>';
        if (kids.length && open) {
            html += kids.map(function (k) { return rowHtml(k, depth + 1); }).join('');
        }
        return html;
    }

    function renderTree() {
        var html =
            '<div class="wn-mf-row wn-mf-fixed' + (state.current === 'all' ? ' is-active' : '') + '" data-folder="all">' +
                '<span class="wn-mf-ico">' + ICON_ALL + '</span><span class="wn-mf-name">' + esc(T.allFiles) + '</span>' +
                '<span class="wn-mf-count">' + state.counts.all + '</span></div>' +
            '<div class="wn-mf-row wn-mf-fixed' + (state.current === 'none' ? ' is-active' : '') + '" data-folder="none">' +
                '<span class="wn-mf-ico">' + ICON_NONE + '</span><span class="wn-mf-name">' + esc(T.uncategorized) + '</span>' +
                '<span class="wn-mf-count">' + state.counts.none + '</span></div>' +
            '<div class="wn-mf-sep"></div>';

        if (state.search) {
            var q = state.search.toLowerCase();
            html += state.folders
                .filter(function (f) { return f.name.toLowerCase().indexOf(q) !== -1; })
                .map(function (f) { return rowHtml($.extend({}, f), 0).replace(/<button type="button" class="wn-mf-toggle[^>]*>.*?<\/button>/, '<span class="wn-mf-toggle-sp"></span>'); })
                .join('');
        } else {
            html += children(0).map(function (f) { return rowHtml(f, 0); }).join('');
        }
        $side.find('.wn-mf-tree').html(html);
    }

    function renderPanel() {
        var cur = state.current;
        var parent = /^\d+$/.test(cur) ? parseInt(cur, 10) : 0;
        var title = cur === 'all' ? T.allFiles : cur === 'none' ? T.uncategorized : (byId(cur) || {}).name || T.allFiles;

        // Breadcrumb
        var crumbs = '<a href="#" data-go="all">' + esc(T.allFiles) + '</a>';
        if (parent) {
            ancestors(parent).forEach(function (f, i, arr) {
                crumbs += '<span>/</span>' + (i === arr.length - 1
                    ? '<strong>' + esc(f.name) + '</strong>'
                    : '<a href="#" data-go="' + f.id + '" data-folder="' + f.id + '">' + esc(f.name) + '</a>');
            });
        } else if (cur === 'none') {
            crumbs += '<span>/</span><strong>' + esc(T.uncategorized) + '</strong>';
        }

        var kids = cur === 'none' ? [] : children(parent);
        var cards = kids.map(function (f) {
            var sub = children(f.id).length;
            return '<button type="button" class="wn-mf-card" data-folder="' + f.id + '" draggable="true" style="--c:' + esc(f.color) + '">' +
                (f.starred ? '<span class="wn-mf-card-star">' + ICON_STAR + '</span>' : '') +
                '<span class="wn-mf-card-ico">' + ICON_FOLDER + '</span>' +
                '<span class="wn-mf-card-name">' + esc(f.name) + '</span>' +
                '<span class="wn-mf-card-meta">' + f.count + ' ' + (f.count === 1 ? 'arquivo' : 'arquivos') +
                    (sub ? ' · ' + sub + ' ' + (sub === 1 ? 'pasta' : 'pastas') : '') + '</span>' +
            '</button>';
        }).join('');

        if (cur !== 'none') {
            cards += '<button type="button" class="wn-mf-card wn-mf-card--new wn-mf-new" data-parent="' + parent + '">' +
                '<span class="wn-mf-card-plus">+</span><span class="wn-mf-card-name">' + esc(parent ? T.newSub : T.newFolder) + '</span></button>';
        }

        var options = '<option value="">' + esc(T.moveTo) + '</option><option value="0">' + esc(T.uncategorized) + '</option>' + optionTree(0, 0);

        $panel.html(
            '<div class="wn-mf-panel-head">' +
                '<div><nav class="wn-mf-crumbs">' + crumbs + '</nav><h2 class="wn-mf-title">' + esc(title) + '</h2></div>' +
                '<select class="wn-mf-bulk">' + options + '</select>' +
            '</div>' +
            (cards ? '<div class="wn-mf-label">' + esc(T.subfolders) + ' <span>' + kids.length + '</span></div><div class="wn-mf-cards">' + cards + '</div>' : '')
        );
    }

    function optionTree(parent, depth) {
        return children(parent).map(function (f) {
            return '<option value="' + f.id + '">' + new Array(depth + 1).join('— ') + esc(f.name) + '</option>' + optionTree(f.id, depth + 1);
        }).join('');
    }

    function render() {
        renderTree();
        renderPanel();
    }

    /* ── Navegação / filtro ─────────────────────────────────────────────── */

    function gridLibrary() {
        var frame = window.wp && wp.media && wp.media.frame;
        return frame && frame.state && frame.state() && frame.state().get('library');
    }

    function setUploadFolder() {
        var id = /^\d+$/.test(state.current) ? state.current : '';
        try {
            var up = wp.media.frame.uploader.uploader.uploader;
            up.setOption('multipart_params', $.extend({}, up.getOption('multipart_params'), { wn_folder: id }));
        } catch (e) { /* uploader ainda não iniciado */ }
        if (window.wp && wp.Uploader && wp.Uploader.defaults) {
            wp.Uploader.defaults.multipart_params = $.extend({}, wp.Uploader.defaults.multipart_params, { wn_folder: id });
        }
    }

    function syncUrls() {
        var url = new URL(window.location.href);
        if (state.current === 'all') url.searchParams.delete('wn_folder');
        else url.searchParams.set('wn_folder', state.current);
        window.history.replaceState(null, '', url.toString());

        // Mantém a pasta ao alternar grade/lista
        $('.view-switch a').each(function () {
            var u = new URL(this.href, window.location.href);
            if (state.current === 'all') u.searchParams.delete('wn_folder');
            else u.searchParams.set('wn_folder', state.current);
            this.href = u.toString();
        });
    }

    function go(folder) {
        folder = String(folder);
        if (!isGrid) {
            var url = new URL(window.location.href);
            url.searchParams.delete('paged');
            if (folder === 'all') url.searchParams.delete('wn_folder');
            else url.searchParams.set('wn_folder', folder);
            window.location.href = url.toString();
            return;
        }
        state.current = folder;
        var lib = gridLibrary();
        if (lib) {
            if (folder === 'all') lib.props.unset('wn_folder');
            else lib.props.set({ wn_folder: folder });
        }
        // Expande ancestrais da pasta aberta
        if (/^\d+$/.test(folder)) ancestors(folder).forEach(function (f) { delete state.collapsed[f.id]; });
        save('wn-mf-collapsed', state.collapsed);
        syncUrls();
        setUploadFolder();
        render();
    }

    /* ── Menu de contexto ───────────────────────────────────────────────── */

    function closeMenu() { $('.wn-mf-menu').remove(); }

    function openMenu(id, x, y) {
        closeMenu();
        var f = byId(id);
        if (!f) return;
        var swatches = D.colors.map(function (c) {
            return '<button type="button" class="wn-mf-swatch' + (c === f.color ? ' is-on' : '') + '" data-color="' + c + '" style="background:' + c + '" aria-label="' + c + '"></button>';
        }).join('');
        var $m = $(
            '<div class="wn-mf-menu" role="menu">' +
                '<button type="button" data-act="sub">' + esc(T.newSub) + '</button>' +
                '<button type="button" data-act="rename">' + esc(T.rename) + '</button>' +
                '<button type="button" data-act="star">' + esc(f.starred ? T.unstar : T.star) + '</button>' +
                '<div class="wn-mf-menu-label">' + esc(T.color) + '</div><div class="wn-mf-swatches">' + swatches + '</div>' +
                '<hr><button type="button" data-act="delete" class="is-danger">' + esc(T.delete) + '</button>' +
            '</div>'
        ).data('id', id).appendTo('body');
        var w = $m.outerWidth(), h = $m.outerHeight();
        $m.css({ left: Math.min(x, window.innerWidth - w - 8), top: Math.min(y, window.innerHeight - h - 8) });
    }

    $(document).on('click', '.wn-mf-menu button', function (e) {
        e.stopPropagation();
        var id = $(this).closest('.wn-mf-menu').data('id');
        var f = byId(id);
        var act = $(this).data('act');
        closeMenu();
        if (!f) return;

        if ($(this).data('color')) {
            api('update', { id: id, color: $(this).data('color') });
        } else if (act === 'sub') {
            createFolder(id);
        } else if (act === 'rename') {
            var name = window.prompt(T.namePrompt, f.name);
            if (name && name.trim() && name.trim() !== f.name) api('rename', { id: id, name: name.trim() });
        } else if (act === 'star') {
            api('update', { id: id, starred: f.starred ? '0' : '1' });
        } else if (act === 'delete') {
            if (window.confirm(T.confirmDel.replace('%s', f.name))) {
                api('delete', { id: id }).then(function () {
                    if (state.current === String(id)) go(f.parent || 'all');
                    else refreshGrid();
                });
            }
        }
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('.wn-mf-menu, .wn-mf-more').length) closeMenu();
    });
    $(document).on('keydown', function (e) { if (e.key === 'Escape') closeMenu(); });
    $(window).on('scroll resize', closeMenu);

    function createFolder(parent) {
        var name = window.prompt(T.namePrompt, '');
        if (!name || !name.trim()) return;
        delete state.collapsed[parent];
        save('wn-mf-collapsed', state.collapsed);
        api('create', { name: name.trim(), parent: parent || 0 });
    }

    /* ── Eventos do painel ─────────────────────────────────────────────── */

    $side.on('click', '.wn-mf-row', function (e) {
        if ($(e.target).closest('.wn-mf-toggle, .wn-mf-more').length) return;
        go($(this).data('folder'));
    });
    $side.on('click', '.wn-mf-toggle', function (e) {
        e.stopPropagation();
        var id = $(this).closest('.wn-mf-row').data('folder');
        state.collapsed[id] = !state.collapsed[id];
        if (!state.collapsed[id]) delete state.collapsed[id];
        save('wn-mf-collapsed', state.collapsed);
        renderTree();
    });
    $side.on('click', '.wn-mf-more', function (e) {
        e.stopPropagation();
        var r = this.getBoundingClientRect();
        openMenu($(this).closest('.wn-mf-row').data('folder'), r.left, r.bottom + 4);
    });
    $side.on('contextmenu', '.wn-mf-row[draggable]', function (e) {
        e.preventDefault();
        openMenu($(this).data('folder'), e.clientX, e.clientY);
    });
    $side.on('input', '.wn-mf-search', function () {
        state.search = this.value.trim();
        renderTree();
    });

    $(document).on('click', '.wn-mf-new', function (e) {
        e.preventDefault();
        createFolder(parseInt($(this).data('parent'), 10) || 0);
    });

    $panel.on('click', '.wn-mf-card[data-folder]', function () { go($(this).data('folder')); });
    $panel.on('contextmenu', '.wn-mf-card[data-folder]', function (e) {
        e.preventDefault();
        openMenu($(this).data('folder'), e.clientX, e.clientY);
    });
    $panel.on('click', '[data-go]', function (e) {
        e.preventDefault();
        go($(this).data('go'));
    });
    $panel.on('change', '.wn-mf-bulk', function () {
        var val = this.value;
        this.value = '';
        if (val === '') return;
        var ids = selectedIds();
        if (!ids.length) {
            toast(isGrid ? 'Ative "Seleção em massa" e selecione arquivos.' : 'Selecione arquivos na lista.', true);
            return;
        }
        assign(ids, parseInt(val, 10));
    });

    /* ── Seleção e atribuição ───────────────────────────────────────────── */

    function selectedIds() {
        if (isGrid) {
            var frame = window.wp && wp.media && wp.media.frame;
            var sel = frame && frame.state() && frame.state().get('selection');
            return sel ? sel.pluck('id') : [];
        }
        return $('#the-list input[name="media[]"]:checked').map(function () { return parseInt(this.value, 10); }).get();
    }

    function refreshGrid() {
        var lib = gridLibrary();
        if (lib && lib.mirroring) {
            lib.mirroring._hasMore = true;
            lib.props.set({ ignore: (+new Date()) });
        }
    }

    function assign(ids, folder) {
        return api('assign', { ids: ids, folder: folder }).then(function (data) {
            toast(T.moved.replace('%d', data.moved));
            var stays = state.current === 'all' || state.current === String(folder) || (state.current === 'none' && !folder);
            if (stays) return;
            if (isGrid) {
                var lib = gridLibrary();
                if (lib) ids.forEach(function (id) { lib.remove(lib.get(id)); });
                var sel = wp.media.frame.state().get('selection');
                if (sel) sel.reset();
            } else {
                ids.forEach(function (id) { $('#post-' + id).fadeOut(180, function () { $(this).remove(); }); });
            }
        });
    }

    /* ── Drag & drop ───────────────────────────────────────────────────── */

    var DRAG_TYPE = 'application/x-wn-media';
    var dragPayload = null;

    // Itens de mídia arrastáveis (grade e lista)
    $(document).on('mouseenter', '#wp-media-grid li.attachment, #the-list > tr', function () {
        if (!this.hasAttribute('draggable')) this.setAttribute('draggable', 'true');
    });

    $(document).on('dragstart', '#wp-media-grid li.attachment, #the-list > tr', function (e) {
        var id = parseInt(isGrid ? $(this).data('id') : String(this.id).replace('post-', ''), 10);
        if (!id) return;
        var ids = selectedIds();
        if (ids.indexOf(id) === -1) ids = [id];
        dragPayload = { kind: 'files', ids: ids };
        var dt = e.originalEvent.dataTransfer;
        dt.effectAllowed = 'move';
        dt.setData(DRAG_TYPE, '1');
        var $ghost = $('<div class="wn-mf-ghost">').text(ids.length + (ids.length === 1 ? ' arquivo' : ' arquivos')).appendTo('body');
        dt.setDragImage($ghost[0], 10, 10);
        setTimeout(function () { $ghost.remove(); }, 0);
        $('body').addClass('wn-mf-dragging');
    });

    // Pastas arrastáveis (mover para outra pasta)
    $(document).on('dragstart', '.wn-mf-row[draggable], .wn-mf-card[draggable]', function (e) {
        e.stopPropagation();
        dragPayload = { kind: 'folder', id: parseInt($(this).data('folder'), 10) };
        var dt = e.originalEvent.dataTransfer;
        dt.effectAllowed = 'move';
        dt.setData(DRAG_TYPE, '1');
        $('body').addClass('wn-mf-dragging');
    });

    $(document).on('dragend', function () {
        dragPayload = null;
        $('body').removeClass('wn-mf-dragging');
        $('.is-drop').removeClass('is-drop');
    });

    var DROP_SEL = '.wn-mf-row, .wn-mf-card[data-folder], .wn-mf-crumbs a[data-folder], .wn-mf-crumbs a[data-go="all"]';

    $(document).on('dragover', DROP_SEL, function (e) {
        if (!dragPayload) return;
        var target = String($(this).data('folder') || $(this).data('go'));
        if (dragPayload.kind === 'folder' && (target === 'none' || target === String(dragPayload.id))) return;
        e.preventDefault();
        e.originalEvent.dataTransfer.dropEffect = 'move';
        $(this).addClass('is-drop');
    });
    $(document).on('dragleave', DROP_SEL, function () { $(this).removeClass('is-drop'); });

    $(document).on('drop', DROP_SEL, function (e) {
        if (!dragPayload) return;
        e.preventDefault();
        var target = String($(this).data('folder') || $(this).data('go'));
        var payload = dragPayload;
        $(this).removeClass('is-drop');

        if (payload.kind === 'files') {
            if (target === 'all') return;
            assign(payload.ids, target === 'none' ? 0 : parseInt(target, 10));
        } else {
            api('move', { id: payload.id, parent: target === 'all' ? 0 : parseInt(target, 10) });
        }
    });

    /* ── Inicialização ──────────────────────────────────────────────────── */

    render();
    syncUrls();

    if (isGrid) {
        // Aguarda o frame de mídia do core estar pronto
        var tries = 0;
        (function waitFrame() {
            var lib = gridLibrary();
            if (lib && placePanel()) {
                if (state.current !== 'all') lib.props.set({ wn_folder: state.current });
                setUploadFolder();
                return;
            }
            if (tries++ < 100) setTimeout(waitFrame, 100);
        })();
    }

})(jQuery);
