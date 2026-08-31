<?php
require_once 'conexao.php';
require_once 'helpers.php';
require_once __DIR__ . '/../includes/denuncia_filters.php';
verificaLogin();

$adminId = (int) ($_SESSION['admin_id'] ?? 0);
$setorAdmin = setorAdministrador($pdo, $adminId);
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar_preferencia') {
    $csrf = (string) ($_POST['csrf_token'] ?? '');
    if ($csrf === '' || !hash_equals((string) $_SESSION['csrf_token'], $csrf)) {
        header('Location: denuncias.php?error=csrf');
        exit;
    }
    try {
        salvarPreferenciaDenuncia($pdo, $adminId, $_POST);
        header('Location: denuncias.php?success=padrao_salvo');
    } catch (InvalidArgumentException $e) {
        header('Location: denuncias.php?error=filtros_invalidos');
    } catch (Throwable $e) {
        error_log('[denuncias] Falha ao salvar preferência: ' . $e->getMessage());
        header('Location: denuncias.php?error=preferencia');
    }
    exit;
}

$preferenciaSalva = carregarPreferenciaDenuncia($pdo, $adminId);
$filtros = resolverFiltrosDenuncia($_GET, $preferenciaSalva, $setorAdmin);
$filtroBusca = trim((string) ($_GET['busca'] ?? ''));
$paginaAtual = max(1, (int) ($_GET['pagina'] ?? 1));
$itensPorPagina = 20;

if ($filtros['status'] === 'concluida') {
    $filtros['concluidas'] = '1';
}

$where = ['1=1'];
$params = [];
if ($filtroBusca !== '') {
    $where[] = '(d.protocolo_publico LIKE ? OR d.infrator_nome LIKE ? OR d.infrator_cpf_cnpj LIKE ? OR d.infrator_endereco LIKE ? OR d.observacoes LIKE ?)';
    $term = '%' . $filtroBusca . '%';
    array_push($params, $term, $term, $term, $term, $term);
}
if ($filtros['setor'] !== '') {
    $where[] = 'd.setor = ?';
    $params[] = $filtros['setor'];
}
if ($filtros['origem'] === 'publico') {
    $where[] = "d.origem = 'publico'";
} elseif ($filtros['origem'] === 'interno') {
    $where[] = "d.origem = 'admin'";
} elseif ($filtros['origem'] === 'minhas') {
    $where[] = "d.origem = 'admin' AND d.admin_id = ?";
    $params[] = $adminId;
}
if ($filtros['anonimo'] !== '') {
    $where[] = 'd.anonimo = ?';
    $params[] = (int) $filtros['anonimo'];
}

$statusSql = [
    'pendente' => "LOWER(TRIM(d.status)) = 'pendente'",
    'em_analise' => "LOWER(TRIM(d.status)) IN ('em análise', 'em analise', 'em_analise')",
    'concluida' => "LOWER(TRIM(d.status)) IN ('concluída', 'concluida', 'concluído', 'concluido', 'finalizado', 'finalizada')",
];
if ($filtros['status'] !== '') {
    $where[] = $statusSql[$filtros['status']];
} elseif ($filtros['concluidas'] !== '1') {
    $where[] = 'NOT ' . $statusSql['concluida'];
}

$whereSql = implode(' AND ', $where);
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM denuncias d WHERE {$whereSql}");
$stmtCount->execute($params);
$totalDenuncias = (int) $stmtCount->fetchColumn();
$totalPaginas = max(1, (int) ceil($totalDenuncias / $itensPorPagina));
$paginaAtual = min($paginaAtual, $totalPaginas);
$offset = ($paginaAtual - 1) * $itensPorPagina;

$sql = "SELECT d.id, d.data_registro, d.infrator_nome, d.infrator_cpf_cnpj,
               d.infrator_endereco, d.observacoes, d.status, d.origem, d.setor,
               d.protocolo_publico, d.anonimo, d.tipo_denuncia, d.admin_id,
               a.nome AS responsavel
        FROM denuncias d
        LEFT JOIN administradores a ON d.admin_id = a.id
        WHERE {$whereSql}
        ORDER BY d.data_registro DESC, d.id DESC
        LIMIT {$itensPorPagina} OFFSET {$offset}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$denuncias = $stmt->fetchAll();

