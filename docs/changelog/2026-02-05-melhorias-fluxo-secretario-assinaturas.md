# 2026-02-05 - Melhorias no fluxo do secretário e assinaturas

## Resumo
Reestruturado o dashboard do secretário, revisão com visualização de documentos no `parecer_viewer.php`, verificação por email no fluxo de assinatura e logs detalhados de assinatura e códigos.

## Mudanças Implementadas
- Dashboard do secretário com resumo de pendências, filtros rápidos e fluxo mais claro.
- Revisão do secretário com lista de documentos e visualização direta no viewer.
- Verificação por email antes da assinatura do secretário.
- Nova tabela `historico_assinaturas` para logs completos de envio/validação de código e assinaturas.
- Validade da sessão de assinatura ampliada para 8 horas e texto atualizado em UI e emails.

## Arquivos Modificados
- `admin/secretario_dashboard.php`
- `admin/revisao_secretario.php`
- `admin/parecer_handler.php`
- `admin/processar_assinatura_secretario.php`
- `admin/visualizar_requerimento.php`
- `includes/email_service.php`
- `templates/email_verification_code.php`
- `docs/estrutura.sql`

## Novos Arquivos
- `database/criar_tabela_historico_assinaturas.sql`

## Testes Recomendados
1. Acessar o dashboard do secretário e validar filtros, contagens e navegação.
2. Abrir revisão do secretário e alternar documentos no viewer.
3. Tentar assinar sem sessão válida e confirmar bloqueio com nova verificação.
4. Enviar e validar código por email e concluir assinatura.
5. Verificar registros na tabela `historico_assinaturas`.
