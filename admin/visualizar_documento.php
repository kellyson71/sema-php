<?php
require_once 'conexao.php';
require_once 'helpers.php';
require_once '../includes/admin_notifications.php';
require_once '../tipos_alvara.php';
verificaLogin();
ensureAdminNotificationTables($pdo);

$requerimentoId = (int) ($_GET['requerimento_id'] ?? 0);
$documentoIdFiltro = trim($_GET['documento_id'] ?? '');

if (!$requerimentoId) {
    header("Location: requerimentos.php");
    exit;
}

// Processar solicitação de assinatura via POST
$mensagem = '';
$mensagemTipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['solicitar_assinatura'])) {
    $docId = trim($_POST['solicitar_documento_id'] ?? '');
    $destinatarioId = (int) ($_POST['solicitar_destinatario_id'] ?? 0);

    if (empty($docId) || $destinatarioId <= 0) {
        $mensagem = "Selecione um documento e um destinatário.";
        $mensagemTipo = "danger";
    } else {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("
                INSERT INTO solicitacoes_assinatura (documento_id, requerimento_id, solicitante_id, destinatario_id)
                VALUES (?, ?, ?, ?)
            ")->execute([$docId, $requerimentoId, $_SESSION['admin_id'], $destinatarioId]);

            $stmtNome = $pdo->prepare("SELECT nome FROM administradores WHERE id = ? LIMIT 1");
            $stmtNome->execute([$destinatarioId]);
            $nomeDestinatario = $stmtNome->fetchColumn() ?: 'Usuário';

            $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao) VALUES (?, ?, ?)")->execute(
                [$_SESSION['admin_id'], $requerimentoId, "Solicitou assinatura de {$nomeDestinatario} no documento {$docId}"]
            );
            createAdminNotificationForRequerimento($pdo, $requerimentoId, 'assinatura_solicitada', [
                'titulo'    => 'Assinatura solicitada',
                'descricao' => "Sua assinatura foi solicitada em um documento do processo.",
                'link_url'  => "visualizar_documento.php?requerimento_id={$requerimentoId}&documento_id={$docId}",
            ]);
            $pdo->commit();
            $mensagem = "Solicitação enviada para {$nomeDestinatario}.";
            $mensagemTipo = "success";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $mensagem = "Erro: " . $e->getMessage();
            $mensagemTipo = "danger";
        }
    }
}

