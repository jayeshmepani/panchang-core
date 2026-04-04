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
                ? config('panchang.ephe_path', getenv('PANCHANG_EPHE_PATH') !== false ? getenv('PANCHANG_EPHE_PATH') : '')
                : (getenv('PANCHANG_EPHE_PATH') !== false ? getenv('PANCHANG_EPHE_PATH') : ''));

        if (is_string($ephePath) && $ephePath !== '' && file_exists($ephePath)) {
            $sweph->swe_set_ephe_path($ephePath);
        }
    }

    private static function setEphemerisPath(string $ephePath): void
    {
        self::$ephePath = $ephePath;
    }
}