$stats = $pdo->query("SELECT
    COUNT(*) AS total,
    SUM(CASE WHEN LOWER(TRIM(status)) = 'pendente' THEN 1 ELSE 0 END) AS pendentes,
    SUM(CASE WHEN LOWER(TRIM(status)) IN ('em análise','em analise','em_analise') THEN 1 ELSE 0 END) AS em_analise,
    SUM(CASE WHEN anonimo = 1 THEN 1 ELSE 0 END) AS anonimas
    FROM denuncias")->fetch() ?: [];

$mensagem = match ($_GET['success'] ?? '') {
    'registrada' => 'Denúncia registrada com sucesso.',
    'atualizada' => 'Denúncia atualizada com sucesso.',
    'excluida' => 'Denúncia removida corretamente.',
    'padrao_salvo' => 'Os filtros atuais foram salvos como seu padrão.',
    default => '',
};
$mensagemErro = match ($_GET['error'] ?? '') {
    'criacao' => 'Não foi possível registrar a denúncia.',
    'nao_encontrado' => 'Denúncia não encontrada.',
    'csrf' => 'A sessão expirou. Atualize a página e tente novamente.',
    'filtros_invalidos' => 'Um dos filtros enviados não é permitido.',
    'preferencia' => 'Não foi possível salvar a preferência. Verifique se a migration foi aplicada.',
    default => '',
};

function buildDenunciaUrl(array $overrides = []): string
{
    global $filtros, $filtroBusca;
    $params = array_merge($filtros, ['busca' => $filtroBusca], $overrides);
    unset($params['limpar']);
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null || ($key === 'pagina' && (int) $value === 1) || ($key === 'concluidas' && $value === '0')) {
            unset($params[$key]);
        }
    }
    return 'denuncias.php' . ($params ? '?' . http_build_query($params) : '');
}

function denunciaStatusClass(string $status): string
{
    return match (normalizarStatusProcesso($status)) {
        'em_analise' => 'status-em-analise',
        'concluida' => 'status-finalizado',
        default => 'status-pendente',
    };
}

