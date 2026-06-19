<?php
require_once 'conexao.php';
require_once __DIR__ . '/../includes/functions.php';
verificaLogin();

$nivelAtual = $_SESSION['admin_nivel'] ?? '';
if (!in_array($nivelAtual, ['admin', 'admin_geral'], true)) {
    header('Location: index.php');
    exit;
}

// Atualizar status via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $sid    = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'];

    if ($action === 'status' && $sid) {
        $novoStatus = $_POST['status'] ?? '';
        $valid = ['nova','lida','em_analise','implementada','descartada'];
        if (in_array($novoStatus, $valid, true)) {
            $pdo->prepare("UPDATE sugestoes SET status = ? WHERE id = ?")->execute([$novoStatus, $sid]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Status inválido.']);
        }
        exit;
    }
    if ($action === 'nota' && $sid) {
        $nota = mb_substr(trim($_POST['nota'] ?? ''), 0, 1000);
        $pdo->prepare("UPDATE sugestoes SET nota_admin = ? WHERE id = ?")->execute([$nota ?: null, $sid]);
        echo json_encode(['success' => true]);
        exit;
    }
    echo json_encode(['success' => false]);
    exit;
}

// Filtros
$filtroTipo   = $_GET['tipo']   ?? '';
$filtroStatus = $_GET['status'] ?? 'nova';
$page         = max(1, (int) ($_GET['p'] ?? 1));
$perPage      = 20;
$offset       = ($page - 1) * $perPage;

$where  = '1=1';
$params = [];
if ($filtroTipo   !== '') { $where .= ' AND tipo = ?';   $params[] = $filtroTipo; }
if ($filtroStatus !== '') { $where .= ' AND status = ?'; $params[] = $filtroStatus; }

$total = (int) $pdo->prepare("SELECT COUNT(*) FROM sugestoes WHERE $where")->execute($params) ?
         $pdo->prepare("SELECT COUNT(*) FROM sugestoes WHERE $where")->execute($params) && 0 : 0;
$stC = $pdo->prepare("SELECT COUNT(*) FROM sugestoes WHERE $where");
$stC->execute($params);
$total = (int) $stC->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));

$st = $pdo->prepare("SELECT * FROM sugestoes WHERE $where ORDER BY criado_em DESC LIMIT $perPage OFFSET $offset");
$st->execute($params);
$sugestoes = $st->fetchAll(PDO::FETCH_ASSOC);

// Contadores por status
$contadores = [];
foreach (['nova','lida','em_analise','implementada','descartada'] as $s) {
    $stCnt = $pdo->prepare("SELECT COUNT(*) FROM sugestoes WHERE status = ?");
    $stCnt->execute([$s]);
    $contadores[$s] = (int) $stCnt->fetchColumn();
}

$titulo_pagina = 'Sugestões';
include 'header.php';

