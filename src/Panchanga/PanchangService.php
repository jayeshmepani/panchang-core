<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Panchanga;

use Carbon\CarbonImmutable;
use FFI\CData;
use JayeshMepani\PanchangCore\Astronomy\AstronomyService;
use JayeshMepani\PanchangCore\Astronomy\Math\IntervalTracker;
use JayeshMepani\PanchangCore\Astronomy\Math\TransitEngine;
use JayeshMepani\PanchangCore\Astronomy\SunService;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Core\Enums\CalendarType;
use JayeshMepani\PanchangCore\Core\Enums\Nakshatra;
use JayeshMepani\PanchangCore\Core\Enums\Paksha;
use JayeshMepani\PanchangCore\Core\Enums\Rasi;
use JayeshMepani\PanchangCore\Core\Enums\Tithi;
use JayeshMepani\PanchangCore\Core\Localization;
use JayeshMepani\PanchangCore\Festivals\FestivalService;
use JayeshMepani\PanchangCore\Festivals\Utils\BhadraEngine;
use JayeshMepani\PanchangCore\Panchanga\Doshas\BhadraCalculator;
use JayeshMepani\PanchangCore\Panchanga\Doshas\PanchakCalculator;
use JayeshMepani\PanchangCore\Panchanga\Residences\ShoolaCalculator;
use JayeshMepani\PanchangCore\Panchanga\Residences\VaasaCalculator;
use JayeshMepani\PanchangCore\Panchanga\Traits\PanchangAstronomyHelpersTrait;
use JayeshMepani\PanchangCore\Panchanga\Traits\PanchangBirthMonthHelpersTrait;
use JayeshMepani\PanchangCore\Panchanga\Traits\PanchangCalendarApiTrait;
use JayeshMepani\PanchangCore\Panchanga\Traits\PanchangMuhurtaYogaDelegatesTrait;
use JayeshMepani\PanchangCore\Panchanga\Traits\PanchangRuntimeEvaluationTrait;
use JayeshMepani\PanchangCore\Panchanga\Traits\PanchangSelectiveApiTrait;
use JayeshMepani\PanchangCore\Panchanga\Vrata\EkadashiParanaCalculator;
use JayeshMepani\PanchangCore\Panchanga\Yogas\SpecialYogaCalculator;
use JayeshMepani\PanchangCore\Support\DebugTrace;
use JmeEph\FFI\JmeEphFFI;
use Throwable;

/**
 * Panchang Service.
 *
 * Main service for calculating Vedic Panchanga details:
 * - Tithi, Vara, Nakṣatra, Yoga, Karaṇa
 * - Muhūrta calculations
 * - Sunrise/sunset based calculations
 */
class PanchangService
{
    use PanchangAstronomyHelpersTrait;
    use PanchangBirthMonthHelpersTrait;
    use PanchangCalendarApiTrait;
    use PanchangMuhurtaYogaDelegatesTrait;
    use PanchangRuntimeEvaluationTrait;
    use PanchangSelectiveApiTrait;

    /** @var array<int, string> */
    private const array YEARLY_SINGLE_OBSERVANCE_FESTIVALS = [
        'Ganga Dussehra',
        'Samaveda Upakarma',
    ];

    private const int BODY_LONGITUDE_CACHE_MAX = 20000;

    private const int BODY_LONGITUDE_CACHE_TRIM_TO = 10000;

    private static string $ephePath = '';

    private array $monthCache = [];

    /** @var array<string, array<string, mixed>> */
    private array $festivalSnapshotCache = [];

    /** @var array<string, float> */
    private array $bodyLongitudeCache = [];

    private readonly CData $calcBodyBuffer;

    private readonly CData $calcBodyErrorBuffer;

    private readonly VaasaCalculator $vaasaCalculator;

    private readonly ShoolaCalculator $shoolaCalculator;

    private readonly SpecialYogaCalculator $specialYogaCalculator;

    private readonly IntervalTracker $intervalTracker;

    private readonly PanchakCalculator $panchakCalculator;

    private readonly BhadraCalculator $bhadraCalculator;

    private readonly EkadashiParanaCalculator $ekadashiParanaCalculator;

    public function __construct(
        private readonly JmeEphFFI $jme,
        private readonly SunService $sunService,
        private readonly AstronomyService $astronomy,
        private readonly PanchangaEngine $panchanga,
        private readonly MuhurtaService $muhurta,
        private readonly FestivalService $festivalService,
        private readonly BhadraEngine $bhadraEngine,
        mixed $unusedTransitEngine = null,
        mixed $unusedIntervalTracker = null,
        ?VaasaCalculator $vaasaCalculator = null,
        ?ShoolaCalculator $shoolaCalculator = null,
        ?SpecialYogaCalculator $specialYogaCalculator = null,
        ?PanchakCalculator $panchakCalculator = null,
        ?BhadraCalculator $bhadraCalculator = null,
        mixed $unusedVarjyamWindowCalculator = null,
        ?EkadashiParanaCalculator $ekadashiParanaCalculator = null,
        mixed ...$unusedExtractedCalculators,
    ) {
        unset($unusedVarjyamWindowCalculator, $unusedExtractedCalculators);

        $transitEngine = $unusedTransitEngine instanceof TransitEngine
            ? $unusedTransitEngine
            : new TransitEngine($this->jme);
        $intervalTracker = $unusedIntervalTracker instanceof IntervalTracker
            ? $unusedIntervalTracker
            : new IntervalTracker($transitEngine, $this->sunService);

        $this->vaasaCalculator = $vaasaCalculator ?? new VaasaCalculator($this->sunService);
        $this->shoolaCalculator = $shoolaCalculator ?? new ShoolaCalculator($this->sunService);
        $this->intervalTracker = $intervalTracker;
        $this->specialYogaCalculator = $specialYogaCalculator ?? new SpecialYogaCalculator($this->sunService, $intervalTracker);
        $this->panchakCalculator = $panchakCalculator ?? new PanchakCalculator($intervalTracker);
        $this->bhadraCalculator = $bhadraCalculator ?? new BhadraCalculator($transitEngine, $this->bhadraEngine);
        $this->ekadashiParanaCalculator = $ekadashiParanaCalculator ?? new EkadashiParanaCalculator($transitEngine, $this->sunService);
        $ffi = $this->jme->getFFI();
        $this->calcBodyBuffer = $ffi->new('double[6]');
        $this->calcBodyErrorBuffer = $ffi->new('char[256]');

        $envEphePath = ($_ENV['PANCHANG_EPHE_PATH'] ?? false);
        $envEphePath = is_string($envEphePath) ? $envEphePath : '';

        $configEphePath = function_exists('config') ? config('panchang.ephe_path', $envEphePath) : $envEphePath;
        $ephePath = self::$ephePath !== '' ? self::$ephePath : (is_string($configEphePath) ? $configEphePath : '');
        if ($ephePath !== '' && file_exists($ephePath)) {
            $this->jme->jme_set_ephemeris_path($ephePath);
        }

        $engineMode = strtoupper((string) (function_exists('config') ? config('panchang.jme_settings.mode', 'auto') : 'auto'));
        $nativeEngine = match ($engineMode) {
            'JPL' => 'JPL',
            'MOSHIER' => 'MOSHIER',
            'VSOP_ELP_MEEUS', 'ANALYTICAL' => 'VSOP_ELP_MEEUS',
            default => 'AUTO',
        };
        $this->jme->configureEngine($nativeEngine, $ephePath !== '' ? $ephePath : null);

        // Enforce Lahiri globally for all Panchang calculations, including lightweight snapshots.
        $this->jme->jme_set_sidereal_mode(JmeEphFFI::JME_SIDEREAL_LAHIRI, 0.0, 0.0);
    }

    public static function configure(string $ephePath = ''): void
    {
        self::$ephePath = $ephePath;
    }

