<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/pagamento_helpers.php';
require_once __DIR__ . '/includes/admin_notifications.php';
require_once __DIR__ . '/tipos_alvara.php';

$mensagem = '';
$mensagemTipo = '';
$requerimento = null;
$pagamento = null;
$documentoBoleto = null;
$documentoComprovante = null;

$token = trim($_GET['token'] ?? '');
$partesToken = explode('.', $token, 2);
$requerimentoId = isset($partesToken[0]) ? (int) $partesToken[0] : 0;

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
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

    if (!$requerimento || !validarTokenPagamento($token, (int) $requerimento['id'], $requerimento['protocolo'])) {
        throw new RuntimeException('Link de pagamento inválido ou expirado.');
    }

    $pagamento = buscarPagamentoRequerimento($pdo, (int) $requerimento['id']);
    $documentoBoleto = buscarDocumentoPorCampo($pdo, (int) $requerimento['id'], 'boleto_pagamento_admin');
    $documentoComprovante = buscarDocumentoPorCampo($pdo, (int) $requerimento['id'], 'comprovante_pagamento_boleto');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$pagamento) {
            throw new RuntimeException('Este requerimento ainda não possui boleto liberado pela equipe.');
        }

        if ($documentoComprovante) {
            $mensagem = 'O comprovante deste boleto já foi enviado e está em conferência.';
            $mensagemTipo = 'info';
        } else {
            $arquivo = $_FILES['comprovante_boleto'] ?? null;
            if (!$arquivo || ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Selecione um comprovante em PDF para continuar.');
            }

            $pdo->beginTransaction();
            $salvo = salvarDocumentoPagamento($pdo, (int) $requerimento['id'], $requerimento['protocolo'], $arquivo, 'comprovante_pagamento_boleto');
            if ($salvo === false) {
                throw new RuntimeException('Não foi possível salvar o comprovante. Envie um PDF válido.');
            }

            $stmt = $pdo->prepare("
                UPDATE requerimento_pagamentos
                SET comprovante_enviado_em = NOW(), data_atualizacao = NOW()
                WHERE requerimento_id = ?
            ");
            $stmt->execute([(int) $requerimento['id']]);

            $stmt = $pdo->prepare("
                UPDATE requerimentos
                SET status = 'Boleto pago', comprovante_pagamento = ?, data_atualizacao = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$salvo['nome_original'], (int) $requerimento['id']]);

            $stmt = $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao) VALUES (NULL, ?, ?)");
            $stmt->execute([(int) $requerimento['id'], 'Enviou comprovante de pagamento pela página pública']);

            createAdminNotificationForRequerimento($pdo, (int) $requerimento['id'], 'comprovante_enviado');

            $pdo->commit();

            $pagamento = buscarPagamentoRequerimento($pdo, (int) $requerimento['id']);
            $documentoComprovante = buscarDocumentoPorCampo($pdo, (int) $requerimento['id'], 'comprovante_pagamento_boleto');
            $requerimento['status'] = 'Boleto pago';
            $mensagem = 'Comprovante enviado com sucesso. Agora a equipe fará a conferência para concluir o processo.';
            $mensagemTipo = 'success';
        }
    }
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
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
    <title>Pagamento de Requerimento - SEMA</title>
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

        /* ── Header ── */
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

        .site-header .brand img {
            height: 30px;
            width: auto;
        }

        .site-header .brand span {
            color: #fff;
            font-weight: 700;
            font-size: 0.85rem;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }

        .site-header .tagline {
            color: rgba(255,255,255,0.75);
            font-size: 0.78rem;
        }

        /* ── Wrap ── */
        .wrap {
            max-width: 860px;
            margin: 0 auto;
            padding: 36px 16px 64px;
        }

        /* ── Page title ── */
        .page-title {
            text-align: center;
            margin-bottom: 28px;
        }

        .page-title h1 {
            color: #ffffff;
            font-size: 1.9rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            margin-bottom: 6px;
            text-shadow: 0 2px 8px rgba(0,0,0,0.25);
        }

        .page-title p {
            color: rgba(255,255,255,0.72);
            font-size: 0.97rem;
            line-height: 1.55;
        }

        /* ── Card base ── */
        .card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 20px 50px rgba(1, 34, 84, 0.28);
            overflow: hidden;
        }

        /* ── Info strip ── */
        .info-strip {
            background: linear-gradient(135deg, #009640 0%, #007a30 100%);
            padding: 22px 28px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
        }

        .info-item .label {
            color: rgba(255,255,255,0.72);
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 4px;
        }

        .info-item .value {
            color: #ffffff;
            font-weight: 700;
            font-size: 1rem;
            line-height: 1.35;
        }

        /* ── Body ── */
        .card-body {
            padding: 30px 28px;
        }

        /* ── Alerts ── */
        .alert {
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 24px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            line-height: 1.55;
            font-size: 0.95rem;
        }

        .alert i { margin-top: 2px; flex-shrink: 0; }
        .alert-danger  { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
        .alert-info    { background: #eff6ff; border: 1px solid #bfdbfe; color: #1d4ed8; }

        /* ── Section title ── */
        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.05rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 10px;
        }

        .section-title .icon-badge {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .badge-teal   { background: #ccfbf1; color: #0f766e; }
        .badge-green  { background: #dcfce7; color: #16a34a; }
        .badge-blue   { background: #dbeafe; color: #1d4ed8; }

        .section-divider {
            border: none;
            border-top: 1px solid #e2e8f0;
            margin: 28px 0;
        }

        /* ── Boleto actions ── */
        .boleto-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 14px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 22px;
            border: none;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.92rem;
            cursor: pointer;
            transition: filter 0.15s, transform 0.1s;
        }

        .btn:hover { filter: brightness(0.92); transform: translateY(-1px); }

        .btn-green  { background: #009640; color: #fff; }
        .btn-dark   { background: #0f172a; color: #fff; }
        .btn-submit { background: #024287; color: #fff; }

        /* ── Instructions hint ── */
        .hint {
            background: #fffbeb;
            border-left: 4px solid #f59e0b;
            border-radius: 0 12px 12px 0;
            padding: 14px 18px;
            margin: 20px 0;
            color: #78350f;
            font-size: 0.93rem;
            line-height: 1.6;
        }

        /* ── Upload form ── */
        .upload-area {
            border: 2px dashed #94a3b8;
            border-radius: 14px;
            background: #f8fafc;
            padding: 22px 18px;
            text-align: center;
            cursor: pointer;
            transition: border-color 0.2s, background 0.2s;
            position: relative;
        }

        .upload-area:hover {
            border-color: #024287;
            background: #eff6ff;
        }

        .upload-area input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }

        .upload-icon {
            font-size: 2rem;
            color: #94a3b8;
            margin-bottom: 8px;
        }

        .upload-label {
            font-weight: 600;
            color: #334155;
            margin-bottom: 4px;
        }

        .upload-hint-text {
            color: #64748b;
            font-size: 0.83rem;
        }

        .file-name-display {
            margin-top: 10px;
            font-size: 0.85rem;
            color: #024287;
            font-weight: 600;
            display: none;
        }

        .upload-form {
            display: grid;
            gap: 14px;
            margin-top: 16px;
        }

        /* ── Success badge ── */
        .success-badge {
            display: flex;
            align-items: center;
            gap: 14px;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 14px;
            padding: 16px 20px;
            margin-top: 14px;
        }

        .success-badge .check {
            width: 42px;
            height: 42px;
            background: #16a34a;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .success-badge .text strong {
            display: block;
            color: #166534;
            font-size: 0.97rem;
        }

        .success-badge .text span {
            color: #15803d;
            font-size: 0.85rem;
        }

        /* ── No boleto ── */
        .empty-state {
            text-align: center;
            padding: 32px 0 8px;
            color: #64748b;
        }

        .empty-state i {
            font-size: 2.5rem;
            color: #cbd5e1;
            margin-bottom: 14px;
        }

        .empty-state p {
            font-size: 0.95rem;
            line-height: 1.6;
        }

        /* ── Footer ── */
        .site-footer {
            text-align: center;
            color: rgba(255,255,255,0.45);
            font-size: 0.78rem;
            padding: 0 16px 24px;
            line-height: 1.6;
        }

        @media (max-width: 600px) {
            .info-strip  { padding: 18px 20px; }
            .card-body   { padding: 22px 18px; }
            .boleto-actions { flex-direction: column; }
            .btn { width: 100%; }
            .page-title h1 { font-size: 1.4rem; }
        }
    </style>
</head>
<body>

    <header class="site-header">
        <a class="brand" href="./">
            <img src="./assets/img/Logo_sema.png" alt="SEMA">
            <span>Secretaria Municipal de Meio Ambiente</span>
        </a>
        <span class="tagline">Pau dos Ferros / RN</span>
    </header>

    <div class="wrap">

        <div class="page-title">
            <h1>Pagamento do Requerimento</h1>
            <p>Acesse o boleto liberado pela equipe e envie o comprovante após o pagamento.</p>
        </div>

        <div class="card">

            <?php if ($requerimento): ?>
            <div class="info-strip">
                <div class="info-item">
                    <div class="label">Protocolo</div>
                    <div class="value">#<?php echo htmlspecialchars($requerimento['protocolo']); ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Tipo de solicitação</div>
                    <div class="value"><?php echo htmlspecialchars($tipoNome); ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Status atual</div>
                    <div class="value"><?php echo htmlspecialchars($requerimento['status']); ?></div>
                </div>
            </div>
            <?php endif; ?>

            <div class="card-body">

                <?php if ($mensagem !== ''): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($mensagemTipo); ?>">
                        <?php if ($mensagemTipo === 'success'): ?>
                            <i class="fas fa-check-circle"></i>
                        <?php elseif ($mensagemTipo === 'danger'): ?>
                            <i class="fas fa-exclamation-circle"></i>
                        <?php else: ?>
                            <i class="fas fa-info-circle"></i>
                        <?php endif; ?>
                        <span><?php echo htmlspecialchars($mensagem); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($requerimento && $pagamento): ?>

                    <div class="section-title">
                        <div class="icon-badge badge-teal">
                            <i class="fas fa-receipt"></i>
                        </div>
                        Acesso ao boleto
                    </div>
                    <p style="color:#475569;font-size:0.93rem;margin-bottom:4px;">
                        Escolha uma das opções abaixo para abrir o boleto disponibilizado para este requerimento.
                    </p>

                    <div class="boleto-actions">
                        <?php if (!empty($pagamento['boleto_url'])): ?>
                            <a class="btn btn-green" href="<?php echo htmlspecialchars($pagamento['boleto_url']); ?>" target="_blank" rel="noopener">
                                <i class="fas fa-external-link-alt"></i>
                                Abrir boleto online
                            </a>
                        <?php endif; ?>
                        <?php if ($documentoBoleto): ?>
                            <a class="btn btn-dark" href="<?php echo htmlspecialchars(urlPublicaUpload($documentoBoleto['caminho'])); ?>" target="_blank" rel="noopener">
                                <i class="fas fa-file-pdf"></i>
                                Baixar PDF do boleto
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($pagamento['instrucoes'])): ?>
                        <div class="hint">
                            <strong><i class="fas fa-info-circle"></i> Instruções da equipe:</strong><br>
                            <?php echo nl2br(htmlspecialchars($pagamento['instrucoes'])); ?>
                        </div>
                    <?php endif; ?>

                    <hr class="section-divider">

                    <?php if ($documentoComprovante): ?>
                        <div class="section-title">
                            <div class="icon-badge badge-green">
                                <i class="fas fa-check"></i>
                            </div>
                            Comprovante recebido
                        </div>
                        <div class="success-badge">
                            <div class="check"><i class="fas fa-check"></i></div>
                            <div class="text">
                                <strong>Seu comprovante foi enviado com sucesso</strong>
                                <span>A equipe está realizando a conferência. Não é necessário reenviar.</span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="section-title">
                            <div class="icon-badge badge-blue">
                                <i class="fas fa-upload"></i>
                            </div>
                            Enviar comprovante
                        </div>
                        <p style="color:#475569;font-size:0.93rem;">
                            Após o pagamento, envie o comprovante em PDF. O processo ficará aguardando conferência pela equipe.
                        </p>

                        <form class="upload-form" method="post" enctype="multipart/form-data">
                            <div class="upload-area" onclick="document.getElementById('file-input').click()">
                                <input type="file" id="file-input" name="comprovante_boleto" accept="application/pdf,.pdf" required
                                    onchange="document.getElementById('file-name').style.display='block'; document.getElementById('file-name').textContent=this.files[0]?.name ?? ''">
                                <div class="upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                                <div class="upload-label">Clique para selecionar o comprovante</div>
                                <div class="upload-hint-text">Apenas arquivos PDF são aceitos &mdash; máximo 10 MB</div>
                                <div class="file-name-display" id="file-name"></div>
                            </div>
                            <button type="submit" class="btn btn-submit">
                                <i class="fas fa-paper-plane"></i>
                                Enviar comprovante
                            </button>
                        </form>
                    <?php endif; ?>

                <?php elseif ($mensagem === ''): ?>
                    <div class="empty-state">
                        <i class="fas fa-clock"></i>
                        <p>Este requerimento ainda não possui boleto disponível para pagamento.<br>Aguarde o contato da equipe da SEMA.</p>
                    </div>
                <?php endif; ?>

            </div><!-- /.card-body -->
        </div><!-- /.card -->

        <div class="site-footer" style="margin-top:24px;">
            Secretaria Municipal de Meio Ambiente &mdash; Prefeitura de Pau dos Ferros / RN<br>
            Em caso de dúvidas, entre em contato: <strong style="color:rgba(255,255,255,0.6);">(84) 99668-6413</strong> &nbsp;|&nbsp; fiscalizacaosemapdf@gmail.com
        </div>

    </div><!-- /.wrap -->

</body>
</html>
