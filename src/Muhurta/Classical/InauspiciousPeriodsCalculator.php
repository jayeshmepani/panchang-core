<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Muhurta\Classical;

use Carbon\CarbonImmutable;
use DateTimeZone;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Core\Enums\Nakshatra;
use JayeshMepani\PanchangCore\Core\Localization;
use Throwable;

/** Inauspicious Periods Calculator - Handles Rahu Kaal, Gulika, Yamaganda, Varjyam, Amrita Kaal, Pradosha Kaal. */
class InauspiciousPeriodsCalculator
{
    public function calculateBadTimes(CarbonImmutable $sunrise, CarbonImmutable $sunset, int $varaIdx): array
    {
        $dayDuration = $sunset->getTimestamp() - $sunrise->getTimestamp();
        $part = $dayDuration / 8.0;

        $rahuParts = [1 => 2, 2 => 7, 3 => 5, 4 => 6, 5 => 4, 6 => 3, 0 => 8];
        $gulikaParts = [1 => 6, 2 => 5, 3 => 4, 4 => 3, 5 => 2, 6 => 1, 0 => 7];
        $yamaParts = [1 => 4, 2 => 3, 3 => 2, 4 => 1, 5 => 7, 6 => 6, 0 => 5];

        $getTime = function (int $pIdx) use ($sunrise, $part): array {
            $start = $this->addFloatSeconds($sunrise, ($pIdx - 1) * $part);
            $end = $this->addFloatSeconds($start, $part);

            return [
                'start' => AstroCore::formatTime($start),
                'end' => AstroCore::formatTime($end),
                'duration_min' => AstroCore::formatDuration($part / 60.0),
            ];
        };

        return [
            Localization::translate('String', 'Rahu Kaal') => $getTime($rahuParts[$varaIdx]),
            Localization::translate('String', 'Gulika') => $getTime($gulikaParts[$varaIdx]),
            Localization::translate('String', 'Yamaganda') => $getTime($yamaParts[$varaIdx]),
        ];
    }

    public function calculateVarjyam(
        int $nakshatraIndex,
        float $nakshatraStartJd,
        float $nakshatraEndJd,
        CarbonImmutable $sunrise
    ): array {
        $tyajyaRanges = [
            [51, 54], [25, 28], [31, 34], [41, 44], [15, 18], [22, 25], [31, 34], [21, 24], [33, 36],
            [31, 34], [21, 24], [19, 22], [22, 25], [21, 24], [15, 18], [15, 18], [11, 14], [15, 18],
            [57, 60], [25, 28], [21, 24], [11, 14], [11, 14], [19, 22], [17, 20], [25, 28], [31, 34],
        ];

        [$tStart, $tEnd] = $tyajyaRanges[$nakshatraIndex % 27] ?? [31, 34];
        $tStartActual = max(0, $tStart - 1);

        $durationSec = ($nakshatraEndJd - $nakshatraStartJd) * 86400.0;
        $vStartOff = ($tStartActual * $durationSec) / 60.0;
        $vDur = (($tEnd - $tStartActual) * $durationSec) / 60.0;

        $nStart = $this->jdToCarbon($nakshatraStartJd, $sunrise->getTimezone());
        $vStart = $this->addFloatSeconds($nStart, $vStartOff);
        $vEnd = $this->addFloatSeconds($vStart, $vDur);

        return [
            'varjyam_start' => AstroCore::formatTime($vStart),
            'varjyam_end' => AstroCore::formatTime($vEnd),
            'duration_minutes' => AstroCore::formatDuration($vDur / 60.0),
            'duration_seconds_raw' => $vDur,
            'nakshatra_start_jd' => $nakshatraStartJd,
            'nakshatra_end_jd' => $nakshatraEndJd,
            'nakshatra_index' => $nakshatraIndex,
            'nakshatra_name' => Nakshatra::from($nakshatraIndex % 27)->getName(),
            'tyajya_ghati_start' => $tStart,
            'tyajya_ghati_end' => $tEnd,
            'is_auspicious' => false,
        ];
    }

    public function calculateAmritaKaal(CarbonImmutable $sunrise, array $varjyam): array
    {
        if (!isset($varjyam['varjyam_end']) || !isset($varjyam['duration_seconds_raw'])) {
            return ['is_available' => false];
        }

        $vEndStr = preg_replace('/[^0-9:]/', '', (string) $varjyam['varjyam_end']);
        try {
            $vEnd = CarbonImmutable::createFromFormat('H:i:s', $vEndStr, $sunrise->getTimezone())->setDate($sunrise->year, $sunrise->month, $sunrise->day);
        } catch (Throwable) {
            return ['is_available' => false];
        }

        $aDur = (float) $varjyam['duration_seconds_raw'];
        $aStart = $vEnd;
        $aEnd = $this->addFloatSeconds($aStart, $aDur);

        return [
            'amrita_kaal_start' => AstroCore::formatTime($aStart),
            'amrita_kaal_end' => AstroCore::formatTime($aEnd),
            'duration_minutes' => AstroCore::formatDuration($aDur / 60.0),
            'is_auspicious' => true,
            'amrita_kaal_start_iso' => AstroCore::formatDateTime($aStart),
            'amrita_kaal_end_iso' => AstroCore::formatDateTime($aEnd),
        ];
    }

    public function calculatePradoshaKaal(CarbonImmutable $sunset, int $tithiNum): array
    {
        $isTrayodashi = ($tithiNum === 13);
        $dur = 90.0 * 60.0;
        $start = $this->addFloatSeconds($sunset, -$dur);
        $end = $this->addFloatSeconds($sunset, $dur);

        return [
            'name' => Localization::translate('String', 'Pradosha Kaal'),
            'pradosha_start' => AstroCore::formatTime($start),
            'pradosha_end' => AstroCore::formatTime($end),
            'is_auspicious' => $isTrayodashi,
        ];
    }

    private function addFloatSeconds(CarbonImmutable $dt, float $seconds): CarbonImmutable
    {
        $whole = (int) floor($seconds);
        $fraction = $seconds - $whole;

        return $dt->addSeconds($whole)->addMicroseconds((int) ($fraction * 1_000_000));
    }

    private function jdToCarbon(float $jd, DateTimeZone $tz): CarbonImmutable
    {
        $unixTimestamp = ($jd - 2440587.5) * 86400.0;
        $seconds = (int) floor($unixTimestamp);
        $microseconds = (int) (($unixTimestamp - $seconds) * 1_000_000);

        return CarbonImmutable::createFromTimestamp($seconds, $tz)->addMicroseconds($microseconds);
    }
}
