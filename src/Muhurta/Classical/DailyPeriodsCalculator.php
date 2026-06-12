<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Muhurta\Classical;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Core\Enums\Muhurta;
use JayeshMepani\PanchangCore\Core\Localization;

/** Daily Periods Calculator - Handles Abhijit, Brahma, Dur, Nishita, Vijaya, Godhuli, Sandhya, Prahara. */
class DailyPeriodsCalculator
{
    public function calculateAbhijitMuhurta(CarbonImmutable $sunrise, CarbonImmutable $sunset): array
    {
        $daySeconds = $sunset->getTimestamp() - $sunrise->getTimestamp();
        $muhurtaDuration = $daySeconds / 15.0;

        $abhijitStart = $this->addFloatSeconds($sunrise, 7 * $muhurtaDuration);
        $abhijitEnd = $this->addFloatSeconds($abhijitStart, $muhurtaDuration);
        $daylightMidpoint = $this->addFloatSeconds($sunrise, $daySeconds / 2.0);

        return [
            'source' => Localization::translate('Source', 'Muhūrta Chintāmaṇi / Nārada Saṁhitā'),
            'abhijit_start' => AstroCore::formatTime($abhijitStart),
            'abhijit_end' => AstroCore::formatTime($abhijitEnd),
            'daylight_midpoint' => AstroCore::formatTime($daylightMidpoint),
            'muhurta_duration_minutes' => AstroCore::formatDuration($muhurtaDuration / 60.0),
            'muhurta_number' => '8th of 15 (Abhijit)',
            'abhijit_start_iso' => AstroCore::formatDateTime($abhijitStart),
            'abhijit_end_iso' => AstroCore::formatDateTime($abhijitEnd),
            'daylight_midpoint_iso' => AstroCore::formatDateTime($daylightMidpoint),
        ];
    }

    public function calculateBrahmaMuhurta(CarbonImmutable $previousSunset, CarbonImmutable $sunrise): array
    {
        $fixedMuhurtaSeconds = 48.0 * 60.0;
        $fixedStart = $sunrise->subSeconds((int) ($fixedMuhurtaSeconds * 2));
        $fixedEnd = $sunrise->subSeconds((int) $fixedMuhurtaSeconds);
        $nightSeconds = $sunrise->getTimestamp() - $previousSunset->getTimestamp();

        if ($nightSeconds <= 0) {
            throw new InvalidArgumentException('Previous sunset must be before sunrise for Brahma Muhurta calculation.');
        }

        $nightMuhurtaSeconds = $nightSeconds / 15.0;
        $start = $this->addFloatSeconds($sunrise, -2.0 * $nightMuhurtaSeconds);
        $end = $this->addFloatSeconds($sunrise, -1.0 * $nightMuhurtaSeconds);

        return [
            'source' => Localization::translate('Source', 'Night divided into 15 Muhurtas; Brahma Muhurta is the penultimate night Muhurta before sunrise'),
            'calculation_convention' => 'dynamic_night_muhurta',
            'calculation_convention_label' => Localization::translate('String', 'Dynamic night Muhurta convention'),
            'previous_sunset_iso' => AstroCore::formatDateTime($previousSunset),
            'sunrise_iso' => AstroCore::formatDateTime($sunrise),
            'brahma_muhurta_start' => AstroCore::formatTime($start),
            'brahma_muhurta_end' => AstroCore::formatTime($end),
            'duration_minutes' => $nightMuhurtaSeconds / 60.0,
            'duration_seconds' => $nightMuhurtaSeconds,
            'night_duration_seconds' => $nightSeconds,
            'significance' => Localization::translate('MuhurtaDesc', 'Brahma Muhurta significance'),
            'brahma_muhurta_start_iso' => AstroCore::formatDateTime($start),
            'brahma_muhurta_end_iso' => AstroCore::formatDateTime($end),
            'fixed_48_minute_convention' => [
                'calculation_convention' => 'fixed_48_minute_muhurta',
                'calculation_convention_label' => Localization::translate('String', 'Fixed 48-minute Muhurta convention'),
                'brahma_muhurta_start' => AstroCore::formatTime($fixedStart),
                'brahma_muhurta_end' => AstroCore::formatTime($fixedEnd),
                'duration_minutes' => 48,
                'duration_seconds' => $fixedMuhurtaSeconds,
                'brahma_muhurta_start_iso' => AstroCore::formatDateTime($fixedStart),
                'brahma_muhurta_end_iso' => AstroCore::formatDateTime($fixedEnd),
            ],
        ];
    }

