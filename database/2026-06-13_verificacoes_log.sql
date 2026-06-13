-- ============================================================
-- LOG DE VERIFICAÇÕES DE AUTENTICIDADE
-- Registra cada verificação feita no portal público (por código
-- ou por upload de PDF). NÃO armazena o arquivo enviado — apenas
-- o hash, o resultado e a origem, para auditoria e métricas.
-- ============================================================

CREATE TABLE IF NOT EXISTS `verificacoes_log` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `documento_id`  VARCHAR(64) NULL,                 -- preenchido quando o doc é reconhecido
  `hash_enviado`  VARCHAR(64) NULL,                 -- sha256 do arquivo enviado (upload)
  `metodo`        ENUM('codigo','upload') NOT NULL DEFAULT 'upload',
  `resultado`     ENUM('autentico','alterado','desconhecido') NOT NULL,
  `ip_origem`     VARCHAR(45) NULL,
  `criado_em`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_documento` (`documento_id`),
  INDEX `idx_criado`    (`criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
