-- Módulo de Fiscalização de Obras: notificações, autos de infração e embargos
-- lavrados pelo fiscal de obras (nivel = 'fiscal'), com número oficial próprio
-- por tipo de documento, controle de prazo em dias úteis e assinatura digital.
--
-- Diferente de `denuncias` (relato informal de qualquer pessoa), este é um ato
-- oficial do fiscal, por isso fica em tabela própria — mas uma denúncia do
-- setor 'obras_urbanismo' pode ser convertida numa notificação (denuncia_origem_id).

CREATE TABLE IF NOT EXISTS `notificacoes_obras` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo_documento` enum('notificacao_fiscal_obras','notificacao_descarte_material','embargo','interdicao','outro') NOT NULL,
  `numero` int(10) unsigned NOT NULL,
  `ano` smallint(5) unsigned NOT NULL,
  `origem` enum('gerado_sistema','upload_pdf') NOT NULL DEFAULT 'gerado_sistema',
  `notificado_nome` varchar(255) NOT NULL,
  `notificado_cpf_cnpj` varchar(20) DEFAULT NULL,
  `proprietario_nome` varchar(255) DEFAULT NULL,
  `endereco` varchar(255) DEFAULT NULL,
  `bairro` varchar(150) DEFAULT NULL,
  `numero_imovel` varchar(20) DEFAULT NULL,
  `descricao_fato` text NOT NULL,
  `artigos_selecionados` text DEFAULT NULL COMMENT 'JSON com os artigos do checklist marcados',
  `prazo_dias` int(10) unsigned DEFAULT NULL,
  `data_emissao` date NOT NULL,
  `data_vencimento` date DEFAULT NULL COMMENT 'emissao + prazo_dias em dias uteis',
  `status` enum('pendente','notificado','protocolado','autuado','alvara_emitido','multado','interditado','multa_paga','encaminhado_outra_secretaria','finalizado','outro') NOT NULL DEFAULT 'pendente',
  `observacoes` text DEFAULT NULL,
  `denuncia_origem_id` int(11) DEFAULT NULL,
  `documento_id` varchar(64) DEFAULT NULL COMMENT 'assinaturas_digitais.documento_id quando assinado',
  `pdf_upload_path` varchar(500) DEFAULT NULL COMMENT 'quando origem = upload_pdf',
  `caminho_pdf_gerado` varchar(500) DEFAULT NULL COMMENT 'PDF gerado pelo sistema (origem = gerado_sistema), assinado ou nao',
  `fiscal_id` int(11) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_notificacao_numero` (`tipo_documento`, `ano`, `numero`),
  KEY `idx_notificacao_denuncia_origem` (`denuncia_origem_id`),
  KEY `idx_notificacao_fiscal` (`fiscal_id`),
  KEY `idx_notificacao_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notificacoes_obras_anexos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `notificacao_id` int(11) NOT NULL,
  `nome_arquivo` varchar(255) NOT NULL,
  `caminho_arquivo` varchar(255) NOT NULL,
  `tipo_arquivo` varchar(50) NOT NULL,
  `data_upload` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notificacao_obras_anexos_notificacao` (`notificacao_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Assinatura digital de notificações de obras não tem requerimento por trás,
-- então requerimento_id precisa deixar de ser obrigatório e ganhar um par
-- alternativo (notificacao_obras_id). Exatamente um dos dois deve estar
-- preenchido — validado em código, não em CHECK, para manter compatibilidade
-- ampla de versão do MySQL/MariaDB.
ALTER TABLE `assinaturas_digitais`
    MODIFY `requerimento_id` int(11) DEFAULT NULL;

ALTER TABLE `assinaturas_digitais`
    ADD COLUMN IF NOT EXISTS `notificacao_obras_id` int(11) DEFAULT NULL AFTER `requerimento_id`;

ALTER TABLE `assinaturas_digitais`
    ADD INDEX IF NOT EXISTS `idx_assinatura_notificacao_obras` (`notificacao_obras_id`);
