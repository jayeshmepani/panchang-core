<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Tests;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Panchanga\KalaNirnayaEngine;
use JayeshMepani\PanchangCore\Panchanga\PanchangService;
use JayeshMepani\PanchangCore\PanchangServiceProvider;
use Orchestra\Testbench\TestCase;
use Override;
use PHPUnit\Framework\Attributes\Group;

#[Group('slow')]
class SpecialYogaRegressionTest extends TestCase
{
    /** @var array<string, array<string, mixed>> */
    private static array $dayDetailsCache = [];

    public function test_local_day_boundary_regression_keeps_vara_on_correct_civil_day(): void
    {
        $day = $this->getDayDetails('2026-05-21', 28.6139, 77.2090, 'Asia/Kolkata');

        $this->assertSame('Thursday', $day['Vara']['name'] ?? null);
        $this->assertSame('Thursday', $day['Special_Yogas']['summary']['weekday'] ?? null);
        $this->assertTrue($day['Special_Yogas']['sarvartha_siddhi']['is_present'] ?? false);
        $this->assertTrue($day['Special_Yogas']['amrit_siddhi']['is_present'] ?? false);
        $this->assertTrue($day['Special_Yogas']['guru_pushya']['is_present'] ?? false);
        $this->assertSame('South', $day['Disha_Shool']['direction_to_avoid'] ?? null);
    }

    /** @return array<string, array{0:string,1:string,2:string}> */
    public static function yogaDateProvider(): array
    {
        return [
            'tripushkar_window' => ['2026-05-03', 'tripushkar', 'Vishakha'],
            'jwalamukhi_window' => ['2025-12-20', 'jwalamukhi', 'Mula'],
            'ravi_yoga_window' => ['2026-04-25', 'ravi_yoga', 'Ashlesha'],
            'aadal_window' => ['2026-04-22', 'aadal', 'Punarvasu'],
            'vidaal_window' => ['2026-04-22', 'vidaal', 'Ardra'],
            'vinchhudo_window' => ['2026-05-03', 'vinchhudo', 'Vrischika'],
        ];
    }

    /** @dataProvider yogaDateProvider */
    public function test_special_yoga_regression_windows(string $date, string $key, string $expectedMarker): void
    {
        $day = $this->getDayDetails($date, 28.6139, 77.2090, 'Asia/Kolkata');
        $node = $day['Special_Yogas'][$key] ?? null;

        $this->assertIsArray($node);
        $this->assertTrue($node['is_present'] ?? false, $key . ' should be present on ' . $date);
        $this->assertGreaterThan(0, $node['window_count'] ?? 0);

        $windows = $node['windows'] ?? [];
        $this->assertIsArray($windows);
        $this->assertNotSame([], $windows);

        $encoded = json_encode($windows, JSON_UNESCAPED_SLASHES);
        $this->assertIsString($encoded);
        $this->assertStringContainsString($expectedMarker, $encoded);
    }

    public function test_krishna_ekadashi_phase_still_generates_ekadashi_reports(): void
    {
        $engine = new KalaNirnayaEngine(23.2472446, 69.668339);

        $report = $engine->generateKalaNirnayaReport(
            26,
            100.10,
            100.80,
            100.10,
            100.25,
            100.75,
            101.25
        );

        $this->assertIsArray($report['ekadashi_smarta'] ?? null);
        $this->assertIsArray($report['ekadashi_vaishnava'] ?? null);
        $this->assertSame('Smarta', $report['ekadashi_smarta']['tradition'] ?? null);
        $this->assertSame('Vaishnava', $report['ekadashi_vaishnava']['tradition'] ?? null);
    }

    public function test_full_day_details_expose_ekadashi_observance_under_dharma_sindhu(): void
    {
        $day = $this->getDayDetails('2026-01-14', 23.2472446, 69.668339, 'Asia/Kolkata');

        $this->assertSame('Krishna Ekadashi', $day['Tithi']['name'] ?? null);
        $observance = $day['Dharma_Sindhu']['Ekadashi_Observance'] ?? null;
        $this->assertIsArray($observance);
        $this->assertSame($observance, $day['Ekadashi_Observance'] ?? null);
        $this->assertSame(11, $observance['phase_tithi_number'] ?? null);
        $this->assertSame('Shuddha_Ekadashi', $observance['ekadashi_smarta']['status'] ?? null);
        $this->assertTrue($observance['parana']['parana_available'] ?? false);
    }

