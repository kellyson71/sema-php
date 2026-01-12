-- Script SQL para adicionar suporte ao status "Indeferido"
-- Execute este script no seu banco de dados MySQL

-- 1. Verificar se existe alguma constraint que precise ser atualizada
-- (Este comando é apenas para verificação - pode retornar erro se não houver constraints)
SHOW CREATE TABLE requerimentos;

-- 2. Atualizar comentários na coluna status para incluir o novo valor
ALTER TABLE requerimentos 
MODIFY COLUMN status ENUM(
    'Pendente',
    'Em análise', 
    'Aprovado',
    'Reprovado',
    'Finalizado',
    'Cancelado',
    'Indeferido'
) DEFAULT 'Pendente' 
COMMENT 'Status do requerimento: Pendente, Em análise, Aprovado, Reprovado, Finalizado, Cancelado, Indeferido';

-- 3. Se você tiver uma tabela de configurações ou logs, pode querer adicionar uma entrada
-- INSERT INTO configuracoes (chave, valor, descricao) VALUES 
-- ('status_indeferido_ativo', '1', 'Status Indeferido ativo no sistema');

-- 4. Verificar se a alteração foi aplicada corretamente
SELECT COLUMN_TYPE 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'requerimentos' 
AND COLUMN_NAME = 'status';

-- 5. Script para testar se existem registros com status diferentes dos esperados
SELECT status, COUNT(*) as quantidade 
FROM requerimentos 
GROUP BY status 
ORDER BY quantidade DESC;

-- Comentários:
-- - O status "Indeferido" agora está disponível para uso
-- - Requerimentos indeferidos terão o mesmo comportamento de bloqueio que os finalizados
-- - O motivo do indeferimento será armazenado no campo "observacoes"
-- - Um email automático será enviado ao requerente informando o indeferimento
-- - Processo indeferido pode ser reaberto pelo administrador se necessário

-- Data de criação: 09/07/2025
-- Versão: 1.0
-- Descrição: Adiciona suporte ao status "Indeferido" para requerimentos
