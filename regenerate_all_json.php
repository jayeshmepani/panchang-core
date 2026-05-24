<?php

declare(strict_types=1);
// regenerate_all_json.php
// This script regenerates all 30 JSON files for the Panchang Core project.

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

$calendarTypes = ['amanta', 'purnimanta'];
$locales = ['en', 'hi', 'gu'];
$scriptsDir = __DIR__ . DIRECTORY_SEPARATOR . 'scripts';
$outputBaseDir = $scriptsDir . DIRECTORY_SEPARATOR . 'output';

// Ensure output base directory exists
if (!is_dir($outputBaseDir)) {
    mkdir($outputBaseDir, 0777, true);
}

foreach ($calendarTypes as $type) {
    foreach ($locales as $lang) {
        $targetDir = $outputBaseDir . DIRECTORY_SEPARATOR . $type . DIRECTORY_SEPARATOR . $lang;
        echo "--- Generating for Calendar: $type, Locale: $lang ---\n";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        // Set environment variables for the current process
        putenv("PANCHANG_CALENDAR_TYPE=$type");
        putenv("PANCHANG_LOCALE=$lang");

        echo "Running panchang_today.php...\n";
        if (getenv('PANCHANG_DEBUG')) {
            echo 'Debug enabled: PANCHANG_DEBUG=' . getenv('PANCHANG_DEBUG') . "\n";
        }
        $exitCode = runPhpScript(
            'panchang_today.php',
            'php ' . escapeshellarg($scriptsDir . DIRECTORY_SEPARATOR . 'panchang_today.php'),
            __DIR__
        );
        if ($exitCode !== 0) {
            throw new RuntimeException("panchang_today.php failed with exit code {$exitCode}");
        }
        if (file_exists('today_panchang.json')) {
            rename('today_panchang.json', $targetDir . DIRECTORY_SEPARATOR . 'today.json');
        }

        echo "Running panchang_festivals.php...\n";
        $exitCode = runPhpScript(
            'panchang_festivals.php',
            'php ' . escapeshellarg($scriptsDir . DIRECTORY_SEPARATOR . 'panchang_festivals.php') . ' 2026',
            __DIR__
        );
        if ($exitCode !== 0) {
            throw new RuntimeException("panchang_festivals.php failed with exit code {$exitCode}");
        }
        if (file_exists('festivals_2026.json')) {
            rename('festivals_2026.json', $targetDir . DIRECTORY_SEPARATOR . 'festivals_2026.json');
        }

        echo "Running panchang_eclipses.php...\n";
        $exitCode = runPhpScript(
            'panchang_eclipses.php',
            'php ' . escapeshellarg($scriptsDir . DIRECTORY_SEPARATOR . 'panchang_eclipses.php') . ' 2026 2032',
            __DIR__
        );
        if ($exitCode !== 0) {
            throw new RuntimeException("panchang_eclipses.php failed with exit code {$exitCode}");
        }
        if (file_exists('eclipses_2026_2032.json')) {
            rename('eclipses_2026_2032.json', $targetDir . DIRECTORY_SEPARATOR . 'eclipses_2026_2032.json');
        }

        echo "Running panchang_month_output.php...\n";
        $monthResult = capturePhpScript(
            'panchang_month_output.php',
            'php ' . escapeshellarg($scriptsDir . DIRECTORY_SEPARATOR . 'panchang_month_output.php') . ' 2026 4',
            __DIR__
        );
        if ($monthResult['exit_code'] !== 0) {
            throw new RuntimeException('panchang_month_output.php failed with exit code ' . $monthResult['exit_code']);
        }
        file_put_contents($targetDir . DIRECTORY_SEPARATOR . 'month_2026_04.json', (string) $monthResult['stdout']);
        $monthDecoded = json_decode((string) $monthResult['stdout'], true);
        $monthDayCount = is_array($monthDecoded['calendar'] ?? null) ? count($monthDecoded['calendar']) : 0;
        echo "Written month_2026_04.json — {$monthDayCount} calendar days.\n";

        // echo "Running panchang_raw_output.php...\n";
        // $rawOutput = shell_exec('php ' . escapeshellarg($scriptsDir . DIRECTORY_SEPARATOR . 'panchang_raw_output.php'));
        // file_put_contents($targetDir . DIRECTORY_SEPARATOR . 'raw_output_2026_2032.json', $rawOutput);
    }
}

echo "Bulk generation complete! Files are located in $outputBaseDir\n";
