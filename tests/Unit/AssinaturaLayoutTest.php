<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * O carimbo de assinatura nunca pode cobrir o texto do documento.
 */
final class AssinaturaLayoutTest extends TestCase
{
    #[Test]
    public function assinaturaPermaneceNaUltimaPaginaQuandoHaEspaco(): void
    {
        $this->assertFalse(assinaturaPrecisaNovaPagina(230.0, 263.0));
    }

    #[Test]
    public function assinaturaRecebeNovaUltimaPaginaQuandoCobririaConteudo(): void
    {
        $this->assertTrue(assinaturaPrecisaNovaPagina(260.0, 263.0));
    }

    #[Test]
    public function respiroMinimoEmpurraAAssinaturaParaAProximaFolha(): void
    {
        // Texto termina exatamente no respiro: ainda cabe.
        $this->assertFalse(assinaturaPrecisaNovaPagina(259.0, 263.0));
        // Um milímetro além dele: ganha folha própria.
        $this->assertTrue(assinaturaPrecisaNovaPagina(259.5, 263.0));
    }

    #[Test]
    public function blocoCresceSemDividirOsCoassinantes(): void
    {
        $this->assertSame([88.0, 20.0], dimensoesBlocoAssinatura(1));
        $this->assertSame([88.0, 35.0], dimensoesBlocoAssinatura(3));
    }

    #[Test]
    public function posicaoDoBlocoEDeterministica(): void
    {
        [$largura, $altura] = dimensoesBlocoAssinatura(1);
        [$x, $y] = posicaoBlocoAssinatura($largura, $altura);

        // Encostado na margem direita (210 − 15 − 88) e no rodapé (297 − 14 − 20).
        $this->assertSame(107.0, $x);
        $this->assertSame(263.0, $y);
    }

    #[Test]
    public function blocoDeCoassinantesSobeSemInvadirORodape(): void
    {
        [$largura, $altura] = dimensoesBlocoAssinatura(4);
        [, $y] = posicaoBlocoAssinatura($largura, $altura);

        // O bloco cresce para cima: a base continua a 14mm do pé da folha.
        $this->assertSame(A4_ALTURA_MM - A4_RODAPE_MM, $y + $altura);
    }

    #[Test]
    public function areaUtilBateComAPaginacaoDoEditor(): void
    {
        // js/editor_paginacao.js corta a folha exatamente nesse valor.
        $this->assertSame(256.0, A4_AREA_UTIL_MM);
    }
}
