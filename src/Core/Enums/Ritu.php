<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Core\Enums;

use JayeshMepani\PanchangCore\Core\Localization;

/**
 * Ṛtu (Season) Enumeration.
 *
 * Represents the 6 seasons in the Hindu calendar.
 * Each ritu spans 60° of the zodiac (approx. 2 lunar months).
 */
enum Ritu: int
{
    case Vasanta = 0;
    case Grishma = 1;
    case Varsha = 2;
    case Sharad = 3;
    case Hemanta = 4;
    case Shishira = 5;

    /** Get Sanskrit name */
    public function getName(?string $locale = null): string
    {
        return Localization::translate('Ritu', $this->value, $locale);
    }

    /**
     * Get ṛtu from Sun longitude.
     *
     * @param float $sunLon Sun longitude in degrees
     *
     * @return self Ritu instance
     */
    public static function fromSunLongitude(float $sunLon): self
    {
        $normalized = fmod($sunLon, 360.0);
        if ($normalized < 0) {
            $normalized += 360.0;
        }

        $index = (int) floor($normalized / 60.0);
        return self::from($index);
    }

    /**
     * Get ṛtu from solar month index (0-11).
     *
     * @param int $monthIndex Month index (0=Mesha/Chaitra)
     *
     * @return self Ritu instance
     */
    public static function fromMonth(int $monthIndex): self
    {
        return self::from((int) floor(($monthIndex % 12) / 2.0));
    }
}
