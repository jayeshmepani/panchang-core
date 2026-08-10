<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Astronomy;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use JayeshMepani\PanchangCore\Core\Enums\Samvatsara;
use JmeEph\FFI\JmeEphFFI;
use RuntimeException;

/**
 * Bārhaspatya Saṃvatsara Service.
 *
 * Provides two deliberately separate timing models:
 *
 * 1) classical_ss
 *    Genuine classical Sūrya-Siddhānta-family Bārhaspatya reckoning,
 *    following Sewell-Dīkṣit, The Indian Calendar (1896), Article 59.
 *
 * 2) modern_ephemeris
 *    A physical-astronomy comparison model. The traditional 60-name
 *    Samvatsara sequence is retained, but each classical name-transition
 *    is projected onto the nearest PROGRADE sidereal Jupiter rāśi ingress
 *    calculated by the project's modern AstronomyService.
 *
 * IMPORTANT ABOUT THE MODERN MODEL
 * --------------------------------
 * Actual Jupiter longitude contains only the 12 rāśis; it does not by itself
 * encode the phase of the traditional 60-name Samvatsara cycle. Therefore the
 * modern model is explicitly a timing projection:
 *
 *     classical 60-name transition
 *               ↓
 *     nearest prograde true/ephemeris Jupiter 30° ingress
 *
 * Retrograde re-crossings are ignored for the 60-name progression, otherwise
 * the name cycle could reverse or repeat. This makes the modern model useful
 * for comparing classical mean-motion timings against physical Jupiter
 * ingress timings without misrepresenting it as an independent textual
 * Bārhaspatya authority.
 *
 * The modern model is NOT claimed to reproduce Drik Panchang's published
 * Brihaspati-Samvatsara boundary. It is an explicit modern ephemeris ingress
 * comparison model.
 */
final class BrihaspatiSamvatsaraService
{
    public const string MODEL_CLASSICAL_SS = 'classical_ss';

    public const string MODEL_MODERN_EPHEMERIS = 'modern_ephemeris';

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

    private const string CLASSICAL_VARIANT_PLAIN = 'surya_siddhanta_barhaspatya';

    private const string CLASSICAL_VARIANT_BIJA = 'surya_siddhanta_barhaspatya_bija';

    private const string MODERN_VARIANT
        = 'modern_ephemeris_sidereal_jupiter_prograde_ingress';

    /** Sūrya-Siddhānta civil days in one Mahāyuga. */
    private const float MAHAYUGA_CIVIL_DAYS = 1_577_917_828.0;

    /** Sūrya-Siddhānta solar revolutions in one Mahāyuga. */
    private const int SOLAR_REVOLUTIONS_PER_MAHAYUGA = 4_320_000;

    /** Sūrya-Siddhānta Jupiter revolutions in one Mahāyuga. */
    private const int JUPITER_REVOLUTIONS_PER_MAHAYUGA = 364_220;

    /**
     * Conventional Kali-yuga astronomical epoch.
     *
     * JD 588465.5 is used only as the mean-solar-year/Ahargana origin.
     * It is NOT treated as a Bārhaspatya Samvatsara boundary.
     */
    private const float KALI_EPOCH_JD = 588465.5;

    /** Sewell-Dīkṣit Article 59a, mean-Meṣa equivalent form. */
    private const int SS_EXCESS_NUMERATOR = 211;

    private const int SS_EXCESS_DENOMINATOR = 18_000;

    /**
     * Sewell-Dīkṣit Article 59c, Sūrya-Siddhānta with bīja,
     * mean-Meṣa equivalent form.
     */
    private const int BIJA_EXCESS_NUMERATOR = 117;

    private const int BIJA_EXCESS_DENOMINATOR = 10_000;

    /** Sewell-Dīkṣit's timing rule uses 361 days as its working Jovian year. */
    private const float RULE_JOVIAN_DAYS = 361.0;

    /**
     * Deterministic implementation of Sewell-Dīkṣit's instruction that the
     * bīja form is to be used for years "after about 1500 A.D.".
     *
     * Expired Kali year 4601 has its mean Meṣa-saṅkrānti in 1500 CE.
     */
    private const int BIJA_START_EXPIRED_KALI_YEAR = 4601;

