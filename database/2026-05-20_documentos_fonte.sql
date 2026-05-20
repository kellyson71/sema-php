-- Tabela para persistir HTML-fonte dos documentos assinados.
-- Permite co-assinatura: o HTML é recarregado e o PDF é regravado com assinantes acumulados.
CREATE TABLE IF NOT EXISTS `documentos_fonte` (
    `documento_id`   VARCHAR(64)   NOT NULL,
    `requerimento_id` INT          NOT NULL,
    `conteudo_html`  LONGTEXT      NOT NULL,
    `tipo_documento` VARCHAR(100)  NULL,
    `caminho_arquivo` VARCHAR(500) NOT NULL,
    `criado_por_id`  INT           NOT NULL,
    `criado_em`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`documento_id`),
    INDEX `idx_req` (`requerimento_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
