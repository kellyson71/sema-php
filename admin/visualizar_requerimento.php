<?php
require_once 'conexao.php';
verificaLogin();

// Verificar se o ID foi fornecido
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: requerimentos.php");
    exit;
}

$id = (int)$_GET['id'];

// Marcar o requerimento como visualizado
$stmtVisualizado = $pdo->prepare("UPDATE requerimentos SET visualizado = 1 WHERE id = ?");
$stmtVisualizado->execute([$id]);

// Processar atualização de status
$mensagem = '';
$mensagemTipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status']) && isset($_POST['observacoes'])) {
    $novoStatus = $_POST['status'];
    $observacoes = $_POST['observacoes'];

    try {
        $pdo->beginTransaction();

        // Atualizar status e observações do requerimento
        $stmt = $pdo->prepare("UPDATE requerimentos SET status = ?, observacoes = ?, data_atualizacao = NOW() WHERE id = ?");
        $stmt->execute([$novoStatus, $observacoes, $id]);

        // Registrar no histórico de ações
        $acao = "Alterou status para '{$novoStatus}'";
        if (!empty($observacoes)) {
            $acao .= " com a observação: {$observacoes}";
        }

        $stmt = $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['admin_id'], $id, $acao]);

        $pdo->commit();

        $mensagem = "Status do requerimento atualizado com sucesso!";
        $mensagemTipo = "success";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $mensagem = "Erro ao atualizar status: " . $e->getMessage();
        $mensagemTipo = "danger";
    }
}

// Buscar dados do requerimento
$stmt = $pdo->prepare("
    SELECT r.*, 
           req.nome as requerente_nome, 
           req.cpf_cnpj as requerente_cpf_cnpj, 
           req.telefone as requerente_telefone, 
           req.email as requerente_email,
           p.nome as proprietario_nome,
           p.cpf_cnpj as proprietario_cpf_cnpj
    FROM requerimentos r
    JOIN requerentes req ON r.requerente_id = req.id
    LEFT JOIN proprietarios p ON r.proprietario_id = p.id
    WHERE r.id = ?
");
$stmt->execute([$id]);
$requerimento = $stmt->fetch();

if (!$requerimento) {
    header("Location: requerimentos.php");
    exit;
}

// Buscar documentos do requerimento
$stmt = $pdo->prepare("SELECT * FROM documentos WHERE requerimento_id = ? ORDER BY id");
$stmt->execute([$id]);
$documentos = $stmt->fetchAll();

// Buscar histórico de ações
$stmt = $pdo->prepare("
    SELECT ha.*, a.nome as admin_nome
    FROM historico_acoes ha
    LEFT JOIN administradores a ON ha.admin_id = a.id
    WHERE ha.requerimento_id = ?
    ORDER BY ha.data_acao DESC
");
$stmt->execute([$id]);
$historico = $stmt->fetchAll();

include 'header.php';
?>

<style>
    /* Estilos modernos para as páginas de requerimento - Esquema Verde */
    :root {
        --color-primary: #2ecc71;
        --color-primary-dark: #27ae60;
        --color-primary-light: #a3f3c0;
        --color-text: #2c3e50;
        --color-text-light: #7f8c8d;
        --color-background: #f8f9fa;
        --color-background-alt: #ecf0f1;
        --shadow-sm: 0 2px 5px rgba(0, 0, 0, 0.08);
        --shadow-md: 0 5px 15px rgba(0, 0, 0, 0.1);
        --shadow-lg: 0 10px 30px rgba(0, 0, 0, 0.15);
        --radius-sm: 8px;
        --radius-md: 15px;
        --radius-lg: 20px;
        --transition: all 0.3s ease;
    }

    /* Componentes modernos */
    .card-modern {
        border: none;
        border-radius: var(--radius-md);
        box-shadow: var(--shadow-sm);
        transition: var(--transition);
    }

    .card-modern:hover {
        box-shadow: var(--shadow-md);
    }

    /* Abas modernas */
    .nav-tabs .nav-link {
        padding: 10px 20px;
        margin-right: 5px;
        transition: var(--transition);
        border-top-left-radius: var(--radius-sm);
        border-top-right-radius: var(--radius-sm);
        color: var(--color-text-light);
        font-weight: 500;
    }

    .nav-tabs .nav-link:hover {
        background-color: var(--color-background);
        color: var(--color-primary);
        transform: translateY(-3px);
    }

    .nav-tabs .nav-link.active {
        border-bottom: 3px solid var(--color-primary);
        color: var(--color-primary);
        font-weight: 600;
    }

    /* Conteúdo das abas */
    .tab-content {
        transition: opacity 0.3s ease;
    }

    .tab-pane {
        transition: opacity 0.3s ease;
    }

    /* Cards com hover */
    .hover-card {
        border-radius: var(--radius-md);
        border: 1px solid rgba(0, 0, 0, 0.05);
        box-shadow: var(--shadow-sm);
        background: #fafbfc;
        transition: var(--transition);
    }

    .hover-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-md);
    }

    /* Status box flutuante */
    .status-box {
        border-radius: var(--radius-lg);
        overflow: hidden;
        border: none;
        box-shadow: var(--shadow-lg);
        transition: var(--transition);
    }

    .status-box:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
    }

    /* Animações */
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .animate-fade-in {
        animation: fadeIn 0.5s ease forwards;
    }

    /* Botões modernos */
    .btn-modern {
        border-radius: var(--radius-sm);
        padding: 10px 20px;
        font-weight: 500;
        transition: var(--transition);
        box-shadow: var(--shadow-sm);
    }

    .btn-modern:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .btn-primary {
        background: var(--color-primary);
        border-color: var(--color-primary);
    }

    .btn-primary:hover {
        background: var(--color-primary-dark);
        border-color: var(--color-primary-dark);
    }

    /* Badges personalizados */
    .badge-status {
        padding: 8px 15px;
        font-weight: 500;
        border-radius: 30px;
        box-shadow: var(--shadow-sm);
    }

    /* Campos de formulário */
    .form-control,
    .form-select {
        border-radius: var(--radius-sm);
        padding: 10px 15px;
    }

    /* Containers com borda de destaque */
    .highlight-container {
        background: var(--color-background);
        border-radius: var(--radius-sm);
        border-left: 4px solid var(--color-primary);
        padding: 15px;
    }
