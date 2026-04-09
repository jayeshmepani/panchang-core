<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Core\Enums;

use JayeshMepani\PanchangCore\Core\Localization;

/**
 * Karaṇa Enumeration.
 *
 * Represents the 11 half-tithis (karaṇas) in Vedic astrology.
 * 7 of these are repeating (movable), and 4 are fixed.
 */
enum Karana: int
{
    case Bava = 0;
    case Balava = 1;
    case Kaulava = 2;
    case Taitila = 3;
    case Gara = 4;
    case Vanija = 5;
    case Vishti = 6;
    case Shakuni = 7;
    case Chatushpada = 8;
    case Naga = 9;
    case Kintughna = 10;

    /** Get Sanskrit name */
    public function getName(?string $locale = null): string
    {
        return Localization::translate('Karana', $this->value, $locale);
    }

    /**
     * Get karaṇa from Sun-Moon longitude difference.
     *
     * @param float $sunLon Sun longitude in degrees
     * @param float $moonLon Moon longitude in degrees
     *
     * @return array{0: string, 1: int} [Name, Index (1-60)]
     */
    public static function getFromLongitudes(float $sunLon, float $moonLon): array
    {
        $diff = fmod($moonLon - $sunLon, 360.0);
        if ($diff < 0) {
            $diff += 360.0;
        }

        $index = (int) floor($diff / 6.0) + 1; // 1-60

        if ($index === 1) {
            return [self::Kintughna->getName(), $index];
        }

        if ($index >= 2 && $index <= 57) {
            $enumIdx = ($index - 2) % 7;
            return [self::from($enumIdx)->getName(), $index];
        }

        if ($index === 58) {
            return [self::Shakuni->getName(), $index];
        }

        if ($index === 59) {
            return [self::Chatushpada->getName(), $index];
        }

        return [self::Naga->getName(), $index];
    }

    /**
     * Get karaṇa from tithi index and its completion fraction.
     *
     * @param int $tithiIndex Tithi index (1-30)
     * @param float $fraction Completion fraction of tithi (0-1)
     *
     * @return self Karana instance
     */
    public static function fromTithi(int $tithiIndex, float $fraction): self
    {
        $kIdx = ($tithiIndex - 1) * 2 + ($fraction < 0.5 ? 0 : 1) + 1;

        if ($kIdx === 1) {
            return self::Kintughna;
        }

        if ($kIdx >= 2 && $kIdx <= 57) {
            return self::from(($kIdx - 2) % 7);
        }

        if ($kIdx === 58) {
            return self::Shakuni;
        }

        if ($kIdx === 59) {
            return self::Chatushpada;
        }

        return self::Naga;
    }
}
