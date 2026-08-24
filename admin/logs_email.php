<?php
require_once '../includes/config.php';
require_once 'conexao.php';
require_once '../includes/email_service.php';

verificaLogin();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$filtroStatus = $_GET['status'] ?? '';
$filtroEmail = trim($_GET['email'] ?? '');
$filtroDataInicio = $_GET['data_inicio'] ?? '';
$filtroDataFim = $_GET['data_fim'] ?? '';

$whereConditions = [];
$params = [];

if ($filtroStatus !== '') {
    $whereConditions[] = "el.status = ?";
    $params[] = $filtroStatus;
}

if ($filtroEmail !== '') {
    $whereConditions[] = "el.email_destino LIKE ?";
    $params[] = '%' . $filtroEmail . '%';
}

if ($filtroDataInicio !== '') {
    $whereConditions[] = "DATE(el.data_envio) >= ?";
    $params[] = $filtroDataInicio;
}

if ($filtroDataFim !== '') {
    $whereConditions[] = "DATE(el.data_envio) <= ?";
    $params[] = $filtroDataFim;
}

$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$sql = "
    SELECT
        el.*,
        r.protocolo,
        req.nome AS requerente_nome
    FROM email_logs el
    LEFT JOIN requerimentos r ON el.requerimento_id = r.id
    LEFT JOIN requerentes req ON r.requerente_id = req.id
    {$whereClause}
    ORDER BY el.data_envio DESC
    LIMIT {$perPage} OFFSET {$offset}
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$countSql = "
    SELECT COUNT(*) AS total
    FROM email_logs el
    LEFT JOIN requerimentos r ON el.requerimento_id = r.id
    LEFT JOIN requerentes req ON r.requerente_id = req.id
    {$whereClause}
";

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalLogs = (int) ($countStmt->fetch()['total'] ?? 0);
$totalPages = max(1, (int) ceil($totalLogs / $perPage));

$statsSql = "
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'SUCESSO' THEN 1 ELSE 0 END) AS sucessos,
        SUM(CASE WHEN status = 'ERRO' THEN 1 ELSE 0 END) AS erros,
        SUM(CASE WHEN status = 'SUCESSO' AND COALESCE(eh_teste, 0) = 0 THEN 1 ELSE 0 END) AS envios_reais,
        SUM(CASE WHEN COALESCE(eh_teste, 0) = 1 THEN 1 ELSE 0 END) AS testes,
        SUM(CASE WHEN DATE(data_envio) = CURDATE() THEN 1 ELSE 0 END) AS hoje
    FROM email_logs
";
$statsStmt = $pdo->query($statsSql);
$stats = $statsStmt->fetch();

function buildLogsUrl(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            unset($params[$key]);
        }
    }
    return 'logs_email.php' . ($params ? '?' . http_build_query($params) : '');
}

