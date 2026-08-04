-- Migration: adiciona campo setor à tabela administradores
-- Usado para detectar automaticamente a equipe (Meio Ambiente x Obras/Urbanismo)
-- de uma denúncia registrada internamente, com base em quem está logado.
-- 'ambos' é para quem atende as duas equipes (ex: admin geral, secretário) e
-- continua escolhendo manualmente ao registrar.
ALTER TABLE administradores
    ADD COLUMN setor ENUM('meio_ambiente','obras_urbanismo','ambos') NOT NULL DEFAULT 'meio_ambiente'
        COMMENT 'Equipe do administrador, usada para detectar o setor da denúncia automaticamente'
    AFTER nivel;
