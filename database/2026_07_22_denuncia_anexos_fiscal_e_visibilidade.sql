ALTER TABLE denuncia_anexos
  ADD COLUMN origem VARCHAR(20) NOT NULL DEFAULT 'denunciante' AFTER denuncia_id,
  ADD COLUMN admin_id INT NULL AFTER origem,
  ADD COLUMN visivel_denunciante TINYINT(1) NOT NULL DEFAULT 0 AFTER tipo_arquivo,
  ADD COLUMN descricao VARCHAR(255) NULL AFTER visivel_denunciante;

ALTER TABLE denuncia_historico
  ADD COLUMN visivel_denunciante TINYINT(1) NOT NULL DEFAULT 0 AFTER detalhes;
