MySQL:3306/information_schema/COLUMNS/		http://localhost/phpmyadmin/index.php?route=/database/sql&db=sema_db

   Mostrando registros 0 - 113 (114 no total, Consulta levou 0.0065 segundos.) [TABLE_NAME: ADMINISTRADORES... - USERS...]


SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_KEY,
    COLUMN_DEFAULT,
    EXTRA
FROM 
    information_schema.COLUMNS
WHERE 
    TABLE_SCHEMA = 'sema_db'
ORDER BY 
    TABLE_NAME, ORDINAL_POSITION;


TABLE_NAME   	COLUMN_NAME	COLUMN_TYPE	IS_NULLABLE	COLUMN_KEY	COLUMN_DEFAULT	EXTRA	
administradores	id	int	NO	PRI	NULL	auto_increment	
administradores	nome	varchar(255)	NO		NULL		
administradores	email	varchar(191)	NO	UNI	NULL		
administradores	senha	varchar(191)	NO		NULL		
administradores	foto_perfil	varchar(255)	YES		NULL		
administradores	nivel	enum('admin','operador')	YES		operador		
administradores	ativo	tinyint(1)	YES		1		
administradores	ultimo_acesso	timestamp	YES		NULL		
administradores	data_cadastro	timestamp	YES		CURRENT_TIMESTAMP	DEFAULT_GENERATED	
configuracoes	id	int	NO	PRI	NULL	auto_increment	
configuracoes	chave	varchar(50)	NO	UNI	NULL		
configuracoes	nome	varchar(100)	NO		NULL		
configuracoes	valor	text	YES		NULL		
configuracoes	tipo	enum('texto','numero','booleano','select','textare...	YES		texto		
configuracoes	categoria	varchar(50)	NO		Geral		
configuracoes	descricao	text	YES		NULL		
configuracoes	opcoes	text	YES		NULL		
configuracoes	data_criacao	timestamp	YES		CURRENT_TIMESTAMP	DEFAULT_GENERATED	
configuracoes	data_atualizacao	timestamp	YES		CURRENT_TIMESTAMP	DEFAULT_GENERATED on update CURRENT_TIMESTAMP	
documentos	id	int	NO	PRI	NULL	auto_increment	
documentos	requerimento_id	int	NO	MUL	NULL		
documentos	campo_formulario	varchar(50)	NO		NULL		
documentos	nome_original	varchar(255)	NO		NULL		
documentos	nome_salvo	varchar(255)	NO		NULL		
documentos	caminho	varchar(255)	NO		NULL		
documentos	tipo_arquivo	varchar(100)	YES		NULL		
documentos	tamanho	int	NO		NULL		
documentos	data_upload	timestamp	YES		CURRENT_TIMESTAMP	DEFAULT_GENERATED	
documentos_admin	id	int	NO	PRI	NULL	auto_increment	
documentos_admin	nome	varchar(255)	NO		NULL		
documentos_admin	descricao	text	YES		NULL		
documentos_admin	obrigatorio	tinyint(1)	YES		0		
documentos_admin	tipos_alvara	text	YES		NULL		
documentos_admin	data_criacao	timestamp	YES		CURRENT_TIMESTAMP	DEFAULT_GENERATED	
documentos_admin	data_atualizacao	timestamp	YES		CURRENT_TIMESTAMP	DEFAULT_GENERATED on update CURRENT_TIMESTAMP	
failed_jobs	id	bigint unsigned	NO	PRI	NULL	auto_increment	
failed_jobs	uuid	varchar(191)	NO	UNI	NULL		
failed_jobs	connection	text	NO		NULL		
failed_jobs	queue	text	NO		NULL		
failed_jobs	payload	longtext	NO		NULL		
failed_jobs	exception	longtext	NO		NULL		
failed_jobs	failed_at	timestamp	NO		CURRENT_TIMESTAMP	DEFAULT_GENERATED	
historico_acoes	id	int	NO	PRI	NULL	auto_increment	
historico_acoes	admin_id	int	YES	MUL	NULL		
historico_acoes	requerimento_id	int	YES	MUL	NULL		
historico_acoes	acao	text	NO		NULL		
historico_acoes	data_acao	timestamp	YES		CURRENT_TIMESTAMP	DEFAULT_GENERATED	
migrations	id	int unsigned	NO	PRI	NULL	auto_increment	
migrations	migration	varchar(191)	NO		NULL		
migrations	batch	int	NO		NULL		
password_reset_tokens	email	varchar(191)	NO	PRI	NULL		
password_reset_tokens	token	varchar(191)	NO		NULL		
password_reset_tokens	created_at	timestamp	YES		NULL		
personal_access_tokens	id	bigint unsigned	NO	PRI	NULL	auto_increment	
personal_access_tokens	tokenable_type	varchar(191)	NO	MUL	NULL		
personal_access_tokens	tokenable_id	bigint unsigned	NO		NULL		
personal_access_tokens	name	varchar(191)	NO		NULL		
personal_access_tokens	token	varchar(64)	NO	UNI	NULL		
personal_access_tokens	abilities	text	YES		NULL		
personal_access_tokens	last_used_at	timestamp	YES		NULL		
personal_access_tokens	expires_at	timestamp	YES		NULL		
personal_access_tokens	created_at	timestamp	YES		NULL		
personal_access_tokens	updated_at	timestamp	YES		NULL		
processos	id	bigint unsigned	NO	PRI	NULL	auto_increment	
processos	requerente_id	bigint unsigned	NO	MUL	NULL		
processos	proprietario_id	bigint unsigned	NO	MUL	NULL		
processos	endereco_objetivo	varchar(191)	NO		NULL		
processos	tipo_alvara	varchar(191)	NO		NULL		
processos	documentos	json	YES		NULL		
processos	status	varchar(191)	NO		em_analise		
processos	declaracao_veracidade	tinyint(1)	NO		0		
processos	created_at	timestamp	YES		NULL		
processos	updated_at	timestamp	YES		NULL		
proprietarios	id	bigint unsigned	NO	PRI	NULL	auto_increment	
proprietarios	nome	varchar(191)	NO		NULL		
proprietarios	cpf_cnpj	varchar(191)	NO	UNI	NULL		
proprietarios	mesmo_requerente	tinyint(1)	NO		0		
proprietarios	requerente_id	int	YES	MUL	NULL		
proprietarios	created_at	timestamp	YES		NULL		
proprietarios	updated_at	timestamp	YES		NULL		
requerentes	id	bigint unsigned	NO	PRI	NULL	auto_increment	
requerentes	nome	varchar(191)	NO		NULL		
requerentes	cpf_cnpj	varchar(191)	NO	UNI	NULL		
requerentes	telefone	varchar(191)	NO		NULL		
requerentes	email	varchar(191)	NO		NULL		
requerentes	created_at	timestamp	YES		NULL		
requerentes	updated_at	timestamp	YES		NULL		
requerimentos	id	int	NO	PRI	NULL	auto_increment	
requerimentos	protocolo	varchar(20)	NO	UNI	NULL		
requerimentos	tipo_alvara	varchar(50)	NO		NULL		
requerimentos	requerente_id	int	NO	MUL	NULL		
requerimentos	proprietario_id	int	YES	MUL	NULL		
requerimentos	endereco_objetivo	text	NO		NULL		
requerimentos	status	enum('Em análise','Aprovado','Reprovado','Pendente...	YES		Em análise		
requerimentos	observacoes	text	YES		NULL		
requerimentos	data_envio	timestamp	YES		CURRENT_TIMESTAMP	DEFAULT_GENERATED	
requerimentos	data_atualizacao	timestamp	YES		CURRENT_TIMESTAMP	DEFAULT_GENERATED on update CURRENT_TIMESTAMP	
tipos_alvara	id	int	NO	PRI	NULL	auto_increment	
tipos_alvara	nome	varchar(255)	NO		NULL		
tipos_alvara	descricao	text	YES		NULL		

tipos_alvara	documentos_necessarios	text	YES		NULL		
tipos_alvara	prazo_analise	int	YES		30		
tipos_alvara	valor_taxa	decimal(10,2)	YES		0.00		
tipos_alvara	ativo	tinyint(1)	YES		1		
tipos_alvara	data_criacao	timestamp	YES		CURRENT_TIMESTAMP	DEFAULT_GENERATED	
tipos_alvara	data_atualizacao	timestamp	YES		CURRENT_TIMESTAMP	DEFAULT_GENERATED on update CURRENT_TIMESTAMP	
users	id	bigint unsigned	NO	PRI	NULL	auto_increment	
users	name	varchar(191)	NO		NULL		
users	email	varchar(191)	NO	UNI	NULL		
users	email_verified_at	timestamp	YES		NULL		
users	password	varchar(191)	NO		NULL		
users	remember_token	varchar(100)	YES		NULL		
users	created_at	timestamp	YES		NULL		
users	updated_at	timestamp	YES		NULL		
    