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
 * Electional Astrology (Muhurta) evaluator.
 *
 * This class mixes:
 * - transit facts computed elsewhere in the package,
 * - package rule mappings attributed to traditional muhurta literature,
 * - and a small number of explicitly marked legacy or heuristic helpers.
 *
 * It should be treated as a package evaluation layer, not as a fully text-critical
 * edition of every cited classical source.
 */
final class ElectionalEvaluator
{
    /** Package Panchaka remainder mapping attributed to classical Muhurta sources. */
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

    /** Package Visha Ghati constants used for the legacy Varjyam helper. */
    private const VISHA_GHATI_CONSTANTS = [
        1 => 50, 2 => 4, 3 => 30, 4 => 40, 5 => 14, 6 => 21, 7 => 30, 8 => 20, 9 => 32,
        10 => 30, 11 => 20, 12 => 1, 13 => 21, 14 => 20, 15 => 14, 16 => 14, 17 => 10,
        18 => 14, 19 => 20, 20 => 20, 21 => 20, 22 => 10, 23 => 10, 24 => 18, 25 => 16,
        26 => 30, 27 => 30,
    ];

    /**
     * Package Dagdha Tithi mapping attributed to Muhurta Chintamani.
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

    /**
     * Legacy moorthy classifier from nakshatra context.
     * This is not source-verified against a primary textual rule in the package.
     */
    public static function calculateTransitMoorthy(string $nakshatraName): array
    {
        $normalized = strtolower(preg_replace('/\s+/', '', trim($nakshatraName)));
        $nakshatraNumber = 1;

        foreach (Nakshatra::cases() as $case) {
            $caseName = strtolower(preg_replace('/\s+/', '', $case->getName()));
            if ($caseName === $normalized) {
                $nakshatraNumber = $case->value + 1;
                break;
            }
        }

        $moorthyIdx = $nakshatraNumber % 4;
        $moorthy = match ($moorthyIdx) {
            1 => ['name' => 'Suvarna', 'english' => 'Gold', 'quality' => 'excellent', 'score' => 4],
            2 => ['name' => 'Rajata', 'english' => 'Silver', 'quality' => 'good', 'score' => 3],
            3 => ['name' => 'Tamra', 'english' => 'Copper', 'quality' => 'mixed', 'score' => 2],
            default => ['name' => 'Lauha', 'english' => 'Iron', 'quality' => 'challenging', 'score' => 1],
        };

        return [
            'source' => 'Unverified heuristic moorthy classifier',
            'is_verified' => false,
            'nakshatra_name' => $nakshatraName,
            'nakshatra_number' => $nakshatraNumber,
            'moorthy' => $moorthy['name'],
            'moorthy_english' => $moorthy['english'],
            'quality' => $moorthy['quality'],
            'score' => $moorthy['score'],
        ];
    }

