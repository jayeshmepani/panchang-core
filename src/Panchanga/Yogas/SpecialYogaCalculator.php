<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Panchanga\Yogas;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Astronomy\Math\IntervalTracker;
use JayeshMepani\PanchangCore\Astronomy\SunService;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Core\Enums\Nakshatra;
use JayeshMepani\PanchangCore\Core\Enums\Rasi;
use JayeshMepani\PanchangCore\Core\Enums\Tithi;
use JayeshMepani\PanchangCore\Core\Enums\Vara;
use JayeshMepani\PanchangCore\Core\Localization;
use RuntimeException;

/** Special Yoga Calculator - Handles complex combinatorial yoga rule systems. */
class SpecialYogaCalculator
{
    private const array SARVARTHA_SIDDHI_RULES = [
        0 => [3, 7, 11, 12, 20, 25],
        1 => [3, 4, 7, 16, 21],
        2 => [0, 2, 8, 12, 25],
        3 => [2, 3, 4, 12, 16, 24, 25],
        4 => [6, 7, 16, 26],
        5 => [0, 6, 16, 21, 26],
        6 => [3, 14, 21],
    ];

    private const array AMRIT_SIDDHI_RULES = [
        0 => [12],
        1 => [4],
        2 => [0],
        3 => [16],
        4 => [7],
        5 => [26],
        6 => [3],
    ];

    private const array DWI_PUSHKARA_NAKSHATRAS = [4, 13, 22];

    private const array TRI_PUSHKARA_NAKSHATRAS = [2, 6, 10, 15, 20, 24];

    private const array PUSHKARA_TITHIS = [2, 7, 12];

    private const array PUSHKARA_WEEKDAYS = [0, 2, 6];

    private const array RAVI_YOGA_DISTANCES = [4, 6, 9, 10, 13, 20];

    private const array AADAL_COUNTS = [2, 7, 9, 14, 16, 21, 23, 28];

    private const array VIDAAL_COUNTS = [3, 6, 10, 13, 17, 20, 24, 27];

    private const array JWALAMUKHI_RULES = [
        1 => [18], // Pratipada + Moola
        5 => [1],  // Panchami + Bharani
        8 => [2],  // Ashtami + Krittika
        9 => [3],  // Navami + Rohini
        10 => [8], // Dashami + Ashlesha
    ];

