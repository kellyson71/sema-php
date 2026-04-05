<?php
require_once 'conexao.php';
verificaLogin();

// Apenas admins e admin_geral podem ver esta página
if (!in_array($_SESSION['admin_nivel'], ['admin', 'admin_geral'])) {
    header('Location: index.php');
    exit;
}

$resultsDir = dirname(__DIR__) . '/tests/results';

function lerJson(string $path): array
{
    if (!file_exists($path)) return [];
    $decoded = json_decode(file_get_contents($path), true);
    return is_array($decoded) ? $decoded : [];
}

$meta      = lerJson($resultsDir . '/meta.json');
$phpunit   = lerJson($resultsDir . '/phpunit-results.json');
$playwright = lerJson($resultsDir . '/playwright-results.json');

$temResultados = !empty($meta);

function formatDuration(int $ms): string
{
    if ($ms < 1000) return "{$ms}ms";
    return round($ms / 1000, 2) . 's';
}

function formatTimestamp(string $ts): string
{
    if (!$ts) return '—';
    $dt = new DateTime($ts, new DateTimeZone('UTC'));
    $dt->setTimezone(new DateTimeZone('America/Fortaleza'));
    return $dt->format('d/m/Y \à\s H:i:s');
}

function pct(int $passed, int $total): int
{
    return $total > 0 ? (int) round($passed * 100 / $total) : 0;
}

$unSummary = $phpunit['summary'] ?? ['total' => 0, 'passed' => 0, 'failed' => 0, 'skipped' => 0, 'duration_ms' => 0];
$e2eSummary = $playwright['summary'] ?? ['total' => 0, 'passed' => 0, 'failed' => 0, 'skipped' => 0, 'duration_ms' => 0];

$totalGeral  = ($unSummary['total'] ?? 0) + ($e2eSummary['total'] ?? 0);
$passedGeral = ($unSummary['passed'] ?? 0) + ($e2eSummary['passed'] ?? 0);
$failedGeral = ($unSummary['failed'] ?? 0) + ($e2eSummary['failed'] ?? 0);
$pctGeral    = pct($passedGeral, $totalGeral);

$statusGeral = $failedGeral === 0 && $totalGeral > 0 ? 'success' : ($totalGeral === 0 ? 'secondary' : 'danger');
$iconGeral   = $failedGeral === 0 && $totalGeral > 0 ? 'fa-check-circle' : ($totalGeral === 0 ? 'fa-question-circle' : 'fa-times-circle');

