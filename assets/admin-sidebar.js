/**
 * WP Notion UI — JS  v2.0
 * Matches the reference wp-dashboard-clone design.
 * Sidebar collapse syncs --wn-w so adminbar + content track the real width.
 */
(function () {
    'use strict';

    var cfg = window.wpNotionUI || {};
    var W_FULL = parseInt(cfg.wFull || 200, 10);
    var W_SM = parseInt(cfg.wCollapsed || 56, 10);

    // ── Boot ─────────────────────────────────────────────────────────────────

    function initIcons() {
        if (window.lucide) {
            lucide.createIcons();
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        initIcons();
        restoreState();
        setupToggleButtons();
        setupThemeSelector();
        setupSearch();
        setupFlyouts();
        setupMobileLayout();
        setupThemeFilterDrawer();
        setupTablePaginationControls();

        // Libera as transições CSS e mostra a interface após tudo estável


        requestAnimationFrame(function () {
            document.documentElement.classList.remove('wn-render');
            document.documentElement.classList.add('wn-ready');
        });
    });





    // Garante que os ícones sejam renderizados se a página for restaurada do cache (bfcache)
    window.addEventListener('pageshow', function (e) {
        initIcons();
        document.documentElement.classList.remove('wn-render');
        document.documentElement.classList.add('wn-ready');
    });

    // Sincroniza os ícones em transições de página dinâmicas via PJAX
    document.addEventListener('wp-pjax-loaded', function () {
        initIcons();
        setupTablePaginationControls();
    });

    if (window.jQuery) {
        window.jQuery(document).on('wp-pjax-loaded', function () {
            initIcons();
            setupTablePaginationControls();
        });
    }

    // Garante a restauração dos ícones quando a aba/janela recuperar o foco
    window.addEventListener('focus', function () {
        initIcons();
    });



    // ── Mobile Layout ─────────────────────────────────────────────────────────

    function setupMobileLayout() {
        var overlay = document.createElement('div');
        overlay.className = 'wn-overlay';
        document.body.appendChild(overlay);

        overlay.addEventListener('click', function () {
            document.body.classList.remove('wn-mobile-open');
        });

        // Handle resize
        window.addEventListener('resize', function () {
            if (window.innerWidth > 960) {
                document.body.classList.remove('wn-mobile-open');
            }
        });
    }

    // ── Flyout Dynamic Positioning ─────────────────────────────────────────────
    //
    // O flyout usa position:fixed, por isso sai do fluxo do .wn-group e o
    // :hover CSS perde-se assim que o cursor passa do item para o submenu.
    // Solução: gerir abertura/fecho via JS com um timer de debounce.

    function setupFlyouts() {
        var groups = document.querySelectorAll('.wn-group.has-children');
        var openGroup = null; // grupo atualmente com flyout aberto

        function closeFlyoutNow(group) {
            if (!group) return;
            var flyout = group.querySelector('.wn-flyout');
            if (flyout) {
                flyout.classList.remove('wn-flyout-open');
            }
            if (openGroup === group) {
                openGroup = null;
            }
        }

        groups.forEach(function (group) {
            var flyout = group.querySelector('.wn-flyout');
            if (!flyout) return;

            group._openTimer = null;
            group._closeTimer = null;

            group.addEventListener('mouseenter', function () {
                // Cancela o fecho deste grupo se o rato voltar a entrar
                if (group._closeTimer) {
                    clearTimeout(group._closeTimer);
                    group._closeTimer = null;
                }

                if (openGroup === group) return;

                // Cancela abertura pendente anterior
                if (group._openTimer) {
                    clearTimeout(group._openTimer);
                }

                // Agenda a abertura (150ms)
                group._openTimer = setTimeout(function () {
                    if (openGroup && openGroup !== group) {
                        closeFlyoutNow(openGroup);
                    }

                    // Posicionamento vertical
                    var rect = group.getBoundingClientRect();
                    var top = rect.top - 2;
                    var fH = flyout.offsetHeight;

                    if (top + fH > window.innerHeight) { top = window.innerHeight - fH - 10; }
                    if (top < 10) top = 10;

                    flyout.style.setProperty('--wn-flyout-top', top + 'px');
                    flyout.classList.add('wn-flyout-open');
                    openGroup = group;
                    group._openTimer = null;
                }, 150);
            });

            group.addEventListener('mouseleave', function () {
                // Cancela abertura se sair antes de abrir
                if (group._openTimer) {
                    clearTimeout(group._openTimer);
                    group._openTimer = null;
                }

                // Agenda o fecho deste grupo especificamente
                if (openGroup === group) {
                    if (group._closeTimer) { clearTimeout(group._closeTimer); }
                    group._closeTimer = setTimeout(function () {
                        closeFlyoutNow(group);
                        group._closeTimer = null;
                    }, 250);
                }
            });

            flyout.addEventListener('mouseenter', function () {
                if (group._closeTimer) {
                    clearTimeout(group._closeTimer);
                    group._closeTimer = null;
                }
            });

            flyout.addEventListener('mouseleave', function () {
                if (openGroup === group) {
                    if (group._closeTimer) { clearTimeout(group._closeTimer); }
                    group._closeTimer = setTimeout(function () {
                        closeFlyoutNow(group);
                        group._closeTimer = null;
                    }, 250);
                }
            });
        });
    }

    // ── Width sync ────────────────────────────────────────────────────────────

    function syncWidth(collapsed) {
        document.documentElement.style.setProperty(
            '--wn-w',
            (collapsed ? W_SM : W_FULL) + 'px'
        );
    }

    // ── Restore persisted collapse state ──────────────────────────────────────

    function restoreState() {
        var collapsed = localStorage.getItem('wn-collapsed') === '1';
        if (collapsed) {
            document.documentElement.classList.add('wn-collapsed');
            document.body.classList.add('wn-collapsed');
        }
        syncWidth(collapsed);
    }

    // ── Toggle buttons (sidebar collapse btn + topbar hamburger) ─────────────

    function setupToggleButtons() {
        // Both buttons do the same thing
        ['wn-bar-toggle'].forEach(function (id) {
            var btn = document.getElementById(id);
            if (!btn) return;
            btn.addEventListener('click', function () {
                // If mobile breakpoint, toggle mobile-open instead of collapse state
                if (window.innerWidth <= 960) {
                    document.body.classList.toggle('wn-mobile-open');
                    return;
                }

                var collapsed = document.documentElement.classList.toggle('wn-collapsed');
                // Ensure body also has the class for any legacy CSS
                document.body.classList.toggle('wn-collapsed', collapsed);

                syncWidth(collapsed);
                localStorage.setItem('wn-collapsed', collapsed ? '1' : '0');
            });
        });
    }

    // ── Theme Selector Dropdown & Switcher ────────────────────────────────────

    function setupThemeSelector() {
        var themeGroup = document.querySelector('.wn-bar-theme-group');
        if (!themeGroup) return;

        var trigger = themeGroup.querySelector('.wn-theme-trigger');
        var dropdown = themeGroup.querySelector('.wn-bar-theme-dropdown');

        if (trigger && dropdown) {
            trigger.addEventListener('click', function (e) {
                e.stopPropagation();
                var isOpen = dropdown.classList.toggle('wn-theme-open');
                trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });

            document.addEventListener('click', function (e) {
                if (!themeGroup.contains(e.target)) {
                    dropdown.classList.remove('wn-theme-open');
                    trigger.setAttribute('aria-expanded', 'false');
                }
            });
        }

        var buttons = themeGroup.querySelectorAll('.wn-theme-palette-btn');
        buttons.forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var scheme = btn.getAttribute('data-scheme');
                if (!scheme) return;

                buttons.forEach(function (b) { b.classList.remove('is-active'); });
                btn.classList.add('is-active');

                // Exibe o loader progressivo no topo da tela
                var loader = document.getElementById('wn-pjax-loader');
                if (loader) {
                    loader.style.opacity = '1';
                    loader.style.width = '70%';
                }

                var ajaxUrl = themeGroup.getAttribute('data-ajax-url') || (cfg && cfg.ajaxUrl ? cfg.ajaxUrl : 'admin-ajax.php');
                var nonce = themeGroup.getAttribute('data-nonce') || (cfg && cfg.nonce ? cfg.nonce : '');

                var formData = new FormData();
                formData.append('action', 'wn_set_admin_color');
                formData.append('scheme', scheme);
                formData.append('nonce', nonce);

                fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data && data.success) {
                        window.location.reload();
                    } else {
                        window.location.reload();
                    }
                })
                .catch(function () {
                    window.location.reload();
                });
            });
        });
    }


    // ── Search (⌘K or bar input) ──────────────────────────────────────────────

    function setupSearch() {
        // Topbar search trigger / pill
        var searchTrigger = document.getElementById('wn-search-trigger-pill');
        if (searchTrigger) {
            searchTrigger.addEventListener('click', function (e) {
                e.preventDefault();
                openSearchModal();
            });
        }

        var barInput = document.querySelector('#wn-adminbar .wn-bar-search input');

        document.addEventListener('keydown', function (e) {
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                if (barInput) { barInput.focus(); return; }
                openSearchModal();
            }
            if (e.key === 'Escape') closeSearchModal();
        });

        if (barInput) {
            barInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    var q = barInput.value.trim();
                    if (q) openSearchModal(q);
                }
            });
        }
    }


    function openSearchModal(prefill) {
        if (document.getElementById('wn-search-modal')) {
            var inp = document.getElementById('wn-search-input');
            inp.focus();
            if (prefill) { inp.value = prefill; inp.dispatchEvent(new Event('input')); }
            return;
        }

        var links = Array.from(document.querySelectorAll(
            '#wn-sidebar .wn-item, #wn-sidebar .wn-flyout a'
        )).map(function (el) {
            return { label: el.textContent.trim(), url: el.href || '' };
        }).filter(function (l) { return l.label && l.url; });

        var overlay = make('div', {
            id: 'wn-search-modal',
            style: 'position:fixed;inset:0;z-index:99999;display:flex;align-items:flex-start;justify-content:center;padding-top:15vh;background:rgba(0,0,0,.55)',
        });

        var box = make('div', {
            style: 'background:#1d2327;border:1px solid #2c3338;border-radius:6px;width:520px;max-width:90vw;overflow:hidden;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Helvetica,sans-serif;box-shadow:0 12px 40px rgba(0,0,0,.5)',
        });

        var input = make('input', {
            id: 'wn-search-input',
            type: 'search',
            placeholder: 'Pesquisar páginas, posts, configurações…',
            style: 'width:100%;box-sizing:border-box;padding:14px 16px;font-size:14px;border:none;border-bottom:1px solid #2c3338;outline:none;background:transparent;color:#f0f0f1;font-family:inherit',
        });

        var results = make('div', {
            id: 'wn-search-results',
            style: 'padding:6px;max-height:320px;overflow-y:auto',
        });
        results.innerHTML = hint('Digite para pesquisar…');

        input.addEventListener('input', function () {
            var q = input.value.trim().toLowerCase();
            if (!q) { results.innerHTML = hint('Digite para pesquisar…'); return; }
            var hits = links.filter(function (l) { return l.label.toLowerCase().includes(q); });
            if (!hits.length) { results.innerHTML = hint('Nenhum resultado.'); return; }
            results.innerHTML = hits.map(function (m) {
                return '<a href="' + esc(m.url) + '" '
                    + 'style="display:block;padding:8px 10px;border-radius:4px;text-decoration:none;font-size:13px;color:#a7aaad;" '
                    + 'onmouseover="this.style.background=\'#2c3338\';this.style.color=\'#72aee6\'" '
                    + 'onmouseout="this.style.background=\'transparent\';this.style.color=\'#a7aaad\'">'
                    + esc(m.label) + '</a>';
            }).join('');
        });

        if (prefill) {
            input.value = prefill;
            input.dispatchEvent(new Event('input'));
        }

        box.appendChild(input);
        box.appendChild(results);
        overlay.appendChild(box);
        document.body.appendChild(overlay);

        overlay.addEventListener('click', function (e) { if (e.target === overlay) closeSearchModal(); });
        input.focus();
    }

    function closeSearchModal() {
        var m = document.getElementById('wn-search-modal');
        if (m) m.remove();
    }

    // ── Theme Filter Drawer ───────────────────────────────────────────────────

    function setupThemeFilterDrawer() {
        if (!window.jQuery) return;
        var $ = window.jQuery;

        $(document).on('click', '.drawer-toggle', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $btn = $(this);
            var $drawer = $('.filter-drawer');
            var $filter = $('.wp-filter');

            var isOpen = $drawer.hasClass('wn-drawer-open');
            if (isOpen) {
                $drawer.removeClass('wn-drawer-open');
                $filter.removeClass('show-filters');
                $btn.removeClass('open').attr('aria-expanded', 'false');
            } else {
                $drawer.addClass('wn-drawer-open');
                $filter.addClass('show-filters');
                $btn.addClass('open').attr('aria-expanded', 'true');
            }
        });

        $(document).on('click', function (e) {
            var $target = $(e.target);
            if ($target.closest('.filter-drawer').length === 0 && $target.closest('.drawer-toggle').length === 0) {
                $('.filter-drawer').removeClass('wn-drawer-open');
                $('.wp-filter').removeClass('show-filters');
                $('.drawer-toggle').removeClass('open').attr('aria-expanded', 'false');
            }
        });

        $(document).on('click', '.filter-drawer .apply-filters', function () {
            $('.filter-drawer').removeClass('wn-drawer-open');
            $('.wp-filter').removeClass('show-filters');
            $('.drawer-toggle').removeClass('open').attr('aria-expanded', 'false');
        });
    }

    // ── Table Rows Per Page Controls ──────────────────────────────────────────

    function setupTablePaginationControls() {
        if (!window.jQuery) return;
        var $ = window.jQuery;

        var $tablenav = $('.tablenav.top');
        if (!$tablenav.length) return;

        // Verifica se há tabela de listagem na página
        if (!$('table.wp-list-table').length) return;

        $tablenav.each(function () {
            var $nav = $(this);
            if ($nav.find('.wn-per-page-container').length) return;

            var currentVal = parseInt(cfg.perPage || 20, 10);
            var options = [10, 20, 50, 100];
            if (options.indexOf(currentVal) === -1) {
                options.push(currentVal);
                options.sort(function (a, b) { return a - b; });
            }

            var $wrap = $('<div class="wn-per-page-container"></div>');
            var $label = $('<label class="wn-per-page-label">Linhas:</label>');
            var $select = $('<select class="wn-per-page-select" title="Quantidade de itens exibidos por página"></select>');

            options.forEach(function (opt) {
                var $opt = $('<option></option>').val(opt).text(opt);
                if (opt === currentVal) {
                    $opt.prop('selected', true);
                }
                $select.append($opt);
            });

            $wrap.append($label).append($select);

            // Inserir antes ou dentro de .tablenav-pages se existir, ou no final de tablenav
            var $pages = $nav.find('.tablenav-pages');
            if ($pages.length) {
                $pages.before($wrap);
            } else {
                $nav.append($wrap);
            }

            $select.on('change', function () {
                var selectedVal = parseInt($(this).val(), 10);
                if (!selectedVal) return;

                $wrap.addClass('wn-loading');
                $select.prop('disabled', true);

                $.ajax({
                    url: cfg.ajaxUrl || 'admin-ajax.php',
                    type: 'POST',
                    data: {
                        action: 'wn_set_posts_per_page',
                        nonce: cfg.nonce,
                        screen_id: cfg.screenId || '',
                        per_page: selectedVal
                    },
                    success: function (res) {
                        cfg.perPage = selectedVal;
                        // Recarrega a página respeitando filtros atuais e voltando à página 1
                        var currentUrl = new URL(window.location.href);
                        currentUrl.searchParams.delete('paged');
                        window.location.href = currentUrl.toString();
                    },
                    error: function () {
                        $wrap.removeClass('wn-loading');
                        $select.prop('disabled', false);
                        alert('Não foi possível salvar a preferência.');
                    }
                });
            });
        });
    }

    // ── Utilities ─────────────────────────────────────────────────────────────





    function make(tag, attrs) {
        var n = document.createElement(tag);
        Object.keys(attrs).forEach(function (k) { n[k] = attrs[k]; });
        return n;
    }

    function hint(text) {
        return '<p style="padding:10px;font-size:13px;color:#646970;">' + esc(text) + '</p>';
    }

    function esc(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

})();

