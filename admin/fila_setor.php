<?php
require_once 'conexao.php';
require_once 'helpers.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../tipos_alvara.php';
verificaLogin();

$setorParam = $_GET['setor'] ?? 'setor1';
if (!in_array($setorParam, ['setor1','setor2','setor3'], true)) {
    $setorParam = 'setor1';
}

$setorMeta = [
    'setor1' => ['label' => 'Setor 1', 'sublabel' => 'Triagem', 'icon' => 'fa-inbox'],
    'setor2' => ['label' => 'Setor 2', 'sublabel' => 'Análise', 'icon' => 'fa-magnifying-glass'],
    'setor3' => ['label' => 'Setor 3', 'sublabel' => 'Revisão Final', 'icon' => 'fa-shield-halved'],
];

// Labels centralizados via helpers.php (acaoLabel / acaoClass)

$tipoSiglas = [
    'licenca_ambiental_unica'   => 'LAU',
    'habite_se'                 => 'HBT',
    'habite_se_simples'         => 'HBS',
    'construcao'                => 'CNS',
    'licenca_previa_obras'      => 'LPO',
    'desmembramento'            => 'DSM',
    'licenca_previa_ambiental'  => 'LPA',
];

// Contagens por setor para as abas
$contagens = [];
foreach (['setor1','setor2','setor3'] as $s) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM requerimentos WHERE setor_atual = ? AND aguardando_acao != 'concluido'");
    $st->execute([$s]);
    $contagens[$s] = (int) $st->fetchColumn();
}

$itensPorPagina = 30;
$paginaAtual = max(1, (int) ($_GET['pagina'] ?? 1));
$offset = ($paginaAtual - 1) * $itensPorPagina;

$filtroBusca = trim($_GET['busca'] ?? '');

$sql = "SELECT r.id, r.protocolo, r.tipo_alvara, r.status, r.setor_atual, r.aguardando_acao, r.data_envio, r.data_atualizacao, req.nome AS requerente
        FROM requerimentos r
        JOIN requerentes req ON r.requerente_id = req.id
        WHERE r.setor_atual = ? AND r.aguardando_acao != 'concluido'";

$params = [$setorParam];

if ($filtroBusca !== '') {
    $sql .= " AND (r.protocolo LIKE ? OR req.nome LIKE ?)";
    $t = '%' . $filtroBusca . '%';
    $params[] = $t;
    $params[] = $t;
}

$sqlCount = str_replace(
    "r.id, r.protocolo, r.tipo_alvara, r.status, r.setor_atual, r.aguardando_acao, r.data_envio, r.data_atualizacao, req.nome AS requerente",
    "COUNT(*) AS total",
    $sql
);

$sql .= " ORDER BY r.data_envio ASC LIMIT {$itensPorPagina} OFFSET {$offset}";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$processos = $stmt->fetchAll();

$stmtC = $pdo->prepare($sqlCount);
$stmtC->execute($params);
$totalProcessos = (int) ($stmtC->fetch()['total'] ?? 0);
$totalPaginas = max(1, (int) ceil($totalProcessos / $itensPorPagina));

include 'header.php';

