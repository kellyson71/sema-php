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

$titulo_pagina = 'Gerar Documento Oficial - Fluxo de Assinatura';
include 'header.php';
?>
    <!-- Assets Extras Específicos do Gerador -->
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    
    <style>
        /* Estilo dos Cards UX */
        .template-card { 
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); 
            cursor: pointer; 
            border: 1px solid #e5e9f2;
            border-bottom: 3px solid transparent; 
            background: white;
        }
        .template-card:hover { 
            transform: translateY(-4px); 
            border-bottom-color: var(--primary-color); 
            box-shadow: 0 10px 25px rgba(28, 75, 54, 0.1); 
        }
        .template-card .icon-placeholder {
            width: 60px; height: 60px;
            border-radius: 12px;
            background: #f0fdf4;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 15px auto;
        }
        .preview-miniature { 
            font-size: 0.75rem; color: #718096; text-align: left; 
            background: #f8fafc; padding: 12px; border-radius: 6px; 
            height: 70px; overflow: hidden; margin-top: 15px; 
            border: 1px dashed #cbd5e1; 
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
            position: relative;
        }
        /* Fading effect pro final do texto */
        .preview-miniature::after {
            content: '';
            position: absolute; bottom: 0; left: 0; right: 0; height: 25px;
            background: linear-gradient(transparent, #f8fafc);
        }

        /* Editor Fullscreen Fake */
        #secao-editor { height: calc(100vh - var(--topbar-height) - 70px); background: #f8f9fa; }
        .editor-container-wrapper {
            height: calc(100% - 70px);
            background: white;
            border-radius: 8px;
            border: 1px solid #e0e4e8;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            /* Sem margens para ficar grande e imersivo */
        }
        
        /* Modal Custom */
        .modal-header-sema { background-color: #f8fafc; border-bottom: 1px solid #e2e8f0; }
        .text-sema { color: var(--primary-color) !important; }
        .btn-sema { background-color: var(--primary-color); border-color: var(--primary-color); color: white; }
        .btn-sema:hover { background-color: var(--secondary-color); border-color: var(--secondary-color); color: white; }
    </style>

    <!-- Navegação de Topo Limpa para Retorno -->
    <div class="d-flex align-items-center justify-content-between mb-4 border-bottom pb-3">
        <div class="d-flex align-items-center">
            <a href="visualizar_requerimento.php?id=<?= $requerimento_id ?>" class="btn btn-sm btn-light border fw-medium px-3 text-secondary me-4">
                <i class="fas fa-arrow-left me-2"></i> Voltar ao Processo
            </a>
            <h5 class="mb-0 fw-bold text-dark">
                <i class="fas fa-file-signature text-success me-2"></i> Gestor de Documentos
            </h5>
        </div>
        <div>
            <span class="badge bg-secondary px-3 py-2 rounded-pill fw-medium fs-6">Protocolo #<?= htmlspecialchars($req['protocolo']) ?></span>
        </div>
    </div>

    <!-- ETAPA 1: Seleção de Templates e Histórico -->
    <div class="py-2" id="secao-selecao">
        
        <h5 class="fw-bold mb-4 text-dark"><i class="fas fa-plus-square text-primary me-2"></i> Criar Novo Documento (Templates Padrão)</h5>
        
        <!-- Grid Carregado via AJAX -->
        <div class="row g-4 mb-5" id="lista-templates">
            <div class="col-12 text-center text-muted py-4">
                <div class="spinner-border spinner-border-sm me-2" role="status"></div> Carregando acervo oficial...
            </div>
        </div>

        <h5 class="fw-bold mb-4 text-dark"><i class="fas fa-history text-warning me-2"></i> Reaproveitar Documento Passado Deste Processo</h5>
        
        <div class="row g-4 mb-5" id="lista-historico">
             <div class="col-12 text-muted"><p class="small text-muted mb-0">Nenhum rascunho ou documento anterior para herdar conteúdo foi encontrado neste processo.</p></div>
        </div>
        
    </div>

    <!-- ETAPA 2: Editor Tela Cheia -->
    <div class="container-fluid py-0 px-0 d-none" id="secao-editor">
        
        <div class="d-flex justify-content-between align-items-center bg-white px-4 py-3 border-bottom shadow-sm mb-3">
            <h5 class="mb-0 fw-bold text-dark" id="editor-title">
                <i class="fas fa-edit text-success me-2"></i> Editando Documento
            </h5>
            <div>
                <button class="btn btn-outline-danger fw-medium px-4 me-2 border-0" onclick="voltarParaSelecao()">
                    Cancelar
                </button>
                <button class="btn btn-sema fw-medium px-4 shadow-sm" onclick="abrirModalAssinatura()">
                    Assinar e Finalizar <i class="fas fa-arrow-right ms-2"></i>
                </button>
            </div>
        </div>
        
        <div class="editor-container-wrapper">
            <textarea id="editor-conteudo"></textarea>
        </div>
        
    </div>

    <!-- ETAPA 3: Modal de Confirmação & Diretrizes Legais -->
    <div class="modal fade" id="modalConfirmacao" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
          <div class="modal-header modal-header-sema px-4 py-3">
             <h5 class="modal-title fw-bold text-sema"><i class="fas fa-shield-check me-2"></i> Autenticação Legal Exigida</h5>
             <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body p-5">
              
              <div class="text-center mb-4">
                  <div class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success rounded-circle mb-3" style="width: 70px; height: 70px;">
                      <i class="fas fa-file-signature fs-2"></i>
                  </div>
                  <h4 class="fw-bold text-dark">Deseja finalizar a assinatura digital?</h4>
                  <p class="text-muted mb-0">Após a confirmação, o documento será lacrado administrativamente.</p>
              </div>
              
              <div class="bg-light p-3 rounded-3 mb-4 text-center border">
                  <a href="diretrizes_assinatura.php" target="_blank" class="text-decoration-none fw-bold" style="color: #1c4b36;">
                      <i class="fas fa-external-link-alt me-1"></i> Ler Diretrizes de Convalidação e Responsabilidade Legal
                  </a>
              </div>

              <form id="formCheckout">
                  <div class="form-check custom-checkbox mb-3 p-3 border rounded border-success bg-success bg-opacity-10">
                      <input class="form-check-input ms-1 me-2 shadow-none border-success" type="checkbox" id="checkDiretrizes" required style="transform: scale(1.3); margin-top: 5px;">
                      <label class="form-check-label fw-bold text-dark" for="checkDiretrizes">
                          Eu afirmo que li e concordo inteiramente com as diretrizes de assinatura digital <span class="text-danger">*</span>
                      </label>
                  </div>
                  <div class="form-check ms-2 mb-4">
                      <input class="form-check-input" type="checkbox" id="checkDownload" checked>
                      <label class="form-check-label text-muted" for="checkDownload">
                          Fazer o download automático do arquivo PDF logo após autenticar
                      </label>
                  </div>
                  
                  <div class="d-grid gap-3 d-md-flex justify-content-md-end pt-3">
                      <button type="button" class="btn btn-light fw-medium px-4 border" data-bs-dismiss="modal">Revisar Documento</button>
                      <button type="button" class="btn btn-sema fw-bold px-5" id="btnAssinarFinal" onclick="finalizarAssinatura()">
                          <i class="fas fa-check-circle me-2"></i> Confirmar Assinatura Técnica
                      </button>
                  </div>
              </form>
          </div>
        </div>
      </div>
    </div>


    <!-- Scripts Essenciais (Bootst. e jQuery já no Footer) -->
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    const reqId = <?= $requerimento_id ?>;
    let currentTemplate = '';

    document.addEventListener('DOMContentLoaded', function() {
        carregarTemplates();
    });

    function initEditor(html, title) {
        document.getElementById('secao-selecao').classList.add('d-none');
        document.getElementById('secao-editor').classList.remove('d-none');
        document.getElementById('editor-title').innerHTML = '<i class="fas fa-edit text-success me-2"></i> Editando: <b>' + title + '</b>';
        
        let editorNode = $('#editor-conteudo'); // O plugin summernote ainda precisa de JQ interno pra instanciar, garantido lá no footer de carregar tudo.
        
        if (editorNode.data('summernote')) {
            editorNode.summernote('destroy');
        }
        editorNode.val(html);
        
        editorNode.summernote({
            height: '100%',
            focus: true,
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'underline', 'clear']],
                ['fontname', ['fontname']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['table', ['table']],
                ['insert', ['link']],
                ['view', ['codeview', 'fullscreen']]
            ],
            callbacks: {
                onInit: function() {
                    document.querySelector('.note-editor').style.height = '100%';
                    document.querySelector('.note-editable').style.height = 'calc(100% - 40px)';
                    document.querySelector('.note-editable').style.overflowY = 'auto';
                }
            }
        });
    }

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
            const listTpl = document.getElementById('lista-templates');
            const listHist = document.getElementById('lista-historico');
            
            if(ret.success && ret.templates) {
                let html = '';
                ret.templates.forEach(t => {
                    let fileKey = t.nome ? t.nome : t;
                    let rawName = t.nome ? t.nome : t.replace('.html', '');
                    rawName = rawName.replace(/_/g, ' ').toUpperCase();

                    let desc = 'Modelo padrão oficial validado para a secretaria. Clique para aplicar o modelo no editor.';
                    let icone = 'fa-file-signature text-secondary';
                    if(rawName.includes('ALVARA') || rawName.includes('CONSTRU')) icone = 'fa-hard-hat text-warning';
                    if(rawName.includes('HABITE') || rawName.includes('DESMEMBRAMENTO')) icone = 'fa-home text-success';
                    if(rawName.includes('LICENCA') || rawName.includes('ECONOMICA')) icone = 'fa-store text-primary';
                    
                    html += `
                    <div class="col-md-3 col-sm-6">
                        <div class="card h-100 template-card rounded-4 border-0 shadow-sm" onclick="selecionarTemplate('${fileKey}', '${rawName}')">
                            <div class="card-body text-center p-4">
                                <div class="icon-placeholder bg-light">
                                    <i class="fas ${icone} fs-2"></i>
                                </div>
                                <h6 class="fw-bold text-dark lh-sm">${rawName}</h6>
                                <div class="preview-miniature">
                                    <i class="fas fa-align-left text-black-50 mb-1"></i> ${desc}
                                </div>
                            </div>
                        </div>
                    </div>`;
                });
                listTpl.innerHTML = html;
            } else {
                listTpl.innerHTML = '<div class="col-12 text-danger">Falha ao carregar os templates do sistema.</div>';
            }

            // Historico Recente
            if(ret.success && ret.historico_recente && ret.historico_recente.length > 0) {
                let htmlHist = '';
                ret.historico_recente.forEach(h => {
                     let rawName = h.label || h.nome;
                     let icone = h.origem == 'db' ? 'fa-file-edit text-primary' : 'fa-file-pdf text-danger';
                     
                     htmlHist += `
                     <div class="col-md-3 col-sm-6">
                        <div class="card h-100 template-card rounded-4 border-0 shadow-sm" style="border-left: 3px solid #1c4b36 !important;" onclick="selecionarTemplate('${h.id}', 'Cópia: ${rawName}')">
                            <div class="card-body text-center p-3">
                                <div class="icon-placeholder rounded-circle" style="width: 45px; height: 45px; background: #f8fafc;">
                                    <i class="fas ${icone} fs-4"></i>
                                </div>
                                <h6 class="fw-bold text-dark text-truncate" title="${rawName}">${rawName}</h6>
                                <small class="text-muted d-block mb-1">Atualizado/Gerado em: <b>${h.data}</b></small>
                                <small class="text-secondary"><i class="fas fa-copy me-1"></i> Usar como base</small>
                            </div>
                        </div>
                     </div>`;
                });
                listHist.innerHTML = htmlHist;
            }
        })
        .catch(err => {
             document.getElementById('lista-templates').innerHTML = '<div class="col-12 text-danger">Falha na conexão com o servidor.</div>';
        });
    }

    function selecionarTemplate(arquivo, nomeLimpo) {
        currentTemplate = arquivo;
        Swal.fire({
            title: 'Preparando espelho documental...',
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
                Swal.fire('Erro', ret.error || 'Erro ao carregar os metadados do processo.', 'error');
            }
        })
        .catch(err => {
            Swal.close();
            Swal.fire('Erro', 'Falha na comunicação com o servidor ao carregar template.', 'error');
        });
    }

    function voltarParaSelecao() {
        let val = document.getElementById('editor-conteudo').value;
        if(val && val.length > 50) {
            Swal.fire({
                title: 'Descartar alterações?',
                text: 'Os conteúdos digitados serão irremediavelmente apagados.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sim, descartar e voltar',
                cancelButtonText: 'Continuar editando'
            }).then((res) => {
                if(res.isConfirmed) {
                    fecharEditor();
                }
            });
        } else {
            fecharEditor();
        }
    }

    function fecharEditor() {
        document.getElementById('secao-editor').classList.add('d-none');
        document.getElementById('secao-selecao').classList.remove('d-none');
        
        // Destroy summernote if active
        if ($('#editor-conteudo').data('summernote')) {
            $('#editor-conteudo').summernote('destroy');
        }
    }

    function abrirModalAssinatura() {
        let htmlContent = $('#editor-conteudo').summernote('code');
        if (!htmlContent || htmlContent.trim() === '' || htmlContent === '<p><br></p>') {
            Swal.fire('Atenção', 'O documento matriz não pode estar vazio.', 'warning');
            return;
        }
        
        let checkDiretrizes = document.getElementById('checkDiretrizes');
        checkDiretrizes.checked = false;
        checkDiretrizes.setCustomValidity('O aceite nas diretrizes é um bloco obrigatório legal.');
        
        var modalConf = new bootstrap.Modal(document.getElementById('modalConfirmacao'));
        modalConf.show();
    }

    document.getElementById('checkDiretrizes').addEventListener('change', function() {
        if(this.checked) this.setCustomValidity('');
        else this.setCustomValidity('O aceite nas diretrizes é obrigatório.');
    });

    function finalizarAssinatura() {
        const form = document.getElementById('formCheckout');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const btn = document.getElementById('btnAssinarFinal');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Autenticando Ofício...';
        
        // Obtendo conteúdo final do editor usando jQ (pois Summernote é restrito JQ)
        let conteudoHtml = $('#editor-conteudo').summernote('code');
        let fazDownload = document.getElementById('checkDownload').checked;
        
        let fd = new FormData();
        fd.append('conteudo_parecer', conteudoHtml);
        fd.append('requerimento_id', reqId);
        fd.append('salvar_banco', 'true');
        fd.append('download', fazDownload);

        fetch('assinatura/processa_assinatura.php', {
            method: 'POST',
            body: fd
        })
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
                        let a = document.createElement('a');
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
                Swal.fire('Erro Interno', ret.error || 'Não foi possível carimbar o documento nas bases governamentais.', 'error');
            }
        })
        .catch(err => {
             btn.disabled = false;
             btn.innerHTML = '<i class="fas fa-check-circle me-2"></i> Confirmar Assinatura Técnica';
             Swal.fire('Falha Crítica', 'Falha estrutural ao registrar no Endpoint de Assinaturas (Erro Nativo JS).', 'error');
        });
    }
    </script>
<?php include 'footer.php'; ?>
