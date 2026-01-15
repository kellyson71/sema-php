<?php
require_once 'conexao.php';
require_once '../includes/email_service.php';
require_once '../tipos_alvara.php';
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

// Processar indeferimento de processo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['indeferir_processo'])) {
    $motivoIndeferimento = trim($_POST['motivo_indeferimento']);
    $orientacoesAdicionais = trim($_POST['orientacoes_adicionais']);

    if (empty($motivoIndeferimento)) {
        $mensagem = "É necessário informar o motivo do indeferimento.";
        $mensagemTipo = "danger";
    } elseif (strlen($motivoIndeferimento) < 10) {
        $mensagem = "O motivo do indeferimento deve ter pelo menos 10 caracteres.";
        $mensagemTipo = "danger";
    } else {
        try {
            $emailService = new EmailService();
            $email_enviado = $emailService->enviarEmailIndeferimento(
                $requerimento['requerente_email'],
                $requerimento['requerente_nome'],
                $requerimento['protocolo'],
                $requerimento['tipo_alvara'],
                $motivoIndeferimento,
                $orientacoesAdicionais
            );

            if ($email_enviado) {
                try {
                    $pdo->beginTransaction();

                    // Criar observações combinadas
                    $observacoesCombinadas = "PROCESSO INDEFERIDO\n\nMotivo: " . $motivoIndeferimento;
                    if (!empty($orientacoesAdicionais)) {
                        $observacoesCombinadas .= "\n\nOrientações: " . $orientacoesAdicionais;
                    }

                    // Atualizar status para "Indeferido" automaticamente
                    $stmt = $pdo->prepare("UPDATE requerimentos SET status = 'Indeferido', observacoes = ?, data_atualizacao = NOW() WHERE id = ?");
                    $stmt->execute([$observacoesCombinadas, $id]);

                    // Registrar no histórico de ações
                    $acao = "Indeferiu o processo e enviou email de notificação - Motivo: {$motivoIndeferimento}";
                    $stmt = $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao) VALUES (?, ?, ?)");
                    $stmt->execute([$_SESSION['admin_id'], $id, $acao]);

                    $pdo->commit();

                    // Recarregar dados do requerimento para refletir as mudanças
                    $requerimento = buscarDadosRequerimento($pdo, $id);

                    $mensagem = "✅ Processo indeferido com sucesso! O requerente foi notificado por email.";
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

// Processar arquivamento de processo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['arquivar_processo'])) {
    $motivoArquivamento = trim($_POST['motivo_arquivamento']);

    if (empty($motivoArquivamento)) {
        $mensagem = "É necessário informar o motivo do arquivamento.";
        $mensagemTipo = "danger";
    } else {
        try {
            $pdo->beginTransaction();

            // Buscar todos os dados do requerimento com relacionamentos
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
            $dadosCompletos = $stmt->fetch();

            if (!$dadosCompletos) {
                throw new Exception("Requerimento não encontrado.");
            }

            // Inserir na tabela de arquivados
            $stmt = $pdo->prepare("
                INSERT INTO requerimentos_arquivados (
                    requerimento_id, protocolo, tipo_alvara, requerente_id, proprietario_id,
                    endereco_objetivo, status, observacoes, data_envio, data_atualizacao,
                    admin_arquivamento, motivo_arquivamento, requerente_nome, requerente_email,
                    requerente_cpf_cnpj, requerente_telefone, proprietario_nome, proprietario_cpf_cnpj
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $dadosCompletos['id'],
                $dadosCompletos['protocolo'],
                $dadosCompletos['tipo_alvara'],
                $dadosCompletos['requerente_id'],
                $dadosCompletos['proprietario_id'],
                $dadosCompletos['endereco_objetivo'],
                $dadosCompletos['status'],
                $dadosCompletos['observacoes'],
                $dadosCompletos['data_envio'],
                $dadosCompletos['data_atualizacao'],
                $_SESSION['admin_id'],
                $motivoArquivamento,
                $dadosCompletos['requerente_nome'],
                $dadosCompletos['requerente_email'],
                $dadosCompletos['requerente_cpf_cnpj'],
                $dadosCompletos['requerente_telefone'],
                $dadosCompletos['proprietario_nome'] ?? null,
                $dadosCompletos['proprietario_cpf_cnpj'] ?? null
            ]);

            // Registrar no histórico antes de deletar
            $stmt = $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['admin_id'], $id, "Arquivou o processo - Motivo: {$motivoArquivamento}"]);

            // Remover das tabelas principais (cascade vai remover documentos e histórico)
            $stmt = $pdo->prepare("DELETE FROM requerimentos WHERE id = ?");
            $stmt->execute([$id]);

            $pdo->commit();

            // Redirecionar para a lista com mensagem de sucesso
            header("Location: requerimentos.php?success=arquivado");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $mensagem = "Erro ao arquivar o processo: " . $e->getMessage();
            $mensagemTipo = "danger";
        }
    }
}

