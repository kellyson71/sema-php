<?php
// Configurações do banco de dados LOCAL (modo simulado para desenvolvimento)
define('DB_TYPE', 'file');
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sema_db');

// Outras configurações
define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB em bytes
define('BASE_URL', '/sema-php'); // URL base do projeto

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

// URLs importantes
define('PORTAL_CONSULTA_URL', 'http://localhost:8000/');

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Iniciar sessão se ainda não foi iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
