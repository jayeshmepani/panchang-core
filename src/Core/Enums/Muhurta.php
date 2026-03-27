<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Core\Enums;

/**
 * Muhūrta Enumeration.
 *
 * Represents the 15 muhurtas in a day (from sunrise to next sunrise).
 * Each muhurta spans approximately 48 minutes (1/30 of a day).
 */
enum Muhurta: int
{
    case Rudra = 1;
    case Ahi = 2;
    case Mitra = 3;
    case Pitri = 4;
    case Vasu = 5;
    case Varaha = 6;
    case Vishvedeva = 7;
    case Vidhi = 8;
    case Satyamukhi = 9;
    case Ati = 10;
    case Vahni = 11;
    case Vishnu = 12;
    case Vajra = 13;
    case Shubha = 14;
    case Amrita = 15;

    /** Get Sanskrit name */
    public function getName(): string
    {
        return match ($this) {
            self::Rudra => 'Rudra',
            self::Ahi => 'Ahi',
            self::Mitra => 'Mitra',
            self::Pitri => 'Pitṛi',
            self::Vasu => 'Vasu',
            self::Varaha => 'Varāha',
            self::Vishvedeva => 'Viśvedeva',
            self::Vidhi => 'Vidhi',
            self::Satyamukhi => 'Satyamuḳhi',
            self::Ati => 'Ati',
            self::Vahni => 'Vahni',
            self::Vishnu => 'Viṣṇu',
            self::Vajra => 'Vajra',
            self::Shubha => 'Śubha',
            self::Amrita => 'Amṛta',
        };
    }

    /** Get nature (Auspicious/Inauspicious) */
    public function getNature(): string
    {
        // Inauspicious: 1, 3, 5, 7, 9, 11, 13, 15
        $inauspicious = [1, 3, 5, 7, 9, 11, 13, 15];
        return in_array($this->value, $inauspicious, true) ? 'Inauspicious' : 'Auspicious';
    }

    /**
     * Get muhurta from time of day.
     *
     * @param float $jdSunrise Sunrise Julian Day
     * @param float $jdSunset Sunset Julian Day
     * @param float $jdCurrent Current Julian Day
     *
     * @return self Muhurta instance
     */
    public static function fromTime(float $jdSunrise, float $jdSunset, float $jdCurrent): self
    {
        $dayDuration = $jdSunset - $jdSunrise;
        $muhurtaDuration = $dayDuration / 15.0;

        $elapsed = $jdCurrent - $jdSunrise;
        $index = (int) floor($elapsed / $muhurtaDuration) + 1;

        if ($index < 1) {
            $index = 1;
        }
        if ($index > 15) {
            $index = 15;
        }

        return self::from($index);
    }

}
