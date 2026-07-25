#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Festival / vrat algorithm-parity gate for intentional code reshape.
 *
 * ALLOWED
 *   Reorganization, restructuring, refactorization, reshaping of the festival
 *   module layout (files/classes/methods only).
 *
 * FORBIDDEN (must fail this script)
 *   - Changing algorithms, classifiers, formulas, calculations, logics, conditions
 *   - Date drift for any festival/vrat
 *   - Removing any currently generated festival/vrat identity
 *   - Introducing same-day same-identity duplicates
 *   - Changing dated entry multiset or decision keys without intentional rule change
 *
 * Fingerprint line (localization-stable):
 *   date|name_key|fasting|calculation_basis.type|winning_reason_key
 *
 * Usage:
 *   php scripts/festival_parity_fingerprint.php write
 *   php scripts/festival_parity_fingerprint.php verify
 */
$baseDir = is_file(__DIR__ . '/../vendor/autoload.php') ? dirname(__DIR__) : __DIR__;
$command = strtolower((string) ($argv[1] ?? 'verify'));
$fixturePath = $baseDir . '/tests/fixtures/festival_parity/2026_bhuj_en_fingerprints.json';
$identitiesDir = $baseDir . '/tests/fixtures/festival_parity';

$targets = [
    'amanta_en_festivals' => $baseDir . '/scripts/output/amanta/en/festivals_2026.json',
    'amanta_en_festivals_only' => $baseDir . '/scripts/output/amanta/en/festivals_only_2026.json',
    'amanta_en_vrats' => $baseDir . '/scripts/output/amanta/en/vrats_2026.json',
    'purnimanta_en_festivals' => $baseDir . '/scripts/output/purnimanta/en/festivals_2026.json',
    'purnimanta_en_festivals_only' => $baseDir . '/scripts/output/purnimanta/en/festivals_only_2026.json',
    'purnimanta_en_vrats' => $baseDir . '/scripts/output/purnimanta/en/vrats_2026.json',
];

/**
 * @return array{
 *   source:string,
 *   entry_count:int,
 *   unique_identity_count:int,
 *   recurring_weekday_count:int,
 *   duplicate_same_day_identity_count:int,
 *   total_festivals_field:int|null,
 *   total_vrats_field:int|null,
 *   events_sha256:string,
 *   recurring_sha256:string,
 *   identities_sha256:string,
 *   identity_dates_sha256:string,
 *   identities:list<string>,
 *   duplicates:array<string,int>
 * }
 */
function festival_parity_analyze_file(string $path): array
{
    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException('Cannot read: ' . $path);
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new RuntimeException('Bad JSON: ' . $path);
    }

    $root = $data['festivals'] ?? $data['vrats'] ?? $data;
    if (!is_array($root)) {
        throw new RuntimeException('Missing festivals/vrats root: ' . $path);
    }

    $byDate = is_array($root['by_date'] ?? null) ? $root['by_date'] : [];
    $recurring = is_array($root['recurring_weekday_vrats'] ?? null) ? $root['recurring_weekday_vrats'] : [];

    $events = [];
    $identities = [];
    $identityDates = [];
    $pairCounts = [];

    foreach ($byDate as $date => $entries) {
        if (!is_array($entries)) {
            continue;
        }

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $nameKey = trim((string) ($entry['name_key'] ?? $entry['name'] ?? ''));
            if ($nameKey === '') {
                continue;
            }

            $reasonKey = (string) (
                $entry['rules_applied']['winning_reason_key']
                ?? $entry['resolution']['decision']['winning_reason_key']
                ?? ''
            );
            $type = (string) ($entry['calculation_basis']['type'] ?? '');
            $fasting = empty($entry['fasting']) ? '0' : '1';
            $dateStr = (string) $date;

            $events[] = implode('|', [$dateStr, $nameKey, $fasting, $type, $reasonKey]);
            $identities[$nameKey] = true;
            $identityDates[$nameKey][] = $dateStr;

            $pair = $dateStr . '|' . $nameKey;
            $pairCounts[$pair] = ($pairCounts[$pair] ?? 0) + 1;
        }
    }

    $duplicates = [];
    foreach ($pairCounts as $pair => $count) {
        if ($count > 1) {
            $duplicates[$pair] = $count;
        }
    }

    $recurringKeys = [];
    foreach ($recurring as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $nameKey = trim((string) ($entry['name_key'] ?? $entry['name'] ?? ''));
        if ($nameKey !== '') {
            $recurringKeys[] = $nameKey;
            $identities[$nameKey] = true;
        }
    }

    $identityDateLines = [];
    foreach ($identityDates as $nameKey => $dates) {
        $dates = array_values(array_unique($dates));
        sort($dates);
        $identityDateLines[] = $nameKey . '=' . implode(',', $dates);
    }

    sort($events);
    sort($recurringKeys);
    sort($identityDateLines);
    $identityList = array_keys($identities);
    sort($identityList);

    return [
        'source' => basename($path),
        'entry_count' => count($events),
        'unique_identity_count' => count($identityList),
        'recurring_weekday_count' => count($recurringKeys),
        'duplicate_same_day_identity_count' => count($duplicates),
        'total_festivals_field' => isset($root['total_festivals']) ? (int) $root['total_festivals'] : null,
        'total_vrats_field' => isset($root['total_vrats']) ? (int) $root['total_vrats'] : null,
        'events_sha256' => hash('sha256', implode("\n", $events)),
        'recurring_sha256' => hash('sha256', implode("\n", $recurringKeys)),
        'identities_sha256' => hash('sha256', implode("\n", $identityList)),
        'identity_dates_sha256' => hash('sha256', implode("\n", $identityDateLines)),
        'identities' => $identityList,
        'duplicates' => $duplicates,
    ];
}

