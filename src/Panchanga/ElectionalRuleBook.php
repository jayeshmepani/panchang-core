<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Panchanga;

/**
 * Electional Rule Book - General/Common Muhurta Rules.
 *
 * Contains universal Muhurta rules based on current transit positions.
 */
final class ElectionalRuleBook
{
    /**
     * Universal Bad Tithis (apply to everyone, no birth data needed).
     * Source: Muhurta Chintamani.
     */
    public const UNIVERSAL_BAD_TITHIS = [4, 9, 14, 29, 30];

    /**
     * Universal Bad Choghadiya (apply to everyone).
     * Source: Classical Choghadiya texts.
     */
    public const UNIVERSAL_BAD_CHOGADIYA = ['Kaal', 'Rog', 'Udveg'];

    /** Universal Good Choghadiya (apply to everyone). */
    public const UNIVERSAL_GOOD_CHOGADIYA = ['Amrit', 'Shubh', 'Labh'];

    /** Universal Good Horas (apply to everyone). */
    public const UNIVERSAL_GOOD_HORAS = ['Moon', 'Mercury', 'Jupiter', 'Venus'];

    /**
     * Universal Bad Yogas (apply to everyone).
     * Source: Classical Yoga texts.
     */
    public const UNIVERSAL_BAD_YOGAS = ['Vyatipata', 'Vaidhriti'];

    /** Universal Good Karanas (apply to everyone). */
    public const UNIVERSAL_GOOD_KARANAS = ['Bava', 'Balava', 'Kaulava', 'Taitila', 'Gara', 'Vanija'];

    /** Universal Bad Karanas (apply to everyone). */
    public const UNIVERSAL_BAD_KARANAS = ['Vishti', 'Shakuni', 'Chatushpada', 'Naga'];

    /**
     * Planetary combustion orbs (general transit-only rules).
     * Source: Classical astronomy texts.
     */
    public const COMBUSTION_ORBS = [
        'Moon' => 12.0,
        'Mars' => 17.0,
        'Mercury' => 14.0,
        'Jupiter' => 11.0,
        'Venus' => 10.0,
        'Saturn' => 15.0,
    ];

    /** Combustion orbs for retrograde planets. */
    public const COMBUSTION_ORBS_RETRO = [
        'Mercury' => 12.0,
        'Venus' => 8.0,
    ];

    /**
     * Vara-Tithi Yogas (general combinations, no birth data needed).
     * Source: Muhurta Chintamani.
     */
    public const VARA_TITHI_YOGAS = [
        'mrityu' => [
            0 => [1, 6, 11],
            2 => [1, 6, 11],
            1 => [2, 7, 12],
            5 => [2, 7, 12],
            3 => [3, 8, 13],
            4 => [4, 9, 14],
            6 => [5, 10, 15],
        ],
        'dagdha' => [
            0 => [12],
            1 => [11],
            2 => [5],
            3 => [3],
            4 => [6],
            5 => [8],
            6 => [9],
        ],
        'visha' => [
            0 => [4],
            1 => [6],
            2 => [7],
            3 => [2],
            4 => [8],
            5 => [9],
        ],
        'hutashana' => [
            0 => [12],
            1 => [6],
            2 => [7],
            3 => [8],
            4 => [9],
            5 => [10],
            6 => [11],
        ],
        'krakacha' => [
            0 => [12],
            1 => [11],
            2 => [10],
            3 => [9],
            4 => [8],
            5 => [7],
            6 => [6],
        ],
        'samvarta' => [
            0 => [7],
            3 => [1],
        ],
    ];

