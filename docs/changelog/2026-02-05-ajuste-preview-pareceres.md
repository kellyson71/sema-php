# 2026-02-05 - Ajuste de pré-visualização de pareceres

## Resumo
Melhorada a pré-visualização do posicionamento de assinatura para refletir o layout A4 real e evitar cortes do conteúdo no modal.

## Mudanças Implementadas
- Adicionada área de preview com rolagem e escala automática para caber no modal sem cortar a página.
- Ajustados offsets e estilos do conteúdo do preview para alinhar com os templates A4.
- Incluída detecção de templates `licenca_` no viewer para tratamento A4.

## Arquivos Modificados
- `admin/visualizar_requerimento.php`
- `admin/parecer_viewer.php`
