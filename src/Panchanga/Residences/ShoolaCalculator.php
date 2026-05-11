<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Panchanga\Residences;

use JayeshMepani\PanchangCore\Astronomy\SunService;
use JayeshMepani\PanchangCore\Core\AstroCore;
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
            'remedies' => $hasDishaShool ? [
                Localization::translate('String', 'Consume or carry jaggery, curd, or barley before travel.'),
                Localization::translate('String', 'Begin travel in an auspicious muhurta if direction cannot be changed.'),
                Localization::translate('String', 'Offer prayer to Ganesha or Ishta Devata before departure.'),
            ] : [],
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

    private function nakshatraShoolDirection(int $nakshatraIndex): ?string
    {
        foreach (self::NAKSHATRA_SHOOL_DIRECTIONS as $dir => $naks) {
            if (in_array($nakshatraIndex, $naks, true)) {
                return $dir;
            }
        }

        return null;
    }
}
