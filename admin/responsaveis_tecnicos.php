<?php
require_once 'conexao.php';
require_once 'helpers.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../tipos_alvara.php';
verificaLogin();

function rtIniciais(string $nome): string
{
    $partes = preg_split('/\s+/', trim($nome), -1, PREG_SPLIT_NO_EMPTY);
    $partes = $partes ?: [''];
    $iniciais = mb_substr($partes[0], 0, 1);
    if (count($partes) > 1) {
        $iniciais .= mb_substr($partes[count($partes) - 1], 0, 1);
    }
    return mb_strtoupper($iniciais, 'UTF-8');
}

function rtCorConselho(string $conselho): array
{
    return match (strtoupper($conselho)) {
        'CAU' => ['#d97706', '#fff7ed', '#9a5b0a'],
        'CTF' => ['#7c3aed', '#f5f3ff', '#5b21b6'],
        default => ['#2563eb', '#eff6ff', '#1d4ed8'],
    };
}

function rtStatusClass(string $status): string
{
    return match (strtolower($status)) {
        'em análise', 'em_analise' => 'status-em-analise',
        'pendente' => 'status-pendente',
        'finalizado', 'aprovado' => 'status-finalizado',
        'indeferido', 'cancelado' => 'status-indeferido',
        'reprovado' => 'status-reprovado',
        'aguardando fiscalização', 'aguardando fiscalizacao' => 'status-aguardando-fiscalizacao',
        default => 'status-pendente',
    };
}

