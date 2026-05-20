<?php
require_once 'conexao.php';
require_once __DIR__ . '/../includes/functions.php';
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

// Buscar requerimento
$stmt = $pdo->prepare("
    SELECT r.id, r.protocolo, r.status, r.setor_atual, r.aguardando_acao, r.tipo_alvara,
           r.data_criacao, req.nome AS requerente_nome, req.email AS requerente_email
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
           ) AS total_assinaturas
    FROM assinaturas_digitais ad
    WHERE ad.requerimento_id = ?
    GROUP BY COALESCE(ad.group_id, ad.documento_id)
    ORDER BY ad.timestamp_assinatura DESC
");
$stmtDocs->execute([$requerimentoId]);
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
$adminId = $_SESSION['admin_id'];
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
$admins = $pdo->query("SELECT id, nome, nivel FROM administradores WHERE ativo = 1 AND id != $adminId ORDER BY nome")->fetchAll();

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

                        <?php if ($isSetor3 && $requerimento['setor_atual'] === 'setor3'): ?>
                            <a href="assinatura/assinatura_modal.php?requerimento_id=<?= $requerimentoId ?>&documento_id=<?= urlencode($doc['documento_id']) ?>"
                               class="btn btn-sm w-100 mt-2 fw-semibold"
                               style="background:#059669;color:#fff;font-size:.78rem;">
                                <i class="fas fa-file-signature me-1"></i>Assinar este documento
                            </a>
                        <?php endif; ?>

                        <?php if ($isSetor2 || $isSetor3): ?>
                            <button type="button"
                                    class="btn btn-sm w-100 mt-1 btn-outline-secondary"
                                    style="font-size:.76rem;"
                                    onclick="abrirModalSolicitar('<?= htmlspecialchars($doc['documento_id']) ?>')">
                                <i class="fas fa-user-plus me-1"></i>Solicitar co-assinatura
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if ($isSetor3 && $requerimento['setor_atual'] === 'setor3'): ?>
                <div class="section-label mt-3"><i class="fas fa-tasks me-1"></i>Ações do Setor 3</div>

                <form method="post" action="fluxo_setor_handler.php">
                    <input type="hidden" name="requerimento_id" value="<?= $requerimentoId ?>">
                    <input type="hidden" name="fluxo_acao" value="setor3_aprovado">
                    <button type="submit" class="btn btn-sm w-100 fw-semibold mb-2"
                            style="background:#0d9488;color:#fff;font-size:.8rem;"
                            onclick="return confirm('Confirmar aprovação? O processo retornará ao Setor 2 para envio ao cidadão.')">
                        <i class="fas fa-check-double me-1"></i>Aprovar e retornar ao Setor 2
                    </button>
                </form>

                <button type="button"
                        class="btn btn-sm btn-outline-danger w-100"
                        style="font-size:.8rem;"
                        onclick="document.getElementById('devolverSetor2Panel').style.display='block'">
                    <i class="fas fa-reply me-1"></i>Devolver ao Setor 2 com motivo
                </button>

                <div id="devolverSetor2Panel" style="display:none;" class="mt-2">
                    <form method="post" action="fluxo_setor_handler.php">
                        <input type="hidden" name="requerimento_id" value="<?= $requerimentoId ?>">
                        <input type="hidden" name="fluxo_acao" value="devolver_setor2">
                        <textarea name="motivo" rows="3" required
                                  class="form-control form-control-sm mb-2"
                                  placeholder="Descreva o motivo da devolução..."></textarea>
                        <button type="submit" class="btn btn-danger btn-sm w-100" style="font-size:.78rem;">
                            <i class="fas fa-paper-plane me-1"></i>Confirmar devolução
                        </button>
                    </form>
                </div>
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

<script>
function carregarDocumento(el, docId) {
    // Atualiza item ativo na sidebar
    document.querySelectorAll('.doc-item').forEach(d => d.classList.remove('active'));
    el.classList.add('active');

    // Oculta todos os painéis de assinatura e mostra o correto
    document.querySelectorAll('.doc-sigs-panel').forEach(p => p.style.display = 'none');
    const sigsPanel = document.getElementById('sigs-' + docId);
    if (sigsPanel) sigsPanel.style.display = 'block';

    // Atualiza iframe
    const iframe = document.getElementById('doc-viewer-iframe');
    if (iframe) {
        iframe.src = 'assinatura/redownload_pdf.php?id=' + encodeURIComponent(docId) + '&inline=1';
    }

    // Atualiza title + botões
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
</script>

<?php include 'footer.php'; ?>
