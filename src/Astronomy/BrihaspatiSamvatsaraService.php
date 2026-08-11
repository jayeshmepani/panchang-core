<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Astronomy;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use JayeshMepani\PanchangCore\Core\Enums\Samvatsara;
use JmeEph\FFI\JmeEphFFI;
use RuntimeException;
use Throwable;

/**
 * Bārhaspatya Saṃvatsara Service.
 *
 * Four deliberately distinct timing models are exposed. Package default is
 * always classical_ss (canonical Bārhaspatya). Other models are opt-in
 * strategies — not silent substitutes for the traditional cycle.
 *
 * 1) classical_ss — STATUS: canonical
 *    Sewell-Dīkṣit / present Sūrya-Siddhānta Article-59 Bārhaspatya
 *    mean-motion reckoning. Zero ephemeris dependency.
 *
 * 2) grahalaghava — STATUS: experimental
 *    Historical mean-Jupiter comparison based on Gaṇeśa Daivajña's
 *    Graha-lāghava (epoch Śaka 1442 / 19 March 1520 Julian, mean sunrise
 *    at Ujjayinī). Research/comparison model; not a complete source-locked
 *    ahargaṇa reconstruction.
 *
 * 3) makaranda — STATUS: experimental
 *    Saurapakṣa/Makaranda bīja mean-Jupiter comparison using
 *    364,212 Jupiter revolutions in 1,577,917,828 civil days per Mahāyuga.
 *    Research model; not claimed to be Drik Panchang's published formula.
 *
 * 4) modern_ephemeris — STATUS: astronomical_comparator
 *    Physical geocentric sidereal-Jupiter prograde rāśi ingress via
 *    AstronomyService (JPL when configured). Answers a different question
 *    than traditional Bārhaspatya year reckoning.
 *
 * IMPORTANT ABOUT THE THREE PROJECTION MODELS
 * --------------------------------------------
 * A physical or historical Jupiter longitude gives a 12-rāśi phase, not an
 * independently established phase of the traditional 60-name cycle. To avoid
 * inventing a 60-name epoch, grahalaghava, makaranda and modern_ephemeris all
 * retain the classical_ss 60-name transition sequence and project each
 * classical transition onto the nearest prograde 30° Jupiter ingress in the
 * selected timing model.
 *
 * Thus these three are explicit TIMING COMPARISON models. They are not claimed
 * to be independent textual authorities for the 60-name phase and they are not
 * claimed to reproduce Drik Panchang. Do not promote them to package default
 * merely because one series is closer to a media almanac over a subset of years.
 */
final class BrihaspatiSamvatsaraService
{
    public const string MODEL_CLASSICAL_SS = 'classical_ss';

    public const string MODEL_GRAHALAGHAVA = 'grahalaghava';

    public const string MODEL_MAKARANDA = 'makaranda';

    public const string MODEL_MODERN_EPHEMERIS = 'modern_ephemeris';

    /** Package-canonical default. Prefer this over media-almanac fitting. */
    public const string DEFAULT_MODEL = self::MODEL_CLASSICAL_SS;

    public const string STATUS_CANONICAL = 'canonical';

    public const string STATUS_EXPERIMENTAL = 'experimental';

    public const string STATUS_ASTRONOMICAL_COMPARATOR = 'astronomical_comparator';

    public const string FAMILY_TRADITIONAL_BARHASPATYA = 'traditional_barhaspatya';

    public const string FAMILY_TRADITIONAL_MEAN_JUPITER = 'traditional_mean_jupiter';

    public const string FAMILY_MODERN_EPHEMERIS = 'modern_ephemeris';

    /**
     * Classical Sūrya-Siddhānta mean solar year in civil days.
     *
     * 1,577,917,828 / 4,320,000
     * = 365.25875648148148... days
     */
    public const float SURYA_SIDDHANTA_SOLAR_YEAR_DAYS
        = self::MAHAYUGA_CIVIL_DAYS / self::SOLAR_REVOLUTIONS_PER_MAHAYUGA;

    /**
     * Classical uncorrected mean Jovian sign-year.
     *
     * 1,577,917,828 / (364,220 × 12)
     * = 361.02672102941443... days
     */
    public const float SURYA_SIDDHANTA_JOVIAN_YEAR_DAYS
        = self::MAHAYUGA_CIVIL_DAYS / (self::JUPITER_REVOLUTIONS_PER_MAHAYUGA * 12);

    /**
     * Makaranda bīja mean Jovian sign-year.
     *
     * 1,577,917,828 / (364,212 × 12)
     * = 361.034651064032... days
     */
    public const float MAKARANDA_JOVIAN_YEAR_DAYS
        = self::MAHAYUGA_CIVIL_DAYS
        / (self::MAKARANDA_JUPITER_REVOLUTIONS_PER_MAHAYUGA * 12);

    /** Sūrya-Siddhānta civil days in one Mahāyuga. */
    private const float MAHAYUGA_CIVIL_DAYS = 1_577_917_828.0;

    /** Sūrya-Siddhānta solar revolutions in one Mahāyuga. */
    private const int SOLAR_REVOLUTIONS_PER_MAHAYUGA = 4_320_000;

    /** Present Sūrya-Siddhānta Jupiter revolutions before the bīja. */
    private const int JUPITER_REVOLUTIONS_PER_MAHAYUGA = 364_220;

    /** Makaranda's corrected Jupiter revolutions per Mahāyuga. */
    private const int MAKARANDA_JUPITER_REVOLUTIONS_PER_MAHAYUGA = 364_212;

    private const string CLASSICAL_VARIANT_PLAIN = 'surya_siddhanta_barhaspatya';

    private const string CLASSICAL_VARIANT_BIJA = 'surya_siddhanta_barhaspatya_bija';

    private const string GRAHALAGHAVA_VARIANT
        = 'grahalaghava_1520_mean_jupiter_ingress';

    private const string MAKARANDA_VARIANT
        = 'makaranda_1478_bija_mean_jupiter_ingress';

    private const string MODERN_VARIANT
        = 'modern_ephemeris_sidereal_jupiter_prograde_ingress';

    /**
     * Existing project Kali epoch constant.
     *
     * Kept unchanged for classical_ss so adding comparison models is backward
     * compatible with the already-generated classical payloads.
     */
    private const float KALI_EPOCH_JD = 588_465.5;

    /** Sewell-Dīkṣit Article 59a, mean-Meṣa equivalent form. */
    private const int SS_EXCESS_NUMERATOR = 211;

    private const int SS_EXCESS_DENOMINATOR = 18_000;

