-- Migration: adiciona campo setor à tabela denuncias
-- Permite diferenciar denúncias de Meio Ambiente (SEMA) das de Obras e Serviços Urbanos,
-- já que as duas equipes usam o mesmo sistema.
ALTER TABLE denuncias
    ADD COLUMN setor ENUM('meio_ambiente','obras_urbanismo') NOT NULL DEFAULT 'meio_ambiente'
        COMMENT 'Equipe responsável: meio_ambiente (SEMA) ou obras_urbanismo'
    AFTER tipo_denuncia;
