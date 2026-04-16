<?php

/**
 * Bootstrap para os testes PHPUnit.
 * Define constantes e stubs necessários para testar funções
 * sem precisar de banco de dados ou servidor web.
 */

define('MAX_FILE_SIZE', 100 * 1024 * 1024);
define('UPLOAD_DIR', sys_get_temp_dir() . '/sema_tests/');
define('BASE_URL', 'http://localhost:8090');
define('DB_HOST', 'localhost');
define('DB_USER', 'test');
define('DB_PASS', 'test');
define('DB_NAME', 'test');
define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_USERNAME', 'test@test.com');
define('SMTP_PASSWORD', 'test');
define('EMAIL_FROM', 'test@test.com');
define('EMAIL_FROM_NAME', 'SEMA Test');
define('RECAPTCHA_SITE_KEY', 'test');
define('RECAPTCHA_SECRET_KEY', 'test');
define('MODO_TESTE', true);
define('EMAIL_TEST_MODE', true);

// Garante que a sessão esteja disponível nos testes
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

// Carrega o stub do Database antes de qualquer include que o requeira
require_once __DIR__ . '/stubs/DatabaseStub.php';

// Inclui as funções puras diretamente (sem os requires de config/database)
require_once __DIR__ . '/helpers/pure_functions.php';
