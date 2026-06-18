<?php
require_once 'conexao.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/coassinatura_helper.php';
verificaLogin();

$requerimentoId = (int) ($_GET['requerimento_id'] ?? 0);
$documentoIdFoco = $_GET['documento_id'] ?? null;

if (!$requerimentoId) {
    header('Location: requerimentos.php');
    exit;
}

$nivelAtual = $_SESSION['admin_nivel'] ?? 'operador';
$isAdmin    = in_array($nivelAtual, ['admin', 'admin_geral'], true);
$isSetor3   = ($nivelAtual === 'secretario' || $isAdmin);
$isSetor2   = ($nivelAtual === 'fiscal' || $isAdmin);
$adminId    = $_SESSION['admin_id'];

// Buscar requerimento
$stmt = $pdo->prepare("
    SELECT r.id, r.protocolo, r.status, r.setor_atual, r.aguardando_acao, r.tipo_alvara,
           r.data_envio, req.nome AS requerente_nome, req.email AS requerente_email
    FROM requerimentos r
    JOIN requerentes req ON r.requerente_id = req.id
    WHERE r.id = ?
");
$stmt->execute([$requerimentoId]);
$requerimento = $stmt->fetch();

if (!$requerimento) {
    header('Location: requerimentos.php?error=nao_encontrado');
    exit;
}

// Buscar documentos (agrupados por grupo de assinaturas, ou por document_id individual)
$stmtDocs = $pdo->prepare("
    SELECT ad.documento_id, ad.nome_arquivo, ad.tipo_documento, ad.hash_documento,
           ad.timestamp_assinatura, ad.assinante_nome, ad.assinante_cargo,
           ad.group_id, ad.visivel_para,
           (SELECT COUNT(*) FROM assinaturas_digitais ad2
            WHERE (ad2.group_id = ad.group_id OR ad2.documento_id = ad.documento_id)
           ) AS total_assinaturas,
           (df.documento_id IS NOT NULL) AS tem_fonte,
           (SELECT COUNT(*) FROM assinaturas_digitais ad3
            WHERE ad3.documento_id = COALESCE(ad.group_id, ad.documento_id)
              AND ad3.assinante_id = ?
           ) AS ja_assinei
    FROM assinaturas_digitais ad
    LEFT JOIN documentos_fonte df ON df.documento_id = COALESCE(ad.group_id, ad.documento_id)
    WHERE ad.requerimento_id = ?
    GROUP BY COALESCE(ad.group_id, ad.documento_id)
    ORDER BY ad.timestamp_assinatura DESC
");
$stmtDocs->execute([$adminId, $requerimentoId]);
$documentos = $stmtDocs->fetchAll();

// Buscar todas as assinaturas por document/group para exibir no painel lateral
$stmtSigs = $pdo->prepare("
    SELECT ad.documento_id, ad.assinante_nome, ad.assinante_cargo, ad.assinante_cpf,
           ad.timestamp_assinatura, ad.group_id
    FROM assinaturas_digitais ad
    WHERE ad.requerimento_id = ?
    ORDER BY ad.timestamp_assinatura ASC
");
$stmtSigs->execute([$requerimentoId]);
$todasAssinaturas = $stmtSigs->fetchAll();

// Indexar assinaturas por group_id (ou documento_id se sem group)
$assinaturasPorGrupo = [];
foreach ($todasAssinaturas as $sig) {
    $chave = $sig['group_id'] ?: $sig['documento_id'];
    $assinaturasPorGrupo[$chave][] = $sig;
}

// Documento em foco
if (!$documentoIdFoco && !empty($documentos)) {
    $documentoIdFoco = $documentos[0]['documento_id'];
}

// Buscar solicitações de assinatura pendentes para o usuário atual
$stmtSolicit = $pdo->prepare("
    SELECT sa.*, a.nome AS solicitante_nome
    FROM solicitacoes_assinatura sa
    JOIN administradores a ON a.id = sa.solicitante_id
    WHERE sa.requerimento_id = ? AND sa.destinatario_id = ? AND sa.status = 'pendente'
");
$solicitacoesPendentes = [];
try {
    $stmtSolicit->execute([$requerimentoId, $adminId]);
    $solicitacoesPendentes = $stmtSolicit->fetchAll();
} catch (Throwable $e) {
    // tabela pode não existir ainda
}

// Buscar lista de admins ativos para solicitar assinatura
$stmtAdmins = $pdo->prepare("SELECT id, nome, nivel FROM administradores WHERE ativo = 1 AND id != ? ORDER BY nome");
$stmtAdmins->execute([$adminId]);
$admins = $stmtAdmins->fetchAll();

// Feedback de erro/sucesso
$pageError = '';
$pageSuccess = '';
if (isset($_GET['error'])) {
    $errorMsgs = [
        'dados_invalidos'       => 'Dados inválidos. Verifique e tente novamente.',
        'motivo_obrigatorio'    => 'O motivo é obrigatório para recusar.',
        'solicitacao_duplicada' => 'Já existe uma solicitação pendente para este documento e destinatário.',
        'erro_solicitacao'      => 'Erro ao processar solicitação' . (isset($_GET['details']) ? ': ' . htmlspecialchars(urldecode($_GET['details'])) : '.'),
        'erro_fluxo'            => 'Erro no fluxo' . (isset($_GET['details']) ? ': ' . htmlspecialchars(urldecode($_GET['details'])) : '.'),
    ];
    $pageError = $errorMsgs[$_GET['error']] ?? 'Ocorreu um erro inesperado.';
}
if (isset($_GET['success'])) {
    $successMsgs = [
        'solicitacao_enviada' => 'Solicitação de co-assinatura enviada com sucesso.',
        'fluxo_atualizado'    => 'Fluxo atualizado com sucesso.',
        'coassinado'          => 'Assinatura adicionada com sucesso.',
    ];
    $pageSuccess = $successMsgs[$_GET['success']] ?? 'Operação realizada com sucesso.';
}

include 'header.php';
?>

<style>
.doc-viewer-layout { display: flex; gap: 0; height: calc(100vh - 72px); overflow: hidden; }
.doc-sidebar { width: 340px; min-width: 280px; max-width: 400px; background: #fff; border-right: 1px solid #e5e7eb; display: flex; flex-direction: column; overflow: hidden; flex-shrink: 0; }
.doc-sidebar-header { padding: 1rem 1.25rem 0.75rem; border-bottom: 1px solid #e5e7eb; }
.doc-sidebar-body { flex: 1; overflow-y: auto; padding: 0.75rem; }
.doc-viewer-area { flex: 1; display: flex; flex-direction: column; overflow: hidden; background: #374151; }
.doc-viewer-toolbar { display: flex; align-items: center; justify-content: space-between; background: #1f2937; padding: 0.6rem 1rem; color: #f3f4f6; gap: 0.5rem; flex-shrink: 0; }
.doc-viewer-iframe { flex: 1; width: 100%; border: none; }

.doc-item { border-radius: 8px; padding: 0.65rem 0.85rem; margin-bottom: 0.4rem; cursor: pointer; border: 1.5px solid transparent; background: #f9fafb; transition: all .15s; }
.doc-item:hover { background: #f0f9ff; border-color: #bae6fd; }
.doc-item.active { background: #eff6ff; border-color: #93c5fd; }
.doc-item .doc-name { font-size: .8rem; font-weight: 600; color: #1e3a5f; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.doc-item .doc-meta { font-size: .72rem; color: #6b7280; margin-top: 2px; }

.sig-badge { display: flex; align-items: center; gap: 0.5rem; padding: 0.4rem 0.6rem; border-radius: 6px; background: #f0fdf4; border: 1px solid #bbf7d0; margin-bottom: 0.35rem; }
.sig-badge .sig-name { font-size: .78rem; font-weight: 600; color: #166534; }
.sig-badge .sig-role { font-size: .72rem; color: #4b7c5e; }
.sig-badge .sig-date { font-size: .7rem; color: #6b7280; margin-left: auto; white-space: nowrap; }

.solicit-badge { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.6rem; border-radius: 6px; background: #fff7ed; border: 1px solid #fed7aa; margin-bottom: 0.35rem; }
.solicit-badge .sol-text { font-size: .78rem; color: #92400e; font-weight: 500; }

.section-label { font-size: .7rem; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: .06em; margin-bottom: .4rem; margin-top: .75rem; }

/* FM modals (inline, sem Bootstrap) */
.fm-backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:9000; align-items:center; justify-content:center; }
.fm-backdrop.open { display:flex; }
.fm-box { background:#fff; border-radius:18px; max-width:430px; width:100%; margin:16px; box-shadow:0 20px 60px rgba(0,0,0,.18); animation:fmIn .2s ease; }
@keyframes fmIn { from { opacity:0; transform:translateY(14px) scale(.97); } to { opacity:1; transform:none; } }
.fm-header { display:flex; align-items:center; gap:12px; padding:18px 20px 0; }
.fm-icon { width:38px; height:38px; border-radius:10px; display:inline-flex; align-items:center; justify-content:center; font-size:1rem; flex-shrink:0; }
.fm-icon.verde   { background:#e6f2ea; color:#14532d; }
.fm-icon.amarelo { background:#fef3c7; color:#b7791f; }
.fm-icon.vermelho{ background:#fef2f2; color:#8f2222; }
.fm-icon.azul    { background:#eff6ff; color:#1d4ed8; }
.fm-header h3 { margin:0; font-size:.97rem; font-weight:800; color:#102117; }
.fm-body { padding:14px 20px 20px; }
.fm-box .fm-sub { margin:0 0 10px; color:#66756d; font-size:.83rem; line-height:1.55; }
.fm-box .fm-impact { background:#f7f9f7; border:1px solid #e3e8e4; border-radius:8px; padding:8px 12px; font-size:.78rem; color:#374151; margin-bottom:12px; }
.fm-box textarea { width:100%; padding:9px; border:1px solid #e3e8e4; border-radius:8px; font-size:.83rem; resize:vertical; margin-bottom:12px; outline:none; transition:border-color .15s; box-sizing:border-box; }
.fm-box textarea:focus { border-color:#14532d; }
.fm-box .fm-btns { display:flex; gap:8px; justify-content:flex-end; }
.fm-btn-cancel  { padding:7px 14px; border:1px solid #e3e8e4; border-radius:8px; background:#fff; color:#374151; font-size:.82rem; font-weight:600; cursor:pointer; }
.fm-btn-confirm { padding:7px 16px; border-radius:8px; background:#14532d; color:#fff; border:none; font-size:.82rem; font-weight:700; cursor:pointer; }
.fm-btn-warn    { background:#b7791f !important; }
.fm-btn-danger  { background:#8f2222 !important; }
</style>

<div class="doc-viewer-layout">

    <!-- Sidebar -->
    <div class="doc-sidebar">
        <div class="doc-sidebar-header">
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="visualizar_requerimento.php?id=<?= $requerimentoId ?>"
                   class="btn btn-sm btn-light px-2 py-1" style="font-size:.78rem;">
                    <i class="fas fa-arrow-left me-1"></i>Voltar
                </a>
                <div>
                    <div style="font-size:.82rem;font-weight:700;color:#1e3a5f;">
                        #<?= htmlspecialchars($requerimento['protocolo']) ?>
                    </div>
                    <div style="font-size:.72rem;color:#6b7280;"><?= htmlspecialchars($requerimento['requerente_nome']) ?></div>
                </div>
            </div>
        </div>

        <div class="doc-sidebar-body">

            <?php if (!empty($solicitacoesPendentes)): ?>
                <div class="section-label"><i class="fas fa-bell me-1"></i>Aguardando sua assinatura</div>
                <?php foreach ($solicitacoesPendentes as $sol): ?>
                    <div class="solicit-badge">
                        <i class="fas fa-file-signature" style="color:#f97316;font-size:.85rem;"></i>
                        <div>
                            <div class="sol-text">Solicitado por <?= htmlspecialchars($sol['solicitante_nome']) ?></div>
                            <div style="font-size:.7rem;color:#b45309;">Doc.: <?= htmlspecialchars(substr($sol['documento_id'], 0, 12)) ?>…</div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="section-label"><i class="fas fa-file-pdf me-1"></i>Documentos (<?= count($documentos) ?>)</div>

            <?php if (empty($documentos)): ?>
                <div class="text-center py-4 text-muted" style="font-size:.82rem;">
                    <i class="fas fa-folder-open fa-2x mb-2 d-block opacity-40"></i>
                    Nenhum documento assinado encontrado.
                </div>
            <?php else: ?>
                <?php foreach ($documentos as $doc):
                    $grupoChave = $doc['group_id'] ?: $doc['documento_id'];
                    $sigs = $assinaturasPorGrupo[$grupoChave] ?? [];
                    $isActive = ($doc['documento_id'] === $documentoIdFoco);
                ?>
                    <div class="doc-item <?= $isActive ? 'active' : '' ?>"
                         onclick="carregarDocumento(this, '<?= htmlspecialchars($doc['documento_id']) ?>')"
                         data-doc-id="<?= htmlspecialchars($doc['documento_id']) ?>"
                         data-group-id="<?= htmlspecialchars($doc['group_id'] ?? $doc['documento_id']) ?>">
                        <div class="d-flex align-items-start gap-2">
                            <i class="fas fa-file-pdf mt-1" style="color:#ef4444;font-size:.9rem;flex-shrink:0;"></i>
                            <div style="min-width:0;flex:1;">
                                <div class="doc-name"><?= htmlspecialchars($doc['nome_arquivo']) ?></div>
                                <div class="doc-meta">
                                    <?= date('d/m/Y H:i', strtotime($doc['timestamp_assinatura'])) ?>
                                    · <?= (int) $doc['total_assinaturas'] ?> ass.
                                </div>
                                <?php if (!empty($sigs)): ?>
                                    <div class="mt-1 d-flex flex-wrap gap-1">
                                        <?php foreach ($sigs as $s): ?>
                                            <span title="<?= htmlspecialchars($s['assinante_nome']) ?>"
                                                  style="display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:50%;background:#d1fae5;border:1.5px solid #6ee7b7;font-size:.6rem;font-weight:700;color:#065f46;">
                                                <?= mb_strtoupper(mb_substr($s['assinante_nome'], 0, 2)) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Painel de assinaturas (visível somente para o doc ativo, via JS) -->
                    <div class="doc-sigs-panel ps-2 pb-1" id="sigs-<?= htmlspecialchars($doc['documento_id']) ?>"
                         style="display:<?= $isActive ? 'block' : 'none' ?>;">
                        <?php foreach ($sigs as $sig): ?>
                            <div class="sig-badge">
                                <span style="width:28px;height:28px;border-radius:50%;background:#d1fae5;border:1.5px solid #6ee7b7;display:inline-flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:700;color:#065f46;flex-shrink:0;">
                                    <?= mb_strtoupper(mb_substr($sig['assinante_nome'], 0, 2)) ?>
                                </span>
                                <div style="min-width:0;">
                                    <div class="sig-name"><?= htmlspecialchars($sig['assinante_nome']) ?></div>
                                    <div class="sig-role"><?= htmlspecialchars($sig['assinante_cargo'] ?? '') ?></div>
                                </div>
                                <div class="sig-date"><?= date('d/m/Y', strtotime($sig['timestamp_assinatura'])) ?></div>
                            </div>
                        <?php endforeach; ?>

                        <?php
                        // Progresso de co-assinatura (pendentes / recusados)
                        $coStatus = statusAssinaturasDocumento($pdo, $doc['documento_id']);
                        if (!empty($coStatus['pendentes']) || !empty($coStatus['recusados'])): ?>
                            <div class="mt-2 pt-2" style="border-top:1px dashed #e2e8f0;">
                                <div style="font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:#94a3b8;margin-bottom:5px;">
                                    <?= (int) $coStatus['total_assinado'] ?> de <?= (int) $coStatus['total_esperado'] ?> assinaram
                                </div>
                                <?php foreach ($coStatus['pendentes'] as $pend): ?>
                                    <div class="d-flex align-items-center gap-2 mb-1" style="font-size:.74rem;color:#92722a;">
                                        <i class="fas fa-hourglass-half"></i>
                                        <span class="flex-grow-1">Aguardando <strong><?= htmlspecialchars($pend['nome']) ?></strong></span>
                                        <?php if ((int) $coStatus['solicitante_id'] === (int) $adminId): ?>
                                            <button type="button" class="btn btn-link p-0 text-danger" style="font-size:.72rem;"
                                                onclick="cancelarSolic('<?= htmlspecialchars($doc['documento_id']) ?>', <?= (int) $pend['destinatario_id'] ?>)">
                                                cancelar
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                <?php foreach ($coStatus['recusados'] as $rec): ?>
                                    <div class="mb-1" style="font-size:.74rem;color:#b91c1c;">
                                        <i class="fas fa-xmark me-1"></i>
                                        <strong><?= htmlspecialchars($rec['nome']) ?></strong> recusou<?= !empty($rec['motivo']) ? ' — ' . htmlspecialchars($rec['motivo']) : '' ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($doc['tem_fonte']): ?>
                            <?php if ($doc['ja_assinei']): ?>
                                <div class="mt-2 text-center" style="font-size:.75rem;color:#059669;">
                                    <i class="fas fa-check-circle me-1"></i>Você já assinou este documento
                                </div>
                            <?php else: ?>
                                <button type="button"
                                        class="btn btn-sm w-100 mt-2 fw-semibold"
                                        style="background:#059669;color:#fff;font-size:.78rem;"
                                        onclick="abrirCoAssinar('<?= htmlspecialchars($doc['documento_id']) ?>')">
                                    <i class="fas fa-file-signature me-1"></i>Adicionar minha assinatura
                                </button>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="mt-2 text-center text-muted" style="font-size:.72rem;">
                                <i class="fas fa-lock me-1"></i>Co-assinatura indisponível para documentos antigos
                            </div>
                        <?php endif; ?>

                        <button type="button"
                                class="btn btn-sm w-100 mt-1 btn-outline-secondary"
                                style="font-size:.76rem;"
                                onclick="abrirModalSolicitar('<?= htmlspecialchars($doc['documento_id']) ?>')">
                            <i class="fas fa-user-plus me-1"></i>Solicitar co-assinatura
                        </button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if ($isSetor3 && $requerimento['setor_atual'] === 'setor3'): ?>
                <div class="section-label mt-3"><i class="fas fa-tasks me-1"></i>Ações do Setor 3</div>

                <button type="button" class="btn btn-sm w-100 fw-semibold mb-1"
                        style="background:#0d9488;color:#fff;font-size:.8rem;"
                        onclick="abrirFM('fm-s3-aprovar')">
                    <i class="fas fa-check-double me-1"></i>Aprovar e retornar ao Setor 2
                </button>

                <button type="button" class="btn btn-sm w-100 fw-semibold mb-1"
                        style="background:#8f2222;color:#fff;font-size:.8rem;"
                        onclick="abrirFM('fm-s3-recusar')">
                    <i class="fas fa-times-circle me-1"></i>Recusar e retornar ao Setor 2
                </button>

                <button type="button" class="btn btn-sm w-100"
                        style="background:#b7791f;color:#fff;font-size:.8rem;"
                        onclick="abrirFM('fm-s3-sem-decisao')">
                    <i class="fas fa-rotate-left me-1"></i>Retornar sem decisão
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Viewer -->
    <div class="doc-viewer-area">
        <div class="doc-viewer-toolbar">
            <span id="viewer-title" style="font-size:.85rem;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:60%;">
                <i class="fas fa-file-pdf me-2" style="color:#ef4444;"></i>
                <?php
                $nomeFoco = '';
                foreach ($documentos as $d) {
                    if ($d['documento_id'] === $documentoIdFoco) { $nomeFoco = $d['nome_arquivo']; break; }
                }
                echo htmlspecialchars($nomeFoco ?: 'Selecione um documento');
                ?>
            </span>
            <div class="d-flex gap-2">
                <?php if ($documentoIdFoco): ?>
                    <a id="btn-download"
                       href="assinatura/redownload_pdf.php?id=<?= urlencode($documentoIdFoco) ?>"
                       class="btn btn-sm px-3"
                       style="background:#059669;color:#fff;font-size:.78rem;">
                        <i class="fas fa-download me-1"></i>Baixar
                    </a>
                    <a id="btn-print"
                       href="parecer_viewer.php?id=<?= urlencode($documentoIdFoco) ?>&autoprint=1"
                       target="_blank"
                       class="btn btn-sm px-3"
                       style="background:#1d4ed8;color:#fff;font-size:.78rem;">
                        <i class="fas fa-print me-1"></i>Imprimir
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($documentoIdFoco): ?>
            <iframe class="doc-viewer-iframe"
                    id="doc-viewer-iframe"
                    src="assinatura/redownload_pdf.php?id=<?= urlencode($documentoIdFoco) ?>&inline=1"
                    title="Visualizador de documento"></iframe>
        <?php else: ?>
            <div class="d-flex align-items-center justify-content-center h-100 text-white opacity-50">
                <div class="text-center">
                    <i class="fas fa-file-pdf fa-3x mb-3"></i>
                    <p>Nenhum documento disponível</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Toast de feedback -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:9999;">
    <div id="pageToast" class="toast align-items-center text-white border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body" id="pageToastMsg">Operação realizada com sucesso.</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<!-- FM Modal: Aprovar e retornar ao Setor 2 -->
<div class="fm-backdrop" id="fm-s3-aprovar">
  <div class="fm-box">
    <div class="fm-header">
      <div class="fm-icon verde"><i class="fas fa-check-double"></i></div>
      <h3>Aprovar e retornar ao Setor 2</h3>
    </div>
    <div class="fm-body">
      <p class="fm-sub">O Setor 2 receberá o processo de volta para enviar o documento final ao cidadão.</p>
      <div class="fm-impact">Exige pelo menos uma assinatura digital neste processo · Cidadão ainda não é notificado</div>
      <form method="post" action="fluxo_setor_handler.php">
        <input type="hidden" name="requerimento_id" value="<?= $requerimentoId ?>">
        <input type="hidden" name="fluxo_acao" value="setor3_aprovado">
        <input type="hidden" name="referer" value="visualizar_documento">
        <textarea name="motivo" rows="2" placeholder="Observação opcional..."></textarea>
        <div class="fm-btns">
          <button type="button" class="fm-btn-cancel" onclick="fecharFM('fm-s3-aprovar')">Cancelar</button>
          <button type="submit" class="fm-btn-confirm"><i class="fas fa-check-double me-1"></i>Confirmar aprovação</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- FM Modal: Recusar e retornar ao Setor 2 -->
<div class="fm-backdrop" id="fm-s3-recusar">
  <div class="fm-box">
    <div class="fm-header">
      <div class="fm-icon vermelho"><i class="fas fa-times-circle"></i></div>
      <h3>Recusar e retornar ao Setor 2</h3>
    </div>
    <div class="fm-body">
      <p class="fm-sub">O processo volta ao Setor 2 com o motivo da recusa registrado.</p>
      <div class="fm-impact">O motivo é obrigatório · Não exige assinatura · Setor 2 verá o motivo no histórico</div>
      <form method="post" action="fluxo_setor_handler.php">
        <input type="hidden" name="requerimento_id" value="<?= $requerimentoId ?>">
        <input type="hidden" name="fluxo_acao" value="setor3_recusado">
        <input type="hidden" name="referer" value="visualizar_documento">
        <textarea name="motivo" rows="3" placeholder="Descreva o motivo da recusa..." required></textarea>
        <div class="fm-btns">
          <button type="button" class="fm-btn-cancel" onclick="fecharFM('fm-s3-recusar')">Cancelar</button>
          <button type="submit" class="fm-btn-confirm fm-btn-danger"><i class="fas fa-times-circle me-1"></i>Confirmar recusa</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- FM Modal: Retornar sem decisão -->
<div class="fm-backdrop" id="fm-s3-sem-decisao">
  <div class="fm-box">
    <div class="fm-header">
      <div class="fm-icon amarelo"><i class="fas fa-rotate-left"></i></div>
      <h3>Retornar sem decisão</h3>
    </div>
    <div class="fm-body">
      <p class="fm-sub">O processo retorna ao Setor 2 sem aprovação nem recusa. Útil para ajustes intermediários.</p>
      <div class="fm-impact">Não exige assinatura · Histórico registrará "Retornou sem decisão" · Setor 2 retoma a análise</div>
      <form method="post" action="fluxo_setor_handler.php">
        <input type="hidden" name="requerimento_id" value="<?= $requerimentoId ?>">
        <input type="hidden" name="fluxo_acao" value="setor3_sem_decisao">
        <input type="hidden" name="referer" value="visualizar_documento">
        <textarea name="motivo" rows="2" placeholder="Observação opcional..."></textarea>
        <div class="fm-btns">
          <button type="button" class="fm-btn-cancel" onclick="fecharFM('fm-s3-sem-decisao')">Cancelar</button>
          <button type="submit" class="fm-btn-confirm fm-btn-warn"><i class="fas fa-rotate-left me-1"></i>Retornar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Solicitar Co-assinatura -->
<div class="modal fade" id="solicitarAssinaturaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <div class="d-flex align-items-center gap-2">
                    <span style="width:36px;height:36px;border-radius:10px;background:#eff6ff;border:1px solid #bfdbfe;display:inline-flex;align-items:center;justify-content:center;color:#1d4ed8;">
                        <i class="fas fa-user-plus"></i>
                    </span>
                    <h5 class="modal-title fw-bold mb-0" style="color:#1e3a5f;">Solicitar Co-assinatura</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="solicitar_assinatura_handler.php">
                <input type="hidden" name="requerimento_id" value="<?= $requerimentoId ?>">
                <input type="hidden" name="documento_id" id="modal-doc-id" value="">
                <div class="modal-body px-4 pt-3 pb-2">
                    <p class="text-muted small mb-3">O administrador selecionado receberá uma notificação para assinar este documento.</p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" style="font-size:.875rem;">
                            Administrador <span class="text-danger">*</span>
                        </label>
                        <select name="destinatario_id" class="form-select" required>
                            <option value="">— Selecione —</option>
                            <?php foreach ($admins as $adm): ?>
                                <option value="<?= $adm['id'] ?>"><?= htmlspecialchars($adm['nome']) ?> (<?= htmlspecialchars($adm['nivel']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold" style="font-size:.875rem;">
                            Mensagem <span class="text-muted fw-normal">(opcional)</span>
                        </label>
                        <textarea name="mensagem" rows="2" class="form-control"
                                  style="font-size:.875rem;resize:none;"
                                  placeholder="Orientação complementar para o assinante..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-2 gap-2">
                    <button type="button" class="btn btn-slate btn-sm px-3" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4" style="font-size:.875rem;">
                        <i class="fas fa-paper-plane me-2"></i>Enviar solicitação
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- FM Modal: Adicionar minha assinatura (co-assinatura) -->
<div class="fm-backdrop" id="fm-coassinar">
  <div class="fm-box">
    <div class="fm-header">
      <div class="fm-icon verde"><i class="fas fa-file-signature"></i></div>
      <h3>Adicionar minha assinatura</h3>
    </div>
    <div class="fm-body">
      <p class="fm-sub">Sua assinatura digital será adicionada ao documento. O PDF será regenerado com todas as assinaturas acumuladas. O conteúdo não será alterado.</p>
      <div class="fm-impact">Ação irreversível · Assinatura registrada com IP e timestamp</div>
      <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;font-size:.83rem;margin-bottom:14px;margin-top:10px;">
        <input type="checkbox" id="chk-coassinar" style="margin-top:3px;flex-shrink:0;">
        <span>Declaro que revisei o conteúdo deste documento e concordo em assiná-lo digitalmente em nome da SEMA.</span>
      </label>
      <div style="margin-bottom:12px;">
        <label style="font-size:.8rem;font-weight:700;display:block;margin-bottom:4px;">
          <i class="fas fa-key me-1"></i> PIN de assinatura
        </label>
        <input type="password" id="pin-coassinar" maxlength="64" autocomplete="off"
               placeholder="Seu PIN pessoal de assinatura"
               style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:8px;font-size:.85rem;">
        <small style="font-size:.7rem;color:#6b7280;">Protege sua chave criptográfica individual (assinatura avançada). Primeira vez? Configure o PIN ao assinar um documento no editor.</small>
      </div>
      <div id="coassinar-error" style="display:none;color:#8f2222;font-size:.8rem;margin-bottom:8px;"></div>
      <div class="fm-btns">
        <button type="button" class="fm-btn-cancel" onclick="fecharFM('fm-coassinar')">Cancelar</button>
        <button type="button" class="fm-btn-confirm" id="btn-confirmar-coassinar" onclick="confirmarCoAssinar()">
          <i class="fas fa-file-signature me-1"></i>Assinar
        </button>
      </div>
    </div>
  </div>
</div>

<script>
let _coAssinarDocId = '';

function abrirCoAssinar(docId) {
    _coAssinarDocId = docId;
    document.getElementById('chk-coassinar').checked = false;
    document.getElementById('coassinar-error').style.display = 'none';
    const btn = document.getElementById('btn-confirmar-coassinar');
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-file-signature me-1"></i>Assinar';
    abrirFM('fm-coassinar');
}

function confirmarCoAssinar() {
    if (!document.getElementById('chk-coassinar').checked) {
        const err = document.getElementById('coassinar-error');
        err.textContent = 'Marque a declaração antes de assinar.';
        err.style.display = 'block';
        return;
    }
    const pinCoassinar = document.getElementById('pin-coassinar').value;
    if (!pinCoassinar) {
        const err = document.getElementById('coassinar-error');
        err.textContent = 'Digite seu PIN de assinatura.';
        err.style.display = 'block';
        return;
    }
    const btn = document.getElementById('btn-confirmar-coassinar');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Assinando...';

    const fd = new FormData();
    fd.append('documento_id', _coAssinarDocId);
    fd.append('requerimento_id', '<?= $requerimentoId ?>');
    fd.append('pin_assinatura', pinCoassinar);

    fetch('assinatura/coassinar.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                fecharFM('fm-coassinar');
                showToast('Assinatura adicionada com sucesso! Recarregando...', 'success');
                setTimeout(() => location.reload(), 1800);
            } else {
                const err = document.getElementById('coassinar-error');
                err.textContent = data.error || 'Erro ao assinar.';
                err.style.display = 'block';
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-file-signature me-1"></i>Assinar';
            }
        })
        .catch(() => {
            const err = document.getElementById('coassinar-error');
            err.textContent = 'Falha de comunicação com o servidor.';
            err.style.display = 'block';
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-file-signature me-1"></i>Assinar';
        });
}

function cancelarSolic(docId, destinatarioId) {
    if (!confirm('Cancelar esta solicitação de assinatura?')) return;
    const fd = new FormData();
    fd.append('documento_id', docId);
    fd.append('destinatario_id', destinatarioId);
    fetch('assinatura/cancelar_solicitacao.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) { showToast('Solicitação cancelada.', 'success'); setTimeout(() => location.reload(), 1200); }
            else { showToast(d.error || 'Erro ao cancelar.', 'error'); }
        })
        .catch(() => showToast('Falha de comunicação.', 'error'));
}

function carregarDocumento(el, docId) {
    document.querySelectorAll('.doc-item').forEach(d => d.classList.remove('active'));
    el.classList.add('active');

    document.querySelectorAll('.doc-sigs-panel').forEach(p => p.style.display = 'none');
    const sigsPanel = document.getElementById('sigs-' + docId);
    if (sigsPanel) sigsPanel.style.display = 'block';

    const iframe = document.getElementById('doc-viewer-iframe');
    if (iframe) {
        iframe.src = 'assinatura/redownload_pdf.php?id=' + encodeURIComponent(docId) + '&inline=1';
    }

    const nome = el.querySelector('.doc-name')?.textContent ?? '';
    document.getElementById('viewer-title').innerHTML = '<i class="fas fa-file-pdf me-2" style="color:#ef4444;"></i>' + nome;

    const btnDownload = document.getElementById('btn-download');
    const btnPrint = document.getElementById('btn-print');
    if (btnDownload) btnDownload.href = 'assinatura/redownload_pdf.php?id=' + encodeURIComponent(docId);
    if (btnPrint) btnPrint.href = 'parecer_viewer.php?id=' + encodeURIComponent(docId) + '&autoprint=1';
}

function abrirModalSolicitar(docId) {
    document.getElementById('modal-doc-id').value = docId;
    new bootstrap.Modal(document.getElementById('solicitarAssinaturaModal')).show();
}

function abrirFM(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.add('open');
    const ta = el.querySelector('textarea');
    if (ta) setTimeout(() => ta.focus(), 220);
}

function fecharFM(id) {
    const el = document.getElementById(id);
    if (!el) return;
    const box = el.querySelector('.fm-box');
    if (box) {
        box.style.transition = 'opacity .18s ease, transform .18s ease';
        box.style.opacity = '0';
        box.style.transform = 'translateY(14px) scale(.97)';
    }
    el.style.transition = 'background .18s ease';
    el.style.background = 'rgba(0,0,0,0)';
    setTimeout(() => {
        el.classList.remove('open');
        if (box) { box.style.transition = ''; box.style.opacity = ''; box.style.transform = ''; }
        el.style.transition = ''; el.style.background = '';
    }, 190);
}

// Fechar FM ao clicar no backdrop
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.fm-backdrop').forEach(function(el) {
        el.addEventListener('click', function(e) {
            if (e.target === el) fecharFM(el.id);
        });
    });

    // Toast de feedback (erro/sucesso vindos da URL)
    <?php if ($pageError): ?>
    showToast(<?= json_encode($pageError) ?>, 'error');
    <?php elseif ($pageSuccess): ?>
    showToast(<?= json_encode($pageSuccess) ?>, 'success');
    <?php endif; ?>
});

function showToast(msg, type) {
    const toastEl = document.getElementById('pageToast');
    const msgEl   = document.getElementById('pageToastMsg');
    if (!toastEl || !msgEl) return;
    msgEl.textContent = msg;
    toastEl.classList.remove('bg-success', 'bg-danger', 'bg-warning');
    toastEl.classList.add(type === 'error' ? 'bg-danger' : type === 'warning' ? 'bg-warning' : 'bg-success');
    if (typeof bootstrap !== 'undefined') {
        new bootstrap.Toast(toastEl, { delay: 6000 }).show();
    }
}
</script>

<?php include 'footer.php'; ?>
