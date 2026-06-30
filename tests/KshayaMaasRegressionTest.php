<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Tests;

use JayeshMepani\PanchangCore\Facades\Panchang;
use JayeshMepani\PanchangCore\PanchangServiceProvider;
use Orchestra\Testbench\TestCase;
use Override;
use PHPUnit\Framework\Attributes\Group;

#[Group('slow')]
class KshayaMaasRegressionTest extends TestCase
{
    public function test_kshaya_maas_keeps_running_month_and_does_not_label_current_month_as_omitted(): void
    {
        config(['panchang.defaults.locale' => 'en']);

        $calendar = Panchang::getMonthCalendar(
            2123,
            12,
            23.2472446,
            69.668339,
            'Asia/Kolkata',
            0.0,
            ['festival_scope' => 'month']
        );

        $before = $calendar['2123-12-17']['hindu_calendar'] ?? [];
        $start = $calendar['2123-12-18']['hindu_calendar'] ?? [];
        $next = $calendar['2123-12-19']['hindu_calendar'] ?? [];

        $this->assertSame('Kartika', $before['Month_Amanta_En'] ?? null);
        $this->assertSame('Margashirsha', $before['Month_Purnimanta_En'] ?? null);

        $this->assertSame('Margashirsha', $start['Month_Amanta_En'] ?? null);
        $this->assertSame('Margashirsha', $start['Month_Purnimanta_En'] ?? null);
        $this->assertTrue((bool) ($start['Is_Kshaya'] ?? false));
        $this->assertStringNotContainsString('Omitted', $start['Month_Amanta_En'] ?? '');
        $this->assertStringNotContainsString('Omitted', $start['Month_Purnimanta_En'] ?? '');

        $this->assertSame('Margashirsha', $next['Month_Amanta_En'] ?? null);
        $this->assertTrue((bool) ($next['Is_Kshaya'] ?? false));
    }

    public function test_amanta_pausha_kshaya_boundary_matches_expected_2124_sequence(): void
    {
        config(['panchang.defaults.locale' => 'en']);

        $calendar = Panchang::getMonthCalendar(
            2124,
            2,
            23.2472446,
            69.668339,
            'Asia/Kolkata',
            0.0,
            ['festival_scope' => 'month']
        );

        $amavasyaDay = $calendar['2124-02-15']['hindu_calendar'] ?? [];
        $nextDay = $calendar['2124-02-16']['hindu_calendar'] ?? [];

        $this->assertSame('Magha', $amavasyaDay['Month_Amanta_En'] ?? null);
        $this->assertSame('Phalguna', $amavasyaDay['Month_Purnimanta_En'] ?? null);
        $this->assertFalse((bool) ($amavasyaDay['Is_Kshaya'] ?? true));

        $this->assertSame('Phalguna', $nextDay['Month_Amanta_En'] ?? null);
        $this->assertSame('Phalguna', $nextDay['Month_Purnimanta_En'] ?? null);
        $this->assertFalse((bool) ($nextDay['Is_Kshaya'] ?? true));
    }

    public function test_purnimanta_pausha_kshaya_boundary_matches_expected_2124_sequence(): void
    {
        config(['panchang.defaults.locale' => 'en']);

        $january = Panchang::getMonthCalendar(
            2124,
            1,
            23.2472446,
            69.668339,
            'Asia/Kolkata',
            0.0,
            ['festival_scope' => 'month'],
            null,
            'purnimanta'
        );

        $february = Panchang::getMonthCalendar(
            2124,
            2,
            23.2472446,
            69.668339,
            'Asia/Kolkata',
            0.0,
            ['festival_scope' => 'month'],
            null,
            'purnimanta'
        );

        $purnimaDay = $january['2124-01-31']['hindu_calendar'] ?? [];
        $nextDay = $february['2124-02-01']['hindu_calendar'] ?? [];

        $this->assertSame('Magha', $purnimaDay['Month_Purnimanta_En'] ?? null);
        $this->assertSame('Magha', $purnimaDay['Month_Amanta_En'] ?? null);
        $this->assertFalse((bool) ($purnimaDay['Is_Kshaya'] ?? true));

        $this->assertSame('Phalguna', $nextDay['Month_Purnimanta_En'] ?? null);
        $this->assertSame('Magha', $nextDay['Month_Amanta_En'] ?? null);
        $this->assertFalse((bool) ($nextDay['Is_Kshaya'] ?? true));
    }

