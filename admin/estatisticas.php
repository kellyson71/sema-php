<?php
require_once 'conexao.php';
require_once 'helpers.php';
verificaLogin();

$stmt = $pdo->query("
    SELECT
        DATE_FORMAT(data_envio, '%Y-%m') AS mes,
        COUNT(*) AS total
    FROM requerimentos
    WHERE data_envio >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(data_envio, '%Y-%m')
    ORDER BY mes
");
$requerimentosPorMes = $stmt->fetchAll();

$stmt = $pdo->query("
    SELECT
        status,
        COUNT(*) AS total
    FROM requerimentos
    GROUP BY status
    ORDER BY total DESC
");
$requerimentosPorStatus = $stmt->fetchAll();

$stmt = $pdo->query("SELECT COUNT(*) AS total FROM requerimentos");
$totalRequerimentos = (int) ($stmt->fetch()['total'] ?? 0);

$stmt = $pdo->query("
    SELECT
        COUNT(*) AS total,
        COUNT(DISTINCT DATE(data_envio)) AS dias
    FROM requerimentos
    WHERE data_envio >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
");
$dadosMedia = $stmt->fetch();
$mediaPorDia = ($dadosMedia['dias'] ?? 0) > 0 ? round($dadosMedia['total'] / $dadosMedia['dias'], 1) : 0;

$stmt = $pdo->query("
    SELECT
        r.id,
        r.protocolo,
        r.data_envio,
        r.status AS status_atual,
        MIN(CASE WHEN ha.acao LIKE '%primeira vez%' THEN ha.data_acao END) AS data_visualizacao,
        MIN(CASE WHEN ha.acao LIKE '%status para%Pendente%' THEN ha.data_acao END) AS data_pendente,
        MIN(CASE WHEN ha.acao LIKE '%Fiscalização%' THEN ha.data_acao END) AS data_fiscalizacao,
        MIN(CASE WHEN ha.acao LIKE '%Secretário%' OR ha.acao LIKE '%vistoria técnica%' THEN ha.data_acao END) AS data_secretario,
        MIN(CASE WHEN ha.acao LIKE '%Assinou o Alvará%' OR ha.acao LIKE '%Finalizado%' OR ha.acao LIKE '%Indeferido%' THEN ha.data_acao END) AS data_conclusao
    FROM requerimentos r
    LEFT JOIN historico_acoes ha ON r.id = ha.requerimento_id
    GROUP BY r.id
");
$temposRequerimentos = $stmt->fetchAll();

$somaResposta = 0; $qtdResposta = 0;
$somaTriagem = 0; $qtdTriagem = 0;
$somaAnaliseFiscal = 0; $qtdAnaliseFiscal = 0;
$somaFiscalSecretario = 0; $qtdFiscalSecretario = 0;
$somaTempoTotal = 0; $qtdTempoTotal = 0;
$processosTempoTotal = [];

foreach ($temposRequerimentos as $req) {
    if (!$req['data_envio']) {
        continue;
    }

    $tEnvio = strtotime($req['data_envio']);
    $tVisualizacao = $req['data_visualizacao'] ? strtotime($req['data_visualizacao']) : null;
    $tPendente = $req['data_pendente'] ? strtotime($req['data_pendente']) : null;
    $tFiscalizacao = $req['data_fiscalizacao'] ? strtotime($req['data_fiscalizacao']) : null;
    $tSecretario = $req['data_secretario'] ? strtotime($req['data_secretario']) : null;
    $tConclusao = $req['data_conclusao'] ? strtotime($req['data_conclusao']) : null;

    if ($tVisualizacao && $tVisualizacao >= $tEnvio) {
        $somaResposta += ($tVisualizacao - $tEnvio);
        $qtdResposta++;
    }

    if ($tPendente && $tPendente >= $tEnvio) {
        $somaTriagem += ($tPendente - $tEnvio);
        $qtdTriagem++;
    }

    if ($tFiscalizacao) {
        $inicio = $tPendente ?? $tVisualizacao ?? $tEnvio;
        if ($tFiscalizacao >= $inicio) {
            $somaAnaliseFiscal += ($tFiscalizacao - $inicio);
            $qtdAnaliseFiscal++;
        }
    }

    if ($tSecretario) {
        $inicio = $tFiscalizacao ?? $tPendente ?? $tVisualizacao ?? $tEnvio;
        if ($tSecretario >= $inicio) {
            $somaFiscalSecretario += ($tSecretario - $inicio);
            $qtdFiscalSecretario++;
        }
    }

    if ($tConclusao && $tConclusao >= $tEnvio) {
        $tempoTotal = $tConclusao - $tEnvio;
        $somaTempoTotal += $tempoTotal;
        $qtdTempoTotal++;
        $processosTempoTotal[] = ['protocolo' => $req['protocolo'], 'tempo' => $tempoTotal, 'id' => $req['id']];
    }
}

usort($processosTempoTotal, function ($a, $b) {
    return $a['tempo'] <=> $b['tempo'];
});

$topRapidos = array_slice($processosTempoTotal, 0, 5);
$topLentos = array_slice(array_reverse($processosTempoTotal), 0, 5);

$mediaResposta = $qtdResposta > 0 ? $somaResposta / $qtdResposta : 0;
$mediaTriagem = $qtdTriagem > 0 ? $somaTriagem / $qtdTriagem : 0;
$mediaAnaliseFiscal = $qtdAnaliseFiscal > 0 ? $somaAnaliseFiscal / $qtdAnaliseFiscal : 0;
$mediaFiscalSecretario = $qtdFiscalSecretario > 0 ? $somaFiscalSecretario / $qtdFiscalSecretario : 0;
$mediaTempoTotal = $qtdTempoTotal > 0 ? $somaTempoTotal / $qtdTempoTotal : 0;

$statusSucesso = ['Aprovado', 'Finalizado'];
$statusFalha = ['Reprovado', 'Indeferido', 'Cancelado'];
$totalAprovados = 0;
$totalRejeitados = 0;

foreach ($requerimentosPorStatus as $status) {
    if (in_array($status['status'], $statusSucesso, true)) {
        $totalAprovados += $status['total'];
    } elseif (in_array($status['status'], $statusFalha, true)) {
        $totalRejeitados += $status['total'];
    }
}

$totalResolvidos = $totalAprovados + $totalRejeitados;
$taxaAprovacao = $totalResolvidos > 0 ? round(($totalAprovados / $totalResolvidos) * 100, 1) : 0;

include 'header.php';
?>
<style>
    .stats-shell { max-width: 1240px; margin: 0 auto; display: flex; flex-direction: column; gap: 18px; }
    .stats-grid, .time-grid, .chart-grid, .ranking-grid { display: grid; gap: 16px; }
    .stats-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .time-grid { grid-template-columns: repeat(5, minmax(0, 1fr)); }
    .chart-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .ranking-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .stats-card, .stats-block { background: #fff; border: 1px solid var(--line); border-radius: 20px; box-shadow: var(--card-shadow); }
    .stats-card { padding: 22px; }
    .stats-label { display: block; margin-bottom: 6px; font-size: .76rem; color: var(--muted); font-weight: 700; text-transform: uppercase; letter-spacing: .08em; }
    .stats-value { display: block; margin-bottom: 6px; font-size: 1.85rem; font-weight: 800; color: var(--ink); line-height: 1; }
    .stats-note { display: flex; align-items: center; gap: 8px; color: var(--muted); font-size: .82rem; }
    .time-card { padding: 18px; }
    .time-title { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; color: var(--muted); font-size: .75rem; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; }
    .time-value { font-size: 1.25rem; font-weight: 800; color: var(--ink); }
    .time-copy { margin-top: 4px; color: var(--muted); font-size: .8rem; line-height: 1.4; }
    .stats-section-head { display: flex; align-items: end; justify-content: space-between; gap: 12px; margin-top: 6px; }
    .stats-section-head h2 { margin: 0; font-size: 1.12rem; font-weight: 800; color: var(--ink); }
    .stats-section-head p { margin: 0; color: var(--muted); font-size: .84rem; }
    .stats-block { overflow: hidden; }
    .stats-block-head { padding: 18px 18px 0; }
    .stats-block-head h3 { margin: 0 0 4px; font-size: 1rem; font-weight: 800; color: var(--ink); }
    .stats-block-head p { margin: 0; color: var(--muted); font-size: .8rem; }
    .stats-chart-body { padding: 12px 18px 18px; }
    .stats-chart-wrap { position: relative; height: 260px; width: 100%; }
    .ranking-table { width: 100%; border-collapse: separate; border-spacing: 0; }
    .ranking-table th { padding: 14px 18px; background: var(--surface-soft); color: var(--muted); font-size: .76rem; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; border-bottom: 1px solid var(--line); }
    .ranking-table td { padding: 14px 18px; border-bottom: 1px solid #edf2ee; vertical-align: middle; }
    .ranking-table tr:last-child td { border-bottom: 0; }
    .ranking-link { color: var(--primary); font-weight: 700; }
    .ranking-muted { color: var(--muted); font-size: .82rem; }
    .ranking-empty { padding: 28px 18px; text-align: center; color: var(--muted); }
    @media (max-width: 1199px) { .stats-grid, .time-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .chart-grid, .ranking-grid { grid-template-columns: 1fr; } }
    @media (max-width: 767px) { .stats-grid, .time-grid { grid-template-columns: 1fr; } }
</style>

<div class="stats-shell">
    <section class="page-hero page-hero-compact">
        <div class="page-hero-copy">
            <h1 class="page-title">Estatísticas</h1>
            <p class="page-subtitle">Visão executiva do volume, desfecho e tempo de tramitação dos processos.</p>
        </div>
    </section>

    <section class="stats-grid">
        <article class="stats-card">
            <span class="stats-label">Total de Processos</span>
            <strong class="stats-value"><?= $totalRequerimentos ?></strong>
            <span class="stats-note"><i class="fas fa-clipboard-list"></i> base completa do painel</span>
        </article>
        <article class="stats-card">
            <span class="stats-label">Média por Dia</span>
            <strong class="stats-value"><?= $mediaPorDia ?></strong>
            <span class="stats-note"><i class="fas fa-calendar-week"></i> últimos 30 dias</span>
        </article>
        <article class="stats-card">
            <span class="stats-label">Taxa de Aprovação</span>
            <strong class="stats-value"><?= $taxaAprovacao ?>%</strong>
            <span class="stats-note"><i class="fas fa-check-circle"></i> <?= $totalAprovados ?> de <?= $totalResolvidos ?> resolvidos</span>
        </article>
        <article class="stats-card">
            <span class="stats-label">Taxa de Rejeição</span>
            <strong class="stats-value"><?= $totalResolvidos > 0 ? round(($totalRejeitados / $totalResolvidos) * 100, 1) : 0 ?>%</strong>
            <span class="stats-note"><i class="fas fa-ban"></i> rejeitados ou cancelados</span>
        </article>
    </section>

    <div class="stats-section-head">
        <div>
            <h2>Tempo médio por etapa</h2>
            <p>Todas as métricas de fluxo mantidas, com leitura mais direta.</p>
        </div>
    </div>

    <section class="time-grid">
        <article class="stats-card time-card">
            <div class="time-title"><i class="fas fa-eye"></i> Resposta</div>
            <div class="time-value"><?= formatarTempoEstatisticas($mediaResposta) ?></div>
            <div class="time-copy">envio → 1ª visualização</div>
        </article>
        <article class="stats-card time-card">
            <div class="time-title"><i class="fas fa-inbox"></i> Triagem</div>
            <div class="time-value"><?= formatarTempoEstatisticas($mediaTriagem) ?></div>
            <div class="time-copy">em análise → pendente</div>
        </article>
        <article class="stats-card time-card">
            <div class="time-title"><i class="fas fa-list-check"></i> Encaminhamentos legados</div>
            <div class="time-value"><?= formatarTempoEstatisticas($mediaAnaliseFiscal) ?></div>
            <div class="time-copy">média histórica até etapas extras desativadas</div>
        </article>
        <article class="stats-card time-card">
            <div class="time-title"><i class="fas fa-box-archive"></i> Fluxo complementar</div>
            <div class="time-value"><?= formatarTempoEstatisticas($mediaFiscalSecretario) ?></div>
            <div class="time-copy">tempo histórico entre etapas extras</div>
        </article>
        <article class="stats-card time-card">
            <div class="time-title"><i class="fas fa-clock"></i> Tempo total</div>
            <div class="time-value"><?= formatarTempoEstatisticas($mediaTempoTotal) ?></div>
            <div class="time-copy">envio → conclusão</div>
        </article>
    </section>

    <section class="ranking-grid">
        <div class="stats-block">
            <div class="stats-block-head">
                <h3>Processos mais rápidos</h3>
                <p>Conclusões com menor tempo total de tramitação.</p>
            </div>
            <?php if ($topRapidos): ?>
                <table class="ranking-table">
                    <thead>
                        <tr>
                            <th>Protocolo</th>
                            <th>Tempo</th>
                            <th class="text-end">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topRapidos as $proc): ?>
                            <tr>
                                <td><span class="fw-bold"><?= htmlspecialchars($proc['protocolo']) ?></span></td>
                                <td><span class="ranking-muted"><?= formatarTempoEstatisticas($proc['tempo']) ?></span></td>
                                <td class="text-end"><a href="visualizar_requerimento.php?id=<?= (int) $proc['id'] ?>" class="ranking-link">Abrir</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="ranking-empty">Nenhum processo concluído ainda.</div>
            <?php endif; ?>
        </div>

        <div class="stats-block">
            <div class="stats-block-head">
                <h3>Processos mais demorados</h3>
                <p>Casos que consumiram mais tempo até o desfecho.</p>
            </div>
            <?php if ($topLentos): ?>
                <table class="ranking-table">
                    <thead>
                        <tr>
                            <th>Protocolo</th>
                            <th>Tempo</th>
                            <th class="text-end">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topLentos as $proc): ?>
                            <tr>
                                <td><span class="fw-bold"><?= htmlspecialchars($proc['protocolo']) ?></span></td>
                                <td><span class="ranking-muted"><?= formatarTempoEstatisticas($proc['tempo']) ?></span></td>
                                <td class="text-end"><a href="visualizar_requerimento.php?id=<?= (int) $proc['id'] ?>" class="ranking-link">Abrir</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="ranking-empty">Nenhum processo concluído ainda.</div>
            <?php endif; ?>
        </div>
    </section>

    <div class="stats-section-head">
        <div>
            <h2>Gráficos essenciais</h2>
            <p>Apenas volume por mês e distribuição por status.</p>
        </div>
    </div>

    <section class="chart-grid">
        <div class="stats-block">
            <div class="stats-block-head">
                <h3>Requerimentos por mês</h3>
                <p>Entrada de processos nos últimos 6 meses.</p>
            </div>
            <div class="stats-chart-body">
                <div class="stats-chart-wrap">
                    <canvas id="requerimentosPorMes"></canvas>
                </div>
            </div>
        </div>

        <div class="stats-block">
            <div class="stats-block-head">
                <h3>Requerimentos por status</h3>
                <p>Foto atual da distribuição operacional.</p>
            </div>
            <div class="stats-chart-body">
                <div class="stats-chart-wrap">
                    <canvas id="requerimentosPorStatus"></canvas>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const mesesLabels = [];
        const mesesDados = [];

        <?php foreach ($requerimentosPorMes as $item): ?>
            mesesLabels.push('<?php echo date("M/Y", strtotime($item['mes'] . "-01")); ?>');
            mesesDados.push(<?php echo $item['total']; ?>);
        <?php endforeach; ?>

        new Chart(document.getElementById('requerimentosPorMes'), {
            type: 'line',
            data: {
                labels: mesesLabels,
                datasets: [{
                    data: mesesDados,
                    fill: true,
                    borderColor: '#14532d',
                    backgroundColor: 'rgba(20, 83, 45, 0.08)',
                    pointBackgroundColor: '#14532d',
                    pointRadius: 4,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } },
                    x: { grid: { display: false } }
                }
            }
        });

        const coresStatus = {
            'Em análise': 'rgba(245, 158, 11, 0.8)',
            'Aprovado': 'rgba(20, 83, 45, 0.8)',
            'Reprovado': 'rgba(225, 29, 72, 0.8)',
            'Pendente': 'rgba(55, 98, 217, 0.8)',
            'Cancelado': 'rgba(100, 116, 139, 0.8)',
            'Finalizado': 'rgba(13, 84, 51, 0.8)',
            'Indeferido': 'rgba(71, 85, 105, 0.8)',
            'Aguardando boleto': 'rgba(180, 83, 9, 0.8)',
            'Boleto pago': 'rgba(15, 118, 110, 0.8)'
        };

        const statusLabels = [];
        const statusDados = [];
        const statusCores = [];

        <?php foreach ($requerimentosPorStatus as $item): ?>
            statusLabels.push('<?php echo $item['status']; ?>');
            statusDados.push(<?php echo $item['total']; ?>);
            statusCores.push(coresStatus['<?php echo $item['status']; ?>'] || 'rgba(20, 83, 45, 0.8)');
        <?php endforeach; ?>

        new Chart(document.getElementById('requerimentosPorStatus'), {
            type: 'bar',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusDados,
                    backgroundColor: statusCores,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } },
                    x: { grid: { display: false }, ticks: { maxRotation: 38, minRotation: 38, font: { size: 10 } } }
                }
            }
        });
    });
</script>

<?php include 'footer.php'; ?>
