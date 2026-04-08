<?php
require_once 'conexao.php';
require_once '../includes/config.php';
require_once '../vendor/autoload.php';

verificaLogin();

$denuncia_id = (int)($_GET['denuncia_id'] ?? 0);

if ($denuncia_id <= 0) {
    http_response_code(400);
    die('Parâmetros inválidos.');
}

// Recuperar HTML da sessão
if (
    empty($_SESSION['denuncia_pdf_html']) ||
    $_SESSION['denuncia_pdf_id'] != $denuncia_id ||
    (time() - ($_SESSION['denuncia_pdf_time'] ?? 0)) > 300
) {
    http_response_code(400);
    die('Sessão expirada ou inválida. Volte ao editor e tente novamente.');
}

$html_conteudo = $_SESSION['denuncia_pdf_html'];
$template      = $_SESSION['denuncia_pdf_template'] ?? 'documento';

// Buscar dados da denúncia para nome do arquivo
$stmt = $pdo->prepare("SELECT id, infrator_nome FROM denuncias WHERE id = ?");
$stmt->execute([$denuncia_id]);
$denuncia = $stmt->fetch();

if (!$denuncia) {
    http_response_code(404);
    die('Denúncia não encontrada.');
}

// Buscar admin logado
$stmtA = $pdo->prepare("SELECT nome, nome_completo, cargo, matricula_portaria FROM administradores WHERE id = ?");
$stmtA->execute([$_SESSION['admin_id']]);
$admin = $stmtA->fetch(PDO::FETCH_ASSOC);

// Registrar histórico
try {
    $labelTpl = [
        'denuncia_notificacao'       => 'Notificação Fiscal',
        'denuncia_tac'               => 'Termo de Ajustamento de Conduta',
        'denuncia_termo_compromisso' => 'Termo de Compromisso Ambiental',
    ][$template] ?? 'Documento';

    $stmtH = $pdo->prepare("
        INSERT INTO denuncia_historico (denuncia_id, admin_id, acao, detalhes)
        VALUES (?, ?, ?, ?)
    ");
    $stmtH->execute([
        $denuncia_id,
        $_SESSION['admin_id'],
        'Documento gerado',
        'Documento gerado: ' . $labelTpl,
    ]);
} catch (\Throwable $e) {
    // Histórico é opcional — não bloqueia a geração
    error_log('[gerar_pdf_denuncia] Erro ao registrar histórico: ' . $e->getMessage());
}

// Limpar sessão
unset($_SESSION['denuncia_pdf_html'], $_SESSION['denuncia_pdf_id'],
      $_SESSION['denuncia_pdf_template'], $_SESSION['denuncia_pdf_time']);

// Gerar PDF com TCPDF
require_once __DIR__ . '/assinatura/gerar_pdf.php';

$nomeAdmin = $admin['nome_completo'] ?: $admin['nome'];

$assinante = [
    'nome'      => strtoupper($nomeAdmin),
    'cargo'     => $admin['cargo'] ?: 'Fiscal de Meio Ambiente',
    'data_hora' => date('d/m/Y H:i:s'),
    'cpf'       => '',
    'matricula' => $admin['matricula_portaria'] ?? '',
];

$numeroProcesso = 'DEN-' . str_pad($denuncia['id'], 6, '0', STR_PAD_LEFT);

emitirParecerAssinado($html_conteudo, $assinante, $numeroProcesso, 'D');
