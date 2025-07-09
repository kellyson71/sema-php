<?php
require_once 'conexao.php';
require_once '../includes/email_service.php';
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

// Função para buscar dados do requerimento
function buscarDadosRequerimento($pdo, $id)
{
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
    return $stmt->fetch();
}

// Buscar dados do requerimento PRIMEIRO
$requerimento = buscarDadosRequerimento($pdo, $id);

if (!$requerimento) {
    header("Location: requerimentos.php");
    exit;
}

// Processar atualização de status
$mensagem = '';
$mensagemTipo = '';

// Processar marcar como não lido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['marcar_nao_lido'])) {
    try {
        $stmt = $pdo->prepare("UPDATE requerimentos SET visualizado = 0 WHERE id = ?");
        $stmt->execute([$id]);

        // Registrar no histórico de ações
        $stmt = $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['admin_id'], $id, "Marcou o requerimento como não lido"]);

        // Redirecionar para a lista de requerimentos com mensagem de sucesso
        header("Location: requerimentos.php?success=nao_lido");
        exit;
    } catch (PDOException $e) {
        $mensagem = "Erro ao marcar como não lido: " . $e->getMessage();
        $mensagemTipo = "danger";
    }
}

// Processar envio de email com protocolo oficial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_email_protocolo'])) {
    $protocolo_oficial = trim($_POST['protocolo_oficial']);

    if (empty($protocolo_oficial)) {
        $mensagem = "É necessário informar o protocolo oficial da prefeitura.";
        $mensagemTipo = "danger";
    } elseif (strtolower($requerimento['status']) === 'finalizado') {
        $mensagem = "⚠️ Este requerimento já está finalizado. Tem certeza que deseja enviar o email novamente?";
        $mensagemTipo = "warning";
    } else {
        try {
            $emailService = new EmailService();
            $email_enviado = $emailService->enviarEmailProtocoloOficial(
                $requerimento['requerente_email'],
                $requerimento['requerente_nome'],
                $protocolo_oficial,
                $id
            );

            if ($email_enviado) {
                try {
                    $pdo->beginTransaction();

                    // Atualizar status para "Finalizado" automaticamente
                    $stmt = $pdo->prepare("UPDATE requerimentos SET status = 'Finalizado', data_atualizacao = NOW() WHERE id = ?");
                    $stmt->execute([$id]);

                    // Registrar no histórico de ações
                    $acao = "Enviou email com protocolo oficial: {$protocolo_oficial} e marcou como Finalizado";
                    $stmt = $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao) VALUES (?, ?, ?)");
                    $stmt->execute([$_SESSION['admin_id'], $id, $acao]);

                    $pdo->commit();

                    // Recarregar dados do requerimento para refletir as mudanças
                    $requerimento = buscarDadosRequerimento($pdo, $id);

                    $mensagem = "✅ Email com protocolo oficial enviado com sucesso! O requerimento foi automaticamente marcado como Finalizado.";
                    $mensagemTipo = "success";
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $mensagem = "Email enviado, mas houve erro ao atualizar o status: " . $e->getMessage();
                    $mensagemTipo = "warning";
                }
            } else {
                $mensagem = "Erro ao enviar email. Verifique as configurações de email.";
                $mensagemTipo = "danger";
            }
        } catch (Exception $e) {
            $mensagem = "Erro ao enviar email: " . $e->getMessage();
            $mensagemTipo = "danger";
        }
    }
}

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

        // Recarregar dados do requerimento para refletir as mudanças
        $requerimento = buscarDadosRequerimento($pdo, $id);

        $mensagem = "Status do requerimento atualizado com sucesso!";
        $mensagemTipo = "success";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $mensagem = "Erro ao atualizar status: " . $e->getMessage();
        $mensagemTipo = "danger";
    }
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
    /* Design System - Cores Profissionais */
    :root {
        --primary-600: #059669;
        --primary-700: #047857;
        --primary-50: #ecfdf5;
        --primary-100: #d1fae5;

        --green-600: #059669;
        --green-700: #047857;
        --green-50: #ecfdf5;

        --blue-600: #2563eb;
        --red-600: #dc2626;
        --amber-500: #f59e0b;
        --amber-600: #d97706;
        --purple-600: #9333ea;

        --gray-50: #f9fafb;
        --gray-100: #f3f4f6;
        --gray-200: #e5e7eb;
        --gray-300: #d1d5db;
        --gray-400: #9ca3af;
        --gray-500: #6b7280;
        --gray-600: #4b5563;
        --gray-700: #374151;
        --gray-800: #1f2937;

        --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --transition: all 0.2s ease;
        --radius: 8px;
        --radius-sm: 6px;
    }

    /* Componentes base atualizados */
    .card-modern,
    .modern-card {
        border: 1px solid var(--gray-200);
        border-radius: var(--radius);
        background: white;
        box-shadow: var(--shadow-sm);
        overflow: hidden;
        transition: var(--transition);
    }

    .card-modern:hover,
    .modern-card:hover {
        box-shadow: var(--shadow-md);
    }

    .modern-card-header,
    .data-table-header {
        background: var(--gray-50);
        border-bottom: 1px solid var(--gray-200);
        padding: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .modern-card-header h6,
    .data-table-header h6 {
        margin: 0;
        font-weight: 600;
        color: var(--gray-800);
        font-size: 0.875rem;
    }

    .modern-card-header .icon,
    .data-table-header .icon {
        color: var(--gray-500);
        font-size: 1rem;
    }

    /* Tabela de dados moderna */
    .data-row {
        display: flex;
        align-items: center;
        padding: 1rem;
        border-bottom: 1px solid var(--gray-100);
        transition: var(--transition);
    }

    .data-row:last-child {
        border-bottom: none;
    }

    .data-row:hover {
        background: var(--gray-50);
    }

    .data-label {
        font-weight: 500;
        color: var(--gray-600);
        min-width: 140px;
        font-size: 0.875rem;
    }

    .data-value {
        flex: 1;
        color: var(--gray-900);
        font-size: 0.875rem;
    }

    .data-actions {
        display: flex;
        gap: 0.25rem;
        margin-left: auto;
    }

    /* Botão de copiar atualizado */
    .copy-btn {
        background: white;
        border: 1px solid var(--gray-300);
        color: var(--gray-600);
        border-radius: var(--radius-sm);
        padding: 0.375rem;
        font-size: 0.75rem;
        cursor: pointer;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2rem;
        height: 2rem;
    }

    .copy-btn:hover {
        background: var(--gray-50);
        border-color: var(--gray-400);
        color: var(--gray-700);
    }

    .copy-btn.copied {
        background: var(--primary-50);
        border-color: var(--primary-600);
        color: var(--primary-600);
    }

    /* Cards de ação administrativa */
    .admin-action-card {
        background: white;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius);
        padding: 1.5rem;
        height: 100%;
        transition: var(--transition);
    }

    .admin-action-card:hover {
        box-shadow: var(--shadow-md);
        border-color: var(--gray-300);
    }

    .admin-action-header {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1.25rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid var(--gray-200);
    }

    .admin-action-header i {
        font-size: 1.125rem;
    }

    .admin-action-header h6 {
        margin: 0;
        font-weight: 600;
        color: var(--gray-800);
        font-size: 1rem;
    }

    /* Formulários modernos */
    .modern-select,
    .modern-textarea,
    .modern-input,
    .form-control,
    .form-select {
        border: 1px solid var(--gray-300);
        border-radius: var(--radius-sm);
        padding: 0.75rem;
        font-size: 0.875rem;
        transition: var(--transition);
        background: white;
    }

    .modern-select:focus,
    .modern-textarea:focus,
    .modern-input:focus,
    .form-control:focus,
    .form-select:focus {
        border-color: var(--primary-600);
        box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
        outline: none;
    }

    .form-label {
        font-weight: 500;
        color: var(--gray-700);
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
    }

    /* Botões de ação modernos */
    .btn-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.75rem 1.5rem;
        border-radius: var(--radius-sm);
        font-weight: 500;
        font-size: 0.875rem;
        border: none;
        cursor: pointer;
        transition: var(--transition);
        text-decoration: none;
        min-height: 2.5rem;
    }

    .btn-action-primary {
        background: var(--primary-600);
        color: white;
    }

    .btn-action-primary:hover {
        background: var(--primary-700);
        transform: translateY(-1px);
        box-shadow: var(--shadow-sm);
        color: white;
    }

    .btn-action-success {
        background: #059669;
        color: white;
    }

    .btn-action-success:hover {
        background: #047857;
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        color: white;
    }

    /* Descrições de ação */
    .action-description {
        background: var(--gray-50);
        border-radius: var(--radius-sm);
        padding: 0.75rem;
        border-left: 3px solid var(--gray-300);
        margin-bottom: 1rem;
    }

    .action-description small {
        color: var(--gray-600);
        font-size: 0.8125rem;
    }

    /* Estilos para processos finalizados */
    .finalized-card {
        background: #f8f9fa !important;
        border-color: #dee2e6 !important;
        opacity: 0.8;
    }

    .finalized-header {
        background: #e9ecef !important;
        border-color: #dee2e6 !important;
    }

    .finalized-body {
        background: #f8f9fa !important;
    }

    .finalized-card .admin-action-card {
        background: #f1f3f4 !important;
        border-color: #dee2e6 !important;
        opacity: 0.6;
        pointer-events: none;
    }

    .finalized-card .btn-action {
        background: #6c757d !important;
        border-color: #6c757d !important;
        cursor: not-allowed;
        pointer-events: none;
    }

    .finalized-card input,
    .finalized-card select,
    .finalized-card textarea {
        background: #e9ecef !important;
        border-color: #ced4da !important;
        color: #6c757d !important;
        pointer-events: none;
    }

    /* Estilo para card principal quando finalizado */
    .finalized-main-card {
        background: linear-gradient(45deg, #f8f9fa, #e9ecef) !important;
        border-color: #dee2e6 !important;
        opacity: 0.9;
    }

    .finalized-status-badge {
        background: #6c757d !important;
        color: white !important;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-left: 10px;
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

    /* Navegação de abas moderna */
    .nav-tabs .nav-link {
        padding: 0.75rem 1.25rem;
        margin-right: 0.25rem;
        transition: var(--transition);
        border-radius: var(--radius-sm) var(--radius-sm) 0 0;
        color: var(--gray-600);
        font-weight: 500;
        border: 1px solid transparent;
        background: transparent;
    }

    .nav-tabs .nav-link:hover {
        background: var(--gray-50);
        color: var(--gray-800);
        border-color: var(--gray-200) var(--gray-200) transparent;
    }

    .nav-tabs .nav-link.active {
        background: white;
        color: var(--primary-600);
        border-color: var(--gray-200) var(--gray-200) white;
        font-weight: 600;
        position: relative;
    }

    .nav-tabs .nav-link.active::after {
        content: '';
        position: absolute;
        bottom: -1px;
        left: 0;
        right: 0;
        height: 2px;
        background: var(--primary-600);
    }
</style>

<?php
// Verificar se o processo está finalizado
$isFinalized = (strtolower($requerimento['status']) === 'finalizado');
?>

<div class="container-fluid px-4">
    <!-- RESUMO DO REQUERIMENTO -->
    <div class="card-modern mb-4 <?php echo $isFinalized ? 'finalized-main-card' : ''; ?>">
        <div class="card-body d-flex flex-wrap align-items-center justify-content-between">
            <div>
                <div class="d-flex align-items-center mb-2">
                    <h4 class="mb-0 me-3" style="color: var(--gray-800); letter-spacing: 0.5px;">
                        Protocolo <span class="fw-bold" style="color: var(--primary-600);">#<?php echo $requerimento['protocolo']; ?></span>
                    </h4>
                    <!-- Botão Marcar como Não Lido -->
                    <?php if (!$isFinalized): ?>
                        <form method="post" action="" class="d-inline">
                            <button type="submit" name="marcar_nao_lido" class="btn btn-outline-dark btn-sm"
                                onclick="return confirm('Deseja marcar este requerimento como não lido?')"
                                style="border-radius: 6px; font-size: 11px; padding: 4px 8px;">
                                <i class="fas fa-envelope me-1"></i> Marcar como Não Lido
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="mb-2">
                    <div class="d-flex align-items-center">
                        <span class="rounded-circle me-2" style="width: 12px; height: 12px; background-color: <?php echo getStatusDotColor($requerimento['status']); ?>"></span>
                        <span class="fw-medium" style="color: var(--gray-700);"><?php echo $requerimento['status']; ?></span>
                    </div>
                </div>
                <div class="text-muted small">
                    <i class="far fa-calendar-alt me-1"></i> Enviado: <?php echo formataData($requerimento['data_envio']); ?>
                    <span class="mx-2">|</span>
                    <i class="far fa-calendar-alt me-1"></i> Atualizado: <?php echo formataData($requerimento['data_atualizacao']); ?>
                </div>
            </div>
            <div class="text-end">
                <div class="mb-2">
                    <span class="fw-bold" style="color: var(--gray-700);">Tipo:</span>
                    <span style="color: var(--primary-600);"><?php echo $requerimento['tipo_alvara']; ?></span>
                </div>
                <div>
                    <span class="fw-bold" style="color: var(--gray-700);">Requerente:</span>
                    <span style="color: var(--primary-600);"><?php echo $requerimento['requerente_nome']; ?></span>
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
    <?php endif; ?>

    <!-- ABAS DE INFORMAÇÕES -->
    <ul class="nav nav-tabs mb-3" id="requerimentoTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="informacoes-tab" data-bs-toggle="tab" data-bs-target="#informacoes" type="button" role="tab">
                Informações Completas
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="historico-tab" data-bs-toggle="tab" data-bs-target="#historico" type="button" role="tab">
                Histórico
            </button>
        </li>
    </ul>

    <div class="tab-content" id="requerimentoTabsContent">
        <!-- Aba: Informações Completas -->
        <div class="tab-pane fade show active" id="informacoes" role="tabpanel">
            <!-- Dados do Requerimento -->
            <div class="modern-card mb-3">
                <div class="modern-card-header">
                    <i class="fas fa-file-alt icon"></i>
                    <h6>Dados do Requerimento</h6>
                </div>
                <div class="card-body p-0">
                    <div class="data-row">
                        <div class="data-label">Protocolo:</div>
                        <div class="data-value">
                            <span class="fw-bold text-dark"><?php echo $requerimento['protocolo']; ?></span>
                        </div>
                        <div class="data-actions">
                            <button class="copy-btn" onclick="copyToClipboard('<?php echo $requerimento['protocolo']; ?>', this)" title="Copiar protocolo">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    <div class="data-row">
                        <div class="data-label">Status:</div>
                        <div class="data-value">
                            <div class="d-flex align-items-center">
                                <span class="rounded-circle me-2" style="width: 8px; height: 8px; background-color: <?php echo getStatusDotColor($requerimento['status']); ?>"></span>
                                <span class="fw-medium"><?php echo $requerimento['status']; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="data-row">
                        <div class="data-label">Tipo de Alvará:</div>
                        <div class="data-value"><?php echo htmlspecialchars($requerimento['tipo_alvara']); ?></div>
                        <div class="data-actions">
                            <button class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($requerimento['tipo_alvara']); ?>', this)" title="Copiar tipo de alvará">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    <div class="data-row">
                        <div class="data-label">Data de Envio:</div>
                        <div class="data-value"><?php echo formataData($requerimento['data_envio']); ?></div>
                    </div>
                    <div class="data-row">
                        <div class="data-label">Endereço:</div>
                        <div class="data-value"><?php echo nl2br(htmlspecialchars($requerimento['endereco_objetivo'])); ?></div>
                        <div class="data-actions">
                            <button class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($requerimento['endereco_objetivo']); ?>', this)" title="Copiar endereço">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    <?php if (!empty($requerimento['observacoes'])): ?>
                        <div class="data-row">
                            <div class="data-label">Observações:</div>
                            <div class="data-value"><?php echo nl2br(htmlspecialchars($requerimento['observacoes'])); ?></div>
                            <div class="data-actions">
                                <button class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($requerimento['observacoes']); ?>', this)" title="Copiar observações">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Dados do Requerente -->
            <div class="modern-card mb-3">
                <div class="modern-card-header">
                    <i class="fas fa-user icon"></i>
                    <h6>Dados do Requerente</h6>
                </div>
                <div class="card-body p-0">
                    <div class="data-row">
                        <div class="data-label">Nome:</div>
                        <div class="data-value">
                            <span class="fw-bold text-dark"><?php echo htmlspecialchars($requerimento['requerente_nome']); ?></span>
                        </div>
                        <div class="data-actions">
                            <button class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($requerimento['requerente_nome']); ?>', this)" title="Copiar nome">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    <div class="data-row">
                        <div class="data-label">E-mail:</div>
                        <div class="data-value">
                            <a href="mailto:<?php echo $requerimento['requerente_email']; ?>" class="text-decoration-none">
                                <?php echo $requerimento['requerente_email']; ?>
                            </a>
                        </div>
                        <div class="data-actions">
                            <button class="copy-btn" onclick="copyToClipboard('<?php echo $requerimento['requerente_email']; ?>', this)" title="Copiar e-mail">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    <div class="data-row">
                        <div class="data-label">CPF/CNPJ:</div>
                        <div class="data-value"><?php echo $requerimento['requerente_cpf_cnpj']; ?></div>
                        <div class="data-actions">
                            <button class="copy-btn" onclick="copyToClipboard('<?php echo $requerimento['requerente_cpf_cnpj']; ?>', this)" title="Copiar CPF/CNPJ">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    <div class="data-row">
                        <div class="data-label">Telefone:</div>
                        <div class="data-value">
                            <a href="tel:<?php echo $requerimento['requerente_telefone']; ?>" class="text-decoration-none">
                                <?php echo $requerimento['requerente_telefone']; ?>
                            </a>
                        </div>
                        <div class="data-actions">
                            <button class="copy-btn" onclick="copyToClipboard('<?php echo $requerimento['requerente_telefone']; ?>', this)" title="Copiar telefone">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dados do Proprietário -->
            <?php if (!empty($requerimento['proprietario_id'])): ?>
                <div class="modern-card mb-3">
                    <div class="modern-card-header">
                        <i class="fas fa-home icon"></i>
                        <h6>Dados do Proprietário</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="data-row">
                            <div class="data-label">Nome:</div>
                            <div class="data-value">
                                <span class="fw-bold text-dark"><?php echo htmlspecialchars($requerimento['proprietario_nome']); ?></span>
                            </div>
                            <div class="data-actions">
                                <button class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($requerimento['proprietario_nome']); ?>', this)" title="Copiar nome do proprietário">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                        <div class="data-row">
                            <div class="data-label">CPF/CNPJ:</div>
                            <div class="data-value"><?php echo $requerimento['proprietario_cpf_cnpj']; ?></div>
                            <div class="data-actions">
                                <button class="copy-btn" onclick="copyToClipboard('<?php echo $requerimento['proprietario_cpf_cnpj']; ?>', this)" title="Copiar CPF/CNPJ do proprietário">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="modern-card mb-3">
                    <div class="modern-card-header">
                        <i class="fas fa-home icon"></i>
                        <h6>Dados do Proprietário</h6>
                    </div>
                    <div class="card-body">
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-info-circle me-2"></i>
                            Não há dados de proprietário para este requerimento.
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Documentos -->
            <div class="modern-card mb-3">
                <div class="modern-card-header">
                    <i class="fas fa-folder-open icon"></i>
                    <h6>Documentos (<?php echo count($documentos); ?>)</h6>
                    <div class="ms-auto">
                        <button class="btn btn-sm btn-outline-primary me-2" onclick="window.open('protocolo-capa.php?id=<?php echo $id; ?>', '_blank')" title="Baixar capa do processo">
                            <i class="fas fa-file-alt me-1"></i>Baixar Capa
                        </button>
                        <?php if (count($documentos) > 0): ?>
                            <button class="btn btn-sm btn-outline-secondary" onclick="downloadAllFiles()" title="Baixar todos os documentos">
                                <i class="fas fa-download me-1"></i>Baixar Todos
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (count($documentos) > 0): ?>
                        <?php foreach ($documentos as $doc): ?>
                            <?php
                            $iconClass = "fas fa-file";
                            $iconColor = "#6b7280";

                            if (strpos($doc['tipo_arquivo'], 'pdf') !== false) {
                                $iconClass = "fas fa-file-pdf";
                                $iconColor = "#dc2626";
                            } elseif (strpos($doc['tipo_arquivo'], 'image') !== false) {
                                $iconClass = "fas fa-image";
                                $iconColor = "#059669";
                            } elseif (strpos($doc['tipo_arquivo'], 'word') !== false || strpos($doc['tipo_arquivo'], 'document') !== false) {
                                $iconClass = "fas fa-file-word";
                                $iconColor = "#2563eb";
                            } elseif (strpos($doc['tipo_arquivo'], 'excel') !== false || strpos($doc['tipo_arquivo'], 'spreadsheet') !== false) {
                                $iconClass = "fas fa-file-excel";
                                $iconColor = "#16a34a";
                            }
                            ?>
                            <div class="data-row">
                                <div class="data-label" style="min-width: 40px;">
                                    <i class="<?php echo $iconClass; ?>" style="color: <?php echo $iconColor; ?>; font-size: 20px;"></i>
                                </div>
                                <div class="data-value">
                                    <div class="fw-semibold"><?php echo htmlspecialchars($doc['nome_original']); ?></div>
                                    <div class="text-muted small"><?php echo number_format($doc['tamanho'] / 1024, 2) . ' KB'; ?></div>
                                </div>
                                <div class="data-actions">
                                    <a href="<?php echo '../uploads/' . ltrim($doc['caminho'], '/\\'); ?>"
                                        class="copy-btn me-1"
                                        target="_blank"
                                        title="Visualizar arquivo">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="<?php echo '../uploads/' . ltrim($doc['caminho'], '/\\'); ?>"
                                        class="copy-btn"
                                        download
                                        title="Baixar arquivo">
                                        <i class="fas fa-download"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="card-body">
                            <div class="text-center text-muted py-3">
                                <i class="fas fa-info-circle me-2"></i>
                                Nenhum documento anexado a este requerimento.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Aba: Histórico -->
        <div class="tab-pane fade" id="historico" role="tabpanel">
            <div class="modern-card mb-3">
                <div class="modern-card-header">
                    <i class="fas fa-history icon"></i>
                    <h6>Histórico de Ações</h6>
                </div>
                <div class="card-body p-0">
                    <?php if (count($historico) > 0): ?>
                        <?php foreach ($historico as $h): ?>
                            <div class="data-row">
                                <div class="data-label" style="min-width: 140px;">
                                    <div class="fw-semibold text-dark"><?php echo htmlspecialchars($h['admin_nome']); ?></div>
                                    <div class="text-muted small"><?php echo formataData($h['data_acao']); ?></div>
                                </div>
                                <div class="data-value">
                                    <?php echo htmlspecialchars($h['acao']); ?>
                                </div>
                                <div class="data-actions">
                                    <button class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($h['acao']); ?>', this)" title="Copiar ação">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="card-body">
                            <div class="text-center text-muted py-3">
                                <i class="fas fa-info-circle me-2"></i>
                                Nenhuma ação registrada.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Seção de Ações Administrativas -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="modern-card <?php echo $isFinalized ? 'finalized-card' : ''; ?>">
                <div class="modern-card-header <?php echo $isFinalized ? 'finalized-header' : ''; ?>">
                    <i class="fas fa-cog icon <?php echo $isFinalized ? 'text-muted' : ''; ?>"></i>
                    <h6 class="<?php echo $isFinalized ? 'text-muted' : ''; ?>">Ações Administrativas</h6>
                    <?php if ($isFinalized): ?>
                        <div class="ms-auto">
                            <span class="badge bg-secondary">
                                <i class="fas fa-check-circle me-1"></i>Finalizado
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-body <?php echo $isFinalized ? 'finalized-body' : ''; ?>">
                    <?php if ($isFinalized): ?>
                        <!-- Mensagem para processo finalizado -->
                        <div class="text-center py-5">
                            <div class="mb-3">
                                <i class="fas fa-check-circle text-muted" style="font-size: 3rem;"></i>
                            </div>
                            <h5 class="text-muted mb-2">Processo Finalizado</h5>
                            <p class="text-muted mb-0">
                                Este requerimento já foi finalizado e não permite mais alterações.
                                <br>
                                <small>O protocolo oficial já foi enviado ao requerente.</small>
                            </p>
                        </div>
                    <?php else: ?>
                        <!-- Ações normais para processos não finalizados -->
                        <div class="row g-3">
                            <!-- Atualizar Status -->
                            <div class="col-md-6 col-lg-6">
                                <div class="admin-action-card">
                                    <div class="admin-action-header">
                                        <i class="fas fa-edit text-primary"></i>
                                        <h6>Atualizar Status</h6>
                                    </div>
                                    <form method="post" action="">
                                        <div class="mb-3">
                                            <label for="status" class="form-label">Status</label>
                                            <select class="form-select modern-select" id="status" name="status" required>
                                                <option value="Em análise" <?php echo $requerimento['status'] == 'Em análise' ? 'selected' : ''; ?>>Em análise</option>
                                                <option value="Aprovado" <?php echo $requerimento['status'] == 'Aprovado' ? 'selected' : ''; ?>>Aprovado</option>
                                                <option value="Finalizado" <?php echo $requerimento['status'] == 'Finalizado' ? 'selected' : ''; ?>>Finalizado</option>
                                                <option value="Reprovado" <?php echo $requerimento['status'] == 'Reprovado' ? 'selected' : ''; ?>>Reprovado</option>
                                                <option value="Pendente" <?php echo $requerimento['status'] == 'Pendente' ? 'selected' : ''; ?>>Pendente</option>
                                                <option value="Cancelado" <?php echo $requerimento['status'] == 'Cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="observacoes" class="form-label">Observações</label>
                                            <textarea class="form-control modern-textarea" id="observacoes" name="observacoes" rows="3"
                                                placeholder="Adicione observações ou feedback para o requerente"><?php echo htmlspecialchars($requerimento['observacoes']); ?></textarea>
                                        </div>
                                        <button type="submit" class="btn-action btn-action-primary w-100">
                                            <i class="fas fa-save me-2"></i>Salvar Alterações
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <!-- Conclusão do Processo -->
                            <div class="col-md-6 col-lg-6">
                                <div class="admin-action-card">
                                    <div class="admin-action-header">
                                        <i class="fas fa-check-circle text-success"></i>
                                        <h6>Finalização do Processo</h6>
                                    </div>
                                    <div class="action-description mb-3">
                                        <i class="fas fa-info-circle text-info me-2"></i>
                                        <small class="text-muted">Finalize o processo enviando o protocolo oficial para o requerente</small>
                                    </div>
                                    <div class="alert alert-info alert-sm mb-3" style="padding: 8px 12px; font-size: 12px; border-radius: 6px; background-color: #e3f2fd; border: 1px solid #bbdefb; color: #1976d2;">
                                        <i class="fas fa-info-circle me-1"></i>
                                        <strong>Atenção:</strong> Ao enviar o email, o status será automaticamente alterado para "Finalizado".
                                    </div>
                                    <div class="mb-3">
                                        <label for="protocolo_oficial" class="form-label">Protocolo Oficial da Prefeitura</label>
                                        <input type="text" class="form-control modern-input" id="protocolo_oficial" name="protocolo_oficial"
                                            placeholder="Ex: 2025001234-SEMA" required>
                                    </div>
                                    <button type="button" class="btn-action btn-action-success w-100"
                                        onclick="showProtocolConfirmModal()">
                                        <i class="fas fa-paper-plane me-2"></i>Enviar Protocolo Oficial
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Confirmação para Protocolo Oficial -->
<div class="modal fade" id="protocolConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-paper-plane text-success me-2"></i>
                    Enviar Protocolo Oficial
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <strong>Destinatário:</strong> <?php echo htmlspecialchars($requerimento['requerente_nome']); ?>
                </div>
                <div class="mb-3">
                    <strong>Email:</strong> <?php echo htmlspecialchars($requerimento['requerente_email']); ?>
                </div>
                <div class="mb-3">
                    <strong>Protocolo:</strong> <span id="protocol-display"></span>
                </div>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    O requerente receberá um email com o protocolo oficial da prefeitura.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancelar
                </button>
                <form method="post" action="" style="display: inline;">
                    <input type="hidden" id="hidden_protocolo_oficial" name="protocolo_oficial">
                    <button type="submit" name="enviar_email_protocolo" class="btn btn-success">
                        <i class="fas fa-check me-2"></i>Confirmar Envio
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Função para copiar texto para a área de transferência
    function copyToClipboard(text, button) {
        navigator.clipboard.writeText(text).then(function() {
            const originalIcon = button.querySelector('i').className;
            button.innerHTML = '<i class="fas fa-check"></i>';
            button.classList.add('copied');
            button.title = 'Copiado!';

            setTimeout(function() {
                button.innerHTML = '<i class="' + originalIcon + '"></i>';
                button.classList.remove('copied');
                button.title = 'Copiar';
            }, 2000);
        });
    }

    // Função para baixar todos os arquivos
    function downloadAllFiles() {
        const requerimentoId = <?php echo $id; ?>;
        if (!confirm('Deseja baixar todos os arquivos em um arquivo ZIP?')) {
            return;
        }
        window.location.href = 'download_arquivos.php?requerimento_id=' + requerimentoId;
    }

    // Função para mostrar modal de confirmação de protocolo
    function showProtocolConfirmModal() {
        const protocolInput = document.getElementById('protocolo_oficial');
        const protocolValue = protocolInput.value.trim();

        if (!protocolValue) {
            alert('Por favor, informe o protocolo oficial antes de continuar.');
            protocolInput.focus();
            return;
        }

        document.getElementById('protocol-display').textContent = protocolValue;
        document.getElementById('hidden_protocolo_oficial').value = protocolValue;

        const modal = new bootstrap.Modal(document.getElementById('protocolConfirmModal'));
        modal.show();
    }
</script>

<?php
// Função para obter a classe de cor com base no status
function getStatusClass($status)
{
    switch ($status) {
        case 'Aprovado':
            return 'success';
        case 'Finalizado':
            return 'purple';
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

function getStatusDotColor($status)
{
    switch (strtolower($status)) {
        case 'pendente':
            return '#f59e0b'; // amarelo
        case 'aprovado':
            return '#10b981'; // verde
        case 'finalizado':
            return '#8b5cf6'; // roxo
        case 'reprovado':
        case 'rejeitado':
            return '#ef4444'; // vermelho
        case 'em análise':
        case 'em_analise':
            return '#3b82f6'; // azul
        case 'cancelado':
            return '#6c757d'; // cinza
        default:
            return '#6b7280'; // cinza
    }
}
?>

<?php include 'footer.php'; ?>