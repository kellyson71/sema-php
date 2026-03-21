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

## Roles de administrador

`admin`, `admin_geral`, `secretario`, `analista`, `fiscal`, `operador` — definidos no enum da tabela `administradores`. O menu lateral em `admin/header.php` exibe itens condicionalmente por role.

## Banco de dados

Schema completo em `database/u492577848_SEMA.sql`. Migrations incrementais em `database/*.sql`. Não existe ORM — usar PDO com prepared statements. A conexão do painel admin (`admin/conexao.php`) é separada da conexão pública (`includes/database.php`).

## Uploads

Arquivos ficam em `uploads/{protocolo}/` (formulário público) e `uploads/pareceres/{requerimento_id}/` (pareceres gerados). Apenas PDFs são aceitos, máximo 10MB. Validação dupla: extensão e MIME type.

## Branches

- `main` — produção
- `homologacao` — staging (branch ativa de desenvolvimento)
