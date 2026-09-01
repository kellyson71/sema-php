<?php
require_once 'conexao.php';
require_once 'helpers.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/denuncia_filters.php';
require_once __DIR__ . '/../tipos_alvara.php';
verificaLogin();

// Fiscal (setor2) e secretário (setor3) enxergam apenas seu setor
$nivelAdmin = $_SESSION['admin_nivel'] ?? '';
$setorFiltro = match($nivelAdmin) {
    'fiscal'     => 'setor2',
    'secretario' => 'setor3',
    default      => null,
};

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
$filtroAcao = $_GET['acao'] ?? '';
if ($filtroCategoria !== '' && !isset($tiposPorCategoria[$filtroCategoria])) {
    $filtroCategoria = '';
}
$filtroBusca = $_GET['busca'] ?? '';
$filtroNaoVisualizados = isset($_GET['nao_visualizados']) && $_GET['nao_visualizados'] === '1';
$filtroEmail = $_GET['email_enviado'] ?? '';
if (!in_array($filtroEmail, ['1', '0'], true)) {
    $filtroEmail = '';
}
$filtroFonte = $_GET['fonte'] ?? 'todos';
if (!in_array($filtroFonte, ['todos', 'requerimentos', 'denuncias'], true)) {
    $filtroFonte = 'todos';
}

// Estes filtros não possuem equivalente numa denúncia. Ao utilizá-los, a
// própria consulta passa a exibir apenas requerimentos, sem combinações
// ambíguas ou resultados que pareçam ignorar o filtro escolhido.
$filtroExclusivoRequerimento = $filtroTipo !== '' || $filtroCategoria !== '' || $filtroAcao !== ''
    || $filtroNaoVisualizados || $filtroEmail !== '';
if ($filtroExclusivoRequerimento) {
    $filtroFonte = 'requerimentos';
}

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

$whereReq = ['1=1'];
$paramsReq = [];

// Com busca ativa (protocolo/nome/CPF), ignora a trava de setor: o usuário precisa
// localizar o processo mesmo que ele ainda não tenha chegado ao seu setor.
if ($setorFiltro && $filtroBusca === '') {
    $whereReq[] = 'r.setor_atual = ?';
    $paramsReq[] = $setorFiltro;
}

if ($filtroStatus !== '') {
    $statusNormalizadoReq = normalizarStatusProcesso($filtroStatus);
    if ($statusNormalizadoReq === 'em_analise') {
        $whereReq[] = "LOWER(TRIM(r.status)) IN ('em análise','em analise','em_analise')";
    } elseif ($statusNormalizadoReq === 'pendente') {
        $whereReq[] = "LOWER(TRIM(r.status)) = 'pendente'";
    } else {
        $whereReq[] = 'r.status = ?';
        $paramsReq[] = $filtroStatus;
    }
}

if ($filtroTipo !== '') {
    $whereReq[] = 'r.tipo_alvara = ?';
    $paramsReq[] = $filtroTipo;
}

if ($filtroCategoria !== '' && !empty($tiposPorCategoria[$filtroCategoria])) {
    $slugsCat = $tiposPorCategoria[$filtroCategoria];
    $placeholders = implode(',', array_fill(0, count($slugsCat), '?'));
    $whereReq[] = "r.tipo_alvara IN ($placeholders)";
    foreach ($slugsCat as $s) {
        $paramsReq[] = $s;
    }
}

if ($filtroBusca !== '') {
    $whereReq[] = '(r.protocolo LIKE ? OR req.nome LIKE ? OR req.cpf_cnpj LIKE ?)';
    $termoBusca = '%' . $filtroBusca . '%';
    array_push($paramsReq, $termoBusca, $termoBusca, $termoBusca);
}

if ($filtroAcao !== '') {
    $whereReq[] = 'r.aguardando_acao = ?';
    $paramsReq[] = $filtroAcao;
}

if ($filtroNaoVisualizados) {
    $whereReq[] = 'r.visualizado = 0';
}

if ($filtroEmail !== '') {
    // Só conta email enviado manualmente por um admin (usuario_envio != 'Sistema') — a
    // confirmação automática do requerimento sai pra todo mundo, então incluí-la aqui
    // tornaria o filtro inútil (praticamente tudo teria "já enviado").
    $existeEmail = "EXISTS (SELECT 1 FROM email_logs el WHERE el.requerimento_id = r.id AND el.status = 'SUCESSO' AND el.eh_teste = 0 AND el.usuario_envio <> 'Sistema')";
    $whereReq[] = $filtroEmail === '1' ? $existeEmail : "NOT $existeEmail";
}

