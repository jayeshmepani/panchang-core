<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Astronomy\Math;

use JayeshMepani\PanchangCore\Astronomy\SunService;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Core\Enums\Karana;
use JayeshMepani\PanchangCore\Core\Enums\Nakshatra;
use JayeshMepani\PanchangCore\Core\Enums\Rasi;
use JayeshMepani\PanchangCore\Core\Enums\Tithi;
use JayeshMepani\PanchangCore\Core\Enums\Yoga;

/** Interval Tracker - Collects astronomical windows for Tithi, Nakshatra, etc. */
class IntervalTracker
{
    public function __construct(
        private readonly TransitEngine $transitEngine,
        private readonly SunService $sunService
    ) {
    }

    public function collectTithiIntervals(float $jdStart, float $jdEnd): array
    {
        $intervals = [];
        $cursor = $jdStart;

        for ($guard = 0; $guard < 4 && $cursor < $jdEnd; $guard++) {
            $interval = $this->getTithiIntervalAtJd($cursor + 0.000001);
            $index = $interval['index'];
            $intervals[] = [
                'index' => $index,
                'phase_index' => (($index - 1) % 15) + 1,
                'name' => Tithi::from($index)->getName(),
                'start_jd' => max($interval['start_jd'], $cursor - 2.0), // Bound search to prevent infinite backtrack
                'end_jd' => $interval['end_jd'],
            ];

            // Re-fetch start_jd without broad bounding once we have the interval context
            $intervals[count($intervals) - 1]['start_jd'] = $interval['start_jd'];

            $cursor = $interval['end_jd'] + 0.000001;
        }

        return $intervals;
    }

    /**
     * Return precise tithi interval (start/end JD) containing given JD.
     *
     * @return array{index:int,start_jd:float,end_jd:float}
     */
    public function getTithiIntervalAtJd(float $jd): array
    {
        $angle = $this->transitEngine->getMoonSunAngle($jd);
        $tithiIndex = (int) floor($angle / 12.0) + 1; // 1..30

        $startAngle = (($tithiIndex - 1) % 30) * 12.0;
        $endAngle = ($tithiIndex % 30) * 12.0;

        $startJd = $this->transitEngine->findAngleCrossing($jd, $startAngle, -1, fn (float $probe): float => $this->transitEngine->getMoonSunAngle($probe));
        $endJd = $this->transitEngine->findAngleCrossing($jd, $endAngle, 1, fn (float $probe): float => $this->transitEngine->getMoonSunAngle($probe));

        return [
            'index' => $tithiIndex,
            'start_jd' => $startJd,
            'end_jd' => $endJd,
        ];
    }

    public function collectNakshatraIntervals(float $jdStart, float $jdEnd): array
    {
        $intervals = [];
        $cursor = $jdStart;

        for ($guard = 0; $guard < 4 && $cursor < $jdEnd; $guard++) {
            $moonLongitude = $this->transitEngine->getMoonLongitude($cursor + 0.000001);
            $nakshatraIndex = ((int) floor($moonLongitude / (360.0 / 27.0))) % 27;
            $startAngle = $nakshatraIndex * (360.0 / 27.0);
            $targetAngle = ($nakshatraIndex + 1) * (360.0 / 27.0);

            $startJd = $this->transitEngine->findAngleCrossing(
                $cursor,
                $startAngle,
                -1,
                fn (float $jd): float => $this->transitEngine->getMoonLongitude($jd)
            );
            $endJd = $this->transitEngine->findAngleCrossing(
                $cursor,
                $targetAngle,
                1,
                fn (float $jd): float => $this->transitEngine->getMoonLongitude($jd)
            );

            $intervals[] = [
                'index' => $nakshatraIndex,
                'name' => Nakshatra::from($nakshatraIndex)->getName(),
                'start_jd' => $startJd,
                'end_jd' => $endJd,
            ];

            if ($endJd <= $cursor) { $cursor += 0.1; } else { $cursor = $endJd + 0.000001; }
        }

        return $intervals;
    }

