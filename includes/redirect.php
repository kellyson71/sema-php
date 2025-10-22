<?php

/**
 * Arquivo para redirecionamento de domínio
 * Redireciona sema.protocolosead.com para sematemp.protocolosead.com
 */

// Verificação de redirecionamento para o domínio principal
$host = $_SERVER['HTTP_HOST'] ?? '';
if (preg_match('/^(www\.)?sema\.protocolosead\.com$/i', $host)) {
    $redirect_url = 'http://sematemp.protocolosead.com' . $_SERVER['REQUEST_URI'];
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: $redirect_url");
    exit();
}
