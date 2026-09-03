<?php
require_once 'conexao.php';
require_once __DIR__ . '/../includes/functions.php';

verificaLogin();

// Esta tela é exclusiva do secretário (setor3) — outros roles usam o painel
// padrão (index.php) e a fila normal (requerimentos.php). O secretário nunca
// gera documento aqui: ele só decide sobre o que o fiscal (setor2) encaminhou.
if (($_SESSION['admin_nivel'] ?? '') !== 'secretario') {
    header('Location: index.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$adminId = (int) $_SESSION['admin_id'];
$tabSolicitada = $_GET['tab'] ?? 'dashboard';
$tab = in_array($tabSolicitada, ['dashboard', 'fila', 'detalhe'], true) ? $tabSolicitada : 'dashboard';

// Retorno do fluxo_setor_handler.php (passo "Pronto" da modal de assinatura) —
// sem isso, depois de decidir o processo o secretário só via a fila esvaziada,
// sem confirmação nenhuma de que a ação foi de fato registrada.
$acaoConcluidaLabel = [
    'setor3_aprovado' => ['titulo' => 'Aprovado e devolvido ao Setor 2', 'texto' => 'O Setor 2 vai cuidar da entrega ao cidadão a partir daqui.'],
    'devolver_setor2' => ['titulo' => 'Devolvido para ajuste', 'texto' => 'O processo voltou para análise no Setor 2.'],
    'setor3_recusado' => ['titulo' => 'Processo recusado', 'texto' => 'O retorno foi registrado como recusado para o Setor 2.'],
    'setor3_sem_decisao' => ['titulo' => 'Devolvido sem decisão', 'texto' => 'O processo voltou para o Setor 2.'],
];
$mensagemSucesso = null;
if (($_GET['success'] ?? '') === 'fluxo_atualizado') {
    $mensagemSucesso = $acaoConcluidaLabel[$_GET['acao_concluida'] ?? ''] ?? ['titulo' => 'Ação registrada', 'texto' => 'O processo foi atualizado.'];
}
$mensagemErro = null;
if (($_GET['error'] ?? '') !== '') {
    $mensagemErro = $_SESSION['fluxo_erro_msg'] ?? 'Não foi possível concluir a ação. Tente novamente.';
    unset($_SESSION['fluxo_erro_msg']);
}

// ── Fila "Para assinar": lê exclusivamente solicitacoes_assinatura endereçada
// a este secretário. Denúncias e triagem de setor1/2 não entram aqui — é
// exatamente o que o design pediu ("esta fila lê apenas solicitacoes_assinatura
// com destinatario_id = você e status='pendente'").
$stmt = $pdo->prepare("
    SELECT sa.id AS solicitacao_id, sa.documento_id, sa.requerimento_id, sa.solicitante_id,
           sa.mensagem, sa.criado_em,
           r.protocolo, r.tipo_alvara,
           req.nome AS requerente_nome,
           fiscal.nome AS fiscal_nome,
           ad.tipo_documento, ad.nome_arquivo,
           (EXISTS(
               SELECT 1 FROM assinaturas_digitais ad2
               WHERE ad2.documento_id = sa.documento_id AND ad2.assinante_id = sa.solicitante_id
           )) AS fiscal_assinou
    FROM solicitacoes_assinatura sa
    JOIN requerimentos r ON r.id = sa.requerimento_id
    JOIN requerentes req ON req.id = r.requerente_id
    JOIN administradores fiscal ON fiscal.id = sa.solicitante_id
    JOIN assinaturas_digitais ad ON ad.documento_id = sa.documento_id
    WHERE sa.destinatario_id = ? AND sa.status = 'pendente'
    GROUP BY sa.documento_id
    ORDER BY sa.criado_em ASC
");
$stmt->execute([$adminId]);
$filaAssinatura = $stmt->fetchAll(PDO::FETCH_ASSOC);

$agora = new DateTimeImmutable('now');
foreach ($filaAssinatura as &$item) {
    try {
        $item['dias_espera'] = (new DateTimeImmutable((string) $item['criado_em']))->diff($agora)->days;
    } catch (Throwable $e) {
        $item['dias_espera'] = 0;
    }
    $item['tipo_doc_legivel'] = ucfirst(str_replace('_', ' ', (string) ($item['tipo_documento'] ?? 'documento')));
}
unset($item);

$qtdFila = count($filaAssinatura);
$qtdAssinadoFiscal = count(array_filter($filaAssinatura, static fn (array $i): bool => (bool) $i['fiscal_assinou']));
$qtdSoSua = $qtdFila - $qtdAssinadoFiscal;
$qtdAtraso = count(array_filter($filaAssinatura, static fn (array $i): bool => $i['dias_espera'] >= 3));

// KPIs da dashboard
$totalSetor3 = (int) $pdo->query("SELECT COUNT(*) FROM requerimentos WHERE setor_atual = 'setor3'")->fetchColumn();
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT requerimento_id) FROM historico_acoes
    WHERE admin_id = ? AND acao LIKE 'Setor 3 aprovou%' AND data_acao >= DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$stmt->execute([$adminId]);
$retornadosSemana = (int) $stmt->fetchColumn();

$hubSetores = [];
foreach (['setor1', 'setor2', 'setor3'] as $s) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM requerimentos WHERE setor_atual = ? AND aguardando_acao != 'concluido'");
    $st->execute([$s]);
    $hubSetores[$s] = (int) $st->fetchColumn();
}

// ── Detalhe de um item específico da fila (aba "Detalhes do processo")
$itemDetalhe = null;
$documentoIdDetalhe = trim((string) ($_GET['doc'] ?? ''));
if ($tab === 'detalhe' && $documentoIdDetalhe !== '') {
    foreach ($filaAssinatura as $i) {
        if ($i['documento_id'] === $documentoIdDetalhe) {
            $itemDetalhe = $i;
            break;
        }
    }
    if (!$itemDetalhe) {
        // Documento não está mais na fila (já resolvido) — volta pra fila.
        header('Location: painel_secretario.php?tab=fila');
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT r.*, req.nome AS requerente_nome, req.cpf_cnpj AS requerente_cpf_cnpj
        FROM requerimentos r
        JOIN requerentes req ON req.id = r.requerente_id
        WHERE r.id = ?
    ");
    $stmt->execute([(int) $itemDetalhe['requerimento_id']]);
    $processoDetalhe = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmt = $pdo->prepare("
        SELECT ha.acao, ha.data_acao, a.nome AS admin_nome
        FROM historico_acoes ha
        LEFT JOIN administradores a ON a.id = ha.admin_id
        WHERE ha.requerimento_id = ?
        ORDER BY ha.data_acao DESC
        LIMIT 12
    ");
    $stmt->execute([(int) $itemDetalhe['requerimento_id']]);
    $historicoDetalhe = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM assinaturas_digitais WHERE requerimento_id = ? AND assinante_id = ?");
    $stmt->execute([(int) $itemDetalhe['requerimento_id'], $adminId]);
    $jaAssineiAlgo = (int) $stmt->fetchColumn() > 0;
}

include 'header.php';
?>
<style>
    .ps-shell { display:flex; flex-direction:column; gap:16px; max-width:1100px; margin:0 auto; }
    .ps-tabs { display:flex; gap:6px; margin-bottom:2px; border-bottom:1px solid var(--line); overflow-x:auto; }
    .ps-tab { display:inline-flex; align-items:center; gap:8px; padding:10px 14px; border:0; border-bottom:2px solid transparent; background:transparent; font-size:.88rem; font-weight:700; color:var(--muted); white-space:nowrap; text-decoration:none; }
    .ps-tab.active { color:var(--primary); border-bottom-color:var(--primary); }
    .ps-tab .badge-count { font-size:.68rem; font-weight:800; padding:1px 7px; border-radius:999px; background:#fdf3e0; color:#b7791f; }
    .ps-kpis { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; }
    .ps-kpi { background:#fff; border:1px solid var(--line); border-radius:14px; padding:16px 18px; text-align:center; }
    .ps-kpi.highlight { background:var(--primary); }
    .ps-kpi.highlight .ps-kpi-value, .ps-kpi.highlight .ps-kpi-label { color:#fff; }
    .ps-kpi-value { font-size:1.9rem; font-weight:800; color:var(--ink); line-height:1; }
    .ps-kpi-label { margin-top:4px; font-size:.78rem; color:var(--muted-2); }
    .ps-panel { background:#fff; border:1px solid var(--line); border-radius:16px; overflow:hidden; }
    .ps-panel-head { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:14px 18px; border-bottom:1px solid #eef2ef; font-size:.95rem; font-weight:700; color:var(--ink); }
    .ps-chips { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:7px; margin-bottom:12px; }
    .ps-chip { padding:8px 11px; border:1px solid var(--line); border-radius:10px; background:#fff; display:flex; align-items:center; justify-content:space-between; gap:8px; font-size:.78rem; font-weight:700; color:var(--muted); }
    .ps-card { display:flex; flex-direction:column; gap:9px; padding:16px 18px; border-bottom:1px solid #f4f7f5; }
    .ps-card:last-child { border-bottom:0; }
    .ps-card-top { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .ps-pill { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:999px; font-size:.68rem; font-weight:800; text-transform:uppercase; letter-spacing:.03em; }
    .ps-pill.assinado { background:#eaf2ed; color:#245b38; }
    .ps-pill.sosua { background:#fdf3e0; color:#b7791f; }
    .ps-protocol { font-family:'Geist Mono',ui-monospace,monospace; font-size:.85rem; font-weight:600; color:var(--ink); }
    .ps-name { font-size:1rem; font-weight:700; color:var(--ink); }
    .ps-meta { font-size:.82rem; color:var(--muted-2); line-height:1.5; }
    .ps-actions { display:flex; gap:8px; flex-wrap:wrap; }
    .ps-btn { display:inline-flex; align-items:center; justify-content:center; gap:7px; min-height:44px; padding:0 16px; border-radius:11px; font-size:.86rem; font-weight:700; text-decoration:none; border:0; cursor:pointer; }
    .ps-btn-primary { background:var(--primary); color:#fff; }
    .ps-btn-primary:hover { background:var(--primary-strong); color:#fff; }
    .ps-btn-secondary { background:#fff; border:1px solid var(--line); color:var(--ink); }
    .ps-btn-danger { background:#fff; border:1px solid #f3cccc; color:#b13232; }
    .ps-empty { padding:40px 18px; text-align:center; color:var(--muted); }
    .ps-empty i { font-size:2rem; color:#009640; margin-bottom:10px; display:block; }
    .ps-detail-grid { display:grid; grid-template-columns:minmax(0,1fr) 300px; gap:14px; align-items:start; }
    @media (max-width:900px) { .ps-detail-grid { grid-template-columns:1fr; } }
    .ps-info-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:1px; background:#f4f7f5; }
    .ps-info-cell { background:#fff; padding:12px 15px; }
    .ps-info-label { font-size:.72rem; color:#8fa399; }
    .ps-info-value { margin-top:2px; font-size:.86rem; font-weight:600; }
    .ps-banner { display:flex; align-items:flex-start; gap:10px; padding:11px 14px; border-radius:12px; margin-bottom:14px; background:#f3e8ff; border:1px solid rgba(126,34,206,.13); color:#7e22ce; font-size:.83rem; line-height:1.5; }
    .ps-timeline-row { display:grid; grid-template-columns:18px minmax(0,1fr); gap:11px; padding-bottom:14px; }
    .ps-timeline-dot { width:9px; height:9px; border-radius:50%; background:#fff; border:2px solid #a7c3b2; margin-top:4px; }
    .ps-timeline-title { font-size:.83rem; font-weight:600; }
    .ps-timeline-meta { font-size:.75rem; color:#8fa399; }

    /* Modal de assinatura rápida — 3 passos, conforme o design aprovado */
    .ps-modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.48); display:none; align-items:center; justify-content:center; padding:24px; z-index:1050; }
    .ps-modal-backdrop.open { display:flex; }
    .ps-modal { background:#fff; border-radius:16px; width:min(680px,100%); max-height:92vh; overflow:auto; box-shadow:0 24px 60px rgba(0,0,0,.28); }
    .ps-modal-head { display:flex; align-items:center; gap:12px; padding:18px 20px 14px; border-bottom:1px solid #f0f2f0; }
    .ps-modal-steps { display:flex; align-items:center; padding:12px 20px; border-bottom:1px solid #f0f2f0; font-size:.8rem; color:var(--muted-2); flex-wrap:wrap; gap:4px; }
    .ps-modal-body { padding:16px 20px 20px; }
    .ps-method { display:flex; align-items:center; gap:12px; width:100%; padding:12px 13px; border:1.5px solid var(--line); border-radius:12px; background:#fff; text-align:left; cursor:pointer; margin-bottom:8px; }
    .ps-method.selected { border-color:var(--primary); background:#f2f7f3; }
    .ps-method-ic { width:34px; height:34px; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .ps-decision { display:flex; align-items:center; gap:12px; width:100%; padding:12px 13px; border:1.5px solid var(--line); border-radius:12px; background:#fff; text-align:left; cursor:pointer; margin-bottom:8px; }
    .ps-decision.selected { border-color:var(--primary); background:#f2f7f3; }
    .ps-decision[disabled] { opacity:.45; cursor:not-allowed; }
    .ps-alert { display:flex; gap:9px; padding:11px 13px; border-radius:10px; margin-bottom:14px; font-size:.82rem; line-height:1.5; }
    .ps-alert-info { background:#e8effd; color:#1e429f; border:1px solid #c7d7fb; }
    .ps-alert-warn { background:#fdf3e0; color:#8a4b08; border:1px solid #f4ca8b; }
</style>

<div class="ps-shell">
    <nav class="ps-tabs">
        <a class="ps-tab <?= $tab === 'dashboard' ? 'active' : '' ?>" href="painel_secretario.php?tab=dashboard"><i class="fas fa-gauge-high"></i> Dashboard</a>
        <a class="ps-tab <?= $tab === 'fila' ? 'active' : '' ?>" href="painel_secretario.php?tab=fila"><i class="fas fa-file-signature"></i> Para assinar <?php if ($qtdFila > 0): ?><span class="badge-count"><?= $qtdFila ?></span><?php endif; ?></a>
        <a class="ps-tab" href="fila_revisao_secretario.php"><i class="fas fa-clipboard-list"></i> Processos do setor 3</a>
    </nav>

    <?php if ($tab === 'dashboard'): ?>
        <?php
        $horaAtual = (int) date('H');
        $saudacao = $horaAtual >= 18 ? 'Boa noite' : ($horaAtual >= 12 ? 'Boa tarde' : 'Bom dia');
        $primeiroNome = preg_split('/\s+/', trim((string) ($_SESSION['admin_nome'] ?? '')), -1, PREG_SPLIT_NO_EMPTY)[0] ?? '';
        ?>
        <div>
            <h2 style="margin:0;font-size:1.5rem;font-weight:800;"><?= htmlspecialchars($saudacao) ?>, <?= htmlspecialchars($primeiroNome) ?></h2>
            <p style="margin:6px 0 0;color:var(--muted);">
                <?php if ($qtdFila > 0): ?>
                    <?= $qtdFila ?> <?= $qtdFila === 1 ? 'documento espera' : 'documentos esperam' ?> sua assinatura.
                <?php else: ?>
                    Tudo em dia — nenhum documento esperando sua assinatura agora.
                <?php endif; ?>
            </p>
        </div>

        <section class="ps-kpis">
            <div class="ps-kpi highlight">
                <div class="ps-kpi-value"><?= $qtdFila ?></div>
                <div class="ps-kpi-label">Aguardando sua assinatura</div>
            </div>
            <div class="ps-kpi">
                <div class="ps-kpi-value"><?= $totalSetor3 ?></div>
                <div class="ps-kpi-label">Processos no setor 3</div>
            </div>
            <div class="ps-kpi">
                <div class="ps-kpi-value"><?= $retornadosSemana ?></div>
                <div class="ps-kpi-label">Retornados ao setor 2 (7 dias)</div>
            </div>
        </section>

        <section class="ps-panel">
            <div class="ps-panel-head">Precisa de você agora <a href="painel_secretario.php?tab=fila" style="font-size:.8rem;font-weight:600;">Ver a fila completa <i class="fas fa-chevron-right" style="font-size:.68rem;"></i></a></div>
            <?php if ($filaAssinatura): ?>
                <?php foreach (array_slice($filaAssinatura, 0, 4) as $item): ?>
                    <div class="ps-card">
                        <div class="ps-card-top">
                            <span class="ps-pill <?= $item['fiscal_assinou'] ? 'assinado' : 'sosua' ?>"><?= $item['fiscal_assinou'] ? 'Já assinado pelo fiscal' : 'Só sua assinatura' ?></span>
                            <span class="ps-protocol"><?= htmlspecialchars($item['protocolo']) ?></span>
                        </div>
                        <div>
                            <div class="ps-name"><?= htmlspecialchars($item['requerente_nome']) ?></div>
                            <div class="ps-meta"><?= htmlspecialchars($item['tipo_doc_legivel']) ?> · encaminhado por <?= htmlspecialchars($item['fiscal_nome']) ?></div>
                        </div>
                        <div class="ps-actions">
                            <a href="painel_secretario.php?tab=detalhe&doc=<?= urlencode($item['documento_id']) ?>" class="ps-btn ps-btn-primary"><i class="fas fa-pen-nib"></i> <?= $item['fiscal_assinou'] ? 'Co-assinar' : 'Assinar' ?></a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="ps-empty"><i class="fas fa-circle-check"></i>Nenhum documento esperando você.</div>
            <?php endif; ?>
        </section>

        <details class="ps-panel" style="padding:0;">
            <summary style="cursor:pointer;padding:14px 18px;font-weight:700;font-size:.88rem;color:var(--muted);list-style:none;">Outros setores — só para acompanhar</summary>
            <div style="padding:0 18px 14px;">
                <div class="ps-timeline-row" style="grid-template-columns:1fr auto;padding:8px 0;border-bottom:1px solid #f4f7f5;"><span>Triagem Ambiental — Setor 1</span><strong><?= $hubSetores['setor1'] ?></strong></div>
                <div class="ps-timeline-row" style="grid-template-columns:1fr auto;padding:8px 0;border-bottom:1px solid #f4f7f5;"><span>Fiscalização de Obras — Setor 2</span><strong><?= $hubSetores['setor2'] ?></strong></div>
                <div class="ps-timeline-row" style="grid-template-columns:1fr auto;padding:8px 0;"><span>Revisão do Secretário — Setor 3</span><strong><?= $hubSetores['setor3'] ?></strong></div>
            </div>
        </details>

    <?php elseif ($tab === 'fila'): ?>
        <?php if ($mensagemSucesso): ?>
            <div class="ps-alert ps-alert-info" style="background:#e6f2ea;border-color:#cfe6da;color:#14532d;">
                <i class="fas fa-circle-check"></i>
                <div><strong><?= htmlspecialchars($mensagemSucesso['titulo']) ?></strong><div style="margin-top:2px;"><?= htmlspecialchars($mensagemSucesso['texto']) ?></div></div>
            </div>
        <?php elseif ($mensagemErro): ?>
            <div class="ps-alert" style="background:#fce7e7;border:1px solid #f3cccc;color:#b13232;">
                <i class="fas fa-triangle-exclamation"></i>
                <div><strong>Não foi possível concluir</strong><div style="margin-top:2px;"><?= htmlspecialchars($mensagemErro) ?></div></div>
            </div>
        <?php endif; ?>
        <div>
            <h1 style="margin:0;font-size:1.3rem;font-weight:800;"><i class="fas fa-file-signature" style="color:var(--primary);margin-right:8px;"></i>Para assinar</h1>
            <p style="margin:4px 0 0;color:var(--muted);"><?= $qtdFila ?> <?= $qtdFila === 1 ? 'documento encaminhado' : 'documentos encaminhados' ?> pela Fiscalização de Obras.</p>
        </div>

        <div class="ps-chips">
            <div class="ps-chip"><span><i class="fas fa-inbox"></i> Todos</span><strong><?= $qtdFila ?></strong></div>
            <div class="ps-chip"><span><i class="fas fa-user-pen"></i> Já assinado pelo fiscal</span><strong><?= $qtdAssinadoFiscal ?></strong></div>
            <div class="ps-chip"><span><i class="fas fa-signature"></i> Só sua assinatura</span><strong><?= $qtdSoSua ?></strong></div>
            <div class="ps-chip"><span><i class="fas fa-clock"></i> Esperando 3+ dias</span><strong><?= $qtdAtraso ?></strong></div>
        </div>

        <section class="ps-panel">
            <?php if ($filaAssinatura): ?>
                <?php foreach ($filaAssinatura as $item): ?>
                    <div class="ps-card">
                        <div class="ps-card-top">
                            <span class="ps-pill <?= $item['fiscal_assinou'] ? 'assinado' : 'sosua' ?>"><?= $item['fiscal_assinou'] ? 'Já assinado pelo fiscal' : 'Só sua assinatura' ?></span>
                            <span class="ps-protocol"><?= htmlspecialchars($item['protocolo']) ?></span>
                        </div>
                        <div>
                            <div class="ps-name"><?= htmlspecialchars($item['requerente_nome']) ?></div>
                            <div class="ps-meta">
                                <?= htmlspecialchars($item['tipo_doc_legivel']) ?> · <?= htmlspecialchars(nomeAlvara($item['tipo_alvara'])) ?><br>
                                Encaminhado por <strong><?= htmlspecialchars($item['fiscal_nome']) ?></strong> · há <?= $item['dias_espera'] ?> dia(s)
                                <?php if (!empty($item['mensagem'])): ?> — "<?= htmlspecialchars($item['mensagem']) ?>"<?php endif; ?>
                            </div>
                        </div>
                        <div class="ps-actions">
                            <a href="painel_secretario.php?tab=detalhe&doc=<?= urlencode($item['documento_id']) ?>" class="ps-btn ps-btn-primary"><i class="fas fa-pen-nib"></i> <?= $item['fiscal_assinou'] ? 'Co-assinar' : 'Assinar' ?></a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="ps-empty"><i class="fas fa-circle-check"></i>Nenhum documento esperando você.</div>
            <?php endif; ?>
        </section>
        <p style="margin:14px 0 0;font-size:.8rem;color:var(--muted-2);">Esta fila lê apenas documentos encaminhados pelo Setor 2. Denúncias não entram aqui.</p>

    <?php elseif ($tab === 'detalhe' && $itemDetalhe): ?>
        <div style="display:flex;align-items:center;gap:10px;font-size:.8rem;color:var(--muted);">
            <a href="painel_secretario.php?tab=fila" class="ps-btn ps-btn-secondary" style="min-height:32px;padding:5px 10px;"><i class="fas fa-arrow-left"></i> Para assinar</a>
            <span style="font-family:'Geist Mono',ui-monospace,monospace;"><?= htmlspecialchars($itemDetalhe['protocolo']) ?></span>
        </div>

        <div class="ps-panel" style="padding:20px 22px;">
            <div style="font-size:1.3rem;font-weight:800;"><?= htmlspecialchars($itemDetalhe['protocolo']) ?></div>
            <div style="font-size:1rem;font-weight:700;margin-top:4px;"><?= htmlspecialchars($itemDetalhe['requerente_nome']) ?></div>
            <div style="margin-top:8px;color:var(--muted-2);font-size:.85rem;"><?= htmlspecialchars(nomeAlvara($itemDetalhe['tipo_alvara'])) ?> · <?= $itemDetalhe['dias_espera'] ?> dia(s) em aberto</div>
        </div>

        <div class="ps-banner"><i class="fas fa-shield-halved"></i> Aprovar exige pelo menos uma assinatura sua neste processo. Assine o documento antes de retornar ao setor 2.</div>

        <div class="ps-detail-grid">
            <div style="display:flex;flex-direction:column;gap:14px;">
                <div class="ps-panel">
                    <div class="ps-panel-head" style="font-size:.68rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#3d5c46;">Documento aguardando sua assinatura</div>
                    <div style="display:flex;align-items:center;gap:14px;padding:15px;">
                        <div style="width:46px;height:46px;border-radius:12px;background:#f0f7f3;color:#1c4b36;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;"><i class="fas fa-file-lines"></i></div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:700;font-size:.9rem;"><?= htmlspecialchars($itemDetalhe['nome_arquivo']) ?></div>
                            <div style="font-size:.78rem;color:#8fa399;margin-top:2px;">Encaminhado por <?= htmlspecialchars($itemDetalhe['fiscal_nome']) ?></div>
                        </div>
                        <button type="button" class="ps-btn ps-btn-secondary" onclick="psAbrirModal()"><i class="fas fa-eye"></i> Abrir e assinar</button>
                    </div>
                </div>

                <div class="ps-panel">
                    <div class="ps-panel-head" style="font-size:.68rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#3d5c46;">Dados do processo</div>
                    <div class="ps-info-grid">
                        <div class="ps-info-cell"><div class="ps-info-label">Requerente</div><div class="ps-info-value"><?= htmlspecialchars($processoDetalhe['requerente_nome'] ?? '—') ?></div></div>
                        <div class="ps-info-cell"><div class="ps-info-label">CPF/CNPJ</div><div class="ps-info-value"><?= htmlspecialchars($processoDetalhe['requerente_cpf_cnpj'] ?? '—') ?></div></div>
                        <div class="ps-info-cell"><div class="ps-info-label">Tipo</div><div class="ps-info-value"><?= htmlspecialchars(nomeAlvara($itemDetalhe['tipo_alvara'])) ?></div></div>
                        <div class="ps-info-cell"><div class="ps-info-label">Status</div><div class="ps-info-value"><?= htmlspecialchars($processoDetalhe['status'] ?? '—') ?></div></div>
                    </div>
                </div>

                <div class="ps-panel">
                    <div class="ps-panel-head" style="font-size:.68rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#3d5c46;">Movimentações</div>
                    <div style="padding:14px 15px 0;">
                        <?php foreach ($historicoDetalhe as $h): ?>
                            <div class="ps-timeline-row">
                                <div class="ps-timeline-dot"></div>
                                <div>
                                    <div class="ps-timeline-title"><?= htmlspecialchars($h['acao']) ?></div>
                                    <div class="ps-timeline-meta"><?= htmlspecialchars($h['admin_nome'] ?? 'Sistema') ?> · <?= date('d/m/Y H:i', strtotime($h['data_acao'])) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div style="display:flex;flex-direction:column;gap:12px;">
                <div class="ps-panel">
                    <div class="ps-panel-head" style="font-size:.68rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#3d5c46;">Decisão final</div>
                    <div style="padding:14px 15px;display:flex;flex-direction:column;gap:8px;">
                        <button type="button" class="ps-btn ps-btn-primary" onclick="psAbrirModal()"><i class="fas fa-pen-nib"></i> <?= $itemDetalhe['fiscal_assinou'] ? 'Co-assinar' : 'Assinar' ?> e retornar ao setor 2</button>
                        <p style="margin:4px 0 0;font-size:.75rem;color:#8fa399;">Devolver e recusar exigem motivo. Todas as ações devolvem o processo ao setor 2 — a entrega ao cidadão continua sendo feita por lá.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de assinatura rápida (3 passos) -->
        <div class="ps-modal-backdrop" id="psModalBackdrop">
            <div class="ps-modal">
                <div class="ps-modal-head">
                    <div style="width:40px;height:40px;border-radius:10px;background:#e6f2ea;color:#14532d;display:flex;align-items:center;justify-content:center;"><i class="fas fa-file-signature"></i></div>
                    <div style="flex:1;">
                        <h3 style="margin:0;font-size:.97rem;font-weight:800;"><?= $itemDetalhe['fiscal_assinou'] ? 'Co-assinar documento' : 'Assinar documento' ?></h3>
                        <div style="font-size:.78rem;color:#8fa399;"><?= htmlspecialchars($itemDetalhe['tipo_doc_legivel']) ?> · <?= htmlspecialchars($itemDetalhe['protocolo']) ?></div>
                    </div>
                    <button type="button" onclick="psFecharModal()" style="width:34px;height:34px;border:1px solid #e3e8e4;border-radius:10px;background:#fff;cursor:pointer;"><i class="fas fa-xmark"></i></button>
                </div>
                <div class="ps-modal-steps">
                    <span id="psStepLabel1" style="font-weight:700;color:#14532d;">1. Assinar</span>&nbsp;&rarr;&nbsp;
                    <span id="psStepLabel2">2. Retornar ao setor 2</span>&nbsp;&rarr;&nbsp;
                    <span id="psStepLabel3">3. Pronto</span>
                </div>
                <div class="ps-modal-body">
                    <!-- Passo 1: método de assinatura -->
                    <div id="psPasso1">
                        <?php if (!empty($itemDetalhe['mensagem'])): ?>
                            <div class="ps-alert ps-alert-info"><i class="fas fa-user-pen"></i> <strong><?= htmlspecialchars($itemDetalhe['fiscal_nome']) ?></strong> encaminhou: "<?= htmlspecialchars($itemDetalhe['mensagem']) ?>"</div>
                        <?php endif; ?>

                        <div style="border:1px solid #e3e8e4;border-radius:12px;overflow:hidden;margin-bottom:14px;">
                            <iframe src="assinatura/redownload_pdf.php?id=<?= urlencode($itemDetalhe['documento_id']) ?>&inline=1" style="width:100%;height:260px;border:0;display:block;background:#525659;"></iframe>
                        </div>

                        <div style="font-size:.68rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#3d5c46;margin-bottom:8px;">Como você quer assinar</div>

                        <button type="button" class="ps-method selected" data-metodo="pin" onclick="psEscolherMetodo('pin')">
                            <span class="ps-method-ic" style="background:#e6f2ea;color:#14532d;"><i class="fas fa-lock"></i></span>
                            <span><span style="display:block;font-weight:700;font-size:.86rem;">Assinar eletronicamente</span><span style="display:block;font-size:.77rem;color:#8fa399;">Com o PIN da sua chave pessoal — assinatura registrada na hora</span></span>
                        </button>
                        <button type="button" class="ps-method" data-metodo="gov" onclick="psEscolherMetodo('gov')">
                            <span class="ps-method-ic" style="background:#e8effd;color:#1d4ed8;"><i class="fas fa-id-card"></i></span>
                            <span><span style="display:block;font-weight:700;font-size:.86rem;">Assinar via Gov.br</span><span style="display:block;font-size:.77rem;color:#8fa399;">Baixa o PDF e abre o assinador do gov.br — retorno ainda manual</span></span>
                        </button>
                        <button type="button" class="ps-method" data-metodo="print" onclick="psEscolherMetodo('print')">
                            <span class="ps-method-ic" style="background:#fdf3e0;color:#b7791f;"><i class="fas fa-print"></i></span>
                            <span><span style="display:block;font-weight:700;font-size:.86rem;">Imprimir e assinar à mão</span><span style="display:block;font-size:.77rem;color:#8fa399;">Gera o PDF para impressão e segue direto para a decisão</span></span>
                        </button>

                        <div id="psPinWrap" style="margin:14px 0;">
                            <label style="display:flex;gap:6px;align-items:center;margin-bottom:5px;font-size:.78rem;font-weight:700;color:#14532d;"><i class="fas fa-lock"></i> PIN de assinatura</label>
                            <input type="password" id="psPin" placeholder="Digite seu PIN pessoal de assinatura" style="width:100%;padding:11px 12px;border:1px solid #e3e8e4;border-radius:10px;box-sizing:border-box;">
                        </div>
                        <div id="psGovAlert" class="ps-alert ps-alert-info" style="display:none;"><i class="fas fa-circle-info"></i> Ainda não há integração: o sistema baixa o PDF e abre o gov.br numa nova aba. Depois de assinar por lá, volte aqui para registrar a decisão.</div>
                        <div id="psPrintAlert" class="ps-alert ps-alert-warn" style="display:none;"><i class="fas fa-triangle-exclamation"></i> A assinatura em papel não é registrada no sistema. Como aprovar exige assinatura digital, este caminho segue para devolução/recusa, ou fica aguardando a coleta física.</div>

                        <div id="psErro1" style="display:none;color:#b13232;font-size:.83rem;margin-bottom:10px;"></div>

                        <div style="display:flex;gap:8px;justify-content:flex-end;">
                            <button type="button" class="ps-btn ps-btn-secondary" onclick="psFecharModal()">Cancelar</button>
                            <button type="button" class="ps-btn ps-btn-primary" id="psBtnContinuar" onclick="psContinuarPasso1()"><i class="fas fa-signature"></i> <span id="psBtnContinuarLabel">Confirmar assinatura</span></button>
                        </div>
                    </div>

                    <!-- Passo 2: decisão -->
                    <div id="psPasso2" style="display:none;">
                        <div id="psRecibo" class="ps-alert ps-alert-info" style="display:none;"></div>

                        <div style="font-size:.68rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#3d5c46;margin-bottom:8px;">Decisão sobre o processo</div>

                        <button type="button" class="ps-decision selected" data-decisao="setor3_aprovado" onclick="psEscolherDecisao('setor3_aprovado', this)">
                            <span class="ps-method-ic" style="background:#e6f2ea;color:#14532d;"><i class="fas fa-circle-check"></i></span>
                            <span><span style="display:block;font-weight:700;font-size:.86rem;">Aprovar e retornar ao setor 2</span><span style="display:block;font-size:.77rem;color:#8fa399;">O setor 2 entrega o documento ao cidadão</span></span>
                        </button>
                        <button type="button" class="ps-decision" data-decisao="devolver_setor2" onclick="psEscolherDecisao('devolver_setor2', this)">
                            <span class="ps-method-ic" style="background:#fdf3e0;color:#b7791f;"><i class="fas fa-rotate-left"></i></span>
                            <span><span style="display:block;font-weight:700;font-size:.86rem;">Devolver para ajuste</span><span style="display:block;font-size:.77rem;color:#8fa399;">Volta como análise no setor 2 · motivo obrigatório</span></span>
                        </button>
                        <button type="button" class="ps-decision" data-decisao="setor3_recusado" onclick="psEscolherDecisao('setor3_recusado', this)">
                            <span class="ps-method-ic" style="background:#fce7e7;color:#b13232;"><i class="fas fa-circle-xmark"></i></span>
                            <span><span style="display:block;font-weight:700;font-size:.86rem;">Recusar o processo</span><span style="display:block;font-size:.77rem;color:#8fa399;">Retorno recusado ao setor 2 · motivo obrigatório</span></span>
                        </button>

                        <div id="psMotivoWrap" style="margin:14px 0;display:none;">
                            <label style="display:block;margin-bottom:5px;font-size:.78rem;font-weight:700;color:#3d5c46;">Motivo</label>
                            <textarea id="psMotivo" rows="3" placeholder="Explique o motivo…" style="width:100%;padding:10px;border:1px solid #e3e8e4;border-radius:8px;box-sizing:border-box;"></textarea>
                        </div>

                        <form id="psFormDecisao" method="POST" action="fluxo_setor_handler.php">
                            <input type="hidden" name="requerimento_id" value="<?= (int) $itemDetalhe['requerimento_id'] ?>">
                            <input type="hidden" name="fluxo_acao" id="psFluxoAcao" value="setor3_aprovado">
                            <input type="hidden" name="motivo" id="psMotivoHidden" value="">
                            <input type="hidden" name="referer" value="painel_secretario">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <div style="display:flex;gap:8px;justify-content:space-between;align-items:center;">
                                <button type="button" class="ps-btn ps-btn-secondary" onclick="psVoltarPasso1()"><i class="fas fa-arrow-left"></i> Voltar</button>
                                <button type="submit" class="ps-btn ps-btn-primary" id="psBtnConfirmarDecisao" onclick="return psConfirmarDecisao()"><i class="fas fa-paper-plane"></i> Confirmar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <script>
        const psCsrf = <?= json_encode($_SESSION['csrf_token']) ?>;
        const psDocumentoId = <?= json_encode($itemDetalhe['documento_id']) ?>;
        const psRequerimentoId = <?= (int) $itemDetalhe['requerimento_id'] ?>;
        let psMetodoAtual = 'pin';
        let psAssinaturaRegistrada = false;

        function psAbrirModal() { document.getElementById('psModalBackdrop').classList.add('open'); }
        function psFecharModal() { document.getElementById('psModalBackdrop').classList.remove('open'); }

        function psEscolherMetodo(metodo) {
            psMetodoAtual = metodo;
            document.querySelectorAll('.ps-method').forEach(b => b.classList.toggle('selected', b.dataset.metodo === metodo));
            document.getElementById('psPinWrap').style.display = metodo === 'pin' ? 'block' : 'none';
            document.getElementById('psGovAlert').style.display = metodo === 'gov' ? 'flex' : 'none';
            document.getElementById('psPrintAlert').style.display = metodo === 'print' ? 'flex' : 'none';
            document.getElementById('psBtnContinuarLabel').textContent = metodo === 'pin' ? 'Confirmar assinatura' : 'Continuar';
        }

        function psAbrirPdfEmNovaAba() {
            window.open('assinatura/redownload_pdf.php?id=' + encodeURIComponent(psDocumentoId), '_blank');
        }

        async function psContinuarPasso1() {
            const erroEl = document.getElementById('psErro1');
            erroEl.style.display = 'none';

            if (psMetodoAtual === 'pin') {
                const pin = document.getElementById('psPin').value;
                if (!pin) {
                    erroEl.textContent = 'Digite seu PIN de assinatura para confirmar.';
                    erroEl.style.display = 'block';
                    return;
                }
                const btn = document.getElementById('psBtnContinuar');
                btn.disabled = true;
                try {
                    const fd = new FormData();
                    fd.append('documento_id', psDocumentoId);
                    fd.append('requerimento_id', psRequerimentoId);
                    fd.append('pin_assinatura', pin);
                    fd.append('csrf_token', psCsrf);
                    const r = await fetch('assinatura/coassinar.php', { method: 'POST', body: fd, credentials: 'same-origin' });
                    const d = await r.json();
                    if (d.success) {
                        psAssinaturaRegistrada = true;
                        psMostrarRecibo('sucesso', 'Assinatura registrada', 'O documento foi atualizado com a sua assinatura.');
                        psIrPasso2();
                    } else {
                        erroEl.textContent = d.error || 'Não foi possível assinar. Confira o PIN e tente novamente.';
                        erroEl.style.display = 'block';
                    }
                } catch (e) {
                    erroEl.textContent = 'Falha de comunicação com o servidor.';
                    erroEl.style.display = 'block';
                } finally {
                    btn.disabled = false;
                }
                return;
            }

            if (psMetodoAtual === 'gov') {
                psAbrirPdfEmNovaAba();
                window.open('https://www.gov.br/governodigital/pt-br/assinatura-eletronica', '_blank');
                psAssinaturaRegistrada = false;
                psMostrarRecibo('info', 'PDF baixado e gov.br aberto', 'Assine por lá e depois registre a decisão abaixo (aprovar fica indisponível até haver uma assinatura sua registrada no sistema).');
                psIrPasso2();
                return;
            }

            if (psMetodoAtual === 'print') {
                psAbrirPdfEmNovaAba();
                psAssinaturaRegistrada = false;
                psMostrarRecibo('warn', 'PDF gerado para impressão', 'A assinatura em papel não fica registrada no sistema — aprovar exige assinatura digital.');
                psIrPasso2();
            }
        }

        function psMostrarRecibo(tipo, titulo, texto) {
            const el = document.getElementById('psRecibo');
            el.className = 'ps-alert ' + (tipo === 'warn' ? 'ps-alert-warn' : 'ps-alert-info');
            el.style.display = 'flex';
            el.innerHTML = '<i class="fas ' + (tipo === 'sucesso' ? 'fa-circle-check' : 'fa-circle-info') + '"></i> <div><strong>' + titulo + '</strong><div style="margin-top:2px;">' + texto + '</div></div>';
        }

        function psIrPasso2() {
            document.getElementById('psPasso1').style.display = 'none';
            document.getElementById('psPasso2').style.display = 'block';
            document.getElementById('psStepLabel1').style.color = '#8fa399';
            document.getElementById('psStepLabel2').style.color = '#14532d';
            document.getElementById('psStepLabel2').style.fontWeight = '700';

            const btnAprovar = document.querySelector('.ps-decision[data-decisao="setor3_aprovado"]');
            if (!psAssinaturaRegistrada) {
                btnAprovar.setAttribute('disabled', 'disabled');
                if (btnAprovar.classList.contains('selected')) {
                    psEscolherDecisao('devolver_setor2', document.querySelector('.ps-decision[data-decisao="devolver_setor2"]'));
                }
            } else {
                btnAprovar.removeAttribute('disabled');
            }
        }

        function psVoltarPasso1() {
            document.getElementById('psPasso2').style.display = 'none';
            document.getElementById('psPasso1').style.display = 'block';
            document.getElementById('psStepLabel1').style.color = '#14532d';
            document.getElementById('psStepLabel2').style.color = '#8fa399';
        }

        function psEscolherDecisao(acao, btn) {
            if (btn.hasAttribute('disabled')) return;
            document.querySelectorAll('.ps-decision').forEach(b => b.classList.remove('selected'));
            btn.classList.add('selected');
            document.getElementById('psFluxoAcao').value = acao;
            document.getElementById('psMotivoWrap').style.display = acao === 'setor3_aprovado' ? 'none' : 'block';
        }

        function psConfirmarDecisao() {
            const acao = document.getElementById('psFluxoAcao').value;
            const motivo = document.getElementById('psMotivo').value.trim();
            if (acao !== 'setor3_aprovado' && motivo.length < 5) {
                alert('Descreva o motivo (mínimo 5 caracteres).');
                return false;
            }
            document.getElementById('psMotivoHidden').value = motivo;
            return true;
        }
        </script>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
