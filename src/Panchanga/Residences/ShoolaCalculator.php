<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Panchanga\Residences;

use JayeshMepani\PanchangCore\Astronomy\SunService;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Core\Enums\Nakshatra;
use JayeshMepani\PanchangCore\Core\Enums\Rasi;
use JayeshMepani\PanchangCore\Core\Enums\Tithi;
use JayeshMepani\PanchangCore\Core\Enums\Vara;
use JayeshMepani\PanchangCore\Core\Localization;

/** Shoola Calculator - Handles directional inauspiciousness (Disha Shool, Nakshatra Shool). */
class ShoolaCalculator
{
    private const array NAKSHATRA_SHOOL_DIRECTIONS = [
        'East' => [18, 21, 17],
        'West' => [3, 7],
        'North' => [12, 11],
        'South' => [24, 0, 5, 22],
    ];

    private const array YATRA_DIRECTION_RULES = [
        'East' => [
            'tithis' => [1, 9],
            'weekdays' => [1, 6],
            'nakshatras' => ['Jyeshtha', 'Dhanishtha'],
            'lagnas' => ['Libra', 'Aquarius'],
        ],
        'South-East' => [
            'tithis' => [3, 11],
            'weekdays' => [4, 6],
            'nakshatras' => [],
            'lagnas' => ['Capricorn'],
        ],
        'South' => [
            'tithis' => [5, 13],
            'weekdays' => [4],
            'nakshatras' => ['Ashwini', 'Shravana'],
            'lagnas' => ['Scorpio', 'Pisces'],
        ],
        'South-West' => [
            'tithis' => [4, 12],
            'weekdays' => [1, 5],
            'nakshatras' => [],
            'lagnas' => ['Cancer'],
        ],
        'West' => [
            'tithis' => [6, 14],
            'weekdays' => [0, 5],
            'nakshatras' => ['Rohini', 'Pushya'],
            'lagnas' => ['Leo', 'Sagittarius'],
        ],
        'North-West' => [
            'tithis' => [7, 15],
            'weekdays' => [0, 2],
            'nakshatras' => [],
            'lagnas' => ['Aries'],
        ],
        'North' => [
            'tithis' => [2, 10],
            'weekdays' => [2, 3],
            'nakshatras' => ['Purva Phalguni', 'Hasta'],
            'lagnas' => ['Taurus', 'Virgo'],
        ],
        'North-East' => [
            'tithis' => [8, 30],
            'weekdays' => [3],
            'nakshatras' => [],
            'lagnas' => ['Gemini'],
        ],
    ];

    private const array YATRA_AUSPICIOUS_NAKSHATRAS = [
        'Ashwini', 'Rohini', 'Mrigashira', 'Punarvasu', 'Pushya', 'Magha', 'Hasta',
        'Anuradha', 'Mula', 'Shravana', 'Dhanishtha', 'Uttara Bhadrapada', 'Revati',
    ];

    private const array YATRA_AUSPICIOUS_TITHIS = [2, 3, 5, 7, 10, 11, 13];

    public function __construct(private readonly SunService $sunService)
    {
    }

    public function calculateDishaShool(int $weekdayIndex): array
    {
        $directionMap = [
            0 => 'West',
            1 => 'East',
            2 => 'North',
            3 => 'North',
            4 => 'South',
            5 => 'West',
            6 => 'East',
        ];
        $direction = $directionMap[$weekdayIndex] ?? 'None';
        $hasDishaShool = $direction !== 'None';

        return [
            'weekday' => Vara::from($weekdayIndex)->getName(),
            'direction_to_avoid' => Localization::translate('String', $direction),
            'direction_to_avoid_key' => $direction,
            'has_disha_shool' => $hasDishaShool,
            'is_safe_for_all_directions' => !$hasDishaShool,
            'guidance' => $hasDishaShool
                ? Localization::translate('String', 'Avoid travel in the indicated direction if possible.')
                : Localization::translate('String', 'No Disha Shool for this weekday.'),
        ];
    }

