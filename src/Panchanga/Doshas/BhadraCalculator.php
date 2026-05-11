<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Panchanga\Doshas;

use JayeshMepani\PanchangCore\Astronomy\Math\TransitEngine;
use JayeshMepani\PanchangCore\Festivals\Utils\BhadraEngine;

/** Bhadra Calculator - Handles Vishti Karana period detection. */
class BhadraCalculator
{
    public function __construct(
        private readonly TransitEngine $transitEngine,
        private readonly BhadraEngine $bhadraEngine
    ) {
    }

    public function findBhadraPeriods(float $jdStart, float $jdEnd, int $sunriseTithi, string $paksha): array
    {
        $vishtiKaranas = [8, 15, 22, 29, 36, 43, 50, 57];
        $bhadraPeriods = [];

        // Check the range for any Karana transition into Vishti
        $currentJd = $jdStart;
        while ($currentJd < $jdEnd) {
            $angle = $this->transitEngine->getMoonSunAngle($currentJd);
            $karanaNum = (int) floor($angle / 6.0) + 1;

            if (in_array($karanaNum, $vishtiKaranas, true)) {
                $vStartAngle = ($karanaNum - 1) * 6.0;
                $vEndAngle = $karanaNum * 6.0;

                $vStartJd = $this->transitEngine->findAngleCrossing($currentJd, $vStartAngle, -1, fn (float $jd): float => $this->transitEngine->getMoonSunAngle($jd));
                $vEndJd = $this->transitEngine->findAngleCrossing($currentJd, $vEndAngle, 1, fn (float $jd): float => $this->transitEngine->getMoonSunAngle($jd));

                // Constrain to the day
                $actualStart = max($jdStart, $vStartJd);
                $actualEnd = min($jdEnd, $vEndJd);

                if ($actualStart < $actualEnd) {
                    $moonRasi = (int) floor($this->transitEngine->getMoonLongitude($actualStart) / 30.0);
                    $bhadraPeriods[] = $this->bhadraEngine->calculateBhadra(
                        $jdStart,
                        $vStartJd,
                        $vEndJd,
                        $moonRasi,
                        $sunriseTithi,
                        $paksha
                    );
                }

                $currentJd = $vEndJd + 0.01; // Move past this Karana
            } else {
                // Find next Karana crossing
                $nextKaranaAngle = ceil(($angle + 0.0001) / 6.0) * 6.0;
                $nextKaranaJd = $this->transitEngine->findAngleCrossing($currentJd, $nextKaranaAngle, 1, fn (float $jd): float => $this->transitEngine->getMoonSunAngle($jd));
                $currentJd = $nextKaranaJd + 0.001;
            }
        }

        return $bhadraPeriods;
    }
}
