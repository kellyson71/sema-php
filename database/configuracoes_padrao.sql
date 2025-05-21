-- Arquivo para inserir configurações padrão no sistema

-- Usar o banco de dados
USE sema_db;

-- Verificar se a tabela configuracoes existe e criá-la se necessário
CREATE TABLE IF NOT EXISTS configuracoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(50) NOT NULL UNIQUE,
    nome VARCHAR(100) NOT NULL,
    valor TEXT,
    tipo ENUM('texto', 'numero', 'booleano', 'lista', 'textarea') DEFAULT 'texto',
    categoria VARCHAR(50) NOT NULL DEFAULT 'Geral',
    descricao TEXT,
    opcoes TEXT,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Inserir configurações padrão apenas se não existirem
-- Geral
INSERT INTO configuracoes (chave, nome, valor, tipo, categoria, descricao) 
VALUES 
('sistema_nome', 'Nome do Sistema', 'Sistema de Gerenciamento de Alvarás Ambientais', 'texto', 'Geral', 'Nome completo do sistema usado em relatórios e emails')
ON DUPLICATE KEY UPDATE chave = chave;

INSERT INTO configuracoes (chave, nome, valor, tipo, categoria, descricao) 
VALUES 
('sistema_email', 'Email de Contato', 'contato@sema.gov.br', 'texto', 'Geral', 'Email oficial para contato')
ON DUPLICATE KEY UPDATE chave = chave;

INSERT INTO configuracoes (chave, nome, valor, tipo, categoria, descricao) 
VALUES 
('sistema_versao', 'Versão do Sistema', '1.0', 'texto', 'Geral', 'Versão atual do sistema')
ON DUPLICATE KEY UPDATE chave = chave;

-- Email
INSERT INTO configuracoes (chave, nome, valor, tipo, categoria, descricao) 
VALUES 
('email_ativar_notificacoes', 'Ativar Notificações por Email', '1', 'booleano', 'Email', 'Ativa ou desativa o envio de emails pelo sistema')
ON DUPLICATE KEY UPDATE chave = chave;

INSERT INTO configuracoes (chave, nome, valor, tipo, categoria, descricao) 
VALUES 
('email_remetente', 'Email Remetente', 'naoresponda@sema.gov.br', 'texto', 'Email', 'Email usado como remetente nas notificações')
ON DUPLICATE KEY UPDATE chave = chave;

INSERT INTO configuracoes (chave, nome, valor, tipo, categoria, descricao, opcoes) 
VALUES 
('email_formato', 'Formato dos Emails', 'html', 'lista', 'Email', 'Formato padrão para envio de emails', 'html,texto')
ON DUPLICATE KEY UPDATE chave = chave;

-- Requerimentos
INSERT INTO configuracoes (chave, nome, valor, tipo, categoria, descricao) 
VALUES 
('requerimento_prazo_analise', 'Prazo para Análise (dias)', '30', 'numero', 'Requerimentos', 'Prazo em dias para análise de requerimentos')
ON DUPLICATE KEY UPDATE chave = chave;

INSERT INTO configuracoes (chave, nome, valor, tipo, categoria, descricao) 
VALUES 
('requerimento_quantidade_por_pagina', 'Itens por Página', '10', 'numero', 'Requerimentos', 'Quantidade de requerimentos exibidos por página')
ON DUPLICATE KEY UPDATE chave = chave;

INSERT INTO configuracoes (chave, nome, valor, tipo, categoria, descricao) 
VALUES 
('requerimento_mensagem_confirmacao', 'Mensagem de Confirmação', 'Seu requerimento foi enviado com sucesso e está em análise pela nossa equipe.', 'textarea', 'Requerimentos', 'Mensagem exibida após envio do requerimento')
ON DUPLICATE KEY UPDATE chave = chave;

-- Upload
INSERT INTO configuracoes (chave, nome, valor, tipo, categoria, descricao) 
VALUES 
('upload_tamanho_maximo', 'Tamanho Máximo (MB)', '10', 'numero', 'Upload', 'Tamanho máximo permitido para upload de arquivos em MB')
ON DUPLICATE KEY UPDATE chave = chave;

INSERT INTO configuracoes (chave, nome, valor, tipo, categoria, descricao) 
VALUES 
('upload_tipos_permitidos', 'Tipos de Arquivos Permitidos', 'pdf,jpg,jpeg,png,doc,docx', 'texto', 'Upload', 'Extensões de arquivos permitidos para upload (separados por vírgula)')
ON DUPLICATE KEY UPDATE chave = chave;

INSERT INTO configuracoes (chave, nome, valor, tipo, categoria, descricao) 
VALUES 
('upload_manter_nome_original', 'Manter Nome Original', '0', 'booleano', 'Upload', 'Se ativado, mantém o nome original dos arquivos enviados')
ON DUPLICATE KEY UPDATE chave = chave;

-- Dashboard
INSERT INTO configuracoes (chave, nome, valor, tipo, categoria, descricao) 
VALUES 
('dashboard_mostrar_estatisticas', 'Mostrar Estatísticas', '1', 'booleano', 'Dashboard', 'Exibe gráficos estatísticos no dashboard')
ON DUPLICATE KEY UPDATE chave = chave;

INSERT INTO configuracoes (chave, nome, valor, tipo, categoria, descricao) 
VALUES 
('dashboard_requerimentos_recentes', 'Quantidade de Requerimentos Recentes', '5', 'numero', 'Dashboard', 'Número de requerimentos recentes exibidos no dashboard')
ON DUPLICATE KEY UPDATE chave = chave;
