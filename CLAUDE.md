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
