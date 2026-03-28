<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Core\Constants;

/**
 * Time measurement and calendrical constants used by the package.
 *
 * Notes on provenance:
 * - Some constants are modern arithmetic identities (seconds/day, minutes/hour).
 * - Some are standard classical Jyotisha conventions used across Panchang calculation
 *   (ghaṭikā, muhūrta, tithi span, nakṣatra span).
 * - Some are package-level traditional reference values used to support festival or
 *   muhurta logic, where practice can vary by tradition.
 */
final readonly class ClassicalTimeConstants
{
    // ===== PRIMARY TIME UNITS =====

    /**
     * Seconds per day (exact)
     * Source: Modern SI definition.
     */
    public const float SECONDS_PER_DAY = 86400.0;

    /**
     * Minutes per day (exact)
     * Source: Modern definition.
     */
    public const float MINUTES_PER_DAY = 1440.0;

    /** Seconds per hour (exact) */
    public const float SECONDS_PER_HOUR = 3600.0;

    /** Minutes per hour (exact) */
    public const float MINUTES_PER_HOUR = 60.0;

    /** Seconds per minute (exact) */
    public const float SECONDS_PER_MINUTE = 60.0;

    // ===== VEDIC TIME UNITS =====

    /**
     * Ghaṭikā (also called Nāḍikā) in minutes.
     *
     * Classical Definition:
     * - 1 ghaṭikā = 24 minutes (exact)
     * - 60 ghaṭikās = 1 day (Sūrya Siddhānta 1.11)
     *
     * Classical convention used in Jyotisha/Panchang literature.
     */
    public const float GHATIKA_IN_MINUTES = 24.0;

    /**
     * Ghaṭikā in days (exact fraction)
     * 1/60 day = 0.016666... days.
     */
    public const float GHATIKA_PER_DAY = 1.0 / 60.0;

    /**
     * Pala (also called Viṇāḍikā) in seconds.
     *
     * Classical Definition:
     * - 1 pala = 24 seconds (exact)
     * - 60 palas = 1 ghaṭikā (Sūrya Siddhānta 1.11)
     *
     * Classical convention used in Jyotisha/Panchang literature.
     */
    public const float PALA_IN_SECONDS = 24.0;

    /**
     * Muhūrta in minutes.
     *
     * Classical Definition:
     * - 1 muhūrta = 2 ghaṭikās = 48 minutes (exact)
     * - 30 muhūrtas = 1 day (Sūrya Siddhānta 1.10)
     *
     * Classical convention used in Jyotisha/Panchang literature.
     */
    public const float MUHURTA_IN_MINUTES = 48.0;

    /**
     * Muhūrta in days (exact fraction)
     * 1/30 day = 0.033333... days.
     */
    public const float MUHURTA_PER_DAY = 1.0 / 30.0;

    // ===== KARMAKALA PERIODS =====

    /**
     * Aruṇodaya duration in minutes.
     *
     * Classical Definition:
     * - Aruṇodaya = 4 ghaṭikās before sunrise = 96 minutes (exact)
     * - "Aruṇodaya is the dawn when Aruṇa (sun's charioteer) appears"
     *
     * Traditional dawn offset used by this package for Aruṇodaya-sensitive festival logic.
     * Usage in fasting logic is tradition-dependent.
     */
    public const float ARUNODAYA_MINUTES = 96.0;

    /** Aruṇodaya in days (exact fraction) */
    public const float ARUNODAYA_PER_DAY = self::ARUNODAYA_MINUTES / self::MINUTES_PER_DAY;

    /**
     * Pradoṣa duration in minutes.
     *
     * Classical Definition:
     * - Pradoṣa = 3 ghaṭikās after sunset = 72 minutes (exact)
     * - "Pradoṣa is most auspicious for Śiva worship"
     *
     * Traditional Pradoṣa window reference used by package festival logic.
     * Usage may vary across ritual contexts.
     */
    public const float PRADOSHA_MINUTES = 72.0;

    /** Pradoṣa in days (exact fraction) */
    public const float PRADOSHA_PER_DAY = self::PRADOSHA_MINUTES / self::MINUTES_PER_DAY;

    /**
     * Brahma Muhūrta duration in minutes.
     *
     * Classical Definition:
     * - Brahma Muhūrta = 2 muhūrtas before sunrise = 96 minutes (exact)
     * - "Brahma Muhūrta is most auspicious for Vedic study and meditation"
     *
     * Traditional Brahma Muhūrta reference used by the package.
     * Usage contexts vary across ritual, Ayurvedic, and study traditions.
     */
    public const float BRAHMA_MUHURTA_MINUTES = 96.0;

    /**
     * Brahma Muhūrta offset before sunrise in minutes
     * Ends 1 muhūrta (48 minutes) before sunrise.
     */
    public const float BRAHMA_MUHURTA_OFFSET_MINUTES = 96.0;

    // ===== ASTRONOMICAL CONSTANTS =====

    /**
     * Degrees per zodiac sign (exact)
     * 360° / 12 signs = 30° per sign.
     */
    public const float DEGREES_PER_SIGN = 30.0;

    /**
     * Degrees per nakṣatra (exact)
     * 360° / 27 nakṣatras = 13.333...° per nakṣatra.
     *
     * Standard Panchang convention.
     */
    public const float DEGREES_PER_NAKSHATRA = 360.0 / 27.0;

    /**
     * Degrees per tithi (exact)
     * Moon-Sun longitude difference for one tithi.
     *
     * Standard Panchang convention.
     */
    public const float DEGREES_PER_TITHI = 12.0;

    /** Tithis per pakṣa (fortnight) */
    public const int TITHIS_PER_PAKSHA = 15;

    /** Nakṣatras in full zodiac */
    public const int NAKSHATRAS_TOTAL = 27;

    /** Zodiac signs in full zodiac */
    public const int SIGNS_TOTAL = 12;

    /** Full circle in degrees */
    public const float CIRCLE_DEGREES = 360.0;

    // ===== PRECISION CONSTANTS =====

    /**
     * Julian Day epsilon for comparisons.
     *
     * Precision: 1e-12 days = ~0.000086 seconds
     * This keeps internal comparison noise very small.
     */
    public const float JD_EPSILON = 1.0e-12;

    /**
     * Binary search iterations for JD refinement.
     *
     * 80 iterations gives precision of ~1e-24 JD
     * (theoretical limit, practical limit is Swiss Ephemeris accuracy)
     */
    public const int BINARY_SEARCH_ITERATIONS = 80;

    /** Degrees epsilon for angular comparisons */
    public const float DEGREE_EPSILON = 1.0e-10;

    // ===== SAMVATSARA CONSTANTS =====

    /**
     * Years in Jupiter cycle
     * Jupiter completes 12 orbits in ~141.6 years
     * Used for Saṃvatsara calculation.
     */
    public const int JUPITER_CYCLE_YEARS = 60;

    /**
     * Kali Yuga epoch offset for Vikrama Saṃvat
     * Vikrama Saṃvat + 3044 = Kali year.
     */
    public const int KALI_ERA_OFFSET = 3044;

    /**
     * Vikrama Saṃvat epoch (57 BCE)
     * Used for Saṃvat calculations.
     */
    public const int VIKRAMA_ERA_OFFSET = 57;

    /**
     * Śaka era epoch (78 CE)
     * Used for Indian national calendar.
     */
    public const int SHAKA_ERA_OFFSET = 78;

    // ===== FESTIVAL THRESHOLDS =====

    /**
     * Bhadra tithis — reference constant for documentation.
     *
     * Traditional reference list used for documentation/supporting logic.
     * Bhadra (Vishti Karana) occurs on these absolute tithi numbers:
     * - Shukla: 4, 8, 11, 15
     * - Krishna: 3 (=18), 7 (=22), 10 (=25), 14 (=29)
     *
     * NOTE: Live Bhadra detection in PanchangService::findBhadraPeriods()
     * uses ephemeris-computed Vishti Karana indices [8,15,22,29,36,43,50,57]
     * derived from actual Moon-Sun angular separation, not this constant.
     */
    public const array BHADRA_TITHIS = [4, 8, 11, 15, 18, 22, 25, 29];

    /** Ekadashi tithi number */
    public const int EKADASHI_TITHI = 11;

    /** Chaturdashi tithi number */
    public const int CHATURDASHI_TITHI = 14;

    /** Purnima/Amavasya tithi number */
    public const int PURNIMA_TITHI = 15;

    // ===== SCORING WEIGHTS =====

    /**
     * Score weight for tithi at karmakala (optimal)
     * Highest priority - festival should be at sacred time.
     */
    public const int SCORE_TITHI_AT_KARMAKALA = 1000;

    /**
     * Score weight for tithi at sunrise
     * Second priority - traditional time for most vratas.
     */
    public const int SCORE_TITHI_AT_SUNRISE = 700;

    /**
     * Score weight for tithi during day
     * Third priority - day-time presence.
     */
    public const int SCORE_TITHI_DURING_DAY = 300;

    /**
     * Score weight for tithi during night
     * For night festivals like Shivaratri.
     */
    public const int SCORE_TITHI_DURING_NIGHT = 250;

    /**
     * Score weight for weekday match
     * For festivals with weekday requirements (e.g., Hanuman Jayanti on Tuesday).
     */
    public const int SCORE_WEEKDAY_MATCH = 150;

    /**
     * Score weight for nakṣatra match
     * For festivals with nakṣatra requirements (e.g., Janmashtami + Rohini).
     */
    public const int SCORE_NAKSHATRA_MATCH = 125;

    /** Score bonus for vṛddhi first day preference */
    public const int SCORE_VRIDDHI_FIRST_DAY = 50;

    /** Score penalty for kṣaya tithi */
    public const int SCORE_KSHAYA_PENALTY = 200;

    /** Prevent instantiation */
    private function __construct() {}
}
