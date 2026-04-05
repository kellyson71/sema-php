<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Testes para a integridade do array $tipos_alvara (tipos_alvara.php).
 * Garante que todos os tipos têm a estrutura correta e nenhum dado obrigatório está faltando.
 */
class TiposAlvaraTest extends TestCase
{
    private static array $tipos = [];

    public static function setUpBeforeClass(): void
    {
        $arquivo = dirname(__DIR__, 2) . '/tipos_alvara.php';
        include $arquivo;
        self::$tipos = $tipos_alvara;
    }

    #[Test]
    public function arrayNaoEstaVazio(): void
    {
        $this->assertNotEmpty(self::$tipos, 'O array $tipos_alvara está vazio.');
    }

    #[Test]
    #[DataProvider('tiposProvider')]
    public function tipoPossuiChavesObrigatorias(string $slug, array $tipo): void
    {
        foreach (['nome', 'categoria', 'exige_ctf', 'exige_licenca_anterior'] as $chave) {
            $this->assertArrayHasKey(
                $chave,
                $tipo,
                "Tipo '{$slug}' não possui a chave obrigatória '{$chave}'."
            );
        }
        // Tipos normais possuem 'documentos'; 'funcionamento' usa 'pessoa_fisica'/'pessoa_juridica'
        $temDocumentos = isset($tipo['documentos'])
            || (isset($tipo['pessoa_fisica']) && isset($tipo['pessoa_juridica']));
        $this->assertTrue($temDocumentos, "Tipo '{$slug}' não possui lista de documentos.");
    }

    #[Test]
    #[DataProvider('tiposProvider')]
    public function nomeTipoNaoEstaVazio(string $slug, array $tipo): void
    {
        $this->assertNotEmpty($tipo['nome'], "Tipo '{$slug}' tem nome vazio.");
    }

    #[Test]
    #[DataProvider('tiposProvider')]
    public function categoriaTipoEValida(string $slug, array $tipo): void
    {
        $this->assertContains(
            $tipo['categoria'],
            ['obras', 'ambiental', 'outro'],
            "Tipo '{$slug}' tem categoria inválida: '{$tipo['categoria']}'."
        );
    }

    #[Test]
    #[DataProvider('tiposProvider')]
    public function documentosEhArray(string $slug, array $tipo): void
    {
        if (isset($tipo['documentos'])) {
            $this->assertIsArray($tipo['documentos'], "Tipo '{$slug}': 'documentos' deve ser array.");
        } elseif (isset($tipo['pessoa_fisica'])) {
            // Tipo 'funcionamento' usa listas separadas por perfil jurídico
            $this->assertIsArray($tipo['pessoa_fisica'], "Tipo '{$slug}': 'pessoa_fisica' deve ser array.");
            $this->assertIsArray($tipo['pessoa_juridica'], "Tipo '{$slug}': 'pessoa_juridica' deve ser array.");
        } else {
            $this->fail("Tipo '{$slug}' não tem 'documentos' nem 'pessoa_fisica'/'pessoa_juridica'.");
        }
    }

    #[Test]
    #[DataProvider('tiposProvider')]
    public function exigeCtfEBooleano(string $slug, array $tipo): void
    {
        $this->assertIsBool($tipo['exige_ctf'], "Tipo '{$slug}': 'exige_ctf' deve ser booleano.");
    }

    #[Test]
    #[DataProvider('tiposProvider')]
    public function exigeLicencaAnteriorEBooleano(string $slug, array $tipo): void
    {
        $this->assertIsBool(
            $tipo['exige_licenca_anterior'],
            "Tipo '{$slug}': 'exige_licenca_anterior' deve ser booleano."
        );
    }

    #[Test]
    #[DataProvider('tiposAmbientaisProvider')]
    public function tipoAmbientalQueExigeCtfTemChaveCtfExplicita(string $slug, array $tipo): void
    {
        // Garante que o campo 'exige_ctf' está presente em todos os tipos ambientais
        $this->assertArrayHasKey('exige_ctf', $tipo, "Tipo '{$slug}' não tem 'exige_ctf'.");
        $this->assertIsBool($tipo['exige_ctf'], "Tipo '{$slug}': 'exige_ctf' deve ser booleano.");
    }

    #[Test]
    public function nomeAlvaraRetornaNomeCorretoParaSlugConhecido(): void
    {
        $this->assertSame(
            'ALVARÁ DE CONSTRUÇÃO, REFORMA E/OU AMPLIAÇÃO',
            nomeAlvara('construcao')
        );
        $this->assertSame(
            'ALVARÁ DE HABITE-SE E LEGALIZAÇÃO',
            nomeAlvara('habite_se')
        );
    }

    #[Test]
    public function nomeAlvaraRetornaFallbackParaSlugDesconhecido(): void
    {
        $resultado = nomeAlvara('slug_inexistente');
        $this->assertNotEmpty($resultado);
        $this->assertSame('Slug Inexistente', $resultado);
    }

    // ─── Data Providers ───────────────────────────────────────────────────────

    public static function tiposProvider(): array
    {
        $arquivo = dirname(__DIR__, 2) . '/tipos_alvara.php';
        include $arquivo;
        $rows = [];
        foreach ($tipos_alvara as $slug => $tipo) {
            $rows[$slug] = [$slug, $tipo];
        }
        return $rows;
    }

    public static function tiposAmbientaisProvider(): array
    {
        $arquivo = dirname(__DIR__, 2) . '/tipos_alvara.php';
        include $arquivo;
        $rows = [];
        foreach ($tipos_alvara as $slug => $tipo) {
            if ($tipo['categoria'] === 'ambiental') {
                $rows[$slug] = [$slug, $tipo];
            }
        }
        return $rows;
    }
}
