<?php
require_once 'conexao.php';
verificaLogin();

// Configurações e Paginação
$itensPorPagina = 25;
$paginaAtual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($paginaAtual - 1) * $itensPorPagina;

// Preparar filtros (Busca por nome infrator)
$filtroBusca = isset($_GET['busca']) ? $_GET['busca'] : '';
$filtroStatus = isset($_GET['status']) ? $_GET['status'] : '';

// Mensagens
$mensagem = '';
if (isset($_GET['success'])) {
    if ($_GET['success'] == 'registrada') $mensagem = "✅ Denúncia registrada com sucesso!";
    if ($_GET['success'] == 'atualizada') $mensagem = "✅ Denúncia atualizada com sucesso!";
    if ($_GET['success'] == 'excluida') $mensagem = "✅ Denúncia removida corretamente.";
}

$mensagemErro = '';
if (isset($_GET['error'])) {
    if ($_GET['error'] == 'criacao') $mensagemErro = "❌ Erro ao registrar denúncia.";
    if ($_GET['error'] == 'nao_encontrado') $mensagemErro = "❌ Denúncia não encontrada.";
}

// Query de Denúncias
$sql = "SELECT d.id, d.data_registro, d.infrator_nome, d.status, a.nome as responsavel 
        FROM denuncias d
        LEFT JOIN administradores a ON d.admin_id = a.id
        WHERE 1=1";

$sqlCount = "SELECT COUNT(*) as total FROM denuncias d WHERE 1=1";

$params = [];

if (!empty($filtroBusca)) {
    $sql .= " AND (d.infrator_nome LIKE ? OR d.infrator_cpf_cnpj LIKE ?)";
    $sqlCount .= " AND (d.infrator_nome LIKE ? OR d.infrator_cpf_cnpj LIKE ?)";
    $termoBusca = "%$filtroBusca%";
    $params[] = $termoBusca;
    $params[] = $termoBusca;
}

if (!empty($filtroStatus)) {
    $sql .= " AND d.status = ?";
    $sqlCount .= " AND d.status = ?";
    $params[] = $filtroStatus;
}

$sql .= " ORDER BY d.data_registro DESC LIMIT $offset, $itensPorPagina";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$denuncias = $stmt->fetchAll();

$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$totalDenuncias = $stmtCount->fetch()['total'];

include 'header.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Controle Interno - Denúncias SEMA</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .table-responsive::-webkit-scrollbar { height: 8px; }
        .table-responsive::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
        .table-responsive::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 4px; }
        .table-responsive::-webkit-scrollbar-thumb:hover { background: #a8a8a8; }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .status-pendente { background-color: #FEF3C7; color: #92400E; }
        .status-em-analise { background-color: #DBEAFE; color: #1E40AF; }
        .status-concluida { background-color: #D1FAE5; color: #065F46; }
    </style>
</head>

<body class="bg-gray-50 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 py-8">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                    <i class="fas fa-bullhorn text-red-600 mr-3"></i>
                    Denúncias
                </h1>
                <p class="text-gray-600 mt-1">Gerenciamento de autuações e infrações ambientais.</p>
            </div>
            <div>
                <a href="nova_denuncia.php" class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg shadow transition-colors font-medium">
                    <i class="fas fa-plus mr-2"></i> Registrar Denúncia
                </a>
            </div>
        </div>

        <?php if ($mensagem): ?>
            <div class="mb-6 p-4 rounded-lg bg-green-50 border border-green-200 text-green-700 flex items-center">
                <?php echo $mensagem; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($mensagemErro): ?>
            <div class="mb-6 p-4 rounded-lg bg-red-50 border border-red-200 text-red-700 flex items-center">
                <?php echo $mensagemErro; ?>
            </div>
        <?php endif; ?>

        <!-- Filtros -->
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 mb-6">
            <form method="GET" class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Buscar</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                        <input type="text" name="busca" value="<?php echo htmlspecialchars($filtroBusca); ?>" placeholder="Nome do infrator ou CPF/CNPJ..." class="pl-10 w-full rounded-lg border border-gray-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
                    </div>
                </div>
                <div class="w-full md:w-64">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 bg-white">
                        <option value="">Todos os status</option>
                        <option value="Pendente" <?php echo $filtroStatus == 'Pendente' ? 'selected' : ''; ?>>Pendente</option>
                        <option value="Em Análise" <?php echo $filtroStatus == 'Em Análise' ? 'selected' : ''; ?>>Em Análise</option>
                        <option value="Concluída" <?php echo $filtroStatus == 'Concluída' ? 'selected' : ''; ?>>Concluída</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="px-6 py-2 bg-gray-800 hover:bg-gray-900 text-white rounded-lg font-medium transition-colors">
                        Filtrar
                    </button>
                    <?php if (!empty($filtroBusca) || !empty($filtroStatus)): ?>
                        <a href="denuncias.php" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-medium transition-colors">
                            Limpar
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Tabela -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="table-responsive">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Infrator</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registrado Por</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (count($denuncias) > 0): ?>
                            <?php foreach ($denuncias as $denuncia): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('d/m/Y H:i', strtotime($denuncia['data_registro'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($denuncia['infrator_nome']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($denuncia['responsavel'] ?? 'Sistema'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php 
                                            $classeBadge = 'status-pendente';
                                            if ($denuncia['status'] == 'Em Análise') $classeBadge = 'status-em-analise';
                                            if ($denuncia['status'] == 'Concluída') $classeBadge = 'status-concluida';
                                        ?>
                                        <span class="status-badge <?php echo $classeBadge; ?>">
                                            <?php echo htmlspecialchars($denuncia['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="visualizar_denuncia.php?id=<?php echo $denuncia['id']; ?>" class="text-blue-600 hover:text-blue-900 bg-blue-50 hover:bg-blue-100 px-3 py-1 rounded-md transition-colors inline-block">
                                            <i class="fas fa-eye mr-1"></i> Ver Mais
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-gray-500">
                                    <i class="fas fa-info-circle text-4xl text-gray-300 mb-3 block"></i>
                                    <p class="text-lg">Nenhuma denúncia encontrada.</p>
                                    <p class="text-sm">Clique em "Registrar Denúncia" para adicionar o primeiro caso interno.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($totalDenuncias > $itensPorPagina): ?>
            <!-- Paginação simples -->
            <div class="bg-white px-4 py-3 border-t border-gray-200 flex items-center justify-between sm:px-6">
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Mostrando <span class="font-medium"><?php echo $offset + 1; ?></span> a <span class="font-medium"><?php echo min($offset + $itensPorPagina, $totalDenuncias); ?></span> de <span class="font-medium"><?php echo $totalDenuncias; ?></span> resultados
                        </p>
                    </div>
                    <!-- (Lógica de botões de prve/next iria aqui num cenário mais extenso) -->
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php include 'footer.php'; ?>
