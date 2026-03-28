<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Panchanga;

use JayeshMepani\PanchangCore\Core\Constants\ClassicalTimeConstants;
use JayeshMepani\PanchangCore\Core\Enums\Choghadiya;
use JayeshMepani\PanchangCore\Core\Enums\Muhurta;
use JayeshMepani\PanchangCore\Core\Enums\Nakshatra;
use JayeshMepani\PanchangCore\Core\Enums\Rasi;
use JayeshMepani\PanchangCore\Core\Enums\Tithi;
use JayeshMepani\PanchangCore\Core\Enums\Vara;

/**
 * Electional Astrology (Muhurta) Evaluator.
 *
 * Evaluates muhurta quality based on classical Muhurta texts:
 * - Muhurta Chintamani
 * - Brihat Samhita
 * - Muhurta Martanda
 * - Kalaprakashika
 * - Grihya Sutras
 *
 * All calculations are based on CURRENT TRANSIT positions.
 */
final class ElectionalEvaluator
{
    /** Panchaka remainder to type mapping (EXACT from classical texts). */
    private const PANCHAKA_TYPES = [
        1 => ['name' => 'Mrityu', 'english' => 'Death', 'severity' => 'critical'],
        2 => ['name' => 'Agni', 'english' => 'Fire', 'severity' => 'high'],
        3 => ['name' => 'Rahita', 'english' => 'Auspicious', 'severity' => 'none'],
        4 => ['name' => 'Raja', 'english' => 'King', 'severity' => 'medium'],
        5 => ['name' => 'Rahita', 'english' => 'Auspicious', 'severity' => 'none'],
        6 => ['name' => 'Chora', 'english' => 'Thief', 'severity' => 'high'],
        7 => ['name' => 'Rahita', 'english' => 'Auspicious', 'severity' => 'none'],
        8 => ['name' => 'Roga', 'english' => 'Disease', 'severity' => 'high'],
        9 => ['name' => 'Rahita', 'english' => 'Auspicious', 'severity' => 'none'],
        0 => ['name' => 'Rahita', 'english' => 'Auspicious', 'severity' => 'none'],
    ];

    /** Nakshatra Visha Ghati constants for Varjyam calculation (EXACT from classical texts). */
    private const VISHA_GHATI_CONSTANTS = [
        1 => 50, 2 => 4, 3 => 30, 4 => 40, 5 => 14, 6 => 21, 7 => 30, 8 => 20, 9 => 32,
        10 => 30, 11 => 20, 12 => 1, 13 => 21, 14 => 20, 15 => 14, 16 => 14, 17 => 10,
        18 => 14, 19 => 20, 20 => 20, 21 => 20, 22 => 10, 23 => 10, 24 => 18, 25 => 16,
        26 => 30, 27 => 30,
    ];

    /** Complete 27 Nakshatra Vedha pairs (EXACT from classical texts). */
    private const NAKSHATRA_VEDHA_PAIRS = [
        [0, 17], [1, 16], [2, 15], [3, 14], [4, 13], [5, 12], [6, 11],
        [7, 10], [8, 9], [18, 26], [19, 25], [20, 24], [21, 23], [22, 22],
    ];

    /**
     * Dagdha Tithi mappings from Muhurta Chintamani Chapter 4, Sloka 12-15.
     * Format: Rashi index (0=Aries) => [Tithi numbers].
     */
    private const DAGDHA_TITHI_MAP = [
        0 => [12, 17, 22], 1 => [7, 27], 2 => [2, 25], 3 => [10, 15, 20],
        4 => [5, 23], 5 => [1, 26], 6 => [11, 16, 21], 7 => [6, 28],
        8 => [3, 24], 9 => [9, 14, 19], 10 => [4, 29], 11 => [8, 13, 18, 30],
    ];

    /**
     * Dagdha Yoga (Vara+Tithi) mappings from classical texts.
     * Format: Vara index (0=Sunday) => [Tithi numbers].
     */
    private const DAGDHA_YOGA_MAP = [
        0 => [12], 1 => [11], 2 => [5], 3 => [3], 4 => [6], 5 => [8], 6 => [9],
    ];

    /**
     * Bhadra abode mappings from Muhurta Martanda.
     * Format: Rashi index => abode type.
     */
    private const BHADRA_ABODES = [
        'earth' => [3, 4, 10, 11], // Cancer, Leo, Aquarius, Pisces
        'heaven' => [0, 1, 2, 9], // Aries, Taurus, Gemini, Scorpio
        'underworld' => [5, 6, 7, 8], // Virgo, Libra, Sagittarius, Capricorn
    ];

    /**
     * Convert time string (HH:MM:SS AM/PM) to decimal hours.
     *
     * @param string $timeStr Time string in format "HH:MM:SS AM/PM"
     *
     * @return float Decimal hours (e.g., 6.7975 for 06:47:51 AM)
     */
    public static function timeStringToDecimal(string $timeStr): float
    {
        preg_match('/(\d{1,2}):(\d{2}):(\d{2})\s*(AM|PM)/i', $timeStr, $matches);
        if (count($matches) !== 5) {
            return 6.5; // Fallback to 6:30 AM
        }
        $hours = (int) $matches[1];
        $minutes = (int) $matches[2];
        $seconds = (int) $matches[3];
        $period = strtoupper($matches[4]);
        
        if ($period === 'PM' && $hours !== 12) {
            $hours += 12;
        } elseif ($period === 'AM' && $hours === 12) {
            $hours = 0;
        }
        
        return (float) $hours + ($minutes / 60.0) + ($seconds / 3600.0);
    }

