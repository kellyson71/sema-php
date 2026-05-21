-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Tempo de geração: 21/03/2026 às 01:28
-- Versão do servidor: 11.8.3-MariaDB-log
-- Versão do PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `u492577848_SEMA`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `administradores`
--

CREATE TABLE `administradores` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `nome_completo` varchar(255) DEFAULT NULL,
  `email` varchar(191) NOT NULL,
  `cpf` varchar(14) DEFAULT NULL,
  `cargo` varchar(100) DEFAULT 'Administrador',
  `matricula_portaria` varchar(100) DEFAULT NULL,
  `senha` varchar(191) NOT NULL,
  `totp_secret` varchar(255) DEFAULT NULL,
  `nivel` enum('admin','admin_geral','operador','secretario','analista','fiscal') NOT NULL DEFAULT 'operador',
  `ativo` tinyint(1) DEFAULT 1,
  `ultimo_acesso` timestamp NULL DEFAULT NULL,
  `data_cadastro` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `assinaturas_digitais`
--

CREATE TABLE `assinaturas_digitais` (
  `id` int(11) NOT NULL,
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
  `tipo_assinatura` enum('desenho','texto','digital_sema') NOT NULL,
  `assinatura_visual` text DEFAULT NULL,
  `assinatura_criptografada` text NOT NULL,
  `timestamp_assinatura` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_assinante` varchar(45) DEFAULT NULL,
  `metadados_json` text DEFAULT NULL,
  `data_criacao` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `configuracoes`
--

CREATE TABLE `configuracoes` (
  `id` int(11) NOT NULL,
  `chave` varchar(50) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `valor` text DEFAULT NULL,
  `tipo` enum('texto','numero','booleano','select','textarea') DEFAULT 'texto',
  `categoria` varchar(50) NOT NULL DEFAULT 'Geral',
  `descricao` text DEFAULT NULL,
  `opcoes` text DEFAULT NULL,
  `data_criacao` timestamp NULL DEFAULT current_timestamp(),
  `data_atualizacao` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `denuncias`
--

CREATE TABLE `denuncias` (
  `id` int(11) NOT NULL,
  `data_registro` datetime DEFAULT current_timestamp(),
  `infrator_nome` varchar(255) NOT NULL,
  `infrator_cpf_cnpj` varchar(20) DEFAULT NULL,
  `infrator_endereco` text DEFAULT NULL,
  `observacoes` text NOT NULL,
  `status` varchar(50) DEFAULT 'Pendente',
  `admin_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `denuncia_anexos`
--

CREATE TABLE `denuncia_anexos` (
  `id` int(11) NOT NULL,
  `denuncia_id` int(11) NOT NULL,
  `nome_arquivo` varchar(255) NOT NULL,
  `caminho_arquivo` varchar(255) NOT NULL,
  `tipo_arquivo` varchar(50) NOT NULL,
  `data_upload` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `documentos`
--

CREATE TABLE `documentos` (
  `id` int(11) NOT NULL,
  `requerimento_id` int(11) NOT NULL,
  `campo_formulario` varchar(50) NOT NULL,
  `nome_original` varchar(191) NOT NULL,
  `nome_salvo` varchar(191) NOT NULL,
  `caminho` varchar(191) NOT NULL,
  `tipo_arquivo` varchar(100) DEFAULT NULL,
  `tamanho` int(11) NOT NULL,
  `data_upload` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `email_logs`
--

CREATE TABLE `email_logs` (
  `id` int(11) NOT NULL,
  `requerimento_id` int(11) DEFAULT NULL,
  `email_destino` varchar(191) NOT NULL,
  `assunto` varchar(255) NOT NULL,
  `mensagem` text DEFAULT NULL,
  `usuario_envio` varchar(100) DEFAULT NULL,
  `status` enum('SUCESSO','ERRO') NOT NULL,
  `eh_teste` tinyint(1) DEFAULT 0,
  `erro` text DEFAULT NULL,
  `detalhes_envio` text DEFAULT NULL,
  `data_envio` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura stand-in para view `email_logs_reais`
-- (Veja abaixo para a visão atual)
--
CREATE TABLE `email_logs_reais` (
`id` int(11)
,`requerimento_id` int(11)
,`email_destino` varchar(191)
,`assunto` varchar(255)
,`mensagem` text
,`usuario_envio` varchar(100)
,`status` enum('SUCESSO','ERRO')
,`eh_teste` tinyint(1)
,`erro` text
,`detalhes_envio` text
,`data_envio` timestamp
);

-- --------------------------------------------------------

--
-- Estrutura stand-in para view `estatisticas_email`
-- (Veja abaixo para a visão atual)
--
CREATE TABLE `estatisticas_email` (
`data` date
,`status` enum('SUCESSO','ERRO')
,`eh_teste` tinyint(1)
,`total` bigint(21)
,`emails_unicos` bigint(21)
);

-- --------------------------------------------------------

--
-- Estrutura para tabela `historico_acoes`
--

CREATE TABLE `historico_acoes` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `requerimento_id` int(11) DEFAULT NULL,
  `acao` text NOT NULL,
  `data_acao` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `historico_assinaturas`
--

CREATE TABLE `historico_assinaturas` (
  `id` int(11) NOT NULL,
  `documento_id` varchar(64) DEFAULT NULL,
  `requerimento_id` int(11) DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `evento` varchar(30) NOT NULL,
  `origem` varchar(30) DEFAULT NULL,
  `status` enum('sucesso','erro') NOT NULL,
  `email_destino` varchar(191) DEFAULT NULL,
  `codigo_hash` varchar(64) DEFAULT NULL,
  `codigo_ultimos` varchar(2) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `accept_language` varchar(50) DEFAULT NULL,
  `host` varchar(191) DEFAULT NULL,
  `nome_arquivo` varchar(255) DEFAULT NULL,
  `hash_documento` varchar(64) DEFAULT NULL,
  `erro` text DEFAULT NULL,
  `data_evento` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `parecer_rascunhos`
--

CREATE TABLE `parecer_rascunhos` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `requerimento_id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `conteudo_html` longtext DEFAULT NULL,
  `dados_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dados_json`)),
  `data_criacao` datetime DEFAULT current_timestamp(),
  `data_atualizacao` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `passkeys`
--

CREATE TABLE `passkeys` (
  `id` varchar(255) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `credential_data` text NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `proprietarios`
--

CREATE TABLE `proprietarios` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `cpf_cnpj` varchar(20) NOT NULL,
  `mesmo_requerente` tinyint(1) DEFAULT 0,
  `requerente_id` int(11) DEFAULT NULL,
  `data_cadastro` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `requerentes`
--

CREATE TABLE `requerentes` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `email` varchar(191) NOT NULL,
  `cpf_cnpj` varchar(20) NOT NULL,
  `telefone` varchar(20) NOT NULL,
  `data_cadastro` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `requerimentos`
--

CREATE TABLE `requerimentos` (
  `id` int(11) NOT NULL,
  `protocolo` varchar(20) NOT NULL,
  `tipo_alvara` varchar(50) NOT NULL,
  `requerente_id` int(11) NOT NULL,
  `proprietario_id` int(11) DEFAULT NULL,
  `endereco_objetivo` text NOT NULL,
  `area_construcao` varchar(50) DEFAULT NULL,
  `numero_pavimentos` varchar(50) DEFAULT NULL,
  `area_construida` varchar(50) DEFAULT NULL,
  `area_lote` varchar(50) DEFAULT NULL,
  `responsavel_tecnico_nome` varchar(255) DEFAULT NULL,
  `responsavel_tecnico_registro` varchar(100) DEFAULT NULL,
  `responsavel_tecnico_tipo_documento` varchar(10) DEFAULT NULL,
  `responsavel_tecnico_numero` varchar(100) DEFAULT NULL,
  `especificacao` text DEFAULT NULL,
  `status` enum('Pendente','Em análise','Aguardando Fiscalização','Aprovado','Reprovado','Cancelado','Indeferido','Finalizado','Apto a gerar alvará','Alvará Emitido') NOT NULL DEFAULT 'Pendente',
  `observacoes` text DEFAULT NULL,
  `data_envio` timestamp NULL DEFAULT current_timestamp(),
  `data_atualizacao` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `visualizado` tinyint(1) NOT NULL DEFAULT 0,
  `ctf_numero` varchar(50) DEFAULT NULL COMMENT 'Cadastro Técnico Federal',
  `licenca_anterior_numero` varchar(50) DEFAULT NULL COMMENT 'Número da licença anterior',
  `publicacao_diario_oficial` varchar(255) DEFAULT NULL COMMENT 'Dados da publicação em Diário\r\n  Oficial',
  `comprovante_pagamento` varchar(255) DEFAULT NULL COMMENT 'Recibo/código do pagamento',
  `possui_estudo_ambiental` tinyint(1) DEFAULT NULL COMMENT 'Indica se possui estudo ambiental',
  `tipo_estudo_ambiental` varchar(100) DEFAULT NULL COMMENT 'Tipo de estudo ambiental\r\n  informado'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `requerimentos_arquivados`
--

CREATE TABLE `requerimentos_arquivados` (
  `id` int(11) NOT NULL,
  `requerimento_id` int(11) NOT NULL,
  `protocolo` varchar(20) NOT NULL,
  `tipo_alvara` varchar(50) NOT NULL,
  `requerente_id` int(11) NOT NULL,
  `proprietario_id` int(11) DEFAULT NULL,
  `endereco_objetivo` text NOT NULL,
  `status` enum('Pendente','Em análise','Aguardando Fiscalização','Aprovado','Reprovado','Cancelado','Indeferido','Finalizado','Apto a gerar alvará','Alvará Emitido') NOT NULL DEFAULT 'Pendente',
  `observacoes` text DEFAULT NULL,
  `data_envio` timestamp NOT NULL,
  `data_atualizacao` timestamp NOT NULL,
  `data_arquivamento` timestamp NULL DEFAULT current_timestamp(),
  `admin_arquivamento` int(11) DEFAULT NULL,
  `motivo_arquivamento` text DEFAULT NULL,
  `requerente_nome` varchar(255) DEFAULT NULL,
  `requerente_email` varchar(191) DEFAULT NULL,
  `requerente_cpf_cnpj` varchar(20) DEFAULT NULL,
  `requerente_telefone` varchar(20) DEFAULT NULL,
  `proprietario_nome` varchar(255) DEFAULT NULL,
  `proprietario_cpf_cnpj` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `administradores`
--
ALTER TABLE `administradores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Índices de tabela `assinaturas_digitais`
--
ALTER TABLE `assinaturas_digitais`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `documento_id` (`documento_id`),
  ADD KEY `requerimento_id` (`requerimento_id`),
  ADD KEY `assinante_id` (`assinante_id`),
  ADD KEY `idx_hash` (`hash_documento`);

--
-- Índices de tabela `configuracoes`
--
ALTER TABLE `configuracoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `chave` (`chave`);

--
-- Índices de tabela `denuncias`
--
ALTER TABLE `denuncias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Índices de tabela `denuncia_anexos`
--
ALTER TABLE `denuncia_anexos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `denuncia_id` (`denuncia_id`);

--
-- Índices de tabela `documentos`
--
ALTER TABLE `documentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `requerimento_id` (`requerimento_id`);

--
-- Índices de tabela `email_logs`
--
ALTER TABLE `email_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `requerimento_id` (`requerimento_id`),
  ADD KEY `idx_email_logs_eh_teste` (`eh_teste`),
  ADD KEY `idx_email_logs_data_status` (`data_envio`,`status`),
  ADD KEY `idx_email_logs_email_destino` (`email_destino`);

--
-- Índices de tabela `historico_acoes`
--
ALTER TABLE `historico_acoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `requerimento_id` (`requerimento_id`);

--
-- Índices de tabela `historico_assinaturas`
--
ALTER TABLE `historico_assinaturas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `documento_id` (`documento_id`),
  ADD KEY `requerimento_id` (`requerimento_id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `evento` (`evento`),
  ADD KEY `status` (`status`),
  ADD KEY `data_evento` (`data_evento`);

--
-- Índices de tabela `parecer_rascunhos`
--
ALTER TABLE `parecer_rascunhos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_usuario` (`usuario_id`),
  ADD KEY `idx_requerimento` (`requerimento_id`);

--
-- Índices de tabela `passkeys`
--
ALTER TABLE `passkeys`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id_idx` (`admin_id`);

--
-- Índices de tabela `proprietarios`
--
ALTER TABLE `proprietarios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `requerente_id` (`requerente_id`);

--
-- Índices de tabela `requerentes`
--
ALTER TABLE `requerentes`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `requerimentos`
--
ALTER TABLE `requerimentos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `protocolo` (`protocolo`),
  ADD KEY `requerente_id` (`requerente_id`),
  ADD KEY `proprietario_id` (`proprietario_id`);

--
-- Índices de tabela `requerimentos_arquivados`
--
ALTER TABLE `requerimentos_arquivados`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_protocolo` (`protocolo`),
  ADD KEY `idx_requerimento_id` (`requerimento_id`),
  ADD KEY `idx_data_arquivamento` (`data_arquivamento`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `administradores`
--
ALTER TABLE `administradores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `assinaturas_digitais`
--
ALTER TABLE `assinaturas_digitais`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `configuracoes`
--
ALTER TABLE `configuracoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `denuncias`
--
ALTER TABLE `denuncias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `denuncia_anexos`
--
ALTER TABLE `denuncia_anexos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `documentos`
--
ALTER TABLE `documentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `historico_acoes`
--
ALTER TABLE `historico_acoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `historico_assinaturas`
--
ALTER TABLE `historico_assinaturas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `parecer_rascunhos`
--
ALTER TABLE `parecer_rascunhos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `proprietarios`
--
ALTER TABLE `proprietarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `requerentes`
--
ALTER TABLE `requerentes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `requerimentos`
--
ALTER TABLE `requerimentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `requerimentos_arquivados`
--
ALTER TABLE `requerimentos_arquivados`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------

--
-- Estrutura para view `email_logs_reais`
--
DROP TABLE IF EXISTS `email_logs_reais`;

CREATE ALGORITHM=UNDEFINED DEFINER=`u492577848_SEMA`@`127.0.0.1` SQL SECURITY DEFINER VIEW `email_logs_reais`  AS SELECT `email_logs`.`id` AS `id`, `email_logs`.`requerimento_id` AS `requerimento_id`, `email_logs`.`email_destino` AS `email_destino`, `email_logs`.`assunto` AS `assunto`, `email_logs`.`mensagem` AS `mensagem`, `email_logs`.`usuario_envio` AS `usuario_envio`, `email_logs`.`status` AS `status`, `email_logs`.`eh_teste` AS `eh_teste`, `email_logs`.`erro` AS `erro`, `email_logs`.`detalhes_envio` AS `detalhes_envio`, `email_logs`.`data_envio` AS `data_envio` FROM `email_logs` WHERE `email_logs`.`eh_teste` = 0 ;

-- --------------------------------------------------------

--
-- Estrutura para view `estatisticas_email`
--
DROP TABLE IF EXISTS `estatisticas_email`;

CREATE ALGORITHM=UNDEFINED DEFINER=`u492577848_SEMA`@`127.0.0.1` SQL SECURITY DEFINER VIEW `estatisticas_email`  AS SELECT cast(`email_logs`.`data_envio` as date) AS `data`, `email_logs`.`status` AS `status`, `email_logs`.`eh_teste` AS `eh_teste`, count(0) AS `total`, count(distinct `email_logs`.`email_destino`) AS `emails_unicos` FROM `email_logs` WHERE `email_logs`.`data_envio` >= current_timestamp() - interval 30 day GROUP BY cast(`email_logs`.`data_envio` as date), `email_logs`.`status`, `email_logs`.`eh_teste` ORDER BY cast(`email_logs`.`data_envio` as date) DESC, `email_logs`.`status` ASC ;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `denuncias`
--
ALTER TABLE `denuncias`
  ADD CONSTRAINT `denuncias_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `administradores` (`id`);

--
-- Restrições para tabelas `denuncia_anexos`
--
ALTER TABLE `denuncia_anexos`
  ADD CONSTRAINT `denuncia_anexos_ibfk_1` FOREIGN KEY (`denuncia_id`) REFERENCES `denuncias` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `passkeys`
--
ALTER TABLE `passkeys`
  ADD CONSTRAINT `passkeys_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `administradores` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
