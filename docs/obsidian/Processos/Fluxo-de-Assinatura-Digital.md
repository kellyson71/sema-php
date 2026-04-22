---
tags:
  - obsidian
  - processo
  - assinatura
---

# Fluxo de Assinatura Digital

O sistema possui duas camadas de assinatura dentro do fluxo administrativo: técnica e institucional.

```mermaid
flowchart TD
    A[Fiscal prepara documento] --> B[Documento técnico disponível]
    B --> C[Secretário revisa]
    C -->|sessão válida| D[Assinatura institucional]
    C -->|devolver| E[Retorna ao setor 2]
    D --> F[Registro da assinatura]
    F --> G[Processo volta ao setor 2]
    G --> H[Envio final ao cidadão]
```

## Etapas principais

- O fiscal produz ou consolida o documento técnico.
- O secretário valida a sessão de assinatura antes da ação final.
- A assinatura institucional não encerra automaticamente o processo; ela devolve o item ao `setor2` para envio final.
