<?php

/**
 * Converte a saída JUnit XML do PHPUnit para JSON estruturado
 * lido pela página admin/testes.php.
 * Suporta testsuites aninhados (formato do PHPUnit 11+).
 *
 * Uso: php tests/helpers/junit-to-json.php [junit.xml] [out.json]
 */

$inputFile  = $argv[1] ?? __DIR__ . '/../results/phpunit-junit.xml';
$outputFile = $argv[2] ?? __DIR__ . '/../results/phpunit-results.json';

if (!file_exists($inputFile)) {
    $empty = [
        'summary' => ['total' => 0, 'passed' => 0, 'failed' => 0, 'errors' => 0, 'skipped' => 0, 'duration_ms' => 0],
        'suites' => [],
    ];
    file_put_contents($outputFile, json_encode($empty, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    exit(0);
}

$xml = simplexml_load_file($inputFile);
if (!$xml) {
    fwrite(STDERR, "Erro ao ler JUnit XML: {$inputFile}\n");
    exit(1);
}

$result = [
    'summary' => ['total' => 0, 'passed' => 0, 'failed' => 0, 'errors' => 0, 'skipped' => 0, 'duration_ms' => 0],
    'suites' => [],
];

/**
 * Verifica se um <testsuite> contém diretamente <testcase> (é folha = classe de teste).
 */
function isLeafSuite(SimpleXMLElement $suite): bool
{
    return count($suite->testcase) > 0;
}

/**
 * Processa a árvore de <testsuite> recursivamente.
 * Só adiciona suítes que sejam folhas (contêm <testcase>).
 */
function processSuite(SimpleXMLElement $suite, array &$result): void
{
    if (isLeafSuite($suite)) {
        // Coleta todos os testcases desta suíte (incluindo de sub-suítes de data providers)
        $suiteData = buildSuiteData($suite);
        $result['suites'][] = $suiteData;
        $result['summary']['total']       += $suiteData['total'];
        $result['summary']['passed']      += $suiteData['passed'];
        $result['summary']['failed']      += $suiteData['failed'];
        $result['summary']['errors']      += $suiteData['errors'];
        $result['summary']['skipped']     += $suiteData['skipped'];
        $result['summary']['duration_ms'] += $suiteData['duration_ms'];
        return;
    }

    // Suíte container — desce na árvore
    foreach ($suite->testsuite as $child) {
        processSuite($child, $result);
    }
}

/**
 * Monta o array de dados de uma suíte-folha, coletando testcases
 * recursivamente (data providers geram sub-suítes).
 */
function buildSuiteData(SimpleXMLElement $suite): array
{
    $name = (string) ($suite['name'] ?? 'Unnamed');
    // Remove prefixo de caminho absoluto do nome
    $name = preg_replace('/^.*\\\\|^.*\//', '', $name);

    $data = [
        'name'        => $name,
        'total'       => 0,
        'passed'      => 0,
        'failed'      => 0,
        'errors'      => 0,
        'skipped'     => 0,
        'duration_ms' => 0,
        'tests'       => [],
    ];

    collectTestcases($suite, $data);
    return $data;
}

/**
 * Coleta testcases de uma suíte e suas sub-suítes (data providers).
 */
function collectTestcases(SimpleXMLElement $suite, array &$data): void
{
    foreach ($suite->testcase as $tc) {
        $status  = 'passed';
        $message = null;

        if (isset($tc->failure)) {
            $status  = 'failed';
            $message = trim((string) $tc->failure);
        } elseif (isset($tc->error)) {
            $status  = 'error';
            $message = trim((string) $tc->error);
        } elseif (isset($tc->skipped)) {
            $status = 'skipped';
        }

        $durationMs = (int) round((float) ($tc['time'] ?? 0) * 1000);

        $data['tests'][] = [
            'name'        => (string) ($tc['name'] ?? ''),
            'class'       => (string) ($tc['classname'] ?? ''),
            'status'      => $status,
            'duration_ms' => $durationMs,
            'message'     => $message,
        ];

        $data['total']       += 1;
        $data['duration_ms'] += $durationMs;

        match ($status) {
            'passed'  => $data['passed']++,
            'failed'  => $data['failed']++,
            'error'   => $data['errors']++ & $data['failed']++,
            'skipped' => $data['skipped']++,
            default   => null,
        };
    }

    // Sub-suítes (data providers geram <testsuite> dentro da suíte-classe)
    foreach ($suite->testsuite as $child) {
        collectTestcases($child, $data);
    }
}

// Percorre a árvore a partir do <testsuites> raiz
foreach ($xml->testsuite as $topSuite) {
    processSuite($topSuite, $result);
}

file_put_contents($outputFile, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "PHPUnit JSON salvo em: {$outputFile} ({$result['summary']['total']} testes)\n";
