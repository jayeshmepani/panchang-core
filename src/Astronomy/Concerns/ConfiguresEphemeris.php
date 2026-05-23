<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Astronomy\Concerns;

use JmeEph\FFI\JmeEphFFI;

trait ConfiguresEphemeris
{
    private static string $ephePath = '';

    private function initializeEphemerisPath(JmeEphFFI $jme): void
    {
        $ephePath = self::$ephePath !== '' ? self::$ephePath
            : (function_exists('config')
                ? config('panchang.ephe_path', $_ENV['PANCHANG_EPHE_PATH'] ?? '')
                : ($_ENV['PANCHANG_EPHE_PATH'] ?? ''));

        if (is_string($ephePath) && $ephePath !== '' && file_exists($ephePath)) {
            $jme->jme_set_ephemeris_path($ephePath);
        }
    }

    private static function setEphemerisPath(string $ephePath): void
    {
        self::$ephePath = $ephePath;
    }
}
