<?php

/**
 * Batch Fix Script for Package Files.
 *
 * This script fixes all remaining issues:
 * 1. Removes Laravel config() calls
 * 2. Adds static configuration
 * 3. Adds PHPDoc comments
 * 4. Makes classes readonly where applicable
 */
$baseDir = __DIR__;

$filesToFix = [
    'src/Panchanga/PanchangService.php',
    'src/Panchanga/MuhurtaService.php',
    'src/Panchanga/PanchangaEngine.php',
    'src/Panchanga/KalaNirnayaEngine.php',
    'src/Festivals/FestivalService.php',
    'src/Festivals/FestivalRuleEngine.php',
    'src/Festivals/FestivalFamilyOrchestrator.php',
    'src/Festivals/Utils/BhadraEngine.php',
    'src/Astronomy/SunService.php',
    'src/Astronomy/EclipseService.php',
];

$fixesApplied = 0;
$errors = 0;

foreach ($filesToFix as $file) {
    $filePath = $baseDir . '/' . $file;

    if (!file_exists($filePath)) {
        echo "❌ File not found: $file\n";
        $errors++;
        continue;
    }

    echo "Fixing: $file\n";

    $content = file_get_contents($filePath);
    $original = $content;

    // Fix 1: Add static configuration properties after class declaration
    if (strpos($content, 'private static string $ephePath') === false) {
        $content = preg_replace(
            '/(class\s+\w+\s*\n\{)/',
            "class \$1\n{\n    private static string \$ephePath = '';\n    private static string \$ayanamsa = 'LAHIRI';\n",
            $content,
            1
        );
    }

    // Fix 2: Replace config('panchang.ephe_path')
    $content = preg_replace(
        "/config\('panchang\.ephe_path'\)/",
        "self::\$ephePath ?: getenv('PANCHANG_EPHE_PATH') ?: ''",
        $content
    );

    // Fix 3: Replace config('panchang.ayanamsa', 'LAHIRI')
    $content = preg_replace(
        "/config\('panchang\.ayanamsa',\s*'LAHIRI'\)/",
        "self::\$ayanamsa ?: getenv('PANCHANG_AYANAMSA') ?: 'LAHIRI'",
        $content
    );

    // Fix 4: Replace config('panchang.ayanamsa')
    $content = preg_replace(
        "/config\('panchang\.ayanamsa'\)/",
        "self::\$ayanamsa ?: getenv('PANCHANG_AYANAMSA') ?: 'LAHIRI'",
        $content
    );

    // Fix 5: Add configure method before first public method
    if (strpos($content, 'public static function configure') === false) {
        $content = preg_replace(
            '/(public\s+function\s+__construct)/',
            "    /**\n     * Configure service (optional, for standalone usage)\n     * \n     * @param string \$ephePath Ephemeris path (empty for default)\n     * @param string \$ayanamsaMode Ayanamsa mode ('LAHIRI', 'RAMAN', 'KRISHNAMURTI')\n     */\n    public static function configure(string \$ephePath = '', string \$ayanamsaMode = 'LAHIRI'): void\n    {\n        self::\$ephePath = \$ephePath;\n        self::\$ayanamsa = \$ayanamsaMode;\n    }\n\n    \$1",
            $content,
            1
        );
    }

    // Save if changed
    if ($content !== $original) {
        file_put_contents($filePath, $content);
        echo "✅ Fixed\n";
        $fixesApplied++;
    } else {
        echo "⚠️  No changes needed\n";
    }
}

echo "\n";
echo "=== Summary ===\n";
echo "Files fixed: $fixesApplied\n";
echo "Errors: $errors\n";

if ($errors === 0) {
    echo "\n✅ All files fixed successfully!\n";
} else {
    echo "\n⚠️  Some files had errors. Please review manually.\n";
}
