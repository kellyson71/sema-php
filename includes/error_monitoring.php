<?php
/**
 * Monitoramento de erros por e-mail — avisa pmpfestagio@gmail.com de qualquer
 * erro do PHP em produção, mesmo os pequenos (como observação). Não é alarme:
 * é um aviso de baixo perfil, pra ficar sabendo que algo aconteceu sem
 * precisar abrir o PostHog.
 *
 * Complementa includes/error_tracking.php (PostHog) — aquele existe pra
 * investigar depois com contexto de sessão; este é só pra notificar rápido.
 * Os dois só rodam em produção real (nem Docker local, nem homologação, nem CLI).
 *
 * Encadeia com o handler anterior (o do PostHog, se já registrado) em vez de
 * substituí-lo — assim os dois continuam funcionando juntos.
 */

function severidadeErroMonitor(int $errno): string
{
    if (in_array($errno, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
        return 'alto';
    }
    if (in_array($errno, [E_WARNING, E_USER_WARNING, E_CORE_WARNING, E_COMPILE_WARNING], true)) {
        return 'medio';
    }
    return 'baixo';
}

function rotuloSeveridadeErroMonitor(string $severidade): string
{
    return match ($severidade) {
        'alto' => 'Erro',
        'medio' => 'Aviso',
        default => 'Observação',
    };
}

if (!function_exists('deveNotificarErroMonitor')) {
    /**
     * Throttle simples baseado em arquivo: a mesma ocorrência (arquivo+linha+mensagem)
     * só notifica de novo depois de $minutosThrottle, pra um erro que dispara em toda
     * requisição não incendiar a caixa de entrada.
     */
    function deveNotificarErroMonitor(string $chave, int $minutosThrottle = 30): bool
    {
        $dir = dirname(__DIR__) . '/tmp/error_monitor';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $arquivo = $dir . '/' . md5($chave) . '.flag';
        if (is_file($arquivo) && (time() - (int) @filemtime($arquivo)) < ($minutosThrottle * 60)) {
            return false;
        }
        @touch($arquivo);
        return true;
    }
}

if (!function_exists('notificarErroMonitorPorEmail')) {
    function notificarErroMonitorPorEmail(string $severidade, string $mensagem, string $arquivo, int $linha): void
    {
        $chave = $arquivo . ':' . $linha . ':' . $mensagem;
        if (!deveNotificarErroMonitor($chave)) {
            return;
        }

        try {
            require_once __DIR__ . '/email_service.php';

            $rotulo = rotuloSeveridadeErroMonitor($severidade);
            $assunto = "[SEMA] {$rotulo} em produção ({$severidade})";
            $url = $_SERVER['REQUEST_URI'] ?? '(execução via CLI)';
            $corpo = '<p>' . htmlspecialchars($rotulo) . ' detectado em produção — aviso automático, não precisa de ação imediata.</p>'
                . '<p><strong>Severidade:</strong> ' . htmlspecialchars($severidade) . '</p>'
                . '<p><strong>Mensagem:</strong> ' . htmlspecialchars($mensagem) . '</p>'
                . '<p><strong>Arquivo:</strong> ' . htmlspecialchars($arquivo) . ':' . $linha . '</p>'
                . '<p><strong>URL:</strong> ' . htmlspecialchars($url) . '</p>'
                . '<p><strong>Quando:</strong> ' . date('d/m/Y H:i:s') . '</p>';

            sendMail('pmpfestagio@gmail.com', 'Equipe SEMA', $assunto, $corpo);
        } catch (\Throwable $e) {
            // O monitoramento de erro nunca pode ser, ele mesmo, a causa de um erro.
        }
    }
}

(static function (): void {
    $ehProducao = PHP_SAPI !== 'cli'
        && defined('DOCKER_ENV') && !DOCKER_ENV
        && defined('MODO_HOMOLOG') && !MODO_HOMOLOG;

    if (!$ehProducao) {
        return;
    }

    $exceptionHandlerAnterior = set_exception_handler(function (\Throwable $e) use (&$exceptionHandlerAnterior) {
        notificarErroMonitorPorEmail('alto', $e->getMessage(), $e->getFile(), $e->getLine());
        if (is_callable($exceptionHandlerAnterior)) {
            $exceptionHandlerAnterior($e);
        }
    });

    $errorHandlerAnterior = set_error_handler(function (int $errno, string $errstr, string $errfile = '', int $errline = 0) use (&$errorHandlerAnterior) {
        notificarErroMonitorPorEmail(severidadeErroMonitor($errno), $errstr, $errfile, $errline);
        if (is_callable($errorHandlerAnterior)) {
            return $errorHandlerAnterior($errno, $errstr, $errfile, $errline);
        }
        return false; // false = deixa o PHP seguir com o tratamento padrão dele também
    });

    register_shutdown_function(function (): void {
        $erro = error_get_last();
        if ($erro && in_array($erro['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            notificarErroMonitorPorEmail('alto', $erro['message'], $erro['file'], $erro['line']);
        }
    });
})();
