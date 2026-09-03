<?php
require_once 'conexao.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_notifications.php';
verificaLogin();

$adminId = (int) $_SESSION['admin_id'];
$notificationId = (int) ($_GET['id'] ?? 0);

$notification = $notificationId > 0 ? findAdminNotificationById($pdo, $notificationId, $adminId) : null;
if (!$notification) {
    header('Location: notificacoes.php');
    exit;
}

if (isset($_GET['marcar_nao_lida'])) {
    markAdminNotificationAsUnread($pdo, $notificationId, $adminId);
    header('Location: notificacao.php?id=' . $notificationId);
    exit;
}

markAdminNotificationAsRead($pdo, $notificationId, $adminId);
$notification['foi_lida'] = true;

// Rótulo legível por tipo — mesma régua usada nos outros paineis (setor/origem
// do evento), já que a tabela não guarda quem disparou a notificação.
$tipoLabels = [
    'novo_protocolo'          => 'Novo protocolo',
    'boleto_enviado'          => 'Boleto enviado',
    'comprovante_enviado'     => 'Comprovante enviado',
    'indeferido'              => 'Processo indeferido',
    'encaminhado_setor2'      => 'Enviado à Fiscalização',
    'encaminhado_setor3'      => 'Enviado ao Secretário',
    'devolvido_setor1'        => 'Devolvido à Triagem',
    'devolvido_setor2'        => 'Devolvido à Fiscalização',
    'setor3_aprovado'         => 'Secretário aprovou',
    'assinatura_solicitada'   => 'Assinatura solicitada',
    'coassinatura_solicitada' => 'Assinatura solicitada',
    'coassinatura_recusada'   => 'Co-assinatura recusada',
    'coassinatura_concluida'  => 'Documento assinado',
];
$origemLabels = [
    'novo_protocolo'          => 'Cidadão — formulário público',
    'encaminhado_setor2'      => 'Setor 1 — Triagem Ambiental',
    'encaminhado_setor3'      => 'Setor 2 — Fiscalização de Obras',
    'devolvido_setor1'        => 'Setor 2 — Fiscalização de Obras',
    'devolvido_setor2'        => 'Setor 3 — Secretário',
    'setor3_aprovado'         => 'Setor 3 — Secretário',
    'assinatura_solicitada'   => 'Solicitação de co-assinatura',
    'coassinatura_solicitada' => 'Solicitação de co-assinatura',
    'coassinatura_recusada'   => 'Solicitação de co-assinatura',
    'coassinatura_concluida'  => 'Solicitação de co-assinatura',
];
$acaoLabels = [
    'assinatura_solicitada'   => ['label' => 'Abrir e assinar', 'icon' => 'fa-pen-nib'],
    'coassinatura_solicitada' => ['label' => 'Abrir e assinar', 'icon' => 'fa-pen-nib'],
];

$tipo = (string) $notification['tipo'];
$tipoLabel = $tipoLabels[$tipo] ?? 'Atualização de processo';
$origemLabel = $origemLabels[$tipo] ?? 'Sistema';
$acaoInfo = $acaoLabels[$tipo] ?? ['label' => 'Abrir o processo', 'icon' => 'fa-eye'];

