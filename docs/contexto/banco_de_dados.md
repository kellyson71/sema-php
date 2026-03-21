# Dicionário de Banco de Dados

Baseado no arquivo `docs/estrutura.sql`. O banco de dados utiliza o motor **InnoDB** com charset `utf8mb4_unicode_ci`.

## Principais Tabelas

### 1. `requerimentos` (Núcleo)

Armazena todos os pedidos enviados pelos cidadãos.

- `id`: Chave primária.
- `protocolo`: Número único gerado para identificação.
- `status`: Enum (Pendente, Em análise, Aprovado, Reprovado, Finalizado, etc.).
- `requerente_id` / `proprietario_id`: Chaves estrangeiras.

### 2. `requerentes` e `proprietarios`

Armazenam dados cadastrais (Nome, CPF/CNPJ, E-mail, Telefone).

### 3. `historico_acoes`

Log de auditoria de cada processo.

- Salva qual administrador fez qual ação e em que data.
- Usado para calcular o tempo médio de tramitação nas estatísticas.

### 4. `documentos`

Metadados dos arquivos enviados via upload.

- `caminho`: Localização física do arquivo na pasta de uploads.
- `tipo_arquivo`: Extensão (PDF, JPG, PNG).

### 5. `administradores`

Contas de acesso ao painel.

- `nivel`: enum('admin', 'operador', 'secretario').
- `senha`: Hash BCrypt.

### 6. `assinaturas_digitais`

Registros de validade jurídica para pareceres emitidos.

- Contém hash do documento e dados do assinante.

### 7. `denuncias`

Módulo interno para gestão de queixas ambientais externas.

## Relacionamentos Críticos

- `requerimentos` -> `requerentes` (N:1)
- `requerimentos` -> `documentos` (1:N)
- `requerimentos` -> `historico_acoes` (1:N)
