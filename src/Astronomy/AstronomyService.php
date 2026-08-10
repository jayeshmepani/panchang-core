<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Astronomy;

use Carbon\CarbonImmutable;
use FFI;
use FFI\CData;
use JayeshMepani\PanchangCore\Astronomy\Concerns\ConfiguresEphemeris;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JmeEph\FFI\JmeEphFFI;
use RuntimeException;

/**
 * Astronomy Service.
 *
 * Provides astronomical calculations using the JME native wrapper:
 * - Julian Day conversions
 * - Ayanamsa calculations
 * - Planet position calculations
 * - Sunrise/sunset calculations
 */
class AstronomyService
{
    use ConfiguresEphemeris;

    private const int CACHE_MAX = 2000;

    private const int CACHE_TRIM_TO = 1000;

    /** @var array<string, float> */
    private array $julianDayCache = [];

    /** @var array<string, array<string, float>> */
    private array $planetLongitudeCache = [];

    /** @var array<string, float> */
    private array $ascendantCache = [];

    /** @var array<string, float> */
    private array $ayanamsaCache = [];

    private readonly CData $xxBuffer;

    private readonly CData $serrBuffer;

    private readonly CData $cuspsBuffer;

    private readonly CData $ascmcBuffer;

    public function __construct(private JmeEphFFI $jme)
    {
        $this->initializeEphemerisPath($this->jme);
        $ffi = $this->jme->getFFI();
        $this->xxBuffer = $ffi->new('double[6]');
        $this->serrBuffer = $ffi->new('char[256]');
        $this->cuspsBuffer = $ffi->new('double[13]');
        $this->ascmcBuffer = $ffi->new('double[10]');
    }

