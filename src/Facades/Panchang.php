<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Facades;

use Illuminate\Support\Facades\Facade;
use JayeshMepani\PanchangCore\Panchanga\PanchangService;
use Override;

/**
 * Panchang Facade.
 *
 * @method static array getDayDetails(\Carbon\CarbonImmutable $date, float $lat, float $lon, string $tz, float $elevation = 0.0, ?\Carbon\CarbonImmutable $calculationAt = null, array $options = [])
 * @method static array getFestivalSnapshot(\Carbon\CarbonImmutable $date, float $lat, float $lon, string $tz, float $elevation = 0.0)
 * @method static array getElectionalSnapshot(\Carbon\CarbonImmutable $date, float $lat, float $lon, string $tz, float $elevation = 0.0, array $options = [])
 * @method static array getDailyMuhurtaEvaluation(\Carbon\CarbonImmutable $date, float $lat, float $lon, string $tz, ?\Carbon\CarbonImmutable $currentAt = null, float $elevation = 0.0, array $options = [])
 * @method static array getMonthCalendar(int $year, int $month, float $lat, float $lon, string $tz, float $elevation = 0.0, array $options = [])
 *
 * @see \JayeshMepani\PanchangCore\Panchanga\PanchangService
 */
class Panchang extends Facade
{
    /** Get the registered name of the component. */
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return PanchangService::class;
    }
}
