<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Tests;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Core\Enums\Choghadiya;
use JayeshMepani\PanchangCore\Core\Enums\Vara;
use JayeshMepani\PanchangCore\Muhurta\Classical\DailyPeriodsCalculator;
use JayeshMepani\PanchangCore\Muhurta\Classical\InauspiciousPeriodsCalculator;
use PHPUnit\Framework\TestCase;

final class DynamicDayNightPeriodsTest extends TestCase
{
    public function testSandhyaUsesDynamicNightGhatiWindowsLikePublishedPanchangs(): void
    {
        $calculator = new DailyPeriodsCalculator;
        $sunrise = CarbonImmutable::parse('2026-06-20 05:30:00', 'Asia/Kolkata');
        $solarNoon = CarbonImmutable::parse('2026-06-20 12:30:00', 'Asia/Kolkata');
        $sunset = CarbonImmutable::parse('2026-06-20 19:30:00', 'Asia/Kolkata');
        $nextSunrise = CarbonImmutable::parse('2026-06-21 05:45:00', 'Asia/Kolkata');

        $sandhya = $calculator->calculateSandhya(
            $sunrise,
            $sunset,
            $nextSunrise,
            $solarNoon
        );

        self::assertSame('dynamic_ratrimana_30_ghati_sandhya', $sandhya['calculation_basis']);
        self::assertSame(3690, $sandhya['pratah_sandhya']['duration_seconds']);
        self::assertSame(4320, $sandhya['madhyahna_sandhya']['duration_seconds']);
        self::assertSame(3690, $sandhya['sayahna_sandhya']['duration_seconds']);
        self::assertSame('20/06/2026 04:28:30 AM', $sandhya['pratah_sandhya']['start_iso']);
        self::assertSame('20/06/2026 05:30:00 AM', $sandhya['pratah_sandhya']['end_iso']);
        self::assertSame('20/06/2026 11:54:00 AM', $sandhya['madhyahna_sandhya']['start_iso']);
        self::assertSame('20/06/2026 01:06:00 PM', $sandhya['madhyahna_sandhya']['end_iso']);
        self::assertSame('20/06/2026 07:30:00 PM', $sandhya['sayahna_sandhya']['start_iso']);
        self::assertSame('20/06/2026 08:31:30 PM', $sandhya['sayahna_sandhya']['end_iso']);
    }

    public function testChoghadiyaFromTimeUsesTheActualNextSunrise(): void
    {
        $sunrise = 2461211.5;
        $sunset = $sunrise + (14.0 / 24.0);
        $nextSunrise = $sunset + (10.25 / 24.0);
        $current = $sunset + (2.53 / 24.0);

        $actual = Choghadiya::fromTime($sunrise, $sunset, $nextSunrise, $current, Vara::Sunday);

        self::assertSame(Choghadiya::Amrit, $actual);
    }

    public function testPradoshaHelperUsesThreeDynamicNightMuhurtasAfterLocalSunset(): void
    {
        $calculator = new InauspiciousPeriodsCalculator;
        $sunset = CarbonImmutable::parse('2026-06-20 19:30:00', 'Asia/Kolkata');
        $nextSunrise = CarbonImmutable::parse('2026-06-21 05:45:00', 'Asia/Kolkata');

        $pradosha = $calculator->calculatePradoshaKaal($sunset, $nextSunrise, 13);

        self::assertSame('07:30:00 PM', $pradosha['pradosha_start']);
        self::assertSame('09:33:00 PM', $pradosha['pradosha_end']);
        self::assertSame(7380.0, $pradosha['duration_seconds']);
        self::assertSame('dynamic_ratrimana_3_muhurta_from_local_sunset', $pradosha['calculation_basis']);
        self::assertTrue($pradosha['is_auspicious']);
    }

    public function testNighttimeFivefoldDivisionUsesDynamicRatrimana(): void
    {
        $calculator = new DailyPeriodsCalculator;
        $sunset = CarbonImmutable::parse('2026-06-20 19:30:00', 'Asia/Kolkata');
        $nextSunrise = CarbonImmutable::parse('2026-06-21 05:45:00', 'Asia/Kolkata');

        $nightFivefold = $calculator->calculateNighttimeFivefoldDivision($sunset, $nextSunrise);

        self::assertCount(5, $nightFivefold);
        self::assertSame('Pradosha', $nightFivefold[0]['name_key']);
        self::assertSame('Ratri', $nightFivefold[1]['name_key']);
        self::assertSame('Nishitha', $nightFivefold[2]['name_key']);
        self::assertSame('Usha', $nightFivefold[3]['name_key']);
        self::assertSame('Arunodaya', $nightFivefold[4]['name_key']);
        self::assertSame(7380.0, $nightFivefold[0]['duration_seconds']);
        self::assertSame('20/06/2026 07:30:00 PM', $nightFivefold[0]['start_iso']);
        self::assertSame('20/06/2026 09:33:00 PM', $nightFivefold[0]['end_iso']);
        self::assertSame('21/06/2026 03:42:00 AM', $nightFivefold[4]['start_iso']);
        self::assertSame('21/06/2026 05:45:00 AM', $nightFivefold[4]['end_iso']);
    }
}
