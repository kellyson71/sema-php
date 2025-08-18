<?php
require_once 'conexao.php';
verificaLogin();

// Obter dados do administrador logado
$adminData = getDadosAdmin($pdo, $_SESSION['admin_id']);

// Contar notificações (requerimentos recentes - últimas 24h)
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM requerimentos WHERE data_envio >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$stmt->execute();
$totalNotificacoes = $stmt->fetch()['total'];

// Contar requerimentos não visualizados
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM requerimentos WHERE visualizado = 0");
$stmt->execute();
$totalNaoVisualizados = $stmt->fetch()['total'];

// Buscar notificações recentes
$stmt = $pdo->prepare("
    SELECT r.id, r.protocolo, r.tipo_alvara, r.status, r.data_envio, req.nome as requerente 
    FROM requerimentos r
    JOIN requerentes req ON r.requerente_id = req.id
    ORDER BY r.data_envio DESC
    LIMIT 5
");
$stmt->execute();
$notificacoes = $stmt->fetchAll();

// Determinar página atual
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo - SEMA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #2D8661;
            --secondary-color: #134E5E;
            --accent-color: #47AF8C;
            --sidebar-width: 250px;
            --topbar-height: 60px;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--secondary-color), var(--primary-color));
            color: #fff;
            z-index: 1000;
            box-shadow: 2px 0px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-brand {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }

        .sidebar-logo {
            width: 180px;
            margin-bottom: 10px;
        }

        .sidebar-footer {
            position: absolute;
            bottom: 15px;
            left: 0;
            right: 0;
            text-align: center;
            padding: 0 20px;
        }

        .version-text {
            font-size: 0.7rem;
            color: rgba(255, 255, 255, 0.4);
            font-weight: 400;
            letter-spacing: 0.5px;
        }

        /* Estilos para o link de suporte */
        .support-link {
            display: block;
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.75rem;
            text-decoration: none;
            padding: 8px 0;
            margin-bottom: 5px;
            transition: all 0.3s ease;
        }

        .support-link:hover {
            color: #fff;
        }

        .support-link i {
            margin-right: 5px;
            font-size: 0.9rem;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .menu-header {
            padding: 10px 25px;
            font-size: 0.8rem;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.6);
        }

        .sidebar-menu ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-menu li {
            margin-bottom: 5px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 25px;
            text-decoration: none;
            color: rgba(255, 255, 255, 0.8);
            transition: all 0.3s ease;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: #fff;
            border-left: 4px solid #fff;
        }

        .sidebar-menu a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .content-wrapper {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            padding-top: var(--topbar-height);
            transition: all 0.3s ease;
        }

        .topbar {
            position: fixed;
            top: 0;
            right: 0;
            left: var(--sidebar-width);
            height: var(--topbar-height);
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            z-index: 900;
            transition: all 0.3s ease;
        }

        .topbar-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-color);
        }

        .topbar-items {
            display: flex;
            align-items: center;
        }

        .topbar-item {
            margin-left: 15px;
            position: relative;
        }

        .topbar-link {
            color: #6c757d;
            font-size: 1.1rem;
            padding: 5px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.3s;
        }

        .topbar-link:hover {
            color: var(--primary-color);
            background-color: rgba(0, 0, 0, 0.05);
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .dropdown-menu {
            border: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            min-width: 300px;
        }

        .dropdown-header {
            background-color: var(--primary-color);
            color: white;
            border-radius: 8px 8px 0 0;
            padding: 10px 15px;
        }

        .dropdown-item {
            padding: 10px 15px;
            border-bottom: 1px solid #f1f1f1;
        }

        .dropdown-item:last-child {
            border-bottom: none;
        }

        .notification-item {
            white-space: normal;
        }

        .notification-info {
            font-size: 0.8rem;
            color: #6c757d;
        }

        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .card-header {
            background-color: #fff;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            font-weight: 600;
            padding: 15px 20px;
        }

        .card-body {
            padding: 20px;
        }

        .user-profile {
            display: flex;
            align-items: center;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 10px;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.9rem;
            color: #333;
        }

        .user-role {
            font-size: 0.75rem;
            color: #6c757d;
        }

        .main-content {
            padding: 20px;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .content-wrapper,
            .topbar {
                margin-left: 0;
                left: 0;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .content-wrapper.active,
            .topbar.active {
                margin-left: var(--sidebar-width);
            }
        }

        .badge-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: normal;
        }

        .status-em-analise {
            background-color: #ffc107;
            color: #212529;
        }

        .status-aprovado {
            background-color: #28a745;
            color: white;
        }

        .status-reprovado {
            background-color: #dc3545;
            color: white;
        }

        .status-pendente {
            background-color: #17a2b8;
            color: white;
        }

        .status-cancelado {
            background-color: #6c757d;
            color: white;
        }

        /* Estilos para as linhas da tabela clicáveis */
        .table tbody tr.clickable-row {
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .table tbody tr.clickable-row:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }

        /* Estilos para a sidebar de notificações */
        .notification-sidebar {
            position: fixed;
            top: var(--topbar-height);
            right: -300px;
            width: 300px;
            height: calc(100vh - var(--topbar-height));
            background-color: #fff;
            box-shadow: -2px 0 10px rgba(0, 0, 0, 0.1);
            z-index: 999;
            transition: right 0.3s ease;
            overflow-y: auto;
        }

        .notification-sidebar.active {
            right: 0;
        }

        .notification-sidebar-header {
            padding: 15px;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-sidebar-header h5 {
            margin: 0;
            font-size: 1.1rem;
        }

        .notification-sidebar-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
        }

        .notification-sidebar-body {
            padding: 15px;
        }

        .notification-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .notification-item-sidebar {
            padding: 10px;
            border-bottom: 1px solid #f1f1f1;
            transition: background-color 0.2s;
        }

        .notification-item-sidebar:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }

        .notification-item-sidebar a {
            text-decoration: none;
            color: inherit;
        }

        .notification-item-sidebar .notification-title {
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
        }

        .notification-item-sidebar .notification-content {
            font-size: 0.85rem;
            color: #666;
        }

        .notification-item-sidebar .notification-time {
            font-size: 0.75rem;
            color: #999;
            margin-top: 5px;
        }

        .notification-unread {
            background-color: rgba(13, 110, 253, 0.1);
        }

        .notification-toggle {
            position: relative;
        }

        .content-overlay {
            position: fixed;
            top: var(--topbar-height);
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 998;
            display: none;
        }

        .content-overlay.active {
            display: block;
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="index.php" class="sidebar-brand">
                <img src="../assets/img/Logo_sema.png" alt="SEMA" class="sidebar-logo">
            </a>
        </div>
        <div class="sidebar-menu">
            <div class="menu-header">Principal</div>
            <ul>
                <li>
                    <a href="index.php" class="<?php echo $currentPage === 'index.php' ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="requerimentos.php" class="<?php echo $currentPage === 'requerimentos.php' ? 'active' : ''; ?>">
                        <i class="fas fa-clipboard-list"></i>
                        <span>Requerimentos</span>
                        <?php if ($totalNaoVisualizados > 0): ?>
                            <span class="badge bg-danger ms-2"><?php echo $totalNaoVisualizados; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="requerimentos_arquivados.php" class="<?php echo $currentPage === 'requerimentos_arquivados.php' ? 'active' : ''; ?>">
                        <i class="fas fa-archive"></i>
                        <span>Arquivados</span>
                    </a>
                </li>
                <li>
                    <a href="estatisticas.php" class="<?php echo $currentPage === 'estatisticas.php' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-bar"></i>
                        <span>Estatísticas</span>
                    </a>
                </li>
            </ul>

            <div class="menu-header">Administração</div>
            <ul>
                <li>
                    <a href="perfil.php" class="<?php echo $currentPage === 'perfil.php' ? 'active' : ''; ?>">
                        <i class="fas fa-user"></i>
                        <span>Meu Perfil</span>
                    </a>
                </li>
                <?php if ($_SESSION['admin_nivel'] === 'admin'): ?>
                    <li>
                        <a href="administradores.php" class="<?php echo $currentPage === 'administradores.php' ? 'active' : ''; ?>">
                            <i class="fas fa-users-cog"></i>
                            <span>Gerenciar Usuários</span>
                        </a>
                    </li>
                <?php endif; ?>
                <li>
                    <a href="logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Sair</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Versão do sistema - discreta -->
        <div class="sidebar-footer">
            <!-- Link de suporte -->
            <a href="https://wa.me/5584981087357" target="_blank" class="support-link" title="Fale conosco no WhatsApp">
                <i class="fab fa-whatsapp"></i>
                <span>Problemas? Fale conosco</span>
            </a>
            <span class="version-text">v2.8</span>
        </div>
    </div>

    <!-- Topbar -->
    <div class="topbar">
        <div class="topbar-title">
            <?php
            switch ($currentPage) {
                case 'index.php':
                    echo 'Dashboard';
                    break;
                case 'requerimentos.php':
                    echo 'Requerimentos';
                    break;
                case 'estatisticas.php':
                    echo 'Estatísticas';
                    break;
                case 'visualizar_requerimento.php':
                    echo 'Detalhes do Requerimento';
                    break;
                case 'perfil.php':
                    echo 'Meu Perfil';
                    break;
                case 'administradores.php':
                    echo 'Gerenciar Usuários';
                    break;
                default:
                    echo 'Painel Administrativo';
            }
            ?>
        </div>
        <div class="topbar-items">
            <div class="topbar-item notification-toggle">
                <a href="#" class="topbar-link" id="openNotificationSidebar">
                    <i class="fas fa-bell"></i>
                    <?php if ($totalNotificacoes > 0 || $totalNaoVisualizados > 0): ?>
                        <span class="notification-badge">
                            <?php
                            $total = $totalNaoVisualizados > 0 ? $totalNaoVisualizados : $totalNotificacoes;
                            echo $total > 9 ? '9+' : $total;
                            ?>
                        </span>
                    <?php endif; ?>
                </a>
            </div>
            <div class="topbar-item dropdown">
                <a href="#" class="topbar-link" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="user-profile">
                        <div class="user-avatar">
                            <?php if (!empty($adminData['foto_perfil'])): ?>
                                <img src="../uploads/perfil/<?php echo $adminData['foto_perfil']; ?>" alt="Foto de Perfil">
                            <?php else: ?>
                                <i class="fas fa-user-circle fa-2x"></i>
                            <?php endif; ?>
                        </div>
                        <div class="user-info d-none d-md-flex">
                            <span class="user-name"><?php echo $_SESSION['admin_nome']; ?></span>
                            <span class="user-role"><?php echo $_SESSION['admin_nivel'] === 'admin' ? 'Administrador' : 'Operador'; ?></span>
                        </div>
                    </div>
                </a>
                <div class="dropdown-menu dropdown-menu-end">
                    <div class="dropdown-header">
                        <strong>Conta</strong>
                    </div>
                    <a class="dropdown-item" href="perfil.php">
                        <i class="fas fa-user me-2"></i> Meu Perfil
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i> Sair
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <!-- Notification Sidebar -->
        <div class="notification-sidebar" id="notificationSidebar">
            <div class="notification-sidebar-header">
                <h5><i class="fas fa-bell me-2"></i> Notificações</h5>
                <button class="notification-sidebar-close" id="closeNotificationSidebar">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="notification-sidebar-body">
                <?php if ($totalNaoVisualizados > 0): ?>
                    <div class="alert alert-info mb-3">
                        <i class="fas fa-info-circle me-2"></i> Você tem <?php echo $totalNaoVisualizados; ?> requerimentos não visualizados
                        <a href="requerimentos.php?nao_visualizados=1" class="alert-link d-block mt-2">Ver requerimentos não visualizados</a>
                    </div>
                <?php endif; ?>

                <h6 class="mb-3">Requerimentos Recentes</h6>

                <?php
                // Buscar todos os requerimentos para a sidebar
                $stmt = $pdo->prepare("
                    SELECT r.id, r.protocolo, r.tipo_alvara, r.status, r.data_envio, r.visualizado, req.nome as requerente 
                    FROM requerimentos r
                    JOIN requerentes req ON r.requerente_id = req.id
                    ORDER BY r.data_envio DESC
                    LIMIT 20
                ");
                $stmt->execute();
                $todasNotificacoes = $stmt->fetchAll();
                ?>

                <ul class="notification-list">
                    <?php if (count($todasNotificacoes) > 0): ?>
                        <?php foreach ($todasNotificacoes as $notif): ?>
                            <li class="notification-item-sidebar <?php echo $notif['visualizado'] ? '' : 'notification-unread'; ?>">
                                <a href="visualizar_requerimento.php?id=<?php echo $notif['id']; ?>">
                                    <div class="notification-title">
                                        <?php if (!$notif['visualizado']): ?>
                                            <i class="fas fa-circle text-primary me-1" style="font-size: 0.6rem;"></i>
                                        <?php endif; ?>
                                        Requerimento #<?php echo $notif['protocolo']; ?>
                                    </div>
                                    <div class="notification-content">
                                        <?php echo $notif['requerente']; ?> - <?php echo $notif['tipo_alvara']; ?>
                                    </div>
                                    <div class="notification-content">
                                        <span class="badge badge-status status-<?php echo strtolower(str_replace(' ', '-', $notif['status'])); ?>">
                                            <?php echo $notif['status']; ?>
                                        </span>
                                    </div>
                                    <div class="notification-time">
                                        <i class="far fa-clock me-1"></i> <?php echo formataData($notif['data_envio']); ?>
                                    </div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="notification-item-sidebar">
                            <div class="notification-content">Nenhuma notificação disponível</div>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- Overlay para fechar a sidebar -->
        <div class="content-overlay" id="contentOverlay"></div>

        <div class="main-content">