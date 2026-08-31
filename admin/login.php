<?php
        require_once 'conexao.php';
        require_once '../includes/email_service.php';

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

/**
 * A homologação atual usa subdomínio, enquanto instalações antigas usam
 * /homologacao/ no caminho (coberto por MODO_HOMOLOG).
 */
function loginEmHomologacao(): bool {
    if (defined('MODO_HOMOLOG') && MODO_HOMOLOG) {
        return true;
    }

    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\\d+$/', '', $host);

    return $host === 'semaholog.protocolosead.com';
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

// No ambiente local, uma sessão presa pelo teste de força bruta não deve
// impedir o desenvolvimento. Em produção, o bloqueio continua respeitando
// a janela de 15 minutos normalmente.
if (DOCKER_ENV && $_SERVER['REQUEST_METHOD'] === 'GET' && $_SESSION['login_attempts'] >= 8) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt_time'] = time();
}

if ($_SESSION['login_attempts'] >= 8) {
    $tempo_restante = 900 - (time() - $_SESSION['last_attempt_time']);
    if ($tempo_restante > 0) {
        $erro = "Muitas tentativas de login. Tente novamente em " . ceil($tempo_restante / 60) . " minutos.";
        $_SESSION['login_attempts'] = 8;
    } else {
        $_SESSION['login_attempts'] = 0;
    }
}

// Funções de dispositivo confiado delegam para conexao.php (sistema correto):
// - dispositivoConfiado($pdo)       → verifica cookie sema_device_token + admin_sessoes_confiadas
// - registrarSessaoConfiada($pdo, $adminId) → salva token + seta cookie sema_device_token

/**
 * Cria a sessão de admin.
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
    $_SESSION['admin_primeiro_acesso']    = !empty($admin['primeiro_acesso']);
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

    registrarLoginPostHog($admin);
}

/**
 * Trilha de acesso da equipe no PostHog: quem entrou, de onde e com qual dispositivo.
 *
 * Vai pelo servidor de propósito — evento de segurança não pode depender de JS, que
 * bloqueador de anúncio derruba. O PostHog deriva navegador, SO e tipo de dispositivo
 * a partir do $raw_user_agent, e cidade/país a partir do $ip.
 *
 * Nunca derruba o login: sem SDK ou sem chave, é no-op; qualquer falha é engolida.
 */
function registrarLoginPostHog(array $admin): void
{
    if (!class_exists('\PostHog\PostHog')) {
        return; // sem SDK/chave: error_tracking.php não inicializou
    }

    try {
        \PostHog\PostHog::capture([
            'distinctId' => 'admin_' . $admin['id'],
            'event'      => 'admin_login',
            'properties' => [
                '$ip'              => $_SERVER['REMOTE_ADDR'] ?? null,
                '$raw_user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? null,
                // O painel já tem controle de dispositivo confiável (sema_device_token).
                // Um login de dispositivo NÃO confiável é o sinal que interessa vigiar.
                'dispositivo_confiavel' => isset($_COOKIE['sema_device_token']),
                'nivel'            => $admin['nivel'] ?? null,
                // Propriedades da pessoa. Sem CPF: ele está na sessão, mas não vai para cá.
                // 'name' é a chave que o PostHog usa para exibir a pessoa (em vez do admin_<id>).
                '$set'             => [
                    'name'  => $admin['nome'] ?? null,
                    'nome'  => $admin['nome'] ?? null,
                    'nivel' => $admin['nivel'] ?? null,
                    'cargo' => $admin['cargo'] ?? 'Administrador',
                ],
            ],
        ]);
    } catch (\Throwable $e) {
        // Silêncio proposital: telemetria não pode impedir alguém de logar.
    }
}

function mascararEmail($email) {
    return preg_replace('/(?<=.).(?=.*@)/', '*', $email ?? '');
}

function enviarCodigoLoginPorEmail($admin, $codigo) {
    if (!emailDestinoValido((string) ($admin['email'] ?? ''))) {
        return false;
    }

    $emailService = new EmailService();
    return $emailService->enviarEmailCodigoVerificacao($admin['email'], $admin['nome'], $codigo);
}

