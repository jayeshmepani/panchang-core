<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Core\Types;

use SwissEph\FFI\SwissEphFFI;

/**
 * Ayanāṁśa Mode Enumeration.
 *
 * Represents the different ayanāṁśa systems used in Vedic astrology:
 * - Lahiri: Most widely used, official Indian standard
 * - Raman: Used by followers of B.V. Raman
 * - Krishnamurti: Used in KP astrology system
 */
enum AyanamsaMode: string
{
    case LAHIRI = 'LAHIRI';
    case RAMAN = 'RAMAN';
    case KRISHNAMURTI = 'KRISHNAMURTI';

    /** Get Swiss Ephemeris mode constant */
    public function toSwissEphMode(): int
    {
        return match ($this) {
            self::LAHIRI => SwissEphFFI::SE_SIDM_LAHIRI,
            self::RAMAN => SwissEphFFI::SE_SIDM_RAMAN,
            self::KRISHNAMURTI => SwissEphFFI::SE_SIDM_KRISHNAMURTI,
        };
    }

    /** Get description */
    public function getDescription(): string
    {
        return match ($this) {
            self::LAHIRI => 'Lahiri Ayanāṁśa - Official Indian standard, most widely used',
            self::RAMAN => 'Raman Ayanāṁśa - Used by followers of B.V. Raman',
            self::KRISHNAMURTI => 'Krishnamurti Ayanāṁśa - Used in KP astrology system',
        };
    }

    /** Get approximate ayanāṁśa value for year 2000 */
    public function getApproximateValueForY2K(): float
    {
        return match ($this) {
            self::LAHIRI => 23.85,
            self::RAMAN => 22.47,
            self::KRISHNAMURTI => 23.52,
        };
    }
}
