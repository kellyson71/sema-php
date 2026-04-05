<?php

/**
 * Normaliza o JSON gerado pelo Playwright (--reporter=json) para o formato
 * consumido pela página admin/testes.php.
 *
 * Uso: php tests/helpers/playwright-to-json.php [playwright-raw.json] [out.json]
 */

$inputFile  = $argv[1] ?? __DIR__ . '/../results/playwright-raw.json';
$outputFile = $argv[2] ?? __DIR__ . '/../results/playwright-results.json';

if (!file_exists($inputFile)) {
    $empty = [
        'summary' => ['total' => 0, 'passed' => 0, 'failed' => 0, 'skipped' => 0, 'duration_ms' => 0],
        'suites'  => [],
    ];
    file_put_contents($outputFile, json_encode($empty, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    exit(0);
}

$raw = json_decode(file_get_contents($inputFile), true);
if (!$raw) {
    fwrite(STDERR, "Erro ao ler JSON do Playwright: {$inputFile}\n");
    exit(1);
}

$result = [
    'summary' => ['total' => 0, 'passed' => 0, 'failed' => 0, 'skipped' => 0, 'duration_ms' => 0],
    'suites'  => [],
];

function processPlaywrightSuites(array $suites, array &$result): void
{
    foreach ($suites as $suite) {
        if (!empty($suite['specs'])) {
            $suiteData = [
                'name'        => $suite['title'] ?? 'Unnamed',
                'file'        => $suite['file']  ?? '',
                'total'       => 0,
                'passed'      => 0,
                'failed'      => 0,
                'skipped'     => 0,
                'duration_ms' => 0,
                'tests'       => [],
            ];

            foreach ($suite['specs'] as $spec) {
                foreach ($spec['tests'] ?? [] as $test) {
                    $status = match ($test['status'] ?? 'unknown') {
                        'expected'   => 'passed',
                        'unexpected' => 'failed',
                        'skipped'    => 'skipped',
                        default      => 'unknown',
                    };

                    $duration = 0;
                    $message  = null;
                    foreach ($test['results'] ?? [] as $r) {
                        $duration += $r['duration'] ?? 0;
                        if (!empty($r['error']['message'])) {
                            $message = $r['error']['message'];
                        }
                    }

                    $suiteData['tests'][] = [
                        'name'        => $spec['title'] ?? '',
                        'status'      => $status,
                        'duration_ms' => $duration,
                        'message'     => $message,
                        'retry'       => $test['retry'] ?? 0,
                    ];

                    $suiteData['total']++;
                    $suiteData['duration_ms'] += $duration;
                    if ($status === 'passed')  $suiteData['passed']++;
                    if ($status === 'failed')  $suiteData['failed']++;
                    if ($status === 'skipped') $suiteData['skipped']++;

                    $result['summary']['total']++;
                    $result['summary']['duration_ms'] += $duration;
                    if ($status === 'passed')  $result['summary']['passed']++;
                    if ($status === 'failed')  $result['summary']['failed']++;
                    if ($status === 'skipped') $result['summary']['skipped']++;
                }
            }

            if ($suiteData['total'] > 0) {
                $result['suites'][] = $suiteData;
            }
        }

        // Recursivo para suítes aninhadas
        if (!empty($suite['suites'])) {
            processPlaywrightSuites($suite['suites'], $result);
        }
    }
}

processPlaywrightSuites($raw['suites'] ?? [], $result);

file_put_contents($outputFile, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "Playwright JSON salvo em: {$outputFile}\n";