// Processar formulário de login via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'validar_credenciais') {
    // Responde JSON mesmo quando bloqueado — evita que o HTML da página seja retornado ao fetch
    if ($_SESSION['login_attempts'] >= 8) {
        $tempo_restante = 900 - (time() - ($_SESSION['last_attempt_time'] ?? time()));
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Acesso bloqueado. Muitas tentativas incorretas. Tente novamente em ' . ceil(max($tempo_restante, 60) / 60) . ' minutos.']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SESSION['login_attempts'] < 8) {
    if (isset($_POST['action']) && $_POST['action'] === 'validar_credenciais') {
        header('Content-Type: application/json');

        $usuario = trim($_POST['usuario'] ?? '');
        $senha = trim($_POST['senha'] ?? '');
        $recaptcha_token = $_POST['recaptcha_token'] ?? '';

        $recaptcha_data = (object) ['success' => true, 'score' => 1.0];
        if (!MODO_HOMOLOG && !DOCKER_ENV) {
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

        // Aceita tanto o nome institucional quanto o e-mail cadastrado.
        $stmt = $pdo->prepare("SELECT * FROM administradores WHERE (nome = ? OR email = ?) AND ativo = 1 LIMIT 1");
        $stmt->execute([$usuario, $usuario]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($senha, $admin['senha'])) {
            // Em homologação, credenciais válidas já concedem acesso para facilitar testes.
            // Produção continua exigindo 2FA, salvo bypass individual ou dispositivo confiado.
            if (loginEmHomologacao() || !empty($admin['bypass_2fa'] ?? null) || dispositivoConfiado($pdo)) {
                criarSessaoAdmin($pdo, $admin);
                $redirectUrl = $_SESSION['login_redirect_url'] ?? 'index.php';
                unset($_SESSION['login_redirect_url']);
                if (ob_get_length()) ob_clean();
                echo json_encode(['success' => true, 'skip_2fa' => true, 'redirect' => $redirectUrl]);
                exit;
            }

            $hasTotp = !empty($admin['totp_secret']);

            if (!$hasTotp && !emailDestinoValido((string) ($admin['email'] ?? ''))) {
                if (ob_get_length()) ob_clean();
                echo json_encode(['success' => false, 'error' => "Usuário não possui e-mail ou App Autenticador cadastrado para verificação em duas etapas."]);
                exit;
            }

            $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $_SESSION['2fa_admin_data'] = $admin;
            $_SESSION['2fa_otp_code'] = $codigo;
            $_SESSION['2fa_otp_expires'] = time() + (15 * 60);

            $emailMascarado = '';
            if (emailDestinoValido((string) ($admin['email'] ?? ''))) {
                $enviado = enviarCodigoLoginPorEmail($admin, $codigo);
                if ($enviado) {
                    $emailMascarado = mascararEmail($admin['email']);
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
            $restantes = max(0, 8 - (int) $_SESSION['login_attempts']);
            if (ob_get_length()) ob_clean();
            echo json_encode([
                'success' => false,
                'error'   => 'Usuário ou senha incorretos.',
                'tentativas_restantes' => $restantes <= 3 ? $restantes : null,
            ]);
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

        if (!empty($admin['totp_secret'])) {
            require_once 'MultiFactorService.php';
            $twoFactorService = new \Admin\Services\TwoFactorService();
            if ($twoFactorService->verify($admin['totp_secret'], $codigoRecebido)) {
                $codigoValido = true;
            }
        }

        if (!$codigoValido && isset($_SESSION['2fa_otp_code'])) {
            if (time() > $_SESSION['2fa_otp_expires']) {
                $erroMsg = 'Código expirado. Volte e faça login novamente.';
            } else if ($codigoRecebido === $_SESSION['2fa_otp_code']) {
                $codigoValido = true;
            }
        }

        if ($codigoValido) {
            criarSessaoAdmin($pdo, $admin);

            if ($lembrarDispositivo) {
                registrarSessaoConfiada($pdo, $admin['id']);
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

    if (isset($_POST['action']) && $_POST['action'] === 'reenviar_otp_email') {
        header('Content-Type: application/json');

        if (!isset($_SESSION['2fa_admin_data'])) {
            if (ob_get_length()) ob_clean();
            echo json_encode(['success' => false, 'error' => 'Sessão de verificação expirada. Faça login novamente.']);
            exit;
        }

        $admin = $_SESSION['2fa_admin_data'];
        if (!emailDestinoValido((string) ($admin['email'] ?? ''))) {
            if (ob_get_length()) ob_clean();
            echo json_encode(['success' => false, 'error' => 'Este usuário não possui e-mail cadastrado para reenvio do código.']);
            exit;
        }

        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $_SESSION['2fa_otp_code'] = $codigo;
        $_SESSION['2fa_otp_expires'] = time() + (15 * 60);

        if (!enviarCodigoLoginPorEmail($admin, $codigo)) {
            if (ob_get_length()) ob_clean();
            echo json_encode(['success' => false, 'error' => 'Não foi possível reenviar o código agora. Tente novamente em instantes.']);
            exit;
        }

        if (ob_get_length()) ob_clean();
        echo json_encode([
            'success' => true,
            'email_mascarado' => mascararEmail($admin['email']),
            'message' => 'Enviamos um novo código para o seu e-mail institucional.'
        ]);
        exit;
    }
}

// Recuperação de senha (placeholder institucional)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'recuperar_senha') {
    header('Content-Type: application/json');
    $email = trim($_POST['email'] ?? '');
    $emailMascarado = '';

    if ($email !== '' && strpos($email, '@') !== false) {
        $emailMascarado = mascararEmail($email);
    }

    echo json_encode([
        'success' => true,
        'email_mascarado' => $emailMascarado,
        'message' => 'A redefinição online ainda não está disponível. Entre em contato com o desenvolvedor para recuperar seu acesso.'
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — SEMA Painel Administrativo</title>
    <link rel="icon" href="../assets/img/favicon.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <!-- Mesma tipografia usada no protocolo eletrônico público. -->
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php if (!MODO_HOMOLOG && !DOCKER_ENV): ?>
    <script src="https://www.google.com/recaptcha/api.js?render=<?php echo RECAPTCHA_SITE_KEY; ?>"></script>
    <?php endif; ?>
    <style>
        :root {
            --primary: #009640;
            --primary-600: #007a33;
            --primary-700: #00652a;
            --primary-50: #f0fdf4;
            --primary-100: #dcfce7;
            --ink: #333333;
            --ink-2: #59636f;
            --ink-3: #8a949f;
            --line: #e3e7e4;
            --line-2: #d9dedb;
            --bg: #f8f8f8;
            --card:  #ffffff;
            --navy: #0a1a2e;
            --danger: #a72c2c;
            --danger-bg: #fff5f5;
            --success: #007a33;
            --success-bg: #f0fdf4;
            --radius:    9px;
            --radius-lg: 14px;
            --shadow-card: 0 12px 34px rgba(10, 26, 46, .08);
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; }
        body {
            font-family: Raleway, "Segoe UI", sans-serif;
            color: var(--ink);
            background: var(--bg);
            -webkit-font-smoothing: antialiased;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .display { font-family: Raleway, "Segoe UI", sans-serif; letter-spacing: -0.015em; }
        .mono    { font-family: Raleway, "Segoe UI", sans-serif; }
        .step-meta {
            font-family: Raleway, "Segoe UI", sans-serif;
            font-weight: 700;
            letter-spacing: .08em;
        }
        .login-heading {
            display: grid;
            grid-template-columns: 44px 1fr;
            gap: 14px;
            align-items: center;
            margin-bottom: 26px;
        }
        .profile-icon {
            width: 44px;
            height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #bde7cd;
            border-radius: 10px;
            background: var(--primary-50);
            color: var(--primary);
        }
        .login-heading h2 { font-size: 1.55rem; line-height: 1.15; font-weight: 800; }
        .login-heading p {
            margin-top: 6px;
            max-width: 39ch;
            font-size: .88rem;
            color: var(--ink-2);
            line-height: 1.5;
        }
        button, input { font-family: inherit; }
        ::selection { background: var(--primary-100); color: var(--ink); }

        /* ── Layout ── */
        .layout {
            flex: 1;
            display: grid;
            grid-template-columns: minmax(420px, 1.08fr) 1fr;
            min-height: 0;
        }

        /* Barra verde do portal público */
        .public-login-bar {
            min-height: 46px;
            padding: 7px clamp(20px, 4vw, 58px);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            background: var(--primary);
            color: #fff;
            font-size: .76rem;
            font-weight: 700;
            letter-spacing: .035em;
            text-transform: uppercase;
        }
        .public-login-bar a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 30px;
            padding: 6px 11px;
            border: 1px solid rgba(255,255,255,.42);
            border-radius: 3px;
            color: #fff;
            text-decoration: none;
            transition: background .16s ease, border-color .16s ease;
        }
        .public-login-bar a:hover,
        .public-login-bar a:focus-visible {
            background: rgba(255,255,255,.1);
            border-color: rgba(255,255,255,.72);
            outline: none;
        }

        /* ── Brand Panel ── */
        .brand-panel {
            position: relative;
            background: #fff;
            color: var(--ink);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: clamp(42px, 6vw, 78px);
            overflow: hidden;
            border-right: 1px solid var(--line);
        }
        .brand-panel::after {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: 5px;
            background: linear-gradient(90deg, #f8e400 0 25%, #cafe2b 25% 50%, #12d2d5 50% 75%, var(--primary) 75%);
        }
        .brand-glow {
            position: absolute; right: -180px; bottom: -210px;
            width: 560px; height: 560px; border-radius: 50%;
            background: radial-gradient(circle, rgba(0,150,64,.11), transparent 68%);
        }
        .brand-top { position: relative; z-index: 1; }
        .brand-logo {
            width: min(520px, 82%); display: block;
        }
        .brand-middle { position: relative; z-index: 1; margin-top: 32px; }
        .brand-kicker {
            display: block;
            margin-bottom: 11px;
            color: var(--primary);
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
        }
        .brand-middle h1 {
            font-size: clamp(32px, 3.4vw, 48px); font-weight: 800; line-height: 1.08;
            letter-spacing: -.025em; margin-bottom: 15px; color: var(--ink);
        }
        .brand-middle p {
            font-size: 15px; line-height: 1.6; max-width: 460px;
            color: var(--ink-2);
        }

        /* ── Form Panel ── */
        .form-panel {
            display: flex; align-items: center; justify-content: center;
            flex-direction: column;
            padding: 54px 32px; background: var(--bg);
        }
        .mobile-login-brand { display: none; }
        .login-card {
            width: 100%; max-width: 452px;
            padding: 36px 34px 32px;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-card);
        }

        /* ── Form fields ── */
        .field { margin-bottom: 16px; }
        .field-label {
            display: block; font-size: 11.5px; font-weight: 700;
            letter-spacing: .05em; color: var(--ink-2);
            text-transform: uppercase; margin-bottom: 7px;
        }
        .field-wrap {
            position: relative; display: flex; align-items: center;
            min-height: 52px; border: 1px solid #d9dedb; border-radius: 10px;
            background: #fff; transition: border-color .18s, box-shadow .18s;
        }
        .field-wrap:focus-within {
            background: #fff;
            border-color: #009640;
            box-shadow: 0 0 0 3px rgba(0, 150, 64, .12);
        }
        .field-wrap.has-error { background: var(--danger-bg); border-color: var(--danger); }
        .field-wrap.has-error:focus-within {
            box-shadow: 0 0 0 3px rgba(141, 39, 39, .12);
        }
        .field-icon { padding-left: 16px; display: flex; align-items: center; color: var(--ink-3); flex-shrink: 0; }
        .field-wrap.has-error .field-icon { color: var(--danger); }
        .field-input {
            flex: 1; border: none; outline: none; background: transparent;
            padding: 14px 12px; font-size: 1rem; color: var(--ink); font-weight: 500;
        }
        .field-input::placeholder { color: var(--ink-3); opacity: .7; }
        .field-trail { padding-right: 6px; display: flex; align-items: center; }
        .field-error {
            margin-top: 6px; font-size: 12.5px; color: var(--danger);
            display: flex; align-items: center; gap: 5px;
        }

        /* ── Buttons ── */
        .btn-primary {
            width: 100%; height: 50px; border: none; border-radius: var(--radius);
            background: var(--primary); color: #fff;
            padding: 0 16px; font-size: .95rem; font-weight: 600;
            display: inline-flex; gap: 8px; align-items: center; justify-content: center;
            cursor: pointer; transition: background .15s, transform .05s;
            margin-top: 4px;
        }
        .btn-primary:hover:not(:disabled) { background: var(--primary-600); }
        .btn-primary:active:not(:disabled) { transform: translateY(1px); }
        .btn-primary:disabled { opacity: .8; cursor: progress; }
        .btn-icon {
            border: none; background: transparent; color: var(--ink-3);
            padding: 8px; cursor: pointer; border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
        }
        .btn-icon:hover { color: var(--ink-2); }
        .btn-back {
            background: none; border: none; color: var(--ink-2); font-size: 13px;
            font-weight: 500; cursor: pointer; padding: 0;
            display: inline-flex; align-items: center; gap: 6px; margin-top: 4px;
        }
        .btn-back:hover { color: var(--primary); }
        .btn-link {
            background: none; border: none; font-size: 13px;
            color: var(--primary); font-weight: 500; cursor: pointer; padding: 0;
        }
        .btn-link:hover { text-decoration: underline; text-underline-offset: 3px; }

        /* ── Alerts ── */
        .alert {
            padding: 12px 15px; border-radius: 9px;
            display: flex; gap: 11px; align-items: flex-start; margin-bottom: 14px;
        }
        .alert-icon { flex-shrink: 0; margin-top: 1px; }
        .alert.error {
            background: var(--danger-bg); border: none;
            color: var(--danger); font-size: 13.5px;
        }
        .alert.info {
            background: var(--primary-50); border: none;
            color: var(--ink-2); font-size: 12.5px;
        }
        .alert.info .alert-icon { color: #3f9d6a; }

        /* Alertas de autenticação são persistentes: só saem por uma nova ação
           explícita (ou pelo fluxo que troca de etapa), nunca por tempo. */
        .alert[role="alert"] {
            opacity: 1 !important;
            visibility: visible !important;
            animation: none !important;
        }

        /* ── Method cards ── */
        .method-card {
            border: 1px solid #dce7df; border-radius: 10px; padding: 15px 18px;
            cursor: pointer; background: var(--primary-50); transition: all .18s;
            display: flex; gap: 14px; align-items: flex-start; width: 100%;
            text-align: left; margin-bottom: 10px;
        }
        .method-card:hover {
            background: var(--card);
            box-shadow: 0 0 0 2px var(--primary);
        }
        .method-icon {
            width: 44px; height: 44px; border-radius: 10px;
            background: var(--primary-100); color: var(--primary);
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .method-title { font-weight: 600; font-size: 14px; color: var(--ink); }
        .method-desc  { font-size: 12.5px; color: var(--ink-3); margin-top: 2px; }

        /* ── OTP inputs ── */
        .otp-grid { display: flex; gap: 10px; margin-bottom: 6px; }
        .otp-digit {
            width: 100%; height: 52px; border-radius: 8px 8px 0 0;
            border: none; border-bottom: 2px solid var(--line-2); background: transparent;
            text-align: center; font-family: inherit;
            font-size: 1.2rem; font-weight: 500; color: var(--ink);
            outline: none; transition: border-color .15s, background-color .15s;
        }
        .otp-digit:focus {
            border-bottom-color: var(--primary);
            background: var(--primary-50);
        }
        .otp-digit.has-error { border-bottom-color: var(--danger); }

        /* ── Remember device ── */
        .remember-wrap {
            display: flex; align-items: flex-start; gap: 11px; cursor: pointer;
            padding: 13px 14px; border-radius: 10px;
            background: var(--primary-50); border: 1px solid #dcefe3; margin-bottom: 4px;
        }
        .custom-check {
            width: 20px; height: 20px; border-radius: 50%; flex-shrink: 0; margin-top: 1px;
            border: 1px solid var(--line-2); background: var(--card);
            display: flex; align-items: center; justify-content: center;
            transition: all .15s; cursor: pointer;
        }
        .custom-check.on { border-color: var(--primary); background: var(--primary); }
        .remember-title {
            font-size: 13.5px; font-weight: 500; color: var(--ink);
            display: flex; align-items: center; gap: 6px;
        }
        .remember-desc { font-size: 12px; color: var(--ink-3); margin-top: 2px; }

        /* ── Spinner ── */
        .spinner {
            display: inline-block; width: 15px; height: 15px;
            border: 2px solid rgba(255,255,255,.35);
            border-top-color: #fff; border-radius: 50%;
            animation: spin .65s linear infinite;
        }

        /* ── Animations ── */
        @keyframes spin     { to { transform: rotate(360deg); } }
        @keyframes fade-in  { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
        @keyframes fill-bar { from { transform: scaleX(0); } to { transform: scaleX(1); } }
        .fade-in { animation: fade-in .2s ease both; }

        /* ── Footer ── */
        .login-footer {
            padding: 16px 28px; border-top: 1px solid var(--line);
            display: flex; align-items: center; justify-content: space-between;
            gap: 16px; flex-wrap: wrap; font-size: 12px; color: rgba(255,255,255,.58);
            background: var(--navy);
        }
        .footer-links { display: flex; gap: 20px; }
        .footer-links a { color: rgba(255,255,255,.74); text-decoration: none; font-weight: 500; font-size: 12px; }
        .footer-links a:hover { text-decoration: underline; text-underline-offset: 3px; }

        /* ── Homolog banner ── */
        .homolog-banner {
            display: flex; align-items: center; justify-content: center; gap: 9px;
            background: #f2c14e; color: #3d2d05;
            padding: 9px 16px; font-weight: 700;
            font-size: .74rem; text-transform: uppercase; letter-spacing: .14em;
        }

        /* ── Responsive ── */
        @media (max-width: 900px) {
            .layout { grid-template-columns: 1fr; }
            .brand-panel { display: none; }
            .form-panel { padding: 40px 20px; }
            .public-login-bar > span { display: none; }
            .mobile-login-brand {
                display: block;
                width: min(390px, 82vw);
                height: auto;
                margin: 0 auto 26px;
            }
        }
        @media (max-width: 480px) {
            .login-card { padding: 24px 18px; }
            .public-login-bar { justify-content: center; }
            .login-footer { justify-content: center; text-align: center; }
            .footer-links { width: 100%; justify-content: center; }
        }

        .hidden { display: none !important; }
    </style>
</head>
<body>

<?php if (defined('MODO_HOMOLOG') && MODO_HOMOLOG): ?>
<div class="homolog-banner">
    <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 2v6L4 20a1 1 0 0 0 1 2h14a1 1 0 0 0 1-2L15 8V2"/><path d="M8.5 2h7"/><path d="M6.5 15h11"/></svg>
    Ambiente de testes
</div>
<?php endif; ?>

<div class="public-login-bar">
    <span>Prefeitura Municipal de Pau dos Ferros · SEMA</span>
    <a href="../index.php" aria-label="Voltar ao protocolo eletrônico">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="M11 18l-6-6 6-6"/></svg>
        Voltar ao protocolo eletrônico
    </a>
</div>

<div class="layout">

    <!-- ══ Brand Panel ══ -->
    <aside class="brand-panel">
        <div class="brand-glow"></div>

        <div class="brand-top">
            <img src="../assets/img/logo-prefeitura-sema-horizontal.png"
                 alt="Prefeitura de Pau dos Ferros — Secretaria Municipal do Meio Ambiente"
                 class="brand-logo">
        </div>

        <div class="brand-middle">
            <span class="brand-kicker">Acesso institucional</span>
            <h1 class="display">Painel administrativo</h1>
            <p>Painel de gestão de alvarás, pareceres técnicos e licenciamento ambiental do município de Pau dos Ferros/RN.</p>
        </div>
    </aside>

    <!-- ══ Form Panel ══ -->
    <section class="form-panel">
        <img src="../assets/img/logo-prefeitura-sema-horizontal.png"
             alt="Prefeitura de Pau dos Ferros — Secretaria Municipal do Meio Ambiente"
             class="mobile-login-brand">
        <div class="login-card">

            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:8px">
                <span class="step-meta" id="step-eyebrow" style="font-size:.68rem;text-transform:uppercase;color:var(--ink-3)">Etapa 1 de 2 · Credenciais</span>
                <span class="step-meta" style="font-size:.68rem;color:var(--line-2)">SEMA</span>
            </div>
            <div style="height:3px;border-radius:999px;background:var(--line);overflow:hidden;margin-bottom:26px">
                <div id="step-progress" style="height:100%;border-radius:999px;background:var(--primary);transition:width .4s cubic-bezier(.2,.7,.3,1);width:18%"></div>
            </div>

            <!-- ── STEP: LOGIN ── -->
            <div id="step-login" class="fade-in">
                <div class="login-heading">
                    <span class="profile-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="8" r="3.5"/>
                            <path d="M5 20c.8-3.3 3.2-5 7-5s6.2 1.7 7 5"/>
                        </svg>
                    </span>
                    <div>
                        <h2 class="display">Entrar no painel</h2>
                        <p>Use seu usuário ou e-mail institucional.</p>
                    </div>
                </div>

                <?php if ($erro): ?>
                <div class="alert error" role="alert" style="margin-bottom:16px">
                    <div class="alert-icon">
                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><circle cx="12" cy="16" r=".5" fill="currentColor"/></svg>
                    </div>
                    <span><strong>Acesso bloqueado.</strong> <?= htmlspecialchars($erro) ?></span>
                </div>
                <?php endif; ?>

                <div id="login-err" class="alert error hidden" role="alert" aria-live="assertive" style="margin-bottom:16px">
                    <div class="alert-icon">
                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><circle cx="12" cy="16" r=".5" fill="currentColor"/></svg>
                    </div>
                    <span data-msg></span>
                </div>

                <form id="login-form" novalidate>
                    <input type="hidden" name="action" value="validar_credenciais">
                    <input type="hidden" name="recaptcha_token" id="recaptcha-token">

                    <div class="field">
                        <label class="field-label" for="usuario">Usuário ou e-mail</label>
                        <div class="field-wrap" id="wrap-usuario">
                            <div class="field-icon">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/></svg>
                            </div>
                            <input type="text" id="usuario" name="usuario" class="field-input"
                                   placeholder="nome.sobrenome" autocomplete="username" required>
                        </div>
                        <div class="field-error hidden" id="err-usuario">
                            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><circle cx="12" cy="16" r=".5" fill="currentColor"/></svg>
                            <span></span>
                        </div>
                    </div>

                    <div class="field">
                        <label class="field-label" for="senha">Senha</label>
                        <div class="field-wrap" id="wrap-senha">
                            <div class="field-icon">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                            </div>
                            <input type="password" id="senha" name="senha" class="field-input"
                                   placeholder="••••••••" autocomplete="current-password" required>
                            <div class="field-trail">
                                <button type="button" class="btn-icon" id="toggle-senha" aria-label="Mostrar senha">
                                    <svg id="eye-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                            </div>
                        </div>
                        <div class="field-error hidden" id="err-senha">
                            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><circle cx="12" cy="16" r=".5" fill="currentColor"/></svg>
                            <span></span>
                        </div>
                    </div>

                    <div style="display:flex;justify-content:flex-end;margin-top:-8px;margin-bottom:16px">
                        <button type="button" class="btn-link" id="btn-forgot">Esqueci minha senha</button>
                    </div>

                    <button type="submit" class="btn-primary" id="btn-login">
                        Entrar no painel
                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg>
                    </button>

                </form>
            </div>

            <!-- ── STEP: 2FA METHOD SELECTION ── -->
            <div id="step-method" class="hidden">
                <div style="margin-bottom:18px">
                    <div style="display:inline-flex;padding:10px;border-radius:10px;background:var(--primary-50);color:var(--primary);margin-bottom:12px">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l8 3v7c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V5l8-3z"/><path d="M9 12l2 2 4-4"/></svg>
                    </div>
                    <h2 class="display" style="font-size:26px;font-weight:700;margin-bottom:8px">Verificação em duas etapas</h2>
                    <p style="font-size:14px;color:var(--ink-2);line-height:1.55">
                        Como deseja receber seu código, <strong style="color:var(--ink)" id="method-user"></strong>?
                    </p>
                </div>

                <button type="button" class="method-card" id="mc-email" onclick="selectMethod('email')">
                    <div class="method-icon">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 7l-10 7L2 7"/></svg>
                    </div>
                    <div>
                        <div class="method-title">E-mail institucional</div>
                        <div class="method-desc">Enviar código para <strong id="method-email-masked"></strong></div>
                    </div>
                </button>

                <button type="button" class="method-card hidden" id="mc-app" onclick="selectMethod('app')">
                    <div class="method-icon">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="7" y="2" width="10" height="20" rx="2"/><circle cx="12" cy="18" r="1" fill="currentColor"/></svg>
                    </div>
                    <div>
                        <div class="method-title">Aplicativo autenticador</div>
                        <div class="method-desc">Usar código do Google Authenticator ou similar</div>
                    </div>
                </button>

                <button type="button" class="btn-back" onclick="goTo('login')">
                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="M11 6l-6 6 6 6"/></svg>
                    Voltar ao login
                </button>
            </div>

            <!-- ── STEP: OTP ENTRY ── -->
            <div id="step-otp" class="hidden">
                <div style="margin-bottom:16px">
                    <div id="otp-icon" style="display:inline-flex;padding:10px;border-radius:10px;background:var(--primary-50);color:var(--primary);margin-bottom:12px"></div>
                    <h2 class="display" id="otp-title" style="font-size:24px;font-weight:700;margin-bottom:7px"></h2>
                    <p id="otp-desc" style="font-size:13.5px;color:var(--ink-2);line-height:1.55"></p>
                </div>

                <div id="otp-err" class="alert error hidden" role="alert" aria-live="assertive" style="margin-bottom:12px">
                    <div class="alert-icon">
                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><circle cx="12" cy="16" r=".5" fill="currentColor"/></svg>
                    </div>
                    <span data-msg></span>
                </div>

                <form id="otp-form" novalidate>
                    <input type="hidden" name="action" value="validar_otp">
                    <input type="hidden" name="lembrar_dispositivo" id="lembrar-input" value="0">
                    <input type="hidden" name="codigo" id="codigo-hidden">

                    <div class="otp-grid">
                        <input type="text" class="otp-digit" inputmode="numeric" maxlength="1" data-i="0" aria-label="Dígito 1">
                        <input type="text" class="otp-digit" inputmode="numeric" maxlength="1" data-i="1" aria-label="Dígito 2">
                        <input type="text" class="otp-digit" inputmode="numeric" maxlength="1" data-i="2" aria-label="Dígito 3">
                        <input type="text" class="otp-digit" inputmode="numeric" maxlength="1" data-i="3" aria-label="Dígito 4">
                        <input type="text" class="otp-digit" inputmode="numeric" maxlength="1" data-i="4" aria-label="Dígito 5">
                        <input type="text" class="otp-digit" inputmode="numeric" maxlength="1" data-i="5" aria-label="Dígito 6">
                    </div>

                    <label class="remember-wrap" style="margin-top:12px" onclick="toggleRemember()">
                        <div class="custom-check" id="remember-check"></div>
                        <div>
                            <div class="remember-title">
                                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="7" y="2" width="10" height="20" rx="2"/><path d="M10 2v2h4V2"/></svg>
                                Lembrar este dispositivo por 30 dias
                            </div>
                            <div class="remember-desc">Não solicitar verificação em duas etapas neste dispositivo.</div>
                        </div>
                    </label>

                    <button type="submit" class="btn-primary" id="btn-otp" style="margin-top:12px">
                        Verificar código
                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg>
                    </button>
                </form>

                <div style="display:flex;justify-content:space-between;align-items:center;font-size:13px;margin-top:12px">
                    <button type="button" class="btn-back" id="btn-other-method" style="margin-top:0" onclick="backFromOtp()">
                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="M11 6l-6 6 6 6"/></svg>
                        <span id="btn-other-method-label">Outro método</span>
                    </button>
                    <div id="resend-wrap" class="hidden">
                        <span id="resend-timer" style="color:var(--ink-3)">
                            Reenviar em <span class="mono" id="resend-secs">60</span>s
                        </span>
                        <button type="button" class="btn-link hidden" id="btn-resend" onclick="startResend()">
                            Reenviar código
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── STEP: FORGOT PASSWORD ── -->
            <div id="step-forgot" class="hidden">
                <div style="margin-bottom:16px">
                    <div style="display:inline-flex;padding:10px;border-radius:10px;background:var(--primary-50);color:var(--primary);margin-bottom:12px">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="7.5" cy="15.5" r="5.5"/><path d="M21 2l-9.6 9.6"/><path d="M15.5 7.5l3 3"/><path d="M18 5l2 2"/></svg>
                    </div>
                    <h2 class="display" style="font-size:26px;font-weight:700;margin-bottom:8px">Recuperar acesso</h2>
                    <p style="font-size:14px;color:var(--ink-2);line-height:1.55">
                        Informe seu e-mail institucional. Enviaremos as instruções de recuperação.
                    </p>
                </div>

                <div id="forgot-err" class="alert error hidden" role="alert" aria-live="assertive" style="margin-bottom:12px">
                    <div class="alert-icon">
                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><circle cx="12" cy="16" r=".5" fill="currentColor"/></svg>
                    </div>
                    <span data-msg></span>
                </div>

                <form id="forgot-form" novalidate>
                    <input type="hidden" name="action" value="recuperar_senha">
                    <div class="field">
                        <label class="field-label" for="forgot-email">E-mail institucional</label>
                        <div class="field-wrap">
                            <div class="field-icon">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 7l-10 7L2 7"/></svg>
                            </div>
                            <input type="email" id="forgot-email" name="email" class="field-input"
                                   placeholder="nome@pauferros.rn.gov.br" autocomplete="email">
                        </div>
                    </div>

                    <div class="alert info" style="margin-bottom:14px">
                        <div class="alert-icon">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                        </div>
                        <div>
                            Não tem acesso ao e-mail cadastrado? Entre em contato com o desenvolvedor.
                        </div>
                    </div>

                    <button type="submit" class="btn-primary" id="btn-forgot-submit">
                        Enviar instruções
                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg>
                    </button>
                </form>

                <button type="button" class="btn-back" onclick="goTo('login')" style="margin-top:12px">
                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="M11 6l-6 6 6 6"/></svg>
                    Voltar ao login
                </button>
            </div>

            <!-- ── STEP: FORGOT SENT ── -->
            <div id="step-forgot-sent" class="hidden">
                <div style="display:inline-flex;padding:12px;border-radius:50%;background:var(--success-bg);color:var(--success);margin-bottom:16px">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 7l-10 7L2 7"/></svg>
                </div>
                <h2 class="display" style="font-size:26px;font-weight:700;margin-bottom:8px">Solicitação registrada</h2>
                <p style="font-size:14px;color:var(--ink-2);line-height:1.6;margin-bottom:16px">
                    Recebemos a solicitação vinculada a <strong style="color:var(--ink)" id="sent-email"></strong>.
                    A recuperação será orientada pelo desenvolvedor.
                </p>
                <div class="alert info" style="margin-bottom:16px">
                    <div class="alert-icon">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                    </div>
                    <div>
                        No momento, a redefinição de senha é feita diretamente com o desenvolvedor.
                    </div>
                </div>
                <button type="button" class="btn-back" onclick="goTo('login')">
                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="M11 6l-6 6 6 6"/></svg>
                    Voltar ao login
                </button>
            </div>

        </div><!-- /.login-card -->
    </section>
</div><!-- /.layout -->

<footer class="login-footer">
    <div style="display:flex;gap:16px;align-items:center">
        <span class="mono" style="letter-spacing:.02em">SEMA · PAU DOS FERROS/RN</span>
        <span style="opacity:.5">•</span>
        <span>© <?= date('Y') ?> Secretaria Municipal de Meio Ambiente</span>
    </div>
    <div class="footer-links">
        <a href="../suporte.php">Suporte</a>
        <a href="../privacidade.php">Privacidade</a>
        <a href="../acessibilidade.php">Acessibilidade</a>
    </div>
</footer>

<script>
/* ── State ── */
const state = { maskedEmail: '', hasTotp: false, method: 'email', remember: false, user: '' };
let resendTimer = null, resendSecs = 60;
const ALL_STEPS = ['login','method','otp','forgot','forgot-sent'];

/* ── Navigation ── */
const STEP_LABELS = {
    login: 'Etapa 1 de 2 · Credenciais',
    method: 'Etapa 2 de 2 · Verificação',
    otp: 'Etapa 2 de 2 · Código de acesso',
    forgot: 'Recuperação de acesso',
    'forgot-sent': 'Recuperação de acesso',
};
const STEP_PROGRESS = { login: '18%', method: '58%', otp: '78%', forgot: '34%', 'forgot-sent': '70%' };

function goTo(name) {
    ALL_STEPS.forEach(s => {
        const el = document.getElementById('step-' + s);
        if (el) el.classList.add('hidden');
    });
    const target = document.getElementById('step-' + name);
    if (!target) return;
    target.classList.remove('hidden');
    target.classList.remove('fade-in');
    void target.offsetWidth;
    target.classList.add('fade-in');

    document.getElementById('step-eyebrow').textContent = STEP_LABELS[name] || '';
    document.getElementById('step-progress').style.width = STEP_PROGRESS[name] || '18%';

    // Reset passwords on back-to-login
    if (name === 'login') {
        document.getElementById('senha').value = '';
        clearAlert('login-err');
        clearFieldError('usuario');
        clearFieldError('senha');
        stopResendTimer();
        clearAlert('otp-err');
        clearAlert('forgot-err');
        document.getElementById('forgot-email').value = '';
        document.getElementById('mc-app').classList.add('hidden');
        document.getElementById('method-email-masked').textContent = '';
        document.getElementById('method-user').textContent = '';
        state.maskedEmail = '';
        state.hasTotp = false;
        state.method = 'email';
        state.remember = false;
        state.user = '';
    }
}

/* ── Toggle password visibility ── */
const senhaInput = document.getElementById('senha');
const eyeIcon    = document.getElementById('eye-icon');
document.getElementById('toggle-senha').addEventListener('click', () => {
    const show = senhaInput.type === 'password';
    senhaInput.type = show ? 'text' : 'password';
    eyeIcon.innerHTML = show
        ? '<path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-6.5 0-10-7-10-7a18.5 18.5 0 0 1 5.06-5.94"/><path d="M9.9 4.24A10.94 10.94 0 0 1 12 4c6.5 0 10 7 10 7a18.6 18.6 0 0 1-2.16 3.19"/><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M2 2l20 20"/>'
        : '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>';
});

/* ── Login form ── */
document.getElementById('login-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const usuario = document.getElementById('usuario').value.trim();
    const senha   = document.getElementById('senha').value;

    let ok = true;
    if (usuario.length < 3) { showFieldError('usuario', 'Usuário inválido (mín. 3 caracteres)'); ok = false; }
    else clearFieldError('usuario');
    clearFieldError('senha');
    if (!ok) return;

    const btn = document.getElementById('btn-login');
    setLoading(btn, true, 'Verificando…');
    clearAlert('login-err');

    <?php if (MODO_HOMOLOG || DOCKER_ENV): ?>
    fetch('login.php', { method: 'POST', body: new FormData(document.getElementById('login-form')) })
    .then(r => r.json())
    .then(data => {
        setLoading(btn, false);
        btn.innerHTML = 'Entrar no painel ' + iconArrow();
        if (data.success) {
            if (data.skip_2fa) { window.location.href = data.redirect || 'index.php'; return; }
            state.user = usuario;
            state.maskedEmail = data.email_mascarado || '';
            state.hasTotp     = !!data.has_totp;
            document.getElementById('mc-app').classList.add('hidden');

            if (state.hasTotp && state.maskedEmail) {
                document.getElementById('method-user').textContent = usuario;
                document.getElementById('method-email-masked').textContent = state.maskedEmail;
                document.getElementById('mc-app').classList.remove('hidden');
                goTo('method');
            } else {
                state.method = state.hasTotp ? 'app' : 'email';
                renderOtp();
                goTo('otp');
                setTimeout(() => document.querySelector('.otp-digit')?.focus(), 80);
            }
        } else {
            let _msg = '<strong>Falha no acesso.</strong> ' + esc(data.error);
            if (data.tentativas_restantes !== null && data.tentativas_restantes !== undefined) {
                const r = data.tentativas_restantes;
                _msg += ' Você ainda tem <strong>' + r + '</strong> tentativa' + (r !== 1 ? 's' : '') + ' antes do bloqueio.';
            }
            showAlert('login-err', _msg);
        }
    })
    .catch(() => {
        setLoading(btn, false);
        btn.innerHTML = 'Entrar no painel ' + iconArrow();
        showAlert('login-err', 'Erro de rede. Tente novamente.');
    });
    <?php else: ?>
    grecaptcha.ready(() => {
        grecaptcha.execute('<?= RECAPTCHA_SITE_KEY ?>', { action: 'login' }).then(token => {
            document.getElementById('recaptcha-token').value = token;
            fetch('login.php', { method: 'POST', body: new FormData(document.getElementById('login-form')) })
            .then(r => r.json())
            .then(data => {
                setLoading(btn, false);
                btn.innerHTML = 'Entrar no painel ' + iconArrow();
                if (data.success) {
                    if (data.skip_2fa) { window.location.href = data.redirect || 'index.php'; return; }
                    state.user = usuario;
                    state.maskedEmail = data.email_mascarado || '';
                    state.hasTotp     = !!data.has_totp;
                    document.getElementById('mc-app').classList.add('hidden');

                    if (state.hasTotp && state.maskedEmail) {
                        // Both methods — show selector
                        document.getElementById('method-user').textContent = usuario;
                        document.getElementById('method-email-masked').textContent = state.maskedEmail;
                        document.getElementById('mc-app').classList.remove('hidden');
                        goTo('method');
                    } else {
                        state.method = state.hasTotp ? 'app' : 'email';
                        renderOtp();
                        goTo('otp');
                        setTimeout(() => document.querySelector('.otp-digit')?.focus(), 80);
                    }
                } else {
                    let _msg = '<strong>Falha no acesso.</strong> ' + esc(data.error);
            if (data.tentativas_restantes !== null && data.tentativas_restantes !== undefined) {
                const r = data.tentativas_restantes;
                _msg += ' Você ainda tem <strong>' + r + '</strong> tentativa' + (r !== 1 ? 's' : '') + ' antes do bloqueio.';
            }
            showAlert('login-err', _msg);
                }
            })
            .catch(() => {
                setLoading(btn, false);
                btn.innerHTML = 'Entrar no painel ' + iconArrow();
                showAlert('login-err', 'Erro de rede. Tente novamente.');
            });
        });
    });
    <?php endif; ?>
});

/* ── 2FA method selection ── */
function selectMethod(method) {
    state.method = method;
    renderOtp();
    goTo('otp');
    setTimeout(() => document.querySelector('.otp-digit')?.focus(), 80);
}

function renderOtp() {
    const isApp = state.method === 'app';
    const icon  = document.getElementById('otp-icon');
    const title = document.getElementById('otp-title');
    const desc  = document.getElementById('otp-desc');
    const resendWrap = document.getElementById('resend-wrap');
    const resendTimerEl = document.getElementById('resend-timer');
    const resendButton = document.getElementById('btn-resend');
    const otherMethodButton = document.getElementById('btn-other-method');
    const otherMethodLabel = document.getElementById('btn-other-method-label');
    const hasMultipleMethods = state.hasTotp && !!state.maskedEmail;

    if (isApp) {
        icon.innerHTML  = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="7" y="2" width="10" height="20" rx="2"/><circle cx="12" cy="18" r="1" fill="currentColor"/></svg>';
        title.textContent = 'Código do autenticador';
        desc.innerHTML    = 'Abra seu aplicativo autenticador e informe o código de 6 dígitos gerado para <strong>SEMA/Pau dos Ferros</strong>.';
        resendWrap.classList.add('hidden');
        stopResendTimer();
    } else {
        icon.innerHTML  = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 7l-10 7L2 7"/></svg>';
        title.textContent = 'Código por e-mail';
        desc.innerHTML    = 'Enviamos um código de 6 dígitos para <strong>' + esc(state.maskedEmail) + '</strong>. Verifique sua caixa de entrada.';
        resendWrap.classList.remove('hidden');
        resendTimerEl.classList.remove('hidden');
        resendButton.classList.add('hidden');
        startResend();
    }

    otherMethodLabel.textContent = hasMultipleMethods ? 'Outro método' : 'Voltar ao login';
    otherMethodButton.classList.remove('hidden');

    document.querySelectorAll('.otp-digit').forEach(d => { d.value = ''; d.classList.remove('has-error'); });
    clearAlert('otp-err');
    // Reset remember device
    state.remember = false;
    const chk = document.getElementById('remember-check');
    chk.classList.remove('on'); chk.innerHTML = '';
    document.getElementById('lembrar-input').value = '0';
}

function backFromOtp() {
    stopResendTimer();
    if (state.hasTotp && state.maskedEmail) {
        goTo('method');
    } else {
        goTo('login');
    }
}

/* ── OTP digit inputs ── */
const digits = document.querySelectorAll('.otp-digit');
digits.forEach((inp, i) => {
    inp.addEventListener('input', e => {
        const v = e.target.value.replace(/\D/g, '').slice(-1);
        e.target.value = v;
        e.target.classList.remove('has-error');
        if (v && i < 5) digits[i + 1].focus();
    });
    inp.addEventListener('keydown', e => {
        if (e.key === 'Backspace' && !inp.value && i > 0) digits[i - 1].focus();
        if (e.key === 'ArrowLeft'  && i > 0) { e.preventDefault(); digits[i - 1].focus(); }
        if (e.key === 'ArrowRight' && i < 5) { e.preventDefault(); digits[i + 1].focus(); }
    });
    inp.addEventListener('paste', e => {
        const t = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6);
        if (!t) return;
        e.preventDefault();
        [...t].forEach((c, j) => { if (digits[j]) digits[j].value = c; });
        digits[Math.min(t.length, 5)].focus();
    });
});

/* ── OTP submit ── */
document.getElementById('otp-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const code = [...digits].map(d => d.value).join('');
    if (code.length < 6) {
        digits.forEach(d => { if (!d.value) d.classList.add('has-error'); });
        showAlert('otp-err', 'Informe os 6 dígitos do código.');
        return;
    }
    document.getElementById('codigo-hidden').value = code;

    const btn = document.getElementById('btn-otp');
    setLoading(btn, true, 'Validando…');
    clearAlert('otp-err');

    fetch('login.php', { method: 'POST', body: new FormData(document.getElementById('otp-form')) })
    .then(r => r.json())
    .then(data => {
        setLoading(btn, false);
        btn.innerHTML = 'Verificar código ' + iconArrow();
        if (data.success) {
            window.location.href = data.redirect || 'index.php';
        } else {
            digits.forEach(d => d.classList.add('has-error'));
            showAlert('otp-err', esc(data.error));
        }
    })
    .catch(() => {
        setLoading(btn, false);
        btn.innerHTML = 'Verificar código ' + iconArrow();
        showAlert('otp-err', 'Erro de rede. Tente novamente.');
    });
});

/* ── Remember device ── */
function toggleRemember() {
    state.remember = !state.remember;
    const chk = document.getElementById('remember-check');
    if (state.remember) {
        chk.classList.add('on');
        chk.innerHTML = '<svg width="10" height="10" viewBox="0 0 10 10" fill="none"><path d="M1.5 5l2.5 2.5 4.5-5" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    } else {
        chk.classList.remove('on');
        chk.innerHTML = '';
    }
    document.getElementById('lembrar-input').value = state.remember ? '1' : '0';
}

/* ── Resend timer ── */
function startResend() {
    stopResendTimer();
    resendSecs = 60;
    document.getElementById('resend-secs').textContent = '60';
    document.getElementById('resend-timer').classList.remove('hidden');
    document.getElementById('btn-resend').classList.add('hidden');
    resendTimer = setInterval(() => {
        resendSecs--;
        document.getElementById('resend-secs').textContent = String(resendSecs).padStart(2, '0');
        if (resendSecs <= 0) {
            stopResendTimer();
            document.getElementById('resend-timer').classList.add('hidden');
            document.getElementById('btn-resend').classList.remove('hidden');
        }
    }, 1000);
}

function stopResendTimer() {
    if (resendTimer) {
        clearInterval(resendTimer);
        resendTimer = null;
    }
}

document.getElementById('btn-resend').addEventListener('click', function() {
    const btn = this;
    btn.disabled = true;
    clearAlert('otp-err');

    const formData = new FormData();
    formData.append('action', 'reenviar_otp_email');

    fetch('login.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        if (!data.success) {
            showAlert('otp-err', esc(data.error));
            return;
        }

        state.maskedEmail = data.email_mascarado || state.maskedEmail;
        if (state.method === 'email') {
            document.getElementById('otp-desc').innerHTML = 'Enviamos um código de 6 dígitos para <strong>' + esc(state.maskedEmail) + '</strong>. Verifique sua caixa de entrada.';
        }
        startResend();
        showAlert('otp-err', esc(data.message));
        document.getElementById('otp-err').classList.remove('error');
        document.getElementById('otp-err').classList.add('info');
    })
    .catch(() => {
        btn.disabled = false;
        showAlert('otp-err', 'Erro de rede. Tente novamente.');
    });
});

/* ── Forgot password ── */
document.getElementById('btn-forgot').addEventListener('click', () => goTo('forgot'));
document.getElementById('forgot-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const email = document.getElementById('forgot-email').value.trim();
    if (!email || !email.includes('@') || !email.includes('.')) {
        showAlert('forgot-err', 'Informe um e-mail válido.');
        return;
    }
    const btn = document.getElementById('btn-forgot-submit');
    setLoading(btn, true, 'Enviando…');
    clearAlert('forgot-err');
    fetch('login.php', { method: 'POST', body: new FormData(this) })
    .then(r => r.json())
    .then(data => {
        setLoading(btn, false);
        btn.innerHTML = 'Enviar instruções ' + iconArrow();
        document.getElementById('sent-email').textContent = data.email_mascarado || email;
        goTo('forgot-sent');
    })
    .catch(() => {
        setLoading(btn, false);
        btn.innerHTML = 'Enviar instruções ' + iconArrow();
        showAlert('forgot-err', 'Erro de rede. Tente novamente.');
    });
});

/* ── Helpers ── */
function setLoading(btn, on, label) {
    btn.disabled = on;
    if (on) btn.innerHTML = '<span class="spinner"></span>' + (label || '');
}

function showAlert(id, msg) {
    const el = document.getElementById(id);
    const span = el.querySelector('[data-msg]');
    if (span) span.innerHTML = msg;
    // Não agendar remoção: a mensagem permanece até o usuário corrigir ou
    // trocar de etapa usando uma ação explícita.
    el.dataset.persistent = 'true';
    el.classList.remove('info');
    el.classList.add('error');
    el.classList.remove('hidden');
}

function clearAlert(id) { document.getElementById(id).classList.add('hidden'); }

function showFieldError(field, msg) {
    document.getElementById('wrap-' + field)?.classList.add('has-error');
    const err = document.getElementById('err-' + field);
    if (err) { err.querySelector('span').textContent = msg; err.classList.remove('hidden'); }
}

function clearFieldError(field) {
    document.getElementById('wrap-' + field)?.classList.remove('has-error');
    document.getElementById('err-' + field)?.classList.add('hidden');
}

function esc(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function iconArrow() {
    return '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg>';
}
</script>
</body>
</html>
