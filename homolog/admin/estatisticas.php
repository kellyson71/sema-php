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