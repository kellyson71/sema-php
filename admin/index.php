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

$ctaFilaLabel = $naoVisualizados > 0 ? 'Abrir fila não lida' : 'Ver requerimentos';
$ctaFilaHref = $naoVisualizados > 0 ? 'requerimentos.php?nao_visualizados=1' : 'requerimentos.php';
$ctaFilaIcon = $naoVisualizados > 0 ? 'fa-eye-slash' : 'fa-list';
?>

<style>
    .dashboard-shell { display:flex; flex-direction:column; gap:18px; max-width:1240px; margin:0 auto; }
    .dashboard-date { font-size:.84rem; color:var(--muted); font-weight:600; margin-bottom:10px; text-transform:lowercase; }
    .dashboard-hero { background:#fff; border:1px solid var(--line); border-radius:20px; padding:28px; box-shadow:var(--card-shadow); }
    .hero-title { margin:0 0 8px; font-size:2rem; font-weight:800; color:var(--ink); line-height:1.05; }
    .hero-subtitle { margin:0 0 18px; max-width:760px; color:var(--muted); font-size:1rem; line-height:1.5; }
    .hero-actions { display:flex; flex-wrap:wrap; gap:10px; }
    .hero-cta { border-radius:14px; min-height:44px; padding:0 18px; font-weight:700; border:1px solid var(--primary-soft-2); background:var(--primary); color:#fff; display:inline-flex; align-items:center; gap:8px; box-shadow:0 10px 18px rgba(20,83,45,.12); }
    .hero-cta:hover { background:var(--primary-strong); color:#fff; transform:translateY(-1px); }
    .hero-cta-secondary { border-radius:14px; min-height:44px; padding:0 16px; font-weight:700; border:1px solid var(--line); background:#fff; color:var(--ink); display:inline-flex; align-items:center; gap:8px; }
    .hero-cta-secondary:hover { border-color:var(--primary-soft-2); color:var(--primary); }
    .hero-helper { margin-top:12px; display:inline-flex; align-items:center; gap:8px; min-height:34px; padding:0 12px; border-radius:999px; background:var(--surface-soft); color:var(--muted); font-size:.8rem; font-weight:600; }
    .hero-helper i { color:var(--primary); }
    .metric-grid { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:16px; }
    .metric-card, .panel-card { background:#fff; border:1px solid var(--line); border-radius:20px; box-shadow:var(--card-shadow); }
    .metric-card { padding:22px; }
    .metric-label { display:block; margin-bottom:6px; font-size:.76rem; color:var(--muted); font-weight:700; text-transform:uppercase; letter-spacing:.08em; }
    .metric-value { display:block; margin-bottom:6px; font-size:1.95rem; font-weight:800; color:var(--ink); line-height:1; }
    .metric-note { display:block; color:var(--muted); font-size:.82rem; }
    .dashboard-grid { display:grid; grid-template-columns:minmax(0, 1fr); gap:16px; align-items:start; }
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
    .release-modal-dialog { max-width: 760px; }
    .release-modal-content { border: 0; border-radius: 28px; overflow: hidden; box-shadow: 0 32px 80px rgba(16, 33, 23, 0.16); }
    .release-modal-hero { padding: 28px 28px 22px; background: linear-gradient(135deg, #14532d 0%, #1f7a45 100%); color: #fff; }
    .release-modal-kicker { display:inline-flex; align-items:center; gap:8px; min-height:30px; padding:0 12px; border-radius:999px; background:rgba(255,255,255,.12); font-size:.76rem; font-weight:800; letter-spacing:.08em; text-transform:uppercase; }
    .release-modal-title { margin:16px 0 10px; font-size:2rem; font-weight:800; line-height:1.04; }
    .release-modal-copy { margin:0; max-width:560px; color:rgba(255,255,255,.84); line-height:1.65; }
    .release-modal-body { padding: 26px 28px 28px; background:#fff; }
    .release-highlights { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:14px; margin-bottom:20px; }
    .release-highlight { border:1px solid var(--line); border-radius:18px; background:var(--surface-soft); padding:16px; }
    .release-highlight-icon { width:40px; height:40px; border-radius:12px; display:inline-flex; align-items:center; justify-content:center; margin-bottom:12px; background:var(--primary-soft); color:var(--primary); }
    .release-highlight h3 { margin:0 0 6px; font-size:1rem; font-weight:800; color:var(--ink); }
    .release-highlight p { margin:0; font-size:.88rem; line-height:1.55; color:var(--muted); }
    .release-support { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; padding:18px; border:1px solid var(--line); border-radius:18px; background:#f8faf8; }
    .release-support h4 { margin:0 0 6px; font-size:1rem; font-weight:800; color:var(--ink); }
    .release-support p { margin:0; color:var(--muted); line-height:1.55; }
    .release-actions { display:flex; align-items:center; justify-content:flex-end; gap:10px; margin-top:20px; }
    .release-link { display:inline-flex; align-items:center; gap:8px; min-height:42px; padding:0 16px; border-radius:14px; border:1px solid var(--line); color:var(--ink); font-weight:700; background:#fff; }
    .release-link:hover { border-color:var(--primary-soft-2); color:var(--primary); }
    .release-close { display:inline-flex; align-items:center; gap:8px; min-height:42px; padding:0 18px; border-radius:14px; border:1px solid var(--primary-soft-2); color:#fff; font-weight:800; background:var(--primary); }
    .release-close:hover { background:var(--primary-strong); color:#fff; }
    @media (max-width: 1199px) { .metric-grid { grid-template-columns:repeat(2, minmax(0, 1fr)); } .dashboard-grid { grid-template-columns:1fr; } }
    @media (max-width: 767px) { .dashboard-hero, .metric-card, .panel-card, .queue-card { padding:18px; border-radius:18px; } .hero-title { font-size:1.55rem; } .metric-grid, .release-highlights { grid-template-columns:1fr; } .queue-item { grid-template-columns:1fr; } .queue-item-side { align-items:flex-start; } .section-head, .release-support, .release-actions { flex-direction:column; align-items:flex-start; } .release-modal-hero, .release-modal-body { padding:20px; } .release-modal-title { font-size:1.55rem; } }
</style>

<div class="dashboard-shell">
    <section class="dashboard-hero">
        <div class="dashboard-date"><?= htmlspecialchars($dataPainelLabel) ?></div>
        <h2 class="hero-title"><?= $saudacao ?>, <?= htmlspecialchars($_SESSION['admin_nome']) ?>.</h2>
        <p class="hero-subtitle">Você tem <strong><?= $naoVisualizados ?></strong> requerimentos novos e <strong><?= $emAnalise ?></strong> em análise hoje.</p>
        <div class="hero-actions">
            <a href="<?= htmlspecialchars($ctaFilaHref) ?>" class="hero-cta"><i class="fas <?= htmlspecialchars($ctaFilaIcon) ?>"></i><?= htmlspecialchars($ctaFilaLabel) ?></a>
            <a href="requerimentos.php" class="hero-cta-secondary"><i class="fas fa-list"></i>Ver todos os requerimentos</a>
        </div>
        <div class="hero-helper">
            <i class="fas fa-arrow-rotate-left"></i>
            A fila rápida abre só os não vistos. Na listagem, há um atalho para voltar ao total.
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
            <span class="metric-label">Novos na semana</span>
            <strong class="metric-value"><?= $novosSemana ?></strong>
            <span class="metric-note">entradas recentes</span>
        </article>
    </section>

    <section class="dashboard-grid">
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

<div class="modal fade" id="releaseUpdateModal" tabindex="-1" aria-hidden="true" data-release-version="admin-release-2026-04-22">
    <div class="modal-dialog modal-dialog-centered release-modal-dialog">
        <div class="modal-content release-modal-content">
            <div class="release-modal-hero">
                <span class="release-modal-kicker"><i class="fas fa-sparkles"></i> Atualização do painel</span>
                <h2 class="release-modal-title">O painel foi reestruturado e o fluxo ficou mais direto.</h2>
                <p class="release-modal-copy">Esta atualização traz visual novo no admin, ajustes no formulário de ambientação e um fluxo de boleto mais simples para acompanhamento do pagamento.</p>
            </div>
            <div class="release-modal-body">
                <div class="release-highlights">
                    <article class="release-highlight">
                        <span class="release-highlight-icon"><i class="fas fa-panels-top-left"></i></span>
                        <h3>Visual mais limpo</h3>
                        <p>As telas principais foram reorganizadas para dar mais foco na operação e reduzir excesso de informação.</p>
                    </article>
                    <article class="release-highlight">
                        <span class="release-highlight-icon"><i class="fas fa-leaf"></i></span>
                        <h3>Ambientação atualizada</h3>
                        <p>O formulário de ambientação foi revisado para refletir a nova estrutura e melhorar a leitura dos dados.</p>
                    </article>
                    <article class="release-highlight">
                        <span class="release-highlight-icon"><i class="fas fa-file-invoice-dollar"></i></span>
                        <h3>Novo fluxo de boleto</h3>
                        <p>Agora o usuário recebe um e-mail com acesso ao boleto, realiza o pagamento e envia o comprovante pela página pública para conferência.</p>
                    </article>
                </div>

                <div class="release-support">
                    <div>
                        <h4>Precisa de ajuda com a atualização?</h4>
                        <p>Se surgir alguma dúvida operacional ou dificuldade de acesso, use os canais oficiais de suporte do sistema.</p>
                    </div>
                    <a href="../suporte.php" class="release-link">
                        <i class="fas fa-life-ring"></i> Abrir suporte
                    </a>
                </div>

                <div class="release-actions">
                    <button type="button" class="release-close" data-bs-dismiss="modal">
                        <i class="fas fa-check"></i> Entendi
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
