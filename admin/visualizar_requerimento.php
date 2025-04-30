<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/models.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['admin_id'])) {
    setMensagem('erro', 'Você precisa estar logado para acessar esta área.');
    redirect('index.php');
}

// Inicializar variáveis
$mensagem = getMensagem();
$adminId = $_SESSION['admin_id'];
$adminNome = $_SESSION['admin_nome'];
$adminNivel = $_SESSION['admin_nivel'];

// Verificar se foi fornecido um ID válido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setMensagem('erro', 'ID do requerimento inválido.');
    redirect('requerimentos.php');
}

$requerimentoId = (int)$_GET['id'];

// Buscar dados do requerimento
$requerimentoModel = new Requerimento();
$requerimento = $requerimentoModel->buscarPorId($requerimentoId);

if (!$requerimento) {
    setMensagem('erro', 'Requerimento não encontrado.');
    redirect('requerimentos.php');
}

// Buscar dados do requerente
$requerenteModel = new Requerente();
$requerente = $requerenteModel->buscarPorId($requerimento['requerente_id']);

// Buscar dados do proprietário (se existir)
$proprietario = null;
if (!empty($requerimento['proprietario_id'])) {
    $proprietarioModel = new Proprietario();
    $proprietario = $proprietarioModel->buscarPorId($requerimento['proprietario_id']);
}

// Buscar documentos do requerimento
$documentoModel = new Documento();
$documentos = $documentoModel->buscarPorRequerimento($requerimentoId);

// Buscar histórico de ações
$historicoModel = new HistoricoAcao();
$acoes = $historicoModel->buscarPorRequerimento($requerimentoId);

