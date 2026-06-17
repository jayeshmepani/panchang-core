<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Astronomy\Math;

use FFI\CData;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JmeEph\FFI\JmeEphFFI;

/** Transit Engine - Handles astronomical crossing and low-level math. */
class TransitEngine
{
    private const int DEFAULT_BODY_LONGITUDE_CACHE_MAX = 20000;

    private const int DEFAULT_BODY_LONGITUDE_CACHE_TRIM_TO = 10000;

    private readonly CData $xxBuffer;

    private readonly CData $serrBuffer;

    /** @var array<string, float> */
    private array $bodyLongitudeCache = [];

    public function __construct(
        private readonly JmeEphFFI $jme,
        private readonly int $bodyLongitudeCacheMax = self::DEFAULT_BODY_LONGITUDE_CACHE_MAX,
        private readonly int $bodyLongitudeCacheTrimTo = self::DEFAULT_BODY_LONGITUDE_CACHE_TRIM_TO,
    ) {
        $ffi = $this->jme->getFFI();
        $this->xxBuffer = $ffi->new('double[6]');
        $this->serrBuffer = $ffi->new('char[256]');
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
                true
            );
        }

        $this->bodyLongitudeCache[$cacheKey] = $value;

        return $value;
    }

    public function getMoonSunAngle(float $jd): float
    {
        // NOTE: JME_CALC_NO_ABERRATION compensates a JME JPL-mode quirk that otherwise
        // applies annual aberration twice (~20.5" Sun error). With this flag the longitudes
        // become the correct single-aberration apparent positions (verified against the
        // published 2026 equinox/solstice instants).
        $flags = JmeEphFFI::JME_CALC_HIGH_PRECISION | JmeEphFFI::JME_CALC_SIDEREAL | JmeEphFFI::JME_CALC_NO_ABERRATION;
        $sun = $this->calcBodyAtJd($jd, JmeEphFFI::JME_BODY_SUN, $flags);
        $moon = $this->calcBodyAtJd($jd, JmeEphFFI::JME_BODY_MOON, $flags);
        return AstroCore::normalize($moon - $sun);
    }

    public function getSunLongitude(float $jd): float
    {
        // NOTE: JME_CALC_NO_ABERRATION compensates a JME JPL-mode quirk that otherwise
        // applies annual aberration twice (~20.5" Sun error). With this flag the longitudes
        // become the correct single-aberration apparent positions (verified against the
        // published 2026 equinox/solstice instants).
        $flags = JmeEphFFI::JME_CALC_HIGH_PRECISION | JmeEphFFI::JME_CALC_SIDEREAL | JmeEphFFI::JME_CALC_NO_ABERRATION;
        return $this->calcBodyAtJd($jd, JmeEphFFI::JME_BODY_SUN, $flags);
    }

    public function getMoonLongitude(float $jd): float
    {
        // NOTE: JME_CALC_NO_ABERRATION compensates a JME JPL-mode quirk that otherwise
        // applies annual aberration twice (~20.5" Sun error). With this flag the longitudes
        // become the correct single-aberration apparent positions (verified against the
        // published 2026 equinox/solstice instants).
        $flags = JmeEphFFI::JME_CALC_HIGH_PRECISION | JmeEphFFI::JME_CALC_SIDEREAL | JmeEphFFI::JME_CALC_NO_ABERRATION;
        return $this->calcBodyAtJd($jd, JmeEphFFI::JME_BODY_MOON, $flags);
    }

    public function getSunMoonSum(float $jd): float
    {
        // NOTE: JME_CALC_NO_ABERRATION compensates a JME JPL-mode quirk that otherwise
        // applies annual aberration twice (~20.5" Sun error). With this flag the longitudes
        // become the correct single-aberration apparent positions (verified against the
        // published 2026 equinox/solstice instants).
        $flags = JmeEphFFI::JME_CALC_HIGH_PRECISION | JmeEphFFI::JME_CALC_SIDEREAL | JmeEphFFI::JME_CALC_NO_ABERRATION;
        $sun = $this->calcBodyAtJd($jd, JmeEphFFI::JME_BODY_SUN, $flags);
        $moon = $this->calcBodyAtJd($jd, JmeEphFFI::JME_BODY_MOON, $flags);
        return AstroCore::normalize($sun + $moon);
    }
}
