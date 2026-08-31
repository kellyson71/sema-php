-- Preferências de filtros por administrador e página.
-- A chave de página mantém o recurso extensível sem misturar configurações.
CREATE TABLE IF NOT EXISTS admin_preferencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    pagina_chave VARCHAR(80) NOT NULL,
    filtros_json JSON NOT NULL,
    data_criacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_admin_preferencia_pagina (admin_id, pagina_chave),
    CONSTRAINT fk_admin_preferencias_admin
        FOREIGN KEY (admin_id) REFERENCES administradores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
