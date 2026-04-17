<?php
require_once 'conexao.php';

$host = $_SERVER['HTTP_HOST'] ?? '';
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
if (!MODO_HOMOLOG && preg_match('/^(www\.)?sema\.protocolosead\.com$/i', $host)) {
    $redirect_url = 'http://sema.paudosferros.rn.gov.br' . $requestUri;
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: $redirect_url");
    exit();
}
verificaLogin();

if ((isset($_SESSION['admin_nivel']) && $_SESSION['admin_nivel'] === 'secretario') || (isset($_SESSION['admin_email']) && $_SESSION['admin_email'] === 'secretario@sema.rn.gov.br')) {
    header("Location: secretario_dashboard.php");
    exit;
}

if (isset($_SESSION['admin_nivel']) && $_SESSION['admin_nivel'] === 'fiscal') {
    header("Location: fiscal_dashboard.php");
    exit;
}

$totalRequerimentos = (int) $pdo->query("SELECT COUNT(*) FROM requerimentos")->fetchColumn();
$emAnalise = (int) $pdo->query("SELECT COUNT(*) FROM requerimentos WHERE status = 'Em análise'")->fetchColumn();
$naoVisualizados = (int) $pdo->query("SELECT COUNT(*) FROM requerimentos WHERE visualizado = 0")->fetchColumn();

