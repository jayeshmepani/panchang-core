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

    public function test_grahalaghava_and_makaranda_are_self_contained_projection_models(): void
    {
        $date = CarbonImmutable::create(2026, 8, 3, 12, 0, 0, 'Asia/Kolkata');

        $classical = $this->service->getBrihaspatiSamvatsaraInfo(
            $date,
            BrihaspatiSamvatsaraService::MODEL_CLASSICAL_SS
        );
        $grahalaghava = $this->service->getBrihaspatiSamvatsaraInfo(
            $date,
            BrihaspatiSamvatsaraService::MODEL_GRAHALAGHAVA
        );
        $makaranda = $this->service->getBrihaspatiSamvatsaraInfo(
            $date,
            BrihaspatiSamvatsaraService::MODEL_MAKARANDA
        );

        // No AstronomyService required for historical mean models.
        $this->assertSame(BrihaspatiSamvatsaraService::MODEL_GRAHALAGHAVA, $grahalaghava['model']);
        $this->assertSame(BrihaspatiSamvatsaraService::MODEL_MAKARANDA, $makaranda['model']);
        $this->assertSame(
            BrihaspatiSamvatsaraService::MODEL_CLASSICAL_SS,
            $grahalaghava['name_phase_source']
        );
        $this->assertSame(
            BrihaspatiSamvatsaraService::MODEL_CLASSICAL_SS,
            $makaranda['name_phase_source']
        );

        // Projection retains classical 60-name phase at this civil date.
        $this->assertSame($classical['name'], $grahalaghava['name']);
        $this->assertSame($classical['name'], $makaranda['name']);

        // Timing basis differs from pure classical mean-motion.
        $this->assertNotSame($classical['start_jd'], $grahalaghava['start_jd']);
        $this->assertNotSame($classical['start_jd'], $makaranda['start_jd']);
        $this->assertArrayHasKey('start_rashi_index', $grahalaghava);
        $this->assertArrayHasKey('start_rashi_index', $makaranda);
        $this->assertStringContainsString('grahalaghava', (string) $grahalaghava['timing_basis']);
        $this->assertStringContainsString('makaranda', (string) $makaranda['timing_basis']);
    }

    public function test_compare_models_works_without_astronomy_for_historical_means(): void
    {
        $date = CarbonImmutable::create(2026, 8, 3, 12, 0, 0, 'Asia/Kolkata');
        $comparison = $this->service->compareModels($date);

        $this->assertArrayHasKey('classical_ss', $comparison['current_state']);
        $this->assertArrayHasKey('grahalaghava', $comparison['current_state']);
        $this->assertArrayHasKey('makaranda', $comparison['current_state']);
        $this->assertArrayNotHasKey('modern_ephemeris', $comparison['current_state']);

        $same = $comparison['same_name_timing'];
        $this->assertArrayHasKey('grahalaghava', $same);
        $this->assertArrayHasKey('makaranda', $same);
        $this->assertArrayNotHasKey('modern', $same);
        $this->assertNull($same['difference']);
        $this->assertArrayHasKey(
            BrihaspatiSamvatsaraService::MODEL_GRAHALAGHAVA,
            $same['differences_from_classical']
        );
        $this->assertArrayHasKey(
            BrihaspatiSamvatsaraService::MODEL_MAKARANDA,
            $same['differences_from_classical']
        );
    }

    public function test_supported_models_catalogue_includes_four_models(): void
    {
        $models = BrihaspatiSamvatsaraService::supportedModels();
        $this->assertCount(4, $models);
        $this->assertArrayHasKey(BrihaspatiSamvatsaraService::MODEL_CLASSICAL_SS, $models);
        $this->assertArrayHasKey(BrihaspatiSamvatsaraService::MODEL_GRAHALAGHAVA, $models);
        $this->assertArrayHasKey(BrihaspatiSamvatsaraService::MODEL_MAKARANDA, $models);
        $this->assertArrayHasKey(BrihaspatiSamvatsaraService::MODEL_MODERN_EPHEMERIS, $models);
        $this->assertFalse($models[BrihaspatiSamvatsaraService::MODEL_GRAHALAGHAVA]['requires_astronomy_service']);
        $this->assertFalse($models[BrihaspatiSamvatsaraService::MODEL_MAKARANDA]['requires_astronomy_service']);
        $this->assertTrue($models[BrihaspatiSamvatsaraService::MODEL_MODERN_EPHEMERIS]['requires_astronomy_service']);
    }

    public function test_package_default_is_canonical_classical_ss(): void
    {
        $this->assertSame(
            BrihaspatiSamvatsaraService::MODEL_CLASSICAL_SS,
            BrihaspatiSamvatsaraService::DEFAULT_MODEL
        );
        $this->assertSame(
            BrihaspatiSamvatsaraService::MODEL_CLASSICAL_SS,
            BrihaspatiSamvatsaraService::defaultModel()
        );

        $models = BrihaspatiSamvatsaraService::supportedModels();
        $this->assertSame(
            BrihaspatiSamvatsaraService::STATUS_CANONICAL,
            $models[BrihaspatiSamvatsaraService::MODEL_CLASSICAL_SS]['status']
        );
        $this->assertSame(
            BrihaspatiSamvatsaraService::STATUS_EXPERIMENTAL,
            $models[BrihaspatiSamvatsaraService::MODEL_MAKARANDA]['status']
        );
        $this->assertSame(
            BrihaspatiSamvatsaraService::STATUS_EXPERIMENTAL,
            $models[BrihaspatiSamvatsaraService::MODEL_GRAHALAGHAVA]['status']
        );
        $this->assertSame(
            BrihaspatiSamvatsaraService::STATUS_ASTRONOMICAL_COMPARATOR,
            $models[BrihaspatiSamvatsaraService::MODEL_MODERN_EPHEMERIS]['status']
        );
        $this->assertSame(
            BrihaspatiSamvatsaraService::FAMILY_TRADITIONAL_BARHASPATYA,
            $models[BrihaspatiSamvatsaraService::MODEL_CLASSICAL_SS]['model_family']
        );
        $this->assertSame(
            BrihaspatiSamvatsaraService::FAMILY_TRADITIONAL_MEAN_JUPITER,
            $models[BrihaspatiSamvatsaraService::MODEL_MAKARANDA]['model_family']
        );
    }

    public function test_info_payload_includes_status_and_family_metadata(): void
    {
        $classical = $this->service->getBrihaspatiSamvatsaraInfo(
            CarbonImmutable::create(2026, 8, 3, 12, 0, 0, 'Asia/Kolkata')
        );
        $this->assertSame(BrihaspatiSamvatsaraService::MODEL_CLASSICAL_SS, $classical['model']);
        $this->assertSame(BrihaspatiSamvatsaraService::STATUS_CANONICAL, $classical['status']);
        $this->assertSame(
            BrihaspatiSamvatsaraService::FAMILY_TRADITIONAL_BARHASPATYA,
            $classical['model_family']
        );

        $makaranda = $this->service->getBrihaspatiSamvatsaraInfo(
            CarbonImmutable::create(2026, 8, 3, 12, 0, 0, 'Asia/Kolkata'),
            BrihaspatiSamvatsaraService::MODEL_MAKARANDA
        );
        $this->assertSame(BrihaspatiSamvatsaraService::STATUS_EXPERIMENTAL, $makaranda['status']);
        $this->assertSame(
            BrihaspatiSamvatsaraService::FAMILY_TRADITIONAL_MEAN_JUPITER,
            $makaranda['model_family']
        );
    }

    public function test_build_samvatsara_fields_expose_default_model_status(): void
    {
        $fields = $this->engine->buildSamvatsaraCalendarFields(
            2083,
            1948,
            2082,
            CarbonImmutable::create(2026, 8, 3)
        );

        $this->assertSame(
            BrihaspatiSamvatsaraService::MODEL_CLASSICAL_SS,
            $fields['Samvatsara_Brihaspati_Model']
        );
        $this->assertSame(
            BrihaspatiSamvatsaraService::STATUS_CANONICAL,
            $fields['Samvatsara_Brihaspati_Model_Status']
        );
        $this->assertSame(
            BrihaspatiSamvatsaraService::MODEL_CLASSICAL_SS,
            $fields['Samvatsara_Systems']['north_brihaspati']['model']
        );
    }
}
