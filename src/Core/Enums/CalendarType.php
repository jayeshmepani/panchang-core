<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Core\Enums;

use JayeshMepani\PanchangCore\Core\Localization;

/**
 * Hindu Calendar Type Enumeration.
 *
 * Determines how lunar months are named and which festivals are observed.
 * - Amanta (Amavasyant): Month ends on Amavasya (New Moon). Used in South, West, and Central India.
 * - Purnimanta: Month ends on Purnima (Full Moon). Used in North, East, and parts of Central India.
 *
 * During Shukla Paksha (bright fortnight), both calendars show identical dates.
 * During Krishna Paksha (dark fortnight), the month names differ.
 */
enum CalendarType: string
{
    case Amanta = 'amanta';
    case Purnimanta = 'purnimanta';

    /** Get the localized name for this calendar type. */
    public function getLocalizedName(?string $locale = null): string
    {
        return match ($this) {
            self::Amanta => Localization::translate('Common', 'Amanta Calendar', $locale),
            self::Purnimanta => Localization::translate('Common', 'Purnimanta Calendar', $locale),
        };
    }

    /** Check if this calendar follows the Amavasya-Ending system. */
    public function isAmanta(): bool
    {
        return $this === self::Amanta;
    }

    /** Check if this calendar follows the Purnima-Ending system. */
    public function isPurnimanta(): bool
    {
        return $this === self::Purnimanta;
    }
}
