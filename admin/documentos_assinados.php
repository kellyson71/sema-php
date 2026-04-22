<?php
require_once 'conexao.php';
verificaLogin();

$itensPorPagina = 25;
$paginaAtual = max(1, (int) ($_GET['pagina'] ?? 1));
$offset = ($paginaAtual - 1) * $itensPorPagina;

$filtroBusca = trim($_GET['busca'] ?? '');
$filtroAssinante = trim($_GET['assinante'] ?? '');

$sql = "SELECT ad.*, r.protocolo AS requerimento_protocolo, req.nome AS requerente_nome
        FROM assinaturas_digitais ad
        LEFT JOIN requerimentos r ON ad.requerimento_id = r.id
        LEFT JOIN requerentes req ON r.requerente_id = req.id
        WHERE 1=1";

$sqlCount = "SELECT COUNT(*) AS total
             FROM assinaturas_digitais ad
             LEFT JOIN requerimentos r ON ad.requerimento_id = r.id
             LEFT JOIN requerentes req ON r.requerente_id = req.id
             WHERE 1=1";

$params = [];
$paramsCount = [];

if ($filtroBusca !== '') {
    $sql .= " AND (ad.documento_id LIKE ? OR ad.assinante_nome LIKE ? OR r.protocolo LIKE ? OR ad.nome_arquivo LIKE ? OR req.nome LIKE ?)";
    $sqlCount .= " AND (ad.documento_id LIKE ? OR ad.assinante_nome LIKE ? OR r.protocolo LIKE ? OR ad.nome_arquivo LIKE ? OR req.nome LIKE ?)";
    $termoBusca = '%' . $filtroBusca . '%';
    for ($i = 0; $i < 5; $i++) {
        $params[] = $termoBusca;
        $paramsCount[] = $termoBusca;
    }
}

if ($filtroAssinante !== '') {
    $sql .= " AND ad.assinante_nome LIKE ?";
    $sqlCount .= " AND ad.assinante_nome LIKE ?";
    $termoAssinante = '%' . $filtroAssinante . '%';
    $params[] = $termoAssinante;
    $paramsCount[] = $termoAssinante;
}

$sql .= " ORDER BY ad.timestamp_assinatura DESC LIMIT {$itensPorPagina} OFFSET {$offset}";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$documentos = $stmt->fetchAll();

$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($paramsCount);
$totalDocumentos = (int) ($stmtCount->fetch()['total'] ?? 0);
$totalPaginas = max(1, (int) ceil($totalDocumentos / $itensPorPagina));

$stmtAssinantes = $pdo->query("SELECT DISTINCT assinante_nome FROM assinaturas_digitais ORDER BY assinante_nome");
$listaAssinantes = $stmtAssinantes->fetchAll();

$assinadosHoje = (int) $pdo->query("SELECT COUNT(*) FROM assinaturas_digitais WHERE DATE(timestamp_assinatura) = CURDATE()")->fetchColumn();
$comRequerimento = (int) $pdo->query("SELECT COUNT(*) FROM assinaturas_digitais WHERE requerimento_id IS NOT NULL")->fetchColumn();
$porDesenho = (int) $pdo->query("SELECT COUNT(*) FROM assinaturas_digitais WHERE tipo_assinatura = 'desenho'")->fetchColumn();

function buildSignedDocsUrl(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            unset($params[$key]);
        }
    }
    return 'documentos_assinados.php' . ($params ? '?' . http_build_query($params) : '');
}

