# Melhorias de Segurança - Campos Adicionais nos Administradores

**Data:** 04/11/2025
**Versão:** 2.10
**Tipo:** Melhoria de Segurança

## Descrição

Adicionados novos campos na tabela de administradores e nos metadados JSON dos documentos assinados digitalmente para aumentar a segurança e rastreabilidade.

## Mudanças Implementadas

### 1. Novos Campos na Tabela `administradores`

- **`nome_completo`** (VARCHAR(255)): Nome completo do administrador
- **`matricula_portaria`** (VARCHAR(100)): Matrícula ou portaria de nomeação

### 2. Atualização de Dados dos Usuários

Atualizados os seguintes usuários com informações completas:

- **Isabely Keyva**

  - Email: eng.isabelykeyva@gmail.com
  - CPF: 09382706488
  - Cargo: Assessora técnica
  - Matrícula/Portaria: Portaria 179/2025

- **Sabrina Deise Pereira do Vale**

  - CPF: 07568254402
  - Cargo: Fiscal de Meio Ambiente
  - Matrícula/Portaria: Matrícula 2505

- **Samara do Nascimento Linhares**

  - Email: samlinhares12@gmail.com
  - CPF: 08149272461
  - Cargo: Fiscal de Meio Ambiente
  - Matrícula/Portaria: Matrícula 2518

- **Julia Paiva**
  - Email: juliampaiva@gmail.com
  - CPF: 04996480483
  - Cargo: Fiscal de Meio Ambiente
  - Matrícula/Portaria: Matrícula 1300

### 3. Metadados JSON Ampliados

Os metadados JSON dos documentos assinados agora incluem:

- `nome_completo`: Nome completo do assinante
- `email`: Email do assinante
- `matricula_portaria`: Matrícula ou portaria do assinante
- `cpf`: CPF do assinante (já existia)
- `role`: Cargo do assinante (já existia)

### 4. Bloco de Assinatura no PDF

O bloco visual de assinatura no PDF agora exibe:

- Nome completo
- CPF
- Cargo
- Matrícula/Portaria (quando disponível)
- Data e hora da assinatura

## Arquivos Modificados

1. `database/atualizar_administradores_seguranca.sql` - Script SQL para adicionar campos e atualizar usuários
2. `admin/login.php` - Carregamento dos novos campos na sessão
3. `admin/parecer_handler.php` - Busca dos dados completos e inclusão nos metadados
4. `includes/assinatura_digital_service.php` - Geração de JSON com novos campos

## Como Aplicar

Execute o script SQL no banco de dados:

```sql
source database/atualizar_administradores_seguranca.sql;
```

Ou execute manualmente cada comando do arquivo SQL.

## Impacto

### Antes

- Apenas nome curto e cargo básico nos metadados
- Sem identificação completa do assinante
- Menor rastreabilidade

### Depois

- Identificação completa do assinante (nome completo, CPF, email, matrícula/portaria)
- Maior segurança e rastreabilidade
- Metadados mais completos para auditoria
- Bloco de assinatura mais informativo no PDF

## Segurança

- Todos os campos são carregados do banco de dados no momento da assinatura
- Dados não podem ser alterados após assinatura (hash do documento)
- Informações completas garantem rastreabilidade total
- Metadados JSON incluem todas as informações para verificação posterior

## Compatibilidade

- Sistema retrocompatível: documentos antigos continuam válidos
- Novos campos são opcionais (NULL permitido)
- Fallback para campos antigos quando novos não estão disponíveis
