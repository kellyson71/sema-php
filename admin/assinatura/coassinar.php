<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ob_start();

$rootDir = dirname(__DIR__, 2);
require_once $rootDir . '/includes/config.php';
require_once dirname(__DIR__) . '/conexao.php';

if (function_exists('verificaLogin')) {
    verificaLogin();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Método inválido.']);
    exit;
}

$documentoId    = trim($_POST['documento_id']    ?? '');
$requerimentoId = (int) ($_POST['requerimento_id'] ?? 0);
$adminId        = $_SESSION['admin_id'] ?? null;

if (!$documentoId || !$requerimentoId || !$adminId) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Dados insuficientes ou sessão expirada.']);
    exit;
}

try {
    // 1. Buscar fonte do documento (HTML + caminho do PDF)
    $stmtFonte = $pdo->prepare("SELECT * FROM documentos_fonte WHERE documento_id = ?");
    $stmtFonte->execute([$documentoId]);
    $fonte = $stmtFonte->fetch(PDO::FETCH_ASSOC);

    if (!$fonte) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Este documento foi criado antes da atualização de co-assinatura e não suporta múltiplas assinaturas.']);
        exit;
    }

    // 2. Verificar se o admin já assinou este documento
    $stmtCheck = $pdo->prepare("SELECT id FROM assinaturas_digitais WHERE documento_id = ? AND assinante_id = ?");
    $stmtCheck->execute([$documentoId, $adminId]);
    if ($stmtCheck->fetch()) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Você já assinou este documento.']);
        exit;
    }

    // 3. Buscar dados do admin atual
    $stmtAdmin = $pdo->prepare("SELECT nome, nome_completo, cargo, cpf, matricula_portaria FROM administradores WHERE id = ?");
    $stmtAdmin->execute([$adminId]);
    $admin = $stmtAdmin->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Administrador não encontrado.']);
        exit;
    }

    // 4. Buscar todos os assinantes existentes (ordem cronológica)
    $stmtSigs = $pdo->prepare("
        SELECT assinante_nome, assinante_cargo, assinante_cpf, matricula_portaria, timestamp_assinatura, tipo_assinatura
        FROM assinaturas_digitais
        LEFT JOIN administradores ON administradores.id = assinaturas_digitais.assinante_id
        WHERE assinaturas_digitais.documento_id = ?
        ORDER BY timestamp_assinatura ASC
    ");
    $stmtSigs->execute([$documentoId]);
    $signatariosExistentes = $stmtSigs->fetchAll(PDO::FETCH_ASSOC);

    // 5. Montar array de assinantes acumulados
    $assinantes = [];
    foreach ($signatariosExistentes as $sig) {
        if ($sig['tipo_assinatura'] === 'sem_assinatura') {
            continue;
        }
        $assinantes[] = [
            'nome'      => $sig['assinante_nome'],
            'cargo'     => $sig['assinante_cargo'] ?? '',
            'cpf'       => $sig['assinante_cpf'] ?? '',
            'matricula' => $sig['matricula_portaria'] ?? '',
            'data_hora' => date('d/m/Y \à\s H:i:s', strtotime($sig['timestamp_assinatura'])),
        ];
    }
    // Adicionar assinante atual
    $assinantes[] = [
        'nome'      => $admin['nome_completo'] ?: $admin['nome'],
        'cargo'     => $admin['cargo'] ?? '',
        'cpf'       => $admin['cpf'] ?? '',
        'matricula' => $admin['matricula_portaria'] ?? '',
        'data_hora' => date('d/m/Y \à\s H:i:s'),
    ];

    // 6. Resolver caminho físico do PDF
    $caminhoRelativo = $fonte['caminho_arquivo'];
    $adminDir = dirname(__DIR__); // admin/

    if (file_exists($adminDir . '/' . ltrim($caminhoRelativo, '/'))) {
        $caminhoFisico = $adminDir . '/' . ltrim($caminhoRelativo, '/');
    } elseif (file_exists($rootDir . '/' . ltrim($caminhoRelativo, '/'))) {
        $caminhoFisico = $rootDir . '/' . ltrim($caminhoRelativo, '/');
    } else {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Arquivo PDF original não encontrado no servidor.']);
        exit;
    }

    // 7. Regravar o PDF com assinantes acumulados (sobrescreve in-place)
    require_once __DIR__ . '/gerar_pdf.php';

    $numero_processo = "Processo_#{$requerimentoId}";
    emitirParecerAssinado($fonte['conteudo_html'], $assinantes, $numero_processo, 'F', $caminhoFisico);

    if (!file_exists($caminhoFisico)) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Falha ao regravar o PDF com a nova assinatura.']);
        exit;
    }

    $novoHash = hash_file('sha256', $caminhoFisico);

    // 8. Inserir nova linha em assinaturas_digitais (mesmo documento_id, novo assinante)
    $nomeArquivo = basename($caminhoFisico);
    $pdo->prepare("
        INSERT INTO assinaturas_digitais
            (documento_id, requerimento_id, tipo_documento, nome_arquivo, caminho_arquivo,
             hash_documento, assinante_id, assinante_nome, assinante_cpf, assinante_cargo,
             tipo_assinatura, assinatura_visual, assinatura_criptografada,
             timestamp_assinatura, ip_assinante)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'digital_sema', '{}', ?, NOW(), ?)
    ")->execute([
        $documentoId,
        $requerimentoId,
        $fonte['tipo_documento'],
        $nomeArquivo,
        $caminhoRelativo,
        $novoHash,
        $adminId,
        $admin['nome_completo'] ?: $admin['nome'],
        $admin['cpf'] ?? '',
        $admin['cargo'] ?? '',
        hash('sha256', $documentoId . time() . $adminId),
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);

    // 9. Marcar solicitações pendentes como assinadas
    try {
        $pdo->prepare("
            UPDATE solicitacoes_assinatura
            SET status = 'assinado', resolvido_em = NOW()
            WHERE documento_id = ? AND destinatario_id = ? AND status = 'pendente'
        ")->execute([$documentoId, $adminId]);
    } catch (Throwable $e) {
        // não bloquear se tabela não existir ou query falhar
    }

    // 10. Registrar no histórico
    $nomeAdmin = $admin['nome_completo'] ?: $admin['nome'];
    $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao) VALUES (?, ?, ?)")
        ->execute([$adminId, $requerimentoId, "Co-assinou digitalmente o documento: " . strtoupper($fonte['tipo_documento'] ?? 'DOCUMENTO')]);

    ob_clean();
    echo json_encode(['success' => true, 'hash' => $novoHash]);
    exit;

} catch (Throwable $e) {
    error_log('[coassinar] Erro: ' . $e->getMessage());
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
    exit;
}
