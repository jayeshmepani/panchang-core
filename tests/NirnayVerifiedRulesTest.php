<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Tests;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Astronomy\EclipseService;
use JayeshMepani\PanchangCore\Festivals\FestivalRuleEngine;
use JayeshMepani\PanchangCore\Festivals\FestivalService;
use JayeshMepani\PanchangCore\Panchanga\KalaNirnayaEngine;
use JayeshMepani\PanchangCore\Panchanga\Vrata\EkadashiParanaCalculator;
use JayeshMepani\PanchangCore\Traits\CliBootstrap;
use JmeEph\FFI\JmeEphFFI;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

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
            5.0
        );

        self::assertSame(5.0, $result['arunodaya_ghatikas']);
        self::assertSame(120.0, $result['arunodaya_minutes']);
        self::assertEqualsWithDelta(100.9166666667, $result['arunodaya_jd'], 1e-10);
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
            'Relaxed sutak start should align to previous dynamic prahara boundary.'
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
        self::assertSame('sunrise', FestivalService::FESTIVALS['Samaveda Upakarma']['karmakala_type']);
        self::assertTrue(FestivalService::FESTIVALS['Govardhan Puja']['chandradarshan_nishedh']);
        self::assertTrue(FestivalService::FESTIVALS['Govardhan Puja']['location_sensitive']);
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
        self::assertSame(1, FestivalService::FESTIVALS['Phuldolotsava']['tithi']);
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

    private function festivalSnapshot(
        int $tithiAbs,
        string $paksha,
        float $sunriseJd,
        float $sunsetJd,
        float $nextSunriseJd,
        float $tithiStartJd,
        float $tithiEndJd,
        string $nakshatra
    ): array {
        return [
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
            ],
        ];
    }
}
