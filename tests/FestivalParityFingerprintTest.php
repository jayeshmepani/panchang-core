<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Tests;

use JayeshMepani\PanchangCore\Festivals\FestivalService;
use JayeshMepani\PanchangCore\PanchangServiceProvider;
use Orchestra\Testbench\TestCase;

/**
 * Hard parity gate for festival-module intentional reshape.
 *
 * ALLOWED: reorganization / restructuring / refactorization / reshaping of code layout.
 * FORBIDDEN: algorithm/condition changes, date drift, identity removals, same-day duplicates.
 *
 * See docs/FESTIVAL_REFACTOR_PARITY_CONTRACT.md.
 */
class FestivalParityFingerprintTest extends TestCase
{
    public function testCatalogTotalsRemainGeneralAndStable(): void
    {
        $this->assertSame(335, FestivalService::getCatalogFestivalCount());
        $this->assertSame(123, FestivalService::getCatalogVratCount());
        $this->assertSame(
            454,
            FestivalService::getFestivalCount(),
            'First-level FESTIVALS registry size must stay stable unless catalog intentionally changes'
        );
    }

    public function testGeneratedYearOutputsMatchGoldenParityFingerprints(): void
    {
        $baseDir = dirname(__DIR__);
        $script = $baseDir . '/scripts/festival_parity_fingerprint.php';
        $this->assertFileExists($script);
        $this->assertFileExists($baseDir . '/tests/fixtures/festival_parity/2026_bhuj_en_fingerprints.json');

        $cmd = 'php ' . escapeshellarg($script) . ' verify';
        $output = [];
        $exit = 0;
        exec($cmd . ' 2>&1', $output, $exit);

        $this->assertSame(
            0,
            $exit,
            "Festival/vrat parity failure (dates/identities/duplicates/algorithms).\n" . implode("\n", $output)
        );
    }

    public function testGoldenBaselineHasZeroSameDayDuplicates(): void
    {
        $path = dirname(__DIR__) . '/tests/fixtures/festival_parity/2026_bhuj_en_fingerprints.json';
        $golden = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($golden);
        foreach ($golden['fingerprints'] ?? [] as $label => $fp) {
            $this->assertSame(
                0,
                (int) ($fp['duplicate_same_day_identity_count'] ?? -1),
                (string) $label . ' baseline must have zero same-day identity duplicates'
            );
        }
    }

    protected function getPackageProviders($app): array
    {
        return [PanchangServiceProvider::class];
    }
}