// Processar reabertura de processo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reabrir_processo'])) {
    $novoStatus = $_POST['novo_status'];
    $motivoReabertura = trim($_POST['motivo_reabertura']);

    try {
        $pdo->beginTransaction();

        // Atualizar status do requerimento
        $stmt = $pdo->prepare("UPDATE requerimentos SET status = ?, data_atualizacao = NOW() WHERE id = ?");
        $stmt->execute([$novoStatus, $id]);

        // Registrar no histórico de ações
        $acao = "Reabriu o processo finalizado e alterou status para '{$novoStatus}'";
        if (!empty($motivoReabertura)) {
            $acao .= " - Motivo: {$motivoReabertura}";
        }

        $stmt = $pdo->prepare("INSERT INTO historico_acoes (admin_id, requerimento_id, acao) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['admin_id'], $id, $acao]);

        $pdo->commit();

        // Recarregar dados do requerimento para refletir as mudanças
        $requerimento = buscarDadosRequerimento($pdo, $id);

        $mensagem = "✅ Processo reaberto com sucesso! Status alterado para '{$novoStatus}'.";
        $mensagemTipo = "success";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $mensagem = "Erro ao reabrir o processo: " . $e->getMessage();
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

    /* Custom Select Styles */
    #template-select {
        border: 1px solid var(--gray-300);
        border-radius: var(--radius-sm);
        padding: 0.875rem;
        font-size: 0.875rem;
        transition: var(--transition);
        background: white;
        box-shadow: var(--shadow-sm);
        width: 100%;
        cursor: pointer;
    }

    #template-select:focus {
        border-color: var(--primary-600);
        box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1), var(--shadow-sm);
        outline: none;
    }

    #template-select optgroup {
        font-weight: 600;
        color: var(--gray-700);
        font-size: 0.875rem;
        background: var(--gray-50);
        padding: 8px;
    }

    #template-select option {
        padding: 10px;
        font-size: 0.875rem;
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

    /* Container para ações administrativas em layout vertical */
    .admin-actions-container {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    /* Cards grandes para ações administrativas */
    .admin-action-card-large {
        background: white;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius);
        padding: 2rem;
        transition: var(--transition);
        box-shadow: var(--shadow-sm);
        cursor: pointer;
    }

    .admin-action-card-large:hover {
        box-shadow: var(--shadow-md);
        border-color: var(--gray-300);
        transform: translateY(-2px);
        background: var(--gray-50);
    }

    .admin-action-card-large.collapsed:hover {
        background: var(--gray-50);
        border-color: var(--gray-300);
    }

    .admin-action-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid var(--gray-200);
    }

    .admin-action-header i {
        font-size: 1.25rem;
        width: 24px;
        text-align: center;
    }

    .admin-action-header h6 {
        margin: 0;
        font-weight: 600;
        color: var(--gray-800);
        font-size: 1.125rem;
        letter-spacing: 0.025em;
    }

    /* Estilos para sistema de colapso */
    .collapsible-header {
        cursor: pointer;
        user-select: none;
        transition: var(--transition);
        position: relative;
    }

    .collapsible-header:hover {
        background: var(--gray-100);
        border-radius: var(--radius-sm);
    }

    .collapsible-header::after {
        content: 'Clique para fechar';
        position: absolute;
        right: 2rem;
        top: 50%;
        transform: translateY(-50%);
        font-size: 0.75rem;
        color: var(--gray-500);
        opacity: 0;
        transition: var(--transition);
    }

    .collapsible-header:hover::after {
        opacity: 1;
    }

    .collapse-icon {
        transition: var(--transition);
        color: var(--gray-500);
        font-size: 0.875rem;
    }

    .collapsible-card.collapsed .collapse-icon {
        transform: rotate(-90deg);
    }

    .collapsible-content {
        overflow: hidden;
        transition: all 0.3s ease;
        max-height: 1000px;
        opacity: 1;
    }

    .collapsible-card.collapsed .collapsible-content {
        max-height: 0;
        opacity: 0;
        padding-top: 0;
        padding-bottom: 0;
        margin-top: 0;
        margin-bottom: 0;
    }

    /* Formulários modernos */
    .modern-select,
    .modern-textarea,
    .modern-input,
    .form-control,
    .form-select {
        border: 1px solid var(--gray-300);
        border-radius: var(--radius-sm);
        padding: 0.875rem;
        font-size: 0.875rem;
        transition: var(--transition);
        background: white;
        box-shadow: var(--shadow-sm);
    }

    .modern-select:focus,
    .modern-textarea:focus,
    .modern-input:focus,
    .form-control:focus,
    .form-select:focus {
        border-color: var(--primary-600);
        box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1), var(--shadow-sm);
        outline: none;
        transform: translateY(-1px);
    }

    .form-label {
        font-weight: 600;
        color: var(--gray-700);
        margin-bottom: 0.75rem;
        font-size: 0.875rem;
        letter-spacing: 0.025em;
    }

    /* Botões de ação modernos */
    .btn-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.875rem 1.75rem;
        border-radius: var(--radius-sm);
        font-weight: 500;
        font-size: 0.875rem;
        border: none;
        cursor: pointer;
        transition: var(--transition);
        text-decoration: none;
        min-height: 2.75rem;
        box-shadow: var(--shadow-sm);
    }

    .btn-action:hover {
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
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
        padding: 1rem;
        border-left: 4px solid var(--gray-300);
        margin-bottom: 1.25rem;
        box-shadow: var(--shadow-sm);
    }

    .action-description small {
        color: var(--gray-600);
        font-size: 0.875rem;
        line-height: 1.5;
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

    .tox-tinymce {
        border: 1px solid #ced4da !important;
        border-radius: 4px !important;
    }
    
    /* Estilo para o backdrop com blur para o modal de segurança */
    .modal-backdrop.show {
        backdrop-filter: blur(5px);
        -webkit-backdrop-filter: blur(5px);
        opacity: 0.8 !important;
        background-color: rgba(0, 0, 0, 0.6) !important;
    }
    
    #modalVerificacaoSeguranca .modal-content {
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
        border: none;
    }

    /* Estilos para processos indeferidos */
    .indeferido-card {
        background: #fef2f2 !important;
        border-color: #fecaca !important;
        opacity: 0.8;
    }

    .indeferido-header {
        background: #fee2e2 !important;
        border-color: #fecaca !important;
    }

    .indeferido-body {
        background: #fef2f2 !important;
    }

    .indeferido-card .admin-action-card {
        background: #fee2e2 !important;
        border-color: #fecaca !important;
        opacity: 0.6;
        pointer-events: none;
    }

    .indeferido-card .btn-action {
        background: #6c757d !important;
        border-color: #6c757d !important;
        cursor: not-allowed;
        pointer-events: none;
    }

    .indeferido-card input,
    .indeferido-card select,
    .indeferido-card textarea {
        background: #fee2e2 !important;
        border-color: #fecaca !important;
        color: #6c757d !important;
        pointer-events: none;
    }

    /* Estilo para card principal quando finalizado */
    .finalized-main-card {
        background: linear-gradient(45deg, #f8f9fa, #e9ecef) !important;
        border-color: #dee2e6 !important;
        opacity: 0.9;
    }

    /* Estilo para card principal quando indeferido */
    .indeferido-main-card {
        background: linear-gradient(45deg, #fef2f2, #fee2e2) !important;
        border-color: #fecaca !important;
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

    .indeferido-status-badge {
        background: #dc2626 !important;
        color: white !important;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-left: 10px;
    }

    /* Botões de ação para indeferimento */
    .btn-action-danger {
        background: var(--red-600);
        color: white;
    }

    .btn-action-danger:hover {
        background: #b91c1c;
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        color: white;
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

    /* Botão de reabertura */
    .btn-reopen {
        background: linear-gradient(45deg, #f59e0b, #d97706);
        border: none;
        color: white;
        border-radius: var(--radius-sm);
        padding: 0.5rem 1rem;
        font-weight: 500;
        transition: var(--transition);
        box-shadow: var(--shadow-sm);
    }

    .btn-reopen:hover {
        background: linear-gradient(45deg, #d97706, #b45309);
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
        color: white;
    }

    /* Estilo especial para processo finalizado com reabertura */
    .finalized-reopen-section {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border: 2px dashed #dee2e6;
        border-radius: var(--radius);
        padding: 2rem;
        margin: 1rem 0;
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
        cursor: pointer;
    }

    /* CSS para assinatura */
    .signature-pad-container {
        display: flex;
        justify-content: center;
    }

    #signature-canvas {
        background-color: #fff;
        touch-action: none;
    }

    #signature-preview {
        background-color: #f8f9fa;
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

    /* Estilos específicos para o modal de pré-visualização de email */
    #emailPreviewModal .modal-dialog {
        max-width: 800px;
    }

    #emailPreviewModal .modal-header {
        background: linear-gradient(135deg, var(--primary-50), var(--gray-50));
        border-bottom: 2px solid var(--primary-100);
    }

    #emailPreviewModal .modal-title {
        color: var(--primary-700);
        font-weight: 600;
    }

    .email-preview-info {
        background: var(--gray-50);
        border-radius: var(--radius-sm);
        padding: 1rem;
    }

    .email-preview-info small {
        color: var(--gray-500);
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 500;
    }

    .email-preview-info strong {
        color: var(--gray-900);
        font-weight: 600;
    }

    #email-preview-content {
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-sm);
        background: #f8f9fa;
        min-height: 200px;
    }

    /* Animação suave para os botões de pré-visualização */
    .btn-outline-info {
        border-color: #0ea5e9;
        color: #0ea5e9;
        transition: var(--transition);
    }

    .btn-outline-info:hover {
        background: #0ea5e9;
        border-color: #0ea5e9;
        color: white;
        transform: translateY(-1px);
        box-shadow: var(--shadow-sm);
    }

    /* Estilo para o botão de copiar conteúdo */
    .btn-primary {
        background: var(--primary-600);
        border-color: var(--primary-600);
    }

    .btn-primary:hover {
        background: var(--primary-700);
        border-color: var(--primary-700);
        transform: translateY(-1px);
        box-shadow: var(--shadow-sm);
    }

    /* Feedback visual para quando o conteúdo é copiado */
    .btn-success {
        background: #10b981 !important;
        border-color: #10b981 !important;
    }

    /* Melhorar a responsividade do modal */
    @media (max-width: 768px) {
        #emailPreviewModal .modal-dialog {
            max-width: 95%;
            margin: 1rem;
        }

        #emailPreviewModal .modal-body {
            padding: 0.75rem;
        }

        .email-preview-info .row {
            flex-direction: column;
        }

        .email-preview-info .col-md-6 {
            margin-bottom: 0.75rem;
        }
    }

    /* Estilos melhorados para os botões dos modais */
    .modal-footer .btn {
        padding: 0.75rem 1.5rem;
        font-weight: 500;
        border-radius: var(--radius-sm);
        transition: all 0.2s ease;
        min-width: 120px;
        font-size: 0.875rem;
    }

    .modal-footer .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }

    .modal-footer .btn-outline-secondary {
        border-color: var(--gray-400);
        color: var(--gray-600);
    }

    .modal-footer .btn-outline-secondary:hover {
        background-color: var(--gray-100);
        border-color: var(--gray-500);
        color: var(--gray-700);
    }

    .modal-footer .btn-outline-info {
        border-color: #0ea5e9;
        color: #0ea5e9;
    }

    .modal-footer .btn-outline-info:hover {
        background-color: #0ea5e9;
        border-color: #0ea5e9;
        color: white;
    }

    .modal-footer .btn-success {
        background: linear-gradient(135deg, #10b981, #059669);
        border-color: #10b981;
        color: white;
    }

    .modal-footer .btn-success:hover {
        background: linear-gradient(135deg, #059669, #047857);
        border-color: #059669;
        color: white;
    }

    .modal-footer .btn-danger {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        border-color: #ef4444;
        color: white;
    }

    .modal-footer .btn-danger:hover {
        background: linear-gradient(135deg, #dc2626, #b91c1c);
        border-color: #dc2626;
        color: white;
    }

    .modal-footer .btn-primary {
        background: linear-gradient(135deg, var(--primary-600), var(--primary-700));
        border-color: var(--primary-600);
        color: white;
    }

    .modal-footer .btn-primary:hover {
        background: linear-gradient(135deg, var(--primary-700), #047857);
        border-color: var(--primary-700);
        color: white;
    }

    /* Melhorar o layout dos botões */
    .modal-footer .d-flex.gap-2 {
        gap: 0.75rem !important;
    }

         /* Responsividade para os botões */
     @media (max-width: 576px) {
         .modal-footer {
             flex-direction: column;
             gap: 0.75rem;
         }

         .modal-footer .d-flex.gap-2 {
             width: 100%;
             justify-content: center;
         }

         .modal-footer .btn {
             min-width: auto;
             flex: 1;
         }
     }

     /* Estilos para a mensagem tutorial discreta */
     .tutorial-message {
         animation: slideInDown 0.3s ease-out;
         opacity: 0.85;
         transition: opacity 0.3s ease;
     }

     .tutorial-message:hover {
         opacity: 1;
     }

     .tutorial-message .alert {
         border-radius: var(--radius-sm);
         box-shadow: 0 1px 3px rgba(0,0,0,0.05);
         background-color: #fafbfc;
         border: 1px solid #e1e5e9;
         font-size: 0.75rem;
     }

     .tutorial-message .btn-sm {
         font-size: 0.65rem;
         padding: 0.2rem 0.4rem;
         border-radius: var(--radius-sm);
         transition: var(--transition);
         border-width: 1px;
         min-height: auto;
     }

     .tutorial-message .btn-sm:hover {
         transform: none;
         box-shadow: none;
         background-color: #f8f9fa;
     }

     .tutorial-message .btn-close-sm {
         font-size: 0.65rem;
         opacity: 0.5;
         transition: opacity 0.2s ease;
     }

     .tutorial-message .btn-close-sm:hover {
         opacity: 0.8;
     }

     @keyframes slideInDown {
         from {
             opacity: 0;
             transform: translateY(-10px);
         }
         to {
             opacity: 1;
             transform: translateY(0);
         }
     }

     /* Responsividade para a mensagem tutorial */
     @media (max-width: 768px) {
         .tutorial-message .d-flex {
             flex-direction: column;
             align-items: stretch;
         }

         .tutorial-message .flex-grow-1 {
             flex-direction: column !important;
             align-items: stretch !important;
         }

         .tutorial-message .btn-sm {
             width: 100%;
             margin-bottom: 0.5rem;
             font-size: 0.7rem;
             padding: 0.3rem 0.5rem;
         }

         .tutorial-message .gap-1 {
             gap: 0.5rem !important;
         }

         .tutorial-message .alert {
             font-size: 0.7rem;
             padding: 0.5rem 0.75rem;
         }

         .tutorial-message .ms-2 {
             margin-left: 0 !important;
             margin-top: 0.5rem;
         }
     }
</style>

<?php
// Verificar se o processo está finalizado ou indeferido
$isFinalized = (strtolower($requerimento['status']) === 'finalizado');
$isIndeferido = (strtolower($requerimento['status']) === 'indeferido');
$isBlocked = $isFinalized || $isIndeferido;
?>

<div class="container-fluid px-4">
    <!-- RESUMO DO REQUERIMENTO -->
    <div class="card-modern mb-4 <?php echo $isFinalized ? 'finalized-main-card' : ($isIndeferido ? 'indeferido-main-card' : ''); ?>">
        <div class="card-body d-flex flex-wrap align-items-center justify-content-between">
            <div>
                <div class="d-flex align-items-center mb-2">
                    <h4 class="mb-0 me-3" style="color: var(--gray-800); letter-spacing: 0.5px;">
                        Protocolo <span class="fw-bold" style="color: var(--primary-600);">#<?php echo $requerimento['protocolo']; ?></span>
                    </h4>
                    <!-- Botão Marcar como Não Lido -->
                    <?php if (!$isBlocked): ?>
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
                    <span style="color: var(--primary-600);"><?php echo $tipos_alvara[$requerimento['tipo_alvara']]['nome'] ?? ucwords(str_replace('_', ' ', $requerimento['tipo_alvara'])); ?></span>
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
                        <div class="data-value"><?php echo htmlspecialchars($tipos_alvara[$requerimento['tipo_alvara']]['nome'] ?? ucwords(str_replace('_', ' ', $requerimento['tipo_alvara']))); ?></div>
                        <div class="data-actions">
                            <button class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($tipos_alvara[$requerimento['tipo_alvara']]['nome'] ?? ucwords(str_replace('_', ' ', $requerimento['tipo_alvara']))); ?>', this)" title="Copiar tipo de alvará">
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
                        <div class="data-value"><?php echo nl2br(htmlspecialchars($requerimento['endereco_objetivo'] ?? '')); ?></div>
                        <div class="data-actions">
                            <button class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($requerimento['endereco_objetivo'] ?? ''); ?>', this)" title="Copiar endereço">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    <?php if (!empty($requerimento['ctf_numero'])): ?>
                        <div class="data-row">
                            <div class="data-label">Cadastro Técnico Federal:</div>
                            <div class="data-value"><?php echo htmlspecialchars($requerimento['ctf_numero']); ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($requerimento['licenca_anterior_numero'])): ?>
                        <div class="data-row">
                            <div class="data-label">Licença anterior:</div>
                            <div class="data-value"><?php echo htmlspecialchars($requerimento['licenca_anterior_numero']); ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($requerimento['publicacao_diario_oficial'])): ?>
                        <div class="data-row">
                            <div class="data-label">Publicação em D.O.:</div>
                            <div class="data-value"><?php echo nl2br(htmlspecialchars($requerimento['publicacao_diario_oficial'])); ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($requerimento['comprovante_pagamento'])): ?>
                        <div class="data-row">
                            <div class="data-label">Comprovante de pagamento:</div>
                            <div class="data-value"><?php echo nl2br(htmlspecialchars($requerimento['comprovante_pagamento'])); ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if ($requerimento['possui_estudo_ambiental'] !== null): ?>
                        <div class="data-row">
                            <div class="data-label">Estudo ambiental:</div>
                            <div class="data-value">
                                <?php echo $requerimento['possui_estudo_ambiental'] ? 'Sim' : 'Não'; ?>
                                <?php if (!empty($requerimento['tipo_estudo_ambiental'])): ?>
                                    <span class="ms-2 text-muted">(<?php echo htmlspecialchars($requerimento['tipo_estudo_ambiental']); ?>)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($requerimento['observacoes'])): ?>
                        <div class="data-row">
                            <div class="data-label">Observações:</div>
                                                    <div class="data-value"><?php echo nl2br(htmlspecialchars($requerimento['observacoes'] ?? '')); ?></div>
                        <div class="data-actions">
                            <button class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($requerimento['observacoes'] ?? ''); ?>', this)" title="Copiar observações">
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
                            <span class="fw-bold text-dark"><?php echo htmlspecialchars($requerimento['requerente_nome'] ?? ''); ?></span>
                        </div>
                        <div class="data-actions">
                            <button class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($requerimento['requerente_nome'] ?? ''); ?>', this)" title="Copiar nome">
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
                            <span class="fw-bold text-dark"><?php echo htmlspecialchars($requerimento['proprietario_nome'] ?? ''); ?></span>
                        </div>
                        <div class="data-actions">
                            <button class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($requerimento['proprietario_nome'] ?? ''); ?>', this)" title="Copiar nome do proprietário">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                        <div class="data-row">
                            <div class="data-label">CPF/CNPJ:</div>
                                                    <div class="data-value"><?php echo $requerimento['proprietario_cpf_cnpj'] ?? ''; ?></div>
                        <div class="data-actions">
                            <button class="copy-btn" onclick="copyToClipboard('<?php echo $requerimento['proprietario_cpf_cnpj'] ?? ''; ?>', this)" title="Copiar CPF/CNPJ do proprietário">
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

            <!-- Pareceres Técnicos -->
            <div class="modern-card mb-3" id="pareceres-section" style="display:none;">
                <div class="modern-card-header">
                    <i class="fas fa-file-contract icon"></i>
                    <h6>Pareceres Técnicos (Assinados Digitalmente)</h6>
                </div>
                <div class="card-body p-0" id="pareceres-documentos-list">
                    <!-- Pareceres serão carregados aqui -->
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
            <div class="modern-card <?php echo $isFinalized ? 'finalized-card' : ($isIndeferido ? 'indeferido-card' : ''); ?>">
                <div class="modern-card-header <?php echo $isFinalized ? 'finalized-header' : ($isIndeferido ? 'indeferido-header' : ''); ?>">
                    <i class="fas fa-cog icon <?php echo $isBlocked ? 'text-muted' : ''; ?>"></i>
                    <h6 class="<?php echo $isBlocked ? 'text-muted' : ''; ?>">Ações Administrativas</h6>
                    <?php if ($isFinalized): ?>
                        <div class="ms-auto">
                            <span class="badge bg-secondary">
                                <i class="fas fa-check-circle me-1"></i>Finalizado
                            </span>
                        </div>
                    <?php elseif ($isIndeferido): ?>
                        <div class="ms-auto">
                            <span class="badge bg-danger">
                                <i class="fas fa-times-circle me-1"></i>Indeferido
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-body <?php echo $isFinalized ? 'finalized-body' : ($isIndeferido ? 'indeferido-body' : ''); ?>">
                    <?php if ($isFinalized): ?>
                        <!-- Mensagem para processo finalizado com opção de reabertura -->
                        <div class="text-center py-4">
                            <div class="mb-3">
                                <i class="fas fa-check-circle text-muted" style="font-size: 3rem;"></i>
                            </div>
                            <h5 class="text-muted mb-2">Processo Finalizado</h5>
                            <p class="text-muted mb-4">
                                Este requerimento já foi finalizado e não permite mais alterações.
                                <br>
                                <small>O protocolo oficial já foi enviado ao requerente.</small>
                            </p>

                            <!-- Botões de ação -->
                            <div class="mt-4">
                                <button type="button" class="btn btn-warning btn-sm me-2"
                                    onclick="showReopenModal()"
                                    title="Reabrir processo para novas alterações">
                                    <i class="fas fa-unlock me-2"></i>Reabrir Processo
                                </button>
                                <button type="button" class="btn btn-danger btn-sm"
                                    onclick="showArquivarModal()"
                                    title="Arquivar processo definitivamente">
                                    <i class="fas fa-archive me-2"></i>Arquivar Processo
                                </button>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        Use apenas quando necessário. Arquivamento remove o processo da lista principal.
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($isIndeferido): ?>
                        <!-- Mensagem para processo indeferido com opção de reabertura -->
                        <div class="text-center py-4">
                            <div class="mb-3">
                                <i class="fas fa-times-circle text-danger" style="font-size: 3rem;"></i>
                            </div>
                            <h5 class="text-danger mb-2">Processo Indeferido</h5>
                            <p class="text-muted mb-4">
                                Este requerimento foi indeferido e não permite mais alterações.
                                <br>
                                <small>O requerente foi notificado por email sobre o indeferimento.</small>
                            </p>

                            <!-- Botões de ação -->
                            <div class="mt-4">
                                <button type="button" class="btn btn-warning btn-sm me-2"
                                    onclick="showReopenModal()"
                                    title="Reabrir processo para novas alterações">
                                    <i class="fas fa-unlock me-2"></i>Reabrir Processo
                                </button>
                                <button type="button" class="btn btn-danger btn-sm"
                                    onclick="showArquivarModal()"
                                    title="Arquivar processo definitivamente">
                                    <i class="fas fa-archive me-2"></i>Arquivar Processo
                                </button>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        Use apenas quando necessário. Arquivamento remove o processo da lista principal.
                                    </small>
                                </div>
                            </div>
                        </div>
                                         <?php else: ?>
                         <!-- Ações normais para processos não finalizados -->

                         <div class="admin-actions-container">
                            <!-- Atualizar Status -->
                            <div class="admin-action-card-large collapsible-card" data-card-id="status-card" onclick="openCard('status-card')">
                                <div class="admin-action-header collapsible-header" onclick="event.stopPropagation(); toggleCard('status-card')">
                                    <i class="fas fa-edit text-primary"></i>
                                    <h6>Atualizar Status</h6>
                                    <div class="ms-auto">
                                        <i class="fas fa-chevron-down collapse-icon" id="icon-status-card"></i>
                                    </div>
                                </div>
                                <div class="collapsible-content" id="content-status-card">
                                    <form method="post" action="">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label for="status" class="form-label">Status</label>
                                                <select class="form-select modern-select" id="status" name="status" required>
                                                    <option value="Em análise" <?php echo $requerimento['status'] == 'Em análise' ? 'selected' : ''; ?>>Em análise</option>
                                                    <option value="Aprovado" <?php echo $requerimento['status'] == 'Aprovado' ? 'selected' : ''; ?>>Aprovado</option>
                                                    <option value="Reprovado" <?php echo $requerimento['status'] == 'Reprovado' ? 'selected' : ''; ?>>Reprovado</option>
                                                    <option value="Pendente" <?php echo $requerimento['status'] == 'Pendente' ? 'selected' : ''; ?>>Pendente</option>
                                                    <option value="Cancelado" <?php echo $requerimento['status'] == 'Cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                                                    <option value="Finalizado" <?php echo $requerimento['status'] == 'Finalizado' ? 'selected' : ''; ?>>Finalizado</option>
                                                    <option value="Indeferido" <?php echo $requerimento['status'] == 'Indeferido' ? 'selected' : ''; ?>>Indeferido</option>
                                                    <option value="Apto a gerar alvará" <?php echo $requerimento['status'] == 'Apto a gerar alvará' ? 'selected' : ''; ?>>Apto a gerar alvará</option>
                                                    <option value="Alvará Emitido" <?php echo $requerimento['status'] == 'Alvará Emitido' ? 'selected' : ''; ?>>Alvará Emitido</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="observacoes" class="form-label">Observações</label>
                                                <textarea class="form-control modern-textarea" id="observacoes" name="observacoes" rows="3"
                                                    placeholder="Adicione observações ou feedback para o requerente"><?php echo htmlspecialchars($requerimento['observacoes'] ?? ''); ?></textarea>
                                            </div>
                                        </div>
                                        <div class="mt-3">
                                            <button type="submit" class="btn-action btn-action-primary">
                                                <i class="fas fa-save me-2"></i>Salvar Alterações
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                                                        <!-- Conclusão do Processo -->
                            <div class="admin-action-card-large collapsible-card" data-card-id="finalizacao-card" onclick="openCard('finalizacao-card')">
                                <div class="admin-action-header collapsible-header" onclick="event.stopPropagation(); toggleCard('finalizacao-card')">
                                    <i class="fas fa-check-circle text-success"></i>
                                    <h6>Finalização do Processo</h6>
                                    <div class="ms-auto">
                                        <i class="fas fa-chevron-down collapse-icon" id="icon-finalizacao-card"></i>
                                    </div>
                                </div>
                                <div class="collapsible-content" id="content-finalizacao-card">
                                    <div class="action-description mb-3">
                                        <i class="fas fa-info-circle text-info me-2"></i>
                                        <small class="text-muted">Finalize o processo enviando o protocolo oficial para o requerente</small>
                                    </div>
                                    <div class="alert alert-info alert-sm mb-3" style="padding: 12px 16px; font-size: 13px; border-radius: 8px; background-color: #e3f2fd; border: 1px solid #bbdefb; color: #1976d2; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                        <i class="fas fa-info-circle me-1"></i>
                                        <strong>Atenção:</strong> Ao enviar o email, o status será automaticamente alterado para "Finalizado".
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-8">
                                            <label for="protocolo_oficial" class="form-label">Protocolo Oficial da Prefeitura</label>
                                            <input type="text" class="form-control modern-input" id="protocolo_oficial" name="protocolo_oficial"
                                                placeholder="Ex: 2025001234-SEMA" required>
                                        </div>
                                        <div class="col-md-4 d-flex align-items-end">
                                            <button type="button" class="btn-action btn-action-success w-100"
                                                onclick="showProtocolConfirmModal()">
                                                <i class="fas fa-paper-plane me-2"></i>Enviar Protocolo Oficial
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                                                        <!-- Indeferimento do Processo -->
                            <div class="admin-action-card-large collapsible-card" data-card-id="indeferimento-card" onclick="openCard('indeferimento-card')">
                                <div class="admin-action-header collapsible-header" onclick="event.stopPropagation(); toggleCard('indeferimento-card')">
                                    <i class="fas fa-times-circle text-danger"></i>
                                    <h6>Indeferir Processo</h6>
                                    <div class="ms-auto">
                                        <i class="fas fa-chevron-down collapse-icon" id="icon-indeferimento-card"></i>
                                    </div>
                                </div>
                                <div class="collapsible-content" id="content-indeferimento-card">
                                    <div class="action-description mb-3" style="border-left-color: var(--red-600);">
                                        <i class="fas fa-exclamation-circle text-danger me-2"></i>
                                        <small class="text-muted">Negue o requerimento informando os motivos detalhados</small>
                                    </div>
                                    <div class="alert alert-warning alert-sm mb-3" style="padding: 12px 16px; font-size: 13px; border-radius: 8px; background-color: #fef3c7; border: 1px solid #fbbf24; color: #92400e; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        <strong>Atenção:</strong> O requerente será notificado por email sobre o indeferimento.
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="motivo_indeferimento" class="form-label">Motivo do Indeferimento</label>
                                            <textarea class="form-control modern-textarea" id="motivo_indeferimento" name="motivo_indeferimento" rows="3"
                                                placeholder="Descreva detalhadamente o motivo do indeferimento..." required></textarea>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="orientacoes_adicionais" class="form-label">Orientações Adicionais <small class="text-muted">(opcional)</small></label>
                                            <textarea class="form-control modern-textarea" id="orientacoes_adicionais" name="orientacoes_adicionais" rows="3"
                                                placeholder="Orientações para correção ou reenvio do processo..."></textarea>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <button type="button" class="btn-action btn-action-danger"
                                            onclick="showIndeferimentoModal()">
                                            <i class="fas fa-times me-2"></i>Indeferir Processo
                                        </button>
                                    </div>
                                </div>
                            </div>

                                                        <!-- Card de Arquivamento -->
                            <div class="admin-action-card-large collapsible-card" data-card-id="arquivamento-card" onclick="openCard('arquivamento-card')">
                                <div class="admin-action-header collapsible-header" onclick="event.stopPropagation(); toggleCard('arquivamento-card')">
                                    <i class="fas fa-archive text-secondary"></i>
                                    <h6>Arquivar Processo</h6>
                                    <div class="ms-auto">
                                        <i class="fas fa-chevron-down collapse-icon" id="icon-arquivamento-card"></i>
                                    </div>
                                </div>
                                <div class="collapsible-content" id="content-arquivamento-card">
                                    <div class="action-description mb-3" style="border-left-color: var(--gray-400);">
                                        <i class="fas fa-info-circle text-secondary me-2"></i>
                                        <small class="text-muted">Remove o processo da lista principal sem deletar permanentemente</small>
                                    </div>
                                    <div class="alert alert-warning alert-sm mb-3" style="padding: 12px 16px; font-size: 13px; border-radius: 8px; background-color: #fef3c7; border: 1px solid #fbbf24; color: #92400e; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        <strong>Atenção:</strong> O processo será movido para arquivo e ficará oculto da lista principal.
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-8">
                                            <label for="motivo_arquivamento" class="form-label">Motivo do Arquivamento</label>
                                            <textarea class="form-control modern-textarea" id="motivo_arquivamento" name="motivo_arquivamento" rows="3"
                                                placeholder="Descreva o motivo do arquivamento..." required></textarea>
                                        </div>
                                        <div class="col-md-4 d-flex align-items-end">
                                            <button type="button" class="btn-action" style="background: var(--gray-600); color: white;"
                                                onclick="showArquivarModal()">
                                                <i class="fas fa-archive me-2"></i>Arquivar Processo
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Card de Gerar Parecer Técnico -->
                            <div class="admin-action-card-large collapsible-card" data-card-id="parecer-card" onclick="openCard('parecer-card')">
                                <div class="admin-action-header collapsible-header" onclick="event.stopPropagation(); toggleCard('parecer-card')">
                                    <i class="fas fa-file-contract text-info"></i>
                                    <h6>Gerar Parecer Técnico</h6>
                                    <div class="ms-auto">
                                        <i class="fas fa-chevron-down collapse-icon" id="icon-parecer-card"></i>
                                    </div>
                                </div>
                                <div class="collapsible-content" id="content-parecer-card">
                                    <div class="action-description mb-3">
                                        <i class="fas fa-info-circle text-info me-2"></i>
                                        <small class="text-muted">Gere documentos técnicos preenchidos automaticamente com os dados do requerimento</small>
                                    </div>

                                    <button type="button" class="btn-action btn-action-primary" onclick="abrirModalParecer()">
                                        <i class="fas fa-plus me-2"></i>Criar Novo Parecer
                                    </button>

                                    <div id="pareceres-existentes-list" class="mt-3"></div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Geração de Parecer -->
