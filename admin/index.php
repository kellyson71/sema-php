<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/models.php';

// Se já estiver logado, redireciona para o dashboard
if (isset($_SESSION['admin_id'])) {
    redirect('dashboard.php');
}

$mensagem = getMensagem();

// Processar o formulário de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';

    if (empty($email) || empty($senha)) {
        setMensagem('erro', 'Por favor, preencha todos os campos.');
    } else {
        $adminModel = new Administrador();
        $admin = $adminModel->autenticar($email, $senha);

        if ($admin) {
            // Login bem-sucedido
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_nome'] = $admin['nome'];
            $_SESSION['admin_nivel'] = $admin['nivel'];
            $_SESSION['admin'] = true;

            // Registrar ação de login no histórico
            $historicoModel = new HistoricoAcao();
            $historicoModel->registrar([
                'admin_id' => $admin['id'],
                'requerimento_id' => null,
                'acao' => 'Login no sistema administrativo'
            ]);

            setMensagem('sucesso', 'Login realizado com sucesso!');
            redirect('dashboard.php');
        } else {
            // Login falhou
            setMensagem('erro', 'Email ou senha incorretos.');
        }
    }

    $mensagem = getMensagem();
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Administrativo - SEMA</title>
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
        flex-direction: column;
    }

    main {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .login-container {
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        width: 100%;
        max-width: 400px;
        padding: 30px;
        text-align: center;
    }

    .logo {
        margin-bottom: 20px;
    }

    .logo img {
        max-width: 100px;
        height: auto;
    }

    h1 {
        color: var(--primary-color);
        margin-bottom: 20px;
        font-size: 24px;
    }

    form {
        text-align: left;
    }

    .form-group {
        margin-bottom: 20px;
    }

    label {
        display: block;
        margin-bottom: 5px;
        color: var(--secondary-color);
        font-weight: 500;
    }

    input[type="email"],
    input[type="password"] {
        width: 100%;
        padding: 12px;
        border: 1px solid var(--border-color);
        border-radius: 4px;
        font-size: 16px;
        transition: border-color 0.3s;
    }

    input[type="email"]:focus,
    input[type="password"]:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 2px rgba(0, 152, 81, 0.2);
    }

    button {
        width: 100%;
        padding: 12px;
        background-color: var(--primary-color);
        color: white;
        border: none;
        border-radius: 4px;
        font-size: 16px;
        font-weight: 500;
        cursor: pointer;
        transition: background-color 0.3s;
    }

    button:hover {
        background-color: var(--primary-dark);
    }

    .voltar-link {
        display: block;
        margin-top: 20px;
        color: var(--primary-color);
        text-decoration: none;
    }

    .voltar-link:hover {
        text-decoration: underline;
    }

    .mensagem {
        padding: 12px;
        margin-bottom: 20px;
        border-radius: 4px;
        text-align: center;
    }

    .mensagem-sucesso {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .mensagem-erro {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .mensagem-info {
        background-color: #d1ecf1;
        color: #0c5460;
        border: 1px solid #bee5eb;
    }

    .mensagem-alerta {
        background-color: #fff3cd;
        color: #856404;
        border: 1px solid #ffeeba;
    }
    </style>
</head>

<body>
    <main>
        <div class="login-container">
            <div class="logo">
                <img src="../assets/prefeitura-logo.png" alt="Prefeitura de Pau dos Ferros">
            </div>
            <h1>Área Administrativa - SEMA</h1>

            <?php if ($mensagem): ?>
            <div class="mensagem mensagem-<?php echo $mensagem['tipo']; ?>">
                <?php echo $mensagem['texto']; ?>
            </div>
            <?php endif; ?>

            <form method="post" action="">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="Digite seu email" required>
                </div>

                <div class="form-group">
                    <label for="senha">Senha</label>
                    <input type="password" id="senha" name="senha" placeholder="Digite sua senha" required>
                </div>

                <button type="submit">
                    <i class="fas fa-sign-in-alt"></i> Entrar
                </button>
            </form>

            <a href="../index.php" class="voltar-link">
                <i class="fas fa-arrow-left"></i> Voltar para o site
            </a>
        </div>
    </main>
</body>

</html>