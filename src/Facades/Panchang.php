<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Facades;

use Illuminate\Support\Facades\Facade;
use JayeshMepani\PanchangCore\Panchanga\PanchangService;

/**
 * Panchang Facade.
 *
 * @method static array getDayDetails(\Carbon\CarbonImmutable $date, float $lat, float $lon, string $tz, float $elevation = 0.0, ?\Carbon\CarbonImmutable $ayanamsaAt = null, array $options = [])
 * @method static array getFestivalSnapshot(\Carbon\CarbonImmutable $date, float $lat, float $lon, string $tz, float $elevation = 0.0)
 *
 * @see \JayeshMepani\PanchangCore\Panchanga\PanchangService
 */
class Panchang extends Facade
{
    /** Get the registered name of the component. */
    protected static function getFacadeAccessor(): string
    {
        return PanchangService::class;
    }
}
