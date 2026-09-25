<?php
require_once 'conexao.php';
require_once 'helpers.php';
require_once __DIR__ . '/../includes/denuncia_filters.php';
verificaLogin();

$adminId = (int) ($_SESSION['admin_id'] ?? 0);
$setorAdmin = setorAdministrador($pdo, $adminId);
$setorPadrao = setorPadraoDenunciaSessao($pdo);

// Sem "setor" na URL a lista abre na equipe do usuário; "setor=todas" mostra todas.
$filtros = resolverFiltrosDenuncia($_GET, null, $setorAdmin);
$setorUrl = (string) ($_GET['setor'] ?? '');
$filtros['setor'] = match (true) {
    in_array($setorUrl, ['meio_ambiente', 'obras_urbanismo'], true) => $setorUrl,
    $setorUrl === 'todas' => '',
    default => $setorPadrao,
};
$filtroBusca = trim((string) ($_GET['busca'] ?? ''));
$paginaAtual = max(1, (int) ($_GET['pagina'] ?? 1));
$itensPorPagina = 20;

if ($filtros['status'] === 'concluida') {
    $filtros['concluidas'] = '1';
}

$where = ['1=1'];
$params = [];
if ($filtroBusca !== '') {
    $where[] = '(d.protocolo_publico LIKE ? OR d.infrator_nome LIKE ? OR d.infrator_cpf_cnpj LIKE ? OR d.infrator_endereco LIKE ? OR d.observacoes LIKE ?)';
    $term = '%' . $filtroBusca . '%';
    array_push($params, $term, $term, $term, $term, $term);
}
if ($filtros['setor'] !== '') {
    $where[] = 'd.setor = ?';
    $params[] = $filtros['setor'];
}
if ($filtros['origem'] === 'publico') {
    $where[] = "d.origem = 'publico'";
} elseif ($filtros['origem'] === 'interno') {
    $where[] = "d.origem = 'admin'";
} elseif ($filtros['origem'] === 'minhas') {
    $where[] = "d.origem = 'admin' AND d.admin_id = ?";
    $params[] = $adminId;
}
if ($filtros['anonimo'] !== '') {
    $where[] = 'd.anonimo = ?';
    $params[] = (int) $filtros['anonimo'];
}
// Última movimentação: último andamento registrado no histórico ou, sem nenhum, o registro.
$ultimaMovSql = "(SELECT COALESCE(MAX(h.data_registro), d.data_registro) FROM denuncia_historico h WHERE h.denuncia_id = d.id AND h.acao <> 'Responsável')";
if ($filtros['atribuidas'] === '1') {
    $where[] = 'd.responsavel_id = ?';
    $params[] = $adminId;
}

$statusSql = [
    'pendente' => "LOWER(TRIM(d.status)) = 'pendente'",
    'em_analise' => "LOWER(TRIM(d.status)) IN ('em análise', 'em analise', 'em_analise')",
    'concluida' => "LOWER(TRIM(d.status)) IN ('concluída', 'concluida', 'concluído', 'concluido', 'finalizado', 'finalizada')",
];
if ($filtros['status'] !== '') {
    $where[] = $statusSql[$filtros['status']];
} elseif ($filtros['concluidas'] !== '1') {
    $where[] = 'NOT ' . $statusSql['concluida'];
}
$atrasoSql = "NOT {$statusSql['concluida']} AND {$ultimaMovSql} < NOW() - INTERVAL " . DENUNCIA_DIAS_ATRASO . ' DAY';
if ($filtros['atrasadas'] === '1') {
    $where[] = $atrasoSql;
}

$whereSql = implode(' AND ', $where);
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM denuncias d WHERE {$whereSql}");
$stmtCount->execute($params);
$totalDenuncias = (int) $stmtCount->fetchColumn();
$totalPaginas = max(1, (int) ceil($totalDenuncias / $itensPorPagina));
$paginaAtual = min($paginaAtual, $totalPaginas);
$offset = ($paginaAtual - 1) * $itensPorPagina;

