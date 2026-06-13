<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../conexao.php';
verificaLogin();

$requerimento_id = filter_input(INPUT_GET, 'requerimento_id', FILTER_VALIDATE_INT);
$template        = filter_input(INPUT_GET, 'template', FILTER_DEFAULT);
$label           = filter_input(INPUT_GET, 'label', FILTER_DEFAULT) ?: '';

if (!$requerimento_id || empty($template)) {
    header('Location: selecionar.php' . ($requerimento_id ? '?requerimento_id=' . $requerimento_id : ''));
    exit;
}

$stmt = $pdo->prepare("SELECT protocolo, status FROM requerimentos WHERE id = ?");
$stmt->execute([$requerimento_id]);
$req = $stmt->fetch();
if (!$req) die("Erro: Requerimento não encontrado.");

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
            background: #d0d4da;
        }

        /* ═══════════════════════════════════════════════
           CANVAS CONTÍNUO — "Folha Infinita"
        ═══════════════════════════════════════════════ */
        .a4-outer-wrapper {
            background: #d0d4da;
            padding: 24px 16px 32px;
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
            padding: 6mm var(--a4-margin-lr) 0 var(--a4-margin-lr);
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
            padding: 0 var(--a4-margin-lr) 6mm;
            border-top: 0.5px solid #d2d2d2;
            margin-top: auto;
            flex-shrink: 0;
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
           ASSINATURA DIGITAL — preview ARRASTÁVEL, fiel ao
           bloco do PDF (88mm × 20mm, QR à esquerda, padrão gov.br)
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
            cursor: grab;
            user-select: none;
            touch-action: none;
            display: flex;
            gap: 2.5mm;
            padding: 2mm;
            box-sizing: border-box;
            transition: box-shadow .15s;
        }
        .a4-signature-badge.dragging {
            cursor: grabbing;
            box-shadow: 0 10px 28px rgba(0,0,0,0.3);
            opacity: .92;
        }
        .a4-signature-badge:hover::after {
            content: 'Arraste para reposicionar a assinatura';
            position: absolute;
            top: -26px; left: 50%;
            transform: translateX(-50%);
            background: #1e293b; color: #fff;
            font-size: 10px; font-weight: 600;
            padding: 4px 10px; border-radius: 6px;
            white-space: nowrap;
            pointer-events: none;
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
            padding: 2mm var(--a4-margin-lr) 10mm !important;
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

        /* ═══════════════════════════════════════════════
           MARCADOR DE CORTE DE PÁGINA
           O TCPDF pagina o fluxo contínuo cortando a cada 256mm
           úteis — inclusive NO MEIO de um parágrafo. O marcador é
           um overlay na posição exata do corte: o texto acima fica
           na página N, o texto abaixo vai para a página N+1.
        ═══════════════════════════════════════════════ */
        .note-editable .page-cut {
            position: absolute;
            left: 0; right: 0;
            height: 0;
            pointer-events: none;
            z-index: 12;
        }
        .note-editable .page-cut::before {
            content: '';
            position: absolute;
            left: calc(-1 * var(--a4-margin-lr));
            right: calc(-1 * var(--a4-margin-lr));
            top: 0;
            border-top: 2px dashed #64a3d8;
        }
        /* sombra suave abaixo do corte = "início da próxima folha" */
        .note-editable .page-cut::after {
            content: '';
            position: absolute;
            left: calc(-1 * var(--a4-margin-lr));
            right: calc(-1 * var(--a4-margin-lr));
            top: 2px;
            height: 12px;
            background: linear-gradient(rgba(100, 163, 216, .14), transparent);
        }
        .note-editable .page-cut .pc-label {
            position: absolute;
            right: calc(-1 * var(--a4-margin-lr) + 4px);
            top: -11px;
            background: #1e5a96;
            color: #fff;
            font: 700 9px 'Helvetica Neue', sans-serif;
            padding: 3px 9px;
            border-radius: 10px;
            white-space: nowrap;
            box-shadow: 0 1px 4px rgba(0,0,0,.3);
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
        /* Etiqueta de etapa */
        .etapa-kicker { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color: var(--sema-teal); }

        /* PIN de assinatura — bloco EVIDENCIADO */
        .pin-box {
            display:flex; align-items:center; gap:14px;
            background: linear-gradient(135deg, #f0f9f4, #e7f5ee);
            border: 2px solid var(--sema-green); border-radius:14px;
            padding:16px; margin-bottom:18px;
            box-shadow: 0 4px 16px rgba(28,75,54,.12);
        }
        .pin-box .pin-ic {
            width:46px; height:46px; border-radius:12px; flex-shrink:0;
            background: var(--sema-green); color:#fff;
            display:flex; align-items:center; justify-content:center; font-size:1.2rem;
            box-shadow: 0 4px 10px rgba(28,75,54,.3);
        }
        .pin-box .form-control { border:1.5px solid #bfe3d0; font-weight:600; letter-spacing:.18em; }
        .pin-box .form-control:focus { border-color: var(--sema-green); box-shadow:0 0 0 .2rem rgba(28,75,54,.15); }
        .pin-box-label { font-weight:800; font-size:.9rem; color: var(--sema-green); }
        .pin-box-hint { font-size:.72rem; color:#5b7c6e; }

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
        .aceite-box.warn { border-color:#fde68a; background:#fffdf5; }
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

    <!-- Navegação de Topo -->
    <div class="d-flex align-items-center justify-content-between mb-4 border-bottom pb-3">
        <div class="d-flex align-items-center gap-3">
            <a href="selecionar.php?requerimento_id=<?= $requerimento_id ?>" class="btn btn-sm btn-light border fw-medium px-3 text-secondary">
                <i class="fas fa-arrow-left me-1"></i> Voltar
            </a>
            <div>
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="fas fa-edit me-2" style="color: var(--sema-green)"></i> Editor de Documento
                </h5>
                <small class="text-muted">Edite e assine o documento oficial do processo</small>
            </div>
        </div>
        <span class="badge px-3 py-2 rounded-pill fw-semibold" style="background: #f0fdf4; color: var(--sema-green); border: 1px solid #bbf7d0; font-size: 0.85rem;">
            <i class="fas fa-hashtag me-1"></i><?= htmlspecialchars($req['protocolo']) ?>
        </span>
    </div>

    <!-- Skeleton loader enquanto o template carrega -->
    <div id="editor-loading" class="text-center py-5">
        <div class="spinner-border text-success" role="status"></div>
        <p class="mt-2 text-muted small">Carregando template...</p>
    </div>

    <!-- Seção do editor (oculta até carregar) -->
    <div class="py-0 d-none" id="secao-editor">

        <!-- Barra de ações do editor -->
        <div class="bg-white border rounded-3 shadow-sm px-4 py-3 mb-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-0 fw-bold text-dark" id="editor-title">
                        <i class="fas fa-edit me-2 text-success"></i> Editando Documento
                    </h5>
                    <small class="text-muted" style="font-size:.78rem">
                        Campos <span style="font-weight:700">em negrito</span>
                        são preenchidos automaticamente pelo protocolo.
                    </small>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="selecionar.php?requerimento_id=<?= $requerimento_id ?>" class="btn btn-outline-secondary fw-medium px-3">
                        <i class="fas fa-arrow-left me-1"></i> Voltar
                    </a>
                    <button class="btn btn-outline-success fw-medium px-3" onclick="abrirModalSalvarTemplate()">
                        <i class="fas fa-bookmark me-1"></i> Salvar Template
                    </button>
                    <button class="btn btn-preview fw-medium px-3" onclick="previewPdf()" title="Gera o PDF real (TCPDF) sem assinar nem registrar — o que você vê é exatamente o documento final">
                        <i class="fas fa-eye me-1"></i> Pré-visualizar PDF
                    </button>
                    <button class="btn btn-sema fw-medium px-4" onclick="abrirModalAssinatura()">
                        <i class="fas fa-signature me-2"></i> Assinar e Finalizar
                    </button>
                </div>
            </div>
        </div>

        <!-- Wrapper que simula a "mesa" de trabalho com a página A4 -->
        <div class="a4-outer-wrapper rounded-3">
            <textarea id="editor-conteudo"></textarea>
        </div>

    </div><!-- /secao-editor -->

    <!-- Modal de Confirmação -->
    <div class="modal fade" id="modalConfirmacao" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
          <div class="modal-header modal-header-sema px-4 py-3">
             <h5 class="modal-title fw-bold">
                <i class="fas fa-file-signature me-2"></i> Assinar e Finalizar Documento
             </h5>
             <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body p-4">

              <!-- Seletor de modo: lista vertical com hierarquia clara -->
              <div class="mb-1 etapa-kicker">Etapa 1 de 2</div>
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
                          <div class="mc-title">Gerar com linha para assinatura manual</div>
                          <div class="mc-desc">Sem assinatura eletrônica — o documento será assinado à caneta</div>
                      </div>
                      <i class="fas fa-circle-check mc-check"></i>
                  </label>
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

              <!-- PIN de assinatura (modos digitais) — EVIDENCIADO -->
              <div id="blocoPin" class="pin-box">
                  <div class="pin-ic"><i class="fas fa-key"></i></div>
                  <div class="flex-grow-1">
                      <div class="pin-box-label">PIN de assinatura</div>
                      <input type="password" id="pinAssinatura" class="form-control form-control-lg mt-1" maxlength="64"
                             autocomplete="off" placeholder="Digite seu PIN pessoal">
                      <div class="pin-box-hint mt-1">
                          <i class="fas fa-lock me-1"></i>Protege sua chave individual (RSA-2048). Só você o conhece — é o que garante a validade jurídica.
                      </div>
                  </div>
              </div>

              <!-- Primeira configuração de PIN (exibido quando o admin ainda não tem chave) -->
              <div id="blocoPinSetup" style="display:none;background:#f0f7f3;border:1px solid #bbf0d4;border-radius:12px;padding:14px;margin-bottom:16px;">
                  <div class="fw-bold mb-1" style="font-size:.88rem;color:var(--sema-green);">
                      <i class="fas fa-shield-halved me-1"></i> Configure sua chave de assinatura
                  </div>
                  <p class="text-muted mb-3" style="font-size:.78rem;">
                      É a primeira vez que você assina. Crie um PIN pessoal (mínimo 6 caracteres): ele cifra sua chave criptográfica exclusiva.
                      Sem o seu PIN, ninguém — nem o sistema — consegue assinar em seu nome. Guarde-o com segurança.
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
                  <div class="mb-1 etapa-kicker">Etapa 2 de 2</div>
                  <p class="fw-bold mb-2" style="font-size:.92rem;">Confirmação</p>

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

                  <!-- Confirmação para modo sem_assinar -->
                  <div id="blocoConfirmacaoManual" style="display:none;">
                      <label class="aceite-box warn" id="aceiteManual" for="checkManual">
                          <input class="form-check-input shadow-none flex-shrink-0" type="checkbox" id="checkManual"
                                 style="margin-top:2px;">
                          <span style="font-size:.84rem;cursor:pointer;">
                              Entendo que sem assinatura eletrônica este documento <strong>não pode ser aprovado pelo Secretário</strong> (Setor 3) <span class="text-danger">*</span>
                          </span>
                      </label>
                  </div>

                  <div class="form-check ms-1 mb-3">
                      <input class="form-check-input" type="checkbox" id="checkDownload" checked>
                      <label class="form-check-label text-muted" for="checkDownload" style="font-size:.84rem;">
                          Baixar o PDF automaticamente após gerar
                      </label>
                  </div>

                  <div class="d-grid gap-3 d-md-flex justify-content-md-end pt-3">
                      <button type="button" class="btn btn-light fw-medium px-4 border"
                              data-bs-dismiss="modal">Revisar Documento</button>
                      <button type="button" class="btn btn-sema fw-bold px-5"
                              id="btnAssinarFinal" onclick="finalizarAssinatura()">
                          <i class="fas fa-check-circle me-2"></i> <span id="btnAssinarLabel">Confirmar Assinatura Técnica</span>
                      </button>
                  </div>
              </form>
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
    const templateNome  = <?= json_encode($template) ?>;
    const templateLabel = <?= json_encode($label) ?>;
    const logoSemaUrl   = <?= json_encode(rtrim(BASE_URL, '/') . '/assets/SEMA/PNG/Azul/' . rawurlencode('Logo SEMA Vertical.png')) ?>;
    const adminNome     = <?= json_encode($_SESSION['admin_nome_completo'] ?? $_SESSION['admin_nome'] ?? 'Assinante') ?>;
    const adminCargo    = <?= json_encode($_SESSION['admin_cargo'] ?? 'Administrador(a)') ?>;
    let currentTemplate = templateNome;
    let adminTemChave   = null; // null = ainda não consultado

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
       CANVAS MULTI-PÁGINA (Página Contínua)
       Evita bugs de seleção e digitação mantendo o texto em um 
       bloco único, mas indica visualmente onde o PDF irá cortar.
    ═══════════════════════════════════════════════════════════ */
    // 297mm(total) - 27mm(header) - 14mm(footer) = 256mm
    // TCPDF corta as páginas exatamente nesse limite.
    const PAGE_USABLE_PX = 256 * 3.7795; 

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

    function gerarFooterHtml(totalPages) {
        return `
            <div class="a4-sema-footer">
                <div class="a4-footer-sign">Assinatura eletrônica de ${escapeHtml(adminNome)}  |  ${escapeHtml(adminCargo)}</div>
                <div class="a4-footer-date">O QR code e o código de verificação são gerados na assinatura</div>
                <div class="a4-footer-page" id="visual-page-counter">&mdash; Página 1 de ${totalPages} &mdash;</div>
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

    /* ═══════════════════════════════════════════════════════════
       BADGE DE ASSINATURA ARRASTÁVEL
       A posição é mantida em mm (mesma unidade do TCPDF) relativa
       à ÚLTIMA página. O PDF coloca o bloco exatamente onde o
       usuário soltou no preview.
    ═══════════════════════════════════════════════════════════ */
    const SIG_W_MM = 88, SIG_H_MM = 20;
    // Padrão = inferior-direito, idêntico ao default do gerar_pdf.php
    let sigPos = { x: 210 - 15 - SIG_W_MM, y: 297 - 14 - SIG_H_MM };
    let sigPosCustomizada = false;

    function _sheet()    { return document.querySelector('.a4-page-sheet'); }
    function _editable() { return document.querySelector('.note-editable'); }
    function _badge()    { return document.getElementById('sigBadge'); }
    function pxPerMm()   { const s = _sheet(); return s ? s.getBoundingClientRect().width / 210 : 3.7795; }

    /** Topo (px, relativo à folha) da última página visual. */
    function lastPageTopPx() {
        const ed = _editable(), s = _sheet();
        if (!ed || !s) return 0;
        const gaps = ed.querySelectorAll('.page-gap');
        if (!gaps.length) return 0;
        const g = gaps[gaps.length - 1];
        const sheetTop = s.getBoundingClientRect().top;
        // Conteúdo após o separador começa em y=27mm (header) na página do PDF
        return (g.getBoundingClientRect().bottom - sheetTop) - 27 * pxPerMm();
    }

    function posicionarBadge() {
        const b = _badge(), s = _sheet();
        if (!b || !s) return;
        const k = pxPerMm();
        const topPx = lastPageTopPx() + sigPos.y * k;
        b.style.left   = (sigPos.x * k) + 'px';
        b.style.top    = topPx + 'px';
        b.style.right  = 'auto';
        b.style.bottom = 'auto';
        // Folha precisa ser alta o bastante para conter o badge
        const minH = topPx + SIG_H_MM * k + 14 * k;
        if (s.offsetHeight < minH) s.style.minHeight = minH + 'px';
    }

    function clampSigPos() {
        sigPos.x = Math.max(10, Math.min(sigPos.x, 210 - SIG_W_MM - 10));
        sigPos.y = Math.max(25, Math.min(sigPos.y, 297 - SIG_H_MM - 12));
    }

    function iniciarDragBadge() {
        const b = _badge(), s = _sheet();
        if (!b || !s) return;
        let dragging = false, offX = 0, offY = 0;

        b.addEventListener('pointerdown', function(e) {
            dragging = true;
            b.classList.add('dragging');
            const r = b.getBoundingClientRect();
            offX = e.clientX - r.left;
            offY = e.clientY - r.top;
            b.setPointerCapture(e.pointerId);
            e.preventDefault();
        });
        b.addEventListener('pointermove', function(e) {
            if (!dragging) return;
            const sr = s.getBoundingClientRect();
            const k = pxPerMm();
            sigPos.x = (e.clientX - offX - sr.left) / k;
            sigPos.y = ((e.clientY - offY - sr.top) - lastPageTopPx()) / k;
            clampSigPos();
            sigPosCustomizada = true;
            posicionarBadge();
        });
        b.addEventListener('pointerup', function(e) {
            dragging = false;
            b.classList.remove('dragging');
            b.releasePointerCapture(e.pointerId);
        });
    }

    /**
     * Monta o canvas contínuo
     */
    function montarCanvasMultiPagina() {
        if (document.querySelector('.a4-page-sheet')) return;
        const editingArea = document.querySelector('.note-editing-area');
        if (!editingArea) return;
        const parent = editingArea.parentNode;

        // Container geral de página (folha contínua)
        const sheet = document.createElement('div');
        sheet.className = 'a4-page-sheet';
        
        // Inserir Header
        sheet.innerHTML = gerarHeaderHtml();

        // Mover editing-area para dentro da folha
        parent.insertBefore(sheet, editingArea);
        sheet.appendChild(editingArea);

        // Inserir Footer
        const footerEl = document.createElement('div');
        footerEl.innerHTML = gerarFooterHtml(1);
        sheet.appendChild(footerEl.firstElementChild);

        // Inserir Badge de Assinatura (arrastável, fiel ao PDF)
        sheet.insertAdjacentHTML('beforeend', gerarSignatureBadgeHtml());
        iniciarDragBadge();
        posicionarBadge();

        iniciarMonitorPaginas();
    }

    /**
     * MARCADORES DE CORTE DE PÁGINA
     * O TCPDF pagina o fluxo contínuo: corta a cada 256mm úteis, inclusive no
     * MEIO de um parágrafo (a linha de cima fica na página N, a de baixo vai
     * para a N+1). O marcador é um overlay posicionado exatamente no Y do
     * corte — funciona para qualquer conteúdo, inclusive um parágrafo gigante.
     * Para conferência 100% fiel existe o botão "Pré-visualizar PDF".
     */
    let _lastTotalPages = 1;
    function iniciarMonitorPaginas() {
        const editable = document.querySelector('.note-editable');
        if (!editable) return;

        let _debounceTimer = null;
        let _updating = false;

        const observer = new MutationObserver(function(mutations) {
            if (_updating) return;
            // Ignorar mutações causadas pelos próprios marcadores
            const isOnlyCuts = mutations.every(function(m) {
                return Array.from(m.addedNodes).concat(Array.from(m.removedNodes)).every(function(n) {
                    return n.nodeType === 1 && n.classList && n.classList.contains('page-cut');
                });
            });
            if (isOnlyCuts) return;

            clearTimeout(_debounceTimer);
            _debounceTimer = setTimeout(recalcularPaginas, 200);
        });

        function recalcularPaginas() {
            if (_updating) return;
            _updating = true;
            observer.disconnect();

            editable.querySelectorAll('.page-cut').forEach(function(c) { c.remove(); });

            const cs = getComputedStyle(editable);
            const padTop = parseFloat(cs.paddingTop) || 0;
            const padBottom = parseFloat(cs.paddingBottom) || 0;

            // Altura real do conteúdo, sem os paddings do canvas
            const contentH = editable.scrollHeight - padTop - padBottom;
            const totalPaginas = Math.max(1, Math.ceil(contentH / PAGE_USABLE_PX));

            // Um marcador por corte, na posição exata do fluxo contínuo
            for (let p = 1; p < totalPaginas; p++) {
                const cut = document.createElement('div');
                cut.className = 'page-cut';
                cut.setAttribute('contenteditable', 'false');
                cut.style.top = (padTop + p * PAGE_USABLE_PX) + 'px';
                cut.innerHTML = '<span class="pc-label">fim da pág. ' + p + ' ↓ pág. ' + (p + 1) + '</span>';
                editable.appendChild(cut);
            }

            if (totalPaginas !== _lastTotalPages) {
                const counter = document.getElementById('visual-page-counter');
                if (counter) counter.innerHTML = '&mdash; ' + totalPaginas + ' página' + (totalPaginas > 1 ? 's' : '') + ' no PDF &mdash;';
                _lastTotalPages = totalPaginas;
            }

            // Reposiciona o badge de assinatura na última página
            posicionarBadge();

            _updating = false;
            observer.observe(editable, { childList: true, subtree: true, characterData: true });
        }

        editable.addEventListener('input', function() {
            clearTimeout(_debounceTimer);
            _debounceTimer = setTimeout(recalcularPaginas, 200);
        });

        observer.observe(editable, { childList: true, subtree: true, characterData: true });

        setTimeout(recalcularPaginas, 300);
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
        document.getElementById('editor-title').innerHTML =
            '<i class="fas fa-edit text-success me-2"></i> Editando: <b>' + escapeHtml(title) + '</b>';

        waitForSummernote(function() {
            var $editor = $('#editor-conteudo');

            if ($editor.data('summernote')) {
                $editor.summernote('destroy');
            }
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
                    }
                }
            });
        });
    }

    /* ─── Conteúdo do editor, limpo dos elementos visuais ──── */
    function obterConteudoLimpo() {
        let html = '';
        if (typeof $ !== 'undefined' && $('#editor-conteudo').data('summernote')) {
            html = $('#editor-conteudo').summernote('code');
        } else {
            html = document.getElementById('editor-conteudo').value;
        }
        // Separadores de página do editor (Google Docs style) — nunca vão ao servidor
        html = html.replace(/<div[^>]*class="[^"]*page-(?:cut|gap|break-indicator)[^"]*"[^>]*>[\s\S]*?<\/div>/g, '');
        // Spans var-field viram texto puro
        html = html.replace(
            /<span[^>]+class="var-field"[^>]*>((?:(?!<\/span>)[\s\S])*)<\/span>/g,
            '$1'
        );
        // Cores residuais do Summernote
        html = html.replace(
            /(<span[^>]*style="[^"]*)color\s*:\s*(?:rgb\(26\s*,\s*82\s*,\s*118\)|#1a5276)\s*;?/gi,
            '$1'
        );
        return html;
    }

    /* ─── Pré-visualizar o PDF REAL (TCPDF) em nova aba ────── */
    function previewPdf() {
        const html = obterConteudoLimpo();
        if (!html || html.trim() === '' || html === '<p><br></p>') {
            Swal.fire('Atenção', 'O documento está vazio.', 'warning');
            return;
        }
        const modoAtivo = document.querySelector('.modo-card.selected')?.dataset.modo ?? 'assinar';
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '../assinatura/preview_pdf.php';
        form.target = '_blank';
        const campos = {
            conteudo_parecer: html,
            requerimento_id:  reqId,
            modo_assinatura:  modoAtivo,
            sig_pos_x: sigPosCustomizada ? sigPos.x.toFixed(1) : '',
            sig_pos_y: sigPosCustomizada ? sigPos.y.toFixed(1) : '',
        };
        for (const [k, v] of Object.entries(campos)) {
            const inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = k; inp.value = v;
            form.appendChild(inp);
        }
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

        const chk = document.getElementById('checkDiretrizes');
        chk.checked = false;
        chk.setCustomValidity('O aceite nas diretrizes é um bloco obrigatório legal.');

        document.getElementById('pinAssinatura').value = '';
        document.getElementById('pinNovo').value = '';
        document.getElementById('pinNovoConfirma').value = '';

        // Consulta se o admin já tem chave de assinatura → decide entre
        // pedir o PIN ou exibir o fluxo de primeira configuração
        fetch('../assinatura/chave_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ acao: 'status' })
        })
        .then(r => r.json())
        .then(ret => {
            adminTemChave = !!(ret.success && ret.tem_chave);
            atualizarBlocosPin();
        })
        .catch(() => { adminTemChave = null; atualizarBlocosPin(); });

        new bootstrap.Modal(document.getElementById('modalConfirmacao')).show();
    }

    /* Exibe o bloco certo de PIN conforme modo selecionado + estado da chave */
    function atualizarBlocosPin() {
        const modoAtivo  = document.querySelector('.modo-card.selected')?.dataset.modo ?? 'assinar';
        const ehDigital  = modoAtivo !== 'sem_assinar';
        document.getElementById('blocoPin').style.display      = (ehDigital && adminTemChave !== false) ? 'flex' : 'none';
        document.getElementById('blocoPinSetup').style.display = (ehDigital && adminTemChave === false) ? 'block' : 'none';
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

    /* Cards de co-assinante: clique alterna o checkbox + estilo .sel */
    document.addEventListener('click', function(e) {
        const card = e.target.closest('.coass-card');
        if (!card) return;
        const cb = card.querySelector('input[type=checkbox]');
        // se clicou direto no checkbox, ele já alterna; senão alternamos nós
        if (e.target !== cb) { cb.checked = !cb.checked; }
        card.classList.toggle('sel', cb.checked);
    });

    /* ─── Listener do checkbox de diretrizes ─────────────── */
    document.getElementById('checkDiretrizes').addEventListener('change', function() {
        this.setCustomValidity(this.checked ? '' : 'O aceite nas diretrizes é obrigatório.');
    });

    /* ─── Seletor de modo ──────────────────────────────────── */
    (function() {
        const cards = document.querySelectorAll('.modo-card');
        const btnLabels = {
            assinar: 'Assinar Documento',
            sem_assinar: 'Gerar Documento',
            assinar_e_requisitar: 'Assinar e Solicitar',
        };

        cards.forEach(card => {
            card.addEventListener('click', () => {
                const modo = card.dataset.modo;

                cards.forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');

                document.getElementById('btnAssinarLabel').textContent = btnLabels[modo] || 'Confirmar';

                const isSemAssinar = modo === 'sem_assinar';
                const isRequisitar = modo === 'assinar_e_requisitar';

                document.getElementById('painelCoAssinaturaEditor').style.display = isRequisitar ? 'block' : 'none';
                document.getElementById('blocoDiretrizes').style.display        = isSemAssinar ? 'none' : 'block';
                document.getElementById('blocoConfirmacaoManual').style.display = isSemAssinar ? 'block' : 'none';

                document.getElementById('checkDiretrizes').required = !isSemAssinar;
                document.getElementById('checkManual').required     = isSemAssinar;

                atualizarBlocosPin();
            });
        });
    })();

    /* ─── Finalizar assinatura ─────────────────────────────── */
    async function finalizarAssinatura() {
        const modoAtivo = document.querySelector('.modo-card.selected')?.dataset.modo ?? 'assinar';
        const isSemAssinar = modoAtivo === 'sem_assinar';

        // Validação dos checkboxes conforme modo (com feedback visual)
        const checkDiretrizes = document.getElementById('checkDiretrizes');
        const checkManual     = document.getElementById('checkManual');
        if (!isSemAssinar && !checkDiretrizes.checked) {
            sacudirAceite('aceiteDiretrizes', 'Confirme que leu e aceita as diretrizes de assinatura.');
            return;
        }
        if (isSemAssinar && !checkManual.checked) {
            sacudirAceite('aceiteManual', 'Confirme que entendeu a observação sobre a assinatura manual.');
            return;
        }

        // Modo digital: garante chave + PIN antes de prosseguir
        let pinParaAssinar = '';
        if (!isSemAssinar) {
            if (adminTemChave === false) {
                // Primeira configuração: cria a chave com o PIN escolhido
                const p1 = document.getElementById('pinNovo').value;
                const p2 = document.getElementById('pinNovoConfirma').value;
                if (p1.length < 6) {
                    Swal.fire('PIN muito curto', 'O PIN de assinatura deve ter no mínimo 6 caracteres.', 'warning');
                    return;
                }
                if (p1 !== p2) {
                    Swal.fire('PINs diferentes', 'Os dois campos de PIN não coincidem.', 'warning');
                    return;
                }
                try {
                    const r = await fetch('../assinatura/chave_handler.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ acao: 'criar', pin: p1, pin_confirmacao: p2 })
                    });
                    const ret = await r.json();
                    if (!ret.success) {
                        Swal.fire('Erro', ret.error || 'Falha ao criar sua chave de assinatura.', 'error');
                        return;
                    }
                    adminTemChave = true;
                    pinParaAssinar = p1;
                } catch (e) {
                    Swal.fire('Erro', 'Falha de conexão ao criar a chave de assinatura.', 'error');
                    return;
                }
            } else {
                pinParaAssinar = document.getElementById('pinAssinatura').value;
                if (!pinParaAssinar) {
                    const box = document.getElementById('blocoPin');
                    box.classList.add('shake');
                    box.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    setTimeout(() => box.classList.remove('shake'), 500);
                    document.getElementById('pinAssinatura').focus();
                    Swal.fire({ toast:true, position:'top', icon:'warning', title:'Digite seu PIN de assinatura', showConfirmButton:false, timer:2600 });
                    return;
                }
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
        fd.append('pin_assinatura',   pinParaAssinar);
        if (sigPosCustomizada) {
            fd.append('sig_pos_x', sigPos.x.toFixed(1));
            fd.append('sig_pos_y', sigPos.y.toFixed(1));
        }
        if (modoAtivo === 'assinar_e_requisitar') {
            destinatarios.forEach(d => fd.append('coassinatura_destinatarios[]', d));
            fd.append('coassinatura_mensagem', document.getElementById('coassMensagem').value);
        }

        fetch('../assinatura/processa_assinatura.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(ret => {
            btn.disabled = false;
            btn.innerHTML = `<i class="fas fa-check-circle me-2"></i> <span id="btnAssinarLabel">${document.getElementById('btnAssinarLabel')?.textContent || 'Confirmar'}</span>`;

            if (ret.success) {
                bootstrap.Modal.getInstance(document.getElementById('modalConfirmacao')).hide();
                const swalTitle = isSemAssinar ? 'Documento Gerado' : 'Assinado com Sucesso';
                const swalText  = isSemAssinar
                    ? 'Documento gerado com linha de assinatura manual. Lembre-se de coletar a assinatura física.'
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
            } else if (ret.code === 'pin_incorreto') {
                Swal.fire('PIN incorreto', 'O PIN de assinatura informado está errado. Tente novamente.', 'error');
                document.getElementById('pinAssinatura').value = '';
                document.getElementById('pinAssinatura').focus();
            } else if (ret.code === 'pin_setup_required') {
                adminTemChave = false;
                atualizarBlocosPin();
                Swal.fire('Configure seu PIN', 'Você ainda não tem chave de assinatura. Crie seu PIN no campo exibido.', 'info');
            } else {
                Swal.fire('Erro Interno', ret.error || 'Não foi possível registrar o documento.', 'error');
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check-circle me-2"></i> Confirmar Assinatura Técnica';
            Swal.fire('Falha Crítica', 'Falha de comunicação ao registrar a assinatura.', 'error');
        });
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
            } else {
                sel.innerHTML = '<option value="">Nenhum template personalizado ainda</option>';
            }
        })
        .catch(() => {
            sel.innerHTML = '<option value="">Erro ao carregar templates</option>';
        });
    }

    /* ─── Salvar template (novo ou substituindo) ──────────── */
    function salvarTemplate(modo) {
        const rawHtml = (typeof $ !== 'undefined' && $('#editor-conteudo').data('summernote'))
            ? $('#editor-conteudo').summernote('code')
            : document.getElementById('editor-conteudo').value;

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

    document.addEventListener('DOMContentLoaded', function() { carregarTemplate(); });
    </script>
<?php include '../footer.php'; ?>
