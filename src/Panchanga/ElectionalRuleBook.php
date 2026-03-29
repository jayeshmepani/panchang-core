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

}
