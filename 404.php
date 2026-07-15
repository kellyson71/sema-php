<?php
http_response_code(404);

$referer    = $_SERVER['HTTP_REFERER'] ?? '';
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$isArquivo  = (bool) preg_match('#/(uploads|pareceres|pareceres_denuncia)/#', $requestUri);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isArquivo ? 'Arquivo não encontrado' : 'Página não encontrada' ?> — SEMA</title>
    <link rel="icon" href="./assets/img/favicon.ico" type="image/x-icon">
    <link rel="shortcut icon" href="./assets/img/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Viga&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; }
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #013d86;
            background-image: url(./assets/img/background.jpg);
            background-size: contain;
            color: #fff;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Header verde (igual ao index) ── */
        .site-header {
            background-color: #009640;
            height: 48px;
            width: 100%;
            padding: 0 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }
        .header-nav {
            display: flex;
            align-items: center;
            list-style: none;
            gap: 10px;
        }
        .header-nav a { display: inline-block; font-size: 0; }
        .header-nav img { width: 25px; height: 25px; }

        /* ── Conteúdo central ── */
        .main {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            gap: 0;
        }

        /* Logo + nome (igual ao form-header do index) */
        .brand {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 28px;
        }
        .brand img {
            width: 90px;
            height: auto;
            margin-bottom: 10px;
            filter: drop-shadow(0 2px 8px rgba(0,0,0,0.3));
        }
        .brand h1 {
            color: #fff;
            font-family: 'Viga', sans-serif;
            font-size: 1.1rem;
            text-align: center;
            line-height: 1.3;
            letter-spacing: 0.02em;
        }
        .brand p {
            color: rgba(255,255,255,0.55);
            font-size: 0.72rem;
            font-weight: 500;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-top: 4px;
        }

        /* Card glassmorphism */
        .card-404 {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 16px;
            padding: 40px 40px 36px;
            max-width: 440px;
            width: 100%;
            text-align: center;
        }

        .error-code {
            font-size: 80px;
            font-weight: 700;
            color: rgba(255,255,255,0.12);
            line-height: 1;
            letter-spacing: -0.04em;
            margin-bottom: 8px;
            user-select: none;
        }

        .icon-wrap {
            width: 56px; height: 56px;
            background: rgba(0,150,64,0.2);
            border: 1px solid rgba(0,150,64,0.35);
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 18px;
        }
        .icon-wrap i {
            font-size: 22px;
            color: #4ade80;
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 8px;
            line-height: 1.3;
        }
        .card-desc {
            font-size: 0.82rem;
            color: rgba(255,255,255,0.6);
            line-height: 1.65;
            margin-bottom: 28px;
        }
        .card-desc strong { color: #fff; font-weight: 600; }

        /* ── Botões ── */
        .actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px 20px;
            border-radius: 9px;
            font-size: 0.82rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            border: 1px solid transparent;
            cursor: pointer;
            font-family: 'Roboto', sans-serif;
        }
        .btn i { font-size: 12px; }
        .btn-primary {
            background: #009640;
            color: #fff;
            border-color: rgba(0,150,64,0.5);
        }
        .btn-primary:hover {
            background: #00a84a;
            box-shadow: 0 4px 16px rgba(0,150,64,0.35);
            transform: translateY(-1px);
        }
        .btn-secondary {
            background: rgba(255,255,255,0.08);
            color: rgba(255,255,255,0.85);
            border-color: rgba(255,255,255,0.18);
        }
        .btn-secondary:hover {
            background: rgba(255,255,255,0.14);
            color: #fff;
        }

        /* ── Divisor ── */
        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            color: rgba(255,255,255,0.25);
            font-size: 0.65rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin: 2px 0;
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(255,255,255,0.12);
        }

        /* ── Rodapé ── */
        .site-footer {
            padding: 14px 24px;
            text-align: center;
            font-size: 0.68rem;
            color: rgba(255,255,255,0.3);
            flex-shrink: 0;
        }

        @media (max-width: 540px) {
            .card-404 { padding: 32px 24px 28px; }
            .error-code { font-size: 60px; }
            .brand img { width: 70px; }
            .brand h1 { font-size: 0.95rem; }
        }
    </style>
    <?php if (file_exists(__DIR__ . '/includes/posthog.php')) include __DIR__ . '/includes/posthog.php'; ?>
</head>
<body>

<header class="site-header">
    <ul class="header-nav">
        <li><a href="https://www.instagram.com/prefeituradepaudosferros/"><img src="./assets/img/instagram.png" alt="Instagram"></a></li>
        <li><a href="https://www.facebook.com/prefeituradepaudosferros/"><img src="./assets/img/facebook.png" alt="Facebook"></a></li>
        <li><a href="https://twitter.com/paudosferros"><img src="./assets/img/twitter.png" alt="Twitter"></a></li>
        <li><a href="https://www.youtube.com/c/prefeituramunicipaldepaudosferros"><img src="./assets/img/youtube.png" alt="YouTube"></a></li>
    </ul>
</header>

<main class="main">
    <div class="brand">
        <img src="./assets/img/Logo_sema.png" alt="SEMA">
        <h1>SECRETARIA MUNICIPAL DE MEIO AMBIENTE</h1>
        <p>Prefeitura de Pau dos Ferros / RN</p>
    </div>

    <div class="card-404">
        <div class="error-code">404</div>

        <div class="icon-wrap">
            <i class="fas <?= $isArquivo ? 'fa-file-circle-xmark' : 'fa-map-location-dot' ?>"></i>
        </div>

        <h2 class="card-title">
            <?= $isArquivo ? 'Documento não encontrado' : 'Página não encontrada' ?>
        </h2>

        <p class="card-desc">
            <?php if ($isArquivo): ?>
                O arquivo solicitado não existe ou foi removido do servidor.<br>
                Se você precisa de uma cópia, <strong>entre em contato com a SEMA</strong><br>
                ou solicite ao responsável pelo processo.
            <?php else: ?>
                O endereço que você acessou não existe ou foi movido.<br>
                Verifique o link ou use uma das opções abaixo.
            <?php endif; ?>
        </p>

        <div class="actions">
            <?php if ($isArquivo): ?>
                <a href="/consultar/" class="btn btn-primary">
                    <i class="fas fa-search"></i> Consultar protocolo
                </a>
                <div class="divider">ou</div>
                <a href="/" class="btn btn-secondary">
                    <i class="fas fa-house"></i> Voltar ao início
                </a>
            <?php else: ?>
                <?php if ($referer && parse_url($referer, PHP_URL_HOST) === ($_SERVER['HTTP_HOST'] ?? '')): ?>
                <a href="<?= htmlspecialchars($referer) ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
                <?php endif; ?>
                <a href="/" class="btn btn-primary">
                    <i class="fas fa-house"></i> Página inicial
                </a>
                <div class="divider">ou</div>
                <a href="/consultar/" class="btn btn-secondary">
                    <i class="fas fa-search"></i> Consultar protocolo
                </a>
            <?php endif; ?>
        </div>
    </div>
</main>

<footer class="site-footer">
    Prefeitura Municipal de Pau dos Ferros &mdash; Secretaria Municipal de Meio Ambiente
</footer>

</body>
</html>
