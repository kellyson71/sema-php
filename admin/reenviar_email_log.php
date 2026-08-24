<?php
require_once '../includes/config.php';
require_once 'conexao.php';
require_once '../includes/email_service.php';

verificaLogin();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'erro' => 'Método não permitido.']);
    exit;
}

$csrf = (string) ($_POST['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'erro' => 'Sessão expirada. Recarregue a página.']);
    exit;
}

$logId = (int) ($_POST['log_id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT el.*, req.nome AS destinatario_nome, req.email AS email_atual
    FROM email_logs el
    LEFT JOIN requerimentos r ON r.id = el.requerimento_id
    LEFT JOIN requerentes req ON req.id = r.requerente_id
    WHERE el.id = ? AND el.status = 'ERRO'
    LIMIT 1
");
$stmt->execute([$logId]);
$log = $stmt->fetch();

if (!$log) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'erro' => 'Falha de e-mail não encontrada.']);
    exit;
}

if (!emailRegistradoPodeSerReenviado((string) ($log['mensagem'] ?? ''))) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'erro' => 'Esta mensagem contém um link transacional. Abra o processo e repita a ação para gerar um link novo e válido.']);
    exit;
}

$destinatario = emailDestinoValido((string) ($log['email_atual'] ?? ''))
    ? trim((string) $log['email_atual'])
    : trim((string) $log['email_destino']);

if (!emailDestinoValido($destinatario)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'erro' => 'O destinatário registrado é inválido. Corrija o e-mail no processo antes de reenviar.']);
    exit;
}

$enviado = sendMail(
    $destinatario,
    $log['destinatario_nome'] ?? '',
    $log['assunto'],
    $log['mensagem'],
    $log['requerimento_id'] ? (int) $log['requerimento_id'] : null
);

if (!$enviado) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'erro' => 'O provedor não aceitou o reenvio. Uma nova falha foi registrada no histórico.']);
    exit;
}

echo json_encode(['ok' => true, 'mensagem' => 'Reenvio aceito pelo provedor para ' . $destinatario . '.']);
