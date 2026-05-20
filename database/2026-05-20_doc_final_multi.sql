-- Permite múltiplos documentos finais por requerimento
-- Remove UNIQUE no requerimento_id, mantém UNIQUE no token_acesso
-- Precisa dropar FK antes de dropar o índice único, depois recriar FK com índice simples
ALTER TABLE documentos_finais DROP FOREIGN KEY fk_doc_final_requerimento;
ALTER TABLE documentos_finais DROP INDEX requerimento_id;
ALTER TABLE documentos_finais ADD INDEX idx_requerimento (requerimento_id);
ALTER TABLE documentos_finais ADD CONSTRAINT fk_doc_final_requerimento
    FOREIGN KEY (requerimento_id) REFERENCES requerimentos (id) ON DELETE CASCADE;
