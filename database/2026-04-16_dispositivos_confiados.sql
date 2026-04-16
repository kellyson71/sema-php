-- Migration: Tabela de dispositivos confiáveis para "lembrar dispositivo" no 2FA
-- Data: 2026-04-16

CREATE TABLE IF NOT EXISTS dispositivos_confiados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    token_hash VARCHAR(64) NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    expira_em DATETIME NOT NULL,
    INDEX idx_token (token_hash),
    INDEX idx_admin (admin_id),
    INDEX idx_expira (expira_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
