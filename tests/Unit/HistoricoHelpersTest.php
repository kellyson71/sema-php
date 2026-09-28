<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/historico_helpers.php';

final class HistoricoHelpersTest extends TestCase
{
    public function testMudancaDeStatusSeparaStatusEObservacao(): void
    {
        $c = classificarAcaoHistorico("Alterou status para 'Pendente' com a observação: Aguardando a visita e o parecer técnico. ");

        self::assertSame('status', $c['tipo']);
        self::assertSame('Status alterado para Pendente', $c['titulo']);
        self::assertSame('Aguardando a visita e o parecer técnico.', $c['detalhe']);
        self::assertSame('Pendente', $c['status']);
        self::assertSame('#b45309', $c['cor']);

        $semObs = classificarAcaoHistorico("Alterou status para 'Finalizado'");
        self::assertSame('', $semObs['detalhe']);
        self::assertSame('#0a6b34', $semObs['cor']);
    }

    public function testIndeferimentoGuardaOMotivoCompleto(): void
    {
        $c = classificarAcaoHistorico("Indeferiu o processo e enviou email de notificação - Motivo: - Ausência da ART;\n- Tanque fora da NBR.");

        self::assertSame('decisao', $c['tipo']);
        self::assertSame('Processo indeferido', $c['titulo']);
        self::assertSame("- Ausência da ART;\n- Tanque fora da NBR.", $c['detalhe']);
    }

    public function testDocumentosTemNomeLegivel(): void
    {
        $c = classificarAcaoHistorico('Gerou e assinou eletronicamente o documento: PARECER TECNICO HABITE SE AMBIENTAL');
        self::assertSame('documento', $c['tipo']);
        self::assertSame('Parecer técnico habite-se ambiental', $c['detalhe']);

        $co = classificarAcaoHistorico('Gerou e assinou eletronicamente (requisitou co-assinatura): licenca_ambiental_unica');
        self::assertSame('Assinou documento e pediu coassinatura', $co['titulo']);
        self::assertSame('Licença ambiental única', $co['detalhe']);

        $recusa = classificarAcaoHistorico('Recusou a co-assinatura do documento 6659b88a — Motivo: Não há necessidade.');
        self::assertSame('Recusou a coassinatura', $recusa['titulo']);
        self::assertSame('Não há necessidade.', $recusa['detalhe']);
    }

    public function testTramitacaoEmailECorrecao(): void
    {
        $fluxo = classificarAcaoHistorico('Enviou processo ao Setor 3 — Revisão Final: ASSINATURA');
        self::assertSame(['fluxo', 'Enviou ao Setor 3 — Revisão Final', 'ASSINATURA'], [$fluxo['tipo'], $fluxo['titulo'], $fluxo['detalhe']]);

        $email = classificarAcaoHistorico('Enviou e-mail com protocolo oficial: 2026.IMOB.10.10726-3');
        self::assertSame('email', $email['tipo']);
        self::assertSame('Protocolo 2026.IMOB.10.10726-3', $email['detalhe']);

        $correcao = classificarAcaoHistorico("Correção: status 'Pendente' marcado por engano em 02/09 desfeito");
        self::assertSame('correcao', $correcao['tipo']);
        self::assertSame('Correção de dados', $correcao['titulo']);

        $falha = classificarAcaoHistorico('Finalizou o processo com 1 documento(s) final(is), mas FALHOU o envio do e-mail para x@y');
        self::assertSame('#b13232', $falha['cor']);
    }

    public function testAcaoDesconhecidaViraOutroSemPerderTexto(): void
    {
        $c = classificarAcaoHistorico('Fez algo novo no sistema');
        self::assertSame('outro', $c['tipo']);
        self::assertSame('Fez algo novo no sistema', $c['titulo']);
    }

    public function testItemHtmlEscapaTextoEMostraSistemaSemAutor(): void
    {
        $html = itemHistoricoHtml([
            'acao' => "Alterou status para 'Pendente' com a observação: <script>x</script>",
            'admin_nome' => null,
            'data_acao' => '2026-09-02 12:28:53',
        ]);

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringContainsString('Sistema', $html);
        self::assertStringContainsString('data-hist-tipo="status"', $html);
    }

    public function testQuandoUsaHojeOntemEDiasRelativos(): void
    {
        $agora = new DateTimeImmutable('2026-09-27 20:00:00');
        self::assertSame('hoje, 08:10', quandoHistorico('2026-09-27 08:10:00', $agora));
        self::assertSame('ontem, 23:59', quandoHistorico('2026-09-26 23:59:00', $agora));
        self::assertSame('há 3 dias · 24/09/2026 10:00', quandoHistorico('2026-09-24 10:00:00', $agora));
        self::assertSame('02/09/2026 12:28', quandoHistorico('2026-09-02 12:28:53', $agora));
    }
}