/**
 * @param array<string, string> $targets
 *
 * @return array{payload: array<string, mixed>, analyses: array<string, array<string, mixed>>}
 */
function festival_parity_build(array $targets): array
{
    $analyses = [];
    $fingerprints = [];

    foreach ($targets as $label => $path) {
        if (!is_file($path)) {
            throw new RuntimeException('Missing output file for parity: ' . $path);
        }

        $analysis = festival_parity_analyze_file($path);
        $analyses[$label] = $analysis;
        $fingerprints[$label] = [
            'source' => $analysis['source'],
            'entry_count' => $analysis['entry_count'],
            'unique_identity_count' => $analysis['unique_identity_count'],
            'recurring_weekday_count' => $analysis['recurring_weekday_count'],
            'duplicate_same_day_identity_count' => $analysis['duplicate_same_day_identity_count'],
            'total_festivals_field' => $analysis['total_festivals_field'],
            'total_vrats_field' => $analysis['total_vrats_field'],
            'events_sha256' => $analysis['events_sha256'],
            'recurring_sha256' => $analysis['recurring_sha256'],
            'identities_sha256' => $analysis['identities_sha256'],
            'identity_dates_sha256' => $analysis['identity_dates_sha256'],
        ];
    }

    $payload = [
        'generated_at' => gmdate('c'),
        'contract' => [
            'allowed' => 'Intentional reorganization, restructuring, refactorization, reshaping of festival module code layout only.',
            'forbidden' => [
                'Change algorithms, classifiers, formulas, calculations, logics, or conditions for any festival/vrat',
                'Date drift for any observance',
                'Remove any festival/vrat identity that currently generates',
                'Introduce same-day same-identity duplicates',
                'Change entry counts or decision keys without intentional catalog/rule change',
            ],
            'parity_fields' => 'date|name_key|fasting|calculation_basis.type|winning_reason_key',
        ],
        'location' => 'Bhuj 23.2472446,69.668339 Asia/Kolkata',
        'year' => 2026,
        'fingerprints' => $fingerprints,
    ];

    return ['payload' => $payload, 'analyses' => $analyses];
}

try {
    $built = festival_parity_build($targets);
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . PHP_EOL);
    exit(1);
}

$current = $built['payload'];
$analyses = $built['analyses'];

