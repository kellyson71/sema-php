-- Script SQL para criar tabela de requerimentos arquivados
-- Execute este script no seu banco de dados MySQL

-- Criar tabela de requerimentos arquivados
CREATE TABLE IF NOT EXISTS requerimentos_arquivados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requerimento_id INT NOT NULL,
    protocolo VARCHAR(20) NOT NULL,
    tipo_alvara VARCHAR(50) NOT NULL,
    requerente_id INT NOT NULL,
    proprietario_id INT,
    endereco_objetivo TEXT NOT NULL,
    status VARCHAR(50) NOT NULL,
    observacoes TEXT,
    data_envio TIMESTAMP NOT NULL,
    data_atualizacao TIMESTAMP NOT NULL,
    data_arquivamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    admin_arquivamento INT,
    motivo_arquivamento TEXT,
    requerente_nome VARCHAR(255),
    requerente_email VARCHAR(191),
    requerente_cpf_cnpj VARCHAR(20),
    requerente_telefone VARCHAR(20),
    proprietario_nome VARCHAR(255),
    proprietario_cpf_cnpj VARCHAR(20),
    INDEX idx_protocolo (protocolo),
    INDEX idx_requerimento_id (requerimento_id),
    INDEX idx_data_arquivamento (data_arquivamento)
);

-- Comentários:
-- - Esta tabela armazena requerimentos "excluídos" (arquivados)
-- - Mantém todos os dados originais para auditoria
-- - Inclui informações sobre quem e quando arquivou
-- - Permite restauração se necessário
-- - Não deleta dados permanentemente
-- - Foreign key removida para evitar problemas de compatibilidade

-- Data de criação: 16/01/2025
-- Versão: 1.0
-- Descrição: Tabela para arquivamento de requerimentos 