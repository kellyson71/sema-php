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
                       WHERE r.id = ? AND r.status IN ('Apto a gerar alvará', 'Alvará Emitido')");
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
// Buscar TODOS os documentos (parecer/alvará) gerados para este requerimento
// Agrupando por nome do arquivo para evitar duplicatas de assinaturas no mesmo arquivo
$stmtDoc = $pdo->prepare("SELECT * FROM assinaturas_digitais 
                          WHERE requerimento_id = ? 
                          GROUP BY nome_arquivo 
                          ORDER BY timestamp_assinatura DESC");
$stmtDoc->execute([$id]);
$documentosAnteriores = $stmtDoc->fetchAll(PDO::FETCH_ASSOC);

$documentoIdParaVisualizar = !empty($documentosAnteriores) ? $documentosAnteriores[0]['documento_id'] : null;

// Se não houver documento anterior, algo está errado no fluxo (técnico não gerou?)
// Mas vamos prosseguir permitindo ver o requerimento pelo menos.

include 'header.php';
?>

<div class="container-fluid py-4 h-100">
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'verificacao_expirada'): ?>
        <div class="alert alert-warning d-flex align-items-center mb-4" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            Sua verificação expirou. Envie um novo código para assinar.
        </div>
    <?php endif; ?>
    <div class="row h-100">
        <!-- Coluna Esquerda: Informações -->
        <div class="col-md-4 col-lg-3 d-flex flex-column gap-3">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0 text-primary"><i class="fas fa-info-circle me-2"></i>Resumo do Processo</h5>
                </div>
                <!-- ... (Details Section remains same, skipped for brevity in replace, assume context handles it if I don't touch it. Wait I need to match context) ... --> 
                <!-- Actually, I need to include the card body content if I am replacing the whole block or be precise. 
                     The user instruction says "Fetch all documents...". I will target the PHP block and the Document Viewer Column. 
                     I will split this into smaller reliable edits or replace a large chunk. 
                     Let's replace the PHP block first, then the Viewer column. -->                
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
                    <div class="mb-3">
                        <label class="text-muted small text-uppercase fw-bold mb-2">Documentos no Processo</label>
                        <?php if (count($documentosAnteriores) > 0): ?>
                            <div class="list-group list-group-flush small">
                                <?php foreach ($documentosAnteriores as $index => $doc): ?>
                                    <button type="button" 
                                            class="list-group-item list-group-item-action d-flex align-items-center <?php echo $index === 0 ? 'active' : ''; ?>"
                                            onclick="trocarDocumento(this, '<?php echo $doc['documento_id']; ?>', '<?php echo htmlspecialchars($doc['nome_arquivo']); ?>')">
                                        <i class="fas fa-file-pdf me-2"></i>
                                        <span class="text-truncate"><?php echo htmlspecialchars($doc['nome_arquivo']); ?></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning mb-0">
                                Nenhum documento técnico encontrado para este processo.
                            </div>
                        <?php endif; ?>
                    </div>

                    <hr>

                    <div class="d-grid gap-2">
                        <?php if ($requerimento['status'] === 'Alvará Emitido'): ?>
                            <div class="alert alert-success text-center mb-2">
                                <i class="fas fa-check-double mb-2 d-block fa-2x"></i>
                                <strong>Alvará Emitido</strong><br>
                                <small>Todos os documentos foram assinados.</small>
                            </div>
                        <?php else: ?>
                            <small class="text-center text-muted mb-1">Ações de Decisão</small>
                            <div id="alert-assinatura" class="alert d-none mb-2" role="alert"></div>
                            <form action="processar_assinatura_secretario.php" method="POST" id="form-assinar-secretario">
                                <input type="hidden" name="requerimento_id" value="<?php echo $id; ?>">
                                <input type="hidden" name="acao" value="aprovar">
                                <button type="button" class="btn btn-success w-100 py-2 fw-bold text-uppercase shadow-sm" onclick="iniciarVerificacaoAssinaturaSecretario()">
                                    <i class="fas fa-file-signature me-2"></i> Assinar Tudo e Emitir
                                </button>
                            </form>

                            <button type="button" class="btn btn-outline-danger w-100 mt-2" data-bs-toggle="modal" data-bs-target="#modalDevolucao">
                                <i class="fas fa-undo me-2"></i> Solicitar Correção
                            </button>
                        <?php endif; ?>
                        
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
                        <span class="badge bg-primary" id="badge-doc-id">Documento: <?php echo $documentoIdParaVisualizar; ?></span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark">Documento Preliminar não encontrado</span>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0 bg-secondary bg-opacity-10 d-flex justify-content-center align-items-center overflow-hidden">
                    <?php if ($documentoIdParaVisualizar): ?>
                        <iframe src="parecer_viewer.php?id=<?php echo $documentoIdParaVisualizar; ?>&noprint=1" 
                                style="width: 100%; height: 100%; border: none;" 
                                id="iframe-viewer"
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

<script>
function trocarDocumento(element, docId, nomeArquivo) {
    const iframe = document.getElementById('iframe-viewer');
    if (iframe) {
        iframe.src = 'parecer_viewer.php?id=' + docId + '&noprint=1';
    }
    const badge = document.getElementById('badge-doc-id');
    if (badge) {
        badge.textContent = 'Documento: ' + docId;
        if (nomeArquivo) {
            badge.setAttribute('title', nomeArquivo);
        }
    }
    document.querySelectorAll('.list-group-item').forEach(el => el.classList.remove('active'));
    element.classList.add('active');
}

let modalVerificacaoSecretario = null;

function mostrarAvisoSecretario(tipo, texto) {
    const alerta = document.getElementById('alert-assinatura');
    if (!alerta) return;
    alerta.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning', 'alert-info');
    alerta.classList.add('alert-' + tipo);
    alerta.textContent = texto;
}

async function verificarSessaoAssinaturaSecretario() {
    try {
        const response = await fetch('parecer_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'verificar_sessao_assinatura' })
        });
        const data = await response.json();
        return data.success && data.sessao_valida;
    } catch (error) {
        return false;
    }
}

