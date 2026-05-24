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
class EkadashiDateRegressionTest extends TestCase
{
    public function test_un_gujarat_ekadashi_regression_dates(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);

        foreach ([
            '2026-05-27' => 'Padmini Ekadashi',
            '2026-08-23' => 'Shravana Putrada Ekadashi',
            '2026-11-20' => 'Devutthana (Prabodhini) Ekadashi',
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

    #[Override]
    protected function getPackageProviders($app): array
    {
        return [PanchangServiceProvider::class];
    }
}
