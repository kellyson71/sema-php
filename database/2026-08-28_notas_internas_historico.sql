-- Permitir múltiplas anotações internas por requerimento (formato histórico/chat da equipe)
-- 2026-08-28

ALTER TABLE `requerimento_notas_internas`
  DROP INDEX IF EXISTS `requerimento_id`,
  ADD INDEX IF NOT EXISTS `idx_requerimento_id` (`requerimento_id`);
