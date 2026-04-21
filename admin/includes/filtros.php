<?php
// Seção de Filtros
function renderFiltros($statusList, $tiposAlvara, $filtroStatus, $filtroTipo, $filtroBusca, $filtroNaoVisualizados)
{
?>
    <section class="filter-section">
        <div class="filter-head">
            <div>
                <h3 class="filter-title">
                    <i class="fas fa-filter"></i>
                    Filtros de Pesquisa
                </h3>
                <p class="filter-subtitle">Refine a fila por status, tipo, termo livre e leitura pendente.</p>
            </div>
        </div>

        <form method="GET" class="filter-grid">
            <div class="filter-field">
                <label class="filter-label">Buscar</label>
                <input type="text"
                    name="busca"
                    value="<?php echo htmlspecialchars($filtroBusca); ?>"
                    placeholder="Protocolo, nome ou CPF/CNPJ..."
                    class="filter-input w-full">
            </div>

            <div class="filter-field">
                <label class="filter-label">Status</label>
                <select name="status" class="filter-input w-full">
                    <option value="">Todos os Status</option>
                    <?php foreach ($statusList as $status): ?>
                        <option value="<?php echo $status['status']; ?>" <?php echo $filtroStatus === $status['status'] ? 'selected' : ''; ?>>
                            <?php echo $status['status']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-field">
                <label class="filter-label">Tipo de Alvará</label>
                <select name="tipo" class="filter-input w-full">
                    <option value="">Todos os Tipos</option>
                    <?php foreach ($tiposAlvara as $tipo): ?>
                        <option value="<?php echo $tipo['tipo_alvara']; ?>" <?php echo $filtroTipo === $tipo['tipo_alvara'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(nomeAlvara($tipo['tipo_alvara'])); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-actions">
                <div class="filter-checkbox">
                    <input type="checkbox"
                        name="nao_visualizados"
                        value="1"
                        <?php echo $filtroNaoVisualizados ? 'checked' : ''; ?>
                        class="w-4 h-4 text-blue-600 border-gray-300 rounded">
                    <label class="filter-check-label">Apenas não visualizados</label>
                </div>
                <div class="filter-button-row">
                    <button type="submit" class="btn-primary flex-1">
                        <i class="fas fa-search mr-2"></i>Filtrar
                    </button>
                    <a href="requerimentos.php" class="btn-secondary flex-1 text-center">
                        <i class="fas fa-times mr-2"></i>Limpar
                    </a>
                </div>
            </div>
        </form>
    </section>
<?php } ?>
