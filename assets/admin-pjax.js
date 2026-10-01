/**
 * WP Admin UI — Instant PJAX Navigation
 * Intercepts in-admin links to load content asynchronously without full page reloads.
 */
(function () {
    'use strict';

    var isFetching = false;
    var loaderEl = null;

    // ── Create Top Progress Bar Loader ──────────────────────────────────────

    function getLoader() {
        if (!loaderEl) {
            loaderEl = document.getElementById('wn-pjax-loader');
            if (!loaderEl) {
                loaderEl = document.createElement('div');
                loaderEl.id = 'wn-pjax-loader';
                document.body.appendChild(loaderEl);
            }
        }
        return loaderEl;
    }

    function showLoader() {
        var loader = getLoader();
        loader.style.opacity = '1';
        loader.style.width = '30%';
        requestAnimationFrame(function () {
            loader.style.width = '70%';
        });
    }

    function hideLoader() {
        var loader = getLoader();
        loader.style.width = '100%';
        setTimeout(function () {
            loader.style.opacity = '0';
            setTimeout(function () {
                loader.style.width = '0%';
            }, 200);
        }, 150);
    }

    // ── Eligibility Check ────────────────────────────────────────────────────

    function isEligibleLink(anchor, event) {
        if (!anchor || !anchor.href) return false;
        if (event.defaultPrevented) return false;
        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return false;

        var href = anchor.getAttribute('href');
        if (!href || href === '#' || href.indexOf('javascript:') === 0) return false;

        // Check attributes
        if (anchor.target && anchor.target !== '_self') return false;
        if (anchor.hasAttribute('download')) return false;
        if (anchor.getAttribute('data-no-pjax') === 'true' || anchor.getAttribute('data-pjax') === 'false') return false;

        var url = new URL(anchor.href, window.location.href);

        // Same origin check
        if (url.origin !== window.location.origin) return false;

        // Must be in wp-admin
        if (url.pathname.indexOf('/wp-admin/') === -1) return false;

        // Exclude non-HTML & special WP pages
        var path = url.pathname.toLowerCase();
        var currentPath = window.location.pathname.toLowerCase();
        var excludedPaths = [
            'wp-login.php',
            'wp-signup.php',
            'customize.php',
            'export.php',
            'import.php',
            'async-upload.php',
            'upload.php',
            'themes.php',
            'theme-install.php',
            'plugin-install.php',
            'plugin-upload.php',
            'update.php',
            'update-core.php',
            'post-new.php',
            'post.php',
            'site-editor.php',
            'widgets.php',
            'nav-menus.php',
            'options-permalink.php',
            'privacy.php',
            'erase-personal-data.php',
            'site-health.php'
        ];

        for (var i = 0; i < excludedPaths.length; i++) {
            if (path.indexOf(excludedPaths[i]) !== -1 || currentPath.indexOf(excludedPaths[i]) !== -1) return false;
        }

        // Exclude WooCommerce React / Heavy Builder / Special Admin Pages
        if (url.search.indexOf('page=wc-admin') !== -1 || url.search.indexOf('page=wc-orders') !== -1) return false;

        // Excluir o File Editor IDE (editor pesado Monaco — requer carga completa da página)
        if (url.search.indexOf('page=code-editor-ide') !== -1 || url.search.indexOf('page=code-editor-themes') !== -1) return false;
        if (url.search.indexOf('action=elementor') !== -1 || url.search.indexOf('action=editpost') !== -1) return false;
        // Exclude upload action on themes/plugins pages
        if (url.search.indexOf('action=upload') !== -1) return false;

        // Exclude file extensions
        if (/\.(zip|gz|tar|pdf|docx?|xlsx?|csv|png|jpe?g|gif|svg)$/i.test(path)) return false;

        // Exclude logout & action links
        if (url.search.indexOf('action=logout') !== -1 || url.search.indexOf('action=delete') !== -1) return false;

        // Hash-only links on same page
        if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash !== window.location.hash) {
            return false;
        }

        return true;
    }

    // ── Update Sidebar Active States ─────────────────────────────────────────

    function updateActiveState(targetUrl) {
        var sidebar = document.getElementById('wn-sidebar');
        if (!sidebar) return;

        var targetObj = new URL(targetUrl, window.location.origin);
        var targetPath = targetObj.pathname;
        var targetParams = new URLSearchParams(targetObj.search);

        var targetPostType = targetParams.get('post_type');
        if (!targetPostType && targetPath.indexOf('edit.php') !== -1) {
            targetPostType = 'post';
        }
        var targetPage = targetParams.get('page');

        // Remove active classes
        var activeItems = sidebar.querySelectorAll('.is-active, .has-active-child');
        activeItems.forEach(function (el) {
            el.classList.remove('is-active', 'has-active-child');
        });

        // Find matching item
        var links = sidebar.querySelectorAll('a[href]');
        var matchedLink = null;
        var bestScore = -1;

        links.forEach(function (link) {
            var linkObj = new URL(link.href, window.location.origin);
            if (linkObj.pathname !== targetPath) return;

            var linkParams = new URLSearchParams(linkObj.search);
            var linkPostType = linkParams.get('post_type');
            if (!linkPostType && linkObj.pathname.indexOf('edit.php') !== -1) {
                linkPostType = 'post';
            }
            var linkPage = linkParams.get('page');

            // Compare post_type strictly
            if (targetPostType || linkPostType) {
                if (targetPostType !== linkPostType) return;
            }

            // Compare page parameter strictly
            if (targetPage || linkPage) {
                if (targetPage !== linkPage) return;
            }

            // Calculate matching score
            var score = 1;
            linkParams.forEach(function (val, key) {
                if (targetParams.get(key) === val) {
                    score += 2;
                }
            });
            if (linkObj.search === targetObj.search) {
                score += 10;
            }

            if (score > bestScore) {
                bestScore = score;
                matchedLink = link;
            }
        });

        if (matchedLink) {
            matchedLink.classList.add('is-active');
            var group = matchedLink.closest('.wn-group');
            if (group) {
                group.classList.add('has-active-child');
            }
        }
    }

    // ── Update Topbar Page Title Badge ──────────────────────────────────────

    function updateTopbarBadge(doc) {
        var newBadge = doc.querySelector('.wn-bar-badge-page');
        var currBadge = document.querySelector('.wn-bar-badge-page');
        if (newBadge && currBadge) {
            currBadge.textContent = newBadge.textContent;
        }

        var newSub = doc.querySelector('.wn-bar-title-sub');
        var currSub = document.querySelector('.wn-bar-title-sub');
        var titleContainer = document.querySelector('.wn-bar-title');

        if (newSub && currSub) {
            currSub.textContent = newSub.textContent;
        } else if (newSub && !currSub && titleContainer) {
            var subSpan = document.createElement('span');
            subSpan.className = 'wn-bar-title-sub';
            subSpan.textContent = newSub.textContent;
            var sepSpan = document.createElement('span');
            sepSpan.className = 'wn-bar-title-sep';
            sepSpan.setAttribute('aria-hidden', 'true');
            sepSpan.textContent = '/';
            titleContainer.insertBefore(sepSpan, currBadge);
            titleContainer.insertBefore(subSpan, sepSpan);
        } else if (!newSub && currSub && titleContainer) {
            currSub.remove();
            var currSep = titleContainer.querySelector('.wn-bar-title-sep');
            if (currSep) currSep.remove();
        }
    }

    // ── Page Loader ─────────────────────────────────────────────────────────

    function loadPage(url, pushState) {
        if (isFetching) return;
        isFetching = true;
        showLoader();

        fetch(url, {
            headers: {
                'X-PJAX': 'true',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function (response) {
            if (!response.ok) {
                window.location.href = url;
                return;
            }

            var finalUrl = response.redirected ? response.url : url;

            return response.text().then(function (htmlText) {
                var parser = new DOMParser();
                var doc = parser.parseFromString(htmlText, 'text/html');

                var newContent = doc.querySelector('#wpbody-content');
                var currContent = document.querySelector('#wpbody-content');

                if (!newContent || !currContent) {
                    window.location.href = finalUrl;
                    return;
                }

                // 1. Sync Head Styles & Links
                var newHeadStyles = doc.querySelectorAll('head link[rel="stylesheet"], head style');
                var firstOurStyle = document.querySelector('head link[id*="wp-admin-"], head link[id*="ga-admin-"]');
                newHeadStyles.forEach(function (el) {
                    if (el.tagName.toLowerCase() === 'link' && el.href) {
                        var baseUrl = el.href.split('?')[0];
                        var alreadyExists = Array.from(document.querySelectorAll('head link[rel="stylesheet"]')).some(function(link) {
                            return link.href.split('?')[0] === baseUrl;
                        });
                        if (!alreadyExists) {
                            var node = el.cloneNode(true);
                            if (firstOurStyle && firstOurStyle.parentNode) {
                                firstOurStyle.parentNode.insertBefore(node, firstOurStyle);
                            } else {
                                document.head.appendChild(node);
                            }
                        }
                    } else if (el.id && !document.getElementById(el.id)) {
                        var node = el.cloneNode(true);
                        if (firstOurStyle && firstOurStyle.parentNode) {
                            firstOurStyle.parentNode.insertBefore(node, firstOurStyle);
                        } else {
                            document.head.appendChild(node);
                        }
                    }
                });

                // Sync Head Scripts
                var newHeadScripts = doc.querySelectorAll('head script[src]');
                newHeadScripts.forEach(function (el) {
                    if (el.src && !document.querySelector('head script[src="' + el.src + '"]')) {
                        var s = document.createElement('script');
                        Array.from(el.attributes).forEach(function (attr) {
                            s.setAttribute(attr.name, attr.value);
                        });
                        s.src = el.src;
                        document.head.appendChild(s);
                    }
                });

                // 2. Replace HTML Content
                currContent.innerHTML = newContent.innerHTML;

                // 3. Re-execute Embedded Scripts inside #wpbody-content
                var scripts = currContent.querySelectorAll('script');
                scripts.forEach(function (oldScript) {
                    // Evitar re-executar scripts de configuração de estado do Admin UI
                    if (oldScript.id && oldScript.id.indexOf('wn-state') === 0) return;
                    var newScript = document.createElement('script');
                    Array.from(oldScript.attributes).forEach(function (attr) {
                        newScript.setAttribute(attr.name, attr.value);
                    });
                    if (oldScript.src) {
                        newScript.src = oldScript.src;
                    } else {
                        newScript.textContent = oldScript.textContent;
                    }
                    if (oldScript.parentNode) {
                        oldScript.parentNode.replaceChild(newScript, oldScript);
                    }
                });

                // 2. Update Document Title & Body Classes
                if (doc.title) {
                    document.title = doc.title;
                }

                if (doc.body && doc.body.className) {
                    var isWnActive = document.body.classList.contains('wn-active');
                    var isWnCollapsed = document.body.classList.contains('wn-collapsed');
                    document.body.className = doc.body.className.replace(/\bno-js\b/g, 'js');
                    if (isWnActive) document.body.classList.add('wn-active');
                    if (isWnCollapsed) document.body.classList.add('wn-collapsed');
                }

                // 3. Update Topbar Titles & Sidebar States
                updateTopbarBadge(doc);
                updateActiveState(finalUrl);

                // 4. Update History
                if (pushState !== false) {
                    history.pushState({ pjax: true, url: finalUrl }, '', finalUrl);
                }

                // 5. Scroll to top
                window.scrollTo(0, 0);

                // 6. Re-init Icons (Scoped only to new content so sidebar/topbar never flicker)
                if (window.lucide) {
                    try {
                        lucide.createIcons({ root: currContent });
                    } catch (e) {
                        lucide.createIcons();
                    }
                }

                // 7. Re-close mobile menu if open
                document.body.classList.remove('wn-mobile-open');

                // 8. Trigger events for re-initializing components & FAB
                document.dispatchEvent(new CustomEvent('wp-pjax-loaded', { detail: { url: finalUrl } }));
                if (window.jQuery) {
                    window.jQuery(document).trigger('wp-pjax-loaded', [finalUrl]);
                }


                hideLoader();
                isFetching = false;
            });
        })
        .catch(function (err) {
            console.warn('PJAX fetch failed, falling back to location reload:', err);
            hideLoader();
            isFetching = false;
            window.location.href = url;
        });
    }

    // ── Event Listeners ─────────────────────────────────────────────

    document.addEventListener('click', function (e) {
        var anchor = e.target.closest('a');
        if (isEligibleLink(anchor, e)) {
            // Excluir plugin-upload.php do PJAX
            if (anchor.href.indexOf('plugin-upload.php') !== -1) return;
            
            e.preventDefault();
            loadPage(anchor.href, true);
        }
    });

    window.addEventListener('popstate', function (e) {
        if (e.state && e.state.pjax) {
            loadPage(location.href, false);
        } else {
            loadPage(location.href, false);
        }
    });

    // Mostra o loader mesmo em navegações tradicionais ou submissão de forms
    window.addEventListener('beforeunload', function () {
        if (!isFetching) {
            showLoader();
        }
    });

    // Esconde o loader caso o browser restaure a página do cache (botão Voltar)
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) {
            hideLoader();
        }
    });

    // ── Upload Dropzone Enhancement ─────────────────────────────────
    // Transforma o form nativo .wp-upload-form numa dropzone customizada
    // criando os elementos .wn-file-label e .wn-file-name que o CSS espera.

    function initUploadDropzone() {
        var form = document.querySelector('body.wn-active .wp-upload-form');
        if (!form) return;
        // Evita inicializar duas vezes
        if (form.getAttribute('data-wn-dropzone')) return;
        form.setAttribute('data-wn-dropzone', '1');

        var fileInput = form.querySelector('input[type="file"]');
        var submitBtn = form.querySelector('input[type="submit"], button[type="submit"]');
        if (!fileInput) return;

        // Oculta o botão de submit nativo — será acionado pelo nosso botão customizado
        if (submitBtn) submitBtn.style.display = 'none';

        // Cria o botão customizado de escolha de ficheiro
        var label = document.createElement('button');
        label.type = 'button';
        label.className = 'wn-file-label';
        label.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg> Escolher ficheiro .zip';
        form.appendChild(label);

        // Cria o span para exibir o nome do ficheiro selecionado
        var fileNameEl = document.createElement('span');
        fileNameEl.className = 'wn-file-name';
        fileNameEl.textContent = '';
        form.appendChild(fileNameEl);

        // Clicar no botão customizado: se não tem ficheiro, abre o seletor; se tem, submete
        label.addEventListener('click', function () {
            if (label.classList.contains('wn-file-label--ready')) {
                // Submete o formulário
                if (submitBtn) {
                    submitBtn.click();
                } else {
                    form.submit();
                }
            } else {
                fileInput.click();
            }
        });

        // Quando um ficheiro é selecionado pelo input nativo
        fileInput.addEventListener('change', function () {
            if (fileInput.files && fileInput.files.length > 0) {
                var fileName = fileInput.files[0].name;
                fileNameEl.textContent = fileName;
                label.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> Instalar agora';
                label.classList.add('wn-file-label--ready');
                form.classList.remove('wn-dropzone--over');
            } else {
                fileNameEl.textContent = '';
                label.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg> Escolher ficheiro .zip';
                label.classList.remove('wn-file-label--ready');
            }
        });

        // Clicar em qualquer parte da dropzone (exceto botão) abre o seletor
        form.addEventListener('click', function (e) {
            if (e.target === form || e.target.classList.contains('install-help') || e.target.tagName === 'P') {
                if (!label.classList.contains('wn-file-label--ready')) {
                    fileInput.click();
                }
            }
        });

        // Drag & Drop
        form.addEventListener('dragenter', function (e) {
            e.preventDefault();
            form.classList.add('wn-dropzone--over');
        });
        form.addEventListener('dragover', function (e) {
            e.preventDefault();
            form.classList.add('wn-dropzone--over');
        });
        form.addEventListener('dragleave', function (e) {
            if (!form.contains(e.relatedTarget)) {
                form.classList.remove('wn-dropzone--over');
            }
        });
        form.addEventListener('drop', function (e) {
            e.preventDefault();
            form.classList.remove('wn-dropzone--over');
            var files = e.dataTransfer.files;
            if (files && files.length > 0) {
                // Injeta os ficheiros no input nativo via DataTransfer
                try {
                    var dt = new DataTransfer();
                    dt.items.add(files[0]);
                    fileInput.files = dt.files;
                    fileInput.dispatchEvent(new Event('change'));
                } catch (err) {
                    console.warn('DataTransfer not supported:', err);
                }
            }
        });
    }

    // Inicializa na carga normal
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initUploadDropzone);
    } else {
        initUploadDropzone();
    }

    // Reinicializa após PJAX
    if (window.jQuery) {
        window.jQuery(document).on('wp-pjax-loaded', function () {
            initUploadDropzone();
        });
    }

})();
