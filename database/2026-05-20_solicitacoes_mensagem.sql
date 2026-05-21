-- Migration: adiciona coluna mensagem em solicitacoes_assinatura
-- Criada em 2026-05-20 para corrigir INSERT que falhava com "Unknown column 'mensagem'"
-- Usa IF NOT EXISTS pois multi_setor.sql pode ter criado a coluna junto com a tabela.

ALTER TABLE `solicitacoes_assinatura`
    ADD COLUMN IF NOT EXISTS `mensagem` TEXT NULL AFTER `destinatario_id`;
