<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Core\Enums;

use JayeshMepani\PanchangCore\Core\Localization;

/**
 * Pakṣa (Fortnight) Enumeration.
 *
 * In the Hindu lunar calendar, a month consists of two fortnights:
 * - Śukla Pakṣa (bright/waxing)
 * - Kṛṣṇa Pakṣa (dark/waning)
 */
enum Paksha: int
{
    case Shukla = 0;
    case Krishna = 1;

    /** Get Sanskrit name */
    public function getName(?string $locale = null): string
    {
        return Localization::translate('Paksha', $this->value, $locale);
    }

    /** Get raw enum name (non-localized, for programmatic use) */
    public function getRawName(): string
    {
        return $this->name;
    }

    /** Get description */
    public function getDescription(): string
    {
        return match ($this) {
            self::Shukla => 'Waxing moon fortnight (bright half)',
            self::Krishna => 'Waning moon fortnight (dark half)',
        };
    }

    /**
     * Get tithi range.
     *
     * @return array{start: int, end: int} Tithi index range
     */
    public function getTithiRange(): array
    {
        return match ($this) {
            self::Shukla => ['start' => 1, 'end' => 15],
            self::Krishna => ['start' => 16, 'end' => 30],
        };
    }

    /**
     * Check if tithi belongs to this pakṣa.
     *
     * @param int $tithiIndex Tithi index (1-30)
     *
     * @return bool True if belongs to this pakṣa
     */
    public function containsTithi(int $tithiIndex): bool
    {
        $range = $this->getTithiRange();
        return $tithiIndex >= $range['start'] && $tithiIndex <= $range['end'];
    }

    /**
     * Normalize tithi to 1-15 range.
     *
     * @param int $tithiIndex Tithi index (1-30)
     *
     * @return int Normalized tithi (1-15)
     */
    public function normalizeTithi(int $tithiIndex): int
    {
        return match ($this) {
            self::Shukla => $tithiIndex,
            self::Krishna => $tithiIndex - 15,
        };
    }

    /**
     * Convert normalized tithi to absolute index.
     *
     * @param int $normalizedTithi Normalized tithi (1-15)
     *
     * @return int Absolute tithi index (1-30)
     */
    public function toAbsoluteTithi(int $normalizedTithi): int
    {
        return match ($this) {
            self::Shukla => $normalizedTithi,
            self::Krishna => $normalizedTithi + 15,
        };
    }

    /** Get opposite pakṣa */
    public function opposite(): self
    {
        return $this === self::Shukla ? self::Krishna : self::Shukla;
    }

    /** Check if pakṣa is Śukla */
    public function isShukla(): bool
    {
        return $this === self::Shukla;
    }

    /** Check if pakṣa is Kṛṣṇa */
    public function isKrishna(): bool
    {
        return $this === self::Krishna;
    }
}
