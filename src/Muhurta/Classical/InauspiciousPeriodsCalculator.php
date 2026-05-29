<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Muhurta\Classical;

use Carbon\CarbonImmutable;
use DateTimeZone;
use InvalidArgumentException;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Core\Enums\Nakshatra;
use JayeshMepani\PanchangCore\Core\Localization;
use RuntimeException;

/** Inauspicious Periods Calculator - Handles Rahu Kaal, Gulika, Yamaganda, Varjyam, Amrita Kaal, Pradosha Kaal. */
class InauspiciousPeriodsCalculator
{
    private const int PERIOD_GHATIS = 4;

    /** @var array<int, array{name:string, amrita:int[], varjyam:int[]}> */
    private const array NAKSHATRA_AMRITA_VARJYAM_OFFSETS = [
        0 => ['name' => 'Ashwini', 'amrita' => [42], 'varjyam' => [50]],
        1 => ['name' => 'Bharani', 'amrita' => [48], 'varjyam' => [24]],
        2 => ['name' => 'Krittika', 'amrita' => [54], 'varjyam' => [30]],
        3 => ['name' => 'Rohini', 'amrita' => [52], 'varjyam' => [40]],
        4 => ['name' => 'Mrigashirsha', 'amrita' => [38], 'varjyam' => [14]],
        5 => ['name' => 'Ardra', 'amrita' => [35], 'varjyam' => [21]],
        6 => ['name' => 'Punarvasu', 'amrita' => [54], 'varjyam' => [30]],
        7 => ['name' => 'Pushya', 'amrita' => [44], 'varjyam' => [20]],
        8 => ['name' => 'Ashlesha', 'amrita' => [56], 'varjyam' => [32]],
        9 => ['name' => 'Magha', 'amrita' => [54], 'varjyam' => [30]],
        10 => ['name' => 'Purva Phalguni', 'amrita' => [44], 'varjyam' => [20]],
        11 => ['name' => 'Uttara Phalguni', 'amrita' => [42], 'varjyam' => [18]],
        12 => ['name' => 'Hasta', 'amrita' => [45], 'varjyam' => [21]],
        13 => ['name' => 'Chitra', 'amrita' => [44], 'varjyam' => [20]],
        14 => ['name' => 'Swati', 'amrita' => [38], 'varjyam' => [14]],
        15 => ['name' => 'Vishakha', 'amrita' => [38], 'varjyam' => [14]],
        16 => ['name' => 'Anuradha', 'amrita' => [34], 'varjyam' => [10]],
        17 => ['name' => 'Jyeshtha', 'amrita' => [38], 'varjyam' => [14]],
        18 => ['name' => 'Mula', 'amrita' => [44], 'varjyam' => [21]],
        19 => ['name' => 'Purva Ashadha', 'amrita' => [48], 'varjyam' => [24]],
        20 => ['name' => 'Uttara Ashadha', 'amrita' => [44], 'varjyam' => [20]],
        21 => ['name' => 'Shravana', 'amrita' => [34], 'varjyam' => [10]],
        22 => ['name' => 'Dhanishta', 'amrita' => [34], 'varjyam' => [10]],
        23 => ['name' => 'Shatabhisha', 'amrita' => [42], 'varjyam' => [18]],
        24 => ['name' => 'Purva Bhadrapada', 'amrita' => [40], 'varjyam' => [16]],
        25 => ['name' => 'Uttara Bhadrapada', 'amrita' => [48], 'varjyam' => [24]],
        26 => ['name' => 'Revati', 'amrita' => [54], 'varjyam' => [30]],
    ];

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
        $windows = $this->calculateNakshatraPeriodWindows(
            'varjyam',
            $nakshatraIndex,
            $nakshatraStartJd,
            $nakshatraEndJd,
            $sunrise,
            $nakshatraStartJd,
            $nakshatraEndJd
        );

