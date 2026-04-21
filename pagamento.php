<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/pagamento_helpers.php';
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --bg: #f8fafc;
            --card: #ffffff;
            --ink: #0f172a;
            --muted: #475569;
            --line: #e2e8f0;
            --brand: #0f766e;
            --brand-soft: #ccfbf1;
            --warn-soft: #fff7ed;
            --warn: #f59e0b;
            --ok-soft: #ecfdf5;
            --ok: #16a34a;
            --danger-soft: #fef2f2;
            --danger: #dc2626;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(20, 184, 166, 0.12), transparent 32%),
                linear-gradient(180deg, #f8fafc 0%, #ecfeff 100%);
            color: var(--ink);
        }

        .wrap {
            max-width: 880px;
            margin: 0 auto;
            padding: 40px 16px 56px;
        }

        .hero, .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 22px;
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.08);
        }

        .hero {
            padding: 28px;
            margin-bottom: 22px;
        }

        .hero h1 {
            margin: 0 0 10px;
            font-size: 1.8rem;
        }

        .hero p {
            margin: 0;
            color: var(--muted);
            line-height: 1.6;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px;
            margin-top: 22px;
        }

        .mini-card {
            background: linear-gradient(180deg, #ffffff, #f8fafc);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 16px;
        }

        .mini-card .label {
            display: block;
            color: #64748b;
            font-size: 0.8rem;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .mini-card .value {
            font-weight: 700;
            line-height: 1.5;
        }

        .card {
            padding: 26px;
        }

        .alert {
            border-radius: 16px;
            padding: 14px 16px;
            margin-bottom: 18px;
            line-height: 1.55;
        }

        .alert-danger { background: var(--danger-soft); border: 1px solid #fecaca; color: #991b1b; }
        .alert-success { background: var(--ok-soft); border: 1px solid #bbf7d0; color: #166534; }
        .alert-info { background: #eff6ff; border: 1px solid #bfdbfe; color: #1d4ed8; }

        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0 0 14px;
            font-size: 1.08rem;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin: 18px 0 0;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 18px;
            border: none;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-primary { background: var(--brand); color: #fff; }
        .btn-secondary { background: #0f172a; color: #fff; }
        .btn-muted { background: #e2e8f0; color: #0f172a; }

        .hint {
            background: var(--warn-soft);
            border-left: 4px solid var(--warn);
            border-radius: 0 14px 14px 0;
            padding: 14px 16px;
            margin: 18px 0;
            color: #7c2d12;
        }

        form {
            margin-top: 20px;
            display: grid;
            gap: 14px;
        }

        input[type="file"] {
            width: 100%;
            padding: 14px;
            border: 1px dashed #94a3b8;
            border-radius: 14px;
            background: #f8fafc;
        }

        .muted {
            color: #64748b;
            font-size: 0.92rem;
        }

        @media (max-width: 640px) {
            .hero, .card { padding: 20px; }
            .hero h1 { font-size: 1.5rem; }
            .actions { flex-direction: column; }
            .btn { width: 100%; }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="hero">
            <h1>Pagamento do requerimento</h1>
            <p>Use esta página para acessar o boleto liberado pela equipe e enviar o comprovante em PDF após o pagamento.</p>

            <?php if ($requerimento): ?>
                <div class="grid">
                    <div class="mini-card">
                        <span class="label">Protocolo</span>
                        <div class="value">#<?php echo htmlspecialchars($requerimento['protocolo']); ?></div>
                    </div>
                    <div class="mini-card">
                        <span class="label">Tipo de solicitação</span>
                        <div class="value"><?php echo htmlspecialchars($tipoNome); ?></div>
                    </div>
                    <div class="mini-card">
                        <span class="label">Status atual</span>
                        <div class="value"><?php echo htmlspecialchars($requerimento['status']); ?></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <?php if ($mensagem !== ''): ?>
                <div class="alert alert-<?php echo htmlspecialchars($mensagemTipo); ?>">
                    <?php echo htmlspecialchars($mensagem); ?>
                </div>
            <?php endif; ?>

            <?php if ($requerimento && $pagamento): ?>
                <h2 class="section-title">
                    <i class="fas fa-receipt" style="color: var(--brand);"></i>
                    Acesso ao boleto
                </h2>

                <p class="muted">Escolha uma das opções abaixo para abrir o boleto disponibilizado para este requerimento.</p>

                <div class="actions">
                    <?php if (!empty($pagamento['boleto_url'])): ?>
                        <a class="btn btn-primary" href="<?php echo htmlspecialchars($pagamento['boleto_url']); ?>" target="_blank" rel="noopener">
                            <i class="fas fa-external-link-alt"></i>
                            Abrir boleto
                        </a>
                    <?php endif; ?>

                    <?php if ($documentoBoleto): ?>
                        <a class="btn btn-secondary" href="<?php echo htmlspecialchars(urlPublicaUpload($documentoBoleto['caminho'])); ?>" target="_blank" rel="noopener">
                            <i class="fas fa-file-pdf"></i>
                            Baixar PDF do boleto
                        </a>
                    <?php endif; ?>
                </div>

                <?php if (!empty($pagamento['instrucoes'])): ?>
                    <div class="hint">
                        <strong>Instruções da equipe:</strong><br>
                        <?php echo nl2br(htmlspecialchars($pagamento['instrucoes'])); ?>
                    </div>
                <?php endif; ?>

                <?php if ($documentoComprovante): ?>
                    <h2 class="section-title" style="margin-top: 30px;">
                        <i class="fas fa-check-circle" style="color: var(--ok);"></i>
                        Comprovante recebido
                    </h2>
                    <p class="muted">Seu comprovante já foi enviado e está em conferência pela equipe. Não é necessário reenviar neste momento.</p>
                <?php else: ?>
                    <h2 class="section-title" style="margin-top: 30px;">
                        <i class="fas fa-upload" style="color: var(--brand);"></i>
                        Enviar comprovante
                    </h2>
                    <p class="muted">Envie o comprovante de pagamento em PDF. Após o envio, o processo ficará aguardando conferência pela equipe.</p>

                    <form method="post" enctype="multipart/form-data">
                        <input type="file" name="comprovante_boleto" accept="application/pdf,.pdf" required>
                        <div class="muted">Apenas arquivos em PDF são aceitos.</div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i>
                            Enviar comprovante
                        </button>
                    </form>
                <?php endif; ?>
            <?php elseif ($mensagem === ''): ?>
                <div class="alert alert-info">Este requerimento ainda não possui boleto disponível para pagamento.</div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
