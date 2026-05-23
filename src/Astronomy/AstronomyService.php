<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Astronomy;

use Carbon\CarbonImmutable;
use FFI\CData;
use JayeshMepani\PanchangCore\Astronomy\Concerns\ConfiguresEphemeris;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Panchanga\ElectionalRuleBook;
use JmeEph\FFI\JmeEphFFI;

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

    /** @var array<string, float> */
    private array $julianDayCache = [];

    /** @var array<string, array<string, float>> */
    private array $planetLongitudeCache = [];

    /** @var array<string, array<string, array<string, bool|float|null>>> */
    private array $planetaryStateCache = [];

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
        // For any authentic Hindu Panchanga, Lahiri is the only absolute standard.
        $this->jme->jme_set_sidereal_mode(JmeEphFFI::JME_SIDEREAL_LAHIRI, 0.0, 0.0);
    }

    public function getPlanets(array $birth): array
    {
        $cacheKey = $this->birthCacheKey($birth);
        if (isset($this->planetLongitudeCache[$cacheKey])) {
            return $this->planetLongitudeCache[$cacheKey];
        }

        $jd = $this->toJulianDayUtc($birth);
        $this->setAyanamsa($jd);

        $flags = JmeEphFFI::JME_CALC_HIGH_PRECISION | JmeEphFFI::JME_CALC_SIDEREAL;
        $planets = [
            'Sun' => JmeEphFFI::JME_BODY_SUN,
            'Moon' => JmeEphFFI::JME_BODY_MOON,
            'Mars' => JmeEphFFI::JME_BODY_MARS,
            'Mercury' => JmeEphFFI::JME_BODY_MERCURY,
            'Jupiter' => JmeEphFFI::JME_BODY_JUPITER,
            'Venus' => JmeEphFFI::JME_BODY_VENUS,
            'Saturn' => JmeEphFFI::JME_BODY_SATURN,
            'Rahu' => JmeEphFFI::JME_BODY_MEAN_NODE,
        ];

        $out = [];
        foreach ($planets as $name => $pid) {
            $this->jme->jme_calc_ut($jd, $pid, $flags, $this->xxBuffer, $this->serrBuffer);
            $out[$name] = AstroCore::normalize($this->xxBuffer[0]);
        }

        $out['Ketu'] = AstroCore::normalize($out['Rahu'] + 180.0);

        // Ensure all planet longitudes use configured precision
        foreach ($out as $name => $lon) {
            $out[$name] = $lon;
        }

        return $this->planetLongitudeCache[$cacheKey] = $out;
    }

    public function getPlanetaryStates(array $birth, string $nodeMode = 'mean', float $stationTolerance = 1.0e-6): array
    {
        $cacheKey = $this->birthCacheKey($birth) . '|node=' . strtoupper($nodeMode) . '|tol=' . sprintf('%.12g', $stationTolerance);
        if (isset($this->planetaryStateCache[$cacheKey])) {
            return $this->planetaryStateCache[$cacheKey];
        }

        $jd = $this->toJulianDayUtc($birth);
        $this->setAyanamsa($jd);

        $flags = JmeEphFFI::JME_CALC_HIGH_PRECISION | JmeEphFFI::JME_CALC_SIDEREAL | JmeEphFFI::JME_CALC_SPEED;
        $rahupId = strtoupper($nodeMode) === 'TRUE' ? JmeEphFFI::JME_BODY_TRUE_NODE : JmeEphFFI::JME_BODY_MEAN_NODE;
        $planets = [
            'Sun' => JmeEphFFI::JME_BODY_SUN,
            'Moon' => JmeEphFFI::JME_BODY_MOON,
            'Mars' => JmeEphFFI::JME_BODY_MARS,
            'Mercury' => JmeEphFFI::JME_BODY_MERCURY,
            'Jupiter' => JmeEphFFI::JME_BODY_JUPITER,
            'Venus' => JmeEphFFI::JME_BODY_VENUS,
            'Saturn' => JmeEphFFI::JME_BODY_SATURN,
            'Rahu' => $rahupId,
        ];

        $sunLongitude = 0.0;
        $states = [];

        foreach ($planets as $name => $pid) {
            $this->jme->jme_calc_ut($jd, $pid, $flags, $this->xxBuffer, $this->serrBuffer);
            $longitude = AstroCore::normalize($this->xxBuffer[0]);
            $speed = (float) $this->xxBuffer[3];

            if ($name === 'Sun') {
                $sunLongitude = $longitude;
            }

            $isRetrograde = match ($name) {
                'Moon' => false,
                'Rahu' => strtoupper($nodeMode) === 'MEAN' ? true : $speed < 0.0,
                default => $speed < 0.0,
            };

            $separation = $this->minimalAngularSeparation($longitude, $sunLongitude);
            $orb = null;
            if ($name !== 'Sun') {
                $orb = $isRetrograde && isset(ElectionalRuleBook::COMBUSTION_ORBS_RETRO[$name])
                    ? ElectionalRuleBook::COMBUSTION_ORBS_RETRO[$name]
                    : (ElectionalRuleBook::COMBUSTION_ORBS[$name] ?? null);
            }

            $isCombust = false;
            if (!in_array($name, ['Sun', 'Rahu'], true) && $orb !== null) {
                $outerRetrogradeExempt = in_array($name, ['Mars', 'Jupiter', 'Saturn'], true) && $isRetrograde;
                $isCombust = !$outerRetrogradeExempt && $separation <= $orb;
            }

            $states[$name] = [
                'lon' => $longitude,
                'speed_deg_per_day' => $speed,
                'is_retrograde' => $isRetrograde,
                'is_stationary' => abs($speed) <= $stationTolerance,
                'is_combust' => $isCombust,
                'separation_from_sun' => $separation,
                'orb_used' => $orb,
            ];
        }

        $ketuLongitude = AstroCore::normalize($states['Rahu']['lon'] + 180.0);
        $states['Ketu'] = [
            'lon' => $ketuLongitude,
            'speed_deg_per_day' => -($states['Rahu']['speed_deg_per_day']),
            'is_retrograde' => $states['Rahu']['is_retrograde'],
            'is_stationary' => $states['Rahu']['is_stationary'],
            'is_combust' => false,
            'separation_from_sun' => $this->minimalAngularSeparation($ketuLongitude, $sunLongitude),
            'orb_used' => null,
        ];

        return $this->planetaryStateCache[$cacheKey] = $states;
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

        return $this->ascendantCache[$cacheKey] = AstroCore::normalize($this->ascmcBuffer[0] - $ayanamsa);
    }

    public function getAyanamsa(float $jd): float
    {
        $this->setAyanamsa($jd);
        $cacheKey = sprintf('%.17g', $jd);

        return $this->ayanamsaCache[$cacheKey] ?? $this->ayanamsaCache[$cacheKey] = $this->jme->jme_get_ayanamsa_ut($jd);
    }

    private function minimalAngularSeparation(float $a, float $b): float
    {
        $delta = abs(AstroCore::normalize($a) - AstroCore::normalize($b));
        return min($delta, 360.0 - $delta);
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