// Buscar dados do requerimento
$stmtReq = $pdo->prepare("
    SELECT r.*, req.nome AS requerente_nome
    FROM requerimentos r
    JOIN requerentes req ON req.id = r.requerente_id
    WHERE r.id = ? LIMIT 1
");
$stmtReq->execute([$requerimentoId]);
$requerimento = $stmtReq->fetch();

if (!$requerimento) {
    header("Location: requerimentos.php");
    exit;
}

// Buscar documentos assinados do requerimento
$stmtDocs = $pdo->prepare("
    SELECT ad.*, a.nome AS assinante_nome_adm
    FROM assinaturas_digitais ad
    LEFT JOIN administradores a ON a.id = ad.assinante_id
    WHERE ad.requerimento_id = ?
    ORDER BY ad.timestamp_assinatura DESC
");
$stmtDocs->execute([$requerimentoId]);
$documentosAssinados = $stmtDocs->fetchAll();

// Agrupar assinaturas por documento_id
$docMap = [];
foreach ($documentosAssinados as $row) {
    $did = $row['documento_id'];
    if (!isset($docMap[$did])) {
        $docMap[$did] = [
            'documento_id'    => $did,
            'nome_arquivo'    => $row['nome_arquivo'],
            'caminho_arquivo' => $row['caminho_arquivo'],
            'primeira_assinatura' => $row['timestamp_assinatura'],
            'assinaturas'     => [],
        ];
    }
    $docMap[$did]['assinaturas'][] = [
        'nome'  => $row['assinante_nome'] ?? $row['assinante_nome_adm'],
        'cargo' => $row['assinante_cargo'],
        'data'  => $row['timestamp_assinatura'],
    ];
}
$documentos = array_values($docMap);

// Documento selecionado (para o viewer)
$docSelecionado = null;
if ($documentoIdFiltro) {
    foreach ($documentos as $d) {
        if ($d['documento_id'] === $documentoIdFiltro) {
            $docSelecionado = $d;
            break;
        }
    }
}
if (!$docSelecionado && !empty($documentos)) {
    $docSelecionado = $documentos[0];
}

// Verificar solicitações pendentes para o usuário atual neste requerimento
$stmtSolic = $pdo->prepare("
    SELECT sa.*, ad.documento_id AS doc_id, adm.nome AS solicitante_nome
    FROM solicitacoes_assinatura sa
    JOIN assinaturas_digitais ad ON ad.documento_id = sa.documento_id
    JOIN administradores adm ON adm.id = sa.solicitante_id
    WHERE sa.requerimento_id = ? AND sa.destinatario_id = ? AND sa.status = 'pendente'
");
$stmtSolic->execute([$requerimentoId, $_SESSION['admin_id']]);
$solicitacoesPendentes = $stmtSolic->fetchAll();

// Buscar administradores ativos para modal de solicitação
$stmtAdmins = $pdo->prepare("SELECT id, nome, cargo FROM administradores WHERE ativo = 1 AND id != ? ORDER BY nome");
$stmtAdmins->execute([$_SESSION['admin_id']]);
$admins = $stmtAdmins->fetchAll();

$tipoNome = $tipos_alvara[$requerimento['tipo_alvara']]['nome'] ?? ucwords(str_replace('_', ' ', $requerimento['tipo_alvara']));
$pdfUrl = $docSelecionado ? ('assinatura/redownload_pdf.php?id=' . urlencode($docSelecionado['documento_id'])) : '';

include 'header.php';
?>
<link rel="stylesheet" href="<?= adminAssetUrl('includes/admin-styles.css') ?>">
<style>
    .doc-viewer-layout {
        display: grid;
        grid-template-columns: 1fr 340px;
        gap: 20px;
        height: calc(100vh - 140px);
        min-height: 500px;
    }
    .doc-embed-wrap {
        background: #f8fafc;
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .doc-embed-wrap iframe {
        width: 100%;
        height: 100%;
        border: none;
    }
    .doc-sidebar {
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 14px;
    }
    .sig-chip {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 10px 12px;
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        border-radius: 8px;
        font-size: .825rem;
    }
    .sig-chip-icon {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: #16a34a;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        margin-top: 2px;
    }
    .sig-chip-icon i { color: #fff; font-size: .7rem; }
    .pending-chip {
        background: #fff7ed;
        border-color: #fed7aa;
    }
    .pending-chip .sig-chip-icon { background: #ea580c; }
    @media (max-width: 900px) {
        .doc-viewer-layout { grid-template-columns: 1fr; height: auto; }
        .doc-embed-wrap { height: 60vh; }
    }
</style>

<div class="admin-page-shell">
    <section class="page-hero page-hero-compact">
        <div class="page-hero-copy">
            <h1 class="page-title">Visualizar Documento</h1>
            <p class="page-subtitle">
                Protocolo <strong><?= htmlspecialchars($requerimento['protocolo']) ?></strong> —
                <?= htmlspecialchars($tipoNome) ?>
                &nbsp;·&nbsp;
                <a href="visualizar_requerimento.php?id=<?= $requerimentoId ?>" style="color:inherit;text-decoration:underline;">
                    <i class="fas fa-arrow-left me-1"></i>Voltar ao processo
                </a>
            </p>
        </div>
    </section>

    <?php if ($mensagem): ?>
    <div class="alert alert-<?= $mensagemTipo ?> mx-0 mb-3"><?= htmlspecialchars($mensagem) ?></div>
    <?php endif; ?>

    <?php if (!empty($solicitacoesPendentes)): ?>
    <div class="alert alert-warning d-flex align-items-center gap-2 mb-3" style="border-radius:10px">
        <i class="fas fa-file-signature" style="font-size:1.1rem"></i>
        <span>
            <strong>Sua assinatura foi solicitada</strong> em
            <?= count($solicitacoesPendentes) === 1 ? '1 documento' : count($solicitacoesPendentes) . ' documentos' ?>
            deste processo.
        </span>
    </div>
    <?php endif; ?>

    <?php if (empty($documentos)): ?>
    <div class="modern-card">
        <div class="card-body text-center py-5 text-muted">
            <i class="fas fa-file-slash" style="font-size:2.5rem;margin-bottom:12px;display:block"></i>
            Nenhum documento assinado encontrado para este processo.
        </div>
    </div>
    <?php else: ?>

    <!-- Seletor de documento (se mais de um) -->
    <?php if (count($documentos) > 1): ?>
    <div class="mb-3">
        <label class="form-label fw-semibold" style="font-size:.875rem">Selecionar documento:</label>
        <select class="form-select form-select-sm" style="max-width:480px"
            onchange="window.location.href='visualizar_documento.php?requerimento_id=<?= $requerimentoId ?>&documento_id='+this.value">
            <?php foreach ($documentos as $d): ?>
            <option value="<?= htmlspecialchars($d['documento_id']) ?>"
                <?= ($docSelecionado && $docSelecionado['documento_id'] === $d['documento_id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($d['nome_arquivo']) ?>
                (<?= count($d['assinaturas']) ?> assinatura<?= count($d['assinaturas']) !== 1 ? 's' : '' ?>)
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>

    <div class="doc-viewer-layout">
        <!-- Viewer do PDF -->
        <div class="doc-embed-wrap">
            <?php if ($docSelecionado): ?>
            <iframe src="<?= htmlspecialchars($pdfUrl) ?>#toolbar=1" title="Documento"></iframe>
            <?php else: ?>
            <p class="text-muted">Nenhum documento selecionado.</p>
            <?php endif; ?>
        </div>

        <!-- Painel lateral -->
        <div class="doc-sidebar">

            <?php if ($docSelecionado): ?>
            <!-- Metadados -->
            <div class="modern-card">
                <div class="modern-card-header">
                    <i class="fas fa-info-circle icon"></i>
                    <h6>Informações</h6>
                </div>
                <div class="card-body" style="font-size:.85rem">
                    <div style="margin-bottom:6px"><span class="text-muted">Arquivo:</span> <strong><?= htmlspecialchars($docSelecionado['nome_arquivo']) ?></strong></div>
                    <div style="margin-bottom:6px"><span class="text-muted">Primeira assinatura:</span> <?= date('d/m/Y H:i', strtotime($docSelecionado['primeira_assinatura'])) ?></div>
                    <div class="mt-2 d-flex gap-2 flex-wrap">
                        <a href="<?= htmlspecialchars($pdfUrl) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-eye me-1"></i>Abrir
                        </a>
                        <a href="assinatura/redownload_pdf.php?id=<?= urlencode($docSelecionado['documento_id']) ?>" class="btn btn-sm btn-outline-secondary" download>
                            <i class="fas fa-download me-1"></i>Baixar
                        </a>
                    </div>
                </div>
            </div>

            <!-- Assinaturas existentes -->
            <div class="modern-card">
                <div class="modern-card-header">
                    <i class="fas fa-signatures icon"></i>
                    <h6>Assinaturas (<?= count($docSelecionado['assinaturas']) ?>)</h6>
                </div>
                <div class="card-body" style="display:flex;flex-direction:column;gap:8px">
                    <?php foreach ($docSelecionado['assinaturas'] as $sig): ?>
                    <div class="sig-chip">
                        <div class="sig-chip-icon"><i class="fas fa-check"></i></div>
                        <div>
                            <div style="font-weight:600;color:#14532d"><?= htmlspecialchars($sig['nome']) ?></div>
                            <?php if ($sig['cargo']): ?>
                            <div style="color:#6b7280"><?= htmlspecialchars($sig['cargo']) ?></div>
                            <?php endif; ?>
                            <div style="color:#9ca3af;font-size:.775rem"><?= date('d/m/Y H:i', strtotime($sig['data'])) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Solicitações pendentes para este usuário -->
            <?php
            $solicParaEste = array_filter($solicitacoesPendentes, fn($s) => $s['doc_id'] === $docSelecionado['documento_id']);
            ?>
            <?php if (!empty($solicParaEste)): ?>
            <div class="modern-card" style="border:2px solid #fed7aa">
                <div class="modern-card-header" style="background:#fff7ed">
                    <i class="fas fa-file-signature icon" style="color:#ea580c"></i>
                    <h6 style="color:#92400e">Assinatura Solicitada</h6>
                </div>
                <div class="card-body" style="display:flex;flex-direction:column;gap:8px">
                    <?php foreach ($solicParaEste as $solic): ?>
                    <div class="sig-chip pending-chip">
                        <div class="sig-chip-icon"><i class="fas fa-pen-nib"></i></div>
                        <div>
                            <div style="font-weight:600;color:#92400e">Solicitado por <?= htmlspecialchars($solic['solicitante_nome']) ?></div>
                            <div style="color:#9ca3af;font-size:.775rem"><?= date('d/m/Y H:i', strtotime($solic['criado_em'])) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <a href="documentos/selecionar.php?requerimento_id=<?= $requerimentoId ?>&documento_id=<?= urlencode($docSelecionado['documento_id']) ?>"
                        class="btn btn-sm text-white fw-semibold mt-1"
                        style="background:#ea580c">
                        <i class="fas fa-pen-nib me-1"></i>Assinar este documento
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Ações -->
            <div class="modern-card">
                <div class="modern-card-header">
                    <i class="fas fa-cog icon"></i>
                    <h6>Ações</h6>
                </div>
                <div class="card-body d-flex flex-column gap-2">
                    <a href="documentos/selecionar.php?requerimento_id=<?= $requerimentoId ?>&documento_id=<?= urlencode($docSelecionado['documento_id']) ?>"
                        class="btn btn-sm text-white fw-medium"
                        style="background:var(--primary-600)">
                        <i class="fas fa-pen-nib me-2"></i>Assinar Documento
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-primary fw-medium"
                        data-bs-toggle="modal" data-bs-target="#solicitarAssinaturaModal">
                        <i class="fas fa-user-plus me-2"></i>Solicitar Assinatura
                    </button>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <?php endif; ?>
</div>

<!-- Modal: Solicitar Assinatura -->
<div class="modal fade" id="solicitarAssinaturaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <div class="d-flex align-items-center gap-2">
                    <span style="width:36px;height:36px;border-radius:8px;background:#f0fdf4;display:flex;align-items:center;justify-content:center;">
                        <i class="fas fa-user-plus" style="color:#16a34a"></i>
                    </span>
                    <h5 class="modal-title fw-bold mb-0">Solicitar Assinatura</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="">
                <input type="hidden" name="solicitar_documento_id"
                    value="<?= htmlspecialchars($docSelecionado['documento_id'] ?? '') ?>">
                <div class="modal-body px-4 pt-3 pb-2">
                    <p class="text-muted small mb-3">Selecione o usuário que deverá assinar este documento. Ele receberá uma notificação interna.</p>
                    <div class="mb-2">
                        <label class="form-label fw-semibold" style="font-size:.875rem">
                            Usuário destinatário <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" name="solicitar_destinatario_id" required>
                            <option value="">-- Selecione --</option>
                            <?php foreach ($admins as $adm): ?>
                            <option value="<?= $adm['id'] ?>">
                                <?= htmlspecialchars($adm['nome']) ?>
                                <?php if ($adm['cargo']): ?>(<?= htmlspecialchars($adm['cargo']) ?>)<?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-2 gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="solicitar_assinatura" class="btn btn-success px-4 fw-medium">
                        <i class="fas fa-paper-plane me-2"></i>Enviar Solicitação
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
