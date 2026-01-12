<?php
function renderContextMenu()
{
?>
    <!-- Context Menu -->
    <div id="contextMenu" class="context-menu">
        <button class="context-menu-item" onclick="abrirRequerimentoContext()">
            <i class="fas fa-eye"></i>
            Visualizar
        </button>

        <div class="context-menu-separator"></div>

        <button class="context-menu-item" onclick="ativarModoSelecao()">
            <i class="fas fa-check-square"></i>
            Selecionar Múltiplos
        </button>

        <div class="context-menu-separator"></div>

        <button class="context-menu-item" onclick="alterarStatusContext('Finalizado')">
            <i class="fas fa-check"></i>
            Finalizar
        </button>

        <button class="context-menu-item" onclick="alterarStatusContext('Indeferido')">
            <i class="fas fa-ban"></i>
            Indeferir
        </button>

        <div class="context-menu-sub">
            <button class="context-menu-item context-menu-sub-trigger">
                <i class="fas fa-edit"></i>
                Alterar Status
            </button>
            <div class="context-menu-sub-content">
                <button class="context-menu-item" onclick="alterarStatusContext('Em análise')">
                    <span class="w-2 h-2 rounded-full bg-blue-500 mr-2"></span>
                    Em análise
                </button>
                <button class="context-menu-item" onclick="alterarStatusContext('Aprovado')">
                    <span class="w-2 h-2 rounded-full bg-green-500 mr-2"></span>
                    Aprovado
                </button>
                <button class="context-menu-item" onclick="alterarStatusContext('Reprovado')">
                    <span class="w-2 h-2 rounded-full bg-red-500 mr-2"></span>
                    Reprovado
                </button>
                <button class="context-menu-item" onclick="alterarStatusContext('Pendente')">
                    <span class="w-2 h-2 rounded-full bg-yellow-500 mr-2"></span>
                    Pendente
                </button>
                <button class="context-menu-item" onclick="alterarStatusContext('Cancelado')">
                    <span class="w-2 h-2 rounded-full bg-gray-500 mr-2"></span>
                    Cancelado
                </button>
            </div>
        </div>

        <div class="context-menu-separator"></div>

        <button class="context-menu-item" onclick="marcarComoLidoContext()">
            <i class="fas fa-eye"></i>
            Marcar como Lido
        </button>

        <button class="context-menu-item destructive" onclick="confirmarExclusaoContext()">
            <i class="fas fa-trash"></i>
            Excluir
        </button>
    </div>
<?php
}
?>