    /** Sewell-Dīkṣit Article 59c, bīja, mean-Meṣa equivalent form. */
    private const int BIJA_EXCESS_NUMERATOR = 117;

    private const int BIJA_EXCESS_DENOMINATOR = 10_000;

    /** Sewell-Dīkṣit's timing rule uses 361 days as its working Jovian year. */
    private const float RULE_JOVIAN_DAYS = 361.0;

    /** Deterministic switch for Sewell-Dīkṣit's "after about 1500 A.D.". */
    private const int BIJA_START_EXPIRED_KALI_YEAR = 4601;

    /**
     * Historical Ujjayinī reference meridian used by Sewell for general tables:
     * 75°46' east of Greenwich.
     */
    private const float UJJAIN_REFERENCE_LONGITUDE_DEGREES = 75.0 + (46.0 / 60.0);

    /** Ujjayinī local mean time offset from Greenwich in civil days. */
    private const float UJJAIN_MEAN_TIME_OFFSET_DAYS
        = self::UJJAIN_REFERENCE_LONGITUDE_DEGREES / 360.0;

    /**
     * Makaranda/Saurapakṣa midnight realization of the Kali epoch as an
     * absolute JD: local mean midnight on the Ujjayinī meridian.
     *
     * The existing KALI_EPOCH_JD is a nominal midnight-JD origin. Subtracting
     * the east-longitude mean-time offset converts local Ujjayinī midnight to
     * the corresponding Greenwich/UTC-like absolute JD used by this service.
     */
    private const float MAKARANDA_KALI_EPOCH_ABSOLUTE_JD
        = self::KALI_EPOCH_JD - self::UJJAIN_MEAN_TIME_OFFSET_DAYS;

    /**
     * Graha-lāghava epoch:
     * 19 March 1520 (Julian), mean sunrise at Ujjayinī.
     *
     * Julian-calendar 1520-03-19 00:00 has JD 2276315.5.
     * Mean sunrise is represented by 06:00 local mean time; convert that
     * Ujjayinī local mean time to the absolute JD axis by subtracting the
     * 75°46' east longitude offset.
     */
    private const float GRAHALAGHAVA_EPOCH_JD
        = 2_276_315.5 + 0.25 - self::UJJAIN_MEAN_TIME_OFFSET_DAYS;

    /** Graha-lāghava 11-year computational cakra in civil days. */
    private const float GRAHALAGHAVA_CAKRA_DAYS = 4_016.0;

    /** Jupiter dhruva: 0 signs 26°18'. */
    private const float GRAHALAGHAVA_JUPITER_DHRUVA_DEGREES
        = 26.0 + (18.0 / 60.0);

    /** Jupiter kṣepaka: 7 signs 2°16'. */
    private const float GRAHALAGHAVA_JUPITER_KSEPAKA_DEGREES
        = (7.0 * 30.0) + 2.0 + (16.0 / 60.0);

    /**
     * Verse 1.13 Jupiter increment per completed ahargaṇa day:
     *   1/12 degree - 1/70 arcminute.
     */
    private const float GRAHALAGHAVA_JUPITER_AHARGANA_DEGREES_PER_DAY
        = (1.0 / 12.0) - (1.0 / (70.0 * 60.0));

    /** Verse 1.15 abbreviated Jupiter mean daily motion: 5 arcminutes/day. */
    private const float GRAHALAGHAVA_JUPITER_INTRADAY_DEGREES_PER_DAY
        = 5.0 / 60.0;

    /** Unix epoch as Julian Day. */
    private const float UNIX_EPOCH_JD = 2_440_587.5;

    private const float SECONDS_PER_DAY = 86_400.0;

    private const float JD_EPSILON = 1.0e-10;

    /**
     * Generic ingress-search controls for all projection models.
     *
     * A ±240-day window contains the nearest mean/physical Jupiter sign
     * ingress in ordinary cases; ±420 days is a defensive fallback.
     */
    private const float PROJECTION_PRIMARY_SEARCH_HALF_DAYS = 240.0;

    private const float PROJECTION_FALLBACK_SEARCH_HALF_DAYS = 420.0;

    private const float PROJECTION_SCAN_STEP_DAYS = 1.0;

    /** Root precision target: one second in Julian days. */
    private const float PROJECTION_ROOT_TOLERANCE_DAYS
        = 1.0 / self::SECONDS_PER_DAY;

    /** @var array<int, float> */
    private array $modernJupiterLongitudeCache = [];

    /**
     * Cache mapped ingress by model + classical boundary.
     *
     * @var array<string, array{
     *   jd:float,
     *   rashi_index:int,
     *   longitude_before:float,
     *   longitude_after:float
     * }>
     */
    private array $ingressByClassicalBoundaryCache = [];

    public function __construct(
        private readonly ?AstronomyService $astronomy = null
    ) {}

