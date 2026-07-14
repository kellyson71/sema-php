<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/tipos_alvara.php';

$mensagem = '';
$mensagemTipo = '';
$requerimento = null;
$docFinal = null;
$docsFinal = [];

$token = trim($_GET['token'] ?? '');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec("SET time_zone = '" . date('P') . "'");

    // O token é aleatório e vive no banco: quem autentica o acesso é a própria
    // existência de um lote válido (não revogado, não expirado) com este token.
    // Todos os documentos do lote vêm juntos — antes só o primeiro aparecia.
    $docsFinal = buscarLoteEntregaValido($pdo, $token);

    if (empty($docsFinal)) {
        throw new RuntimeException('Link de documento inválido, revogado ou expirado. Se você recebeu um e-mail mais recente da SEMA, use o link dele.');
    }

    $requerimentoId = (int) $docsFinal[0]['requerimento_id'];

    $stmt = $pdo->prepare("
        SELECT r.id, r.protocolo, r.status, r.tipo_alvara, r.endereco_objetivo,
               req.nome AS requerente_nome, req.email AS requerente_email
        FROM requerimentos r
        JOIN requerentes req ON req.id = r.requerente_id
        WHERE r.id = ?
        LIMIT 1
    ");
    $stmt->execute([$requerimentoId]);
    $requerimento = $stmt->fetch();

    if (!$requerimento) {
        throw new RuntimeException('Processo não encontrado.');
    }

    $docFinal = $docsFinal[0];

    // Registrar primeiro acesso nos documentos ainda não visualizados
    foreach ($docsFinal as $df) {
        if (!$df['visualizado_em']) {
            $pdo->prepare("UPDATE documentos_finais SET visualizado_em = NOW() WHERE id = ?")
                ->execute([$df['id']]);
        }
    }

} catch (Throwable $e) {
    $mensagem = $e->getMessage();
    $mensagemTipo = 'danger';
}

