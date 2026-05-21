-- Permite múltiplos documentos finais por requerimento
-- Remove UNIQUE no requerimento_id, mantém UNIQUE no token_acesso
-- Usa procedimento para dropar FK e índice somente se existirem (idempotente).

DROP PROCEDURE IF EXISTS _fix_doc_final_multi;

DELIMITER $$
CREATE PROCEDURE _fix_doc_final_multi()
BEGIN
    -- Dropar FK se existir
    IF EXISTS (
        SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME = 'documentos_finais'
          AND CONSTRAINT_NAME = 'fk_doc_final_requerimento'
          AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    ) THEN
        ALTER TABLE documentos_finais DROP FOREIGN KEY fk_doc_final_requerimento;
    END IF;

    -- Dropar índice UNIQUE se existir
    IF EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'documentos_finais'
          AND INDEX_NAME = 'requerimento_id'
          AND NON_UNIQUE = 0
    ) THEN
        ALTER TABLE documentos_finais DROP INDEX requerimento_id;
    END IF;

    -- Adicionar índice simples se ainda não existir
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'documentos_finais'
          AND INDEX_NAME = 'idx_requerimento'
    ) THEN
        ALTER TABLE documentos_finais ADD INDEX idx_requerimento (requerimento_id);
    END IF;

    -- Recriar FK se não existir
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME = 'documentos_finais'
          AND CONSTRAINT_NAME = 'fk_doc_final_requerimento'
    ) THEN
        ALTER TABLE documentos_finais ADD CONSTRAINT fk_doc_final_requerimento
            FOREIGN KEY (requerimento_id) REFERENCES requerimentos (id) ON DELETE CASCADE;
    END IF;
END$$
DELIMITER ;

CALL _fix_doc_final_multi();
DROP PROCEDURE IF EXISTS _fix_doc_final_multi;
