<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/models.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['admin_id'])) {
    setMensagem('erro', 'Você precisa estar logado para acessar esta área.');
    redirect('index.php');
}

// Verificar se o usuário tem permissão de administrador
if ($_SESSION['admin_nivel'] != 'admin') {
    setMensagem('erro', 'Você não tem permissão para acessar esta área.');
    redirect('dashboard.php');
}

// Inicializar variáveis
$mensagem = getMensagem();
$adminId = $_SESSION['admin_id'];
$adminNome = $_SESSION['admin_nome'];
$adminNivel = $_SESSION['admin_nivel'];

// Carregar configurações
$configModel = new Configuracao();
$configs = $configModel->listarTodas();

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $atualizacoes = [];

    // Processar cada configuração enviada
    foreach ($_POST as $chave => $valor) {
        if (strpos($chave, 'config_') === 0) {
            $configId = substr($chave, 7); // Remover 'config_' para obter o ID
            $atualizacoes[$configId] = $valor;
        }
    }

    // Atualizar configurações
    $sucesso = true;
    foreach ($atualizacoes as $id => $valor) {
        if (!$configModel->atualizar($id, ['valor' => $valor])) {
            $sucesso = false;
        }
    }

    if ($sucesso) {
        // Registrar ação no histórico
        $historicoModel = new HistoricoAcao();
        $historicoModel->registrar([
            'admin_id' => $adminId,
            'requerimento_id' => null,
            'acao' => 'Atualização de configurações do sistema'
        ]);

        setMensagem('sucesso', 'Configurações atualizadas com sucesso!');
    } else {
        setMensagem('erro', 'Erro ao atualizar algumas configurações.');
    }

    redirect('configuracoes.php');
}

