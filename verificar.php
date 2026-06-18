<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/assinatura_digital_service.php';
require_once __DIR__ . '/admin/conexao.php';

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
    <title>Verificação de Documento — SEMA Pau dos Ferros</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --azul:#013d86; --azul-d:#012a5e; --verde:#1c4b36; --verde-2:#0d7f5f; }
        * { box-sizing: border-box; }
        body {
            background-color: var(--azul);
            background-image: url(<?php echo rtrim(BASE_URL, '/'); ?>/assets/img/background.jpg);
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            min-height: 100vh; margin: 0;
            font-family: 'Segoe UI', Roboto, system-ui, sans-serif;
            display: flex; flex-direction: column; align-items: center;
            padding: 40px 16px;
        }
        .wrap { width: 100%; max-width: 720px; }
        .card-v {
            background: #fff; border-radius: 22px; overflow: hidden;
            box-shadow: 0 24px 70px rgba(1,21,52,0.45);
            animation: cardIn .5s cubic-bezier(.2,.7,.3,1) both;
        }
        @keyframes cardIn { from { opacity:0; transform: translateY(18px) scale(.98);} to { opacity:1; transform:none;} }

        /* Cabeçalho institucional */
        .v-head { text-align: center; padding: 30px 28px 22px; border-bottom: 1px solid #eef1f5; }
        .v-head img { max-width: 210px; }
        .v-head h1 { font-size: 1.18rem; font-weight: 800; color: #0f172a; margin: 16px 0 3px; letter-spacing: -.01em; }
        .v-head .sub { font-size: .82rem; color: #64748b; }
        .v-body { padding: 28px; }

        /* ── Dropzone (entrada) ── */
        .dropzone {
            border: 2.5px dashed #c7d2dd; border-radius: 18px;
            padding: 48px 24px; text-align: center; cursor: pointer;
            transition: all .18s ease; background: #f7f9fc;
        }
        .dropzone:hover, .dropzone.drag { border-color: var(--verde); background: #f0faf4; transform: translateY(-2px); }
        .dz-icon { font-size: 3rem; color: var(--verde); margin-bottom: 14px; }
        .dz-title { font-weight: 800; color: #1e293b; font-size: 1.08rem; }
        .dz-sub { color: #64748b; font-size: .85rem; margin-top: 6px; }
        .sep-ou { display:flex; align-items:center; gap:12px; color:#94a3b8; font-size:.8rem; margin:24px 0 18px; }
        .sep-ou::before, .sep-ou::after { content:''; flex:1; height:1px; background:#e2e8f0; }
        .btn-azul { background: var(--azul); border-color: var(--azul); color:#fff; font-weight:600; }
        .btn-azul:hover { background: var(--azul-d); border-color: var(--azul-d); color:#fff; }
        .btn-verde { background: var(--verde); border-color: var(--verde); color:#fff; font-weight:700; }
        .btn-verde:hover { background: #143a2a; color:#fff; }

        /* ── Resultado (centralizado) ── */
        .banner { padding: 34px 24px 26px; text-align: center; color:#fff; }
        .banner.ok   { background: linear-gradient(135deg,#0d7f5f,#10b981); }
        .banner.warn { background: linear-gradient(135deg,#b45309,#f59e0b); }
        .banner.err  { background: linear-gradient(135deg,#b91c1c,#ef4444); }
        .banner h2 { font-weight: 800; font-size: 1.4rem; margin: 14px 0 6px; }
        .banner p { margin: 0 auto; opacity:.95; font-size:.92rem; max-width: 440px; }
        .res-body { padding: 28px; text-align: center; }
        .res-section-label { font-size:.72rem; font-weight:800; text-transform:uppercase; letter-spacing:.05em; color:#94a3b8; margin-bottom:12px; }
        .assinantes-wrap { max-width: 460px; margin: 0 auto 26px; }
        .assinante-item { display:flex; align-items:center; gap:14px; padding:14px 16px; border:1px solid #e8edf3; border-radius:14px; margin-bottom:10px; background:#fafcff; text-align:left; }
        .assinante-item .av { width:44px; height:44px; border-radius:50%; background:var(--verde); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; flex-shrink:0; }
        .badge-nivel { font-size:.66rem; font-weight:800; padding:4px 10px; border-radius:20px; }
        .badge-avancada { background:#d1fae5; color:#065f46; }
        .badge-simples { background:#fef3c7; color:#92400e; }
        .meta-grid { display:flex; gap:14px; max-width:460px; margin:0 auto 22px; }
        .meta-grid > div { flex:1; background:#f8fafc; border-radius:12px; padding:12px; }
        .meta-grid .info-label { font-size:.68rem; font-weight:800; text-transform:uppercase; letter-spacing:.04em; color:#94a3b8; }
        .meta-grid .info-val { font-size:.9rem; color:#1e293b; font-weight:600; margin-top:2px; }
        .hash-display { font-family:'SFMono-Regular',Consolas,monospace; font-size:.7rem; word-break:break-all; background:#f1f5f9; border-radius:10px; padding:11px 13px; color:#475569; max-width:460px; margin:0 auto 22px; }
        .res-actions { max-width: 460px; margin: 0 auto; }

        /* ── Animações de ícone do resultado ── */
        .res-icon { width:74px; height:74px; margin:0 auto; }
        .res-icon circle, .res-icon path, .res-icon line { stroke:#fff; stroke-width:5; fill:none; stroke-linecap:round; stroke-linejoin:round; }
        .res-icon .ring { stroke-dasharray:295; stroke-dashoffset:295; animation: draw .6s ease forwards; }
        .res-icon .mark { stroke-dasharray:120; stroke-dashoffset:120; animation: draw .45s .45s ease forwards; }
        @keyframes draw { to { stroke-dashoffset:0; } }

        /* ── Overlay "verificando" ── */
        #overlay {
            position: fixed; inset:0; z-index: 9999;
            background: rgba(1,21,52,.78); backdrop-filter: blur(4px);
            display:none; align-items:center; justify-content:center; flex-direction:column;
        }
        #overlay.show { display:flex; animation: fadeIn .25s ease; }
        @keyframes fadeIn { from{opacity:0;} to{opacity:1;} }
        .scan-doc { position:relative; width:96px; height:120px; background:#fff; border-radius:8px; box-shadow:0 12px 40px rgba(0,0,0,.4); overflow:hidden; }
        .scan-doc .lines { position:absolute; inset:16px 14px; display:flex; flex-direction:column; gap:7px; }
        .scan-doc .lines span { height:6px; border-radius:3px; background:#dbe3ee; }
        .scan-doc .lines span:nth-child(4){ width:60%; }
        .scan-doc .scanbar { position:absolute; left:0; right:0; height:14px; background:linear-gradient(rgba(13,127,95,.0),rgba(13,127,95,.55),rgba(13,127,95,.0)); box-shadow:0 0 14px 2px rgba(16,185,129,.6); animation: scan 1.5s ease-in-out infinite; }
        @keyframes scan { 0%{ top:-14px;} 50%{ top:106px;} 100%{ top:-14px;} }
        #overlay .ov-text { color:#fff; font-weight:700; margin-top:24px; font-size:1.05rem; letter-spacing:.01em; }
        #overlay .ov-sub { color:#bcd2ee; font-size:.85rem; margin-top:4px; }

        .foot { text-align:center; color:#cfe0f5; font-size:.8rem; margin-top:22px; opacity:.9; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card-v">
            <div class="v-head">
                <img src="<?php echo rtrim(BASE_URL, '/'); ?>/assets/SEMA/PNG/Azul/Logo SEMA Horizontal.png" alt="SEMA">
                <h1>Verificação de Autenticidade</h1>
                <div class="sub">Assinatura Eletrônica — Lei nº 14.063/2020</div>
            </div>

            <?php if ($resultado === null): ?>
                <!-- ENTRADA: upload em destaque -->
                <div class="v-body">
                    <?php if ($erroEntrada): ?>
                        <div class="alert alert-danger py-2" style="font-size:.85rem;">
                            <i class="fas fa-circle-exclamation me-1"></i><?php echo htmlspecialchars($erroEntrada); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" id="formUpload">
                        <label class="dropzone" id="dropzone" for="inputArquivo">
                            <div class="dz-icon"><i class="fas fa-file-arrow-up"></i></div>
                            <div class="dz-title">Arraste o documento PDF aqui</div>
                            <div class="dz-sub">ou clique para selecionar — a verificação começa automaticamente</div>
                            <input type="file" name="arquivo" id="inputArquivo" accept="application/pdf,.pdf" hidden>
                        </label>
                    </form>

                    <div class="sep-ou">ou informe o código do documento</div>

                    <form method="GET" class="d-flex gap-2">
                        <input type="text" name="id" class="form-control"
                               placeholder="Código impresso no rodapé do documento">
                        <button type="submit" class="btn btn-azul px-3"><i class="fas fa-search"></i></button>
                    </form>

                    <p class="text-muted small mt-3 mb-0 text-center">
                        <i class="fas fa-circle-info me-1"></i>
                        Verifica documentos emitidos pela Secretaria Municipal de Meio Ambiente.
                        Para máxima confiabilidade, envie o arquivo original (sem reabrir/re-salvar).
                    </p>
                </div>

            <?php elseif (($resultado['estado'] ?? '') === 'autentico'): ?>
                <div class="banner ok">
                    <svg class="res-icon" viewBox="0 0 100 100">
                        <circle class="ring" cx="50" cy="50" r="47"/>
                        <path class="mark" d="M28 52 L44 67 L73 35"/>
                    </svg>
                    <h2>Documento Autêntico</h2>
                    <p>As assinaturas eletrônicas e a integridade do arquivo foram verificadas com sucesso.</p>
                </div>
                <div class="res-body">
                    <div class="res-section-label"><i class="fas fa-users me-1"></i> Assinado eletronicamente por</div>
                    <div class="assinantes-wrap">
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
                                <span class="badge-nivel badge-simples"><i class="fas fa-file-circle-check me-1"></i>Registro</span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="meta-grid">
                        <div>
                            <div class="info-label">Tipo de documento</div>
                            <div class="info-val"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $resultado['dados']['tipo_documento']))); ?></div>
                        </div>
                        <div>
                            <div class="info-label">Processo</div>
                            <div class="info-val">#<?php echo (int) $resultado['dados']['requerimento_id']; ?></div>
                        </div>
                    </div>

                    <div class="res-section-label"><i class="fas fa-fingerprint me-1"></i> Impressão digital (SHA-256)</div>
                    <div class="hash-display"><?php echo htmlspecialchars($resultado['dados']['hash_documento']); ?></div>

                    <div class="res-actions d-grid">
                        <a href="<?php echo rtrim(BASE_URL, '/'); ?>/consultar/baixar.php?id=<?php echo urlencode($resultado['dados']['documento_id']); ?>"
                           target="_blank" class="btn btn-verde btn-lg">
                            <i class="fas fa-file-pdf me-2"></i>Baixar versão oficial
                        </a>
                    </div>
                </div>

            <?php elseif (($resultado['estado'] ?? '') === 'alterado'): ?>
                <div class="banner warn">
                    <svg class="res-icon" viewBox="0 0 100 100">
                        <path class="ring" d="M50 12 L92 84 L8 84 Z"/>
                        <line class="mark" x1="50" y1="40" x2="50" y2="62"/>
                        <line class="mark" x1="50" y1="72" x2="50" y2="73"/>
                    </svg>
                    <h2>Arquivo Alterado</h2>
                    <p><?php echo htmlspecialchars($resultado['erro'] ?? 'O arquivo não corresponde à versão oficial.'); ?></p>
                </div>
                <div class="res-body">
                    <?php if (isset($resultado['dados'])): ?>
                        <div class="res-section-label"><i class="fas fa-file-shield me-1"></i> Registro oficial deste documento</div>
                        <div class="assinantes-wrap">
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
                        </div>
                        <p class="text-muted small mb-3" style="max-width:440px;margin-left:auto;margin-right:auto;">
                            Compare com a cópia oficial. Se você não reconhece esta alteração,
                            o arquivo recebido pode ter sido adulterado.
                        </p>
                        <div class="res-actions d-grid">
                            <a href="<?php echo rtrim(BASE_URL, '/'); ?>/consultar/baixar.php?id=<?php echo urlencode($resultado['dados']['documento_id']); ?>"
                               target="_blank" class="btn btn-verde">
                                <i class="fas fa-file-pdf me-2"></i>Baixar versão oficial para comparar
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

            <?php else: /* desconhecido */ ?>
                <div class="banner err">
                    <svg class="res-icon" viewBox="0 0 100 100">
                        <circle class="ring" cx="50" cy="50" r="47"/>
                        <line class="mark" x1="35" y1="35" x2="65" y2="65"/>
                        <line class="mark" x1="65" y1="35" x2="35" y2="65"/>
                    </svg>
                    <h2>Documento Não Reconhecido</h2>
                    <p><?php echo htmlspecialchars($resultado['erro'] ?? 'Este arquivo não foi emitido pelo SEMA.'); ?></p>
                </div>
                <div class="res-body">
                    <p class="text-muted small mb-0" style="max-width:480px;margin:0 auto;">
                        O arquivo não confere com nenhum documento emitido pelo SEMA. Isso pode acontecer se:
                        o documento não foi emitido por esta secretaria; o arquivo foi reaberto e salvo em
                        outro programa (o que altera os dados internos); ou trata-se de um documento falso.
                        Em caso de dúvida, contate a Secretaria Municipal de Meio Ambiente.
                    </p>
                </div>
            <?php endif; ?>

            <?php if ($resultado !== null): ?>
                <div class="text-center pb-4">
                    <a href="<?php echo rtrim(BASE_URL, '/'); ?>/verificar" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-rotate-left me-2"></i>Verificar outro documento
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <div class="foot">
            <i class="fas fa-shield-halved me-1"></i>
            Assinatura RSA-2048 &middot; integridade SHA-256<br>
            Prefeitura Municipal de Pau dos Ferros/RN — SEMA
        </div>
    </div>

    <!-- Overlay de verificação -->
    <div id="overlay">
        <div class="scan-doc">
            <div class="lines"><span></span><span></span><span></span><span></span></div>
            <div class="scanbar"></div>
        </div>
        <div class="ov-text">Verificando autenticidade…</div>
        <div class="ov-sub">Conferindo assinaturas e integridade do arquivo</div>
    </div>

    <script>
    (function () {
        const dz = document.getElementById('dropzone');
        const input = document.getElementById('inputArquivo');
        const form = document.getElementById('formUpload');
        const overlay = document.getElementById('overlay');
        if (!dz) return;

        function verificar() {
            if (!input.files.length) return;
            overlay.classList.add('show');
            form.submit();
        }
        input.addEventListener('change', verificar);

        ['dragenter','dragover'].forEach(ev =>
            dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.add('drag'); }));
        ['dragleave','drop'].forEach(ev =>
            dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.remove('drag'); }));
        dz.addEventListener('drop', e => {
            if (e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                verificar();
            }
        });
    })();
    </script>
</body>
</html>
