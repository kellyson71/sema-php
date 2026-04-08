<?php

/**
 * Arquivo para redirecionamento de domínio
 * Redireciona sema.protocolosead.com para sema.paudosferros.rn.gov.br
 */

require_once __DIR__ . '/config.php';

// Verificação de redirecionamento para o domínio principal
$host = $_SERVER['HTTP_HOST'] ?? '';
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
if (!MODO_HOMOLOG && preg_match('/^(www\.)?sema\.protocolosead\.com$/i', $host)) {
    $redirect_url = 'http://sema.paudosferros.rn.gov.br' . $requestUri;
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: $redirect_url");
    exit();
}
