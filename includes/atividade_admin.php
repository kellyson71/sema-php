<?php
/**
 * Rastro de uso do painel admin: quem abriu o quê, quando, quanto tempo ficou ativo.
 *
 * Serve a duas coisas: a tela admin/atividade_usuarios.php (gráfico estilo GitHub por
 * usuário) e a reprodução de erro — com admin_eventos em ordem cronológica dá para
 * refazer o caminho da pessoa, e posthog_session_id liga cada passo à gravação da
 * sessão no PostHog.
 *
 * Carregado por includes/error_monitoring.php (que o config.php de cada ambiente já
 * inclui), então vale para toda requisição sem precisar mexer em arquivo fora do git.
 *
 * Regra de privacidade: nunca grava valor enviado em formulário. Só a página, o NOME
 * da ação (campo acao/action), o id numérico do registro, status HTTP e duração.
 *
 * Como o error tracking, nunca pode derrubar a página: tudo engole Throwable, e sem
 * a migration 2026-09-25_atividade_admin.sql simplesmente não grava nada.
 */

const ATIVIDADE_RETENCAO_DIAS = 180;
const ATIVIDADE_MAX_SEGUNDOS_POR_PING = 120;

function atividadePaginaRelativa(string $scriptName): string
{
    $pos = strpos($scriptName, '/admin/');
    $pagina = $pos === false ? ltrim($scriptName, '/') : substr($scriptName, $pos + 1);
    return mb_substr($pagina, 0, 190);
}

function atividadeNomeAcao(array $get, array $post, string $metodo, string $pagina): ?string
{
    foreach ([$post['acao'] ?? null, $post['action'] ?? null, $get['acao'] ?? null, $get['action'] ?? null] as $valor) {
        if (is_scalar($valor) && trim((string) $valor) !== '') {
            $limpo = preg_replace('/[^a-z0-9_\-:.]/i', '', (string) $valor) ?? '';
            if ($limpo !== '') {
                return mb_substr($limpo, 0, 120);
            }
        }
    }
    if ($metodo === 'POST') {
        return basename($pagina, '.php');
    }
    return null;
}

/** @return array{0: ?string, 1: ?int} */
function atividadeEntidade(array $get, array $post, string $pagina): array
{
    $explicitas = [
        'requerimento_id' => 'requerimento',
        'denuncia_id' => 'denuncia',
        'notificacao_id' => 'notificacao_obra',
        'admin_id' => 'administrador',
    ];
    foreach ($explicitas as $campo => $entidade) {
        $id = (int) ($post[$campo] ?? $get[$campo] ?? 0);
        if ($id > 0) {
            return [$entidade, $id];
        }
    }

    $id = (int) ($post['id'] ?? $get['id'] ?? 0);
    if ($id <= 0) {
        return [null, null];
    }
    $nome = strtolower(basename($pagina));
    $entidade = match (true) {
        str_contains($nome, 'denuncia') => 'denuncia',
        str_contains($nome, 'requerimento') => 'requerimento',
        str_contains($nome, 'administrador') => 'administrador',
        str_contains($nome, 'notificac') || str_contains($pagina, 'fiscalizacao_obras') => 'notificacao_obra',
        default => 'registro',
    };
    return [$entidade, $id];
}

function atividadeTipo(string $metodo, string $pagina, bool $ajax, int $statusHttp, bool $erroFatal): string
{
    if ($erroFatal || $statusHttp >= 500) {
        return 'erro';
    }
    if ($ajax || str_contains($pagina, '/ajax/')) {
        return 'ajax';
    }
    return $metodo === 'POST' ? 'acao' : 'pagina';
}

/** Mensagem de erro sem sequências longas de dígitos (CPF, telefone, protocolo). */
function atividadeMensagemErroSegura(string $mensagem): string
{
    $mensagem = preg_replace('/\d[\d.\-\/]{4,}/', '#', $mensagem) ?? '';
    return mb_substr($mensagem, 0, 255);
}

function atividadeFormatarDuracao(int $segundos): string
{
    if ($segundos < 60) {
        return $segundos > 0 ? 'menos de 1 min' : '0 min';
    }
    $horas = intdiv($segundos, 3600);
    $minutos = intdiv($segundos % 3600, 60);
    if ($horas === 0) {
        return $minutos . ' min';
    }
    return $horas . ' h' . ($minutos > 0 ? ' ' . $minutos . ' min' : '');
}

/**
 * Grade do gráfico estilo GitHub: colunas = semanas (domingo a sábado), terminando
 * na semana de $hoje. Nível 0-4 proporcional ao maior valor do período.
 *
 * @param array<string, int> $porDia 'Y-m-d' => valor
 * @return list<list<array{data: string, valor: int, nivel: int, futuro: bool}>>
 */
function atividadeGradeHeatmap(array $porDia, DateTimeImmutable $hoje, int $semanas = 53): array
{
    $fimSemana = $hoje->modify('saturday this week');
    if ((int) $hoje->format('w') === 6) {
        $fimSemana = $hoje;
    }
    $inicio = $fimSemana->modify('-' . ($semanas * 7 - 1) . ' days');

    $maximo = 0;
    for ($d = $inicio; $d <= $hoje; $d = $d->modify('+1 day')) {
        $maximo = max($maximo, (int) ($porDia[$d->format('Y-m-d')] ?? 0));
    }

    $grade = [];
    $dia = $inicio;
    for ($s = 0; $s < $semanas; $s++) {
        $coluna = [];
        for ($i = 0; $i < 7; $i++) {
            $chave = $dia->format('Y-m-d');
            $valor = (int) ($porDia[$chave] ?? 0);
            $nivel = ($valor <= 0 || $maximo <= 0) ? 0 : min(4, (int) ceil(4 * $valor / $maximo));
            $coluna[] = ['data' => $chave, 'valor' => $valor, 'nivel' => $nivel, 'futuro' => $dia > $hoje];
            $dia = $dia->modify('+1 day');
        }
        $grade[] = $coluna;
    }
    return $grade;
}

