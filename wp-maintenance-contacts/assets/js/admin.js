/* Lógica do Color Picker Customizado (Design do Paper - Atualizado com 3 Sliders) */

jQuery(document).ready(function($) {
    
    // --- FUNÇÕES AUXILIARES DE CONVERSÃO DE CORES ---
    
    // Converte Hex (6 ou 8 dígitos) ou RGB/RGBA para objeto { hex: '#RRGGBB', opacity: 0-100 }
    function parseColor(colorString) {
        var hex = '#7F56D9';
        var opacity = 100;
        
        if (!colorString) {
            return { hex: hex, opacity: opacity };
        }
        
        colorString = colorString.trim();
        
        if (colorString.indexOf('#') === 0) {
            if (colorString.length === 9) { // #RRGGBBAA
                hex = colorString.substring(0, 7);
                var alphaHex = colorString.substring(7, 9);
                opacity = Math.round(parseInt(alphaHex, 16) / 2.55);
            } else {
                hex = colorString;
                opacity = 100;
            }
        } else if (colorString.indexOf('rgba') === 0) {
            var parts = colorString.match(/rgba\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*([\d.]+)\s*\)/);
            if (parts) {
                var r = parseInt(parts[1]).toString(16);
                var g = parseInt(parts[2]).toString(16);
                var b = parseInt(parts[3]).toString(16);
                var a = parseFloat(parts[4]);
                
                r = r.length === 1 ? '0' + r : r;
                g = g.length === 1 ? '0' + g : g;
                b = b.length === 1 ? '0' + b : b;
                
                hex = '#' + r + g + b;
                opacity = Math.round(a * 100);
            }
        } else if (colorString.indexOf('rgb') === 0) {
            var parts = colorString.match(/rgb\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)/);
            if (parts) {
                var r = parseInt(parts[1]).toString(16);
                var g = parseInt(parts[2]).toString(16);
                var b = parseInt(parts[3]).toString(16);
                
                r = r.length === 1 ? '0' + r : r;
                g = g.length === 1 ? '0' + g : g;
                b = b.length === 1 ? '0' + b : b;
                
                hex = '#' + r + g + b;
                opacity = 100;
            }
        }
        
        return {
            hex: hex.toUpperCase(),
            opacity: opacity
        };
    }
    
    // Converte Hex de 6 dígitos para RGB { r, g, b }
    function hexToRgb(hex) {
        hex = hex.replace(/^#/, '');
        if (hex.length === 3) {
            hex = hex[0]+hex[0]+hex[1]+hex[1]+hex[2]+hex[2];
        }
        var r = parseInt(hex.substring(0, 2), 16);
        var g = parseInt(hex.substring(2, 4), 16);
        var b = parseInt(hex.substring(4, 6), 16);
        return { r: r, g: g, b: b };
    }
    
    // Adiciona opacidade (0-100) a um Hex de 6 dígitos, retornando Hex de 8 dígitos ou de 6 se for 100
    function addAlphaToHex(hex, opacityPercent) {
        hex = hex.toUpperCase();
        if (opacityPercent >= 100) {
            return hex;
        }
        var alpha = Math.round(opacityPercent * 2.55).toString(16);
        if (alpha.length === 1) {
            alpha = '0' + alpha;
        }
        return hex + alpha.toUpperCase();
    }
    
    // Converte Hex para HSL { h: 0-360, s: 0-100, l: 0-100 }
    function hexToHsl(hex) {
        hex = hex.replace(/^#/, '');
        if (hex.length === 3) {
            hex = hex[0]+hex[0]+hex[1]+hex[1]+hex[2]+hex[2];
        }
        
        var r = parseInt(hex.substring(0, 2), 16) / 255;
        var g = parseInt(hex.substring(2, 4), 16) / 255;
        var b = parseInt(hex.substring(4, 6), 16) / 255;
        
        var max = Math.max(r, g, b), min = Math.min(r, g, b);
        var h, s, l = (max + min) / 2;

        if (max == min) {
            h = s = 0; // acromático
        } else {
            var d = max - min;
            s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
            switch (max) {
                case r: h = (g - b) / d + (g < b ? 6 : 0); break;
                case g: h = (b - r) / d + 2; break;
                case b: h = (r - g) / d + 4; break;
            }
            h /= 6;
        }

        return {
            h: Math.round(h * 360),
            s: Math.round(s * 100),
            l: Math.round(l * 100)
        };
    }
    
    // Converte HSL para Hex de 6 dígitos
    function hslToHex(h, s, l) {
        h /= 360;
        s /= 100;
        l /= 100;
        var r, g, b;

        if (s == 0) {
            r = g = b = l; // acromático
        } else {
            function hue2rgb(p, q, t) {
                if (t < 0) t += 1;
                if (t > 1) t -= 1;
                if (t < 1/6) return p + (q - p) * 6 * t;
                if (t < 1/2) return q;
                if (t < 2/3) return p + (q - p) * (2/3 - t) * 6;
                return p;
            }

            var q = l < 0.5 ? l * (1 + s) : l + s - l * s;
            var p = 2 * l - q;
            r = hue2rgb(p, q, h + 1/3);
            g = hue2rgb(p, q, h);
            b = hue2rgb(p, q, h - 1/3);
        }

        var toHex = function(x) {
            var hex = Math.round(x * 255).toString(16);
            return hex.length === 1 ? '0' + hex : hex;
        };

        return '#' + toHex(r) + toHex(g) + toHex(b);
    }

    // --- INICIALIZAÇÃO DOS COLOR PICKERS ---
    
    function initColorPickers() {
        $('.wpmc-color-picker').each(function() {
            var $input = $(this);
            var $wrapper = $input.closest('.wpmc-picker-wrapper');
            
            if (!$wrapper.length) {
                $input.wrap('<div class="wpmc-picker-wrapper"></div>');
                $wrapper = $input.parent();
            }

            var $previewBtn = $wrapper.find('.wpmc-picker-preview-button');
            if (!$previewBtn.length) {
                $previewBtn = $('<div class="wpmc-picker-preview-button"></div>');
                $wrapper.prepend($previewBtn);
            }

            // Se o popover já foi criado para este wrapper, não duplica
            if ($wrapper.find('.wpmc-paper-picker').length) {
                return;
            }

            var initialRawColor = $input.val() || '#7F56D9';
        
            // Processa a cor inicial
            var parsed = parseColor(initialRawColor);
            var currentHex = parsed.hex;       // ex: '#7F56D9' (sempre 6 dígitos)
            var currentOpacity = parsed.opacity; // ex: 100
            
            // Calcula HSL inicial
            var hsl = hexToHsl(currentHex);
            var currentH = hsl.h;
            var currentS = hsl.s;
            var currentL = hsl.l;
            
            var initialCombined = addAlphaToHex(currentHex, currentOpacity);
            $previewBtn.css('background-color', initialCombined);
            $input.attr('readonly', true);
        
        // Monta o HTML do popover conforme o design de 3 sliders
        var hexNoHash = currentHex.replace('#', '');
        var popoverHtml = `
            <div class="wpmc-paper-picker" style="display: none;">
                <!-- Cabeçalho (Preview Grande - Sem Input de Cor Nativo) -->
                <div class="wpmc-picker-header" style="background-color: ${currentHex};">
                    <span class="wpmc-hash">#</span>
                    <span class="wpmc-hex-text">${hexNoHash}</span>
                </div>
                
                <!-- Corpo -->
                <div class="wpmc-picker-body">
                    <!-- Grade de cores rápidas (Swatches) -->
                    <div class="wpmc-swatches-grid">
                        <div class="wpmc-swatch" data-color="#16A34A" style="background-color: #16A34A;"></div>
                        <div class="wpmc-swatch" data-color="#059669" style="background-color: #059669;"></div>
                        <div class="wpmc-swatch" data-color="#0284C7" style="background-color: #0284C7;"></div>
                        <div class="wpmc-swatch" data-color="#2563EB" style="background-color: #2563EB;"></div>
                        <div class="wpmc-swatch" data-color="#4F46E5" style="background-color: #4F46E5;"></div>
                        <div class="wpmc-swatch" data-color="#9333EA" style="background-color: #9333EA;"></div>
                        <div class="wpmc-swatch" data-color="#C026D3" style="background-color: #C026D3;"></div>
                        <div class="wpmc-swatch" data-color="#DB2777" style="background-color: #DB2777;"></div>
                        <div class="wpmc-swatch" data-color="#DC2626" style="background-color: #DC2626;"></div>
                        <div class="wpmc-swatch" data-color="#EA580C" style="background-color: #EA580C;"></div>
                        <div class="wpmc-swatch" data-color="#D97706" style="background-color: #D97706;"></div>
                        <div class="wpmc-swatch" data-color="#CA8A04" style="background-color: #CA8A04;"></div>
                    </div>
                    
                    <!-- Linha de Controles (Conta-gotas + 3 Sliders) -->
                    <div class="wpmc-controls-row">
                        <!-- Conta-gotas -->
                        <button type="button" class="wpmc-eyedropper-btn">
                            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="m10.5 6.5 7 7M2 22s4.5-.5 7-3L21 7a2.828 2.828 0 1 0-4-4L5 15c-2.5 2.5-3 7-3 7Z" fill="none" stroke="#a3a3a3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                        
                        <!-- Sliders -->
                        <div class="wpmc-sliders-col">
                            <!-- Slider 1: Cor (Matiz/Hue) -->
                            <div class="wpmc-slider-wrapper">
                                <input type="range" min="0" max="360" value="${currentH}" class="wpmc-hue-slider" title="Cor (Matiz)" />
                            </div>
                            <!-- Slider 2: Iluminação (Luminosidade/Lightness) -->
                            <div class="wpmc-slider-wrapper">
                                <input type="range" min="0" max="100" value="${currentL}" class="wpmc-lightness-slider" title="Iluminação" />
                            </div>
                            <!-- Slider 3: Transparência (Opacidade) -->
                            <div class="wpmc-slider-wrapper">
                                <input type="range" min="0" max="100" value="${currentOpacity}" class="wpmc-opacity-slider" title="Transparência" />
                            </div>
                        </div>
                    </div>
                    
                    <!-- Campo de Entrada Inferior (Digitável) -->
                    <div class="wpmc-input-row">
                        <div class="wpmc-input-hex-wrapper">
                            <div class="wpmc-mini-preview" style="background-color: ${initialCombined};"></div>
                            <input type="text" class="wpmc-input-hex-val" value="${initialCombined}" maxlength="9" />
                        </div>
                        <div class="wpmc-opacity-box">${currentOpacity}%</div>
                    </div>
                </div>
            </div>
        `;
        
        var $popover = $(popoverHtml);
        $wrapper.append($popover);
        
        // Seletores de elementos internos
        var $header = $popover.find('.wpmc-picker-header');
        var $hexText = $popover.find('.wpmc-hex-text');
        var $hueSlider = $popover.find('.wpmc-hue-slider');
        var $lightnessSlider = $popover.find('.wpmc-lightness-slider');
        var $opacitySlider = $popover.find('.wpmc-opacity-slider');
        var $eyedropperBtn = $popover.find('.wpmc-eyedropper-btn');
        var $miniPreview = $popover.find('.wpmc-mini-preview');
        var $hexValInput = $popover.find('.wpmc-input-hex-val');
        var $opacityBox = $popover.find('.wpmc-opacity-box');
        
        // --- FUNÇÃO DE ATUALIZAÇÃO DA UI ---
        
        function updatePicker(hexColor, opacityVal, origin) {
            currentHex = hexColor.toUpperCase();
            currentOpacity = parseInt(opacityVal);
            
            // Cor combinada final (com ou sem opacidade alfa)
            var combinedColor = addAlphaToHex(currentHex, currentOpacity);
            
            // 1. Atualizar o input real que salva no banco
            $input.val(combinedColor);
            $input.trigger('change');
            
            // 2. Atualizar elementos visuais externos
            $previewBtn.css('background-color', combinedColor);
            
            // 3. Atualizar elementos do cabeçalho
            $header.css('background-color', currentHex);
            $hexText.text(currentHex.replace('#', ''));
            
            // 4. Atualizar mini-preview e caixa de opacidade
            $miniPreview.css('background-color', combinedColor);
            $opacityBox.text(currentOpacity + '%');
            
            // 5. Atualizar o input de texto inferior (se a origem não for a digitação direta)
            if (origin !== 'input-hex') {
                $hexValInput.val(combinedColor);
            }
            
            // 6. Recalcular e sincronizar os 3 Sliders
            var hslCurrent = hexToHsl(currentHex);
            
            // Tratamento especial para preservar H e S em tons sem cor (como preto/branco/cinzas)
            if (hslCurrent.s === 0) {
                if (origin === 'hue-slider') {
                    currentH = parseInt($hueSlider.val());
                }
                if (origin === 'lightness-slider') {
                    currentL = parseInt($lightnessSlider.val());
                } else {
                    currentL = hslCurrent.l;
                }
            } else {
                currentH = hslCurrent.h;
                currentS = hslCurrent.s;
                currentL = hslCurrent.l;
            }
            
            // --- Slider 1: Hue (Matiz) ---
            var pureHueColor = hslToHex(currentH, 100, 50);
            $hueSlider.css('--wpmc-hue-thumb', pureHueColor);
            if (origin !== 'hue-slider') {
                $hueSlider.val(currentH);
            }
            
            // --- Slider 2: Lightness (Iluminação) ---
            // O background exibe gradiente de preto -> cor pura no Hue atual -> branco
            var midColor = hslToHex(currentH, currentS, 50);
            var lightGrad = `linear-gradient(to right, #000000 0%, ${midColor} 50%, #ffffff 100%)`;
            $lightnessSlider.css('background', lightGrad);
            
            var currentLightColor = hslToHex(currentH, currentS, currentL);
            $lightnessSlider.css('--wpmc-light-thumb', currentLightColor);
            if (origin !== 'lightness-slider') {
                $lightnessSlider.val(currentL);
            }
            
            // --- Slider 3: Opacity (Transparência) ---
            var rgb = hexToRgb(currentHex);
            var opacityGrad = `linear-gradient(to right, rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, 0) 0%, rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, 1) 100%)`;
            $opacitySlider.css('background', opacityGrad);
            $opacitySlider.css('--wpmc-opacity-thumb', combinedColor);
            if (origin !== 'opacity-slider') {
                $opacitySlider.val(currentOpacity);
            }
        }
        
        // Inicializa as cores na primeira execução
        updatePicker(currentHex, currentOpacity, 'init');
        
        // --- EVENTOS DO POPOVER ---

        // Posiciona o popover na viewport (imune ao overflow:hidden dos cards)
        function togglePicker() {
            $('.wpmc-paper-picker').not($popover).fadeOut(150);

            if ($popover.is(':visible')) {
                $popover.fadeOut(150);
                return;
            }

            // Mostra o popover invisível para medir dimensões reais
            $popover.css({ visibility: 'hidden', display: 'block', opacity: 1 });

            var rect = $previewBtn[0].getBoundingClientRect();
            var w = $popover.outerWidth();
            var h = $popover.outerHeight();
            var left = rect.left;
            var top = rect.bottom + 8;

            if (top + h > window.innerHeight - 12) {
                top = rect.top - h - 8;
                if (top < 12) top = 12;
            }
            if (left + w > window.innerWidth - 12) {
                left = window.innerWidth - w - 12;
            }
            if (left < 12) left = 12;

            $popover.css({ visibility: 'visible', left: left, top: top });
            $popover.fadeIn(150);
        }

        // Abrir/Fechar popover
        $previewBtn.on('click', function(e) {
            e.stopPropagation();
            togglePicker();
        });
        
        $input.on('click', function(e) {
            e.stopPropagation();
            togglePicker();
        });
        
        $popover.on('click', function(e) {
            e.stopPropagation();
        });
        
        // Clique em swatches rápidas (reseta a opacidade para 100%)
        $popover.find('.wpmc-swatch').on('click', function() {
            var color = $(this).data('color');
            updatePicker(color, 100, 'swatch');
        });
        
        // Slider 1: Matiz (Hue)
        $hueSlider.on('input change', function() {
            var h = parseInt($(this).val());
            currentH = h;
            var s = currentS === 0 ? 80 : currentS;
            var l = currentL === 0 || currentL === 100 ? 50 : currentL;
            
            var newHex = hslToHex(h, s, l);
            updatePicker(newHex, currentOpacity, 'hue-slider');
        });
        
        // Slider 2: Iluminação (Lightness)
        $lightnessSlider.on('input change', function() {
            var l = parseInt($(this).val());
            currentL = l;
            var s = currentS === 0 ? 80 : currentS;
            
            var newHex = hslToHex(currentH, s, l);
            updatePicker(newHex, currentOpacity, 'lightness-slider');
        });
        
        // Slider 3: Transparência (Opacidade)
        $opacitySlider.on('input change', function() {
            var opacity = $(this).val();
            updatePicker(currentHex, opacity, 'opacity-slider');
        });
        
        // Permite digitar o hexadecimal no input inferior
        $hexValInput.on('input', function() {
            var val = $(this).val().trim();
            
            // Permite que o usuário digite sem travar imediatamente se estiver incompleto
            // Mas valida quando for um formato hex de 3, 6 ou 8 caracteres válido
            if (val.indexOf('#') !== 0) {
                val = '#' + val;
            }
            
            // Regex para validar hex: #RGB, #RRGGBB, #RRGGBBAA
            var isValidHex = /^#([0-9A-F]{3}|[0-9A-F]{6}|[0-9A-F]{8})$/i.test(val);
            if (isValidHex) {
                var picked = parseColor(val);
                updatePicker(picked.hex, picked.opacity, 'input-hex');
            }
        });
        
        // Impede que clicar dentro do input de texto inferior feche o popover
        $hexValInput.on('click', function(e) {
            e.stopPropagation();
        });
        
        // API EyeDropper (Conta-gotas nativo da tela se disponível no navegador)
        $eyedropperBtn.on('click', function() {
            if (window.EyeDropper) {
                var eyeDropper = new EyeDropper();
                eyeDropper.open().then(function(result) {
                    var picked = parseColor(result.sRGBHex);
                    updatePicker(picked.hex, picked.opacity, 'eyedropper');
                }).catch(function(err) {
                    console.log('EyeDropper cancelado ou erro:', err);
                });
            } else {
                alert('O conta-gotas de tela não é suportado pelo seu navegador atual. Use o seletor visual.');
            }
        });
        });
    }

    // Inicializa os seletores de cor na carga inicial
    initColorPickers();
    
    // Fechar seletores abertos ao clicar fora
    $(document).on('click', function() {
        $('.wpmc-paper-picker').fadeOut(150);
    });

    // Fechar seletores abertos ao rolar/redimensionar a janela
    $(window).on('scroll resize', function() {
        $('.wpmc-paper-picker').fadeOut(150);
    });

    // --- BOTÃO "Adicionar meu IP" (event delegation para compatibilidade PJAX) ---
    $(document).on('click', '#wpmc-add-my-ip', function() {
        var $btn      = $(this);
        var ip        = $btn.data('ip');
        var $textarea = $('#wpmc-allowed-ips');
        var current   = $textarea.val().trim();

        // Verifica se o IP já está na lista
        var lines = current.length > 0 ? current.split('\n') : [];
        var exists = lines.some(function(line) {
            return line.trim() === ip;
        });

        if (exists) {
            $btn.text('\u2713 IP já está na lista');
            $btn.addClass('wpmc-btn-success');
        } else {
            // Adiciona o IP à textarea
            $textarea.val(current.length > 0 ? current + '\n' + ip : ip);
            $btn.text('\u2713 IP adicionado!');
            $btn.addClass('wpmc-btn-success');
        }

        // Reverte o botão após 2.5s
        setTimeout(function() {
            $btn.text('+ Adicionar meu IP à lista');
            $btn.removeClass('wpmc-btn-success');
        }, 2500);
    });

    // --- DETECÇÃO DO IP ATUAL (com fallback para API pública) ---
    function wpmc_detect_and_show_ip() {
        var $ipCard    = $('.wpmc-ip-current-card');
        var $ipValue   = $ipCard.find('.wpmc-ip-current-value');
        var $addBtn    = $ipCard.find('#wpmc-add-my-ip');
        if (!$ipCard.length) return;

        var serverIp = (typeof wpmcData !== 'undefined') ? wpmcData.serverIp : '';
        var isLocal  = !serverIp || serverIp === '127.0.0.1' || serverIp === '::1' || serverIp === 'localhost';

        if (!isLocal) {
            // IP do servidor já é válido — apenas garante que o botão tem o IP correto
            $addBtn.data('ip', serverIp);
            return;
        }

        // IP local detectado — tentar obter IP público via API externa
        $ipValue.text('Detectando...');
        $.getJSON('https://api.ipify.org?format=json', function(data) {
            if (data && data.ip) {
                $ipValue.text(data.ip);
                $addBtn.data('ip', data.ip).show();
            }
        }).fail(function() {
            // Fallback: tentar outra API
            $.getJSON('https://api64.ipify.org?format=json', function(data) {
                if (data && data.ip) {
                    $ipValue.text(data.ip);
                    $addBtn.data('ip', data.ip).show();
                } else {
                    $ipValue.text('Não detectado');
                    $addBtn.hide();
                }
            }).fail(function() {
                $ipValue.text('Não detectado');
                $addBtn.hide();
            });
        });
    }
    wpmc_detect_and_show_ip();

    // --- FUNÇÃO PARA ATUALIZAR O PREVIEW EM TEMPO REAL ---
    function updateLivePreview() {
        var font          = $('select[name="wpmc_font_family"]').val() || 'Outfit';
        var bgStart       = $('input[name="wpmc_bg_start"]').val() || '#0E2E3F';
        var bgEnd         = $('input[name="wpmc_bg_end"]').val() || '#081C27';
        var cardBg        = $('input[name="wpmc_card_bg"]').val() || 'rgba(15, 23, 42, 0.55)';
        var textPrimary   = $('input[name="wpmc_text_primary"]').val() || '#ffffff';
        var textSecondary = $('input[name="wpmc_text_secondary"]').val() || '#94a3b8';
        var accentColor   = $('input[name="wpmc_accent_color"]').val() || '#6366f1';

        // Carrega dinamicamente a fonte do Google Fonts no admin se necessário
        loadFontInAdmin(font);

        // Aplica as variáveis CSS na página de preview simulada
        var $previewPage = $('.wpmc-preview-page');
        if ($previewPage.length) {
            $previewPage.css({
                '--wpmc-font-family': font,
                '--wpmc-bg-start': bgStart,
                '--wpmc-bg-end': bgEnd,
                '--wpmc-card-bg': cardBg,
                '--wpmc-text-primary': textPrimary,
                '--wpmc-text-secondary': textSecondary,
                '--wpmc-accent-color': accentColor
            });
        }
    }

    // Carrega uma fonte do Google Fonts injetando a tag link no head
    function loadFontInAdmin(fontFamily) {
        var fontId = 'wpmc-google-font-' + fontFamily.toLowerCase().replace(/\s+/g, '-');
        if (!$('#' + fontId).length) {
            var weights = fontFamily === 'Roboto' ? '300;400;500;700' : '300;400;600;800';
            var url = "https://fonts.googleapis.com/css2?family=" + encodeURIComponent(fontFamily) + ":wght@" + weights + "&display=swap";
            $('head').append('<link id="' + fontId + '" rel="stylesheet" href="' + url + '">');
        }
    }

    // Inicializa o preview
    updateLivePreview();

    // Escuta mudanças nos inputs do design
    $(document).on('change input', 'select[name="wpmc_font_family"], .wpmc-color-picker', function() {
        updateLivePreview();
    });

    // Suporte ao evento de carregamento via PJAX e eventos globais
    function reinitAll() {
        initColorPickers();
        updateLivePreview();
        wpmc_detect_and_show_ip();
    }

    $(document).on('wp-pjax-loaded', reinitAll);
    document.addEventListener('DOMContentLoaded', reinitAll);
    window.addEventListener('load', reinitAll);

    // Observador para o caso do container do poststuff ser atualizado dinamicamente
    if (window.MutationObserver) {
        var observer = new MutationObserver(function(mutations) {
            var hasUninit = $('.wpmc-color-picker').filter(function() {
                return !$(this).parent().hasClass('wpmc-picker-wrapper');
            }).length > 0;
            if (hasUninit) {
                initColorPickers();
                updateLivePreview();
            }
        });
        var targetNode = document.getElementById('wpbody-content') || document.body;
        if (targetNode) {
            observer.observe(targetNode, { childList: true, subtree: true });
        }
    }
});
