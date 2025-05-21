<?php
require_once 'conexao.php';
verificaLogin();

// Configurações de paginação
$itensPorPagina = 10;
$paginaAtual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($paginaAtual - 1) * $itensPorPagina;

// Preparar filtros
$filtroStatus = isset($_GET['status']) ? $_GET['status'] : '';
$filtroTipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$filtroBusca = isset($_GET['busca']) ? $_GET['busca'] : '';
$filtroNaoVisualizados = isset($_GET['nao_visualizados']) && $_GET['nao_visualizados'] == '1';

// Construir a consulta SQL com filtros
$sql = "SELECT r.id, r.protocolo, r.tipo_alvara, r.status, r.data_envio, r.visualizado, req.nome as requerente 
        FROM requerimentos r
        JOIN requerentes req ON r.requerente_id = req.id
        WHERE 1=1";
$sqlCount = "SELECT COUNT(*) as total FROM requerimentos r
              JOIN requerentes req ON r.requerente_id = req.id
              WHERE 1=1";
$params = [];

if (!empty($filtroStatus)) {
    $sql .= " AND r.status = ?";
    $sqlCount .= " AND r.status = ?";
    $params[] = $filtroStatus;
}

if (!empty($filtroTipo)) {
    $sql .= " AND r.tipo_alvara = ?";
    $sqlCount .= " AND r.tipo_alvara = ?";
    $params[] = $filtroTipo;
}

if (!empty($filtroBusca)) {
    $sql .= " AND (r.protocolo LIKE ? OR req.nome LIKE ? OR req.cpf_cnpj LIKE ?)";
    $sqlCount .= " AND (r.protocolo LIKE ? OR req.nome LIKE ? OR req.cpf_cnpj LIKE ?)";
    $termoBusca = "%$filtroBusca%";
    $params[] = $termoBusca;
    $params[] = $termoBusca;
    $params[] = $termoBusca;
}

if ($filtroNaoVisualizados) {
    $sql .= " AND r.visualizado = 0";
    $sqlCount .= " AND r.visualizado = 0";
}

// Ordenação
$sql .= " ORDER BY r.data_envio DESC";

// Adicionar LIMIT para paginação
$sql .= " LIMIT $offset, $itensPorPagina";

// Executar consultas
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requerimentos = $stmt->fetchAll();

$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$totalRequerimentos = $stmtCount->fetch()['total'];

// Contar requerimentos não visualizados
$sqlNaoVis = "SELECT COUNT(*) as total FROM requerimentos WHERE visualizado = 0";
$stmtNaoVis = $pdo->query($sqlNaoVis);
$totalNaoVisualizados = $stmtNaoVis->fetch()['total'];

$totalPaginas = ceil($totalRequerimentos / $itensPorPagina);

// Obter lista de tipos de alvará para o filtro
$stmtTipos = $pdo->query("SELECT DISTINCT tipo_alvara FROM requerimentos ORDER BY tipo_alvara");
$tiposAlvara = $stmtTipos->fetchAll();

// Obter lista de status para o filtro
$stmtStatus = $pdo->query("SELECT DISTINCT status FROM requerimentos ORDER BY status");
$statusList = $stmtStatus->fetchAll();

include 'header.php';
?>

<h2 class="section-title">Requerimentos</h2>

