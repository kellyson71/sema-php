# Fluxos de Trabalho (Workflows)

O sistema opera em dois fluxos principais: Requerimentos e Denúncias.

## 1. Fluxo de Requerimentos (Cidadão)

O ciclo de vida de um requerimento segue os seguintes status:

1.  **Pendente**: O cidadão envia o formulário e os anexos via `index.php`. O processo ganha um número de protocolo único.
2.  **Em análise**: Um administrador abre o processo no painel administrativo. O sistema registra a primeira visualização.
3.  **Aprovado**: O administrador analisa os documentos e aprova o pedido. O sistema pode solicitar a emissão de taxas ou documentos complementares.
4.  **Apto a gerar alvará / Alvará Emitido**: Passos finais onde o documento oficial é gerado e assinado digitalmente.
5.  **Finalizado**: O processo é encerrado com sucesso.
6.  **Indeferido**: O processo é negado. Um e-mail automático é enviado ao requerente com o motivo e orientações.

### Notificações

- O sistema envia e-mails automáticos em cada mudança crítica de status via `EmailService`.
- Logs de envio de e-mail são registrados na tabela `email_logs`.

## 2. Fluxo de Denúncias (Interno)

As denúncias são ferramentas internas para a fiscalização:

- **Registro**: Criado via `nova_denuncia.php`.
- **Anexos**: Fotos e evidências de irregularidades ambientais.
- **Acompanhamento**: Visualização e histórico de ações tomadas pelos fiscais.

## 3. Fluxo de Assinatura Digital

Para garantir validade jurídica:

- O sistema gera um PDF do parecer/alvará.
- O **Secretário** revisa e aplica a assinatura (usando hash e registro em `assinaturas_digitais`).
- O documento final fica disponível para download pelo cidadão através da consulta de protocolo.
