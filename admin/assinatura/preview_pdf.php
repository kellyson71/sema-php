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
require_once $rootDir . '/includes/assinatura_workflow_helpers.php';

if (function_exists('verificaLogin')) {
    verificaLogin();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

try {
    validarCsrfAssinatura($_POST['csrf_token'] ?? null);
} catch (AssinaturaWorkflowException $e) {
    http_response_code($e->httpStatus());
    die(htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

$conteudo = sanitizarHtmlParaPdf(trim($_POST['conteudo_parecer'] ?? ''));
$requerimento_id = trim($_POST['requerimento_id'] ?? '');
$modoAssinatura = $_POST['modo_assinatura'] ?? 'assinar';
$tipoAssinanteManual = trim((string) ($_POST['assinatura_manual_tipo'] ?? 'secretario'));
$nomeAssinanteManual = (string) ($_POST['assinatura_manual_nome'] ?? '');
$cargoAssinanteManual = (string) ($_POST['assinatura_manual_cargo'] ?? '');

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

$numero_processo = $requerimento_id ? "Processo_#{$requerimento_id}" : "Documento_Avulso";

require_once __DIR__ . '/gerar_pdf.php';

// QR de demonstração: aponta para a página de verificação sem código —
// o código real só existe após a assinatura definitiva
$verifyUrlDemo = rtrim(BASE_URL, '/') . '/verificar';

$opcoes = [
    'verify_url' => ($modoAssinatura !== 'sem_assinar') ? $verifyUrlDemo : '',
    'doc_codigo' => 'PREVIEW',
];

if (ob_get_length()) ob_clean();

if ($modoAssinatura === 'sem_assinar') {
    try {
        $secretarioManual = $tipoAssinanteManual === 'secretario'
            ? buscarSecretarioAtivoUnico($pdo)
            : null;
        $assinanteManual = resolverAssinanteManual(
            $tipoAssinanteManual,
            array_merge(['id' => (int) $admin_id], $admin),
            $secretarioManual,
            $nomeAssinanteManual,
            $cargoAssinanteManual
        );
        $assinanteManual['tipo'] = 'manual';
    } catch (AssinaturaWorkflowException $e) {
        http_response_code($e->httpStatus());
        die(htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
    }
    emitirParecerAssinado($conteudo, $assinanteManual, $numero_processo, 'I', null, $opcoes);
} else {
    emitirParecerAssinado($conteudo, $assinante, $numero_processo, 'I', null, $opcoes);
}
exit;
