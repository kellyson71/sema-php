<?php
// Estatísticas
function renderEstatisticas($estatisticas)
{
?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
        <div class="stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Total de Requerimentos</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo number_format($estatisticas['total']); ?></p>
                </div>
                <div class="text-blue-600">
                    <i class="fas fa-file-alt text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Não Visualizados</p>
                    <p class="text-2xl font-bold text-blue-600"><?php echo number_format($estatisticas['nao_lidos']); ?></p>
                </div>
                <div class="text-blue-600">
                    <i class="fas fa-bell text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Pendentes</p>
                    <p class="text-2xl font-bold text-yellow-600"><?php echo number_format($estatisticas['pendentes']); ?></p>
                </div>
                <div class="text-yellow-600">
                    <i class="fas fa-clock text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Aprovados</p>
                    <p class="text-2xl font-bold text-green-600"><?php echo number_format($estatisticas['aprovados']); ?></p>
                </div>
                <div class="text-green-600">
                    <i class="fas fa-check-circle text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Finalizados</p>
                    <p class="text-2xl font-bold text-purple-600"><?php echo number_format($estatisticas['finalizados']); ?></p>
                </div>
                <div class="text-purple-600">
                    <i class="fas fa-flag-checkered text-2xl"></i>
                </div>
            </div>
        </div>
    </div>
<?php } ?>