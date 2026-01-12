-- Atualização de Segurança - Adicionar Campos e Informações dos Administradores
-- Data: 04/11/2025
-- Descrição: Adiciona campos nome_completo e matricula_portaria, e atualiza informações dos usuários

-- Verificar e adicionar coluna nome_completo se não existir
SET @dbname = DATABASE();
SET @tablename = 'administradores';
SET @columnname = 'nome_completo';
SET @preparedStatement = (SELECT IF(
    (
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE
            (TABLE_SCHEMA = @dbname)
            AND (TABLE_NAME = @tablename)
            AND (COLUMN_NAME = @columnname)
    ) > 0,
    "SELECT 1",
    CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " VARCHAR(255) NULL AFTER nome")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Verificar e adicionar coluna matricula_portaria se não existir
SET @columnname = 'matricula_portaria';
SET @preparedStatement = (SELECT IF(
    (
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE
            (TABLE_SCHEMA = @dbname)
            AND (TABLE_NAME = @tablename)
            AND (COLUMN_NAME = @columnname)
    ) > 0,
    "SELECT 1",
    CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " VARCHAR(100) NULL AFTER cargo")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Copiar nome atual para nome_completo se nome_completo estiver vazio
UPDATE `administradores`
SET `nome_completo` = `nome`
WHERE `nome_completo` IS NULL OR `nome_completo` = '';

-- Atualizar Isabely
UPDATE `administradores`
SET
    `nome_completo` = 'Isabely Keyva',
    `email` = 'eng.isabelykeyva@gmail.com',
    `cpf` = '09382706488',
    `cargo` = 'Assessora técnica',
    `matricula_portaria` = 'Portaria 179/2025'
WHERE `email` = 'isabely@sema.gov.br' OR `nome` = 'Isabely';

-- Atualizar Sabrina
UPDATE `administradores`
SET
    `nome_completo` = 'Sabrina Deise Pereira do Vale',
    `cpf` = '07568254402',
    `cargo` = 'Fiscal de Meio Ambiente',
    `matricula_portaria` = 'Matrícula 2505'
WHERE `email` = 'sabrina@sema.gov.br' OR `nome` = 'Sabrina';

-- Atualizar Samara
UPDATE `administradores`
SET
    `nome_completo` = 'Samara do Nascimento Linhares',
    `email` = 'samlinhares12@gmail.com',
    `cpf` = '08149272461',
    `cargo` = 'Fiscal de Meio Ambiente',
    `matricula_portaria` = 'Matrícula 2518'
WHERE `email` = 'samara@sema.gov.br' OR `nome` = 'Samara';

-- Atualizar Julia
UPDATE `administradores`
SET
    `nome_completo` = 'Julia Paiva',
    `email` = 'juliampaiva@gmail.com',
    `cpf` = '04996480483',
    `cargo` = 'Fiscal de Meio Ambiente',
    `matricula_portaria` = 'Matrícula 1300'
WHERE `email` = 'julia@sema.gov.br' OR `nome` = 'Julia';


