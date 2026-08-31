<?php
/**
 * Sugestões contextuais da busca na listagem de denúncias.
 * Recebe os filtros já resolvidos pela página e pesquisa somente dentro deles.
 */
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../../includes/denuncia_filters.php';
verificaLogin();

header('Content-Type: application/json; charset=utf-8');

$termo = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($termo) < 2) {
    echo json_encode(['resultados' => [], 'total' => 0, 'termo' => $termo], JSON_UNESCAPED_UNICODE);
    exit;
}

$filtros = array_merge(filtrosLimposDenuncia(), validarFiltrosDenuncia($_GET));
if ($filtros['status'] === 'concluida') {
    $filtros['concluidas'] = '1';
}

$where = ['(d.protocolo_publico LIKE ? OR d.infrator_nome LIKE ? OR d.infrator_cpf_cnpj LIKE ? OR d.infrator_endereco LIKE ?)'];
$curinga = '%' . $termo . '%';
$params = [$curinga, $curinga, $curinga, $curinga];

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
    $params[] = (int) ($_SESSION['admin_id'] ?? 0);
}
if ($filtros['anonimo'] !== '') {
    $where[] = 'd.anonimo = ?';
    $params[] = (int) $filtros['anonimo'];
}

$statusConcluido = "LOWER(TRIM(d.status)) IN ('concluída','concluida','concluído','concluido','finalizado','finalizada')";
if ($filtros['status'] === 'pendente') {
    $where[] = "LOWER(TRIM(d.status)) = 'pendente'";
} elseif ($filtros['status'] === 'em_analise') {
    $where[] = "LOWER(TRIM(d.status)) IN ('em análise','em analise','em_analise')";
} elseif ($filtros['status'] === 'concluida') {
    $where[] = $statusConcluido;
} elseif ($filtros['concluidas'] !== '1') {
    $where[] = "NOT {$statusConcluido}";
}

try {
    $stmt = $pdo->prepare("SELECT d.id, d.protocolo_publico, d.infrator_nome,
                                  d.infrator_cpf_cnpj, d.status, d.setor, d.origem,
                                  d.anonimo, d.tipo_denuncia, d.data_registro
                           FROM denuncias d
                           WHERE " . implode(' AND ', $where) . "
                           ORDER BY CASE WHEN d.protocolo_publico = ? THEN 0
                                         WHEN d.protocolo_publico LIKE ? THEN 1 ELSE 2 END,
                                    d.data_registro DESC, d.id DESC
                           LIMIT 7");
    $stmt->execute(array_merge($params, [$termo, $termo . '%']));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['resultados' => [], 'total' => 0, 'termo' => $termo, 'erro' => 'Falha na consulta.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$temMais = count($rows) > 6;
$rows = array_slice($rows, 0, 6);
$resultados = array_map(static function (array $row): array {
    $tipos = tiposDenuncia($row);
    return [
        'id' => (int) $row['id'],
        'protocolo' => $row['protocolo_publico'] ?: 'DEN-' . str_pad((string) $row['id'], 6, '0', STR_PAD_LEFT),
        'titulo' => tituloDenuncia($row),
        'documento' => trim((string) ($row['infrator_cpf_cnpj'] ?? '')),
        'status' => (string) $row['status'],
        'setor' => $row['setor'] === 'obras_urbanismo' ? 'Obras e Urbanismo' : 'Meio Ambiente',
        'tipo' => $tipos[0] ?? '',
        'anonimo' => !empty($row['anonimo']),
        'url' => 'visualizar_denuncia.php?id=' . (int) $row['id'],
    ];
}, $rows);

echo json_encode([
    'resultados' => $resultados,
    'total' => count($resultados),
    'tem_mais' => $temMais,
    'termo' => $termo,
], JSON_UNESCAPED_UNICODE);