$tipoNome = $requerimento ? ($tipos_alvara[$requerimento['tipo_alvara']]['nome'] ?? ucwords(str_replace('_', ' ', $requerimento['tipo_alvara']))) : '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- O token de acesso viaja na URL: esta página nunca deve ser indexada, nem
         vazar o endereço completo no Referer ao sair para um site externo. -->
    <meta name="robots" content="noindex,nofollow">
    <meta name="referrer" content="no-referrer">
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

        .site-header .brand img { height: 32px; }

        .site-header .brand span {
            color: #fff;
            font-weight: 700;
            font-size: 14px;
            letter-spacing: .5px;
        }

        .page-wrapper {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 48px 16px 64px;
        }

        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,.18);
            width: 100%;
            max-width: 560px;
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, #065f46, #059669);
            padding: 24px 28px 20px;
            color: #fff;
        }

        .card-header .icon-wrap {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: rgba(255,255,255,.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
        }

        .card-header h1 { font-size: 1.3rem; font-weight: 700; margin-bottom: 4px; }
        .card-header p { font-size: .875rem; opacity: .85; }

        .card-body { padding: 24px 28px; }

        .info-row {
            display: flex;
            flex-direction: column;
            gap: 2px;
            margin-bottom: 16px;
        }

        .info-row label {
            font-size: .72rem;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .info-row span { font-size: .9rem; color: #1e293b; font-weight: 500; }

        .divider { border: none; border-top: 1px solid #f0f0f0; margin: 20px 0; }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: .875rem;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .alert-danger { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-info   { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }

        .btn-download {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 14px;
            background: #059669;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: background .2s;
        }
        .btn-download:hover { background: #047857; color: #fff; }

        .doc-item { margin-bottom: 14px; }

        .doc-meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 6px 14px;
            padding: 7px 4px 0;
            font-size: .75rem;
            color: #6b7280;
        }

        .doc-meta i { margin-right: 4px; }
        .doc-meta strong { color: #374151; font-weight: 600; }

        .link-verificar {
            color: #059669;
            font-weight: 700;
            text-decoration: none;
            white-space: nowrap;
        }
        .link-verificar:hover { text-decoration: underline; }

        .btn-zip {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 12px;
            margin-top: 4px;
            background: #fff;
            color: #047857;
            border: 1.5px solid #a7f3d0;
            border-radius: 8px;
            font-size: .9rem;
            font-weight: 700;
            text-decoration: none;
            transition: background .2s;
        }
        .btn-zip:hover { background: #f0fdf4; color: #047857; }

        .instrucoes-box {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            padding: 14px 16px;
            font-size: .875rem;
            color: #166534;
            margin-bottom: 20px;
            white-space: pre-wrap;
        }

        .footer-note {
            text-align: center;
            font-size: .75rem;
            color: rgba(255,255,255,.6);
            margin-top: 20px;
        }
    </style>
    <?php include __DIR__ . '/includes/posthog.php'; ?>
</head>
<body>

<header class="site-header">
    <a href="./" class="brand">
        <img src="./assets/img/logo_sema_branca.png" alt="SEMA" onerror="this.style.display='none'">
        <span>SEMA — Secretaria de Meio Ambiente</span>
    </a>
</header>

<div class="page-wrapper">
    <div>
        <div class="card">
            <div class="card-header">
                <div class="icon-wrap">
                    <i class="fas fa-file-circle-check fa-lg"></i>
                </div>
                <h1>Documento Final</h1>
                <p><?= !empty($docsFinal) && count($docsFinal) > 1 ? count($docsFinal) . ' documentos prontos para download' : 'Seu documento está pronto para download' ?></p>
            </div>
            <div class="card-body">

                <?php if ($mensagem): ?>
                    <div class="alert alert-<?= $mensagemTipo ?>">
                        <i class="fas fa-<?= $mensagemTipo === 'danger' ? 'exclamation-circle' : 'info-circle' ?>"></i>
                        <?= htmlspecialchars($mensagem) ?>
                    </div>
                <?php endif; ?>

                <?php if ($requerimento && !empty($docsFinal)): ?>

                    <div class="info-row">
                        <label>Protocolo</label>
                        <span><?= htmlspecialchars($requerimento['protocolo']) ?></span>
                    </div>

                    <div class="info-row">
                        <label>Requerente</label>
                        <span><?= htmlspecialchars($requerimento['requerente_nome']) ?></span>
                    </div>

                    <div class="info-row">
                        <label>Tipo de solicitação</label>
                        <span><?= htmlspecialchars($tipoNome) ?></span>
                    </div>

                    <?php if (!empty($requerimento['endereco_objetivo'])): ?>
                        <div class="info-row">
                            <label>Endereço</label>
                            <span><?= htmlspecialchars($requerimento['endereco_objetivo']) ?></span>
                        </div>
                    <?php endif; ?>

                    <hr class="divider">

                    <?php if (!empty($docsFinal[0]['instrucoes'])): ?>
                        <div class="instrucoes-box">
                            <strong>Observações da equipe técnica:</strong><br>
                            <?= htmlspecialchars($docsFinal[0]['instrucoes']) ?>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($docsFinal as $df): ?>
                        <?php $rotulo = rotuloDocumento($df['nome_arquivo']); ?>
                        <div class="doc-item">
                            <a href="<?= htmlspecialchars(urlArquivo($df['caminho_arquivo'], $token) . '&download=1') ?>"
                               class="btn-download">
                                <i class="fas fa-download"></i>
                                <?= htmlspecialchars($rotulo !== '' ? $rotulo : $df['nome_arquivo']) ?>
                            </a>
                            <div class="doc-meta">
                                <?php if (!empty($df['assinantes'])): ?>
                                    <span>
                                        <i class="fas fa-file-signature"></i>
                                        Assinado por <strong><?= htmlspecialchars($df['assinantes']) ?></strong>
                                        <?php if (!empty($df['assinado_em'])): ?>
                                            em <?= date('d/m/Y', strtotime($df['assinado_em'])) ?>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($df['documento_id'])): ?>
                                    <a href="<?= rtrim(BASE_URL, '/') ?>/verificar.php?id=<?= urlencode($df['documento_id']) ?>"
                                       target="_blank" rel="noopener" class="link-verificar">
                                        <i class="fas fa-shield-halved"></i> Verificar autenticidade
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (count($docsFinal) > 1): ?>
                        <a href="<?= htmlspecialchars(urlArquivo('', $token) . '&zip=1') ?>" class="btn-zip">
                            <i class="fas fa-file-zipper"></i>
                            Baixar todos os <?= count($docsFinal) ?> documentos (.zip)
                        </a>
                    <?php endif; ?>

                    <p style="text-align:center;font-size:.75rem;color:#9ca3af;margin-top:12px;">
                        <i class="fas fa-lock me-1"></i>
                        Enviado em <?= date('d/m/Y \à\s H:i', strtotime($docsFinal[0]['enviado_em'])) ?>
                        &nbsp;·&nbsp;
                        <?= count($docsFinal) ?> documento(s)
                        <?php if (!empty($docsFinal[0]['expira_em'])): ?>
                        <br>Este link fica disponível até <?= date('d/m/Y', strtotime($docsFinal[0]['expira_em'])) ?> — baixe e guarde os arquivos.
                        <?php endif; ?>
                    </p>

                <?php elseif (!$mensagem): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        Link inválido ou documento não encontrado.
                    </div>
                <?php endif; ?>

            </div>
        </div>

        <p class="footer-note">
            Secretaria Municipal de Meio Ambiente — Prefeitura de Pau dos Ferros/RN
        </p>
    </div>
</div>

</body>
</html>
