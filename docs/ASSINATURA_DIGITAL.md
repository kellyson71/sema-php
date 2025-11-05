# Sistema de Assinatura Digital - SEMA

## Conformidade Legal

- Lei 14.063/2020 - Assinatura Eletrônica Avançada
- Tecnologia: RSA-2048 + SHA-256
- Validade: Juridicamente aceita dentro da prefeitura

## Como Funciona

### 1. Assinatura

- Admin cria documento (parecer técnico)
- Desenha ou digita assinatura
- Valida com senha
- Sistema gera hash SHA-256 do PDF
- Assina hash com chave privada RSA
- Salva metadados JSON
- Adiciona QR Code ao documento

### 2. Verificação

- Qualquer pessoa pode verificar em /consultar/verificar.php
- Sistema recalcula hash do PDF
- Valida assinatura com chave pública
- Mostra status: válido ou adulterado

## Arquivos Importantes

- `/includes/keys/private.pem` - Chave privada (NÃO compartilhar)
- `/includes/keys/public.pem` - Chave pública (pode ser pública)
- `/uploads/pareceres/{id}/*.json` - Metadados de cada documento

## Backup

Sempre fazer backup de:

- Banco de dados (tabela assinaturas_digitais)
- Pasta /includes/keys/
- Pasta /uploads/pareceres/

## Segurança Implementada

### Criptografia

- RSA-2048 bits para assinatura digital
- SHA-256 para hash do documento
- Chaves armazenadas com permissões restritas (0600 para privada, 0644 para pública)

### Controle de Acesso

- Limite de 5 tentativas de login
- Bloqueio de 15 minutos após exceder tentativas
- Validação de senha para assinar documentos
- Opção "lembrar senha na sessão"

### Integridade

- Hash SHA-256 calculado no momento da assinatura
- Recalculado na verificação para detectar alterações
- Metadados JSON salvos junto ao PDF

### Rastreabilidade

- IP do assinante registrado
- Timestamp preciso da assinatura
- Histórico de ações no banco de dados
- ID único para cada documento

## Fluxo de Assinatura

1. Admin seleciona template de parecer
2. Edita conteúdo no editor TinyMCE
3. Clica em "Continuar para Assinatura"
4. Escolhe tipo de assinatura (desenho ou texto)
5. Insere senha para validar identidade
6. Sistema:
   - Gera PDF preliminar
   - Calcula hash SHA-256
   - Assina hash com chave privada RSA
   - Salva registro no banco
   - Salva metadados JSON
   - Gera QR Code com URL de verificação
   - Adiciona bloco de assinatura ao PDF
   - Regera PDF final
   - Atualiza hash final no banco

## Verificação Pública

### URL de Acesso

`https://seudominio.com/consultar/verificar.php?id={DOCUMENTO_ID}`

### Processo de Verificação

1. Busca documento no banco de dados
2. Verifica se arquivo físico existe
3. Recalcula hash SHA-256 do PDF atual
4. Compara com hash armazenado
5. Valida assinatura criptográfica com chave pública
6. Retorna resultado: válido ou adulterado

### Informações Exibidas

- Status de autenticidade
- Nome do assinante
- CPF e cargo
- Data/hora da assinatura
- Hash SHA-256
- ID do documento

## Estrutura do Banco de Dados

### Tabela: assinaturas_digitais

```sql
- id: int(11) AUTO_INCREMENT
- documento_id: varchar(64) UNIQUE
- requerimento_id: int(11)
- tipo_documento: varchar(50)
- nome_arquivo: varchar(255)
- caminho_arquivo: varchar(500)
- hash_documento: varchar(64)
- assinante_id: int(11)
- assinante_nome: varchar(255)
- assinante_cpf: varchar(20)
- assinante_cargo: varchar(100)
- tipo_assinatura: enum('desenho','texto')
- assinatura_visual: text
- assinatura_criptografada: text
- timestamp_assinatura: timestamp
- ip_assinante: varchar(45)
- metadados_json: text
- data_criacao: timestamp
```

## Metadados JSON

Exemplo de arquivo `.json` salvo junto ao PDF:

```json
{
  "documento_id": "a1b2c3d4e5f6...",
  "signer": "Kellyson Raphael",
  "cpf": "123.456.789-00",
  "role": "Administrador",
  "timestamp": "2025-10-28T12:30:00-03:00",
  "ip": "192.168.1.100",
  "hash_algorithm": "SHA-256",
  "signature_algorithm": "RSA-2048",
  "hash": "a1b2c3d4e5f6...",
  "signature": "base64EncodedSignature...",
  "tipo_documento": "parecer",
  "requerimento_id": 123
}
```

## Manutenção

### Gerar Novas Chaves RSA

Se necessário gerar novas chaves (perda ou comprometimento):

1. Deletar arquivos em `/includes/keys/`
2. Sistema gerará automaticamente no próximo uso
3. **IMPORTANTE**: Documentos assinados com chaves antigas não poderão ser verificados

### Auditoria

Consultar tabela `assinaturas_digitais` para:

- Listar todos os documentos assinados
- Verificar quem assinou cada documento
- Rastrear IPs de assinaturas
- Identificar documentos de um período específico

### Troubleshooting

**Erro: "Arquivo físico não encontrado"**

- Verificar se o PDF ainda existe no caminho especificado
- Verificar permissões da pasta `/uploads/pareceres/`

**Erro: "Documento foi modificado após assinatura"**

- PDF foi alterado após ser assinado
- Hash atual diferente do hash armazenado
- Documento inválido e não confiável

**Erro: "Assinatura digital inválida"**

- Assinatura criptográfica não corresponde ao hash
- Possível corrupção de dados ou tentativa de fraude

## Ampliações Futuras

### ICP-Brasil (Certificado Digital)

Para evoluir para assinatura qualificada:

1. Contratar certificado digital ICP-Brasil
2. Integrar biblioteca de validação de certificados
3. Substituir chaves RSA locais por certificado
4. Adicionar cadeia de certificação completa

### Carimbos de Tempo

1. Integrar com TSA (Time Stamping Authority)
2. Adicionar timestamp confiável de terceiros
3. Garantir momento exato da assinatura

### Múltiplas Assinaturas

1. Permitir mais de um signatário por documento
2. Workflow de aprovações
3. Ordem de assinaturas
