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

// Buscar dados para usuários
$adminModel = new Administrador();
$usuarios = $adminModel->listar();

// Processar ação de exclusão
if (isset($_GET['excluir']) && is_numeric($_GET['excluir'])) {
    $id = (int)$_GET['excluir'];

    // Não permitir excluir o próprio usuário
    if ($id == $adminId) {
        setMensagem('erro', 'Você não pode excluir seu próprio usuário.');
    } else {
        if ($adminModel->excluir($id)) {
            // Registrar ação no histórico
            $historicoModel = new HistoricoAcao();
            $historicoModel->registrar([
                'admin_id' => $adminId,
                'requerimento_id' => null,
                'acao' => 'Exclusão de usuário administrador (ID: ' . $id . ')'
            ]);

            setMensagem('sucesso', 'Usuário excluído com sucesso!');
        } else {
            setMensagem('erro', 'Erro ao excluir o usuário.');
        }
    }

    redirect('usuarios.php');
}

// Processar ação de adição ou edição de usuário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : null;
    $dados = [
        'nome' => $_POST['nome'] ?? '',
        'email' => $_POST['email'] ?? '',
        'nivel' => $_POST['nivel'] ?? 'operador',
        'senha' => $_POST['senha'] ?? ''
    ];

    // Validação básica
    if (empty($dados['nome']) || empty($dados['email'])) {
        setMensagem('erro', 'Nome e email são obrigatórios.');
    } else {
        // Verificar se o email já existe (para novos usuários)
        if (!$id && $adminModel->emailExiste($dados['email'])) {
            setMensagem('erro', 'Este email já está cadastrado.');
        } else {
            // Se não for informada nova senha em edição, manter a atual
            if ($id && empty($dados['senha'])) {
                unset($dados['senha']);
            }

            if ($id) {
                // Atualização
                if ($adminModel->atualizar($id, $dados)) {
                    // Registrar ação no histórico
                    $historicoModel = new HistoricoAcao();
                    $historicoModel->registrar([
                        'admin_id' => $adminId,
                        'requerimento_id' => null,
                        'acao' => 'Atualização de usuário administrador (ID: ' . $id . ')'
                    ]);

                    setMensagem('sucesso', 'Usuário atualizado com sucesso!');
                } else {
                    setMensagem('erro', 'Erro ao atualizar o usuário.');
                }
            } else {
                // Inserção - senha é obrigatória
                if (empty($dados['senha'])) {
                    setMensagem('erro', 'A senha é obrigatória para novos usuários.');
                } else {
                    // Inserção
                    if ($novoId = $adminModel->inserir($dados)) {
                        // Registrar ação no histórico
                        $historicoModel = new HistoricoAcao();
                        $historicoModel->registrar([
                            'admin_id' => $adminId,
                            'requerimento_id' => null,
                            'acao' => 'Cadastro de novo usuário administrador (ID: ' . $novoId . ')'
                        ]);

                        setMensagem('sucesso', 'Usuário cadastrado com sucesso!');
                    } else {
                        setMensagem('erro', 'Erro ao cadastrar o usuário.');
                    }
                }
            }

            redirect('usuarios.php');
        }
    }

    $mensagem = getMensagem();
}

