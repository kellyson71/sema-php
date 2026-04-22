<?php
// Estatísticas
function renderEstatisticas($estatisticas)
{
?>
    <section class="stats-grid">
        <article class="stat-card stat-card-primary">
            <div class="stat-copy">
                <span class="stat-label">Total de requerimentos</span>
                <strong class="stat-value"><?php echo number_format($estatisticas['total']); ?></strong>
                <span class="stat-caption">Volume completo da base administrativa.</span>
            </div>
            <span class="stat-icon">
                <i class="fas fa-file-alt"></i>
            </span>
        </article>

        <article class="stat-card stat-card-info">
            <div class="stat-copy">
                <span class="stat-label">Não visualizados</span>
                <strong class="stat-value"><?php echo number_format($estatisticas['nao_lidos']); ?></strong>
                <span class="stat-caption">Protocolos que ainda precisam entrar na leitura ativa.</span>
            </div>
            <span class="stat-icon">
                <i class="fas fa-bell"></i>
            </span>
        </article>

        <article class="stat-card stat-card-warning">
            <div class="stat-copy">
                <span class="stat-label">Pendentes</span>
                <strong class="stat-value"><?php echo number_format($estatisticas['pendentes']); ?></strong>
                <span class="stat-caption">Itens parados em alguma etapa de decisão.</span>
            </div>
            <span class="stat-icon">
                <i class="fas fa-clock"></i>
            </span>
        </article>

        <article class="stat-card stat-card-success">
            <div class="stat-copy">
                <span class="stat-label">Aprovados</span>
                <strong class="stat-value"><?php echo number_format($estatisticas['aprovados']); ?></strong>
                <span class="stat-caption">Processos liberados dentro do fluxo atual.</span>
            </div>
            <span class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </span>
        </article>

        <article class="stat-card stat-card-neutral">
            <div class="stat-copy">
                <span class="stat-label">Finalizados</span>
                <strong class="stat-value"><?php echo number_format($estatisticas['finalizados']); ?></strong>
                <span class="stat-caption">Protocolos encerrados ou concluídos.</span>
            </div>
            <span class="stat-icon">
                <i class="fas fa-flag-checkered"></i>
            </span>
        </article>
    </section>
<?php } ?>