    /**
     * Machine-readable model catalogue (status + family for API consumers).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function supportedModels(): array
    {
        return [
            self::MODEL_CLASSICAL_SS => [
                'model' => self::MODEL_CLASSICAL_SS,
                'status' => self::STATUS_CANONICAL,
                'model_family' => self::FAMILY_TRADITIONAL_BARHASPATYA,
                'type' => 'classical_mean_motion',
                'description'
                    => 'Classical Sūrya-Siddhānta / Sewell-Dīkṣit Bārhaspatya reckoning (package default).',
                'recommended_use'
                    => 'Standard panchanga, academic baseline, zero ephemeris overhead.',
                'requires_astronomy_service' => false,
                'is_default' => true,
                'implementation_scope' => 'source_defined_barhaspatya_mean_motion',
            ],
            self::MODEL_GRAHALAGHAVA => [
                'model' => self::MODEL_GRAHALAGHAVA,
                'status' => self::STATUS_EXPERIMENTAL,
                'model_family' => self::FAMILY_TRADITIONAL_MEAN_JUPITER,
                'type' => 'historical_mean_jupiter_projection',
                'description'
                    => 'Graha-lāghava 1520 mean-Jupiter timing projected onto the classical 60-name sequence.',
                'recommended_use'
                    => 'Regional Western/Central research comparison (not full ahargaṇa machinery).',
                'requires_astronomy_service' => false,
                'is_default' => false,
                'name_phase_source' => self::MODEL_CLASSICAL_SS,
                'implementation_scope' => 'mean_motion_research_model',
                'epoch_jd' => self::GRAHALAGHAVA_EPOCH_JD,
                'reference_meridian_degrees_east'
                    => self::UJJAIN_REFERENCE_LONGITUDE_DEGREES,
            ],
            self::MODEL_MAKARANDA => [
                'model' => self::MODEL_MAKARANDA,
                'status' => self::STATUS_EXPERIMENTAL,
                'model_family' => self::FAMILY_TRADITIONAL_MEAN_JUPITER,
                'type' => 'historical_mean_jupiter_projection',
                'description'
                    => 'Makaranda 1478 Saurapakṣa bīja mean-Jupiter timing projected onto the classical 60-name sequence.',
                'recommended_use'
                    => "Research comparison often closer to published Drik intraday times; not Drik's formula.",
                'requires_astronomy_service' => false,
                'is_default' => false,
                'name_phase_source' => self::MODEL_CLASSICAL_SS,
                'implementation_scope' => 'mean_motion_research_model',
                'jupiter_revolutions_per_mahayuga'
                    => self::MAKARANDA_JUPITER_REVOLUTIONS_PER_MAHAYUGA,
                'mahayuga_civil_days' => self::MAHAYUGA_CIVIL_DAYS,
                'jovian_sign_year_days' => self::MAKARANDA_JOVIAN_YEAR_DAYS,
                'reference_meridian_degrees_east'
                    => self::UJJAIN_REFERENCE_LONGITUDE_DEGREES,
            ],
            self::MODEL_MODERN_EPHEMERIS => [
                'model' => self::MODEL_MODERN_EPHEMERIS,
                'status' => self::STATUS_ASTRONOMICAL_COMPARATOR,
                'model_family' => self::FAMILY_MODERN_EPHEMERIS,
                'type' => 'modern_physical_ephemeris',
                'description'
                    => 'Nearest prograde geocentric sidereal Jupiter rāśi ingress from AstronomyService (JPL when enabled).',
                'recommended_use'
                    => 'Astrology / gochara / physical transit comparison — not traditional Bārhaspatya default.',
                'requires_astronomy_service' => true,
                'is_default' => false,
                'name_phase_source' => self::MODEL_CLASSICAL_SS,
                'implementation_scope' => 'physical_jupiter_ingress_comparator',
            ],
        ];
    }

    /**
     * Effective default model: package DEFAULT_MODEL, optionally overridden by
     * config('panchang.defaults.brihaspati_samvatsara_model') when Laravel config is present.
     *
     * Invalid configured values fall back to DEFAULT_MODEL (never throw on boot).
     */
    public static function defaultModel(): string
    {
        $configured = null;

        if (function_exists('config')) {
            try {
                $value = config('panchang.defaults.brihaspati_samvatsara_model');
                if (is_string($value) && trim($value) !== '') {
                    $configured = $value;
                }
            } catch (Throwable) {
                $configured = null;
            }
        }

        if ($configured === null) {
            return self::DEFAULT_MODEL;
        }

        try {
            return self::normalizeModelKey($configured);
        } catch (InvalidArgumentException) {
            return self::DEFAULT_MODEL;
        }
    }

    /** @return array<string, mixed> */
    public static function modelCatalogueEntry(string $model): array
    {
        $key = self::normalizeModelKey($model);
        $catalogue = self::supportedModels();

        return $catalogue[$key];
    }

    public static function modelStatus(string $model): string
    {
        return (string) self::modelCatalogueEntry($model)['status'];
    }

    public static function modelFamily(string $model): string
    {
        return (string) self::modelCatalogueEntry($model)['model_family'];
    }

    /** Public assignment_rule string for calendar-period windows of a model. */
    public static function assignmentRuleForModel(string $model): string
    {
        $resolved = self::normalizeModelKey($model);

        return match ($resolved) {
            self::MODEL_CLASSICAL_SS => 'continuous_barhaspatya_mean_transit',
            self::MODEL_GRAHALAGHAVA
                => 'continuous_barhaspatya_grahalaghava_mean_jupiter_ingress',
            self::MODEL_MAKARANDA
                => 'continuous_barhaspatya_makaranda_mean_jupiter_ingress',
            self::MODEL_MODERN_EPHEMERIS
                => 'continuous_barhaspatya_modern_jupiter_ingress',
            default => throw new InvalidArgumentException(
                sprintf("Unknown Brihaspati Samvatsara model '%s'.", $resolved)
            ),
        };
    }

    /**
     * Calculate Bārhaspatya Samvatsara information for a Julian Day.
     *
     * @param string|null $model null → {@see defaultModel()}
     *
     * @return array<string, mixed>
     */
    public function getBrihaspatiSamvatsaraInfoFromJd(
        float $jd,
        ?string $model = null
    ): array {
        if (!is_finite($jd)) {
            throw new InvalidArgumentException('Julian Day must be finite.');
        }

        $model = self::normalizeModelKey($model ?? self::defaultModel());

        return match ($model) {
            self::MODEL_CLASSICAL_SS => $this->getClassicalInfoFromJd($jd),
            self::MODEL_GRAHALAGHAVA,
            self::MODEL_MAKARANDA,
            self::MODEL_MODERN_EPHEMERIS => $this->getProjectedInfoFromJd($jd, $model),
            default => throw new InvalidArgumentException(
                sprintf("Unknown Brihaspati Samvatsara model '%s'.", $model)
            ),
        };
    }

    /**
     * Calculate Bārhaspatya Samvatsara information for a civil DateTime.
     *
     * @param string|null $model null → {@see defaultModel()}
     *
     * @return array<string, mixed>
     */
    public function getBrihaspatiSamvatsaraInfo(
        DateTimeInterface $date,
        ?string $model = null
    ): array {
        return $this->getBrihaspatiSamvatsaraInfoFromJd(
            $this->dateTimeToJulianDay($date),
            $model
        );
    }

    /**
     * Get the Bārhaspatya Samvatsara name for a civil DateTime.
     *
     * @param string|null $model null → {@see defaultModel()}
     */
    public function getSamvatsaraBrihaspati(
        DateTimeInterface $date,
        ?string $model = null
    ): string {
        return $this->getBrihaspatiSamvatsaraInfo($date, $model)['name'];
    }

