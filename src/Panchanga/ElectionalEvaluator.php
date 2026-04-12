<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Panchanga;

use JayeshMepani\PanchangCore\Core\Constants\ClassicalTimeConstants;
use JayeshMepani\PanchangCore\Core\Enums\Muhurta;
use JayeshMepani\PanchangCore\Core\Enums\Nakshatra;
use JayeshMepani\PanchangCore\Core\Enums\Rasi;
use JayeshMepani\PanchangCore\Core\Enums\Tithi;
use JayeshMepani\PanchangCore\Core\Enums\Vara;
use JayeshMepani\PanchangCore\Core\Localization;

/** Electional Astrology (Muhurta) evaluator. */
final class ElectionalEvaluator
{
    /** Package Panchaka remainder mapping attributed to classical Muhurta sources. */
    private const array PANCHAKA_TYPES = [
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
    private const array VISHA_GHATI_CONSTANTS = [
        1 => 50, 2 => 4, 3 => 30, 4 => 40, 5 => 14, 6 => 21, 7 => 30, 8 => 20, 9 => 32,
        10 => 30, 11 => 20, 12 => 1, 13 => 21, 14 => 20, 15 => 14, 16 => 14, 17 => 10,
        18 => 14, 19 => 20, 20 => 20, 21 => 20, 22 => 10, 23 => 10, 24 => 18, 25 => 16,
        26 => 30, 27 => 30,
    ];

    /** Package Dagdha Tithi mapping attributed to Muhurta Chintamani. */
    private const array DAGDHA_TITHI_MAP = [
        0 => [12, 17, 22], 1 => [7, 27], 2 => [2, 25], 3 => [10, 15, 20],
        4 => [5, 23], 5 => [1, 26], 6 => [11, 16, 21], 7 => [6, 28],
        8 => [3, 24], 9 => [9, 14, 19], 10 => [4, 29], 11 => [8, 13, 18, 30],
    ];

    /** Dagdha Yoga (Vara+Tithi) mappings from classical texts. */
    private const array DAGDHA_YOGA_MAP = [
        0 => [12], 1 => [11], 2 => [5], 3 => [3], 4 => [6], 5 => [8], 6 => [9],
    ];

    /** Bhadra abode mappings from Muhurta Martanda. */
    private const array BHADRA_ABODES = [
        'earth' => [3, 4, 10, 11],
        'heaven' => [0, 1, 2, 9],
        'underworld' => [5, 6, 7, 8],
    ];

    public static function calculateTransitMoorthy(string $nakshatraName): array
    {
        $normalized = strtolower((string) preg_replace('/\s+/', '', trim($nakshatraName)));
        $nakshatraNumber = 1;

        foreach (Nakshatra::cases() as $case) {
            $caseName = strtolower((string) preg_replace('/\s+/', '', $case->getName('en')));
            if ($caseName === $normalized) {
                $nakshatraNumber = $case->value + 1;
                break;
            }
        }

        $moorthyIdx = $nakshatraNumber % 4;
        $moorthyKey = match ($moorthyIdx) {
            1 => 'Suvarna',
            2 => 'Rajata',
            3 => 'Tamra',
            default => 'Lauha',
        };

        $qualityKey = match ($moorthyIdx) {
            1 => 'Excellent',
            2 => 'Good',
            3 => 'Mixed',
            default => 'Challenging',
        };

        return [
            'moorthy' => Localization::translate('Moorthy', $moorthyKey),
            'quality' => Localization::translate('Common', $qualityKey),
            'score' => match($moorthyIdx) { 1 => 4, 2 => 3, 3 => 2, default => 1 },
        ];
    }

    public static function calculatePanchakaDosha(int $tithiNumber, int $varaNumber, int $nakshatraNumber, int $lagnaNumber): array
    {
        // Adjust indices to match 1-based logic usually expected in Muhurta manuals for the sum
        // tithi (1-30), vara (1-7, Sun=1), nakshatra (1-27), lagna (1-12)
        $tNum = $tithiNumber;
        $vNum = $varaNumber + 1;
        $nNum = $nakshatraNumber;
        $lNum = $lagnaNumber;

        $sum = $tNum + $vNum + $nNum + $lNum;
        $remainder = $sum % 9;

        $panchakaInfo = self::PANCHAKA_TYPES[$remainder];
        $hasDosha = in_array($remainder, [1, 2, 4, 6, 8], true);

        $panchakaName = Localization::translate('Panchaka', $panchakaInfo['name']);
        $panchakaEng = Localization::translate('Panchaka', $panchakaInfo['english'], 'en');
        $description = $hasDosha
            ? $panchakaName . ' - ' . Localization::translate('Common', 'Inauspicious')
            : $panchakaName . ' - ' . Localization::translate('Common', 'Auspicious');

        return [
            'source' => Localization::translate('Source', 'Muhurta Chintamani / Brihat Samhita'),
            'tithi' => $tithiNumber,
            'tithi_name' => Tithi::from(self::normalizeTithiNumber($tithiNumber))->getName(),
            'tithi_number_base' => 1,
            'vara' => $varaNumber + 1,
            'vara_name' => Vara::from($varaNumber % 7)->getName(),
            'vara_number_base' => 1,
            'nakshatra' => $nakshatraNumber,
            'nakshatra_name' => Nakshatra::from(($nakshatraNumber - 1) % 27)->getName(),
            'nakshatra_number_base' => 1,
            'lagna' => $lagnaNumber,
            'lagna_name' => Rasi::from(($lagnaNumber - 1) % 12)->getName(),
            'lagna_number_base' => 1,
            'sum' => $sum,
            'remainder' => $remainder,
            'panchaka_name' => $panchakaName,
            'panchaka_english' => $panchakaEng,
            'severity' => $panchakaInfo['severity'],
            'has_dosha' => $hasDosha,
            'is_panchaka_rahita' => !$hasDosha,
            'description' => $description,
        ];
    }

    public static function calculateDagdhaTithi(int $tithiNumber, int $moonSignIdx): array
    {
        $dagdhaTithis = self::DAGDHA_TITHI_MAP[$moonSignIdx] ?? [];
        $isDagdha = in_array($tithiNumber, $dagdhaTithis, true);

        $names = array_map(fn ($idx) => Tithi::from($idx)->getName(), $dagdhaTithis);

        return [
            'source' => Localization::translate('Source', 'Muhurta Chintamani'),
            'tithi_number' => $tithiNumber,
            'tithi_name' => Tithi::from(self::normalizeTithiNumber($tithiNumber))->getName(),
            'tithi_number_base' => 1,
            'moon_sign_idx' => $moonSignIdx,
            'moon_sign_index_base' => 0,
            'moon_sign_number' => $moonSignIdx + 1,
            'moon_sign_number_base' => 1,
            'moon_sign_name' => Rasi::from($moonSignIdx)->getName(),
            'dagdha_tithis_for_sign' => $dagdhaTithis,
            'dagdha_tithi_names_for_sign' => $names,
            'is_dagdha' => $isDagdha,
            'has_dosha' => $isDagdha,
            'severity' => $isDagdha ? 'high' : 'none',
            'description' => $isDagdha
                ? Localization::translate('String', 'Dagdha Tithi') . ' - ' . Localization::translate('Common', 'Inauspicious')
                : Localization::translate('String', 'Not Dagdha Tithi'),
        ];
    }

    public static function calculateDagdhaYoga(int $varaNumber, int $tithiNumber): array
    {
        $dagdhaTithis = self::DAGDHA_YOGA_MAP[$varaNumber] ?? [];
        $isDagdha = in_array($tithiNumber, $dagdhaTithis, true);
        $names = array_map(fn ($idx) => Tithi::from($idx)->getName(), $dagdhaTithis);

        return [
            'source' => Localization::translate('Source', 'Classical Muhurta texts'),
            'vara_number' => $varaNumber,
            'vara_name' => Vara::from($varaNumber)->getName(),
            'vara_index_base' => 0,
            'vara_sequence_number' => $varaNumber + 1,
            'vara_sequence_number_base' => 1,
            'tithi_number' => $tithiNumber,
            'tithi_name' => Tithi::from(self::normalizeTithiNumber($tithiNumber))->getName(),
            'tithi_number_base' => 1,
            'dagdha_tithis_for_vara' => $dagdhaTithis,
            'dagdha_tithi_names_for_vara' => $names,
            'is_dagdha' => $isDagdha,
            'has_dosha' => $isDagdha,
            'severity' => $isDagdha ? 'high' : 'none',
            'description' => $isDagdha
                ? Localization::translate('String', 'Dagdha Yoga') . ' - ' . Localization::translate('Common', 'Inauspicious')
                : Localization::translate('String', 'Not Dagdha Yoga'),
        ];
    }

    public static function calculateBhadra(int $moonSignIdx): array
    {
        $abodeType = 'unknown';
        if (in_array($moonSignIdx, self::BHADRA_ABODES['earth'], true)) {
            $abodeType = 'Earth';
        } elseif (in_array($moonSignIdx, self::BHADRA_ABODES['heaven'], true)) {
            $abodeType = 'Heaven';
        } elseif (in_array($moonSignIdx, self::BHADRA_ABODES['underworld'], true)) {
            $abodeType = 'Underworld';
        }

        return [
            'abode_name' => Localization::translate('Common', $abodeType),
            'is_auspicious' => $abodeType !== 'Earth',
        ];
    }

    public static function calculateRiktaTithi(int $tithiNumber, bool $isKrishnaPaksha): array
    {
        $riktaTithis = [4, 9, 14];
        $isRikta = in_array($tithiNumber, $riktaTithis, true);
        $hasDosha = $isRikta;

        $pakshaName = $isKrishnaPaksha ? 'Krishna Paksha (waning)' : 'Shukla Paksha (waxing)';

        return [
            'source' => Localization::translate('Source', 'Muhurta Chintamani / Gargiya Jyotisha'),
            'tithi_number' => $tithiNumber,
            'tithi_name' => Tithi::from(self::normalizeTithiNumber($tithiNumber))->getName(),
            'tithi_number_base' => 1,
            'is_krishna_paksha' => $isKrishnaPaksha,
            'paksha_name' => Localization::translate('String', $pakshaName),
            'is_rikta' => $isRikta,
            'is_special_avoid' => false,
            'has_dosha' => $hasDosha,
            'severity' => $hasDosha ? 'high' : 'none',
            'description' => $hasDosha
                ? Localization::translate('String', 'Rikta Tithi') . ' - ' . Localization::translate('Common', 'Inauspicious')
                : Localization::translate('String', 'Not Rikta Tithi'),
        ];
    }

    public static function calculateVarjyam(int $nakshatraNumber, float $nakshatraStartTime, float $nakshatraDurationMinutes): array
    {
        $vishaGhati = self::VISHA_GHATI_CONSTANTS[$nakshatraNumber] ?? 0;
        $hasDosha = $vishaGhati > 0;

        return [
            'source' => Localization::translate('Source', 'Visha Ghati constants'),
            'nakshatra_number' => $nakshatraNumber,
            'nakshatra_name' => Nakshatra::from(($nakshatraNumber - 1) % 27)->getName(),
            'nakshatra_number_base' => 1,
            'visha_ghati_start_constant' => $vishaGhati,
            'visha_ghati_duration_ghatis' => 4,
            'is_special_varjyam' => false,
            'has_dosha' => $hasDosha,
            'severity' => $hasDosha ? 'high' : 'none',
            'description' => $hasDosha
                ? Localization::translate('String', 'Varjyam (Visha Ghati)') . ' - ' . Localization::translate('Common', 'Inauspicious')
                : 'No Varjyam',
        ];
    }

    public static function calculateAmritaKaal(int $varaNumber, float $sunrise, float $sunset, float $nextSunrise, float $currentTime): array
    {
        return [
            'is_in_amrita_kaal' => false,
            'is_auspicious' => false,
            'description' => Localization::translate('String', 'Not in Amrita Kaal'),
        ];
    }

    public static function calculateAbhijitCancellation(float $sunrise, float $sunset, int $varaNumber, float $currentTime): array
    {
        $vara = Vara::from($varaNumber);
        $dayDurationSeconds = ($sunset - $sunrise) * ClassicalTimeConstants::SECONDS_PER_HOUR;
        $muhurtaDurationSeconds = $dayDurationSeconds / 15.0;

        $abhijitStart = $sunrise + (7 * $muhurtaDurationSeconds / 3600.0);
        $abhijitEnd = $abhijitStart + ($muhurtaDurationSeconds / 3600.0);

        $isInAbhijit = $currentTime >= $abhijitStart && $currentTime < $abhijitEnd;
        $isWednesday = $vara === Vara::Wednesday;
        $hasCancellationPower = $isInAbhijit && !$isWednesday;

        $note = $hasCancellationPower
            ? 'Abhijit Muhurta - High Dosha Cancellation Power'
            : ($isInAbhijit ? 'Abhijit power cancelled (Wednesday)' : 'Not in Abhijit Muhurta');

        return [
            'source' => Localization::translate('Source', 'Muhurta Chintamani / Muhurta Martanda'),
            'sunrise' => $sunrise,
            'sunset' => $sunset,
            'vara_number' => $varaNumber,
            'vara_name' => $vara->getName(),
            'current_time' => $currentTime,
            'abhijit_start' => $abhijitStart,
            'abhijit_end' => $abhijitEnd,
            'abhijit_duration_minutes' => $muhurtaDurationSeconds / 60.0,
            'muhurta_number' => Localization::translate('String', '8th of 15 (Abhijit)'),
            'is_in_abhijit' => $isInAbhijit,
            'is_wednesday' => $isWednesday,
            'has_cancellation_power' => $hasCancellationPower,
            'cancellation_note' => Localization::translate('String', $note),
            'cancellable_doshas' => [
                'rikta_tithi' => true,
                'nakshatra_dosha' => true,
                'yoga_dosha' => true,
                'karana_dosha' => true,
                'minor_graha_dosha' => true,
                'varjyam' => false,
                'grahan' => false,
            ],
            'description' => Localization::translate('String', $note),
        ];
    }

    public static function generateRejectionReport(array $evaluationResults): array
    {
        $rejections = [];
        $highSeverity = [];

        foreach ($evaluationResults as $factor => $result) {
            if (!is_array($result)) { continue; }
            if (isset($result['has_dosha']) && $result['has_dosha'] === true) {
                $rejection = [
                    'dosha_name' => $factor,
                    'severity' => $result['severity'] ?? 'medium',
                    'source' => Localization::translate('Source', 'Rule engine'),
                    'description' => $result['description'] ?? Localization::translate('Common', 'Inauspicious combination'),
                    'cancellation_possible' => false,
                    'cancellation_method' => null,
                ];
                $rejections[] = $rejection;
                if (($result['severity'] ?? '') === 'high' || ($result['severity'] ?? '') === 'critical') {
                    $highSeverity[] = $rejection;
                }
            }
        }

        $overallVerdict = count($rejections) > 0 ? 'rejected_but_can_try_remedies' : 'accepted';

        return [
            'source' => Localization::translate('Source', 'Transit-only evaluation'),
            'overall_verdict' => Localization::translate('Common', $overallVerdict),
            'confidence_level' => 'low',
            'rejection_count' => count($rejections),
            'warning_count' => 0,
            'acceptance_count' => count($rejections) === 0 ? 1 : 0,
            'critical_rejections' => [],
            'high_severity_rejections' => $highSeverity,
            'medium_severity_warnings' => [],
            'low_severity_acceptances' => [],
            'detailed_rejections' => $rejections,
            'detailed_warnings' => [],
            'detailed_acceptances' => [],
            'remedies_available' => false,
            'recommendation' => match ($overallVerdict) {
                'accepted' => Localization::translate('String', 'Muhurta is auspicious. Proceed with confidence.'),
                'rejected_but_can_try_remedies' => Localization::translate('String', 'Muhurta has significant doshas. Remedies may help but alternative preferred.')
            },
        ];
    }

    private static function normalizeTithiNumber(int $tithiNumber): int
    {
        $normalized = $tithiNumber % 30;
        return $normalized === 0 ? 30 : $normalized;
    }
}
