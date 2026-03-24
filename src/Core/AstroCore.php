<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Core;

use DateTimeInterface;

/**
 * Core Astrological Calculations.
 *
 * Provides fundamental functions for Vedic astrology calculations:
 * - Angle normalization (0-360°)
 * - Angular distance calculation
 * - Sign determination
 * - House calculation
 */
final readonly class AstroCore
{
    /**
     * Normalize angle to 0-360° range.
     *
     * @param float $x Input angle in degrees
     *
     * @return float Normalized angle (0.0 <= x < 360.0)
     */
    public static function normalize(float $x): float
    {
        $val = fmod($x, 360.0);
        if ($val < 0) {
            $val += 360.0;
        }
        return $val;
    }

    /**
     * Get angular distance between two longitudes.
     *
     * @param float $a First longitude
     * @param float $b Second longitude
     *
     * @return float Angular distance (0.0 <= x <= 180.0)
     */
    public static function getAngularDistance(float $a, float $b): float
    {
        $diff = abs($a - $b);
        if ($diff > 180.0) {
            $diff = 360.0 - $diff;
        }
        return $diff;
    }

    /**
     * Get zodiac sign from longitude.
     *
     * @param float $lon Longitude in degrees
     *
     * @return int Sign number (0-11, where 0=Aries, 1=Taurus, etc.)
     */
    public static function getSign(float $lon): int
    {
        return ((int) floor($lon / 30.0)) % 12;
    }

    /**
     * Get longitude within sign.
     *
     * @param float $lon Longitude in degrees
     *
     * @return float Longitude within sign (0.0 <= x < 30.0)
     */
    public static function getSignLon(float $lon): float
    {
        $val = fmod($lon, 30.0);
        if ($val < 0) {
            $val += 30.0;
        }
        return $val;
    }

    /**
     * Count signs between two positions.
     *
     * @param int $targetSign Target sign (0-11)
     * @param int $startSign Starting sign (0-11)
     * @param bool $forward Direction (true=forward, false=backward)
     *
     * @return int Distance in signs (1-12)
     */
    public static function countSigns(int $targetSign, int $startSign, bool $forward = true): int
    {
        if ($forward) {
            return (($targetSign - $startSign) % 12 + 12) % 12 + 1;
        }

        return (($startSign - $targetSign) % 12 + 12) % 12 + 1;
    }

    /**
     * Get house number from lagna.
     *
     * @param int $signIdx Sign index (0-11)
     * @param int $ascSignIdx Ascendant sign index (0-11)
     *
     * @return int House number (1-12)
     */
    public static function getHouseNumFromLagna(int $signIdx, int $ascSignIdx): int
    {
        return self::countSigns($signIdx, $ascSignIdx);
    }

    /**
     * Get precise time difference in minutes.
     *
     * @param DateTimeInterface $target Target datetime
     * @param DateTimeInterface $reference Reference datetime
     *
     * @return float Time difference in minutes
     */
    public static function getTimeDiffMinutesPrecise(DateTimeInterface $target, DateTimeInterface $reference): float
    {
        $diffSeconds = ($target->getTimestamp() - $reference->getTimestamp());
        if ($diffSeconds < 0) {
            $diffSeconds += 86400.0;
        }
        return $diffSeconds / 60.0;
    }

    /**
     * Identity function (placeholder for compatibility).
     *
     * @param float $x Input value
     *
     * @return float Same value
     */
    public static function r9(float $x): float
    {
        return $x;
    }

    /**
     * Convert decimal degrees to DMS string.
     *
     * @param float $deg Decimal degrees
     *
     * @return string DMS string (e.g., "23° 26' 12"")
     */
    public static function toDms(float $deg): string
    {
        $d = (int) floor($deg);
        $m = (int) floor(($deg - $d) * 60.0);
        $s = ($deg - $d - $m / 60.0) * 3600.0;
        return sprintf('%d° %d\' %s"', $d, $m, $s);
    }
}
