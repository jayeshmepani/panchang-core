<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Tests;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Astronomy\EclipseService;
use JayeshMepani\PanchangCore\Astronomy\SunService;
use JayeshMepani\PanchangCore\Core\Localization;
use JayeshMepani\PanchangCore\Festivals\FestivalRuleEngine;
use JayeshMepani\PanchangCore\Festivals\FestivalService;
use JayeshMepani\PanchangCore\Panchanga\KalaNirnayaEngine;
use JayeshMepani\PanchangCore\Panchanga\Ritual\MahadikshaGuidance;
use JayeshMepani\PanchangCore\Panchanga\Vrata\EkadashiParanaCalculator;
use JayeshMepani\PanchangCore\Traits\CliBootstrap;
use JmeEph\FFI\JmeEphFFI;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class NirnayVerifiedRulesTest extends TestCase
{
    public function testVaishnavaEkadashiUsesFiftyFiveGhadiDashamiVedhaThreshold(): void
    {
        $engine = new KalaNirnayaEngine(23.2472446, 69.668339);

        $previousSunriseJd = 100.0;
        $sunriseJd = 101.0;
        $nextSunriseJd = 102.0;
        $sunsetJd = 101.6;
        $ekadashiStartJd = 100.95;
        $ekadashiEndJd = 101.6;
        $dvadashiStartJd = $ekadashiEndJd;

        $clean = $engine->determineEkadashi(
            $ekadashiStartJd,
            $ekadashiEndJd,
            100.90,
            $dvadashiStartJd,
            $sunriseJd,
            $nextSunriseJd,
            'Vaishnava',
            $previousSunriseJd,
            KalaNirnayaEngine::ARUNODAYA_GHATIKAS,
            $sunsetJd
        );

        $viddha = $engine->determineEkadashi(
            $ekadashiStartJd,
            $ekadashiEndJd,
            100.94,
            $dvadashiStartJd,
            $sunriseJd,
            $nextSunriseJd,
            'Vaishnava',
            $previousSunriseJd,
            KalaNirnayaEngine::ARUNODAYA_GHATIKAS,
            $sunsetJd
        );

        self::assertSame(55.0, $clean['dashami_vedha_threshold_ghatikas_from_previous_sunrise']);
        self::assertEqualsWithDelta(100.9333333333, $clean['dashami_vedha_threshold_jd'], 1e-10);
        self::assertSame('dynamic_ratrimana_5_ghati_before_local_sunrise', $clean['dashami_vedha_threshold_basis']);
        self::assertSame('Shuddha_Ekadashi', $clean['status']);
        self::assertFalse($clean['dashami_pierces_nirnay_vedha']);
        self::assertSame('Viddha_Ekadashi', $viddha['status']);
        self::assertTrue($viddha['dashami_pierces_nirnay_vedha']);
    }

    public function testSmartaEkadashiRejectsDashamiAtSunriseButToleratesArunodayaOnlyDashami(): void
    {
        $engine = new KalaNirnayaEngine(23.2472446, 69.668339);

        $sunriseJd = 101.0;
        $nextSunriseJd = 102.0;
        $ekadashiStartJd = 100.95;
        $ekadashiEndJd = 101.6;

        $sunriseDashami = $engine->determineEkadashi(
            $ekadashiStartJd,
            $ekadashiEndJd,
            101.01,
            $ekadashiEndJd,
            $sunriseJd,
            $nextSunriseJd,
            'Smarta',
            100.0
        );

        $arunodayaOnlyDashami = $engine->determineEkadashi(
            $ekadashiStartJd,
            $ekadashiEndJd,
            100.94,
            $ekadashiEndJd,
            $sunriseJd,
            $nextSunriseJd,
            'Smarta',
            100.0
        );

        self::assertSame('smarta_dashami_at_sunrise_rejected', $sunriseDashami['case_key']);
        self::assertSame('Tomorrow', $sunriseDashami['fasting_day']);
        self::assertSame('smarta_shuddha_arunodaya_dashami_tolerated', $arunodayaOnlyDashami['case_key']);
        self::assertSame('Today', $arunodayaOnlyDashami['fasting_day']);
    }

    public function testArunodayaLengthCanBeConfiguredWithinFourToFiveGhatiRange(): void
    {
        $engine = new KalaNirnayaEngine(23.2472446, 69.668339);

        $result = $engine->determineEkadashi(
            100.95,
            101.6,
            100.90,
            101.6,
            101.0,
            102.0,
            'Smarta',
            100.0,
            5.0,
            101.60
        );

        self::assertSame(5.0, $result['arunodaya_ghatikas']);
        self::assertEqualsWithDelta(96.0, $result['arunodaya_minutes'], 1e-10);
        self::assertEqualsWithDelta(100.9333333333, $result['arunodaya_jd'], 1e-10);
        self::assertSame('dynamic_ratrimana_ghati_before_local_sunrise', $result['arunodaya_basis']);
        self::assertEqualsWithDelta(19.2, $result['night_ghati_minutes'], 1e-10);
    }

    public function testSankrantiPunyaKaalUsesDynamicDayGhatisForDaytimeIngress(): void
    {
        $engine = new KalaNirnayaEngine(23.2472446, 69.668339);

        $result = $engine->calculatePunyaKaal(
            'Mesha',
            100.5,
            100.0,
            100.75,
            101.25,
            99.5
        );

        self::assertSame('dynamic_dinamana_30_ghati_day', $result['ghati_basis']);
        self::assertEqualsWithDelta(36.0, $result['dynamic_before_ghati_minutes'], 1e-10);
        self::assertEqualsWithDelta(36.0, $result['dynamic_after_ghati_minutes'], 1e-10);
        self::assertEqualsWithDelta(20.0, $result['duration_ghatikas'], 1e-10);
        self::assertEqualsWithDelta(100.25, $result['punya_kaal_start_jd'], 1e-10);
        self::assertEqualsWithDelta(100.75, $result['punya_kaal_end_jd'], 1e-10);
    }

    public function testSankrantiPunyaKaalUsesNightBeforeAndDayAfterForPreSunriseIngress(): void
    {
        $engine = new KalaNirnayaEngine(23.2472446, 69.668339);

        $result = $engine->calculatePunyaKaal(
            'Mesha',
            99.9,
            100.0,
            100.75,
            101.25,
            99.5
        );

        self::assertSame('dynamic_segmental_ghati_by_day_night', $result['ghati_basis']);
        self::assertEqualsWithDelta(24.0, $result['dynamic_before_ghati_minutes'], 1e-10);
        self::assertEqualsWithDelta(36.0, $result['dynamic_after_ghati_minutes'], 1e-10);
        self::assertEqualsWithDelta(99.7333333333, $result['punya_kaal_start_jd'], 1e-10);
        self::assertEqualsWithDelta(100.25, $result['punya_kaal_end_jd'], 1e-10);
    }

    public function testVerifiedParanaNakshatraPadaRestrictionsAreEncoded(): void
    {
        $reflection = new ReflectionClass(EkadashiParanaCalculator::class);
        $constant = $reflection->getReflectionConstant('NIRNAY_PARANA_RESTRICTED_NAKSHATRA_PADAS');

        self::assertNotFalse($constant);
        self::assertSame([
            'Anuradha' => [1],
            'Shravana' => [2, 3],
            'Revati' => [4],
        ], $constant->getValue());
    }

    public function testEclipseServiceResolvesInstalledLunarPenumbralFlagConstant(): void
    {
        $reflection = new ReflectionClass(EclipseService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('lunarPenumbralFlag');

        self::assertSame(
            JmeEphFFI::JME_ECLIPSE_LUNAR_PENUMBRAL,
            $method->invoke($service)
        );
    }

    public function testVisibleEclipseSutakUsesDynamicPraharBoundaries(): void
    {
        CliBootstrap::init(dirname(__DIR__));
        $service = CliBootstrap::makeEclipseService();
        $events = $service->getEclipsesForYear(2025, 23.2472446, 69.668339, 'Asia/Kolkata');

        $event = null;
        foreach ($events as $candidate) {
            if (($candidate['date'] ?? null) === '2025-09-07' && ($candidate['type'] ?? null) === 'Lunar') {
                $event = $candidate;
                break;
            }
        }

        self::assertIsArray($event, 'Expected visible 2025-09-07 lunar eclipse event.');
        self::assertTrue((bool) ($event['sutak']['applicable'] ?? false));

        $reflection = new ReflectionClass(EclipseService::class);
        $resolveAnchors = $reflection->getMethod('resolveSutakAnchors');

        $expectedAnchors = $resolveAnchors->invoke(
            $service,
            (float) $event['visibility']['window']['start_jd'],
            23.2472446,
            69.668339,
            'Asia/Kolkata',
            3
        );

        self::assertIsArray($expectedAnchors);
        self::assertEqualsWithDelta(
            $expectedAnchors['start_jd'],
            $event['sutak']['start_jd'] ?? null,
            1e-9,
            'Sutak start should align to dynamic prahara boundary.'
        );
        self::assertEqualsWithDelta(
            $expectedAnchors['relaxed_start_jd'],
            $event['sutak']['relaxed_start_jd'] ?? null,
            1e-9,
            'Relaxed sutak should begin one variable prahara before local eclipse contact.'
        );
    }

    public function testLunarGrastodayaUsesFourPraharSutakRule(): void
    {
        $reflection = new ReflectionClass(EclipseService::class);
        $service = CliBootstrap::makeEclipseService();
        $method = $reflection->getMethod('resolveSutakPraharCount');

        self::assertSame(4, $method->invoke($service, 'Lunar', ['grast_uday' => true]));
        self::assertSame(3, $method->invoke($service, 'Lunar', ['grast_uday' => false]));
        self::assertSame(4, $method->invoke($service, 'Solar', ['grast_uday' => false]));
    }

    public function testChudamaniYogaMatchesWeekdayRule(): void
    {
        CliBootstrap::init(dirname(__DIR__));
        $service = CliBootstrap::makeEclipseService();
        $reflection = new ReflectionClass(EclipseService::class);
        $carbonToJd = $reflection->getMethod('carbonToJd');
        $isChudamaniYoga = $reflection->getMethod('isChudamaniYoga');

        $mondayLunarJd = $carbonToJd->invoke($service, CarbonImmutable::parse('2025-09-08 00:30:00', 'Asia/Kolkata'));
        $sundaySolarJd = $carbonToJd->invoke($service, CarbonImmutable::parse('2026-02-15 12:00:00', 'Asia/Kolkata'));
        $tuesdayLunarJd = $carbonToJd->invoke($service, CarbonImmutable::parse('2025-09-09 00:30:00', 'Asia/Kolkata'));

        self::assertTrue($isChudamaniYoga->invoke($service, 'Lunar', $mondayLunarJd, 'Asia/Kolkata'));
        self::assertTrue($isChudamaniYoga->invoke($service, 'Solar', $sundaySolarJd, 'Asia/Kolkata'));
        self::assertFalse($isChudamaniYoga->invoke($service, 'Lunar', $tuesdayLunarJd, 'Asia/Kolkata'));
    }

    public function testSolarGrastastaPostEclipseRitualExtendsToNextSunrise(): void
    {
        CliBootstrap::init(dirname(__DIR__));
        $service = CliBootstrap::makeEclipseService();
        $reflection = new ReflectionClass(EclipseService::class);
        $carbonToJd = $reflection->getMethod('carbonToJd');
        $nextSunriseAfter = $reflection->getMethod('nextSunriseAfter');
        $buildPostEclipseRitualPayload = $reflection->getMethod('buildPostEclipseRitualPayload');

        $visibleEndJd = $carbonToJd->invoke($service, CarbonImmutable::parse('2022-10-25 18:18:00', 'Asia/Kolkata'));
        $expectedNextSunriseJd = $nextSunriseAfter->invoke($service, $visibleEndJd, 23.2472446, 69.668339, 'Asia/Kolkata');
        $payload = $buildPostEclipseRitualPayload->invoke(
            $service,
            $visibleEndJd,
            'Solar',
            23.2472446,
            69.668339,
            'Asia/Kolkata',
            true,
            ['grast_ast' => true]
        );

        self::assertTrue($payload['applicable']);
        self::assertSame('after_next_sunrise_bathe_see_pure_sun_disc_then_eat', $payload['ritual_completion_requirement_key']);
        self::assertArrayNotHasKey('starts_after_jd', $payload);
        self::assertEqualsWithDelta($expectedNextSunriseJd, $payload['snana_homa_after_jd'], 1e-9);
        self::assertEqualsWithDelta($expectedNextSunriseJd, $payload['food_allowed_after_jd'], 1e-9);
        self::assertGreaterThan($visibleEndJd, $payload['food_allowed_after_jd']);
    }

    public function testLunarGrastastaPostEclipseRitualWaitsForMoonriseAgain(): void
    {
        CliBootstrap::init(dirname(__DIR__));
        $service = CliBootstrap::makeEclipseService();
        $reflection = new ReflectionClass(EclipseService::class);
        $carbonToJd = $reflection->getMethod('carbonToJd');
        $nextMoonriseAfter = $reflection->getMethod('nextMoonriseAfter');
        $buildPostEclipseRitualPayload = $reflection->getMethod('buildPostEclipseRitualPayload');

        $visibleEndJd = $carbonToJd->invoke($service, CarbonImmutable::parse('2022-11-08 18:18:00', 'Asia/Kolkata'));
        $mokshaJd = $carbonToJd->invoke($service, CarbonImmutable::parse('2022-11-08 19:40:00', 'Asia/Kolkata'));
        $expectedNextMoonriseJd = $nextMoonriseAfter->invoke($service, $visibleEndJd, 23.2472446, 69.668339, 'Asia/Kolkata');
        $payload = $buildPostEclipseRitualPayload->invoke(
            $service,
            $visibleEndJd,
            'Lunar',
            23.2472446,
            69.668339,
            'Asia/Kolkata',
            true,
            ['grast_ast' => true, 'type' => 'grast_ast'],
            $mokshaJd
        );

        self::assertTrue($payload['applicable']);
        self::assertSame('after_moonrise_again_then_eat', $payload['ritual_completion_requirement_key']);
        self::assertSame('next_local_moonrise', $payload['food_allowed_after_key']);
        self::assertArrayNotHasKey('starts_after_jd', $payload);
        self::assertEqualsWithDelta($mokshaJd, $payload['snana_homa_after_jd'], 1e-9);
        self::assertEqualsWithDelta($mokshaJd, $payload['astronomical_moksha_jd'], 1e-9);
        self::assertEqualsWithDelta($expectedNextMoonriseJd, $payload['food_allowed_after_jd'], 1e-9);
        self::assertGreaterThan($visibleEndJd, $payload['food_allowed_after_jd']);
        self::assertGreaterThan($visibleEndJd, $payload['astronomical_moksha_jd'] - 1e-6);
    }

    public function testAugust2035PartialLunarIsGrastastaNotGrastodaya(): void
    {
        CliBootstrap::init(dirname(__DIR__));
        $service = CliBootstrap::makeEclipseService();
        $events = $service->getEclipsesForDateRange(
            CarbonImmutable::parse('2035-08-18', 'Asia/Kolkata'),
            CarbonImmutable::parse('2035-08-20', 'Asia/Kolkata'),
            23.2472446,
            69.668339,
            'Asia/Kolkata'
        );

        $event = null;
        foreach ($events as $candidate) {
            if (($candidate['date'] ?? null) === '2035-08-19' && ($candidate['type'] ?? null) === 'Lunar') {
                $event = $candidate;
                break;
            }
        }

        self::assertIsArray($event, 'Expected 2035-08-19 lunar eclipse for Bhuj.');
        self::assertTrue((bool) ($event['visibility']['visible'] ?? false));
        self::assertTrue((bool) ($event['magnitudes']['meets_ritual_minimum'] ?? false));
        self::assertFalse((bool) ($event['ritual_boundary']['grast_uday'] ?? true), 'Must not classify as grastodaya');
        self::assertTrue((bool) ($event['ritual_boundary']['grast_ast'] ?? false), 'Must classify as grastasta (moonset during partial)');
        self::assertSame('grast_ast', $event['ritual_boundary']['type'] ?? null);
        self::assertSame('lunar_grastasta', $event['ritual_boundary']['instruction_key'] ?? null);
        self::assertSame(3, $event['sutak']['standard_prahars_before'] ?? null);
        self::assertFalse((bool) ($event['ritual_boundary']['is_chudamani_yoga'] ?? true));

        $partialEnd = $event['contacts']['partial_end_jd']['jd'] ?? null;
        $windowEnd = $event['visibility']['window']['end_jd'] ?? null;
        self::assertIsFloat($partialEnd);
        self::assertIsFloat($windowEnd);
        self::assertLessThan($partialEnd, $windowEnd, 'Local visibility ends at moonset before astronomical moksha');
        self::assertEqualsWithDelta($partialEnd, $event['sutak']['end_jd'] ?? null, 1e-6);
        self::assertEqualsWithDelta($partialEnd, $event['post_eclipse_ritual']['astronomical_moksha_jd'] ?? null, 1e-6);
        self::assertEqualsWithDelta($partialEnd, $event['post_eclipse_ritual']['snana_homa_after_jd'] ?? null, 1e-6);
        self::assertSame('next_local_moonrise', $event['post_eclipse_ritual']['food_allowed_after_key'] ?? null);
        self::assertArrayNotHasKey('starts_after', $event['post_eclipse_ritual']);
        self::assertGreaterThan(
            $windowEnd,
            $event['post_eclipse_ritual']['food_allowed_after_jd'] ?? 0.0
        );

        // One horizon definition: moonset, window end, penumbral end, punya end, local_visible_end must match.
        $moonsetJd = $event['ritual_boundary']['moonset_jd'] ?? null;
        $punyaEnd = $event['punya_kaal']['end_jd'] ?? null;
        $localVisibleEnd = $event['post_eclipse_ritual']['local_visible_end_jd'] ?? null;
        $penumbralEnd = $event['visibility']['penumbral_window']['end_jd'] ?? null;
        self::assertIsFloat($moonsetJd);
        self::assertEqualsWithDelta($moonsetJd, $windowEnd, 1e-9, 'visibility.window.end must equal ritual moonset');
        self::assertEqualsWithDelta($moonsetJd, $punyaEnd, 1e-9, 'punya_kaal.end must equal ritual moonset');
        self::assertEqualsWithDelta($moonsetJd, $localVisibleEnd, 1e-9, 'local_visible_end must equal ritual moonset');
        self::assertEqualsWithDelta($moonsetJd, $penumbralEnd, 1e-9, 'penumbral_window.end must equal literal moonset');
        self::assertSame(
            'literally_visible_geometric_above_horizon',
            $event['literally_visible']['meaning_key'] ?? null
        );
        self::assertArrayHasKey('potentially_visible_to_unaided_eye', $event['literally_visible']);
        self::assertArrayHasKey('ritually_visible_by_horizon_and_magnitude_rules', $event['visibility']);
        self::assertArrayNotHasKey('unaided_eye_ritual_visible', $event['visibility']);
        self::assertSame(
            'snana_homa_after_moksha_food_after_next_moonrise',
            $event['sutak']['end_scope_key'] ?? null
        );

        // Separated concepts: literally_visible vs ritual sparsha/madhya/moksha + sutak pair.
        self::assertTrue((bool) ($event['literally_visible']['in_sky'] ?? false));
        self::assertEqualsWithDelta($windowEnd, $event['literally_visible']['window']['end_jd'] ?? null, 1e-9);
        self::assertTrue((bool) ($event['ritual']['applicable'] ?? false));
        self::assertEqualsWithDelta(
            $event['contacts']['partial_begin_jd']['jd'] ?? null,
            $event['ritual']['sparsha']['jd'] ?? null,
            1e-9
        );
        self::assertEqualsWithDelta($event['jd'] ?? null, $event['ritual']['madhya']['jd'] ?? null, 1e-9);
        self::assertSame('ritual_madhya', $event['ritual']['madhya']['meaning_key'] ?? null);
        self::assertEqualsWithDelta($partialEnd, $event['ritual']['moksha']['jd'] ?? null, 1e-9);
        self::assertEqualsWithDelta($event['sutak']['start_jd'] ?? null, $event['ritual']['sutak']['start_jd'] ?? null, 1e-9);
        self::assertEqualsWithDelta($event['sutak']['relaxed_start_jd'] ?? null, $event['ritual']['relaxed_sutak']['start_jd'] ?? null, 1e-9);
        self::assertEqualsWithDelta($partialEnd, $event['ritual']['sutak']['end_jd'] ?? null, 1e-9);
        self::assertEqualsWithDelta($partialEnd, $event['ritual']['relaxed_sutak']['end_jd'] ?? null, 1e-9);
        // Literally visible end (moonset) is not the same as moksha.
        self::assertLessThan($event['ritual']['moksha']['jd'], $event['literally_visible']['window']['end_jd']);

        // Horizon event through eclipse must be exported (grastasta ⇒ moonset).
        self::assertTrue((bool) ($event['horizon_events']['has_rise_or_set_through_eclipse'] ?? false));
        self::assertTrue((bool) ($event['horizon_events']['grast_ast'] ?? false));
        self::assertNotNull($event['horizon_events']['moonset'] ?? null);
        self::assertSame('moonset', $event['horizon_events']['moonset']['type'] ?? null);
        self::assertSame('grast_ast', $event['horizon_events']['moonset']['role'] ?? null);
        self::assertEqualsWithDelta(
            $moonsetJd,
            $event['horizon_events']['moonset']['jd'] ?? null,
            1e-9
        );
        self::assertNull($event['horizon_events']['moonrise']);
        self::assertNull($event['horizon_events']['sunrise']);
        self::assertNull($event['horizon_events']['sunset']);
    }

    public function testGujaratiParanaNakshatraRestrictionsAreMonthPakshaSpecific(): void
    {
        $reflection = new ReflectionClass(EkadashiParanaCalculator::class);
        $calculator = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('activeRestrictedNakshatraPadas');

        self::assertSame(['Anuradha' => [1]], $method->invoke($calculator, 'Ashadha', 'Shukla'));
        self::assertSame(['Shravana' => [2, 3]], $method->invoke($calculator, 'Bhadrapada', 'Shukla'));
        self::assertSame(['Revati' => [4]], $method->invoke($calculator, 'Kartika', 'Shukla'));
        self::assertSame([], $method->invoke($calculator, 'Ashadha', 'Krishna'));
        self::assertSame([], $method->invoke($calculator, 'Vaishakha', 'Shukla'));
    }

    public function testEkadashiParanaBasisIsClassifiedForTithyavasaraAndHarivasara(): void
    {
        $reflection = new ReflectionClass(EkadashiParanaCalculator::class);
        $calculator = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('classifyParanaBasis');

        self::assertSame([
            'basis_key' => 'tithyavasara',
            'basis_label' => 'Tithyavasara',
            'has_nakshatra_restrictions' => false,
        ], $method->invoke($calculator, [], []));

        self::assertSame([
            'basis_key' => 'harivasara_nakshatra_restricted',
            'basis_label' => 'Harivasara',
            'has_nakshatra_restrictions' => true,
        ], $method->invoke($calculator, ['Anuradha' => [1]], [['nakshatra' => 'Anuradha', 'pada' => 1]]));
    }

    public function testSatsangiEkadashiFastingGuidanceIsLocalizedAndPrabodhiniAware(): void
    {
        $reflection = new ReflectionClass(EkadashiParanaCalculator::class);
        $calculator = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('buildFastingGuidance');

        $standard = $method->invoke($calculator, 11, 'Ashadha', 'Shukla');
        self::assertSame('satsangi_jeevan_ekadashi', $standard['profile']);
        self::assertSame([
            'satsangi_ekadashi_standard_fast_guidance',
            'satsangi_ekadashi_unable_allowance_guidance',
        ], $standard['guidance_keys']);
        self::assertStringContainsString('Satsangi Jeevan Ekadashi guidance', $standard['guidance'][0]);

        $prabodhini = $method->invoke($calculator, 11, 'Kartika', 'Shukla');
        self::assertContains('satsangi_prabodhini_strict_fast_guidance', $prabodhini['guidance_keys']);
        self::assertContains('Satsangi Jeevan 3.32.160-175', $prabodhini['source_refs']);
    }

    public function testEkadashiParanaDaytimePreferenceUsesFirstSixGhatisWhenDvadashiRunsPastMadhyahna(): void
    {
        $reflection = new ReflectionClass(EkadashiParanaCalculator::class);
        $calculator = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('buildDaytimePreferenceRule');

        $preferred = $method->invoke($calculator, 100.0, 100.7, 100.0, 100.25, 0.60);
        self::assertSame('pratah_kala_first_six_ghatis', $preferred['rule_key']);
        self::assertTrue($preferred['applies']);
        self::assertEqualsWithDelta(100.12, $preferred['preferred_end_jd'], 1e-12);
        self::assertEqualsWithDelta(28.8, $preferred['dynamic_ghati_minutes'], 1e-12);
        self::assertSame('dynamic_dinamana_midpoint', $preferred['madhyahna_basis']);
        self::assertSame('dynamic_dinamana_30_ghati_day', $preferred['preferred_duration_basis']);

        $notPreferred = $method->invoke($calculator, 100.0, 100.2, 100.0, 100.25, 0.60);
        self::assertSame('standard_dvadashi_parana', $notPreferred['rule_key']);
        self::assertFalse($notPreferred['applies']);
        self::assertNull($notPreferred['preferred_end_jd']);
    }

    public function testEkadashiParanaResolutionEnforcesSixGhatiWindowWhenApplicable(): void
    {
        $reflection = new ReflectionClass(EkadashiParanaCalculator::class);
        $calculator = $this->paranaCalculatorForWindowResolution();
        $method = $reflection->getMethod('resolveParanaWindows');

        $resolved = $method->invoke(
            $calculator,
            100.025,
            100.7,
            [],
            100.1,
            'Asia/Kolkata'
        );

        self::assertSame([], $resolved['restricted_windows']);
        self::assertCount(1, $resolved['allowed_windows']);
        self::assertEqualsWithDelta(100.025, $resolved['allowed_windows'][0]['start_jd'], 1e-12);
        self::assertEqualsWithDelta(100.1, $resolved['allowed_windows'][0]['end_jd'], 1e-12);
    }

    public function testEkadashiParanaResolutionIgnoresRestrictionThatStartsAfterParanaOpens(): void
    {
        $reflection = new ReflectionClass(EkadashiParanaCalculator::class);
        $calculator = $this->paranaCalculatorForWindowResolution();
        $method = $reflection->getMethod('resolveParanaWindows');

        $resolved = $method->invoke(
            $calculator,
            100.025,
            100.2,
            [[
                'nakshatra' => 'Revati',
                'pada' => 4,
                'start_jd' => 100.15,
                'end_jd' => 100.18,
            ]],
            null,
            'Asia/Kolkata'
        );

        self::assertSame([], $resolved['restricted_windows']);
        self::assertCount(1, $resolved['allowed_windows']);
        self::assertEqualsWithDelta(100.025, $resolved['allowed_windows'][0]['start_jd'], 1e-12);
        self::assertEqualsWithDelta(100.2, $resolved['allowed_windows'][0]['end_jd'], 1e-12);
    }

    public function testEkadashiParanaResolutionReopensDvadashiIfActiveRestrictionConsumesMorningCap(): void
    {
        $reflection = new ReflectionClass(EkadashiParanaCalculator::class);
        $calculator = $this->paranaCalculatorForWindowResolution();
        $method = $reflection->getMethod('resolveParanaWindows');

        $resolved = $method->invoke(
            $calculator,
            100.025,
            100.3,
            [[
                'nakshatra' => 'Revati',
                'pada' => 4,
                'start_jd' => 100.0,
                'end_jd' => 100.2,
            ]],
            100.1,
            'Asia/Kolkata'
        );

        self::assertCount(1, $resolved['restricted_windows']);
        self::assertCount(1, $resolved['allowed_windows']);
        self::assertEqualsWithDelta(100.2, $resolved['allowed_windows'][0]['start_jd'], 1e-12);
        self::assertEqualsWithDelta(100.3, $resolved['allowed_windows'][0]['end_jd'], 1e-12);
    }

    public function testFestivalResolverUsesFullPradoshaWindowInsteadOfSinglePoint(): void
    {
        $engine = new FestivalRuleEngine;
        $date = CarbonImmutable::parse('2026-03-02');
        $today = $this->festivalSnapshot(15, 'Shukla', 100.25, 100.75, 101.25, 100.760, 100.780, 'Magha');
        $tomorrow = $this->festivalSnapshot(1, 'Krishna', 101.25, 101.75, 102.25, 101.250, 102.250, 'Purva Phalguni');

        $resolved = $engine->resolveMajorFestival('Holika Dahan', [
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'karmakala_type' => 'pradosha',
            'strict_karmakala' => true,
        ], $date, $today, $tomorrow);

        self::assertNotNull($resolved);
        self::assertSame('2026-03-02', $resolved['observance_date']);
        self::assertTrue($resolved['tithi_at_karmakala_today']);
        self::assertGreaterThan(0, $resolved['tithi_coverage_seconds_today']);
    }

    public function testChandraDarshanaRejectsDayWithoutMoonAboveHorizonAfterSunset(): void
    {
        $engine = new FestivalRuleEngine;
        $date = CarbonImmutable::parse('2026-06-15');
        $today = $this->festivalSnapshot(1, 'Shukla', 100.25, 100.75, 101.25, 100.20, 100.50, 'Mrigashira', 100.70);
        $tomorrow = $this->festivalSnapshot(2, 'Shukla', 101.25, 101.75, 102.25, 100.50, 101.60, 'Ardra', 101.70);

        $resolved = $engine->resolveMajorFestival('Chandra Darshana', FestivalService::FESTIVALS['Chandra Darshana'], $date, $today, $tomorrow);

        self::assertNull($resolved);
    }

    public function testAdhikaChandraDarshanaSkipsKshayaAmavasyaHostDay(): void
    {
        $engine = new FestivalRuleEngine;
        $date = CarbonImmutable::parse('2026-06-15');
        $today = $this->festivalSnapshot(30, 'Krishna', 100.25, 100.75, 101.25, 100.30, 100.55, 'Mrigashira', 100.70);
        $tomorrow = $this->festivalSnapshot(1, 'Shukla', 101.25, 101.75, 102.25, 100.55, 101.10, 'Ardra', 101.70);

        $resolved = $engine->resolveMajorFestival(
            'Adhika Chandra Darshana',
            FestivalService::FESTIVALS['Adhika Chandra Darshana'],
            $date,
            $today,
            $tomorrow,
        );

        self::assertNull($resolved);
    }

    public function testChandraDarshanaUsesClassicalSthulaMetadata(): void
    {
        self::assertSame(1, FestivalService::FESTIVALS['Chandra Darshana']['tithi']);
        self::assertSame('chandra_darshana_visibility', FestivalService::FESTIVALS['Chandra Darshana']['karmakala_type']);
        self::assertTrue(FestivalService::FESTIVALS['Chandra Darshana']['chandra_darshana_visibility']);
        self::assertSame('source_sensitive_monthly_chandra_darshana_first_crescent', FestivalService::FESTIVALS['Chandra Darshana']['chandra_darshana_visibility_model']);
        self::assertSame('application_definition_first_visible_crescent', FestivalService::FESTIVALS['Chandra Darshana']['chandra_darshana_visibility_basis']);
        self::assertArrayNotHasKey('chandra_darshana_sthula_muhurta_threshold', FestivalService::FESTIVALS['Chandra Darshana']);
    }

    public function testGujaratiFestivalRegistryCorrectionsAreEncoded(): void
    {
        self::assertSame('sangava', FestivalService::FESTIVALS['Kali Chaudas (Naraka Chaturdashi)']['karmakala_type']);
        self::assertArrayHasKey('Naraka Chaturdashi Abhyanga Snan', FestivalService::FESTIVALS);
        self::assertSame('moonrise', FestivalService::FESTIVALS['Naraka Chaturdashi Abhyanga Snan']['karmakala_type']);
        self::assertTrue(FestivalService::FESTIVALS['Naraka Chaturdashi Abhyanga Snan']['location_sensitive']);
        self::assertTrue(FestivalService::FESTIVALS['Naraka Chaturdashi Abhyanga Snan']['naraka_chaturdashi_abhyanga_table']);
        self::assertSame('sunrise', FestivalService::FESTIVALS['Chaitra Purnima']['karmakala_type']);
        self::assertSame('sunrise', FestivalService::FESTIVALS['Swaminarayan Jayanti (Hari-Nom)']['karmakala_type']);
        self::assertTrue(FestivalService::FESTIVALS['Swaminarayan Jayanti (Hari-Nom)']['require_sunrise_vyapini']);
        self::assertSame('first', FestivalService::FESTIVALS['Swaminarayan Jayanti (Hari-Nom)']['vriddhi_preference']);
        self::assertArrayHasKey('Hari Jayanti', FestivalService::FESTIVALS);
        self::assertSame('sunrise', FestivalService::FESTIVALS['Hari Jayanti']['karmakala_type']);
        self::assertTrue(FestivalService::FESTIVALS['Hari Jayanti']['require_sunrise_vyapini']);
        self::assertSame('first', FestivalService::FESTIVALS['Hari Jayanti']['vriddhi_preference']);
        self::assertSame('first', FestivalService::FESTIVALS['Hari Jayanti']['kshaya_preference']);
        self::assertSame(['Chaitra'], FestivalService::FESTIVALS['Hari Jayanti']['excluded_months_amanta']);
        self::assertSame(['Chaitra'], FestivalService::FESTIVALS['Hari Jayanti']['excluded_months_purnimanta']);
        self::assertSame('abhijit', FestivalService::FESTIVALS['Vamana Jayanti']['karmakala_type']);
        self::assertSame('Shravana', FestivalService::FESTIVALS['Vamana Jayanti']['nakshatra']);
        self::assertSame('aparahna', FestivalService::FESTIVALS['Samaveda Upakarma']['karmakala_type']);
        self::assertTrue(FestivalService::FESTIVALS['Samaveda Upakarma']['require_nakshatra_window']);
        self::assertTrue(FestivalService::FESTIVALS['Govardhan Puja']['chandradarshan_nishedh']);
        self::assertTrue(FestivalService::FESTIVALS['Govardhan Puja']['location_sensitive']);
        self::assertTrue(FestivalService::FESTIVALS['Ganesh Chaturthi']['chandradarshan_nishedh']);
        self::assertSame('metadata', FestivalService::FESTIVALS['Ganesh Chaturthi']['chandradarshan_nishedh_mode']);
        self::assertContains('Ganesh Chaturthi', FestivalService::FESTIVALS['Vinayaka Chaturthi']['aliases']);
        self::assertContains('Siddhivinayaka Chaturthi', FestivalService::FESTIVALS['Vinayaka Chaturthi']['aliases']);
        self::assertArrayNotHasKey('Siddhivinayaka Chaturthi', FestivalService::FESTIVALS);
        self::assertSame('madhyahna', FestivalService::FESTIVALS['Tulsi Vivah']['karmakala_type']);
        self::assertSame('sayankala', FestivalService::FESTIVALS['Tulsi Vivah']['ritual_kala_type']);
        self::assertSame('Satsangi Jeevan 4.58.105-117', FestivalService::FESTIVALS['Tulsi Vivah']['source_refs'][0]);
    }

    public function testSatsangiJeevanAnnualFestivalRegistryEntriesAreEncoded(): void
    {
        $expanded = $this->expandedFestivalRules();

        $expected = [
            'Ramanand Swami Appearance Festival',
            'Chandrayan Vrat',
            'Nara-Narayan Arjun Janmotsav',
            'Dharmadev Janmotsav',
            'Hatadi Festival',
            'Snanyatra',
            'Swaminarayan Rathyatra',
            'Hindola Festival Begins',
            'Pavitra Festival',
        ];

        foreach ($expected as $festivalName) {
            self::assertArrayHasKey($festivalName, FestivalService::FESTIVALS);
            self::assertTrue(FestivalService::FESTIVALS[$festivalName]['sect_specific'] ?? false, $festivalName);
            self::assertNotEmpty(FestivalService::FESTIVALS[$festivalName]['source_refs'] ?? [], $festivalName);
        }

        self::assertSame('pratah_kal', FestivalService::FESTIVALS['Ramanand Swami Appearance Festival']['karmakala_type']);
        self::assertSame('ramanand_pradurbhav_satsangi', FestivalService::FESTIVALS['Ramanand Swami Appearance Festival']['ritual_profile']);
        self::assertSame(14, FestivalService::FESTIVALS['Chandrayan Vrat']['tithi']);
        self::assertSame('madhyahna', FestivalService::FESTIVALS['Nara-Narayan Arjun Janmotsav']['karmakala_type']);
        self::assertSame('Uttara Phalguni', FestivalService::FESTIVALS['Nara-Narayan Arjun Janmotsav']['nakshatra']);
        self::assertSame('Jyeshtha', FestivalService::FESTIVALS['Snanyatra']['nakshatra']);
        self::assertTrue(FestivalService::FESTIVALS['Snanyatra']['nakshatra_only']);
        self::assertSame('Pushya', FestivalService::FESTIVALS['Swaminarayan Rathyatra']['nakshatra']);
        self::assertSame('Shukla', FestivalService::FESTIVALS['Swaminarayan Rathyatra']['paksha']);
        self::assertTrue(FestivalService::FESTIVALS['Swaminarayan Rathyatra']['nakshatra_only']);
        self::assertSame([1, 2], FestivalService::FESTIVALS['Hindola Festival Begins']['tithi_options']);
        self::assertTrue(FestivalService::FESTIVALS['Hindola Festival Begins']['prefer_higher_tithi_option'] ?? false);
        self::assertSame(3, FestivalService::FESTIVALS['Hindola Festival Ends']['tithi']);
        self::assertSame('last', FestivalService::FESTIVALS['Varaha Jayanti']['vriddhi_preference']);
        self::assertSame('last', FestivalService::FESTIVALS['Swaminarayan Varaha Jayanti']['vriddhi_preference']);
        self::assertArrayHasKey('Kurma Jayanti', FestivalService::FESTIVALS);
        self::assertTrue(FestivalService::FESTIVALS['Kurma Jayanti']['sect_specific'] ?? false);
        self::assertSame('kurma_jayanti_satsangi', FestivalService::FESTIVALS['Kurma Jayanti']['ritual_profile']);
    }

    public function testNewSatsangiFestivalStringsAreLocalizedInHindiAndGujarati(): void
    {
        $keysByType = [
            'Festival' => [
                'Ramanand Swami Appearance Festival',
                'Nara-Narayan Arjun Janmotsav',
                'Chandrayan Vrat',
                'Dharmadev Janmotsav',
                'Hatadi Festival',
                'Kurma Jayanti',
                'Snanyatra',
                'Swaminarayan Rathyatra',
                'Hindola Festival Begins',
                'Pavitra Festival',
                'Swaminarayan Varaha Jayanti',
                'Dhanurmas Festival Begins',
                'Dhanatrayodashi',
                'Alankar Marjan',
                'Gangavatar',
                'Dasahara',
                'Danleela Mahotsav',
                'Padma Ekadashi',
            ],
            'FestivalDesc' => [
                'Swaminarayan/Uddhav-sampraday Ramanand Swami appearance observance on Janmashtami',
                'Swaminarayan Nara-Narayan / Arjun birth festival with midday Dwitiya and Uttara Phalguni preference',
                'Swaminarayan Dharmadev birth festival observed on Prabodhini Ekadashi',
                'Swaminarayan Hatadi observance with Radha-Damodar worship on Prabodhini evening',
                'Chandrayan vrat beginning with Pausha Shukla Chaturdashi in the Swaminarayan annual vrata cycle',
                'Commemorates the Kurma incarnation of Lord Vishnu with tradition-aware observance routing',
                'Swaminarayan Snanyatra when Jyeshtha nakshatra is present at sunrise',
                'Swaminarayan Rathyatra when Pushya nakshatra is present at sunrise in Ashadha',
                'Beginning of the Swaminarayan Hindola festival season; source permits Ashadha Krishna Pratipada or Dwitiya when Moon is in Taurus',
                'Swaminarayan Pavitra offering on Shravana Shukla Ekadashi or Dwadashi',
                'Swaminarayan Varaha Jayanti on Shravana Shukla Chaturthi with midday worship',
            ],
            'String' => [
                'janmashtami_uddhav',
                'nara_narayan_arjun_janmotsav',
                'dharmadev_janmotsav',
                'hatadi_prabodhini',
                'chandrayan_vrat_satsangi',
                'kurma_jayanti_satsangi',
                'snanyatra_satsangi',
                'rathyatra_satsangi',
                'hindola_satsangi',
                'pavitra_satsangi',
                'varaha_jayanti_satsangi',
                'dhanurmas_satsangi',
                'ramnavami_satsangi',
                'parashurama_jayanti_satsangi',
                'narasimha_jayanti_satsangi',
                'gangavatar_dasahara_satsangi',
                'vijayadashami_satsangi',
                'sharad_purnima_rasa',
                'alankar_marjan_satsangi',
                'pushpa_dolotsav_satsangi',
                'source_sensitive_monthly_chandra_darshana_first_crescent',
                'application_definition_first_visible_crescent',
                'Uddhav/Swaminarayan Janmashtami with Vitthalesh Goswami accepted opinion',
                'Swaminarayan/Uddhav Janmashtami morning and midnight observance',
            ],
            'Deity' => [
                'Ramanand Swami',
                'Nara-Narayan/Arjun',
                'Dharmadev/Bhaktidevi',
                'Radha-Damodar',
                'Vishnu/Chandra',
                'Vishnu (Kurma)',
                'Balakrishna',
                'Vishnu (Varaha)',
            ],
            'Region' => ['Swaminarayan'],
        ];

        foreach (['hi', 'gu'] as $locale) {
            foreach ($keysByType as $type => $keys) {
                foreach ($keys as $key) {
                    self::assertNotSame($key, Localization::translate($type, $key, $locale), sprintf('%s:%s:%s', $type, $key, $locale));
                }
            }
        }
    }

    public function testHariJayantiUsesSunriseVyapiniNavamiAndPrefersFirstVriddhiDay(): void
    {
        $engine = new FestivalRuleEngine;
        $date = CarbonImmutable::parse('2026-03-26');
        $today = $this->festivalSnapshot(9, 'Shukla', 100.25, 100.75, 101.25, 100.10, 101.60, 'Pushya');
        $tomorrow = $this->festivalSnapshot(9, 'Shukla', 101.25, 101.75, 102.25, 100.10, 101.60, 'Ashlesha');

        $resolved = $engine->resolveMajorFestival('Swaminarayan Jayanti (Hari-Nom)', FestivalService::FESTIVALS['Swaminarayan Jayanti (Hari-Nom)'], $date, $today, $tomorrow);

        self::assertNotNull($resolved);
        self::assertSame('2026-03-26', $resolved['observance_date']);
        self::assertTrue($resolved['tithi_at_sunrise_today']);
        self::assertTrue($resolved['tithi_at_sunrise_tomorrow']);
        self::assertSame('sunrise', $resolved['karmakala_type']);
        self::assertSame('first', $resolved['decision']['vriddhi_preference']);
    }

    public function testMonthlyHariJayantiInheritsSunriseVyapiniNavamiRuleOutsideChaitra(): void
    {
        $engine = new FestivalRuleEngine;
        $date = CarbonImmutable::parse('2026-05-26');
        $today = $this->festivalSnapshot(9, 'Shukla', 100.25, 100.75, 101.25, 100.10, 101.60, 'Pushya');
        $today['Hindu_Calendar'] = [
            'Calendar_Type' => 'amanta',
            'Month_Amanta_En' => 'Vaishakha',
            'Month_Purnimanta_En' => 'Vaishakha',
        ];
        $tomorrow = $this->festivalSnapshot(9, 'Shukla', 101.25, 101.75, 102.25, 100.10, 101.60, 'Ashlesha');
        $tomorrow['Hindu_Calendar'] = [
            'Calendar_Type' => 'amanta',
            'Month_Amanta_En' => 'Vaishakha',
            'Month_Purnimanta_En' => 'Vaishakha',
        ];

        $resolved = $engine->resolveMajorFestival('Hari Jayanti', FestivalService::FESTIVALS['Hari Jayanti'], $date, $today, $tomorrow);

        self::assertNotNull($resolved);
        self::assertSame('2026-05-26', $resolved['observance_date']);
        self::assertTrue($resolved['tithi_at_sunrise_today']);
        self::assertTrue($resolved['tithi_at_sunrise_tomorrow']);
        self::assertSame('sunrise', $resolved['karmakala_type']);
        self::assertSame('first', $resolved['decision']['vriddhi_preference']);
    }

    public function testHolikaDahanRejectsBhadraMukhaDuringPradosha(): void
    {
        $engine = new FestivalRuleEngine;
        $date = CarbonImmutable::parse('2026-03-02');
        $today = $this->festivalSnapshot(15, 'Shukla', 100.25, 100.75, 101.25, 100.70, 101.80, 'Magha');
        $today['Bhadra'] = [[
            'start_jd' => 100.75,
            'end_jd' => 100.90,
            'parts' => [
                'mukha' => ['start_jd' => 100.75, 'end_jd' => 100.80],
                'madhya' => ['start_jd' => 100.80, 'end_jd' => 100.86],
                'puchha' => ['start_jd' => 100.86, 'end_jd' => 100.90],
            ],
        ]];
        $tomorrow = $this->festivalSnapshot(15, 'Shukla', 101.25, 101.75, 102.25, 100.70, 101.80, 'Purva Phalguni');

        $resolved = $engine->resolveMajorFestival('Holika Dahan', [
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'karmakala_type' => 'pradosha',
            'strict_karmakala' => true,
            'avoid_bhadra_mukha' => true,
            'prefer_bhadra_puchha' => true,
        ], $date, $today, $tomorrow);

        self::assertNotNull($resolved);
        self::assertSame('2026-03-03', $resolved['observance_date']);
        self::assertSame('no_bhadra_in_window', $resolved['decision']['bhadra_decision']['reason']);
    }

    public function testFestivalKarmakalasSeparateProportionalAndFixedAnchorWindows(): void
    {
        $engine = new FestivalRuleEngine;
        $method = new ReflectionMethod(FestivalRuleEngine::class, 'karmakalaWindowJd');
        $ctx = [
            'sunrise_jd' => 100.25,
            'sunset_jd' => 100.85,
            'next_sunrise_jd' => 101.25,
        ];

        $dayDuration = 0.60;
        $nightDuration = 0.40;
        $dayMuhurta = $dayDuration / 15.0;
        $nightMuhurta = $nightDuration / 15.0;

        $windows = [
            'arunodaya' => [100.25 - (2.0 * $nightMuhurta), 100.25],
            'pratah_kal' => [100.25, 100.25 + ($dayDuration / 5.0)],
            'sangava' => [100.25 + ($dayDuration / 5.0), 100.25 + ($dayDuration * 2.0 / 5.0)],
            'madhyahna' => [100.25 + ($dayDuration * 2.0 / 5.0), 100.25 + ($dayDuration * 3.0 / 5.0)],
            'abhijit' => [100.25 + (7.0 * $dayMuhurta), 100.25 + (8.0 * $dayMuhurta)],
            'aparahna' => [100.25 + ($dayDuration * 3.0 / 5.0), 100.25 + ($dayDuration * 4.0 / 5.0)],
            'vijaya_kaal' => [100.25 + (10.0 * $dayMuhurta), 100.25 + (11.0 * $dayMuhurta)],
            'sayankala' => [100.25 + ($dayDuration * 4.0 / 5.0), 100.85],
            'sunset' => [100.85 - (24.0 / 1440.0), 100.85 + (48.0 / 1440.0)],
            'pradosha' => [100.85, 100.85 + (3.0 * $nightMuhurta)],
            'ratri' => [100.85 + (3.0 * $nightMuhurta), 100.85 + (7.0 * $nightMuhurta)],
            'nishitha' => [100.85 + (7.0 * $nightMuhurta), 100.85 + (8.0 * $nightMuhurta)],
            'usha' => [100.85 + (8.0 * $nightMuhurta), 100.85 + (13.0 * $nightMuhurta)],
        ];

        foreach ($windows as $type => [$expectedStart, $expectedEnd]) {
            $window = $method->invoke($engine, $type, $ctx);
            self::assertEqualsWithDelta($expectedStart, $window['start_jd'], 1e-10, $type . ' start');
            self::assertEqualsWithDelta($expectedEnd, $window['end_jd'], 1e-10, $type . ' end');
        }
    }

    public function testRakshaBandhanUsesUdayaPurnimaWhenThreeMuhurtasRemainAfterSunrise(): void
    {
        $engine = new FestivalRuleEngine;
        $date = CarbonImmutable::parse('2026-08-27');
        $today = $this->festivalSnapshot(15, 'Shukla', 100.25, 100.75, 101.25, 100.70, 101.38, 'Shravana');
        $tomorrow = $this->festivalSnapshot(15, 'Shukla', 101.25, 101.85, 102.25, 100.70, 101.38, 'Dhanishta');

        $resolved = $engine->resolveMajorFestival('Shravana Purnima', FestivalService::FESTIVALS['Shravana Purnima'], $date, $today, $tomorrow);

        self::assertNotNull($resolved);
        self::assertSame('2026-08-28', $resolved['observance_date']);
        self::assertSame('raksha_bandhan_udaya_purnima_3_muhurta', $resolved['decision']['winning_reason']);
        self::assertSame('UDAYA_PURNIMA_3_MUHURTA', $resolved['decision']['raksha_bandhan_selection']['selection_rule']);
        self::assertFalse($resolved['decision']['raksha_bandhan_selection']['previous_day_fallback_selected']);
        self::assertSame('dynamic_dinamana_day_muhurta', $resolved['decision']['raksha_bandhan_selection']['basis']);
        self::assertEqualsWithDelta(57.6, $resolved['decision']['raksha_bandhan_selection']['day_muhurta_minutes'], 1e-6);
        self::assertEqualsWithDelta(172.8, $resolved['decision']['raksha_bandhan_selection']['minimum_post_sunrise_purnima_minutes'], 1e-6);
        self::assertGreaterThanOrEqual(
            $resolved['decision']['raksha_bandhan_selection']['minimum_post_sunrise_purnima_minutes'],
            $resolved['decision']['raksha_bandhan_selection']['post_sunrise_purnima_minutes']
        );
    }

    public function testRakshaBandhanFallsBackToPreviousDayWhenUdayaPurnimaIsShorterThanThreeMuhurtas(): void
    {
        $engine = new FestivalRuleEngine;
        $date = CarbonImmutable::parse('2026-08-27');
        $today = $this->festivalSnapshot(15, 'Shukla', 100.25, 100.75, 101.25, 100.70, 101.36, 'Shravana');
        $tomorrow = $this->festivalSnapshot(15, 'Shukla', 101.25, 101.85, 102.25, 100.70, 101.36, 'Dhanishta');

        $resolved = $engine->resolveMajorFestival('Shravana Purnima', FestivalService::FESTIVALS['Shravana Purnima'], $date, $today, $tomorrow);

        self::assertNotNull($resolved);
        self::assertSame('2026-08-27', $resolved['observance_date']);
        self::assertSame('raksha_bandhan_previous_day_fallback', $resolved['decision']['winning_reason']);
        self::assertSame('PREVIOUS_DAY_FALLBACK', $resolved['decision']['raksha_bandhan_selection']['selection_rule']);
        self::assertTrue($resolved['decision']['raksha_bandhan_selection']['previous_day_fallback_selected']);
        self::assertSame('dynamic_dinamana_day_muhurta', $resolved['decision']['raksha_bandhan_selection']['basis']);
        self::assertEqualsWithDelta(57.6, $resolved['decision']['raksha_bandhan_selection']['day_muhurta_minutes'], 1e-6);
        self::assertEqualsWithDelta(172.8, $resolved['decision']['raksha_bandhan_selection']['minimum_post_sunrise_purnima_minutes'], 1e-6);
        self::assertLessThan(
            $resolved['decision']['raksha_bandhan_selection']['minimum_post_sunrise_purnima_minutes'],
            $resolved['decision']['raksha_bandhan_selection']['post_sunrise_purnima_minutes']
        );
    }

    public function testPurnimaVratTruthTableKeepsFirstDayWhenChaturdashiEndsBeforeEighteenGhadis(): void
    {
        $engine = new FestivalRuleEngine;
        $date = CarbonImmutable::parse('2026-01-03');
        $today = $this->festivalSnapshot(15, 'Shukla', 100.25, 100.85, 101.25, 100.40, 101.60, 'Punarvasu');
        $tomorrow = $this->festivalSnapshot(15, 'Shukla', 101.25, 101.85, 102.25, 100.40, 101.60, 'Pushya');

        $resolved = $engine->resolveMajorFestival('Pausha Purnima Vrat', [
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'karmakala_type' => 'sunrise',
            'strict_karmakala' => true,
            'purnima_vrat_18_ghadi_rule' => true,
        ], $date, $today, $tomorrow);

        self::assertNotNull($resolved);
        self::assertSame('2026-01-03', $resolved['observance_date']);
        self::assertSame('purnima_vrat_chaturdashi_below_18_ghadi_keep_day1', $resolved['decision']['winning_reason']);
    }

    public function testPurnimaVratTruthTableShiftsToSecondDayWhenChaturdashiReachesEighteenGhadis(): void
    {
        $engine = new FestivalRuleEngine;
        $date = CarbonImmutable::parse('2026-01-03');
        $today = $this->festivalSnapshot(15, 'Shukla', 100.25, 100.85, 101.25, 100.70, 101.60, 'Punarvasu');
        $tomorrow = $this->festivalSnapshot(15, 'Shukla', 101.25, 101.85, 102.25, 100.70, 101.60, 'Pushya');

        $resolved = $engine->resolveMajorFestival('Pausha Purnima Vrat', [
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'karmakala_type' => 'sunrise',
            'strict_karmakala' => true,
            'purnima_vrat_18_ghadi_rule' => true,
        ], $date, $today, $tomorrow);

        self::assertNotNull($resolved);
        self::assertSame('2026-01-04', $resolved['observance_date']);
        self::assertSame('purnima_vrat_chaturdashi_at_or_above_18_ghadi_shift_day2', $resolved['decision']['winning_reason']);
    }

    public function testPurnimaVratTruthTableUsesDynamicDaytimeEighteenGhadis(): void
    {
        $engine = new FestivalRuleEngine;
        $date = CarbonImmutable::parse('2026-01-03');
        $today = $this->festivalSnapshot(15, 'Shukla', 100.25, 101.05, 101.25, 100.65, 101.60, 'Punarvasu');
        $tomorrow = $this->festivalSnapshot(15, 'Shukla', 101.25, 102.05, 102.25, 100.65, 101.60, 'Pushya');

        $resolved = $engine->resolveMajorFestival('Pausha Purnima Vrat', [
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'karmakala_type' => 'sunrise',
            'strict_karmakala' => true,
            'purnima_vrat_18_ghadi_rule' => true,
        ], $date, $today, $tomorrow);

        self::assertNotNull($resolved);
        self::assertSame('2026-01-03', $resolved['observance_date']);
        self::assertSame('purnima_vrat_chaturdashi_below_18_ghadi_keep_day1', $resolved['decision']['winning_reason']);
    }

    public function testGaneshChaturthiPrefersFullMadhyahnaCoverage(): void
    {
        $engine = new FestivalRuleEngine;
        $date = CarbonImmutable::parse('2026-09-14');
        $today = $this->festivalSnapshot(4, 'Shukla', 100.25, 100.75, 101.25, 100.45, 101.50, 'Hasta');
        $tomorrow = $this->festivalSnapshot(4, 'Shukla', 101.25, 101.75, 102.25, 100.45, 101.50, 'Chitra');

        $resolved = $engine->resolveMajorFestival('Ganesh Chaturthi', [
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 4,
            'karmakala_type' => 'madhyahna',
            'strict_karmakala' => true,
            'prefer_full_karmakala_coverage' => true,
        ], $date, $today, $tomorrow);

        self::assertNotNull($resolved);
        self::assertSame('2026-09-14', $resolved['observance_date']);
        self::assertSame('target_covers_full_karmakala', $resolved['decision']['winning_reason']);
        self::assertEqualsWithDelta(1.0, $resolved['decision']['winning_window_coverage_ratio'], 1e-6);
    }

    public function testHolikaDahanLunarEclipseExceptionShiftsToSecondPradoshaWhenAvailable(): void
    {
        $engine = new FestivalRuleEngine;
        $date = CarbonImmutable::parse('2026-03-02');
        $today = $this->festivalSnapshot(15, 'Shukla', 100.25, 100.75, 101.25, 100.70, 101.90, 'Magha');
        $today['Lunar_Eclipse'] = true;
        $tomorrow = $this->festivalSnapshot(15, 'Shukla', 101.25, 101.75, 102.25, 100.70, 101.90, 'Purva Phalguni');

        $resolved = $engine->resolveMajorFestival('Holika Dahan', [
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 15,
            'karmakala_type' => 'pradosha',
            'strict_karmakala' => true,
            'holika_lunar_eclipse_exception' => true,
        ], $date, $today, $tomorrow);

        self::assertNotNull($resolved);
        self::assertSame('2026-03-03', $resolved['observance_date']);
        self::assertSame('holika_lunar_eclipse_shift_to_second_pradosha', $resolved['decision']['winning_reason']);
    }

    public function testJanmashtamiTruthTableRejectsSaptamiViddhaDayOne(): void
    {
        $engine = new FestivalRuleEngine;
        $date = CarbonImmutable::parse('2026-09-07'); // Monday, but no Rohini/Jayanti in this fixture.
        $today = $this->festivalSnapshot(23, 'Krishna', 100.25, 100.75, 101.25, 100.80, 101.90, 'Mrigashira');
        $tomorrow = $this->festivalSnapshot(23, 'Krishna', 101.25, 101.75, 102.25, 100.80, 101.90, 'Ardra');

        $resolved = $engine->resolveMajorFestival('Krishna Janmashtami', [
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 8,
            'karmakala_type' => 'nishitha',
            'strict_karmakala' => true,
            'nakshatra' => 'Rohini',
            'prefer_weekdays' => [1, 3],
            'janmashtami_truth_table' => true,
        ], $date, $today, $tomorrow);

        self::assertNotNull($resolved);
        self::assertSame('2026-09-08', $resolved['observance_date']);
        self::assertSame('janmashtami_saptami_viddha_choose_day2', $resolved['decision']['winning_reason']);
    }

    public function testJanmashtamiTruthTablePrioritizesJayantiYoga(): void
    {
        $engine = new FestivalRuleEngine;
        $date = CarbonImmutable::parse('2026-09-07'); // Monday
        $today = $this->festivalSnapshot(23, 'Krishna', 100.25, 100.75, 101.25, 100.70, 101.90, 'Rohini');
        $tomorrow = $this->festivalSnapshot(23, 'Krishna', 101.25, 101.75, 102.25, 100.70, 101.90, 'Mrigashira');

        $resolved = $engine->resolveMajorFestival('Krishna Janmashtami', [
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 8,
            'karmakala_type' => 'nishitha',
            'strict_karmakala' => true,
            'nakshatra' => 'Rohini',
            'prefer_weekdays' => [1, 3],
            'janmashtami_truth_table' => true,
        ], $date, $today, $tomorrow);

        self::assertNotNull($resolved);
        self::assertSame('2026-09-07', $resolved['observance_date']);
        self::assertSame('janmashtami_jayanti_yoga_day1', $resolved['decision']['winning_reason']);
    }

    public function testMasikJanmashtamiKeepsPanchangDayWhenAshtamiEntersBeforeNishitha(): void
    {
        $engine = new FestivalRuleEngine;
        $date = CarbonImmutable::parse('2026-04-09');
        $today = $this->festivalSnapshot(23, 'Krishna', 100.25, 100.75, 101.25, 100.90, 101.40, 'Purva Ashadha');
        $tomorrow = $this->festivalSnapshot(23, 'Krishna', 101.25, 101.75, 102.25, 100.90, 101.40, 'Uttara Ashadha');

        $resolved = $engine->resolveMajorFestival('Masik Krishna Janmashtami', [
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 8,
            'karmakala_type' => 'nishitha',
            'strict_karmakala' => true,
            'masik_janmashtami_truth_table' => true,
        ], $date, $today, $tomorrow);

        self::assertNotNull($resolved);
        self::assertSame('2026-04-09', $resolved['observance_date']);
        self::assertSame('masik_janmashtami_nishitha_only_day1', $resolved['decision']['winning_reason']);
    }

    public function testVijayadashamiTruthTableUsesShravanaTieBreakWhenBothDaysHaveVijayaKaal(): void
    {
        $engine = new FestivalRuleEngine;
        $date = CarbonImmutable::parse('2026-10-20');
        $today = $this->festivalSnapshot(10, 'Shukla', 100.25, 100.75, 101.25, 100.60, 101.85, 'Uttara Ashadha');
        $tomorrow = $this->festivalSnapshot(10, 'Shukla', 101.25, 101.75, 102.25, 100.60, 101.85, 'Shravana');

        $resolved = $engine->resolveMajorFestival('Dussehra', [
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 10,
            'karmakala_type' => 'vijaya_kaal',
            'strict_karmakala' => true,
            'nakshatra' => 'Shravana',
            'vijayadashami_truth_table' => true,
        ], $date, $today, $tomorrow);

        self::assertNotNull($resolved);
        self::assertSame('2026-10-21', $resolved['observance_date']);
        self::assertSame('vijayadashami_both_vijaya_kaal_shravana_day2', $resolved['decision']['winning_reason']);
    }

    public function testGovatsaTruthTableChoosesSecondDayWhenBothHavePradosha(): void
    {
        $engine = new FestivalRuleEngine;
        $date = CarbonImmutable::parse('2026-11-06');
        $today = $this->festivalSnapshot(27, 'Krishna', 100.25, 100.75, 101.25, 100.70, 101.90, 'Hasta');
        $tomorrow = $this->festivalSnapshot(27, 'Krishna', 101.25, 101.75, 102.25, 100.70, 101.90, 'Chitra');

        $resolved = $engine->resolveMajorFestival('Vagh Baras', [
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 12,
            'karmakala_type' => 'pradosha',
            'strict_karmakala' => true,
            'govatsa_truth_table' => true,
        ], $date, $today, $tomorrow);

        self::assertNotNull($resolved);
        self::assertSame('2026-11-07', $resolved['observance_date']);
        self::assertSame('govatsa_equal_pradosha_choose_day2', $resolved['decision']['winning_reason']);
    }

    public function testPradoshTruthTableChoosesSecondDayWhenBothHaveEqualPradosha(): void
    {
        $engine = new FestivalRuleEngine;
        $date = CarbonImmutable::parse('2026-01-06');
        $today = $this->festivalSnapshot(13, 'Shukla', 100.25, 100.75, 101.25, 100.70, 101.90, 'Hasta');
        $tomorrow = $this->festivalSnapshot(13, 'Shukla', 101.25, 101.75, 102.25, 100.70, 101.90, 'Chitra');

        $resolved = $engine->resolveMajorFestival('Pradosh Vrat', [
            'resolver' => 'classical',
            'paksha' => 'Both',
            'tithi' => 13,
            'karmakala_type' => 'pradosha',
            'strict_karmakala' => true,
            'pradosh_truth_table' => true,
        ], $date, $today, $tomorrow);

        self::assertNotNull($resolved);
        self::assertSame('2026-01-06', $resolved['observance_date']);
        self::assertSame('pradosh_first_day_full_pradosha_coverage', $resolved['decision']['winning_reason']);
    }

    public function testPradoshTruthTableKeepsFirstDayWhenFirstPradoshaCoverageIsFull(): void
    {
        $engine = new FestivalRuleEngine;
        $date = CarbonImmutable::parse('2026-01-06');
        $today = $this->festivalSnapshot(13, 'Shukla', 100.25, 100.75, 101.25, 100.70, 101.78, 'Hasta');
        $tomorrow = $this->festivalSnapshot(13, 'Shukla', 101.25, 101.75, 102.25, 100.70, 101.78, 'Chitra');

        $resolved = $engine->resolveMajorFestival('Pradosh Vrat', [
            'resolver' => 'classical',
            'paksha' => 'Both',
            'tithi' => 13,
            'karmakala_type' => 'pradosha',
            'strict_karmakala' => true,
            'pradosh_truth_table' => true,
        ], $date, $today, $tomorrow);

        self::assertNotNull($resolved);
        self::assertSame('2026-01-06', $resolved['observance_date']);
        self::assertSame('pradosh_first_day_full_pradosha_coverage', $resolved['decision']['winning_reason']);
    }

    public function testTrayodashiFestivalDescriptionDoesNotTriggerPradoshVratTruthTable(): void
    {
        $engine = new FestivalRuleEngine;
        $date = CarbonImmutable::parse('2026-11-06');
        $today = $this->festivalSnapshot(28, 'Krishna', 100.25, 100.75, 101.25, 100.70, 101.90, 'Hasta');
        $tomorrow = $this->festivalSnapshot(29, 'Krishna', 101.25, 101.75, 102.25, 100.70, 101.90, 'Chitra');

        $resolved = $engine->resolveMajorFestival('Dhanteras', [
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 13,
            'description' => 'Worship of Lakshmi and Dhanvantari during pradosha.',
            'fasting' => false,
            'karmakala_type' => 'pradosha',
            'strict_karmakala' => true,
        ], $date, $today, $tomorrow);

        self::assertNotNull($resolved);
        self::assertFalse(str_starts_with((string) $resolved['decision']['winning_reason'], 'pradosh_'));
    }

    public function testSankashtiTruthTableChoosesFirstWhenBothMoonrisesAreVyapini(): void
    {
        $engine = new FestivalRuleEngine;
        $date = CarbonImmutable::parse('2026-01-07');
        $today = $this->festivalSnapshot(19, 'Krishna', 100.25, 100.75, 101.25, 100.40, 101.90, 'Hasta', null, 100.60);
        $tomorrow = $this->festivalSnapshot(19, 'Krishna', 101.25, 101.75, 102.25, 100.40, 101.90, 'Chitra', null, 101.60);

        $resolved = $engine->resolveMajorFestival('Sankashti Chaturthi', [
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 4,
            'karmakala_type' => 'moonrise',
            'strict_karmakala' => true,
            'sankashti_truth_table' => true,
        ], $date, $today, $tomorrow);

        self::assertNotNull($resolved);
        self::assertSame('2026-01-07', $resolved['observance_date']);
        self::assertSame('sankashti_both_moonrise_vyapini_tritiya_yuta_day1', $resolved['decision']['winning_reason']);
    }

    public function testSankashtiTruthTableDoesNotInventDateWhenNeitherMoonriseIsVyapini(): void
    {
        $engine = new FestivalRuleEngine;
        $date = CarbonImmutable::parse('2026-01-07');
        $today = $this->festivalSnapshot(19, 'Krishna', 100.25, 100.75, 101.25, 100.70, 101.40, 'Hasta', null, 100.60);
        $tomorrow = $this->festivalSnapshot(19, 'Krishna', 101.25, 101.75, 102.25, 100.70, 101.40, 'Chitra', null, 101.60);

        $resolved = $engine->resolveMajorFestival('Sankashti Chaturthi', [
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 4,
            'karmakala_type' => 'moonrise',
            'strict_karmakala' => true,
            'sankashti_truth_table' => true,
        ], $date, $today, $tomorrow);

        self::assertNull($resolved);
    }

    public function testSankashtiTruthTableMarksAngarakWhenWinnerIsTuesday(): void
    {
        $engine = new FestivalRuleEngine;
        $date = CarbonImmutable::parse('2026-01-06'); // Tuesday
        $today = $this->festivalSnapshot(19, 'Krishna', 100.25, 100.75, 101.25, 100.40, 100.90, 'Hasta', null, 100.60);
        $tomorrow = $this->festivalSnapshot(20, 'Krishna', 101.25, 101.75, 102.25, 100.90, 101.40, 'Chitra', null, 101.60);

        $resolved = $engine->resolveMajorFestival('Sankashti Chaturthi', [
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 4,
            'karmakala_type' => 'moonrise',
            'strict_karmakala' => true,
            'sankashti_truth_table' => true,
        ], $date, $today, $tomorrow);

        self::assertNotNull($resolved);
        self::assertTrue($resolved['decision']['sankashti_selection']['is_angarak_sankashti']);
        self::assertSame('Angarak Sankashti Chaturthi', $resolved['decision']['sankashti_selection']['special_name']);
    }

    public function testVinayakiTruthTablePrefersFullMadhyahnaCoverage(): void
    {
        $engine = new FestivalRuleEngine;
        $date = CarbonImmutable::parse('2026-01-21');
        $today = $this->festivalSnapshot(4, 'Shukla', 100.25, 100.75, 101.25, 100.40, 100.66, 'Hasta');
        $tomorrow = $this->festivalSnapshot(4, 'Shukla', 101.25, 101.75, 102.25, 100.40, 100.66, 'Chitra');

        $resolved = $engine->resolveMajorFestival('Vinayaki Chaturthi', [
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 4,
            'karmakala_type' => 'madhyahna',
            'strict_karmakala' => true,
            'vinayaki_chaturthi_truth_table' => true,
        ], $date, $today, $tomorrow);

        self::assertNotNull($resolved);
        self::assertSame('2026-01-21', $resolved['observance_date']);
        self::assertSame('vinayaki_full_madhyahna_day1', $resolved['decision']['winning_reason']);
    }

    public function testEkadashiFestivalResolverFollowsVaishnavaDashamiVedhaShift(): void
    {
        $engine = new FestivalRuleEngine;
        $date = CarbonImmutable::parse('2026-01-01');
        $today = $this->festivalSnapshot(11, 'Shukla', 100.25, 100.75, 101.25, 100.20, 100.90, 'Hasta');
        $tomorrow = $this->festivalSnapshot(12, 'Shukla', 101.25, 101.75, 102.25, 100.90, 101.60, 'Chitra');

        $resolved = $engine->resolveMajorFestival('Ekadashi Vrat', [
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 11,
            'karmakala_type' => 'sunrise',
            'fasting' => true,
            'ekadashi_nirnay_table' => true,
        ], $date, $today, $tomorrow);

        self::assertNotNull($resolved);
        self::assertSame('2026-01-02', $resolved['observance_date']);
        self::assertSame('ekadashi_vaishnava_dashami_55_ghati_vedha', $resolved['decision']['winning_reason']);
    }

    public function testRemainingMonthlyVratGapsAreRepresentedInRegistryMetadata(): void
    {
        self::assertArrayHasKey('Vratni Purnima', FestivalService::FESTIVALS);
        self::assertTrue(FestivalService::FESTIVALS['Vratni Purnima']['purnima_vrat_18_ghadi_rule']);
        self::assertTrue(FestivalService::FESTIVALS['Vinayaki Chaturthi']['vinayaki_chaturthi_truth_table']);
        self::assertContains('Angarak Sankashti Chaturthi', FestivalService::FESTIVALS['Sankashti Chaturthi']['aliases']);
    }

    public function testMahadikshaGuidanceIsStructuredOutsideFestivalCalendar(): void
    {
        $rules = MahadikshaGuidance::rules();
        self::assertSame('ritual_guidance', $rules['type']);
        self::assertFalse($rules['calendar_event']);
        self::assertContains('guru_asta', $rules['prohibitions']);
        self::assertContains('shukra_asta', $rules['prohibitions']);
        self::assertContains('adhika_masa', $rules['prohibitions']);
        self::assertContains('varsha_ritu', $rules['prohibitions']);

        $blocked = MahadikshaGuidance::evaluate(['guru_asta' => true, 'adhika_masa' => true]);
        self::assertFalse($blocked['eligible']);
        self::assertSame(['guru_asta', 'adhika_masa'], $blocked['blocking_conditions']);
    }

    public function testMahashivaratriTruthTablePrefersSecondWhenBothHaveFullNishitha(): void
    {
        $engine = new FestivalRuleEngine;
        $date = CarbonImmutable::parse('2026-02-14');
        $today = $this->festivalSnapshot(29, 'Krishna', 100.25, 100.75, 101.25, 100.97, 102.03, 'Shravana');
        $tomorrow = $this->festivalSnapshot(29, 'Krishna', 101.25, 101.75, 102.25, 100.97, 102.03, 'Dhanishta');

        $resolved = $engine->resolveMajorFestival('Maha Shivaratri', [
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 14,
            'karmakala_type' => 'nishitha',
            'strict_karmakala' => true,
            'mahashivaratri_truth_table' => true,
        ], $date, $today, $tomorrow);

        self::assertNotNull($resolved);
        self::assertSame('2026-02-15', $resolved['observance_date']);
        self::assertSame('mahashivaratri_both_full_nishitha_choose_day2_per_ref', $resolved['decision']['winning_reason']);
    }

    public function testMahashivaratriTruthTableKeepsFullNishithaOverSecondDayEkadesha(): void
    {
        $engine = new FestivalRuleEngine;
        $date = CarbonImmutable::parse('2026-02-14');
        $today = $this->festivalSnapshot(29, 'Krishna', 100.25, 100.75, 101.25, 100.97, 101.99, 'Shravana');
        $tomorrow = $this->festivalSnapshot(29, 'Krishna', 101.25, 101.75, 102.25, 100.97, 101.99, 'Dhanishta');

        $resolved = $engine->resolveMajorFestival('Maha Shivaratri', [
            'resolver' => 'classical',
            'paksha' => 'Krishna',
            'tithi' => 14,
            'karmakala_type' => 'nishitha',
            'strict_karmakala' => true,
            'mahashivaratri_truth_table' => true,
        ], $date, $today, $tomorrow);

        self::assertNotNull($resolved);
        self::assertSame('2026-02-14', $resolved['observance_date']);
        self::assertSame('mahashivaratri_day1_full_over_day2_partial', $resolved['decision']['winning_reason']);
    }

    public function testSkandaSashtiTruthTablePrefersPanchamiViddhaEveningMatchOnDayOne(): void
    {
        $engine = new FestivalRuleEngine;
        $date = CarbonImmutable::parse('2026-10-26');
        $today = $this->festivalSnapshot(5, 'Shukla', 100.25, 100.75, 101.25, 100.10, 100.60, 'Mula');
        $tomorrow = $this->festivalSnapshot(6, 'Shukla', 101.25, 101.75, 102.25, 100.60, 101.10, 'Purva Ashadha');

        $resolved = $engine->resolveMajorFestival('Skanda Sashti', [
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 6,
            'karmakala_type' => 'sunset',
            'strict_karmakala' => true,
            'panchami_viddha_allowed' => true,
        ], $date, $today, $tomorrow);

        self::assertNotNull($resolved);
        self::assertSame('2026-10-26', $resolved['observance_date']);
        self::assertSame('skanda_sashti_panchami_viddha_evening_match', $resolved['decision']['winning_reason']);
    }

    public function testSkandaSashtiTruthTableFallsBackToShuddhaSunriseMatchOnDayTwo(): void
    {
        $engine = new FestivalRuleEngine;
        $date = CarbonImmutable::parse('2026-10-26');
        $today = $this->festivalSnapshot(5, 'Shukla', 100.25, 100.75, 101.25, 100.10, 100.80, 'Mula');
        $tomorrow = $this->festivalSnapshot(6, 'Shukla', 101.25, 101.75, 102.25, 100.80, 101.30, 'Purva Ashadha');

        $resolved = $engine->resolveMajorFestival('Skanda Sashti', [
            'resolver' => 'classical',
            'paksha' => 'Shukla',
            'tithi' => 6,
            'karmakala_type' => 'sunset',
            'strict_karmakala' => true,
            'panchami_viddha_allowed' => true,
        ], $date, $today, $tomorrow);

        self::assertNotNull($resolved);
        self::assertSame('2026-10-27', $resolved['observance_date']);
        self::assertSame('skanda_sashti_shuddha_sunrise_match', $resolved['decision']['winning_reason']);
    }

    public function testRemainingGujaratiFestivalRuleFlagsAreEncoded(): void
    {
        $expanded = $this->expandedFestivalRules();

        self::assertTrue(FestivalService::FESTIVALS['Holika Dahan']['avoid_bhadra_mukha']);
        self::assertTrue(FestivalService::FESTIVALS['Holika Dahan']['prefer_bhadra_puchha']);
        self::assertTrue(FestivalService::FESTIVALS['Holika Dahan']['holika_lunar_eclipse_exception']);
        self::assertArrayNotHasKey('reject_weekday_nakshatra', FestivalService::FESTIVALS['Mahavir Jayanti']);
        self::assertSame(['Magha'], FestivalService::FESTIVALS['Masik Shivaratri']['excluded_months_amanta']);
        self::assertTrue(FestivalService::FESTIVALS['Krishna Janmashtami']['janmashtami_truth_table']);
        self::assertTrue(FestivalService::FESTIVALS['Ganesh Chaturthi']['prefer_full_karmakala_coverage']);
        self::assertSame('prefer_full_madhyahna_chaturthi_coverage_over_partial_previous_overlap', FestivalService::FESTIVALS['Ganesh Chaturthi']['gujarati_special_case']);
        self::assertTrue(FestivalService::FESTIVALS['Vasant Panchami']['require_sunrise_vyapini']);
        self::assertContains('Satsangi Jeevan 4.59', FestivalService::FESTIVALS['Vasant Panchami']['source_refs']);
        self::assertSame('matsya_jayanti_satsangi', FestivalService::FESTIVALS['Matsya Jayanti']['ritual_profile']);
        self::assertTrue(FestivalService::FESTIVALS['Matsya Jayanti']['require_sunrise_vyapini']);
        self::assertSame('first', FestivalService::FESTIVALS['Matsya Jayanti']['kshaya_preference']);
        self::assertSame('Shukla', FestivalService::FESTIVALS['Balarama Jayanti']['paksha']);
        self::assertContains('Baladeva Chhath', FestivalService::FESTIVALS['Balarama Jayanti']['aliases']);
        self::assertContains('Baldev Chhath', FestivalService::FESTIVALS['Balarama Jayanti']['aliases']);
        self::assertSame('madhyahna', $expanded['Balarama Jayanti']['karmakala_type']);
        self::assertSame(6, $expanded['Balarama Jayanti']['tithi']);
        self::assertSame('Bhadrapada', $expanded['Balarama Jayanti']['month_amanta']);
        self::assertSame('Swati', $expanded['Balarama Jayanti']['nakshatra']);
        self::assertTrue($expanded['Balarama Jayanti']['prefer_nakshatra']);
        self::assertTrue($expanded['Balarama Jayanti']['strict_karmakala']);
        self::assertArrayHasKey('traditions', FestivalService::FESTIVALS['Parashurama Jayanti']);
        self::assertSame('parashurama_jayanti_satsangi', $expanded['Parashurama Jayanti']['ritual_profile']);
        self::assertSame('pradosha', $expanded['Parashurama Jayanti (Pradosha Tradition)']['karmakala_type']);
        self::assertSame('last', $expanded['Parashurama Jayanti (Pradosha Tradition)']['vriddhi_preference']);
        self::assertTrue(FestivalService::FESTIVALS['Rama Navami']['require_karmakala_match']);
        self::assertSame('last', FestivalService::FESTIVALS['Rama Navami']['vriddhi_preference']);
        self::assertSame('first', FestivalService::FESTIVALS['Rama Navami']['kshaya_preference']);
        self::assertSame('swaminarayan_jayanti_night', FestivalService::FESTIVALS['Swaminarayan Jayanti (Hari-Nom)']['ritual_profile']);
        self::assertSame('madhyahna', FestivalService::FESTIVALS['Ganga Saptami']['karmakala_type']);
        self::assertTrue(FestivalService::FESTIVALS['Ganga Saptami']['strict_karmakala']);
        self::assertSame('madhyahna', FestivalService::FESTIVALS['Sita Navami']['karmakala_type']);
        self::assertTrue(FestivalService::FESTIVALS['Sita Navami']['strict_karmakala']);
        self::assertTrue(FestivalService::FESTIVALS['Yamuna Chhath']['require_sunrise_vyapini']);
        self::assertTrue(FestivalService::FESTIVALS['Shravana Purnima']['avoid_bhadra_mukha']);
        self::assertTrue(FestivalService::FESTIVALS['Shravana Purnima']['prefer_bhadra_puchha']);
        self::assertSame('aparahna', FestivalService::FESTIVALS['Darsha Amavasya']['karmakala_type']);
        self::assertSame('aparahna', FestivalService::FESTIVALS['Adhika Darsha Amavasya']['karmakala_type']);
        self::assertSame('sunrise', FestivalService::FESTIVALS['Maghi Purnima']['karmakala_type']);
        self::assertArrayNotHasKey('forbid_previous_tithi_at', FestivalService::FESTIVALS['Maghi Purnima']);
        self::assertSame('first', FestivalService::FESTIVALS['Maghi Purnima']['vriddhi_preference']);
        self::assertTrue(FestivalService::FESTIVALS['Pausha Purnima Vrat']['purnima_vrat_18_ghadi_rule']);
        self::assertSame('sunrise', FestivalService::FESTIVALS['Ashadha Purnima Vrat']['karmakala_type']);
        self::assertTrue(FestivalService::FESTIVALS['Ashadha Purnima Vrat']['purnima_vrat_18_ghadi_rule']);
        self::assertSame('ashtami_navami_sandhi_interval', FestivalService::FESTIVALS['Sandhi Puja']['ritual_profile']);
        self::assertSame('sharad_purnima_rasa', FestivalService::FESTIVALS['Kojagari Lakshmi Puja']['ritual_profile']);
        self::assertTrue(FestivalService::FESTIVALS['Maha Shivaratri']['ekadesha_coverage_allowed']);
        self::assertTrue(FestivalService::FESTIVALS['Maha Shivaratri']['mahashivaratri_truth_table']);
        self::assertTrue(FestivalService::FESTIVALS['Ganesh Chaturthi']['require_karmakala_match']);
        self::assertTrue(FestivalService::FESTIVALS['Ganesh Chaturthi']['previous_tithi_vedha_tolerated']);
        self::assertTrue(FestivalService::FESTIVALS['Anant Chaturdashi']['require_sunrise_vyapini']);
        self::assertTrue(FestivalService::FESTIVALS['Anant Chaturdashi']['anant_chaturdashi_paraviddha_table']);
        self::assertSame(2, FestivalService::FESTIVALS['Anant Chaturdashi']['resolution_policy']['post_sunrise_muhurta_threshold']);
        self::assertSame('purvahna', FestivalService::FESTIVALS['Akshaya Tritiya']['karmakala_type']);
        self::assertTrue(FestivalService::FESTIVALS['Akshaya Tritiya']['akshaya_tritiya_purvahna_table']);
        self::assertSame(3, FestivalService::FESTIVALS['Akshaya Tritiya']['resolution_policy']['both_days_purvahna_second_day_muhurta_threshold']);
        self::assertSame('first', FestivalService::FESTIVALS['Akshaya Tritiya']['vriddhi_preference']);
        self::assertSame('madhyahna', FestivalService::FESTIVALS['Radha Ashtami']['karmakala_type']);
        self::assertTrue(FestivalService::FESTIVALS['Radha Ashtami']['madhyahna_purvatithi_vedha_rejection']);
        self::assertTrue(FestivalService::FESTIVALS['Radha Ashtami']['kshaya_accept_previous_tithi_vedha']);
        self::assertSame('first', FestivalService::FESTIVALS['Radha Ashtami']['vriddhi_preference']);
        self::assertSame('madhyahna', FestivalService::FESTIVALS['Swaminarayan Varaha Jayanti']['karmakala_type']);
        self::assertTrue(FestivalService::FESTIVALS['Swaminarayan Varaha Jayanti']['madhyahna_purvatithi_vedha_rejection']);
        self::assertTrue(FestivalService::FESTIVALS['Swaminarayan Varaha Jayanti']['kshaya_accept_previous_tithi_vedha']);
        self::assertSame('sunrise', FestivalService::FESTIVALS['Ganga Dussehra']['karmakala_type']);
        self::assertSame('madhyahna', FestivalService::FESTIVALS['Ganga Dussehra']['ritual_kala_type']);
        self::assertSame('Hasta', FestivalService::FESTIVALS['Ganga Dussehra']['nakshatra']);
        self::assertTrue(FestivalService::FESTIVALS['Ganga Dussehra']['prefer_nakshatra']);
        self::assertTrue(FestivalService::FESTIVALS['Ganga Dussehra']['prefer_adhika']);
        self::assertSame('govatsa_dwadashi', FestivalService::FESTIVALS['Vagh Baras']['deepotsav_sequence']);
        self::assertSame('second_day', FestivalService::FESTIVALS['Vagh Baras']['govatsa_equal_pradosha_preference']);
        self::assertTrue(FestivalService::FESTIVALS['Vagh Baras']['govatsa_truth_table']);
        self::assertTrue(FestivalService::FESTIVALS['Dussehra']['vijaya_kaal_primary']);
        self::assertSame('vijaya_kaal', FestivalService::FESTIVALS['Dussehra']['karmakala_type']);
        self::assertTrue(FestivalService::FESTIVALS['Dussehra']['vijayadashami_truth_table']);
        self::assertSame('Shravana', FestivalService::FESTIVALS['Dussehra']['nakshatra']);
        self::assertSame('dhanteras', FestivalService::FESTIVALS['Dhanteras']['deepotsav_sequence']);
        self::assertSame('diwali_lakshmi_kali_puja', FestivalService::FESTIVALS['Kali Puja']['deepotsav_sequence']);
        self::assertSame('bhai_beej', FestivalService::FESTIVALS['Bhai Dooj']['deepotsav_sequence']);
        self::assertContains('Chaitra Navratri Ghatasthapana', FestivalService::FESTIVALS['Chaitra (Vasant) Navaratri Day 1 (Shailaputri Puja)']['aliases']);
        self::assertContains('Sharad Navratri Ghatasthapana', FestivalService::FESTIVALS['Ashvina Sharad Navaratri Day 1 (Shailaputri Puja)']['aliases']);
        self::assertArrayHasKey('Ashvina Sharad Navaratri Day 8 (Mahagauri Puja)', FestivalService::FESTIVALS);
        self::assertArrayHasKey('Ashvina Sharad Navaratri Day 9 (Siddhidatri Puja)', FestivalService::FESTIVALS);
        self::assertContains('Durga Ashtami', FestivalService::FESTIVALS['Ashvina Sharad Navaratri Day 8 (Mahagauri Puja)']['aliases']);
        self::assertContains('Durga Ashtami (Mahagauri Puja)', FestivalService::FESTIVALS['Ashvina Sharad Navaratri Day 8 (Mahagauri Puja)']['aliases']);
        self::assertContains('Ashvina Sharad Navaratri Day 8', FestivalService::FESTIVALS['Ashvina Sharad Navaratri Day 8 (Mahagauri Puja)']['aliases']);
        self::assertContains('Maha Navami', FestivalService::FESTIVALS['Ashvina Sharad Navaratri Day 9 (Siddhidatri Puja)']['aliases']);
        self::assertContains('Maha Navami (Siddhidatri Puja)', FestivalService::FESTIVALS['Ashvina Sharad Navaratri Day 9 (Siddhidatri Puja)']['aliases']);
        self::assertContains('Ashvina Sharad Navaratri Day 9', FestivalService::FESTIVALS['Ashvina Sharad Navaratri Day 9 (Siddhidatri Puja)']['aliases']);
        self::assertSame(8, FestivalService::FESTIVALS['Ashvina Sharad Navaratri Day 8 (Mahagauri Puja)']['tithi']);
        self::assertSame(9, FestivalService::FESTIVALS['Ashvina Sharad Navaratri Day 9 (Siddhidatri Puja)']['tithi']);
        self::assertSame('sharad', FestivalService::FESTIVALS['Ashvina Sharad Navaratri Day 8 (Mahagauri Puja)']['navratri_type']);
        self::assertSame('sharad', FestivalService::FESTIVALS['Ashvina Sharad Navaratri Day 9 (Siddhidatri Puja)']['navratri_type']);
        self::assertContains('Diwali Lakshmi Puja', FestivalService::FESTIVALS['Lakshmi Puja (Deepavali)']['aliases']);
        self::assertContains('Chhath Puja (Surya Shashthi)', FestivalService::FESTIVALS['Chhath Puja (Sandhya Arghya)']['aliases']);
        self::assertContains('Kanda Sashti (Soorasamharam)', FestivalService::FESTIVALS['Skanda Sashti']['aliases']);
        self::assertContains('Skanda Shashti Vratam', FestivalService::FESTIVALS['Skanda Sashti']['aliases']);
        self::assertSame('Kartika', FestivalService::FESTIVALS['Skanda Sashti']['month_amanta']);
        self::assertSame('Kartika', FestivalService::FESTIVALS['Skanda Sashti']['month_purnimanta']);
        self::assertSame('sunset', FestivalService::FESTIVALS['Skanda Sashti']['karmakala_type']);
        self::assertTrue(FestivalService::FESTIVALS['Skanda Sashti']['strict_karmakala']);
        self::assertTrue(FestivalService::FESTIVALS['Skanda Sashti']['panchami_viddha_allowed']);
        self::assertSame('pradosha', FestivalService::FESTIVALS['Narasimha Jayanti']['karmakala_type']);
        self::assertTrue(FestivalService::FESTIVALS['Narasimha Jayanti']['trayodashi_viddha_rejection']);
        self::assertTrue(FestivalService::FESTIVALS['Narasimha Jayanti']['kshaya_accept_previous_tithi_vedha']);
        self::assertContains('Telugu Hanuman Vratam', FestivalService::FESTIVALS['Telugu Hanuman Jayanti']['aliases']);
        self::assertContains('Telugu Hanuman Jayanthi', FestivalService::FESTIVALS['Telugu Hanuman Jayanti']['aliases']);
        self::assertTrue(FestivalService::FESTIVALS['Telugu Hanuman Jayanti']['require_sunrise_vyapini']);
        self::assertArrayHasKey('Phuldolotsava', FestivalService::FESTIVALS);
        self::assertSame('special', FestivalService::FESTIVALS['Phuldolotsava']['type']);
        self::assertSame('phuldolotsava', FestivalService::FESTIVALS['Phuldolotsava']['resolver']);
        self::assertSame('phuldolotsava', FestivalService::FESTIVALS['Phuldolotsava']['family']);
        self::assertSame('Krishna', FestivalService::FESTIVALS['Phuldolotsava']['paksha']);
        self::assertSame(1, FestivalService::FESTIVALS['Phuldolotsava']['tithi']);
        self::assertSame('Phalguna', FestivalService::FESTIVALS['Phuldolotsava']['month_amanta']);
        self::assertSame('Chaitra', FestivalService::FESTIVALS['Phuldolotsava']['month_purnimanta']);
        self::assertSame(['Phalguna', 'Chaitra'], FestivalService::FESTIVALS['Phuldolotsava']['allowed_months_purnimanta']);
        self::assertSame('Uttara Phalguni', FestivalService::FESTIVALS['Phuldolotsava']['nakshatra']);
        self::assertTrue(FestivalService::FESTIVALS['Phuldolotsava']['require_sunrise_nakshatra']);
        self::assertSame(
            'if_purnima_and_pratipada_both_have_sunrise_uttara_phalguni_choose_purnima',
            FestivalService::FESTIVALS['Phuldolotsava']['dual_day_rule']
        );
        self::assertSame(
            [
                ['paksha' => 'Shukla', 'tithi' => 15],
                ['paksha' => 'Krishna', 'tithi' => 1],
            ],
            FestivalService::FESTIVALS['Phuldolotsava']['candidate_tithis']
        );
        self::assertSame('Satsangi Jeevan 4.60', FestivalService::FESTIVALS['Phuldolotsava']['source_refs'][0]);
        self::assertSame(['Satsangi Jeevan 4.59'], FestivalService::FESTIVALS['Phuldolotsava']['context_refs']);
        self::assertTrue(FestivalService::FESTIVALS['Phuldolotsava']['sect_specific']);
        self::assertTrue(FestivalService::FESTIVALS['Samaveda Upakarma']['nakshatra_only']);
        self::assertArrayNotHasKey('Chaitra Navratri Ghatasthapana', FestivalService::FESTIVALS);
        self::assertArrayNotHasKey('Sharad Navratri Ghatasthapana', FestivalService::FESTIVALS);
        self::assertArrayNotHasKey('Diwali Lakshmi Puja', FestivalService::FESTIVALS);
        self::assertArrayNotHasKey('Chhath Puja (Surya Shashthi)', FestivalService::FESTIVALS);
        self::assertArrayNotHasKey('Kanda Sashti (Soorasamharam)', FestivalService::FESTIVALS);
        self::assertArrayNotHasKey('Narasimha Jayanti (Swaminarayan/Satsangi)', FestivalService::FESTIVALS);
        self::assertArrayNotHasKey('Narasimha Jayanti (Smarta/Nirnaya)', FestivalService::FESTIVALS);
        self::assertArrayNotHasKey('Parashurama Jayanti (Pradosha Tradition)', FestivalService::FESTIVALS);
        self::assertArrayNotHasKey('Telugu Hanuman Jayanthi', FestivalService::FESTIVALS);
        self::assertArrayNotHasKey('Balarama Jayanti (Garga Samhita)', FestivalService::FESTIVALS);
    }

    public function testNamedVratsCarryFamilyAndClassifierMetadata(): void
    {
        self::assertSame('sankashti_chaturthi', FestivalService::FESTIVALS['Bhalachandra Sankashti Chaturthi']['family']);
        self::assertSame('Bhalachandra Sankashti Chaturthi', FestivalService::FESTIVALS['Bhalachandra Sankashti Chaturthi']['name_classifier']);
        self::assertTrue(FestivalService::FESTIVALS['Bhalachandra Sankashti Chaturthi']['sankashti_truth_table']);

        self::assertSame('pradosh_vrat', FestivalService::FESTIVALS['Pradosh Vrat']['family']);
        self::assertTrue(FestivalService::FESTIVALS['Pradosh Vrat']['weekday_classifier_after_resolution']);

        self::assertSame('ekadashi_vrat', FestivalService::FESTIVALS['Papmochani Ekadashi']['family']);
        self::assertSame('Papmochani Ekadashi', FestivalService::FESTIVALS['Papmochani Ekadashi']['name_classifier']);
        self::assertTrue(FestivalService::FESTIVALS['Papmochani Ekadashi']['ekadashi_nirnay_table']);

        self::assertSame('masik_krishna_janmashtami', FestivalService::FESTIVALS['Masik Krishna Janmashtami']['family']);
        self::assertSame('Krishna Janmashtami', FestivalService::FESTIVALS['Masik Krishna Janmashtami']['inherit_decision_from']);
        self::assertTrue(FestivalService::FESTIVALS['Masik Krishna Janmashtami']['masik_janmashtami_truth_table']);

        self::assertSame('prabodhini_ekadashi_related', FestivalService::FESTIVALS['Dharmadev Janmotsav']['family']);
        self::assertSame('external_or_sect_specific_not_named_in_doc', FestivalService::FESTIVALS['Dharmadev Janmotsav']['document_status']);
        self::assertSame('prabodhini_ekadashi_related', FestivalService::FESTIVALS['Hatadi Festival']['family']);
        self::assertSame('named_in_nirnay_document', FestivalService::FESTIVALS['Hatadi Festival']['document_status']);
    }

    public function testNakshatraOnlyResolverUsesKarmakalaWindowOverlap(): void
    {
        $engine = new FestivalRuleEngine;
        $date = CarbonImmutable::parse('2026-08-28');
        $today = $this->festivalSnapshot(15, 'Shukla', 100.25, 100.75, 101.25, 100.0, 101.0, 'Chitra');
        $today['Hindu_Calendar'] = ['Month_Amanta_En' => 'Bhadrapada'];
        $today['Nakshatra_Windows'] = [
            ['name' => 'Hasta', 'start_jd' => 100.55, 'end_jd' => 100.63],
        ];
        $tomorrow = $this->festivalSnapshot(1, 'Krishna', 101.25, 101.75, 102.25, 101.0, 102.0, 'Chitra');
        $tomorrow['Hindu_Calendar'] = ['Month_Amanta_En' => 'Bhadrapada'];
        $tomorrow['Nakshatra_Windows'] = [];

        $resolved = $engine->resolveNakshatraBasedFestival('Samaveda Upakarma', [
            'nakshatra_only' => true,
            'nakshatra' => 'Hasta',
            'allowed_months_amanta' => ['Bhadrapada'],
            'karmakala_type' => 'aparahna',
        ], $date, $today, $tomorrow);

        self::assertNotNull($resolved);
        self::assertSame('2026-08-28', $resolved['observance_date']);
        self::assertSame('nakshatra_overlaps_karmakala_window', $resolved['decision']['winning_reason']);
        self::assertGreaterThan(0, $resolved['decision']['winning_nakshatra_window_overlap_seconds']);
    }

    public function testPhuldolotsavaCarriesKrishnaPratipadaAndUttaraPhalguniMetadata(): void
    {
        $rule = FestivalService::FESTIVALS['Phuldolotsava'];

        self::assertSame('special', $rule['type']);
        self::assertSame('phuldolotsava', $rule['resolver']);
        self::assertSame('Krishna', $rule['paksha']);
        self::assertSame(1, $rule['tithi']);
        self::assertSame('Phalguna', $rule['month_amanta']);
        self::assertSame('Chaitra', $rule['month_purnimanta']);
        self::assertSame('Uttara Phalguni', $rule['nakshatra']);
        self::assertTrue($rule['require_sunrise_nakshatra']);
        self::assertTrue(FestivalService::usesClassicalResolver($rule));
    }

    public function testPhuldolotsavaResolverPrefersPurnimaWhenBothHaveSunriseUttaraPhalguni(): void
    {
        $engine = new FestivalRuleEngine;
        $date = CarbonImmutable::parse('2026-03-03');
        $today = $this->festivalSnapshot(15, 'Shukla', 100.25, 100.75, 101.25, 100.20, 101.10, 'Uttara Phalguni');
        $today['Hindu_Calendar'] = ['Month_Amanta_En' => 'Phalguna', 'Calendar_Type' => 'amanta'];
        $today['Nakshatra_At_Sunrise'] = ['name' => 'Uttara Phalguni'];
        $today['Tithi_At_Sunrise'] = ['index' => 15, 'paksha' => 'Shukla'];

        $tomorrow = $this->festivalSnapshot(16, 'Krishna', 101.25, 101.75, 102.25, 101.10, 101.90, 'Uttara Phalguni');
        $tomorrow['Hindu_Calendar'] = ['Month_Amanta_En' => 'Phalguna', 'Calendar_Type' => 'amanta'];
        $tomorrow['Nakshatra_At_Sunrise'] = ['name' => 'Uttara Phalguni'];
        $tomorrow['Tithi_At_Sunrise'] = ['index' => 16, 'paksha' => 'Krishna'];

        $resolved = $engine->resolveMajorFestival('Phuldolotsava', FestivalService::FESTIVALS['Phuldolotsava'], $date, $today, $tomorrow);

        self::assertIsArray($resolved);
        self::assertSame('2026-03-03', $resolved['observance_date']);
        self::assertSame('Shukla', $resolved['paksha']);
        self::assertSame(15, $resolved['required_tithi']);
        self::assertSame(
            'phuldolotsava_both_have_sunrise_uttara_phalguni_choose_purnima',
            $resolved['decision']['winning_reason']
        );
    }

    public function testPhuldolotsavaResolverFallsBackToPratipadaWithoutSunriseUttaraPhalguni(): void
    {
        $engine = new FestivalRuleEngine;
        $date = CarbonImmutable::parse('2026-03-03');
        $today = $this->festivalSnapshot(15, 'Shukla', 100.25, 100.75, 101.25, 100.20, 101.10, 'Magha');
        $today['Hindu_Calendar'] = ['Month_Amanta_En' => 'Phalguna', 'Calendar_Type' => 'amanta'];
        $today['Nakshatra_At_Sunrise'] = ['name' => 'Magha'];
        $today['Tithi_At_Sunrise'] = ['index' => 15, 'paksha' => 'Shukla'];

        $tomorrow = $this->festivalSnapshot(16, 'Krishna', 101.25, 101.75, 102.25, 101.10, 101.90, 'Purva Phalguni');
        $tomorrow['Hindu_Calendar'] = ['Month_Amanta_En' => 'Phalguna', 'Calendar_Type' => 'amanta'];
        $tomorrow['Nakshatra_At_Sunrise'] = ['name' => 'Purva Phalguni'];
        $tomorrow['Tithi_At_Sunrise'] = ['index' => 16, 'paksha' => 'Krishna'];

        $resolved = $engine->resolveMajorFestival('Phuldolotsava', FestivalService::FESTIVALS['Phuldolotsava'], $date, $today, $tomorrow);

        self::assertIsArray($resolved);
        self::assertSame('2026-03-04', $resolved['observance_date']);
        self::assertSame('Krishna', $resolved['paksha']);
        self::assertSame(1, $resolved['required_tithi']);
        self::assertSame(
            'phuldolotsava_fallback_pratipada_without_sunrise_uttara_phalguni',
            $resolved['decision']['winning_reason']
        );
    }

    public function testFestivalRuleEngineRejectsUnknownKarmakalaType(): void
    {
        $engine = new FestivalRuleEngine;
        $date = CarbonImmutable::parse('2026-01-01');
        $today = $this->festivalSnapshot(4, 'Shukla', 100.25, 100.75, 101.25, 100.50, 100.60, 'Hasta');
        $tomorrow = $this->festivalSnapshot(5, 'Shukla', 101.25, 101.75, 102.25, 101.30, 101.80, 'Chitra');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Unknown karmakala_type 'ashtami_navami_sandhi'");

        $engine->resolveMajorFestival('Sandhi Puja', [
            'type' => 'tithi',
            'paksha' => 'Shukla',
            'tithi' => 8,
            'karmakala_type' => 'ashtami_navami_sandhi',
        ], $date, $today, $tomorrow);
    }

    public function testFestivalRuleEngineRejectsInvalidGrowthPreference(): void
    {
        $engine = new FestivalRuleEngine;
        $date = CarbonImmutable::parse('2026-01-01');
        $today = $this->festivalSnapshot(3, 'Shukla', 100.25, 100.75, 101.25, 100.20, 101.40, 'Ashwini');
        $tomorrow = $this->festivalSnapshot(3, 'Shukla', 101.25, 101.75, 102.25, 100.20, 101.40, 'Bharani');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Invalid vriddhi_preference for Parashurama Jayanti');

        $engine->resolveMajorFestival('Parashurama Jayanti', [
            'type' => 'tithi',
            'paksha' => 'Shukla',
            'tithi' => 3,
            'karmakala_type' => 'madhyahna',
            'vriddhi_preference' => 'second',
        ], $date, $today, $tomorrow);
    }

    public function testMoonriseKarmakalaUsesMoonrisePoint(): void
    {
        $engine = new FestivalRuleEngine;
        $date = CarbonImmutable::parse('2026-10-10');
        $today = $this->festivalSnapshot(19, 'Krishna', 100.25, 100.75, 101.25, 100.60, 100.61, 'Rohini', null, 100.605);
        $tomorrow = $this->festivalSnapshot(20, 'Krishna', 101.25, 101.75, 102.25, 101.30, 101.90, 'Mrigashira', null, 101.60);

        $resolved = $engine->resolveMajorFestival('Karva Chauth', [
            'type' => 'tithi',
            'paksha' => 'Krishna',
            'tithi' => 4,
            'karmakala_type' => 'moonrise',
            'strict_karmakala' => true,
        ], $date, $today, $tomorrow);

        self::assertIsArray($resolved);
        self::assertSame('2026-10-10', $resolved['observance_date']);
        self::assertSame('moonrise', $resolved['karmakala_type']);
        self::assertTrue($resolved['tithi_at_karmakala_today']);
    }

    public function testTithiOptionsCanFallbackToAlternateConfiguredTithi(): void
    {
        $engine = new FestivalRuleEngine;
        $date = CarbonImmutable::parse('2026-07-10');
        $today = $this->festivalSnapshot(17, 'Krishna', 100.25, 100.75, 101.25, 100.30, 101.10, 'Rohini');
        $tomorrow = $this->festivalSnapshot(18, 'Krishna', 101.25, 101.75, 102.25, 101.10, 101.90, 'Mrigashira');

        $resolved = $engine->resolveMajorFestival('Hindola Festival Begins', [
            'type' => 'tithi',
            'paksha' => 'Krishna',
            'tithi' => 1,
            'tithi_options' => [1, 2],
            'karmakala_type' => 'sayankala',
        ], $date, $today, $tomorrow);

        self::assertIsArray($resolved);
        self::assertSame(2, $resolved['required_tithi']);
        self::assertSame('2026-07-10', $resolved['observance_date']);
    }

    public function testCalculationBasisExportsResearchMetadataFields(): void
    {
        $service = (new ReflectionClass(FestivalService::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(FestivalService::class, 'buildCalculationBasis');

        $basis = $method->invoke($service, [
            'type' => 'tithi',
            'paksha' => 'Shukla',
            'tithi' => 3,
            'karmakala_type' => 'madhyahna',
            'source_evidence' => [
                [
                    'kind' => 'date_rule',
                    'source' => 'Satsangi Jeevan',
                    'locator' => 'foo',
                    'supports' => 'bar',
                ],
            ],
            'textual_variants' => ['bar'],
            'resolver_compatibility' => 'partial',
            'unresolved_conditions' => ['baz'],
            'family' => 'sankashti_chaturthi',
            'name_classifier' => 'Bhalachandra Sankashti Chaturthi',
            'inherit_decision_from' => 'Sankashti Chaturthi',
            'document_status' => 'generic_family_rule_named_classifier',
            'dual_day_rule' => 'if_purnima_and_pratipada_both_have_sunrise_uttara_phalguni_choose_purnima',
            'strict_dashami_vedha' => true,
        ], null);

        self::assertSame('date_rule', $basis['source_evidence'][0]['kind']);
        self::assertSame('date-rule evidence', $basis['source_evidence'][0]['kind_name']);
        self::assertSame('Satsangi Jeevan', $basis['source_evidence'][0]['source']);
        self::assertSame('foo', $basis['source_evidence'][0]['locator']);
        self::assertSame('bar', $basis['source_evidence'][0]['supports']);
        self::assertSame(['bar'], $basis['textual_variants']);
        self::assertSame('partial', $basis['resolver_compatibility']);
        self::assertSame(['baz'], $basis['unresolved_conditions']);
        self::assertSame(['baz'], $basis['unresolved_condition_keys']);
        self::assertSame('sankashti_chaturthi', $basis['family_key']);
        self::assertSame('Bhalachandra Sankashti Chaturthi', $basis['name_classifier_key']);
        self::assertSame('Sankashti Chaturthi', $basis['inherit_decision_from_key']);
        self::assertSame('generic_family_rule_named_classifier', $basis['document_status_key']);
        self::assertSame('if_purnima_and_pratipada_both_have_sunrise_uttara_phalguni_choose_purnima', $basis['dual_day_rule_key']);
        self::assertTrue($basis['strict_dashami_vedha']);
    }

    public function testVerifiedEclipseRitualMagnitudeThresholdsAreEncoded(): void
    {
        $reflection = new ReflectionClass(EclipseService::class);
        $lunarMinimum = $reflection->getReflectionConstant('NIRNAY_LUNAR_ECLIPSE_MINIMUM_MAGNITUDE');
        $solarMinimum = $reflection->getReflectionConstant('NIRNAY_SOLAR_ECLIPSE_MINIMUM_MAGNITUDE');

        self::assertNotFalse($lunarMinimum);
        self::assertNotFalse($solarMinimum);

        self::assertEqualsWithDelta(
            1.0 / 16.0,
            $lunarMinimum->getValue(),
            1e-12
        );
        self::assertEqualsWithDelta(
            1.0 / 12.0,
            $solarMinimum->getValue(),
            1e-12
        );
    }

    /** @return array<string, array<string, mixed>> */
    private function expandedFestivalRules(): array
    {
        $reflection = new ReflectionClass(FestivalService::class);
        $method = $reflection->getMethod('expandFestivalRules');

        /** @var FestivalService $service */
        $service = $reflection->newInstanceWithoutConstructor();

        /** @var array<string, array<string, mixed>> $expanded */
        $expanded = $method->invoke($service);

        return $expanded;
    }

    private function paranaCalculatorForWindowResolution(): EkadashiParanaCalculator
    {
        $reflection = new ReflectionClass(EkadashiParanaCalculator::class);
        /** @var EkadashiParanaCalculator $calculator */
        $calculator = $reflection->newInstanceWithoutConstructor();
        /** @var MockObject&SunService $sunService */
        $sunService = $this->createMock(SunService::class);
        $sunService
            ->method('jdToCarbonPublic')
            ->willReturnCallback(
                static fn (float $jd, string $tz): CarbonImmutable => CarbonImmutable::createFromTimestampUTC((int) round($jd * 86400))
                    ->setTimezone($tz)
            );

        $sunServiceProperty = $reflection->getProperty('sunService');
        $sunServiceProperty->setValue($calculator, $sunService);

        return $calculator;
    }

    private function festivalSnapshot(
        int $tithiAbs,
        string $paksha,
        float $sunriseJd,
        float $sunsetJd,
        float $nextSunriseJd,
        float $tithiStartJd,
        float $tithiEndJd,
        string $nakshatra,
        ?float $moonsetJd = null,
        ?float $moonriseJd = null
    ): array {
        $snapshot = [
            'Tithi' => [
                'index' => $tithiAbs > 15 ? $tithiAbs - 15 : $tithiAbs,
                'paksha' => $paksha,
            ],
            'Nakshatra' => [
                'name' => $nakshatra,
            ],
            'Resolution_Context' => [
                'tithi_index_abs' => $tithiAbs,
                'tithi_start_jd' => $tithiStartJd,
                'tithi_end_jd' => $tithiEndJd,
                'prev_tithi_end_jd' => $tithiStartJd,
                'sunrise_jd' => $sunriseJd,
                'sunset_jd' => $sunsetJd,
                'next_sunrise_jd' => $nextSunriseJd,
                'moon_sun_elongation_at_sunset_degrees' => 12.0,
                'moon_illumination_at_sunset_percent' => 1.1,
            ],
        ];

        if ($moonsetJd !== null) {
            $snapshot['Moonset_JD'] = $moonsetJd;
        }

        if ($moonriseJd !== null) {
            $snapshot['Moonrise_JD'] = $moonriseJd;
        }

        return $snapshot;
    }
}