// Processar formulário de atualização de status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_status'])) {
    $novoStatus = $_POST['status'] ?? '';
    $observacoes = $_POST['observacoes'] ?? '';

    if (empty($novoStatus)) {
        setMensagem('erro', 'Por favor, selecione um status válido.');
    } else {
        // Atualizar status
        $requerimentoModel->atualizarStatus($requerimentoId, $novoStatus, $observacoes);

        // Registrar ação no histórico
        $historicoModel->registrar([
            'admin_id' => $adminId,
            'requerimento_id' => $requerimentoId,
            'acao' => "Alterou o status para '{$novoStatus}'" . (empty($observacoes) ? "" : " com observações")
        ]);

        setMensagem('sucesso', 'Status do requerimento atualizado com sucesso.');
        redirect("visualizar_requerimento.php?id={$requerimentoId}");
    }

    $mensagem = getMensagem();
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizar Requerimento - SEMA</title>
    <link rel="icon" href="../assets/prefeitura-logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #009851;
            --primary-dark: #007840;
            --secondary-color: #333;
            --light-color: #f8f9fa;
            --border-color: #e0e0e0;
            --danger-color: #dc3545;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --dark-color: #343a40;
            --gray-color: #6c757d;
            --sidebar-width: 250px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f5f5;
            min-height: 100vh;
            display: flex;
        }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--primary-color);
            color: white;
            min-height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            z-index: 100;
            transition: all 0.3s;
        }

        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .sidebar-logo img {
            width: 60px;
            height: auto;
            margin-bottom: 10px;
        }

        .sidebar-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .sidebar-subtitle {
            font-size: 12px;
            opacity: 0.8;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .menu-item {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            color: white;
            text-decoration: none;
            transition: background-color 0.3s;
        }

        .menu-item:hover,
        .menu-item.active {
            background-color: var(--primary-dark);
        }

        .menu-item i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .sidebar-footer {
            padding: 15px 20px;
            position: absolute;
            bottom: 0;
            width: 100%;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        .user-info {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            font-weight: bold;
        }

        .user-details {
            flex: 1;
        }

        .user-name {
            font-size: 14px;
            font-weight: bold;
        }

        .user-role {
            font-size: 12px;
            opacity: 0.8;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            color: white;
            text-decoration: none;
            padding: 8px 10px;
            border-radius: 4px;
            background-color: rgba(255, 255, 255, 0.1);
            transition: background-color 0.3s;
        }

        .logout-btn:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }

        .logout-btn i {
            margin-right: 8px;
        }

        /* Content Area */
        .content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 20px;
            transition: all 0.3s;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .page-title {
            font-size: 24px;
            color: var(--secondary-color);
        }

        .header-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.3s, color 0.3s;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
        }

        .btn-secondary {
            background-color: var(--secondary-color);
            color: white;
        }

        .btn-secondary:hover {
            background-color: #212529;
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background-color: #c82333;
        }

        /* Alert Messages */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-weight: 500;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }

        /* Detalhes do Requerimento */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 20px;
        }

        @media (max-width: 992px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .card-header {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #f8f9fa;
        }

        .card-title {
            font-size: 18px;
            color: var(--secondary-color);
            font-weight: 600;
            margin: 0;
        }

        .card-body {
            padding: 20px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 15px;
        }

        .info-item {
            margin-bottom: 15px;
        }

        .info-item label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--gray-color);
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .info-value {
            font-size: 15px;
            color: var(--dark-color);
        }

        .text-block {
            margin-bottom: 15px;
        }

        .text-block label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--gray-color);
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .text-block-content {
            font-size: 15px;
            color: var(--dark-color);
            line-height: 1.6;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 4px;
            min-height: 80px;
        }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
            text-align: center;
            min-width: 90px;
        }

        .status-pendente {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-analise {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .status-aprovado {
            background-color: #d4edda;
            color: #155724;
        }

        .status-rejeitado {
            background-color: #f8d7da;
            color: #721c24;
        }

        /* Documentos */
        .doc-list {
            list-style: none;
            padding: 0;
        }

        .doc-item {
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            transition: background-color 0.3s;
        }

        .doc-item:last-child {
            border-bottom: none;
        }

        .doc-item:hover {
            background-color: #f8f9fa;
        }

        .doc-icon {
            color: var(--primary-color);
            font-size: 20px;
            margin-right: 10px;
            width: 30px;
            text-align: center;
        }

        .doc-details {
            flex: 1;
        }

        .doc-name {
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 3px;
        }

        .doc-meta {
            font-size: 12px;
            color: var(--gray-color);
        }

        .doc-action {
            margin-left: 10px;
        }

        .doc-action a {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 14px;
        }

        .doc-action a:hover {
            text-decoration: underline;
        }

        /* Formulário de Status */
        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: var(--secondary-color);
            margin-bottom: 5px;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(0, 152, 81, 0.2);
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        /* Histórico de Ações */
        .timeline {
            list-style: none;
            padding: 20px 0 0 0;
            position: relative;
        }

        .timeline::before {
            content: ' ';
            display: inline-block;
            position: absolute;
            left: 25px;
            top: 0;
            width: 2px;
            height: 100%;
            z-index: 1;
            background-color: #e0e0e0;
        }

        .timeline-item {
            position: relative;
            margin-bottom: 25px;
            padding-left: 60px;
        }

        .timeline-badge {
            position: absolute;
            left: 0;
            top: 0;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            text-align: center;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
        }

        .badge-primary {
            background-color: var(--primary-color);
        }

        .badge-info {
            background-color: var(--info-color);
        }

        .badge-success {
            background-color: var(--success-color);
        }

        .badge-warning {
            background-color: var(--warning-color);
        }

        .badge-danger {
            background-color: var(--danger-color);
        }

        .timeline-content {
            background-color: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .timeline-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }

        .timeline-title {
            font-weight: 600;
            font-size: 16px;
            color: var(--dark-color);
        }

        .timeline-date {
            font-size: 12px;
            color: var(--gray-color);
        }

        .timeline-body {
            font-size: 14px;
            color: var(--secondary-color);
            margin-bottom: 0;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                overflow: hidden;
            }

            .content {
                margin-left: 0;
            }

            .menu-toggle {
                display: block;
            }

            .sidebar.active {
                width: var(--sidebar-width);
            }
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <img src="../assets/prefeitura-logo.png" alt="SEMA">
            </div>
            <div class="sidebar-title">Secretaria de Meio Ambiente</div>
            <div class="sidebar-subtitle">Painel Administrativo</div>
        </div>

        <div class="sidebar-menu">
            <a href="dashboard.php" class="menu-item">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="requerimentos.php" class="menu-item">
                <i class="fas fa-file-alt"></i> Requerimentos
            </a>
            <a href="estatisticas.php" class="menu-item">
                <i class="fas fa-chart-bar"></i> Estatísticas
            </a>
            <?php if ($adminNivel == 'admin'): ?>
                <a href="usuarios.php" class="menu-item">
                    <i class="fas fa-users"></i> Usuários
                </a>
                <a href="configuracoes.php" class="menu-item">
                    <i class="fas fa-cog"></i> Configurações
                </a>
            <?php endif; ?>
        </div>

        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($adminNome, 0, 1)); ?>
                </div>
                <div class="user-details">
                    <div class="user-name"><?php echo sanitize($adminNome); ?></div>
                    <div class="user-role"><?php echo $adminNivel == 'admin' ? 'Administrador' : 'Operador'; ?></div>
                </div>
            </div>
            <a href="dashboard.php?logout=1" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Sair
            </a>
        </div>
    </div>

    <!-- Content Area -->
    <div class="content">
        <div class="page-header">
            <h1 class="page-title">Requerimento #<?php echo sanitize($requerimento['protocolo']); ?></h1>
            <div class="header-actions">
                <a href="editar_requerimento.php?id=<?php echo $requerimentoId; ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Editar
                </a>
                <a href="requerimentos.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
            </div>
        </div>

        <?php if ($mensagem): ?>
            <div class="alert alert-<?php echo $mensagem['tipo']; ?>">
                <?php echo $mensagem['texto']; ?>
            </div>
        <?php endif; ?>

        <div class="content-grid">
            <div class="main-content">
                <!-- Informações Gerais -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Informações Gerais</h2>
                    </div>
                    <div class="card-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <label>Protocolo</label>
                                <div class="info-value"><?php echo sanitize($requerimento['protocolo']); ?></div>
                            </div>
                            <div class="info-item">
                                <label>Tipo de Alvará</label>
                                <div class="info-value"><?php echo sanitize($requerimento['tipo_alvara']); ?></div>
                            </div>
                            <div class="info-item">
                                <label>Status</label>
                                <div class="info-value">
                                    <span class="status-badge status-<?php echo strtolower($requerimento['status']); ?>">
                                        <?php echo formatarStatus($requerimento['status']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="info-item">
                                <label>Data de Envio</label>
                                <div class="info-value"><?php echo formatarData($requerimento['data_envio']); ?></div>
                            </div>
                            <div class="info-item">
                                <label>Última Atualização</label>
                                <div class="info-value"><?php echo formatarData($requerimento['data_atualizacao']); ?></div>
                            </div>
                        </div>

                        <div class="text-block">
                            <label>Endereço do Objetivo</label>
                            <div class="text-block-content">
                                <?php echo nl2br(sanitize($requerimento['endereco_objetivo'])); ?>
                            </div>
                        </div>

                        <?php if (!empty($requerimento['observacoes'])): ?>
                            <div class="text-block">
                                <label>Observações</label>
                                <div class="text-block-content">
                                    <?php echo nl2br(sanitize($requerimento['observacoes'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Dados do Requerente -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Dados do Requerente</h2>
                    </div>
                    <div class="card-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <label>Nome</label>
                                <div class="info-value"><?php echo sanitize($requerente['nome']); ?></div>
                            </div>
                            <div class="info-item">
                                <label>CPF/CNPJ</label>
                                <div class="info-value"><?php echo sanitize($requerente['cpf_cnpj']); ?></div>
                            </div>
                            <div class="info-item">
                                <label>E-mail</label>
                                <div class="info-value"><?php echo sanitize($requerente['email']); ?></div>
                            </div>
                            <div class="info-item">
                                <label>Telefone</label>
                                <div class="info-value"><?php echo sanitize($requerente['telefone']); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dados do Proprietário (se existir) -->
                <?php if ($proprietario): ?>
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Dados do Proprietário</h2>
                        </div>
                        <div class="card-body">
                            <div class="info-grid">
                                <div class="info-item">
                                    <label>Nome</label>
                                    <div class="info-value"><?php echo sanitize($proprietario['nome']); ?></div>
                                </div>
                                <div class="info-item">
                                    <label>CPF/CNPJ</label>
                                    <div class="info-value"><?php echo sanitize($proprietario['cpf_cnpj']); ?></div>
                                </div>
                                <?php if ($proprietario['mesmo_requerente']): ?>
                                    <div class="info-item">
                                        <label>Observação</label>
                                        <div class="info-value">O proprietário é o mesmo que o requerente</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Documentos -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Documentos</h2>
                    </div>
                    <div class="card-body">
                        <?php if (count($documentos) > 0): ?>
                            <ul class="doc-list">
                                <?php foreach ($documentos as $documento): ?>
                                    <li class="doc-item">
                                        <div class="doc-icon">
                                            <?php
                                            $ext = pathinfo($documento['nome_original'], PATHINFO_EXTENSION);
                                            $icon = 'file';

                                            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                                                $icon = 'file-image';
                                            } elseif (in_array($ext, ['pdf'])) {
                                                $icon = 'file-pdf';
                                            } elseif (in_array($ext, ['doc', 'docx'])) {
                                                $icon = 'file-word';
                                            } elseif (in_array($ext, ['xls', 'xlsx'])) {
                                                $icon = 'file-excel';
                                            }
                                            ?>
                                            <i class="fas fa-<?php echo $icon; ?>"></i>
                                        </div>
                                        <div class="doc-details">
                                            <div class="doc-name"><?php echo sanitize($documento['nome_original']); ?></div>
                                            <div class="doc-meta">
                                                <span><strong>Tipo:</strong> <?php echo sanitize($documento['campo_formulario']); ?></span> •
                                                <span><strong>Tamanho:</strong> <?php echo formatarTamanho($documento['tamanho']); ?></span> •
                                                <span><strong>Enviado em:</strong> <?php echo formatarData($documento['data_upload']); ?></span>
                                            </div>
                                        </div>
                                        <div class="doc-action">
                                            <a href="../<?php echo sanitize($documento['caminho']); ?>" target="_blank">
                                                <i class="fas fa-download"></i> Baixar
                                            </a>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-center" style="padding: 20px; color: var(--gray-color);">
                                Nenhum documento encontrado para este requerimento.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Histórico de Ações -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Histórico de Ações</h2>
                    </div>
                    <div class="card-body">
                        <?php if (count($acoes) > 0): ?>
                            <ul class="timeline">
                                <?php foreach ($acoes as $acao): ?>
                                    <li class="timeline-item">
                                        <div class="timeline-badge badge-info">
                                            <i class="fas fa-history"></i>
                                        </div>
                                        <div class="timeline-content">
                                            <div class="timeline-header">
                                                <div class="timeline-title">
                                                    <?php echo isset($acao['admin_nome']) && $acao['admin_nome'] ? sanitize($acao['admin_nome']) : 'Sistema'; ?>
                                                </div>
                                                <div class="timeline-date">
                                                    <?php echo formatarDataHora($acao['data_acao'] ?? $acao['data'] ?? null); ?>
                                                </div>
                                            </div>
                                            <div class="timeline-body">
                                                <?php echo sanitize($acao['acao']); ?>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-center" style="padding: 20px; color: var(--gray-color);">
                                Nenhuma ação registrada para este requerimento.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar do requerimento -->
            <div class="sidebar-content">
                <!-- Atualizar Status -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Atualizar Status</h2>
                    </div>
                    <div class="card-body">
                        <form action="" method="post">
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select class="form-control" id="status" name="status" required>
                                    <option value="">Selecione um status</option>
                                    <option value="pendente" <?php echo $requerimento['status'] === 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                                    <option value="analise" <?php echo $requerimento['status'] === 'analise' ? 'selected' : ''; ?>>Em Análise</option>
                                    <option value="aprovado" <?php echo $requerimento['status'] === 'aprovado' ? 'selected' : ''; ?>>Aprovado</option>
                                    <option value="rejeitado" <?php echo $requerimento['status'] === 'rejeitado' ? 'selected' : ''; ?>>Rejeitado</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="observacoes">Observações</label>
                                <textarea class="form-control" id="observacoes" name="observacoes" rows="4" placeholder="Adicione observações sobre a mudança de status (opcional)"><?php echo sanitize($requerimento['observacoes']); ?></textarea>
                            </div>

                            <button type="submit" name="atualizar_status" class="btn btn-primary btn-block">
                                <i class="fas fa-save"></i> Atualizar Status
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Ações Rápidas -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Ações Rápidas</h2>
                    </div>
                    <div class="card-body">
                        <a href="javascript:void(0);" onclick="window.print();" class="btn btn-secondary btn-block" style="margin-bottom: 10px;">
                            <i class="fas fa-print"></i> Imprimir Requerimento
                        </a>

                        <a href="requerimentos.php" class="btn btn-secondary btn-block">
                            <i class="fas fa-arrow-left"></i> Voltar para Requerimentos
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Para futura implementação de toggle de sidebar em mobile
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggle = document.querySelector('.menu-toggle');
            const sidebar = document.querySelector('.sidebar');

            if (menuToggle) {
                menuToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                });
            }
        });
    </script>
</body>

</html>