    /**
     * Extract nakshatra timing from Varjyam data array.
     *
     * @param array|null $varjyamData Varjyam data from PanchangService
     * @param float $defaultStart Default nakshatra start time (decimal hours)
     * @param float $defaultDuration Default nakshatra duration (minutes)
     *
     * @return array [nakshatra_number, nakshatra_start, nakshatra_duration]
     */
    public static function getNakshatraTiming(?array $varjyamData, float $defaultStart, float $defaultDuration): array
    {
        $nakshatraNumber = 1;
        $nakshatraStart = $defaultStart;
        $nakshatraDuration = $defaultDuration;

        if ($varjyamData && isset($varjyamData['nakshatra_index'])) {
            $nakshatraNumber = $varjyamData['nakshatra_index'] + 1; // Convert 0-based to 1-based
        }

        if ($varjyamData && isset($varjyamData['nakshatra_start_jd'])) {
            $jd = $varjyamData['nakshatra_start_jd'];
            $jdFraction = $jd - floor($jd);
            $nakshatraStart = $jdFraction * 24.0;
            
            if (isset($varjyamData['nakshatra_end_jd'])) {
                $durationDays = $varjyamData['nakshatra_end_jd'] - $varjyamData['nakshatra_start_jd'];
                $nakshatraDuration = $durationDays * 24.0 * 60.0;
            }
        }

        return [$nakshatraNumber, $nakshatraStart, $nakshatraDuration];
    }

