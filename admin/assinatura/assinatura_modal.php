<!-- Modal de Geração de Parecer e Assinatura via TCPDF -->
<div class="modal fade" id="parecerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-file-contract text-info me-2"></i>
                    Gerar Parecer Técnico e Assinar
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formNovaAssinatura" action="assinatura/processa_assinatura.php" method="POST">
                    <input type="hidden" name="requerimento_id" id="modal_ass_req_id" value="<?php echo htmlspecialchars($id); ?>">
                    
                    <div class="alert alert-info mb-3">
                        <i class="fas fa-info-circle me-2"></i>
                        A assinatura digital padrão constará **automaticamente no canto inferior direito** do documento. Use o editor abaixo para redigir ou colar o conteúdo técnico pertinente.
                    </div>

                    <!-- Seleção de Template -->
                    <div class="mb-3">
                        <label class="form-label font-weight-bold">Template Predefinido (opcional):</label>
                        <div class="d-flex gap-2">
                            <select id="template-select" class="form-select"></select>
                            <button type="button" class="btn btn-outline-primary" onclick="carregarTemplateParaEdicao()" title="Aplicar template no editor">
                                <i class="fas fa-file-import"></i> Aplicar
                            </button>
                        </div>
                    </div>

                    <!-- Editor de Conteúdo -->
                    <div class="mb-3">
                        <label class="form-label font-weight-bold">Redação do Parecer/Documento:</label>
                        <textarea id="editor-parecer-content" name="conteudo_documento" required></textarea>
                    </div>

                    <!-- Validação de Assinatura -->
                    <div class="card mb-3 border-success mb-3">
                        <div class="card-body">
                            <h6 class="card-title text-success"><i class="fas fa-lock me-2"></i>Validação de Identidade</h6>
                            <p class="small text-muted mb-2">
                                Ao confirmar, você estará anexando sua assinatura digital oficial e juridicamente válida a este processo, com a geração imediata do PDF.
                            </p>
                        </div>
                    </div>

                    <div class="d-flex gap-2 justify-content-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </button>
                        <button type="submit" class="btn btn-success" id="btn-gerar-assinar">
                            <i class="fas fa-check-circle me-2"></i>Assinar e Gerar PDF
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Intercepta o envio do formulário para forçar o download diretamente e fechar o modal
    document.getElementById('formNovaAssinatura').addEventListener('submit', function(e) {
        
        const btn = document.getElementById('btn-gerar-assinar');
        const originalHtml = btn.innerHTML;
        
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Gerando PDF...';
        btn.disabled = true;

        // Como o botão pode acionar download de arquivo via POST (Content-Disposition: attachment),
        // uma forma clássica é apenas deixar o formulário enviar e dar o disable temporário
        
        setTimeout(() => {
            btn.innerHTML = originalHtml;
            btn.disabled = false;
            
            // Fecha modal
            var myModalEl = document.getElementById('parecerModal');
            var modal = bootstrap.Modal.getInstance(myModalEl);
            if(modal) {
                modal.hide();
            }

            // A página já deverá estar atualizada ou podemos dar um reload opcional
            // setTimeout(() => window.location.reload(), 1500); 
        }, 2000);
    });

    // Função para abrir este modal do zero
    function abrirModalParecer() {
        // Tenta preencher templates se a função existir
        if(typeof carregarTemplates === 'function') {
            carregarTemplates();
        }

        // Se usar tinyMCE
        if (typeof tinymce !== 'undefined') {
            let editor = tinymce.get('editor-parecer-content');
            if(editor) {
                editor.setContent('');
            }
        } else {
            document.getElementById('editor-parecer-content').value = '';
        }

        var myModal = new bootstrap.Modal(document.getElementById('parecerModal'), {
            backdrop: 'static'
        });
        myModal.show();
    }
</script>
