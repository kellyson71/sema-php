-- Criar banco de dados
CREATE DATABASE IF NOT EXISTS sema_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Usar o banco de dados
USE sema_db;

-- Tabela de requerentes
CREATE TABLE IF NOT EXISTS requerentes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(191) NOT NULL,
    cpf_cnpj VARCHAR(20) NOT NULL,
    telefone VARCHAR(20) NOT NULL,
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de proprietários
CREATE TABLE IF NOT EXISTS proprietarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    cpf_cnpj VARCHAR(20) NOT NULL,
    mesmo_requerente BOOLEAN DEFAULT FALSE,
    requerente_id INT,
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (requerente_id) REFERENCES requerentes(id) ON DELETE CASCADE
);

-- Tabela de requerimentos
CREATE TABLE IF NOT EXISTS requerimentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    protocolo VARCHAR(20) NOT NULL UNIQUE,
    tipo_alvara VARCHAR(50) NOT NULL,
    requerente_id INT NOT NULL,
    proprietario_id INT,
    endereco_objetivo TEXT NOT NULL,
    ctf_numero VARCHAR(50) NULL COMMENT 'Cadastro Técnico Federal',
    licenca_anterior_numero VARCHAR(50) NULL COMMENT 'Número da licença anterior',
    publicacao_diario_oficial VARCHAR(255) NULL COMMENT 'Dados da publicação em Diário Oficial',
    comprovante_pagamento VARCHAR(255) NULL COMMENT 'Recibo/código do pagamento',
    possui_estudo_ambiental BOOLEAN NULL COMMENT 'Indica se possui estudo ambiental',
    tipo_estudo_ambiental VARCHAR(100) NULL COMMENT 'Tipo de estudo ambiental informado',
    status ENUM('Em análise', 'Aprovado', 'Reprovado', 'Pendente', 'Aguardando Fiscalização', 'Apto a gerar alvará', 'Alvará Emitido', 'Finalizado', 'Indeferido', 'Cancelado', 'Aguardando boleto', 'Boleto pago') DEFAULT 'Em análise',
    observacoes TEXT,
    data_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (requerente_id) REFERENCES requerentes(id) ON DELETE CASCADE,
    FOREIGN KEY (proprietario_id) REFERENCES proprietarios(id) ON DELETE SET NULL
);

-- Tabela de documentos
CREATE TABLE IF NOT EXISTS documentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requerimento_id INT NOT NULL,
    campo_formulario VARCHAR(50) NOT NULL,
    nome_original VARCHAR(191) NOT NULL,
    nome_salvo VARCHAR(191) NOT NULL,
    caminho VARCHAR(191) NOT NULL,
    tipo_arquivo VARCHAR(100),
    tamanho INT NOT NULL,
    data_upload TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (requerimento_id) REFERENCES requerimentos(id) ON DELETE CASCADE
);

-- Dados do fluxo de cobrança manual por boleto
-- Tabela de administradores
CREATE TABLE IF NOT EXISTS administradores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(191) NOT NULL UNIQUE,
    senha VARCHAR(191) NOT NULL,
    nivel ENUM('admin', 'operador') DEFAULT 'operador',
    ativo BOOLEAN DEFAULT TRUE,
    ultimo_acesso TIMESTAMP NULL,
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Dados do fluxo de cobrança manual por boleto
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
    FOREIGN KEY (requerimento_id) REFERENCES requerimentos(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_envio_id) REFERENCES administradores(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS requerimento_pagamento_historico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requerimento_id INT NOT NULL,
    documento_id INT NULL,
    instrucoes TEXT NULL,
    enviado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    admin_envio_id INT NULL,
    FOREIGN KEY (requerimento_id) REFERENCES requerimentos(id) ON DELETE CASCADE,
    FOREIGN KEY (documento_id) REFERENCES documentos(id) ON DELETE SET NULL,
    FOREIGN KEY (admin_envio_id) REFERENCES administradores(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS admin_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(50) NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT NOT NULL,
    link_url VARCHAR(255) NULL,
    requerimento_id INT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (requerimento_id) REFERENCES requerimentos(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS admin_notification_reads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    notification_id INT NOT NULL,
    admin_id INT NOT NULL,
    lida_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_notification_admin (notification_id, admin_id),
    FOREIGN KEY (notification_id) REFERENCES admin_notifications(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES administradores(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS admin_release_reads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    version VARCHAR(50) NOT NULL,
    lida_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_admin_release_read (admin_id, version),
    FOREIGN KEY (admin_id) REFERENCES administradores(id) ON DELETE CASCADE
);

-- Tabela de histórico de ações
CREATE TABLE IF NOT EXISTS historico_acoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT,
    requerimento_id INT,
    acao TEXT NOT NULL,
    data_acao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES administradores(id) ON DELETE SET NULL,
    FOREIGN KEY (requerimento_id) REFERENCES requerimentos(id) ON DELETE CASCADE
);

-- Tabela de configurações do sistema
CREATE TABLE IF NOT EXISTS configuracoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(50) NOT NULL UNIQUE,
    nome VARCHAR(100) NOT NULL,
    valor TEXT,
    tipo ENUM('texto', 'numero', 'booleano', 'select', 'textarea') DEFAULT 'texto',
    categoria VARCHAR(50) NOT NULL DEFAULT 'Geral',
    descricao TEXT,
    opcoes TEXT,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de log de emails
CREATE TABLE IF NOT EXISTS email_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requerimento_id INT,
    email_destino VARCHAR(191) NOT NULL,
    assunto VARCHAR(255) NOT NULL,
    mensagem TEXT,
    usuario_envio VARCHAR(100),
    status ENUM('SUCESSO', 'ERRO') NOT NULL,
    erro TEXT,
    data_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (requerimento_id) REFERENCES requerimentos(id) ON DELETE CASCADE
);
