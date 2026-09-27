<?php
require_once 'conexao.php';
require_once 'helpers.php';
require_once __DIR__ . '/../includes/functions.php';
verificaLogin();

// Fila dedicada do secretário — só requerimentos do setor 3, sem denúncias,
// sem ações em massa/alterar status/excluir (ele só decide via painel_secretario.php).
// Existe como um "acompanhamento" separado da fila de assinatura em si.
if (($_SESSION['admin_nivel'] ?? '') !== 'secretario') {
    header('Location: index.php');
    exit;
}

function formataDataFila($data)
{
    return date('d/m/Y \à\s H:i', strtotime($data));
}

$itensPorPagina = 20;
$paginaAtual = max(1, (int) ($_GET['pagina'] ?? 1));
$filtroBusca = trim((string) ($_GET['busca'] ?? ''));
$filtroAcao = $_GET['acao'] ?? '';

$where = ['r.setor_atual = ?'];
$params = ['setor3'];

if ($filtroBusca !== '') {
    $where[] = '(r.protocolo LIKE ? OR req.nome LIKE ? OR req.cpf_cnpj LIKE ?)';
    $termo = '%' . $filtroBusca . '%';
    array_push($params, $termo, $termo, $termo);
}

if ($filtroAcao !== '') {
    $where[] = 'r.aguardando_acao = ?';
    $params[] = $filtroAcao;
}

$whereSql = implode(' AND ', $where);

$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM requerimentos r JOIN requerentes req ON req.id = r.requerente_id WHERE $whereSql");
$stmtCount->execute($params);
$totalRegistros = (int) $stmtCount->fetchColumn();
$totalPaginas = max(1, (int) ceil($totalRegistros / $itensPorPagina));
$paginaAtual = min($paginaAtual, $totalPaginas);
$offset = ($paginaAtual - 1) * $itensPorPagina;

$stmt = $pdo->prepare("
    SELECT r.id, r.protocolo, r.tipo_alvara, r.status, r.data_envio, r.visualizado,
           r.aguardando_acao, r.motivo_devolucao, req.nome AS requerente_nome
    FROM requerimentos r
    JOIN requerentes req ON req.id = r.requerente_id
    WHERE $whereSql
    ORDER BY r.data_envio DESC
    LIMIT $itensPorPagina OFFSET $offset
");
$stmt->execute($params);
$processos = $stmt->fetchAll(PDO::FETCH_ASSOC);

function contarFilaSecretario(PDO $pdo, string $extraWhere = '1=1', array $extraParams = []): int
{
    $st = $pdo->prepare("SELECT COUNT(*) FROM requerimentos WHERE setor_atual = 'setor3' AND $extraWhere");
    $st->execute($extraParams);
    return (int) $st->fetchColumn();
}

$estatisticas = [
    'na_fila'      => contarFilaSecretario($pdo, 'aguardando_acao = ?', ['revisao_setor3']),
    'aprovados'    => contarFilaSecretario($pdo, 'aguardando_acao = ?', ['retorno_aprovado']),
    'devolvidos'   => contarFilaSecretario($pdo, "aguardando_acao IN (?, ?)", ['retorno_recusado', 'analise_setor2']),
    'total'        => contarFilaSecretario($pdo),
];

function buildFilaUrl(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            unset($params[$key]);
        }
    }
    return 'fila_revisao_secretario.php' . ($params ? '?' . http_build_query($params) : '');
}

$tipoSiglas = [
    'licenca_ambiental_unica' => 'LAU',
    'habite_se' => 'HBT',
    'habite_se_simples' => 'HBS',
    'construcao' => 'CNS',
    'licenca_previa_obras' => 'LPO',
    'desmembramento' => 'DSM',
];