    /**
     * Calculate Panchaka Dosha using the package mapping attributed to
     * Muhurta Chintamani and Brihat Samhita.
     *
     * Package formula:
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
        $tithi = Tithi::from($tithiNumber);
        $vara = Vara::from($varaNumber - 1);
        $nakshatra = Nakshatra::from($nakshatraNumber - 1);
        $lagna = Rasi::from($lagnaNumber - 1);

        return [
            'source' => 'Package rule mapping attributed to Muhurta Chintamani / Brihat Samhita',
            'tithi' => $tithiNumber,
            'tithi_name' => $tithi->getName(),
            'tithi_number_base' => 1,
            'vara' => $varaNumber,
            'vara_name' => $vara->getEnglishName(),
            'vara_number_base' => 1,
            'nakshatra' => $nakshatraNumber,
            'nakshatra_name' => $nakshatra->getName(),
            'nakshatra_number_base' => 1,
            'lagna' => $lagnaNumber,
            'lagna_name' => $lagna->getName(),
            'lagna_number_base' => 1,
            'sum' => $sum,
            'remainder' => $remainder,
            'panchaka_name' => $panchakaInfo['name'],
            'panchaka_english' => $panchakaInfo['english'],
            'severity' => $panchakaInfo['severity'],
            'has_dosha' => $hasDosha,
            'is_panchaka_rahita' => !$hasDosha,
            'description' => $hasDosha ? "{$panchakaInfo['english']} Panchaka - Inauspicious" : 'Panchaka Rahita - Auspicious',
        ];
    }

    /**
     * Calculate Dagdha Tithi using the package mapping attributed to Muhurta Chintamani.
     *
     * Package formula:
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
        $tithi = Tithi::from($tithiNumber);
        $moonSign = Rasi::from($moonSignIdx);

        return [
            'source' => 'Package rule mapping attributed to Muhurta Chintamani',
            'tithi_number' => $tithiNumber,
            'tithi_name' => $tithi->getName(),
            'tithi_number_base' => 1,
            'moon_sign_idx' => $moonSignIdx,
            'moon_sign_index_base' => 0,
            'moon_sign_number' => $moonSignIdx + 1,
            'moon_sign_number_base' => 1,
            'moon_sign_name' => $moonSign->getEnglishName(),
            'dagdha_tithis_for_sign' => $dagdhaTithis,
            'dagdha_tithi_names_for_sign' => array_map(
                static fn (int $candidate): string => Tithi::from($candidate)->getName(),
                $dagdhaTithis
            ),
            'is_dagdha' => $isDagdha,
            'has_dosha' => $isDagdha,
            'severity' => $isDagdha ? 'high' : 'none',
            'description' => $isDagdha ? 'Dagdha Tithi - Inauspicious for this Moon sign' : 'Not Dagdha Tithi',
        ];
    }

    /**
     * Calculate Dagdha Yoga from classical texts.
     *
     * Package formula:
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

        $vara = Vara::from($varaNumber);
        $tithi = Tithi::from($tithiNumber);

        return [
            'source' => 'Package rule mapping attributed to classical Muhurta texts',
            'vara_number' => $varaNumber,
            'vara_name' => $vara->getEnglishName(),
            'vara_index_base' => 0,
            'vara_sequence_number' => $varaNumber + 1,
            'vara_sequence_number_base' => 1,
            'tithi_number' => $tithiNumber,
            'tithi_name' => $tithi->getName(),
            'tithi_number_base' => 1,
            'dagdha_tithis_for_vara' => $dagdhaTithis,
            'dagdha_tithi_names_for_vara' => array_map(
                static fn (int $candidate): string => Tithi::from($candidate)->getName(),
                $dagdhaTithis
            ),
            'is_dagdha' => $isDagdha,
            'has_dosha' => $isDagdha,
            'severity' => $isDagdha ? 'high' : 'none',
            'description' => $isDagdha ? 'Dagdha Yoga - Inauspicious for this Vara' : 'Not Dagdha Yoga',
        ];
    }

    /**
     * Legacy Bhadra helper.
     *
     * This only classifies a moon-sign based Bhadravasa heuristic and does not determine
     * whether Vishti/Bhadra is actually active at a given time. For accurate Bhadra usage,
     * use PanchangService day output and its timed Bhadra windows.
     *
     * @param int $moonSignIdx Moon Sign index (0-11, 0=Aries)
     *
     * @return array Bhadra assessment with abode type
     */
    public static function calculateBhadra(int $moonSignIdx): array
    {
        $moonSign = Rasi::from($moonSignIdx);
        $abodeType = 'unknown';
        $severity = 'none';

        if (in_array($moonSignIdx, self::BHADRA_ABODES['earth'], true)) {
            $abodeType = 'earth';
            $severity = 'critical';
        } elseif (in_array($moonSignIdx, self::BHADRA_ABODES['heaven'], true)) {
            $abodeType = 'heaven';
            $severity = 'low';
        } elseif (in_array($moonSignIdx, self::BHADRA_ABODES['underworld'], true)) {
            $abodeType = 'underworld';
            $severity = 'none';
        }

        $abodeNames = [
            'earth' => 'Earth (Bhoo Loka)',
            'heaven' => 'Heaven (Swarga Loka)',
            'underworld' => 'Underworld (Patala Loka)',
            'unknown' => 'Unknown',
        ];

        return [
            'source' => 'Legacy Bhadravasa heuristic',
            'is_verified' => false,
            'moon_sign_idx' => $moonSignIdx,
            'moon_sign_name' => $moonSign->getEnglishName(),
            'moon_sign_symbol' => $moonSign->getSymbol(),
            'abode_type' => $abodeType,
            'abode_name' => $abodeNames[$abodeType],
            'severity' => $severity,
            'is_auspicious' => $abodeType !== 'earth',
            'description' => match ($abodeType) {
                'earth' => 'Moon-sign heuristic maps Bhadravasa to Earth; this is not a timed active-Bhadra judgement.',
                'heaven' => 'Moon-sign heuristic maps Bhadravasa to Heaven; this is not a timed active-Bhadra judgement.',
                'underworld' => 'Moon-sign heuristic maps Bhadravasa to Underworld; this is not a timed active-Bhadra judgement.',
                default => 'Unknown Bhadravasa heuristic state',
            },
        ];
    }

