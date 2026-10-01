<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Muhurta\Panchaka;

use Carbon\CarbonImmutable;
use DateTimeZone;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Core\Enums\Vara;
use JayeshMepani\PanchangCore\Core\Localization;

class PanchakaRahitaTableCalculator
{
    private const array PANCHAKA_DEFS = [
        1 => ['name' => 'Mrityu Panchaka', 'short_name' => 'Mrityu', 'english' => 'Death', 'severity' => 'critical', 'has_dosha' => true],
        2 => ['name' => 'Agni Panchaka', 'short_name' => 'Agni', 'english' => 'Fire', 'severity' => 'high', 'has_dosha' => true],
        3 => ['name' => 'Shubha Panchaka', 'short_name' => 'Shubha', 'english' => 'Auspicious', 'severity' => 'none', 'has_dosha' => false],
        4 => ['name' => 'Raja Panchaka', 'short_name' => 'Raja', 'english' => 'King', 'severity' => 'medium', 'has_dosha' => true],
        5 => ['name' => 'Shubha Panchaka', 'short_name' => 'Shubha', 'english' => 'Auspicious', 'severity' => 'none', 'has_dosha' => false],
        6 => ['name' => 'Chora Panchaka', 'short_name' => 'Chora', 'english' => 'Thief', 'severity' => 'high', 'has_dosha' => true],
        7 => ['name' => 'Shubha Panchaka', 'short_name' => 'Shubha', 'english' => 'Auspicious', 'severity' => 'none', 'has_dosha' => false],
        8 => ['name' => 'Roga Panchaka', 'short_name' => 'Roga', 'english' => 'Disease', 'severity' => 'high', 'has_dosha' => true],
        0 => ['name' => 'Nish-Panchaka', 'short_name' => 'Shubha', 'english' => 'Flawless Auspicious', 'severity' => 'none', 'has_dosha' => false],
    ];

