<?php
require_once 'conexao.php';
verificaLogin();

// Função auxiliar para formatar data brasileira
function formataDataBR($data)
{
    return date('d/m/Y \à\s H:i', strtotime($data));
}

// Configurações
$itensPorPagina = 25;
$paginaAtual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($paginaAtual - 1) * $itensPorPagina;

// Preparar filtros
$filtroStatus = isset($_GET['status']) ? $_GET['status'] : '';
$filtroTipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$filtroBusca = isset($_GET['busca']) ? $_GET['busca'] : '';
$filtroNaoVisualizados = isset($_GET['nao_visualizados']) && $_GET['nao_visualizados'] == '1';

// Mensagens de sucesso
$mensagem = '';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'nao_lido':
            $mensagem = "✅ Requerimento marcado como não lido com sucesso!";
            break;
        case 'atualizado':
            $mensagem = "✅ Requerimento atualizado com sucesso!";
            break;
    }
}

// Construir consulta SQL otimizada
$sql = "SELECT r.id, r.protocolo, r.tipo_alvara, r.status, r.data_envio, r.visualizado, 
               req.nome as requerente 
        FROM requerimentos r
        JOIN requerentes req ON r.requerente_id = req.id
        WHERE 1=1";

$sqlCount = "SELECT COUNT(*) as total FROM requerimentos r
             JOIN requerentes req ON r.requerente_id = req.id
             WHERE 1=1";

$params = [];

// Aplicar filtros
if (!empty($filtroStatus)) {
    $sql .= " AND r.status = ?";
    $sqlCount .= " AND r.status = ?";
    $params[] = $filtroStatus;
}

if (!empty($filtroTipo)) {
    $sql .= " AND r.tipo_alvara = ?";
    $sqlCount .= " AND r.tipo_alvara = ?";
    $params[] = $filtroTipo;
}

if (!empty($filtroBusca)) {
    $sql .= " AND (r.protocolo LIKE ? OR req.nome LIKE ? OR req.cpf_cnpj LIKE ?)";
    $sqlCount .= " AND (r.protocolo LIKE ? OR req.nome LIKE ? OR req.cpf_cnpj LIKE ?)";
    $termoBusca = "%$filtroBusca%";
    $params[] = $termoBusca;
    $params[] = $termoBusca;
    $params[] = $termoBusca;
}

if ($filtroNaoVisualizados) {
    $sql .= " AND r.visualizado = 0";
    $sqlCount .= " AND r.visualizado = 0";
}

// Ordenação e paginação
$sql .= " ORDER BY r.visualizado ASC, r.data_envio DESC LIMIT $offset, $itensPorPagina";

// Executar consultas
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requerimentos = $stmt->fetchAll();

$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$totalRequerimentos = $stmtCount->fetch()['total'];

// Estatísticas gerais
$estatisticas = [
    'total' => $pdo->query("SELECT COUNT(*) FROM requerimentos")->fetchColumn(),
    'nao_lidos' => $pdo->query("SELECT COUNT(*) FROM requerimentos WHERE visualizado = 0")->fetchColumn(),
    'pendentes' => $pdo->query("SELECT COUNT(*) FROM requerimentos WHERE status = 'Pendente'")->fetchColumn(),
    'aprovados' => $pdo->query("SELECT COUNT(*) FROM requerimentos WHERE status = 'Aprovado'")->fetchColumn(),
    'finalizados' => $pdo->query("SELECT COUNT(*) FROM requerimentos WHERE status = 'Finalizado'")->fetchColumn()
];

// Listas para filtros
$tiposAlvara = $pdo->query("SELECT DISTINCT tipo_alvara FROM requerimentos ORDER BY tipo_alvara")->fetchAll();
$statusList = $pdo->query("SELECT DISTINCT status FROM requerimentos ORDER BY status")->fetchAll();