    /**
     * Compare all four models at one JD.
     *
     * Backward compatibility:
     * - same_name_timing.classical remains unchanged.
     * - same_name_timing.modern remains the modern_ephemeris projection.
     * - same_name_timing.difference remains modern - classical.
     *
     * New:
     * - current_state.grahalaghava
     * - current_state.makaranda
     * - same_name_timing.grahalaghava
     * - same_name_timing.makaranda
     * - same_name_timing.differences_from_classical
     *
     * @return array<string, mixed>
     */
    public function compareModelsFromJd(float $jd): array
    {
        if (!is_finite($jd)) {
            throw new InvalidArgumentException('Julian Day must be finite.');
        }

        $classical = $this->getClassicalInfoFromJd($jd);
        $grahalaghavaCurrent = $this->getProjectedInfoFromJd(
            $jd,
            self::MODEL_GRAHALAGHAVA
        );
        $makarandaCurrent = $this->getProjectedInfoFromJd(
            $jd,
            self::MODEL_MAKARANDA
        );

        // Modern ephemeris is optional: historical mean models must still
        // compare when AstronomyService is not injected.
        $modernCurrent = null;
        if ($this->astronomy instanceof AstronomyService) {
            $modernCurrent = $this->getProjectedInfoFromJd(
                $jd,
                self::MODEL_MODERN_EPHEMERIS
            );
        }

        $classicalStartJd = (float) $classical['start_jd'];
        $classicalEndJd = (float) $classical['end_jd'];

        $projectionModels = [
            self::MODEL_GRAHALAGHAVA,
            self::MODEL_MAKARANDA,
        ];
        if ($modernCurrent !== null) {
            $projectionModels[] = self::MODEL_MODERN_EPHEMERIS;
        }

        $projected = [];
        foreach ($projectionModels as $model) {
            $start = $this->findNearestModelJupiterIngress($classicalStartJd, $model);
            $end = $this->findNearestModelJupiterIngress($classicalEndJd, $model);

            $projected[$model] = [
                'model' => $model,
                'variant' => $this->projectionVariant($model),
                'start_jd' => $start['jd'],
                'end_jd' => $end['jd'],
                'start_date' => $this->julianDayToCarbon($start['jd']),
                'end_date' => $this->julianDayToCarbon($end['jd']),
                'start_rashi_index' => $start['rashi_index'],
                'end_rashi_index' => $end['rashi_index'],
            ];
        }

        $differences = [];
        foreach ($projected as $model => $timing) {
            $differences[$model] = $this->buildTimingDifference(
                $classicalStartJd,
                $classicalEndJd,
                $timing['start_jd'],
                $timing['end_jd']
            );
        }

        $currentState = [
            self::MODEL_CLASSICAL_SS => $classical,
            self::MODEL_GRAHALAGHAVA => $grahalaghavaCurrent,
            self::MODEL_MAKARANDA => $makarandaCurrent,
        ];
        if ($modernCurrent !== null) {
            $currentState[self::MODEL_MODERN_EPHEMERIS] = $modernCurrent;
        }

        $sameNameTiming = [
            'name' => $classical['name'],
            'index' => $classical['index'],
            'classical' => [
                'model' => self::MODEL_CLASSICAL_SS,
                'variant' => $classical['variant'],
                'start_jd' => $classicalStartJd,
                'end_jd' => $classicalEndJd,
                'start_date' => $this->julianDayToCarbon($classicalStartJd),
                'end_date' => $this->julianDayToCarbon($classicalEndJd),
            ],
            'grahalaghava' => $projected[self::MODEL_GRAHALAGHAVA],
            'makaranda' => $projected[self::MODEL_MAKARANDA],
            // Existing two-model callers expect this exact key when modern is present.
            'difference' => $differences[self::MODEL_MODERN_EPHEMERIS] ?? null,
            'differences_from_classical' => $differences,
        ];
        if (isset($projected[self::MODEL_MODERN_EPHEMERIS])) {
            $sameNameTiming['modern'] = $projected[self::MODEL_MODERN_EPHEMERIS];
        }

        return [
            'at_jd' => $jd,
            'current_state' => $currentState,
            'same_name_timing' => $sameNameTiming,
        ];
    }

    /** Compare all models for a DateTime. */
    public function compareModels(DateTimeInterface $date): array
    {
        return $this->compareModelsFromJd($this->dateTimeToJulianDay($date));
    }

    /** Normalize aliases to a canonical model key. */
    public static function normalizeModelKey(string $model): string
    {
        $key = strtolower(str_replace([' ', '-'], '_', trim($model)));

        return match ($key) {
            'classical',
            'ss',
            'surya_siddhanta',
            'surya_siddhanta_mean',
            'classical_ss',
            'default' => self::MODEL_CLASSICAL_SS,

            'grahalaghava',
            'graha_laghava',
            'ganesa',
            'ganesha',
            'ganesa_daivajna',
            'ganesha_daivajna' => self::MODEL_GRAHALAGHAVA,

            'makaranda',
            'makaranda_sarini',
            'makarandasarini',
            'makarandasari',
            'saurapaksha_makaranda' => self::MODEL_MAKARANDA,

            'modern',
            'true',
            'astronomical',
            'ephemeris',
            'modern_ephemeris',
            'true_astronomical' => self::MODEL_MODERN_EPHEMERIS,

            default => throw new InvalidArgumentException(
                sprintf("Unknown Brihaspati Samvatsara model '%s'. ", $model)
                . 'Supported models: '
                . implode(', ', [
                    self::MODEL_CLASSICAL_SS,
                    self::MODEL_GRAHALAGHAVA,
                    self::MODEL_MAKARANDA,
                    self::MODEL_MODERN_EPHEMERIS,
                ])
                . '.',
            ),
        };
    }

    /**
     * Classical Sūrya-Siddhānta-family model.
     *
     * @return array<string, mixed>
     */
    private function getClassicalInfoFromJd(float $jd): array
    {
        $approximateK = $this->expiredKaliYearAtJd($jd);

        $boundaries = $this->collectClassicalBoundaries(
            $approximateK - 3,
            $approximateK + 3
        );

        [$startBoundary, $endBoundary] = $this->findBoundingBoundaries($jd, $boundaries);

        if ($startBoundary === null || $endBoundary === null) {
            $boundaries = $this->collectClassicalBoundaries(
                $approximateK - 10,
                $approximateK + 10
            );
            [$startBoundary, $endBoundary] = $this->findBoundingBoundaries($jd, $boundaries);
        }

        if ($startBoundary === null || $endBoundary === null) {
            throw new RuntimeException(
                'Unable to determine a complete classical Bārhaspatya window around JD '
                . sprintf('%.12F', $jd)
            );
        }

        $index = (int) $startBoundary['new_index'];
        $samvatsara = Samvatsara::from($index);

        return [
            'samvatsara' => $samvatsara,
            'name' => $samvatsara->getName(),
            'index' => $index,
            'classical_number' => $index + 1,
            'start_jd' => (float) $startBoundary['jd'],
            'end_jd' => (float) $endBoundary['jd'],
            'start_date' => $this->julianDayToCarbon((float) $startBoundary['jd']),
            'end_date' => $this->julianDayToCarbon((float) $endBoundary['jd']),
            'model' => self::MODEL_CLASSICAL_SS,
            'status' => self::STATUS_CANONICAL,
            'model_family' => self::FAMILY_TRADITIONAL_BARHASPATYA,
            'variant' => (string) $startBoundary['variant'],
            'expired_kali_year' => (int) $startBoundary['expired_kali_year'],
            'timing_basis' => 'classical_mean_motion',
        ];
    }

