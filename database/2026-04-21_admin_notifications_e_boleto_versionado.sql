CREATE TABLE IF NOT EXISTS admin_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(50) NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT NOT NULL,
    link_url VARCHAR(255) NULL,
    requerimento_id INT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_notifications_criado_em (criado_em),
    INDEX idx_admin_notifications_requerimento (requerimento_id),
    CONSTRAINT fk_admin_notifications_requerimento
        FOREIGN KEY (requerimento_id) REFERENCES requerimentos(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS admin_notification_reads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    notification_id INT NOT NULL,
    admin_id INT NOT NULL,
    lida_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_notification_admin (notification_id, admin_id),
    KEY idx_admin_notification_reads_admin (admin_id, notification_id),
    CONSTRAINT fk_admin_notification_reads_notification
        FOREIGN KEY (notification_id) REFERENCES admin_notifications(id) ON DELETE CASCADE,
    CONSTRAINT fk_admin_notification_reads_admin
        FOREIGN KEY (admin_id) REFERENCES administradores(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS requerimento_pagamento_historico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requerimento_id INT NOT NULL,
    documento_id INT NULL,
    instrucoes TEXT NULL,
    enviado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    admin_envio_id INT NULL,
    KEY idx_pagamento_historico_requerimento (requerimento_id, enviado_em),
    CONSTRAINT fk_pagamento_historico_requerimento
        FOREIGN KEY (requerimento_id) REFERENCES requerimentos(id) ON DELETE CASCADE,
    CONSTRAINT fk_pagamento_historico_documento
        FOREIGN KEY (documento_id) REFERENCES documentos(id) ON DELETE SET NULL,
    CONSTRAINT fk_pagamento_historico_admin
        FOREIGN KEY (admin_envio_id) REFERENCES administradores(id) ON DELETE SET NULL
);
