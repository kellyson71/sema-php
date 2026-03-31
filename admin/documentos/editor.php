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
    <!-- SweetAlert2 -->
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

        /* ─── Variáveis de template destacadas ─── */
        .var-field {
            font-weight: 700 !important;
            color: inherit !important;
            text-decoration: none !important;
            background: rgba(0, 0, 0, 0.045);
            border-radius: 2px;
            padding: 0 2px;
        }

        /* ═══════════════════════════════════════════════
           TOOLBAR DO EDITOR
        ═══════════════════════════════════════════════ */
        #editor-toolbar {
            background: #fff;
            border-bottom: 1px solid #dee2e6;
            border-top: 1px solid #dee2e6;
            padding: 5px 12px;
            display: flex;
            flex-wrap: wrap;
            gap: 3px;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 20;
        }
        #editor-toolbar .toolbar-sep {
            width: 1px; height: 22px;
            background: #dee2e6;
            margin: 0 3px;
            flex-shrink: 0;
        }
        #editor-toolbar .btn-tool {
            padding: 3px 8px;
            font-size: 0.8rem;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            background: transparent;
            color: #444;
            cursor: pointer;
            line-height: 1.5;
            transition: background .12s, border-color .12s, color .12s;
            white-space: nowrap;
        }
        #editor-toolbar .btn-tool:hover {
            background: #f0fdf4;
            border-color: var(--sema-green);
            color: var(--sema-green);
        }
        #editor-toolbar select.tool-select {
            padding: 3px 5px;
            font-size: 0.78rem;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            background: transparent;
            color: #444;
            cursor: pointer;
            height: 28px;
        }
        #editor-toolbar select.tool-select:hover {
            border-color: var(--sema-green);
            color: var(--sema-green);
        }

        /* ═══════════════════════════════════════════════
           ÁREA DO CANVAS-EDITOR
        ═══════════════════════════════════════════════ */
        #secao-editor {
            min-height: calc(100vh - 60px - 70px);
            display: flex;
            flex-direction: column;
        }
        #canvas-editor-wrap {
            flex: 1;
            overflow: hidden;
            background: #d0d4da;
        }
        #canvas-editor-container {
            height: calc(100vh - 230px);
            min-height: 500px;
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

        /* ─── Section header ─── */
        .section-header {
            display: flex; align-items: center; gap: 10px; margin-bottom: 1.2rem;
        }
        .section-header .section-icon {
            width: 36px; height: 36px; border-radius: 10px;
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
        <div class="bg-white border rounded-3 shadow-sm px-4 py-3 mb-0">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-0 fw-bold text-dark" id="editor-title">
                        <i class="fas fa-edit text-success me-2"></i> Editando Documento
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

        <!-- Toolbar de formatação (Canvas-Editor commands) -->
        <div id="editor-toolbar">
            <!-- Histórico -->
            <button class="btn-tool" onclick="cmd('executeUndo')" title="Desfazer"><i class="fas fa-undo"></i></button>
            <button class="btn-tool" onclick="cmd('executeRedo')" title="Refazer"><i class="fas fa-redo"></i></button>
            <div class="toolbar-sep"></div>
            <!-- Formatação de texto -->
            <button class="btn-tool" onclick="cmd('executeBold')"      title="Negrito"><b>N</b></button>
            <button class="btn-tool" onclick="cmd('executeItalic')"    title="Itálico"><i>I</i></button>
            <button class="btn-tool" onclick="cmd('executeUnderline')" title="Sublinhado"><u>S</u></button>
            <button class="btn-tool" onclick="cmd('executeStrikeout')" title="Tachado"><s>T</s></button>
            <div class="toolbar-sep"></div>
            <!-- Tamanho da fonte -->
            <select class="tool-select" title="Tamanho da fonte"
                    onchange="cmd('executeFontSize', parseInt(this.value)); this.value = ''">
                <option value="">Tam.</option>
                <option>10</option><option>11</option><option>12</option>
                <option>14</option><option>16</option><option>18</option>
                <option>20</option><option>24</option><option>28</option><option>36</option>
            </select>
            <div class="toolbar-sep"></div>
            <!-- Alinhamento -->
            <button class="btn-tool" onclick="cmdFlex('left')"      title="Alinhar à esquerda"><i class="fas fa-align-left"></i></button>
            <button class="btn-tool" onclick="cmdFlex('center')"    title="Centralizar"><i class="fas fa-align-center"></i></button>
            <button class="btn-tool" onclick="cmdFlex('right')"     title="Alinhar à direita"><i class="fas fa-align-right"></i></button>
            <button class="btn-tool" onclick="cmdFlex('alignment')" title="Justificar"><i class="fas fa-align-justify"></i></button>
            <div class="toolbar-sep"></div>
            <!-- Listas -->
            <button class="btn-tool" onclick="inserirLista('ol')" title="Lista numerada"><i class="fas fa-list-ol"></i></button>
            <button class="btn-tool" onclick="inserirLista('ul')" title="Lista com marcadores"><i class="fas fa-list-ul"></i></button>
            <div class="toolbar-sep"></div>
            <!-- Tabela -->
            <button class="btn-tool" onclick="inserirTabela()" title="Inserir tabela 3×3"><i class="fas fa-table"></i></button>
            <div class="toolbar-sep"></div>
            <!-- Impressão -->
            <button class="btn-tool" onclick="cmd('executePrint')" title="Imprimir documento"><i class="fas fa-print"></i></button>
        </div>

        <!-- Container do Canvas-Editor (A4 paginado nativo) -->
        <div id="canvas-editor-wrap">
            <div id="canvas-editor-container"></div>
        </div>

    </div><!-- /secao-editor -->

    <!-- Modal de Confirmação (Assinatura) -->
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
                  <div id="iconPickerGrid" style="display:grid;grid-template-columns:repeat(8,1fr);gap:6px;"></div>
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

    <!-- SweetAlert2 e Canvas-Editor UMD (sem dependência de jQuery) -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/@hufe921/canvas-editor@0.9.130/dist/canvas-editor.umd.js"></script>

    <script>
    const reqId         = <?= $requerimento_id ?>;
    const templateNome  = <?= json_encode($template) ?>;
    const templateLabel = <?= json_encode($label) ?>;
    let canvasInstance  = null;

    /* ═══════════════════════════════════════════════════════════
       CANVAS-EDITOR — Instância e Configuração A4
    ═══════════════════════════════════════════════════════════ */
    function criarInstancia() {
        // O bundle UMD registra o namespace como window["canvas-editor"]
        const CE = window["canvas-editor"];
        if (!CE || !CE.Editor) {
            console.error('Canvas-Editor UMD não carregado. Global disponível:', Object.keys(window).filter(k => k.includes('canvas') || k.includes('Editor')));
            return false;
        }
        canvasInstance = new CE.Editor(
            document.getElementById('canvas-editor-container'),
            { header: [], main: [{ value: '' }], footer: [] },
            {
                // A4 a 96dpi: 210mm × 297mm ≈ 794px × 1123px
                width:       794,
                height:      1123,
                pageGap:     20,
                // margens: topo, direita, base, esquerda (px)
                margins:     [100, 120, 100, 120],
                defaultFont: 'Arial',
                defaultSize: 14,
            }
        );
        return true;
    }

    /* ─── Carrega HTML do template na instância ─── */
    function carregarHtmlNoEditor(html) {
        if (!canvasInstance) return;
        try {
            // executeSetHTML aceita { header?, main, footer? } com strings HTML
            canvasInstance.command.executeSetHTML({ main: html });
        } catch (e) {
            console.warn('executeSetHTML falhou, tentando setValue:', e);
            try {
                // Fallback: reconstruir instância com texto puro extraído do HTML
                const tmp = document.createElement('div');
                tmp.innerHTML = html;
                const texto = tmp.textContent || tmp.innerText || '';
                canvasInstance.command.executeSetValue({
                    header: [],
                    main: [{ value: texto }],
                    footer: []
                });
            } catch (e2) {
                console.error('Falha ao carregar conteúdo no editor:', e2);
            }
        }
    }

    /* ═══════════════════════════════════════════════════════════
       TOOLBAR — Comandos da instância
    ═══════════════════════════════════════════════════════════ */

    /** Executa um comando sem argumento ou com argumento simples */
    function cmd(name, arg) {
        if (!canvasInstance) return;
        try {
            if (arg !== undefined) {
                canvasInstance.command[name](arg);
            } else {
                canvasInstance.command[name]();
            }
        } catch(e) {
            console.warn('Comando não disponível:', name, e);
        }
    }

    /** Alinhamento via RowFlex (valores em minúsculas conforme a lib) */
    function cmdFlex(align) {
        if (!canvasInstance) return;
        try {
            // Tenta usar o enum exposto no UMD; cai no valor string caso não exista
            const RF = (window["canvas-editor"] && window["canvas-editor"].RowFlex) || {};
            const val = RF[align.toUpperCase()] || align;
            canvasInstance.command.executeRowFlex(val);
        } catch(e) {
            console.warn('executeRowFlex falhou:', e);
        }
    }

    /** Inserir lista numerada (ol) ou com marcadores (ul) */
    function inserirLista(tipo) {
        if (!canvasInstance) return;
        try {
            canvasInstance.command.executeList({ listType: tipo });
        } catch(e) {
            console.warn('executeList falhou:', e);
        }
    }

    /** Inserir tabela 3×3 */
    function inserirTabela() {
        if (!canvasInstance) return;
        try {
            canvasInstance.command.executeInsertTable(3, 3);
        } catch(e) {
            console.warn('executeInsertTable falhou:', e);
        }
    }

    /* ═══════════════════════════════════════════════════════════
       EXTRAÇÃO DE CONTEÚDO PARA O BACKEND
    ═══════════════════════════════════════════════════════════ */
    function getEditorHtml() {
        if (!canvasInstance) return '';
        let html = '';
        try {
            html = canvasInstance.command.executeHTML() || '';
        } catch(e) {
            console.error('executeHTML falhou:', e);
            return '';
        }
        // Remover possíveis indicadores visuais residuais
        html = html.replace(/<div[^>]+class="page-break-indicator"[^>]*>[\s\S]*?<\/div>/g, '');
        // Remover spans var-field (mantém apenas o texto do valor preenchido)
        html = html.replace(
            /<span[^>]+class="var-field"[^>]*>((?:(?!<\/span>)[\s\S])*)<\/span>/g,
            '$1'
        );
        return html;
    }

    /* ═══════════════════════════════════════════════════════════
       CARREGAMENTO DO TEMPLATE
    ═══════════════════════════════════════════════════════════ */
    function carregarTemplate() {
        fetch('../parecer_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                'action':          'carregar_template',
                'template':        templateNome,
                'requerimento_id': reqId,
                'origem':          'tecnico'
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
                        <br><small class="text-muted">${escapeHtml(ret.error || 'Erro ao carregar os metadados do processo.')}</small>
                    </div>
                </div>`;
            }
        })
        .catch(err => {
            document.getElementById('editor-loading').innerHTML = `
            <div class="alert alert-danger rounded-3 mx-auto" style="max-width:500px">
                <i class="fas fa-wifi-slash me-2"></i>
                <strong>Falha na conexão com o servidor.</strong>
                <br><small class="text-muted">${escapeHtml(err.message || 'Verifique sua conexão e recarregue a página.')}</small>
            </div>`;
        });
    }

    /** Exibe a seção do editor e inicializa o Canvas-Editor com o HTML do template */
    function initEditor(html, title) {
        document.getElementById('editor-loading').remove();
        document.getElementById('secao-editor').classList.remove('d-none');
        document.getElementById('editor-title').innerHTML =
            '<i class="fas fa-edit text-success me-2"></i> Editando: <b>' + escapeHtml(title) + '</b>';

        if (criarInstancia()) {
            carregarHtmlNoEditor(html);
        } else {
            document.getElementById('secao-editor').insertAdjacentHTML('afterbegin', `
            <div class="alert alert-danger mx-3 mt-2">
                <i class="fas fa-triangle-exclamation me-2"></i>
                <strong>Erro:</strong> O editor Canvas não pôde ser carregado. Verifique a conexão com o CDN.
            </div>`);
        }
    }

    /* ═══════════════════════════════════════════════════════════
       MODAL DE ASSINATURA
    ═══════════════════════════════════════════════════════════ */
    function abrirModalAssinatura() {
        const html = getEditorHtml();
        if (!html || html.trim() === '') {
            Swal.fire('Atenção', 'O documento não pode estar vazio.', 'warning');
            return;
        }
        const chk = document.getElementById('checkDiretrizes');
        chk.checked = false;
        chk.setCustomValidity('O aceite nas diretrizes é um bloco obrigatório legal.');
        new bootstrap.Modal(document.getElementById('modalConfirmacao')).show();
    }

    document.getElementById('checkDiretrizes').addEventListener('change', function() {
        this.setCustomValidity(this.checked ? '' : 'O aceite nas diretrizes é obrigatório.');
    });

    function finalizarAssinatura() {
        const form = document.getElementById('formCheckout');
        if (!form.checkValidity()) { form.reportValidity(); return; }

        const btn = document.getElementById('btnAssinarFinal');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Autenticando...';

        const conteudoHtml = getEditorHtml();
        const fazDownload  = document.getElementById('checkDownload').checked;

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

    /* ═══════════════════════════════════════════════════════════
       MODAL SALVAR TEMPLATE
    ═══════════════════════════════════════════════════════════ */
    function abrirModalSalvarTemplate() {
        document.getElementById('novoTemplateNome').value = '';
        document.getElementById('novoTemplateDesc').value = '';
        document.getElementById('novoTemplateIcone').value = 'fa-bookmark';
        iniciarIconPicker();
        carregarTemplatesParaModal();
        new bootstrap.Modal(document.getElementById('modalSalvarTemplate')).show();
    }

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

    function salvarTemplate(modo) {
        const rawHtml = getEditorHtml();
        if (!rawHtml || rawHtml.trim() === '') {
            Swal.fire('Atenção', 'O editor está vazio.', 'warning'); return;
        }

        // Converter spans var-field de volta para {{variavel}} no template salvo
        const templateHtml = rawHtml.replace(
            /<span[^>]+class="var-field"[^>]+data-var="([^"]+)"[^>]*>(?:(?!<\/span>)[\s\S])*?<\/span>/g,
            '{{$1}}'
        );

        const nome = document.getElementById('novoTemplateNome').value.trim();
        const desc = document.getElementById('novoTemplateDesc').value.trim();
        const utId = document.getElementById('selectTemplateExistente').value;

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

    /* ═══════════════════════════════════════════════════════════
       ICON PICKER
    ═══════════════════════════════════════════════════════════ */
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

    /* ─── Utilitários ─── */
    function escapeHtml(str) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str)));
        return d.innerHTML;
    }

    document.addEventListener('DOMContentLoaded', function() { carregarTemplate(); });
    </script>
<?php include '../footer.php'; ?>
