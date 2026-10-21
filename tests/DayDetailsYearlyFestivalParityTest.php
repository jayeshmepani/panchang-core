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
class DayDetailsYearlyFestivalParityTest extends TestCase
{
    public function test_day_details_festivals_match_the_yearly_calendar(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);
        $yearly = $service->getFestivalYearCalendar(2026, 23.2472446, 69.668339, 'Asia/Kolkata', 0.0, null, 'amanta')['by_date'];

        foreach (['2026-06-24', '2026-09-03', '2026-09-04', '2026-10-10', '2026-10-17', '2026-10-18', '2026-11-20', '2026-11-21'] as $date) {
            $details = $service->getDayDetails(CarbonImmutable::parse($date, 'Asia/Kolkata'), 23.2472446, 69.668339, 'Asia/Kolkata', 0.0, null, 'amanta');

            $dayNames = array_map(static fn(array $festival): string => (string) $festival['name'], $details['Festivals']);
            $yearNames = array_map(static fn(array $festival): string => (string) $festival['name'], $yearly[$date] ?? []);
            sort($dayNames);
            sort($yearNames);

            $this->assertSame($yearNames, $dayNames, 'Festivals on ' . $date);
        }

        $this->assertContains('Maha Saptami (Durga Puja)', array_column($yearly['2026-10-17'], 'name'));
        $this->assertNotContains('Maha Saptami (Durga Puja)', array_column($yearly['2026-10-18'] ?? [], 'name'));
    }

    public function test_devutthana_ekadashi_follows_the_sunrise_tithi_window(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);
        $name = 'Devutthana (Prabodhini) Ekadashi';
        $locations = ['Bhuj' => [23.2472446, 69.668339], 'Delhi' => [28.6139, 77.2090]];

        foreach ($locations as $label => [$latitude, $longitude]) {
            foreach ([2026 => '2026-11-21', 2027 => '2027-11-10'] as $year => $expected) {
                $byDate = $service->getFestivalYearCalendar($year, $latitude, $longitude, 'Asia/Kolkata', 0.0, null, 'amanta')['by_date'];
                $dates = [];
                foreach ($byDate as $date => $festivals) {
                    if (in_array($name, array_column($festivals, 'name'), true)) {
                        $dates[] = $date;
                    }
                }

                $this->assertSame([$expected], $dates, $name . ' ' . $year . ' at ' . $label);
            }
        }
    }

    #[Override]
    protected function getPackageProviders($app): array
    {
        return [PanchangServiceProvider::class];
    }
}