include 'header.php';
include __DIR__ . '/includes/acervo_tabs.php';
?>
<style>
    .logs-shell { max-width: 1320px; margin: 0 auto; display: flex; flex-direction: column; gap: 20px; }
    .logs-metric-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
    .logs-metric-card, .logs-block, .logs-proof { background: #fff; border: 1px solid var(--line); border-radius: 20px; box-shadow: var(--card-shadow); }
    .logs-metric-card { padding: 20px; position: relative; overflow: hidden; }
    .logs-metric-card::after { content: ''; position: absolute; width: 90px; height: 90px; right: -34px; top: -34px; border-radius: 50%; background: var(--primary-soft); opacity: .7; }
    .logs-metric-icon { width: 38px; height: 38px; display: grid; place-items: center; border-radius: 12px; margin-bottom: 16px; color: var(--primary-strong); background: var(--primary-soft); }
    .logs-metric-card.warning .logs-metric-icon { color: #9a6800; background: var(--warning-soft); }
    .logs-metric-card.danger .logs-metric-icon { color: var(--danger); background: var(--danger-soft); }
    .logs-metric-card.neutral .logs-metric-icon { color: var(--info); background: var(--info-soft); }
    .logs-metric-label { display: block; margin-bottom: 6px; font-size: .76rem; color: var(--muted); font-weight: 700; text-transform: uppercase; letter-spacing: .08em; }
    .logs-metric-value { display: block; margin-bottom: 6px; font-size: 1.9rem; font-weight: 800; color: var(--ink); line-height: 1; }
    .logs-metric-note { display: flex; align-items: center; gap: 8px; color: var(--muted); font-size: .82rem; }
    .logs-trust { display: grid; grid-template-columns: 1.2fr 1fr; gap: 14px; }
    .logs-proof { padding: 18px 20px; display: flex; gap: 14px; align-items: flex-start; }
    .logs-proof-icon { flex: 0 0 38px; height: 38px; display: grid; place-items: center; border-radius: 12px; background: var(--success-soft); color: var(--success); }
    .logs-proof.pending .logs-proof-icon { background: var(--warning-soft); color: #9a6800; }
    .logs-proof h3 { margin: 0 0 4px; font-size: .98rem; color: var(--ink); }
    .logs-proof p { margin: 0; color: var(--muted); font-size: .83rem; line-height: 1.5; }
    .logs-proof strong { color: var(--ink-2); }
    .logs-filter-bar { padding: 18px; border-bottom: 1px solid var(--line); }
    .logs-filter-form { display: flex; align-items: end; gap: 10px; flex-wrap: wrap; }
    .logs-field { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
    .logs-field label { color: var(--muted); font-size: .78rem; font-weight: 700; }
    .logs-field input, .logs-field select {
        min-height: 42px; padding: 0 14px; border: 1px solid var(--line); border-radius: 14px; background: #fff; color: var(--ink); font-size: .9rem;
    }
    .logs-field.wide { flex: 1 1 260px; }
    .logs-field.compact { width: 170px; }
    .logs-actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .logs-table-wrap { overflow: auto; }
    .logs-table { width: 100%; border-collapse: separate; border-spacing: 0; }
    .logs-table th { padding: 14px 18px; background: var(--surface-soft); color: var(--muted); font-size: .77rem; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; border-bottom: 1px solid var(--line); white-space: nowrap; }
    .logs-table td { padding: 16px 18px; border-bottom: 1px solid #edf2ee; vertical-align: middle; }
    .logs-table tr:hover td { background: #fafcfb; }
    .logs-stack { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
    .logs-main { font-size: .9rem; font-weight: 700; color: var(--ink); }
    .logs-sub { font-size: .78rem; color: var(--muted); }
    .logs-sub a { color: var(--primary); }
    .logs-evidence { display: inline-flex; align-items: center; gap: 6px; padding: 5px 9px; border-radius: 999px; color: var(--success); background: var(--success-soft); font-size: .72rem; font-weight: 800; white-space: nowrap; }
    .logs-evidence-error { color: var(--danger); background: var(--danger-soft); }
    .logs-receipt-note { display: block; margin-top: 6px; white-space: nowrap; }
    .eyebrow { display: inline-flex; align-items: center; gap: 7px; margin-bottom: 8px; color: var(--primary); font-size: .75rem; font-weight: 800; text-transform: uppercase; letter-spacing: .09em; }
    .logs-protocol { display: inline-flex; align-items: center; gap: 6px; min-height: 26px; padding: 0 10px; border-radius: 999px; background: var(--primary-soft); color: var(--primary-strong); font-size: .72rem; font-weight: 800; }
    .logs-subject { max-width: 360px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .logs-empty { padding: 48px 24px; text-align: center; color: var(--muted); }
    .logs-empty i { display: block; margin-bottom: 12px; font-size: 2.5rem; color: #c4d0c8; }
    .logs-pagination { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 18px; }
    .logs-pagination-copy { color: var(--muted); font-size: .82rem; }
    .logs-pagination-links { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .logs-page-link { min-width: 38px; height: 38px; padding: 0 12px; border: 1px solid var(--line); border-radius: 12px; background: #fff; color: var(--ink); display: inline-flex; align-items: center; justify-content: center; font-size: .82rem; font-weight: 700; }
    .logs-page-link.active { background: var(--primary); border-color: var(--primary); color: #fff; }
    @media (max-width: 1100px) { .logs-metric-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .logs-trust { grid-template-columns: 1fr; } }
    @media (max-width: 767px) {
        .logs-metric-grid { grid-template-columns: 1fr; }
        .logs-trust { grid-template-columns: 1fr; }
        .logs-filter-form, .logs-pagination { flex-direction: column; align-items: stretch; }
        .logs-field.compact, .logs-field.wide { width: 100%; flex: 1 1 100%; }
        .logs-table { min-width: 1050px; }
    }
</style>

<div class="admin-page-shell logs-shell">
    <section class="page-hero page-hero-compact">
        <div class="page-hero-copy">
            <div class="eyebrow"><i class="fas fa-shield-halved"></i> Auditoria de comunicações</div>
            <h1 class="page-title">Histórico de e-mails</h1>
            <p class="page-subtitle">Acompanhe cada disparo, consulte as evidências técnicas e identifique rapidamente qualquer falha.</p>
        </div>
        <div class="page-toolbar">
            <button class="toolbar-button" type="button" onclick="window.location.reload()">
                <i class="fas fa-rotate-right"></i> Atualizar
            </button>
        </div>
    </section>

    <section class="logs-metric-grid">
        <article class="logs-metric-card">
            <div class="logs-metric-icon"><i class="fas fa-envelope"></i></div>
            <span class="logs-metric-label">Total</span>
            <strong class="logs-metric-value"><?= number_format((int) ($stats['total'] ?? 0)) ?></strong>
            <span class="logs-metric-note"><i class="fas fa-layer-group"></i> registros armazenados</span>
        </article>
        <article class="logs-metric-card">
            <div class="logs-metric-icon"><i class="fas fa-circle-check"></i></div>
            <span class="logs-metric-label">Sucesso</span>
            <strong class="logs-metric-value"><?= number_format((int) ($stats['sucessos'] ?? 0)) ?></strong>
            <span class="logs-metric-note"><i class="fas fa-server"></i> aceitos pelo provedor</span>
        </article>
        <article class="logs-metric-card danger">
            <div class="logs-metric-icon"><i class="fas fa-triangle-exclamation"></i></div>
            <span class="logs-metric-label">Falhas</span>
            <strong class="logs-metric-value"><?= number_format((int) ($stats['erros'] ?? 0)) ?></strong>
            <span class="logs-metric-note"><i class="fas fa-arrow-rotate-right"></i> exigem acompanhamento</span>
        </article>
        <article class="logs-metric-card neutral">
            <div class="logs-metric-icon"><i class="fas fa-calendar-day"></i></div>
            <span class="logs-metric-label">Hoje</span>
            <strong class="logs-metric-value"><?= number_format((int) ($stats['hoje'] ?? 0)) ?></strong>
            <span class="logs-metric-note"><i class="fas fa-clock"></i> disparos registrados hoje</span>
        </article>
    </section>

    <section class="logs-trust" aria-label="Comprovação dos e-mails">
        <article class="logs-proof">
            <div class="logs-proof-icon"><i class="fas fa-fingerprint"></i></div>
            <div><h3>Envio comprovado</h3><p>Um registro como <strong>Sucesso</strong> confirma que a Hostinger Mail API aceitou a mensagem em <strong><?= number_format((int) ($stats['envios_reais'] ?? 0)) ?> envio(s) real(is)</strong>. O histórico preserva data, destinatário, assunto, operador e retorno do provedor.</p></div>
        </article>
        <article class="logs-proof pending">
            <div class="logs-proof-icon"><i class="fas fa-inbox"></i></div>
            <div><h3>Recebimento na caixa de entrada</h3><p><strong>Ainda não confirmado.</strong> A API atual não fornece evento de entrega ou leitura. Portanto, não exibimos “entregue” como se fosse comprovado; um sucesso pode ter sido filtrado como spam ou rejeitado depois pelo servidor destinatário.</p></div>
        </article>
    </section>

    <section class="logs-block">
        <div class="logs-filter-bar">
            <form method="GET" class="logs-filter-form">
                <div class="logs-field compact">
                    <label for="status">Status</label>
                    <select name="status" id="status">
                        <option value="">Todos</option>
                        <option value="SUCESSO" <?= $filtroStatus === 'SUCESSO' ? 'selected' : '' ?>>Sucesso</option>
                        <option value="ERRO" <?= $filtroStatus === 'ERRO' ? 'selected' : '' ?>>Erro</option>
                    </select>
                </div>
                <div class="logs-field wide">
                    <label for="email">Destinatário</label>
                    <input type="text" name="email" id="email" value="<?= htmlspecialchars($filtroEmail) ?>" placeholder="Buscar por e-mail">
                </div>
                <div class="logs-field compact">
                    <label for="data_inicio">De</label>
                    <input type="date" name="data_inicio" id="data_inicio" value="<?= htmlspecialchars($filtroDataInicio) ?>">
                </div>
                <div class="logs-field compact">
                    <label for="data_fim">Até</label>
                    <input type="date" name="data_fim" id="data_fim" value="<?= htmlspecialchars($filtroDataFim) ?>">
                </div>
                <div class="logs-actions">
                    <button type="submit" class="toolbar-button toolbar-button-primary"><i class="fas fa-filter"></i> Filtrar</button>
                    <a href="logs_email.php" class="toolbar-button">Limpar</a>
                </div>
            </form>
        </div>

        <?php if ($logs): ?>
            <div class="logs-table-wrap">
                <table class="logs-table">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Envio</th>
                            <th>Destinatário</th>
                            <th>Assunto</th>
                            <th>Origem</th>
                            <th>Evidência</th>
                            <th class="text-end">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td>
                                    <span class="badge badge-status <?= $log['status'] === 'SUCESSO' ? 'status-aprovado' : 'status-reprovado' ?>">
                                        <?= $log['status'] === 'SUCESSO' ? 'Sucesso' : 'Erro' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="logs-stack">
                                        <span class="logs-main"><?= date('d/m/Y', strtotime($log['data_envio'])) ?></span>
                                        <span class="logs-sub"><?= date('H:i:s', strtotime($log['data_envio'])) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="logs-stack">
                                        <span class="logs-main"><?= htmlspecialchars($log['email_destino']) ?></span>
                                        <?php if (!empty($log['requerente_nome'])): ?>
                                            <span class="logs-sub"><?= htmlspecialchars($log['requerente_nome']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="logs-stack">
                                        <?php if (!empty($log['protocolo'])): ?>
                                            <span>
                                                <a href="visualizar_requerimento.php?id=<?= (int) $log['requerimento_id'] ?>" class="logs-protocol">
                                                    <i class="fas fa-barcode"></i><?= htmlspecialchars($log['protocolo']) ?>
                                                </a>
                                            </span>
                                        <?php endif; ?>
                                        <span class="logs-main logs-subject" title="<?= htmlspecialchars($log['assunto']) ?>"><?= htmlspecialchars($log['assunto']) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="logs-stack">
                                        <span class="logs-main"><?= htmlspecialchars($log['usuario_envio'] ?? 'Sistema') ?></span>
                                        <?php if (!empty($log['erro']) && $log['status'] !== 'SUCESSO'): ?>
                                            <span class="logs-sub" title="<?= htmlspecialchars($log['erro']) ?>">falha registrada</span>
                                        <?php else: ?>
                                            <span class="logs-sub">origem do disparo</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($log['status'] === 'SUCESSO'): ?>
                                        <span class="logs-evidence"><i class="fas fa-check"></i> API aceitou</span>
                                    <?php else: ?>
                                        <span class="logs-evidence logs-evidence-error"><i class="fas fa-xmark"></i> falha registrada</span>
                                    <?php endif; ?>
                                    <span class="logs-sub logs-receipt-note"><i class="fas fa-inbox"></i> recebimento não confirmado</span>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-2">
                                        <?php if ($log['status'] === 'ERRO' && emailRegistradoPodeSerReenviado((string) ($log['mensagem'] ?? ''))): ?>
                                            <button class="toolbar-button" type="button" onclick="resendFailedEmail(<?= (int) $log['id'] ?>, this)">
                                                <i class="fas fa-rotate-right"></i> Reenviar
                                            </button>
                                        <?php elseif ($log['status'] === 'ERRO' && !empty($log['requerimento_id'])): ?>
                                            <a class="toolbar-button" href="visualizar_requerimento.php?id=<?= (int) $log['requerimento_id'] ?>" title="Repita a ação no processo para gerar um link válido">
                                                <i class="fas fa-arrow-up-right-from-square"></i> Abrir processo
                                            </a>
                                        <?php endif; ?>
                                        <button class="toolbar-button" type="button" onclick="showLogDetails(<?= (int) $log['id'] ?>)">
                                            <i class="fas fa-circle-info"></i> Ver detalhes
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="logs-empty">
                <i class="fas fa-inbox"></i>
                <p class="mb-0">Nenhum log encontrado para os filtros atuais.</p>
            </div>
        <?php endif; ?>

        <?php if ($totalPages > 1): ?>
            <div class="logs-pagination">
                <div class="logs-pagination-copy">
                    Página <?= $page ?> de <?= $totalPages ?> · <?= $totalLogs ?> registro(s)
                </div>
                <div class="logs-pagination-links">
                    <?php if ($page > 1): ?>
                        <a href="<?= htmlspecialchars(buildLogsUrl(['page' => 1])) ?>" class="logs-page-link">«</a>
                        <a href="<?= htmlspecialchars(buildLogsUrl(['page' => $page - 1])) ?>" class="logs-page-link">‹</a>
                    <?php endif; ?>
                    <?php
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                        <a href="<?= htmlspecialchars(buildLogsUrl(['page' => $i])) ?>" class="logs-page-link <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="<?= htmlspecialchars(buildLogsUrl(['page' => $page + 1])) ?>" class="logs-page-link">›</a>
                        <a href="<?= htmlspecialchars(buildLogsUrl(['page' => $totalPages])) ?>" class="logs-page-link">»</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>
</div>

<style>
    #logDetailsContent, #logDetailsContent * { max-width: none !important; }
</style>
<div class="modal fade" id="logDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light border-bottom-0">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-info-circle me-2 text-primary"></i> Detalhes do e-mail
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="logDetailsContent"></div>
            <div class="modal-footer border-top-0 bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script>
    let logDetailsModal = null;

    document.addEventListener('DOMContentLoaded', function() {
        const modalEl = document.getElementById('logDetailsModal');
        if (modalEl) {
            document.body.appendChild(modalEl);
            logDetailsModal = new bootstrap.Modal(modalEl);
        }
    });

    function showLogDetails(logId) {
        const content = document.getElementById('logDetailsContent');

        content.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary mb-3" role="status"></div>
                <p class="text-muted mb-0">Carregando detalhes...</p>
            </div>
        `;

        logDetailsModal.show();

        fetch(\`ajax_log_details.php?id=\${logId}\`)
            .then(response => {
                if (!response.ok) throw new Error('Erro na requisição');
                return response.text();
            })
            .then(data => {
                content.innerHTML = data;
            })
            .catch(() => {
                content.innerHTML = `
                    <div class="alert alert-danger d-flex align-items-center">
                        <i class="fas fa-exclamation-circle fa-2x me-3"></i>
                        <div>
                            <strong>Erro ao carregar!</strong><br>
                            Não foi possível obter os detalhes deste log.
                        </div>
                    </div>
                `;
            });
    }

    function resendFailedEmail(logId, button) {
        if (!confirm('Reenviar exatamente esta mensagem para o destinatário registrado?')) return;
        const original = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando';

        const body = new FormData();
        body.append('log_id', String(logId));
        body.append('csrf_token', <?= json_encode($_SESSION['csrf_token'], JSON_UNESCAPED_UNICODE) ?>);

        fetch('reenviar_email_log.php', { method: 'POST', body })
            .then(async response => {
                const data = await response.json().catch(() => ({}));
                if (!response.ok || !data.ok) throw new Error(data.erro || 'Não foi possível reenviar.');
                button.innerHTML = '<i class="fas fa-check"></i> Reenviado';
                button.classList.add('is-success');
                if (typeof showToast === 'function') showToast(data.mensagem, 'success');
            })
            .catch(error => {
                button.disabled = false;
                button.innerHTML = original;
                if (typeof showToast === 'function') showToast(error.message, 'danger');
                else alert(error.message);
            });
    }
</script>

<?php include 'footer.php'; ?>
