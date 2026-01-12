<?php
// Função para renderizar mensagens
function renderMensagens($mensagem, $mensagemErro)
{
    if (!empty($mensagem)) {
        echo '<div class="success-message">
                <i class="fas fa-check-circle mr-2"></i>
                ' . $mensagem . '
              </div>';
    }

    if (!empty($mensagemErro)) {
        echo '<div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6 animation-slideIn">
                <i class="fas fa-exclamation-circle mr-2"></i>
                ' . $mensagemErro . '
              </div>';
    }
}

// Função para renderizar alertas
function renderAlertas()
{
?>
    <!-- Alerta Informativo -->
    <div id="alertaInformativo" class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-6" style="display: none;">
        <div class="flex items-start justify-between">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-amber-500 text-lg"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-amber-800">
                        <span class="font-medium">Atenção:</span>
                        Os protocolos que foram finalizados antes da atualização do sistema não estarão marcados automaticamente como "Finalizado" ou "Indeferido".
                        Para uma melhor organização, recomenda-se atualizar manualmente o status destes protocolos conforme necessário.
                    </p>
                    <div class="mt-2 pt-2 border-t border-amber-200">
                        <p class="text-xs text-amber-700">
                            <span class="font-medium">💡 Dica:</span>
                            Use o <strong>botão direito</strong> sobre qualquer protocolo para ações rápidas, ou ative a <strong>seleção múltipla</strong> para alterações em massa de status.
                        </p>
                    </div>
                </div>
            </div>
            <div class="flex-shrink-0 ml-4">
                <button
                    onclick="fecharAlerta()"
                    class="bg-amber-100 hover:bg-amber-200 text-amber-800 text-xs font-medium px-3 py-1 rounded transition-colors duration-200">
                    OK
                </button>
            </div>
        </div>
    </div>

    <!-- Alerta Modo Seleção Múltipla -->
    <div id="alertaModoSelecao" class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6" style="display: none;">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <i class="fas fa-info-circle text-blue-500 text-lg"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm text-blue-800">
                    <span class="font-medium">Modo de Seleção Múltipla Ativo:</span>
                    Clique nos protocolos para selecioná-los ou use os checkboxes. Use a barra de ações no topo para executar ações em massa.
                </p>
            </div>
        </div>
    </div>
<?php
}
?>