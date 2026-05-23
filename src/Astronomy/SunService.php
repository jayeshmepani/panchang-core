<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Astronomy;

use Carbon\CarbonImmutable;
use FFI;
use FFI\CData;
use JayeshMepani\PanchangCore\Astronomy\Concerns\ConfiguresEphemeris;
use JayeshMepani\PanchangCore\Support\DebugTrace;
use JmeEph\FFI\JmeEphFFI;
use RuntimeException;

class SunService
{
    use ConfiguresEphemeris;

    private const int RISE_TRANS_FLAGS = JmeEphFFI::JME_CALC_TRUE_POSITION;

    /** @var array<string, array{0: CarbonImmutable, 1: CarbonImmutable}> */
    private array $sunriseSunsetCache = [];

    /** @var array<string, array{0: ?CarbonImmutable, 1: ?CarbonImmutable}> */
    private array $moonriseMoonsetCache = [];

    public function __construct(private JmeEphFFI $jme)
    {
        $this->initializeEphemerisPath($this->jme);
    }

    /**
     * Configure service (optional, for standalone usage).
     *
     * @param string $ephePath Ephemeris path (empty for default)
     */
    public static function configure(string $ephePath = ''): void
    {
        self::setEphemerisPath($ephePath);
    }

    /** Get sunrise and sunset as timezone-aware Carbon instances. */
    public function getSunriseSunset(array $birth): array
    {
        $cacheKey = $this->dateLocationCacheKey($birth);
        if (isset($this->sunriseSunsetCache[$cacheKey])) {
            return $this->sunriseSunsetCache[$cacheKey];
        }

        DebugTrace::log('sun.sunrise_sunset', 'starting rise/set calculation', [
            'date' => sprintf('%04d-%02d-%02d', $birth['year'], $birth['month'], $birth['day']),
            'timezone' => $birth['timezone'],
            'latitude' => $birth['latitude'],
            'longitude' => $birth['longitude'],
        ]);
        $jd = $this->localDateStartJulianDay($birth);

        /** @var CData $geopos */
        $geopos = $this->jme->getFFI()->new('double[3]');
        $geopos[0] = (float) $birth['longitude'];
        $geopos[1] = (float) $birth['latitude'];
        $geopos[2] = (float) ($birth['elevation'] ?? 0.0);

        $tz = $birth['timezone'];
        $sunrise = $this->runRiseTransit($jd, $geopos, JmeEphFFI::JME_BODY_SUN, JmeEphFFI::JME_RISE_RISE, $tz);
        $sunset = $this->runRiseTransit($jd, $geopos, JmeEphFFI::JME_BODY_SUN, JmeEphFFI::JME_RISE_SET, $tz);
        DebugTrace::log('sun.sunrise_sunset', 'completed rise/set calculation', [
            'sunrise' => $sunrise->toIso8601String(),
            'sunset' => $sunset->toIso8601String(),
        ]);

        return $this->sunriseSunsetCache[$cacheKey] = [$sunrise, $sunset];
    }

    public function getMoonriseMoonset(array $birth): array
    {
        $cacheKey = $this->dateLocationCacheKey($birth);
        if (isset($this->moonriseMoonsetCache[$cacheKey])) {
            return $this->moonriseMoonsetCache[$cacheKey];
        }

        $dayStart = $this->localDateStart($birth);
        $dayEnd = $dayStart->addDay();
        $jd = $this->toJulianDay($dayStart);

        $geopos = $this->newGeoPos($birth);
        $moonrise = $this->runRiseTransit($jd, $geopos, JmeEphFFI::JME_BODY_MOON, JmeEphFFI::JME_RISE_RISE, $birth['timezone'], false);
        if ($moonrise === null) {
            return $this->moonriseMoonsetCache[$cacheKey] = [null, null];
        }

        if ($moonrise->greaterThanOrEqualTo($dayEnd)) {
            return $this->moonriseMoonsetCache[$cacheKey] = [null, null];
        }

        $moonset = $this->runRiseTransit(
            $this->toJulianDay($moonrise->addSecond()),
            $geopos,
            JmeEphFFI::JME_BODY_MOON,
            JmeEphFFI::JME_RISE_SET,
            $birth['timezone'],
            false
        );

        return $this->moonriseMoonsetCache[$cacheKey] = [$moonrise, $moonset];
    }

