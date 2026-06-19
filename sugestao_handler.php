<?php
header('Content-Type: application/json');
require_once __DIR__ . '/includes/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método inválido.']);
    exit;
}

$tipo  = trim($_POST['tipo']  ?? 'melhoria');
$texto = trim($_POST['texto'] ?? '');
$nome  = trim($_POST['nome']  ?? '');
$email = trim($_POST['email'] ?? '');

$tiposValidos = ['melhoria', 'dificuldade', 'elogio', 'outro'];
if (!in_array($tipo, $tiposValidos, true)) $tipo = 'melhoria';

if (mb_strlen($texto) < 10) {
    echo json_encode(['success' => false, 'error' => 'Descreva sua sugestão com pelo menos 10 caracteres.']);
    exit;
}
if (mb_strlen($texto) > 2000) {
    echo json_encode(['success' => false, 'error' => 'Sugestão muito longa (máximo 2000 caracteres).']);
    exit;
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'E-mail inválido.']);
    exit;
}

$db   = Database::getInstance();
$nome  = $nome  !== '' ? mb_substr($nome,  0, 120) : null;
$email = $email !== '' ? mb_substr($email, 0, 120) : null;

$db->insert('sugestoes', [
    'tipo'      => $tipo,
    'texto'     => mb_substr($texto, 0, 2000),
    'nome'      => $nome,
    'email'     => $email,
    'pagina'    => mb_substr($_SERVER['HTTP_REFERER'] ?? '', 0, 255),
    'ip_origem' => $_SERVER['REMOTE_ADDR'] ?? null,
]);

echo json_encode(['success' => true]);
