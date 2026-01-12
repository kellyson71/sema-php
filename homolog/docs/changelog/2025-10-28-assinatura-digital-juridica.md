# 2025-10-28 - Sistema de Assinatura Digital Juridicamente Válida

## O que foi implementado

Sistema completo de assinatura digital para documentos conforme Lei 14.063/2020 (Assinatura Eletrônica Avançada), tornando os pareceres técnicos juridicamente válidos.

## Mudanças Principais

### 1. Criptografia RSA-2048 + SHA-256

- Geração automática de chaves RSA no primeiro uso
- Chaves armazenadas em `/includes/keys/` com permissões restritas
- Hash SHA-256 calculado para cada documento
- Assinatura criptográfica do hash com chave privada

### 2. Banco de Dados

- Nova tabela `assinaturas_digitais` com todos os metadados
- Campos `cpf` e `cargo` adicionados à tabela `administradores`
- Índices para otimizar consultas de verificação

### 3. Metadados JSON

- Arquivo JSON salvo junto a cada PDF assinado
- Contém: documento_id, assinante, timestamp, hash, assinatura, algoritmos
- Backup redundante dos dados do banco

### 4. QR Code de Verificação

- Biblioteca `endroid/qr-code` integrada via Composer
- QR Code adicionado ao rodapé de cada documento
- Aponta para página pública de verificação

### 5. Página de Verificação Pública

- `/consultar/verificar.php` acessível sem login
- Verifica autenticidade recalculando hash
- Valida assinatura criptográfica
- Mostra informações do assinante e timestamp

### 6. Segurança Aprimorada

- Limite de 5 tentativas de login
- Bloqueio de 15 minutos após exceder tentativas
- Registro de IP do assinante
- Audit trail completo no histórico de ações

### 7. Interface do Usuário

- Bloco visual de assinatura no PDF com:
  - Título "ASSINATURA DIGITAL VÁLIDA JURIDICAMENTE"
  - Referência à Lei 14.063/2020
  - Assinatura visual (desenho ou texto)
  - Nome, CPF, cargo do assinante
  - Data/hora precisa
  - Hash SHA-256 completo
  - ID único do documento
  - QR Code para verificação

## Arquivos Criados

1. `database/assinaturas_digitais.sql` - Schema da tabela
2. `includes/assinatura_digital_service.php` - Serviço de criptografia RSA
3. `includes/qrcode_service.php` - Gerador de QR Codes
4. `includes/keys/.htaccess` - Proteção das chaves privadas
5. `consultar/verificar.php` - Página pública de verificação
6. `docs/ASSINATURA_DIGITAL.md` - Documentação completa
7. `docs/changelog/2025-10-28-assinatura-digital-juridica.md` - Este arquivo

## Arquivos Modificados

1. `composer.json` - Adicionada biblioteca `endroid/qr-code:^5.0`
2. `admin/parecer_handler.php` - Caso `gerar_pdf_com_assinatura` completamente reescrito
3. `admin/login.php` - Limite de tentativas e carregamento de CPF/cargo na sessão

## Fluxo Completo

1. Admin gera parecer técnico
2. Assina com desenho ou texto
3. Valida identidade com senha
4. Sistema:
   - Gera PDF preliminar
   - Calcula hash SHA-256
   - Assina com chave privada RSA
   - Salva no banco + JSON
   - Gera QR Code
   - Adiciona bloco de assinatura ao PDF
   - Regera PDF final
   - Atualiza hash final
5. Qualquer pessoa pode verificar via QR Code ou URL

## Impacto Legal

### Antes

- Assinatura visual apenas
- Sem garantia de integridade
- Sem rastreabilidade
- Sem validade jurídica forte

### Depois

- Assinatura eletrônica avançada (Lei 14.063/2020)
- Integridade garantida por hash SHA-256
- Rastreabilidade completa (quem, quando, onde)
- Validade jurídica dentro da prefeitura
- Verificação pública de autenticidade
- Detecção automática de adulteração

## Requisitos Técnicos

- PHP 7.4+ com extensão OpenSSL
- Biblioteca Composer: `endroid/qr-code:^5.0`
- Tabela `assinaturas_digitais` no banco
- Permissões de escrita em `/includes/keys/` e `/uploads/pareceres/`

## Backups Críticos

Sempre fazer backup de:

1. Banco de dados (especialmente `assinaturas_digitais`)
2. Pasta `/includes/keys/` (chaves RSA)
3. Pasta `/uploads/pareceres/` (PDFs + JSONs)

**ATENÇÃO**: Perda das chaves RSA tornará impossível verificar documentos antigos!

## Próximos Passos Opcionais

1. Integrar com ICP-Brasil (certificado digital) para assinatura qualificada
2. Adicionar carimbos de tempo (TSA) de terceiros
3. Implementar múltiplas assinaturas por documento
4. Criar workflow de aprovações

## Referências

- Lei 14.063/2020 - Lei de Assinaturas Eletrônicas
- RFC 3447 - RSA Cryptography Specifications
- RFC 6234 - SHA-256 Hash Algorithm
- ISO/IEC 14533 - Digital Signature Standards
