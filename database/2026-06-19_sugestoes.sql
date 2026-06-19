-- Tabela de sugestões de melhoria enviadas pelos cidadãos
CREATE TABLE IF NOT EXISTS sugestoes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    tipo        ENUM('melhoria','dificuldade','elogio','outro') NOT NULL DEFAULT 'melhoria',
    texto       TEXT NOT NULL,
    nome        VARCHAR(120) NULL,
    email       VARCHAR(120) NULL,
    pagina      VARCHAR(255) NULL,
    ip_origem   VARCHAR(45)  NULL,
    criado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status      ENUM('nova','lida','em_analise','implementada','descartada') NOT NULL DEFAULT 'nova',
    nota_admin  TEXT NULL,
    INDEX idx_sugestoes_status  (status),
    INDEX idx_sugestoes_tipo    (tipo),
    INDEX idx_sugestoes_criado  (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
