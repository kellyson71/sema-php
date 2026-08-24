-- Aba "Pendências e cobrança" do redesenho de Visualizar Requerimento
-- 2026-08-20

-- Observações internas: uma nota por requerimento, só visível à equipe,
-- sobreposta a cada edição (mesmo padrão de requerimento_pagamentos).
CREATE TABLE IF NOT EXISTS `requerimento_notas_internas` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `requerimento_id` INT(11) NOT NULL UNIQUE,
  `texto` TEXT NOT NULL,
  `admin_id` INT(11) DEFAULT NULL,
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_nota_interna_requerimento` FOREIGN KEY (`requerimento_id`)
    REFERENCES `requerimentos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_nota_interna_admin` FOREIGN KEY (`admin_id`)
    REFERENCES `administradores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ações de aceitar/resolver manualmente uma complementação, hoje inexistentes
-- (só existiam "abrir" e "responder", pelo lado público). 'cancelada' já
-- existia no enum sem nenhum handler usá-la — mantido como está.
ALTER TABLE `requerimento_pendencias`
  MODIFY `status` ENUM('aberta','respondida','cancelada','aceita') NOT NULL DEFAULT 'aberta',
  ADD COLUMN `decidido_em` TIMESTAMP NULL DEFAULT NULL AFTER `respondido_em`,
  ADD COLUMN `resolvido_manualmente` TINYINT(1) NOT NULL DEFAULT 0 AFTER `decidido_em`,
  ADD COLUMN `reaberta_de_id` INT(11) DEFAULT NULL AFTER `resolvido_manualmente`,
  ADD CONSTRAINT `fk_pendencia_reaberta_de` FOREIGN KEY (`reaberta_de_id`)
    REFERENCES `requerimento_pendencias` (`id`) ON DELETE SET NULL;