    public function test_additional_daily_indicators_are_present(): void
    {
        $day = $this->getDayDetails('2026-01-14', 23.2472446, 69.668339, 'Asia/Kolkata');

        $this->assertSame('sripati_jyotisha_ratnamala_28_nakshatra', $day['Anandadi_Yoga']['rule_system'] ?? null);
        $this->assertTrue($day['Anandadi_Yoga']['is_complete_system'] ?? false);
        $this->assertSame(28, $day['Anandadi_Yoga']['system_size'] ?? null);
        $this->assertNotEmpty($day['Anandadi_Yoga']['windows'] ?? []);
        $this->assertSame('amritadi_yoga_27_nakshatra_7_weekday', $day['Amritadi_Yoga']['rule_system'] ?? null);
        $this->assertTrue($day['Amritadi_Yoga']['is_complete_system'] ?? false);
        $this->assertSame(189, $day['Amritadi_Yoga']['system_size'] ?? null);
        $this->assertNotEmpty($day['Amritadi_Yoga']['windows'] ?? []);
        $this->assertSame('moon_dhanishta_pada_3_to_revati', $day['Panchak']['rule_system'] ?? null);
        $this->assertTrue($day['Panchak']['is_complete_system'] ?? false);
        $this->assertIsBool($day['Panchak']['is_present'] ?? null);
        $this->assertTrue($day['Gajachchhaya_Yoga']['is_complete_known_variant_set'] ?? false);
        $this->assertSame(3, $day['Gajachchhaya_Yoga']['variant_count'] ?? null);
        $this->assertArrayHasKey('pitru_paksha_bhadrapada_krishna_trayodashi', $day['Gajachchhaya_Yoga']['variants'] ?? []);
        $this->assertSame('simple_travel_nakshatra_direction_table', $day['Nakshatra_Shool']['rule_system'] ?? null);
        $this->assertTrue($day['Nakshatra_Shool']['is_complete_system'] ?? false);
        $this->assertIsBool($day['Nakshatra_Shool']['is_present'] ?? null);
        $this->assertSame('North', $day['Disha_Shool']['direction_to_avoid'] ?? null);
        $this->assertSame('weekday_rahu_direction_7_day', $day['Rahu_Vaasa']['rule_system'] ?? null);
        $this->assertTrue($day['Rahu_Vaasa']['is_complete_system'] ?? false);
        $this->assertSame('North-West', $day['Rahu_Vaasa']['direction'] ?? null);
        $this->assertSame('moon_rashi_direction_4_direction', $day['Chandra_Vaasa']['rule_system'] ?? null);
        $this->assertTrue($day['Chandra_Vaasa']['is_complete_system'] ?? false);
        $this->assertContains($day['Chandra_Vaasa']['direction_key'] ?? null, ['East', 'South', 'West', 'North']);
        $this->assertSame('nakshatra_pada_abode_4_part', $day['Chandra_Vaasa']['nakshatra_pada_vaasa']['rule_system'] ?? null);
        $this->assertContains($day['Chandra_Vaasa']['nakshatra_pada_vaasa']['current']['abode_key'] ?? null, ['Deva', 'Nara', 'Pashava', 'Rakshasa']);
        $this->assertSame('At Work / Play', $day['Shiva_Vaasa']['method_1']['state'] ?? null);
        $this->assertSame('At Kailash', $day['Shiva_Vaasa']['method_2']['state'] ?? null);
        $this->assertSame('Prithvi (Earth)', $day['Agni_Vaasa']['state'] ?? null);
        $this->assertSame('South-East', $day['Yogini_Vaasa']['direction'] ?? null);
    }

