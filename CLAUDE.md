# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## O que é este projeto

Sistema de protocolo eletrônico de alvará ambiental para a Secretaria Municipal de Meio Ambiente (SEMA) de Pau dos Ferros/RN. Cidadãos submetem requerimentos, a equipe técnica analisa, gera pareceres e emite alvarás com assinatura digital.

## Comandos Docker (ambiente local)

```bash
./scripts/start.sh       # Sobe os containers e abre o navegador
./scripts/stop.sh        # Para os containers
./scripts/inject-sql.sh  # Injeta SQL no banco (padrão: database/u492577848_SEMA.sql)
./scripts/inject-sql.sh outro.sql  # Injeta SQL específico
```

Portas locais após `start.sh`:
- **App PHP** → http://localhost:8090
- **phpMyAdmin** → http://localhost:8091
- **MariaDB** → localhost:3307 (root/root)

O `includes/config.php` detecta `DOCKER_ENV=1` (passado pelo docker-compose) e troca automaticamente para as credenciais locais. Em produção usa as credenciais do Hostinger.

## Fluxo principal da aplicação

```
Cidadão → index.php (formulário)
       → processar_formulario.php (valida, salva no DB, envia email)
       → sucesso.php (exibe protocolo gerado)

Admin   → admin/login.php (bcrypt + 2FA TOTP ou email OTP)
       → admin/requerimentos.php (lista e filtra)
       → admin/visualizar_requerimento.php (ações: aprovar, indeferir, gerar parecer,
         abrir pendência de complementação, notas internas)
       → admin/gerar_documento.php (editor TinyMCE + templates HTML)
       → admin/parecer_handler.php (salva parecer, dispara assinatura digital)
       → admin/assinatura/ (workflow de assinatura com código por email)
       → admin/responsaveis_tecnicos.php (catálogo de engenheiros/arquitetos,
         alimentado automaticamente a cada requerimento)

Pendência/complementação → quando falta algo num requerimento, o admin abre uma
pendência (includes/pendencia_helpers.php); o requerente recebe um link para
responder e anexar documentos (pendencia.php); a equipe resolve manualmente ou
reabre a pendência a partir de uma anterior, mantendo o rastro (reaberta_de_id).

Público → consultar/index.php (consulta por protocolo)
        → consultar/verificar.php (valida QR code de documento assinado)
```

## Arquitetura de arquivos-chave

| Arquivo | Responsabilidade |
|---|---|
| `includes/config.php` | Constantes globais: DB, SMTP, reCAPTCHA, BASE_URL, detecção de ambiente |
| `includes/database.php` | Wrapper PDO com `query()`, `insert()`, `update()`, `getRow()`, `getRows()` |
| `includes/models.php` | Classes `Requerente`, `Proprietario`, `Requerimento`, `Documento` com CRUD |
| `includes/functions.php` | `gerarProtocolo()`, `salvarArquivo()`, `setMensagem()`, `redirect()`, `formatarStatus()` |
| `includes/email_service.php` | PHPMailer wrapper; loga tudo em `email_logs`; detecta emails de teste |
| `includes/parecer_service.php` | Geração de documentos: preenche variáveis `{{campo}}` nos templates HTML/DOCX |
| `includes/assinatura_digital_service.php` | Workflow de assinatura digital |
| `includes/documento_regras.php` | Regras de formatação/numeração usadas nos documentos gerados (endereço, área, numeração oficial) |
| `includes/notas_internas_helpers.php` | Chat/observações internas por requerimento, visível só à equipe |
| `includes/pendencia_helpers.php` | Pendência de complementação: abrir, listar, resolver, reabrir |
| `includes/public_form_components.php` | Componentes do formulário público (validação de e-mail, composer de endereço) |
| `tipos_alvara.php` | Array `$tipos_alvara` com nome legível, documentos e observações por tipo |
| `admin/conexao.php` | Conexão PDO do painel admin; cria tabelas de denúncias se não existirem |

## Tipos de alvará e campos dinâmicos

