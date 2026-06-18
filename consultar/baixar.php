<?php
/**
 * Download público do documento assinado pelo documento_id.
 * O documento_id (32 hex aleatórios) funciona como token de capacidade:
 * só quem tem o código — impresso no documento/QR — consegue baixar.
 * O arquivo só é servido se a verificação criptográfica passar.
 */
require_once '../includes/config.php';
require_once '../includes/assinatura_digital_service.php';
require_once '../admin/conexao.php';

$documentoId = $_GET['id'] ?? '';
if ($documentoId === '' || !preg_match('/^[a-zA-Z0-9_]{8,64}$/', $documentoId)) {
    http_response_code(404);
    exit('Documento não encontrado.');
}

$servico = new AssinaturaDigitalService($pdo);
$resultado = $servico->verificarDocumento($documentoId);

if (empty($resultado['valido']) || empty($resultado['caminho_fisico'])) {
    http_response_code(404);
    exit('Documento não disponível ou falhou na verificação de integridade.');
}

$caminho = $resultado['caminho_fisico'];
$nome = basename($caminho);

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $nome . '"');
header('Content-Length: ' . filesize($caminho));
header('Cache-Control: private, no-store');
readfile($caminho);
exit;
