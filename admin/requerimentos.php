<?php
require_once 'conexao.php';
require_once 'helpers.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../tipos_alvara.php';
verificaLogin();

require_once 'includes/alertas.php';

$categoriasDisponiveis = [
    'obras'     => ['label' => 'Obras e Construção',  'icon' => 'fa-hard-hat'],
    'ambiental' => ['label' => 'Licenças Ambientais', 'icon' => 'fa-leaf'],
    'outro'     => ['label' => 'Outros Serviços',     'icon' => 'fa-folder-open'],
];

$tiposPorCategoria = [];
foreach ($tipos_alvara as $slug => $tipo) {
    $cat = $tipo['categoria'] ?? 'outro';
    $tiposPorCategoria[$cat][] = $slug;
}

// Slugs legados que não existem mais em tipos_alvara.php (anteriores à refatoração
// de licenciamento ambiental 2026-04) mas ainda aparecem em requerimentos antigos.
$slugsLegadosPorCategoria = [
    'ambiental' => ['licenca_previa'], // antiga "LP — Licença Prévia Ambiental"
];
foreach ($slugsLegadosPorCategoria as $cat => $slugs) {
    foreach ($slugs as $slug) {
        if (!in_array($slug, $tiposPorCategoria[$cat] ?? [], true)) {
            $tiposPorCategoria[$cat][] = $slug;
        }
    }
}

function formataDataBR($data)
{
    return date('d/m/Y \à\s H:i', strtotime($data));
}

$itensPorPagina = 25;
$paginaAtual = max(1, (int) ($_GET['pagina'] ?? 1));
$offset = ($paginaAtual - 1) * $itensPorPagina;

$filtroStatus = $_GET['status'] ?? '';
$filtroTipo = $_GET['tipo'] ?? '';
$filtroCategoria = $_GET['categoria'] ?? '';
if ($filtroCategoria !== '' && !isset($tiposPorCategoria[$filtroCategoria])) {
    $filtroCategoria = '';
}
$filtroBusca = $_GET['busca'] ?? '';
$filtroNaoVisualizados = isset($_GET['nao_visualizados']) && $_GET['nao_visualizados'] === '1';

// Status encerrados: ocultos por padrão, visíveis apenas se filtro explícito ou toggle ativo
$statusEncerrados = ['Finalizado', 'Indeferido', 'Aprovado', 'Cancelado'];
$mostrarEncerrados = isset($_GET['encerrados']) && $_GET['encerrados'] === '1';
// Se filtro de status aponta para um encerrado, mostra encerrados implicitamente
if (in_array($filtroStatus, $statusEncerrados, true)) {
    $mostrarEncerrados = true;
}

$mensagem = '';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'nao_lido':
            $mensagem = "✅ Protocolo devolvido para a fila com sucesso!";
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

$sql = "SELECT r.id, r.protocolo, r.tipo_alvara, r.status, r.data_envio, r.visualizado, req.nome AS requerente
        FROM requerimentos r
        JOIN requerentes req ON r.requerente_id = req.id
        WHERE 1=1";
$sqlCount = "SELECT COUNT(*) AS total
             FROM requerimentos r
             JOIN requerentes req ON r.requerente_id = req.id
             WHERE 1=1";
$params = [];

if ($filtroStatus !== '') {
    $sql .= " AND r.status = ?";
    $sqlCount .= " AND r.status = ?";
    $params[] = $filtroStatus;
}

if ($filtroTipo !== '') {
    $sql .= " AND r.tipo_alvara = ?";
    $sqlCount .= " AND r.tipo_alvara = ?";
    $params[] = $filtroTipo;
}

if ($filtroCategoria !== '' && !empty($tiposPorCategoria[$filtroCategoria])) {
    $slugsCat = $tiposPorCategoria[$filtroCategoria];
    $placeholders = implode(',', array_fill(0, count($slugsCat), '?'));
    $sql .= " AND r.tipo_alvara IN ($placeholders)";
    $sqlCount .= " AND r.tipo_alvara IN ($placeholders)";
    foreach ($slugsCat as $s) {
        $params[] = $s;
    }
}

