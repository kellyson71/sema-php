<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/models.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['admin_id'])) {
    setMensagem('erro', 'Você precisa estar logado para acessar esta área.');
    redirect('index.php');
}

// Inicializar variáveis
$mensagem = getMensagem();
$adminId = $_SESSION['admin_id'];
$adminNome = $_SESSION['admin_nome'];
$adminNivel = $_SESSION['admin_nivel'];

// Buscar dados para estatísticas
$requerimentoModel = new Requerimento();
$totalRequerimentos = $requerimentoModel->contarTotal();
$statusContagem = $requerimentoModel->contarPorStatus();

// Estatísticas por mês (últimos 6 meses)
$meses = [];
$dadosMensais = [];
$mesAtual = date('n');
$anoAtual = date('Y');

for ($i = 5; $i >= 0; $i--) {
    $mes = $mesAtual - $i;
    $ano = $anoAtual;

    if ($mes <= 0) {
        $mes += 12;
        $ano--;
    }

    $nomeMes = formatarNomeMes($mes);
    $meses[] = $nomeMes;

    // Contar requerimentos por mês
    $contagem = $requerimentoModel->contarPorMes($mes, $ano);
    $dadosMensais[] = $contagem;
}

// Estatísticas por tipo de alvará
$tipoAlvaraModel = new TipoAlvara();
$tiposAlvara = $tipoAlvaraModel->listar();
$dadosPorTipo = [];

foreach ($tiposAlvara as $tipo) {
    $contagem = $requerimentoModel->contarPorTipoAlvara($tipo['id']);
    $dadosPorTipo[$tipo['nome']] = $contagem;
}

// Estatísticas de tempo médio de processamento
$tempoMedioPorStatus = [
    'analise' => $requerimentoModel->calcularTempoMedioPorStatus('analise'),
    'aprovado' => $requerimentoModel->calcularTempoMedioPorStatus('aprovado'),
    'rejeitado' => $requerimentoModel->calcularTempoMedioPorStatus('rejeitado')
];