// Detalhe de um responsável técnico: todas as obras/requerimentos vinculados.
$rtId = (int) ($_GET['id'] ?? 0);
if ($rtId > 0) {
    $stRt = $pdo->prepare('SELECT * FROM responsaveis_tecnicos WHERE id = ?');
    $stRt->execute([$rtId]);
    $rt = $stRt->fetch(PDO::FETCH_ASSOC);

    if (!$rt) {
        header('Location: responsaveis_tecnicos.php');
        exit;
    }

    $stObras = $pdo->prepare("
        SELECT r.id, r.protocolo, r.tipo_alvara, r.status, r.data_envio
        FROM responsavel_tecnico_obras rto
        JOIN requerimentos r ON r.id = rto.requerimento_id
        WHERE rto.responsavel_tecnico_id = ?
        ORDER BY r.data_envio DESC
    ");
    $stObras->execute([$rtId]);
    $obras = $stObras->fetchAll(PDO::FETCH_ASSOC);

    [$corForte, $corSuave, $corTexto] = rtCorConselho($rt['conselho']);

    $titulo_pagina = 'Responsável Técnico';
    include 'header.php';
    ?>
    <link rel="stylesheet" href="<?= adminAssetUrl('includes/admin-styles.css') ?>">
    <style>
    .rt-hero { display:flex; align-items:center; gap:16px; margin-bottom:20px; flex-wrap:wrap; }
    .rt-avatar { width:54px; height:54px; border-radius:14px; display:flex; align-items:center; justify-content:center;
        font-size:1.1rem; font-weight:800; color:#fff; flex-shrink:0; letter-spacing:.02em; }
    .rt-hero h1 { margin:0 0 4px; font-size:1.15rem; font-weight:800; color:var(--req-ink); }
    .rt-pill { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:999px; font-size:.72rem; font-weight:800; letter-spacing:.02em; }
    .rt-card-box { background:#fff; border:1px solid var(--req-line); border-radius:16px; padding:18px 20px; margin-bottom:18px;
        display:flex; gap:28px; flex-wrap:wrap; }
    .rt-meta-item { display:flex; align-items:center; gap:9px; font-size:.86rem; color:var(--req-ink); }
    .rt-meta-item i { color:var(--req-muted); width:16px; text-align:center; }
    .rt-meta-item.muted { color:var(--req-muted); }
    .rt-section-title { font-size:.78rem; font-weight:800; letter-spacing:.06em; text-transform:uppercase; color:var(--req-muted); margin:0 0 12px; }
    .rt-obra-row { display:flex; align-items:center; gap:14px; padding:14px 16px; background:#fff; border:1px solid var(--req-line);
        border-radius:12px; margin-bottom:8px; transition:.12s; }
    .rt-obra-row:hover { border-color:var(--req-line-strong); box-shadow:0 4px 14px rgba(16,33,23,.06); }
    .rt-obra-protocolo { font-family:'JetBrains Mono', monospace; font-size:.8rem; font-weight:700; color:var(--req-ink); }
    .rt-obra-tipo { font-size:.78rem; color:var(--req-muted); margin-top:2px; }
    .rt-obra-side { margin-left:auto; display:flex; align-items:center; gap:14px; }
    .rt-obra-date { font-size:.76rem; color:var(--req-muted); white-space:nowrap; }
    .rt-back { display:inline-flex; align-items:center; gap:6px; padding:8px 14px; border-radius:10px; border:1px solid var(--req-line);
        color:var(--req-ink); font-size:.82rem; font-weight:700; text-decoration:none; margin-left:auto; transition:.12s; }
    .rt-back:hover { border-color:var(--req-line-strong); background:var(--req-primary-soft); color:var(--req-primary); }
    .rt-empty { text-align:center; padding:50px 20px; color:var(--req-muted); background:#fff; border:1px dashed var(--req-line-strong); border-radius:16px; }
    .rt-empty i { display:block; margin-bottom:10px; font-size:1.8rem; color:#b7c2bb; }
    </style>

    <div class="rt-hero">
        <div class="rt-avatar" style="background:linear-gradient(135deg, <?= $corForte ?>, <?= $corForte ?>cc);"><?= htmlspecialchars(rtIniciais($rt['nome'])) ?></div>
        <div>
            <h1><?= htmlspecialchars($rt['nome']) ?></h1>
            <span class="rt-pill" style="background:<?= $corSuave ?>;color:<?= $corTexto ?>;"><?= htmlspecialchars($rt['conselho']) ?> <?= htmlspecialchars($rt['registro']) ?></span>
        </div>
        <a href="responsaveis_tecnicos.php" class="rt-back"><i class="fas fa-arrow-left"></i>Voltar ao catálogo</a>
    </div>

    <div class="rt-card-box">
        <div class="rt-meta-item <?= $rt['email'] ? '' : 'muted' ?>"><i class="fas fa-envelope"></i><?= htmlspecialchars($rt['email'] ?: 'Sem e-mail informado') ?></div>
        <div class="rt-meta-item <?= $rt['telefone'] ? '' : 'muted' ?>"><i class="fas fa-phone"></i><?= htmlspecialchars($rt['telefone'] ?: 'Sem telefone informado') ?></div>
        <div class="rt-meta-item"><i class="fas fa-building"></i><?= count($obras) ?> obra<?= count($obras) === 1 ? '' : 's' ?> vinculada<?= count($obras) === 1 ? '' : 's' ?></div>
    </div>

    <div class="rt-section-title">Requerimentos vinculados</div>
    <?php if (empty($obras)): ?>
        <div class="rt-empty">
            <i class="fas fa-building-circle-xmark"></i>
            <div class="fw-semibold">Nenhum requerimento vinculado a este responsável técnico.</div>
        </div>
    <?php else: ?>
        <?php foreach ($obras as $obra): ?>
            <a href="visualizar_requerimento.php?id=<?= (int) $obra['id'] ?>" class="rt-obra-row" style="text-decoration:none;">
                <div>
                    <div class="rt-obra-protocolo">#<?= htmlspecialchars($obra['protocolo']) ?></div>
                    <div class="rt-obra-tipo"><?= htmlspecialchars($tipos_alvara[$obra['tipo_alvara']]['nome'] ?? ucwords(str_replace('_', ' ', $obra['tipo_alvara']))) ?></div>
                </div>
                <div class="rt-obra-side">
                    <span class="badge-status <?= rtStatusClass($obra['status']) ?>"><?= htmlspecialchars($obra['status']) ?></span>
                    <span class="rt-obra-date"><?= $obra['data_envio'] ? date('d/m/Y', strtotime($obra['data_envio'])) : '—' ?></span>
                    <i class="fas fa-chevron-right" style="color:var(--req-muted);font-size:.78rem;"></i>
                </div>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php
    include 'footer.php';
    exit;
}

// ─── Listagem ───────────────────────────────────────────────────────────────

$termo   = trim((string) ($_GET['q'] ?? ''));
$page    = max(1, (int) ($_GET['p'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where  = '1=1';
$params = [];
if ($termo !== '') {
    $where .= ' AND (nome LIKE ? OR registro LIKE ?)';
    $curinga = '%' . $termo . '%';
    $params[] = $curinga;
    $params[] = $curinga;
}

$stC = $pdo->prepare("SELECT COUNT(*) FROM responsaveis_tecnicos WHERE $where");
$stC->execute($params);
$total = (int) $stC->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));

$st = $pdo->prepare("
    SELECT rt.*, COUNT(rto.requerimento_id) AS total_obras
    FROM responsaveis_tecnicos rt
    LEFT JOIN responsavel_tecnico_obras rto ON rto.responsavel_tecnico_id = rt.id
    WHERE $where
    GROUP BY rt.id
    ORDER BY rt.nome ASC
    LIMIT $perPage OFFSET $offset
");
$st->execute($params);
$responsaveis = $st->fetchAll(PDO::FETCH_ASSOC);

$titulo_pagina = 'Responsáveis Técnicos';
include 'header.php';
?>
<link rel="stylesheet" href="<?= adminAssetUrl('includes/admin-styles.css') ?>">

<style>
/* Cabeçalho de página no mesmo padrão de admin/sugestoes.php */
.rt-hero { display:flex; align-items:center; gap:10px; margin-bottom:14px; flex-wrap:wrap; }
.rt-hero-icon { width:38px; height:38px; border-radius:10px; background:linear-gradient(135deg,#1c4b36,#0d7f5f);
    color:#fff; display:flex; align-items:center; justify-content:center; font-size:1rem; flex-shrink:0; }
.rt-hero h1 { margin:0; font-size:1rem; font-weight:700; color:var(--req-ink); }
.rt-hero p { margin:0; font-size:.82rem; color:var(--req-muted); }
.rt-count-badge { margin-left:auto; padding:5px 12px; border-radius:999px; background:var(--req-primary); color:#fff;
    font-size:.8rem; font-weight:700; }

/* A partir daqui reaproveita literalmente as classes de admin/requerimentos.php
   (.req-filter-bar/.req-list*), pra manter os dois catálogos com a mesma cara. */
.req-list-item.rt-item { grid-template-columns: auto minmax(0, 1fr) auto; }
.rt-avatar { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center;
    font-size:.86rem; font-weight:800; color:#fff; flex-shrink:0; }
.rt-empty { text-align:center; padding:60px 20px; color:var(--req-muted); background:#fff; border:1px dashed var(--req-line-strong); border-radius:18px; }
.rt-empty i { display:block; margin-bottom:12px; font-size:1.9rem; color:#b7c2bb; }
.rt-pagination { display:flex; justify-content:center; gap:6px; margin-top:22px; }
.rt-page-link { min-width:36px; height:36px; display:flex; align-items:center; justify-content:center; border-radius:9px;
    border:1px solid var(--req-line); color:var(--req-ink); font-size:.82rem; font-weight:700; text-decoration:none; }
.rt-page-link.active { background:var(--req-primary); border-color:var(--req-primary); color:#fff; }
.rt-page-link:hover:not(.active) { border-color:var(--req-line-strong); background:var(--req-primary-soft); }
</style>

<div class="rt-hero">
    <div class="rt-hero-icon"><i class="fas fa-hard-hat"></i></div>
    <div>
        <h1>Responsáveis Técnicos</h1>
        <p>Catálogo de engenheiros/arquitetos vinculados a requerimentos</p>
    </div>
    <span class="rt-count-badge"><?= $total ?> cadastrado<?= $total === 1 ? '' : 's' ?></span>
</div>

<section class="req-filter-bar">
    <form method="get" class="req-filter-form">
        <div class="req-filter-search">
            <i class="fas fa-magnifying-glass"></i>
            <input type="text" name="q" placeholder="Buscar por nome ou registro (CREA/CAU/CTF)…" value="<?= htmlspecialchars($termo) ?>">
        </div>
        <?php if ($termo !== ''): ?>
            <a href="responsaveis_tecnicos.php" class="req-open-button"><i class="fas fa-xmark"></i>Limpar busca</a>
        <?php endif; ?>
    </form>
</section>

<?php if (empty($responsaveis)): ?>
    <div class="rt-empty">
        <i class="fas fa-user-slash"></i>
        <div class="fw-semibold">Nenhum responsável técnico encontrado</div>
        <div style="font-size:.85rem;margin-top:4px;">O catálogo é preenchido automaticamente quando um requerimento informa um responsável técnico.</div>
    </div>
<?php else: ?>
    <section class="req-list">
        <?php foreach ($responsaveis as $rt): [$corForte, $corSuave, $corTexto] = rtCorConselho($rt['conselho']); ?>
            <article class="req-list-item rt-item">
                <div class="rt-avatar" style="background:linear-gradient(135deg, <?= $corForte ?>, <?= $corForte ?>cc);"><?= htmlspecialchars(rtIniciais($rt['nome'])) ?></div>
                <a href="responsaveis_tecnicos.php?id=<?= (int) $rt['id'] ?>" class="req-list-main" style="text-decoration:none;display:block;">
                    <div class="req-list-top">
                        <span class="feed-type-badge" style="background:<?= $corSuave ?>;color:<?= $corTexto ?>;"><i class="fas fa-hard-hat"></i>Responsável técnico</span>
                        <span class="req-protocol">Nº <?= htmlspecialchars($rt['registro']) ?></span>
                    </div>
                    <div class="req-name"><?= htmlspecialchars($rt['nome']) ?></div>
                    <div class="req-type-row">
                        <span class="req-type-short" style="background:<?= $corSuave ?>;color:<?= $corTexto ?>;"><?= htmlspecialchars($rt['conselho']) ?></span>
                        <span class="req-type-name"><i class="fas fa-building" style="margin-right:5px;"></i><?= (int) $rt['total_obras'] ?> obra<?= (int) $rt['total_obras'] === 1 ? '' : 's' ?> vinculada<?= (int) $rt['total_obras'] === 1 ? '' : 's' ?></span>
                    </div>
                    <?php if ($rt['email'] || $rt['telefone']): ?>
                        <div class="req-preview">
                            <?= $rt['email'] ? htmlspecialchars($rt['email']) : '' ?>
                            <?= ($rt['email'] && $rt['telefone']) ? ' · ' : '' ?>
                            <?= $rt['telefone'] ? htmlspecialchars($rt['telefone']) : '' ?>
                        </div>
                    <?php endif; ?>
                </a>
                <div class="req-list-side">
                    <a href="responsaveis_tecnicos.php?id=<?= (int) $rt['id'] ?>" class="req-open-button">Abrir <i class="fas fa-arrow-right"></i></a>
                </div>
            </article>
        <?php endforeach; ?>
    </section>

    <?php if ($totalPages > 1): ?>
        <div class="rt-pagination">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a href="?p=<?= $p ?><?= $termo !== '' ? '&q=' . urlencode($termo) : '' ?>"
                   class="rt-page-link <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php include 'footer.php'; ?>
