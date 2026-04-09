<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Core\Enums;

use JayeshMepani\PanchangCore\Core\Localization;

/**
 * Māsa (Lunar Month) Enumeration.
 *
 * Represents the 12 lunar months in the Hindu calendar.
 * In a purnimānta calendar, months end on full moon days.
 * In an amānta calendar, months end on new moon days.
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
    public function getName(?string $locale = null): string
    {
        return Localization::translate('Masa', $this->value, $locale);
    }

    /**
     * Get māsa from Sun longitude.
     *
     * @param float $sunLon Sun longitude in degrees
     *
     * @return self Masa instance
     */
    public static function fromSunLongitude(float $sunLon): self
    {
        $normalized = fmod($sunLon, 360.0);
        if ($normalized < 0) {
            $normalized += 360.0;
        }

        $index = (int) floor($normalized / 30.0);
        return self::from($index);
    }

    /**
     * Get māsa from amānta index (0-11).
     *
     * @param int $index Month index
     *
     * @return self Masa instance
     */
    public static function fromAmantaIndex(int $index): self
    {
        return self::from($index % 12);
    }

    /**
     * Alias for from().
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
