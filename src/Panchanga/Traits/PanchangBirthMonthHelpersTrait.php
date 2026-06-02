<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Panchanga\Traits;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Core\Enums\Masa;
use JayeshMepani\PanchangCore\Core\Localization;
use JmeEph\FFI\JmeEphFFI;
use Throwable;

trait PanchangBirthMonthHelpersTrait
{
    private function getSunMoonLongitudes(array $birth): array
    {
        $jd = $this->toJulianDayFromCarbon(
            CarbonImmutable::create(
                (int) $birth['year'],
                (int) $birth['month'],
                (int) $birth['day'],
                (int) $birth['hour'],
                (int) $birth['minute'],
                (int) $birth['second'],
                $birth['timezone']
            ),
            $birth['timezone']
        );

        $flags = JmeEphFFI::JME_CALC_HIGH_PRECISION | JmeEphFFI::JME_CALC_SIDEREAL;

        $sun = $this->calcBody($jd, JmeEphFFI::JME_BODY_SUN, $flags);
        $moon = $this->calcBody($jd, JmeEphFFI::JME_BODY_MOON, $flags);

        return [
            'Sun' => $sun,
            'Moon' => $moon,
        ];
    }

    private function calcBody(float $jd, int $planet, int $flags): float
    {
        $cacheKey = sprintf('%.17g|%d|%d', $jd, $planet, $flags);
        if (array_key_exists($cacheKey, $this->bodyLongitudeCache)) {
            return $this->bodyLongitudeCache[$cacheKey];
        }

        $this->jme->jme_calc_ut($jd, $planet, $flags, $this->calcBodyBuffer, $this->calcBodyErrorBuffer);
        $value = $this->normalize($this->calcBodyBuffer[0]);

        return $this->rememberBodyLongitude($jd, $planet, $flags, $value);
    }

    private function calculateIshtkaal(CarbonImmutable $sunrise, array $birth, string $tz): string
    {
        $dt = CarbonImmutable::create(
            (int) $birth['year'],
            (int) $birth['month'],
            (int) $birth['day'],
            (int) $birth['hour'],
            (int) $birth['minute'],
            (int) $birth['second'],
            $tz
        );

        $relSunrise = $sunrise;
        if ($dt->lessThan($sunrise)) {
            $relSunrise = $sunrise->subDay();
        }

        $sec = (int) abs($dt->diffInSeconds($relSunrise, false));

        $gh = (int) floor($sec / 1440);
        $pl = (int) floor(($sec % 1440) / 24);
        $vp = (int) floor((($sec % 1440) % 24) / 0.4);

        return sprintf('%02d:%02d:%02d', $gh, $pl, $vp);
    }

    private function normalize(float $value): float
    {
        return AstroCore::normalize($value);
    }

