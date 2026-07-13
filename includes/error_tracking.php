<?php
/**
 * Error tracking (PostHog) — captura exceções e erros PHP não tratados.
 *
 * Complementa o includes/posthog.php: aquele é o posthog-js, que só enxerga o browser.
 * Erro de servidor (fatal, exceção, PDOException) só o SDK PHP vê. Os dois usam a
 * mesma chave e caem no mesmo projeto.
 *
 * Ligado pelo mesmo gate do snippet: POSTHOG_KEY vem do SetEnvIf no .htaccess, que só
 * casa com os domínios de produção. Sem a chave (localhost, homologação, CLI) isto é
 * um no-op silencioso.
 *
 * Este arquivo roda no início de toda requisição e NUNCA pode derrubar o site:
 * tudo abaixo é defensivo (checa vendor, checa classe, engole qualquer Throwable).
 * Se o PostHog falhar, o site segue como se ele não existisse.
 */

(static function (): void {
    $key = $_SERVER['POSTHOG_KEY'] ?? getenv('POSTHOG_KEY') ?: '';
    if (trim((string) $key) === '') {
        return; // sem chave = desligado, sem custo nenhum
    }

    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        return; // vendor ausente (deploy incompleto) — não é motivo para quebrar a página
    }

    try {
        require_once $autoload;

        if (!class_exists(\PostHog\PostHog::class)) {
            return; // pacote não instalado ainda
        }

        $host = $_SERVER['POSTHOG_HOST'] ?? getenv('POSTHOG_HOST') ?: 'https://us.i.posthog.com';

        \PostHog\PostHog::init($key, [
            'host'           => $host,
            'error_tracking' => [
                'enabled'        => true,
                'capture_errors' => true,
                'max_frames'     => 20,
                // Fluxo normal do sistema, não é defeito: 404 e validação de formulário
                // não devem virar issue no Error Tracking.
                'excluded_exceptions' => [
                    \InvalidArgumentException::class,
                ],
                'context_provider' => static function (array $payload) use ($key): array {
                    // Ponte com o browser. O posthog-js guarda distinct_id e session id num
                    // cookie; reusar o mesmo distinct_id aqui é o que cola o erro do servidor
                    // à sessão de quem o provocou — sem isso, o PHP inventa um ID aleatório e
                    // o erro fica órfão, sem ligação com o pageview da pessoa.
                    $browser = ['distinct_id' => null, 'session_id' => null];
                    $raw = $_COOKIE['ph_' . $key . '_posthog'] ?? null;
                    if (is_string($raw)) {
                        $data = json_decode($raw, true);
                        if (is_array($data)) {
                            $browser['distinct_id'] = $data['distinct_id'] ?? null;
                            // $sesid = [timestamp, sessionId, startTimestamp]
                            if (isset($data['$sesid'][1]) && is_string($data['$sesid'][1])) {
                                $browser['session_id'] = $data['$sesid'][1];
                            }
                        }
                    }

                    // Admin logado vira admin_<id> — o mesmo ID que o posthog.identify() usa
                    // no painel, então browser e servidor viram a mesma pessoa.
                    // O cidadão continua anônimo: cai no distinct_id de dispositivo do cookie,
                    // que não tem nome nem CPF.
                    $adminId    = $_SESSION['admin_id'] ?? null;
                    $distinctId = $adminId ? 'admin_' . $adminId : $browser['distinct_id'];

                    return [
                        'distinctId' => $distinctId,
                        'properties' => [
                            // Caminho sem query string: protocolo e CPF costumam viajar
                            // na URL e não podem virar propriedade de evento.
                            '$current_url'      => strtok($_SERVER['REQUEST_URI'] ?? '', '?'),
                            '$request_method'   => $_SERVER['REQUEST_METHOD'] ?? null,
                            '$exception_source' => $payload['source'] ?? null,
                            // Amarra o erro à gravação/linha do tempo da sessão no PostHog.
                            '$session_id'       => $browser['session_id'],
                            'ambiente'          => defined('MODO_HOMOLOG') && MODO_HOMOLOG ? 'homologacao' : 'producao',
                            'area'              => strpos($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') !== false ? 'admin' : 'publico',
                        ],
                    ];
                },
            ],
        ]);
    } catch (\Throwable $e) {
        // Silêncio proposital: o error tracking não pode ser a causa de um erro.
        return;
    }
})();