    public function collectNakshatra28Intervals(float $jdStart, float $jdEnd): array
    {
        $intervals = [];
        $cursor = $jdStart;

        for ($guard = 0; $guard < 5 && $cursor < $jdEnd; $guard++) {
            $moonLongitude = $this->transitEngine->getMoonLongitude($cursor + 0.000001);
            $orderIndex = $this->nakshatra28IndexFromLongitude($moonLongitude);
            $targetAngle = $this->nextNakshatra28BoundaryAfter($moonLongitude);
            $endJd = $this->transitEngine->findAngleCrossing(
                $cursor,
                $targetAngle,
                1,
                fn (float $jd): float => $this->transitEngine->getMoonLongitude($jd)
            );
            $segmentEnd = min($endJd, $jdEnd);

            if ($segmentEnd <= $cursor) {
                break;
            }

            $intervals[] = [
                'order_index' => $orderIndex,
                'name' => $this->nakshatra28Name($orderIndex),
                'start_jd' => $cursor,
                'end_jd' => $segmentEnd,
            ];

            $cursor = $segmentEnd + 0.000001;
        }

        return $intervals;
    }

    public function collectNakshatraPadaIntervals(float $jdStart, float $jdEnd): array
    {
        $intervals = [];
        $cursor = $jdStart;

        for ($guard = 0; $guard < 12 && $cursor < $jdEnd; $guard++) {
            $moonLongitude = AstroCore::normalize($this->transitEngine->getMoonLongitude($cursor + 0.000001));
            $nakshatraIndex = (int) floor($moonLongitude / 13.333333333333334);
            $nakshatraStart = $nakshatraIndex * 13.333333333333334;
            $pada = (int) floor(($moonLongitude - $nakshatraStart) / 3.3333333333333335) + 1;
            $pada = max(1, min(4, $pada));

            $startAngle = $nakshatraStart + (($pada - 1) * 3.3333333333333335);
            $targetAngle = $nakshatraStart + ($pada * 3.3333333333333335);
            if ($targetAngle >= 360.0) {
                $targetAngle = 360.0;
            }

            $startJd = $this->transitEngine->findAngleCrossing(
                $cursor,
                $startAngle,
                -1,
                fn (float $jd): float => $this->transitEngine->getMoonLongitude($jd)
            );
            $endJd = $this->transitEngine->findAngleCrossing(
                $cursor,
                $targetAngle,
                1,
                fn (float $jd): float => $this->transitEngine->getMoonLongitude($jd)
            );

            $intervals[] = [
                'nakshatra_index' => $nakshatraIndex,
                'nakshatra' => Nakshatra::from($nakshatraIndex)->getName(),
                'pada' => $pada,
                'start_jd' => $startJd,
                'end_jd' => $endJd,
            ];

            if ($endJd <= $cursor) { $cursor += 0.1; } else { $cursor = $endJd + 0.000001; }
        }

        return $intervals;
    }

    public function collectSunNakshatraIntervals(float $jdStart, float $jdEnd): array
    {
        $intervals = [];
        $cursor = $jdStart;

        for ($guard = 0; $guard < 2 && $cursor < $jdEnd; $guard++) {
            $sunLongitude = $this->transitEngine->getSunLongitude($cursor + 0.000001);
            $nakshatraIndex = ((int) floor($sunLongitude / (360.0 / 27.0))) % 27;
            $targetAngle = ($nakshatraIndex + 1) * (360.0 / 27.0);
            $endJd = $this->transitEngine->findAngleCrossing(
                $cursor,
                $targetAngle,
                1,
                fn (float $jd): float => $this->transitEngine->getSunLongitude($jd)
            );

            $intervals[] = [
                'index' => $nakshatraIndex,
                'name' => Nakshatra::from($nakshatraIndex)->getName(),
                'start_jd' => $cursor,
                'end_jd' => min($endJd, $jdEnd),
            ];

            $cursor = min($endJd, $jdEnd) + 0.000001;
        }

        return $intervals;
    }

    public function collectYogaIntervals(float $jdStart, float $jdEnd): array
    {
        $intervals = [];
        $cursor = $jdStart;

        for ($guard = 0; $guard < 4 && $cursor < $jdEnd; $guard++) {
            $sum = AstroCore::normalize($this->transitEngine->getSunMoonSum($cursor + 0.000001));
            $yogaIndex = (int) floor($sum / (360.0 / 27.0));
            $startAngle = $yogaIndex * (360.0 / 27.0);
            $targetAngle = ($yogaIndex + 1) * (360.0 / 27.0);

            $startJd = $this->transitEngine->findAngleCrossing(
                $cursor,
                $startAngle,
                -1,
                fn (float $jd): float => $this->transitEngine->getSunMoonSum($jd)
            );
            $endJd = $this->transitEngine->findAngleCrossing(
                $cursor,
                $targetAngle,
                1,
                fn (float $jd): float => $this->transitEngine->getSunMoonSum($jd)
            );

            $intervals[] = [
                'index' => $yogaIndex + 1,
                'name' => Yoga::from($yogaIndex)->getName(),
                'start_jd' => $startJd,
                'end_jd' => $endJd,
            ];

            if ($endJd <= $cursor) { $cursor += 0.1; } else { $cursor = $endJd + 0.000001; }
        }

        return $intervals;
    }