`tipos_alvara.php` define todos os tipos (construcao, habite_se, habite_se_simples, licenca_previa_ambiental, etc.). O formulário público (`index.php`) é um wizard de 3 etapas — 1) serviço e identificação, 2) dados do serviço, 3) documentos e envio — controlado por `js/public-form.js`. Ao selecionar um tipo, esse JS injeta campos específicos em `#campos_dinamicos` (área, responsável técnico, etc.) e carrega a lista de documentos via AJAX em `scripts/obter_documentos.php`. `window.SEMA_PUBLIC_FORM` expõe `showStep(n, {preview})`, `validateStep(n)`, `refresh()` e `restoreStep(n)` para navegação/depuração entre etapas; validação de etapa usa `.field-invalid`/`.field-error` (marcação própria via JS), não a validade nativa do HTML5.

**Nunca exibir o slug bruto** do banco (`habite_se_simples`) — sempre converter via:
```php
$tipos_alvara[$requerimento['tipo_alvara']]['nome'] ?? ucwords(str_replace('_', ' ', $slug))
```

## Sistema de pareceres (templates de documentos)

Templates HTML ficam em `admin/templates/`. Variáveis usam sintaxe `{{nome_variavel}}`.

O método `ParecerService::preencherDados($requerimento, $adminData)` em `includes/parecer_service.php` mapeia os campos do banco para as variáveis dos templates. Ao adicionar um novo campo ao formulário, verificar se precisa adicionar o mapeamento neste método.

Variáveis disponíveis nos templates (todas preenchidas por `ParecerService::preencherDados()`; campos vazios viram "Não informado" automaticamente):

- **Protocolo e status**: `{{protocolo}}`, `{{status}}`, `{{data_envio}}`, `{{data_atual}}`, `{{ano_atual}}`, `{{numero_documento_ano}}`, `{{protocolo_oficial}}`, `{{tipo_alvara}}` (nome legível)
- **Requerente / proprietário / interessado**: `{{nome_requerente}}`, `{{cpf_cnpj_requerente}}`, `{{email_requerente}}`, `{{telefone_requerente}}`, `{{nome_proprietario}}`, `{{cpf_cnpj_proprietario}}`, `{{nome_interessado}}`, `{{cpf_interessado}}` (proprietário, com fallback pro requerente)
- **Endereço e área**: `{{endereco_objetivo}}`, `{{area}}` / `{{area_construida}}`, `{{area_lote}}`, `{{area_total_terreno}}`, `{{area_remanescente}}`, `{{cadastro_imobiliario}}`, `{{matricula_imovel}}`, `{{desmembramento_matricula_texto}}`
- **Responsável técnico**: `{{responsavel_tecnico_nome}}`, `{{responsavel_tecnico_registro}}`, `{{responsavel_tecnico_tipo_documento}}`, `{{responsavel_tecnico_numero}}`, `{{responsavel_tecnico_conselho}}`, `{{responsavel_tecnico_rotulo}}`, `{{art_numero}}` (e-mail/telefone do RT são coletados no formulário mas não têm variável de template — nenhum template hoje usa)
- **Construção / habite-se / desmembramento**: `{{especificacao}}` / `{{detalhes_imovel}}`, `{{inicio_obra}}`, `{{termino_obra}}`, `{{alvara_construcao_numero}}`, `{{desmembramento_lotes_numeros}}`, `{{desmembramento_area_lotes}}`, `{{desmembramento_lotes_html}}`
- **Ambiental**: `{{atividade}}`, `{{cnae_descricao}}`, `{{eng_fiscal_nome}}`, `{{eng_fiscal_registro}}` (padrão configurável por `admin/configuracoes.php`, só no `carta_habite_se`)
- **Administrativas (quando `$adminData` é passado)**: `{{admin_nome_completo}}`, `{{admin_cargo}}`, `{{admin_matricula_portaria}}`, `{{observacoes}}`

## Métricas do formulário público (PostHog)

`js/form-analytics.js` expõe `window.SEMA_FORM_METRICS`; `js/public-form.js` chama os hooks
(sempre com `?.`, então o formulário funciona mesmo sem o PostHog carregado — é o caso do
ambiente local, onde `POSTHOG_KEY` é vazio e `includes/posthog.php` não renderiza nada).

Eventos emitidos: `form_iniciado`, `form_servico_selecionado`, `form_etapa_concluida`,
`form_etapa_voltou`, `form_validacao_falhou`, `form_documento_anexado`,
`form_documento_rejeitado`, `form_envio_bloqueado`, `form_enviado`, `form_abandonado`
(no `pagehide`) e `requerimento_concluido` (em `sucesso.php`).