    /**
     * Activity profiles for general/common Muhurta (transit-only requirements).
     * All requirements are based on current transit positions only.
     */
    public const ACTIVITY_PROFILES = [
        'general_auspicious' => [
            'label' => 'General Auspicious Work',
            'sources' => ['Muhurta Chintamani', 'Brihat Samhita'],
            'block_high_doshas' => true,
            'minimum_duration_seconds' => 0,
            'forbidden_tithis' => self::UNIVERSAL_BAD_TITHIS,
            'forbidden_yogas' => self::UNIVERSAL_BAD_YOGAS,
            'preferred_karanas' => self::UNIVERSAL_GOOD_KARANAS,
            'forbidden_karanas' => self::UNIVERSAL_BAD_KARANAS,
            'forbidden_chogadiya' => self::UNIVERSAL_BAD_CHOGADIYA,
            'preferred_chogadiya' => self::UNIVERSAL_GOOD_CHOGADIYA,
            'preferred_horas' => self::UNIVERSAL_GOOD_HORAS,
        ],
        'vivaha' => [
            'label' => 'Vivaha (Marriage)',
            'sources' => ['Muhurta Chintamani', 'Brihat Samhita'],
            'block_high_doshas' => true,
            'minimum_duration_seconds' => 300,
            'forbidden_tithis' => [4, 6, 8, 9, 12, 14, 29, 30],
            'forbidden_yogas' => self::UNIVERSAL_BAD_YOGAS,
            'preferred_karanas' => self::UNIVERSAL_GOOD_KARANAS,
            'forbidden_karanas' => self::UNIVERSAL_BAD_KARANAS,
            'preferred_tithis' => [2, 3, 5, 7, 10, 11, 13],
            'preferred_weekdays' => [1, 3, 4, 5],
            'forbidden_weekdays' => [2, 6],
            'preferred_nakshatras' => ['Rohini', 'Mrigashira', 'Uttara Phalguni', 'Hasta', 'Swati', 'Anuradha', 'Uttara Ashadha', 'Uttara Bhadrapada', 'Revati'],
            'preferred_lagnas' => ['Vrishabha', 'Mithuna', 'Kanya', 'Tula', 'Dhanu', 'Meena'],
            'preferred_chogadiya' => self::UNIVERSAL_GOOD_CHOGADIYA,
            'preferred_horas' => ['Venus', 'Jupiter', 'Mercury', 'Moon'],
        ],
        'griha_pravesha' => [
            'label' => 'Griha Pravesha (House Entry)',
            'sources' => ['Muhurta Chintamani', 'Brihat Samhita', 'Mayamata'],
            'block_high_doshas' => true,
            'minimum_duration_seconds' => 300,
            'forbidden_tithis' => self::UNIVERSAL_BAD_TITHIS,
            'forbidden_yogas' => self::UNIVERSAL_BAD_YOGAS,
            'preferred_karanas' => self::UNIVERSAL_GOOD_KARANAS,
            'forbidden_karanas' => self::UNIVERSAL_BAD_KARANAS,
            'preferred_weekdays' => [1, 3, 4, 5],
            'forbidden_weekdays' => [2, 6],
            'preferred_nakshatras' => ['Ashwini', 'Rohini', 'Mrigashira', 'Punarvasu', 'Pushya', 'Uttara Phalguni', 'Hasta', 'Chitra', 'Swati', 'Anuradha', 'Uttara Ashadha', 'Shravana', 'Dhanishtha', 'Shatabhisha', 'Uttara Bhadrapada', 'Revati'],
            'preferred_lagnas' => ['Vrishabha', 'Mithuna', 'Kanya', 'Tula', 'Dhanu', 'Kumbha', 'Meena'],
            'preferred_chogadiya' => self::UNIVERSAL_GOOD_CHOGADIYA,
            'preferred_horas' => ['Jupiter', 'Venus', 'Mercury', 'Moon'],
        ],
        'bhumi_pujan' => [
            'label' => 'Bhumi Pujan / Shilanyasa',
            'sources' => ['Muhurta Chintamani', 'Brihat Samhita'],
            'block_high_doshas' => true,
            'minimum_duration_seconds' => 0,
            'forbidden_tithis' => self::UNIVERSAL_BAD_TITHIS,
            'forbidden_yogas' => self::UNIVERSAL_BAD_YOGAS,
            'preferred_karanas' => self::UNIVERSAL_GOOD_KARANAS,
            'forbidden_karanas' => self::UNIVERSAL_BAD_KARANAS,
            'preferred_weekdays' => [1, 3, 4, 5],
            'preferred_nakshatras' => ['Ashwini', 'Rohini', 'Mrigashira', 'Punarvasu', 'Pushya', 'Hasta', 'Chitra', 'Anuradha', 'Shravana', 'Dhanishtha', 'Revati'],
            'preferred_lagnas' => ['Vrishabha', 'Simha', 'Kanya', 'Vrischika', 'Makara', 'Kumbha'],
            'preferred_chogadiya' => self::UNIVERSAL_GOOD_CHOGADIYA,
            'preferred_horas' => ['Mars', 'Jupiter', 'Venus', 'Mercury'],
        ],
        'upanayana' => [
            'label' => 'Upanayana',
            'sources' => ['Muhurta Chintamani', 'Brihat Samhita', 'Ashvalayana Grihya Sutra'],
            'block_high_doshas' => true,
            'minimum_duration_seconds' => 0,
            'forbidden_tithis' => self::UNIVERSAL_BAD_TITHIS,
            'forbidden_yogas' => self::UNIVERSAL_BAD_YOGAS,
            'preferred_karanas' => self::UNIVERSAL_GOOD_KARANAS,
            'forbidden_karanas' => self::UNIVERSAL_BAD_KARANAS,
            'preferred_weekdays' => [1, 3, 4, 5],
            'preferred_nakshatras' => ['Ashwini', 'Rohini', 'Mrigashira', 'Punarvasu', 'Pushya', 'Hasta', 'Swati', 'Anuradha', 'Shravana', 'Revati'],
            'preferred_chogadiya' => self::UNIVERSAL_GOOD_CHOGADIYA,
            'preferred_horas' => ['Jupiter', 'Mercury', 'Moon'],
        ],
        'namakarana' => [
            'label' => 'Namakarana',
            'sources' => ['Muhurta Chintamani', 'Brihat Samhita', 'Ashvalayana Grihya Sutra'],
            'block_high_doshas' => true,
            'minimum_duration_seconds' => 0,
            'forbidden_tithis' => self::UNIVERSAL_BAD_TITHIS,
            'forbidden_yogas' => self::UNIVERSAL_BAD_YOGAS,
            'preferred_karanas' => self::UNIVERSAL_GOOD_KARANAS,
            'forbidden_karanas' => self::UNIVERSAL_BAD_KARANAS,
            'preferred_weekdays' => [1, 3, 4, 5],
            'preferred_nakshatras' => ['Ashwini', 'Rohini', 'Mrigashira', 'Punarvasu', 'Pushya', 'Hasta', 'Anuradha', 'Shravana', 'Dhanishtha', 'Revati'],
            'preferred_horas' => ['Mercury', 'Jupiter', 'Moon', 'Venus'],
            'preferred_chogadiya' => self::UNIVERSAL_GOOD_CHOGADIYA,
        ],
        'annaprashana' => [
            'label' => 'Annaprashana',
            'sources' => ['Muhurta Chintamani', 'Brihat Samhita', 'Ashvalayana Grihya Sutra'],
            'block_high_doshas' => true,
            'minimum_duration_seconds' => 0,
            'forbidden_tithis' => self::UNIVERSAL_BAD_TITHIS,
            'forbidden_yogas' => self::UNIVERSAL_BAD_YOGAS,
            'preferred_karanas' => self::UNIVERSAL_GOOD_KARANAS,
            'forbidden_karanas' => self::UNIVERSAL_BAD_KARANAS,
            'preferred_weekdays' => [1, 3, 4, 5],
            'preferred_nakshatras' => ['Ashwini', 'Rohini', 'Mrigashira', 'Punarvasu', 'Pushya', 'Hasta', 'Swati', 'Anuradha', 'Revati'],
            'preferred_horas' => ['Moon', 'Jupiter', 'Venus', 'Mercury'],
            'preferred_chogadiya' => self::UNIVERSAL_GOOD_CHOGADIYA,
        ],
        'mundan' => [
            'label' => 'Mundan / Chudakarana',
            'sources' => ['Muhurta Chintamani', 'Brihat Samhita', 'Ashvalayana Grihya Sutra'],
            'block_high_doshas' => true,
            'minimum_duration_seconds' => 0,
            'forbidden_tithis' => self::UNIVERSAL_BAD_TITHIS,
            'forbidden_yogas' => self::UNIVERSAL_BAD_YOGAS,
            'preferred_karanas' => self::UNIVERSAL_GOOD_KARANAS,
            'forbidden_karanas' => self::UNIVERSAL_BAD_KARANAS,
            'preferred_weekdays' => [1, 3, 4, 5],
            'preferred_nakshatras' => ['Ashwini', 'Mrigashira', 'Punarvasu', 'Pushya', 'Hasta', 'Chitra', 'Swati', 'Shravana', 'Dhanishtha', 'Revati'],
            'preferred_horas' => ['Moon', 'Jupiter', 'Venus'],
            'preferred_chogadiya' => self::UNIVERSAL_GOOD_CHOGADIYA,
        ],
        'aksharabhyasa' => [
            'label' => 'Aksharabhyasa / Vidyarambha',
            'sources' => ['Muhurta Chintamani', 'Brihat Samhita', 'Ashvalayana Grihya Sutra'],
            'block_high_doshas' => true,
            'minimum_duration_seconds' => 0,
            'forbidden_tithis' => self::UNIVERSAL_BAD_TITHIS,
            'forbidden_yogas' => self::UNIVERSAL_BAD_YOGAS,
            'preferred_karanas' => self::UNIVERSAL_GOOD_KARANAS,
            'forbidden_karanas' => self::UNIVERSAL_BAD_KARANAS,
            'preferred_weekdays' => [1, 3, 4, 5],
            'preferred_nakshatras' => ['Ashwini', 'Rohini', 'Mrigashira', 'Punarvasu', 'Pushya', 'Hasta', 'Swati', 'Anuradha', 'Shravana', 'Revati'],
            'preferred_horas' => ['Mercury', 'Jupiter', 'Venus', 'Moon'],
            'preferred_chogadiya' => self::UNIVERSAL_GOOD_CHOGADIYA,
        ],
        'karnavedha' => [
            'label' => 'Karnavedha',
            'sources' => ['Muhurta Chintamani', 'Brihat Samhita', 'Ashvalayana Grihya Sutra'],
            'block_high_doshas' => true,
            'minimum_duration_seconds' => 0,
            'forbidden_tithis' => self::UNIVERSAL_BAD_TITHIS,
            'forbidden_yogas' => self::UNIVERSAL_BAD_YOGAS,
            'preferred_karanas' => self::UNIVERSAL_GOOD_KARANAS,
            'forbidden_karanas' => self::UNIVERSAL_BAD_KARANAS,
            'preferred_weekdays' => [1, 3, 4, 5],
            'preferred_nakshatras' => ['Ashwini', 'Mrigashira', 'Punarvasu', 'Pushya', 'Hasta', 'Chitra', 'Swati', 'Shravana', 'Revati'],
            'preferred_horas' => ['Moon', 'Venus', 'Mercury'],
            'preferred_chogadiya' => self::UNIVERSAL_GOOD_CHOGADIYA,
        ],
        'nishkramana' => [
            'label' => 'Nishkramana',
            'sources' => ['Muhurta Chintamani', 'Brihat Samhita', 'Ashvalayana Grihya Sutra'],
            'block_high_doshas' => true,
            'minimum_duration_seconds' => 0,
            'forbidden_tithis' => self::UNIVERSAL_BAD_TITHIS,
            'forbidden_yogas' => self::UNIVERSAL_BAD_YOGAS,
            'preferred_karanas' => self::UNIVERSAL_GOOD_KARANAS,
            'forbidden_karanas' => self::UNIVERSAL_BAD_KARANAS,
            'preferred_weekdays' => [1, 3, 4, 5],
            'preferred_nakshatras' => ['Ashwini', 'Rohini', 'Punarvasu', 'Pushya', 'Hasta', 'Anuradha', 'Shravana', 'Revati'],
            'preferred_horas' => ['Moon', 'Mercury', 'Jupiter'],
            'preferred_chogadiya' => self::UNIVERSAL_GOOD_CHOGADIYA,
        ],
        'yatra' => [
            'label' => 'Yatra',
            'sources' => ['Muhurta Chintamani', 'Brihat Samhita'],
            'block_high_doshas' => true,
            'minimum_duration_seconds' => 0,
            'forbidden_tithis' => self::UNIVERSAL_BAD_TITHIS,
            'forbidden_yogas' => self::UNIVERSAL_BAD_YOGAS,
            'preferred_karanas' => self::UNIVERSAL_GOOD_KARANAS,
            'forbidden_karanas' => self::UNIVERSAL_BAD_KARANAS,
            'preferred_weekdays' => [1, 3, 4, 5],
            'preferred_nakshatras' => ['Ashwini', 'Punarvasu', 'Pushya', 'Hasta', 'Anuradha', 'Shravana', 'Dhanishtha', 'Revati'],
            'preferred_horas' => ['Mercury', 'Jupiter', 'Venus', 'Moon'],
            'preferred_chogadiya' => self::UNIVERSAL_GOOD_CHOGADIYA,
        ],
        'vyapara' => [
            'label' => 'Vyapara Arambha',
            'sources' => ['Muhurta Chintamani', 'Brihat Samhita'],
            'block_high_doshas' => true,
            'minimum_duration_seconds' => 0,
            'forbidden_tithis' => self::UNIVERSAL_BAD_TITHIS,
            'forbidden_yogas' => self::UNIVERSAL_BAD_YOGAS,
            'preferred_karanas' => self::UNIVERSAL_GOOD_KARANAS,
            'forbidden_karanas' => self::UNIVERSAL_BAD_KARANAS,
            'preferred_weekdays' => [1, 3, 4, 5],
            'preferred_nakshatras' => ['Ashwini', 'Rohini', 'Mrigashira', 'Punarvasu', 'Hasta', 'Chitra', 'Swati', 'Anuradha', 'Shravana', 'Revati'],
            'preferred_horas' => ['Mercury', 'Jupiter', 'Venus'],
            'preferred_chogadiya' => self::UNIVERSAL_GOOD_CHOGADIYA,
        ],
        'pratishtha' => [
            'label' => 'Pratishtha',
            'sources' => ['Muhurta Chintamani', 'Brihat Samhita', 'Vaikhanasa Agama'],
            'block_high_doshas' => true,
            'forbidden_tithis' => self::UNIVERSAL_BAD_TITHIS,
            'forbidden_yogas' => self::UNIVERSAL_BAD_YOGAS,
            'preferred_weekdays' => [1, 3, 4, 5],
            'preferred_nakshatras' => ['Ashwini', 'Rohini', 'Mrigashira', 'Punarvasu', 'Pushya', 'Hasta', 'Chitra', 'Swati', 'Anuradha', 'Shravana', 'Uttara Bhadrapada', 'Revati'],
            'preferred_horas' => ['Jupiter', 'Venus', 'Mercury', 'Moon'],
            'preferred_chogadiya' => self::UNIVERSAL_GOOD_CHOGADIYA,
        ],
    ];

