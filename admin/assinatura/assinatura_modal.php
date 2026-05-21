<!-- Modal de Assinatura -->
<div class="modal fade" id="assinaturaModal" tabindex="-1" aria-labelledby="assinaturaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assinaturaModalLabel"><i class="fas fa-file-signature me-2"></i>Assinar e Gerar PDF</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="assinatura/processa_assinatura.php" method="POST" id="formAssinatura">
                <div class="modal-body">
                    <!-- Passa IDs ocultos -->
                    <input type="hidden" name="requerimento_id" id="req_id_assinatura" value="">
                    <input type="hidden" name="modo_assinatura" id="hidden_modo_assinatura" value="assinar">

                    <!-- Seletor de modo -->
                    <div class="assinatura-modo-selector" style="display:flex;gap:10px;margin-bottom:20px;">
                        <label class="assinatura-modo-card selected" data-modo="assinar" style="flex:1;border:2px solid #16a34a;border-radius:12px;padding:14px 12px;cursor:pointer;text-align:center;background:#f0fdf4;transition:all .15s;">
                            <input type="radio" name="modo_assinatura_radio" value="assinar" checked style="display:none;">
                            <div style="font-size:1.3rem;margin-bottom:6px;">🖋️</div>
                            <div style="font-weight:700;font-size:.85rem;color:#16a34a;">Assinar e finalizar</div>
                            <div style="font-size:.73rem;color:#6b7280;margin-top:4px;">Gera PDF com bloco de assinatura</div>
                        </label>
                        <label class="assinatura-modo-card" data-modo="sem_assinar" style="flex:1;border:2px solid #e5e7eb;border-radius:12px;padding:14px 12px;cursor:pointer;text-align:center;background:#f9fafb;transition:all .15s;">
                            <input type="radio" name="modo_assinatura_radio" value="sem_assinar" style="display:none;">
                            <div style="font-size:1.3rem;margin-bottom:6px;">📄</div>
                            <div style="font-weight:700;font-size:.85rem;color:#374151;">Finalizar sem assinar</div>
                            <div style="font-size:.73rem;color:#6b7280;margin-top:4px;">PDF sem bloco de assinatura</div>
                        </label>
                        <label class="assinatura-modo-card" data-modo="assinar_e_requisitar" style="flex:1;border:2px solid #e5e7eb;border-radius:12px;padding:14px 12px;cursor:pointer;text-align:center;background:#f9fafb;transition:all .15s;">
                            <input type="radio" name="modo_assinatura_radio" value="assinar_e_requisitar" style="display:none;">
                            <div style="font-size:1.3rem;margin-bottom:6px;">👥</div>
                            <div style="font-weight:700;font-size:.85rem;color:#1d4ed8;">Assinar e requisitar</div>
                            <div style="font-size:.73rem;color:#6b7280;margin-top:4px;">Assina + pede co-assinatura</div>
                        </label>
                    </div>

                    <!-- Painel expandível para co-assinatura (só visível quando modo=assinar_e_requisitar) -->
                    <div id="painelCoAssinatura" style="display:none;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:14px;margin-bottom:16px;">
                        <label class="fw-semibold" style="font-size:.85rem;margin-bottom:6px;display:block;">Solicitar co-assinatura de:</label>
                        <select name="coassinatura_destinatario_id" class="form-select form-select-sm mb-2">
                            <option value="">— Selecione um administrador —</option>
                            <?php
                            $stmtAdmins = $pdo->query("SELECT id, nome, nivel FROM administradores WHERE ativo = 1 AND id != " . (int)($_SESSION['admin_id'] ?? 0) . " ORDER BY nome");
                            foreach ($stmtAdmins->fetchAll() as $adm) {
                                echo '<option value="' . htmlspecialchars($adm['id']) . '">' . htmlspecialchars($adm['nome']) . ' (' . htmlspecialchars($adm['nivel']) . ')</option>';
                            }
                            ?>
                        </select>
                        <textarea name="coassinatura_mensagem" class="form-control form-control-sm" rows="2"
                                  placeholder="Mensagem para o destinatário (opcional)..."
                                  style="font-size:.82rem;resize:none;"></textarea>
                    </div>

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
                    <button type="submit" class="btn btn-success btn-assinar-submit">
                        <i class="fas fa-signature me-2"></i>Assinar documento
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Seletor de modo de assinatura
    document.querySelectorAll('.assinatura-modo-card').forEach(function(card) {
        card.addEventListener('click', function() {
            // Resetar todos os cards
            document.querySelectorAll('.assinatura-modo-card').forEach(function(c) {
                c.style.border = '2px solid #e5e7eb';
                c.style.background = '#f9fafb';
                var label = c.querySelector('div:nth-child(2)');
                if (label) label.style.color = '#374151';
            });

            // Ativar card clicado
            var modo = this.dataset.modo;
            if (modo === 'assinar') {
                this.style.border = '2px solid #16a34a';
                this.style.background = '#f0fdf4';
                var lbl = this.querySelector('div:nth-child(2)');
                if (lbl) lbl.style.color = '#16a34a';
            } else if (modo === 'sem_assinar') {
                this.style.border = '2px solid #6b7280';
                this.style.background = '#f3f4f6';
                var lbl = this.querySelector('div:nth-child(2)');
                if (lbl) lbl.style.color = '#374151';
            } else {
                this.style.border = '2px solid #1d4ed8';
                this.style.background = '#eff6ff';
                var lbl = this.querySelector('div:nth-child(2)');
                if (lbl) lbl.style.color = '#1d4ed8';
            }

            // Mostrar/ocultar painel de co-assinatura
            document.getElementById('painelCoAssinatura').style.display =
                modo === 'assinar_e_requisitar' ? 'block' : 'none';

            // Atualizar hidden field e label do botão de submit
            document.getElementById('hidden_modo_assinatura').value = modo;
            var btnLabels = {
                'assinar':              '<i class="fas fa-signature me-2"></i>Assinar documento',
                'sem_assinar':          '<i class="fas fa-file me-2"></i>Finalizar sem assinar',
                'assinar_e_requisitar': '<i class="fas fa-users me-2"></i>Assinar e solicitar co-assinatura'
            };
            var btnSubmit = document.querySelector('#assinaturaModal .btn-assinar-submit');
            if (btnSubmit && btnLabels[modo]) {
                btnSubmit.innerHTML = btnLabels[modo];
            }
        });
    });

    // Inicializar cor do card "Assinar e finalizar" (selecionado por padrão)
    (function() {
        var cardAssinar = document.querySelector('.assinatura-modo-card[data-modo="assinar"]');
        if (cardAssinar) {
            var lbl = cardAssinar.querySelector('div:nth-child(2)');
            if (lbl) lbl.style.color = '#16a34a';
        }
    })();

    // Função pública para abrir o modal
    function abrirModalAssinatura(requerimentoId) {
        if (requerimentoId) {
            document.getElementById('req_id_assinatura').value = requerimentoId;
        }
        var authModal = new bootstrap.Modal(document.getElementById('assinaturaModal'));
        authModal.show();
    }
</script>
