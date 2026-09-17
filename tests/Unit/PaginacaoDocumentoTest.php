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

    #[Test]
    public function documentoSemMarcaDeWordPassaIntacto(): void
    {
        $html = '<table><tr><td>célula</td></tr></table><img src="data:image/png;base64,QUJD">';

        $this->assertSame($html, limparColagemWord($html));
    }

    #[Test]
    public function imagemColadaDoWordComCaminhoLocalEhRemovida(): void
    {
        // O Word cola <img> apontando pro disco de quem colou (nunca existe
        // no servidor). Sem isso, o TCPDF reserva o vão do width/height da
        // tag mesmo sem conseguir abrir o arquivo.
        $html = '<p style="mso-margin-top-alt:0">Antes</p>'
            . '<img width="559" height="166" src="file:///C:/Users/Usuario/AppData/Local/Temp/msohtmlclip1/01/clip_image004.gif">'
            . '<p>Depois</p>';

        $limpo = limparColagemWord($html);

        $this->assertStringNotContainsString('<img', $limpo);
        $this->assertStringNotContainsString('file://', $limpo);
        $this->assertStringContainsString('Antes', $limpo);
        $this->assertStringContainsString('Depois', $limpo);
    }

    #[Test]
    public function imagemBase64OuHttpDoWordEhPreservada(): void
    {
        $html = '<p class="MsoNormal">x</p>'
            . '<img src="data:image/png;base64,QUJD">'
            . '<img src="https://exemplo.gov.br/foto.jpg">';

        $limpo = limparColagemWord($html);

        $this->assertStringContainsString('data:image/png;base64,QUJD', $limpo);
        $this->assertStringContainsString('https://exemplo.gov.br/foto.jpg', $limpo);
    }

    #[Test]
    public function bordaPorLadoDoWordEhRemovidaParaOCssDoDocumentoValer(): void
    {
        // O Word manda border-width/-style/-color separados, um valor por
        // lado (T R B L). O parser de CSS do TCPDF trata mal o valor "none"
        // nesse formato e deixa risco solto onde não devia ter borda.
        $html = '<table class="MsoTableGrid" style="mso-border-alt:solid black .5pt">'
            . '<tr><td style="width:247.75pt;border-top:none;border-left:none;'
            . 'border-bottom:solid black 1.0pt;border-right:solid black 1.0pt;'
            . 'mso-border-top-alt:solid black .5pt;padding:0cm 5.4pt">x</td></tr></table>';

        $limpo = limparColagemWord($html);

        $this->assertStringNotContainsString('border-top', $limpo);
        $this->assertStringNotContainsString('border-left', $limpo);
        $this->assertStringNotContainsString('mso-', $limpo);
        // Propriedades que não são de borda/mso continuam.
        $this->assertStringContainsString('padding:0cm 5.4pt', $limpo);
    }

    #[Test]
    public function tagOpDoWordEhDesembrulhadaSemPerderTexto(): void
    {
        $html = '<p class="MsoNormal">Trata o presente parecer<o:p></o:p></p>'
            . '<p class="MsoNormal"><o:p>&nbsp;</o:p></p>';

        $limpo = limparColagemWord($html);

        $this->assertStringNotContainsString('o:p', $limpo);
        $this->assertStringContainsString('Trata o presente parecer', $limpo);
    }
}