$stmt = $pdo->query("
    SELECT r.id, r.protocolo, r.tipo_alvara, r.status, r.data_envio, req.nome AS requerente
    FROM requerimentos r
    JOIN requerentes req ON r.requerente_id = req.id
    ORDER BY r.data_envio DESC
    LIMIT 10
");
$ultimosRequerimentos = $stmt->fetchAll();

$stmt = $pdo->query("
    SELECT ha.acao, ha.data_acao, a.nome AS admin_nome, r.protocolo
    FROM historico_acoes ha
    LEFT JOIN administradores a ON ha.admin_id = a.id
    LEFT JOIN requerimentos r ON ha.requerimento_id = r.id
    ORDER BY ha.data_acao DESC
    LIMIT 8
");
$historicoAcoes = $stmt->fetchAll();

$stmt = $pdo->query("
    SELECT el.email_destino, el.assunto, el.status, el.data_envio, r.protocolo
    FROM email_logs el
    LEFT JOIN requerimentos r ON el.requerimento_id = r.id
    WHERE el.eh_teste = 0 OR el.eh_teste IS NULL
    ORDER BY el.data_envio DESC
    LIMIT 8
");
$ultimosEmails = $stmt->fetchAll();

include 'header.php';

$saudacao = 'Bom dia';
$horaAtual = (int) date('H');
if ($horaAtual >= 12 && $horaAtual < 18) {
    $saudacao = 'Boa tarde';
} elseif ($horaAtual >= 18) {
    $saudacao = 'Boa noite';
}

$statusMeta = [
    'Em análise' => ['class' => 'status-em-analise', 'label' => 'Em análise'],
    'Aprovado' => ['class' => 'status-aprovado', 'label' => 'Aprovado'],
    'Finalizado' => ['class' => 'status-finalizado', 'label' => 'Finalizado'],
    'Reprovado' => ['class' => 'status-reprovado', 'label' => 'Reprovado'],
    'Pendente' => ['class' => 'status-pendente', 'label' => 'Pendente'],
    'Cancelado' => ['class' => 'status-cancelado', 'label' => 'Cancelado'],
    'Indeferido' => ['class' => 'status-indeferido', 'label' => 'Indeferido'],
    'Apto a gerar alvará' => ['class' => 'status-apto-a-gerar-alvara', 'label' => 'Apto a gerar alvará'],
    'Alvará Emitido' => ['class' => 'status-alvara-emitido', 'label' => 'Alvará emitido'],
    'Aguardando Fiscalização' => ['class' => 'status-aguardando-fiscalizacao', 'label' => 'Aguardando fiscalização'],
];

$resumoOperacional = [];
if ($emAnalise > 0) {
    $resumoOperacional[] = $emAnalise . ' em análise';
}
if ($naoVisualizados > 0) {
    $resumoOperacional[] = $naoVisualizados . ' não visualizados';
}
if (($totalAguardandoFiscal ?? 0) > 0) {
    $resumoOperacional[] = $totalAguardandoFiscal . ' aguardando fiscalização';
}
if (!$resumoOperacional) {
    $resumoOperacional[] = 'sem filas críticas no momento';
}
?>

<style>
    .dashboard-shell {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .dashboard-hero {
        display: grid;
        grid-template-columns: minmax(0, 1.35fr) minmax(280px, .9fr);
        gap: 18px;
    }

    .hero-card,
    .hero-side-card,
    .metric-card,
    .queue-card,
    .activity-card,
    .mail-card {
        border: 1px solid rgba(219, 231, 243, .96);
        border-radius: var(--radius-lg);
        background: rgba(255,255,255,.95);
        box-shadow: var(--card-shadow);
    }

    .hero-card {
        padding: 28px;
        background:
            radial-gradient(circle at top right, rgba(14, 165, 233, .15), transparent 28%),
            linear-gradient(145deg, #ffffff 0%, #f8fbff 100%);
    }

    .hero-label {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 7px 12px;
        border-radius: 999px;
        background: var(--primary-soft);
        color: var(--primary-strong);
        font-size: .78rem;
        font-weight: 600;
        margin-bottom: 14px;
    }

    .hero-title {
        font-size: 1.9rem;
        font-weight: 700;
        line-height: 1.15;
        margin-bottom: 10px;
        color: var(--text);
    }

    .hero-copy {
        font-size: .96rem;
        line-height: 1.7;
        color: var(--muted);
        max-width: 720px;
        margin-bottom: 22px;
    }

    .hero-copy strong {
        color: var(--text);
    }

    .hero-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
    }

    .hero-actions .btn {
        border-radius: 14px;
        padding: 10px 16px;
        font-weight: 600;
    }

    .hero-side-card {
        padding: 22px;
        display: flex;
        flex-direction: column;
        gap: 16px;
        justify-content: space-between;
    }

    .hero-side-header h3 {
        font-size: 1rem;
        font-weight: 700;
        margin-bottom: 6px;
        color: var(--text);
    }

    .hero-side-header p {
        font-size: .84rem;
        line-height: 1.6;
        color: var(--muted);
        margin: 0;
    }

    .operational-checklist {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .operational-check {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 12px 14px;
        border-radius: var(--radius-sm);
        background: var(--surface-soft);
        border: 1px solid rgba(219, 231, 243, .85);
    }

    .operational-check i {
        color: var(--primary);
        margin-top: 3px;
    }

    .operational-check strong {
        display: block;
        font-size: .85rem;
        margin-bottom: 2px;
        color: var(--text);
    }

    .operational-check span {
        font-size: .78rem;
        color: var(--muted);
        line-height: 1.5;
    }

    .metric-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 16px;
    }

    .metric-card {
        padding: 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
    }

    .metric-copy small {
        display: block;
        font-size: .76rem;
        font-weight: 600;
        color: var(--muted);
        text-transform: uppercase;
        letter-spacing: .08em;
        margin-bottom: 8px;
    }

    .metric-copy strong {
        display: block;
        font-size: 1.95rem;
        font-weight: 700;
        line-height: 1;
        color: var(--text);
        margin-bottom: 6px;
    }

    .metric-copy span {
        display: block;
        font-size: .82rem;
        color: var(--muted);
        line-height: 1.45;
    }

    .metric-icon {
        width: 52px;
        height: 52px;
        border-radius: 16px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 1.05rem;
    }

    .metric-icon.info { background: #e0f2fe; color: #0369a1; }
    .metric-icon.warning { background: #fff7ed; color: #c2410c; }
    .metric-icon.danger { background: #fef2f2; color: #b91c1c; }
    .metric-icon.success { background: #ecfdf5; color: #047857; }

    .dashboard-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.45fr) minmax(320px, .8fr);
        gap: 18px;
        align-items: start;
    }

    .queue-card,
    .activity-card,
    .mail-card {
        padding: 22px;
    }

    .section-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        margin-bottom: 18px;
    }

    .section-head-copy h2,
    .section-head-copy h3 {
        margin: 0 0 6px;
        font-size: 1.08rem;
        font-weight: 700;
        color: var(--text);
    }

    .section-head-copy p {
        margin: 0;
        font-size: .84rem;
        color: var(--muted);
        line-height: 1.5;
    }

    .queue-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .queue-item {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 18px;
        align-items: center;
        padding: 16px 18px;
        border: 1px solid rgba(219, 231, 243, .9);
        border-radius: 18px;
        background: #fff;
        transition: transform .2s ease, border-color .2s ease, background-color .2s ease;
    }

    .queue-item:hover {
        transform: translateY(-1px);
        border-color: #b7d8ea;
        background: #fafdff;
    }

    .queue-item-main {
        min-width: 0;
    }

    .queue-item-top {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
        margin-bottom: 8px;
    }

    .queue-protocol {
        font-size: .78rem;
        font-weight: 700;
        color: var(--primary-strong);
        text-transform: uppercase;
        letter-spacing: .06em;
    }

    .queue-name {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text);
        margin-bottom: 4px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .queue-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        font-size: .8rem;
        color: var(--muted);
    }

    .queue-meta span {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .queue-item-side {
        text-align: right;
    }

    .queue-date {
        font-size: .76rem;
        color: var(--muted);
        margin-top: 8px;
    }

    .queue-empty {
        padding: 28px;
        border: 1px dashed rgba(180, 198, 214, .9);
        border-radius: 18px;
        background: #fafcff;
        text-align: center;
        color: var(--muted);
        font-size: .92rem;
    }

    .side-stack {
        display: flex;
        flex-direction: column;
        gap: 18px;
    }

    .priority-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-bottom: 18px;
    }

    .priority-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding: 12px 14px;
        border-radius: 16px;
        background: var(--surface-soft);
        border: 1px solid rgba(219, 231, 243, .85);
    }

    .priority-copy strong {
        display: block;
        font-size: .84rem;
        color: var(--text);
        margin-bottom: 2px;
    }

    .priority-copy span {
        font-size: .76rem;
        color: var(--muted);
    }

    .priority-value {
        padding: 6px 10px;
        border-radius: 999px;
        font-size: .76rem;
        font-weight: 700;
    }

    .priority-value.info { background: #e0f2fe; color: #075985; }
    .priority-value.warning { background: #fff7ed; color: #9a3412; }
    .priority-value.danger { background: #fef2f2; color: #991b1b; }

    .feed-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
        max-height: 440px;
        overflow-y: auto;
        padding-right: 4px;
    }

    .feed-item {
        display: grid;
        grid-template-columns: auto 1fr;
        gap: 12px;
        padding: 12px 14px;
        border-radius: 16px;
        background: var(--surface-soft);
        border: 1px solid rgba(219, 231, 243, .85);
    }

    .feed-icon {
        width: 36px;
        height: 36px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #fff;
        color: var(--primary-strong);
        border: 1px solid rgba(219, 231, 243, .85);
        flex-shrink: 0;
    }

    .feed-copy small {
        display: block;
        color: var(--muted);
        font-size: .72rem;
        margin-bottom: 3px;
    }

    .feed-copy strong {
        display: block;
        font-size: .85rem;
        color: var(--text);
        margin-bottom: 4px;
    }

    .feed-copy span {
        display: block;
        font-size: .78rem;
        color: var(--muted);
        line-height: 1.5;
    }

    .feed-copy .feed-protocol {
        margin-top: 6px;
        color: var(--primary-strong);
        font-weight: 600;
    }

    .section-head .btn {
        border-radius: 12px;
    }

    @media (max-width: 1199px) {
        .metric-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .dashboard-grid,
        .dashboard-hero {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 767px) {
        .hero-card,
        .hero-side-card,
        .metric-card,
        .queue-card,
        .activity-card,
        .mail-card {
            padding: 18px;
            border-radius: 20px;
        }

        .hero-title {
            font-size: 1.45rem;
        }

        .metric-grid {
            grid-template-columns: 1fr;
        }

        .queue-item {
            grid-template-columns: 1fr;
        }

        .queue-item-side {
            text-align: left;
        }

        .section-head {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>

<div class="dashboard-shell">
    <section class="dashboard-hero">
        <div class="hero-card">
            <div class="hero-label">
                <i class="fas fa-bolt"></i>
                Painel operacional
            </div>
            <h2 class="hero-title"><?= $saudacao ?>, <?= htmlspecialchars($_SESSION['admin_nome']) ?>.</h2>
            <p class="hero-copy">
                A fila de hoje está com <strong><?= implode(' · ', array_map('htmlspecialchars', $resumoOperacional)) ?></strong>.
                Use este painel para atacar primeiro o que exige resposta rápida e navegar para os fluxos principais sem passar por telas intermediárias.
            </p>
            <div class="hero-actions">
                <a href="requerimentos.php" class="btn btn-primary">
                    <i class="fas fa-clipboard-list me-2"></i>Abrir requerimentos
                </a>
                <a href="requerimentos.php?nao_visualizados=1" class="btn btn-outline-primary">
                    <i class="fas fa-eye-slash me-2"></i>Não visualizados
                </a>
                <a href="estatisticas.php" class="btn btn-outline-secondary">
                    <i class="fas fa-chart-column me-2"></i>Estatísticas
                </a>
                <?php if ($isAnalista): ?>
                    <a href="<?= $isAdmin ? 'simular_perfil.php?role=analista' : 'requerimentos.php?status=Pendente' ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-magnifying-glass me-2"></i>Triagem
                    </a>
                <?php endif; ?>
                <?php if ($isFiscal): ?>
                    <a href="<?= $isAdmin ? 'simular_perfil.php?role=fiscal' : 'fiscal_dashboard.php' ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-hard-hat me-2"></i>Fiscalização
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="hero-side-card">
            <div class="hero-side-header">
                <h3>Leitura rápida da operação</h3>
                <p>Resumo institucional com foco no que precisa de atenção imediata.</p>
            </div>
            <div class="operational-checklist">
                <div class="operational-check">
                    <i class="fas fa-hourglass-half"></i>
                    <div>
                        <strong>Fluxo em análise</strong>
                        <span><?= $emAnalise ?> processos aguardam andamento técnico ou administrativo.</span>
                    </div>
                </div>
                <div class="operational-check">
                    <i class="fas fa-envelope-open-text"></i>
                    <div>
                        <strong>Fila não lida</strong>
                        <span><?= $naoVisualizados ?> requerimentos ainda não foram visualizados pela equipe.</span>
                    </div>
                </div>
                <div class="operational-check">
                    <i class="fas fa-hard-hat"></i>
                    <div>
                        <strong>Fiscalização</strong>
                        <span><?= (int) $totalAguardandoFiscal ?> processos estão aguardando inspeção ou retorno de campo.</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="metric-grid">
        <article class="metric-card">
            <div class="metric-copy">
                <small>Total da base</small>
                <strong><?= $totalRequerimentos ?></strong>
                <span>Volume geral de requerimentos cadastrados.</span>
            </div>
            <span class="metric-icon info"><i class="fas fa-folder-tree"></i></span>
        </article>
        <article class="metric-card">
            <div class="metric-copy">
                <small>Em análise</small>
                <strong><?= $emAnalise ?></strong>
                <span>Principal fila ativa do fluxo administrativo.</span>
            </div>
            <span class="metric-icon warning"><i class="fas fa-hourglass-half"></i></span>
        </article>
        <article class="metric-card">
            <div class="metric-copy">
                <small>Não visualizados</small>
                <strong><?= $naoVisualizados ?></strong>
                <span>Entradas que ainda precisam de primeiro olhar.</span>
            </div>
            <span class="metric-icon danger"><i class="fas fa-eye-slash"></i></span>
        </article>
        <article class="metric-card">
            <div class="metric-copy">
                <small>Fiscalização</small>
                <strong><?= (int) $totalAguardandoFiscal ?></strong>
                <span>Processos aguardando ação da equipe de campo.</span>
            </div>
            <span class="metric-icon success"><i class="fas fa-hard-hat"></i></span>
        </article>
    </section>

    <section class="dashboard-grid">
        <div class="queue-card">
            <div class="section-head">
                <div class="section-head-copy">
                    <h2>Fila operacional</h2>
                    <p>Últimos requerimentos recebidos, organizados para leitura rápida e ação imediata.</p>
                </div>
                <a href="requerimentos.php" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-arrow-up-right-from-square me-1"></i>Ver todos
                </a>
            </div>

            <?php if ($ultimosRequerimentos): ?>
                <div class="queue-list">
                    <?php foreach ($ultimosRequerimentos as $req): ?>
                        <?php $meta = $statusMeta[$req['status']] ?? ['class' => 'status-pendente', 'label' => $req['status']]; ?>
                        <a href="visualizar_requerimento.php?id=<?= (int) $req['id'] ?>" class="queue-item">
                            <div class="queue-item-main">
                                <div class="queue-item-top">
                                    <span class="queue-protocol">Protocolo #<?= htmlspecialchars($req['protocolo']) ?></span>
                                    <span class="badge badge-status <?= htmlspecialchars($meta['class']) ?>"><?= htmlspecialchars($meta['label']) ?></span>
                                </div>
                                <div class="queue-name"><?= htmlspecialchars($req['requerente']) ?></div>
                                <div class="queue-meta">
                                    <span><i class="fas fa-file-lines"></i><?= htmlspecialchars($req['tipo_alvara']) ?></span>
                                    <span><i class="far fa-calendar"></i><?= formataData($req['data_envio']) ?></span>
                                </div>
                            </div>
                            <div class="queue-item-side">
                                <div class="btn btn-light btn-sm">
                                    Abrir <i class="fas fa-arrow-right ms-1"></i>
                                </div>
                                <div class="queue-date">Atualizado em <?= date('d/m \à\s H:i', strtotime($req['data_envio'])) ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="queue-empty">Nenhum requerimento encontrado para compor a fila operacional.</div>
            <?php endif; ?>
        </div>

        <div class="side-stack">
            <div class="activity-card">
                <div class="section-head">
                    <div class="section-head-copy">
                        <h3>Prioridades do dia</h3>
                        <p>Indicadores rápidos para definir o próximo movimento.</p>
                    </div>
                </div>

                <div class="priority-list">
                    <div class="priority-row">
                        <div class="priority-copy">
                            <strong>Protocolos em análise</strong>
                            <span>Fila central do fluxo administrativo</span>
                        </div>
                        <span class="priority-value warning"><?= $emAnalise ?></span>
                    </div>
                    <div class="priority-row">
                        <div class="priority-copy">
                            <strong>Itens não visualizados</strong>
                            <span>Entradas que precisam de primeira leitura</span>
                        </div>
                        <span class="priority-value danger"><?= $naoVisualizados ?></span>
                    </div>
                    <div class="priority-row">
                        <div class="priority-copy">
                            <strong>Aguardando fiscalização</strong>
                            <span>Dependem de inspeção ou retorno da equipe</span>
                        </div>
                        <span class="priority-value info"><?= (int) $totalAguardandoFiscal ?></span>
                    </div>
                </div>

                <div class="section-head" style="margin-bottom:12px;">
                    <div class="section-head-copy">
                        <h3>Histórico recente</h3>
                        <p>Últimas ações registradas na plataforma.</p>
                    </div>
                </div>

                <div class="feed-list">
                    <?php if ($historicoAcoes): ?>
                        <?php foreach ($historicoAcoes as $acao): ?>
                            <div class="feed-item">
                                <span class="feed-icon"><i class="fas fa-clock-rotate-left"></i></span>
                                <div class="feed-copy">
                                    <small><?= date('d/m H:i', strtotime($acao['data_acao'])) ?></small>
                                    <strong><?= htmlspecialchars($acao['admin_nome'] ?? 'Sistema') ?></strong>
                                    <span><?= htmlspecialchars($acao['acao']) ?></span>
                                    <?php if ($acao['protocolo']): ?>
                                        <span class="feed-protocol">Protocolo: <?= htmlspecialchars($acao['protocolo']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="queue-empty">Nenhuma ação registrada até o momento.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mail-card">
                <div class="section-head">
                    <div class="section-head-copy">
                        <h3>Últimos emails</h3>
                        <p>Visão rápida da comunicação mais recente enviada pelo sistema.</p>
                    </div>
                </div>

                <div class="feed-list" style="max-height:320px;">
                    <?php if ($ultimosEmails): ?>
                        <?php foreach ($ultimosEmails as $email): ?>
                            <?php $emailOk = strtoupper($email['status']) === 'SUCESSO'; ?>
                            <div class="feed-item">
                                <span class="feed-icon" style="color:<?= $emailOk ? '#047857' : '#b91c1c' ?>;">
                                    <i class="fas <?= $emailOk ? 'fa-paper-plane' : 'fa-triangle-exclamation' ?>"></i>
                                </span>
                                <div class="feed-copy">
                                    <small><?= date('d/m H:i', strtotime($email['data_envio'])) ?></small>
                                    <strong><?= htmlspecialchars($email['email_destino']) ?></strong>
                                    <span><?= htmlspecialchars($email['assunto']) ?></span>
                                    <span class="mt-1">
                                        <span class="badge badge-status <?= $emailOk ? 'status-aprovado' : 'status-reprovado' ?>"><?= htmlspecialchars($email['status']) ?></span>
                                    </span>
                                    <?php if ($email['protocolo']): ?>
                                        <span class="feed-protocol">Protocolo: <?= htmlspecialchars($email['protocolo']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="queue-empty">Nenhum email enviado recentemente.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include 'footer.php'; ?>