    public function calculateNakshatraShool(array $nakshatraIntervals, string $tz): array
    {
        $windows = [];
        foreach ($nakshatraIntervals as $interval) {
            $direction = $this->nakshatraShoolDirection((int) $interval['index']);
            if ($direction === null) {
                continue;
            }

            $windows[] = [
                'direction' => Localization::translate('String', $direction),
                'direction_key' => $direction,
                'nakshatra' => $interval['name'],
                'start_jd' => $interval['start_jd'],
                'end_jd' => $interval['end_jd'],
                'start' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic((float) $interval['start_jd'], $tz)),
                'end' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic((float) $interval['end_jd'], $tz)),
            ];
        }

        return [
            'rule_system' => 'simple_travel_nakshatra_direction_table',
            'source_family' => 'popular_travel_muhurta_panchang_table',
            'is_complete_system' => true,
            'is_present' => $windows !== [],
            'current' => $windows[0] ?? null,
            'window_count' => count($windows),
            'windows' => $windows,
        ];
    }

    public function calculateYatraScreening(
        int $tithiNumber,
        int $weekdayIndex,
        int $nakshatraIndex,
        int $lagnaSignIndex
    ): array {
        $tithiName = Tithi::from($this->normalizeTithiNumber($tithiNumber))->getName();
        $weekdayName = Vara::from($weekdayIndex)->getName();
        $nakshatraName = Nakshatra::from($nakshatraIndex % 27)->getName();
        $lagnaName = Rasi::from($lagnaSignIndex % 12)->getName();
        $normalizedTithi = $this->normalizeDirectionalTithiNumber($tithiNumber);
        $directionGrid = [];
        $recommendedDirections = [];
        $blockedDirections = [];

        foreach (self::YATRA_DIRECTION_RULES as $direction => $rules) {
            $blockedByTithi = in_array($normalizedTithi, $rules['tithis'], true);
            $blockedByWeekday = in_array($weekdayIndex, $rules['weekdays'], true);
            $blockedByNakshatra = in_array($nakshatraName, $rules['nakshatras'], true);
            $blockedByLagna = in_array($lagnaName, $rules['lagnas'], true);
            $isAllowed = !($blockedByTithi || $blockedByWeekday || $blockedByNakshatra || $blockedByLagna);

            $row = [
                'direction' => Localization::translate('String', $direction),
                'direction_key' => $direction,
                'is_allowed' => $isAllowed,
                'blocked_by' => [
                    'tithi' => $blockedByTithi,
                    'weekday' => $blockedByWeekday,
                    'nakshatra' => $blockedByNakshatra,
                    'lagna' => $blockedByLagna,
                ],
                'matching_rules' => [
                    'tithis' => $rules['tithis'],
                    'weekdays' => array_map(static fn (int $idx): string => Vara::from($idx)->getName(), $rules['weekdays']),
                    'nakshatras' => array_map($this->localizeNakshatraName(...), $rules['nakshatras']),
                    'lagnas' => array_map($this->localizeRasiName(...), $rules['lagnas']),
                ],
            ];

            $directionGrid[] = $row;
            if ($isAllowed) {
                $recommendedDirections[] = Localization::translate('String', $direction);
            } else {
                $blockedDirections[] = Localization::translate('String', $direction);
            }
        }

        return [
            'rule_system' => 'travel_direction_composite_tithi_vara_nakshatra_lagna',
            'source_family' => 'popular_travel_muhurta_nivas_shool_table',
            'is_complete_system' => true,
            'scope' => 'pleasure_and_business_travel_only',
            'urgent_same_day_return_exception' => true,
            'urgent_same_day_return_guidance' => Localization::translate('String', 'Emergency or same-day-return travel may ignore this direction screen.'),
            'pre_sunrise_emergency_window' => Localization::translate('String', 'If travel is urgent and unavoidable, departure between 4 AM and sunrise is preferred.'),
            'current_inputs' => [
                'tithi_number' => $normalizedTithi,
                'tithi_name' => $tithiName,
                'weekday' => $weekdayName,
                'nakshatra' => $nakshatraName,
                'lagna' => $lagnaName,
            ],
            'general_auspicious_travel_nakshatras' => array_map($this->localizeNakshatraName(...), self::YATRA_AUSPICIOUS_NAKSHATRAS),
            'general_auspicious_travel_tithis' => self::YATRA_AUSPICIOUS_TITHIS,
            'recommended_directions' => $recommendedDirections,
            'blocked_directions' => $blockedDirections,
            'direction_grid' => $directionGrid,
        ];
    }

    private function nakshatraShoolDirection(int $nakshatraIndex): ?string
    {
        foreach (self::NAKSHATRA_SHOOL_DIRECTIONS as $dir => $naks) {
            if (in_array($nakshatraIndex, $naks, true)) {
                return $dir;
            }
        }

        return null;
    }

    private function normalizeDirectionalTithiNumber(int $tithiNumber): int
    {
        $normalized = (($tithiNumber - 1) % 30) + 1;

        return $normalized <= 0 ? $normalized + 30 : $normalized;
    }

    private function normalizeTithiNumber(int $tithiNumber): int
    {
        $normalized = (($tithiNumber - 1) % 30) + 1;

        return $normalized <= 0 ? $normalized + 30 : $normalized;
    }

    private function localizeNakshatraName(string $englishName): string
    {
        foreach (Nakshatra::cases() as $case) {
            if (strcasecmp($case->getName('en'), $englishName) === 0) {
                return $case->getName();
            }
        }

        return $englishName;
    }

    private function localizeRasiName(string $englishName): string
    {
        foreach (Rasi::cases() as $case) {
            if (strcasecmp($case->getEnglishName(), $englishName) === 0 || strcasecmp($case->getName('en'), $englishName) === 0) {
                return $case->getName();
            }
        }

        return $englishName;
    }
}
