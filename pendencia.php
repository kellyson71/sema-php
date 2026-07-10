<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/pendencia_helpers.php';
require_once __DIR__ . '/includes/admin_notifications.php';
require_once __DIR__ . '/tipos_alvara.php';

$mensagem = '';
$mensagemTipo = '';
$requerimento = null;
$pendencia = null;
$anexos = [];

$token = trim($_GET['token'] ?? '');
$partesToken = explode('.', $token, 2);
$pendenciaId = isset($partesToken[0]) ? (int) $partesToken[0] : 0;

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec("SET time_zone = '" . date('P') . "'");

    if ($pendenciaId > 0) {
        $pendencia = buscarPendencia($pdo, $pendenciaId);
    }

    if ($pendencia) {
        $stmt = $pdo->prepare("
            SELECT r.*, req.nome AS requerente_nome, req.email AS requerente_email
            FROM requerimentos r
            JOIN requerentes req ON req.id = r.requerente_id
            WHERE r.id = ?
            LIMIT 1
        ");
        $stmt->execute([(int) $pendencia['requerimento_id']]);
        $requerimento = $stmt->fetch();
    }

    if (!$pendencia || !$requerimento || !validarTokenPendencia($token, (int) $pendencia['id'], $requerimento['protocolo'])) {
        throw new RuntimeException('Link de complementação inválido ou expirado.');
    }

    $anexos = listarAnexosPendencia($pdo, (int) $requerimento['id'], (int) $pendencia['id']);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($pendencia['status'] !== 'aberta') {
            throw new RuntimeException('Esta solicitação já foi respondida e não aceita novos envios.');
        }

        $resposta = trim($_POST['resposta'] ?? '');
        $arquivos = normalizarUploadMultiplo($_FILES['anexos'] ?? null);

        if ($resposta === '' && empty($arquivos)) {
            throw new RuntimeException('Escreva uma resposta ou anexe ao menos um documento.');
        }

        $pdo->beginTransaction();

        foreach ($arquivos as $arquivo) {
            $salvo = salvarDocumentoPagamento(
                $pdo,
                (int) $requerimento['id'],
                $requerimento['protocolo'],
                $arquivo,
                campoFormularioPendencia((int) $pendencia['id'])
            );
            if ($salvo === false) {
                throw new RuntimeException('Não foi possível salvar "' . $arquivo['name'] . '". Envie apenas PDFs de até 10 MB.');
            }
        }

        $stmt = $pdo->prepare("
            UPDATE requerimento_pendencias
            SET resposta = ?, status = 'respondida', respondido_em = NOW()
            WHERE id = ? AND status = 'aberta'
        ");
        $stmt->execute([$resposta, (int) $pendencia['id']]);

        $stmt = $pdo->prepare("UPDATE requerimentos SET status = 'Em análise', data_atualizacao = NOW() WHERE id = ?");
        $stmt->execute([(int) $requerimento['id']]);

        $stmt = $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao) VALUES (NULL, ?, ?)");
        $stmt->execute([
            (int) $requerimento['id'],
            'Respondeu a solicitação de complementação: ' . $pendencia['titulo'] . ' (' . count($arquivos) . ' anexo(s))'
        ]);

        $pdo->commit();

        createAdminNotificationForRequerimento($pdo, (int) $requerimento['id'], 'pendencia_respondida');

        $pendencia = buscarPendencia($pdo, (int) $pendencia['id']);
        $anexos = listarAnexosPendencia($pdo, (int) $requerimento['id'], (int) $pendencia['id']);
        $mensagem = 'Complementação enviada com sucesso. A equipe da SEMA voltará a analisar seu processo.';
        $mensagemTipo = 'success';
    }
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $mensagem = $e->getMessage();
    $mensagemTipo = 'danger';
}

