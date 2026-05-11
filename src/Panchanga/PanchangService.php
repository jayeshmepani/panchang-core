<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Panchanga;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Astronomy\AstronomyService;
use JayeshMepani\PanchangCore\Astronomy\Math\IntervalTracker;
use JayeshMepani\PanchangCore\Astronomy\Math\TransitEngine;
use JayeshMepani\PanchangCore\Astronomy\SunService;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Core\Enums\CalendarType;
use JayeshMepani\PanchangCore\Core\Enums\Rasi;
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
use JayeshMepani\PanchangCore\Panchanga\Vrata\EkadashiParanaCalculator;
use JayeshMepani\PanchangCore\Panchanga\Yogas\SpecialYogaCalculator;
use SwissEph\FFI\SwissEphFFI;

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

    /** @var array<int, string> */
    private const array YEARLY_SINGLE_OBSERVANCE_FESTIVALS = [
        'Ganga Dussehra',
    ];

    private static string $ephePath = '';

    private array $monthCache = [];

    private readonly VaasaCalculator $vaasaCalculator;

    private readonly ShoolaCalculator $shoolaCalculator;

    private readonly SpecialYogaCalculator $specialYogaCalculator;

    private readonly IntervalTracker $intervalTracker;

    private readonly PanchakCalculator $panchakCalculator;

    private readonly BhadraCalculator $bhadraCalculator;

    private readonly EkadashiParanaCalculator $ekadashiParanaCalculator;

    public function __construct(
        private readonly SwissEphFFI $sweph,
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
            : new TransitEngine($this->sweph);
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

        $envEphePath = ($_ENV['PANCHANG_EPHE_PATH'] ?? false);
        $envEphePath = is_string($envEphePath) ? $envEphePath : '';

        $configEphePath = function_exists('config') ? config('panchang.ephe_path', $envEphePath) : $envEphePath;
        $ephePath = self::$ephePath !== '' ? self::$ephePath : (is_string($configEphePath) ? $configEphePath : '');
        if ($ephePath !== '' && file_exists($ephePath)) {
            $this->sweph->swe_set_ephe_path($ephePath);
        }

        // Enforce Lahiri globally for all Panchang calculations, including lightweight snapshots.
        $this->sweph->swe_set_sid_mode(SwissEphFFI::SE_SIDM_LAHIRI, 0.0, 0.0);
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

        $ayanamsaBirth = $birthAt;
        $ayanamsaJd = $this->astronomy->toJulianDayUtc($ayanamsaBirth);
        $ayanamsaDeg = $this->astronomy->getAyanamsa($ayanamsaJd);

        $tithi = $this->panchanga->calculateTithi($sunLon, $moonLon);
        $yoga = $this->panchanga->calculateYoga($sunLon, $moonLon);
        [$karanaName, $karanaIdx] = $this->panchanga->getKarana($sunLon, $moonLon);
        [$nakName, $nakPada, $nakLord] = $this->panchanga->getNakshatraInfo($moonLon);
        $nakIdx = (int) floor(($moonLon * 60.0) / 800.0);
        $vara = $this->panchanga->calculateVara($birthAt, $this->sunService);

        $isth = $this->calculateIshtkaal($relSunrise, $birthAt, $tz);

        $ascLon = $this->astronomy->getAscendant($birthAt);
        $ascSign = AstroCore::getSign($ascLon);
        $moonSign = AstroCore::getSign($moonLon);
        $sunSign = AstroCore::getSign($sunLon);

        $panchaka = $this->panchanga->calculatePanchakaRahita(
            (int) $tithi['index'],
            (int) $vara['index'] + 1,
            $nakIdx + 1,
            $ascSign + 1
        );

        $nextDay = $date->addDay();
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

        $twilight = $this->sunService->getTwilightTimes($birthBase);
        $solarTransits = $this->sunService->getSolarTransits($birthBase);
        $solarTransitsNext = $this->sunService->getSolarTransits($nextBirth);

        $apparentSolarDaySeconds = $solarTransitsNext['solar_noon']->getTimestamp() - $solarTransits['solar_noon']->getTimestamp();
        $civilDayStart = $date->setTime(0, 0, 0);
        $civilDayEnd = $civilDayStart->addDay();
        $civilDaySeconds = $civilDayEnd->getTimestamp() - $civilDayStart->getTimestamp();

        $hora = $this->muhurta->calculateHora($relSunrise, $sunset, $nextSunrise, $calculationAt, $vara['index']);
        $chogadiya = $this->muhurta->calculateChogadiya($relSunrise, $sunset, $nextSunrise, $calculationAt, $vara['index']);
        $horaTable = $this->muhurta->calculateHoraTable($relSunrise, $sunset, $nextSunrise, $vara['index']);
        $chogadiyaTable = $this->muhurta->calculateChogadiyaTable($relSunrise, $sunset, $nextSunrise, $vara['index']);
        $muhurtaTable = $this->muhurta->calculateMuhurtaTable($relSunrise, $sunset, $nextSunrise);
        $badTimes = $this->muhurta->calculateBadTimes($relSunrise, $sunset, $vara['index']);

        $abhijit = $this->muhurta->calculateAbhijitMuhurta($relSunrise, $sunset);

        // New calculations: Prahara, Brahma Muhurta, Dur Muhurta
        $praharaTable = $this->muhurta->calculatePrahara($relSunrise, $sunset, $nextSunrise);
        $brahmaMuhurta = $this->muhurta->calculateBrahmaMuhurta($nextSunrise);
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
            $this->sweph
        );

        $jdSunrise = $this->toJulianDayFromCarbon($relSunrise, $tz);
        $jdSunset = $this->toJulianDayFromCarbon($sunset, $tz);
        $jdNextSunrise = $this->toJulianDayFromCarbon($nextSunrise, $tz);

        $tithiNum = (int) $tithi['index'];
        $tithiStartAngle = ($tithiNum - 1) * 12.0;
        $tithiEndAngle = $tithiNum * 12.0;
        $tithiStartJd = $this->findAngleCrossing($jdSunrise, $tithiStartAngle, -1, fn (float $jd): float => $this->getMoonSunAngle($jd));
        $tithiEndJd = $this->findAngleCrossing($jdSunrise, $tithiEndAngle, 1, fn (float $jd): float => $this->getMoonSunAngle($jd));

        $nakEndAngle = ($nakIdx + 1) * (360.0 / 27.0);
        $nakEndJd = $this->findAngleCrossing($jdSunrise, $nakEndAngle, 1, fn (float $jd): float => $this->getMoonLongitude($jd));
        $nakStartAngle = $nakIdx * (360.0 / 27.0);
        $nakStartJd = $this->findAngleCrossing($jdSunrise, $nakStartAngle, -1, fn (float $jd): float => $this->getMoonLongitude($jd));

        // Varjyam (Tyajyam) can occur once or twice between sunrise and next sunrise.
        $varjyamWindows = $this->calculateVarjyamWindows($relSunrise, $sunset, $nextSunrise, $jdSunrise, $jdNextSunrise, $nakIdx, $nakStartJd, $nakEndJd);
        $varjyam = $this->buildVarjyamPayload($varjyamWindows);
        $amritaKaal = $this->muhurta->calculateAmritaKaal($relSunrise, $varjyam);

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
            $this->sweph
        );

        $yogaIdx = (int) $yoga['index'];
        $yogaEndAngle = $yogaIdx * (360.0 / 27.0);
        $yogaEndJd = $this->findAngleCrossing($jdSunrise, $yogaEndAngle, 1, fn (float $jd): float => $this->getSunMoonSum($jd));

        $karanaEndAngle = $karanaIdx * 6.0;
        $karanaEndJd = $this->findAngleCrossing($jdSunrise, $karanaEndAngle, 1, fn (float $jd): float => $this->getMoonSunAngle($jd));

        $kalaEngine = new KalaNirnayaEngine($lat, $lon);
        $hinduMonth = $this->getTrueHinduMonth($jdSunrise);

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
                'sunset_jd' => $jdSunset,
                'next_sunrise_jd' => $jdNextSunrise,
                'tithi_start_jd' => $tithiStartJd,
                'tithi_end_jd' => $tithiEndJd,
                'prev_tithi_end_jd' => $tithiStartJd,
                'tithi_index_abs' => $tithiNum,
                'tithi_index_phase' => $tithiNum > 15 ? $tithiNum - 15 : $tithiNum,
                'paksha' => (string) ($tithi['paksha'] ?? ''),
                'sunrise_iso' => AstroCore::formatDateTime($relSunrise),
                'sunset_iso' => AstroCore::formatDateTime($sunset),
                'next_sunrise_iso' => AstroCore::formatDateTime($nextSunrise),
                'sankranti_rashi' => $sankrantiRashi,
            ],
        ];

        $tomorrowSnapshot = $this->getFestivalSnapshot($nextDay, $lat, $lon, $tz, $elevation);
        $festivals = $this->festivalService->resolveFestivalsForDate($date, $todaySnapshot, $tomorrowSnapshot);
        $festivals = $this->retainFestivalsForDate($festivals, $date->toDateString());

        $dailyObservances = $this->festivalService->getDailyObservances($todaySnapshot);
        $specialYogas = $this->calculateSpecialYogas($date, $jdSunrise, $jdNextSunrise, $tithiNum, (int) $vara['index'], $tz);
        $anandadiYoga = $this->calculateAnandadiYoga($jdSunrise, $jdNextSunrise, (int) $vara['index'], $tz);
        $amritadiYoga = $this->calculateAmritadiYoga($jdSunrise, $jdNextSunrise, (int) $vara['index'], $tz);
        $panchak = $this->calculatePanchak($jdSunrise, $jdNextSunrise, $tz);
        $maitreyaYoga = $this->calculateMaitreyaYoga($jdSunrise, $jdNextSunrise, (int) $vara['index'], $lagnaTable, $tz);
        $gajachchhayaYoga = $this->calculateGajachchhayaYoga($jdSunrise, $jdNextSunrise, $hinduMonth, $tz);
        $nakshatraShool = $this->calculateNakshatraShool($jdSunrise, $jdNextSunrise, $tz);
        $dishaShool = $this->calculateDishaShool((int) $vara['index']);
        $rahuVaasa = $this->calculateRahuVaasa((int) $vara['index']);
        $chandraVaasa = $this->calculateChandraVaasa($jdSunrise, $jdNextSunrise, $tz);
        $shivaVaasa = $this->calculateShivaVaasa($tithiNum, $tithiEndJd, $tz);
        $agniVaasa = $this->calculateAgniVaasa($tithiNum, (int) $vara['index'], $tithiEndJd, $tz);
        $yoginiVaasa = $this->calculateYoginiVaasa($tithiNum);
        $transitionSignals = $this->buildTransitionSignals(
            $jdSunrise,
            $jdNextSunrise,
            $sunLon,
            $moonLon,
            $tithiNum,
            $nakIdx,
            $yogaIdx,
            $karanaIdx,
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
            $lon
        );

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
            'Vara' => $vara,
            'Nakshatra' => [
                'name' => $nakName,
                'pada' => $nakPada,
                'lord' => $nakLord,
            ],
            'Yoga' => $yoga,
            'Karana' => ['name' => $karanaName, 'index' => $karanaIdx],
            'Is_Vishti_Karana' => $this->panchanga->isVishtiKarana($sunLon, $moonLon),
            'Sunrise' => AstroCore::formatTime($relSunrise),
            'Sunset' => AstroCore::formatTime($sunset),
            'Ishtkaal' => $isth,
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
                'solar_noon' => AstroCore::formatDateTime($solarTransits['solar_noon']),
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
                'Vara' => $vara,
                'Nakshatra' => [
                    'name' => $nakName,
                    'pada' => $nakPada,
                    'lord' => $nakLord,
                ],
                'Yoga' => $yoga,
                'Karana' => ['name' => $karanaName, 'index' => $karanaIdx],
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
            'Chart_Auxiliary' => [
                'Sun_Sign' => Rasi::from($sunSign)->getName(),
                'Moon_Sign' => Rasi::from($moonSign)->getName(),
            ],
            'Festivals' => $festivals,
            'Daily_Observances' => $dailyObservances,
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
            'Transitions' => $transitionSignals,
            'Panchaka_Rahita' => $panchaka,
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
            'Karmakala_Windows' => $karmakalaWindows,
            'Varjyam' => $varjyam,
            'Amrita_Kaal' => $amritaKaal,
            'Pradosha_Kaal' => $pradoshaKaal,
            'Lagna' => $lagna,
            'Lagna_Full_Day' => $lagnaTable,

            'Bhadra' => $this->findBhadraPeriods($jdSunrise, $jdNextSunrise, $tithiNum, (string) ($tithi['paksha'] ?? '')),

            'Dharma_Sindhu' => array_filter([
                'Punya_Kaal' => $punyaKaal,
                'Ekadashi_Observance' => $ekadashiObservance,
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
        CalendarType|string $calendarType = CalendarType::Amanta
    ): array {
        // Normalize calendar_type from string to enum
        if (is_string($calendarType)) {
            $calendarType = match (strtolower($calendarType)) {
                'purnimanta', 'purnimant' => CalendarType::Purnimanta,
                default => CalendarType::Amanta,
            };
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
        $jdSunset = $this->toJulianDayFromCarbon($sunset, $tz);
        $jdNextSunrise = $this->toJulianDayFromCarbon($nextSunrise, $tz);
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

        $lagnaTable = $this->muhurta->calculateLagnaTable(
            $sunrise,
            $sunset,
            $nextSunrise,
            $sunLon,
            $ayanamsaDeg,
            $lat,
            $lon,
            $this->sweph
        );
        $specialYogas = $this->calculateSpecialYogas($date, $jdSunrise, $jdNextSunrise, $tithiNum, (int) $vara['index'], $tz);
        $anandadiYoga = $this->calculateAnandadiYoga($jdSunrise, $jdNextSunrise, (int) $vara['index'], $tz);
        $amritadiYoga = $this->calculateAmritadiYoga($jdSunrise, $jdNextSunrise, (int) $vara['index'], $tz);
        $panchak = $this->calculatePanchak($jdSunrise, $jdNextSunrise, $tz);
        $maitreyaYoga = $this->calculateMaitreyaYoga($jdSunrise, $jdNextSunrise, (int) $vara['index'], $lagnaTable, $tz);
        $gajachchhayaYoga = $this->calculateGajachchhayaYoga($jdSunrise, $jdNextSunrise, $hinduMonth, $tz);
        $nakshatraShool = $this->calculateNakshatraShool($jdSunrise, $jdNextSunrise, $tz);
        $dishaShool = $this->calculateDishaShool((int) $vara['index']);
        $rahuVaasa = $this->calculateRahuVaasa((int) $vara['index']);
        $chandraVaasa = $this->calculateChandraVaasa($jdSunrise, $jdNextSunrise, $tz);
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
            $lon
        );

        return [
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
                'sunset_jd' => $jdSunset,
                'next_sunrise_jd' => $jdNextSunrise,
                'tithi_start_jd' => $tithiStartJd,
                'tithi_end_jd' => $tithiEndJd,
                'prev_tithi_end_jd' => $prevTithiEndJd,
                'tithi_index_abs' => $tithiNum,
                'tithi_index_phase' => $tithiNum > 15 ? $tithiNum - 15 : $tithiNum,
                'paksha' => (string) ($tithi['paksha'] ?? ''),
                'sunrise_iso' => AstroCore::formatDateTime($sunrise),
                'sunset_iso' => AstroCore::formatDateTime($sunset),
                'next_sunrise_iso' => AstroCore::formatDateTime($nextSunrise),
                'sankranti_rashi' => $sankrantiRashi,
            ],
            'Bhadra' => $this->findBhadraPeriods($jdSunrise, $jdNextSunrise, $tithiNum, (string) $tithi['paksha']),
        ];
    }

}
