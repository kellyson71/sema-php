<?php
require_once 'conexao.php';

// Verificar se já está logado
if (isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

$erro = '';

// Processar formulário de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = trim($_POST['senha'] ?? '');

    // Credenciais fixas para teste
    if ($email === 'kellyson' && $senha === 'k') {
        $_SESSION['admin_id'] = 1;
        $_SESSION['admin_nome'] = 'Kellyson';
        $_SESSION['admin_email'] = 'kellyson@teste.com';
        $_SESSION['admin_nivel'] = 1;
        header("Location: index.php");
        exit;
    }

    if (empty($email) || empty($senha)) {
        $erro = "Por favor, preencha todos os campos.";
    } else {
        // Verificar credenciais no banco
        $stmt = $pdo->prepare("SELECT * FROM administradores WHERE email = ? AND ativo = 1");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($senha, $admin['senha'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_nome'] = $admin['nome'];
            $_SESSION['admin_email'] = $admin['email'];
            $_SESSION['admin_nivel'] = $admin['nivel'];

            // Atualizar último acesso
            $stmt = $pdo->prepare("UPDATE administradores SET ultimo_acesso = NOW() WHERE id = ?");
            $stmt->execute([$admin['id']]);

            header("Location: index.php");
            exit;
        } else {
            $erro = "E-mail ou senha incorretos.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Painel Administrativo SEMA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #009851;
            --primary-dark: #007840;
            --primary-light: #e6f7ef;
            --secondary-color: #333;
            --accent-color: #47AF8C;
            --background: 0 0% 100%;
            --foreground: 222.2 47.4% 11.2%;
            --card: 0 0% 100%;
            --card-foreground: 222.2 47.4% 11.2%;
            --border: 214.3 31.8% 91.4%;
            --input: 214.3 31.8% 91.4%;
            --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --ring: 222.2 84% 4.9%;
        }

        body {
            background: linear-gradient(135deg, var(--primary-light), #ffffff);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', system-ui, sans-serif;
            padding: 1rem;
        }

        .login-container {
            background-color: #fff;
            border-radius: 16px;
            padding: 2.5rem;
            box-shadow: var(--shadow);
            width: 400px;
            max-width: 100%;
            position: relative;
            overflow: hidden;
        }

        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
        }

        .login-logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-logo img {
            max-width: 180px;
            height: auto;
        }

        .login-title {
            color: var(--primary-color);
            margin-bottom: 2rem;
            text-align: center;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .login-form {
            margin-top: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            color: var(--secondary-color);
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .form-control-wrapper {
            position: relative;
        }

        .form-control {
            width: 100%;
            padding: 0.875rem 1rem 0.875rem 2.75rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.2s ease;
            background-color: #fff;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 152, 81, 0.1);
            outline: none;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--secondary-color);
            opacity: 0.5;
            font-size: 1rem;
        }

        .btn-primary {
            width: 100%;
            padding: 0.875rem;
            background-color: var(--primary-color);
            border: none;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.2s ease;
            margin-top: 1rem;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-1px);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .alert {
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 2rem;
            }
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-logo">
            <img src="../assets/SEMA/PNG/Azul/Logo SEMA Vertical.png" alt="Logo SEMA">
        </div>
        <h2 class="login-title">Acesso Administrativo</h2>

        <?php if ($erro): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $erro; ?>
            </div>
        <?php endif; ?>

        <form class="login-form" method="post" action="">
            <div class="form-group">
                <label class="form-label" for="email">Usuário</label>
                <div class="form-control-wrapper">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text"
                        class="form-control"
                        id="email"
                        name="email"
                        required
                        placeholder="Digite seu usuário"
                        value="kellyson">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="senha">Senha</label>
                <div class="form-control-wrapper">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password"
                        class="form-control"
                        id="senha"
                        name="senha"
                        required
                        placeholder="Digite sua senha"
                        value="k">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-sign-in-alt me-2"></i>
                Entrar
            </button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('.login-container').style.opacity = '0';
            setTimeout(() => {
                document.querySelector('.login-container').style.transition = 'opacity 0.5s ease';
                document.querySelector('.login-container').style.opacity = '1';
            }, 100);
        });
    </script>
</body>

</html>