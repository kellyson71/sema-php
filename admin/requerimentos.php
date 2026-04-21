<?php
require_once 'conexao.php';
require_once __DIR__ . '/../includes/functions.php';
verificaLogin();

// Incluir arquivos de componentes
require_once 'includes/filtros.php';
require_once 'includes/estatisticas.php';
require_once 'includes/tabela.php';
require_once 'includes/alertas.php';
require_once 'includes/context-menu.php';

// Função auxiliar para formatar data brasileira
function formataDataBR($data)
{
    return date('d/m/Y \à\s H:i', strtotime($data));
}

// Configurações
$itensPorPagina = 25;
$paginaAtual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($paginaAtual - 1) * $itensPorPagina;

// Preparar filtros
$filtroStatus = isset($_GET['status']) ? $_GET['status'] : '';
$filtroTipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$filtroBusca = isset($_GET['busca']) ? $_GET['busca'] : '';
$filtroNaoVisualizados = isset($_GET['nao_visualizados']) && $_GET['nao_visualizados'] == '1';
$modoVisualizacao = (isset($_GET['view']) && $_GET['view'] === 'table') ? 'table' : 'list';

// Mensagens de sucesso
$mensagem = '';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'nao_lido':
            $mensagem = "✅ Requerimento marcado como não lido com sucesso!";
            break;
        case 'atualizado':
            $mensagem = "✅ Requerimento atualizado com sucesso!";
            break;
        case 'acoes_massa':
            $mensagem = "✅ " . ($_GET['msg'] ?? 'Ação executada com sucesso!');
            break;
        case 'arquivado':
            $mensagem = "✅ Processo arquivado com sucesso! O requerimento foi movido para o arquivo.";
            break;
    }
}

// Mensagens de erro
$mensagemErro = '';
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'dados_invalidos':
            $mensagemErro = "❌ Dados inválidos para a ação solicitada.";
            break;
        case 'ids_invalidos':
            $mensagemErro = "❌ IDs de requerimentos inválidos.";
            break;
        case 'erro_acao':
            $mensagemErro = "❌ Erro ao executar ação: " . ($_GET['details'] ?? 'Erro desconhecido');
            break;
    }
}

// Construir consulta SQL otimizada
$sql = "SELECT r.id, r.protocolo, r.tipo_alvara, r.status, r.data_envio, r.visualizado, 
               req.nome as requerente 
        FROM requerimentos r
        JOIN requerentes req ON r.requerente_id = req.id
        WHERE 1=1";

$sqlCount = "SELECT COUNT(*) as total FROM requerimentos r
             JOIN requerentes req ON r.requerente_id = req.id
             WHERE 1=1";

$params = [];

// Aplicar filtros
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

// Ordenação e paginação
$sql .= " ORDER BY r.visualizado ASC, r.data_envio DESC LIMIT $offset, $itensPorPagina";

// Executar consultas
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requerimentos = $stmt->fetchAll();

$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$totalRequerimentos = $stmtCount->fetch()['total'];

// Estatísticas gerais
$estatisticas = [
    'total' => $pdo->query("SELECT COUNT(*) FROM requerimentos")->fetchColumn(),
    'nao_lidos' => $pdo->query("SELECT COUNT(*) FROM requerimentos WHERE visualizado = 0")->fetchColumn(),
    'pendentes' => $pdo->query("SELECT COUNT(*) FROM requerimentos WHERE status = 'Pendente'")->fetchColumn(),
    'aprovados' => $pdo->query("SELECT COUNT(*) FROM requerimentos WHERE status = 'Aprovado'")->fetchColumn(),
    'finalizados' => $pdo->query("SELECT COUNT(*) FROM requerimentos WHERE status = 'Finalizado'")->fetchColumn(),
    'em_analise' => $pdo->query("SELECT COUNT(*) FROM requerimentos WHERE status = 'Em análise'")->fetchColumn(),
    'indeferidos' => $pdo->query("SELECT COUNT(*) FROM requerimentos WHERE status = 'Indeferido'")->fetchColumn(),
];
$pagamentosPendentesConclusao = (int) $pdo->query("SELECT COUNT(*) FROM requerimentos WHERE status = 'Boleto pago'")->fetchColumn();

// Listas para filtros
$tiposAlvara = $pdo->query("SELECT DISTINCT tipo_alvara FROM requerimentos ORDER BY tipo_alvara")->fetchAll();
$statusList = $pdo->query("SELECT DISTINCT status FROM requerimentos ORDER BY status")->fetchAll();

