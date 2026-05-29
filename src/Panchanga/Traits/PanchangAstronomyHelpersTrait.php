<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Panchanga\Traits;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JmeEph\FFI\JmeEphFFI;
use Throwable;

trait PanchangAstronomyHelpersTrait
{
    private function toJulianDayFromCarbon(CarbonImmutable $dt, string $tz): float
    {
        return $this->astronomy->toJulianDayUtc([
            'year' => (int) $dt->format('Y'),
            'month' => (int) $dt->format('m'),
            'day' => (int) $dt->format('d'),
            'hour' => (int) $dt->format('H'),
            'minute' => (int) $dt->format('i'),
            'second' => (int) $dt->format('s'),
            'timezone' => $tz,
        ]);
    }

    private function getMoonSunAngle(float $jd): float
    {
        $flags = JmeEphFFI::JME_CALC_HIGH_PRECISION | JmeEphFFI::JME_CALC_SIDEREAL;
        $sun = $this->calcBodyAtJd($jd, JmeEphFFI::JME_BODY_SUN, $flags);
        $moon = $this->calcBodyAtJd($jd, JmeEphFFI::JME_BODY_MOON, $flags);
        return AstroCore::normalize($moon - $sun);
    }

    private function getSunLongitude(float $jd): float
    {
        $flags = JmeEphFFI::JME_CALC_HIGH_PRECISION | JmeEphFFI::JME_CALC_SIDEREAL;
        return $this->calcBodyAtJd($jd, JmeEphFFI::JME_BODY_SUN, $flags);
    }

    private function getMoonLongitude(float $jd): float
    {
        $flags = JmeEphFFI::JME_CALC_HIGH_PRECISION | JmeEphFFI::JME_CALC_SIDEREAL;
        return $this->calcBodyAtJd($jd, JmeEphFFI::JME_BODY_MOON, $flags);
    }

    private function findBhadraPeriods(float $jdStart, float $jdEnd, int $sunriseTithi, string $paksha): array
    {
        return $this->bhadraCalculator->findBhadraPeriods($jdStart, $jdEnd, $sunriseTithi, $paksha);
    }

    private function getSunMoonSum(float $jd): float
    {
        $flags = JmeEphFFI::JME_CALC_HIGH_PRECISION | JmeEphFFI::JME_CALC_SIDEREAL;
        $sun = $this->calcBodyAtJd($jd, JmeEphFFI::JME_BODY_SUN, $flags);
        $moon = $this->calcBodyAtJd($jd, JmeEphFFI::JME_BODY_MOON, $flags);
        return AstroCore::normalize($sun + $moon);
    }

    /**
     * Add deterministic *_iso companions for time-only fields in payload.
     * Rule: times earlier than sunrise belong to the next civil date in Panchang-day context.
     */
    private function annotateTimeOnlyFieldsWithDateTime(array $payload, CarbonImmutable $sunrise, string $tz): array
    {
        $annotate = function (array $node) use (&$annotate, $sunrise, $tz): array {
            $lastResolvedDt = null;
            foreach ($node as $key => $value) {
                if (is_array($value)) {
                    $node[$key] = $annotate($value);
                    // Reset context for next array/object at same level?
                    // Usually ranges are in the same object.
                    continue;
                }

                if (!is_string($value) || !$this->isTimeOnlyString($value)) {
                    // If it's an ISO string, track it as last resolved
                    if (is_string($value) && str_contains($value, 'T') && str_contains($value, ':')) {
                        try {
                            $lastResolvedDt = CarbonImmutable::parse($value, $tz);
                        } catch (Throwable) {}
                    }

                    continue;
                }

                $isoKey = $key . '_iso';
                if (array_key_exists($isoKey, $node)) {
                    try {
                        $lastResolvedDt = CarbonImmutable::parse((string)$node[$isoKey], $tz);
                    } catch (Throwable) {}

                    continue;
                }

                $dt = $this->resolveTimeStringToDateTime($value, $sunrise, $tz, $lastResolvedDt);
                if ($dt instanceof CarbonImmutable) {
                    $node[$isoKey] = AstroCore::formatDateTime($dt);
                    $lastResolvedDt = $dt;
                }
            }

            return $node;
        };

        return $annotate($payload);
    }

    private function isTimeOnlyString(string $value): bool
    {
        $v = trim($value);
        if ($v === '') {
            return false;
        }

        // Skip values that already include a date marker.
        if (str_contains($v, '/') || str_contains($v, '-') || str_contains($v, ',')) {
            return false;
        }

        $v = rtrim($v, '*');

        return (bool) preg_match('/^\d{1,2}:\d{2}(:\d{2})?\s?(AM|PM)?$/i', $v);
    }

    private function resolveTimeStringToDateTime(string $timeString, CarbonImmutable $sunrise, string $tz, ?CarbonImmutable $baseTime = null): ?CarbonImmutable
    {
        $raw = trim(rtrim($timeString, '*'));
        $formats = ['h:i:s A', 'h:i A', 'H:i:s', 'H:i'];

        foreach ($formats as $format) {
            try {
                $parsed = CarbonImmutable::createFromFormat($format, $raw, $tz);
            } catch (Throwable) {
                $parsed = false;
            }

            if ($parsed === false) {
                continue;
            }

            $reference = $baseTime ?? $sunrise;
            $dt = $reference->setTime((int) $parsed->format('H'), (int) $parsed->format('i'), (int) $parsed->format('s'));

            // If we have a base time (like a start time), ensure this time is after it
            if ($baseTime instanceof CarbonImmutable) {
                if ($dt->lessThan($baseTime)) {
                    $dt = $dt->addDay();
                }
            } else {
                // Original logic relative to sunrise
                $secondsDeltaFromSunrise = abs($dt->diffInSeconds($sunrise, false));
                if ($dt->lessThan($sunrise) && $secondsDeltaFromSunrise >= 60) {
                    $dt = $dt->addDay();
                }
            }

            return $dt;
        }

        return null;
    }

    private function formatTransitionWindow(array $interval, string $type, string $tz): array
    {
        $name = (string) ($interval['name'] ?? '');
        if ($type === 'pada') {
            $name = ($interval['nakshatra'] ?? '') . ' Pada ' . ($interval['pada'] ?? '');
        }

        return [
            'name' => $name,
            'type' => $type,
            'start_jd' => $interval['start_jd'],
            'end_jd' => $interval['end_jd'],
            'start_iso' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic((float) $interval['start_jd'], $tz)),
            'end_iso' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic((float) $interval['end_jd'], $tz)),
        ];
    }

}