    /**
     * Historical/modern timing projection containing a JD.
     *
     * @return array<string, mixed>
     */
    private function getProjectedInfoFromJd(float $jd, string $model): array
    {
        $this->assertProjectionModel($model);

        if ($model === self::MODEL_MODERN_EPHEMERIS) {
            $this->requireAstronomyService();
        }

        $approximateK = $this->expiredKaliYearAtJd($jd);

        $boundaries = $this->collectProjectedBoundaries(
            $approximateK - 3,
            $approximateK + 3,
            $model
        );

        [$startBoundary, $endBoundary] = $this->findBoundingBoundaries($jd, $boundaries);

        if ($startBoundary === null || $endBoundary === null) {
            $boundaries = $this->collectProjectedBoundaries(
                $approximateK - 10,
                $approximateK + 10,
                $model
            );
            [$startBoundary, $endBoundary] = $this->findBoundingBoundaries($jd, $boundaries);
        }

        if ($startBoundary === null || $endBoundary === null) {
            throw new RuntimeException(
                sprintf(
                    "Unable to determine a complete '%s' Bārhaspatya projection window around JD %.12F.",
                    $model,
                    $jd
                )
            );
        }

        $index = (int) $startBoundary['new_index'];
        $samvatsara = Samvatsara::from($index);

        return [
            'samvatsara' => $samvatsara,
            'name' => $samvatsara->getName(),
            'index' => $index,
            'classical_number' => $index + 1,
            'start_jd' => (float) $startBoundary['jd'],
            'end_jd' => (float) $endBoundary['jd'],
            'start_date' => $this->julianDayToCarbon((float) $startBoundary['jd']),
            'end_date' => $this->julianDayToCarbon((float) $endBoundary['jd']),
            'model' => $model,
            'status' => self::modelStatus($model),
            'model_family' => self::modelFamily($model),
            'variant' => $this->projectionVariant($model),
            'expired_kali_year' => (int) $startBoundary['expired_kali_year'],
            'timing_basis' => $this->projectionTimingBasis($model),
            'name_phase_source' => self::MODEL_CLASSICAL_SS,
            'start_rashi_index' => (int) $startBoundary['rashi_index'],
            'end_rashi_index' => (int) $endBoundary['rashi_index'],
            'classical_reference_start_jd'
                => (float) $startBoundary['classical_reference_jd'],
            'classical_reference_end_jd'
                => (float) $endBoundary['classical_reference_jd'],
        ];
    }

    /** Get the expired Kali solar year at a Julian Day. */
    private function expiredKaliYearAtJd(float $jd): int
    {
        return (int) floor(
            ($jd - self::KALI_EPOCH_JD)
            / self::SURYA_SIDDHANTA_SOLAR_YEAR_DAYS
        );
    }

    /**
     * Build all continuous Jovian boundaries generated by Article 59.
     *
     * @return array<int, array<string, mixed>>
     */
    private function collectClassicalBoundaries(int $fromK, int $toK): array
    {
        $boundaries = [];

        for ($k = $fromK; $k <= $toK; $k++) {
            $state = $this->stateAtMeanMesha($k);
            $nextState = $this->stateAtMeanMesha($k + 1);

            $advanceCount = $this->forwardIndexDistance(
                (int) $state['index'],
                (int) $nextState['index']
            );

            if ($advanceCount < 1 || $advanceCount > 2) {
                throw new RuntimeException(
                    sprintf(
                        'Unexpected Bārhaspatya advancement of %d names between expired Kali years %d and %d.',
                        $advanceCount,
                        $k,
                        $k + 1
                    )
                );
            }

            $firstBoundaryJd = (float) $state['first_boundary_jd'];
            $periodDays = (float) $state['jovian_period_days'];

            for ($step = 0; $step < $advanceCount; $step++) {
                $boundaryJd = $firstBoundaryJd + ($step * $periodDays);

                if ($boundaryJd >= (float) $nextState['mesha_jd'] + self::JD_EPSILON) {
                    throw new RuntimeException(
                        sprintf(
                            'Derived Bārhaspatya boundary %.12F does not fall before the next mean Meṣa-saṅkrānti %.12F for expired Kali year %d.',
                            $boundaryJd,
                            (float) $nextState['mesha_jd'],
                            $k
                        )
                    );
                }

                $newIndex = ((int) $state['index'] + $step + 1) % 60;
                $key = sprintf('%.9F', $boundaryJd);
                $boundaries[$key] = [
                    'jd' => $boundaryJd,
                    'new_index' => $newIndex,
                    'variant' => (string) $state['variant'],
                    'expired_kali_year' => $k,
                ];
            }
        }

        $boundaries = array_values($boundaries);
        usort(
            $boundaries,
            static fn(array $a, array $b): int => $a['jd'] <=> $b['jd']
        );

        return $boundaries;
    }

    /**
     * Project classical name transitions onto one selected Jupiter model.
     *
     * @return array<int, array<string, mixed>>
     */
    private function collectProjectedBoundaries(
        int $fromK,
        int $toK,
        string $model
    ): array {
        $this->assertProjectionModel($model);

        $classicalBoundaries = $this->collectClassicalBoundaries($fromK, $toK);
        $projected = [];

        foreach ($classicalBoundaries as $classicalBoundary) {
            $mapped = $this->mapClassicalBoundaryToIngress(
                $classicalBoundary,
                $model
            );

            $key = sprintf('%.7F', (float) $mapped['jd']);

            if (isset($projected[$key])
                && (int) $projected[$key]['new_index'] !== (int) $mapped['new_index']) {
                throw new RuntimeException(
                    sprintf(
                        "Two different Samvatsara transitions mapped to the same '%s' Jupiter ingress near JD %s.",
                        $model,
                        $key
                    )
                );
            }

            $projected[$key] = $mapped;
        }

        $projected = array_values($projected);
        usort(
            $projected,
            static fn(array $a, array $b): int => $a['jd'] <=> $b['jd']
        );

        return $projected;
    }

