<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Panchanga;

/**
 * Kala Nirnaya Engine - Time Determination for Hindu Festivals.
 *
 * Source basis used by the package:
 * - Nirṇaya Sindhu as a major traditional reference for festival resolution
 * - Sūrya Siddhānta style time-unit conventions
 * - Muhūrta Chintāmaṇi style karmakala references
 * - Hari Bhakti Vilāsa for Vaishnava Ekadashi-related handling
 */
class KalaNirnayaEngine
{
    public const GHATI_IN_MINUTES = 24.0;
    public const PALA_IN_SECONDS = 24.0;
    public const GHATIKA_PER_DAY = 60.0;

    public const ARUNODAYA_GHATIKAS = 4.0;
    public const ARUNODAYA_MINUTES = 96.0;

    public const SANKRANTI_PUNYA_KAAL = [
        'Makara' => ['before' => 16, 'after' => 16, 'type' => 'Maha_Punya_Kaal'],
        'Karka' => ['before' => 16, 'after' => 16, 'type' => 'Maha_Punya_Kaal'],
        'Mesha' => ['before' => 10, 'after' => 10, 'type' => 'Punya_Kaal'],
        'Tula' => ['before' => 10, 'after' => 10, 'type' => 'Punya_Kaal'],
        'Vrishabha' => ['before' => 7, 'after' => 7, 'type' => 'Sadharana_Punya_Kaal'],
        'Mithuna' => ['before' => 7, 'after' => 7, 'type' => 'Sadharana_Punya_Kaal'],
        'Simha' => ['before' => 7, 'after' => 7, 'type' => 'Sadharana_Punya_Kaal'],
        'Kanya' => ['before' => 7, 'after' => 7, 'type' => 'Sadharana_Punya_Kaal'],
        'Vrischika' => ['before' => 7, 'after' => 7, 'type' => 'Sadharana_Punya_Kaal'],
        'Dhanu' => ['before' => 7, 'after' => 7, 'type' => 'Sadharana_Punya_Kaal'],
        'Kumbha' => ['before' => 7, 'after' => 7, 'type' => 'Sadharana_Punya_Kaal'],
        'Meena' => ['before' => 7, 'after' => 7, 'type' => 'Sadharana_Punya_Kaal'],
    ];

    public const TITHI_NAMES = [
        'Prathama',
        'Dvitiya',
        'Tritiya',
        'Chaturthi',
        'Panchami',
        'Shashthi',
        'Saptami',
        'Ashtami',
        'Navami',
        'Dashami',
        'Ekadashi',
        'Dvadashi',
        'Trayodashi',
        'Chaturdashi',
        'Purnima/Amavasya',
    ];

    public const FESTIVAL_RULES = [
        'Rama_Navami' => [
            'tithi' => 9,
            'paksha' => 'Shukla',
            'masa' => 'Chaitra',
            'karmakala_type' => 'madhyahna',
            'priority' => 'tithi_at_karmakala',
        ],
        'Krishna_Janmashtami' => [
            'tithi' => 8,
            'paksha' => 'Krishna',
            'masa' => 'Shravana',
            'karmakala_type' => 'nishitha',
            'priority' => 'tithi_at_karmakala',
        ],
        'Maha_Shivaratri' => [
            'tithi' => 14,
            'paksha' => 'Krishna',
            'masa' => 'Magha',
            'karmakala_type' => 'nishitha',
            'priority' => 'tithi_at_karmakala',
        ],
        'Ganesh_Chaturthi' => [
            'tithi' => 4,
            'paksha' => 'Shukla',
            'masa' => 'Bhadrapada',
            'karmakala_type' => 'madhyahna',
            'priority' => 'tithi_at_karmakala',
        ],
        'Diwali' => [
            'tithi' => 15,
            'paksha' => 'Krishna',
            'masa' => 'Ashvina',
            'karmakala_type' => 'pradosha',
            'priority' => 'tithi_at_karmakala',
        ],
        'Holi' => [
            'tithi' => 15,
            'paksha' => 'Shukla',
            'masa' => 'Phalguna',
            'karmakala_type' => 'pradosha',
            'priority' => 'tithi_at_karmakala',
        ],
        'Akshaya_Tritiya' => [
            'tithi' => 3,
            'paksha' => 'Shukla',
            'masa' => 'Vaishakha',
            'karmakala_type' => 'sunrise',
            'priority' => 'tithi_at_karmakala',
        ],
        'Vijaya_Dashami' => [
            'tithi' => 10,
            'paksha' => 'Shukla',
            'masa' => 'Ashvina',
            'karmakala_type' => 'aparahna',
            'priority' => 'tithi_at_karmakala',
        ],
    ];
    public function __construct(public float $latitude, public float $longitude)
    {
        $this->latitude = $latitude;
        $this->longitude = $longitude;
    }

