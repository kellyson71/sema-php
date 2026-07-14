<?php

/**
 * Servidor de arquivos de uploads/.
 *
 * A pasta uploads/ é fechada por .htaccess — nenhum arquivo lá é acessível por URL
 * direta. Todo download passa por aqui, que exige uma de duas autorizações:
 *
 *   1. sessão de admin válida  → acesso a qualquer arquivo de uploads/;
 *   2. token de acesso público → só aos arquivos vinculados àquele token
 *      (documentos do lote de entrega, ou o boleto do próprio requerimento).
 *
 * Antes disso, os PDFs enviados pelos cidadãos (RG, CPF, contratos, projetos)
 * ficavam legíveis para qualquer um que descobrisse o caminho.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

function negar(int $status, string $mensagem): void
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $mensagem;
    exit;
}

$caminhoPedido = (string) ($_GET['path'] ?? '');
$token         = trim((string) ($_GET['token'] ?? ''));

// ---------- Download do lote inteiro em .zip ----------
// Autorizado pelo próprio token: entrega exatamente os arquivos daquele lote.
if (isset($_GET['zip'])) {
    if ($token === '' || !class_exists('ZipArchive')) {
        negar(400, 'Download em lote indisponível.');
    }

    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $lote = buscarLoteEntregaValido($pdo, $token);
    } catch (Throwable $e) {
        error_log('[arquivo] Falha ao montar zip: ' . $e->getMessage());
        $lote = [];
    }

    if (empty($lote)) {
        negar(403, 'Link inválido, revogado ou expirado.');
    }

    $protocolo = '';
    try {
        $stmt = $pdo->prepare('SELECT protocolo FROM requerimentos WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $lote[0]['requerimento_id']]);
        $protocolo = (string) $stmt->fetchColumn();
    } catch (Throwable $e) {
        // nome do zip é cosmético; segue com o padrão
    }

    $zipTemp = tempnam(sys_get_temp_dir(), 'sema_zip_');
    $zip = new ZipArchive();
    if ($zip->open($zipTemp, ZipArchive::OVERWRITE) !== true) {
        @unlink($zipTemp);
        negar(500, 'Não foi possível montar o arquivo .zip.');
    }

    foreach ($lote as $doc) {
        $fisico = realpath(rtrim(UPLOAD_DIR, '/\\') . '/' . ltrim($doc['caminho_arquivo'], '/'));
        if ($fisico !== false && is_file($fisico)) {
            $zip->addFile($fisico, basename($doc['nome_arquivo']));
        }
    }
    $zip->close();

    $nomeZip = 'documentos_' . ($protocolo !== '' ? $protocolo : 'sema') . '.zip';
    header('Content-Type: application/zip');
    header('Content-Length: ' . filesize($zipTemp));
    header('Content-Disposition: attachment; filename="' . $nomeZip . '"');
    header('X-Content-Type-Options: nosniff');
    readfile($zipTemp);
    @unlink($zipTemp);
    exit;
}

if ($caminhoPedido === '') {
    negar(400, 'Arquivo não informado.');
}

// Normalização: resolve o caminho e confirma que ele cai dentro de uploads/.
// Barra o "../" tanto antes (string) quanto depois (realpath) da resolução.
$caminhoRelativo = ltrim(str_replace('\\', '/', $caminhoPedido), '/');
if (str_contains($caminhoRelativo, '..') || str_contains($caminhoRelativo, "\0")) {
    negar(400, 'Caminho inválido.');
}

$raizUploads = realpath(rtrim(UPLOAD_DIR, '/\\'));
$caminhoReal = realpath($raizUploads . '/' . $caminhoRelativo);

if ($raizUploads === false || $caminhoReal === false || !is_file($caminhoReal)) {
    negar(404, 'Arquivo não encontrado.');
}
if (!str_starts_with($caminhoReal, $raizUploads . DIRECTORY_SEPARATOR)) {
    negar(403, 'Acesso negado.');
}

// ---------- Autorização ----------

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$autorizado = !empty($_SESSION['admin_id']);

if (!$autorizado && $token !== '') {
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );

        // Token de entrega de documentos: libera só os arquivos daquele lote.
        foreach (buscarLoteEntregaValido($pdo, $token) as $doc) {
            $docReal = realpath($raizUploads . '/' . ltrim($doc['caminho_arquivo'], '/'));
            if ($docReal !== false && $docReal === $caminhoReal) {
                $autorizado = true;
                break;
            }
        }

        // Token de pagamento: libera só os documentos do próprio requerimento.
        if (!$autorizado) {
            $requerimentoId = requerimentoIdDoToken($token);
            if ($requerimentoId > 0) {
                $stmt = $pdo->prepare('SELECT protocolo FROM requerimentos WHERE id = ? LIMIT 1');
                $stmt->execute([$requerimentoId]);
                $protocolo = $stmt->fetchColumn();

                if ($protocolo && validarTokenPagamento($token, $requerimentoId, $protocolo)) {
                    $stmt = $pdo->prepare('SELECT caminho FROM documentos WHERE requerimento_id = ?');
                    $stmt->execute([$requerimentoId]);
                    foreach ($stmt->fetchAll() as $docReq) {
                        $docReal = realpath($raizUploads . '/' . ltrim($docReq['caminho'], '/'));
                        if ($docReal !== false && $docReal === $caminhoReal) {
                            $autorizado = true;
                            break;
                        }
                    }
                }
            }
        }
    } catch (Throwable $e) {
        error_log('[arquivo] Falha ao validar token: ' . $e->getMessage());
    }
}

if (!$autorizado) {
    negar(403, 'Acesso negado. Este arquivo exige autenticação ou um link válido.');
}

// ---------- Entrega ----------

$nome      = basename($caminhoReal);
$extensao  = strtolower(pathinfo($caminhoReal, PATHINFO_EXTENSION));
$mimePorExt = [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp',
    'mp4'  => 'video/mp4',
    'mov'  => 'video/quicktime',
];
$mime = $mimePorExt[$extensao] ?? 'application/octet-stream';

// Só tipos conhecidos são exibidos no navegador; o resto vai como download,
// para que um arquivo inesperado na pasta nunca seja renderizado inline.
$disposicao = isset($mimePorExt[$extensao]) ? 'inline' : 'attachment';
if (isset($_GET['download'])) {
    $disposicao = 'attachment';
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($caminhoReal));
header('Content-Disposition: ' . $disposicao . '; filename="' . rawurlencode($nome) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=600');
readfile($caminhoReal);
