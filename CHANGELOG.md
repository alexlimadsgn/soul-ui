# Changelog

Todas as alterações notáveis neste projeto serão documentadas neste arquivo.

O formato é baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/),
e este projeto adere ao [Semantic Versioning](https://semver.org/lang/pt-BR/).

## [2.2.0] - 2026-09-02

### Adicionado
- **Editor Monaco Integrado (Temas e Plugins)**:
  - Menu principal dedicado **File Editor** na seção de **Funcionalidades** da sidebar com submenus específicos dedicados: **Editor de Temas** (`admin.php?page=code-editor-ide`) e **Editor de Plugins** (`admin.php?page=code-editor-plugins`).
  - Remoção dos botões de alternância em abas (Tabs) no topo do editor de código, adotando um indicador contextual limpo e seletor específico para o tipo selecionado (Tema ou Plugin).
  - Redirecionamento automático dos editores nativos do WordPress (`theme-editor.php` e `plugin-editor.php`) diretamente para seus respectivos submenus na nova IDE.
  - Encapsulamento completo de código na classe `WP_Admin_UI_Editor_Monaco`, prevenindo conflitos de funções duplicadas (*redeclare functions*).
  - Árvore interativa de arquivos com criação de arquivos/pastas, upload de múltiplos arquivos, download individual, download em arquivo ZIP do pacote e exclusão segura.
  - Validação estrita de segurança contra Path Traversal e checagem contextual de permissões (`edit_themes` e `edit_plugins`).
  - Interface com fontes *Inter* e *Fira Code*, coloração de sintaxe e atalhos rápidos (`Ctrl+S`, `Ctrl+F`).
- **WP Analytics Integrado**:
  - Rastreamento leve de pageviews e eventos personalizados via atributo `data-track`.
  - Painel de controle analítico com métricas de visitantes únicos, páginas mais acessadas, fontes de tráfego, países, dispositivos e histórico de eventos em tempo real.
  - Integração com gráficos Chart.js e criação automática de tabelas no banco de dados (`wp_analytics_hits` e `wp_analytics_events`).
- **Padronização Visual**:
  - Eliminação de estilos inline nos módulos de Analytics e Editor Monaco em conformidade com as diretrizes do ecossistema.
  - Compactação da altura dos itens e submenus da sidebar do Soul UI para `34px`, proporcionando uma navegação mais ergonômica e concisa.
  - Transformação do **Filtro de Características de Temas** em uma **Sidebar Drawer flutuante à direita (Off-Canvas)** com overlay de fundo, rolagem suave e botões fixos de ação.
  - Padronização das abas de navegação e exibição do **Cookie Consent** em cards `postbox` nativos do WordPress por aba (`&tab=`), com layout clássico de duas colunas e caixa lateral **Publicar** nativa.
  - Padronização completa da interface do **Modo de Manutenção** (`wp-maintenance-contacts`), removendo espaçamento excessivo (`padding-top: 86px`), cabeçalho customizado e adotando navegação nativa por abas (`&tab=`) com conteúdo organizado em `postbox` único e padronizado por aba, além de metabox **Publicar** nativo.
  - **Renderização Condicional e Controle de Acesso no Modo de Manutenção**:
    - Omissão automática da seção/grid de contatos e mensagem informativa dinâmica na tela pública de manutenção quando nenhum dado de contato (telefone, e-mail, endereço) estiver configurado.
    - Validação de acesso estrita: exibição forçada do modo de manutenção para todos os visitantes não autenticados que não possuam seu endereço IP cadastrado na lista de IPs autorizados, permitindo acesso irrestrito somente a administradores autenticados ou IPs na lista branca.
    - **Pré-visualização em Tempo Real Integrada na Aba de Design**: Exibição lateral embutida e responsiva do mockup da página de manutenção diretamente na guia "Design e Aparência", com preenchimento balanceado do container (`width: 100%`), proporções e padding aprimorados e atualização instantânea e reativa de cores, fontes, gradientes e opacidades.
    - **Seletor de Cores Dinâmico (Color Picker)**: Renderização direta pelo PHP do markup com quadrado de visualização de cor (`.wpmc-picker-preview-button`) e wrapper flexível ao lado de cada input de código hexadecimal, garantindo exibição instantânea sem dependência de atraso de script, associado a inicialização robusta com popover de 3 sliders (Matiz, Luminosidade e Opacidade), paleta de swatches rápidas e conta-gotas sob qualquer navegação (comum ou PJAX).

### Corrigido
- Correção de erro de sintaxe (*parse error*) no fechamento de `wp_send_json_error` em `editor-monaco.php`.
- Remoção de bordas duplas, sombras e cantos arredondados (`border-radius`) em tabelas do WordPress embutidas dentro de cards (`.postbox`) no WP Analytics e em todo o Soul UI.
- Unificação canônica de URLs e agrupamento de páginas no WP Analytics, impedindo a duplicação de linhas com a mesma página inicial ou variações de parâmetros.
- Ajuste de `padding-right` em elementos `<select>` em toda a interface e na barra de ações em lote (`.tablenav`), prevenindo a sobreposição de texto sob o ícone de seta do dropdown.
- Alinhamento e layout responsivo com Flexbox para os botões secundários (*Guardar rascunho* / *Pré-visualização*) e ações principais (*Mover para o lixo* / *Publicar*) no metabox `#submitdiv` (`.submitbox`), eliminando a sobreposição e quebra desnivelada de botões flutuantes.
- Correção do aninhamento de fechamento da tag `#post-body-content` no Simple Cookie Consent, restaurando a exibição do metabox **Publicar** na coluna lateral direita.
- Correção do alinhamento vertical e eliminação de margens conflitantes entre os filtros/ações em lote (`.tablenav.top`, `.actions`, `.bulkactions`, `.tablenav-pages`) e o formulário de busca (`.search-box`) nas tabelas de listagem (`wp-list-table`), garantindo que todos os controles fiquem perfeitamente nivelados na mesma linha horizontal.
- Padronização do espaçamento interno (`padding: 16px !important`) para `.postbox .inside` e `#poststuff .inside`, garantindo respiro visual e compatibilidade com campos de Custom Post Types (CPTs), temas e metaboxes customizados, mantendo a caixa `#submitdiv` isolada com seu layout específico.

---

## [2.1.0] - 2026-08-31

### Adicionado
- **Desativação Padrão do Gutenberg**: O Editor de Blocos (Gutenberg) e os blocos de widgets foram desativados por padrão para posts, páginas e Custom Post Types, mantendo o editor clássico ágil e sem sobrecarga.
- **Topbar Clara (Paper Style)**: Redesenho completo da barra de administração superior com fundo branco `#ffffff`, borda inferior `#dcdcde`, tipografia de alto contraste e busca pill integrada com atalho `Ctrl K`.

- **Seletor de Esquema de Cores**: Novo menu dropdown na Topbar permitindo ao utilizador alternar instantaneamente entre as 9 paletas de cores padrão do WordPress (*WordPress (Padrão)*, *Moderno*, *Azul*, *Café*, *Ectoplasma*, *Meia-noite*, *Oceano*, *Nascer do Sol*, *Verde / Fresh*) com persistência via AJAX (`wn_set_admin_color`).
- **Dashboard Paper Style**:
  - Cabeçalho contextual com saudação dinâmica baseada no horário e data formatada por extenso.
  - Indicador circular em SVG de Saúde do Site (*Site Health Score*).
  - Contadores de métricas para Artigos e Páginas.
  - Grid de Acesso Rápido com cartões modulares de ferramentas essenciais.
  - Módulo dinâmico "Tipos de Conteúdo do Tema" para gerenciamento de Custom Post Types registrados (ex: Serviços).
  - Painel lateral de Atividade Recente.

### Modificado & Refatorado
- **Unificação da Arquitetura CSS**:
  - Criação de `assets/admin-core.css` consolidando as variáveis globais, reset do WordPress, o Shell de layout (`#wpcontent`, `#wpbody-content`, `.wrap`), a Sidebar expansível/colapsável e a Topbar clara.
  - `assets/admin-listtables.css` agora focado estritamente na estilização de tabelas (`wp-list-table`), filtros `.tablenav`, abas `.subsubsub` e badges de status, eliminando conflitos de escopo em páginas de configurações e plugins.
  - Padronização de paddings em todas as telas (`24px 32px 32px 32px`), eliminando espaços excessivos no topo das listagens e desalinhamentos.
  - Alinhamento unificado na mesma linha entre ações em massa (`.tablenav.top` / Bulk Actions) e caixa de pesquisa (`.search-box`) em todas as telas de listagem (Plugins, Utilizadores, Posts, etc).
  - **Botão de Adicionar (Topo Direito)**: Mantido o botão de ação clássico e confiável (*Adicionar / Add New*) posicionado no topo direito do título da página, com estilo refinado em formato pílula/retângulo suave.
  - **Limpeza do Menu Aparência (Temas)**: Desativados e removidos os submenus desnecessários **Tipos de letra** (*Font Library*) e **Padrões** (*Patterns / wp_block*), mantendo a navegação limpa e objetiva.
  - **Ocultação de Opções de Tela & Ajuda**: Ocultados os botões e os painéis de *Opções de Tela (Screen Options)* e *Ajuda (Help)* para uma interface mais limpa, moderna e sem distrações visuais.
  - Ocultação completa do rodapé padrão do WordPress (*"Obrigado por criar com o WordPress"* e número de versão).
  - Padronização de títulos de cards na Dashboard para `15px` e cabeçalhos para `22px`.

---

## [2.0.1] - 2026-08-30

### Modificado
- Ajustes finos no suporte PJAX e restauração de estado da sidebar colapsada.
- Correção de animações e transições durante o carregamento inicial.

---

## [2.0.0] - 2026-08-25

### Adicionado
- Lançamento inicial da nova interface Soul UI.
- Sistema de colapso de sidebar estilo Notion/SaaS.
- Suporte a navegação instantânea via PJAX.
- Integração dos módulos de Gerenciamento de Acessos, Modo de Manutenção e Cookie Consent.