include 'header.php';
?>
<style>
    .signed-shell { max-width: 1240px; margin: 0 auto; display: flex; flex-direction: column; gap: 18px; }
    .signed-metric-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; }
    .signed-metric-card, .signed-block { background: #fff; border: 1px solid var(--line); border-radius: 20px; box-shadow: var(--card-shadow); }
    .signed-metric-card { padding: 22px; }
    .signed-metric-label { display: block; margin-bottom: 6px; font-size: .76rem; color: var(--muted); font-weight: 700; text-transform: uppercase; letter-spacing: .08em; }
    .signed-metric-value { display: block; margin-bottom: 6px; font-size: 1.9rem; font-weight: 800; color: var(--ink); line-height: 1; }
    .signed-metric-note { display: flex; align-items: center; gap: 8px; color: var(--muted); font-size: .82rem; }
    .signed-filter-bar { padding: 18px; border-bottom: 1px solid var(--line); }
    .signed-filter-form { display: flex; align-items: end; gap: 10px; flex-wrap: wrap; }
    .signed-field { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
    .signed-field label { color: var(--muted); font-size: .78rem; font-weight: 700; }
    .signed-field input, .signed-field select {
        min-height: 42px; padding: 0 14px; border: 1px solid var(--line); border-radius: 14px; background: #fff; color: var(--ink); font-size: .9rem;
    }
    .signed-field.wide { flex: 1 1 280px; }
    .signed-field.compact { width: 260px; }
    .signed-actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .signed-list { display: flex; flex-direction: column; gap: 0; }
    .signed-item { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(0, .9fr) minmax(0, .9fr) auto; gap: 16px; align-items: center; padding: 18px; border-bottom: 1px solid #edf2ee; }
    .signed-item:hover { background: #fafcfb; }
    .signed-item:last-child { border-bottom: 0; }
    .signed-main, .signed-meta { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
    .signed-title { font-size: .95rem; font-weight: 800; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .signed-sub { font-size: .8rem; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .signed-protocol { display: inline-flex; align-items: center; gap: 6px; min-height: 26px; padding: 0 10px; border-radius: 999px; background: var(--primary-soft); color: var(--primary-strong); font-size: .72rem; font-weight: 800; width: fit-content; }
    .signed-badges { display: flex; gap: 8px; flex-wrap: wrap; }
    .signed-actions-row { display: flex; align-items: center; gap: 10px; justify-content: flex-end; flex-wrap: wrap; }
    .signed-empty { padding: 48px 24px; text-align: center; color: var(--muted); }
    .signed-empty i { display: block; margin-bottom: 12px; font-size: 2.5rem; color: #c4d0c8; }
    .signed-pagination { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 18px; border-top: 1px solid var(--line); }
    .signed-pagination-copy { color: var(--muted); font-size: .82rem; }
    .signed-pagination-links { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .signed-page-link { min-width: 38px; height: 38px; padding: 0 12px; border: 1px solid var(--line); border-radius: 12px; background: #fff; color: var(--ink); display: inline-flex; align-items: center; justify-content: center; font-size: .82rem; font-weight: 700; }
    .signed-page-link.active { background: var(--primary); border-color: var(--primary); color: #fff; }
    @media (max-width: 1100px) { .signed-metric-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .signed-item { grid-template-columns: 1fr; align-items: start; } .signed-actions-row { justify-content: flex-start; } }
    @media (max-width: 767px) { .signed-metric-grid { grid-template-columns: 1fr; } .signed-filter-form, .signed-pagination { flex-direction: column; align-items: stretch; } .signed-field.compact, .signed-field.wide { width: 100%; flex: 1 1 100%; } }
</style>

<div class="admin-page-shell signed-shell">
    <section class="page-hero page-hero-compact">
        <div class="page-hero-copy">
            <h1 class="page-title">Documentos Assinados</h1>
            <p class="page-subtitle">Acervo de documentos com assinatura digital, consulta rápida e validação pública.</p>
        </div>
    </section>

    <section class="signed-metric-grid">
        <article class="signed-metric-card">
            <span class="signed-metric-label">Total</span>
            <strong class="signed-metric-value"><?= number_format($totalDocumentos) ?></strong>
            <span class="signed-metric-note"><i class="fas fa-file-signature"></i> documentos listados</span>
        </article>
        <article class="signed-metric-card">
            <span class="signed-metric-label">Hoje</span>
            <strong class="signed-metric-value"><?= number_format($assinadosHoje) ?></strong>
            <span class="signed-metric-note"><i class="fas fa-calendar-day"></i> assinaturas no dia</span>
        </article>
        <article class="signed-metric-card">
            <span class="signed-metric-label">Com Protocolo</span>
            <strong class="signed-metric-value"><?= number_format($comRequerimento) ?></strong>
            <span class="signed-metric-note"><i class="fas fa-barcode"></i> vinculados a requerimento</span>
        </article>
        <article class="signed-metric-card">
            <span class="signed-metric-label">Assinatura Desenho</span>
            <strong class="signed-metric-value"><?= number_format($porDesenho) ?></strong>
            <span class="signed-metric-note"><i class="fas fa-pen"></i> desenho manuscrito</span>
        </article>
    </section>

    <section class="signed-block">
        <div class="signed-filter-bar">
            <form method="GET" class="signed-filter-form">
                <div class="signed-field wide">
                    <label for="busca">Buscar</label>
                    <input type="text" name="busca" id="busca" value="<?= htmlspecialchars($filtroBusca) ?>" placeholder="Documento, protocolo, arquivo, requerente ou assinante">
                </div>
                <div class="signed-field compact">
                    <label for="assinante">Assinante</label>
                    <select name="assinante" id="assinante">
                        <option value="">Todos</option>
                        <?php foreach ($listaAssinantes as $assinante): ?>
                            <option value="<?= htmlspecialchars($assinante['assinante_nome']) ?>" <?= $filtroAssinante === $assinante['assinante_nome'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($assinante['assinante_nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="signed-actions">
                    <button type="submit" class="toolbar-button toolbar-button-primary"><i class="fas fa-filter"></i> Filtrar</button>
                    <a href="documentos_assinados.php" class="toolbar-button">Limpar</a>
                </div>
            </form>
        </div>

        <?php if ($documentos): ?>
            <div class="signed-list">
                <?php foreach ($documentos as $doc): ?>
                    <?php
                    $caminhoArquivo = $doc['caminho_arquivo'];
                    $documentoApagado = empty($caminhoArquivo) || !file_exists($caminhoArquivo);
                    $tipoDocumento = ucfirst($doc['tipo_documento'] ?? 'parecer');
                    ?>
                    <article class="signed-item">
                        <div class="signed-main">
                            <?php if (!empty($doc['requerimento_protocolo'])): ?>
                                <a href="visualizar_requerimento.php?id=<?= (int) $doc['requerimento_id'] ?>" class="signed-protocol">
                                    <i class="fas fa-barcode"></i><?= htmlspecialchars($doc['requerimento_protocolo']) ?>
                                </a>
                            <?php endif; ?>
                            <div class="signed-title" title="<?= htmlspecialchars($doc['nome_arquivo'] ?? $doc['documento_id']) ?>">
                                <?= htmlspecialchars($doc['nome_arquivo'] ?: $doc['documento_id']) ?>
                            </div>
                            <div class="signed-sub"><?= htmlspecialchars($doc['requerente_nome'] ?: 'Sem requerente vinculado') ?></div>
                        </div>

                        <div class="signed-meta">
                            <div class="signed-title"><?= htmlspecialchars($doc['assinante_nome']) ?></div>
                            <div class="signed-sub">
                                <?php if (!empty($doc['assinante_cargo'])): ?>
                                    <?= htmlspecialchars($doc['assinante_cargo']) ?>
                                <?php elseif (!empty($doc['assinante_cpf'])): ?>
                                    CPF <?= htmlspecialchars($doc['assinante_cpf']) ?>
                                <?php else: ?>
                                    Assinante registrado
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="signed-meta">
                            <div class="signed-title"><?= formataData($doc['timestamp_assinatura']) ?></div>
                            <div class="signed-badges">
                                <span class="badge badge-status <?= $doc['tipo_assinatura'] === 'desenho' ? 'status-aprovado' : 'status-em-analise' ?>"><?= $doc['tipo_assinatura'] === 'desenho' ? 'Desenho' : 'Texto' ?></span>
                                <span class="badge badge-status status-pendente"><?= htmlspecialchars($tipoDocumento) ?></span>
                                <?php if ($documentoApagado): ?>
                                    <span class="badge badge-status status-indeferido">Arquivo ausente</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="signed-actions-row">
                            <?php if ($documentoApagado): ?>
                                <span class="toolbar-button" style="opacity:.55;cursor:not-allowed;"><i class="fas fa-eye-slash"></i> Indisponível</span>
                            <?php else: ?>
                                <a href="parecer_viewer.php?id=<?= htmlspecialchars($doc['documento_id']) ?>" target="_blank" class="toolbar-button toolbar-button-primary">
                                    <i class="fas fa-eye"></i> Ver / Baixar
                                </a>
                            <?php endif; ?>
                            <a href="../consultar/verificar.php?id=<?= htmlspecialchars($doc['documento_id']) ?>" target="_blank" class="toolbar-button">
                                <i class="fas fa-shield-halved"></i> Validar
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPaginas > 1): ?>
                <div class="signed-pagination">
                    <div class="signed-pagination-copy">
                        Página <?= $paginaAtual ?> de <?= $totalPaginas ?> · <?= $totalDocumentos ?> documento(s)
                    </div>
                    <div class="signed-pagination-links">
                        <?php if ($paginaAtual > 1): ?>
                            <a href="<?= htmlspecialchars(buildSignedDocsUrl(['pagina' => 1])) ?>" class="signed-page-link">«</a>
                            <a href="<?= htmlspecialchars(buildSignedDocsUrl(['pagina' => $paginaAtual - 1])) ?>" class="signed-page-link">‹</a>
                        <?php endif; ?>
                        <?php
                        $start = max(1, $paginaAtual - 2);
                        $end = min($totalPaginas, $paginaAtual + 2);
                        for ($i = $start; $i <= $end; $i++):
                        ?>
                            <a href="<?= htmlspecialchars(buildSignedDocsUrl(['pagina' => $i])) ?>" class="signed-page-link <?= $i === $paginaAtual ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                        <?php if ($paginaAtual < $totalPaginas): ?>
                            <a href="<?= htmlspecialchars(buildSignedDocsUrl(['pagina' => $paginaAtual + 1])) ?>" class="signed-page-link">›</a>
                            <a href="<?= htmlspecialchars(buildSignedDocsUrl(['pagina' => $totalPaginas])) ?>" class="signed-page-link">»</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="signed-empty">
                <i class="fas fa-file-signature"></i>
                <p class="mb-0">Nenhum documento encontrado para os filtros atuais.</p>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php include 'footer.php'; ?>
