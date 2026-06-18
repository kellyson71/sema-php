<?php
/**
 * Pré-visualização REAL do PDF — renderiza com o mesmo TCPDF, mesmas margens,
 * mesmo carimbo e mesma posição de assinatura do documento final, mas inline
 * no navegador e sem gravar nada no banco ou no disco.
 *
 * É a garantia de "o que está no preview é o que sai no final": o preview
 * É o final, apenas sem registro.
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
ob_start();

$rootDir = dirname(__DIR__, 2);
require_once $rootDir . '/includes/config.php';
require_once dirname(__DIR__) . '/conexao.php';
require_once $rootDir . '/includes/pdf_sanitizer.php';

if (function_exists('verificaLogin')) {
    verificaLogin();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

$conteudo = sanitizarHtmlParaPdf(trim($_POST['conteudo_parecer'] ?? ''));
$requerimento_id = trim($_POST['requerimento_id'] ?? '');
$modoAssinatura = $_POST['modo_assinatura'] ?? 'assinar';

if (empty($conteudo)) {
    die('O conteúdo do documento está vazio.');
}

$admin_id = $_SESSION['admin_id'] ?? null;
if (!$admin_id) die('Sessão expirada.');

$stmt = $pdo->prepare("SELECT nome, nome_completo, cargo, cpf FROM administradores WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$assinante = [
    'nome'      => ($admin['nome_completo'] ?? '') ?: (($admin['nome'] ?? '') ?: ($_SESSION['admin_nome'] ?? 'ASSINANTE')),
    'cargo'     => ($admin['cargo'] ?? '') ?: 'Administrador(a)',
    'cpf'       => $admin['cpf'] ?? '',
    'data_hora' => date('d/m/Y \à\s H:i:s'),
];

$sigPosX = isset($_POST['sig_pos_x']) && $_POST['sig_pos_x'] !== '' ? (float) $_POST['sig_pos_x'] : null;
$sigPosY = isset($_POST['sig_pos_y']) && $_POST['sig_pos_y'] !== '' ? (float) $_POST['sig_pos_y'] : null;
$sigPos  = ($sigPosX !== null && $sigPosY !== null) ? ['x' => $sigPosX, 'y' => $sigPosY] : null;

$numero_processo = $requerimento_id ? "Processo_#{$requerimento_id}" : "Documento_Avulso";

require_once __DIR__ . '/gerar_pdf.php';

// QR de demonstração: aponta para a página de verificação sem código —
// o código real só existe após a assinatura definitiva
$verifyUrlDemo = rtrim(BASE_URL, '/') . '/verificar';

$opcoes = [
    'verify_url' => ($modoAssinatura !== 'sem_assinar') ? $verifyUrlDemo : '',
    'doc_codigo' => 'PREVIEW',
    'sig_pos'    => $sigPos,
];

if (ob_get_length()) ob_clean();

if ($modoAssinatura === 'sem_assinar') {
    $assinanteManual = array_merge($assinante, ['tipo' => 'manual']);
    emitirParecerAssinado($conteudo, $assinanteManual, $numero_processo, 'I', null, $opcoes);
} else {
    emitirParecerAssinado($conteudo, $assinante, $numero_processo, 'I', null, $opcoes);
}
exit;
