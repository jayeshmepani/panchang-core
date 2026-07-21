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

    public const ARUNODAYA_MIN_GHATIKAS = 4.0;

    public const ARUNODAYA_MAX_GHATIKAS = 5.0;

    public const DASHAMI_VEDHA_THRESHOLD_GHATIKAS_FROM_PREVIOUS_SUNRISE = 55.0;

    public const DASHAMI_VEDHA_THRESHOLD_MINUTES_FROM_PREVIOUS_SUNRISE = 1320.0;

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
            'ashtami_viddha_rejection' => true,
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
            'diwali_truth_table' => true,
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
    }

    /**
     * Configure service (optional, for standalone usage).
     *
     * @param string $ephePath Ephemeris path (empty for default)
     */
    public static function configure(string $ephePath = ''): void {}

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
            $result['observance_day_jd'] = $tithiAtSunriseDay2 ? $sunriseDay2Jd : $sunriseDay1Jd;
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
        string $tradition = 'Vaishnava',
        ?float $previousSunriseJd = null,
        float $arunodayaGhatikas = self::ARUNODAYA_GHATIKAS,
        ?float $sunsetJd = null,
        ?float $dvadashiEndJd = null,
        ?float $thirdSunriseJd = null,
        ?float $dashamiStartJd = null,
        ?bool $followingPakshaEndVriddhi = null
    ): array {
        $arunodayaGhatikas = max(self::ARUNODAYA_MIN_GHATIKAS, min(self::ARUNODAYA_MAX_GHATIKAS, $arunodayaGhatikas));
        $nightGhatiJd = $this->nightGhatiJd($sunriseJd, $nextSunriseJd, $sunsetJd);
        $arunodayaJd = $sunriseJd - ($arunodayaGhatikas * $nightGhatiJd);
        $arunodayaMinutes = ($sunriseJd - $arunodayaJd) * 1440.0;
        $dashamiVedhaThresholdJd = $sunriseJd
            - ((self::GHATIKA_PER_DAY - self::DASHAMI_VEDHA_THRESHOLD_GHATIKAS_FROM_PREVIOUS_SUNRISE) * $nightGhatiJd);

        $result = [
            'tradition' => $tradition,
            'ekadashi_start_jd' => $ekadashiStartJd,
            'ekadashi_end_jd' => $ekadashiEndJd,
            'dashami_end_jd' => $dashamiEndJd,
            'sunrise_jd' => $sunriseJd,
            'arunodaya_jd' => $arunodayaJd,
            'arunodaya_ghatikas' => $arunodayaGhatikas,
            'arunodaya_minutes' => $arunodayaMinutes,
            'arunodaya_basis' => 'dynamic_ratrimana_ghati_before_local_sunrise',
            'night_ghati_minutes' => $nightGhatiJd * 1440.0,
            'dashami_vedha_threshold_jd' => $dashamiVedhaThresholdJd,
            'dashami_vedha_threshold_ghatikas_from_previous_sunrise' => self::DASHAMI_VEDHA_THRESHOLD_GHATIKAS_FROM_PREVIOUS_SUNRISE,
            'dashami_vedha_threshold_basis' => 'dynamic_ratrimana_5_ghati_before_local_sunrise',
            'status' => '',
            'fasting_day' => '',
        ];

        $ekadashiAtSunrise = ($ekadashiStartJd <= $sunriseJd) && ($ekadashiEndJd > $sunriseJd);
        $ekadashiAtNextSunrise = ($ekadashiStartJd <= $nextSunriseJd) && ($ekadashiEndJd > $nextSunriseJd);
        $ekadashiAtPreviousSunrise = $previousSunriseJd !== null && $ekadashiStartJd <= $previousSunriseJd && $ekadashiEndJd > $previousSunriseJd;
        $dashamiAtSunrise = $dashamiEndJd > $sunriseJd;
        $dashamiAtArunodaya = $dashamiEndJd > $arunodayaJd;
        $dashamiAtPreviousSunrise = $dashamiStartJd !== null
            && $previousSunriseJd !== null
            && $dashamiStartJd <= $previousSunriseJd
            && $dashamiEndJd > $previousSunriseJd;
        $dashamiKshaya = $dashamiStartJd !== null
            && $previousSunriseJd !== null
            && !$dashamiAtPreviousSunrise
            && !$dashamiAtSunrise;
        $ekadashiVriddhi = $ekadashiAtSunrise && $ekadashiAtNextSunrise;
        $ekadashiKshaya = !$ekadashiAtSunrise && !$ekadashiAtNextSunrise;
        $thirdSunriseJd ??= $nextSunriseJd + max(0.0, $nextSunriseJd - $sunriseJd);
        $dvadashiEndJd ??= 0.0;
        $dvadashiAtNextSunrise = $dvadashiStartJd <= $nextSunriseJd && $dvadashiEndJd > $nextSunriseJd;
        $dvadashiAtThirdSunrise = $dvadashiStartJd <= $thirdSunriseJd && $dvadashiEndJd > $thirdSunriseJd;
        $dvadashiVriddhi = $dvadashiAtNextSunrise && $dvadashiAtThirdSunrise;
        $dvadashiKshaya = $dvadashiEndJd > 0.0 && !$dvadashiAtNextSunrise && !$dvadashiAtThirdSunrise;

        $result['ekadashi_at_sunrise'] = $ekadashiAtSunrise;
        $result['ekadashi_at_next_sunrise'] = $ekadashiAtNextSunrise;
        $result['ekadashi_at_previous_sunrise'] = $ekadashiAtPreviousSunrise;
        $result['dashami_at_sunrise'] = $dashamiAtSunrise;
        $result['dashami_at_arunodaya'] = $dashamiAtArunodaya;
        $result['dashami_start_jd'] = $dashamiStartJd;
        $result['dashami_at_previous_sunrise'] = $dashamiAtPreviousSunrise;
        $result['is_dashami_kshaya'] = $dashamiKshaya;
        $result['is_ekadashi_vriddhi'] = $ekadashiVriddhi;
        $result['is_ekadashi_kshaya'] = $ekadashiKshaya;
        $result['dvadashi_end_jd'] = $dvadashiEndJd > 0.0 ? $dvadashiEndJd : null;
        $result['third_sunrise_jd'] = $thirdSunriseJd;
        $result['dvadashi_at_next_sunrise'] = $dvadashiAtNextSunrise;
        $result['dvadashi_at_third_sunrise'] = $dvadashiAtThirdSunrise;
        $result['is_dvadashi_vriddhi'] = $dvadashiVriddhi;
        $result['is_dvadashi_kshaya'] = $dvadashiKshaya;
        $result['is_following_paksha_end_vriddhi'] = (bool) $followingPakshaEndVriddhi;

        if ($tradition === 'Vaishnava') {
            $dashamiPiercesNirnayVedha = $dashamiEndJd > $dashamiVedhaThresholdJd;
            $result['dashami_pierces_nirnay_vedha'] = $dashamiPiercesNirnayVedha;
            if ($ekadashiAtPreviousSunrise && $ekadashiAtSunrise) {
                $result['status'] = 'Vriddhi_Ekadashi';
                $result['case_key'] = 'vaishnava_satsangijivan_ekadashi_vriddhi_second_day';
                $result['fasting_day'] = 'Today';
            } elseif ($ekadashiVriddhi && $dvadashiKshaya) {
                $result['status'] = 'Vriddhi_Ekadashi_Dvadashi_Kshaya';
                $result['case_key'] = 'vaishnava_satsangijivan_ekadashi_vriddhi_dwadashi_kshaya';
                $result['fasting_day'] = 'Tomorrow';
            } elseif ($ekadashiVriddhi) {
                $result['status'] = 'Vriddhi_Ekadashi';
                $result['case_key'] = 'vaishnava_satsangijivan_ekadashi_vriddhi_second_day';
                $result['fasting_day'] = 'Tomorrow';
            } elseif ((bool) $followingPakshaEndVriddhi) {
                $result['status'] = 'Pakshavarddhini_Mahadvadashi';
                $result['case_key'] = 'vaishnava_pakshavarddhini_mahadvadashi';
                $result['fasting_day'] = 'Tomorrow_Mahadvadashi';
            } elseif ($dashamiPiercesNirnayVedha) {
                $result['status'] = 'Viddha_Ekadashi';
                $result['case_key'] = 'vaishnava_dashami_55_ghati_vedha';
                $result['fasting_day'] = 'Tomorrow_Mahadvadashi';
            } elseif ($dvadashiKshaya) {
                $result['status'] = 'Trisparsha_Mahadvadashi';
                $result['case_key'] = 'vaishnava_trisparsha_dwadashi_kshaya';
                $result['fasting_day'] = 'Today';
            } elseif ($dashamiKshaya) {
                $result['status'] = 'Dashami_Kshaya';
                $result['case_key'] = 'vaishnava_satsangijivan_dashami_kshaya_mahadvadashi';
                $result['fasting_day'] = 'Tomorrow_Mahadvadashi';
            } elseif ($ekadashiKshaya) {
                $result['status'] = 'Kshaya_Ekadashi';
                $result['case_key'] = 'vaishnava_kshaya_mahadvadashi';
                $result['fasting_day'] = 'Tomorrow_Mahadvadashi';
            } elseif ($dvadashiVriddhi) {
                $result['status'] = 'Dvadashi_Vriddhi_Mahadvadashi';
                $result['case_key'] = 'vaishnava_satsangijivan_dwadashi_vriddhi_mahadvadashi';
                $result['fasting_day'] = 'Tomorrow_Mahadvadashi';
                $result['capable_devotee_fast_option'] = 'Today_And_Tomorrow';
                $result['primary_fast_day'] = 'Tomorrow_Mahadvadashi';
            } elseif ($ekadashiAtSunrise && !$dashamiPiercesNirnayVedha) {
                $result['status'] = 'Shuddha_Ekadashi';
                $result['case_key'] = 'vaishnava_shuddha_clean';
                $result['fasting_day'] = 'Today';
            } elseif ($ekadashiAtNextSunrise) {
                $result['status'] = 'Ekadashi_Next_Day';
                $result['case_key'] = 'vaishnava_ekadashi_next_sunrise';
                $result['fasting_day'] = 'Tomorrow';
            } else {
                $result['status'] = 'Unmillani_Mahadvadashi';
                $result['case_key'] = 'vaishnava_unmillani_mahadvadashi';
                $result['fasting_day'] = 'Tomorrow_Mahadvadashi';
            }
        } elseif ($tradition === 'Smarta') {
            if ($ekadashiVriddhi && !$dashamiAtSunrise) {
                $result['status'] = 'Vriddhi_Ekadashi';
                $result['case_key'] = 'smarta_vriddhi_first_day';
                $result['fasting_day'] = 'Today';
            } elseif ($ekadashiAtSunrise && !$dashamiAtSunrise) {
                $result['status'] = 'Shuddha_Ekadashi';
                $result['case_key'] = $dashamiAtArunodaya ? 'smarta_shuddha_arunodaya_dashami_tolerated' : 'smarta_shuddha_clean';
                $result['fasting_day'] = 'Today';
            } elseif ($ekadashiAtSunrise) {
                $result['status'] = 'Viddha_Ekadashi';
                $result['case_key'] = 'smarta_dashami_at_sunrise_rejected';
                $result['fasting_day'] = 'Tomorrow';
            } else {
                $result['status'] = 'Ekadashi_Next_Day';
                $result['case_key'] = $ekadashiKshaya ? 'smarta_kshaya_next_day' : 'smarta_ekadashi_next_sunrise';
                $result['fasting_day'] = 'Tomorrow';
            }
        }

        return $result;
    }

    public function calculatePunyaKaal(
        string $sankrantiName,
        float $sankrantiJd,
        float $sunriseJd,
        float $sunsetJd,
        float $nextSunriseJd,
        ?float $previousSunsetJd = null
    ): array
    {
        if (!isset(self::SANKRANTI_PUNYA_KAAL[$sankrantiName])) {
            return ['error' => 'Unknown Sankranti: ' . $sankrantiName];
        }

        $config = self::SANKRANTI_PUNYA_KAAL[$sankrantiName];
        $ghatiBefore = $config['before'];
        $ghatiAfter = $config['after'];
        $isDaytime = ($sankrantiJd >= $sunriseJd) && ($sankrantiJd <= $sunsetJd);
        $dayGhatiJd = max(0.0, $sunsetJd - $sunriseJd) / 30.0;
        $previousNightGhatiJd = $previousSunsetJd !== null && $sunriseJd > $previousSunsetJd
            ? ($sunriseJd - $previousSunsetJd) / 30.0
            : max(0.0, $nextSunriseJd - $sunsetJd) / 30.0;
        $currentNightGhatiJd = max(0.0, $nextSunriseJd - $sunsetJd) / 30.0;
        $beforeGhatiJd = $isDaytime
            ? $dayGhatiJd
            : ($sankrantiJd < $sunriseJd ? $previousNightGhatiJd : $currentNightGhatiJd);
        $afterGhatiJd = $isDaytime
            ? $dayGhatiJd
            : ($sankrantiJd < $sunriseJd ? $dayGhatiJd : $currentNightGhatiJd);

        $punyaStart = $sankrantiJd - ($ghatiBefore * $beforeGhatiJd);
        $punyaEnd = $sankrantiJd + ($ghatiAfter * $afterGhatiJd);

        if (!$isDaytime) {
            if ($sankrantiJd < $sunriseJd) {
                $punyaStart = $sankrantiJd - ($ghatiBefore * $beforeGhatiJd);
                $punyaEnd = $sunriseJd + ($ghatiAfter * $afterGhatiJd);
            } else {
                $punyaStart = $sankrantiJd - ($ghatiBefore * $beforeGhatiJd);
                $punyaEnd = $nextSunriseJd + ($ghatiAfter * $dayGhatiJd);
            }
        }

        $totalMinutes = ($punyaEnd - $punyaStart) * 1440.0;
        $durationGhatis = $beforeGhatiJd > 0.0
            ? ($ghatiBefore + ($ghatiAfter * ($afterGhatiJd / $beforeGhatiJd)))
            : 0.0;

        return [
            'sankranti_name' => $sankrantiName,
            'sankranti_jd' => $sankrantiJd,
            'punya_kaal_type' => $config['type'],
            'punya_kaal_start_jd' => $punyaStart,
            'punya_kaal_end_jd' => $punyaEnd,
            'duration_ghatikas' => $durationGhatis,
            'duration_minutes' => $totalMinutes,
            'dynamic_before_ghati_minutes' => $beforeGhatiJd * 1440.0,
            'dynamic_after_ghati_minutes' => $afterGhatiJd * 1440.0,
            'ghati_basis' => $isDaytime ? 'dynamic_dinamana_30_ghati_day' : 'dynamic_segmental_ghati_by_day_night',
            'is_daytime_sankranti' => $isDaytime,
            'ghati_pala_duration' => $this->minutesToGhatiPala($totalMinutes, $beforeGhatiJd * 1440.0),
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
            return ['error' => 'Unknown festival: ' . $festivalName];
        }

        $rules = self::FESTIVAL_RULES[$festivalName];
        $dayDuration = $sunsetJd - $sunriseJd;
        $nightDuration = $nextSunriseJd - $sunsetJd;
        $dayMuhurta = $dayDuration / 15.0;
        $nightMuhurta = $nightDuration / 15.0;

        $karmakalaType = $rules['karmakala_type'];
        $karmakalaJd = match ($karmakalaType) {
            'sunrise' => $sunriseJd,
            'arunodaya' => $sunriseJd - (2.0 * $nightMuhurta), // @phpstan-ignore match.alwaysFalse
            'pratah_kal' => $sunriseJd + ($dayDuration / 10.0), // @phpstan-ignore match.alwaysFalse
            'sangava' => $sunriseJd + ($dayDuration * 3.0 / 10.0), // @phpstan-ignore match.alwaysFalse
            'madhyahna' => $sunriseJd + ($dayDuration / 2.0),
            'abhijit' => $sunriseJd + (7.5 * $dayMuhurta), // @phpstan-ignore match.alwaysFalse
            'aparahna' => $sunriseJd + ($dayDuration * 7.0 / 10.0),
            'vijaya_kaal' => $sunriseJd + (10.5 * $dayMuhurta), // @phpstan-ignore match.alwaysFalse
            'sayankala' => $sunriseJd + ($dayDuration * 9.0 / 10.0), // @phpstan-ignore match.alwaysFalse
            'nishitha' => $sunsetJd + (($nextSunriseJd - $sunsetJd) / 2.0),
            default => $sunsetJd + (1.5 * $nightMuhurta),
        };

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
        float $nextSunriseJd,
        ?float $previousSunriseJd = null,
        ?float $dvadashiEndJd = null,
        ?float $thirdSunriseJd = null,
        ?float $dashamiStartJd = null,
        ?bool $followingPakshaEndVriddhi = null
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
        $phaseTithiNumber = (($tithiNumber - 1) % 15) + 1;
        if ($phaseTithiNumber === 11) {
            $dashamiEndJd = $prevTithiEndJd;
            $dvadashiStartJd = $tithiEndJd;

            $ekadashiSmarta = $this->determineEkadashi(
                $tithiStartJd,
                $tithiEndJd,
                $dashamiEndJd,
                $dvadashiStartJd,
                $sunriseJd,
                $nextSunriseJd,
                'Smarta',
                $previousSunriseJd,
                self::ARUNODAYA_GHATIKAS,
                $sunsetJd,
                $dvadashiEndJd,
                $thirdSunriseJd,
                $dashamiStartJd,
                $followingPakshaEndVriddhi
            );

            $ekadashiVaishnava = $this->determineEkadashi(
                $tithiStartJd,
                $tithiEndJd,
                $dashamiEndJd,
                $dvadashiStartJd,
                $sunriseJd,
                $nextSunriseJd,
                'Vaishnava',
                $previousSunriseJd,
                self::ARUNODAYA_GHATIKAS,
                $sunsetJd,
                $dvadashiEndJd,
                $thirdSunriseJd,
                $dashamiStartJd,
                $followingPakshaEndVriddhi
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

    private function minutesToGhatiPala(float $minutes, float $ghatiMinutes = self::GHATI_IN_MINUTES): array
    {
        $normalizedGhatiMinutes = $ghatiMinutes > 0.0 ? $ghatiMinutes : self::GHATI_IN_MINUTES;
        $ghati = (int) floor($minutes / $normalizedGhatiMinutes);
        $remaining = $minutes - ($ghati * $normalizedGhatiMinutes);
        $pala = $remaining * (60.0 / $normalizedGhatiMinutes);

        return [
            'ghati' => $ghati,
            'pala' => $pala,
            'total_minutes' => $minutes,
            'ghati_minutes' => $normalizedGhatiMinutes,
            'basis' => $ghatiMinutes > 0.0 && abs($ghatiMinutes - self::GHATI_IN_MINUTES) > 1e-9
                ? 'dynamic_segmental_ghati'
                : 'fixed_elapsed_time_unit',
        ];
    }

    private function nightGhatiJd(float $sunriseJd, float $nextSunriseJd, ?float $sunsetJd): float
    {
        $nightDurationJd = $sunsetJd !== null && $nextSunriseJd > $sunsetJd
            ? $nextSunriseJd - $sunsetJd
            : max(0.0, $nextSunriseJd - $sunriseJd);

        return $nightDurationJd / 30.0;
    }
}
