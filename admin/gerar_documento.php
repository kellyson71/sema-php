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
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo_pagina; ?> - SEMA</title>
    <!-- Assets Essenciais -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f4f6f9; overflow-x: hidden; }
        
        /* Navbar Superior Minimalista */
        .doc-navbar {
            background-color: white;
            border-bottom: 1px solid #e0e4e8;
            padding: 12px 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

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
            border-bottom-color: #1c4b36; 
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
        #secao-editor { height: calc(100vh - 70px); background: #f4f6f9; }
        .editor-container-wrapper {
            height: calc(100% - 70px);
            background: white;
            border-radius: 8px;
            border: 1px solid #e0e4e8;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin: 0 24px;
        }
        
        /* Modal Custom */
        .modal-header-sema { background-color: #f8fafc; border-bottom: 1px solid #e2e8f0; }
        .text-sema { color: #1c4b36 !important; }
        .btn-sema { background-color: #1c4b36; border-color: #1c4b36; color: white; }
        .btn-sema:hover { background-color: #143627; border-color: #143627; color: white; }
    </style>
</head>
<body>

    <!-- Navegação de Topo Limpa -->
    <div class="doc-navbar">
        <div class="d-flex align-items-center">
            <a href="visualizar_requerimento.php?id=<?= $requerimento_id ?>" class="btn btn-sm btn-light border fw-medium px-3 text-secondary me-4">
                <i class="fas fa-arrow-left me-2"></i> Voltar ao Processo
            </a>
            <h5 class="mb-0 fw-bold text-dark">
                <i class="fas fa-file-signature text-success me-2"></i> Emissão de Documento Oficial
            </h5>
        </div>
        <div>
            <span class="badge bg-secondary px-3 py-2 rounded-pill fw-medium">Protocolo #<?= htmlspecialchars($req['protocolo']) ?></span>
        </div>
    </div>

    <!-- ETAPA 1: Seleção de Templates e Histórico -->
    <div class="container-fluid py-5 px-5" id="secao-selecao">
        
        <h5 class="fw-bold mb-4 text-dark"><i class="fas fa-plus-square text-primary me-2"></i> Criar Novo Documento (Templates)</h5>
        
        <!-- Grid Carregado via AJAX -->
        <div class="row g-4 mb-5" id="lista-templates">
            <div class="col-12 text-center text-muted py-4">
                <div class="spinner-border spinner-border-sm me-2" role="status"></div> Carregando acervo oficial...
            </div>
        </div>

        <?php if (!empty($pastDocs)): ?>
        <hr class="text-black-50 my-5">
        <h5 class="fw-bold mb-4 text-dark"><i class="fas fa-history text-warning me-2"></i> Documentos Anteriores Deste Processo</h5>
        <div class="row g-4">
            <?php foreach ($pastDocs as $doc): ?>
            <div class="col-md-3">
                <div class="card h-100 template-card rounded-4 border-0" onclick="alert('Estes documentos originais e assinados não podem ser alterados. Baixe o PDF no Histórico de Respostas do processo para visualizá-lo.')">
                    <div class="card-body text-center p-4">
                        <div class="icon-placeholder bg-light">
                            <i class="fas fa-file-pdf fs-3 text-danger"></i>
                        </div>
                        <h6 class="fw-bold text-dark text-truncate" title="<?= htmlspecialchars(ucfirst($doc['tipo_documento'] ?? 'Parecer Legal')) ?>">
                            <?= htmlspecialchars(ucfirst($doc['tipo_documento'] ?? 'Parecer Legal')) ?>
                        </h6>
                        <small class="text-muted d-block mb-1">Assinado por: <b><?= htmlspecialchars($doc['assinante_nome']) ?></b></small>
                        <small class="text-muted"><i class="fas fa-clock"></i> <?= date('d/m/Y \à\s H:i', strtotime($doc['timestamp_assinatura'])) ?></small>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
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


    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    const reqId = <?= $requerimento_id ?>;
    let currentTemplate = '';

    $(document).ready(function() {
        carregarTemplates();
    });

    function initEditor(html, title) {
        $('#secao-selecao').addClass('d-none');
        $('#secao-editor').removeClass('d-none');
        $('#editor-title').html('<i class="fas fa-edit text-success me-2"></i> Editando: <b>' + title + '</b>');
        
        let editorNode = $('#editor-conteudo');
        
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
                ['view', ['codeview']]
            ],
            callbacks: {
                onInit: function() {
                    // Force height inside wrapper
                    $('.note-editor').css('height', '100%');
                    $('.note-editable').css('height', 'calc(100% - 40px)').css('overflow-y', 'auto');
                }
            }
        });
    }

    function carregarTemplates() {
        $.post('parecer_handler.php', { action: 'listar_templates' }, function(ret) {
            if(ret.success && ret.templates) {
                let html = '';
                ret.templates.forEach(t => {
                    let rawName = t.replace('.html', '').replace(/_/g, ' ').toUpperCase();
                    let desc = 'Formulário padrão validado pelo plano diretor ambiental. Clique para aplicar o modelo no editor de tela cheia.';
                    
                    let icone = 'fa-file-signature text-secondary';
                    if(rawName.includes('ALVARA') || rawName.includes('CONSTRU')) icone = 'fa-hard-hat text-warning';
                    if(rawName.includes('HABITE') || rawName.includes('DESMEMBRAMENTO')) icone = 'fa-home text-success';
                    if(rawName.includes('LICENCA') || rawName.includes('ECONOMICA')) icone = 'fa-store text-primary';
                    
                    html += `
                    <div class="col-md-3 col-sm-6">
                        <div class="card h-100 template-card rounded-4 border-0 shadow-sm" onclick="selecionarTemplate('${t}', '${rawName}')">
                            <div class="card-body text-center p-4">
                                <div class="icon-placeholder">
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
                $('#lista-templates').html(html);
            } else {
                $('#lista-templates').html('<div class="col-12 text-danger">Falha ao carregar os templates do sistema.</div>');
            }
        }, 'json').fail(function(){
            $('#lista-templates').html('<div class="col-12 text-danger">Falha na conexão com o servidor.</div>');
        });
    }

    function selecionarTemplate(arquivo, nomeLimpo) {
        currentTemplate = arquivo;
        Swal.fire({
            title: 'Preparando espelho documental...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });
        
        $.post('parecer_handler.php', {
            action: 'carregar_template',
            template: arquivo,
            requerimento_id: reqId,
            origem: 'tecnico'
        }, function(ret) {
            Swal.close();
            if (ret.success) {
                initEditor(ret.html, nomeLimpo);
            } else {
                Swal.fire('Erro', ret.error || 'Erro ao carregar os metadados do processo.', 'error');
            }
        }, 'json').fail(function() {
            Swal.close();
            Swal.fire('Erro', 'Falha na comunicação com o servidor ao carregar template.', 'error');
        });
    }

    function voltarParaSelecao() {
        let val = $('#editor-conteudo').val();
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
        $('#secao-editor').addClass('d-none');
        $('#secao-selecao').removeClass('d-none');
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
        
        $('#checkDiretrizes').prop('checked', false);
        document.getElementById('checkDiretrizes').setCustomValidity('O aceite nas diretrizes é um bloco obrigatório legal.');
        
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

        const btn = $('#btnAssinarFinal');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> Autenticando Ofício...');
        
        let conteudoHtml = $('#editor-conteudo').summernote('code');
        let fazDownload = $('#checkDownload').is(':checked');

        $.ajax({
            url: 'assinatura/processa_assinatura.php', 
            type: 'POST',
            data: {
                conteudo_parecer: conteudoHtml,
                requerimento_id: reqId,
                origem: 'tecnico',
                salvar_banco: true,    // Envia flag pro PHP renderizar para disco (`F`) e registrar no Postgres
                download: fazDownload
            },
            dataType: 'json',
            success: function(ret) {
                btn.prop('disabled', false).html('<i class="fas fa-check-circle me-2"></i> Confirmar Assinatura Técnica');
                
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
            },
            error: function(xhr) {
                 btn.prop('disabled', false).html('<i class="fas fa-check-circle me-2"></i> Confirmar Assinatura Técnica');
                 console.error(xhr.responseText);
                 Swal.fire('Falha Crítica do Autenticador', xhr.responseText || 'Erro ao conectar no Endpoint de Assinaturas (Status 500). Verifique Log Central.', 'error');
            }
        });
    }
    </script>

</body>
</html>
