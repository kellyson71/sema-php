<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/atividade_admin.php';

final class AtividadeAdminTest extends TestCase
{
    public function testPaginaFicaRelativaAoAdmin(): void
    {
        self::assertSame('admin/denuncias.php', atividadePaginaRelativa('/admin/denuncias.php'));
        self::assertSame('admin/documentos/editor.php', atividadePaginaRelativa('/sema/admin/documentos/editor.php'));
    }

    public function testNomeDaAcaoVemDoCampoAcaoSemValoresDigitados(): void
    {
        self::assertSame('alterar_status', atividadeNomeAcao([], ['acao' => 'alterar_status', 'nome' => 'Fulano'], 'POST', 'admin/processar_denuncia.php'));
        self::assertSame('excluir', atividadeNomeAcao(['action' => 'excluir'], [], 'GET', 'admin/x.php'));
        self::assertSame('processar_denuncia', atividadeNomeAcao([], ['observacoes' => 'texto'], 'POST', 'admin/processar_denuncia.php'));
        self::assertNull(atividadeNomeAcao(['id' => '3'], [], 'GET', 'admin/visualizar_denuncia.php'));
    }

    public function testNomeDaAcaoDescartaCaracteresEstranhos(): void
    {
        self::assertSame('scriptalert1script', atividadeNomeAcao([], ['acao' => '<script>alert(1)</script>'], 'POST', 'admin/x.php'));
    }

    public function testEntidadeDeduzidaPelaPaginaOuPeloCampo(): void
    {
        self::assertSame(['denuncia', 12], atividadeEntidade(['id' => '12'], [], 'admin/visualizar_denuncia.php'));
        self::assertSame(['requerimento', 7], atividadeEntidade([], ['requerimento_id' => '7'], 'admin/parecer_handler.php'));
        self::assertSame(['registro', 5], atividadeEntidade(['id' => '5'], [], 'admin/sugestoes.php'));
        self::assertSame([null, null], atividadeEntidade(['id' => 'abc'], [], 'admin/visualizar_denuncia.php'));
    }

    public function testTipoDoEvento(): void
    {
        self::assertSame('erro', atividadeTipo('GET', 'admin/x.php', false, 500, false));
        self::assertSame('erro', atividadeTipo('POST', 'admin/x.php', false, 200, true));
        self::assertSame('ajax', atividadeTipo('GET', 'admin/ajax/busca_rapida.php', false, 200, false));
        self::assertSame('acao', atividadeTipo('POST', 'admin/processar_denuncia.php', false, 302, false));
        self::assertSame('pagina', atividadeTipo('GET', 'admin/denuncias.php', false, 200, false));
    }

    public function testMensagemDeErroNaoCarregaCpfOuTelefone(): void
    {
        $msg = atividadeMensagemErroSegura("Duplicate entry '123.456.789-00' for key cpf, tel 84999998888");
        self::assertStringNotContainsString('123.456', $msg);
        self::assertStringNotContainsString('84999998888', $msg);
        self::assertStringContainsString('Duplicate entry', $msg);
    }

    public function testDuracaoLegivel(): void
    {
        self::assertSame('0 min', atividadeFormatarDuracao(0));
        self::assertSame('menos de 1 min', atividadeFormatarDuracao(30));
        self::assertSame('45 min', atividadeFormatarDuracao(45 * 60));
        self::assertSame('2 h 5 min', atividadeFormatarDuracao(2 * 3600 + 5 * 60));
        self::assertSame('3 h', atividadeFormatarDuracao(3 * 3600));
    }

    public function testGradeTem53SemanasDeDomingoASabadoTerminandoHoje(): void
    {
        $hoje = new DateTimeImmutable('2026-09-24'); // quinta-feira
        $grade = atividadeGradeHeatmap(['2026-09-24' => 10, '2026-09-20' => 5], $hoje);

        self::assertCount(53, $grade);
        self::assertSame('0', (new DateTimeImmutable($grade[0][0]['data']))->format('w'));
        $ultima = end($grade);
        self::assertSame('2026-09-26', $ultima[6]['data']);
        self::assertTrue($ultima[6]['futuro']);
        self::assertSame(4, $ultima[4]['nivel']);
        self::assertSame(2, $ultima[0]['nivel']);
        self::assertSame(0, $grade[10][3]['nivel']);
    }

    public function testGradeQuandoHojeESabado(): void
    {
        $grade = atividadeGradeHeatmap([], new DateTimeImmutable('2026-09-26'));
        $ultima = end($grade);
        self::assertSame('2026-09-26', $ultima[6]['data']);
        self::assertFalse($ultima[6]['futuro']);
    }
}