if ($command === 'write') {
    if (!is_dir($identitiesDir) && !mkdir($identitiesDir, 0777, true) && !is_dir($identitiesDir)) {
        fwrite(STDERR, 'Cannot create fixture directory: ' . $identitiesDir . PHP_EOL);
        exit(1);
    }

    file_put_contents(
        $fixturePath,
        json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
    );

    foreach ($analyses as $label => $analysis) {
        file_put_contents(
            $identitiesDir . '/' . $label . '_identities.txt',
            implode("\n", $analysis['identities']) . "\n"
        );
    }

    echo 'Wrote ' . $fixturePath . PHP_EOL;
    foreach ($current['fingerprints'] as $label => $fp) {
        echo sprintf(
            "  %s entries=%d identities=%d dupes=%d events=%s dates=%s\n",
            $label,
            $fp['entry_count'],
            $fp['unique_identity_count'],
            $fp['duplicate_same_day_identity_count'],
            $fp['events_sha256'],
            $fp['identity_dates_sha256']
        );
    }
    exit(0);
}

if ($command !== 'verify') {
    fwrite(STDERR, sprintf('Unknown command: %s. Use write|verify', $command) . PHP_EOL);
    exit(1);
}

if (!is_file($fixturePath)) {
    fwrite(STDERR, sprintf('Golden fixture missing: %s. Run: php scripts/festival_parity_fingerprint.php write', $fixturePath) . PHP_EOL);
    exit(1);
}

$golden = json_decode((string) file_get_contents($fixturePath), true);
if (!is_array($golden) || !isset($golden['fingerprints']) || !is_array($golden['fingerprints'])) {
    fwrite(STDERR, 'Invalid golden fixture: ' . $fixturePath . PHP_EOL);
    exit(1);
}

$mismatches = [];
$compareFields = [
    'entry_count',
    'unique_identity_count',
    'recurring_weekday_count',
    'duplicate_same_day_identity_count',
    'events_sha256',
    'recurring_sha256',
    'identities_sha256',
    'identity_dates_sha256',
    'total_festivals_field',
    'total_vrats_field',
];

foreach ($golden['fingerprints'] as $label => $expected) {
    $actual = $current['fingerprints'][$label] ?? null;
    if (!is_array($actual)) {
        $mismatches[] = $label . ': missing current fingerprint';
        continue;
    }

    // Hard fail if any same-day duplicates appear after reshape.
    if ((int) ($actual['duplicate_same_day_identity_count'] ?? 0) !== 0) {
        $dupes = $analyses[$label]['duplicates'] ?? [];
        $mismatches[] = $label . ': introduced same-day identity duplicates: ' . json_encode($dupes, JSON_UNESCAPED_UNICODE);
    }

    foreach ($compareFields as $field) {
        $exp = $expected[$field] ?? null;
        $act = $actual[$field] ?? null;
        if ($exp !== $act) {
            $mismatches[] = sprintf('%s.%s: expected ', $label, $field) . json_encode($exp) . ' got ' . json_encode($act);
        }
    }

    // Explicit identity set diff (removals / additions).
    $goldenIdsPath = $identitiesDir . '/' . $label . '_identities.txt';
    if (is_file($goldenIdsPath)) {
        $goldenIds = array_values(array_filter(array_map(trim(...), explode("\n", (string) file_get_contents($goldenIdsPath))), static fn (string $s): bool => $s !== ''));
        $currentIds = $analyses[$label]['identities'] ?? [];
        $removed = array_values(array_diff($goldenIds, $currentIds));
        $added = array_values(array_diff($currentIds, $goldenIds));
        if ($removed !== []) {
            $mismatches[] = $label . ': REMOVED identities (' . count($removed) . '): ' . implode(', ', array_slice($removed, 0, 20));
        }
        if ($added !== []) {
            $mismatches[] = $label . ': ADDED identities (' . count($added) . '): ' . implode(', ', array_slice($added, 0, 20));
        }
    }
}

if ($mismatches !== []) {
    fwrite(STDERR, "FESTIVAL/VRAT PARITY FAILURE — reshape must not change calendar behavior:\n" . implode("\n", $mismatches) . "\n");
    exit(1);
}

echo 'Festival/vrat parity OK — no date drift, no removals, no additions, no duplicates.' . PHP_EOL;
foreach ($current['fingerprints'] as $label => $fp) {
    echo sprintf(
        "  %s entries=%d identities=%d dupes=%d\n",
        $label,
        $fp['entry_count'],
        $fp['unique_identity_count'],
        $fp['duplicate_same_day_identity_count']
    );
}
exit(0);
