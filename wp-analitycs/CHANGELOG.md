# Registro de Alterações (Changelog) - Análise de Tráfego e Eventos

Todas as alterações notáveis, melhorias, correções de bugs e novos recursos deste projeto serão documentados neste arquivo.

O formato é baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/) e este projeto segue o [Versionamento Semântico](https://semver.org/lang/pt-BR/).

---

## [Não Lançado] (Unreleased)

---

## [1.0.1] - 2026-08-25

### 🛡️ Melhorias e Correções
- **Filtro de Páginas Reais:** Adicionada verificação rigorosa para não contabilizar erros 404 (`is_404()`), ignorando requisições a páginas inexistentes.
- **Bloqueio de Requisições Técnicas:** Ignora chamadas automáticas de sistema como `/.well-known/`, `xmlrpc.php`, `wp-cron.php` e arquivos estáticos/técnicos.
- **Detecção Avançada de Bots e IA:** Ampliado o filtro de User-Agents para barrar robôs e scrapers de Inteligência Artificial (ex: GPTBot, ChatGPT-User, ClaudeBot, Anthropic, Bytespider, Perplexity, Google-Extended), além de ferramentas CLI/automação (curl, wget, postman, python).

---

## [1.0.0] - 2026-08-24

### 🚀 Lançamento Inicial
- **Coleta de Métricas:** Rastreamento leve e independente de visualizações de página (*Page Views*) e contagem de visitantes únicos com hash anônimo (respeito a privacidade/LGPD).
- **Rastreamento de Eventos Personalizados:** Suporte a tracking via atributos HTML `data-track` (ex: cliques, envios de formulário, downloads).
- **Dashboard Integrado no WordPress Admin:**
  - Painel com cartões de indicadores-chave (KPIs): Visualizações Totais, Visitantes Únicos, Eventos Rastreados e Taxa de Interação.
  - Filtros por períodos pré-definidos (Hoje, Últimos 7 dias, Últimos 30 dias, Este Mês) e intervalo personalizado com seletores de data.
  - Gráficos visuais de evolução de tráfego e distribuição de dispositivos/canais.
  - Tabelas analíticas detalhadas: Páginas Mais Acessadas, Origem de Tráfego (Referrers), Eventos Disparados, Dispositivos e Navegadores.
- **Estrutura de Banco de Dados Otimizada:** Criação de tabelas personalizadas (`wp_analytics_views` e `wp_analytics_events`) com índices apropriados para alta performance.
- **Endpoints REST API:** Rotas REST protegidas com *nonces* para recepção assíncrona de eventos e visualizações em tempo real sem impacto no TTFB.
- **Configurações e Exclusões:** Opção para ignorar acessos de usuários logados/administradores e bots conhecidos.