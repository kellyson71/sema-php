CREATE TABLE IF NOT EXISTS admin_release_reads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    version VARCHAR(50) NOT NULL,
    lida_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_admin_release_read (admin_id, version),
    KEY idx_admin_release_reads_admin (admin_id, version),
    CONSTRAINT fk_admin_release_reads_admin
        FOREIGN KEY (admin_id) REFERENCES administradores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
