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
require_once $rootDir . '/includes/assinatura_workflow_helpers.php';

function recusaRespostaJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    ob_clean();
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    recusaRespostaJson(['success' => false, 'code' => 'method_not_allowed', 'error' => 'Método inválido.'], 405);
}
if (!assinaturaSessaoAdminAtiva($pdo)) {
    recusaRespostaJson(['success' => false, 'code' => 'session_expired',
        'error' => 'Sua sessão realmente expirou. Entre novamente para continuar.'], 401);
}

try {
    validarCsrfAssinatura($_POST['csrf_token'] ?? null);
} catch (Throwable $e) {
    $erro = respostaErroAssinatura($e, '[recusar_assinatura] CSRF');
    recusaRespostaJson($erro['payload'], $erro['status']);
}

$documentoId    = trim($_POST['documento_id'] ?? '');
$requerimentoId = (int) ($_POST['requerimento_id'] ?? 0);
$motivo         = trim($_POST['motivo'] ?? '');
$adminId        = (int) ($_SESSION['admin_id'] ?? 0);

if (!$documentoId || !$adminId) {
    recusaRespostaJson(['success' => false, 'code' => 'invalid_request', 'error' => 'Documento não informado.'], 422);
}
if (mb_strlen($motivo) < 5) {
    recusaRespostaJson(['success' => false, 'code' => 'invalid_reason',
        'error' => 'Informe o motivo da recusa (mínimo 5 caracteres).'], 422);
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
        recusaRespostaJson(['success' => false, 'code' => 'signature_request_missing',
            'error' => 'Não há solicitação de assinatura pendente para você neste documento.'], 403);
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

    recusaRespostaJson(['success' => true]);

} catch (Throwable $e) {
    $erro = respostaErroAssinatura($e, '[recusar_assinatura]');
    recusaRespostaJson($erro['payload'], $erro['status']);
}
