<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Panchanga;

use Carbon\CarbonImmutable;
use DateTimeZone;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Core\Enums\Choghadiya;
use JayeshMepani\PanchangCore\Core\Enums\Hora;
use JayeshMepani\PanchangCore\Core\Enums\Muhurta;
use JayeshMepani\PanchangCore\Core\Enums\Nakshatra;
use JayeshMepani\PanchangCore\Core\Enums\Rasi;
use JayeshMepani\PanchangCore\Core\Enums\Vara;
use JayeshMepani\PanchangCore\Core\Enums\VimshottariDasha;
use JayeshMepani\PanchangCore\Core\Localization;
use SwissEph\FFI\SwissEphFFI;

class MuhurtaService
{
    private const array WEEKDAY_PLANET_ORDER = ['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn'];

    public function calculateHora(
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise,
        CarbonImmutable $current,
        int $varaIdx
    ): array {
        $seq = Hora::getSequence(Vara::from($varaIdx));
        $isDay = ($current >= $sunrise) && ($current < $sunset);

        if ($isDay) {
            $durationTotal = $sunset->getTimestamp() - $sunrise->getTimestamp();
            $elapsed = $current->getTimestamp() - $sunrise->getTimestamp();
            $horaDuration = $durationTotal / 12.0;
            $horaIdx = (int) floor($elapsed / $horaDuration);
            $baseOffset = 0;
        } else {
            $durationTotal = $nextSunrise->getTimestamp() - $sunset->getTimestamp();
            $elapsed = $current->getTimestamp() - $sunset->getTimestamp();
            $horaDuration = $durationTotal / 12.0;
            $horaIdx = (int) floor($elapsed / $horaDuration);
            $baseOffset = 12;
        }

        if ($horaIdx >= 12) { $horaIdx = 11; }
        $currentHora = $seq[($baseOffset + $horaIdx) % 24];

        return [
            'hora_number' => $baseOffset + $horaIdx + 1,
            'is_day_hora' => $isDay,
            'ruler' => $currentHora->getName(),
            'hora_duration_seconds' => $horaDuration,
            'hora_duration_minutes' => AstroCore::formatDuration($horaDuration / 60.0),
        ];
    }

    public function calculateChogadiya(
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise,
        CarbonImmutable $current,
        int $varaIdx
    ): array {
        $vara = Vara::from($varaIdx);
        $isDay = ($current >= $sunrise) && ($current < $sunset);

        if ($isDay) {
            $durationTotal = $sunset->getTimestamp() - $sunrise->getTimestamp();
            $elapsed = $current->getTimestamp() - $sunrise->getTimestamp();
        } else {
            $durationTotal = ($nextSunrise->getTimestamp() - $sunset->getTimestamp());
            $elapsed = $current->getTimestamp() - $sunset->getTimestamp();
        }

        $divDuration = $durationTotal / 8.0;
        $divIdx = (int) floor($elapsed / $divDuration);
        if ($divIdx >= 8) { $divIdx = 7; }

        $pattern = $isDay ? Choghadiya::getDaySequence($vara) : Choghadiya::getNightSequence($vara);
        $choghadiya = $pattern[$divIdx];

        return [
            'mode' => $isDay ? Localization::translate('String', 'Day') : Localization::translate('String', 'Night'),
            'division' => $divIdx + 1,
            'name' => $choghadiya->getName(),
            'is_auspicious' => $choghadiya->isAuspicious(),
            'division_duration_minutes' => AstroCore::formatDuration($divDuration / 60.0),
        ];
    }

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

    public function calculateAbhijitMuhurta(CarbonImmutable $sunrise, CarbonImmutable $sunset): array
    {
        $daySeconds = $sunset->getTimestamp() - $sunrise->getTimestamp();
        $muhurtaDuration = $daySeconds / 15.0;

        $abhijitStart = $this->addFloatSeconds($sunrise, 7 * $muhurtaDuration);
        $abhijitEnd = $this->addFloatSeconds($abhijitStart, $muhurtaDuration);
        $solarNoon = $this->addFloatSeconds($sunrise, $daySeconds / 2.0);

        return [
            'source' => Localization::translate('Source', 'Muhūrta Chintāmaṇi / Nārada Saṁhitā'),
            'abhijit_start' => AstroCore::formatTime($abhijitStart),
            'abhijit_end' => AstroCore::formatTime($abhijitEnd),
            'solar_noon' => AstroCore::formatTime($solarNoon),
            'muhurta_duration_minutes' => AstroCore::formatDuration($muhurtaDuration / 60.0),
            'muhurta_number' => '8th of 15 (Abhijit)',
        ];
    }

