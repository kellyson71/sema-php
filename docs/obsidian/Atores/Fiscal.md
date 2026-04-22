---
tags:
  - obsidian
  - ator
  - fiscal
---

# Fiscal

## Objetivo

Executar a etapa técnica principal no `setor2`, produzir o documento do processo, conduzir a assinatura técnica e preparar ou concluir o envio ao cidadão.

## Entradas principais

- Processos enviados ao `setor2`
- Requerimentos aguardando geração de documento
- Processos devolvidos pelo secretário para correção
- Processos com `status_admin = deferido` aguardando envio ao cidadão

## Saídas principais

- Processo enviado ao secretário
- Processo indeferido
- Processo concluído após envio final

## Ações permitidas

- Revisar documentos do processo
- Gerar documento técnico
- Assinar tecnicamente
- Enviar ao secretário
- Receber devolução para ajuste
- Enviar e-mail final ao cidadão
- Concluir o processo

## Caso de uso

```mermaid
flowchart LR
    f[Fiscal]

    subgraph Setor 2
        uc1[Revisar processo técnico]
        uc2[Gerar documento]
        uc3[Assinar tecnicamente]
        uc4[Enviar ao secretário]
        uc5[Corrigir devolução]
        uc6[Enviar documento ao cidadão]
        uc7[Concluir processo]
        uc8[Indeferir processo]
    end

    f --> uc1
    f --> uc2
    f --> uc3
    f --> uc4
    f --> uc5
    f --> uc6
    f --> uc7
    f --> uc8
```

## Regras de workflow

- Atua principalmente no `setor2`.
- Conduz a transição `enviar_secretario`.
- Após a assinatura institucional, o processo retorna ao `setor2` para `envio_cidadao`.
