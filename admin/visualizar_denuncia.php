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
    if ($_GET['success'] == 'atualizada') $mensagem = "✅ Andamento registrado com sucesso!";
    if ($_GET['success'] == 'editada')    $mensagem = "✅ Denúncia atualizada com sucesso!";
    if ($_GET['success'] == 'anexo')      $mensagem = "✅ Anexos atualizados com sucesso!";
}
$erroEdicao = '';
if (isset($_GET['error']) && $_GET['error'] == 'vazio') $erroEdicao = "⚠️ Nome e relato são obrigatórios.";

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
                <a href="exportar_denuncia.php?id=<?php echo $denuncia['id']; ?>" target="_blank"
                   class="bg-white border border-indigo-200 text-indigo-700 hover:bg-indigo-50 px-4 py-2 rounded-lg font-medium shadow-sm transition-colors">
                    <i class="fas fa-file-export mr-2"></i> Exportar Denúncia
                </a>
                <a href="documentos/selecionar_denuncia.php?denuncia_id=<?php echo $denuncia['id']; ?>"
                   class="bg-white border border-green-200 text-green-700 hover:bg-green-50 px-4 py-2 rounded-lg font-medium shadow-sm transition-colors">
                    <i class="fas fa-file-signature mr-2"></i> Gerar Documento
                </a>
                <button type="button" onclick="toggleEdicao()" id="btnEditar"
                        class="bg-white border border-blue-200 text-blue-600 hover:bg-blue-50 px-4 py-2 rounded-lg font-medium shadow-sm transition-colors">
                    <i class="fas fa-edit mr-2"></i> Editar
                </button>
                <form action="processar_denuncia.php" method="POST" class="d-inline" onsubmit="return confirm('ATENÇÃO: Esta ação é irreversível e excluirá todos os dados e arquivos anexados desta denúncia. Deseja continuar?')">
                    <input type="hidden" name="acao" value="excluir">
                    <input type="hidden" name="id" value="<?php echo $denuncia['id']; ?>">
                    <button type="submit" class="bg-white border border-red-200 text-red-600 hover:bg-red-50 px-4 py-2 rounded-lg font-medium shadow-sm transition-colors mr-2">
                        <i class="fas fa-trash-alt mr-2"></i> Excluir
                    </button>
                </form>
                <a href="#andamento"
                   class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium shadow-sm transition-colors">
                    <i class="fas fa-clipboard-check mr-2"></i> Registrar andamento
                </a>
            </div>
        </div>

        <?php if ($mensagem): ?>
            <div class="mb-6 p-4 rounded-lg bg-green-50 border border-green-200 text-green-700">
                <?php echo $mensagem; ?>
            </div>
        <?php endif; ?>
        <?php if ($erroEdicao): ?>
            <div class="mb-6 p-4 rounded-lg bg-red-50 border border-red-200 text-red-700">
                <?php echo $erroEdicao; ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Coluna Principal (Dados) -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Andamento e medidas: muda o status e registra o que foi feito -->
                <div id="andamento" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                        <i class="fas fa-clipboard-check text-blue-500 mr-2"></i> Registrar andamento
                    </h3>
                    <form action="processar_denuncia.php" method="POST" class="space-y-4">
                        <input type="hidden" name="acao" value="alterar_status">
                        <input type="hidden" name="id" value="<?php echo $denuncia['id']; ?>">

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select name="status" class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                                <option value="Pendente" <?php echo $denuncia['status'] == 'Pendente' ? 'selected' : ''; ?>>Pendente</option>
                                <option value="Em Análise" <?php echo $denuncia['status'] == 'Em Análise' ? 'selected' : ''; ?>>Em Análise</option>
                                <option value="Concluída" <?php echo $denuncia['status'] == 'Concluída' ? 'selected' : ''; ?>>Concluída</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Medidas tomadas <span class="font-normal text-gray-400">(o que foi feito nesta etapa)</span>
                            </label>
                            <textarea name="detalhes" rows="4"
                                      class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                      placeholder="Ex.: Vistoria realizada no local em 20/07. Constatada obra sem licença. Notificação emitida ao responsável, prazo de 15 dias."></textarea>
                        </div>

                        <label class="flex items-start gap-2 text-sm text-gray-700 cursor-pointer">
                            <input type="checkbox" name="visivel_denunciante" value="1" checked
                                   class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span>
                                <span class="font-medium">Mostrar ao denunciante</span>
                                <span class="block text-xs text-gray-500">Quando marcado, este andamento aparece para quem consultar o protocolo. Desmarque para deixar só interno.</span>
                            </span>
                        </label>

                        <div class="flex justify-end">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg font-medium shadow-sm transition-colors">
                                <i class="fas fa-save mr-2"></i> Salvar andamento
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Formulário de edição (agrupa os dois cards editáveis) -->
                <form id="formEdicao" action="processar_denuncia.php" method="POST">
                    <input type="hidden" name="acao" value="editar">
                    <input type="hidden" name="id" value="<?php echo $denuncia['id']; ?>">

                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center">
                        <i class="fas fa-user-tag text-gray-400 mr-2"></i> Informações da Denúncia
                    </h3>
                    <!-- Modo visualização -->
                    <div id="viewInfos" class="space-y-4">
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
                    <!-- Modo edição -->
                    <div id="editInfos" class="hidden space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nome/Razão Social <span class="text-red-500">*</span></label>
                                <input type="text" name="infrator_nome" required
                                       value="<?php echo htmlspecialchars($denuncia['infrator_nome']); ?>"
                                       class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">CPF/CNPJ</label>
                                <input type="text" name="infrator_cpf_cnpj"
                                       value="<?php echo htmlspecialchars($denuncia['infrator_cpf_cnpj']); ?>"
                                       class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Endereço da Ocorrência</label>
                                <input type="text" name="infrator_endereco"
                                       value="<?php echo htmlspecialchars($denuncia['infrator_endereco']); ?>"
                                       class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center text-red-600">
                        <i class="fas fa-exclamation-triangle mr-2"></i> Relato e Detalhes
                    </h3>
                    <!-- Modo visualização -->
                    <div id="viewRelato">
                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                            <p class="text-gray-800 whitespace-pre-wrap leading-relaxed"><?php echo htmlspecialchars($denuncia['observacoes']); ?></p>
                        </div>
                    </div>
                    <!-- Modo edição -->
                    <div id="editRelato" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Relato e Observações <span class="text-red-500">*</span></label>
                        <textarea name="observacoes" rows="6" required
                                  class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"><?php echo htmlspecialchars($denuncia['observacoes']); ?></textarea>
                    </div>
                </div>

                <!-- Botões salvar/cancelar (apenas no modo edição) -->
                <div id="botoesEdicao" class="hidden bg-white rounded-xl shadow-sm border border-blue-100 p-4 flex justify-end gap-3">
                    <button type="button" onclick="toggleEdicao()"
                            class="px-5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-medium transition-colors">
                        <i class="fas fa-times mr-2"></i> Cancelar
                    </button>
                    <button type="submit"
                            class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium shadow-sm transition-colors">
                        <i class="fas fa-save mr-2"></i> Salvar Alterações
                    </button>
                </div>

                </form>

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
                                    <div class="text-sm text-gray-600 whitespace-pre-wrap"><?php echo htmlspecialchars($item['detalhes'] ?: 'Nenhum detalhe adicional.'); ?></div>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="text-xs text-blue-600 font-medium">Por: <?php echo htmlspecialchars($item['admin_nome'] ?: 'Sistema'); ?></span>
                                        <?php if (!empty($item['visivel_denunciante'])): ?>
                                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-green-100 text-green-700"><i class="fas fa-eye mr-1"></i>Visível ao denunciante</span>
                                        <?php else: ?>
                                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-gray-100 text-gray-500"><i class="fas fa-eye-slash mr-1"></i>Interno</span>
                                        <?php endif; ?>
                                    </div>
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

                <div id="anexos" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
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
                                    $urlDownload = '../' . $anexo['caminho_arquivo'];
                                    $doFiscal    = ($anexo['origem'] ?? 'denunciante') === 'fiscal';
                                    $visivel     = !empty($anexo['visivel_denunciante']);
                                ?>
                                <div class="p-3 <?php echo $bg; ?> rounded-lg border border-transparent hover:border-gray-200 transition-colors">
                                    <div class="flex items-center">
                                        <a href="<?php echo htmlspecialchars($urlDownload); ?>" target="_blank" class="flex items-center flex-1 min-w-0 group">
                                            <div class="w-10 h-10 rounded-full bg-white flex items-center justify-center shadow-sm mr-3 flex-shrink-0">
                                                <i class="fas <?php echo $icone; ?> <?php echo $cor; ?> text-lg"></i>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-gray-900 truncate"><?php echo htmlspecialchars($anexo['nome_arquivo']); ?></p>
                                                <p class="text-xs text-gray-500 truncate">
                                                    <?php echo date('d/m/y H:i', strtotime($anexo['data_upload'])); ?>
                                                    · <?php echo $doFiscal ? 'Fiscalização' : 'Denunciante'; ?>
                                                </p>
                                            </div>
                                        </a>
                                        <span class="ml-2 flex-shrink-0 text-xs font-semibold px-2 py-1 rounded-full <?php echo $visivel ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600'; ?>">
                                            <i class="fas <?php echo $visivel ? 'fa-eye' : 'fa-eye-slash'; ?> mr-1"></i><?php echo $visivel ? 'Visível' : 'Interno'; ?>
                                        </span>
                                    </div>
                                    <form action="processar_denuncia.php" method="POST" class="mt-2 text-right">
                                        <input type="hidden" name="acao" value="toggle_anexo_visivel">
                                        <input type="hidden" name="id" value="<?php echo $denuncia['id']; ?>">
                                        <input type="hidden" name="anexo_id" value="<?php echo $anexo['id']; ?>">
                                        <button type="submit" class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                                            <?php echo $visivel ? 'Tornar interno' : 'Mostrar ao denunciante'; ?>
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-6 bg-gray-50 rounded-lg border border-dashed border-gray-200">
                            <i class="fas fa-folder-open text-gray-300 text-3xl mb-2"></i>
                            <p class="text-sm text-gray-500">Nenhum anexo enviado.</p>
                        </div>
                    <?php endif; ?>

                    <!-- Upload pela fiscalização -->
                    <form action="processar_denuncia.php" method="POST" enctype="multipart/form-data" class="mt-4 pt-4 border-t border-gray-100 space-y-3">
                        <input type="hidden" name="acao" value="adicionar_anexo">
                        <input type="hidden" name="id" value="<?php echo $denuncia['id']; ?>">
                        <label class="block text-sm font-medium text-gray-700">Adicionar arquivos (fotos, documentos)</label>
                        <input type="file" name="anexos[]" multiple accept=".jpg,.jpeg,.png,.pdf,.mp4,.mov"
                               class="block w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
                        <p class="text-xs text-gray-400">JPG, PNG, PDF, MP4 ou MOV — até 10MB cada.</p>
                        <label class="flex items-start gap-2 text-sm text-gray-700 cursor-pointer">
                            <input type="checkbox" name="visivel_denunciante" value="1"
                                   class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span>
                                <span class="font-medium">Mostrar ao denunciante</span>
                                <span class="block text-xs text-gray-500">Marque para fotos que o denunciante pode ver. Deixe desmarcado para documentos internos (ex.: notificação com dados do infrator).</span>
                            </span>
                        </label>
                        <button type="submit" class="w-full bg-gray-800 hover:bg-gray-900 text-white px-4 py-2 rounded-lg font-medium shadow-sm transition-colors">
                            <i class="fas fa-upload mr-2"></i> Enviar anexos
                        </button>
                    </form>
                </div>

                <?php
                // Documentos gerados (PDFs assinados digitalmente)
                $pastaDocsDenuncia = __DIR__ . '/pareceres_denuncia/' . $denuncia['id'] . '/';
                $docsPdf = [];
                if (is_dir($pastaDocsDenuncia)) {
                    $arquivos = glob($pastaDocsDenuncia . '*.pdf');
                    foreach ($arquivos as $arq) {
                        $docsPdf[] = [
                            'nome' => basename($arq),
                            'data' => filemtime($arq),
                            'url'  => 'pareceres_denuncia/' . $denuncia['id'] . '/' . basename($arq),
                        ];
                    }
                    usort($docsPdf, fn($a,$b) => $b['data'] - $a['data']);
                }
                ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100 flex items-center justify-between">
                        <span><i class="fas fa-file-signature text-gray-400 mr-2"></i> Documentos Gerados</span>
                        <span class="bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded-full font-bold"><?php echo count($docsPdf); ?></span>
                    </h3>

                    <?php if (count($docsPdf) > 0): ?>
                        <div class="space-y-3">
                            <?php foreach ($docsPdf as $doc):
                                // Extrair label legível do nome do arquivo
                                $label = $doc['nome'];
                                $label = preg_replace('/_DEN\d+_\d+\.pdf$/i', '', $label);
                                $label = str_replace('_', ' ', $label);
                            ?>
                                <a href="<?php echo htmlspecialchars($doc['url']); ?>" target="_blank"
                                   class="flex items-center p-3 bg-red-50 rounded-lg border border-transparent hover:border-red-200 transition-colors group">
                                    <div class="w-10 h-10 rounded-full bg-white flex items-center justify-center shadow-sm mr-3 flex-shrink-0">
                                        <i class="fas fa-file-pdf text-red-500 text-lg"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 truncate"><?php echo htmlspecialchars($label); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo date('d/m/Y H:i', $doc['data']); ?></p>
                                    </div>
                                    <div class="text-gray-400 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <i class="fas fa-download text-sm"></i>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-6 bg-gray-50 rounded-lg border border-dashed border-gray-200">
                            <i class="fas fa-file-signature text-gray-300 text-3xl mb-2"></i>
                            <p class="text-sm text-gray-500">Nenhum documento gerado ainda.</p>
                            <a href="documentos/selecionar_denuncia.php?denuncia_id=<?php echo $denuncia['id']; ?>"
                               class="text-xs text-green-600 hover:text-green-700 font-medium mt-1 inline-block">
                                Gerar documento →
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>

    </div>
    <script>
        function toggleEdicao() {
            const editando = document.getElementById('editInfos').classList.contains('hidden');

            document.getElementById('viewInfos').classList.toggle('hidden', editando);
            document.getElementById('editInfos').classList.toggle('hidden', !editando);
            document.getElementById('viewRelato').classList.toggle('hidden', editando);
            document.getElementById('editRelato').classList.toggle('hidden', !editando);
            document.getElementById('botoesEdicao').classList.toggle('hidden', !editando);

            const btn = document.getElementById('btnEditar');
            if (editando) {
                btn.innerHTML = '<i class="fas fa-times mr-2"></i> Cancelar';
                btn.classList.replace('border-blue-200', 'border-gray-200');
                btn.classList.replace('text-blue-600', 'text-gray-600');
                btn.classList.replace('hover:bg-blue-50', 'hover:bg-gray-50');
            } else {
                btn.innerHTML = '<i class="fas fa-edit mr-2"></i> Editar';
                btn.classList.replace('border-gray-200', 'border-blue-200');
                btn.classList.replace('text-gray-600', 'text-blue-600');
                btn.classList.replace('hover:bg-gray-50', 'hover:bg-blue-50');
            }
        }
    </script>
</body>
</html>
<?php include 'footer.php'; ?>
