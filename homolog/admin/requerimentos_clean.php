<?php
require_once 'conexao.php';
verificaLogin();

// Verificar se houve ação de sucesso
$mensagem = '';
$mensagemTipo = '';

if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'nao_lido':
            $mensagem = "Requerimento marcado como não lido com sucesso!";
            $mensagemTipo = "success";
            break;
    }
}

// Configurações de paginação
$itensPorPagina = 15;
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

<!-- Alert de Sucesso -->
<?php if (!empty($mensagem)): ?>
    <div class="alert alert-<?php echo $mensagemTipo; ?> alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <?php echo $mensagem; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="section-title mb-1">
            <i class="fas fa-file-alt me-2"></i>Gerenciamento de Requerimentos
        </h2>
        <p class="text-muted">Visualize e gerencie todos os requerimentos de alvará</p>
    </div>
    <div class="d-flex gap-3">
        <span class="badge bg-primary fs-6 px-3 py-2">
            Total: <?php echo $totalRequerimentos; ?>
        </span>
        <?php if ($totalNaoVisualizados > 0): ?>
            <span class="badge bg-warning text-dark fs-6 px-3 py-2">
                <i class="fas fa-bell me-1"></i><?php echo $totalNaoVisualizados; ?> não lidos
            </span>
        <?php endif; ?>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-body">
        <form action="" method="get" class="row g-3">
            <div class="col-md-4">
                <label for="busca" class="form-label">Buscar</label>
                <input type="text" class="form-control" id="busca" name="busca" 
                       placeholder="Digite protocolo, nome ou CPF/CNPJ..." 
                       value="<?php echo htmlspecialchars($filtroBusca); ?>">
            </div>
            <div class="col-md-2">
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
                <div class="w-100">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="nao_visualizados" 
                               name="nao_visualizados" value="1" <?php echo $filtroNaoVisualizados ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="nao_visualizados">
                            <i class="fas fa-bell text-warning"></i> Apenas não visualizados
                        </label>
                    </div>
                    <div class="d-grid gap-2 d-md-flex">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                        <a href="requerimentos.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Limpar
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Tabela Clean -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Lista de Requerimentos</h5>
    </div>
    <div class="card-body p-0">
        <?php if (count($requerimentos) > 0): ?>
            <table id="requerimentos-table" class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>
                            <span class="d-flex align-items-center">
                                <i class="fas fa-hashtag me-2"></i>Protocolo
                            </span>
                        </th>
                        <th>
                            <span class="d-flex align-items-center">
                                <i class="fas fa-user me-2"></i>Requerente
                            </span>
                        </th>
                        <th>
                            <span class="d-flex align-items-center">
                                <i class="fas fa-file-alt me-2"></i>Tipo de Alvará
                            </span>
                        </th>
                        <th>
                            <span class="d-flex align-items-center">
                                <i class="fas fa-flag me-2"></i>Status
                            </span>
                        </th>
                        <th>
                            <span class="d-flex align-items-center">
                                <i class="fas fa-calendar me-2"></i>Data de Envio
                            </span>
                        </th>
                        <th class="text-center">
                            <span class="d-flex align-items-center justify-content-center">
                                <i class="fas fa-cogs me-2"></i>Ações
                            </span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requerimentos as $req): ?>
                        <tr class="<?php echo $req['visualizado'] == 0 ? 'table-warning' : ''; ?>">
                            <td class="font-medium text-gray-900 whitespace-nowrap">
                                <div class="d-flex align-items-center">
                                    <?php if ($req['visualizado'] == 0): ?>
                                        <span class="badge bg-danger rounded-pill me-2" style="width: 8px; height: 8px; padding: 0;" title="Não visualizado"></span>
                                    <?php endif; ?>
                                    <strong><?php echo $req['protocolo']; ?></strong>
                                </div>
                            </td>
                            <td><?php echo $req['requerente']; ?></td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <?php echo $req['tipo_alvara']; ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $statusColor = [
                                    'Pendente' => 'warning',
                                    'Em Análise' => 'info',
                                    'Aprovado' => 'success',
                                    'Reprovado' => 'danger',
                                    'Aguardando Documentos' => 'secondary'
                                ];
                                $color = $statusColor[$req['status']] ?? 'primary';
                                ?>
                                <span class="badge bg-<?php echo $color; ?>">
                                    <?php echo $req['status']; ?>
                                </span>
                            </td>
                            <td class="text-muted"><?php echo formataData($req['data_envio']); ?></td>
                            <td class="text-center">
                                <a href="visualizar_requerimento.php?id=<?php echo $req['id']; ?>" 
                                   class="btn btn-sm btn-primary" 
                                   title="Visualizar Requerimento">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h4>Nenhum requerimento encontrado</h4>
                <p class="text-muted">Tente ajustar os filtros de pesquisa ou verificar se há requerimentos cadastrados.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Paginação -->