$currentPage = 'testes.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Testes — SEMA Homologação</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2D8661;
            --secondary-color: #134E5E;
            --accent-color: #47AF8C;
            --sidebar-width: 250px;
            --topbar-height: 60px;
        }

        body { font-family: 'Segoe UI', sans-serif; background: #f0f4f8; }

        /* ── Sidebar (reaproveitada do header.php) ── */
        .sidebar {
            position: fixed; left: 0; top: 0; height: 100vh;
            width: var(--sidebar-width); background: linear-gradient(180deg, var(--secondary-color), #0a2a35);
            z-index: 1000; overflow-y: auto; display: flex; flex-direction: column;
        }
        .sidebar-header { padding: 15px; border-bottom: 1px solid rgba(255,255,255,.1); text-align: center; }
        .sidebar-logo { max-height: 55px; }
        .sidebar-menu { padding: 15px 0; flex: 1; }
        .menu-header { color: rgba(255,255,255,.5); font-size: .7rem; font-weight: 700;
            letter-spacing: 1px; text-transform: uppercase; padding: 10px 20px 5px; }
        .sidebar-menu ul { list-style: none; padding: 0; margin: 0; }
        .sidebar-menu li { margin: 2px 10px; }
        .sidebar-menu a {
            display: flex; align-items: center; gap: 10px; padding: 10px 15px;
            color: rgba(255,255,255,.85); text-decoration: none; border-radius: 8px;
            transition: all .2s;
        }
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: rgba(71,175,140,.25); color: #fff;
        }
        .content-wrapper { margin-left: var(--sidebar-width); padding: 20px; min-height: 100vh; }

        /* ── Topbar ── */
        .topbar {
            background: #fff; padding: 0 20px; height: var(--topbar-height);
            display: flex; align-items: center; justify-content: space-between;
            border-bottom: 1px solid #e2e8f0; position: sticky; top: 0; z-index: 100;
            box-shadow: 0 1px 4px rgba(0,0,0,.06);
        }

        /* ── Badge de ambiente ── */
        .badge-homolog {
            background: #f59e0b; color: #000; font-size: .7rem; font-weight: 700;
            padding: 3px 10px; border-radius: 20px; letter-spacing: .5px; text-transform: uppercase;
        }

        /* ── Cards de resumo ── */
        .stat-card {
            background: #fff; border-radius: 12px; padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,.06); border-left: 4px solid;
            transition: transform .2s;
        }
        .stat-card:hover { transform: translateY(-2px); }
        .stat-card.success { border-color: #10b981; }
        .stat-card.danger  { border-color: #ef4444; }
        .stat-card.warning { border-color: #f59e0b; }
        .stat-card.info    { border-color: #3b82f6; }
        .stat-card.secondary { border-color: #94a3b8; }
        .stat-card .stat-icon { font-size: 2rem; margin-bottom: 8px; }
        .stat-card .stat-value { font-size: 2rem; font-weight: 700; line-height: 1; }
        .stat-card .stat-label { color: #64748b; font-size: .85rem; margin-top: 4px; }

        /* ── Progress bar ── */
        .progress { height: 10px; border-radius: 10px; background: #e2e8f0; }
        .progress-bar { border-radius: 10px; }

        /* ── Accordion de testes ── */
        .test-suite-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 12px 16px; background: #f8fafc; border: 1px solid #e2e8f0;
            border-radius: 8px; cursor: pointer; transition: background .15s;
        }
        .test-suite-header:hover { background: #f1f5f9; }
        .test-row {
            display: flex; align-items: center; padding: 8px 16px;
            border-bottom: 1px solid #f1f5f9; font-size: .875rem;
        }
        .test-row:last-child { border-bottom: none; }
        .test-row.passed .test-icon { color: #10b981; }
        .test-row.failed .test-icon { color: #ef4444; }
        .test-row.skipped .test-icon { color: #94a3b8; }
        .test-row.error .test-icon { color: #f59e0b; }
        .test-icon { width: 20px; flex-shrink: 0; }
        .test-name { flex: 1; margin: 0 10px; word-break: break-word; }
        .test-duration { color: #94a3b8; font-size: .78rem; white-space: nowrap; }
        .test-message {
            background: #fef2f2; color: #991b1b; font-size: .78rem; padding: 6px 10px;
            border-radius: 6px; margin: 4px 36px; font-family: monospace;
            white-space: pre-wrap; word-break: break-all; max-height: 200px; overflow-y: auto;
        }

        /* ── Tabs ── */
        .nav-tabs .nav-link { color: #64748b; border-radius: 8px 8px 0 0; }
        .nav-tabs .nav-link.active { color: var(--primary-color); font-weight: 600; border-bottom-color: #fff; }

        /* ── Meta info ── */
        .meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
        .meta-item { background: #f8fafc; border-radius: 8px; padding: 12px 16px; }
        .meta-item .meta-label { font-size: .75rem; color: #94a3b8; text-transform: uppercase; letter-spacing: .5px; }
        .meta-item .meta-value { font-size: .9rem; font-weight: 600; color: #1e293b; margin-top: 2px; word-break: break-all; }

        /* ── Comando de execução ── */
        .run-cmd {
            background: #1e293b; color: #86efac; font-family: monospace; font-size: .85rem;
            padding: 12px 16px; border-radius: 8px; position: relative;
        }
        .copy-btn { position: absolute; right: 10px; top: 8px; }

        /* ── Sem resultados ── */
        .empty-state { text-align: center; padding: 60px 20px; color: #94a3b8; }
        .empty-state i { font-size: 4rem; margin-bottom: 16px; }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <a href="index.php">
            <img src="../assets/img/Logo_sema.png" alt="SEMA" class="sidebar-logo">
        </a>
    </div>
    <div class="sidebar-menu">
        <div class="menu-header">Principal</div>
        <ul>
            <li><a href="index.php"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a></li>
            <li><a href="requerimentos.php"><i class="fas fa-clipboard-list"></i><span>Requerimentos</span></a></li>
            <li><a href="denuncias.php"><i class="fas fa-bullhorn"></i><span>Denúncias</span></a></li>
        </ul>
        <div class="menu-header">Homologação</div>
        <ul>
            <li>
                <a href="testes.php" class="active">
                    <i class="fas fa-vial"></i>
                    <span>Painel de Testes</span>
                    <?php if ($failedGeral > 0): ?>
                        <span class="badge bg-danger ms-auto"><?= $failedGeral ?></span>
                    <?php elseif ($totalGeral > 0): ?>
                        <span class="badge bg-success ms-auto"><?= $passedGeral ?></span>
                    <?php endif; ?>
                </a>
            </li>
        </ul>
        <div class="menu-header">Conta</div>
        <ul>
            <li><a href="perfil.php"><i class="fas fa-user-circle"></i><span>Meu Perfil</span></a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Sair</span></a></li>
        </ul>
    </div>
</div>

<!-- Conteúdo -->
<div class="content-wrapper">

    <!-- Topbar -->
    <div class="topbar mb-4">
        <div class="d-flex align-items-center gap-3">
            <h5 class="mb-0 fw-bold" style="color: var(--secondary-color);">
                <i class="fas fa-vial me-2" style="color: var(--accent-color);"></i>
                Painel de Testes
            </h5>
            <span class="badge-homolog">Homologação</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="text-muted small">
                <?= htmlspecialchars($_SESSION['admin_nome'] ?? '') ?>
            </span>
            <a href="logout.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>

    <?php if (!$temResultados): ?>
    <!-- Estado vazio -->
    <div class="card border-0 shadow-sm">
        <div class="card-body empty-state">
            <i class="fas fa-flask text-muted d-block"></i>
            <h4 class="mt-2 mb-2">Nenhum resultado encontrado</h4>
            <p class="text-muted mb-4">Execute os testes para gerar o relatório.</p>
            <div class="run-cmd text-start d-inline-block" style="min-width: 420px;">
                <span id="cmdText">./scripts/run-tests.sh</span>
                <button class="btn btn-sm btn-outline-light copy-btn" onclick="copiarComando()">
                    <i class="fas fa-copy"></i>
                </button>
            </div>
        </div>
    </div>

    <?php else: ?>

    <!-- ── Cards de resumo geral ── -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card <?= $statusGeral ?>">
                <div class="stat-icon text-<?= $statusGeral ?>">
                    <i class="fas <?= $iconGeral ?>"></i>
                </div>
                <div class="stat-value"><?= $pctGeral ?>%</div>
                <div class="stat-label">Taxa de sucesso</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card success">
                <div class="stat-icon text-success"><i class="fas fa-check-circle"></i></div>
                <div class="stat-value text-success"><?= $passedGeral ?></div>
                <div class="stat-label">Passaram</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card <?= $failedGeral > 0 ? 'danger' : 'info' ?>">
                <div class="stat-icon text-<?= $failedGeral > 0 ? 'danger' : 'secondary' ?>">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-value text-<?= $failedGeral > 0 ? 'danger' : 'secondary' ?>"><?= $failedGeral ?></div>
                <div class="stat-label">Falharam</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card info">
                <div class="stat-icon text-primary"><i class="fas fa-list-check"></i></div>
                <div class="stat-value"><?= $totalGeral ?></div>
                <div class="stat-label">Total de testes</div>
            </div>
        </div>
    </div>

    <!-- Barra de progresso geral -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between mb-2">
                <span class="fw-semibold">Progresso geral</span>
                <span class="text-muted small"><?= $passedGeral ?>/<?= $totalGeral ?> testes passando</span>
            </div>
            <div class="progress">
                <div class="progress-bar bg-success" style="width: <?= pct($passedGeral, $totalGeral) ?>%"></div>
                <?php if ($failedGeral > 0): ?>
                <div class="progress-bar bg-danger" style="width: <?= pct($failedGeral, $totalGeral) ?>%"></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── Meta-dados da última execução ── -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom d-flex align-items-center gap-2">
            <i class="fas fa-info-circle text-muted"></i>
            <strong>Última Execução</strong>
        </div>
        <div class="card-body">
            <div class="meta-grid">
                <div class="meta-item">
                    <div class="meta-label"><i class="fas fa-clock me-1"></i>Data/Hora</div>
                    <div class="meta-value"><?= htmlspecialchars(formatTimestamp($meta['timestamp'] ?? '')) ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label"><i class="fas fa-code-branch me-1"></i>Branch</div>
                    <div class="meta-value"><?= htmlspecialchars($meta['branch'] ?? '—') ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label"><i class="fas fa-code-commit me-1"></i>Commit</div>
                    <div class="meta-value"><?= htmlspecialchars($meta['commit'] ?? '—') ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label"><i class="fas fa-message me-1"></i>Mensagem</div>
                    <div class="meta-value"><?= htmlspecialchars($meta['commit_message'] ?? '—') ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label"><i class="fas fa-globe me-1"></i>URL testada</div>
                    <div class="meta-value"><?= htmlspecialchars($meta['base_url'] ?? '—') ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label"><i class="fas fa-stopwatch me-1"></i>Duração total</div>
                    <div class="meta-value">
                        <?= formatDuration(($unSummary['duration_ms'] ?? 0) + ($e2eSummary['duration_ms'] ?? 0)) ?>
                    </div>
                </div>
            </div>

            <!-- Comando para re-executar -->
            <div class="mt-3">
                <div class="meta-label mb-1"><i class="fas fa-terminal me-1"></i>Executar novamente</div>
                <div class="run-cmd position-relative">
                    <span id="cmdText">./scripts/run-tests.sh</span>
                    <button class="btn btn-sm btn-outline-light copy-btn" onclick="copiarComando()">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Tabs: Unitários | E2E ── -->
    <ul class="nav nav-tabs mb-0" id="testTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabUnit" type="button">
                <i class="fas fa-cube me-1"></i>
                Testes Unitários
                <span class="badge ms-1 <?= ($unSummary['failed'] ?? 0) > 0 ? 'bg-danger' : 'bg-success' ?>">
                    <?= ($unSummary['passed'] ?? 0) ?>/<?= ($unSummary['total'] ?? 0) ?>
                </span>
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabE2E" type="button">
                <i class="fas fa-browser me-1"></i>
                Testes E2E
                <span class="badge ms-1 <?= ($e2eSummary['failed'] ?? 0) > 0 ? 'bg-danger' : 'bg-success' ?>">
                    <?= ($e2eSummary['passed'] ?? 0) ?>/<?= ($e2eSummary['total'] ?? 0) ?>
                </span>
            </button>
        </li>
    </ul>

    <div class="tab-content">

        <!-- ─ Tab: PHPUnit ─ -->
        <div class="tab-pane fade show active" id="tabUnit">
            <div class="card border-0 shadow-sm" style="border-top-left-radius: 0; border-top-right-radius: 0;">
                <div class="card-body p-0">
                    <?php if (empty($phpunit['suites'])): ?>
                        <div class="empty-state">
                            <i class="fas fa-cube d-block"></i>
                            <p class="mt-2 mb-0 text-muted">Nenhum resultado de testes unitários disponível.</p>
                        </div>
                    <?php else: ?>
                        <!-- Resumo PHPUnit -->
                        <div class="p-3 border-bottom d-flex gap-4 flex-wrap" style="background: #f8fafc; border-radius: 0 0 0 0;">
                            <div><span class="text-success fw-bold"><?= $unSummary['passed'] ?? 0 ?></span> <span class="text-muted small">passaram</span></div>
                            <div><span class="text-danger fw-bold"><?= $unSummary['failed'] ?? 0 ?></span> <span class="text-muted small">falharam</span></div>
                            <div><span class="text-secondary fw-bold"><?= $unSummary['skipped'] ?? 0 ?></span> <span class="text-muted small">ignorados</span></div>
                            <div class="ms-auto"><i class="fas fa-stopwatch text-muted me-1"></i><span class="text-muted small"><?= formatDuration($unSummary['duration_ms'] ?? 0) ?></span></div>
                        </div>

                        <!-- Barra de progresso PHPUnit -->
                        <div class="px-3 pt-3 pb-2">
                            <div class="progress">
                                <?php $pctU = pct($unSummary['passed'] ?? 0, $unSummary['total'] ?? 1); ?>
                                <div class="progress-bar bg-success" style="width: <?= $pctU ?>%" title="<?= $pctU ?>% passaram"></div>
                                <?php if (($unSummary['failed'] ?? 0) > 0): ?>
                                <div class="progress-bar bg-danger" style="width: <?= pct($unSummary['failed'], $unSummary['total']) ?>%"></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Suítes PHPUnit -->
                        <div class="p-3" id="phpunitSuites">
                            <?php foreach ($phpunit['suites'] as $si => $suite): ?>
                            <?php
                                $sHasFail = ($suite['failed'] ?? 0) > 0;
                                $sIcon    = $sHasFail ? 'fa-times-circle text-danger' : 'fa-check-circle text-success';
                            ?>
                            <div class="mb-2">
                                <div class="test-suite-header" onclick="toggleSuite('suite<?= $si ?>')">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="fas <?= $sIcon ?>"></i>
                                        <span class="fw-semibold"><?= htmlspecialchars($suite['name'] ?? '') ?></span>
                                        <span class="badge <?= $sHasFail ? 'bg-danger' : 'bg-success' ?> bg-opacity-10 text-<?= $sHasFail ? 'danger' : 'success' ?> border border-<?= $sHasFail ? 'danger' : 'success' ?>">
                                            <?= $suite['passed'] ?? 0 ?>/<?= $suite['total'] ?? 0 ?>
                                        </span>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="text-muted small"><?= formatDuration($suite['duration_ms'] ?? 0) ?></span>
                                        <i class="fas fa-chevron-down text-muted toggle-icon" id="icon-suite<?= $si ?>" style="font-size:.75rem; transition: transform .2s;"></i>
                                    </div>
                                </div>
                                <div id="suite<?= $si ?>" class="<?= $sHasFail ? '' : 'd-none' ?>" style="border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 8px 8px; background: #fff;">
                                    <?php foreach ($suite['tests'] ?? [] as $test): ?>
                                    <?php $status = $test['status'] ?? 'unknown'; ?>
                                    <div class="test-row <?= $status ?>">
                                        <span class="test-icon">
                                            <?php if ($status === 'passed'): ?>
                                                <i class="fas fa-check text-success"></i>
                                            <?php elseif ($status === 'skipped'): ?>
                                                <i class="fas fa-minus text-secondary"></i>
                                            <?php else: ?>
                                                <i class="fas fa-times text-danger"></i>
                                            <?php endif; ?>
                                        </span>
                                        <span class="test-name"><?= htmlspecialchars($test['name'] ?? '') ?></span>
                                        <span class="test-duration"><?= formatDuration($test['duration_ms'] ?? 0) ?></span>
                                    </div>
                                    <?php if (!empty($test['message']) && $status !== 'passed'): ?>
                                    <div class="test-message"><?= htmlspecialchars($test['message']) ?></div>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ─ Tab: Playwright ─ -->
        <div class="tab-pane fade" id="tabE2E">
            <div class="card border-0 shadow-sm" style="border-top-left-radius: 0;">
                <div class="card-body p-0">
                    <?php if (empty($playwright['suites'])): ?>
                        <div class="empty-state">
                            <i class="fas fa-browser d-block"></i>
                            <p class="mt-2 mb-0 text-muted">Nenhum resultado E2E disponível.</p>
                            <p class="text-muted small mt-1">Execute: <code>./scripts/run-tests.sh --e2e-only</code></p>
                        </div>
                    <?php else: ?>
                        <!-- Resumo E2E -->
                        <div class="p-3 border-bottom d-flex gap-4 flex-wrap" style="background: #f8fafc;">
                            <div><span class="text-success fw-bold"><?= $e2eSummary['passed'] ?? 0 ?></span> <span class="text-muted small">passaram</span></div>
                            <div><span class="text-danger fw-bold"><?= $e2eSummary['failed'] ?? 0 ?></span> <span class="text-muted small">falharam</span></div>
                            <div><span class="text-secondary fw-bold"><?= $e2eSummary['skipped'] ?? 0 ?></span> <span class="text-muted small">ignorados</span></div>
                            <div class="ms-auto"><i class="fas fa-stopwatch text-muted me-1"></i><span class="text-muted small"><?= formatDuration($e2eSummary['duration_ms'] ?? 0) ?></span></div>
                        </div>

                        <!-- Barra de progresso E2E -->
                        <div class="px-3 pt-3 pb-2">
                            <div class="progress">
                                <?php $pctE = pct($e2eSummary['passed'] ?? 0, $e2eSummary['total'] ?? 1); ?>
                                <div class="progress-bar bg-success" style="width: <?= $pctE ?>%"></div>
                                <?php if (($e2eSummary['failed'] ?? 0) > 0): ?>
                                <div class="progress-bar bg-danger" style="width: <?= pct($e2eSummary['failed'], $e2eSummary['total']) ?>%"></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Suítes E2E -->
                        <div class="p-3">
                            <?php foreach ($playwright['suites'] as $ei => $suite): ?>
                            <?php
                                $eHasFail = ($suite['failed'] ?? 0) > 0;
                                $eIcon    = $eHasFail ? 'fa-times-circle text-danger' : 'fa-check-circle text-success';
                            ?>
                            <div class="mb-2">
                                <div class="test-suite-header" onclick="toggleSuite('e2e<?= $ei ?>')">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="fas <?= $eIcon ?>"></i>
                                        <span class="fw-semibold"><?= htmlspecialchars($suite['name'] ?? '') ?></span>
                                        <?php if (!empty($suite['file'])): ?>
                                        <code class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars($suite['file']) ?></code>
                                        <?php endif; ?>
                                        <span class="badge <?= $eHasFail ? 'bg-danger' : 'bg-success' ?> bg-opacity-10 text-<?= $eHasFail ? 'danger' : 'success' ?> border border-<?= $eHasFail ? 'danger' : 'success' ?>">
                                            <?= $suite['passed'] ?? 0 ?>/<?= $suite['total'] ?? 0 ?>
                                        </span>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="text-muted small"><?= formatDuration($suite['duration_ms'] ?? 0) ?></span>
                                        <i class="fas fa-chevron-down text-muted" id="icon-e2e<?= $ei ?>" style="font-size:.75rem; transition: transform .2s;"></i>
                                    </div>
                                </div>
                                <div id="e2e<?= $ei ?>" class="<?= $eHasFail ? '' : 'd-none' ?>" style="border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 8px 8px; background: #fff;">
                                    <?php foreach ($suite['tests'] ?? [] as $test): ?>
                                    <?php $status = $test['status'] ?? 'unknown'; ?>
                                    <div class="test-row <?= $status ?>">
                                        <span class="test-icon">
                                            <?php if ($status === 'passed'): ?>
                                                <i class="fas fa-check text-success"></i>
                                            <?php elseif ($status === 'skipped'): ?>
                                                <i class="fas fa-minus text-secondary"></i>
                                            <?php else: ?>
                                                <i class="fas fa-times text-danger"></i>
                                            <?php endif; ?>
                                        </span>
                                        <span class="test-name">
                                            <?= htmlspecialchars($test['name'] ?? '') ?>
                                            <?php if (($test['retry'] ?? 0) > 0): ?>
                                            <span class="badge bg-warning text-dark ms-1" style="font-size:.65rem;">retry <?= $test['retry'] ?></span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="test-duration"><?= formatDuration($test['duration_ms'] ?? 0) ?></span>
                                    </div>
                                    <?php if (!empty($test['message']) && $status !== 'passed'): ?>
                                    <div class="test-message"><?= htmlspecialchars($test['message']) ?></div>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div><!-- /tab-content -->
    <?php endif; ?>

    <p class="text-center text-muted small mt-4">
        SEMA Pau dos Ferros/RN &mdash; Ambiente de Homologação &mdash;
        Atualizado em <?= formatTimestamp($meta['timestamp'] ?? '') ?>
    </p>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleSuite(id) {
    const el   = document.getElementById(id);
    const icon = document.getElementById('icon-' + id);
    if (!el) return;
    const hidden = el.classList.contains('d-none');
    el.classList.toggle('d-none', !hidden);
    if (icon) icon.style.transform = hidden ? 'rotate(180deg)' : '';
}

function copiarComando() {
    const text = document.getElementById('cmdText').textContent.trim();
    navigator.clipboard.writeText(text).then(() => {
        const btn = document.querySelector('.copy-btn');
        if (btn) { btn.innerHTML = '<i class="fas fa-check"></i>'; setTimeout(() => btn.innerHTML = '<i class="fas fa-copy"></i>', 1500); }
    });
}

// Expande suítes com falhas automaticamente (já estão abertas pelo PHP acima)
// Fecha com duplo clique nas que passaram
document.querySelectorAll('.test-suite-header').forEach(h => {
    h.addEventListener('dblclick', () => {
        const id = h.nextElementSibling?.id;
        if (id) toggleSuite(id);
    });
});
</script>
</body>
</html>
