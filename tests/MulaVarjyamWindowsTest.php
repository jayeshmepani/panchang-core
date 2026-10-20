<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Tests;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Panchanga\PanchangService;
use JayeshMepani\PanchangCore\PanchangServiceProvider;
use Orchestra\Testbench\TestCase;

/**
 * Mula carries two Varjyam windows, starting after 20 and 56 ghatis of the
 * Nakshatra. A ghati is 1/60 of the Nakshatra's actual duration and each
 * window lasts 4 ghatis, so the second window ends exactly when Mula ends.
 */
final class MulaVarjyamWindowsTest extends TestCase
{
    private const float LAT = 23.2472446;

    private const float LON = 69.668339;

    private const string TZ = 'Asia/Kolkata';

    public function testBhujSixteenthOctoberHasTheTwentyGhatiWindow(): void
    {
        $varjyam = $this->varjyam(16);

        self::assertSame(1, $varjyam['window_count']);
        $window = $varjyam['windows'][0];
        self::assertSame(20, $window['offset_ghati']);
        $this->assertWindowGeometry($window, 20);
        self::assertSame('16/10/2026 03:45:40 PM', $window['varjyam_start_iso']);
        self::assertSame('16/10/2026 05:33:38 PM', $window['varjyam_end_iso']);
    }

    public function testFiftySixGhatiWindowSpillsIntoTheNextCivilDay(): void
    {
        $varjyam = $this->varjyam(17);

        self::assertSame(2, $varjyam['window_count']);
        $window = $varjyam['windows'][0];
        self::assertSame(56, $window['offset_ghati']);
        $this->assertWindowGeometry($window, 56);
        self::assertSame($window['nakshatra_end_jd'], $window['window_end_jd']);
        self::assertSame('17/10/2026 07:57:21 AM', $window['varjyam_start_iso']);
        self::assertSame('17/10/2026 09:45:19 AM', $window['varjyam_end_iso']);
    }

    protected function getPackageProviders($app): array
    {
        return [PanchangServiceProvider::class];
    }

    private function assertWindowGeometry(array $window, int $offsetGhati): void
    {
        $duration = $window['nakshatra_end_jd'] - $window['nakshatra_start_jd'];
        self::assertSame($window['nakshatra_start_jd'] + ($duration * $offsetGhati / 60.0), $window['window_start_jd']);
        self::assertEqualsWithDelta($duration * 4.0 / 60.0, $window['window_end_jd'] - $window['window_start_jd'], 1e-9);
    }

    private function varjyam(int $day): array
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);
        $date = CarbonImmutable::create(2026, 10, $day, 0, 0, 0, self::TZ);

        return $service->getVarjyam($date, self::LAT, self::LON, self::TZ, 0.0, $date->setTime(12, 0));
    }
}
