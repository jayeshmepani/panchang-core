<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use JayeshMepani\PanchangCore\Core\Localization;
use JayeshMepani\PanchangCore\Festivals\FestivalService;

/**
 * Find localization keys that are safely removable.
 *
 * Strategy:
 * - Read Localization::$translations through reflection.
 * - Parse literal Localization::translate('Category', 'Key') usages from PHP source.
 * - Harvest dynamic festival-related keys from FestivalService registries.
 * - Skip categories that are clearly dynamic unless we have a dedicated resolver.
 */

$rootDir = dirname(__DIR__);
$sourceDirs = [
    $rootDir . '/src',
    $rootDir . '/tests',
    $rootDir . '/scripts',
];

$translationReflection = new ReflectionClass(Localization::class);
$translationProperty = $translationReflection->getProperty('translations');
$translationProperty->setAccessible(true);
/** @var array<string, array<string, array<int|string, string>>> $translations */
$translations = $translationProperty->getValue();

$festivalReflection = new ReflectionClass(FestivalService::class);
/** @var array<string, array<string, mixed>> $festivalDefinitions */
$festivalDefinitions = $festivalReflection->getConstant('FESTIVALS');
/** @var array<int, array<string, string>> $tithiVratas */
$tithiVratas = $festivalReflection->getConstant('TITHI_VRATAS');

$literalUsage = [];
$dynamicCategories = [];

foreach ($sourceDirs as $dir) {
    if (!is_dir($dir)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $path = $file->getPathname();
        if (str_ends_with($path, 'src/Core/Localization.php')) {
            continue;
        }

        $content = file_get_contents($path);
        if (!is_string($content) || $content === '') {
            continue;
        }

        preg_match_all(
            '/Localization::translate\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*([\'"])((?:\\\\.|(?!\2).)*)\2/s',
            $content,
            $literalMatches,
            PREG_SET_ORDER
        );

        foreach ($literalMatches as $match) {
            $category = $match[1];
            $key = stripcslashes($match[3]);
            $literalUsage[$category][$key] = true;
        }

        preg_match_all(
            '/Localization::translate\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*(?![\'"]|\d)([^,\)]+)/',
            $content,
            $dynamicMatches,
            PREG_SET_ORDER
        );

        foreach ($dynamicMatches as $match) {
            $dynamicCategories[$match[1]] = true;
        }
    }
}

// Harvest dynamic keys from the canonical festival registries.
foreach ($festivalDefinitions as $festivalName => $rules) {
    $literalUsage['Festival'][(string) $festivalName] = true;

    if (isset($rules['description']) && is_string($rules['description']) && $rules['description'] !== '') {
        $literalUsage['FestivalDesc'][$rules['description']] = true;
    }

    if (isset($rules['deity']) && is_string($rules['deity']) && $rules['deity'] !== '') {
        $literalUsage['Deity'][$rules['deity']] = true;
    }

    foreach ((array) ($rules['regions'] ?? []) as $region) {
        if (is_string($region) && $region !== '') {
            $literalUsage['Region'][$region] = true;
        }
    }
}

foreach ($tithiVratas as $rule) {
    if (isset($rule['vrata']) && is_string($rule['vrata']) && $rule['vrata'] !== '') {
        $literalUsage['Vrata'][$rule['vrata']] = true;
    }

    if (isset($rule['deity']) && is_string($rule['deity']) && $rule['deity'] !== '') {
        $literalUsage['Deity'][$rule['deity']] = true;
    }

    if (isset($rule['benefit']) && is_string($rule['benefit']) && $rule['benefit'] !== '') {
        $literalUsage['Benefit'][$rule['benefit']] = true;
    }
}

$skipDynamicCategories = [
    'Nakshatra', 'Vara', 'Tithi', 'Rasi', 'Yoga', 'Karana', 'Muhurta', 'Planet',
    'Ayana', 'Paksha', 'Masa', 'Ritu', 'Samvatsara', 'Choghadiya', 'Prahara',
    'Gowri', 'GowriQuality', 'Fivefold', 'Panchaka', 'Moorthy', 'Common', 'String',
    'Source', 'Eclipse',
];

$report = [
    'safe_unused' => [],
    'skipped_dynamic_categories' => [],
];

foreach ($translations as $category => $locales) {
    $englishKeys = array_keys($locales['en'] ?? []);
    if ($englishKeys === []) {
        continue;
    }

    if (isset($dynamicCategories[$category]) || in_array($category, $skipDynamicCategories, true)) {
        $report['skipped_dynamic_categories'][] = $category;
        continue;
    }

    foreach ($englishKeys as $key) {
        if (!is_string($key) || $key === '') {
            continue;
        }

        if (!isset($literalUsage[$category][$key])) {
            $report['safe_unused'][$category][] = $key;
        }
    }
}

sort($report['skipped_dynamic_categories']);
foreach ($report['safe_unused'] as &$keys) {
    sort($keys, SORT_NATURAL);
}
unset($keys);
ksort($report['safe_unused']);

$reportPath = __DIR__ . '/unused_keys_report.json';
file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

echo 'Safe unused-key report written to ' . $reportPath . PHP_EOL;

if ($report['safe_unused'] === []) {
    echo "No safely removable keys detected.\n";
    exit(0);
}

echo "Safely removable keys:\n";
foreach ($report['safe_unused'] as $category => $keys) {
    echo '[' . $category . ']' . PHP_EOL;
    foreach ($keys as $key) {
        echo '  - ' . $key . PHP_EOL;
    }
}
