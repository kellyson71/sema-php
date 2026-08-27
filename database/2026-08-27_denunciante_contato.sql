-- Dados de contato do denunciante.
-- O formulário público passou a usar um único bloco de identificação (nome,
-- CPF, e-mail e telefone) para todos os serviços, inclusive denúncia. Antes a
-- denúncia tinha um bloco próprio que só guardava o nome, o que impedia dar
-- retorno a quem optou por acompanhar. Continuam nulos na denúncia anônima.

ALTER TABLE denuncias
    ADD COLUMN denunciante_cpf VARCHAR(20) NULL AFTER denunciante_nome,
    ADD COLUMN denunciante_email VARCHAR(191) NULL AFTER denunciante_cpf,
    ADD COLUMN denunciante_telefone VARCHAR(20) NULL AFTER denunciante_email;
