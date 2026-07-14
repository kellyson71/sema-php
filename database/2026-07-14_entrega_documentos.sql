-- Entrega de documentos ao cidadão — lote, auditoria e expiração do link
--
-- Contexto: `documentos_finais` guarda uma linha por documento, mas o token de acesso
-- era UNIQUE por linha. Só a primeira linha do envio recebia o token real, e a página
-- pública filtra por token — então o cidadão só enxergava o primeiro documento.
-- O reenvio ainda apagava (DELETE) o registro anterior, destruindo a auditoria.
--
-- Agora: todas as linhas de um envio compartilham `token_acesso` + `lote_id`; envios
-- antigos são revogados em vez de apagados; o link tem prazo de validade.
--
-- Idempotente: pode ser executado mais de uma vez com segurança.

DROP PROCEDURE IF EXISTS _migra_entrega_documentos;

DELIMITER $$
CREATE PROCEDURE _migra_entrega_documentos()
BEGIN
    -- O token deixa de ser único por linha (passa a ser único por lote)
    IF EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'documentos_finais'
          AND INDEX_NAME = 'token_acesso'
          AND NON_UNIQUE = 0
    ) THEN
        ALTER TABLE documentos_finais DROP INDEX token_acesso;
    END IF;

    -- Índice de busca por token (a página pública procura o lote por ele)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'documentos_finais'
          AND INDEX_NAME = 'idx_token'
    ) THEN
        ALTER TABLE documentos_finais ADD INDEX idx_token (token_acesso);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'documentos_finais'
          AND COLUMN_NAME = 'lote_id'
    ) THEN
        ALTER TABLE documentos_finais
            ADD COLUMN lote_id VARCHAR(64) NULL AFTER requerimento_id,
            ADD INDEX idx_lote (lote_id);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'documentos_finais'
          AND COLUMN_NAME = 'expira_em'
    ) THEN
        ALTER TABLE documentos_finais ADD COLUMN expira_em DATETIME NULL AFTER enviado_em;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'documentos_finais'
          AND COLUMN_NAME = 'revogado_em'
    ) THEN
        ALTER TABLE documentos_finais ADD COLUMN revogado_em DATETIME NULL AFTER expira_em;
    END IF;

    -- Entregas anteriores (se houver) viram um lote próprio, mantendo o token atual.
    -- Ficam sem expiração para não invalidar links já entregues a cidadãos.
    UPDATE documentos_finais
       SET lote_id = CONCAT('legado_', requerimento_id)
     WHERE lote_id IS NULL;
END$$
DELIMITER ;

CALL _migra_entrega_documentos();
DROP PROCEDURE IF EXISTS _migra_entrega_documentos;
