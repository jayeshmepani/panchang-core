<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Core\Enums;

use JayeshMepani\PanchangCore\Core\Localization;

/**
 * Yoga Enumeration.
 *
 * Represents the 27 yogas in Vedic astrology.
 * Each yoga is completed when the sum of Sun and Moon longitudes gains 13°20'.
 */
enum Yoga: int
{
    case Vishkumbha = 0;
    case Priti = 1;
    case Ayushman = 2;
    case Saubhagya = 3;
    case Shobhana = 4;
    case Atiganda = 5;
    case Sukarma = 6;
    case Dhriti = 7;
    case Shula = 8;
    case Ganda = 9;
    case Vriddhi = 10;
    case Dhruva = 11;
    case Vyaghata = 12;
    case Harshana = 13;
    case Vajra = 14;
    case Siddhi = 15;
    case Vyatipata = 16;
    case Variyana = 17;
    case Parigha = 18;
    case Shiva = 19;
    case Siddha = 20;
    case Sadhya = 21;
    case Shubha = 22;
    case Shukla = 23;
    case Brahma = 24;
    case Indra = 25;
    case Vaidhriti = 26;

    /** Get Sanskrit name */
    public function getName(?string $locale = null): string
    {
        return Localization::translate('Yoga', $this->value, $locale);
    }

    /**
     * Get yoga from Sun-Moon longitude sum.
     *
     * @param float $sunLon Sun longitude in degrees
     * @param float $moonLon Moon longitude in degrees
     *
     * @return self Yoga instance
     */
    public static function fromLongitudes(float $sunLon, float $moonLon): self
    {
        $sum = fmod($sunLon + $moonLon, 360.0);
        if ($sum < 0) {
            $sum += 360.0;
        }

        $index = (int) floor($sum / 13.3333333333);
        return self::from($index);
    }

    /**
     * Get fraction remaining in yoga.
     *
     * @param float $sunLon Sun longitude in degrees
     * @param float $moonLon Moon longitude in degrees
     *
     * @return float Fraction remaining (0.0-1.0)
     */
    public static function getFractionRemaining(float $sunLon, float $moonLon): float
    {
        $sum = fmod($sunLon + $moonLon, 360.0);
        if ($sum < 0) {
            $sum += 360.0;
        }

        return 1.0 - (fmod($sum, 13.3333333333) / 13.3333333333);
    }
}