    /**
     * Pair one classical transition with the nearest prograde Jupiter ingress
     * in the chosen projection model.
     *
     * @param array<string, mixed> $classicalBoundary
     *
     * @return array<string, mixed>
     */
    private function mapClassicalBoundaryToIngress(
        array $classicalBoundary,
        string $model
    ): array {
        $classicalJd = (float) $classicalBoundary['jd'];
        $cacheKey = implode('|', [
            $model,
            sprintf('%.9F', $classicalJd),
            (string) ((int) $classicalBoundary['new_index']),
        ]);

        if (!isset($this->ingressByClassicalBoundaryCache[$cacheKey])) {
            $this->ingressByClassicalBoundaryCache[$cacheKey]
                = $this->findNearestModelJupiterIngress($classicalJd, $model);
        }

        $ingress = $this->ingressByClassicalBoundaryCache[$cacheKey];

        return [
            'jd' => $ingress['jd'],
            'new_index' => (int) $classicalBoundary['new_index'],
            'variant' => $this->projectionVariant($model),
            'expired_kali_year' => (int) $classicalBoundary['expired_kali_year'],
            'rashi_index' => $ingress['rashi_index'],
            'classical_reference_jd' => $classicalJd,
        ];
    }

    /**
     * State at mean Meṣa-saṅkrānti of expired Kali year K.
     *
     * @return array<string, mixed>
     */
    private function stateAtMeanMesha(int $k): array
    {
        $rule = $this->ruleForExpiredKaliYear($k);
        $numerator = $rule['numerator'];
        $denominator = $rule['denominator'];

        $a = $numerator * $k;
        $q = $this->floorDiv($a, $denominator);
        $r = $this->floorMod($a, $denominator);

        $classicalRemainder = $this->floorMod($q + $k + 27, 60);
        $index = $classicalRemainder === 0
            ? 59
            : $classicalRemainder - 1;

        $meshaJd = self::KALI_EPOCH_JD
            + ($k * self::SURYA_SIDDHANTA_SOLAR_YEAR_DAYS);

        $daysUntilEnd = (($denominator - $r) * self::RULE_JOVIAN_DAYS)
            / $denominator;

        $firstBoundaryJd = $meshaJd + $daysUntilEnd;

        $jovianPeriodDays
            = self::SURYA_SIDDHANTA_SOLAR_YEAR_DAYS
            * $denominator
            / ($denominator + $numerator);

        return [
            'index' => $index,
            'classical_number' => $index + 1,
            'mesha_jd' => $meshaJd,
            'first_boundary_jd' => $firstBoundaryJd,
            'jovian_period_days' => $jovianPeriodDays,
            'variant' => $rule['variant'],
        ];
    }

    /** @return array{numerator:int,denominator:int,variant:string} */
    private function ruleForExpiredKaliYear(int $k): array
    {
        if ($k >= self::BIJA_START_EXPIRED_KALI_YEAR) {
            return [
                'numerator' => self::BIJA_EXCESS_NUMERATOR,
                'denominator' => self::BIJA_EXCESS_DENOMINATOR,
                'variant' => self::CLASSICAL_VARIANT_BIJA,
            ];
        }

        return [
            'numerator' => self::SS_EXCESS_NUMERATOR,
            'denominator' => self::SS_EXCESS_DENOMINATOR,
            'variant' => self::CLASSICAL_VARIANT_PLAIN,
        ];
    }

    /**
     * Find nearest prograde Jupiter 30° ingress in one projection model.
     *
     * @return array{jd:float,rashi_index:int,longitude_before:float,longitude_after:float}
     */
    private function findNearestModelJupiterIngress(
        float $referenceJd,
        string $model
    ): array {
        $this->assertProjectionModel($model);

        if ($model === self::MODEL_MODERN_EPHEMERIS) {
            $this->requireAstronomyService();
        }

        $candidates = $this->scanModelJupiterIngresses(
            $referenceJd - self::PROJECTION_PRIMARY_SEARCH_HALF_DAYS,
            $referenceJd + self::PROJECTION_PRIMARY_SEARCH_HALF_DAYS,
            $model
        );

        if ($candidates === []) {
            $candidates = $this->scanModelJupiterIngresses(
                $referenceJd - self::PROJECTION_FALLBACK_SEARCH_HALF_DAYS,
                $referenceJd + self::PROJECTION_FALLBACK_SEARCH_HALF_DAYS,
                $model
            );
        }

        if ($candidates === []) {
            throw new RuntimeException(
                sprintf(
                    "No prograde Jupiter rāśi ingress found for model '%s' around JD %.12F.",
                    $model,
                    $referenceJd
                )
            );
        }

        usort(
            $candidates,
            static fn(array $a, array $b): int
                => abs($a['jd'] - $referenceJd) <=> abs($b['jd'] - $referenceJd)
        );

        return $candidates[0];
    }

    /**
     * Scan a JD interval for prograde crossings of multiples of 30°.
     *
     * @return array<int, array{jd:float,rashi_index:int,longitude_before:float,longitude_after:float}>
     */
    private function scanModelJupiterIngresses(
        float $fromJd,
        float $toJd,
        string $model
    ): array {
        if ($toJd <= $fromJd) {
            return [];
        }

        $candidates = [];
        $leftJd = $fromJd;
        $leftLon = $this->jupiterLongitudeForModel($leftJd, $model);
        $leftSign = $this->rashiIndex($leftLon);

        for (
            $rightJd = min($leftJd + self::PROJECTION_SCAN_STEP_DAYS, $toJd);
            $rightJd <= $toJd + self::JD_EPSILON;
            $rightJd = min($rightJd + self::PROJECTION_SCAN_STEP_DAYS, $toJd)
        ) {
            if ($rightJd <= $leftJd + self::JD_EPSILON) {
                break;
            }

            $rightLon = $this->jupiterLongitudeForModel($rightJd, $model);
            $rightSign = $this->rashiIndex($rightLon);
            $motion = $this->signedAngularDelta($rightLon, $leftLon);
            $forwardSignDistance = (($rightSign - $leftSign) % 12 + 12) % 12;

            if ($motion > 0.0 && $forwardSignDistance === 1) {
                $targetLongitude = $rightSign * 30.0;

                $rootJd = $this->refineModelLongitudeCrossing(
                    $leftJd,
                    $rightJd,
                    $targetLongitude,
                    $model
                );

                $beforeJd = $rootJd - (2.0 / self::SECONDS_PER_DAY);
                $afterJd = $rootJd + (2.0 / self::SECONDS_PER_DAY);
                $beforeLon = $this->jupiterLongitudeForModel($beforeJd, $model);
                $afterLon = $this->jupiterLongitudeForModel($afterJd, $model);

                // Reject retrograde/reverse discontinuity crossings.
                if ($this->signedAngularDelta($afterLon, $beforeLon) > 0.0) {
                    $key = sprintf('%.7F', $rootJd);
                    $candidates[$key] = [
                        'jd' => $rootJd,
                        'rashi_index' => $rightSign,
                        'longitude_before' => $beforeLon,
                        'longitude_after' => $afterLon,
                    ];
                }
            }

            $leftJd = $rightJd;
            $leftLon = $rightLon;
            $leftSign = $rightSign;

            if ($rightJd >= $toJd - self::JD_EPSILON) {
                break;
            }
        }

        return array_values($candidates);
    }

