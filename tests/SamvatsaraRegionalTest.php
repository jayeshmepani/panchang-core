<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Tests;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Astronomy\BrihaspatiSamvatsaraService;
use JayeshMepani\PanchangCore\Panchanga\PanchangaEngine;
use JayeshMepani\PanchangCore\PanchangServiceProvider;
use JayeshMepani\PanchangCore\Traits\CliBootstrap;
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
        // Classical transition is ~2026-04-21 17:37 UTC (23:07 IST); civil midnight still Siddharthi.
        $this->assertSame(
            'Siddharthi',
            $engine->getSamvatsaraBrihaspati(CarbonImmutable::create(2026, 4, 20))
        );
        $this->assertSame(
            'Siddharthi',
            $engine->getSamvatsaraBrihaspati(CarbonImmutable::create(2026, 4, 21))
        );
        $this->assertSame(
            'Raudri',
            $engine->getSamvatsaraBrihaspati(CarbonImmutable::create(2026, 4, 22))
        );

        $fields = $engine->buildSamvatsaraCalendarFields($vikram, $saka, $gujarati, $date);
        $this->assertSame('Parabhava', $fields['Samvatsara']);
        $this->assertSame('Parabhava', $fields['Samvatsara_South']);
        $this->assertSame('South', $fields['Samvatsara_South_Prefix']);
        $this->assertSame('Siddharthi', $fields['Samvatsara_North']);
        $this->assertSame('Raudri', $fields['Samvatsara_Brihaspati']);
        $this->assertSame(
            BrihaspatiSamvatsaraService::MODEL_CLASSICAL_SS,
            $fields['Samvatsara_Brihaspati_Model']
        );
        $this->assertSame(
            BrihaspatiSamvatsaraService::STATUS_CANONICAL,
            $fields['Samvatsara_Brihaspati_Model_Status']
        );
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

    /**
     * Day Hindu_Calendar era years must follow Chaitra / Kartika Śukla Pratipadā
     * (same civil ranges as calendar-period windows), not civil month≥April.
     */
    public function test_day_api_era_years_match_chaitradi_and_kartika_edges(): void
    {
        CliBootstrap::init(dirname(__DIR__));
        $service = CliBootstrap::makePanchangService();
        $lat = 23.2472446;
        $lon = 69.668339;
        $tz = 'Asia/Kolkata';

        $cases = [
            // day before Ugadi 2026
            '2026-03-18' => [2082, 1947, 5126, 2082, 'Vishvavasu', 'Kalayukti', 'Pingala', 'Siddharthi'],
            // Chaitra SP 2026 — new VS/Śaka/Kali; Gujarati still 2082
            '2026-03-19' => [2083, 1948, 5127, 2082, 'Parabhava', 'Siddharthi', 'Pingala', 'Siddharthi'],
            '2026-03-31' => [2083, 1948, 5127, 2082, 'Parabhava', 'Siddharthi', 'Pingala', 'Siddharthi'],
            '2026-04-01' => [2083, 1948, 5127, 2082, 'Parabhava', 'Siddharthi', 'Pingala', 'Siddharthi'],
            // mean Brihaspati around classical transition
            '2026-04-20' => [2083, 1948, 5127, 2082, 'Parabhava', 'Siddharthi', 'Pingala', 'Siddharthi'],
            '2026-04-22' => [2083, 1948, 5127, 2082, 'Parabhava', 'Siddharthi', 'Pingala', 'Raudri'],
            // Kartika Bestu edge 2026
            '2026-11-09' => [2083, 1948, 5127, 2082, 'Parabhava', 'Siddharthi', 'Pingala', 'Raudri'],
            '2026-11-10' => [2083, 1948, 5127, 2083, 'Parabhava', 'Siddharthi', 'Kalayukti', 'Raudri'],
            // day before Ugadi 2027 still VS 2083
            '2027-04-06' => [2083, 1948, 5127, 2083, 'Parabhava', 'Siddharthi', 'Kalayukti', 'Raudri'],
            '2027-04-07' => [2084, 1949, 5128, 2083, 'Plavanga', 'Raudri', 'Kalayukti', 'Raudri'],
        ];

        foreach ($cases as $day => [$vikram, $saka, $kali, $gujarati, $south, $north, $gujName, $brihaspati]) {
            $details = $service->getDayDetails(
                CarbonImmutable::parse($day, $tz)->setTime(12, 0, 0),
                $lat,
                $lon,
                $tz
            );
            $hc = $details['Hindu_Calendar'];

            $this->assertSame($vikram, (int) $hc['Vikram_Samvat'], $day . ' Vikram');
            $this->assertSame($saka, (int) $hc['Saka_Samvat'], $day . ' Saka');
            $this->assertSame($kali, (int) $hc['Kali_Samvat'], $day . ' Kali');
            $this->assertSame($gujarati, (int) $hc['Gujarati_Samvat'], $day . ' Gujarati');
            $this->assertSame($south, $hc['Samvatsara_South'], $day . ' South');
            $this->assertSame($north, $hc['Samvatsara_North'], $day . ' North');
            $this->assertSame($gujName, $hc['Samvatsara_Gujarati'], $day . ' Guj name');
            $this->assertSame($brihaspati, $hc['Samvatsara_Brihaspati'], $day . ' Brihaspati');
        }
    }

    public function test_month_fields_hindu_calendar_uses_chaitradi_era_on_ugadi_edge(): void
    {
        CliBootstrap::init(dirname(__DIR__));
        $service = CliBootstrap::makePanchangService();
        $lat = 23.2472446;
        $lon = 69.668339;
        $tz = 'Asia/Kolkata';

        $month = $service->getMonthFields(
            2026,
            3,
            $lat,
            $lon,
            $tz,
            ['hindu_calendar'],
            0.0
        );

        $this->assertArrayHasKey('2026-03-18', $month);
        $this->assertArrayHasKey('2026-03-19', $month);

        $hc18 = $month['2026-03-18']['hindu_calendar'] ?? [];
        $hc19 = $month['2026-03-19']['hindu_calendar'] ?? [];

        $this->assertSame(2082, (int) ($hc18['Vikram_Samvat'] ?? 0));
        $this->assertSame(2083, (int) ($hc19['Vikram_Samvat'] ?? 0));
        $this->assertSame(1948, (int) ($hc19['Saka_Samvat'] ?? 0));
        $this->assertSame(5127, (int) ($hc19['Kali_Samvat'] ?? 0));
        $this->assertSame(2082, (int) ($hc19['Gujarati_Samvat'] ?? 0));
        $this->assertSame('Parabhava', $hc19['Samvatsara_South'] ?? null);
        $this->assertSame('Siddharthi', $hc19['Samvatsara_North'] ?? null);
        $this->assertSame('Pingala', $hc19['Samvatsara_Gujarati'] ?? null);
    }

    protected function getPackageProviders($app): array
    {
        return [PanchangServiceProvider::class];
    }
}