⚠️ **Nenhum evento carrega valor digitado pelo cidadão.** Só saem nomes de campo, contagens,
tempos, extensões e tamanhos de arquivo — nome de arquivo fica de fora de propósito, porque
costuma conter o nome da pessoa. Toda propriedade nova tem que passar por essa mesma régua.

## Atividade dos usuários e reprodução de erros

Todo uso do painel admin fica registrado para dar para **reproduzir o passo a passo de
qualquer usuário** quando alguém relata um erro. Para investigar, use a skill
`reproduzir-sessao-usuario` ou o agente `sema-investigador-sessao` (ambos em `.claude/`).

- `includes/atividade_admin.php` — carregado por `includes/error_monitoring.php` (que o
  `config.php` de cada ambiente já inclui, então não precisa de FTP). No fim de cada
  requisição em `/admin/` grava uma linha em `admin_eventos`: página, nome da ação
  (`acao`/`action`), id do registro, status HTTP, duração, cargo e o id da sessão do PostHog.
  **Nunca grava valor enviado em formulário** — mantenha assim ao mexer nele.
- `admin/ajax/atividade_ping.php` + script no `admin/header.php` — soma o **tempo ativo**
  (aba visível e mouse/teclado no último minuto) em `admin_atividade_diaria`, no máximo
  120 s por envio.
- `admin/atividade_usuarios.php` — só `admin`/`admin_geral`: gráfico estilo GitHub por
  pessoa (tempo ativo, ações do histórico, páginas), resumo da equipe e linha do tempo do dia.
- `includes/posthog.php` — no painel, a **gravação de sessão do PostHog está ligada**,
  com logs de console e captura de exceções do navegador. O cookie `sema_ph_sid` leva o
  id da sessão ao servidor, que o grava em `admin_eventos.posthog_session_id` — assim cada
  passo no banco aponta para o trecho certo da gravação. Pessoa no PostHog = `admin_<id>`.
  Projeto no PostHog: **SEMA, id `509259`** (a mesma conta tem o projeto Curta PDF — não confundir).
  A tela de atividade tem "Ver gravação" em cada passo (`https://us.posthog.com/project/509259/replay/<session_id>`)
  e "Gravações no PostHog" na página da pessoa (`/project/509259/person/admin_<id>`).
  No projeto, a gravação está ligada só para os domínios de produção e só dispara em URLs com
  `/admin/` (gatilho de URL) — o site público não é gravado nem se o código mudar.
- Migration: `database/2026-09-25_atividade_admin.sql`. Eventos com mais de 180 dias são
  apagados pelo próprio ping (retenção LGPD).

⚠️ **LGPD:** por decisão de 2026-09-25, a gravação mascara só os **campos** (o que é
digitado). O **texto das telas** — nomes e CPF de cidadão nas listas e detalhes — aparece
na gravação e vai para o PostHog. Para esconder um trecho específico, coloque a classe
`ph-no-capture` no elemento. No site público a gravação continua desligada.

## Retificação de documento assinado

Depois de assinado e entregue, um documento ainda pode ser corrigido. Em
`admin/documentos/selecionar.php` os documentos vigentes do processo aparecem no
histórico com "Corrigir e reemitir" (`template=assinado:{documento_id}`); o editor
reabre o HTML original — guardado em `admin/pareceres/{req_id}/{documento_id}.html`
no momento da assinatura — e a reemissão **mantém o mesmo número**.

Ao assinar a retificação, `admin/assinatura/processa_assinatura.php`:
- repõe o `document_numbers` daquele número para o novo `documento_id`;
- marca a versão anterior em `assinaturas_digitais` (`substituido_por_documento_id`,
  `substituido_em`, `substituido_por_admin_id`, `motivo_substituicao`);
- o `/verificar` passa a mostrar "Documento retificado" na versão antiga, com link
  para a vigente — a assinatura dela continua íntegra, mas ela não vale mais.

Reusar um número de **outro** processo continua bloqueado (erro 409).
Documentos assinados antes desta funcionalidade não têm o HTML guardado e por isso
não aparecem para retificação — para eles, só emitindo um documento novo.

## Roles de administrador

`admin`, `admin_geral`, `secretario`, `analista`, `fiscal`, `operador` — definidos no enum da tabela `administradores`. O menu lateral em `admin/header.php` exibe itens condicionalmente por role.

## Banco de dados

