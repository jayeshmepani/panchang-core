<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Core\Constants;

use RuntimeException;

/**
 * Astrology Constants.
 *
 * Provides access to astrological data (nakshatras, yogas, karanas, etc.)
 * from a JSON file bundled with the package.
 */
final readonly class AstrologyConstants
{
    /**
     * Get astrology constant by key.
     *
     * @param string $key Constant key (e.g., 'NAKSHATRA_NAMES', 'YOGA_NAMES')
     *
     * @throws RuntimeException If data file is missing or invalid
     *
     * @return mixed Constant value
     */
    public static function get(string $key): mixed
    {
        static $data = null;

        if (!is_array($data)) {
            $path = __DIR__ . '/../../../data/astrology_constants.json';
            if (!file_exists($path)) {
                throw new RuntimeException('Missing astrology_constants.json. Please ensure package data files are installed.');
            }

            $raw = file_get_contents($path);
            $decoded = json_decode($raw ?: '', true);
            if (!is_array($decoded)) {
                throw new RuntimeException('Invalid astrology_constants.json');
            }

            $data = $decoded;
        }

        return $data[$key] ?? null;
    }
}
