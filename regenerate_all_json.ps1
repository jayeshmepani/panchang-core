# regenerate_all_json.ps1
# This script regenerates all 30 JSON files for the Panchang Core project.

$calendarTypes = @("amanta", "purnimanta")
$locales = @("en", "hi", "gu")
$scriptsDir = "e:\project\astrology\panchang-core\scripts"
$outputBaseDir = "$scriptsDir\output"

# Ensure output base directory exists
if (-not (Test-Path $outputBaseDir)) {
    New-Item -ItemType Directory -Path $outputBaseDir -Force
}

foreach ($type in $calendarTypes) {
    foreach ($lang in $locales) {
        $targetDir = "$outputBaseDir\$type\$lang"
        Write-Host "--- Generating for Calendar: $type, Locale: $lang ---"
        
        if (-not (Test-Path $targetDir)) {
            New-Item -ItemType Directory -Path $targetDir -Force
        }

        # Set environment variables for the current process
        $env:PANCHANG_CALENDAR_TYPE = $type
        $env:PANCHANG_LOCALE = $lang

        Write-Host "Running panchang_today.php..."
        php "$scriptsDir\panchang_today.php"
        if (Test-Path "today_panchang.json") {
            Move-Item -Force "today_panchang.json" "$targetDir\today.json"
        }

        Write-Host "Running panchang_festivals.php..."
        php "$scriptsDir\panchang_festivals.php" 2026
        if (Test-Path "festivals_2026.json") {
            Move-Item -Force "festivals_2026.json" "$targetDir\festivals_2026.json"
        }

        Write-Host "Running panchang_eclipses.php..."
        php "$scriptsDir\panchang_eclipses.php" 2026 2032
        if (Test-Path "eclipses_2026_2032.json") {
            Move-Item -Force "eclipses_2026_2032.json" "$targetDir\eclipses_2026_2032.json"
        }

        Write-Host "Running panchang_month_output.php..."
        php "$scriptsDir\panchang_month_output.php" 2026 4 > "$targetDir\month_2026_04.json"

        Write-Host "Running panchang_raw_output.php..."
        php "$scriptsDir\panchang_raw_output.php" > "$targetDir\raw_output_2026_2032.json"
        
        # Clean up environment variables
        Remove-Item Env:PANCHANG_CALENDAR_TYPE
        Remove-Item Env:PANCHANG_LOCALE
    }
}

Write-Host "Bulk generation complete! Files are located in $outputBaseDir"

