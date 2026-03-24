<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Core\Types;

/**
 * Karma Kāla (Sacred Time Period) Enumeration.
 *
 * Represents the auspicious time periods used in festival calculations:
 * - Sunrise: Udaya (sunrise moment)
 * - Aruṇodaya: 4 ghaṭikās before sunrise (96 minutes)
 * - Madhyāhna: Midday (middle of sunrise to sunset)
 * - Aparāhṇa: Afternoon (3/4 of daytime)
 * - Pradoṣa: Evening twilight (3 ghaṭikās after sunset, 72 minutes)
 * - Niśīta: Midnight (middle of sunset to next sunrise)
 * - Moonrise: Chandrodaya (moonrise moment)
 */
enum KarmaKalaType: string
{
    case SUNRISE = 'sunrise';
    case ARUNODAYA = 'arunodaya';
    case MADHYAHNA = 'madhyahna';
    case APARAHNA = 'aparahna';
    case PRADOSHA = 'pradosha';
    case NISHITA = 'nishita';
    case MOONRISE = 'moonrise';

    /** Get classical Sanskrit name */
    public function getSanskritName(): string
    {
        return match ($this) {
            self::SUNRISE => 'Udaya',
            self::ARUNODAYA => 'Aruṇodaya',
            self::MADHYAHNA => 'Madhyāhna',
            self::APARAHNA => 'Aparāhṇa',
            self::PRADOSHA => 'Pradoṣa',
            self::NISHITA => 'Niśīta',
            self::MOONRISE => 'Chandrodaya',
        };
    }

    /** Get duration in minutes (0 for moment-based) */
    public function getDurationMinutes(): float
    {
        return match ($this) {
            self::SUNRISE, self::MADHYAHNA, self::APARAHNA, self::NISHITA, self::MOONRISE => 0.0,
            self::ARUNODAYA => 96.0,
            self::PRADOSHA => 72.0,
        };
    }

    /** Get classical reference */
    public function getClassicalReference(): string
    {
        return match ($this) {
            self::SUNRISE => 'Sūrya Siddhānta 1.10',
            self::ARUNODAYA => 'Muhūrta Chintāmaṇi 5',
            self::MADHYAHNA => 'Kāla Nirṇaya 4.2',
            self::APARAHNA => 'Kāla Nirṇaya 4.2',
            self::PRADOSHA => 'Muhūrta Chintāmaṇi 45',
            self::NISHITA => 'Muhūrta Chintāmaṇi 67',
            self::MOONRISE => 'Sūrya Siddhānta 1.11',
        };
    }
}
