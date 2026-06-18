<?php
require_once 'conexao.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$adminId = (int) $_SESSION['admin_id'];
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $novaSenha      = $_POST['nova_senha']      ?? '';
    $confirmarSenha = $_POST['confirmar_senha'] ?? '';

    $temMaiuscula  = preg_match('/[A-Z]/', $novaSenha);
    $temMinuscula  = preg_match('/[a-z]/', $novaSenha);
    $temNumero     = preg_match('/[0-9]/', $novaSenha);
    $temTamanho    = strlen($novaSenha) >= 8;

    if (!$temTamanho || !$temMaiuscula || !$temMinuscula || !$temNumero) {
        $erro = 'A senha não atende aos requisitos mínimos.';
    } elseif ($novaSenha !== $confirmarSenha) {
        $erro = 'As senhas não coincidem.';
    } else {
        $hash = password_hash($novaSenha, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE administradores SET senha = ?, primeiro_acesso = 0 WHERE id = ?")
            ->execute([$hash, $adminId]);
        $_SESSION['admin_primeiro_acesso'] = false;
        header('Location: index.php?msg=senha_alterada');
        exit;
    }
}

$adminNome = $_SESSION['admin_nome'] ?? 'Administrador';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Definir nova senha — SEMA</title>
    <link rel="icon" href="../assets/img/favicon.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter+Tight:wght@700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #009851;
            --primary-600: #007840;
            --primary-700: #0b5d38;
            --primary-100: #cfeedd;
            --ink: #10233d;
            --ink-2: #475569;
            --ink-3: #64748b;
            --line: #e2e8f0;
            --line-2: #cbd5e1;
            --bg: #f8fafc;
            --card: #ffffff;
            --danger: #dc2626;
            --success: #16a34a;
            --radius: 10px;
            --radius-lg: 14px;
            --shadow-card: 0 1px 2px rgba(15,23,42,.04), 0 8px 24px -8px rgba(15,23,42,.10);
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg);
            color: var(--ink);
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
            display: grid;
            grid-template-columns: minmax(380px, 1.1fr) 1fr;
        }

        /* ── Painel esquerdo (brand) ── */
        .brand-panel {
            position: relative;
            background: linear-gradient(160deg, var(--primary-700) 0%, var(--primary) 60%, var(--primary-600) 100%);
            color: #fff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 44px 48px;
            overflow: hidden;
        }
        .brand-glow {
            position: absolute; right: -100px; top: -100px;
            width: 400px; height: 400px; border-radius: 50%;
            background: radial-gradient(circle, rgba(255,255,255,.13), transparent 70%);
            filter: blur(20px); pointer-events: none;
        }
        .brand-top { position: relative; z-index: 1; }
        .brand-badge {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 5px 12px; border-radius: 999px;
            background: rgba(255,255,255,.10); border: 1px solid rgba(255,255,255,.20);
            font-size: 11px; font-weight: 600; letter-spacing: .08em; text-transform: uppercase;
        }
        .brand-logo { margin-top: 44px; width: 180px; display: block; filter: drop-shadow(0 2px 8px rgba(0,0,0,.20)); }
        .brand-middle { position: relative; z-index: 1; margin-top: 32px; }
        .brand-middle h1 {
            font-family: 'Inter Tight', sans-serif;
            font-size: 36px; font-weight: 700; line-height: 1.1;
            letter-spacing: -.025em; margin-bottom: 12px;
        }
        .brand-middle p { font-size: 14px; line-height: 1.65; color: rgba(255,255,255,.8); max-width: 360px; }
        .brand-bottom { position: relative; z-index: 1; }
        .brand-bottom small { font-size: 12px; color: rgba(255,255,255,.45); }

        /* ── Painel direito (form) ── */
        .form-panel {
            display: flex; align-items: center; justify-content: center;
            padding: 60px 32px; background: var(--bg);
        }
        .card {
            width: 100%; max-width: 420px;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-card);
            padding: 36px 36px 32px;
        }
        .card-title {
            font-family: 'Inter Tight', sans-serif;
            font-size: 22px; font-weight: 700;
            letter-spacing: -.02em; margin-bottom: 4px;
        }
        .card-sub { font-size: 13.5px; color: var(--ink-3); margin-bottom: 24px; line-height: 1.5; }

        /* ── Alerta de erro ── */
        .alert-danger {
            background: #fef2f2; border: 1px solid #fecaca;
            border-radius: var(--radius); padding: 12px 14px;
            margin-bottom: 18px; font-size: 13px; color: var(--danger);
            display: flex; gap: 9px; align-items: flex-start;
        }
        .alert-danger i { margin-top: 1px; flex-shrink: 0; }

        /* ── Campos ── */
        .field { margin-bottom: 16px; }
        .field-label {
            display: block; font-size: 11.5px; font-weight: 600;
            letter-spacing: .05em; text-transform: uppercase;
            color: var(--ink-2); margin-bottom: 7px;
        }
        .field-wrap {
            position: relative; display: flex; align-items: center;
            border: 1px solid var(--line-2); border-radius: var(--radius);
            background: var(--card); transition: border-color .15s, box-shadow .15s;
        }
        .field-wrap:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0,152,81,.14);
        }
        .field-wrap.has-error { border-color: var(--danger); }
        .field-wrap input {
            flex: 1; height: 44px;
            border: none; outline: none; background: transparent;
            padding: 0 42px 0 13px; font-size: 14px; color: var(--ink);
            font-family: inherit;
        }
        .field-wrap input::placeholder { color: #94a3b8; }
        .toggle-pw {
            position: absolute; right: 12px;
            background: none; border: none; cursor: pointer;
            color: var(--ink-3); font-size: 13px; padding: 4px;
            line-height: 1;
        }
        .toggle-pw:hover { color: var(--ink); }

        /* ── Barra de força ── */
        .strength-track {
            height: 3px; border-radius: 99px; background: var(--line);
            margin-top: 7px; overflow: hidden;
        }
        .strength-fill {
            height: 100%; border-radius: 99px;
            transition: width .3s ease, background .3s ease; width: 0;
        }
        .strength-text {
            font-size: 11.5px; margin-top: 4px; height: 16px;
            color: var(--ink-3); font-weight: 500;
        }

        /* ── Requisitos ── */
        .reqs {
            margin-top: 12px; margin-bottom: 6px;
            display: grid; grid-template-columns: 1fr 1fr;
            gap: 6px 12px;
        }
        .req {
            display: flex; align-items: center; gap: 7px;
            font-size: 12.5px; color: var(--ink-3); transition: color .2s;
        }
        .req .dot {
            width: 16px; height: 16px; border-radius: 50%; flex-shrink: 0;
            border: 1.5px solid var(--line-2); display: flex;
            align-items: center; justify-content: center;
            font-size: 9px; transition: all .2s;
        }
        .req.ok .dot {
            background: var(--success); border-color: var(--success); color: #fff;
        }
        .req.ok { color: var(--success); }

        /* ── Botão ── */
        .btn-submit {
            width: 100%; height: 46px; border: none;
            border-radius: var(--radius);
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-600) 100%);
            color: #fff; font-size: 14px; font-weight: 600;
            cursor: pointer; margin-top: 20px;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            transition: opacity .15s, transform .15s; font-family: inherit;
        }
        .btn-submit:hover { opacity: .92; transform: translateY(-1px); }
        .btn-submit:disabled { opacity: .5; cursor: not-allowed; transform: none; }

        /* ── Responsive ── */
        @media (max-width: 720px) {
            body { grid-template-columns: 1fr; }
            .brand-panel { display: none; }
            .form-panel { padding: 40px 20px; }
        }
    </style>
