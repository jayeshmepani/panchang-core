<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Core\Enums;

/**
 * Horā (Planetary Hour) Enumeration
 * 
 * Represents the 7 planets that rule the hourly periods.
 * Each hora spans 1/12th of the day or night duration.
 * 
 * @package JayeshMepani\PanchangCore
 */
enum Hora: int
{
    case Sun = 0;
    case Venus = 1;
    case Mercury = 2;
    case Moon = 3;
    case Saturn = 4;
    case Jupiter = 5;
    case Mars = 6;
    
    /**
     * Get Sanskrit name
     */
    public function getName(): string
    {
        return match ($this) {
            self::Sun => 'Sūrya',
            self::Venus => 'Śukra',
            self::Mercury => 'Budha',
            self::Moon => 'Chandra',
            self::Saturn => 'Śani',
            self::Jupiter => 'Guru',
            self::Mars => 'Maṅgala',
        };
    }
    
    /**
     * Get hora sequence starting planet for weekday
     * 
     * @param Vara $vara Weekday
     * @return self Starting planet
     */
    public static function getStartingPlanet(Vara $vara): self
    {
        return match ($vara) {
            Vara::Sunday => self::Sun,
            Vara::Monday => self::Moon,
            Vara::Tuesday => self::Mars,
            Vara::Wednesday => self::Mercury,
            Vara::Thursday => self::Jupiter,
            Vara::Friday => self::Venus,
            Vara::Saturday => self::Saturn,
        };
    }
    
    /**
     * Get hora ruler at given time
     * 
     * @param float $jdSunrise Sunrise Julian Day
     * @param float $jdSunset Sunset Julian Day
     * @param float $jdNextSunrise Next sunrise Julian Day
     * @param float $jdCurrent Current Julian Day
     * @param Vara $vara Weekday
     * @return self Hora instance
     */
    public static function fromTime(float $jdSunrise, float $jdSunset, float $jdNextSunrise, float $jdCurrent, Vara $vara): self
    {
        $isDay = $jdCurrent >= $jdSunrise && $jdCurrent < $jdSunset;
        
        if ($isDay) {
            $durationTotal = $jdSunset - $jdSunrise;
            $elapsed = $jdCurrent - $jdSunrise;
            $baseOffset = 0;
        } else {
            $durationTotal = $jdNextSunrise - $jdSunset;
            $elapsed = $jdCurrent - $jdSunset;
            $baseOffset = 12;
        }
        
        $horaDuration = $durationTotal / 12.0;
        $horaIdx = (int) floor($elapsed / $horaDuration);
        
        if ($horaIdx > 11) {
            $horaIdx = 11;
        }
        
        $startPlanet = self::getStartingPlanet($vara);
        $totalHoursPassed = $baseOffset + $horaIdx;
        $planetIndex = ($startPlanet->value + $totalHoursPassed) % 7;
        
        return self::from($planetIndex);
    }
}