    private const array ABHIJIT_ORDER = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 27, 21, 22, 23, 24, 25, 26];

    private const array ANANDADI_YOGA_NAMES = [
        'Ananda', 'Kaladanda', 'Dhumra', 'Dhata/Prajapati', 'Saumya', 'Dhvanksha', 'Dhvaja',
        'Srivatsa', 'Vajra', 'Mudgara', 'Chhatra', 'Mitra', 'Manasa', 'Padma', 'Lumbaka',
        'Utpata', 'Mrityu', 'Kana', 'Siddhi', 'Shubha', 'Amrita', 'Mushala', 'Gada',
        'Matanga', 'Rakshasa', 'Chara', 'Sthira', 'Vardhamana',
    ];

    private const array ANANDADI_YOGA_EFFECTS = [
        'Siddhi', 'Mrityu', 'Asukha', 'Saubhagya', 'Bahu Sukha', 'Dhanakshaya', 'Saubhagya',
        'Saukhyasampatti', 'Loss / Kshaya', 'Lakshmikshaya', 'Rajasanmana', 'Pushti',
        'Saubhagya', 'Dhanagama', 'Dhanakshaya', 'Prananasha', 'Mrityu', 'Klesha',
        'Karyasiddhi', 'Kalyana', 'Rajasanmana', 'Dhanakshaya', 'Bhaya', 'Kulavriddhi',
        'Mahakashta', 'Karyasiddhi', 'Griharambha', 'Vivaha',
    ];

    private const array ANANDADI_WEEKDAY_START_28_INDEX = [
        0 => 0, 1 => 4, 2 => 8, 3 => 12, 4 => 16, 5 => 20, 6 => 24,
    ];

    private const array AMRITADI_YOGA_TABLE = [
        0 => ['Siddha', 'Siddha', 'Siddha', 'Marana', 'Amrita', 'Amrita', 'Siddha'],
        1 => ['Prabalarishta', 'Siddha', 'Siddha', 'Siddha', 'Siddha', 'Siddha', 'Siddha'],
        2 => ['Siddha', 'Marana', 'Siddha', 'Amrita', 'Marana', 'Siddha', 'Siddha'],
        3 => ['Siddha', 'Amrita', 'Amrita', 'Siddha', 'Marana', 'Marana', 'Amrita'],
        4 => ['Siddha', 'Siddha', 'Siddha', 'Siddha', 'Marana', 'Siddha', 'Siddha'],
        5 => ['Siddha', 'Siddha', 'Marana', 'Siddha', 'Marana', 'Siddha', 'Siddha'],
        6 => ['Siddha', 'Amrita', 'Siddha', 'Siddha', 'Amrita', 'Siddha', 'Siddha'],
        7 => ['Siddha', 'Siddha', 'Siddha', 'Siddha', 'Siddha', 'Marana', 'Siddha'],
        8 => ['Siddha', 'Siddha', 'Siddha', 'Siddha', 'Siddha', 'Marana', 'Marana'],
        9 => ['Marana', 'Marana', 'Siddha', 'Siddha', 'Amrita', 'Marana', 'Amrita'],
        10 => ['Siddha', 'Siddha', 'Siddha', 'Amrita', 'Siddha', 'Siddha', 'Siddha'],
        11 => ['Amrita', 'Siddha', 'Amrita', 'Amrita', 'Marana', 'Siddha', 'Marana'],
        12 => ['Siddha', 'Siddha', 'Siddha', 'Marana', 'Siddha', 'Amrita', 'Marana'],
        13 => ['Siddha', 'Prabalarishta', 'Siddha', 'Siddha', 'Siddha', 'Siddha', 'Marana'],
        14 => ['Siddha', 'Amrita', 'Siddha', 'Siddha', 'Amrita', 'Siddha', 'Siddha'],
        15 => ['Marana', 'Marana', 'Marana', 'Siddha', 'Amrita', 'Siddha', 'Siddha'],
        16 => ['Marana', 'Siddha', 'Siddha', 'Amrita', 'Siddha', 'Siddha', 'Siddha'],
        17 => ['Marana', 'Siddha', 'Marana', 'Siddha', 'Prabalarishta', 'Marana', 'Siddha'],
        18 => ['Amrita', 'Siddha', 'Amrita', 'Marana', 'Siddha', 'Amrita', 'Siddha'],
        19 => ['Siddha', 'Marana', 'Siddha', 'Amrita', 'Siddha', 'Prabalarishta', 'Siddha'],
        20 => ['Amrita', 'Marana', 'Prabalarishta', 'Amrita', 'Siddha', 'Siddha', 'Siddha'],
        21 => ['Amrita', 'Amrita', 'Siddha', 'Siddha', 'Siddha', 'Marana', 'Siddha'],
        22 => ['Marana', 'Siddha', 'Siddha', 'Prabalarishta', 'Siddha', 'Siddha', 'Siddha'],
        23 => ['Siddha', 'Siddha', 'Marana', 'Siddha', 'Marana', 'Siddha', 'Amrita'],
        24 => ['Siddha', 'Siddha', 'Marana', 'Amrita', 'Siddha', 'Siddha', 'Marana'],
        25 => ['Amrita', 'Siddha', 'Amrita', 'Siddha', 'Siddha', 'Siddha', 'Siddha'],
        26 => ['Amrita', 'Siddha', 'Siddha', 'Marana', 'Siddha', 'Siddha', 'Prabalarishta'],
    ];

    public function __construct(
        private readonly SunService $sunService,
        private readonly IntervalTracker $intervalTracker
    ) {
    }

    public function calculateSpecialYogas(
        CarbonImmutable $date,
        float $jdStart,
        float $jdEnd,
        int $tithiNumber,
        int $weekdayIndex,
        string $tz
    ): array {
        $nakshatraIntervals = $this->intervalTracker->collectNakshatraIntervals($jdStart, $jdEnd);
        $tithiIntervals = $this->intervalTracker->collectTithiIntervals($jdStart, $jdEnd);
        $sunNakshatraIntervals = $this->intervalTracker->collectSunNakshatraIntervals($jdStart, $jdEnd);

        $payload = [
            'sarvartha_siddhi' => $this->buildSpecialYogaPayload(
                'Sarvartha Siddhi Yoga',
                'weekday_nakshatra',
                $this->matchWeekdayNakshatraWindows($nakshatraIntervals, $weekdayIndex, self::SARVARTHA_SIDDHI_RULES, $tz)
            ),
            'amrit_siddhi' => $this->buildSpecialYogaPayload(
                'Amrit Siddhi Yoga',
                'weekday_nakshatra',
                $this->matchWeekdayNakshatraWindows($nakshatraIntervals, $weekdayIndex, self::AMRIT_SIDDHI_RULES, $tz)
            ),
            'ravi_pushya' => $this->buildSpecialYogaPayload(
                'Ravi Pushya Yoga',
                'weekday_nakshatra',
                $weekdayIndex === 0 ? $this->filterNakshatraWindows($nakshatraIntervals, [7], $tz) : []
            ),
            'guru_pushya' => $this->buildSpecialYogaPayload(
                'Guru Pushya Yoga',
                'weekday_nakshatra',
                $weekdayIndex === 4 ? $this->filterNakshatraWindows($nakshatraIntervals, [7], $tz) : []
            ),
            'dwipushkar' => $this->buildSpecialYogaPayload(
                'Dwipushkar Yoga',
                'weekday_tithi_nakshatra',
                $this->matchPushkaraWindows($tithiIntervals, $nakshatraIntervals, $weekdayIndex, self::PUSHKARA_TITHIS, self::DWI_PUSHKARA_NAKSHATRAS, $tz)
            ),
            'tripushkar' => $this->buildSpecialYogaPayload(
                'Tripushkar Yoga',
                'weekday_tithi_nakshatra',
                $this->matchPushkaraWindows($tithiIntervals, $nakshatraIntervals, $weekdayIndex, self::PUSHKARA_TITHIS, self::TRI_PUSHKARA_NAKSHATRAS, $tz)
            ),
            'ganda_mula' => $this->buildSpecialYogaPayload(
                'Ganda Mula',
                'nakshatra',
                $this->filterNakshatraWindows($nakshatraIntervals, [0, 8, 9, 17, 18, 26], $tz)
            ),
            'vinchhudo' => $this->buildSpecialYogaPayload(
                'Vinchhudo',
                'moon_sign',
                $this->intervalTracker->collectMoonSignWindows($jdStart, $jdEnd, 7, $tz)
            ),
            'ravi_yoga' => $this->buildSpecialYogaPayload(
                'Ravi Yoga',
                'sun_moon_nakshatra_distance',
                $this->matchRaviYogaWindows($nakshatraIntervals, $sunNakshatraIntervals, $tz)
            ),
            'aadal' => $this->buildSpecialYogaPayload(
                'Aadal Yoga',
                'sun_moon_nakshatra_count_with_abhijit',
                $this->matchAadalVidaalWindows($nakshatraIntervals, $sunNakshatraIntervals, self::AADAL_COUNTS, $tz, 'Aadal')
            ),
            'vidaal' => $this->buildSpecialYogaPayload(
                'Vidaal Yoga',
                'sun_moon_nakshatra_count_with_abhijit',
                $this->matchAadalVidaalWindows($nakshatraIntervals, $sunNakshatraIntervals, self::VIDAAL_COUNTS, $tz, 'Vidaal')
            ),
            'jwalamukhi' => $this->buildSpecialYogaPayload(
                'Jwalamukhi Yoga',
                'tithi_nakshatra',
                $this->matchJwalamukhiWindows($tithiIntervals, $nakshatraIntervals, $tz)
            ),
        ];

        $payload['summary'] = [
            'active_yoga_keys' => array_keys(array_filter(
                $payload,
                static fn (array $node): bool => (($node['is_present'] ?? false) === true)
            )),
            'sunrise_tithi' => Tithi::from($tithiNumber)->getName(),
            'weekday' => Vara::from($weekdayIndex)->getName(),
            'date' => $date->toDateString(),
        ];

        return $payload;
    }

    public function calculateAnandadiYoga(float $jdStart, float $jdEnd, int $weekdayIndex, string $tz, ?float $currentJd = null): array
    {
        $windows = [];
        $startIndex = self::ANANDADI_WEEKDAY_START_28_INDEX[$weekdayIndex] ?? 0;

        foreach ($this->intervalTracker->collectNakshatra28Intervals($jdStart, $jdEnd) as $interval) {
            $yogaIndex = ((int) $interval['order_index'] - $startIndex + 28) % 28;
            $name = self::ANANDADI_YOGA_NAMES[$yogaIndex];
            $effect = self::ANANDADI_YOGA_EFFECTS[$yogaIndex];

            $windows[] = [
                'index' => $yogaIndex + 1,
                'name' => Localization::translate('String', $name),
                'name_key' => $name,
                'effect' => Localization::translate('String', $effect),
                'effect_key' => $effect,
                'nakshatra' => $interval['name'],
                'nakshatra_order_index' => $interval['order_index'],
                'start_jd' => $interval['start_jd'],
                'end_jd' => $interval['end_jd'],
                'start' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic((float) $interval['start_jd'], $tz)),
                'end' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic((float) $interval['end_jd'], $tz)),
            ];
        }

        return [
            'rule_system' => 'sripati_jyotisha_ratnamala_28_nakshatra',
            'is_complete_system' => true,
            'system_size' => 28,
            'weekday' => Vara::from($weekdayIndex)->getName(),
            'weekday_index' => $weekdayIndex,
            'weekday_start_nakshatra' => $this->nakshatra28Name($startIndex),
            'current' => $this->activeWindow($windows, $currentJd),
            'window_count' => count($windows),
            'windows' => $windows,
        ];
    }

    public function calculateAmritadiYoga(float $jdStart, float $jdEnd, int $weekdayIndex, string $tz, ?float $currentJd = null): array
    {
        $windows = [];

        foreach ($this->intervalTracker->collectNakshatraIntervals($jdStart, $jdEnd) as $interval) {
            $classification = self::AMRITADI_YOGA_TABLE[(int) $interval['index']][$weekdayIndex] ?? 'Siddha';
            $windows[] = [
                'classification' => Localization::translate('String', $classification),
                'classification_key' => $classification,
                'is_auspicious' => in_array($classification, ['Amrita', 'Siddha'], true),
                'nakshatra' => $interval['name'],
                'nakshatra_index' => $interval['index'],
                'weekday' => Vara::from($weekdayIndex)->getName(),
                'start_jd' => $interval['start_jd'],
                'end_jd' => $interval['end_jd'],
                'start' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($interval['start_jd'], $tz)),
                'end' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($interval['end_jd'], $tz)),
            ];
        }

        return [
            'rule_system' => 'amritadi_yoga_27_nakshatra_7_weekday',
            'is_complete_system' => true,
            'system_size' => 189,
            'weekday' => Vara::from($weekdayIndex)->getName(),
            'weekday_index' => $weekdayIndex,
            'current' => $this->activeWindow($windows, $currentJd),
            'window_count' => count($windows),
            'windows' => $windows,
            'classifications' => ['Amrita', 'Siddha', 'Marana', 'Prabalarishta'],
        ];
    }

    public function calculateMaitreyaYoga(
        float $jdStart,
        float $jdEnd,
        int $weekdayIndex,
        array $lagnaTable,
        string $tz
    ): array {
        $windows = [];
        $rawRules = [
            [
                'weekday_index' => 2,
                'nakshatra_index' => 0,
                'lagna_sign_index' => 0,
            ],
            [
                'weekday_index' => 2,
                'nakshatra_index' => 16,
                'lagna_sign_index' => 7,
            ],
        ];

        $rules = array_map(function (array $rule): array {
            $rule['combination'] = sprintf(
                '%s + %s + %s Lagna',
                Vara::from($rule['weekday_index'])->getName(),
                Localization::translate('Nakshatra', $rule['nakshatra_index'], config('panchang.defaults.locale', 'en')),
                Rasi::from($rule['lagna_sign_index'])->getName()
            );
            return $rule;
        }, $rawRules);

        if ($weekdayIndex !== 2) {
            return [
                'rule_system' => 'weekday_nakshatra_lagna_debt_repayment',
                'is_complete_system' => true,
                'is_present' => false,
                'window_count' => 0,
                'windows' => [],
                'rules' => $rules,
            ];
        }

        $nakshatraIntervals = $this->intervalTracker->collectNakshatraIntervals($jdStart, $jdEnd);
        foreach ($rules as $rule) {
            foreach ($nakshatraIntervals as $nakshatraInterval) {
                if ($nakshatraInterval['index'] !== $rule['nakshatra_index']) {
                    continue;
                }

                foreach ($lagnaTable as $lagnaWindow) {
                    if (($lagnaWindow['sign_index'] ?? null) !== $rule['lagna_sign_index']) {
                        continue;
                    }

                    $startJd = max((float) $nakshatraInterval['start_jd'], (float) $lagnaWindow['start_jd']);
                    $endJd = min((float) $nakshatraInterval['end_jd'], (float) $lagnaWindow['end_jd']);
                    if ($startJd >= $endJd) {
                        continue;
                    }

                    $windows[] = [
                        'combination' => $rule['combination'],
                        'weekday' => Vara::from($weekdayIndex)->getName(),
                        'nakshatra' => $nakshatraInterval['name'],
                        'lagna' => Rasi::from($rule['lagna_sign_index'])->getName(),
                        'start_jd' => $startJd,
                        'end_jd' => $endJd,
                        'start' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($startJd, $tz)),
                        'end' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($endJd, $tz)),
                    ];
                }
            }
        }

        return [
            'rule_system' => 'weekday_nakshatra_lagna_debt_repayment',
            'is_complete_system' => true,
            'is_present' => $windows !== [],
            'window_count' => count($windows),
            'windows' => $windows,
            'rules' => $rules,
        ];
    }

    public function calculateGajachchhayaYoga(float $jdStart, float $jdEnd, array $hinduMonth, string $tz): array
    {
        $variants = [
            'trayodashi_hasta_magha' => [
                'rule_system' => 'trayodashi_tithi_sun_hasta_moon_magha',
                'description' => Localization::translate('String', 'Trayodashi tithi with Sun in Hasta and Moon in Magha'),
                'requires_bhadrapada_pitru_paksha' => false,
                'tithi_phases' => [13],
                'require_krishna_paksha' => false,
            ],
            'amavasya_hasta_magha' => [
                'rule_system' => 'amavasya_tithi_sun_hasta_moon_magha',
                'description' => Localization::translate('String', 'Amavasya tithi with Sun in Hasta and Moon in Magha'),
                'requires_bhadrapada_pitru_paksha' => false,
                'tithi_phases' => [15],
                'require_krishna_paksha' => true,
            ],
            'pitru_paksha_bhadrapada_krishna_trayodashi' => [
                'rule_system' => 'bhadrapada_krishna_trayodashi_sun_hasta_moon_magha',
                'description' => Localization::translate('String', 'Bhadrapada Krishna Trayodashi / Pitru Paksha with Sun in Hasta and Moon in Magha'),
                'requires_bhadrapada_pitru_paksha' => true,
                'tithi_phases' => [13],
                'require_krishna_paksha' => true,
            ],
        ];
        $tithiIntervals = $this->intervalTracker->collectTithiIntervals($jdStart, $jdEnd);
        $moonNakshatraIntervals = $this->intervalTracker->collectNakshatraIntervals($jdStart, $jdEnd);
        $sunNakshatraIntervals = $this->intervalTracker->collectSunNakshatraIntervals($jdStart, $jdEnd);
        $monthContext = [
            'amanta' => (string) ($hinduMonth['Month_Amanta_En'] ?? $hinduMonth['Month_Amanta'] ?? ''),
            'purnimanta' => (string) ($hinduMonth['Month_Purnimanta_En'] ?? $hinduMonth['Month_Purnimanta'] ?? ''),
        ];

        $variantPayload = [];
        foreach ($variants as $key => $variant) {
            $monthQualified = !$variant['requires_bhadrapada_pitru_paksha']
                || $monthContext['amanta'] === 'Bhadrapada'
                || $monthContext['purnimanta'] === 'Bhadrapada';

            $windows = $monthQualified
                ? $this->matchGajachchhayaWindows(
                    $tithiIntervals,
                    $moonNakshatraIntervals,
                    $sunNakshatraIntervals,
                    $variant['tithi_phases'],
                    $variant['require_krishna_paksha'],
                    $tz
                )
                : [];

            $variantPayload[$key] = [
                'rule_system' => $variant['rule_system'],
                'description' => $variant['description'],
                'requires_bhadrapada_pitru_paksha' => $variant['requires_bhadrapada_pitru_paksha'],
                'month_qualified' => $monthQualified,
                'is_present' => $windows !== [],
                'window_count' => count($windows),
                'windows' => $windows,
            ];
        }

        return [
            'is_complete_known_variant_set' => true,
            'variant_count' => count($variantPayload),
            'month_context' => $monthContext,
            'is_present' => $this->anyVariantPresent($variantPayload),
            'variants' => $variantPayload,
        ];
    }

    private function matchGajachchhayaWindows(
        array $tithiIntervals,
        array $moonNakshatraIntervals,
        array $sunNakshatraIntervals,
        array $targetTithiPhases,
        bool $requireKrishnaPaksha,
        string $tz
    ): array {
        $windows = [];

        foreach ($tithiIntervals as $tithiInterval) {
            $phaseIndex = (int) $tithiInterval['phase_index'];
            $tithiIndex = (int) $tithiInterval['index'];
            if (!in_array($phaseIndex, $targetTithiPhases, true)) {
                continue;
            }

            $isKrishna = $tithiIndex > 15;
            if ($requireKrishnaPaksha && !$isKrishna) {
                continue;
            }

            foreach ($moonNakshatraIntervals as $moonInterval) {
                if ((int) $moonInterval['index'] !== 9) {
                    continue;
                }

                foreach ($sunNakshatraIntervals as $sunInterval) {
                    if ((int) $sunInterval['index'] !== 12) {
                        continue;
                    }

                    $startJd = max((float) $tithiInterval['start_jd'], (float) $moonInterval['start_jd'], (float) $sunInterval['start_jd']);
                    $endJd = min((float) $tithiInterval['end_jd'], (float) $moonInterval['end_jd'], (float) $sunInterval['end_jd']);
                    if ($startJd >= $endJd) {
                        continue;
                    }

                    $windows[] = [
                        'start_jd' => $startJd,
                        'end_jd' => $endJd,
                        'start' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($startJd, $tz)),
                        'end' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($endJd, $tz)),
                        'tithi' => $tithiInterval['name'],
                        'paksha' => $isKrishna ? 'Krishna' : 'Shukla',
                        'moon_nakshatra' => $moonInterval['name'],
                        'sun_nakshatra' => $sunInterval['name'],
                    ];
                }
            }
        }

        return $windows;
    }

    private function matchPushkaraWindows(
        array $tithiIntervals,
        array $nakshatraIntervals,
        int $weekdayIndex,
        array $targetTithis,
        array $targetNakshatras,
        string $tz
    ): array {
        if (!in_array($weekdayIndex, self::PUSHKARA_WEEKDAYS, true)) {
            return [];
        }

        $windows = [];
        foreach ($tithiIntervals as $tithiInterval) {
            if (!in_array($tithiInterval['phase_index'], $targetTithis, true)) {
                continue;
            }

            foreach ($nakshatraIntervals as $nakshatraInterval) {
                if (!in_array($nakshatraInterval['index'], $targetNakshatras, true)) {
                    continue;
                }

                $startJd = max((float) $tithiInterval['start_jd'], (float) $nakshatraInterval['start_jd']);
                $endJd = min((float) $tithiInterval['end_jd'], (float) $nakshatraInterval['end_jd']);
                if ($startJd >= $endJd) {
                    continue;
                }

                $windows[] = [
                    'start_jd' => $startJd,
                    'end_jd' => $endJd,
                    'start' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($startJd, $tz)),
                    'end' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($endJd, $tz)),
                    'tithi' => $tithiInterval['name'],
                    'nakshatra' => $nakshatraInterval['name'],
                ];
            }
        }

        return $windows;
    }

    private function matchRaviYogaWindows(array $moonNakshatraIntervals, array $sunNakshatraIntervals, string $tz): array
    {
        $windows = [];
        foreach ($moonNakshatraIntervals as $moonInterval) {
            foreach ($sunNakshatraIntervals as $sunInterval) {
                $startJd = max((float) $moonInterval['start_jd'], (float) $sunInterval['start_jd']);
                $endJd = min((float) $moonInterval['end_jd'], (float) $sunInterval['end_jd']);
                if ($startJd >= $endJd) {
                    continue;
                }

                $distance = (($moonInterval['index'] - $sunInterval['index'] + 27) % 27) + 1;
                if (!in_array($distance, self::RAVI_YOGA_DISTANCES, true)) {
                    continue;
                }

                $windows[] = [
                    'start_jd' => $startJd,
                    'end_jd' => $endJd,
                    'start' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($startJd, $tz)),
                    'end' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($endJd, $tz)),
                    'nakshatra' => $moonInterval['name'],
                    'sun_nakshatra' => $sunInterval['name'],
                    'distance_from_sun_nakshatra' => $distance,
                ];
            }
        }

        return $windows;
    }

    private function matchAadalVidaalWindows(
        array $moonNakshatraIntervals,
        array $sunNakshatraIntervals,
        array $targetCounts,
        string $tz,
        string $label
    ): array {
        $windows = [];
        foreach ($moonNakshatraIntervals as $moonInterval) {
            foreach ($sunNakshatraIntervals as $sunInterval) {
                $startJd = max((float) $moonInterval['start_jd'], (float) $sunInterval['start_jd']);
                $endJd = min((float) $moonInterval['end_jd'], (float) $sunInterval['end_jd']);
                if ($startJd >= $endJd) {
                    continue;
                }

                $count = $this->countNakshatrasWithAbhijit($sunInterval['index'], $moonInterval['index']);
                if (!in_array($count, $targetCounts, true)) {
                    continue;
                }

                $windows[] = [
                    'start_jd' => $startJd,
                    'end_jd' => $endJd,
                    'start' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($startJd, $tz)),
                    'end' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($endJd, $tz)),
                    'nakshatra' => $moonInterval['name'],
                    'sun_nakshatra' => $sunInterval['name'],
                    'count_with_abhijit' => $count,
                    'classification' => Localization::translate('String', $label),
                ];
            }
        }

        return $windows;
    }

    private function matchJwalamukhiWindows(array $tithiIntervals, array $nakshatraIntervals, string $tz): array
    {
        $windows = [];
        foreach ($tithiIntervals as $tithiInterval) {
            $targetNakshatras = self::JWALAMUKHI_RULES[(int) $tithiInterval['phase_index']] ?? null;
            if ($targetNakshatras === null) {
                continue;
            }

            foreach ($nakshatraIntervals as $nakshatraInterval) {
                if (!in_array($nakshatraInterval['index'], $targetNakshatras, true)) {
                    continue;
                }

                $startJd = max((float) $tithiInterval['start_jd'], (float) $nakshatraInterval['start_jd']);
                $endJd = min((float) $tithiInterval['end_jd'], (float) $nakshatraInterval['end_jd']);
                if ($startJd >= $endJd) {
                    continue;
                }

                $windows[] = [
                    'start_jd' => $startJd,
                    'end_jd' => $endJd,
                    'start' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($startJd, $tz)),
                    'end' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($endJd, $tz)),
                    'tithi' => $tithiInterval['name'],
                    'nakshatra' => $nakshatraInterval['name'],
                ];
            }
        }

        return $windows;
    }

    private function countNakshatrasWithAbhijit(int $sunNakshatraIndex, int $moonNakshatraIndex): int
    {
        $startIndex = array_search($sunNakshatraIndex, self::ABHIJIT_ORDER, true);
        $endIndex = array_search($moonNakshatraIndex, self::ABHIJIT_ORDER, true);
        if ($startIndex === false || $endIndex === false) {
            throw new RuntimeException('Invalid nakshatra index for Abhijit count.');
        }

        if ($startIndex <= $endIndex) {
            $count = ($endIndex - $startIndex + 1) % count(self::ABHIJIT_ORDER);
            if ($count === 0) {
                return count(self::ABHIJIT_ORDER);
            }

            return $count;
        }

        return count(self::ABHIJIT_ORDER) - $startIndex + $endIndex + 1;
    }

    private function nakshatra28Name(int $orderIndex): string
    {
        return match ($orderIndex) {
            21 => 'Abhijit',
            22 => Nakshatra::Shravana->getName(),
            23 => Nakshatra::Dhanishta->getName(),
            24 => Nakshatra::Shatabhisha->getName(),
            25 => Nakshatra::PurvaBhadrapada->getName(),
            26 => Nakshatra::UttaraBhadrapada->getName(),
            27 => Nakshatra::Revati->getName(),
            default => Nakshatra::from($orderIndex)->getName(),
        };
    }

    private function filterNakshatraWindows(array $nakshatraIntervals, array $targetIndexes, string $tz): array
    {
        $windows = [];
        foreach ($nakshatraIntervals as $interval) {
            if (!in_array($interval['index'], $targetIndexes, true)) {
                continue;
            }

            $windows[] = [
                'start_jd' => $interval['start_jd'],
                'end_jd' => $interval['end_jd'],
                'start' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($interval['start_jd'], $tz)),
                'end' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($interval['end_jd'], $tz)),
                'nakshatra' => $interval['name'],
            ];
        }

        return $windows;
    }

    private function buildSpecialYogaPayload(string $name, string $basis, array $windows): array
    {
        return [
            'name' => Localization::translate('String', $name),
            'name_key' => $name,
            'basis' => $basis,
            'is_present' => $windows !== [],
            'window_count' => count($windows),
            'windows' => $windows,
        ];
    }

    /** @param array<int, array<string, mixed>> $windows */
    private function activeWindow(array $windows, ?float $currentJd): ?array
    {
        if ($windows === []) {
            return null;
        }

        if ($currentJd === null) {
            return $windows[0];
        }

        foreach ($windows as $window) {
            $startJd = (float) ($window['start_jd'] ?? 0.0);
            $endJd = (float) ($window['end_jd'] ?? 0.0);
            if ($currentJd >= $startJd && $currentJd < $endJd) {
                return $window;
            }
        }

        return $windows[0];
    }

    private function matchWeekdayNakshatraWindows(array $nakshatraIntervals, int $weekdayIndex, array $rules, string $tz): array
    {
        return $this->filterNakshatraWindows($nakshatraIntervals, $rules[$weekdayIndex] ?? [], $tz);
    }

    private function anyVariantPresent(array $variants): bool
    {
        foreach ($variants as $v) {
            if ($v['is_present'] ?? false) {
                return true;
            }
        }

        return false;
    }
}
