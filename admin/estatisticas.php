<?php
require_once 'conexao.php';
verificaLogin();

// Gráfico de requerimentos dos últimos 6 meses
$stmt = $pdo->query("
    SELECT 
        DATE_FORMAT(data_envio, '%Y-%m') as mes,
        COUNT(*) as total
    FROM requerimentos
    WHERE data_envio >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(data_envio, '%Y-%m')
    ORDER BY mes
");
$requerimentosPorMes = $stmt->fetchAll();

// Gráfico de requerimentos por tipo de alvará
$stmt = $pdo->query("
    SELECT 
        tipo_alvara,
        COUNT(*) as total
    FROM requerimentos
    GROUP BY tipo_alvara
    ORDER BY total DESC
    LIMIT 5
");
$requerimentosPorTipo = $stmt->fetchAll();

// Gráfico de requerimentos por status
$stmt = $pdo->query("
    SELECT 
        status,
        COUNT(*) as total
    FROM requerimentos
    GROUP BY status
    ORDER BY total DESC
");
$requerimentosPorStatus = $stmt->fetchAll();

// Total de requerimentos
$stmt = $pdo->query("SELECT COUNT(*) as total FROM requerimentos");
$totalRequerimentos = $stmt->fetch()['total'];

// Média de requerimentos por dia nos últimos 30 dias
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total,
        COUNT(DISTINCT DATE(data_envio)) as dias
    FROM requerimentos
    WHERE data_envio >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
");
$dadosMedia = $stmt->fetch();
$mediaPorDia = $dadosMedia['dias'] > 0 ? round($dadosMedia['total'] / $dadosMedia['dias'], 1) : 0;

// Novas Métricas de Tempo
$stmt = $pdo->query("
    SELECT 
        r.id,
        r.protocolo,
        r.data_envio,
        r.status as status_atual,
        MIN(CASE WHEN ha.acao LIKE '%Em análise%' THEN ha.data_acao END) as data_analise,
        MIN(CASE WHEN ha.acao LIKE '%Aprovado%' THEN ha.data_acao END) as data_aprovado,
        MIN(CASE WHEN ha.acao LIKE '%Finalizado%' OR ha.acao LIKE '%Indeferido%' THEN ha.data_acao END) as data_conclusao
    FROM requerimentos r
    LEFT JOIN historico_acoes ha ON r.id = ha.requerimento_id
    GROUP BY r.id
");
$temposRequerimentos = $stmt->fetchAll();

$somaEsperaAnalise = 0; $qtdEsperaAnalise = 0;
$somaAnaliseAprovacao = 0; $qtdAnaliseAprovacao = 0;
$somaAprovacaoConclusao = 0; $qtdAprovacaoConclusao = 0;
$somaTempoTotal = 0; $qtdTempoTotal = 0;

$processosTempoTotal = [];

foreach ($temposRequerimentos as $req) {
    if (!$req['data_envio']) continue;
    $tEnvio = strtotime($req['data_envio']);
    $tAnalise = $req['data_analise'] ? strtotime($req['data_analise']) : null;
    $tAprovado = $req['data_aprovado'] ? strtotime($req['data_aprovado']) : null;
    $tConclusao = $req['data_conclusao'] ? strtotime($req['data_conclusao']) : null;

    if ($tAnalise && $tAnalise >= $tEnvio) {
        $somaEsperaAnalise += ($tAnalise - $tEnvio);
        $qtdEsperaAnalise++;
    }
    
    if ($tAprovado) {
        $inicioAprovacao = $tAnalise ? $tAnalise : $tEnvio;
        if ($tAprovado >= $inicioAprovacao) {
            $somaAnaliseAprovacao += ($tAprovado - $inicioAprovacao);
            $qtdAnaliseAprovacao++;
        }
    }
    
    if ($tConclusao && $tAprovado && $tConclusao >= $tAprovado) {
        $somaAprovacaoConclusao += ($tConclusao - $tAprovado);
        $qtdAprovacaoConclusao++;
    }
    
    if ($tConclusao && $tConclusao >= $tEnvio) {
        $tempoTotal = $tConclusao - $tEnvio;
        $somaTempoTotal += $tempoTotal;
        $qtdTempoTotal++;
        
        $processosTempoTotal[] = [
            'protocolo' => $req['protocolo'],
            'tempo' => $tempoTotal,
            'id' => $req['id']
        ];
    }
}

usort($processosTempoTotal, function($a, $b) {
    return $a['tempo'] <=> $b['tempo'];
});

$topRapidos = array_slice($processosTempoTotal, 0, 5);
$topLentos = array_slice(array_reverse($processosTempoTotal), 0, 5);

if (!function_exists('formatarTempoEstatisticas')) {
    function formatarTempoEstatisticas($segundos) {
        if ($segundos === 0 || $segundos === null) return 'N/A';
        $dias = floor($segundos / 86400);
        $horas = floor(($segundos % 86400) / 3600);
        $minutos = floor(($segundos % 3600) / 60);
        
        $partes = [];
        if ($dias > 0) $partes[] = "{$dias}d";
        if ($horas > 0) $partes[] = "{$horas}h";
        if ($minutos > 0 && $dias == 0) $partes[] = "{$minutos}m";
        
        if (empty($partes)) return "< 1m";
        return implode(' ', $partes);
    }
}

$mediaEsperaAnalise = $qtdEsperaAnalise > 0 ? $somaEsperaAnalise / $qtdEsperaAnalise : 0;
$mediaAnaliseAprovacao = $qtdAnaliseAprovacao > 0 ? $somaAnaliseAprovacao / $qtdAnaliseAprovacao : 0;
$mediaAprovacaoConclusao = $qtdAprovacaoConclusao > 0 ? $somaAprovacaoConclusao / $qtdAprovacaoConclusao : 0;
$mediaTempoTotal = $qtdTempoTotal > 0 ? $somaTempoTotal / $qtdTempoTotal : 0;

include 'header.php';
?>

<h2 class="section-title">Estatísticas</h2>

<div class="row mb-4">
    <div class="col-md-3 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <i class="fas fa-clipboard-list me-1"></i>
                Total de Requerimentos
            </div>
            <div class="card-body">
                <h1 class="text-center mb-0"><?php echo $totalRequerimentos; ?></h1>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <i class="fas fa-calendar-alt me-1"></i>
                Média por Dia (30 dias)
            </div>
            <div class="card-body">
                <h1 class="text-center mb-0"><?php echo $mediaPorDia; ?></h1>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <i class="fas fa-check-circle me-1"></i>
                Taxa de Aprovação
            </div>
            <div class="card-body">
                <?php
                $aprovados = 0;
                foreach ($requerimentosPorStatus as $status) {
                    if ($status['status'] === 'Aprovado') {
                        $aprovados = $status['total'];
                        break;
                    }
                }
                $taxaAprovacao = $totalRequerimentos > 0 ? round(($aprovados / $totalRequerimentos) * 100, 1) : 0;
                ?>
                <h1 class="text-center mb-0"><?php echo $taxaAprovacao; ?>%</h1>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <i class="fas fa-exclamation-circle me-1"></i>
                Taxa de Rejeição
            </div>
            <div class="card-body">
                <?php
                $reprovados = 0;
                foreach ($requerimentosPorStatus as $status) {
                    if ($status['status'] === 'Reprovado') {
                        $reprovados = $status['total'];
                        break;
                    }
                }
                $taxaRejeicao = $totalRequerimentos > 0 ? round(($reprovados / $totalRequerimentos) * 100, 1) : 0;
                ?>
                <h1 class="text-center mb-0"><?php echo $taxaRejeicao; ?>%</h1>
            </div>
        </div>
    </div>
</div>

<h3 class="section-title mt-5 mb-4">Métricas de Tempo (Média de Fluxo)</h3>

<div class="row mb-4">
    <div class="col-md-3 mb-4">
        <div class="card h-100 border-start border-4 border-info">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase mb-2">Pendente <i class="fas fa-arrow-right mx-1"></i> Análise</div>
                <h3 class="mb-0 text-info fw-bold">
                    <i class="fas fa-search me-2"></i>
                    <?php echo formatarTempoEstatisticas($mediaEsperaAnalise); ?>
                </h3>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card h-100 border-start border-4 border-warning">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase mb-2">Análise <i class="fas fa-arrow-right mx-1"></i> Aprovação</div>
                <h3 class="mb-0 text-warning fw-bold">
                    <i class="fas fa-file-signature me-2"></i>
                    <?php echo formatarTempoEstatisticas($mediaAnaliseAprovacao); ?>
                </h3>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card h-100 border-start border-4 border-success">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase mb-2">Aprovação <i class="fas fa-arrow-right mx-1"></i> Conclusão</div>
                <h3 class="mb-0 text-success fw-bold">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo formatarTempoEstatisticas($mediaAprovacaoConclusao); ?>
                </h3>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card h-100 border-start border-4 border-secondary">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase mb-2">Tempo Total</div>
                <h3 class="mb-0 text-secondary fw-bold">
                    <i class="fas fa-clock me-2"></i>
                    <?php echo formatarTempoEstatisticas($mediaTempoTotal); ?>
                </h3>
            </div>
        </div>
    </div>
</div>

<div class="row mb-5">
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-white">
                <h6 class="m-0 font-weight-bold text-success"><i class="fas fa-bolt me-2 text-warning"></i>Processos Mais Rápidos</h6>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Protocolo</th>
                            <th>Tempo Total</th>
                            <th class="text-center">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($topRapidos)): ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-3">Nenhum processo concluído ainda</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($topRapidos as $proc): ?>
                                <tr>
                                    <td class="align-middle fw-bold"><?php echo htmlspecialchars($proc['protocolo']); ?></td>
                                    <td class="align-middle text-success"><i class="fas fa-clock fa-sm me-1"></i><?php echo formatarTempoEstatisticas($proc['tempo']); ?></td>
                                    <td class="align-middle text-center">
                                        <a href="visualizar_requerimento.php?id=<?php echo $proc['id']; ?>" class="btn btn-sm btn-outline-primary" title="Visualizar">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-white">
                <h6 class="m-0 font-weight-bold text-danger"><i class="fas fa-stopwatch me-2 text-danger"></i>Processos Mais Demorados</h6>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Protocolo</th>
                            <th>Tempo Total</th>
                            <th class="text-center">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($topLentos)): ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-3">Nenhum processo concluído ainda</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($topLentos as $proc): ?>
                                <tr>
                                    <td class="align-middle fw-bold"><?php echo htmlspecialchars($proc['protocolo']); ?></td>
                                    <td class="align-middle text-danger"><i class="fas fa-clock fa-sm me-1"></i><?php echo formatarTempoEstatisticas($proc['tempo']); ?></td>
                                    <td class="align-middle text-center">
                                        <a href="visualizar_requerimento.php?id=<?php echo $proc['id']; ?>" class="btn btn-sm btn-outline-primary" title="Visualizar">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<h3 class="section-title mt-2 mb-4">Gráficos Gerais</h3>
<div class="row">
    <!-- Gráfico de linha - Requerimentos por mês -->
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <i class="fas fa-chart-line me-1"></i>
                Requerimentos por Mês
            </div>
            <div class="card-body">
                <canvas id="requerimentosPorMes" height="300"></canvas>
            </div>
        </div>
    </div>

    <!-- Gráfico de pizza - Requerimentos por tipo -->
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <i class="fas fa-chart-pie me-1"></i>
                Requerimentos por Tipo
            </div>
            <div class="card-body">
                <canvas id="requerimentosPorTipo" height="300"></canvas>
            </div>
        </div>
    </div>

    <!-- Gráfico de barras - Requerimentos por status -->
    <div class="col-lg-12 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <i class="fas fa-chart-bar me-1"></i>
                Requerimentos por Status
            </div>
            <div class="card-body">
                <canvas id="requerimentosPorStatus" height="150"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Cores para os gráficos
        const coresPrimarias = [
            'rgba(45, 134, 97, 0.7)', // Verde primário
            'rgba(19, 78, 94, 0.7)', // Verde azulado
            'rgba(71, 175, 140, 0.7)', // Verde claro
            'rgba(33, 150, 83, 0.7)', // Verde médio
            'rgba(24, 100, 115, 0.7)' // Verde escuro
        ];

        const coresStatus = {
            'Em análise': 'rgba(255, 193, 7, 0.7)', // Amarelo
            'Aprovado': 'rgba(40, 167, 69, 0.7)', // Verde
            'Reprovado': 'rgba(220, 53, 69, 0.7)', // Vermelho
            'Pendente': 'rgba(23, 162, 184, 0.7)', // Azul
            'Cancelado': 'rgba(108, 117, 125, 0.7)' // Cinza
        };

        // Preparar dados para o gráfico de linha - Requerimentos por mês
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
                    label: 'Requerimentos',
                    data: mesesDados,
                    fill: false,
                    borderColor: '#2D8661',
                    backgroundColor: 'rgba(45, 134, 97, 0.1)',
                    tension: 0.1,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    title: {
                        display: true,
                        text: 'Evolução dos últimos 6 meses'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });

        // Preparar dados para o gráfico de pizza - Requerimentos por tipo
        const tiposLabels = [];
        const tiposDados = [];

        <?php foreach ($requerimentosPorTipo as $index => $item): ?>
            tiposLabels.push('<?php echo $item['tipo_alvara']; ?>');
            tiposDados.push(<?php echo $item['total']; ?>);
        <?php endforeach; ?>

        new Chart(document.getElementById('requerimentosPorTipo'), {
            type: 'pie',
            data: {
                labels: tiposLabels,
                datasets: [{
                    data: tiposDados,
                    backgroundColor: coresPrimarias,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right'
                    },
                    title: {
                        display: true,
                        text: 'Distribuição por tipo de alvará'
                    }
                }
            }
        });

        // Preparar dados para o gráfico de barras - Requerimentos por status
        const statusLabels = [];
        const statusDados = [];
        const statusCores = [];

        <?php foreach ($requerimentosPorStatus as $item): ?>
            statusLabels.push('<?php echo $item['status']; ?>');
            statusDados.push(<?php echo $item['total']; ?>);
            statusCores.push(coresStatus['<?php echo $item['status']; ?>'] || coresPrimarias[0]);
        <?php endforeach; ?>

        new Chart(document.getElementById('requerimentosPorStatus'), {
            type: 'bar',
            data: {
                labels: statusLabels,
                datasets: [{
                    label: 'Requerimentos',
                    data: statusDados,
                    backgroundColor: statusCores,
                    borderColor: statusCores.map(cor => cor.replace('0.7', '1')),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    title: {
                        display: true,
                        text: 'Distribuição por status'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    });
</script>

<?php include 'footer.php'; ?>