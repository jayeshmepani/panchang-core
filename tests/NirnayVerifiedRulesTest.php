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
use JayeshMepani\PanchangCore\Panchanga\Vrata\EkadashiParanaCalculator;
use JayeshMepani\PanchangCore\Traits\CliBootstrap;
use JmeEph\FFI\JmeEphFFI;
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
            $previousSunriseJd
        );

        $viddha = $engine->determineEkadashi(
            $ekadashiStartJd,
            $ekadashiEndJd,
            100.94,
            $dvadashiStartJd,
            $sunriseJd,
            $nextSunriseJd,
            'Vaishnava',
            $previousSunriseJd
        );

        self::assertSame(55.0, $clean['dashami_vedha_threshold_ghatikas_from_previous_sunrise']);
        self::assertEqualsWithDelta(100.9166666667, $clean['dashami_vedha_threshold_jd'], 1e-10);
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
        self::assertEqualsWithDelta(120.0, $result['arunodaya_minutes'], 1e-10);
        self::assertEqualsWithDelta(100.9166666667, $result['arunodaya_jd'], 1e-10);
        self::assertSame('fixed_ghati_elapsed_before_dynamic_local_sunrise', $result['arunodaya_basis']);
        self::assertSame(24.0, $result['fixed_ghati_minutes']);
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
            (float) $event['visibility']['window']['start_jd'] - $this->containingPraharDurationJd(
                $service,
                (float) $event['visibility']['window']['start_jd'],
                23.2472446,
                69.668339,
                'Asia/Kolkata'
            ),
            $event['sutak']['relaxed_start_jd'] ?? null,
            1e-9,
            'Relaxed sutak should begin one variable prahara before local eclipse contact.'
        );
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

    public function testChandraDarshanaUsesSunsetToMoonsetVisibilityWindowForPratipada(): void
    {
        $engine = new FestivalRuleEngine;
        $date = CarbonImmutable::parse('2026-06-16');
        $today = $this->festivalSnapshot(1, 'Shukla', 100.25, 100.75, 101.25, 100.60, 101.10, 'Mrigashira', 100.82);
        $tomorrow = $this->festivalSnapshot(2, 'Shukla', 101.25, 101.75, 102.25, 101.10, 102.00, 'Ardra', 101.90);

        $resolved = $engine->resolveMajorFestival('Chandra Darshana', FestivalService::FESTIVALS['Chandra Darshana'], $date, $today, $tomorrow);

        self::assertNotNull($resolved);
        self::assertSame('2026-06-16', $resolved['observance_date']);
        self::assertSame(1, $resolved['required_tithi']);
        self::assertSame('chandra_darshana_pratipada_visible_after_sunset', $resolved['decision']['winning_reason']);
        self::assertGreaterThan(0, $resolved['decision']['moon_visibility_seconds']);

        $payload = (new FestivalService($engine))->buildFestivalPayload('Chandra Darshana', FestivalService::FESTIVALS['Chandra Darshana'], $resolved);
        self::assertSame('chandra_darshana_visibility', $payload['calculation_basis']['karmakala_type'] ?? null);
        self::assertSame('sunset to moonset visibility', $payload['calculation_basis']['karmakala_type_name'] ?? null);
        self::assertSame('chandra_darshana_pratipada_visible_after_sunset', $payload['rules_applied']['winning_reason_key'] ?? null);
        self::assertSame('Pratipada visible after sunset before moonset', $payload['rules_applied']['winning_reason'] ?? null);
    }

    public function testChandraDarshanaFallsBackToDwitiyaWhenPratipadaMissesEveningVisibility(): void
    {
        $engine = new FestivalRuleEngine;
        $date = CarbonImmutable::parse('2026-07-15');
        $today = $this->festivalSnapshot(1, 'Shukla', 100.25, 100.75, 101.25, 100.20, 100.50, 'Punarvasu', 100.77);
        $tomorrow = $this->festivalSnapshot(2, 'Shukla', 101.25, 101.75, 102.25, 100.50, 101.70, 'Pushya', 101.95);

        $resolved = $engine->resolveMajorFestival('Chandra Darshana', FestivalService::FESTIVALS['Chandra Darshana'], $date, $today, $tomorrow);

        self::assertNotNull($resolved);
        self::assertSame('2026-07-16', $resolved['observance_date']);
        self::assertSame(2, $resolved['required_tithi']);
        self::assertSame('chandra_darshana_dwitiya_visible_after_sunset', $resolved['decision']['winning_reason']);
        self::assertTrue($resolved['decision']['visibility_assessment']['visible']);
        self::assertTrue($resolved['decision']['visibility_assessment']['passes_lag']);
    }

    public function testChandraDarshanaRejectsTooShortPratipadaVisibilityWindow(): void
    {
        $engine = new FestivalRuleEngine;
        $date = CarbonImmutable::parse('2026-08-13');
        $today = $this->festivalSnapshot(1, 'Shukla', 100.25, 100.75, 101.25, 100.10, 100.95, 'Ashlesha', 100.772);
        $tomorrow = $this->festivalSnapshot(2, 'Shukla', 101.25, 101.75, 102.25, 100.95, 101.60, 'Magha', 101.84);

        $resolved = $engine->resolveMajorFestival('Chandra Darshana', FestivalService::FESTIVALS['Chandra Darshana'], $date, $today, $tomorrow);

        self::assertNotNull($resolved);
        self::assertSame('2026-08-14', $resolved['observance_date']);
        self::assertSame(2, $resolved['required_tithi']);
        self::assertSame('chandra_darshana_dwitiya_visible_after_sunset', $resolved['decision']['winning_reason']);
        self::assertTrue($resolved['decision']['visibility_assessment']['visible']);
        self::assertTrue($resolved['decision']['visibility_assessment']['passes_lag']);
        self::assertSame(0.0, $resolved['decision']['winning_window_overlap_seconds']);
    }

    public function testGujaratiFestivalRegistryCorrectionsAreEncoded(): void
    {
        self::assertSame('sangava', FestivalService::FESTIVALS['Kali Chaudas (Naraka Chaturdashi)']['karmakala_type']);
        self::assertArrayHasKey('Naraka Chaturdashi Abhyanga Snan', FestivalService::FESTIVALS);
        self::assertSame('arunodaya', FestivalService::FESTIVALS['Naraka Chaturdashi Abhyanga Snan']['karmakala_type']);
        self::assertTrue(FestivalService::FESTIVALS['Naraka Chaturdashi Abhyanga Snan']['location_sensitive']);
        self::assertSame('sunrise', FestivalService::FESTIVALS['Chaitra Purnima']['karmakala_type']);
        self::assertSame('sunrise', FestivalService::FESTIVALS['Swaminarayan Jayanti (Hari-Nom)']['karmakala_type']);
        self::assertTrue(FestivalService::FESTIVALS['Swaminarayan Jayanti (Hari-Nom)']['require_sunrise_vyapini']);
        self::assertSame('first', FestivalService::FESTIVALS['Swaminarayan Jayanti (Hari-Nom)']['vriddhi_preference']);
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
        $expected = [
            'Ramanand Swami Appearance Festival',
            'Chandrayan Vrat',
            'Nara-Narayan Arjun Janmotsav',
            'Dharmadev Janmotsav',
            'Hatadi Festival',
            'Swaminarayan Kurma Jayanti',
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

        self::assertSame('nishitha', FestivalService::FESTIVALS['Ramanand Swami Appearance Festival']['karmakala_type']);
        self::assertSame('janmashtami_uddhav', FestivalService::FESTIVALS['Ramanand Swami Appearance Festival']['ritual_profile']);
        self::assertSame(14, FestivalService::FESTIVALS['Chandrayan Vrat']['tithi']);
        self::assertSame('madhyahna', FestivalService::FESTIVALS['Nara-Narayan Arjun Janmotsav']['karmakala_type']);
        self::assertSame('Uttara Phalguni', FestivalService::FESTIVALS['Nara-Narayan Arjun Janmotsav']['nakshatra']);
        self::assertSame('Jyeshtha', FestivalService::FESTIVALS['Snanyatra']['nakshatra']);
        self::assertTrue(FestivalService::FESTIVALS['Snanyatra']['nakshatra_only']);
        self::assertSame('Pushya', FestivalService::FESTIVALS['Swaminarayan Rathyatra']['nakshatra']);
        self::assertSame([1, 2], FestivalService::FESTIVALS['Hindola Festival Begins']['tithi_options']);
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
                'Swaminarayan Kurma Jayanti',
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
                'Swaminarayan Kurma Jayanti rule on Vaishakha Shukla Pratipada',
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
                'simplified_modern_crescent_visibility',
                'modern_astronomical_heuristic_not_classical',
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
        $fixedGhati = 24.0 / 1440.0;

        $windows = [
            'arunodaya' => [100.25 - (4.0 * $fixedGhati), 100.25],
            'pratah_kal' => [100.25, 100.25 + ($dayDuration / 5.0)],
            'sangava' => [100.25 + ($dayDuration / 5.0), 100.25 + ($dayDuration * 2.0 / 5.0)],
            'madhyahna' => [100.25 + ($dayDuration * 2.0 / 5.0), 100.25 + ($dayDuration * 3.0 / 5.0)],
            'abhijit' => [100.25 + (7.0 * $dayMuhurta), 100.25 + (8.0 * $dayMuhurta)],
            'aparahna' => [100.25 + ($dayDuration * 3.0 / 5.0), 100.25 + ($dayDuration * 4.0 / 5.0)],
            'vijaya_kaal' => [100.25 + (10.0 * $dayMuhurta), 100.25 + (11.0 * $dayMuhurta)],
            'sayankala' => [100.25 + ($dayDuration * 4.0 / 5.0), 100.85],
            'sunset' => [100.85 - $fixedGhati, 100.85 + (2.0 * $fixedGhati)],
            'nishitha' => [100.85 + ($nightDuration / 2.0) - ($nightMuhurta / 2.0), 100.85 + ($nightDuration / 2.0) + ($nightMuhurta / 2.0)],
            'pradosha' => [100.85, 100.85 + (6.0 * $fixedGhati)],
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
        self::assertSame('mahashivaratri_both_full_nishitha_choose_day2', $resolved['decision']['winning_reason']);
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

    public function testRemainingGujaratiFestivalRuleFlagsAreEncoded(): void
    {
        self::assertTrue(FestivalService::FESTIVALS['Holika Dahan']['avoid_bhadra_mukha']);
        self::assertTrue(FestivalService::FESTIVALS['Holika Dahan']['prefer_bhadra_puchha']);
        self::assertTrue(FestivalService::FESTIVALS['Holika Dahan']['holika_lunar_eclipse_exception']);
        self::assertArrayNotHasKey('reject_weekday_nakshatra', FestivalService::FESTIVALS['Mahavir Jayanti']);
        self::assertSame(['Magha'], FestivalService::FESTIVALS['Masik Shivaratri']['excluded_months_amanta']);
        self::assertTrue(FestivalService::FESTIVALS['Krishna Janmashtami']['janmashtami_truth_table']);
        self::assertTrue(FestivalService::FESTIVALS['Ganesh Chaturthi']['prefer_full_karmakala_coverage']);
        self::assertSame('prefer_full_madhyahna_chaturthi_coverage_over_partial_previous_overlap', FestivalService::FESTIVALS['Ganesh Chaturthi']['gujarati_special_case']);
        self::assertTrue(FestivalService::FESTIVALS['Vasant Panchami']['require_sunrise_vyapini']);
        self::assertSame(['Satsangi Jeevan 4.59.31-58'], FestivalService::FESTIVALS['Vasant Panchami']['source_refs']);
        self::assertSame('matsya_jayanti_satsangi', FestivalService::FESTIVALS['Matsya Jayanti']['ritual_profile']);
        self::assertSame('parashurama_jayanti_satsangi', FestivalService::FESTIVALS['Parashurama Jayanti']['ritual_profile']);
        self::assertSame('sharad_purnima_rasa', FestivalService::FESTIVALS['Kojagari Lakshmi Puja']['ritual_profile']);
        self::assertTrue(FestivalService::FESTIVALS['Maha Shivaratri']['ekadesha_coverage_allowed']);
        self::assertTrue(FestivalService::FESTIVALS['Maha Shivaratri']['mahashivaratri_truth_table']);
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
        self::assertArrayHasKey('Phuldolotsava', FestivalService::FESTIVALS);
        self::assertSame(15, FestivalService::FESTIVALS['Phuldolotsava']['tithi']);
        self::assertSame('Shukla', FestivalService::FESTIVALS['Phuldolotsava']['paksha']);
        self::assertSame('Phalguna', FestivalService::FESTIVALS['Phuldolotsava']['month_amanta']);
        self::assertTrue(FestivalService::FESTIVALS['Phuldolotsava']['sect_specific']);
        self::assertTrue(FestivalService::FESTIVALS['Samaveda Upakarma']['nakshatra_only']);
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

    private function containingPraharDurationJd(
        EclipseService $service,
        float $eventStartJd,
        float $lat,
        float $lon,
        string $tz
    ): float {
        $reflection = new ReflectionClass(EclipseService::class);
        $eventStart = $reflection->getMethod('jdToCarbon')->invoke($service, $eventStartJd, $tz);
        $boundaries = $reflection->getMethod('buildPraharBoundaries')->invoke($service, $eventStart, $lat, $lon, $tz);
        $eventTimestamp = $eventStart->getTimestamp();

        for ($index = 0, $count = count($boundaries) - 1; $index < $count; $index++) {
            $startTimestamp = $boundaries[$index]->getTimestamp();
            $endTimestamp = $boundaries[$index + 1]->getTimestamp();
            if ($eventTimestamp >= $startTimestamp && $eventTimestamp < $endTimestamp) {
                $carbonToJd = $reflection->getMethod('carbonToJd');

                return $carbonToJd->invoke($service, $boundaries[$index + 1])
                    - $carbonToJd->invoke($service, $boundaries[$index]);
            }
        }

        self::fail('Unable to find contact-containing prahara.');
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
        ?float $moonsetJd = null
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

        return $snapshot;
    }
}
