<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Astronomy\Math;

use JayeshMepani\PanchangCore\Core\AstroCore;
use SwissEph\FFI\SwissEphFFI;

/** Transit Engine - Handles astronomical crossing and low-level math. */
class TransitEngine
{
    public function __construct(private readonly SwissEphFFI $sweph)
    {
    }

    public function findAngleCrossing(float $jd0, float $targetAngle, int $direction, callable $angleFn): float
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

    public function signedDiff(float $angle, float $target): float
    {
        $diff = AstroCore::normalize($angle - $target);
        if ($diff > 180.0) {
            $diff -= 360.0;
        }

        return $diff;
    }

    public function calcBodyAtJd(float $jd, int $planet, int $flags): float
    {
        $xx = $this->sweph->getFFI()->new('double[6]');
        $serr = $this->sweph->getFFI()->new('char[256]');
        $this->sweph->swe_calc_ut($jd, $planet, $flags, $xx, $serr);
        return AstroCore::normalize($xx[0]);
    }

    public function getMoonSunAngle(float $jd): float
    {
        $flags = SwissEphFFI::SEFLG_SWIEPH | SwissEphFFI::SEFLG_SIDEREAL;
        $sun = $this->calcBodyAtJd($jd, SwissEphFFI::SE_SUN, $flags);
        $moon = $this->calcBodyAtJd($jd, SwissEphFFI::SE_MOON, $flags);
        return AstroCore::normalize($moon - $sun);
    }

    public function getSunLongitude(float $jd): float
    {
        $flags = SwissEphFFI::SEFLG_SWIEPH | SwissEphFFI::SEFLG_SIDEREAL;
        return $this->calcBodyAtJd($jd, SwissEphFFI::SE_SUN, $flags);
    }

    public function getMoonLongitude(float $jd): float
    {
        $flags = SwissEphFFI::SEFLG_SWIEPH | SwissEphFFI::SEFLG_SIDEREAL;
        return $this->calcBodyAtJd($jd, SwissEphFFI::SE_MOON, $flags);
    }

    public function getSunMoonSum(float $jd): float
    {
        $flags = SwissEphFFI::SEFLG_SWIEPH | SwissEphFFI::SEFLG_SIDEREAL;
        $sun = $this->calcBodyAtJd($jd, SwissEphFFI::SE_SUN, $flags);
        $moon = $this->calcBodyAtJd($jd, SwissEphFFI::SE_MOON, $flags);
        return AstroCore::normalize($sun + $moon);
    }
}
