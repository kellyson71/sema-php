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

// Buscar dados completos do administrador
$adminModel = new Administrador();
$admin = $adminModel->buscarPorId($adminId);

if (!$admin) {
    setMensagem('erro', 'Administrador não encontrado.');
    redirect('dashboard.php');
}

// Definir diretório para uploads de fotos
$uploadDir = '../uploads/fotos_perfil/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    // Atualizar dados pessoais
    if ($acao === 'atualizar_dados') {
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');

        // Validar campos
        if (empty($nome)) {
            setMensagem('erro', 'O nome é obrigatório.');
        } elseif (empty($email)) {
            setMensagem('erro', 'O e-mail é obrigatório.');
        } else {
            // Verificar se o e-mail já existe (exceto para o próprio usuário)
            $emailExiste = $adminModel->emailExiste($email);
            $emailAtual = $admin['email'];

            if ($emailExiste && $email !== $emailAtual) {
                setMensagem('erro', 'Este e-mail já está sendo utilizado por outro usuário.');
            } else {
                // Atualizar dados
                $dados = [
                    'nome' => $nome,
                    'email' => $email
                ];

                if ($adminModel->atualizar($adminId, $dados)) {
                    // Atualizar sessão
                    $_SESSION['admin_nome'] = $nome;

                    // Registrar ação
                    $historicoModel = new HistoricoAcao();
                    $historicoModel->registrar([
                        'admin_id' => $adminId,
                        'requerimento_id' => null,
                        'acao' => 'Atualizou dados do perfil'
                    ]);

                    setMensagem('sucesso', 'Dados atualizados com sucesso.');
                } else {
                    setMensagem('erro', 'Erro ao atualizar dados.');
                }
            }
        }

        $mensagem = getMensagem();
        // Recarregar dados do administrador
        $admin = $adminModel->buscarPorId($adminId);
    }

    // Atualizar senha
    if ($acao === 'atualizar_senha') {
        $senhaAtual = $_POST['senha_atual'] ?? '';
        $novaSenha = $_POST['nova_senha'] ?? '';
        $confirmaSenha = $_POST['confirma_senha'] ?? '';

        // Validar campos
        if (empty($senhaAtual) || empty($novaSenha) || empty($confirmaSenha)) {
            setMensagem('erro', 'Todos os campos de senha são obrigatórios.');
        } elseif ($novaSenha !== $confirmaSenha) {
            setMensagem('erro', 'A nova senha e a confirmação não coincidem.');
        } elseif (strlen($novaSenha) < 6) {
            setMensagem('erro', 'A nova senha deve ter pelo menos 6 caracteres.');
        } else {
            // Verificar senha atual
            if (password_verify($senhaAtual, $admin['senha'])) {
                // Atualizar senha
                $dados = [
                    'senha' => $novaSenha // O hash será feito no modelo
                ];

                if ($adminModel->atualizar($adminId, $dados)) {
                    // Registrar ação
                    $historicoModel = new HistoricoAcao();
                    $historicoModel->registrar([
                        'admin_id' => $adminId,
                        'requerimento_id' => null,
                        'acao' => 'Alterou a senha do perfil'
                    ]);

                    setMensagem('sucesso', 'Senha atualizada com sucesso.');
                } else {
                    setMensagem('erro', 'Erro ao atualizar senha.');
                }
            } else {
                setMensagem('erro', 'Senha atual incorreta.');
            }
        }

        $mensagem = getMensagem();
    }

    // Atualizar foto de perfil
    if ($acao === 'atualizar_foto') {
        if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
            $arquivo = $_FILES['foto_perfil'];
            $resultado = salvarArquivo($arquivo, $uploadDir, 'perfil_' . $adminId);

            if ($resultado) {
                // Remover foto antiga se existir e for diferente da padrão
                if (!empty($admin['foto_perfil']) && file_exists('../' . $admin['foto_perfil']) && $admin['foto_perfil'] !== 'assets/default-profile.png') {
                    unlink('../' . $admin['foto_perfil']);
                }

                // Atualizar caminho da foto no banco de dados - remover possível barra dupla
                $caminhoCorrigido = str_replace('//', '/', $resultado['caminho']);
                $dados = [
                    'foto_perfil' => $caminhoCorrigido
                ];

                if ($adminModel->atualizar($adminId, $dados)) {
                    // Registrar ação
                    $historicoModel = new HistoricoAcao();
                    $historicoModel->registrar([
                        'admin_id' => $adminId,
                        'requerimento_id' => null,
                        'acao' => 'Atualizou a foto de perfil'
                    ]);

                    setMensagem('sucesso', 'Foto de perfil atualizada com sucesso.');
                } else {
                    setMensagem('erro', 'Erro ao atualizar foto de perfil no banco de dados.');
                }
            } else {
                setMensagem('erro', 'Erro ao fazer upload da foto de perfil.');
            }
        } else {
            setMensagem('erro', 'Nenhuma foto selecionada ou erro no upload.');
        }

        $mensagem = getMensagem();
        // Recarregar dados do administrador
        $admin = $adminModel->buscarPorId($adminId);
    }
}

