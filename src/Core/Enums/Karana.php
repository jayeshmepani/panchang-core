<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Core\Enums;

/**
 * Karaṇa Enumeration.
 *
 * Represents the 11 karanas (half lunar days) in Vedic astrology.
 * Each tithi is divided into two karanas (each 6° of Moon-Sun longitude difference).
 */
enum Karana: int
{
    case Bava = 1;
    case Balava = 2;
    case Kaulava = 3;
    case Taitila = 4;
    case Gara = 5;
    case Vanija = 6;
    case Vishti = 7;
    case Shakuni = 8;
    case Chatushpada = 9;
    case Naga = 10;
    case Kimstughna = 11;

    /** Get Sanskrit name */
    public function getName(): string
    {
        return match ($this) {
            self::Bava => 'Bava',
            self::Balava => 'Balava',
            self::Kaulava => 'Kaulava',
            self::Taitila => 'Taitila',
            self::Gara => 'Gara',
            self::Vanija => 'Vanija',
            self::Vishti => 'Vishti',
            self::Shakuni => 'Shakuni',
            self::Chatushpada => 'Chatushpada',
            self::Naga => 'Naga',
            self::Kimstughna => 'Kimstughna',
        };
    }

    /** Get type (Chara/Movable or Sthira/Fixed) */
    public function getType(): string
    {
        return in_array($this->value, [8, 9, 10, 11], true) ? 'Sthira' : 'Chara';
    }

    /**
     * Get karana from tithi index.
     *
     * @param int $tithiIndex Tithi index (1-30)
     * @param float $fraction Tithi fraction (0.0-1.0)
     *
     * @return self Karana instance
     */
    public static function fromTithi(int $tithiIndex, float $fraction): self
    {
        $adjustedTithi = $tithiIndex - 1;
        if ($adjustedTithi < 0) {
            $adjustedTithi = 29;
        }

        $isFirstHalf = $fraction < 0.5;
        $index = ($adjustedTithi * 2) + ($isFirstHalf ? 0 : 1);

        if ($index === 0) { return self::Kimstughna; }
        if ($index === 57) { return self::Shakuni; }
        if ($index === 58) { return self::Chatushpada; }
        if ($index === 59) { return self::Naga; }

        $charaValue = (($index - 1) % 7) + 1;
        return self::from($charaValue);
    }

    /** Check if this is Vishti (Bhadra) */
    public function isVishti(): bool
    {
        return $this === self::Vishti;
    }
}
