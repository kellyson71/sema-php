<?php
require_once 'conexao.php';
verificaLogin();

// Incluir arquivos de componentes
require_once 'includes/filtros.php';
require_once 'includes/estatisticas.php';
require_once 'includes/tabela.php';
require_once 'includes/alertas.php';
require_once 'includes/context-menu.php';

// Função auxiliar para formatar data brasileira
function formataDataBR($data)
{
    return date('d/m/Y \à\s H:i', strtotime($data));
}

// Configurações
$itensPorPagina = 25;
$paginaAtual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($paginaAtual - 1) * $itensPorPagina;

// Preparar filtros
$filtroStatus = isset($_GET['status']) ? $_GET['status'] : '';
$filtroTipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$filtroBusca = isset($_GET['busca']) ? $_GET['busca'] : '';
$filtroNaoVisualizados = isset($_GET['nao_visualizados']) && $_GET['nao_visualizados'] == '1';

// Mensagens de sucesso
$mensagem = '';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'nao_lido':
            $mensagem = "✅ Requerimento marcado como não lido com sucesso!";
            break;
        case 'atualizado':
            $mensagem = "✅ Requerimento atualizado com sucesso!";
            break;
        case 'acoes_massa':
            $mensagem = "✅ " . ($_GET['msg'] ?? 'Ação executada com sucesso!');
            break;
    }
}

// Mensagens de erro
$mensagemErro = '';
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'dados_invalidos':
            $mensagemErro = "❌ Dados inválidos para a ação solicitada.";
            break;
        case 'ids_invalidos':
            $mensagemErro = "❌ IDs de requerimentos inválidos.";
            break;
        case 'erro_acao':
            $mensagemErro = "❌ Erro ao executar ação: " . ($_GET['details'] ?? 'Erro desconhecido');
            break;
    }
}

// Construir consulta SQL otimizada
$sql = "SELECT r.id, r.protocolo, r.tipo_alvara, r.status, r.data_envio, r.visualizado, 
               req.nome as requerente 
        FROM requerimentos r
        JOIN requerentes req ON r.requerente_id = req.id
        WHERE 1=1";

$sqlCount = "SELECT COUNT(*) as total FROM requerimentos r
             JOIN requerentes req ON r.requerente_id = req.id
             WHERE 1=1";

$params = [];

// Aplicar filtros
if (!empty($filtroStatus)) {
    $sql .= " AND r.status = ?";
    $sqlCount .= " AND r.status = ?";
    $params[] = $filtroStatus;
}

if (!empty($filtroTipo)) {
    $sql .= " AND r.tipo_alvara = ?";
    $sqlCount .= " AND r.tipo_alvara = ?";
    $params[] = $filtroTipo;
}

if (!empty($filtroBusca)) {
    $sql .= " AND (r.protocolo LIKE ? OR req.nome LIKE ? OR req.cpf_cnpj LIKE ?)";
    $sqlCount .= " AND (r.protocolo LIKE ? OR req.nome LIKE ? OR req.cpf_cnpj LIKE ?)";
    $termoBusca = "%$filtroBusca%";
    $params[] = $termoBusca;
    $params[] = $termoBusca;
    $params[] = $termoBusca;
}

if ($filtroNaoVisualizados) {
    $sql .= " AND r.visualizado = 0";
    $sqlCount .= " AND r.visualizado = 0";
}

// Ordenação e paginação
$sql .= " ORDER BY r.visualizado ASC, r.data_envio DESC LIMIT $offset, $itensPorPagina";

// Executar consultas
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requerimentos = $stmt->fetchAll();

$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$totalRequerimentos = $stmtCount->fetch()['total'];

// Estatísticas gerais
$estatisticas = [
    'total' => $pdo->query("SELECT COUNT(*) FROM requerimentos")->fetchColumn(),
    'nao_lidos' => $pdo->query("SELECT COUNT(*) FROM requerimentos WHERE visualizado = 0")->fetchColumn(),
    'pendentes' => $pdo->query("SELECT COUNT(*) FROM requerimentos WHERE status = 'Pendente'")->fetchColumn(),
    'aprovados' => $pdo->query("SELECT COUNT(*) FROM requerimentos WHERE status = 'Aprovado'")->fetchColumn(),
    'finalizados' => $pdo->query("SELECT COUNT(*) FROM requerimentos WHERE status = 'Finalizado'")->fetchColumn()
];

// Listas para filtros
$tiposAlvara = $pdo->query("SELECT DISTINCT tipo_alvara FROM requerimentos ORDER BY tipo_alvara")->fetchAll();
$statusList = $pdo->query("SELECT DISTINCT status FROM requerimentos ORDER BY status")->fetchAll();

include 'header.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Requerimentos - SEMA Pau dos Ferros</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Simple DataTables -->
    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.3/dist/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.3/dist/umd/simple-datatables.js"></script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- Admin Styles -->
    <link rel="stylesheet" href="includes/admin-styles.css">
</head>

<body class="bg-gray-50 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 py-8">

        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">
                <i class="fas fa-file-alt text-blue-600 mr-3"></i>
                Gerenciamento de Requerimentos
            </h1>
            <p class="text-gray-600">Visualize e gerencie todos os requerimentos de alvará ambiental</p>
        </div>

        <!-- Mensagens -->
        <?php renderMensagens($mensagem, $mensagemErro); ?>

        <!-- Estatísticas -->
        <?php renderEstatisticas($estatisticas); ?>

        <!-- Filtros -->
        <?php renderFiltros($statusList, $tiposAlvara, $filtroStatus, $filtroTipo, $filtroBusca, $filtroNaoVisualizados); ?>

        <!-- Alertas -->
        <?php renderAlertas(); ?>

        <!-- Tabela de Requerimentos -->
        <div class="modern-table">
            <?php renderTabela($requerimentos); ?>
        </div>

        <!-- Context Menu -->
        <?php renderContextMenu(); ?>
    </div>

    <!-- Admin Scripts -->
    <script src="includes/admin-scripts.js"></script>
</body>

</html>

<?php include 'footer.php'; ?>