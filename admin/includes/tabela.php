<?php
// Função para gerar a cor do status
function getStatusColor($status)
{
    switch (strtolower($status)) {
        case 'pendente':
            return ['color' => '#f59e0b', 'textClass' => 'text-yellow-700'];
        case 'aprovado':
            return ['color' => '#10b981', 'textClass' => 'text-green-700'];
        case 'finalizado':
        case 'indeferido':
            return ['color' => '#6b7280', 'textClass' => 'text-gray-500'];
        case 'reprovado':
            return ['color' => '#ef4444', 'textClass' => 'text-red-700'];
        case 'em análise':
        case 'em_analise':
            return ['color' => '#3b82f6', 'textClass' => 'text-blue-700'];
        case 'cancelado':
            return ['color' => '#6b7280', 'textClass' => 'text-gray-500'];
        default:
            return ['color' => '#6b7280', 'textClass' => 'text-gray-500'];
    }
}

// Função para renderizar a tabela
function renderTabela($requerimentos)
{
    if (count($requerimentos) === 0) {
        renderTabelaVazia();
        return;
    }
?>
    <!-- Barra de Ações Múltiplas (oculta por padrão) -->
    <div id="acoesMultiplas" class="bg-blue-50 border-b border-blue-200 px-6 py-4" style="display: none;">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <span id="contadorSelecionados" class="text-sm text-blue-800 mr-4">0 itens selecionados</span>
                <button onclick="cancelarSelecaoMultipla()" class="text-sm text-blue-600 hover:text-blue-800">
                    <i class="fas fa-times mr-1"></i>Cancelar
                </button>
            </div>
            <div class="flex gap-2">
                <!-- Dropdown para alterar status -->
                <div class="relative">
                    <button onclick="toggleDropdownStatus()" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm flex items-center">
                        <i class="fas fa-edit mr-1"></i>Alterar Status
                        <i class="fas fa-chevron-down ml-1 text-xs"></i>
                    </button>
                    <div id="dropdownStatus" class="absolute top-full left-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg min-w-40 z-10" style="display: none;">
                        <button onclick="alterarStatusMultiplo('Em análise')" class="w-full text-left px-3 py-2 hover:bg-gray-50 text-sm flex items-center">
                            <span class="w-2 h-2 rounded-full bg-blue-500 mr-2"></span>Em análise
                        </button>
                        <button onclick="alterarStatusMultiplo('Aprovado')" class="w-full text-left px-3 py-2 hover:bg-gray-50 text-sm flex items-center">
                            <span class="w-2 h-2 rounded-full bg-green-500 mr-2"></span>Aprovado
                        </button>
                        <button onclick="alterarStatusMultiplo('Pendente')" class="w-full text-left px-3 py-2 hover:bg-gray-50 text-sm flex items-center">
                            <span class="w-2 h-2 rounded-full bg-yellow-500 mr-2"></span>Pendente
                        </button>
                        <button onclick="alterarStatusMultiplo('Reprovado')" class="w-full text-left px-3 py-2 hover:bg-gray-50 text-sm flex items-center">
                            <span class="w-2 h-2 rounded-full bg-red-500 mr-2"></span>Reprovado
                        </button>
                        <button onclick="alterarStatusMultiplo('Finalizado')" class="w-full text-left px-3 py-2 hover:bg-gray-50 text-sm flex items-center">
                            <span class="w-2 h-2 rounded-full bg-gray-500 mr-2"></span>Finalizado
                        </button>
                        <button onclick="alterarStatusMultiplo('Indeferido')" class="w-full text-left px-3 py-2 hover:bg-gray-50 text-sm flex items-center">
                            <span class="w-2 h-2 rounded-full bg-gray-500 mr-2"></span>Indeferido
                        </button>
                        <button onclick="alterarStatusMultiplo('Cancelado')" class="w-full text-left px-3 py-2 hover:bg-gray-50 text-sm flex items-center">
                            <span class="w-2 h-2 rounded-full bg-gray-500 mr-2"></span>Cancelado
                        </button>
                    </div>
                </div>

                <button onclick="confirmarExclusaoMultipla()" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm">
                    <i class="fas fa-trash mr-1"></i>Excluir
                </button>
            </div>
        </div>
    </div>

    <table id="requerimentosTable" class="w-full">
        <thead>
            <tr>
                <th class="text-left w-12">
                    <!-- Checkbox para seleção múltipla (oculto por padrão) -->
                </th>
                <th class="text-left">Protocolo</th>
                <th class="text-left">Requerente</th>
                <th class="text-left">Tipo de Alvará</th>
                <th class="text-left">Status</th>
                <th class="text-left">Data de Envio</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($requerimentos as $req): ?>
                <?php
                $isCompleted = (strtolower($req['status']) === 'finalizado' || strtolower($req['status']) === 'indeferido');
                $rowClasses = '';
                $rowClasses .= $req['visualizado'] == 0 ? 'unread ' : '';
                $rowClasses .= $isCompleted ? 'opacity-60 ' : '';
                $rowClasses .= 'transition-all hover:bg-gray-50 requerimento-row';
                $statusData = getStatusColor($req['status']);
                ?>
                <tr class="<?php echo $rowClasses; ?>"
                    data-id="<?php echo $req['id']; ?>"
                    oncontextmenu="showContextMenu(event, <?php echo $req['id']; ?>); return false;">

                    <!-- Checkbox para seleção múltipla (oculto por padrão) -->
                    <td class="text-center">
                        <input type="checkbox"
                            class="checkbox-selecao w-4 h-4 text-blue-600 border-gray-300 rounded"
                            data-id="<?php echo $req['id']; ?>"
                            onchange="updateContadorSelecionados()"
                            style="display: none;">
                    </td>

                    <td class="font-medium text-gray-900 cursor-pointer" onclick="abrirRequerimento(<?php echo $req['id']; ?>)">
                        <div class="flex items-center">
                            <?php if ($req['visualizado'] == 0): ?>
                                <span class="status-indicator" title="Não visualizado"></span>
                            <?php endif; ?>
                            <span><?php echo $req['protocolo']; ?></span>
                        </div>
                    </td>

                    <td class="text-gray-900 cursor-pointer" onclick="abrirRequerimento(<?php echo $req['id']; ?>)">
                        <?php echo $req['requerente']; ?>
                    </td>

                    <td class="cursor-pointer" onclick="abrirRequerimento(<?php echo $req['id']; ?>)">
                        <span class="type-badge">
                            <?php echo ucfirst(str_replace('_', ' ', $req['tipo_alvara'])); ?>
                        </span>
                    </td>

                    <td class="cursor-pointer" onclick="abrirRequerimento(<?php echo $req['id']; ?>)">
                        <div class="flex items-center">
                            <span class="w-2 h-2 rounded-full mr-2" style="background-color: <?php echo $statusData['color']; ?>"></span>
                            <span class="text-sm <?php echo $statusData['textClass']; ?> font-medium"><?php echo $req['status']; ?></span>
                        </div>
                    </td>

                    <td class="text-gray-600 cursor-pointer" onclick="abrirRequerimento(<?php echo $req['id']; ?>)">
                        <?php echo formataDataBR($req['data_envio']); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php
}

function renderTabelaVazia()
{
?>
    <div class="text-center py-16">
        <div class="text-6xl text-gray-300 mb-4">
            <i class="fas fa-search"></i>
        </div>
        <h4 class="text-xl font-semibold text-gray-700 mb-2">Nenhum requerimento encontrado</h4>
        <p class="text-gray-500">Tente ajustar os filtros de pesquisa ou verificar se há requerimentos cadastrados.</p>
    </div>
<?php
}
?>