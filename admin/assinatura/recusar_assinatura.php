<?php
/**
 * Recusa de co-assinatura. O destinatário recusa assinar com um motivo
 * obrigatório; o solicitante é notificado.
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
ob_start();

$rootDir = dirname(__DIR__, 2);
require_once $rootDir . '/includes/config.php';
require_once dirname(__DIR__) . '/conexao.php';
require_once $rootDir . '/includes/admin_notifications.php';

if (function_exists('verificaLogin')) {
    verificaLogin();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método inválido.']);
    exit;
}

$documentoId    = trim($_POST['documento_id'] ?? '');
$requerimentoId = (int) ($_POST['requerimento_id'] ?? 0);
$motivo         = trim($_POST['motivo'] ?? '');
$adminId        = (int) ($_SESSION['admin_id'] ?? 0);

if (!$documentoId || !$adminId) {
    echo json_encode(['success' => false, 'error' => 'Dados insuficientes ou sessão expirada.']);
    exit;
}
if (mb_strlen($motivo) < 5) {
    echo json_encode(['success' => false, 'error' => 'Informe o motivo da recusa (mínimo 5 caracteres).']);
    exit;
}

try {
    // Só recusa se houver uma solicitação pendente para este admin
    $st = $pdo->prepare("
        SELECT id, solicitante_id, requerimento_id
        FROM solicitacoes_assinatura
        WHERE documento_id = ? AND destinatario_id = ? AND status = 'pendente'
        LIMIT 1
    ");
    $st->execute([$documentoId, $adminId]);
    $sol = $st->fetch(PDO::FETCH_ASSOC);

    if (!$sol) {
        echo json_encode(['success' => false, 'error' => 'Não há solicitação de assinatura pendente para você neste documento.']);
        exit;
    }
    $requerimentoId = $requerimentoId ?: (int) $sol['requerimento_id'];

    $pdo->prepare("
        UPDATE solicitacoes_assinatura
        SET status = 'recusado', motivo_recusa = ?, resolvido_em = NOW()
        WHERE id = ?
    ")->execute([$motivo, $sol['id']]);

    // Notifica o solicitante (direcionado)
    if (function_exists('createAdminNotificationForRequerimento')) {
        createAdminNotificationForRequerimento($pdo, $requerimentoId, 'coassinatura_recusada', [
            'destinatario_admin_id' => (int) $sol['solicitante_id'],
            'link_url' => 'visualizar_documento.php?requerimento_id=' . $requerimentoId,
        ]);
    }

    $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao) VALUES (?, ?, ?)")
        ->execute([$adminId, $requerimentoId, "Recusou a co-assinatura do documento $documentoId — Motivo: $motivo"]);

    echo json_encode(['success' => true]);
    exit;

} catch (Throwable $e) {
    error_log('[recusar_assinatura] ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro ao registrar a recusa. Tente novamente.']);
    exit;
}
