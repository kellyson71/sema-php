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
$statusFiltro = isset($_GET['status']) ? trim($_GET['status']) : 'todos';

$sqlBase = "FROM requerimentos r
            JOIN requerentes req ON r.requerente_id = req.id
            WHERE r.status IN ('Apto a gerar alvará', 'Alvará Emitido')";

$params = [];

if ($statusFiltro === 'pendentes') {
    $sqlBase .= " AND r.status = 'Apto a gerar alvará'";
} elseif ($statusFiltro === 'emitidos') {
    $sqlBase .= " AND r.status = 'Alvará Emitido'";
}

if (!empty($busca)) {
    $sqlBase .= " AND (r.protocolo LIKE ? OR req.nome LIKE ? OR r.tipo_alvara LIKE ?)";
    $termo = "%$busca%";
    $params = [$termo, $termo, $termo];
}

$sqlResumo = "FROM requerimentos r
            JOIN requerentes req ON r.requerente_id = req.id
            WHERE r.status IN ('Apto a gerar alvará', 'Alvará Emitido')";
$paramsResumo = [];
if (!empty($busca)) {
    $sqlResumo .= " AND (r.protocolo LIKE ? OR req.nome LIKE ? OR r.tipo_alvara LIKE ?)";
    $paramsResumo = [$termo, $termo, $termo];
}

// Contar total
$stmtCount = $pdo->prepare("SELECT COUNT(*) as total $sqlBase");
$stmtCount->execute($params);
$totalRegistros = $stmtCount->fetch()['total'];
$totalPaginas = ceil($totalRegistros / $itensPorPagina);

$sql = "SELECT r.id, r.protocolo, r.tipo_alvara, r.status, r.data_envio, req.nome as requerente
        $sqlBase
        ORDER BY FIELD(r.status, 'Apto a gerar alvará', 'Alvará Emitido'), r.data_envio DESC
        LIMIT $offset, $itensPorPagina";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requerimentos = $stmt->fetchAll();

$stmtResumo = $pdo->prepare("SELECT r.status, COUNT(*) as total $sqlResumo GROUP BY r.status");
$stmtResumo->execute($paramsResumo);
$resumoStatus = $stmtResumo->fetchAll(PDO::FETCH_ASSOC);
$contagem = [
    'Apto a gerar alvará' => 0,
    'Alvará Emitido' => 0
];
foreach ($resumoStatus as $linha) {
    $contagem[$linha['status']] = (int)$linha['total'];
}

include 'header.php';
?>

<div class="container-fluid py-4">
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="bg-white rounded-3 shadow-sm p-4 border-start border-5 border-success">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                    <div>
                        <h2 class="h3 mb-2 text-success"><i class="fas fa-signature me-2"></i>Assinatura do Secretário</h2>
                        <p class="text-muted mb-0">
                            Revise os documentos técnicos, confirme a assinatura e emita o alvará com segurança.
                        </p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="?status=pendentes" class="btn btn-outline-warning <?php echo $statusFiltro === 'pendentes' ? 'active' : ''; ?>">
                            Pendentes
                        </a>
                        <a href="?status=emitidos" class="btn btn-outline-success <?php echo $statusFiltro === 'emitidos' ? 'active' : ''; ?>">
                            Emitidos
                        </a>
                        <a href="secretario_dashboard.php" class="btn btn-outline-secondary <?php echo $statusFiltro === 'todos' ? 'active' : ''; ?>">
                            Todos
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted small text-uppercase fw-bold">Pendentes</div>
                            <div class="h2 mb-0 text-warning"><?php echo $contagem['Apto a gerar alvará']; ?></div>
                        </div>
                        <div class="rounded-circle bg-warning bg-opacity-10 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                            <i class="fas fa-clock text-warning"></i>
                        </div>
                    </div>
                    <div class="small text-muted mt-2">Aguardam assinatura para emissão</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted small text-uppercase fw-bold">Emitidos</div>
                            <div class="h2 mb-0 text-success"><?php echo $contagem['Alvará Emitido']; ?></div>
                        </div>
                        <div class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                            <i class="fas fa-check-double text-success"></i>
                        </div>
                    </div>
                    <div class="small text-muted mt-2">Assinaturas concluídas</div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 d-none d-lg-block">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase fw-bold">Fluxo rápido</div>
                    <ol class="mb-0 mt-2 ps-3 text-muted">
                        <li>Revisar documento</li>
                        <li>Confirmar assinatura</li>
                        <li>Emitir alvará</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Mensagens de Feedback -->
    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-dismissible fade show mb-4 shadow-sm <?php echo $_GET['msg'] == 'devolvido' ? 'alert-warning' : 'alert-success'; ?>" role="alert">
            <?php if ($_GET['msg'] == 'sucesso_assinatura'): ?>
                <i class="fas fa-check-circle me-2"></i> <strong>Sucesso!</strong> O alvará foi assinado e emitido corretamente.
            <?php elseif ($_GET['msg'] == 'devolvido'): ?>
                <i class="fas fa-undo me-2"></i> <strong>Devolvido!</strong> O processo foi retornado para correção técnica.
            <?php endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Filtros de Busca -->
    <div class="card mb-4 shadow-sm border-0">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-center">
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFiltro); ?>">
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
                            <th class="py-3">Status</th>
                            <th class="text-end pe-4 py-3">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($requerimentos) > 0): ?>
                            <?php foreach ($requerimentos as $req): ?>
                                <?php 
                                    $isSigned = $req['status'] === 'Alvará Emitido';
                                    $statusClass = $isSigned ? 'bg-success text-white' : 'bg-warning text-dark';
                                    $statusIcon = $isSigned ? 'fa-check-double' : 'fa-clock';
                                ?>
                                <tr class="<?php echo $isSigned ? 'bg-light bg-opacity-25' : ''; ?>">
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
                                    <td>
                                        <span class="badge rounded-pill <?php echo $statusClass; ?> px-3">
                                            <i class="fas <?php echo $statusIcon; ?> me-1"></i>
                                            <?php echo $req['status']; ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <?php if ($isSigned): ?>
                                            <a href="revisao_secretario.php?id=<?php echo $req['id']; ?>" class="btn btn-outline-secondary btn-sm px-3 rounded-pill">
                                                <i class="fas fa-eye me-1"></i> Visualizar
                                            </a>
                                        <?php else: ?>
                                            <a href="revisao_secretario.php?id=<?php echo $req['id']; ?>" class="btn btn-success btn-sm px-3 rounded-pill shadow-sm">
                                                <i class="fas fa-pen-nib me-1"></i> Revisar e Assinar
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <div class="d-flex flex-column align-items-center">
                                        <i class="fas fa-check-circle fa-3x mb-3 text-light-gray"></i>
                                        <p class="mb-0">Nenhum processo encontrado.</p>
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
                            <a class="page-link" href="?pagina=<?php echo $i; ?>&busca=<?php echo urlencode($busca); ?>&status=<?php echo urlencode($statusFiltro); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
