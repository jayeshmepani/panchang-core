<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Festivals;

/**
 * Classical festival-rule numeric thresholds.
 *
 * Kept as a dedicated constants holder so truth-table traits can share values without scattering
 * magic numbers or orphaning documentary comments on the façade.
 *
 * Values are unchanged from the original FestivalRuleEngine constants.
 *
 * @see docs/FESTIVAL_REFACTOR_PARITY_CONTRACT.md
 */
final class FestivalRuleConstants
{
    /**
     * Raksha Bandhan: require Purnima to span at least this many dinamana-muhurtas past the
     * next day's sunrise to keep the udaya-Purnima day; otherwise fall back to the previous day.
     */
    public const float RAKSHA_BANDHAN_UDAYA_PURNIMA_THRESHOLD_MUHURTAS = 3.0;

    /**
     * Govardhan/Annakut Pratipada thresholds from the Bali-puja nirnaya tradition.
     *
     * Dharma Sindhu says a sunrise Pratipada lasting 10 dinamana-muhurtas carries all rites
     * on the later Pratipada day, while Govardhan/Gokrida/Bali rites fall back to the previous
     * Amavasya-viddha Pratipada when even 9 muhurtas are unavailable. The same passage treats
     * 6 muhurtas of Dvitiya-entry as the gross Chandra-darshana concern, but that is explanatory
     * context rather than a standalone monthly Chandra Darshana selector.
     */
    public const float GOVARDHAN_LATER_PRATIPADA_FULL_RITES_MUHURTAS = 10.0;

    public const float GOVARDHAN_PREVIOUS_VIDDHA_FALLBACK_BELOW_MUHURTAS = 9.0;

    public const float GOVARDHAN_GROSS_DWITIYA_ENTRY_MUHURTAS = 6.0;

    /**
     * Nag Panchami (Shravana Krishna Panchami) is paraviddha: the reference keeps the Panchami
     * pierced by the Shashthi that spans at least 6 daytime ghadis past sunrise, and only
     * shifts the observance based on the same 6-ghadi Chaturthi vedha threshold.
     */
    public const float NAG_PANCHAMI_SHASHTHI_VEDHA_GHADI = 6.0;

    /**
     * Durgashtami / Bhavani Pragatya (Chaitra Shukla Ashtami and its monthly derivative) is
     * paraviddha (navami-viddha): the reference takes the Ashtami spanning at least 3 muhurtas
     * past sunrise, otherwise it falls back to the Saptami-viddha previous day.
     */
    public const float DURGASHTAMI_PARAVIDDHA_MUHURTAS = 3.0;

    /**
     * Akshaya Tritiya (Vaishakha Shukla Tritiya) is purvahna (forenoon) vyapini. When the
     * Tritiya pervades the purvahna on both civil days the reference shifts the observance to
     * the second day only if the second day's Tritiya spans at least 3 muhurtas past sunrise;
     * otherwise it stays on the first (purva) day.
     */
    public const float AKSHAYA_TRITIYA_PURVAHNA_MUHURTAS = 3.0;

    /**
     * Anant Chaturdashi (Bhadrapada Shukla Chaturdashi) is a post-sunrise paraviddha: the
     * reference takes the Chaturdashi spanning at least 2 muhurtas past sunrise, otherwise the
     * observance falls back to the previous day. Purvahna is the primary kala.
     */
    public const float ANANT_CHATURDASHI_PARAVIDDHA_MUHURTAS = 2.0;

    /**
     * Chaitra/Ashvina Navaratri Pratipada starts on Pratipada when it lasts at least one
     * dinamana-muhurta after sunrise; when below one muhurta or kshaya, the start falls
     * back to the Amavasya-viddha previous day.
     */
    public const float NAVRATRI_PRATIPADA_MIN_MUHURTAS = 1.0;

    /**
     * Durva Ashtami (Dharo Atham) is purvaviddha with a sunset-side check: if Ashtami
     * begins at least three dinamana-muhurtas before sunset on the Saptami day, take
     * that first day; vriddhi/kshaya also keep the first day.
     */
    public const float DURVA_ASHTAMI_PURVAVIDDHA_MUHURTAS = 3.0;

    /** Vratni Purnima uses a daytime ghadi threshold derived from local dinamana. */
    public const float PURNIMA_VRAT_CHATURDASHI_THRESHOLD_GHADIS = 18.0;
}