    public function collectKaranaIntervals(float $jdStart, float $jdEnd): array
    {
        $intervals = [];
        $cursor = $jdStart;

        for ($guard = 0; $guard < 6 && $cursor < $jdEnd; $guard++) {
            $diff = AstroCore::normalize($this->transitEngine->getMoonSunAngle($cursor + 0.000001));
            $karanaIndex = (int) floor($diff / 6.0);
            $startAngle = $karanaIndex * 6.0;
            $targetAngle = ($karanaIndex + 1) * 6.0;

            $startJd = $this->transitEngine->findAngleCrossing(
                $cursor,
                $startAngle,
                -1,
                fn (float $jd): float => $this->transitEngine->getMoonSunAngle($jd)
            );
            $endJd = $this->transitEngine->findAngleCrossing(
                $cursor,
                $targetAngle,
                1,
                fn (float $jd): float => $this->transitEngine->getMoonSunAngle($jd)
            );

            $tithiIndex = (int) floor($diff / 12.0) + 1;
            $fraction = fmod($diff, 12.0) / 12.0;
            $karana = Karana::fromTithi($tithiIndex, $fraction);

            $intervals[] = [
                'index' => $karanaIndex + 1,
                'name' => $karana->getName(),
                'start_jd' => $startJd,
                'end_jd' => $endJd,
            ];

            if ($endJd <= $cursor) { $cursor += 0.1; } else { $cursor = $endJd + 0.000001; }
        }

        return $intervals;
    }