    /**
     * Configure service (optional, for standalone usage).
     *
     * @param string $ephePath Ephemeris path (empty for default)
     * @param string $ayanamsaMode Ayanamsa mode ('LAHIRI', 'RAMAN', 'KRISHNAMURTI')
     */
    public static function configure(string $ephePath = '', string $ayanamsaMode = 'LAHIRI'): void {}

    public function determineViddhaTithi(
        int $tithiNumber,
        float $tithiStartJd,
        float $tithiEndJd,
        float $sunriseDay1Jd,
        float $sunriseDay2Jd,
        float $prevTithiEndJd
    ): array {
        $result = [
            'tithi_number' => $tithiNumber,
            'tithi_name' => self::TITHI_NAMES[($tithiNumber - 1) % 15],
            'tithi_start_jd' => $tithiStartJd,
            'tithi_end_jd' => $tithiEndJd,
            'status' => '',
            'observance_day_jd' => 0.0,
        ];

        $tithiAtSunriseDay1 = ($tithiStartJd <= $sunriseDay1Jd) && ($tithiEndJd > $sunriseDay1Jd);
        $tithiAtSunriseDay2 = ($tithiStartJd <= $sunriseDay2Jd) && ($tithiEndJd > $sunriseDay2Jd);
        $prevTithiPiercesDay1 = $prevTithiEndJd > $sunriseDay1Jd;

        if ($tithiAtSunriseDay1 && !$prevTithiPiercesDay1) {
            $result['status'] = 'Shuddha';
            $result['observance_day_jd'] = $sunriseDay1Jd;
        } elseif ($tithiAtSunriseDay1) {
            $result['status'] = 'Viddha';
            if ($tithiAtSunriseDay2) {
                $result['observance_day_jd'] = $sunriseDay2Jd;
            } else {
                $result['observance_day_jd'] = $sunriseDay1Jd;
            }
        } elseif ($tithiAtSunriseDay2) {
            $result['status'] = 'Shuddha';
            $result['observance_day_jd'] = $sunriseDay2Jd;
        } else {
            $result['status'] = 'Kshaya';
            $result['observance_day_jd'] = $sunriseDay2Jd;
        }

        return $result;
    }

