---
tags:
  - obsidian
  - processo
  - requerimentos
---

# Fluxo de Requerimentos

Este é o fluxo principal do sistema, desde o envio público até o encerramento administrativo.

```mermaid
flowchart TD
    A[Cidadão envia requerimento] --> B[Setor 1 / Analista]
    B -->|liberar_setor2| C[Setor 2 / Fiscal]
    B -->|indeferir| I[Indeferido]
    B -->|concluir| F[Concluído]
    B -->|arquivar| AR[Arquivado]
    C -->|enviar_secretario| D[Secretário]
    C -->|indeferir| I
    C -->|concluir| F
    C -->|arquivar| AR
    D -->|secretario_assinou| C2[Retorna ao Setor 2]
    D -->|secretario_devolveu| C3[Setor 2 para correção]
    C2 -->|enviar_email_cidadao| E[Envio ao cidadão]
    C2 -->|concluir| F
```

## Leitura operacional

- O cidadão não percorre os setores internos.
- O setor 1 faz triagem e decisão inicial.
- O setor 2 concentra a etapa técnica e o envio final.
- O secretário não encerra o fluxo diretamente; ao assinar, o processo retorna ao setor 2.
