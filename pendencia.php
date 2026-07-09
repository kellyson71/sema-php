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
    <title>Complementação de Requerimento &mdash; SEMA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Segoe UI', Tahoma, sans-serif; background: linear-gradient(135deg, #065f46, #047857); min-height: 100vh; padding: 32px 16px; }
        .wrap { max-width: 720px; margin: 0 auto; }
        .card { background: #fff; border-radius: 14px; box-shadow: 0 18px 40px rgba(0,0,0,.18); overflow: hidden; }
        .card-head { background: #064e3b; color: #fff; padding: 22px 28px; }
        .card-head h1 { margin: 0 0 4px; font-size: 1.25rem; }
        .card-head span { font-size: .85rem; opacity: .8; }
        .card-body { padding: 28px; }
        .alert { display: flex; gap: 10px; align-items: center; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: .92rem; }
        .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-danger { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-info { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
        .meta { display: grid; grid-template-columns: auto 1fr; gap: 6px 14px; font-size: .9rem; color: #475569; margin-bottom: 22px; }
        .meta b { color: #0f172a; font-weight: 600; }
        .pendencia { background: #fffbeb; border: 1px solid #fde68a; border-left: 4px solid #f59e0b; border-radius: 8px; padding: 16px 18px; margin-bottom: 24px; }
        .pendencia h2 { margin: 0 0 8px; font-size: 1.02rem; color: #92400e; }
        .pendencia p { margin: 0; color: #78350f; font-size: .93rem; line-height: 1.55; }
        label { display: block; font-weight: 600; color: #0f172a; margin-bottom: 6px; font-size: .93rem; }
        textarea { width: 100%; min-height: 130px; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font: inherit; resize: vertical; }
        .upload-area { border: 2px dashed #94a3b8; border-radius: 10px; padding: 24px; text-align: center; cursor: pointer; color: #475569; margin-top: 6px; }
        .upload-area:hover { border-color: #047857; background: #f0fdf4; }
        .upload-area input { display: none; }
        .upload-area i { font-size: 1.8rem; color: #047857; }
        .file-list { margin-top: 10px; font-size: .87rem; color: #334155; text-align: left; }
        .file-list div { padding: 2px 0; }
        .hint { font-size: .82rem; color: #64748b; margin-top: 6px; }
        .btn { display: inline-flex; align-items: center; gap: 8px; background: #047857; color: #fff; border: 0; border-radius: 8px; padding: 12px 22px; font-size: .95rem; font-weight: 600; cursor: pointer; margin-top: 22px; }
        .btn:hover { background: #065f46; }
        .anexos { margin-top: 18px; }
        .anexos a { display: block; color: #047857; text-decoration: none; padding: 3px 0; font-size: .9rem; }
        .foot { text-align: center; color: rgba(255,255,255,.75); font-size: .82rem; margin-top: 22px; line-height: 1.6; }
        .empty { text-align: center; color: #64748b; padding: 30px 0; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="card-head">
            <h1><i class="fas fa-folder-open"></i> Complementação de Requerimento</h1>
            <span>Secretaria Municipal de Meio Ambiente &mdash; Pau dos Ferros / RN</span>
        </div>
        <div class="card-body">

            <?php if ($mensagem !== ''): ?>
                <div class="alert alert-<?= htmlspecialchars($mensagemTipo) ?>">
                    <i class="fas fa-<?= $mensagemTipo === 'success' ? 'check-circle' : ($mensagemTipo === 'danger' ? 'exclamation-circle' : 'info-circle') ?>"></i>
                    <span><?= htmlspecialchars($mensagem) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($requerimento && $pendencia): ?>

                <div class="meta">
                    <b>Protocolo</b><span><?= htmlspecialchars($requerimento['protocolo']) ?></span>
                    <b>Requerente</b><span><?= htmlspecialchars($requerimento['requerente_nome']) ?></span>
                    <b>Tipo</b><span><?= htmlspecialchars($tipoAlvaraNome) ?></span>
                </div>

                <div class="pendencia">
                    <h2><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($pendencia['titulo']) ?></h2>
                    <p><?= nl2br(htmlspecialchars($pendencia['descricao'])) ?></p>
                </div>

                <?php if ($pendencia['status'] === 'aberta'): ?>

                    <form method="post" enctype="multipart/form-data">
                        <label for="resposta">Sua resposta</label>
                        <textarea id="resposta" name="resposta" placeholder="Descreva a informação solicitada..."><?= htmlspecialchars($_POST['resposta'] ?? '') ?></textarea>

                        <label style="margin-top:18px;">Documentos complementares (opcional)</label>
                        <div class="upload-area" onclick="document.getElementById('anexos').click()">
                            <input type="file" id="anexos" name="anexos[]" accept="application/pdf,.pdf" multiple
                                   onchange="listar(this)">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <div>Clique para selecionar um ou mais arquivos</div>
                            <div class="hint">Apenas PDF &mdash; máximo 10 MB por arquivo</div>
                            <div class="file-list" id="file-list"></div>
                        </div>

                        <button type="submit" class="btn"><i class="fas fa-paper-plane"></i> Enviar complementação</button>
                    </form>

                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-check-circle"></i>
                        <span>Esta solicitação já foi respondida em <?= htmlspecialchars(date('d/m/Y H:i', strtotime($pendencia['respondido_em'] ?? $pendencia['criado_em']))) ?>. Não é necessário reenviar.</span>
                    </div>

                    <?php if (!empty($pendencia['resposta'])): ?>
                        <label>Resposta enviada</label>
                        <p style="color:#475569;font-size:.93rem;"><?= nl2br(htmlspecialchars($pendencia['resposta'])) ?></p>
                    <?php endif; ?>

                    <?php if ($anexos): ?>
                        <div class="anexos">
                            <label>Documentos enviados</label>
                            <?php foreach ($anexos as $anexo): ?>
                                <a href="<?= htmlspecialchars(urlPublicaUpload($anexo['caminho'])) ?>" target="_blank" rel="noopener">
                                    <i class="fas fa-file-pdf"></i> <?= htmlspecialchars($anexo['nome_original']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

            <?php elseif ($mensagem === ''): ?>
                <div class="empty">
                    <i class="fas fa-link-slash fa-2x"></i>
                    <p>Link inválido.</p>
                </div>
            <?php endif; ?>

        </div>
    </div>
    <div class="foot">
        Em caso de dúvidas: <strong>(84) 99668-6413</strong> &nbsp;|&nbsp; fiscalizacaosemapdf@gmail.com
    </div>
</div>
<script>
function listar(input) {
    const alvo = document.getElementById('file-list');
    alvo.innerHTML = '';
    for (const f of input.files) {
        const linha = document.createElement('div');
        linha.textContent = '📄 ' + f.name;
        alvo.appendChild(linha);
    }
}
</script>
</body>
</html>
