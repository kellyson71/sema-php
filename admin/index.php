<?php
require_once 'conexao.php';
require_once __DIR__ . '/../includes/functions.php';

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
$novosSemana = (int) $pdo->query("SELECT COUNT(*) FROM requerimentos WHERE data_envio >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)")->fetchColumn();

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

$tipoSiglas = [
    'licenca_ambiental_unica' => 'LAU',
    'habite_se' => 'HBT',
    'habite_se_simples' => 'HBS',
    'construcao' => 'CNS',
    'licenca_previa_obras' => 'LPO',
    'desmembramento' => 'DSM',
];

$dataPainel = new DateTimeImmutable('now');
$diasSemana = [
    'Sunday' => 'domingo',
    'Monday' => 'segunda-feira',
    'Tuesday' => 'terça-feira',
    'Wednesday' => 'quarta-feira',
    'Thursday' => 'quinta-feira',
    'Friday' => 'sexta-feira',
    'Saturday' => 'sábado',
];
$meses = [
    1 => 'janeiro',
    2 => 'fevereiro',
    3 => 'março',
    4 => 'abril',
    5 => 'maio',
    6 => 'junho',
    7 => 'julho',
    8 => 'agosto',
    9 => 'setembro',
    10 => 'outubro',
    11 => 'novembro',
    12 => 'dezembro',
];
$dataPainelLabel = sprintf(
    '%s, %d de %s',
    $diasSemana[$dataPainel->format('l')] ?? strtolower($dataPainel->format('l')),
    (int) $dataPainel->format('d'),
    $meses[(int) $dataPainel->format('n')] ?? $dataPainel->format('m')
);

$rotaFiscal = $isAdmin ? 'simular_perfil.php?role=fiscal' : 'fiscal_dashboard.php';
?>

