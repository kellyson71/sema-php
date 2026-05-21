-- Adiciona campo primeiro_acesso em administradores
-- Quando 1, o usuário é forçado a trocar a senha no próximo login.
-- Novos usuários criados pelo painel recebem este campo = 1 automaticamente.

ALTER TABLE `administradores`
    ADD COLUMN IF NOT EXISTS `primeiro_acesso` TINYINT(1) NOT NULL DEFAULT 0 AFTER `ativo`;
