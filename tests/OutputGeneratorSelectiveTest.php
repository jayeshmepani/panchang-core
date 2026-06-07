<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Tests;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Facades\Panchang;
use JayeshMepani\PanchangCore\Panchanga\OutputGeneratorService;
use JayeshMepani\PanchangCore\PanchangServiceProvider;
use Orchestra\Testbench\TestCase;

class OutputGeneratorSelectiveTest extends TestCase
{
    public function testFestivalSelectiveOutputsMatchFullGeneratorBranches(): void
    {
        /** @var OutputGeneratorService $service */
        $service = $this->app->make(OutputGeneratorService::class);

        $full = $service->generateFestivals(2026, 23.2472446, 69.668339, 'Asia/Kolkata');
        $selected = $service->generateFestivalsSelected(2026, 23.2472446, 69.668339, 'Asia/Kolkata', ['by_date', 'flat']);

        $this->assertSame(
            json_encode($full['festivals']['by_date'], JSON_THROW_ON_ERROR),
            json_encode($selected['by_date'], JSON_THROW_ON_ERROR)
        );
        $this->assertSame(
            json_encode($full['festivals']['flat'], JSON_THROW_ON_ERROR),
            json_encode($selected['flat'], JSON_THROW_ON_ERROR)
        );
        $this->assertSame(
            json_encode(['flat' => $full['festivals']['flat']], JSON_THROW_ON_ERROR),
            json_encode($service->generateFestivalFlat(2026, 23.2472446, 69.668339, 'Asia/Kolkata'), JSON_THROW_ON_ERROR)
        );
        $this->assertSame(
            json_encode(['by_date' => $full['festivals']['by_date']], JSON_THROW_ON_ERROR),
            json_encode($service->generateFestivalByDate(2026, 23.2472446, 69.668339, 'Asia/Kolkata'), JSON_THROW_ON_ERROR)
        );
    }

    public function testFestivalAndVratSelectiveOutputsAreBuiltAsSeparateContracts(): void
    {
        /** @var OutputGeneratorService $service */
        $service = $this->app->make(OutputGeneratorService::class);

        $all = $service->generateFestivals(2026, 23.2472446, 69.668339, 'Asia/Kolkata');
        $festivalsOnly = $service->generateFestivalsOnly(2026, 23.2472446, 69.668339, 'Asia/Kolkata');
        $vratsOnly = $service->generateVrats(2026, 23.2472446, 69.668339, 'Asia/Kolkata');

        $allFlat = $all['festivals']['flat'];
        $festivalFlat = $festivalsOnly['festivals']['flat'];
        $vratFlat = $vratsOnly['vrats']['flat'];

        $this->assertNotEmpty($festivalFlat);
        $this->assertNotEmpty($vratFlat);

        foreach ($festivalFlat as $entry) {
            $this->assertFalse((bool) ($entry['festival']['fasting'] ?? false));
        }

        foreach ($vratFlat as $entry) {
            $this->assertTrue((bool) ($entry['festival']['fasting'] ?? false));
        }

        $combinedKeys = [];
        foreach (array_merge($festivalFlat, $vratFlat) as $entry) {
            $key = $entry['date'] . '|' . $entry['festival']['name'];
            $this->assertArrayNotHasKey($key, $combinedKeys);
            $combinedKeys[$key] = true;
        }

        foreach ($allFlat as $entry) {
            $this->assertArrayHasKey($entry['date'] . '|' . $entry['festival']['name'], $combinedKeys);
        }
    }

