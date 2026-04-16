<?php
        require_once 'conexao.php';
        require_once '../includes/email_service.php';

        // Redireciona apenas o ambiente principal. A homologação vive em /homologacao/.
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $isHomologRequest = defined('MODO_HOMOLOG') && MODO_HOMOLOG;
        if (!$isHomologRequest && preg_match('/^(www\.)?sema\.protocolosead\.com$/i', $host)) {
            $redirect_url = 'http://sema.paudosferros.rn.gov.br' . $requestUri;
            header("HTTP/1.1 301 Moved Permanently");
            header("Location: $redirect_url");
            exit();
        }

// Garante que a tabela de dispositivos confiáveis exista
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS dispositivos_confiados (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        token_hash VARCHAR(64) NOT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent VARCHAR(500) DEFAULT NULL,
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
        expira_em DATETIME NOT NULL,
        INDEX idx_token (token_hash),
        INDEX idx_admin (admin_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

// Valida que o redirect é relativo (sem protocolo/domínio externo)
function sanitizarRedirect($url, $fallback = 'index.php') {
    $url = trim($url ?? '');
    if (preg_match('#^(https?:)?//#i', $url) || strpos($url, ':') !== false) {
        return $fallback;
    }
    return $url ?: $fallback;
}

// Verificar se já está logado
if (isset($_SESSION['admin_id'])) {
    $redirect = sanitizarRedirect($_GET['redirect'] ?? '', 'index.php');
    header("Location: " . $redirect);
    exit;
}

if (isset($_GET['redirect'])) {
    $_SESSION['login_redirect_url'] = sanitizarRedirect($_GET['redirect']);
}

$erro = '';

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt_time'] = time();
}

if (time() - $_SESSION['last_attempt_time'] > 900) {
    $_SESSION['login_attempts'] = 0;
}

if ($_SESSION['login_attempts'] >= 5) {
    $tempo_restante = 900 - (time() - $_SESSION['last_attempt_time']);
    if ($tempo_restante > 0) {
        $erro = "Muitas tentativas de login. Tente novamente em " . ceil($tempo_restante / 60) . " minutos.";
        $_SESSION['login_attempts'] = 5;
    } else {
        $_SESSION['login_attempts'] = 0;
    }
}

/**
 * Verifica se o dispositivo atual é confiável (cookie + DB).
 * Retorna o admin_id se confiável, ou false.
 */
function verificarDispositivoConfiavel($pdo) {
    $token = $_COOKIE['sema_trusted_device'] ?? '';
    if (empty($token) || strlen($token) !== 64) return false;

    $hash = hash('sha256', $token);
    $stmt = $pdo->prepare("SELECT admin_id FROM dispositivos_confiados WHERE token_hash = ? AND expira_em > NOW() LIMIT 1");
    $stmt->execute([$hash]);
    $row = $stmt->fetch();
    return $row ? (int)$row['admin_id'] : false;
}

/**
 * Registra o dispositivo como confiável por 30 dias.
 */
function registrarDispositivoConfiavel($pdo, $adminId) {
    $token = bin2hex(random_bytes(32)); // 64 chars hex
    $hash = hash('sha256', $token);
    $expira = date('Y-m-d H:i:s', time() + 30 * 24 * 3600);

    $stmt = $pdo->prepare("INSERT INTO dispositivos_confiados (admin_id, token_hash, ip_address, user_agent, expira_em) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$adminId, $hash, $_SERVER['REMOTE_ADDR'] ?? '', substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500), $expira]);

    $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    setcookie('sema_trusted_device', $token, [
        'expires'  => time() + 30 * 24 * 3600,
        'path'     => '/',
        'secure'   => $isSecure,
        'httponly'  => true,
        'samesite' => 'Strict',
    ]);

    // Limpa tokens expirados (manutenção)
    $pdo->exec("DELETE FROM dispositivos_confiados WHERE expira_em < NOW()");
}

/**
 * Cria a sessão de admin (reutilizado em login normal e dispositivo confiável).
 */
function criarSessaoAdmin($pdo, $admin) {
    session_regenerate_id(true);
    $_SESSION['login_attempts'] = 0;
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_nome'] = $admin['nome'];
    $_SESSION['admin_nome_completo'] = $admin['nome_completo'] ?? $admin['nome'];
    $_SESSION['admin_email'] = $admin['email'];
    $_SESSION['admin_nivel'] = $admin['nivel'];
    $_SESSION['admin_cpf'] = $admin['cpf'] ?? '';
    $_SESSION['admin_cargo'] = $admin['cargo'] ?? 'Administrador';
    $_SESSION['admin_matricula_portaria'] = $admin['matricula_portaria'] ?? '';
    $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'];
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
    $_SESSION['assinatura_auth_valid_until'] = time() + (24 * 60 * 60);

    $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    setcookie('sema_auth_persist', hash('sha256', $admin['id'] . $_SERVER['HTTP_USER_AGENT']), [
        'expires'  => time() + 43200,
        'path'     => '/',
        'secure'   => $isSecure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    $stmt = $pdo->prepare("UPDATE administradores SET ultimo_acesso = NOW() WHERE id = ?");
    $stmt->execute([$admin['id']]);
}

// Processar formulário de login via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SESSION['login_attempts'] < 5) {
    if (isset($_POST['action']) && $_POST['action'] === 'validar_credenciais') {
        header('Content-Type: application/json');

        $usuario = trim($_POST['usuario'] ?? '');
        $senha = trim($_POST['senha'] ?? '');
        $recaptcha_token = $_POST['recaptcha_token'] ?? '';

        $recaptcha_data = (object) ['success' => true, 'score' => 1.0];
        if (!MODO_HOMOLOG) {
            $recaptcha_url = 'https://www.google.com/recaptcha/api/siteverify';
            $recaptcha_response = @file_get_contents($recaptcha_url . '?secret=' . RECAPTCHA_SECRET_KEY . '&response=' . $recaptcha_token);
            $recaptcha_data = json_decode($recaptcha_response ?: '{}');
        }

        if (empty($usuario) || empty($senha)) {
            if (ob_get_length()) ob_clean();
            echo json_encode(['success' => false, 'error' => "Por favor, preencha todos os campos."]);
            exit;
        } elseif (!$recaptcha_data->success || $recaptcha_data->score < 0.5) {
            if (ob_get_length()) ob_clean();
            echo json_encode(['success' => false, 'error' => "Falha na verificação de segurança (reCAPTCHA). Por favor, tente novamente."]);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM administradores WHERE nome = ? AND ativo = 1");
        $stmt->execute([$usuario]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($senha, $admin['senha'])) {
            // Verificar dispositivo confiável
            $trustedAdminId = verificarDispositivoConfiavel($pdo);
            if ($trustedAdminId === (int)$admin['id']) {
                // Dispositivo confiável: pula 2FA
                criarSessaoAdmin($pdo, $admin);
                $redirectUrl = $_SESSION['login_redirect_url'] ?? 'index.php';
                unset($_SESSION['login_redirect_url']);
                if (ob_get_length()) ob_clean();
                echo json_encode(['success' => true, 'skip_2fa' => true, 'redirect' => $redirectUrl]);
                exit;
            }

            $hasTotp = !empty($admin['totp_secret']);

            if (!$hasTotp && empty($admin['email'])) {
                if (ob_get_length()) ob_clean();
                echo json_encode(['success' => false, 'error' => "Usuário não possui e-mail ou App Autenticador cadastrado para verificação em duas etapas."]);
                exit;
            }

            $codigo = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $_SESSION['2fa_admin_data'] = $admin;
            $_SESSION['2fa_otp_code'] = $codigo;
            $_SESSION['2fa_otp_expires'] = time() + (15 * 60);

            $emailMascarado = '';
            if (!empty($admin['email'])) {
                $emailService = new EmailService();
                $enviado = $emailService->enviarEmailCodigoVerificacao($admin['email'], $admin['nome'], $codigo);
                if ($enviado) {
                    $emailMascarado = preg_replace('/(?<=.).(?=.*@)/', '*', $admin['email']);
                } else if (!$hasTotp) {
                    if (ob_get_length()) ob_clean();
                    echo json_encode(['success' => false, 'error' => "Erro ao enviar e-mail de código de verificação."]);
                    exit;
                }
            }

            if (ob_get_length()) ob_clean();
            echo json_encode([
                'success' => true,
                'email_mascarado' => $emailMascarado,
                'has_totp' => $hasTotp
            ]);
        } else {
            $_SESSION['login_attempts']++;
            $_SESSION['last_attempt_time'] = time();
            if (ob_get_length()) ob_clean();
            echo json_encode(['success' => false, 'error' => "Usuário ou senha incorretos."]);
        }
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'validar_otp') {
        header('Content-Type: application/json');
        $codigoRecebido = trim($_POST['codigo'] ?? '');
        $lembrarDispositivo = ($_POST['lembrar_dispositivo'] ?? '') === '1';

        if (!isset($_SESSION['2fa_admin_data'])) {
            if (ob_get_length()) ob_clean();
            echo json_encode(['success' => false, 'error' => 'Sessão de verificação expirada ou inválida.']);
            exit;
        }

        $admin = $_SESSION['2fa_admin_data'];
        $codigoValido = false;
        $erroMsg = 'Código incorreto.';

        // 1. Tentar TOTP
        if (!empty($admin['totp_secret'])) {
            require_once 'MultiFactorService.php';
            $twoFactorService = new \Admin\Services\TwoFactorService();
            if ($twoFactorService->verify($admin['totp_secret'], $codigoRecebido)) {
                $codigoValido = true;
            }
        }

        // 2. Fallback e-mail
        if (!$codigoValido && isset($_SESSION['2fa_otp_code'])) {
            if (time() > $_SESSION['2fa_otp_expires']) {
                $erroMsg = 'Código expirado. Volte e faça login novamente.';
            } else if ($codigoRecebido === $_SESSION['2fa_otp_code']) {
                $codigoValido = true;
            }
        }

        if ($codigoValido) {
            criarSessaoAdmin($pdo, $admin);

            // Registrar dispositivo confiável se solicitado
            if ($lembrarDispositivo) {
                registrarDispositivoConfiavel($pdo, $admin['id']);
            }

            unset($_SESSION['2fa_admin_data'], $_SESSION['2fa_otp_code'], $_SESSION['2fa_otp_expires']);

            $redirectUrl = $_SESSION['login_redirect_url'] ?? 'index.php';
            unset($_SESSION['login_redirect_url']);

            if (ob_get_length()) ob_clean();
            echo json_encode(['success' => true, 'redirect' => $redirectUrl]);
        } else {
            if (ob_get_length()) ob_clean();
            echo json_encode(['success' => false, 'error' => $erroMsg]);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Painel Administrativo SEMA</title>
    <link rel="icon" href="../assets/img/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://www.google.com/recaptcha/api.js?render=<?php echo RECAPTCHA_SITE_KEY; ?>"></script>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{
            min-height:100vh;
            display:flex;
            flex-direction:column;
            font-family:'Inter',system-ui,-apple-system,sans-serif;
            background:#0f172a;
            color:#e2e8f0;
        }

        .login-wrapper{
            flex:1;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:2rem 1rem;
            position:relative;
            overflow:hidden;
        }

        /* Orbs decorativas de fundo */
        .login-wrapper::before,
        .login-wrapper::after{
            content:'';position:absolute;border-radius:50%;filter:blur(80px);pointer-events:none;
        }
        .login-wrapper::before{
            width:500px;height:500px;top:-150px;right:-100px;
            background:radial-gradient(circle,rgba(99,102,241,0.15),transparent 70%);
        }
        .login-wrapper::after{
            width:400px;height:400px;bottom:-100px;left:-80px;
            background:radial-gradient(circle,rgba(14,165,233,0.1),transparent 70%);
        }

        .login-card{
            background:rgba(30,41,59,0.8);
            backdrop-filter:blur(20px);
            -webkit-backdrop-filter:blur(20px);
            border:1px solid rgba(148,163,184,0.1);
            border-radius:20px;
            width:420px;
            max-width:100%;
            overflow:hidden;
            position:relative;
            z-index:1;
            box-shadow:0 25px 50px rgba(0,0,0,0.4);
        }

        .login-card-header{
            padding:36px 32px 28px;
            text-align:center;
            border-bottom:1px solid rgba(148,163,184,0.08);
        }
        .login-card-header img{
            max-width:180px;height:auto;margin-bottom:8px;
            filter:brightness(0) invert(1);opacity:0.92;
        }
        .login-card-header p{
            font-size:0.8rem;color:#94a3b8;font-weight:500;letter-spacing:1.5px;text-transform:uppercase;margin:0;
        }

        .login-card-body{padding:32px}

        .form-group{margin-bottom:18px}
        .form-label{
            display:block;font-size:0.8rem;font-weight:600;color:#94a3b8;margin-bottom:6px;
            letter-spacing:0.5px;text-transform:uppercase;
        }

        .input-wrap{position:relative}
        .input-wrap i{
            position:absolute;left:14px;top:50%;transform:translateY(-50%);
            color:#64748b;font-size:0.85rem;transition:color .2s;
        }
        .input-wrap input{
            width:100%;padding:13px 14px 13px 42px;
            background:rgba(15,23,42,0.6);
            border:1px solid rgba(148,163,184,0.15);
            border-radius:12px;font-size:0.95rem;color:#f1f5f9;
            transition:all .2s;
        }
        .input-wrap input::placeholder{color:#475569}
        .input-wrap input:focus{
            border-color:#6366f1;
            box-shadow:0 0 0 3px rgba(99,102,241,0.15);
            outline:none;background:rgba(15,23,42,0.8);
        }
        .input-wrap:focus-within i{color:#818cf8}

        .btn-login{
            width:100%;padding:13px;margin-top:8px;
            background:linear-gradient(135deg,#6366f1,#4f46e5);
            border:none;border-radius:12px;color:#fff;
            font-weight:600;font-size:0.95rem;cursor:pointer;
            transition:all .2s;letter-spacing:0.3px;
        }
        .btn-login:hover{
            background:linear-gradient(135deg,#818cf8,#6366f1);
            transform:translateY(-1px);
            box-shadow:0 8px 24px rgba(99,102,241,0.3);
        }
        .btn-login:active{transform:translateY(0)}
        .btn-login:disabled{opacity:0.6;transform:none;cursor:not-allowed}

        .alert-box{
            padding:12px 14px;border-radius:10px;font-size:0.85rem;
            margin-bottom:16px;display:flex;align-items:center;gap:10px;
        }
        .alert-box.error{background:rgba(239,68,68,0.1);color:#fca5a5;border:1px solid rgba(239,68,68,0.2)}
        .alert-box.error i{color:#ef4444}

        /* Etapa 2 */
        .otp-header{text-align:center;margin-bottom:24px}
        .otp-header i{font-size:2.5rem;margin-bottom:12px;display:block}
        .otp-header .fa-shield-alt,.otp-header .fa-mobile-alt{color:#818cf8}
        .otp-header .fa-envelope-open-text{color:#38bdf8}
        .otp-header p{color:#94a3b8;font-size:0.9rem;line-height:1.5}
        .otp-header strong{color:#f1f5f9}

        .otp-input{
            width:100%;padding:16px;
            background:rgba(15,23,42,0.6);
            border:1px solid rgba(148,163,184,0.15);
            border-radius:14px;font-size:1.8rem;font-weight:700;
            text-align:center;letter-spacing:10px;color:#f1f5f9;
            transition:all .2s;
        }
        .otp-input:focus{
            border-color:#6366f1;
            box-shadow:0 0 0 3px rgba(99,102,241,0.15);
            outline:none;
        }
        .otp-input::placeholder{letter-spacing:4px;font-size:1.3rem;color:#475569;font-weight:400}

        .remember-device{
            display:flex;align-items:center;gap:10px;
            margin:16px 0 4px;padding:12px 14px;
            background:rgba(99,102,241,0.06);
            border-radius:10px;border:1px solid rgba(99,102,241,0.12);
            cursor:pointer;transition:background .2s;
        }
        .remember-device:hover{background:rgba(99,102,241,0.1)}
        .remember-device input[type="checkbox"]{
            width:18px;height:18px;accent-color:#6366f1;cursor:pointer;flex-shrink:0;
        }
        .remember-device span{font-size:0.85rem;color:#cbd5e1;line-height:1.3}
        .remember-device small{display:block;color:#64748b;font-size:0.75rem;margin-top:2px}

        .btn-link-back{
            background:none;border:none;color:#64748b;font-size:0.85rem;
            cursor:pointer;margin-top:12px;transition:color .2s;
        }
        .btn-link-back:hover{color:#818cf8}

        .divider{display:flex;align-items:center;gap:12px;margin:20px 0}
        .divider hr{flex:1;border:none;border-top:1px solid rgba(148,163,184,0.1)}
        .divider span{font-size:0.72rem;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:1px}

        .btn-alt{
            width:100%;padding:11px;
            border:1px solid rgba(148,163,184,0.15);
            border-radius:10px;background:transparent;
            color:#94a3b8;font-size:0.88rem;font-weight:500;
            cursor:pointer;transition:all .2s;
            display:flex;align-items:center;justify-content:center;gap:8px;
        }
        .btn-alt:hover{border-color:#6366f1;color:#818cf8;background:rgba(99,102,241,0.05)}

        /* Footer */
        .login-footer{
            background:#0a1a2e;padding:32px 24px 20px;text-align:center;
            border-top:1px solid rgba(148,163,184,0.06);
        }
        .login-footer img{max-width:150px;height:auto;margin-bottom:16px;opacity:0.85}
        .footer-contacts{
            display:flex;flex-wrap:wrap;justify-content:center;gap:20px;margin-bottom:20px;
        }
        .footer-contacts a{
            display:inline-flex;align-items:center;gap:8px;
            color:rgba(255,255,255,0.5);text-decoration:none;font-size:0.82rem;transition:color .2s;
        }
        .footer-contacts a:hover{color:rgba(255,255,255,0.8)}
        .footer-contacts .ic{
            width:28px;height:28px;border-radius:50%;
            display:flex;align-items:center;justify-content:center;flex-shrink:0;
        }
        .login-footer .copy{
            color:rgba(255,255,255,0.3);font-size:0.75rem;margin:0;
            padding-top:14px;border-top:1px solid rgba(255,255,255,0.05);
        }

        .d-none{display:none!important}
        .spinner-border{display:inline-block;width:1rem;height:1rem;border:.2em solid currentColor;border-right-color:transparent;border-radius:50%;animation:spin .6s linear infinite}
        @keyframes spin{to{transform:rotate(360deg)}}

        @media(max-width:480px){
            .login-card-body{padding:24px 20px}
            .login-card-header{padding:28px 20px 20px}
        }
    </style>
</head>
<body>
    <?php if (defined('MODO_HOMOLOG') && MODO_HOMOLOG): ?>
    <div style="background:repeating-linear-gradient(45deg,#ff9800,#ff9800 10px,#f57c00 10px,#f57c00 20px);color:#fff;text-align:center;padding:8px;font-weight:700;font-size:1rem;text-transform:uppercase;letter-spacing:2px;position:fixed;top:0;left:0;width:100%;z-index:9999;opacity:0.9;pointer-events:none;">
        Ambiente de Homologacao / Testes
    </div>
    <div style="height:40px;"></div>
    <?php endif; ?>

    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-card-header">
                <img src="../assets/SEMA/PNG/Branca/Logo SEMA Horizontal 3.png" alt="SEMA">
                <p>Painel Administrativo</p>
            </div>

            <div class="login-card-body">
                <?php if ($erro): ?>
                    <div class="alert-box error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?= htmlspecialchars($erro) ?></span>
                    </div>
                <?php endif; ?>

                <!-- Etapa 1: Credenciais -->
                <div id="etapa-1">
                    <form id="loginForm">
                        <div class="alert-box error d-none" id="erro-1">
                            <i class="fas fa-exclamation-circle"></i>
                            <span></span>
                        </div>
                        <input type="hidden" name="recaptcha_token" id="recaptchaToken">
                        <input type="hidden" name="action" value="validar_credenciais">

                        <div class="form-group">
                            <label class="form-label" for="usuario">Usuario</label>
                            <div class="input-wrap">
                                <i class="fas fa-user"></i>
                                <input type="text" id="usuario" name="usuario" required placeholder="Digite seu usuario" autocomplete="username">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="senha">Senha</label>
                            <div class="input-wrap">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="senha" name="senha" required placeholder="Digite sua senha" autocomplete="current-password">
                            </div>
                        </div>

                        <button type="submit" class="btn-login" id="btn-1">
                            Entrar <i class="fas fa-arrow-right" style="margin-left:8px;font-size:0.85rem;"></i>
                        </button>
                    </form>
                </div>

                <!-- Etapa 2: 2FA -->
                <div id="etapa-2" class="d-none">
                    <div class="otp-header">
                        <i class="fas fa-shield-alt" id="otp-icon"></i>
                        <p id="otp-text">Verificacao em duas etapas</p>
                    </div>

                    <form id="otpForm">
                        <div class="alert-box error d-none" id="erro-2">
                            <i class="fas fa-exclamation-circle"></i>
                            <span></span>
                        </div>
                        <input type="hidden" name="action" value="validar_otp">
                        <input type="hidden" name="lembrar_dispositivo" id="lembrar_dispositivo_input" value="0">

                        <div class="form-group">
                            <input type="text" class="otp-input" id="codigo" name="codigo" placeholder="000000" maxlength="6" required autocomplete="one-time-code" inputmode="numeric">
                        </div>

                        <label class="remember-device" for="lembrar_check">
                            <input type="checkbox" id="lembrar_check">
                            <span>
                                Lembrar este dispositivo
                                <small>Pular verificacao por 30 dias neste navegador</small>
                            </span>
                        </label>

                        <button type="submit" class="btn-login" id="btn-2" style="margin-top:16px;">
                            <i class="fas fa-check-circle" style="margin-right:8px;"></i>Verificar e Entrar
                        </button>

                        <div style="text-align:center;">
                            <button type="button" class="btn-link-back" onclick="voltarLogin()">
                                <i class="fas fa-arrow-left" style="margin-right:4px;"></i> Voltar
                            </button>
                        </div>
                    </form>

                    <div id="totp-switch" class="d-none">
                        <div class="divider"><hr><span>ou</span><hr></div>
                        <button type="button" class="btn-alt" onclick="toggleMetodo()" id="btn-metodo">
                            <i class="fas fa-mobile-alt"></i> Usar App Autenticador
                        </button>
                    </div>

                    <div id="totp-promo" class="d-none">
                        <div class="divider"><hr><span>mais seguranca</span><hr></div>
                        <button type="button" class="btn-alt" onclick="alert('Apos entrar, acesse Meu Perfil para configurar o App Autenticador!')">
                            <i class="fas fa-qrcode" style="color:#818cf8;"></i> Configurar App Autenticador
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="login-footer">
        <img src="../assets/SEMA/PNG/Branca/Logo SEMA Horizontal 3.png" alt="SEMA">
        <div class="footer-contacts">
            <a href="https://wa.me/5584996686413" target="_blank" rel="noopener">
                <span class="ic" style="background:rgba(74,222,128,0.1);"><i class="fab fa-whatsapp" style="color:#4ade80;font-size:0.85rem;"></i></span>
                (84) 99668-6413
            </a>
            <a href="mailto:fiscalizacaosemapdf@gmail.com">
                <span class="ic" style="background:rgba(56,189,248,0.1);"><i class="fas fa-envelope" style="color:#38bdf8;font-size:0.8rem;"></i></span>
                fiscalizacaosemapdf@gmail.com
            </a>
        </div>
        <p class="copy">&copy; <?= date('Y') ?> Prefeitura Municipal de Pau dos Ferros</p>
    </footer>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const loginForm = document.getElementById('loginForm');
        const otpForm = document.getElementById('otpForm');
        const lembrarCheck = document.getElementById('lembrar_check');
        const lembrarInput = document.getElementById('lembrar_dispositivo_input');

        lembrarCheck.addEventListener('change', () => {
            lembrarInput.value = lembrarCheck.checked ? '1' : '0';
        });

        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-1');
            const orig = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border" style="margin-right:8px;"></span>Verificando...';

            grecaptcha.ready(function() {
                grecaptcha.execute('<?= RECAPTCHA_SITE_KEY ?>', {action: 'login'}).then(function(token) {
                    document.getElementById('recaptchaToken').value = token;
                    fetch('login.php', { method: 'POST', body: new FormData(loginForm) })
                    .then(r => r.json())
                    .then(data => {
                        btn.disabled = false;
                        btn.innerHTML = orig;
                        if (data.success) {
                            if (data.skip_2fa) {
                                window.location.href = data.redirect || 'index.php';
                                return;
                            }
                            document.getElementById('erro-1').classList.add('d-none');
                            window.maskedEmail = data.email_mascarado || '';
                            window.authMethod = data.has_totp ? 'totp' : 'email';

                            if (window.authMethod === 'totp') {
                                renderTotp();
                                document.getElementById('totp-switch').classList.remove('d-none');
                                document.getElementById('totp-promo').classList.add('d-none');
                            } else {
                                renderEmail();
                                document.getElementById('totp-switch').classList.add('d-none');
                                document.getElementById('totp-promo').classList.remove('d-none');
                            }

                            document.getElementById('etapa-1').classList.add('d-none');
                            document.getElementById('etapa-2').classList.remove('d-none');
                            setTimeout(() => document.getElementById('codigo').focus(), 200);
                        } else {
                            showError('erro-1', data.error);
                        }
                    })
                    .catch(() => { btn.disabled = false; btn.innerHTML = orig; showError('erro-1', 'Erro de rede.'); });
                });
            });
        });

        otpForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-2');
            const orig = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border" style="margin-right:8px;"></span>Validando...';

            fetch('login.php', { method: 'POST', body: new FormData(otpForm) })
            .then(r => r.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = orig;
                if (data.success) {
                    window.location.href = data.redirect || 'index.php';
                } else {
                    showError('erro-2', data.error);
                }
            })
            .catch(() => { btn.disabled = false; btn.innerHTML = orig; showError('erro-2', 'Erro de rede.'); });
        });
    });

    function showError(id, msg) {
        const box = document.getElementById(id);
        box.querySelector('span').textContent = msg;
        box.classList.remove('d-none');
    }

    function voltarLogin() {
        document.getElementById('etapa-2').classList.add('d-none');
        document.getElementById('etapa-1').classList.remove('d-none');
        document.getElementById('senha').value = '';
        document.getElementById('codigo').value = '';
        document.getElementById('erro-1').classList.add('d-none');
        document.getElementById('erro-2').classList.add('d-none');
    }

    function toggleMetodo() {
        window.authMethod = window.authMethod === 'totp' ? 'email' : 'totp';
        window.authMethod === 'totp' ? renderTotp() : renderEmail();
    }

    function renderTotp() {
        document.getElementById('otp-icon').className = 'fas fa-mobile-alt';
        document.getElementById('otp-text').innerHTML = 'Abra seu <strong>App Autenticador</strong> e informe o codigo de 6 digitos.';
        const btn = document.getElementById('btn-metodo');
        if (btn) btn.innerHTML = '<i class="fas fa-envelope"></i> Usar codigo por E-mail';
    }

    function renderEmail() {
        document.getElementById('otp-icon').className = 'fas fa-envelope-open-text';
        document.getElementById('otp-text').innerHTML = 'Informe o codigo enviado para <strong>' + window.maskedEmail + '</strong>';
        const btn = document.getElementById('btn-metodo');
        if (btn) btn.innerHTML = '<i class="fas fa-mobile-alt"></i> Usar App Autenticador';
    }
    </script>
</body>
</html>
