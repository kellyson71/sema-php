-- Schema SQLite para desenvolvimento local
-- Criar tabela de requerentes
CREATE TABLE IF NOT EXISTS requerentes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(191) NOT NULL,
    cpf_cnpj VARCHAR(20) NOT NULL,
    telefone VARCHAR(20) NOT NULL,
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de proprietários
CREATE TABLE IF NOT EXISTS proprietarios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome VARCHAR(255) NOT NULL,
    cpf_cnpj VARCHAR(20) NOT NULL,
    mesmo_requerente BOOLEAN DEFAULT 0,
    requerente_id INTEGER,
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (requerente_id) REFERENCES requerentes(id) ON DELETE CASCADE
);

-- Tabela de requerimentos
CREATE TABLE IF NOT EXISTS requerimentos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    protocolo VARCHAR(20) NOT NULL UNIQUE,
    tipo_alvara VARCHAR(50) NOT NULL,
    requerente_id INTEGER NOT NULL,
    proprietario_id INTEGER,
    endereco_objetivo TEXT NOT NULL,
    status VARCHAR(20) DEFAULT 'Em análise',
    observacoes TEXT,
    data_envio DATETIME DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (requerente_id) REFERENCES requerentes(id) ON DELETE CASCADE,
    FOREIGN KEY (proprietario_id) REFERENCES proprietarios(id) ON DELETE SET NULL
);

-- Tabela de documentos
CREATE TABLE IF NOT EXISTS documentos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    requerimento_id INTEGER NOT NULL,
    campo_formulario VARCHAR(50) NOT NULL,
    nome_original VARCHAR(191) NOT NULL,
    nome_salvo VARCHAR(191) NOT NULL,
    caminho VARCHAR(191) NOT NULL,
    tipo_arquivo VARCHAR(100),
    tamanho INTEGER NOT NULL,
    data_upload DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (requerimento_id) REFERENCES requerimentos(id) ON DELETE CASCADE
);

-- Tabela de administradores
CREATE TABLE IF NOT EXISTS administradores (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(191) NOT NULL UNIQUE,
    senha VARCHAR(191) NOT NULL,
    nivel VARCHAR(20) DEFAULT 'operador',
    ativo BOOLEAN DEFAULT 1,
    ultimo_acesso DATETIME NULL,
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de histórico de ações
CREATE TABLE IF NOT EXISTS historico_acoes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    admin_id INTEGER,
    requerimento_id INTEGER,
    acao TEXT NOT NULL,
    data_acao DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES administradores(id) ON DELETE SET NULL,
    FOREIGN KEY (requerimento_id) REFERENCES requerimentos(id) ON DELETE CASCADE
);

-- Tabela de configurações do sistema
CREATE TABLE IF NOT EXISTS configuracoes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    chave VARCHAR(50) NOT NULL UNIQUE,
    nome VARCHAR(100) NOT NULL,
    valor TEXT,
    tipo VARCHAR(20) DEFAULT 'texto',
    categoria VARCHAR(50) NOT NULL DEFAULT 'Geral',
    descricao TEXT,
    opcoes TEXT,
    data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de log de emails
CREATE TABLE IF NOT EXISTS email_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    requerimento_id INTEGER,
    email_destino VARCHAR(191) NOT NULL,
    assunto VARCHAR(255) NOT NULL,
    mensagem TEXT,
    usuario_envio VARCHAR(100),
    status VARCHAR(10) NOT NULL,
    erro TEXT,
    data_envio DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (requerimento_id) REFERENCES requerimentos(id) ON DELETE CASCADE
);

-- Tabela de requerimentos arquivados
CREATE TABLE IF NOT EXISTS requerimentos_arquivados (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    protocolo VARCHAR(20) NOT NULL UNIQUE,
    tipo_alvara VARCHAR(50) NOT NULL,
    requerente_nome VARCHAR(255) NOT NULL,
    requerente_email VARCHAR(191) NOT NULL,
    requerente_cpf_cnpj VARCHAR(20) NOT NULL,
    requerente_telefone VARCHAR(20) NOT NULL,
    proprietario_nome VARCHAR(255),
    proprietario_cpf_cnpj VARCHAR(20),
    endereco_objetivo TEXT NOT NULL,
    status VARCHAR(20) DEFAULT 'Arquivado',
    observacoes TEXT,
    motivo_arquivamento TEXT,
    data_envio DATETIME,
    data_arquivamento DATETIME DEFAULT CURRENT_TIMESTAMP,
    admin_arquivamento VARCHAR(100)
);

-- Inserir administrador padrão
INSERT OR IGNORE INTO administradores (nome, email, senha, nivel) 
VALUES ('Administrador', 'admin@sema.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Inserir configurações padrão
INSERT OR IGNORE INTO configuracoes (chave, nome, valor, tipo, categoria, descricao) VALUES
('sistema_nome', 'Nome do Sistema', 'SEMA - Sistema de Requerimentos', 'texto', 'Geral', 'Nome do sistema'),
('sistema_versao', 'Versão', '1.0.0', 'texto', 'Geral', 'Versão atual do sistema'),
('email_ativo', 'Email Ativo', '1', 'booleano', 'Email', 'Se o sistema deve enviar emails'),
('protocolo_prefixo', 'Prefixo do Protocolo', 'SEMA', 'texto', 'Protocolo', 'Prefixo usado nos números de protocolo');
