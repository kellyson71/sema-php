<?php
// Configurações do banco de dados para Docker
// define('DB_TYPE', 'mysql');
// define('DB_HOST', 'db');
// define('DB_USER', 'root');
// define('DB_PASS', 'root');
// define('DB_NAME', 'u492577848_SEMA');

define('DB_TYPE', 'mysql');
define('DB_HOST', 'srv1844.hstgr.io');
define('DB_USER', 'u492577848_SEMA');
define('DB_PASS', 'Pmpfestagio2021');
define('DB_NAME', 'u492577848_SEMA');

// Outras configurações
define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB em bytes
define('BASE_URL', 'http://localhost:8080/sema-php'); // URL base do projeto

// Configurações de Email (modo teste)
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_PORT', 465);
define('SMTP_SECURE', 'ssl');
define('SMTP_USERNAME', 'naoresponder@protocolosead.com');
define('SMTP_PASSWORD', 'Kellys0n_123');
define('EMAIL_FROM', 'naoresponder@protocolosead.com');
define('EMAIL_FROM_NAME', 'Prefeitura de Pau dos Ferros');

// Modo de teste para emails (true = não envia, apenas loga)
define('EMAIL_TEST_MODE', true);

// Modo de teste para formulário (true = habilita botão de preenchimento automático)
define('MODO_TESTE', true);

// URLs importantes
define('PORTAL_CONSULTA_URL', 'http://localhost:8080/');

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Iniciar sessão se ainda não foi iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
