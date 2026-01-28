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
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 16px;
        margin-top: 20px;
    }

    .document-card {
        border: 1px solid #e8e8e8;
        border-radius: 8px;
        padding: 14px;
        background: #fff;
        transition: all 0.2s ease;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        display: flex;
        flex-direction: column;
    }

    .document-card:hover {
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        border-color: #d0d0d0;
    }

    .document-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
        padding-bottom: 8px;
        border-bottom: 1px solid #f0f0f0;
        gap: 8px;
    }

    .document-id {
        font-family: 'Courier New', monospace;
        font-size: 0.7rem;
        color: #888;
        background: #f5f5f5;
        padding: 3px 7px;
        border-radius: 4px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .document-badge {
        font-size: 0.65rem;
        padding: 3px 8px;
        border-radius: 12px;
        font-weight: 600;
        white-space: nowrap;
    }

    .info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        margin-bottom: 10px;
    }

    .info-item {
        display: flex;
        flex-direction: column;
    }

    .info-item.full-width {
        grid-column: 1 / -1;
    }

    .info-label {
        font-size: 0.65rem;
        color: #888;
        text-transform: uppercase;
        font-weight: 600;
        margin-bottom: 2px;
        letter-spacing: 0.3px;
    }

    .info-value {
        font-size: 0.8rem;
        color: #333;
        font-weight: 500;
        line-height: 1.3;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .info-value strong {
        color: var(--primary-color);
    }

    .protocolo-badge {
        background: var(--primary-color);
        color: white;
        padding: 4px 10px;
        border-radius: 5px;
        font-weight: 600;
        font-size: 0.75rem;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        white-space: nowrap;
        margin-bottom: 8px;
    }

    .assinante-info {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px;
        background: #fafafa;
        border-radius: 6px;
        margin-bottom: 10px;
        border: 1px solid #f0f0f0;
    }

    .assinante-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: var(--primary-color);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
        font-size: 0.85rem;
        flex-shrink: 0;
    }

    .assinante-details {
        flex: 1;
        min-width: 0;
    }

    .assinante-nome {
        font-weight: 600;
        color: #333;
        font-size: 0.8rem;
        margin-bottom: 1px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .assinante-meta {
        font-size: 0.7rem;
        color: #888;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .document-actions {
        display: flex;
        flex-direction: row;
        gap: 6px;
        margin-top: auto;
        padding-top: 10px;
        border-top: 1px solid #f0f0f0;
    }

    .btn-action {
        padding: 6px 10px;
        border-radius: 5px;
        font-weight: 500;
        font-size: 0.75rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        transition: all 0.2s;
        flex: 1;
        text-decoration: none;
        border: 1px solid transparent;
    }

    .btn-action i {
        font-size: 0.85rem;
    }

    .btn-action.btn-primary {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }

    .btn-action.btn-primary:hover {
        background: var(--secondary-color);
        border-color: var(--secondary-color);
    }

    .btn-action.btn-outline-secondary {
        background: transparent;
        color: #6c757d;
        border-color: #6c757d;
    }

    .btn-action.btn-outline-secondary:hover {
        background: #6c757d;
        color: white;
    }

    .btn-action.btn-outline-info {
        background: transparent;
        color: #0dcaf0;
        border-color: #0dcaf0;
    }

    .btn-action.btn-outline-info:hover {
        background: #0dcaf0;
        color: white;
    }

    .btn-action.btn-outline-danger {
        background: transparent;
        color: #dc3545;
        border-color: #dc3545;
    }

    .btn-action.btn-outline-danger:hover {
        background: #dc3545;
        color: white;
    }

    .btn-action:disabled,
    .btn-action.btn-secondary:disabled {
        background: #e9ecef;
        color: #adb5bd;
        border-color: #dee2e6;
        cursor: not-allowed;
        opacity: 0.6;
    }

    .badge.bg-danger {
        background-color: #dc3545 !important;
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
        padding: 18px 20px;
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        color: white;
        border-radius: 8px 8px 0 0;
    }

    .stats-header h5 {
        margin: 0;
        font-weight: 600;
        font-size: 1.1rem;
    }

    .badge-white {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        padding: 6px 12px;
        border-radius: 16px;
        font-weight: 600;
        font-size: 0.85rem;
    }

    @media (max-width: 1200px) {
        .documentos-grid {
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        }
    }

    @media (max-width: 768px) {
        .documentos-grid {
            grid-template-columns: 1fr;
        }

        .document-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 6px;
        }

        .stats-header {
            flex-direction: column;
            gap: 10px;
            text-align: center;
        }

        .document-actions {
            flex-direction: column;
        }

        .info-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (min-width: 1400px) {
        .documentos-grid {
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
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
                        <?php
                        // Verificar se o arquivo existe usando o caminho salvo no banco
                        $caminhoArquivo = $doc['caminho_arquivo'];
                        $documentoApagado = empty($caminhoArquivo) || !file_exists($caminhoArquivo);
                        ?>
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
                                    <?php if ($documentoApagado): ?>
                                        <span class="badge bg-danger document-badge" title="Documento HTML foi apagado">
                                            <i class="fas fa-trash"></i> Apagado
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($doc['requerimento_protocolo']): ?>
                                <div>
                                    <span class="protocolo-badge">
                                        <i class="fas fa-barcode"></i>
                                        <?php echo htmlspecialchars($doc['requerimento_protocolo']); ?>
                                    </span>
                                </div>
                            <?php endif; ?>

                            <div class="info-grid">
                                <div class="info-item full-width">
                                    <span class="info-label">
                                        <i class="fas fa-user me-1"></i> Requerente
                                    </span>
                                    <span class="info-value" title="<?php echo htmlspecialchars($doc['requerente_nome'] ?? 'N/A'); ?>">
                                        <?php if (!empty($doc['requerente_nome'])): ?>
                                            <strong><?php echo htmlspecialchars($doc['requerente_nome']); ?></strong>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </span>
                                </div>

                                <div class="info-item">
                                    <span class="info-label">
                                        <i class="fas fa-calendar-alt me-1"></i> Data
                                    </span>
                                    <span class="info-value">
                                        <?php echo formataData($doc['timestamp_assinatura']); ?>
                                    </span>
                                </div>

                                <div class="info-item">
                                    <span class="info-label">
                                        <i class="fas fa-file-alt me-1"></i> Tipo
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
                                    <div class="assinante-nome" title="<?php echo htmlspecialchars($doc['assinante_nome']); ?>">
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
                                <?php if ($documentoApagado): ?>
                                    <button class="btn btn-secondary btn-action" disabled title="Documento foi apagado">
                                        <i class="fas fa-eye-slash"></i>
                                    </button>
                                <?php else: ?>
                                    <a href="parecer_viewer.php?id=<?php echo htmlspecialchars($doc['documento_id']); ?>"
                                       target="_blank"
                                       class="btn btn-primary btn-action"
                                       title="Visualizar Documento">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if ($doc['requerimento_id']): ?>
                                    <a href="visualizar_requerimento.php?id=<?php echo $doc['requerimento_id']; ?>"
                                       class="btn btn-outline-secondary btn-action"
                                       title="Ver Requerimento">
                                        <i class="fas fa-file-alt"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="../consultar/verificar.php?id=<?php echo htmlspecialchars($doc["documento_id"]); ?>" target="_blank" class="btn btn-outline-info btn-action" title="Verificar Autenticidade"><i class="fas fa-shield-alt"></i></a><button type="button" class="btn btn-outline-danger btn-action" onclick="excluirDocumento('<?php echo $doc["documento_id"]; ?>')" title="Excluir Documento"><i class="fas fa-trash"></i></button></div>
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

<!-- Modal de Confirmação de Exclusão -->
<div class="modal fade" id="modalExcluirDocumento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i> Excluir Documento
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p>Como você deseja excluir este documento assinado?</p>
                
                <div class="card mb-3 border-warning">
                    <div class="card-body">
                        <h6 class="fw-bold"><i class="fas fa-list me-2"></i>Remover da Listagem</h6>
                        <p class="small text-muted mb-2">O registro será removido do banco de dados e não aparecerá mais nesta lista, mas os arquivos físicos permanecerão no servidor.</p>
                        <button type="button" class="btn btn-warning btn-sm w-100" onclick="confirmarExclusao(false)">
                            Apenas remover da listagem
                        </button>
                    </div>
                </div>

                <div class="card border-danger">
                    <div class="card-body">
                        <h6 class="fw-bold text-danger"><i class="fas fa-trash-alt me-2"></i>Exclusão Permanente</h6>
                        <p class="small text-muted mb-2">O registro será removido e o arquivo PDF será apagado permanentemente do servidor. Esta ação não pode ser desfeita.</p>
                        <button type="button" class="btn btn-danger btn-sm w-100" onclick="confirmarExclusao(true)">
                            Excluir permanentemente
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let documentoIdParaExcluir = null;
let modalExcluir = null;

function excluirDocumento(id) {
    documentoIdParaExcluir = id;
    
    // Inicializar o modal apenas quando necessário (lazy initialization)
    // Isso garante que o Bootstrap já esteja carregado
    if (!modalExcluir) {
        modalExcluir = new bootstrap.Modal(document.getElementById('modalExcluirDocumento'));
    }
    
    modalExcluir.show();
}

function confirmarExclusao(permanente) {
    if (permanente && !confirm('TEM CERTEZA? Esta ação apagará o arquivo físico do servidor e não poderá ser desfeita.')) {
        return;
    }

    fetch('parecer_handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            action: 'excluir_documento_assinado',
            documento_id: documentoIdParaExcluir,
            permanente: permanente
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Erro ao excluir: ' + (data.error || data.mensagem));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao processar a requisição.');
    });
}
</script>

<?php include 'footer.php'; ?>

