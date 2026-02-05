# 2026-02-05 - Ajuste de pré-visualização de pareceres

## Resumo
Pré-visualização de assinatura alinhada ao `parecer_viewer` para espelhar o resultado final sem cortes no modal.

## Mudanças Implementadas
- Preview do modal passa a renderizar conteúdo via `parecer_viewer_preview.php` com layout idêntico ao viewer.
- Assinatura posicionada em overlay sobre o iframe de pré-visualização.
- Detecção de templates `licenca_` no viewer para tratamento A4.
- Exibição da matrícula/portaria do assinante no bloco de assinatura do preview.
- Exibição da matrícula/portaria no bloco de assinatura do `parecer_viewer`.

## Arquivos Modificados
- `admin/visualizar_requerimento.php`
- `admin/parecer_viewer.php`
- `admin/parecer_viewer_preview.php`
- `admin/parecer_handler.php`