include 'header.php';
?>
<link rel="stylesheet" href="<?= adminAssetUrl('includes/admin-styles.css') ?>">
<style>
.fila-sec-shell { max-width: 1100px; margin: 0 auto; display: flex; flex-direction: column; gap: 14px; }
.fila-sec-chips { display: grid; grid-template-columns: repeat(auto-fit,minmax(150px,1fr)); gap: 8px; }
.fila-sec-chip { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 10px 14px; border: 1px solid var(--line); border-radius: 12px; background: #fff; color: var(--muted); font-size: .82rem; font-weight: 700; text-decoration: none; }
.fila-sec-chip.active { border-color: #7e22ce; background: #faf5ff; color: #7e22ce; }
.fila-sec-chip strong { font-size: 1.05rem; }
</style>

<div class="fila-sec-shell">
    <section class="page-hero page-hero-compact">
        <div class="page-hero-copy">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:2px;">
                <span style="width:32px;height:32px;border-radius:9px;background:#faf5ff;border:1.5px solid #7e22ce33;display:inline-flex;align-items:center;justify-content:center;color:#7e22ce;flex-shrink:0;">
                    <i class="fas fa-shield-halved" style="font-size:.85rem;"></i>
                </span>
                <h1 class="page-title" style="color:#7e22ce;">Fila de Revisão do Secretário</h1>
            </div>
            <p class="page-subtitle">Acompanhamento dos processos no setor 3 · <?= (int) $totalRegistros ?> processo(s)</p>
        </div>
    </section>

    <nav class="fila-sec-chips">
        <a href="<?= htmlspecialchars(buildFilaUrl(['acao' => '', 'pagina' => 1])) ?>" class="fila-sec-chip <?= $filtroAcao === '' ? 'active' : '' ?>"><span><i class="fas fa-layer-group"></i> Todos</span><strong><?= $estatisticas['total'] ?></strong></a>
        <a href="<?= htmlspecialchars(buildFilaUrl(['acao' => 'revisao_setor3', 'pagina' => 1])) ?>" class="fila-sec-chip <?= $filtroAcao === 'revisao_setor3' ? 'active' : '' ?>"><span><i class="fas fa-hourglass-half"></i> Aguardando decisão</span><strong><?= $estatisticas['na_fila'] ?></strong></a>
        <a href="<?= htmlspecialchars(buildFilaUrl(['acao' => 'retorno_aprovado', 'pagina' => 1])) ?>" class="fila-sec-chip <?= $filtroAcao === 'retorno_aprovado' ? 'active' : '' ?>"><span><i class="fas fa-circle-check"></i> Aprovados</span><strong><?= $estatisticas['aprovados'] ?></strong></a>
    </nav>

    <section class="req-filter-bar">
        <form method="GET" class="req-filter-form">
            <?php if ($filtroAcao !== ''): ?><input type="hidden" name="acao" value="<?= htmlspecialchars($filtroAcao) ?>"><?php endif; ?>
            <div class="req-filter-search">
                <i class="fas fa-magnifying-glass"></i>
                <input type="text" name="busca" value="<?= htmlspecialchars($filtroBusca) ?>" placeholder="Buscar por protocolo, nome ou CPF/CNPJ">
            </div>
            <button type="submit" class="toolbar-button toolbar-button-primary">Buscar</button>
            <?php if ($filtroBusca !== '' || $filtroAcao !== ''): ?>
                <a href="fila_revisao_secretario.php" class="toolbar-button">Limpar</a>
            <?php endif; ?>
        </form>
    </section>

    <?php if ($processos): ?>
        <section class="req-list">
            <?php foreach ($processos as $req): ?>
                <?php
                $short = $tipoSiglas[$req['tipo_alvara']] ?? 'ALV';
                $acaoAtual = $req['aguardando_acao'] ?? '';
                ?>
                <article class="req-list-item <?= $req['visualizado'] == 0 ? 'is-unread' : '' ?>">
                    <button type="button" class="req-list-main" onclick="window.location='visualizar_requerimento.php?id=<?= (int) $req['id'] ?>'">
                        <div class="req-list-top">
                            <span class="req-protocol">#<?= htmlspecialchars($req['protocolo']) ?></span>
                            <?php if ($acaoAtual === 'retorno_aprovado'): ?>
                                <span class="badge badge-retorno-aprovado"><i class="fas fa-circle-check" style="font-size:.6rem;opacity:.7;"></i>Aprovado — aguardando setor 2</span>
                            <?php elseif ($acaoAtual === 'retorno_recusado'): ?>
                                <span class="badge badge-retorno-recusado" title="<?= htmlspecialchars($req['motivo_devolucao'] ?? '') ?>"><i class="fas fa-circle-xmark" style="font-size:.6rem;opacity:.7;"></i>Recusado — devolvido</span>
                            <?php elseif (!empty($acaoAtual)): ?>
                                <span class="badge <?= htmlspecialchars(acaoClass($acaoAtual)) ?>" style="font-size:.7rem;"><?= htmlspecialchars(acaoLabel($acaoAtual)) ?></span>
                            <?php endif; ?>
                            <?php if ($req['visualizado'] == 0): ?>
                                <span class="req-unread-pill"><span class="req-unread-dot"></span>Não aberto</span>
                            <?php endif; ?>
                        </div>
                        <div class="req-name"><?= htmlspecialchars($req['requerente_nome']) ?></div>
                        <div class="req-type-row">
                            <span class="req-type-short"><?= htmlspecialchars($short) ?></span>
                            <span class="req-type-name"><?= htmlspecialchars(nomeAlvara($req['tipo_alvara'])) ?></span>
                        </div>
                    </button>
                    <div class="req-list-side">
                        <div class="req-date"><?= formataDataFila($req['data_envio']) ?></div>
                        <a href="visualizar_requerimento.php?id=<?= (int) $req['id'] ?>" class="req-open-button">Ver <i class="fas fa-arrow-right"></i></a>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="req-pagination">
            <div class="req-pagination-copy">Página <?= $paginaAtual ?> de <?= $totalPaginas ?> · <?= $totalRegistros ?> processo(s)</div>
            <div class="req-pagination-links">
                <?php if ($paginaAtual > 1): ?>
                    <a href="<?= htmlspecialchars(buildFilaUrl(['pagina' => $paginaAtual - 1])) ?>" class="req-page-link">‹</a>
                <?php endif; ?>
                <?php for ($i = max(1, $paginaAtual - 2); $i <= min($totalPaginas, $paginaAtual + 2); $i++): ?>
                    <a href="<?= htmlspecialchars(buildFilaUrl(['pagina' => $i])) ?>" class="req-page-link <?= $i === $paginaAtual ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($paginaAtual < $totalPaginas): ?>
                    <a href="<?= htmlspecialchars(buildFilaUrl(['pagina' => $paginaAtual + 1])) ?>" class="req-page-link">›</a>
                <?php endif; ?>
            </div>
        </section>
    <?php else: ?>
        <div class="req-empty">
            <i class="fas fa-search"></i>
            <p>Nenhum processo encontrado no setor 3.</p>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
