-- Script para melhorar o sistema de logs de email
-- Adiciona coluna para identificar emails de teste e evitar falsos positivos

USE sema_db;

-- Adicionar coluna para identificar se é um email de teste
ALTER TABLE email_logs ADD COLUMN eh_teste BOOLEAN DEFAULT FALSE AFTER status;

-- Adicionar coluna para detalhes adicionais do envio
ALTER TABLE email_logs ADD COLUMN detalhes_envio TEXT AFTER erro;

-- Atualizar emails existentes que são claramente de teste
UPDATE email_logs 
SET eh_teste = TRUE 
WHERE email_destino LIKE '%@example.com' 
   OR email_destino LIKE '%teste%' 
   OR email_destino LIKE '%test%'
   OR assunto LIKE '[TESTE]%'
   OR assunto LIKE '%teste%'
   OR assunto LIKE '%test%';

-- Criar índices para melhorar performance nas consultas
CREATE INDEX idx_email_logs_eh_teste ON email_logs(eh_teste);
CREATE INDEX idx_email_logs_data_status ON email_logs(data_envio, status);
CREATE INDEX idx_email_logs_email_destino ON email_logs(email_destino);

-- Criar view para emails reais (não de teste)
CREATE VIEW email_logs_reais AS 
SELECT * FROM email_logs 
WHERE eh_teste = FALSE;

-- Criar view para estatísticas de email
CREATE VIEW estatisticas_email AS
SELECT 
    DATE(data_envio) as data,
    status,
    eh_teste,
    COUNT(*) as total,
    COUNT(DISTINCT email_destino) as emails_unicos
FROM email_logs 
WHERE data_envio >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(data_envio), status, eh_teste
ORDER BY data DESC, status;
