-- Migration: Sistema Multi-Setor
-- Setor 1 = analista, Setor 2 = fiscal, Setor 3 = secretario

-- 1. Novos status para fluxo entre setores
ALTER TABLE requerimentos MODIFY COLUMN status ENUM(
    'Pendente',
    'Em análise',
    'Aguardando Fiscalização',
    'Aprovado',
    'Reprovado',
    'Cancelado',
    'Indeferido',
    'Finalizado',
    'Apto a gerar alvará',
    'Alvará Emitido',
    'Aguardando boleto',
    'Boleto pago',
    'Aguardando Secretaria',
    'Devolvido pela Secretaria',
    'Documento Final Enviado'
) DEFAULT 'Pendente';

-- 2. Multi-assinatura: trocar UNIQUE(documento_id) por UNIQUE(documento_id, assinante_id)
ALTER TABLE assinaturas_digitais DROP INDEX documento_id;
ALTER TABLE assinaturas_digitais ADD UNIQUE KEY uq_doc_assinante (documento_id, assinante_id);

-- 3. Solicitações de assinatura entre usuários
CREATE TABLE IF NOT EXISTS solicitacoes_assinatura (
    id INT AUTO_INCREMENT PRIMARY KEY,
    documento_id VARCHAR(64) NOT NULL,
    requerimento_id INT NOT NULL,
    solicitante_id INT NOT NULL,
    destinatario_id INT NOT NULL,
    status ENUM('pendente', 'assinado', 'recusado') DEFAULT 'pendente',
    motivo_recusa TEXT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolvido_em TIMESTAMP NULL,
    INDEX idx_destinatario (destinatario_id),
    INDEX idx_documento (documento_id),
    INDEX idx_requerimento (requerimento_id),
    CONSTRAINT fk_solicit_requerimento FOREIGN KEY (requerimento_id) REFERENCES requerimentos(id) ON DELETE CASCADE,
    CONSTRAINT fk_solicit_solicitante FOREIGN KEY (solicitante_id) REFERENCES administradores(id) ON DELETE CASCADE,
    CONSTRAINT fk_solicit_destinatario FOREIGN KEY (destinatario_id) REFERENCES administradores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Documentos finais enviados ao cidadão pelo Setor 2
CREATE TABLE IF NOT EXISTS documentos_finais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requerimento_id INT NOT NULL UNIQUE,
    documento_id VARCHAR(64) NULL,
    caminho_arquivo VARCHAR(500) NOT NULL,
    nome_arquivo VARCHAR(255) NOT NULL,
    instrucoes TEXT NULL,
    token_acesso VARCHAR(128) NOT NULL UNIQUE,
    admin_envio_id INT NOT NULL,
    enviado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    visualizado_em TIMESTAMP NULL,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_token (token_acesso),
    CONSTRAINT fk_doc_final_requerimento FOREIGN KEY (requerimento_id) REFERENCES requerimentos(id) ON DELETE CASCADE,
    CONSTRAINT fk_doc_final_admin FOREIGN KEY (admin_envio_id) REFERENCES administradores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
