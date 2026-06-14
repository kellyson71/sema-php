<?php
/**
 * Card "Documentos aguardando sua assinatura".
 * Inclua em qualquer dashboard; depende de $pdo e $_SESSION['admin_id'].
 * Renderiza nada se não houver pendências.
 */
require_once __DIR__ . '/../../includes/coassinatura_helper.php';

$__adminIdCard = (int) ($_SESSION['admin_id'] ?? 0);
$__stmtCard = $pdo->prepare("
    SELECT sa.documento_id, sa.requerimento_id, sa.criado_em,
           r.protocolo, s.nome AS solicitante_nome
    FROM solicitacoes_assinatura sa
    JOIN requerimentos r   ON r.id = sa.requerimento_id
    JOIN administradores s ON s.id = sa.solicitante_id
    WHERE sa.destinatario_id = ? AND sa.status = 'pendente'
    GROUP BY sa.documento_id
    ORDER BY sa.criado_em DESC
    LIMIT 5
");
$__stmtCard->execute([$__adminIdCard]);
$__pendCard = $__stmtCard->fetchAll(PDO::FETCH_ASSOC);

if (!empty($__pendCard)):
    $__base = (basename(dirname($_SERVER['SCRIPT_NAME'] ?? '')) !== 'admin') ? '../' : '';
?>
<div style="background:#fff;border:1px solid #e6eaf0;border-left:4px solid #1c4b36;border-radius:14px;padding:18px 20px;margin-bottom:20px;box-shadow:0 2px 10px rgba(0,0,0,.05);">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
        <i class="fas fa-file-signature" style="color:#1c4b36;font-size:1.1rem;"></i>
        <strong style="color:#1e293b;">Documentos aguardando sua assinatura</strong>
        <span style="background:#b91c1c;color:#fff;font-size:.72rem;font-weight:700;border-radius:20px;padding:2px 9px;"><?= count($__pendCard) ?></span>
        <a href="<?= $__base ?>minhas_assinaturas.php" style="margin-left:auto;font-size:.8rem;color:#1c4b36;font-weight:600;text-decoration:none;">Ver todos <i class="fas fa-arrow-right ms-1" style="font-size:.7rem;"></i></a>
    </div>
    <?php foreach ($__pendCard as $__p): ?>
        <a href="<?= $__base ?>coassinar_documento.php?documento_id=<?= urlencode($__p['documento_id']) ?>"
           style="display:flex;align-items:center;gap:12px;padding:10px 12px;border:1px solid #eef1f5;border-radius:10px;margin-bottom:8px;text-decoration:none;color:inherit;transition:background .12s;"
           onmouseover="this.style.background='#f7faf8'" onmouseout="this.style.background='#fff'">
            <i class="fas fa-pen-nib" style="color:#1c4b36;"></i>
            <div style="flex-grow:1;">
                <div style="font-weight:700;color:#1e293b;font-size:.88rem;">#<?= htmlspecialchars($__p['protocolo']) ?></div>
                <div style="font-size:.76rem;color:#64748b;">Solicitado por <?= htmlspecialchars($__p['solicitante_nome']) ?> &middot; <?= date('d/m/Y H:i', strtotime($__p['criado_em'])) ?></div>
            </div>
            <span style="font-size:.78rem;color:#1c4b36;font-weight:600;">Assinar <i class="fas fa-chevron-right ms-1" style="font-size:.65rem;"></i></span>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
