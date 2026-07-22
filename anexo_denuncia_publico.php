<?php
/**
 * Serve um anexo de denúncia ao público — SOMENTE se marcado como visível ao
 * denunciante. A pasta uploads/ é bloqueada por .htaccess, então todo acesso
 * público a um anexo passa por aqui, onde a visibilidade é conferida no banco.
 *
 * Sem a marca visivel_denunciante = 1, o arquivo não é entregue — mesmo que
 * alguém descubra o id. Nunca expõe dados do infrator/denunciante.
 */
require_once __DIR__ . '/includes/config.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    exit('Arquivo não encontrado.');
}

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $stmt = $pdo->prepare("
        SELECT nome_arquivo, caminho_arquivo, tipo_arquivo
        FROM denuncia_anexos
        WHERE id = ? AND visivel_denunciante = 1
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $anexo = $stmt->fetch();
} catch (Throwable $e) {
    error_log('[anexo_denuncia_publico] ' . $e->getMessage());
    http_response_code(500);
    exit('Erro ao acessar o arquivo.');
}

if (!$anexo) {
    http_response_code(404);
    exit('Arquivo não disponível.');
}

// O caminho é relativo à raiz do projeto. realpath + verificação de contenção
// barram qualquer tentativa de sair da pasta do projeto (path traversal).
$fisico = realpath(__DIR__ . '/' . ltrim($anexo['caminho_arquivo'], '/'));
$raiz   = realpath(__DIR__);
if ($fisico === false || $raiz === false || strpos($fisico, $raiz) !== 0 || !is_file($fisico)) {
    http_response_code(404);
    exit('Arquivo não encontrado.');
}

$mimes = [
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
    'pdf' => 'application/pdf', 'mp4' => 'video/mp4', 'mov' => 'video/quicktime',
];
$ext  = strtolower($anexo['tipo_arquivo']);
$mime = $mimes[$ext] ?? 'application/octet-stream';

$disposicao = isset($_GET['download']) ? 'attachment' : 'inline';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($fisico));
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: ' . $disposicao . '; filename="' . basename($anexo['nome_arquivo']) . '"');
readfile($fisico);
exit;
