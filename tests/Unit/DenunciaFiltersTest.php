<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/denuncia_filters.php';

final class DenunciaFiltersTest extends TestCase
{
    public function testSetorDoAdministradorDefinePadraoDoSistema(): void
    {
        self::assertSame('meio_ambiente', filtrosSistemaDenuncia('meio_ambiente')['setor']);
        self::assertSame('obras_urbanismo', filtrosSistemaDenuncia('obras_urbanismo')['setor']);
        self::assertSame('', filtrosSistemaDenuncia('ambos')['setor']);
    }

    public function testUrlTemPrioridadeSobrePreferenciaSalva(): void
    {
        $saved = ['setor' => 'meio_ambiente', 'origem' => 'publico', 'status' => 'pendente'];
        $resolved = resolverFiltrosDenuncia(['setor' => 'obras_urbanismo'], $saved, 'ambos');

        self::assertSame('obras_urbanismo', $resolved['setor']);
        self::assertSame('publico', $resolved['origem']);
        self::assertSame('pendente', $resolved['status']);
    }

    public function testLimparNaoRestauraPreferenciaSalva(): void
    {
        $resolved = resolverFiltrosDenuncia(
            ['limpar' => '1'],
            ['setor' => 'meio_ambiente', 'origem' => 'publico'],
            'obras_urbanismo'
        );

        self::assertSame(filtrosLimposDenuncia(), $resolved);
    }

    public function testCriadasPorMimEValorValido(): void
    {
        self::assertSame(['origem' => 'minhas'], validarFiltrosDenuncia(['origem' => 'minhas']));
    }

    public function testValorInvalidoERejeitadoNoSalvamento(): void
    {
        $this->expectException(InvalidArgumentException::class);
        validarFiltrosDenuncia(['setor' => 'financeiro'], true);
    }

    public function testStatusEquivalentesSaoNormalizados(): void
    {
        self::assertSame('em_analise', normalizarStatusProcesso('Em Análise'));
        self::assertSame('concluida', normalizarStatusProcesso('Finalizado'));
        self::assertSame('concluida', normalizarStatusProcesso('Concluída'));
    }

    public function testTituloAnonimoSemInfrator(): void
    {
        self::assertSame('Denúncia anônima', tituloDenuncia(['anonimo' => 1, 'infrator_nome' => '']));
        self::assertSame('Denúncia anônima', tituloDenuncia(['anonimo' => 1, 'infrator_nome' => 'Não informado']));
        self::assertSame('Empresa X', tituloDenuncia(['anonimo' => 1, 'infrator_nome' => 'Empresa X']));
    }

    public function testTiposDeOcorrenciaGanhamRotulosLegiveis(): void
    {
        self::assertSame(
            ['Terreno sujo', 'Construção irregular'],
            tiposDenuncia(['tipo_denuncia' => '["terreno_sujo","construcao_irregular"]'])
        );
    }
}
