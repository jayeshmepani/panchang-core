<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Festivals\Utils;

use JayeshMepani\PanchangCore\Core\Localization;

/**
 * Bhadra (Vishti Karana) Engine.
 *
 * Source attributions used by the package:
 * - Traditional Bhadra/Vishti references in Muhūrta Chintāmaṇi and Nirṇaya Sindhu
 * - Modern secondary discussion in Ernst Wilhelm's Classical Muhurta
 *
 * Core Rules:
 * 1. Bhadra = Vishti Karaṇa. Occurs 8 times per lunar month (4 per paksha).
 *    Actual boundaries are computed via Swiss Ephemeris in PanchangService::findBhadraPeriods().
 *
 * 2. Classical Subdivision (Ghatis from start of Bhadra):
 *    - Mukha (Mouth): First 5 Ghatis → Extremely Inauspicious
 *    - Madhya (Body): Middle portion → Inauspicious
 *    - Puchha (Tail): Last 3 Ghatis → Auspicious/Safe for work
 *
 * 3. Bhadravāsa (Lok): Determined by Moon's Rāśi at Bhadra start.
 *    - Svargaloka (Heaven): Aries, Taurus, Gemini, Scorpio → Neutral for Earth
 *    - Patalaloka (Underworld): Virgo, Libra, Sagittarius, Capricorn → Positive for Earth
 *    - Mrityuloka (Earth): Cancer, Leo, Aquarius, Pisces → Strictly Inauspicious
 */
class BhadraEngine
{
    /** Ghatis for Mukha (mouth) from start of Bhadra */
    private const float MUKHA_GHATIS = 5.0;

    /** Ghatis for Puchha (tail) before end of Bhadra */
    private const float PUCHHA_GHATIS = 3.0;

    /**
     * Calculate Bhadra period with classical subdivisions.
     *
     * @param float $sunriseJd Julian Day of sunrise (for relative time display)
     * @param float $vishtiStartJd Julian Day of Vishti Karana start
     * @param float $vishtiEndJd Julian Day of Vishti Karana end
     * @param int $moonRasiIndex Moon's rashi index (0-11) at Bhadra start
     * @param int $tithiIndex Absolute tithi number (1-30)
     * @param string $paksha 'Shukla' or 'Krishna'
     *
     * @return array Bhadra period data with classical subdivisions
     */
    public function calculateBhadra(
        float $sunriseJd,
        float $vishtiStartJd,
        float $vishtiEndJd,
        int $moonRasiIndex,
        int $tithiIndex,
        string $paksha
    ): array {
        $durationJd = $vishtiEndJd - $vishtiStartJd;
        $durationGhatis = $durationJd * 60.0; // 1 day = 60 ghatis

        // Classical ghati-to-JD conversion (1 ghati = 1/60 of a day)
        $ghatiToJd = 1.0 / 60.0;

        // Classical boundaries
        $mukhaEndJd = $vishtiStartJd + (self::MUKHA_GHATIS * $ghatiToJd);
        $puchhaStartJd = $vishtiEndJd - (self::PUCHHA_GHATIS * $ghatiToJd);

        // Clamp to actual Bhadra boundaries
        $mukhaEndJd = min($mukhaEndJd, $vishtiEndJd);
        $puchhaStartJd = max($puchhaStartJd, $vishtiStartJd);

        // If Bhadra is shorter than 8 ghatis, mukha and puchha may overlap;
        // in that case the entire period is inauspicious (no safe tail).
        $hasPuchha = $puchhaStartJd > $mukhaEndJd;

        $jdToTime = function (float $jd) use ($sunriseJd): string {
            $seconds = ($jd - $sunriseJd) * 86400.0;
            $hours = (int) floor(abs($seconds) / 3600);
            $minutes = (int) floor(fmod(abs($seconds), 3600) / 60);
            $secs = (int) floor(fmod(abs($seconds), 60));
            $sign = $seconds < 0 ? '-' : '';
            return sprintf('%s%02d:%02d:%02d', $sign, $hours, $minutes, $secs);
        };

        $lok = $this->getBhadraLok($moonRasiIndex);

        $parts = [
            'mukha' => [
                'start_time' => $jdToTime($vishtiStartJd),
                'end_time' => $jdToTime($mukhaEndJd),
                'ghatis' => round(min(self::MUKHA_GHATIS, $durationGhatis), 2),
                'status' => 'Extremely Inauspicious',
            ],
        ];

        // Madhya (body) only exists if there is space between mukha end and puchha start
        if ($hasPuchha) {
            $parts['madhya'] = [
                'start_time' => $jdToTime($mukhaEndJd),
                'end_time' => $jdToTime($puchhaStartJd),
                'ghatis' => round(($puchhaStartJd - $mukhaEndJd) * 60.0, 2),
                'status' => 'Inauspicious',
            ];
            $parts['puchha'] = [
                'start_time' => $jdToTime($puchhaStartJd),
                'end_time' => $jdToTime($vishtiEndJd),
                'ghatis' => round(self::PUCHHA_GHATIS, 2),
                'status' => 'Auspicious/Safe',
            ];
        }

        return [
            'has_bhadra' => true,
            'lok' => $lok['name'],
            'lok_description' => $lok['description'],
            'impact_on_earth' => $lok['impact'],
            'start_jd' => $vishtiStartJd,
            'end_jd' => $vishtiEndJd,
            'start_time' => $jdToTime($vishtiStartJd),
            'end_time' => $jdToTime($vishtiEndJd),
            'duration_ghatis' => round($durationGhatis, 2),
            'tithi' => $tithiIndex,
            'paksha' => $paksha,
            'parts' => $parts,
        ];
    }

    /**
     * Determine Bhadravāsa (Bhadra's dwelling) from Moon's Rāśi.
     *
     * Classical rule (Muhūrta Chintāmaṇi):
     * - Svargaloka: Aries(0), Taurus(1), Gemini(2), Scorpio(7)
     * - Patalaloka: Virgo(5), Libra(6), Sagittarius(8), Capricorn(9)
     * - Mrityuloka: Cancer(3), Leo(4), Aquarius(10), Pisces(11)
     */
    private function getBhadraLok(int $rasi): array
    {
        // Svargaloka: Aries(0), Taurus(1), Gemini(2), Scorpio(7)
        if (in_array($rasi, [0, 1, 2, 7], true)) {
            return [
                'name' => 'Svargaloka',
                'description' => Localization::translate('String', 'Bhadra resides in Heaven'),
                'impact' => Localization::translate('String', 'Neutral — no direct harm on Earth'),
            ];
        }

        // Patalaloka: Virgo(5), Libra(6), Sagittarius(8), Capricorn(9)
        if (in_array($rasi, [5, 6, 8, 9], true)) {
            return [
                'name' => 'Patalaloka',
                'description' => Localization::translate('String', 'Bhadra resides in the Underworld'),
                'impact' => Localization::translate('String', 'Positive for Earth'),
            ];
        }

        // Mrityuloka: Cancer(3), Leo(4), Aquarius(10), Pisces(11)
        return [
            'name' => 'Mrityuloka',
            'description' => Localization::translate('String', 'Bhadra resides on Earth'),
            'impact' => Localization::translate('String', 'Strictly Inauspicious — avoid all auspicious work'),
        ];
    }
}
