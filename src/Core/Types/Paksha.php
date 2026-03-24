<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Core\Types;

/**
 * Pakṣa (Fortnight) Enumeration.
 *
 * Represents the two fortnights in a lunar month:
 * - Śukla Pakṣa: Waxing moon (bright fortnight)
 * - Kṛṣṇa Pakṣa: Waning moon (dark fortnight)
 */
enum Paksha: string
{
    case SHUKLA = 'Shukla';
    case KRISHNA = 'Krishna';

    /** Get tithi range for this pakṣa */
    public function getTithiRange(): array
    {
        return match ($this) {
            self::SHUKLA => [1, 15],   // Pratipada to Purnima
            self::KRISHNA => [16, 30], // Pratipada to Amavasya (15+1 to 30)
        };
    }

    /** Check if tithi number belongs to this pakṣa */
    public function containsTithi(int $tithiNumber): bool
    {
        [$min, $max] = $this->getTithiRange();
        return $tithiNumber >= $min && $tithiNumber <= $max;
    }

    /** Get normalized tithi number (1-15) from absolute tithi (1-30) */
    public function normalizeTithi(int $absoluteTithi): int
    {
        return match ($this) {
            self::SHUKLA => $absoluteTithi,
            self::KRISHNA => $absoluteTithi - 15,
        };
    }

    /** Get absolute tithi number (1-30) from normalized tithi (1-15) */
    public function toAbsoluteTithi(int $normalizedTithi): int
    {
        return match ($this) {
            self::SHUKLA => $normalizedTithi,
            self::KRISHNA => $normalizedTithi + 15,
        };
    }
}
