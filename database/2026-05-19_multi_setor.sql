-- Migration: multi-setor (2026-05-19)
-- Habilita multi-assinatura, cria tabelas solicitacoes_assinatura e documentos_finais

-- 1. Multi-assinatura: remove UNIQUE por documento, adiciona UNIQUE por (documento, assinante)
ALTER TABLE `assinaturas_digitais`
  DROP INDEX IF EXISTS `documento_id`;

ALTER TABLE `assinaturas_digitais`
  ADD UNIQUE KEY IF NOT EXISTS `uq_doc_assinante` (`documento_id`, `assinante_id`);

-- 2. Tabela de solicitações de assinatura
CREATE TABLE IF NOT EXISTS `solicitacoes_assinatura` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `documento_id`    VARCHAR(64)  NOT NULL,
  `requerimento_id` INT          NOT NULL,
  `solicitante_id`  INT          NOT NULL,
  `destinatario_id` INT          NOT NULL,
  `mensagem`        TEXT         NULL,
  `status`          ENUM('pendente','assinado','recusado') NOT NULL DEFAULT 'pendente',
  `motivo_recusa`   TEXT         NULL,
  `criado_em`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolvido_em`    TIMESTAMP    NULL,
  INDEX `idx_destinatario` (`destinatario_id`),
  INDEX `idx_documento`    (`documento_id`),
  INDEX `idx_requerimento` (`requerimento_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabela de documentos finais enviados ao cidadão
CREATE TABLE IF NOT EXISTS `documentos_finais` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `requerimento_id`  INT           NOT NULL UNIQUE,
  `documento_id`     VARCHAR(64)   NULL,
  `caminho_arquivo`  VARCHAR(500)  NOT NULL,
  `nome_arquivo`     VARCHAR(255)  NOT NULL,
  `instrucoes`       TEXT          NULL,
  `token_acesso`     VARCHAR(128)  NOT NULL UNIQUE,
  `admin_envio_id`   INT           NOT NULL,
  `enviado_em`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `visualizado_em`   TIMESTAMP     NULL,
  `data_atualizacao` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_token` (`token_acesso`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
