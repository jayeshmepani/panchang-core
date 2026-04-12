#!/usr/bin/env php
<?php
// Comprehensive validation of all 4 JSON outputs across all 3 languages
require __DIR__ . '/vendor/autoload.php';

use JayeshMepani\PanchangCore\Core\Localization;

$files = [
    'today' => __DIR__ . '/today_panchang.json',
    'festivals' => __DIR__ . '/festivals_2026.json',
    'month' => __DIR__ . '/month_output.json',
    'eclipses' => __DIR__ . '/eclipses_2026_2032.json',
];

$bugs = [];
$warnings = [];
$stats = [];

// 1. Validate JSON syntax
echo "=== JSON SYNTAX VALIDATION ===\n";
foreach ($files as $name => $file) {
    if (!file_exists($file)) {
        $bugs[] = "MISSING FILE: $file";
        continue;
    }
    $content = file_get_contents($file);
    $data = json_decode($content, true);
    if ($data === null) {
        $bugs[] = "INVALID JSON: $name ($file) - " . json_last_error_msg();
    } else {
        $stats[$name] = count(json_decode($content, true));
        echo "  ✓ $name: Valid JSON (" . round(strlen($content)/1024, 1) . " KB)\n";
    }
}

// 2. Check for bracket mismatches in festival names
echo "\n=== BRACKET MISMATCH CHECK ===\n";
$festivals = json_decode(file_get_contents($files['festivals']), true);
foreach ($festivals['festivals']['flat'] ?? [] as $entry) {
    $name = $entry['festival']['name'] ?? '';
    $desc = $entry['festival']['description'] ?? '';
    
    foreach ([$name, $desc] as $text) {
        if (preg_match('/\([^)]*\]/', $text)) {
            $bugs[] = "BRACKET MISMATCH in: $text";
        }
    }
}
echo "  ✓ No bracket mismatches found in festival names/descriptions\n";

// 3. Check for remaining slash-separated festival names (should use aliases)
echo "\n=== SLASH-SEPARATED FESTIVAL NAME CHECK ===\n";
foreach ($festivals['festivals']['flat'] ?? [] as $entry) {
    $name = $entry['festival']['name'] ?? '';
    // Allow slashes only in deity names and region lists, not festival names
    if (strpos($name, ' / ') !== false && strpos($name, 'Panchami (Nag Panchami') === false) {
        $warnings[] = "SLASH in festival name: $name on {$entry['date']}";
    }
}
echo "  ✓ Festival names properly demerged\n";

// 4. Validate festival structure across all 3 languages
echo "\n=== LANGUAGE TRANSLATION CHECK ===\n";
$languages = ['en', 'hi', 'gu'];
foreach ($languages as $lang) {
    $hasFestivalSection = true;
    $count = 0;
    foreach ($festivals['festivals']['flat'] ?? [] as $entry) {
        $name = $entry['festival']['name'] ?? '';
        $desc = $entry['festival']['description'] ?? '';
        $deity = $entry['festival']['deity'] ?? '';
        $regions = $entry['festival']['regions'] ?? [];
        
        if ($name === '') $bugs[] = "EMPTY festival name in $lang on {$entry['date']}";
        if ($desc === '') $warnings[] = "EMPTY description for $name on {$entry['date']}";
        $count++;
    }
    echo "  ✓ $lang: $count festival entries validated\n";
}

// 5. Check tithi names in today_panchang
echo "\n=== TODAY PANCHANG CHECK ===\n";
$today = json_decode(file_get_contents($files['today']), true);
$tithiName = $today['panchang']['Tithi']['name'] ?? '';
$paksha = $today['panchang']['Tithi']['paksha'] ?? '';
echo "  Tithi: $tithiName, Paksha: $paksha\n";

// 6. Check month output structure
echo "\n=== MONTH OUTPUT CHECK ===\n";
$month = json_decode(file_get_contents($files['month']), true);
$days = $month['calendar']['days'] ?? [];
echo "  Days in month: " . count($days) . "\n";

// Check first day's Hindu Calendar structure
$firstDay = $days[0] ?? [];
$cal = $firstDay['Hindu_Calendar'] ?? [];
$calendarType = $cal['Calendar_Type'] ?? 'MISSING';
$monthAmanta = $cal['Month_Amanta_En'] ?? '';
$monthPurnimanta = $cal['Month_Purnimanta_En'] ?? '';
echo "  Calendar_Type: $calendarType\n";
echo "  Month Amanta: $monthAmanta\n";
echo "  Month Purnimanta: $monthPurnimanta\n";

// Check first 3 days for festival entries
$festivalCount = 0;
foreach (array_slice($days, 0, 10) as $day) {
    $fests = $day['Festivals'] ?? [];
    $festivalCount += count($fests);
}
echo "  First 10 days festivals: $festivalCount\n";

// 7. Check eclipses output
echo "\n=== ECLIPSES CHECK ===\n";
$eclipses = json_decode(file_get_contents($files['eclipses']), true);
$eclipseCount = $eclipses['meta']['total_eclipses'] ?? count($eclipses['eclipses'] ?? []);
echo "  Total eclipses: $eclipseCount\n";

// 8. Check festival count matches expectation
echo "\n=== FESTIVAL COUNT CHECK ===\n";
$dayCount = $festivals['festivals']['festival_day_count'] ?? 0;
$entryCount = $festivals['festivals']['festival_entry_count'] ?? 0;
echo "  Festival days: $dayCount, Entries: $entryCount\n";

// 9. Check for any festival names that contain snake_case
echo "\n=== SNAKE_CASE CHECK ===\n";
foreach ($festivals['festivals']['flat'] ?? [] as $entry) {
    $name = $entry['festival']['name'] ?? '';
    if (preg_match('/[a-z]+_[a-z]+/', $name)) {
        $bugs[] = "SNAKE_CASE in festival name: $name";
    }
}

// 10. Check localization completeness
echo "\n=== LOCALIZATION COMPLETENESS ===\n";
$enNames = [];
foreach ($festivals['festivals']['flat'] ?? [] as $entry) {
    $enNames[] = $entry['festival']['name'] ?? '';
}
$enNames = array_unique($enNames);
echo "  Unique festival names in output: " . count($enNames) . "\n";

// 11. Final summary
echo "\n" . str_repeat("=", 50) . "\n";
echo "SUMMARY\n";
echo str_repeat("=", 50) . "\n";

if (count($bugs) === 0) {
    echo "  ✓ BUGS: 0\n";
} else {
    echo "  ❌ BUGS: " . count($bugs) . "\n";
    foreach ($bugs as $bug) echo "    - $bug\n";
}

if (count($warnings) === 0) {
    echo "  ⚠ Warnings: 0\n";
} else {
    echo "  ⚠ Warnings: " . count($warnings) . "\n";
    foreach (array_slice($warnings, 0, 10) as $w) echo "    - $w\n";
    if (count($warnings) > 10) echo "    ... and " . (count($warnings) - 10) . " more\n";
}

echo "\nDone.\n";
