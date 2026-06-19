<?php
require_once 'conexao.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/coassinatura_helper.php';
verificaLogin();

$documentoId = trim($_GET['documento_id'] ?? '');
$adminId     = (int) ($_SESSION['admin_id'] ?? 0);

if ($documentoId === '') {
    header('Location: requerimentos.php');
    exit;
}

// Dados básicos do documento (1ª linha de assinaturas_digitais) + fonte
$stmt = $pdo->prepare("
    SELECT ad.documento_id, ad.requerimento_id, ad.tipo_documento, ad.nome_arquivo,
           r.protocolo, req.nome AS requerente_nome,
           (df.documento_id IS NOT NULL) AS tem_fonte
    FROM assinaturas_digitais ad
    JOIN requerimentos r ON r.id = ad.requerimento_id
    JOIN requerentes req ON req.id = r.requerente_id
    LEFT JOIN documentos_fonte df ON df.documento_id = ad.documento_id
    WHERE ad.documento_id = ?
    LIMIT 1
");
$stmt->execute([$documentoId]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doc) {
    header('Location: requerimentos.php?error=documento_nao_encontrado');
    exit;
}

$requerimentoId = (int) $doc['requerimento_id'];
$status = statusAssinaturasDocumento($pdo, $documentoId);

// Tenho uma solicitação pendente para mim neste documento?
$stmtP = $pdo->prepare("
    SELECT sa.mensagem, s.nome AS solicitante_nome
    FROM solicitacoes_assinatura sa
    JOIN administradores s ON s.id = sa.solicitante_id
    WHERE sa.documento_id = ? AND sa.destinatario_id = ? AND sa.status = 'pendente'
    LIMIT 1
");
$stmtP->execute([$documentoId, $adminId]);
$minhaPendencia = $stmtP->fetch(PDO::FETCH_ASSOC);

$jaAssinei = false;
foreach ($status['assinantes'] as $a) {
    if (($a['id'] ?? 0) === $adminId) { $jaAssinei = true; break; }
}

$tipoLegivel = ucfirst(str_replace('_', ' ', $doc['tipo_documento'] ?? 'documento'));
$titulo_pagina = 'Co-assinatura de Documento';
include 'header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<style>
    :root { --co-green:#1c4b36; --co-green2:#0d7f5f; }
    .co-wrap { display:grid; grid-template-columns: 1fr 360px; gap:18px; align-items:start; }
    @media (max-width: 960px){ .co-wrap { grid-template-columns:1fr; } }
    .co-viewer { background:#525659; border-radius:14px; overflow:hidden; box-shadow:0 8px 30px rgba(0,0,0,.18); height:78vh; }
    .co-viewer iframe { width:100%; height:100%; border:0; display:block; }
    .co-panel { background:#fff; border:1px solid #e6eaf0; border-radius:14px; padding:20px; box-shadow:0 4px 18px rgba(0,0,0,.06); }
    .co-head { display:flex; align-items:center; gap:10px; margin-bottom:4px; }
    .co-head .co-ic { width:40px; height:40px; border-radius:11px; background:linear-gradient(135deg,var(--co-green),var(--co-green2)); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.05rem; flex-shrink:0; }
    .co-title { font-weight:800; font-size:1.02rem; color:#1e293b; line-height:1.15; }
    .co-sub { font-size:.78rem; color:#94a3b8; }
    .co-callout { background:#f0f7f3; border:1px solid #cfe6da; border-radius:10px; padding:11px 13px; margin:14px 0; font-size:.84rem; color:#33503f; }
    .co-callout .cc-msg { color:#475569; font-style:italic; margin-top:4px; }
    .co-progress-label { font-size:.72rem; font-weight:800; text-transform:uppercase; letter-spacing:.05em; color:#94a3b8; margin:16px 0 8px; }
    .co-count { font-weight:800; color:var(--co-green); }
    .co-sig { display:flex; align-items:center; gap:11px; padding:9px 11px; border-radius:10px; margin-bottom:7px; border:1px solid #eef1f5; }
    .co-sig.done { background:#f3faf6; border-color:#cdeadb; }
    .co-sig.wait { background:#fff; border-style:dashed; }
    .co-sig.deny { background:#fff6f6; border-color:#f3d2d2; }
    .co-sig .s-ic { width:30px; height:30px; border-radius:50%; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:.78rem; color:#fff; }
    .co-sig.done .s-ic { background:#15803d; }
    .co-sig.wait .s-ic { background:#cbd5e1; }
    .co-sig.deny .s-ic { background:#b91c1c; }
    .co-sig .s-nome { font-weight:700; font-size:.84rem; color:#1e293b; }
    .co-sig .s-meta { font-size:.72rem; color:#94a3b8; }
    .co-actions { margin-top:18px; display:flex; flex-direction:column; gap:9px; }
    .co-btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:12px 16px; border-radius:11px; font-weight:700; font-size:.92rem; border:none; cursor:pointer; transition:transform .12s, box-shadow .12s, filter .12s; }
    .co-btn:hover { transform:translateY(-1px); box-shadow:0 6px 16px rgba(0,0,0,.14); filter:brightness(1.03); }
    .co-btn-assinar { background:linear-gradient(135deg,var(--co-green),var(--co-green2)); color:#fff; }
    .co-btn-recusar { background:#fff; color:#b91c1c; border:1.5px solid #f0c7c7; }
    .co-btn-recusar:hover { background:#b91c1c; color:#fff; }
    .co-pinwrap { margin-top:6px; }
    .co-pinwrap label { font-weight:700; font-size:.78rem; color:var(--co-green); display:flex; gap:6px; align-items:center; margin-bottom:5px; }
    .co-info-readonly { background:#f8fafc; border:1px dashed #cbd5e1; border-radius:10px; padding:12px; font-size:.84rem; color:#64748b; text-align:center; margin-top:14px; }
</style>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div class="d-flex align-items-center gap-2">
        <a href="visualizar_requerimento.php?id=<?= $requerimentoId ?>" class="btn btn-sm btn-light border fw-medium text-secondary">
            <i class="fas fa-arrow-left me-1"></i> Voltar ao processo
        </a>
        <span class="badge px-3 py-2 rounded-pill fw-semibold" style="background:#f0fdf4;color:var(--co-green);border:1px solid #bbf7d0;">
            <i class="fas fa-hashtag me-1"></i><?= htmlspecialchars($doc['protocolo']) ?>
        </span>
    </div>
</div>

<div class="co-wrap">
    <div class="co-viewer">
        <iframe src="assinatura/redownload_pdf.php?id=<?= urlencode($documentoId) ?>&inline=1" title="Documento"></iframe>
    </div>

    <div class="co-panel">
        <div class="co-head">
            <div class="co-ic"><i class="fas fa-file-signature"></i></div>
            <div>
                <div class="co-title"><?= htmlspecialchars($tipoLegivel) ?></div>
                <div class="co-sub">Processo de <?= htmlspecialchars($doc['requerente_nome']) ?></div>
            </div>
        </div>

        <?php if ($minhaPendencia): ?>
            <div class="co-callout">
                <i class="fas fa-user-pen me-1"></i>
                <strong><?= htmlspecialchars($minhaPendencia['solicitante_nome']) ?></strong> solicitou sua assinatura.
                <?php if (!empty($minhaPendencia['mensagem'])): ?>
                    <div class="cc-msg">"<?= htmlspecialchars($minhaPendencia['mensagem']) ?>"</div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="co-progress-label">
            Progresso —
            <span class="co-count"><?= $status['total_assinado'] ?> de <?= $status['total_esperado'] ?></span> assinaram
        </div>

        <?php foreach ($status['assinantes'] as $a): ?>
            <div class="co-sig done">
                <div class="s-ic"><i class="fas fa-check"></i></div>
                <div>
                    <div class="s-nome"><?= htmlspecialchars($a['nome']) ?></div>
                    <div class="s-meta"><?= htmlspecialchars($a['cargo'] ?? '') ?> &middot; <?= date('d/m/Y H:i', strtotime($a['data'])) ?></div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php foreach ($status['pendentes'] as $p): ?>
            <div class="co-sig wait">
                <div class="s-ic"><i class="fas fa-hourglass-half"></i></div>
                <div>
                    <div class="s-nome"><?= htmlspecialchars($p['nome']) ?></div>
                    <div class="s-meta">Aguardando assinatura</div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php foreach ($status['recusados'] as $r): ?>
            <div class="co-sig deny">
                <div class="s-ic"><i class="fas fa-xmark"></i></div>
                <div>
                    <div class="s-nome"><?= htmlspecialchars($r['nome']) ?></div>
                    <div class="s-meta">Recusou<?= !empty($r['motivo']) ? ' — ' . htmlspecialchars($r['motivo']) : '' ?></div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if ($minhaPendencia && !$jaAssinei): ?>
            <div class="co-actions">
                <input type="hidden" id="pinCo" value="">
                <button class="co-btn co-btn-assinar" onclick="assinarDoc()">
                    <i class="fas fa-signature"></i> Assinar documento
                </button>
                <button class="co-btn co-btn-recusar" onclick="recusarDoc()">
                    <i class="fas fa-xmark"></i> Recusar assinatura
                </button>
            </div>
        <?php elseif ($jaAssinei): ?>
            <div class="co-info-readonly"><i class="fas fa-check-circle text-success me-1"></i> Você já assinou este documento.</div>
        <?php else: ?>
            <div class="co-info-readonly"><i class="fas fa-eye me-1"></i> Você não tem uma assinatura pendente neste documento.</div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const _docId = <?= json_encode($documentoId) ?>;
const _reqId = <?= (int) $requerimentoId ?>;

function assinarDoc() {
    const pin = '';
    Swal.fire({ title:'Assinando…', didOpen:()=>Swal.showLoading(), allowOutsideClick:false });
    const fd = new FormData();
    fd.append('documento_id', _docId);
    fd.append('requerimento_id', _reqId);
    fd.append('pin_assinatura', pin);
    fetch('assinatura/coassinar.php', { method:'POST', body:fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                Swal.fire({ icon:'success', title:'Assinatura registrada!', text:'O documento foi atualizado com a sua assinatura.', timer:2600, showConfirmButton:false })
                    .then(() => location.reload());
            } else {
                Swal.fire('Erro', d.error || 'Não foi possível assinar.', 'error');
            }
        })
        .catch(() => Swal.fire('Erro', 'Falha de comunicação.', 'error'));
}

function recusarDoc() {
    Swal.fire({
        title: 'Recusar assinatura',
        input: 'textarea',
        inputLabel: 'Motivo da recusa (obrigatório)',
        inputPlaceholder: 'Explique por que não vai assinar este documento…',
        inputAttributes: { 'aria-label': 'Motivo da recusa' },
        showCancelButton: true,
        confirmButtonText: 'Recusar',
        confirmButtonColor: '#b91c1c',
        cancelButtonText: 'Cancelar',
        inputValidator: (v) => (!v || v.trim().length < 5) ? 'Descreva o motivo (mínimo 5 caracteres).' : undefined
    }).then(res => {
        if (!res.isConfirmed) return;
        const fd = new FormData();
        fd.append('documento_id', _docId);
        fd.append('requerimento_id', _reqId);
        fd.append('motivo', res.value.trim());
        fetch('assinatura/recusar_assinatura.php', { method:'POST', body:fd })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    Swal.fire({ icon:'success', title:'Recusa registrada', text:'Quem solicitou foi avisado.', timer:2400, showConfirmButton:false })
                        .then(() => location.href = 'visualizar_requerimento.php?id=' + _reqId);
                } else {
                    Swal.fire('Erro', d.error || 'Não foi possível registrar a recusa.', 'error');
                }
            })
            .catch(() => Swal.fire('Erro', 'Falha de comunicação.', 'error'));
    });
}
</script>
<?php include 'footer.php'; ?>