$labelStatus = [
    'nova'         => ['Nova',          'bg-primary'],
    'lida'         => ['Lida',          'bg-secondary'],
    'em_analise'   => ['Em análise',    'bg-warning text-dark'],
    'implementada' => ['Implementada',  'bg-success'],
    'descartada'   => ['Descartada',    'bg-danger'],
];
$labelTipo = [
    'melhoria'    => ['Melhoria',    '#eff6ff','#1d4ed8'],
    'dificuldade' => ['Dificuldade', '#fff7ed','#c2410c'],
    'elogio'      => ['Elogio',      '#f0fdf4','#15803d'],
    'outro'       => ['Outro',       '#faf5ff','#7e22ce'],
];
?>
<style>
.sg-filters { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:20px; }
.sg-filter-btn { padding:6px 14px; border-radius:20px; border:1.5px solid #e2e8f0; background:#fff;
    font-size:.78rem; font-weight:600; color:#64748b; cursor:pointer; transition:.12s; text-decoration:none; }
.sg-filter-btn.active, .sg-filter-btn:hover { border-color:#1c4b36; color:#1c4b36; background:#f0f7f3; }
.sg-card { background:#fff; border:1px solid #e6eaf0; border-radius:12px; padding:16px 18px;
    margin-bottom:10px; transition:.12s; }
.sg-card:hover { box-shadow:0 4px 16px rgba(0,0,0,.07); }
.sg-tipo { display:inline-block; padding:2px 9px; border-radius:20px; font-size:.7rem; font-weight:700; }
.sg-texto { font-size:.88rem; color:#1e293b; line-height:1.55; margin:8px 0 6px; white-space:pre-wrap; word-break:break-word; }
.sg-meta { font-size:.72rem; color:#94a3b8; }
.sg-actions { display:flex; gap:6px; margin-top:10px; flex-wrap:wrap; }
.sg-status-sel { font-size:.75rem; padding:4px 8px; border:1px solid #e2e8f0; border-radius:6px;
    background:#f8fafc; cursor:pointer; }
.nota-form { margin-top:8px; display:none; }
.nota-form textarea { width:100%; font-size:.8rem; padding:7px 10px; border:1px solid #e2e8f0;
    border-radius:8px; resize:vertical; min-height:60px; }
.nota-form .btn-nota { margin-top:4px; }
.sg-nota-text { font-size:.75rem; color:#475569; background:#fffbeb; border:1px solid #fde68a;
    border-radius:7px; padding:6px 10px; margin-top:6px; }
.empty-state { text-align:center; padding:60px 20px; color:#94a3b8; }
</style>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
    <div style="width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,#1c4b36,#0d7f5f);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1rem;">
        <i class="fas fa-lightbulb"></i>
    </div>
    <div>
        <h5 class="mb-0 fw-bold">Sugestões dos Cidadãos</h5>
        <small class="text-muted">Melhorias reportadas pelos usuários do portal</small>
    </div>
    <span class="badge bg-primary ms-auto" style="font-size:.8rem;"><?= $contadores['nova'] ?> novas</span>
</div>

<!-- Filtros por status -->
<div class="sg-filters">
    <?php foreach ($contadores as $s => $cnt):
        $ativo = $filtroStatus === $s ? 'active' : '';
        [$label] = $labelStatus[$s];
    ?>
        <a href="?status=<?= $s ?><?= $filtroTipo ? '&tipo='.$filtroTipo : '' ?>"
           class="sg-filter-btn <?= $ativo ?>"><?= $label ?> <span style="opacity:.7">(<?= $cnt ?>)</span></a>
    <?php endforeach; ?>
    <a href="?status=<?= $filtroTipo ? '&tipo='.$filtroTipo : '' ?>"
       class="sg-filter-btn <?= $filtroStatus === '' ? 'active' : '' ?>">Todas</a>
</div>

<!-- Filtros por tipo -->
<div class="sg-filters" style="margin-top:-10px;">
    <?php foreach ($labelTipo as $t => [$lbl, $bg, $cor]):
        $ativo = $filtroTipo === $t ? 'active' : '';
    ?>
        <a href="?tipo=<?= $t ?><?= $filtroStatus ? '&status='.$filtroStatus : '' ?>"
           class="sg-filter-btn <?= $ativo ?>"><?= $lbl ?></a>
    <?php endforeach; ?>
    <?php if ($filtroTipo): ?>
        <a href="?<?= $filtroStatus ? 'status='.$filtroStatus : '' ?>" class="sg-filter-btn">✕ Limpar tipo</a>
    <?php endif; ?>
</div>

<?php if (empty($sugestoes)): ?>
    <div class="empty-state">
        <div style="font-size:2.5rem;margin-bottom:12px;">💡</div>
        <div class="fw-semibold">Nenhuma sugestão encontrada</div>
        <div style="font-size:.85rem;">Quando cidadãos enviarem sugestões, elas aparecerão aqui.</div>
    </div>
<?php else: ?>
    <?php foreach ($sugestoes as $sg):
        [$tipoLabel, $tipoBg, $tipoCor] = $labelTipo[$sg['tipo']] ?? ['Outro','#f5f5f5','#555'];
        [$statusLabel, $statusClass]    = $labelStatus[$sg['status']] ?? ['?', 'bg-secondary'];
    ?>
        <div class="sg-card" id="sg-<?= $sg['id'] ?>">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="sg-tipo" style="background:<?= $tipoBg ?>;color:<?= $tipoCor ?>;"><?= $tipoLabel ?></span>
                <span class="badge <?= $statusClass ?>" style="font-size:.65rem;"><?= $statusLabel ?></span>
                <span class="sg-meta ms-auto">#<?= $sg['id'] ?> · <?= date('d/m/Y H:i', strtotime($sg['criado_em'])) ?></span>
            </div>

            <div class="sg-texto"><?= htmlspecialchars($sg['texto']) ?></div>

            <div class="sg-meta">
                <?php if ($sg['nome'] || $sg['email']): ?>
                    <i class="fas fa-user me-1"></i>
                    <?= htmlspecialchars($sg['nome'] ?? '') ?>
                    <?= $sg['email'] ? '&lt;'.htmlspecialchars($sg['email']).'&gt;' : '' ?> ·
                <?php endif; ?>
                <i class="fas fa-globe me-1"></i><?= htmlspecialchars($sg['ip_origem'] ?? '—') ?>
            </div>

            <?php if ($sg['nota_admin']): ?>
                <div class="sg-nota-text"><i class="fas fa-note-sticky me-1"></i><?= htmlspecialchars($sg['nota_admin']) ?></div>
            <?php endif; ?>

            <div class="sg-actions">
                <select class="sg-status-sel" onchange="atualizarStatus(<?= $sg['id'] ?>, this.value)">
                    <?php foreach ($labelStatus as $sv => [$slabel, $sc]): ?>
                        <option value="<?= $sv ?>" <?= $sg['status'] === $sv ? 'selected' : '' ?>><?= $slabel ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-outline-secondary" style="font-size:.75rem;"
                        onclick="toggleNota(<?= $sg['id'] ?>)">
                    <i class="fas fa-note-sticky me-1"></i>Nota
                </button>
            </div>

            <div class="nota-form" id="nota-<?= $sg['id'] ?>">
                <textarea placeholder="Nota interna sobre esta sugestão…"><?= htmlspecialchars($sg['nota_admin'] ?? '') ?></textarea>
                <button class="btn btn-sm btn-outline-primary btn-nota" onclick="salvarNota(<?= $sg['id'] ?>)">Salvar nota</button>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Paginação -->
    <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-center gap-2 mt-4">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a href="?p=<?= $p ?><?= $filtroStatus ? '&status='.$filtroStatus : '' ?><?= $filtroTipo ? '&tipo='.$filtroTipo : '' ?>"
                   class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<script>
function atualizarStatus(id, status) {
    const fd = new FormData();
    fd.append('action', 'status');
    fd.append('id', id);
    fd.append('status', status);
    fetch('sugestoes.php', { method:'POST', body:fd })
        .then(r => r.json())
        .then(d => { if (!d.success) alert('Erro ao atualizar.'); });
}

function toggleNota(id) {
    const el = document.getElementById('nota-' + id);
    el.style.display = el.style.display === 'none' || !el.style.display ? 'block' : 'none';
    if (el.style.display === 'block') el.querySelector('textarea').focus();
}

function salvarNota(id) {
    const nota = document.querySelector('#nota-' + id + ' textarea').value;
    const fd = new FormData();
    fd.append('action', 'nota');
    fd.append('id', id);
    fd.append('nota', nota);
    fetch('sugestoes.php', { method:'POST', body:fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                document.getElementById('nota-' + id).style.display = 'none';
                location.reload();
            } else alert('Erro ao salvar nota.');
        });
}
</script>

<?php include 'footer.php'; ?>