include 'header.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Requerimentos - SEMA Pau dos Ferros</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Simple DataTables -->
    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.3/dist/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.3/dist/umd/simple-datatables.js"></script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        /* Design System */
        :root {
            --primary: #3b82f6;
            --primary-dark: #1e40af;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-600: #4b5563;
            --gray-900: #111827;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--gray-50);
        }

        /* Indicador de não lido moderno */
        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--primary);
            display: inline-block;
            margin-right: 10px;
            position: relative;
            animation: pulse-soft 2s infinite;
        }

        @keyframes pulse-soft {

            0%,
            100% {
                opacity: 1;
                transform: scale(1);
            }

            50% {
                opacity: 0.7;
                transform: scale(1.1);
            }
        }

        /* Cards de estatísticas */
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px 0 rgba(0, 0, 0, 0.1);
        }

        /* Tabela moderna */
        .modern-table {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        }

        .modern-table th {
            background: var(--gray-50);
            padding: 16px 20px;
            font-weight: 600;
            color: var(--gray-900);
            border-bottom: 1px solid var(--gray-200);
        }

        .modern-table td {
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
            transition: all 0.2s ease;
        }

        .modern-table tr:hover td {
            background: #fafbfc;
        }

        .modern-table tr.unread {
            background: rgba(59, 130, 246, 0.02);
            border-left: 3px solid var(--primary);
        }

        /* Badges de tipo */
        .type-badge {
            background: var(--gray-100);
            color: var(--gray-600);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-transform: capitalize;
        }

        /* Mensagem de sucesso */
        .success-message {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Filtros */
        .filter-section {
            background: white;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        }

        .filter-input {
            border: 2px solid var(--gray-200);
            border-radius: 8px;
            padding: 10px 16px;
            font-size: 14px;
            transition: border-color 0.2s ease;
        }

        .filter-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        /* Botões */
        .btn-primary {
            background: var(--primary);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--gray-100);
            color: var(--gray-600);
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }

        .btn-secondary:hover {
            background: var(--gray-200);
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 py-8">

        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">
                <i class="fas fa-file-alt text-blue-600 mr-3"></i>
                Gerenciamento de Requerimentos
            </h1>
            <p class="text-gray-600">Visualize e gerencie todos os requerimentos de alvará ambiental</p>
        </div>

        <!-- Mensagem de Sucesso -->
        <?php if (!empty($mensagem)): ?>
            <div class="success-message">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo $mensagem; ?>
            </div>
        <?php endif; ?>

        <!-- Estatísticas -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total de Requerimentos</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($estatisticas['total']); ?></p>
                    </div>
                    <div class="text-blue-600">
                        <i class="fas fa-file-alt text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Não Visualizados</p>
                        <p class="text-2xl font-bold text-blue-600"><?php echo number_format($estatisticas['nao_lidos']); ?></p>
                    </div>
                    <div class="text-blue-600">
                        <i class="fas fa-bell text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Pendentes</p>
                        <p class="text-2xl font-bold text-yellow-600"><?php echo number_format($estatisticas['pendentes']); ?></p>
                    </div>
                    <div class="text-yellow-600">
                        <i class="fas fa-clock text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Aprovados</p>
                        <p class="text-2xl font-bold text-green-600"><?php echo number_format($estatisticas['aprovados']); ?></p>
                    </div>
                    <div class="text-green-600">
                        <i class="fas fa-check-circle text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Finalizados</p>
                        <p class="text-2xl font-bold text-purple-600"><?php echo number_format($estatisticas['finalizados']); ?></p>
                    </div>
                    <div class="text-purple-600">
                        <i class="fas fa-flag-checkered text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filter-section">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                <i class="fas fa-filter mr-2 text-blue-500"></i>
                Filtros de Pesquisa
            </h3>

            <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Buscar</label>
                    <input type="text"
                        name="busca"
                        value="<?php echo htmlspecialchars($filtroBusca); ?>"
                        placeholder="Protocolo, nome ou CPF/CNPJ..."
                        class="filter-input w-full">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="status" class="filter-input w-full">
                        <option value="">Todos os Status</option>
                        <?php foreach ($statusList as $status): ?>
                            <option value="<?php echo $status['status']; ?>" <?php echo $filtroStatus === $status['status'] ? 'selected' : ''; ?>>
                                <?php echo $status['status']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Alvará</label>
                    <select name="tipo" class="filter-input w-full">
                        <option value="">Todos os Tipos</option>
                        <?php foreach ($tiposAlvara as $tipo): ?>
                            <option value="<?php echo $tipo['tipo_alvara']; ?>" <?php echo $filtroTipo === $tipo['tipo_alvara'] ? 'selected' : ''; ?>>
                                <?php echo ucfirst(str_replace('_', ' ', $tipo['tipo_alvara'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex flex-col justify-end">
                    <div class="flex items-center mb-3">
                        <input type="checkbox"
                            name="nao_visualizados"
                            value="1"
                            <?php echo $filtroNaoVisualizados ? 'checked' : ''; ?>
                            class="w-4 h-4 text-blue-600 border-gray-300 rounded">
                        <label class="ml-2 text-sm text-gray-700">Apenas não visualizados</label>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="btn-primary flex-1">
                            <i class="fas fa-search mr-2"></i>Filtrar
                        </button>
                        <a href="requerimentos.php" class="btn-secondary flex-1 text-center">
                            <i class="fas fa-times mr-2"></i>Limpar
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Tabela de Requerimentos -->
        <div class="modern-table">
            <?php if (count($requerimentos) > 0): ?>
                <table id="requerimentosTable" class="w-full">
                    <thead>
                        <tr>
                            <th class="text-left">Protocolo</th>
                            <th class="text-left">Requerente</th>
                            <th class="text-left">Tipo de Alvará</th>
                            <th class="text-left">Status</th>
                            <th class="text-left">Data de Envio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requerimentos as $req): ?>
                            <tr class="<?php echo $req['visualizado'] == 0 ? 'unread' : ''; ?> cursor-pointer transition-all hover:bg-gray-50"
                                onclick="window.location.href='visualizar_requerimento.php?id=<?php echo $req['id']; ?>'">

                                <td class="font-medium text-gray-900">
                                    <div class="flex items-center">
                                        <?php if ($req['visualizado'] == 0): ?>
                                            <span class="status-indicator" title="Não visualizado"></span>
                                        <?php endif; ?>
                                        <span><?php echo $req['protocolo']; ?></span>
                                    </div>
                                </td>

                                <td class="text-gray-900">
                                    <?php echo $req['requerente']; ?>
                                </td>

                                <td>
                                    <span class="type-badge">
                                        <?php echo ucfirst(str_replace('_', ' ', $req['tipo_alvara'])); ?>
                                    </span>
                                </td>

                                <td>
                                    <?php
                                    $statusDotColor = '';
                                    switch (strtolower($req['status'])) {
                                        case 'pendente':
                                            $statusDotColor = '#f59e0b'; // amarelo
                                            break;
                                        case 'aprovado':
                                            $statusDotColor = '#10b981'; // verde
                                            break;
                                        case 'finalizado':
                                            $statusDotColor = '#8b5cf6'; // roxo
                                            break;
                                        case 'reprovado':
                                        case 'rejeitado':
                                            $statusDotColor = '#ef4444'; // vermelho
                                            break;
                                        case 'em análise':
                                        case 'em_analise':
                                            $statusDotColor = '#3b82f6'; // azul
                                            break;
                                        case 'cancelado':
                                            $statusDotColor = '#6b7280'; // cinza
                                            break;
                                        default:
                                            $statusDotColor = '#6b7280'; // cinza
                                    }
                                    ?>
                                    <div class="flex items-center">
                                        <span class="w-2 h-2 rounded-full mr-2" style="background-color: <?php echo $statusDotColor; ?>"></span>
                                        <span class="text-sm text-gray-700 font-medium"><?php echo $req['status']; ?></span>
                                    </div>
                                </td>

                                <td class="text-gray-600">
                                    <?php echo formataDataBR($req['data_envio']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="text-center py-16">
                    <div class="text-6xl text-gray-300 mb-4">
                        <i class="fas fa-search"></i>
                    </div>
                    <h4 class="text-xl font-semibold text-gray-700 mb-2">Nenhum requerimento encontrado</h4>
                    <p class="text-gray-500">Tente ajustar os filtros de pesquisa ou verificar se há requerimentos cadastrados.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar DataTable com configurações em português
            if (document.getElementById("requerimentosTable")) {
                const dataTable = new simpleDatatables.DataTable("#requerimentosTable", {
                    searchable: true,
                    sortable: true,
                    perPage: 25,
                    perPageSelect: [10, 25, 50, 100],
                    labels: {
                        placeholder: "Pesquisar requerimentos...",
                        perPage: "registros por página",
                        noRows: "Nenhum requerimento encontrado",
                        info: "Mostrando {start} a {end} de {rows} requerimentos",
                        noResults: "Nenhum resultado encontrado para sua pesquisa"
                    }
                });
            }

            // Auto-dismiss de mensagens de sucesso
            const successMessages = document.querySelectorAll('.success-message');
            successMessages.forEach(message => {
                setTimeout(() => {
                    message.style.opacity = '0';
                    message.style.transform = 'translateY(-10px)';
                    setTimeout(() => message.remove(), 300);
                }, 5000);
            });
        });
    </script>
</body>

</html>

<?php include 'footer.php'; ?>