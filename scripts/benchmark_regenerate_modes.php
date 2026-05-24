#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Benchmark JSON regeneration across JME modes, calendar types, and locales.
 *
 * Output:
 * - scripts/output/benchmark/regenerate_modes.csv
 * - scripts/output/benchmark/regenerate_modes.json
 */
$baseDir = is_file(__DIR__ . '/../vendor/autoload.php') ? dirname(__DIR__) : __DIR__;
$scriptsDir = $baseDir . DIRECTORY_SEPARATOR . 'scripts';
$benchmarkDir = $scriptsDir . DIRECTORY_SEPARATOR . 'output' . DIRECTORY_SEPARATOR . 'benchmark';
$memoryFooter = $benchmarkDir . DIRECTORY_SEPARATOR . 'memory_footer.php';

if (! is_dir($benchmarkDir)) {
    mkdir($benchmarkDir, 0777, true);
}

file_put_contents(
    $memoryFooter,
    "<?php fwrite(STDERR, \"\\n__PANCHANG_BENCH_PEAK_MEMORY__=\" . memory_get_peak_usage(true) . \"\\n\");\n"
);

$modes = [
    'moshier' => null,
    'jpl' => getenv('PANCHANG_BENCH_JPL_KERNEL') ?: null,
    'vsop_elp_meeus' => null,
];
$calendarTypes = ['amanta', 'purnimanta'];
$locales = ['en', 'hi', 'gu'];

$tasks = [
    'today' => ['label' => 'panchang_today.php', 'args' => []],
    'festival' => ['label' => 'panchang_festivals.php', 'args' => ['2026']],
    'eclipse' => ['label' => 'panchang_eclipses.php', 'args' => ['2026', '2032']],
    'month' => ['label' => 'panchang_month_output.php', 'args' => ['2026', '4'], 'capture_stdout' => true],
];

$rows = [];

foreach ($modes as $mode => $ephePath) {
    foreach ($calendarTypes as $calendarType) {
        foreach ($locales as $locale) {
            echo "--- Benchmark mode={$mode}, calendar={$calendarType}, locale={$locale} ---" . PHP_EOL;

            foreach ($tasks as $taskName => $task) {
                $scriptPath = $scriptsDir . DIRECTORY_SEPARATOR . $task['label'];
                $command = buildPhpCommand($scriptPath, $task['args'], $memoryFooter);
                $env = [
                    'PANCHANG_ENGINE' => 'jme',
                    'PANCHANG_JME_MODE' => $mode,
                    'PANCHANG_CALENDAR_TYPE' => $calendarType,
                    'PANCHANG_LOCALE' => $locale,
                ];
                if (is_string($ephePath)) {
                    $env['PANCHANG_EPHE_PATH'] = $ephePath;
                }

                echo "Running {$task['label']}..." . PHP_EOL;
                $result = runBenchCommand($command, $baseDir, $env, (bool) ($task['capture_stdout'] ?? false));
                $row = [
                    'mode' => $mode,
                    'calendar_type' => $calendarType,
                    'locale' => $locale,
                    'script' => $taskName,
                    'label' => $task['label'],
                    'exit_code' => $result['exit_code'],
                    'elapsed_s' => $result['elapsed_s'],
                    'peak_memory_bytes' => $result['peak_memory_bytes'],
                    'peak_memory_mb' => $result['peak_memory_bytes'] !== null
                        ? round($result['peak_memory_bytes'] / 1048576, 3)
                        : null,
                ];
                $rows[] = $row;

                printf(
                    "[bench] %s exit=%d elapsed_s=%.6f peak_mb=%s\n",
                    $task['label'],
                    $result['exit_code'],
                    $result['elapsed_s'],
                    $row['peak_memory_mb'] === null ? 'n/a' : number_format((float) $row['peak_memory_mb'], 3, '.', '')
                );

                if ($result['exit_code'] !== 0) {
                    throw new RuntimeException("{$task['label']} failed for mode={$mode}, calendar={$calendarType}, locale={$locale}");
                }
            }
        }
    }
}

