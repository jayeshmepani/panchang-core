<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Muhurta\Lagna;

use Carbon\CarbonImmutable;
use DateTimeZone;
use InvalidArgumentException;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Core\Enums\Rasi;
use JmeEph\FFI\JmeEphFFI;
use RuntimeException;

/** Lagna Table Calculator - Handles ascendant sign transitions. */
class LagnaTableCalculator
{
    public function calculateLagna(
        CarbonImmutable $current,
        float $ayanamsaDeg,
        float $lat,
        float $lon,
        JmeEphFFI $jme
    ): array {
        $jd = $this->carbonToJulianDayUtc($jme, $current);
        $cusp = $jme->getFFI()->new('double[13]');
        $ascmc = $jme->getFFI()->new('double[10]');
        $jme->jme_houses($jd, $lat, $lon, ord('P'), $cusp, $ascmc);

        $nirayanaLagna = AstroCore::normalize($ascmc[0] - $ayanamsaDeg);
        $sayanaLagna = AstroCore::normalize($ascmc[0]);
        $sign = Rasi::fromLongitude($nirayanaLagna);

        return [
            'lagna_longitude_nirayana' => $nirayanaLagna,
            'lagna_longitude_sayana' => $sayanaLagna,
            'sign_index' => $sign->value,
            'sign_name' => $sign->getName(),
            'degree_in_sign' => fmod($nirayanaLagna, 30.0),
            'ayanamsa_applied' => $ayanamsaDeg,
        ];
    }

    public function calculateLagnaTable(
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise,
        float $ayanamsaDeg,
        float $lat,
        float $lon,
        JmeEphFFI $jme
    ): array {
        $jdStart = $this->carbonToJulianDayUtc($jme, $sunrise);
        $jdSunset = $this->carbonToJulianDayUtc($jme, $sunset);
        $jdEnd = $this->carbonToJulianDayUtc($jme, $nextSunrise);

        if ($jdEnd <= $jdStart) {
            throw new InvalidArgumentException('Next sunrise must be after sunrise for Lagna table calculation.');
        }

        $timezone = $sunrise->getTimezone();
        $epsilon = 1.0 / 86400.0;
        $activeSign = $this->getSignIndexAtJd($jme, $jdStart, $lat, $lon, $ayanamsaDeg);
        $intervalStartJd = $this->findPreviousLagnaTransition($jme, $jdStart, $activeSign, $lat, $lon, $ayanamsaDeg);
        $lagnas = [];

        for ($guard = 0; $guard < 16 && $intervalStartJd < $jdEnd; $guard++) {
            [$intervalEndJd, $nextSign] = $this->findNextLagnaTransition($jme, $intervalStartJd + $epsilon, $activeSign, $lat, $lon, $ayanamsaDeg);

            if ($intervalEndJd > $jdStart && $intervalStartJd < $jdEnd) {
                $visibleStartJd = max($intervalStartJd, $jdStart);
                $visibleEndJd = min($intervalEndJd, $jdEnd);
                $startTime = $this->jdToCarbon($intervalStartJd, $timezone);
                $endTime = $this->jdToCarbon($intervalEndJd, $timezone);
                $visibleStartTime = $this->jdToCarbon($visibleStartJd, $timezone);
                $visibleEndTime = $this->jdToCarbon($visibleEndJd, $timezone);

                $lagnas[] = [
                    'lagna_number' => $activeSign + 1,
                    'sign_name' => Rasi::from($activeSign)->getName(),
                    'sign_index' => $activeSign,
                    'start' => AstroCore::formatTime($startTime),
                    'start_iso' => AstroCore::formatDateTime($startTime),
                    'start_jd' => $intervalStartJd,
                    'end' => AstroCore::formatTime($endTime),
                    'end_iso' => AstroCore::formatDateTime($endTime),
                    'end_jd' => $intervalEndJd,
                    'visible_start' => AstroCore::formatTime($visibleStartTime),
                    'visible_start_iso' => AstroCore::formatDateTime($visibleStartTime),
                    'visible_start_jd' => $visibleStartJd,
                    'visible_end' => AstroCore::formatTime($visibleEndTime),
                    'visible_end_iso' => AstroCore::formatDateTime($visibleEndTime),
                    'visible_end_jd' => $visibleEndJd,
                    'is_partial_start' => $intervalStartJd < $jdStart,
                    'is_partial_end' => $intervalEndJd > $jdEnd,
                    'duration_minutes' => ($intervalEndJd - $intervalStartJd) * 1440.0,
                    'visible_duration_minutes' => ($visibleEndJd - $visibleStartJd) * 1440.0,
                    'is_day_lagna' => $visibleStartJd < $jdSunset,
                ];
            }

            if ($intervalEndJd <= $intervalStartJd) {
                throw new RuntimeException('Invalid Lagna transition order.');
            }

            $intervalStartJd = $intervalEndJd;
            $activeSign = $nextSign;
        }

        return $lagnas;
    }

