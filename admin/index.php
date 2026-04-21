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
        gap: 22px;
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
        padding: 28px;
        background:
            radial-gradient(circle at top right, rgba(13, 84, 51, 0.08), transparent 26%),
            linear-gradient(135deg, rgba(255,255,255,0.98), rgba(247,250,248,0.96));
        border-color: var(--line);
    }

    .hero-kicker {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 14px;
        padding: 6px 10px;
        border-radius: 999px;
        background: var(--primary-soft);
        color: var(--primary-strong);
        font-size: .75rem;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
    }

    .hero-title {
        font-size: 1.8rem;
        font-weight: 800;
        line-height: 1.2;
        margin-bottom: 8px;
        color: var(--ink);
    }

    .hero-subtitle {
        max-width: 760px;
        font-size: .95rem;
        color: var(--muted);
        margin-bottom: 16px;
        line-height: 1.55;
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
        gap: 18px;
    }

    .metric-card {
        padding: 22px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        border-color: var(--line);
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
        font-size: 1.85rem;
        font-weight: 800;
        line-height: 1;
        color: var(--ink);
        margin-bottom: 6px;
    }

    .metric-copy span {
        display: block;
        font-size: .82rem;
        color: var(--muted);
        line-height: 1.45;
    }

    .metric-icon {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 1.05rem;
    }

    .metric-icon.info { background: var(--primary-soft); color: var(--primary-strong); }
    .metric-icon.warning { background: #fff7df; color: #9a6700; }
    .metric-icon.danger { background: #fce7e7; color: #a32929; }
    .metric-icon.success { background: #e4f4ea; color: #0d5433; }

    .dashboard-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 18px;
        align-items: start;
    }

    .queue-card,
    .activity-card,
    .mail-card {
        padding: 24px;
        border-color: var(--line);
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
        font-size: 1.16rem;
        font-weight: 800;
        color: var(--ink);
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
        padding: 18px 20px;
        border: 1px solid var(--line);
        border-radius: 20px;
        background: linear-gradient(180deg, #fff, #fbfdfb);
        transition: transform .2s ease, border-color .2s ease, background-color .2s ease;
    }

    .queue-item:hover {
        transform: translateY(-1px);
        border-color: var(--line-strong);
        background: #fff;
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
        font-weight: 700;
        color: var(--ink);
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
        border: 1px dashed var(--line-strong);
        border-radius: 20px;
        background: #fbfdfb;
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
            <span class="hero-kicker"><i class="fas fa-leaf"></i>Painel operacional</span>
            <h2 class="hero-title"><?= $saudacao ?>, <?= htmlspecialchars($_SESSION['admin_nome']) ?></h2>
            <p class="hero-subtitle" style="margin-bottom:0">Resumo do momento: <?= implode(' &middot; ', array_map('htmlspecialchars', $resumoOperacional)) ?>.</p>
        </div>
    </section>

    <section class="metric-grid">
        <article class="metric-card">
            <div class="metric-copy">
                <small>Total</small>
                <strong><?= $totalRequerimentos ?></strong>
                <span>Protocolos registrados na base administrativa.</span>
            </div>
            <span class="metric-icon info"><i class="fas fa-folder-tree"></i></span>
        </article>
        <article class="metric-card">
            <div class="metric-copy">
                <small>Em analise</small>
                <strong><?= $emAnalise ?></strong>
                <span>Fluxos técnicos aguardando andamento.</span>
            </div>
            <span class="metric-icon warning"><i class="fas fa-hourglass-half"></i></span>
        </article>
        <article class="metric-card">
            <div class="metric-copy">
                <small>Nao lidos</small>
                <strong><?= $naoVisualizados ?></strong>
                <span>Itens ainda fora da fila visualizada.</span>
            </div>
            <span class="metric-icon danger"><i class="fas fa-eye-slash"></i></span>
        </article>
        <article class="metric-card">
            <div class="metric-copy">
                <small>Fiscalizacao</small>
                <strong><?= (int) $totalAguardandoFiscal ?></strong>
                <span>Demandas aguardando vistoria ou retorno.</span>
            </div>
            <span class="metric-icon success"><i class="fas fa-hard-hat"></i></span>
        </article>
    </section>

    <section class="dashboard-grid">
        <div class="queue-card">
            <div class="section-head">
                <div class="section-head-copy">
                    <h2>Ultimos requerimentos</h2>
                    <p>Fila recente com acesso rapido aos protocolos mais atuais.</p>
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
