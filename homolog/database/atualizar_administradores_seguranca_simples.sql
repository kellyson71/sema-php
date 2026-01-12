-- Versão Alternativa Simples - Adicionar Campos e Informações dos Administradores
-- Data: 04/11/2025
-- Use esta versão se a versão principal não funcionar

-- Adicionar coluna nome_completo
ALTER TABLE `administradores`
ADD COLUMN `nome_completo` VARCHAR(255) NULL AFTER `nome`;

-- Adicionar coluna matricula_portaria
ALTER TABLE `administradores`
ADD COLUMN `matricula_portaria` VARCHAR(100) NULL AFTER `cargo`;

-- Copiar nome atual para nome_completo
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

