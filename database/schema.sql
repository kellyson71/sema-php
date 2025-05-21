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
    status ENUM('Em análise', 'Aprovado', 'Reprovado', 'Pendente') DEFAULT 'Em análise',
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