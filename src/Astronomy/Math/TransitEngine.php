<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Astronomy\Math;

use FFI\CData;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JmeEph\FFI\JmeEphFFI;

/** Transit Engine - Handles astronomical crossing and low-level math. */
class TransitEngine
{
    private const int DEFAULT_BODY_LONGITUDE_CACHE_MAX = 5000;

    private const int DEFAULT_BODY_LONGITUDE_CACHE_TRIM_TO = 2500;

    private readonly CData $xxBuffer;

    private readonly CData $serrBuffer;

    /** @var array<string, float> */
    private array $bodyLongitudeCache = [];

    public function __construct(
        private ?JmeEphFFI $jme = null,
        private readonly int $bodyLongitudeCacheMax = self::DEFAULT_BODY_LONGITUDE_CACHE_MAX,
        private readonly int $bodyLongitudeCacheTrimTo = self::DEFAULT_BODY_LONGITUDE_CACHE_TRIM_TO,
    ) {
        $this->jme ??= new JmeEphFFI;
        $ffi = $this->jme->getFFI();
        $this->xxBuffer = $ffi->new('double[6]');
        $this->serrBuffer = $ffi->new('char[256]');
    }

    public function clearCache(): void
    {
        $this->bodyLongitudeCache = [];
    }

    public function jme(): JmeEphFFI
    {
        return $this->jme ??= new JmeEphFFI;
    }

    public function findAngleCrossing(float $jd0, float $targetAngle, int $direction, callable $angleFn): float
    {
        $curAngle = $angleFn($jd0);
        $f0 = $this->signedDiff($curAngle, $targetAngle);
        if ($f0 === 0.0) {
            return $jd0;
        }

        // Fast-forward starting point when crossing is far away in search direction.
        $dist = $direction > 0
            ? AstroCore::normalize($targetAngle - $curAngle)
            : AstroCore::normalize($curAngle - $targetAngle);
        if ($dist === 0.0) {
            $dist = 360.0;
        }

        // Bound lunar motion at 15.5 deg/day. Estimate slower solar motion so
        // sign transitions do not require a quarter-day scan over many weeks.
        $speedBound = 15.5;
        $probeAngle = $angleFn($jd0 + ($direction * 0.25));
        $estimatedRate = $this->signedDiff($probeAngle, $curAngle) / ($direction * 0.25);
        if ($estimatedRate > 0.0 && $estimatedRate < 10.0) {
            $speedBound = max(0.25, ($estimatedRate * 1.35) + 0.05);
        }

        $safeJumpDays = max(0.0, ($dist / $speedBound) * 0.75);
        $jd1 = $jd0 + $direction * $safeJumpDays;
        $f0 = $this->signedDiff($angleFn($jd1), $targetAngle);

        $step = 0.25 * $direction;
        $maxSteps = 500;
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

        // Secant interpolation converges quickly for smooth longitude motion;
        // midpoint fallback keeps the bracket safe when progress stalls.
        for ($i = 0; $i < 20; $i++) {
            $range = $high - $low;
            if ($range < 1e-6) {
                break;
            }

            $denominator = $fHigh - $fLow;
            $mid = $denominator === 0.0
                ? ($low + $high) / 2.0
                : $low - ($fLow * $range / $denominator);
            $margin = $range * 0.05;
            if ($mid <= $low + $margin || $mid >= $high - $margin) {
                $mid = ($low + $high) / 2.0;
            }

            $fMid = $this->signedDiff($angleFn($mid), $targetAngle);
            if (abs($fMid) < 1e-6) {
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
        $cacheKey = sprintf('%.17g|%d|%d', $jd, $planet, $flags);
        if (array_key_exists($cacheKey, $this->bodyLongitudeCache)) {
            return $this->bodyLongitudeCache[$cacheKey];
        }

        $this->jme->jme_calc_ut($jd, $planet, $flags, $this->xxBuffer, $this->serrBuffer);
        $value = AstroCore::normalize($this->xxBuffer[0]);
        if (count($this->bodyLongitudeCache) >= $this->bodyLongitudeCacheMax) {
            $this->bodyLongitudeCache = array_slice(
                $this->bodyLongitudeCache,
                -$this->bodyLongitudeCacheTrimTo,
                null,
                true,
            );
        }

        $this->bodyLongitudeCache[$cacheKey] = $value;

        return $value;
    }

    public function getMoonSunAngle(float $jd): float
    {
        $flags = JmeEphFFI::JME_CALC_HIGH_PRECISION | JmeEphFFI::JME_CALC_SIDEREAL;
        $sun = $this->calcBodyAtJd($jd, JmeEphFFI::JME_BODY_SUN, $flags);
        $moon = $this->calcBodyAtJd($jd, JmeEphFFI::JME_BODY_MOON, $flags);

        return AstroCore::normalize($moon - $sun);
    }

    public function getSunLongitude(float $jd): float
    {
        $flags = JmeEphFFI::JME_CALC_HIGH_PRECISION | JmeEphFFI::JME_CALC_SIDEREAL;

        return $this->calcBodyAtJd($jd, JmeEphFFI::JME_BODY_SUN, $flags);
    }

    public function getMoonLongitude(float $jd): float
    {
        $flags = JmeEphFFI::JME_CALC_HIGH_PRECISION | JmeEphFFI::JME_CALC_SIDEREAL;

        return $this->calcBodyAtJd($jd, JmeEphFFI::JME_BODY_MOON, $flags);
    }

    public function getSunMoonSum(float $jd): float
    {
        $flags = JmeEphFFI::JME_CALC_HIGH_PRECISION | JmeEphFFI::JME_CALC_SIDEREAL;
        $sun = $this->calcBodyAtJd($jd, JmeEphFFI::JME_BODY_SUN, $flags);
        $moon = $this->calcBodyAtJd($jd, JmeEphFFI::JME_BODY_MOON, $flags);

        return AstroCore::normalize($sun + $moon);
    }
}
