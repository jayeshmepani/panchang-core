<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Panchanga\Doshas;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Astronomy\Math\IntervalTracker;
use JayeshMepani\PanchangCore\Core\Localization;

/** Panchak Calculator - Handles moon longitude range detection. */
class PanchakCalculator
{
    private const array WEEKDAY_TYPES = [
        0 => 'Roga Panchaka',
        1 => 'Raja Panchaka',
        2 => 'Agni Panchaka',
        5 => 'Chora Panchaka',
        6 => 'Mrityu Panchaka',
    ];

    public function __construct(private readonly IntervalTracker $intervalTracker)
    {
    }

    public function calculatePanchak(float $jdStart, float $jdEnd, string $tz): array
    {
        $windows = $this->intervalTracker->collectMoonLongitudeRangeWindows($jdStart, $jdEnd, 300.0, 360.0, $tz);

        foreach ($windows as &$window) {
            $window['range'] = 'Dhanishta Pada 3 through Revati';
            $window['range_key'] = 'dhanishta_pada_3_to_revati';
            $weekdayIndex = $this->weekdayIndexFromFormattedDateTime((string) $window['start'], $tz);
            $typeKey = self::WEEKDAY_TYPES[$weekdayIndex] ?? 'Shubha Panchaka';
            $window['start_weekday'] = $this->localizedWeekdayFromFormattedDateTime((string) $window['start'], $tz);
            $window['current_running_weekday'] = $this->localizedWeekdayFromFormattedDateTime((string) $window['visible_start'], $tz);
            $window['weekday_type'] = Localization::translate('Panchaka', $typeKey);
            $window['weekday_type_key'] = $typeKey;
            $window['classification_lock'] = 'entry_weekday';
        }

        unset($window);

        return [
            'rule_system' => 'moon_dhanishta_pada_3_to_revati',
            'weekday_type_rule_system' => 'panchak_start_weekday_type_table',
            'is_complete_system' => true,
            'moon_longitude_start' => 300.0,
            'moon_longitude_end' => 360.0,
            'is_present' => $windows !== [],
            'window_count' => count($windows),
            'current_weekday_type' => $windows[0]['weekday_type'] ?? null,
            'current_weekday_type_key' => $windows[0]['weekday_type_key'] ?? null,
            'windows' => $windows,
        ];
    }

    private function weekdayIndexFromFormattedDateTime(string $dateTime, string $tz): int
    {
        $carbon = CarbonImmutable::createFromFormat('d/m/Y h:i:s A', $dateTime, $tz);
        if (!$carbon instanceof CarbonImmutable) {
            return 0;
        }

        return $carbon->dayOfWeek % 7;
    }

    private function localizedWeekdayFromFormattedDateTime(string $dateTime, string $tz): string
    {
        $carbon = CarbonImmutable::createFromFormat('d/m/Y h:i:s A', $dateTime, $tz);
        if (!$carbon instanceof CarbonImmutable) {
            return '';
        }

        return Localization::translate('Vara', $carbon->dayOfWeek % 7);
    }
}
