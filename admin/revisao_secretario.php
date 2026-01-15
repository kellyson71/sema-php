<?php
require_once 'conexao.php';
verificaLogin();

// Verificar permissão
if (!($_SESSION['admin_nivel'] === 'secretario' || $_SESSION['admin_email'] === 'secretario@sema.rn.gov.br')) {
    header("Location: index.php");
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: secretario_dashboard.php");
    exit;
}

// Buscar dados do requerimento
$stmt = $pdo->prepare("SELECT r.*, req.nome as requerente_nome, req.cpf_cnpj as requerente_doc 
                       FROM requerimentos r 
                       JOIN requerentes req ON r.requerente_id = req.id 
                       WHERE r.id = ? AND r.status = 'Apto a gerar alvará'");
$stmt->execute([$id]);
$requerimento = $stmt->fetch();

if (!$requerimento) {
    // Se não encontrou ou status não é compatível, volta
    header("Location: secretario_dashboard.php");
    exit;
}

// Buscar o documento (parecer/alvará) já gerado e assinado pelo técnico
// Assumindo que é o último documento gerado do tipo 'parecer' ou similar
// Ou idealmente buscamos na tabela documentos se houver, ou assinaturas_digitais
$stmtDoc = $pdo->prepare("SELECT * FROM assinaturas_digitais WHERE requerimento_id = ? ORDER BY timestamp_assinatura DESC LIMIT 1");
$stmtDoc->execute([$id]);
$documentoAnterior = $stmtDoc->fetch();

$documentoIdParaVisualizar = $documentoAnterior ? $documentoAnterior['documento_id'] : null;

// Se não houver documento anterior, algo está errado no fluxo (técnico não gerou?)
// Mas vamos prosseguir permitindo ver o requerimento pelo menos.

include 'header.php';
?>

<div class="container-fluid py-4 h-100">
    <div class="row h-100">
        <!-- Coluna Esquerda: Informações -->
        <div class="col-md-4 col-lg-3 d-flex flex-column gap-3">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0 text-primary"><i class="fas fa-info-circle me-2"></i>Resumo do Processo</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="text-muted small text-uppercase fw-bold">Protocolo</label>
                        <div class="fs-5 fw-bold text-dark">#<?php echo htmlspecialchars($requerimento['protocolo']); ?></div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="text-muted small text-uppercase fw-bold">Requerente</label>
                        <div class="fw-medium"><?php echo htmlspecialchars($requerimento['requerente_nome']); ?></div>
                        <div class="small text-muted"><?php echo htmlspecialchars($requerimento['requerente_doc']); ?></div>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small text-uppercase fw-bold">Tipo de Solicitação</label>
                        <div><span class="badge bg-secondary"><?php echo htmlspecialchars($requerimento['tipo_alvara']); ?></span></div>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small text-uppercase fw-bold">Endereço</label>
                        <div class="small"><?php echo htmlspecialchars($requerimento['endereco_objetivo']); ?></div>
                    </div>

                    <hr>

                    <div class="d-grid gap-2">
                        <small class="text-center text-muted mb-1">Ações de Decisão</small>
                        
                        <form action="processar_assinatura_secretario.php" method="POST" onsubmit="return confirm('Confirmar assinatura e emissão do Alvará?');">
                            <input type="hidden" name="requerimento_id" value="<?php echo $id; ?>">
                            <input type="hidden" name="acao" value="aprovar">
                            <button type="submit" class="btn btn-success w-100 py-2 fw-bold text-uppercase shadow-sm">
                                <i class="fas fa-file-signature me-2"></i> Assinar e Emitir
                            </button>
                        </form>

                        <button type="button" class="btn btn-outline-danger w-100 mt-2" data-bs-toggle="modal" data-bs-target="#modalDevolucao">
                            <i class="fas fa-undo me-2"></i> Solicitar Correção
                        </button>
                        
                        <a href="secretario_dashboard.php" class="btn btn-link text-muted mt-2">
                            <i class="fas fa-arrow-left me-1"></i> Voltar à lista
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Coluna Direita: Visualizador -->
        <div class="col-md-8 col-lg-9">
            <div class="card shadow-sm border-0 h-100" style="min-height: 800px;">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-secondary"><i class="fas fa-file-alt me-2"></i>Visualização do Documento</h5>
                    <?php if ($documentoIdParaVisualizar): ?>
                        <span class="badge bg-primary">Documento Gerado: <?php echo $documentoIdParaVisualizar; ?></span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark">Documento Preliminar não encontrado</span>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0 bg-secondary bg-opacity-10 d-flex justify-content-center align-items-center overflow-hidden">
                    <?php if ($documentoIdParaVisualizar): ?>
                        <iframe src="parecer_viewer.php?id=<?php echo $documentoIdParaVisualizar; ?>&noprint=1" 
                                style="width: 100%; height: 100%; border: none;" 
                                title="Visualizador de Documento"></iframe>
                    <?php else: ?>
                        <div class="text-center text-muted">
                            <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                            <p>Não foi possível carregar o documento prévio para visualização.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Devolução -->
<div class="modal fade" id="modalDevolucao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Solicitar Correção</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="processar_assinatura_secretario.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="requerimento_id" value="<?php echo $id; ?>">
                    <input type="hidden" name="acao" value="corrigir">
                    
                    <div class="mb-3">
                        <label class="form-label">Motivo da devolução / Observações</label>
                        <textarea class="form-control" name="observacao" rows="4" required placeholder="Descreva o que precisa ser ajustado pelo setor técnico..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Devolver Processo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