    /**
     * Calculate Rikta Tithi dosha from classical Muhurta texts.
     * Source attributions used by the package: Muhurta Chintamani, Gargiya Jyotisha,
     * and other Rikta Tithi traditions.
     *
     * Package baseline rule:
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
        $tithi = Tithi::from($tithiNumber);

        $severity = 'none';
        if ($isRikta) {
            $severity = $isKrishnaPaksha ? 'high' : ($tithiNumber === 14 ? 'low' : 'medium');
        } elseif ($isSpecialAvoid) {
            $severity = 'medium';
        }

        $hasDosha = $isRikta || $isSpecialAvoid;

        return [
            'source' => 'Package rule mapping attributed to Muhurta Chintamani / Gargiya Jyotisha',
            'tithi_number' => $tithiNumber,
            'tithi_name' => $tithi->getName(),
            'tithi_number_base' => 1,
            'is_krishna_paksha' => $isKrishnaPaksha,
            'paksha_name' => $isKrishnaPaksha ? 'Krishna Paksha (waning)' : 'Shukla Paksha (waxing)',
            'is_rikta' => $isRikta,
            'is_special_avoid' => $isSpecialAvoid,
            'has_dosha' => $hasDosha,
            'severity' => $severity,
            'description' => $hasDosha ? 'Rikta Tithi - avoid all auspicious beginnings' : 'Not Rikta Tithi',
        ];
    }

    /**
     * Calculate Varjyam (Visha Ghati) from classical Muhurta texts.
     * Source basis used by the package: published Panchanga Varjyam tables and KP-style references.
     *
     * Package helper formula:
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
        $nakshatra = Nakshatra::from($nakshatraNumber - 1);

        $startOffsetMinutes = ($vishaGhati * $nakshatraDurationMinutes) / 60.0;
        $durationMinutes = $nakshatraDurationMinutes / 15.0;

        $varjyamStart = $nakshatraStartTime + ($startOffsetMinutes / 60.0);
        $varjyamEnd = $varjyamStart + ($durationMinutes / 60.0);

        return [
            'source' => 'Legacy package helper using published Visha Ghati constants',
            'nakshatra_number' => $nakshatraNumber,
            'nakshatra_name' => $nakshatra->getName(),
            'nakshatra_number_base' => 1,
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
        ];
    }

    /**
     * Legacy Amrit-segment helper based on Choghadiya.
     *
     * This is not the same concept as Panchang "Amrita Kalam" derived from Varjyam-opposite timing.
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
            'source' => 'Legacy Choghadiya-based Amrit segment helper',
            'is_verified' => false,
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
        ];
    }

    /**
     * Calculate Abhijit Muhurta cancellation power from classical Muhurta texts.
     * Source attributions used by the package: Muhurta Chintamani, Muhurta Martanda,
     * and later dosha-nivarana traditions.
     *
     * Package formula:
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
            'source' => 'Package rule mapping attributed to Muhurta Chintamani / Muhurta Martanda',
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
     * Generate detailed rejection report for Muhurta evaluation.
     * Source basis used by the package: evaluator rules attributed to Muhurta Chintamani,
     * Brihat Samhita, and related dosha-analysis traditions.
     *
     * @param array $evaluationResults Evaluation results from getDailyMuhurtaEvaluation
     *
     * @return array Detailed rejection report with rationale and severity
     */
    public static function generateRejectionReport(array $evaluationResults): array
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
                $source = $result['source'] ?? 'Package evaluator result';
                $description = $result['description'] ?? 'Dosha present';

                $rejectionEntry = [
                    'dosha_name' => $doshaName,
                    'severity' => $severity,
                    'source' => $source,
                    'description' => $description,
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
                $source = $result['source'] ?? 'Package evaluator result';
                $description = $result['description'] ?? 'Weak strength';

                $warnings[] = [
                    'dosha_name' => $balaName,
                    'severity' => $severity,
                    'source' => $source,
                    'description' => $description,
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
            'source' => 'Package evaluation summary from configured/transit-only rule mappings',
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

}
