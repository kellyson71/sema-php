CREATE TABLE IF NOT EXISTS requerimento_edicoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requerimento_id INT NOT NULL,
    admin_id INT NULL,
    campo VARCHAR(80) NOT NULL,
    valor_original TEXT NULL,
    valor_novo TEXT NULL,
    editado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_requerimento_edicoes_requerimento (requerimento_id),
    CONSTRAINT fk_requerimento_edicoes_requerimento
        FOREIGN KEY (requerimento_id) REFERENCES requerimentos(id) ON DELETE CASCADE,
    CONSTRAINT fk_requerimento_edicoes_admin
        FOREIGN KEY (admin_id) REFERENCES administradores(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