    /**
     * Get activity profile by name.
     *
     * @param string $activity Activity name
     *
     * @return array Activity profile
     */
    public static function getProfile(string $activity): array
    {
        $key = self::normalizeToken($activity);

        foreach (self::ACTIVITY_PROFILES as $name => $profile) {
            if (self::normalizeToken($name) === $key) {
                return $profile + ['activity_key' => $name];
            }
        }

        return self::ACTIVITY_PROFILES['general_auspicious'] + ['activity_key' => 'general_auspicious'];
    }

    /**
     * Normalize activity/token name for comparison.
     *
     * @param string $value Value to normalize
     *
     * @return string Normalized value
     */
    public static function normalizeToken(string $value): string
    {
        $transliterated = strtr(trim($value), [
            'Ā' => 'A', 'ā' => 'a',
            'Ī' => 'I', 'ī' => 'i',
            'Ū' => 'U', 'ū' => 'u',
            'Ṛ' => 'Ri', 'ṛ' => 'ri',
            'Ḍ' => 'D', 'ḍ' => 'd',
            'Ṭ' => 'T', 'ṭ' => 't',
            'Ṅ' => 'N', 'ṅ' => 'n',
            'Ñ' => 'N', 'ñ' => 'n',
            'Ṇ' => 'N', 'ṇ' => 'n',
            'Ś' => 'Sh', 'ś' => 'sh',
            'Ṣ' => 'Sh', 'ṣ' => 'sh',
            'Ḥ' => 'H', 'ḥ' => 'h',
            'ṁ' => 'm', 'ṃ' => 'm',
            ' ' => '', '-' => '', '_' => '',
        ]);

        return strtolower(preg_replace('/[^A-Za-z0-9]/', '', $transliterated) ?? '');
    }
}