        return $windows[0] ?? ['is_available' => false];
    }

    public function calculateNakshatraPeriodWindows(
        string $type,
        int $nakshatraIndex,
        float $nakshatraStartJd,
        float $nakshatraEndJd,
        CarbonImmutable $timezoneReference,
        float $scopeStartJd,
        float $scopeEndJd,
        bool $includePartialWindows = true
    ): array
    {
        if ($type !== 'amrita_kaal' && $type !== 'varjyam') {
            throw new InvalidArgumentException('Invalid Nakshatra period type.');
        }

        if ($nakshatraEndJd <= $nakshatraStartJd) {
            throw new InvalidArgumentException('Nakshatra end must be after start.');
        }

        if ($scopeEndJd <= $scopeStartJd) {
            throw new InvalidArgumentException('Scope end must be after start.');
        }

        $index = (($nakshatraIndex % 27) + 27) % 27;
        $config = self::NAKSHATRA_AMRITA_VARJYAM_OFFSETS[$index];

        $offsets = $config[$type === 'amrita_kaal' ? 'amrita' : 'varjyam'];
        $windows = [];
        foreach ($offsets as $offsetGhati) {
            $windows[] = $this->buildNakshatraPeriodWindow(
                $type,
                $index,
                $nakshatraStartJd,
                $nakshatraEndJd,
                $offsetGhati,
                $timezoneReference,
                $scopeStartJd,
                $scopeEndJd,
                $includePartialWindows
            );
        }

        return array_values(array_filter($windows, static fn (?array $window): bool => $window !== null));
    }

    public function calculateAmritaKaal(CarbonImmutable $sunrise, array $varjyam): array
    {
        unset($sunrise, $varjyam);

        return [
            'is_available' => false,
            'window_count' => 0,
            'windows' => [],
            'calculation_note' => 'Amrita Kaal requires Nakshatra-specific Amrita offsets; use calculateNakshatraPeriodWindows().',
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

    private function buildNakshatraPeriodWindow(
        string $type,
        int $nakshatraIndex,
        float $nakshatraStartJd,
        float $nakshatraEndJd,
        int $offsetGhati,
        CarbonImmutable $timezoneReference,
        float $scopeStartJd,
        float $scopeEndJd,
        bool $includePartialWindows
    ): ?array {
        if ($offsetGhati < 0 || $offsetGhati > 60) {
            throw new InvalidArgumentException('Nakshatra period offset must be between 0 and 60 ghatis.');
        }

        $nakshatraDurationJd = $nakshatraEndJd - $nakshatraStartJd;
        $periodDurationJd = $nakshatraDurationJd * self::PERIOD_GHATIS / 60.0;
        $windowStartJd = $nakshatraStartJd + ($nakshatraDurationJd * $offsetGhati / 60.0);
        $windowEndJd = $windowStartJd + $periodDurationJd;

        if ($windowStartJd < $nakshatraStartJd || $windowStartJd > $nakshatraEndJd || $windowEndJd < $windowStartJd || $windowEndJd > $nakshatraEndJd) {
            throw new RuntimeException('Nakshatra period window exceeds its Nakshatra bounds.');
        }

        if ($windowStartJd >= $scopeEndJd || $windowEndJd <= $scopeStartJd) {
            return null;
        }

        if ($includePartialWindows) {
            $visibleStartJd = max($windowStartJd, $scopeStartJd);
            $visibleEndJd = min($windowEndJd, $scopeEndJd);
        } elseif ($windowStartJd >= $scopeStartJd && $windowEndJd <= $scopeEndJd) {
            $visibleStartJd = $windowStartJd;
            $visibleEndJd = $windowEndJd;
        } else {
            return null;
        }

        $tz = $timezoneReference->getTimezone();
        $start = $this->jdToCarbon($windowStartJd, $tz);
        $end = $this->jdToCarbon($windowEndJd, $tz);
        $visibleStart = $this->jdToCarbon($visibleStartJd, $tz);
        $visibleEnd = $this->jdToCarbon($visibleEndJd, $tz);
        $nakStart = $this->jdToCarbon($nakshatraStartJd, $tz);
        $nakEnd = $this->jdToCarbon($nakshatraEndJd, $tz);

        $prefix = $type === 'amrita_kaal' ? 'amrita_kaal' : 'varjyam';

        return [
            $prefix . '_start' => AstroCore::formatTime($start),
            $prefix . '_end' => AstroCore::formatTime($end),
            $prefix . '_start_iso' => AstroCore::formatDateTime($start),
            $prefix . '_end_iso' => AstroCore::formatDateTime($end),
            'type' => $type,
            'duration_minutes' => AstroCore::formatDuration($periodDurationJd * 1440.0),
            'duration_seconds_raw' => $periodDurationJd * 86400.0,
            'duration_ghati' => self::PERIOD_GHATIS,
            'offset_ghati' => $offsetGhati,
            'nakshatra_start_jd' => $nakshatraStartJd,
            'nakshatra_end_jd' => $nakshatraEndJd,
            'nakshatra_start_iso' => AstroCore::formatDateTime($nakStart),
            'nakshatra_end_iso' => AstroCore::formatDateTime($nakEnd),
            'nakshatra_duration_seconds_raw' => $nakshatraDurationJd * 86400.0,
            'nakshatra_index' => $nakshatraIndex,
            'nakshatra_name' => Nakshatra::from($nakshatraIndex)->getName(),
            'window_start_jd' => $windowStartJd,
            'window_end_jd' => $windowEndJd,
            'window_start_iso' => AstroCore::formatDateTime($start),
            'window_end_iso' => AstroCore::formatDateTime($end),
            'visible_start_jd' => $visibleStartJd,
            'visible_end_jd' => $visibleEndJd,
            'visible_start_iso' => AstroCore::formatDateTime($visibleStart),
            'visible_end_iso' => AstroCore::formatDateTime($visibleEnd),
            'is_partial_start' => $visibleStartJd > $windowStartJd,
            'is_partial_end' => $visibleEndJd < $windowEndJd,
            'is_auspicious' => $type === 'amrita_kaal',
        ];
    }
}
