<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Tests;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Core\Enums\CalendarType;
use JayeshMepani\PanchangCore\Panchanga\ElectionalEvaluator;
use JayeshMepani\PanchangCore\Panchanga\PanchangService;
use JayeshMepani\PanchangCore\PanchangServiceProvider;
use Orchestra\Testbench\TestCase;

class GeneralCalculativeRegressionTest extends TestCase
{
    public function testVaraTithiDoshaTableExposesActiveClassicalMatches(): void
    {
        $sunDagdha = ElectionalEvaluator::calculateVaraTithiDoshas(0, 12);
        self::assertTrue($sunDagdha['all']['dagdha']['is_present']);
        self::assertContains('dagdha', $sunDagdha['active_keys']);

        $sunVisha = ElectionalEvaluator::calculateVaraTithiDoshas(0, 4);
        self::assertTrue($sunVisha['all']['visha']['is_present']);
        self::assertContains('visha', $sunVisha['active_keys']);

        $mercurySamvarta = ElectionalEvaluator::calculateVaraTithiDoshas(3, 1);
        self::assertTrue($mercurySamvarta['all']['samvarta']['is_present']);
        self::assertContains('samvarta', $mercurySamvarta['active_keys']);

        $neutral = ElectionalEvaluator::calculateVaraTithiDoshas(1, 1);
        self::assertFalse($neutral['has_any_dosha']);
        self::assertSame([], $neutral['active_keys']);
    }

    public function testNityaYogaObservationsFlagKrantiDosha(): void
    {
        $vyatipata = ElectionalEvaluator::calculateNityaYogaObservations(17, 'Vyatipata');
        self::assertTrue($vyatipata['is_marriage_prohibited']);
        self::assertTrue($vyatipata['is_kranti_dosha']);
        self::assertSame('entire_yoga', $vyatipata['avoidance_scope']);

        $vaidhriti = ElectionalEvaluator::calculateNityaYogaObservations(27, 'Vaidhriti');
        self::assertTrue($vaidhriti['is_marriage_prohibited']);
        self::assertTrue($vaidhriti['is_kranti_dosha']);

        $siddhi = ElectionalEvaluator::calculateNityaYogaObservations(21, 'Siddhi');
        self::assertFalse($siddhi['is_marriage_prohibited']);
        self::assertFalse($siddhi['is_kranti_dosha']);
    }

    public function testDayDetailsExposeGeneralCalculativeLayers(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);
        $date = CarbonImmutable::create(2026, 6, 8, 0, 0, 0, 'Asia/Kolkata');
        $at = CarbonImmutable::create(2026, 6, 8, 10, 15, 0, 'Asia/Kolkata');

        $details = $service->getDayDetails($date, 23.2472446, 69.668339, 'Asia/Kolkata', 0.0, $at, CalendarType::Amanta);

        self::assertArrayHasKey('Vara_Tithi_Doshas', $details);
        self::assertArrayHasKey('Nitya_Yoga_Observations', $details);
        self::assertArrayHasKey('Day_Night_Measures', $details);
        self::assertArrayHasKey('Tithi_Observance_Analysis', $details);
        self::assertArrayHasKey('Yatra_Screening', $details);
        self::assertArrayHasKey('Vrata_Parana', $details);
        self::assertArrayHasKey('Nakshatra_Tyajya', $details);

        self::assertArrayHasKey('all', $details['Vara_Tithi_Doshas']);
        self::assertArrayHasKey('dagdha', $details['Vara_Tithi_Doshas']['all']);

        self::assertArrayHasKey('dinamana', $details['Day_Night_Measures']);
        self::assertArrayHasKey('ratrimana', $details['Day_Night_Measures']);
        self::assertArrayHasKey('ghati', $details['Day_Night_Measures']['dinamana']);
        self::assertArrayHasKey('pala', $details['Day_Night_Measures']['ratrimana']);

        self::assertSame('smarta_udaya_tithi_structural_analysis', $details['Tithi_Observance_Analysis']['rule_system'] ?? null);
        self::assertArrayHasKey('current_at_input_now', $details['Tithi_Observance_Analysis']);
        self::assertArrayHasKey('at_sunrise', $details['Tithi_Observance_Analysis']);
        self::assertContains($details['Tithi_Observance_Analysis']['current_at_input_now']['status'] ?? null, ['Shuddha', 'Viddha', 'Kshaya']);
        self::assertArrayHasKey('is_tithi_vriddhi', $details['Tithi_Observance_Analysis']['at_sunrise']);
        self::assertTrue($details['Tithi_Observance_Analysis']['festival_specific_override_required'] ?? false);

        self::assertArrayHasKey('direction_grid', $details['Yatra_Screening']['current_at_input_now']);
        self::assertCount(8, $details['Yatra_Screening']['current_at_input_now']['direction_grid']);
        self::assertTrue($details['Yatra_Screening']['current_at_input_now']['urgent_same_day_return_exception'] ?? false);
        self::assertSame(true, $details['Nakshatra_Tyajya']['is_equivalent_to_varjyam'] ?? false);
    }

    public function testPanchakSubtypeLocksToCycleEntryWeekdayNotCurrentDay(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);
        $date = CarbonImmutable::create(2026, 6, 8, 0, 0, 0, 'Asia/Kolkata');
        $at = CarbonImmutable::create(2026, 6, 8, 11, 2, 32, 'Asia/Kolkata');

        $details = $service->getDayDetails($date, 23.2472446, 69.668339, 'Asia/Kolkata', 0.0, $at, CalendarType::Amanta);
        $panchak = $details['Panchak'];

        self::assertTrue($panchak['is_present']);
        self::assertSame('Mrityu Panchaka', $panchak['current_weekday_type_key']);
        self::assertSame('Saturday', $panchak['windows'][0]['start_weekday']);
        self::assertSame('Monday', $panchak['windows'][0]['current_running_weekday']);
        self::assertSame('entry_weekday', $panchak['windows'][0]['classification_lock']);
        self::assertStringStartsWith('06/06/2026', (string) $panchak['windows'][0]['start']);
        self::assertStringStartsWith('11/06/2026', (string) $panchak['windows'][0]['end']);
        self::assertStringStartsWith('08/06/2026', (string) $panchak['windows'][0]['visible_start']);
    }

    public function testNewUserFacingGeneralLayersAreLocalizedForGujarati(): void
    {
        config(['panchang.defaults.locale' => 'gu']);

        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);
        $date = CarbonImmutable::create(2026, 6, 8, 0, 0, 0, 'Asia/Kolkata');
        $at = CarbonImmutable::create(2026, 6, 8, 10, 15, 0, 'Asia/Kolkata');

        $details = $service->getDayDetails($date, 23.2472446, 69.668339, 'Asia/Kolkata', 0.0, $at, CalendarType::Amanta);

        self::assertContains($details['Tithi_Observance_Analysis']['current_at_input_now']['status_label'] ?? null, ['શુદ્ધ', 'વિદ્ધ', 'ક્ષય']);
        self::assertNotSame('Purnima / Amavasya', $details['Tithi_Observance_Analysis']['current_at_input_now']['phase_tithi_name'] ?? null);
        self::assertNotEmpty($details['Vrata_Parana']['supported_families'][0]['family_name'] ?? null);
        self::assertNotEmpty($details['Vrata_Parana']['supported_families'][0]['parana_policy'] ?? null);
        self::assertNotSame('East', $details['Yatra_Screening']['current_at_input_now']['direction_grid'][0]['direction'] ?? null);
    }

    protected function getPackageProviders($app): array
    {
        return [PanchangServiceProvider::class];
    }
}
