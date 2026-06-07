<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Tests;

use JayeshMepani\PanchangCore\Facades\Panchang;
use JayeshMepani\PanchangCore\PanchangServiceProvider;
use Orchestra\Testbench\TestCase;

class MonthFieldsSelectiveTest extends TestCase
{
    public function testMonthTithiFieldsReturnOnlyTithiGroupWithCompanions(): void
    {
        config(['panchang.defaults.locale' => 'en']);

        $full = Panchang::getMonthCalendar(
            2026,
            6,
            23.2472446,
            69.668339,
            'Asia/Kolkata',
            0.0,
            ['festival_scope' => 'month']
        );
        $selected = Panchang::getMonthFields(
            2026,
            6,
            23.2472446,
            69.668339,
            'Asia/Kolkata',
            ['tithi'],
            0.0,
            ['festival_scope' => 'month']
        );

        $this->assertCount(30, $selected);
        $this->assertSame(['date', 'day', 'tithi', 'tithi_display', 'tithi_windows'], array_keys($selected['2026-06-01']));
        $this->assertSame($full['2026-06-01']['tithi'], $selected['2026-06-01']['tithi']);
        $this->assertSame($full['2026-06-01']['tithi_display'], $selected['2026-06-01']['tithi_display']);
        $this->assertSame($full['2026-06-01']['tithi_windows'], $selected['2026-06-01']['tithi_windows']);
        $this->assertArrayNotHasKey('nakshatra', $selected['2026-06-01']);
        $this->assertArrayNotHasKey('festivals', $selected['2026-06-01']);
    }

    public function testMonthMoonAndFestivalFieldsMatchFullCalendarGroups(): void
    {
        config(['panchang.defaults.locale' => 'en']);

        $full = Panchang::getMonthCalendar(
            2026,
            4,
            23.2472446,
            69.668339,
            'Asia/Kolkata',
            0.0,
            ['festival_scope' => 'month']
        );
        $selected = Panchang::getMonthFields(
            2026,
            4,
            23.2472446,
            69.668339,
            'Asia/Kolkata',
            ['moon', 'festivals'],
            0.0,
            ['festival_scope' => 'month']
        );

        foreach (['moon_sign', 'moon_phase', 'moonrise', 'moonset', 'moonrise_date', 'moonset_date', 'moonrise_iso', 'moonset_iso', 'moonset_day_relation', 'moon_visibility', 'festivals', 'daily_observances', 'sankranti'] as $key) {
            $this->assertSame($full['2026-04-01'][$key], $selected['2026-04-01'][$key], $key);
        }

        $this->assertArrayNotHasKey('tithi', $selected['2026-04-01']);
        $this->assertArrayNotHasKey('sunrise', $selected['2026-04-01']);
    }

    public function testCalendarPeriodWindowsRangeReturnsSelectedWindowsOnly(): void
    {
        config(['panchang.defaults.locale' => 'en']);

        $selected = Panchang::getCalendarPeriodWindowsRange(
            2026,
            10,
            2026,
            11,
            23.2472446,
            69.668339,
            'Asia/Kolkata',
            ['ayana', 'ritu', 'amanta_month', 'purnimanta_month']
        );

        $this->assertSame(
            ['ayana_windows', 'ritu_windows', 'amanta_month_windows', 'purnimanta_month_windows'],
            array_keys($selected)
        );
        $this->assertNotEmpty($selected['ayana_windows']);
        $this->assertNotEmpty($selected['ritu_windows']);
        $this->assertNotEmpty($selected['amanta_month_windows']);
        $this->assertNotEmpty($selected['purnimanta_month_windows']);
        $this->assertSame(['name', 'start_iso', 'end_iso'], array_keys($selected['ayana_windows'][0]));
        $this->assertSame(['name', 'start_iso', 'end_iso'], array_keys($selected['ritu_windows'][0]));
        $this->assertSame(['name', 'start_iso', 'end_iso', 'index'], array_keys($selected['amanta_month_windows'][0]));
        $this->assertSame(['name', 'start_iso', 'end_iso', 'index'], array_keys($selected['purnimanta_month_windows'][0]));
        $this->assertArrayNotHasKey('vikram_samvat_windows', $selected);
    }

    public function testPurnimantaCalendarPeriodWindowsPreserveIntercalaryMonthLabel(): void
    {
        config(['panchang.defaults.locale' => 'en']);

        $selected = Panchang::getCalendarPeriodWindowsRange(
            2026,
            4,
            2026,
            6,
            23.2472446,
            69.668339,
            'Asia/Kolkata',
            ['purnimanta_month']
        );

        $jyeshthaWindows = array_values(array_filter(
            $selected['purnimanta_month_windows'],
            static fn (array $window): bool => str_starts_with((string) $window['name'], 'Jyeshtha')
        ));

        $this->assertCount(2, $jyeshthaWindows);
        $this->assertSame('Jyeshtha (Intercalary)', $jyeshthaWindows[0]['name']);
        $this->assertSame('Jyeshtha', $jyeshthaWindows[1]['name']);
    }

    public function testAllMonthFieldGroupsMatchFullMonthCalendar(): void
    {
        config(['panchang.defaults.locale' => 'en']);

        $full = Panchang::getMonthCalendar(
            2026,
            4,
            23.2472446,
            69.668339,
            'Asia/Kolkata',
            0.0,
            ['festival_scope' => 'month']
        );
        $selected = Panchang::getMonthFields(
            2026,
            4,
            23.2472446,
            69.668339,
            'Asia/Kolkata',
            ['tithi', 'nakshatra', 'yoga', 'karana', 'vara', 'sun', 'moon', 'hindu_calendar', 'festivals'],
            0.0,
            ['festival_scope' => 'month']
        );

        $this->assertSame(
            json_encode($full, JSON_THROW_ON_ERROR),
            json_encode($selected, JSON_THROW_ON_ERROR)
        );
    }

    protected function getPackageProviders($app): array
    {
        return [PanchangServiceProvider::class];
    }
}
