-- Migration: adiciona coluna mensagem em solicitacoes_assinatura
-- Criada em 2026-05-20 para corrigir INSERT que falhava com "Unknown column 'mensagem'"

ALTER TABLE `solicitacoes_assinatura`
    ADD COLUMN `mensagem` TEXT NULL AFTER `destinatario_id`;