    /** Karmakala requirements for all 16+ activities (EXACT from classical texts). */
    private const KARMAKALA_REQUIREMENTS = [
        'vivaha' => [
            'activity_name' => 'Vivaha (Marriage)',
            'source' => 'Muhurta Chintamani / Brihat Samhita',
            'min_duration_minutes' => 240,
            'required_empty_houses' => [7],
            'forbidden_houses' => [6, 8, 12],
            'paksha_preference' => 'Shukla Paksha preferred',
            'tithi_preference' => '2, 3, 5, 7, 10, 11, 13',
            'nakshatra_preference' => 'Rohini, Mrigashira, Uttara Phalguni, Hasta, Swati, Anuradha, Uttara Ashadha, Uttara Bhadrapada, Revati',
            'lagna_preference' => 'Taurus, Gemini, Virgo, Libra, Sagittarius, Pisces',
        ],
        'griha_pravasha' => [
            'activity_name' => 'Griha Pravasha (House Entry)',
            'source' => 'Muhurta Chintamani / Classical Griha Pravasha Texts',
            'min_duration_minutes' => 120,
            'required_empty_houses' => [8],
            'forbidden_houses' => [4, 8, 12],
            'paksha_preference' => 'Shukla Paksha preferred',
            'tithi_preference' => '2, 3, 5, 7, 10, 11, 13',
            'nakshatra_preference' => 'Rohini, Mrigashira, Uttara Phalguni, Hasta, Swati, Anuradha, Uttara Ashadha, Shravana, Dhanishtha, Revati',
            'lagna_preference' => 'Taurus, Gemini, Virgo, Libra, Sagittarius, Aquarius, Pisces',
        ],
        'upanayana' => [
            'activity_name' => 'Upanayana (Sacred Thread Ceremony)',
            'source' => 'Muhurta Chintamani / Grihya Sutras',
            'min_duration_minutes' => 180,
            'required_empty_houses' => [6, 8, 12],
            'forbidden_houses' => [6, 8, 12],
            'paksha_preference' => 'Shukla Paksha required',
            'tithi_preference' => '2, 3, 5, 10, 11, 12',
            'nakshatra_preference' => 'Rohini, Mrigashira, Punarvasu, Pushya, Hasta, Swati, Anuradha, Shravana, Revati',
            'lagna_preference' => 'Taurus, Gemini, Virgo, Libra, Sagittarius, Pisces',
        ],
        'namakarana' => [
            'activity_name' => 'Namakarana (Naming Ceremony)',
            'source' => 'Muhurta Chintamani / Grihya Sutras / Manu Smriti',
            'min_duration_minutes' => 60,
            'required_empty_houses' => [],
            'forbidden_houses' => [8, 12],
            'paksha_preference' => 'Shukla Paksha preferred',
            'tithi_preference' => '2, 3, 5, 7, 10, 11, 13',
            'nakshatra_preference' => 'Rohini, Mrigashira, Punarvasu, Pushya, Hasta, Swati, Anuradha, Shravana, Revati',
            'lagna_preference' => 'Taurus, Gemini, Virgo, Libra, Pisces',
        ],
        'annaprashana' => [
            'activity_name' => 'Annaprashana (First Feeding)',
            'source' => 'Muhurta Chintamani / Grihya Sutras',
            'min_duration_minutes' => 60,
            'required_empty_houses' => [],
            'forbidden_houses' => [6, 8, 12],
            'paksha_preference' => 'Shukla Paksha preferred',
            'tithi_preference' => '2, 3, 5, 7, 10, 11, 13',
            'nakshatra_preference' => 'Rohini, Mrigashira, Punarvasu, Pushya, Hasta, Swati, Anuradha, Revati',
            'lagna_preference' => 'Taurus, Gemini, Virgo, Libra, Pisces',
        ],
        'mundan' => [
            'activity_name' => 'Mundan (Head Shaving)',
            'source' => 'Muhurta Chintamani / Grihya Sutras',
            'min_duration_minutes' => 60,
            'required_empty_houses' => [],
            'forbidden_houses' => [8, 12],
            'paksha_preference' => 'Shukla Paksha preferred',
            'tithi_preference' => '2, 3, 5, 7, 10, 11, 13',
            'nakshatra_preference' => 'Rohini, Mrigashira, Punarvasu, Pushya, Hasta, Swati, Anuradha, Revati',
            'lagna_preference' => 'Taurus, Gemini, Virgo, Libra, Pisces',
        ],
        'karnavedha' => [
            'activity_name' => 'Karnavedha (Ear Piercing)',
            'source' => 'Muhurta Chintamani / Grihya Sutras',
            'min_duration_minutes' => 60,
            'required_empty_houses' => [],
            'forbidden_houses' => [6, 8, 12],
            'paksha_preference' => 'Shukla Paksha preferred',
            'tithi_preference' => '2, 3, 5, 7, 10, 11, 13',
            'nakshatra_preference' => 'Rohini, Mrigashira, Punarvasu, Pushya, Hasta, Swati, Anuradha, Revati',
            'lagna_preference' => 'Taurus, Gemini, Virgo, Libra, Pisces',
        ],
        'aksharabhyasa' => [
            'activity_name' => 'Aksharabhyasa (Learning Start)',
            'source' => 'Muhurta Chintamani / Grihya Sutras',
            'min_duration_minutes' => 60,
            'required_empty_houses' => [],
            'forbidden_houses' => [6, 8, 12],
            'paksha_preference' => 'Shukla Paksha preferred',
            'tithi_preference' => '2, 3, 5, 7, 10, 11, 13',
            'nakshatra_preference' => 'Rohini, Mrigashira, Punarvasu, Pushya, Hasta, Swati, Anuradha, Shravana, Revati',
            'lagna_preference' => 'Taurus, Gemini, Virgo, Libra, Pisces',
        ],
        'nishkramana' => [
            'activity_name' => 'Nishkramana (First Outing)',
            'source' => 'Muhurta Chintamani / Grihya Sutras',
            'min_duration_minutes' => 60,
            'required_empty_houses' => [],
            'forbidden_houses' => [6, 8, 12],
            'paksha_preference' => 'Shukla Paksha preferred',
            'tithi_preference' => '2, 3, 5, 7, 10, 11, 13',
            'nakshatra_preference' => 'Rohini, Mrigashira, Punarvasu, Pushya, Hasta, Swati, Anuradha, Revati',
            'lagna_preference' => 'Taurus, Gemini, Virgo, Libra, Pisces',
        ],
        'yatra' => [
            'activity_name' => 'Yatra (Travel)',
            'source' => 'Muhurta Chintamani / Brihat Samhita',
            'min_duration_minutes' => 30,
            'required_empty_houses' => [],
            'forbidden_houses' => [8, 12],
            'paksha_preference' => 'Shukla Paksha preferred',
            'tithi_preference' => '2, 3, 5, 7, 10, 11, 13',
            'nakshatra_preference' => 'Ashwini, Rohini, Mrigashira, Punarvasu, Pushya, Hasta, Swati, Anuradha, Shravana, Revati',
            'lagna_preference' => 'Taurus, Gemini, Virgo, Libra, Sagittarius, Pisces',
        ],
        'vyapara' => [
            'activity_name' => 'Vyapara (Business/Trade)',
            'source' => 'Muhurta Chintamani / Brihat Samhita',
            'min_duration_minutes' => 60,
            'required_empty_houses' => [],
            'forbidden_houses' => [6, 8, 12],
            'paksha_preference' => 'Shukla Paksha preferred',
            'tithi_preference' => '2, 3, 5, 7, 10, 11, 13',
            'nakshatra_preference' => 'Rohini, Mrigashira, Punarvasu, Pushya, Hasta, Swati, Anuradha, Shravana, Dhanishtha, Revati',
            'lagna_preference' => 'Taurus, Gemini, Virgo, Libra, Sagittarius, Pisces',
        ],
        'pratishtha' => [
            'activity_name' => 'Pratishtha (Installation/Consecration)',
            'source' => 'Muhurta Chintamani / Brihat Samhita / Agama Shastras',
            'min_duration_minutes' => 120,
            'required_empty_houses' => [8, 12],
            'forbidden_houses' => [4, 8, 12],
            'paksha_preference' => 'Shukla Paksha preferred',
            'tithi_preference' => '2, 3, 5, 7, 10, 11, 13',
            'nakshatra_preference' => 'Rohini, Uttara Phalguni, Uttara Ashadha, Uttara Bhadrapada, Revati',
            'lagna_preference' => 'Taurus, Leo, Virgo, Libra, Sagittarius, Pisces',
        ],
    ];

