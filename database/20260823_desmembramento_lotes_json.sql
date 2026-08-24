-- Estrutura dos lotes e confrontações informados no formulário público.
ALTER TABLE requerimentos
    ADD COLUMN desmembramento_lotes_json LONGTEXT NULL
    COMMENT 'JSON com lotes, áreas, confrontações, soma e consistência do desmembramento';