<div class="card mb-4">
    <div class="card-body">
        <form action="" method="get" class="row g-3">
            <div class="col-md-3">
                <label for="busca" class="form-label">Buscar</label>
                <input type="text" class="form-control" id="busca" name="busca" placeholder="Protocolo, nome ou CPF/CNPJ" value="<?php echo htmlspecialchars($filtroBusca); ?>">
            </div>
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Todos</option>
                    <?php foreach ($statusList as $s): ?>
                        <option value="<?php echo $s['status']; ?>" <?php echo $filtroStatus === $s['status'] ? 'selected' : ''; ?>>
                            <?php echo $s['status']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="tipo" class="form-label">Tipo de Alvará</label>
                <select class="form-select" id="tipo" name="tipo">
                    <option value="">Todos</option>
                    <?php foreach ($tiposAlvara as $tipo): ?>
                        <option value="<?php echo $tipo['tipo_alvara']; ?>" <?php echo $filtroTipo === $tipo['tipo_alvara'] ? 'selected' : ''; ?>>
                            <?php echo $tipo['tipo_alvara']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <div class="d-grid gap-2 d-md-flex justify-content-md-end w-100">
                    <div class="form-check form-switch me-2 mt-2">
                        <input class="form-check-input" type="checkbox" id="nao_visualizados" name="nao_visualizados" value="1" <?php echo $filtroNaoVisualizados ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="nao_visualizados">
                            <i class="fas fa-bell"></i> Apenas não visualizados
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                    <a href="requerimentos.php" class="btn btn-outline-secondary">
                        <i class="fas fa-undo"></i> Limpar
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Lista de Requerimentos</span>
        <span>
            Total: <?php echo $totalRequerimentos; ?> requerimentos
            <?php if ($totalNaoVisualizados > 0): ?>
                <span class="badge bg-primary ms-2">
                    <i class="fas fa-bell"></i> <?php echo $totalNaoVisualizados; ?> não visualizados
                </span>
            <?php endif; ?>
        </span>
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
                    <?php foreach ($requerimentos as $req): ?>
                        <tr class="clickable-row <?php echo $req['visualizado'] == 0 ? 'unread-row' : ''; ?>" data-href="visualizar_requerimento.php?id=<?php echo $req['id']; ?>">
                            <td>
                                <?php if ($req['visualizado'] == 0): ?>
                                    <span class="unread-indicator" title="Não visualizado"></span>
                                <?php endif; ?>
                                <?php echo $req['protocolo']; ?>
                            </td>
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
                    <?php if (count($requerimentos) == 0): ?>
                        <tr>
                            <td colspan="5" class="text-center">Nenhum requerimento encontrado</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPaginas > 1): ?>
            <nav aria-label="Paginação" class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo $paginaAtual == 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?pagina=1<?php echo !empty($filtroStatus) ? '&status=' . urlencode($filtroStatus) : ''; ?><?php echo !empty($filtroTipo) ? '&tipo=' . urlencode($filtroTipo) : ''; ?><?php echo !empty($filtroBusca) ? '&busca=' . urlencode($filtroBusca) : ''; ?>">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                    </li>
                    <li class="page-item <?php echo $paginaAtual == 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?pagina=<?php echo max(1, $paginaAtual - 1); ?><?php echo !empty($filtroStatus) ? '&status=' . urlencode($filtroStatus) : ''; ?><?php echo !empty($filtroTipo) ? '&tipo=' . urlencode($filtroTipo) : ''; ?><?php echo !empty($filtroBusca) ? '&busca=' . urlencode($filtroBusca) : ''; ?>">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    </li>

                    <?php
                    $inicio = max(1, $paginaAtual - 2);
                    $fim = min($totalPaginas, $paginaAtual + 2);

                    for ($i = $inicio; $i <= $fim; $i++):
                    ?>
                        <li class="page-item <?php echo $paginaAtual == $i ? 'active' : ''; ?>">
                            <a class="page-link" href="?pagina=<?php echo $i; ?><?php echo !empty($filtroStatus) ? '&status=' . urlencode($filtroStatus) : ''; ?><?php echo !empty($filtroTipo) ? '&tipo=' . urlencode($filtroTipo) : ''; ?><?php echo !empty($filtroBusca) ? '&busca=' . urlencode($filtroBusca) : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>

                    <li class="page-item <?php echo $paginaAtual == $totalPaginas ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?pagina=<?php echo min($totalPaginas, $paginaAtual + 1); ?><?php echo !empty($filtroStatus) ? '&status=' . urlencode($filtroStatus) : ''; ?><?php echo !empty($filtroTipo) ? '&tipo=' . urlencode($filtroTipo) : ''; ?><?php echo !empty($filtroBusca) ? '&busca=' . urlencode($filtroBusca) : ''; ?>">
                            <i class="fas fa-angle-right"></i>
                        </a>
                    </li>
                    <li class="page-item <?php echo $paginaAtual == $totalPaginas ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?pagina=<?php echo $totalPaginas; ?><?php echo !empty($filtroStatus) ? '&status=' . urlencode($filtroStatus) : ''; ?><?php echo !empty($filtroTipo) ? '&tipo=' . urlencode($filtroTipo) : ''; ?><?php echo !empty($filtroBusca) ? '&busca=' . urlencode($filtroBusca) : ''; ?>">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
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

<style>
    .unread-row {
        font-weight: bold;
        background-color: rgba(45, 134, 97, 0.1) !important;
    }

    .unread-indicator {
        display: inline-block;
        width: 10px;
        height: 10px;
        background-color: #2D8661;
        border-radius: 50%;
        margin-right: 5px;
    }
</style>

<?php include 'footer.php'; ?>