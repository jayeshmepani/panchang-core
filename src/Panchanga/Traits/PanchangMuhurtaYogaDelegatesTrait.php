<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Panchanga\Traits;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Core\Enums\Nakshatra;
use JayeshMepani\PanchangCore\Core\Enums\Rasi;
use JayeshMepani\PanchangCore\Core\Enums\Tithi;
use JayeshMepani\PanchangCore\Core\Localization;

trait PanchangMuhurtaYogaDelegatesTrait
{
    private function calculateVarjyamWindows(
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise,
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

        for ($i = 0; $i < 4; $i++) {
            if ($currentNakStartJd >= $jdNextSunrise) {
                break;
            }

            $window = $this->muhurta->calculateVarjyam(
                $sunrise,
                $sunset,
                $nextSunrise,
                $currentNakIdx,
                $currentNakStartJd,
                $currentNakEndJd
            );

            $windowStartJd = $window['nakshatra_start_jd'] + (($window['tyajya_ghati_start'] / 60.0) * ($window['nakshatra_end_jd'] - $window['nakshatra_start_jd']));
            $windowEndJd = $windowStartJd + (($window['tyajya_ghati_end'] - $window['tyajya_ghati_start']) / 60.0) * ($window['nakshatra_end_jd'] - $window['nakshatra_start_jd']);

            if ($windowEndJd > $jdSunrise && $windowStartJd < $jdNextSunrise) {
                $window['window_start_jd'] = $windowStartJd;
                $window['window_end_jd'] = $windowEndJd;
                $windows[] = $window;
            }

            $currentNakIdx = ($currentNakIdx + 1) % 27;
            $currentNakStartJd = $currentNakEndJd;
            $targetAngle = (($currentNakIdx + 1) % 27) * $nakshatraSpan;
            $currentNakEndJd = $this->findAngleCrossing(
                $currentNakStartJd + 1e-6,
                $targetAngle,
                1,
                fn (float $jd): float => $this->getMoonLongitude($jd)
            );

            if ($currentNakEndJd <= $currentNakStartJd) {
                break;
            }
        }

        usort(
            $windows,
            static fn (array $a, array $b): int => $a['window_start_jd'] <=> $b['window_start_jd']
        );

        return $windows;
    }

    /** Backward-compatible Varjyam payload with multi-window support. */
    private function buildVarjyamPayload(array $windows): array
    {
        if ($windows === []) {
            return [
                'is_available' => false,
                'window_count' => 0,
                'windows' => [],
            ];
        }

        $primary = $windows[0];

        return [
            ...$primary,
            'is_available' => true,
            'window_count' => count($windows),
            'windows' => $windows,
        ];
    }

    /** Calculate Pradosha Kaal using first 1/5th of night and Trayodashi overlap logic. */
    private function calculatePradoshaKaal(
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise,
        float $jdSunset,
        float $jdNextSunrise,
        string $tz
    ): array {
        $nightDurationJd = $jdNextSunrise - $jdSunset;
        $pradoshaEndJd = $jdSunset + ($nightDurationJd / 5.0);

        $trayodashiOverlaps = [];
        $cursor = $jdSunset + 1e-7;

        for ($i = 0; $i < 6 && $cursor < $pradoshaEndJd; $i++) {
            $interval = $this->getTithiIntervalAtJd($cursor);
            $tithiIndex = $interval['index'];
            $tithiPhase = $tithiIndex > 15 ? $tithiIndex - 15 : $tithiIndex;

            $overlapStartJd = max($interval['start_jd'], $jdSunset);
            $overlapEndJd = min($interval['end_jd'], $pradoshaEndJd);

            if ($tithiPhase === 13 && $overlapEndJd > $overlapStartJd) {
                $trayodashiOverlaps[] = [
                    'start_jd' => $overlapStartJd,
                    'end_jd' => $overlapEndJd,
                    'start' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($overlapStartJd, $tz)),
                    'end' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($overlapEndJd, $tz)),
                    'duration_minutes' => ($overlapEndJd - $overlapStartJd) * 1440.0,
                ];
            }

            $nextCursor = max($interval['end_jd'] + 1e-6, $cursor + 1e-5);
            if ($nextCursor <= $cursor) {
                break;
            }