// Caminho da foto de perfil
$fotoPerfil = !empty($admin['foto_perfil']) ? $admin['foto_perfil'] : 'assets/default-profile.png';
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil - SEMA</title>
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
            overflow: hidden;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
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

        /* Profile Section */
        .profile-container {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        @media (max-width: 992px) {
            .profile-container {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .card-header {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #f8f9fa;
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

        /* Forms */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: var(--secondary-color);
            margin-bottom: 8px;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(0, 152, 81, 0.2);
        }

        /* Buttons */
        .btn {
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.3s, color 0.3s;
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

        /* Profile Photo */
        .profile-photo-container {
            text-align: center;
            padding: 20px;
        }

        .profile-photo {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            margin: 0 auto 20px;
            border: 3px solid var(--primary-color);
            overflow: hidden;
            position: relative;
        }

        .profile-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .photo-change-btn {
            position: absolute;
            bottom: 0;
            right: 0;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.3s;
        }

        .photo-change-btn:hover {
            background-color: var(--primary-dark);
        }

        .upload-btn-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
        }

        .upload-btn-wrapper input[type=file] {
            font-size: 100px;
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            cursor: pointer;
        }

        /* Alerts */
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

        /* Mobile Responsive */
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
            <a href="perfil.php" class="menu-item active">
                <i class="fas fa-user"></i> Meu Perfil
            </a>
        </div>

        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar">
                    <?php if (!empty($admin['foto_perfil'])): ?>
                        <img src="<?php echo BASE_URL . '/' . $admin['foto_perfil']; ?>" alt="Foto de Perfil">
                    <?php else: ?>
                        <?php echo strtoupper(substr($adminNome, 0, 1)); ?>
                    <?php endif; ?>
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
            <h1 class="page-title">Meu Perfil</h1>
        </div>

        <?php if ($mensagem): ?>
            <div class="alert alert-<?php echo $mensagem['tipo']; ?>">
                <?php echo $mensagem['texto']; ?>
            </div>
        <?php endif; ?>

        <div class="profile-container">
            <!-- Coluna da Foto -->
            <div class="left-column">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Foto de Perfil</h2>
                    </div>
                    <div class="card-body">
                        <div class="profile-photo-container">
                            <div class="profile-photo">
                                <?php if (!empty($admin['foto_perfil'])): ?>
                                    <img src="../<?php echo sanitize($admin['foto_perfil']); ?>" alt="Foto de Perfil">
                                <?php else: ?>
                                    <div style="width: 100%; height: 100%; background-color: var(--primary-dark); display: flex; align-items: center; justify-content: center; font-size: 60px; color: white;">
                                        <?php echo strtoupper(substr($adminNome, 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="photo-change-btn" onclick="document.getElementById('upload-photo-input').click()">
                                    <i class="fas fa-camera"></i>
                                </div>
                            </div>
                            <form action="" method="post" enctype="multipart/form-data" id="photo-form">
                                <input type="hidden" name="acao" value="atualizar_foto">
                                <input type="file" name="foto_perfil" id="upload-photo-input" accept="image/*" style="display: none" onchange="submitPhotoForm()">
                                <div class="form-group">
                                    <button type="button" class="btn btn-primary" onclick="document.getElementById('upload-photo-input').click()">
                                        Alterar Foto
                                    </button>
                                </div>
                            </form>
                        </div>
                        <div class="user-info-summary" style="text-align: center;">
                            <h3><?php echo sanitize($admin['nome']); ?></h3>
                            <p><?php echo $adminNivel == 'admin' ? 'Administrador' : 'Operador'; ?></p>
                            <p>Membro desde: <?php echo formatarData($admin['data_cadastro']); ?></p>
                            <p>Último acesso: <?php echo formatarData($admin['ultimo_acesso']); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Coluna de Dados -->
            <div class="right-column">
                <!-- Dados Pessoais -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Dados Pessoais</h2>
                    </div>
                    <div class="card-body">
                        <form action="" method="post">
                            <input type="hidden" name="acao" value="atualizar_dados">
                            <div class="form-group">
                                <label for="nome">Nome Completo</label>
                                <input type="text" class="form-control" id="nome" name="nome" value="<?php echo sanitize($admin['nome']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="email">E-mail</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo sanitize($admin['email']); ?>" required>
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Alterar Senha -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Alterar Senha</h2>
                    </div>
                    <div class="card-body">
                        <form action="" method="post">
                            <input type="hidden" name="acao" value="atualizar_senha">
                            <div class="form-group">
                                <label for="senha_atual">Senha Atual</label>
                                <input type="password" class="form-control" id="senha_atual" name="senha_atual" required>
                            </div>
                            <div class="form-group">
                                <label for="nova_senha">Nova Senha</label>
                                <input type="password" class="form-control" id="nova_senha" name="nova_senha" required minlength="6">
                            </div>
                            <div class="form-group">
                                <label for="confirma_senha">Confirmar Nova Senha</label>
                                <input type="password" class="form-control" id="confirma_senha" name="confirma_senha" required minlength="6">
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">Alterar Senha</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Para submeter automaticamente quando uma foto é selecionada
        function submitPhotoForm() {
            document.getElementById('photo-form').submit();
        }

        // Para implementação futura de toggle de sidebar em mobile
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