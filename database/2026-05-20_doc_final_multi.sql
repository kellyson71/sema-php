-- Permite múltiplos documentos finais por requerimento
-- Remove UNIQUE no requerimento_id, mantém UNIQUE no token_acesso
ALTER TABLE documentos_finais DROP INDEX IF EXISTS requerimento_id;
ALTER TABLE documentos_finais ADD INDEX IF NOT EXISTS idx_requerimento (requerimento_id);
