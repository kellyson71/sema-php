-- Migration: assinatura_v2 (2026-05-20)
-- Fase 3: adiciona 'sem_assinatura' ao enum de tipo_assinatura
--
-- Contexto: o modo "Finalizar sem assinar" introduzido no modal de assinatura
-- grava tipo_assinatura = 'sem_assinatura' na tabela assinaturas_digitais.
-- Esta migration estende o ENUM para aceitar esse novo valor.

ALTER TABLE `assinaturas_digitais`
  MODIFY COLUMN `tipo_assinatura`
    ENUM('desenho','texto','digital_sema','sem_assinatura')
    NOT NULL DEFAULT 'digital_sema';
