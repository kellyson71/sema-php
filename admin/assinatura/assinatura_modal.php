<!-- Modal de Assinatura Simplificada -->
<div class="modal fade" id="assinaturaModal" tabindex="-1" aria-labelledby="assinaturaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assinaturaModalLabel"><i class="fas fa-file-signature me-2"></i>Assinar e Gerar PDF</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="assinatura/processa_assinatura.php" method="POST" id="formAssinatura">
                <div class="modal-body">
                    <!-- Passa IDs ocultos, caso necessário -->
                    <input type="hidden" name="requerimento_id" id="req_id_assinatura" value="">
                    
                    <div class="mb-3">
                        <label for="conteudo_parecer" class="form-label">Conteúdo do Documento/Parecer</label>
                        <textarea class="form-control" id="conteudo_parecer" name="conteudo_parecer" rows="15" required placeholder="Digite aqui o conteúdo principal do seu parecer..."></textarea>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> 
                        Sua assinatura institucional (Nome, Cargo e Data) será fixada automaticamente no canto inferior direito do PDF gerado.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-file-pdf me-2"></i> Assinar e Baixar PDF
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Se quiser pegar IDs específicos antes de abrir:
    function abrirModalAssinatura(requerimentoId) {
        if(requerimentoId) {
            document.getElementById('req_id_assinatura').value = requerimentoId;
        }
        var authModal = new bootstrap.Modal(document.getElementById('assinaturaModal'));
        authModal.show();
    }
</script>
