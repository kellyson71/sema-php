# Estudo do Fluxo do Secretário (Assinatura Digital)

Este documento descreve o fluxo de trabalho do usuário com nível de acesso **Secretário** no sistema SEMA-PHP. Este é o segundo fluxo principal do sistema, focado na revisão final e assinatura jurídica dos documentos técnicos (alvarás e licenças).

## 1. Acesso e Segurança (MFA)

Diferente do usuário comum, o Secretário possui uma camada de segurança reforçada integrada ao processo de login.

- **Login**: Realizado em `admin/login.php`.
- **Autenticação de Dois Fatores (2FA)**: É obrigatória para o nível de secretário.
    - **Métodos**: Código de 6 dígitos via E-mail ou App Autenticador (TOTP).
    - **Validade da Sessão de Assinatura**: Ao realizar o login com sucesso via MFA, o sistema define a variável `$_SESSION['assinatura_auth_valid_until']` com validade de **24 horas**.
    - **Benefício**: Isso permite que o Secretário assine múltiplos processos durante o dia sem a necessidade de inserir um novo código para cada documento individual, mantendo a agilidade sem comprometer a segurança.

## 2. Dashboard do Secretário

A interface principal (`admin/secretario_dashboard.php`) é simplificada e focada na fila de assinaturas.

- **Filtros de Status**:
    - **Apto a gerar alvará**: Processos que já receberam o parecer favorável do técnico e aguardam a assinatura do Secretário.
    - **Alvará Emitido**: Processos que já foram assinados e concluídos.
- **Indicadores**: Exibe o total de processos pendentes e emitidos para controle de demanda.

## 3. Fluxo de Revisão e Assinatura

Ao selecionar um processo "Apto a gerar alvará", o Secretário entra na tela de revisão (`admin/revisao_secretario.php`).

### 3.1. Visualização Técnica

- O Secretário pode visualizar os documentos gerados pelo setor técnico através de um iframe que consome o `parecer_viewer.php`.
- É possível alternar entre diferentes documentos do mesmo processo para conferência.

### 3.2. Ações Possíveis

As ações são processadas por `admin/processar_assinatura_secretario.php`:

1.  **Assinar Tudo e Emitir (Aprovar)**:
    - O sistema verifica se a sessão de assinatura ainda é válida (dentro das 24h).
    - Utiliza o `AssinaturaDigitalService` para gerar uma nova assinatura criptografada sobre o hash do documento original.
    - Registra a assinatura na tabela `assinaturas_digitais` com o cargo "Secretário Municipal de Meio Ambiente".
    - Atualiza o status do requerimento para **"Alvará Emitido"**.
    - Registra o evento no histórico de ações e no log de assinaturas.

2.  **Solicitar Correção (Devolver)**:
    - Caso identifique erros, o Secretário pode devolver o processo.
    - O status do requerimento retorna para **"Em análise"**.
    - Uma observação em caixa alta "DEVOLVIDO PELO SECRETÁRIO" é adicionada ao processo com o motivo da devolução.

## 4. Diferenças Cruciais em Relação ao Fluxo Técnico

| Característica   | Fluxo Técnico (Operador)                        | Fluxo de Assinatura (Secretário)          |
| :--------------- | :---------------------------------------------- | :---------------------------------------- |
| **Objetivo**     | Analisar dados e gerar o documento.             | Validar juridicamente e assinar.          |
| **MFA**          | Geralmente opcional no ato ou por sessão curta. | Obrigatório no login com validade de 24h. |
| **Status Final** | Apto a gerar alvará.                            | Alvará Emitido.                           |
| **Documentação** | Cria o conteúdo do parecer.                     | Assina o hash do documento já existente.  |

## 5. Arquivos Envolvidos

- `admin/login.php`: Gestão de acesso e desafio MFA.
- `admin/secretario_dashboard.php`: Fila de trabalho e visibilidade de status.
- `admin/revisao_secretario.php`: Interface de leitura e botões de decisão.
- `admin/processar_assinatura_secretario.php`: Motor de regras de negócio para a assinatura.
- `includes/assinatura_digital_service.php`: Lógica criptográfica de assinatura.
