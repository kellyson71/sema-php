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
    <!-- Card principal - Total de requerimentos -->
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

    <?php
    // Estatísticas principais (mais relevantes)
    $statusPrincipais = [
        'Em análise' => ['icon' => 'fas fa-hourglass-half', 'color' => 'warning'],
        'Aprovado' => ['icon' => 'fas fa-check-circle', 'color' => 'success'],
        'Finalizado' => ['icon' => 'fas fa-flag-checkered', 'color' => 'primary'],
        'Reprovado' => ['icon' => 'fas fa-times-circle', 'color' => 'danger']
    ];

    // Organizar estatísticas por status
    $estatisticasPorStatus = [];
    foreach ($totalPorStatus as $status) {
        $estatisticasPorStatus[$status['status']] = $status['total'];
    }

    // Mostrar apenas os status principais
    foreach ($statusPrincipais as $statusNome => $config):
        $total = $estatisticasPorStatus[$statusNome] ?? 0;
        if ($total > 0 || in_array($statusNome, ['Em análise', 'Aprovado'])): // Sempre mostrar Em análise e Aprovado
    ?>
            <div class="col-md-3 mb-4">
                <div class="card h-100 border-<?php echo $config['color']; ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2"><?php echo $statusNome; ?></h6>
                                <h2 class="mb-0 text-<?php echo $config['color']; ?>"><?php echo $total; ?></h2>
                            </div>
                            <div class="rounded-circle bg-<?php echo $config['color']; ?> bg-opacity-10 p-3">
                                <i class="<?php echo $config['icon']; ?> fa-2x text-<?php echo $config['color']; ?>"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php
        endif;
    endforeach;

    // Mostrar outros status em um card resumido se existirem
    $outrosStatus = array_diff_key($estatisticasPorStatus, $statusPrincipais);
    if (!empty($outrosStatus)):
        $totalOutros = array_sum($outrosStatus);
        ?>
        <div class="col-md-3 mb-4">
            <div class="card h-100 border-secondary">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Outros Status</h6>
                            <h2 class="mb-0 text-secondary"><?php echo $totalOutros; ?></h2>
                            <small class="text-muted">
                                <?php echo implode(', ', array_keys($outrosStatus)); ?>
                            </small>
                        </div>
                        <div class="rounded-circle bg-secondary bg-opacity-10 p-3">
                            <i class="fas fa-ellipsis-h fa-2x text-secondary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
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
                                    <td><?php echo $req['status']; ?></td>
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