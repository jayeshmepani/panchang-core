<?php

/**
 * Comprehensive 1:1 File Comparison Script
 * 
 * Compares app files with package files, ignoring only:
 * - Namespace declarations
 * - Use statements
 * - PHPDoc comments
 * - Whitespace changes from CS Fixer
 */

$baseDir = __DIR__;
$appDir = $baseDir . '/../panchang/app/Services';
$packageDir = $baseDir . '/src';

$comparisons = [
    'AstroCore.php' => 'Core/AstroCore.php',
    'AstronomyService.php' => 'Astronomy/AstronomyService.php',
    'SunService.php' => 'Astronomy/SunService.php',
    'EclipseService.php' => 'Astronomy/EclipseService.php',
    'PanchangaEngine.php' => 'Panchanga/PanchangaEngine.php',
    'PanchangService.php' => 'Panchanga/PanchangService.php',
    'MuhurtaService.php' => 'Panchanga/MuhurtaService.php',
    'KalaNirnayaEngine.php' => 'Panchanga/KalaNirnayaEngine.php',
    'FestivalService.php' => 'Festivals/FestivalService.php',
    'FestivalRuleEngine.php' => 'Festivals/FestivalRuleEngine.php',
    'FestivalFamilyOrchestrator.php' => 'Festivals/FestivalFamilyOrchestrator.php',
    'BhadraEngine.php' => 'Festivals/Utils/BhadraEngine.php',
];

echo "=== 1:1 FILE COMPARISON ===\n\n";

$totalFiles = count($comparisons);
$passedFiles = 0;
$failedFiles = 0;

foreach ($comparisons as $appFile => $packageFile) {
    $appPath = $appDir . '/' . $appFile;
    $packagePath = $packageDir . '/' . $packageFile;
    
    echo "Comparing: $appFile\n";
    
    if (!file_exists($appPath)) {
        echo "  ❌ App file not found: $appPath\n";
        $failedFiles++;
        continue;
    }
    
    if (!file_exists($packagePath)) {
        echo "  ❌ Package file not found: $packagePath\n";
        $failedFiles++;
        continue;
    }
    
    $appContent = file_get_contents($appPath);
    $packageContent = file_get_contents($packagePath);
    
    // Extract only the method bodies (ignore namespaces, use statements, PHPDoc)
    $appMethods = extractMethods($appContent);
    $packageMethods = extractMethods($packageContent);
    
    $allMatch = true;
    $methodCount = count($appMethods);
    
    foreach ($appMethods as $methodName => $appMethodBody) {
        if (!isset($packageMethods[$methodName])) {
            echo "  ⚠️  Method missing in package: $methodName\n";
            $allMatch = false;
            continue;
        }
        
        $packageMethodBody = $packageMethods[$methodName];
        
        // Normalize whitespace for comparison
        $appNormalized = normalizeCode($appMethodBody);
        $packageNormalized = normalizeCode($packageMethodBody);
        
        if ($appNormalized !== $packageNormalized) {
            echo "  ❌ Method mismatch: $methodName\n";
            $allMatch = false;
        }
    }
    
    if ($allMatch) {
        echo "  ✅ PASS - All $methodCount methods match\n";
        $passedFiles++;
    } else {
        echo "  ❌ FAIL - Some methods don't match\n";
        $failedFiles++;
    }
    
    echo "\n";
}

echo "=== SUMMARY ===\n";
echo "Total Files: $totalFiles\n";
echo "Passed: $passedFiles\n";
echo "Failed: $failedFiles\n";
echo "Pass Rate: " . round(($passedFiles / $totalFiles) * 100, 2) . "%\n";

if ($failedFiles === 0) {
    echo "\n✅ ALL FILES PASS - 100% CODE PARITY ACHIEVED\n";
} else {
    echo "\n❌ SOME FILES FAILED - REVIEW REQUIRED\n";
}

/**
 * Extract all method bodies from a PHP file
 */
function extractMethods(string $code): array
{
    $methods = [];
    preg_match_all('/(public|private|protected)\s+(static\s+)?function\s+(\w+)\s*\([^)]*\)\s*(:\s*\w+)?\s*\{([^}]+(?:\{[^}]*\}[^}]*)*)\}/s', $code, $matches, PREG_SET_ORDER);
    
    foreach ($matches as $match) {
        $methodName = $match[3];
        $methodBody = $match[5];
        $methods[$methodName] = $methodBody;
    }
    
    return $methods;
}

/**
 * Normalize code for comparison (remove whitespace differences)
 */
function normalizeCode(string $code): string
{
    // Remove all whitespace
    $normalized = preg_replace('/\s+/', ' ', $code);
    // Remove leading/trailing whitespace
    $normalized = trim($normalized);
    return $normalized;
}
