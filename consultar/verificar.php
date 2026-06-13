<?php
require_once '../includes/config.php';
require_once '../includes/assinatura_digital_service.php';
require_once '../admin/conexao.php';

$servico = new AssinaturaDigitalService($pdo);
$resultado = null;     // array de resultado de verificação
$erroEntrada = null;   // erro de validação do upload

// ── Entrada por UPLOAD (método principal) ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['arquivo'])) {
    $f = $_FILES['arquivo'];
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        $erroEntrada = 'Não foi possível receber o arquivo. Tente novamente.';
    } elseif ($f['size'] > 20 * 1024 * 1024) {
        $erroEntrada = 'O arquivo excede o limite de 20 MB.';
    } else {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $f['tmp_name']);
        finfo_close($finfo);
        $extOk = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) === 'pdf';
        if ($mime !== 'application/pdf' || !$extOk) {
            $erroEntrada = 'Envie um arquivo PDF válido.';
        } else {
            $resultado = $servico->verificarPorArquivo($f['tmp_name']);
        }
    }
}

// ── Entrada por CÓDIGO (secundária — também atende o QR via GET ?id=) ───
if ($resultado === null && $erroEntrada === null) {
    $documentoId = trim($_GET['id'] ?? $_POST['codigo'] ?? '');
    if ($documentoId !== '') {
        $resultado = $servico->verificarDocumento($documentoId);
        $resultado['estado'] = !empty($resultado['valido']) ? 'autentico'
            : (isset($resultado['dados']) ? 'alterado' : 'desconhecido');
        $servico->registrarVerificacao($documentoId, null, $resultado['estado'], 'codigo');
    }
}

