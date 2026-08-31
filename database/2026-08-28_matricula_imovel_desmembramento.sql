-- Adicionar campo matricula_imovel para registro de desmembramento no Cartório de Imóveis (RGI)
ALTER TABLE `requerimentos`
    ADD COLUMN `matricula_imovel` VARCHAR(100) DEFAULT NULL COMMENT 'Nº da Matrícula no Cartório de Registro de Imóveis (RGI)' AFTER `cadastro_imobiliario`;
