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

-- Novo status. Mantém todos os valores já existentes em produção.
ALTER TABLE `requerimentos` MODIFY `status` ENUM(
  'Pendente',
  'Em análise',
  'Aprovado',
  'Reprovado',
  'Finalizado',
  'Cancelado',
  'Indeferido',
  'Aguardando boleto',
  'Boleto pago',
  'Aguardando complementação'
) NOT NULL DEFAULT 'Pendente';