    /** Bisection refinement for a known prograde crossing. */
    private function refineModelLongitudeCrossing(
        float $leftJd,
        float $rightJd,
        float $targetLongitude,
        string $model
    ): float {
        $leftValue = $this->signedLongitudeFromTarget(
            $this->jupiterLongitudeForModel($leftJd, $model),
            $targetLongitude
        );
        $rightValue = $this->signedLongitudeFromTarget(
            $this->jupiterLongitudeForModel($rightJd, $model),
            $targetLongitude
        );

        if ($leftValue > 0.0 || $rightValue < 0.0) {
            throw new RuntimeException(
                sprintf(
                    "Jupiter ingress root is not bracketed for model '%s': left=%.9F right=%.9F target=%.6F.",
                    $model,
                    $leftValue,
                    $rightValue,
                    $targetLongitude
                )
            );
        }

        while (($rightJd - $leftJd) > self::PROJECTION_ROOT_TOLERANCE_DAYS) {
            $midJd = ($leftJd + $rightJd) / 2.0;
            $midValue = $this->signedLongitudeFromTarget(
                $this->jupiterLongitudeForModel($midJd, $model),
                $targetLongitude
            );

            if ($midValue >= 0.0) {
                $rightJd = $midJd;
                $rightValue = $midValue;
            } else {
                $leftJd = $midJd;
                $leftValue = $midValue;
            }
        }

        return ($leftJd + $rightJd) / 2.0;
    }

    /** Select Jupiter longitude engine for a projection model. */
    private function jupiterLongitudeForModel(float $jd, string $model): float
    {
        return match ($model) {
            self::MODEL_GRAHALAGHAVA => $this->grahalaghavaMeanJupiterLongitude($jd),
            self::MODEL_MAKARANDA => $this->makarandaMeanJupiterLongitude($jd),
            self::MODEL_MODERN_EPHEMERIS => $this->modernJupiterSiderealLongitude($jd),
            default => throw new InvalidArgumentException(
                sprintf("Model '%s' does not provide a projected Jupiter longitude.", $model)
            ),
        };
    }

    /**
     * Graha-lāghava mean Jupiter longitude.
     *
     * Textual parameters implemented here:
     * - epoch: 19 March 1520 Julian, mean sunrise at Ujjayinī;
     * - cakra: 4016 civil days;
     * - Jupiter dhruva: 0s 26°18';
     * - Jupiter kṣepaka: 7s 2°16';
     * - completed-day increment: a/12 degrees - a/70 arcminutes;
     * - fractional-day interpolation: 5 arcminutes/day.
     *
     * The original handbook obtains c and a from its Śaka/lunisolar ahargaṇa
     * procedure. For a direct JD API, this service realizes the published
     * 4016-civil-day cakra on the absolute civil-day axis from the published
     * epoch. This removes any dependency on a separate historical lunar-date
     * converter while preserving the stated mean-Jupiter parameters exactly.
     */
    private function grahalaghavaMeanJupiterLongitude(float $jd): float
    {
        $elapsed = $jd - self::GRAHALAGHAVA_EPOCH_JD;

        $cakra = (int) floor($elapsed / self::GRAHALAGHAVA_CAKRA_DAYS);
        $within = $elapsed - ($cakra * self::GRAHALAGHAVA_CAKRA_DAYS);

        // Defensive normalization for floating-point edge cases and negatives.
        if ($within < 0.0) {
            $cakra--;
            $within += self::GRAHALAGHAVA_CAKRA_DAYS;
        } elseif ($within >= self::GRAHALAGHAVA_CAKRA_DAYS) {
            $cakra++;
            $within -= self::GRAHALAGHAVA_CAKRA_DAYS;
        }

        $completedDays = (int) floor($within);
        $fractionalDay = $within - $completedDays;

        $withinCycleIncrement
            = $completedDays * self::GRAHALAGHAVA_JUPITER_AHARGANA_DEGREES_PER_DAY;

        $intradayIncrement
            = $fractionalDay * self::GRAHALAGHAVA_JUPITER_INTRADAY_DEGREES_PER_DAY;

        $longitude
            = self::GRAHALAGHAVA_JUPITER_KSEPAKA_DEGREES
            - ($cakra * self::GRAHALAGHAVA_JUPITER_DHRUVA_DEGREES)
            + $withinCycleIncrement
            + $intradayIncrement;

        return $this->normalizeDegrees($longitude);
    }

    /**
     * Makaranda/Saurapakṣa bīja mean Jupiter longitude.
     *
     * Makaranda's correction uses 364,212 Jupiter revolutions in the unchanged
     * 1,577,917,828 civil days of a Mahāyuga. The mean Jupiter is realized from
     * the traditional midnight Kali epoch on the Ujjayinī meridian.
     */
    private function makarandaMeanJupiterLongitude(float $jd): float
    {
        $elapsedDays = $jd - self::MAKARANDA_KALI_EPOCH_ABSOLUTE_JD;

        $longitude
            = $elapsedDays
            * 360.0
            * self::MAKARANDA_JUPITER_REVOLUTIONS_PER_MAHAYUGA
            / self::MAHAYUGA_CIVIL_DAYS;

        return $this->normalizeDegrees($longitude);
    }

    /** Modern geocentric sidereal Jupiter longitude from AstronomyService. */
    private function modernJupiterSiderealLongitude(float $jd): float
    {
        $astronomy = $this->requireAstronomyService();
        $utc = $this->julianDayToCarbon($jd);

        // AstronomyService's standard birth-array API is second-granular.
        $cacheKey = $utc->getTimestamp();

        if (array_key_exists($cacheKey, $this->modernJupiterLongitudeCache)) {
            return $this->modernJupiterLongitudeCache[$cacheKey];
        }

        $longitude = $astronomy->calcBodyLongitudeAtJd(
            $jd,
            JmeEphFFI::JME_BODY_JUPITER_BARYCENTER
        );

        if (!is_finite($longitude)) {
            throw new RuntimeException(
                'AstronomyService returned a non-finite Jupiter longitude.'
            );
        }

        return $this->modernJupiterLongitudeCache[$cacheKey]
            = $this->normalizeDegrees($longitude);
    }

