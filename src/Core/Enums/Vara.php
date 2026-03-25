<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Core\Enums;

/**
 * Vāra (Weekday) Enumeration.
 *
 * Represents the 7 weekdays in Vedic astrology, each ruled by a planet.
 */
enum Vara: int
{
    case Sunday = 0;
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;

    /** Get Sanskrit name */
    public function getName(): string
    {
        return match ($this) {
            self::Sunday => 'Ravivāra',
            self::Monday => 'Somavāra',
            self::Tuesday => 'Maṅgalavāra',
            self::Wednesday => 'Budhavāra',
            self::Thursday => 'Guruvāra',
            self::Friday => 'Śukravāra',
            self::Saturday => 'Śanivāra',
        };
    }

    /** Get English name */
    public function getEnglishName(): string
    {
        return match ($this) {
            self::Sunday => 'Sunday',
            self::Monday => 'Monday',
            self::Tuesday => 'Tuesday',
            self::Wednesday => 'Wednesday',
            self::Thursday => 'Thursday',
            self::Friday => 'Friday',
            self::Saturday => 'Saturday',
        };
    }

    /** Get ruling planet */
    public function getRulingPlanet(): string
    {
        return match ($this) {
            self::Sunday => 'Sūrya (Sun)',
            self::Monday => 'Chandra (Moon)',
            self::Tuesday => 'Maṅgala (Mars)',
            self::Wednesday => 'Budha (Mercury)',
            self::Thursday => 'Guru (Jupiter)',
            self::Friday => 'Śukra (Venus)',
            self::Saturday => 'Śani (Saturn)',
        };
    }

    /**
     * Get weekday from Julian Day.
     *
     * @param float $jd Julian Day
     *
     * @return self Vara instance
     */
    public static function fromJulianDay(float $jd): self
    {
        $weekday = (int) floor($jd + 1.5) % 7;
        return self::from($weekday);
    }

    /**
     * Get weekday from Carbon date.
     *
     * @param int $dayOfWeek Carbon day of week (0=Sunday, 6=Saturday)
     *
     * @return self Vara instance
     */
    public static function fromDayOfWeek(int $dayOfWeek): self
    {
        return self::from($dayOfWeek);
    }
}
