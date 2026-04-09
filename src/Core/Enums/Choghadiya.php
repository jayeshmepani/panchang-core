<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Core\Enums;

use JayeshMepani\PanchangCore\Core\Localization;

/**
 * Choghadiya Enumeration.
 *
 * Represents the 8 choghadiya periods in a day.
 * Each choghadiya spans 1/8th of the day or night duration.
 */
enum Choghadiya: int
{
    case Udveg = 0;
    case Chal = 1;
    case Labh = 2;
    case Amrit = 3;
    case Kaal = 4;
    case Shubh = 5;
    case Rog = 6;

    /** Get Sanskrit name */
    public function getName(?string $locale = null): string
    {
        return Localization::translate('Choghadiya', $this->value, $locale);
    }

    /** Get nature */
    public function getNature(): string
    {
        return match ($this) {
            self::Udveg => 'Inauspicious',
            self::Chal => 'Neutral',
            self::Labh => 'Auspicious',
            self::Amrit => 'Very Auspicious',
            self::Kaal => 'Inauspicious',
            self::Shubh => 'Auspicious',
            self::Rog => 'Inauspicious',
        };
    }

    /** Check if auspicious */
    public function isAuspicious(): bool
    {
        return in_array($this, [self::Labh, self::Amrit, self::Shubh], true);
    }

    /**
     * Get choghadiya sequence for weekday.
     *
     * @param Vara $vara Weekday
     *
     * @return array<int, self> Choghadiya sequence
     */
    public static function getDaySequence(Vara $vara): array
    {
        $sequences = [
            0 => [self::Udveg, self::Chal, self::Labh, self::Amrit, self::Kaal, self::Shubh, self::Rog, self::Udveg],  // Sunday
            1 => [self::Amrit, self::Kaal, self::Shubh, self::Rog, self::Udveg, self::Chal, self::Labh, self::Amrit],   // Monday
            2 => [self::Rog, self::Udveg, self::Chal, self::Labh, self::Amrit, self::Kaal, self::Shubh, self::Rog],     // Tuesday
            3 => [self::Chal, self::Labh, self::Amrit, self::Kaal, self::Shubh, self::Rog, self::Udveg, self::Chal],    // Wednesday
            4 => [self::Shubh, self::Rog, self::Udveg, self::Chal, self::Labh, self::Amrit, self::Kaal, self::Shubh],   // Thursday
            5 => [self::Chal, self::Labh, self::Amrit, self::Kaal, self::Shubh, self::Rog, self::Udveg, self::Chal],    // Friday
            6 => [self::Kaal, self::Shubh, self::Rog, self::Udveg, self::Chal, self::Labh, self::Amrit, self::Kaal],    // Saturday
        ];

        return $sequences[$vara->value];
    }

    /**
     * Get choghadiya from time.
     *
     * @param float $jdSunrise Sunrise Julian Day
     * @param float $jdSunset Sunset Julian Day
     * @param float $jdCurrent Current Julian Day
     * @param Vara $vara Weekday
     *
     * @return self Choghadiya instance
     */
    public static function fromTime(float $jdSunrise, float $jdSunset, float $jdCurrent, Vara $vara): self
    {
        $isDay = $jdCurrent >= $jdSunrise && $jdCurrent < $jdSunset;

        if ($isDay) {
            $durationTotal = $jdSunset - $jdSunrise;
            $elapsed = $jdCurrent - $jdSunrise;
        } else {
            $durationTotal = ($jdSunrise + 1.0) - $jdSunset;
            $elapsed = $jdCurrent - $jdSunset;
        }

        $divDuration = $durationTotal / 8.0;
        $divIdx = (int) floor($elapsed / $divDuration);

        if ($divIdx >= 8) {
            $divIdx = 7;
        }

        $sequence = $isDay ? self::getDaySequence($vara) : self::getNightSequence($vara);
        return $sequence[$divIdx];
    }

    /**
     * Get night sequence for weekday.
     *
     * @param Vara $vara Weekday
     *
     * @return array<int, self> Night choghadiya sequence
     */
    public static function getNightSequence(Vara $vara): array
    {
        $sequences = [
            0 => [self::Shubh, self::Amrit, self::Chal, self::Labh, self::Udveg, self::Shubh, self::Amrit, self::Chal],
            1 => [self::Chal, self::Labh, self::Amrit, self::Kaal, self::Shubh, self::Rog, self::Udveg, self::Chal],
            2 => [self::Kaal, self::Shubh, self::Rog, self::Udveg, self::Chal, self::Labh, self::Amrit, self::Kaal],
            3 => [self::Udveg, self::Chal, self::Labh, self::Amrit, self::Kaal, self::Shubh, self::Rog, self::Udveg],
            4 => [self::Amrit, self::Kaal, self::Shubh, self::Rog, self::Udveg, self::Chal, self::Labh, self::Amrit],
            5 => [self::Rog, self::Udveg, self::Chal, self::Labh, self::Amrit, self::Kaal, self::Shubh, self::Rog],
            6 => [self::Chal, self::Labh, self::Amrit, self::Kaal, self::Shubh, self::Rog, self::Udveg, self::Chal],
        ];

        return $sequences[$vara->value];
    }
}