    /** Unix epoch as Julian Day. */
    private const float UNIX_EPOCH_JD = 2_440_587.5;

    private const float SECONDS_PER_DAY = 86_400.0;

    private const float JD_EPSILON = 1.0e-10;

    /**
     * Modern-ingress search controls.
     *
     * Jupiter is slow enough that a one-day scan cannot skip a full 30° sign.
     * A ±240-day primary search is normally ample; ±420 days is a defensive
     * fallback around unusual retrograde geometry.
     */
    private const float MODERN_PRIMARY_SEARCH_HALF_DAYS = 240.0;

    private const float MODERN_FALLBACK_SEARCH_HALF_DAYS = 420.0;

    private const float MODERN_SCAN_STEP_DAYS = 1.0;

    /** Root precision target: one second in Julian days. */
    private const float MODERN_ROOT_TOLERANCE_DAYS = 1.0 / self::SECONDS_PER_DAY;

    /**
     * Cache modern Jupiter longitude by whole UTC second because the existing
     * AstronomyService public birth-array API accepts integer seconds.
     *
     * @var array<int, float>
     */
    private array $modernJupiterLongitudeCache = [];

    /**
     * Cache paired modern ingress for a classical boundary.
     *
     * @var array<string, array{
     *   jd: float,
     *   rashi_index: int,
     *   longitude_before: float,
     *   longitude_after: float
     * }>
     */
    private array $modernIngressByClassicalBoundaryCache = [];

    public function __construct(
        private readonly ?AstronomyService $astronomy = null
    ) {}

    /**
     * Machine-readable model catalogue.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function supportedModels(): array
    {
        return [
            self::MODEL_CLASSICAL_SS => [
                'model' => self::MODEL_CLASSICAL_SS,
                'type' => 'classical_mean_motion',
                'description'
                    => 'Classical Sūrya-Siddhānta / Sewell-Dīkṣit Bārhaspatya reckoning.',
                'requires_astronomy_service' => false,
            ],
            self::MODEL_MODERN_EPHEMERIS => [
                'model' => self::MODEL_MODERN_EPHEMERIS,
                'type' => 'modern_physical_ephemeris',
                'description'
                    => 'Nearest prograde sidereal Jupiter rāśi ingress computed from the project AstronomyService.',
                'requires_astronomy_service' => true,
                'name_phase_source' => self::MODEL_CLASSICAL_SS,
            ],
        ];
    }

    /**
     * Calculate Bārhaspatya Samvatsara information for a Julian Day.
     *
     * @return array<string, mixed>
     */
    public function getBrihaspatiSamvatsaraInfoFromJd(
        float $jd,
        string $model = self::MODEL_CLASSICAL_SS
    ): array {
        if (!is_finite($jd)) {
            throw new InvalidArgumentException('Julian Day must be finite.');
        }

        return match ($this->normalizeModel($model)) {
            self::MODEL_CLASSICAL_SS => $this->getClassicalInfoFromJd($jd),
            self::MODEL_MODERN_EPHEMERIS => $this->getModernInfoFromJd($jd),
        };
    }

    /**
     * Calculate Bārhaspatya Samvatsara information for a civil DateTime.
     *
     * @return array<string, mixed>
     */
    public function getBrihaspatiSamvatsaraInfo(
        DateTimeInterface $date,
        string $model = self::MODEL_CLASSICAL_SS
    ): array {
        return $this->getBrihaspatiSamvatsaraInfoFromJd(
            $this->dateTimeToJulianDay($date),
            $model
        );
    }

    /** Get the Bārhaspatya Samvatsara name for a civil DateTime. */
    public function getSamvatsaraBrihaspati(
        DateTimeInterface $date,
        string $model = self::MODEL_CLASSICAL_SS
    ): string {
        return $this->getBrihaspatiSamvatsaraInfo($date, $model)['name'];
    }

