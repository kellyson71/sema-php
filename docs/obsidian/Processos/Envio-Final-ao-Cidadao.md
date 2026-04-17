---
tags:
  - obsidian
  - processo
  - email
---

# Envio Final ao Cidadão

Após a assinatura institucional, o processo retorna ao `setor2` para o fechamento operacional e comunicação externa.

```mermaid
flowchart LR
    A[Secretário assina] --> B[status_admin = deferido]
    B --> C[setor_atual = setor2]
    C --> D[aguardando_acao = envio_cidadao]
    D --> E[Fiscal envia documento ao cidadão]
    E --> F[Histórico registra envio]
    F --> G[Processo pode ser concluído]
```

## Regras importantes

- O documento já foi institucionalmente assinado antes desta etapa.
- O fiscal ou administrador conclui a comunicação final.
- O envio ao cidadão é parte do fechamento do processo, não da assinatura institucional em si.