$tipoSiglas = [
    'licenca_ambiental_unica' => 'LAU',
    'habite_se' => 'HBT',
    'habite_se_simples' => 'HBS',
    'construcao' => 'CNS',
    'licenca_previa_obras' => 'LPO',
    'desmembramento' => 'DSM',
];

$statusCards = [
    ['label' => 'Todos', 'value' => (int) $estatisticas['total'], 'status' => '', 'icon' => 'fa-layer-group'],
    ['label' => 'Não vistos', 'value' => (int) $estatisticas['nao_lidos'], 'status' => null, 'unread' => true, 'icon' => 'fa-eye-slash'],
    ['label' => 'Em análise', 'value' => (int) $estatisticas['em_analise'], 'status' => 'Em análise', 'icon' => 'fa-hourglass-half'],
    ['label' => 'Pendente', 'value' => (int) $estatisticas['pendentes'], 'status' => 'Pendente', 'icon' => 'fa-clock'],
    ['label' => 'Finalizado', 'value' => (int) $estatisticas['finalizados'], 'status' => 'Finalizado', 'icon' => 'fa-check-circle'],
    ['label' => 'Indeferido', 'value' => (int) $estatisticas['indeferidos'], 'status' => 'Indeferido', 'icon' => 'fa-ban'],
];

function buildReqUrl(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            unset($params[$key]);
        }
    }
    return 'requerimentos.php' . ($params ? '?' . http_build_query($params) : '');
}

