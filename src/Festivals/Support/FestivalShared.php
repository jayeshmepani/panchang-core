<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Festivals\Support;

use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Core\Localization;

/**
 * Shared pure helpers for festival catalog resolution and presentation.
 *
 * Single source of truth for text normalization, rule classifiers, and
 * calendar paksha selection — previously duplicated across RuleEngine and Service traits.
 *
 * Algorithms are moved as-is from FestivalRuleEngine (canonical classical path).
 */
final class FestivalShared
{
    public const array SUPPORTED_KARMAKALA_TYPES = [
        'abhijit',
        'aparahna',
        'arunodaya',
        'chandra_darshana_visibility',
        'daytime',
        'madhyahna',
        'moonrise',
        'nishitha',
        'pradosha',
        'prathama_ratri',
        'pratah_first_third',
        'pratah_kal',
        'purvahna',
        'ratri',
        'sangava',
        'sayankala',
        'sunrise',
        'sunset',
        'tithi_boundary',
        'usha',
        'vijaya_kaal',
    ];

    public const array NAKSHATRA_NUMBERS = [
        'Ashwini' => 1,
        'Bharani' => 2,
        'Krittika' => 3,
        'Rohini' => 4,
        'Mrigashira' => 5,
        'Ardra' => 6,
        'Punarvasu' => 7,
        'Pushya' => 8,
        'Ashlesha' => 9,
        'Magha' => 10,
        'Purva Phalguni' => 11,
        'Uttara Phalguni' => 12,
        'Hasta' => 13,
        'Chitra' => 14,
        'Swati' => 15,
        'Vishakha' => 16,
        'Anuradha' => 17,
        'Jyeshtha' => 18,
        'Mula' => 19,
        'Purva Ashadha' => 20,
        'Uttara Ashadha' => 21,
        'Shravana' => 22,
        'Dhanishta' => 23,
        'Shatabhisha' => 24,
        'Purva Bhadrapada' => 25,
        'Uttara Bhadrapada' => 26,
        'Revati' => 27,
    ];

    /** Normalize Sanskrit month names for robust matching across ASCII and diacritic forms. */
    public static function normalizeMonthName(string $month): string
    {
        $month = trim($month);
        if ($month === '') {
            return '';
        }

        // Strip parenthetical suffixes like "(Adhika)", "(Kshaya)"
        $month = preg_replace('/\s*\(.*?\)\s*/', '', $month) ?? $month;

        $transliterated = strtr($month, [
            'Ā' => 'A', 'ā' => 'a',
            'Ī' => 'I', 'ī' => 'i',
            'Ū' => 'U', 'ū' => 'u',
            'Ṛ' => 'Ri', 'ṛ' => 'ri',
            'Ṝ' => 'Ri', 'ṝ' => 'ri',
            'Ḷ' => 'Li', 'ḷ' => 'li',
            'Ḍ' => 'D', 'ḍ' => 'd',
            'Ṭ' => 'T', 'ṭ' => 't',
            'Ṅ' => 'N', 'ṅ' => 'n',
            'Ñ' => 'N', 'ñ' => 'n',
            'Ṇ' => 'N', 'ṇ' => 'n',
            'Ś' => 'Sh', 'ś' => 'sh',
            'Ṣ' => 'Sh', 'ṣ' => 'sh',
            'Ḥ' => 'H', 'ḥ' => 'h',
            'ṁ' => 'm', 'ṃ' => 'm',
        ]);

        $asciiOnly = preg_replace('/[^A-Za-z]/', '', $transliterated) ?? '';

        $normalized = strtolower($asciiOnly);

        return match ($normalized) {
            'ashwin', 'ashwina' => 'ashvina',
            default => $normalized,
        };
    }

    /** Normalize free-text labels (ASCII/Unicode) for robust equality checks. */
    public static function normalizeLabel(string $label): string
    {
        $label = trim($label);
        if ($label === '') {
            return '';
        }

        $label = preg_replace('/\s*\(.*?\)\s*/u', '', $label) ?? $label;
        $label = function_exists('mb_strtolower') ? mb_strtolower($label, 'UTF-8') : strtolower($label);

        // Keep letters across all scripts (Latin + Indic) and remove separators/punctuation.
        return preg_replace('/[^\p{L}]+/u', '', $label) ?? '';
    }

    public static function normalizeKarmakalaType(string $type): string
    {
        return match ($type) {
            'nishita', 'midnight' => 'nishitha',
            'sayahna' => 'sayankala',
            'pratah' => 'pratah_kal',
            'ratri_kal', 'ratri_kala', 'ratrikala' => 'ratri',
            'ushah', 'ushakala', 'usha_kala' => 'usha',
            default => $type,
        };
    }

    public static function isEkadashiNirnayRule(array $rule): bool
    {
        return (int) ($rule['tithi'] ?? 0) === 11
            && ((bool) ($rule['ekadashi_nirnay_table'] ?? false) || (bool) ($rule['require_vaishnava_ekadashi_today'] ?? false));
    }

    public static function isPradoshRule(array $rule): bool
    {
        return (int) ($rule['tithi'] ?? 0) === 13
            && self::normalizeKarmakalaType((string) ($rule['karmakala_type'] ?? '')) === 'pradosha'
            && (bool) ($rule['fasting'] ?? false);
    }

    public static function isSankashtiRule(array $rule): bool
    {
        return (int) ($rule['tithi'] ?? 0) === 4
            && (string) ($rule['paksha'] ?? '') === 'Krishna'
            && self::normalizeKarmakalaType((string) ($rule['karmakala_type'] ?? '')) === 'moonrise'
            && str_contains(strtolower((string) ($rule['description'] ?? '')), 'sankashti');
    }

    /** Resolve paksha constraint for a rule under the active calendar system. */
    public static function resolveRulePaksha(array $rule, array $calendar, string $fallbackPaksha = 'Shukla'): array|string
    {
        $calendarType = strtolower((string) ($calendar['Calendar_Type'] ?? AstroCore::getConfig('panchang.defaults.calendar_type', 'amanta')));
        if ($calendarType === 'purnimanta' && isset($rule['paksha_purnimanta'])) {
            return $rule['paksha_purnimanta'];
        }

        if ($calendarType !== 'purnimanta' && isset($rule['paksha_amanta'])) {
            return $rule['paksha_amanta'];
        }

        return $rule['paksha'] ?? $fallbackPaksha;
    }

    /**
     * Resolve canonical nakshatra number (1..27) from a localized/english label.
     * Canonical classical implementation (FestivalRuleEngine).
     */
    public static function resolveNakshatraNumber(string $label): ?int
    {
        $labelNorm = self::normalizeLabel($label);
        if ($labelNorm === '') {
            return null;
        }

        foreach (self::NAKSHATRA_NUMBERS as $name => $number) {
            if (self::normalizeLabel($name) === $labelNorm) {
                return $number;
            }
        }

        foreach (['en', 'hi', 'gu'] as $locale) {
            for ($idx = 0; $idx < 27; $idx++) {
                $translated = Localization::translate('Nakshatra', $idx, $locale);
                if (self::normalizeLabel($translated) === $labelNorm) {
                    return $idx + 1;
                }
            }
        }

        return null;
    }
}
