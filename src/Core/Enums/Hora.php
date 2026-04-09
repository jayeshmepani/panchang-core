<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Core\Enums;

use JayeshMepani\PanchangCore\Core\Localization;

/**
 * Horā (Planetary Hour) Enumeration.
 *
 * Represents the 24 horas in a day, each ruled by a planet.
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

    /** Get Sanskrit name */
    public function getName(?string $locale = null): string
    {
        return Localization::translate('Planet', $this->toPlanetIndex(), $locale);
    }

    /**
     * Map Hora enum index to standard Planet index in Localization.
     * Localization 'Planet' order: Sun (0), Moon (1), Mars (2), Rahu (3), Jupiter (4), Saturn (5), Mercury (6), Ketu (7), Venus (8)
     */
    private function toPlanetIndex(): int
    {
        return match ($this) {
            self::Sun => 0,
            self::Moon => 1,
            self::Mars => 2,
            self::Jupiter => 4,
            self::Saturn => 5,
            self::Mercury => 6,
            self::Venus => 8,
        };
    }

    /**
     * Get horā sequence for weekday.
     *
     * @param Vara $vara Weekday
     *
     * @return array<int, self> Hora sequence (24 horas)
     */
    public static function getSequence(Vara $vara): array
    {
        // Sequence follows descending order of orbital speeds:
        // Saturn -> Jupiter -> Mars -> Sun -> Venus -> Mercury -> Moon
        $baseOrder = [
            self::Sun,
            self::Venus,
            self::Mercury,
            self::Moon,
            self::Saturn,
            self::Jupiter,
            self::Mars,
        ];

        // Day starts with the lord of the weekday
        $startLords = [
            0 => self::Sun,     // Sunday
            1 => self::Moon,    // Monday
            2 => self::Mars,    // Tuesday
            3 => self::Mercury, // Wednesday
            4 => self::Jupiter, // Thursday
            5 => self::Venus,   // Friday
            6 => self::Saturn,  // Saturday
        ];

        $firstHora = $startLords[$vara->value];
        $startIndex = array_search($firstHora, $baseOrder, true);

        $sequence = [];
        for ($i = 0; $i < 24; $i++) {
            $sequence[] = $baseOrder[($startIndex + $i) % 7];
        }

        return $sequence;
    }
}