// Preparar dados para edição
$usuario = null;
if (isset($_GET['editar']) && is_numeric($_GET['editar'])) {
    $usuario = $adminModel->buscarPorId((int)$_GET['editar']);
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuários - SEMA</title>
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

        .btn-secondary {
            background-color: var(--gray-color);
            color: white;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background-color: #c82333;
        }

        .form-buttons {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }

        /* Table Styles */
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
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-color);
            color: var(--dark-color);
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        .data-table tr:hover {
            background-color: #f8f9fa;
        }

        /* Badge for Admin Level */
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-admin {
            background-color: var(--primary-color);
            color: white;
        }

        .badge-operator {
            background-color: var(--info-color);
            color: white;
        }

        /* Action Buttons */
        .action-btn {
            padding: 5px 10px;
            border-radius: 4px;
            color: white;
            text-decoration: none;
            font-size: 12px;
            transition: background-color 0.3s;
            margin-right: 5px;
            display: inline-block;
        }

        .action-btn:last-child {
            margin-right: 0;
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
            <a href="estatisticas.php" class="menu-item">
                <i class="fas fa-chart-bar"></i> Estatísticas
            </a>
            <?php if ($adminNivel == 'admin'): ?>
                <a href="usuarios.php" class="menu-item active">
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
            <h1 class="page-title">Gerenciar Usuários</h1>
            <div class="header-actions">
                <?php if (!isset($_GET['editar']) && !isset($_GET['adicionar'])): ?>
                    <a href="?adicionar"><i class="fas fa-user-plus"></i> Novo Usuário</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($mensagem): ?>
            <div class="alert alert-<?php echo $mensagem['tipo']; ?>">
                <?php echo $mensagem['texto']; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['adicionar']) || isset($_GET['editar'])): ?>
            <!-- Formulário de adicionar/editar usuário -->
            <div class="form-section">
                <h2><?php echo isset($_GET['editar']) ? 'Editar Usuário' : 'Novo Usuário'; ?></h2>

                <form method="post" action="">
                    <?php if ($usuario): ?>
                        <input type="hidden" name="id" value="<?php echo $usuario['id']; ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="nome">Nome Completo*</label>
                        <input type="text" class="form-control" id="nome" name="nome" value="<?php echo $usuario ? sanitize($usuario['nome']) : ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email*</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo $usuario ? sanitize($usuario['email']) : ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="senha">Senha <?php echo $usuario ? '' : '*'; ?></label>
                        <input type="password" class="form-control" id="senha" name="senha">
                        <?php if ($usuario): ?>
                            <div class="form-text">Deixe em branco para manter a senha atual.</div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="nivel">Nível de Acesso*</label>
                        <select class="form-control" id="nivel" name="nivel" required>
                            <option value="operador" <?php echo ($usuario && $usuario['nivel'] == 'operador') ? 'selected' : ''; ?>>Operador</option>
                            <option value="admin" <?php echo ($usuario && $usuario['nivel'] == 'admin') ? 'selected' : ''; ?>>Administrador</option>
                        </select>
                        <div class="form-text">
                            <strong>Operador:</strong> Pode gerenciar requerimentos, documentos e tipos de alvará.<br>
                            <strong>Administrador:</strong> Acesso completo ao sistema, incluindo gerenciamento de usuários e configurações.
                        </div>
                    </div>

                    <div class="form-buttons">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo isset($_GET['editar']) ? 'Atualizar' : 'Salvar'; ?>
                        </button>
                        <a href="usuarios.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <!-- Lista de usuários -->
            <div class="form-section">
                <h2>Usuários Cadastrados</h2>

                <?php if (count($usuarios) > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Email</th>
                                <th>Nível</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo sanitize($user['nome']); ?></td>
                                    <td><?php echo sanitize($user['email']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $user['nivel'] == 'admin' ? 'admin' : 'operator'; ?>">
                                            <?php echo $user['nivel'] == 'admin' ? 'Administrador' : 'Operador'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="?editar=<?php echo $user['id']; ?>" class="action-btn btn-edit">
                                            <i class="fas fa-edit"></i> Editar
                                        </a>
                                        <?php if ($user['id'] != $adminId): ?>
                                            <a href="?excluir=<?php echo $user['id']; ?>" class="action-btn btn-delete" onclick="return confirm('Tem certeza que deseja excluir este usuário?');">
                                                <i class="fas fa-trash"></i> Excluir
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="text-align: center; padding: 20px; color: #6c757d;">
                        Não há usuários cadastrados. <a href="?adicionar">Adicionar um novo usuário</a>.
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
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
        });
    </script>
</body>

</html>