function mascararCpfPublico(?string $cpf): string
{
    $dig = preg_replace('/\D/', '', (string) $cpf);
    if (strlen($dig) !== 11) return '';
    return '***.' . substr($dig, 3, 3) . '.' . substr($dig, 6, 3) . '-**';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificação de Documento - SEMA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: linear-gradient(160deg, #11271c 0%, #1c4b36 60%, #2a6b50 100%); min-height: 100vh; font-family: 'Segoe UI', system-ui, sans-serif; }
        .verificacao-container { max-width: 760px; margin: 40px auto; padding: 0 16px; }
        .card { border: none; border-radius: 18px; box-shadow: 0 16px 50px rgba(0,0,0,0.3); overflow: hidden; }
        .status-banner { padding: 28px 24px; text-align: center; color: #fff; }
        .status-banner.ok   { background: linear-gradient(135deg, #0d7f5f, #10b981); }
        .status-banner.err  { background: linear-gradient(135deg, #b91c1c, #ef4444); }
        .status-banner.warn { background: linear-gradient(135deg, #b45309, #f59e0b); }
        .status-banner .status-icon { font-size: 2.6rem; margin-bottom: 10px; }
        .status-banner h4 { font-weight: 700; margin: 0; }
        .status-banner p  { margin: 6px 0 0; opacity: .92; font-size: .9rem; }
        .assinante-item { display: flex; align-items: center; gap: 14px; padding: 14px 16px; border: 1px solid #e5e9f0; border-radius: 12px; margin-bottom: 10px; background: #fafcfb; }
        .assinante-item .av { width: 42px; height: 42px; border-radius: 50%; background: #1c4b36; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0; }
        .assinante-item .badge-nivel { font-size: .68rem; font-weight: 700; padding: 3px 9px; border-radius: 20px; }
        .badge-avancada { background: #d1fae5; color: #065f46; }
        .badge-simples  { background: #fef3c7; color: #92400e; }
        .hash-display { font-family: 'SFMono-Regular', Consolas, monospace; font-size: 0.72rem; word-break: break-all; background: #f1f5f9; border-radius: 8px; padding: 10px 12px; color: #475569; }
        .info-label { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #64748b; margin-bottom: 3px; }
        .btn-sema { background: #1c4b36; border-color: #1c4b36; color: #fff; }
        .btn-sema:hover { background: #2a6b50; border-color: #2a6b50; color: #fff; }
        /* Dropzone */
        .dropzone {
            border: 2.5px dashed #cbd5e1; border-radius: 16px;
            padding: 40px 24px; text-align: center; cursor: pointer;
            transition: border-color .15s, background .15s;
            background: #f8fafc;
        }
        .dropzone:hover, .dropzone.drag { border-color: #1c4b36; background: #f0fdf4; }
        .dropzone .dz-icon { font-size: 2.6rem; color: #1c4b36; margin-bottom: 10px; }
        .dropzone .dz-title { font-weight: 700; color: #1e293b; font-size: 1rem; }
        .dropzone .dz-sub   { color: #64748b; font-size: .82rem; margin-top: 4px; }
        .sep-ou { display: flex; align-items: center; gap: 12px; color: #94a3b8; font-size: .8rem; margin: 22px 0 16px; }
        .sep-ou::before, .sep-ou::after { content: ''; flex: 1; height: 1px; background: #e2e8f0; }
    </style>
</head>
<body>
    <div class="verificacao-container">
        <div class="card">
            <div class="card-body p-0">
                <div class="text-center pt-4 pb-3 px-4">
                    <img src="../assets/SEMA/PNG/Azul/Logo SEMA Horizontal.png" alt="SEMA" style="max-width: 220px;">
                    <h5 class="mt-3 mb-1 fw-bold">Verificação de Autenticidade</h5>
                    <p class="text-muted small mb-0">Assinatura Eletrônica — Lei nº 14.063/2020</p>
                </div>

                <?php if ($resultado === null): ?>
                    <!-- ENTRADA: upload em destaque + código como alternativa -->
                    <div class="px-4 pb-4">
                        <?php if ($erroEntrada): ?>
                            <div class="alert alert-danger py-2" style="font-size:.85rem;">
                                <i class="fas fa-circle-exclamation me-1"></i><?php echo htmlspecialchars($erroEntrada); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data" id="formUpload">
                            <label class="dropzone" id="dropzone" for="inputArquivo">
                                <div class="dz-icon"><i class="fas fa-file-arrow-up"></i></div>
                                <div class="dz-title">Arraste o PDF aqui ou clique para selecionar</div>
                                <div class="dz-sub">O sistema confere se o documento é autêntico e se não foi alterado</div>
                                <input type="file" name="arquivo" id="inputArquivo" accept="application/pdf,.pdf" hidden>
                            </label>
                            <div id="dzFile" class="text-center mt-2" style="display:none;font-size:.85rem;color:#1c4b36;">
                                <i class="fas fa-file-pdf me-1"></i><span id="dzFileName"></span>
                            </div>
                            <button type="submit" class="btn btn-sema btn-lg w-100 mt-3" id="btnVerificar" disabled>
                                <i class="fas fa-shield-halved me-2"></i>Verificar documento
                            </button>
                        </form>

                        <div class="sep-ou">ou verifique pelo código</div>

                        <form method="GET" class="d-flex gap-2">
                            <input type="text" name="id" class="form-control"
                                   placeholder="Código impresso no rodapé / QR do documento">
                            <button type="submit" class="btn btn-outline-secondary px-3">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>

                        <p class="text-muted small mt-3 mb-0">
                            <i class="fas fa-circle-info me-1"></i>
                            Verifica apenas documentos emitidos pelo SEMA. Para máxima confiabilidade,
                            envie o arquivo original recebido (sem reabrir/re-salvar).
                        </p>
                    </div>

                <?php elseif (($resultado['estado'] ?? '') === 'autentico'): ?>
                    <div class="status-banner ok">
                        <div class="status-icon"><i class="fas fa-circle-check"></i></div>
                        <h4>Documento Autêntico</h4>
                        <p>As assinaturas eletrônicas e a integridade do arquivo foram verificadas com sucesso.</p>
                    </div>
                    <div class="p-4">
                        <div class="info-label"><i class="fas fa-users me-1"></i> Assinado eletronicamente por</div>
                        <div class="mt-2 mb-4">
                            <?php foreach (($resultado['assinantes'] ?? []) as $a):
                                $iniciais = strtoupper(mb_substr(trim($a['nome']), 0, 1));
                                $cpfM = mascararCpfPublico($a['cpf']);
                            ?>
                            <div class="assinante-item">
                                <div class="av"><?php echo htmlspecialchars($iniciais); ?></div>
                                <div class="flex-grow-1">
                                    <div class="fw-bold" style="font-size:.92rem;"><?php echo htmlspecialchars($a['nome']); ?></div>
                                    <div class="text-muted" style="font-size:.78rem;">
                                        <?php echo htmlspecialchars($a['cargo'] ?? ''); ?>
                                        <?php if ($cpfM): ?> &middot; CPF <?php echo $cpfM; ?><?php endif; ?>
                                        &middot; <?php echo date('d/m/Y H:i', strtotime($a['data'])); ?>
                                    </div>
                                </div>
                                <?php if (($a['nivel'] ?? '') === 'avancada'): ?>
                                    <span class="badge-nivel badge-avancada"><i class="fas fa-shield-halved me-1"></i>Avançada</span>
                                <?php else: ?>
                                    <span class="badge-nivel badge-simples"><i class="fas fa-file-circle-check me-1"></i>Registro eletrônico</span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="info-label"><i class="fas fa-file-lines me-1"></i> Tipo de documento</div>
                                <div style="font-size:.9rem;"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $resultado['dados']['tipo_documento']))); ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label"><i class="fas fa-hashtag me-1"></i> Processo</div>
                                <div style="font-size:.9rem;">#<?php echo (int) $resultado['dados']['requerimento_id']; ?></div>
                            </div>
                        </div>

                        <div class="info-label"><i class="fas fa-fingerprint me-1"></i> Impressão digital do arquivo (SHA-256)</div>
                        <div class="hash-display mb-4"><?php echo htmlspecialchars($resultado['dados']['hash_documento']); ?></div>

                        <div class="d-grid">
                            <a href="baixar.php?id=<?php echo urlencode($resultado['dados']['documento_id']); ?>"
                               target="_blank" class="btn btn-sema btn-lg">
                                <i class="fas fa-file-pdf me-2"></i>Baixar versão oficial
                            </a>
                        </div>
                    </div>

                <?php elseif (($resultado['estado'] ?? '') === 'alterado'): ?>
                    <div class="status-banner warn">
                        <div class="status-icon"><i class="fas fa-triangle-exclamation"></i></div>
                        <h4>Arquivo Alterado</h4>
                        <p><?php echo htmlspecialchars($resultado['erro'] ?? 'O arquivo não corresponde à versão oficial.'); ?></p>
                    </div>
                    <div class="p-4">
                        <?php if (isset($resultado['dados'])): ?>
                            <div class="info-label mb-2"><i class="fas fa-file-shield me-1"></i> Registro oficial deste documento</div>
                            <div class="assinante-item">
                                <div class="av"><?php echo strtoupper(htmlspecialchars(mb_substr($resultado['dados']['assinante_nome'], 0, 1))); ?></div>
                                <div class="flex-grow-1">
                                    <div class="fw-bold" style="font-size:.92rem;"><?php echo htmlspecialchars($resultado['dados']['assinante_nome']); ?></div>
                                    <div class="text-muted" style="font-size:.78rem;">
                                        <?php echo htmlspecialchars($resultado['dados']['assinante_cargo'] ?? ''); ?>
                                        &middot; <?php echo date('d/m/Y H:i', strtotime($resultado['dados']['timestamp_assinatura'])); ?>
                                    </div>
                                </div>
                            </div>
                            <p class="text-muted small mt-3 mb-3">
                                Compare com a cópia oficial abaixo. Se você não reconhece esta alteração,
                                o arquivo que você recebeu pode ter sido adulterado.
                            </p>
                            <div class="d-grid">
                                <a href="baixar.php?id=<?php echo urlencode($resultado['dados']['documento_id']); ?>"
                                   target="_blank" class="btn btn-sema">
                                    <i class="fas fa-file-pdf me-2"></i>Baixar versão oficial para comparar
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                <?php else: /* desconhecido */ ?>
                    <div class="status-banner err">
                        <div class="status-icon"><i class="fas fa-circle-xmark"></i></div>
                        <h4>Documento Não Reconhecido</h4>
                        <p><?php echo htmlspecialchars($resultado['erro'] ?? 'Este arquivo não foi emitido pelo SEMA.'); ?></p>
                    </div>
                    <div class="p-4">
                        <p class="text-muted small mb-0">
                            O arquivo não confere com nenhum documento emitido pelo SEMA. Isso pode acontecer se:
                            o documento não foi emitido por esta secretaria; o arquivo foi reaberto e salvo em
                            outro programa (o que altera os dados internos); ou trata-se de um documento falso.
                            Em caso de dúvida, contate a Secretaria Municipal de Meio Ambiente.
                        </p>
                    </div>
                <?php endif; ?>

                <?php if ($resultado !== null): ?>
                    <div class="text-center pb-4">
                        <a href="verificar.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-rotate-left me-2"></i>Verificar outro documento
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="text-center text-white mt-4 opacity-75">
            <small>
                <i class="fas fa-shield-halved me-2"></i>
                Assinatura individual RSA-2048 &middot; integridade SHA-256<br>
                Prefeitura Municipal de Pau dos Ferros/RN — SEMA
            </small>
        </div>
    </div>

    <script>
    (function () {
        const dz = document.getElementById('dropzone');
        const input = document.getElementById('inputArquivo');
        const btn = document.getElementById('btnVerificar');
        const fileBox = document.getElementById('dzFile');
        const fileName = document.getElementById('dzFileName');
        if (!dz) return;

        function mostrarArquivo() {
            if (input.files.length) {
                fileName.textContent = input.files[0].name;
                fileBox.style.display = 'block';
                btn.disabled = false;
            }
        }
        input.addEventListener('change', mostrarArquivo);

        ['dragenter', 'dragover'].forEach(ev =>
            dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.add('drag'); }));
        ['dragleave', 'drop'].forEach(ev =>
            dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.remove('drag'); }));
        dz.addEventListener('drop', e => {
            if (e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                mostrarArquivo();
            }
        });
    })();
    </script>
</body>
</html>
