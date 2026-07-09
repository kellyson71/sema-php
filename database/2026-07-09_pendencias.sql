-- Reabertura de formulário: solicitação de complementação ao cidadão
-- 2026-07-09

CREATE TABLE IF NOT EXISTS `requerimento_pendencias` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `requerimento_id` INT(11) NOT NULL,
  `titulo` VARCHAR(255) NOT NULL,
  `descricao` TEXT NOT NULL,
  `resposta` TEXT DEFAULT NULL,
  `status` ENUM('aberta','respondida','cancelada') NOT NULL DEFAULT 'aberta',
  `admin_id` INT(11) DEFAULT NULL,
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `respondido_em` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_requerimento` (`requerimento_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_pendencia_requerimento` FOREIGN KEY (`requerimento_id`)
    REFERENCES `requerimentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Novo status.
--
-- ATENÇÃO: esta lista foi extraída do enum REAL do banco (15 valores), não do
-- schema.sql, que está desatualizado. Um MODIFY com a lista do schema.sql
-- silenciosamente zera as linhas cujo status não estiver na nova lista
-- (havia 3 delas em homologação: 'Aguardando Fiscalização' e
-- 'Aguardando Secretaria'). Antes de reaplicar isto em outro ambiente,
-- reconfira com:
--   SELECT COLUMN_TYPE FROM information_schema.COLUMNS
--    WHERE TABLE_NAME='requerimentos' AND COLUMN_NAME='status';
ALTER TABLE `requerimentos` MODIFY `status` ENUM(
  'Pendente',
  'Em análise',
  'Aguardando Fiscalização',
  'Aprovado',
  'Reprovado',
  'Cancelado',
  'Indeferido',
  'Finalizado',
  'Apto a gerar alvará',
  'Alvará Emitido',
  'Aguardando boleto',
  'Boleto pago',
  'Aguardando Secretaria',
  'Devolvido pela Secretaria',
  'Documento Final Enviado',
  'Aguardando complementação'
) NOT NULL DEFAULT 'Pendente';
