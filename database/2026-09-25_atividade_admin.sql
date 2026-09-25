-- Migration: rastro de uso do painel admin (2026-09-25)
-- Alimenta a tela admin/atividade_usuarios.php e a reprodução de erros
-- (ver includes/atividade_admin.php e a seção "Atividade e reprodução de erros" do CLAUDE.md).
--
-- Nada aqui guarda valor digitado: só página, nome da ação, id do registro,
-- status HTTP e duração. Eventos com mais de 180 dias são apagados pelo próprio
-- ping de atividade (retenção LGPD).

CREATE TABLE IF NOT EXISTS `admin_eventos` (
  `id`                 BIGINT AUTO_INCREMENT PRIMARY KEY,
  `admin_id`           INT          NOT NULL,
  `ocorrido_em`        DATETIME(3)  NOT NULL,
  `tipo`               ENUM('pagina','acao','ajax','erro') NOT NULL,
  `metodo`             VARCHAR(8)   NOT NULL,
  `pagina`             VARCHAR(190) NOT NULL,
  `acao`               VARCHAR(120) NULL,
  `entidade`           VARCHAR(40)  NULL,
  `entidade_id`        INT          NULL,
  `status_http`        SMALLINT     NULL,
  `duracao_ms`         INT          NULL,
  `erro`               VARCHAR(255) NULL,
  `posthog_session_id` VARCHAR(64)  NULL,
  `nivel`              VARCHAR(30)  NULL,
  `simulando`          TINYINT(1)   NOT NULL DEFAULT 0,
  INDEX `idx_admin_data` (`admin_id`, `ocorrido_em`),
  INDEX `idx_data` (`ocorrido_em`),
  INDEX `idx_tipo_data` (`tipo`, `ocorrido_em`),
  INDEX `idx_entidade` (`entidade`, `entidade_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admin_atividade_diaria` (
  `admin_id`           INT      NOT NULL,
  `dia`                DATE     NOT NULL,
  `segundos_ativos`    INT      NOT NULL DEFAULT 0,
  `acoes`              INT      NOT NULL DEFAULT 0,
  `paginas`            INT      NOT NULL DEFAULT 0,
  `erros`              INT      NOT NULL DEFAULT 0,
  `primeira_atividade` DATETIME NULL,
  `ultima_atividade`   DATETIME NULL,
  PRIMARY KEY (`admin_id`, `dia`),
  INDEX `idx_dia` (`dia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
