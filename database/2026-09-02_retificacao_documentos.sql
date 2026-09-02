-- Retificação de documento já assinado e entregue.
--
-- Antes disso, corrigir um alvará depois de entregue só era possível emitindo
-- um documento novo, com outro número: o antigo continuava verificando como
-- válido para sempre no /verificar, e o contribuinte ficava com dois documentos
-- vigentes do mesmo processo.
--
-- Agora a reemissão mantém o número original e marca a versão anterior como
-- substituída, apontando para a que passou a valer.

ALTER TABLE `assinaturas_digitais`
    ADD COLUMN IF NOT EXISTS `substituido_por_documento_id` VARCHAR(64) DEFAULT NULL
        COMMENT 'documento_id da versão que substituiu esta',
    ADD COLUMN IF NOT EXISTS `substituido_em` DATETIME DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `substituido_por_admin_id` INT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `motivo_substituicao` VARCHAR(500) DEFAULT NULL;

-- O verificador público consulta por documento_id e precisa saber, na mesma
-- leitura, se aquela versão ainda é a vigente.
ALTER TABLE `assinaturas_digitais`
    ADD INDEX IF NOT EXISTS `idx_assinatura_substituido` (`substituido_por_documento_id`);
