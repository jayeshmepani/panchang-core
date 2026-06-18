<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Core\Enums;

use JayeshMepani\PanchangCore\Core\Localization;

/**
 * Muhūrta Devatā / Adhipati Enumeration (Classical 30-Devatā System).
 *
 * Primary Sources:
 * - Nārada Saṃhitā, Muhūrtādhyāya (with minor recensional variations)
 * - Kāśyapa, as quoted in Vṛddha Vasiṣṭha Saṃhitā, Muhūrtādhyāya
 *
 * This implementation follows the recension where the 8th daytime Muhūrta is **Vidhātṛ**
 * (some editions of Nārada Saṃhitā list **Abhijit** in this position instead).
 *
 * Note:
 * - This is the classical Jyotiṣa Devatā-adhipati list.
 * - It is different from the older qualitative naming system in Taittirīya Brāhmaṇa 3.10.1.
 * - Symbolic/cosmic interpretations (e.g., last two Muhūrtas belonging to Brahma & Savitur)
 *   belong to a separate interpretive layer (see Sanjay Rath and related modern traditional sources).
 *
 * Base Quality vs Weekday Durmuhūrta:
 * - Base Muhūrta Quality (getBaseQuality / isBaseAuspicious) is fixed from the Muhūrtādhyāya
 *   tradition: specific tyājya/pāpa and krūra muhūrtas are marked Inauspicious; the rest are
 *   generally Auspicious. This is independent of weekday.
 * - Weekday Durmuhūrta (getWeekdayDurMuhurtaNames) is a separate overlay (see method).
 * - Final decision in practice should combine base quality + weekday dur + full pañcāṅga context.
 *
 * See docs/MUHURTA_TEXT_SOURCES.md for full source classification, exact ślokas, and the
 * Base Quality vs Durmuhūrta explanation.
 */
enum Muhurta: int
{
    // Day Muhurtas — Nārada Saṃhitā 9.1–3 / Kāśyapa
    case Rudra = 0;       // रुद्र
    case Ahi = 1;         // आहि
    case Mitra = 2;       // मित्र
    case Pitri = 3;       // पित्र्य
    case Vasu = 4;        // वसु
    case Udaka = 5;       // उदक
    case Vishvedeva = 6;  // विश्वे
    case Vidhatr = 7;     // विधातृ
    case Brahma = 8;      // ब्रह्म
    case Indra = 9;       // इन्द्र
    case Indragni = 10;   // इन्द्राग्नि
    case Nirriti = 11;    // निऋति
    case Toyapa = 12;     // तोयप
    case Aryaman = 13;    // अर्यमा
    case Bhaga = 14;      // भग

    // Night Muhurtas — Nārada Saṃhitā 9.3–5 / Kāśyapa
    case Isha = 15;        // ईश
    case Ajapada = 16;     // अजपाद
    case Ahirbudhnya = 17; // अहिर्बुध्न्य
    case Pusha = 18;       // पूषा
    case Ashvini = 19;     // अश्विनौ
    case Yama = 20;        // यम
    case Vahni = 21;       // वह्नि
    case Dhatr = 22;       // धातृ
    case Chandra = 23;     // चन्द्र
    case Aditi = 24;       // अदिति
    case Ijya = 25;        // इज्य
    case Vishnu = 26;      // विष्णु
    case Arka = 27;        // अर्क
    case Tvashtr = 28;     // त्वष्टृ
    case Vayu = 29;        // वायु

    /**
     * Complete explicit base quality map (MuhurtaQuality).
     *
     * Source: Muhūrtādhyāya tradition (Nārada Saṃhitā + Kāśyapa as quoted in Vṛddha Vasiṣṭha Saṃhitā)
     *
     * Day tyājya/pāpa muhūrtas:
     *   Rudra, Ahi/Sarpa, Pitri, Indragni, Nirriti/Daitya, Bhaga
     *
     * Night tyājya/krūra muhūrtas:
     *   Isha/Raudra, Ajapada, Yama, Vahni/Agni
     *
     * All remaining muhūrtas are treated as generally śubha.
     *
     * Note:
     * - Weekday-based Durmuhūrta rules are kept separate (see getWeekdayDurMuhurtaNames).
     * - Final auspiciousness must also consider full pañcāṅga context.
     */
    /**
     * Complete base quality map (the canonical MuhurtaQuality definition).
     * Public so callers can audit the exact classical rule without instantiation.
     *
     * @var array<int, 'Auspicious'|'Inauspicious'>
     */
    public const array MUHURTA_BASE_QUALITY = [
        // Day Muhurtas
        0  => 'Inauspicious', // Rudra
        1  => 'Inauspicious', // Ahi
        2  => 'Auspicious',   // Mitra
        3  => 'Inauspicious', // Pitri
        4  => 'Auspicious',   // Vasu
        5  => 'Auspicious',   // Udaka
        6  => 'Auspicious',   // Vishvedeva
        7  => 'Auspicious',   // Vidhatr
        8  => 'Auspicious',   // Brahma
        9  => 'Auspicious',   // Indra
        10 => 'Inauspicious', // Indragni
        11 => 'Inauspicious', // Nirriti
        12 => 'Auspicious',   // Toyapa
        13 => 'Auspicious',   // Aryaman
        14 => 'Inauspicious', // Bhaga

        // Night Muhurtas
        15 => 'Inauspicious', // Isha / Raudra
        16 => 'Inauspicious', // Ajapada
        17 => 'Auspicious',   // Ahirbudhnya
        18 => 'Auspicious',   // Pusha
        19 => 'Auspicious',   // Ashvini
        20 => 'Inauspicious', // Yama
        21 => 'Inauspicious', // Vahni / Agni
        22 => 'Auspicious',   // Dhatr
        23 => 'Auspicious',   // Chandra
        24 => 'Auspicious',   // Aditi
        25 => 'Auspicious',   // Ijya
        26 => 'Auspicious',   // Vishnu
        27 => 'Auspicious',   // Arka
        28 => 'Auspicious',   // Tvashtr
        29 => 'Auspicious',   // Vayu
    ];