    public function collectMoonSignTransitions(float $jdStart, float $jdEnd, string $tz): array
    {
        $currentMoonSign = AstroCore::getSign($this->transitEngine->getMoonLongitude($jdStart + 0.000001));
        $targetAngle = ($currentMoonSign + 1) * 30.0;
        $transitionJd = $this->transitEngine->findAngleCrossing(
            $jdStart,
            $targetAngle,
            1,
            fn (float $jd): float => $this->transitEngine->getMoonLongitude($jd)
        );

        if ($transitionJd >= $jdEnd) {
            return [];
        }

        return [[
            'from' => Rasi::from($currentMoonSign)->getName(),
            'to' => Rasi::from(($currentMoonSign + 1) % 12)->getName(),
            'at_jd' => $transitionJd,
            'at_iso' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($transitionJd, $tz)),
        ]];
    }

    public function collectMoonSignWindows(float $jdStart, float $jdEnd, int $targetSignIndex, string $tz): array
    {
        $windows = [];
        $cursor = $jdStart;

        for ($guard = 0; $guard < 3 && $cursor < $jdEnd; $guard++) {
            $moonLongitude = $this->transitEngine->getMoonLongitude($cursor + 0.000001);
            $signIndex = AstroCore::getSign($moonLongitude);
            $targetAngle = (($signIndex + 1) % 12) * 30.0;
            $endJd = $this->transitEngine->findAngleCrossing(
                $cursor,
                $targetAngle,
                1,
                fn (float $jd): float => $this->transitEngine->getMoonLongitude($jd)
            );

            $segmentEnd = min($endJd, $jdEnd);
            if ($signIndex === $targetSignIndex && $segmentEnd > $cursor) {
                $windows[] = [
                    'start_jd' => $cursor,
                    'end_jd' => $segmentEnd,
                    'start' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($cursor, $tz)),
                    'end' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($segmentEnd, $tz)),
                    'moon_sign' => Rasi::from($signIndex)->getName(),
                ];
            }

            $cursor = $segmentEnd + 0.000001;
        }

        return $windows;
    }

    public function collectMoonLongitudeRangeWindows(
        float $jdStart,
        float $jdEnd,
        float $rangeStart,
        float $rangeEnd,
        string $tz
    ): array {
        $windows = [];
        $cursor = $jdStart;

        for ($guard = 0; $guard < 4 && $cursor < $jdEnd; $guard++) {
            $moonLongitude = AstroCore::normalize($this->transitEngine->getMoonLongitude($cursor + 0.000001));

            if ($moonLongitude >= $rangeStart && $moonLongitude < $rangeEnd) {
                $trueStartJd = $this->transitEngine->findAngleCrossing(
                    $cursor,
                    $rangeStart,
                    -1,
                    fn (float $jd): float => $this->transitEngine->getMoonLongitude($jd)
                );
                $endJd = $this->transitEngine->findAngleCrossing(
                    $cursor,
                    $rangeEnd,
                    1,
                    fn (float $jd): float => $this->transitEngine->getMoonLongitude($jd)
                );
                $segmentEnd = min($endJd, $jdEnd);
                $windows[] = [
                    'start_jd' => $trueStartJd,
                    'end_jd' => $endJd,
                    'start' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($trueStartJd, $tz)),
                    'end' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($endJd, $tz)),
                    'visible_start_jd' => $cursor,
                    'visible_end_jd' => $segmentEnd,
                    'visible_start' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($cursor, $tz)),
                    'visible_end' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($segmentEnd, $tz)),
                ];
                $cursor = $segmentEnd + 0.000001;

                continue;
            }

            $target = $moonLongitude < $rangeStart ? $rangeStart : 360.0;
            $nextJd = $this->transitEngine->findAngleCrossing(
                $cursor,
                $target,
                1,
                fn (float $jd): float => $this->transitEngine->getMoonLongitude($jd)
            );
            if ($nextJd <= $cursor || $nextJd >= $jdEnd) {
                break;
            }

            $endJd = $this->transitEngine->findAngleCrossing(
                $nextJd,
                $rangeEnd,
                1,
                fn (float $jd): float => $this->transitEngine->getMoonLongitude($jd)
            );
            $segmentEnd = min($endJd, $jdEnd);
            $windows[] = [
                'start_jd' => $nextJd,
                'end_jd' => $endJd,
                'start' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($nextJd, $tz)),
                'end' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($endJd, $tz)),
                'visible_start_jd' => max($nextJd, $jdStart),
                'visible_end_jd' => $segmentEnd,
                'visible_start' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic(max($nextJd, $jdStart), $tz)),
                'visible_end' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($segmentEnd, $tz)),
            ];
            $cursor = $segmentEnd + 0.000001;
        }

        return $windows;
    }

    private function nakshatra28IndexFromLongitude(float $longitude): int
    {
        $normalized = AstroCore::normalize($longitude);
        $boundaries = $this->nakshatra28Boundaries();

        for ($i = 0; $i < 28; $i++) {
            $start = $boundaries[$i];
            $end = $boundaries[$i + 1];
            if ($normalized >= $start && $normalized < $end) {
                return $i;
            }
        }

        return 27;
    }

    private function nextNakshatra28BoundaryAfter(float $longitude): float
    {
        $normalized = AstroCore::normalize($longitude);
        foreach ($this->nakshatra28Boundaries() as $boundary) {
            if ($boundary > $normalized + 1e-9) {
                return $boundary;
            }
        }

        return 360.0;
    }

    /** @return list<float> */
    private function nakshatra28Boundaries(): array
    {
        return [
            0.0,
            13.333333333333334,
            26.666666666666668,
            40.0,
            53.333333333333336,
            66.66666666666667,
            80.0,
            93.33333333333334,
            106.66666666666667,
            120.0,
            133.33333333333334,
            146.66666666666669,
            160.0,
            173.33333333333334,
            186.66666666666669,
            200.0,
            213.33333333333334,
            226.66666666666669,
            240.0,
            253.33333333333334,
            266.6666666666667,
            276.6666666666667,
            280.8888888888889,
            293.33333333333337,
            306.6666666666667,
            320.0,
            333.33333333333337,
            346.6666666666667,
            360.0,
        ];
    }

    private function nakshatra28Name(int $orderIndex): string
    {
        return match ($orderIndex) {
            21 => 'Abhijit',
            22 => Nakshatra::Shravana->getName(),
            23 => Nakshatra::Dhanishta->getName(),
            24 => Nakshatra::Shatabhisha->getName(),
            25 => Nakshatra::PurvaBhadrapada->getName(),
            26 => Nakshatra::UttaraBhadrapada->getName(),
            27 => Nakshatra::Revati->getName(),
            default => Nakshatra::from($orderIndex)->getName(),
        };
    }
}
