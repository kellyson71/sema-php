<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/admin/helpers.php';

final class AdminStatusHelpersTest extends TestCase
{
    public function testSetor2TemVocabularioProprioSemPendenteGenerico(): void
    {
        $opcoesSetor2 = adminStatusOpcoesModal('fiscal');

        self::assertNotContains('Pendente', $opcoesSetor2);
        self::assertContains('Aguardando visita técnica', $opcoesSetor2);
        self::assertContains('Aguardando parecer técnico', $opcoesSetor2);
    }

    public function testOutrosNiveisContinuamComAListaPrincipal(): void
    {
        self::assertSame(adminStatusFluxoPrincipal(), adminStatusOpcoesModal('analista'));
        self::assertSame(adminStatusFluxoPrincipal(), adminStatusOpcoesModal('admin'));
    }

    public function testValidacaoRespeitaOSetorDeQuemEstaAgindo(): void
    {
        self::assertTrue(adminStatusPermitidoParaOperacao('Aguardando visita técnica', 'fiscal'));
        self::assertFalse(adminStatusPermitidoParaOperacao('Pendente', 'fiscal'));
        self::assertTrue(adminStatusPermitidoParaOperacao('Pendente', 'analista'));
        self::assertFalse(adminStatusPermitidoParaOperacao('Aguardando visita técnica', 'analista'));
    }

    public function testSemNivelInformadoAceitaQualquerStatusValido(): void
    {
        self::assertTrue(adminStatusPermitidoParaOperacao('Pendente'));
        self::assertTrue(adminStatusPermitidoParaOperacao('Aguardando visita técnica'));
        self::assertFalse(adminStatusPermitidoParaOperacao('Status Inventado'));
    }
}