<?php if ($totalPaginas > 1): ?>
    <nav aria-label="Paginação dos requerimentos" class="mt-4">
        <ul class="pagination justify-content-center">
            <li class="page-item <?php echo $paginaAtual == 1 ? 'disabled' : ''; ?>">
                <a class="page-link" href="?pagina=1<?php echo !empty($filtroStatus) ? '&status=' . urlencode($filtroStatus) : ''; ?><?php echo !empty($filtroTipo) ? '&tipo=' . urlencode($filtroTipo) : ''; ?><?php echo !empty($filtroBusca) ? '&busca=' . urlencode($filtroBusca) : ''; ?><?php echo $filtroNaoVisualizados ? '&nao_visualizados=1' : ''; ?>">
                    <i class="fas fa-angle-double-left"></i>
                </a>
            </li>
            <li class="page-item <?php echo $paginaAtual == 1 ? 'disabled' : ''; ?>">
                <a class="page-link" href="?pagina=<?php echo max(1, $paginaAtual - 1); ?><?php echo !empty($filtroStatus) ? '&status=' . urlencode($filtroStatus) : ''; ?><?php echo !empty($filtroTipo) ? '&tipo=' . urlencode($filtroTipo) : ''; ?><?php echo !empty($filtroBusca) ? '&busca=' . urlencode($filtroBusca) : ''; ?><?php echo $filtroNaoVisualizados ? '&nao_visualizados=1' : ''; ?>">
                    <i class="fas fa-angle-left"></i>
                </a>
            </li>

            <?php
            $inicio = max(1, $paginaAtual - 2);
            $fim = min($totalPaginas, $paginaAtual + 2);

            for ($i = $inicio; $i <= $fim; $i++):
            ?>
                <li class="page-item <?php echo $paginaAtual == $i ? 'active' : ''; ?>">
                    <a class="page-link" href="?pagina=<?php echo $i; ?><?php echo !empty($filtroStatus) ? '&status=' . urlencode($filtroStatus) : ''; ?><?php echo !empty($filtroTipo) ? '&tipo=' . urlencode($filtroTipo) : ''; ?><?php echo !empty($filtroBusca) ? '&busca=' . urlencode($filtroBusca) : ''; ?><?php echo $filtroNaoVisualizados ? '&nao_visualizados=1' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
            <?php endfor; ?>

            <li class="page-item <?php echo $paginaAtual == $totalPaginas ? 'disabled' : ''; ?>">
                <a class="page-link" href="?pagina=<?php echo min($totalPaginas, $paginaAtual + 1); ?><?php echo !empty($filtroStatus) ? '&status=' . urlencode($filtroStatus) : ''; ?><?php echo !empty($filtroTipo) ? '&tipo=' . urlencode($filtroTipo) : ''; ?><?php echo !empty($filtroBusca) ? '&busca=' . urlencode($filtroBusca) : ''; ?><?php echo $filtroNaoVisualizados ? '&nao_visualizados=1' : ''; ?>">
                    <i class="fas fa-angle-right"></i>
                </a>
            </li>
            <li class="page-item <?php echo $paginaAtual == $totalPaginas ? 'disabled' : ''; ?>">
                <a class="page-link" href="?pagina=<?php echo $totalPaginas; ?><?php echo !empty($filtroStatus) ? '&status=' . urlencode($filtroStatus) : ''; ?><?php echo !empty($filtroTipo) ? '&tipo=' . urlencode($filtroTipo) : ''; ?><?php echo !empty($filtroBusca) ? '&busca=' . urlencode($filtroBusca) : ''; ?><?php echo $filtroNaoVisualizados ? '&nao_visualizados=1' : ''; ?>">
                    <i class="fas fa-angle-double-right"></i>
                </a>
            </li>
        </ul>
        <div class="text-center mt-3">
            <small class="text-muted">
                Página <?php echo $paginaAtual; ?> de <?php echo $totalPaginas; ?> 
                (<?php echo $totalRequerimentos; ?> requerimentos no total)
            </small>
        </div>
    </nav>
<?php endif; ?>

<!-- Simple DataTables com busca -->
<script src="https://cdn.jsdelivr.net/npm/simple-datatables@latest" type="text/javascript"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar Simple DataTables se existir a tabela
    if (document.getElementById("requerimentos-table") && typeof simpleDatatables.DataTable !== 'undefined') {
        const dataTable = new simpleDatatables.DataTable("#requerimentos-table", {
            searchable: true,
            sortable: true,
            perPageSelect: false,
            perPage: <?php echo $itensPorPagina; ?>,
            labels: {
                placeholder: "Buscar na tabela...",
                searchTitle: "Buscar",
                pageTitle: "Página {page}",
                noRows: "Nenhum requerimento encontrado",
                info: "Mostrando {start} a {end} de {rows} requerimentos"
            }
        });
    }

    // Auto-dismiss de alertas
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });

    // Smooth scroll para paginação
    const paginationLinks = document.querySelectorAll('.pagination .page-link');
    paginationLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (!this.parentElement.classList.contains('disabled')) {
                document.querySelector('.card').scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
});
</script>

<style>
/* Estilos customizados para a tabela */
.table-warning {
    --bs-table-bg: rgba(255, 193, 7, 0.1);
    border-left: 4px solid #ffc107;
    font-weight: 500;
}

.table > :not(caption) > * > * {
    padding: 1rem 0.75rem;
    border-bottom-width: 1px;
}

.table thead th {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
    color: #495057;
}

/* Hover effect para linhas */
.table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.05);
    transform: translateX(2px);
    transition: all 0.2s ease;
}

/* Badges com melhor estilo */
.badge {
    font-size: 0.75em;
    font-weight: 500;
}

/* Botões de ação */
.btn-sm {
    padding: 0.375rem 0.75rem;
    border-radius: 0.375rem;
    transition: all 0.2s ease;
}

.btn-sm:hover {
    transform: scale(1.05);
}

/* Simple DataTables customização */
.dataTable-wrapper .dataTable-top,
.dataTable-wrapper .dataTable-bottom {
    padding: 1rem;
}

.dataTable-search input {
    border-radius: 0.375rem;
    border: 2px solid #e9ecef;
    padding: 0.5rem 0.75rem;
}

.dataTable-search input:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
}

/* Card hover effect */
.card {
    transition: all 0.3s ease;
}

.card:hover {
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    transform: translateY(-2px);
}

/* Animações suaves */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.card {
    animation: fadeIn 0.5s ease-out;
}
</style>

<?php include 'footer.php'; ?>
