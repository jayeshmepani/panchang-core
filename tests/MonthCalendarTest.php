<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Tests;

use JayeshMepani\PanchangCore\Facades\Panchang;
use JayeshMepani\PanchangCore\PanchangServiceProvider;
use Orchestra\Testbench\TestCase;
use Override;

class MonthCalendarTest extends TestCase
{
    public function test_month_calendar_retrieval()
    {
        $lat = 23.247;
        $lon = 69.668;
        $tz = 'Asia/Kolkata';
        $year = 2026;
        $month = 4; // April 2026

        // Set locale to English for consistent test assertions
        config(['panchang.defaults.locale' => 'en']);

        $calendar = Panchang::getMonthCalendar($year, $month, $lat, $lon, $tz);

        $this->assertIsArray($calendar);
        $this->assertCount(30, $calendar); // April has 30 days

        $firstDay = $calendar['2026-04-01'];
        $this->assertEquals(1, $firstDay['day']);
        $this->assertArrayHasKey('tithi', $firstDay);
        $this->assertArrayHasKey('festivals', $firstDay);
        $this->assertArrayHasKey('moon_sign', $firstDay);
        $this->assertArrayHasKey('sunrise', $firstDay);
        $this->assertArrayHasKey('sunset', $firstDay);
        $this->assertArrayHasKey('moonrise', $firstDay);
        $this->assertArrayHasKey('moonset', $firstDay);
        $this->assertArrayHasKey('sun_sign', $firstDay);
        $this->assertArrayHasKey('yoga', $firstDay);
        $this->assertArrayHasKey('karana', $firstDay);

        // On 2026-04-01, it should be Chaitra Shukla 14 or 15 depending on sunrise
        // Let's just check if data is populated
        $this->assertNotEmpty($firstDay['tithi']['name']);

        // Check for Hanuman Jayanti (usually Chaitra Purnima)
        // 2026-04-01 is Chaitra Shukla Chaturdashi/Purnima
        $foundHanumanJayanti = false;
        foreach ($calendar as $day) {
            foreach ($day['festivals'] as $fest) {
                if (str_contains((string) $fest['name'], 'Hanuman Jayanti')) {
                    $foundHanumanJayanti = true;
                }
            }
        }
        $this->assertTrue($foundHanumanJayanti, 'Hanuman Jayanti should be in April 2026');
    }
    #[Override]
    protected function getPackageProviders($app)
    {
        return [PanchangServiceProvider::class];
    }
}
