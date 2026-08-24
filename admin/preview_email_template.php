<?php
/**
 * Prévia dos e-mails antes do envio.
 *
 * Este endpoint sempre renderiza o template real usado pelo EmailService e o
 * abre dentro da mesma moldura Gmail usada na prévia do documento final.
 * Nada é enviado nem persistido.
 */
require_once 'conexao.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../tipos_alvara.php';
verificaLogin();

$tipo = preg_replace('/[^a-z_]/', '', (string) ($_POST['tipo'] ?? ''));
$permitidos = ['protocolo', 'protocolo_oficial', 'indeferimento', 'aprovado', 'pendencia', 'reenvio', 'boleto'];
if (!in_array($tipo, $permitidos, true)) {
    http_response_code(400);
    exit('Tipo de e-mail inválido.');
}

$id = (int) ($_POST['requerimento_id'] ?? 0);
$req = null;
if ($id > 0) {
    $stmt = $pdo->prepare('SELECT r.protocolo, r.tipo_alvara, re.nome AS requerente_nome, re.email AS requerente_email
        FROM requerimentos r LEFT JOIN requerentes re ON re.id = r.requerente_id WHERE r.id = ?');
    $stmt->execute([$id]);
    $req = $stmt->fetch() ?: null;
}

$nome_destinatario = trim((string) ($_POST['nome_destinatario'] ?? ($req['requerente_nome'] ?? 'Requerente')));
$email_destino = trim((string) ($_POST['email_destino'] ?? ($req['requerente_email'] ?? '')));
$protocolo = trim((string) ($_POST['protocolo'] ?? ($req['protocolo'] ?? '')));
$tipo_alvara = trim((string) ($_POST['tipo_alvara'] ?? ($req['tipo_alvara'] ?? '')));
$tipo_alvara_nome = $tipos_alvara[$tipo_alvara]['nome'] ?? ucwords(str_replace('_', ' ', $tipo_alvara));
$protocolo_oficial = trim((string) ($_POST['protocolo_oficial'] ?? ''));
$motivo_indeferimento = trim((string) ($_POST['motivo_indeferimento'] ?? ''));
$orientacoes_adicionais = trim((string) ($_POST['orientacoes_adicionais'] ?? ''));
$pendencias = trim((string) ($_POST['pendencias'] ?? ''));
$link_complementacao = trim((string) ($_POST['link_complementacao'] ?? ''));
$motivo_reenvio = trim((string) ($_POST['motivo_reenvio'] ?? ''));
$url_pagamento = trim((string) ($_POST['url_pagamento'] ?? '#'));
$instrucoes = trim((string) ($_POST['instrucoes'] ?? ''));

$subjects = [
    'protocolo' => "Confirmação de Requerimento - Protocolo #{$protocolo}",
    'protocolo_oficial' => "Protocolo Oficial da Prefeitura - #{$protocolo_oficial}",
    'indeferimento' => "[SEMA] Protocolo #{$protocolo} - Processo Indeferido",
    'aprovado' => "[SEMA] Protocolo #{$protocolo} - Processo Aprovado",
    'pendencia' => "[SEMA] Protocolo #{$protocolo} - Documentação Pendente",
    'reenvio' => "[SEMA] Protocolo #{$protocolo} - Processo Devolvido para Correção",
    'boleto' => "[SEMA] Protocolo #{$protocolo} - Boleto disponível para pagamento",
];
$assunto = $subjects[$tipo];

ob_start();
switch ($tipo) {
    case 'protocolo':
        $nome = $nome_destinatario;
        $dados = $req ? array_merge($req, ['id' => $id]) : ($id ? ['id' => $id] : []);
        include __DIR__ . '/../templates/email_protocolo.php';
        break;
    case 'protocolo_oficial':
        include __DIR__ . '/../templates/email_protocolo_oficial.php';
        break;
    case 'indeferimento':
        include __DIR__ . '/../templates/email_indeferimento.php';
        break;
    case 'aprovado':
        include __DIR__ . '/../templates/email_aprovado.php';
        break;
    case 'pendencia':
        include __DIR__ . '/../templates/email_pendencia.php';
        break;
    case 'reenvio':
        include __DIR__ . '/../templates/email_reenvio.php';
        break;
    case 'boleto':
        include __DIR__ . '/../templates/email_boleto.php';
        break;
}
$corpo_email = ob_get_clean();
$remetente = defined('EMAIL_FROM') ? EMAIL_FROM : 'naoresponder@protocolosead.com';
$avatar = function_exists('mb_substr') ? mb_substr($nome_destinatario ?: 'C', 0, 1) : substr($nome_destinatario ?: 'C', 0, 1);
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Prévia do e-mail — <?= htmlspecialchars($assunto) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f6f8fc;font-family:'Google Sans',Roboto,Arial,sans-serif;color:#202124}
        .notice{background:#0b3b8c;color:#fff;padding:11px 20px;font-size:.82rem;display:flex;align-items:center;gap:10px;flex-wrap:wrap}.tag{background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.3);border-radius:999px;padding:3px 11px;font-size:.68rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}
        .gm-top{height:58px;background:#fff;border-bottom:1px solid #e4e7ec;display:flex;align-items:center;gap:18px;padding:0 18px}.gm-logo{display:flex;align-items:center;gap:9px}.gm-logo b{color:#5f6368;font-size:1.35rem;font-weight:400}.gm-search{flex:1;max-width:720px;height:40px;background:#eaf1fb;border-radius:8px;display:flex;align-items:center;gap:12px;padding:0 16px;color:#5f6368;font-size:.9rem}.avatar{width:32px;height:32px;border-radius:50%;background:#0b8043;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;margin-left:auto}
        .wrap{max-width:1080px;margin:auto;padding:14px 18px 40px}.toolbar{display:flex;gap:20px;color:#5f6368;padding:8px 4px 14px}.mail{background:#fff;border-radius:16px;box-shadow:0 1px 3px rgba(60,64,67,.15);overflow:hidden}.subject{display:flex;align-items:center;gap:12px;padding:22px 28px 8px}.subject h1{font-size:1.35rem;font-weight:400;flex:1;margin:0}.label{background:#e8f0fe;color:#1967d2;font-size:.72rem;font-weight:600;border-radius:5px;padding:3px 8px;white-space:nowrap}.from{display:flex;align-items:center;gap:12px;padding:6px 28px 16px}.from-avatar{width:40px;height:40px;border-radius:50%;background:#1a73e8;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0}.from-info{flex:1;min-width:0;font-size:.9rem}.from-info .addr,.from-info .to,.meta{color:#5f6368;font-size:.8rem}.meta{text-align:right;white-space:nowrap}.body-frame{width:100%;border:0;display:block;background:#fff;min-height:650px}
        @media(max-width:640px){.gm-search{display:none}.wrap{padding:10px 8px 28px}.subject{padding:18px 16px 8px;align-items:flex-start}.subject h1{font-size:1.1rem}.from{padding:6px 16px 16px}.meta{display:none}.mail{border-radius:12px}.notice{padding:10px 12px}}
    </style>
</head>
<body>
    <div class="notice"><span class="tag">Prévia</span>Este é o template real de <strong><?= htmlspecialchars($assunto) ?></strong>. Nenhum e-mail foi enviado.</div>
    <div class="gm-top">
        <div class="gm-logo"><svg width="34" height="26" viewBox="0 0 34 26" aria-hidden="true"><path fill="#4285f4" d="M2 24h5V11L0 6v16a2 2 0 0 0 2 2z"/><path fill="#34a853" d="M27 24h5a2 2 0 0 0 2-2V6l-7 5z"/><path fill="#fbbc04" d="M27 4v7l7-5V4.5A2.5 2.5 0 0 0 30 3z"/><path fill="#ea4335" d="M7 11V4l10 7.5L27 4v7l-10 7.5z"/><path fill="#c5221f" d="M0 4.5V6l7 5V4L4 3A2.5 2.5 0 0 0 0 4.5z"/></svg><b>Gmail</b></div>
        <div class="gm-search"><i class="fas fa-magnifying-glass"></i> Pesquisar e-mail</div><div class="avatar"><?= htmlspecialchars($avatar) ?></div>
    </div>
    <main class="wrap"><div class="toolbar"><i class="fas fa-arrow-left"></i><i class="fas fa-box-archive"></i><i class="fas fa-triangle-exclamation"></i><i class="fas fa-trash-can"></i><i class="fas fa-envelope-open"></i></div>
        <article class="mail"><div class="subject"><h1><?= htmlspecialchars($assunto) ?></h1><span class="label">Caixa de entrada</span><i class="fa-regular fa-star"></i></div>
            <div class="from"><div class="from-avatar">P</div><div class="from-info"><strong>Prefeitura de Pau dos Ferros</strong> <span class="addr">&lt;<?= htmlspecialchars($remetente) ?>&gt;</span><div class="to">para <?= htmlspecialchars($email_destino ?: '(sem e-mail cadastrado)') ?></div></div><div class="meta"><?= date('d/m/Y H:i') ?><br>prévia local</div></div>
            <iframe id="mailFrame" class="body-frame" srcdoc="<?= htmlspecialchars($corpo_email, ENT_QUOTES, 'UTF-8') ?>" title="Conteúdo do e-mail"></iframe>
        </article>
    </main>
    <script>const f=document.getElementById('mailFrame');function resize(){try{f.style.height=(f.contentWindow.document.body.scrollHeight+32)+'px'}catch(e){f.style.height='900px'}}f.addEventListener('load',resize);addEventListener('resize',resize);setTimeout(resize,400);</script>
</body>
</html>
