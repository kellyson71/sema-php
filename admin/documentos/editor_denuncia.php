<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../conexao.php';
verificaLogin();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$denuncia_id = filter_input(INPUT_GET, 'denuncia_id', FILTER_VALIDATE_INT);
$template    = filter_input(INPUT_GET, 'template', FILTER_DEFAULT);

if (!$denuncia_id || empty($template)) {
    header('Location: selecionar_denuncia.php' . ($denuncia_id ? '?denuncia_id=' . $denuncia_id : ''));
    exit;
}

$stmt = $pdo->prepare("SELECT id, infrator_nome, status FROM denuncias WHERE id = ?");
$stmt->execute([$denuncia_id]);
$denuncia = $stmt->fetch();
if (!$denuncia) die("Denúncia não encontrada.");

$labelsTpl = [
    'denuncia_notificacao'       => 'Notificação Fiscal',
    'denuncia_tac'               => 'Termo de Ajustamento de Conduta (TAC)',
    'denuncia_termo_compromisso' => 'Termo de Compromisso Ambiental',
];
$templateLabel = $labelsTpl[$template] ?? ucwords(str_replace('_', ' ', $template));

$titulo_pagina = 'Editor – ' . $templateLabel;
include '../header.php';
?>
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

    <style>
        :root {
            --sema-green:    #1c4b36;
            --sema-green-lt: #2a6b50;
            --a4-width:      210mm;
            --a4-height:     297mm;
            --a4-header-h:   27mm;
            --a4-footer-h:   14mm;
            --a4-margin-lr:  15mm;
            --a4-usable-h:   256mm;
            --page-gap:      28px;
        }
        /* Ocultar imagem de fundo no editor */
        .note-editable #fundo-imagem,
        .note-editable img[alt="Fundo A4"] { display: none !important; }

        #secao-editor { min-height: calc(100vh - 60px - 70px); background: #d0d4da; }

        .a4-outer-wrapper { background: #d0d4da; padding: 24px 16px 32px; min-height: 100%; }

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

        .a4-sema-header {
            /* Altura EXATA da margem superior do TCPDF: a folha 1 fecha em
               27 + 256 + 14 = 297mm, igual ao PDF. */
            height: var(--a4-header-h);
            padding: 6mm var(--a4-margin-lr) 0 var(--a4-margin-lr);
            box-sizing: border-box;
            flex-shrink: 0; background: #fff; z-index: 5;
        }
        .a4-sema-header .header-content {
            display: flex; align-items: center; gap: 10px; padding-bottom: 5mm;
        }
        .a4-sema-header img { height: 17mm; width: auto; object-fit: contain; flex-shrink: 0; }
        .a4-sema-header .sema-prefeitura {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-weight: 700; font-size: 10pt; color: #282828; line-height: 1.3;
        }
        .a4-sema-header .sema-secretaria {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-weight: 700; font-size: 8pt; color: #646464; line-height: 1.3; margin-top: 1px;
        }
        .a4-sema-header .header-line { height: 1.2px; background: #2d8661; }

        .a4-sema-footer {
            height: var(--a4-footer-h);
            padding: 0 var(--a4-margin-lr);
            box-sizing: border-box; overflow: hidden;
            border-top: 0.5px solid #d2d2d2; margin-top: auto;
            flex-shrink: 0; text-align: center; background: #fff; z-index: 5;
        }
        .a4-footer-sign  { font-size: 5.5pt; color: #8c8c8c; margin-top: 2.5mm; line-height: 1.6; }
        .a4-footer-date  { font-size: 5pt; color: #aaa; font-style: italic; }
        .a4-footer-page  { font-size: 6pt; color: #b4b4b4; margin-top: 2mm; }

        /* Editor */
        .note-editor.note-frame { border: none !important; box-shadow: none !important; background: transparent; }
        .note-toolbar {
            background: #fff !important; border: 1px solid #dee2e6 !important;
            border-radius: 8px !important; padding: 6px 10px !important;
            margin: 0 auto 12px !important; max-width: var(--a4-width) !important;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06) !important;
            position: sticky; top: 0; z-index: 20;
        }
        .note-editing-area { background: transparent; flex: 1; overflow: visible !important; }
        .note-editable {
            font-family: "Times New Roman", Times, serif !important;
            font-size: 12pt !important; line-height: 1.4 !important;
            color: #1e1e1e !important; text-align: justify !important;
            /* Sem padding vertical: o topo do editável é o topo da área útil
               da folha 1, exatamente onde o TCPDF começa a escrever. */
            padding: 0 var(--a4-margin-lr) !important;
            min-height: var(--a4-usable-h) !important; height: auto !important;
            overflow: visible !important; box-sizing: border-box !important;
            position: relative;
            overflow-wrap: break-word !important; word-break: break-word !important;
        }
        .note-editable table { width: 100%; border-collapse: collapse; }
        .note-editable td, .note-editable th {
            padding: 5px 8px; border: 1px solid #aaa; vertical-align: middle;
        }
        .note-editable .texto-parecer p {
            margin-bottom: 12px; text-indent: 50px; line-height: 1.7;
        }

        /* Separador entre folhas: rodapé da folha que termina, faixa da mesa
           e cabeçalho da folha que começa. Inserido ENTRE os nós do texto,
           nunca dentro deles — não parte parágrafo nem move o cursor. */
        .note-editable .page-gap {
            width: 100%; position: relative; background: #fff;
            user-select: none; pointer-events: none; z-index: 8;
        }
        .note-editable .page-gap .page-gap-inner {
            margin-left: calc(-1 * var(--a4-margin-lr));
            margin-right: calc(-1 * var(--a4-margin-lr));
        }
        .page-gap .page-gap-footer {
            height: var(--a4-footer-h);
            border-top: .5px solid #d2d2d2;
            display: flex; align-items: flex-end; justify-content: center;
            padding-bottom: 3mm; box-sizing: border-box;
            color: #a5aaa7; font: 600 6pt 'Helvetica Neue', Arial, sans-serif;
            background: #fff;
        }
        .page-gap .page-gap-space {
            height: var(--page-gap); background: #d0d4da;
            border-top: 1px solid #c3c8ce; border-bottom: 1px solid #c3c8ce;
            box-shadow: inset 0 8px 14px rgba(19,45,32,.06), inset 0 -8px 14px rgba(19,45,32,.05);
        }
        .page-gap .page-gap-header {
            height: var(--a4-header-h);
            padding: 6mm var(--a4-margin-lr) 0;
            box-sizing: border-box; background: #fff;
        }
        .page-gap .page-gap-header-inner { display: flex; align-items: center; gap: 4mm; height: 17mm; }
        .page-gap .page-gap-header img { height: 17mm; width: auto; object-fit: contain; }
        .page-gap .page-gap-prefeitura { font: 700 8pt 'Helvetica Neue',Arial,sans-serif; color: #282828; }
        .page-gap .page-gap-secretaria { margin-top: 1px; font: 700 6pt 'Helvetica Neue',Arial,sans-serif; color: #646464; }
        .page-gap .page-gap-line { height: 1.2px; background: #2d8661; }


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

        .btn-sema   { background: var(--sema-green); border-color: var(--sema-green); color: #fff; }
        .btn-sema:hover { background: var(--sema-green-lt); border-color: var(--sema-green-lt); color: #fff; }
        .text-sema  { color: var(--sema-green) !important; }
        .modal-header-sema { background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
    </style>

    <!-- Navegação de Topo -->
    <div class="d-flex align-items-center justify-content-between mb-4 border-bottom pb-3">
        <div class="d-flex align-items-center gap-3">
            <a href="selecionar_denuncia.php?denuncia_id=<?= $denuncia_id ?>"
               class="btn btn-sm btn-light border fw-medium px-3 text-secondary">
                <i class="fas fa-arrow-left me-1"></i> Voltar
            </a>
            <div>
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="fas fa-edit me-2" style="color:var(--sema-green)"></i> Editor de Documento
                </h5>
                <small class="text-muted">Edite e assine digitalmente o documento da denúncia</small>
            </div>
        </div>
        <span class="badge px-3 py-2 rounded-pill fw-semibold"
              style="background:#fef3c7;color:#92400e;border:1px solid #fde68a;font-size:.85rem;">
            <i class="fas fa-exclamation-triangle me-1"></i>
            Denúncia #<?= str_pad($denuncia['id'], 6, '0', STR_PAD_LEFT) ?>
        </span>
    </div>

    <!-- Skeleton loader -->
    <div id="editor-loading" class="text-center py-5">
        <div class="spinner-border text-success" role="status"></div>
        <p class="mt-2 text-muted small">Carregando template...</p>
    </div>

    <!-- Seção do editor -->
    <div class="py-0 d-none" id="secao-editor">

        <!-- Barra de ações -->
        <div class="bg-white border rounded-3 shadow-sm px-4 py-3 mb-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-0 fw-bold text-dark" id="editor-title">
                        <i class="fas fa-edit me-2 text-success"></i> Editando Documento
                    </h5>
                    <small class="text-muted" style="font-size:.78rem">
                        Os dados da denúncia foram preenchidos automaticamente. Edite conforme necessário.
                    </small>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="selecionar_denuncia.php?denuncia_id=<?= $denuncia_id ?>"
                       class="btn btn-outline-secondary fw-medium px-3">
                        <i class="fas fa-arrow-left me-1"></i> Voltar
                    </a>
                    <button class="btn btn-outline-success fw-medium px-3" onclick="previewPdfDenuncia()">
                        <i class="fas fa-eye me-2"></i> Pré-visualizar PDF
                    </button>
                    <button class="btn btn-sema fw-medium px-4" onclick="abrirModalAssinatura()">
                        <i class="fas fa-signature me-2"></i> Assinar e Finalizar
                    </button>
                </div>
            </div>
        </div>

        <!-- Canvas A4 -->
        <div class="a4-outer-wrapper rounded-3">
            <textarea id="editor-conteudo"></textarea>
        </div>

    </div>

    <!-- Modal de Assinatura Digital -->
    <div class="modal fade" id="modalConfirmacao" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
          <div class="modal-header modal-header-sema px-4 py-3">
            <h5 class="modal-title fw-bold text-sema">
              <i class="fas fa-shield-alt me-2"></i> Autenticação Legal Exigida
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body p-5">
            <div class="text-center mb-4">
              <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3"
                   style="width:72px;height:72px;background:#d1fae5;">
                <i class="fas fa-file-signature fs-2 text-success"></i>
              </div>
              <h4 class="fw-bold text-dark">Deseja finalizar a assinatura digital?</h4>
              <p class="text-muted mb-0">O PDF será registrado com a assinatura somente na última página.</p>
            </div>
            <div class="bg-light p-3 rounded-3 mb-4 text-center border">
              <a href="../diretrizes_assinatura.php" target="_blank" class="text-decoration-none fw-bold text-sema">
                <i class="fas fa-external-link-alt me-1"></i>
                Ler Diretrizes de Convalidação e Responsabilidade Legal
              </a>
            </div>
            <form id="formCheckout">
              <div class="mb-3 text-start">
                <label for="senhaAssinaturaDenuncia" class="form-label fw-bold small text-sema">
                  <i class="fas fa-lock me-1"></i> Confirme sua identidade
                </label>
                <input type="password" class="form-control" id="senhaAssinaturaDenuncia" required
                       maxlength="128" autocomplete="current-password" placeholder="Digite sua senha de acesso">
                <div class="form-text">A senha confirma que a assinatura foi realizada por você.</div>
              </div>
              <div class="form-check p-3 mb-3 border rounded border-success" style="background:rgba(16,185,129,0.06)">
                <input class="form-check-input ms-1 me-2 border-success shadow-none" type="checkbox"
                       id="checkDiretrizes" required style="transform:scale(1.3);margin-top:5px">
                <label class="form-check-label fw-bold text-dark" for="checkDiretrizes">
                  Eu afirmo que li e concordo inteiramente com as diretrizes de assinatura digital
                  <span class="text-danger">*</span>
                </label>
              </div>
              <div class="form-check ms-2 mb-4">
                <input class="form-check-input" type="checkbox" id="checkDownload" checked>
                <label class="form-check-label text-muted" for="checkDownload">
                  Fazer o download automático do PDF logo após assinar
                </label>
              </div>
              <div class="d-grid gap-3 d-md-flex justify-content-md-end pt-3">
                <button type="button" class="btn btn-light fw-medium px-4 border"
                        data-bs-dismiss="modal">Revisar Documento</button>
                <button type="button" class="btn btn-sema fw-bold px-5"
                        id="btnAssinarFinal" onclick="finalizarAssinatura()">
                  <i class="fas fa-check-circle me-2"></i> Confirmar Assinatura
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="modalPreviewPdfDenuncia" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-xl modal-fullscreen-lg-down">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
          <div class="modal-header bg-white border-bottom px-4 py-3">
            <div>
              <h5 class="modal-title fw-bold text-dark"><i class="fas fa-file-pdf text-success me-2"></i>Pré-visualização do documento</h5>
              <small class="text-muted">Folhas separadas, com a assinatura reservada para a última página.</small>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div style="height:min(82vh,900px);background:#3f4542;padding:14px;position:relative;">
            <div id="previewLoadingDenuncia" class="position-absolute top-50 start-50 translate-middle text-white small">
              <i class="fas fa-spinner fa-spin me-2"></i>Montando as páginas…
            </div>
            <iframe id="previewFrameDenuncia" name="previewFrameDenuncia" src="about:blank" title="Pré-visualização paginada" style="width:100%;height:100%;border:0;border-radius:8px;background:#626765;"></iframe>
          </div>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Paginação visual da folha A4, compartilhada com o editor de pareceres -->
    <script src="<?= rtrim(BASE_URL, '/') ?>/js/editor_paginacao.js"></script>
    <script>
    (function waitForJQuery() {
        if (typeof window.jQuery === 'undefined') { setTimeout(waitForJQuery, 50); return; }
        var s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js';
        s.onload = function() { window._summernoteReady = true; };
        document.head.appendChild(s);
    })();
    </script>

    <script>
    const denunciaId    = <?= $denuncia_id ?>;
    const templateNome  = <?= json_encode($template) ?>;
    const templateLabel = <?= json_encode($templateLabel) ?>;
    const logoSemaUrl   = <?= json_encode(rtrim(BASE_URL, '/') . '/assets/SEMA/PNG/Azul/' . rawurlencode('Logo SEMA Vertical.png')) ?>;
    const csrfToken     = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;

    /* ═══════════════════════════════════════════════════════════
       FOLHA A4 E PAGINAÇÃO VISUAL
       A paginação de verdade é do TCPDF (admin/assinatura/gerar_pdf.php);
       js/editor_paginacao.js só desenha onde o corte vai cair.
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
                <div class="a4-footer-page" id="visual-page-counter">1 página no PDF</div>
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

        SemaPaginacao.iniciar({
            logoUrl:  logoSemaUrl,
            editavel: () => document.querySelector('.note-editable'),
            folha:    () => document.querySelector('.a4-page-sheet'),
            badge:    null,
            aoAtualizar(total) {
                _lastTotalPages = total;
                const contador = document.getElementById('visual-page-counter');
                if (contador) {
                    contador.textContent = total + ' página' + (total > 1 ? 's' : '') + ' no PDF';
                }
            },
        });
    }

    function waitForSummernote(cb) {
        if (typeof window.jQuery !== 'undefined' && typeof jQuery.fn.summernote !== 'undefined') {
            cb();
        } else {
            setTimeout(function() { waitForSummernote(cb); }, 80);
        }
    }

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str)));
        return d.innerHTML;
    }

    /* ─── Carregar template ─────────────────────────────── */
    function carregarTemplate() {
        fetch('../denuncia_doc_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'carregar_template_denuncia',
                template: templateNome,
                denuncia_id: denunciaId
            })
        })
        .then(res => res.json())
        .then(ret => {
            if (ret.success) {
                initEditor(ret.html, templateLabel);
            } else {
                document.getElementById('editor-loading').innerHTML = `
                <div class="alert alert-danger d-flex align-items-center gap-3 rounded-3 mx-auto" style="max-width:500px">
                    <i class="fas fa-triangle-exclamation fs-4"></i>
                    <div><strong>Erro ao carregar template</strong>
                    <br><small class="text-muted">${ret.error || 'Erro desconhecido.'}</small></div>
                </div>`;
            }
        })
        .catch(err => {
            document.getElementById('editor-loading').innerHTML = `
            <div class="alert alert-danger rounded-3 mx-auto" style="max-width:500px">
                <i class="fas fa-wifi-slash me-2"></i>
                <strong>Falha na conexão.</strong>
                <br><small class="text-muted">${err.message || ''}</small>
            </div>`;
        });
    }

    /* ─── Inicializar Summernote ────────────────────────── */
    function initEditor(html, title) {
        document.getElementById('editor-loading').remove();
        document.getElementById('secao-editor').classList.remove('d-none');
        document.getElementById('editor-title').innerHTML =
            '<i class="fas fa-edit text-success me-2"></i> Editando: <b>' + escapeHtml(title) + '</b>';

        waitForSummernote(function() {
            var $editor = $('#editor-conteudo');
            if ($editor.data('summernote')) $editor.summernote('destroy');
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
                    onInit: function() { montarCanvasMultiPagina(); }
                }
            });
        });
    }

    /* ─── Abrir modal de assinatura ────────────────────────── */
    function abrirModalAssinatura() {
        let html = '';
        if (typeof $ !== 'undefined' && $('#editor-conteudo').data('summernote')) {
            html = $('#editor-conteudo').summernote('code');
        } else {
            html = document.getElementById('editor-conteudo').value;
        }
        if (!html || html.trim() === '' || html === '<p><br></p>') {
            Swal.fire('Atenção', 'O documento não pode estar vazio.', 'warning');
            return;
        }
        const chk = document.getElementById('checkDiretrizes');
        chk.checked = false;
        chk.setCustomValidity('O aceite nas diretrizes é obrigatório.');
        document.getElementById('senhaAssinaturaDenuncia').value = '';
        new bootstrap.Modal(document.getElementById('modalConfirmacao')).show();
    }

    function obterConteudoDenunciaLimpo() {
        let html = (typeof $ !== 'undefined' && $('#editor-conteudo').data('summernote'))
            ? $('#editor-conteudo').summernote('code')
            : document.getElementById('editor-conteudo').value;
        return SemaPaginacao.limparHtml(html);
    }

    function previewPdfDenuncia() {
        const html = obterConteudoDenunciaLimpo();
        if (!html || html.trim() === '' || html === '<p><br></p>') {
            Swal.fire('Atenção', 'O documento está vazio.', 'warning');
            return;
        }

        const frame = document.getElementById('previewFrameDenuncia');
        const loading = document.getElementById('previewLoadingDenuncia');
        frame.dataset.pending = '1';
        loading.style.display = '';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalPreviewPdfDenuncia')).show();

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '../assinatura/preview_pdf.php';
        form.target = 'previewFrameDenuncia';
        const campos = {
            conteudo_parecer: html,
            requerimento_id: 'DEN-' + String(denunciaId).padStart(6, '0'),
            modo_assinatura: 'assinar',
            csrf_token: csrfToken,
        };
        Object.entries(campos).forEach(([nome, valor]) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = nome;
            input.value = valor;
            form.appendChild(input);
        });
        document.body.appendChild(form);
        form.submit();
        form.remove();
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('checkDiretrizes').addEventListener('change', function() {
            this.setCustomValidity(this.checked ? '' : 'O aceite nas diretrizes é obrigatório.');
        });
        const frame = document.getElementById('previewFrameDenuncia');
        frame.addEventListener('load', function() {
            if (this.dataset.pending !== '1') return;
            delete this.dataset.pending;
            document.getElementById('previewLoadingDenuncia').style.display = 'none';
        });
        document.getElementById('modalPreviewPdfDenuncia').addEventListener('hidden.bs.modal', function() {
            frame.src = 'about:blank';
        });
        carregarTemplate();
    });

    /* ─── Finalizar assinatura digital ─────────────────────── */
    function finalizarAssinatura() {
        const form = document.getElementById('formCheckout');
        if (!form.checkValidity()) { form.reportValidity(); return; }

        const btn = document.getElementById('btnAssinarFinal');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Processando...';

        const conteudoHtml = obterConteudoDenunciaLimpo();

        const fazDownload = document.getElementById('checkDownload').checked;
        const fd = new FormData();
        fd.append('conteudo_parecer', conteudoHtml);
        fd.append('denuncia_id',      denunciaId);
        fd.append('template_salvo',   templateNome);
        fd.append('pin_assinatura',   document.getElementById('senhaAssinaturaDenuncia').value);
        fd.append('csrf_token',       csrfToken);

        fetch('../assinatura/processa_assinatura_denuncia.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(ret => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check-circle me-2"></i> Confirmar Assinatura';

            if (ret.success) {
                bootstrap.Modal.getInstance(document.getElementById('modalConfirmacao')).hide();
                Swal.fire({
                    title: 'Documento Assinado!',
                    text: 'O documento foi gerado e assinado digitalmente.',
                    icon: 'success',
                    timer: 2500,
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
                        window.location.href = '../visualizar_denuncia.php?id=' + denunciaId + '&success=atualizada';
                    }, 500);
                });
            } else {
                Swal.fire('Erro', ret.error || 'Não foi possível gerar o documento.', 'error');
                if (ret.code === 'senha_incorreta') {
                    document.getElementById('senhaAssinaturaDenuncia').value = '';
                    document.getElementById('senhaAssinaturaDenuncia').focus();
                }
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check-circle me-2"></i> Confirmar Assinatura';
            Swal.fire('Falha na Conexão', 'Erro ao enviar para o servidor.', 'error');
        });
    }
    </script>
<?php include '../footer.php'; ?>