if ($filtroBusca !== '') {
    $sql .= " AND (r.protocolo LIKE ? OR req.nome LIKE ? OR req.cpf_cnpj LIKE ?)";
    $sqlCount .= " AND (r.protocolo LIKE ? OR req.nome LIKE ? OR req.cpf_cnpj LIKE ?)";
    $termoBusca = '%' . $filtroBusca . '%';
    $params[] = $termoBusca;
    $params[] = $termoBusca;
    $params[] = $termoBusca;
}

if ($filtroNaoVisualizados) {
    $sql .= " AND r.visualizado = 0";
    $sqlCount .= " AND r.visualizado = 0";
}

if (!$mostrarEncerrados && $filtroStatus === '') {
    $placeholdersEnc = implode(',', array_fill(0, count($statusEncerrados), '?'));
    $sql .= " AND r.status NOT IN ($placeholdersEnc)";
    $sqlCount .= " AND r.status NOT IN ($placeholdersEnc)";
    foreach ($statusEncerrados as $se) { $params[] = $se; }
}

$sql .= " ORDER BY r.visualizado ASC, r.data_envio DESC LIMIT {$itensPorPagina} OFFSET {$offset}";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requerimentos = $stmt->fetchAll();

$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$totalRequerimentos = (int) ($stmtCount->fetch()['total'] ?? 0);
$totalPaginas = max(1, (int) ceil($totalRequerimentos / $itensPorPagina));

$statusEncPH = implode(',', array_fill(0, count($statusEncerrados), '?'));
$stmtEnc = $pdo->prepare("SELECT COUNT(*) FROM requerimentos WHERE status IN ($statusEncPH)");
$stmtEnc->execute($statusEncerrados);
$totalEncerrados = (int) $stmtEnc->fetchColumn();

$estatisticas = [
    'total' => (int) $pdo->query("SELECT COUNT(*) FROM requerimentos")->fetchColumn(),
    'nao_lidos' => (int) $pdo->query("SELECT COUNT(*) FROM requerimentos WHERE visualizado = 0")->fetchColumn(),
    'pendentes' => (int) $pdo->query("SELECT COUNT(*) FROM requerimentos WHERE status = 'Pendente'")->fetchColumn(),
    'aprovados' => (int) $pdo->query("SELECT COUNT(*) FROM requerimentos WHERE status = 'Aprovado'")->fetchColumn(),
    'finalizados' => (int) $pdo->query("SELECT COUNT(*) FROM requerimentos WHERE status = 'Finalizado'")->fetchColumn(),
    'em_analise' => (int) $pdo->query("SELECT COUNT(*) FROM requerimentos WHERE status = 'Em análise'")->fetchColumn(),
    'indeferidos' => (int) $pdo->query("SELECT COUNT(*) FROM requerimentos WHERE status = 'Indeferido'")->fetchColumn(),
];
$pagamentosPendentesConclusao = (int) $pdo->query("SELECT COUNT(*) FROM requerimentos WHERE status = 'Boleto pago'")->fetchColumn();

$contagemCategorias = [];
foreach ($tiposPorCategoria as $cat => $slugs) {
    if (empty($slugs)) {
        $contagemCategorias[$cat] = 0;
        continue;
    }
    $placeholders = implode(',', array_fill(0, count($slugs), '?'));
    $stmtCat = $pdo->prepare("SELECT COUNT(*) FROM requerimentos WHERE tipo_alvara IN ($placeholders)");
    $stmtCat->execute($slugs);
    $contagemCategorias[$cat] = (int) $stmtCat->fetchColumn();
}

$tiposAlvara = $pdo->query("SELECT DISTINCT tipo_alvara FROM requerimentos ORDER BY tipo_alvara")->fetchAll();

$tipoSiglas = [
    'licenca_ambiental_unica' => 'LAU',
    'habite_se' => 'HBT',
    'habite_se_simples' => 'HBS',
    'construcao' => 'CNS',
    'licenca_previa_obras' => 'LPO',
    'desmembramento' => 'DSM',
];

