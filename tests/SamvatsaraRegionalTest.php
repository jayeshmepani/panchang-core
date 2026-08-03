<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Tests;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Panchanga\PanchangaEngine;
use JayeshMepani\PanchangCore\PanchangServiceProvider;
use Orchestra\Testbench\TestCase;

/**
 * Regional Saṃvatsara labels for mid-2026 (Drik-aligned snapshot).
 *
 * South / Shaka continuous: Parabhava (Shaka 1948)
 * North Chaitradi Vikram: Siddharthi (VS 2083)
 * Mean-Bṛhaspati after ~21 Apr 2026: Raudri
 * Gujarati Kartika year: Pingala (Gujarati 2082)
 */
class SamvatsaraRegionalTest extends TestCase
{
    public function test_regional_samvatsara_formulas_for_august_2026(): void
    {
        $engine = new PanchangaEngine;
        $vikram = 2083;
        $saka = 1948;
        $gujarati = 2082;
        $date = CarbonImmutable::create(2026, 8, 3, 12, 0, 0, 'Asia/Kolkata');

        $this->assertSame('Parabhava', $engine->getSamvatsara($vikram));
        $this->assertSame('Parabhava', $engine->getSamvatsaraSouth($vikram));
        $this->assertSame('Siddharthi', $engine->getSamvatsaraNorth($vikram));
        $this->assertSame('Pingala', $engine->getSamvatsaraGujarati($gujarati));
        $this->assertSame('Raudri', $engine->getSamvatsaraBrihaspati($date));
        $this->assertSame(
            'Siddharthi',
            $engine->getSamvatsaraBrihaspati(CarbonImmutable::create(2026, 4, 20))
        );
        $this->assertSame(
            'Raudri',
            $engine->getSamvatsaraBrihaspati(CarbonImmutable::create(2026, 4, 21))
        );

        $fields = $engine->buildSamvatsaraCalendarFields($vikram, $saka, $gujarati, $date);
        $this->assertSame('Parabhava', $fields['Samvatsara']);
        $this->assertSame('Parabhava', $fields['Samvatsara_South']);
        $this->assertSame('South', $fields['Samvatsara_South_Prefix']);
        $this->assertSame('Siddharthi', $fields['Samvatsara_North']);
        $this->assertSame('Raudri', $fields['Samvatsara_Brihaspati']);
        $this->assertSame('Siddharthi / Raudri', $fields['Samvatsara_North_Display']);
        $this->assertSame('Pingala', $fields['Samvatsara_Gujarati']);
        $this->assertSame('Parabhava', $fields['Samvatsara_Systems']['south_shaka']['name']);
        $this->assertSame(1948, $fields['Samvatsara_Systems']['south_shaka']['era_year']);
        $this->assertSame('Pingala', $fields['Samvatsara_Systems']['gujarati_vikram']['name']);
        $this->assertSame(2082, $fields['Samvatsara_Systems']['gujarati_vikram']['era_year']);
    }

    public function test_gujarati_2083_is_kalayukti(): void
    {
        $engine = new PanchangaEngine;
        $this->assertSame('Kalayukti', $engine->getSamvatsaraGujarati(2083));
    }

    protected function getPackageProviders($app): array
    {
        return [PanchangServiceProvider::class];
    }
}
