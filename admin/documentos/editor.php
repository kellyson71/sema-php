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
            background: #e8ecf0;
        }

        /* ═══════════════════════════════════════════════
           WRAPPER A4 — contém header + editor + footer
        ═══════════════════════════════════════════════ */
        .a4-page-wrapper {
            max-width: 210mm;
            margin: 0 auto;
            background: #fff;
            box-shadow: 0 6px 32px rgba(0,0,0,0.15);
            border-radius: 3px;
            min-height: 297mm;
            display: flex;
            flex-direction: column;
        }

        /* ═══════════════════════════════════════════════
           PREVIEW A4 — HEADER SEMA (fiel ao PDF)
        ═══════════════════════════════════════════════ */
        .a4-sema-header {
            padding: 6mm 15mm 0 15mm;
            flex-shrink: 0;
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
           PREVIEW A4 — FOOTER (carimbo + paginação)
        ═══════════════════════════════════════════════ */
        .a4-sema-footer {
            padding: 0 15mm 8mm;
            border-top: 0.8px solid #c8cdd2;
            margin-top: auto;
            flex-shrink: 0;
        }
        .a4-footer-stamp {
            width: 90mm;
            margin: 5mm auto 0;
            border: 1.2px solid #2d8661;
            border-radius: 5px;
            overflow: hidden;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        }
        .a4-footer-stamp .stamp-bar {
            background: #2d8661;
            color: #fff;
            text-align: center;
            font-size: 6.5pt;
            font-weight: 700;
            padding: 2px 0;
            letter-spacing: 0.5px;
        }
        .a4-footer-stamp .stamp-body {
            background: #f8fcf9;
            text-align: center;
            padding: 3mm 4mm 2.5mm;
        }
        .a4-footer-stamp .stamp-nome {
            font-size: 8.5pt;
            font-weight: 700;
            color: #1e2328;
            margin-bottom: 1px;
        }
        .a4-footer-stamp .stamp-cargo {
            font-size: 6pt;
            color: #505560;
        }
        .a4-footer-stamp .stamp-info {
            font-size: 5.5pt;
            color: #6e7378;
            margin-top: 2px;
        }
        .a4-footer-stamp .stamp-data {
            font-size: 5.5pt;
            color: #82878c;
            font-style: italic;
            margin-top: 2px;
        }
        .a4-footer-page {
            text-align: center;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 7pt;
            color: #a0a5aa;
            margin-top: 3mm;
        }

        /* ═══════════════════════════════════════════════
           VARIÁVEIS DESTACADAS (campos auto-preenchidos)
        ═══════════════════════════════════════════════ */
        .note-editable .var-field,
        .var-field {
            text-decoration: underline;
            text-decoration-color: #1a5276;
            color: #1a5276 !important;
            font-weight: 600;
            background: rgba(26, 82, 118, 0.07);
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
        /* Toolbar do Summernote: barra separada acima da página, mesma largura A4 */
        .note-toolbar {
            background: #fff !important;
            border: 1px solid #dee2e6 !important;
            border-radius: 8px !important;
            padding: 6px 10px !important;
            margin: 0 auto 12px !important;
            max-width: 210mm !important;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06) !important;
        }
        /* Editing area — herda largura do wrapper A4, sem estilos próprios de página */
        .note-editing-area {
            background: transparent;
            flex: 1;
        }
        /* Área editável: margens laterais como o TCPDF (15mm) */
        .note-editable {
            font-family: "Times New Roman", Times, serif !important;
            font-size: 12pt !important;
            line-height: 1.4 !important;
            color: #1e1e1e !important;
            text-align: justify !important;
            padding: 2mm 15mm 10mm !important;
            min-height: 180mm !important;
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
        /* Área externa ao editor — fundo cinza como "mesa" de trabalho */
        .a4-outer-wrapper {
            background: #dde1e7;
            padding: 20px 16px 24px;
        }

        /* ═══════════════════════════════════════════════
           MODAL
        ═══════════════════════════════════════════════ */
        .modal-header-sema { background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
        .text-sema  { color: var(--sema-green) !important; }
        .btn-sema   { background: var(--sema-green); border-color: var(--sema-green); color: #fff; }
        .btn-sema:hover { background: var(--sema-green-lt); border-color: var(--sema-green-lt); color: #fff; }

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
                        Campos <span style="text-decoration:underline;color:#1a5276;font-weight:600">sublinhados em azul</span>
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
                <div class="alert alert-info d-flex align-items-start gap-2 py-2 mb-3" style="font-size:.8rem">
                  <i class="fas fa-circle-info mt-1 flex-shrink-0"></i>
                  <span>Os campos <strong>sublinhados em azul</strong> serão preservados como variáveis automáticas para futuros protocolos.</span>
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

    /* ─── Aguardar Summernote estar pronto ─────────────────── */
    function waitForSummernote(cb) {
        if (typeof window.jQuery !== 'undefined' && typeof jQuery.fn.summernote !== 'undefined') {
            cb();
        } else {
            setTimeout(function() { waitForSummernote(cb); }, 80);
        }
    }

    /* ─── Monta o wrapper A4 com header + editing-area + footer ── */
    function montarPaginaA4() {
        if (document.querySelector('.a4-page-wrapper')) return;
        const editingArea = document.querySelector('.note-editing-area');
        if (!editingArea) return;
        const parent = editingArea.parentNode;

        // Criar wrapper A4
        const wrapper = document.createElement('div');
        wrapper.className = 'a4-page-wrapper';

        // Header
        wrapper.innerHTML = `
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

        // Mover editing-area para dentro do wrapper
        parent.insertBefore(wrapper, editingArea);
        wrapper.appendChild(editingArea);

        // Footer
        const footer = document.createElement('div');
        footer.className = 'a4-sema-footer';
        footer.innerHTML = `
            <div class="a4-footer-stamp">
                <div class="stamp-bar">&#10003;  DOCUMENTO ASSINADO DIGITALMENTE  &#10003;</div>
                <div class="stamp-body">
                    <div class="stamp-nome">NOME DO ASSINANTE</div>
                    <div class="stamp-cargo">Cargo do assinante</div>
                    <div class="stamp-info">CPF: ***.***.**-**  |  Mat: ******</div>
                    <div class="stamp-data">Autenticado em dd/mm/aaaa hh:mm:ss</div>
                </div>
            </div>
            <div class="a4-footer-page" id="page-counter">&mdash;  Página 1 de 1  &mdash;</div>`;
        wrapper.appendChild(footer);

        // Iniciar contador de páginas dinâmico
        iniciarContadorPaginas();
    }

    /* ─── Contador de páginas dinâmico baseado na altura do conteúdo ── */
    function iniciarContadorPaginas() {
        const editable = document.querySelector('.note-editable');
        if (!editable) return;

        function atualizarPaginas() {
            const counter = document.getElementById('page-counter');
            if (!counter) return;
            // Área útil por página A4: 297mm - 27mm(header) - 35mm(footer) ≈ 235mm
            // Convertendo mm para px: 1mm ≈ 3.7795px (96dpi)
            const alturaPaginaPx = 235 * 3.7795;
            const alturaConteudo = editable.scrollHeight;
            const paginas = Math.max(1, Math.ceil(alturaConteudo / alturaPaginaPx));
            counter.innerHTML = '&mdash;  Página 1 de ' + paginas + '  &mdash;';
        }

        // Atualizar ao digitar e ao mudar conteúdo
        editable.addEventListener('input', atualizarPaginas);
        new MutationObserver(atualizarPaginas).observe(editable, {
            childList: true, subtree: true, characterData: true
        });

        // Atualizar após carregamento inicial (com delay para renderizar)
        setTimeout(atualizarPaginas, 500);
        setTimeout(atualizarPaginas, 2000);
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
                height: 600,
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
                        montarPaginaA4();
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

        // Remover spans var-field antes de enviar para PDF (mantém apenas o valor de texto)
        conteudoHtml = conteudoHtml.replace(
            /<span[^>]+class="var-field"[^>]*>((?:(?!<\/span>)[\s\S])*)<\/span>/g,
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

        const body = new URLSearchParams({
            action:        'salvar_template_usuario',
            conteudo_html: templateHtml,
            template_base: templateNome,
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