    public function getName(?string $locale = null): string
    {
        return Localization::translate('Muhurta', $this->value, $locale);
    }

    public function getBaseQuality(?string $locale = null): string
    {
        $key = $this->getBaseQualityKey();
        return Localization::translate('Common', $key, $locale);
    }

    public function isBaseAuspicious(): bool
    {
        return $this->getBaseQualityKey() === 'Auspicious';
    }

    /**
     * Returns the complete explicit Muhurta base quality map (index => quality).
     * This is the canonical MuhurtaQuality array used by the engine.
     *
     * @return array<int, 'Auspicious'|'Inauspicious'>
     */
    public static function getMuhurtaBaseQualityMap(): array
    {
        return self::MUHURTA_BASE_QUALITY;
    }

    /**
     * Weekday Durmuhūrta indexes (0-based enum values, separate from base quality).
     *
     * Source: Nārada Saṃhitā (and parallel in Muhūrta Chintāmaṇi / Karmakāṇḍa):
     *   Sunday: Aryamā (14th muhūrta, index 13)
     *   Monday: Rākṣasa/Nirṛti + Brahmā (12th + 9th, indexes 11, 8)
     *   Tuesday: Pitṛ + Agni/Vahni (4th + 22nd overall, indexes 3, 21)
     *   Wednesday: Abhijit / Vidhātṛ / Vidhi (8th muhūrta, index 7)
     *   Thursday: Rākṣasa + Udaka/Jala (12th + 6th, indexes 11, 5)
     *   Friday: Brahmā + Pitṛ (9th + 4th, indexes 8, 3)
     *   Saturday: Sarpa/Ahi + Īśa/Śiva (2nd + 16th overall, indexes 1, 15)
     *
     * Use these exact indexes for Durmuhūrta filtering (more reliable than names or 1-based labels).
     *
     * @return list<int>
     */
    public static function getWeekdayDurMuhurtaIndexes(int $varaIndex): array
    {
        $map = [
            0 => [13],      // Sunday: Aryaman (14th)
            1 => [11, 8],   // Monday: Nirriti + Brahma (12th, 9th)
            2 => [3, 21],   // Tuesday: Pitri + Vahni (4th, 22nd overall)
            3 => [7],       // Wednesday: Vidhatr / Abhijit (8th)
            4 => [11, 5],   // Thursday: Nirriti + Udaka (12th, 6th)
            5 => [8, 3],    // Friday: Brahma + Pitri (9th, 4th)
            6 => [1, 15],   // Saturday: Ahi + Isha (2nd, 16th overall)
        ];
        return $map[$varaIndex] ?? [];
    }

    /**
     * Weekday Durmuhūrta names (derived from indexes, for readability).
     * See getWeekdayDurMuhurtaIndexes() for the authoritative 0-based schedule.
     *
     * @return list<string>
     */
    public static function getWeekdayDurMuhurtaNames(int $varaIndex): array
    {
        $indexes = self::getWeekdayDurMuhurtaIndexes($varaIndex);
        return array_map(
            fn (int $idx): string => self::from($idx)->getName(),
            $indexes
        );
    }