    /** Calculate Panchaka Rahita Muhurat timeline for the full Vedic day (sunrise to next sunrise). */
    public function calculatePanchakaRahitaTable(
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise,
        int $varaIdx,
        array $lagnaTable,
        array $tithiIntervals,
        array $nakshatraIntervals,
        string $tz
    ): array {
        $tzObj = new DateTimeZone($tz);
        $jdSunrise = $this->carbonToJd($sunrise);
        $jdSunset = $this->carbonToJd($sunset);
        $jdNextSunrise = $this->carbonToJd($nextSunrise);

        if ($jdNextSunrise <= $jdSunrise) {
            return [];
        }

        $varaNum = ($varaIdx % 7) + 1; // 1=Sun, 2=Mon... 7=Sat

        // 1. Collect all boundary points in [jdSunrise, jdNextSunrise]
        $boundaryMap = [];
        $addPoint = function (float $jd) use (&$boundaryMap, $jdSunrise, $jdNextSunrise): void {
            if ($jd >= $jdSunrise && $jd <= $jdNextSunrise) {
                $key = (int) round($jd * 86400.0);
                $boundaryMap[$key] = $jd;
            }
        };

        $addPoint($jdSunrise);
        $addPoint($jdNextSunrise);

        foreach ($lagnaTable as $lagna) {
            $s = (float) ($lagna['visible_start_jd'] ?? $lagna['start_jd']);
            $e = (float) ($lagna['visible_end_jd'] ?? $lagna['end_jd']);
            $addPoint($s);
            $addPoint($e);
        }

        foreach ($tithiIntervals as $tithi) {
            $s = (float) ($tithi['start_jd'] ?? 0.0);
            $e = (float) ($tithi['end_jd'] ?? 0.0);
            $addPoint($s);
            $addPoint($e);
        }

        foreach ($nakshatraIntervals as $nak) {
            $s = (float) ($nak['start_jd'] ?? 0.0);
            $e = (float) ($nak['end_jd'] ?? 0.0);
            $addPoint($s);
            $addPoint($e);
        }

        sort($boundaryMap);
        $sortedJds = $boundaryMap;

        // Merge boundary points within 5 seconds
        $mergedJds = [$sortedJds[0]];
        $counter = count($sortedJds);
        for ($i = 1; $i < $counter; $i++) {
            $curr = $sortedJds[$i];
            $prev = $mergedJds[count($mergedJds) - 1];
            if (($curr - $prev) * 86400.0 > 5.0) {
                $mergedJds[] = $curr;
            }
        }

        if (($jdNextSunrise - $mergedJds[count($mergedJds) - 1]) * 86400.0 <= 5.0) {
            $mergedJds[count($mergedJds) - 1] = $jdNextSunrise;
        } else {
            $mergedJds[] = $jdNextSunrise;
        }

        $table = [];
        for ($i = 0; $i < count($mergedJds) - 1; $i++) {
            $sJd = $mergedJds[$i];
            $eJd = $mergedJds[$i + 1];
            $midJd = ($sJd + $eJd) / 2.0;

            // Find active Lagna
            $activeLagna = null;
            foreach ($lagnaTable as $lagna) {
                $lS = (float) ($lagna['visible_start_jd'] ?? $lagna['start_jd']);
                $lE = (float) ($lagna['visible_end_jd'] ?? $lagna['end_jd']);
                if ($midJd >= $lS && $midJd <= $lE) {
                    $activeLagna = $lagna;
                    break;
                }
            }

            if ($activeLagna === null && $lagnaTable !== []) {
                $activeLagna = $lagnaTable[0];
            }

            // Find active Tithi
            $activeTithi = null;
            foreach ($tithiIntervals as $tithi) {
                $tS = (float) ($tithi['start_jd'] ?? 0.0);
                $tE = (float) ($tithi['end_jd'] ?? 0.0);
                if ($midJd >= $tS && $midJd <= $tE) {
                    $activeTithi = $tithi;
                    break;
                }
            }

            // Find active Nakshatra
            $activeNak = null;
            foreach ($nakshatraIntervals as $nak) {
                $nS = (float) ($nak['start_jd'] ?? 0.0);
                $nE = (float) ($nak['end_jd'] ?? 0.0);
                if ($midJd >= $nS && $midJd <= $nE) {
                    $activeNak = $nak;
                    break;
                }
            }

            $tithiNum = (int) ($activeTithi['index'] ?? 1);
            $nakNum = (int) (($activeNak['index'] ?? 0) + 1); // 1..27
            $lagnaNum = (int) ($activeLagna['lagna_number'] ?? 1);

            $sum = $tithiNum + $varaNum + $nakNum + $lagnaNum;
            $remainder = $sum % 9;
            $def = self::PANCHAKA_DEFS[$remainder] ?? self::PANCHAKA_DEFS[0];
            $hasDosha = $def['has_dosha'];
            $isAuspicious = !$hasDosha;

            $startTime = $this->jdToCarbon($sJd, $tzObj);
            $endTime = $this->jdToCarbon($eJd, $tzObj);

            $localizedName = Localization::translate('Panchaka', $def['name']);
            $quality = $isAuspicious ? 'Auspicious' : 'Inauspicious';
            $nature = strtolower($quality);

            $table[] = [
                'name' => $localizedName,
                'panchaka_name' => $def['name'],
                'short_name' => $def['short_name'],
                'english_name' => $def['english'],
                'type' => $nature,
                'nature' => $nature,
                'quality' => $quality,
                'severity' => $def['severity'],
                'has_dosha' => $hasDosha,
                'is_auspicious' => $isAuspicious,
                'start' => AstroCore::formatTime($startTime),
                'end' => AstroCore::formatTime($endTime),
                'start_time' => AstroCore::formatTime($startTime),
                'end_time' => AstroCore::formatTime($endTime),
                'start_iso' => AstroCore::formatDateTime($startTime),
                'end_iso' => AstroCore::formatDateTime($endTime),
                'start_jd' => $sJd,
                'end_jd' => $eJd,
                'duration_minutes' => ($eJd - $sJd) * 1440.0,
                'is_day_period' => $midJd < $jdSunset,
                'period' => $midJd < $jdSunset ? 'Day' : 'Night',
                'tithi' => $tithiNum,
                'tithi_name' => $activeTithi['name'] ?? '',
                'nakshatra' => $nakNum,
                'nakshatra_name' => $activeNak['name'] ?? '',
                'lagna' => $lagnaNum,
                'lagna_name' => $activeLagna['sign_name'] ?? '',
                'vara' => $varaNum,
                'vara_name' => Vara::from($varaIdx % 7)->getName(),
                'sum' => $sum,
                'remainder' => $remainder,
            ];
        }

        return $table;
    }

    private function jdToCarbon(float $jd, DateTimeZone $tz): CarbonImmutable
    {
        $unixTimestamp = ($jd - 2440587.5) * 86400.0;
        $seconds = (int) floor($unixTimestamp);
        $micros = (int) round(($unixTimestamp - $seconds) * 1_000_000);
        if ($micros >= 1_000_000) {
            $seconds++;
            $micros -= 1_000_000;
        }

        return CarbonImmutable::createFromTimestampUTC($seconds)->setTimezone($tz)->setMicrosecond($micros);
    }

    private function carbonToJd(CarbonImmutable $dt): float
    {
        return ($dt->getTimestamp() + $dt->microsecond / 1_000_000.0) / 86400.0 + 2440587.5;
    }
}
