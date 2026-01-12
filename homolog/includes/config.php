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

// Detectar se estamos em ambiente de homologação
$scriptPath = $_SERVER['SCRIPT_NAME'];
$isHomolog = (strpos($scriptPath, '/homolog/') !== false);
define('MODO_HOMOLOG', $isHomolog);

// Outras configurações
define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB em bytes

// URL base dinâmica
if (isset($_SERVER['HTTP_HOST'])) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    
    if ($isHomolog) {
        define('BASE_URL', $protocol . '://' . $host . '/homolog');
    } else {
        // Se estiver em localhost mas não em homolog, mantém o padrão antigo ou ajusta para a raiz
        if ($host === 'localhost' || $host === 'localhost:8080') {
             define('BASE_URL', $protocol . '://' . $host . '/sema-php');
        } else {
             define('BASE_URL', $protocol . '://' . $host);
        }
    }
} else {
    define('BASE_URL', 'http://localhost:8080/sema-php'); // Fallback para CLI
}

// Configurações de Email (modo teste)
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_PORT', 465);
define('SMTP_SECURE', 'ssl');
define('SMTP_USERNAME', 'naoresponder@protocolosead.com');
define('SMTP_PASSWORD', 'Kellys0n_123');
define('EMAIL_FROM', 'naoresponder@protocolosead.com');
define('EMAIL_FROM_NAME', 'Prefeitura de Pau dos Ferros');

// Modo de teste para emails (true = não envia, apenas loga)
define('EMAIL_TEST_MODE', false);

// Modo de teste para formulário (true = habilita botão de preenchimento automático)
define('MODO_TESTE', false);

// URLs importantes
define('PORTAL_CONSULTA_URL', 'http://localhost:8080/');

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Iniciar sessão se ainda não foi iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
