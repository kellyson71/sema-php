-- Migration: responsável atribuído à denúncia (2026-09-25)
-- admin_id continua sendo quem REGISTROU; responsavel_id é quem está cuidando dela agora.

ALTER TABLE denuncias
  ADD COLUMN responsavel_id INT NULL AFTER admin_id,
  ADD INDEX idx_denuncia_responsavel (responsavel_id);