    public function jdToCarbonPublic(float $jd, string $timezone): CarbonImmutable
    {
        return $this->jdToCarbon($jd, $timezone);
    }

    public function getTwilightTimes(array $birth): array
    {
        return [
            'civil' => $this->getRiseSetWithFlag($birth, JmeEphFFI::JME_RISE_CIVIL_TWILIGHT),
            'nautical' => $this->getRiseSetWithFlag($birth, JmeEphFFI::JME_RISE_NAUTICAL_TWILIGHT),
            'astronomical' => $this->getRiseSetWithFlag($birth, JmeEphFFI::JME_RISE_ASTRONOMICAL_TWILIGHT),
        ];
    }

    public function getSolarTransits(array $birth): array
    {
        $jd = $this->localDateStartJulianDay($birth);
        $geopos = $this->newGeoPos($birth);

        $solarNoon = $this->runRiseTransit($jd, $geopos, JmeEphFFI::JME_BODY_SUN, JmeEphFFI::JME_RISE_MERIDIAN_TRANSIT, $birth['timezone']);
        $solarMidnight = $this->runRiseTransit($jd, $geopos, JmeEphFFI::JME_BODY_SUN, JmeEphFFI::JME_RISE_ANTI_MERIDIAN_TRANSIT, $birth['timezone']);

        return [
            'solar_noon' => $solarNoon,
            'solar_midnight' => $solarMidnight,
        ];
    }

    /** Get birth moment as timezone-aware Carbon. */
    public function getBirthDatetime(array $birth): CarbonImmutable
    {
        return CarbonImmutable::create(
            (int) $birth['year'],
            (int) $birth['month'],
            (int) $birth['day'],
            (int) $birth['hour'],
            (int) $birth['minute'],
            (int) $birth['second'],
            $birth['timezone']
        );
    }

    /** Determine if birth occurred during daytime. */
    public function isDayBirth(array $birth): bool
    {
        [$sunrise, $sunset] = $this->getSunriseSunset($birth);
        $birthDt = $this->getBirthDatetime($birth);

        return $birthDt->greaterThanOrEqualTo($sunrise)
            && $birthDt->lessThan($sunset);
    }

    /** Get interval duration (seconds), start time, and day/night flag. */
    public function getIntervalAndStart(array $birth): array
    {
        [$sunrise, $sunset] = $this->getSunriseSunset($birth);
        $birthDt = $this->getBirthDatetime($birth);
        $isDay = $birthDt->greaterThanOrEqualTo($sunrise)
            && $birthDt->lessThan($sunset);

        if ($isDay) {
            $interval = $sunset->diffInSeconds($sunrise);
            $start = $sunrise;
        } else {
            $nextDate = CarbonImmutable::create(
                (int) $birth['year'],
                (int) $birth['month'],
                (int) $birth['day'],
                0,
                0,
                0,
                $birth['timezone']
            )->addDay();

            $nextBirth = $birth;
            $nextBirth['year'] = $nextDate->year;
            $nextBirth['month'] = $nextDate->month;
            $nextBirth['day'] = $nextDate->day;

            [$nextSunrise] = $this->getSunriseSunset($nextBirth);

            $interval = $nextSunrise->diffInSeconds($sunset);
            $start = $sunset;
        }

        return [$interval, $start, $isDay];
    }

    private function getRiseSetWithFlag(array $birth, int $twilightFlag): array
    {
        $jd = $this->localDateStartJulianDay($birth);
        $geopos = $this->newGeoPos($birth);

        $rise = $this->runRiseTransit($jd, $geopos, JmeEphFFI::JME_BODY_SUN, JmeEphFFI::JME_RISE_RISE | $twilightFlag, $birth['timezone']);
        $set = $this->runRiseTransit($jd, $geopos, JmeEphFFI::JME_BODY_SUN, JmeEphFFI::JME_RISE_SET | $twilightFlag, $birth['timezone']);

        return ['dawn' => $rise, 'dusk' => $set];
    }

