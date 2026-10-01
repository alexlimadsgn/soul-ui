/**
 * Simple Cookie Consent - Frontend Script
 * Versão: 2.0.0
 */

(function () {
    'use strict';

    // Elementos do DOM
    const elements = {
        banner: null,
        modal: null,
        btnAcceptAll: null,
        btnConfigure: null,
        btnSavePreferences: null,
        btnAcceptAllModal: null,
        btnOpenSettings: null,
        analyticsCheckbox: null,
        marketingCheckbox: null,
        btnCloseBanner: null
    };

    // Configurações do plugin
    let settings = {};

    // Estado do consentimento
    let consentState = {
        necessary: true,
        analytics: false,
        marketing: false,
        saved: false
    };

    /**
     * Inicializa o plugin
     */
    function init() {
        // Verificar se o banner está ativo
        if (typeof scc_settings === 'undefined' || scc_settings.enable_banner !== 'yes') {
            return;
        }

        // Carregar configurações
        settings = scc_settings || {};

        // Cache dos elementos
        cacheElements();

        // Carregar consentimento salvo
        loadSavedConsent();

        // Configurar eventos
        setupEventListeners();

        // Aplicar posição do banner
        applyPosition();

        // Mostrar banner se necessário
        if (!consentState.saved) {
            setTimeout(showBanner, settings.delay || 1000);
        }
    }

    /**
     * Cache dos elementos do DOM
     */
    function cacheElements() {
        elements.banner = document.getElementById('scc-cookie-banner');
        elements.modal = document.getElementById('scc-cookie-modal');
        elements.btnAcceptAll = document.getElementById('scc-accept-all');
        elements.btnConfigure = document.getElementById('scc-configure');
        elements.btnSavePreferences = document.getElementById('scc-save-preferences');
        elements.btnAcceptAllModal = document.getElementById('scc-accept-all-modal');
        elements.btnOpenSettings = document.getElementById('scc-open-settings');
        elements.analyticsCheckbox = document.getElementById('scc-analytics');
        elements.marketingCheckbox = document.getElementById('scc-marketing');
        elements.btnCloseBanner = document.getElementById('scc-close-banner');
    }

    /**
     * Carrega consentimento salvo
     */
    function loadSavedConsent() {
        try {
            const saved = localStorage.getItem('scc_consent');
            if (saved) {
                const parsed = JSON.parse(saved);
                consentState = {
                    ...consentState,
                    ...parsed,
                    saved: true
                };

                // Atualizar checkboxes se existirem
                if (elements.analyticsCheckbox) {
                    elements.analyticsCheckbox.checked = consentState.analytics;
                }
                if (elements.marketingCheckbox) {
                    elements.marketingCheckbox.checked = consentState.marketing;
                }

                // Disparar evento de consentimento carregado
                document.dispatchEvent(new CustomEvent('sccConsentLoaded', {
                    detail: consentState
                }));
            }
        } catch (error) {
            console.error('Erro ao carregar consentimento:', error);
        }
    }

    /**
     * Configura os event listeners
     */
    function setupEventListeners() {
        // Botão Aceitar Todos (banner)
        if (elements.btnAcceptAll) {
            elements.btnAcceptAll.addEventListener('click', (e) => {
                e.preventDefault();
                acceptAllCookies();
            });
        }
        
        // Botão Fechar Banner (X)
        if (elements.btnCloseBanner) {
            elements.btnCloseBanner.addEventListener('click', (e) => {
                e.preventDefault();
                rejectOptionalCookies();
            });
        }

        // Botão Configurar (abrir modal)
        if (elements.btnConfigure) {
            elements.btnConfigure.addEventListener('click', (e) => {
                e.preventDefault();
                openModal();
            });
        }

        // Botão Salvar Preferências
        if (elements.btnSavePreferences) {
            elements.btnSavePreferences.addEventListener('click', (e) => {
                e.preventDefault();
                savePreferences();
            });
        }

        // Botão Aceitar Todos (modal)
        if (elements.btnAcceptAllModal) {
            elements.btnAcceptAllModal.addEventListener('click', (e) => {
                e.preventDefault();
                acceptAllCookies();
            });
        }

        // Link de configuração via shortcode
        if (elements.btnOpenSettings) {
            elements.btnOpenSettings.addEventListener('click', (e) => {
                e.preventDefault();
                openModal();
            });
        }

        // Fechar modal ao clicar fora
        document.addEventListener('click', (e) => {
            if (elements.modal && e.target === elements.modal) {
                closeModal();
            }
        });

        // Fechar modal com ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && elements.modal && elements.modal.classList.contains('active')) {
                closeModal();
            }
        });

        // Preencher checkboxes ao abrir modal
        if (elements.modal) {
            elements.modal.addEventListener('click', (e) => {
                if (e.target === elements.btnConfigure || e.target.closest('#scc-configure')) {
                    fillCheckboxes();
                }
            });
        }
    }

    /**
     * Aplica a posição do banner
     */
    function applyPosition() {
        if (!elements.banner || !settings.position) return;

        elements.banner.classList.add(settings.position);

        // Remover outras posições
        const positions = ['top', 'bottom'];
        positions.forEach(pos => {
            if (pos !== settings.position) {
                elements.banner.classList.remove(pos);
            }
        });
    }

    /**
     * Mostra o banner
     */
    function showBanner() {
        if (elements.banner) {
            elements.banner.style.display = 'block';

            // Disparar evento
            document.dispatchEvent(new CustomEvent('sccBannerShown'));
        }
    }

    /**
     * Esconde o banner
     */
    function hideBanner() {
        if (elements.banner) {
            elements.banner.style.display = 'none';
        }
    }

    /**
     * Abre o modal
     */
    function openModal() {
        if (elements.modal) {
            elements.modal.classList.add('active');
            document.body.style.overflow = 'hidden';

            // Disparar evento
            document.dispatchEvent(new CustomEvent('sccModalOpened'));
        }
    }

    /**
     * Fecha o modal
     */
    function closeModal() {
        if (elements.modal) {
            elements.modal.classList.remove('active');
            document.body.style.overflow = '';

            // Disparar evento
            document.dispatchEvent(new CustomEvent('sccModalClosed'));
        }
    }

    /**
     * Preenche os checkboxes com valores salvos
     */
    function fillCheckboxes() {
        if (elements.analyticsCheckbox) {
            elements.analyticsCheckbox.checked = consentState.analytics;
        }
        if (elements.marketingCheckbox) {
            elements.marketingCheckbox.checked = consentState.marketing;
        }
    }

    /**
     * Aceita todos os cookies
     */
    function acceptAllCookies() {
        consentState = {
            necessary: true,
            analytics: settings.enable_analytics === 'yes',
            marketing: settings.enable_marketing === 'yes',
            saved: true
        };

        saveConsent(consentState);
        hideBanner();
        closeModal();

        // Disparar evento
        document.dispatchEvent(new CustomEvent('sccAllAccepted', {
            detail: consentState
        }));
    }

    /**
     * Rejeita cookies opcionais (salva apenas os essenciais)
     */
    function rejectOptionalCookies() {
        consentState = {
            necessary: true,
            analytics: false,
            marketing: false,
            saved: true
        };

        saveConsent(consentState);
        hideBanner();

        // Disparar evento
        document.dispatchEvent(new CustomEvent('sccConsentRejected', {
            detail: consentState
        }));
    }

    /**
     * Salva preferências personalizadas
     */
    function savePreferences() {
        consentState = {
            necessary: true,
            analytics: elements.analyticsCheckbox ? elements.analyticsCheckbox.checked : false,
            marketing: elements.marketingCheckbox ? elements.marketingCheckbox.checked : false,
            saved: true
        };

        saveConsent(consentState);
        hideBanner();
        closeModal();

        // Disparar evento
        document.dispatchEvent(new CustomEvent('sccPreferencesSaved', {
            detail: consentState
        }));
    }

    /**
     * Salva consentimento localmente e via AJAX
     */
    function saveConsent(consent) {
        try {
            // Salvar no localStorage
            localStorage.setItem('scc_consent', JSON.stringify(consent));
            localStorage.setItem('scc_consent_timestamp', new Date().toISOString());

            // Enviar via AJAX se disponível
            if (settings.ajax_url) {
                const data = new FormData();
                data.append('action', 'scc_save_consent');
                data.append('nonce', settings.nonce);
                data.append('analytics', consent.analytics);
                data.append('marketing', consent.marketing);

                fetch(settings.ajax_url, {
                    method: 'POST',
                    body: data,
                    credentials: 'same-origin'
                }).catch(error => {
                    console.error('Erro ao salvar consentimento no servidor:', error);
                });
            }

            // Ativar/desativar scripts baseados no consentimento
            updateScripts(consent);

        } catch (error) {
            console.error('Erro ao salvar consentimento:', error);
        }
    }

    /**
     * Ativa/desativa scripts baseados no consentimento
     */
    function updateScripts(consent) {
        // Exemplo: gtag.js para Google Analytics
        if (consent.analytics && window.gtag) {
            window['ga-disable-UA-XXXXX-Y'] = false;
            gtag('consent', 'update', {
                'analytics_storage': 'granted'
            });
        } else if (!consent.analytics && window.gtag) {
            window['ga-disable-UA-XXXXX-Y'] = true;
            gtag('consent', 'update', {
                'analytics_storage': 'denied'
            });
        }

        // Facebook Pixel
        if (consent.marketing && window.fbq) {
            fbq('consent', 'grant');
        } else if (!consent.marketing && window.fbq) {
            fbq('consent', 'revoke');
        }

        // Outros scripts podem ser adicionados aqui
    }

    /**
     * Verifica se um tipo de cookie foi aceito
     */
    function hasConsent(type) {
        return consentState[type] === true;
    }

    /**
     * Retorna o estado atual do consentimento
     */
    function getConsentState() {
        return { ...consentState };
    }

    /**
     * Abre o modal de configuração programaticamente
     */
    function openSettings() {
        openModal();
    }

    /**
     * Reseta o consentimento (para testes)
     */
    function resetConsent() {
        localStorage.removeItem('scc_consent');
        localStorage.removeItem('scc_consent_timestamp');
        consentState = {
            necessary: true,
            analytics: false,
            marketing: false,
            saved: false
        };

        if (elements.analyticsCheckbox) {
            elements.analyticsCheckbox.checked = false;
        }
        if (elements.marketingCheckbox) {
            elements.marketingCheckbox.checked = false;
        }

        showBanner();
    }

    // Expor funções públicas
    window.SimpleCookieConsent = {
        hasConsent,
        getConsentState,
        openSettings,
        resetConsent
    };

    // Inicializar quando o DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();