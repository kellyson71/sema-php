-- Correção: processos colocados incorretamente em setor2/setor3 pela migration inicial
-- Data: 2026-04-26

-- 1. Mover tudo de setor2/setor3 de volta para setor1
UPDATE requerimentos SET setor_atual = 'setor1' WHERE setor_atual IN ('setor2', 'setor3');

-- 2. Corrigir aguardando_acao para processos encerrados
UPDATE requerimentos 
SET aguardando_acao = 'concluido' 
WHERE status IN ('Finalizado', 'Indeferido', 'Cancelado', 'Reprovado', 'Aprovado');

-- 3. Migrar Reprovado → Indeferido
INSERT INTO historico_acoes (admin_id, requerimento_id, acao)
SELECT 1, id, 'Migração automática: status Reprovado convertido para Indeferido (normalização de dados)'
FROM requerimentos WHERE status = 'Reprovado';

UPDATE requerimentos SET status = 'Indeferido' WHERE status = 'Reprovado';
