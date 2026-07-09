-- Migration: Adicionar campos de padrão popular, previsão de obra e protocolo oficial
-- Data: 2026-07-08
-- Contexto: Sugestões de melhoria no fluxo de alvará de construção, habite-se e desmembramento

ALTER TABLE requerimentos
    ADD COLUMN padrao_popular ENUM('sim','nao') DEFAULT NULL COMMENT 'Obra padrão popular (menor que 70m²)',
    ADD COLUMN data_inicio_obra DATE DEFAULT NULL COMMENT 'Previsão de início da obra',
    ADD COLUMN data_termino_obra DATE DEFAULT NULL COMMENT 'Previsão de término da obra',
    ADD COLUMN protocolo_oficial VARCHAR(50) DEFAULT NULL COMMENT 'Protocolo oficial da prefeitura (processo administrativo externo)';
