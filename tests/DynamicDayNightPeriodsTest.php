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
    public function testSandhyaUsesLocalAstronomicalAnchorsWithFixedGhatiOffsets(): void
    {
        $calculator = new DailyPeriodsCalculator;
        $sunrise = CarbonImmutable::parse('2026-06-20 05:30:00', 'Asia/Kolkata');
        $solarNoon = CarbonImmutable::parse('2026-06-20 12:30:00', 'Asia/Kolkata');
        $sunset = CarbonImmutable::parse('2026-06-20 19:30:00', 'Asia/Kolkata');

        $sandhya = $calculator->calculateSandhya(
            $sunrise,
            $sunset,
            $solarNoon
        );

        self::assertSame(4320, $sandhya['pratah_sandhya']['duration_seconds']);
        self::assertSame(4320, $sandhya['madhyahna_sandhya']['duration_seconds']);
        self::assertSame(4320, $sandhya['sayahna_sandhya']['duration_seconds']);
        self::assertSame('20/06/2026 04:42:00 AM', $sandhya['pratah_sandhya']['start_iso']);
        self::assertSame('20/06/2026 05:54:00 AM', $sandhya['pratah_sandhya']['end_iso']);
        self::assertSame('20/06/2026 11:54:00 AM', $sandhya['madhyahna_sandhya']['start_iso']);
        self::assertSame('20/06/2026 01:06:00 PM', $sandhya['madhyahna_sandhya']['end_iso']);
        self::assertSame('20/06/2026 07:06:00 PM', $sandhya['sayahna_sandhya']['start_iso']);
        self::assertSame('20/06/2026 08:18:00 PM', $sandhya['sayahna_sandhya']['end_iso']);
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

    public function testLegacyPradoshaHelperUsesSixFixedGhatisAfterLocalSunset(): void
    {
        $calculator = new InauspiciousPeriodsCalculator;
        $sunset = CarbonImmutable::parse('2026-06-20 19:30:00', 'Asia/Kolkata');

        $pradosha = $calculator->calculatePradoshaKaal($sunset, 13);

        self::assertSame('07:30:00 PM', $pradosha['pradosha_start']);
        self::assertSame('09:54:00 PM', $pradosha['pradosha_end']);
        self::assertSame(8640.0, $pradosha['duration_seconds']);
        self::assertSame('fixed_ghati_offset_from_local_sunset', $pradosha['calculation_basis']);
        self::assertTrue($pradosha['is_auspicious']);
    }
}
