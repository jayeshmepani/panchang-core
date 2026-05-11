<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Facades;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Facade;
use JayeshMepani\PanchangCore\Core\Enums\CalendarType;
use JayeshMepani\PanchangCore\Panchanga\PanchangService;
use Override;

/**
 * Panchang Facade.
 *
 * @method static array getDayDetails(CarbonImmutable $date, float $lat, float $lon, string $tz, float $elevation = 0.0, ?CarbonImmutable $calculationAt = null, CalendarType|string $calendarType = \JayeshMepani\PanchangCore\Core\Enums\CalendarType::Amanta, array $options = [])
 * @method static array getFestivalSnapshot(CarbonImmutable $date, float $lat, float $lon, string $tz, float $elevation = 0.0, ?CarbonImmutable $calculationAt = null, CalendarType|string $calendarType = \JayeshMepani\PanchangCore\Core\Enums\CalendarType::Amanta)
 * @method static array getElectionalSnapshot(CarbonImmutable $date, float $lat, float $lon, string $tz, float $elevation = 0.0, array $options = [])
 * @method static array getDailyMuhurtaEvaluation(CarbonImmutable $date, float $lat, float $lon, string $tz, ?CarbonImmutable $currentAt = null, float $elevation = 0.0, array $options = [])
 * @method static array getMonthCalendar(int $year, int $month, float $lat, float $lon, string $tz, float $elevation = 0.0, array $options = [], ?CarbonImmutable $calculationAt = null, CalendarType|string $calendarType = \JayeshMepani\PanchangCore\Core\Enums\CalendarType::Amanta)
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
