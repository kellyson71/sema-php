-- Migration: novos formulários públicos (2026-06-18)
-- Adapta `denuncias` para receber submissões públicas de cidadãos,
-- cria `denuncia_historico` (referenciada pelo código mas ausente no banco).

-- 1. Adaptar `denuncias` para submissões públicas
ALTER TABLE `denuncias`
    MODIFY COLUMN `admin_id` INT NULL,
    ADD COLUMN IF NOT EXISTS `origem` ENUM('admin','publico') NOT NULL DEFAULT 'admin' AFTER `admin_id`,
    ADD COLUMN IF NOT EXISTS `denunciante_nome` VARCHAR(255) NULL AFTER `observacoes`,
    ADD COLUMN IF NOT EXISTS `denunciante_endereco` TEXT NULL AFTER `denunciante_nome`,
    ADD COLUMN IF NOT EXISTS `anonimo` TINYINT(1) NOT NULL DEFAULT 0 AFTER `denunciante_endereco`,
    ADD COLUMN IF NOT EXISTS `proprietario_nome` VARCHAR(255) NULL AFTER `anonimo`,
    ADD COLUMN IF NOT EXISTS `proprietario_endereco` TEXT NULL AFTER `proprietario_nome`,
    ADD COLUMN IF NOT EXISTS `proprietario_contato` VARCHAR(100) NULL AFTER `proprietario_endereco`,
    ADD COLUMN IF NOT EXISTS `tipo_denuncia` JSON NULL AFTER `proprietario_contato`,
    ADD COLUMN IF NOT EXISTS `protocolo_publico` VARCHAR(50) NULL AFTER `tipo_denuncia`;

-- 2. Criar denuncia_historico (referenciada pelo código mas ausente no banco)
CREATE TABLE IF NOT EXISTS `denuncia_historico` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `denuncia_id`  INT NOT NULL,
    `admin_id`     INT NULL,
    `acao`         VARCHAR(100) NOT NULL,
    `detalhes`     TEXT NULL,
    `data_registro` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`denuncia_id`) REFERENCES `denuncias`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
