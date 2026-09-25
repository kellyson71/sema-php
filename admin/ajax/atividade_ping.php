<?php
/**
 * Recebe do navegador os segundos de uso ATIVO (aba visível + mouse/teclado no último
 * minuto) acumulados desde o último envio. Chamado pelo script de atividade do
 * header.php a cada minuto e no pagehide (via sendBeacon).
 */
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../../includes/atividade_admin.php';

header('Content-Type: application/json; charset=utf-8');

$adminId = (int) ($_SESSION['admin_id'] ?? 0);
if ($adminId <= 0 || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessão expirada.', 'data' => null]);
    exit;
}

$corpo = json_decode((string) file_get_contents('php://input'), true);
$segundos = (int) (is_array($corpo) ? ($corpo['segundos'] ?? 0) : ($_POST['segundos'] ?? 0));

try {
    $registrados = atividadeRegistrarTempo($pdo, $adminId, $segundos);
    if (random_int(1, 200) === 1) {
        atividadeLimparAntigos($pdo);
    }
    echo json_encode(['success' => true, 'message' => '', 'data' => ['segundos' => $registrados]]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Atividade não registrada.', 'data' => null]);
}