    private function jdToCarbon(float $jd, DateTimeZone $tz): CarbonImmutable
    {
        $unixTimestamp = ($jd - 2440587.5) * 86400.0;
        $seconds = (int) floor($unixTimestamp);
        $microseconds = (int) (($unixTimestamp - $seconds) * 1_000_000);

        return CarbonImmutable::createFromTimestamp($seconds, $tz)->addMicroseconds($microseconds);
    }

    private function carbonToJulianDayUtc(JmeEphFFI $jme, CarbonImmutable $dt): float
    {
        $u = $dt->setTimezone('UTC');

        return $jme->jme_julian_day($u->year, $u->month, $u->day, $u->hour + $u->minute / 60.0 + $u->second / 3600.0, JmeEphFFI::JME_CALENDAR_GREGORIAN);
    }

    private function getAscendantSiderealAtJd(JmeEphFFI $jme, float $jd, float $lat, float $lon, float $ayanamsa): float
    {
        $cusp = $jme->getFFI()->new('double[13]');
        $ascmc = $jme->getFFI()->new('double[10]');
        $jme->jme_houses($jd, $lat, $lon, ord('P'), $cusp, $ascmc);

        return AstroCore::normalize($ascmc[0] - $ayanamsa);
    }

    private function getSignIndexAtJd(JmeEphFFI $jme, float $jd, float $lat, float $lon, float $ayanamsa): int
    {
        return (int) floor($this->getAscendantSiderealAtJd($jme, $jd, $lat, $lon, $ayanamsa) / 30.0) % 12;
    }

    private function findPreviousLagnaTransition(
        JmeEphFFI $jme,
        float $jd,
        int $currentSign,
        float $lat,
        float $lon,
        float $ayanamsa
    ): float {
        $step = 120.0 / 86400.0;
        $high = $jd;
        $low = $jd - $step;

        for ($guard = 0; $guard < 720; $guard++) {
            if ($this->getSignIndexAtJd($jme, $low, $lat, $lon, $ayanamsa) !== $currentSign) {
                return $this->refineLagnaBoundary($jme, $low, $high, $currentSign, $lat, $lon, $ayanamsa);
            }

            $high = $low;
            $low -= $step;
        }

        throw new RuntimeException('Previous Lagna transition not found.');
    }

    /** @return array{0: float, 1: int} */
    private function findNextLagnaTransition(
        JmeEphFFI $jme,
        float $jd,
        int $currentSign,
        float $lat,
        float $lon,
        float $ayanamsa
    ): array {
        $step = 120.0 / 86400.0;
        $low = $jd;
        $high = $jd + $step;

        for ($guard = 0; $guard < 720; $guard++) {
            $nextSign = $this->getSignIndexAtJd($jme, $high, $lat, $lon, $ayanamsa);
            if ($nextSign !== $currentSign) {
                return [
                    $this->refineLagnaBoundary($jme, $low, $high, $nextSign, $lat, $lon, $ayanamsa),
                    $nextSign,
                ];
            }

            $low = $high;
            $high += $step;
        }

        throw new RuntimeException('Next Lagna transition not found.');
    }

    private function refineLagnaBoundary(
        JmeEphFFI $jme,
        float $low,
        float $high,
        int $targetSign,
        float $lat,
        float $lon,
        float $ayanamsa
    ): float {
        $targetAngle = $targetSign * 30.0;

        for ($iter = 0; $iter < 70; $iter++) {
            $mid = ($low + $high) / 2.0;
            $midAsc = $this->getAscendantSiderealAtJd($jme, $mid, $lat, $lon, $ayanamsa);
            $diff = AstroCore::normalize($midAsc - $targetAngle);
            if ($diff < 180.0) {
                $high = $mid;
            } else {
                $low = $mid;
            }
        }

        return $high;
    }
}
