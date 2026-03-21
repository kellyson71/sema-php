# Fluxo de Usuários — SEMA

Este documento descreve os três perfis de usuário do sistema SEMA, o que cada um faz, suas permissões e como o fluxo de um requerimento avança de ponta a ponta.

---

## 1. Perfis de Usuário

O sistema possui três perfis armazenados na coluna `nivel` da tabela `administradores`:

| Perfil | Valor no banco | Papel |
|---|---|---|
| **Admin** | `admin` | Administração completa do sistema |
| **Operador** (Triagem / Alvará) | `operador` | Análise técnica e geração do parecer |
| **Secretário** | `secretario` | Assinatura final e emissão do alvará |

> **Nota:** O redirecionamento do secretário é feito tanto pelo `nivel = 'secretario'` quanto por `email = 'secretario@sema.rn.gov.br'` (compatibilidade legada).

---

## 2. Fluxo Completo de um Requerimento

```
Cidadão envia formulário (index.php)
        │
        ▼
Status: "Em análise"
   ← OPERADOR age aqui →
        │
        ├─── Indeferido ──────────────────────────────────► FIM
        │     └─ Email: email_indeferimento.php
        │
        └─── "Apto a gerar alvará"
              └─ Email: email_aprovado.php (notifica cidadão)
                      │
                      ▼
               ← SECRETÁRIO age aqui →
                      │
                      └─── "Alvará Emitido"
                            └─ Email: email_protocolo_oficial.php
                                      │
                                      ▼
                                    FIM
```

### Todos os status possíveis

| Status | Quem define | Significado |
|---|---|---|
| `Em análise` | Sistema (ao receber) | Aguarda análise técnica |
| `Apto a gerar alvará` | Operador | Aprovado tecnicamente; aguarda secretário |
| `Alvará Emitido` | Secretário | Alvará assinado e emitido |
| `Indeferido` | Operador | Processo rejeitado com motivo |
| `Finalizado` | Operador / Admin | Protocolo oficial enviado ao cidadão |
| `Arquivado` | Admin | Movido para arquivo morto |

---

## 3. Perfil: Operador (Triagem / Análise Técnica)

### O que faz
O operador é quem realiza a triagem e análise técnica dos requerimentos recebidos. Ele verifica a documentação, elabora o parecer técnico e decide se o processo avança ou é indeferido.

### Acesso após login
- Redireciona para **`admin/index.php`** (dashboard principal com estatísticas gerais)

### Permissões

| Ação | Permitido? |
|---|---|
| Ver lista de requerimentos | ✅ |
| Abrir detalhes de um requerimento | ✅ |
| Criar / editar parecer técnico | ✅ |
| Assinar documento com OTP | ✅ |
| Alterar status → `Apto a gerar alvará` | ✅ |
| Alterar status → `Indeferido` | ✅ |
| Enviar email de indeferimento | ✅ |
| Enviar email de protocolo oficial | ✅ |
| Emitir alvará | ❌ |
| Gerenciar usuários | ❌ |
| Acessar estatísticas | ❌ |

### Arquivos principais
- `admin/requerimentos.php` — lista de requerimentos
- `admin/visualizar_requerimento.php` — detalhes + ações
- `admin/parecer_handler.php` — AJAX para criar/assinar documentos
- `admin/parecer_viewer.php` — visualização do parecer

### Fluxo passo a passo

1. Faz login → vai para `admin/index.php`
2. Clica em um requerimento (status `Em análise`)
3. Visualiza dados do requerente e documentos enviados
4. Cria o parecer técnico no editor (TinyMCE)
5. Solicita OTP por email → valida código → assina o documento
6. PDF assinado é salvo em `uploads/pareceres/{id}/`
7. Escolhe:
   - **Aprovar**: muda status para `Apto a gerar alvará` → e-mail de notificação enviado
   - **Indeferir**: muda status para `Indeferido` + motivo → e-mail de indeferimento enviado

---

## 4. Perfil: Secretário

### O que faz
O secretário recebe os processos aprovados tecnicamente (`Apto a gerar alvará`), revisa o parecer técnico já assinado pelo operador e emite o alvará com sua assinatura digital.

### Acesso após login
- Redireciona **automaticamente** para **`admin/secretario_dashboard.php`** (via `admin/index.php` linhas 14–17)

### Permissões

| Ação | Permitido? |
|---|---|
| Ver requerimentos (`Apto a gerar alvará` e `Alvará Emitido`) | ✅ |
| Revisar parecer técnico assinado | ✅ |
| Assinar documento com OTP | ✅ |
| Emitir alvará (`Alvará Emitido`) | ✅ |
| Ver histórico de alvarás emitidos | ✅ |
| Criar / editar pareceres | ❌ |
| Indeferir requerimentos | ❌ |
| Gerenciar usuários | ❌ |
| Ver estatísticas ou logs | ❌ |

### Arquivos principais
- `admin/secretario_dashboard.php` — painel exclusivo do secretário
- `admin/revisao_secretario.php` — tela de revisão e assinatura
- `admin/processar_assinatura_secretario.php` — processa assinatura e emite alvará

### Fluxo passo a passo

1. Faz login → redirecionado automaticamente para `secretario_dashboard.php`
2. Vê dois grupos: **Pendentes** (apto a gerar) e **Emitidos** (já assinados)
3. Clica em "Revisar e Assinar" em um processo pendente
4. Visualiza o parecer técnico já assinado pelo operador
5. Solicita OTP por email → valida código (válido por 8 horas para assinar múltiplos)
6. Confirma assinatura → status muda para `Alvará Emitido`
7. E-mail com protocolo oficial é enviado ao cidadão

