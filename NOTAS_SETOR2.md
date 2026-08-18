# Contexto — acesso do Setor 2 a processos finalizados (provisório)

> Notas de discussão para retomar depois. Não é documentação de arquitetura definitiva.

## Por que o Setor 2 precisa ver processos já finalizados

Fluxo real (conforme explicado em 2026-07-10):

1. Cidadão envia requerimento (ex: Habite-se).
2. Setor 1 (triagem) analisa e, ao concluir, gera o **protocolo oficial** — que é o número que
   vai para **outro sistema**: o portal do contribuinte.
3. Setor 2 é quem dá suporte/controle interno sobre esse protocolo oficial depois. Se alguém
   procura um processo já encerrado sabendo só o protocolo do Setor 1, o Setor 2 precisa
   conseguir abrir o processo e informar: "o protocolo oficial é tal, acesse pelo portal do
   contribuinte" — e também mostrar os documentos (o portal do contribuinte não os exibe).
4. Por isso, ao Setor 1 finalizar um processo (direto ou indeferindo), ele **já deve estar**
   visível para o Setor 2 — não é um processo "aguardando ação" do Setor 2, é referência/consulta.
5. Repasse ao Secretário (Setor 3) continua fora do escopo dessa mudança — não mexer por ora.

## O que foi implementado (branch `main`, produção — não foi pra `homologacao`)

- `concluir_direto` e indeferimento no Setor 1 passam `setor_atual` para `setor2`.
- Backfill único rodado no banco de produção: 819 processos já Finalizados/Indeferidos que
  estavam presos em `setor_atual='setor1'` foram movidos para `setor2`.
- Listagem do fiscal deixou de esconder esses processos atrás do filtro "Mostrar encerrados".
- Badge da listagem mostra o `status` real (Finalizado/Indeferido/etc) em vez do genérico
  "Concluído" quando `aguardando_acao='concluido'`.
- Ordenação da fila do fiscal/secretário passou de mais antigo primeiro (FIFO) para mais
  recente primeiro.
- **Temporário**: papel `fiscal` trata processos Finalizado/Indeferido como ativos — continua
  vendo o painel normal de ações (Gerar Documento, encaminhar, etc.) em vez do painel de
  "processo encerrado". Os botões Reabrir/Arquivar desse painel ficam ocultos para fiscal
  quando o painel de encerrado aparece (papéis que não são fiscal puro).
- Documentos assinados por contas de teste "Kellyson" (kellyson, kellyson1/2/3) ficam ocultos
  da listagem de documentos gerados/assinados para outros usuários — só a própria conta
  Kellyson continua vendo, para limpeza posterior.

## Pendências / decisões adiadas

- Repasse automático Setor 2 → Setor 3 quando aplicável: não mexido.
- Limpeza definitiva das assinaturas de teste da conta Kellyson em produção: adiada,
  hoje só filtradas na exibição, não removidas do banco.
- Reavaliar se o tratamento "fiscal sempre ativo" deve virar permanente ou se volta a
  restringir ações quando o fluxo Setor 2 → Setor 3 for revisado.
