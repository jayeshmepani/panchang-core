<?php

namespace JayeshMepani\PanchangCore\Tests;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Astronomy\BrihaspatiSamvatsaraService;
use JayeshMepani\PanchangCore\Core\Enums\Samvatsara;
use JayeshMepani\PanchangCore\Panchanga\PanchangaEngine;
use PHPUnit\Framework\TestCase;
use ValueError;

final class BrihaspatiSamvatsaraTest extends TestCase
{
    private BrihaspatiSamvatsaraService $service;

    private PanchangaEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BrihaspatiSamvatsaraService;
        $this->engine = new PanchangaEngine;
    }

    public function test_brihaspati_samvatsara_transitions_progress_accurately(): void
    {
        // 2024-04-28 (Pingala) -> 2024-04-30 (Kalayukti)
        $this->assertSame('Pingala', $this->service->getSamvatsaraBrihaspati(CarbonImmutable::create(2024, 4, 28)));
        $this->assertSame('Kalayukti', $this->service->getSamvatsaraBrihaspati(CarbonImmutable::create(2024, 4, 30)));

        // 2025-04-24 (Kalayukti) -> 2025-04-26 (Siddharthi)
        $this->assertSame('Kalayukti', $this->service->getSamvatsaraBrihaspati(CarbonImmutable::create(2025, 4, 24)));
        $this->assertSame('Siddharthi', $this->service->getSamvatsaraBrihaspati(CarbonImmutable::create(2025, 4, 26)));

        // 2026-04-20 (Siddharthi) -> 2026-04-22 (Raudri)
        $this->assertSame('Siddharthi', $this->service->getSamvatsaraBrihaspati(CarbonImmutable::create(2026, 4, 20)));
        $this->assertSame('Raudri', $this->service->getSamvatsaraBrihaspati(CarbonImmutable::create(2026, 4, 22)));

        // 2027-04-16 (Raudri) -> 2027-04-18 (Durmati)
        $this->assertSame('Raudri', $this->service->getSamvatsaraBrihaspati(CarbonImmutable::create(2027, 4, 16)));
        $this->assertSame('Durmati', $this->service->getSamvatsaraBrihaspati(CarbonImmutable::create(2027, 4, 18)));

        // 2028-04-11 (Durmati) -> 2028-04-13 (Dundubhi)
        $this->assertSame('Durmati', $this->service->getSamvatsaraBrihaspati(CarbonImmutable::create(2028, 4, 11)));
        $this->assertSame('Dundubhi', $this->service->getSamvatsaraBrihaspati(CarbonImmutable::create(2028, 4, 13)));
    }

    public function test_build_samvatsara_calendar_fields_handles_null_date_safely(): void
    {
        $fields = $this->engine->buildSamvatsaraCalendarFields(2083, 1948, 2082, null);

        $this->assertNull($fields['Samvatsara_Brihaspati']);
        $this->assertSame('Siddharthi', $fields['Samvatsara_North_Display']);
        $this->assertArrayHasKey('north_brihaspati', $fields['Samvatsara_Systems']);
    }

    public function test_samvatsara_enum_from_year_validates_bounds(): void
    {
        $this->assertSame(Samvatsara::Prabhava, Samvatsara::fromYear(1));
        $this->assertSame(Samvatsara::Akshaya, Samvatsara::fromYear(60));

        $this->expectException(ValueError::class);
        Samvatsara::fromYear(61);
    }

    public function test_model_parameter_is_forwarded_to_service_info_lookup(): void
    {
        $classical = $this->service->getBrihaspatiSamvatsaraInfo(
            CarbonImmutable::create(2026, 8, 3, 12, 0, 0, 'Asia/Kolkata'),
            BrihaspatiSamvatsaraService::MODEL_CLASSICAL_SS
        );
        $this->assertSame(
            BrihaspatiSamvatsaraService::MODEL_CLASSICAL_SS,
            $classical['model']
        );
        $this->assertStringContainsString(
            'surya_siddhanta',
            (string) ($classical['variant'] ?? '')
        );

        // Engine wrapper must forward $model (regression for silent classical default).
        $this->assertSame(
            'Raudri',
            $this->engine->getSamvatsaraBrihaspati(
                CarbonImmutable::create(2026, 4, 22),
                BrihaspatiSamvatsaraService::MODEL_CLASSICAL_SS
            )
        );
    }
}
