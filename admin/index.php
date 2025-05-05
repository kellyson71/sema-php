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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #009851;
            --primary-dark: #007840;
            --primary-light: #e6f7ef;
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
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-image: url('../assets/O6DXV10.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        body::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.6) 0%, rgba(0, 0, 0, 0.3) 100%);
            z-index: 1;
        }

        .data-atual {
            position: absolute;
            top: 15px;
            right: 15px;
            color: white;
            font-size: 14px;
            z-index: 10;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.6);
        }

        main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            z-index: 2;
        }

        .login-container {
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 420px;
            padding: 35px;
            text-align: center;
            backdrop-filter: blur(10px);
            transform: translateY(0);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .login-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.25);
        }

        .header-container {
            margin-bottom: 30px;
        }

        .logo {
            margin-bottom: 15px;
            position: relative;
            display: inline-block;
        }

        .logo img {
            max-width: 110px;
            height: auto;
            transition: transform 0.3s ease;
            filter: drop-shadow(0 3px 5px rgba(0, 0, 0, 0.1));
        }

        .logo:hover img {
            transform: scale(1.05);
        }

        h1 {
            color: var(--primary-color);
            margin-bottom: 10px;
            font-size: 26px;
            font-weight: 600;
            position: relative;
            padding-bottom: 10px;
        }

        h1::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            height: 3px;
            width: 60px;
            background: var(--primary-color);
            border-radius: 3px;
        }

        .subtitle {
            color: var(--secondary-color);
            font-size: 14px;
            margin-top: 5px;
            opacity: 0.8;
        }

        form {
            text-align: left;
            margin-top: 20px;
        }

        .form-group {
            margin-bottom: 22px;
            position: relative;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: var(--secondary-color);
            font-weight: 500;
            font-size: 14px;
        }

        .input-group {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 18px;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px 12px 12px 40px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
            background-color: #fff;
        }

        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 152, 81, 0.15);
            background-color: var(--primary-light);
        }

        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(to right, var(--primary-color), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }

        button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(to right, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.6s ease;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 152, 81, 0.3);
        }

        button:hover::before {
            left: 100%;
        }

        .voltar-link {
            display: inline-block;
            margin-top: 25px;
            color: var(--primary-color);
            text-decoration: none;
            font-size: 15px;
            font-weight: 500;
            transition: transform 0.3s ease, color 0.3s ease;
        }

        .voltar-link:hover {
            color: var(--primary-dark);
            transform: translateX(-5px);
        }

        .voltar-link i {
            margin-right: 5px;
        }

        .mensagem {
            padding: 14px;
            margin-bottom: 20px;
            border-radius: 8px;
            text-align: center;
            font-size: 14px;
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .mensagem-sucesso {
            background-color: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .mensagem-erro {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .mensagem-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }

        .mensagem-alerta {
            background-color: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }

        .footer {
            margin-top: 25px;
            font-size: 12px;
            color: #777;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 25px;
            }

            h1 {
                font-size: 22px;
            }
        }
    </style>
</head>

<body>
    <div class="data-atual">
        <i class="fas fa-calendar-alt"></i> <?php echo date('d/m/Y'); ?>
    </div>

    <main>
        <div class="login-container">
            <div class="header-container">
                <div class="logo">
                    <img src="../assets/prefeitura-logo.png" alt="Prefeitura de Pau dos Ferros">
                </div>
                <h1>Área Administrativa</h1>
                <div class="subtitle">Secretaria Municipal do Ambiente - SEMA</div>
            </div>

            <?php if ($mensagem): ?>
                <div class="mensagem mensagem-<?php echo $mensagem['tipo']; ?>">
                    <i class="fas fa-<?php
                                        echo $mensagem['tipo'] === 'sucesso' ? 'check-circle' : ($mensagem['tipo'] === 'erro' ? 'exclamation-circle' : ($mensagem['tipo'] === 'info' ? 'info-circle' : 'exclamation-triangle'));
                                        ?>"></i>
                    <?php echo $mensagem['texto']; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="">
                <div class="form-group">
                    <label for="email">Email</label>
                    <div class="input-group">
                        <i class="input-icon fas fa-envelope"></i>
                        <input type="email" id="email" name="email" placeholder="Digite seu email institucional" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="senha">Senha</label>
                    <div class="input-group">
                        <i class="input-icon fas fa-lock"></i>
                        <input type="password" id="senha" name="senha" placeholder="Digite sua senha" required>
                    </div>
                </div>

                <button type="submit">
                    <i class="fas fa-sign-in-alt"></i> Entrar no Sistema
                </button>
            </form>

            <a href="../index.php" class="voltar-link">
                <i class="fas fa-arrow-left"></i> Voltar para o site principal
            </a>

            <div class="footer">
                SEMA © <?php echo date('Y'); ?> - Sistema de Gerenciamento Ambiental
            </div>
        </div>
    </main>
</body>

</html>