<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Festivals\Utils;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Core\Constants\ClassicalTimeConstants;

/**
 * Bhadra (Vishti Karaṇa) Calculation Engine.
 *
 * Classical References:
 * - Nirṇaya Sindhu 1.4.15 - Bhadra avoidance rules
 * - Muhūrta Chintāmaṇi 67 - Bhadra duration and parts
 * - Dharma Sindhu 2.3.15 - Holika Dahan Bhadra exception
 *
 * Bhadra is an inauspicious period that occurs during specific karaṇa periods.
 * Critical for festival timing - many festivals (Holika Dahan, Raksha Bandhan)
 * must avoid Bhadra or have specific rules about Bhadra overlap.
 */
class BhadraEngine
{
    /**
     * Bhadra duration in ghaṭikās (24 minutes each)
     * Varies by weekday.
     *
     * Source: Muhūrta Chintāmaṇi 67
     */
    private const BHADRA_DURATION_GHATI = [
        0 => 4.0,  // Sunday
        1 => 2.0,  // Monday
        2 => 3.0,  // Tuesday
        3 => 2.0,  // Wednesday
        4 => 4.0,  // Thursday
        5 => 2.0,  // Friday
        6 => 3.0,  // Saturday
    ];

    /**
     * Bhadra start offset from sunrise in ghaṭikās
     * Varies by weekday and pakṣa.
     *
     * Source: Nirṇaya Sindhu 1.4.15
     */
    private const BHADRA_START_SHUKLA = [
        0 => 0.0,   // Sunday: starts at sunrise
        1 => 6.0,   // Monday: starts 6 ghaṭikās after sunrise
        2 => 3.0,   // Tuesday: starts 3 ghaṭikās after sunrise
        3 => 1.0,   // Wednesday: starts 1 ghaṭikā after sunrise
        4 => 5.0,   // Thursday: starts 5 ghaṭikās after sunrise
        5 => 2.0,   // Friday: starts 2 ghaṭikās after sunrise
        6 => 4.0,   // Saturday: starts 4 ghaṭikās after sunrise
    ];

    private const BHADRA_START_KRISHNA = [
        0 => 5.0,   // Sunday
        1 => 1.0,   // Monday
        2 => 6.0,   // Tuesday
        3 => 4.0,   // Wednesday
        4 => 2.0,   // Thursday
        5 => 7.0,   // Friday
        6 => 3.0,   // Saturday
    ];

    /**
     * Calculate Bhadra period for a given date and location.
     *
     * @param CarbonImmutable $date Date to calculate for
     * @param float $sunriseJd Sunrise Julian Day
     * @param float $sunsetJd Sunset Julian Day
     * @param int $weekday Weekday (0=Sunday, 1=Monday, etc.)
     * @param string $paksha Paksha (Shukla or Krishna)
     * @param int $tithi Tithi number (1-15)
     *
     * @return array Bhadra period information
     */
    public function calculateBhadra(
        CarbonImmutable $date,
        float $sunriseJd,
        float $sunsetJd,
        int $weekday,
        string $paksha,
        int $tithi
    ): array {
        // Only occurs on specific tithis (mainly 6, 8, 10, 12, 14)
        // Source: Nirṇaya Sindhu 1.4.15
        $hasBhadra = in_array($tithi, ClassicalTimeConstants::BHADRA_TITHIS, true);

        if (!$hasBhadra) {
            return [
                'has_bhadra' => false,
                'bhadra_tithi' => false,
                'reason' => 'Bhadra only occurs on tithis ' . implode(', ', ClassicalTimeConstants::BHADRA_TITHIS),
            ];
        }

        // Get duration and start offset
        $durationGhati = self::BHADRA_DURATION_GHATI[$weekday] ?? 2.0;
        $startOffsets = $paksha === 'Shukla' ? self::BHADRA_START_SHUKLA : self::BHADRA_START_KRISHNA;
        $startGhati = $startOffsets[$weekday] ?? 0.0;

        // Convert to JD (exact: 1 ghaṭikā = 1/60 day)
        $ghatiToJd = ClassicalTimeConstants::GHATIKA_PER_DAY;
        $bhadraStartJd = $sunriseJd + ($startGhati * $ghatiToJd);
        $bhadraEndJd = $bhadraStartJd + ($durationGhati * $ghatiToJd);

        // Calculate parts (Mukha = 40%, Punchha = 60%)
        // Source: Muhūrta Chintāmaṇi 67
        $mukhaEndJd = $bhadraStartJd + (($durationGhati * 0.4) * $ghatiToJd);
        $punchhaStartJd = $bhadraStartJd + (($durationGhati * 0.6) * $ghatiToJd);

        // Convert to times (exact: 1 day = 86400 seconds)
        $jdToTime = function (float $jd) use ($sunriseJd): string {
            $seconds = ($jd - $sunriseJd) * ClassicalTimeConstants::SECONDS_PER_DAY;
            $hours = (int) floor($seconds / ClassicalTimeConstants::SECONDS_PER_HOUR);
            $minutes = (int) floor(fmod($seconds, ClassicalTimeConstants::SECONDS_PER_HOUR) / ClassicalTimeConstants::SECONDS_PER_MINUTE);
            $secs = (int) floor(fmod($seconds, ClassicalTimeConstants::SECONDS_PER_MINUTE));
            return sprintf('%02d:%02d:%02d', max(0, $hours), max(0, $minutes), max(0, $secs));
        };

        return [
            'has_bhadra' => true,
            'bhadra_tithi' => true,
            'tithi' => $tithi,
            'paksha' => $paksha,
            'weekday' => $weekday,
            'duration_ghati' => $durationGhati,
            'duration_minutes' => $durationGhati * ClassicalTimeConstants::GHATIKA_IN_MINUTES,
            'start_ghati' => $startGhati,
            'bhadra_start_jd' => $bhadraStartJd,
            'bhadra_end_jd' => $bhadraEndJd,
            'bhadra_start_time' => $jdToTime($bhadraStartJd),
            'bhadra_end_time' => $jdToTime($bhadraEndJd),
            'mukha' => [
                'start_jd' => $bhadraStartJd,
                'end_jd' => $mukhaEndJd,
                'start_time' => $jdToTime($bhadraStartJd),
                'end_time' => $jdToTime($mukhaEndJd),
                'duration_minutes' => ($durationGhati * 0.4) * ClassicalTimeConstants::GHATIKA_IN_MINUTES,
            ],
            'punchha' => [
                'start_jd' => $punchhaStartJd,
                'end_jd' => $bhadraEndJd,
                'start_time' => $jdToTime($punchhaStartJd),
                'end_time' => $jdToTime($bhadraEndJd),
                'duration_minutes' => ($durationGhati * 0.6) * ClassicalTimeConstants::GHATIKA_IN_MINUTES,
            ],
            'festival_impact' => $this->getFestivalImpact($tithi, $paksha),
        ];
    }

