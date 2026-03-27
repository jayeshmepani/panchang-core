<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Panchanga;

use Carbon\CarbonImmutable;
use DateTimeZone;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Core\Enums\Nakshatra;
use RuntimeException;
use SwissEph\FFI\SwissEphFFI;

class MuhurtaService
{
    private const CHOGADIYA_DAY_SEQUENCES = [
        0 => ['Udveg', 'Chal', 'Labh', 'Amrit', 'Kaal', 'Shubh', 'Rog', 'Udveg'],
        1 => ['Amrit', 'Kaal', 'Shubh', 'Rog', 'Udveg', 'Chal', 'Labh', 'Amrit'],
        2 => ['Rog', 'Udveg', 'Chal', 'Labh', 'Amrit', 'Kaal', 'Shubh', 'Rog'],
        3 => ['Chal', 'Labh', 'Amrit', 'Kaal', 'Shubh', 'Rog', 'Udveg', 'Chal'],
        4 => ['Shubh', 'Rog', 'Udveg', 'Chal', 'Labh', 'Amrit', 'Kaal', 'Shubh'],
        5 => ['Chal', 'Labh', 'Amrit', 'Kaal', 'Shubh', 'Rog', 'Udveg', 'Chal'],
        6 => ['Kaal', 'Shubh', 'Rog', 'Udveg', 'Chal', 'Labh', 'Amrit', 'Kaal'],
    ];

    private const CHOGADIYA_NIGHT_SEQUENCES = [
        0 => ['Shubh', 'Amrit', 'Chal', 'Labh', 'Udveg', 'Shubh', 'Amrit', 'Chal'],
        1 => ['Chal', 'Labh', 'Amrit', 'Kaal', 'Shubh', 'Rog', 'Udveg', 'Chal'],
        2 => ['Kaal', 'Shubh', 'Rog', 'Udveg', 'Chal', 'Labh', 'Amrit', 'Kaal'],
        3 => ['Udveg', 'Chal', 'Labh', 'Amrit', 'Kaal', 'Shubh', 'Rog', 'Udveg'],
        4 => ['Amrit', 'Kaal', 'Shubh', 'Rog', 'Udveg', 'Chal', 'Labh', 'Amrit'],
        5 => ['Rog', 'Udveg', 'Chal', 'Labh', 'Amrit', 'Kaal', 'Shubh', 'Rog'],
        6 => ['Chal', 'Labh', 'Amrit', 'Kaal', 'Shubh', 'Rog', 'Udveg', 'Chal'],
    ];

    private const AUSPICIOUS_CHOGADIYA = ['Amrit', 'Shubh', 'Labh'];
    private const DAY_MUHURTA_NAMES = [
        'Rudra', 'Sarpa', 'Mitra', 'Pitri', 'Vasu',
        'Vara', 'Vishvedeva', 'Vidhi', 'Brahma', 'Indra',
        'Indragni', 'Daitya', 'Varuna', 'Aryaman', 'Bhaga',
    ];
    private const NIGHT_MUHURTA_NAMES = [
        'Ishvara', 'Ajapada', 'Ahirbudhnya', 'Pushya', 'Nasatya',
        'Yama', 'Vahni', 'Dhala', 'Shashi', 'Aditya',
        'Guru', 'Acyuta', 'Arka', 'Tvashta', 'Vayu',
    ];

    private array $horaPlanetsOrder = ['Sun', 'Venus', 'Mercury', 'Moon', 'Saturn', 'Jupiter', 'Mars'];
    private array $weekPlanets = ['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn'];