async function iniciarVerificacaoAssinaturaSecretario() {
    const temDocumento = <?php echo $documentoIdParaVisualizar ? 'true' : 'false'; ?>;
    if (!temDocumento) {
        mostrarAvisoSecretario('warning', 'Não é possível assinar sem documentos gerados.');
        return;
    }
    const sessaoValida = await verificarSessaoAssinaturaSecretario();
    if (sessaoValida) {
        if (confirm('Confirmar assinatura de todos os documentos e emissão do alvará?')) {
            document.getElementById('form-assinar-secretario').submit();
        }
        return;
    }
    if (!modalVerificacaoSecretario) {
        modalVerificacaoSecretario = new bootstrap.Modal(document.getElementById('modalVerificacaoSeguranca'));
    }
    document.getElementById('etapa-enviar-codigo').style.display = 'block';
    document.getElementById('etapa-validar-codigo').style.display = 'none';
    document.getElementById('codigo_verificacao').value = '';
    document.getElementById('codigo_verificacao').classList.remove('is-invalid');
    modalVerificacaoSecretario.show();
}

async function enviarCodigoVerificacao() {
    const btn = document.querySelector('#etapa-enviar-codigo button');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enviando...';
    try {
        const response = await fetch('parecer_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ 
                action: 'enviar_codigo_assinatura',
                origem: 'secretario',
                requerimento_id: <?php echo (int)$id; ?>
            })
        });
        const data = await response.json();
        if (data.success) {
            document.getElementById('email-mascarado-display').textContent = data.email_mascarado;
            document.getElementById('etapa-enviar-codigo').style.display = 'none';
            document.getElementById('etapa-validar-codigo').style.display = 'block';
        } else {
            mostrarAvisoSecretario('danger', data.error || 'Falha ao enviar email.');
        }
    } catch (error) {
        mostrarAvisoSecretario('danger', 'Erro de conexão ao enviar código.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

function voltarEnviarCodigo() {
    document.getElementById('etapa-enviar-codigo').style.display = 'block';
    document.getElementById('etapa-validar-codigo').style.display = 'none';
}

async function validarCodigoVerificacao() {
    const input = document.getElementById('codigo_verificacao');
    const btn = document.querySelector('#etapa-validar-codigo .btn-success');
    const codigo = input.value.trim();
    if (codigo.length !== 6) {
        input.classList.add('is-invalid');
        document.getElementById('erro-codigo').textContent = 'O código deve ter 6 dígitos.';
        return;
    }
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Validando...';
    try {
        const response = await fetch('parecer_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ 
                action: 'validar_codigo_assinatura',
                codigo: codigo,
                origem: 'secretario',
                requerimento_id: <?php echo (int)$id; ?>
            })
        });
        const data = await response.json();
        if (data.success) {
            if (modalVerificacaoSecretario) modalVerificacaoSecretario.hide();
            document.getElementById('form-assinar-secretario').submit();
        } else {
            input.classList.add('is-invalid');
            document.getElementById('erro-codigo').textContent = data.error || 'Código inválido.';
        }
    } catch (error) {
        mostrarAvisoSecretario('danger', 'Erro de conexão ao validar código.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}
</script>

<div class="modal fade" id="modalVerificacaoSeguranca" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-white border-bottom-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center px-4 pb-4">
                <div class="mb-4">
                    <div class="rounded-circle bg-success bg-opacity-10 d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                        <i class="fas fa-shield-alt text-success" style="font-size: 40px;"></i>
                    </div>
                </div>
                <h4 class="fw-bold mb-2">Verificação de Segurança</h4>
                <p class="text-muted mb-4">Para sua segurança, precisamos confirmar sua identidade antes de prosseguir com a assinatura digital.</p>
                <div id="etapa-enviar-codigo">
                    <button onclick="enviarCodigoVerificacao()" class="btn btn-primary w-100 py-2 mb-3 d-flex align-items-center justify-content-center gap-2">
                        <i class="fas fa-paper-plane"></i>
                        Enviar código para meu email
                    </button>
                    <p class="small text-muted mb-0">
                        Um código de 6 dígitos será enviado para seu email cadastrado.
                    </p>
                </div>
                <div id="etapa-validar-codigo" style="display: none;">
                    <p class="small text-muted mb-3">
                        Enviamos um código para <strong id="email-mascarado-display">...</strong>
                    </p>
                    <div class="mb-3">
                        <input type="text" id="codigo_verificacao" class="form-control form-control-lg text-center fw-bold letter-spacing-lg" placeholder="000 000" maxlength="6" style="letter-spacing: 5px; font-size: 24px;">
                        <div class="invalid-feedback text-start" id="erro-codigo">
                            Código incorreto.
                        </div>
                    </div>
                    <button onclick="validarCodigoVerificacao()" class="btn btn-success w-100 py-2 mb-3">
                        <i class="fas fa-check-circle me-2"></i>
                        Validar Código
                    </button>
                    <button onclick="voltarEnviarCodigo()" class="btn btn-link text-muted btn-sm text-decoration-none">
                        Reenviar código
                    </button>
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
