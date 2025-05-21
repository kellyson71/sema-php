<?php
require_once 'conexao.php';
verificaLogin();

// Obter estatísticas para o dashboard
// Total de requerimentos
$stmt = $pdo->query("SELECT COUNT(*) as total FROM requerimentos");
$totalRequerimentos = $stmt->fetch()['total'];

// Total por status
$stmt = $pdo->query("SELECT status, COUNT(*) as total FROM requerimentos GROUP BY status");
$totalPorStatus = $stmt->fetchAll();

// Últimos requerimentos recebidos
$stmt = $pdo->query("
    SELECT r.id, r.protocolo, r.tipo_alvara, r.status, r.data_envio, req.nome as requerente 
    FROM requerimentos r
    JOIN requerentes req ON r.requerente_id = req.id
    ORDER BY r.data_envio DESC
    LIMIT 10
");
$ultimosRequerimentos = $stmt->fetchAll();

include 'header.php';
?>

<h2 class="section-title">Dashboard</h2>

<div class="row">
    <!-- Cards de estatísticas -->
    <div class="col-md-3 mb-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Total de Requerimentos</h6>
                        <h2 class="mb-0"><?php echo $totalRequerimentos; ?></h2>
                    </div>
                    <div class="rounded-circle bg-light p-3">
                        <i class="fas fa-clipboard-list fa-2x text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
    $statusIcons = [
        'Em análise' => '<i class="fas fa-hourglass-half fa-2x text-warning"></i>',
        'Aprovado' => '<i class="fas fa-check-circle fa-2x text-success"></i>',
        'Reprovado' => '<i class="fas fa-times-circle fa-2x text-danger"></i>',
        'Pendente' => '<i class="fas fa-exclamation-circle fa-2x text-info"></i>',
        'Cancelado' => '<i class="fas fa-ban fa-2x text-secondary"></i>'
    ];

    foreach ($totalPorStatus as $status):
        $icon = $statusIcons[$status['status']] ?? '<i class="fas fa-question-circle fa-2x text-primary"></i>';
    ?>
        <div class="col-md-3 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2"><?php echo $status['status']; ?></h6>
                            <h2 class="mb-0"><?php echo $status['total']; ?></h2>
                        </div>
                        <div class="rounded-circle bg-light p-3">
                            <?php echo $icon; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row">
    <!-- Últimos requerimentos -->
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Últimos Requerimentos</span>
                <a href="requerimentos.php" class="btn btn-sm btn-outline-primary">Ver Todos</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Protocolo</th>
                                <th>Requerente</th>
                                <th>Tipo</th>
                                <th>Status</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ultimosRequerimentos as $req): ?>
                                <tr class="clickable-row" data-href="visualizar_requerimento.php?id=<?php echo $req['id']; ?>">
                                    <td><?php echo $req['protocolo']; ?></td>
                                    <td><?php echo $req['requerente']; ?></td>
                                    <td><?php echo $req['tipo_alvara']; ?></td>
                                    <td>
                                        <span class="badge badge-status status-<?php echo strtolower(str_replace(' ', '-', $req['status'])); ?>">
                                            <?php echo $req['status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo formataData($req['data_envio']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (count($ultimosRequerimentos) == 0): ?>
                                <tr>
                                    <td colspan="5" class="text-center">Nenhum requerimento encontrado</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
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