    /** @return array{start_seconds:float,end_seconds:float,start_days:float,end_days:float} */
    private function buildTimingDifference(
        float $classicalStartJd,
        float $classicalEndJd,
        float $otherStartJd,
        float $otherEndJd
    ): array {
        return [
            'start_seconds' => ($otherStartJd - $classicalStartJd)
                * self::SECONDS_PER_DAY,
            'end_seconds' => ($otherEndJd - $classicalEndJd)
                * self::SECONDS_PER_DAY,
            'start_days' => $otherStartJd - $classicalStartJd,
            'end_days' => $otherEndJd - $classicalEndJd,
        ];
    }

    private function projectionVariant(string $model): string
    {
        return match ($model) {
            self::MODEL_GRAHALAGHAVA => self::GRAHALAGHAVA_VARIANT,
            self::MODEL_MAKARANDA => self::MAKARANDA_VARIANT,
            self::MODEL_MODERN_EPHEMERIS => self::MODERN_VARIANT,
            default => throw new InvalidArgumentException(
                sprintf("Model '%s' is not a projection model.", $model)
            ),
        };
    }

    private function projectionTimingBasis(string $model): string
    {
        return match ($model) {
            self::MODEL_GRAHALAGHAVA
                => 'nearest_prograde_grahalaghava_mean_jupiter_rashi_ingress',
            self::MODEL_MAKARANDA
                => 'nearest_prograde_makaranda_bija_mean_jupiter_rashi_ingress',
            self::MODEL_MODERN_EPHEMERIS
                => 'nearest_prograde_sidereal_jupiter_rashi_ingress_from_astronomy_service',
            default => throw new InvalidArgumentException(
                sprintf("Model '%s' is not a projection model.", $model)
            ),
        };
    }

    private function assertProjectionModel(string $model): void
    {
        if (!in_array($model, [
            self::MODEL_GRAHALAGHAVA,
            self::MODEL_MAKARANDA,
            self::MODEL_MODERN_EPHEMERIS,
        ], true)) {
            throw new InvalidArgumentException(
                sprintf("Model '%s' is not a Jupiter-ingress projection model.", $model)
            );
        }
    }

    private function requireAstronomyService(): AstronomyService
    {
        if (!$this->astronomy instanceof AstronomyService) {
            throw new RuntimeException(
                'The modern_ephemeris Bārhaspatya model requires an AstronomyService instance. '
                . 'Construct BrihaspatiSamvatsaraService with the project AstronomyService.'
            );
        }

        return $this->astronomy;
    }

    /**
     * Find immediately preceding and following boundaries.
     * Interval convention: start_jd <= jd < end_jd.
     *
     * @param array<int, array<string, mixed>> $boundaries
     *
     * @return array{0:?array<string,mixed>,1:?array<string,mixed>}
     */
    private function findBoundingBoundaries(float $jd, array $boundaries): array
    {
        $start = null;
        $end = null;

        foreach ($boundaries as $boundary) {
            $boundaryJd = (float) $boundary['jd'];

            if ($boundaryJd <= $jd + self::JD_EPSILON) {
                $start = $boundary;
                continue;
            }

            $end = $boundary;
            break;
        }

        return [$start, $end];
    }

    /** Forward distance between two 0-based Samvatsara indexes. */
    private function forwardIndexDistance(int $from, int $to): int
    {
        return (($to - $from) % 60 + 60) % 60;
    }

    /** Mathematical floor division for integers. */
    private function floorDiv(int $a, int $b): int
    {
        if ($b <= 0) {
            throw new InvalidArgumentException('Divisor must be positive.');
        }

        $q = intdiv($a, $b);
        $r = $a % $b;

        if ($r !== 0 && $a < 0) {
            $q--;
        }

        return $q;
    }

    /** Mathematical non-negative modulo. */
    private function floorMod(int $a, int $b): int
    {
        if ($b <= 0) {
            throw new InvalidArgumentException('Modulus must be positive.');
        }

        $r = $a % $b;

        return $r < 0 ? $r + $b : $r;
    }

    private function rashiIndex(float $longitude): int
    {
        return ((int) floor($this->normalizeDegrees($longitude) / 30.0)) % 12;
    }

    private function normalizeDegrees(float $degrees): float
    {
        $normalized = fmod($degrees, 360.0);

        return $normalized < 0.0
            ? $normalized + 360.0
            : $normalized;
    }

    /** Signed shortest angular change from $from to $to in degrees. */
    private function signedAngularDelta(float $to, float $from): float
    {
        $delta = $this->normalizeDegrees($to - $from);

        return $delta > 180.0
            ? $delta - 360.0
            : $delta;
    }

    /** Signed longitude relative to a target angle in [-180, 180). */
    private function signedLongitudeFromTarget(float $longitude, float $target): float
    {
        $delta = $this->normalizeDegrees($longitude - $target);

        return $delta >= 180.0
            ? $delta - 360.0
            : $delta;
    }

    /** Convert a DateTimeInterface to Julian Day. */
    private function dateTimeToJulianDay(DateTimeInterface $date): float
    {
        $carbon = $date instanceof CarbonImmutable
            ? $date->setTimezone('UTC')
            : CarbonImmutable::instance($date)->setTimezone('UTC');

        $microseconds = (int) $carbon->format('u');

        return (
            $carbon->getTimestamp()
            + ($microseconds / 1_000_000.0)
        ) / self::SECONDS_PER_DAY
            + self::UNIX_EPOCH_JD;
    }

    /** Convert Julian Day to UTC CarbonImmutable. */
    private function julianDayToCarbon(float $jd): CarbonImmutable
    {
        if (!is_finite($jd)) {
            throw new InvalidArgumentException('Julian Day must be finite.');
        }

        $unixSeconds = ($jd - self::UNIX_EPOCH_JD) * self::SECONDS_PER_DAY;

        $wholeSeconds = (int) floor($unixSeconds);
        $microseconds = (int) round(
            ($unixSeconds - $wholeSeconds) * 1_000_000.0
        );

        if ($microseconds >= 1_000_000) {
            $wholeSeconds++;
            $microseconds -= 1_000_000;
        }

        if ($microseconds < 0) {
            $wholeSeconds--;
            $microseconds += 1_000_000;
        }

        return CarbonImmutable::createFromTimestampUTC($wholeSeconds)
            ->addMicroseconds($microseconds);
    }
}
