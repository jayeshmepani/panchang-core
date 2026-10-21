<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Core;

use InvalidArgumentException;

/**
 * Exact equal-arc divisions of the zodiac.
 *
 * A boundary such as 30° (Mesha/Vrishabha) or 40° (Krittika/Rohini) belongs to the
 * following division. Floating-point quotients like floor($lon / 30.0) or
 * $lon / (360 / 27) can round across a boundary, so the index is decided by an
 * exact comparison of $lon × segments against 360 × index (Dekker two-product).
 */
final class ZodiacDivision
{
    public const float DEGREES_PER_NAKSHATRA = 360.0 / 27.0;

    public const float DEGREES_PER_PADA = 360.0 / 108.0;

    private const float SPLITTER = 134217729.0; // 2^27 + 1

    /** 0-based index of the equal arc (360° / $segments) holding $longitude. */
    public static function segment(float $longitude, int $segments): int
    {
        if (!is_finite($longitude)) {
            throw new InvalidArgumentException('Longitude must be finite.');
        }

        if ($segments < 1) {
            throw new InvalidArgumentException('Segments must be positive.');
        }

        // fmod is exact, so $reduced is the true remainder in (-360, 360).
        $reduced = fmod($longitude, 360.0);
        $estimate = (int) floor($reduced * $segments / 360.0);

        $index = $estimate - 2;
        for ($candidate = $estimate - 1; $candidate <= $estimate + 2; $candidate++) {
            if (self::productAtLeast($reduced, (float) $segments, 360.0 * $candidate)) {
                $index = $candidate;
            }
        }

        $index %= $segments;

        return $index < 0 ? $index + $segments : $index;
    }

    public static function rashiIndex(float $longitude): int
    {
        return self::segment($longitude, 12);
    }

    public static function nakshatraIndex(float $longitude): int
    {
        return self::segment($longitude, 27);
    }

    /** 1-based pada (quarter) of the nakshatra holding $longitude. */
    public static function nakshatraPada(float $longitude): int
    {
        return self::segment($longitude, 108) % 4 + 1;
    }

    /** Whether the exact product $a × $b is at least $threshold (an exactly representable value). */
    private static function productAtLeast(float $a, float $b, float $threshold): bool
    {
        $high = $a * $b;
        if ($high !== $threshold) {
            return $high > $threshold;
        }

        [$aHigh, $aLow] = self::split($a);
        [$bHigh, $bLow] = self::split($b);
        $low = (($aHigh * $bHigh - $high) + $aHigh * $bLow + $aLow * $bHigh) + $aLow * $bLow;

        return $low >= 0.0;
    }

    /** @return array{float, float} */
    private static function split(float $value): array
    {
        $scaled = self::SPLITTER * $value;
        $high = $scaled - ($scaled - $value);

        return [$high, $value - $high];
    }
}
