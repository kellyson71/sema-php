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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://www.google.com/recaptcha/api.js?render=<?php echo RECAPTCHA_SITE_KEY; ?>"></script>
    <style>
        :root{
            --brand-700:#0369a1;
            --brand-600:#0284c7;
            --brand-500:#0ea5e9;
            --brand-100:#e0f2fe;
            --surface:#ffffff;
            --surface-alt:#f8fafc;
            --line:#e2e8f0;
            --text:#0f172a;
            --muted:#64748b;
            --shadow:0 24px 60px rgba(15,23,42,.12);
        }
        *{margin:0;padding:0;box-sizing:border-box}
        body{min-height:100vh;font-family:'Poppins',system-ui,sans-serif;background:linear-gradient(180deg,#f8fbff 0%,#f1f5f9 100%);color:var(--text);display:flex;flex-direction:column}

        /* ── Layout split ── */
        .split{flex:1;display:flex;min-height:0}

        /* Painel esquerdo — branding */
        .brand-panel{
            flex:0 0 45%;display:flex;flex-direction:column;align-items:center;justify-content:center;
            background:
                linear-gradient(160deg,rgba(3,105,161,.96) 0%,rgba(2,132,199,.94) 52%,rgba(14,165,233,.9) 100%),
                url('../assets/SEMA/PNG/Azul/fundo.png') center/cover no-repeat;
            color:#fff;padding:3rem 2.5rem;position:relative;overflow:hidden;
        }
        .brand-panel::before{
            content:'';position:absolute;width:600px;height:600px;border-radius:50%;
            background:rgba(255,255,255,0.04);top:-200px;right:-200px;
        }
        .brand-panel::after{
            content:'';position:absolute;width:400px;height:400px;border-radius:50%;
            background:rgba(255,255,255,0.03);bottom:-150px;left:-100px;
        }
        .brand-badge{
            position:relative;z-index:1;display:inline-flex;align-items:center;gap:8px;
            padding:8px 14px;border-radius:999px;background:rgba(255,255,255,.12);
            border:1px solid rgba(255,255,255,.18);font-size:.76rem;font-weight:600;letter-spacing:.08em;
            text-transform:uppercase;margin-bottom:1.25rem;
        }
        .brand-panel img{max-width:220px;height:auto;margin-bottom:1.5rem;position:relative;z-index:1;filter:brightness(0) invert(1)}
        .brand-panel h2{font-size:2rem;font-weight:700;text-align:center;line-height:1.2;margin-bottom:1rem;position:relative;z-index:1;max-width:420px}
        .brand-panel p.sub{font-size:0.96rem;text-align:center;opacity:0.88;line-height:1.7;max-width:390px;position:relative;z-index:1}

        .brand-features{
            margin-top:2.25rem;display:flex;flex-direction:column;gap:16px;
            position:relative;z-index:1;width:100%;max-width:360px;
        }
        .brand-feat{
            display:flex;align-items:flex-start;gap:14px;padding:14px 16px;background:rgba(255,255,255,0.1);
            border:1px solid rgba(255,255,255,0.12);border-radius:16px;backdrop-filter:blur(10px);
            box-shadow:0 10px 30px rgba(0,0,0,.08);
        }
        .brand-feat i{font-size:1.1rem;width:20px;text-align:center;flex-shrink:0;opacity:0.9}
        .brand-feat span{font-size:0.85rem;opacity:0.9;line-height:1.4}

        /* Painel direito — formulario */
        .form-panel{
            flex:1;display:flex;align-items:center;justify-content:center;padding:2rem;
            background:
                radial-gradient(circle at top left,rgba(14,165,233,.08),transparent 28%),
                radial-gradient(circle at bottom right,rgba(2,132,199,.08),transparent 22%),
                var(--surface-alt);
        }
        .form-inner{
            width:100%;max-width:430px;background:rgba(255,255,255,.88);backdrop-filter:blur(10px);
            border:1px solid rgba(226,232,240,.9);border-radius:28px;padding:2rem;box-shadow:var(--shadow);
        }
        .form-kicker{
            display:inline-flex;align-items:center;gap:8px;margin-bottom:.9rem;padding:8px 12px;border-radius:999px;
            background:var(--brand-100);color:var(--brand-700);font-size:.78rem;font-weight:600;
        }
        .form-inner h1{font-size:1.7rem;font-weight:700;color:var(--text);margin-bottom:6px}
        .form-inner .welcome{font-size:0.92rem;color:var(--muted);margin-bottom:1.75rem;line-height:1.6}
        .quick-note{
            display:flex;align-items:flex-start;gap:12px;margin-bottom:1.5rem;padding:14px 16px;border-radius:16px;
            background:#f8fbff;border:1px solid #dbeafe;color:#334155;
        }
        .quick-note i{color:var(--brand-600);margin-top:3px}
        .quick-note strong{display:block;font-size:.86rem;margin-bottom:2px}
        .quick-note span{font-size:.8rem;line-height:1.5}

        .form-group{margin-bottom:18px}
        .form-label{display:block;font-size:0.78rem;font-weight:600;color:#475569;margin-bottom:8px;text-transform:uppercase;letter-spacing:0.4px}

        .input-wrap{position:relative}
        .input-wrap i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:0.85rem;transition:color .2s}
        .input-wrap input{
            width:100%;padding:12px 14px 12px 42px;
            background:#f8fafc;border:1.5px solid var(--line);border-radius:14px;
            font-size:0.95rem;color:var(--text);transition:all .2s;
        }
        .input-wrap input::placeholder{color:#94a3b8}
        .input-wrap input:focus{border-color:var(--brand-600);box-shadow:0 0 0 4px rgba(2,132,199,0.1);outline:none;background:#fff}
        .input-wrap:focus-within i{color:var(--brand-600)}

        .btn-login{
            width:100%;padding:13px;margin-top:8px;
            background:linear-gradient(135deg,var(--brand-700),var(--brand-600));border:none;border-radius:14px;color:#fff;
            font-weight:600;font-size:0.95rem;cursor:pointer;transition:all .2s;
        }
        .btn-login:hover{transform:translateY(-1px);box-shadow:0 12px 28px rgba(2,132,199,0.28)}
        .btn-login:active{transform:translateY(0)}
        .btn-login:disabled{opacity:0.6;transform:none;cursor:not-allowed}

        .alert-box{padding:12px 14px;border-radius:10px;font-size:0.85rem;margin-bottom:16px;display:flex;align-items:center;gap:10px}
        .alert-box.error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
        .alert-box.error i{color:#ef4444}

        /* Etapa 2 */
        .otp-header{text-align:center;margin-bottom:24px}
        .otp-header i{font-size:2.4rem;margin-bottom:12px;display:block;color:var(--brand-600)}
        .otp-header p{color:var(--muted);font-size:0.9rem;line-height:1.5}
        .otp-header strong{color:var(--text)}

        .otp-input{
            width:100%;padding:14px;background:#f8fafc;
            border:1.5px solid var(--line);border-radius:14px;
            font-size:1.8rem;font-weight:700;text-align:center;letter-spacing:10px;color:var(--text);transition:all .2s;
        }
        .otp-input:focus{border-color:var(--brand-600);box-shadow:0 0 0 4px rgba(2,132,199,0.1);outline:none;background:#fff}
        .otp-input::placeholder{letter-spacing:4px;font-size:1.3rem;color:#cbd5e1;font-weight:400}

        .remember-device{
            display:flex;align-items:center;gap:10px;margin:16px 0 4px;padding:12px 14px;
            background:#eff6ff;border-radius:10px;border:1px solid #dbeafe;cursor:pointer;transition:background .2s;
        }
        .remember-device:hover{background:#dbeafe}
        .remember-device input[type="checkbox"]{width:18px;height:18px;accent-color:var(--brand-600);cursor:pointer;flex-shrink:0}
        .remember-device span{font-size:0.85rem;color:#334155;line-height:1.3}
        .remember-device small{display:block;color:#64748b;font-size:0.75rem;margin-top:2px}

        .btn-link-back{background:none;border:none;color:#64748b;font-size:0.85rem;cursor:pointer;margin-top:12px;transition:color .2s}
        .btn-link-back:hover{color:var(--brand-600)}

        .divider{display:flex;align-items:center;gap:12px;margin:20px 0}
        .divider hr{flex:1;border:none;border-top:1px solid #e2e8f0}
        .divider span{font-size:0.72rem;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:1px}

        .btn-alt{
            width:100%;padding:11px;border:1.5px solid #e2e8f0;border-radius:10px;background:#fff;
            color:#475569;font-size:0.88rem;font-weight:500;cursor:pointer;transition:all .2s;
            display:flex;align-items:center;justify-content:center;gap:8px;
        }
        .btn-alt:hover{border-color:var(--brand-600);color:var(--brand-600);background:#eff6ff}

        /* Footer */
        .login-footer{background:#0f172a;padding:18px 24px;text-align:center}
        .login-footer p{color:rgba(255,255,255,0.35);font-size:0.75rem;margin:0}

        .d-none{display:none!important}
        .spinner-border{display:inline-block;width:1rem;height:1rem;border:.2em solid currentColor;border-right-color:transparent;border-radius:50%;animation:spin .6s linear infinite}
        @keyframes spin{to{transform:rotate(360deg)}}

        /* Responsivo: mobile empilha */
        @media(max-width:860px){
            .split{flex-direction:column}
            .brand-panel{flex:0 0 auto;padding:2rem 1.5rem}
            .brand-panel h2{font-size:1.45rem}
            .brand-features{display:none}
            .form-panel{padding:1.5rem}
            .form-inner{padding:1.5rem;border-radius:22px}
        }
        @media(max-width:480px){
            .brand-badge{font-size:.7rem}
            .brand-panel img{max-width:180px}
            .form-panel{padding:1rem}
            .form-inner h1{font-size:1.45rem}
        }

        /* Homologacao banner */
        .homolog-banner{
            background:repeating-linear-gradient(45deg,#ff9800,#ff9800 10px,#f57c00 10px,#f57c00 20px);
            color:#fff;text-align:center;padding:8px;font-weight:700;font-size:0.9rem;
            text-transform:uppercase;letter-spacing:2px;
        }
    </style>
</head>
<body>
    <?php if (defined('MODO_HOMOLOG') && MODO_HOMOLOG): ?>
    <div class="homolog-banner">Ambiente de Homologacao / Testes</div>
    <?php endif; ?>

    <div class="split">
        <!-- Painel esquerdo: branding -->
        <div class="brand-panel">
            <div class="brand-badge"><i class="fas fa-shield-halved"></i> Acesso Administrativo</div>
            <img src="../assets/SEMA/PNG/Branca/Logo SEMA Vertical 3.png" alt="SEMA">
            <h2>Secretaria Municipal<br>de Meio Ambiente</h2>
            <p class="sub">Painel de gestao de alvaras e licenciamento ambiental do municipio de Pau dos Ferros/RN.</p>

            <div class="brand-features">
                <div class="brand-feat">
                    <i class="fas fa-file-signature"></i>
                    <span>Gestao de requerimentos e pareceres tecnicos</span>
                </div>
                <div class="brand-feat">
                    <i class="fas fa-signature"></i>
                    <span>Assinatura digital com verificacao por QR Code</span>
                </div>
                <div class="brand-feat">
                    <i class="fas fa-shield-alt"></i>
                    <span>Autenticacao em duas etapas para sua seguranca</span>
                </div>
            </div>
        </div>

        <!-- Painel direito: formulario -->
        <div class="form-panel">
            <div class="form-inner">
                <?php if ($erro): ?>
                    <div class="alert-box error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?= htmlspecialchars($erro) ?></span>
                    </div>
                <?php endif; ?>

                <!-- Etapa 1: Credenciais -->
                <div id="etapa-1">
                    <div class="form-kicker"><i class="fas fa-user-lock"></i> Painel Administrativo</div>
                    <h1>Bem-vindo</h1>
                    <p class="welcome">Acesse o painel administrativo da SEMA</p>
                    <div class="quick-note">
                        <i class="fas fa-circle-info"></i>
                        <div>
                            <strong>Aviso de seguranca</strong>
                            <span>Use seu acesso institucional. Em dispositivos compartilhados, nao habilite a opcao de lembrar este navegador.</span>
                        </div>
                    </div>

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
                            <i class="fas fa-qrcode" style="color:#2563eb;"></i> Configurar App Autenticador
                        </button>
                    </div>
                </div>

                <!-- Rodape do formulario -->
                <div style="text-align:center;margin-top:2rem;padding-top:1.5rem;border-top:1px solid #f1f5f9;">
                    <p style="font-size:0.78rem;color:#94a3b8;">Prefeitura Municipal de Pau dos Ferros/RN</p>
                </div>
            </div>
        </div>
    </div>

    <footer class="login-footer">
        <p>&copy; <?= date('Y') ?> SEMA - Secretaria Municipal de Meio Ambiente</p>
    </footer>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const loginForm = document.getElementById('loginForm');
        const otpForm = document.getElementById('otpForm');
        const lembrarCheck = document.getElementById('lembrar_check');
        const lembrarInput = document.getElementById('lembrar_dispositivo_input');

        lembrarCheck.addEventListener('change', () => { lembrarInput.value = lembrarCheck.checked ? '1' : '0'; });

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
                        btn.disabled = false; btn.innerHTML = orig;
                        if (data.success) {
                            if (data.skip_2fa) { window.location.href = data.redirect || 'index.php'; return; }
                            document.getElementById('erro-1').classList.add('d-none');
                            window.maskedEmail = data.email_mascarado || '';
                            window.authMethod = data.has_totp ? 'totp' : 'email';
                            if (window.authMethod === 'totp') {
                                renderTotp(); document.getElementById('totp-switch').classList.remove('d-none'); document.getElementById('totp-promo').classList.add('d-none');
                            } else {
                                renderEmail(); document.getElementById('totp-switch').classList.add('d-none'); document.getElementById('totp-promo').classList.remove('d-none');
                            }
                            document.getElementById('etapa-1').classList.add('d-none');
                            document.getElementById('etapa-2').classList.remove('d-none');
                            setTimeout(() => document.getElementById('codigo').focus(), 200);
                        } else { showError('erro-1', data.error); }
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
                btn.disabled = false; btn.innerHTML = orig;
                if (data.success) { window.location.href = data.redirect || 'index.php'; }
                else { showError('erro-2', data.error); }
            })
            .catch(() => { btn.disabled = false; btn.innerHTML = orig; showError('erro-2', 'Erro de rede.'); });
        });
    });

    function showError(id, msg) { const b = document.getElementById(id); b.querySelector('span').textContent = msg; b.classList.remove('d-none'); }

    function voltarLogin() {
        document.getElementById('etapa-2').classList.add('d-none');
        document.getElementById('etapa-1').classList.remove('d-none');
        document.getElementById('senha').value = '';
        document.getElementById('codigo').value = '';
        document.getElementById('erro-1').classList.add('d-none');
        document.getElementById('erro-2').classList.add('d-none');
    }

    function toggleMetodo() { window.authMethod = window.authMethod === 'totp' ? 'email' : 'totp'; window.authMethod === 'totp' ? renderTotp() : renderEmail(); }

    function renderTotp() {
        document.getElementById('otp-icon').className = 'fas fa-mobile-alt';
        document.getElementById('otp-text').innerHTML = 'Abra seu <strong>App Autenticador</strong> e informe o codigo de 6 digitos.';
        const b = document.getElementById('btn-metodo'); if (b) b.innerHTML = '<i class="fas fa-envelope"></i> Usar codigo por E-mail';
    }
    function renderEmail() {
        document.getElementById('otp-icon').className = 'fas fa-envelope-open-text';
        document.getElementById('otp-text').innerHTML = 'Informe o codigo enviado para <strong>' + window.maskedEmail + '</strong>';
        const b = document.getElementById('btn-metodo'); if (b) b.innerHTML = '<i class="fas fa-mobile-alt"></i> Usar App Autenticador';
    }
    </script>
</body>
</html>
