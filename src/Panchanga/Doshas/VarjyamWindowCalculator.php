<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Panchanga\Doshas;

use JayeshMepani\PanchangCore\Astronomy\Math\TransitEngine;
use JayeshMepani\PanchangCore\Core\Enums\Nakshatra;

/** Varjyam Window Calculator - Handles Tyajyam period calculations. */
class VarjyamWindowCalculator
{
    private const array TYAJYA_START_PERCENTAGES = [
        0 => 83.33, 1 => 23.33, 2 => 50.00, 3 => 66.67, 4 => 23.33,
        5 => 30.00, 6 => 50.00, 7 => 33.33, 8 => 53.33, 9 => 50.00,
        10 => 30.00, 11 => 33.33, 12 => 33.33, 13 => 30.00, 14 => 23.33,
        15 => 23.33, 16 => 16.67, 17 => 93.33, 18 => 40.00, 19 => 33.33,
        20 => 40.00, 21 => 33.33, 22 => 16.67, 23 => 16.67, 24 => 30.00,
        25 => 30.00, 26 => 50.00,
    ];

    public function __construct(private readonly TransitEngine $transitEngine)
    {
    }

    public function calculateVarjyamWindows(
        float $jdSunrise,
        float $jdNextSunrise,
        int $nakIdxAtSunrise,
        float $nakStartJdAtSunrise,
        float $nakEndJdAtSunrise
    ): array {
        $windows = [];
        $nakshatraSpan = 360.0 / 27.0;

        $currentNakIdx = $nakIdxAtSunrise;
        $currentNakStartJd = $nakStartJdAtSunrise;
        $currentNakEndJd = $nakEndJdAtSunrise;

        // Check up to 4 potential nakshatras overlapping the day
        for ($guard = 0; $guard < 4; $guard++) {
            $tyajyaStartPercent = self::TYAJYA_START_PERCENTAGES[$currentNakIdx];
            $durationJd = $currentNakEndJd - $currentNakStartJd;
            $tyajyaStartJd = $currentNakStartJd + ($durationJd * $tyajyaStartPercent / 100.0);
            $tyajyaEndJd = $tyajyaStartJd + ($durationJd * 4.0 / 60.0); // 4 Ghatis duration

            $actualStart = max($jdSunrise, $tyajyaStartJd);
            $actualEnd = min($jdNextSunrise, $tyajyaEndJd);

            if ($actualStart < $actualEnd) {
                $windows[] = [
                    'start_jd' => $actualStart,
                    'end_jd' => $actualEnd,
                    'nakshatra' => Nakshatra::from($currentNakIdx)->getName(),
                ];
            }

            $currentNakIdx = ($currentNakIdx + 1) % 27;
            $currentNakStartJd = $currentNakEndJd;
            $targetAngle = (($currentNakIdx + 1) % 27) * $nakshatraSpan;
            $currentNakEndJd = $this->transitEngine->findAngleCrossing(
                $currentNakStartJd + 1e-6,
                $targetAngle,
                1,
                fn (float $jd): float => $this->transitEngine->getMoonLongitude($jd)
            );

            if ($currentNakEndJd <= $currentNakStartJd) {
                break;
            }

            if ($currentNakStartJd >= $jdNextSunrise) {
                break;
            }
        }

        return $windows;
    }
}
