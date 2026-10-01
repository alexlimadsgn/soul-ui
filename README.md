# Soul UI

![Versão](https://img.shields.io/badge/vers%C3%A3o-2.2.0-blue)
![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4)
![Licença](https://img.shields.io/badge/licen%C3%A7a-MIT-green)

Plugin WordPress que transforma o painel administrativo em uma interface moderna e minimalista, com controle de acessos integrado, editor de código Monaco e analytics.

## Funcionalidades

- **Interface moderna** — sidebar, admin bar, dashboard, tabelas e tela de login redesenhadas.
- **Controle de acessos** — gerenciamento de permissões e visibilidade de menus por usuário/função.
- **Segurança** — reforços de segurança para o painel e o login.
- **Pastas de mídia** — organização da biblioteca de mídia em pastas.
- **Editor Monaco** — IDE para temas e plugins com árvore de arquivos, upload, download em ZIP e proteção contra *path traversal*.
- **WP Analytics** — rastreamento leve de pageviews e eventos (`data-track`), com painel de métricas e gráficos.
- **Cookie Consent** — banner de consentimento de cookies configurável.
- **Modo de Manutenção** — página de manutenção com informações de contato.

## Requisitos

- WordPress 6.0 ou superior
- PHP 7.4 ou superior

## Instalação

1. Baixe o repositório como `.zip` (ou clone-o) para `wp-content/plugins/soul-ui`.
2. No painel do WordPress, acesse **Plugins** e ative **Soul UI**.
3. Configure as opções no novo menu do painel.

```bash
cd wp-content/plugins
git clone https://github.com/alexlimadsgn/soul-ui.git
```

## Estrutura

```
soul-ui/
├── soul-ui.php               # Arquivo principal do plugin
├── includes/                 # Classes da interface (sidebar, login, acesso, etc.)
├── assets/                   # CSS, JS e imagens
├── editor-monaco/            # Módulo do editor de código
├── wp-analitycs/             # Módulo de analytics
├── simple-cookie-consent/    # Módulo de consentimento de cookies
└── wp-maintenance-contacts/  # Módulo de modo de manutenção
```

## Changelog

As alterações de cada versão estão em [CHANGELOG.md](CHANGELOG.md).

## Contribuindo

1. Faça um fork do projeto.
2. Crie uma branch: `git checkout -b minha-feature`.
3. Faça commit das alterações e abra um Pull Request.

Para relatar bugs ou sugerir melhorias, abra uma [issue](https://github.com/alexlimadsgn/soul-ui/issues).

## Créditos

- [Monaco Editor](https://github.com/microsoft/monaco-editor) — MIT
- [Lucide](https://lucide.dev) — ISC
- [Chart.js](https://www.chartjs.org) — MIT

## Licença

Distribuído sob a licença MIT. Veja [LICENSE](LICENSE) para mais detalhes.

© 2026 [Alex Lima](https://alexlimadsgn.framer.ai/)
