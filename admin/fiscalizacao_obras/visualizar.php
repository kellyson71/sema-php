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

$id = (int) ($_GET['id'] ?? 0);
$notificacao = $id > 0 ? buscarNotificacaoObras($pdo, $id) : null;
if (!$notificacao) {
    header('Location: index.php?error=nao_encontrada');
    exit;
}

$alerta = calcularAlertaPrazo($notificacao['data_vencimento']);
$artigosSelecionados = $notificacao['artigos_selecionados'] ? json_decode($notificacao['artigos_selecionados'], true) : [];
$artigosDisponiveis = fiscalizacaoObrasArtigosDoTipo($notificacao['tipo_documento']);

include '../header.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificação Nº <?= (int) $notificacao['numero'] ?>/<?= (int) $notificacao['ano'] ?> - SEMA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="max-w-3xl mx-auto px-4 py-8">

        <a href="index.php" class="text-amber-600 hover:text-amber-800 flex items-center mb-4 transition-colors w-max">
            <i class="fas fa-arrow-left mr-2"></i> Voltar
        </a>

        <?php if (isset($_GET['success'])): ?>
            <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">Ação realizada com sucesso.</div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">Não foi possível concluir a ação. (<?= htmlspecialchars($_GET['error']) ?>)</div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 mb-6">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <span class="text-xs font-semibold text-gray-500 uppercase"><?= htmlspecialchars(fiscalizacaoObrasTipoLabel($notificacao['tipo_documento'])) ?></span>
                    <h1 class="text-2xl font-bold text-gray-900">Nº <?= (int) $notificacao['numero'] ?>/<?= (int) $notificacao['ano'] ?></h1>
                </div>
                <span class="text-xs font-bold px-3 py-1.5 rounded-full <?= [
                    'vencido' => 'bg-red-100 text-red-700',
                    'atencao' => 'bg-amber-100 text-amber-700',
                    'ok' => 'bg-green-100 text-green-700',
                    'indefinido' => 'bg-gray-100 text-gray-600',
                ][$alerta['nivel']] ?>"><?= htmlspecialchars($alerta['label']) ?><?= $alerta['dias'] !== null ? ' (' . $alerta['dias'] . ' dias)' : '' ?></span>
            </div>

            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm mb-6">
                <div><dt class="text-gray-500">Notificado(a)</dt><dd class="font-medium text-gray-900"><?= htmlspecialchars($notificacao['notificado_nome']) ?></dd></div>
                <div><dt class="text-gray-500">CPF/CNPJ</dt><dd class="font-medium text-gray-900"><?= htmlspecialchars($notificacao['notificado_cpf_cnpj'] ?? 'Não informado') ?></dd></div>
                <div class="md:col-span-2"><dt class="text-gray-500">Endereço</dt><dd class="font-medium text-gray-900"><?= htmlspecialchars($notificacao['endereco'] ?? 'Não informado') ?> <?= $notificacao['bairro'] ? '- ' . htmlspecialchars($notificacao['bairro']) : '' ?></dd></div>
                <div><dt class="text-gray-500">Emissão</dt><dd class="font-medium text-gray-900"><?= date('d/m/Y', strtotime($notificacao['data_emissao'])) ?></dd></div>
                <div><dt class="text-gray-500">Vencimento</dt><dd class="font-medium text-gray-900"><?= $notificacao['data_vencimento'] ? date('d/m/Y', strtotime($notificacao['data_vencimento'])) : 'Sem prazo definido' ?></dd></div>
            </dl>

            <div class="mb-6">
                <dt class="text-gray-500 text-sm mb-1">Descrição do fato</dt>
                <dd class="text-gray-800 whitespace-pre-line"><?= htmlspecialchars($notificacao['descricao_fato']) ?></dd>
            </div>

            <?php if (!empty($artigosDisponiveis)): ?>
            <div class="mb-6">
                <dt class="text-gray-500 text-sm mb-2">Fundamentação legal marcada</dt>
                <ul class="text-sm text-gray-700 space-y-1">
                    <?php foreach ($artigosDisponiveis as $codigo => $texto): ?>
                        <?php if (in_array($codigo, (array) $artigosSelecionados, true)): ?>
                            <li>☑ <?= htmlspecialchars($texto) ?></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <div class="flex flex-wrap gap-3 pt-4 border-t border-gray-100">
                <?php if ($notificacao['origem'] === 'gerado_sistema' && empty($notificacao['documento_id'])): ?>
                    <form action="assinar_handler.php" method="POST">
                        <input type="hidden" name="id" value="<?= (int) $notificacao['id'] ?>">
                        <button type="submit" class="px-5 py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-lg font-medium text-sm">
                            <i class="fas fa-signature mr-2"></i>Assinar digitalmente
                        </button>
                    </form>
                <?php elseif (!empty($notificacao['documento_id'])): ?>
                    <a href="<?= rtrim(BASE_URL, '/') ?>/verificar?id=<?= urlencode($notificacao['documento_id']) ?>" target="_blank" class="px-5 py-2.5 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium text-sm">
                        <i class="fas fa-shield-halved mr-2"></i>Ver documento assinado
                    </a>
                <?php elseif (!empty($notificacao['pdf_upload_path'])): ?>
                    <a href="<?= rtrim(BASE_URL, '/') ?>/<?= htmlspecialchars($notificacao['pdf_upload_path']) ?>" target="_blank" class="px-5 py-2.5 bg-gray-700 hover:bg-gray-800 text-white rounded-lg font-medium text-sm">
                        <i class="fas fa-file-pdf mr-2"></i>Ver PDF anexado
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Status do processo</h3>
            <form action="atualizar_status_handler.php" method="POST" class="flex flex-col md:flex-row gap-3 items-start md:items-end">
                <input type="hidden" name="id" value="<?= (int) $notificacao['id'] ?>">
                <div class="flex-1 w-full">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                    <select name="status" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        <?php foreach (FISCALIZACAO_OBRAS_STATUS as $valor => $label): ?>
                            <option value="<?= htmlspecialchars($valor) ?>" <?= $notificacao['status'] === $valor ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex-1 w-full">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Observações</label>
                    <input type="text" name="observacoes" value="<?= htmlspecialchars($notificacao['observacoes'] ?? '') ?>" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                </div>
                <button type="submit" class="px-5 py-2.5 bg-gray-800 hover:bg-gray-900 text-white rounded-lg font-medium text-sm">Atualizar</button>
            </form>
        </div>
    </div>
</body>
</html>
<?php include '../footer.php'; ?>
