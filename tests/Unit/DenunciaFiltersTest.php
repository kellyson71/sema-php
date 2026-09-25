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

    public function testAdminESecretarioAbremEmTodasAsEquipes(): void
    {
        self::assertSame('', setorPadraoDenuncia('admin', 'meio_ambiente'));
        self::assertSame('', setorPadraoDenuncia('admin_geral', 'obras_urbanismo'));
        self::assertSame('', setorPadraoDenuncia('secretario', 'ambos'));
    }

    public function testDemaisCargosAbremNaPropriaEquipe(): void
    {
        self::assertSame('meio_ambiente', setorPadraoDenuncia('analista', 'meio_ambiente'));
        self::assertSame('obras_urbanismo', setorPadraoDenuncia('fiscal', 'obras_urbanismo'));
        self::assertSame('', setorPadraoDenuncia('operador', 'ambos'));
    }

    public function testSimulacaoDeCargoUsaEquipeTipica(): void
    {
        self::assertSame('obras_urbanismo', setorPadraoDenuncia('fiscal', setorTipicoDoCargo('fiscal')));
        self::assertSame('meio_ambiente', setorPadraoDenuncia('analista', setorTipicoDoCargo('analista')));
        self::assertSame('', setorPadraoDenuncia('secretario', setorTipicoDoCargo('secretario')));
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

    public function testDiasSemAndamentoContaDiasInteiros(): void
    {
        $agora = new DateTimeImmutable('2026-09-25 09:00');
        self::assertSame(0, diasSemAndamento('2026-09-25 01:00', $agora));
        self::assertSame(1, diasSemAndamento('2026-09-24 23:59', $agora));
        self::assertSame(15, diasSemAndamento('2026-09-10 08:00', $agora));
        self::assertSame(0, diasSemAndamento(null, $agora));
    }

    public function testNivelDeAtraso(): void
    {
        self::assertSame('', nivelAtrasoDenuncia(6, 'Pendente'));
        self::assertSame('atencao', nivelAtrasoDenuncia(7, 'Em Análise'));
        self::assertSame('atrasada', nivelAtrasoDenuncia(15, 'Pendente'));
        self::assertSame('', nivelAtrasoDenuncia(40, 'Concluída'));
    }

    public function testSituacoesAceitasCasamComOsFiltros(): void
    {
        self::assertSame(['Pendente', 'Em Análise', 'Concluída'], DENUNCIA_SITUACOES);
        $normalizadas = array_map('normalizarStatusProcesso', DENUNCIA_SITUACOES);
        self::assertSame(['pendente', 'em_analise', 'concluida'], $normalizadas);
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
