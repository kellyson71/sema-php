-- ============================================================
-- CO-ASSINATURA — direcionamento de notificações e cancelamento
-- ============================================================

-- 1. Notificações direcionadas a um administrador específico.
--    NULL = broadcast (todos veem), como era antes.
ALTER TABLE `admin_notifications`
  ADD COLUMN IF NOT EXISTS `destinatario_admin_id` INT NULL AFTER `requerimento_id`,
  ADD INDEX IF NOT EXISTS `idx_notif_destinatario` (`destinatario_admin_id`);

-- 2. Permitir que o solicitante CANCELE uma solicitação pendente
--    (sem perder o rastro — fica registrado como 'cancelado').
ALTER TABLE `solicitacoes_assinatura`
  MODIFY `status` ENUM('pendente','assinado','recusado','cancelado') NOT NULL DEFAULT 'pendente';
