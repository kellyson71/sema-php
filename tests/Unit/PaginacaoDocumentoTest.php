<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A estrutura visual de folhas do editor não pode sobreviver até o PDF.
 */
final class PaginacaoDocumentoTest extends TestCase
{
    /** Marcação real de um separador de folha gerado pelo editor. */
    private function separador(int $numero): string
    {
        return '<div class="page-gap" contenteditable="false">'
            . '<div class="page-gap-inner">'
            . '<div class="page-gap-footer">Página ' . $numero . '</div>'
            . '<div class="page-gap-space"></div>'
            . '<div class="page-gap-header">'
            . '<div class="page-gap-header-inner">'
            . '<img src="logo.png" alt="">'
            . '<div>'
            . '<div class="page-gap-prefeitura">PREFEITURA MUNICIPAL DE PAU DOS FERROS/RN</div>'
            . '<div class="page-gap-secretaria">SECRETARIA MUNICIPAL DE MEIO AMBIENTE · SEMA</div>'
            . '</div></div>'
            . '<div class="page-gap-line"></div>'
            . '</div></div></div>';
    }

    #[Test]
    public function separadorComDivsAninhadasSaiPorInteiro(): void
    {
        $html = '<p>Antes</p>' . $this->separador(1) . '<p>Depois</p>';

        $limpo = removerEstruturaPaginacaoHtml($html);

        $this->assertSame('<p>Antes</p><p>Depois</p>', $limpo);
    }

    #[Test]
    public function nenhumTextoDoSeparadorVazaParaOPdf(): void
    {
        // O bug antigo: a regex preguiçosa parava no primeiro </div> e deixava
        // o cabeçalho do separador virar texto do parecer.
        $limpo = removerEstruturaPaginacaoHtml('<p>Parecer</p>' . $this->separador(2));

        $this->assertStringNotContainsString('PREFEITURA MUNICIPAL', $limpo);
        $this->assertStringNotContainsString('Página 2', $limpo);
        $this->assertStringNotContainsString('</div>', $limpo);
    }

    #[Test]
    public function variosSeparadoresSaemDeUmaVez(): void
    {
        $html = '<p>A</p>' . $this->separador(1) . '<p>B</p>' . $this->separador(2) . '<p>C</p>';

        $this->assertSame('<p>A</p><p>B</p><p>C</p>', removerEstruturaPaginacaoHtml($html));
    }

    #[Test]
    public function separadorDentroDeContainerNaoLevaOContainerJunto(): void
    {
        $html = '<div class="texto-parecer"><p>Um</p>' . $this->separador(1) . '<p>Dois</p></div>';

        $this->assertSame(
            '<div class="texto-parecer"><p>Um</p><p>Dois</p></div>',
            removerEstruturaPaginacaoHtml($html)
        );
    }

    #[Test]
    public function separadorEmLinhaDeTabelaSaiSemQuebrarATabela(): void
    {
        $html = '<table><tbody>'
            . '<tr><td>Linha 1</td></tr>'
            . '<tr class="page-gap" contenteditable="false"><td colspan="2"><div class="page-gap-inner">'
            . '<div class="page-gap-footer">Página 1</div></div></td></tr>'
            . '<tr><td>Linha 2</td></tr>'
            . '</tbody></table>';

        $this->assertSame(
            '<table><tbody><tr><td>Linha 1</td></tr><tr><td>Linha 2</td></tr></tbody></table>',
            removerEstruturaPaginacaoHtml($html)
        );
    }

    #[Test]
    public function involucroDeFolhaAntigoEDesembrulhadoSemPerderConteudo(): void
    {
        // Rascunhos salvos pela versão anterior do editor ainda trazem isto.
        $html = '<div class="doc-page-content"><p>Um</p><div><p>Aninhado</p></div></div>'
              . '<div class="doc-page-content"><p>Dois</p></div>';

        $this->assertSame(
            '<p>Um</p><div><p>Aninhado</p></div><p>Dois</p>',
            removerEstruturaPaginacaoHtml($html)
        );
    }

    #[Test]
    public function documentoSemPaginacaoPassaIntacto(): void
    {
        $html = '<div class="texto-parecer"><p>Nada a remover</p>'
              . '<table><tr><td>célula</td></tr></table></div>';

        $this->assertSame($html, removerEstruturaPaginacaoHtml($html));
    }

    #[Test]
    public function classeParecidaNaoEConfundidaComSeparador(): void
    {
        $html = '<div class="page-gap-descricao"><p>Conteúdo legítimo</p></div>';

        $this->assertSame($html, removerEstruturaPaginacaoHtml($html));
    }

    #[Test]
    public function htmlTruncadoNaoEngoleORestoDoDocumento(): void
    {
        // Sem </div> de fechamento: some só a abertura, o texto continua.
        $html = '<p>Antes</p><div class="page-gap"><p>Depois</p>';

        $limpo = removerEstruturaPaginacaoHtml($html);

        $this->assertStringContainsString('<p>Depois</p>', $limpo);
        $this->assertStringNotContainsString('page-gap', $limpo);
    }
}
