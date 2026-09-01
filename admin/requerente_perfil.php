<?php
require_once 'conexao.php';
require_once 'helpers.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../tipos_alvara.php';
verificaLogin();

function reqIniciais(string $nome): string
{
    $partes = preg_split('/\s+/', trim($nome), -1, PREG_SPLIT_NO_EMPTY);
    $partes = $partes ?: [''];
    $iniciais = mb_substr($partes[0], 0, 1);
    if (count($partes) > 1) {
        $iniciais .= mb_substr($partes[count($partes) - 1], 0, 1);
    }
    return mb_strtoupper($iniciais, 'UTF-8');
}

function reqStatusClass(string $status): string
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

$cpfCnpj = trim((string) ($_GET['cpf'] ?? ''));
if ($cpfCnpj === '') {
    header('Location: requerimentos.php');
    exit;
}

// Um CPF pode estar espalhado em vários registros de `requerentes` (o
// formulário público não deduplica no cadastro) — o perfil junta todos os
// processos vinculados a qualquer um deles, não só ao primeiro encontrado.
$stProcessos = $pdo->prepare("
    SELECT r.id, r.protocolo, r.tipo_alvara, r.status, r.data_envio,
           req.nome AS requerente_nome, req.email AS requerente_email, req.telefone AS requerente_telefone
    FROM requerimentos r
    JOIN requerentes req ON req.id = r.requerente_id
    WHERE req.cpf_cnpj = ?
    ORDER BY r.data_envio DESC
");
$stProcessos->execute([$cpfCnpj]);
$processos = $stProcessos->fetchAll(PDO::FETCH_ASSOC);

if (empty($processos)) {
    header('Location: requerimentos.php');
    exit;
}

// Dados de contato mais recentes — a pessoa pode ter atualizado nome/e-mail/telefone
// entre um requerimento e outro; o mais novo é o mais confiável.
$maisRecente = $processos[0];

$totalProcessos = count($processos);
$perPage = 10;
$totalPages = max(1, (int) ceil($totalProcessos / $perPage));
$page = max(1, min($totalPages, (int) ($_GET['p'] ?? 1)));
$processosPagina = array_slice($processos, ($page - 1) * $perPage, $perPage);

$titulo_pagina = 'Requerente';
include 'header.php';
?>
<link rel="stylesheet" href="<?= adminAssetUrl('includes/admin-styles.css') ?>">
<style>
.rt-hero { display:flex; align-items:center; gap:16px; margin-bottom:20px; flex-wrap:wrap; }
.rt-avatar { width:54px; height:54px; border-radius:14px; display:flex; align-items:center; justify-content:center;
    font-size:1.1rem; font-weight:800; color:#fff; flex-shrink:0; letter-spacing:.02em;
    background:linear-gradient(135deg, #2563eb, #2563ebcc); }
.rt-hero h1 { margin:0 0 4px; font-size:1.15rem; font-weight:800; color:var(--req-ink); }
.rt-pill { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:999px; font-size:.72rem; font-weight:800;
    letter-spacing:.02em; background:#eff6ff; color:#1d4ed8; }
.rt-card-box { background:#fff; border:1px solid var(--req-line); border-radius:16px; padding:18px 20px; margin-bottom:18px;
    display:flex; gap:28px; flex-wrap:wrap; }
.rt-meta-item { display:flex; align-items:center; gap:9px; font-size:.86rem; color:var(--req-ink); }
.rt-meta-item i { color:var(--req-muted); width:16px; text-align:center; }
.rt-meta-item.muted { color:var(--req-muted); }
.rt-section-title { font-size:.78rem; font-weight:800; letter-spacing:.06em; text-transform:uppercase; color:var(--req-muted); margin:0 0 12px; }
.rt-obra-row { display:flex; align-items:center; gap:14px; padding:14px 16px; background:#fff; border:1px solid var(--req-line);
    border-radius:12px; margin-bottom:8px; transition:.12s; text-decoration:none; }
.rt-obra-row:hover { border-color:var(--req-line-strong); box-shadow:0 4px 14px rgba(16,33,23,.06); }
.rt-obra-protocolo { font-family:'JetBrains Mono', monospace; font-size:.8rem; font-weight:700; color:var(--req-ink); }
.rt-obra-tipo { font-size:.78rem; color:var(--req-muted); margin-top:2px; }
.rt-obra-side { margin-left:auto; display:flex; align-items:center; gap:14px; }
.rt-obra-date { font-size:.76rem; color:var(--req-muted); white-space:nowrap; }
.rt-back { display:inline-flex; align-items:center; gap:6px; padding:8px 14px; border-radius:10px; border:1px solid var(--req-line);
    color:var(--req-ink); font-size:.82rem; font-weight:700; text-decoration:none; margin-left:auto; transition:.12s; }
.rt-back:hover { border-color:var(--req-line-strong); background:var(--req-primary-soft); color:var(--req-primary); }
.rt-pagination { display:flex; justify-content:center; gap:6px; margin-top:22px; }
.rt-page-link { min-width:36px; height:36px; display:flex; align-items:center; justify-content:center; border-radius:9px;
    border:1px solid var(--req-line); color:var(--req-ink); font-size:.82rem; font-weight:700; text-decoration:none; }
.rt-page-link.active { background:var(--req-primary); border-color:var(--req-primary); color:#fff; }
.rt-page-link:hover:not(.active) { border-color:var(--req-line-strong); background:var(--req-primary-soft); }
</style>

<div class="rt-hero">
    <div class="rt-avatar"><?= htmlspecialchars(reqIniciais($maisRecente['requerente_nome'])) ?></div>
    <div>
        <h1><?= htmlspecialchars($maisRecente['requerente_nome']) ?></h1>
        <span class="rt-pill"><?= htmlspecialchars($cpfCnpj) ?></span>
    </div>
    <a href="requerimentos.php" class="rt-back"><i class="fas fa-arrow-left"></i>Voltar aos requerimentos</a>
</div>

<div class="rt-card-box">
    <div class="rt-meta-item <?= $maisRecente['requerente_email'] ? '' : 'muted' ?>"><i class="fas fa-envelope"></i><?= htmlspecialchars($maisRecente['requerente_email'] ?: 'Sem e-mail informado') ?></div>
    <div class="rt-meta-item <?= $maisRecente['requerente_telefone'] ? '' : 'muted' ?>"><i class="fas fa-phone"></i><?= htmlspecialchars($maisRecente['requerente_telefone'] ?: 'Sem telefone informado') ?></div>
    <div class="rt-meta-item"><i class="fas fa-folder-open"></i><?= count($processos) ?> processo<?= count($processos) === 1 ? '' : 's' ?> com este CPF/CNPJ</div>
</div>

<div class="rt-section-title">Processos deste requerente</div>
<?php foreach ($processosPagina as $proc): ?>
    <a href="visualizar_requerimento.php?id=<?= (int) $proc['id'] ?>" class="rt-obra-row">
        <div>
            <div class="rt-obra-protocolo">#<?= htmlspecialchars($proc['protocolo']) ?></div>
            <div class="rt-obra-tipo"><?= htmlspecialchars($tipos_alvara[$proc['tipo_alvara']]['nome'] ?? ucwords(str_replace('_', ' ', $proc['tipo_alvara']))) ?></div>
        </div>
        <div class="rt-obra-side">
            <span class="badge-status <?= reqStatusClass($proc['status']) ?>"><?= htmlspecialchars($proc['status']) ?></span>
            <span class="rt-obra-date"><?= $proc['data_envio'] ? date('d/m/Y', strtotime($proc['data_envio'])) : '—' ?></span>
            <i class="fas fa-chevron-right" style="color:var(--req-muted);font-size:.78rem;"></i>
        </div>
    </a>
<?php endforeach; ?>

<?php if ($totalPages > 1): ?>
    <div class="rt-pagination">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a href="?cpf=<?= urlencode($cpfCnpj) ?>&p=<?= $p ?>" class="rt-page-link <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div>
<?php endif; ?>

<?php include 'footer.php'; ?>
