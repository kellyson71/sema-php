# Fluxo de Assinatura Digital

Este documento detalha o funcionamento técnico e o fluxo de negócio do módulo de **Assinatura Digital** do sistema SEMA-PHP, garantindo integridade, autenticidade e validade jurídica aos documentos emitidos.

## 1. Validação de Identidade (Autenticação)

Para que um documento receba uma assinatura digital, o assinante (Técnico ou Secretário) deve passar por um rigoroso processo de validação:

- MFA (Multi-Factor Authentication): O sistema utiliza o protocolo **TOTP** (Time-based One-Time Password). O usuário deve configurar seu dispositivo (Google Authenticator, Authy, etc.) e fornecer um código de 6 dígitos.
- Consentimento e Sessão:
    - Ao validar o 2FA, o sistema cria uma sessão segura (`assinatura_auth_valid_until`) com validade de até **24 horas**.
    - Este período representa o consentimento explícito do usuário para realizar atos administrativos durante aquela jornada de trabalho.
    - Tentativas de assinatura fora desse prazo ou sem validação prévia são bloqueadas e registradas no log de erros.

## 2. Implementação Técnica

A assinatura não é apenas um "visto visual", mas uma operação criptográfica complexa.

### Criptografia e Hashing

- Algoritmo de Hash: **SHA-256**. O sistema gera uma impressão digital única (hash) do conteúdo binário do arquivo PDF.
- Criptografia Assimétrica: Utiliza chaves **RSA-2048**.
    - **Chave Privada**: Armazenada de forma protegida no servidor (pasta `includes/keys/`), usada exclusivamente para gerar a assinatura.
    - **Chave Pública**: Usada para verificar a autenticidade da assinatura sem expor a chave privada.

### O Ato de Assinar

Quando o Secretário aprova um processo:

1. O sistema lê o **hash** do documento original gerado pelo técnico.
2. Utiliza a função `openssl_sign` para cifrar esse hash com a chave privada do sistema, vinculando-o aos dados do Secretário (ID, Nome, Cargo).
3. Uma nova entrada é criada na tabela `assinaturas_digitais`, contendo a assinatura criptografada em Base64.

## 3. Fluxo de Trabalho (Workflow)

1. **Fase Técnica**: O consultor técnico analisa o requerimento e gera um "Parecer". O sistema assina automaticamente este parecer com o selo técnico.
2. **Revisão do Secretário**: O Secretário visualiza o documento através do `parecer_viewer.php`.
3. **Decisão**:
    - **Correção**: O Secretário devolve o processo com observações, reiniciando o fluxo técnico.
    - **Assinatura**: O Secretário confirma a emissão. O sistema "reassina" os documentos, elevando o status do processo para **Alvará Emitido**.

## 4. Auditoria e Verificação

- **Histórico de Assinaturas**: Cada tentativa (sucesso ou falha) é gravada na tabela `historico_assinaturas`, incluindo IP, User-Agent e timestamps.
- **Metadados JSON**: Além do banco de dados, o sistema gera um arquivo `.json` ao lado de cada PDF assinado com todos os metadados da assinatura para redundância.
- **Validação de Integridade**: O sistema possui uma função de verificação que recalcula o hash do arquivo atual e o compara com o hash assinado. Se o arquivo for alterado em um único Byte, a assinatura é invalidada automaticamente.

## 5. Segurança Jurídica

As assinaturas seguem padrões inspirados na ICP-Brasil, garantindo que o documento:

1. **Não possa ser alterado** (Integridade).
2. **Tenha autoria confirmada** (Autenticidade).
3. **Não possa ser repudiado** pelo assinante (Não-repúdio).
