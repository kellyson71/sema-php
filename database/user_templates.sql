-- Migration: Templates personalizados por usuário
-- Cada administrador pode salvar seus próprios templates reutilizáveis.
-- Os templates padrão globais são carregados do disco; versões editadas ficam aqui.

CREATE TABLE IF NOT EXISTS `user_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `descricao` varchar(500) DEFAULT NULL,
  `template_base` varchar(255) DEFAULT NULL,
  `conteudo_html` longtext NOT NULL,
  `data_criacao` datetime DEFAULT current_timestamp(),
  `data_atualizacao` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
