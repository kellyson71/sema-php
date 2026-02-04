-- Adicionar colunas faltantes na tabela requerimentos para suportar os dados dos templates
-- Campos identificados:
-- area_construcao (Alvará de Construção)
-- numero_pavimentos (Alvará de Construção)
-- area_construida (Licença Prévia)
-- area_lote (Desmembramento)
-- responsavel_tecnico_nome (Geral)
-- responsavel_tecnico_registro (Geral)
-- responsavel_tecnico_numero (Geral - ART/RRT/TRT)
-- especificacao (Geral - Descrição)

ALTER TABLE requerimentos ADD COLUMN area_construcao VARCHAR(50) NULL AFTER endereco_objetivo;
ALTER TABLE requerimentos ADD COLUMN numero_pavimentos VARCHAR(50) NULL AFTER area_construcao;
ALTER TABLE requerimentos ADD COLUMN area_construida VARCHAR(50) NULL AFTER numero_pavimentos;
ALTER TABLE requerimentos ADD COLUMN area_lote VARCHAR(50) NULL AFTER area_construida;
ALTER TABLE requerimentos ADD COLUMN responsavel_tecnico_nome VARCHAR(255) NULL AFTER area_lote;
ALTER TABLE requerimentos ADD COLUMN responsavel_tecnico_registro VARCHAR(100) NULL AFTER responsavel_tecnico_nome;
ALTER TABLE requerimentos ADD COLUMN responsavel_tecnico_numero VARCHAR(100) NULL AFTER responsavel_tecnico_registro;
ALTER TABLE requerimentos ADD COLUMN especificacao TEXT NULL AFTER responsavel_tecnico_numero;