<div class="modal fade" id="parecerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-file-contract text-info me-2"></i>
                    Gerar Parecer Técnico
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Etapa 1: Seleção de Template -->
                <div id="etapa-selecao-template">
                    <label class="form-label">Selecione o Template:</label>
                    <select id="template-select" class="form-select mb-3"></select>
                    <button type="button" class="btn btn-primary" onclick="carregarTemplateParaEdicao()">
                        <i class="fas fa-file-import me-2"></i>Carregar Template
                    </button>
                </div>

                <!-- Etapa 2: Editor -->
                <div id="etapa-editor" style="display:none;">
                    <div class="alert alert-info mb-3">
                        <i class="fas fa-lightbulb me-2"></i>
                        O template foi preenchido automaticamente. Edite conforme necessário.
                    </div>
                    <textarea id="editor-parecer-content"></textarea>
                    <div class="mt-3 d-flex gap-2">
                        <button type="button" class="btn btn-success" onclick="irParaAssinatura()">
                            <i class="fas fa-arrow-right me-2"></i>Continuar para Assinar e Posicionar
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="voltarParaSelecao()">
                            <i class="fas fa-arrow-left me-2"></i>Voltar
                        </button>
                    </div>
                </div>

                <!-- Etapa 3 (única): Posicionamento da Assinatura -->
                <div id="etapa-posicionamento" style="display:none;">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div class="alert alert-info mb-0 flex-grow-1 py-2 px-3">
                            <i class="fas fa-hand-paper me-2"></i>
                            Arraste a assinatura ou use as posições rápidas para finalizar
                        </div>
                        <span id="assinatura-status-badge" class="badge bg-secondary">Assinatura: Digitada (Arial)</span>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="abrirConfigAssinatura()">
                            <i class="fas fa-edit me-1"></i>Configurar assinatura
                        </button>
                    </div>
                    <div class="mb-3 d-flex align-items-center gap-2">
                        <span class="text-muted small">Posições rápidas:</span>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="posicionarAssinaturaRapido('esquerda')">Esquerda</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="posicionarAssinaturaRapido('centro')">Centro</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="posicionarAssinaturaRapido('direita')">Direita</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetarPosicaoAssinatura()">Reposicionar padrão</button>
                    </div>
                    <div class="mb-3">
                        <div id="preview-documento" style="position: relative; width: 210mm; height: 297mm; margin: 0 auto; background: white; border: 2px solid #ddd; overflow: hidden;">
                            <img id="preview-fundo" src="" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 1;" />
                            <div id="preview-conteudo" style="position: absolute; top: 150px; left: 60px; width: calc(100% - 120px); z-index: 2; font-family: 'Times New Roman', Times, serif; font-size: 12pt; color: #000;" contenteditable="true"></div>
                            <div id="bloco-assinatura-arrastavel" class="assinatura-bloco-arrastavel" draggable="true" style="position: absolute; z-index: 3; cursor: move; display: flex; align-items: center; gap: 15px; background: rgba(255, 255, 255, 0.95); padding: 10px; border: 2px dashed #007bff; border-radius: 4px; min-width: 220px;">
                                <img id="preview-qr-code" src="" style="width: 60px; height: 60px; flex-shrink: 0;" />
                                <div style="font-size: 12px; text-align: left; display: flex; flex-direction: column; gap: 4px;">
                                    <strong id="preview-nome-assinante"></strong>
                                    <span id="preview-cargo-assinante"></span>
                                    <span id="preview-assinatura-visual" style="font-size: 16px; display: block;"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-warning">
                        <small><i class="fas fa-info-circle me-1"></i>Posição inicial: Canto inferior direito. Arraste para reposicionar.</small>
                    </div>
                    <div class="card mb-3">
                        <div class="card-body">
                            <h6 class="card-title mb-2"><i class="fas fa-lock me-2"></i>Validação final</h6>
                            <label class="form-label mb-1">Digite sua senha para assinar:</label>
                            <input type="password" id="senha-finalizacao" class="form-control" placeholder="Senha da sua conta">
                            <div id="erro-senha-finalizacao" class="text-danger small mt-2" style="display:none;"></div>
                        </div>
                    </div>
                    <div class="mt-3 d-flex gap-2">
                        <button type="button" class="btn btn-success" onclick="confirmarPosicaoEGerarPdf()">
                            <i class="fas fa-check me-2"></i>Assinar e Gerar PDF
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="voltarParaEditor()">
                            <i class="fas fa-arrow-left me-2"></i>Voltar para Edição
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Indeferimento de Processo -->
<div class="modal fade" id="indeferimentoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-times-circle text-danger me-2"></i>
                    Indeferir Processo
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Atenção:</strong> O requerente será notificado por email sobre o indeferimento do processo.
                </div>

                <div class="mb-3">
                    <strong>Destinatário:</strong> <?php echo htmlspecialchars($requerimento['requerente_nome'] ?? ''); ?>
                </div>
                <div class="mb-3">
                    <strong>Email:</strong> <?php echo htmlspecialchars($requerimento['requerente_email'] ?? ''); ?>
                </div>
                <div class="mb-3">
                    <strong>Protocolo:</strong> #<?php echo $requerimento['protocolo']; ?>
                </div>
                <div class="mb-3">
                    <strong>Tipo de Alvará:</strong> <?php echo htmlspecialchars($requerimento['tipo_alvara'] ?? ''); ?>
                </div>

                <div class="mb-3">
                    <strong>Motivo do Indeferimento:</strong> <span id="motivo-display"></span>
                </div>

                <div class="mb-3" id="orientacoes-display-container" style="display: none;">
                    <strong>Orientações Adicionais:</strong> <span id="orientacoes-display"></span>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    Cancelar
                </button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-info" onclick="previewIndeferimentoEmail()">
                        Pré-visualizar Email
                    </button>
                    <form method="post" action="" style="display: inline;">
                        <input type="hidden" id="hidden_motivo_indeferimento" name="motivo_indeferimento">
                        <input type="hidden" id="hidden_orientacoes_adicionais" name="orientacoes_adicionais">
                        <button type="submit" name="indeferir_processo" class="btn btn-danger">
                            Confirmar Indeferimento
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

