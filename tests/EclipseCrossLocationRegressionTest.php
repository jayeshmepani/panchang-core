<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Tests;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Traits\CliBootstrap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('slow')]
final class EclipseCrossLocationRegressionTest extends TestCase
{
    public function testBhujGlobalEclipseCatalogFor2026To2032MatchesNasaTypes(): void
    {
        CliBootstrap::init(dirname(__DIR__));
        $service = CliBootstrap::makeEclipseService();

        $events = [];
        for ($year = 2026; $year <= 2032; $year++) {
            foreach ($service->getEclipsesForYear($year, 23.2472446, 69.668339, 'Asia/Kolkata') as $event) {
                $events[(string) ($event['date'] ?? '') . '|' . (string) ($event['type'] ?? '')] = $event;
            }
        }

        self::assertCount(33, $events);

        foreach ([
            '2026-03-03|Lunar' => 'Total',
            '2026-08-28|Lunar' => 'Partial',
            '2028-01-12|Lunar' => 'Partial',
            '2029-06-26|Lunar' => 'Total',
            '2029-12-05|Solar' => 'Partial',
            '2027-08-02|Solar' => 'Total',
            '2030-06-01|Solar' => 'Annular',
            '2031-05-21|Solar' => 'Annular',
            '2031-11-15|Solar' => 'Hybrid',
        ] as $key => $expectedType) {
            self::assertArrayHasKey($key, $events);
            self::assertSame($expectedType, $events[$key]['global_eclipse_type'] ?? null, $key);
            self::assertSame($expectedType, $events[$key]['eclipse_type'] ?? null, $key);
        }

        self::assertSame('Partial', $events['2027-08-02|Solar']['local_eclipse_type'] ?? null);
        self::assertSame('Partial', $events['2030-06-01|Solar']['local_eclipse_type'] ?? null);
        self::assertSame('Partial', $events['2031-05-21|Solar']['local_eclipse_type'] ?? null);
    }

    #[DataProvider('locationCases')]
    public function testCrossLocationEdgeCasesRemainStable(
        string $label,
        float $lat,
        float $lon,
        string $tz,
        int $year,
        string $expectedDate,
        string $expectedType,
        string $expectedLocalEclipseType,
        string $expectedGlobalEclipseType,
        string $expectedMaximum,
        string $expectedStart,
        string $expectedEnd,
        int $maximumDeltaSeconds,
        int $startDeltaSeconds,
        int $endDeltaSeconds,
    ): void {
        CliBootstrap::init(dirname(__DIR__));
        $service = CliBootstrap::makeEclipseService();

        $events = $service->getEclipsesForYear($year, $lat, $lon, $tz);
        $match = null;
        foreach ($events as $event) {
            if (($event['date'] ?? null) === $expectedDate && ($event['type'] ?? null) === $expectedType) {
                $match = $event;
                break;
            }
        }

        self::assertIsArray($match, $label . ' expected event missing');
        self::assertTrue((bool) ($match['visibility']['visible'] ?? false), $label . ' should be locally visible');
        self::assertSame($expectedGlobalEclipseType, $match['global_eclipse_type'] ?? null, $label . ' global eclipse type mismatch');
        self::assertSame($expectedLocalEclipseType, $match['local_eclipse_type'] ?? null, $label . ' local eclipse type mismatch');

        $this->assertTimeClose($match['datetime'] ?? null, $expectedMaximum, $tz, $maximumDeltaSeconds, $label . ' maximum');
        $this->assertTimeClose($match['visibility']['window']['start'] ?? null, $expectedStart, $tz, $startDeltaSeconds, $label . ' start');
        $this->assertTimeClose($match['visibility']['window']['end'] ?? null, $expectedEnd, $tz, $endDeltaSeconds, $label . ' end');
    }

    /** @return iterable<string, array<int, float|int|string>> */
    public static function locationCases(): iterable
    {
        yield 'Singapore total lunar 2021-05-26' => [
            'Singapore',
            1.3521,
            103.8198,
            'Asia/Singapore',
            2021,
            '2021-05-26',
            'Lunar',
            'Total',
            'Total',
            '26/05/2021 07:18:00 PM',
            '26/05/2021 07:04:00 PM',
            '26/05/2021 08:52:00 PM',
            120,
            300,
            120,
        ];

        yield 'Oslo partial solar 2021-06-10' => [
            'Oslo',
            59.9139,
            10.7522,
            'Europe/Oslo',
            2021,
            '2021-06-10',
            'Solar',
            'Partial',
            'Annular',
            '10/06/2021 12:44:06 PM',
            '10/06/2021 11:31:57 AM',
            '10/06/2021 01:58:10 PM',
            120,
            120,
            120,
        ];

        yield 'Exmouth total solar 2023-04-20' => [
            'Exmouth',
            -21.795,
            114.106,
            'Australia/Perth',
            2023,
            '2023-04-20',
            'Solar',
            'Total',
            'Hybrid',
            '20/04/2023 11:31:44 AM',
            '20/04/2023 10:05:41 AM',
            '20/04/2023 01:04:10 PM',
            120,
            120,
            120,
        ];

        yield 'Albuquerque annular solar 2023-10-14' => [
            'Albuquerque',
            35.0844,
            -106.6504,
            'America/Denver',
            2023,
            '2023-10-14',
            'Solar',
            'Annular',
            'Annular',
            '14/10/2023 10:38:09 AM',
            '14/10/2023 09:14:06 AM',
            '14/10/2023 12:10:41 PM',
            120,
            120,
            120,
        ];

        yield 'Mazatlan total solar 2024-04-08' => [
            'Mazatlan',
            23.2494,
            -106.4111,
            'America/Mazatlan',
            2024,
            '2024-04-08',
            'Solar',
            'Total',
            'Total',
            '08/04/2024 11:09:00 AM',
            '08/04/2024 09:51:00 AM',
            '08/04/2024 12:32:00 PM',
            120,
            120,
            120,
        ];
    }

    private function assertTimeClose(mixed $actual, string $expected, string $tz, int $deltaSeconds, string $label): void
    {
        self::assertIsString($actual, $label);

        $actualTime = CarbonImmutable::createFromFormat('d/m/Y h:i:s A', $actual, $tz);
        $expectedTime = CarbonImmutable::createFromFormat('d/m/Y h:i:s A', $expected, $tz);

        self::assertNotFalse($actualTime, $label . ' actual parse');
        self::assertNotFalse($expectedTime, $label . ' expected parse');
        self::assertLessThanOrEqual(
            $deltaSeconds,
            abs($actualTime->diffInSeconds($expectedTime, false)),
            $label
        );
    }
}
