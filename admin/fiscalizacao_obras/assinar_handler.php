<?php
require_once '../conexao.php';
verificaLogin();
require_once __DIR__ . '/../../includes/fiscalizacao_obras_helpers.php';
require_once __DIR__ . '/../../includes/assinatura_digital_service.php';

$nivelAtual = $_SESSION['admin_nivel'] ?? 'operador';
$isAdmin = in_array($nivelAtual, ['admin', 'admin_geral'], true);
if ($nivelAtual !== 'fiscal' && !$isAdmin) {
    http_response_code(403);
    header('Location: ../index.php?error=sem_permissao');
    exit;
}

$notificacaoId = (int) ($_POST['id'] ?? 0);
$notificacao = $notificacaoId > 0 ? buscarNotificacaoObras($pdo, $notificacaoId) : null;

if (!$notificacao) {
    header('Location: index.php?error=nao_encontrada');
    exit;
}
if ($notificacao['origem'] !== 'gerado_sistema') {
    header('Location: visualizar.php?id=' . $notificacaoId . '&error=sem_geracao');
    exit;
}
if (!empty($notificacao['documento_id'])) {
    header('Location: visualizar.php?id=' . $notificacaoId . '&error=ja_assinado');
    exit;
}

$stmtAdmin = $pdo->prepare("SELECT nome, nome_completo, cargo, matricula_portaria, cpf FROM administradores WHERE id = ?");
$stmtAdmin->execute([$_SESSION['admin_id']]);
$admin = $stmtAdmin->fetch(PDO::FETCH_ASSOC) ?: [];

$assinante = [
    'nome' => ($admin['nome_completo'] ?: ($admin['nome'] ?: $_SESSION['admin_nome'])),
    'cargo' => ($admin['cargo'] ?: 'Fiscal de Obras'),
    'cpf' => ($admin['cpf'] ?? ''),
    'matricula' => ($admin['matricula_portaria'] ?? ''),
    'data_hora' => date('d/m/Y \à\s H:i:s'),
];

try {
    require_once __DIR__ . '/../assinatura/gerar_pdf.php';

    $conteudoHtml = renderizarNotificacaoObrasHtml($notificacao);
    $numeroDocumento = fiscalizacaoObrasTipoLabel($notificacao['tipo_documento']) . ' ' . $notificacao['numero'] . '/' . $notificacao['ano'];

    $dirDestino = __DIR__ . '/../pareceres_obras/' . $notificacaoId;
    if (!is_dir($dirDestino)) {
        mkdir($dirDestino, 0755, true);
    }

    $nomeArquivo = 'Notificacao_' . $notificacaoId . '_' . date('His') . '.pdf';
    $caminhoFisico = $dirDestino . '/' . $nomeArquivo;
    $caminhoRelativo = 'pareceres_obras/' . $notificacaoId . '/' . $nomeArquivo;

    $documentoId = bin2hex(random_bytes(16));
    $verifyUrlPdf = rtrim(BASE_URL, '/') . '/verificar';

    emitirParecerAssinado($conteudoHtml, $assinante, $numeroDocumento, 'F', $caminhoFisico, [
        'verify_url' => $verifyUrlPdf,
        'doc_codigo' => $documentoId,
    ]);

    if (!file_exists($caminhoFisico)) {
        throw new RuntimeException('Falha ao gravar o arquivo PDF.');
    }

    @file_put_contents($dirDestino . '/' . $documentoId . '.html', $conteudoHtml);

    $servico = new AssinaturaDigitalService($pdo);
    $hash = $servico->calcularHashDocumento($caminhoFisico);
    $assinaturaCriptografada = $servico->assinarHash($hash);

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO assinaturas_digitais (
            documento_id, requerimento_id, notificacao_obras_id, tipo_documento, nome_arquivo,
            caminho_arquivo, hash_documento, assinante_id, assinante_nome,
            assinante_cpf, assinante_cargo, tipo_assinatura, assinatura_visual,
            assinatura_criptografada, timestamp_assinatura, ip_assinante, metadados_json
        ) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'digital_sema', NULL, ?, NOW(), ?, ?)
    ");
    $stmt->execute([
        $documentoId,
        $notificacaoId,
        $notificacao['tipo_documento'],
        $nomeArquivo,
        $caminhoRelativo,
        $hash,
        $_SESSION['admin_id'],
        $assinante['nome'],
        $assinante['cpf'],
        $assinante['cargo'],
        $assinaturaCriptografada,
        $_SERVER['REMOTE_ADDR'] ?? null,
        json_encode(['notificacao_obras_id' => $notificacaoId], JSON_UNESCAPED_UNICODE),
    ]);

    registrarDocumentoAssinadoNotificacao($pdo, $notificacaoId, $documentoId);

    $pdo->commit();

    header('Location: visualizar.php?id=' . $notificacaoId . '&success=assinada');
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[fiscalizacao_obras/assinar_handler] ' . $e->getMessage());
    header('Location: visualizar.php?id=' . $notificacaoId . '&error=assinatura_falhou');
    exit;
}
