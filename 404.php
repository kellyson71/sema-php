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
    <link rel="icon" href="/assets/img/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&family=Viga&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; }
        body {
            font-family: 'Roboto', sans-serif;
            background: #013d86;
            color: #fff;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
            overflow: hidden;
        }

        /* ── Header verde ── */
        .site-header {
            background: #009640;
            height: 48px;
            padding: 0 24px;
            display: flex;
            align-items: center;
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

        /* ── Main ── */
        .main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        /* Fundo: imagem escurecida */
        .main::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url('/assets/img/background.jpg') top left / cover no-repeat;
            opacity: 0.08;
        }

        .content {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 60px;
            max-width: 860px;
            padding: 40px 32px;
        }

        /* Lado esquerdo: 404 grande */
        .left-side {
            flex-shrink: 0;
            text-align: center;
        }
        .logo-404 {
            width: 80px;
            margin-bottom: 16px;
        }
        .big-404 {
            font-size: 140px;
            font-weight: 900;
            line-height: 1;
            letter-spacing: -6px;
            color: #fff;
            text-shadow: 0 4px 30px rgba(0,0,0,0.3);
            position: relative;
        }
        .big-404 span {
            color: #00c853;
        }

        /* Divisor vertical */
        .divider-v {
            width: 2px;
            height: 180px;
            background: linear-gradient(transparent, rgba(255,255,255,0.2), transparent);
            flex-shrink: 0;
        }

        /* Lado direito: info */
        .right-side {
            max-width: 380px;
        }
        .right-side h1 {
            font-family: 'Viga', sans-serif;
            font-size: 1.4rem;
            font-weight: 400;
            margin-bottom: 10px;
            line-height: 1.3;
        }
        .right-side p {
            font-size: 0.88rem;
            color: rgba(255,255,255,0.6);
            line-height: 1.7;
            margin-bottom: 28px;
        }
        .right-side p strong { color: #fff; }

        /* Botões */
        .actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 12px 22px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
            font-family: 'Roboto', sans-serif;
        }
        .btn i { font-size: 13px; }
        .btn-green {
            background: #009640;
            color: #fff;
        }
        .btn-green:hover {
            background: #00a84a;
            box-shadow: 0 6px 20px rgba(0,150,64,0.4);
            transform: translateY(-1px);
        }
        .btn-outline {
            background: transparent;
            color: rgba(255,255,255,0.8);
            border: 1px solid rgba(255,255,255,0.2);
        }
        .btn-outline:hover {
            background: rgba(255,255,255,0.08);
            color: #fff;
            border-color: rgba(255,255,255,0.35);
        }

        /* Tag SEMA */
        .sema-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: rgba(255,255,255,0.35);
            margin-bottom: 14px;
        }
        .sema-tag i { color: #00c853; font-size: 10px; }

        /* ── Footer ── */
        .site-footer {
            padding: 12px 24px;
            text-align: center;
            font-size: 0.68rem;
            color: rgba(255,255,255,0.2);
            flex-shrink: 0;
            position: relative;
            z-index: 1;
        }

        /* ── Responsivo ── */
        @media (max-width: 700px) {
            .content {
                flex-direction: column;
                text-align: center;
                gap: 24px;
                padding: 30px 20px;
            }
            .divider-v {
                width: 120px;
                height: 2px;
                background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            }
            .big-404 { font-size: 90px; letter-spacing: -3px; }
            .logo-404 { width: 60px; }
            .right-side { max-width: 100%; }
            .right-side h1 { font-size: 1.2rem; }
        }
    </style>
    <?php if (file_exists(__DIR__ . '/includes/posthog.php')) include __DIR__ . '/includes/posthog.php'; ?>
</head>
<body>

<header class="site-header">
    <ul class="header-nav">
        <li><a href="https://www.instagram.com/prefeituradepaudosferros/"><img src="/assets/img/instagram.png" alt="Instagram"></a></li>
        <li><a href="https://www.facebook.com/prefeituradepaudosferros/"><img src="/assets/img/facebook.png" alt="Facebook"></a></li>
        <li><a href="https://twitter.com/paudosferros"><img src="/assets/img/twitter.png" alt="Twitter"></a></li>
        <li><a href="https://www.youtube.com/c/prefeituramunicipaldepaudosferros"><img src="/assets/img/youtube.png" alt="YouTube"></a></li>
    </ul>
</header>

<main class="main">
    <div class="content">
        <div class="left-side">
            <img src="/assets/img/Logo_sema.png" alt="SEMA" class="logo-404">
            <div class="big-404">4<span>0</span>4</div>
        </div>

        <div class="divider-v"></div>

        <div class="right-side">
            <div class="sema-tag">
                <i class="fas fa-leaf"></i> Secretaria de Meio Ambiente
            </div>

            <h1>
                <?php if ($isArquivo): ?>
                    Documento não encontrado
                <?php else: ?>
                    Página não encontrada
                <?php endif; ?>
            </h1>

            <p>
                <?php if ($isArquivo): ?>
                    O arquivo solicitado não existe ou foi removido do servidor.
                    Se você precisa de uma cópia, <strong>entre em contato com a SEMA</strong>
                    ou solicite ao responsável pelo processo.
                <?php else: ?>
                    O endereço que você acessou não existe ou foi movido.
                    Verifique o link digitado ou utilize uma das opções abaixo para continuar.
                <?php endif; ?>
            </p>

            <div class="actions">
                <?php if ($referer && parse_url($referer, PHP_URL_HOST) === ($_SERVER['HTTP_HOST'] ?? '')): ?>
                <a href="<?= htmlspecialchars($referer) ?>" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Voltar à página anterior
                </a>
                <?php endif; ?>

                <a href="/" class="btn btn-green">
                    <i class="fas fa-house"></i> Página inicial
                </a>

                <a href="/consultar/" class="btn btn-outline">
                    <i class="fas fa-magnifying-glass"></i> Consultar protocolo
                </a>
            </div>
        </div>
    </div>
</main>

<footer class="site-footer">
    Prefeitura Municipal de Pau dos Ferros &mdash; Secretaria Municipal de Meio Ambiente
</footer>

</body>
</html>
