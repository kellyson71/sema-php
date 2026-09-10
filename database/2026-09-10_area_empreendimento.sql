-- Corrige perda do campo "Área do empreendimento" nos requerimentos de licença
-- ambiental (licença de operação, instalação/operação, operação corretiva e
-- demais tipos ambientais): o campo existe no formulário público desde sempre,
-- mas nunca era lido em processar_formulario.php nem persistido no banco, então
-- ParecerService::preencherDados() sempre caía no fallback "a ser informada".

ALTER TABLE requerimentos ADD COLUMN area_empreendimento VARCHAR(50) NULL AFTER area_lote;
