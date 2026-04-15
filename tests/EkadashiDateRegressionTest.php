<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Tests;

use JayeshMepani\PanchangCore\Panchanga\PanchangService;
use JayeshMepani\PanchangCore\PanchangServiceProvider;
use Orchestra\Testbench\TestCase;
use Override;

class EkadashiDateRegressionTest extends TestCase
{
    public function test_un_gujarat_ekadashi_regression_dates(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);

        $festivals = $service->getFestivalYearCalendar(
            year: 2026,
            lat: 23.2472446,
            lon: 69.668339,
            tz: 'Asia/Kolkata',
            elevation: 0.0,
            calculationAt: null,
            calendarType: 'amanta',
        );

        $datesByFestival = [];
        foreach ($festivals['flat'] as $entry) {
            $festival = (array) $entry['festival'];
            $name = (string) ($festival['resolution']['festival_name'] ?? $festival['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $date = (string) $entry['date'];
            if ($date === '') {
                continue;
            }

            $datesByFestival[$name][] = $date;
        }

        $this->assertSame(['2026-05-27'], $datesByFestival['Padmini Ekadashi'] ?? []);
        $this->assertSame(['2026-08-23'], $datesByFestival['Shravana Putrada Ekadashi'] ?? []);
        $this->assertSame(['2026-11-20'], $datesByFestival['Devutthana (Prabodhini) Ekadashi'] ?? []);
    }

    #[Override]
    protected function getPackageProviders($app): array
    {
        return [PanchangServiceProvider::class];
    }
}