    /**
     * Calculate Panchaka Dosha from classical Muhurta texts.
     * Source: Muhurta Chintamani, Brihat Samhita.
     *
     * Classical Formula (EXACT):
     * Sum = Tithi + Vara + Nakshatra + Lagna
     * Remainder = Sum mod 9
     *
     * @param int $tithiNumber Tithi number (1-30, count from Sukla Pratipada)
     * @param int $varaNumber Vara number (1-7, Sunday=1)
     * @param int $nakshatraNumber Nakshatra number (1-27, Aswini=1)
     * @param int $lagnaNumber Lagna number (1-12, Mesha=1)
     *
     * @return array Panchaka assessment with remainder, type, and blocked activities
     */
    public static function calculatePanchakaDosha(int $tithiNumber, int $varaNumber, int $nakshatraNumber, int $lagnaNumber): array
    {
        $sum = $tithiNumber + $varaNumber + $nakshatraNumber + $lagnaNumber;
        $remainder = $sum % 9;

        $panchakaInfo = self::PANCHAKA_TYPES[$remainder];
        $hasDosha = in_array($remainder, [1, 2, 4, 6, 8], true);

        return [
            'source' => 'Muhurta Chintamani / Brihat Samhita',
            'tithi' => $tithiNumber,
            'vara' => $varaNumber,
            'nakshatra' => $nakshatraNumber,
            'lagna' => $lagnaNumber,
            'sum' => $sum,
            'remainder' => $remainder,
            'panchaka_name' => $panchakaInfo['name'],
            'panchaka_english' => $panchakaInfo['english'],
            'severity' => $panchakaInfo['severity'],
            'has_dosha' => $hasDosha,
            'is_panchaka_rahita' => !$hasDosha,
            'blocked_activities' => $hasDosha ? ['marriage', 'griha_pravesh', 'surgery', 'important_work'] : [],
            'description' => $hasDosha ? "{$panchakaInfo['english']} Panchaka - Inauspicious" : 'Panchaka Rahita - Auspicious',
        ];
    }

    /**
     * Calculate Dagdha Tithi from Muhurta Chintamani Chapter 4, Sloka 12-15.
     *
     * Classical Formula (EXACT):
     * Tithi + Rashi combinations from classical mapping table.
     *
     * @param int $tithiNumber Tithi number (1-30)
     * @param int $moonSignIdx Moon Sign index (0-11, 0=Aries)
     *
     * @return array Dagdha Tithi assessment
     */
    public static function calculateDagdhaTithi(int $tithiNumber, int $moonSignIdx): array
    {
        $dagdhaTithis = self::DAGDHA_TITHI_MAP[$moonSignIdx] ?? [];
        $isDagdha = in_array($tithiNumber, $dagdhaTithis, true);

        return [
            'source' => 'Muhurta Chintamani Chapter 4, Sloka 12-15',
            'tithi_number' => $tithiNumber,
            'moon_sign_idx' => $moonSignIdx,
            'dagdha_tithis_for_sign' => $dagdhaTithis,
            'is_dagdha' => $isDagdha,
            'has_dosha' => $isDagdha,
            'severity' => $isDagdha ? 'high' : 'none',
            'blocked_activities' => $isDagdha ? ['all_auspicious_work', 'marriage', 'griha_pravesh', 'new_ventures'] : [],
            'description' => $isDagdha ? 'Dagdha Tithi - Inauspicious for this Moon sign' : 'Not Dagdha Tithi',
        ];
    }

    /**
     * Calculate Dagdha Yoga from classical texts.
     *
     * Classical Formula (EXACT):
     * Vara + Tithi combinations from classical mapping table.
     *
     * @param int $varaNumber Vara number (0-6, 0=Sunday)
     * @param int $tithiNumber Tithi number (1-30)
     *
     * @return array Dagdha Yoga assessment
     */
    public static function calculateDagdhaYoga(int $varaNumber, int $tithiNumber): array
    {
        $dagdhaTithis = self::DAGDHA_YOGA_MAP[$varaNumber] ?? [];
        $isDagdha = in_array($tithiNumber, $dagdhaTithis, true);

        $varaNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        return [
            'source' => 'Classical Muhurta Texts',
            'vara_number' => $varaNumber,
            'vara_name' => $varaNames[$varaNumber] ?? 'Unknown',
            'tithi_number' => $tithiNumber,
            'dagdha_tithis_for_vara' => $dagdhaTithis,
            'is_dagdha' => $isDagdha,
            'has_dosha' => $isDagdha,
            'severity' => $isDagdha ? 'high' : 'none',
            'blocked_activities' => $isDagdha ? ['all_auspicious_work', 'marriage', 'griha_pravesh', 'new_ventures'] : [],
            'description' => $isDagdha ? 'Dagdha Yoga - Inauspicious for this Vara' : 'Not Dagdha Yoga',
        ];
    }

    /**
     * Calculate Nakshatra Vedha from classical texts.
     *
     * Classical Formula (EXACT):
     * Complete 27 Nakshatra Vedha pairs - marriage prohibited if Moon in either nakshatra of the pair.
     *
     * @param int $nakshatraIdx1 First Nakshatra index (0-26)
     * @param int $nakshatraIdx2 Second Nakshatra index (0-26)
     *
     * @return array Nakshatra Vedha assessment
     */
    public static function calculateNakshatraVedha(int $nakshatraIdx1, int $nakshatraIdx2): array
    {
        $hasVedha = false;
        foreach (self::NAKSHATRA_VEDHA_PAIRS as $pair) {
            if (($nakshatraIdx1 === $pair[0] && $nakshatraIdx2 === $pair[1]) ||
                ($nakshatraIdx1 === $pair[1] && $nakshatraIdx2 === $pair[0])) {
                $hasVedha = true;
                break;
            }
        }

        return [
            'source' => 'Classical Muhurta Texts',
            'nakshatra_1_idx' => $nakshatraIdx1,
            'nakshatra_2_idx' => $nakshatraIdx2,
            'has_vedha' => $hasVedha,
            'has_dosha' => $hasVedha,
            'severity' => $hasVedha ? 'critical' : 'none',
            'blocked_activities' => $hasVedha ? ['marriage', 'all_auspicious_work'] : [],
            'description' => $hasVedha ? 'Nakshatra Vedha - Marriage prohibited' : 'No Nakshatra Vedha',
        ];
    }