$sql = "SELECT d.id, d.data_registro, d.infrator_nome, d.infrator_cpf_cnpj,
               d.infrator_endereco, d.observacoes, d.status, d.origem, d.setor,
               d.protocolo_publico, d.anonimo, d.tipo_denuncia, d.admin_id,
               a.nome AS responsavel, d.responsavel_id, r.nome AS atribuido_nome,
               {$ultimaMovSql} AS ultima_movimentacao
        FROM denuncias d
        LEFT JOIN administradores a ON d.admin_id = a.id
        LEFT JOIN administradores r ON d.responsavel_id = r.id
        WHERE {$whereSql}
        ORDER BY d.data_registro DESC, d.id DESC
        LIMIT {$itensPorPagina} OFFSET {$offset}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$denuncias = $stmt->fetchAll();

$concluidaSql = "LOWER(TRIM(status)) IN ('concluída','concluida','concluído','concluido','finalizado','finalizada')";
$stmtStats = $pdo->prepare("SELECT
    COUNT(*) AS todas,
    SUM(CASE WHEN NOT {$concluidaSql} THEN 1 ELSE 0 END) AS abertas,
    SUM(CASE WHEN LOWER(TRIM(status)) = 'pendente' THEN 1 ELSE 0 END) AS pendentes,
    SUM(CASE WHEN LOWER(TRIM(status)) IN ('em análise','em analise','em_analise') THEN 1 ELSE 0 END) AS em_analise,
    SUM(CASE WHEN {$concluidaSql} THEN 1 ELSE 0 END) AS concluidas,
    SUM(CASE WHEN origem = 'admin' AND admin_id = ? THEN 1 ELSE 0 END) AS minhas,
    SUM(CASE WHEN responsavel_id = ? AND NOT {$concluidaSql} THEN 1 ELSE 0 END) AS atribuidas,
    SUM(CASE WHEN {$atrasoSql} THEN 1 ELSE 0 END) AS atrasadas
    FROM denuncias d" . ($filtros['setor'] !== '' ? ' WHERE setor = ?' : ''));
$stmtStats->execute($filtros['setor'] !== '' ? [$adminId, $adminId, $filtros['setor']] : [$adminId, $adminId]);
$stats = $stmtStats->fetch() ?: [];

$porSetor = [];
foreach ($pdo->query("SELECT setor, COUNT(*) n FROM denuncias WHERE NOT {$concluidaSql} GROUP BY setor") as $row) {
    $porSetor[$row['setor'] ?: 'meio_ambiente'] = ($porSetor[$row['setor'] ?: 'meio_ambiente'] ?? 0) + (int) $row['n'];
}

$mensagem = match ($_GET['success'] ?? '') {
    'registrada' => 'Denúncia registrada com sucesso.',
    'atualizada' => 'Denúncia atualizada com sucesso.',
    'excluida' => 'Denúncia removida corretamente.',
    default => '',
};
$mensagemErro = match ($_GET['error'] ?? '') {
    'criacao' => 'Não foi possível registrar a denúncia.',
    'nao_encontrado' => 'Denúncia não encontrada.',
    default => '',
};

$statusAtivo = $filtros['status'] !== '' ? $filtros['status'] : ($filtros['concluidas'] === '1' ? 'todas' : 'abertas');

function buildDenunciaUrl(array $overrides = []): string
{
    global $filtros, $filtroBusca, $setorPadrao;
    $params = array_merge($filtros, ['busca' => $filtroBusca], $overrides);
    unset($params['limpar']);
    $params['setor'] = paramSetorUrl((string) $params['setor'], $setorPadrao);
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null || ($key === 'pagina' && (int) $value === 1) || ($key === 'concluidas' && $value === '0')) {
            unset($params[$key]);
        }
    }
    return 'denuncias.php' . ($params ? '?' . http_build_query($params) : '');
}

function paramSetorUrl(string $setor, string $setorPadrao): string
{
    if ($setor === $setorPadrao) {
        return '';
    }
    return $setor === '' ? 'todas' : $setor;
}

function denunciaStatusClass(string $status): string
{
    return match (normalizarStatusProcesso($status)) {
        'em_analise' => 'status-em-analise',
        'concluida' => 'status-finalizado',
        default => 'status-pendente',
    };
}

