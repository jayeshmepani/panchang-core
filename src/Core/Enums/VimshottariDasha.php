<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Core\Enums;

/**
 * Vimśottarī Daśā Planet Enumeration
 * 
 * Represents the 9 planets in the Vimshottari Dasha system with their dasha periods.
 * 
 * @package JayeshMepani\PanchangCore
 */
enum VimshottariDasha: int
{
    case Sun = 0;
    case Moon = 1;
    case Mars = 2;
    case Rahu = 3;
    case Jupiter = 4;
    case Saturn = 5;
    case Mercury = 6;
    case Ketu = 7;
    case Venus = 8;
    
    /**
     * Get Sanskrit name
     */
    public function getName(): string
    {
        return match ($this) {
            self::Sun => 'Sūrya',
            self::Moon => 'Chandra',
            self::Mars => 'Maṅgala',
            self::Rahu => 'Rāhu',
            self::Jupiter => 'Guru',
            self::Saturn => 'Śani',
            self::Mercury => 'Budha',
            self::Ketu => 'Ketu',
            self::Venus => 'Śukra',
        };
    }
    
    /**
     * Get dasha period in years
     */
    public function getDashaYears(): int
    {
        return match ($this) {
            self::Sun => 6,
            self::Moon => 10,
            self::Mars => 7,
            self::Rahu => 18,
            self::Jupiter => 16,
            self::Saturn => 19,
            self::Mercury => 17,
            self::Ketu => 7,
            self::Venus => 20,
        };
    }
    
    /**
     * Get planet from nakshatra
     * 
     * @param Nakshatra $nakshatra Nakshatra instance
     * @return self VimshottariDasha instance
     */
    public static function fromNakshatra(Nakshatra $nakshatra): self
    {
        $planets = [
            self::Ketu,    // Ashwini (0)
            self::Venus,   // Bharani (1)
            self::Sun,     // Krittika (2)
            self::Moon,    // Rohini (3)
            self::Mars,    // Mrigashira (4)
            self::Rahu,    // Ardra (5)
            self::Jupiter, // Punarvasu (6)
            self::Saturn,  // Pushya (7)
            self::Mercury, // Ashlesha (8)
            self::Ketu,    // Magha (9)
            self::Venus,   // Purva Phalguni (10)
            self::Sun,     // Uttara Phalguni (11)
            self::Moon,    // Hasta (12)
            self::Mars,    // Chitra (13)
            self::Rahu,    // Swati (14)
            self::Jupiter, // Vishakha (15)
            self::Saturn,  // Anuradha (16)
            self::Mercury, // Jyeshtha (17)
            self::Ketu,    // Mula (18)
            self::Venus,   // Purva Ashadha (19)
            self::Sun,     // Uttara Ashadha (20)
            self::Moon,    // Shravana (21)
            self::Mars,    // Dhanishta (22)
            self::Rahu,    // Shatabhisha (23)
            self::Jupiter, // Purva Bhadrapada (24)
            self::Saturn,  // Uttara Bhadrapada (25)
            self::Mercury, // Revati (26)
        ];
        
        return $planets[$nakshatra->value];
    }
}
