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

    #[Override]
    protected function getPackageProviders($app): array
    {
        return [PanchangServiceProvider::class];
    }
}