    public function getDayDetails(
        CarbonImmutable $date,
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0,
        ?CarbonImmutable $calculationAt = null,
        CalendarType|string $calendarType = CalendarType::Amanta,
        array $options = []
    ): array
    {
        // Normalize calendar_type from string to enum
        if (is_string($calendarType)) {
            $calendarType = match (strtolower($calendarType)) {
                'purnimanta', 'purnimant' => CalendarType::Purnimanta,
                default => CalendarType::Amanta,
            };
        }

        DebugTrace::log('panchang.day', 'starting getDayDetails', [
            'date' => $date->toDateString(),
            'tz' => $tz,
            'lat' => $lat,
            'lon' => $lon,
            'calendar_type' => $calendarType->value,
        ]);

        $birthBase = [
            'year' => $date->year,
            'month' => $date->month,
            'day' => $date->day,
            'hour' => 0,
            'minute' => 0,
            'second' => 0,
            'timezone' => $tz,
            'latitude' => $lat,
            'longitude' => $lon,
            'elevation' => $elevation,
        ];

        [$sunrise, $sunset] = $this->sunService->getSunriseSunset($birthBase);
        DebugTrace::log('panchang.day', 'sunrise/sunset resolved', [
            'sunrise' => $sunrise->toIso8601String(),
            'sunset' => $sunset->toIso8601String(),
        ]);

        $calculationAt ??= $sunrise;
        $birthAt = $birthBase;
        $birthAt['hour'] = (int) $calculationAt->format('H');
        $birthAt['minute'] = (int) $calculationAt->format('i');
        $birthAt['second'] = (int) $calculationAt->format('s');

        $relSunrise = $sunrise;
        if ($calculationAt->lessThan($sunrise)) {
            $prev = $date->subDay();
            $prevBirth = [
                'year' => $prev->year,
                'month' => $prev->month,
                'day' => $prev->day,
                'hour' => 0,
                'minute' => 0,
                'second' => 0,
                'timezone' => $tz,
                'latitude' => $lat,
                'longitude' => $lon,
                'elevation' => $elevation,
            ];
            [$relSunrise] = $this->sunService->getSunriseSunset($prevBirth);
        }

        $sunriseBirth = $birthBase;
        $sunriseBirth['year'] = (int) $relSunrise->format('Y');
        $sunriseBirth['month'] = (int) $relSunrise->format('m');
        $sunriseBirth['day'] = (int) $relSunrise->format('d');
        $sunriseBirth['hour'] = (int) $relSunrise->format('H');
        $sunriseBirth['minute'] = (int) $relSunrise->format('i');
        $sunriseBirth['second'] = (int) $relSunrise->format('s');

        $sunMoon = $this->getSunMoonLongitudes($sunriseBirth);
        $sunLon = $sunMoon['Sun'];
        $moonLon = $sunMoon['Moon'];
        $currentSunMoon = $this->getSunMoonLongitudes($birthAt);
        $currentSunLon = $currentSunMoon['Sun'];
        $currentMoonLon = $currentSunMoon['Moon'];

        $ayanamsaBirth = $birthAt;
        $ayanamsaJd = $this->astronomy->toJulianDayUtc($ayanamsaBirth);
        $ayanamsaDeg = $this->astronomy->getAyanamsa($ayanamsaJd);

        $tithi = $this->panchanga->calculateTithi($sunLon, $moonLon);
        $yoga = $this->panchanga->calculateYoga($sunLon, $moonLon);
        [$karanaName, $karanaIdx] = $this->panchanga->getKarana($sunLon, $moonLon);
        [$nakName, $nakPada, $nakLord] = $this->panchanga->getNakshatraInfo($moonLon);
        $nakIdx = (int) floor(($moonLon * 60.0) / 800.0);
        $currentTithi = $this->panchanga->calculateTithi($currentSunLon, $currentMoonLon);
        $currentYoga = $this->panchanga->calculateYoga($currentSunLon, $currentMoonLon);
        [$currentKaranaName, $currentKaranaIdx] = $this->panchanga->getKarana($currentSunLon, $currentMoonLon);
        $currentNakIdx = (int) floor(($currentMoonLon * 60.0) / 800.0);
        [$currentNakName, $currentNakPada, $currentNakLord] = $this->panchanga->getNakshatraInfo($currentMoonLon);
        $vara = $this->panchanga->calculateVara($birthAt, $this->sunService);

        $isth = $this->calculateIshtkaal($relSunrise, $birthAt, $tz);

        $ascLon = $this->astronomy->getAscendant($birthAt);
        $ascSign = AstroCore::getSign($ascLon);
        $sunriseAscLon = $this->astronomy->getAscendant($sunriseBirth);
        $sunriseAscSign = AstroCore::getSign($sunriseAscLon);
        $moonSign = AstroCore::getSign($moonLon);
        $sunSign = AstroCore::getSign($sunLon);
        $currentMoonSign = AstroCore::getSign($currentMoonLon);
        $currentSunSign = AstroCore::getSign($currentSunLon);
        $moonPhaseAtSunrise = $this->buildMoonPhase($sunLon, $moonLon);
        $currentMoonPhase = $this->buildMoonPhase($currentSunLon, $currentMoonLon);
        $varaTithiDoshas = ElectionalEvaluator::calculateVaraTithiDoshas((int) $vara['index'], (int) $currentTithi['index']);
        $nityaYogaObservations = ElectionalEvaluator::calculateNityaYogaObservations((int) $currentYoga['index'], (string) $currentYoga['name']);

        $panchakaAtSunrise = $this->panchanga->calculatePanchakaRahita(
            (int) $tithi['index'],
            (int) $vara['index'] + 1,
            $nakIdx + 1,
            $ascSign + 1
        );
        $panchakaRuntime = ElectionalEvaluator::calculatePanchakaDosha(
            (int) $currentTithi['index'],
            (int) $vara['index'],
            $currentNakIdx + 1,
            $ascSign + 1
        );
        $panchaka = [
            ...$panchakaRuntime,
            'is_auspicious' => !$panchakaRuntime['has_dosha'],
            'calculated_for' => 'input_now',
            'input_now_iso' => AstroCore::formatDateTime($calculationAt),
            'at_sunrise' => [
                ...$panchakaAtSunrise,
                'calculated_for' => 'sunrise',
                'sunrise_iso' => AstroCore::formatDateTime($relSunrise),
                'tithi' => (int) $tithi['index'],
                'tithi_name' => Tithi::from((int) $tithi['index'])->getName(),
                'nakshatra' => $nakIdx + 1,
                'nakshatra_name' => Nakshatra::from($nakIdx % 27)->getName(),
            ],
        ];

        $nextDay = $date->addDay();
        // Ensure previous sunset is calculated relative to the sunrise actually used
        // (which may be the previous day's sunrise when calculation time is before sunrise).
        $previousForSunset = $relSunrise->subDay();
        $previousBirth = [
            'year' => $previousForSunset->year,
            'month' => $previousForSunset->month,
            'day' => $previousForSunset->day,
            'hour' => 0,
            'minute' => 0,
            'second' => 0,
            'timezone' => $tz,
            'latitude' => $lat,
            'longitude' => $lon,
            'elevation' => $elevation,
        ];
        [$previousSunrise, $previousSunset] = $this->sunService->getSunriseSunset($previousBirth);
        $nextBirth = [
            'year' => $nextDay->year,
            'month' => $nextDay->month,
            'day' => $nextDay->day,
            'hour' => 0,
            'minute' => 0,
            'second' => 0,
            'timezone' => $tz,
            'latitude' => $lat,
            'longitude' => $lon,
            'elevation' => $elevation,
        ];
        [$nextSunrise] = $this->sunService->getSunriseSunset($nextBirth);
        DebugTrace::log('panchang.day', 'next sunrise resolved', [
            'next_sunrise' => $nextSunrise->toIso8601String(),
        ]);

        $twilight = $this->sunService->getTwilightTimes($birthBase);
        $solarTransits = $this->sunService->getSolarTransits($birthBase);
        $solarTransitsNext = $this->sunService->getSolarTransits($nextBirth);

        $apparentSolarDaySeconds = $solarTransitsNext['solar_noon']->getTimestamp() - $solarTransits['solar_noon']->getTimestamp();
        $civilDayStart = $date->setTime(0, 0, 0);
        $civilDayEnd = $civilDayStart->addDay();
        $civilDaySeconds = $civilDayEnd->getTimestamp() - $civilDayStart->getTimestamp();
        $dayNightMeasures = $this->buildDayNightMeasures($relSunrise, $sunset, $nextSunrise);

        $hora = $this->muhurta->calculateHora($relSunrise, $sunset, $nextSunrise, $calculationAt, $vara['index']);
        $chogadiya = $this->muhurta->calculateChogadiya($relSunrise, $sunset, $nextSunrise, $calculationAt, $vara['index']);
        $horaTable = $this->muhurta->calculateHoraTable($relSunrise, $sunset, $nextSunrise, $vara['index']);
        $chogadiyaTable = $this->muhurta->calculateChogadiyaTable($relSunrise, $sunset, $nextSunrise, $vara['index']);
        $muhurtaTable = $this->muhurta->calculateMuhurtaTable($relSunrise, $sunset, $nextSunrise);
        $badTimes = $this->muhurta->calculateBadTimes($relSunrise, $sunset, $vara['index']);
        DebugTrace::log('panchang.day', 'muhurta layer completed', [
            'hora_ruler' => $hora['ruler'] ?? null,
            'chogadiya_type' => $chogadiya['type'] ?? null,
        ]);

        $abhijit = $this->muhurta->calculateAbhijitMuhurta($relSunrise, $sunset);

        // New calculations: Prahara, Brahma Muhurta, Dur Muhurta
        $praharaTable = $this->muhurta->calculatePrahara($relSunrise, $sunset, $nextSunrise);
        $brahmaMuhurta = $this->muhurta->calculateBrahmaMuhurta($previousSunset, $relSunrise);
        $durMuhurtaTable = $this->muhurta->calculateDurMuhurta($relSunrise, $sunset, $nextSunrise, (int) $vara['index']);
        $daylightFivefold = $this->muhurta->calculateDaylightFivefoldDivision($relSunrise, $sunset);
        $nishitaMuhurta = $this->muhurta->calculateNishitaMuhurta($sunset, $nextSunrise);
        $vijayaMuhurta = $this->muhurta->calculateVijayaMuhurta($relSunrise, $sunset);
        $godhuliMuhurta = $this->muhurta->calculateGodhuliMuhurta($sunset, $nextSunrise);
        $sandhya = $this->muhurta->calculateSandhya($relSunrise, $sunset, $nextSunrise, $solarTransits['solar_noon']);
        $gowriPanchangam = $this->muhurta->calculateGowriPanchangam($relSunrise, $sunset, $nextSunrise, (int) $vara['index']);
        $kalaVela = $this->muhurta->calculateKalaVela($relSunrise, $sunset, $nextSunrise, (int) $vara['index']);

        // Lagna table for full day (Whole Sign system - exact sign entry times)
        $lagnaTable = $this->muhurta->calculateLagnaTable(
            $relSunrise,
            $sunset,
            $nextSunrise,
            $sunMoon['Sun'],
            $ayanamsaDeg,
            $lat,
            $lon,
            $this->jme
        );
        DebugTrace::log('panchang.day', 'lagna table completed', [
            'lagna_rows' => count($lagnaTable),
        ]);

        $angleStageStart = hrtime(true);
        DebugTrace::log('panchang.day', 'angle-crossing stage started');

        $jdSunrise = $this->toJulianDayFromCarbon($relSunrise, $tz);
        $jdSunset = $this->toJulianDayFromCarbon($sunset, $tz);
        $jdNextSunrise = $this->toJulianDayFromCarbon($nextSunrise, $tz);
        $jdPreviousSunrise = $this->toJulianDayFromCarbon($previousSunrise, $tz);
        $jdCalculationAt = $this->toJulianDayFromCarbon($calculationAt, $tz);

        $tithiNum = (int) $tithi['index'];
        $tithiStartAngle = ($tithiNum - 1) * 12.0;
        $tithiEndAngle = $tithiNum * 12.0;
        $tithiStartJd = $this->findAngleCrossing($jdSunrise, $tithiStartAngle, -1, fn (float $jd): float => $this->getMoonSunAngle($jd));
        $tithiEndJd = $this->findAngleCrossing($jdSunrise, $tithiEndAngle, 1, fn (float $jd): float => $this->getMoonSunAngle($jd));
        DebugTrace::log('panchang.day', 'tithi crossings resolved', [
            'tithi_start_jd' => $tithiStartJd,
            'tithi_end_jd' => $tithiEndJd,
        ]);

        $nakEndAngle = ($nakIdx + 1) * (360.0 / 27.0);
        $nakEndJd = $this->findAngleCrossing($jdSunrise, $nakEndAngle, 1, fn (float $jd): float => $this->getMoonLongitude($jd));
        $nakStartAngle = $nakIdx * (360.0 / 27.0);
        $nakStartJd = $this->findAngleCrossing($jdSunrise, $nakStartAngle, -1, fn (float $jd): float => $this->getMoonLongitude($jd));
        DebugTrace::log('panchang.day', 'nakshatra crossings resolved', [
            'nakshatra_start_jd' => $nakStartJd,
            'nakshatra_end_jd' => $nakEndJd,
        ]);

        // Varjyam (Tyajyam) can occur once or twice between sunrise and next sunrise.
        $varjyamWindows = $this->calculateVarjyamWindows($relSunrise, $sunset, $nextSunrise, $jdSunrise, $jdNextSunrise, $nakIdx, $nakStartJd, $nakEndJd);
        $varjyam = $this->buildVarjyamPayload($varjyamWindows);
        $nakshatraTyajya = $this->buildNakshatraTyajyaPayload($varjyam);
        $amritaKaalWindows = $this->calculateAmritaKaalWindows($relSunrise, $jdSunrise, $jdNextSunrise, $nakIdx, $nakStartJd, $nakEndJd);
        $amritaKaal = $this->buildAmritaKaalPayload($amritaKaalWindows);
        DebugTrace::log('panchang.day', 'varjyam payload built', [
            'window_count' => count($varjyamWindows),
        ]);

        // Pradosha Kaal: first 1/5th of night, auspicious only when Trayodashi overlaps it.
        $pradoshaKaal = $this->calculatePradoshaKaal($sunset, $nextSunrise, $jdSunset, $jdNextSunrise, $tz);

        // Lagna calculation
        $lagna = $this->muhurta->calculateLagna(
            $calculationAt,
            $relSunrise,
            $sunMoon['Sun'],
            $ayanamsaDeg,
            $lat,
            $lon,
            $this->jme
        );
        DebugTrace::log('panchang.day', 'instant lagna resolved', [
            'sign_index' => $lagna['sign_index'] ?? null,
            'degree_in_sign' => $lagna['degree_in_sign'] ?? null,
        ]);

        $yogaIdx = (int) $yoga['index'];
        $yogaEndAngle = $yogaIdx * (360.0 / 27.0);
        $yogaEndJd = $this->findAngleCrossing($jdSunrise, $yogaEndAngle, 1, fn (float $jd): float => $this->getSunMoonSum($jd));

        $karanaEndAngle = $karanaIdx * 6.0;
        $karanaEndJd = $this->findAngleCrossing($jdSunrise, $karanaEndAngle, 1, fn (float $jd): float => $this->getMoonSunAngle($jd));
        $currentTithiNum = (int) $currentTithi['index'];
        $currentTithiStartAngle = ($currentTithiNum - 1) * 12.0;
        $currentTithiStartJd = $this->findAngleCrossing($jdCalculationAt, $currentTithiStartAngle, -1, fn (float $jd): float => $this->getMoonSunAngle($jd));
        $currentTithiEndJd = $this->findAngleCrossing($jdCalculationAt, $currentTithiNum * 12.0, 1, fn (float $jd): float => $this->getMoonSunAngle($jd));
        DebugTrace::log('panchang.day', 'yoga/karana crossings resolved', [
            'yoga_end_jd' => $yogaEndJd,
            'karana_end_jd' => $karanaEndJd,
        ]);

        $kalaEngine = new KalaNirnayaEngine($lat, $lon);
        $hinduMonth = $this->getTrueHinduMonth($jdSunrise);
        DebugTrace::log('panchang.day', 'core angle-crossing layer completed', [
            'tithi_index' => $tithiNum,
            'nakshatra_index' => $nakIdx,
            'yoga_index' => $yogaIdx,
            'karana_index' => $karanaIdx,
            'month_amanta' => $hinduMonth['Month_Amanta_En'] ?? null,
            'elapsed_s' => number_format((hrtime(true) - $angleStageStart) / 1_000_000_000, 6, '.', ''),
        ]);

        $sankrantiNameMap = [
            0 => 'Mesha',
            1 => 'Vrishabha',
            2 => 'Mithuna',
            3 => 'Karka',
            4 => 'Simha',
            5 => 'Kanya',
            6 => 'Tula',
            7 => 'Vrischika',
            8 => 'Dhanu',
            9 => 'Makara',
            10 => 'Kumbha',
            11 => 'Meena',
        ];

        // Check Sankranti across the civil day (midnight to midnight) for reliable detection.
        // Sunrise-to-sunrise can miss Sankrantis that occur very close to sunrise.
        $civilStart = $date->startOfDay();
        $civilEnd = $civilStart->addDay();
        $jdCivilStart = $this->toJulianDayFromCarbon($civilStart, $tz);
        $jdCivilEnd = $this->toJulianDayFromCarbon($civilEnd, $tz);
        $sunLonCivilStart = $this->getSunLongitude($jdCivilStart);
        $sunLonCivilEnd = $this->getSunLongitude($jdCivilEnd);
        $civilStartSign = (int) floor($sunLonCivilStart / 30.0) % 12;
        $civilEndSign = (int) floor($sunLonCivilEnd / 30.0) % 12;

        $punyaKaal = null;
        $sankrantiRashi = null;
        if ($civilStartSign !== $civilEndSign) {
            // Sun crossed a sign boundary during this civil day
            $nextSign = ($civilStartSign + 1) % 12;
            $targetAngle = $nextSign * 30.0;
            // Search from civil start to find exact Sankranti moment
            $sankrantiJd = $this->findAngleCrossing($jdCivilStart, $targetAngle, 1, fn (float $jd): float => $this->getSunLongitude($jd));
            if ($sankrantiJd >= $jdCivilStart && $sankrantiJd < $jdCivilEnd) {
                $sankrantiName = $sankrantiNameMap[$nextSign];
                $sankrantiRashi = $nextSign;
                $punyaKaal = $kalaEngine->calculatePunyaKaal($sankrantiName, $sankrantiJd, $jdSunrise, $jdSunset);
                $punyaKaal['sankranti_name'] = Rasi::from($nextSign)->getName();
            }
        }

        $samvat = $this->panchanga->getSamvat($date->year, $date->month);
        $vikram = $samvat['Vikram_Samvat'];
        $saka = $samvat['Saka_Samvat'];
        $kali = $this->panchanga->getKaliSamvat($vikram);
        $gujarati = $this->panchanga->getGujaratiSamvat($vikram, $hinduMonth['Amanta_Index']);
        $samvatsara = $this->panchanga->getSamvatsara($vikram);
        $samvatsaraNorth = $this->panchanga->getSamvatsaraNorth($vikram);

        [$moonrise, $moonset] = $this->sunService->getMoonriseMoonset($birthBase);
        $snapshotEkadashiObservance = $this->buildEkadashiObservance(
            $tithiNum,
            $tithiStartJd,
            $tithiEndJd,
            $jdSunrise,
            $jdSunset,
            $jdNextSunrise,
            $tz,
            $lat,
            $lon,
            $jdPreviousSunrise,
            $hinduMonth['Month_Amanta_En'] ?? $hinduMonth['Month_Amanta'] ?? null,
            (string) ($tithi['paksha'] ?? '')
        );

        $todaySnapshot = [
            'Tithi' => $tithi,
            'Nakshatra' => [
                'name' => $nakName,
            ],
            'Hindu_Calendar' => [
                'Month_Amanta' => $hinduMonth['Month_Amanta'],
                'Month_Amanta_En' => $hinduMonth['Month_Amanta_En'],
                'Month_Purnimanta' => $hinduMonth['Month_Purnimanta'],
                'Month_Purnimanta_En' => $hinduMonth['Month_Purnimanta_En'],
                'Is_Adhika' => $hinduMonth['Is_Adhika'],
                'Is_Kshaya' => $hinduMonth['Is_Kshaya'],
                'Amanta_Index' => $hinduMonth['Amanta_Index'],
                'Purnimanta_Index' => $hinduMonth['Purnimanta_Index'],
                'Calendar_Type' => $calendarType->value,
            ],
            'Resolution_Context' => [
                'sunrise_jd' => $jdSunrise,
                'previous_sunrise_jd' => $jdPreviousSunrise,
                'sunset_jd' => $jdSunset,
                'next_sunrise_jd' => $jdNextSunrise,
                'tithi_start_jd' => $tithiStartJd,
                'tithi_end_jd' => $tithiEndJd,
                'prev_tithi_end_jd' => $tithiStartJd,
                'tithi_index_abs' => $tithiNum,
                'tithi_index_phase' => $tithiNum > 15 ? $tithiNum - 15 : $tithiNum,
                'paksha' => (string) ($tithi['paksha'] ?? ''),
                'sunrise_iso' => AstroCore::formatDateTime($relSunrise),
                'previous_sunrise_iso' => AstroCore::formatDateTime($previousSunrise),
                'sunset_iso' => AstroCore::formatDateTime($sunset),
                'next_sunrise_iso' => AstroCore::formatDateTime($nextSunrise),
                'sankranti_rashi' => $sankrantiRashi,
            ],
            'Ekadashi_Observance' => $snapshotEkadashiObservance,
        ];

        $festivalSnapshotStart = hrtime(true);
        DebugTrace::log('panchang.day', 'festival snapshot stage started');
        $tomorrowSnapshot = $this->getFestivalSnapshot($nextDay, $lat, $lon, $tz, $elevation, null, $calendarType);
        DebugTrace::log('panchang.day', 'tomorrow snapshot built');
        $yesterdaySnapshot = $this->getFestivalSnapshot($date->subDay(), $lat, $lon, $tz, $elevation, null, $calendarType);
        DebugTrace::log('panchang.day', 'festival snapshots built');
        $festivals = $this->festivalService->resolveFestivalsForDate(
            $date,
            $todaySnapshot,
            $tomorrowSnapshot,
            $yesterdaySnapshot,
            fn (CarbonImmutable $historicalDate): array => $this->getFestivalSnapshot(
                $historicalDate,
                $lat,
                $lon,
                $tz,
                $elevation,
                null,
                $calendarType
            )
        );
        $festivals = $this->retainFestivalsForDate($festivals, $date->toDateString());
        DebugTrace::log('panchang.day', 'festival resolution completed', [
            'festival_count' => count($festivals),
            'snapshot_elapsed_s' => number_format((hrtime(true) - $festivalSnapshotStart) / 1_000_000_000, 6, '.', ''),
        ]);

        $dailyObservances = $this->festivalService->getDailyObservances($todaySnapshot);
        $specialYogas = $this->calculateSpecialYogas($date, $jdSunrise, $jdNextSunrise, $tithiNum, (int) $vara['index'], $tz);
        $anandadiYoga = $this->calculateAnandadiYoga($jdSunrise, $jdNextSunrise, (int) $vara['index'], $tz, $jdCalculationAt);
        $amritadiYoga = $this->calculateAmritadiYoga($jdSunrise, $jdNextSunrise, (int) $vara['index'], $tz, $jdCalculationAt);
        $panchak = $this->calculatePanchak($jdSunrise, $jdNextSunrise, $tz);
        $maitreyaYoga = $this->calculateMaitreyaYoga($jdSunrise, $jdNextSunrise, (int) $vara['index'], $lagnaTable, $tz);
        $gajachchhayaYoga = $this->calculateGajachchhayaYoga($jdSunrise, $jdNextSunrise, $hinduMonth, $tz);
        $nakshatraShool = $this->calculateNakshatraShool($jdSunrise, $jdNextSunrise, $tz);
        $dishaShool = $this->calculateDishaShool((int) $vara['index']);
        $yatraScreening = [
            'current_at_input_now' => [
                ...$this->calculateYatraScreening((int) $currentTithi['index'], (int) $vara['index'], $currentNakIdx, $ascSign),
                'calculated_for' => 'input_now',
                'input_now_iso' => AstroCore::formatDateTime($calculationAt),
            ],
            'at_sunrise' => [
                ...$this->calculateYatraScreening($tithiNum, (int) $vara['index'], $nakIdx, $sunriseAscSign),
                'calculated_for' => 'sunrise',
                'sunrise_iso' => AstroCore::formatDateTime($relSunrise),
            ],
        ];
        $rahuVaasa = $this->calculateRahuVaasa((int) $vara['index']);
        $chandraVaasa = $this->calculateChandraVaasa($jdSunrise, $jdNextSunrise, $tz, $currentMoonLon, $jdCalculationAt);
        $shivaVaasaAtSunrise = $this->calculateShivaVaasa($tithiNum, $tithiEndJd, $tz);
        $agniVaasaAtSunrise = $this->calculateAgniVaasa($tithiNum, (int) $vara['index'], $tithiEndJd, $tz);
        $yoginiVaasaAtSunrise = $this->calculateYoginiVaasa($tithiNum);
        $shivaVaasa = [
            ...$this->calculateShivaVaasa($currentTithiNum, $currentTithiEndJd, $tz),
            'calculated_for' => 'input_now',
            'input_now_iso' => AstroCore::formatDateTime($calculationAt),
            'at_sunrise' => [
                ...$shivaVaasaAtSunrise,
                'calculated_for' => 'sunrise',
                'sunrise_iso' => AstroCore::formatDateTime($relSunrise),
            ],
        ];
        $agniVaasa = [
            ...$this->calculateAgniVaasa($currentTithiNum, (int) $vara['index'], $currentTithiEndJd, $tz),
            'calculated_for' => 'input_now',
            'input_now_iso' => AstroCore::formatDateTime($calculationAt),
            'at_sunrise' => [
                ...$agniVaasaAtSunrise,
                'calculated_for' => 'sunrise',
                'sunrise_iso' => AstroCore::formatDateTime($relSunrise),
            ],
        ];
        $yoginiVaasa = [
            ...$this->calculateYoginiVaasa($currentTithiNum),
            'calculated_for' => 'input_now',
            'input_now_iso' => AstroCore::formatDateTime($calculationAt),
            'at_sunrise' => [
                ...$yoginiVaasaAtSunrise,
                'calculated_for' => 'sunrise',
                'sunrise_iso' => AstroCore::formatDateTime($relSunrise),
            ],
        ];
        $transitionSignals = $this->buildTransitionSignals(
            $jdSunrise,
            $jdNextSunrise,
            $sunLon,
            $moonLon,
            $currentSunLon,
            $currentMoonLon,
            $tithiNum,
            (int) $currentTithi['index'],
            $nakIdx,
            $currentNakIdx,
            (int) $yoga['index'],
            (int) $currentYoga['index'],
            (int) $karanaIdx,
            (int) $currentKaranaIdx,
            $tz,
            $sankrantiRashi
        );
        $ekadashiObservance = $this->buildEkadashiObservance(
            $tithiNum,
            $tithiStartJd,
            $tithiEndJd,
            $jdSunrise,
            $jdSunset,
            $jdNextSunrise,
            $tz,
            $lat,
            $lon,
            $jdPreviousSunrise,
            $hinduMonth['Month_Amanta_En'] ?? $hinduMonth['Month_Amanta'] ?? null,
            (string) ($tithi['paksha'] ?? '')
        );
        $tithiObservanceAnalysis = [
            'rule_system' => 'smarta_udaya_tithi_structural_analysis',
            'is_festival_specific_engine' => false,
            'festival_specific_override_required' => true,
            'current_at_input_now' => [
                ...$this->buildTithiObservanceAnalysis($kalaEngine, $currentTithiNum, $currentTithiStartJd, $currentTithiEndJd, $jdSunrise, $jdNextSunrise, $currentTithiStartJd, $tz),
                'calculated_for' => 'input_now',
                'input_now_iso' => AstroCore::formatDateTime($calculationAt),
            ],
            'at_sunrise' => [
                ...$this->buildTithiObservanceAnalysis($kalaEngine, $tithiNum, $tithiStartJd, $tithiEndJd, $jdSunrise, $jdNextSunrise, $tithiStartJd, $tz),
                'calculated_for' => 'sunrise',
                'sunrise_iso' => AstroCore::formatDateTime($relSunrise),
            ],
        ];
        $vrataParana = [
            'rule_system' => 'supported_non_ekadashi_vrata_parana_profiles',
            'is_complete_system' => false,
            'supported_families' => $this->supportedVrataParanaFamilies(),
            'current_at_input_now' => $this->buildVrataParanaProfile((int) $currentTithi['index'], (string) ($currentTithi['paksha'] ?? ''), $currentTithiStartJd, $currentTithiEndJd, $jdSunrise, $jdSunset, $jdNextSunrise, $moonrise instanceof CarbonImmutable ? $this->toJulianDayFromCarbon($moonrise, $tz) : null, $pradoshaKaal, $nishitaMuhurta, $tz),
            'at_sunrise' => $this->buildVrataParanaProfile($tithiNum, (string) ($tithi['paksha'] ?? ''), $tithiStartJd, $tithiEndJd, $jdSunrise, $jdSunset, $jdNextSunrise, $moonrise instanceof CarbonImmutable ? $this->toJulianDayFromCarbon($moonrise, $tz) : null, $pradoshaKaal, $nishitaMuhurta, $tz),
        ];
        DebugTrace::log('panchang.day', 'advanced observance layer completed');

        // Build fivefold lookup by English name (lowercase) since translated names may differ
        $englishNames = ['pratah', 'sangava', 'madhyahna', 'aparahna', 'sayahna'];
        $daylightFivefoldByName = [];
        foreach ($daylightFivefold as $index => $division) {
            if (is_array($division) && isset($englishNames[$index])) {
                $daylightFivefoldByName[$englishNames[$index]] = $division;
            }
        }

        $karmakalaWindows = [
            'sunrise' => [
                'label' => Localization::translate('String', 'Sunrise'),
                'type' => 'instant',
                'time' => AstroCore::formatTime($relSunrise),
                'time_iso' => AstroCore::formatDateTime($relSunrise),
            ],
            'pratah' => $daylightFivefoldByName['pratah'] ?? null,
            'sangava' => $daylightFivefoldByName['sangava'] ?? null,
            'madhyahna' => $daylightFivefoldByName['madhyahna'] ?? null,
            'aparahna' => $daylightFivefoldByName['aparahna'] ?? null,
            'sayahna' => $daylightFivefoldByName['sayahna'] ?? null,
            'pradosha' => $pradoshaKaal,
            'nishitha' => $nishitaMuhurta,
            'brahma_muhurta' => $brahmaMuhurta,
            'abhijit' => $abhijit,
            'vijaya' => $vijayaMuhurta,
            'godhuli' => $godhuliMuhurta,
        ];

        $defaultConfig = function_exists('config') ? config('panchang.defaults', []) : [];
        if (!is_array($defaultConfig)) {
            $defaultConfig = [];
        }

        $payload = [
            'Display_Settings' => [
                'measurement_system' => $options['measurement_system'] ?? $defaultConfig['measurement_system'] ?? 'indian_metric',
                'date_time_format' => $options['date_time_format'] ?? $defaultConfig['date_time_format'] ?? 'indian_12h',
                'time_notation' => $options['time_notation'] ?? $defaultConfig['time_notation'] ?? '12h',
                'coordinate_format' => $options['coordinate_format'] ?? $defaultConfig['coordinate_format'] ?? 'decimal',
                'angle_unit' => $options['angle_unit'] ?? $defaultConfig['angle_unit'] ?? 'degree',
                'duration_format' => $options['duration_format'] ?? $defaultConfig['duration_format'] ?? 'mixed',
                'timezone' => $tz,
            ],
            'Units' => [
                'Ayanamsa_Degree' => 'degree',
                'Ayanamsa_JD' => 'julian_day',
                'sun_sunrise_lon' => 'degree',
                'moon_sunrise_lon' => 'degree',
                'Hora.hora_duration_seconds' => 'second',
                'Hora.hora_duration_minutes' => 'minute',
                'Chogadiya.division_duration_minutes' => 'minute',
                'Rahu_Kaal_Gulika_Yamaganda.Rahu_Kaal.duration_min' => 'minute',
                'Rahu_Kaal_Gulika_Yamaganda.Gulika.duration_min' => 'minute',
                'Rahu_Kaal_Gulika_Yamaganda.Yamaganda.duration_min' => 'minute',
                'Abhijit_Muhurta.muhurta_duration_minutes' => 'minute',
                'Day_Types.civil_day_length_seconds' => 'second',
                'Day_Types.mean_solar_day_seconds' => 'second',
                'Day_Types.apparent_solar_day_seconds' => 'second',
                'Dharma_Sindhu.Punya_Kaal.duration_minutes' => 'minute',
                'Dharma_Sindhu.Punya_Kaal.duration_ghatikas' => 'ghatika',
                'Dharma_Sindhu.Punya_Kaal.sankranti_jd' => 'julian_day',
                'Dharma_Sindhu.Punya_Kaal.punya_kaal_start_jd' => 'julian_day',
                'Dharma_Sindhu.Punya_Kaal.punya_kaal_end_jd' => 'julian_day',
                'Prahara.duration_seconds' => 'second',
                'Prahara.duration_hours' => 'hour',
                'Daylight_Fivefold_Division.duration_seconds' => 'second',
                'Daylight_Fivefold_Division.duration_hours' => 'hour',
                'Brahma_Muhurta.duration_minutes' => 'minute',
                'Brahma_Muhurta.duration_seconds' => 'second',
                'Dur_Muhurta.duration_seconds' => 'second',
                'Nishita_Muhurta.muhurta_duration_seconds' => 'second',
                'Nishita_Muhurta.muhurta_duration_minutes' => 'minute',
                'Vijaya_Muhurta.muhurta_duration_seconds' => 'second',
                'Vijaya_Muhurta.muhurta_duration_minutes' => 'minute',
                'Godhuli_Muhurta.duration_seconds' => 'second',
                'Godhuli_Muhurta.duration_minutes' => 'minute',
                'Sandhya.Pratah_Sandhya.duration_seconds' => 'second',
                'Sandhya.Pratah_Sandhya.duration_minutes' => 'minute',
                'Sandhya.Madhyahna_Sandhya.duration_seconds' => 'second',
                'Sandhya.Madhyahna_Sandhya.duration_minutes' => 'minute',
                'Sandhya.Sayahna_Sandhya.duration_seconds' => 'second',
                'Sandhya.Sayahna_Sandhya.duration_minutes' => 'minute',
                'Gowri_Panchangam.division_duration_seconds' => 'second',
                'Gowri_Panchangam.division_duration_minutes' => 'minute',
                'Kala_Vela.division_duration_seconds' => 'second',
                'Kala_Vela.division_duration_minutes' => 'minute',
                'Varjyam.duration_minutes' => 'minute',
                'Amrita_Kaal.duration_minutes' => 'minute',
                'Pradosha_Kaal.duration_minutes' => 'minute',
                'Lagna.lagna_longitude_nirayana' => 'degree',
                'Lagna.lagna_longitude_sayana' => 'degree',
                'Lagna.degree_in_sign' => 'degree',
                'Lagna.ayanamsa_applied' => 'degree',
            ],
            'Tithi' => $tithi,
            'Tithi_At_Sunrise' => $tithi,
            'Current_Tithi_At_Input_Now' => $currentTithi,
            'Vara' => $vara,
            'Nakshatra' => [
                'name' => $nakName,
                'pada' => $nakPada,
                'lord' => $nakLord,
            ],
            'Nakshatra_At_Sunrise' => [
                'name' => $nakName,
                'pada' => $nakPada,
                'lord' => $nakLord,
            ],
            'Current_Nakshatra_At_Input_Now' => [
                'name' => $currentNakName,
                'pada' => $currentNakPada,
                'lord' => $currentNakLord,
            ],
            'Yoga' => $yoga,
            'Current_Yoga_At_Input_Now' => $currentYoga,
            'Karana' => ['name' => $karanaName, 'index' => $karanaIdx],
            'Karana_At_Sunrise' => ['name' => $karanaName, 'index' => $karanaIdx],
            'Current_Karana_At_Input_Now' => ['name' => $currentKaranaName, 'index' => $currentKaranaIdx],
            'Is_Vishti_Karana' => $this->panchanga->isVishtiKarana($sunLon, $moonLon),
            'Sunrise' => AstroCore::formatTime($relSunrise),
            'Sunset' => AstroCore::formatTime($sunset),
            'Ishtkaal' => $isth,
            'Ishtkaal_iso' => AstroCore::formatDateTime($calculationAt),
            'sun_sunrise_lon' => AstroCore::formatAngle($sunLon),
            'moon_sunrise_lon' => AstroCore::formatAngle($moonLon),
            'sunrise_hm' => [(int) $relSunrise->format('H'), (int) $relSunrise->format('i')],
            'sunrise_dt' => AstroCore::formatDateTime($relSunrise),
            'Ayanamsa' => 'LAHIRI (Chitra Paksha)',
            'Ayanamsa_Degree' => AstroCore::formatAngle($ayanamsaDeg),
            'Ayanamsa_At' => AstroCore::formatDateTime($calculationAt),
            'Ayanamsa_JD' => $ayanamsaJd,
            'Day_Types' => [
                'civil_day_start' => AstroCore::formatDateTime($civilDayStart),
                'civil_day_end' => AstroCore::formatDateTime($civilDayEnd),
                'civil_day_length_seconds' => $civilDaySeconds,
                'mean_solar_day_seconds' => 86400.0,
                'apparent_solar_day_seconds' => $apparentSolarDaySeconds,
                'apparent_solar_noon' => AstroCore::formatDateTime($solarTransits['solar_noon']),
                'solar_midnight' => AstroCore::formatDateTime($solarTransits['solar_midnight']),
            ],
            'Twilight' => [
                'civil' => [
                    'dawn' => AstroCore::formatDateTime($twilight['civil']['dawn']),
                    'dusk' => AstroCore::formatDateTime($twilight['civil']['dusk']),
                ],
                'nautical' => [
                    'dawn' => AstroCore::formatDateTime($twilight['nautical']['dawn']),
                    'dusk' => AstroCore::formatDateTime($twilight['nautical']['dusk']),
                ],
                'astronomical' => [
                    'dawn' => AstroCore::formatDateTime($twilight['astronomical']['dawn']),
                    'dusk' => AstroCore::formatDateTime($twilight['astronomical']['dusk']),
                ],
            ],
            'Panchanga' => [
                'Tithi' => $tithi,
                'Tithi_At_Sunrise' => $tithi,
                'Current_Tithi_At_Input_Now' => $currentTithi,
                'Vara' => $vara,
                'Nakshatra' => [
                    'name' => $nakName,
                    'pada' => $nakPada,
                    'lord' => $nakLord,
                ],
                'Nakshatra_At_Sunrise' => [
                    'name' => $nakName,
                    'pada' => $nakPada,
                    'lord' => $nakLord,
                ],
                'Current_Nakshatra_At_Input_Now' => [
                    'name' => $currentNakName,
                    'pada' => $currentNakPada,
                    'lord' => $currentNakLord,
                ],
                'Yoga' => $yoga,
                'Current_Yoga_At_Input_Now' => $currentYoga,
                'Karana' => ['name' => $karanaName, 'index' => $karanaIdx],
                'Karana_At_Sunrise' => ['name' => $karanaName, 'index' => $karanaIdx],
                'Current_Karana_At_Input_Now' => ['name' => $currentKaranaName, 'index' => $currentKaranaIdx],
                'Sunrise' => [
                    'jd' => $jdSunrise,
                    'iso' => AstroCore::formatDateTime($relSunrise),
                    'display' => AstroCore::formatTime($relSunrise),
                    'timestamp' => $relSunrise->getTimestamp(),
                ],
                'Sunset' => [
                    'jd' => $jdSunset,
                    'iso' => AstroCore::formatDateTime($sunset),
                    'display' => AstroCore::formatTime($sunset),
                    'timestamp' => $sunset->getTimestamp(),
                ],
                'Moonrise' => [
                    'jd' => $moonrise instanceof CarbonImmutable ? $this->toJulianDayFromCarbon($moonrise, $tz) : null,
                    'iso' => $moonrise instanceof CarbonImmutable ? AstroCore::formatDateTime($moonrise) : null,
                    'display' => $moonrise instanceof CarbonImmutable ? AstroCore::formatTime($moonrise) : null,
                    'timestamp' => $moonrise instanceof CarbonImmutable ? $moonrise->getTimestamp() : null,
                ],
                'Moonset' => [
                    'jd' => $moonset instanceof CarbonImmutable ? $this->toJulianDayFromCarbon($moonset, $tz) : null,
                    'iso' => $moonset instanceof CarbonImmutable ? AstroCore::formatDateTime($moonset) : null,
                    'display' => $moonset instanceof CarbonImmutable ? AstroCore::formatTime($moonset) : null,
                    'timestamp' => $moonset instanceof CarbonImmutable ? $moonset->getTimestamp() : null,
                ],
                'Ishtkaal' => $isth,
                'Ishtkaal_iso' => AstroCore::formatDateTime($calculationAt),
                'sun_sunrise_lon' => AstroCore::formatAngle($sunLon),
                'moon_sunrise_lon' => AstroCore::formatAngle($moonLon),
                'sunrise_hm' => [(int) $relSunrise->format('H'), (int) $relSunrise->format('i')],
                'sunrise_dt' => AstroCore::formatDateTime($relSunrise),
                'tithi_start_dt' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($tithiStartJd, $tz)),
                'tithi_end_dt' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($tithiEndJd, $tz)),
                'nakshatra_end_dt' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($nakEndJd, $tz)),
                'yoga_end_dt' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($yogaEndJd, $tz)),
                'karana_end_dt' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($karanaEndJd, $tz)),
            ],
            'Hindu_Calendar' => [
                'Ayana' => $this->panchanga->getAyana($sunLon),
                'Ritu' => $this->panchanga->getRitu($sunLon),
                'Vikram_Samvat' => $vikram,
                'Gujarati_Samvat' => $gujarati,
                'Saka_Samvat' => $saka,
                'Kali_Samvat' => $kali,
                'Samvatsara' => $samvatsara,
                'Samvatsara_North' => $samvatsaraNorth,
                'Month_Amanta' => $hinduMonth['Month_Amanta'],
                'Month_Amanta_En' => $hinduMonth['Month_Amanta_En'],
                'Month_Purnimanta' => $hinduMonth['Month_Purnimanta'],
                'Month_Purnimanta_En' => $hinduMonth['Month_Purnimanta_En'],
                'Is_Adhika' => $hinduMonth['Is_Adhika'],
                'Is_Kshaya' => $hinduMonth['Is_Kshaya'],
                'Amanta_Index' => $hinduMonth['Amanta_Index'],
                'Purnimanta_Index' => $hinduMonth['Purnimanta_Index'],
                'Calendar_Type' => $calendarType->value,
            ],
            'Resolution_Context' => $todaySnapshot['Resolution_Context'],
            'Chart_Auxiliary' => [
                'Sun_Sign' => Rasi::from($currentSunSign)->getName(),
                'Moon_Sign' => Rasi::from($currentMoonSign)->getName(),
            ],
            'Moon_Phase_At_Sunrise' => $moonPhaseAtSunrise,
            'Current_Moon_Phase_At_Input_Now' => $currentMoonPhase,
            'Festivals' => $festivals,
            'Daily_Observances' => $dailyObservances,
            'Special_Yogas' => $specialYogas,
            'Anandadi_Yoga' => $anandadiYoga,
            'Amritadi_Yoga' => $amritadiYoga,
            'Nitya_Yoga_Observations' => $nityaYogaObservations,
            'Panchak' => $panchak,
            'Maitreya_Yoga' => $maitreyaYoga,
            'Gajachchhaya_Yoga' => $gajachchhayaYoga,
            'Nakshatra_Shool' => $nakshatraShool,
            'Disha_Shool' => $dishaShool,
            'Yatra_Screening' => $yatraScreening,
            'Rahu_Vaasa' => $rahuVaasa,
            'Chandra_Vaasa' => $chandraVaasa,
            'Shiva_Vaasa' => $shivaVaasa,
            'Agni_Vaasa' => $agniVaasa,
            'Yogini_Vaasa' => $yoginiVaasa,
            'Ekadashi_Observance' => $ekadashiObservance,
            'Tithi_Observance_Analysis' => $tithiObservanceAnalysis,
            'Vrata_Parana' => $vrataParana,
            'Transitions' => $transitionSignals,
            'Panchaka_Rahita' => $panchaka,
            'Vara_Tithi_Doshas' => $varaTithiDoshas,
            'Hora' => $hora,
            'Chogadiya' => $chogadiya,
            'Chogadiya_Duration' => [
                'day_each_seconds' => ($sunset->getTimestamp() - $relSunrise->getTimestamp()) / 8.0,
                'night_each_seconds' => ($nextSunrise->getTimestamp() - $sunset->getTimestamp()) / 8.0,
            ],
            'Hora_Full_Day' => $horaTable,
            'Chogadiya_Full_Day' => $chogadiyaTable,
            'Muhurta_Full_Day' => $muhurtaTable,
            'Rahu_Kaal_Gulika_Yamaganda' => $badTimes,
            'Abhijit_Muhurta' => $abhijit,

            'Prahara_Full_Day' => $praharaTable,
            'Daylight_Fivefold_Division' => $daylightFivefold,
            'Brahma_Muhurta' => $brahmaMuhurta,
            'Dur_Muhurta_Full_Day' => $durMuhurtaTable,
            'Nishita_Muhurta' => $nishitaMuhurta,
            'Vijaya_Muhurta' => $vijayaMuhurta,
            'Godhuli_Muhurta' => $godhuliMuhurta,
            'Sandhya' => [
                'pratah_sandhya' => [
                    ...$sandhya['pratah_sandhya'],
                    'duration_human' => AstroCore::formatDuration($sandhya['pratah_sandhya']['duration_seconds'] / 60.0),
                ],
                'madhyahna_sandhya' => [
                    ...$sandhya['madhyahna_sandhya'],
                    'duration_human' => AstroCore::formatDuration($sandhya['madhyahna_sandhya']['duration_seconds'] / 60.0),
                ],
                'sayahna_sandhya' => [
                    ...$sandhya['sayahna_sandhya'],
                    'duration_human' => AstroCore::formatDuration($sandhya['sayahna_sandhya']['duration_seconds'] / 60.0),
                ],
            ],
            'Gowri_Panchangam' => $gowriPanchangam,
            'Kala_Vela' => [
                'named_kala_velas' => $kalaVela['named_kala_velas'],
                'day' => $kalaVela['day'],
                'night' => $kalaVela['night'],
            ],
            'Day_Night_Measures' => $dayNightMeasures,
            'Karmakala_Windows' => $karmakalaWindows,
            'Varjyam' => $varjyam,
            'Nakshatra_Tyajya' => $nakshatraTyajya,
            'Amrita_Kaal' => $amritaKaal,
            'Pradosha_Kaal' => $pradoshaKaal,
            'Lagna' => $lagna,
            'Lagna_Full_Day' => $lagnaTable,

            'Bhadra' => $this->findBhadraPeriods($jdSunrise, $jdNextSunrise, $tithiNum, (string) ($tithi['paksha'] ?? '')),

            'Dharma_Sindhu' => array_filter([
                'Punya_Kaal' => $punyaKaal,
                'Ekadashi_Observance' => $ekadashiObservance,
                'Tithi_Observance_Analysis' => $tithiObservanceAnalysis,
                'Shiva_Vaasa' => $shivaVaasa,
                'Agni_Vaasa' => $agniVaasa,
                'Yogini_Vaasa' => $yoginiVaasa,
            ], static fn (?array $v): bool => $v !== null),
        ];

        return $this->annotateTimeOnlyFieldsWithDateTime($payload, $relSunrise, $tz);
    }

    /**
     * Lightweight daily snapshot for festival resolution.
     * Avoids heavy muhurta/tithi-boundary computations to keep yearly festival listing stable.
     */
    public function getFestivalSnapshot(
        CarbonImmutable $date,
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0,
        ?CarbonImmutable $calculationAt = null,
        CalendarType|string $calendarType = CalendarType::Amanta,
        bool $includeExtended = true
    ): array {
        // Normalize calendar_type from string to enum
        if (is_string($calendarType)) {
            $calendarType = match (strtolower($calendarType)) {
                'purnimanta', 'purnimant' => CalendarType::Purnimanta,
                default => CalendarType::Amanta,
            };
        }

        $locale = AstroCore::getConfig('panchang.defaults.locale', 'en');

        $snapshotCacheKey = implode('|', [
            $date->toDateString(),
            sprintf('%.12F', $lat),
            sprintf('%.12F', $lon),
            $tz,
            sprintf('%.6F', $elevation),
            $calculationAt?->toIso8601String() ?? '',
            $calendarType->value,
            $includeExtended ? 'extended' : 'basic',
            $locale,
        ]);
        if (isset($this->festivalSnapshotCache[$snapshotCacheKey])) {
            return $this->festivalSnapshotCache[$snapshotCacheKey];
        }

        $birthBase = [
            'year' => $date->year,
            'month' => $date->month,
            'day' => $date->day,
            'hour' => 0,
            'minute' => 0,
            'second' => 0,
            'timezone' => $tz,
            'latitude' => $lat,
            'longitude' => $lon,
            'elevation' => $elevation,
        ];

        [$sunrise, $sunset] = $this->sunService->getSunriseSunset($birthBase);
        [$moonrise, $moonset] = $this->sunService->getMoonriseMoonset($birthBase);
        $previousDay = $date->subDay();
        [$previousSunrise] = $this->sunService->getSunriseSunset([
            ...$birthBase,
            'year' => $previousDay->year,
            'month' => $previousDay->month,
            'day' => $previousDay->day,
        ]);
        $nextDay = $date->addDay();
        [$nextSunrise] = $this->sunService->getSunriseSunset([
            ...$birthBase,
            'year' => $nextDay->year,
            'month' => $nextDay->month,
            'day' => $nextDay->day,
        ]);

        // Use fixed calculation reference (sunrise) for deterministic Ayanamsa
        $ayanamsaRef = $calculationAt ?? $sunrise;
        $ayanamsaJd = $this->toJulianDayFromCarbon($ayanamsaRef, $tz);
        $ayanamsaDeg = $this->astronomy->getAyanamsa($ayanamsaJd);

        $jdSunrise = $this->toJulianDayFromCarbon($sunrise, $tz);
        $jdPreviousSunrise = $this->toJulianDayFromCarbon($previousSunrise, $tz);
        $jdSunset = $this->toJulianDayFromCarbon($sunset, $tz);
        $jdNextSunrise = $this->toJulianDayFromCarbon($nextSunrise, $tz);
        $moonSunAngleAtSunset = $this->getMoonSunAngle($jdSunset);
        $moonIlluminationAtSunset = (1.0 - cos(deg2rad($moonSunAngleAtSunset))) / 2.0;
        $sunriseBirth = [
            ...$birthBase,
            'year' => (int) $sunrise->format('Y'),
            'month' => (int) $sunrise->format('m'),
            'day' => (int) $sunrise->format('d'),
            'hour' => (int) $sunrise->format('H'),
            'minute' => (int) $sunrise->format('i'),
            'second' => (int) $sunrise->format('s'),
        ];

        $sunMoon = $this->getSunMoonLongitudes($sunriseBirth);
        $sunLon = $sunMoon['Sun'];
        $moonLon = $sunMoon['Moon'];

        $tithi = $this->panchanga->calculateTithi($sunLon, $moonLon);
        $yoga = $this->panchanga->calculateYoga($sunLon, $moonLon);
        [$karanaName, $karanaIdx] = $this->panchanga->getKarana($sunLon, $moonLon);
        [$nakName, $nakPada, $nakLord] = $this->panchanga->getNakshatraInfo($moonLon);
        // Use sunrise timestamp for snapshot weekday to avoid pre-sunrise rollback
        // from 00:00 civil time into previous day's vara.
        $vara = $this->panchanga->calculateVara($sunriseBirth, $this->sunService);

        $hinduMonth = $this->getTrueHinduMonth($jdSunrise);

        $tithiNum = (int) ($tithi['index'] ?? 0);
        $tithiStartAngle = ($tithiNum - 1) * 12.0;
        $tithiEndAngle = $tithiNum * 12.0;
        $tithiStartJd = $this->findAngleCrossing($jdSunrise, $tithiStartAngle, -1, fn (float $jd): float => $this->getMoonSunAngle($jd));
        $tithiEndJd = $this->findAngleCrossing($jdSunrise, $tithiEndAngle, 1, fn (float $jd): float => $this->getMoonSunAngle($jd));
        $prevTithiEndJd = $tithiStartJd;

        // Sankranti date should map to the civil date on which ingress occurs.
        // Using sunrise->next-sunrise can backshift pre-sunrise ingress to previous date.
        $civilStart = $date->startOfDay();
        $civilEnd = $civilStart->addDay();
        $jdCivilStart = $this->toJulianDayFromCarbon($civilStart, $tz);
        $jdCivilEnd = $this->toJulianDayFromCarbon($civilEnd, $tz);
        $sunLonCivilStart = $this->getSunLongitude($jdCivilStart);
        $sunLonCivilEnd = $this->getSunLongitude($jdCivilEnd);
        $currentSign = (int) floor($sunLonCivilStart / 30.0);
        $nextSunriseSign = (int) floor($sunLonCivilEnd / 30.0);
        $sankrantiRashi = null;
        if ($currentSign !== $nextSunriseSign) {
            $sankrantiRashi = ($currentSign + 1) % 12;
        }

        $lagnaTable = $includeExtended
            ? $this->muhurta->calculateLagnaTable(
                $sunrise,
                $sunset,
                $nextSunrise,
                $sunLon,
                $ayanamsaDeg,
                $lat,
                $lon,
                $this->jme
            )
            : [];
        $specialYogas = $includeExtended ? $this->calculateSpecialYogas($date, $jdSunrise, $jdNextSunrise, $tithiNum, (int) $vara['index'], $tz) : [];
        $anandadiYoga = $includeExtended ? $this->calculateAnandadiYoga($jdSunrise, $jdNextSunrise, (int) $vara['index'], $tz) : [];
        $amritadiYoga = $includeExtended ? $this->calculateAmritadiYoga($jdSunrise, $jdNextSunrise, (int) $vara['index'], $tz) : [];
        $panchak = $includeExtended ? $this->calculatePanchak($jdSunrise, $jdNextSunrise, $tz) : [];
        $maitreyaYoga = $includeExtended ? $this->calculateMaitreyaYoga($jdSunrise, $jdNextSunrise, (int) $vara['index'], $lagnaTable, $tz) : [];
        $gajachchhayaYoga = $includeExtended ? $this->calculateGajachchhayaYoga($jdSunrise, $jdNextSunrise, $hinduMonth, $tz) : [];
        $nakshatraShool = $includeExtended ? $this->calculateNakshatraShool($jdSunrise, $jdNextSunrise, $tz) : [];
        $dishaShool = $this->calculateDishaShool((int) $vara['index']);
        $rahuVaasa = $this->calculateRahuVaasa((int) $vara['index']);
        $chandraVaasa = $this->calculateChandraVaasa($jdSunrise, $jdNextSunrise, $tz, $moonLon);
        $shivaVaasa = $this->calculateShivaVaasa($tithiNum, $tithiEndJd, $tz);
        $agniVaasa = $this->calculateAgniVaasa($tithiNum, (int) $vara['index'], $tithiEndJd, $tz);
        $yoginiVaasa = $this->calculateYoginiVaasa($tithiNum);
        $ekadashiObservance = $this->buildEkadashiObservance(
            $tithiNum,
            $tithiStartJd,
            $tithiEndJd,
            $jdSunrise,
            $jdSunset,
            $jdNextSunrise,
            $tz,
            $lat,
            $lon,
            $jdPreviousSunrise,
            $hinduMonth['Month_Amanta_En'] ?? $hinduMonth['Month_Amanta'] ?? null,
            (string) ($tithi['paksha'] ?? '')
        );

        return $this->festivalSnapshotCache[$snapshotCacheKey] = [
            'Tithi' => $tithi,
            'Vara' => $vara,
            'Nakshatra' => [
                'name' => $nakName,
                'pada' => $nakPada,
                'lord' => $nakLord,
            ],
            'Yoga' => $yoga,
            'Karana' => ['name' => $karanaName, 'index' => $karanaIdx],
            'Sun_Sign' => Rasi::from(AstroCore::getSign($sunLon))->getName(),
            'Sun_Sign_Index' => AstroCore::getSign($sunLon),
            'Moon_Sign' => Rasi::from(AstroCore::getSign($moonLon))->getName(),
            'Moon_Sign_Index' => AstroCore::getSign($moonLon),
            'Moon_Phase' => $this->buildMoonPhase($sunLon, $moonLon),
            'Hindu_Calendar' => [
                'Month_Amanta' => $hinduMonth['Month_Amanta'],
                'Month_Amanta_En' => $hinduMonth['Month_Amanta_En'],
                'Month_Purnimanta' => $hinduMonth['Month_Purnimanta'],
                'Month_Purnimanta_En' => $hinduMonth['Month_Purnimanta_En'],
                'Is_Adhika' => $hinduMonth['Is_Adhika'],
                'Is_Kshaya' => $hinduMonth['Is_Kshaya'],
                'Amanta_Index' => $hinduMonth['Amanta_Index'],
                'Purnimanta_Index' => $hinduMonth['Purnimanta_Index'],
                'Calendar_Type' => $calendarType->value,
            ],
            'Sunrise' => AstroCore::formatTime($sunrise),
            'Sunset' => AstroCore::formatTime($sunset),
            'Moonrise' => $moonrise instanceof CarbonImmutable ? AstroCore::formatTime($moonrise) : null,
            'Moonset' => $moonset instanceof CarbonImmutable ? AstroCore::formatTime($moonset) : null,
            'Moonrise_JD' => $moonrise instanceof CarbonImmutable ? $this->toJulianDayFromCarbon($moonrise, $tz) : null,
            'Moonset_JD' => $moonset instanceof CarbonImmutable ? $this->toJulianDayFromCarbon($moonset, $tz) : null,
            'Moonrise_Date' => $moonrise instanceof CarbonImmutable ? $moonrise->toDateString() : null,
            'Moonset_Date' => $moonset instanceof CarbonImmutable ? $moonset->toDateString() : null,
            'Moonrise_ISO' => $moonrise instanceof CarbonImmutable ? AstroCore::formatDateTime($moonrise) : null,
            'Moonset_ISO' => $moonset instanceof CarbonImmutable ? AstroCore::formatDateTime($moonset) : null,
            'Moonset_Day_Relation' => $this->moonsetDayRelation($date, $moonset),
            'Special_Yogas' => $specialYogas,
            'Anandadi_Yoga' => $anandadiYoga,
            'Amritadi_Yoga' => $amritadiYoga,
            'Panchak' => $panchak,
            'Maitreya_Yoga' => $maitreyaYoga,
            'Gajachchhaya_Yoga' => $gajachchhayaYoga,
            'Nakshatra_Shool' => $nakshatraShool,
            'Disha_Shool' => $dishaShool,
            'Rahu_Vaasa' => $rahuVaasa,
            'Chandra_Vaasa' => $chandraVaasa,
            'Shiva_Vaasa' => $shivaVaasa,
            'Agni_Vaasa' => $agniVaasa,
            'Yogini_Vaasa' => $yoginiVaasa,
            'Ekadashi_Observance' => $ekadashiObservance,
            'Resolution_Context' => [
                'sunrise_jd' => $jdSunrise,
                'previous_sunrise_jd' => $jdPreviousSunrise,
                'sunset_jd' => $jdSunset,
                'next_sunrise_jd' => $jdNextSunrise,
                'moon_sun_elongation_at_sunset_degrees' => $moonSunAngleAtSunset,
                'moon_illumination_at_sunset_fraction' => $moonIlluminationAtSunset,
                'moon_illumination_at_sunset_percent' => $moonIlluminationAtSunset * 100.0,
                'tithi_start_jd' => $tithiStartJd,
                'tithi_end_jd' => $tithiEndJd,
                'prev_tithi_end_jd' => $prevTithiEndJd,
                'tithi_index_abs' => $tithiNum,
                'tithi_index_phase' => $tithiNum > 15 ? $tithiNum - 15 : $tithiNum,
                'paksha' => (string) ($tithi['paksha'] ?? ''),
                'sunrise_iso' => AstroCore::formatDateTime($sunrise),
                'previous_sunrise_iso' => AstroCore::formatDateTime($previousSunrise),
                'sunset_iso' => AstroCore::formatDateTime($sunset),
                'next_sunrise_iso' => AstroCore::formatDateTime($nextSunrise),
                'sankranti_rashi' => $sankrantiRashi,
            ],
            'Bhadra' => $includeExtended ? $this->findBhadraPeriods($jdSunrise, $jdNextSunrise, $tithiNum, (string) $tithi['paksha']) : [],
            'Tithi_Windows' => array_map(fn (array $interval): array => $this->formatTransitionWindow($interval, 'tithi', $tz), $this->intervalTracker->collectTithiIntervals($jdSunrise, $jdNextSunrise)),
            'Nakshatra_Windows' => array_map(fn (array $interval): array => $this->formatTransitionWindow($interval, 'nakshatra', $tz), $this->intervalTracker->collectNakshatraIntervals($jdSunrise, $jdNextSunrise)),
            'Nakshatra_Padas' => array_map(fn (array $interval): array => $this->formatTransitionWindow($interval, 'pada', $tz), $this->intervalTracker->collectNakshatraPadaIntervals($jdSunrise, $jdNextSunrise)),
            'Yoga_Windows' => array_map(fn (array $interval): array => $this->formatTransitionWindow($interval, 'yoga', $tz), $this->intervalTracker->collectYogaIntervals($jdSunrise, $jdNextSunrise)),
            'Karana_Windows' => array_map(fn (array $interval): array => $this->formatTransitionWindow($interval, 'karana', $tz), $this->intervalTracker->collectKaranaIntervals($jdSunrise, $jdNextSunrise)),
        ];
    }

    private function buildDayNightMeasures(CarbonImmutable $sunrise, CarbonImmutable $sunset, CarbonImmutable $nextSunrise): array
    {
        $daySeconds = $sunset->getTimestamp() - $sunrise->getTimestamp();
        $nightSeconds = $nextSunrise->getTimestamp() - $sunset->getTimestamp();

        return [
            'dinamana' => $this->buildTraditionalDurationPayload($daySeconds),
            'ratrimana' => $this->buildTraditionalDurationPayload($nightSeconds),
        ];
    }

    private function buildTithiObservanceAnalysis(
        KalaNirnayaEngine $kalaEngine,
        int $tithiNumber,
        float $tithiStartJd,
        float $tithiEndJd,
        float $sunriseJd,
        float $nextSunriseJd,
        float $prevTithiEndJd,
        string $tz
    ): array {
        $analysis = $kalaEngine->determineViddhaTithi(
            $tithiNumber,
            $tithiStartJd,
            $tithiEndJd,
            $sunriseJd,
            $nextSunriseJd,
            $prevTithiEndJd
        );
        $phaseTithiNumber = (($tithiNumber - 1) % 15) + 1;
        $phaseTithiName = $phaseTithiNumber === 15 ? 'Purnima/Amavasya' : KalaNirnayaEngine::TITHI_NAMES[$phaseTithiNumber - 1];
        $tithiAtSunriseToday = $tithiStartJd <= $sunriseJd && $tithiEndJd > $sunriseJd;
        $tithiAtSunriseTomorrow = $tithiStartJd <= $nextSunriseJd && $tithiEndJd > $nextSunriseJd;
        $prevTithiPiercesTodaySunrise = $prevTithiEndJd > $sunriseJd;

        return [
            ...$analysis,
            'analysis_scope' => 'structural_sunrise_tithi_state',
            'festival_specific_override_required' => true,
            'tithi_name' => Localization::translate('String', (string) ($analysis['tithi_name'] ?? '')),
            'status_label' => Localization::translate('String', (string) ($analysis['status'] ?? '')),
            'phase_tithi_number' => $phaseTithiNumber,
            'phase_tithi_name' => Localization::translate('String', $phaseTithiName),
            'full_tithi_name' => Tithi::from($tithiNumber)->getName(),
            'tithi_start_iso' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($tithiStartJd, $tz)),
            'tithi_end_iso' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($tithiEndJd, $tz)),
            'previous_tithi_end_jd' => $prevTithiEndJd,
            'previous_tithi_end_iso' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($prevTithiEndJd, $tz)),
            'observance_day_iso' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic((float) $analysis['observance_day_jd'], $tz)),
            'tithi_at_sunrise_today' => $tithiAtSunriseToday,
            'tithi_at_sunrise_tomorrow' => $tithiAtSunriseTomorrow,
            'previous_tithi_pierces_today_sunrise' => $prevTithiPiercesTodaySunrise,
            'is_tithi_vriddhi' => $tithiAtSunriseToday && $tithiAtSunriseTomorrow,
            'is_tithi_kshaya' => !$tithiAtSunriseToday && !$tithiAtSunriseTomorrow,
        ];
    }

    private function buildNakshatraTyajyaPayload(array $varjyam): array
    {
        return [
            ...$varjyam,
            'rule_system' => 'nakshatra_thyajyam_equals_varjyam',
            'is_equivalent_to_varjyam' => true,
            'source' => Localization::translate('Source', 'Varjyam (Tyajyam) window from Panchang day calculation'),
        ];
    }

    private function supportedVrataParanaFamilies(): array
    {
        return [
            [
                'family_key' => 'sankashti_chaturthi',
                'family_name' => Localization::translate('Festival', 'Sankashti Chaturthi'),
                'phase_tithi_number' => 4,
                'paksha' => 'Krishna',
                'paksha_label' => Paksha::Krishna->getName(),
                'observance_rule' => 'moonrise_with_krishna_chaturthi',
                'parana_policy' => 'break_after_moonrise',
            ],
            [
                'family_key' => 'pradosha_vrata',
                'family_name' => Localization::translate('Festival', 'Pradosh Vrat'),
                'phase_tithi_number' => 13,
                'paksha' => 'Any',
                'paksha_label' => Localization::translate('String', 'Any'),
                'observance_rule' => 'trayodashi_during_pradosha',
                'parana_policy' => 'break_after_pradosha_puja',
            ],
            [
                'family_key' => 'masik_shivaratri',
                'family_name' => Localization::translate('Festival', 'Masik Shivaratri'),
                'phase_tithi_number' => 14,
                'paksha' => 'Krishna',
                'paksha_label' => Paksha::Krishna->getName(),
                'observance_rule' => 'krishna_chaturdashi_during_nishita',
                'parana_policy' => 'break_after_next_sunrise_before_tithi_end',
            ],
            [
                'family_key' => 'skanda_shashthi',
                'family_name' => Localization::translate('Festival', 'Skanda Sashti'),
                'phase_tithi_number' => 6,
                'paksha' => 'Shukla',
                'paksha_label' => Paksha::Shukla->getName(),
                'observance_rule' => 'shashthi_at_sunrise_or_panchami_shashthi_conjugation_before_sunset',
                'parana_policy' => 'break_after_sunset_puja',
            ],
        ];
    }

    private function buildVrataParanaProfile(
        int $tithiNumber,
        string $paksha,
        float $tithiStartJd,
        float $tithiEndJd,
        float $sunriseJd,
        float $sunsetJd,
        float $nextSunriseJd,
        ?float $moonriseJd,
        array $pradoshaKaal,
        array $nishitaMuhurta,
        string $tz
    ): ?array {
        $phaseTithiNumber = (($tithiNumber - 1) % 15) + 1;
        $paksha = $paksha === '' ? 'Any' : $paksha;

        $pradoshaStartJd = isset($pradoshaKaal['pradosha_start_iso']) ? $this->toJulianDayFromIso((string) $pradoshaKaal['pradosha_start_iso'], $tz) : null;
        $pradoshaEndJd = isset($pradoshaKaal['pradosha_end_iso']) ? $this->toJulianDayFromIso((string) $pradoshaKaal['pradosha_end_iso'], $tz) : null;
        $nishitaStartJd = isset($nishitaMuhurta['nishita_start_iso']) ? $this->toJulianDayFromIso((string) $nishitaMuhurta['nishita_start_iso'], $tz) : null;
        $nishitaEndJd = isset($nishitaMuhurta['nishita_end_iso']) ? $this->toJulianDayFromIso((string) $nishitaMuhurta['nishita_end_iso'], $tz) : null;

        foreach ($this->supportedVrataParanaFamilies() as $family) {
            if ($family['phase_tithi_number'] !== $phaseTithiNumber) {
                continue;
            }

            if ($family['paksha'] !== 'Any' && $family['paksha'] !== $paksha) {
                continue;
            }

            $profile = [
                ...$family,
                'tithi_name' => Tithi::from($tithiNumber)->getName(),
                'tithi_start_iso' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($tithiStartJd, $tz)),
                'tithi_end_iso' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($tithiEndJd, $tz)),
                'sunrise_iso' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($sunriseJd, $tz)),
                'sunset_iso' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($sunsetJd, $tz)),
                'next_sunrise_iso' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($nextSunriseJd, $tz)),
            ];

            if ($family['family_key'] === 'sankashti_chaturthi') {
                if ($moonriseJd === null || !($tithiStartJd <= $moonriseJd && $tithiEndJd > $moonriseJd)) {
                    continue;
                }

                $profile['observance_checkpoint_iso'] = AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($moonriseJd, $tz));
                $profile['parana_after_jd'] = $moonriseJd;
                $profile['parana_after_iso'] = AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($moonriseJd, $tz));
                $profile['parana_basis'] = 'moonrise';
                $profile['parana_basis_label'] = Localization::translate('String', 'moonrise');

                return $profile;
            }

            if ($family['family_key'] === 'pradosha_vrata') {
                if ($pradoshaStartJd === null || $pradoshaEndJd === null) {
                    continue;
                }

                $overlapStartJd = max($tithiStartJd, $pradoshaStartJd);
                $overlapEndJd = min($tithiEndJd, $pradoshaEndJd);
                if ($overlapEndJd <= $overlapStartJd) {
                    continue;
                }

                $profile['observance_window'] = [
                    'start_iso' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($overlapStartJd, $tz)),
                    'end_iso' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($overlapEndJd, $tz)),
                ];
                $profile['parana_after_jd'] = $overlapEndJd;
                $profile['parana_after_iso'] = AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($overlapEndJd, $tz));
                $profile['parana_basis'] = 'pradosha_end';
                $profile['parana_basis_label'] = Localization::translate('String', 'pradosha_end');

                return $profile;
            }

            if ($family['family_key'] === 'masik_shivaratri') {
                if ($nishitaStartJd === null || $nishitaEndJd === null) {
                    continue;
                }

                $overlapStartJd = max($tithiStartJd, $nishitaStartJd);
                $overlapEndJd = min($tithiEndJd, $nishitaEndJd);
                if ($overlapEndJd <= $overlapStartJd) {
                    continue;
                }

                $profile['observance_window'] = [
                    'start_iso' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($overlapStartJd, $tz)),
                    'end_iso' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($overlapEndJd, $tz)),
                ];
                $profile['parana_after_jd'] = $nextSunriseJd;
                $profile['parana_after_iso'] = AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($nextSunriseJd, $tz));
                $profile['parana_window_end_jd'] = $tithiEndJd > $nextSunriseJd ? $tithiEndJd : null;
                $profile['parana_window_end_iso'] = $tithiEndJd > $nextSunriseJd
                    ? AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($tithiEndJd, $tz))
                    : null;
                $profile['parana_basis'] = 'next_sunrise_before_tithi_end';
                $profile['parana_basis_label'] = Localization::translate('String', 'next_sunrise_before_tithi_end');

                return $profile;
            }

            if ($family['family_key'] === 'skanda_shashthi') {
                $tithiAtSunrise = $tithiStartJd <= $sunriseJd && $tithiEndJd > $sunriseJd;
                $tithiBeginsBeforeSunset = $tithiStartJd > $sunriseJd && $tithiStartJd < $sunsetJd;
                if (!$tithiAtSunrise && !$tithiBeginsBeforeSunset) {
                    continue;
                }

                $profile['observance_checkpoint_iso'] = AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($tithiAtSunrise ? $sunriseJd : $tithiStartJd, $tz));
                $profile['parana_after_jd'] = $sunsetJd;
                $profile['parana_after_iso'] = AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($sunsetJd, $tz));
                $profile['parana_basis'] = 'sunset_after_shashthi_observance';
                $profile['parana_basis_label'] = Localization::translate('String', 'sunset');

                return $profile;
            }

        }

        return null;
    }

    private function buildTraditionalDurationPayload(float $seconds): array
    {
        $minutes = $seconds / 60.0;
        $ghati = (int) floor($minutes / KalaNirnayaEngine::GHATI_IN_MINUTES);
        $remainingMinutes = $minutes - ($ghati * KalaNirnayaEngine::GHATI_IN_MINUTES);
        $pala = $remainingMinutes * (60.0 / KalaNirnayaEngine::PALA_IN_SECONDS);

        return [
            'seconds' => $seconds,
            'minutes' => $minutes,
            'hours' => $seconds / 3600.0,
            'ghati' => $ghati,
            'pala' => $pala,
        ];
    }

    /**
     * Classify the Moon into the common 8 visual phase buckets.
     *
     * This is distinct from Tithi: it is based on the instantaneous Sun-Moon
     * elongation and resulting illuminated fraction.
     *
     * @return array<string, float|int|string>
     */
    private function buildMoonPhase(float $sunLongitude, float $moonLongitude): array
    {
        $synodicMonthDays = 29.530588861;
        $phaseAngle = fmod(($moonLongitude - $sunLongitude) + 360.0, 360.0);
        $illuminationFraction = (1.0 - cos(deg2rad($phaseAngle))) / 2.0;
        $illuminationPercent = $illuminationFraction * 100.0;
        $synodicAgeDays = ($phaseAngle / 360.0) * $synodicMonthDays;

        $phaseKey = 'new_moon';
        $visibilityKey = 'invisible_except_eclipse';
        $illuminationBand = '0%';

        if ($phaseAngle >= 22.5 && $phaseAngle < 67.5) {
            $phaseKey = 'waxing_crescent';
            $visibilityKey = 'late_morning_to_post_dusk';
            $illuminationBand = '1%-49%';
        } elseif ($phaseAngle >= 67.5 && $phaseAngle < 112.5) {
            $phaseKey = 'first_quarter';
            $visibilityKey = 'afternoon_and_early_night';
            $illuminationBand = '50%';
        } elseif ($phaseAngle >= 112.5 && $phaseAngle < 157.5) {
            $phaseKey = 'waxing_gibbous';
            $visibilityKey = 'late_afternoon_and_most_of_night';
            $illuminationBand = '51%-99%';
        } elseif ($phaseAngle >= 157.5 && $phaseAngle < 202.5) {
            $phaseKey = 'full_moon';
            $visibilityKey = 'sunset_to_sunrise';
            $illuminationBand = '100%';
        } elseif ($phaseAngle >= 202.5 && $phaseAngle < 247.5) {
            $phaseKey = 'waning_gibbous';
            $visibilityKey = 'most_of_night_and_early_morning';
            $illuminationBand = '99%-51%';
        } elseif ($phaseAngle >= 247.5 && $phaseAngle < 292.5) {
            $phaseKey = 'last_quarter';
            $visibilityKey = 'late_night_and_morning';
            $illuminationBand = '50%';
        } elseif ($phaseAngle >= 292.5 && $phaseAngle < 337.5) {
            $phaseKey = 'waning_crescent';
            $visibilityKey = 'pre_dawn_to_early_afternoon';
            $illuminationBand = '49%-1%';
        }

        return [
            'key' => $phaseKey,
            'name' => Localization::translate('MoonPhase', $phaseKey),
            'visibility_key' => $visibilityKey,
            'visibility' => Localization::translate('MoonPhaseVisibility', $visibilityKey),
            'illumination_band' => $illuminationBand,
            'illumination_fraction' => $illuminationFraction,
            'illumination_percent' => $illuminationPercent,
            'phase_angle_degrees' => $phaseAngle,
            'synodic_age_days' => $synodicAgeDays,
        ];
    }

    private function toJulianDayFromIso(string $dateTime, string $tz): float
    {
        foreach (['d/m/Y h:i:s A', 'd/m/Y H:i:s', 'Y-m-d H:i:s'] as $format) {
            try {
                $parsed = CarbonImmutable::createFromFormat($format, $dateTime, $tz);
                if ($parsed instanceof CarbonImmutable) {
                    return $this->toJulianDayFromCarbon($parsed, $tz);
                }
            } catch (Throwable) {
                // Try the next supported display format.
            }
        }

        return $this->toJulianDayFromCarbon(CarbonImmutable::parse($dateTime, $tz), $tz);
    }

    private function rememberBodyLongitude(float $jd, int $planet, int $flags, float $value): float
    {
        if (count($this->bodyLongitudeCache) >= self::BODY_LONGITUDE_CACHE_MAX) {
            $this->bodyLongitudeCache = array_slice(
                $this->bodyLongitudeCache,
                -self::BODY_LONGITUDE_CACHE_TRIM_TO,
                null,
                true
            );
        }

        $cacheKey = sprintf('%.17g|%d|%d', $jd, $planet, $flags);
        $this->bodyLongitudeCache[$cacheKey] = $value;

        return $value;
    }

}