    public function calculateHoraTable(
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise,
        int $varaIdx
    ): array {
        $seq = Hora::getSequence(Vara::from($varaIdx));
        $dayDuration = ($sunset->getTimestamp() - $sunrise->getTimestamp()) / 12.0;
        $nightDuration = ($nextSunrise->getTimestamp() - $sunset->getTimestamp()) / 12.0;

        $rows = [];
        for ($i = 0; $i < 24; $i++) {
            $isDayHora = $i < 12;
            $duration = $isDayHora ? $dayDuration : $nightDuration;
            if ($isDayHora) {
                $start = $this->addFloatSeconds($sunrise, $i * $duration);
            } else {
                $start = $this->addFloatSeconds($sunset, ($i - 12) * $duration);
            }
            $rows[] = $this->buildTimedRow($start, $duration, [
                'hora_number' => $i + 1,
                'is_day_hora' => $isDayHora,
                'ruler' => $seq[$i]->getName(),
            ]);
        }

        return $rows;
    }

    public function calculateChogadiyaTable(
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise,
        int $varaIdx
    ): array {
        $vara = Vara::from($varaIdx);
        $rows = [];
        $dayDuration = ($sunset->getTimestamp() - $sunrise->getTimestamp()) / 8.0;
        $nightDuration = ($nextSunrise->getTimestamp() - $sunset->getTimestamp()) / 8.0;
        $dayPattern = Choghadiya::getDaySequence($vara);
        $nightPattern = Choghadiya::getNightSequence($vara);

        for ($i = 0; $i < 8; $i++) {
            $start = $this->addFloatSeconds($sunrise, $i * $dayDuration);
            $choghadiya = $dayPattern[$i];

            $rows[] = $this->buildTimedRow($start, $dayDuration, [
                'mode' => Localization::translate('String', 'Day'),
                'division' => $i + 1,
                'name' => $choghadiya->getName(),
                'is_auspicious' => $choghadiya->isAuspicious(),
            ]);
        }

        for ($i = 0; $i < 8; $i++) {
            $start = $this->addFloatSeconds($sunset, $i * $nightDuration);
            $choghadiya = $nightPattern[$i];

            $rows[] = $this->buildTimedRow($start, $nightDuration, [
                'mode' => Localization::translate('String', 'Night'),
                'division' => $i + 1,
                'name' => $choghadiya->getName(),
                'is_auspicious' => $choghadiya->isAuspicious(),
            ]);
        }

        return $rows;
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

    public function calculateGowriPanchangam(
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise,
        int $varaIdx
    ): array {
        $dayDuration = ($sunset->getTimestamp() - $sunrise->getTimestamp()) / 8.0;
        $nightDuration = ($nextSunrise->getTimestamp() - $sunset->getTimestamp()) / 8.0;

        $dayRows = [];
        $labels = [
            0 => ['Uthi', 'Amirdha', 'Rogam', 'Laabam', 'Dhanam', 'Sugam', 'Soram', 'Visham'],
            1 => ['Amirdha', 'Visham', 'Rogam', 'Laabam', 'Dhanam', 'Sugam', 'Soram', 'Uthi'],
            2 => ['Rogam', 'Laabam', 'Dhanam', 'Sugam', 'Soram', 'Uthi', 'Visham', 'Amirdha'],
            3 => ['Laabam', 'Dhanam', 'Sugam', 'Soram', 'Visham', 'Uthi', 'Amirdha', 'Rogam'],
            4 => ['Dhanam', 'Sugam', 'Soram', 'Uthi', 'Amirdha', 'Visham', 'Rogam', 'Laabam'],
            5 => ['Sugam', 'Soram', 'Uthi', 'Visham', 'Amirdha', 'Rogam', 'Laabam', 'Dhanam'],
            6 => ['Soram', 'Uthi', 'Visham', 'Amirdha', 'Rogam', 'Laabam', 'Dhanam', 'Sugam'],
        ][$varaIdx] ?? ['Uthi', 'Amirdha', 'Rogam', 'Laabam', 'Dhanam', 'Sugam', 'Soram', 'Visham'];

        $gowriLabelsEn = [
            'Amirdha' => ['quality' => 'best', 'is_auspicious' => true],
            'Dhanam' => ['quality' => 'wealth', 'is_auspicious' => true],
            'Uthi' => ['quality' => 'good', 'is_auspicious' => true],
            'Laabam' => ['quality' => 'gain', 'is_auspicious' => true],
            'Sugam' => ['quality' => 'good', 'is_auspicious' => true],
            'Rogam' => ['quality' => 'evil', 'is_auspicious' => false],
            'Soram' => ['quality' => 'bad', 'is_auspicious' => false],
            'Visham' => ['quality' => 'bad', 'is_auspicious' => false],
        ];

        foreach ($labels as $idx => $lbl) {
            $start = $this->addFloatSeconds($sunrise, $idx * $dayDuration);
            $dayRows[] = $this->buildTimedRow($start, $dayDuration, [
                'division' => $idx + 1,
                'label' => Localization::translate('Gowri', $lbl),
                'quality' => Localization::translate('GowriQuality', $gowriLabelsEn[$lbl]['quality']),
                'is_auspicious' => $gowriLabelsEn[$lbl]['is_auspicious'],
                'is_day' => true,
            ]);
        }

        $nightRows = [];
        $labels = [
            0 => ['Dhanam', 'Sugam', 'Soram', 'Visham', 'Uthi', 'Amirdha', 'Rogam', 'Laabam'],
            1 => ['Sugam', 'Soram', 'Uthi', 'Amirdha', 'Visham', 'Rogam', 'Laabam', 'Dhanam'],
            2 => ['Soram', 'Uthi', 'Visham', 'Amirdha', 'Rogam', 'Laabam', 'Dhanam', 'Sugam'],
            3 => ['Uthi', 'Amirdha', 'Rogam', 'Laabam', 'Dhanam', 'Sugam', 'Soram', 'Visham'],
            4 => ['Amirdha', 'Visham', 'Rogam', 'Laabam', 'Dhanam', 'Sugam', 'Soram', 'Uthi'],
            5 => ['Rogam', 'Laabam', 'Dhanam', 'Sugam', 'Soram', 'Uthi', 'Visham', 'Amirdha'],
            6 => ['Laabam', 'Dhanam', 'Sugam', 'Soram', 'Uthi', 'Visham', 'Amirdha', 'Soram'],
        ][$varaIdx] ?? ['Dhanam', 'Sugam', 'Soram', 'Visham', 'Uthi', 'Amirdha', 'Rogam', 'Laabam'];

        foreach ($labels as $idx => $lbl) {
            $start = $this->addFloatSeconds($sunset, $idx * $nightDuration);
            $nightRows[] = $this->buildTimedRow($start, $nightDuration, [
                'division' => $idx + 1,
                'label' => Localization::translate('Gowri', $lbl),
                'quality' => Localization::translate('GowriQuality', $gowriLabelsEn[$lbl]['quality']),
                'is_auspicious' => $gowriLabelsEn[$lbl]['is_auspicious'],
                'is_day' => false,
            ]);
        }

        return [
            'source' => Localization::translate('Source', 'Published Gowri/Pambu table convention'),
            'day' => $dayRows,
            'night' => $nightRows,
            'auspicious_labels' => array_map(fn ($l) => Localization::translate('Gowri', $l), ['Amirdha', 'Dhanam', 'Uthi', 'Laabam', 'Sugam']),
            'inauspicious_labels' => array_map(fn ($l) => Localization::translate('Gowri', $l), ['Rogam', 'Soram', 'Visham']),
        ];
    }

    public function calculateKalaVela(
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise,
        int $varaIdx
    ): array {
        $dayDuration = ($sunset->getTimestamp() - $sunrise->getTimestamp()) / 8.0;
        $nightDuration = ($nextSunrise->getTimestamp() - $sunset->getTimestamp()) / 8.0;

        $dayPortions = [];
        for ($i = 0; $i < 8; $i++) {
            $start = $this->addFloatSeconds($sunrise, $i * $dayDuration);
            $planet = $i < 7 ? self::WEEKDAY_PLANET_ORDER[($varaIdx + $i) % 7] : null;
            $dayPortions[] = $this->buildTimedRow($start, $dayDuration, [
                'division' => $i + 1,
                'planetary_lord' => $planet ? VimshottariDasha::from($this->getPlanetIndex($planet))->getName() : null,
                'is_optional_eighth_portion' => $i === 7,
                'planetary_lord_en' => $planet,
            ]);
        }

        $nightPortions = [];
        for ($i = 0; $i < 8; $i++) {
            $start = $this->addFloatSeconds($sunset, $i * $nightDuration);
            $planet = $i < 7 ? self::WEEKDAY_PLANET_ORDER[($varaIdx + 4 + $i) % 7] : null;
            $nightPortions[] = $this->buildTimedRow($start, $nightDuration, [
                'division' => $i + 1,
                'planetary_lord' => $planet ? VimshottariDasha::from($this->getPlanetIndex($planet))->getName() : null,
                'is_optional_eighth_portion' => $i === 7,
                'planetary_lord_en' => $planet,
            ]);
        }

        return [
            'named_kala_velas' => [
                'kala' => $this->extractKalaVelaWindows('Sun', $dayPortions, $nightPortions),
                'mrityu' => $this->extractKalaVelaWindows('Mars', $dayPortions, $nightPortions),
                'ardhaprahara' => $this->extractKalaVelaWindows('Mercury', $dayPortions, $nightPortions),
                'yamaghantaka' => $this->extractKalaVelaWindows('Jupiter', $dayPortions, $nightPortions),
                'gulika' => $this->extractKalaVelaWindows('Saturn', $dayPortions, $nightPortions),
            ],
            'day' => $dayPortions,
            'night' => $nightPortions,
        ];
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

    public function calculateBrahmaMuhurta(CarbonImmutable $sunrise): array
    {
        // Brahma Muhurta is traditionally the 29th muhurta of the 24-hour cycle (2nd to last).
        // For a fixed 48-minute model:
        // Start = Sunrise - 96 minutes (2 muhurtas)
        // End = Sunrise - 48 minutes (1 muhurta)
        $muhurtaSeconds = 48.0 * 60.0;
        $start = $sunrise->subSeconds((int) ($muhurtaSeconds * 2));
        $end = $sunrise->subSeconds((int) $muhurtaSeconds);

        return [
            'source' => Localization::translate('Source', 'Ashtanga Hridaya Sutrasthana 2:1, Charaka Samhita, Manu Smriti 4.92'),
            'brahma_muhurta_start' => AstroCore::formatTime($start),
            'brahma_muhurta_end' => AstroCore::formatTime($end),
            'duration_minutes' => 48,
            'duration_seconds' => 2880,
            'significance' => Localization::translate('MuhurtaDesc', 'Brahma Muhurta significance'),
            'brahma_muhurta_start_iso' => AstroCore::formatDateTime($start),
            'brahma_muhurta_end_iso' => AstroCore::formatDateTime($end),
        ];
    }

    /**
     * Identify specific inauspicious Muhurtas from the daily 30-period table.
     *
     * @param CarbonImmutable $sunrise
     * @param CarbonImmutable $sunset
     * @param CarbonImmutable $nextSunrise
     * @param int $varaIdx Weekday index (0-6)
     * @return array List of Dur-Muhurta periods
     */
    public function calculateDurMuhurta(
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise,
        int $varaIdx
    ): array {
        // Dur-Muhurta rules per weekday (1-based indices from classical texts)
        $rules = [
            0 => ['day' => [14, 15], 'night' => []], // Sunday
            1 => ['day' => [9, 11], 'night' => []], // Monday
            2 => ['day' => [1, 5], 'night' => []],  // Tuesday
            3 => ['day' => [12], 'night' => []],    // Wednesday
            4 => ['day' => [1, 2, 4, 11, 12, 15], 'night' => [1, 2, 6, 7]], // Thursday
            5 => ['day' => [9, 11], 'night' => []], // Friday
            6 => ['day' => [1], 'night' => []],     // Saturday
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

    public function calculateVarjyam(
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise,
        int $nakshatraIndex,
        float $nakshatraStartJd,
        float $nakshatraEndJd
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
        $vEnd = CarbonImmutable::createFromFormat('H:i:s', $vEndStr)->setDate($sunrise->year, $sunrise->month, $sunrise->day);
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

    public function calculateLagna(
        CarbonImmutable $current,
        CarbonImmutable $sunrise,
        float $sunriseSunLongitude,
        float $ayanamsaDeg,
        float $lat,
        float $lon,
        SwissEphFFI $sweph
    ): array {
        $jd = $this->carbonToJulianDayUtc($sweph, $current);
        $cusp = $sweph->getFFI()->new('double[13]');
        $ascmc = $sweph->getFFI()->new('double[10]');
        $sweph->swe_houses($jd, $lat, $lon, ord('P'), $cusp, $ascmc);

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
        float $sunriseSunLongitude,
        float $ayanamsaDeg,
        float $lat,
        float $lon,
        SwissEphFFI $sweph
    ): array {
        $jdStart = $this->carbonToJulianDayUtc($sweph, $sunrise);
        $jdSunset = $this->carbonToJulianDayUtc($sweph, $sunset);
        $jdEnd = $jdStart + 1.0; // One solar day (24 hours from sunrise)

        $lagnas = [];
        $step = 120.0 / 86400.0;
        $prevSign = -1;
        $signsCollected = 0;

        // Sampling phase - collect exactly 12 lagna sign transitions
        for ($jd = $jdStart; $jd <= $jdEnd + $step && $signsCollected < 12; $jd += $step) {
            $asc = $this->getAscendantSiderealAtJd($sweph, $jd, $lat, $lon, $ayanamsaDeg);
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
                        $midAsc = $this->getAscendantSiderealAtJd($sweph, $mid, $lat, $lon, $ayanamsaDeg);
                        $diff = AstroCore::normalize($midAsc - $targetAngle);
                        if ($diff < 180.0) { $high = $mid; } else { $low = $mid; }
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

    private function extractKalaVelaWindows(string $planet, array $dayPortions, array $nightPortions): array
    {
        $matches = [];
        foreach (array_merge($dayPortions, $nightPortions) as $portion) {
            if (($portion['planetary_lord_en'] ?? null) !== $planet) { continue; }
            $matches[] = [
                'division' => $portion['division'],
                'start' => $portion['start'],
                'end' => $portion['end'],
                'start_iso' => $portion['start_iso'],
                'end_iso' => $portion['end_iso'],
            ];
        }
        return $matches;
    }

    private function getPlanetIndex(string $name): int
    {
        return match ($name) {
            'Sun' => 0, 'Moon' => 1, 'Mars' => 2, 'Rahu' => 3, 'Jupiter' => 4, 'Saturn' => 5, 'Mercury' => 6, 'Ketu' => 7, 'Venus' => 8,
            default => 0
        };
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

    private function jdToCarbon(float $jd, DateTimeZone $tz): CarbonImmutable
    {
        $unixTimestamp = ($jd - 2440587.5) * 86400.0;
        $seconds = (int) floor($unixTimestamp);
        $microseconds = (int) (($unixTimestamp - $seconds) * 1_000_000);
        return CarbonImmutable::createFromTimestamp($seconds, $tz)->addMicroseconds($microseconds);
    }

    private function carbonToJulianDayUtc(SwissEphFFI $sweph, CarbonImmutable $dt): float
    {
        $u = $dt->setTimezone('UTC');
        return $sweph->swe_julday($u->year, $u->month, $u->day, $u->hour + $u->minute / 60.0 + $u->second / 3600.0, SwissEphFFI::SE_GREG_CAL);
    }

    private function getAscendantSiderealAtJd(SwissEphFFI $sweph, float $jd, float $lat, float $lon, float $ayanamsa): float
    {
        $cusp = $sweph->getFFI()->new('double[13]');
        $ascmc = $sweph->getFFI()->new('double[10]');
        $sweph->swe_houses($jd, $lat, $lon, ord('P'), $cusp, $ascmc);
        return AstroCore::normalize($ascmc[0] - $ayanamsa);
    }
}
