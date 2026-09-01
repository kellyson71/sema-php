-- Lote de ajustes pedidos pelo cliente após revisão do formulário reformulado:
-- campos internos do habite-se (só admin) e dados de Corpo de Bombeiros (opcional).

ALTER TABLE requerimentos
    ADD COLUMN IF NOT EXISTS habite_padrao_interno VARCHAR(100) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS habite_denominacao_interna VARCHAR(150) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS bombeiro_possui TINYINT(1) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS bombeiro_numero VARCHAR(100) DEFAULT NULL;
