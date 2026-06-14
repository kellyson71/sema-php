<?php
require_once 'conexao.php';
require_once __DIR__ . '/../includes/functions.php';
verificaLogin();

$adminId = (int) ($_SESSION['admin_id'] ?? 0);

// Pendências de co-assinatura direcionadas a mim
$stmt = $pdo->prepare("
    SELECT sa.documento_id, sa.requerimento_id, sa.mensagem, sa.criado_em,
           r.protocolo, req.nome AS requerente_nome,
           s.nome AS solicitante_nome,
           ad.tipo_documento
    FROM solicitacoes_assinatura sa
    JOIN requerimentos r   ON r.id = sa.requerimento_id
    JOIN requerentes req   ON req.id = r.requerente_id
    JOIN administradores s ON s.id = sa.solicitante_id
    LEFT JOIN assinaturas_digitais ad ON ad.documento_id = sa.documento_id
    WHERE sa.destinatario_id = ? AND sa.status = 'pendente'
    GROUP BY sa.documento_id
    ORDER BY sa.criado_em DESC
");
$stmt->execute([$adminId]);
$pendencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

$titulo_pagina = 'Para assinar';
include 'header.php';
?>
<style>
    .pa-head { display:flex; align-items:center; gap:12px; margin-bottom:20px; }
    .pa-head .pa-ic { width:46px; height:46px; border-radius:13px; background:linear-gradient(135deg,#1c4b36,#0d7f5f); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.2rem; }
    .pa-head h1 { font-size:1.4rem; font-weight:800; color:#1e293b; margin:0; }
    .pa-head p { margin:0; color:#64748b; font-size:.85rem; }
    .pa-card { display:flex; align-items:center; gap:16px; background:#fff; border:1px solid #e6eaf0; border-radius:14px; padding:16px 18px; margin-bottom:12px; box-shadow:0 2px 10px rgba(0,0,0,.04); transition:transform .12s, box-shadow .12s; }
    .pa-card:hover { transform:translateY(-2px); box-shadow:0 8px 22px rgba(0,0,0,.1); }
    .pa-card .pa-doc { width:46px; height:46px; border-radius:12px; background:#f0f7f3; color:#1c4b36; display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex-shrink:0; }
    .pa-card .pa-proto { font-weight:800; color:#1e293b; }
    .pa-card .pa-meta { font-size:.8rem; color:#64748b; margin-top:2px; }
    .pa-card .pa-msg { font-size:.8rem; color:#475569; font-style:italic; margin-top:3px; }
    .pa-card .pa-go { margin-left:auto; flex-shrink:0; }
    .pa-btn { display:inline-flex; align-items:center; gap:8px; padding:10px 16px; border-radius:10px; background:#1c4b36; color:#fff; font-weight:700; font-size:.86rem; text-decoration:none; transition:filter .12s; }
    .pa-btn:hover { filter:brightness(1.1); color:#fff; }
    .pa-empty { text-align:center; padding:60px 20px; color:#94a3b8; }
    .pa-empty i { font-size:3rem; margin-bottom:14px; color:#cbd5e1; }
</style>

<div class="pa-head">
    <div class="pa-ic"><i class="fas fa-file-signature"></i></div>
    <div>
        <h1>Para assinar</h1>
        <p>Documentos que solicitaram a sua co-assinatura</p>
    </div>
</div>

<?php if (empty($pendencias)): ?>
    <div class="pa-empty">
        <div><i class="fas fa-circle-check"></i></div>
        <div class="fw-semibold">Nenhuma assinatura pendente</div>
        <div style="font-size:.85rem;">Quando solicitarem sua assinatura, os documentos aparecerão aqui.</div>
    </div>
<?php else: ?>
    <?php foreach ($pendencias as $p):
        $tipo = ucfirst(str_replace('_', ' ', $p['tipo_documento'] ?? 'documento')); ?>
        <div class="pa-card">
            <div class="pa-doc"><i class="fas fa-file-lines"></i></div>
            <div class="flex-grow-1">
                <div class="pa-proto">#<?= htmlspecialchars($p['protocolo']) ?> — <?= htmlspecialchars($tipo) ?></div>
                <div class="pa-meta">
                    Solicitado por <strong><?= htmlspecialchars($p['solicitante_nome']) ?></strong>
                    &middot; <?= date('d/m/Y H:i', strtotime($p['criado_em'])) ?>
                    &middot; Processo de <?= htmlspecialchars($p['requerente_nome']) ?>
                </div>
                <?php if (!empty($p['mensagem'])): ?>
                    <div class="pa-msg">"<?= htmlspecialchars($p['mensagem']) ?>"</div>
                <?php endif; ?>
            </div>
            <div class="pa-go">
                <a href="coassinar_documento.php?documento_id=<?= urlencode($p['documento_id']) ?>" class="pa-btn">
                    <i class="fas fa-pen-nib"></i> Revisar e assinar
                </a>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php include 'footer.php'; ?>
