<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Tests;

use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Core\Localization;
use JayeshMepani\PanchangCore\Festivals\FestivalService;
use JayeshMepani\PanchangCore\PanchangServiceProvider;
use Orchestra\Testbench\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Ensures high-traffic user-facing String/Festival keys resolve properly
 * for en (human English), hi (Devanagari), and gu (Gujarati).
 */
class LocalizationLeakRegressionTest extends TestCase
{
    public function testCriticalDisplayKeysResolveForAllLocales(): void
    {
        $stringKeys = [
            'sunrise',
            'sunset',
            'pradosha',
            'tithi',
            'noon',
            'afternoon',
            'anvadhan',
            'day_after_anvadhan',
            'kalashtami',
            'bhanu_saptami',
            'sheetala_ashtami',
            'chapchar_kut',
            'nakshatra_entry_civil_date',
            'darsha_amavasya_day_before_sunrise_aparahna',
            'vaishnava_ekadashi_from_named_ekadashi',
            'not_applicable',
            'ordinary_visible_eclipse',
            'grast_ast',
            'grast_uday',
            'monthly_sud9',
            'swing_dolotsav',
            'rikta_tithi',
            'bhadra',
            'prefer_ashtami_yukta_purva_navami',
            'sunrise_chaturdashi_preferred_when_both_pradosha',
            'date_rule',
            'inherited_rule',
            'gupta_mahavidya_custom',
            'north_navadurga_bhadrakali_kalpa',
            'Pakshavarddhini_Mahadvadashi',
            'Trisparsha_Mahadvadashi',
            'Vijaya_Mahadvadashi',
            'vaishnava_pakshavarddhini_mahadvadashi',
            'vaishnava_trisparsha_dwadashi_kshaya',
            'observance_note_shifted_to_dwadashi_satsangijivan',
            'Lagna',
            'No natal or person-specific inputs are used.',
            'Evaluation is derived only from current Panchang and transit state for the configured location/time.',
        ];

        foreach ($stringKeys as $key) {
            $en = Localization::translate('String', $key, 'en');
            $hi = Localization::translate('String', $key, 'hi');
            $gu = Localization::translate('String', $key, 'gu');

            if (preg_match('/^[a-z0-9_]+$/', $key) === 1) {
                $this->assertNotSame($key, $en, 'en String still raw key: ' . $key);
            }

            $this->assertDoesNotMatchRegularExpression('/^[a-z0-9_]+$/', $en, sprintf('en String still snake/lowercase key: %s => %s', $key, $en));
            $this->assertMatchesRegularExpression('/[\x{0900}-\x{097F}]/u', $hi, sprintf('hi String missing Devanagari: %s => %s', $key, $hi));
            $this->assertMatchesRegularExpression('/[\x{0A80}-\x{0AFF}]/u', $gu, sprintf('gu String missing Gujarati: %s => %s', $key, $gu));
        }

        $festivalKeys = [
            'Dhanurmas Begins',
            'Rama Navami (Smarta)',
            'Rama Navami (Vaishnava)',
            'Angarak Sankashti Chaturthi',
            'Maha Ashtami',
            'Ashvina Sharad Navaratri Day 8',
            'Ashvina Sharad Navaratri Day 9',
            'Shree Varaha Jayanti',
            'Deepavali Hanuman Puja',
            'Vanjuli Mahadwadashi',
            'Unmilini Mahadwadashi',
            'Trisparsha Mahadwadashi',
            'Pakshavarddhini Mahadwadashi',
            'Vijaya Mahadwadashi',
        ];

        foreach ($festivalKeys as $key) {
            $hi = Localization::translate('Festival', $key, 'hi');
            $gu = Localization::translate('Festival', $key, 'gu');
            $this->assertMatchesRegularExpression('/[\x{0900}-\x{097F}]/u', $hi, 'hi Festival missing Devanagari: ' . $key);
            $this->assertMatchesRegularExpression('/[\x{0A80}-\x{0AFF}]/u', $gu, 'gu Festival missing Gujarati: ' . $key);
        }

        $publicStringKeys = [
            'Eclipse',
            'local',
            'time_range_to',
            'Vara-Tithi dosha present',
            'No Vara-Tithi dosha',
            'Rise/set proxy; not an apparent upper-limb altitude and next-set search.',
        ];
        foreach ($publicStringKeys as $key) {
            $hi = Localization::translate('String', $key, 'hi');
            $gu = Localization::translate('String', $key, 'gu');
            $this->assertMatchesRegularExpression('/[\x{0900}-\x{097F}]/u', $hi, 'hi String missing Devanagari: ' . $key);
            $this->assertMatchesRegularExpression('/[\x{0A80}-\x{0AFF}]/u', $gu, 'gu String missing Gujarati: ' . $key);
        }

        $hiDeity = Localization::translate('Deity', 'Vishnu (Lakshmi-Narayana)', 'hi');
        $guDeity = Localization::translate('Deity', 'Vishnu (Lakshmi-Narayana)', 'gu');
        $this->assertMatchesRegularExpression('/[\x{0900}-\x{097F}]/u', $hiDeity, 'hi Deity missing Devanagari: Vishnu (Lakshmi-Narayana)');
        $this->assertMatchesRegularExpression('/[\x{0A80}-\x{0AFF}]/u', $guDeity, 'gu Deity missing Gujarati: Vishnu (Lakshmi-Narayana)');

        $descKeys = [
            'Birth anniversary of Lord Ganesha (Magha)',
            'Vanjuli Mahadwadashi fasting day observed in the Vaishnava Ekadashi tradition',
            'Unmilini Mahadwadashi fasting day observed in the Vaishnava Ekadashi tradition',
            'Trisparsha Mahadwadashi fasting day observed in the Vaishnava Ekadashi tradition',
            'Pakshavarddhini Mahadwadashi fasting day observed in the Vaishnava Ekadashi tradition',
            'Vijaya Mahadwadashi fasting day observed in the Vaishnava Ekadashi tradition',
            'Unmilini Mahadwadashi occurs when Ekadashi extends to a second sunrise and Dwadashi begins on the selected fasting day.',
            'Trisparsha Mahadwadashi occurs when Ekadashi, a lost Dwadashi and Trayodashi meet within the same sunrise-to-sunrise day.',
            'Vijaya Mahadwadashi occurs when Shukla Dwadashi coincides with Shravana nakshatra.',
        ];
        foreach ($descKeys as $desc) {
            $this->assertMatchesRegularExpression(
                '/[\x{0900}-\x{097F}]/u',
                Localization::translate('FestivalDesc', $desc, 'hi'),
                'hi FestivalDesc missing Devanagari: ' . $desc
            );
            $this->assertMatchesRegularExpression(
                '/[\x{0A80}-\x{0AFF}]/u',
                Localization::translate('FestivalDesc', $desc, 'gu'),
                'gu FestivalDesc missing Gujarati: ' . $desc
            );
        }

        foreach (['Satsangi Jeevan', 'Garga Samhita', 'Nirnaya Sindhu / Dharma Sindhu'] as $source) {
            $this->assertMatchesRegularExpression('/[\x{0900}-\x{097F}]/u', Localization::translate('Source', $source, 'hi'));
            $this->assertMatchesRegularExpression('/[\x{0A80}-\x{0AFF}]/u', Localization::translate('Source', $source, 'gu'));
        }

        $nityaSource = 'Muhurta Chintamani / Drik Panchang prohibited Nitya Yoga list';
        $this->assertMatchesRegularExpression('/[\x{0900}-\x{097F}]/u', Localization::translate('Source', $nityaSource, 'hi'));
        $this->assertMatchesRegularExpression('/[\x{0A80}-\x{0AFF}]/u', Localization::translate('Source', $nityaSource, 'gu'));
    }

