<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ob_start();

$rootDir = dirname(__DIR__, 2);
require_once $rootDir . '/includes/config.php';
require_once dirname(__DIR__) . '/conexao.php';
require_once $rootDir . '/includes/parecer_service.php';

if (function_exists('verificaLogin')) {
    verificaLogin();
}

function sanitizarHtmlParaPdf(string $html): string {
    $html = ParecerService::stripVarSpans($html);
    $html = preg_replace('/\bposition\s*:\s*(absolute|fixed|relative|sticky)\b\s*[;]?/i', '', $html);
    $html = preg_replace('/\b(z-index|overflow(-[xy])?)\s*:\s*[^;\"]+[;]?/i', '', $html);
    $html = preg_replace_callback(
        '/<span(\s[^>]*)>/i',
        function ($m) {
            $attrs = preg_replace('/\bbackground(-color)?\s*:\s*[^;\"]+[;]?/i', '', $m[1]);
            $attrs = preg_replace('/\bcolor\s*:\s*(#[0-9a-fA-F]{3,8}|rgb[a]?\([^)]+\)|[a-z]+)\s*[;]?/i', '', $attrs);
            $attrs = preg_replace('/\bstyle\s*=\s*["\'][\s;]*["\']/', '', $attrs);
            return '<span' . $attrs . '>';
        },
        $html
    );
    $html = preg_replace('/\bdisplay\s*:\s*none\b\s*[;]?/i', '', $html);
    return $html;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php");
    exit;
}

header('Content-Type: application/json');

$conteudo    = sanitizarHtmlParaPdf(trim($_POST['conteudo_parecer'] ?? ''));
$denuncia_id = (int)trim($_POST['denuncia_id'] ?? 0);
$template    = $_POST['template_salvo'] ?? 'Documento';

if (empty($conteudo)) {
    ob_clean(); echo json_encode(['success' => false, 'error' => 'O conteúdo do documento não pode estar vazio.']); exit;
}
if ($denuncia_id <= 0) {
    ob_clean(); echo json_encode(['success' => false, 'error' => 'ID da denúncia inválido.']); exit;
}

$admin_id = $_SESSION['admin_id'] ?? null;
if (!$admin_id) {
    ob_clean(); echo json_encode(['success' => false, 'error' => 'Sessão expirada.']); exit;
}

try {
    // Verificar que a denúncia existe
    $stmtD = $pdo->prepare("SELECT id, infrator_nome FROM denuncias WHERE id = ?");
    $stmtD->execute([$denuncia_id]);
    $denuncia = $stmtD->fetch(PDO::FETCH_ASSOC);
    if (!$denuncia) {
        ob_clean(); echo json_encode(['success' => false, 'error' => 'Denúncia não encontrada.']); exit;
    }

    // Buscar dados do admin
    $stmtA = $pdo->prepare("SELECT nome, nome_completo, cargo, cpf, matricula_portaria FROM administradores WHERE id = ?");
    $stmtA->execute([$admin_id]);
    $admin = $stmtA->fetch(PDO::FETCH_ASSOC);
    if (!$admin) {
        ob_clean(); echo json_encode(['success' => false, 'error' => 'Administrador não encontrado.']); exit;
    }
} catch (Exception $e) {
    ob_clean(); echo json_encode(['success' => false, 'error' => 'Erro SQL: ' . $e->getMessage()]); exit;
}

$assinante = [
    'nome'      => ($admin['nome_completo'] ?: $admin['nome']),
    'cargo'     => ($admin['cargo'] ?: 'Fiscal de Meio Ambiente'),
    'cpf'       => ($admin['cpf'] ?? ''),
    'matricula' => ($admin['matricula_portaria'] ?? ''),
    'data_hora' => date('d/m/Y \à\s H:i:s'),
];

$numeroProcesso = 'DEN-' . str_pad($denuncia_id, 6, '0', STR_PAD_LEFT);

require_once __DIR__ . '/gerar_pdf.php';

try {
    $dirDestino = dirname(__DIR__) . '/pareceres_denuncia/' . $denuncia_id;
    if (!is_dir($dirDestino)) {
        mkdir($dirDestino, 0755, true);
    }

    $labelTpl = [
        'denuncia_notificacao'       => 'Notificacao_Fiscal',
        'denuncia_auto_infracao'     => 'Auto_de_Infracao',
        'denuncia_tac'               => 'TAC',
        'denuncia_termo_compromisso' => 'Termo_Compromisso_Ambiental',
        'denuncia_relatorio_vistoria'=> 'Relatorio_Vistoria',
        'denuncia_parecer_ambiental' => 'Parecer_Tecnico_Ambiental',
    ][$template] ?? 'Documento';

    $nomeArquivo    = $labelTpl . '_DEN' . str_pad($denuncia_id, 6, '0', STR_PAD_LEFT) . '_' . date('His') . '.pdf';
    $caminhoFisico  = $dirDestino . '/' . $nomeArquivo;
    $caminhoRelativo = 'pareceres_denuncia/' . $denuncia_id . '/' . $nomeArquivo;

    emitirParecerAssinado($conteudo, $assinante, $numeroProcesso, 'F', $caminhoFisico);

    if (!file_exists($caminhoFisico)) {
        ob_clean(); echo json_encode(['success' => false, 'error' => 'Falha ao gravar o arquivo PDF.']); exit;
    }

    // Registrar no histórico da denúncia
    $stmtH = $pdo->prepare("
        INSERT INTO denuncia_historico (denuncia_id, admin_id, acao, detalhes)
        VALUES (?, ?, ?, ?)
    ");
    $stmtH->execute([
        $denuncia_id,
        $admin_id,
        'Documento assinado digitalmente',
        'Documento gerado e assinado: ' . str_replace('_', ' ', $labelTpl),
    ]);

    ob_clean();
    echo json_encode([
        'success'      => true,
        'url_pdf'      => $caminhoRelativo,
        'nome_arquivo' => $nomeArquivo,
    ]);

} catch (Exception $e) {
    error_log('[processa_assinatura_denuncia] ' . $e->getMessage());
    ob_clean(); echo json_encode(['success' => false, 'error' => 'Falha ao gerar o documento: ' . $e->getMessage()]); exit;
}