    public function clearCaches(): void
    {
        $this->julianDayCache = [];
        $this->planetLongitudeCache = [];
        $this->ascendantCache = [];
        $this->ayanamsaCache = [];
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

    /**
     * Convert birth array to Julian Day (UTC).
     *
     * @param array{year:int, month:int, day:int, hour:int, minute:int, second:int, timezone:string} $birth Birth data
     *
     * @return float Julian Day in UTC
     */
    public function toJulianDayUtc(array $birth): float
    {
        $cacheKey = $this->birthCacheKey($birth);
        if (isset($this->julianDayCache[$cacheKey])) {
            return $this->julianDayCache[$cacheKey];
        }

        $local = CarbonImmutable::create(
            $birth['year'],
            $birth['month'],
            $birth['day'],
            $birth['hour'],
            $birth['minute'],
            $birth['second'],
            $birth['timezone']
        );

        $utc = $local->setTimezone('UTC');
        $hourDecimal = (int) $utc->format('H')
            + ((int) $utc->format('i')) / 60.0
            + ((int) $utc->format('s')) / 3600.0;

        $this->trimCache($this->julianDayCache);

        return $this->julianDayCache[$cacheKey] = $this->jme->jme_julian_day(
            $utc->year,
            $utc->month,
            $utc->day,
            $hourDecimal,
            JmeEphFFI::JME_CALENDAR_GREGORIAN
        );
    }

    public function setAyanamsa(float $jd): void
    {
        // Configure project default sidereal reference (PanchangCore uses Lahiri/Chitrapaksha by default).
        $this->jme->jme_set_sidereal_mode(JmeEphFFI::JME_SIDEREAL_LAHIRI, 0.0, 0.0);
    }

    /**
     * Canonical geocentric planet body IDs for DE/JPL kernels.
     *
     * Lossless reference: astrology JME_PLANET_IDS / jme_compat.py body map
     * and jpl-ephemeris metadata.c NAIF mapping.
     *
     * Mars/Jupiter/Saturn MUST use planetary barycenters (NAIF 4/5/6).
     * Planet-center IDs (499/599/699) are not reliably available from
     * de440.bsp alone under CALCEPH.
     *
     * @return array<string, int>
     */
    public static function jmePlanetBodyIds(): array
    {
        return [
            'Sun' => JmeEphFFI::JME_BODY_SUN,
            'Moon' => JmeEphFFI::JME_BODY_MOON,
            'Mars' => JmeEphFFI::JME_BODY_MARS_BARYCENTER,
            'Mercury' => JmeEphFFI::JME_BODY_MERCURY,
            'Jupiter' => JmeEphFFI::JME_BODY_JUPITER_BARYCENTER,
            'Venus' => JmeEphFFI::JME_BODY_VENUS,
            'Saturn' => JmeEphFFI::JME_BODY_SATURN_BARYCENTER,
            'Rahu' => JmeEphFFI::JME_BODY_MEAN_NODE,
        ];
    }

    /**
     * Sidereal geocentric apparent flags matching astrology jme_compat.calc_ut().
     *
     * astrology uses HIGH_PRECISION|SIDEREAL, then auto-ORs NO_ABERRATION for
     * apparent positions (JPL double-aberration compensation), unless true /
     * heliocentric / barycentric vector flags are set.
     */
    public static function jmeSiderealApparentFlags(int $extraFlags = 0): int
    {
        $flags = JmeEphFFI::JME_CALC_HIGH_PRECISION
            | JmeEphFFI::JME_CALC_SIDEREAL
            | $extraFlags;

        $skipNoAberration = (bool) ($flags & (
            JmeEphFFI::JME_CALC_TRUE_POSITION
            | JmeEphFFI::JME_CALC_HELIOCENTRIC
            | JmeEphFFI::JME_CALC_BARYCENTRIC
        ));

        if (!$skipNoAberration) {
            $flags |= JmeEphFFI::JME_CALC_NO_ABERRATION;
        }

        return $flags;
    }

    public function getPlanets(array $birth): array
    {
        $cacheKey = $this->birthCacheKey($birth);
        if (isset($this->planetLongitudeCache[$cacheKey])) {
            return $this->planetLongitudeCache[$cacheKey];
        }

        $jd = $this->toJulianDayUtc($birth);
        $this->setAyanamsa($jd);

        $flags = self::jmeSiderealApparentFlags();
        $out = [];
        foreach (self::jmePlanetBodyIds() as $name => $pid) {
            $out[$name] = $this->calcBodyLongitudeAtJd($jd, $pid, $flags);
        }

        $out['Ketu'] = AstroCore::normalize($out['Rahu'] + 180.0);

        $this->trimCache($this->planetLongitudeCache);

        return $this->planetLongitudeCache[$cacheKey] = $out;
    }

    /**
     * Sidereal body longitude at JD via the configured JME engine (JPL when enabled).
     *
     * Throws on native calculation failure. No Moshier fallback — keep ENGINE=JPL
     * and use barycenter body IDs for outer planets (astrology lossless path).
     */
    public function calcBodyLongitudeAtJd(float $jd, int $bodyId, ?int $flags = null): float
    {
        $flags ??= self::jmeSiderealApparentFlags();

        $this->setAyanamsa($jd);

        $rc = $this->jme->jme_calc_ut($jd, $bodyId, $flags, $this->xxBuffer, $this->serrBuffer);
        $lon = AstroCore::normalize((float) $this->xxBuffer[0]);
        $err = FFI::string($this->serrBuffer);

        if ($rc < 0 || !is_finite($lon)) {
            throw new RuntimeException(
                sprintf(
                    'Unable to compute body %d longitude at JD %.8F (rc=%d, err=%s).',
                    $bodyId,
                    $jd,
                    $rc,
                    $err !== '' ? $err : 'empty'
                )
            );
        }

        return $lon;
    }

    public function getAscendant(array $birth): float
    {
        $cacheKey = $this->birthCacheKey($birth);
        if (isset($this->ascendantCache[$cacheKey])) {
            return $this->ascendantCache[$cacheKey];
        }

        $jd = $this->toJulianDayUtc($birth);
        $this->setAyanamsa($jd);

        $ayanamsa = $this->getAyanamsa($jd);

        $this->jme->jme_houses(
            $jd,
            (float) $birth['latitude'],
            (float) $birth['longitude'],
            ord('W'),
            $this->cuspsBuffer,
            $this->ascmcBuffer
        );

        $this->trimCache($this->ascendantCache);

        return $this->ascendantCache[$cacheKey] = AstroCore::normalize($this->ascmcBuffer[0] - $ayanamsa);
    }

    public function getAyanamsa(float $jd): float
    {
        $this->setAyanamsa($jd);
        $cacheKey = sprintf('%.17g', $jd);

        if (isset($this->ayanamsaCache[$cacheKey])) {
            return $this->ayanamsaCache[$cacheKey];
        }

        $this->trimCache($this->ayanamsaCache);

        return $this->ayanamsaCache[$cacheKey] = $this->jme->jme_get_ayanamsa_ut($jd);
    }

    private function trimCache(array &$cache): void
    {
        if (count($cache) >= self::CACHE_MAX) {
            $cache = array_slice($cache, -self::CACHE_TRIM_TO, null, true);
        }
    }

    private function birthCacheKey(array $birth): string
    {
        return implode('|', [
            (string) ($birth['year'] ?? ''),
            (string) ($birth['month'] ?? ''),
            (string) ($birth['day'] ?? ''),
            (string) ($birth['hour'] ?? ''),
            (string) ($birth['minute'] ?? ''),
            (string) ($birth['second'] ?? ''),
            (string) ($birth['timezone'] ?? ''),
            sprintf('%.12F', (float) ($birth['latitude'] ?? 0.0)),
            sprintf('%.12F', (float) ($birth['longitude'] ?? 0.0)),
            sprintf('%.6F', (float) ($birth['elevation'] ?? 0.0)),
        ]);
    }
}