function tempoEmFila(string $dataEnvio): string
{
    $diff = time() - strtotime($dataEnvio);
    if ($diff < 3600) return floor($diff/60) . 'm';
    if ($diff < 86400) return floor($diff/3600) . 'h';
    $d = floor($diff/86400);
    return $d . ($d === 1 ? ' dia' : ' dias');
}
?>
<link rel="stylesheet" href="includes/admin-styles.css">
<style>
.fila-shell { max-width:1240px; margin:0 auto; }
.setor-tabs { display:flex; gap:6px; margin-bottom:20px; flex-wrap:wrap; }
.setor-tab { display:flex; flex-direction:column; align-items:flex-start; padding:12px 18px; border:2px solid var(--req-line); border-radius:14px; background:#fff; text-decoration:none; color:var(--req-ink); min-width:130px; transition:all .15s; }
.setor-tab:hover { border-color:var(--req-primary); color:var(--req-primary); }
.setor-tab.active { border-color:var(--req-primary); background:var(--req-primary-soft); color:var(--req-primary); }
.setor-tab-label { font-size:.82rem; font-weight:800; letter-spacing:.03em; }
.setor-tab-sublabel { font-size:.73rem; color:var(--req-muted); }
.setor-tab .count-badge { margin-top:6px; display:inline-flex; align-items:center; justify-content:center; min-width:26px; padding:2px 8px; border-radius:999px; background:var(--req-primary); color:#fff; font-size:.72rem; font-weight:800; }
.setor-tab:not(.active) .count-badge { background:var(--req-line-strong); color:var(--req-muted); }

.fila-header { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:14px; flex-wrap:wrap; }
.fila-title { margin:0; font-size:1.5rem; font-weight:800; color:var(--req-ink); }
.fila-sublabel { color:var(--req-muted); font-size:.9rem; margin:0; }
.fila-search { display:flex; align-items:center; gap:8px; padding:8px 14px; border:1px solid var(--req-line); border-radius:10px; background:#fff; }
.fila-search input { border:none; outline:none; font-size:.88rem; width:220px; background:transparent; }

.fila-list { display:flex; flex-direction:column; gap:8px; }
.fila-item { display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:center; gap:16px; padding:14px 18px; border:1px solid var(--req-line); border-radius:14px; background:#fff; text-decoration:none; color:inherit; transition:border-color .15s, background .15s; }
.fila-item:hover { border-color:var(--req-primary); background:var(--req-primary-soft); }
.fila-item.destaque { border-left:4px solid #d97706; }
.fila-protocol { font-size:.8rem; font-weight:800; color:var(--req-primary); letter-spacing:.03em; }
.fila-nome { font-size:.97rem; font-weight:700; color:var(--req-ink); margin:3px 0; }
.fila-meta { display:flex; flex-wrap:wrap; align-items:center; gap:10px; font-size:.78rem; color:var(--req-muted); }
.tipo-badge { display:inline-flex; padding:2px 8px; border-radius:999px; background:var(--req-primary-soft); color:var(--req-primary); font-size:.7rem; font-weight:800; letter-spacing:.06em; }
.acao-badge { display:inline-flex; padding:3px 10px; border-radius:999px; font-size:.74rem; font-weight:700; }
.acao-triagem  { background:#e8effd; color:#3762d9; }
.acao-boleto   { background:#fff3dc; color:#b7791f; }
.acao-analise  { background:#e3f3e8; color:#14532d; }
.acao-revisao  { background:#f3e8ff; color:#7e22ce; }
.acao-envio    { background:#e0f2fe; color:#0369a1; }
.acao-concluido{ background:#f1f5f0; color:#666; }
.fila-tempo { font-size:.78rem; color:var(--req-muted); }
.fila-item-side { display:flex; flex-direction:column; align-items:flex-end; gap:8px; }
.btn-abrir { display:inline-flex; align-items:center; gap:6px; padding:6px 16px; border:1px solid var(--req-line); border-radius:10px; background:#fff; color:var(--req-ink); font-size:.82rem; font-weight:700; text-decoration:none; transition:all .15s; }
.btn-abrir:hover { border-color:var(--req-primary); color:var(--req-primary); }
.proxima-acao-hint { font-size:.72rem; font-weight:700; color:var(--req-primary); opacity:.75; white-space:nowrap; }
.fila-empty { padding:32px; text-align:center; border:1px dashed var(--req-line-strong); border-radius:14px; color:var(--req-muted); }
.fila-paginacao { display:flex; gap:8px; justify-content:center; margin-top:18px; }
.fila-paginacao a, .fila-paginacao span { padding:6px 14px; border:1px solid var(--req-line); border-radius:8px; font-size:.82rem; text-decoration:none; color:var(--req-ink); }
.fila-paginacao a:hover { border-color:var(--req-primary); color:var(--req-primary); }
.fila-paginacao span.current { background:var(--req-primary); color:#fff; border-color:var(--req-primary); font-weight:700; }
@media (max-width:600px) {
  .fila-item { grid-template-columns:1fr; }
  .fila-item-side { align-items:flex-start; flex-direction:row; }
}
</style>

<div class="fila-shell">
    <nav class="setor-tabs">
        <?php foreach ($setorMeta as $s => $sm): ?>
            <a href="fila_setor.php?setor=<?= $s ?>" class="setor-tab <?= $s === $setorParam ? 'active' : '' ?>">
                <span class="setor-tab-label"><i class="fas <?= $sm['icon'] ?> me-1"></i><?= $sm['label'] ?></span>
                <span class="setor-tab-sublabel"><?= $sm['sublabel'] ?></span>
                <span class="count-badge"><?= $contagens[$s] ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="fila-header">
        <div>
            <h1 class="fila-title"><?= $setorMeta[$setorParam]['label'] ?> — <?= $setorMeta[$setorParam]['sublabel'] ?></h1>
            <p class="fila-sublabel">
                <?= $totalProcessos ?> processo<?= $totalProcessos !== 1 ? 's' : '' ?> na fila · ordem: mais antigo primeiro
            </p>
        </div>
        <form method="GET" class="fila-search">
            <input type="hidden" name="setor" value="<?= htmlspecialchars($setorParam) ?>">
            <i class="fas fa-magnifying-glass" style="color:var(--req-muted)"></i>
            <input type="text" name="busca" value="<?= htmlspecialchars($filtroBusca) ?>" placeholder="Protocolo ou nome...">
        </form>
    </div>

    <?php if ($processos): ?>
        <div class="fila-list">
            <?php foreach ($processos as $p): ?>
                <?php
                $acao    = $p['aguardando_acao'] ?? 'triagem_setor1';
                $short   = $tipoSiglas[$p['tipo_alvara']] ?? 'ALV';
                $nomeAlv = $tipos_alvara[$p['tipo_alvara']]['nome'] ?? ucwords(str_replace('_', ' ', $p['tipo_alvara']));
                $destaque = $acao === 'boleto_pendente' || $acao === 'envio_cidadao';
                $tempo   = tempoEmFila($p['data_envio']);
                ?>
                <div class="fila-item <?= $destaque ? 'destaque' : '' ?>">
                    <div>
                        <div class="fila-protocol">#<?= htmlspecialchars($p['protocolo']) ?></div>
                        <div class="fila-nome"><?= htmlspecialchars($p['requerente']) ?></div>
                        <div class="fila-meta">
                            <span class="tipo-badge"><?= $short ?></span>
                            <span><?= htmlspecialchars($nomeAlv) ?></span>
                            <span class="acao-badge <?= acaoClass($acao) ?>"><?= acaoLabel($acao) ?></span>
                            <span class="fila-tempo"><i class="far fa-clock me-1"></i><?= $tempo ?> na fila</span>
                        </div>
                    </div>
                    <div class="fila-item-side">
                        <span class="proxima-acao-hint"><i class="fas fa-bolt me-1"></i><?= acaoLabel($acao) ?></span>
                        <a href="visualizar_requerimento.php?id=<?= (int) $p['id'] ?>" class="btn-abrir">
                            <i class="fas fa-arrow-right"></i> Abrir
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPaginas > 1): ?>
            <div class="fila-paginacao">
                <?php if ($paginaAtual > 1): ?>
                    <a href="?setor=<?= $setorParam ?>&pagina=<?= $paginaAtual - 1 ?>&busca=<?= urlencode($filtroBusca) ?>">&#8592;</a>
                <?php endif; ?>
                <?php for ($pg = 1; $pg <= $totalPaginas; $pg++): ?>
                    <?php if ($pg === $paginaAtual): ?>
                        <span class="current"><?= $pg ?></span>
                    <?php else: ?>
                        <a href="?setor=<?= $setorParam ?>&pagina=<?= $pg ?>&busca=<?= urlencode($filtroBusca) ?>"><?= $pg ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($paginaAtual < $totalPaginas): ?>
                    <a href="?setor=<?= $setorParam ?>&pagina=<?= $paginaAtual + 1 ?>&busca=<?= urlencode($filtroBusca) ?>">&#8594;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="fila-empty">
            <i class="fas fa-check-circle fa-2x mb-2" style="color:var(--req-primary);display:block"></i>
            Nenhum processo na fila do <?= $setorMeta[$setorParam]['label'] ?> no momento.
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
