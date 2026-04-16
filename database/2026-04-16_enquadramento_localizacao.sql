-- Migration: Adicionar campos de enquadramento CONEMA e localização Google Maps
-- Data: 2026-04-16
-- Contexto: Adequação ao fluxo de licenciamento ambiental (Resolução CONEMA 04/2009)

ALTER TABLE requerimentos
    ADD COLUMN enquadramento_atividade VARCHAR(255) DEFAULT NULL COMMENT 'Slug da atividade selecionada na tabela CONEMA 04/2009' AFTER notificado_fiscal_obras,
    ADD COLUMN localizacao_google_maps VARCHAR(500) DEFAULT NULL COMMENT 'Link do Google Maps do empreendimento' AFTER enquadramento_atividade;
