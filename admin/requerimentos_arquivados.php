<?php
require_once 'conexao.php';
verificaLogin();

// Configurações
$itensPorPagina = 25;
$paginaAtual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($paginaAtual - 1) * $itensPorPagina;

// Preparar filtros
$filtroStatus = isset($_GET['status']) ? $_GET['status'] : '';
$filtroTipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$filtroBusca = isset($_GET['busca']) ? $_GET['busca'] : '';

// Mensagens
$mensagem = '';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'restaurado':
            $mensagem = "✅ Processo restaurado com sucesso!";
            break;
    }
}

// Mensagens de erro
$mensagemErro = '';
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'id_invalido':
            $mensagemErro = "❌ ID inválido fornecido.";
            break;
        case 'restauracao':
            $mensagemErro = "❌ Erro ao restaurar processo: " . ($_GET['msg'] ?? 'Erro desconhecido');
            break;
    }
}

// Construir consulta SQL
$sql = "SELECT ra.*, a.nome as admin_nome
        FROM requerimentos_arquivados ra
        LEFT JOIN administradores a ON ra.admin_arquivamento = a.id
        WHERE 1=1";

$sqlCount = "SELECT COUNT(*) as total FROM requerimentos_arquivados ra WHERE 1=1";

$params = [];

// Aplicar filtros
if (!empty($filtroStatus)) {
    $sql .= " AND ra.status = ?";
    $sqlCount .= " AND ra.status = ?";
    $params[] = $filtroStatus;
}

if (!empty($filtroTipo)) {
    $sql .= " AND ra.tipo_alvara = ?";
    $sqlCount .= " AND ra.tipo_alvara = ?";
    $params[] = $filtroTipo;
}

if (!empty($filtroBusca)) {
    $sql .= " AND (ra.protocolo LIKE ? OR ra.requerente_nome LIKE ? OR ra.requerente_cpf_cnpj LIKE ?)";
    $sqlCount .= " AND (ra.protocolo LIKE ? OR ra.requerente_nome LIKE ? OR ra.requerente_cpf_cnpj LIKE ?)";
    $termoBusca = "%$filtroBusca%";
    $params[] = $termoBusca;
    $params[] = $termoBusca;
    $params[] = $termoBusca;
}

// Ordenação
$sql .= " ORDER BY ra.data_arquivamento DESC";

// Paginação
$sql .= " LIMIT $itensPorPagina OFFSET $offset";

// Executar consultas
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requerimentos = $stmt->fetchAll();

$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$totalRegistros = $stmtCount->fetch()['total'];
$totalPaginas = ceil($totalRegistros / $itensPorPagina);