$tipoAlvaraNome = $requerimento
    ? ($tipos_alvara[$requerimento['tipo_alvara']]['nome'] ?? ucwords(str_replace('_', ' ', $requerimento['tipo_alvara'])))
    : '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complementação de Requerimento - SEMA</title>
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

        .site-header .brand img { height: 30px; width: auto; }

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
        .wrap { max-width: 780px; margin: 0 auto; padding: 36px 16px 64px; }

        /* ── Page title ── */
        .page-title { text-align: center; margin-bottom: 28px; }

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
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 14px;
        }

        .info-item .label {
            color: rgba(255,255,255,0.72);
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 4px;
        }

        .info-item .value { color: #ffffff; font-weight: 700; font-size: 1rem; line-height: 1.35; }

        .card-body { padding: 30px 28px; }

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

        .badge-amber { background: #fef3c7; color: #b45309; }
        .badge-blue  { background: #dbeafe; color: #1d4ed8; }
        .badge-green { background: #dcfce7; color: #16a34a; }

        .section-divider { border: none; border-top: 1px solid #e2e8f0; margin: 28px 0; }

        /* ── Pendência (o que o admin pediu) ── */
        .hint {
            background: #fffbeb;
            border-left: 4px solid #f59e0b;
            border-radius: 0 12px 12px 0;
            padding: 14px 18px;
            margin: 8px 0 24px;
            color: #78350f;
            font-size: 0.93rem;
            line-height: 1.6;
        }

        label { display: block; font-weight: 600; color: #0f172a; margin-bottom: 6px; font-size: 0.9rem; }

        textarea {
            width: 100%;
            min-height: 130px;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font: inherit;
            resize: vertical;
        }

        /* ── Upload ── */
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

        .upload-area:hover { border-color: #024287; background: #eff6ff; }

        .upload-area input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }

        .upload-icon { font-size: 2rem; color: #94a3b8; margin-bottom: 8px; }
        .upload-label { font-weight: 600; color: #334155; margin-bottom: 4px; }
        .upload-hint-text { color: #64748b; font-size: 0.83rem; }
        .file-list { margin-top: 10px; font-size: 0.85rem; color: #024287; font-weight: 600; text-align: left; }
        .file-list div { padding: 2px 0; display: flex; align-items: center; gap: 6px; }

        .upload-form { display: grid; gap: 14px; margin-top: 16px; }

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
        .btn-submit { background: #024287; color: #fff; }

        /* ── Lista de documentos (mesmo padrão usado no admin e na consulta pública) ── */
        .doc-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .doc-row:last-child { border-bottom: none; }
        .doc-row i { font-size: 1.2rem; color: #dc2626; flex-shrink: 0; }
        .doc-row .name { font-weight: 600; font-size: 0.9rem; color: #0f172a; }
        .doc-row .meta { font-size: 0.78rem; color: #64748b; }
        .doc-row a.view { margin-left: auto; color: #024287; font-size: 0.85rem; }

        /* ── Footer ── */
        .site-footer {
            text-align: center;
            color: rgba(255,255,255,0.45);
            font-size: 0.78rem;
            padding: 0 16px 24px;
            line-height: 1.6;
        }

        @media (max-width: 600px) {
            .info-strip { padding: 18px 20px; }
            .card-body  { padding: 22px 18px; }
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
            <h1>Complementação de Requerimento</h1>
            <p>Envie a informação ou os documentos solicitados pela equipe técnica.</p>
        </div>

        <div class="card">

            <?php if ($requerimento): ?>
            <div class="info-strip">
                <div class="info-item">
                    <div class="label">Protocolo</div>
                    <div class="value">#<?= htmlspecialchars($requerimento['protocolo']) ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Requerente</div>
                    <div class="value"><?= htmlspecialchars($requerimento['requerente_nome']) ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Tipo de solicitação</div>
                    <div class="value"><?= htmlspecialchars($tipoAlvaraNome) ?></div>
                </div>
            </div>
            <?php endif; ?>

            <div class="card-body">

                <?php if ($mensagem !== ''): ?>
                    <div class="alert alert-<?= htmlspecialchars($mensagemTipo) ?>">
                        <?php if ($mensagemTipo === 'success'): ?>
                            <i class="fas fa-check-circle"></i>
                        <?php elseif ($mensagemTipo === 'danger'): ?>
                            <i class="fas fa-exclamation-circle"></i>
                        <?php else: ?>
                            <i class="fas fa-info-circle"></i>
                        <?php endif; ?>
                        <span><?= htmlspecialchars($mensagem) ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($requerimento && $pendencia): ?>

                    <div class="section-title">
                        <div class="icon-badge badge-amber"><i class="fas fa-triangle-exclamation"></i></div>
                        <?= htmlspecialchars($pendencia['titulo']) ?>
                    </div>
                    <div class="hint">
                        <?= nl2br(htmlspecialchars($pendencia['descricao'])) ?>
                    </div>

                    <?php if ($pendencia['status'] === 'aberta'): ?>

                        <form class="upload-form" method="post" enctype="multipart/form-data">
                            <div>
                                <label for="resposta">Sua resposta</label>
                                <textarea id="resposta" name="resposta" placeholder="Descreva a informação solicitada..."><?= htmlspecialchars($_POST['resposta'] ?? '') ?></textarea>
                            </div>

                            <div>
                                <label>Documentos complementares (opcional)</label>
                                <div class="upload-area" onclick="document.getElementById('anexos').click()">
                                    <input type="file" id="anexos" name="anexos[]" accept="application/pdf,.pdf" multiple onchange="listarArquivos(this)">
                                    <div class="upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                                    <div class="upload-label">Clique para selecionar um ou mais arquivos</div>
                                    <div class="upload-hint-text">Apenas PDF, máximo 10 MB por arquivo</div>
                                    <div class="file-list" id="file-list"></div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-submit">
                                <i class="fas fa-paper-plane"></i>
                                Enviar complementação
                            </button>
                        </form>

                    <?php else: ?>

                        <div class="alert alert-info">
                            <i class="fas fa-check-circle"></i>
                            <span>Esta solicitação já foi respondida em <?= htmlspecialchars(date('d/m/Y H:i', strtotime($pendencia['respondido_em'] ?? $pendencia['criado_em']))) ?>. Não é necessário reenviar.</span>
                        </div>

                        <?php if (!empty($pendencia['resposta'])): ?>
                            <div class="section-title" style="margin-top:20px;">
                                <div class="icon-badge badge-blue"><i class="fas fa-comment"></i></div>
                                Resposta enviada
                            </div>
                            <p style="color:#475569;font-size:0.93rem;"><?= nl2br(htmlspecialchars($pendencia['resposta'])) ?></p>
                        <?php endif; ?>

                        <?php if ($anexos): ?>
                            <hr class="section-divider">
                            <div class="section-title">
                                <div class="icon-badge badge-green"><i class="fas fa-file-pdf"></i></div>
                                Documentos enviados
                            </div>
                            <?php foreach ($anexos as $anexo): ?>
                                <div class="doc-row">
                                    <i class="fas fa-file-pdf"></i>
                                    <div>
                                        <div class="name"><?= htmlspecialchars($anexo['nome_original']) ?></div>
                                        <div class="meta"><?= number_format($anexo['tamanho'] / 1024, 2) ?> KB</div>
                                    </div>
                                    <a class="view" href="<?= htmlspecialchars(urlPublicaUpload($anexo['caminho'])) ?>" target="_blank" rel="noopener">
                                        <i class="fas fa-eye"></i> Visualizar
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                    <?php endif; ?>

                <?php elseif ($mensagem === ''): ?>
                    <div style="text-align:center;color:#64748b;padding:30px 0;">
                        <i class="fas fa-link-slash" style="font-size:2.2rem;color:#cbd5e1;margin-bottom:12px;display:block;"></i>
                        <p>Link inválido.</p>
                    </div>
                <?php endif; ?>

            </div><!-- /.card-body -->
        </div><!-- /.card -->

        <div class="site-footer" style="margin-top:24px;">
            Secretaria Municipal de Meio Ambiente, Prefeitura de Pau dos Ferros / RN<br>
            Em caso de dúvidas, entre em contato: <strong style="color:rgba(255,255,255,0.6);">(84) 99668-6413</strong> &nbsp;|&nbsp; fiscalizacaosemapdf@gmail.com
        </div>

    </div><!-- /.wrap -->

    <script>
    function listarArquivos(input) {
        const alvo = document.getElementById('file-list');
        alvo.innerHTML = '';
        for (const f of input.files) {
            const linha = document.createElement('div');
            linha.innerHTML = '<i class="fas fa-file-pdf"></i> ' + f.name;
            alvo.appendChild(linha);
        }
    }
    </script>
</body>
</html>