$processo = null;
if (!empty($notification['requerimento_id'])) {
    $stmt = $pdo->prepare("
        SELECT r.id, r.protocolo, r.tipo_alvara, r.setor_atual, req.nome AS requerente_nome
        FROM requerimentos r
        JOIN requerentes req ON req.id = r.requerente_id
        WHERE r.id = ?
    ");
    $stmt->execute([(int) $notification['requerimento_id']]);
    $processo = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$setorLabels = [
    'setor1' => 'Triagem Ambiental',
    'setor2' => 'Fiscalização de Obras',
    'setor3' => 'Revisão do Secretário',
];

$stmt = $pdo->prepare("
    SELECT n.*, CASE WHEN r.id IS NULL THEN 0 ELSE 1 END AS foi_lida
    FROM admin_notifications n
    LEFT JOIN admin_notification_reads r ON r.notification_id = n.id AND r.admin_id = ?
    WHERE (n.destinatario_admin_id IS NULL OR n.destinatario_admin_id = ?) AND n.id <> ?
    ORDER BY CASE WHEN r.id IS NULL THEN 0 ELSE 1 END ASC, n.criado_em DESC
    LIMIT 6
");
$stmt->execute([$adminId, $adminId, $notificationId]);
$outras = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($outras as &$o) {
    $o['icon'] = adminNotificationIcon($o['tipo']);
    $o['accent_class'] = adminNotificationAccent($o['tipo']);
}
unset($o);

include 'header.php';
?>
<style>
    .notif-shell { max-width: 1000px; margin: 0 auto; display: flex; flex-direction: column; gap: 12px; }
    .notif-crumb { display: flex; align-items: center; gap: 10px; font-size: .8rem; color: var(--muted); }
    .notif-crumb a { display: inline-flex; align-items: center; gap: 6px; padding: 5px 10px; border: 1px solid var(--line); border-radius: 9px; background: #fff; font-weight: 700; color: var(--ink); }
    .notif-grid { display: grid; grid-template-columns: minmax(0,1fr) 320px; gap: 14px; align-items: start; }
    @media (max-width: 860px) { .notif-grid { grid-template-columns: 1fr; } }
    .notif-card { background: #fff; border: 1px solid var(--line); border-radius: 16px; overflow: hidden; }
    .notif-main { padding: 22px; }
    .notif-icon { width: 48px; height: 48px; border-radius: 14px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.15rem; flex-shrink: 0; }
    .notif-pill { display: inline-flex; padding: 3px 9px; border-radius: 999px; background: #f1f5f0; color: #3d5c46; font-size: .68rem; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; }
    .notif-pill-status { display: inline-flex; align-items: center; gap: 5px; padding: 3px 9px; border-radius: 999px; font-size: .68rem; font-weight: 800; }
    .notif-meta-grid { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: 1px; margin-top: 20px; background: #eef2ef; border: 1px solid #eef2ef; border-radius: 12px; overflow: hidden; }
    @media (max-width: 620px) { .notif-meta-grid { grid-template-columns: 1fr; } }
    .notif-meta-cell { background: #fff; padding: 11px 14px; }
    .notif-meta-label { font-size: .71rem; color: #8fa399; }
    .notif-meta-value { margin-top: 2px; font-size: .85rem; font-weight: 600; overflow-wrap: anywhere; }
    .notif-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 18px; }
    .notif-btn { display: inline-flex; align-items: center; gap: 8px; min-height: 44px; padding: 0 16px; border-radius: 11px; font-size: .86rem; font-weight: 700; text-decoration: none; border: 0; cursor: pointer; }
    .notif-btn-primary { background: var(--primary); color: #fff; }
    .notif-btn-primary:hover { background: var(--primary-strong); color: #fff; }
    .notif-btn-secondary { background: #fff; border: 1px solid var(--line); color: var(--ink); }
    .notif-side-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 12px 15px; border-bottom: 1px solid #eef2ef; font-size: .68rem; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; color: #3d5c46; }
    .notif-other { display: flex; align-items: center; gap: 12px; width: 100%; padding: 12px 15px; border-bottom: 1px solid #f4f7f5; background: transparent; border-left: 0; border-right: 0; border-top: 0; text-decoration: none; color: inherit; text-align: left; }
    .notif-other:last-child { border-bottom: 0; }
    .notif-other:hover { background: #fbfcfb; }
    .notif-other-ic { width: 30px; height: 30px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: .8rem; flex-shrink: 0; }
    .notif-other-title { font-size: .82rem; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .notif-other-time { margin-top: 1px; font-size: .73rem; color: #8fa399; }
    .notif-other-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--primary); flex-shrink: 0; }
    .notif-proc { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; padding: 15px; }
</style>

<div class="notif-shell">
    <div class="notif-crumb">
        <a href="notificacoes.php"><i class="fas fa-arrow-left"></i> Notificações</a>
        <span style="color:#c8d2cc;">/</span>
        <span><?= htmlspecialchars($tipoLabel) ?></span>
    </div>

    <div class="notif-grid">
        <div style="display:flex;flex-direction:column;gap:14px;">
            <div class="notif-card notif-main">
                <div style="display:flex;align-items:flex-start;gap:14px;">
                    <span class="notif-icon <?= htmlspecialchars($notification['accent_class'] ?? adminNotificationAccent($tipo)) ?>">
                        <i class="fas <?= htmlspecialchars($notification['icon'] ?? adminNotificationIcon($tipo)) ?>"></i>
                    </span>
                    <div style="flex:1;min-width:0;">
                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:5px;">
                            <span class="notif-pill"><?= htmlspecialchars($tipoLabel) ?></span>
                            <span class="notif-pill-status" style="background:<?= $notification['foi_lida'] ? '#f1f5f0' : '#fce7e7' ?>;color:<?= $notification['foi_lida'] ? '#3d5c46' : '#b13232' ?>;">
                                <?= $notification['foi_lida'] ? 'Lida' : 'Não lida' ?>
                            </span>
                        </div>
                        <h2 style="margin:0;font-size:1.28rem;font-weight:800;line-height:1.2;"><?= htmlspecialchars($notification['titulo']) ?></h2>
                        <p style="margin:8px 0 0;font-size:.92rem;line-height:1.6;color:#21372b;"><?= htmlspecialchars($notification['descricao']) ?></p>
                    </div>
                </div>

                <div class="notif-meta-grid">
                    <div class="notif-meta-cell"><div class="notif-meta-label">Recebida</div><div class="notif-meta-value"><?= htmlspecialchars(formataData($notification['criado_em'])) ?></div></div>
                    <div class="notif-meta-cell"><div class="notif-meta-label">Origem</div><div class="notif-meta-value"><?= htmlspecialchars($origemLabel) ?></div></div>
                    <div class="notif-meta-cell"><div class="notif-meta-label">Tipo no banco</div><div class="notif-meta-value" style="font-family:'Geist Mono',ui-monospace,monospace;font-weight:500;"><?= htmlspecialchars($tipo) ?></div></div>
                </div>

                <div class="notif-actions">
                    <a href="<?= htmlspecialchars($notification['destino']) ?>" class="notif-btn notif-btn-primary"><i class="fas <?= htmlspecialchars($acaoInfo['icon']) ?>"></i><?= htmlspecialchars($acaoInfo['label']) ?></a>
                    <?php if ($notification['foi_lida']): ?>
                        <a href="notificacao.php?id=<?= $notificationId ?>&marcar_nao_lida=1" class="notif-btn notif-btn-secondary"><i class="fas fa-envelope"></i>Marcar como não lida</a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($processo): ?>
                <div class="notif-card">
                    <div class="notif-side-head" style="text-transform:none;letter-spacing:normal;font-weight:800;">Processo relacionado</div>
                    <div class="notif-proc">
                        <div style="flex:1;min-width:220px;">
                            <div style="font-family:ui-monospace,Menlo,monospace;font-size:1.05rem;font-weight:500;"><?= htmlspecialchars($processo['protocolo']) ?></div>
                            <div style="margin-top:3px;font-size:.95rem;font-weight:700;"><?= htmlspecialchars($processo['requerente_nome']) ?></div>
                            <div style="display:flex;align-items:center;gap:8px;margin-top:8px;flex-wrap:wrap;">
                                <span style="display:inline-flex;padding:3px 10px;border-radius:999px;background:#e6f2ea;color:#0f4425;font-size:.73rem;font-weight:700;"><?= htmlspecialchars(nomeAlvara($processo['tipo_alvara'])) ?></span>
                                <span style="display:inline-flex;align-items:center;gap:6px;padding:4px 11px;border-radius:999px;background:#f3e8ff;color:#7e22ce;font-size:.75rem;font-weight:700;"><span style="width:7px;height:7px;border-radius:50%;background:currentColor;"></span><?= htmlspecialchars($setorLabels[$processo['setor_atual']] ?? $processo['setor_atual']) ?></span>
                            </div>
                        </div>
                        <a href="visualizar_requerimento.php?id=<?= (int) $processo['id'] ?>" class="notif-btn notif-btn-secondary"><i class="fas fa-eye"></i>Abrir o processo</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="notif-card">
            <div class="notif-side-head">
                <span>Outras notificações</span>
                <a href="notificacoes.php" style="font-size:.75rem;font-weight:600;">Ver todas</a>
            </div>
            <?php if ($outras): ?>
                <?php foreach ($outras as $o): ?>
                    <a href="notificacao.php?id=<?= (int) $o['id'] ?>" class="notif-other">
                        <span class="notif-other-ic <?= htmlspecialchars($o['accent_class']) ?>"><i class="fas <?= htmlspecialchars($o['icon']) ?>"></i></span>
                        <span style="flex:1;min-width:0;">
                            <span class="notif-other-title"><?= htmlspecialchars($o['titulo']) ?></span>
                            <span class="notif-other-time"><?= htmlspecialchars(formataData($o['criado_em'])) ?></span>
                        </span>
                        <?php if (!$o['foi_lida']): ?><span class="notif-other-dot"></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="padding:16px 15px;font-size:.83rem;color:var(--muted);">Nenhuma outra notificação por aqui.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
