<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../../includes/assinatura_workflow_helpers.php';
verificaLogin();

$requerimento_id = filter_input(INPUT_GET, 'requerimento_id', FILTER_VALIDATE_INT);
$template        = filter_input(INPUT_GET, 'template', FILTER_DEFAULT);
$label           = filter_input(INPUT_GET, 'label', FILTER_DEFAULT) ?: '';
$labelsOficiais = [
    'alvara_de_construcao' => 'Alvará de Construção',
    'alvara_de_desmembramento' => 'Alvará de Desmembramento',
    'carta_habite_se' => 'Carta de Habite-se',
];
if ($label === '' && isset($labelsOficiais[$template])) $label = $labelsOficiais[$template];

if (!$requerimento_id || empty($template)) {
    header('Location: selecionar.php' . ($requerimento_id ? '?requerimento_id=' . $requerimento_id : ''));
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Dados completos do processo: alimentam a consulta rápida da barra de ações,
// pra não precisar abrir o requerimento em outra aba durante a redação.
require_once __DIR__ . '/../../tipos_alvara.php';

$stmt = $pdo->prepare("
    SELECT r.*,
           req.nome AS requerente_nome, req.cpf_cnpj AS requerente_cpf_cnpj,
           req.email AS requerente_email, req.telefone AS requerente_telefone,
           p.nome AS proprietario_nome, p.cpf_cnpj AS proprietario_cpf_cnpj
    FROM requerimentos r
    JOIN requerentes req ON r.requerente_id = req.id
    LEFT JOIN proprietarios p ON r.proprietario_id = p.id
    WHERE r.id = ?
");
$stmt->execute([$requerimento_id]);
$req = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$req) die("Erro: Requerimento não encontrado.");

$stmtDocs = $pdo->prepare("SELECT campo_formulario, nome_original, caminho, tipo_arquivo, tamanho
    FROM documentos WHERE requerimento_id = ? ORDER BY id");
$stmtDocs->execute([$requerimento_id]);
$documentosProcesso = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);

$tipoAlvaraLabel = $tipos_alvara[$req['tipo_alvara']]['nome']
    ?? ucwords(str_replace('_', ' ', (string) $req['tipo_alvara']));

/**
 * Rótulo legível para o campo que originou o anexo.
 *
 * Os campos de upload nascem em scripts/obter_documentos.php como
 * `doc_{tipo}_{indice}` (ou `doc_opcional_`, `doc_pf_`, `doc_pj_`), onde o
 * índice é a posição na lista de tipos_alvara.php — dá pra recuperar o nome
 * real do documento em vez de mostrar "Doc desmembramento 5".
 */
$rotuloCampoDocumento = static function (string $campo) use ($req, $tipos_alvara): string {
    $especiais = [
        'boleto_pagamento_admin' => 'Boleto enviado pela equipe',
        'comprovante_pagamento_boleto' => 'Comprovante de pagamento',
    ];
    if (isset($especiais[$campo])) return $especiais[$campo];
    if (preg_match('/^pendencia_(\d+)$/', $campo, $m)) return 'Resposta de pendência #' . $m[1];

    $tipo = (string) $req['tipo_alvara'];
    $listas = [
        'doc_opcional_' . $tipo . '_' => 'documentos_opcionais',
        'doc_pf_' . $tipo . '_'       => 'pessoa_fisica',
        'doc_pj_' . $tipo . '_'       => 'pessoa_juridica',
        'doc_' . $tipo . '_'          => 'documentos',
    ];
    foreach ($listas as $prefixo => $chave) {
        if (strpos($campo, $prefixo) !== 0) continue;
        $indice = substr($campo, strlen($prefixo));
        if (!ctype_digit($indice)) continue;
        $rotulo = $tipos_alvara[$tipo][$chave][(int) $indice] ?? '';
        if ($rotulo === '') continue;
        // As listas vêm numeradas e com ponto e vírgula final ("4. Documento do terreno;").
        return trim(preg_replace('/^\d+\.\s*/', '', rtrim(trim($rotulo), ';')));
    }
    return ucfirst(str_replace('_', ' ', $campo));
};

$formatarTamanho = static function ($bytes): string {
    $bytes = (int) $bytes;
    if ($bytes <= 0) return '—';
    if ($bytes < 1024 * 1024) return number_format($bytes / 1024, 0, ',', '.') . ' KB';
    return number_format($bytes / (1024 * 1024), 1, ',', '.') . ' MB';
};

$adminTemChave = false;
try {
    $stmtChave = $pdo->prepare('SELECT 1 FROM admin_chaves_assinatura WHERE admin_id = ? LIMIT 1');
    $stmtChave->execute([(int) ($_SESSION['admin_id'] ?? 0)]);
    $adminTemChave = (bool) $stmtChave->fetchColumn();
} catch (Throwable $e) {
    // Instalações anteriores à assinatura avançada continuam usando a senha de acesso.
}

$secretarioManual = null;
$erroSecretarioManual = '';
try {
    $secretarioManual = buscarSecretarioAtivoUnico($pdo);
} catch (AssinaturaWorkflowException $e) {
    $erroSecretarioManual = $e->getMessage();
}
$adminManualAtual = [
    'id' => (int) ($_SESSION['admin_id'] ?? 0),
    'nome' => (string) ($_SESSION['admin_nome_completo'] ?? $_SESSION['admin_nome'] ?? 'Usuário atual'),
    'cargo' => (string) ($_SESSION['admin_cargo'] ?? 'Servidor(a) Municipal'),
];
try {
    $stmtAdminManual = $pdo->prepare('SELECT id, nome, nome_completo, cargo FROM administradores WHERE id = ? LIMIT 1');
    $stmtAdminManual->execute([$adminManualAtual['id']]);
    $registroAdminManual = $stmtAdminManual->fetch(PDO::FETCH_ASSOC);
    if ($registroAdminManual) {
        $adminManualAtual = resolverAssinanteManual('atual', $registroAdminManual);
    }
} catch (Throwable $e) {
    // A identificação final é novamente carregada e validada no endpoint.
}
$tipoAssinanteManualPadrao = $secretarioManual ? 'secretario' : 'atual';

$titulo_pagina = 'Editor de Documento';
include '../header.php';
?>
    <!-- Assets Extras Específicos do Editor -->
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

    <style>
        /* ═══════════════════════════════════════════════
           VARIÁVEIS E BASE
        ═══════════════════════════════════════════════ */
        :root {
            --sema-green:    #1c4b36;
            --sema-green-lt: #2a6b50;
            --sema-teal:     #0d7f5f;
            --card-radius:   14px;
            --a4-width:      210mm;
            --a4-height:     297mm;
            --a4-header-h:   27mm;
            --a4-footer-h:   14mm;
            --a4-margin-lr:  15mm;
            --a4-usable-h:   256mm; /* 297 - 27 - 14 */
            --page-gap:      28px;
            --doc-font-size: 12pt;
            --doc-line-h:    1.4;
            --doc-p-gap:     12px;
            --doc-p-indent:  50px;
            --doc-p-line-h:  1.7;
            --doc-table-vpad: 5px;
            --doc-table-hpad: 8px;
        }

        @keyframes shimmer {
            0%   { background-position: -800px 0; }
            100% { background-position: 800px 0; }
        }

        /* Ocultar imagem de fundo do template no editor — só usada na geração do PDF */
        .note-editable #fundo-imagem,
        .note-editable img[alt="Fundo A4"] {
            display: none !important;
        }

        /* Editor fullscreen */
        #secao-editor {
            min-height: calc(100vh - var(--topbar-height, 60px) - 70px);
            /* Sem fundo próprio: quem pinta o cinza é a .a4-outer-wrapper. */
        }

        /* ═══════════════════════════════════════════════
           CANVAS CONTÍNUO — "Folha Infinita"
        ═══════════════════════════════════════════════ */
        .a4-outer-wrapper {
            /* A "mesa" cinza agora é só a coluna da folha, não a largura toda:
               o rail fica ao lado, sobre o fundo normal da página. */
            background: #ecefec;
            border: 1px solid #e3e8e4;
            border-radius: 16px;
            padding: 22px 16px 28px;
            min-height: 100%;
        }

        /* Um único papel que cresce, mas marca quebras visualmente */
        .a4-page-sheet {
            max-width: var(--a4-width);
            min-height: var(--a4-height);
            margin: 0 auto;
            background: #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08), 0 6px 32px rgba(0,0,0,0.12);
            border-radius: 2px;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        /* ═══════════════════════════════════════════════
           HEADER SEMA (topo da primeira folha)
        ═══════════════════════════════════════════════ */
        .a4-sema-header {
            /* Altura EXATA da margem superior do TCPDF: assim a primeira folha
               fecha em 27 + 256 + 14 = 297mm, igual ao PDF. */
            height: var(--a4-header-h);
            padding: 6mm var(--a4-margin-lr) 0 var(--a4-margin-lr);
            box-sizing: border-box;
            flex-shrink: 0;
            background: #fff;
            z-index: 5;
        }
        .a4-sema-header .header-content {
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 5mm;
        }
        .a4-sema-header img {
            height: 17mm;
            width: auto;
            object-fit: contain;
            flex-shrink: 0;
        }
        .a4-sema-header .sema-prefeitura {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-weight: 700;
            font-size: 10pt;
            color: #282828;
            line-height: 1.3;
        }
        .a4-sema-header .sema-secretaria {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-weight: 700;
            font-size: 8pt;
            color: #646464;
            line-height: 1.3;
            margin-top: 1px;
        }
        .a4-sema-header .header-line {
            height: 1.2px;
            background: #2d8661;
        }

        /* ═══════════════════════════════════════════════
           FOOTER (base da última folha)
        ═══════════════════════════════════════════════ */
        .a4-sema-footer {
            height: var(--a4-footer-h);
            padding: 0 var(--a4-margin-lr);
            box-sizing: border-box;
            border-top: 0.5px solid #d2d2d2;
            margin-top: auto;
            flex-shrink: 0;
            overflow: hidden;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            text-align: center;
            background: #fff;
            z-index: 5;
        }
        .a4-footer-sign {
            font-size: 5.5pt;
            color: #8c8c8c;
            margin-top: 2.5mm;
            line-height: 1.6;
        }
        .a4-footer-date {
            font-size: 5pt;
            color: #aaa;
            font-style: italic;
        }
        .a4-footer-page {
            font-size: 6pt;
            color: #b4b4b4;
            margin-top: 2mm;
        }

        /* ═══════════════════════════════════════════════
           ASSINATURA DIGITAL — espelho do bloco do PDF
           (88mm × 20mm). A posição é decidida pelo TCPDF:
           rodapé da última folha real. Aqui é só reflexo.
        ═══════════════════════════════════════════════ */
        .a4-signature-badge {
            position: absolute;
            width: 88mm;
            height: 20mm;
            background: #fff;
            border: 0.5px solid #969696;
            border-top: 1.1mm solid var(--sema-green);
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            z-index: 30;
            box-shadow: 0 2px 8px rgba(0,0,0,0.14);
            cursor: default;
            user-select: none;
            pointer-events: none;
            display: flex;
            gap: 2.5mm;
            padding: 2mm;
            box-sizing: border-box;
            transition: box-shadow .15s;
        }
        .a4-signature-badge .sig-logo {
            width: 15mm; height: 15mm;
            flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
        }
        .a4-signature-badge .sig-logo img {
            max-width: 100%; max-height: 100%;
            object-fit: contain;
        }
        .a4-signature-badge .sig-info { flex: 1; min-width: 0; display: flex; flex-direction: column; }
        .a4-signature-badge .sig-title {
            font-size: 6pt; font-weight: 700; color: var(--sema-green);
            letter-spacing: .02em;
        }
        .a4-signature-badge .sig-name {
            font-size: 6.4pt; font-weight: 700; color: #141414; margin-top: 1mm;
        }
        .a4-signature-badge .sig-detail {
            font-size: 5.4pt; color: #555; margin-top: 0.4mm;
        }
        .a4-signature-badge .sig-verify {
            font-size: 4.8pt; color: #777; margin-top: auto;
            border-top: 0.15mm solid #ddd; padding-top: 0.6mm;
        }
        .a4-signature-badge .sig-verify b { color: var(--sema-green); }

        /* ═══════════════════════════════════════════════
           VARIÁVEIS DESTACADAS — NEGRITO (sem cor)
           Campos auto-preenchidos pelo protocolo.
           Estilo neutro para não gerar artefatos no PDF.
        ═══════════════════════════════════════════════ */
        .note-editable .var-field,
        .var-field {
            font-weight: 700 !important;
            color: inherit !important;
            text-decoration: none !important;
            background: rgba(0, 0, 0, 0.045);
            border-radius: 2px;
            padding: 0 2px;
        }
        .doc-campo.em-edicao {
            background: #eef7f1 !important;
        }
        .doc-campo.em-edicao .doc-campo-input {
            border-color: #2e7d32 !important;
            background: #fff !important;
            box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.12) !important;
        }

        /* ═══════════════════════════════════════════════
           ESTRUTURA DO EDITOR — APARÊNCIA DE PÁGINA A4
        ═══════════════════════════════════════════════ */
        .note-editor.note-frame {
            border: none !important;
            box-shadow: none !important;
            background: transparent;
        }
        .note-toolbar {
            background: #fff !important;
            border: 1px solid #dee2e6 !important;
            border-radius: 8px !important;
            padding: 6px 10px !important;
            margin: 0 auto 12px !important;
            max-width: var(--a4-width) !important;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06) !important;
            position: sticky;
            top: 0;
            z-index: 20;
        }
        .note-editing-area {
            background: transparent;
            flex: 1;
            overflow: visible !important;
        }
        .note-editor.note-frame .note-editing-area {
            overflow: visible !important;
        }
        .note-editable {
            font-family: "Times New Roman", Times, serif !important;
            font-size: var(--doc-font-size) !important;
            line-height: var(--doc-line-h) !important;
            color: #1e1e1e !important;
            text-align: justify !important;
            /* Sem padding vertical: o topo do editável é exatamente o topo da
               área útil da folha 1, que é onde o TCPDF começa a escrever. */
            padding: 0 var(--a4-margin-lr) !important;
            min-height: var(--a4-usable-h) !important;
            height: auto !important;
            overflow: visible !important;
            box-sizing: border-box !important;
            position: relative;
            /* Espelha a quebra do TCPDF: palavras longas (ex: "aaaa…" sem
               espaços) quebram em vez de transbordar — mantém a altura medida
               coerente com a paginação real do PDF. */
            overflow-wrap: break-word !important;
            word-break: break-word !important;
        }
        .note-editable table {
            width: 100%; border-collapse: collapse;
        }
        .note-editable td, .note-editable th {
            padding: var(--doc-table-vpad) var(--doc-table-hpad); border: 1px solid #aaa; vertical-align: middle;
            font-size: 11pt; line-height: var(--doc-line-h);
        }
        .note-editable .texto-parecer p {
            margin-bottom: var(--doc-p-gap); text-indent: var(--doc-p-indent); line-height: var(--doc-p-line-h);
        }
        .note-editable .condicionantes {
            font-size: 9pt; border: 1px solid #000; padding: 8px 10px;
        }

        /* Separador entre folhas: rodapé da folha que termina, faixa da mesa
           e cabeçalho da folha que começa. É inserido ENTRE os nós do texto,
           nunca dentro deles — por isso não parte parágrafo nem move o cursor.
           A altura sai da soma dos três blocos internos, o que permite usar a
           mesma marcação como <div>, como <li> ou como <tr> de tabela. */
        .note-editable .page-gap {
            width: 100%;
            position: relative;
            background: #fff;
            user-select: none;
            pointer-events: none;
            z-index: 8;
        }
        /* Sangra até a borda do papel, apagando as margens laterais do texto. */
        .note-editable .page-gap .page-gap-inner {
            margin-left: calc(-1 * var(--a4-margin-lr));
            margin-right: calc(-1 * var(--a4-margin-lr));
        }
        .page-gap .page-gap-footer {
            height: var(--a4-footer-h);
            border-top: .5px solid #d2d2d2;
            display:flex;
            align-items:flex-end;
            justify-content:center;
            padding-bottom:3mm;
            box-sizing:border-box;
            color:#a5aaa7;
            font:600 6pt 'Helvetica Neue', Arial, sans-serif;
            background:#fff;
        }
        .page-gap .page-gap-space {
            height: var(--page-gap);
            background:#ecefec;
            border-top:1px solid #dce2de;
            border-bottom:1px solid #dce2de;
            box-shadow:inset 0 8px 14px rgba(19,45,32,.06), inset 0 -8px 14px rgba(19,45,32,.05);
        }
        .page-gap .page-gap-header {
            height: var(--a4-header-h);
            padding:6mm var(--a4-margin-lr) 0;
            box-sizing:border-box;
            background:#fff;
        }
        .page-gap .page-gap-header-inner { display:flex; align-items:center; gap:4mm; height:17mm; }
        .page-gap .page-gap-header img { height:17mm; width:auto; object-fit:contain; }
        .page-gap .page-gap-prefeitura { font:700 8pt 'Helvetica Neue',Arial,sans-serif; color:#282828; }
        .page-gap .page-gap-secretaria { margin-top:1px; font:700 6pt 'Helvetica Neue',Arial,sans-serif; color:#646464; }
        .page-gap .page-gap-line { height:1.2px; background:#2d8661; }


        /* ═══════════════════════════════════════════════
           FIDELIDADE COM O PDF
           O TCPDF ignora margens verticais de div/p/h1..h6 — o espaço
           vertical desses blocos é zerado por setHtmlVSpace() em
           admin/assinatura/gerar_pdf.php, e o espaçamento no documento
           final acaba sendo exatamente uma linha.
           Os modelos trazem o próprio <style> com margens generosas, que o
           navegador aplica e o PDF não: era essa diferença que inflava o
           editor e fazia a contagem de folhas divergir do PDF.
        ═══════════════════════════════════════════════ */
        .note-editable p,
        .note-editable div,
        .note-editable h1, .note-editable h2, .note-editable h3,
        .note-editable h4, .note-editable h5, .note-editable h6 {
            margin-top: 0 !important;
            margin-bottom: 0 !important;
        }

        /* Tabelas: o TCPDF monta a linha com a altura de UMA linha de texto
           (sem padding vertical) e reserva ~5mm antes e depois da tabela.
           Medido em admin/assinatura/gerar_pdf.php: passo de linha 5,43mm,
           espaço texto→tabela 12,36mm contra 7,20mm de passo normal. */
        .note-editable table {
            border-collapse: collapse;
            margin: 5.2mm 0 !important;
        }
        .note-editable td,
        .note-editable th {
            padding: 0 2mm !important;
            font-size: 11pt !important;
            line-height: var(--doc-line-h) !important;
            vertical-align: middle;
        }

        /* O separador de folha é do editor, não do documento: mantém a altura. */
        .note-editable .page-gap,
        .note-editable .page-gap div {
            margin-top: 0 !important;
            margin-bottom: 0 !important;
        }

        /* ═══════════════════════════════════════════════
           MODAL — SELETOR DE MODO (lista vertical hierárquica)
        ═══════════════════════════════════════════════ */
        .modo-lista { display: flex; flex-direction: column; gap: 10px; }
        .modo-card {
            display: flex; align-items: center; gap: 14px;
            border: 1.5px solid #e2e8f0; border-radius: 14px;
            padding: 14px 16px; cursor: pointer; background: #fff;
            transition: all .15s ease;
            margin: 0; position: relative;
        }
        .modo-card:hover { border-color: var(--sema-green-lt); background: #f6faf8; transform: translateY(-1px); }
        .modo-card .mc-icon {
            width: 42px; height: 42px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.05rem; flex-shrink: 0;
            background: #eef2f0; color: #5b7c6e;
            transition: all .15s ease;
        }
        .modo-card .mc-title { font-weight: 700; font-size: .9rem; color: #1e293b; }
        .modo-card .mc-desc  { font-size: .76rem; color: #64748b; margin-top: 2px; line-height: 1.35; }
        .modo-card .mc-check { margin-left: auto; font-size: 1.25rem; color: #d8dee6; transition: all .15s ease; flex-shrink: 0; }
        .modo-card.selected {
            border-color: var(--sema-green);
            background: linear-gradient(180deg, #f3faf6, #eaf4ee);
            box-shadow: 0 4px 16px rgba(28,75,54,.13), 0 0 0 1px var(--sema-green) inset;
        }
        .modo-card.selected .mc-icon { background: var(--sema-green); color: #fff; box-shadow: 0 4px 10px rgba(28,75,54,.28); }
        .modo-card.selected .mc-check { color: var(--sema-green); transform: scale(1.1); }

        /* ═══════════════════════════════════════════════
           MODAL
        ═══════════════════════════════════════════════ */
        .modal-header-sema {
            background: linear-gradient(135deg, var(--sema-green), var(--sema-teal));
            border-bottom: none; color: #fff;
        }
        .modal-header-sema .modal-title { color: #fff !important; }
        .modal-header-sema .btn-close { filter: brightness(0) invert(1); opacity: .85; }
        .text-sema  { color: var(--sema-green) !important; }
        .btn-sema   { background: var(--sema-green); border-color: var(--sema-green); color: #fff; }
        .btn-sema:hover { background: var(--sema-green-lt); border-color: var(--sema-green-lt); color: #fff; }
        /* Botão de pré-visualização — neutro elegante, fora da paleta azul Bootstrap */
        .btn-preview {
            background: #fff; color: var(--sema-green);
            border: 1.5px solid var(--sema-green); font-weight: 500;
            transition: all .15s ease;
        }
        .btn-preview:hover { background: var(--sema-green); color: #fff; }
        .doc-autosave-status { display:inline-flex; align-items:center; gap:4px; margin-left:8px; font-size:.72rem; color:#718078; }
        .doc-autosave-status.salvando { color:#a26a12; }
        .doc-autosave-status.salvo { color:#26734d; }
        .doc-autosave-status.erro { color:#b13232; }
        /* Resumo compacto do documento no topo do modal de finalização */
        .doc-resumo { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:1px; background:#e3ece6;
                      border:1px solid #e3ece6; border-radius:12px; overflow:hidden; }
        .doc-resumo-item { background:#f8fbf9; padding:10px 14px; min-width:0; }
        .doc-resumo-item span { display:block; font-size:.68rem; text-transform:uppercase; letter-spacing:.06em;
                                color:#7d8f84; font-weight:700; margin-bottom:2px; }
        .doc-resumo-item strong { display:block; font-size:.82rem; color:#1a2e1e; overflow:hidden;
                                  text-overflow:ellipsis; white-space:nowrap; }
        .doc-resumo-item strong.pendente { color:#a26a12; }
        /* Consulta rápida do processo (botão "Processo" na barra de ações) */
        .doc-processo-contador { display:inline-flex; align-items:center; justify-content:center; min-width:20px; height:20px;
                                 padding:0 6px; margin-left:6px; border-radius:999px; background:#e6efe9; color:#1f6b47;
                                 font-size:.7rem; font-weight:800; }
        .doc-processo-contador.escuro { background:var(--sema-green); color:#fff; }
        .doc-processo-modal .modal-body { background:#fbfdfc; }
        .doc-processo-bloco { background:#fff; border:1px solid #e4ebe7; border-radius:12px; padding:14px 16px; margin-bottom:12px; }
        .doc-processo-bloco:last-child { margin-bottom:0; }
        .doc-processo-bloco-titulo { display:flex; align-items:center; font-size:.72rem; font-weight:800; letter-spacing:.07em;
                                     text-transform:uppercase; color:#7d8f84; margin-bottom:10px; }
        .doc-processo-lista { display:grid; grid-template-columns:auto minmax(0,1fr); gap:6px 16px; margin:0; font-size:.84rem; }
        .doc-processo-lista dt { color:#7d8f84; font-weight:600; white-space:nowrap; }
        .doc-processo-lista dd { margin:0; color:#17231c; overflow-wrap:anywhere; }
        .doc-processo-texto { margin:0; font-size:.84rem; color:#17231c; line-height:1.55; }
        .doc-processo-vazio { margin:0; font-size:.82rem; color:#8a998f; font-style:italic; }
        .doc-processo-anexos { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:8px; }
        .doc-processo-anexos li { display:flex; align-items:center; gap:10px; padding:9px 11px; border:1px solid #e8eeea;
                                  border-radius:10px; background:#fcfefd; }
        .doc-processo-anexos li > i { color:#c0392b; flex-shrink:0; }
        .doc-processo-anexos li.nao-enviado { background:#f8f9f8; border-style:dashed; }
        .doc-processo-anexos li.nao-enviado > i { color:#b3bfb7; }
        .doc-processo-anexo-info { flex:1; min-width:0; }
        .doc-processo-anexo-info strong { display:block; font-size:.82rem; color:#17231c; font-weight:700; }
        .doc-processo-anexo-info small { display:block; font-size:.73rem; color:#7d8f84; overflow-wrap:anywhere; }
        .doc-processo-abrir { flex-shrink:0; width:30px; height:30px; display:inline-flex; align-items:center; justify-content:center;
                              border-radius:8px; color:var(--sema-green); background:#eef6f1; text-decoration:none; font-size:.75rem; }
        .doc-processo-abrir:hover { background:var(--sema-green); color:#fff; }
        @media (max-width:560px) {
            .doc-processo-lista { grid-template-columns:1fr; gap:2px; }
            .doc-processo-lista dt { margin-top:6px; }
        }
        .signature-dialog { max-width:980px; }
        .signature-modal { overflow:hidden; background:#fff; }
        .signature-modal-header { display:flex; align-items:center; gap:15px; padding:22px 28px; background:linear-gradient(135deg,#153e2c 0%,#216044 100%); color:#fff; }
        .signature-modal-icon { width:46px; height:46px; border-radius:14px; flex:none; display:flex; align-items:center; justify-content:center; background:rgba(255,255,255,.14); color:#d9f4e3; font-size:1.1rem; }
        .signature-modal-heading { flex:1; min-width:0; }
        .signature-modal-heading .modal-title { font-size:1.15rem; letter-spacing:-.01em; }
        .signature-modal-heading p { margin:3px 0 0; color:#d8e9dd; font-size:.78rem; }
        .signature-modal-header .btn-close { filter:brightness(0) invert(1); opacity:.75; align-self:flex-start; margin-top:2px; }
        .signature-modal .modo-lista { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px; }
        .signature-modal .modo-card { min-height:142px; display:flex; flex-direction:column; align-items:flex-start; gap:9px; padding:15px; border-radius:13px; position:relative; }
        .signature-modal .modo-card .mc-icon { width:34px; height:34px; border-radius:10px; display:flex; align-items:center; justify-content:center; }
        .signature-modal .modo-card .mc-check { position:absolute; top:14px; right:14px; }
        .signature-modal .modo-card .mc-title { font-size:.84rem; }
        .signature-modal .modo-card .mc-desc { font-size:.72rem; line-height:1.45; }
        .signature-modal .pin-box { margin-top:2px; }
        .signature-modal .aceite-box { margin-bottom:0; }
        .signature-modal .signature-confirm-footer { display:flex; align-items:center; justify-content:space-between; gap:12px; padding-top:18px; margin-top:20px; border-top:1px solid #edf2ee; }
        .signature-modal .signature-confirm-footer .btn { min-height:42px; border-radius:10px; }
        .pdf-preview-dialog { max-width:1180px; }
        .pdf-preview-modal { overflow:hidden; background:#f4f6f5; }
        .pdf-preview-head { display:flex; align-items:center; gap:13px; padding:15px 18px; background:#fff; border-bottom:1px solid #dfe6e2; }
        .pdf-preview-head-icon { width:40px; height:40px; border-radius:11px; display:flex; align-items:center; justify-content:center; flex:none; background:#eaf4ee; color:var(--sema-green); }
        .pdf-preview-head-copy { flex:1; min-width:0; }
        .pdf-preview-head-copy strong { display:block; color:#17231c; font-size:.95rem; }
        .pdf-preview-head-copy span { display:block; color:#718078; font-size:.74rem; margin-top:2px; }
        .pdf-preview-chip { display:inline-flex; align-items:center; gap:6px; padding:7px 10px; border-radius:999px; background:#f0f7f3; color:#286143; font-size:.72rem; font-weight:700; white-space:nowrap; }
        .pdf-preview-stage { position:relative; height:min(82vh,900px); min-height:560px; padding:14px; background:#3f4542; }
        .pdf-preview-stage iframe { width:100%; height:100%; display:block; border:0; border-radius:8px; background:#626765; }
        .pdf-preview-loading { position:absolute; inset:14px; z-index:2; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:10px; color:#dce7e1; background:#3f4542; border-radius:8px; font-size:.82rem; }
        .pdf-preview-loading.loaded { display:none; }
        @media (max-width:760px) {
            .signature-modal-header { padding:18px; }
            .signature-modal .modo-lista { grid-template-columns:1fr; }
            .signature-modal .modo-card { min-height:0; flex-direction:row; align-items:center; }
            .signature-modal .modo-card .mc-desc { max-width:80%; }
            .doc-resumo { grid-template-columns:repeat(2,minmax(0,1fr)); }
            .signature-modal .signature-confirm-footer { flex-direction:column-reverse; align-items:stretch; }
            .pdf-preview-chip { display:none; }
            .pdf-preview-stage { height:calc(100vh - 88px); min-height:0; padding:0; }
            .pdf-preview-stage iframe, .pdf-preview-loading { border-radius:0; }
            .pdf-preview-loading { inset:0; }
        }

        /* PIN de assinatura — compacto */
        .pin-box {
            background:#f6faf8; border:1px solid #cfe6da; border-radius:10px;
            padding:12px 14px; margin-bottom:16px;
        }
        .pin-box-label { font-weight:700; font-size:.82rem; color: var(--sema-green); display:flex; align-items:center; gap:6px; margin-bottom:6px; }
        .pin-box .form-control { border:1px solid #cfe1d7; }
        .pin-box .form-control:focus { border-color: var(--sema-green); box-shadow:0 0 0 .18rem rgba(28,75,54,.13); }
        .pin-box-hint { font-size:.71rem; color:#6b8377; margin-top:5px; }
        .modo-card.disabled { opacity:.58; cursor:not-allowed; background:#f8fafc; }
        .modo-card.disabled:hover { transform:none; box-shadow:none; }

        /* Pessoa exibida na linha de assinatura manual */
        .manual-signers { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:8px; }
        .manual-signer-option {
            display:flex; align-items:flex-start; gap:8px; cursor:pointer; margin:0;
            border:1.5px solid #dbe4df; border-radius:10px; padding:10px; background:#fff;
        }
        .manual-signer-option.selected { border-color:var(--sema-green); background:#f0faf4; }
        .manual-signer-option.disabled { opacity:.55; cursor:not-allowed; }
        .manual-signer-option input { margin-top:3px; accent-color:var(--sema-green); }
        .manual-signer-option strong { display:block; font-size:.8rem; color:#1e293b; }
        .manual-signer-option small { display:block; font-size:.69rem; color:#64748b; line-height:1.25; margin-top:2px; }
        @media (max-width: 767px) { .manual-signers { grid-template-columns:1fr; } }

        /* Cards de co-assinante (lista selecionável) */
        .coass-grid { display:flex; flex-direction:column; gap:7px; max-height:200px; overflow-y:auto; padding:2px; }
        .coass-card {
            display:flex; align-items:center; gap:11px; cursor:pointer;
            border:1.5px solid #e2e8f0; border-radius:11px; padding:9px 12px; background:#fff;
            transition: all .13s ease; margin:0;
        }
        .coass-card:hover { border-color: var(--sema-green-lt); background:#f6faf8; }
        .coass-card .cc-av {
            width:34px; height:34px; border-radius:50%; flex-shrink:0;
            background:#e6efe9; color:#3f6a54; font-weight:800; font-size:.8rem;
            display:flex; align-items:center; justify-content:center;
        }
        .coass-card .cc-nome { font-weight:700; font-size:.84rem; color:#1e293b; line-height:1.2; }
        .coass-card .cc-nivel { font-size:.7rem; color:#94a3b8; }
        .coass-card .cc-check { margin-left:auto; width:22px; height:22px; border-radius:50%; border:2px solid #d1d9e2; display:flex; align-items:center; justify-content:center; color:#fff; font-size:.7rem; transition: all .13s ease; flex-shrink:0; }
        .coass-card input { display:none; }
        .coass-card.sel { border-color: var(--sema-green); background:#f0faf4; box-shadow:0 0 0 1px var(--sema-green) inset; }
        .coass-card.sel .cc-av { background: var(--sema-green); color:#fff; }
        .coass-card.sel .cc-check { background: var(--sema-green); border-color: var(--sema-green); }

        /* Caixa de aceite (diretrizes / manual) */
        .aceite-box { display:flex; align-items:flex-start; gap:10px; padding:13px 15px; margin-bottom:14px; border:1.5px solid #bbf7d0; border-radius:12px; background:#f7fefb; transition: border-color .15s, background .15s; }
        @keyframes shakeX { 0%,100%{transform:translateX(0);} 20%,60%{transform:translateX(-7px);} 40%,80%{transform:translateX(7px);} }
        .shake { animation: shakeX .4s ease; border-color:#ef4444 !important; background:#fef2f2 !important; }

        /* ─── Icon Picker ─── */
        .icon-option {
            aspect-ratio: 1;
            display: flex; align-items: center; justify-content: center;
            border: 1.5px solid #e5e9f2;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.1rem;
            color: #64748b;
            transition: border-color .15s, background .15s, color .15s;
        }
        .icon-option:hover { border-color: var(--sema-green); color: var(--sema-green); background: #f0fdf4; }
        .icon-option.selected { border-color: var(--sema-green); background: #d1fae5; color: var(--sema-green); }

        /* ═══════════════════════════════════════════════
           HEADER DA SEÇÃO
        ═══════════════════════════════════════════════ */
        .section-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 1.2rem;
        }
        .section-header .section-icon {
            width: 36px; height: 36px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
        }
        .section-header h5 { margin: 0; font-weight: 700; color: #1e293b; }
    </style>

    <!-- Trilha + stepper: aqui é o passo 2 (Modelo já escolhido). -->
    <div class="proc-crumb">
        <a href="selecionar.php?requerimento_id=<?= $requerimento_id ?>">
            <i class="fas fa-arrow-left" style="font-size:.72rem"></i> Modelos
        </a>
        <span class="proc-crumb-sep">/</span>
        <span class="proc-crumb-proto">#<?= htmlspecialchars($req['protocolo']) ?></span>
        <?php if ($label !== ''): ?>
            <span class="proc-crumb-sep">/</span>
            <span><?= htmlspecialchars($label) ?></span>
        <?php endif; ?>
    </div>

    <!-- Skeleton loader enquanto o template carrega -->
    <div id="editor-loading" class="text-center py-5">
        <div class="spinner-border text-success" role="status"></div>
        <p class="mt-2 text-muted small">Carregando template...</p>
    </div>

    <!-- Seção do editor (oculta até carregar) -->
    <div class="py-0 d-none" id="secao-editor">

        <!-- Barra de ações do editor -->
        <div class="bg-white border rounded-3 shadow-sm px-4 py-3 mb-3 doc-editor-barra">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-0 fw-bold text-dark" id="editor-title">
                        <i class="fas fa-edit me-2 text-success"></i> Editando Documento
                    </h5>
                    <small class="text-muted" style="font-size:.78rem">
                        Campos em destaque vêm do protocolo. Edite o texto direto na página.
                    </small>
                    <span id="doc-autosave-status" class="doc-autosave-status" aria-live="polite">
                        <i class="fas fa-cloud me-1"></i>Pronto
                    </span>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button class="btn btn-outline-success fw-medium px-3" onclick="abrirModalSalvarTemplate()"
                            title="Guarda este texto como modelo reutilizável">
                        <i class="fas fa-bookmark me-1"></i> Salvar
                    </button>
                    <button class="btn btn-outline-secondary fw-medium px-3" onclick="abrirModalProcesso()"
                            title="Consulta rápida: dados do protocolo e documentos anexados pelo requerente">
                        <i class="fas fa-folder-open me-1"></i> Processo
                        <span class="doc-processo-contador"><?= count($documentosProcesso) ?></span>
                    </button>
                    <button class="btn btn-preview fw-medium px-3" onclick="previewPdf()" title="Gera o PDF real (TCPDF) sem assinar nem registrar. O que você vê é o documento final">
                        <i class="fas fa-file-pdf me-1"></i> Pré-visualizar
                    </button>
                    <button class="btn btn-sema fw-medium px-4" onclick="abrirModalAssinatura()">
                        <i class="fas fa-signature me-2"></i> Assinar e Finalizar
                    </button>
                </div>
            </div>
        </div>

        <!-- Wrapper que simula a "mesa" de trabalho com a página A4 -->
        <div class="doc-layout">
          <div class="doc-col-principal">
            <div class="a4-outer-wrapper rounded-3">
                <textarea id="editor-conteudo"></textarea>
            </div>
          </div>

          <!-- ══════════════════════════════════════════════════
               Rail do editor: os campos que vieram do protocolo e o
               que acontece depois de assinar. A lista é montada a
               partir dos <span class="var-field"> que o
               ParecerService::aplicarHighlights() deixou no HTML —
               nenhum dado novo vem do servidor pra isso.
          ══════════════════════════════════════════════════ -->
          <aside class="doc-rail">
            <div class="doc-rail-card">
                <div class="doc-rail-head doc-campos-head">
                    <span>Campos do documento</span>
                    <span class="doc-campos-vazios" id="doc-campos-vazios" style="display:none"></span>
                </div>
                <div id="doc-campos-lista">
                    <div class="doc-rail-vazio">Carregando campos…</div>
                </div>
                <div class="doc-campos-nota" id="doc-campos-nota" style="display:none">
                    Campos vazios podem ser preenchidos direto na página, antes de assinar.
                </div>
            </div>

            <div class="doc-rail-card">
                <div class="doc-rail-head">Depois de assinar</div>
                <ul class="doc-pos-assinar">
                    <li><i class="fas fa-qrcode"></i>PDF registrado com QR de verificação pública</li>
                    <li><i class="fas fa-folder-open"></i>Anexado à aba Documentos do processo</li>
                    <li><i class="fas fa-paper-plane"></i>Disponível para envio ao cidadão</li>
                </ul>
            </div>
          </aside>
        </div>

    </div><!-- /secao-editor -->

    <!-- Modal de Confirmação -->
    <div class="modal fade" id="modalConfirmacao" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
      <div class="modal-dialog modal-dialog-centered modal-xl signature-dialog">
        <div class="modal-content border-0 shadow-lg rounded-4 signature-modal">
          <div class="signature-modal-header">
             <div class="signature-modal-icon"><i class="fas fa-file-signature"></i></div>
             <div class="signature-modal-heading">
                 <h5 class="modal-title fw-bold">Finalizar documento</h5>
                 <p id="resumoDocumentoLinha">Aguardando…</p>
             </div>
             <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body p-4 p-lg-5">

              <div class="doc-resumo mb-4" id="blocoRevisaoDocumento">
                  <div class="doc-resumo-item">
                      <span>Processo</span><strong id="reviewProtocolo">—</strong>
                  </div>
                  <div class="doc-resumo-item">
                      <span>Documento</span><strong id="reviewDocumento">—</strong>
                  </div>
                  <div class="doc-resumo-item">
                      <span>Páginas</span><strong id="reviewPaginas">—</strong>
                  </div>
                  <div class="doc-resumo-item">
                      <span>Campos</span><strong id="reviewCampos">—</strong>
                  </div>
              </div>

              <!-- Seletor de modo: lista vertical com hierarquia clara -->
              <p class="fw-bold mb-3" style="font-size:.95rem;">Como este documento será finalizado?</p>
              <div class="modo-lista mb-4" id="modoCards">
                  <label class="modo-card selected" data-modo="assinar">
                      <input type="radio" name="modo_assinatura_radio" value="assinar" checked style="display:none;">
                      <div class="mc-icon"><i class="fas fa-file-signature"></i></div>
                      <div>
                          <div class="mc-title">Assinar eletronicamente</div>
                          <div class="mc-desc">Assinatura avançada com sua chave pessoal e código de verificação pública</div>
                      </div>
                      <i class="fas fa-circle-check mc-check"></i>
                  </label>
                  <label class="modo-card" data-modo="assinar_e_requisitar">
                      <input type="radio" name="modo_assinatura_radio" value="assinar_e_requisitar" style="display:none;">
                      <div class="mc-icon"><i class="fas fa-users"></i></div>
                      <div>
                          <div class="mc-title">Assinar e solicitar co-assinatura</div>
                          <div class="mc-desc">Você assina agora e outros servidores são notificados para assinar também</div>
                      </div>
                      <i class="fas fa-circle-check mc-check"></i>
                  </label>
                  <label class="modo-card" data-modo="sem_assinar">
                      <input type="radio" name="modo_assinatura_radio" value="sem_assinar" style="display:none;">
                      <div class="mc-icon"><i class="fas fa-pen-ruler"></i></div>
                      <div>
                          <div class="mc-title">Gerar para assinar à caneta</div>
                          <div class="mc-desc">PDF com linha de assinatura para impressão — sem assinatura eletrônica</div>
                      </div>
                      <i class="fas fa-circle-check mc-check"></i>
                  </label>
              </div>

              <!-- Pessoa que aparecerá na linha física (apenas modo manual) -->
              <div id="painelAssinanteManual" style="display:none;background:#fffdf5;border:1px solid #fde68a;border-radius:12px;padding:14px;margin-bottom:16px;">
                  <label class="fw-semibold" style="font-size:.85rem;margin-bottom:8px;display:block;color:#76520c;">
                      <i class="fas fa-signature me-1"></i> Quem deve aparecer na linha de assinatura?
                  </label>
                  <div class="manual-signers">
                      <label class="manual-signer-option<?= $tipoAssinanteManualPadrao === 'atual' ? ' selected' : '' ?>">
                          <input type="radio" name="assinante_manual_tipo" value="atual"
                                 <?= $tipoAssinanteManualPadrao === 'atual' ? 'checked' : '' ?>>
                          <span>
                              <strong>Usuário atual</strong>
                              <small><?= htmlspecialchars($adminManualAtual['nome']) ?><br><?= htmlspecialchars($adminManualAtual['cargo']) ?></small>
                          </span>
                      </label>
                      <label class="manual-signer-option<?= $tipoAssinanteManualPadrao === 'secretario' ? ' selected' : '' ?><?= $secretarioManual ? '' : ' disabled' ?>">
                          <input type="radio" name="assinante_manual_tipo" value="secretario"
                                 <?= $tipoAssinanteManualPadrao === 'secretario' ? 'checked' : '' ?>
                                 <?= $secretarioManual ? '' : 'disabled' ?>>
                          <span>
                              <strong>Secretário</strong>
                              <small><?= $secretarioManual
                                  ? htmlspecialchars($secretarioManual['nome']) . '<br>' . htmlspecialchars($secretarioManual['cargo'])
                                  : htmlspecialchars($erroSecretarioManual) ?></small>
                          </span>
                      </label>
                      <label class="manual-signer-option">
                          <input type="radio" name="assinante_manual_tipo" value="personalizado">
                          <span>
                              <strong>Outra pessoa</strong>
                              <small>Informe manualmente o nome e o cargo</small>
                          </span>
                      </label>
                  </div>
                  <div id="camposAssinantePersonalizado" class="row g-2 mt-1" style="display:none;">
                      <div class="col-md-7">
                          <label for="assinanteManualNome" class="form-label mb-1" style="font-size:.75rem;">Nome completo</label>
                          <input type="text" id="assinanteManualNome" class="form-control form-control-sm" maxlength="255"
                                 placeholder="Nome de quem assinará">
                      </div>
                      <div class="col-md-5">
                          <label for="assinanteManualCargo" class="form-label mb-1" style="font-size:.75rem;">Cargo</label>
                          <input type="text" id="assinanteManualCargo" class="form-control form-control-sm" maxlength="100"
                                 placeholder="Ex.: Secretário Municipal">
                      </div>
                  </div>
                  <div class="text-muted mt-2" style="font-size:.7rem;">
                      O sistema registra separadamente quem gerou o PDF.
                  </div>
              </div>

              <!-- Painel co-assinatura (apenas modo assinar_e_requisitar) -->
              <div id="painelCoAssinaturaEditor" style="display:none;background:#f3faf6;border:1px solid #bbf0d4;border-radius:12px;padding:14px;margin-bottom:16px;">
                  <label class="fw-semibold" style="font-size:.85rem;margin-bottom:8px;display:block;color:var(--sema-green);">
                      <i class="fas fa-user-plus me-1"></i> Quem mais vai assinar?
                      <span class="text-muted fw-normal" style="font-size:.75rem;">selecione um ou mais servidores</span>
                  </label>
                  <div id="coassListaDestinatarios" class="coass-grid">
                      <?php
                      $adminLogado = $_SESSION['admin_id'] ?? 0;
                      $stmtAdminsEditor = $pdo->prepare("SELECT id, nome, nivel FROM administradores WHERE ativo = 1 AND id != ? ORDER BY nome");
                      $stmtAdminsEditor->execute([$adminLogado]);
                      $adminsLista = $stmtAdminsEditor->fetchAll();
                      foreach ($adminsLista as $adm):
                          $inicial = strtoupper(mb_substr(trim($adm['nome']), 0, 1)); ?>
                          <label class="coass-card" data-coass>
                              <input type="checkbox" class="coass-destinatario" value="<?= $adm['id'] ?>">
                              <span class="cc-av"><?= htmlspecialchars($inicial) ?></span>
                              <span>
                                  <span class="cc-nome d-block"><?= htmlspecialchars($adm['nome']) ?></span>
                                  <span class="cc-nivel"><?= htmlspecialchars(ucfirst(str_replace('_',' ',$adm['nivel']))) ?></span>
                              </span>
                              <span class="cc-check"><i class="fas fa-check"></i></span>
                          </label>
                      <?php endforeach; ?>
                  </div>
                  <textarea id="coassMensagem" class="form-control form-control-sm mt-2" rows="2"
                            placeholder="Mensagem para os destinatários (opcional)..."
                            style="font-size:.82rem;resize:none;"></textarea>
              </div>

              <!-- Confirmação por senha (modos digitais) -->
              <div id="blocoPin" class="pin-box" style="display:none;">
                  <div class="pin-box-label"><i class="fas fa-lock"></i>
                      <span id="credencialLabel"><?= $adminTemChave ? 'PIN de assinatura' : 'Senha de acesso' ?></span>
                  </div>
                  <input type="password" id="pinAssinatura" class="form-control" maxlength="128"
                         autocomplete="<?= $adminTemChave ? 'off' : 'current-password' ?>"
                         placeholder="<?= $adminTemChave ? 'Digite seu PIN pessoal de assinatura' : 'Digite sua senha de acesso' ?>">
                  <div class="pin-box-hint" id="credencialHint">
                      <i class="fas fa-shield-halved me-1"></i><?= $adminTemChave
                          ? 'O PIN desbloqueia sua chave criptográfica pessoal.'
                          : 'A senha confirma que esta assinatura foi feita por você.' ?>
                  </div>
              </div>

              <!-- Primeira configuração de PIN (exibido quando o admin ainda não tem chave) -->
              <div id="blocoPinSetup" style="display:none;background:#f0f7f3;border:1px solid #bbf0d4;border-radius:12px;padding:14px;margin-bottom:16px;">
                  <div class="fw-bold mb-1" style="font-size:.88rem;color:var(--sema-green);">
                      <i class="fas fa-shield-halved me-1"></i> Configure sua chave de assinatura
                  </div>
                  <p class="text-muted mb-3" style="font-size:.78rem;">
                      É a primeira vez que você assina. Crie um PIN pessoal (mínimo 6 caracteres): ele cifra sua chave criptográfica exclusiva.
                      Sem o seu PIN, ninguém, nem mesmo o sistema, consegue assinar em seu nome. Guarde-o com segurança.
                  </p>
                  <div class="row g-2">
                      <div class="col-6">
                          <input type="password" id="pinNovo" class="form-control" maxlength="64"
                                 autocomplete="new-password" placeholder="Criar PIN">
                      </div>
                      <div class="col-6">
                          <input type="password" id="pinNovoConfirma" class="form-control" maxlength="64"
                                 autocomplete="new-password" placeholder="Confirmar PIN">
                      </div>
                  </div>
              </div>

              <form id="formCheckout">
                  <!-- Diretrizes (só para modos com assinatura digital) -->
                  <div id="blocoDiretrizes">
                      <label class="aceite-box" id="aceiteDiretrizes" for="checkDiretrizes">
                          <input class="form-check-input shadow-none flex-shrink-0" type="checkbox" id="checkDiretrizes"
                                 style="margin-top:2px;">
                          <span style="font-size:.84rem;cursor:pointer;">
                              Li e aceito as
                              <a href="../diretrizes_assinatura.php" target="_blank" class="fw-bold text-decoration-none" style="color:var(--sema-green);">diretrizes de responsabilidade legal <i class="fas fa-arrow-up-right-from-square" style="font-size:.65rem;"></i></a>
                              da assinatura eletrônica <span class="text-danger">*</span>
                          </span>
                      </label>
                  </div>

                  <div class="form-check ms-1 mb-3">
                      <input class="form-check-input" type="checkbox" id="checkDownload" checked>
                      <label class="form-check-label text-muted" for="checkDownload" style="font-size:.84rem;">
                          Baixar o PDF automaticamente após gerar
                      </label>
                  </div>

                  <div class="signature-confirm-footer">
                      <button type="button" class="btn btn-light fw-medium px-4 border"
                              data-bs-dismiss="modal">Voltar ao documento</button>
                      <button type="button" class="btn btn-sema fw-bold px-5"
                              id="btnAssinarFinal" onclick="finalizarAssinatura()">
                          <i class="fas fa-check-circle me-2"></i> <span id="btnAssinarLabel">Assinar documento</span>
                      </button>
                  </div>
              </form>
          </div>
        </div>
      </div>
    </div>

    <!-- Prévia fiel: o próprio PDF final, página por página -->
    <div class="modal fade" id="modalPreviewPdf" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-fullscreen-xl-down pdf-preview-dialog">
        <div class="modal-content border-0 shadow-lg rounded-4 pdf-preview-modal">
          <div class="pdf-preview-head">
            <div class="pdf-preview-head-icon"><i class="fas fa-file-pdf"></i></div>
            <div class="pdf-preview-head-copy">
              <strong>Pré-visualização do documento</strong>
              <span>Confira cada folha separadamente. A assinatura aparece somente na última página.</span>
            </div>
            <span class="pdf-preview-chip"><i class="fas fa-layer-group"></i><span id="previewPageHint">Página por página</span></span>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
          </div>
          <div class="pdf-preview-stage">
            <div class="pdf-preview-loading" id="previewPdfLoading">
              <div class="spinner-border spinner-border-sm" role="status"></div>
              <span>Montando as páginas do PDF…</span>
            </div>
            <iframe id="previewPdfFrame" name="previewPdfFrame" src="about:blank" title="Pré-visualização paginada do documento"></iframe>
          </div>
        </div>
      </div>
    </div>

    <!-- Consulta rápida do processo: dados do protocolo + anexos do requerente -->
    <div class="modal fade" id="modalProcesso" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4 doc-processo-modal">
          <div class="modal-header modal-header-sema px-4 py-3">
            <h5 class="modal-title fw-bold text-sema">
              <i class="fas fa-folder-open me-2"></i> Processo <?= htmlspecialchars($req['protocolo']) ?>
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
          </div>
          <div class="modal-body p-4">
<?php
$blocosProcesso = [
    'Solicitação' => [
        'Serviço'            => $tipoAlvaraLabel,
        'Status'             => ucfirst(str_replace('_', ' ', (string) $req['status'])),
        'Enviado em'         => !empty($req['data_envio']) ? date('d/m/Y H:i', strtotime((string) $req['data_envio'])) : '',
        'Protocolo oficial'  => $req['protocolo_oficial'] ?? '',
    ],
    'Requerente' => [
        'Nome'      => $req['requerente_nome'] ?? '',
        'CPF/CNPJ'  => $req['requerente_cpf_cnpj'] ?? '',
        'E-mail'    => $req['requerente_email'] ?? '',
        'Telefone'  => $req['requerente_telefone'] ?? '',
    ],
    'Proprietário' => [
        'Nome'     => $req['proprietario_nome'] ?? '',
        'CPF/CNPJ' => $req['proprietario_cpf_cnpj'] ?? '',
    ],
    'Imóvel' => [
        'Endereço'              => $req['endereco_objetivo'] ?? '',
        'Cadastro imobiliário'  => $req['cadastro_imobiliario'] ?? '',
        'Área a construir'      => $req['area_construcao'] ?? '',
        'Área construída'       => $req['area_construida'] ?? '',
        'Área do lote'          => $req['area_lote'] ?? '',
        'Tipo de edificação'    => $req['tipo_edificacao'] ?? '',
        'Pavimentos'            => $req['numero_pavimentos'] ?? '',
    ],
    'Responsável técnico' => [
        'Nome'      => $req['responsavel_tecnico_nome'] ?? '',
        'Registro'  => $req['responsavel_tecnico_registro'] ?? '',
        'Documento' => trim(($req['responsavel_tecnico_tipo_documento'] ?? '') . ' ' . ($req['responsavel_tecnico_numero'] ?? '')),
        'E-mail'    => $req['responsavel_tecnico_email'] ?? '',
        'Telefone'  => $req['responsavel_tecnico_telefone'] ?? '',
    ],
];
foreach ($blocosProcesso as $titulo => $linhas):
    $linhas = array_filter($linhas, static fn($v) => trim((string) $v) !== '');
    if (!$linhas) continue; ?>
            <div class="doc-processo-bloco">
              <div class="doc-processo-bloco-titulo"><?= htmlspecialchars($titulo) ?></div>
              <dl class="doc-processo-lista">
                <?php foreach ($linhas as $rotulo => $valor): ?>
                  <dt><?= htmlspecialchars($rotulo) ?></dt>
                  <dd><?= htmlspecialchars((string) $valor) ?></dd>
                <?php endforeach; ?>
              </dl>
            </div>
<?php endforeach; ?>

<?php $especificacaoProcesso = trim((string) ($req['especificacao'] ?? '')); ?>
<?php if ($especificacaoProcesso !== ''): ?>
            <div class="doc-processo-bloco">
              <div class="doc-processo-bloco-titulo">Especificação</div>
              <p class="doc-processo-texto"><?= nl2br(htmlspecialchars($especificacaoProcesso)) ?></p>
            </div>
<?php endif; ?>

<?php $observacoesProcesso = trim((string) ($req['observacoes'] ?? '')); ?>
<?php if ($observacoesProcesso !== ''): ?>
            <div class="doc-processo-bloco">
              <div class="doc-processo-bloco-titulo">Observações do requerente</div>
              <p class="doc-processo-texto"><?= nl2br(htmlspecialchars($observacoesProcesso)) ?></p>
            </div>
<?php endif; ?>

            <div class="doc-processo-bloco">
              <div class="doc-processo-bloco-titulo">
                Documentos anexados
                <span class="doc-processo-contador escuro"><?= count($documentosProcesso) ?></span>
              </div>
<?php if (!$documentosProcesso): ?>
              <p class="doc-processo-vazio">Nenhum documento anexado a este protocolo.</p>
<?php else: ?>
              <ul class="doc-processo-anexos">
<?php foreach ($documentosProcesso as $anexo):
        $naoEnviado = ($anexo['tipo_arquivo'] ?? '') === 'opcional_nao_enviado';
        $ehPdf = strpos((string) ($anexo['tipo_arquivo'] ?? ''), 'pdf') !== false; ?>
                <li class="<?= $naoEnviado ? 'nao-enviado' : '' ?>">
                  <i class="fas <?= $naoEnviado ? 'fa-minus-circle' : ($ehPdf ? 'fa-file-pdf' : 'fa-file') ?>"></i>
                  <span class="doc-processo-anexo-info">
                    <strong><?= htmlspecialchars($rotuloCampoDocumento((string) $anexo['campo_formulario'])) ?></strong>
                    <small><?= $naoEnviado
                        ? 'Marcado como não necessário pelo requerente'
                        : htmlspecialchars((string) $anexo['nome_original']) . ' · ' . $formatarTamanho($anexo['tamanho']) ?></small>
                  </span>
<?php if (!$naoEnviado): ?>
                  <a href="../../uploads/<?= htmlspecialchars(ltrim((string) $anexo['caminho'], '/\\')) ?>"
                     target="_blank" rel="noopener" class="doc-processo-abrir" title="Abrir em nova aba">
                    <i class="fas fa-arrow-up-right-from-square"></i>
                  </a>
<?php endif; ?>
                </li>
<?php endforeach; ?>
              </ul>
<?php endif; ?>
            </div>
          </div>
          <div class="modal-footer border-0 px-4 pb-4 pt-0">
            <a href="../visualizar_requerimento.php?id=<?= (int) $requerimento_id ?>" target="_blank" rel="noopener"
               class="btn btn-outline-success fw-medium">
              <i class="fas fa-arrow-up-right-from-square me-1"></i> Abrir o processo completo
            </a>
            <button type="button" class="btn btn-light border fw-medium" data-bs-dismiss="modal">Fechar</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Salvar Template -->
    <div class="modal fade" id="modalSalvarTemplate" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
          <div class="modal-header modal-header-sema px-4 py-3">
            <h5 class="modal-title fw-bold text-sema">
              <i class="fas fa-bookmark me-2"></i> Salvar como Template
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body p-4">
            <ul class="nav nav-tabs mb-3" id="tabsSalvarTemplate">
              <li class="nav-item">
                <button class="nav-link active fw-semibold" data-bs-toggle="tab" data-bs-target="#pane-novo-tpl" type="button">
                  <i class="fas fa-plus me-1"></i> Novo Template
                </button>
              </li>
              <li class="nav-item">
                <button class="nav-link fw-semibold" id="tab-substituir-tpl" data-bs-toggle="tab" data-bs-target="#pane-subst-tpl" type="button">
                  <i class="fas fa-arrows-rotate me-1"></i> Substituir Existente
                </button>
              </li>
            </ul>
            <div class="tab-content">
              <!-- Salvar como Novo -->
              <div class="tab-pane fade show active" id="pane-novo-tpl">
                <div class="mb-3">
                  <label class="form-label fw-semibold small">Nome do Template <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="novoTemplateNome" placeholder="Ex: Parecer Padrão Construção">
                </div>
                <div class="mb-3">
                  <label class="form-label fw-semibold small text-muted">Descrição <small>(opcional)</small></label>
                  <textarea class="form-control form-control-sm" id="novoTemplateDesc" rows="2"
                            placeholder="Breve descrição do uso deste template..."></textarea>
                </div>
                <!-- Seletor de Ícone -->
                <div class="mb-3">
                  <label class="form-label fw-semibold small">Ícone</label>
                  <input type="hidden" id="novoTemplateIcone" value="fa-bookmark">
                  <div id="iconPickerGrid" style="display:grid;grid-template-columns:repeat(8,1fr);gap:6px;">
                  </div>
                </div>
                <div class="alert alert-info d-flex align-items-start gap-2 py-2 mb-3" style="font-size:.8rem">
                  <i class="fas fa-circle-info mt-1 flex-shrink-0"></i>
                  <span>Os campos <strong>em negrito</strong> serão preservados como variáveis automáticas.</span>
                </div>
                <button class="btn btn-sema w-100 fw-bold" onclick="salvarTemplate('novo')">
                  <i class="fas fa-save me-2"></i> Salvar Novo Template
                </button>
              </div>
              <!-- Substituir Existente -->
              <div class="tab-pane fade" id="pane-subst-tpl">
                <div class="mb-3">
                  <label class="form-label fw-semibold small">Selecionar Template para Substituir</label>
                  <select class="form-select" id="selectTemplateExistente">
                    <option value="">Carregando seus templates...</option>
                  </select>
                </div>
                <div id="templateVersoesBox" class="mb-3" style="display:none;">
                  <label class="form-label fw-semibold small">Versões anteriores</label>
                  <div id="templateVersoesLista" class="small text-muted"></div>
                </div>
                <div class="alert alert-warning d-flex align-items-start gap-2 py-2 mb-3" style="font-size:.8rem">
                  <i class="fas fa-triangle-exclamation mt-1 flex-shrink-0"></i>
                  <span>O template selecionado será permanentemente substituído pelo conteúdo atual do editor.</span>
                </div>
                <button class="btn btn-warning w-100 fw-bold text-dark" onclick="salvarTemplate('substituir')">
                  <i class="fas fa-arrows-rotate me-2"></i> Substituir Template
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- SweetAlert2 pode ser carregado de forma independente do jQuery -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Paginação visual da folha A4, compartilhada com o editor de denúncias -->
    <script src="<?= rtrim(BASE_URL, '/') ?>/js/editor_paginacao.js"></script>
    <!-- Summernote PRECISA do jQuery, que só está disponível após o footer.php.
         Usamos um carregador dinâmico que aguarda o jQuery estar pronto. -->
    <script>
    (function waitForJQuery() {
        if (typeof window.jQuery === 'undefined') {
            setTimeout(waitForJQuery, 50);
            return;
        }
        var s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js';
        s.onload = function() {
            window._summernoteReady = true;
        };
        document.head.appendChild(s);
    })();
    </script>

    <script>
    const reqId         = <?= $requerimento_id ?>;
    const reqProtocolo  = <?= json_encode($req['protocolo'] ?? '') ?>;
    const templateNome  = <?= json_encode($template) ?>;
    const templateLabel = <?= json_encode($label) ?>;
    const logoSemaUrl   = <?= json_encode(rtrim(BASE_URL, '/') . '/assets/SEMA/PNG/Azul/' . rawurlencode('Logo SEMA Vertical.png')) ?>;
    const adminNome     = <?= json_encode($_SESSION['admin_nome_completo'] ?? $_SESSION['admin_nome'] ?? 'Assinante') ?>;
    const adminCargo    = <?= json_encode($_SESSION['admin_cargo'] ?? 'Administrador(a)') ?>;
    const csrfToken     = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
    const adminTemChave = <?= $adminTemChave ? 'true' : 'false' ?>;
    const adminManualAtual = <?= json_encode($adminManualAtual, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const secretarioManual = <?= json_encode($secretarioManual, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    let currentTemplate = templateNome;
    let currentDraftId  = templateNome.startsWith('db_draft:') ? Number(templateNome.slice(9)) : 0;
    let autosaveTimer   = null;
    let autosaveInFlight = false;

    /* ─── Icon Picker ────────────────────────────────────────── */
    const ICONES_DISPONIVEIS = [
        'fa-bookmark','fa-file-alt','fa-file-signature','fa-clipboard-list',
        'fa-leaf','fa-tree','fa-seedling','fa-globe',
        'fa-hard-hat','fa-building','fa-home','fa-city',
        'fa-gavel','fa-stamp','fa-certificate','fa-scroll',
        'fa-microscope','fa-search','fa-clipboard-check','fa-tasks',
        'fa-bullhorn','fa-flag','fa-star','fa-map-marked-alt',
    ];

    function iniciarIconPicker() {
        const grid  = document.getElementById('iconPickerGrid');
        const input = document.getElementById('novoTemplateIcone');
        if (!grid) return;
        grid.innerHTML = ICONES_DISPONIVEIS.map(ic => `
            <div class="icon-option${ic === input.value ? ' selected' : ''}" data-icon="${ic}" title="${ic.replace('fa-','')}">
                <i class="fas ${ic}"></i>
            </div>`).join('');
        grid.querySelectorAll('.icon-option').forEach(el => {
            el.addEventListener('click', function() {
                grid.querySelectorAll('.icon-option').forEach(x => x.classList.remove('selected'));
                this.classList.add('selected');
                input.value = this.dataset.icon;
            });
        });
    }

    /* ─── Aguardar Summernote estar pronto ─────────────────── */
    function waitForSummernote(cb) {
        if (typeof window.jQuery !== 'undefined' && typeof jQuery.fn.summernote !== 'undefined') {
            cb();
        } else {
            setTimeout(function() { waitForSummernote(cb); }, 80);
        }
    }

    /* ═══════════════════════════════════════════════════════════
       FOLHA A4 E PAGINAÇÃO VISUAL
       A paginação de verdade é do TCPDF (admin/assinatura/gerar_pdf.php).
       Aqui só montamos a folha e deixamos js/editor_paginacao.js desenhar
       onde o corte vai cair. O carimbo de assinatura acompanha a decisão
       do PDF: rodapé da última folha, com folha exclusiva se não couber.
    ═══════════════════════════════════════════════════════════ */
    function gerarHeaderHtml() {
        return `
            <div class="a4-sema-header">
                <div class="header-content">
                    <img src="${logoSemaUrl}" alt="Logo SEMA">
                    <div>
                        <div class="sema-prefeitura">PREFEITURA MUNICIPAL DE PAU DOS FERROS/RN</div>
                        <div class="sema-secretaria">SECRETARIA MUNICIPAL DE MEIO AMBIENTE - SEMA</div>
                    </div>
                </div>
                <div class="header-line"></div>
            </div>`;
    }

    function gerarFooterHtml() {
        return `
            <div class="a4-sema-footer">
                <div class="a4-footer-sign">Assinatura eletrônica de ${escapeHtml(adminNome)}  |  ${escapeHtml(adminCargo)}</div>
                <div class="a4-footer-page" id="visual-page-counter">1 página no PDF</div>
            </div>`;
    }

    function gerarSignatureBadgeHtml() {
        return `
            <div class="a4-signature-badge" id="sigBadge">
                <div class="sig-logo"><img src="${logoSemaUrl}" alt="SEMA"></div>
                <div class="sig-info">
                    <div class="sig-title">DOCUMENTO ASSINADO ELETRONICAMENTE</div>
                    <div class="sig-name">${escapeHtml(adminNome.toUpperCase())}</div>
                    <div class="sig-detail">${escapeHtml(adminCargo)} | dd/mm/aaaa hh:mm</div>
                    <div class="sig-verify">Verifique a autenticidade em: <b>consultar/verificar.php</b></div>
                </div>
            </div>`;
    }

    let _lastTotalPages = 1;

    function montarCanvasMultiPagina() {
        if (document.querySelector('.a4-page-sheet')) return;
        const editingArea = document.querySelector('.note-editing-area');
        if (!editingArea) return;

        const sheet = document.createElement('div');
        sheet.className = 'a4-page-sheet';
        sheet.innerHTML = gerarHeaderHtml();

        editingArea.parentNode.insertBefore(sheet, editingArea);
        sheet.appendChild(editingArea);

        const footerEl = document.createElement('div');
        footerEl.innerHTML = gerarFooterHtml();
        sheet.appendChild(footerEl.firstElementChild);

        sheet.insertAdjacentHTML('beforeend', gerarSignatureBadgeHtml());

        SemaPaginacao.iniciar({
            logoUrl:   logoSemaUrl,
            editavel:  () => document.querySelector('.note-editable'),
            folha:     () => document.querySelector('.a4-page-sheet'),
            badge:     () => document.getElementById('sigBadge'),
            aoAtualizar(total) {
                _lastTotalPages = total;
                const contador = document.getElementById('visual-page-counter');
                if (contador) {
                    contador.textContent = total + ' página' + (total > 1 ? 's' : '') + ' no PDF';
                }
            },
        });
    }


    /* ─── Carregar template ao abrir a página ───────────────── */
    function carregarTemplate() {
        fetch('../parecer_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                'action': 'carregar_template',
                'template': templateNome,
                'requerimento_id': reqId,
                'origem': 'tecnico'
            })
        })
        .then(res => res.json())
        .then(ret => {
            if (ret.success) {
                initEditor(ret.html, templateLabel || ret.nome_rascunho || templateNome);
            } else {
                document.getElementById('editor-loading').innerHTML = `
                <div class="alert alert-danger d-flex align-items-center gap-3 rounded-3 mx-auto" style="max-width:500px">
                    <i class="fas fa-triangle-exclamation fs-4"></i>
                    <div>
                        <strong>Erro ao carregar template</strong>
                        <br><small class="text-muted">${ret.error || 'Erro ao carregar os metadados do processo.'}</small>
                    </div>
                </div>`;
            }
        })
        .catch(err => {
            document.getElementById('editor-loading').innerHTML = `
            <div class="alert alert-danger rounded-3 mx-auto" style="max-width:500px">
                <i class="fas fa-wifi-slash me-2"></i>
                <strong>Falha na conexão com o servidor.</strong>
                <br><small class="text-muted">${err.message || 'Verifique sua conexão e recarregue a página.'}</small>
            </div>`;
        });
    }

    /* ─── Inicializar editor Summernote ────────────────────── */
    function initEditor(html, title) {
        document.getElementById('editor-loading').remove();
        document.getElementById('secao-editor').classList.remove('d-none');
        document.getElementById('editor-title').textContent = title;

        waitForSummernote(function() {
            var $editor = $('#editor-conteudo');

            if ($editor.data('summernote')) {
                $editor.summernote('destroy');
            }

            // Recuperação local para o caso de queda de conexão/aba fechada.
            // Só oferece a restauração quando há uma versão recente diferente.
            try {
                const salvoLocal = JSON.parse(localStorage.getItem('sema_doc_rascunho_' + reqId + '_' + templateNome) || 'null');
                const recente = salvoLocal && (Date.now() - Number(salvoLocal.atualizado_em || 0)) < 7 * 24 * 60 * 60 * 1000;
                if (recente && salvoLocal.html && salvoLocal.html !== html
                    && window.confirm('Encontramos uma edição local mais recente deste documento. Deseja restaurá-la?')) {
                    html = salvoLocal.html;
                }
            } catch (e) {}
            $editor.val(html);

            $editor.summernote({
                focus: true,
                codeviewFilter: false,
                codeviewIframeFilter: false,
                toolbar: [
                    ['style',    ['style']],
                    ['font',     ['bold', 'italic', 'underline', 'clear']],
                    ['fontname', ['fontname']],
                    ['fontsize', ['fontsize']],
                    ['color',    ['color']],
                    ['para',     ['ul', 'ol', 'paragraph']],
                    ['table',    ['table']],
                    ['insert',   ['link']],
                    ['view',     ['codeview', 'fullscreen']]
                ],
                callbacks: {
                    onInit: function() {
                        montarCanvasMultiPagina();
                        montarPainelCampos();
                        iniciarSincronizacaoDocumento();
                        iniciarAutosave();
                    },
                    onChange: function() {
                        sincronizarDoDocumentoParaPainel();
                    }
                }
            });
        });
    }

    /* ─── Painel "Campos do documento" ─────────────────────
       Lê os <span class="var-field" data-var="..."> que o
       ParecerService::aplicarHighlights() deixou no HTML. Nada disso
       vem de uma chamada nova ao servidor: os campos já estão na
       página, só não eram mostrados em lugar nenhum.

       O contador de vazios só aparece se realmente houver algum —
       hoje o preenchimento cobre todos os campos, então o normal é
       o painel não mostrar contador nenhum. */
    const ROTULOS_CAMPO = {
        protocolo_oficial: 'Protocolo oficial',
        numero_documento_ano: 'Número do documento',
        protocolo: 'Protocolo do requerimento',
        nome_requerente: 'Requerente',
        cpf_cnpj_requerente: 'CPF/CNPJ do requerente',
        email_requerente: 'E-mail do requerente',
        telefone_requerente: 'Telefone do requerente',
        nome_proprietario: 'Proprietário',
        cpf_cnpj_proprietario: 'CPF/CNPJ do proprietário',
        nome_interessado: 'Interessado',
        cpf_interessado: 'CPF/CNPJ do interessado',
        endereco_objetivo: 'Endereço da obra',
        tipo_alvara: 'Tipo de alvará',
        ano_atual: 'Ano',
        responsavel_tecnico_nome: 'Responsável técnico',
        responsavel_tecnico_conselho: 'Conselho profissional',
        responsavel_tecnico_registro: 'Registro profissional',
        responsavel_tecnico_numero: 'ART/RRT',
        responsavel_tecnico_rotulo: 'Tipo do documento técnico',
        responsavel_tecnico_tipo_documento: 'Tipo do documento técnico',
        area_construida: 'Área construída',
        area: 'Área',
        area_lote: 'Área do lote',
        area_total_terreno: 'Área da porção maior',
        desmembramento_area_lotes: 'Área total desmembrada',
        area_remanescente: 'Área remanescente',
        desmembramento_lotes_numeros: 'Lotes a desmembrar',
        cadastro_imobiliario: 'Cadastro imobiliário',
        especificacao: 'Especificação',
        detalhes_imovel: 'Detalhes do imóvel',
        inicio_obra: 'Início da obra',
        termino_obra: 'Término da obra',
        alvara_construcao_numero: 'Alvará de construção de origem',
        eng_fiscal_nome: 'Engenheira fiscal',
        eng_fiscal_registro: 'CREA da engenheira fiscal',
        atividade: 'Atividade',
        cnae_descricao: 'CNAE',
        art_numero: 'ART/RRT',
        observacoes: 'Observações',
        data_atual: 'Data de emissão',
    };

    const ORDEM_CAMPO = [
        'protocolo_oficial', 'numero_documento_ano', 'protocolo',
        'nome_proprietario', 'cpf_cnpj_proprietario', 'nome_requerente',
        'cpf_cnpj_requerente', 'endereco_objetivo', 'responsavel_tecnico_nome',
        'responsavel_tecnico_conselho', 'responsavel_tecnico_registro',
        'responsavel_tecnico_rotulo', 'responsavel_tecnico_numero',
        'area_construida', 'area_lote', 'area_total_terreno',
        'desmembramento_area_lotes', 'area_remanescente', 'desmembramento_lotes_numeros',
        'cadastro_imobiliario', 'especificacao',
        'inicio_obra', 'termino_obra', 'alvara_construcao_numero',
        'eng_fiscal_nome', 'eng_fiscal_registro', 'data_atual'
    ];

    const PLACEHOLDERS_VAZIOS = [
        '', 'não informado', 'nao informado', 'a ser informado',
        'a ser informada', '??', '-', '—', 'n/a'
    ];

    function rotuloCampo(chave) {
        if (ROTULOS_CAMPO[chave]) return ROTULOS_CAMPO[chave];
        const texto = String(chave || '').replace(/_/g, ' ').trim();
        return texto.charAt(0).toUpperCase() + texto.slice(1);
    }

    function campoVazio(valor) {
        return PLACEHOLDERS_VAZIOS.includes(String(valor || '').trim().toLowerCase());
    }

    function autosaveStatus(texto, estado) {
        const el = document.getElementById('doc-autosave-status');
        if (!el) return;
        el.className = 'doc-autosave-status' + (estado ? ' ' + estado : '');
        el.innerHTML = estado === 'salvando'
            ? '<i class="fas fa-spinner fa-spin me-1"></i>' + escapeHtml(texto)
            : '<i class="fas fa-cloud me-1"></i>' + escapeHtml(texto);
    }

    function iniciarAutosave() {
        autosaveStatus('Alterações salvas', 'salvo');
    }

    function agendarAutosave() {
        clearTimeout(autosaveTimer);
        autosaveStatus('Salvando…', 'salvando');
        autosaveTimer = setTimeout(salvarRascunho, 900);
    }

    async function salvarRascunho() {
        if (autosaveInFlight) return;
        // Rascunho preserva os spans var-field para que o painel continue
        // editável ao recuperar o documento. A limpeza só acontece no PDF.
        const htmlComPaginacao = (typeof $ !== 'undefined' && $('#editor-conteudo').data('summernote'))
            ? $('#editor-conteudo').summernote('code')
            : document.getElementById('editor-conteudo')?.value || '';
        const html = removerEstruturaPaginacao(htmlComPaginacao);
        if (!html || html.trim() === '') return;

        const dados = {};
        document.querySelectorAll('.doc-campo-input').forEach((input) => {
            dados[input.dataset.campo] = input.value;
        });
        const chaveLocal = 'sema_doc_rascunho_' + reqId + '_' + templateNome;
        const payloadLocal = { html, dados, atualizado_em: Date.now() };
        try { localStorage.setItem(chaveLocal, JSON.stringify(payloadLocal)); } catch (e) {}

        autosaveInFlight = true;
        const body = new URLSearchParams({
            action: 'salvar_rascunho',
            requerimento_id: reqId,
            rascunho_id: currentDraftId,
            nome: templateLabel || templateNome || 'Documento em edição',
            conteudo_html: html,
            dados_json: JSON.stringify(dados),
            csrf_token: csrfToken
        });

        try {
            const res = await fetch('../parecer_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body
            });
            const ret = await res.json();
            if (!ret.success) throw new Error(ret.error || 'Falha ao salvar');
            currentDraftId = Number(ret.rascunho_id || currentDraftId);
            autosaveStatus('Salvo às ' + (ret.salvo_em || '').slice(-8), 'salvo');
        } catch (e) {
            // O espelho local continua disponível mesmo sem conexão.
            autosaveStatus('Salvo localmente · conexão pendente', 'erro');
        } finally {
            autosaveInFlight = false;
        }
    }

    function obterCamposDocumento() {
        const editavel = document.querySelector('.note-editable');
        if (!editavel) return [];
        const unicos = new Map();
        editavel.querySelectorAll('.var-field[data-var]').forEach((el, indice) => {
            const chave = el.dataset.var || ('campo_' + indice);
            el.dataset.campoIdx = chave;
            if (!unicos.has(chave)) unicos.set(chave, el);
        });
        return Array.from(unicos.entries()).sort((a, b) => {
            const ai = ORDEM_CAMPO.indexOf(a[0]);
            const bi = ORDEM_CAMPO.indexOf(b[0]);
            return (ai < 0 ? 999 : ai) - (bi < 0 ? 999 : bi);
        });
    }

    function validarCamposAntesDeAssinar() {
        const pendentes = [];
        document.querySelectorAll('.var-field').forEach((el) => {
            const chave = el.dataset.var || '';
            const valor = (el.textContent || '').trim();
            if (chave && campoVazio(valor) && !pendentes.includes(chave)) pendentes.push(chave);
        });
        return pendentes;
    }

    function validarFormatoCampos() {
        const erros = [];
        document.querySelectorAll('.doc-campo-input').forEach((input) => {
            const chave = input.dataset.campo || '';
            const valor = input.value.trim();
            if (!valor || campoVazio(valor)) return;
            const digitos = valor.replace(/\D/g, '');
            if ((chave.includes('cpf') || chave.includes('cnpj')) && ![11, 14].includes(digitos.length)) {
                erros.push(rotuloCampo(chave) + ': CPF/CNPJ inválido');
            }
            if ((chave.includes('area') || chave.includes('numero_pavimentos')) && !/^[\d.,\s]+(?:m²)?$/i.test(valor)) {
                erros.push(rotuloCampo(chave) + ': informe apenas números');
            }
            if ((chave.includes('inicio') || chave.includes('termino')) && !/^\d{2}\/\d{2}\/\d{4}$/.test(valor)) {
                erros.push(rotuloCampo(chave) + ': use DD/MM/AAAA');
            }
        });
        return erros;
    }

    function montarPainelCampos() {
        const lista = document.getElementById('doc-campos-lista');
        if (!lista) return;
        const campos = obterCamposDocumento();
        if (!campos.length) {
            lista.innerHTML = '<div class="doc-rail-vazio">Este documento não usa campos automáticos.</div>';
            atualizarContadorVazios();
            return;
        }

        lista.innerHTML = campos.map(([chave, el]) => {
            const valor = (el.textContent || '').trim();
            const vazio = campoVazio(valor);
            return `<div class="doc-campo${vazio ? ' vazio' : ''}">
                <label class="doc-campo-rotulo" for="campo-${escapeHtml(chave)}">${escapeHtml(rotuloCampo(chave))}</label>
                <span class="doc-campo-entrada">
                    <input type="text" class="doc-campo-input" id="campo-${escapeHtml(chave)}"
                           data-campo="${escapeHtml(chave)}" value="${vazio ? '' : escapeHtml(valor)}"
                           placeholder="${vazio ? escapeHtml(valor || 'A preencher') : ''}" autocomplete="off">
                    <button type="button" class="doc-campo-ir" data-ir="${escapeHtml(chave)}" title="Mostrar no documento" tabindex="-1">
                        <i class="fas fa-location-crosshairs"></i>
                    </button>
                </span>
            </div>`;
        }).join('');

        lista.querySelectorAll('.doc-campo-input').forEach((input) => {
            input.addEventListener('input', () => aplicarCampo(input.dataset.campo, input.value));
            input.addEventListener('focus', () => irParaCampo(input.dataset.campo, true));
        });
        lista.querySelectorAll('.doc-campo-ir').forEach((btn) => {
            btn.addEventListener('click', () => irParaCampo(btn.dataset.ir, false));
        });
        atualizarContadorVazios();
        iniciarSincronizacaoDocumento();
    }

    function aplicarCampo(chave, valor) {
        const editavel = document.querySelector('.note-editable');
        if (!editavel) return;
        editavel.querySelectorAll('.var-field[data-var="' + CSS.escape(chave) + '"]').forEach((el) => {
            el.textContent = String(valor);
        });
        const linha = document.querySelector('.doc-campo-input[data-campo="' + CSS.escape(chave) + '"]')?.closest('.doc-campo');
        if (linha) linha.classList.toggle('vazio', campoVazio(valor));
        atualizarContadorVazios();
        agendarAutosave();
    }

    function sincronizarPainelCampos() {
        const campos = new Map(obterCamposDocumento());
        document.querySelectorAll('.doc-campo-input').forEach((input) => {
            const alvo = campos.get(input.dataset.campo);
            if (!alvo || document.activeElement === input) return;
            const valorCru = (alvo.textContent || '').replace(/\u200B/g, '');
            const ehVazio = campoVazio(valorCru.trim());
            input.value = ehVazio ? '' : valorCru;
            if (ehVazio) input.placeholder = valorCru.trim() || 'A preencher';
            input.closest('.doc-campo')?.classList.toggle('vazio', ehVazio);
        });
        atualizarContadorVazios();
    }

    function obterVarFieldAtivo() {
        const sel = window.getSelection();
        if (!sel || !sel.rangeCount) return null;
        let node = sel.anchorNode;
        if (!node) return null;
        if (node.nodeType !== Node.ELEMENT_NODE) {
            node = node.parentElement;
        }
        return node ? node.closest('.var-field[data-var]') : null;
    }

    function sincronizarDoDocumentoParaPainel(ev) {
        const editavel = document.querySelector('.note-editable');
        if (!editavel) return;

        let campoAlvo = obterVarFieldAtivo();
        if (!campoAlvo && ev && ev.target && ev.target.closest) {
            campoAlvo = ev.target.closest('.var-field[data-var]');
        }

        if (campoAlvo && campoAlvo.dataset.var) {
            const chave = campoAlvo.dataset.var;
            const textoCru = (campoAlvo.textContent || '').replace(/\u200B/g, '');
            const ehVazio = campoVazio(textoCru.trim());

            // 1. Atualizar o input correspondente no painel lateral
            const input = document.querySelector('.doc-campo-input[data-campo="' + CSS.escape(chave) + '"]');
            if (input && document.activeElement !== input) {
                input.value = ehVazio ? '' : textoCru;
                input.closest('.doc-campo')?.classList.toggle('vazio', ehVazio);
                if (ehVazio) {
                    input.placeholder = textoCru.trim() || 'A preencher';
                }
            }

            // 2. Destacar campo ativo no painel lateral
            document.querySelectorAll('.doc-campo.em-edicao').forEach(el => el.classList.remove('em-edicao'));
            if (input) {
                input.closest('.doc-campo')?.classList.add('em-edicao');
            }

            // 3. Sincronizar outras ocorrências da mesma variável no documento
            editavel.querySelectorAll('.var-field[data-var="' + CSS.escape(chave) + '"]').forEach((el) => {
                if (el !== campoAlvo && el.textContent.replace(/\u200B/g, '') !== textoCru) {
                    el.textContent = textoCru;
                }
            });

            atualizarContadorVazios();
            agendarAutosave();
            return;
        }

        // Se não foi um campo específico ou foi alteração genérica
        sincronizarPainelCampos();
        agendarAutosave();
    }

    function iniciarSincronizacaoDocumento() {
        const editable = document.querySelector('.note-editable');
        if (!editable || editable.dataset.syncInit) return;
        editable.dataset.syncInit = '1';

        // 1. Digitação ou edição direta no documento atualiza a barra lateral em tempo real
        editable.addEventListener('input', function(e) {
            sincronizarDoDocumentoParaPainel(e);
        });
        editable.addEventListener('keyup', function(e) {
            sincronizarDoDocumentoParaPainel(e);
        });

        // 2. Prevenir destruição do span do campo ao apagar todo o texto (Backspace / Delete)
        editable.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' || e.key === 'Delete') {
                const sel = window.getSelection();
                if (!sel || !sel.rangeCount) return;
                const node = sel.anchorNode;
                const span = node && (node.nodeType === Node.ELEMENT_NODE ? node.closest('.var-field[data-var]') : node.parentElement?.closest('.var-field[data-var]'));
                if (span) {
                    const rawText = (span.textContent || '').replace(/\u200B/g, '');
                    const selectedText = sel.toString().replace(/\u200B/g, '');
                    if (rawText.length <= 1 || (selectedText && selectedText.trim() === rawText.trim())) {
                        e.preventDefault();
                        span.innerHTML = '&#8203;';
                        const r = document.createRange();
                        r.setStart(span.firstChild, 1);
                        r.setEnd(span.firstChild, 1);
                        sel.removeAllRanges();
                        sel.addRange(r);
                        sincronizarDoDocumentoParaPainel(e);
                    }
                }
            }
        });

        // 3. Ao clicar ou focar num campo do documento, destacar visualmente no painel lateral
        editable.addEventListener('pointerup', function(e) {
            const campo = e.target.closest?.('.var-field[data-var]');
            document.querySelectorAll('.doc-campo.em-edicao').forEach(el => el.classList.remove('em-edicao'));
            if (campo) {
                const chave = campo.dataset.var;
                const input = document.querySelector('.doc-campo-input[data-campo="' + CSS.escape(chave) + '"]');
                if (input) {
                    const linha = input.closest('.doc-campo');
                    linha?.classList.add('em-edicao');
                    linha?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }
        });

        // 4. Ao colar conteúdo
        editable.addEventListener('paste', function() {
            setTimeout(function() {
                sincronizarDoDocumentoParaPainel();
            }, 50);
        });

        // 5. Ao sair do editor, remover destaque de edição
        editable.addEventListener('blur', function() {
            document.querySelectorAll('.doc-campo.em-edicao').forEach(el => el.classList.remove('em-edicao'));
        });
    }

    function atualizarContadorVazios() {
        const badge = document.getElementById('doc-campos-vazios');
        const nota = document.getElementById('doc-campos-nota');
        if (!badge) return;
        const total = document.querySelectorAll('.doc-campo.vazio').length;
        badge.textContent = total === 1 ? '1 vazio' : total + ' vazios';
        badge.style.display = total ? '' : 'none';
        if (nota) nota.style.display = total ? '' : 'none';
    }

    function irParaCampo(chave, suave) {
        const alvo = document.querySelector('.note-editable .var-field[data-var="' + CSS.escape(chave) + '"]');
        if (!alvo) return;
        alvo.scrollIntoView({ behavior: 'smooth', block: 'center' });
        document.querySelectorAll('.var-field.campo-ativo').forEach((el) => el.classList.remove('campo-ativo'));
        alvo.classList.add('campo-ativo');
        if (!suave) {
            alvo.classList.remove('campo-piscando');
            void alvo.offsetWidth;
            alvo.classList.add('campo-piscando');
            setTimeout(() => alvo.classList.remove('campo-piscando'), 1600);
        }
    }

    function valorCampoDocumento(chave) {
        const input = document.querySelector('.doc-campo-input[data-campo="' + CSS.escape(chave) + '"]');
        if (input) return input.value.replace(/\u200B/g, '').trim();
        return (document.querySelector('.note-editable .var-field[data-var="' + CSS.escape(chave) + '"]')?.textContent || '').replace(/\u200B/g, '').trim();
    }

    /* ─── Conteúdo do editor, limpo dos elementos visuais ──── */
    function removerEstruturaPaginacao(html) {
        return SemaPaginacao.limparHtml(html);
    }

    function obterConteudoLimpo() {
        let html = '';
        if (typeof $ !== 'undefined' && $('#editor-conteudo').data('summernote')) {
            html = $('#editor-conteudo').summernote('code');
        } else {
            html = document.getElementById('editor-conteudo').value;
        }
        html = removerEstruturaPaginacao(html);
        // Spans var-field viram texto puro
        html = html.replace(
            /<span[^>]+class="[^"]*\bvar-field\b[^"]*"[^>]*>((?:(?!<\/span>)[\s\S])*)<\/span>/g,
            '$1'
        );
        // Cores residuais do Summernote
        html = html.replace(
            /(<span[^>]*style="[^"]*)color\s*:\s*(?:rgb\(26\s*,\s*82\s*,\s*118\)|#1a5276)\s*;?/gi,
            '$1'
        );
        html = html.replace(/\u200B/g, '');
        return html;
    }

    function dadosAssinanteManual() {
        const tipo = document.querySelector('input[name="assinante_manual_tipo"]:checked')?.value || 'atual';
        if (tipo === 'secretario') {
            return { tipo, nome: secretarioManual?.nome || '', cargo: secretarioManual?.cargo || '' };
        }
        if (tipo === 'personalizado') {
            return {
                tipo,
                nome: document.getElementById('assinanteManualNome').value.trim(),
                cargo: document.getElementById('assinanteManualCargo').value.trim(),
            };
        }
        return { tipo: 'atual', nome: adminManualAtual.nome || adminNome, cargo: adminManualAtual.cargo || adminCargo };
    }

    function atualizarAssinanteManual() {
        const dados = dadosAssinanteManual();
        document.querySelectorAll('.manual-signer-option').forEach(opcao => {
            opcao.classList.toggle('selected', opcao.querySelector('input')?.checked === true);
        });
        document.getElementById('camposAssinantePersonalizado').style.display = dados.tipo === 'personalizado' ? 'flex' : 'none';
    }

    function validarAssinanteManual(exibirErro = true) {
        const dados = dadosAssinanteManual();
        if (dados.tipo === 'secretario' && !secretarioManual) {
            if (exibirErro) Swal.fire('Secretário indisponível', 'Selecione o usuário atual ou informe outra pessoa.', 'warning');
            return false;
        }
        if (dados.tipo === 'personalizado' && (!dados.nome || !dados.cargo)) {
            if (exibirErro) {
                Swal.fire('Dados incompletos', 'Informe o nome e o cargo da pessoa que assinará.', 'warning');
                document.getElementById(!dados.nome ? 'assinanteManualNome' : 'assinanteManualCargo').focus();
            }
            return false;
        }
        return true;
    }

    document.querySelectorAll('input[name="assinante_manual_tipo"]').forEach(input => {
        input.addEventListener('change', atualizarAssinanteManual);
    });
    document.getElementById('assinanteManualNome').addEventListener('input', atualizarAssinanteManual);
    atualizarAssinanteManual();

    /* ─── Pré-visualizar o PDF REAL (TCPDF) em nova aba ────── */
    function previewPdf() {
        const html = obterConteudoLimpo();
        if (!html || html.trim() === '' || html === '<p><br></p>') {
            Swal.fire('Atenção', 'O documento está vazio.', 'warning');
            return;
        }
        const modoAtivo = document.querySelector('.modo-card.selected')?.dataset.modo ?? 'assinar';
        if (modoAtivo === 'sem_assinar' && !validarAssinanteManual()) return;
        const dadosManual = dadosAssinanteManual();
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '../assinatura/preview_pdf.php';
        form.target = 'previewPdfFrame';
        const campos = {
            conteudo_parecer: html,
            requerimento_id:  reqId,
            modo_assinatura:  modoAtivo,
            csrf_token: csrfToken,
        };
        for (const [k, v] of Object.entries(campos)) {
            const inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = k; inp.value = v;
            form.appendChild(inp);
        }
        const loading = document.getElementById('previewPdfLoading');
        if (loading) loading.classList.remove('loaded');
        const previewFrame = document.getElementById('previewPdfFrame');
        if (previewFrame) previewFrame.dataset.pending = '1';
        const pageHint = document.getElementById('previewPageHint');
        if (pageHint) pageHint.textContent = `${_lastTotalPages} página${_lastTotalPages > 1 ? 's' : ''}`;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalPreviewPdf')).show();
        document.body.appendChild(form);
        form.submit();
        form.remove();
    }

    /* ─── Abrir modal de assinatura ────────────────────────── */
    function abrirModalAssinatura() {
        const htmlContent = obterConteudoLimpo();
        if (!htmlContent || htmlContent.trim() === '' || htmlContent === '<p><br></p>') {
            Swal.fire('Atenção', 'O documento não pode estar vazio.', 'warning');
            return;
        }

        const paginasTexto = `${_lastTotalPages} página${_lastTotalPages > 1 ? 's' : ''}`;
        const totalCampos = document.querySelectorAll('.var-field').length;
        const pendentes = validarCamposAntesDeAssinar().length;
        const campoCampos = document.getElementById('reviewCampos');

        document.getElementById('reviewProtocolo').textContent = reqProtocolo || 'Não informado';
        document.getElementById('reviewDocumento').textContent = templateLabel || templateNome || 'Documento';
        document.getElementById('reviewPaginas').textContent = paginasTexto;
        campoCampos.textContent = !totalCampos
            ? 'Redação livre'
            : (pendentes ? `${pendentes} em branco` : 'Todos preenchidos');
        campoCampos.classList.toggle('pendente', pendentes > 0);
        document.getElementById('resumoDocumentoLinha').textContent =
            `${templateLabel || templateNome || 'Documento'} · ${paginasTexto} · assinatura na última página`;

        const chk = document.getElementById('checkDiretrizes');
        chk.checked = false;
        chk.setCustomValidity('O aceite nas diretrizes é um bloco obrigatório legal.');

        document.getElementById('pinAssinatura').value = '';
        document.getElementById('pinNovo').value = '';
        document.getElementById('pinNovoConfirma').value = '';

        atualizarBlocosPin();
        new bootstrap.Modal(document.getElementById('modalConfirmacao')).show();
    }

    /* Mostra confirmação de senha nos modos digitais */
    function atualizarBlocosPin() {
        const modoAtivo = document.querySelector('.modo-card.selected')?.dataset.modo ?? 'assinar';
        document.getElementById('blocoPin').style.display      = modoAtivo !== 'sem_assinar' ? 'block' : 'none';
        document.getElementById('blocoPinSetup').style.display = 'none';
    }

    /* Feedback visual de aceite não marcado (shake + toast) */
    function sacudirAceite(boxId, msg) {
        const box = document.getElementById(boxId);
        if (box) {
            box.classList.add('shake');
            box.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(() => box.classList.remove('shake'), 500);
        }
        if (typeof Swal !== 'undefined') {
            Swal.fire({ toast:true, position:'top', icon:'warning', title: msg, showConfirmButton:false, timer:2800 });
        }
    }

    /* Cards de co-assinante: o <label> alterna o checkbox nativamente;
       só refletimos o estado na classe .sel via evento change (sem toggle
       manual, que causava duplo-toggle e impedia a seleção). */
    document.addEventListener('change', function(e) {
        if (e.target.matches('.coass-card input[type=checkbox]')) {
            e.target.closest('.coass-card').classList.toggle('sel', e.target.checked);
        }
    });

    /* ─── Listener do checkbox de diretrizes ─────────────── */
    document.getElementById('checkDiretrizes').addEventListener('change', function() {
        this.setCustomValidity(this.checked ? '' : 'O aceite nas diretrizes é obrigatório.');
    });

    /* ─── Seletor de modo ──────────────────────────────────── */
    (function() {
        const cards = document.querySelectorAll('.modo-card');
        const btnLabels = {
            assinar: 'Assinar documento',
            sem_assinar: 'Gerar PDF para assinar',
            assinar_e_requisitar: 'Assinar e solicitar',
        };

        function aplicarModo(modo) {
            document.getElementById('btnAssinarLabel').textContent = btnLabels[modo] || 'Confirmar';

            const isSemAssinar = modo === 'sem_assinar';
            const isRequisitar = modo === 'assinar_e_requisitar';

            document.getElementById('painelCoAssinaturaEditor').style.display = isRequisitar ? 'block' : 'none';
            document.getElementById('painelAssinanteManual').style.display   = isSemAssinar ? 'block' : 'none';
            document.getElementById('blocoDiretrizes').style.display = isSemAssinar ? 'none' : 'block';
            document.getElementById('checkDiretrizes').required      = !isSemAssinar;

            atualizarBlocosPin();
        }

        cards.forEach(card => {
            card.addEventListener('click', (event) => {
                if (card.dataset.disponivel === '0') {
                    event.preventDefault();
                    Swal.fire('Assinatura manual indisponível', card.dataset.indisponivelMsg || 'Revise o cadastro do secretário ativo.', 'warning');
                    return;
                }
                const modo = card.dataset.modo;

                cards.forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');

                aplicarModo(modo);
            });
        });

        // Sincroniza o painel com o card já marcado como "selected" no carregamento
        // (relevante quando a assinatura direta está bloqueada e outro card vem pré-selecionado).
        const cardInicial = document.querySelector('.modo-card.selected');
        if (cardInicial) aplicarModo(cardInicial.dataset.modo);
    })();

    /* ─── Finalizar assinatura ─────────────────────────────── */
    async function finalizarAssinatura() {
        const modoAtivo = document.querySelector('.modo-card.selected')?.dataset.modo ?? 'assinar';
        const isSemAssinar = modoAtivo === 'sem_assinar';
        if (isSemAssinar && !validarAssinanteManual()) return;
        const dadosManual = dadosAssinanteManual();

        // Campo em branco não impede a finalização: quem assina decide. Só
        // pedimos uma confirmação explícita para o caso de ter passado batido.
        const camposPendentes = validarCamposAntesDeAssinar();
        if (camposPendentes.length > 0) {
            const nomes = camposPendentes.slice(0, 6).map(rotuloCampo).join(', ');
            const extra = camposPendentes.length > 6
                ? ` e mais ${camposPendentes.length - 6}` : '';
            const confirmacao = await Swal.fire({
                title: `${camposPendentes.length} campo${camposPendentes.length > 1 ? 's' : ''} em branco`,
                html: `Ficar${camposPendentes.length > 1 ? 'ão' : 'á'} sem preenchimento: <strong>${escapeHtml(nomes)}${extra}</strong>.<br>Deseja continuar mesmo assim?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Continuar assim mesmo',
                cancelButtonText: 'Voltar e preencher',
                confirmButtonColor: '#1c4b36',
            });
            if (!confirmacao.isConfirmed) {
                bootstrap.Modal.getInstance(document.getElementById('modalConfirmacao'))?.hide();
                const primeiro = document.querySelector('.doc-campo.vazio .doc-campo-input');
                if (primeiro) primeiro.focus();
                return;
            }
        }

        const errosFormato = validarFormatoCampos();
        if (errosFormato.length) {
            Swal.fire({ title: 'Revise os dados', html: errosFormato.slice(0, 5).map(escapeHtml).join('<br>'), icon: 'warning' });
            return;
        }

        // Garante que a última edição também esteja preservada antes de abrir
        // o fluxo irreversível de assinatura.
        await salvarRascunho();

        // O aceite das diretrizes é exigência legal da assinatura eletrônica;
        // o modo manual não assina nada eletronicamente, então não se aplica.
        if (!isSemAssinar && !document.getElementById('checkDiretrizes').checked) {
            sacudirAceite('aceiteDiretrizes', 'Confirme que leu e aceita as diretrizes de assinatura.');
            return;
        }

        // Confirmação de identidade por senha
        let pinParaAssinar = '';
        if (!isSemAssinar) {
            pinParaAssinar = document.getElementById('pinAssinatura').value;
            if (!pinParaAssinar) {
                const box = document.getElementById('blocoPin');
                box.classList.add('shake');
                box.scrollIntoView({ behavior: 'smooth', block: 'center' });
                setTimeout(() => box.classList.remove('shake'), 500);
                document.getElementById('pinAssinatura').focus();
                const credencialNome = adminTemChave ? 'seu PIN de assinatura' : 'sua senha de acesso';
                Swal.fire({ toast:true, position:'top', icon:'warning', title:'Digite ' + credencialNome + ' para confirmar', showConfirmButton:false, timer:2800 });
                return;
            }
        }

        // Co-assinatura: exige pelo menos um destinatário marcado
        let destinatarios = [];
        if (modoAtivo === 'assinar_e_requisitar') {
            destinatarios = Array.from(document.querySelectorAll('.coass-destinatario:checked')).map(c => c.value);
            if (destinatarios.length === 0) {
                Swal.fire('Atenção', 'Marque pelo menos um servidor para co-assinar.', 'warning');
                return;
            }
        }

        const btn = document.getElementById('btnAssinarFinal');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Processando...';

        const conteudoHtml = obterConteudoLimpo();

        const fazDownload = document.getElementById('checkDownload').checked;
        const fd = new FormData();
        fd.append('conteudo_parecer', conteudoHtml);
        fd.append('requerimento_id',  reqId);
        fd.append('salvar_banco',     'true');
        fd.append('template_salvo',   templateNome);
        fd.append('download',         fazDownload);
        fd.append('modo_assinatura',  modoAtivo);
        fd.append('assinatura_manual_tipo', dadosManual.tipo);
        fd.append('assinatura_manual_nome', dadosManual.nome);
        fd.append('assinatura_manual_cargo', dadosManual.cargo);
        fd.append('pin_assinatura',   pinParaAssinar);
        fd.append('csrf_token',       csrfToken);
        fd.append('numero_documento', valorCampoDocumento('numero_documento_ano'));
        if (modoAtivo === 'assinar_e_requisitar') {
            destinatarios.forEach(d => fd.append('coassinatura_destinatarios[]', d));
            fd.append('coassinatura_mensagem', document.getElementById('coassMensagem').value);
        }

        fetch('../assinatura/processa_assinatura.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(async res => {
            const tipo = res.headers.get('content-type') || '';
            if (!tipo.includes('application/json')) {
                throw new Error('O servidor retornou uma resposta inválida.');
            }
            const dados = await res.json();
            dados._httpStatus = res.status;
            return dados;
        })
        .then(ret => {
            btn.disabled = false;
            btn.innerHTML = `<i class="fas fa-check-circle me-2"></i> <span id="btnAssinarLabel">${document.getElementById('btnAssinarLabel')?.textContent || 'Confirmar'}</span>`;

            if (ret.success) {
                bootstrap.Modal.getInstance(document.getElementById('modalConfirmacao')).hide();
                const swalTitle = isSemAssinar ? 'Documento Gerado' : 'Assinado com Sucesso';
                const swalText  = isSemAssinar
                    ? 'Documento gerado com a linha de assinatura de ' + dadosManual.nome + '. Lembre-se de coletar a assinatura física.'
                    : 'Documento assinado eletronicamente e registrado no processo. O QR code de verificação está impresso no documento.';
                Swal.fire({
                    title: swalTitle,
                    text: swalText,
                    icon: 'success',
                    timer: 3200,
                    showConfirmButton: false
                }).then(() => {
                    if (fazDownload && ret.url_pdf) {
                        const a = document.createElement('a');
                        a.href = '../' + ret.url_pdf;
                        a.download = ret.nome_arquivo || 'Documento_Assinado.pdf';
                        document.body.appendChild(a);
                        a.click();
                        a.remove();
                    }
                    setTimeout(() => {
                        window.location.href = '../visualizar_requerimento.php?id=' + reqId;
                    }, 500);
                });
            } else if (ret.code === 'session_expired') {
                Swal.fire({
                    title: 'Sessão encerrada',
                    text: ret.error || 'Entre novamente para continuar.',
                    icon: 'warning',
                    confirmButtonText: 'Entrar novamente'
                }).then(() => {
                    const retorno = encodeURIComponent(window.location.pathname + window.location.search);
                    window.location.href = '../login.php?redirect=' + retorno;
                });
            } else if (ret.code === 'credential_invalid' || ret.code === 'credential_required') {
                Swal.fire('Credencial incorreta', ret.error || 'A credencial informada não confere. Tente novamente.', 'error');
                document.getElementById('pinAssinatura').value = '';
                document.getElementById('pinAssinatura').focus();
            } else {
                Swal.fire('Não foi possível concluir', ret.error || 'Não foi possível registrar o documento.', 'error');
            }
        })
        .catch((erro) => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check-circle me-2"></i> Confirmar Assinatura Técnica';
            Swal.fire('Falha de comunicação', erro.message || 'Não foi possível comunicar com o servidor.', 'error');
        });
    }

    /* ─── Consulta rápida do processo ─────────────────────── */
    function abrirModalProcesso() {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalProcesso')).show();
    }

    /* ─── Abrir modal Salvar Template ─────────────────────── */
    function abrirModalSalvarTemplate() {
        document.getElementById('novoTemplateNome').value = '';
        document.getElementById('novoTemplateDesc').value = '';
        document.getElementById('novoTemplateIcone').value = 'fa-bookmark';
        iniciarIconPicker();
        carregarTemplatesParaModal();
        new bootstrap.Modal(document.getElementById('modalSalvarTemplate')).show();
    }

    /* ─── Carregar templates do usuário no dropdown ────────── */
    function carregarTemplatesParaModal() {
        const sel = document.getElementById('selectTemplateExistente');
        document.getElementById('templateVersoesBox').style.display = 'none';
        sel.innerHTML = '<option value="">Carregando...</option>';
        fetch('../parecer_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'listar_templates_usuario' })
        })
        .then(r => r.json())
        .then(ret => {
            if (ret.success && ret.templates && ret.templates.length > 0) {
                sel.innerHTML = ret.templates.map(t =>
                    `<option value="${t.id}">${escapeHtml(t.nome)}</option>`
                ).join('');
                sel.dispatchEvent(new Event('change'));
            } else {
                sel.innerHTML = '<option value="">Nenhum template personalizado ainda</option>';
            }
        })
        .catch(() => {
            sel.innerHTML = '<option value="">Erro ao carregar templates</option>';
        });
    }

    document.getElementById('selectTemplateExistente')?.addEventListener('change', function() {
        const id = this.value;
        const box = document.getElementById('templateVersoesBox');
        const lista = document.getElementById('templateVersoesLista');
        if (!id) { box.style.display = 'none'; return; }
        box.style.display = '';
        lista.innerHTML = '<span><i class="fas fa-spinner fa-spin me-1"></i>Carregando versões…</span>';
        fetch('../parecer_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'listar_versoes_template', id: id })
        }).then(r => r.json()).then(ret => {
            if (!ret.success) throw new Error(ret.error || 'Não foi possível carregar versões.');
            if (!ret.versoes.length) {
                lista.innerHTML = '<span>Este modelo ainda não possui versões anteriores.</span>';
                return;
            }
            lista.innerHTML = ret.versoes.map(v => `
                <div class="d-flex align-items-center justify-content-between gap-2 border rounded p-2 mb-1">
                    <span>Versão ${v.numero_versao} · ${escapeHtml(v.criado_em || '')}</span>
                    <button type="button" class="btn btn-sm btn-outline-success"
                            onclick="restaurarVersaoTemplate(${id}, ${v.id})">
                        <i class="fas fa-rotate-left me-1"></i>Restaurar
                    </button>
                </div>`).join('');
        }).catch(err => { lista.innerHTML = `<span class="text-danger">${escapeHtml(err.message)}</span>`; });
    });

    function restaurarVersaoTemplate(templateId, versaoId) {
        Swal.fire({
            title: 'Restaurar versão?',
            text: 'A versão atual será preservada no histórico antes da restauração.',
            icon: 'question', showCancelButton: true,
            confirmButtonText: 'Restaurar', cancelButtonText: 'Cancelar'
        }).then(result => {
            if (!result.isConfirmed) return;
            fetch('../parecer_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'restaurar_versao_template', template_id: templateId, versao_id: versaoId, csrf_token: csrfToken })
            }).then(r => r.json()).then(ret => {
                if (!ret.success) throw new Error(ret.error || 'Não foi possível restaurar.');
                Swal.fire({ icon: 'success', title: 'Versão restaurada', timer: 1800, showConfirmButton: false });
                carregarTemplatesParaModal();
            }).catch(err => Swal.fire('Erro', err.message, 'error'));
        });
    }

    /* ─── Salvar template (novo ou substituindo) ──────────── */
    function salvarTemplate(modo) {
        const rawHtmlComPaginacao = (typeof $ !== 'undefined' && $('#editor-conteudo').data('summernote'))
            ? $('#editor-conteudo').summernote('code')
            : document.getElementById('editor-conteudo').value;
        const rawHtml = removerEstruturaPaginacao(rawHtmlComPaginacao);

        if (!rawHtml || rawHtml.trim() === '' || rawHtml === '<p><br></p>') {
            Swal.fire('Atenção', 'O editor está vazio.', 'warning'); return;
        }

        // Converter spans de volta para {{variavel}} para preservar o template
        const templateHtml = rawHtml.replace(
            /<span[^>]+class="var-field"[^>]+data-var="([^"]+)"[^>]*>(?:(?!<\/span>)[\s\S])*?<\/span>/g,
            '{{$1}}'
        );

        const nome  = document.getElementById('novoTemplateNome').value.trim();
        const desc  = document.getElementById('novoTemplateDesc').value.trim();
        const utId  = document.getElementById('selectTemplateExistente').value;

        if (modo === 'novo' && !nome) {
            Swal.fire('Atenção', 'Informe um nome para o template.', 'warning'); return;
        }
        if (modo === 'substituir' && !utId) {
            Swal.fire('Atenção', 'Selecione um template para substituir.', 'warning'); return;
        }

        const icone = document.getElementById('novoTemplateIcone')?.value || 'fa-bookmark';
        const body = new URLSearchParams({
            action:        'salvar_template_usuario',
            conteudo_html: templateHtml,
            template_base: templateNome,
            icone:         icone,
            csrf_token:    csrfToken,
        });
        if (modo === 'novo')       { body.append('nome', nome); body.append('descricao', desc); }
        if (modo === 'substituir') { body.append('id', utId); }

        fetch('../parecer_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        })
        .then(r => r.json())
        .then(ret => {
            if (ret.success) {
                bootstrap.Modal.getInstance(document.getElementById('modalSalvarTemplate')).hide();
                const msg = modo === 'novo'
                    ? `Template "<strong>${escapeHtml(ret.nome)}</strong>" salvo com sucesso.`
                    : 'Template atualizado com sucesso.';
                Swal.fire({ title: 'Template Salvo!', html: msg, icon: 'success', timer: 2200, showConfirmButton: false });
            } else {
                Swal.fire('Erro', ret.error || 'Não foi possível salvar o template.', 'error');
            }
        })
        .catch(() => {
            Swal.fire('Erro', 'Falha na conexão ao salvar template.', 'error');
        });
    }

    /* ─── Utilitários ──────────────────────────────────────── */
    function escapeHtml(str) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str)));
        return d.innerHTML;
    }

    document.addEventListener('DOMContentLoaded', function() {
        const previewFrame = document.getElementById('previewPdfFrame');
        const previewModal = document.getElementById('modalPreviewPdf');
        if (previewFrame) {
            previewFrame.addEventListener('load', function() {
                if (this.dataset.pending !== '1') return;
                delete this.dataset.pending;
                document.getElementById('previewPdfLoading')?.classList.add('loaded');
            });
        }
        if (previewModal) {
            previewModal.addEventListener('hidden.bs.modal', function() {
                if (previewFrame) previewFrame.src = 'about:blank';
            });
        }
        carregarTemplate();
    });
    </script>
<?php include '../footer.php'; ?>