<style>
    .dashboard-shell { display:flex; flex-direction:column; gap:18px; max-width:1240px; margin:0 auto; }
    .dashboard-date { font-size:.84rem; color:var(--muted); font-weight:600; margin-bottom:10px; text-transform:lowercase; }
    .dashboard-hero { background:#fff; border:1px solid var(--line); border-radius:20px; padding:28px; box-shadow:var(--card-shadow); }
    .hero-title { margin:0 0 8px; font-size:2rem; font-weight:800; color:var(--ink); line-height:1.05; }
    .hero-subtitle { margin:0 0 18px; max-width:760px; color:var(--muted); font-size:1rem; line-height:1.5; }
    .hero-actions { display:flex; flex-wrap:wrap; gap:10px; }
    .hero-actions .btn { border-radius:14px; min-height:42px; padding:0 16px; font-weight:700; }
    .hero-actions .btn-primary { background:var(--primary); border-color:var(--primary); }
    .metric-grid { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:16px; }
    .metric-card, .panel-card { background:#fff; border:1px solid var(--line); border-radius:20px; box-shadow:var(--card-shadow); }
    .metric-card { padding:22px; }
    .metric-label { display:block; margin-bottom:6px; font-size:.76rem; color:var(--muted); font-weight:700; text-transform:uppercase; letter-spacing:.08em; }
    .metric-value { display:block; margin-bottom:6px; font-size:1.95rem; font-weight:800; color:var(--ink); line-height:1; }
    .metric-note { display:block; color:var(--muted); font-size:.82rem; }
    .dashboard-grid { display:grid; grid-template-columns:minmax(0, 300px) minmax(0, 1fr); gap:16px; align-items:start; }
    .fiscal-card { padding:22px; }
    .fiscal-kicker { display:block; margin-bottom:8px; font-size:.76rem; color:var(--muted); font-weight:700; text-transform:uppercase; letter-spacing:.08em; }
    .fiscal-title { margin:0 0 8px; font-size:1.15rem; font-weight:800; color:var(--ink); }
    .fiscal-copy { margin:0 0 16px; color:var(--muted); font-size:.88rem; line-height:1.5; }
    .fiscal-actions { display:flex; flex-wrap:wrap; gap:10px; }
    .fiscal-actions .btn { border-radius:12px; font-weight:700; }
    .queue-card { padding:22px; }
    .section-head { display:flex; align-items:flex-end; justify-content:space-between; gap:12px; margin-bottom:14px; }
    .section-head h2 { margin:0 0 4px; font-size:1.12rem; font-weight:800; color:var(--ink); }
    .section-head p { margin:0; color:var(--muted); font-size:.84rem; }
    .queue-list { display:flex; flex-direction:column; gap:10px; }
    .queue-item { display:grid; grid-template-columns:minmax(0, 1fr) auto; align-items:center; gap:16px; padding:16px 18px; border:1px solid var(--line); border-radius:18px; background:var(--surface-soft); transition:border-color .2s ease, background-color .2s ease; }
    .queue-item:hover { border-color:var(--line-strong); background:#fff; }
    .queue-item-top { display:flex; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:8px; }
    .queue-protocol { font-size:.82rem; font-weight:800; color:var(--primary-strong); letter-spacing:.02em; }
    .queue-name { margin-bottom:6px; font-size:1rem; font-weight:700; color:var(--ink); }
    .queue-meta { display:flex; flex-wrap:wrap; gap:10px; color:var(--muted); font-size:.82rem; }
    .queue-meta span { display:inline-flex; align-items:center; gap:6px; }
    .queue-type-short { display:inline-flex; align-items:center; justify-content:center; min-width:36px; padding:3px 8px; border-radius:999px; background:var(--primary-soft); color:var(--primary-strong); font-size:.7rem; font-weight:800; letter-spacing:.08em; }
    .queue-item-side { display:flex; flex-direction:column; align-items:flex-end; gap:8px; }
    .queue-open { min-height:36px; padding:0 14px; border:1px solid var(--line); border-radius:12px; background:#fff; color:var(--ink); font-size:.82rem; font-weight:700; display:inline-flex; align-items:center; }
    .queue-open:hover { border-color:var(--primary-soft-2); color:var(--primary); }
    .queue-date { color:var(--muted); font-size:.76rem; }
    .queue-empty { padding:24px; border:1px dashed var(--line-strong); border-radius:18px; color:var(--muted); text-align:center; }
    @media (max-width: 1199px) { .metric-grid { grid-template-columns:repeat(2, minmax(0, 1fr)); } .dashboard-grid { grid-template-columns:1fr; } }
    @media (max-width: 767px) { .dashboard-hero, .metric-card, .panel-card, .queue-card { padding:18px; border-radius:18px; } .hero-title { font-size:1.55rem; } .metric-grid { grid-template-columns:1fr; } .queue-item { grid-template-columns:1fr; } .queue-item-side { align-items:flex-start; } .section-head { flex-direction:column; align-items:flex-start; } }
</style>

<div class="dashboard-shell">
    <section class="dashboard-hero">
        <div class="dashboard-date"><?= htmlspecialchars($dataPainelLabel) ?></div>
        <h2 class="hero-title"><?= $saudacao ?>, <?= htmlspecialchars($_SESSION['admin_nome']) ?>.</h2>
        <p class="hero-subtitle">Você tem <strong><?= $naoVisualizados ?></strong> requerimentos novos e <strong><?= $emAnalise ?></strong> em análise hoje.</p>
        <div class="hero-actions">
            <a href="requerimentos.php?nao_visualizados=1" class="btn btn-primary">Abrir fila não lida</a>
        </div>
    </section>

    <section class="metric-grid">
        <article class="metric-card">
            <span class="metric-label">Total de processos</span>
            <strong class="metric-value"><?= $totalRequerimentos ?></strong>
            <span class="metric-note">+<?= $novosSemana ?> esta semana</span>
        </article>
        <article class="metric-card">
            <span class="metric-label">Em análise</span>
            <strong class="metric-value"><?= $emAnalise ?></strong>
            <span class="metric-note">aguardando ação</span>
        </article>
        <article class="metric-card">
            <span class="metric-label">Não visualizados</span>
            <strong class="metric-value"><?= $naoVisualizados ?></strong>
            <span class="metric-note">precisam revisão</span>
        </article>
        <article class="metric-card">
            <span class="metric-label">Em fiscalização</span>
            <strong class="metric-value"><?= (int) $totalAguardandoFiscal ?></strong>
            <span class="metric-note">campo ativo</span>
        </article>
    </section>

    <section class="dashboard-grid">
        <aside class="panel-card fiscal-card">
            <span class="fiscal-kicker">Acesso de fiscal</span>
            <h3 class="fiscal-title">Processos aguardando vistoria</h3>
            <p class="fiscal-copy">Visite as obras, registre relatórios de vistoria e marque processos como aptos ao alvará.</p>
            <div class="fiscal-actions">
                <a href="<?= htmlspecialchars($rotaFiscal) ?>" class="btn btn-success btn-sm">Processos para vistoria</a>
                <a href="estatisticas.php" class="btn btn-outline-secondary btn-sm">Relatórios</a>
            </div>
        </aside>

        <div class="queue-card">
            <div class="section-head">
                <div class="section-head-copy">
                    <h2>Últimos requerimentos</h2>
                    <p>Clique para abrir os detalhes do processo</p>
                </div>
                <a href="requerimentos.php" class="btn btn-outline-secondary btn-sm">Ver todos</a>
            </div>

            <?php if ($ultimosRequerimentos): ?>
                <div class="queue-list">
                    <?php foreach ($ultimosRequerimentos as $req): ?>
                        <?php $meta = $statusMeta[$req['status']] ?? ['class' => 'status-pendente', 'label' => $req['status']]; ?>
                        <?php $short = $tipoSiglas[$req['tipo_alvara']] ?? 'ALV'; ?>
                        <a href="visualizar_requerimento.php?id=<?= (int) $req['id'] ?>" class="queue-item">
                            <div class="queue-item-main">
                                <div class="queue-item-top">
                                    <span class="queue-protocol">#<?= htmlspecialchars($req['protocolo']) ?></span>
                                    <span class="badge badge-status <?= htmlspecialchars($meta['class']) ?>"><?= htmlspecialchars($meta['label']) ?></span>
                                </div>
                                <div class="queue-name"><?= htmlspecialchars($req['requerente']) ?></div>
                                <div class="queue-meta">
                                    <span class="queue-type-short"><?= htmlspecialchars($short) ?></span>
                                    <span><?= htmlspecialchars(nomeAlvara($req['tipo_alvara'])) ?></span>
                                    <span><?= date('d/m/Y H:i', strtotime($req['data_envio'])) ?></span>
                                </div>
                            </div>
                            <div class="queue-item-side">
                                <span class="queue-open">Abrir</span>
                                <div class="queue-date"><?= date('d/m/Y H:i', strtotime($req['data_envio'])) ?></div>
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
