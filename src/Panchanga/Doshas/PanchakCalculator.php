<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Panchanga\Doshas;

use JayeshMepani\PanchangCore\Astronomy\Math\IntervalTracker;

/** Panchak Calculator - Handles moon longitude range detection. */
class PanchakCalculator
{
    public function __construct(private readonly IntervalTracker $intervalTracker)
    {
    }

    public function calculatePanchak(float $jdStart, float $jdEnd, string $tz): array
    {
        $windows = $this->intervalTracker->collectMoonLongitudeRangeWindows($jdStart, $jdEnd, 300.0, 360.0, $tz);

        foreach ($windows as &$window) {
            $window['range'] = 'Dhanishta Pada 3 through Revati';
            $window['range_key'] = 'dhanishta_pada_3_to_revati';
        }

        unset($window);

        return [
            'rule_system' => 'moon_dhanishta_pada_3_to_revati',
            'is_complete_system' => true,
            'moon_longitude_start' => 300.0,
            'moon_longitude_end' => 360.0,
            'is_present' => $windows !== [],
            'window_count' => count($windows),
            'windows' => $windows,
        ];
    }
}