    public function calculateMuhurtaTable(
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise
    ): array {
        $rows = [];
        $dayDuration = ($sunset->getTimestamp() - $sunrise->getTimestamp()) / 15.0;
        $nightDuration = ($nextSunrise->getTimestamp() - $sunset->getTimestamp()) / 15.0;
        $daySeq = Muhurta::getDaySequence();
        $nightSeq = Muhurta::getNightSequence();

        for ($i = 0; $i < 15; $i++) {
            $start = $this->addFloatSeconds($sunrise, $i * $dayDuration);
            $rows[] = $this->buildTimedRow($start, $dayDuration, [
                'period' => Localization::translate('String', 'Day'),
                'muhurta_number' => $i + 1,
                'name' => $daySeq[$i]->getName(),
            ]);
        }

        for ($i = 0; $i < 15; $i++) {
            $start = $this->addFloatSeconds($sunset, $i * $nightDuration);
            $rows[] = $this->buildTimedRow($start, $nightDuration, [
                'period' => Localization::translate('String', 'Night'),
                'muhurta_number' => $i + 1,
                'name' => $nightSeq[$i]->getName(),
            ]);
        }

        return $rows;
    }

    public function calculateDurMuhurta(
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise,
        int $varaIdx
    ): array {
        $rules = [
            0 => ['day' => [14, 15], 'night' => []],
            1 => ['day' => [9, 11], 'night' => []],
            2 => ['day' => [1, 5], 'night' => []],
            3 => ['day' => [12], 'night' => []],
            4 => ['day' => [1, 2, 4, 11, 12, 15], 'night' => [1, 2, 6, 7]],
            5 => ['day' => [4, 9], 'night' => []],
            6 => ['day' => [1], 'night' => []],
        ];

        $currentRules = $rules[$varaIdx] ?? $rules[0];
        $full = $this->calculateMuhurtaTable($sunrise, $sunset, $nextSunrise);
        $durMuhurtas = [];

        foreach ($full as $row) {
            $num = $row['muhurta_number'];
            $isNight = $row['period'] === Localization::translate('String', 'Night');
            $targetList = $isNight ? $currentRules['night'] : $currentRules['day'];

            if (in_array($num, $targetList, true)) {
                $row['is_auspicious'] = false;
                $durMuhurtas[] = $row;
            }
        }

        return $durMuhurtas;
    }

    public function calculateNishitaMuhurta(
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise
    ): array {
        $nightDuration = $nextSunrise->getTimestamp() - $sunset->getTimestamp();
        $nightMuhurta = $nightDuration / 15.0;
        $start = $this->addFloatSeconds($sunset, 7 * $nightMuhurta);
        $end = $this->addFloatSeconds($start, $nightMuhurta);
        $midpoint = $this->addFloatSeconds($start, $nightMuhurta / 2.0);

        return [
            'source' => Localization::translate('Source', '15-part night Muhurta model'),
            'nishita_start' => AstroCore::formatTime($start),
            'nishita_end' => AstroCore::formatTime($end),
            'nishita_start_iso' => AstroCore::formatDateTime($start),
            'nishita_end_iso' => AstroCore::formatDateTime($end),
            'nishita_start_jd' => AstroCore::toJulianDay($start),
            'nishita_end_jd' => AstroCore::toJulianDay($end),
            'night_midpoint' => AstroCore::formatTime($midpoint),
            'night_midpoint_iso' => AstroCore::formatDateTime($midpoint),
            'muhurta_duration_minutes' => AstroCore::formatDuration($nightMuhurta / 60.0),
            'muhurta_number' => '8th of 15 (Nishita)',
        ];
    }

    public function calculateVijayaMuhurta(
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset
    ): array {
        $dayDuration = $sunset->getTimestamp() - $sunrise->getTimestamp();
        $muhurtaDuration = $dayDuration / 15.0;
        $start = $this->addFloatSeconds($sunrise, 10 * $muhurtaDuration);
        $end = $this->addFloatSeconds($start, $muhurtaDuration);

        return [
            'source' => Localization::translate('Source', '30 Muhurta day division'),
            'vijaya_start' => AstroCore::formatTime($start),
            'vijaya_end' => AstroCore::formatTime($end),
            'vijaya_start_iso' => AstroCore::formatDateTime($start),
            'vijaya_end_iso' => AstroCore::formatDateTime($end),
            'muhurta_duration_minutes' => AstroCore::formatDuration($muhurtaDuration / 60.0),
            'muhurta_number' => '11th of 15 (Vijaya)',
        ];
    }

    public function calculateGodhuliMuhurta(
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise
    ): array {
        $nightDuration = $nextSunrise->getTimestamp() - $sunset->getTimestamp();
        $duration = $nightDuration / 30.0;
        $start = $sunset;
        $end = $this->addFloatSeconds($start, $duration);

        return [
            'source' => Localization::translate('Source', 'Observed Panchang convention; tradition-dependent'),
            'godhuli_start' => AstroCore::formatTime($start),
            'godhuli_end' => AstroCore::formatTime($end),
            'godhuli_start_iso' => AstroCore::formatDateTime($start),
            'godhuli_end_iso' => AstroCore::formatDateTime($end),
            'duration_minutes' => AstroCore::formatDuration($duration / 60.0),
        ];
    }