    public function determineEkadashi(
        float $ekadashiStartJd,
        float $ekadashiEndJd,
        float $dashamiEndJd,
        float $dvadashiStartJd,
        float $sunriseJd,
        float $nextSunriseJd,
        string $tradition = 'Vaishnava'
    ): array {
        $arunodayaJd = $sunriseJd - (self::ARUNODAYA_MINUTES / 1440.0);

        $result = [
            'tradition' => $tradition,
            'ekadashi_start_jd' => $ekadashiStartJd,
            'ekadashi_end_jd' => $ekadashiEndJd,
            'dashami_end_jd' => $dashamiEndJd,
            'sunrise_jd' => $sunriseJd,
            'arunodaya_jd' => $arunodayaJd,
            'status' => '',
            'fasting_day' => '',
        ];

        $ekadashiAtSunrise = ($ekadashiStartJd <= $sunriseJd) && ($ekadashiEndJd > $sunriseJd);
        $ekadashiAtNextSunrise = ($ekadashiStartJd <= $nextSunriseJd) && ($ekadashiEndJd > $nextSunriseJd);

        if ($tradition === 'Vaishnava') {
            $dashamiAtArunodaya = $dashamiEndJd > $arunodayaJd;
            if ($ekadashiAtSunrise && !$dashamiAtArunodaya) {
                $result['status'] = 'Shuddha_Ekadashi';
                $result['fasting_day'] = 'Today';
            } elseif ($dashamiAtArunodaya) {
                $result['status'] = 'Viddha_Ekadashi';
                $result['fasting_day'] = 'Tomorrow_Mahadvadashi';
            } else {
                if ($ekadashiAtNextSunrise) {
                    $result['status'] = 'Ekadashi_Next_Day';
                    $result['fasting_day'] = 'Tomorrow';
                } else {
                    $result['status'] = 'Unmillani_Mahadvadashi';
                    $result['fasting_day'] = 'Tomorrow_Mahadvadashi';
                }
            }
        } elseif ($tradition === 'Smarta') {
            $dashamiAtSunrise = $dashamiEndJd > $sunriseJd;
            if ($ekadashiAtSunrise && !$dashamiAtSunrise) {
                $result['status'] = 'Shuddha_Ekadashi';
                $result['fasting_day'] = 'Today';
            } elseif ($ekadashiAtSunrise) {
                $result['status'] = 'Viddha_Ekadashi';
                $result['fasting_day'] = 'Today_With_Vedha';
            } else {
                $result['status'] = 'Ekadashi_Next_Day';
                $result['fasting_day'] = 'Tomorrow';
            }
        }

        return $result;
    }

    public function calculatePunyaKaal(string $sankrantiName, float $sankrantiJd, float $sunriseJd, float $sunsetJd): array
    {
        if (!isset(self::SANKRANTI_PUNYA_KAAL[$sankrantiName])) {
            return ['error' => "Unknown Sankranti: {$sankrantiName}"];
        }

        $config = self::SANKRANTI_PUNYA_KAAL[$sankrantiName];
        $ghatiBefore = $config['before'];
        $ghatiAfter = $config['after'];

        $jdBefore = ($ghatiBefore * self::GHATI_IN_MINUTES) / 1440.0;
        $jdAfter = ($ghatiAfter * self::GHATI_IN_MINUTES) / 1440.0;

        $punyaStart = $sankrantiJd - $jdBefore;
        $punyaEnd = $sankrantiJd + $jdAfter;

        $isDaytime = ($sankrantiJd >= $sunriseJd) && ($sankrantiJd <= $sunsetJd);

        if (!$isDaytime) {
            if ($sankrantiJd < $sunriseJd) {
                $punyaStart = $sankrantiJd - $jdBefore;
                $punyaEnd = $sunriseJd + $jdAfter;
            } else {
                $nextSunriseJd = $sunriseJd + 1.0;
                $punyaStart = $sankrantiJd - $jdBefore;
                $punyaEnd = $nextSunriseJd + $jdAfter;
            }
        }

        $totalMinutes = ($punyaEnd - $punyaStart) * 1440.0;

        return [
            'sankranti_name' => $sankrantiName,
            'sankranti_jd' => $sankrantiJd,
            'punya_kaal_type' => $config['type'],
            'punya_kaal_start_jd' => $punyaStart,
            'punya_kaal_end_jd' => $punyaEnd,
            'duration_ghatikas' => $totalMinutes / self::GHATI_IN_MINUTES,
            'duration_minutes' => $totalMinutes,
            'is_daytime_sankranti' => $isDaytime,
            'ghati_pala_duration' => $this->minutesToGhatiPala($totalMinutes),
        ];
    }