    private function localDateStartJulianDay(array $birth): float
    {
        return $this->toJulianDay($this->localDateStart($birth));
    }

    private function localDateStart(array $birth): CarbonImmutable
    {
        return CarbonImmutable::create(
            (int) $birth['year'],
            (int) $birth['month'],
            (int) $birth['day'],
            0,
            0,
            0,
            (string) $birth['timezone']
        );
    }

    private function toJulianDay(CarbonImmutable $time): float
    {
        $utc = $time->setTimezone('UTC');

        $hourDecimal = (int) $utc->format('H')
            + ((int) $utc->format('i')) / 60.0
            + (((int) $utc->format('s')) + ((int) $utc->format('u') / 1_000_000)) / 3600.0;

        return $this->jme->jme_julian_day(
            $utc->year,
            $utc->month,
            $utc->day,
            $hourDecimal,
            JmeEphFFI::JME_CALENDAR_GREGORIAN
        );
    }

    private function runRiseTransit(float $jd, object $geopos, int $body, int $eventFlag, string $timezone, bool $strict = true): ?CarbonImmutable
    {
        $tret = $this->jme->getFFI()->new('double[1]');
        $serr = $this->jme->getFFI()->new('char[256]');
        $rc = $this->jme->jme_rise_trans(
            $jd,
            $body,
            null,
            self::RISE_TRANS_FLAGS,
            $eventFlag,
            $geopos,
            1013.25,
            15.0,
            $tret,
            $serr
        );
        $error = FFI::string($serr);
        DebugTrace::log('sun.rise_transit', 'native rise/transit returned', [
            'jd' => $jd,
            'body' => $body,
            'event_flag' => $eventFlag,
            'rc' => $rc,
            'tret' => (float) $tret[0],
            'error' => $error,
        ]);
        if ($rc !== JmeEphFFI::JME_OK || (float) $tret[0] <= 0.0) {
            if (!$strict) {
                return null;
            }

            throw new RuntimeException('jme_rise_trans failed: ' . $error);
        }

        return $this->jdToCarbon($tret[0], $timezone);
    }

    /** @phpstan-ignore return.unusedType */
    private function newGeoPos(array $birth): object
    {
        /** @var CData $geopos */
        $geopos = $this->jme->getFFI()->new('double[3]');
        $geopos[0] = (float) $birth['longitude'];
        $geopos[1] = (float) $birth['latitude'];
        $geopos[2] = (float) ($birth['elevation'] ?? 0.0);
        return $geopos;
    }

    /** Convert a Julian Day number (UT) to a timezone-aware Carbon instance. */
    private function jdToCarbon(float $jd, string $timezone): CarbonImmutable
    {
        $y = $this->jme->getFFI()->new('int[1]');
        $mo = $this->jme->getFFI()->new('int[1]');
        $d = $this->jme->getFFI()->new('int[1]');
        $h = $this->jme->getFFI()->new('int[1]');
        $mi = $this->jme->getFFI()->new('int[1]');
        $s = $this->jme->getFFI()->new('double[1]');

        $this->jme->jme_jd_to_utc(
            $jd,
            JmeEphFFI::JME_CALENDAR_GREGORIAN,
            $y,
            $mo,
            $d,
            $h,
            $mi,
            $s
        );

        $sInt = (int) $s[0];
        $micro = (int) floor(($s[0] - $sInt) * 1_000_000);

        return CarbonImmutable::create(
            (int) $y[0],
            (int) $mo[0],
            (int) $d[0],
            (int) $h[0],
            (int) $mi[0],
            $sInt,
            'UTC'
        )
            ->addMicroseconds($micro)
            ->setTimezone($timezone);
    }

    private function dateLocationCacheKey(array $birth): string
    {
        return implode('|', [
            (string) ($birth['year'] ?? ''),
            (string) ($birth['month'] ?? ''),
            (string) ($birth['day'] ?? ''),
            (string) ($birth['timezone'] ?? ''),
            sprintf('%.12F', (float) ($birth['latitude'] ?? 0.0)),
            sprintf('%.12F', (float) ($birth['longitude'] ?? 0.0)),
            sprintf('%.6F', (float) ($birth['elevation'] ?? 0.0)),
        ]);
    }
}
