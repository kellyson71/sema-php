<?php
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../../includes/admin_release_reads.php';

header('Content-Type: application/json');

if (empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado.']);
    exit;
}

$version = trim($_POST['version'] ?? '');
if ($version === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Versão inválida.']);
    exit;
}

markAdminReleaseVersionAsSeen($pdo, (int) $_SESSION['admin_id'], $version);

echo json_encode(['success' => true]);