    /**
     * Calculate True Hindu Month using exact solar transits (Sankranti)
     * between exact Sun-Moon conjunctions (Amavasya).
     *
     * Correct algorithm per Calendrical Calculations (Reingold & Dershowitz):
     * 1. The Amanta month runs from one Amavasya (new moon) to the next.
     * 2. The month NAME is determined by which solar Sankranti (sun sign
     *    crossing) occurs DURING the lunar month — i.e., the sun's sign
     *    at the ENDING Amavasya (next new moon).
     * 3. Adhika Maas: NO Sankranti occurs between two consecutive Amavasyas
     *    (sun stays in the same sign) → the second month is a leap month.
     * 4. Kshaya Maas: TWO Sankrantis occur between two consecutive Amavasyas
     *    (sun jumps 2+ signs) → the intermediate month is skipped entirely.
     *
     * Lossless algorithm as per Siddhantic tradition.
     */
    private function getTrueHinduMonth(float $jd): array
    {
        // Find the Amavasya that STARTS the current lunar month (most recent new moon)
        $startAmavasya = $this->findAngleCrossing($jd, 0.0, -1, fn (float $t): float => $this->getMoonSunAngle($t));
        // Find the Amavasya that ENDS the current lunar month (next new moon)
        // Start from slightly after the start Amavasya to ensure we find the NEXT one
        $endAmavasya = $this->findAngleCrossing($startAmavasya + 1.0, 0.0, 1, fn (float $t): float => $this->getMoonSunAngle($t));

        // Sun's sidereal longitude at both Amavasyas
        $sunAtStart = $this->getSunLongitude($startAmavasya);
        $sunAtEnd = $this->getSunLongitude($endAmavasya);

        $signAtStart = (int) floor($sunAtStart / 30.0) % 12;
        $signAtEnd = (int) floor($sunAtEnd / 30.0) % 12;

        // Count sign crossings between the two Amavasyas
        // The sun moves ~1°/day, so in ~29.5 days it moves ~29.5° ≈ 1 sign.
        // Normal month: 1 crossing. Adhika: 0 crossings. Kshaya: 2+ crossings.
        $signCrossings = ($signAtEnd - $signAtStart + 12) % 12;

        $isAdhika = ($signCrossings === 0);
        $isKshaya = ($signCrossings >= 2);

        // Month name = sun's sign at the ENDING Amavasya
        // (the sign the sun entered during this lunar month)
        $amantaIdx = $signAtEnd;

        // For Adhika: the sun didn't enter a new sign, so this month repeats
        // the previous month's name. The Adhika month takes the name of the
        // sign the sun WILL enter (the NEXT sign after signAtStart).
        if ($isAdhika) {
            $amantaIdx = ($signAtStart + 1) % 12;
        }

        $adhikaStr = ' (' . Localization::translate('Common', 'Adhika') . ')';
        $kshayaStr = ' (' . Localization::translate('Common', 'Kshaya') . ')';

        $amantaName = Masa::from($amantaIdx)->getName();
        $amantaNameEn = Masa::from($amantaIdx)->getName('en');
        if ($isAdhika) {
            $amantaName .= $adhikaStr;
            $amantaNameEn .= $adhikaStr;
        } elseif ($isKshaya) {
            $amantaName .= $kshayaStr;
            $amantaNameEn .= $kshayaStr;
        }

        // Purnimanta month: Amanta month during Shukla Paksha, (Amanta+1) during Krishna Paksha
        $moonSunAngle = $this->getMoonSunAngle($jd);
        $paksha = ($moonSunAngle < 180.0) ? 'Shukla' : 'Krishna';

        $purnimantaIdx = ($paksha === 'Shukla') ? $amantaIdx : ($amantaIdx + 1) % 12;
        $purnimantaName = Masa::from($purnimantaIdx)->getName();
        $purnimantaNameEn = Masa::from($purnimantaIdx)->getName('en');
        // Purnimanta gets Adhika suffix when we're in Shukla Paksha of an Adhika month
        if ($isAdhika && $paksha === 'Shukla') {
            $purnimantaName .= $adhikaStr;
            $purnimantaNameEn .= $adhikaStr;
        }

        $data = [
            'Month_Amanta' => $amantaName,
            'Month_Amanta_En' => $amantaNameEn,
            'Month_Purnimanta' => $purnimantaName,
            'Month_Purnimanta_En' => $purnimantaNameEn,
            'Amanta_Index' => $amantaIdx,
            'Purnimanta_Index' => $purnimantaIdx,
            'Is_Adhika' => $isAdhika,
            'Is_Kshaya' => $isKshaya,
        ];

        $this->monthCache[] = [
            'start' => $startAmavasya,
            'end' => $endAmavasya,
            'data' => $data,
        ];

        // Keep cache manageable (LRU-ish)
        if (count($this->monthCache) > 3) {
            array_shift($this->monthCache);
        }

        return $data;
    }

    private function buildBirthArray(
        CarbonImmutable $dt,
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0
    ): array {
        return [
            'year' => (int) $dt->format('Y'),
            'month' => (int) $dt->format('m'),
            'day' => (int) $dt->format('d'),
            'hour' => (int) $dt->format('H'),
            'minute' => (int) $dt->format('i'),
            'second' => (int) $dt->format('s'),
            'timezone' => $tz,
            'latitude' => $lat,
            'longitude' => $lon,
            'elevation' => $elevation,
        ];
    }

    private function parseDisplayDateTime(string $value, string $tz): CarbonImmutable
    {
        $formats = ['d/m/Y h:i:s A', 'd/m/Y h:i A', 'Y-m-d H:i:s', 'Y-m-d\\TH:i:sP', 'Y-m-d H:i'];
        foreach ($formats as $format) {
            try {
                $parsed = CarbonImmutable::createFromFormat($format, trim($value), $tz);
            } catch (Throwable) {
                $parsed = false;
            }

            if ($parsed instanceof CarbonImmutable) {
                return $parsed;
            }
        }

        return CarbonImmutable::parse($value, $tz);
    }
}
