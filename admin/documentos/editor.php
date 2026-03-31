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
           ASSINATURA DIGITAL (preview no editor)
        ═══════════════════════════════════════════════ */
        .a4-signature-badge {
            position: absolute;
            bottom: 18mm;
            right: var(--a4-margin-lr);
            width: 62mm;
            background: #fff;
            border: 0.5px solid #a0a0a0;
            border-radius: 2px;
            padding: 2mm 3mm;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            pointer-events: none;
            z-index: 10;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
        }
        .a4-signature-badge .sig-header {
            background: #dcdcdc;
            margin: -2mm -3mm 1.5mm;
            padding: 1mm 3mm;
            border-radius: 2px 2px 0 0;
            font-size: 5pt;
            font-weight: 700;
            color: #333;
            display: flex;
            align-items: center;
            gap: 2mm;
        }
        .a4-signature-badge .sig-header::before {
            content: '';
            display: inline-block;
            width: 1.8mm; height: 1.8mm;
            background: #333;
        }
        .a4-signature-badge .sig-name {
            font-size: 5.5pt; font-weight: 700; color: #1e1e1e;
        }
        .a4-signature-badge .sig-detail {
            font-size: 5pt; color: #555; margin-top: 0.5mm;
        }
        .a4-signature-badge .sig-date {
            font-size: 5pt; color: #808080; margin-top: 0.5mm;
        }

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
            font-size: 12pt !important;
            line-height: 1.4 !important;
            color: #1e1e1e !important;
            text-align: justify !important;
            padding: 2mm var(--a4-margin-lr) 10mm !important;
            min-height: var(--a4-usable-h) !important;
            height: auto !important;
            overflow: visible !important;
            box-sizing: border-box !important;
            position: relative;
        }
        .note-editable table {
            width: 100%; border-collapse: collapse;
        }
        .note-editable td, .note-editable th {
            padding: 5px 8px; border: 1px solid #aaa; vertical-align: middle;
            font-size: 11pt; line-height: 1.4;
        }
        .note-editable .texto-parecer p {
            margin-bottom: 12px; text-indent: 50px; line-height: 1.7;
        }
        .note-editable .condicionantes {
            font-size: 9pt; border: 1px solid #000; padding: 8px 10px;
        }

        /* ═══════════════════════════════════════════════
           SEPARADOR VISUAL DE PÁGINAS (Dentro do canvas)
        ═══════════════════════════════════════════════ */
        .page-break-indicator {
            position: absolute;
            left: -15mm; right: -15mm;
            height: 0;
            pointer-events: none;
            z-index: 10;
        }
        .page-break-indicator::before {
            content: '';
            position: absolute;
            left: 0; right: 0;
            top: 0;
            border-top: 2px dashed #ffb0b0;
        }
        .page-break-indicator::after {
            content: attr(data-page-label);
            position: absolute;
            right: 0;
            top: -9px;
            font-size: 7.5pt;
            font-weight: 700;
            color: #bd4848;
            font-family: 'Helvetica Neue', sans-serif;
            background: #fff;
            padding: 0 6px;
            border: 1px solid #ffb0b0;
            border-radius: 10px;
        }

        /* ═══════════════════════════════════════════════
           MODAL
        ═══════════════════════════════════════════════ */
        .modal-header-sema { background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
        .text-sema  { color: var(--sema-green) !important; }
        .btn-sema   { background: var(--sema-green); border-color: var(--sema-green); color: #fff; }
        .btn-sema:hover { background: var(--sema-green-lt); border-color: var(--sema-green-lt); color: #fff; }

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
             <h5 class="modal-title fw-bold text-sema">
                <i class="fas fa-shield-alt me-2"></i> Autenticação Legal Exigida
             </h5>
             <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body p-5">
              <div class="text-center mb-4">
                  <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3"
                       style="width:72px;height:72px;background:#d1fae5;">
                      <i class="fas fa-file-signature fs-2 text-success"></i>
                  </div>
                  <h4 class="fw-bold text-dark">Deseja finalizar a assinatura digital?</h4>
                  <p class="text-muted mb-0">Após a confirmação, o documento será lacrado administrativamente.</p>
              </div>

              <div class="bg-light p-3 rounded-3 mb-4 text-center border">
                  <a href="../diretrizes_assinatura.php" target="_blank" class="text-decoration-none fw-bold"
                     style="color: var(--sema-green);">
                      <i class="fas fa-external-link-alt me-1"></i>
                      Ler Diretrizes de Convalidação e Responsabilidade Legal
                  </a>
              </div>

              <form id="formCheckout">
                  <div class="form-check p-3 mb-3 border rounded border-success"
                       style="background: rgba(16,185,129,0.06);">
                      <input class="form-check-input ms-1 me-2 border-success shadow-none"
                             type="checkbox" id="checkDiretrizes" required
                             style="transform: scale(1.3); margin-top: 5px;">
                      <label class="form-check-label fw-bold text-dark" for="checkDiretrizes">
                          Eu afirmo que li e concordo inteiramente com as diretrizes de assinatura digital
                          <span class="text-danger">*</span>
                      </label>
                  </div>
                  <div class="form-check ms-2 mb-4">
                      <input class="form-check-input" type="checkbox" id="checkDownload" checked>
                      <label class="form-check-label text-muted" for="checkDownload">
                          Fazer o download automático do PDF logo após autenticar
                      </label>
                  </div>

                  <div class="d-grid gap-3 d-md-flex justify-content-md-end pt-3">
                      <button type="button" class="btn btn-light fw-medium px-4 border"
                              data-bs-dismiss="modal">Revisar Documento</button>
                      <button type="button" class="btn btn-sema fw-bold px-5"
                              id="btnAssinarFinal" onclick="finalizarAssinatura()">
                          <i class="fas fa-check-circle me-2"></i> Confirmar Assinatura Técnica
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
    let currentTemplate = templateNome;

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
                <div class="a4-footer-sign">Assinado digitalmente por NOME DO ASSINANTE  |  Cargo</div>
                <div class="a4-footer-date">Autenticado em dd/mm/aaaa hh:mm:ss</div>
                <div class="a4-footer-page" id="visual-page-counter">&mdash; Página 1 de ${totalPages} &mdash;</div>
            </div>`;
    }

    function gerarSignatureBadgeHtml() {
        return `
            <div class="a4-signature-badge">
                <div class="sig-header">ASSINADO DIGITALMENTE</div>
                <div class="sig-name">NOME DO ASSINANTE</div>
                <div class="sig-detail">Cargo  |  CPF: ***.***.**-**</div>
                <div class="sig-date">dd/mm/aaaa hh:mm:ss</div>
            </div>`;
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

        // Inserir Badge de Assinatura (absolute sempre presa ao fundo da folha)
        sheet.insertAdjacentHTML('beforeend', gerarSignatureBadgeHtml());

        iniciarMonitorPaginas();
    }

    /**
     * Aplica as linhas tracejadas de corte no editor e atualiza contador
     */
    let _lastTotalPages = 1;
    function iniciarMonitorPaginas() {
        const editable = document.querySelector('.note-editable');
        if (!editable) return;

        let _debounceTimer = null;
        let _updating = false;

        const observer = new MutationObserver(function(mutations) {
            // Ignorar mutações causadas pelos próprios indicadores
            if (_updating) return;
            // Ignorar se só mudou page-break-indicator
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

            // Remove todos os indicadores atuais
            editable.querySelectorAll('.page-break-indicator').forEach(function(i) { i.remove(); });

            // Redesenha os indicadores
            for (let p = 1; p < paginasNecessarias; p++) {
                const indicator = document.createElement('div');
                indicator.className = 'page-break-indicator';
                indicator.setAttribute('data-page-label', 'Corte da Página ' + p + ' / ' + (p + 1));
                indicator.style.top = (p * PAGE_USABLE_PX) + 'px';
                editable.appendChild(indicator);
            }

            // Atualiza footer se a página mudou
            if (paginasNecessarias !== _lastTotalPages) {
                const counter = document.getElementById('visual-page-counter');
                if (counter) {
                    counter.innerHTML = '&mdash; Página 1 a ' + paginasNecessarias + ' &mdash;';
                }
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

    /* ─── Abrir modal de assinatura ────────────────────────── */
    function abrirModalAssinatura() {
        let htmlContent = '';
        if (typeof $ !== 'undefined' && $('#editor-conteudo').data('summernote')) {
            htmlContent = $('#editor-conteudo').summernote('code');
        } else {
            htmlContent = document.getElementById('editor-conteudo').value;
        }
        if (!htmlContent || htmlContent.trim() === '' || htmlContent === '<p><br></p>') {
            Swal.fire('Atenção', 'O documento não pode estar vazio.', 'warning');
            return;
        }

        const chk = document.getElementById('checkDiretrizes');
        chk.checked = false;
        chk.setCustomValidity('O aceite nas diretrizes é um bloco obrigatório legal.');

        new bootstrap.Modal(document.getElementById('modalConfirmacao')).show();
    }

    /* ─── Listener do checkbox de diretrizes ─────────────── */
    document.getElementById('checkDiretrizes').addEventListener('change', function() {
        this.setCustomValidity(this.checked ? '' : 'O aceite nas diretrizes é obrigatório.');
    });

    /* ─── Finalizar assinatura ─────────────────────────────── */
    function finalizarAssinatura() {
        const form = document.getElementById('formCheckout');
        if (!form.checkValidity()) { form.reportValidity(); return; }

        const btn = document.getElementById('btnAssinarFinal');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Autenticando...';

        let conteudoHtml = '';
        if (typeof $ !== 'undefined' && $('#editor-conteudo').data('summernote')) {
            conteudoHtml = $('#editor-conteudo').summernote('code');
        } else {
            conteudoHtml = document.getElementById('editor-conteudo').value;
        }

        // Remover indicadores de quebra de página (elementos visuais do editor)
        conteudoHtml = conteudoHtml.replace(/<div[^>]+class="page-break-indicator"[^>]*><\/div>/g, '');

        // Remover spans var-field antes de enviar para PDF (mantém apenas o valor de texto)
        conteudoHtml = conteudoHtml.replace(
            /<span[^>]+class="var-field"[^>]*>((?:(?!<\/span>)[\s\S])*)<\/span>/g,
            '$1'
        );

        // Limpeza de cores inline residuais que o Summernote injeta ao quebrar spans
        // Remove color:#1a5276 e variantes (hex azul do antigo var-field)
        conteudoHtml = conteudoHtml.replace(
            /(<span[^>]*style="[^"]*)color\s*:\s*(?:rgb\(26\s*,\s*82\s*,\s*118\)|#1a5276)\s*;?/gi,
            '$1'
        );

        const fazDownload = document.getElementById('checkDownload').checked;
        const fd = new FormData();
        fd.append('conteudo_parecer', conteudoHtml);
        fd.append('requerimento_id',  reqId);
        fd.append('salvar_banco',     'true');
        fd.append('template_salvo',   templateNome);
        fd.append('download',         fazDownload);

        fetch('../assinatura/processa_assinatura.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(ret => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check-circle me-2"></i> Confirmar Assinatura Técnica';

            if (ret.success) {
                bootstrap.Modal.getInstance(document.getElementById('modalConfirmacao')).hide();
                Swal.fire({
                    title: 'Autenticado com Sucesso!',
                    text: 'O arquivo consta permanentemente armazenado nos registros eletrônicos do Processo.',
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
                        window.location.href = '../visualizar_requerimento.php?id=' + reqId;
                    }, 500);
                });
            } else {
                Swal.fire('Erro Interno', ret.error || 'Não foi possível registrar o documento.', 'error');
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check-circle me-2"></i> Confirmar Assinatura Técnica';
            Swal.fire('Falha Crítica', 'Falha estrutural ao registrar no Endpoint de Assinaturas.', 'error');
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
