<?php
require_once 'conexao.php';
verificaLogin();

// Verificar permissão
if (!($_SESSION['admin_nivel'] === 'secretario' || $_SESSION['admin_email'] === 'secretario@sema.rn.gov.br')) {
    header("Location: index.php");
    exit;
}

// Configuração de paginação
$itensPorPagina = 20;
$paginaAtual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($paginaAtual - 1) * $itensPorPagina;

// Filtros
$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';

// Query base
$sqlBase = "FROM requerimentos r
            JOIN requerentes req ON r.requerente_id = req.id
            WHERE r.status = 'Apto a gerar alvará'";

$params = [];

if (!empty($busca)) {
    $sqlBase .= " AND (r.protocolo LIKE ? OR req.nome LIKE ? OR r.tipo_alvara LIKE ?)";
    $termo = "%$busca%";
    $params = [$termo, $termo, $termo];
}

// Contar total
$stmtCount = $pdo->prepare("SELECT COUNT(*) as total $sqlBase");
$stmtCount->execute($params);
$totalRegistros = $stmtCount->fetch()['total'];
$totalPaginas = ceil($totalRegistros / $itensPorPagina);

// Buscar registros
$sql = "SELECT r.id, r.protocolo, r.tipo_alvara, r.data_envio, req.nome as requerente
        $sqlBase
        ORDER BY r.data_envio ASC
        LIMIT $offset, $itensPorPagina";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requerimentos = $stmt->fetchAll();

include 'header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="bg-white rounded-3 shadow-sm p-4 border-start border-5 border-success">
                <h2 class="h3 mb-2 text-success"><i class="fas fa-signature me-2"></i>Aprovação de Alvarás</h2>
                <p class="text-muted mb-0">
                    Bem-vindo(a), Secretário(a). Abaixo estão os processos analisados tecnicamente e aguardando sua assinatura final.
                </p>
            </div>
        </div>
    </div>

    <!-- Filtros de Busca -->
    <div class="card mb-4 shadow-sm border-0">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-center">
                <div class="col-md-10">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" class="form-control border-start-0" name="busca" value="<?php echo htmlspecialchars($busca); ?>" placeholder="Buscar por protocolo, requerente ou tipo...">
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista de Requerimentos -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 py-3">Protocolo</th>
                            <th class="py-3">Requerente</th>
                            <th class="py-3">Tipo de Alvará</th>
                            <th class="py-3">Data de Entrada</th>
                            <th class="text-end pe-4 py-3">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($requerimentos) > 0): ?>
                            <?php foreach ($requerimentos as $req): ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-primary">
                                        #<?php echo htmlspecialchars($req['protocolo']); ?>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle me-2 bg-light text-secondary rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                                <i class="fas fa-user mb-0" style="font-size: 0.8rem;"></i>
                                            </div>
                                            <?php echo htmlspecialchars($req['requerente']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 rounded-pill px-3">
                                            <?php echo htmlspecialchars($req['tipo_alvara']); ?>
                                        </span>
                                    </td>
                                    <td class="text-muted">
                                        <i class="far fa-calendar me-1"></i>
                                        <?php echo date('d/m/Y', strtotime($req['data_envio'])); ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <a href="revisao_secretario.php?id=<?php echo $req['id']; ?>" class="btn btn-success btn-sm px-3 rounded-pill shadow-sm">
                                            <i class="fas fa-pen-nib me-1"></i> Revisar e Assinar
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <div class="d-flex flex-column align-items-center">
                                        <i class="fas fa-check-circle fa-3x mb-3 text-light-gray"></i>
                                        <p class="mb-0">Nenhum processo aguardando assinatura no momento.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Paginação -->
        <?php if ($totalPaginas > 1): ?>
        <div class="card-footer bg-white border-top-0 py-3">
            <nav aria-label="Navegação de página">
                <ul class="pagination justify-content-center mb-0">
                    <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                        <li class="page-item <?php echo $i === $paginaAtual ? 'active' : ''; ?>">
                            <a class="page-link" href="?pagina=<?php echo $i; ?>&busca=<?php echo urlencode($busca); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