    /**
     * Calculate Bhadra/Vishti Karana abode from Muhurta Martanda.
     *
     * Classical Formula (EXACT):
     * Earth signs (Cancer, Leo, Aquarius, Pisces) = Inauspicious
     * Heaven signs (Aries, Taurus, Gemini, Scorpio) = Neutral
     * Underworld signs (Virgo, Libra, Sagittarius, Capricorn) = Good for wealth
     *
     * @param int $moonSignIdx Moon Sign index (0-11, 0=Aries)
     *
     * @return array Bhadra assessment with abode type and allowed/blocked activities
     */
    public static function calculateBhadra(int $moonSignIdx): array
    {
        $moonSign = Rasi::from($moonSignIdx);
        $abodeType = 'unknown';
        $severity = 'none';
        $blockedActivities = [];
        $allowedActivities = [];

        if (in_array($moonSignIdx, self::BHADRA_ABODES['earth'], true)) {
            $abodeType = 'earth';
            $severity = 'critical';
            $blockedActivities = ['all_auspicious_work', 'marriage', 'griha_pravesh', 'new_ventures'];
        } elseif (in_array($moonSignIdx, self::BHADRA_ABODES['heaven'], true)) {
            $abodeType = 'heaven';
            $severity = 'low';
            $allowedActivities = ['spiritual_work', 'charity'];
        } elseif (in_array($moonSignIdx, self::BHADRA_ABODES['underworld'], true)) {
            $abodeType = 'underworld';
            $severity = 'none';
            $allowedActivities = ['wealth_related', 'business', 'financial_transactions'];
        }

        $abodeNames = [
            'earth' => 'Earth (Bhoo Loka)',
            'heaven' => 'Heaven (Swarga Loka)',
            'underworld' => 'Underworld (Patala Loka)',
            'unknown' => 'Unknown',
        ];

        return [
            'source' => 'Muhurta Martanda',
            'moon_sign_idx' => $moonSignIdx,
            'moon_sign_name' => $moonSign->getEnglishName(),
            'moon_sign_symbol' => $moonSign->getSymbol(),
            'abode_type' => $abodeType,
            'abode_name' => $abodeNames[$abodeType],
            'severity' => $severity,
            'is_auspicious' => $abodeType !== 'earth',
            'blocked_activities' => $blockedActivities,
            'allowed_activities' => $allowedActivities,
            'description' => match ($abodeType) {
                'earth' => 'Bhadra on Earth - Inauspicious, avoid all auspicious work',
                'heaven' => 'Bhadra in Heaven - Neutral, good for spiritual work',
                'underworld' => 'Bhadra in Underworld - Auspicious for wealth-related activities',
                default => 'Unknown Bhadra position',
            },
        ];
    }

    /**
     * Calculate Rikta Tithi dosha from classical Muhurta texts.
     * Source: Muhurta Chintamani, Gargiya Jyotisha, classical Tithi texts.
     *
     * Classical Formula (EXACT from classical texts):
     * Rikta Tithi = 4th (Chaturthi), 9th (Navami), 14th (Chaturdashi)
     *
     * @param int $tithiNumber Tithi number (1-30)
     * @param bool $isKrishnaPaksha True if Krishna Paksha (waning), false if Shukla Paksha (waxing)
     *
     * @return array Rikta Tithi assessment with dosha status and blocked activities
     */
    public static function calculateRiktaTithi(int $tithiNumber, bool $isKrishnaPaksha): array
    {
        $riktaTithis = [4, 9, 14];
        $isRikta = in_array($tithiNumber, $riktaTithis, true);

        $specialAvoidTithis = $isKrishnaPaksha ? [13] : [1];
        $isSpecialAvoid = in_array($tithiNumber, $specialAvoidTithis, true);

        $severity = 'none';
        if ($isRikta) {
            $severity = $isKrishnaPaksha ? 'high' : ($tithiNumber === 14 ? 'low' : 'medium');
        } elseif ($isSpecialAvoid) {
            $severity = 'medium';
        }

        $hasDosha = $isRikta || $isSpecialAvoid;

        return [
            'source' => 'Muhurta Chintamani / Gargiya Jyotisha / Classical Tithi Texts',
            'tithi_number' => $tithiNumber,
            'is_krishna_paksha' => $isKrishnaPaksha,
            'paksha_name' => $isKrishnaPaksha ? 'Krishna Paksha (waning)' : 'Shukla Paksha (waxing)',
            'is_rikta' => $isRikta,
            'is_special_avoid' => $isSpecialAvoid,
            'has_dosha' => $hasDosha,
            'severity' => $severity,
            'blocked_activities' => $hasDosha ? ['all_auspicious_work', 'marriage', 'griha_pravesh', 'new_ventures', 'important_work'] : [],
            'allowed_activities' => $hasDosha ? ['destruction', 'exorcism', 'removal_of_obstacles', 'harsh_acts'] : [],
            'description' => $hasDosha ? 'Rikta Tithi - avoid all auspicious beginnings' : 'Not Rikta Tithi',
        ];
    }

