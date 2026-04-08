<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../conexao.php';
verificaLogin();

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
            padding: 6mm var(--a4-margin-lr) 0 var(--a4-margin-lr);
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
            padding: 0 var(--a4-margin-lr) 6mm;
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
            padding: 2mm var(--a4-margin-lr) 10mm !important;
            min-height: var(--a4-usable-h) !important; height: auto !important;
            overflow: visible !important; box-sizing: border-box !important;
        }
        .note-editable table { width: 100%; border-collapse: collapse; }
        .note-editable td, .note-editable th {
            padding: 5px 8px; border: 1px solid #aaa; vertical-align: middle;
        }
        .note-editable .texto-parecer p {
            margin-bottom: 12px; text-indent: 50px; line-height: 1.7;
        }

        /* Quebra de página visual */
        .page-break-indicator {
            position: absolute; left: -15mm; right: -15mm; height: 0;
            pointer-events: none; z-index: 10;
        }
        .page-break-indicator::before {
            content: ''; position: absolute; left: 0; right: 0; top: 0;
            border-top: 2px dashed #ffb0b0;
        }
        .page-break-indicator::after {
            content: attr(data-page-label); position: absolute; right: 0; top: -9px;
            font-size: 7.5pt; font-weight: 700; color: #bd4848;
            font-family: 'Helvetica Neue', sans-serif; background: #fff;
            padding: 0 6px; border: 1px solid #ffb0b0;
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
                <small class="text-muted">Edite e gere o PDF do documento da denúncia</small>
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
                    <button class="btn btn-sema fw-medium px-4" onclick="gerarPDF()">
                        <i class="fas fa-file-pdf me-2"></i> Gerar PDF
                    </button>
                </div>
            </div>
        </div>

        <!-- Canvas A4 -->
        <div class="a4-outer-wrapper rounded-3">
            <textarea id="editor-conteudo"></textarea>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                <div class="a4-footer-page" id="visual-page-counter">&mdash; Página 1 de ${totalPages} &mdash;</div>
            </div>`;
    }

    function montarCanvasMultiPagina() {
        if (document.querySelector('.a4-page-sheet')) return;
        const editingArea = document.querySelector('.note-editing-area');
        if (!editingArea) return;
        const parent = editingArea.parentNode;

        const sheet = document.createElement('div');
        sheet.className = 'a4-page-sheet';
        sheet.innerHTML = gerarHeaderHtml();

        parent.insertBefore(sheet, editingArea);
        sheet.appendChild(editingArea);

        const footerEl = document.createElement('div');
        footerEl.innerHTML = gerarFooterHtml(1);
        sheet.appendChild(footerEl.firstElementChild);

        iniciarMonitorPaginas();
    }

    let _lastTotalPages = 1;
    function iniciarMonitorPaginas() {
        const editable = document.querySelector('.note-editable');
        if (!editable) return;
        let _debounceTimer = null;
        let _updating = false;

        const observer = new MutationObserver(function(mutations) {
            if (_updating) return;
            const isOnlyIndicators = mutations.every(function(m) {
                return Array.from(m.addedNodes).concat(Array.from(m.removedNodes)).every(function(n) {
                    return n.nodeType === 1 && n.classList && n.classList.contains('page-break-indicator');
                });
            });
            if (isOnlyIndicators) return;
            clearTimeout(_debounceTimer);
            _debounceTimer = setTimeout(recalcularPaginas, 150);
        });

        function recalcularPaginas() {
            if (_updating) return;
            _updating = true;
            observer.disconnect();

            const alturaConteudo = editable.scrollHeight;
            const paginasNecessarias = Math.max(1, Math.ceil((alturaConteudo - 50) / PAGE_USABLE_PX));

            editable.querySelectorAll('.page-break-indicator').forEach(function(i) { i.remove(); });

            for (let p = 1; p < paginasNecessarias; p++) {
                const indicator = document.createElement('div');
                indicator.className = 'page-break-indicator';
                indicator.setAttribute('data-page-label', 'Corte da Página ' + p + ' / ' + (p + 1));
                indicator.style.top = (p * PAGE_USABLE_PX) + 'px';
                editable.appendChild(indicator);
            }

            if (paginasNecessarias !== _lastTotalPages) {
                const counter = document.getElementById('visual-page-counter');
                if (counter) counter.innerHTML = '&mdash; Página 1 a ' + paginasNecessarias + ' &mdash;';
                _lastTotalPages = paginasNecessarias;
            }

            _updating = false;
            observer.observe(editable, { childList: true, subtree: true, characterData: true });
        }

        editable.addEventListener('input', function() {
            clearTimeout(_debounceTimer);
            _debounceTimer = setTimeout(recalcularPaginas, 150);
        });
        observer.observe(editable, { childList: true, subtree: true, characterData: true });
        setTimeout(recalcularPaginas, 300);
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

    /* ─── Gerar PDF ─────────────────────────────────────── */
    function gerarPDF() {
        let conteudoHtml = '';
        if (typeof $ !== 'undefined' && $('#editor-conteudo').data('summernote')) {
            conteudoHtml = $('#editor-conteudo').summernote('code');
        } else {
            conteudoHtml = document.getElementById('editor-conteudo').value;
        }

        if (!conteudoHtml || conteudoHtml.trim() === '' || conteudoHtml === '<p><br></p>') {
            Swal.fire('Atenção', 'O documento não pode estar vazio.', 'warning');
            return;
        }

        // Limpar indicadores visuais de página
        conteudoHtml = conteudoHtml.replace(/<div[^>]+class="page-break-indicator"[^>]*><\/div>/g, '');

        Swal.fire({
            title: 'Gerando PDF...',
            text: 'Aguarde enquanto o documento é processado.',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        fetch('../denuncia_doc_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action:      'gerar_pdf_denuncia',
                html:        conteudoHtml,
                denuncia_id: denunciaId,
                template:    templateNome
            })
        })
        .then(r => r.json())
        .then(ret => {
            Swal.close();
            if (ret.success) {
                // Abrir PDF em nova aba
                window.open(ret.pdf_url, '_blank');
            } else {
                Swal.fire('Erro', ret.error || 'Não foi possível gerar o PDF.', 'error');
            }
        })
        .catch(err => {
            Swal.close();
            Swal.fire('Falha na Conexão', err.message || 'Erro inesperado.', 'error');
        });
    }

    document.addEventListener('DOMContentLoaded', carregarTemplate);
    </script>
<?php include '../footer.php'; ?>