</style>

<div class="container-fluid px-4"> <!-- RESUMO DO REQUERIMENTO -->
    <div class="card-modern mb-4 animate-fade-in" style="background: linear-gradient(45deg, var(--color-background), var(--color-background-alt));">
        <div class="card-body d-flex flex-wrap align-items-center justify-content-between">
            <div>
                <h4 class="mb-1" style="color: var(--color-text); letter-spacing: 0.5px;">Protocolo <span class="fw-bold" style="color: var(--color-primary);">#<?php echo $requerimento['protocolo']; ?></span></h4>
                <div class="mb-2">
                    <span class="badge bg-<?php echo getStatusClass($requerimento['status']); ?> fs-6 px-3 py-2 badge-status">
                        <?php echo $requerimento['status']; ?>
                    </span>
                </div>
                <div class="text-muted small">
                    <i class="far fa-calendar-alt me-1"></i> Enviado: <?php echo formataData($requerimento['data_envio']); ?>
                    <span class="mx-2">|</span>
                    <i class="far fa-calendar-alt me-1"></i> Atualizado: <?php echo formataData($requerimento['data_atualizacao']); ?>
                </div>
            </div>
            <div class="text-end">
                <div class="mb-2">
                    <span class="fw-bold" style="color: var(--color-text);">Tipo:</span> <span style="color: var(--color-primary);"><?php echo $requerimento['tipo_alvara']; ?></span>
                </div>
                <div>
                    <span class="fw-bold" style="color: var(--color-text);">Requerente:</span> <span style="color: var(--color-primary);"><?php echo $requerimento['requerente_nome']; ?></span>
                </div>
            </div>
            <div>
                <a href="requerimentos.php" class="btn btn-modern btn-outline-success ms-3">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
            </div>
        </div>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert alert-<?php echo $mensagemTipo; ?> alert-dismissible fade show" role="alert" style="border-radius: 10px; border: none; box-shadow: 0 3px 10px rgba(0,0,0,0.08);">
            <?php echo $mensagem; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endif; ?> <!-- ABAS DE INFORMAÇÕES -->
    <ul class="nav nav-tabs mb-3" id="requerimentoTabs" role="tablist" style="border-bottom: 2px solid var(--color-background-alt);">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button" role="tab">
                <i class="fas fa-info-circle me-1"></i> Requerimento
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="requerente-tab" data-bs-toggle="tab" data-bs-target="#requerente" type="button" role="tab">
                <i class="fas fa-user me-1"></i> Requerente
            </button>
        </li>
        <?php if (!empty($requerimento['proprietario_id'])): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="proprietario-tab" data-bs-toggle="tab" data-bs-target="#proprietario" type="button" role="tab">
                    <i class="fas fa-home me-1"></i> Proprietário
                </button>
            </li>
        <?php endif; ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="documentos-tab" data-bs-toggle="tab" data-bs-target="#documentos" type="button" role="tab">
                <i class="fas fa-file-alt me-1"></i> Documentos
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="historico-tab" data-bs-toggle="tab" data-bs-target="#historico" type="button" role="tab">
                <i class="fas fa-history me-1"></i> Histórico
            </button>
        </li>
    </ul>
    <div class="tab-content" id="requerimentoTabsContent"> <!-- Aba: Informações do Requerimento -->
        <div class="tab-pane fade show active" id="info" role="tabpanel">
            <div class="card-modern mb-3">
                <div class="card-body">
                    <div class="mb-3">
                        <span class="fw-bold" style="color: var(--color-text); font-size: 1rem;">Endereço do Objetivo:</span><br>
                        <div class="highlight-container mt-2"><?php echo nl2br(htmlspecialchars($requerimento['endereco_objetivo'])); ?></div>
                    </div>
                    <?php if (!empty($requerimento['observacoes'])): ?>
                        <div class="mb-2">
                            <span class="fw-bold" style="color: var(--color-text); font-size: 1rem;">Observações:</span><br>
                            <div class="highlight-container mt-2"><?php echo nl2br(htmlspecialchars($requerimento['observacoes'])); ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div> <!-- Aba: Requerente -->
        <div class="tab-pane fade" id="requerente" role="tabpanel">
            <div class="card-modern mb-3">
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <div class="p-3 rounded" style="background: var(--color-background);">
                                <span class="d-block text-muted small mb-1">Nome</span>
                                <span class="fw-bold" style="color: var(--color-primary); font-size: 1.05rem;"><?php echo $requerimento['requerente_nome']; ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 rounded" style="background: var(--color-background);">
                                <span class="d-block text-muted small mb-1">CPF/CNPJ</span>
                                <span class="fw-bold"><?php echo $requerimento['requerente_cpf_cnpj']; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <div class="p-3 rounded" style="background: var(--color-background);">
                                <span class="d-block text-muted small mb-1">E-mail</span>
                                <a href="mailto:<?php echo $requerimento['requerente_email']; ?>" style="color: var(--color-primary); text-decoration: none;">
                                    <i class="fas fa-envelope me-1"></i> <?php echo $requerimento['requerente_email']; ?>
                                </a>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 rounded" style="background: var(--color-background);">
                                <span class="d-block text-muted small mb-1">Telefone</span>
                                <a href="tel:<?php echo $requerimento['requerente_telefone']; ?>" style="color: var(--color-primary); text-decoration: none;">
                                    <i class="fas fa-phone me-1"></i> <?php echo $requerimento['requerente_telefone']; ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div> <!-- Aba: Proprietário -->
        <?php if (!empty($requerimento['proprietario_id'])): ?>
            <div class="tab-pane fade" id="proprietario" role="tabpanel">
                <div class="card-modern mb-3">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <div class="p-3 rounded" style="background: var(--color-background);">
                                    <span class="d-block text-muted small mb-1">Nome</span>
                                    <span class="fw-bold" style="color: var(--color-primary); font-size: 1.05rem;"><?php echo $requerimento['proprietario_nome']; ?></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 rounded" style="background: var(--color-background);">
                                    <span class="d-block text-muted small mb-1">CPF/CNPJ</span>
                                    <span class="fw-bold"><?php echo $requerimento['proprietario_cpf_cnpj']; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Aba: Documentos -->
        <div class="tab-pane fade" id="documentos" role="tabpanel">
            <div class="card-modern mb-3">
                <div class="card-body">
                    <?php if (count($documentos) > 0): ?>
                        <div class="row g-3">
                            <?php foreach ($documentos as $doc): ?>
                                <div class="col-md-4 col-6">
                                    <div class="hover-card p-4 h-100 d-flex flex-column align-items-center justify-content-between">
                                        <?php
                                        $iconClass = "fas fa-file fa-3x text-secondary";
                                        $isPdf = false;
                                        if (strpos($doc['tipo_arquivo'], 'pdf') !== false) {
                                            $iconClass = "fas fa-file-pdf fa-3x text-danger";
                                            $isPdf = true;
                                        } elseif (strpos($doc['tipo_arquivo'], 'image') !== false) {
                                            $iconClass = "fas fa-file-image fa-3x text-primary";
                                        } elseif (strpos($doc['tipo_arquivo'], 'word') !== false || strpos($doc['tipo_arquivo'], 'document') !== false) {
                                            $iconClass = "fas fa-file-word fa-3x text-primary";
                                        } elseif (strpos($doc['tipo_arquivo'], 'excel') !== false || strpos($doc['tipo_arquivo'], 'spreadsheet') !== false) {
                                            $iconClass = "fas fa-file-excel fa-3x text-success";
                                        }
                                        ?>
                                        <i class="<?php echo $iconClass; ?> mb-3"></i>
                                        <div class="text-center mb-3 fw-bold" style="color: var(--color-text);"><?php echo $doc['nome_original']; ?></div>
                                        <div class="mb-3 text-muted small"><?php echo number_format($doc['tamanho'] / 1024, 2) . ' KB'; ?></div>
                                        <div class="d-flex gap-2">
                                            <?php if ($isPdf): ?>
                                                <button class="btn btn-modern btn-outline-success"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#pdfModal"
                                                    data-pdf-url="../uploads/<?php echo $doc['caminho']; ?>"
                                                    data-pdf-name="<?php echo $doc['nome_original']; ?>">
                                                    <i class="fas fa-eye"></i> Visualizar
                                                </button>
                                            <?php else: ?>
                                                <a href="../uploads/<?php echo $doc['caminho']; ?>" class="btn btn-modern btn-outline-success" target="_blank">
                                                    <i class="fas fa-eye"></i> Visualizar
                                                </a>
                                            <?php endif; ?>
                                            <a href="../uploads/<?php echo $doc['caminho']; ?>" class="btn btn-modern btn-outline-success" download>
                                                <i class="fas fa-download"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info" style="border-radius: var(--radius-sm); border-left: 4px solid var(--color-primary);">
                            <i class="fas fa-info-circle me-2"></i> Nenhum documento anexado a este requerimento.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div> <!-- Aba: Histórico -->
        <div class="tab-pane fade" id="historico" role="tabpanel">
            <div class="card-modern mb-3">
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php if (count($historico) > 0): ?>
                            <?php foreach ($historico as $h): ?>
                                <div class="list-group-item py-3" style="border-left: 4px solid var(--color-primary); margin-bottom: 5px; border-radius: var(--radius-sm);">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <strong style="color: var(--color-text);"><i class="fas fa-user-circle me-2"></i><?php echo $h['admin_nome']; ?></strong>
                                        <small class="text-muted px-2 py-1 rounded" style="background: rgba(0,0,0,0.03);">
                                            <i class="far fa-clock me-1"></i>
                                            <?php echo formataData($h['data_acao']); ?>
                                        </small>
                                    </div>
                                    <p class="mb-0"><?php echo $h['acao']; ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="list-group-item py-3 text-center">
                                <i class="fas fa-info-circle me-2"></i> Nenhuma ação registrada.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div> <!-- Atualizar Status sempre visível -->
    <div class="fixed-bottom d-flex justify-content-end p-4" style="z-index: 1050; pointer-events: none;">
        <div class="status-box animate__animated animate__fadeInUp"
            style="min-width:320px; max-width:400px; pointer-events: auto;">
            <div class="card-header" style="background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark)); border: none; padding: 15px;">
                <h5 class="mb-0" style="color: white; font-weight: 600; letter-spacing: 0.5px;">
                    <i class="fas fa-edit me-2"></i>Atualizar Status
                </h5>
            </div>
            <div class="card-body" style="background: white;">
                <form method="post" action="">
                    <div class="mb-3">
                        <label for="status" class="form-label fw-bold" style="color: var(--color-text);">Status</label>
                        <div class="input-group">
                            <span class="input-group-text" style="background: var(--color-background); border-radius: 10px 0 0 10px;">
                                <i class="fas fa-tasks text-success"></i>
                            </span>
                            <select class="form-select" id="status" name="status" required
                                style="border-radius: 0 10px 10px 0; border-left: 0; padding: 10px 15px; font-size: 1rem;">
                                <option value="Em análise" <?php echo $requerimento['status'] == 'Em análise' ? 'selected' : ''; ?>>Em análise</option>
                                <option value="Aprovado" <?php echo $requerimento['status'] == 'Aprovado' ? 'selected' : ''; ?>>Aprovado</option>
                                <option value="Reprovado" <?php echo $requerimento['status'] == 'Reprovado' ? 'selected' : ''; ?>>Reprovado</option>
                                <option value="Pendente" <?php echo $requerimento['status'] == 'Pendente' ? 'selected' : ''; ?>>Pendente</option>
                                <option value="Cancelado" <?php echo $requerimento['status'] == 'Cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="observacoes" class="form-label fw-bold" style="color: var(--color-text);">Observações</label>
                        <div class="input-group">
                            <span class="input-group-text" style="background: var(--color-background); border-radius: 10px 0 0 10px;">
                                <i class="fas fa-comment text-success"></i>
                            </span>
                            <textarea class="form-control" id="observacoes" name="observacoes" rows="3"
                                placeholder="Adicione observações ou feedback para o requerente"
                                style="border-radius: 0 10px 10px 0; border-left: 0;"><?php echo htmlspecialchars($requerimento['observacoes']); ?></textarea>
                        </div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success btn-lg shadow-sm"
                            style="border-radius: 10px; padding: 12px; background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark)); border: none; font-weight: 600; letter-spacing: 0.5px; box-shadow: 0 4px 15px rgba(46, 204, 113, 0.3) !important; transition: all 0.3s ease;">
                            <i class="fas fa-save me-2"></i> Salvar Alterações
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal para visualização de PDF -->
<div class="modal fade" id="pdfModal" tabindex="-1" aria-labelledby="pdfModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-fullscreen-lg-down">
        <div class="modal-content" style="border-radius: var(--radius-md); border: none; overflow: hidden;">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark)); border: none;">
                <h5 class="modal-title" id="pdfModalLabel" style="color: white;">Visualizando documento</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body p-0">
                <iframe id="pdfViewer" src="" frameborder="0" style="width: 100%; height: 80vh;"></iframe>
            </div>
            <div class="modal-footer">
                <a id="pdfDownload" href="" download class="btn btn-success btn-modern"
                    style="box-shadow: 0 2px 10px rgba(46, 204, 113, 0.2); font-weight: 500;">
                    <i class="fas fa-download me-1"></i> Baixar Documento
                </a>
                <button type="button" class="btn btn-outline-secondary btn-modern" data-bs-dismiss="modal">
                    Fechar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Adicionar classe para fade-in aos elementos
        document.querySelectorAll('.card-modern, .hover-card').forEach(function(card) {
            card.classList.add('animate-fade-in');
        });

        // Tabs com transição suave
        var triggerTabList = [].slice.call(document.querySelectorAll('#requerimentoTabs button'));
        triggerTabList.forEach(function(triggerEl, index) {
            var tabTrigger = new bootstrap.Tab(triggerEl);

            // Adicionar delay progressivo para animação
            setTimeout(() => {
                triggerEl.classList.add('animate-fade-in');
            }, 100 + (index * 50));

            triggerEl.addEventListener('click', function(event) {
                event.preventDefault();
                tabTrigger.show();

                // Efeito de transição do conteúdo
                const targetId = this.getAttribute('data-bs-target');
                const targetPane = document.querySelector(targetId);

                document.querySelectorAll('.tab-pane.show').forEach(pane => {
                    if (pane !== targetPane) {
                        pane.style.opacity = '0';
                        setTimeout(() => {
                            pane.style.opacity = '1';
                            pane.style.transition = 'opacity 0.3s ease';
                        }, 50);
                    }
                });
            });

            // Highlight da aba ativa
            triggerEl.addEventListener('shown.bs.tab', function() {
                triggerTabList.forEach(t => {
                    t.classList.remove('active-tab');
                    t.style.color = '';
                });
                this.classList.add('active-tab');
                this.style.color = 'var(--color-primary)';
            });
        });

        // Modal de PDF com loader
        var pdfModal = document.getElementById('pdfModal');
        if (pdfModal) {
            pdfModal.addEventListener('show.bs.modal', function(event) {
                var button = event.relatedTarget;
                var pdfUrl = button.getAttribute('data-pdf-url');
                var pdfName = button.getAttribute('data-pdf-name');
                var pdfViewer = document.getElementById('pdfViewer');
                var pdfDownload = document.getElementById('pdfDownload');
                var pdfModalLabel = document.getElementById('pdfModalLabel');

                // Remover spinner anterior, se existir
                const oldSpinner = document.getElementById('pdfSpinner');
                if (oldSpinner) oldSpinner.remove();

                // Adicionar spinner de carregamento
                pdfViewer.insertAdjacentHTML('beforebegin',
                    '<div id="pdfSpinner" class="text-center py-5">' +
                    '<div class="spinner-border" style="color: var(--color-primary);" role="status">' +
                    '<span class="visually-hidden">Carregando...</span>' +
                    '</div>' +
                    '<p class="mt-2">Carregando documento...</p>' +
                    '</div>');

                pdfViewer.style.opacity = '0';
                pdfViewer.onload = function() {
                    document.getElementById('pdfSpinner').remove();
                    pdfViewer.style.opacity = '1';
                    pdfViewer.style.transition = 'opacity 0.3s ease';
                };

                pdfViewer.src = pdfUrl;
                pdfDownload.href = pdfUrl;
                pdfModalLabel.textContent = 'Visualizando: ' + pdfName;
            });
        }

        // Animação do box de status
        setTimeout(function() {
            const statusBox = document.querySelector('.fixed-bottom .status-box');
            if (statusBox) {
                statusBox.style.transform = 'translateY(0)';
                statusBox.style.opacity = '1';
            }
        }, 300);

        // Cards com hover para documentos
        document.querySelectorAll('.hover-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
                this.style.boxShadow = 'var(--shadow-md)';
            });

            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = 'var(--shadow-sm)';
            });
        });

        // Adequações para dispositivos móveis
        function adjustForMobile() {
            if (window.innerWidth < 768) {
                const statusBox = document.querySelector('.fixed-bottom .status-box');
                if (statusBox) {
                    statusBox.style.maxWidth = '100%';
                    statusBox.style.width = '100%';
                    statusBox.style.margin = '0 10px';
                }
            } else {
                const statusBox = document.querySelector('.fixed-bottom .status-box');
                if (statusBox) {
                    statusBox.style.maxWidth = '400px';
                    statusBox.style.width = 'auto';
                    statusBox.style.margin = '0';
                }
            }
        }

        // Executa no carregamento e quando a janela é redimensionada
        adjustForMobile();
        window.addEventListener('resize', adjustForMobile);
    });
</script>

<?php
// Função para obter a classe de cor com base no status
function getStatusClass($status)
{
    switch ($status) {
        case 'Aprovado':
            return 'success';
        case 'Reprovado':
            return 'danger';
        case 'Em análise':
            return 'warning';
        case 'Pendente':
            return 'info';
        case 'Cancelado':
            return 'secondary';
        default:
            return 'primary';
    }
}
?>

<?php include 'footer.php'; ?>