$statusCards = [
    ['label' => 'Todos', 'value' => $estatisticas['total'], 'status' => '', 'icon' => 'fa-layer-group'],
    ['label' => 'Não abertos', 'value' => $estatisticas['nao_lidos'], 'status' => null, 'unread' => true, 'icon' => 'fa-eye-slash'],
    ['label' => 'Em análise', 'value' => $estatisticas['em_analise'], 'status' => 'Em análise', 'icon' => 'fa-hourglass-half'],
    ['label' => 'Pendente', 'value' => $estatisticas['pendentes'], 'status' => 'Pendente', 'icon' => 'fa-clock'],
    ['label' => 'Finalizado', 'value' => $estatisticas['finalizados'], 'status' => 'Finalizado', 'icon' => 'fa-check-circle'],
    ['label' => 'Indeferido', 'value' => $estatisticas['indeferidos'], 'status' => 'Indeferido', 'icon' => 'fa-ban'],
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

$statusOperacionais = adminStatusFluxoPrincipal();
?>
<link rel="stylesheet" href="<?= adminAssetUrl('includes/admin-styles.css') ?>">

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
        </div>
    </section>

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

    <section class="req-category-strip" aria-label="Filtros rápidos por categoria de alvará">
        <?php
        $totalCategorias = array_sum($contagemCategorias);
        $allActive = $filtroCategoria === '';
        $allUrl = buildReqUrl(['categoria' => '', 'pagina' => 1]);
        ?>
        <a href="<?= htmlspecialchars($allUrl) ?>" class="category-chip <?= $allActive ? 'active' : '' ?>">
            <span><i class="fas fa-layer-group"></i>Todas as categorias</span>
            <strong><?= (int) $totalCategorias ?></strong>
        </a>
        <?php foreach ($categoriasDisponiveis as $catSlug => $catInfo): ?>
            <?php
            $isActive = $filtroCategoria === $catSlug;
            $catUrl = buildReqUrl(['categoria' => $catSlug, 'pagina' => 1]);
            ?>
            <a href="<?= htmlspecialchars($catUrl) ?>" class="category-chip category-chip-<?= htmlspecialchars($catSlug) ?> <?= $isActive ? 'active' : '' ?>">
                <span><i class="fas <?= htmlspecialchars($catInfo['icon']) ?>"></i><?= htmlspecialchars($catInfo['label']) ?></span>
                <strong><?= (int) ($contagemCategorias[$catSlug] ?? 0) ?></strong>
            </a>
        <?php endforeach; ?>
    </section>

    <section class="req-filter-bar">
        <form method="GET" class="req-filter-form">
            <?php if ($filtroStatus !== ''): ?>
                <input type="hidden" name="status" value="<?= htmlspecialchars($filtroStatus) ?>">
            <?php endif; ?>
            <?php if ($filtroCategoria !== ''): ?>
                <input type="hidden" name="categoria" value="<?= htmlspecialchars($filtroCategoria) ?>">
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
                    <i class="fas fa-eye"></i> Ver todos
                </a>
            <?php endif; ?>
        </form>
        <!-- Toggle encerrados -->
        <div style="margin-top:8px;padding-top:8px;border-top:1px solid var(--req-line,#e5e8e6);">
        <?php if (!$mostrarEncerrados): ?>
            <a href="<?= htmlspecialchars(buildReqUrl(['encerrados' => '1', 'pagina' => 1])) ?>"
               style="display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border:1px dashed var(--req-line-strong,#ccc);border-radius:8px;font-size:.77rem;font-weight:600;color:var(--req-muted,#888);text-decoration:none;white-space:nowrap;"
               title="Exibir processos finalizados, indeferidos e arquivados">
                <i class="fas fa-eye-slash fa-xs"></i>Mostrar encerrados <span style="opacity:.7">(<?= $totalEncerrados ?>)</span>
            </a>
        <?php else: ?>
            <a href="<?= htmlspecialchars(buildReqUrl(['encerrados' => '', 'pagina' => 1])) ?>"
               style="display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border:1px solid #dde8e2;border-radius:8px;font-size:.77rem;font-weight:600;color:#5a8a6a;text-decoration:none;white-space:nowrap;">
                <i class="fas fa-eye fa-xs"></i>Ocultar encerrados
            </a>
        <?php endif; ?>
        </div>
        <?php if ($filtroNaoVisualizados): ?>
            <div class="active-filter-row">
                <span class="active-filter-chip">
                    <span class="active-filter-dot"></span>
                    Mostrando apenas protocolos ainda não abertos
                </span>
            </div>
        <?php endif; ?>
    </section>

    <?php renderAlertas($pagamentosPendentesConclusao); ?>

    <?php if ($requerimentos): ?>
        <div id="acoesMultiplas" class="bulk-actions-bar" style="display: none;">
            <div class="bulk-actions-inner">
                <div class="bulk-actions-copy">
                    <span id="contadorSelecionados" class="text-sm text-blue-800 mr-4">0 itens selecionados</span>
                    <button type="button" onclick="cancelarSelecaoMultipla()" class="text-sm text-blue-600 hover:text-blue-800">
                        <i class="fas fa-times mr-1"></i>Cancelar
                    </button>
                </div>
                <div class="bulk-actions-controls">
                    <div class="relative">
                        <button type="button" onclick="toggleDropdownStatus()" class="bulk-action-button bulk-action-button-primary">
                            <i class="fas fa-edit mr-1"></i>Alterar Status
                            <i class="fas fa-chevron-down ml-1 text-xs"></i>
                        </button>
                        <div id="dropdownStatus" class="req-inline-dropdown" style="display: none;">
                            <?php foreach ($statusOperacionais as $statusAcao): ?>
                                <button type="button" onclick="alterarStatusMultiplo('<?= htmlspecialchars($statusAcao) ?>')" class="req-inline-dropdown-item"><?= htmlspecialchars($statusAcao) ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <button type="button" onclick="confirmarExclusaoMultipla()" class="bulk-action-button bulk-action-button-danger">
                        <i class="fas fa-trash mr-1"></i>Excluir
                    </button>
                </div>
            </div>
        </div>

        <section class="req-list" data-selection-container>
            <?php foreach ($requerimentos as $req): ?>
                <?php
                $metaClass = match (strtolower($req['status'])) {
                    'em análise', 'em_analise' => 'status-em-analise',
                    'pendente' => 'status-pendente',
                    'finalizado' => 'status-finalizado',
                    'indeferido' => 'status-indeferido',
                    'aprovado' => 'status-aprovado',
                    'reprovado' => 'status-reprovado',
                    'aguardando fiscalização', 'aguardando fiscalizacao' => 'status-aguardando-fiscalizacao',
                    'apto a gerar alvará', 'apto a gerar alvara' => 'status-apto-a-gerar-alvara',
                    'alvará emitido', 'alvara emitido' => 'status-alvara-emitido',
                    'aguardando boleto' => 'status-aguardando-boleto',
                    'boleto pago' => 'status-boleto-pago',
                    'cancelado' => 'status-cancelado',
                    default => 'status-pendente',
                };
                $short = $tipoSiglas[$req['tipo_alvara']] ?? 'ALV';
                ?>
                <article class="req-list-item <?= $req['visualizado'] == 0 ? 'is-unread' : '' ?>" data-id="<?= (int) $req['id'] ?>">
                    <div class="req-list-check">
                        <input
                            type="checkbox"
                            class="checkbox-selecao"
                            data-id="<?= (int) $req['id'] ?>"
                            onchange="updateContadorSelecionados()"
                            style="display: none;"
                        >
                    </div>

                    <button type="button" class="req-list-main" onclick="abrirRequerimento(<?= (int) $req['id'] ?>)">
                        <div class="req-list-top">
                            <span class="req-protocol">#<?= htmlspecialchars($req['protocolo']) ?></span>
                            <span class="badge badge-status <?= htmlspecialchars($metaClass) ?>"><?= htmlspecialchars($req['status']) ?></span>
                            <?php if ($req['visualizado'] == 0): ?>
                                <span class="req-unread-pill"><span class="req-unread-dot"></span>Não aberto</span>
                            <?php endif; ?>
                        </div>
                        <div class="req-name"><?= htmlspecialchars($req['requerente']) ?></div>
                        <div class="req-type-row">
                            <span class="req-type-short"><?= htmlspecialchars($short) ?></span>
                            <span class="req-type-name"><?= htmlspecialchars(nomeAlvara($req['tipo_alvara'])) ?></span>
                        </div>
                    </button>

                    <div class="req-list-side">
                        <div class="req-date"><?= formataDataBR($req['data_envio']) ?></div>
                        <details class="req-actions-menu">
                            <summary class="req-open-button" onclick="event.stopPropagation();">
                                Ações <i class="fas fa-ellipsis-vertical"></i>
                            </summary>
                            <div class="req-actions-dropdown">
                                <button type="button" class="req-actions-item" onclick="event.stopPropagation(); abrirRequerimento(<?= (int) $req['id'] ?>);">
                                    <i class="fas fa-eye"></i>Ver
                                </button>
                                <div class="req-actions-submenu">
                                    <button type="button" class="req-actions-item" onclick="event.stopPropagation();">
                                        <i class="fas fa-pen"></i>Alterar status
                                    </button>
                                    <div class="req-actions-submenu-panel">
                                        <?php foreach ($statusOperacionais as $statusAcao): ?>
                                            <button type="button" class="req-actions-item" onclick="event.stopPropagation(); alterarStatusUnico(<?= (int) $req['id'] ?>, '<?= htmlspecialchars($statusAcao) ?>');">
                                                <?= htmlspecialchars($statusAcao) ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php if ($req['visualizado'] == 0): ?>
                                <button type="button" class="req-actions-item" onclick="event.stopPropagation(); marcarComoLidoUnico(<?= (int) $req['id'] ?>);">
                                    <i class="fas fa-envelope-open"></i>Marcar como aberto
                                </button>
                                <?php else: ?>
                                <button type="button" class="req-actions-item" onclick="event.stopPropagation(); marcarComoNaoLidoUnico(<?= (int) $req['id'] ?>);">
                                    <i class="fas fa-envelope"></i>Marcar como não lido
                                </button>
                                <?php endif; ?>
                                <button type="button" class="req-actions-item" onclick="event.stopPropagation(); ativarModoSelecao(); toggleCheckboxById(<?= (int) $req['id'] ?>);">
                                    <i class="fas fa-check-double"></i>Selecionar múltiplos
                                </button>
                                <button type="button" class="req-actions-item req-actions-item-danger" onclick="event.stopPropagation(); confirmarExclusaoUnica(<?= (int) $req['id'] ?>);">
                                    <i class="fas fa-trash"></i>Excluir
                                </button>
                            </div>
                        </details>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="req-pagination">
            <div class="req-pagination-copy">
                Página <?= (int) $paginaAtual ?> de <?= (int) $totalPaginas ?> · <?= (int) $totalRequerimentos ?> processo(s)
            </div>
            <div class="req-pagination-links">
                <?php if ($paginaAtual > 1): ?>
                    <a href="<?= htmlspecialchars(buildReqUrl(['pagina' => 1])) ?>" class="req-page-link">«</a>
                    <a href="<?= htmlspecialchars(buildReqUrl(['pagina' => $paginaAtual - 1])) ?>" class="req-page-link">‹</a>
                <?php endif; ?>
                <?php
                $inicio = max(1, $paginaAtual - 2);
                $fim = min($totalPaginas, $paginaAtual + 2);
                for ($i = $inicio; $i <= $fim; $i++):
                ?>
                    <a href="<?= htmlspecialchars(buildReqUrl(['pagina' => $i])) ?>" class="req-page-link <?= $i === $paginaAtual ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($paginaAtual < $totalPaginas): ?>
                    <a href="<?= htmlspecialchars(buildReqUrl(['pagina' => $paginaAtual + 1])) ?>" class="req-page-link">›</a>
                    <a href="<?= htmlspecialchars(buildReqUrl(['pagina' => $totalPaginas])) ?>" class="req-page-link">»</a>
                <?php endif; ?>
            </div>
        </section>
    <?php else: ?>
        <div class="req-empty">
            <i class="fas fa-search"></i>
            <p>Nenhum requerimento encontrado.</p>
        </div>
    <?php endif; ?>
</div>

<script src="<?= adminAssetUrl('includes/admin-scripts.js') ?>"></script>
<?php include 'footer.php'; ?>
