<?php
require_once 'conexao.php';
require_once __DIR__ . '/../includes/admin_notifications.php';
verificaLogin();

$tab = $_GET['tab'] ?? 'unread';
if (!in_array($tab, ['unread', 'read', 'all'], true)) {
    $tab = 'unread';
}

if (isset($_GET['acao']) && $_GET['acao'] === 'marcar_todas') {
    markAllAdminNotificationsAsRead($pdo, (int) $_SESSION['admin_id']);
    $redirectTab = $tab === 'unread' ? 'read' : $tab;
    header('Location: notificacoes.php?tab=' . urlencode($redirectTab) . '&success=all_read');
    exit;
}

$itensPorPagina = 20;
$paginaAtual = max(1, (int) ($_GET['pagina'] ?? 1));
$offset = ($paginaAtual - 1) * $itensPorPagina;

$counts = fetchAdminNotificationCounts($pdo, (int) $_SESSION['admin_id']);
$totalAtual = countAdminNotificationsByTab($pdo, (int) $_SESSION['admin_id'], $tab);
$totalPaginas = max(1, (int) ceil($totalAtual / $itensPorPagina));
if ($paginaAtual > $totalPaginas) {
    $paginaAtual = $totalPaginas;
    $offset = ($paginaAtual - 1) * $itensPorPagina;
}

$items = fetchAdminNotifications($pdo, (int) $_SESSION['admin_id'], $tab, $itensPorPagina, $offset);
$mensagem = isset($_GET['success']) && $_GET['success'] === 'all_read' ? 'Todas as notificações foram marcadas como lidas.' : '';

function buildNotificationsUrl(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            unset($params[$key]);
        }
    }
    return 'notificacoes.php' . ($params ? '?' . http_build_query($params) : '');
}

