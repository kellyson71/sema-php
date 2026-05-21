-- Migration: fluxo por setores (2026-04-26)
-- Adiciona/normaliza setor_atual e aguardando_acao em requerimentos
-- Adiciona group_id e visivel_para em assinaturas_digitais

-- setor_atual: garante enum correto (ADD ignora se já existe, MODIFY corrige o tipo)
ALTER TABLE `requerimentos`
  ADD COLUMN IF NOT EXISTS `setor_atual` ENUM('setor1','setor2','setor3') NOT NULL DEFAULT 'setor1' AFTER `status`;

ALTER TABLE `requerimentos`
  MODIFY COLUMN `setor_atual` ENUM('setor1','setor2','setor3') NOT NULL DEFAULT 'setor1';

ALTER TABLE `requerimentos`
  ADD COLUMN IF NOT EXISTS `aguardando_acao` VARCHAR(50) NOT NULL DEFAULT 'triagem_setor1' AFTER `setor_atual`;

-- Ajusta processos existentes por status legado
UPDATE `requerimentos`
SET `aguardando_acao` = 'concluido'
WHERE `status` IN ('Finalizado','Indeferido','Cancelado')
  AND `aguardando_acao` = 'triagem_setor1';

UPDATE `requerimentos`
SET `aguardando_acao` = 'boleto_pendente'
WHERE `status` IN ('Aguardando boleto','Boleto pago')
  AND `aguardando_acao` = 'triagem_setor1';

-- Normaliza registros que ficaram com valor inválido do enum antigo
UPDATE `requerimentos`
SET `setor_atual` = 'setor1'
WHERE `setor_atual` NOT IN ('setor1','setor2','setor3');

-- assinaturas_digitais: group_id e visivel_para
ALTER TABLE `assinaturas_digitais`
  ADD COLUMN IF NOT EXISTS `group_id` VARCHAR(64) NULL AFTER `documento_id`,
  ADD COLUMN IF NOT EXISTS `visivel_para` VARCHAR(50) NOT NULL DEFAULT 'todos' AFTER `group_id`;

UPDATE `assinaturas_digitais`
SET `group_id` = `documento_id`
WHERE `group_id` IS NULL;
