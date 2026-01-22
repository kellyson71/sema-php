<?php
require_once 'conexao.php';
verificaLogin();

if (!isset($_GET['id'])) {
    echo '<div class="alert alert-danger">ID não fornecido</div>';
    exit;
}

$id = (int)$_GET['id'];

try {
    $stmt = $pdo->prepare("
        SELECT el.*, 
               r.protocolo,
               req.nome as requerente_nome,
               req.email as requerente_email
        FROM email_logs el
        LEFT JOIN requerimentos r ON el.requerimento_id = r.id
        LEFT JOIN requerentes req ON r.requerente_id = req.id
        WHERE el.id = ?
    ");
    $stmt->execute([$id]);
    $log = $stmt->fetch();

    if (!$log) {
        echo '<div class="alert alert-warning">Log não encontrado</div>';
        exit;
    }
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Erro ao buscar dados: ' . $e->getMessage() . '</div>';
    exit;
}

// Estilos inline para garantir que o modal não quebre e fique bonito
?>

<div class="comprovante-container bg-white">
    <!-- Cabeçalho do Comprovante -->
    <div class="text-center mb-4 pb-3 border-bottom">
        <h5 class="fw-bold text-secondary text-uppercase mb-1">Comprovante de Envio de Email</h5>
        <p class="text-muted small mb-0">Hash de Registro: <?php echo md5($log['id'] . $log['data_envio']); ?></p>
    </div>

    <!-- Status do Envio -->
    <div class="alert <?php echo $log['status'] === 'SUCESSO' ? 'alert-success' : 'alert-danger'; ?> d-flex align-items-center mb-4">
        <?php if ($log['status'] === 'SUCESSO'): ?>
            <i class="fas fa-check-circle fa-2x me-3"></i>
            <div>
                <strong>Email Enviado com Sucesso</strong><br>
                <small>O servidor de email aceitou a solicitação em <?php echo date('d/m/Y \à\s H:i:s', strtotime($log['data_envio'])); ?></small>
            </div>
        <?php else: ?>
            <i class="fas fa-times-circle fa-2x me-3"></i>
            <div>
                <strong>Falha no Envio</strong><br>
                <small>Ocorreu um erro ao tentar processar este envio.</small>
            </div>
        <?php endif; ?>
    </div>

    <!-- Detalhes Técnicos (Grid) -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="p-3 border rounded bg-light h-100">
                <label class="d-block text-muted small fw-bold text-uppercase mb-1">Destinatário</label>
                <div class="d-flex align-items-center">
                    <div class="bg-white rounded-circle p-2 border me-2 text-secondary">
                        <i class="fas fa-user"></i>
                    </div>
                    <div>
                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($log['email_destino']); ?></div>
                        <?php if ($log['requerente_nome']): ?>
                            <small class="text-muted"><?php echo htmlspecialchars($log['requerente_nome']); ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="p-3 border rounded bg-light h-100">
                <label class="d-block text-muted small fw-bold text-uppercase mb-1">Identificação</label>
                <div class="d-flex flex-column gap-1">
                    <?php if ($log['protocolo']): ?>
                        <span class="badge bg-white text-dark border w-auto align-self-start">
                            <i class="fas fa-file-alt me-1 text-primary"></i> Protocolo: <?php echo htmlspecialchars($log['protocolo']); ?>
                        </span>
                    <?php endif; ?>
                    <span class="text-muted small">
                        Enviado por: <strong><?php echo htmlspecialchars($log['usuario_envio'] ?? 'Sistema'); ?></strong>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Se houver erro, mostrar detalhe -->
    <?php if ($log['status'] !== 'SUCESSO' && !empty($log['erro'])): ?>
        <div class="card border-danger mb-4">
            <div class="card-header bg-danger text-white">
                <i class="fas fa-exclamation-triangle me-2"></i> Detalhes do Erro
            </div>
            <div class="card-body bg-light text-danger font-monospace small">
                <?php echo htmlspecialchars($log['erro']); ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Conteúdo do Email -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white border-bottom py-3">
            <label class="text-muted small fw-bold text-uppercase mb-1">Assunto</label>
            <h6 class="mb-0 fw-bold text-dark w-100"><?php echo htmlspecialchars($log['assunto']); ?></h6>
        </div>
        <div class="card-body bg-light p-0">
            <div class="email-preview p-4 bg-white m-3 border rounded">
                <!-- Wrapper seguro para conteúdo HTML do email -->
                <div class="email-content-wrapper" style="all: initial; font-family: sans-serif;">
                    <?php echo $log['mensagem']; ?>
                </div>
            </div>
        </div>
    </div>
</div>
