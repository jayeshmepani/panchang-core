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
}