    public function calculateSandhya(
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise,
        CarbonImmutable $solarNoon
    ): array {
        $nightDuration = $nextSunrise->getTimestamp() - $sunset->getTimestamp();
        $twilightDuration = $nightDuration / 10.0;
        $madhyahnaHalf = 36.0 * 60.0;

        $pratahStart = $this->addFloatSeconds($sunrise, -$twilightDuration);
        $pratahEnd = $sunrise;
        $sayahnaStart = $sunset;
        $sayahnaEnd = $this->addFloatSeconds($sunset, $twilightDuration);
        $madhyahnaStart = $this->addFloatSeconds($solarNoon, -$madhyahnaHalf);
        $madhyahnaEnd = $this->addFloatSeconds($solarNoon, $madhyahnaHalf);

        return [
            'source' => Localization::translate('Source', 'Sandhyavandanam practice convention'),
            'pratah_sandhya' => [
                'start' => AstroCore::formatTime($pratahStart),
                'end' => AstroCore::formatTime($pratahEnd),
                'start_iso' => AstroCore::formatDateTime($pratahStart),
                'end_iso' => AstroCore::formatDateTime($pratahEnd),
                'duration_seconds' => $pratahEnd->getTimestamp() - $pratahStart->getTimestamp(),
            ],
            'madhyahna_sandhya' => [
                'start' => AstroCore::formatTime($madhyahnaStart),
                'end' => AstroCore::formatTime($madhyahnaEnd),
                'start_iso' => AstroCore::formatDateTime($madhyahnaStart),
                'end_iso' => AstroCore::formatDateTime($madhyahnaEnd),
                'duration_seconds' => $madhyahnaEnd->getTimestamp() - $madhyahnaStart->getTimestamp(),
            ],
            'sayahna_sandhya' => [
                'start' => AstroCore::formatTime($sayahnaStart),
                'end' => AstroCore::formatTime($sayahnaEnd),
                'start_iso' => AstroCore::formatDateTime($sayahnaStart),
                'end_iso' => AstroCore::formatDateTime($sayahnaEnd),
                'duration_seconds' => $sayahnaEnd->getTimestamp() - $sayahnaStart->getTimestamp(),
            ],
        ];
    }

    public function calculateDaylightFivefoldDivision(
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset
    ): array {
        $duration = ($sunset->getTimestamp() - $sunrise->getTimestamp()) / 5.0;
        $names = ['Pratah', 'Sangava', 'Madhyahna', 'Aparahna', 'Sayahna'];

        $rows = [];
        foreach (array_keys($names) as $index) {
            $start = $this->addFloatSeconds($sunrise, $index * $duration);
            $rows[] = $this->buildTimedRow($start, $duration, [
                'name' => Localization::translate('Fivefold', $index),
                'division_number' => $index + 1,
            ]);
        }

        return $rows;
    }

    public function calculatePrahara(
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise
    ): array {
        $dayDuration = ($sunset->getTimestamp() - $sunrise->getTimestamp()) / 4.0;
        $nightDuration = ($nextSunrise->getTimestamp() - $sunset->getTimestamp()) / 4.0;
        $dayNames = ['Pratah Prahara', 'Sangava Prahara', 'Madhyahna Prahara', 'Aparahna Prahara'];
        $nightNames = ['Pradosha Prahara', 'Nishitha Prahara', 'Triyama Prahara', 'Usha Prahara'];

        $praharas = [];
        for ($i = 0; $i < 4; $i++) {
            $start = $this->addFloatSeconds($sunrise, $i * $dayDuration);
            $praharas[] = $this->buildTimedRow($start, $dayDuration, [
                'period' => Localization::translate('String', 'Day'),
                'prahara_number' => $i + 1,
                'name' => Localization::translate('Prahara', $dayNames[$i]),
            ]);
        }

        for ($i = 0; $i < 4; $i++) {
            $start = $this->addFloatSeconds($sunset, $i * $nightDuration);
            $praharas[] = $this->buildTimedRow($start, $nightDuration, [
                'period' => Localization::translate('String', 'Night'),
                'prahara_number' => $i + 5,
                'name' => Localization::translate('Prahara', $nightNames[$i]),
            ]);
        }

        return $praharas;
    }

    private function addFloatSeconds(CarbonImmutable $dt, float $seconds): CarbonImmutable
    {
        $whole = (int) floor($seconds);
        $fraction = $seconds - $whole;

        return $dt->addSeconds($whole)->addMicroseconds((int) ($fraction * 1_000_000));
    }

    private function buildTimedRow(CarbonImmutable $start, float $duration, array $payload): array
    {
        $end = $this->addFloatSeconds($start, $duration);

        return $payload + [
            'start' => AstroCore::formatTime($start),
            'end' => AstroCore::formatTime($end),
            'start_iso' => AstroCore::formatDateTime($start),
            'end_iso' => AstroCore::formatDateTime($end),
            'duration_seconds' => $duration,
        ];
    }
}
