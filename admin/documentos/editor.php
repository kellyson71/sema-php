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
           ESTILOS DO EDITOR (espelham o TCPDF)
        ═══════════════════════════════════════════════ */
        .note-editable {
            font-family: "Times New Roman", Times, serif;
            font-size: 12pt;
            line-height: 1.4;
            color: #1e1e1e;
            text-align: justify;
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

        <div class="d-flex justify-content-between align-items-center bg-white px-4 py-3 border-bottom shadow-sm mb-3 rounded-3">
            <h5 class="mb-0 fw-bold text-dark" id="editor-title">
                <i class="fas fa-edit me-2 text-success"></i> Editando Documento
            </h5>
            <div class="d-flex gap-2">
                <a href="selecionar.php?requerimento_id=<?= $requerimento_id ?>" class="btn btn-outline-secondary fw-medium px-4 border">
                    <i class="fas fa-times me-1"></i> Cancelar
                </a>
                <button class="btn btn-sema fw-medium px-4 shadow-sm" onclick="abrirModalAssinatura()">
                    Assinar e Finalizar <i class="fas fa-arrow-right ms-2"></i>
                </button>
            </div>
        </div>

        <div class="editor-container-wrapper">
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
    const reqId        = <?= $requerimento_id ?>;
    const templateNome = <?= json_encode($template) ?>;
    const templateLabel = <?= json_encode($label) ?>;
    let currentTemplate = templateNome;

    /* ─── Aguardar Summernote estar pronto ─────────────────── */
    function waitForSummernote(cb) {
        if (typeof window.jQuery !== 'undefined' && typeof jQuery.fn.summernote !== 'undefined') {
            cb();
        } else {
            setTimeout(function() { waitForSummernote(cb); }, 80);
        }
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
                            editable.style.minHeight = '600px';
                            editable.style.overflowY = 'auto';
                        }
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
        const fazDownload = document.getElementById('checkDownload').checked;
        const fd = new FormData();
        fd.append('conteudo_parecer', conteudoHtml);
        fd.append('requerimento_id',  reqId);
        fd.append('salvar_banco',     'true');
        fd.append('template_salvo',  templateNome);
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
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check-circle me-2"></i> Confirmar Assinatura Técnica';
            Swal.fire('Falha Crítica', 'Falha estrutural ao registrar no Endpoint de Assinaturas.', 'error');
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
