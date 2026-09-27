-- Migration: licenciamento ambiental — LAU, parecer de pendências e placa (2026-09-27)
-- Campos preenchidos pela equipe em "Editar dados do processo" (só nos tipos ambientais).
-- A validade da LAU é calculada: data_recebimento_processo + 5 anos (ParecerService).

ALTER TABLE `requerimentos`
  ADD COLUMN IF NOT EXISTS `data_recebimento_processo` DATE NULL COMMENT 'Recebimento do processo pela equipe ambiental; base da validade da licença',
  ADD COLUMN IF NOT EXISTS `endereco_empreendedor` VARCHAR(500) NULL COMMENT 'Endereço do empreendedor (LAU)',
  ADD COLUMN IF NOT EXISTS `caracterizacao_empreendimento` TEXT NULL COMMENT 'Caracterização do empreendimento (LAU)';
