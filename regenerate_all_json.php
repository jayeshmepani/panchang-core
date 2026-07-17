<?php

declare(strict_types=1);
// regenerate_all_json.php
// This script regenerates all JSON files for the Panchang Core project.
// Usage: php regenerate_all_json.php [year] [month]
// Default month output: current month.

use Spatie\Fork\Fork;

require_once __DIR__ . '/vendor/autoload.php';

function runPhpScript(string $label, string $command, string $workingDir): int
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $start = hrtime(true);
    $process = proc_open($command, $descriptors, $pipes, $workingDir);
    if (! is_resource($process)) {
        throw new RuntimeException("Failed to start {$label}: {$command}");
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

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

                if ($stream !== $pipes[1]) {
                    fwrite(STDERR, $chunk);
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
    $elapsedNs = hrtime(true) - $start;
    $elapsedSeconds = number_format($elapsedNs / 1_000_000_000, 6, '.', '');
    echo "[runner] {$label} exit={$exitCode} elapsed_s={$elapsedSeconds}" . PHP_EOL;

    return $exitCode;
}

function capturePhpScript(string $label, string $command, string $workingDir): array
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $start = hrtime(true);
    $process = proc_open($command, $descriptors, $pipes, $workingDir);
    if (! is_resource($process)) {
        throw new RuntimeException("Failed to start {$label}: {$command}");
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $stdout = '';
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

                if ($stream === $pipes[1]) {
                    $stdout .= $chunk;
                } else {
                    fwrite(STDERR, $chunk);
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
    $elapsedNs = hrtime(true) - $start;
    $elapsedSeconds = number_format($elapsedNs / 1_000_000_000, 6, '.', '');
    echo "[runner] {$label} exit={$exitCode} elapsed_s={$elapsedSeconds}" . PHP_EOL;

    return [
        'exit_code' => $exitCode,
        'stdout' => $stdout,
    ];
}

function generateCalendarLocale(
    string $calendarType,
    string $locale,
    string $scriptsDir,
    string $outputBaseDir,
    int $monthYear,
    int $month,
    string $monthFile,
): array {
    $targetDir = $outputBaseDir . DIRECTORY_SEPARATOR . $calendarType . DIRECTORY_SEPARATOR . $locale;
    $jobLabel = "{$calendarType}/{$locale}";
    $startedAt = hrtime(true);

    if (! is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    putenv("PANCHANG_CALENDAR_TYPE={$calendarType}");
    putenv("PANCHANG_LOCALE={$locale}");
    $_ENV['PANCHANG_CALENDAR_TYPE'] = $calendarType;
    $_ENV['PANCHANG_LOCALE'] = $locale;
    $_SERVER['PANCHANG_CALENDAR_TYPE'] = $calendarType;
    $_SERVER['PANCHANG_LOCALE'] = $locale;

    echo "--- Generating for Calendar: {$calendarType}, Locale: {$locale} ---" . PHP_EOL;
    if (getenv('PANCHANG_DEBUG')) {
        echo "[{$jobLabel}] Debug enabled: PANCHANG_DEBUG=" . getenv('PANCHANG_DEBUG') . PHP_EOL;
    }

    $steps = [
        [
            'label' => 'panchang_today.php',
            'command' => 'php ' . escapeshellarg($scriptsDir . DIRECTORY_SEPARATOR . 'panchang_today.php'),
        ],
        [
            'label' => 'panchang_festivals.php',
            'command' => 'php ' . escapeshellarg($scriptsDir . DIRECTORY_SEPARATOR . 'panchang_festivals.php') . ' 2026',
        ],
        [
            'label' => 'panchang_festivals.php festivals',
            'command' => 'php ' . escapeshellarg($scriptsDir . DIRECTORY_SEPARATOR . 'panchang_festivals.php') . ' 2026 festivals',
        ],
        [
            'label' => 'panchang_festivals.php vrats',
            'command' => 'php ' . escapeshellarg($scriptsDir . DIRECTORY_SEPARATOR . 'panchang_festivals.php') . ' 2026 vrats',
        ],
        [
            'label' => 'panchang_eclipses.php',
            'command' => 'php ' . escapeshellarg($scriptsDir . DIRECTORY_SEPARATOR . 'panchang_eclipses.php') . ' 2026 2035',
        ],
    ];

    foreach ($steps as $step) {
        echo "[{$jobLabel}] Running {$step['label']}..." . PHP_EOL;
        $exitCode = runPhpScript(
            "{$jobLabel} {$step['label']}",
            $step['command'],
            __DIR__,
        );
        if ($exitCode !== 0) {
            throw new RuntimeException("[{$jobLabel}] {$step['label']} failed with exit code {$exitCode}");
        }
    }

    echo "[{$jobLabel}] Running panchang_month_output.php..." . PHP_EOL;
    $monthResult = capturePhpScript(
        "{$jobLabel} panchang_month_output.php",
        'php ' . escapeshellarg($scriptsDir . DIRECTORY_SEPARATOR . 'panchang_month_output.php') . ' ' . $monthYear . ' ' . $month,
        __DIR__,
    );
    if ($monthResult['exit_code'] !== 0) {
        throw new RuntimeException("[{$jobLabel}] panchang_month_output.php failed with exit code {$monthResult['exit_code']}");
    }
    file_put_contents($targetDir . DIRECTORY_SEPARATOR . $monthFile, (string) $monthResult['stdout']);
    $monthDecoded = json_decode((string) $monthResult['stdout'], true);
    $monthDayCount = is_array($monthDecoded['calendar'] ?? null) ? count($monthDecoded['calendar']) : 0;
    echo "[{$jobLabel}] Written {$monthFile} — {$monthDayCount} calendar days." . PHP_EOL;

    echo "[{$jobLabel}] Running panchang_raw_output.php..." . PHP_EOL;
    $rawResult = capturePhpScript(
        "{$jobLabel} panchang_raw_output.php",
        'php ' . escapeshellarg($scriptsDir . DIRECTORY_SEPARATOR . 'panchang_raw_output.php'),
        __DIR__,
    );
    if ($rawResult['exit_code'] !== 0) {
        throw new RuntimeException("[{$jobLabel}] panchang_raw_output.php failed with exit code {$rawResult['exit_code']}");
    }
    file_put_contents($targetDir . DIRECTORY_SEPARATOR . 'raw_output_2026_2032.json', (string) $rawResult['stdout']);

    $elapsedSeconds = number_format((hrtime(true) - $startedAt) / 1_000_000_000, 6, '.', '');

    return [
        'calendar_type' => $calendarType,
        'locale' => $locale,
        'elapsed_s' => $elapsedSeconds,
        'output_dir' => $targetDir,
    ];
}

$calendarTypes = ['amanta', 'purnimanta'];
$locales = ['en', 'hi', 'gu'];
$scriptsDir = __DIR__ . DIRECTORY_SEPARATOR . 'scripts';
$outputBaseDir = $scriptsDir . DIRECTORY_SEPARATOR . 'output';
$now = new DateTimeImmutable('now', new DateTimeZone((string) (getenv('PANCHANG_TIMEZONE') ?: 'Asia/Kolkata')));
$monthYear = isset($argv[1]) ? (int) $argv[1] : (int) $now->format('Y');
$month = isset($argv[2]) ? (int) $argv[2] : (int) $now->format('n');

if ($month < 1 || $month > 12) {
    throw new InvalidArgumentException('Month must be between 1 and 12.');
}

$monthFile = sprintf('month_%04d_%02d.json', $monthYear, $month);

// Ensure output base directory exists
if (! is_dir($outputBaseDir)) {
    mkdir($outputBaseDir, 0777, true);
}

$jobs = [];
foreach ($calendarTypes as $type) {
    foreach ($locales as $lang) {
        $jobs[] = static fn (): array => generateCalendarLocale(
            $type,
            $lang,
            $scriptsDir,
            $outputBaseDir,
            $monthYear,
            $month,
            $monthFile,
        );
    }
}

echo 'Starting ' . count($jobs) . ' parallel generation jobs...' . PHP_EOL;

$results = Fork::new()
    ->concurrent(count($jobs))
    ->run(...$jobs);

foreach ($results as $result) {
    echo sprintf(
        '[runner] completed %s/%s elapsed_s=%s output=%s',
        $result['calendar_type'],
        $result['locale'],
        $result['elapsed_s'],
        $result['output_dir'],
    ) . PHP_EOL;
}

echo "Bulk generation complete! Files are located in $outputBaseDir\n";