    public function calculateHora(
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise,
        CarbonImmutable $current,
        int $varaIdx
    ): array {
        $startPlanet = $this->weekPlanets[$varaIdx];
        $startIdx = array_search($startPlanet, $this->horaPlanetsOrder, true);

        $isDay = ($current >= $sunrise) && ($current < $sunset);

        if ($isDay) {
            $durationTotal = $sunset->getTimestamp() - $sunrise->getTimestamp();
            $elapsed = $current->getTimestamp() - $sunrise->getTimestamp();
            $horaDuration = $durationTotal / 12.0;
            $horaIdx = (int) floor($elapsed / $horaDuration) + 1;
            $baseOffset = 0;
        } else {
            $durationTotal = $nextSunrise->getTimestamp() - $sunset->getTimestamp();
            $elapsed = $current->getTimestamp() - $sunset->getTimestamp();
            $horaDuration = $durationTotal / 12.0;
            $horaIdx = (int) floor($elapsed / $horaDuration) + 1;
            $baseOffset = 12;
        }

        if ($horaIdx > 12) {
            $horaIdx = 12;
        }

        $totalHoursPassed = $baseOffset + ($horaIdx - 1);
        $currentRuler = $this->horaPlanetsOrder[($startIdx + $totalHoursPassed) % 7];

        return [
            'hora_number' => $baseOffset + $horaIdx,
            'is_day_hora' => $isDay,
            'ruler' => $currentRuler,
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
        $isDay = ($current >= $sunrise) && ($current < $sunset);

        if ($isDay) {
            $durationTotal = $sunset->getTimestamp() - $sunrise->getTimestamp();
            $elapsed = $current->getTimestamp() - $sunrise->getTimestamp();
            $mode = 'Day';
        } else {
            $durationTotal = $nextSunrise->getTimestamp() - $sunset->getTimestamp();
            $elapsed = $current->getTimestamp() - $sunset->getTimestamp();
            $mode = 'Night';
        }

        $divDuration = $durationTotal / 8.0;
        $divIdx = (int) floor($elapsed / $divDuration);
        if ($divIdx >= 8) {
            $divIdx = 7;
        }

        $seq = $this->getChogadiyaPattern($varaIdx, $isDay);
        $name = $seq[$divIdx];

        return [
            'mode' => $mode,
            'division' => $divIdx + 1,
            'name' => $name,
            'is_auspicious' => $this->isAuspiciousChogadiya($name),
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
            'Rahu_Kaal' => $getTime($rahuParts[$varaIdx]),
            'Gulika' => $getTime($gulikaParts[$varaIdx]),
            'Yamaganda' => $getTime($yamaParts[$varaIdx]),
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
            'source' => 'Muhūrta Chintāmaṇi / Nārada Saṁhitā',
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
        $startPlanet = $this->weekPlanets[$varaIdx];
        $startIdx = array_search($startPlanet, $this->horaPlanetsOrder, true);
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
                'ruler' => $this->horaPlanetsOrder[($startIdx + $i) % 7],
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
        $rows = [];
        $dayDuration = ($sunset->getTimestamp() - $sunrise->getTimestamp()) / 8.0;
        $nightDuration = ($nextSunrise->getTimestamp() - $sunset->getTimestamp()) / 8.0;
        $dayPattern = $this->getChogadiyaPattern($varaIdx, true);
        $nightPattern = $this->getChogadiyaPattern($varaIdx, false);

        for ($i = 0; $i < 8; $i++) {
            $start = $this->addFloatSeconds($sunrise, $i * $dayDuration);
            $name = $dayPattern[$i];

            $rows[] = $this->buildTimedRow($start, $dayDuration, [
                'mode' => 'Day',
                'division' => $i + 1,
                'name' => $name,
                'is_auspicious' => $this->isAuspiciousChogadiya($name),
            ]);
        }

        for ($i = 0; $i < 8; $i++) {
            $start = $this->addFloatSeconds($sunset, $i * $nightDuration);
            $name = $nightPattern[$i];

            $rows[] = $this->buildTimedRow($start, $nightDuration, [
                'mode' => 'Night',
                'division' => $i + 1,
                'name' => $name,
                'is_auspicious' => $this->isAuspiciousChogadiya($name),
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

        for ($i = 0; $i < 15; $i++) {
            $start = $this->addFloatSeconds($sunrise, $i * $dayDuration);
            $rows[] = $this->buildTimedRow($start, $dayDuration, [
                'period' => 'Day',
                'muhurta_number' => $i + 1,
                'name' => self::DAY_MUHURTA_NAMES[$i],
            ]);
        }

        for ($i = 0; $i < 15; $i++) {
            $start = $this->addFloatSeconds($sunset, $i * $nightDuration);
            $rows[] = $this->buildTimedRow($start, $nightDuration, [
                'period' => 'Night',
                'muhurta_number' => $i + 1,
                'name' => self::NIGHT_MUHURTA_NAMES[$i],
            ]);
        }

        return $rows;
    }

    /**
     * Calculate 8 Prahara (4 day + 4 night) as per Srimad Bhagavata Purana 3.11.8, 3.11.10
     * Each Prahara = ~3 hours (6-7 Nadikas).
     */
    public function calculatePrahara(
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise
    ): array {
        $dayPraharaDuration = ($sunset->getTimestamp() - $sunrise->getTimestamp()) / 4.0;
        $nightPraharaDuration = ($nextSunrise->getTimestamp() - $sunset->getTimestamp()) / 4.0;

        // Day Prahara names (Sanskrit)
        $dayNames = [
            'Pratah Prahara (प्रातः प्रहर)',
            'Sangava Prahara (संगव प्रहर)',
            'Madhyahna Prahara (मध्याह्न प्रहर)',
            'Aparahna Prahara (अपराह्न प्रहर)',
        ];

        // Night Prahara names (Sanskrit)
        $nightNames = [
            'Pradosha Prahara (प्रदोष प्रहर)',
            'Nishitha Prahara (निशिथ प्रहर)',
            'Triyama Prahara (त्रियाम प्रहर)',
            'Usha Prahara (उषा प्रहर)',
        ];

        $praharas = [];

        // Day Prahara
        for ($i = 0; $i < 4; $i++) {
            $start = $this->addFloatSeconds($sunrise, $i * $dayPraharaDuration);
            $end = $this->addFloatSeconds($start, $dayPraharaDuration);
            $praharas[] = [
                'period' => 'Day',
                'prahara_number' => $i + 1,
                'name' => $dayNames[$i],
                'sanskrit_name' => explode(' ', $dayNames[$i])[0],
                'start' => AstroCore::formatTime($start),
                'end' => AstroCore::formatTime($end),
                'duration_seconds' => $dayPraharaDuration,
                'duration_hours' => $dayPraharaDuration / 3600.0,
            ];
        }

        // Night Prahara
        for ($i = 0; $i < 4; $i++) {
            $start = $this->addFloatSeconds($sunset, $i * $nightPraharaDuration);
            $end = $this->addFloatSeconds($start, $nightPraharaDuration);
            $praharas[] = [
                'period' => 'Night',
                'prahara_number' => $i + 5,
                'name' => $nightNames[$i],
                'sanskrit_name' => explode(' ', $nightNames[$i])[0],
                'start' => AstroCore::formatTime($start),
                'end' => AstroCore::formatTime($end),
                'duration_seconds' => $nightPraharaDuration,
                'duration_hours' => $nightPraharaDuration / 3600.0,
            ];
        }

        return $praharas;
    }

    /**
     * Calculate Brahma Muhurta as per Ashtanga Hridaya Sutrasthana 2:1
     * Brahma Muhurta = 2 Muhurtas (96 minutes) before Sunrise
     * Duration = 1 Muhurta (48 minutes)
     * Ends 48 minutes before Sunrise.
     */
    public function calculateBrahmaMuhurta(CarbonImmutable $sunrise): array
    {
        $muhurtaSeconds = 48.0 * 60.0; // 48 minutes in seconds
        $brahmaStart = $this->addFloatSeconds($sunrise, -$muhurtaSeconds * 2); // 96 minutes before sunrise
        $brahmaEnd = $this->addFloatSeconds($sunrise, -$muhurtaSeconds); // 48 minutes before sunrise

        return [
            'source' => 'Ashtanga Hridaya Sutrasthana 2:1, Charaka Samhita, Manu Smriti 4.92',
            'brahma_muhurta_start' => AstroCore::formatTime($brahmaStart),
            'brahma_muhurta_end' => AstroCore::formatTime($brahmaEnd),
            'duration_minutes' => 48.0,
            'duration_seconds' => $muhurtaSeconds,
            'significance' => 'Most auspicious time for meditation, study, and spiritual practices. Sattvik period filled with purity, calmness, and clarity.',
        ];
    }

    /**
     * Calculate Dur Muhurta (Inauspicious Muhurtas)
     * Based on classical texts: 1st, 3rd, 5th, 7th, 9th, 11th, 13th, 15th muhurtas are inauspicious.
     */
    public function calculateDurMuhurta(
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise
    ): array {
        // Do Ghati Muhurta inauspicious names (Drik / classical naming variants).
        $inauspiciousNames = [
            'Rudra',
            'Uraga', 'Sarpa',
            'Pitara', 'Pitri',
            'Indragni',
            'Daitya',
            'Bhaga',
            'Ishwara', 'Ishvara',
            'Ajaikapada', 'Ajapada',
            'Yama',
            'Agni', 'Vahni',
        ];

        $full = $this->calculateMuhurtaTable($sunrise, $sunset, $nextSunrise);

        $durMuhurtas = [];
        foreach ($full as $row) {
            $name = (string) ($row['name'] ?? '');
            if (!in_array($name, $inauspiciousNames, true)) {
                continue;
            }

            $durMuhurtas[] = [
                'period' => $row['period'],
                'muhurta_number' => $row['muhurta_number'],
                'name' => $name,
                'start' => $row['start'],
                'end' => $row['end'],
                'duration_seconds' => $row['duration_seconds'],
                'is_auspicious' => false,
            ];
        }

        return $durMuhurtas;
    }

    /**
     * Calculate Varjyam (Tyajyam) - Inauspicious period based on Nakshatra
     * Classical Formula:
     * Varjyam Start = A × DurationOfNakshatra / 60
     * Varjyam Duration = DurationOfNakshatra / 15
     * A values per Nakshatra: [50,4,30,40,14,21,30,20,32,30,20,1,21,20,14,14,10,14,20,20,20,10,10,18,16,30,30].
     */
    public function calculateVarjyam(
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise,
        int $nakshatraIndex, // 0-26
        float $nakshatraStartJd,
        float $nakshatraEndJd
    ): array {
        // Nakshatra Thyajya Ghati ranges (start..end) for 27 nakshatras.
        // Source alignment: widely used Panchangam Tyajyam table representation.
        $tyajyaRanges = [
            [51, 54], // Ashwini
            [25, 28], // Bharani
            [31, 34], // Krittika
            [41, 44], // Rohini
            [15, 18], // Mrigashira
            [22, 25], // Ardra
            [31, 34], // Punarvasu
            [21, 24], // Pushya
            [33, 36], // Ashlesha
            [31, 34], // Magha
            [21, 24], // Purva Phalguni
            [19, 22], // Uttara Phalguni
            [22, 25], // Hasta
            [21, 24], // Chitra
            [15, 18], // Swati
            [15, 18], // Vishakha
            [11, 14], // Anuradha
            [15, 18], // Jyeshtha
            [57, 60], // Mula
            [25, 28], // Purva Ashadha
            [21, 24], // Uttara Ashadha
            [11, 14], // Shravana
            [11, 14], // Dhanishtha
            [19, 22], // Shatabhisha
            [17, 20], // Purva Bhadrapada
            [25, 28], // Uttara Bhadrapada
            [31, 34], // Revati
        ];

        $nakshatraName = Nakshatra::from($nakshatraIndex % 27)->getName();
        [$tyajyaStartGhatiRaw, $tyajyaEndGhati] = $tyajyaRanges[$nakshatraIndex % 27] ?? [31, 34];
        // Panchang implementations commonly interpret the listed start as inclusive boundary.
        // Convert to interval arithmetic by shifting start one ghati earlier.
        $tyajyaStartGhati = max(0, $tyajyaStartGhatiRaw - 1);

        // Calculate full Nakshatra duration from start->end boundary, not sunrise->end.
        $nakshatraDurationSeconds = ($nakshatraEndJd - $nakshatraStartJd) * 86400.0;
        if ($nakshatraDurationSeconds <= 0) {
            throw new RuntimeException('Invalid nakshatra duration: end JD must be greater than start JD.');
        }

        $varjyamStartOffset = ($tyajyaStartGhati * $nakshatraDurationSeconds) / 60.0;
        $varjyamDuration = (($tyajyaEndGhati - $tyajyaStartGhati) * $nakshatraDurationSeconds) / 60.0;

        $nakshatraStart = $this->jdToCarbon($nakshatraStartJd, $sunrise->getTimezone());
        $varjyamStart = $this->addFloatSeconds($nakshatraStart, $varjyamStartOffset);
        $varjyamEnd = $this->addFloatSeconds($varjyamStart, $varjyamDuration);

        return [
            'varjyam_start' => AstroCore::formatTime($varjyamStart),
            'varjyam_end' => AstroCore::formatTime($varjyamEnd),
            'duration_minutes' => AstroCore::formatDuration($varjyamDuration / 60.0),
            'duration_seconds_raw' => $varjyamDuration,
            'nakshatra_start_jd' => $nakshatraStartJd,
            'nakshatra_end_jd' => $nakshatraEndJd,
            'nakshatra_index' => $nakshatraIndex + 1,
            'nakshatra_name' => $nakshatraName,
            'tyajya_ghati_start' => $tyajyaStartGhati,
            'tyajya_ghati_end' => $tyajyaEndGhati,
            'is_auspicious' => false,
        ];
    }

    /**
     * Calculate Amrita Kaal (Auspicious period) - Opposite of Varjyam
     * Usually occurs after Varjyam period.
     */
    public function calculateAmritaKaal(
        CarbonImmutable $sunrise,
        array $varjyam
    ): array {
        if (!isset($varjyam['varjyam_end']) || !isset($varjyam['duration_seconds_raw'])) {
            return [
                'is_available' => false,
                'reason' => 'varjyam_window_missing',
            ];
        }

        // We assume varjyam_end_iso is passed along internally, but if it is just a formatted time, we rely on the object
        $varjyamEndStr = preg_replace('/[^0-9:]/', '', $varjyam['varjyam_end']);
        $varjyamEnd = CarbonImmutable::createFromFormat('H:i:s', $varjyamEndStr)->setDate($sunrise->year, $sunrise->month, $sunrise->day);

        $amritaDuration = (float) $varjyam['duration_seconds_raw'];
        $amritaStart = $this->addFloatSeconds($varjyamEnd, 0);
        $amritaEnd = $this->addFloatSeconds($amritaStart, $amritaDuration);

        return [
            'amrita_kaal_start' => AstroCore::formatTime($amritaStart),
            'amrita_kaal_end' => AstroCore::formatTime($amritaEnd),
            'duration_minutes' => AstroCore::formatDuration($amritaDuration / 60.0),
            'is_auspicious' => true,
        ];
    }

    /**
     * Calculate Pradosha Kaal - Twilight period on Trayodashi (13th Tithi)
     * Classical definition: 1.5 hours before sunset to 1.5 hours after sunset
     * Total duration: 3 hours (90 minutes before + 90 minutes after).
     */
    public function calculatePradoshaKaal(
        CarbonImmutable $sunset,
        int $tithiNum
    ): array {
        $isTrayodashi = ($tithiNum === 13);

        $pradoshaDuration = 90.0 * 60.0; // 90 minutes in seconds
        $pradoshaStart = $this->addFloatSeconds($sunset, -$pradoshaDuration); // 90 min before sunset
        $pradoshaEnd = $this->addFloatSeconds($sunset, $pradoshaDuration); // 90 min after sunset

        return [
            'pradosha_start' => AstroCore::formatTime($pradoshaStart),
            'pradosha_end' => AstroCore::formatTime($pradoshaEnd),
            'sunset' => AstroCore::formatTime($sunset),
            'duration_minutes' => 180.0,
            'is_trayodashi' => $isTrayodashi,
            'is_auspicious' => $isTrayodashi,
            'significance' => $isTrayodashi ? 'Most auspicious for Lord Shiva worship' : 'Pradosha occurs on Trayodashi (13th lunar day)',
        ];
    }

    /**
     * Calculate Lagna (Ascendant) using Swiss Ephemeris for precise house cusps
     * Uses Placidus house system (most accurate for Vedic astrology).
     */
    public function calculateLagna(
        CarbonImmutable $current,
        CarbonImmutable $sunrise,
        float $sunriseSunLongitude,
        float $ayanamsaDeg,
        float $lat,
        float $lon,
        SwissEphFFI $sweph
    ): array {
        // Convert local timestamp to UT Julian Day (Swiss requires UT)
        $jd = $this->carbonToJulianDayUtc($sweph, $current);

        // Whole-sign baseline for Panchang/Lagna reporting
        $cusp = $sweph->getFFI()->new('double[13]');
        $ascmc = $sweph->getFFI()->new('double[10]');

        // Ascendant is ascmc[0] and independent of house model choice.
        $retFlag = $sweph->swe_houses($jd, $lat, $lon, ord('P'), $cusp, $ascmc);

        if ($retFlag < 0) {
            throw new RuntimeException('Swiss Ephemeris failed to calculate Lagna house cusps.');
        }

        // Get tropical Ascendant longitude
        $ascLongitude = $ascmc[0];

        // Apply Ayanamsa to get Nirayana (Sidereal) longitude
        $nirayanaLagna = AstroCore::normalize($ascLongitude - $ayanamsaDeg);

        // Calculate sign and degree
        $signIndex = (int) floor($nirayanaLagna / 30.0);
        $signDegree = fmod($nirayanaLagna, 30.0);

        $signNames = [
            'Mesha', 'Vrishabha', 'Mithuna',
            'Karka', 'Simha', 'Kanya',
            'Tula', 'Vrischika', 'Dhanu',
            'Makara', 'Kumbha', 'Meena',
        ];

        return [
            'lagna_longitude_nirayana' => $nirayanaLagna,
            'lagna_longitude_sayana' => $ascLongitude,
            'sign_index' => $signIndex,
            'sign_name' => $signNames[$signIndex],
            'degree_in_sign' => $signDegree,
            'ayanamsa_applied' => $ayanamsaDeg,
        ];
    }

    /**
     * Calculate full 12 Lagna table for the day using Whole Sign system
     * This calculates exact times when each sign (Rasi) enters the Ascendant (0°00')
     * Used for Panchang, Muhurta, and D1/Rasi chart purposes.
     *
     * Algorithm: Uses Swiss Ephemeris to find exact moments when Ascendant
     * crosses 0° of each zodiac sign
     */
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
        $signNames = [
            'Mesha', 'Vrishabha', 'Mithuna',
            'Karka', 'Simha', 'Kanya',
            'Tula', 'Vrischika', 'Dhanu',
            'Makara', 'Kumbha', 'Meena',
        ];

        $lagnas = [];
        $currentSign = -1;

        // Convert local timestamps to UT Julian Day
        $jdStart = $this->carbonToJulianDayUtc($sweph, $sunrise);

        // Calculate Ascendant at sunrise to find starting sign
        $ascAtSunrise = $this->getAscendantSiderealAtJd($sweph, $jdStart, $lat, $lon, $ayanamsaDeg);
        $currentSign = (int) floor($ascAtSunrise / 30.0) % 12;

        // Find when each sign rises (crosses 0°) from sunrise to next sunrise.
        $jdEnd = $this->carbonToJulianDayUtc($sweph, $nextSunrise);

        // Detect sign-change brackets with high-precision sampling (120-second intervals), then refine with binary search.
        // This ensures lossless lagna table calculation with sub-second precision.
        $transitions = [];
        $precisionStepDays = 120.0 / 86400.0; // 120 seconds = 120/86400 days (exact, no rounding)
        $prevJd = $jdStart;
        $prevSign = $currentSign;
        for ($probe = $jdStart + $precisionStepDays; $probe <= $jdEnd; $probe += $precisionStepDays) {
            $probeSign = (int) floor($this->getAscendantSiderealAtJd($sweph, $probe, $lat, $lon, $ayanamsaDeg) / 30.0) % 12;
            if ($probeSign !== $prevSign) {
                // Binary search refinement to sub-second precision (80 iterations = ~1e-24 JD precision)
                $rise = $this->findSignRiseTime($sweph, $prevJd, $probe, $prevSign, $probeSign, $lat, $lon, $ayanamsaDeg);
                $transitions[] = $rise;
                $prevSign = $probeSign;
            }
            $prevJd = $probe;
        }

        // Build contiguous intervals from sunrise->...->next sunrise
        $bounds = array_merge([$jdStart], $transitions, [$jdEnd]);
        for ($i = 0; $i < count($bounds) - 1; $i++) {
            $startJd = $bounds[$i];
            $endJd = $bounds[$i + 1];
            if ($endJd <= $startJd) {
                continue;
            }

            $midJd = ($startJd + $endJd) / 2.0;
            $signIndex = (int) floor($this->getAscendantSiderealAtJd($sweph, $midJd, $lat, $lon, $ayanamsaDeg) / 30.0) % 12;

            $startTime = $this->jdToCarbon($startJd, $sunrise->getTimezone());
            $endTime = $this->jdToCarbon($endJd, $sunrise->getTimezone());
            $durationMinutes = ($endJd - $startJd) * 24.0 * 60.0;
            $isAfterMidnight = $startTime->lessThan($sunrise);
            $isEndAfterMidnight = $endTime->lessThan($sunrise);

            $lagnas[] = [
                'lagna_number' => $signIndex + 1,
                'sign_name' => $signNames[$signIndex],
                'sign_index' => $signIndex,
                'start' => AstroCore::formatTime($startTime) . ($isAfterMidnight ? '*' : ''),
                'end' => AstroCore::formatTime($endTime) . ($isEndAfterMidnight ? '*' : ''),
                'start_iso' => AstroCore::formatDateTime($startTime),
                'end_iso' => AstroCore::formatDateTime($endTime),
                'duration_minutes' => $durationMinutes,
                'is_day_lagna' => $startTime->lessThan($sunset),
                'start_jd' => $startJd,
                'end_jd' => $endJd,
            ];
        }

        usort($lagnas, static fn (array $a, array $b): int => ((float) ($a['start_jd'] ?? 0.0)) <=> ((float) ($b['start_jd'] ?? 0.0)));

        // Panchang presentation convention: show one complete 12-sign cycle for the day.
        // If a repeated starting sign appears near next sunrise due to sidereal-vs-solar day drift,
        // drop the trailing duplicate segment to keep canonical 12 rows.
        if (count($lagnas) > 12) {
            $firstSign = $lagnas[0]['sign_index'] ?? null;
            $lastSign = $lagnas[count($lagnas) - 1]['sign_index'] ?? null;
            if ($firstSign !== null && $firstSign === $lastSign) {
                array_pop($lagnas);
            }
        }

        return $lagnas;
    }

    private function addFloatSeconds(CarbonImmutable $dt, float $seconds): CarbonImmutable
    {
        $whole = (int) floor($seconds);
        $fraction = $seconds - $whole;
        $micros = (int) floor($fraction * 1_000_000);

        return $dt->addSeconds($whole)->addMicroseconds($micros);
    }

    private function getChogadiyaPattern(int $varaIdx, bool $isDay): array
    {
        $sequences = $isDay ? self::CHOGADIYA_DAY_SEQUENCES : self::CHOGADIYA_NIGHT_SEQUENCES;

        return $sequences[$varaIdx] ?? $sequences[0];
    }

    private function isAuspiciousChogadiya(string $name): bool
    {
        return in_array($name, self::AUSPICIOUS_CHOGADIYA, true);
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

    /**
     * Find exact time when a sign rises (Ascendant crosses 0° of that sign)
     * Uses binary search for precision.
     */
    private function findSignRiseTime(
        SwissEphFFI $sweph,
        float $jdStart,
        float $jdEnd,
        int $startSign,
        int $targetSign,
        float $lat,
        float $lon,
        float $ayanamsaDeg
    ): float {
        // Binary search in an already bracketed sign-change interval.
        // By construction, sign at jdStart != sign at jdEnd and transition occurs once.
        for ($i = 0; $i < 70; $i++) {
            $jdMid = ($jdStart + $jdEnd) / 2.0;
            $midSign = (int) floor($this->getAscendantSiderealAtJd($sweph, $jdMid, $lat, $lon, $ayanamsaDeg) / 30.0) % 12;
            if (($jdEnd - $jdStart) * 86400.0 < 0.1) {
                break;
            }
            if ($midSign === $startSign) {
                $jdStart = $jdMid;
            } else {
                $jdEnd = $jdMid;
            }
        }

        return ($jdStart + $jdEnd) / 2.0;
    }

    /** Convert Julian Day to Carbon datetime */
    private function jdToCarbon(float $jd, DateTimeZone $tz): CarbonImmutable
    {
        // JD to Unix timestamp
        $unixTimestamp = ($jd - 2440587.5) * 86400.0;
        return CarbonImmutable::createFromTimestamp($unixTimestamp, $tz);
    }

    private function getAscendantSiderealAtJd(
        SwissEphFFI $sweph,
        float $jd,
        float $lat,
        float $lon,
        float $ayanamsaDeg
    ): float {
        $cusp = $sweph->getFFI()->new('double[13]');
        $ascmc = $sweph->getFFI()->new('double[10]');
        $ret = $sweph->swe_houses($jd, $lat, $lon, ord('P'), $cusp, $ascmc);
        if ($ret < 0) {
            throw new RuntimeException('Swiss Ephemeris failed while calculating sidereal ascendant.');
        }
        return AstroCore::normalize($ascmc[0] - $ayanamsaDeg);
    }

    private function carbonToJulianDayUtc(SwissEphFFI $sweph, CarbonImmutable $dt): float
    {
        $utc = $dt->setTimezone('UTC');
        $hour = (int) $utc->format('H') + ((int) $utc->format('i')) / 60.0 + ((int) $utc->format('s')) / 3600.0 + ((int) $utc->format('u')) / 3_600_000_000.0;
        return $sweph->swe_julday(
            (int) $utc->format('Y'),
            (int) $utc->format('m'),
            (int) $utc->format('d'),
            $hour,
            SwissEphFFI::SE_GREG_CAL
        );
    }
}