include 'header.php';
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="fas fa-archive me-2"></i>Requerimentos Arquivados</h2>
        <a href="requerimentos.php" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-2"></i>Voltar para Ativos
        </a>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $mensagem; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($mensagemErro): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $mensagemErro; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label for="busca" class="form-label">Buscar</label>
                    <input type="text" class="form-control" id="busca" name="busca" 
                           value="<?php echo htmlspecialchars($filtroBusca); ?>"
                           placeholder="Protocolo, nome ou CPF/CNPJ">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Todos os status</option>
                        <option value="Em análise" <?php echo $filtroStatus == 'Em análise' ? 'selected' : ''; ?>>Em análise</option>
                        <option value="Aprovado" <?php echo $filtroStatus == 'Aprovado' ? 'selected' : ''; ?>>Aprovado</option>
                        <option value="Reprovado" <?php echo $filtroStatus == 'Reprovado' ? 'selected' : ''; ?>>Reprovado</option>
                        <option value="Finalizado" <?php echo $filtroStatus == 'Finalizado' ? 'selected' : ''; ?>>Finalizado</option>
                        <option value="Indeferido" <?php echo $filtroStatus == 'Indeferido' ? 'selected' : ''; ?>>Indeferido</option>
                        <option value="Cancelado" <?php echo $filtroStatus == 'Cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="tipo" class="form-label">Tipo de Alvará</label>
                    <select class="form-select" id="tipo" name="tipo">
                        <option value="">Todos os tipos</option>
                        <?php
                        $stmtTipos = $pdo->query("SELECT DISTINCT tipo_alvara FROM requerimentos_arquivados ORDER BY tipo_alvara");
                        while ($tipo = $stmtTipos->fetch()) {
                            $selected = $filtroTipo == $tipo['tipo_alvara'] ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars($tipo['tipo_alvara']) . "' $selected>" . htmlspecialchars($tipo['tipo_alvara']) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search me-1"></i>Filtrar
                    </button>
                    <a href="requerimentos_arquivados.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Estatísticas -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">
                        <i class="fas fa-chart-bar me-2"></i>Estatísticas dos Arquivados
                    </h6>
                    <div class="row text-center">
                        <div class="col-md-2">
                            <div class="border-end">
                                <h4 class="mb-0 text-muted"><?php echo $totalRegistros; ?></h4>
                                <small class="text-muted">Total Arquivados</small>
                            </div>
                        </div>
                        <?php
                        $stmtStats = $pdo->query("SELECT status, COUNT(*) as total FROM requerimentos_arquivados GROUP BY status");
                        $stats = $stmtStats->fetchAll();
                        foreach ($stats as $stat):
                        ?>
                        <div class="col-md-2">
                            <div class="border-end">
                                <h4 class="mb-0"><?php echo $stat['total']; ?></h4>
                                <small class="text-muted"><?php echo $stat['status']; ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabela -->
    <div class="card">
        <div class="card-body">
            <?php if (count($requerimentos) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Protocolo</th>
                                <th>Requerente</th>
                                <th>Tipo</th>
                                <th>Status</th>
                                <th>Arquivado em</th>
                                <th>Arquivado por</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requerimentos as $req): ?>
                                <tr>
                                    <td>
                                        <span class="fw-bold">#<?php echo $req['protocolo']; ?></span>
                                    </td>
                                    <td>
                                        <div>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($req['requerente_nome']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($req['requerente_email']); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?php echo htmlspecialchars($req['tipo_alvara']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo $req['status']; ?></span>
                                    </td>
                                    <td>
                                        <small><?php echo formataData($req['data_arquivamento']); ?></small>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?php echo htmlspecialchars($req['admin_nome'] ?? 'N/A'); ?></small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary btn-sm"
                                                onclick="visualizarArquivado(<?php echo $req['id']; ?>)"
                                                title="Visualizar detalhes">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-success btn-sm"
                                                onclick="restaurarProcesso(<?php echo $req['id']; ?>)"
                                                title="Restaurar processo">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginação -->
                <?php if ($totalPaginas > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($paginaAtual > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?pagina=<?php echo $paginaAtual - 1; ?>&<?php echo http_build_query($_GET); ?>">Anterior</a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                                <li class="page-item <?php echo $i == $paginaAtual ? 'active' : ''; ?>">
                                    <a class="page-link" href="?pagina=<?php echo $i; ?>&<?php echo http_build_query($_GET); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($paginaAtual < $totalPaginas): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?pagina=<?php echo $paginaAtual + 1; ?>&<?php echo http_build_query($_GET); ?>">Próxima</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-archive text-muted" style="font-size: 4rem;"></i>
                    <h4 class="text-muted mt-3">Nenhum processo arquivado</h4>
                    <p class="text-muted">Não há requerimentos arquivados com os filtros aplicados.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal para visualizar detalhes do arquivado -->
<div class="modal fade" id="detalhesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalhes do Processo Arquivado</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalhes-conteudo">
                <!-- Conteúdo carregado via AJAX -->
            </div>
        </div>
    </div>
</div>

<script>
function visualizarArquivado(id) {
    // Carregar detalhes via AJAX
    fetch('ajax/detalhes_arquivado.php?id=' + id)
        .then(response => response.text())
        .then(data => {
            document.getElementById('detalhes-conteudo').innerHTML = data;
            const modal = new bootstrap.Modal(document.getElementById('detalhesModal'));
            modal.show();
        })
        .catch(error => {
            alert('Erro ao carregar detalhes: ' + error);
        });
}

function restaurarProcesso(id) {
    if (confirm('Deseja restaurar este processo? Ele voltará para a lista principal.')) {
        window.location.href = 'ajax/restaurar_processo.php?id=' + id;
    }
}
</script>

<?php include 'footer.php'; ?> 