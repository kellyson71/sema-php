-- Migration: adiciona campo notificado_fiscal_obras à tabela requerimentos
ALTER TABLE requerimentos
    ADD COLUMN notificado_fiscal_obras TINYINT(1) NULL DEFAULT NULL
        COMMENT 'Indica se o requerente foi notificado pelo fiscal de obras (1=Sim, 0=Não)'
    AFTER especificacao;
