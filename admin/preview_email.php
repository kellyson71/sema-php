<?php
/**
 * Pré-visualização de um e-mail já enviado (ou tentado) pelo sistema, a partir
 * do registro salvo em `email_logs` — a coluna `mensagem` guarda o HTML completo
 * do corpo, então não precisamos reconstruir nenhum template: é o e-mail real.
 *
 * Diferente de preview_email_doc_final.php (que simula um envio ainda não feito),
 * esta página só lê um envio que já aconteceu — os links do corpo funcionam normalmente.
 */
require_once 'conexao.php';
require_once __DIR__ . '/../includes/functions.php';
verificaLogin();

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(400);
    exit('E-mail não informado.');
}

$stmt = $pdo->prepare("
    SELECT id, email_destino, assunto, mensagem, status, erro, data_envio, usuario_envio
    FROM email_logs
    WHERE id = ?
");
$stmt->execute([$id]);
$log = $stmt->fetch();

if (!$log) {
    http_response_code(404);
    exit('Registro de e-mail não encontrado.');
}

$sucesso    = $log['status'] === 'SUCESSO';
$remetente  = defined('EMAIL_FROM') ? EMAIL_FROM : 'naoresponder@protocolosead.com';
$corpoEmail = $log['mensagem'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prévia do e-mail — <?= htmlspecialchars($log['assunto']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing:border-box; }
        body { margin:0; background:#f6f8fc; font-family:'Google Sans',Roboto,Arial,sans-serif; }

        .aviso-previa {
            background:#0b3b8c; color:#fff; padding:11px 20px; font-size:.82rem;
            display:flex; align-items:center; gap:10px; flex-wrap:wrap;
        }
        .aviso-previa.erro { background:#b91c1c; }
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
        .gm-label.erro { background:#fce8e6; color:#b91c1c; }
        .gm-from { display:flex; align-items:center; gap:12px; padding:6px 28px 16px; }
        .gm-from-av { width:40px; height:40px; border-radius:50%; background:#1a73e8; color:#fff;
                      display:flex; align-items:center; justify-content:center; font-weight:700; font-size:1.05rem; flex-shrink:0; }
        .gm-from-info { flex:1; min-width:0; }
        .gm-from-info .l1 { font-size:.9rem; color:#202124; }
        .gm-from-info .l1 b { font-weight:700; }
        .gm-from-info .l1 .addr { color:#5f6368; }
        .gm-from-info .l2 { font-size:.8rem; color:#5f6368; margin-top:1px; }
        .gm-from-meta { text-align:right; color:#5f6368; font-size:.78rem; white-space:nowrap; }
        .gm-body-frame { width:100%; border:0; display:block; background:#fff; }
        .erro-box { margin:0 28px 20px; background:#fef2f2; border:1px solid #fecaca; color:#991b1b;
                    border-radius:8px; padding:12px 16px; font-size:.85rem; }
    </style>
</head>
<body>
    <div class="aviso-previa <?= $sucesso ? '' : 'erro' ?>">
        <span class="tag"><?= $sucesso ? 'Prévia' : 'Falha no envio' ?></span>
        <?php if ($sucesso): ?>
            Este é o e-mail exatamente como foi enviado para <strong><?= htmlspecialchars($log['email_destino']) ?></strong>.
        <?php else: ?>
            Este e-mail <strong>FALHOU</strong> ao ser enviado para <strong><?= htmlspecialchars($log['email_destino']) ?></strong>. Abaixo está o conteúdo que seria entregue.
        <?php endif; ?>
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
        <div class="gm-avatar"><?= htmlspecialchars(mb_substr($log['email_destino'], 0, 1)) ?></div>
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
                <h1><?= htmlspecialchars($log['assunto']) ?></h1>
                <span class="gm-label <?= $sucesso ? '' : 'erro' ?>"><?= $sucesso ? 'Caixa de entrada' : 'Não entregue' ?></span>
                <i class="fa-regular fa-star" style="color:#5f6368;"></i>
            </div>
            <div class="gm-from">
                <div class="gm-from-av">P</div>
                <div class="gm-from-info">
                    <div class="l1"><b>Prefeitura de Pau dos Ferros</b> <span class="addr">&lt;<?= htmlspecialchars($remetente) ?>&gt;</span></div>
                    <div class="l2">para <?= htmlspecialchars($log['email_destino']) ?></div>
                </div>
                <div class="gm-from-meta">
                    <?= htmlspecialchars(formataData($log['data_envio'])) ?><br>
                    por <?= htmlspecialchars($log['usuario_envio'] ?: 'Sistema') ?>
                </div>
            </div>

            <?php if (!$sucesso && !empty($log['erro'])): ?>
                <div class="erro-box">
                    <i class="fas fa-triangle-exclamation me-1"></i>
                    <strong>Erro registrado:</strong> <?= htmlspecialchars($log['erro']) ?>
                </div>
            <?php endif; ?>

            <iframe id="mailFrame" class="gm-body-frame" srcdoc="<?= htmlspecialchars($corpoEmail, ENT_QUOTES, 'UTF-8') ?>"></iframe>
        </div>
    </div>

    <script>
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
