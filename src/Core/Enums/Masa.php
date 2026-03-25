<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Core\Enums;

/**
 * Māsa (Hindu Lunar Month) Enumeration.
 *
 * Represents the 12 lunar months in the Hindu calendar.
 */
enum Masa: int
{
    case Chaitra = 0;
    case Vaishakha = 1;
    case Jyeshtha = 2;
    case Ashadha = 3;
    case Shravana = 4;
    case Bhadrapada = 5;
    case Ashvina = 6;
    case Kartika = 7;
    case Margashirsha = 8;
    case Pausha = 9;
    case Magha = 10;
    case Phalguna = 11;

    /** Get Sanskrit name */
    public function getName(): string
    {
        return match ($this) {
            self::Chaitra => 'Chaitra',
            self::Vaishakha => 'Vaiśākha',
            self::Jyeshtha => 'Jyeṣṭha',
            self::Ashadha => 'Āṣāḍha',
            self::Shravana => 'Śrāvaṇa',
            self::Bhadrapada => 'Bhādrapada',
            self::Ashvina => 'Āśvina',
            self::Kartika => 'Kārtika',
            self::Margashirsha => 'Mārgaśīrṣa',
            self::Pausha => 'Pauṣa',
            self::Magha => 'Māgha',
            self::Phalguna => 'Phālguna',
        };
    }

    /** Get English approximation */
    public function getEnglishApproximation(): string
    {
        return match ($this) {
            self::Chaitra => 'March-April',
            self::Vaishakha => 'April-May',
            self::Jyeshtha => 'May-June',
            self::Ashadha => 'June-July',
            self::Shravana => 'July-August',
            self::Bhadrapada => 'August-September',
            self::Ashvina => 'September-October',
            self::Kartika => 'October-November',
            self::Margashirsha => 'November-December',
            self::Pausha => 'December-January',
            self::Magha => 'January-February',
            self::Phalguna => 'February-March',
        };
    }

    /** Get ruling nakṣatra */
    public function getRulingNakshatra(): Nakshatra
    {
        return match ($this) {
            self::Chaitra => Nakshatra::Chitra,
            self::Vaishakha => Nakshatra::Vishakha,
            self::Jyeshtha => Nakshatra::Jyeshtha,
            self::Ashadha => Nakshatra::UttaraAshadha,
            self::Shravana => Nakshatra::Shravana,
            self::Bhadrapada => Nakshatra::PurvaBhadrapada,
            self::Ashvina => Nakshatra::Ashwini,
            self::Kartika => Nakshatra::Krittika,
            self::Margashirsha => Nakshatra::Mrigashira,
            self::Pausha => Nakshatra::Pushya,
            self::Magha => Nakshatra::Magha,
            self::Phalguna => Nakshatra::PurvaPhalguni,
        };
    }

    /**
     * Get month from index.
     *
     * @param int $index Month index (0-11)
     *
     * @return self Masa instance
     */
    public static function fromIndex(int $index): self
    {
        return self::from($index % 12);
    }
}