    public function testDurationDisplayUnitsAreLocalized(): void
    {
        config(['panchang.defaults.duration_format' => 'mixed']);

        config(['panchang.defaults.locale' => 'en']);
        $this->assertSame('1h 2m 3s', AstroCore::formatDuration(62.05));

        config(['panchang.defaults.locale' => 'hi']);
        $this->assertSame('१घं २मि ३से', AstroCore::formatDuration(62.05));

        config(['panchang.defaults.locale' => 'gu']);
        $this->assertSame('૧ક ૨મિ ૩સે', AstroCore::formatDuration(62.05));
    }

    public function testCalculationBasisKeepsUntranslatedResearchProseOutOfLocalizedDisplayFields(): void
    {
        $service = (new ReflectionClass(FestivalService::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(FestivalService::class, 'buildCalculationBasis');

        $rules = [
            'type' => 'tithi',
            'paksha' => 'Shukla',
            'tithi' => 1,
            'source_refs' => ['Satsangi Jeevan 4.61'],
            'source_evidence' => [
                [
                    'kind' => 'date_rule',
                    'source' => 'Satsangi Jeevan',
                    'locator' => '4.61.17-18',
                    'supports' => 'Start on Ashadha Krishna Pratipada or Dwitiya when the Moon has strength in Taurus; every evening seat Balakrishna, arati, and swing for two ghadi.',
                ],
            ],
            'unresolved_conditions' => [
                'Moon strength in Taurus is stated in SJ 4.61.17 but not yet enforced as a hard executable filter.',
            ],
        ];

        config(['panchang.defaults.locale' => 'hi']);
        $hiBasis = $method->invoke($service, $rules, null);
        $this->assertSame(['सत्संगी जीवन 4.61'], $hiBasis['source_refs']);
        $this->assertSame('तिथि-नियम प्रमाण', $hiBasis['source_evidence'][0]['kind_name']);
        $this->assertArrayNotHasKey('supports', $hiBasis['source_evidence'][0]);
        $this->assertArrayNotHasKey('unresolved_conditions', $hiBasis);
        $this->assertSame($rules['unresolved_conditions'], $hiBasis['unresolved_condition_keys']);

        config(['panchang.defaults.locale' => 'gu']);
        $guBasis = $method->invoke($service, $rules, null);
        $this->assertSame(['સત્સંગી જીવન 4.61'], $guBasis['source_refs']);
        $this->assertSame('તારીખ-નિયમ પુરાવો', $guBasis['source_evidence'][0]['kind_name']);
        $this->assertArrayNotHasKey('supports', $guBasis['source_evidence'][0]);
        $this->assertArrayNotHasKey('unresolved_conditions', $guBasis);
        $this->assertSame($rules['unresolved_conditions'], $guBasis['unresolved_condition_keys']);
    }

    protected function getPackageProviders($app): array
    {
        return [PanchangServiceProvider::class];
    }
}
