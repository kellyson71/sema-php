-- Histórico das versões anteriores dos modelos personalizados.
-- Aplicar uma vez no banco de dados da instalação.

CREATE TABLE IF NOT EXISTS `user_template_versions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `numero_versao` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `descricao` varchar(500) DEFAULT NULL,
  `icone` varchar(100) DEFAULT NULL,
  `template_base` varchar(255) DEFAULT NULL,
  `conteudo_html` longtext NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_template_usuario` (`template_id`, `usuario_id`),
  KEY `idx_template_versao` (`template_id`, `numero_versao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
