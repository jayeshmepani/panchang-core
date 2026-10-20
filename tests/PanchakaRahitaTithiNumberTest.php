<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Tests;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Panchanga\PanchangService;
use JayeshMepani\PanchangCore\PanchangServiceProvider;
use Orchestra\Testbench\TestCase;

/**
 * Panchaka Rahita sums the 1-based tithi (1-30), vara (Sun=1), nakshatra
 * (1-27) and lagna (1-12). The tithi index returned by the engine is already
 * 1-based, so it enters the sum unchanged.
 */
final class PanchakaRahitaTithiNumberTest extends TestCase
{
    private const float LAT = 23.2472446;

    private const float LON = 69.668339;

    private const string TZ = 'Asia/Kolkata';

    public function testTithiNumberMatchesTheDayTithiAcrossALunation(): void
    {
        $service = $this->service();

        for ($offset = 0; $offset < 30; $offset++) {
            $date = CarbonImmutable::create(2026, 1, 1, 0, 0, 0, self::TZ)->addDays($offset);
            $details = $service->getDayDetails($date, self::LAT, self::LON, self::TZ, 0.0, $date->setTime(15, 0));
            $selected = $service->getSelectedDetails($date, self::LAT, self::LON, self::TZ, ['Panchaka_Rahita'], 0.0, $date->setTime(15, 0));

            $sunriseTithi = (int) $details['Tithi_At_Sunrise']['index'];
            $currentTithi = (int) $details['Current_Tithi_At_Input_Now']['index'];
            $label = $date->toDateString();

            foreach ([$details['Panchaka_Rahita'], $selected['Panchaka_Rahita']] as $panchaka) {
                self::assertSame($currentTithi, $panchaka['tithi'], $label);
                self::assertSame($details['Current_Tithi_At_Input_Now']['name'], $panchaka['tithi_name'], $label);
                self::assertSame($sunriseTithi, $panchaka['at_sunrise']['tithi'], $label);
                self::assertSame($details['Tithi_At_Sunrise']['name'], $panchaka['at_sunrise']['tithi_name'], $label);
                self::assertSame(
                    $panchaka['at_sunrise']['tithi'] + $panchaka['vara'] + $panchaka['at_sunrise']['nakshatra'] + $panchaka['at_sunrise']['lagna'],
                    $panchaka['at_sunrise']['sum'],
                    $label,
                );
            }

            self::assertSame($details['Panchaka_Rahita_Full_Day'][0]['sum'], $details['Panchaka_Rahita']['at_sunrise']['sum'], $label);
        }
    }

    public function testAmavasyaKeepsItsOwnNumberAndName(): void
    {
        $date = CarbonImmutable::create(2026, 1, 18, 0, 0, 0, self::TZ);
        $details = $this->service()->getDayDetails($date, self::LAT, self::LON, self::TZ, 0.0, $date->setTime(9, 0));

        self::assertSame(30, $details['Panchaka_Rahita']['tithi']);
        self::assertSame($details['Tithi_At_Sunrise']['name'], $details['Panchaka_Rahita']['tithi_name']);
        self::assertSame(30, $details['Panchaka_Rahita']['at_sunrise']['tithi']);
    }

    public function testSunriseSegmentOnKrishnaDwadashiIsChoraPanchaka(): void
    {
        // Bhuj, Thursday 15 Jan 2026 at 08:00: Krishna Dwadashi (27) + Thursday (5)
        // + Jyeshtha (18) + Makara lagna (10) = 60, remainder 6 (Chora Panchaka).
        // At sunrise the lagna is still Dhanu (9): 59, remainder 5 (Shubha).
        $date = CarbonImmutable::create(2026, 1, 15, 0, 0, 0, self::TZ);
        $panchaka = $this->service()->getDayDetails($date, self::LAT, self::LON, self::TZ, 0.0, $date->setTime(8, 0))['Panchaka_Rahita'];

        self::assertSame(27, $panchaka['tithi']);
        self::assertSame(60, $panchaka['sum']);
        self::assertSame(6, $panchaka['remainder']);
        self::assertTrue($panchaka['has_dosha']);
        self::assertSame(9, $panchaka['at_sunrise']['lagna']);
        self::assertSame(59, $panchaka['at_sunrise']['sum']);
        self::assertSame('Shubha Panchaka', $panchaka['at_sunrise']['panchaka_name']);
    }

    protected function getPackageProviders($app): array
    {
        return [PanchangServiceProvider::class];
    }

    private function service(): PanchangService
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);

        return $service;
    }
}