</head>
<body>

<!-- Painel de marca -->
<div class="brand-panel">
    <div class="brand-glow"></div>
    <div class="brand-top">
        <span class="brand-badge">
            <i class="fas fa-shield-halved"></i> Acesso seguro
        </span>
        <img src="../assets/img/Logo_sema.png" alt="SEMA" class="brand-logo">
    </div>
    <div class="brand-middle">
        <h1>Primeiro acesso ao sistema</h1>
        <p>Para garantir a segurança do seu perfil, você precisa criar uma senha pessoal antes de continuar. Ela substitui a senha temporária fornecida pelo administrador.</p>
    </div>
    <div class="brand-bottom">
        <small>Secretaria Municipal de Meio Ambiente — Pau dos Ferros/RN</small>
    </div>
</div>

<!-- Painel do formulário -->
<div class="form-panel">
    <div class="card">
        <p class="card-title">Crie sua senha</p>
        <p class="card-sub">Olá, <strong><?= htmlspecialchars($adminNome) ?></strong>. Defina uma senha pessoal para acessar o painel.</p>

        <?php if ($erro): ?>
        <div class="alert-danger">
            <i class="fas fa-circle-exclamation"></i>
            <span><?= htmlspecialchars($erro) ?></span>
        </div>
        <?php endif; ?>

        <form method="post" id="frm" autocomplete="off" novalidate>

            <div class="field">
                <label class="field-label" for="nova_senha">Nova senha</label>
                <div class="field-wrap" id="wrap1">
                    <input type="password" id="nova_senha" name="nova_senha"
                           placeholder="Mínimo 8 caracteres" autocomplete="new-password">
                    <button type="button" class="toggle-pw" onclick="togglePw('nova_senha','ico1')" tabindex="-1">
                        <i class="fas fa-eye" id="ico1"></i>
                    </button>
                </div>
                <div class="strength-track"><div class="strength-fill" id="sFill"></div></div>
                <div class="strength-text" id="sText"></div>

                <!-- Requisitos visuais -->
                <div class="reqs">
                    <div class="req" id="req-len">
                        <span class="dot"><i class="fas fa-check"></i></span> Mínimo 8 caracteres
                    </div>
                    <div class="req" id="req-upper">
                        <span class="dot"><i class="fas fa-check"></i></span> Letra maiúscula
                    </div>
                    <div class="req" id="req-lower">
                        <span class="dot"><i class="fas fa-check"></i></span> Letra minúscula
                    </div>
                    <div class="req" id="req-num">
                        <span class="dot"><i class="fas fa-check"></i></span> Número
                    </div>
                </div>
            </div>

            <div class="field">
                <label class="field-label" for="confirmar_senha">Confirmar senha</label>
                <div class="field-wrap" id="wrap2">
                    <input type="password" id="confirmar_senha" name="confirmar_senha"
                           placeholder="Repita a senha" autocomplete="new-password">
                    <button type="button" class="toggle-pw" onclick="togglePw('confirmar_senha','ico2')" tabindex="-1">
                        <i class="fas fa-eye" id="ico2"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-submit" id="btnSubmit" disabled>
                <i class="fas fa-check"></i> Salvar e entrar no painel
            </button>
        </form>
    </div>
