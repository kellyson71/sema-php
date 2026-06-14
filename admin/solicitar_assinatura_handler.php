<?php
require_once 'conexao.php';
require_once __DIR__ . '/../includes/admin_notifications.php';
verificaLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: requerimentos.php');
    exit;
}

$requerimentoId  = (int) ($_POST['requerimento_id'] ?? 0);
$documentoId     = trim($_POST['documento_id'] ?? '');
$destinatarioId  = (int) ($_POST['destinatario_id'] ?? 0);
$mensagem        = trim($_POST['mensagem'] ?? '');
$solicitanteId   = $_SESSION['admin_id'];

if (!$requerimentoId || !$documentoId || !$destinatarioId) {
    header("Location: visualizar_documento.php?requerimento_id=$requerimentoId&error=dados_invalidos");
    exit;
}

try {
    ensureAdminNotificationTables($pdo);

    // Verificar se já existe solicitação pendente para este doc+destinatário
    $stmt = $pdo->prepare("
        SELECT id FROM solicitacoes_assinatura
        WHERE documento_id = ? AND destinatario_id = ? AND status = 'pendente'
        LIMIT 1
    ");
    $stmt->execute([$documentoId, $destinatarioId]);
    if ($stmt->fetch()) {
        header("Location: visualizar_documento.php?requerimento_id=$requerimentoId&error=solicitacao_duplicada");
        exit;
    }

    $pdo->beginTransaction();

    $pdo->prepare("
        INSERT INTO solicitacoes_assinatura
            (documento_id, requerimento_id, solicitante_id, destinatario_id, mensagem)
        VALUES (?, ?, ?, ?, ?)
    ")->execute([$documentoId, $requerimentoId, $solicitanteId, $destinatarioId, $mensagem ?: null]);

    // Notificação DIRECIONADA ao destinatário, com link para a tela dedicada
    createAdminNotificationForRequerimento($pdo, $requerimentoId, 'coassinatura_solicitada', [
        'destinatario_admin_id' => $destinatarioId,
        'link_url' => 'coassinar_documento.php?documento_id=' . $documentoId,
    ]);

    $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao) VALUES (?,?,?)")
        ->execute([$solicitanteId, $requerimentoId, "Solicitou co-assinatura no documento $documentoId"]);

    $pdo->commit();
    header("Location: visualizar_documento.php?requerimento_id=$requerimentoId&success=solicitacao_enviada");
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $msg = urlencode($e->getMessage());
    header("Location: visualizar_documento.php?requerimento_id=$requerimentoId&error=erro_solicitacao&details=$msg");
    exit;
}
