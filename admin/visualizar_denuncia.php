<?php
require_once 'conexao.php';
verificaLogin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: denuncias.php?error=nao_encontrado");
    exit;
}

// Buscar dados da Denúncia
$stmt = $pdo->prepare("SELECT d.*, a.nome as responsavel 
                       FROM denuncias d 
                       LEFT JOIN administradores a ON d.admin_id = a.id 
                       WHERE d.id = ?");
$stmt->execute([$id]);
$denuncia = $stmt->fetch();

if (!$denuncia) {
    header("Location: denuncias.php?error=nao_encontrado");
    exit;
}

// Buscar Anexos
$stmtAnexos = $pdo->prepare("SELECT * FROM denuncia_anexos WHERE denuncia_id = ? ORDER BY data_upload ASC");
$stmtAnexos->execute([$id]);
$anexos = $stmtAnexos->fetchAll();

// Mensagens
$mensagem = '';
if (isset($_GET['success'])) {
    if ($_GET['success'] == 'atualizada') $mensagem = "✅ Status atualizado com sucesso!";
}

include 'header.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes da Denúncia - SEMA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -5px;
            top: 6px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: #3b82f6; 
            border: 2px solid white;
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen">
    <div class="max-w-6xl mx-auto px-4 py-8">
        
        <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <a href="denuncias.php" class="text-blue-600 hover:text-blue-800 flex items-center mb-2 transition-colors w-max">
                    <i class="fas fa-arrow-left mr-2"></i> Voltar à Lista
                </a>
                <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                    Detalhes da Denúncia #<?php echo str_pad($denuncia['id'], 6, '0', STR_PAD_LEFT); ?>
                </h1>
            </div>
            
            <div class="flex items-center gap-3">
                <a href="documentos/selecionar_denuncia.php?denuncia_id=<?php echo $denuncia['id']; ?>"
                   class="bg-white border border-green-200 text-green-700 hover:bg-green-50 px-4 py-2 rounded-lg font-medium shadow-sm transition-colors">
                    <i class="fas fa-file-signature mr-2"></i> Gerar Documento
                </a>
                <form action="processar_denuncia.php" method="POST" class="d-inline" onsubmit="return confirm('ATENÇÃO: Esta ação é irreversível e excluirá todos os dados e arquivos anexados desta denúncia. Deseja continuar?')">
                    <input type="hidden" name="acao" value="excluir">
                    <input type="hidden" name="id" value="<?php echo $denuncia['id']; ?>">
                    <button type="submit" class="bg-white border border-red-200 text-red-600 hover:bg-red-50 px-4 py-2 rounded-lg font-medium shadow-sm transition-colors mr-2">
                        <i class="fas fa-trash-alt mr-2"></i> Excluir
                    </button>
                </form>
                <form action="processar_denuncia.php" method="POST" class="flex gap-2">
                    <input type="hidden" name="acao" value="alterar_status">
                    <input type="hidden" name="id" value="<?php echo $denuncia['id']; ?>">
                    <select name="status" class="rounded-lg border border-gray-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-red-500 bg-white">
                        <option value="Pendente" <?php echo $denuncia['status'] == 'Pendente' ? 'selected' : ''; ?>>Pendente</option>
                        <option value="Em Análise" <?php echo $denuncia['status'] == 'Em Análise' ? 'selected' : ''; ?>>Em Análise</option>
                        <option value="Concluída" <?php echo $denuncia['status'] == 'Concluída' ? 'selected' : ''; ?>>Concluída</option>
                    </select>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium shadow-sm transition-colors">
                        Atualizar Status
                    </button>
                </form>
            </div>
        </div>

        <?php if ($mensagem): ?>
            <div class="mb-6 p-4 rounded-lg bg-green-50 border border-green-200 text-green-700">
                <?php echo $mensagem; ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Coluna Principal (Dados) -->
            <div class="lg:col-span-2 space-y-6">
                
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                        <i class="fas fa-user-tag text-gray-400 mr-2"></i> Informações da Denúncia
                    </h3>
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <span class="block text-sm text-gray-500 mb-1">Nome/Razão Social:</span>
                                <span class="text-base font-medium text-gray-900"><?php echo htmlspecialchars($denuncia['infrator_nome']); ?></span>
                            </div>
                            <div>
                                <span class="block text-sm text-gray-500 mb-1">CPF/CNPJ:</span>
                                <span class="text-base font-medium text-gray-900"><?php echo htmlspecialchars($denuncia['infrator_cpf_cnpj'] ?: 'Não informado'); ?></span>
                            </div>
                        </div>
                        <div>
                            <span class="block text-sm text-gray-500 mb-1">Endereço da Ocorrência:</span>
                            <span class="text-base text-gray-800"><?php echo htmlspecialchars($denuncia['infrator_endereco'] ?: 'Não informado'); ?></span>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center text-red-600">
                        <i class="fas fa-exclamation-triangle mr-2"></i> Relato e Detalhes
                    </h3>
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <p class="text-gray-800 whitespace-pre-wrap leading-relaxed"><?php echo htmlspecialchars($denuncia['observacoes']); ?></p>
                    </div>
                </div>

                <!-- Histórico de Ações -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-6 pb-2 border-b border-gray-100 flex items-center">
                        <i class="fas fa-history text-gray-400 mr-2"></i> Histórico de Ações
                    </h3>
                    
                    <div class="relative">
                        <div class="absolute top-0 bottom-0 left-4 w-0.5 bg-gray-100"></div>
                        
                        <div class="space-y-8">
                            <?php 
                                $stmtHist = $pdo->prepare("SELECT h.*, a.nome as admin_nome FROM denuncia_historico h LEFT JOIN administradores a ON h.admin_id = a.id WHERE h.denuncia_id = ? ORDER BY h.data_registro DESC");
                                $stmtHist->execute([$id]);
                                $historico = $stmtHist->fetchAll();
                                
                                if (count($historico) > 0):
                                    foreach ($historico as $item):
                            ?>
                                <div class="relative pl-10">
                                    <div class="absolute left-2.5 top-1.5 w-3 h-3 rounded-full bg-blue-500 border-2 border-white shadow-sm"></div>
                                    <div class="flex flex-col md:flex-row md:justify-between mb-1">
                                        <span class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars($item['acao']); ?></span>
                                        <span class="text-xs text-gray-500"><?php echo date('d/m/Y H:i', strtotime($item['data_registro'])); ?></span>
                                    </div>
                                    <div class="text-sm text-gray-600"><?php echo htmlspecialchars($item['detalhes'] ?: 'Nenhum detalhe adicional.'); ?></div>
                                    <div class="text-xs text-blue-600 font-medium mt-1">Por: <?php echo htmlspecialchars($item['admin_nome'] ?: 'Sistema'); ?></div>
                                </div>
                            <?php 
                                    endforeach;
                                else:
                            ?>
                                <p class="text-sm text-gray-500 pl-10 italic">Nenhum histórico registrado.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Coluna Lateral (Infos e Anexos) -->
            <div class="space-y-6">
                
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                        <i class="fas fa-info-circle text-gray-400 mr-2"></i> Metadados
                    </h3>
                    <ul class="space-y-3">
                        <li class="flex justify-between items-center pb-2 border-b border-dashed border-gray-200">
                            <span class="text-sm text-gray-500">Status Atual:</span>
                            <?php 
                                $classeBadge = 'bg-yellow-100 text-yellow-800';
                                if ($denuncia['status'] == 'Em Análise') $classeBadge = 'bg-blue-100 text-blue-800';
                                if ($denuncia['status'] == 'Concluída') $classeBadge = 'bg-green-100 text-green-800';
                            ?>
                            <span class="px-3 py-1 text-xs font-semibold rounded-full <?php echo $classeBadge; ?>">
                                <?php echo $denuncia['status']; ?>
                            </span>
                        </li>
                        <li class="flex justify-between items-center pb-2 border-b border-dashed border-gray-200">
                            <span class="text-sm text-gray-500">Registrado em:</span>
                            <span class="text-sm font-medium text-gray-800"><?php echo date('d/m/Y H:i', strtotime($denuncia['data_registro'])); ?></span>
                        </li>
                        <li class="flex justify-between items-center">
                            <span class="text-sm text-gray-500">Por:</span>
                            <span class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($denuncia['responsavel']); ?></span>
                        </li>
                    </ul>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center justify-between">
                        <span><i class="fas fa-paperclip text-gray-400 mr-2"></i> Anexos</span>
                        <span class="bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded-full font-bold"><?php echo count($anexos); ?></span>
                    </h3>
                    
                    <?php if (count($anexos) > 0): ?>
                        <div class="space-y-3">
                            <?php foreach ($anexos as $anexo): ?>
                                <?php 
                                    $icone = 'fa-file';
                                    $cor = 'text-gray-500';
                                    $bg = 'bg-gray-50';
                                    if (in_array($anexo['tipo_arquivo'], ['jpg', 'jpeg', 'png'])) {
                                        $icone = 'fa-file-image'; $cor = 'text-blue-500'; $bg = 'bg-blue-50';
                                    } elseif ($anexo['tipo_arquivo'] == 'pdf') {
                                        $icone = 'fa-file-pdf'; $cor = 'text-red-500'; $bg = 'bg-red-50';
                                    } elseif (in_array($anexo['tipo_arquivo'], ['mp4', 'mov'])) {
                                        $icone = 'fa-file-video'; $cor = 'text-purple-500'; $bg = 'bg-purple-50';
                                    }
                                    
                                    // Determinar o URL completo
                                    $urlDownload = '../' . $anexo['caminho_arquivo'];
                                ?>
                                <a href="<?php echo htmlspecialchars($urlDownload); ?>" target="_blank" class="flex items-center p-3 <?php echo $bg; ?> rounded-lg border border-transparent hover:border-gray-200 transition-colors group">
                                    <div class="w-10 h-10 rounded-full bg-white flex items-center justify-center shadow-sm mr-3 flex-shrink-0">
                                        <i class="fas <?php echo $icone; ?> <?php echo $cor; ?> text-lg"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 truncate"><?php echo htmlspecialchars($anexo['nome_arquivo']); ?></p>
                                        <p class="text-xs text-gray-500 truncate"><?php echo date('d/m/y H:i', strtotime($anexo['data_upload'])); ?></p>
                                    </div>
                                    <div class="text-gray-400 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <i class="fas fa-external-link-alt text-sm"></i>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-6 bg-gray-50 rounded-lg border border-dashed border-gray-200">
                            <i class="fas fa-folder-open text-gray-300 text-3xl mb-2"></i>
                            <p class="text-sm text-gray-500">Nenhum anexo enviado.</p>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
        
    </div>
</body>
</html>
<?php include 'footer.php'; ?>