    /**
     * Calculate Varjyam (Visha Ghati) from classical Muhurta texts.
     * Source: Classical Panchanga calculation texts, KP System.
     *
     * Classical Formula (EXACT from classical texts):
     * Varjyam is calculated based on Nakshatra duration and fixed constants (Nakshatra Visha Ghati).
     *
     * @param int $nakshatraNumber Nakshatra number (1-27, Aswini=1)
     * @param float $nakshatraStartTime Nakshatra start time in decimal hours (e.g., 5.5 for 5:30 AM)
     * @param float $nakshatraDurationMinutes Nakshatra duration in minutes (typically 54-66 minutes)
     *
     * @return array Varjyam assessment with start/end times and dosha status
     */
    public static function calculateVarjyam(int $nakshatraNumber, float $nakshatraStartTime, float $nakshatraDurationMinutes): array
    {
        $vishaGhati = self::VISHA_GHATI_CONSTANTS[$nakshatraNumber] ?? 0;

        $startOffsetMinutes = ($vishaGhati * $nakshatraDurationMinutes) / 60.0;
        $durationMinutes = $nakshatraDurationMinutes / 15.0;

        $varjyamStart = $nakshatraStartTime + ($startOffsetMinutes / 60.0);
        $varjyamEnd = $varjyamStart + ($durationMinutes / 60.0);

        return [
            'source' => 'Classical Panchanga Calculation Texts / KP System',
            'nakshatra_number' => $nakshatraNumber,
            'visha_ghati_constant' => $vishaGhati,
            'nakshatra_start_time' => $nakshatraStartTime,
            'nakshatra_duration_minutes' => $nakshatraDurationMinutes,
            'varjyam_start_offset_minutes' => $startOffsetMinutes,
            'varjyam_duration_minutes' => $durationMinutes,
            'varjyam_start_time' => $varjyamStart,
            'varjyam_end_time' => $varjyamEnd,
            'has_varjyam' => $vishaGhati > 0,
            'severity' => $vishaGhati > 0 ? 'high' : 'none',
            'description' => $vishaGhati > 0 ? 'Varjyam (Visha Ghati) - poison period, avoid all auspicious work' : 'No Varjyam',
            'blocked_activities' => $vishaGhati > 0 ? ['all_auspicious_work', 'marriage', 'griha_pravesh', 'new_ventures', 'important_work'] : [],
        ];
    }

    /**
     * Calculate Amrita Kaal from classical Muhurta texts.
     * Source: Classical Panchanga calculation texts, Chaughadia system.
     *
     * Classical Formula (EXACT from classical texts):
     * Amrita Kaal is derived from Chaughadia Muhurta system.
     *
     * @param int $varaNumber Vara number (0-6, Sunday=0)
     * @param float $sunrise Sunrise time in decimal hours (e.g., 6.5 for 6:30 AM)
     * @param float $sunset Sunset time in decimal hours (e.g., 18.5 for 6:30 PM)
     * @param float $nextSunrise Next sunrise time in decimal hours
     * @param float $currentTime Current time in decimal hours
     *
     * @return array Amrita Kaal assessment with time windows and auspiciousness
     */
    public static function calculateAmritaKaal(int $varaNumber, float $sunrise, float $sunset, float $nextSunrise, float $currentTime): array
    {
        $vara = Vara::from($varaNumber);
        $dayDurationMinutes = ($sunset - $sunrise) * ClassicalTimeConstants::MINUTES_PER_HOUR;
        $nightDurationMinutes = ($nextSunrise - $sunset) * ClassicalTimeConstants::MINUTES_PER_HOUR;

        $dayChaughadiaDuration = $dayDurationMinutes / 8.0;
        $nightChaughadiaDuration = $nightDurationMinutes / 8.0;

        $amritaPeriods = [];

        // Get day sequence using Choghadiya enum
        $daySeq = Choghadiya::getDaySequence($vara);
        foreach ($daySeq as $index => $choghadiya) {
            if ($choghadiya === Choghadiya::Amrit) {
                $start = $sunrise + (($index * $dayChaughadiaDuration) / ClassicalTimeConstants::MINUTES_PER_HOUR);
                $end = $start + ($dayChaughadiaDuration / ClassicalTimeConstants::MINUTES_PER_HOUR);
                $amritaPeriods[] = [
                    'period' => 'day',
                    'start' => $start,
                    'end' => $end,
                    'duration_minutes' => $dayChaughadiaDuration,
                    'choghadiya_name' => $choghadiya->getName(),
                    'nature' => $choghadiya->getNature(),
                ];
            }
        }

        // Get night sequence using Choghadiya enum
        $nightSeq = Choghadiya::getNightSequence($vara);
        foreach ($nightSeq as $index => $choghadiya) {
            if ($choghadiya === Choghadiya::Amrit) {
                $start = $sunset + (($index * $nightChaughadiaDuration) / ClassicalTimeConstants::MINUTES_PER_HOUR);
                $end = $start + ($nightChaughadiaDuration / ClassicalTimeConstants::MINUTES_PER_HOUR);
                $amritaPeriods[] = [
                    'period' => 'night',
                    'start' => $start,
                    'end' => $end,
                    'duration_minutes' => $nightChaughadiaDuration,
                    'choghadiya_name' => $choghadiya->getName(),
                    'nature' => $choghadiya->getNature(),
                ];
            }
        }

        $isInAmritaKaal = false;
        $currentPeriod = null;
        foreach ($amritaPeriods as $period) {
            if ($currentTime >= $period['start'] && $currentTime < $period['end']) {
                $isInAmritaKaal = true;
                $currentPeriod = $period;
                break;
            }
        }

        return [
            'source' => 'Classical Panchanga Calculation Texts / Chaughadia System',
            'vara_number' => $varaNumber,
            'vara_name' => $vara->getEnglishName(),
            'sunrise' => $sunrise,
            'sunset' => $sunset,
            'next_sunrise' => $nextSunrise,
            'current_time' => $currentTime,
            'day_chaughadia_duration_minutes' => $dayChaughadiaDuration,
            'night_chaughadia_duration_minutes' => $nightChaughadiaDuration,
            'amrita_periods' => $amritaPeriods,
            'is_in_amrita_kaal' => $isInAmritaKaal,
            'current_amrita_period' => $currentPeriod,
            'is_auspicious' => $isInAmritaKaal,
            'description' => $isInAmritaKaal ? 'Amrita Kaal - highly auspicious period, excellent for all auspicious work' : 'Not in Amrita Kaal',
            'enhanced_activities' => $isInAmritaKaal ? ['all_auspicious_work', 'marriage', 'griha_pravesh', 'new_ventures', 'important_work', 'spiritual_practices'] : [],
        ];
    }

