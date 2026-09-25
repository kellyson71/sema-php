---
name: reproduzir-sessao-usuario
description: Reconstrói o que um usuário do painel admin do sema-php fez (páginas, ações, erros, gravação da sessão no PostHog) para reproduzir um erro ou problema relatado. Use quando alguém disser "deu erro para a Fulana", "o sistema travou ontem", "não salvou o parecer", pedir o passo a passo de um usuário, ou quiser investigar tempo de uso/atividade de alguém.
---

# Reproduzir a sessão de um usuário

O painel grava três rastros que se completam. O objetivo é juntar os três numa
linha do tempo e refazer o caminho localmente.

| Rastro | Onde | O que tem |
|---|---|---|
| `admin_eventos` | banco (MySQL) | cada página/ação/erro do painel: hora, página, nome da ação, id do registro, status HTTP, duração, `posthog_session_id` |
| `admin_atividade_diaria` | banco | por pessoa e dia: tempo ativo, nº de ações, páginas, erros |
| PostHog | projeto da SEMA (us.posthog.com) | gravação da sessão (campos mascarados), pageviews, `$exception` do navegador e do PHP, console do navegador. Pessoa = `admin_<id>` |

Históricos de negócio que ajudam a cruzar: `historico_acoes` (requerimentos),
`denuncia_historico`, `email_logs`, `assinaturas_digitais`.

Regra de privacidade: `admin_eventos` nunca guarda valor digitado. A gravação
do PostHog mostra o texto das telas (nome/CPF de cidadão). Não copie dado de
cidadão para issue, commit, PR ou resposta — cite protocolo/id, não nome/CPF.

## 1. Delimitar o caso

Descubra (pergunte se faltar): **quem** (nome ou e-mail → `administradores.id`),
**quando** (dia e faixa de horário), **onde** (tela, protocolo/id do registro) e
**o que esperava vs. o que aconteceu**. Ambiente: produção (`u492577848_SEMA`) ou
homologação (`u492577848_SEMA_hmg`). Credenciais e forma do comando SSH estão no
`CLAUDE.md` do projeto, seção "Acesso ao banco de dados via SSH".

Todas as consultas abaixo são **somente leitura**. Nunca rode INSERT/UPDATE/DELETE
em produção durante uma investigação.

```bash
# atalho: defina uma vez e reuse
Q() { ssh -p 65002 -i ~/.ssh/id_ed25519 u492577848@46.202.145.215 \
  "mysql -h srv1844.hstgr.io -u u492577848_SEMA -pSENHA u492577848_SEMA -e \"$1\""; }
```

## 2. Achar o usuário

```sql
SELECT id, nome, nivel, setor, ativo FROM administradores
WHERE nome LIKE '%sabrina%' OR email LIKE '%sabrina%';
```

## 3. Linha do tempo

```sql
-- o dia inteiro (sem as chamadas AJAX de autocomplete)
SELECT ocorrido_em, tipo, metodo, pagina, acao, entidade, entidade_id,
       status_http, duracao_ms, erro, posthog_session_id, simulando
FROM admin_eventos
WHERE admin_id = 12 AND ocorrido_em BETWEEN '2026-09-24 13:00' AND '2026-09-24 18:00'
  AND tipo <> 'ajax'
ORDER BY ocorrido_em;

-- só o que deu errado (HTTP >= 400 ou erro fatal), última semana
SELECT ocorrido_em, admin_id, pagina, acao, status_http, erro
FROM admin_eventos
WHERE ocorrido_em >= NOW() - INTERVAL 7 DAY AND (tipo = 'erro' OR status_http >= 400)
ORDER BY ocorrido_em DESC LIMIT 100;

-- todo mundo que mexeu num registro específico
SELECT ocorrido_em, admin_id, pagina, acao, status_http
FROM admin_eventos WHERE entidade = 'denuncia' AND entidade_id = 57 ORDER BY ocorrido_em;

-- contexto de negócio no mesmo período
SELECT data_acao, acao FROM historico_acoes WHERE admin_id = 12 AND data_acao >= '2026-09-24';
SELECT data_registro, acao, detalhes FROM denuncia_historico WHERE admin_id = 12 AND data_registro >= '2026-09-24';
```

Leitura rápida dos sinais:
- ação POST com `status_http` 302 seguida da mesma página com `?error=` → validação recusou;
- `tipo = 'erro'` → fatal/500; a mensagem vem sem dígitos longos (CPF/telefone viram `#`);
- mesma ação repetida em segundos → pessoa clicou de novo achando que não salvou;
- `duracao_ms` alto → lentidão (PDF, e-mail, upload);
- `simulando = 1` → era um admin simulando outro cargo.

A tela `admin/atividade_usuarios.php?admin=ID&dia=AAAA-MM-DD` mostra a mesma
linha do tempo para quem não usa SQL.

## 4. PostHog

Pegue o `posthog_session_id` dos eventos do período.

- Projeto: **SEMA, id `509259`**. A mesma conta tem o projeto Curta PDF — com as ferramentas
  MCP, rode `switch-project` para 509259 antes de consultar.
- **Gravação**: `https://us.posthog.com/project/509259/replay/<session_id>`.
  Todas as gravações de uma pessoa: `https://us.posthog.com/project/509259/person/admin_<id>#activeTab=sessionRecordings`.
  A tela de atividade já traz os dois links prontos.
- **Com as ferramentas MCP do PostHog** (plugin `posthog`; autentique se pedir):
  - eventos da pessoa: `distinct_id = 'admin_12'` no intervalo (pageview, `$pageleave`, `$exception`);
  - exceções: consulte `$exception` do mesmo `distinct_id`/`$session_id` — inclui erro
    de JavaScript do navegador e erro do PHP (o SDK do servidor usa o mesmo id);
  - gravação: procure pela sessão e leia o console gravado (`enable_recording_console_log`).
  - antes de escrever HogQL, carregue a skill `posthog:querying-posthog-data`;
    para uma gravação específica, `posthog:investigating-replay`.
- Sem acesso ao PostHog, siga só com o banco — a sequência de páginas e ações já
  costuma bastar para reproduzir.

## 5. Reproduzir localmente

1. Suba o ambiente (skill `rodar-projeto`) ou use o servidor PHP embutido com um banco
   de teste.
2. Entre como admin e, se o problema foi com outro cargo, use
   `admin/simular_perfil.php?role=analista` (ou `fiscal`, `secretario`, `operador`).
3. Refaça a sequência de páginas/ações da linha do tempo, com um registro parecido
   (mesmo tipo de alvará/setor/status). Não importe dados reais de cidadão.
4. Confirmado o erro, localize o código (`pagina` + `acao` apontam o arquivo e o ramo
   do `switch`/`if`), corrija e escreva o teste que falharia antes:
   PHPUnit em `tests/Unit/` para regra/helper, Playwright em `tests/e2e/` para fluxo.

## 6. Relatório

Entregue: resumo em uma frase, linha do tempo enxuta (hora · tela · ação · resultado),
causa provável com `arquivo:linha`, passos para reproduzir, correção sugerida e o
teste. Se não reproduziu, diga o que faltou (ex.: gravação expirada, evento ausente).
