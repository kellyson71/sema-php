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

// Parâmetros de paginação e filtros
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$por_pagina = 10;
$offset = ($pagina - 1) * $por_pagina;

$filtro_status = isset($_GET['status']) ? $_GET['status'] : '';
$filtro_tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';

// Montar a condição WHERE para os filtros
$where = '1=1';
$params = [];

if (!empty($filtro_status)) {
    $where .= " AND status = :status";
    $params['status'] = $filtro_status;
}

if (!empty($filtro_tipo)) {
    $where .= " AND tipo_alvara = :tipo";
    $params['tipo'] = $filtro_tipo;
}

if (!empty($busca)) {
    $where .= " AND (protocolo LIKE :busca OR endereco_objetivo LIKE :busca)";
    $params['busca'] = "%{$busca}%";
}

// Buscar dados para a listagem
$requerimentoModel = new Requerimento();
$db = new Database();

// Contagem total para paginação
$sql_count = "SELECT COUNT(*) as total FROM requerimentos WHERE {$where}";
$total_registros = $db->query($sql_count, $params)->fetch()['total'];
$total_paginas = ceil($total_registros / $por_pagina);

// Buscar requerimentos com paginação
$sql = "SELECT r.*, req.nome as requerente_nome, req.cpf_cnpj as requerente_cpf_cnpj
        FROM requerimentos r
        JOIN requerentes req ON r.requerente_id = req.id
        WHERE {$where}
        ORDER BY r.data_envio DESC
        LIMIT {$offset}, {$por_pagina}";

$requerimentos = $db->query($sql, $params)->fetchAll();

