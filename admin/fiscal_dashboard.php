<?php
require_once 'conexao.php';
require_once __DIR__ . '/../includes/functions.php';
verificaLogin();

// Verificar permissão
if (!in_array($_SESSION['admin_nivel'], ['fiscal', 'admin', 'admin_geral'])) {
    header("Location: index.php");
    exit;
}

// Configuração de paginação
$itensPorPagina = 20;
$paginaAtual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($paginaAtual - 1) * $itensPorPagina;

// Filtros
$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';

$sqlBase = "FROM requerimentos r
            JOIN requerentes req ON r.requerente_id = req.id
            WHERE r.status = 'Aguardando Fiscalização'";

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

$sql = "SELECT r.id, r.protocolo, r.tipo_alvara, r.status, r.data_envio, r.data_atualizacao, req.nome as requerente
        $sqlBase
        ORDER BY r.data_atualizacao ASC
        LIMIT $offset, $itensPorPagina";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requerimentos = $stmt->fetchAll();

// Estatísticas
$aguardandoAnalise = $pdo->query("SELECT COUNT(*) FROM requerimentos WHERE status = 'Aguardando Fiscalização'")->fetchColumn();

$stmtAnalisados = $pdo->prepare("
    SELECT COUNT(DISTINCT requerimento_id) FROM historico_acoes
    WHERE admin_id = ? AND acao LIKE '%assinou%'
");
$stmtAnalisados->execute([$_SESSION['admin_id']]);
$analisadosPorMim = $stmtAnalisados->fetchColumn();

$totalSetor = (int)$aguardandoAnalise + (int)$analisadosPorMim;

include 'header.php';
?>

<div class="container-fluid py-4">
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="bg-white rounded-3 shadow-sm p-4 border-start border-5" style="border-color:#10b981!important">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                    <div>
                        <h2 class="h3 mb-2" style="color:#10b981"><i class="fas fa-hard-hat me-2"></i>Fiscalização de Obras</h2>
                        <p class="text-muted mb-0">
                            Analise os processos técnicos, gere o parecer de vistoria e encaminhe ao Secretário para emissão do alvará.
                        </p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="fiscal_dashboard.php" class="btn btn-outline-secondary active">
                            Aguardando Análise
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
                            <div class="text-muted small text-uppercase fw-bold">Aguardando Análise</div>
                            <div class="h2 mb-0 text-warning"><?php echo $aguardandoAnalise; ?></div>
                        </div>
                        <div class="rounded-circle bg-warning bg-opacity-10 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                            <i class="fas fa-clock text-warning"></i>
                        </div>
                    </div>
                    <div class="small text-muted mt-2">Processos aguardando vistoria técnica</div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted small text-uppercase fw-bold">Analisados por Mim</div>
                            <div class="h2 mb-0 text-success"><?php echo $analisadosPorMim; ?></div>
                        </div>
                        <div class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                            <i class="fas fa-check-double text-success"></i>
                        </div>
                    </div>
                    <div class="small text-muted mt-2">Pareceres assinados nesta sessão</div>
                </div>
            </div>
        </div>

        <div class="col-lg-4 d-none d-lg-block">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase fw-bold">Fluxo de trabalho</div>
                    <ol class="mb-0 mt-2 ps-3 text-muted">
                        <li>Abrir processo</li>
                        <li>Gerar parecer técnico</li>
                        <li>Assinar digitalmente</li>
                        <li>Enviar ao Secretário</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Mensagens de Feedback -->
    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-dismissible fade show mb-4 shadow-sm <?php echo $_GET['msg'] === 'enviado' ? 'alert-success' : 'alert-info'; ?>" role="alert">
            <?php if ($_GET['msg'] === 'enviado'): ?>
                <i class="fas fa-check-circle me-2"></i> <strong>Sucesso!</strong> Processo enviado ao Secretário para emissão do alvará.
            <?php else: ?>
                <i class="fas fa-info-circle me-2"></i> <?php echo htmlspecialchars($_GET['msg']); ?>
            <?php endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

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
                            <th class="py-3">Dias Aguardando</th>
                            <th class="text-end pe-4 py-3">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($requerimentos) > 0): ?>
                            <?php foreach ($requerimentos as $req): ?>
                                <?php
                                    $diasAguardando = (int)floor((time() - strtotime($req['data_atualizacao'])) / 86400);
                                    if ($diasAguardando > 7) {
                                        $badgeDias = 'bg-danger';
                                    } elseif ($diasAguardando > 3) {
                                        $badgeDias = 'bg-warning text-dark';
                                    } else {
                                        $badgeDias = 'bg-secondary';
                                    }
                                ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-primary">
                                        #<?php echo htmlspecialchars($req['protocolo']); ?>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-light text-secondary d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px; flex-shrink:0;">
                                                <i class="fas fa-user" style="font-size: 0.8rem;"></i>
                                            </div>
                                            <?php echo htmlspecialchars($req['requerente']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 rounded-pill px-3">
                                            <?php echo htmlspecialchars(nomeAlvara($req['tipo_alvara'])); ?>
                                        </span>
                                    </td>
                                    <td class="text-muted">
                                        <i class="far fa-calendar me-1"></i>
                                        <?php echo date('d/m/Y', strtotime($req['data_envio'])); ?>
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill <?php echo $badgeDias; ?> px-3">
                                            <?php echo $diasAguardando; ?> dia<?php echo $diasAguardando !== 1 ? 's' : ''; ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <a href="documentos/selecionar.php?requerimento_id=<?php echo $req['id']; ?>" class="btn btn-sm px-3 rounded-pill shadow-sm text-white" style="background:#10b981;">
                                            <i class="fas fa-file-signature me-1"></i> Analisar e Assinar
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <div class="d-flex flex-column align-items-center">
                                        <i class="fas fa-check-circle fa-3x mb-3 text-success opacity-25"></i>
                                        <p class="mb-0">Nenhum processo aguardando fiscalização.</p>
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
