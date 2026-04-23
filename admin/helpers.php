<?php
/**
 * Funções utilitárias compartilhadas entre as páginas admin.
 */

if (!function_exists('formatarTempoEstatisticas')) {
    function formatarTempoEstatisticas($segundos) {
        if ($segundos === 0 || $segundos === null) return 'N/A';
        $dias    = floor($segundos / 86400);
        $horas   = floor(($segundos % 86400) / 3600);
        $minutos = floor(($segundos % 3600) / 60);

        $partes = [];
        if ($dias    > 0) $partes[] = "{$dias}d";
        if ($horas   > 0) $partes[] = "{$horas}h";
        if ($minutos > 0 && $dias == 0) $partes[] = "{$minutos}m";

        if (empty($partes)) return "< 1m";
        return implode(' ', $partes);
    }
}

if (!function_exists('adminStatusFluxoPrincipal')) {
    function adminStatusFluxoPrincipal(): array
    {
        return [
            'Em análise',
            'Aprovado',
            'Reprovado',
            'Pendente',
            'Aguardando boleto',
            'Boleto pago',
            'Cancelado',
            'Finalizado',
            'Indeferido',
        ];
    }
}

if (!function_exists('adminStatusFluxoExtra')) {
    function adminStatusFluxoExtra(): array
    {
        return [
            'Aguardando Fiscalização',
            'Apto a gerar alvará',
            'Alvará Emitido',
        ];
    }
}

if (!function_exists('adminStatusPermitidoParaOperacao')) {
    function adminStatusPermitidoParaOperacao(string $status): bool
    {
        return in_array($status, adminStatusFluxoPrincipal(), true);
    }
}

/**
 * Calcula os timestamps de cada etapa a partir do histórico de ações de um requerimento.
 *
 * Retorna um array com as chaves:
 *   tEnvio, tAnalise, tFiscalizacao, tSecretario, tConclusao
 * onde cada valor é um Unix timestamp ou null se não ocorreu.
 *
 * Mapeamento de ações reais gravadas em historico_acoes:
 *   tVisualizacao → "Visualizou o requerimento pela primeira vez"
 *   tPendente     → "Alterou status para 'Pendente'"
 *   tFiscalizacao → "Enviou processo para Fiscalização de Obras"
 *   tSecretario   → "Concluiu a vistoria técnica e enviou para o Secretário"
 *   tConclusao    → "Aprovou e Assinou o Alvará" | "Finalizado" | "Indeferido"
 *
 * @param array  $historico  Resultado de fetchAll() da tabela historico_acoes.
 * @param string $data_envio Data de envio do requerimento (campo data_envio).
 * @return array
 */
function calcularTemposEtapas(array $historico, string $data_envio): array
{
    $tEnvio        = strtotime($data_envio);
    $tVisualizacao = null;
    $tPendente     = null;
    $tFiscalizacao = null;
    $tSecretario   = null;
    $tConclusao    = null;

    foreach ($historico as $h) {
        $t   = strtotime($h['data_acao']);
        $aco = $h['acao'];

        // Primeira visualização
        if (stripos($aco, 'primeira vez') !== false) {
            if ($tVisualizacao === null || $t < $tVisualizacao) $tVisualizacao = $t;
        }

        // Mudança para Pendente (triagem: Em análise → Pendente)
        if (preg_match("/status para ['\"]?Pendente['\"]?/i", $aco)) {
            if ($tPendente === null || $t < $tPendente) $tPendente = $t;
        }

        // Enviado para fiscalização
        if (stripos($aco, 'Fiscalização') !== false) {
            if ($tFiscalizacao === null || $t < $tFiscalizacao) $tFiscalizacao = $t;
        }

        // Enviado para o secretário (fiscal concluiu vistoria)
        if (stripos($aco, 'Secretário') !== false || stripos($aco, 'vistoria técnica') !== false) {
            if ($tSecretario === null || $t < $tSecretario) $tSecretario = $t;
        }

        // Conclusão: alvará assinado, finalizado ou indeferido
        if (
            stripos($aco, 'Assinou o Alvará') !== false ||
            stripos($aco, 'Finalizado')        !== false ||
            stripos($aco, 'Indeferido')        !== false
        ) {
            if ($tConclusao === null || $t < $tConclusao) $tConclusao = $t;
        }
    }

    return compact('tEnvio', 'tVisualizacao', 'tPendente', 'tFiscalizacao', 'tSecretario', 'tConclusao');
}
