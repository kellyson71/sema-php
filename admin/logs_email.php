<?php
require_once '../includes/config.php';
require_once 'conexao.php';

verificaLogin();

// Paginação
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
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

// Estatísticas Rápidas
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

<style>
    :root {
        --primary-600: #059669;
        --secondary-color: #134E5E;
    }
    
    .modern-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        border: 1px solid rgba(0,0,0,0.05);
        margin-bottom: 25px;
        overflow: hidden;
    }

    .modern-card-header {
        background: white;
        padding: 20px 25px;
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modern-card-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--secondary-color);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .stat-card {
        background: white;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.03);
        border: 1px solid #f0f0f0;
        transition: transform 0.2s;
        height: 100%;
    }

    .stat-card:hover {
        transform: translateY(-2px);
    }

    .stat-value {
        font-size: 1.8rem;
        font-weight: 700;
        margin-bottom: 5px;
    }

    .stat-label {
        color: #6c757d;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .table-custom th {
        background-color: #f8f9fa;
        color: #495057;
        font-weight: 600;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-top: none;
        padding: 15px;
    }

    .table-custom td {
        vertical-align: middle;
        padding: 15px;
        font-size: 0.9rem;
        border-bottom: 1px solid #f0f0f0;
    }

    .table-custom tr:hover {
        background-color: #fcfcfc;
    }

    .badge-status {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .badge-success-soft {
        background-color: #d1fae5;
        color: #065f46;
    }

    .badge-danger-soft {
        background-color: #fee2e2;
        color: #991b1b;
    }

    .filter-section {
        background-color: #f9fafb;
        padding: 20px;
        border-bottom: 1px solid #f0f0f0;
    }

    .form-control-sm, .form-select-sm {
        border-radius: 6px;
        border-color: #dee2e6;
        padding: 8px 12px;
    }

    .form-control-sm:focus, .form-select-sm:focus {
        border-color: var(--primary-600);
        box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
    }

    .btn-action {
        width: 32px;
        height: 32px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        transition: all 0.2s;
    }
    
    .pagination-container {
        padding: 20px;
        border-top: 1px solid #f0f0f0;
    }
</style>

<div class="container-fluid py-4">
    <!-- Header Page -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1 fw-bold text-dark">Logs de Email</h4>
            <p class="text-muted mb-0">Histórico de todas as comunicações enviadas pelo sistema</p>
        </div>
        <div>
            <button class="btn btn-light border" onclick="window.location.reload()">
                <i class="fas fa-sync-alt me-2"></i>Atualizar
            </button>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-card border-start border-4 border-primary">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value text-primary"><?php echo number_format($stats['total']); ?></div>
                        <div class="stat-label">Total Enviados</div>
                    </div>
                    <div class="text-primary opacity-25">
                        <i class="fas fa-paper-plane fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card border-start border-4 border-success">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value text-success"><?php echo number_format($stats['sucessos']); ?></div>
                        <div class="stat-label">Entregues com Sucesso</div>
                    </div>
                    <div class="text-success opacity-25">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card border-start border-4 border-danger">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value text-danger"><?php echo number_format($stats['erros']); ?></div>
                        <div class="stat-label">Falhas no Envio</div>
                    </div>
                    <div class="text-danger opacity-25">
                        <i class="fas fa-exclamation-triangle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card border-start border-4 border-info">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value text-info"><?php echo number_format($stats['hoje']); ?></div>
                        <div class="stat-label">Enviados Hoje</div>
                    </div>
                    <div class="text-info opacity-25">
                        <i class="fas fa-calendar-day fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modern-card">
        <!-- Filtros -->
        <div class="filter-section">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label text-muted small fw-bold mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Todos os Status</option>
                        <option value="SUCESSO" <?php echo $filtro_status === 'SUCESSO' ? 'selected' : ''; ?>>Sucesso</option>
                        <option value="ERRO" <?php echo $filtro_status === 'ERRO' ? 'selected' : ''; ?>>Erro</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-bold mb-1">Destinatário (Email)</label>
                    <input type="text" name="email" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filtro_email); ?>" placeholder="Ex: joao@email.com">
                </div>
                <div class="col-md-2">
                    <label class="form-label text-muted small fw-bold mb-1">De</label>
                    <input type="date" name="data_inicio" class="form-control form-control-sm" value="<?php echo $filtro_data_inicio; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label text-muted small fw-bold mb-1">Até</label>
                    <input type="date" name="data_fim" class="form-control form-control-sm" value="<?php echo $filtro_data_fim; ?>">
                </div>
                <div class="col-md-3">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm px-3 w-100">
                            <i class="fas fa-filter me-1"></i> Filtrar
                        </button>
                        <a href="logs_email.php" class="btn btn-outline-secondary btn-sm px-3 w-100">
                            <i class="fas fa-times me-1"></i> Limpar
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Tabela -->
        <div class="table-responsive">
            <table class="table table-custom mb-0">
                <thead>
                    <tr>
                        <th class="ps-4" width="70">ID</th>
                        <th width="100">Status</th>
                        <th width="180">Data/Hora</th>
                        <th>Destinatário</th>
                        <th>Assunto</th>
                        <th width="150">Usuário</th>
                        <th class="text-end pe-4" width="100">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="fas fa-inbox fa-3x mb-3 opacity-25"></i>
                                    <p class="mb-0">Nenhum registro encontrado para os filtros selecionados.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="ps-4 text-muted">#<?php echo $log['id']; ?></td>
                                <td>
                                    <?php if ($log['status'] === 'SUCESSO'): ?>
                                        <span class="badge badge-status badge-success-soft">
                                            <i class="fas fa-check me-1"></i> Sucesso
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-status badge-danger-soft">
                                            <i class="fas fa-times-circle me-1"></i> Erro
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="fw-bold text-dark"><?php echo date('d/m/Y', strtotime($log['data_envio'])); ?></span>
                                        <span class="text-muted small"><?php echo date('H:i:s', strtotime($log['data_envio'])); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="fw-medium text-dark"><?php echo htmlspecialchars($log['email_destino']); ?></span>
                                        <?php if ($log['requerente_nome']): ?>
                                            <span class="text-muted small">
                                                <i class="fas fa-user me-1" style="font-size: 0.7rem;"></i> 
                                                <?php echo htmlspecialchars($log['requerente_nome']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-break" style="max-width: 300px;">
                                        <?php if ($log['protocolo']): ?>
                                            <a href="visualizar_requerimento.php?id=<?php echo $log['requerimento_id']; ?>" class="badge bg-light text-dark text-decoration-none border mb-1">
                                                Proto: <?php echo htmlspecialchars($log['protocolo']); ?>
                                            </a>
                                            <br>
                                        <?php endif; ?>
                                        <span class="text-dark" title="<?php echo htmlspecialchars($log['assunto']); ?>">
                                            <?php echo htmlspecialchars(mb_strimwidth($log['assunto'], 0, 50, '...')); ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-secondary border">
                                        <?php echo htmlspecialchars($log['usuario_envio'] ?? 'Sistema'); ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-action btn-outline-primary" onclick="showLogDetails(<?php echo $log['id']; ?>)" title="Ver Detalhes">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginação -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination-container">
                <nav>
                    <ul class="pagination justify-content-center mb-0">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($filtro_status); ?>&email=<?php echo urlencode($filtro_email); ?>&data_inicio=<?php echo urlencode($filtro_data_inicio); ?>&data_fim=<?php echo urlencode($filtro_data_fim); ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);

                        if ($start > 1) {
                            echo '<li class="page-item"><a class="page-link" href="?page=1&status=' . urlencode($filtro_status) . '&email=' . urlencode($filtro_email) . '...">1</a></li>';
                            if ($start > 2) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                        }

                        for ($i = $start; $i <= $end; $i++):
                        ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($filtro_status); ?>&email=<?php echo urlencode($filtro_email); ?>&data_inicio=<?php echo urlencode($filtro_data_inicio); ?>&data_fim=<?php echo urlencode($filtro_data_fim); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <?php
                        if ($end < $total_pages) {
                            if ($end < $total_pages - 1) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&status=' . urlencode($filtro_status) . '&email=' . urlencode($filtro_email) . '...">' . $total_pages . '</a></li>';
                        }
                        ?>

                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($filtro_status); ?>&email=<?php echo urlencode($filtro_email); ?>&data_inicio=<?php echo urlencode($filtro_data_inicio); ?>&data_fim=<?php echo urlencode($filtro_data_fim); ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para detalhes do log -->
<div class="modal fade" id="logDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light border-bottom-0">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-info-circle me-2 text-primary"></i> Detalhes do Email
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="logDetailsContent">
                <!-- Conteúdo carregado via AJAX -->
            </div>
            <div class="modal-footer border-top-0 bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script>
    function showLogDetails(logId) {
        const modal = new bootstrap.Modal(document.getElementById('logDetailsModal'));
        const content = document.getElementById('logDetailsContent');
        
        content.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary mb-3" role="status"></div>
                <p class="text-muted mb-0">Carregando detalhes...</p>
            </div>
        `;
        modal.show();

        fetch(`ajax_log_details.php?id=${logId}`)
            .then(response => {
                if (!response.ok) throw new Error('Erro na requisição');
                return response.text();
            })
            .then(data => {
                content.innerHTML = data;
            })
            .catch(error => {
                console.error('Erro:', error);
                content.innerHTML = `
                    <div class="alert alert-danger d-flex align-items-center">
                        <i class="fas fa-exclamation-circle fa-2x me-3"></i>
                        <div>
                            <strong>Erro ao carregar!</strong><br>
                            Não foi possível obter os detalhes deste log.
                        </div>
                    </div>
                `;
            });
    }
</script>

<?php include 'footer.php'; ?>