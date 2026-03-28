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
 * @method static array getElectionalSnapshot(\Carbon\CarbonImmutable $date, float $lat, float $lon, string $tz, ?int $birthNakshatraIdx = null, ?int $birthMoonSignIdx = null, float $elevation = 0.0, array $options = [])
 * @method static array getActivityMuhurtas(\Carbon\CarbonImmutable $date, float $lat, float $lon, string $tz, string $activity, ?int $birthNakshatraIdx = null, ?int $birthMoonSignIdx = null, float $elevation = 0.0, array $options = [])
 * @method static array getVivahaMuhurtas(\Carbon\CarbonImmutable $date, float $lat, float $lon, string $tz, ?int $birthNakshatraIdx = null, ?int $birthMoonSignIdx = null, float $elevation = 0.0, array $options = [])
 * @method static array getGrihaPraveshaMuhurtas(\Carbon\CarbonImmutable $date, float $lat, float $lon, string $tz, ?int $birthNakshatraIdx = null, ?int $birthMoonSignIdx = null, float $elevation = 0.0, array $options = [])
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