    public function resolveFestivalDate(
        string $festivalName,
        float $tithiStartJd,
        float $tithiEndJd,
        float $sunriseJd,
        float $sunsetJd,
        float $nextSunriseJd
    ): array {
        if (!isset(self::FESTIVAL_RULES[$festivalName])) {
            return ['error' => "Unknown festival: {$festivalName}"];
        }

        $rules = self::FESTIVAL_RULES[$festivalName];
        $dayDuration = $sunsetJd - $sunriseJd;

        $karmakalaType = $rules['karmakala_type'];
        if ($karmakalaType === 'sunrise') {
            $karmakalaJd = $sunriseJd;
        } elseif ($karmakalaType === 'madhyahna') {
            $karmakalaJd = $sunriseJd + ($dayDuration / 2.0);
        } elseif ($karmakalaType === 'aparahna') {
            $karmakalaJd = $sunriseJd + ($dayDuration * 3.0 / 4.0);
        } elseif ($karmakalaType === 'nishitha') {
            $nightDuration = $nextSunriseJd - $sunsetJd;
            $karmakalaJd = $sunsetJd + ($nightDuration / 2.0);
        } elseif ($karmakalaType === 'pradosha') {
            $karmakalaJd = $sunsetJd + (3.0 / 24.0);
        } else {
            $karmakalaJd = $sunriseJd;
        }

        $tithiAtKarmakala = ($tithiStartJd <= $karmakalaJd) && ($tithiEndJd > $karmakalaJd);
        $tithiAtSunrise = ($tithiStartJd <= $sunriseJd) && ($tithiEndJd > $sunriseJd);

        $result = [
            'festival_name' => $festivalName,
            'required_tithi' => $rules['tithi'],
            'karmakala_type' => $karmakalaType,
            'karmakala_jd' => $karmakalaJd,
            'tithi_at_karmakala' => $tithiAtKarmakala,
            'tithi_at_sunrise' => $tithiAtSunrise,
            'observance_day' => '',
        ];

        if ($tithiAtKarmakala) {
            $result['observance_day'] = 'Today';
        } elseif ($tithiAtSunrise) {
            $result['observance_day'] = 'Today_Partial';
        } else {
            $result['observance_day'] = 'Tomorrow';
        }

        return $result;
    }

    public function generateKalaNirnayaReport(
        int $tithiNumber,
        float $tithiStartJd,
        float $tithiEndJd,
        float $prevTithiEndJd,
        float $sunriseJd,
        float $sunsetJd,
        float $nextSunriseJd
    ): array {
        $viddha = $this->determineViddhaTithi(
            $tithiNumber,
            $tithiStartJd,
            $tithiEndJd,
            $sunriseJd,
            $nextSunriseJd,
            $prevTithiEndJd
        );

        $ekadashiSmarta = null;
        $ekadashiVaishnava = null;
        if ($tithiNumber === 11) {
            $dashamiEndJd = $prevTithiEndJd;
            $dvadashiStartJd = $tithiEndJd;

            $ekadashiSmarta = $this->determineEkadashi(
                $tithiStartJd,
                $tithiEndJd,
                $dashamiEndJd,
                $dvadashiStartJd,
                $sunriseJd,
                $nextSunriseJd,
                'Smarta'
            );

            $ekadashiVaishnava = $this->determineEkadashi(
                $tithiStartJd,
                $tithiEndJd,
                $dashamiEndJd,
                $dvadashiStartJd,
                $sunriseJd,
                $nextSunriseJd,
                'Vaishnava'
            );
        }

        return [
            'viddha_tithi_analysis' => $viddha,
            'ekadashi_smarta' => $ekadashiSmarta,
            'ekadashi_vaishnava' => $ekadashiVaishnava,
            'observer_location' => [
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
            ],
        ];
    }

    private function minutesToGhatiPala(float $minutes): array
    {
        $ghati = (int) floor($minutes / self::GHATI_IN_MINUTES);
        $remaining = $minutes - ($ghati * self::GHATI_IN_MINUTES);
        $pala = $remaining * (60.0 / self::PALA_IN_SECONDS);

        return [
            'ghati' => $ghati,
            'pala' => $pala,
            'total_minutes' => $minutes,
        ];
    }
}