<!-- Modal de Configuração de Assinatura -->
<div class="modal fade" id="configAssinaturaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-pen-fancy me-2"></i>Configurar assinatura</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs mb-3" id="assinaturaTabsConfig" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="config-digitar-tab" data-bs-toggle="tab"
                                data-bs-target="#config-digitar-assinatura" type="button">
                            <i class="fas fa-font me-2"></i>Assinatura digitada
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="config-desenhar-tab" data-bs-toggle="tab"
                                data-bs-target="#config-desenhar-assinatura" type="button">
                            <i class="fas fa-pencil-alt me-2"></i>Desenhar assinatura
                        </button>
                    </li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="config-digitar-assinatura">
                        <div class="mb-3">
                            <label class="form-label">Nome a exibir:</label>
                            <input type="text" id="signature-text" class="form-control" placeholder="Nome completo">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Fonte:</label>
                            <div class="d-flex align-items-center">
                                <button type="button" class="btn btn-outline-secondary btn-sm me-2" onclick="anteriorFonte()" title="Fonte anterior">
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                                <div class="flex-grow-1 text-center">
                                    <span id="fonte-atual" class="fw-bold">Arial</span>
                                </div>
                                <button type="button" class="btn btn-outline-secondary btn-sm ms-2" onclick="proximaFonte()" title="Próxima fonte">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>
                            <input type="hidden" id="signature-font" value="'Arial', sans-serif">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Preview:</label>
                            <div id="signature-preview" style="border: 2px solid #dee2e6; border-radius: 8px;
                                     padding: 20px; min-height: 100px; display: flex; align-items: center;
                                     justify-content: center; font-size: 32px; font-family: Arial, sans-serif;">
                                <?php echo htmlspecialchars($_SESSION['admin_nome']); ?>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="config-desenhar-assinatura">
                        <div class="signature-pad-container mb-3">
                            <canvas id="signature-canvas" width="600" height="200"
                                    style="border: 2px solid #dee2e6; border-radius: 8px; cursor: crosshair;">
                            </canvas>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="limparAssinatura()">
                            <i class="fas fa-eraser me-1"></i>Limpar
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <div class="text-muted small">Escolha o tipo de assinatura e salve para atualizar o bloco.</div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="salvarConfigAssinatura()">Salvar e aplicar</button>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<!-- Modal para Reabertura de Processo -->
<div class="modal fade" id="reopenProcessModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-unlock text-warning me-2"></i>
                    Reabrir Processo Finalizado
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Atenção:</strong> Esta ação irá reabrir o processo finalizado, permitindo novas alterações.
                    </div>

                    <div class="mb-3">
                        <label for="novo_status" class="form-label">Novo Status</label>
                        <select class="form-select" id="novo_status" name="novo_status" required>
                            <option value="Em análise">Em análise</option>
                            <option value="Aprovado">Aprovado</option>
                            <option value="Reprovado">Reprovado</option>
                            <option value="Pendente">Pendente</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="motivo_reabertura" class="form-label">Motivo da Reabertura</label>
                        <textarea class="form-control" id="motivo_reabertura" name="motivo_reabertura"
                            rows="3" placeholder="Descreva o motivo da reabertura do processo..." required></textarea>
                    </div>

                    <div class="mb-3">
                        <strong>Protocolo Atual:</strong> #<?php echo $requerimento['protocolo']; ?>
                    </div>
                    <div class="mb-3">
                        <strong>Requerente:</strong> <?php echo htmlspecialchars($requerimento['requerente_nome'] ?? ''); ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                    <button type="submit" name="reabrir_processo" class="btn btn-warning">
                        <i class="fas fa-unlock me-2"></i>Confirmar Reabertura
                    </button>
                </div>
            </form>
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
                    <strong>Destinatário:</strong> <?php echo htmlspecialchars($requerimento['requerente_nome'] ?? ''); ?>
                </div>
                <div class="mb-3">
                    <strong>Email:</strong> <?php echo htmlspecialchars($requerimento['requerente_email'] ?? ''); ?>
                </div>
                <div class="mb-3">
                    <strong>Protocolo:</strong> <span id="protocol-display"></span>
                </div>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    O requerente receberá um email com o protocolo oficial da prefeitura.
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    Cancelar
                </button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-info" onclick="previewProtocolEmail()">
                        Pré-visualizar Email
                    </button>
                    <form method="post" action="" style="display: inline;">
                        <input type="hidden" id="hidden_protocolo_oficial" name="protocolo_oficial">
                        <button type="submit" name="enviar_email_protocolo" class="btn btn-success">
                            Confirmar Envio
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Arquivamento de Processo -->
<div class="modal fade" id="arquivarModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-archive text-warning me-2"></i>
                    Arquivar Processo
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Atenção:</strong> O processo será movido para o arquivo e ficará oculto da lista principal.
                    </div>

                    <div class="mb-3">
                        <strong>Protocolo:</strong> #<?php echo $requerimento['protocolo']; ?>
                    </div>
                    <div class="mb-3">
                        <strong>Requerente:</strong> <?php echo htmlspecialchars($requerimento['requerente_nome'] ?? ''); ?>
                    </div>
                    <div class="mb-3">
                        <strong>Status Atual:</strong> <?php echo $requerimento['status']; ?>
                    </div>

                    <div class="mb-3">
                        <label for="modal_motivo_arquivamento" class="form-label">Motivo do Arquivamento</label>
                        <textarea class="form-control" id="modal_motivo_arquivamento" name="motivo_arquivamento"
                            rows="3" placeholder="Descreva o motivo do arquivamento..." required></textarea>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Informação:</strong> O processo não será deletado permanentemente e pode ser recuperado posteriormente se necessário.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                    <button type="submit" name="arquivar_processo" class="btn btn-warning">
                        <i class="fas fa-archive me-2"></i>Confirmar Arquivamento
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de Pré-visualização de Email -->
<div class="modal fade" id="emailPreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">
                    <i class="fas fa-eye text-primary me-2"></i>
                    Pré-visualização do Email
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body p-0">
                <div class="border-bottom bg-light p-3">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <small class="text-muted d-block">Para:</small>
                            <strong id="preview-destinatario"></strong>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">Email:</small>
                            <strong id="preview-email"></strong>
                        </div>
                        <div class="col-12">
                            <small class="text-muted d-block">Assunto:</small>
                            <strong id="preview-assunto"></strong>
                        </div>
                    </div>
                </div>
                <div class="p-3" style="max-height: 500px; overflow-y: auto;">
                    <div id="email-preview-content" style="font-family: Arial, sans-serif; line-height: 1.6;"></div>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    Fechar Pré-visualização
                </button>
                <button type="button" class="btn btn-primary" onclick="copyEmailContent()">
                    Copiar Conteúdo
                </button>
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

    // Função para mostrar modal de indeferimento
    function showIndeferimentoModal() {
        const motivoInput = document.getElementById('motivo_indeferimento');
        const motivoValue = motivoInput.value.trim();

        if (!motivoValue) {
            alert('Por favor, informe o motivo do indeferimento antes de continuar.');
            motivoInput.focus();
            return;
        }

        if (motivoValue.length < 10) {
            alert('O motivo do indeferimento deve ter pelo menos 10 caracteres.');
            motivoInput.focus();
            return;
        }

        // Buscar orientações adicionais
        const orientacoesInput = document.getElementById('orientacoes_adicionais');
        const orientacoesValue = orientacoesInput ? orientacoesInput.value.trim() : '';

        document.getElementById('motivo-display').textContent = motivoValue;
        document.getElementById('hidden_motivo_indeferimento').value = motivoValue;

        if (orientacoesValue) {
            document.getElementById('orientacoes-display').textContent = orientacoesValue;
            document.getElementById('hidden_orientacoes_adicionais').value = orientacoesValue;
            document.getElementById('orientacoes-display-container').style.display = 'block';
        } else {
            document.getElementById('orientacoes-display-container').style.display = 'none';
            document.getElementById('hidden_orientacoes_adicionais').value = '';
        }

        const modal = new bootstrap.Modal(document.getElementById('indeferimentoModal'));
        modal.show();
    }

    // Função para mostrar modal de reabertura
    function showReopenModal() {
        const modal = new bootstrap.Modal(document.getElementById('reopenProcessModal'));
        modal.show();
    }

    // Função para mostrar modal de arquivamento
    function showArquivarModal() {
        const motivoInput = document.getElementById('motivo_arquivamento');
        const motivoValue = motivoInput ? motivoInput.value.trim() : '';

        // Se há um motivo preenchido no formulário, usar ele no modal
        if (motivoValue) {
            document.getElementById('modal_motivo_arquivamento').value = motivoValue;
        }

        const modal = new bootstrap.Modal(document.getElementById('arquivarModal'));
        modal.show();
    }

    // Função para pré-visualizar email de protocolo oficial
    function previewProtocolEmail() {
        const protocolInput = document.getElementById('protocolo_oficial');
        const protocolValue = protocolInput.value.trim();

        if (!protocolValue) {
            alert('Por favor, informe o protocolo oficial antes de visualizar.');
            protocolInput.focus();
            return;
        }

        // Dados para o template
        const dados = {
            nome_destinatario: '<?php echo addslashes(htmlspecialchars($requerimento['requerente_nome'] ?? '')); ?>',
            protocolo_oficial: protocolValue
        };

        // Conteúdo do email de protocolo oficial
        const emailContent = `
            <div style="max-width: 600px; margin: 0 auto; background-color: #f4f4f4; padding: 20px;">
                <div style="background-color: #ffffff; border-radius: 5px; padding: 30px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);">
                    <div style="margin: 20px 0; text-align: left;">
                        <p>Prezado(a) <strong>${dados.nome_destinatario}</strong>,</p>

                        <p>Encaminhamos o número de protocolo referente ao processo requerido: <strong style="color: #009851;">${dados.protocolo_oficial}</strong></p>

                        <p>O protocolo pode ser acompanhado pelo site da Prefeitura no link
                            <a href="https://www.paudosferros.rn.gov.br" style="color: #009851; text-decoration: none;">www.paudosferros.rn.gov.br</a>
                            na aba <strong>SERVIÇOS > PORTAL DO CONTRIBUINTE > PROTOCOLO > ACOMPANHAMENTO</strong> (aqui digite o protocolo enviado).
                        </p>

                        <p>O alvará poderá ser retirado na Secretaria de Meio Ambiente / Setor de Obras quando a taxa for paga na Secretaria de Tributação.</p>

                        <div style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px;">
                            <p>Atenciosamente,</p>
                            <p><strong>Setor de fiscalização ambiental<br>
                                    Secretaria Municipal de Meio Ambiente</strong></p>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Preencher dados do modal
        document.getElementById('preview-destinatario').textContent = dados.nome_destinatario;
        document.getElementById('preview-email').textContent = '<?php echo htmlspecialchars($requerimento['requerente_email'] ?? ''); ?>';
        document.getElementById('preview-assunto').textContent = 'Protocolo Oficial - Secretaria de Meio Ambiente';
        document.getElementById('email-preview-content').innerHTML = emailContent;

        // Mostrar modal
        const previewModal = new bootstrap.Modal(document.getElementById('emailPreviewModal'));
        previewModal.show();
    }

    // Função para pré-visualizar email de indeferimento
    function previewIndeferimentoEmail() {
        const motivoValue = document.getElementById('hidden_motivo_indeferimento').value;
        const orientacoesValue = document.getElementById('hidden_orientacoes_adicionais').value;

        if (!motivoValue) {
            alert('Dados do indeferimento não encontrados.');
            return;
        }

        // Dados para o template
        const dados = {
            nome_destinatario: '<?php echo addslashes(htmlspecialchars($requerimento['requerente_nome'] ?? '')); ?>',
            protocolo: '<?php echo $requerimento['protocolo']; ?>',
            motivo_indeferimento: motivoValue,
            orientacoes_adicionais: orientacoesValue
        };

        // Conteúdo do email de indeferimento
        let emailContent = `
            <div style="max-width: 600px; margin: 0 auto; background-color: #f4f4f4; padding: 20px;">
                <div style="background-color: #ffffff; border-radius: 5px; padding: 30px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);">
                    <div style="margin: 20px 0; text-align: left;">
                        <p>Prezado(a) <strong>${dados.nome_destinatario}</strong>,</p>

                        <p>Informamos que seu requerimento foi analisado pela equipe técnica da Secretaria do Meio Ambiente.</p>

                        <p><strong>PROCESSO INDEFERIDO</strong></p>

                        <p>Infelizmente, o processo de protocolo <strong style="color: #009851;">#${dados.protocolo}</strong> foi indeferido pelos seguintes motivos:</p>

                        <p><strong>${dados.motivo_indeferimento.replace(/\n/g, '<br>')}</strong></p>
        `;

        if (dados.orientacoes_adicionais) {
            emailContent += `
                        <p><strong>Orientações para Correção:</strong></p>
                        <p>${dados.orientacoes_adicionais.replace(/\n/g, '<br>')}</p>
            `;
        }

        emailContent += `
                        <p><strong>Para dar continuidade ao processo:</strong></p>
                        <ul>
                            <li>Envie um novo requerimento através do nosso sistema online</li>
                            <li>Corrija todos os pontos indicados acima</li>
                            <li>Apresente toda a documentação novamente, conforme as exigências atuais</li>
                        </ul>

                        <div style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px;">
                            <p>Atenciosamente,<br>
                                <strong>Secretaria Municipal de Meio Ambiente</strong>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Preencher dados do modal
        document.getElementById('preview-destinatario').textContent = dados.nome_destinatario;
        document.getElementById('preview-email').textContent = '<?php echo htmlspecialchars($requerimento['requerente_email'] ?? ''); ?>';
        document.getElementById('preview-assunto').textContent = 'Processo Indeferido - Secretaria de Meio Ambiente';
        document.getElementById('email-preview-content').innerHTML = emailContent;

        // Mostrar modal
        const previewModal = new bootstrap.Modal(document.getElementById('emailPreviewModal'));
        previewModal.show();
    }

    // Função para copiar conteúdo do email
    function copyEmailContent() {
        const content = document.getElementById('email-preview-content').innerText;
        navigator.clipboard.writeText(content).then(function() {
            // Feedback visual
            const button = event.target.closest('button');
            const originalContent = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check me-2"></i>Copiado!';
            button.classList.add('btn-success');
            button.classList.remove('btn-primary');

            setTimeout(function() {
                button.innerHTML = originalContent;
                button.classList.remove('btn-success');
                button.classList.add('btn-primary');
            }, 2000);
        }).catch(function(err) {
            alert('Erro ao copiar conteúdo: ' + err);
        });
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

    // Função para abrir o card (clique em qualquer lugar)
    function openCard(cardId) {
        const card = document.querySelector(`[data-card-id="${cardId}"]`);
        const content = document.getElementById(`content-${cardId}`);
        const icon = document.getElementById(`icon-${cardId}`);

        // Só abre se estiver fechado
        if (card.classList.contains('collapsed')) {
            card.classList.remove('collapsed');
            content.style.maxHeight = content.scrollHeight + 'px';
            icon.style.transform = 'rotate(0deg)';
        }
    }

    // Função para alternar o colapso dos cards (apenas no cabeçalho)
    function toggleCard(cardId) {
        const card = document.querySelector(`[data-card-id="${cardId}"]`);
        const content = document.getElementById(`content-${cardId}`);
        const icon = document.getElementById(`icon-${cardId}`);

        if (card.classList.contains('collapsed')) {
            // Expandir
            card.classList.remove('collapsed');
            content.style.maxHeight = content.scrollHeight + 'px';
            icon.style.transform = 'rotate(0deg)';
        } else {
            // Recolher
            card.classList.add('collapsed');
            content.style.maxHeight = '0';
            icon.style.transform = 'rotate(-90deg)';
        }
    }

         // Inicializar cards colapsados por padrão
     document.addEventListener('DOMContentLoaded', function() {
         const cards = document.querySelectorAll('.collapsible-card');
         cards.forEach(card => {
             card.classList.add('collapsed');
         });

         // Carregar pareceres existentes automaticamente
         carregarPareceresExistentes();

         // Event listener para preview de assinatura em tempo real
         const signatureText = document.getElementById('signature-text');
         if (signatureText) {
             signatureText.addEventListener('input', atualizarPreviewAssinatura);
         }
     });

     // Sistema de Pareceres
     let parecerModal;
     let editorTiny;
     let signaturePad;
     let configAssinaturaModal;
     let senhaMemorizada = null;
     let indiceFonteAtual = 0;
     let dadosAssinatura = null;
     let coordenadasAssinatura = { x: 0, y: 0 };
     let templateAtual = null;
     let handlerPreviewEdicao = null;
     const fontesDisponiveis = [
         { nome: 'Arial', valor: "'Arial', sans-serif" },
         { nome: 'Brush Script', valor: "'Brush Script MT', cursive" },
         { nome: 'Lucida Handwriting', valor: "'Lucida Handwriting', cursive" },
         { nome: 'Dancing Script', valor: "'Dancing Script', cursive" },
         { nome: 'Great Vibes', valor: "'Great Vibes', cursive" }
     ];

     document.addEventListener('DOMContentLoaded', () => {
         const modalElement = document.getElementById('parecerModal');
         if (modalElement) {
             modalElement.addEventListener('hidden.bs.modal', () => resetarFluxoParecer(false));
         }

         const configModalElement = document.getElementById('configAssinaturaModal');
         if (configModalElement) {
             configAssinaturaModal = new bootstrap.Modal(configModalElement);
             configModalElement.addEventListener('shown.bs.modal', () => {
                 inicializarSignaturePad();
                 atualizarFonteAtual();
                 atualizarPreviewAssinatura();
             });
         }

         const signatureTextInput = document.getElementById('signature-text');
         if (signatureTextInput) {
             signatureTextInput.value = '<?php echo $_SESSION['admin_nome']; ?>';
             atualizarPreviewAssinatura();
         }
     });

     function nomeTemplateAmigavel(template) {
         if (!template) return 'Template';

         if (typeof template === 'object' && template.label) {
             return template.label;
         }

         const nomeArquivo = typeof template === 'object' ? template.nome : template;
         if (!nomeArquivo) return 'Template';

         const semExtensao = nomeArquivo.replace(/\.[^.]+$/, '');
         const legivel = semExtensao.replace(/[_-]+/g, ' ').trim().replace(/\s+/g, ' ');

         if (!legivel) return nomeArquivo;

         return legivel
             .split(' ')
             .map(parte => parte.charAt(0).toUpperCase() + parte.slice(1))
             .join(' ');
     }

     function textoTipoTemplate(tipo) {
         const mapa = {
             docx: 'Editor online (DOCX)',
             html: ''
         };
         return mapa[tipo] || 'Documento';
     }

     async function abrirModalParecer() {
         const btn = document.querySelector('.btn-action-primary'); // Botão Gerar Parecer
         const originalHtml = btn.innerHTML;
         
         // Feedback visual de carregamento
         btn.disabled = true;
         btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Verificando...';

         const sessaoValida = await verificarSessaoAssinatura();
         
         btn.disabled = false;
         btn.innerHTML = originalHtml;

         if (sessaoValida) {
             exibirModalParecer();
         } else {
             iniciarVerificacaoEmail();
         }
     }

     function exibirModalParecer() {
         if (!parecerModal) {
             parecerModal = new bootstrap.Modal(document.getElementById('parecerModal'));
         }
         resetarFluxoParecer(true);
         parecerModal.show();
         carregarListaTemplates();
         carregarPareceresExistentes();
     }

     function carregarListaTemplates() {
         fetch('parecer_handler.php', {
             method: 'POST',
             headers: {'Content-Type': 'application/json'},
             body: JSON.stringify({
                 action: 'listar_templates',
                 requerimento_id: <?php echo $id; ?>
             })
         })
         .then(res => res.json())
         .then(data => {
             const select = document.getElementById('template-select');
             select.innerHTML = '<option value="">Selecione um modelo de parecer</option>';

             // Rascunhos Recentes
             if (data.rascunhos && data.rascunhos.length > 0) {
                 const groupRascunhos = document.createElement('optgroup');
                 groupRascunhos.label = "📝 Rascunhos Recentes";
                 data.rascunhos.forEach(r => {
                     const option = document.createElement('option');
                     option.value = r.id;
                     option.textContent = `${r.nome} - ${r.assinante} (${r.data})`;
                     groupRascunhos.appendChild(option);
                 });
                 select.appendChild(groupRascunhos);
             }

             // Modelos Padrão
             const groupModelos = document.createElement('optgroup');
             groupModelos.label = "📄 Modelos Padrão";

             const templates = data.templates_detalhados || data.templates;
             templates.forEach(t => {
                 const nome = typeof t === 'object' ? t.nome : t;
                 const tipo = typeof t === 'object' ? t.tipo : 'docx';
                 const rotulo = typeof t === 'object' ? nomeTemplateAmigavel(t) : nome;
                 const tipoLabel = textoTipoTemplate(tipo);
                 const icone = tipo === 'docx' ? '📝' : '📄';
                 
                 const option = document.createElement('option');
                 option.value = nome;
                 option.innerHTML = `${icone} ${rotulo}${tipoLabel ? ' — ' + tipoLabel : ''}`;
                 groupModelos.appendChild(option);
             });
             select.appendChild(groupModelos);
         })
         .catch(error => {
             console.error('Erro ao carregar templates:', error);
             alert('Erro ao carregar templates');
         });
     }

     function carregarTemplateParaEdicao() {
         const template = document.getElementById('template-select').value;
         if (!template) {
             alert('Selecione um template');
             return;
         }

         fetch('parecer_handler.php', {
             method: 'POST',
             headers: {'Content-Type': 'application/json'},
             body: JSON.stringify({
                 action: 'carregar_template',
                 template: template,
                 requerimento_id: <?php echo $id; ?>
             })
         })
         .then(res => res.json())
         .then(data => {
             if (data.success) {
                 document.getElementById('etapa-selecao-template').style.display = 'none';
                 document.getElementById('etapa-editor').style.display = 'block';

                 // Extrair imagem de fundo do template original para uso posterior
                 const parser = new DOMParser();
                 const doc = parser.parseFromString(data.html, 'text/html');
                 const imgFundo = doc.querySelector('#fundo-imagem');
                 if (imgFundo && imgFundo.src) {
                     // Armazenar imagem de fundo globalmente para uso no preview
                     // Converter caminho relativo para absoluto se necessário
                     let imgSrc = imgFundo.src;
                     if (imgSrc && !imgSrc.startsWith('http') && !imgSrc.startsWith('data:')) {
                         // Se for caminho relativo como 'images/image1.png', converter para caminho completo
                         if (imgSrc.startsWith('images/')) {
                             imgSrc = '../assets/doc/' + imgSrc;
                         }
                     }
                     window.templateFundoImg = imgSrc;
                 }

                 // Sempre usar TinyMCE para preservar formatação
                 inicializarEditorTiny(data.html);
             } else {
                 alert('Erro: ' + data.error);
             }
         })
         .catch(error => {
             console.error('Erro ao carregar template:', error);
             alert('Erro ao carregar template');
         });
     }

     function inicializarEditorTiny(conteudo) {
         if (tinymce.get('editor-parecer-content')) {
             tinymce.remove('#editor-parecer-content');
         }

         // Extrair conteúdo do template A4 se necessário
         let conteudoExtraido = conteudo;
         const parser = new DOMParser();
         const doc = parser.parseFromString(conteudo, 'text/html');
         const conteudoDiv = doc.querySelector('#conteudo');
         if (conteudoDiv) {
             // Se tem div#conteudo, extrair apenas o conteúdo interno
             conteudoExtraido = conteudoDiv.innerHTML;
         } else {
             // Se não tem estrutura específica, usar o HTML completo do body
             const body = doc.querySelector('body');
             if (body) {
                 conteudoExtraido = body.innerHTML;
             }
         }

         tinymce.init({
             selector: '#editor-parecer-content',
             height: 600,
             language: 'pt_BR',
             plugins: 'lists link image table code fullscreen',
             toolbar: 'undo redo | formatselect | bold italic underline | alignleft aligncenter alignright | bullist numlist | link image | fullscreen code',
             content_style: 'body { font-family: "Times New Roman", Times, serif; font-size: 12pt; line-height: 1.45; } p { margin: 0 0 10px; } ul,ol { margin: 0 0 10px 20px; padding-left: 14px; }',
             valid_elements: '*[*]',
             extended_valid_elements: '*[*]',
             valid_styles: {
                 '*': 'color,font-size,font-weight,font-style,text-decoration,text-align,margin,padding,border,width,height'
             },
             setup: function(editor) {
                 editor.on('init', function() {
                     editor.setContent(conteudoExtraido);
                 });
             }
         });
     }

     function gerarPdfFinal() {
         const html = tinymce.get('editor-parecer-content').getContent();
         const template = document.getElementById('template-select').value;

         fetch('parecer_handler.php', {
             method: 'POST',
             headers: {'Content-Type': 'application/json'},
             body: JSON.stringify({
                 action: 'gerar_pdf',
                 html: html,
                 template: template,
                 requerimento_id: <?php echo $id; ?>
             })
         })
         .then(res => res.json())
         .then(data => {
             if (data.success) {
                 alert('Parecer gerado com sucesso!');
                 parecerModal.hide();
                 carregarPareceresExistentes(); // Isso também atualizará a aba de documentos
             } else {
                 alert('Erro ao gerar PDF: ' + data.error);
             }
         })
         .catch(error => {
             console.error('Erro ao gerar PDF:', error);
             alert('Erro ao gerar PDF: ' + error.message);
         });
     }

     function voltarParaSelecao() {
         tinymce.remove('#editor-parecer-content');
         document.getElementById('etapa-editor').style.display = 'none';
         document.getElementById('etapa-selecao-template').style.display = 'block';
     }

     function irParaAssinatura() {
         templateAtual = document.getElementById('template-select').value;

         // Ocultar editor e ir direto para posicionamento com assinatura padrão digitada
         const etapaEditor = document.getElementById('etapa-editor');
         if (etapaEditor) {
             etapaEditor.style.display = 'none';
         }
         if (!dadosAssinatura) {
             definirAssinaturaPadrao();
         }
         prepararEtapaPosicionamento();
     }

     function inicializarSignaturePad() {
         const canvas = document.getElementById('signature-canvas');
         if (!canvas) return;
         signaturePad = new SignaturePad(canvas, {
             backgroundColor: 'rgb(255, 255, 255)',
             penColor: 'rgb(0, 0, 0)',
             minWidth: 1,
             maxWidth: 3
         });

         // Limpar assinatura anterior se existir
         signaturePad.clear();
     }

     function limparAssinatura() {
         if (signaturePad) {
             signaturePad.clear();
         }
     }

     function atualizarPreviewAssinatura() {
         const texto = document.getElementById('signature-text').value || 'Seu Nome Aqui';
         const fonte = document.getElementById('signature-font').value;
         const preview = document.getElementById('signature-preview');
         preview.style.fontFamily = fonte;
         preview.textContent = texto;
     }

     function definirAssinaturaPadrao() {
         dadosAssinatura = {
             assinatura: { texto: '<?php echo $_SESSION['admin_nome']; ?>', fonte: "'Arial', sans-serif" },
             tipo_assinatura: 'texto',
             admin_nome: '<?php echo $_SESSION['admin_nome']; ?>',
             admin_cpf: '<?php echo $_SESSION['admin_cpf'] ?? ''; ?>',
             admin_cargo: '<?php echo $_SESSION['admin_cargo'] ?? 'Administrador'; ?>',
             data_assinatura: new Date().toLocaleString('pt-BR')
         };
         atualizarBadgeAssinatura();
     }

     function abrirConfigAssinatura() {
         if (!dadosAssinatura) {
             definirAssinaturaPadrao();
         }

         const assinaturaAtual = dadosAssinatura.assinatura;
         if (typeof assinaturaAtual === 'object' && assinaturaAtual.texto) {
             document.getElementById('signature-text').value = assinaturaAtual.texto;
             const fonteIndex = fontesDisponiveis.findIndex(f => f.valor === assinaturaAtual.fonte);
             indiceFonteAtual = fonteIndex >= 0 ? fonteIndex : 0;
             document.getElementById('signature-font').value = assinaturaAtual.fonte;
             atualizarPreviewAssinatura();
             document.getElementById('config-digitar-tab').classList.add('active');
             document.getElementById('config-digitar-assinatura').classList.add('show', 'active');
             document.getElementById('config-desenhar-tab').classList.remove('active');
             document.getElementById('config-desenhar-assinatura').classList.remove('show', 'active');
         } else {
             limparAssinatura();
             document.getElementById('config-desenhar-tab').classList.add('active');
             document.getElementById('config-desenhar-assinatura').classList.add('show', 'active');
             document.getElementById('config-digitar-tab').classList.remove('active');
             document.getElementById('config-digitar-assinatura').classList.remove('show', 'active');
         }

         if (configAssinaturaModal) {
             configAssinaturaModal.show();
         }
     }

     function salvarConfigAssinatura() {
         const tabAtiva = document.querySelector('#assinaturaTabsConfig .nav-link.active').id;
         let assinaturaData = null;
         let tipoAssinatura = '';

         if (tabAtiva === 'config-desenhar-tab') {
             if (!signaturePad || signaturePad.isEmpty()) {
                 alert('Por favor, desenhe sua assinatura.');
                 return;
             }
             assinaturaData = signaturePad.toDataURL('image/png');
             tipoAssinatura = 'desenho';
         } else {
             const texto = document.getElementById('signature-text').value.trim() || '<?php echo $_SESSION['admin_nome']; ?>';
             const fonte = document.getElementById('signature-font').value;
             assinaturaData = { texto: texto, fonte: fonte };
             tipoAssinatura = 'texto';
         }

         dadosAssinatura = {
             assinatura: assinaturaData,
             tipo_assinatura: tipoAssinatura,
             admin_nome: '<?php echo $_SESSION['admin_nome']; ?>',
             admin_cpf: '<?php echo $_SESSION['admin_cpf'] ?? ''; ?>',
             admin_cargo: '<?php echo $_SESSION['admin_cargo'] ?? 'Administrador'; ?>',
             data_assinatura: new Date().toLocaleString('pt-BR')
         };

         atualizarBadgeAssinatura();
         atualizarBlocoAssinaturaPreview();
         if (configAssinaturaModal) configAssinaturaModal.hide();
     }

     function atualizarBadgeAssinatura() {
         const badge = document.getElementById('assinatura-status-badge');
         if (!badge || !dadosAssinatura) return;
         if (dadosAssinatura.tipo_assinatura === 'desenho') {
             badge.textContent = 'Assinatura: Desenho';
             badge.className = 'badge bg-primary';
         } else {
             const fonteNome = fontesDisponiveis.find(f => f.valor === (dadosAssinatura.assinatura.fonte || ""))?.nome || 'Arial';
             badge.textContent = `Assinatura: Digitada (${fonteNome})`;
             badge.className = 'badge bg-secondary';
         }
     }

     function atualizarBlocoAssinaturaPreview() {
         const visual = document.getElementById('preview-assinatura-visual');
         if (!visual || !dadosAssinatura) return;

         if (dadosAssinatura.tipo_assinatura === 'desenho' && typeof dadosAssinatura.assinatura === 'string') {
             visual.innerHTML = `<img src="${dadosAssinatura.assinatura}" style="max-width: 140px; height: auto;">`;
         } else if (dadosAssinatura.assinatura && dadosAssinatura.assinatura.texto) {
             visual.textContent = dadosAssinatura.assinatura.texto;
             visual.style.fontFamily = dadosAssinatura.assinatura.fonte || "'Arial', sans-serif";
         } else {
             visual.textContent = '';
         }
     }

     function anteriorFonte() {
         indiceFonteAtual = (indiceFonteAtual - 1 + fontesDisponiveis.length) % fontesDisponiveis.length;
         atualizarFonteAtual();
     }

     function proximaFonte() {
         indiceFonteAtual = (indiceFonteAtual + 1) % fontesDisponiveis.length;
         atualizarFonteAtual();
     }

     function atualizarFonteAtual() {
         const fonteAtual = fontesDisponiveis[indiceFonteAtual];
         document.getElementById('fonte-atual').textContent = fonteAtual.nome;
         document.getElementById('signature-font').value = fonteAtual.valor;
         atualizarPreviewAssinatura();
     }

     function aplicarEspacamentoPreview(previewConteudo) {
         if (!previewConteudo) return;

         previewConteudo.style.whiteSpace = 'pre-wrap';
         previewConteudo.style.lineHeight = '1.45';
         previewConteudo.style.wordBreak = 'break-word';

         previewConteudo.querySelectorAll('p').forEach(p => {
             p.style.marginTop = '0';
             p.style.marginBottom = '10px';
         });
     }

     function habilitarEdicaoPreview(previewConteudo) {
         if (!previewConteudo) return;

         previewConteudo.contentEditable = 'true';
         previewConteudo.setAttribute('role', 'textbox');
         previewConteudo.setAttribute('aria-label', 'Pré-visualização editável do parecer');

         if (handlerPreviewEdicao) {
             previewConteudo.removeEventListener('input', handlerPreviewEdicao);
         }

         handlerPreviewEdicao = () => {
             const editor = tinymce.get('editor-parecer-content');
             if (editor) {
                 editor.setContent(previewConteudo.innerHTML);
             }

             const previewDoc = document.getElementById('preview-documento');
             if (previewDoc) {
                 atualizarStatusPaginacao(previewConteudo, previewDoc);
             }
         };

         previewConteudo.addEventListener('input', handlerPreviewEdicao);
     }

     function voltarParaEditor() {
         document.getElementById('etapa-posicionamento').style.display = 'none';
         document.getElementById('etapa-editor').style.display = 'block';
     }

     function prepararEtapaPosicionamento() {
         if (!dadosAssinatura) {
             definirAssinaturaPadrao();
         }

         const editor = tinymce.get('editor-parecer-content');
         let html = '';

         if (editor) {
             html = editor.getContent();
         } else {
             alert('Erro: Editor TinyMCE não encontrado. Por favor, recarregue a página.');
             return;
         }

         const previewDoc = document.getElementById('preview-documento');
         const previewFundo = document.getElementById('preview-fundo');
         const previewConteudo = document.getElementById('preview-conteudo');
         const blocoAssinatura = document.getElementById('bloco-assinatura-arrastavel');
         if (!previewDoc || !previewFundo || !previewConteudo || !blocoAssinatura) return;

         // Carregar imagem de fundo do template original (armazenada quando carregou o template)
         if (window.templateFundoImg) {
             previewFundo.src = window.templateFundoImg;
         } else {
             // Tentar extrair do HTML se não tiver armazenado
             const parser = new DOMParser();
             const doc = parser.parseFromString(html, 'text/html');
             const imgFundo = doc.querySelector('#fundo-imagem') || doc.querySelector('#documento #fundo-imagem');
             if (imgFundo && imgFundo.src) {
                 let imgSrc = imgFundo.src;
                 // Converter caminho relativo para absoluto se necessário
                 if (imgSrc && !imgSrc.startsWith('http') && !imgSrc.startsWith('data:')) {
                     if (imgSrc.startsWith('images/')) {
                         imgSrc = '../assets/doc/' + imgSrc;
                     }
                 }
                 previewFundo.src = imgSrc;
             } else {
                 // Fallback: usar caminho padrão da imagem de fundo
                 const template = document.getElementById('template-select').value;
                 if (template && (template.includes('template_oficial_a4') || template.includes('licenca_previa_projeto') || template.includes('parecer_tecnico'))) {
                     previewFundo.src = '../assets/doc/images/image1.png';
                 }
             }
         }

         // Extrair conteúdo se existir div#conteudo, senão usar HTML completo
         const parser = new DOMParser();
         const doc = parser.parseFromString(html, 'text/html');
         const conteudoDiv = doc.querySelector('#conteudo');
         if (conteudoDiv) {
             previewConteudo.innerHTML = conteudoDiv.innerHTML;
         } else {
             previewConteudo.innerHTML = html;
         }

         document.getElementById('preview-nome-assinante').textContent = dadosAssinatura.admin_nome;
         document.getElementById('preview-cargo-assinante').textContent = dadosAssinatura.admin_cargo;
         atualizarBlocoAssinaturaPreview();
         atualizarBadgeAssinatura();

         // Gerar QR code temporário para preview (será substituído pelo real no backend)
         const previewQr = document.getElementById('preview-qr-code');
         if (previewQr) {
             // Criar URL temporária de verificação para preview
             const protocolo = window.location.protocol;
             const host = window.location.host;
             const basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/admin'));
             const urlVerificacaoPreview = protocolo + '//' + host + basePath + '/consultar/verificar.php?id=preview';

             // Usar biblioteca QRCode se disponível, senão usar placeholder
             if (typeof QRCode !== 'undefined') {
                 QRCode.toDataURL(urlVerificacaoPreview, {
                     width: 80,
                     height: 80,
                     margin: 1
                 }, function (err, url) {
                     if (!err && previewQr) {
                         previewQr.src = url;
                     } else {
                         previewQr.src = 'data:image/svg+xml;base64,' + btoa('<svg width="80" height="80" xmlns="http://www.w3.org/2000/svg"><rect width="80" height="80" fill="#f0f0f0"/><text x="40" y="40" text-anchor="middle" font-size="10">QR Code</text></svg>');
                     }
                 });
             } else {
                 previewQr.src = 'data:image/svg+xml;base64,' + btoa('<svg width="80" height="80" xmlns="http://www.w3.org/2000/svg"><rect width="80" height="80" fill="#f0f0f0"/><text x="40" y="40" text-anchor="middle" font-size="10">QR Code</text></svg>');
             }
         }

         setTimeout(() => {
             const previewDocRect = previewDoc.getBoundingClientRect();
             const blocoWidth = 200;
             const blocoHeight = 100;
             const centroX = previewDocRect.width - blocoWidth - 40 + (blocoWidth / 2);
             const centroY = previewDocRect.height - blocoHeight - 40 + (blocoHeight / 2);

            coordenadasAssinatura.x = centroX / previewDocRect.width;
            coordenadasAssinatura.y = centroY / previewDocRect.height;

            blocoAssinatura.style.left = (centroX - blocoWidth / 2) + 'px';
            blocoAssinatura.style.top = (centroY - blocoHeight / 2) + 'px';

            atualizarStatusPaginacao(previewConteudo, previewDoc);
        }, 100);

         document.getElementById('etapa-posicionamento').style.display = 'block';

        setTimeout(() => {
            inicializarDragAndDrop();
            habilitarEdicaoPreview(previewConteudo);
        }, 200);
    }

    function atualizarStatusPaginacao(previewConteudo, previewDoc) {
        const statusPreview = document.getElementById('parecer-preview-status');
        const statusPreviewTexto = document.getElementById('parecer-preview-status-text');

        if (!statusPreview || !statusPreviewTexto || !previewConteudo || !previewDoc) return;

        const conteudoStyle = window.getComputedStyle(previewConteudo);
        const top = parseFloat(conteudoStyle.top) || 0;
        const margemInferior = 60;
        const areaUtil = previewDoc.clientHeight - top - margemInferior;
        const paginas = Math.max(1, Math.ceil(previewConteudo.scrollHeight / areaUtil));

        statusPreview.style.display = 'block';
        statusPreviewTexto.textContent = paginas > 1
            ? `O conteúdo atual ocupa aproximadamente ${paginas} páginas A4. Ajuste o texto ou template caso queira manter em menos páginas.`
            : 'O conteúdo cabe dentro de uma página A4.';
    }

     function inicializarDragAndDrop() {
         const blocoAssinatura = document.getElementById('bloco-assinatura-arrastavel');
         const previewDoc = document.getElementById('preview-documento');
         if (!blocoAssinatura || !previewDoc) return;

         let isDragging = false;
         let currentX = 0;
         let currentY = 0;
         let initialX = 0;
         let initialY = 0;

         blocoAssinatura.addEventListener('mousedown', function(e) {
             if (e.target.tagName === 'IMG' || e.target.closest('.dados-assinante')) {
                 return;
             }
             e.preventDefault();
             isDragging = true;
             const rect = blocoAssinatura.getBoundingClientRect();
             const previewRect = previewDoc.getBoundingClientRect();
             initialX = e.clientX - rect.left;
             initialY = e.clientY - rect.top;
             blocoAssinatura.style.cursor = 'grabbing';
         });

         document.addEventListener('mousemove', function(e) {
             if (!isDragging) return;
             e.preventDefault();

             const previewRect = previewDoc.getBoundingClientRect();
             const blocoRect = blocoAssinatura.getBoundingClientRect();

             currentX = e.clientX - previewRect.left - initialX;
             currentY = e.clientY - previewRect.top - initialY;

             const maxX = previewRect.width - blocoRect.width;
             const maxY = previewRect.height - blocoRect.height;

             currentX = Math.max(0, Math.min(currentX, maxX));
             currentY = Math.max(0, Math.min(currentY, maxY));

             blocoAssinatura.style.left = currentX + 'px';
             blocoAssinatura.style.top = currentY + 'px';
         });

         document.addEventListener('mouseup', function() {
            if (isDragging) {
                isDragging = false;
                blocoAssinatura.style.cursor = 'move';

                const previewRect = previewDoc.getBoundingClientRect();
                const blocoRect = blocoAssinatura.getBoundingClientRect();

                const centroX = blocoRect.left - previewRect.left + (blocoRect.width / 2);
                const centroY = blocoRect.top - previewRect.top + (blocoRect.height / 2);

                coordenadasAssinatura.x = centroX / previewRect.width;
                coordenadasAssinatura.y = centroY / previewRect.height;
            }
        });

         blocoAssinatura.addEventListener('dragstart', function(e) {
             e.preventDefault();
         });
     }

     function posicionarAssinaturaRapido(posicao) {
         const previewDoc = document.getElementById('preview-documento');
         const blocoAssinatura = document.getElementById('bloco-assinatura-arrastavel');
         if (!previewDoc || !blocoAssinatura) return;

         const previewRect = previewDoc.getBoundingClientRect();
         const blocoRect = blocoAssinatura.getBoundingClientRect();
         let centroX;
         const margem = 40;
         const baseY = previewRect.height - blocoRect.height - margem + (blocoRect.height / 2);

         if (posicao === 'esquerda') {
             centroX = margem + (blocoRect.width / 2);
         } else if (posicao === 'centro') {
             centroX = previewRect.width / 2;
         } else {
             centroX = previewRect.width - margem - (blocoRect.width / 2);
         }

         coordenadasAssinatura.x = centroX / previewRect.width;
         coordenadasAssinatura.y = baseY / previewRect.height;

         blocoAssinatura.style.left = (centroX - blocoRect.width / 2) + 'px';
         blocoAssinatura.style.top = (baseY - blocoRect.height / 2) + 'px';
     }

     function resetarPosicaoAssinatura() {
         const previewDoc = document.getElementById('preview-documento');
         const blocoAssinatura = document.getElementById('bloco-assinatura-arrastavel');
         if (!previewDoc || !blocoAssinatura) return;
         const previewRect = previewDoc.getBoundingClientRect();
         const blocoWidth = 200;
         const blocoHeight = 100;
         const centroX = previewRect.width - blocoWidth - 40 + (blocoWidth / 2);
         const centroY = previewRect.height - blocoHeight - 40 + (blocoHeight / 2);
         coordenadasAssinatura.x = centroX / previewRect.width;
         coordenadasAssinatura.y = centroY / previewRect.height;
         blocoAssinatura.style.left = (centroX - blocoWidth / 2) + 'px';
         blocoAssinatura.style.top = (centroY - blocoHeight / 2) + 'px';
     }

     // Variáveis globais para o modal de verificação e persistência da senha
     let modalVerificacao = null;
     let senhaTemporaria = '';

     async function verificarSessaoAssinatura() {
        try {
            const response = await fetch('parecer_handler.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'verificar_sessao_assinatura' })
            });
            const data = await response.json();
            return data.success && data.sessao_valida;
        } catch (error) {
            console.error('Erro ao verificar sessão:', error);
            return false;
        }
     }

     function iniciarVerificacaoEmail() {
         // Salvar a senha digitada antes de fechar o modal
         const senhaInput = document.getElementById('senha-finalizacao');
         if (senhaInput) {
             senhaTemporaria = senhaInput.value;
         }

         // Fechar modal de parecer se estiver aberto para limpar a tela
         if (typeof parecerModal !== 'undefined' && parecerModal) {
             parecerModal.hide();
         }
         
         if (!modalVerificacao) {
             modalVerificacao = new bootstrap.Modal(document.getElementById('modalVerificacaoSeguranca'));
         }
         
         // Resetar estado
         document.getElementById('etapa-enviar-codigo').style.display = 'block';
         document.getElementById('etapa-validar-codigo').style.display = 'none';
         document.getElementById('codigo_verificacao').value = '';
         document.getElementById('codigo_verificacao').classList.remove('is-invalid');
         
         modalVerificacao.show();
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
                 body: JSON.stringify({ action: 'enviar_codigo_assinatura' })
             });
             const data = await response.json();
             
             if (data.success) {
                 document.getElementById('email-mascarado-display').textContent = data.email_mascarado;
                 document.getElementById('etapa-enviar-codigo').style.display = 'none';
                 document.getElementById('etapa-validar-codigo').style.display = 'block';
             } else {
                 showToast(data.error || 'Falha ao enviar email.', 'error');
             }
         } catch (error) {
             console.error('Erro:', error);
             showToast('Erro de conexão ao enviar código.', 'error');
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
                     codigo: codigo
                 })
             });
             const data = await response.json();
             
             if (data.success) {
                 // Sucesso! Fechar modal e mostrar notificação moderna
                 if (modalVerificacao) modalVerificacao.hide();
                 showToast('Verificação realizada com sucesso! Acesso liberado por 3 horas.');
                 
                 // CONTINUAR FLUXO: Abrir o modal de parecer
                 setTimeout(() => {
                    exibirModalParecer();
                 }, 500);
                 
             } else {
                 input.classList.add('is-invalid');
                 document.getElementById('erro-codigo').textContent = data.error || 'Código inválido.';
             }
         } catch (error) {
             console.error('Erro:', error);
             showToast('Erro de conexão ao validar código.', 'error');
         } finally {
             btn.disabled = false;
             btn.innerHTML = originalText;
         }
     }


     async function confirmarPosicaoEGerarPdf() {
         // O fluxo agora garante sessão válida no INÍCIO.
         // Mantemos apenas uma verificação silenciosa para logging/segurança redundante
         // sem interromper a UI bruscamente.

         const editor = tinymce.get('editor-parecer-content');
         let html = '';


         if (editor) {
             html = editor.getContent();
         } else {
             alert('Erro: Editor TinyMCE não encontrado. Por favor, recarregue a página.');
             return;
         }

         const senha = document.getElementById('senha-finalizacao').value;
         const erroSenhaEl = document.getElementById('erro-senha-finalizacao');
         if (erroSenhaEl) erroSenhaEl.style.display = 'none';
         if (!dadosAssinatura) {
             definirAssinaturaPadrao();
         }
         if (!senha) {
             if (erroSenhaEl) {
                 erroSenhaEl.textContent = 'Informe sua senha para finalizar a assinatura.';
                 erroSenhaEl.style.display = 'block';
             }
             return;
         }

         try {
             const validacaoResponse = await fetch('parecer_handler.php', {
                 method: 'POST',
                 headers: {'Content-Type': 'application/json'},
                 body: JSON.stringify({
                     action: 'validar_senha',
                     senha: senha
                 })
             });
             const validacaoData = await validacaoResponse.json();
             if (!validacaoData.success) {
                 if (erroSenhaEl) {
                     erroSenhaEl.textContent = 'Senha incorreta. Tente novamente.';
                     erroSenhaEl.style.display = 'block';
                 }
                 return;
             }

             dadosAssinatura.data_assinatura = new Date().toLocaleString('pt-BR');

             const response = await fetch('parecer_handler.php', {
                 method: 'POST',
                 headers: {'Content-Type': 'application/json'},
                 body: JSON.stringify({
                     action: 'gerar_pdf_com_assinatura_posicionada',
                     html: html,
                     template: templateAtual,
                     requerimento_id: <?php echo $id; ?>,
                     assinatura: dadosAssinatura.assinatura,
                     tipo_assinatura: dadosAssinatura.tipo_assinatura,
                     admin_nome: dadosAssinatura.admin_nome,
                     admin_cpf: dadosAssinatura.admin_cpf,
                     admin_cargo: dadosAssinatura.admin_cargo,
                     data_assinatura: dadosAssinatura.data_assinatura,
                     posicao_x: coordenadasAssinatura.x,
                     posicao_y: coordenadasAssinatura.y
                 })
             });

             const data = await response.json();

             if (data.success) {
                 // Configurar e mostrar modal de sucesso
                 const btnVisualizar = document.getElementById('btn-visualizar-sucesso');
                 if (data.url_viewer) {
                     btnVisualizar.onclick = function() { window.open(data.url_viewer, '_blank'); };
                     btnVisualizar.style.display = 'inline-block';
                 } else {
                     btnVisualizar.style.display = 'none';
                 }
                 
                 // Limpar senha temporária
                 senhaTemporaria = '';
                 
                 const modalSucesso = new bootstrap.Modal(document.getElementById('modalSucessoAssinatura'));
                 modalSucesso.show();

                 // Limpar interface
                 parecerModal.hide();
                 carregarPareceresExistentes();

                 if (signaturePad) signaturePad.clear();
                 document.getElementById('signature-text').value = '';
                 const senhaFinalizacao = document.getElementById('senha-finalizacao');
                 if (senhaFinalizacao) senhaFinalizacao.value = '';
                 if (erroSenhaEl) erroSenhaEl.style.display = 'none';

                 document.getElementById('etapa-posicionamento').style.display = 'none';
                 document.getElementById('etapa-selecao-template').style.display = 'block';
                 tinymce.remove('#editor-parecer-content');

                 dadosAssinatura = null;
                 coordenadasAssinatura = { x: 0, y: 0 };
                 templateAtual = null;
             } else {
                 // VERIFICA SE É ERRO DE SESSÃO
                 if (data.code === 'SESSION_EXPIRED') {
                     showToast('Sua sessão de assinatura expirou. Realize a verificação novamente.', 'warning');
                     iniciarVerificacaoEmail();
                 } else {
                     showToast('Erro ao gerar PDF: ' + data.error, 'error');
                 }
             }
         } catch (error) {
             console.error('Erro:', error);
             showToast('Erro ao gerar PDF: ' + (error.message || 'Erro desconhecido'), 'error');
         }
     }

     function carregarPareceresExistentes() {
         fetch('parecer_handler.php', {
             method: 'POST',
             headers: {'Content-Type': 'application/json'},
             body: JSON.stringify({
                 action: 'listar_pareceres',
                 requerimento_id: <?php echo $id; ?>
             })
         })
         .then(res => res.json())
         .then(data => {
             const lista = document.getElementById('pareceres-existentes-list');
             if (data.pareceres.length === 0) {
                 lista.innerHTML = '<p class="text-muted small">Nenhum parecer gerado ainda.</p>';
             } else {
                 lista.innerHTML = '';
                 data.pareceres.forEach(p => {
                     const viewerUrl = p.documento_id ? `parecer_viewer.php?id=${p.documento_id}` : `../uploads/pareceres/<?php echo $id; ?>/${p.arquivo}`;
                    const { iconClass, iconColor } = obterIconeParecer(p.tipo);
                    const nomeLimpo = formatarNomeParecer(p.nome);
                    const seloTipo = gerarSeloTipoParecer(p.tipo);

                     lista.innerHTML += `
                        <div class="data-row">
                            <div class="data-label" style="min-width: 40px;">
                                <i class="fas ${iconClass}" style="color: ${iconColor}; font-size: 20px;"></i>
                            </div>
                            <div class="data-value">
                                <div class="fw-semibold d-flex align-items-center gap-2 flex-wrap">
                                    <span>${nomeLimpo}</span>
                                    ${seloTipo}
                                </div>
                                <div class="text-muted small">${p.data} • ${formatarTamanhoArquivo(p.tamanho)}</div>
                            </div>
                            <div class="data-actions">
                                <a href="${viewerUrl}"
                                   class="copy-btn me-1"
                                   target="_blank"
                                   title="Visualizar parecer">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button onclick="excluirParecer('${p.arquivo}')" class="copy-btn" title="Excluir parecer" style="color: #dc2626;">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                     `;
                 });
             }

             // Também carregar na aba de documentos
             carregarPareceresDocumentos(data.pareceres);
         })
         .catch(error => {
             console.error('Erro ao carregar pareceres:', error);
         });
     }

     function carregarPareceresDocumentos(pareceres) {
         const listaDocumentos = document.getElementById('pareceres-documentos-list');
         const pareceresSection = document.getElementById('pareceres-section');

         if (pareceres.length === 0) {
             pareceresSection.style.display = 'none';
         } else {
             pareceresSection.style.display = 'block';
             let html = '';
             pareceres.forEach(p => {
                 const viewerUrl = p.documento_id ? `parecer_viewer.php?id=${p.documento_id}` : `../uploads/pareceres/<?php echo $id; ?>/${p.arquivo}`;
                const { iconClass, iconColor } = obterIconeParecer(p.tipo);
                const nomeLimpo = formatarNomeParecer(p.nome);
                const seloTipo = gerarSeloTipoParecer(p.tipo);

                 html += `
                     <div class="data-row">
                         <div class="data-label" style="min-width: 40px;">
                             <i class="fas ${iconClass}" style="color: ${iconColor}; font-size: 20px;"></i>
                         </div>
                         <div class="data-value">
                            <div class="fw-semibold d-flex align-items-center gap-2 flex-wrap">
                                <span>${nomeLimpo}</span>
                                ${seloTipo}
                            </div>
                            <div class="text-muted small">${p.data} • ${formatarTamanhoArquivo(p.tamanho)}</div>
                         </div>
                         <div class="data-actions">
                             <a href="${viewerUrl}"
                                class="copy-btn me-1"
                                target="_blank"
                                title="Visualizar parecer">
                                 <i class="fas fa-eye"></i>
                             </a>
                             <a href="parecer_handler.php?action=download_parecer&arquivo=${p.arquivo}&requerimento_id=<?php echo $id; ?>"
                                class="copy-btn me-1"
                                title="Baixar parecer">
                                 <i class="fas fa-download"></i>
                             </a>
                             <button onclick="excluirParecer('${p.arquivo}')" class="copy-btn" title="Excluir parecer" style="color: #dc2626;">
                                 <i class="fas fa-trash"></i>
                             </button>
                         </div>
                     </div>
                 `;
             });
             listaDocumentos.innerHTML = html;
         }
     }

     function formatarTamanhoArquivo(bytes) {
         if (bytes === 0) return '0 Bytes';
         const k = 1024;
         const sizes = ['Bytes', 'KB', 'MB', 'GB'];
         const i = Math.floor(Math.log(bytes) / Math.log(k));
         return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
     }

     function formatarNomeParecer(nomeArquivo) {
         if (!nomeArquivo) return 'Parecer técnico';

         const semExtensao = nomeArquivo.replace(/\.[^.]+$/, '');
         let legivel = semExtensao
             .replace(/^parecer[_-]?/i, '')
             .replace(/^assinatura[_-]?/i, '')
             .replace(/[_-]?\d{8,}$/i, '')
             .replace(/[_-]+/g, ' ')
             .trim();

         if (!legivel) {
             legivel = semExtensao;
         }

         return legivel
             .split(' ')
             .map(p => p.charAt(0).toUpperCase() + p.slice(1))
             .join(' ');
     }

     function obterIconeParecer(tipo) {
         if (tipo === 'pdf') {
             return { iconClass: 'fa-file-pdf', iconColor: '#dc2626' };
         }
         return { iconClass: 'fa-file-signature', iconColor: '#0ea5e9' };
     }

     function gerarSeloTipoParecer(tipo) {
         const label = tipo === 'pdf' ? 'PDF assinado' : 'Digital';
         const cor = tipo === 'pdf' ? '#fef2f2' : '#e0f2fe';
         const texto = tipo === 'pdf' ? '#b91c1c' : '#0ea5e9';
         return `<span class="badge rounded-pill" style="background:${cor}; color:${texto}; font-size: 11px;">${label}</span>`;
     }

     function excluirParecer(arquivo) {
         if (!confirm('Deseja excluir este parecer?')) return;

         fetch('parecer_handler.php', {
             method: 'POST',
             headers: {'Content-Type': 'application/json'},
             body: JSON.stringify({
                 action: 'excluir_parecer',
                 arquivo: arquivo,
                 requerimento_id: <?php echo $id; ?>
             })
         })
         .then(res => res.json())
         .then(data => {
             if (data.success) {
                 carregarPareceresExistentes(); // Isso também atualizará a aba de documentos
             } else {
                 alert('Erro ao excluir: ' + data.error);
             }
         })
         .catch(error => {
             console.error('Erro ao excluir parecer:', error);
             alert('Erro ao excluir parecer');
         });
     }

     function resetarFluxoParecer(resetTemplate = false) {
         const etapaSelecao = document.getElementById('etapa-selecao-template');
         const etapaEditor = document.getElementById('etapa-editor');
         const etapaPosicionamento = document.getElementById('etapa-posicionamento');

         if (etapaSelecao) etapaSelecao.style.display = 'block';
         if (etapaEditor) etapaEditor.style.display = 'none';
         if (etapaPosicionamento) etapaPosicionamento.style.display = 'none';

         if (resetTemplate) {
             const select = document.getElementById('template-select');
             if (select) select.value = '';
         }

         if (tinymce.get('editor-parecer-content')) {
             tinymce.remove('#editor-parecer-content');
         }

         dadosAssinatura = null;
         coordenadasAssinatura = { x: 0, y: 0 };
         templateAtual = null;

         const senhaFinalizacao = document.getElementById('senha-finalizacao');
         if (senhaFinalizacao) senhaFinalizacao.value = '';
         const erroSenhaEl = document.getElementById('erro-senha-finalizacao');
         if (erroSenhaEl) erroSenhaEl.style.display = 'none';

         if (configAssinaturaModal) {
             configAssinaturaModal.hide();
         }
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
        case 'indeferido':
            return '#dc2626'; // vermelho forte
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

<!-- Modal de Verificação de Segurança -->
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
                
                <!-- Etapa 1: Enviar Email -->
                <div id="etapa-enviar-codigo">
                    <button onclick="enviarCodigoVerificacao()" class="btn btn-primary w-100 py-2 mb-3 d-flex align-items-center justify-content-center gap-2">
                        <i class="fas fa-paper-plane"></i>
                        Enviar código para meu email
                    </button>
                    <p class="small text-muted mb-0">
                        Um código de 6 dígitos será enviado para seu email cadastrado.
                    </p>
                </div>
                
                <!-- Etapa 2: Digitar Código -->
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

<!-- Modal de Sucesso -->
<div class="modal fade" id="modalSucessoAssinatura" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-body text-center px-4 py-5">
                <div class="mb-4">
                    <div class="rounded-circle bg-success d-inline-flex align-items-center justify-content-center" style="width: 90px; height: 90px; box-shadow: 0 0 0 10px rgba(25, 135, 84, 0.1);">
                        <i class="fas fa-check text-white" style="font-size: 40px;"></i>
                    </div>
                </div>
                
                <h3 class="fw-bold text-success mb-2">Sucesso!</h3>
                <h5 class="fw-bold mb-3">Parecer Assinado Digitalmente</h5>
                
                <p class="text-muted mb-4">
                    O documento foi gerado, assinado e registrado com sucesso.
                    <br>O protocolo de autenticidade já está ativo.
                </p>
                
                <div class="d-grid gap-2 col-8 mx-auto">
                    <button type="button" class="btn btn-success btn-lg" data-bs-dismiss="modal">
                        <i class="fas fa-thumbs-up me-2"></i> Entendido
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="btn-visualizar-sucesso">
                        <i class="fas fa-external-link-alt me-2"></i> Visualizar Documento
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1090;">
    <div id="liveToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body d-flex align-items-center gap-2">
                <i class="fas fa-check-circle fa-lg"></i>
                <span id="toastMessage">Operação realizada com sucesso!</span>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<script>
    // Variável global para o toast
    let toastInstance = null;

    function showToast(message, type = 'success') {
        const toastEl = document.getElementById('liveToast');
        const toastBody = document.querySelector('#liveToast .toast-body span');
        const toastDiv = document.getElementById('liveToast');
        
        // Inicializar o toast apenas quando necessário (garante que o Bootstrap já carregou)
        if (!toastInstance && typeof bootstrap !== 'undefined') {
            toastInstance = new bootstrap.Toast(toastEl, { delay: 5000 });
        }
        
        toastBody.textContent = message;
        
        // Reset classes
        toastDiv.classList.remove('bg-success', 'bg-danger', 'bg-warning', 'bg-info');
        
        // Add new class based on type
        switch(type) {
            case 'success': toastDiv.classList.add('bg-success'); break;
            case 'error': toastDiv.classList.add('bg-danger'); break;
            case 'warning': toastDiv.classList.add('bg-warning'); break;
            case 'info': toastDiv.classList.add('bg-info'); break;
            default: toastDiv.classList.add('bg-primary');
        }
        
        if (toastInstance) {
            toastInstance.show();
        } else {
            // Fallback caso o Bootstrap falhe ao carregar
            alert(message);
        }
    }
</script>

<?php include 'footer.php'; ?>