            $cursor = $nextCursor;
        }

        $trayodashiDurationMinutes =
            array_reduce(
                $trayodashiOverlaps,
                static fn (float $carry, array $row): float => $carry + $row['duration_minutes'],
                0.0
            );

        $hasTrayodashiOverlap = $trayodashiOverlaps !== [];
        $basePradoshaDurationMinutes = ($pradoshaEndJd - $jdSunset) * 1440.0;
        $effectiveStartJd = $hasTrayodashiOverlap
            ? min(array_column($trayodashiOverlaps, 'start_jd'))
            : $jdSunset;
        $effectiveEndJd = $hasTrayodashiOverlap
            ? max(array_column($trayodashiOverlaps, 'end_jd'))
            : $pradoshaEndJd;
        $effectiveDurationMinutes = ($effectiveEndJd - $effectiveStartJd) * 1440.0;

        $baseStart = $sunset;
        $baseEnd = $this->sunService->jdToCarbonPublic($pradoshaEndJd, $tz);
        $effectiveStart = $this->sunService->jdToCarbonPublic($effectiveStartJd, $tz);
        $effectiveEnd = $this->sunService->jdToCarbonPublic($effectiveEndJd, $tz);

        return [
            'pradosha_start' => AstroCore::formatTime($effectiveStart),
            'pradosha_end' => AstroCore::formatTime($effectiveEnd),
            'pradosha_start_iso' => AstroCore::formatDateTime($effectiveStart),
            'pradosha_end_iso' => AstroCore::formatDateTime($effectiveEnd),
            'sunset' => AstroCore::formatTime($sunset),
            'duration_minutes' => $effectiveDurationMinutes,
            'base_pradosha_start' => AstroCore::formatTime($baseStart),
            'base_pradosha_end' => AstroCore::formatTime($baseEnd),
            'base_pradosha_start_iso' => AstroCore::formatDateTime($baseStart),
            'base_pradosha_end_iso' => AstroCore::formatDateTime($baseEnd),
            'base_duration_minutes' => $basePradoshaDurationMinutes,
            'is_trayodashi' => $hasTrayodashiOverlap,
            'is_auspicious' => $hasTrayodashiOverlap,
            'trayodashi_overlap_minutes' => $trayodashiDurationMinutes,
            'trayodashi_overlaps' => $trayodashiOverlaps,
            'significance' => $hasTrayodashiOverlap
                ? Localization::translate('String', 'Trayodashi overlaps Pradosha Kaal; this is Pradosh-observance eligible.')
                : Localization::translate('String', 'No Trayodashi overlap in Pradosha Kaal for this day.'),
        ];
    }

    private function calculateSpecialYogas(
        CarbonImmutable $date,
        float $jdStart,
        float $jdEnd,
        int $sunriseTithi,
        int $weekdayIndex,
        string $tz
    ): array {
        return $this->specialYogaCalculator->calculateSpecialYogas($date, $jdStart, $jdEnd, $sunriseTithi, $weekdayIndex, $tz);
    }

    private function calculateAnandadiYoga(float $jdStart, float $jdEnd, int $weekdayIndex, string $tz): array
    {
        return $this->specialYogaCalculator->calculateAnandadiYoga($jdStart, $jdEnd, $weekdayIndex, $tz);
    }

    private function calculateAmritadiYoga(float $jdStart, float $jdEnd, int $weekdayIndex, string $tz): array
    {
        return $this->specialYogaCalculator->calculateAmritadiYoga($jdStart, $jdEnd, $weekdayIndex, $tz);
    }

    private function calculatePanchak(float $jdStart, float $jdEnd, string $tz): array
    {
        return $this->panchakCalculator->calculatePanchak($jdStart, $jdEnd, $tz);
    }

    private function calculateMaitreyaYoga(
        float $jdStart,
        float $jdEnd,
        int $weekdayIndex,
        array $lagnaTable,
        string $tz
    ): array {
        return $this->specialYogaCalculator->calculateMaitreyaYoga($jdStart, $jdEnd, $weekdayIndex, $lagnaTable, $tz);
    }

    private function calculateGajachchhayaYoga(float $jdStart, float $jdEnd, array $hinduMonth, string $tz): array
    {
        return $this->specialYogaCalculator->calculateGajachchhayaYoga($jdStart, $jdEnd, $hinduMonth, $tz);
    }

    private function calculateNakshatraShool(float $jdStart, float $jdEnd, string $tz): array
    {
        return $this->shoolaCalculator->calculateNakshatraShool(
            $this->collectNakshatraIntervals($jdStart, $jdEnd),
            $tz
        );
    }

    private function calculateDishaShool(int $weekdayIndex): array
    {
        return $this->shoolaCalculator->calculateDishaShool($weekdayIndex);
    }

    private function calculateRahuVaasa(int $weekdayIndex): array
    {
        return $this->vaasaCalculator->calculateRahuVaasa($weekdayIndex);
    }

    private function calculateChandraVaasa(float $jdStart, float $jdEnd, string $tz): array
    {
        return $this->vaasaCalculator->calculateChandraVaasa(
            $this->collectNakshatraPadaIntervals($jdStart, $jdEnd),
            $tz
        );
    }

    private function calculateShivaVaasa(int $tithiNumber, float $tithiEndJd, string $tz): array
    {
        return $this->vaasaCalculator->calculateShivaVaasa($tithiNumber, $tithiEndJd, $tz);
    }

    private function calculateAgniVaasa(int $tithiNumber, int $weekdayIndex, float $tithiEndJd, string $tz): array
    {
        return $this->vaasaCalculator->calculateAgniVaasa($tithiNumber, $weekdayIndex, $tithiEndJd, $tz);
    }

    private function calculateYoginiVaasa(int $tithiNumber): array
    {
        return $this->vaasaCalculator->calculateYoginiVaasa($tithiNumber);
    }

    private function buildTransitionSignals(
        float $jdStart,
        float $jdEnd,
        float $sunLongitude,
        float $moonLongitude,
        int $tithiNumber,
        int $nakshatraIndex,
        int $yogaIndex,
        int $karanaIndex,
        string $tz,
        ?int $sankrantiRashi
    ): array {
        $tithiIntervals = $this->collectTithiIntervals($jdStart, $jdEnd);
        $nakshatraIntervals = $this->collectNakshatraIntervals($jdStart, $jdEnd);

        return [
            'tithi' => [
                'current' => Tithi::from($tithiNumber)->getName(),
                'windows' => array_map(fn (array $interval): array => $this->formatTransitionWindow($interval, 'tithi', $tz), $tithiIntervals),
            ],
            'nakshatra' => [
                'current' => Nakshatra::from($nakshatraIndex % 27)->getName(),
                'windows' => array_map(fn (array $interval): array => $this->formatTransitionWindow($interval, 'nakshatra', $tz), $nakshatraIntervals),
            ],
            'yoga' => [
                'current' => Localization::translate('Yoga', max(0, $yogaIndex - 1)),
            ],
            'karana' => [
                'current' => Localization::translate('Karana', $this->normalizeKaranaLocalizationIndex($karanaIndex)),
            ],
            'moon_sign' => [
                'current' => Rasi::from(AstroCore::getSign($moonLongitude))->getName(),
                'transitions' => $this->collectMoonSignTransitions($jdStart, $jdEnd, $tz),
            ],
            'sun_sign' => [
                'current' => Rasi::from(AstroCore::getSign($sunLongitude))->getName(),
                'sankranti_today' => $sankrantiRashi !== null,
                'next_sign' => $sankrantiRashi !== null ? Rasi::from($sankrantiRashi)->getName() : null,
            ],
        ];
    }

    private function buildEkadashiObservance(
        int $tithiNumber,
        float $tithiStartJd,
        float $tithiEndJd,
        float $sunriseJd,
        float $sunsetJd,
        float $nextSunriseJd,
        string $tz,
        float $lat,
        float $lon
    ): ?array {
        return $this->ekadashiParanaCalculator->buildEkadashiObservance(
            $tithiNumber,
            $tithiStartJd,
            $tithiEndJd,
            $sunriseJd,
            $sunsetJd,
            $nextSunriseJd,
            $tz,
            $lat,
            $lon
        );
    }

    private function collectNakshatraIntervals(float $jdStart, float $jdEnd): array
    {
        return $this->intervalTracker->collectNakshatraIntervals($jdStart, $jdEnd);
    }

    private function collectNakshatraPadaIntervals(float $jdStart, float $jdEnd): array
    {
        return $this->intervalTracker->collectNakshatraPadaIntervals($jdStart, $jdEnd);
    }

    private function collectTithiIntervals(float $jdStart, float $jdEnd): array
    {
        return $this->intervalTracker->collectTithiIntervals($jdStart, $jdEnd);
    }

    private function collectMoonSignTransitions(float $jdStart, float $jdEnd, string $tz): array
    {
        return $this->intervalTracker->collectMoonSignTransitions($jdStart, $jdEnd, $tz);
    }

    private function formatTransitionWindow(array $interval, string $type, string $tz): array
    {
        return [
            'name' => (string) ($interval['name'] ?? ''),
            'type' => $type,
            'start_jd' => $interval['start_jd'],
            'end_jd' => $interval['end_jd'],
            'start_iso' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic((float) $interval['start_jd'], $tz)),
            'end_iso' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic((float) $interval['end_jd'], $tz)),
        ];
    }

    private function normalizeKaranaLocalizationIndex(int $karanaIndex): int
    {
        return match ($karanaIndex) {
            1 => 0,
            2 => 1,
            3 => 2,
            4 => 3,
            5 => 4,
            6 => 5,
            7 => 6,
            57 => 7,
            58 => 8,
            59 => 9,
            60 => 10,
            default => 0,
        };
    }

    /**
     * Return precise tithi interval (start/end JD) containing given JD.
     *
     * @return array{index:int,start_jd:float,end_jd:float}
     */
    private function getTithiIntervalAtJd(float $jd): array
    {
        return $this->intervalTracker->getTithiIntervalAtJd($jd);
    }

    private function calcBodyAtJd(float $jd, int $planet, int $flags): float
    {
        $xx = $this->sweph->getFFI()->new('double[6]');
        $serr = $this->sweph->getFFI()->new('char[256]');
        $this->sweph->swe_calc_ut($jd, $planet, $flags, $xx, $serr);
        return AstroCore::normalize($xx[0]);
    }

    private function findAngleCrossing(float $jd0, float $targetAngle, int $direction, callable $angleFn): float
    {
        $step = 0.25 * $direction;
        // 0.25 day step with 100 steps only scans 25 days, which is insufficient
        // for month-boundary searches (e.g. next Amavasya ~29.5 days away).
        $maxSteps = 500;
        $jd1 = $jd0;
        $f0 = $this->signedDiff($angleFn($jd1), $targetAngle);
        $jd2 = $jd1;
        $f1 = $f0;

        for ($i = 0; $i < $maxSteps; $i++) {
            $jd2 = $jd1 + $step;
            $f1 = $this->signedDiff($angleFn($jd2), $targetAngle);
            if ($f0 === 0.0) {
                return $jd1;
            }

            if ($f0 === 0.0 || (abs($f1 - $f0) < 180.0 && (($f0 < 0 && $f1 > 0) || ($f0 > 0 && $f1 < 0)))) {
                break;
            }

            $jd1 = $jd2;
            $f0 = $f1;
        }

        $low = min($jd1, $jd2);
        $high = max($jd1, $jd2);
        $fLow = $this->signedDiff($angleFn($low), $targetAngle);
        $fHigh = $this->signedDiff($angleFn($high), $targetAngle);

        for ($i = 0; $i < 80; $i++) {
            $mid = ($low + $high) / 2.0;
            $fMid = $this->signedDiff($angleFn($mid), $targetAngle);
            if ($fMid === 0.0) {
                return $mid;
            }

            if (($fLow < 0 && $fMid > 0) || ($fLow > 0 && $fMid < 0)) {
                $high = $mid;
                $fHigh = $fMid;
            } else {
                $low = $mid;
                $fLow = $fMid;
            }
        }

        return ($low + $high) / 2.0;
    }

    private function signedDiff(float $angle, float $target): float
    {
        $diff = AstroCore::normalize($angle - $target);
        if ($diff > 180.0) {
            $diff -= 360.0;
        }

        return $diff;
    }
}