</div>

<script>
function togglePw(id, icoId) {
    var el = document.getElementById(id);
    var show = el.type === 'password';
    el.type = show ? 'text' : 'password';
    document.getElementById(icoId).className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
}

var senhaEl    = document.getElementById('nova_senha');
var confirmEl  = document.getElementById('confirmar_senha');
var fill       = document.getElementById('sFill');
var sText      = document.getElementById('sText');
var btnSubmit  = document.getElementById('btnSubmit');
var wrap2      = document.getElementById('wrap2');

var strengths  = ['Muito fraca','Fraca','Razoável','Boa','Forte'];
var colors     = ['#ef4444','#f97316','#eab308','#22c55e','#16a34a'];

function checkReq(id, ok) {
    document.getElementById(id).classList.toggle('ok', ok);
}

function validarSenha() {
    var v = senhaEl.value;
    var len   = v.length >= 8;
    var upper = /[A-Z]/.test(v);
    var lower = /[a-z]/.test(v);
    var num   = /[0-9]/.test(v);

    checkReq('req-len',   len);
    checkReq('req-upper', upper);
    checkReq('req-lower', lower);
    checkReq('req-num',   num);

    var score = [v.length >= 8, v.length >= 12, upper, num, /[^A-Za-z0-9]/.test(v)]
                .filter(Boolean).length;
    score = Math.min(score - 1, 4);

    if (v.length === 0) {
        fill.style.width = '0'; sText.textContent = ''; sText.style.color = '';
    } else {
        score = Math.max(score, 0);
        fill.style.width = ((score + 1) * 20) + '%';
        fill.style.background = colors[score];
        sText.textContent = strengths[score];
        sText.style.color = colors[score];
    }

    atualizar();
}

function atualizar() {
    var v  = senhaEl.value;
    var v2 = confirmEl.value;
    var valida = v.length >= 8 && /[A-Z]/.test(v) && /[a-z]/.test(v) && /[0-9]/.test(v);
    var coincidem = v === v2 && v2.length > 0;

    btnSubmit.disabled = !(valida && coincidem);
    wrap2.classList.toggle('has-error', v2.length > 0 && !coincidem);
}

senhaEl.addEventListener('input', validarSenha);
confirmEl.addEventListener('input', atualizar);
</script>
</body>
</html>