function atividadeSomarDiario(PDO $pdo, int $adminId, array $incrementos, ?DateTimeImmutable $quando = null): void
{
    $quando ??= new DateTimeImmutable();
    $campos = ['segundos_ativos', 'acoes', 'paginas', 'erros'];
    $valores = [];
    foreach ($campos as $campo) {
        $valores[$campo] = max(0, (int) ($incrementos[$campo] ?? 0));
    }
    $agora = $quando->format('Y-m-d H:i:s');
    $pdo->prepare(
        'INSERT INTO admin_atividade_diaria
            (admin_id, dia, segundos_ativos, acoes, paginas, erros, primeira_atividade, ultima_atividade)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            segundos_ativos = segundos_ativos + VALUES(segundos_ativos),
            acoes = acoes + VALUES(acoes),
            paginas = paginas + VALUES(paginas),
            erros = erros + VALUES(erros),
            primeira_atividade = LEAST(COALESCE(primeira_atividade, VALUES(primeira_atividade)), VALUES(primeira_atividade)),
            ultima_atividade = GREATEST(COALESCE(ultima_atividade, VALUES(ultima_atividade)), VALUES(ultima_atividade))'
    )->execute([
        $adminId, $quando->format('Y-m-d'),
        $valores['segundos_ativos'], $valores['acoes'], $valores['paginas'], $valores['erros'],
        $agora, $agora,
    ]);
}

function atividadeRegistrarTempo(PDO $pdo, int $adminId, int $segundos): int
{
    $segundos = max(0, min(ATIVIDADE_MAX_SEGUNDOS_POR_PING, $segundos));
    if ($adminId > 0 && $segundos > 0) {
        atividadeSomarDiario($pdo, $adminId, ['segundos_ativos' => $segundos]);
    }
    return $segundos;
}

function atividadeLimparAntigos(PDO $pdo): void
{
    $pdo->exec('DELETE FROM admin_eventos WHERE ocorrido_em < NOW() - INTERVAL ' . ATIVIDADE_RETENCAO_DIAS . ' DAY LIMIT 5000');
}

function atividadePosthogSessionId(): ?string
{
    $sid = (string) ($_COOKIE['sema_ph_sid'] ?? '');
    return preg_match('/^[A-Za-z0-9\-]{8,64}$/', $sid) ? $sid : null;
}

function atividadeRegistrarRequisicao(PDO $pdo): void
{
    $adminId = (int) ($_SESSION['admin_id'] ?? 0);
    if ($adminId <= 0) {
        return;
    }

    $pagina = atividadePaginaRelativa((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $metodo = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $ajax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) !== ''
        || str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
    $statusHttp = (int) (http_response_code() ?: 200);

    $ultimoErro = error_get_last();
    $erroFatal = $ultimoErro !== null
        && in_array($ultimoErro['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true);

    $tipo = atividadeTipo($metodo, $pagina, $ajax, $statusHttp, $erroFatal);
    [$entidade, $entidadeId] = atividadeEntidade($_GET, $_POST, $pagina);
    $inicio = (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));

    $pdo->prepare(
        'INSERT INTO admin_eventos
            (admin_id, ocorrido_em, tipo, metodo, pagina, acao, entidade, entidade_id,
             status_http, duracao_ms, erro, posthog_session_id, nivel, simulando)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $adminId,
        date('Y-m-d H:i:s', (int) $inicio) . sprintf('.%03d', (int) (($inicio - floor($inicio)) * 1000)),
        $tipo,
        mb_substr($metodo, 0, 8),
        $pagina,
        atividadeNomeAcao($_GET, $_POST, $metodo, $pagina),
        $entidade,
        $entidadeId,
        $statusHttp,
        (int) round((microtime(true) - $inicio) * 1000),
        $erroFatal ? atividadeMensagemErroSegura((string) $ultimoErro['message']) : null,
        atividadePosthogSessionId(),
        mb_substr((string) ($_SESSION['admin_nivel'] ?? ''), 0, 30),
        isset($_SESSION['admin_nivel_original']) ? 1 : 0,
    ]);

    atividadeSomarDiario($pdo, $adminId, [
        'acoes' => $tipo === 'acao' ? 1 : 0,
        'paginas' => $tipo === 'pagina' ? 1 : 0,
        'erros' => $tipo === 'erro' ? 1 : 0,
    ]);
}

(static function (): void {
    if (PHP_SAPI === 'cli') {
        return;
    }
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    if (!str_contains($script, '/admin/') || str_ends_with($script, '/ajax/atividade_ping.php')) {
        return;
    }

    register_shutdown_function(static function (): void {
        try {
            if (empty($_SESSION['admin_id'])) {
                return;
            }
            $pdo = $GLOBALS['pdo'] ?? null;
            if (!$pdo instanceof PDO) {
                return;
            }
            atividadeRegistrarRequisicao($pdo);
        } catch (\Throwable $e) {
            // Rastro de uso nunca pode ser, ele mesmo, a causa de um erro.
        }
    });
})();
