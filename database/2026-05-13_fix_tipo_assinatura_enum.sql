-- Fix: adiciona 'digital_sema' ao enum de tipo_assinatura
-- processa_assinatura.php inseria esse valor mas ele não existia no enum,
-- causando erro interno ao assinar documentos pelo fluxo de secretário.
ALTER TABLE `assinaturas_digitais`
  MODIFY `tipo_assinatura` ENUM('desenho','texto','digital_sema') NOT NULL;
