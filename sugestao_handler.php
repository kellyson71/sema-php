<?php
header('Content-Type: application/json');
require_once __DIR__ . '/includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método inválido.']);
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    echo json_encode(['success' => false, 'error' => 'Sessão expirada. Recarregue a página e tente novamente.']);
    exit;
}

if (!empty($_POST['site_empresa'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Não foi possível validar o envio.']);
    exit;
}

$agora = time();
$ultimoEnvio = (int) ($_SESSION['ultima_sugestao_em'] ?? 0);
if ($ultimoEnvio && $agora - $ultimoEnvio < 15) {
    echo json_encode(['success' => false, 'error' => 'Aguarde alguns segundos antes de enviar outro feedback.']);
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

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $pdo->prepare("
        INSERT INTO sugestoes (tipo, texto, nome, email, pagina, ip_origem)
        VALUES (?, ?, ?, ?, ?, ?)
    ")->execute([
        $tipo,
        mb_substr($texto, 0, 2000),
        $nome  !== '' ? mb_substr($nome,  0, 120) : null,
        $email !== '' ? mb_substr($email, 0, 120) : null,
        mb_substr($_SERVER['HTTP_REFERER'] ?? '', 0, 255),
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);

    echo json_encode(['success' => true]);
    $_SESSION['ultima_sugestao_em'] = $agora;

} catch (Throwable $e) {
    error_log('[sugestao_handler] ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro interno. Tente novamente.']);
}