// Para fiscal/secretário (visão travada num setor), "encerrado" não se aplica:
// o processo acabou de chegar ao setor deles, não é um processo arquivado.
if (!$setorFiltro && !$mostrarEncerrados && $filtroStatus === '' && $filtroBusca === '') {
    $placeholdersEnc = implode(',', array_fill(0, count($statusEncerrados), '?'));
    $whereReq[] = "r.status NOT IN ($placeholdersEnc)";
    foreach ($statusEncerrados as $se) { $paramsReq[] = $se; }
}

$selectReq = "SELECT 'requerimento' AS tipo_registro, r.id, r.protocolo,
    req.nome AS titulo, r.status, r.setor_atual AS setor, 'cidadao' AS origem,
    0 AS anonimo, r.data_envio AS data_processo, r.tipo_alvara,
    r.setor_atual, r.aguardando_acao, r.visualizado, r.motivo_devolucao,
    r.devolvido_por, (SELECT a.nivel FROM administradores a WHERE a.id = r.devolvido_por) AS devolvido_por_nivel,
    NULL AS tipo_denuncia, r.especificacao, r.endereco_objetivo, r.responsavel_tecnico_nome
    FROM requerimentos r JOIN requerentes req ON r.requerente_id = req.id
    WHERE " . implode(' AND ', $whereReq);

$feedParts = [];
$params = [];
if ($filtroFonte !== 'denuncias') {
    $feedParts[] = $selectReq;
    $params = array_merge($params, $paramsReq);
}

if ($filtroFonte !== 'requerimentos') {
    $whereDen = ["LOWER(TRIM(d.status)) NOT IN ('concluída','concluida','concluído','concluido','finalizado','finalizada')"];
    $paramsDen = [];
    if ($filtroBusca !== '') {
        $whereDen[] = '(d.protocolo_publico LIKE ? OR d.infrator_nome LIKE ? OR d.infrator_cpf_cnpj LIKE ? OR d.observacoes LIKE ?)';
        $termDen = '%' . $filtroBusca . '%';
        array_push($paramsDen, $termDen, $termDen, $termDen, $termDen);
    }
    if ($filtroStatus !== '') {
        $normalizado = normalizarStatusProcesso($filtroStatus);
        if ($normalizado === 'pendente') {
            $whereDen[] = "LOWER(TRIM(d.status)) = 'pendente'";
        } elseif ($normalizado === 'em_analise') {
            $whereDen[] = "LOWER(TRIM(d.status)) IN ('em análise','em analise','em_analise')";
        } else {
            // Denúncias concluídas não integram o feed principal.
            $whereDen[] = '1=0';
        }
    }
    $setorDenunciaInicial = setorAdministrador($pdo, (int) ($_SESSION['admin_id'] ?? 0));
    if ($setorDenunciaInicial !== 'ambos') {
        $whereDen[] = 'd.setor = ?';
        $paramsDen[] = $setorDenunciaInicial;
    }

    $feedParts[] = "SELECT 'denuncia' AS tipo_registro, d.id,
        COALESCE(NULLIF(d.protocolo_publico,''), CONCAT('DEN-', LPAD(d.id, 6, '0'))) AS protocolo,
        CASE WHEN NULLIF(TRIM(d.infrator_nome),'') IS NOT NULL AND LOWER(TRIM(d.infrator_nome)) NOT IN ('não informado','nao informado') THEN d.infrator_nome
             WHEN d.anonimo = 1 THEN 'Denúncia anônima' ELSE 'Infrator não identificado' END AS titulo,
        d.status, d.setor, d.origem, d.anonimo, d.data_registro AS data_processo,
        NULL AS tipo_alvara, NULL AS setor_atual, NULL AS aguardando_acao, 1 AS visualizado,
        NULL AS motivo_devolucao, NULL AS devolvido_por, NULL AS devolvido_por_nivel,
        d.tipo_denuncia, NULL AS especificacao, NULL AS endereco_objetivo, NULL AS responsavel_tecnico_nome
        FROM denuncias d WHERE " . implode(' AND ', $whereDen);
    $params = array_merge($params, $paramsDen);
}

$feedSql = implode(' UNION ALL ', $feedParts);
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM ({$feedSql}) feed_count");
$stmtCount->execute($params);
$totalRequerimentos = (int) $stmtCount->fetchColumn();
$totalPaginas = max(1, (int) ceil($totalRequerimentos / $itensPorPagina));
$paginaAtual = min($paginaAtual, $totalPaginas);
$offset = ($paginaAtual - 1) * $itensPorPagina;

$stmt = $pdo->prepare("SELECT * FROM ({$feedSql}) feed ORDER BY data_processo DESC, tipo_registro DESC, id DESC LIMIT {$itensPorPagina} OFFSET {$offset}");
$stmt->execute($params);
$requerimentos = $stmt->fetchAll();
$temRequerimentosNaPagina = count(array_filter($requerimentos, static fn($item) => $item['tipo_registro'] === 'requerimento')) > 0;