    public function test_moonrise_moonset_are_reported_as_visibility_interval_for_civil_date(): void
    {
        $expected = [
            '2026-04-05' => ['10:12:35 PM', '09:00:37 AM'],
            '2026-04-06' => ['11:07:41 PM', '09:46:06 AM'],
            '2026-04-07' => [null, null],
            '2026-04-08' => ['12:01:33 AM', '10:35:48 AM'],
            '2026-04-30' => ['06:16:04 PM', '05:41:58 AM'],
        ];

        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);

        foreach ($expected as $date => [$moonrise, $moonset]) {
            $snapshot = $service->getFestivalSnapshot(
                CarbonImmutable::parse($date, 'Asia/Kolkata'),
                23.2472446,
                69.668339,
                'Asia/Kolkata',
                includeExtended: false
            );

            $this->assertTimeWithinSeconds($moonrise, $snapshot['Moonrise'] ?? null, 120, $date . ' moonrise');
            $this->assertTimeWithinSeconds($moonset, $snapshot['Moonset'] ?? null, 120, $date . ' moonset');
        }
    }

    public function test_aadal_window_matches_independent_rule_example(): void
    {
        $day = $this->getDayDetails('2026-03-19', 28.6139, 77.2090, 'Asia/Kolkata');
        $node = $day['Special_Yogas']['aadal'] ?? null;

        $this->assertIsArray($node);
        $this->assertTrue($node['is_present'] ?? false);
        $this->assertCount(1, $node['windows'] ?? []);

        $window = $node['windows'][0];
        $this->assertSame('Revati', $window['nakshatra'] ?? null);
        $this->assertSame('Uttara Bhadrapada', $window['sun_nakshatra'] ?? null);
        $this->assertSame(2, $window['count_with_abhijit'] ?? null);
    }

    public function test_aadal_and_vidaal_sequence_matches_28_nakshatra_counting_example(): void
    {
        $day = $this->getDayDetails('2026-05-10', -33.8688, 151.2093, 'Australia/Sydney');

        $aadal = $day['Special_Yogas']['aadal'] ?? null;
        $vidaal = $day['Special_Yogas']['vidaal'] ?? null;

        $this->assertIsArray($aadal);
        $this->assertIsArray($vidaal);
        $this->assertTrue($aadal['is_present'] ?? false);
        $this->assertTrue($vidaal['is_present'] ?? false);

        $aadalWindow = $aadal['windows'][0];
        $vidaalWindow = $vidaal['windows'][0];

        $this->assertSame('Dhanishta', $aadalWindow['nakshatra'] ?? null);
        $this->assertSame(23, $aadalWindow['count_with_abhijit'] ?? null);
        $this->assertSame('Shatabhisha', $vidaalWindow['nakshatra'] ?? null);
        $this->assertSame(24, $vidaalWindow['count_with_abhijit'] ?? null);
    }

    #[Override]
    protected function getPackageProviders($app): array
    {
        return [PanchangServiceProvider::class];
    }

    /** @return array<string, mixed> */
    private function getDayDetails(string $date, float $lat, float $lon, string $tz): array
    {
        $cacheKey = $date . '|' . $lat . '|' . $lon . '|' . $tz;
        if (isset(self::$dayDetailsCache[$cacheKey])) {
            return self::$dayDetailsCache[$cacheKey];
        }

        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);

        return self::$dayDetailsCache[$cacheKey] = $service->getDayDetails(
            CarbonImmutable::parse($date, $tz),
            $lat,
            $lon,
            $tz
        );
    }

    private function assertTimeWithinSeconds(?string $expected, mixed $actual, int $toleranceSeconds, string $message): void
    {
        if ($expected === null) {
            $this->assertNull($actual, $message);

            return;
        }

        $this->assertIsString($actual, $message);

        $expectedTime = CarbonImmutable::createFromFormat('h:i:s A', $expected, 'Asia/Kolkata');
        $actualTime = CarbonImmutable::createFromFormat('h:i:s A', $actual, 'Asia/Kolkata');

        $this->assertNotFalse($expectedTime, $message);
        $this->assertNotFalse($actualTime, $message);
        $this->assertLessThanOrEqual(
            $toleranceSeconds,
            abs($expectedTime->diffInSeconds($actualTime, true)),
            $message
        );
    }
}
