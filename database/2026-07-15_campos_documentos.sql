-- Campos adicionais para os modelos de Alvará de Construção, Habite-se e Desmembramento,
-- alinhando o formulário e os documentos gerados aos originais da SEMA.
ALTER TABLE `requerimentos`
    ADD COLUMN `cadastro_imobiliario`     varchar(50)  DEFAULT NULL COMMENT 'Nº do cadastro imobiliário / sequencial'         AFTER `especificacao`,
    ADD COLUMN `inicio_obra`              date         DEFAULT NULL COMMENT 'Início da obra (construção/habite-se)'            AFTER `cadastro_imobiliario`,
    ADD COLUMN `termino_obra`             date         DEFAULT NULL COMMENT 'Término/previsão de término da obra'             AFTER `inicio_obra`,
    ADD COLUMN `area_total_terreno`       varchar(50)  DEFAULT NULL COMMENT 'Área total do terreno (desmembramento)'          AFTER `termino_obra`,
    ADD COLUMN `area_remanescente`        varchar(50)  DEFAULT NULL COMMENT 'Área remanescente após desmembramento'           AFTER `area_total_terreno`,
    ADD COLUMN `alvara_construcao_numero` varchar(50)  DEFAULT NULL COMMENT 'Nº do alvará de construção anterior (habite-se)' AFTER `area_remanescente`,
    ADD COLUMN `eng_fiscal_nome`          varchar(255) DEFAULT NULL COMMENT 'Engenheiro fiscal que emitiu o parecer (habite-se)' AFTER `alvara_construcao_numero`,
    ADD COLUMN `eng_fiscal_registro`      varchar(100) DEFAULT NULL COMMENT 'CREA/registro do engenheiro fiscal (habite-se)'  AFTER `eng_fiscal_nome`;