// Listar tipos de alvará
$sql_tipos = "SELECT DISTINCT tipo_alvara FROM requerimentos ORDER BY tipo_alvara";
$tipos_alvara = $db->query($sql_tipos)->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Requerimentos - SEMA</title>
    <link rel="icon" href="../assets/prefeitura-logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .page-title {
            font-size: 24px;
            color: var(--secondary-color);
        }

        /* Filtros */
        .filters-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
            margin-bottom: 20px;
        }

        .form-filtros {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 5px;
            color: var(--secondary-color);
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 14px;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(0, 152, 81, 0.2);
        }

        .filtro-botoes {
            display: flex;
            align-items: flex-end;
            gap: 10px;
        }

        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background-color 0.3s;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
        }

        .btn-secondary {
            background-color: var(--secondary-color);
            color: white;
        }

        .btn-secondary:hover {
            background-color: #212529;
        }

        /* Table Styles */
        .card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }

        .card-header {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            font-size: 18px;
            color: var(--secondary-color);
            font-weight: 600;
            margin: 0;
        }

        .card-body {
            padding: 20px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            background-color: #f8f9fa;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: var(--secondary-color);
            border-bottom: 1px solid var(--border-color);
        }

        .data-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            color: var(--dark-color);
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        .data-table tr {
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }

        .data-table tr:hover {
            background-color: #f0f7f4;
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0, 152, 81, 0.1);
        }

        /* Indicador visual de que a linha é clicável */
        .data-table tr::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background-color: var(--primary-color);
            transition: width 0.3s ease;
        }

        .data-table tr:hover::after {
            width: 100%;
        }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
            text-align: center;
            min-width: 90px;
        }

        .status-pendente {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-analise {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .status-aprovado {
            background-color: #d4edda;
            color: #155724;
        }

        .status-rejeitado {
            background-color: #f8d7da;
            color: #721c24;
        }

        /* Action Buttons */
        .action-btn {
            padding: 6px 12px;
            border-radius: 4px;
            color: white;
            text-decoration: none;
            font-size: 12px;
            transition: all 0.3s;
            margin-right: 5px;
            display: inline-block;
            position: relative;
            z-index: 2;
        }

        .action-btn:last-child {
            margin-right: 0;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .btn-view {
            background-color: var(--info-color);
        }

        .btn-view:hover {
            background-color: #138496;
        }

        .btn-edit {
            background-color: var(--primary-color);
        }

        .btn-edit:hover {
            background-color: var(--primary-dark);
        }

        .btn-delete {
            background-color: var(--danger-color);
        }

        .btn-delete:hover {
            background-color: #c82333;
        }

        .btn-primary,
        .btn-secondary,
        .action-btn {
            position: relative;
            overflow: hidden;
        }

        .btn-primary:after,
        .btn-secondary:after,
        .action-btn:after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 5px;
            height: 5px;
            background: rgba(255, 255, 255, 0.5);
            opacity: 0;
            border-radius: 100%;
            transform: scale(1, 1) translate(-50%);
            transform-origin: 50% 50%;
        }

        .btn-primary:focus:not(:active)::after,
        .btn-secondary:focus:not(:active)::after,
        .action-btn:focus:not(:active)::after {
            animation: ripple 1s ease-out;
        }

        @keyframes ripple {
            0% {
                transform: scale(0, 0);
                opacity: 0.5;
            }

            20% {
                transform: scale(25, 25);
                opacity: 0.5;
            }

            100% {
                opacity: 0;
                transform: scale(40, 40);
            }
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }

        .pagination-item {
            margin: 0 5px;
        }

        .pagination-link {
            display: block;
            padding: 8px 12px;
            border-radius: 4px;
            text-decoration: none;
            transition: background-color 0.3s;
            background-color: white;
            color: var(--secondary-color);
            border: 1px solid var(--border-color);
        }

        .pagination-link:hover {
            background-color: #f8f9fa;
        }

        .pagination-link.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .pagination-disabled {
            color: #adb5bd;
            cursor: not-allowed;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }

        .empty-state i {
            font-size: 48px;
            color: #ccc;
            margin-bottom: 15px;
        }

        .empty-state-text {
            font-size: 16px;
            color: #6c757d;
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
            <a href="requerimentos.php" class="menu-item active">
                <i class="fas fa-file-alt"></i> Requerimentos
            </a>
            <a href="documentos.php" class="menu-item">
                <i class="fas fa-folder-open"></i> Documentos
            </a>
            <a href="tipos_alvara.php" class="menu-item">
                <i class="fas fa-list"></i> Tipos de Alvará
            </a>
            <a href="estatisticas.php" class="menu-item">
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
            <a href="dashboard.php?logout=1" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Sair
            </a>
        </div>
    </div>

    <!-- Content Area -->
    <div class="content">
        <div class="page-header">
            <h1 class="page-title">Gerenciar Requerimentos</h1>
        </div>

        <?php if ($mensagem): ?>
            <div class="alert alert-<?php echo $mensagem['tipo']; ?>">
                <?php echo $mensagem['texto']; ?>
            </div>
        <?php endif; ?>

        <!-- Filtros -->
        <div class="filters-container">
            <form action="" method="get" class="form-filtros">
                <div class="form-group">
                    <label for="busca">Buscar</label>
                    <input type="text" class="form-control" id="busca" name="busca" placeholder="Protocolo ou endereço" value="<?php echo sanitize($busca); ?>">
                </div>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select class="form-control" id="status" name="status">
                        <option value="">Todos</option>
                        <option value="pendente" <?php echo $filtro_status === 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                        <option value="analise" <?php echo $filtro_status === 'analise' ? 'selected' : ''; ?>>Em Análise</option>
                        <option value="aprovado" <?php echo $filtro_status === 'aprovado' ? 'selected' : ''; ?>>Aprovado</option>
                        <option value="rejeitado" <?php echo $filtro_status === 'rejeitado' ? 'selected' : ''; ?>>Rejeitado</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="tipo">Tipo de Alvará</label>
                    <select class="form-control" id="tipo" name="tipo">
                        <option value="">Todos</option>
                        <?php foreach ($tipos_alvara as $tipo): ?>
                            <option value="<?php echo sanitize($tipo); ?>" <?php echo $filtro_tipo === $tipo ? 'selected' : ''; ?>>
                                <?php echo sanitize($tipo); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filtro-botoes">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Filtrar
                    </button>
                    <a href="requerimentos.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Limpar
                    </a>
                </div>
            </form>
        </div>

        <!-- Tabela de Requerimentos -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Requerimentos</h3>
                <div>
                    <span>Total: <?php echo $total_registros; ?> requerimento(s)</span>
                </div>
            </div>
            <div class="card-body">
                <?php if (count($requerimentos) > 0): ?>
                    <div class="alert alert-info" style="display: flex; align-items: center;">
                        <i class="fas fa-info-circle" style="font-size: 18px; margin-right: 10px;"></i>
                        <span>Dica: Clique em qualquer linha para visualizar os detalhes completos do requerimento.</span>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Protocolo</th>
                                <th>Tipo</th>
                                <th>Requerente</th>
                                <th>Endereço</th>
                                <th>Data</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requerimentos as $req): ?>
                                <tr onclick="window.location='visualizar_requerimento.php?id=<?php echo $req['id']; ?>'" title="Clique para visualizar os detalhes deste requerimento" class="requerimento-row">
                                    <td><?php echo sanitize($req['protocolo']); ?></td>
                                    <td><?php echo sanitize($req['tipo_alvara']); ?></td>
                                    <td>
                                        <?php echo sanitize($req['requerente_nome']); ?><br>
                                        <small><?php echo sanitize($req['requerente_cpf_cnpj']); ?></small>
                                    </td>
                                    <td><?php echo sanitize(substr($req['endereco_objetivo'], 0, 40)) . (strlen($req['endereco_objetivo']) > 40 ? '...' : ''); ?></td>
                                    <td><?php echo formatarData($req['data_envio']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($req['status']); ?>">
                                            <?php echo formatarStatus($req['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="visualizar_requerimento.php?id=<?php echo $req['id']; ?>" class="action-btn btn-view">
                                            <i class="fas fa-eye"></i> Ver
                                        </a>
                                        <a href="editar_requerimento.php?id=<?php echo $req['id']; ?>" class="action-btn btn-edit">
                                            <i class="fas fa-edit"></i> Editar
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Paginação -->
                    <?php if ($total_paginas > 1): ?>
                        <div class="pagination">
                            <?php if ($pagina > 1): ?>
                                <div class="pagination-item">
                                    <a href="?pagina=1<?php echo !empty($filtro_status) ? '&status=' . $filtro_status : ''; ?><?php echo !empty($filtro_tipo) ? '&tipo=' . $filtro_tipo : ''; ?><?php echo !empty($busca) ? '&busca=' . $busca : ''; ?>" class="pagination-link">
                                        <i class="fas fa-angle-double-left"></i>
                                    </a>
                                </div>
                                <div class="pagination-item">
                                    <a href="?pagina=<?php echo $pagina - 1; ?><?php echo !empty($filtro_status) ? '&status=' . $filtro_status : ''; ?><?php echo !empty($filtro_tipo) ? '&tipo=' . $filtro_tipo : ''; ?><?php echo !empty($busca) ? '&busca=' . $busca : ''; ?>" class="pagination-link">
                                        <i class="fas fa-angle-left"></i>
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="pagination-item">
                                    <span class="pagination-link pagination-disabled">
                                        <i class="fas fa-angle-double-left"></i>
                                    </span>
                                </div>
                                <div class="pagination-item">
                                    <span class="pagination-link pagination-disabled">
                                        <i class="fas fa-angle-left"></i>
                                    </span>
                                </div>
                            <?php endif; ?>

                            <?php
                            $inicio = max(1, $pagina - 2);
                            $fim = min($total_paginas, $pagina + 2);

                            for ($i = $inicio; $i <= $fim; $i++):
                            ?>
                                <div class="pagination-item">
                                    <a href="?pagina=<?php echo $i; ?><?php echo !empty($filtro_status) ? '&status=' . $filtro_status : ''; ?><?php echo !empty($filtro_tipo) ? '&tipo=' . $filtro_tipo : ''; ?><?php echo !empty($busca) ? '&busca=' . $busca : ''; ?>" class="pagination-link <?php echo $i == $pagina ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </div>
                            <?php endfor; ?>

                            <?php if ($pagina < $total_paginas): ?>
                                <div class="pagination-item">
                                    <a href="?pagina=<?php echo $pagina + 1; ?><?php echo !empty($filtro_status) ? '&status=' . $filtro_status : ''; ?><?php echo !empty($filtro_tipo) ? '&tipo=' . $filtro_tipo : ''; ?><?php echo !empty($busca) ? '&busca=' . $busca : ''; ?>" class="pagination-link">
                                        <i class="fas fa-angle-right"></i>
                                    </a>
                                </div>
                                <div class="pagination-item">
                                    <a href="?pagina=<?php echo $total_paginas; ?><?php echo !empty($filtro_status) ? '&status=' . $filtro_status : ''; ?><?php echo !empty($filtro_tipo) ? '&tipo=' . $filtro_tipo : ''; ?><?php echo !empty($busca) ? '&busca=' . $busca : ''; ?>" class="pagination-link">
                                        <i class="fas fa-angle-double-right"></i>
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="pagination-item">
                                    <span class="pagination-link pagination-disabled">
                                        <i class="fas fa-angle-right"></i>
                                    </span>
                                </div>
                                <div class="pagination-item">
                                    <span class="pagination-link pagination-disabled">
                                        <i class="fas fa-angle-double-right"></i>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <p class="empty-state-text">Nenhum requerimento encontrado com os filtros atuais.</p>
                        <a href="requerimentos.php" class="btn btn-primary">
                            <i class="fas fa-sync-alt"></i> Limpar filtros
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Para futura implementação de toggle de sidebar em mobile
            const menuToggle = document.querySelector('.menu-toggle');
            const sidebar = document.querySelector('.sidebar');

            if (menuToggle) {
                menuToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                });
            }

            // Prevenir que cliques nos botões de ação propaguem para a linha da tabela
            document.querySelectorAll('.action-btn').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            });
        });
    </script>
</body>

</html>