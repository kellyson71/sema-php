<?php
require_once 'conexao.php';
verificaLogin();

$itensPorPagina = 25;
$paginaAtual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($paginaAtual - 1) * $itensPorPagina;

$filtroBusca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
$filtroAssinante = isset($_GET['assinante']) ? trim($_GET['assinante']) : '';

$sql = "SELECT ad.*, r.protocolo as requerimento_protocolo, req.nome as requerente_nome
        FROM assinaturas_digitais ad
        LEFT JOIN requerimentos r ON ad.requerimento_id = r.id
        LEFT JOIN requerentes req ON r.requerente_id = req.id
        WHERE 1=1";

$sqlCount = "SELECT COUNT(*) as total
             FROM assinaturas_digitais ad
             LEFT JOIN requerimentos r ON ad.requerimento_id = r.id
             LEFT JOIN requerentes req ON r.requerente_id = req.id
             WHERE 1=1";

$params = [];
$paramsCount = [];

if (!empty($filtroBusca)) {
    $sql .= " AND (ad.documento_id LIKE ? OR ad.assinante_nome LIKE ? OR r.protocolo LIKE ? OR ad.nome_arquivo LIKE ? OR req.nome LIKE ?)";
    $sqlCount .= " AND (ad.documento_id LIKE ? OR ad.assinante_nome LIKE ? OR r.protocolo LIKE ? OR ad.nome_arquivo LIKE ? OR req.nome LIKE ?)";
    $termoBusca = "%$filtroBusca%";
    $params[] = $termoBusca;
    $params[] = $termoBusca;
    $params[] = $termoBusca;
    $params[] = $termoBusca;
    $params[] = $termoBusca;
    $paramsCount[] = $termoBusca;
    $paramsCount[] = $termoBusca;
    $paramsCount[] = $termoBusca;
    $paramsCount[] = $termoBusca;
    $paramsCount[] = $termoBusca;
}

if (!empty($filtroAssinante)) {
    $sql .= " AND ad.assinante_nome LIKE ?";
    $sqlCount .= " AND ad.assinante_nome LIKE ?";
    $termoAssinante = "%$filtroAssinante%";
    $params[] = $termoAssinante;
    $paramsCount[] = $termoAssinante;
}

$sql .= " ORDER BY ad.timestamp_assinatura DESC LIMIT $offset, $itensPorPagina";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$documentos = $stmt->fetchAll();

$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($paramsCount);
$totalDocumentos = $stmtCount->fetch()['total'];

$totalPaginas = ceil($totalDocumentos / $itensPorPagina);

$stmtAssinantes = $pdo->query("SELECT DISTINCT assinante_nome FROM assinaturas_digitais ORDER BY assinante_nome");
$listaAssinantes = $stmtAssinantes->fetchAll();

include 'header.php';
?>

<style>
    .documentos-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }

    .document-card {
        border: 1px solid #e0e0e0;
        border-radius: 12px;
        padding: 18px;
        background: #fff;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .document-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        transform: translateY(-3px);
        border-color: var(--primary-color);
    }

    .document-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 12px;
        padding-bottom: 12px;
        border-bottom: 2px solid #f5f5f5;
        flex-wrap: wrap;
        gap: 8px;
    }

    .document-id {
        font-family: 'Courier New', monospace;
        font-size: 0.75rem;
        color: #666;
        background: #f8f9fa;
        padding: 4px 8px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .document-badge {
        font-size: 0.7rem;
        padding: 4px 10px;
        border-radius: 20px;
        font-weight: 600;
        white-space: nowrap;
    }

    .info-grid {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-bottom: 12px;
        flex: 1;
    }

    .info-item {
        display: flex;
        flex-direction: column;
    }

    .info-label {
        font-size: 0.7rem;
        color: #666;
        text-transform: uppercase;
        font-weight: 600;
        margin-bottom: 3px;
        letter-spacing: 0.5px;
    }

    .info-value {
        font-size: 0.85rem;
        color: #333;
        font-weight: 500;
        line-height: 1.4;
    }

    .info-value strong {
        color: var(--primary-color);
    }

    .protocolo-badge {
        background: var(--primary-color);
        color: white;
        padding: 6px 12px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.8rem;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
    }

    .assinante-info {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px;
        background: #f8f9fa;
        border-radius: 8px;
        margin-bottom: 12px;
        border: 1px solid #e9ecef;
    }

    .assinante-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--primary-color);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
        font-size: 1rem;
        flex-shrink: 0;
    }

    .assinante-details {
        flex: 1;
        min-width: 0;
    }

    .assinante-nome {
        font-weight: 600;
        color: #333;
        font-size: 0.9rem;
        margin-bottom: 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .assinante-meta {
        font-size: 0.75rem;
        color: #666;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .document-actions {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin-top: auto;
        padding-top: 12px;
        border-top: 1px solid #eee;
    }

    .btn-action {
        padding: 8px 12px;
        border-radius: 8px;
        font-weight: 500;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        transition: all 0.2s;
        width: 100%;
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #999;
    }

    .empty-state i {
        font-size: 4rem;
        margin-bottom: 20px;
        color: #ddd;
    }

    .stats-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px;
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        color: white;
        border-radius: 12px 12px 0 0;
    }

    .stats-header h5 {
        margin: 0;
        font-weight: 600;
    }

    .badge-white {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        padding: 8px 15px;
        border-radius: 20px;
        font-weight: 600;
    }

    @media (max-width: 1200px) {
        .documentos-grid {
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        }
    }

    @media (max-width: 768px) {
        .documentos-grid {
            grid-template-columns: 1fr;
        }

        .document-header {
            flex-direction: column;
            gap: 10px;
        }

        .stats-header {
            flex-direction: column;
            gap: 10px;
            text-align: center;
        }

        .protocolo-badge {
            width: 100%;
            justify-content: center;
        }
    }

    @media (min-width: 1400px) {
        .documentos-grid {
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
        }
    }
