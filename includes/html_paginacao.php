<?php

declare(strict_types=1);

/**
 * Remoção da estrutura visual de paginação do editor antes do PDF.
 *
 * O editor insere separadores de folha (`.page-gap`, e a variante `<tr>` dentro
 * de tabelas) puramente para dar a aparência de folhas A4 separadas. Nada disso
 * pode chegar ao TCPDF — quem pagina o documento final é exclusivamente o
 * TCPDF, e qualquer resíduo desses blocos vira texto solto no corpo do PDF.
 *
 * A varredura conta abertura/fechamento de tags para achar o fechamento CERTO.
 * Uma regex preguiçosa (`[\s\S]*?</div>`) para no primeiro `</div>` e deixa
 * escapar todo o miolo aninhado do separador — foi exatamente esse o bug que
 * derramava "PREFEITURA MUNICIPAL DE PAU DOS FERROS/RN" dentro do parecer.
 */

/** Classes que representam separadores visuais descartáveis. */
const CLASSES_SEPARADOR_PAGINA = ['page-gap', 'page-cut', 'page-break-indicator'];

/** Classes de invólucros de folha: somem, mas o conteúdo dentro é preservado. */
const CLASSES_ENVOLTORIO_PAGINA = ['doc-page-content'];

/**
 * Devolve o HTML do editor sem nenhum vestígio da paginação visual.
 */
function removerEstruturaPaginacaoHtml(string $html): string
{
    // Separadores de folha, na versão bloco e na versão linha de tabela
    $html = processarTagsPorClasse($html, 'div', CLASSES_SEPARADOR_PAGINA, true);
    $html = processarTagsPorClasse($html, 'tr', CLASSES_SEPARADOR_PAGINA, true);
    // Invólucros de folha de versões anteriores do editor: desembrulha
    $html = processarTagsPorClasse($html, 'div', CLASSES_ENVOLTORIO_PAGINA, false);

    return $html;
}

/**
 * Remove (ou desembrulha) toda tag `$tag` que carregue uma das `$classes`.
 *
 * @param bool $remover true = apaga a tag e todo o conteúdo dela;
 *                      false = apaga só as tags de abertura/fechamento.
 */
function processarTagsPorClasse(string $html, string $tag, array $classes, bool $remover): string
{
    if ($classes === []) {
        return $html;
    }

    $padraoClasse = '(?:' . implode('|', array_map(
        static fn(string $c): string => preg_quote($c, '/'),
        $classes
    )) . ')';
    $padraoAbertura = '/<' . preg_quote($tag, '/') . '\b[^>]*\bclass\s*=\s*("|\')([^"\']*)\1[^>]*>/i';

    $offset = 0;
    while ($offset < strlen($html)
        && preg_match($padraoAbertura, $html, $m, PREG_OFFSET_CAPTURE, $offset)) {

        $aberturaInicio = $m[0][1];
        $aberturaFim    = $aberturaInicio + strlen($m[0][0]);

        if (!preg_match('/(?:^|\s)' . $padraoClasse . '(?:\s|$)/', $m[2][0])) {
            $offset = $aberturaFim;
            continue;
        }

        $fechamento = localizarFechamentoDaTag($html, $tag, $aberturaFim);

        if ($fechamento === null) {
            // HTML truncado: sem par de fechamento, some só com a abertura para
            // não engolir o resto do documento por engano.
            $html = substr($html, 0, $aberturaInicio) . substr($html, $aberturaFim);
            $offset = $aberturaInicio;
            continue;
        }

        if ($remover) {
            $html = substr($html, 0, $aberturaInicio) . substr($html, $fechamento['fim']);
        } else {
            // Corta o fechamento primeiro: ele está depois da abertura, então os
            // índices da abertura continuam válidos.
            $html = substr($html, 0, $fechamento['inicio']) . substr($html, $fechamento['fim']);
            $html = substr($html, 0, $aberturaInicio) . substr($html, $aberturaFim);
        }

        $offset = $aberturaInicio;
    }

    return $html;
}

/**
 * Acha o fechamento que realmente pertence à abertura, contando aninhamento.
 *
 * @return array{inicio:int,fim:int}|null
 */
function localizarFechamentoDaTag(string $html, string $tag, int $offset): ?array
{
    $padrao = '/<\s*(\/?)' . preg_quote($tag, '/') . '\b[^>]*>/i';
    $profundidade = 1;
    $limite = strlen($html);

    while ($offset < $limite && preg_match($padrao, $html, $m, PREG_OFFSET_CAPTURE, $offset)) {
        $inicio = $m[0][1];
        $fim    = $inicio + strlen($m[0][0]);

        if ($m[1][0] === '/') {
            if (--$profundidade === 0) {
                return ['inicio' => $inicio, 'fim' => $fim];
            }
        } else {
            $profundidade++;
        }

        $offset = $fim;
    }

    return null;
}
