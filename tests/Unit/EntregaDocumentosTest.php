<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Entrega de documentos ao cidadão: rótulo legível dos arquivos, corpo do
 * e-mail em texto puro e o token do lote.
 */
class EntregaDocumentosTest extends TestCase
{
    // ─── rotuloDocumento ──────────────────────────────────────────────────────

    #[Test]
    #[DataProvider('nomesDeArquivo')]
    public function rotuloDocumentoLimpaCarimbosDoNome(string $arquivo, string $esperado): void
    {
        $this->assertSame($esperado, rotuloDocumento($arquivo));
    }

    public static function nomesDeArquivo(): array
    {
        return [
            'data e hash no nome'   => ['PARECER_TECNICO_20260714_a7f2c9.pdf', 'Parecer Tecnico'],
            'acentos preservados'   => ['alvara_de_construção.pdf', 'Alvara De Construção'],
            'hifens viram espaço'   => ['licenca-previa-ambiental.pdf', 'Licenca Previa Ambiental'],
            'sem extensão'          => ['habite_se', 'Habite Se'],
            'só ruído sobra vazio'  => ['20260714_a7f2c9de.pdf', ''],
            'nome vazio'            => ['', ''],
        ];
    }

    #[Test]
    public function rotuloDocumentoNaoQuebraComNomeSoDeExtensao(): void
    {
        $this->assertSame('', rotuloDocumento('.pdf'));
    }

    // ─── textoSimplesDoEmail ──────────────────────────────────────────────────

    #[Test]
    public function textoSimplesPreservaLinksParaClienteSemHtml(): void
    {
        $html = '<p>Baixe aqui: <a href="https://sema.exemplo/doc?token=abc">Abrir documento</a></p>';
        $texto = textoSimplesDoEmail($html);

        $this->assertStringContainsString('https://sema.exemplo/doc?token=abc', $texto);
        $this->assertStringContainsString('Abrir documento', $texto);
        $this->assertStringNotContainsString('<a', $texto);
    }

    #[Test]
    public function textoSimplesDescartaCssEScripts(): void
    {
        $html = '<style>.btn{color:red}</style><script>alert(1)</script><p>Olá, Maria.</p>';
        $texto = textoSimplesDoEmail($html);

        $this->assertSame('Olá, Maria.', $texto);
        $this->assertStringNotContainsString('color:red', $texto);
        $this->assertStringNotContainsString('alert', $texto);
    }

    #[Test]
    public function textoSimplesDecodificaEntidades(): void
    {
        $texto = textoSimplesDoEmail('<p>Alvar&aacute; &mdash; SEMA</p>');
        $this->assertStringContainsString('Alvará', $texto);
    }

    // ─── gerarTokenDocumentoFinal ─────────────────────────────────────────────

    #[Test]
    public function tokenTrazOIdDoRequerimentoNoPrefixo(): void
    {
        $token = gerarTokenDocumentoFinal(42);

        $this->assertStringStartsWith('42.df.', $token);
        $this->assertSame(42, requerimentoIdDoToken($token));
    }

    #[Test]
    public function tokensSaoAleatoriosEntreEnvios(): void
    {
        // Dois envios do mesmo requerimento não podem gerar o mesmo link:
        // revogar uma entrega antiga não pode derrubar a nova.
        $this->assertNotSame(
            gerarTokenDocumentoFinal(7),
            gerarTokenDocumentoFinal(7)
        );
    }

    #[Test]
    public function requerimentoIdDeTokenInvalidoEhZero(): void
    {
        $this->assertSame(0, requerimentoIdDoToken(''));
        $this->assertSame(0, requerimentoIdDoToken('lixo'));
    }

    // ─── urlArquivo ───────────────────────────────────────────────────────────

    #[Test]
    public function urlArquivoNuncaApontaParaUploads(): void
    {
        $url = urlArquivo('20260714/doc_1.pdf', 'tok3n');

        $this->assertStringStartsWith('arquivo.php?path=', $url);
        $this->assertStringNotContainsString('uploads/', $url);
        $this->assertStringContainsString('token=tok3n', $url);
    }

    #[Test]
    public function urlArquivoOmiteTokenQuandoNaoInformado(): void
    {
        $this->assertStringNotContainsString('token=', urlArquivo('perfil/foto.png'));
    }

    // ─── tituloAmigavel ────────────────────────────────────────────────────────

    #[Test]
    #[DataProvider('titulosAlvara')]
    public function tituloAmigavelTratePreposicoesPortuguesas(string $entrada, string $esperado): void
    {
        $this->assertSame($esperado, tituloAmigavel($entrada));
    }

    public static function titulosAlvara(): array
    {
        return [
            'tipo completo com E/OU'         => ['ALVARÁ DE CONSTRUÇÃO, REFORMA E/OU AMPLIAÇÃO', 'Alvará de Construção, Reforma e/ou Ampliação'],
            'licença prévia'                 => ['LICENÇA PRÉVIA DO PROJETO', 'Licença Prévia do Projeto'],
            'autorização de supressão'       => ['AUTORIZAÇÃO PARA SUPRESSÃO DE VEGETAÇÃO', 'Autorização para Supressão de Vegetação'],
            'certidão de uso e ocupação'     => ['CERTIDÃO DE USO E OCUPAÇÃO DO SOLO', 'Certidão de Uso e Ocupação do Solo'],
            'texto vazio'                    => ['', ''],
            'palavra única'                  => ['ALVARÁ', 'Alvará'],
            'preposição no início maiúscula' => ['DE CABEÇA', 'De Cabeça'],
        ];
    }
}
