<?php
require_once '../includes/config.php';
require_once 'conexao.php';

verificaLogin();

// Paginação
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 25;
$offset = ($page - 1) * $per_page;

// Filtros
$filtro_status = $_GET['status'] ?? '';
$filtro_email = $_GET['email'] ?? '';
$filtro_data_inicio = $_GET['data_inicio'] ?? '';
$filtro_data_fim = $_GET['data_fim'] ?? '';

// Construir query
$where_conditions = [];
$params = [];

if (!empty($filtro_status)) {
    $where_conditions[] = "el.status = ?";
    $params[] = $filtro_status;
}

if (!empty($filtro_email)) {
    $where_conditions[] = "el.email_destino LIKE ?";
    $params[] = "%{$filtro_email}%";
}

if (!empty($filtro_data_inicio)) {
    $where_conditions[] = "DATE(el.data_envio) >= ?";
    $params[] = $filtro_data_inicio;
}

if (!empty($filtro_data_fim)) {
    $where_conditions[] = "DATE(el.data_envio) <= ?";
    $params[] = $filtro_data_fim;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Query principal
$sql = "
    SELECT 
        el.*,
        r.protocolo,
        req.nome as requerente_nome
    FROM email_logs el
    LEFT JOIN requerimentos r ON el.requerimento_id = r.id
    LEFT JOIN requerentes req ON r.requerente_id = req.id
    {$where_clause}
    ORDER BY el.data_envio DESC
    LIMIT {$per_page} OFFSET {$offset}
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Contar total para paginação
$count_sql = "
    SELECT COUNT(*) as total
    FROM email_logs el
    LEFT JOIN requerimentos r ON el.requerimento_id = r.id
    LEFT JOIN requerentes req ON r.requerente_id = req.id
    {$where_clause}
";

$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_logs = $count_stmt->fetch()['total'];
$total_pages = ceil($total_logs / $per_page);

// Estatísticas
$stats_sql = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'SUCESSO' THEN 1 ELSE 0 END) as sucessos,
        SUM(CASE WHEN status = 'ERRO' THEN 1 ELSE 0 END) as erros,
        SUM(CASE WHEN DATE(data_envio) = CURDATE() THEN 1 ELSE 0 END) as hoje
    FROM email_logs
";

$stats_stmt = $pdo->query($stats_sql);
$stats = $stats_stmt->fetch();

include 'header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-envelope-open-text me-2"></i>
                        Logs de Email
                    </h5>
                    <button class="btn btn-outline-secondary btn-sm" onclick="window.location.reload()">
                        <i class="fas fa-sync-alt me-1"></i>Atualizar
                    </button>
                </div>

                <!-- Estatísticas -->
                <div class="card-body border-bottom">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="text-center">
                                <h4 class="text-primary mb-1"><?php echo number_format($stats['total']); ?></h4>
                                <small class="text-muted">Total de Emails</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h4 class="text-success mb-1"><?php echo number_format($stats['sucessos']); ?></h4>
                                <small class="text-muted">Sucessos</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h4 class="text-danger mb-1"><?php echo number_format($stats['erros']); ?></h4>
                                <small class="text-muted">Erros</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h4 class="text-info mb-1"><?php echo number_format($stats['hoje']); ?></h4>
                                <small class="text-muted">Hoje</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="card-body border-bottom">
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="">Todos</option>
                                <option value="SUCESSO" <?php echo $filtro_status === 'SUCESSO' ? 'selected' : ''; ?>>Sucesso</option>
                                <option value="ERRO" <?php echo $filtro_status === 'ERRO' ? 'selected' : ''; ?>>Erro</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Email</label>
                            <input type="text" name="email" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filtro_email); ?>" placeholder="parte do email...">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Data Início</label>
                            <input type="date" name="data_inicio" class="form-control form-control-sm" value="<?php echo $filtro_data_inicio; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Data Fim</label>
                            <input type="date" name="data_fim" class="form-control form-control-sm" value="<?php echo $filtro_data_fim; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fas fa-search me-1"></i>Filtrar
                                </button>
                                <a href="logs_email.php" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-times me-1"></i>Limpar
                                </a>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Tabela de logs -->
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="60">ID</th>
                                    <th width="80">Status</th>
                                    <th width="120">Protocolo</th>
                                    <th>Destinatário</th>
                                    <th>Assunto</th>
                                    <th width="100">Usuário</th>
                                    <th width="130">Data</th>
                                    <th width="80">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($logs)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted">
                                            <i class="fas fa-inbox fa-2x mb-3 d-block"></i>
                                            Nenhum log de email encontrado
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td><?php echo $log['id']; ?></td>
                                            <td>
                                                <?php if ($log['status'] === 'SUCESSO'): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-check me-1"></i>Sucesso
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">
                                                        <i class="fas fa-times me-1"></i>Erro
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($log['protocolo']): ?>
                                                    <a href="visualizar_requerimento.php?id=<?php echo $log['requerimento_id']; ?>" class="text-decoration-none">
                                                        <?php echo htmlspecialchars($log['protocolo']); ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span><?php echo htmlspecialchars($log['email_destino']); ?></span>
                                                    <?php if ($log['requerente_nome']): ?>
                                                        <small class="text-muted"><?php echo htmlspecialchars($log['requerente_nome']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span title="<?php echo htmlspecialchars($log['assunto']); ?>">
                                                    <?php echo htmlspecialchars(mb_strimwidth($log['assunto'], 0, 40, '...')); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small><?php echo htmlspecialchars($log['usuario_envio'] ?? 'Sistema'); ?></small>
                                            </td>
                                            <td>
                                                <small>
                                                    <?php echo date('d/m/Y H:i', strtotime($log['data_envio'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-info" onclick="showLogDetails(<?php echo $log['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Paginação -->
                <?php if ($total_pages > 1): ?>
                    <div class="card-footer">
                        <nav>
                            <ul class="pagination pagination-sm justify-content-center mb-0">
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($filtro_status); ?>&email=<?php echo urlencode($filtro_email); ?>&data_inicio=<?php echo urlencode($filtro_data_inicio); ?>&data_fim=<?php echo urlencode($filtro_data_fim); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal para detalhes do log -->
<div class="modal fade" id="logDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalhes do Email</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="logDetailsContent">
                <!-- Conteúdo carregado via AJAX -->
            </div>
        </div>
    </div>
</div>

<script>
    function showLogDetails(logId) {
        const modal = new bootstrap.Modal(document.getElementById('logDetailsModal'));
        const content = document.getElementById('logDetailsContent');

        content.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Carregando...</div>';
        modal.show();

        fetch(`ajax_log_details.php?id=${logId}`)
            .then(response => response.text())
            .then(data => {
                content.innerHTML = data;
            })
            .catch(error => {
                content.innerHTML = '<div class="alert alert-danger">Erro ao carregar detalhes</div>';
            });
    }
</script>

<?php include 'footer.php'; ?>