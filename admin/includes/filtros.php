<?php
// Seção de Filtros
function renderFiltros($statusList, $tiposAlvara, $filtroStatus, $filtroTipo, $filtroBusca, $filtroNaoVisualizados)
{
?>
    <div class="filter-section">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-filter mr-2 text-blue-500"></i>
            Filtros de Pesquisa
        </h3>

        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Buscar</label>
                <input type="text"
                    name="busca"
                    value="<?php echo htmlspecialchars($filtroBusca); ?>"
                    placeholder="Protocolo, nome ou CPF/CNPJ..."
                    class="filter-input w-full">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <select name="status" class="filter-input w-full">
                    <option value="">Todos os Status</option>
                    <?php foreach ($statusList as $status): ?>
                        <option value="<?php echo $status['status']; ?>" <?php echo $filtroStatus === $status['status'] ? 'selected' : ''; ?>>
                            <?php echo $status['status']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Alvará</label>
                <select name="tipo" class="filter-input w-full">
                    <option value="">Todos os Tipos</option>
                    <?php foreach ($tiposAlvara as $tipo): ?>
                        <option value="<?php echo $tipo['tipo_alvara']; ?>" <?php echo $filtroTipo === $tipo['tipo_alvara'] ? 'selected' : ''; ?>>
                            <?php echo ucfirst(str_replace('_', ' ', $tipo['tipo_alvara'])); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex flex-col justify-end">
                <div class="flex items-center mb-3">
                    <input type="checkbox"
                        name="nao_visualizados"
                        value="1"
                        <?php echo $filtroNaoVisualizados ? 'checked' : ''; ?>
                        class="w-4 h-4 text-blue-600 border-gray-300 rounded">
                    <label class="ml-2 text-sm text-gray-700">Apenas não visualizados</label>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="btn-primary flex-1">
                        <i class="fas fa-search mr-2"></i>Filtrar
                    </button>
                    <a href="requerimentos.php" class="btn-secondary flex-1 text-center">
                        <i class="fas fa-times mr-2"></i>Limpar
                    </a>
                </div>
            </div>
        </form>
    </div>
<?php } ?>