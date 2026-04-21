<?php
require_once 'conexao.php';

$host = $_SERVER['HTTP_HOST'] ?? '';
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
if (!MODO_HOMOLOG && preg_match('/^(www\.)?sema\.protocolosead\.com$/i', $host)) {
    $redirect_url = 'http://sema.paudosferros.rn.gov.br' . $requestUri;
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: $redirect_url");
    exit();
}
verificaLogin();

if ((isset($_SESSION['admin_nivel']) && $_SESSION['admin_nivel'] === 'secretario') || (isset($_SESSION['admin_email']) && $_SESSION['admin_email'] === 'secretario@sema.rn.gov.br')) {
    header("Location: secretario_dashboard.php");
    exit;
}

if (isset($_SESSION['admin_nivel']) && $_SESSION['admin_nivel'] === 'fiscal') {
    header("Location: fiscal_dashboard.php");
    exit;
}

$totalRequerimentos = (int) $pdo->query("SELECT COUNT(*) FROM requerimentos")->fetchColumn();
$emAnalise = (int) $pdo->query("SELECT COUNT(*) FROM requerimentos WHERE status = 'Em análise'")->fetchColumn();
$naoVisualizados = (int) $pdo->query("SELECT COUNT(*) FROM requerimentos WHERE visualizado = 0")->fetchColumn();

