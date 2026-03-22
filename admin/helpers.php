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

/**
 * Calcula os timestamps de cada etapa a partir do histórico de ações de um requerimento.
 *
 * Retorna um array com as chaves:
 *   t_envio, t_analise, t_aprovado, t_conclusao
 * onde cada valor é um Unix timestamp ou null se não ocorreu.
 *
 * @param array  $historico  Resultado de fetchAll() da tabela historico_acoes.
 * @param string $data_envio Data de envio do requerimento (campo data_envio).
 * @return array
 */
function calcularTemposEtapas(array $historico, string $data_envio): array
{
    $tEnvio     = strtotime($data_envio);
    $tAnalise   = null;
    $tAprovado  = null;
    $tConclusao = null;

    foreach ($historico as $h) {
        $t = strtotime($h['data_acao']);

        // Primeira ocorrência de entrada em análise
        if (stripos($h['acao'], 'Em análise') !== false) {
            if ($tAnalise === null || $t < $tAnalise) $tAnalise = $t;
        }

        // Primeira ocorrência de aprovação (evita pegar "Reprovado")
        if (preg_match('/\bAprovado\b/i', $h['acao'])) {
            if ($tAprovado === null || $t < $tAprovado) $tAprovado = $t;
        }

        // Primeira ocorrência de conclusão (Finalizado ou Indeferido)
        if (stripos($h['acao'], 'Finalizado') !== false || stripos($h['acao'], 'Indeferido') !== false) {
            if ($tConclusao === null || $t < $tConclusao) $tConclusao = $t;
        }
    }

    return compact('tEnvio', 'tAnalise', 'tAprovado', 'tConclusao');
}
