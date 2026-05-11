<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Panchanga\Residences;

use JayeshMepani\PanchangCore\Astronomy\SunService;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Core\Enums\Tithi;
use JayeshMepani\PanchangCore\Core\Enums\Vara;
use JayeshMepani\PanchangCore\Core\Localization;

/** Vaasa Calculator - Handles planetary residences (Shiva, Agni, Chandra, Rahu, Yogini). */
class VaasaCalculator
{
    private const array SHIVA_VAASA_LABELS = [
        1 => 'In Cemetery',
        2 => 'With Gowri',
        3 => 'In Assembly',
        4 => 'At Work / Play',
        5 => 'At Kailash',
        6 => 'Mounted on Vrishabha (Nandi)',
        7 => 'At Dinner / Meditation',
    ];

    private const array SHIVA_VAASA_EFFECTS = [
        1 => 'Death / severe inauspiciousness',
        2 => 'Happiness and wealth',
        3 => 'Grief',
        4 => 'Difficulty',
        5 => 'Happiness',
        6 => 'Success',
        7 => 'Trouble / peeda',
    ];

    private const array SHIVA_VAASA_METHOD1 = [
        1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7,
        8 => 1, 9 => 2, 10 => 3, 11 => 4, 12 => 5, 13 => 6, 14 => 7,
        15 => 1, 16 => 1, 17 => 2, 18 => 3, 19 => 4, 20 => 5, 21 => 6, 22 => 7,
        23 => 1, 24 => 2, 25 => 3, 26 => 4, 27 => 5, 28 => 6, 29 => 7, 30 => 2,
    ];

    private const array SHIVA_VAASA_METHOD2 = [
        0 => 1, 1 => 5, 2 => 2, 3 => 6, 4 => 3, 5 => 7, 6 => 4,
    ];

    private const array AGNI_VAASA_LABELS = [
        1 => 'Prithvi (Earth)',
        2 => 'Akaasha (Space)',
        3 => 'Paathaala (Nadir)',
    ];

    private const array AGNI_VAASA_EFFECTS = [
        1 => 'Bestows comfort',
        2 => 'Life threatening / highly adverse',
        3 => 'Destroys wealth',
    ];

    private const array YOGINI_VAASA_MAP = [0, 3, 7, 5, 1, 2, 5, 6, 0, 3, 7, 5, 1, 2, 5, 0, 3, 7, 5, 1, 2, 5, 6, 0, 3, 7, 5, 1, 2, 6];

    private const array CHANDRA_VAASA_PADA_ABODES = [
        1 => 'Deva',
        2 => 'Nara',
        3 => 'Pashava',
        4 => 'Rakshasa',
    ];

    private const array CHANDRA_VAASA_PADA_QUALITY = [
        1 => 'Auspicious',
        2 => 'Neutral',
        3 => 'Mixed',
        4 => 'Inauspicious',
    ];

    private const array DIRECTION_LABELS = [
        0 => 'East',
        1 => 'South',
        2 => 'West',
        3 => 'North',
        4 => 'South-West',
        5 => 'North-West',
        6 => 'North-East',
        7 => 'South-East',
    ];

    public function __construct(private readonly SunService $sunService)
    {
    }

