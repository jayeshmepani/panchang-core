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