    /**
     * Compare both models at one JD.
     *
     * Two views are returned:
     *
     * - current_state:
     *     what each model says is current at the supplied JD.
     *
     * - same_name_timing:
     *     the timing of the SAME classical Samvatsara window after projecting
     *     its start/end onto modern prograde Jupiter ingresses.
     *
     * @return array<string, mixed>
     */
    public function compareModelsFromJd(float $jd): array
    {
        $classical = $this->getClassicalInfoFromJd($jd);
        $modernCurrent = $this->getModernInfoFromJd($jd);

        $modernProjectedStart = $this->findNearestProgradeSiderealJupiterIngress(
            (float) $classical['start_jd']
        );
        $modernProjectedEnd = $this->findNearestProgradeSiderealJupiterIngress(
            (float) $classical['end_jd']
        );

        $classicalStartJd = (float) $classical['start_jd'];
        $classicalEndJd = (float) $classical['end_jd'];
        $modernStartJd = (float) $modernProjectedStart['jd'];
        $modernEndJd = (float) $modernProjectedEnd['jd'];

        return [
            'at_jd' => $jd,
            'current_state' => [
                self::MODEL_CLASSICAL_SS => $classical,
                self::MODEL_MODERN_EPHEMERIS => $modernCurrent,
            ],
            'same_name_timing' => [
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
                'modern' => [
                    'model' => self::MODEL_MODERN_EPHEMERIS,
                    'variant' => self::MODERN_VARIANT,
                    'start_jd' => $modernStartJd,
                    'end_jd' => $modernEndJd,
                    'start_date' => $this->julianDayToCarbon($modernStartJd),
                    'end_date' => $this->julianDayToCarbon($modernEndJd),
                    'start_rashi_index' => $modernProjectedStart['rashi_index'],
                    'end_rashi_index' => $modernProjectedEnd['rashi_index'],
                ],
                'difference' => [
                    'start_seconds'
                        => ($modernStartJd - $classicalStartJd) * self::SECONDS_PER_DAY,
                    'end_seconds'
                        => ($modernEndJd - $classicalEndJd) * self::SECONDS_PER_DAY,
                    'start_days' => $modernStartJd - $classicalStartJd,
                    'end_days' => $modernEndJd - $classicalEndJd,
                ],
            ],
        ];
    }

