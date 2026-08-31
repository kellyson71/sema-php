<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/assinatura_workflow_helpers.php';

final class AssinaturaWorkflowHelpersTest extends TestCase
{
    public function testResolveSecretarioAtivoUnicoComNomeCompletoECargo(): void
    {
        $secretario = resolverSecretarioAtivoUnico([[
            'id' => 7,
            'nome' => 'João',
            'nome_completo' => 'João da Silva',
            'cargo' => 'Secretário Municipal',
            'nivel' => 'secretario',
            'ativo' => 1,
        ]]);

        self::assertSame([
            'id' => 7,
            'nome' => 'João da Silva',
            'cargo' => 'Secretário Municipal',
        ], $secretario);
    }

    public function testUsaNomeECargoPadraoQuandoCamposOpcionaisEstaoVazios(): void
    {
        $secretario = resolverSecretarioAtivoUnico([[
            'id' => 3,
            'nome' => 'Maria Souza',
            'nome_completo' => '',
            'cargo' => '',
            'nivel' => 'secretario',
            'ativo' => 1,
        ]]);

        self::assertSame('Maria Souza', $secretario['nome']);
        self::assertSame('Secretário(a) Municipal de Meio Ambiente', $secretario['cargo']);
    }

    public function testRecusaAusenciaDeSecretarioAtivo(): void
    {
        try {
            resolverSecretarioAtivoUnico([]);
            self::fail('A ausência de secretário deveria impedir a geração manual.');
        } catch (AssinaturaWorkflowException $e) {
            self::assertSame('manual_signer_missing', $e->codigoPublico());
            self::assertSame(409, $e->httpStatus());
        }
    }

    public function testRecusaMaisDeUmSecretarioAtivo(): void
    {
        $this->expectException(AssinaturaWorkflowException::class);
        $this->expectExceptionMessage('mais de um secretário ativo');

        resolverSecretarioAtivoUnico([
            ['id' => 1, 'nome' => 'Secretário A', 'nivel' => 'secretario', 'ativo' => 1],
            ['id' => 2, 'nome' => 'Secretário B', 'nivel' => 'secretario', 'ativo' => 1],
        ]);
    }

    public function testIgnoraRegistroInativoOuDeOutroNivel(): void
    {
        $secretario = resolverSecretarioAtivoUnico([
            ['id' => 1, 'nome' => 'Secretário antigo', 'nivel' => 'secretario', 'ativo' => 0],
            ['id' => 2, 'nome' => 'Fiscal', 'nivel' => 'fiscal', 'ativo' => 1],
            ['id' => 3, 'nome' => 'Secretária atual', 'nivel' => 'secretario', 'ativo' => 1],
        ]);

        self::assertSame(3, $secretario['id']);
    }

    public function testAssinaturaManualPodeUsarUsuarioAtual(): void
    {
        $assinante = resolverAssinanteManual('atual', [
            'id' => 12,
            'nome' => 'Ana',
            'nome_completo' => 'Ana Beatriz Lima',
            'cargo' => 'Fiscal Ambiental',
        ]);

        self::assertSame([
            'id' => 12,
            'nome' => 'Ana Beatriz Lima',
            'cargo' => 'Fiscal Ambiental',
        ], $assinante);
    }

    public function testAssinaturaManualPodeUsarSecretarioResolvido(): void
    {
        $secretario = ['id' => 9, 'nome' => 'Carlos Silva', 'cargo' => 'Secretário Municipal'];

        self::assertSame($secretario, resolverAssinanteManual('secretario', [], $secretario));
    }

    public function testAssinaturaManualPodeUsarPessoaPersonalizada(): void
    {
        $assinante = resolverAssinanteManual(
            'personalizado',
            [],
            null,
            "  Joana   D'Arc <b>Souza</b> ",
            "  Diretora\nAdministrativa  "
        );

        self::assertSame("Joana D'Arc Souza", $assinante['nome']);
        self::assertSame('Diretora Administrativa', $assinante['cargo']);
        self::assertSame(0, $assinante['id']);
    }

    public function testPessoaPersonalizadaExigeNomeECargo(): void
    {
        try {
            resolverAssinanteManual('personalizado', [], null, 'Maria', '');
            self::fail('O cargo personalizado deveria ser obrigatório.');
        } catch (AssinaturaWorkflowException $e) {
            self::assertSame('manual_signer_custom_required', $e->codigoPublico());
            self::assertSame(422, $e->httpStatus());
        }
    }

    public function testRecusaTipoDeAssinanteManualDesconhecido(): void
    {
        $this->expectException(AssinaturaWorkflowException::class);
        resolverAssinanteManual('qualquer', []);
    }

    public function testClassificaDivergenciaDeSchemaSemExporSql(): void
    {
        $resultado = respostaErroAssinatura(
            new PDOException("Unknown column 'hash_conteudo' in 'field list'"),
            '[teste]'
        );

        self::assertSame(503, $resultado['status']);
        self::assertSame('schema_incompatible', $resultado['payload']['code']);
        self::assertStringNotContainsString('hash_conteudo', $resultado['payload']['error']);
    }

    public function testSchemaCanonicoSuportaManualEMultiplasAssinaturas(): void
    {
        $sql = file_get_contents(dirname(__DIR__, 2) . '/database/assinaturas_digitais.sql');

        self::assertIsString($sql);
        self::assertStringContainsString("'sem_assinatura'", $sql);
        self::assertStringContainsString('uq_doc_assinante', $sql);
        self::assertStringContainsString('hash_conteudo', $sql);
        self::assertStringContainsString('nivel_assinatura', $sql);
    }
}
