<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/tipos_alvara.php';

$mensagem = '';
$mensagemTipo = '';
$requerimento = null;
$docFinal = null;

$token = trim($_GET['token'] ?? '');
$partes = explode('.', $token, 3);
$requerimentoId = isset($partes[0]) ? (int) $partes[0] : 0;

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE    => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec("SET time_zone = '" . date('P') . "'");

    if ($requerimentoId > 0) {
        $stmt = $pdo->prepare("
            SELECT r.*, req.nome AS requerente_nome, req.email AS requerente_email
            FROM requerimentos r
            JOIN requerentes req ON req.id = r.requerente_id
            WHERE r.id = ?
            LIMIT 1
        ");
        $stmt->execute([$requerimentoId]);
        $requerimento = $stmt->fetch();
    }

    if (!$requerimento || !validarTokenDocumentoFinal($token, (int) $requerimento['id'], $requerimento['protocolo'])) {
        throw new RuntimeException('Link inválido ou expirado.');
    }

    $stmtDF = $pdo->prepare("SELECT * FROM documentos_finais WHERE requerimento_id = ? LIMIT 1");
    $stmtDF->execute([(int) $requerimento['id']]);
    $docFinal = $stmtDF->fetch();

    if (!$docFinal) {
        throw new RuntimeException('Documento final não encontrado.');
    }

    // Registra primeiro acesso
    if (empty($docFinal['visualizado_em'])) {
        $pdo->prepare("UPDATE documentos_finais SET visualizado_em = NOW() WHERE id = ?")->execute([$docFinal['id']]);
    }

} catch (Throwable $e) {
    $mensagem = $e->getMessage();
    $mensagemTipo = 'danger';
}

$tipoNome = $requerimento ? ($tipos_alvara[$requerimento['tipo_alvara']]['nome'] ?? ucwords(str_replace('_', ' ', $requerimento['tipo_alvara']))) : '';
$caminhoArquivo = $docFinal ? (rtrim(BASE_URL, '/') . '/uploads/' . ltrim($docFinal['caminho_arquivo'], '/')) : '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documento Final - SEMA</title>
    <link rel="icon" href="./assets/img/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Roboto, 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #013d86;
            background-image: url('./assets/img/background.jpg');
            background-size: contain;
            color: #1e293b;
            min-height: 100vh;
        }
        .site-header {
            background-color: #009640;
            height: 48px;
            width: 100%;
            padding: 0 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .site-header .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }
        .site-header .brand img { height: 30px; width: auto; }
        .site-header .brand span { color: #fff; font-weight: 700; font-size: 15px; }
        .page-wrapper {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: calc(100vh - 48px);
            padding: 40px 16px 60px;
        }
        .card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,.18);
            padding: 36px 32px 32px;
            max-width: 520px;
            width: 100%;
        }
        .card-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            background: #f0fdf4;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        .card-icon i { font-size: 1.6rem; color: #16a34a; }
        h1 { font-size: 1.35rem; font-weight: 700; color: #14532d; text-align: center; margin-bottom: 6px; }
        .subtitle { font-size: .875rem; color: #6b7280; text-align: center; margin-bottom: 24px; }
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f3f4f6;
            font-size: .875rem;
        }
        .info-row:last-of-type { border-bottom: none; }
        .info-label { color: #6b7280; font-weight: 500; }
        .info-value { color: #111827; font-weight: 600; text-align: right; max-width: 60%; }
        .btn-download {
            display: block;
            width: 100%;
            padding: 13px;
            border-radius: 10px;
            background: #16a34a;
            color: #fff;
            font-size: 1rem;
            font-weight: 700;
            text-align: center;
            text-decoration: none;
            border: none;
            cursor: pointer;
            margin-top: 24px;
            transition: background .2s;
        }
        .btn-download:hover { background: #15803d; }
        .instrucoes-box {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 10px;
            padding: 14px 16px;
            font-size: .875rem;
            color: #166534;
            margin-top: 16px;
        }
        .alert {
            padding: 14px 16px;
            border-radius: 10px;
            font-size: .9rem;
            text-align: center;
        }
        .alert-danger { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
    </style>
</head>
<body>
<header class="site-header">
    <a class="brand" href="./">
        <img src="./assets/img/logo-sema.webp" alt="SEMA" onerror="this.style.display='none'">
        <span>SEMA — Pau dos Ferros/RN</span>
    </a>
</header>

<div class="page-wrapper">
    <div class="card">
        <?php if ($mensagemTipo === 'danger'): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?= htmlspecialchars($mensagem) ?>
            </div>
        <?php else: ?>
            <div class="card-icon">
                <i class="fas fa-file-circle-check"></i>
            </div>
            <h1>Documento Final Disponível</h1>
            <p class="subtitle">Seu processo foi concluído pela equipe técnica.<br>Baixe o documento abaixo.</p>

            <div class="info-row">
                <span class="info-label">Protocolo</span>
                <span class="info-value"><?= htmlspecialchars($requerimento['protocolo']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Requerente</span>
                <span class="info-value"><?= htmlspecialchars($requerimento['requerente_nome']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Tipo de alvará</span>
                <span class="info-value"><?= htmlspecialchars($tipoNome) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Disponibilizado em</span>
                <span class="info-value"><?= date('d/m/Y', strtotime($docFinal['enviado_em'])) ?></span>
            </div>

            <?php if (!empty($docFinal['instrucoes'])): ?>
            <div class="instrucoes-box">
                <strong><i class="fas fa-info-circle me-2"></i>Instruções da equipe técnica:</strong><br>
                <span style="white-space:pre-wrap"><?= htmlspecialchars($docFinal['instrucoes']) ?></span>
            </div>
            <?php endif; ?>

            <a href="<?= htmlspecialchars($caminhoArquivo) ?>" class="btn-download" download target="_blank">
                <i class="fas fa-download" style="margin-right:8px"></i>
                Baixar Documento Final (PDF)
            </a>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
