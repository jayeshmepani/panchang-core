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
        $this->assertArrayHasKey('planetary_states', $snapshot);
        $this->assertArrayHasKey('sunrise_context', $snapshot);
        $this->assertArrayNotHasKey('Special_Yogas', $snapshot['day_details']);
        $this->assertArrayNotHasKey('Muhurta_Full_Day', $snapshot['day_details']);
    }

    protected function getPackageProviders($app): array
    {
        return [PanchangServiceProvider::class];
    }
}