    public function calculateShivaVaasa(int $tithiNumber, float $tithiEndJd, string $tz): array
    {
        $method1Index = self::SHIVA_VAASA_METHOD1[$tithiNumber] ?? 1;
        $method2Index = self::SHIVA_VAASA_METHOD2[($tithiNumber * 2 + 5) % 7] ?? 1;

        return [
            'tithi_number' => $tithiNumber,
            'tithi_name' => Tithi::from($tithiNumber)->getName(),
            'valid_until' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($tithiEndJd, $tz)),
            'valid_until_jd' => $tithiEndJd,
            'method_1' => [
                'index' => $method1Index,
                'state' => Localization::translate('String', self::SHIVA_VAASA_LABELS[$method1Index]),
                'state_key' => self::SHIVA_VAASA_LABELS[$method1Index],
                'effect' => Localization::translate('String', self::SHIVA_VAASA_EFFECTS[$method1Index]),
                'effect_key' => self::SHIVA_VAASA_EFFECTS[$method1Index],
            ],
            'method_2' => [
                'index' => $method2Index,
                'state' => Localization::translate('String', self::SHIVA_VAASA_LABELS[$method2Index]),
                'state_key' => self::SHIVA_VAASA_LABELS[$method2Index],
                'effect' => Localization::translate('String', self::SHIVA_VAASA_EFFECTS[$method2Index]),
                'effect_key' => self::SHIVA_VAASA_EFFECTS[$method2Index],
            ],
        ];
    }

    public function calculateAgniVaasa(int $tithiNumber, int $weekdayIndex, float $tithiEndJd, string $tz): array
    {
        $index = [1, 2, 3, 1][($tithiNumber + 1 + ($weekdayIndex + 1)) % 4];

        return [
            'tithi_number' => $tithiNumber,
            'tithi_name' => Tithi::from($tithiNumber)->getName(),
            'weekday' => Vara::from($weekdayIndex)->getName(),
            'index' => $index,
            'state' => Localization::translate('String', self::AGNI_VAASA_LABELS[$index]),
            'state_key' => self::AGNI_VAASA_LABELS[$index],
            'effect' => Localization::translate('String', self::AGNI_VAASA_EFFECTS[$index]),
            'effect_key' => self::AGNI_VAASA_EFFECTS[$index],
            'valid_until' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($tithiEndJd, $tz)),
            'valid_until_jd' => $tithiEndJd,
        ];
    }

    public function calculateChandraVaasa(array $padaIntervals, string $tz): array
    {
        $windows = [];
        foreach ($padaIntervals as $interval) {
            $pada = (int) $interval['pada'];
            $abode = self::CHANDRA_VAASA_PADA_ABODES[$pada];
            $quality = self::CHANDRA_VAASA_PADA_QUALITY[$pada];

            $windows[] = [
                'abode' => Localization::translate('String', $abode),
                'abode_key' => $abode,
                'quality' => Localization::translate('String', $quality),
                'quality_key' => $quality,
                'nakshatra' => $interval['nakshatra'],
                'nakshatra_index' => $interval['nakshatra_index'],
                'pada' => $pada,
                'start_jd' => $interval['start_jd'],
                'end_jd' => $interval['end_jd'],
                'start' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic((float) $interval['start_jd'], $tz)),
                'end' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic((float) $interval['end_jd'], $tz)),
            ];
        }

        return [
            'rule_system' => 'nakshatra_pada_abode_4_part',
            'source_family' => 'modern_nivas_shool_panchang_style',
            'is_complete_system' => true,
            'current' => $windows[0] ?? null,
            'window_count' => count($windows),
            'windows' => $windows,
        ];
    }

    public function calculateRahuVaasa(int $weekdayIndex): array
    {
        $directionMap = [
            0 => 'South-West',
            1 => 'East',
            2 => 'North',
            3 => 'North-West',
            4 => 'South-East',
            5 => 'West',
            6 => 'East',
        ];
        $direction = $directionMap[$weekdayIndex] ?? 'None';

        return [
            'rule_system' => 'weekday_rahu_direction_7_day',
            'is_complete_system' => true,
            'weekday' => Vara::from($weekdayIndex)->getName(),
            'direction' => Localization::translate('String', $direction),
            'direction_key' => $direction,
            'guidance' => Localization::translate('String', 'Rahu is considered to reside in the indicated direction for this weekday.'),
        ];
    }

    public function calculateYoginiVaasa(int $tithiNumber): array
    {
        $idx = ($tithiNumber - 1) % 30;
        $dirIdx = self::YOGINI_VAASA_MAP[$idx] ?? 0;
        $direction = self::DIRECTION_LABELS[$dirIdx];

        return [
            'tithi_number' => $tithiNumber,
            'tithi_name' => Tithi::from($tithiNumber)->getName(),
            'direction_index' => $dirIdx,
            'direction' => Localization::translate('String', $direction),
            'direction_key' => $direction,
        ];
    }
}
