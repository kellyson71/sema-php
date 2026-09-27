<?php
require_once 'conexao.php';
require_once __DIR__ . '/../includes/functions.php';

verificaLogin();

// O secretário tem painel dedicado (fila baseada em solicitacoes_assinatura,
// sem denúncias, sem geração de documento) — ele nunca usa este arquivo.
if (($_SESSION['admin_nivel'] ?? '') === 'secretario') {
    header('Location: painel_secretario.php');
    exit;
}

// Restrição de setor para fiscal (setor2)
$nivelAdmin = $_SESSION['admin_nivel'] ?? '';
$setorFiltro = match($nivelAdmin) {
    'fiscal'    => 'setor2',
    default     => null,
};

if ($setorFiltro) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM requerimentos WHERE setor_atual = ?");
    $st->execute([$setorFiltro]);
    $totalRequerimentos = (int) $st->fetchColumn();

    $st = $pdo->prepare("SELECT COUNT(*) FROM requerimentos WHERE setor_atual = ? AND status = 'Em análise'");
    $st->execute([$setorFiltro]);
    $emAnalise = (int) $st->fetchColumn();

    $st = $pdo->prepare("SELECT COUNT(*) FROM requerimentos WHERE setor_atual = ? AND visualizado = 0");
    $st->execute([$setorFiltro]);
    $naoVisualizados = (int) $st->fetchColumn();

    $st = $pdo->prepare("SELECT COUNT(*) FROM requerimentos WHERE setor_atual = ? AND data_envio >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)");
    $st->execute([$setorFiltro]);
    $novosSemana = (int) $st->fetchColumn();
} else {
    $totalRequerimentos = (int) $pdo->query("SELECT COUNT(*) FROM requerimentos")->fetchColumn();
    $emAnalise = (int) $pdo->query("SELECT COUNT(*) FROM requerimentos WHERE status = 'Em análise'")->fetchColumn();
    $naoVisualizados = (int) $pdo->query("SELECT COUNT(*) FROM requerimentos WHERE visualizado = 0")->fetchColumn();
    $novosSemana = (int) $pdo->query("SELECT COUNT(*) FROM requerimentos WHERE data_envio >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)")->fetchColumn();
}

// Contagens para o hub de setores
$hubSetores = [];
foreach (['setor1','setor2','setor3'] as $s) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM requerimentos WHERE setor_atual = ? AND aguardando_acao != 'concluido'");
    $st->execute([$s]);
    $hubSetores[$s] = (int) $st->fetchColumn();
}

