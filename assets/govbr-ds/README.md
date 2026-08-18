# DSGov — Padrão Digital de Governo (vendorizado)

Arquivos baixados do design system oficial do Governo Federal (`@govbr-ds/core` v3.7.0),
publicados sob licença MIT. Sem dependência de React/build — é CSS/JS pronto pra incluir
direto no `<head>`, do mesmo jeito que o Tailwind/Bootstrap via CDN já usados no projeto.

Fonte: https://www.gov.br/ds/ · pacote: https://www.npmjs.com/package/@govbr-ds/core

## Estrutura

```
assets/govbr-ds/
├── css/core.min.css          # todos os componentes + tokens (719 KB)
├── js/core.min.js            # comportamento dos componentes: accordion, dropdown,
│                              # collapse, tooltip, scrim etc. (232 KB)
└── fonts/rawline/
    ├── css/rawline.css       # @font-face da fonte oficial (18 pesos x 2 estilos)
    └── font/*.woff2/.woff    # os arquivos de fonte em si
```

## Como incluir numa página PHP

```html
<link rel="stylesheet" href="/assets/govbr-ds/fonts/rawline/css/rawline.css">
<link rel="stylesheet" href="/assets/govbr-ds/css/core.min.css">
<!-- ...conteúdo da página... -->
<script src="/assets/govbr-ds/js/core.min.js"></script>
```

Ajuste o caminho conforme a profundidade do arquivo PHP (ex.: `../assets/govbr-ds/...`
dentro de `admin/`).

## Pontos importantes

- **Ícones não estão inclusos.** O CSS do DSGov espera o Font Awesome já carregado
  separadamente (usa os mesmos códigos de glifo, ex. `\f00d`) — o projeto já carrega
  Font Awesome 6 via CDN em várias páginas admin, então não precisa baixar nada a mais.
- **Fonte base:** `Rawline, Raleway, sans-serif` — é o que o `core.min.css` já assume
  via `--font-family-base`. Só precisa do `rawline.css` incluído antes.
- **Não builda nada.** Estes são os arquivos `dist/` já compilados pelo próprio DSGov —
  não precisa de Sass, Node ou qualquer etapa de build local.
- **Versão:** fixada em 3.7.0 (última estável no momento do download, ago/2026). Pra
  atualizar, repita o download do `dist/core.min.{css,js}` da versão desejada em
  `cdn.jsdelivr.net/npm/@govbr-ds/core@<versão>/dist/`.
- **Convivência com Tailwind/Bootstrap:** como o DSGov usa classes próprias
  (prefixo geralmente `br-*`, ex. `br-button`, `br-input`), não há colisão de nome
  esperada com as classes utilitárias do Tailwind já usadas no formulário público.
  Ainda não testado lado a lado numa página real.