Schema completo em `database/u492577848_SEMA.sql`. Migrations incrementais em `database/*.sql`. Não existe ORM — usar PDO com prepared statements. A conexão do painel admin (`admin/conexao.php`) é separada da conexão pública (`includes/database.php`).

⚠️ `database/u492577848_SEMA.sql` é um snapshot que fica defasado — não é regenerado a cada migration. Para saber o schema exato de um ambiente, some esse arquivo com todos os `database/*.sql` datados mais novos (ordem cronológica pelo nome do arquivo), ou confira direto no banco (`SHOW CREATE TABLE`). Produção tende a ficar atrás de homologação: antes de promover `homologacao` para `main`, conferir quais migrations ainda não foram rodadas em produção.

## Uploads

Arquivos ficam em `uploads/{protocolo}/` (formulário público) e `uploads/pareceres/{requerimento_id}/` (pareceres gerados). Apenas PDFs são aceitos, máximo 10MB. Validação dupla: extensão e MIME type.

## Credenciais dos bancos de dados

| Ambiente | Host | Usuário | Senha | Banco |
|---|---|---|---|---|
| Homologação | `srv1844.hstgr.io` | `u492577848_SEMA_hmg` | `Kellys0n_123` | `u492577848_SEMA_hmg` |
| Produção | `srv1844.hstgr.io` | `u492577848_SEMA` | `Pmpfestagio2021` | `u492577848_SEMA` |
| Docker local | `db` | `user` | `password` | `u492577848_SEMA` |

As demais credenciais (SMTP, Hostinger Mail API e reCAPTCHA) estão em **`CREDENCIAIS_LOCAL.md`**, na raiz do projeto. Esse arquivo é ignorado pelo Git e não deve ser versionado.

## Acesso ao banco de dados via SSH

Produção e homologação usam **bancos separados** no mesmo host `srv1844.hstgr.io`:
`u492577848_SEMA` (produção) e `u492577848_SEMA_hmg` (homologação). Migrations em homologação não afetam produção.

Credenciais do banco estão na tabela acima. Forma do comando:
```bash
ssh -p 65002 -i ~/.ssh/id_ed25519 u492577848@46.202.145.215 \
  "mysql -h srv1844.hstgr.io -u USUARIO -pSENHA BANCO -e 'SUA QUERY;'"
```

Deploy manual (quando o painel falhar):
```bash
ssh -p 65002 -i ~/.ssh/id_ed25519 u492577848@46.202.145.215 \
  "cd ~/domains/sema.protocolosead.com/public_html && git pull"
```

## Deploy

Ao concluir alterações, **sempre fazer commit e push automaticamente** (sem perguntar). O servidor de produção faz `git pull` via SSH:

```bash
ssh -p 65002 -i ~/.ssh/id_ed25519 u492577848@46.202.145.215 \
  "cd ~/domains/sema.protocolosead.com/public_html && git pull"
```

**Arquivos no `.gitignore`** (como `includes/config.php` e `admin/conexao.php`) não vão pelo git. Se forem modificados, atualizar via FTP (credenciais em `ACESSOS.md`):

```bash
lftp -u "USUARIO,SENHA" ftp://HOST -e \
  "set ftp:ssl-allow no; put arquivo_local -o includes/config.php; quit"
```

Cada ambiente tem sua própria conta FTP, chrootada no respectivo `public_html` — a de produção não alcança homologação. E os dois `config.php` são arquivos distintos, não o mesmo com valores trocados: **uma constante nova precisa ser adicionada manualmente nos dois**.

## Branches

- `main` — produção
- `homologacao` — staging (branch ativa de desenvolvimento)

## Estrutura de deploy no servidor

O servidor tem dois ambientes em domínios separados:

- `~/domains/sema.protocolosead.com/public_html/` → branch **main** (produção)
- `~/domains/semaholog.protocolosead.com/public_html/` → branch **homologacao** (staging)

> ⚠️ A pasta `sema.protocolosead.com/public_html/homologacao/` existe mas NÃO é o ambiente de homologação ativo.  
> O ambiente real de homologação é **semaholog.protocolosead.com**.

Deploy da branch `homologacao`:
```bash
ssh -p 65002 -i ~/.ssh/id_ed25519 u492577848@46.202.145.215 \
  "cd ~/domains/semaholog.protocolosead.com/public_html && git pull"
```