    /**
     * Check if a time period overlaps with Bhadra.
     *
     * @param float $periodStartJd Start of period in JD
     * @param float $periodEndJd End of period in JD
     * @param array $bhadraData Bhadra data from calculateBhadra()
     * @param string $partToCheck Which part to check: 'full', 'mukha', 'punchha'
     *
     * @return array Overlap information
     */
    public function checkBhadraOverlap(
        float $periodStartJd,
        float $periodEndJd,
        array $bhadraData,
        string $partToCheck = 'full'
    ): array {
        if (!$bhadraData['has_bhadra']) {
            return [
                'overlaps' => false,
                'reason' => 'No Bhadra on this tithi',
            ];
        }

        // Get Bhadra boundaries based on part to check
        $bhadraBoundaries = match ($partToCheck) {
            'mukha' => [
                'start' => $bhadraData['mukha']['start_jd'],
                'end' => $bhadraData['mukha']['end_jd'],
            ],
            'punchha' => [
                'start' => $bhadraData['punchha']['start_jd'],
                'end' => $bhadraData['punchha']['end_jd'],
            ],
            default => [
                'start' => $bhadraData['bhadra_start_jd'],
                'end' => $bhadraData['bhadra_end_jd'],
            ],
        };

        // Check overlap
        $overlaps = $periodStartJd < $bhadraBoundaries['end'] && $periodEndJd > $bhadraBoundaries['start'];

        if (!$overlaps) {
            return [
                'overlaps' => false,
                'reason' => 'Period does not overlap Bhadra',
            ];
        }

        // Calculate overlap extent (exact)
        $overlapStart = max($periodStartJd, $bhadraBoundaries['start']);
        $overlapEnd = min($periodEndJd, $bhadraBoundaries['end']);
        $overlapDuration = ($overlapEnd - $overlapStart) * ClassicalTimeConstants::MINUTES_PER_DAY;

        // Calculate non-Bhadra portion
        $nonBhadraStart = max($periodStartJd, $bhadraBoundaries['end']);
        $nonBhadraEnd = min($periodEndJd, $bhadraBoundaries['start']);
        $hasNonBhadraPortion = $nonBhadraStart < $periodEndJd || $periodStartJd < $bhadraBoundaries['start'];

        return [
            'overlaps' => true,
            'overlap_start_jd' => $overlapStart,
            'overlap_end_jd' => $overlapEnd,
            'overlap_duration_minutes' => $overlapDuration,
            'has_non_bhadra_portion' => $hasNonBhadraPortion,
            'recommendation' => $this->getBhadraRecommendation($overlapDuration, $partToCheck),
        ];
    }