---

## 5. Perfil: Admin

### O que faz
Acesso total ao sistema. Pode fazer tudo que operador e secretário fazem, além de gerenciar usuários, ver estatísticas e logs de email.

### Permissões

| Ação | Permitido? |
|---|---|
| Todas as ações do Operador | ✅ |
| Todas as ações do Secretário | ✅ |
| Gerenciar usuários (criar/editar/desativar) | ✅ |
| Ver estatísticas e relatórios | ✅ |
| Ver logs de email | ✅ |
| Reenviar emails | ✅ |
| Arquivar requerimentos | ✅ |
| Ações em massa | ✅ |

### Arquivos principais
- `admin/administradores.php` — gerenciamento de usuários
- `admin/estatisticas.php` — dashboards e gráficos
- `admin/logs_email.php` — histórico de emails enviados
- `admin/resend_emails.php` — reenvio de emails

---

## 6. Lógica de Templates de Email

### Templates existentes

| Arquivo | Quando é enviado | Destinatário | Variáveis principais |
|---|---|---|---|
| `email_protocolo.php` | Ao receber o requerimento | Cidadão | `$nome`, `$protocolo`, `$tipo_alvara`, `$dados` |
| `email_aprovado.php` | Ao aprovar tecnicamente (`Apto a gerar alvará`) | Cidadão | `$nome_destinatario`, `$protocolo`, `$tipo_alvara` |
| `email_indeferimento.php` | Ao indeferir o processo | Cidadão | `$nome_destinatario`, `$protocolo`, `$tipo_alvara`, `$motivo_indeferimento`, `$orientacoes_adicionais` |
| `email_protocolo_oficial.php` | Ao emitir o alvará (`Alvará Emitido`) | Cidadão | `$nome_destinatario`, `$protocolo_oficial` |
| `email_pendencia.php` | Ao solicitar documentação complementar | Cidadão | `$nome_destinatario`, `$protocolo`, `$tipo_alvara`, `$pendencias` |
| `email_reenvio.php` | Ao reabrir/devolver um processo para correção | Cidadão | `$nome_destinatario`, `$protocolo`, `$tipo_alvara`, `$motivo_reenvio` |
| `email_verification_code.php` | Ao solicitar assinatura digital (OTP) | Administrador | `$to_name`, `$codigo` |

### Como os templates são carregados

A classe `EmailService` (em `includes/email_service.php`) usa `ob_start()` / `ob_get_clean()` para incluir cada template PHP e capturar o HTML gerado como string. Esse HTML é então enviado via SMTP com PHPMailer.

```php
// Padrão usado por todos os métodos de carregamento de template
private function carregarTemplateNomeTemplate($nome_destinatario, $protocolo, $tipo_alvara /*, outros parâmetros */)
{
    ob_start();
    include __DIR__ . '/../templates/nome_template.php';
    return ob_get_clean();
}
```

As variáveis passadas como parâmetros ficam disponíveis dentro do arquivo de template pelo escopo do `include`.

### Métodos da classe EmailService

| Método | Template usado |
|---|---|
| `enviarEmailProtocolo()` | `email_protocolo.php` |
| `enviarEmailProtocoloOficial()` | `email_protocolo_oficial.php` |
| `enviarEmailIndeferimento()` | `email_indeferimento.php` |
| `enviarEmailAprovado()` | `email_aprovado.php` |
| `enviarEmailPendencia()` | `email_pendencia.php` |
| `enviarEmailReenvio()` | `email_reenvio.php` |
| `enviarEmailCodigoVerificacao()` | HTML inline (não usa arquivo de template) |

---

## 7. Variáveis de Sessão

Após o login, as seguintes variáveis ficam disponíveis em `$_SESSION`:

| Variável | Conteúdo |
|---|---|
| `$_SESSION['admin_id']` | ID do usuário |
| `$_SESSION['admin_nome']` | Nome de exibição |
| `$_SESSION['admin_nome_completo']` | Nome completo |
| `$_SESSION['admin_email']` | Email |
| `$_SESSION['admin_nivel']` | Perfil: `admin`, `operador` ou `secretario` |
| `$_SESSION['admin_cpf']` | CPF |
| `$_SESSION['admin_cargo']` | Cargo |
| `$_SESSION['admin_matricula_portaria']` | Matrícula/Portaria |

---

## 8. Pontos de Melhoria Identificados

- **Perfis mais granulares**: Separar "triagem" (recepção/conferência de documentos) de "análise técnica" (elaboração do parecer) poderia refletir melhor a realidade do fluxo presencial.
- **Notificação no aprovado**: Atualmente o status `Apto a gerar alvará` não dispara email automático ao cidadão — o template `email_aprovado.php` foi criado para possibilitar isso.
- **Pendências**: O fluxo não possui status explícito de "pendente de documentação" — o template `email_pendencia.php` e o método `enviarEmailPendencia()` foram criados para possibilitar a comunicação de pendências sem indeferir o processo.
- **Reenvio/Devolução**: Há a funcionalidade de devolver processos para correção técnica, mas sem um template de email padronizado — o `email_reenvio.php` e `enviarEmailReenvio()` foram criados para suprir essa necessidade.
- **Código OTP inline**: O template do código de verificação está embutido diretamente no método `enviarEmailCodigoVerificacao()` — poderia ser migrado para `templates/email_verification_code.php` (o arquivo já existe) para manter consistência.
