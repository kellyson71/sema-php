<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/conexao.php';

verificaLogin();

$requerimento_id = filter_input(INPUT_GET, 'requerimento_id', FILTER_VALIDATE_INT);
if (!$requerimento_id) {
    die("Acesso Negado: ID do requerimento não fornecido.");
}

// Buscar dados básicos do Processo
$stmt = $pdo->prepare("SELECT protocolo, status FROM requerimentos WHERE id = ?");
$stmt->execute([$requerimento_id]);
$req = $stmt->fetch();
if (!$req) {
    die("Erro: Requerimento não encontrado.");
}

// Buscar histórico de documentos na tabela oficial
$stmtDocs = $pdo->prepare("
    SELECT documento_id, nome_arquivo, timestamp_assinatura, tipo_documento, assinante_nome 
    FROM assinaturas_digitais 
    WHERE requerimento_id = ? 
    ORDER BY timestamp_assinatura DESC
");
$stmtDocs->execute([$requerimento_id]);
$pastDocs = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);

$titulo_pagina = 'Gerar Documento Oficial';
include 'header.php';
?>
    <!-- Assets Extras Específicos do Gerador -->
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

        /* ═══════════════════════════════════════════════
           SKELETON LOADING
        ═══════════════════════════════════════════════ */
        @keyframes shimmer {
            0%   { background-position: -800px 0; }
            100% { background-position: 800px 0; }
        }
        .skeleton {
            background: linear-gradient(90deg, #f0f2f5 25%, #e2e5ea 50%, #f0f2f5 75%);
            background-size: 800px 100%;
            animation: shimmer 1.5s infinite linear;
            border-radius: 8px;
        }
        .skeleton-card {
            height: 220px;
            border-radius: var(--card-radius);
        }

        /* ═══════════════════════════════════════════════
           ANIMAÇÃO DE ENTRADA DOS CARDS
        ═══════════════════════════════════════════════ */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .template-card-wrapper {
            animation: fadeInUp 0.4s ease both;
        }

        /* ═══════════════════════════════════════════════
           CARD DE TEMPLATE
        ═══════════════════════════════════════════════ */
        .template-card {
            transition: transform 0.28s cubic-bezier(0.25, 0.8, 0.25, 1),
                        box-shadow 0.28s ease,
                        border-color 0.28s ease;
            cursor: pointer;
            border: 1.5px solid #e5e9f2;
            border-bottom: 3px solid transparent;
            background: #fff;
            border-radius: var(--card-radius);
            height: 100%;
            position: relative;
            overflow: hidden;
        }
        .template-card::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(28,75,54,0.04) 0%, transparent 100%);
            opacity: 0;
            transition: opacity 0.28s;
        }
        .template-card:hover {
            transform: translateY(-5px);
            border-bottom-color: var(--sema-green);
            box-shadow: 0 12px 30px rgba(28, 75, 54, 0.13);
        }
        .template-card:hover::before { opacity: 1; }

        .template-card .icon-wrap {
            width: 58px; height: 58px;
            border-radius: 14px;
            background: #f0fdf4;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 14px;
            transition: background 0.25s, transform 0.25s;
        }
        .template-card:hover .icon-wrap {
            background: #d1fae5;
            transform: scale(1.08) rotate(-3deg);
        }
        .template-card .icon-wrap i { font-size: 1.55rem; }

        /* Badge de categoria */
        .tpl-badge {
            font-size: 0.68rem;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 20px;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }
        .tpl-badge.ambiental  { background: #d1fae5; color: #065f46; }
        .tpl-badge.construcao { background: #fef3c7; color: #92400e; }
        .tpl-badge.habite     { background: #dbeafe; color: #1e40af; }
        .tpl-badge.licenca    { background: #ede9fe; color: #5b21b6; }
        .tpl-badge.economico  { background: #fef9c3; color: #854d0e; }
        .tpl-badge.livre      { background: #f1f5f9; color: #475569; }
        .tpl-badge.parecer    { background: #f1f5f9; color: #475569; }
        .tpl-badge.desmembramento { background: #cffafe; color: #155e75; }

        /* Preview de texto do template */
        .preview-miniature {
            font-size: 0.72rem;
            color: #64748b;
            text-align: left;
            background: #f8fafc;
            padding: 10px 12px;
            border-radius: 8px;
            height: 68px;
            overflow: hidden;
            margin-top: 12px;
            border: 1px dashed #cbd5e1;
            position: relative;
            line-height: 1.5;
        }
        .preview-miniature::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 28px;
            background: linear-gradient(transparent, #f8fafc);
        }

        /* ═══════════════════════════════════════════════
           CARD DE HISTÓRICO
        ═══════════════════════════════════════════════ */
        .hist-card {
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            border: 1.5px solid #e5e9f2;
            border-left: 4px solid var(--sema-green) !important;
            border-radius: var(--card-radius);
            background: #fff;
            cursor: pointer;
        }
        .hist-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(28, 75, 54, 0.1);
        }
        .hist-icon-wrap {
            width: 42px; height: 42px;
            border-radius: 10px;
            background: #f0fdf4;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }

        /* Ocultar imagem de fundo do template no editor — só usada na geração do PDF */
        .note-editable #fundo-imagem,
        .note-editable img[alt="Fundo A4"] {
            display: none !important;
        }

        /* Editor fullscreen */
        #secao-editor {
            min-height: calc(100vh - var(--topbar-height, 60px) - 70px);
            background: #f8f9fa;
        }
        .editor-container-wrapper {
            min-height: 540px;
            background: white;
            border-radius: 10px;
            border: 1px solid #e0e4e8;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            overflow: hidden;
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
            <a href="visualizar_requerimento.php?id=<?= $requerimento_id ?>" class="btn btn-sm btn-light border fw-medium px-3 text-secondary">
                <i class="fas fa-arrow-left me-1"></i> Voltar
            </a>
            <div>
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="fas fa-file-signature me-2" style="color: var(--sema-green)"></i> Gestor de Documentos
                </h5>
                <small class="text-muted">Gere, edite e assine documentos oficiais do processo</small>
            </div>
        </div>
        <span class="badge px-3 py-2 rounded-pill fw-semibold" style="background: #f0fdf4; color: var(--sema-green); border: 1px solid #bbf7d0; font-size: 0.85rem;">
            <i class="fas fa-hashtag me-1"></i><?= htmlspecialchars($req['protocolo']) ?>
        </span>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         ETAPA 1: Seleção
    ══════════════════════════════════════════════════════════════ -->
    <div id="secao-selecao">

        <!-- Templates Padrão -->
        <div class="section-header">
            <div class="section-icon bg-success bg-opacity-10">
                <i class="fas fa-layer-group text-success"></i>
            </div>
            <div>
                <h5>Criar Novo Documento</h5>
                <small class="text-muted fw-normal d-block" style="margin-top:-2px">Selecione um modelo oficial para gerar o documento</small>
            </div>
        </div>

        <!-- Grid de Skeletons enquanto carrega -->
        <div class="row g-4 mb-5" id="lista-templates">
            <?php for($i=0;$i<6;$i++): ?>
            <div class="col-xl-3 col-md-4 col-sm-6">
                <div class="skeleton skeleton-card"></div>
            </div>
            <?php endfor; ?>
        </div>

        <!-- Histórico -->
        <div class="section-header mt-2">
            <div class="section-icon bg-warning bg-opacity-10">
                <i class="fas fa-history text-warning"></i>
            </div>
            <div>
                <h5>Reaproveitar Documento Anterior</h5>
                <small class="text-muted fw-normal d-block" style="margin-top:-2px">Documentos gerados neste processo que podem ser usados como base</small>
            </div>
        </div>

        <div class="row g-3 mb-5" id="lista-historico">
            <div class="col-12">
                <div class="text-center text-muted py-3">
                    <div class="spinner-border spinner-border-sm me-2 text-secondary" role="status"></div>
                    <small>Verificando histórico...</small>
                </div>
            </div>
        </div>

    </div><!-- /secao-selecao -->

    <!-- ══════════════════════════════════════════════════════════════
         ETAPA 2: Editor
    ══════════════════════════════════════════════════════════════ -->
    <div class="py-0 d-none" id="secao-editor">

        <div class="d-flex justify-content-between align-items-center bg-white px-4 py-3 border-bottom shadow-sm mb-3 rounded-3">
            <h5 class="mb-0 fw-bold text-dark" id="editor-title">
                <i class="fas fa-edit me-2 text-success"></i> Editando Documento
            </h5>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-secondary fw-medium px-4 border" onclick="voltarParaSelecao()">
                    <i class="fas fa-times me-1"></i> Cancelar
                </button>
                <button class="btn btn-sema fw-medium px-4 shadow-sm" onclick="abrirModalAssinatura()">
                    Assinar e Finalizar <i class="fas fa-arrow-right ms-2"></i>
                </button>
            </div>
        </div>

        <div class="editor-container-wrapper">
            <textarea id="editor-conteudo"></textarea>
        </div>

    </div><!-- /secao-editor -->

    <!-- ══════════════════════════════════════════════════════════════
         ETAPA 3: Modal de Confirmação
    ══════════════════════════════════════════════════════════════ -->
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
                  <a href="diretrizes_assinatura.php" target="_blank" class="text-decoration-none fw-bold"
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

    <!-- SweetAlert2 pode ser carregado de forma independente do jQuery -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Summernote PRECISA do jQuery, que só está disponível após o footer.php.
         Usamos um carregador dinâmico que aguarda o jQuery estar pronto. -->
    <script>
    (function waitForJQuery() {
        if (typeof window.jQuery === 'undefined') {
            // jQuery ainda não carregou — tenta de novo em 50ms
            setTimeout(waitForJQuery, 50);
            return;
        }
        // jQuery disponível: carrega Summernote dinamicamente
        var s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js';
        s.onload = function() {
            // Sinaliza que o Summernote está pronto
            window._summernoteReady = true;
        };
        document.head.appendChild(s);
    })();
    </script>

    <script>
    const reqId = <?= $requerimento_id ?>;
    let currentTemplate = '';

    /* ─── Helpers de badge ─────────────────────────────────── */
    function badgeClass(badge) {
        const map = {
            'Ambiental':      'ambiental',
            'Construção':     'construcao',
            'Habite-se':      'habite',
            'Licença':        'licenca',
            'Econômico':      'economico',
            'Livre':          'livre',
            'Desmembramento': 'desmembramento',
        };
        return map[badge] || 'parecer';
    }

    /* ─── Inicializar ao abrir a página ────────────────────── */
    document.addEventListener('DOMContentLoaded', carregarTemplates);

    /* ─── Carregar templates via AJAX ──────────────────────── */
    function carregarTemplates() {
        fetch('parecer_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                'action': 'listar_templates',
                'requerimento_id': reqId
            })
        })
        .then(res => res.json())
        .then(ret => {
            const listTpl  = document.getElementById('lista-templates');
            const listHist = document.getElementById('lista-historico');

            // ── Templates ──────────────────────────────────
            if (ret.success && ret.templates && ret.templates.length > 0) {
                let html = '';
                ret.templates.forEach((t, idx) => {
                    const nome    = t.nome  || t;
                    const label   = t.label_amigavel || nome.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
                    const desc    = t.descricao  || 'Modelo padrão oficial da secretaria.';
                    const icone   = t.icone      || 'fa-file-signature';
                    const cor     = t.icone_cor  || 'text-secondary';
                    const badge   = t.badge      || 'Parecer';
                    const preview = t.preview    || desc;
                    const delay   = (idx * 0.06).toFixed(2);

                    html += `
                    <div class="col-xl-3 col-md-4 col-sm-6 template-card-wrapper" style="animation-delay:${delay}s">
                        <div class="card template-card border-0 shadow-sm"
                             onclick="selecionarTemplate('${escaparAttr(nome)}', '${escaparAttr(label)}')"
                             title="${escaparAttr(desc)}">
                            <div class="card-body text-center p-4">
                                <div class="icon-wrap mb-1">
                                    <i class="fas ${icone} ${cor} fs-2"></i>
                                </div>
                                <span class="tpl-badge ${badgeClass(badge)} mb-2 d-inline-block">${badge}</span>
                                <h6 class="fw-bold text-dark lh-sm mb-1" style="font-size:.85rem">${label}</h6>
                                <div class="preview-miniature">
                                    ${escapeHtml(preview)}
                                </div>
                            </div>
                        </div>
                    </div>`;
                });
                listTpl.innerHTML = html;
            } else {
                listTpl.innerHTML = `
                <div class="col-12">
                    <div class="alert alert-danger d-flex align-items-center gap-3 rounded-3">
                        <i class="fas fa-triangle-exclamation fs-4"></i>
                        <div>
                            <strong>Falha ao carregar os modelos.</strong>
                            <br><small class="text-muted">${ret.error || 'Nenhum template encontrado no sistema.'}</small>
                        </div>
                    </div>
                </div>`;
            }

            // ── Histórico ──────────────────────────────────
            if (ret.success && ret.historico_recente && ret.historico_recente.length > 0) {
                let htmlHist = '';
                ret.historico_recente.forEach((h, idx) => {
                    const nome  = h.label || h.nome || 'Documento';
                    const isDb  = h.origem === 'db';
                    const icone = isDb ? 'fa-pen-to-square' : 'fa-file-pdf';
                    const cor   = isDb ? 'text-primary'  : 'text-danger';
                    const tipoBadge = isDb
                        ? '<span class="badge bg-primary-subtle text-primary" style="font-size:.65rem">Rascunho</span>'
                        : '<span class="badge bg-danger-subtle text-danger" style="font-size:.65rem">Assinado</span>';
                    const delay = (idx * 0.07).toFixed(2);

                    htmlHist += `
                    <div class="col-xl-3 col-md-4 col-sm-6 template-card-wrapper" style="animation-delay:${delay}s">
                        <div class="card hist-card shadow-sm border-0"
                             onclick="selecionarTemplate('${escaparAttr(h.id)}', 'Cópia: ${escaparAttr(nome)}')">
                            <div class="card-body p-3 d-flex align-items-start gap-3">
                                <div class="hist-icon-wrap">
                                    <i class="fas ${icone} ${cor}"></i>
                                </div>
                                <div class="overflow-hidden flex-grow-1">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        ${tipoBadge}
                                    </div>
                                    <h6 class="fw-bold text-dark mb-1 text-truncate" style="font-size:.82rem" title="${escaparAttr(nome)}">${escapeHtml(nome)}</h6>
                                    <small class="text-muted d-block">${h.data}</small>
                                    <small class="text-success fw-medium" style="font-size:.7rem">
                                        <i class="fas fa-copy me-1"></i> Usar como base
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>`;
                });
                listHist.innerHTML = htmlHist;
            } else {
                listHist.innerHTML = `
                <div class="col-12">
                    <div class="text-muted py-2 d-flex align-items-center gap-2">
                        <i class="fas fa-inbox text-secondary"></i>
                        <small>Nenhum documento anterior encontrado neste processo.</small>
                    </div>
                </div>`;
            }
        })
        .catch(err => {
            document.getElementById('lista-templates').innerHTML = `
            <div class="col-12">
                <div class="alert alert-danger rounded-3">
                    <i class="fas fa-wifi-slash me-2"></i>
                    <strong>Falha na conexão com o servidor.</strong>
                    <br><small class="text-muted">${err.message || 'Verifique sua conexão e recarregue a página.'}</small>
                </div>
            </div>`;
        });
    }

    /* ─── Selecionar e carregar template no editor ─────────── */
    function selecionarTemplate(arquivo, nomeLimpo) {
        currentTemplate = arquivo;
        Swal.fire({
            title: 'Preparando documento...',
            html: '<small class="text-muted">Carregando e preenchendo os dados do processo</small>',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        fetch('parecer_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                'action': 'carregar_template',
                'template': arquivo,
                'requerimento_id': reqId,
                'origem': 'tecnico'
            })
        })
        .then(res => res.json())
        .then(ret => {
            Swal.close();
            if (ret.success) {
                initEditor(ret.html, nomeLimpo);
            } else {
                Swal.fire('Erro ao abrir template', ret.error || 'Erro ao carregar os metadados do processo.', 'error');
            }
        })
        .catch(err => {
            Swal.close();
            Swal.fire('Erro de Conexão', 'Falha na comunicação com o servidor ao carregar template.', 'error');
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

    /* ─── Inicializar editor Summernote ────────────────────── */
    function initEditor(html, title) {
        document.getElementById('secao-selecao').classList.add('d-none');
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
                height: 480,
                focus: true,
                toolbar: [
                    ['style',    ['style']],
                    ['font',     ['bold', 'underline', 'clear']],
                    ['fontname', ['fontname']],
                    ['color',    ['color']],
                    ['para',     ['ul', 'ol', 'paragraph']],
                    ['table',    ['table']],
                    ['insert',   ['link']],
                    ['view',     ['codeview', 'fullscreen']]
                ],
                callbacks: {
                    onInit: function() {
                        var editable = document.querySelector('.note-editable');
                        if (editable) {
                            editable.style.minHeight = '440px';
                            editable.style.overflowY = 'auto';
                        }
                    }
                }
            });
        });
    }

    /* ─── Voltar para seleção ──────────────────────────────── */
    function voltarParaSelecao() {
        const val = document.getElementById('editor-conteudo').value;
        if (val && val.length > 50) {
            Swal.fire({
                title: 'Descartar alterações?',
                text: 'Os conteúdos digitados serão irremediavelmente apagados.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor:  '#64748b',
                confirmButtonText:  'Sim, descartar',
                cancelButtonText:   'Continuar editando'
            }).then(res => {
                if (res.isConfirmed) fecharEditor();
            });
        } else {
            fecharEditor();
        }
    }

    function fecharEditor() {
        document.getElementById('secao-editor').classList.add('d-none');
        document.getElementById('secao-selecao').classList.remove('d-none');
        if (typeof $ !== 'undefined' && $('#editor-conteudo').data('summernote')) {
            $('#editor-conteudo').summernote('destroy');
        }
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

        const fazDownload = document.getElementById('checkDownload').checked;
        const fd = new FormData();
        fd.append('conteudo_parecer', conteudoHtml);
        fd.append('requerimento_id',  reqId);
        fd.append('salvar_banco',     'true');
        fd.append('download',         fazDownload);

        fetch('assinatura/processa_assinatura.php', { method: 'POST', body: fd })
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
                        a.href = ret.url_pdf;
                        a.download = ret.nome_arquivo || 'Documento_Assinado.pdf';
                        document.body.appendChild(a);
                        a.click();
                        a.remove();
                    }
                    setTimeout(() => {
                        window.location.href = 'visualizar_requerimento.php?id=' + reqId;
                    }, 500);
                });
            } else {
                Swal.fire('Erro Interno', ret.error || 'Não foi possível registrar o documento.', 'error');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check-circle me-2"></i> Confirmar Assinatura Técnica';
            Swal.fire('Falha Crítica', 'Falha estrutural ao registrar no Endpoint de Assinaturas.', 'error');
        });
    }

    /* ─── Utilitários ──────────────────────────────────────── */
    function escaparAttr(str) {
        return String(str).replace(/'/g, "\\'").replace(/"/g, '&quot;');
    }
    function escapeHtml(str) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str)));
        return d.innerHTML;
    }
    </script>
<?php include 'footer.php'; ?>
