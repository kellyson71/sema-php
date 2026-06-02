<?php
require_once 'conexao.php';
require_once 'helpers.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../tipos_alvara.php';
verificaLogin();

$nivelAdmin = $_SESSION['admin_nivel'] ?? '';
$setorFiltro = match($nivelAdmin) {
    'fiscal'     => 'setor2',
    'secretario' => 'setor3',
    default      => null,
};

$filtroStatus   = $_GET['status']   ?? '';
$filtroTipo     = $_GET['tipo']     ?? '';
$filtroCategoria = $_GET['categoria'] ?? '';
$filtroBusca    = $_GET['busca']    ?? '';
$filtroNaoVisualizados = isset($_GET['nao_visualizados']) && $_GET['nao_visualizados'] === '1';
$statusEncerrados = ['Finalizado', 'Indeferido', 'Aprovado', 'Cancelado'];
$mostrarEncerrados = isset($_GET['encerrados']) && $_GET['encerrados'] === '1';
if (in_array($filtroStatus, $statusEncerrados, true)) {
    $mostrarEncerrados = true;
}

$tiposPorCategoria = [];
foreach ($tipos_alvara as $slug => $tipo) {
    $cat = $tipo['categoria'] ?? 'outro';
    $tiposPorCategoria[$cat][] = $slug;
}

$sql = "SELECT r.protocolo, req.nome AS requerente, req.cpf_cnpj,
               r.tipo_alvara, r.status, r.setor_atual, r.aguardando_acao,
               r.visualizado, r.data_envio
        FROM requerimentos r
        JOIN requerentes req ON r.requerente_id = req.id
        WHERE 1=1";
$params = [];

if ($setorFiltro) {
    $sql     .= " AND r.setor_atual = ?";
    $params[] = $setorFiltro;
}
if ($filtroStatus !== '') {
    $sql     .= " AND r.status = ?";
    $params[] = $filtroStatus;
}
if ($filtroTipo !== '') {
    $sql     .= " AND r.tipo_alvara = ?";
    $params[] = $filtroTipo;
}
if ($filtroCategoria !== '' && !empty($tiposPorCategoria[$filtroCategoria])) {
    $slugsCat = $tiposPorCategoria[$filtroCategoria];
    $placeholders = implode(',', array_fill(0, count($slugsCat), '?'));
    $sql .= " AND r.tipo_alvara IN ($placeholders)";
    foreach ($slugsCat as $s) { $params[] = $s; }
}
if ($filtroBusca !== '') {
    $sql     .= " AND (r.protocolo LIKE ? OR req.nome LIKE ? OR req.cpf_cnpj LIKE ?)";
    $termo    = '%' . $filtroBusca . '%';
    $params[] = $termo;
    $params[] = $termo;
    $params[] = $termo;
}
if ($filtroNaoVisualizados) {
    $sql .= " AND r.visualizado = 0";
}
if (!$mostrarEncerrados && $filtroStatus === '' && $filtroBusca === '') {
    $phEnc    = implode(',', array_fill(0, count($statusEncerrados), '?'));
    $sql     .= " AND r.status NOT IN ($phEnc)";
    foreach ($statusEncerrados as $se) { $params[] = $se; }
}

$sql .= " ORDER BY r.data_envio DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$nomeArquivo = 'requerimentos_' . date('Y-m-d_His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');

$out = fopen('php://output', 'w');

// BOM para Excel reconhecer UTF-8
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, ['Protocolo', 'Requerente', 'CPF/CNPJ', 'Tipo de Alvará', 'Status', 'Aberto', 'Data de Envio'], ';');

foreach ($rows as $row) {
    fputcsv($out, [
        $row['protocolo'],
        $row['requerente'],
        $row['cpf_cnpj'],
        $tipos_alvara[$row['tipo_alvara']]['nome'] ?? ucwords(str_replace('_', ' ', $row['tipo_alvara'])),
        $row['status'],
        $row['visualizado'] ? 'Sim' : 'Não',
        date('d/m/Y H:i', strtotime($row['data_envio'])),
    ], ';');
}

fclose($out);
exit;