$stmt = $pdo->query("
    SELECT r.id, r.protocolo, r.tipo_alvara, r.status, r.data_envio, req.nome AS requerente
    FROM requerimentos r
    JOIN requerentes req ON r.requerente_id = req.id
    ORDER BY r.data_envio DESC
    LIMIT 10
");
$ultimosRequerimentos = $stmt->fetchAll();

$stmt = $pdo->query("
    SELECT ha.acao, ha.data_acao, a.nome AS admin_nome, r.protocolo
    FROM historico_acoes ha
    LEFT JOIN administradores a ON ha.admin_id = a.id
    LEFT JOIN requerimentos r ON ha.requerimento_id = r.id
    ORDER BY ha.data_acao DESC
    LIMIT 8
");
$historicoAcoes = $stmt->fetchAll();

$stmt = $pdo->query("
    SELECT el.email_destino, el.assunto, el.status, el.data_envio, r.protocolo
    FROM email_logs el
    LEFT JOIN requerimentos r ON el.requerimento_id = r.id
    WHERE el.eh_teste = 0 OR el.eh_teste IS NULL
    ORDER BY el.data_envio DESC
    LIMIT 8
");
$ultimosEmails = $stmt->fetchAll();

include 'header.php';

$saudacao = 'Bom dia';
$horaAtual = (int) date('H');
if ($horaAtual >= 12 && $horaAtual < 18) {
    $saudacao = 'Boa tarde';
} elseif ($horaAtual >= 18) {
    $saudacao = 'Boa noite';
}

$statusMeta = [
    'Em análise' => ['class' => 'status-em-analise', 'label' => 'Em análise'],
    'Aprovado' => ['class' => 'status-aprovado', 'label' => 'Aprovado'],
    'Finalizado' => ['class' => 'status-finalizado', 'label' => 'Finalizado'],
    'Reprovado' => ['class' => 'status-reprovado', 'label' => 'Reprovado'],
    'Pendente' => ['class' => 'status-pendente', 'label' => 'Pendente'],
    'Cancelado' => ['class' => 'status-cancelado', 'label' => 'Cancelado'],
    'Indeferido' => ['class' => 'status-indeferido', 'label' => 'Indeferido'],
    'Apto a gerar alvará' => ['class' => 'status-apto-a-gerar-alvara', 'label' => 'Apto a gerar alvará'],
    'Alvará Emitido' => ['class' => 'status-alvara-emitido', 'label' => 'Alvará emitido'],
    'Aguardando Fiscalização' => ['class' => 'status-aguardando-fiscalizacao', 'label' => 'Aguardando fiscalização'],
    'Aguardando boleto' => ['class' => 'status-aguardando-boleto', 'label' => 'Aguardando boleto'],
    'Boleto pago' => ['class' => 'status-boleto-pago', 'label' => 'Boleto pago'],
];

$resumoOperacional = [];
if ($emAnalise > 0) {
    $resumoOperacional[] = $emAnalise . ' em análise';
}
if ($naoVisualizados > 0) {
    $resumoOperacional[] = $naoVisualizados . ' não visualizados';
}
if (($totalAguardandoFiscal ?? 0) > 0) {
    $resumoOperacional[] = $totalAguardandoFiscal . ' aguardando fiscalização';
}
if (!$resumoOperacional) {
    $resumoOperacional[] = 'sem filas críticas no momento';
}
?>

<style>
    .dashboard-shell {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .dashboard-hero {
        display: block;
    }

    .hero-card,
    .hero-side-card,
    .metric-card,
    .queue-card,
    .activity-card,
    .mail-card {
        border: 1px solid rgba(219, 231, 243, .96);
        border-radius: var(--radius-lg);
        background: rgba(255,255,255,.95);
        box-shadow: var(--card-shadow);
    }

    .hero-card {
        padding: 24px;
        background: #fff;
    }

    .hero-title {
        font-size: 1.4rem;
        font-weight: 700;
        line-height: 1.2;
        margin-bottom: 6px;
        color: var(--text);
    }

    .hero-subtitle {
        font-size: .88rem;
        color: var(--muted);
        margin-bottom: 16px;
    }

    .hero-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
    }

    .hero-actions .btn {
        border-radius: 14px;
        padding: 10px 16px;
        font-weight: 600;
    }

    .metric-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 16px;
    }

    .metric-card {
        padding: 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
    }

    .metric-copy small {
        display: block;
        font-size: .76rem;
        font-weight: 600;
        color: var(--muted);
        text-transform: uppercase;
        letter-spacing: .08em;
        margin-bottom: 8px;
    }

    .metric-copy strong {
        display: block;
        font-size: 1.7rem;
        font-weight: 700;
        line-height: 1;
        color: var(--text);
        margin-bottom: 6px;
    }

    .metric-copy span {
        display: block;
        font-size: .82rem;
        color: var(--muted);
        line-height: 1.45;
    }

    .metric-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 1.05rem;
    }

    .metric-icon.info { background: #e6f7ef; color: #007840; }
    .metric-icon.warning { background: #fffbeb; color: #b45309; }
    .metric-icon.danger { background: #fef2f2; color: #b91c1c; }
    .metric-icon.success { background: #ecfdf5; color: #047857; }

    .dashboard-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 18px;
        align-items: start;
    }

    .queue-card,
    .activity-card,
    .mail-card {
        padding: 22px;
    }

    .section-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        margin-bottom: 18px;
    }

    .section-head-copy h2,
    .section-head-copy h3 {
        margin: 0 0 6px;
        font-size: 1.08rem;
        font-weight: 700;
        color: var(--text);
    }

    .section-head-copy p {
        margin: 0;
        font-size: .84rem;
        color: var(--muted);
        line-height: 1.5;
    }

    .queue-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .queue-item {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 18px;
        align-items: center;
        padding: 16px 18px;
        border: 1px solid rgba(219, 231, 243, .9);
        border-radius: 18px;
        background: #fff;
        transition: transform .2s ease, border-color .2s ease, background-color .2s ease;
    }

    .queue-item:hover {
        transform: translateY(-1px);
        border-color: #b7d8ea;
        background: #fafdff;
    }

    .queue-item-main {
        min-width: 0;
    }

    .queue-item-top {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
        margin-bottom: 8px;
    }

    .queue-protocol {
        font-size: .78rem;
        font-weight: 700;
        color: var(--primary-strong);
        text-transform: uppercase;
        letter-spacing: .06em;
    }

    .queue-name {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text);
        margin-bottom: 4px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .queue-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        font-size: .8rem;
        color: var(--muted);
    }

    .queue-meta span {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .queue-item-side {
        text-align: right;
    }

    .queue-date {
        font-size: .76rem;
        color: var(--muted);
        margin-top: 8px;
    }

    .queue-empty {
        padding: 28px;
        border: 1px dashed rgba(180, 198, 214, .9);
        border-radius: 18px;
        background: #fafcff;
        text-align: center;
        color: var(--muted);
        font-size: .92rem;
    }

    .side-stack {
        display: flex;
        flex-direction: column;
        gap: 18px;
    }

    .feed-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
        max-height: 440px;
        overflow-y: auto;
        padding-right: 4px;
    }

    .feed-item {
        display: grid;
        grid-template-columns: auto 1fr;
        gap: 12px;
        padding: 12px 14px;
        border-radius: 16px;
        background: var(--surface-soft);
        border: 1px solid rgba(219, 231, 243, .85);
    }

    .feed-icon {
        width: 36px;
        height: 36px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #fff;
        color: var(--primary-strong);
        border: 1px solid rgba(219, 231, 243, .85);
        flex-shrink: 0;
    }

    .feed-copy small {
        display: block;
        color: var(--muted);
        font-size: .72rem;
        margin-bottom: 3px;
    }

    .feed-copy strong {
        display: block;
        font-size: .85rem;
        color: var(--text);
        margin-bottom: 4px;
    }

    .feed-copy span {
        display: block;
        font-size: .78rem;
        color: var(--muted);
        line-height: 1.5;
    }

    .feed-copy .feed-protocol {
        margin-top: 6px;
        color: var(--primary-strong);
        font-weight: 600;
    }

    .section-head .btn {
        border-radius: 12px;
    }

    @media (max-width: 1199px) {
        .metric-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .dashboard-grid,
        .dashboard-hero {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 767px) {
        .hero-card,
        .hero-side-card,
        .metric-card,
        .queue-card,
        .activity-card,
        .mail-card {
            padding: 18px;
            border-radius: 20px;
        }

        .hero-title {
            font-size: 1.45rem;
        }

        .metric-grid {
            grid-template-columns: 1fr;
        }

        .queue-item {
            grid-template-columns: 1fr;
        }

        .queue-item-side {
            text-align: left;
        }

        .section-head {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>

<div class="dashboard-shell">
    <section class="dashboard-hero">
        <div class="hero-card">
            <h2 class="hero-title"><?= $saudacao ?>, <?= htmlspecialchars($_SESSION['admin_nome']) ?></h2>
            <p class="hero-subtitle" style="margin-bottom:0"><?= implode(' &middot; ', array_map('htmlspecialchars', $resumoOperacional)) ?></p>
        </div>
    </section>

    <section class="metric-grid">
        <article class="metric-card">
            <div class="metric-copy">
                <small>Total</small>
                <strong><?= $totalRequerimentos ?></strong>
            </div>
            <span class="metric-icon info"><i class="fas fa-folder-tree"></i></span>
        </article>
        <article class="metric-card">
            <div class="metric-copy">
                <small>Em analise</small>
                <strong><?= $emAnalise ?></strong>
            </div>
            <span class="metric-icon warning"><i class="fas fa-hourglass-half"></i></span>
        </article>
        <article class="metric-card">
            <div class="metric-copy">
                <small>Nao lidos</small>
                <strong><?= $naoVisualizados ?></strong>
            </div>
            <span class="metric-icon danger"><i class="fas fa-eye-slash"></i></span>
        </article>
        <article class="metric-card">
            <div class="metric-copy">
                <small>Fiscalizacao</small>
                <strong><?= (int) $totalAguardandoFiscal ?></strong>
            </div>
            <span class="metric-icon success"><i class="fas fa-hard-hat"></i></span>
        </article>
    </section>

    <section class="dashboard-grid">
        <div class="queue-card">
            <div class="section-head">
                <div class="section-head-copy">
                    <h2>Ultimos requerimentos</h2>
                </div>
                <a href="requerimentos.php" class="btn btn-outline-secondary btn-sm">Ver todos</a>
            </div>

            <?php if ($ultimosRequerimentos): ?>
                <div class="queue-list">
                    <?php foreach ($ultimosRequerimentos as $req): ?>
                        <?php $meta = $statusMeta[$req['status']] ?? ['class' => 'status-pendente', 'label' => $req['status']]; ?>
                        <a href="visualizar_requerimento.php?id=<?= (int) $req['id'] ?>" class="queue-item">
                            <div class="queue-item-main">
                                <div class="queue-item-top">
                                    <span class="queue-protocol">Protocolo #<?= htmlspecialchars($req['protocolo']) ?></span>
                                    <span class="badge badge-status <?= htmlspecialchars($meta['class']) ?>"><?= htmlspecialchars($meta['label']) ?></span>
                                </div>
                                <div class="queue-name"><?= htmlspecialchars($req['requerente']) ?></div>
                                <div class="queue-meta">
                                    <span><i class="fas fa-file-lines"></i><?= htmlspecialchars($req['tipo_alvara']) ?></span>
                                    <span><i class="far fa-calendar"></i><?= formataData($req['data_envio']) ?></span>
                                </div>
                            </div>
                            <div class="queue-item-side">
                                <div class="btn btn-light btn-sm">
                                    Abrir <i class="fas fa-arrow-right ms-1"></i>
                                </div>
                                <div class="queue-date">Atualizado em <?= date('d/m \à\s H:i', strtotime($req['data_envio'])) ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="queue-empty">Nenhum requerimento encontrado para compor a fila operacional.</div>
            <?php endif; ?>
        </div>

    </section>
</div>

<?php include 'footer.php'; ?>
