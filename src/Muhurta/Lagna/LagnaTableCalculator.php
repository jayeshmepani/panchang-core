<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Muhurta\Lagna;

use Carbon\CarbonImmutable;
use DateTimeZone;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Core\Enums\Rasi;
use JmeEph\FFI\JmeEphFFI;

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
        float $ayanamsaDeg,
        float $lat,
        float $lon,
        JmeEphFFI $jme
    ): array {
        $jdStart = $this->carbonToJulianDayUtc($jme, $sunrise);
        $jdSunset = $this->carbonToJulianDayUtc($jme, $sunset);
        $jdEnd = $jdStart + 1.0; // One solar day (24 hours from sunrise)

        $lagnas = [];
        $step = 120.0 / 86400.0;
        $prevSign = -1;
        $signsCollected = 0;

        // Sampling phase - collect exactly 12 lagna sign transitions
        for ($jd = $jdStart; $jd <= $jdEnd + $step && $signsCollected < 12; $jd += $step) {
            $asc = $this->getAscendantSiderealAtJd($jme, $jd, $lat, $lon, $ayanamsaDeg);
            $signIdx = (int) floor($asc / 30.0) % 12;

            if ($signIdx !== $prevSign) {
                $transitionJd = $jd;
                if ($prevSign !== -1) {
                    // Refine transition using binary search
                    $low = $jd - $step;
                    $high = $jd;
                    $targetAngle = $signIdx * 30.0;
                    for ($iter = 0; $iter < 70; $iter++) {
                        $mid = ($low + $high) / 2.0;
                        $midAsc = $this->getAscendantSiderealAtJd($jme, $mid, $lat, $lon, $ayanamsaDeg);
                        $diff = AstroCore::normalize($midAsc - $targetAngle);
                        if ($diff < 180.0) {
                            $high = $mid;
                        } else {
                            $low = $mid;
                        }
                    }

                    $transitionJd = $high;
                } else {
                    $transitionJd = $jdStart; // Day starts at sunrise
                }

                $time = $this->jdToCarbon($transitionJd, $sunrise->getTimezone());
                $lagnas[] = [
                    'lagna_number' => $signIdx + 1,
                    'sign_name' => Rasi::from($signIdx)->getName(),
                    'sign_index' => $signIdx,
                    'start' => AstroCore::formatTime($time),
                    'start_iso' => AstroCore::formatDateTime($time),
                    'start_jd' => $transitionJd,
                ];
                $prevSign = $signIdx;
                $signsCollected++;
            }
        }

        // Finalize durations and end times
        $count = count($lagnas);
        for ($i = 0; $i < $count; $i++) {
            $nextJd = ($i === $count - 1) ? ($jdStart + 1.0) : $lagnas[$i + 1]['start_jd'];
            $lagnas[$i]['end_jd'] = $nextJd;
            $endTime = $this->jdToCarbon($nextJd, $sunrise->getTimezone());
            $lagnas[$i]['end'] = AstroCore::formatTime($endTime);
            $lagnas[$i]['end_iso'] = AstroCore::formatDateTime($endTime);
            $lagnas[$i]['duration_minutes'] = ($nextJd - $lagnas[$i]['start_jd']) * 1440.0;
            $lagnas[$i]['is_day_lagna'] = $lagnas[$i]['start_jd'] < $jdSunset;
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
}
