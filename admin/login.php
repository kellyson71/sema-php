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

    if (empty($email) || empty($senha)) {
        $erro = "Por favor, preencha todos os campos.";
    } else {
        // Verificar credenciais
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
            --primary-color: #2D8661;
            --secondary-color: #134E5E;
            --accent-color: #47AF8C;
        }

        body {
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            background-color: #fff;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            width: 400px;
            max-width: 90%;
        }

        .login-logo {
            text-align: center;
            margin-bottom: 25px;
        }

        .login-title {
            color: var(--primary-color);
            margin-bottom: 20px;
            text-align: center;
        }

        .login-form .form-control {
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #ddd;
            margin-bottom: 15px;
        }

        .login-form .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 12px;
            font-weight: 600;
            width: 100%;
            border-radius: 8px;
        }

        .login-form .btn-primary:hover {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
        }

        .alert {
            border-radius: 8px;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-logo">
            <h1><i class="fas fa-leaf text-success"></i> SEMA</h1>
        </div>
        <h2 class="login-title">Acesso Administrativo</h2>

        <?php if ($erro): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $erro; ?>
            </div>
        <?php endif; ?>

        <form class="login-form" method="post" action="">
            <div class="mb-3">
                <label for="email" class="form-label"><i class="fas fa-envelope"></i> E-mail</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="mb-3">
                <label for="senha" class="form-label"><i class="fas fa-lock"></i> Senha</label>
                <input type="password" class="form-control" id="senha" name="senha" required>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i> Entrar
            </button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>