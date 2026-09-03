<?php
require_once '../conexao.php';
verificaLogin();
require_once __DIR__ . '/../../includes/fiscalizacao_obras_helpers.php';

$nivelAtual = $_SESSION['admin_nivel'] ?? 'operador';
$isAdmin = in_array($nivelAtual, ['admin', 'admin_geral'], true);
if ($nivelAtual !== 'fiscal' && !$isAdmin) {
    http_response_code(403);
    header('Location: ../index.php?error=sem_permissao');
    exit;
}

$filtroStatus = $_GET['status'] ?? '';
$filtroTipo = $_GET['tipo'] ?? '';

$notificacoes = listarNotificacoesObras($pdo, [
    'status' => $filtroStatus !== '' ? $filtroStatus : null,
    'tipo_documento' => $filtroTipo !== '' ? $filtroTipo : null,
]);

include '../header.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificações de Obras - SEMA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="max-w-6xl mx-auto px-4 py-8">

        <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                    <i class="fas fa-triangle-exclamation text-amber-600 mr-3"></i>
                    Notificações de Obras
                </h1>
                <p class="text-gray-600 mt-2">Notificações, autos de infração e embargos com prazo em dias úteis.</p>
            </div>
            <a href="nova.php" class="px-5 py-3 bg-amber-600 hover:bg-amber-700 text-white rounded-lg shadow font-medium transition-colors flex items-center gap-2 w-max">
                <i class="fas fa-plus"></i> Nova Notificação
            </a>
        </div>

        <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6 flex flex-wrap gap-4 items-end">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                <select name="status" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    <option value="">Todos</option>
                    <?php foreach (FISCALIZACAO_OBRAS_STATUS as $valor => $label): ?>
                        <option value="<?= htmlspecialchars($valor) ?>" <?= $filtroStatus === $valor ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Tipo de documento</label>
                <select name="tipo" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    <option value="">Todos</option>
                    <?php foreach (FISCALIZACAO_OBRAS_TIPOS as $valor => $label): ?>
                        <option value="<?= htmlspecialchars($valor) ?>" <?= $filtroTipo === $valor ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-lg text-sm font-medium">Filtrar</button>
            <?php if ($filtroStatus !== '' || $filtroTipo !== ''): ?>
                <a href="index.php" class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700">Limpar filtros</a>
            <?php endif; ?>
        </form>

        <?php if (empty($notificacoes)): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-10 text-center text-gray-500">
                <i class="fas fa-clipboard-check text-4xl mb-3 text-gray-300"></i>
                <p>Nenhuma notificação cadastrada com esses filtros.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($notificacoes as $n): ?>
                    <?php
                        $alerta = calcularAlertaPrazo($n['data_vencimento']);
                        $corBorda = [
                            'vencido' => 'border-red-300',
                            'atencao' => 'border-amber-300',
                            'ok' => 'border-green-300',
                            'indefinido' => 'border-gray-200',
                        ][$alerta['nivel']];
                        $corBadge = [
                            'vencido' => 'bg-red-100 text-red-700',
                            'atencao' => 'bg-amber-100 text-amber-700',
                            'ok' => 'bg-green-100 text-green-700',
                            'indefinido' => 'bg-gray-100 text-gray-600',
                        ][$alerta['nivel']];
                    ?>
                    <a href="visualizar.php?id=<?= (int) $n['id'] ?>" class="block bg-white rounded-xl shadow-sm border-2 <?= $corBorda ?> p-5 hover:shadow-md transition-shadow">
                        <div class="flex items-start justify-between mb-2">
                            <span class="text-xs font-semibold text-gray-500 uppercase"><?= htmlspecialchars(fiscalizacaoObrasTipoLabel($n['tipo_documento'])) ?></span>
                            <span class="text-xs font-bold px-2 py-1 rounded-full <?= $corBadge ?>"><?= htmlspecialchars($alerta['label']) ?></span>
                        </div>
                        <h3 class="font-semibold text-gray-900 mb-1">Nº <?= (int) $n['numero'] ?>/<?= (int) $n['ano'] ?></h3>
                        <p class="text-sm text-gray-700 mb-1 truncate"><?= htmlspecialchars($n['notificado_nome']) ?></p>
                        <p class="text-xs text-gray-500 mb-3 truncate"><?= htmlspecialchars($n['endereco'] ?? 'Endereço não informado') ?></p>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-gray-500"><?= htmlspecialchars(fiscalizacaoObrasStatusLabel($n['status'])) ?></span>
                            <span class="font-medium text-gray-700">
                                <?= $n['data_vencimento'] ? date('d/m/Y', strtotime($n['data_vencimento'])) : 'sem prazo' ?>
                            </span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php include '../footer.php'; ?>
