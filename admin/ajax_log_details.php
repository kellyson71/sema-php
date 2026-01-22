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
?>

<div class="table-responsive">
    <table class="table table-bordered">
        <tr>
            <th width="150" class="bg-light">ID</th>
            <td><?php echo $log['id']; ?></td>
        </tr>
        <tr>
            <th class="bg-light">Data Envio</th>
            <td><?php echo date('d/m/Y H:i:s', strtotime($log['data_envio'])); ?></td>
        </tr>
        <tr>
            <th class="bg-light">Status</th>
            <td>
                <?php if ($log['status'] === 'SUCESSO'): ?>
                    <span class="badge bg-success">Sucesso</span>
                <?php else: ?>
                    <span class="badge bg-danger">Erro</span>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th class="bg-light">Enviado por</th>
            <td><?php echo htmlspecialchars($log['usuario_envio'] ?? 'Sistema'); ?></td>
        </tr>
        <tr>
            <th class="bg-light">Protocolo Rel.</th>
            <td><?php echo htmlspecialchars($log['protocolo'] ?? 'N/A'); ?></td>
        </tr>
        <tr>
            <th class="bg-light">Destinatário</th>
            <td>
                <?php echo htmlspecialchars($log['email_destino']); ?>
                <?php if ($log['requerente_nome']): ?>
                    <br><small class="text-muted"><?php echo htmlspecialchars($log['requerente_nome']); ?></small>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th class="bg-light">Assunto</th>
            <td><?php echo htmlspecialchars($log['assunto']); ?></td>
        </tr>
        <?php if (!empty($log['erro'])): ?>
            <tr>
                <th class="bg-light text-danger">Erro Detalhado</th>
                <td class="text-danger"><?php echo htmlspecialchars($log['erro']); ?></td>
            </tr>
        <?php endif; ?>
        <?php if (!empty($log['detalhes_envio'])): ?>
            <tr>
                <th class="bg-light">Info Técnica</th>
                <td><code><?php echo htmlspecialchars($log['detalhes_envio']); ?></code></td>
            </tr>
        <?php endif; ?>
    </table>
</div>

<div class="card mt-3">
    <div class="card-header bg-light">
        <strong>Conteúdo do Email</strong>
    </div>
    <div class="card-body p-3 bg-white" style="max-height: 400px; overflow-y: auto;">
        <div class="border rounded p-3">
            <?php echo $log['mensagem']; ?>
        </div>
    </div>
</div>
