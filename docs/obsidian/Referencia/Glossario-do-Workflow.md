---
tags:
  - obsidian
  - referencia
  - glossario
---

# Glossário do Workflow

## Atores

- `Cidadão`: ator externo que protocola e consulta requerimentos.
- `Analista`: responsável pelo `setor1`.
- `Fiscal`: responsável pelo `setor2`.
- `Secretário`: responsável pela assinatura institucional.
- `Admin / Admin Geral`: ator de supervisão com acesso ampliado.

## Campos centrais

- `status`: rótulo visível do processo.
- `status_admin`: estado administrativo consolidado.
- `setor_atual`: setor responsável no momento.
- `aguardando_acao`: próximo passo esperado.

## Setores

- `setor1`: triagem inicial.
- `setor2`: execução técnica e fechamento operacional.
- `secretario`: revisão e assinatura institucional.
- `concluido`: estado terminal de processo concluído.

## Transições

- `liberar_setor2`
- `enviar_secretario`
- `secretario_assinou`
- `secretario_devolveu`
- `indeferir`
- `concluir`
- `arquivar`
- `reabrir`
- `enviar_email_cidadao`

## Termos legados

- `operador`: termo antigo ainda presente em partes do sistema e em documentação histórica.
- Na documentação funcional nova, o papel antigo foi desdobrado em `analista` e `fiscal`.
