/**
 * Code Editor IDE - Lógica do Frontend (Suporte a Temas e Plugins)
 */

(function($) {
    'use strict';

    // Estado da IDE
    let editor = null;
    let currentType = (typeof ide_params !== 'undefined' && ide_params.current_type) ? ide_params.current_type : 'theme';
    let currentSlug = (typeof ide_params !== 'undefined' && ide_params.current_slug) ? ide_params.current_slug : '';
    let currentTargetName = (typeof ide_params !== 'undefined' && ide_params.target_name) ? ide_params.target_name : '';
    let currentTargetUrl = (typeof ide_params !== 'undefined' && ide_params.target_url) ? ide_params.target_url : '';
    let currentFilePath = null;
    let originalContent = '';
    let isModified = false;
    let filesTreeData = null;
    let fileCache = {};

    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            const context = this;
            clearTimeout(timeout);
            timeout = setTimeout(function() {
                func.apply(context, args);
            }, wait);
        };
    }

    // Configurações do Monaco Loader
    if (typeof require !== 'undefined') {
        require.config({ 
            paths: { 
                'vs': 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.39.0/min/vs' 
            } 
        });
    } else {
        console.error('Monaco Editor loader não pôde ser carregado.');
    }

    // Inicialização
    $(document).ready(function() {
        initIDE();
    });

    /**
     * Inicializa componentes e eventos da IDE
     */
    function initIDE() {
        // 1. Carregar Monaco Editor
        if (typeof require !== 'undefined') {
            require(['vs/editor/editor.main'], function() {
                editor = monaco.editor.create(document.getElementById('ide-monaco-editor'), {
                    value: '',
                    language: 'plaintext',
                    theme: 'vs',
                    automaticLayout: true,
                    fontSize: 14,
                    fontFamily: "'Fira Code', Consolas, Monaco, 'Courier New', monospace",
                    tabSize: 4,
                    minimap: { enabled: true },
                    lineHeight: 22,
                    roundedSelection: true,
                    scrollBeyondLastLine: false,
                    cursorBlinking: 'smooth',
                    cursorSmoothCaretAnimation: 'on',
                    scrollbar: {
                        vertical: 'auto',
                        horizontal: 'auto',
                        verticalScrollbarSize: 6,
                        horizontalScrollbarSize: 6,
                        arrowSize: 0,
                        useShadows: false
                    },
                    tabCompletion: 'on',
                    autoClosingTags: true,
                    autoClosingBrackets: 'always',
                    autoClosingQuotes: 'always',
                    suggestOnTriggerCharacters: true
                });

                const savedWordWrap = localStorage.getItem('ide_wordwrap') || 'off';
                editor.updateOptions({ wordWrap: savedWordWrap });
                if (savedWordWrap === 'on') {
                    $('#ide-wordwrap-button').addClass('active');
                }

                const debouncedCheckModification = debounce(checkModificationState, 300);
                editor.onDidChangeModelContent(function() {
                    debouncedCheckModification();
                });

                // Atalho Ctrl+S / Cmd+S
                editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyS, function() {
                    if (isModified && currentFilePath) {
                        saveCurrentFile();
                    }
                });

                // Inicializar visual do switcher
                syncSwitcherUI();

                // Carregar árvore inicial
                loadFilesTree();
            });
        }

        // 2. Eventos dos Botões da Interface
        $('#ide-undo-button').on('click', function() {
            if (editor && currentFilePath) {
                editor.focus();
                editor.trigger('source', 'undo', null);
            }
        });

        $('#ide-redo-button').on('click', function() {
            if (editor && currentFilePath) {
                editor.focus();
                editor.trigger('source', 'redo', null);
            }
        });

        $('#ide-wordwrap-button').on('click', function() {
            if (!editor) return;
            const $this = $(this);
            const isWrapping = $this.hasClass('active');
            if (isWrapping) {
                editor.updateOptions({ wordWrap: 'off' });
                $this.removeClass('active');
                localStorage.setItem('ide_wordwrap', 'off');
            } else {
                editor.updateOptions({ wordWrap: 'on' });
                $this.addClass('active');
                localStorage.setItem('ide_wordwrap', 'on');
            }
        });

        $('#ide-save-button').on('click', function() {
            if (currentFilePath && isModified) {
                saveCurrentFile();
            }
        });


        // Troca de Tema no Select
        $('#ide-theme-select').on('change', function() {
            if (isModified) {
                if (!confirm('Existem alterações não salvas. Deseja trocar de tema e descartá-las?')) {
                    $('#ide-theme-select').val(currentSlug);
                    return;
                }
            }
            currentSlug = $(this).val();
            resetEditorState();
            fileCache = {};
            loadFilesTree();
        });

        // Troca de Plugin no Select
        $('#ide-plugin-select').on('change', function() {
            if (isModified) {
                if (!confirm('Existem alterações não salvas. Deseja trocar de plugin e descartá-las?')) {
                    $('#ide-plugin-select').val(currentSlug);
                    return;
                }
            }
            currentSlug = $(this).val();
            resetEditorState();
            fileCache = {};
            loadFilesTree();
        });

        // Download ZIP (Tema ou Plugin)
        $('#ide-download-zip-button, #ide-download-theme-button').on('click', function() {
            const $btn    = $(this);
            const $label  = $btn.find('.ide-btn-label');
            const $loader = $btn.find('.ide-btn-loader');

            $btn.prop('disabled', true);
            $label.text('Gerando ZIP...');
            $loader.removeClass('is-hidden').show();

            const downloadUrl = ide_params.ajax_url
                + '?action=ide_download_zip'
                + '&type=' + encodeURIComponent(currentType)
                + '&slug=' + encodeURIComponent(currentSlug)
                + '&nonce=' + encodeURIComponent(ide_params.download_nonce);

            let $iframe = $('#ide-download-frame');
            if ($iframe.length === 0) {
                $iframe = $('<iframe>', { id: 'ide-download-frame', name: 'ide-download-frame' })
                    .addClass('is-hidden');
                $('body').append($iframe);
            }
            $iframe.attr('src', downloadUrl);

            setTimeout(function() {
                $btn.prop('disabled', false);
                $label.text('Download ZIP');
                $loader.addClass('is-hidden').hide();
            }, 4000);
        });

        // Atualizar árvore
        $('#ide-refresh-tree').on('click', function() {
            if (isModified) {
                if (!confirm('Existem alterações não salvas no arquivo atual. Deseja mesmo recarregar e perder as alterações?')) {
                    return;
                }
            }
            fileCache = {};
            loadFilesTree();
        });

        $(window).on('beforeunload', function() {
            if (isModified) {
                return 'Você tem alterações não salvas. Tem certeza que deseja sair?';
            }
        });

        // Menu Dropdown (More Options)
        $('#ide-more-btn').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $('#ide-sidebar-dropdown').toggleClass('open');
        });

        $(document).on('click', function() {
            $('#ide-sidebar-dropdown').removeClass('open');
        });

        $('#ide-sidebar-dropdown').on('click', function(e) {
            e.stopPropagation();
        });

        // Criar Novo Arquivo
        $('#ide-new-file-btn').on('click', function(e) {
            e.preventDefault();
            $('#ide-sidebar-dropdown').removeClass('open');

            if (isModified) {
                if (!confirm('Você tem alterações não salvas no arquivo atual. Deseja continuar assim mesmo?')) {
                    return;
                }
            }

            const baseDir = getSelectedDirectory();
            let promptMsg = currentType === 'plugin' 
                ? 'Digite o nome do arquivo a ser criado na raiz do plugin:'
                : 'Digite o nome do arquivo a ser criado na raiz do tema:';
            
            if (baseDir) {
                promptMsg = `Digite o nome do arquivo a ser criado dentro da pasta "${baseDir}":`;
            }

            const fileName = prompt(promptMsg);
            if (fileName === null || fileName.trim() === '') {
                return;
            }

            const fullPath = baseDir ? baseDir + '/' + fileName.trim() : fileName.trim();

            $.ajax({
                url: ide_params.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'ide_create_file',
                    type: currentType,
                    slug: currentSlug,
                    file_path: fullPath,
                    nonce: ide_params.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showToast(response.data.message, 'success');
                        delete fileCache[fullPath];
                        loadFilesTreeAndSelect(fullPath);
                    } else {
                        showToast('Erro ao criar arquivo: ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    showToast('Erro na requisição AJAX ao tentar criar arquivo.', 'error');
                }
            });
        });

        // Criar Nova Pasta
        $('#ide-new-folder-btn').on('click', function(e) {
            e.preventDefault();
            $('#ide-sidebar-dropdown').removeClass('open');

            const baseDir = getSelectedDirectory();
            let promptMsg = currentType === 'plugin'
                ? 'Digite o nome da pasta a ser criada na raiz do plugin:'
                : 'Digite o nome da pasta a ser criada na raiz do tema:';

            if (baseDir) {
                promptMsg = `Digite o nome da pasta a ser criada dentro de "${baseDir}":`;
            }

            const folderName = prompt(promptMsg);
            if (folderName === null || folderName.trim() === '') {
                return;
            }

            const fullPath = baseDir ? baseDir + '/' + folderName.trim() : folderName.trim();

            $.ajax({
                url: ide_params.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'ide_create_directory',
                    type: currentType,
                    slug: currentSlug,
                    dir_path: fullPath,
                    nonce: ide_params.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showToast(response.data.message, 'success');
                        loadFilesTree();
                    } else {
                        showToast('Erro ao criar pasta: ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    showToast('Erro na requisição AJAX ao tentar criar pasta.', 'error');
                }
            });
        });

        // Upload de Arquivos
        $('#ide-upload-file-btn').on('click', function(e) {
            e.preventDefault();
            if ($(this).hasClass('disabled')) {
                return false;
            }
            $('#ide-sidebar-dropdown').removeClass('open');
            $('#ide-file-uploader').trigger('click');
        });

        $('#ide-file-uploader').on('change', function() {
            const files = this.files;
            if (files.length === 0) {
                return;
            }

            const baseDir = getSelectedDirectory();
            const formData = new FormData();
            formData.append('action', 'ide_upload_file');
            formData.append('type', currentType);
            formData.append('slug', currentSlug);
            formData.append('nonce', ide_params.nonce);
            formData.append('destination_path', baseDir);

            for (let i = 0; i < files.length; i++) {
                formData.append('files[]', files[i]);
            }

            const $btn = $('#ide-upload-file-btn');
            $btn.addClass('disabled');
            showToast('Enviando arquivo(s)...', 'success');

            $.ajax({
                url: ide_params.ajax_url,
                type: 'POST',
                data: formData,
                cache: false,
                contentType: false,
                processData: false,
                success: function(response) {
                    $btn.removeClass('disabled');
                    $('#ide-file-uploader').val('');

                    if (response.success) {
                        showToast(response.data.message, 'success');
                        loadFilesTree();
                    } else {
                        showToast('Erro no upload: ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    $btn.removeClass('disabled');
                    $('#ide-file-uploader').val('');
                    showToast('Erro na requisição de upload.', 'error');
                }
            });
        });

        // Download de Arquivo Individual
        $('#ide-download-file-btn').on('click', function(e) {
            e.preventDefault();
            if ($(this).hasClass('disabled') || !currentFilePath) {
                return false;
            }
            $('#ide-sidebar-dropdown').removeClass('open');

            const downloadUrl = ide_params.ajax_url
                + '?action=ide_download_file'
                + '&type=' + encodeURIComponent(currentType)
                + '&slug=' + encodeURIComponent(currentSlug)
                + '&file_path=' + encodeURIComponent(currentFilePath)
                + '&nonce=' + encodeURIComponent(ide_params.nonce);

            let $iframe = $('#ide-download-frame');
            if ($iframe.length === 0) {
                $iframe = $('<iframe>', { id: 'ide-download-frame', name: 'ide-download-frame' })
                    .addClass('is-hidden');
                $('body').append($iframe);
            }
            $iframe.attr('src', downloadUrl);
        });

        // Redimensionamento
        $(window).on('resize', debounce(function() {
            if (editor) {
                editor.layout();
            }
        }, 100));
    }

    function syncSwitcherUI() {
        if (currentType === 'plugin') {
            $('.ide-type-theme').removeClass('active');
            $('.ide-type-plugin').addClass('active');
            $('.ide-theme-select-wrap, #ide-theme-select').addClass('is-hidden');
            $('.ide-plugin-select-wrap, #ide-plugin-select').removeClass('is-hidden');
            if (currentSlug) {
                $('#ide-plugin-select').val(currentSlug);
            } else {
                currentSlug = $('#ide-plugin-select').val();
            }
        } else {
            $('.ide-type-plugin').removeClass('active');
            $('.ide-type-theme').addClass('active');
            $('.ide-plugin-select-wrap, #ide-plugin-select').addClass('is-hidden');
            $('.ide-theme-select-wrap, #ide-theme-select').removeClass('is-hidden');
            if (currentSlug) {
                $('#ide-theme-select').val(currentSlug);
            } else {
                currentSlug = $('#ide-theme-select').val();
            }
        }
    }

    function resetEditorState() {
        currentFilePath = null;
        originalContent = '';
        isModified = false;
        $('#ide-monaco-editor').hide();
        $('#ide-image-preview').addClass('is-hidden').hide();
        $('#ide-welcome-screen').fadeIn(150);
        $('#ide-active-filename').text('Nenhum arquivo selecionado');
        $('#ide-modified-dot').addClass('is-hidden').hide();
        $('#ide-save-button').prop('disabled', true);
        $('#ide-undo-button').prop('disabled', true);
        $('#ide-redo-button').prop('disabled', true);
        $('#ide-download-file-btn').addClass('disabled');
    }

    /**
     * Carrega a árvore de arquivos via AJAX
     */
    function loadFilesTree() {
        const $treeContainer = $('#ide-files-tree');
        $treeContainer.html(`
            <div class="ide-tree-loading">
                <span class="ide-tree-spinner"></span>
                <span>Carregando arquivos...</span>
            </div>
        `);

        $.ajax({
            url: ide_params.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'ide_get_files',
                type: currentType,
                slug: currentSlug,
                nonce: ide_params.nonce
            },
            success: function(response) {
                if (response.success) {
                    filesTreeData = response.data.tree;
                    currentTargetName = response.data.target_name;
                    currentTargetUrl = response.data.target_url;

                    $('#ide-current-target-name').text(currentTargetName);
                    $('#ide-sidebar-header-title').text(currentType === 'plugin' ? 'Arquivos do Plugin' : 'Arquivos do Tema');

                    renderFilesTree(filesTreeData);
                } else {
                    $treeContainer.html(`<div class="ide-tree-loading ide-tree-error">Erro: ${response.data.message}</div>`);
                }
            },
            error: function() {
                $treeContainer.html('<div class="ide-tree-loading ide-tree-error">Erro na requisição AJAX ao carregar os arquivos.</div>');
            }
        });
    }

    /**
     * Renderiza a árvore de arquivos
     */
    function renderFilesTree(treeData) {
        const $treeContainer = $('#ide-files-tree');
        $treeContainer.empty();

        if (!treeData || treeData.length === 0) {
            $treeContainer.html('<div class="ide-tree-loading">Nenhum arquivo encontrado.</div>');
            return;
        }

        const $treeRoot = $('<div class="ide-tree-root"></div>');
        buildTreeHtml(treeData, $treeRoot, 0);
        $treeContainer.append($treeRoot);

        bindTreeEvents();
    }

    function buildTreeHtml(items, $parentEl, depth) {
        items.forEach(function(item) {
            const $itemRow = $('<a href="#" class="ide-tree-item"></a>')
                .attr('data-path', item.path)
                .attr('data-type', item.type);

            if (depth > 0) {
                const $indent = $('<span class="ide-tree-indent"></span>');
                $indent.attr('style', '--ide-indent-depth: ' + depth);
                $itemRow.append($indent);
            }

            if (item.type === 'directory') {
                $itemRow.addClass('ide-item-directory');
                $itemRow.append('<span class="ide-tree-toggle">▶</span>');
                $itemRow.append('<span class="ide-tree-icon ide-tree-folder-icon">📁</span>');
                $itemRow.append(`<span class="ide-item-name">${item.name}</span>`);
                
                $parentEl.append($itemRow);

                const $subTreeContainer = $('<div class="ide-tree-subtree"></div>');
                if (item.children && item.children.length > 0) {
                    buildTreeHtml(item.children, $subTreeContainer, depth + 1);
                }
                $parentEl.append($subTreeContainer);
            } else {
                $itemRow.addClass('ide-item-file');
                const ext = item.name.split('.').pop().toLowerCase();
                $itemRow.addClass(`ide-file-${ext}`);

                $itemRow.append('<span class="ide-tree-toggle ide-toggle-spacer">▶</span>');
                $itemRow.append('<span class="ide-tree-icon ide-tree-file-icon">📄</span>');
                $itemRow.append(`<span class="ide-item-name">${item.name}</span>`);

                const $deleteBtn = $('<button type="button" class="ide-tree-delete-btn" title="Excluir arquivo"></button>')
                    .html('<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>');
                $itemRow.append($deleteBtn);

                $parentEl.append($itemRow);
            }
        });
    }

    function bindTreeEvents() {
        $('.ide-item-directory').off('click').on('click', function(e) {
            e.preventDefault();
            const $this = $(this);
            const $toggle = $this.find('.ide-tree-toggle');
            const $subtree = $this.next('.ide-tree-subtree');

            if ($subtree.hasClass('open')) {
                $subtree.removeClass('open');
                $toggle.removeClass('open');
                $this.find('.ide-tree-folder-icon').text('📁');
            } else {
                $subtree.addClass('open');
                $toggle.addClass('open');
                $this.find('.ide-tree-folder-icon').text('📂');
            }
        });

        $('.ide-item-file').off('click').on('click', function(e) {
            e.preventDefault();
            const $this = $(this);
            const filePath = $this.data('path');

            if (filePath === currentFilePath) {
                return;
            }

            if (isModified) {
                if (!confirm('Você possui alterações não salvas no arquivo atual. Deseja descartar e abrir outro arquivo?')) {
                    return;
                }
            }

            $('.ide-tree-item').removeClass('active');
            $this.addClass('active');

            openFile(filePath);
        });
    }

    function setupEditorContent(filePath, content, fromCache) {
        currentFilePath = filePath;
        originalContent = content;
        isModified = false;

        $('#ide-image-preview').addClass('is-hidden').hide();

        const $welcome = $('#ide-welcome-screen');
        if ($welcome.is(':visible')) {
            $welcome.fadeOut(fromCache ? 0 : 150, function() {
                if (editor) {
                    editor.layout();
                }
            });
        }
        
        $('#ide-monaco-editor').show();
        editor.setValue(originalContent);

        if (editor) {
            editor.layout();
        }

        if (!fromCache) {
            setTimeout(function() {
                if (editor) {
                    editor.layout();
                }
            }, 50);
        }

        const language = getLanguageByExtension(filePath);
        const model = editor.getModel();
        monaco.editor.setModelLanguage(model, language);

        $('#ide-active-filename').text(filePath);
        $('#ide-modified-dot').addClass('is-hidden').hide();
        $('#ide-save-button').prop('disabled', true);
        $('#ide-undo-button').prop('disabled', false);
        $('#ide-redo-button').prop('disabled', false);
        $('#ide-download-file-btn').removeClass('disabled');
    }

    function isImageExtension(filePath) {
        if (!filePath) return false;
        const ext = filePath.split('.').pop().toLowerCase();
        const imageExtensions = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico'];
        return imageExtensions.indexOf(ext) !== -1;
    }

    function setupImagePreview(filePath) {
        currentFilePath = filePath;
        originalContent = '';
        isModified = false;

        const imageUrl = currentTargetUrl + '/' + filePath;

        $('#ide-monaco-editor').hide();
        $('#ide-welcome-screen').hide();

        $('#ide-image-dimensions').text('Carregando...');
        $('#ide-image-path').text(filePath);

        const $img = $('#ide-preview-img');
        $img.off('load').on('load', function() {
            const width = this.naturalWidth;
            const height = this.naturalHeight;
            $('#ide-image-dimensions').text(width + ' × ' + height + ' px');
        });
        
        $img.off('error').on('error', function() {
            $('#ide-image-dimensions').text('Erro ao carregar imagem');
        });

        $img.attr('src', imageUrl);
        $('#ide-image-preview').removeClass('is-hidden').fadeIn(150);

        $('#ide-active-filename').text(filePath);
        $('#ide-modified-dot').addClass('is-hidden').hide();
        $('#ide-save-button').prop('disabled', true);
        $('#ide-undo-button').prop('disabled', true);
        $('#ide-redo-button').prop('disabled', true);
        $('#ide-download-file-btn').removeClass('disabled');
    }

    function openFile(filePath) {
        if (isImageExtension(filePath)) {
            setupImagePreview(filePath);
            return;
        }

        $('#ide-image-preview').addClass('is-hidden').hide();

        if (fileCache[filePath] !== undefined) {
            setupEditorContent(filePath, fileCache[filePath], true);
            return;
        }

        showLoadingState(true);

        $.ajax({
            url: ide_params.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'ide_get_file_content',
                type: currentType,
                slug: currentSlug,
                file_path: filePath,
                nonce: ide_params.nonce
            },
            success: function(response) {
                showLoadingState(false);
                if (response.success) {
                    fileCache[filePath] = response.data.content;
                    setupEditorContent(filePath, response.data.content, false);
                } else {
                    showToast('Erro ao ler arquivo: ' + response.data.message, 'error');
                }
            },
            error: function() {
                showLoadingState(false);
                showToast('Erro na requisição AJAX ao tentar abrir o arquivo.', 'error');
            }
        });
    }

    function saveCurrentFile() {
        if (!currentFilePath) return;

        const newContent = editor.getValue();
        const $btn = $('#ide-save-button');
        const $label = $btn.find('.ide-btn-label');
        const $loader = $btn.find('.ide-btn-loader');

        $btn.prop('disabled', true);
        $label.text('Salvando...');
        $loader.removeClass('is-hidden').show();

        $.ajax({
            url: ide_params.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'ide_save_file',
                type: currentType,
                slug: currentSlug,
                file_path: currentFilePath,
                content: newContent,
                nonce: ide_params.nonce
            },
            success: function(response) {
                $label.text('Salvar Alterações');
                $loader.addClass('is-hidden').hide();

                if (response.success) {
                    originalContent = newContent;
                    fileCache[currentFilePath] = newContent;
                    isModified = false;
                    checkModificationState();
                    showToast('Arquivo salvo com sucesso!', 'success');
                } else {
                    $btn.prop('disabled', false);
                    showToast('Erro ao salvar: ' + response.data.message, 'error');
                }
            },
            error: function() {
                $label.text('Salvar Alterações');
                $loader.addClass('is-hidden').hide();
                $btn.prop('disabled', false);
                showToast('Erro de rede ou permissão ao salvar o arquivo.', 'error');
            }
        });
    }

    function checkModificationState() {
        if (!editor || !currentFilePath) return;

        const currentValue = editor.getValue();
        if (currentValue !== originalContent) {
            isModified = true;
            $('#ide-modified-dot').removeClass('is-hidden').show();
            $('#ide-save-button').prop('disabled', false);
        } else {
            isModified = false;
            $('#ide-modified-dot').addClass('is-hidden').hide();
            $('#ide-save-button').prop('disabled', true);
        }
    }

    function getLanguageByExtension(filePath) {
        const ext = filePath.split('.').pop().toLowerCase();
        switch(ext) {
            case 'php':
                return 'php';
            case 'css':
            case 'scss':
            case 'less':
                return 'css';
            case 'js':
            case 'jsx':
            case 'ts':
            case 'tsx':
                return 'javascript';
            case 'json':
                return 'json';
            case 'html':
            case 'htm':
                return 'html';
            case 'md':
                return 'markdown';
            case 'xml':
            case 'svg':
                return 'xml';
            case 'sql':
                return 'sql';
            default:
                return 'plaintext';
        }
    }

    function showLoadingState(isLoading) {
        if (isLoading) {
            $('#ide-monaco-editor').addClass('ide-loading-state');
        } else {
            $('#ide-monaco-editor').removeClass('ide-loading-state');
        }
    }

    function showToast(message, type) {
        $('.ide-toast').remove();

        const toastClass = type === 'success' ? 'ide-toast-success' : 'ide-toast-error';
        const $toast = $(`<div class="ide-toast ${toastClass}">${message}</div>`);
        
        $('body').append($toast);
        
        setTimeout(function() {
            $toast.addClass('show');
        }, 10);

        setTimeout(function() {
            $toast.removeClass('show');
            setTimeout(function() {
                $toast.remove();
            }, 300);
        }, 3500);
    }

    function getSelectedDirectory() {
        const $activeItem = $('.ide-tree-item.active');
        if ($activeItem.length === 0) {
            return '';
        }

        const type = $activeItem.attr('data-type');
        const path = $activeItem.attr('data-path');

        if (type === 'directory') {
            return path;
        } else {
            if (path.indexOf('/') === -1) {
                return '';
            }
            return path.substring(0, path.lastIndexOf('/'));
        }
    }

    function loadFilesTreeAndSelect(selectPath) {
        const $treeContainer = $('#ide-files-tree');
        $treeContainer.html(`
            <div class="ide-tree-loading">
                <span class="ide-tree-spinner"></span>
                <span>Carregando arquivos...</span>
            </div>
        `);

        $.ajax({
            url: ide_params.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'ide_get_files',
                type: currentType,
                slug: currentSlug,
                nonce: ide_params.nonce
            },
            success: function(response) {
                if (response.success) {
                    filesTreeData = response.data.tree;
                    renderFilesTree(filesTreeData);
                    
                    if (selectPath) {
                        expandParentDirectories(selectPath);

                        const $target = $(`.ide-tree-item[data-path="${selectPath}"]`);
                        if ($target.length > 0) {
                            $('.ide-tree-item').removeClass('active');
                            $target.addClass('active');
                            openFile(selectPath);
                        }
                    }
                } else {
                    $treeContainer.html(`<div class="ide-tree-loading ide-tree-error">Erro: ${response.data.message}</div>`);
                }
            },
            error: function() {
                $treeContainer.html('<div class="ide-tree-loading ide-tree-error">Erro na requisição AJAX ao carregar os arquivos.</div>');
            }
        });
    }

    function expandParentDirectories(filePath) {
        if (!filePath || filePath.indexOf('/') === -1) {
            return;
        }

        const parts = filePath.split('/');
        let currentPath = '';

        for (let i = 0; i < parts.length - 1; i++) {
            currentPath = currentPath ? currentPath + '/' + parts[i] : parts[i];
            const $dirItem = $(`.ide-item-directory[data-path="${currentPath}"]`);
            if ($dirItem.length > 0) {
                const $subtree = $dirItem.next('.ide-tree-subtree');
                const $toggle = $dirItem.find('.ide-tree-toggle');
                
                if (!$subtree.hasClass('open')) {
                    $subtree.addClass('open');
                    $toggle.addClass('open');
                    $dirItem.find('.ide-tree-folder-icon').text('📂');
                }
            }
        }
    }

    // Modal de Exclusão
    $(document).on('click', '.ide-tree-delete-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();

        const $item = $(this).closest('.ide-tree-item');
        const filePath = $item.data('path');

        $('#ide-delete-file-display').text(filePath);
        $('#ide-delete-confirm-btn').data('path', filePath);
        $('#ide-delete-modal').removeClass('is-hidden').fadeIn(150);
    });

    $('#ide-delete-cancel-btn').on('click', function() {
        $('#ide-delete-modal').fadeOut(150, function() {
            $(this).addClass('is-hidden');
        });
    });

    $(document).on('click', '#ide-delete-modal', function(e) {
        if ($(e.target).hasClass('ide-modal-overlay')) {
            $(this).fadeOut(150, function() {
                $(this).addClass('is-hidden');
            });
        }
    });

    $('#ide-delete-confirm-btn').on('click', function() {
        const filePath = $(this).data('path');
        if (!filePath) return;

        const $btn = $(this);
        $btn.prop('disabled', true).text('Excluindo...');

        $.ajax({
            url: ide_params.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'ide_delete_file',
                type: currentType,
                slug: currentSlug,
                file_path: filePath,
                nonce: ide_params.nonce
            },
            success: function(response) {
                $btn.prop('disabled', false).text('Excluir');
                $('#ide-delete-modal').fadeOut(150, function() {
                    $(this).addClass('is-hidden');
                });

                if (response.success) {
                    showToast(response.data.message, 'success');

                    if (filePath === currentFilePath) {
                        resetEditorState();
                    }

                    delete fileCache[filePath];
                    loadFilesTree();
                } else {
                    showToast('Erro ao excluir: ' + response.data.message, 'error');
                }
            },
            error: function() {
                $btn.prop('disabled', false).text('Excluir');
                $('#ide-delete-modal').fadeOut(150, function() {
                    $(this).addClass('is-hidden');
                });
                showToast('Erro de rede ou permissão na requisição de exclusão.', 'error');
            }
        });
    });

})(jQuery);