include 'header.php';
?>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.3/dist/style.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.3/dist/umd/simple-datatables.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="includes/admin-styles.css">

    <div class="admin-page-shell requerimentos-page">
        <section class="page-hero page-hero-compact">
            <div class="page-hero-copy">
                <h1 class="page-title">Requerimentos</h1>
                <p class="page-subtitle">Exibindo <?= count($requerimentos) ?> de <?= (int) $totalRequerimentos ?> processos</p>
            </div>
            <div class="page-toolbar">
                <button type="button" class="toolbar-button" onclick="window.print()">
                    <i class="fas fa-download"></i> Exportar
                </button>
                <div class="view-toggle">
                    <a href="<?= htmlspecialchars(buildReqUrl(['view' => 'list'])) ?>" class="view-toggle-item <?= $modoVisualizacao === 'list' ? 'active' : '' ?>">Lista</a>
                    <a href="<?= htmlspecialchars(buildReqUrl(['view' => 'table'])) ?>" class="view-toggle-item <?= $modoVisualizacao === 'table' ? 'active' : '' ?>">Tabela</a>
                </div>
            </div>
        </section>

        <!-- Mensagens -->
        <?php renderMensagens($mensagem, $mensagemErro); ?>

        <section class="req-summary-strip">
            <?php foreach ($statusCards as $card): ?>
                <?php
                $isUnreadCard = !empty($card['unread']);
                $isActive = $isUnreadCard
                    ? $filtroNaoVisualizados
                    : ($filtroStatus === $card['status'] || ($card['status'] === '' && $filtroStatus === '' && !$filtroNaoVisualizados));
                $summaryUrl = $isUnreadCard
                    ? buildReqUrl(['nao_visualizados' => 1, 'status' => '', 'pagina' => 1])
                    : buildReqUrl(['status' => $card['status'], 'nao_visualizados' => '', 'pagina' => 1]);
                ?>
                <a href="<?= htmlspecialchars($summaryUrl) ?>" class="summary-chip <?= $isActive ? 'active' : '' ?> <?= $isUnreadCard ? 'summary-chip-unread' : '' ?>">
                    <span><i class="fas <?= htmlspecialchars($card['icon']) ?>"></i><?= htmlspecialchars($card['label']) ?></span>
                    <strong><?= (int) $card['value'] ?></strong>
                </a>
            <?php endforeach; ?>
        </section>

        <section class="req-filter-bar">
            <form method="GET" class="req-filter-form">
                <input type="hidden" name="view" value="<?= htmlspecialchars($modoVisualizacao) ?>">
                <?php if ($filtroStatus !== ''): ?>
                    <input type="hidden" name="status" value="<?= htmlspecialchars($filtroStatus) ?>">
                <?php endif; ?>
                <?php if ($filtroNaoVisualizados): ?>
                    <input type="hidden" name="nao_visualizados" value="1">
                <?php endif; ?>
                <div class="req-filter-search">
                    <i class="fas fa-magnifying-glass"></i>
                    <input type="text" name="busca" value="<?= htmlspecialchars($filtroBusca) ?>" placeholder="Buscar por protocolo, nome ou CPF/CNPJ">
                </div>
                <label class="req-filter-label" for="tipoFiltro">Tipo:</label>
                <select id="tipoFiltro" name="tipo" class="req-filter-select">
                    <option value="">Todos</option>
                    <?php foreach ($tiposAlvara as $tipo): ?>
                        <option value="<?= htmlspecialchars($tipo['tipo_alvara']) ?>" <?= $filtroTipo === $tipo['tipo_alvara'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars(nomeAlvara($tipo['tipo_alvara'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="toolbar-button toolbar-button-primary">Aplicar</button>
                <a href="<?= htmlspecialchars(buildReqUrl(['status' => $filtroStatus, 'tipo' => '', 'busca' => '', 'pagina' => 1])) ?>" class="toolbar-button">Limpar</a>
                <?php if ($filtroNaoVisualizados): ?>
                    <a href="<?= htmlspecialchars(buildReqUrl(['nao_visualizados' => '', 'pagina' => 1])) ?>" class="toolbar-button">
                        <i class="fas fa-eye"></i> Ver todos novamente
                    </a>
                <?php endif; ?>
            </form>
            <?php if ($filtroNaoVisualizados): ?>
                <div class="active-filter-row">
                    <span class="active-filter-chip">
                        <span class="active-filter-dot"></span>
                        Mostrando apenas não visualizados
                    </span>
                </div>
            <?php endif; ?>
        </section>

        <!-- Alertas -->
        <?php renderAlertas($pagamentosPendentesConclusao); ?>

        <?php if ($modoVisualizacao === 'list'): ?>
            <section class="req-list">
                <?php if ($requerimentos): ?>
                    <?php foreach ($requerimentos as $req): ?>
                        <?php
                        $meta = [
                            'class' => match (strtolower($req['status'])) {
                                'em análise', 'em_analise' => 'status-em-analise',
                                'pendente' => 'status-pendente',
                                'finalizado' => 'status-finalizado',
                                'indeferido' => 'status-indeferido',
                                'aprovado' => 'status-aprovado',
                                default => 'status-pendente',
                            },
                        ];
                        $short = $tipoSiglas[$req['tipo_alvara']] ?? 'ALV';
                        ?>
                        <a href="visualizar_requerimento.php?id=<?= (int) $req['id'] ?>" class="req-list-item <?= $req['visualizado'] == 0 ? 'is-unread' : '' ?>">
                            <div class="req-list-main">
                                <div class="req-list-top">
                                    <span class="req-protocol">#<?= htmlspecialchars($req['protocolo']) ?></span>
                                    <span class="badge badge-status <?= htmlspecialchars($meta['class']) ?>"><?= htmlspecialchars($req['status']) ?></span>
                                    <?php if ($req['visualizado'] == 0): ?>
                                        <span class="req-unread-pill"><span class="req-unread-dot"></span>Não visto</span>
                                    <?php endif; ?>
                                </div>
                                <div class="req-name"><?= htmlspecialchars($req['requerente']) ?></div>
                                <div class="req-type-row">
                                    <span class="req-type-short"><?= htmlspecialchars($short) ?></span>
                                    <span class="req-type-name"><?= htmlspecialchars(nomeAlvara($req['tipo_alvara'])) ?></span>
                                </div>
                            </div>
                            <div class="req-list-side">
                                <div class="req-date"><?= formataDataBR($req['data_envio']) ?></div>
                                <span class="req-open-button">Abrir <i class="fas fa-arrow-right"></i></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="req-empty">
                        <i class="fas fa-search"></i>
                        <p>Nenhum requerimento encontrado.</p>
                    </div>
                <?php endif; ?>
            </section>
        <?php else: ?>
            <div class="modern-table">
                <?php renderTabela($requerimentos); ?>
            </div>
        <?php endif; ?>

        <!-- Context Menu -->
        <?php renderContextMenu(); ?>
    </div>

    <!-- Admin Scripts -->
    <script src="includes/admin-scripts.js"></script>

<?php include 'footer.php'; ?>
