<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Tests;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Panchanga\PanchangService;
use JayeshMepani\PanchangCore\PanchangServiceProvider;
use Orchestra\Testbench\TestCase;
use Override;
use PHPUnit\Framework\Attributes\Group;

#[Group('slow')]
class FestivalCoverageRegressionTest extends TestCase
{
    public function test_new_vrat_families_and_derived_observances_are_present(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);

        foreach ([
            '2026-01-03' => 'Shri Satyanarayana Vrat',
            '2026-01-05' => 'Somwar Vrat',
            '2026-01-14' => 'ISKCON Ekadashi',
            '2026-03-26' => 'Ashoka Ashtami Vrat',
            '2026-05-17' => 'Purushottam Maas Begins',
            '2026-05-27' => 'Mahadwadashi',
            '2026-06-15' => 'Purushottam Maas Ends',
            '2026-07-25' => 'Chaturmasa Begins',
            '2026-11-21' => 'Chaturmasa Ends',
        ] as $date => $expectedFestival) {
            $details = $service->getDayDetails(
                CarbonImmutable::parse($date, 'Asia/Kolkata'),
                23.2472446,
                69.668339,
                'Asia/Kolkata',
                0.0,
                null,
                'amanta',
            );

            $festivalNames = array_map(
                static fn (array $festival): string => (string) ($festival['resolution']['festival_name'] ?? $festival['name'] ?? ''),
                $details['Festivals'] ?? []
            );

            $this->assertContains($expectedFestival, $festivalNames, $expectedFestival . ' should be present on ' . $date);
        }
    }

    public function test_rohini_vrat_is_present_in_year_calendar(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);

        $calendar = $service->getFestivalYearCalendar(
            2026,
            23.2472446,
            69.668339,
            'Asia/Kolkata',
            0.0,
            null,
            'amanta',
        );

        $flat = $calendar['flat'] ?? [];
        $rohiniDates = [];
        foreach ($flat as $entry) {
            $festival = $entry['festival'] ?? [];
            $name = (string) ($festival['resolution']['festival_name'] ?? $festival['name'] ?? '');
            if ($name === 'Rohini Vrat') {
                $rohiniDates[] = (string) ($entry['date'] ?? '');
            }
        }

        $this->assertNotEmpty($rohiniDates, 'Rohini Vrat should be emitted in the annual festival calendar');
        $this->assertContains('2026-01-01', $rohiniDates, 'Known Rohini Vrat date should be present');
    }

    public function test_gujarat_2026_festival_dates_match_verified_public_baselines(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);

        $calendar = $service->getFestivalYearCalendar(
            2026,
            23.2472446,
            69.668339,
            'Asia/Kolkata',
            0.0,
            null,
            'amanta',
        );

        $datesByName = [];
        foreach (($calendar['flat'] ?? []) as $entry) {
            $festival = (array) ($entry['festival'] ?? []);
            $name = (string) ($festival['resolution']['festival_name'] ?? $festival['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $datesByName[$name][] = (string) ($entry['date'] ?? '');
        }

        $this->assertContains('2026-11-10', $datesByName['Govardhan Puja'] ?? [], 'Govardhan Puja should be emitted with the Kartika Shukla Pratipada observance.');
        $this->assertNotContains('2026-12-08', $datesByName['Govardhan Puja'] ?? [], 'Govardhan Puja must not leak into the next lunar month.');
        $this->assertSame(['2026-07-23'], array_values(array_unique($datesByName['Ashadha Gupt Navaratri Day 9'] ?? [])));
        $this->assertSame(['2026-03-03'], array_values(array_unique($datesByName['Phuldolotsava'] ?? [])));
        $this->assertSame(['2026-03-02'], array_values(array_unique($datesByName['Holika Dahan'] ?? [])));
        $this->assertSame(['2026-03-31'], array_values(array_unique($datesByName['Mahavir Jayanti'] ?? [])));
        $this->assertSame(['2026-09-12'], array_values(array_unique($datesByName['Samaveda Upakarma'] ?? [])));
        $this->assertSame(['2026-02-15'], array_values(array_unique($datesByName['Maha Shivaratri'] ?? [])));
        $this->assertNotContains('2026-02-16', array_values(array_unique($datesByName['Masik Shivaratri'] ?? [])));
        $this->assertSame(['2026-10-20'], array_values(array_unique($datesByName['Dussehra'] ?? [])));
        $this->assertContains('2026-09-14', array_values(array_unique($datesByName['Vinayaka Chaturthi'] ?? [])));
        $this->assertArrayNotHasKey('Ganesh Chaturthi', $datesByName);
        $this->assertArrayNotHasKey('Siddhivinayaka Chaturthi', $datesByName);
    }

    public function test_purnima_vrat_and_civil_festival_labels_are_split(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);

        $calendar = $service->getFestivalYearCalendar(
            2026,
            23.2472446,
            69.668339,
            'Asia/Kolkata',
            0.0,
            null,
            'amanta',
        );

        $datesByName = [];
        foreach (($calendar['flat'] ?? []) as $entry) {
            $festival = (array) ($entry['festival'] ?? []);
            $name = (string) ($festival['resolution']['festival_name'] ?? $festival['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $datesByName[$name][] = (string) ($entry['date'] ?? '');
        }

        self::assertSame(['2026-01-03'], array_values(array_unique($datesByName['Pausha Purnima Vrat'] ?? [])));
        self::assertSame(['2026-01-03'], array_values(array_unique($datesByName['Pausha Purnima'] ?? [])));
        self::assertSame(['2026-04-01'], array_values(array_unique($datesByName['Chaitra Purnima Vrat'] ?? [])));
        self::assertSame(['2026-04-02'], array_values(array_unique($datesByName['Chaitra Purnima'] ?? [])));
        self::assertSame(['2026-07-28'], array_values(array_unique($datesByName['Ashadha Purnima Vrat'] ?? [])));
        self::assertSame(['2026-07-29'], array_values(array_unique($datesByName['Ashadha Purnima'] ?? [])));
    }

    #[Override]
    protected function getPackageProviders($app): array
    {
        return [PanchangServiceProvider::class];
    }
}
