<?php
require_once 'conexao.php';
verificaLogin();

if (!isset($_GET['id'])) {
    echo '<div class="alert alert-danger m-3">ID não fornecido</div>';
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
        echo '<div class="alert alert-warning m-3">Log não encontrado</div>';
        exit;
    }
} catch (PDOException $e) {
    echo '<div class="alert alert-danger m-3">Erro ao buscar dados: ' . $e->getMessage() . '</div>';
    exit;
}
?>

<div class="container-fluid p-0">
    <!-- Status Banner -->
    <div class="alert <?php echo $log['status'] === 'SUCESSO' ? 'alert-success' : 'alert-danger'; ?> mb-4 rounded-3 border-0 shadow-sm">
        <div class="d-flex align-items-center">
            <?php if ($log['status'] === 'SUCESSO'): ?>
                <div class="display-6 me-3"><i class="fas fa-check-circle"></i></div>
                <div>
                    <h5 class="alert-heading fw-bold mb-1">Email Enviado com Sucesso</h5>
                    <p class="mb-0 small opacity-75">Processado em <?php echo date('d/m/Y \à\s H:i:s', strtotime($log['data_envio'])); ?></p>
                </div>
            <?php else: ?>
                <div class="display-6 me-3"><i class="fas fa-times-circle"></i></div>
                <div>
                    <h5 class="alert-heading fw-bold mb-1">Falha no Envio</h5>
                    <p class="mb-0 small opacity-75">Ocorreu um erro durante o processamento.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4">
        <!-- Detalhes do Envio -->
        <div class="col-12">
            <h6 class="text-uppercase text-muted fw-bold small mb-3">Informações do Envio</h6>
            <div class="card border-0 shadow-sm bg-light">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="small text-muted fw-bold">DESTINATÁRIO</label>
                            <div class="d-flex align-items-center mt-1">
                                <div class="avatar bg-white text-secondary rounded p-2 me-2 border">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div class="text-break">
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($log['email_destino']); ?></div>
                                    <?php if ($log['requerente_nome']): ?>
                                        <div class="small text-muted"><?php echo htmlspecialchars($log['requerente_nome']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="small text-muted fw-bold">CONTEXTO</label>
                            <div class="mt-1">
                                <?php if ($log['protocolo']): ?>
                                    <div class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-10 py-2 px-3">
                                        Protocolo #<?php echo htmlspecialchars($log['protocolo']); ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted fst-italic">Sem protocolo vinculado</span>
                                <?php endif; ?>
                                <div class="small text-muted mt-1">
                                    Enviado por: <strong><?php echo htmlspecialchars($log['usuario_envio'] ?? 'Sistema'); ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Erro (se houver) -->
        <?php if ($log['status'] !== 'SUCESSO' && !empty($log['erro'])): ?>
            <div class="col-12">
                <h6 class="text-uppercase text-danger fw-bold small mb-2">Detalhes do Erro</h6>
                <div class="p-3 bg-danger bg-opacity-10 border border-danger border-opacity-25 rounded font-monospace small text-danger">
                    <?php echo htmlspecialchars($log['erro']); ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Conteúdo do Email -->
        <div class="col-12">
            <h6 class="text-uppercase text-muted fw-bold small mb-3">Conteúdo da Mensagem</h6>
            <div class="card border border-light shadow-sm">
                <div class="card-header bg-white py-3 border-bottom-0">
                    <div class="small text-muted fw-bold mb-1">ASSUNTO</div>
                    <h5 class="mb-0 text-dark"><?php echo htmlspecialchars($log['assunto']); ?></h5>
                </div>
                <div class="card-body p-0">
                    <div class="message-preview bg-white p-4 border-top">
                        <!-- Wrapper seguro para HTML -->
                        <div class="email-safe-container" style="
                            font-family: 'Segoe UI', Arial, sans-serif;
                            line-height: 1.6;
                            color: #333;
                            overflow-wrap: break-word;
                            max-width: 100%;
                        ">
                            <?php echo $log['mensagem']; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
