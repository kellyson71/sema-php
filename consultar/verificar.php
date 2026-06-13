<?php
/**
 * Compatibilidade: o verificador foi movido para a raiz (/verificar).
 * Documentos antigos têm links para /consultar/verificar.php?id=... — este
 * redirect preserva esses acessos.
 */
require_once __DIR__ . '/../includes/config.php';

$id = isset($_GET['id']) ? '?id=' . urlencode($_GET['id']) : '';
header('Location: ' . rtrim(BASE_URL, '/') . '/verificar' . $id, true, 301);
exit;
