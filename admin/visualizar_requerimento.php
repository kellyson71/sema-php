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

// Buscar dados do requerimento PRIMEIRO
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
                // Registrar no histórico de ações
                $acao = "Enviou email com protocolo oficial: {$protocolo_oficial}";

                $stmt = $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao) VALUES (?, ?, ?)");
                $stmt->execute([$_SESSION['admin_id'], $id, $acao]);

                $mensagem = "Email com protocolo oficial enviado com sucesso!";
                $mensagemTipo = "success";
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

        --gray-50: #f9fafb;
        --gray-100: #f3f4f6;
        --gray-200: #e5e7eb;
        --gray-300: #d1d5db;
        --gray-400: #9ca3af;
        --gray-500: #6b7280;
        --gray-600: #4b5563;
        --gray-700: #374151;
        --gray-800: #1f2937;

        /* Componentes base atualizados */
        .card-modern,
        .modern-card {
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            background: white;
            box-shadow: var(--shadow);
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

        /* Badges de status atualizados */
        .badge {
            font-weight: 500;
            font-size: 0.75rem;
            padding: 0.375rem 0.75rem;
            border-radius: var(--radius);
        }

        .bg-warning {
            background-color: var(--amber-500) !important;
            color: white;
        }

        .bg-success {
            background-color: var(--green-600) !important;
            color: white;
        }

        .bg-danger {
            background-color: var(--red-600) !important;
            color: white;
        }

        .bg-info {
            background-color: var(--blue-600) !important;
            color: white;
        }

        .hover-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
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

        /* Estilo profissional shadcn-like */
        .data-table {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
            background: white;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        }

        .data-table-header {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .data-table-header h6 {
            margin: 0;
            font-weight: 600;
            color: #374151;
            font-size: 14px;
        }

        .data-table-header .icon {
            color: #6b7280;
            font-size: 16px;
        }

        .data-table-body {
            padding: 0;
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
            background: var(--green-600);
            color: white;
        }

        .btn-action-success:hover {
            background: #047857;
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
            color: white;
        }

        .btn-action-outline-warning {
            background: white;
            color: #000;
            border: 2px solid white;
            text-shadow: 1px 1px 0 white, -1px -1px 0 white, 1px -1px 0 white, -1px 1px 0 white;
        }

        .btn-action-outline-warning:hover {
            background: #f8f9fa;
            color: #000;
            border-color: #f8f9fa;
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }

        /* Estilo para botão dark do modal */
        .modern-btn-dark {
            background: #000;
            color: white;
            border-color: #000;
        }

        .modern-btn-dark:hover {
            background: #333;
            color: white;
            border-color: #333;
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

        font-size: 18px;
    }

    .admin-action-header h6 {
        margin: 0;
        font-weight: 600;
        color: #374151;
        font-size: 15px;
    }

    /* Modern Form Elements */
    .modern-select,
    .modern-textarea,
    .modern-input {
        border: 1px solid #d1d5db;
        border-radius: 6px;
        padding: 10px 12px;
        font-size: 14px;
        transition: all 0.2s ease;
        background: #ffffff;
    }

    .modern-select:focus,
    .modern-textarea:focus,
    .modern-input:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        outline: none;
    }

    /* Action Buttons */
    .btn-action {
        padding: 12px 20px;
        border-radius: 6px;
        font-weight: 500;
        font-size: 14px;
        border: none;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
    }

    .btn-action-primary {
        background: #3b82f6;
        color: white;
    }

    .btn-action-primary:hover {
        background: #2563eb;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }

    .btn-action-warning {
        background: #f59e0b;
        color: white;
    }

    .btn-action-warning:hover {
        background: #d97706;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
    }

    .btn-action-info {
        background: #06b6d4;
        color: white;
    }

    .btn-action-info:hover {
        background: #0891b2;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(6, 182, 212, 0.3);
    }

    /* Form Labels */
    .form-label {
        font-weight: 500;
        color: #374151;
        margin-bottom: 6px;
        font-size: 13px;
    }

    /* Cards modernas */
    .modern-card {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: white;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        overflow: hidden;
    }

    .modern-card-header {
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        padding: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .modern-card-header h6 {
        margin: 0;
        font-weight: 600;
        color: #374151;
        font-size: 16px;
    }

    .modern-card-header .icon {
        color: #6b7280;
        font-size: 18px;
    }

    .info-container {
        position: relative;
        background: var(--color-background);
        border-radius: var(--radius-sm);
        padding: 15px;
        margin-bottom: 15px;
    }

    .copy-section {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }

    .download-all-btn {
        background: linear-gradient(135deg, #28a745, #20c997);
        border: none;
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        font-weight: 500;
        transition: all 0.3s ease;
        box-shadow: 0 2px 10px rgba(40, 167, 69, 0.3);
    }

    .download-all-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(40, 167, 69, 0.4);
        color: white;
    }
</style>

<div class="container-fluid px-4"> <!-- RESUMO DO REQUERIMENTO -->
    <div class="card-modern mb-4 animate-fade-in" style="background: linear-gradient(45deg, var(--color-background), var(--color-background-alt));">
        <div class="card-body d-flex flex-wrap align-items-center justify-content-between">
            <div>
                <div class="d-flex align-items-center mb-2">
                    <h4 class="mb-0 me-3" style="color: var(--color-text); letter-spacing: 0.5px;">
                        Protocolo <span class="fw-bold" style="color: var(--color-primary);">#<?php echo $requerimento['protocolo']; ?></span>
                    </h4> <!-- Botão Marcar como Não Lido -->
                    <form method="post" action="" class="d-inline">
                        <button type="submit" name="marcar_nao_lido" class="btn btn-outline-dark btn-sm"
                            onclick="return confirm('Deseja marcar este requerimento como não lido?')"
                            style="border-radius: 6px; font-size: 11px; padding: 4px 8px; background: white; color: #000; border: 2px solid white; text-shadow: 1px 1px 0 white, -1px -1px 0 white, 1px -1px 0 white, -1px 1px 0 white;">
                            <i class="fas fa-envelope me-1"></i> Marcar como Não Lido
                        </button>
                    </form>
                </div>
                <div class="mb-2">
                    <span class="badge bg-light text-dark fs-6 px-3 py-2 badge-status" style="border: 1px solid #ddd;">
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
        <div class="tab-pane fade show active" id="informacoes" role="tabpanel"> <!-- Dados do Requerimento -->
            <div class="modern-card mb-3">
                <div class="modern-card-header">
                    <i class="fas fa-file-alt icon"></i>
                    <h6>Dados do Requerimento</h6>
                </div>
                <div class="data-table-body">
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
                            <span class="badge bg-light text-dark" style="border: 1px solid #ddd;">
                                <?php echo $requerimento['status']; ?>
                            </span>
                        </div>
                        <div class="data-actions"></div>
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
                        <div class="data-actions"></div>
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
            </div> <!-- Dados do Requerente -->
            <div class="modern-card mb-3">
                <div class="modern-card-header">
                    <i class="fas fa-user icon"></i>
                    <h6>Dados do Requerente</h6>
                </div>
                <div class="data-table-body">
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
            </div> <!-- Dados do Proprietário -->
            <?php if (!empty($requerimento['proprietario_id'])): ?>
                <div class="modern-card mb-3">
                    <div class="modern-card-header">
                        <i class="fas fa-home icon"></i>
                        <h6>Dados do Proprietário</h6>
                    </div>
                    <div class="data-table-body">
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
                    <div class="data-table-body">
                        <div class="data-row">
                            <div class="data-value text-center text-muted py-3">
                                <i class="fas fa-info-circle me-2"></i>
                                Não há dados de proprietário para este requerimento.
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?> <!-- Documentos -->
            <div class="modern-card mb-3">
                <div class="modern-card-header">
                    <i class="fas fa-folder-open icon"></i>
                    <h6>Documentos (<?php echo count($documentos); ?>)</h6>
                    <div class="ms-auto">
                        <!-- Botão Baixar Capa do Processo -->
                        <button class="btn btn-sm btn-outline-primary me-2" onclick="window.open('protocolo-capa.php?id=<?php echo $id; ?>', '_blank')" title="Baixar capa do processo">
                            <i class="fas fa-file-alt me-1"></i>Baixar Capa
                        </button>
                        <?php if (count($documentos) > 0): ?>
                            <!-- Botão Baixar Todos os Documentos -->
                            <button class="btn btn-sm btn-outline-secondary" onclick="downloadAllFiles()" title="Baixar todos os documentos">
                                <i class="fas fa-download me-1"></i>Baixar Todos
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="data-table-body">
                    <?php if (count($documentos) > 0): ?>
                        <?php foreach ($documentos as $doc): ?>
                            <?php
                            $iconClass = "fas fa-file";
                            $iconColor = "#6b7280";
                            $isPdf = false;

                            if (strpos($doc['tipo_arquivo'], 'pdf') !== false) {
                                $iconClass = "fas fa-file-pdf";
                                $iconColor = "#dc2626";
                                $isPdf = true;
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
                                    <?php if ($isPdf): ?> <button class="copy-btn me-1"
                                            data-bs-toggle="modal"
                                            data-bs-target="#pdfModal"
                                            data-pdf-url="<?php echo '../uploads/' . ltrim($doc['caminho'], '/\\'); ?>"
                                            data-pdf-name="<?php echo $doc['nome_original']; ?>"
                                            title="Visualizar PDF">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    <?php else: ?>
                                        <a href="<?php echo '../uploads/' . ltrim($doc['caminho'], '/\\'); ?>"
                                            class="copy-btn me-1"
                                            target="_blank"
                                            title="Visualizar arquivo">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    <?php endif; ?> <a href="<?php echo '../uploads/' . ltrim($doc['caminho'], '/\\'); ?>"
                                        class="copy-btn"
                                        download
                                        title="Baixar arquivo">
                                        <i class="fas fa-download"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="data-row">
                            <div class="data-value text-center text-muted py-3">
                                <i class="fas fa-info-circle me-2"></i>
                                Nenhum documento anexado a este requerimento.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div> <!-- Aba: Histórico -->
        <div class="tab-pane fade" id="historico" role="tabpanel">
            <div class="modern-card mb-3">
                <div class="modern-card-header">
                    <i class="fas fa-history icon"></i>
                    <h6>Histórico de Ações</h6>
                </div>
                <div class="data-table-body">
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
                        <div class="data-row">
                            <div class="data-value text-center text-muted py-3">
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
            <div class="modern-card">
                <div class="modern-card-header">
                    <i class="fas fa-cog icon"></i>
                    <h6>Ações Administrativas</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">

                        <!-- Atualizar Status -->
                        <div class="col-md-6 col-lg-3">
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
                        </div> <!-- Conclusão do Processo -->
                        <div class="col-md-6 col-lg-3">
                            <div class="admin-action-card">
                                <div class="admin-action-header">
                                    <i class="fas fa-check-circle text-success"></i>
                                    <h6>Finalização do Processo</h6>
                                </div>

                                <!-- Enviar protocolo oficial -->
                                <div class="mb-3">
                                    <div class="action-description mb-3">
                                        <i class="fas fa-info-circle text-info me-2"></i>
                                        <small class="text-muted">Finalize o processo enviando o protocolo oficial para o requerente</small>
                                    </div>
                                    <label for="protocolo_oficial" class="form-label">Protocolo Oficial da Prefeitura</label>
                                    <input type="text" class="form-control modern-input" id="protocolo_oficial" name="protocolo_oficial"
                                        placeholder="Ex: 2025001234-SEMA" required>
                                </div>
                                <button type="button" class="btn-action btn-action-success w-100 mb-3"
                                    onclick="showProtocolConfirmModal()">
                                    <i class="fas fa-paper-plane me-2"></i>Enviar Protocolo Oficial
                                </button> <!-- Marcar como não lido -->
                                <div class="action-description mb-2">
                                    <i class="fas fa-undo text-dark me-2"></i>
                                    <small class="text-muted">Retornar requerimento para a lista de não lidos</small>
                                </div>
                                <button type="button" class="btn-action btn-action-outline-warning w-100"
                                    onclick="showUnreadConfirmModal()">
                                    <i class="fas fa-eye-slash me-2"></i>Marcar como Não Lido
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para visualização de PDF -->
<div class="modal fade" id="pdfModal" tabindex="-1" aria-labelledby="pdfModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-fullscreen-lg-down">
        <div class="modal-content" style="border-radius: 8px; border: none; overflow: hidden; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);">
            <div class="modal-header" style="background: #374151; border: none; padding: 16px 20px;">
                <h5 class="modal-title" id="pdfModalLabel" style="color: white; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-file-pdf"></i>
                    Visualizando documento
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body p-0">
                <iframe id="pdfViewer" src="" frameborder="0" style="width: 100%; height: 80vh;"></iframe>
            </div>
            <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 12px 20px;"> <a id="pdfDownload" href="" download class="btn btn-sm"
                    style="background: #059669; color: white; border: none; border-radius: 6px; padding: 8px 16px; font-weight: 500; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s ease;"
                    onmouseover="this.style.background='#047857'"
                    onmouseout="this.style.background='#059669'">
                    <i class="fas fa-download"></i> Baixar Documento
                </a> <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal"
                    style="border-radius: 6px; padding: 8px 16px;">
                    Fechar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Confirmação para Protocolo Oficial -->
<div class="modal fade" id="protocolConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modern-modal">
            <div class="modal-header modern-modal-header">
                <div class="modal-icon">
                    <i class="fas fa-paper-plane text-success"></i>
                </div>
                <div>
                    <h5 class="modal-title">Enviar Protocolo Oficial</h5>
                    <p class="modal-subtitle">Confirme o envio do protocolo oficial</p>
                </div>
            </div>
            <div class="modal-body modern-modal-body">
                <div class="confirmation-details">
                    <div class="detail-item">
                        <i class="fas fa-user text-primary"></i>
                        <span><strong>Destinatário:</strong> <?php echo htmlspecialchars($requerimento['requerente_nome']); ?></span>
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-envelope text-info"></i>
                        <span><strong>Email:</strong> <?php echo htmlspecialchars($requerimento['requerente_email']); ?></span>
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-hashtag text-success"></i>
                        <span><strong>Protocolo:</strong> <span id="protocol-display"></span></span>
                    </div>
                </div>
                <div class="alert alert-info modern-alert">
                    <i class="fas fa-info-circle me-2"></i>
                    O requerente receberá um email com o protocolo oficial da prefeitura.
                </div>
            </div>
            <div class="modal-footer modern-modal-footer">
                <button type="button" class="btn btn-outline-secondary modern-btn-outline" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancelar
                </button>
                <form method="post" action="" style="display: inline;">
                    <input type="hidden" id="hidden_protocolo_oficial" name="protocolo_oficial">
                    <button type="submit" name="enviar_email_protocolo" class="btn btn-success modern-btn-success">
                        <i class="fas fa-check me-2"></i>Confirmar Envio
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Confirmação para Marcar como Não Lido -->
<div class="modal fade" id="unreadConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modern-modal">
            <div class="modal-header modern-modal-header">
                <div class="modal-icon">
                    <i class="fas fa-eye-slash text-dark"></i>
                </div>
                <div>
                    <h5 class="modal-title">Marcar como Não Lido</h5>
                    <p class="modal-subtitle">Retornar requerimento para análise</p>
                </div>
            </div>
            <div class="modal-body modern-modal-body">
                <div class="confirmation-details">
                    <div class="detail-item">
                        <i class="fas fa-file-alt text-primary"></i>
                        <span><strong>Protocolo:</strong> <?php echo htmlspecialchars($requerimento['protocolo']); ?></span>
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-user text-info"></i>
                        <span><strong>Requerente:</strong> <?php echo htmlspecialchars($requerimento['requerente_nome']); ?></span>
                    </div>
                </div>
                <div class="alert alert-warning modern-alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Este requerimento retornará para a lista de não lidos e precisará ser revisado novamente.
                </div>
            </div>
            <div class="modal-footer modern-modal-footer">
                <button type="button" class="btn btn-outline-secondary modern-btn-outline" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancelar
                </button>
                <form method="post" action="" style="display: inline;">
                    <button type="submit" name="marcar_nao_lido" class="btn modern-btn-dark"
                        style="background: white; color: #000; border: 2px solid white; text-shadow: 1px 1px 0 white, -1px -1px 0 white, 1px -1px 0 white, -1px 1px 0 white; font-weight: 500;">
                        <i class="fas fa-check me-2"></i>Confirmar
                    </button>
                </form>
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
        } // Animação de entrada dos cards
        document.querySelectorAll('.admin-action-card').forEach((card, index) => {
            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });

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
    }); // Função para copiar texto para a área de transferência
    function copyToClipboard(text, button) {
        navigator.clipboard.writeText(text).then(function() {
            // Feedback visual de sucesso
            const originalIcon = button.querySelector('i').className;
            button.innerHTML = '<i class="fas fa-check"></i>';
            button.classList.add('copied');
            button.title = 'Copiado!';

            setTimeout(function() {
                button.innerHTML = '<i class="' + originalIcon + '"></i>';
                button.classList.remove('copied');
                button.title = button.getAttribute('data-original-title') || 'Copiar';
            }, 2000);
        }).catch(function(err) {
            // Fallback para navegadores mais antigos
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);

            // Feedback visual
            const originalIcon = button.querySelector('i').className;
            button.innerHTML = '<i class="fas fa-check"></i>';
            button.classList.add('copied');
            button.title = 'Copiado!';

            setTimeout(function() {
                button.innerHTML = '<i class="' + originalIcon + '"></i>';
                button.classList.remove('copied');
                button.title = button.getAttribute('data-original-title') || 'Copiar';
            }, 2000);
        });
    } // Função para baixar todos os arquivos
    function downloadAllFiles() {
        const requerimentoId = <?php echo $id; ?>;

        // Confirmar ação
        if (!confirm('Deseja baixar todos os arquivos em um arquivo ZIP? Isso pode levar alguns segundos.')) {
            return;
        }

        // Feedback visual
        const button = event.target.closest('button');
        const originalContent = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Preparando ZIP...';
        button.disabled = true;

        // Redirecionar para o download
        window.location.href = 'download_arquivos.php?requerimento_id=' + requerimentoId;

        // Restaurar botão após um tempo
        setTimeout(function() {
            button.innerHTML = originalContent;
            button.disabled = false;
        }, 3000);
    }

    // Funções para os modais de confirmação
    function showProtocolConfirmModal() {
        const protocolInput = document.getElementById('protocolo_oficial');
        const protocolValue = protocolInput.value.trim();

        if (!protocolValue) {
            alert('Por favor, informe o protocolo oficial antes de continuar.');
            protocolInput.focus();
            return;
        }

        // Atualizar informações no modal
        document.getElementById('protocol-display').textContent = protocolValue;
        document.getElementById('hidden_protocolo_oficial').value = protocolValue;

        // Mostrar modal
        const modal = new bootstrap.Modal(document.getElementById('protocolConfirmModal'));
        modal.show();
    }

    function showUnreadConfirmModal() {
        const modal = new bootstrap.Modal(document.getElementById('unreadConfirmModal'));
        modal.show();
    }

    // Animação dos cards de ação administrativa
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            document.querySelectorAll('.admin-action-card').forEach(function(card, index) {
                setTimeout(function() {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 150);
            });
        }, 300);
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