    public function testFestivalAndVratSelectedBranchesMatchTheirDedicatedOutputs(): void
    {
        /** @var OutputGeneratorService $service */
        $service = $this->app->make(OutputGeneratorService::class);

        $festivalSelected = $service->generateFestivalsOnlySelected(2026, 23.2472446, 69.668339, 'Asia/Kolkata', ['by_date', 'flat']);
        $festivalOnly = $service->generateFestivalsOnly(2026, 23.2472446, 69.668339, 'Asia/Kolkata');
        $vratSelected = $service->generateVratsSelected(2026, 23.2472446, 69.668339, 'Asia/Kolkata', ['by_date', 'flat']);
        $vratsOnly = $service->generateVrats(2026, 23.2472446, 69.668339, 'Asia/Kolkata');

        $this->assertSame(
            json_encode($festivalOnly['festivals']['by_date'], JSON_THROW_ON_ERROR),
            json_encode($festivalSelected['by_date'], JSON_THROW_ON_ERROR)
        );
        $this->assertSame(
            json_encode($festivalOnly['festivals']['flat'], JSON_THROW_ON_ERROR),
            json_encode($festivalSelected['flat'], JSON_THROW_ON_ERROR)
        );
        $this->assertSame(
            json_encode($vratsOnly['vrats']['by_date'], JSON_THROW_ON_ERROR),
            json_encode($vratSelected['by_date'], JSON_THROW_ON_ERROR)
        );
        $this->assertSame(
            json_encode($vratsOnly['vrats']['flat'], JSON_THROW_ON_ERROR),
            json_encode($vratSelected['flat'], JSON_THROW_ON_ERROR)
        );
        $this->assertSame(
            json_encode(['by_date' => $festivalOnly['festivals']['by_date']], JSON_THROW_ON_ERROR),
            json_encode($service->generateFestivalOnlyByDate(2026, 23.2472446, 69.668339, 'Asia/Kolkata'), JSON_THROW_ON_ERROR)
        );
        $this->assertSame(
            json_encode(['flat' => $festivalOnly['festivals']['flat']], JSON_THROW_ON_ERROR),
            json_encode($service->generateFestivalOnlyFlat(2026, 23.2472446, 69.668339, 'Asia/Kolkata'), JSON_THROW_ON_ERROR)
        );
        $this->assertSame(
            json_encode(['by_date' => $vratsOnly['vrats']['by_date']], JSON_THROW_ON_ERROR),
            json_encode($service->generateVratByDate(2026, 23.2472446, 69.668339, 'Asia/Kolkata'), JSON_THROW_ON_ERROR)
        );
        $this->assertSame(
            json_encode(['flat' => $vratsOnly['vrats']['flat']], JSON_THROW_ON_ERROR),
            json_encode($service->generateVratFlat(2026, 23.2472446, 69.668339, 'Asia/Kolkata'), JSON_THROW_ON_ERROR)
        );
    }

    public function testCompactVratOutputExtractsRecurringWeekdayVratsFromByDatePayload(): void
    {
        /** @var OutputGeneratorService $service */
        $service = $this->app->make(OutputGeneratorService::class);

        $compact = $service->generateVratsByDateCompact(2026, 23.2472446, 69.668339, 'Asia/Kolkata');

        $this->assertArrayHasKey('recurring_weekday_vrats', $compact['vrats']);
        $this->assertNotEmpty($compact['vrats']['recurring_weekday_vrats']);

        foreach ($compact['vrats']['recurring_weekday_vrats'] as $entry) {
            $this->assertSame('weekday', $entry['calculation_basis']['type'] ?? null);
        }

        foreach ($compact['vrats']['by_date'] as $entries) {
            foreach ($entries as $entry) {
                $this->assertNotSame('weekday', $entry['calculation_basis']['type'] ?? null);
            }
        }
    }

