<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Astronomy\Concerns;

use SwissEph\FFI\SwissEphFFI;

trait ConfiguresEphemeris
{
    private static string $ephePath = '';

    private function initializeEphemerisPath(SwissEphFFI $sweph): void
    {
        $ephePath = self::$ephePath !== '' ? self::$ephePath
            : (function_exists('config')
                ? config('panchang.ephe_path', ($_ENV['PANCHANG_EPHE_PATH'] ?? false) !== false ? ($_ENV['PANCHANG_EPHE_PATH'] ?? false) : '')
                : (($_ENV['PANCHANG_EPHE_PATH'] ?? false) !== false ? ($_ENV['PANCHANG_EPHE_PATH'] ?? false) : ''));

        if (is_string($ephePath) && $ephePath !== '' && file_exists($ephePath)) {
            $sweph->swe_set_ephe_path($ephePath);
        }
    }

    private static function setEphemerisPath(string $ephePath): void
    {
        self::$ephePath = $ephePath;
    }
}