include 'header.php';
?>
<link rel="stylesheet" href="<?= adminAssetUrl('includes/admin-styles.css') ?>">
<style>
.den-list{display:grid;gap:12px}.den-card{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:20px;align-items:center;padding:18px 20px;background:#fff;border:1px solid var(--line);border-left:4px solid #538867;border-radius:16px;box-shadow:var(--card-shadow);color:inherit;text-decoration:none;transition:.16s ease}.den-card.obras{border-left-color:#c98b2e}.den-card:hover{color:inherit;transform:translateY(-1px);border-color:#b9cbc0;box-shadow:0 12px 28px rgba(24,54,37,.09)}.den-card-top,.den-card-meta{display:flex;align-items:center;flex-wrap:wrap;gap:8px}.den-card-title{margin:9px 0 5px;color:var(--ink);font-size:1.02rem;font-weight:800}.den-card-subtitle{color:var(--muted);font-size:.84rem;line-height:1.45}.den-card-side{min-width:150px;text-align:right}.den-card-date{margin-bottom:10px;color:var(--muted);font-size:.78rem}.den-type-pill{display:inline-flex;align-items:center;gap:5px;padding:4px 9px;border-radius:999px;background:#f6e9e8;color:#913f39;font-size:.7rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase}.den-anon-pill{display:inline-flex;align-items:center;gap:5px;padding:4px 9px;border-radius:999px;background:#302b36;color:#fff;font-size:.7rem;font-weight:800}.den-origin,.den-sector{color:var(--muted);font-size:.76rem;font-weight:650}.den-open{display:inline-flex;align-items:center;gap:7px;color:var(--primary);font-size:.82rem;font-weight:800}.den-filter-grid{display:grid;grid-template-columns:minmax(220px,2fr) repeat(4,minmax(135px,1fr));gap:12px;align-items:end}.den-filter-field label{display:block;margin-bottom:6px;color:var(--muted);font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em}.den-filter-field input,.den-filter-field select{width:100%;min-height:42px;border:1px solid var(--line);border-radius:10px;padding:8px 11px;background:#fff;color:var(--ink)}.den-filter-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px;align-items:center}.den-summary .summary-chip{min-width:145px}.den-toggle{display:inline-flex;align-items:center;gap:8px;min-height:42px;padding:8px 11px;border:1px solid var(--line);border-radius:10px;background:#fff;color:var(--ink);font-size:.82rem;font-weight:650}.den-empty{padding:50px 20px;background:#fff;border:1px dashed #cbd8cf;border-radius:18px;text-align:center;color:var(--muted)}.den-empty i{display:block;margin-bottom:12px;font-size:2rem;color:#a9baae}@media(max-width:1100px){.den-filter-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.den-filter-search{grid-column:1/-1}}@media(max-width:680px){.den-filter-grid{grid-template-columns:1fr}.den-filter-search{grid-column:auto}.den-card{grid-template-columns:1fr;gap:12px}.den-card-side{display:flex;align-items:center;justify-content:space-between;text-align:left;min-width:0}.den-card-date{margin:0}}
.den-search-wrap{position:relative}.den-search-wrap>i{position:absolute;z-index:2;left:12px;top:50%;transform:translateY(-50%);color:#8fa399;font-size:.8rem}.den-search-wrap input{padding-left:34px}.den-suggestions{display:none;position:absolute;z-index:50;top:calc(100% + 7px);left:0;right:0;overflow:hidden;padding:6px;background:#fff;border:1px solid #d9e3dc;border-radius:13px;box-shadow:0 18px 42px rgba(16,33,23,.16)}.den-suggestions.active{display:block}.den-suggestion{display:flex;align-items:center;gap:11px;padding:10px;border-radius:9px;color:inherit;text-decoration:none}.den-suggestion:hover{background:#f3f7f4;color:inherit}.den-suggestion-icon{width:34px;height:34px;display:flex;align-items:center;justify-content:center;flex:0 0 auto;border-radius:9px;background:#f6e9e8;color:#913f39}.den-suggestion-copy{min-width:0;flex:1}.den-suggestion-top{display:flex;align-items:center;gap:8px;min-width:0}.den-suggestion-protocol{font-family:ui-monospace,monospace;color:#52635a;font-size:.72rem}.den-suggestion-title{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#102117;font-size:.84rem;font-weight:750}.den-suggestion-meta{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-top:2px;color:#7a8a81;font-size:.72rem}.den-suggestion-empty{padding:14px;text-align:center;color:#7a8a81;font-size:.78rem}
</style>

<div class="admin-page-shell denuncias-page">
    <section class="page-hero page-hero-compact">
        <div class="page-hero-copy"><h1 class="page-title">Denúncias</h1><p class="page-subtitle">Acompanhe ocorrências encaminhadas pelos cidadãos e pela fiscalização.</p></div>
        <div class="page-toolbar"><a href="nova_denuncia.php" class="toolbar-button toolbar-button-primary"><i class="fas fa-plus"></i> Registrar denúncia</a></div>
    </section>
    <?php if ($mensagem): ?><div class="alert alert-success" role="status"><?= htmlspecialchars($mensagem) ?></div><?php endif; ?>
    <?php if ($mensagemErro): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($mensagemErro) ?></div><?php endif; ?>

    <section class="req-summary-strip den-summary" aria-label="Indicadores de denúncias">
        <span class="summary-chip active"><span><i class="fas fa-layer-group"></i>Total</span><strong><?= (int) ($stats['total'] ?? 0) ?></strong></span>
        <a href="<?= htmlspecialchars(buildDenunciaUrl(['status' => 'pendente', 'pagina' => 1])) ?>" class="summary-chip <?= $filtros['status'] === 'pendente' ? 'active' : '' ?>"><span><i class="fas fa-clock"></i>Pendentes</span><strong><?= (int) ($stats['pendentes'] ?? 0) ?></strong></a>
        <a href="<?= htmlspecialchars(buildDenunciaUrl(['status' => 'em_analise', 'pagina' => 1])) ?>" class="summary-chip <?= $filtros['status'] === 'em_analise' ? 'active' : '' ?>"><span><i class="fas fa-magnifying-glass"></i>Em análise</span><strong><?= (int) ($stats['em_analise'] ?? 0) ?></strong></a>
        <a href="<?= htmlspecialchars(buildDenunciaUrl(['anonimo' => '1', 'pagina' => 1])) ?>" class="summary-chip <?= $filtros['anonimo'] === '1' ? 'active' : '' ?>"><span><i class="fas fa-user-secret"></i>Anônimas</span><strong><?= (int) ($stats['anonimas'] ?? 0) ?></strong></a>
    </section>

    <section class="req-filter-bar">
        <form method="GET">
            <div class="den-filter-grid">
                <div class="den-filter-field den-filter-search"><label for="busca">Busca</label><div class="den-search-wrap"><i class="fas fa-magnifying-glass"></i><input id="busca" name="busca" type="search" autocomplete="off" aria-autocomplete="list" aria-controls="denunciaSuggestions" aria-expanded="false" value="<?= htmlspecialchars($filtroBusca) ?>" placeholder="Protocolo, infrator ou CPF/CNPJ"><div id="denunciaSuggestions" class="den-suggestions" role="listbox"></div></div></div>
                <div class="den-filter-field"><label for="status">Status</label><select id="status" name="status"><option value="">Todos</option><option value="pendente" <?= $filtros['status'] === 'pendente' ? 'selected' : '' ?>>Pendente</option><option value="em_analise" <?= $filtros['status'] === 'em_analise' ? 'selected' : '' ?>>Em análise</option><option value="concluida" <?= $filtros['status'] === 'concluida' ? 'selected' : '' ?>>Concluída</option></select></div>
                <div class="den-filter-field"><label for="setor">Setor</label><select id="setor" name="setor"><option value="">Todos</option><option value="meio_ambiente" <?= $filtros['setor'] === 'meio_ambiente' ? 'selected' : '' ?>>Meio Ambiente</option><option value="obras_urbanismo" <?= $filtros['setor'] === 'obras_urbanismo' ? 'selected' : '' ?>>Obras e Urbanismo</option></select></div>
                <div class="den-filter-field"><label for="origem">Origem</label><select id="origem" name="origem"><option value="">Todas</option><option value="publico" <?= $filtros['origem'] === 'publico' ? 'selected' : '' ?>>Cidadão</option><option value="interno" <?= $filtros['origem'] === 'interno' ? 'selected' : '' ?>>Internas</option><option value="minhas" <?= $filtros['origem'] === 'minhas' ? 'selected' : '' ?>>Criadas por mim</option></select></div>
                <div class="den-filter-field"><label for="anonimo">Anonimato</label><select id="anonimo" name="anonimo"><option value="">Todos</option><option value="1" <?= $filtros['anonimo'] === '1' ? 'selected' : '' ?>>Anônimas</option><option value="0" <?= $filtros['anonimo'] === '0' ? 'selected' : '' ?>>Identificadas</option></select></div>
            </div>
            <div class="den-filter-actions">
                <input type="hidden" name="concluidas" value="0"><label class="den-toggle"><input type="checkbox" name="concluidas" value="1" <?= $filtros['concluidas'] === '1' ? 'checked' : '' ?>> Incluir concluídas</label>
                <button type="submit" class="toolbar-button toolbar-button-primary">Aplicar filtros</button>
                <a href="denuncias.php?limpar=1" class="toolbar-button">Limpar filtros</a>
                <a href="denuncias.php" class="toolbar-button toolbar-button-ghost"><i class="fas fa-rotate-left"></i> Restaurar padrão</a>
            </div>
        </form>
        <form method="POST" class="den-filter-actions" style="margin-top:10px;padding-top:10px;border-top:1px solid var(--line);">
            <input type="hidden" name="acao" value="salvar_preferencia"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <?php foreach ($filtros as $key => $value): ?><input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>"><?php endforeach; ?>
            <button type="submit" class="toolbar-button"><i class="fas fa-bookmark"></i> Salvar como padrão</button>
        </form>
    </section>

    <?php if ($denuncias): ?>
        <section class="den-list" aria-label="Lista de denúncias">
            <?php foreach ($denuncias as $denuncia):
                $tipos = tiposDenuncia($denuncia);
                $subtitulo = implode(' · ', $tipos);
                if ($subtitulo === '') $subtitulo = trim((string) ($denuncia['infrator_endereco'] ?? ''));
                if ($subtitulo === '') $subtitulo = mb_strimwidth(trim((string) $denuncia['observacoes']), 0, 130, '…', 'UTF-8');
                $protocolo = $denuncia['protocolo_publico'] ?: 'DEN-' . str_pad((string) $denuncia['id'], 6, '0', STR_PAD_LEFT);
                $ehObras = ($denuncia['setor'] ?? '') === 'obras_urbanismo';
            ?>
                <a class="den-card <?= $ehObras ? 'obras' : '' ?>" href="visualizar_denuncia.php?id=<?= (int) $denuncia['id'] ?>">
                    <div><div class="den-card-top"><span class="den-type-pill"><i class="fas fa-bullhorn"></i> Denúncia</span><span class="req-protocol">#<?= htmlspecialchars($protocolo) ?></span><span class="badge badge-status <?= htmlspecialchars(denunciaStatusClass((string) $denuncia['status'])) ?>"><?= htmlspecialchars($denuncia['status']) ?></span><?php if (!empty($denuncia['anonimo'])): ?><span class="den-anon-pill"><i class="fas fa-user-secret"></i> Anônima</span><?php endif; ?></div>
                        <div class="den-card-title"><?= htmlspecialchars(tituloDenuncia($denuncia)) ?></div><div class="den-card-subtitle"><?= htmlspecialchars($subtitulo ?: 'Ocorrência sem local ou tipo informado') ?></div>
                        <div class="den-card-meta" style="margin-top:9px;"><span class="den-sector"><i class="fas <?= $ehObras ? 'fa-hard-hat' : 'fa-leaf' ?>"></i> <?= $ehObras ? 'Obras e Urbanismo' : 'Meio Ambiente' ?></span><span class="den-origin"><i class="fas <?= ($denuncia['origem'] ?? 'admin') === 'publico' ? 'fa-earth-americas' : 'fa-user-shield' ?>"></i> <?= ($denuncia['origem'] ?? 'admin') === 'publico' ? 'Cidadão' : 'Interna' ?></span><?php if (($denuncia['origem'] ?? 'admin') === 'admin' && $denuncia['responsavel']): ?><span class="den-origin">Criada por <?= htmlspecialchars($denuncia['responsavel']) ?></span><?php endif; ?></div>
                    </div>
                    <div class="den-card-side"><div class="den-card-date"><?= date('d/m/Y \à\s H:i', strtotime($denuncia['data_registro'])) ?></div><span class="den-open">Abrir <i class="fas fa-arrow-right"></i></span></div>
                </a>
            <?php endforeach; ?>
        </section>
        <section class="req-pagination"><div class="req-pagination-copy">Página <?= $paginaAtual ?> de <?= $totalPaginas ?> · <?= $totalDenuncias ?> denúncia(s)</div><div class="req-pagination-links">
            <?php if ($paginaAtual > 1): ?><a href="<?= htmlspecialchars(buildDenunciaUrl(['pagina' => 1])) ?>" class="req-page-link">«</a><a href="<?= htmlspecialchars(buildDenunciaUrl(['pagina' => $paginaAtual - 1])) ?>" class="req-page-link">‹</a><?php endif; ?>
            <?php for ($i = max(1, $paginaAtual - 2); $i <= min($totalPaginas, $paginaAtual + 2); $i++): ?><a href="<?= htmlspecialchars(buildDenunciaUrl(['pagina' => $i])) ?>" class="req-page-link <?= $i === $paginaAtual ? 'active' : '' ?>"><?= $i ?></a><?php endfor; ?>
            <?php if ($paginaAtual < $totalPaginas): ?><a href="<?= htmlspecialchars(buildDenunciaUrl(['pagina' => $paginaAtual + 1])) ?>" class="req-page-link">›</a><a href="<?= htmlspecialchars(buildDenunciaUrl(['pagina' => $totalPaginas])) ?>" class="req-page-link">»</a><?php endif; ?>
        </div></section>
    <?php else: ?><div class="den-empty"><i class="fas fa-inbox"></i><strong>Nenhuma denúncia encontrada.</strong><p class="mb-0 mt-1">Ajuste os filtros ou registre uma nova ocorrência.</p></div><?php endif; ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('busca');
    const box = document.getElementById('denunciaSuggestions');
    if (!input || !box) return;

    const filtros = <?= json_encode($filtros, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    let timer = null;
    let controller = null;

    function escapeHtml(value) {
        const node = document.createElement('div');
        node.textContent = String(value == null ? '' : value);
        return node.innerHTML;
    }
    function closeSuggestions() {
        box.classList.remove('active');
        input.setAttribute('aria-expanded', 'false');
    }
    function renderSuggestions(data) {
        const items = data && Array.isArray(data.resultados) ? data.resultados : [];
        if (!items.length) {
            box.innerHTML = '<div class="den-suggestion-empty">Nenhuma sugestão encontrada</div>';
        } else {
            box.innerHTML = items.map(function (item) {
                const meta = [item.documento, item.tipo, item.status, item.setor].filter(Boolean).join(' · ');
                return '<a class="den-suggestion" role="option" href="' + escapeHtml(item.url) + '">' +
                    '<span class="den-suggestion-icon"><i class="fas ' + (item.anonimo ? 'fa-user-secret' : 'fa-bullhorn') + '"></i></span>' +
                    '<span class="den-suggestion-copy"><span class="den-suggestion-top"><span class="den-suggestion-title">' + escapeHtml(item.titulo) + '</span><span class="den-suggestion-protocol">#' + escapeHtml(item.protocolo) + '</span></span>' +
                    '<span class="den-suggestion-meta">' + escapeHtml(meta) + '</span></span></a>';
            }).join('');
        }
        box.classList.add('active');
        input.setAttribute('aria-expanded', 'true');
    }
    function searchSuggestions() {
        const termo = input.value.trim();
        if (termo.length < 2) {
            if (controller) controller.abort();
            closeSuggestions();
            return;
        }
        if (controller) controller.abort();
        controller = new AbortController();
        const params = new URLSearchParams(filtros);
        params.set('q', termo);
        fetch('ajax/busca_denuncias.php?' + params.toString(), {
            signal: controller.signal,
            headers: {'X-Requested-With': 'fetch'}
        }).then(function (response) {
            return response.ok ? response.json() : null;
        }).then(function (data) {
            if (!data || data.termo !== input.value.trim()) return;
            renderSuggestions(data);
        }).catch(function (error) {
            if (error.name !== 'AbortError') closeSuggestions();
        });
    }
    input.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(searchSuggestions, 220);
    });
    input.addEventListener('focus', function () {
        if (input.value.trim().length >= 2) searchSuggestions();
    });
    input.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeSuggestions();
        // Enter não seleciona uma sugestão: o submit normal executa a busca completa.
    });
    document.addEventListener('click', function (event) {
        if (!event.target.closest('.den-search-wrap')) closeSuggestions();
    });
});
</script>
<?php include 'footer.php'; ?>
