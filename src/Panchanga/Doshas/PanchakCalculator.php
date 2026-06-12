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
            $weekdayIndex = $this->weekdayIndexFromJd((float) $window['start_jd'], $tz);
            $typeKey = self::WEEKDAY_TYPES[$weekdayIndex] ?? 'Shubha Panchaka';
            $window['start_weekday'] = $this->localizedWeekdayFromJd((float) $window['start_jd'], $tz);
            $window['current_running_weekday'] = $this->localizedWeekdayFromJd((float) $window['visible_start_jd'], $tz);
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

    private function weekdayIndexFromJd(float $jd, string $tz): int
    {
        return $this->carbonFromJd($jd, $tz)->dayOfWeek % 7;
    }

    private function localizedWeekdayFromJd(float $jd, string $tz): string
    {
        return Localization::translate('Vara', $this->weekdayIndexFromJd($jd, $tz));
    }

    private function carbonFromJd(float $jd, string $tz): CarbonImmutable
    {
        $unixSeconds = ($jd - 2440587.5) * 86400.0;
        $wholeSeconds = (int) floor($unixSeconds);
        $microseconds = (int) round(($unixSeconds - $wholeSeconds) * 1_000_000);

        if ($microseconds >= 1_000_000) {
            $wholeSeconds++;
            $microseconds -= 1_000_000;
        } elseif ($microseconds < 0) {
            $wholeSeconds--;
            $microseconds += 1_000_000;
        }

        return CarbonImmutable::createFromTimestampUTC($wholeSeconds)
            ->addMicroseconds($microseconds)
            ->setTimezone($tz);
    }
}