    public function test_adhika_jyeshtha_2026_boundaries_match_expected_sequence(): void
    {
        config(['panchang.defaults.locale' => 'en']);

        $may = Panchang::getMonthCalendar(
            2026,
            5,
            23.2472446,
            69.668339,
            'Asia/Kolkata',
            0.0,
            ['festival_scope' => 'month']
        );

        $juneAmanta = Panchang::getMonthCalendar(
            2026,
            6,
            23.2472446,
            69.668339,
            'Asia/Kolkata',
            0.0,
            ['festival_scope' => 'month']
        );

        $junePurnimanta = Panchang::getMonthCalendar(
            2026,
            6,
            23.2472446,
            69.668339,
            'Asia/Kolkata',
            0.0,
            ['festival_scope' => 'month'],
            null,
            'purnimanta'
        );

        $before = $may['2026-05-16']['hindu_calendar'] ?? [];
        $start = $may['2026-05-17']['hindu_calendar'] ?? [];
        $purnima = $may['2026-05-31']['hindu_calendar'] ?? [];
        $end = $juneAmanta['2026-06-15']['hindu_calendar'] ?? [];
        $next = $juneAmanta['2026-06-16']['hindu_calendar'] ?? [];
        $purniEnd = $junePurnimanta['2026-06-15']['hindu_calendar'] ?? [];
        $purniNext = $junePurnimanta['2026-06-16']['hindu_calendar'] ?? [];

        $this->assertSame('Vaishakha', $before['Month_Amanta_En'] ?? null);
        $this->assertSame('Jyeshtha', $before['Month_Purnimanta_En'] ?? null);
        $this->assertFalse((bool) ($before['Is_Adhika'] ?? true));

        $this->assertSame('Jyeshtha (Intercalary)', $start['Month_Amanta_En'] ?? null);
        $this->assertSame('Jyeshtha (Intercalary)', $start['Month_Purnimanta_En'] ?? null);
        $this->assertTrue((bool) ($start['Is_Adhika'] ?? false));

        $this->assertSame('Jyeshtha (Intercalary)', $purnima['Month_Amanta_En'] ?? null);
        $this->assertSame('Jyeshtha (Intercalary)', $purnima['Month_Purnimanta_En'] ?? null);
        $this->assertTrue((bool) ($purnima['Is_Adhika'] ?? false));

        $this->assertSame('Jyeshtha (Intercalary)', $end['Month_Amanta_En'] ?? null);
        $this->assertSame('Ashadha', $end['Month_Purnimanta_En'] ?? null);
        $this->assertTrue((bool) ($end['Is_Adhika'] ?? false));

        $this->assertSame('Jyeshtha', $next['Month_Amanta_En'] ?? null);
        $this->assertSame('Jyeshtha', $next['Month_Purnimanta_En'] ?? null);
        $this->assertFalse((bool) ($next['Is_Adhika'] ?? true));

        $this->assertSame('Ashadha', $purniEnd['Month_Purnimanta_En'] ?? null);
        $this->assertTrue((bool) ($purniEnd['Is_Adhika'] ?? false));
        $this->assertSame('Jyeshtha', $purniNext['Month_Purnimanta_En'] ?? null);
        $this->assertFalse((bool) ($purniNext['Is_Adhika'] ?? true));
    }

    #[Override]
    protected function getPackageProviders($app)
    {
        return [PanchangServiceProvider::class];
    }
}
