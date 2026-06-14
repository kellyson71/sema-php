<?php
/**
 * Cancelamento de uma solicitação de co-assinatura ainda pendente.
 * Apenas o próprio solicitante pode retirar o pedido.
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
ob_start();

$rootDir = dirname(__DIR__, 2);
require_once $rootDir . '/includes/config.php';
require_once dirname(__DIR__) . '/conexao.php';

if (function_exists('verificaLogin')) {
    verificaLogin();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método inválido.']);
    exit;
}

$documentoId   = trim($_POST['documento_id'] ?? '');
$destinatarioId = (int) ($_POST['destinatario_id'] ?? 0);
$adminId       = (int) ($_SESSION['admin_id'] ?? 0);

if (!$documentoId || !$destinatarioId || !$adminId) {
    echo json_encode(['success' => false, 'error' => 'Dados insuficientes.']);
    exit;
}

try {
    // Só o solicitante cancela, e só se ainda estiver pendente
    $st = $pdo->prepare("
        UPDATE solicitacoes_assinatura
        SET status = 'cancelado', resolvido_em = NOW()
        WHERE documento_id = ? AND destinatario_id = ? AND solicitante_id = ? AND status = 'pendente'
    ");
    $st->execute([$documentoId, $destinatarioId, $adminId]);

    if ($st->rowCount() === 0) {
        echo json_encode(['success' => false, 'error' => 'Solicitação não encontrada ou já resolvida.']);
        exit;
    }

    echo json_encode(['success' => true]);
    exit;

} catch (Throwable $e) {
    error_log('[cancelar_solicitacao] ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro ao cancelar a solicitação.']);
    exit;
}
