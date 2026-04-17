<?php

declare(strict_types=1);
// regenerate_all_json.php
// This script regenerates all 30 JSON files for the Panchang Core project.

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
        exec('php ' . escapeshellarg($scriptsDir . DIRECTORY_SEPARATOR . 'panchang_today.php'));
        if (file_exists('today_panchang.json')) {
            rename('today_panchang.json', $targetDir . DIRECTORY_SEPARATOR . 'today.json');
        }

        echo "Running panchang_festivals.php...\n";
        exec('php ' . escapeshellarg($scriptsDir . DIRECTORY_SEPARATOR . 'panchang_festivals.php') . ' 2026');
        if (file_exists('festivals_2026.json')) {
            rename('festivals_2026.json', $targetDir . DIRECTORY_SEPARATOR . 'festivals_2026.json');
        }

        echo "Running panchang_eclipses.php...\n";
        exec('php ' . escapeshellarg($scriptsDir . DIRECTORY_SEPARATOR . 'panchang_eclipses.php') . ' 2026 2032');
        if (file_exists('eclipses_2026_2032.json')) {
            rename('eclipses_2026_2032.json', $targetDir . DIRECTORY_SEPARATOR . 'eclipses_2026_2032.json');
        }

        echo "Running panchang_month_output.php...\n";
        $monthOutput = shell_exec('php ' . escapeshellarg($scriptsDir . DIRECTORY_SEPARATOR . 'panchang_month_output.php') . ' 2026 4');
        file_put_contents($targetDir . DIRECTORY_SEPARATOR . 'month_2026_04.json', $monthOutput);

        echo "Running panchang_raw_output.php...\n";
        $rawOutput = shell_exec('php ' . escapeshellarg($scriptsDir . DIRECTORY_SEPARATOR . 'panchang_raw_output.php'));
        file_put_contents($targetDir . DIRECTORY_SEPARATOR . 'raw_output_2026_2032.json', $rawOutput);
    }
}

echo "Bulk generation complete! Files are located in $outputBaseDir\n";