</style>

<div class="container-fluid">
    <div class="card" style="border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
        <div class="stats-header">
            <h5>
                <i class="fas fa-file-signature me-2"></i>
                Documentos Assinados Digitalmente
            </h5>
            <span class="badge-white">
                <i class="fas fa-file-alt me-1"></i>
                <?php echo $totalDocumentos; ?> documento(s)
            </span>
        </div>
        <div class="card-body" style="background: #f8f9fa;">
            <form method="GET" class="mb-4">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label for="busca" class="form-label fw-bold">Buscar</label>
                        <input type="text" class="form-control form-control-lg" id="busca" name="busca"
                               value="<?php echo htmlspecialchars($filtroBusca); ?>"
                               placeholder="ID, protocolo, requerente, assinante ou arquivo...">
                    </div>
                    <div class="col-md-4">
                        <label for="assinante" class="form-label fw-bold">Filtrar por Assinante</label>
                        <select class="form-select form-select-lg" id="assinante" name="assinante">
                            <option value="">Todos os assinantes</option>
                            <?php foreach ($listaAssinantes as $assinante): ?>
                                <option value="<?php echo htmlspecialchars($assinante['assinante_nome']); ?>"
                                        <?php echo $filtroAssinante === $assinante['assinante_nome'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($assinante['assinante_nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary btn-lg me-2 w-100">
                            <i class="fas fa-search me-1"></i> Filtrar
                        </button>
                        <?php if (!empty($filtroBusca) || !empty($filtroAssinante)): ?>
                            <a href="documentos_assinados.php" class="btn btn-secondary btn-lg">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <?php if (count($documentos) > 0): ?>
                <div class="documentos-grid">
                    <?php foreach ($documentos as $doc): ?>
                        <div class="document-card">
                            <div class="document-header">
                                <div class="document-id">
                                    <i class="fas fa-fingerprint"></i>
                                    <?php echo htmlspecialchars(substr($doc['documento_id'], 0, 12)); ?>...
                                </div>
                                <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                    <?php if ($doc['tipo_assinatura'] === 'desenho'): ?>
                                        <span class="badge bg-success document-badge">
                                            <i class="fas fa-pen"></i> Desenho
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-primary document-badge">
                                            <i class="fas fa-font"></i> Texto
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($doc['requerimento_protocolo']): ?>
                                <div style="margin-bottom: 12px;">
                                    <span class="protocolo-badge">
                                        <i class="fas fa-barcode"></i>
                                        Protocolo: <?php echo htmlspecialchars($doc['requerimento_protocolo']); ?>
                                    </span>
                                </div>
                            <?php endif; ?>

                            <div class="info-grid">
                                <div class="info-item">
                                    <span class="info-label">
                                        <i class="fas fa-user me-1"></i> Requerente
                                    </span>
                                    <span class="info-value">
                                        <?php if (!empty($doc['requerente_nome'])): ?>
                                            <strong><?php echo htmlspecialchars($doc['requerente_nome']); ?></strong>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </span>
                                </div>

                                <div class="info-item">
                                    <span class="info-label">
                                        <i class="fas fa-calendar-alt me-1"></i> Data de Assinatura
                                    </span>
                                    <span class="info-value">
                                        <i class="far fa-clock me-1"></i>
                                        <?php echo formataData($doc['timestamp_assinatura']); ?>
                                    </span>
                                </div>

                                <div class="info-item">
                                    <span class="info-label">
                                        <i class="fas fa-file-alt me-1"></i> Tipo de Documento
                                    </span>
                                    <span class="info-value">
                                        <?php echo htmlspecialchars(ucfirst($doc['tipo_documento'] ?? 'parecer')); ?>
                                    </span>
                                </div>
                            </div>

                            <div class="assinante-info">
                                <div class="assinante-avatar">
                                    <?php echo strtoupper(substr($doc['assinante_nome'], 0, 1)); ?>
                                </div>
                                <div class="assinante-details">
                                    <div class="assinante-nome">
                                        <?php echo htmlspecialchars($doc['assinante_nome']); ?>
                                    </div>
                                    <div class="assinante-meta">
                                        <?php if ($doc['assinante_cpf']): ?>
                                            <i class="fas fa-id-card me-1"></i>CPF: <?php echo htmlspecialchars($doc['assinante_cpf']); ?>
                                        <?php endif; ?>
                                        <?php if ($doc['assinante_cargo']): ?>
                                            <?php if ($doc['assinante_cpf']): ?> • <?php endif; ?>
                                            <i class="fas fa-briefcase me-1"></i><?php echo htmlspecialchars($doc['assinante_cargo']); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="document-actions">
                                <a href="parecer_viewer.php?id=<?php echo htmlspecialchars($doc['documento_id']); ?>"
                                   target="_blank"
                                   class="btn btn-primary btn-action">
                                    <i class="fas fa-eye"></i>
                                    Visualizar Documento
                                </a>
                                <?php if ($doc['requerimento_id']): ?>
                                    <a href="visualizar_requerimento.php?id=<?php echo $doc['requerimento_id']; ?>"
                                       class="btn btn-outline-secondary btn-action">
                                        <i class="fas fa-file-alt"></i>
                                        Ver Requerimento
                                    </a>
                                <?php endif; ?>
                                <a href="../consultar/verificar.php?id=<?php echo htmlspecialchars($doc['documento_id']); ?>"
                                   target="_blank"
                                   class="btn btn-outline-info btn-action">
                                    <i class="fas fa-shield-alt"></i>
                                    Verificar Autenticidade
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPaginas > 1): ?>
                    <div class="mt-4 pt-3 border-top">
                        <nav aria-label="Paginação">
                            <ul class="pagination justify-content-center mb-0">
                                <?php if ($paginaAtual > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?pagina=<?php echo $paginaAtual - 1; ?><?php echo $filtroBusca ? '&busca=' . urlencode($filtroBusca) : ''; ?><?php echo $filtroAssinante ? '&assinante=' . urlencode($filtroAssinante) : ''; ?>">
                                            <i class="fas fa-chevron-left"></i> Anterior
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php for ($i = max(1, $paginaAtual - 2); $i <= min($totalPaginas, $paginaAtual + 2); $i++): ?>
                                    <li class="page-item <?php echo $i === $paginaAtual ? 'active' : ''; ?>">
                                        <a class="page-link" href="?pagina=<?php echo $i; ?><?php echo $filtroBusca ? '&busca=' . urlencode($filtroBusca) : ''; ?><?php echo $filtroAssinante ? '&assinante=' . urlencode($filtroAssinante) : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($paginaAtual < $totalPaginas): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?pagina=<?php echo $paginaAtual + 1; ?><?php echo $filtroBusca ? '&busca=' . urlencode($filtroBusca) : ''; ?><?php echo $filtroAssinante ? '&assinante=' . urlencode($filtroAssinante) : ''; ?>">
                                            Próxima <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                            <p class="text-center text-muted mt-2 mb-0">
                                Página <?php echo $paginaAtual; ?> de <?php echo $totalPaginas; ?>
                                (<?php echo $totalDocumentos; ?> documento(s) no total)
                            </p>
                        </nav>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-file-signature"></i>
                    <h5 class="mt-3 mb-2">Nenhum documento encontrado</h5>
                    <p class="text-muted">
                        <?php if (!empty($filtroBusca) || !empty($filtroAssinante)): ?>
                            Não foram encontrados documentos que correspondam aos filtros aplicados.
                            <a href="documentos_assinados.php" class="text-decoration-none">Limpar filtros</a>
                        <?php else: ?>
                            Ainda não há documentos assinados digitalmente no sistema.
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

