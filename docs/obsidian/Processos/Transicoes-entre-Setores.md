---
tags:
  - obsidian
  - processo
  - workflow
---

# Transições entre Setores

Esta nota resume as transições explicitamente modeladas no workflow do sistema.

## Transições principais

- `liberar_setor2`: move do `setor1` para o `setor2`
- `enviar_secretario`: move do `setor2` para `secretario`
- `secretario_assinou`: move de `secretario` para `setor2`
- `secretario_devolveu`: move de `secretario` para `setor2` com motivo de devolução
- `indeferir`: encerra como `indeferido`
- `concluir`: encerra como `concluido`
- `arquivar`: encerra como `arquivado`
- `reabrir`: retorna ao `setor1`

```mermaid
stateDiagram-v2
    [*] --> setor1
    setor1 --> setor2: liberar_setor2
    setor1 --> indeferido: indeferir
    setor1 --> concluido: concluir
    setor1 --> arquivado: arquivar
    setor2 --> secretario: enviar_secretario
    setor2 --> indeferido: indeferir
    setor2 --> concluido: concluir
    setor2 --> arquivado: arquivar
    secretario --> setor2: secretario_assinou
    secretario --> setor2: secretario_devolveu
    indeferido --> setor1: reabrir
    arquivado --> setor1: reabrir
```

## Observações

- O secretário atua como etapa de validação, não como etapa terminal do fluxo.
- O envio ao cidadão ocorre depois da volta ao `setor2`.
