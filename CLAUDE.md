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
       → admin/visualizar_requerimento.php (ações: aprovar, indeferir, gerar parecer)
       → admin/gerar_documento.php (editor TinyMCE + templates HTML)
       → admin/parecer_handler.php (salva parecer, dispara assinatura digital)
       → admin/assinatura/ (workflow de assinatura com código por email)

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
| `tipos_alvara.php` | Array `$tipos_alvara` com nome legível, documentos e observações por tipo |
| `admin/conexao.php` | Conexão PDO do painel admin; cria tabelas de denúncias se não existirem |

## Tipos de alvará e campos dinâmicos

`tipos_alvara.php` define todos os tipos (construcao, habite_se, habite_se_simples, licenca_previa_ambiental, etc.). Ao selecionar um tipo no formulário, o JS em `index.php` injeta campos específicos em `#campos_dinamicos` (área, responsável técnico, etc.) e carrega a lista de documentos via AJAX em `scripts/obter_documentos.php`.

**Nunca exibir o slug bruto** do banco (`habite_se_simples`) — sempre converter via:
```php
$tipos_alvara[$requerimento['tipo_alvara']]['nome'] ?? ucwords(str_replace('_', ' ', $slug))
```

## Sistema de pareceres (templates de documentos)

Templates HTML ficam em `admin/templates/`. Variáveis usam sintaxe `{{nome_variavel}}`.

O método `ParecerService::preencherDados($requerimento, $adminData)` em `includes/parecer_service.php` mapeia os campos do banco para as variáveis dos templates. Ao adicionar um novo campo ao formulário, verificar se precisa adicionar o mapeamento neste método.

Variáveis disponíveis nos templates:
`{{protocolo}}`, `{{nome_requerente}}`, `{{cpf_cnpj_requerente}}`, `{{nome_proprietario}}`, `{{cpf_cnpj_proprietario}}`, `{{endereco_objetivo}}`, `{{tipo_alvara}}` (nome legível), `{{area}}` / `{{area_construida}}`, `{{detalhes_imovel}}` / `{{especificacao}}`, `{{responsavel_tecnico_nome}}`, `{{responsavel_tecnico_registro}}`, `{{responsavel_tecnico_tipo_documento}}`, `{{responsavel_tecnico_numero}}`, `{{art_numero}}`, `{{numero_documento_ano}}`, `{{data_atual}}`, `{{atividade}}`, `{{nome_interessado}}`, `{{cpf_interessado}}`

## Roles de administrador e Setores

`admin`, `admin_geral`, `secretario`, `analista`, `fiscal`, `operador` — definidos no enum da tabela `administradores`. O menu lateral em `admin/header.php` exibe itens condicionalmente por role.

### Mapeamento de Setores para Roles

| Setor | Role | Responsabilidade |
|---|---|---|
| **Setor 1** | `analista` (+ `admin`, `admin_geral`) | Triagem central — recebe processos, analisa, gera pareceres técnicos, envia boleto, indefe. Pode encaminhar para Setor 2. |
| **Setor 2** | `fiscal` | Fiscalização de Obras — recebe processos encaminhados pelo Setor 1, gera documentos, pode encaminhar para Setor 3 ou finalizar entregando documento ao cidadão. |
| **Setor 3** | `secretario` | Secretaria — recebe processos do Setor 2, revisa, assina documentos e aprova ou devolve ao Setor 2. |

### Fluxo de Status entre Setores

```
Pendente → Em análise (Setor 1)
         → Aguardando Fiscalização (Setor 1 encaminha para Setor 2)
         → Aguardando Secretaria (Setor 2 encaminha para Setor 3)
         → Devolvido pela Secretaria (Setor 3 devolve para Setor 2)
         → Documento Final Enviado (Setor 2 finaliza entregando doc ao cidadão)
         → Finalizado (Setor 1 envia protocolo oficial)
```

### Variáveis de Setor (disponíveis após `include 'header.php'`)

```php
$isSetor1  // analista + admin + admin_geral
$isSetor2  // fiscal + admin + admin_geral
$isSetor3  // secretario + admin + admin_geral
```

Em `admin/requerimentos.php` e qualquer arquivo que precise detectar o setor **antes** do `include 'header.php'`, usar `$_SESSION['admin_nivel']` diretamente.

### Multi-assinatura de documentos

A tabela `assinaturas_digitais` permite múltiplos assinantes por documento (UNIQUE em `documento_id + assinante_id`). O mesmo usuário não pode assinar o mesmo documento duas vezes. Solicitações de assinatura ficam na tabela `solicitacoes_assinatura`.

### Páginas de entrega ao cidadão

- `pagamento.php` — boleto (token via `gerarTokenPagamento()`)
- `documento_final.php` — documento final do Setor 2 (token via `gerarTokenDocumentoFinal()`)

## Banco de dados

Schema completo em `database/u492577848_SEMA.sql`. Migrations incrementais em `database/*.sql`. Não existe ORM — usar PDO com prepared statements. A conexão do painel admin (`admin/conexao.php`) é separada da conexão pública (`includes/database.php`).

## Uploads

Arquivos ficam em `uploads/{protocolo}/` (formulário público) e `uploads/pareceres/{requerimento_id}/` (pareceres gerados). Apenas PDFs são aceitos, máximo 10MB. Validação dupla: extensão e MIME type.

## Acesso ao banco de dados em produção via SSH

```bash
ssh -p 65002 -i ~/.ssh/id_ed25519 u492577848@46.202.145.215 \
  "mysql -h srv1844.hstgr.io -u u492577848_SEMA -pPmpfestagio2021 u492577848_SEMA -e 'SUA QUERY;'"
```

Exemplo — listar tabelas:
```bash
ssh -p 65002 -i ~/.ssh/id_ed25519 u492577848@46.202.145.215 \
  "mysql -h srv1844.hstgr.io -u u492577848_SEMA -pPmpfestagio2021 u492577848_SEMA -e 'SHOW TABLES;'"
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

**Arquivos no `.gitignore`** (como `includes/config.php` e `admin/conexao.php`) não vão pelo git. Se forem modificados, atualizar via FTP:

```bash
lftp -u "u492577848.semapmpfestagio,Pmpfestagio2021" ftp://46.202.145.215 -e \
  "set ftp:ssl-allow no; put arquivo_local -o public_html/caminho/arquivo; quit"
```

## Branches

- `main` — produção
- `homologacao` — staging (branch ativa de desenvolvimento)
