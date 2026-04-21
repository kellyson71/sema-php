ALTER TABLE requerimentos
    MODIFY COLUMN status ENUM(
        'Em análise',
        'Aprovado',
        'Reprovado',
        'Pendente',
        'Aguardando Fiscalização',
        'Apto a gerar alvará',
        'Alvará Emitido',
        'Finalizado',
        'Indeferido',
        'Cancelado',
        'Aguardando boleto',
        'Boleto pago'
    ) DEFAULT 'Em análise';

CREATE TABLE IF NOT EXISTS requerimento_pagamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requerimento_id INT NOT NULL UNIQUE,
    boleto_url TEXT NULL,
    instrucoes TEXT NULL,
    enviado_em TIMESTAMP NULL DEFAULT NULL,
    comprovante_enviado_em TIMESTAMP NULL DEFAULT NULL,
    admin_envio_id INT NULL,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_pagamento_requerimento FOREIGN KEY (requerimento_id) REFERENCES requerimentos(id) ON DELETE CASCADE,
    CONSTRAINT fk_pagamento_admin FOREIGN KEY (admin_envio_id) REFERENCES administradores(id) ON DELETE SET NULL
);