include 'header.php';
?>
<style>
    .notifications-shell { max-width: 1180px; margin: 0 auto; display: flex; flex-direction: column; gap: 18px; }
    .notifications-hero { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; }
    .notifications-title { margin: 0 0 6px; font-size: 1.9rem; line-height: 1.05; font-weight: 800; color: var(--ink); }
    .notifications-subtitle { margin: 0; color: var(--muted); font-size: .92rem; }
    .notifications-summary { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
    .notifications-summary-card { padding: 18px; border: 1px solid var(--line); border-radius: 18px; background: #fff; box-shadow: var(--card-shadow); }
    .notifications-summary-card span { display: block; margin-bottom: 8px; color: var(--muted); font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; }
    .notifications-summary-card strong { display: block; color: var(--ink); font-size: 1.75rem; line-height: 1; font-weight: 800; }
    .notifications-card { border: 1px solid var(--line); border-radius: 22px; background: #fff; box-shadow: var(--card-shadow); overflow: hidden; }
    .notifications-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 18px; border-bottom: 1px solid var(--line); }
    .notifications-tabs { display: inline-flex; align-items: center; padding: 4px; border: 1px solid var(--line); border-radius: 999px; background: var(--surface-soft); gap: 4px; }
    .notifications-tab { min-height: 36px; padding: 0 14px; border-radius: 999px; color: var(--muted); font-size: .84rem; font-weight: 800; display: inline-flex; align-items: center; gap: 8px; }
    .notifications-tab.active { background: #fff; color: var(--primary-strong); box-shadow: 0 8px 18px rgba(16, 33, 23, .06); }
    .notifications-mark-all { color: var(--primary); font-size: .84rem; font-weight: 700; }
    .notifications-list { list-style: none; margin: 0; padding: 18px; display: flex; flex-direction: column; gap: 12px; }
    .notifications-item { border: 1px solid var(--line); border-radius: 18px; background: #fff; transition: border-color .2s ease, transform .2s ease; }
    .notifications-item:hover { border-color: var(--line-strong); transform: translateY(-1px); }
    .notifications-item.is-unread { background: #fbfdfb; border-color: rgba(20, 83, 45, .16); box-shadow: inset 4px 0 0 var(--primary); }
    .notifications-item a, .notifications-empty { display: grid; grid-template-columns: 44px minmax(0, 1fr); gap: 14px; padding: 16px; }
    .notifications-icon { width: 44px; height: 44px; border-radius: 16px; display: inline-flex; align-items: center; justify-content: center; background: var(--surface-tint); color: var(--primary-strong); }
    .notifications-icon.accent-blue { background: #e8effd; color: #1d4ed8; }
    .notifications-icon.accent-amber { background: #fff3dc; color: #b45309; }
    .notifications-icon.accent-teal { background: #e6f7f4; color: #0f766e; }
    .notifications-icon.accent-slate { background: #eef2f0; color: #475569; }
    .notifications-copy { min-width: 0; }
    .notifications-item-title { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; color: var(--ink); font-size: .95rem; font-weight: 800; }
    .notifications-unread-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--primary); flex-shrink: 0; }
    .notifications-description { margin-bottom: 7px; color: var(--muted); font-size: .84rem; line-height: 1.48; }
    .notifications-time { color: var(--muted); font-size: .75rem; display: flex; align-items: center; gap: 6px; }
    .notifications-empty { color: var(--muted); align-items: center; }
    .notifications-pagination { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 0 18px 18px; }
    .notifications-pagination-copy { color: var(--muted); font-size: .82rem; }
    .notifications-pagination-links { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .notifications-page-link { min-width: 38px; height: 38px; padding: 0 12px; border: 1px solid var(--line); border-radius: 12px; background: #fff; color: var(--ink); display: inline-flex; align-items: center; justify-content: center; font-size: .82rem; font-weight: 700; }
    .notifications-page-link.active { background: var(--primary); border-color: var(--primary); color: #fff; }
    .success-inline { margin: 0 0 2px; padding: 14px 16px; border-radius: 16px; background: var(--success-soft); color: var(--success); border: 1px solid #bcdeca; }
    @media (max-width: 991px) {
        .notifications-summary { grid-template-columns: 1fr; }
        .notifications-hero, .notifications-toolbar, .notifications-pagination { flex-direction: column; align-items: flex-start; }
    }
</style>

<div class="notifications-shell">
    <section class="notifications-hero">
        <div>
            <h1 class="notifications-title">Notificações</h1>
            <p class="notifications-subtitle">Central do admin com leitura separada dos protocolos já abertos.</p>
        </div>
    </section>

    <?php if ($mensagem !== ''): ?>
        <div class="success-inline"><?= htmlspecialchars($mensagem) ?></div>
    <?php endif; ?>

    <section class="notifications-summary">
        <article class="notifications-summary-card">
            <span>Não lidas</span>
            <strong><?= (int) $counts['unread'] ?></strong>
        </article>
        <article class="notifications-summary-card">
            <span>Lidas</span>
            <strong><?= (int) $counts['read'] ?></strong>
        </article>
        <article class="notifications-summary-card">
            <span>Total</span>
            <strong><?= (int) $counts['total'] ?></strong>
        </article>
    </section>

    <section class="notifications-card">
        <div class="notifications-toolbar">
            <div class="notifications-tabs">
                <a href="<?= htmlspecialchars(buildNotificationsUrl(['tab' => 'unread', 'pagina' => 1])) ?>" class="notifications-tab <?= $tab === 'unread' ? 'active' : '' ?>">Não lidas <span><?= (int) $counts['unread'] ?></span></a>
                <a href="<?= htmlspecialchars(buildNotificationsUrl(['tab' => 'read', 'pagina' => 1])) ?>" class="notifications-tab <?= $tab === 'read' ? 'active' : '' ?>">Lidas <span><?= (int) $counts['read'] ?></span></a>
                <a href="<?= htmlspecialchars(buildNotificationsUrl(['tab' => 'all', 'pagina' => 1])) ?>" class="notifications-tab <?= $tab === 'all' ? 'active' : '' ?>">Todas <span><?= (int) $counts['total'] ?></span></a>
            </div>
            <a href="<?= htmlspecialchars(buildNotificationsUrl(['acao' => 'marcar_todas', 'pagina' => 1])) ?>" class="notifications-mark-all">Marcar todas como lidas</a>
        </div>

        <ul class="notifications-list">
            <?php if ($items): ?>
                <?php foreach ($items as $item): ?>
                    <li class="notifications-item <?= $item['foi_lida'] ? '' : 'is-unread' ?>">
                        <a href="notificacao_ir.php?id=<?= (int) $item['id'] ?>">
                            <span class="notifications-icon <?= htmlspecialchars($item['accent_class']) ?>">
                                <i class="fas <?= htmlspecialchars($item['icon']) ?>"></i>
                            </span>
                            <span class="notifications-copy">
                                <span class="notifications-item-title">
                                    <?php if (!$item['foi_lida']): ?><span class="notifications-unread-dot"></span><?php endif; ?>
                                    <?= htmlspecialchars($item['titulo']) ?>
                                </span>
                                <span class="notifications-description"><?= htmlspecialchars($item['descricao']) ?></span>
                                <span class="notifications-time"><i class="far fa-clock"></i><?= formataData($item['criado_em']) ?></span>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            <?php else: ?>
                <li class="notifications-item">
                    <div class="notifications-empty">
                        <span class="notifications-icon accent-green"><i class="fas fa-check"></i></span>
                        <span class="notifications-copy">
                            <span class="notifications-item-title">Sem itens neste filtro</span>
                            <span class="notifications-description">Os próximos eventos operacionais vão aparecer aqui.</span>
                        </span>
                    </div>
                </li>
            <?php endif; ?>
        </ul>

        <div class="notifications-pagination">
            <div class="notifications-pagination-copy">
                Página <?= (int) $paginaAtual ?> de <?= (int) $totalPaginas ?> · <?= (int) $totalAtual ?> item(ns)
            </div>
            <div class="notifications-pagination-links">
                <?php if ($paginaAtual > 1): ?>
                    <a href="<?= htmlspecialchars(buildNotificationsUrl(['pagina' => 1])) ?>" class="notifications-page-link">«</a>
                    <a href="<?= htmlspecialchars(buildNotificationsUrl(['pagina' => $paginaAtual - 1])) ?>" class="notifications-page-link">‹</a>
                <?php endif; ?>
                <?php
                $inicio = max(1, $paginaAtual - 2);
                $fim = min($totalPaginas, $paginaAtual + 2);
                for ($i = $inicio; $i <= $fim; $i++):
                ?>
                    <a href="<?= htmlspecialchars(buildNotificationsUrl(['pagina' => $i])) ?>" class="notifications-page-link <?= $i === $paginaAtual ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($paginaAtual < $totalPaginas): ?>
                    <a href="<?= htmlspecialchars(buildNotificationsUrl(['pagina' => $paginaAtual + 1])) ?>" class="notifications-page-link">›</a>
                    <a href="<?= htmlspecialchars(buildNotificationsUrl(['pagina' => $totalPaginas])) ?>" class="notifications-page-link">»</a>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>

<?php include 'footer.php'; ?>
