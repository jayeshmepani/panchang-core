# PowerShell Script to Update All Namespaces in Package
# Run from: E:\project\astrology\panchang-core\

$ErrorActionPreference = "Stop"

Write-Host "=== Updating Package Namespaces ===" -ForegroundColor Cyan

# Define namespace mappings
$namespaceMap = @{
    "Core" = "JayeshMepani\PanchangCore\Core"
    "Core\Constants" = "JayeshMepani\PanchangCore\Core\Constants"
    "Astronomy" = "JayeshMepani\PanchangCore\Astronomy"
    "Panchanga" = "JayeshMepani\PanchangCore\Panchanga"
    "Festivals" = "JayeshMepani\PanchangCore\Festivals"
    "Festivals\Utils" = "JayeshMepani\PanchangCore\Festivals\Utils"
}

# Define use statement mappings
$useMap = @{
    "use App\Support\AstroCore;" = "use JayeshMepani\PanchangCore\Core\AstroCore;"
    "use App\Support\AstrologyConstants;" = "use JayeshMepani\PanchangCore\Core\Constants\AstrologyConstants;"
    "use App\Support\ClassicalTimeConstants;" = "use JayeshMepani\PanchangCore\Core\Constants\ClassicalTimeConstants;"
    "use App\Services\AstroCore;" = "use JayeshMepani\PanchangCore\Core\AstroCore;"
    "use App\Services\AstronomyService;" = "use JayeshMepani\PanchangCore\Astronomy\AstronomyService;"
    "use App\Services\SunService;" = "use JayeshMepani\PanchangCore\Astronomy\SunService;"
    "use App\Services\EclipseService;" = "use JayeshMepani\PanchangCore\Astronomy\EclipseService;"
    "use App\Services\PanchangaEngine;" = "use JayeshMepani\PanchangCore\Panchanga\PanchangaEngine;"
    "use App\Services\PanchangService;" = "use JayeshMepani\PanchangCore\Panchanga\PanchangService;"
    "use App\Services\MuhurtaService;" = "use JayeshMepani\PanchangCore\Panchanga\MuhurtaService;"
    "use App\Services\KalaNirnayaEngine;" = "use JayeshMepani\PanchangCore\Panchanga\KalaNirnayaEngine;"
    "use App\Services\FestivalService;" = "use JayeshMepani\PanchangCore\Festivals\FestivalService;"
    "use App\Services\FestivalRuleEngine;" = "use JayeshMepani\PanchangCore\Festivals\FestivalRuleEngine;"
    "use App\Services\FestivalFamilyOrchestrator;" = "use JayeshMepani\PanchangCore\Festivals\FestivalFamilyOrchestrator;"
    "use App\Services\BhadraEngine;" = "use JayeshMepani\PanchangCore\Festivals\Utils\BhadraEngine;"
}

# File to namespace mapping
$fileNamespaceMap = @{
    "src\Core\AstroCore.php" = "JayeshMepani\PanchangCore\Core"
    "src\Core\Constants\AstrologyConstants.php" = "JayeshMepani\PanchangCore\Core\Constants"
    "src\Core\Constants\ClassicalTimeConstants.php" = "JayeshMepani\PanchangCore\Core\Constants"
    "src\Astronomy\AstronomyService.php" = "JayeshMepani\PanchangCore\Astronomy"
    "src\Astronomy\SunService.php" = "JayeshMepani\PanchangCore\Astronomy"
    "src\Astronomy\EclipseService.php" = "JayeshMepani\PanchangCore\Astronomy"
    "src\Panchanga\PanchangaEngine.php" = "JayeshMepani\PanchangCore\Panchanga"
    "src\Panchanga\PanchangService.php" = "JayeshMepani\PanchangCore\Panchanga"
    "src\Panchanga\MuhurtaService.php" = "JayeshMepani\PanchangCore\Panchanga"
    "src\Panchanga\KalaNirnayaEngine.php" = "JayeshMepani\PanchangCore\Panchanga"
    "src\Festivals\FestivalService.php" = "JayeshMepani\PanchangCore\Festivals"
    "src\Festivals\FestivalRuleEngine.php" = "JayeshMepani\PanchangCore\Festivals"
    "src\Festivals\FestivalFamilyOrchestrator.php" = "JayeshMepani\PanchangCore\Festivals"
    "src\Festivals\Utils\BhadraEngine.php" = "JayeshMepani\PanchangCore\Festivals\Utils"
}

$filesUpdated = 0
$filesWithErrors = 0

foreach ($fileRelative in $fileNamespaceMap.Keys) {
    $filePath = Join-Path $PSScriptRoot $fileRelative
    
    if (-not (Test-Path $filePath)) {
        Write-Host "❌ File not found: $fileRelative" -ForegroundColor Red
        $filesWithErrors++
        continue
    }
    
    try {
        $content = Get-Content $filePath -Raw -Encoding UTF8
        $originalContent = $content
        
        # Update namespace
        $targetNamespace = $fileNamespaceMap[$fileRelative]
        $content = $content -replace 'namespace App\\[^;]+;', "namespace $targetNamespace;"
        
        # Update use statements
        foreach ($oldUse in $useMap.Keys) {
            $newUse = $useMap[$oldUse]
            $content = $content -replace [regex]::Escape($oldUse), $newUse
        }
        
        # Save if changed
        if ($content -ne $originalContent) {
            Set-Content $filePath -Value $content -Encoding UTF8 -NoNewline
            Write-Host "✅ Updated: $fileRelative" -ForegroundColor Green
            $filesUpdated++
        } else {
            Write-Host "⚠️  No changes needed: $fileRelative" -ForegroundColor Yellow
        }
    }
    catch {
        Write-Host "❌ Error updating $fileRelative`: $($_.Exception.Message)" -ForegroundColor Red
        $filesWithErrors++
    }
}

Write-Host ""
Write-Host "=== Summary ===" -ForegroundColor Cyan
Write-Host "Files updated: $filesUpdated" -ForegroundColor Green
Write-Host "Files with errors: $filesWithErrors" -ForegroundColor $(if ($filesWithErrors -eq 0) { "Green" } else { "Red" })
Write-Host ""

if ($filesWithErrors -eq 0) {
    Write-Host "✅ All namespaces updated successfully!" -ForegroundColor Green
} else {
    Write-Host "⚠️  Some files had errors. Please review and fix manually." -ForegroundColor Yellow
}