    public function testFestivalAndVratTotalsCountUniqueIdentitiesNotOccurrences(): void
    {
        /** @var OutputGeneratorService $service */
        $service = $this->app->make(OutputGeneratorService::class);

        $combined = $service->generateFestivals(2026, 23.2472446, 69.668339, 'Asia/Kolkata')['festivals'];
        $festivalOnly = $service->generateFestivalsOnly(2026, 23.2472446, 69.668339, 'Asia/Kolkata')['festivals'];
        $vratCompact = $service->generateVratsByDateCompact(2026, 23.2472446, 69.668339, 'Asia/Kolkata')['vrats'];
        $selectedTotals = $service->generateFestivalsSelected(
            2026,
            23.2472446,
            69.668339,
            'Asia/Kolkata',
            ['total_festivals', 'total_vrats']
        );

        $this->assertSame($this->countUniqueIdentities($combined['by_date'], false), $combined['total_festivals']);
        $this->assertSame($this->countUniqueIdentities($combined['by_date'], true), $combined['total_vrats']);
        $this->assertSame($combined['total_festivals'], $selectedTotals['total_festivals']);
        $this->assertSame($combined['total_vrats'], $selectedTotals['total_vrats']);
        $this->assertSame($this->countUniqueIdentities($festivalOnly['by_date']), $festivalOnly['total_festivals']);
        $this->assertSame(
            $this->countUniqueIdentities($vratCompact['by_date'], null, $vratCompact['recurring_weekday_vrats']),
            $vratCompact['total_vrats']
        );

        $this->assertLessThan($combined['festival_entry_count'], $combined['total_festivals']);
        $this->assertLessThan($vratCompact['vrat_entry_count'] + 365, $vratCompact['total_vrats']);
    }

    public function testEclipseSelectiveOutputsMatchFullGeneratorBranches(): void
    {
        /** @var OutputGeneratorService $service */
        $service = $this->app->make(OutputGeneratorService::class);

        $full = $service->generateEclipses(2026, 2026, 23.2472446, 69.668339, 'Asia/Kolkata');
        $selected = $service->generateEclipsesSelected(2026, 2026, 23.2472446, 69.668339, 'Asia/Kolkata', ['by_year', 'flat']);

        $this->assertSame(
            json_encode($full['eclipses']['by_year'], JSON_THROW_ON_ERROR),
            json_encode($selected['by_year'], JSON_THROW_ON_ERROR)
        );
        $this->assertSame(
            json_encode($full['eclipses']['flat'], JSON_THROW_ON_ERROR),
            json_encode($selected['flat'], JSON_THROW_ON_ERROR)
        );
        $this->assertSame(
            json_encode(['flat' => $full['eclipses']['flat']], JSON_THROW_ON_ERROR),
            json_encode($service->generateEclipseFlat(2026, 2026, 23.2472446, 69.668339, 'Asia/Kolkata'), JSON_THROW_ON_ERROR)
        );
        $this->assertSame(
            json_encode(['by_year' => $full['eclipses']['by_year']], JSON_THROW_ON_ERROR),
            json_encode($service->generateEclipseByYear(2026, 2026, 23.2472446, 69.668339, 'Asia/Kolkata'), JSON_THROW_ON_ERROR)
        );
    }

    public function testFestivalRangeSelectedReturnsOnlyRequestedMonthWindow(): void
    {
        /** @var OutputGeneratorService $service */
        $service = $this->app->make(OutputGeneratorService::class);

        $selected = $service->generateFestivalsRangeSelected(
            2026,
            10,
            2028,
            8,
            23.2472446,
            69.668339,
            'Asia/Kolkata',
            ['by_date', 'flat']
        );

        $this->assertNotEmpty($selected['by_date']);
        $this->assertSame('2026-10-01', array_key_first($selected['by_date']));
        $this->assertLessThanOrEqual('2028-08-31', (string) array_key_last($selected['by_date']));

        foreach ($selected['flat'] as $entry) {
            $this->assertGreaterThanOrEqual('2026-10-01', $entry['date']);
            $this->assertLessThanOrEqual('2028-08-31', $entry['date']);
        }
    }