// Agrupar configurações por categoria
$configsPorCategoria = [];
foreach ($configs as $config) {
    $categoria = $config['categoria'];
    if (!isset($configsPorCategoria[$categoria])) {
        $configsPorCategoria[$categoria] = [];
    }
    $configsPorCategoria[$categoria][] = $config;
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - SEMA</title>
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
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .page-title {
            font-size: 24px;
            color: var(--secondary-color);
        }

        /* Form Styles */
        .form-section {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            padding: 20px;
        }

        .form-section h2 {
            margin-bottom: 20px;
            color: var(--secondary-color);
            font-size: 18px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
        }

        .form-description {
            margin-bottom: 20px;
            color: var(--gray-color);
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--secondary-color);
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(0, 152, 81, 0.2);
        }

        .form-text {
            font-size: 12px;
            color: var(--gray-color);
            margin-top: 5px;
        }

        .btn {
            padding: 10px 15px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            border: none;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
        }

        .form-buttons {
            margin-top: 20px;
            text-align: right;
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

        /* Tabs */
        .config-tabs {
            display: flex;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 20px;
        }

        .tab-item {
            padding: 10px 15px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-right: 10px;
            transition: all 0.3s;
        }

        .tab-item.active {
            border-bottom-color: var(--primary-color);
            color: var(--primary-color);
            font-weight: 500;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
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
            <a href="estatisticas.php" class="menu-item">
                <i class="fas fa-chart-bar"></i> Estatísticas
            </a>
            <?php if ($adminNivel == 'admin'): ?>
                <a href="usuarios.php" class="menu-item">
                    <i class="fas fa-users"></i> Usuários
                </a>
                <a href="configuracoes.php" class="menu-item active">
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
            <h1 class="page-title">Configurações do Sistema</h1>
        </div>

        <?php if ($mensagem): ?>
            <div class="alert alert-<?php echo $mensagem['tipo']; ?>">
                <?php echo $mensagem['texto']; ?>
            </div>
        <?php endif; ?>

        <div class="form-section">
            <div class="form-description">
                Configure os parâmetros do sistema para ajustar seu funcionamento de acordo com as necessidades da Secretaria.
            </div>

            <?php if (count($configsPorCategoria) > 0): ?>
                <!-- Abas de categorias -->
                <div class="config-tabs">
                    <?php $primeiraCategoria = true; ?>
                    <?php foreach ($configsPorCategoria as $categoria => $configs): ?>
                        <div class="tab-item <?php echo $primeiraCategoria ? 'active' : ''; ?>" data-tab="tab-<?php echo sanitize(strtolower(str_replace(' ', '-', $categoria))); ?>">
                            <?php echo sanitize($categoria); ?>
                        </div>
                        <?php $primeiraCategoria = false; ?>
                    <?php endforeach; ?>
                </div>

                <form method="post" action="">
                    <?php $primeiraCategoria = true; ?>
                    <?php foreach ($configsPorCategoria as $categoria => $configs): ?>
                        <div class="tab-content <?php echo $primeiraCategoria ? 'active' : ''; ?>" id="tab-<?php echo sanitize(strtolower(str_replace(' ', '-', $categoria))); ?>">
                            <h2><?php echo sanitize($categoria); ?></h2>

                            <?php foreach ($configs as $config): ?>
                                <div class="form-group">
                                    <label for="config_<?php echo $config['id']; ?>"><?php echo sanitize($config['nome']); ?></label>
                                    <?php if ($config['tipo'] == 'texto'): ?>
                                        <input type="text" class="form-control" id="config_<?php echo $config['id']; ?>" name="config_<?php echo $config['id']; ?>" value="<?php echo sanitize($config['valor']); ?>">
                                    <?php elseif ($config['tipo'] == 'numero'): ?>
                                        <input type="number" class="form-control" id="config_<?php echo $config['id']; ?>" name="config_<?php echo $config['id']; ?>" value="<?php echo sanitize($config['valor']); ?>">
                                    <?php elseif ($config['tipo'] == 'booleano'): ?>
                                        <select class="form-control" id="config_<?php echo $config['id']; ?>" name="config_<?php echo $config['id']; ?>">
                                            <option value="1" <?php echo $config['valor'] == '1' ? 'selected' : ''; ?>>Sim</option>
                                            <option value="0" <?php echo $config['valor'] == '0' ? 'selected' : ''; ?>>Não</option>
                                        </select>
                                    <?php elseif ($config['tipo'] == 'lista'): ?>
                                        <select class="form-control" id="config_<?php echo $config['id']; ?>" name="config_<?php echo $config['id']; ?>">
                                            <?php
                                            $opcoes = explode(',', $config['opcoes']);
                                            foreach ($opcoes as $opcao):
                                                $opcao = trim($opcao);
                                            ?>
                                                <option value="<?php echo sanitize($opcao); ?>" <?php echo $config['valor'] == $opcao ? 'selected' : ''; ?>>
                                                    <?php echo sanitize($opcao); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php elseif ($config['tipo'] == 'textarea'): ?>
                                        <textarea class="form-control" id="config_<?php echo $config['id']; ?>" name="config_<?php echo $config['id']; ?>" rows="4"><?php echo sanitize($config['valor']); ?></textarea>
                                    <?php endif; ?>

                                    <?php if (!empty($config['descricao'])): ?>
                                        <div class="form-text"><?php echo sanitize($config['descricao']); ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php $primeiraCategoria = false; ?>
                    <?php endforeach; ?>

                    <div class="form-buttons">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Salvar Configurações
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <p style="text-align: center; padding: 20px; color: #6c757d;">
                    Não há configurações disponíveis.
                </p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Para navegação entre abas
        document.addEventListener('DOMContentLoaded', function() {
            const tabItems = document.querySelectorAll('.tab-item');
            const tabContents = document.querySelectorAll('.tab-content');

            tabItems.forEach(item => {
                item.addEventListener('click', function() {
                    // Desativar todas as abas
                    tabItems.forEach(tab => tab.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active'));

                    // Ativar a aba clicada
                    this.classList.add('active');
                    const tabId = this.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');
                });
            });

            // Para futura implementação de toggle de sidebar em mobile
            const menuToggle = document.querySelector('.menu-toggle');
            const sidebar = document.querySelector('.sidebar');

            if (menuToggle) {
                menuToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                });
            }
        });
    </script>
</body>

</html>