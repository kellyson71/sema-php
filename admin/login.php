<?php
        // Verificação de redirecionamento para o domínio principal
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (preg_match('/^(www\.)?sema\.protocolosead\.com$/i', $host)) {
            $redirect_url = 'http://sema.paudosferros.rn.gov.br' . $_SERVER['REQUEST_URI'];
            header("HTTP/1.1 301 Moved Permanently");
            header("Location: $redirect_url");
            exit();
        }

        require_once 'conexao.php';
        require_once '../includes/email_service.php';

// Verificar se já está logado
if (isset($_SESSION['admin_id'])) {
    $redirect = $_GET['redirect'] ?? 'index.php';
    header("Location: " . filter_var($redirect, FILTER_SANITIZE_URL));
    exit;
}

if (isset($_GET['redirect'])) {
    $_SESSION['login_redirect_url'] = $_GET['redirect'];
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

// Processar formulário de login via AJAX (etapas ou login tradicional com redirecionamento ajustado)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SESSION['login_attempts'] < 5) {
    if (isset($_POST['action']) && $_POST['action'] === 'validar_credenciais') {
        // Exigência ajax para etapas
        header('Content-Type: application/json');
        
        $usuario = trim($_POST['usuario'] ?? '');
        $senha = trim($_POST['senha'] ?? '');
        $recaptcha_token = $_POST['recaptcha_token'] ?? '';

        // Validar reCAPTCHA
        $recaptcha_url = 'https://www.google.com/recaptcha/api/siteverify';
        $recaptcha_response = file_get_contents($recaptcha_url . '?secret=' . RECAPTCHA_SECRET_KEY . '&response=' . $recaptcha_token);
        $recaptcha_data = json_decode($recaptcha_response);

        if (empty($usuario) || empty($senha)) {
            echo json_encode(['success' => false, 'error' => "Por favor, preencha todos os campos."]);
            exit;
        } elseif (!$recaptcha_data->success || $recaptcha_data->score < 0.5) {
            echo json_encode(['success' => false, 'error' => "Falha na verificação de segurança (reCAPTCHA). Por favor, tente novamente."]);
            exit;
        }

        // Verificar credenciais no banco pelo nome do usuário
        $stmt = $pdo->prepare("SELECT * FROM administradores WHERE nome = ? AND ativo = 1");
        $stmt->execute([$usuario]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($senha, $admin['senha'])) {
            // Se tiver secret salva, flag para o frontend
            $hasTotp = !empty($admin['totp_secret']);

            if (!$hasTotp && empty($admin['email'])) {
                echo json_encode(['success' => false, 'error' => "Usuário não possui e-mail ou App Autenticador cadastrado para verificação em duas etapas."]);
                exit;
            }

            // Gerar código de 6 dígitos para o e-mail (fallback ou primário)
            $codigo = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            
            // Salvar na sessão temporária
            $_SESSION['2fa_admin_data'] = $admin;
            $_SESSION['2fa_otp_code'] = $codigo;
            $_SESSION['2fa_otp_expires'] = time() + (15 * 60);

            $emailMascarado = '';
            if (!empty($admin['email'])) {
                // Enviar email
                $emailService = new EmailService();
                $enviado = $emailService->enviarEmailCodigoVerificacao($admin['email'], $admin['nome'], $codigo);

                if ($enviado) {
                    // Mascarar email para exibir no frontend
                    $emailMascarado = preg_replace('/(?<=.).(?=.*@)/', '*', $admin['email']);
                } else if (!$hasTotp) {
                    echo json_encode(['success' => false, 'error' => "Erro ao enviar e-mail de código de verificação."]);
                    exit;
                }
            }

            echo json_encode([
                'success' => true, 
                'email_mascarado' => $emailMascarado,
                'has_totp' => $hasTotp
            ]);
        } else {
            $_SESSION['login_attempts']++;
            $_SESSION['last_attempt_time'] = time();
            echo json_encode(['success' => false, 'error' => "Usuário ou senha incorretos."]);
        }
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'validar_otp') {
        header('Content-Type: application/json');
        $codigoRecebido = trim($_POST['codigo'] ?? '');

        if (!isset($_SESSION['2fa_admin_data'])) {
            echo json_encode(['success' => false, 'error' => 'Sessão de verificação expirada ou inválida.']);
            exit;
        }

        $admin = $_SESSION['2fa_admin_data'];
        $codigoValido = false;
        $erroMsg = 'Código incorreto.';

        // 1. Tentar validar via App Autenticador (TOTP)
        if (!empty($admin['totp_secret'])) {
            require_once 'TwoFactorService.php';
            $twoFactorService = new \Admin\Services\TwoFactorService();
            if ($twoFactorService->verify($admin['totp_secret'], $codigoRecebido)) {
                $codigoValido = true;
            }
        }

        // 2. Se não validou pelo app, tentar pelo código de e-mail (fallback)
        if (!$codigoValido && isset($_SESSION['2fa_otp_code'])) {
            if (time() > $_SESSION['2fa_otp_expires']) {
                $erroMsg = 'Código expirado. Volte e faça login novamente.';
                // Não expira a sessão inteira pois ele ainda pode usar o TOTP
            } else if ($codigoRecebido === $_SESSION['2fa_otp_code']) {
                $codigoValido = true;
            }
        }

        if ($codigoValido) {
            $_SESSION['login_attempts'] = 0;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_nome'] = $admin['nome'];
            $_SESSION['admin_nome_completo'] = $admin['nome_completo'] ?? $admin['nome'];
            $_SESSION['admin_email'] = $admin['email'];
            $_SESSION['admin_nivel'] = $admin['nivel'];
            $_SESSION['admin_cpf'] = $admin['cpf'] ?? '';
            $_SESSION['admin_cargo'] = $admin['cargo'] ?? 'Administrador';
            $_SESSION['admin_matricula_portaria'] = $admin['matricula_portaria'] ?? '';
            
            // Sessão para assinaturas liberada indefinidamente via login
            $_SESSION['assinatura_auth_valid_until'] = time() + (24 * 60 * 60);

            $stmt = $pdo->prepare("UPDATE administradores SET ultimo_acesso = NOW() WHERE id = ?");
            $stmt->execute([$admin['id']]);

            // Limpar sessões 2FA
            unset($_SESSION['2fa_admin_data']);
            unset($_SESSION['2fa_otp_code']);
            unset($_SESSION['2fa_otp_expires']);

            $redirectUrl = $_SESSION['login_redirect_url'] ?? 'index.php';
            unset($_SESSION['login_redirect_url']);

            echo json_encode(['success' => true, 'redirect' => $redirectUrl]);
        } else {
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://www.google.com/recaptcha/api.js?render=<?php echo RECAPTCHA_SITE_KEY; ?>"></script>
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
            background: linear-gradient(135deg, #1e3c72, #2a5298, #4a90e2, #87ceeb);
            background-size: 400% 400%;
            animation: gradientShift 8s ease infinite;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', system-ui, sans-serif;
            padding: 1rem;
        }

        @keyframes gradientShift {
            0% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }

            100% {
                background-position: 0% 50%;
            }
        }

        .login-container {
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            padding: 2.5rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1), 0 6px 20px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
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
        <h2 class="login-title">Acesso Administrativo teste</h2>

        <?php if ($erro): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $erro; ?>
            </div>
        <?php endif; ?> 
        
        <div id="login-etapa-1">
            <form class="login-form" id="loginForm">
                <!-- Erro dinâmico JS -->
                <div class="alert alert-danger d-none" role="alert" id="js-erro-1">
                    <i class="fas fa-exclamation-circle"></i>
                    <span></span>
                </div>
                
                <input type="hidden" name="recaptcha_token" id="recaptchaToken">
                <input type="hidden" name="action" value="validar_credenciais">
                
                <div class="form-group">
                    <label class="form-label" for="usuario">Usuário</label>
                    <div class="form-control-wrapper">
                        <i class="fas fa-user input-icon"></i> <input type="text"
                            class="form-control"
                            id="usuario"
                            name="usuario"
                            required
                            placeholder="Digite seu usuário">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="senha">Senha</label>
                    <div class="form-control-wrapper">
                        <i class="fas fa-lock input-icon"></i> <input type="password"
                            class="form-control"
                            id="senha"
                            name="senha"
                            required
                            placeholder="Digite sua senha">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary" id="btn-submit-1">
                    <i class="fas fa-sign-in-alt me-2"></i>
                    Avançar
                </button>
            </form>
        </div>

        <div id="login-etapa-2" style="display: none; margin-top: 1.5rem; text-align: center;">
            <div id="totp-header-msg">
                <i class="fas fa-envelope fa-2x text-primary mb-3" id="totp-icon"></i>
                <p class="text-muted mb-4" id="totp-text">Para sua segurança, informe o código enviado para <strong id="email-mascarado-display">...</strong></p>
            </div>
            
            <form class="login-form" id="otpForm">
                <!-- Erro dinâmico JS -->
                <div class="alert alert-danger d-none" role="alert" id="js-erro-2">
                    <i class="fas fa-exclamation-circle"></i>
                    <span></span>
                </div>

                <input type="hidden" name="action" value="validar_otp">
                
                <div class="form-group mb-4">
                    <input type="text" id="codigo" name="codigo" class="form-control fw-bold letter-spacing-lg" placeholder="000 000" maxlength="6" style="letter-spacing: 5px; font-size: 24px; text-align: center;" required autocomplete="off">
                </div>
                
                <button type="submit" class="btn btn-primary btn-lg rounded-3 mb-2" id="btn-submit-2">
                    <i class="fas fa-check-circle me-2"></i> Entrar
                </button>
                <button type="button" class="btn btn-link text-muted btn-sm text-decoration-none" onclick="voltarParaLogin()">
                    Voltar e tentar outro usuário
                </button>
            </form>
            
            <!-- Opção de usar o Autenticador -->
            <div id="totp-option-container" style="display: none;">
                <div class="d-flex align-items-center my-4">
                    <hr class="flex-grow-1 opacity-25">
                    <span class="mx-3 text-muted small fw-bold">OU</span>
                    <hr class="flex-grow-1 opacity-25">
                </div>
                <button type="button" class="btn btn-outline-primary rounded-pill w-100 d-flex align-items-center justify-content-center py-2 fw-medium shadow-sm transition" onclick="toggleTotpMethod()" id="btn-switch-totp">
                    <i class="fas fa-mobile-alt me-2"></i> Usar App Autenticador
                </button>
            </div>

            <!-- Divulgação do Autenticador para quem não tem -->
            <div id="totp-setup-prompt" style="display: none;">
                <div class="d-flex align-items-center my-4">
                    <hr class="flex-grow-1 opacity-25">
                    <span class="mx-3 text-muted small fw-bold"><i class="fas fa-shield-alt text-primary"></i> MAIS SEGURANÇA</span>
                    <hr class="flex-grow-1 opacity-25">
                </div>
                <button type="button" class="btn btn-light rounded-pill w-100 d-flex align-items-center justify-content-center py-2 border fw-medium shadow-sm" onclick="alert('Após entrar, acesse Meu Perfil para configurar o App Autenticador!')">
                    <i class="fas fa-qrcode me-2 text-primary"></i> Configurar App Autenticador
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('.login-container').style.opacity = '0';
            setTimeout(() => {
                document.querySelector('.login-container').style.transition = 'opacity 0.5s ease';
                document.querySelector('.login-container').style.opacity = '1';
            }, 100);

            const loginForm = document.getElementById('loginForm');
            const otpForm = document.getElementById('otpForm');

            loginForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const btn = document.getElementById('btn-submit-1');
                const originalText = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Aguarde...';

                grecaptcha.ready(function() {
                    grecaptcha.execute('<?php echo RECAPTCHA_SITE_KEY; ?>', {action: 'login'}).then(function(token) {
                        document.getElementById('recaptchaToken').value = token;
                        
                        const formData = new FormData(loginForm);
                        fetch('login.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .then(data => {
                            btn.disabled = false;
                            btn.innerHTML = originalText;
                            if (data.success) {
                                window.maskedEmail = data.email_mascarado || '...';
                                window.currentAuthMethod = data.has_totp ? 'totp' : 'email';
                                document.getElementById('js-erro-1').classList.add('d-none');
                                
                                // Atualiza a UI baseada no estado do usuário
                                if (window.currentAuthMethod === 'totp') {
                                    renderTotpUI();
                                    document.getElementById('totp-option-container').style.display = 'block';
                                    document.getElementById('totp-setup-prompt').style.display = 'none';
                                } else {
                                    renderEmailUI();
                                    document.getElementById('totp-option-container').style.display = 'none';
                                    document.getElementById('totp-setup-prompt').style.display = 'block';
                                }
                                
                                document.getElementById('login-etapa-1').style.display = 'none';
                                document.getElementById('login-etapa-2').style.display = 'block';
                            } else {
                                const errBox = document.getElementById('js-erro-1');
                                errBox.querySelector('span').textContent = data.error;
                                errBox.classList.remove('d-none');
                            }
                        })
                        .catch(err => {
                            btn.disabled = false;
                            btn.innerHTML = originalText;
                            const errBox = document.getElementById('js-erro-1');
                            errBox.querySelector('span').textContent = 'Erro de rede. Tente novamente.';
                            errBox.classList.remove('d-none');
                        });
                    });
                });
            });

            otpForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const btn = document.getElementById('btn-submit-2');
                const originalText = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Validando...';

                const formData = new FormData(otpForm);
                fetch('login.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                    if (data.success) {
                        window.location.href = data.redirect || 'index.php';
                    } else {
                        const errBox = document.getElementById('js-erro-2');
                        errBox.querySelector('span').textContent = data.error;
                        errBox.classList.remove('d-none');
                    }
                })
                .catch(err => {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                    const errBox = document.getElementById('js-erro-2');
                    errBox.querySelector('span').textContent = 'Erro de rede. Tente novamente.';
                    errBox.classList.remove('d-none');
                });
            });
        });

        function voltarParaLogin() {
            document.getElementById('login-etapa-2').style.display = 'none';
            document.getElementById('login-etapa-1').style.display = 'block';
            document.getElementById('senha').value = '';
            document.getElementById('codigo').value = '';
            document.getElementById('js-erro-1').classList.add('d-none');
            document.getElementById('js-erro-2').classList.add('d-none');
        }

        function toggleTotpMethod() {
            if (window.currentAuthMethod === 'totp') {
                window.currentAuthMethod = 'email';
                renderEmailUI();
            } else {
                window.currentAuthMethod = 'totp';
                renderTotpUI();
            }
        }

        function renderTotpUI() {
            document.getElementById('totp-icon').className = 'fas fa-mobile-alt fa-3x text-primary mb-3 mt-2';
            document.getElementById('totp-text').innerHTML = `Abra seu <strong>App Autenticador</strong> e informe o código gerado.`;
            const btn = document.getElementById('btn-switch-totp');
            if (btn) btn.innerHTML = `<i class="fas fa-envelope me-2"></i> Usar código por E-mail`;
        }

        function renderEmailUI() {
            document.getElementById('totp-icon').className = 'fas fa-envelope-open-text fa-3x text-primary mb-3 mt-2';
            document.getElementById('totp-text').innerHTML = `Para sua segurança, informe o código enviado para <strong class="text-dark">${window.maskedEmail}</strong>`;
            const btn = document.getElementById('btn-switch-totp');
            if (btn) btn.innerHTML = `<i class="fas fa-mobile-alt me-2"></i> Usar App Autenticador`;
        }
    </script>
</body>

</html>
