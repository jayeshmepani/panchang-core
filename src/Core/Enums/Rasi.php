<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Core\Enums;

/**
 * Rāśi (Zodiac Sign) Enumeration
 * 
 * Represents the 12 zodiac signs in Vedic astrology.
 * Each sign spans 30° of the zodiac.
 * 
 * @package JayeshMepani\PanchangCore
 */
enum Rasi: int
{
    case Mesha = 0;
    case Vrishabha = 1;
    case Mithuna = 2;
    case Karka = 3;
    case Simha = 4;
    case Kanya = 5;
    case Tula = 6;
    case Vrischika = 7;
    case Dhanu = 8;
    case Makara = 9;
    case Kumbha = 10;
    case Meena = 11;
    
    /**
     * Get Sanskrit name
     */
    public function getName(): string
    {
        return match ($this) {
            self::Mesha => 'Meṣa',
            self::Vrishabha => 'Vṛiṣabha',
            self::Mithuna => 'Mithuna',
            self::Karka => 'Karkaṭa',
            self::Simha => 'Siṃha',
            self::Kanya => 'Kanyā',
            self::Tula => 'Tulā',
            self::Vrischika => 'Vṛiśchika',
            self::Dhanu => 'Dhanuṣ',
            self::Makara => 'Makara',
            self::Kumbha => 'Kumbha',
            self::Meena => 'Mīna',
        };
    }
    
    /**
     * Get English name
     */
    public function getEnglishName(): string
    {
        return match ($this) {
            self::Mesha => 'Aries',
            self::Vrishabha => 'Taurus',
            self::Mithuna => 'Gemini',
            self::Karka => 'Cancer',
            self::Simha => 'Leo',
            self::Kanya => 'Virgo',
            self::Tula => 'Libra',
            self::Vrischika => 'Scorpio',
            self::Dhanu => 'Sagittarius',
            self::Makara => 'Capricorn',
            self::Kumbha => 'Aquarius',
            self::Meena => 'Pisces',
        };
    }
    
    /**
     * Get symbol
     */
    public function getSymbol(): string
    {
        return match ($this) {
            self::Mesha => '♈',
            self::Vrishabha => '♉',
            self::Mithuna => '♊',
            self::Karka => '♋',
            self::Simha => '♌',
            self::Kanya => '♍',
            self::Tula => '♎',
            self::Vrischika => '♏',
            self::Dhanu => '♐',
            self::Makara => '♑',
            self::Kumbha => '♒',
            self::Meena => '♓',
        };
    }
    
    /**
     * Get ruling planet
     */
    public function getRulingPlanet(): string
    {
        return match ($this) {
            self::Mesha => 'Maṅgala (Mars)',
            self::Vrishabha => 'Śukra (Venus)',
            self::Mithuna => 'Budha (Mercury)',
            self::Karka => 'Chandra (Moon)',
            self::Simha => 'Sūrya (Sun)',
            self::Kanya => 'Budha (Mercury)',
            self::Tula => 'Śukra (Venus)',
            self::Vrischika => 'Maṅgala (Mars)',
            self::Dhanu => 'Guru (Jupiter)',
            self::Makara => 'Śani (Saturn)',
            self::Kumbha => 'Śani (Saturn)',
            self::Meena => 'Guru (Jupiter)',
        };
    }
    
    /**
     * Get element
     */
    public function getElement(): string
    {
        return match ($this) {
            self::Mesha, self::Simha, self::Dhanu => 'Agni (Fire)',
            self::Vrishabha, self::Kanya, self::Makara => 'Pṛthvī (Earth)',
            self::Mithuna, self::Tula, self::Kumbha => 'Vāyu (Air)',
            self::Karka, self::Vrischika, self::Meena => 'Jala (Water)',
        };
    }
    
    /**
     * Get longitude range
     * 
     * @return array{start: float, end: float} Longitude range in degrees
     */
    public function getLongitudeRange(): array
    {
        $start = $this->value * 30.0;
        $end = ($this->value + 1) * 30.0;
        
        return [
            'start' => $start,
            'end' => $end,
        ];
    }
    
    /**
     * Get rāśi from longitude
     * 
     * @param float $longitude Longitude in degrees (0-360)
     * @return self Rasi instance
     */
    public static function fromLongitude(float $longitude): self
    {
        $normalized = fmod($longitude, 360.0);
        if ($normalized < 0) {
            $normalized += 360.0;
        }
        
        $index = (int) floor($normalized / 30.0);
        return self::from($index);
    }
}
