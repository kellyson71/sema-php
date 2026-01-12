CREATE TABLE `assinaturas_digitais` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `documento_id` varchar(64) NOT NULL,
  `requerimento_id` int(11) NOT NULL,
  `tipo_documento` varchar(50) NOT NULL DEFAULT 'parecer',
  `nome_arquivo` varchar(255) NOT NULL,
  `caminho_arquivo` varchar(500) NOT NULL,
  `hash_documento` varchar(64) NOT NULL,
  `assinante_id` int(11) NOT NULL,
  `assinante_nome` varchar(255) NOT NULL,
  `assinante_cpf` varchar(20) DEFAULT NULL,
  `assinante_cargo` varchar(100) DEFAULT NULL,
  `tipo_assinatura` enum('desenho','texto') NOT NULL,
  `assinatura_visual` text,
  `assinatura_criptografada` text NOT NULL,
  `timestamp_assinatura` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_assinante` varchar(45) DEFAULT NULL,
  `metadados_json` text,
  `data_criacao` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `documento_id` (`documento_id`),
  KEY `requerimento_id` (`requerimento_id`),
  KEY `assinante_id` (`assinante_id`),
  KEY `idx_hash` (`hash_documento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `administradores`
ADD COLUMN `cpf` varchar(14) DEFAULT NULL AFTER `email`,
ADD COLUMN `cargo` varchar(100) DEFAULT 'Administrador' AFTER `cpf`;

