-- ============================================================
-- ASSINATURA ELETRÔNICA AVANÇADA (Lei 14.063/2020, art. 4º, II)
-- Cada administrador passa a ter seu próprio par de chaves RSA-2048.
-- A chave privada é cifrada com AES-256-GCM derivada do PIN de
-- assinatura (PBKDF2) — o servidor NUNCA armazena a chave em claro.
-- Isso garante "controle exclusivo do signatário", requisito legal
-- do nível avançado.
-- ============================================================

-- 1. Chaves por administrador
CREATE TABLE IF NOT EXISTS `admin_chaves_assinatura` (
  `admin_id`              INT          NOT NULL PRIMARY KEY,
  `chave_publica`         TEXT         NOT NULL,
  `chave_privada_cifrada` TEXT         NOT NULL,  -- base64(iv):base64(tag):base64(ciphertext)
  `salt`                  VARCHAR(64)  NOT NULL,  -- salt PBKDF2 (hex)
  `pin_hash`              VARCHAR(255) NOT NULL,  -- bcrypt do PIN p/ validação rápida
  `algoritmo`             VARCHAR(20)  NOT NULL DEFAULT 'RSA-2048',
  `criada_em`             TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizada_em`         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_chave_admin` FOREIGN KEY (`admin_id`) REFERENCES `administradores`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Colunas novas em assinaturas_digitais
--    hash_conteudo: SHA-256 do HTML-fonte canônico. É o que cada signatário
--    assina com RSA — estável entre regravações do PDF (co-assinatura).
--    hash_documento continua sendo o SHA-256 do PDF físico (integridade do arquivo).
--    chave_publica: snapshot PEM da chave usada — verificação continua válida
--    mesmo se o admin trocar de chave depois.
ALTER TABLE `assinaturas_digitais`
  ADD COLUMN IF NOT EXISTS `hash_conteudo`    VARCHAR(64) NULL AFTER `hash_documento`,
  ADD COLUMN IF NOT EXISTS `chave_publica`    TEXT        NULL AFTER `assinatura_criptografada`,
  ADD COLUMN IF NOT EXISTS `nivel_assinatura` VARCHAR(20) NOT NULL DEFAULT 'simples' AFTER `tipo_assinatura`;
-- níveis: 'simples' (legado), 'avancada' (RSA por admin), 'sem_assinatura'

-- 3. Posição customizável do bloco de assinatura no PDF (mm, página final)
ALTER TABLE `documentos_fonte`
  ADD COLUMN IF NOT EXISTS `sig_pos_x` FLOAT NULL,
  ADD COLUMN IF NOT EXISTS `sig_pos_y` FLOAT NULL;

-- 4. Garante chave única para ON DUPLICATE KEY em solicitações
ALTER TABLE `solicitacoes_assinatura`
  ADD UNIQUE INDEX IF NOT EXISTS `uq_doc_dest` (`documento_id`, `destinatario_id`);
