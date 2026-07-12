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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: #f8faf9;
            color: #1a2e22;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Cabeçalho ── */
        .site-header {
            background: #fff;
            border-bottom: 1px solid #e5ece8;
            padding: 0 32px;
            height: 60px;
            display: flex;
            align-items: center;
        }
        .header-brand {
            display: flex;
            align-items: center;
            gap: 11px;
            text-decoration: none;
        }
        .header-icon {
            width: 34px; height: 34px;
            background: #009851;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .header-icon i { color: #fff; font-size: 14px; }
        .header-label-top  { font-size: 10px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #6b8c75; line-height: 1; }
        .header-label-main { font-size: 13px; font-weight: 700; color: #1a2e22; line-height: 1.2; }

        /* ── Conteúdo central ── */
        .main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px 24px;
        }
        .card {
            background: #fff;
            border: 1px solid #e5ece8;
            border-radius: 16px;
            padding: 52px 48px 44px;
            max-width: 480px;
            width: 100%;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,.04), 0 12px 32px -8px rgba(0,40,20,.07);
        }

        .error-code {
            font-size: 72px;
            font-weight: 700;
            color: #d1e4d9;
            line-height: 1;
            letter-spacing: -.04em;
            margin-bottom: 20px;
            user-select: none;
        }
        .icon-wrap {
            width: 56px; height: 56px;
            background: #f0f7f3;
            border: 1px solid #d1e4d9;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }
        .icon-wrap i {
            font-size: 22px;
            color: #5a9470;
        }

        .card-title {
            font-size: 18px;
            font-weight: 700;
            color: #1a2e22;
            margin-bottom: 8px;
            line-height: 1.3;
        }
        .card-desc {
            font-size: 13.5px;
            color: #5a7060;
            line-height: 1.65;
            margin-bottom: 32px;
        }
        .card-desc strong { color: #1a2e22; font-weight: 600; }

        /* ── Ações ── */
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
            font-size: 13.5px;
            font-weight: 600;
            text-decoration: none;
            transition: opacity .15s, background .15s;
            border: 1px solid transparent;
            cursor: pointer;
        }
        .btn-primary {
            background: #009851;
            color: #fff;
        }
        .btn-primary:hover { opacity: .88; }
        .btn-secondary {
            background: #fff;
            color: #2d4a35;
            border-color: #c5d9cc;
        }
        .btn-secondary:hover { background: #f4f8f5; }
        .btn i { font-size: 12px; }

        /* ── Divisor ── */
        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #9ab5a3;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .06em;
            text-transform: uppercase;
            margin: 4px 0;
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e5ece8;
        }

        /* ── Rodapé ── */
        .site-footer {
            border-top: 1px solid #e5ece8;
            padding: 16px 32px;
            text-align: center;
            font-size: 11.5px;
            color: #8aaa94;
        }

        @media (max-width: 540px) {
            .card { padding: 36px 24px 32px; }
            .error-code { font-size: 56px; }
        }
    </style>
    <?php include __DIR__ . '/includes/posthog.php'; ?>
</head>
<body>

<header class="site-header">
    <a href="/" class="header-brand">
        <div class="header-icon"><i class="fas fa-leaf"></i></div>
        <div>
            <div class="header-label-top">SEMA</div>
            <div class="header-label-main">Secretaria de Meio Ambiente</div>
        </div>
    </a>
</header>

<main class="main">
    <div class="card">
        <div class="error-code">404</div>

        <div class="icon-wrap">
            <i class="fas <?= $isArquivo ? 'fa-file-slash' : 'fa-map-pin' ?>"></i>
        </div>

        <h1 class="card-title">
            <?= $isArquivo ? 'Documento não encontrado' : 'Página não encontrada' ?>
        </h1>

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