    /**
     * Calculate optimal festival time avoiding Bhadra.
     *
     * @param float $preferredStartJd Preferred start time in JD
     * @param float $preferredEndJd Preferred end time in JD
     * @param array $bhadraData Bhadra data
     * @param string $partToCheck Which Bhadra part to avoid
     *
     * @return array Optimal timing recommendation
     */
    public function calculateOptimalTime(
        float $preferredStartJd,
        float $preferredEndJd,
        array $bhadraData,
        string $partToCheck = 'full'
    ): array {
        if (!$bhadraData['has_bhadra']) {
            return [
                'optimal_start_jd' => $preferredStartJd,
                'optimal_end_jd' => $preferredEndJd,
                'reason' => 'No Bhadra - use preferred times',
            ];
        }

        $overlap = $this->checkBhadraOverlap($preferredStartJd, $preferredEndJd, $bhadraData, $partToCheck);

        if (!$overlap['overlaps']) {
            return [
                'optimal_start_jd' => $preferredStartJd,
                'optimal_end_jd' => $preferredEndJd,
                'reason' => 'No overlap with Bhadra',
            ];
        }

        // Calculate alternative windows
        $beforeBhadra = match ($partToCheck) {
            'mukha' => $bhadraData['mukha']['start_jd'],
            'punchha' => $bhadraData['punchha']['start_jd'],
            default => $bhadraData['bhadra_start_jd'],
        };

        $afterBhadra = match ($partToCheck) {
            'mukha' => $bhadraData['mukha']['end_jd'],
            'punchha' => $bhadraData['punchha']['end_jd'],
            default => $bhadraData['bhadra_end_jd'],
        };

        $windowBefore = $beforeBhadra - $preferredStartJd;
        $windowAfter = $preferredEndJd - $afterBhadra;

        if ($windowBefore > 0 && $windowBefore >= $windowAfter) {
            return [
                'optimal_start_jd' => $preferredStartJd,
                'optimal_end_jd' => $beforeBhadra,
                'reason' => 'Before Bhadra starts',
                'duration_minutes' => $windowBefore * ClassicalTimeConstants::MINUTES_PER_DAY,
            ];
        }

        if ($windowAfter > 0) {
            return [
                'optimal_start_jd' => $afterBhadra,
                'optimal_end_jd' => $preferredEndJd,
                'reason' => 'After Bhadra ends',
                'duration_minutes' => $windowAfter * ClassicalTimeConstants::MINUTES_PER_DAY,
            ];
        }

        return [
            'optimal_start_jd' => $afterBhadra,
            'optimal_end_jd' => $afterBhadra + (14.0 / ClassicalTimeConstants::MINUTES_PER_DAY), // Minimum 14 minutes
            'reason' => 'No optimal window - minimal time after Bhadra',
            'warning' => 'Consider alternative date',
        ];
    }

    /** Get festival-specific Bhadra impact */
    private function getFestivalImpact(int $tithi, string $paksha): array
    {
        $impacts = [];

        // Holika Dahan (Purnima, but Bhadra rules apply to preceding Trayodashi)
        if ($tithi === 14 && $paksha === 'Krishna') {
            $impacts[] = [
                'festival' => 'Holika Dahan',
                'rule' => 'Must avoid Bhadra during Pradosh Kaal',
                'exception' => 'If Bhadra extends into Pradosh, wait until Bhadra ends',
            ];
        }

        // Raksha Bandhan (Purnima, but Bhadra rules apply)
        if ($tithi === 15 && $paksha === 'Shukla') {
            $impacts[] = [
                'festival' => 'Raksha Bandhan',
                'rule' => 'Avoid Bhadra for thread ceremony',
                'exception' => 'Bhadra Punchha acceptable in some traditions',
            ];
        }

        // General Bhadra-sensitive festivals
        if (in_array($tithi, [6, 8, 10, 12], true)) {
            $impacts[] = [
                'festival' => 'General',
                'rule' => 'Auspicious activities should avoid Bhadra',
                'exception' => 'Nitya karma not affected',
            ];
        }

        return $impacts;
    }

    /** Get Bhadra avoidance recommendation */
    private function getBhadraRecommendation(float $overlapMinutes, string $partToCheck): string
    {
        // Threshold: 10 minutes (minimal)
        if ($overlapMinutes < 10) {
            return 'Minimal overlap - may proceed with caution';
        }

        // Threshold: 30 minutes (significant)
        if ($overlapMinutes < 30) {
            return match ($partToCheck) {
                'mukha' => 'Consider waiting until Mukha ends',
                'punchha' => 'Consider starting before Punchha begins',
                default => 'Short overlap - evaluate based on festival urgency',
            };
        }

        return match ($partToCheck) {
            'mukha' => 'Significant Mukha overlap - wait until Mukha ends',
            'punchha' => 'Significant Punchha overlap - start before Punchha begins',
            default => 'Significant Bhadra overlap - reschedule if possible',
        };
    }
}
