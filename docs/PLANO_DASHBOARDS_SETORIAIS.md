# Plano de Melhoria dos Dashboards Setoriais (SEMA)

Este plano visa separar as interfaces do Setor 1 (Triagem), Setor 2 (Fiscalização) e Setor 3 (Secretário), tornando o sistema mais intuitivo e eficiente.

## 1. Redirecionamento Inteligente
- Modificar `admin/index.php` para identificar o nível do usuário e redirecionar automaticamente:
    - `fiscal` -> `fiscal_dashboard.php`
    - `secretario` -> `secretario_dashboard.php`
    - `analista/operador` -> Permanece no dashboard geral (ou novo dashboard simplificado).

## 2. Dashboard Setor 2 (Fiscalização de Obras)
- **Arquivo**: `admin/fiscal_dashboard.php`
- **Interface**:
    - 3 Cards de Status: "Novos do Triagem", "Devolvidos p/ Corrigir", "Prontos para Envio".
    - Tabela simplificada com foco em Protocolo, Requerente e Data.
    - **Ações Rápidas**:
        - Botão "Assinar e Enviar S3": Abre modal de assinatura, carrega conteúdo anterior, assina (mantendo assinatura do S1) e move para Setor 3.
        - Botão "Assinar e Finalizar": Para casos que não precisam de S3.
        - Link "Detalhes": Para análise profunda.

## 3. Dashboard Setor 3 (Secretário)
- **Arquivo**: `admin/secretario_dashboard.php`
- **Interface**:
    - Layout ultra-minimalista estilo To-Do List.
    - Foco total em processos aguardando assinatura.
    - **Ações Rápidas**:
        - Botão "Aprovar e Assinar": Assina (mantendo assinaturas S1 e S2) e devolve ao S2 para envio final.
        - Botão "Recusar / Devolver": Abre modal de motivo e devolve ao S2.

## 4. Implementação Técnica das Ações
- **Modal de Assinatura Rápida**: Criar um componente AJAX que:
    1. Busca a última assinatura/rascunho do processo.
    2. Permite ao usuário revisar/editar o texto rapidamente.
    3. Processa a nova assinatura via `processa_assinatura.php` passando o array de assinantes acumulados.
    4. Atualiza o fluxo via `fluxo_setor_handler.php`.

## 5. Refatoração de Visualização
- Simplificar o painel de ações em `admin/visualizar_requerimento.php` para que os botões sejam contextualizados com o setor atual.

---
**Próximos Passos**:
1. Reativar `admin/fiscal_dashboard.php` e `admin/secretario_dashboard.php`.
2. Implementar a lógica de redirecionamento no `index.php`.
3. Criar o layout dos novos dashboards.
