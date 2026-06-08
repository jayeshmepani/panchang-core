<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Tests;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Panchanga\PanchangService;
use JayeshMepani\PanchangCore\PanchangServiceProvider;
use Orchestra\Testbench\TestCase;

class SelectiveRuntimeApiTest extends TestCase
{
    public function testDailyMuhurtaEvaluationUsesFocusedOutputContract(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);
        $date = CarbonImmutable::create(2026, 5, 23, 0, 0, 0, 'Asia/Kolkata');
        $at = CarbonImmutable::create(2026, 5, 23, 10, 30, 0, 'Asia/Kolkata');

        $evaluation = $service->getDailyMuhurtaEvaluation($date, 23.2472446, 69.668339, 'Asia/Kolkata', $at);

        $this->assertArrayHasKey('input_parameters', $evaluation);
        $this->assertArrayHasKey('evaluation_results', $evaluation);
        $this->assertArrayHasKey('rejection_report', $evaluation);
        $this->assertArrayHasKey('bhadra', $evaluation['evaluation_results']);
        $this->assertArrayHasKey('varjyam', $evaluation['evaluation_results']);
        $this->assertArrayHasKey('amrita_kaal', $evaluation['evaluation_results']);
    }

    public function testElectionalSnapshotCarriesBasicDayDetailsOnly(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);
        $date = CarbonImmutable::create(2026, 5, 23, 0, 0, 0, 'Asia/Kolkata');

        $snapshot = $service->getElectionalSnapshot($date, 23.2472446, 69.668339, 'Asia/Kolkata');

        $this->assertArrayHasKey('day_details', $snapshot);
        $this->assertArrayHasKey('Tithi', $snapshot['day_details']);
        $this->assertArrayHasKey('Panchanga', $snapshot['day_details']);
        $this->assertArrayHasKey('sunrise_context', $snapshot);
        $this->assertArrayNotHasKey('Special_Yogas', $snapshot['day_details']);
        $this->assertArrayNotHasKey('Muhurta_Full_Day', $snapshot['day_details']);
    }

    public function testFestivalAndVratYearCalendarsAreResolvedSeparately(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);

        $all = $service->getFestivalYearCalendar(2026, 23.2472446, 69.668339, 'Asia/Kolkata');
        $festivalsOnly = $service->getFestivalYearCalendarOnlyFestivals(2026, 23.2472446, 69.668339, 'Asia/Kolkata');
        $vratsOnly = $service->getVratYearCalendar(2026, 23.2472446, 69.668339, 'Asia/Kolkata');

        foreach ($festivalsOnly['flat'] as $entry) {
            $this->assertFalse((bool) ($entry['festival']['fasting'] ?? false));
        }

        foreach ($vratsOnly['flat'] as $entry) {
            $this->assertTrue((bool) ($entry['festival']['fasting'] ?? false));
        }

        $combinedKeys = [];
        foreach (array_merge($festivalsOnly['flat'], $vratsOnly['flat']) as $entry) {
            $key = $entry['date'] . '|' . $entry['festival']['name'];
            $this->assertArrayNotHasKey($key, $combinedKeys);
            $combinedKeys[$key] = true;
        }

        foreach ($all['flat'] as $entry) {
            $this->assertArrayHasKey($entry['date'] . '|' . $entry['festival']['name'], $combinedKeys);
        }
    }

    protected function getPackageProviders($app): array
    {
        return [PanchangServiceProvider::class];
    }
}