writeCsv($benchmarkDir . DIRECTORY_SEPARATOR . 'regenerate_modes.csv', $rows);
file_put_contents(
    $benchmarkDir . DIRECTORY_SEPARATOR . 'regenerate_modes.json',
    json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
);
if (is_file($memoryFooter)) {
    unlink($memoryFooter);
}

echo 'Benchmark complete: ' . $benchmarkDir . PHP_EOL;

function buildPhpCommand(string $scriptPath, array $args, string $memoryFooter): string
{
    $parts = [
        'php',
        '-d',
        'auto_append_file=' . escapeshellarg($memoryFooter),
        escapeshellarg($scriptPath),
    ];
    foreach ($args as $arg) {
        $parts[] = escapeshellarg((string) $arg);
    }

    return implode(' ', $parts);
}

/**
 * @param array<string, string> $env
 *
 * @return array{exit_code: int, elapsed_s: float, peak_memory_bytes: ?int}
 */
function runBenchCommand(string $command, string $workingDir, array $env, bool $captureStdout): array
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $childEnv = array_filter(
        array_merge($_ENV, $_SERVER, $env),
        static fn (mixed $value): bool => is_string($value)
    );
    $start = hrtime(true);
    $process = proc_open($command, $descriptors, $pipes, $workingDir, $childEnv);
    if (! is_resource($process)) {
        throw new RuntimeException("Failed to start benchmark command: {$command}");
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $stderr = '';
    $stdoutOpen = true;
    $stderrOpen = true;

    while ($stdoutOpen || $stderrOpen) {
        $read = [];
        if ($stdoutOpen) {
            $read[] = $pipes[1];
        }
        if ($stderrOpen) {
            $read[] = $pipes[2];
        }
        if ($read === []) {
            break;
        }

        $write = null;
        $except = null;
        $changed = stream_select($read, $write, $except, 1, 0);
        if ($changed === false) {
            break;
        }

        if ($changed > 0) {
            foreach ($read as $stream) {
                $chunk = stream_get_contents($stream);
                if ($chunk === false || $chunk === '') {
                    continue;
                }
                if ($stream === $pipes[2]) {
                    $stderr .= $chunk;
                    $clean = preg_replace('/__PANCHANG_BENCH_PEAK_MEMORY__=\d+\s*/', '', $chunk);
                    if (is_string($clean) && trim($clean) !== '') {
                        fwrite(STDERR, $clean);
                    }
                } elseif (! $captureStdout) {
                    echo $chunk;
                }
            }
        }

        if ($stdoutOpen && feof($pipes[1])) {
            fclose($pipes[1]);
            $stdoutOpen = false;
        }
        if ($stderrOpen && feof($pipes[2])) {
            fclose($pipes[2]);
            $stderrOpen = false;
        }
    }

    $exitCode = proc_close($process);
    $elapsed = (hrtime(true) - $start) / 1_000_000_000;

    preg_match_all('/__PANCHANG_BENCH_PEAK_MEMORY__=(\d+)/', $stderr, $matches);
    $peakMemory = isset($matches[1][0]) ? (int) $matches[1][0] : null;

    return [
        'exit_code' => $exitCode,
        'elapsed_s' => $elapsed,
        'peak_memory_bytes' => $peakMemory,
    ];
}

/**
 * @param array<int, array<string, mixed>> $rows
 */
function writeCsv(string $path, array $rows): void
{
    $handle = fopen($path, 'w');
    if ($handle === false) {
        throw new RuntimeException("Failed to open CSV for writing: {$path}");
    }

    $headers = array_keys($rows[0] ?? []);
    fputcsv($handle, $headers);
    foreach ($rows as $row) {
        fputcsv($handle, array_map(
            static fn (mixed $value): string => $value === null ? '' : (string) $value,
            $row
        ));
    }
    fclose($handle);
}