include 'header.php';
?>
<link rel="stylesheet" href="<?= adminAssetUrl('includes/admin-styles.css') ?>">
<style>
.den-list{display:grid;gap:12px}.den-card{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:20px;align-items:center;padding:18px 20px;background:#fff;border:1px solid var(--line);border-left:4px solid #538867;border-radius:16px;box-shadow:var(--card-shadow);color:inherit;text-decoration:none;transition:.16s ease}.den-card.obras{border-left-color:#c98b2e}.den-card:hover{color:inherit;transform:translateY(-1px);border-color:#b9cbc0;box-shadow:0 12px 28px rgba(24,54,37,.09)}.den-card-top,.den-card-meta{display:flex;align-items:center;flex-wrap:wrap;gap:8px}.den-card-title{margin:9px 0 5px;color:var(--ink);font-size:1.02rem;font-weight:800}.den-card-subtitle{color:var(--muted);font-size:.84rem;line-height:1.45}.den-card-side{min-width:150px;text-align:right}.den-card-date{margin-bottom:10px;color:var(--muted);font-size:.78rem}.den-type-pill{display:inline-flex;align-items:center;gap:5px;padding:4px 9px;border-radius:999px;background:#f6e9e8;color:#913f39;font-size:.7rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase}.den-anon-pill{display:inline-flex;align-items:center;gap:5px;padding:4px 9px;border-radius:999px;background:#302b36;color:#fff;font-size:.7rem;font-weight:800}.den-origin,.den-sector{color:var(--muted);font-size:.76rem;font-weight:650}.den-open{display:inline-flex;align-items:center;gap:7px;color:var(--primary);font-size:.82rem;font-weight:800}.den-filter-grid{display:grid;grid-template-columns:minmax(220px,2fr) repeat(3,minmax(135px,1fr));gap:12px;align-items:end}.den-filter-field label{display:block;margin-bottom:6px;color:var(--muted);font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em}.den-filter-field input,.den-filter-field select{width:100%;min-height:42px;border:1px solid var(--line);border-radius:10px;padding:8px 11px;background:#fff;color:var(--ink)}.den-filter-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px;align-items:center}.den-summary .summary-chip{min-width:145px}.den-toggle{display:inline-flex;align-items:center;gap:8px;min-height:42px;padding:8px 11px;border:1px solid var(--line);border-radius:10px;background:#fff;color:var(--ink);font-size:.82rem;font-weight:650}.den-empty{padding:50px 20px;background:#fff;border:1px dashed #cbd8cf;border-radius:18px;text-align:center;color:var(--muted)}.den-empty i{display:block;margin-bottom:12px;font-size:2rem;color:#a9baae}@media(max-width:1100px){.den-filter-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.den-filter-search{grid-column:1/-1}}@media(max-width:680px){.den-filter-grid{grid-template-columns:1fr}.den-filter-search{grid-column:auto}.den-card{grid-template-columns:1fr;gap:12px}.den-card-side{display:flex;align-items:center;justify-content:space-between;text-align:left;min-width:0}.den-card-date{margin:0}}
.den-tabs{display:flex;flex-wrap:wrap;gap:4px;margin:4px 0 16px;border-bottom:1px solid var(--line)}.den-tab{display:inline-flex;align-items:center;gap:8px;margin-bottom:-1px;padding:10px 16px;border-bottom:3px solid transparent;color:var(--muted);font-weight:750;text-decoration:none}.den-tab:hover{color:var(--ink)}.den-tab.active{color:var(--ink);border-bottom-color:var(--primary)}.den-tab-mine{padding:1px 7px;border-radius:999px;background:#e3f0e7;color:#1f5133;font-size:.66rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em}.den-tab-count{display:inline-block;min-width:22px;padding:1px 7px;border-radius:999px;background:#eef3ef;color:#3d5446;font-size:.72rem;font-weight:800;text-align:center}
.den-search-form{display:flex;flex-wrap:wrap;gap:10px;align-items:center}.den-search-form .den-search-wrap{flex:1 1 320px}.den-search-form input[type=search]{width:100%;min-height:44px;border:1px solid var(--line);border-radius:10px;padding:8px 11px 8px 34px;background:#fff;color:var(--ink)}.den-mine{display:inline-flex;align-items:center;gap:8px;min-height:44px;padding:8px 13px;border:1px solid var(--line);border-radius:10px;background:#fff;color:var(--ink);font-size:.86rem;font-weight:650;text-decoration:none}.den-mine:hover{color:var(--ink);border-color:#b9cbc0}.den-mine.active{background:#eaf3ed;border-color:#8fb89c;color:#1f5133}.den-mine-alerta.active{background:#fbe9e7;border-color:#e0a49c;color:#8f2f26}
.den-atraso{display:inline-flex;align-items:center;gap:5px;padding:4px 9px;border-radius:999px;font-size:.7rem;font-weight:800}.den-atraso.atencao{background:#fff4d6;color:#7a5a00}.den-atraso.atrasada{background:#fbe2df;color:#9b2c22}.den-resp.vazio{color:#b0785a}
.den-search-wrap{position:relative}.den-search-wrap>i{position:absolute;z-index:2;left:12px;top:50%;transform:translateY(-50%);color:#8fa399;font-size:.8rem}.den-search-wrap input{padding-left:34px}.den-suggestions{display:none;position:absolute;z-index:50;top:calc(100% + 7px);left:0;right:0;overflow:hidden;padding:6px;background:#fff;border:1px solid #d9e3dc;border-radius:13px;box-shadow:0 18px 42px rgba(16,33,23,.16)}.den-suggestions.active{display:block}.den-suggestion{display:flex;align-items:center;gap:11px;padding:10px;border-radius:9px;color:inherit;text-decoration:none}.den-suggestion:hover{background:#f3f7f4;color:inherit}.den-suggestion-icon{width:34px;height:34px;display:flex;align-items:center;justify-content:center;flex:0 0 auto;border-radius:9px;background:#f6e9e8;color:#913f39}.den-suggestion-copy{min-width:0;flex:1}.den-suggestion-top{display:flex;align-items:center;gap:8px;min-width:0}.den-suggestion-protocol{font-family:ui-monospace,monospace;color:#52635a;font-size:.72rem}.den-suggestion-title{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#102117;font-size:.84rem;font-weight:750}.den-suggestion-meta{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-top:2px;color:#7a8a81;font-size:.72rem}.den-suggestion-empty{padding:14px;text-align:center;color:#7a8a81;font-size:.78rem}
</style>

<div class="admin-page-shell denuncias-page">
<?php
$chipsStatus = [
    'abertas' => ['Em aberto', 'fa-folder-open', ['status' => '', 'concluidas' => '0'], $stats['abertas'] ?? 0],
    'pendente' => ['Pendentes', 'fa-clock', ['status' => 'pendente', 'concluidas' => '0'], $stats['pendentes'] ?? 0],
    'em_analise' => ['Em análise', 'fa-magnifying-glass', ['status' => 'em_analise', 'concluidas' => '0'], $stats['em_analise'] ?? 0],
    'concluida' => ['Concluídas', 'fa-circle-check', ['status' => 'concluida', 'concluidas' => '0'], $stats['concluidas'] ?? 0],
    'todas' => ['Todas', 'fa-layer-group', ['status' => '', 'concluidas' => '1'], $stats['todas'] ?? 0],
];
$abasSetor = [
    '' => ['Todas as equipes', 'fa-layer-group', array_sum($porSetor)],
    'meio_ambiente' => ['Meio Ambiente', 'fa-leaf', $porSetor['meio_ambiente'] ?? 0],
    'obras_urbanismo' => ['Obras e Urbanismo', 'fa-hard-hat', $porSetor['obras_urbanismo'] ?? 0],
];
if ($setorPadrao !== '') {
    // A aba da própria equipe vem primeiro; "Todas" vai para o fim.
    $todas = $abasSetor[''];
    unset($abasSetor['']);
    $abasSetor = [$setorPadrao => $abasSetor[$setorPadrao]] + $abasSetor + ['' => $todas];
}
$soMinhas = $filtros['origem'] === 'minhas';
$soAtribuidas = $filtros['atribuidas'] === '1';
$soAtrasadas = $filtros['atrasadas'] === '1';
?>
    <section class="page-hero page-hero-compact">
        <div class="page-hero-copy">
            <h1 class="page-title">Denúncias</h1>
            <p class="page-subtitle"><?= $setorPadrao !== ''
                ? 'Sua equipe: ' . htmlspecialchars(nomeSetorDenuncia($setorPadrao)) . '. As outras equipes ficam nas abas ao lado.'
                : 'Escolha uma equipe nas abas ou veja todas juntas.' ?></p>
        </div>
        <div class="page-toolbar"><a href="nova_denuncia.php" class="toolbar-button toolbar-button-primary"><i class="fas fa-plus"></i> Registrar denúncia</a></div>
    </section>
    <?php if ($mensagem): ?><div class="alert alert-success" role="status"><?= htmlspecialchars($mensagem) ?></div><?php endif; ?>
    <?php if ($mensagemErro): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($mensagemErro) ?></div><?php endif; ?>

    <nav class="den-tabs" aria-label="Equipe">
        <?php foreach ($abasSetor as $valor => [$rotulo, $icone, $qtd]): ?>
            <a href="<?= htmlspecialchars(buildDenunciaUrl(['setor' => (string) $valor, 'pagina' => 1])) ?>" class="den-tab <?= $filtros['setor'] === (string) $valor ? 'active' : '' ?>"><i class="fas <?= $icone ?>"></i> <?= $rotulo ?><?php if ($setorPadrao !== '' && (string) $valor === $setorPadrao): ?><span class="den-tab-mine">sua equipe</span><?php endif; ?><span class="den-tab-count" title="Em aberto"><?= (int) $qtd ?></span></a>
        <?php endforeach; ?>
    </nav>

    <section class="req-summary-strip den-summary" aria-label="Situação">
        <?php foreach ($chipsStatus as $chave => [$rotulo, $icone, $params, $qtd]): ?>
            <a href="<?= htmlspecialchars(buildDenunciaUrl($params + ['pagina' => 1])) ?>" class="summary-chip <?= $statusAtivo === $chave ? 'active' : '' ?>"><span><i class="fas <?= $icone ?>"></i><?= $rotulo ?></span><strong><?= (int) $qtd ?></strong></a>
        <?php endforeach; ?>
    </section>

    <section class="req-filter-bar">
        <form method="GET" class="den-search-form">
            <?php $setorParam = paramSetorUrl($filtros['setor'], $setorPadrao); ?>
            <?php if ($setorParam !== ''): ?><input type="hidden" name="setor" value="<?= htmlspecialchars($setorParam) ?>"><?php endif; ?>
            <?php foreach (['status', 'concluidas', 'origem', 'atribuidas', 'atrasadas'] as $campo): ?>
                <?php if ($filtros[$campo] !== ''): ?><input type="hidden" name="<?= $campo ?>" value="<?= htmlspecialchars($filtros[$campo]) ?>"><?php endif; ?>
            <?php endforeach; ?>
            <div class="den-search-wrap"><i class="fas fa-magnifying-glass"></i><input id="busca" name="busca" type="search" autocomplete="off" aria-label="Buscar denúncia" aria-autocomplete="list" aria-controls="denunciaSuggestions" aria-expanded="false" value="<?= htmlspecialchars($filtroBusca) ?>" placeholder="Buscar por protocolo, nome do infrator, CPF/CNPJ ou endereço"><div id="denunciaSuggestions" class="den-suggestions" role="listbox"></div></div>
            <button type="submit" class="toolbar-button toolbar-button-primary">Buscar</button>
            <a href="<?= htmlspecialchars(buildDenunciaUrl(['origem' => $soMinhas ? '' : 'minhas', 'pagina' => 1])) ?>" class="den-mine <?= $soMinhas ? 'active' : '' ?>"><i class="<?= $soMinhas ? 'fas fa-square-check' : 'far fa-square' ?>"></i> Só as que eu registrei <span class="den-tab-count"><?= (int) ($stats['minhas'] ?? 0) ?></span></a>
            <a href="<?= htmlspecialchars(buildDenunciaUrl(['atribuidas' => $soAtribuidas ? '' : '1', 'pagina' => 1])) ?>" class="den-mine <?= $soAtribuidas ? 'active' : '' ?>"><i class="<?= $soAtribuidas ? 'fas fa-square-check' : 'far fa-square' ?>"></i> Atribuídas a mim <span class="den-tab-count"><?= (int) ($stats['atribuidas'] ?? 0) ?></span></a>
            <a href="<?= htmlspecialchars(buildDenunciaUrl(['atrasadas' => $soAtrasadas ? '' : '1', 'pagina' => 1])) ?>" class="den-mine den-mine-alerta <?= $soAtrasadas ? 'active' : '' ?>" title="Sem andamento há <?= DENUNCIA_DIAS_ATRASO ?> dias ou mais"><i class="<?= $soAtrasadas ? 'fas fa-square-check' : 'far fa-square' ?>"></i> Atrasadas <span class="den-tab-count"><?= (int) ($stats['atrasadas'] ?? 0) ?></span></a>
            <?php if ($filtroBusca !== ''): ?><a href="<?= htmlspecialchars(buildDenunciaUrl(['busca' => '', 'pagina' => 1])) ?>" class="toolbar-button toolbar-button-ghost">Limpar busca</a><?php endif; ?>
        </form>
    </section>

    <?php if ($denuncias): ?>
        <section class="den-list" aria-label="Lista de denúncias">
            <?php foreach ($denuncias as $denuncia):
                $tipos = tiposDenuncia($denuncia);
                $subtitulo = implode(' · ', $tipos);
                if ($subtitulo === '') $subtitulo = trim((string) ($denuncia['infrator_endereco'] ?? ''));
                if ($subtitulo === '') $subtitulo = mb_strimwidth(trim((string) $denuncia['observacoes']), 0, 130, '…', 'UTF-8');
                $protocolo = $denuncia['protocolo_publico'] ?: 'DEN-' . str_pad((string) $denuncia['id'], 6, '0', STR_PAD_LEFT);
                $ehObras = ($denuncia['setor'] ?? '') === 'obras_urbanismo';
                $diasParada = diasSemAndamento($denuncia['ultima_movimentacao'] ?? null);
                $nivelAtraso = nivelAtrasoDenuncia($diasParada, (string) $denuncia['status']);
            ?>
                <a class="den-card <?= $ehObras ? 'obras' : '' ?>" href="visualizar_denuncia.php?id=<?= (int) $denuncia['id'] ?>">
                    <div><div class="den-card-top"><span class="den-type-pill"><i class="fas fa-bullhorn"></i> Denúncia</span><span class="req-protocol">#<?= htmlspecialchars($protocolo) ?></span><span class="badge badge-status <?= htmlspecialchars(denunciaStatusClass((string) $denuncia['status'])) ?>"><?= htmlspecialchars($denuncia['status']) ?></span><?php if (!empty($denuncia['anonimo'])): ?><span class="den-anon-pill"><i class="fas fa-user-secret"></i> Anônima</span><?php endif; ?><?php if ($nivelAtraso !== ''): ?><span class="den-atraso <?= $nivelAtraso ?>"><i class="fas fa-hourglass-half"></i> <?= $diasParada ?> dias sem andamento</span><?php endif; ?></div>
                        <div class="den-card-title"><?= htmlspecialchars(tituloDenuncia($denuncia)) ?></div><div class="den-card-subtitle"><?= htmlspecialchars($subtitulo ?: 'Ocorrência sem local ou tipo informado') ?></div>
                        <div class="den-card-meta" style="margin-top:9px;"><span class="den-sector"><i class="fas <?= $ehObras ? 'fa-hard-hat' : 'fa-leaf' ?>"></i> <?= $ehObras ? 'Obras e Urbanismo' : 'Meio Ambiente' ?></span><span class="den-origin"><i class="fas <?= ($denuncia['origem'] ?? 'admin') === 'publico' ? 'fa-earth-americas' : 'fa-user-shield' ?>"></i> <?= ($denuncia['origem'] ?? 'admin') === 'publico' ? 'Cidadão' : 'Interna' ?></span><?php if (($denuncia['origem'] ?? 'admin') === 'admin' && $denuncia['responsavel']): ?><span class="den-origin">Criada por <?= htmlspecialchars($denuncia['responsavel']) ?></span><?php endif; ?><span class="den-origin den-resp <?= $denuncia['atribuido_nome'] ? '' : 'vazio' ?>"><i class="fas fa-user-tag"></i> <?= $denuncia['atribuido_nome'] ? 'Responsável: ' . htmlspecialchars($denuncia['atribuido_nome']) : 'Sem responsável' ?></span></div>
                    </div>
                    <div class="den-card-side"><div class="den-card-date"><?= date('d/m/Y \à\s H:i', strtotime($denuncia['data_registro'])) ?></div><span class="den-open">Abrir <i class="fas fa-arrow-right"></i></span></div>
                </a>
            <?php endforeach; ?>
        </section>
        <section class="req-pagination"><div class="req-pagination-copy">Página <?= $paginaAtual ?> de <?= $totalPaginas ?> · <?= $totalDenuncias ?> denúncia(s)</div><div class="req-pagination-links">
            <?php if ($paginaAtual > 1): ?><a href="<?= htmlspecialchars(buildDenunciaUrl(['pagina' => 1])) ?>" class="req-page-link">«</a><a href="<?= htmlspecialchars(buildDenunciaUrl(['pagina' => $paginaAtual - 1])) ?>" class="req-page-link">‹</a><?php endif; ?>
            <?php for ($i = max(1, $paginaAtual - 2); $i <= min($totalPaginas, $paginaAtual + 2); $i++): ?><a href="<?= htmlspecialchars(buildDenunciaUrl(['pagina' => $i])) ?>" class="req-page-link <?= $i === $paginaAtual ? 'active' : '' ?>"><?= $i ?></a><?php endfor; ?>
            <?php if ($paginaAtual < $totalPaginas): ?><a href="<?= htmlspecialchars(buildDenunciaUrl(['pagina' => $paginaAtual + 1])) ?>" class="req-page-link">›</a><a href="<?= htmlspecialchars(buildDenunciaUrl(['pagina' => $totalPaginas])) ?>" class="req-page-link">»</a><?php endif; ?>
        </div></section>
    <?php else: ?><div class="den-empty"><i class="fas fa-inbox"></i><strong><?= $soMinhas ? 'Você ainda não registrou denúncias nesta situação.' : 'Nenhuma denúncia nesta situação.' ?></strong><p class="mb-0 mt-1"><?= $filtroBusca !== '' ? 'Nenhum resultado para a busca.' : 'Escolha outra situação acima ou registre uma nova ocorrência.' ?></p></div><?php endif; ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('busca');
    const box = document.getElementById('denunciaSuggestions');
    if (!input || !box) return;

    const filtros = <?= json_encode($filtros, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    let timer = null;
    let controller = null;

    function escapeHtml(value) {
        const node = document.createElement('div');
        node.textContent = String(value == null ? '' : value);
        return node.innerHTML;
    }
    function closeSuggestions() {
        box.classList.remove('active');
        input.setAttribute('aria-expanded', 'false');
    }
    function renderSuggestions(data) {
        const items = data && Array.isArray(data.resultados) ? data.resultados : [];
        if (!items.length) {
            box.innerHTML = '<div class="den-suggestion-empty">Nenhuma sugestão encontrada</div>';
        } else {
            box.innerHTML = items.map(function (item) {
                const meta = [item.documento, item.tipo, item.status, item.setor].filter(Boolean).join(' · ');
                return '<a class="den-suggestion" role="option" href="' + escapeHtml(item.url) + '">' +
                    '<span class="den-suggestion-icon"><i class="fas ' + (item.anonimo ? 'fa-user-secret' : 'fa-bullhorn') + '"></i></span>' +
                    '<span class="den-suggestion-copy"><span class="den-suggestion-top"><span class="den-suggestion-title">' + escapeHtml(item.titulo) + '</span><span class="den-suggestion-protocol">#' + escapeHtml(item.protocolo) + '</span></span>' +
                    '<span class="den-suggestion-meta">' + escapeHtml(meta) + '</span></span></a>';
            }).join('');
        }
        box.classList.add('active');
        input.setAttribute('aria-expanded', 'true');
    }
    function searchSuggestions() {
        const termo = input.value.trim();
        if (termo.length < 2) {
            if (controller) controller.abort();
            closeSuggestions();
            return;
        }
        if (controller) controller.abort();
        controller = new AbortController();
        const params = new URLSearchParams(filtros);
        params.set('q', termo);
        fetch('ajax/busca_denuncias.php?' + params.toString(), {
            signal: controller.signal,
            headers: {'X-Requested-With': 'fetch'}
        }).then(function (response) {
            return response.ok ? response.json() : null;
        }).then(function (data) {
            if (!data || data.termo !== input.value.trim()) return;
            renderSuggestions(data);
        }).catch(function (error) {
            if (error.name !== 'AbortError') closeSuggestions();
        });
    }
    input.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(searchSuggestions, 220);
    });
    input.addEventListener('focus', function () {
        if (input.value.trim().length >= 2) searchSuggestions();
    });
    input.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeSuggestions();
        // Enter não seleciona uma sugestão: o submit normal executa a busca completa.
    });
    document.addEventListener('click', function (event) {
        if (!event.target.closest('.den-search-wrap')) closeSuggestions();
    });
});
</script>
<?php include 'footer.php'; ?>
