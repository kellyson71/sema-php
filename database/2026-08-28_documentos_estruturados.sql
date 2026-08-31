-- Campos estruturados dos documentos finais de obras e numeração independente.

ALTER TABLE requerimentos
    ADD COLUMN tipo_edificacao varchar(80) DEFAULT NULL AFTER numero_pavimentos,
    ADD COLUMN obra_logradouro varchar(255) DEFAULT NULL AFTER endereco_objetivo,
    ADD COLUMN obra_lote varchar(50) DEFAULT NULL AFTER obra_logradouro,
    ADD COLUMN obra_quadra varchar(50) DEFAULT NULL AFTER obra_lote,
    ADD COLUMN obra_numero varchar(20) DEFAULT NULL AFTER obra_quadra,
    ADD COLUMN obra_sem_numero tinyint(1) NOT NULL DEFAULT 0 AFTER obra_numero,
    ADD COLUMN obra_sem_lote_quadra tinyint(1) NOT NULL DEFAULT 0 AFTER obra_sem_numero,
    ADD COLUMN obra_bairro varchar(150) DEFAULT NULL AFTER obra_sem_lote_quadra,
    ADD COLUMN responsavel_tecnico_email varchar(191) DEFAULT NULL AFTER responsavel_tecnico_numero,
    ADD COLUMN responsavel_tecnico_telefone varchar(30) DEFAULT NULL AFTER responsavel_tecnico_email,
    ADD COLUMN habite_uso varchar(80) DEFAULT NULL AFTER eng_fiscal_registro,
    ADD COLUMN habite_pavimento varchar(100) DEFAULT NULL AFTER habite_uso,
    ADD COLUMN habite_tipo_construcao varchar(100) DEFAULT NULL AFTER habite_pavimento,
    ADD COLUMN habite_padrao varchar(80) DEFAULT NULL AFTER habite_tipo_construcao,
    ADD COLUMN habite_estrutura varchar(100) DEFAULT NULL AFTER habite_padrao,
    ADD COLUMN habite_portas varchar(100) DEFAULT NULL AFTER habite_estrutura,
    ADD COLUMN habite_janelas varchar(100) DEFAULT NULL AFTER habite_portas,
    ADD COLUMN habite_piso varchar(100) DEFAULT NULL AFTER habite_janelas,
    ADD COLUMN habite_paredes varchar(100) DEFAULT NULL AFTER habite_piso,
    ADD COLUMN habite_forro varchar(100) DEFAULT NULL AFTER habite_paredes,
    ADD COLUMN habite_cobertura varchar(100) DEFAULT NULL AFTER habite_forro,
    ADD COLUMN habite_ambientes_json longtext DEFAULT NULL AFTER habite_cobertura;

CREATE TABLE IF NOT EXISTS document_number_sequences (
    template_key varchar(80) NOT NULL,
    ano smallint unsigned NOT NULL,
    ultimo_numero int unsigned NOT NULL DEFAULT 0,
    atualizado_em timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (template_key, ano)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS document_numbers (
    id bigint unsigned NOT NULL AUTO_INCREMENT,
    template_key varchar(80) NOT NULL,
    ano smallint unsigned NOT NULL,
    numero int unsigned NOT NULL,
    requerimento_id int(11) NOT NULL,
    documento_id varchar(64) DEFAULT NULL,
    criado_por_id int(11) DEFAULT NULL,
    criado_em timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id),
    UNIQUE KEY uq_document_number (template_key, ano, numero),
    KEY idx_document_number_req (requerimento_id),
    KEY idx_document_number_doc (documento_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS responsaveis_tecnicos (
    id int unsigned NOT NULL AUTO_INCREMENT,
    conselho enum('CREA','CAU') NOT NULL,
    registro varchar(100) NOT NULL,
    nome varchar(255) NOT NULL,
    email varchar(191) DEFAULT NULL,
    telefone varchar(30) DEFAULT NULL,
    criado_em timestamp NOT NULL DEFAULT current_timestamp(),
    atualizado_em timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (id),
    UNIQUE KEY uq_responsavel_registro (registro)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS responsavel_tecnico_obras (
    responsavel_tecnico_id int unsigned NOT NULL,
    requerimento_id int(11) NOT NULL,
    criado_em timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (responsavel_tecnico_id, requerimento_id),
    KEY idx_rt_obra_requerimento (requerimento_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO configuracoes (chave, nome, valor, tipo, categoria, descricao)
VALUES
    ('habite_eng_fiscal_nome', 'Engenheira fiscal do Habite-se', 'ISABELY KEYVA FERNANDES COSTA', 'texto', 'Documentos', 'Nome fixo usado no parecer da Carta de Habite-se.'),
    ('habite_eng_fiscal_registro', 'CREA da engenheira fiscal do Habite-se', '2118668139', 'texto', 'Documentos', 'Registro fixo usado no parecer da Carta de Habite-se.')
ON DUPLICATE KEY UPDATE valor = VALUES(valor), nome = VALUES(nome), descricao = VALUES(descricao);