$adminIdDash = $_SESSION['admin_id'] ?? 0;
$itensPorPaginaFila = 8;
$totalPaginasFila = max(1, (int) ceil($totalRequerimentos / $itensPorPaginaFila));
$paginaFila = max(1, (int) ($_GET['pagina_fila'] ?? 1));
$paginaFila = min($paginaFila, $totalPaginasFila);
$offsetFila = ($paginaFila - 1) * $itensPorPaginaFila;
if ($setorFiltro) {
    $st = $pdo->prepare("
        SELECT r.id, r.protocolo, r.tipo_alvara, r.status, r.data_envio, r.visualizado,
               req.nome AS requerente,
               (SELECT COUNT(*) FROM historico_acoes ha WHERE ha.requerimento_id = r.id AND ha.admin_id = ?) AS acoes_minhas
        FROM requerimentos r
        JOIN requerentes req ON r.requerente_id = req.id
        WHERE r.setor_atual = ?
        ORDER BY r.visualizado ASC,
                 CASE WHEN r.status IN ('Aprovado', 'Finalizado') THEN 1 ELSE 0 END,
                 r.data_envio DESC
        LIMIT $itensPorPaginaFila OFFSET $offsetFila
    ");
    $st->execute([$adminIdDash, $setorFiltro]);
    $ultimosRequerimentos = $st->fetchAll();
} else {
    $stmt = $pdo->prepare("
        SELECT r.id, r.protocolo, r.tipo_alvara, r.status, r.data_envio, r.visualizado,
               req.nome AS requerente,
               (SELECT COUNT(*) FROM historico_acoes ha WHERE ha.requerimento_id = r.id AND ha.admin_id = ?) AS acoes_minhas
        FROM requerimentos r
        JOIN requerentes req ON r.requerente_id = req.id
        ORDER BY r.visualizado ASC,
                 CASE WHEN r.status IN ('Aprovado', 'Finalizado') THEN 1 ELSE 0 END,
                 r.data_envio DESC
        LIMIT $itensPorPaginaFila OFFSET $offsetFila
    ");
    $stmt->execute([$adminIdDash]);
    $ultimosRequerimentos = $stmt->fetchAll();
}

$stmt = $pdo->prepare("
    SELECT sa.documento_id, sa.requerimento_id, sa.criado_em,
           r.protocolo, req.nome AS requerente_nome, s.nome AS solicitante_nome,
           ad.tipo_documento
    FROM solicitacoes_assinatura sa
    JOIN requerimentos r   ON r.id = sa.requerimento_id
    JOIN requerentes req   ON req.id = r.requerente_id
    JOIN administradores s ON s.id = sa.solicitante_id
    LEFT JOIN assinaturas_digitais ad ON ad.documento_id = sa.documento_id
    WHERE sa.destinatario_id = ? AND sa.status = 'pendente'
    GROUP BY sa.documento_id
    ORDER BY sa.criado_em DESC
    LIMIT 3
");
$stmt->execute([(int) ($_SESSION['admin_id'] ?? 0)]);
$assinaturasPainel = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mantém a área da fila útil mesmo em dias com pouco movimento: os concluídos
// recentes entram apenas como histórico, sem disputar atenção com a fila ativa.
$sqlHistoricoConcluidos = "
    SELECT r.id, r.protocolo, r.tipo_alvara, r.status, r.data_envio, r.data_atualizacao,
           req.nome AS requerente
    FROM requerimentos r
    JOIN requerentes req ON req.id = r.requerente_id
    WHERE r.status IN ('Aprovado', 'Finalizado')
";
$paramsHistoricoConcluidos = [];
if ($setorFiltro) {
    $sqlHistoricoConcluidos .= ' AND r.setor_atual = ?';
    $paramsHistoricoConcluidos[] = $setorFiltro;
}
$sqlHistoricoConcluidos .= ' ORDER BY COALESCE(r.data_atualizacao, r.data_envio) DESC LIMIT 8';
$stmt = $pdo->prepare($sqlHistoricoConcluidos);
$stmt->execute($paramsHistoricoConcluidos);
$historicoConcluidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($setorFiltro) {
    $st = $pdo->prepare("
        SELECT COUNT(*) FROM requerimentos
        WHERE setor_atual = ?
          AND status IN ('Aprovado', 'Finalizado')
          AND data_atualizacao >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
    ");
    $st->execute([$setorFiltro]);
    $concluidosSemana = (int) $st->fetchColumn();
} else {
    $concluidosSemana = (int) $pdo->query("
        SELECT COUNT(*) FROM requerimentos
        WHERE status IN ('Aprovado', 'Finalizado')
          AND data_atualizacao >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
    ")->fetchColumn();
}

include 'header.php';

$saudacao = 'Bom dia';
$horaAtual = (int) date('H');
if ($horaAtual >= 12 && $horaAtual < 18) {
    $saudacao = 'Boa tarde';
} elseif ($horaAtual >= 18) {
    $saudacao = 'Boa noite';
}

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

if ($setorFiltro) {
    $ctaFilaLabel = 'Abrir minha fila';
    $ctaFilaHref  = 'fila_setor.php?setor=' . $setorFiltro;
    $ctaFilaIcon  = 'fa-inbox';
} else {
    $ctaFilaLabel = $naoVisualizados > 0 ? 'Abrir fila não lida' : 'Ver requerimentos';
    $ctaFilaHref  = $naoVisualizados > 0 ? 'requerimentos.php?nao_visualizados=1' : 'requerimentos.php';
    $ctaFilaIcon  = $naoVisualizados > 0 ? 'fa-eye-slash' : 'fa-list';
}

$nomeAdmin = trim((string) ($_SESSION['admin_nome'] ?? ''));
$primeiroNome = preg_split('/\s+/', $nomeAdmin, -1, PREG_SPLIT_NO_EMPTY)[0] ?? $nomeAdmin;
$qtdFilaTela = count($ultimosRequerimentos);
$idsNaFila = array_map(static fn (array $req): int => (int) $req['id'], $ultimosRequerimentos);
$historicoConcluidos = array_values(array_filter(
    $historicoConcluidos,
    static fn (array $req): bool => !in_array((int) $req['id'], $idsNaFila, true)
));
$historicoConcluidos = array_slice($historicoConcluidos, 0, max(0, 4 - $qtdFilaTela));

$tempoFila = static function (?string $data): string {
    if (!$data) {
        return 'sem data registrada';
    }

    try {
        $entrada = new DateTimeImmutable($data);
        $agora = new DateTimeImmutable('now');
        $diff = $entrada->diff($agora);
    } catch (Throwable $e) {
        return date('d/m/Y H:i', strtotime($data));
    }

    if ($diff->days === 0) {
        return 'entrou hoje, ' . $entrada->format('H:i');
    }
    if ($diff->days === 1) {
        return 'entrou ontem';
    }
    return 'entrou há ' . $diff->days . ' dias';
};

$tipoIcones = [
    'setor1' => 'fa-inbox',
    'setor2' => 'fa-helmet-safety',
    'setor3' => 'fa-shield-halved',
];

$barrasSemana = [
    ['dia' => 'seg', 'h' => 26],
    ['dia' => 'ter', 'h' => 38],
    ['dia' => 'qua', 'h' => 32],
    ['dia' => 'qui', 'h' => 18],
    ['dia' => 'sex', 'h' => 44],
    ['dia' => 'sáb', 'h' => 8],
    ['dia' => 'dom', 'h' => 5],
];
?>

<style>
    .home-shell { display:flex; flex-direction:column; gap:16px; max-width:1240px; margin:0 auto; }
    .home-hero { display:flex; align-items:flex-end; justify-content:space-between; gap:20px; flex-wrap:wrap; }
    .home-date { font-size:.76rem; color:var(--muted-2); margin-bottom:4px; text-transform:lowercase; }
    .home-title { margin:0; font-size:1.55rem; font-weight:800; color:var(--ink); line-height:1.12; letter-spacing:0; }
    .home-subtitle { margin:6px 0 0; font-size:.92rem; color:var(--muted); }
    .home-cta { display:inline-flex; align-items:center; gap:9px; min-height:44px; padding:0 20px; border-radius:12px; border:0; background:var(--primary); color:#fff; font-size:.9rem; font-weight:700; }
    .home-cta:hover { background:var(--primary-strong); color:#fff; }
    .home-body { display:flex; align-items:flex-start; gap:16px; }
    .home-main { flex:1; min-width:0; background:#fff; border:1px solid var(--line); border-radius:16px; overflow:hidden; }
    .home-panel-head { display:flex; align-items:center; gap:10px; flex-wrap:wrap; padding:14px 18px; border-bottom:1px solid #eef2ef; }
    .home-panel-title { font-size:.95rem; font-weight:700; color:var(--ink); }
    .home-filters { display:flex; gap:4px; flex-wrap:wrap; margin-left:6px; }
    .home-filter { display:inline-flex; align-items:center; gap:6px; padding:6px 11px; border:1px solid var(--line); border-radius:9px; background:#fff; color:var(--muted); font-size:.79rem; font-weight:600; }
    .home-filter.active { background:var(--primary); border-color:var(--primary); color:#fff; }
    .home-filter strong { min-width:18px; padding:1px 6px; border-radius:999px; background:#f1f5f2; color:var(--muted-2); font-size:.7rem; font-weight:700; text-align:center; }
    .home-filter.active strong { background:rgba(255,255,255,.2); color:#fff; }
    .home-all { margin-left:auto; font-size:.8rem; font-weight:600; color:var(--primary); }
    .home-row { display:flex; align-items:center; gap:14px; padding:14px 18px; border-bottom:1px solid #f4f7f5; color:inherit; }
    .home-row:hover { background:#fbfcfb; color:inherit; }
    .home-row-bar { width:3px; height:38px; border-radius:3px; flex-shrink:0; background:#cfdad3; }
    .home-row-unread .home-row-bar { background:#b7791f; }
    .home-row-stalled .home-row-bar { background:#b13232; }
    .home-row-main { flex:1; min-width:0; }
    .home-row-top { display:flex; align-items:center; gap:9px; flex-wrap:wrap; }
    .home-protocol { font-family:'Geist Mono',ui-monospace,monospace; font-size:.82rem; font-weight:500; color:var(--ink); }
    .home-badge { display:inline-flex; align-items:center; padding:2px 8px; border-radius:999px; background:#eef3f0; color:#3d5c46; font-size:.66rem; font-weight:800; }
    .home-badge.unread { background:#fdf3e0; color:#b7791f; }
    .home-name { margin-top:3px; font-size:.92rem; font-weight:600; color:var(--ink); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .home-meta { margin-top:2px; font-size:.78rem; color:var(--muted-2); }
    .home-action { font-size:.79rem; font-weight:700; color:var(--primary); white-space:nowrap; flex-shrink:0; }
    .home-arrow { width:34px; height:34px; border:1px solid var(--line); border-radius:10px; background:#fff; color:var(--muted); display:inline-flex; align-items:center; justify-content:center; flex-shrink:0; }
    .home-row:hover .home-arrow { border-color:var(--primary); color:var(--primary); }
    .home-queue-divider { display:flex; align-items:center; gap:8px; padding:11px 18px 6px; background:#fbfcfb; border-top:1px solid #e8eeea; color:var(--muted-2); font-size:.66rem; font-weight:800; letter-spacing:.09em; text-transform:uppercase; }
    .home-queue-divider::after { content:""; height:1px; flex:1; background:#e1e9e4; }
    .home-queue-divider.unread { color:#a76611; background:#fffdfa; }
    .home-queue-divider.finished { color:#718078; }
    .home-footnote { padding:12px 18px; font-size:.8rem; color:var(--muted-2); }
    .home-pagination { display:flex; align-items:center; justify-content:flex-end; gap:8px; padding:0 18px 14px; color:var(--muted-2); font-size:.76rem; }
    .home-pagination-link { display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border:1px solid var(--line); border-radius:8px; color:var(--primary); background:#fff; }
    .home-pagination-link:hover { border-color:var(--primary); color:var(--primary); }
    .home-empty { padding:34px 18px; text-align:center; color:var(--muted); font-size:.9rem; }
    .home-history { border-top:1px solid #eef2ef; background:#fbfcfb; }
    .home-history-title { display:block; padding:10px 18px 3px; color:var(--muted-2); font-size:.68rem; font-weight:800; letter-spacing:.08em; text-transform:uppercase; }
    .home-row.home-row-history { padding-block:10px; border-bottom:0; background:transparent; }
    .home-row.home-row-history:hover { background:#f6f8f6; }
    .home-row-history .home-row-bar { width:2px; height:28px; background:#d7e0da; }
    .home-row-history .home-name { font-size:.84rem; font-weight:500; color:var(--muted); }
    .home-row-history .home-badge { background:#f1f4f2; color:#718078; }
    .home-rail { width:326px; flex:none; display:flex; flex-direction:column; gap:12px; }
    .rail-card { background:#fff; border:1px solid var(--line); border-radius:16px; overflow:hidden; }
    .rail-head { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:12px 15px; border-bottom:1px solid #eef2ef; }
    .rail-title { font-size:.68rem; font-weight:800; letter-spacing:.1em; text-transform:uppercase; color:#3d5c46; }
    .rail-count { font-size:.7rem; font-weight:800; padding:2px 8px; border-radius:999px; background:#fdf3e0; color:#b7791f; }
    .rail-item { display:flex; align-items:center; gap:11px; padding:12px 15px; border-bottom:1px solid #f4f7f5; color:inherit; }
    .rail-item:hover { background:#fbfcfb; color:inherit; }
    .rail-item i { color:#5a8a6a; width:16px; font-size:.85rem; text-align:center; }
    .rail-copy { flex:1; min-width:0; }
    .rail-name { display:block; font-size:.84rem; font-weight:600; color:var(--ink); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .rail-meta { display:block; margin-top:1px; font-size:.72rem; color:var(--muted-2); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .rail-num { font-size:.86rem; font-weight:800; color:var(--muted-2); }
    .rail-num.active { color:var(--primary); }
    .rail-link { display:inline-flex; align-items:center; gap:6px; padding:11px 15px; font-size:.79rem; font-weight:600; color:var(--primary); }
    .week-card { padding:15px; }
    .week-stats { display:flex; gap:18px; }
    .week-value { font-size:1.25rem; font-weight:800; color:var(--ink); line-height:1; background:transparent; }
    .week-label { margin-top:3px; font-size:.74rem; color:var(--muted-2); }
    .week-bars { display:flex; align-items:flex-end; gap:5px; height:44px; margin-top:14px; }
    .week-bar { flex:1; border-radius:4px 4px 2px 2px; background:#dbe7e0; }
    .week-bar.active { background:var(--primary); }
    .week-days { display:flex; justify-content:space-between; margin-top:6px; font-size:.68rem; color:#c0cbc4; }
    @media (max-width:1199px) { .home-body { flex-direction:column; } .home-main, .home-rail { width:100%; } }
    @media (max-width:767px) { .home-shell { gap:14px; } .home-title { font-size:1.35rem; } .home-panel-head { align-items:flex-start; } .home-all { margin-left:0; } .home-row { align-items:flex-start; } .home-action { display:none; } .week-stats { justify-content:space-between; } }

</style>

<div class="home-shell">
    <section class="home-hero">
        <div>
            <div class="home-date"><?= htmlspecialchars($dataPainelLabel) ?></div>
            <h2 class="home-title"><?= htmlspecialchars($saudacao) ?>, <?= htmlspecialchars($primeiroNome) ?></h2>
            <p class="home-subtitle">
                <?= (int) $naoVisualizados ?> processos novos esperam triagem e <?= (int) $emAnalise ?> estão em análise.
            </p>
        </div>
        <a href="<?= htmlspecialchars($ctaFilaHref) ?>" class="home-cta">
            <i class="fas <?= htmlspecialchars($ctaFilaIcon) ?>"></i><?= htmlspecialchars($ctaFilaLabel) ?>
        </a>
    </section>

    <section class="home-body">
        <div class="home-main">
            <div class="home-panel-head">
                <span class="home-panel-title">Sua fila</span>
                <div class="home-filters" aria-label="Resumo da fila">
                    <a href="requerimentos.php?nao_visualizados=1" class="home-filter active">Não vistos <strong><?= (int) $naoVisualizados ?></strong></a>
                    <a href="requerimentos.php?status=Em+an%C3%A1lise" class="home-filter">Em andamento <strong><?= (int) $emAnalise ?></strong></a>
                    <a href="requerimentos.php" class="home-filter">Tudo <strong><?= (int) $totalRequerimentos ?></strong></a>
                </div>
                <a href="requerimentos.php" class="home-all">Ver todos</a>
            </div>

            <?php if ($ultimosRequerimentos): ?>
                <?php $grupoFilaAnterior = null; ?>
                <?php foreach ($ultimosRequerimentos as $req): ?>
                    <?php
                    $naoVisto = !(int) $req['visualizado'];
                    $concluido = in_array($req['status'], ['Aprovado', 'Finalizado'], true);
                    $grupoFila = $naoVisto ? 'unread' : ($concluido ? 'finished' : 'other');
                    $grupoFilaLabel = [
                        'unread' => 'Não lidos',
                        'other' => 'Em acompanhamento',
                        'finished' => 'Concluídos',
                    ][$grupoFila];
                    $diasFila = 0;
                    try {
                        $diasFila = (new DateTimeImmutable((string) $req['data_envio']))->diff(new DateTimeImmutable('now'))->days;
                    } catch (Throwable $e) {
                        $diasFila = 0;
                    }
                    $parado = !$naoVisto && $diasFila >= 5;
                    $statusLabel = $naoVisto ? 'NÃO VISTO' : (($req['status'] === 'Em análise') ? 'EM ANDAMENTO' : mb_strtoupper((string) $req['status']));
                    $acaoLabel = $naoVisto ? 'Abrir e triar' : (($req['status'] === 'Aguardando boleto') ? 'Cobrar boleto' : 'Abrir processo');
                    ?>
                    <?php if ($grupoFila !== $grupoFilaAnterior): ?>
                        <div class="home-queue-divider <?= $grupoFila ?>"><?= $grupoFilaLabel ?></div>
                        <?php $grupoFilaAnterior = $grupoFila; ?>
                    <?php endif; ?>
                    <a href="visualizar_requerimento.php?id=<?= (int) $req['id'] ?>" class="home-row<?= $naoVisto ? ' home-row-unread' : '' ?><?= $parado ? ' home-row-stalled' : '' ?>">
                        <span class="home-row-bar"></span>
                        <span class="home-row-main">
                            <span class="home-row-top">
                                <span class="home-protocol"><?= htmlspecialchars($req['protocolo']) ?></span>
                                <span class="home-badge<?= $naoVisto ? ' unread' : '' ?>"><?= htmlspecialchars($statusLabel) ?></span>
                            </span>
                            <span class="home-name"><?= htmlspecialchars($req['requerente']) ?></span>
                            <span class="home-meta"><?= htmlspecialchars(nomeAlvara($req['tipo_alvara'])) ?> · <?= htmlspecialchars($tempoFila($req['data_envio'])) ?></span>
                        </span>
                        <span class="home-action"><?= htmlspecialchars($acaoLabel) ?></span>
                        <span class="home-arrow" aria-hidden="true"><i class="fas fa-arrow-right"></i></span>
                    </a>
                <?php endforeach; ?>
                <div class="home-footnote">Mostrando <?= (int) $qtdFilaTela ?> de <?= (int) $totalRequerimentos ?> processos na sua responsabilidade.</div>
                <?php if ($totalPaginasFila > 1): ?>
                    <nav class="home-pagination" aria-label="Paginação da fila">
                        <?php if ($paginaFila > 1): ?>
                            <a class="home-pagination-link" href="index.php?pagina_fila=<?= $paginaFila - 1 ?>" aria-label="Página anterior"><i class="fas fa-chevron-left"></i></a>
                        <?php endif; ?>
                        <span>Página <?= $paginaFila ?> de <?= $totalPaginasFila ?></span>
                        <?php if ($paginaFila < $totalPaginasFila): ?>
                            <a class="home-pagination-link" href="index.php?pagina_fila=<?= $paginaFila + 1 ?>" aria-label="Próxima página"><i class="fas fa-chevron-right"></i></a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php else: ?>
                <div class="home-empty">Nenhum processo encontrado para compor a fila operacional.</div>
            <?php endif; ?>

            <?php if ($historicoConcluidos): ?>
                <div class="home-history">
                    <span class="home-history-title">Últimos concluídos</span>
                    <?php foreach ($historicoConcluidos as $req): ?>
                        <a href="visualizar_requerimento.php?id=<?= (int) $req['id'] ?>" class="home-row home-row-history">
                            <span class="home-row-bar"></span>
                            <span class="home-row-main">
                                <span class="home-row-top">
                                    <span class="home-protocol"><?= htmlspecialchars($req['protocolo']) ?></span>
                                    <span class="home-badge"><?= htmlspecialchars(mb_strtoupper((string) $req['status'])) ?></span>
                                </span>
                                <span class="home-name"><?= htmlspecialchars($req['requerente']) ?></span>
                                <span class="home-meta"><?= htmlspecialchars(nomeAlvara($req['tipo_alvara'])) ?> · concluído em <?= htmlspecialchars(date('d/m/Y', strtotime($req['data_atualizacao'] ?: $req['data_envio']))) ?></span>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <aside class="home-rail">
            <?php if ($assinaturasPainel): ?>
            <div class="rail-card">
                <div class="rail-head">
                    <span class="rail-title">Aguardando sua assinatura</span>
                    <span class="rail-count"><?= count($assinaturasPainel) ?></span>
                </div>
                <?php foreach ($assinaturasPainel as $assinatura): ?>
                    <?php $tipoDoc = ucfirst(str_replace('_', ' ', (string) ($assinatura['tipo_documento'] ?? 'documento'))); ?>
                    <a href="coassinar_documento.php?documento_id=<?= urlencode($assinatura['documento_id']) ?>" class="rail-item">
                        <i class="fas fa-file-signature" style="color:#b7791f"></i>
                        <span class="rail-copy">
                            <span class="rail-name"><?= htmlspecialchars($tipoDoc) ?> - <?= htmlspecialchars($assinatura['protocolo']) ?></span>
                            <span class="rail-meta">Solicitado por <?= htmlspecialchars($assinatura['solicitante_nome']) ?> · <?= date('d/m/Y H:i', strtotime($assinatura['criado_em'])) ?></span>
                        </span>
                    </a>
                <?php endforeach; ?>
                <a href="minhas_assinaturas.php" class="rail-link">Abrir para assinar <i class="fas fa-chevron-right"></i></a>
            </div>
            <?php endif; ?>

            <div class="rail-card">
                <div class="rail-head">
                    <span class="rail-title">Filas por setor</span>
                </div>
                <?php
                $hubMeta = [
                    'setor1' => ['label' => 'Triagem Ambiental', 'sub' => 'Setor 1'],
                    'setor2' => ['label' => 'Fiscalização de Obras', 'sub' => 'Setor 2'],
                    'setor3' => ['label' => 'Revisão do Secretário', 'sub' => 'Setor 3'],
                ];
                foreach ($hubMeta as $s => $hm):
                    $n = (int) $hubSetores[$s];
                    $ativo = $setorFiltro === $s;
                ?>
                    <a href="fila_setor.php?setor=<?= htmlspecialchars($s) ?>" class="rail-item">
                        <i class="fas <?= htmlspecialchars($tipoIcones[$s] ?? 'fa-inbox') ?>"></i>
                        <span class="rail-copy">
                            <span class="rail-name"><?= htmlspecialchars($hm['label']) ?></span>
                            <span class="rail-meta"><?= htmlspecialchars($hm['sub']) ?><?= $ativo ? ' · sua fila' : '' ?></span>
                        </span>
                        <span class="rail-num<?= $ativo ? ' active' : '' ?>"><?= $n ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="rail-card week-card">
                <div class="rail-title" style="margin-bottom:12px">Esta semana</div>
                <div class="week-stats">
                    <div>
                        <div class="week-value"><?= (int) $novosSemana ?></div>
                        <div class="week-label">entradas</div>
                    </div>
                    <div>
                        <div class="week-value"><?= (int) $concluidosSemana ?></div>
                        <div class="week-label">concluídos</div>
                    </div>
                    <div>
                        <div class="week-value"><?= (int) $emAnalise ?></div>
                        <div class="week-label">em análise</div>
                    </div>
                </div>
                <div class="week-bars" aria-hidden="true">
                    <?php foreach ($barrasSemana as $i => $barra): ?>
                        <span class="week-bar<?= $i === 4 ? ' active' : '' ?>" style="height:<?= (int) $barra['h'] ?>px" title="<?= htmlspecialchars($barra['dia']) ?>"></span>
                    <?php endforeach; ?>
                </div>
                <div class="week-days"><span>seg</span><span>ter</span><span>qua</span><span>qui</span><span>sex</span><span>sáb</span><span>dom</span></div>
            </div>
        </aside>
    </section>
</div>

<?php include 'footer.php'; ?>
