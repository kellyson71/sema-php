<?php

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

require_once dirname(__DIR__, 2) . '/includes/error_monitoring.php';

/**
 * includes/error_monitoring.php só registra handlers (set_error_handler,
 * set_exception_handler, register_shutdown_function) fora do CLI — o
 * PHPUnit roda em CLI, então requerer o arquivo aqui é seguro: nenhum
 * handler é instalado, só as funções puras ficam disponíveis pra testar.
 */
final class ErrorMonitoringTest extends TestCase
{
    private array $flagsCriadas = [];

    protected function tearDown(): void
    {
        foreach ($this->flagsCriadas as $chave) {
            $arquivo = dirname(__DIR__, 2) . '/tmp/error_monitor/' . md5($chave) . '.flag';
            if (is_file($arquivo)) {
                @unlink($arquivo);
            }
        }
    }

    // ─── severidadeErroMonitor ──────────────────────────────────────────────

    #[Test]
    public function classificaErrosFataisComoAlto(): void
    {
        foreach ([E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR] as $nivel) {
            self::assertSame('alto', severidadeErroMonitor($nivel));
        }
    }

    #[Test]
    public function classificaAvisosComoMedio(): void
    {
        foreach ([E_WARNING, E_USER_WARNING, E_CORE_WARNING, E_COMPILE_WARNING] as $nivel) {
            self::assertSame('medio', severidadeErroMonitor($nivel));
        }
    }

    #[Test]
    public function classificaNoticesEDeprecatedComoBaixo(): void
    {
        // E_STRICT foi removido no PHP 8.4 (mesmo o nome da constante virou
        // deprecated) — não faz mais sentido testar contra ele.
        foreach ([E_NOTICE, E_DEPRECATED, E_USER_NOTICE, E_USER_DEPRECATED] as $nivel) {
            self::assertSame('baixo', severidadeErroMonitor($nivel));
        }
    }

    // ─── rotuloSeveridadeErroMonitor ────────────────────────────────────────

    #[Test]
    public function rotuloDeCadaSeveridade(): void
    {
        self::assertSame('Erro', rotuloSeveridadeErroMonitor('alto'));
        self::assertSame('Aviso', rotuloSeveridadeErroMonitor('medio'));
        self::assertSame('Observação', rotuloSeveridadeErroMonitor('baixo'));
    }

    // ─── deveNotificarErroMonitor (throttle) ────────────────────────────────

    #[Test]
    public function primeiraOcorrenciaSempreNotifica(): void
    {
        $chave = 'teste-unico-' . uniqid('', true);
        $this->flagsCriadas[] = $chave;

        self::assertTrue(deveNotificarErroMonitor($chave));
    }

    #[Test]
    public function segundaOcorrenciaDentroDoThrottleNaoNotificaDeNovo(): void
    {
        $chave = 'teste-repetido-' . uniqid('', true);
        $this->flagsCriadas[] = $chave;

        self::assertTrue(deveNotificarErroMonitor($chave, 30));
        self::assertFalse(deveNotificarErroMonitor($chave, 30), 'A mesma ocorrência não deveria notificar de novo dentro da janela de throttle.');
    }

    #[Test]
    public function ocorrenciaVoltaANotificarAposExpirarOThrottle(): void
    {
        $chave = 'teste-expirado-' . uniqid('', true);
        $this->flagsCriadas[] = $chave;

        self::assertTrue(deveNotificarErroMonitor($chave, 30));

        // Simula que a última notificação foi há mais tempo que a janela de throttle.
        $arquivo = dirname(__DIR__, 2) . '/tmp/error_monitor/' . md5($chave) . '.flag';
        touch($arquivo, time() - 3600);

        self::assertTrue(deveNotificarErroMonitor($chave, 30));
    }

    #[Test]
    public function chavesDiferentesNaoSeThrottlamEntreSi(): void
    {
        $chaveA = 'teste-chave-a-' . uniqid('', true);
        $chaveB = 'teste-chave-b-' . uniqid('', true);
        $this->flagsCriadas[] = $chaveA;
        $this->flagsCriadas[] = $chaveB;

        self::assertTrue(deveNotificarErroMonitor($chaveA));
        self::assertTrue(deveNotificarErroMonitor($chaveB));
    }
}
