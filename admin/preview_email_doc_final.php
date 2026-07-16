<?php
/**
 * Pré-visualização do e-mail de entrega do documento final.
 *
 * Renderiza o MESMO template usado no envio real (templates/email_documento_final.php),
 * com os dados reais do processo e a seleção feita no modal. Nada é gravado nem enviado:
 * o link do portal é fictício, só para o botão do e-mail não ficar vazio na prévia.
 */
require_once 'conexao.php';
require_once 'helpers.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/entrega_helpers.php';
require_once __DIR__ . '/../tipos_alvara.php';
verificaLogin();

$id = (int) ($_POST['requerimento_id'] ?? $_GET['requerimento_id'] ?? 0);
if (!$id) {
    http_response_code(400);
    exit('Processo não informado.');
}

$stmt = $pdo->prepare("
    SELECT r.protocolo, r.tipo_alvara, re.nome AS requerente_nome
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
// para a prévia não sair vazia.
$documentoIds = array_filter(array_map('intval', (array) ($_POST['documento_ids'] ?? [])));

if ($documentoIds) {
    $ph = implode(',', array_fill(0, count($documentoIds), '?'));
    $stmtDocs = $pdo->prepare("SELECT nome_arquivo FROM assinaturas_digitais WHERE requerimento_id = ? AND id IN ($ph)");
    $stmtDocs->execute(array_merge([$id], $documentoIds));
} else {
    $stmtDocs = $pdo->prepare("SELECT nome_arquivo FROM assinaturas_digitais WHERE requerimento_id = ? ORDER BY timestamp_assinatura DESC");
    $stmtDocs->execute([$id]);
}

$documentos = [];
foreach ($stmtDocs->fetchAll() as $docRow) {
    $documentos[] = [
        'nome'   => $docRow['nome_arquivo'],
        'rotulo' => rotuloDocumento($docRow['nome_arquivo']),
    ];
}
if (!$documentos) {
    $documentos[] = ['nome' => 'documento_exemplo.pdf', 'rotulo' => 'Documento de exemplo'];
}

// Variáveis consumidas pelo template.
$protocolo         = $reqData['protocolo'];
$nome_destinatario = $reqData['requerente_nome'];
$tipo_alvara       = $tipos_alvara[$reqData['tipo_alvara']]['nome']
                        ?? ucwords(str_replace('_', ' ', $reqData['tipo_alvara']));
$instrucoes        = trim($_POST['instrucoes_doc_final'] ?? '');
$url_portal        = gerarUrlDocumentoFinal('previa-nao-funcional');
$validade_dias     = ENTREGA_LINK_VALIDADE_DIAS;

ob_start();
include __DIR__ . '/../templates/email_documento_final.php';
$corpoEmail = ob_get_clean();

$assunto = '[SEMA] ' . tituloAmigavel($tipo_alvara) . ' pronto — protocolo #' . $protocolo;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prévia do e-mail — protocolo #<?= htmlspecialchars($protocolo) ?></title>
    <style>
        body { margin:0; background:#e9edf2; font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif; }
        .previa-barra {
            background:#0f2740; color:#fff; padding:14px 20px;
            display:flex; align-items:center; gap:14px; flex-wrap:wrap;
        }
        .previa-tag {
            background:rgba(255,255,255,.14); border:1px solid rgba(255,255,255,.25);
            border-radius:999px; padding:3px 12px; font-size:.7rem; font-weight:700;
            letter-spacing:.08em; text-transform:uppercase;
        }
        .previa-assunto { font-size:.86rem; opacity:.9; }
        .previa-assunto strong { opacity:1; }
        .previa-nota {
            background:#fffbeb; border-bottom:1px solid #fde68a; color:#92400e;
            padding:9px 20px; font-size:.78rem;
        }
        .previa-quadro { border:0; width:100%; height:calc(100vh - 96px); display:block; background:#eef1f5; }
    </style>
</head>
<body>
    <div class="previa-barra">
        <span class="previa-tag">Prévia</span>
        <span class="previa-assunto">
            <strong>Assunto:</strong> <?= htmlspecialchars($assunto) ?>
            &nbsp;·&nbsp; <strong>Para:</strong> <?= htmlspecialchars($nome_destinatario) ?>
        </span>
    </div>
    <div class="previa-nota">
        Nenhum e-mail foi enviado. O botão de download aqui é ilustrativo — o link real só é gerado no envio.
    </div>
    <iframe class="previa-quadro" srcdoc="<?= htmlspecialchars($corpoEmail, ENT_QUOTES, 'UTF-8') ?>"></iframe>
</body>
</html>
