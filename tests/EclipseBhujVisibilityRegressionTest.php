<?php

declare(strict_types=1);

use JayeshMepani\PanchangCore\Traits\CliBootstrap;
use PHPUnit\Framework\TestCase;

final class EclipseBhujVisibilityRegressionTest extends TestCase
{
    private const float LAT = 23.2472446;

    private const float LON = 69.668339;

    private const string TZ = 'Asia/Kolkata';

    public function testBhujVisibleEclipseSetMatchesVerifiedNineBetween2018And2026(): void
    {
        CliBootstrap::init(dirname(__DIR__));
        $service = CliBootstrap::makeEclipseService();

        $all = [];
        for ($year = 2018; $year <= 2026; $year++) {
            $events = $service->getEclipsesForYear($year, self::LAT, self::LON, self::TZ);
            foreach ($events as $event) {
                $all[] = $event;
            }
        }

        $visible = array_values(array_filter($all, static fn (array $event): bool => (bool) ($event['visibility']['visible'] ?? false)));
        $sutakApplicable = array_values(array_filter($all, static fn (array $event): bool => (bool) ($event['sutak']['applicable'] ?? false)));

        self::assertCount(9, $visible, 'Expected exactly 9 visible eclipses in Bhuj from 2018-01-01 to 2026-12-31.');
        self::assertCount(9, $sutakApplicable, 'Expected sutak applicable only for those 9 visible eclipses.');

        $visibleKeySet = [];
        foreach ($visible as $event) {
            $visibleKeySet[$this->eventKey($event)] = true;
        }

        $expectedVisibleKeys = [
            '2018-01-31|Lunar|Total',
            '2018-07-28|Lunar|Total',
            '2019-07-17|Lunar|Partial',
            '2019-12-26|Solar|Partial',
            '2020-06-21|Solar|Partial',
            '2022-10-25|Solar|Partial',
            '2022-11-08|Lunar|Partial',
            '2023-10-29|Lunar|Partial',
            '2025-09-07|Lunar|Total',
        ];

        sort($expectedVisibleKeys);
        $actualVisibleKeys = array_keys($visibleKeySet);
        sort($actualVisibleKeys);

        self::assertSame($expectedVisibleKeys, $actualVisibleKeys, 'Visible eclipse set mismatch against Eclipse.txt baseline.');
    }

    private function eventKey(array $event): string
    {
        return sprintf(
            '%s|%s|%s',
            (string) ($event['date'] ?? ''),
            (string) ($event['type'] ?? ''),
            (string) ($event['eclipse_type'] ?? '')
        );
    }
}