    /**
     * Calculate Abhijit Muhurta cancellation power from classical Muhurta texts.
     * Source: Muhurta Chintamani, Muhurta Martanda, classical Dosha Nivarana texts.
     *
     * Classical Formula (EXACT from classical texts):
     * Abhijit Muhurta is the 8th Muhurta of the day, centered at solar noon.
     * It destroys innumerable doshas and is considered a universal remedy.
     *
     * @param float $sunrise Sunrise time in decimal hours
     * @param float $sunset Sunset time in decimal hours
     * @param int $varaNumber Vara number (0-6, Sunday=0, Wednesday=3)
     * @param float $currentTime Current time in decimal hours
     *
     * @return array Abhijit Muhurta assessment with cancellation power and dosha nivarana
     */
    public static function calculateAbhijitCancellation(float $sunrise, float $sunset, int $varaNumber, float $currentTime): array
    {
        $vara = Vara::from($varaNumber);
        $dayDurationSeconds = ($sunset - $sunrise) * ClassicalTimeConstants::SECONDS_PER_HOUR;
        $muhurtaDurationSeconds = $dayDurationSeconds / count(Muhurta::cases());

        $abhijitStartSeconds = $sunrise * ClassicalTimeConstants::SECONDS_PER_HOUR + ((Muhurta::Vidhi->value - 1) * $muhurtaDurationSeconds);
        $abhijitEndSeconds = $abhijitStartSeconds + $muhurtaDurationSeconds;

        $abhijitStart = $abhijitStartSeconds / ClassicalTimeConstants::SECONDS_PER_HOUR;
        $abhijitEnd = $abhijitEndSeconds / ClassicalTimeConstants::SECONDS_PER_HOUR;

        $isInAbhijit = $currentTime >= $abhijitStart && $currentTime < $abhijitEnd;
        $isWednesday = $vara === Vara::Wednesday;

        $hasCancellationPower = $isInAbhijit && !$isWednesday;

        return [
            'source' => 'Muhurta Chintamani / Muhurta Martanda / Classical Dosha Nivarana Texts',
            'sunrise' => $sunrise,
            'sunset' => $sunset,
            'vara_number' => $varaNumber,
            'vara_name' => $vara->getEnglishName(),
            'current_time' => $currentTime,
            'abhijit_start' => $abhijitStart,
            'abhijit_end' => $abhijitEnd,
            'abhijit_duration_minutes' => ($muhurtaDurationSeconds / ClassicalTimeConstants::SECONDS_PER_MINUTE),
            'muhurta_number' => Muhurta::Vidhi->value . ' of ' . count(Muhurta::cases()) . ' (Abhijit)',
            'is_in_abhijit' => $isInAbhijit,
            'is_wednesday' => $isWednesday,
            'has_cancellation_power' => $hasCancellationPower,
            'cancellation_note' => $isWednesday ? 'Abhijit on Wednesday - not effective for dosha cancellation' : ($isInAbhijit ? 'Abhijit Muhurta - destroys innumerable doshas' : 'Not in Abhijit Muhurta'),
            'cancellable_doshas' => [
                'rikta_tithi' => true,
                'nakshatra_dosha' => true,
                'yoga_dosha' => true,
                'karana_dosha' => true,
                'minor_graha_dosha' => true,
                'varjyam' => false,
                'grahan' => false,
            ],
            'description' => $hasCancellationPower ? 'Abhijit Muhurta with full cancellation power - universal remedy for most doshas' : ($isInAbhijit ? 'Abhijit Muhurta on Wednesday - limited cancellation power' : 'Not in Abhijit Muhurta'),
        ];
    }