// Lidar com ações de logout
if (isset($_GET['logout'])) {
    // Registrar ação de logout no histórico
    $historicoModel = new HistoricoAcao();
    $historicoModel->registrar([
        'admin_id' => $adminId,
        'requerimento_id' => null,
        'acao' => 'Logout do sistema administrativo'
    ]);

    // Limpar sessão
    session_unset();
    session_destroy();

    // Redirecionar para login
    redirect('index.php');
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estatísticas - SEMA</title>
    <link rel="icon" href="../assets/prefeitura-logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #009851;
            --primary-dark: #007840;
            --secondary-color: #333;
            --light-color: #f8f9fa;
            --border-color: #e0e0e0;
            --danger-color: #dc3545;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --dark-color: #343a40;
            --gray-color: #6c757d;
            --sidebar-width: 250px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f5f5;
            min-height: 100vh;
            display: flex;
        }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--primary-color);
            color: white;
            min-height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            z-index: 100;
            transition: all 0.3s;
        }

        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .sidebar-logo img {
            width: 60px;
            height: auto;
            margin-bottom: 10px;
        }

        .sidebar-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .sidebar-subtitle {
            font-size: 12px;
            opacity: 0.8;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .menu-item {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            color: white;
            text-decoration: none;
            transition: background-color 0.3s;
        }

        .menu-item:hover,
        .menu-item.active {
            background-color: var(--primary-dark);
        }

        .menu-item i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .sidebar-footer {
            padding: 15px 20px;
            position: absolute;
            bottom: 0;
            width: 100%;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        .user-info {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            font-weight: bold;
        }

        .user-details {
            flex: 1;
        }

        .user-name {
            font-size: 14px;
            font-weight: bold;
        }

        .user-role {
            font-size: 12px;
            opacity: 0.8;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            color: white;
            text-decoration: none;
            padding: 8px 10px;
            border-radius: 4px;
            background-color: rgba(255, 255, 255, 0.1);
            transition: background-color 0.3s;
        }

        .logout-btn:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }

        .logout-btn i {
            margin-right: 8px;
        }

        /* Content Area */
        .content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 20px;
            transition: all 0.3s;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .page-title {
            font-size: 24px;
            color: var(--secondary-color);
        }

        .header-actions a {
            padding: 8px 15px;
            background-color: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
            transition: background-color 0.3s;
        }

        .header-actions a:hover {
            background-color: var(--primary-dark);
        }

        /* Dashboard Cards */
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
            display: flex;
            align-items: center;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
            font-size: 20px;
        }

        .stat-icon.bg-primary {
            background-color: var(--primary-color);
        }

        .stat-icon.bg-warning {
            background-color: var(--warning-color);
        }

        .stat-icon.bg-success {
            background-color: var(--success-color);
        }

        .stat-icon.bg-danger {
            background-color: var(--danger-color);
        }

        .stat-content {
            flex: 1;
        }

        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: var(--secondary-color);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            color: var(--gray-color);
        }

        /* Chart Sections */
        .chart-section {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            padding: 20px;
        }

        .chart-header {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
        }

        .chart-title {
            font-size: 18px;
            color: var(--secondary-color);
            font-weight: 600;
        }

        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 20px;
        }

        /* Alert Messages */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-weight: 500;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                overflow: hidden;
            }

            .content {
                margin-left: 0;
            }

            .menu-toggle {
                display: block;
            }

            .sidebar.active {
                width: var(--sidebar-width);
            }
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <img src="../assets/prefeitura-logo.png" alt="SEMA">
            </div>
            <div class="sidebar-title">Secretaria de Meio Ambiente</div>
            <div class="sidebar-subtitle">Painel Administrativo</div>
        </div>

        <div class="sidebar-menu">
            <a href="dashboard.php" class="menu-item">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="requerimentos.php" class="menu-item">
                <i class="fas fa-file-alt"></i> Requerimentos
            </a>
            <a href="documentos.php" class="menu-item">
                <i class="fas fa-folder-open"></i> Documentos
            </a>
            <a href="tipos_alvara.php" class="menu-item">
                <i class="fas fa-list"></i> Tipos de Alvará
            </a>
            <a href="estatisticas.php" class="menu-item active">
                <i class="fas fa-chart-bar"></i> Estatísticas
            </a>
            <?php if ($adminNivel == 'admin'): ?>
                <a href="usuarios.php" class="menu-item">
                    <i class="fas fa-users"></i> Usuários
                </a>
                <a href="configuracoes.php" class="menu-item">
                    <i class="fas fa-cog"></i> Configurações
                </a>
            <?php endif; ?>
        </div>

        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($adminNome, 0, 1)); ?>
                </div>
                <div class="user-details">
                    <div class="user-name"><?php echo sanitize($adminNome); ?></div>
                    <div class="user-role"><?php echo $adminNivel == 'admin' ? 'Administrador' : 'Operador'; ?></div>
                </div>
            </div>
            <a href="?logout=1" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Sair
            </a>
        </div>
    </div>

    <!-- Content Area -->
    <div class="content">
        <div class="page-header">
            <h1 class="page-title">Estatísticas</h1>
            <div class="header-actions">
                <a href="../index.php" target="_blank"><i class="fas fa-home"></i> Ver Site</a>
            </div>
        </div>

        <?php if ($mensagem): ?>
            <div class="alert alert-<?php echo $mensagem['tipo']; ?>">
                <?php echo $mensagem['texto']; ?>
            </div>
        <?php endif; ?>

        <div class="dashboard-stats">
            <div class="stat-card">
                <div class="stat-icon bg-primary">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $totalRequerimentos; ?></div>
                    <div class="stat-label">Total de Requerimentos</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon bg-warning">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $statusContagem['pendente'] ?? 0; ?></div>
                    <div class="stat-label">Pendentes</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon bg-success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $statusContagem['aprovado'] ?? 0; ?></div>
                    <div class="stat-label">Aprovados</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon bg-danger">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $statusContagem['rejeitado'] ?? 0; ?></div>
                    <div class="stat-label">Rejeitados</div>
                </div>
            </div>
        </div>

        <!-- Gráfico de Evolução Mensal -->
        <div class="chart-section">
            <div class="chart-header">
                <h2 class="chart-title">Evolução Mensal de Requerimentos</h2>
            </div>
            <div class="chart-container">
                <canvas id="graficoMensal"></canvas>
            </div>
        </div>

        <!-- Gráfico de Distribuição por Tipo -->
        <div class="chart-section">
            <div class="chart-header">
                <h2 class="chart-title">Distribuição por Tipo de Alvará</h2>
            </div>
            <div class="chart-container">
                <canvas id="graficoPorTipo"></canvas>
            </div>
        </div>

        <!-- Gráfico de Status -->
        <div class="chart-section">
            <div class="chart-header">
                <h2 class="chart-title">Distribuição por Status</h2>
            </div>
            <div class="chart-container">
                <canvas id="graficoPorStatus"></canvas>
            </div>
        </div>

        <!-- Tempo Médio de Processamento -->
        <div class="chart-section">
            <div class="chart-header">
                <h2 class="chart-title">Tempo Médio de Processamento (dias)</h2>
            </div>
            <div class="chart-container">
                <canvas id="graficoTempoProcessamento"></canvas>
            </div>
        </div>
    </div>

    <script>
        // Para futura implementação de toggle de sidebar em mobile
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggle = document.querySelector('.menu-toggle');
            const sidebar = document.querySelector('.sidebar');

            if (menuToggle) {
                menuToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                });
            }

            // Gráfico de Evolução Mensal
            const ctxMensal = document.getElementById('graficoMensal').getContext('2d');
            new Chart(ctxMensal, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($meses); ?>,
                    datasets: [{
                        label: 'Requerimentos',
                        data: <?php echo json_encode($dadosMensais); ?>,
                        backgroundColor: 'rgba(0, 152, 81, 0.2)',
                        borderColor: 'rgba(0, 152, 81, 1)',
                        borderWidth: 2,
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
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

            // Gráfico de Distribuição por Tipo
            const ctxPorTipo = document.getElementById('graficoPorTipo').getContext('2d');
            new Chart(ctxPorTipo, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_keys($dadosPorTipo)); ?>,
                    datasets: [{
                        label: 'Requerimentos',
                        data: <?php echo json_encode(array_values($dadosPorTipo)); ?>,
                        backgroundColor: [
                            'rgba(0, 152, 81, 0.7)',
                            'rgba(255, 193, 7, 0.7)',
                            'rgba(23, 162, 184, 0.7)',
                            'rgba(220, 53, 69, 0.7)',
                            'rgba(108, 117, 125, 0.7)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
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

            // Gráfico de Status
            const ctxPorStatus = document.getElementById('graficoPorStatus').getContext('2d');
            new Chart(ctxPorStatus, {
                type: 'doughnut',
                data: {
                    labels: ['Pendentes', 'Em Análise', 'Aprovados', 'Rejeitados'],
                    datasets: [{
                        data: [
                            <?php echo $statusContagem['pendente'] ?? 0; ?>,
                            <?php echo $statusContagem['analise'] ?? 0; ?>,
                            <?php echo $statusContagem['aprovado'] ?? 0; ?>,
                            <?php echo $statusContagem['rejeitado'] ?? 0; ?>
                        ],
                        backgroundColor: [
                            'rgba(255, 193, 7, 0.7)',
                            'rgba(23, 162, 184, 0.7)',
                            'rgba(40, 167, 69, 0.7)',
                            'rgba(220, 53, 69, 0.7)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });

            // Gráfico de Tempo Médio de Processamento
            const ctxTempoProcessamento = document.getElementById('graficoTempoProcessamento').getContext('2d');
            new Chart(ctxTempoProcessamento, {
                type: 'bar',
                data: {
                    labels: ['Em Análise', 'Aprovados', 'Rejeitados'],
                    datasets: [{
                        label: 'Dias',
                        data: [
                            <?php echo $tempoMedioPorStatus['analise'] ?? 0; ?>,
                            <?php echo $tempoMedioPorStatus['aprovado'] ?? 0; ?>,
                            <?php echo $tempoMedioPorStatus['rejeitado'] ?? 0; ?>
                        ],
                        backgroundColor: [
                            'rgba(23, 162, 184, 0.7)',
                            'rgba(40, 167, 69, 0.7)',
                            'rgba(220, 53, 69, 0.7)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        });
    </script>
</body>

</html>