    /**
     * Compare both models for a DateTime.
     *
     * @return array<string, mixed>
     */
    public function compareModels(DateTimeInterface $date): array
    {
        return $this->compareModelsFromJd($this->dateTimeToJulianDay($date));
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
            'model_family' => self::MODEL_CLASSICAL_SS,
            'model' => self::MODEL_CLASSICAL_SS,
            'variant' => (string) $startBoundary['variant'],
            'expired_kali_year' => (int) $startBoundary['expired_kali_year'],
            'timing_basis' => 'classical_mean_motion',
        ];
    }

    /**
     * Modern physical-ephemeris comparison model.
     *
     * The name phase comes from the classical sequence. Timing comes from the
     * nearest prograde 30° Jupiter ingress for each classical transition.
     *
     * @return array<string, mixed>
     */
    private function getModernInfoFromJd(float $jd): array
    {
        $this->requireAstronomyService();

        $approximateK = $this->expiredKaliYearAtJd($jd);

        $boundaries = $this->collectModernProjectedBoundaries(
            $approximateK - 3,
            $approximateK + 3
        );

        [$startBoundary, $endBoundary] = $this->findBoundingBoundaries($jd, $boundaries);

        if ($startBoundary === null || $endBoundary === null) {
            $boundaries = $this->collectModernProjectedBoundaries(
                $approximateK - 10,
                $approximateK + 10
            );

            [$startBoundary, $endBoundary] = $this->findBoundingBoundaries($jd, $boundaries);
        }

        if ($startBoundary === null || $endBoundary === null) {
            throw new RuntimeException(
                'Unable to determine a complete modern Jupiter-ingress Bārhaspatya window around JD '
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
            'model_family' => self::MODEL_MODERN_EPHEMERIS,
            'model' => self::MODEL_MODERN_EPHEMERIS,
            'variant' => self::MODERN_VARIANT,
            'expired_kali_year' => (int) $startBoundary['expired_kali_year'],
            'timing_basis'
                => 'nearest_prograde_sidereal_jupiter_rashi_ingress_from_astronomy_service',
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
     * Build all continuous classical Jovian boundaries across a range of
     * expired Kali solar years.
     *
     * @return array<int, array{
     *   jd: float,
     *   new_index: int,
     *   variant: string,
     *   expired_kali_year: int
     * }>
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
                            'Derived classical Bārhaspatya boundary %.12F does not fall before next mean Meṣa %.12F for expired Kali year %d.',
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
     * Map classical transition sequence onto actual modern prograde Jupiter
     * rāśi ingresses.
     *
     * @return array<int, array{
     *   jd: float,
     *   new_index: int,
     *   variant: string,
     *   expired_kali_year: int,
     *   rashi_index: int,
     *   classical_reference_jd: float
     * }>
     */
    private function collectModernProjectedBoundaries(int $fromK, int $toK): array
    {
        $this->requireAstronomyService();

        $classicalBoundaries = $this->collectClassicalBoundaries($fromK, $toK);
        $modern = [];

        foreach ($classicalBoundaries as $classicalBoundary) {
            $mapped = $this->mapClassicalBoundaryToModernIngress($classicalBoundary);

            $key = sprintf('%.7F', (float) $mapped['jd']);

            if (isset($modern[$key])
                && (int) $modern[$key]['new_index'] !== (int) $mapped['new_index']) {
                throw new RuntimeException(
                    'Two different Samvatsara transitions mapped to the same modern Jupiter ingress near JD '
                    . $key
                );
            }

            $modern[$key] = $mapped;
        }

        $modern = array_values($modern);

        usort(
            $modern,
            static fn(array $a, array $b): int => $a['jd'] <=> $b['jd']
        );

        return $modern;
    }

    /**
     * Pair one classical name-transition with the nearest prograde sidereal
     * Jupiter rāśi ingress.
     *
     * @param array{
     *   jd: float,
     *   new_index: int,
     *   variant: string,
     *   expired_kali_year: int
     * } $classicalBoundary
     *
     * @return array{
     *   jd: float,
     *   new_index: int,
     *   variant: string,
     *   expired_kali_year: int,
     *   rashi_index: int,
     *   classical_reference_jd: float
     * }
     */
    private function mapClassicalBoundaryToModernIngress(array $classicalBoundary): array
    {
        $classicalJd = (float) $classicalBoundary['jd'];
        $cacheKey = sprintf(
            '%.9F|%d',
            $classicalJd,
            (int) $classicalBoundary['new_index']
        );

        if (!isset($this->modernIngressByClassicalBoundaryCache[$cacheKey])) {
            $this->modernIngressByClassicalBoundaryCache[$cacheKey]
                = $this->findNearestProgradeSiderealJupiterIngress($classicalJd);
        }

        $ingress = $this->modernIngressByClassicalBoundaryCache[$cacheKey];

        return [
            'jd' => (float) $ingress['jd'],
            'new_index' => (int) $classicalBoundary['new_index'],
            'variant' => self::MODERN_VARIANT,
            'expired_kali_year' => (int) $classicalBoundary['expired_kali_year'],
            'rashi_index' => (int) $ingress['rashi_index'],
            'classical_reference_jd' => $classicalJd,
        ];
    }

    /**
     * State at the mean Meṣa-saṅkrānti of expired Kali year K.
     *
     * This is the mean-Meṣa equivalent of Sewell-Dīkṣit Article 59:
     * the apparent-Meṣa subtraction and final pala correction are omitted
     * exactly as instructed in their footnote.
     *
     * @return array{
     *   index: int,
     *   classical_number: int,
     *   mesha_jd: float,
     *   first_boundary_jd: float,
     *   jovian_period_days: float,
     *   variant: string
     * }
     */
    private function stateAtMeanMesha(int $k): array
    {
        $rule = $this->ruleForExpiredKaliYear($k);

        $numerator = $rule['numerator'];
        $denominator = $rule['denominator'];

        $a = $numerator * $k;
        $q = $this->floorDiv($a, $denominator);
        $r = $this->floorMod($a, $denominator);

        /**
         * Classical result is counted with:
         *   1 = Prabhava ... 60 = Akshaya.
         *
         * Project enum is:
         *   0 = Prabhava ... 59 = Akshaya.
         */
        $classicalRemainder = $this->floorMod($q + $k + 27, 60);
        $index = $classicalRemainder === 0
            ? 59
            : $classicalRemainder - 1;

        $meshaJd = self::KALI_EPOCH_JD
            + ($k * self::SURYA_SIDDHANTA_SOLAR_YEAR_DAYS);

        /**
         * Mean-Meṣa form:
         *   (denominator − R) × 361 / denominator
         *
         * No 15-pala correction is added because the source says to omit it
         * when the rule is referred to mean Meṣa instead of apparent Meṣa.
         */
        $daysUntilEnd = (($denominator - $r) * self::RULE_JOVIAN_DAYS)
            / $denominator;

        $firstBoundaryJd = $meshaJd + $daysUntilEnd;

        /** Mean Jovian period implied by the same excess-motion fraction. */
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

    /**
     * Select the historically appropriate Sūrya-Siddhānta-family rule.
     *
     * @return array{
     *   numerator: int,
     *   denominator: int,
     *   variant: string
     * }
     */
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
     * Find nearest prograde sidereal Jupiter sign ingress to a reference JD.
     *
     * The AstronomyService is treated as the source of truth for the project's
     * modern ephemeris configuration (ephemeris engine, sidereal mode,
     * precision flags, aberration policy, etc.).
     *
     * @return array{
     *   jd: float,
     *   rashi_index: int,
     *   longitude_before: float,
     *   longitude_after: float
     * }
     */
    private function findNearestProgradeSiderealJupiterIngress(float $referenceJd): array
    {
        $this->requireAstronomyService();

        $candidates = $this->scanProgradeJupiterIngresses(
            $referenceJd - self::MODERN_PRIMARY_SEARCH_HALF_DAYS,
            $referenceJd + self::MODERN_PRIMARY_SEARCH_HALF_DAYS
        );

        if ($candidates === []) {
            $candidates = $this->scanProgradeJupiterIngresses(
                $referenceJd - self::MODERN_FALLBACK_SEARCH_HALF_DAYS,
                $referenceJd + self::MODERN_FALLBACK_SEARCH_HALF_DAYS
            );
        }

        if ($candidates === []) {
            throw new RuntimeException(
                'No prograde sidereal Jupiter rāśi ingress found around classical boundary JD '
                . sprintf('%.12F', $referenceJd)
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
     * Scan a JD interval for actual prograde Jupiter crossings of multiples of 30°.
     *
     * @return array<int, array{
     *   jd: float,
     *   rashi_index: int,
     *   longitude_before: float,
     *   longitude_after: float
     * }>
     */
    private function scanProgradeJupiterIngresses(float $fromJd, float $toJd): array
    {
        if ($toJd <= $fromJd) {
            return [];
        }

        $candidates = [];

        $leftJd = $fromJd;
        $leftLon = $this->modernJupiterSiderealLongitude($leftJd);
        $leftSign = $this->rashiIndex($leftLon);

        for (
            $rightJd = min($leftJd + self::MODERN_SCAN_STEP_DAYS, $toJd);
            $rightJd <= $toJd + self::JD_EPSILON;
            $rightJd = min($rightJd + self::MODERN_SCAN_STEP_DAYS, $toJd)
        ) {
            if ($rightJd <= $leftJd + self::JD_EPSILON) {
                break;
            }

            $rightLon = $this->modernJupiterSiderealLongitude($rightJd);
            $rightSign = $this->rashiIndex($rightLon);
            $motion = $this->signedAngularDelta($rightLon, $leftLon);

            $forwardSignDistance = (($rightSign - $leftSign) % 12 + 12) % 12;

            if ($motion > 0.0 && $forwardSignDistance === 1) {
                $targetLongitude = $rightSign * 30.0;

                $rootJd = $this->refineProgradeLongitudeCrossing(
                    $leftJd,
                    $rightJd,
                    $targetLongitude
                );

                $beforeJd = $rootJd - (2.0 / self::SECONDS_PER_DAY);
                $afterJd = $rootJd + (2.0 / self::SECONDS_PER_DAY);

                $beforeLon = $this->modernJupiterSiderealLongitude($beforeJd);
                $afterLon = $this->modernJupiterSiderealLongitude($afterJd);

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
    private function refineProgradeLongitudeCrossing(
        float $leftJd,
        float $rightJd,
        float $targetLongitude
    ): float {
        $leftValue = $this->signedLongitudeFromTarget(
            $this->modernJupiterSiderealLongitude($leftJd),
            $targetLongitude
        );
        $rightValue = $this->signedLongitudeFromTarget(
            $this->modernJupiterSiderealLongitude($rightJd),
            $targetLongitude
        );

        if ($leftValue > 0.0 || $rightValue < 0.0) {
            throw new RuntimeException(
                sprintf(
                    'Modern Jupiter ingress root is not bracketed: left=%.9F right=%.9F target=%.6F.',
                    $leftValue,
                    $rightValue,
                    $targetLongitude
                )
            );
        }

        while (($rightJd - $leftJd) > self::MODERN_ROOT_TOLERANCE_DAYS) {
            $midJd = ($leftJd + $rightJd) / 2.0;
            $midValue = $this->signedLongitudeFromTarget(
                $this->modernJupiterSiderealLongitude($midJd),
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

    /**
     * Modern geocentric sidereal Jupiter longitude from the project's
     * AstronomyService.
     */
    private function modernJupiterSiderealLongitude(float $jd): float
    {
        $astronomy = $this->requireAstronomyService();
        $utc = $this->julianDayToCarbon($jd);

        // AstronomyService's birth-array API is second-granular.
        $cacheKey = $utc->getTimestamp();

        if (array_key_exists($cacheKey, $this->modernJupiterLongitudeCache)) {
            return $this->modernJupiterLongitudeCache[$cacheKey];
        }

        $birth = [
            'year' => $utc->year,
            'month' => $utc->month,
            'day' => $utc->day,
            'hour' => $utc->hour,
            'minute' => $utc->minute,
            'second' => $utc->second,
            'timezone' => 'UTC',
            // Geocentric planetary longitude does not depend on observer
            // latitude/longitude. Values are supplied only to satisfy the
            // project's standard birth-array contract.
            'latitude' => 0.0,
            'longitude' => 0.0,
            'elevation' => 0.0,
        ];

        // Lossless JPL path (same body as astrology JME_PLANET_IDS["Jupiter"]):
        // JME_BODY_JUPITER_BARYCENTER → NAIF 5. Never use planet-center 599 here.
        $longitude = method_exists($astronomy, 'calcBodyLongitudeAtJd')
            ? (float) $astronomy->calcBodyLongitudeAtJd(
                $jd,
                JmeEphFFI::JME_BODY_JUPITER_BARYCENTER
            )
            : (float) ($astronomy->getPlanets($birth)['Jupiter']
                ?? throw new RuntimeException(
                    'AstronomyService::getPlanets() did not return Jupiter longitude.'
                ));

        if (!is_finite($longitude)) {
            throw new RuntimeException(
                'AstronomyService returned a non-finite Jupiter longitude.'
            );
        }

        return $this->modernJupiterLongitudeCache[$cacheKey]
            = $this->normalizeDegrees($longitude);
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

    private function normalizeModel(string $model): string
    {
        $key = strtolower(str_replace([' ', '-'], '_', trim($model)));

        return match ($key) {
            'classical',
            'ss',
            'surya_siddhanta',
            'surya_siddhanta_mean',
            'classical_ss' => self::MODEL_CLASSICAL_SS,

            'modern',
            'true',
            'astronomical',
            'ephemeris',
            'modern_ephemeris',
            'true_astronomical' => self::MODEL_MODERN_EPHEMERIS,

            default => throw new InvalidArgumentException(
                sprintf("Unknown Brihaspati Samvatsara model '%s'. ", $model)
                . "Supported models: '"
                . self::MODEL_CLASSICAL_SS
                . "', '"
                . self::MODEL_MODERN_EPHEMERIS
                . "'."
            ),
        };
    }

    /**
     * Find immediately preceding and following boundaries.
     *
     * Interval convention:
     *   start_jd <= jd < end_jd
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

    /**
     * Signed longitude relative to a target angle, in [-180, 180).
     *
     * For a prograde crossing:
     *   before target => negative
     *   after target  => positive
     */
    private function signedLongitudeFromTarget(float $longitude, float $target): float
    {
        $delta = $this->normalizeDegrees($longitude - $target);

        return $delta >= 180.0
            ? $delta - 360.0
            : $delta;
    }

    /** Convert DateTimeInterface to Julian Day. */
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