    /**
     * Get cross-recensional names directly attested in the quoted Sanskrit
     * source material documented in docs/MUHURTA_TEXT_SOURCES.md.
     *
     * @return list<string>
     */
    public function getAliases(): array
    {
        // Aliases strictly derived from the quoted Sanskrit ślokas and mnemonics
        // in docs/MUHURTA_TEXT_SOURCES.md (Nārada Saṃhitā, Kāśyapa/Vṛddha Vasiṣṭha,
        // Vṛddha variant, Karmakāṇḍa digests, Bṛhat-saṃhitā mnemonics).
        // No aliases from Taittirīya Brāhmaṇa (different taxonomy) or general time-ladder texts.
        return match ($this) {
            self::Rudra => ['Shiva'],                              // शिव (Vṛddha Vasiṣṭha variant)
            self::Ahi => ['Sarpa', 'Uraga'],                       // सर्प (variant), उरग (Karmakāṇḍa)
            self::Mitra => [],                                     // direct in sources
            self::Pitri => ['Pitrya', 'Pitara'],                   // पित्र्य (Karmakāṇḍa), पितरो (Bṛhat)
            self::Vasu => ['Vasava'],                              // वसवो (multiple)
            self::Udaka => ['Jala'],                               // जल (Vṛddha variant), also Vāri/Āpya in digests
            self::Vishvedeva => ['Vishve', 'Vishvadeva'],          // विश्वे (Nārada), विश्वदेवा (Bṛhat)
            self::Vidhatr => ['Vidhi', 'Vidhata'],                 // विधि (Karmakāṇḍa), विधाता (Vṛddha)
            self::Brahma => ['Vairinchi'],                         // वैरिञ्चिः (Kāśyapa)
            self::Indra => ['Shakra', 'Satakratu'],                // शक्र (Kāśyapa), शतक्रतुः (Bṛhat)
            self::Indragni => [],                                  // direct
            self::Nirriti => ['Nirrti', 'Daitya', 'Asura', 'Shambara', 'Rakshasa'], // निऋति, दैत्य, असुर, शम्बर, राक्षस (from explicit listings in Vṛddha Vasiṣṭha editions and digests)
            self::Toyapa => ['Varuna', 'Ambupa', 'Apya'],          // वरुण (Karmakāṇḍa/Bṛhat), अम्बुपा (Kāśyapa), अप्य (Karmakāṇḍa)
            self::Aryaman => [],                                   // direct
            self::Bhaga => [],                                     // direct

            // Night
            self::Isha => ['Ishvara'],                             // ईश / ईश्वर (Bṛhat, Vṛddha)
            self::Ajapada => [],                                   // direct
            self::Ahirbudhnya => [],                               // direct
            self::Pusha => [],                                     // direct (Pūṣā)
            self::Ashvini => ['Nasatya', 'Ashvinau'],              // नासत्य (Karmakāṇḍa), अश्विनौ (Bṛhat)
            self::Yama => ['Antaka'],                              // अन्तक (Karmakāṇḍa)
            self::Vahni => ['Agni'],                               // वह्नि / अग्नि (Karmakāṇḍa, Bṛhat)
            self::Dhatr => [],                                     // direct
            self::Chandra => ['Shashi'],                           // चन्द्र / शशिन (Karmakāṇḍa)
            self::Aditi => ['Aditya'],                             // आदित्य (Bṛhat "आदित्यो", compounds in Nārada/Kāśyapa)
            self::Ijya => ['Jiva'],                                // इज्य / जीव (Karmakāṇḍa, Bṛhat)
            self::Vishnu => ['Acyuta'],                            // विष्णु / अच्युत (Karmakāṇḍa)
            self::Arka => [],                                      // direct
            self::Tvashtr => ['Tvashta', 'Tvasta'],                // त्वष्टृ / त्वष्टा (Bṛhat)
            self::Vayu => ['Samirana'],                            // वायु / समीरण (Karmakāṇḍa)
        };
    }

    /**
     * Get day sequence: 15 muhurtas from sunrise to sunset.
     *
     * @return array<int, self>
     */
    public static function getDaySequence(): array
    {
        return array_slice(self::cases(), 0, 15);
    }

    /**
     * Get night sequence: 15 muhurtas from sunset to next sunrise.
     *
     * @return array<int, self>
     */
    public static function getNightSequence(): array
    {
        return array_slice(self::cases(), 15, 15);
    }

    /**
     * Base Muhūrta Quality (Auspicious / Inauspicious).
     *
     * Source: Muhūrtādhyāya tradition (Nārada Saṃhitā + Kāśyapa as quoted in Vṛddha Vasiṣṭha Saṃhitā)
     *
     * Day tyājya/pāpa muhūrtas:
     *   Rudra, Ahi/Sarpa, Pitri, Indragni, Nirriti/Daitya, Bhaga
     *
     * Night tyājya/krūra muhūrtas:
     *   Isha/Raudra, Ajapada, Yama, Vahni/Agni
     *
     * All remaining muhūrtas are treated as generally śubha.
     *
     * Note:
     * - Weekday-based Durmuhūrta rules are kept separate (see getWeekdayDurMuhurtaNames).
     * - Final auspiciousness must also consider full pañcāṅga context.
     */
    /** Returns the base quality key in English for internal logic. */
    private function getBaseQualityKey(): string
    {
        return self::MUHURTA_BASE_QUALITY[$this->value];
    }
}
