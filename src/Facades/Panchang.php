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
 * @method static array getSelectedDetails(CarbonImmutable $date, float $lat, float $lon, string $tz, array $sections, float $elevation = 0.0, ?CarbonImmutable $calculationAt = null, CalendarType|string $calendarType = \JayeshMepani\PanchangCore\Core\Enums\CalendarType::Amanta, array $options = [])
 * @method static array getFields(CarbonImmutable $date, float $lat, float $lon, string $tz, array $fields, float $elevation = 0.0, ?CarbonImmutable $calculationAt = null, CalendarType|string $calendarType = \JayeshMepani\PanchangCore\Core\Enums\CalendarType::Amanta, array $options = [])
 * @method static array getSection(CarbonImmutable $date, float $lat, float $lon, string $tz, string $section, float $elevation = 0.0, ?CarbonImmutable $calculationAt = null, CalendarType|string $calendarType = \JayeshMepani\PanchangCore\Core\Enums\CalendarType::Amanta, array $options = [])
 * @method static array getBasicDetails(CarbonImmutable $date, float $lat, float $lon, string $tz, float $elevation = 0.0, ?CarbonImmutable $calculationAt = null, CalendarType|string $calendarType = \JayeshMepani\PanchangCore\Core\Enums\CalendarType::Amanta, array $options = [])
 * @method static array getPanchanga(CarbonImmutable $date, float $lat, float $lon, string $tz, float $elevation = 0.0, ?CarbonImmutable $calculationAt = null, CalendarType|string $calendarType = \JayeshMepani\PanchangCore\Core\Enums\CalendarType::Amanta, array $options = [])
 * @method static array getTithi(CarbonImmutable $date, float $lat, float $lon, string $tz, float $elevation = 0.0, ?CarbonImmutable $calculationAt = null, CalendarType|string $calendarType = \JayeshMepani\PanchangCore\Core\Enums\CalendarType::Amanta, array $options = [])
 * @method static array getCurrentTithi(CarbonImmutable $date, float $lat, float $lon, string $tz, ?CarbonImmutable $calculationAt = null, float $elevation = 0.0, CalendarType|string $calendarType = \JayeshMepani\PanchangCore\Core\Enums\CalendarType::Amanta, array $options = [])
 * @method static array getNakshatra(CarbonImmutable $date, float $lat, float $lon, string $tz, float $elevation = 0.0, ?CarbonImmutable $calculationAt = null, CalendarType|string $calendarType = \JayeshMepani\PanchangCore\Core\Enums\CalendarType::Amanta, array $options = [])
 * @method static array getCurrentNakshatra(CarbonImmutable $date, float $lat, float $lon, string $tz, ?CarbonImmutable $calculationAt = null, float $elevation = 0.0, CalendarType|string $calendarType = \JayeshMepani\PanchangCore\Core\Enums\CalendarType::Amanta, array $options = [])
 * @method static array getYoga(CarbonImmutable $date, float $lat, float $lon, string $tz, float $elevation = 0.0, ?CarbonImmutable $calculationAt = null, CalendarType|string $calendarType = \JayeshMepani\PanchangCore\Core\Enums\CalendarType::Amanta, array $options = [])
 * @method static array getCurrentYoga(CarbonImmutable $date, float $lat, float $lon, string $tz, ?CarbonImmutable $calculationAt = null, float $elevation = 0.0, CalendarType|string $calendarType = \JayeshMepani\PanchangCore\Core\Enums\CalendarType::Amanta, array $options = [])
 * @method static array getKarana(CarbonImmutable $date, float $lat, float $lon, string $tz, float $elevation = 0.0, ?CarbonImmutable $calculationAt = null, CalendarType|string $calendarType = \JayeshMepani\PanchangCore\Core\Enums\CalendarType::Amanta, array $options = [])
 * @method static array getCurrentKarana(CarbonImmutable $date, float $lat, float $lon, string $tz, ?CarbonImmutable $calculationAt = null, float $elevation = 0.0, CalendarType|string $calendarType = \JayeshMepani\PanchangCore\Core\Enums\CalendarType::Amanta, array $options = [])
 * @method static array getVara(CarbonImmutable $date, float $lat, float $lon, string $tz, float $elevation = 0.0, ?CarbonImmutable $calculationAt = null, CalendarType|string $calendarType = \JayeshMepani\PanchangCore\Core\Enums\CalendarType::Amanta, array $options = [])
 * @method static array getSpecialYogas(CarbonImmutable $date, float $lat, float $lon, string $tz, float $elevation = 0.0, ?CarbonImmutable $calculationAt = null, CalendarType|string $calendarType = \JayeshMepani\PanchangCore\Core\Enums\CalendarType::Amanta, array $options = [])
 * @method static array getMuhurtaFullDay(CarbonImmutable $date, float $lat, float $lon, string $tz, float $elevation = 0.0, ?CarbonImmutable $calculationAt = null, CalendarType|string $calendarType = \JayeshMepani\PanchangCore\Core\Enums\CalendarType::Amanta, array $options = [])
 * @method static array getAbhijitMuhurta(CarbonImmutable $date, float $lat, float $lon, string $tz, float $elevation = 0.0, ?CarbonImmutable $calculationAt = null, CalendarType|string $calendarType = \JayeshMepani\PanchangCore\Core\Enums\CalendarType::Amanta, array $options = [])
 * @method static array getVarjyam(CarbonImmutable $date, float $lat, float $lon, string $tz, float $elevation = 0.0, ?CarbonImmutable $calculationAt = null, CalendarType|string $calendarType = \JayeshMepani\PanchangCore\Core\Enums\CalendarType::Amanta, array $options = [])
 * @method static array getDharmaSindhu(CarbonImmutable $date, float $lat, float $lon, string $tz, float $elevation = 0.0, ?CarbonImmutable $calculationAt = null, CalendarType|string $calendarType = \JayeshMepani\PanchangCore\Core\Enums\CalendarType::Amanta, array $options = [])
 * @method static array getFestivalSnapshot(CarbonImmutable $date, float $lat, float $lon, string $tz, float $elevation = 0.0, ?CarbonImmutable $calculationAt = null, CalendarType|string $calendarType = \JayeshMepani\PanchangCore\Core\Enums\CalendarType::Amanta)
 * @method static array getElectionalSnapshot(CarbonImmutable $date, float $lat, float $lon, string $tz, float $elevation = 0.0, array $options = [])
 * @method static array getDailyMuhurtaEvaluation(CarbonImmutable $date, float $lat, float $lon, string $tz, ?CarbonImmutable $currentAt = null, float $elevation = 0.0, array $options = [])
 * @method static array getMonthCalendar(int $year, int $month, float $lat, float $lon, string $tz, float $elevation = 0.0, array $options = [], ?CarbonImmutable $calculationAt = null, CalendarType|string $calendarType = \JayeshMepani\PanchangCore\Core\Enums\CalendarType::Amanta)
 * @method static array getMonthFields(int $year, int $month, float $lat, float $lon, string $tz, array $fields, float $elevation = 0.0, array $options = [], ?CarbonImmutable $calculationAt = null, CalendarType|string $calendarType = \JayeshMepani\PanchangCore\Core\Enums\CalendarType::Amanta)
 * @method static array getMonthRangeCalendar(int $fromYear, int $fromMonth, int $toYear, int $toMonth, float $lat, float $lon, string $tz, float $elevation = 0.0, array $options = [], ?CarbonImmutable $calculationAt = null, CalendarType|string $calendarType = \JayeshMepani\PanchangCore\Core\Enums\CalendarType::Amanta)
 * @method static array getMonthRangeFields(int $fromYear, int $fromMonth, int $toYear, int $toMonth, float $lat, float $lon, string $tz, array $fields, float $elevation = 0.0, array $options = [], ?CarbonImmutable $calculationAt = null, CalendarType|string $calendarType = \JayeshMepani\PanchangCore\Core\Enums\CalendarType::Amanta)
 * @method static array getFestivalRangeCalendar(int $fromYear, int $fromMonth, int $toYear, int $toMonth, float $lat, float $lon, string $tz, float $elevation = 0.0, ?CarbonImmutable $calculationAt = null, CalendarType|string $calendarType = \JayeshMepani\PanchangCore\Core\Enums\CalendarType::Amanta)
 * @method static array getFestivalRangeCalendarOnlyFestivals(int $fromYear, int $fromMonth, int $toYear, int $toMonth, float $lat, float $lon, string $tz, float $elevation = 0.0, ?CarbonImmutable $calculationAt = null, CalendarType|string $calendarType = \JayeshMepani\PanchangCore\Core\Enums\CalendarType::Amanta)
 * @method static array getVratRangeCalendar(int $fromYear, int $fromMonth, int $toYear, int $toMonth, float $lat, float $lon, string $tz, float $elevation = 0.0, ?CarbonImmutable $calculationAt = null, CalendarType|string $calendarType = \JayeshMepani\PanchangCore\Core\Enums\CalendarType::Amanta)
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
