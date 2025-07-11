<?php
// Verificação de redirecionamento para o domínio principal
$host = $_SERVER['HTTP_HOST'] ?? '';
if (preg_match('/^(www\.)?sema\.protocolosead\.com$/i', $host)) {
    $redirect_url = 'http://sema.paudosferros.rn.gov.br' . $_SERVER['REQUEST_URI'];
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: $redirect_url");
    exit();
}

require_once 'conexao.php';
verificaLogin();

// Obter estatísticas para o dashboard
// Total de requerimentos
$stmt = $pdo->query("SELECT COUNT(*) as total FROM requerimentos");
$totalRequerimentos = $stmt->fetch()['total'];

// Requerimentos em análise (mais importante)
$stmt = $pdo->query("SELECT COUNT(*) as total FROM requerimentos WHERE status = 'Em análise'");
$emAnalise = $stmt->fetch()['total'];

// Requerimentos não visualizados
$stmt = $pdo->query("SELECT COUNT(*) as total FROM requerimentos WHERE visualizado = 0");
$naoVisualizados = $stmt->fetch()['total'];

// Últimos requerimentos recebidos
$stmt = $pdo->query("
    SELECT r.id, r.protocolo, r.tipo_alvara, r.status, r.data_envio, req.nome as requerente 
    FROM requerimentos r
    JOIN requerentes req ON r.requerente_id = req.id
    ORDER BY r.data_envio DESC
    LIMIT 10
");
$ultimosRequerimentos = $stmt->fetchAll();

// Histórico de ações recentes
$stmt = $pdo->query("
    SELECT ha.acao, ha.data_acao, a.nome as admin_nome, r.protocolo
    FROM historico_acoes ha
    LEFT JOIN administradores a ON ha.admin_id = a.id
    LEFT JOIN requerimentos r ON ha.requerimento_id = r.id
    ORDER BY ha.data_acao DESC
    LIMIT 8
");
$historicoAcoes = $stmt->fetchAll();

// Últimos emails enviados
$stmt = $pdo->query("
    SELECT el.email_destino, el.assunto, el.status, el.data_envio, r.protocolo
    FROM email_logs el
    LEFT JOIN requerimentos r ON el.requerimento_id = r.id
    WHERE el.eh_teste = 0 OR el.eh_teste IS NULL
    ORDER BY el.data_envio DESC
    LIMIT 8
");
$ultimosEmails = $stmt->fetchAll();

include 'header.php';
?>

<style>
    .timeline-activity,
    .email-activity {
        max-height: 300px;
        overflow-y: auto;
    }

    .activity-item {
        font-size: 0.85rem;
        transition: background-color 0.2s;
    }

    .activity-item:hover {
        background-color: rgba(0, 123, 255, 0.05);
        border-radius: 4px;
    }

    .clickable-row {
        cursor: pointer;
        transition: background-color 0.2s;
    }

    .clickable-row:hover {
        background-color: rgba(0, 123, 255, 0.1);
    }

    .badge-sm {
        font-size: 0.7rem;
    }
</style>

<h2 class="section-title">Dashboard</h2>

<div class="row">
    <!-- Cards de estatísticas resumidas -->
    <div class="col-md-3 mb-4">
        <div class="card h-100 border-info">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Total de Requerimentos</h6>
                        <h2 class="mb-0 text-info"><?php echo $totalRequerimentos; ?></h2>
                    </div>
                    <div class="rounded-circle bg-info bg-opacity-10 p-3">
                        <i class="fas fa-clipboard-list fa-2x text-info"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card h-100 border-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Em Análise</h6>
                        <h2 class="mb-0 text-warning"><?php echo $emAnalise; ?></h2>
                    </div>
                    <div class="rounded-circle bg-warning bg-opacity-10 p-3">
                        <i class="fas fa-hourglass-half fa-2x text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card h-100 border-danger">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Não Visualizados</h6>
                        <h2 class="mb-0 text-danger"><?php echo $naoVisualizados; ?></h2>
                    </div>
                    <div class="rounded-circle bg-danger bg-opacity-10 p-3">
                        <i class="fas fa-envelope fa-2x text-danger"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card h-100 border-success">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Ver Relatórios</h6>
                        <a href="estatisticas.php" class="btn btn-success btn-sm">
                            <i class="fas fa-chart-bar me-1"></i> Estatísticas
                        </a>
                    </div>
                    <div class="rounded-circle bg-success bg-opacity-10 p-3">
                        <i class="fas fa-chart-pie fa-2x text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Últimos requerimentos - Card principal -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-inbox me-2"></i>Últimos Requerimentos</h5>
                <a href="requerimentos.php" class="btn btn-sm btn-outline-primary">Ver Todos</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Requerente</th>
                                <th>Tipo</th>
                                <th>Status</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ultimosRequerimentos as $req): ?>
                                <tr class="clickable-row" data-href="visualizar_requerimento.php?id=<?php echo $req['id']; ?>">
                                    <td><?php echo $req['requerente']; ?></td>
                                    <td><?php echo $req['tipo_alvara']; ?></td>
                                    <td>
                                        <?php
                                        $statusColor = match ($req['status']) {
                                            'Em análise' => '#ffc107',
                                            'Aprovado' => '#198754',
                                            'Finalizado' => '#0d6efd',
                                            'Reprovado' => '#dc3545',
                                            'Pendente' => '#0dcaf0',
                                            'Cancelado' => '#6c757d',
                                            'Indeferido' => '#212529',
                                            default => '#e9ecef'
                                        };
                                        ?>
                                        <span class="d-flex align-items-center">
                                            <span class="status-dot me-2" style="width: 8px; height: 8px; border-radius: 50%; background-color: <?php echo $statusColor; ?>; display: inline-block;"></span>
                                            <?php echo $req['status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo formataData($req['data_envio']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (count($ultimosRequerimentos) == 0): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted">Nenhum requerimento encontrado</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Atividades recentes -->
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-history me-2"></i>Histórico de Ações</h6>
            </div>
            <div class="card-body p-2">
                <div class="timeline-activity">
                    <?php foreach ($historicoAcoes as $acao): ?>
                        <div class="activity-item mb-2 p-2 border-start border-2 border-primary">
                            <small class="text-muted d-block"><?php echo date('d/m H:i', strtotime($acao['data_acao'])); ?></small>
                            <div class="fw-medium"><?php echo $acao['admin_nome'] ?? 'Sistema'; ?></div>
                            <small><?php echo htmlspecialchars($acao['acao']); ?></small>
                            <?php if ($acao['protocolo']): ?>
                                <small class="text-primary d-block">Protocolo: <?php echo $acao['protocolo']; ?></small>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if (count($historicoAcoes) == 0): ?>
                        <p class="text-muted small">Nenhuma ação registrada</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-envelope me-2"></i>Últimos Emails</h6>
            </div>
            <div class="card-body p-2">
                <div class="email-activity">
                    <?php foreach ($ultimosEmails as $email): ?>
                        <div class="activity-item mb-2 p-2 border-start border-2 <?php echo $email['status'] == 'SUCESSO' ? 'border-success' : 'border-danger'; ?>">
                            <small class="text-muted d-block"><?php echo date('d/m H:i', strtotime($email['data_envio'])); ?></small>
                            <div class="fw-medium"><?php echo $email['email_destino']; ?></div>
                            <small><?php echo htmlspecialchars($email['assunto']); ?></small>
                            <span class="badge bg-<?php echo $email['status'] == 'SUCESSO' ? 'success' : 'danger'; ?> badge-sm">
                                <?php echo $email['status']; ?>
                            </span>
                            <?php if ($email['protocolo']): ?>
                                <small class="text-primary d-block">Protocolo: <?php echo $email['protocolo']; ?></small>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if (count($ultimosEmails) == 0): ?>
                        <p class="text-muted small">Nenhum email enviado</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Adicionar evento de clique às linhas da tabela
        document.querySelectorAll('.clickable-row').forEach(function(row) {
            row.addEventListener('click', function() {
                window.location.href = this.dataset.href;
            });
        });
    });
</script>

<?php include 'footer.php'; ?>