    public function testEclipseRangeSelectedReturnsOnlyRequestedMonthWindow(): void
    {
        /** @var OutputGeneratorService $service */
        $service = $this->app->make(OutputGeneratorService::class);

        $selected = $service->generateEclipsesRangeSelected(
            2026,
            10,
            2028,
            8,
            23.2472446,
            69.668339,
            'Asia/Kolkata',
            ['by_year', 'flat']
        );

        foreach ($selected['flat'] as $entry) {
            $this->assertGreaterThanOrEqual('2026-10-01', $entry['date']);
            $this->assertLessThan('2028-09-01', $entry['date']);
        }

        foreach (array_keys($selected['by_year']) as $year) {
            $this->assertGreaterThanOrEqual(2026, (int) $year);
            $this->assertLessThanOrEqual(2028, (int) $year);
        }
    }

    public function testTodaySelectedCanReturnOnlyRequestedBranches(): void
    {
        /** @var OutputGeneratorService $service */
        $service = $this->app->make(OutputGeneratorService::class);
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 5, 23, 10, 30, 0, 'Asia/Kolkata'));

        try {
            $detailsOnly = $service->generateTodaySelected(
                23.2472446,
                69.668339,
                'Asia/Kolkata',
                ['details'],
                ['Panchanga']
            );

            $this->assertSame(['todays_complete_details'], array_keys($detailsOnly));
            $this->assertSame(['Panchanga'], array_keys($detailsOnly['todays_complete_details']['details']));
            $this->assertArrayNotHasKey('muhurta_evaluation', $detailsOnly);

            $evaluationOnly = $service->generateTodaySelected(
                23.2472446,
                69.668339,
                'Asia/Kolkata',
                ['muhurta_evaluation']
            );

            $this->assertSame(['muhurta_evaluation'], array_keys($evaluationOnly));
            $this->assertArrayHasKey('evaluation_results', $evaluationOnly['muhurta_evaluation']);
            $this->assertArrayNotHasKey('todays_complete_details', $evaluationOnly);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function testTodaySelectedBranchesMatchFullTodayOutputForRequestedValues(): void
    {
        /** @var OutputGeneratorService $service */
        $service = $this->app->make(OutputGeneratorService::class);
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 5, 23, 10, 30, 0, 'Asia/Kolkata'));

        try {
            $full = $service->generateTodayPanchang(23.2472446, 69.668339, 'Asia/Kolkata');
            $selected = $service->generateTodaySelected(
                23.2472446,
                69.668339,
                'Asia/Kolkata',
                ['details', 'muhurta_evaluation'],
                ['Panchanga', 'Abhijit_Muhurta', 'Bhadra']
            );

            $this->assertSame(
                $full['todays_complete_details']['details']['Panchanga'],
                $selected['todays_complete_details']['details']['Panchanga']
            );
            $this->assertSame(
                $full['todays_complete_details']['details']['Abhijit_Muhurta'],
                $selected['todays_complete_details']['details']['Abhijit_Muhurta']
            );
            $this->assertSame(
                $full['todays_complete_details']['details']['Bhadra'],
                $selected['todays_complete_details']['details']['Bhadra']
            );
            $this->assertSame(
                json_encode($full['muhurta_evaluation'], JSON_THROW_ON_ERROR),
                json_encode($selected['muhurta_evaluation'], JSON_THROW_ON_ERROR)
            );
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function testMonthSelectedOutputCanReturnOnlyCalendarFields(): void
    {
        /** @var OutputGeneratorService $service */
        $service = $this->app->make(OutputGeneratorService::class);

        $selected = $service->generateMonthCalendarFields(
            2026,
            6,
            23.2472446,
            69.668339,
            'Asia/Kolkata',
            ['tithi'],
            0.0,
            ['festival_scope' => 'month']
        );
        $expected = Panchang::getMonthFields(
            2026,
            6,
            23.2472446,
            69.668339,
            'Asia/Kolkata',
            ['tithi'],
            0.0,
            ['festival_scope' => 'month']
        );

        $this->assertSame(['calendar'], array_keys($selected));
        $this->assertSame($expected, $selected['calendar']);
        $this->assertSame(['date', 'day', 'tithi', 'tithi_display', 'tithi_windows'], array_keys($selected['calendar']['2026-06-01']));
    }

    public function testMonthSelectedOutputCanReturnMetaAndSelectedCalendar(): void
    {
        /** @var OutputGeneratorService $service */
        $service = $this->app->make(OutputGeneratorService::class);

        $selected = $service->generateMonthSelected(
            2026,
            4,
            23.2472446,
            69.668339,
            'Asia/Kolkata',
            ['meta', 'calendar'],
            ['moon'],
            0.0,
            ['festival_scope' => 'month']
        );

        $this->assertSame(['meta', 'calendar'], array_keys($selected));
        $this->assertSame(2026, $selected['meta']['year']);
        $this->assertSame(4, $selected['meta']['month']);
        $this->assertArrayHasKey('moon_visibility', $selected['calendar']['2026-04-01']);
        $this->assertArrayNotHasKey('tithi', $selected['calendar']['2026-04-01']);
    }

    public function testMonthRangeSelectedReturnsOnlyRequestedMonths(): void
    {
        /** @var OutputGeneratorService $service */
        $service = $this->app->make(OutputGeneratorService::class);

        $selected = $service->generateMonthRangeSelected(
            2026,
            10,
            2027,
            2,
            23.2472446,
            69.668339,
            'Asia/Kolkata',
            ['meta', 'months'],
            ['tithi'],
            0.0,
            ['festival_scope' => 'month']
        );

        $this->assertSame(['meta', 'months'], array_keys($selected));
        $this->assertSame(['2026-10', '2026-11', '2026-12', '2027-01', '2027-02'], array_keys($selected['months']));
        $this->assertSame(2026, $selected['meta']['from_year']);
        $this->assertSame(10, $selected['meta']['from_month']);
        $this->assertSame(2027, $selected['meta']['to_year']);
        $this->assertSame(2, $selected['meta']['to_month']);
    }

    public function testMonthSelectedOutputAllCalendarFieldsMatchFullMonthCalendar(): void
    {
        /** @var OutputGeneratorService $service */
        $service = $this->app->make(OutputGeneratorService::class);

        $selected = $service->generateMonthCalendarFields(
            2026,
            4,
            23.2472446,
            69.668339,
            'Asia/Kolkata',
            ['tithi', 'nakshatra', 'yoga', 'karana', 'vara', 'sun', 'moon', 'hindu_calendar', 'festivals'],
            0.0,
            ['festival_scope' => 'month']
        );
        $full = Panchang::getMonthCalendar(
            2026,
            4,
            23.2472446,
            69.668339,
            'Asia/Kolkata',
            0.0,
            ['festival_scope' => 'month']
        );

        $this->assertSame(
            json_encode($full, JSON_THROW_ON_ERROR),
            json_encode($selected['calendar'], JSON_THROW_ON_ERROR)
        );
    }

    protected function getPackageProviders($app): array
    {
        return [PanchangServiceProvider::class];
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $byDate
     * @param array<int, array<string, mixed>> $extraEntries
     */
    private function countUniqueIdentities(array $byDate, ?bool $fasting = null, array $extraEntries = []): int
    {
        $identities = [];

        foreach ($byDate as $entries) {
            foreach ($entries as $entry) {
                if ($fasting !== null && (($entry['fasting'] ?? null) !== $fasting)) {
                    continue;
                }

                $key = trim((string) ($entry['name_key'] ?? $entry['name'] ?? ''));
                if ($key !== '') {
                    $identities[$key] = true;
                }
            }
        }

        foreach ($extraEntries as $entry) {
            if ($fasting !== null && (($entry['fasting'] ?? null) !== $fasting)) {
                continue;
            }

            $key = trim((string) ($entry['name_key'] ?? $entry['name'] ?? ''));
            if ($key !== '') {
                $identities[$key] = true;
            }
        }

        return count($identities);
    }
}