$statusEncPH = implode(',', array_fill(0, count($statusEncerrados), '?'));

if ($setorFiltro) {
    $stmtEnc = $pdo->prepare("SELECT COUNT(*) FROM requerimentos WHERE setor_atual = ? AND status IN ($statusEncPH)");
    $stmtEnc->execute(array_merge([$setorFiltro], $statusEncerrados));
} else {
    $stmtEnc = $pdo->prepare("SELECT COUNT(*) FROM requerimentos WHERE status IN ($statusEncPH)");
    $stmtEnc->execute($statusEncerrados);
}
$totalEncerrados = (int) $stmtEnc->fetchColumn();

// Estatísticas — usa prepared statements para evitar interpolação direta de $setorFiltro
function contarReq(PDO $pdo, ?string $setor, string $extraWhere = '1=1', array $extraParams = []): int {
    $where = $setor ? "setor_atual = ? AND $extraWhere" : $extraWhere;
    $params = $setor ? array_merge([$setor], $extraParams) : $extraParams;
    $st = $pdo->prepare("SELECT COUNT(*) FROM requerimentos WHERE $where");
    $st->execute($params);
    return (int) $st->fetchColumn();
}

if ($setorFiltro) {
    // Cards focados na fila do setor (fiscal / secretário)
    $acaoFila = $setorFiltro === 'setor2' ? 'analise_setor2' : 'revisao_setor3';
    $retornosSetor2 = $setorFiltro === 'setor2'
        ? contarReq($pdo, 'setor2', "(aguardando_acao = ? OR aguardando_acao = ?)", ['retorno_aprovado', 'retorno_recusado'])
        : 0;
    $estatisticas = [
        'na_fila'     => contarReq($pdo, $setorFiltro, "aguardando_acao = ?", [$acaoFila]),
        'retornos'    => $retornosSetor2,
        'em_processo' => contarReq($pdo, $setorFiltro, "aguardando_acao = ?", ['revisao_setor3']),
        'concluidos'  => contarReq($pdo, $setorFiltro, "aguardando_acao = ?", ['concluido']),
        'total'       => contarReq($pdo, $setorFiltro),
    ];
    $statusCards = [
        ['label' => 'Na fila',        'value' => $estatisticas['na_fila'],     'acao' => $acaoFila,          'icon' => 'fa-inbox'],
        ['label' => 'Em processo',    'value' => $estatisticas['em_processo'], 'acao' => 'revisao_setor3',   'icon' => 'fa-hourglass-half'],
        ['label' => 'Concluídos',     'value' => $estatisticas['concluidos'],  'acao' => 'concluido',         'icon' => 'fa-check-circle'],
        ['label' => 'Total recebido', 'value' => $estatisticas['total'],       'acao' => '',                  'icon' => 'fa-layer-group'],
    ];
    if ($setorFiltro === 'setor2' && $retornosSetor2 > 0) {
        array_unshift($statusCards, [
            'label' => 'Retorno do Secretário',
            'value' => $retornosSetor2,
            'acao'  => 'retorno_aprovado',
            'icon'  => 'fa-rotate-left',
            'destaque' => true,
        ]);
    }
} else {
    $estatisticas = [
        'total'      => contarReq($pdo, null),
        'nao_lidos'  => contarReq($pdo, null, "visualizado = 0"),
        'pendentes'  => contarReq($pdo, null, "status = ?", ['Pendente']),
        'aprovados'  => contarReq($pdo, null, "status = ?", ['Aprovado']),
        'finalizados'=> contarReq($pdo, null, "status = ?", ['Finalizado']),
        'em_analise' => contarReq($pdo, null, "status = ?", ['Em análise']),
        'indeferidos'=> contarReq($pdo, null, "status = ?", ['Indeferido']),
    ];

    // Na visão unificada, os três indicadores compartilhados refletem os dois
    // tipos de processo. Indicadores sem equivalente em denúncias continuam
    // contando apenas requerimentos.
    $setorDenStats = setorAdministrador($pdo, (int) ($_SESSION['admin_id'] ?? 0));
    $whereSetorDenStats = $setorDenStats === 'ambos' ? '' : ' AND setor = ?';
    $paramsSetorDenStats = $setorDenStats === 'ambos' ? [] : [$setorDenStats];
    $stmtDenStats = $pdo->prepare("SELECT
        SUM(CASE WHEN LOWER(TRIM(status)) NOT IN ('concluída','concluida','concluído','concluido','finalizado','finalizada') THEN 1 ELSE 0 END) AS total,
        SUM(CASE WHEN LOWER(TRIM(status)) = 'pendente' THEN 1 ELSE 0 END) AS pendentes,
        SUM(CASE WHEN LOWER(TRIM(status)) IN ('em análise','em analise','em_analise') THEN 1 ELSE 0 END) AS em_analise
        FROM denuncias WHERE 1=1{$whereSetorDenStats}");
    $stmtDenStats->execute($paramsSetorDenStats);
    $statsDenFeed = $stmtDenStats->fetch() ?: ['total' => 0, 'pendentes' => 0, 'em_analise' => 0];

    if ($filtroFonte === 'denuncias') {
        $estatisticas = array_merge($estatisticas, [
            'total' => (int) ($statsDenFeed['total'] ?? 0),
            'nao_lidos' => 0,
            'pendentes' => (int) ($statsDenFeed['pendentes'] ?? 0),
            'em_analise' => (int) ($statsDenFeed['em_analise'] ?? 0),
            'aprovados' => 0,
            'finalizados' => 0,
            'indeferidos' => 0,
        ]);
    } elseif ($filtroFonte === 'todos') {
        $estatisticas['total'] += (int) ($statsDenFeed['total'] ?? 0);
        $estatisticas['pendentes'] += (int) ($statsDenFeed['pendentes'] ?? 0);
        $estatisticas['em_analise'] += (int) ($statsDenFeed['em_analise'] ?? 0);
    }
    $statusCards = [
        ['label' => 'Todos',       'value' => $estatisticas['total'],      'status' => '',           'icon' => 'fa-layer-group'],
        ['label' => 'Não abertos', 'value' => $estatisticas['nao_lidos'],  'status' => null,         'unread' => true, 'icon' => 'fa-eye-slash'],
        ['label' => 'Em análise',  'value' => $estatisticas['em_analise'], 'status' => 'Em análise', 'icon' => 'fa-hourglass-half'],
        ['label' => 'Pendente',    'value' => $estatisticas['pendentes'],  'status' => 'Pendente',   'icon' => 'fa-clock'],
        ['label' => 'Aprovado',    'value' => $estatisticas['aprovados'],  'status' => 'Aprovado',   'icon' => 'fa-circle-check'],
        ['label' => 'Finalizado',  'value' => $estatisticas['finalizados'],'status' => 'Finalizado', 'icon' => 'fa-check-circle'],
        ['label' => 'Indeferido',  'value' => $estatisticas['indeferidos'],'status' => 'Indeferido', 'icon' => 'fa-ban'],
    ];
}
$pagamentosPendentesConclusao = contarReq($pdo, $setorFiltro, "status = ?", ['Boleto pago']);

$contagemCategorias = [];
foreach ($tiposPorCategoria as $cat => $slugs) {
    if (empty($slugs)) {
        $contagemCategorias[$cat] = 0;
        continue;
    }
    $placeholders = implode(',', array_fill(0, count($slugs), '?'));
    $args = $setorFiltro ? array_merge([$setorFiltro], $slugs) : $slugs;
    $setorCond = $setorFiltro ? "setor_atual = ? AND " : '';
    $stmtCat = $pdo->prepare("SELECT COUNT(*) FROM requerimentos WHERE {$setorCond}tipo_alvara IN ($placeholders)");
    $stmtCat->execute($args);
    $contagemCategorias[$cat] = (int) $stmtCat->fetchColumn();
}

if ($setorFiltro) {
    $stmtTipos = $pdo->prepare("SELECT DISTINCT tipo_alvara FROM requerimentos WHERE setor_atual = ? ORDER BY tipo_alvara");
    $stmtTipos->execute([$setorFiltro]);
    $tiposAlvara = $stmtTipos->fetchAll();
} else {
    $tiposAlvara = $pdo->query("SELECT DISTINCT tipo_alvara FROM requerimentos ORDER BY tipo_alvara")->fetchAll();
}

$tipoSiglas = [
    'licenca_ambiental_unica' => 'LAU',
    'habite_se' => 'HBT',
    'habite_se_simples' => 'HBS',
    'construcao' => 'CNS',
    'licenca_previa_obras' => 'LPO',
    'desmembramento' => 'DSM',
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
<style>
/* Pills de aguardando_acao — reusados da fila_setor.php */
.acao-triagem          { background:#e8effd; color:#3762d9; }
.acao-boleto           { background:#fff3dc; color:#b7791f; }
.acao-analise          { background:#e3f3e8; color:#14532d; }
.acao-revisao          { background:#f3e8ff; color:#7e22ce; }
.acao-envio            { background:#e0f2fe; color:#0369a1; }
.acao-concluido        { background:#f1f5f0; color:#666; }
.acao-retorno-aprovado { background:#f1f5f2; color:#1e3a28; border:1px solid #b8cfc0; font-weight:600; letter-spacing:.01em; }
.acao-retorno-recusado { background:#f5f1f1; color:#3a1e1e; border:1px solid #cfb8b8; font-weight:600; letter-spacing:.01em; }

/* Destaque sutil — apenas borda esquerda, sem fundo berrante */
.req-list-item.retorno-aprovado { border-left:3px solid #3d7a56; }
.req-list-item.retorno-recusado { border-left:3px solid #8b3a3a; }

.badge-retorno-aprovado {
    background:#f1f5f2;
    color:#1e3a28;
    border:1px solid #b8cfc0;
    font-size:.68rem; font-weight:600; letter-spacing:.03em;
    padding:2px 8px; border-radius:4px;
    display:inline-flex; align-items:center; gap:5px;
    text-transform:uppercase;
}
.badge-retorno-recusado {
    background:#f5f1f1;
    color:#3a1e1e;
    border:1px solid #cfb8b8;
    font-size:.68rem; font-weight:600; letter-spacing:.03em;
    padding:2px 8px; border-radius:4px;
    display:inline-flex; align-items:center; gap:5px;
    text-transform:uppercase; cursor:help;
}
.badge-devolvido-sec {
    background:#f5f3ee;
    color:#5c4a1e;
    border:1px solid #d4c5a0;
    font-size:.68rem;
    font-weight:600; letter-spacing:.03em;
    padding:2px 8px;
    border-radius:4px;
    display:inline-flex;
    align-items:center; gap:5px;
    text-transform:uppercase; cursor:help;
}
.feed-source-strip { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:14px; }
.feed-source-chip { display:inline-flex; align-items:center; gap:7px; padding:8px 13px; border:1px solid var(--line); border-radius:999px; background:#fff; color:var(--muted); text-decoration:none; font-size:.78rem; font-weight:800; }
.feed-source-chip:hover,.feed-source-chip.active { color:var(--primary); border-color:#9fbea9; background:#f1f7f3; }
.req-list-item.feed-denuncia { border-left:4px solid #a9534d; }
.req-list-item.feed-denuncia.obras { border-left-color:#c98b2e; }
.feed-type-badge { display:inline-flex; align-items:center; gap:5px; padding:3px 8px; border-radius:999px; background:#f6e9e8; color:#913f39; font-size:.66rem; font-weight:800; letter-spacing:.04em; text-transform:uppercase; }
.feed-type-badge.requerimento { background:#eaf2ed; color:#245b38; }
.feed-anon-badge { display:inline-flex; align-items:center; gap:5px; padding:3px 8px; border-radius:999px; background:#302b36; color:#fff; font-size:.66rem; font-weight:800; }
</style>

<?php
$filaLabels = [
    'setor2' => ['titulo' => 'Fila da Fiscalização de Obras', 'icon' => 'fa-helmet-safety', 'cor' => '#14532d', 'bg' => '#f0fdf4'],
    'setor3' => ['titulo' => 'Fila de Revisão do Secretário',  'icon' => 'fa-shield-halved',  'cor' => '#7e22ce', 'bg' => '#faf5ff'],
];
$filaInfo = $setorFiltro ? ($filaLabels[$setorFiltro] ?? null) : null;

// Rótulos curtos por setor, usados no selo de "processo de outro setor" nos resultados de busca
$setorLabelsCurto = [
    'setor1' => 'Setor 1 — Triagem',
    'setor2' => 'Setor 2 — Fiscalização',
    'setor3' => 'Setor 3 — Secretário',
];
$buscaCruzaSetor = $setorFiltro && $filtroBusca !== '';
?>
<div class="admin-page-shell requerimentos-page">
    <section class="page-hero page-hero-compact">
        <div class="page-hero-copy">
            <?php if ($filaInfo): ?>
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:2px;">
                    <span style="width:32px;height:32px;border-radius:9px;background:<?= $filaInfo['bg'] ?>;border:1.5px solid <?= $filaInfo['cor'] ?>33;display:inline-flex;align-items:center;justify-content:center;color:<?= $filaInfo['cor'] ?>;flex-shrink:0;">
                        <i class="fas <?= $filaInfo['icon'] ?>" style="font-size:.85rem;"></i>
                    </span>
                    <h1 class="page-title" style="color:<?= $filaInfo['cor'] ?>;"><?= $filaInfo['titulo'] ?></h1>
                </div>
                <p class="page-subtitle">
                    <?= $estatisticas['na_fila'] ?> aguardando ação · <?= (int) $totalRequerimentos ?> processo(s) no setor
                </p>
            <?php else: ?>
                <h1 class="page-title">Requerimentos</h1>
                <p class="page-subtitle">Exibindo <?= count($requerimentos) ?> de <?= (int) $totalRequerimentos ?> processos</p>
            <?php endif; ?>
        </div>
        <div class="page-toolbar">
            <a href="<?= htmlspecialchars('exportar_requerimentos.php?' . http_build_query(array_filter($_GET, fn($v) => $v !== ''))) ?>" class="toolbar-button">
                <i class="fas fa-file-csv"></i> Exportar planilha
            </a>
        </div>
    </section>

    <?php renderMensagens($mensagem, $mensagemErro); ?>

    <nav class="feed-source-strip" aria-label="Tipo de processo">
        <a href="<?= htmlspecialchars(buildReqUrl(['fonte' => 'todos', 'pagina' => 1])) ?>" class="feed-source-chip <?= $filtroFonte === 'todos' ? 'active' : '' ?>"><i class="fas fa-layer-group"></i>Todos</a>
        <a href="<?= htmlspecialchars(buildReqUrl(['fonte' => 'requerimentos', 'pagina' => 1])) ?>" class="feed-source-chip <?= $filtroFonte === 'requerimentos' ? 'active' : '' ?>"><i class="fas fa-clipboard-list"></i>Requerimentos</a>
        <a href="<?= htmlspecialchars(buildReqUrl(['fonte' => 'denuncias', 'tipo' => '', 'categoria' => '', 'acao' => '', 'nao_visualizados' => '', 'email_enviado' => '', 'pagina' => 1])) ?>" class="feed-source-chip <?= $filtroFonte === 'denuncias' ? 'active' : '' ?>"><i class="fas fa-bullhorn"></i>Denúncias</a>
        <?php if ($filtroExclusivoRequerimento): ?><span class="feed-source-chip" title="O filtro atual existe somente em requerimentos"><i class="fas fa-circle-info"></i>Visão limitada por filtro exclusivo</span><?php endif; ?>
    </nav>

    <section class="req-summary-strip">
        <?php foreach ($statusCards as $card): ?>
            <?php
            $isDestaque = !empty($card['destaque']);
            if ($setorFiltro) {
                // Cards de setor: filtram por aguardando_acao quando possível
                $isActive = !empty($card['acao']) && ($filtroAcao ?? '') === $card['acao'];
                $summaryUrl = !empty($card['acao'])
                    ? buildReqUrl(['acao' => $card['acao'], 'pagina' => 1])
                    : buildReqUrl(['acao' => '', 'pagina' => 1]);
            } else {
                $isUnreadCard = !empty($card['unread']);
                $isActive = $isUnreadCard
                    ? $filtroNaoVisualizados
                    : ($filtroStatus === ($card['status'] ?? '') || (($card['status'] ?? '') === '' && $filtroStatus === '' && !$filtroNaoVisualizados));
                $summaryUrl = $isUnreadCard
                    ? buildReqUrl(['nao_visualizados' => 1, 'status' => '', 'pagina' => 1])
                    : buildReqUrl(['status' => $card['status'] ?? '', 'nao_visualizados' => '', 'pagina' => 1]);
            }
            ?>
            <a href="<?= htmlspecialchars($summaryUrl) ?>"
               class="summary-chip <?= $isActive ? 'active' : '' ?> <?= !empty($card['unread']) ? 'summary-chip-unread' : '' ?>"
               <?= $isDestaque ? 'style="background:#f1f5f2;border-color:#b8cfc0;color:#1e3a28;font-weight:600;letter-spacing:.01em;"' : '' ?>>
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
                <?php if ($filtroCategoria !== ''): ?>
                    <?php foreach ($tiposAlvara as $tipo):
                        $catDoTipo = $tipos_alvara[$tipo['tipo_alvara']]['categoria'] ?? null;
                        if ($catDoTipo !== $filtroCategoria) continue;
                    ?>
                        <option value="<?= htmlspecialchars($tipo['tipo_alvara']) ?>" <?= $filtroTipo === $tipo['tipo_alvara'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars(nomeAlvara($tipo['tipo_alvara'])) ?>
                        </option>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php
                    $tiposPorCatOrdenados = [];
                    foreach ($tiposAlvara as $tipo) {
                        $cat = $tipos_alvara[$tipo['tipo_alvara']]['categoria'] ?? 'outro';
                        $tiposPorCatOrdenados[$cat][] = $tipo;
                    }
                    foreach ($categoriasDisponiveis as $catSlug => $catInfo):
                        if (empty($tiposPorCatOrdenados[$catSlug])) continue;
                    ?>
                        <optgroup label="<?= htmlspecialchars($catInfo['label']) ?>">
                            <?php foreach ($tiposPorCatOrdenados[$catSlug] as $tipo): ?>
                                <option value="<?= htmlspecialchars($tipo['tipo_alvara']) ?>" <?= $filtroTipo === $tipo['tipo_alvara'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(nomeAlvara($tipo['tipo_alvara'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                    <?php
                    // Tipos sem categoria mapeada nas categoriasDisponiveis
                    $tiposRestantes = $tiposPorCatOrdenados[''] ?? [];
                    foreach ($tiposRestantes as $tipo): ?>
                        <option value="<?= htmlspecialchars($tipo['tipo_alvara']) ?>" <?= $filtroTipo === $tipo['tipo_alvara'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars(nomeAlvara($tipo['tipo_alvara'])) ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
            <label class="req-filter-label" for="emailFiltro">Email:</label>
            <select id="emailFiltro" name="email_enviado" class="req-filter-select">
                <option value="">Todos</option>
                <option value="1" <?= $filtroEmail === '1' ? 'selected' : '' ?>>Já enviado</option>
                <option value="0" <?= $filtroEmail === '0' ? 'selected' : '' ?>>Não enviado</option>
            </select>
            <button type="submit" class="toolbar-button toolbar-button-primary">Aplicar</button>
            <a href="<?= htmlspecialchars(buildReqUrl(['status' => $filtroStatus, 'tipo' => '', 'busca' => '', 'email_enviado' => '', 'pagina' => 1])) ?>" class="toolbar-button">Limpar</a>
            <?php if ($filtroNaoVisualizados): ?>
                <a href="<?= htmlspecialchars(buildReqUrl(['nao_visualizados' => '', 'pagina' => 1])) ?>" class="toolbar-button">
                    <i class="fas fa-eye"></i> Ver todos
                </a>
            <?php endif; ?>
            <?php /* Encerrados na mesma linha dos outros filtros: sozinho numa faixa
                     com borda no topo, custava ~50px de altura para um único link. */ ?>
            <?php if (!$setorFiltro): ?>
                <?php if (!$mostrarEncerrados): ?>
                    <a href="<?= htmlspecialchars(buildReqUrl(['encerrados' => '1', 'pagina' => 1])) ?>"
                       class="toolbar-button toolbar-button-ghost"
                       title="Exibir processos finalizados, indeferidos e arquivados">
                        <i class="fas fa-eye-slash fa-xs"></i>Encerrados <span style="opacity:.7">(<?= $totalEncerrados ?>)</span>
                    </a>
                <?php else: ?>
                    <a href="<?= htmlspecialchars(buildReqUrl(['encerrados' => '', 'pagina' => 1])) ?>"
                       class="toolbar-button toolbar-button-ghost is-on">
                        <i class="fas fa-eye fa-xs"></i>Ocultar encerrados
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </form>
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
        <?php if ($temRequerimentosNaPagina): ?>
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
        <?php endif; ?>

        <section class="req-list" data-selection-container>
            <?php foreach ($requerimentos as $req): ?>
                <?php if ($req['tipo_registro'] === 'denuncia'):
                    $tiposOcorrencia = tiposDenuncia($req);
                    $subtituloDenuncia = implode(' · ', $tiposOcorrencia);
                    $ehObrasDenuncia = ($req['setor'] ?? '') === 'obras_urbanismo';
                    $statusDenunciaClass = match (normalizarStatusProcesso((string) $req['status'])) {
                        'em_analise' => 'status-em-analise',
                        default => 'status-pendente',
                    };
                ?>
                    <article class="req-list-item feed-denuncia <?= $ehObrasDenuncia ? 'obras' : '' ?>" data-record-type="denuncia" data-id="<?= (int) $req['id'] ?>">
                        <div class="req-list-main" role="link" tabindex="0" onclick="window.location='visualizar_denuncia.php?id=<?= (int) $req['id'] ?>'" onkeydown="if(event.key==='Enter'){this.click()}">
                            <div class="req-list-top">
                                <span class="feed-type-badge"><i class="fas fa-bullhorn"></i>Denúncia</span>
                                <span class="req-protocol">#<?= htmlspecialchars($req['protocolo']) ?></span>
                                <span class="badge badge-status <?= htmlspecialchars($statusDenunciaClass) ?>"><?= htmlspecialchars($req['status']) ?></span>
                                <?php if (!empty($req['anonimo'])): ?><span class="feed-anon-badge"><i class="fas fa-user-secret"></i>Anônima</span><?php endif; ?>
                            </div>
                            <div class="req-name"><?= htmlspecialchars($req['titulo']) ?></div>
                            <div class="req-type-row"><span class="req-type-short">DEN</span><span class="req-type-name"><?= htmlspecialchars($subtituloDenuncia ?: ($ehObrasDenuncia ? 'Obras e Urbanismo' : 'Meio Ambiente')) ?></span></div>
                        </div>
                        <div class="req-list-side">
                            <div class="req-date"><?= formataDataBR($req['data_processo']) ?></div>
                            <a href="visualizar_denuncia.php?id=<?= (int) $req['id'] ?>" class="req-open-button">Abrir <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </article>
                <?php else: ?>
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
                <?php
                $acaoAtual = $req['aguardando_acao'] ?? '';
                $extraCardClass = match($acaoAtual) {
                    'retorno_aprovado' => 'retorno-aprovado',
                    'retorno_recusado' => 'retorno-recusado',
                    default => '',
                };
                // Preview do que é específico deste requerimento — sem isso, todo
                // registro do mesmo tipo mostra exatamente o mesmo texto na lista.
                $previewReq = trim((string) ($req['especificacao'] ?? ''));
                if ($previewReq === '') $previewReq = trim((string) ($req['endereco_objetivo'] ?? ''));
                if ($previewReq === '' && !empty($req['responsavel_tecnico_nome'])) {
                    $previewReq = 'Responsável técnico: ' . trim((string) $req['responsavel_tecnico_nome']);
                }
                $previewReq = mb_strimwidth($previewReq, 0, 130, '…', 'UTF-8');
                ?>
                <article class="req-list-item <?= $req['visualizado'] == 0 ? 'is-unread' : '' ?> <?= $extraCardClass ?>" data-id="<?= (int) $req['id'] ?>">
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
                            <span class="feed-type-badge requerimento"><i class="fas fa-clipboard-list"></i>Requerimento</span>
                            <span class="req-protocol">#<?= htmlspecialchars($req['protocolo']) ?></span>
                            <?php if ($buscaCruzaSetor && $req['setor_atual'] !== $setorFiltro): ?>
                                <span class="badge" style="background:#eef1fd;color:#3b4b9e;border:1px solid #c9d0f2;font-size:.68rem;font-weight:600;letter-spacing:.02em;text-transform:uppercase;"
                                      title="Este processo ainda não está na sua fila — encontrado pela busca">
                                    <i class="fas fa-arrows-turn-to-dots" style="font-size:.6rem;opacity:.7;"></i>
                                    <?= htmlspecialchars($setorLabelsCurto[$req['setor_atual']] ?? $req['setor_atual']) ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($acaoAtual === 'retorno_aprovado'): ?>
                                <span class="badge badge-retorno-aprovado">
                                    <i class="fas fa-circle-check" style="font-size:.6rem;opacity:.7;"></i>Retorno — Secretário aprovou
                                </span>
                            <?php elseif ($acaoAtual === 'retorno_recusado'): ?>
                                <span class="badge badge-retorno-recusado" title="<?= htmlspecialchars($req['motivo_devolucao'] ?? '') ?>">
                                    <i class="fas fa-circle-xmark" style="font-size:.6rem;opacity:.7;"></i>Retorno — Secretário não aprovou
                                </span>
                            <?php elseif ($setorFiltro && !empty($acaoAtual) && $acaoAtual !== 'concluido'): ?>
                                <span class="badge <?= htmlspecialchars(acaoClass($acaoAtual)) ?>" style="font-size:.7rem;">
                                    <?= htmlspecialchars(acaoLabel($acaoAtual)) ?>
                                </span>
                            <?php else: ?>
                                <span class="badge badge-status <?= htmlspecialchars($metaClass) ?>"><?= htmlspecialchars($req['status']) ?></span>
                            <?php endif; ?>
                            <?php if ($req['visualizado'] == 0): ?>
                                <span class="req-unread-pill"><span class="req-unread-dot"></span>Não aberto</span>
                            <?php endif; ?>
                        </div>
                        <div class="req-name"><?= htmlspecialchars($req['titulo']) ?></div>
                        <div class="req-type-row">
                            <span class="req-type-short"><?= htmlspecialchars($short) ?></span>
                            <span class="req-type-name"><?= htmlspecialchars(nomeAlvara($req['tipo_alvara'])) ?></span>
                        </div>
                        <?php if ($previewReq !== ''): ?>
                            <div class="req-preview"><?= htmlspecialchars($previewReq) ?></div>
                        <?php endif; ?>
                    </button>

                    <div class="req-list-side">
                        <div class="req-date"><?= formataDataBR($req['data_processo']) ?></div>
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
                <?php endif; ?>
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
            <p>Nenhum processo encontrado.</p>
        </div>
    <?php endif; ?>
</div>

<script src="<?= adminAssetUrl('includes/admin-scripts.js') ?>"></script>
<?php include 'footer.php'; ?>
