<?php
/**
 * Pré-visualização da entrega ao cidadão.
 *
 * Duas telas simuladas, para o operador ver exatamente o que o cidadão recebe:
 *   view=email    → caixa do Gmail com o e-mail real (template de envio) aberto.
 *   view=download → navegador com a página pública de acesso ao documento.
 *
 * Nada é gravado nem enviado. Os links são simulados: o botão de download do
 * e-mail leva à tela de download simulada, e lá o download apenas demonstra.
 */
require_once 'conexao.php';
require_once 'helpers.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/entrega_helpers.php';
require_once __DIR__ . '/../tipos_alvara.php';
verificaLogin();

$id   = (int) ($_POST['requerimento_id'] ?? $_GET['requerimento_id'] ?? 0);
$view = ($_GET['view'] ?? $_POST['view'] ?? 'email') === 'download' ? 'download' : 'email';
if (!$id) {
    http_response_code(400);
    exit('Processo não informado.');
}

$stmt = $pdo->prepare("
    SELECT r.protocolo, r.tipo_alvara, r.endereco_objetivo,
           re.nome AS requerente_nome, re.email AS requerente_email
    FROM requerimentos r
    JOIN requerentes re ON re.id = r.requerente_id
    WHERE r.id = ?
");
$stmt->execute([$id]);
$reqData = $stmt->fetch();

if (!$reqData) {
    http_response_code(404);
    exit('Processo não encontrado.');
}

// Documentos selecionados no modal; sem seleção, mostra os assinados do processo
// para a prévia não sair vazia. Traz o(s) assinante(s) para a tela de download.
$documentoIds = array_filter(array_map('intval', (array) ($_POST['documento_ids'] ?? [])));

$sqlDocs = "
    SELECT nome_arquivo,
           GROUP_CONCAT(DISTINCT assinante_nome ORDER BY timestamp_assinatura SEPARATOR ', ') AS assinantes,
           MAX(timestamp_assinatura) AS assinado_em
    FROM assinaturas_digitais
    WHERE requerimento_id = ?
";
$params = [$id];
if ($documentoIds) {
    $sqlDocs .= ' AND id IN (' . implode(',', array_fill(0, count($documentoIds), '?')) . ')';
    $params   = array_merge($params, $documentoIds);
}
$sqlDocs .= ' GROUP BY COALESCE(group_id, documento_id) ORDER BY assinado_em DESC';
$stmtDocs = $pdo->prepare($sqlDocs);
$stmtDocs->execute($params);

$documentos = [];
foreach ($stmtDocs->fetchAll() as $docRow) {
    $documentos[] = [
        'nome'       => $docRow['nome_arquivo'],
        'rotulo'     => rotuloDocumento($docRow['nome_arquivo']),
        'assinantes' => $docRow['assinantes'] ?? '',
        'assinado_em'=> $docRow['assinado_em'] ?? '',
    ];
}
if (!$documentos) {
    $documentos[] = ['nome' => 'documento_exemplo.pdf', 'rotulo' => 'Documento de exemplo', 'assinantes' => '', 'assinado_em' => ''];
}

// Dados comuns.
$protocolo         = $reqData['protocolo'];
$nome_destinatario = $reqData['requerente_nome'];
$email_destino     = $reqData['requerente_email'] ?: '(sem e-mail cadastrado)';
$tipoNome          = $tipos_alvara[$reqData['tipo_alvara']]['nome']
                        ?? ucwords(str_replace('_', ' ', $reqData['tipo_alvara']));
$instrucoes        = trim($_POST['instrucoes_doc_final'] ?? $_GET['instrucoes'] ?? '');
$validade_dias     = ENTREGA_LINK_VALIDADE_DIAS;
$assunto           = '[SEMA] ' . tituloAmigavel($tipoNome) . ' pronto — protocolo #' . $protocolo;
$urlDownloadSim    = 'preview_email_doc_final.php?view=download&requerimento_id=' . $id
                     . '&instrucoes=' . rawurlencode($instrucoes);
$urlDocPublica     = rtrim(BASE_URL, '/') . '/documento_final.php?token=' . substr(md5($protocolo), 0, 24);

// Cores e favicon compartilhados.
function previaHead(string $titulo): void { ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php }

/* ============================ TELA: DOWNLOAD ============================ */
if ($view === 'download'):
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?php previaHead('Simulação — acesso ao documento'); ?>
<style>
    * { box-sizing:border-box; margin:0; padding:0; }
    body { font-family:Roboto,'Segoe UI',Arial,sans-serif; background:#c9d2dc; min-height:100vh; }

    .aviso-previa {
        background:#0b3b8c; color:#fff; padding:11px 20px; font-size:.82rem;
        display:flex; align-items:center; gap:10px; flex-wrap:wrap;
    }
    .aviso-previa .tag {
        background:rgba(255,255,255,.16); border:1px solid rgba(255,255,255,.3);
        border-radius:999px; padding:2px 11px; font-size:.68rem; font-weight:800;
        letter-spacing:.08em; text-transform:uppercase;
    }
    .aviso-previa a { color:#cfe0ff; font-weight:600; text-decoration:none; margin-left:auto; }
    .aviso-previa a:hover { text-decoration:underline; }

    /* Janela de navegador simulada */
    .browser { max-width:900px; margin:26px auto; border-radius:12px; overflow:hidden;
               box-shadow:0 18px 50px rgba(10,20,40,.28); background:#fff; }
    .browser-bar { background:#e6e9ee; padding:10px 14px;
               display:flex; align-items:center; gap:12px; border-bottom:1px solid #d5dae2; }
    .dots { display:flex; gap:7px; }
    .dots i { width:12px; height:12px; border-radius:50%; display:inline-block; }
    .dots .r{background:#ff5f57;} .dots .y{background:#febc2e;} .dots .g{background:#28c840;}
    .omnibox { flex:1; background:#fff; border:1px solid #d5dae2; border-radius:999px;
               padding:6px 14px; font-size:.8rem; color:#3b4658; display:flex;
               align-items:center; gap:8px; min-width:0; }
    .omnibox i { color:#12894b; font-size:.72rem; }
    .omnibox span { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

    /* Reprodução FIEL da página pública documento_final.php (mesmos estilos) */
    .site { background-color:#013d86; background-image:url('../assets/img/background.jpg');
            background-size:contain; color:#1e293b; min-height:520px; padding-bottom:8px; }
    .site-header { background:#009640; height:48px; width:100%; padding:0 24px;
            display:flex; align-items:center; justify-content:space-between; }
    .site-header .brand { display:flex; align-items:center; gap:10px; text-decoration:none; }
    .site-header .brand img { height:32px; }
    .site-header .brand span { color:#fff; font-weight:700; font-size:14px; letter-spacing:.5px; }
    .page-wrapper { display:flex; justify-content:center; align-items:flex-start; padding:48px 16px 64px; }
    .card { background:#fff; border-radius:12px; box-shadow:0 8px 32px rgba(0,0,0,.18);
            width:100%; max-width:560px; overflow:hidden; }
    .card-header { background:linear-gradient(135deg,#065f46,#059669); padding:24px 28px 20px; color:#fff; }
    .card-header .icon-wrap { width:48px; height:48px; border-radius:12px; background:rgba(255,255,255,.2);
            display:flex; align-items:center; justify-content:center; margin-bottom:12px; }
    .card-header h1 { font-size:1.3rem; font-weight:700; margin-bottom:4px; }
    .card-header p { font-size:.875rem; opacity:.85; }
    .card-body { padding:24px 28px; }
    .info-row { display:flex; flex-direction:column; gap:2px; margin-bottom:16px; }
    .info-row label { font-size:.72rem; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:.06em; }
    .info-row span { font-size:.9rem; color:#1e293b; font-weight:500; }
    .divider { border:none; border-top:1px solid #f0f0f0; margin:20px 0; }
    .instrucoes-box { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px;
            padding:14px 16px; font-size:.875rem; color:#166534; margin-bottom:20px; white-space:pre-wrap; }
    .doc-item { margin-bottom:14px; }
    .btn-download { display:flex; align-items:center; justify-content:center; gap:10px; width:100%;
            padding:14px; background:#059669; color:#fff; border:none; border-radius:8px;
            font-size:1rem; font-weight:700; cursor:pointer; font-family:inherit; }
    .btn-download:hover { background:#047857; }
    .doc-meta { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between;
            gap:6px 14px; padding:7px 4px 0; font-size:.75rem; color:#6b7280; }
    .doc-meta i { margin-right:4px; }
    .doc-meta strong { color:#374151; font-weight:600; }
    .link-verificar { color:#059669; font-weight:700; text-decoration:none; white-space:nowrap; }
    .sim-toast { display:none; background:#0b3b8c; color:#fff; border-radius:8px; padding:10px 14px;
            font-size:.82rem; margin-top:14px; align-items:center; gap:9px; }
    .sim-toast.show { display:flex; }
    .footer-note { text-align:center; font-size:.75rem; color:rgba(255,255,255,.6); margin-top:20px; }
</style>
</head>
<body>
    <div class="aviso-previa">
        <span class="tag">Simulação</span>
        Esta é a tela que o cidadão vê ao clicar em <strong>&ldquo;Acessar e baixar documento&rdquo;</strong> no e-mail. Nada aqui é real.
        <a href="<?= htmlspecialchars($urlDownloadSim) ?>&_=1" onclick="history.length>1&&(history.back());return false;"><i class="fas fa-arrow-left"></i> Voltar ao e-mail</a>
    </div>

    <div class="browser">
        <div class="browser-bar">
            <span class="dots"><i class="r"></i><i class="y"></i><i class="g"></i></span>
            <span class="omnibox"><i class="fas fa-lock"></i><span><?= htmlspecialchars($urlDocPublica) ?></span></span>
        </div>

        <div class="site">
            <header class="site-header">
                <a href="#" class="brand" onclick="return false;">
                    <img src="../assets/img/logo_sema_branca.png" alt="SEMA" onerror="this.style.display='none'">
                    <span>SEMA — Secretaria de Meio Ambiente</span>
                </a>
            </header>
            <div class="page-wrapper">
                <div>
                <div class="card">
                    <div class="card-header">
                        <div class="icon-wrap"><i class="fas fa-file-circle-check fa-lg"></i></div>
                        <h1>Documento Final</h1>
                        <p><?= count($documentos) > 1 ? count($documentos) . ' documentos prontos para download' : 'Seu documento está pronto para download' ?></p>
                    </div>
                    <div class="card-body">
                        <div class="info-row"><label>Protocolo</label><span><?= htmlspecialchars($protocolo) ?></span></div>
                        <div class="info-row"><label>Requerente</label><span><?= htmlspecialchars($nome_destinatario) ?></span></div>
                        <div class="info-row"><label>Tipo de solicitação</label><span><?= htmlspecialchars($tipoNome) ?></span></div>
                        <?php if (!empty($reqData['endereco_objetivo'])): ?>
                            <div class="info-row"><label>Endereço</label><span><?= htmlspecialchars($reqData['endereco_objetivo']) ?></span></div>
                        <?php endif; ?>

                        <hr class="divider">

                        <?php if ($instrucoes !== ''): ?>
                            <div class="instrucoes-box"><strong>Observações da equipe técnica:</strong><br><?= htmlspecialchars($instrucoes) ?></div>
                        <?php endif; ?>

                        <?php foreach ($documentos as $doc): ?>
                            <div class="doc-item">
                                <button type="button" class="btn-download" onclick="mostrarSimToast()">
                                    <i class="fas fa-download"></i>
                                    <?= htmlspecialchars($doc['rotulo'] !== '' ? $doc['rotulo'] : $doc['nome']) ?>
                                </button>
                                <div class="doc-meta">
                                    <?php if (!empty($doc['assinantes'])): ?>
                                        <span>
                                            <i class="fas fa-file-signature"></i>
                                            Assinado por <strong><?= htmlspecialchars($doc['assinantes']) ?></strong>
                                            <?php if (!empty($doc['assinado_em'])): ?> em <?= date('d/m/Y', strtotime($doc['assinado_em'])) ?><?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                    <a href="#" onclick="return false;" class="link-verificar"><i class="fas fa-shield-halved"></i> Verificar autenticidade</a>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <div class="sim-toast" id="simToast">
                            <i class="fas fa-circle-info"></i>
                            <span>No e-mail real, este botão baixa o PDF assinado. Aqui é só demonstração.</span>
                        </div>

                        <p style="text-align:center;font-size:.75rem;color:#9ca3af;margin-top:12px;">
                            <i class="fas fa-lock"></i>
                            Enviado em <?= date('d/m/Y \à\s H:i') ?> &nbsp;·&nbsp; <?= count($documentos) ?> documento(s)
                            <br>Este link fica disponível até <?= date('d/m/Y', strtotime('+' . (int) $validade_dias . ' days')) ?> — baixe e guarde os arquivos.
                        </p>
                    </div>
                </div>
                <p class="footer-note">Secretaria Municipal de Meio Ambiente — Prefeitura de Pau dos Ferros/RN</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function mostrarSimToast() {
            var t = document.getElementById('simToast');
            t.classList.add('show');
            t.scrollIntoView({ behavior:'smooth', block:'center' });
        }
    </script>
</body>
</html>
<?php
    exit;
endif;

/* ============================ TELA: E-MAIL (Gmail) ============================ */
// O botão do e-mail aponta para a URL pública real (fidelidade visual); um script
// injetado no iframe intercepta o clique e leva para a tela de download simulada.
$url_portal  = $urlDocPublica;
$tipo_alvara = $tipoNome; // o template lê $tipo_alvara
ob_start();
include __DIR__ . '/../templates/email_documento_final.php';
$corpoEmail = ob_get_clean();

// Injeta no e-mail: base target=_top e interceptação de cliques em links, para o
// botão "Acessar e baixar documento" abrir a simulação de download na janela toda.
$injecao = '<base target="_top"><script>document.addEventListener("click",function(e){var a=e.target.closest("a");if(a){e.preventDefault();window.top.location.href='
    . json_encode($urlDownloadSim) . ';}},true);</script>';
$corpoEmail = preg_replace('/<head(\s[^>]*)?>/i', '$0' . $injecao, $corpoEmail, 1);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><?php previaHead('Prévia do e-mail — protocolo #' . $protocolo); ?>
<style>
    * { box-sizing:border-box; }
    body { margin:0; background:#f6f8fc; font-family:'Google Sans',Roboto,Arial,sans-serif; }

    .aviso-previa {
        background:#0b3b8c; color:#fff; padding:11px 20px; font-size:.82rem;
        display:flex; align-items:center; gap:10px; flex-wrap:wrap;
    }
    .aviso-previa .tag {
        background:rgba(255,255,255,.16); border:1px solid rgba(255,255,255,.3);
        border-radius:999px; padding:2px 11px; font-size:.68rem; font-weight:800;
        letter-spacing:.08em; text-transform:uppercase;
    }

    /* Barra do Gmail */
    .gm-top { background:#fff; border-bottom:1px solid #e4e7ec; height:56px; display:flex;
              align-items:center; gap:18px; padding:0 18px; }
    .gm-logo { display:flex; align-items:center; gap:9px; }
    .gm-logo svg { display:block; }
    .gm-logo b { color:#5f6368; font-size:1.35rem; font-weight:400; letter-spacing:.5px; }
    .gm-search { flex:1; max-width:720px; background:#eaf1fb; border-radius:8px; height:40px;
                 display:flex; align-items:center; gap:12px; padding:0 16px; color:#5f6368; font-size:.9rem; }
    .gm-avatar { width:32px; height:32px; border-radius:50%; background:#0b8043; color:#fff;
                 display:flex; align-items:center; justify-content:center; font-weight:700; margin-left:auto; }

    /* Corpo do Gmail */
    .gm-wrap { max-width:1080px; margin:0 auto; padding:14px 18px 40px; }
    .gm-toolbar { display:flex; align-items:center; gap:20px; color:#5f6368; font-size:1rem; padding:8px 4px 14px; }
    .gm-toolbar i { cursor:default; }
    .gm-mail { background:#fff; border-radius:16px; box-shadow:0 1px 3px rgba(60,64,67,.15); overflow:hidden; }
    .gm-subject { display:flex; align-items:center; gap:12px; padding:22px 28px 8px; }
    .gm-subject h1 { font-size:1.35rem; font-weight:400; color:#202124; flex:1; }
    .gm-label { background:#e8f0fe; color:#1967d2; font-size:.72rem; font-weight:600;
                border-radius:5px; padding:2px 8px; }
    .gm-from { display:flex; align-items:center; gap:12px; padding:6px 28px 16px; }
    .gm-from-av { width:40px; height:40px; border-radius:50%; background:#1a73e8; color:#fff;
                  display:flex; align-items:center; justify-content:center; font-weight:700; font-size:1.05rem; flex-shrink:0; }
    .gm-from-info { flex:1; min-width:0; }
    .gm-from-info .l1 { font-size:.9rem; color:#202124; }
    .gm-from-info .l1 b { font-weight:700; }
    .gm-from-info .l1 .addr { color:#5f6368; }
    .gm-from-info .l2 { font-size:.8rem; color:#5f6368; margin-top:1px; }
    .gm-from-meta { text-align:right; color:#5f6368; font-size:.78rem; white-space:nowrap; display:flex; align-items:center; gap:14px; }
    .gm-body-frame { width:100%; border:0; display:block; background:#fff; }
</style>
</head>
<body>
    <div class="aviso-previa">
        <span class="tag">Prévia</span>
        É <strong>exatamente este e-mail</strong> que <strong><?= htmlspecialchars($nome_destinatario) ?></strong> vai receber quando o documento for enviado. Nenhum e-mail foi disparado — isto é só uma simulação.
    </div>

    <!-- Barra do Gmail -->
    <div class="gm-top">
        <div class="gm-logo">
            <svg width="34" height="26" viewBox="0 0 34 26" aria-hidden="true">
                <path fill="#4285f4" d="M2 24h5V11L0 6v16a2 2 0 0 0 2 2z"/>
                <path fill="#34a853" d="M27 24h5a2 2 0 0 0 2-2V6l-7 5z"/>
                <path fill="#fbbc04" d="M27 4v7l7-5V4.5A2.5 2.5 0 0 0 30 3z"/>
                <path fill="#ea4335" d="M7 11V4l10 7.5L27 4v7l-10 7.5z"/>
                <path fill="#c5221f" d="M0 4.5V6l7 5V4L4 3A2.5 2.5 0 0 0 0 4.5z"/>
            </svg>
            <b>Gmail</b>
        </div>
        <div class="gm-search"><i class="fas fa-magnifying-glass"></i> Pesquisar e-mail</div>
        <div class="gm-avatar"><?= htmlspecialchars(mb_substr($nome_destinatario, 0, 1)) ?></div>
    </div>

    <div class="gm-wrap">
        <div class="gm-toolbar">
            <i class="fas fa-arrow-left"></i>
            <i class="fas fa-box-archive"></i>
            <i class="fas fa-triangle-exclamation"></i>
            <i class="fas fa-trash-can"></i>
            <i class="fas fa-envelope-open"></i>
        </div>

        <div class="gm-mail">
            <div class="gm-subject">
                <h1><?= htmlspecialchars($assunto) ?></h1>
                <span class="gm-label">Caixa de entrada</span>
                <i class="fa-regular fa-star" style="color:#5f6368;"></i>
            </div>
            <div class="gm-from">
                <div class="gm-from-av">P</div>
                <div class="gm-from-info">
                    <div class="l1"><b>Prefeitura de Pau dos Ferros</b> <span class="addr">&lt;naoresponder@protocolosead.com&gt;</span></div>
                    <div class="l2">para <?= htmlspecialchars($email_destino) ?></div>
                </div>
                <div class="gm-from-meta">
                    <span><?= date('H:i') ?> (há poucos segundos)</span>
                    <i class="fa-regular fa-star"></i>
                    <i class="fas fa-reply"></i>
                </div>
            </div>
            <iframe id="mailFrame" class="gm-body-frame" srcdoc="<?= htmlspecialchars($corpoEmail, ENT_QUOTES, 'UTF-8') ?>"></iframe>
        </div>
    </div>

    <script>
        // Ajusta a altura do iframe ao conteúdo do e-mail, para não ficar barra dupla.
        var f = document.getElementById('mailFrame');
        function ajustaAltura() {
            try {
                var h = f.contentWindow.document.body.scrollHeight;
                f.style.height = (h + 24) + 'px';
            } catch (e) { f.style.height = '900px'; }
        }
        f.addEventListener('load', ajustaAltura);
        window.addEventListener('resize', ajustaAltura);
        setTimeout(ajustaAltura, 400);
    </script>
</body>
</html>
