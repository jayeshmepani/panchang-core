<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Tests;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Facades\Panchang;
use JayeshMepani\PanchangCore\PanchangServiceProvider;
use Orchestra\Testbench\TestCase;
use Override;
use PHPUnit\Framework\Attributes\Group;

#[Group('slow')]
class MonthCalendarTest extends TestCase
{
    public function test_month_calendar_retrieval(): void
    {
        $lat = 23.247;
        $lon = 69.668;
        $tz = 'Asia/Kolkata';
        $year = 2026;
        $month = 4; // April 2026

        // Set locale to English for consistent test assertions
        config(['panchang.defaults.locale' => 'en']);

        $calendar = Panchang::getMonthCalendar($year, $month, $lat, $lon, $tz, 0.0, ['festival_scope' => 'month']);

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

        // Check for a known April festival from the current rule set.
        $foundChaitraPurnima = false;
        foreach ($calendar as $day) {
            foreach ($day['festivals'] as $fest) {
                if ((string) $fest['name'] === 'Chaitra Purnima') {
                    $foundChaitraPurnima = true;
                }
            }
        }

        $this->assertTrue($foundChaitraPurnima, 'Chaitra Purnima should be present in April 2026');
    }

    public function test_month_calendar_exposes_double_tithi_and_kshaya_tithi_metadata(): void
    {
        config(['panchang.defaults.locale' => 'en']);

        $calendar = Panchang::getMonthCalendar(
            2026,
            6,
            23.2472446,
            69.668339,
            'Asia/Kolkata',
            0.0,
            ['festival_scope' => 'month']
        );

        $day = $calendar['2026-06-15'];

        $this->assertSame('30/1', $day['tithi_display']['display'] ?? null);
        $this->assertSame([30, 1], $day['tithi_display']['indexes'] ?? null);
        $this->assertTrue((bool) ($day['tithi_display']['has_multiple_tithis'] ?? false));
        $this->assertTrue((bool) ($day['tithi_display']['has_kshaya_tithi'] ?? false));
        $this->assertSame(1, $day['tithi_display']['kshaya_tithi']['index'] ?? null);
        $this->assertSame('First Lunar Day of Bright Half', $day['tithi_display']['kshaya_tithi']['name'] ?? null);
        $this->assertSame('Shukla', $day['tithi_display']['kshaya_tithi']['paksha'] ?? null);
        $this->assertSame('Bright Half (waxing)', $day['tithi_display']['kshaya_tithi']['paksha_name'] ?? null);
    }

    public function test_nakshatra_pada_names_are_fully_localized_in_hindi_and_gujarati(): void
    {
        $lat = 23.2472446;
        $lon = 69.668339;
        $tz = 'Asia/Kolkata';

        config(['panchang.defaults.locale' => 'hi']);
        $hiCalendar = Panchang::getMonthCalendar(2026, 6, $lat, $lon, $tz, 0.0, ['festival_scope' => 'month']);
        $this->assertSame('ज्येष्ठा पद ३', $hiCalendar['2026-06-01']['nakshatra_padas'][0]['name'] ?? null);

        config(['panchang.defaults.locale' => 'gu']);
        $guCalendar = Panchang::getMonthCalendar(2026, 6, $lat, $lon, $tz, 0.0, ['festival_scope' => 'month']);
        $this->assertSame('જ્યેષ્ઠા પદ ૩', $guCalendar['2026-06-01']['nakshatra_padas'][0]['name'] ?? null);
    }

    public function test_purnimanta_day_details_match_month_calendar_festivals(): void
    {
        $lat = 23.2472446;
        $lon = 69.668339;
        $tz = 'Asia/Kolkata';
        $year = 2026;
        $month = 4;

        config(['panchang.defaults.locale' => 'en']);

        $calendar = Panchang::getMonthCalendar($year, $month, $lat, $lon, $tz, 0.0, ['festival_scope' => 'month'], null, 'purnimanta');

        foreach (['2026-04-01', '2026-04-15', '2026-04-30'] as $date) {
            $day = $calendar[$date];
            $details = Panchang::getDayDetails(
                CarbonImmutable::parse($date, $tz),
                $lat,
                $lon,
                $tz,
                0.0,
                null,
                'purnimanta'
            );

            $monthFestivals = array_map(
                static fn (array $festival): string => (string) ($festival['name'] ?? ''),
                $day['festivals'] ?? []
            );
            $detailFestivals = array_map(
                static fn (array $festival): string => (string) ($festival['name'] ?? ''),
                $details['Festivals'] ?? []
            );

            sort($monthFestivals);
            sort($detailFestivals);

            $missingFestivals = array_values(array_diff($monthFestivals, $detailFestivals));

            $this->assertSame(
                [],
                $missingFestivals,
                $date . ' month-calendar festivals should all be present in day-detail view for purnimanta.'
            );
        }
    }

    public function testPhuldolotsavaStaysOnMarchFourthInPurnimantaAndDoesNotShiftToChaitraShuklaPratipada(): void
    {
        $lat = 23.2472446;
        $lon = 69.668339;
        $tz = 'Asia/Kolkata';

        config(['panchang.defaults.locale' => 'en']);

        $marchFourth = Panchang::getDayDetails(
            CarbonImmutable::parse('2026-03-04', $tz),
            $lat,
            $lon,
            $tz,
            0.0,
            null,
            'purnimanta'
        );

        $marchNineteenth = Panchang::getDayDetails(
            CarbonImmutable::parse('2026-03-19', $tz),
            $lat,
            $lon,
            $tz,
            0.0,
            null,
            'purnimanta'
        );

        $marchFourthFestival = null;
        foreach (($marchFourth['Festivals'] ?? []) as $festival) {
            if (($festival['name'] ?? null) === 'Phuldolotsava') {
                $marchFourthFestival = $festival;
                break;
            }
        }

        $marchNineteenthNames = array_map(
            static fn (array $festival): string => (string) ($festival['name'] ?? ''),
            $marchNineteenth['Festivals'] ?? []
        );

        $this->assertIsArray($marchFourthFestival);
        $this->assertSame('2026-03-04', $marchFourthFestival['resolution']['observance_date'] ?? null);
        $this->assertSame('Krishna', $marchFourthFestival['resolution']['paksha'] ?? null);
        $this->assertSame('Chaitra', $marchFourthFestival['calculation_basis']['month']['value'] ?? null);
        $this->assertNotContains('Phuldolotsava', $marchNineteenthNames);
    }

    #[Override]
    protected function getPackageProviders($app)
    {
        return [PanchangServiceProvider::class];
    }
}