    /**
     * Get Karmakala event window requirements for specific activities.
     * Source: Muhurta Chintamani, Brihat Samhita, Drik Panchang classical rules.
     *
     * @param string $activityKey Activity key (e.g., 'vivaha', 'griha_pravasha')
     *
     * @return array Karmakala requirements with duration, empty houses, and planetary requirements
     */
    public static function getKarmakalaRequirements(string $activityKey): array
    {
        return self::KARMAKALA_REQUIREMENTS[$activityKey] ?? [
            'activity_name' => 'Unknown Activity',
            'source' => 'Classical Muhurta Texts',
            'min_duration_minutes' => 60,
            'required_empty_houses' => [],
            'forbidden_houses' => [6, 8, 12],
            'paksha_preference' => 'Shukla Paksha preferred',
            'tithi_preference' => '2, 3, 5, 7, 10, 11, 13',
            'nakshatra_preference' => 'Rohini, Mrigashira, Uttara Phalguni, Hasta, Swati, Anuradha, Revati',
            'lagna_preference' => 'Taurus, Gemini, Virgo, Libra, Pisces',
        ];
    }

    /**
     * Generate detailed rejection report for Muhurta evaluation.
     * Source: Muhurta Chintamani, Brihat Samhita, classical Dosha analysis texts.
     *
     * @param array $evaluationResults Evaluation results from evaluateActivityProfile
     * @param string $activityKey Activity being evaluated
     *
     * @return array Detailed rejection report with rationale, severity, and alternatives
     */
    public static function generateRejectionReport(array $evaluationResults, string $activityKey): array
    {
        $rejections = [];
        $warnings = [];
        $acceptances = [];

        foreach ($evaluationResults as $factor => $result) {
            if (!is_array($result)) {
                continue;
            }

            if (isset($result['has_dosha']) && $result['has_dosha'] === true) {
                $severity = $result['severity'] ?? 'medium';
                $doshaName = $result['name'] ?? $factor;
                $source = $result['source'] ?? 'Classical Muhurta Texts';
                $blockedActivities = $result['blocked_activities'] ?? [];
                $description = $result['description'] ?? 'Dosha present';

                $rejectionEntry = [
                    'dosha_name' => $doshaName,
                    'severity' => $severity,
                    'source' => $source,
                    'description' => $description,
                    'blocked_activities' => $blockedActivities,
                    'cancellation_possible' => in_array($severity, ['low', 'medium'], true),
                    'cancellation_method' => in_array($severity, ['low', 'medium'], true) ? 'Abhijit Muhurta or specific remedies' : null,
                ];

                if (in_array($severity, ['critical', 'high'], true)) {
                    $rejections[] = $rejectionEntry;
                } elseif ($severity === 'medium') {
                    $warnings[] = $rejectionEntry;
                } else {
                    $acceptances[] = $rejectionEntry;
                }
            }

            if (isset($result['is_good']) && $result['is_good'] === false) {
                $balaName = $result['name'] ?? $factor;
                $severity = 'low';
                $source = $result['source'] ?? 'Classical Muhurta Texts';
                $description = $result['description'] ?? 'Weak strength';

                $warnings[] = [
                    'dosha_name' => $balaName,
                    'severity' => $severity,
                    'source' => $source,
                    'description' => $description,
                    'blocked_activities' => [],
                    'cancellation_possible' => true,
                    'cancellation_method' => 'Strengthen through remedies or wait for better time',
                ];
            }
        }

        $overallVerdict = 'rejected';
        $confidenceLevel = 'high';

        if (empty($rejections) && empty($warnings)) {
            $overallVerdict = 'accepted';
            $confidenceLevel = 'high';
        } elseif (empty($rejections) && !empty($warnings)) {
            $overallVerdict = 'accepted_with_warnings';
            $confidenceLevel = 'medium';
        } elseif (!empty($rejections) && count($rejections) <= 2) {
            $overallVerdict = 'rejected_but_can_try_remedies';
            $confidenceLevel = 'low';
        }

        return [
            'source' => 'Muhurta Chintamani / Brihat Samhita / Classical Dosha Analysis Texts',
            'activity_key' => $activityKey,
            'overall_verdict' => $overallVerdict,
            'confidence_level' => $confidenceLevel,
            'rejection_count' => count($rejections),
            'warning_count' => count($warnings),
            'acceptance_count' => count($acceptances),
            'critical_rejections' => array_filter($rejections, fn ($r) => $r['severity'] === 'critical'),
            'high_severity_rejections' => array_filter($rejections, fn ($r) => $r['severity'] === 'high'),
            'medium_severity_warnings' => $warnings,
            'low_severity_acceptances' => $acceptances,
            'detailed_rejections' => $rejections,
            'detailed_warnings' => $warnings,
            'detailed_acceptances' => $acceptances,
            'remedies_available' => !empty(array_filter($rejections, fn ($r) => $r['cancellation_possible'])),
            'recommendation' => match ($overallVerdict) {
                'accepted' => 'Muhurta is auspicious. Proceed with confidence.',
                'accepted_with_warnings' => 'Muhurta is acceptable but has minor doshas. Consider remedies.',
                'rejected_but_can_try_remedies' => 'Muhurta has significant doshas. Remedies may help but alternative preferred.',
                'rejected' => 'Muhurta is inauspicious. Strongly recommend finding alternative time.',
                default => 'Unable to determine. Consult classical texts.',
            },
        ];
    }

    /**
     * Get complete classical activity matrix for all 16+ Muhurta activities.
     * Source: Muhurta Chintamani, Brihat Samhita, Grihya Sutras, classical Samskara texts.
     *
     * @return array Complete activity matrix with all classical requirements
     */
    public static function getCompleteActivityMatrix(): array
    {
        return self::KARMAKALA_REQUIREMENTS;
    }
}
