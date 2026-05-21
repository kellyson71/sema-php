<?php
require_once 'conexao.php';

// Requer sessão, mas não chama verificaLogin() para evitar loop de redirect
if (empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$adminId = (int) $_SESSION['admin_id'];
$erro = '';
$sucesso = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $novaSenha     = $_POST['nova_senha']     ?? '';
    $confirmarSenha = $_POST['confirmar_senha'] ?? '';

    if (strlen($novaSenha) < 8) {
        $erro = 'A nova senha deve ter pelo menos 8 caracteres.';
    } elseif ($novaSenha !== $confirmarSenha) {
        $erro = 'As senhas não coincidem.';
    } else {
        $hash = password_hash($novaSenha, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE administradores SET senha = ?, primeiro_acesso = 0 WHERE id = ?")
            ->execute([$hash, $adminId]);

        $_SESSION['admin_primeiro_acesso'] = false;
        $sucesso = true;

        header('Location: index.php?msg=senha_alterada');
        exit;
    }
}

$adminNome = $_SESSION['admin_nome'] ?? 'Administrador';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Definir nova senha — SEMA</title>
    <link rel="icon" href="../assets/img/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0d4a26 0%, #145c30 50%, #1a7240 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .card {
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 32px 64px rgba(0, 0, 0, .22);
            width: 100%;
            max-width: 440px;
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, #0d5433 0%, #1a7240 100%);
            padding: 32px 32px 28px;
            text-align: center;
        }

        .card-header .icon-wrap {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: rgba(255,255,255,.15);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }

        .card-header .icon-wrap i { font-size: 1.6rem; color: #fff; }

        .card-header h1 {
            color: #fff;
            font-size: 1.3rem;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .card-header p {
            color: rgba(255,255,255,.8);
            font-size: .88rem;
            line-height: 1.55;
        }

        .card-body { padding: 28px 32px 32px; }

        .alert-info {
            background: #eff9f2;
            border: 1px solid #b3dfc5;
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 22px;
            font-size: .85rem;
            color: #145c30;
            display: flex;
            gap: 10px;
            align-items: flex-start;
            line-height: 1.5;
        }

        .alert-info i { margin-top: 2px; flex-shrink: 0; }

        .alert-danger {
            background: #fff2f2;
            border: 1px solid #f5c2c2;
            border-radius: 14px;
            padding: 12px 16px;
            margin-bottom: 18px;
            font-size: .85rem;
            color: #9a2323;
        }

        label {
            display: block;
            font-size: .82rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 8px;
            letter-spacing: .02em;
        }

        .input-wrap {
            position: relative;
            margin-bottom: 16px;
        }

        .input-wrap input {
            width: 100%;
            height: 48px;
            border: 1.5px solid #d1d5db;
            border-radius: 14px;
            padding: 0 44px 0 14px;
            font-size: .92rem;
            color: #1e293b;
            background: #fff;
            transition: border-color .2s, box-shadow .2s;
            outline: none;
        }

        .input-wrap input:focus {
            border-color: #16a34a;
            box-shadow: 0 0 0 4px rgba(22, 163, 74, .1);
        }

        .input-wrap .toggle-pw {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #6b7280;
            font-size: .9rem;
            padding: 0;
        }

        .strength-bar {
            height: 4px;
            border-radius: 4px;
            background: #e5e7eb;
            margin-top: 8px;
            overflow: hidden;
        }

        .strength-bar-fill {
            height: 100%;
            border-radius: 4px;
            transition: width .3s, background .3s;
            width: 0;
        }

        .strength-label {
            font-size: .75rem;
            margin-top: 5px;
            color: #6b7280;
            height: 16px;
        }

        .btn-submit {
            width: 100%;
            height: 52px;
            border: none;
            border-radius: 16px;
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            color: #fff;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            margin-top: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: opacity .2s, transform .2s;
        }

        .btn-submit:hover { opacity: .92; transform: translateY(-1px); }
        .btn-submit:active { transform: translateY(0); }
    </style>
</head>
<body>
<div class="card">
    <div class="card-header">
        <div class="icon-wrap"><i class="fas fa-key"></i></div>
        <h1>Defina sua senha</h1>
        <p>Olá, <strong><?= htmlspecialchars($adminNome) ?></strong>. Por segurança, você precisa criar uma senha pessoal antes de continuar.</p>
    </div>
    <div class="card-body">
        <div class="alert-info">
            <i class="fas fa-circle-info"></i>
            <span>Esta é sua primeira entrada no sistema. Crie uma senha com pelo menos 8 caracteres. Você não poderá acessar o painel sem concluir este passo.</span>
        </div>

        <?php if ($erro): ?>
            <div class="alert-danger"><i class="fas fa-triangle-exclamation me-2"></i><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <form method="post" id="formTrocarSenha" autocomplete="off">
            <div class="input-wrap">
                <label for="nova_senha">Nova senha</label>
                <input type="password" id="nova_senha" name="nova_senha" required minlength="8"
                       placeholder="Mínimo 8 caracteres" autocomplete="new-password">
                <button type="button" class="toggle-pw" onclick="togglePw('nova_senha', this)" tabindex="-1">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
            <div class="strength-bar"><div class="strength-bar-fill" id="strengthFill"></div></div>
            <div class="strength-label" id="strengthLabel"></div>

            <div class="input-wrap" style="margin-top:16px;">
                <label for="confirmar_senha">Confirmar nova senha</label>
                <input type="password" id="confirmar_senha" name="confirmar_senha" required minlength="8"
                       placeholder="Repita a senha" autocomplete="new-password">
                <button type="button" class="toggle-pw" onclick="togglePw('confirmar_senha', this)" tabindex="-1">
                    <i class="fas fa-eye"></i>
                </button>
            </div>

            <button type="submit" class="btn-submit">
                <i class="fas fa-check"></i> Salvar e entrar no painel
            </button>
        </form>
    </div>
</div>

<script>
function togglePw(id, btn) {
    var input = document.getElementById(id);
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.querySelector('i').className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
}

var senhaInput = document.getElementById('nova_senha');
var fill = document.getElementById('strengthFill');
var label = document.getElementById('strengthLabel');

var colors = ['#ef4444', '#f97316', '#eab308', '#22c55e', '#16a34a'];
var labels = ['Muito fraca', 'Fraca', 'Razoável', 'Boa', 'Forte'];

senhaInput.addEventListener('input', function() {
    var v = this.value;
    var score = 0;
    if (v.length >= 8)  score++;
    if (v.length >= 12) score++;
    if (/[A-Z]/.test(v)) score++;
    if (/[0-9]/.test(v)) score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;
    score = Math.min(score, 4);

    if (v.length === 0) {
        fill.style.width = '0';
        label.textContent = '';
        return;
    }

    fill.style.width = ((score + 1) * 20) + '%';
    fill.style.background = colors[score];
    label.textContent = labels[score];
    label.style.color = colors[score];
});
</script>
</body>
</html>
