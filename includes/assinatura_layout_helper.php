<?php

declare(strict_types=1);

/**
 * Regras puras de layout da folha e do bloco de assinatura.
 * Compartilhadas pelo gerador e pelos testes sem depender do TCPDF.
 *
 * Estas medidas são a fonte única da verdade da paginação. O editor espelha
 * exatamente os mesmos números em js/editor_paginacao.js — quem pagina de
 * fato é o TCPDF; o editor só desenha onde o corte vai cair.
 */

/** Altura total da folha A4, em mm. */
const A4_ALTURA_MM = 297.0;
/** Largura total da folha A4, em mm. */
const A4_LARGURA_MM = 210.0;
/** Faixa reservada ao cabeçalho institucional (margem superior do TCPDF). */
const A4_CABECALHO_MM = 27.0;
/** Faixa reservada ao rodapé (margem de quebra automática do TCPDF). */
const A4_RODAPE_MM = 14.0;
/** Margem lateral do texto. */
const A4_MARGEM_LATERAL_MM = 15.0;
/** Área útil de texto por folha: 297 − 27 − 14. */
const A4_AREA_UTIL_MM = A4_ALTURA_MM - A4_CABECALHO_MM - A4_RODAPE_MM;
/** Respiro mínimo entre o fim do texto e o topo do bloco de assinatura. */
const ASSINATURA_RESPIRO_MM = 4.0;

/**
 * Largura e altura do bloco de assinatura para N assinantes.
 *
 * @return array{0:float,1:float} [largura, altura] em mm
 */
function dimensoesBlocoAssinatura(int $nAssinantes): array
{
    $quantidade = max(1, $nAssinantes);
    return [88.0, 20.0 + ($quantidade - 1) * 7.5];
}

/**
 * Canto superior-esquerdo do bloco de assinatura: rodapé da última folha,
 * alinhado à direita. Determinístico — não depende de nada vindo do navegador.
 *
 * @return array{0:float,1:float} [x, y] em mm
 */
function posicaoBlocoAssinatura(float $larguraBloco, float $alturaBloco): array
{
    return [
        A4_LARGURA_MM - A4_MARGEM_LATERAL_MM - $larguraBloco,
        A4_ALTURA_MM - A4_RODAPE_MM - $alturaBloco,
    ];
}

/**
 * O bloco de assinatura só pode ocupar espaço vazio. Se o texto terminar perto
 * demais do topo do bloco, a assinatura ganha uma folha exclusiva em vez de
 * cobrir o conteúdo.
 */
function assinaturaPrecisaNovaPagina(
    float $fimConteudoY,
    float $inicioAssinaturaY,
    float $respiro = ASSINATURA_RESPIRO_MM
): bool {
    return ($fimConteudoY + $respiro) > $inicioAssinaturaY;
}
