<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Panchanga;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Astronomy\AstronomyService;
use JayeshMepani\PanchangCore\Astronomy\SunService;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Core\Constants\AstrologyConstants;
use RuntimeException;
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
    private static string $ephePath = '';
    private static string $ayanamsa = 'LAHIRI';

    public function __construct(
        private SwissEphFFI $sweph,
        private SunService $sunService,
        private AstronomyService $astronomy,
        private PanchangaEngine $panchanga,
        private MuhurtaService $muhurta,
    ) {
        $ephePath = self::$ephePath ?: getenv('PANCHANG_EPHE_PATH') ?: '';
        if (is_string($ephePath) && $ephePath !== '' && file_exists($ephePath)) {
            $this->sweph->swe_set_ephe_path($ephePath);
        }

        $this->setAyanamsa(self::$ayanamsa ?: getenv('PANCHANG_AYANAMSA') ?: 'LAHIRI');
    }

    /**
     * Configure service (optional, for standalone usage).
     *
     * @param string $ephePath Ephemeris path (empty for default)
     * @param string $ayanamsaMode Ayanamsa mode ('LAHIRI', 'RAMAN', 'KRISHNAMURTI')
     */
    public static function configure(string $ephePath = '', string $ayanamsaMode = 'LAHIRI'): void
    {
        self::$ephePath = $ephePath;
        self::$ayanamsa = $ayanamsaMode;
    }

    public function getDayDetails(
        CarbonImmutable $date,
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0,
        ?CarbonImmutable $ayanamsaAt = null,
        array $options = []
    ): array
    {
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

        $ayanamsaAt = $ayanamsaAt ?? $sunrise;
        $birthAt = $birthBase;
        $birthAt['hour'] = (int) $ayanamsaAt->format('H');
        $birthAt['minute'] = (int) $ayanamsaAt->format('i');
        $birthAt['second'] = (int) $ayanamsaAt->format('s');

        $relSunrise = $sunrise;
        if ($ayanamsaAt->lessThan($sunrise)) {
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

        $transitPlanets = $this->astronomy->getPlanets($birthAt);
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

        $hora = $this->muhurta->calculateHora($relSunrise, $sunset, $nextSunrise, $ayanamsaAt, $vara['index']);
        $chogadiya = $this->muhurta->calculateChogadiya($relSunrise, $sunset, $nextSunrise, $ayanamsaAt, $vara['index']);
        $horaTable = $this->muhurta->calculateHoraTable($relSunrise, $sunset, $nextSunrise, $vara['index']);
        $chogadiyaTable = $this->muhurta->calculateChogadiyaTable($relSunrise, $sunset, $nextSunrise, $vara['index']);
        $muhurtaTable = $this->muhurta->calculateMuhurtaTable($relSunrise, $sunset, $nextSunrise);
        $badTimes = $this->muhurta->calculateBadTimes($relSunrise, $sunset, $vara['index']);

        $abhijit = $this->muhurta->calculateAbhijitMuhurta($relSunrise, $sunset);

        // New calculations: Prahara, Brahma Muhurta, Dur Muhurta
        $praharaTable = $this->muhurta->calculatePrahara($relSunrise, $sunset, $nextSunrise);
        $brahmaMuhurta = $this->muhurta->calculateBrahmaMuhurta($relSunrise);
        $durMuhurtaTable = $this->muhurta->calculateDurMuhurta($relSunrise, $sunset, $nextSunrise);

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
        $tithiStartJd = $this->findAngleCrossing($jdSunrise, $tithiStartAngle, -1, fn (float $jd) => $this->getMoonSunAngle($jd));
        $tithiEndJd = $this->findAngleCrossing($jdSunrise, $tithiEndAngle, 1, fn (float $jd) => $this->getMoonSunAngle($jd));

        $nakEndAngle = ($nakIdx + 1) * (360.0 / 27.0);
        $nakEndJd = $this->findAngleCrossing($jdSunrise, $nakEndAngle, 1, fn (float $jd) => $this->getMoonLongitude($jd));
        $nakStartAngle = $nakIdx * (360.0 / 27.0);
        $nakStartJd = $this->findAngleCrossing($jdSunrise, $nakStartAngle, -1, fn (float $jd) => $this->getMoonLongitude($jd));

        // Varjyam calculation (depends on $nakEndJd)
        $varjyam = $this->muhurta->calculateVarjyam($relSunrise, $sunset, $nextSunrise, $nakIdx, $nakStartJd, $nakEndJd);
        $amritaKaal = $this->muhurta->calculateAmritaKaal($relSunrise, $varjyam);

        // Pradosha Kaal (only on Trayodashi)
        $pradoshaKaal = $this->muhurta->calculatePradoshaKaal($sunset, $tithiNum);

        // Lagna calculation
        $lagna = $this->muhurta->calculateLagna(
            $ayanamsaAt,
            $relSunrise,
            $sunMoon['Sun'],
            $ayanamsaDeg,
            $lat,
            $lon,
            $this->sweph
        );

        $yogaIdx = (int) $yoga['index'];
        $yogaEndAngle = $yogaIdx * (360.0 / 27.0);
        $yogaEndJd = $this->findAngleCrossing($jdSunrise, $yogaEndAngle, 1, fn (float $jd) => $this->getSunMoonSum($jd));

        $karanaEndAngle = $karanaIdx * 6.0;
        $karanaEndJd = $this->findAngleCrossing($jdSunrise, $karanaEndAngle, 1, fn (float $jd) => $this->getMoonSunAngle($jd));

        $kalaEngine = new KalaNirnayaEngine($lat, $lon);
        $hinduMonth = $this->panchanga->getHinduMonth($sunLon, $moonLon, $tithi['paksha']);

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
        $sunLonNextSunrise = $this->getSunLongitude($jdNextSunrise);
        $currentSign = (int) floor($sunLon / 30.0);
        $nextSunriseSign = (int) floor($sunLonNextSunrise / 30.0);
        $punyaKaal = null;
        if ($currentSign !== $nextSunriseSign) {
            $nextSign = ($currentSign + 1) % 12;
            $targetAngle = $nextSign * 30.0;
            $sankrantiJd = $this->findAngleCrossing($jdSunrise, $targetAngle, 1, fn (float $jd) => $this->getSunLongitude($jd));
            if ($sankrantiJd >= $jdSunrise && $sankrantiJd < $jdNextSunrise) {
                $sankrantiName = $sankrantiNameMap[$nextSign];
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

        $zodiacSigns = AstrologyConstants::get('ZODIAC_SIGNS');
        if (!is_array($zodiacSigns) || !array_key_exists($sunSign, $zodiacSigns) || !array_key_exists($moonSign, $zodiacSigns)) {
            throw new RuntimeException('Missing zodiac sign mapping in astrology constants.');
        }

        $defaultConfig = function_exists('config') ? config('panchang.defaults', []) : [];
        if (!is_array($defaultConfig)) {
            $defaultConfig = [];
        }

        return [
            'Display_Settings' => [
                'measurement_system' => $options['measurement_system'] ?? $defaultConfig['measurement_system'] ?? 'indian_metric',
                'date_time_format' => $options['date_time_format'] ?? $defaultConfig['date_time_format'] ?? 'indian_12h',
                'time_notation' => $options['time_notation'] ?? $defaultConfig['time_notation'] ?? '12h',
                'coordinate_format' => $options['coordinate_format'] ?? $defaultConfig['coordinate_format'] ?? 'decimal',
                'angle_unit' => $options['angle_unit'] ?? $defaultConfig['angle_unit'] ?? 'degree',
                'duration_format' => $options['duration_format'] ?? $defaultConfig['duration_format'] ?? 'mixed',
                'number_precision' => (int) ($options['number_precision'] ?? $defaultConfig['number_precision'] ?? 9),
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
                // New classical elements units
                'Prahara.duration_seconds' => 'second',
                'Prahara.duration_hours' => 'hour',
                'Brahma_Muhurta.duration_minutes' => 'minute',
                'Brahma_Muhurta.duration_seconds' => 'second',
                'Dur_Muhurta.duration_seconds' => 'second',
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
            'Sunrise' => $relSunrise->format('H:i:s'),
            'Sunset' => $sunset->format('H:i:s'),
            'Ishtkaal' => $isth,
            'sun_sunrise_lon' => $sunLon,
            'moon_sunrise_lon' => $moonLon,
            'sunrise_hm' => [(int) $relSunrise->format('H'), (int) $relSunrise->format('i')],
            'sunrise_dt' => $relSunrise->toIso8601String(),
            'Ayanamsa' => function_exists('config') ? config('panchang.ayanamsa', self::$ayanamsa) : self::$ayanamsa,
            'Ayanamsa_Degree' => $ayanamsaDeg,
            'Ayanamsa_At' => $ayanamsaAt->toIso8601String(),
            'Ayanamsa_JD' => $ayanamsaJd,
            'Day_Types' => [
                'civil_day_start' => $civilDayStart->toIso8601String(),
                'civil_day_end' => $civilDayEnd->toIso8601String(),
                'civil_day_length_seconds' => $civilDaySeconds,
                'mean_solar_day_seconds' => 86400.0,
                'apparent_solar_day_seconds' => $apparentSolarDaySeconds,
                'solar_noon' => $solarTransits['solar_noon']->toIso8601String(),
                'solar_midnight' => $solarTransits['solar_midnight']->toIso8601String(),
            ],
            'Twilight' => [
                'civil' => [
                    'dawn' => $twilight['civil']['dawn']->toIso8601String(),
                    'dusk' => $twilight['civil']['dusk']->toIso8601String(),
                ],
                'nautical' => [
                    'dawn' => $twilight['nautical']['dawn']->toIso8601String(),
                    'dusk' => $twilight['nautical']['dusk']->toIso8601String(),
                ],
                'astronomical' => [
                    'dawn' => $twilight['astronomical']['dawn']->toIso8601String(),
                    'dusk' => $twilight['astronomical']['dusk']->toIso8601String(),
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
                'Sunrise' => $relSunrise->format('H:i:s'),
                'Sunset' => $sunset->format('H:i:s'),
                'Moonrise' => $moonrise->format('H:i:s'),
                'Moonset' => $moonset->format('H:i:s'),
                'Ishtkaal' => $isth,
                'sun_sunrise_lon' => $sunLon,
                'moon_sunrise_lon' => $moonLon,
                'sunrise_hm' => [(int) $relSunrise->format('H'), (int) $relSunrise->format('i')],
                'sunrise_dt' => $relSunrise->toIso8601String(),
                'tithi_start_dt' => $this->sunService->jdToCarbonPublic($tithiStartJd, $tz)->toIso8601String(),
                'tithi_end_dt' => $this->sunService->jdToCarbonPublic($tithiEndJd, $tz)->toIso8601String(),
                'nakshatra_end_dt' => $this->sunService->jdToCarbonPublic($nakEndJd, $tz)->toIso8601String(),
                'yoga_end_dt' => $this->sunService->jdToCarbonPublic($yogaEndJd, $tz)->toIso8601String(),
                'karana_end_dt' => $this->sunService->jdToCarbonPublic($karanaEndJd, $tz)->toIso8601String(),
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
                'Month_Amanta' => $hinduMonth['Amanta'],
                'Month_Purnimanta' => $hinduMonth['Purnimanta'],
                'Calendar_Type' => 'Purnimanta / Amavasyant (Calculated)',
            ],
            'Chart_Auxiliary' => [
                'Sun_Sign' => (string) $zodiacSigns[$sunSign],
                'Moon_Sign' => (string) $zodiacSigns[$moonSign],
            ],
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

            // New classical elements
            'Prahara_Full_Day' => $praharaTable,
            'Brahma_Muhurta' => $brahmaMuhurta,
            'Dur_Muhurta_Full_Day' => $durMuhurtaTable,
            'Varjyam' => $varjyam,
            'Amrita_Kaal' => $amritaKaal,
            'Pradosha_Kaal' => $pradoshaKaal,
            'Lagna' => $lagna,
            'Lagna_Full_Day' => $lagnaTable,

            'Dharma_Sindhu' => array_filter([
                'Punya_Kaal' => $punyaKaal,
            ], static fn ($v) => $v !== null),
        ];
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
        float $elevation = 0.0
    ): array {
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
        $nextDay = $date->addDay();
        [$nextSunrise] = $this->sunService->getSunriseSunset([
            ...$birthBase,
            'year' => $nextDay->year,
            'month' => $nextDay->month,
            'day' => $nextDay->day,
        ]);

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
        [$nakName, $nakPada, $nakLord] = $this->panchanga->getNakshatraInfo($moonLon);
        $vara = $this->panchanga->calculateVara($birthBase, $this->sunService);
        $hinduMonth = $this->panchanga->getHinduMonth($sunLon, $moonLon, (string) $tithi['paksha']);

        $jdSunrise = $this->toJulianDayFromCarbon($sunrise, $tz);
        $jdSunset = $this->toJulianDayFromCarbon($sunset, $tz);
        $jdNextSunrise = $this->toJulianDayFromCarbon($nextSunrise, $tz);

        $tithiNum = (int) ($tithi['index'] ?? 0);
        $tithiStartAngle = ($tithiNum - 1) * 12.0;
        $tithiEndAngle = $tithiNum * 12.0;
        $tithiStartJd = $this->findAngleCrossing($jdSunrise, $tithiStartAngle, -1, fn (float $jd) => $this->getMoonSunAngle($jd));
        $tithiEndJd = $this->findAngleCrossing($jdSunrise, $tithiEndAngle, 1, fn (float $jd) => $this->getMoonSunAngle($jd));
        $prevTithiEndJd = $tithiStartJd;

        return [
            'Tithi' => $tithi,
            'Vara' => $vara,
            'Nakshatra' => [
                'name' => $nakName,
                'pada' => $nakPada,
                'lord' => $nakLord,
            ],
            'Hindu_Calendar' => [
                'Month_Amanta' => $hinduMonth['Amanta'],
                'Month_Purnimanta' => $hinduMonth['Purnimanta'],
            ],
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
                'sunrise_iso' => $sunrise->toIso8601String(),
                'sunset_iso' => $sunset->toIso8601String(),
                'next_sunrise_iso' => $nextSunrise->toIso8601String(),
            ],
        ];
    }

    private function toJulianDayFromCarbon(CarbonImmutable $dt, string $tz): float
    {
        return $this->astronomy->toJulianDayUtc([
            'year' => (int) $dt->format('Y'),
            'month' => (int) $dt->format('m'),
            'day' => (int) $dt->format('d'),
            'hour' => (int) $dt->format('H'),
            'minute' => (int) $dt->format('i'),
            'second' => (int) $dt->format('s'),
            'timezone' => $tz,
        ]);
    }

    private function getMoonSunAngle(float $jd): float
    {
        $flags = SwissEphFFI::SEFLG_SWIEPH | SwissEphFFI::SEFLG_SIDEREAL;
        $sun = $this->calcBodyAtJd($jd, SwissEphFFI::SE_SUN, $flags);
        $moon = $this->calcBodyAtJd($jd, SwissEphFFI::SE_MOON, $flags);
        return AstroCore::normalize($moon - $sun);
    }

    private function getSunLongitude(float $jd): float
    {
        $flags = SwissEphFFI::SEFLG_SWIEPH | SwissEphFFI::SEFLG_SIDEREAL;
        return $this->calcBodyAtJd($jd, SwissEphFFI::SE_SUN, $flags);
    }

    private function getMoonLongitude(float $jd): float
    {
        $flags = SwissEphFFI::SEFLG_SWIEPH | SwissEphFFI::SEFLG_SIDEREAL;
        return $this->calcBodyAtJd($jd, SwissEphFFI::SE_MOON, $flags);
    }

    private function getSunMoonSum(float $jd): float
    {
        $flags = SwissEphFFI::SEFLG_SWIEPH | SwissEphFFI::SEFLG_SIDEREAL;
        $sun = $this->calcBodyAtJd($jd, SwissEphFFI::SE_SUN, $flags);
        $moon = $this->calcBodyAtJd($jd, SwissEphFFI::SE_MOON, $flags);
        return AstroCore::normalize($sun + $moon);
    }

    private function calcBodyAtJd(float $jd, int $planet, int $flags): float
    {
        $xx = $this->sweph->getFFI()->new('double[6]');
        $serr = $this->sweph->getFFI()->new('char[256]');
        $this->sweph->swe_calc_ut($jd, $planet, $flags, $xx, $serr);
        return AstroCore::normalize($xx[0]);
    }

    private function findAngleCrossing(float $jd0, float $targetAngle, int $direction, callable $angleFn): float
    {
        $step = 0.5 * $direction;
        $maxSteps = 20;
        $jd1 = $jd0;
        $f0 = $this->signedDiff($angleFn($jd1), $targetAngle);
        $jd2 = $jd1;
        $f1 = $f0;

        for ($i = 0; $i < $maxSteps; $i++) {
            $jd2 = $jd1 + $step;
            $f1 = $this->signedDiff($angleFn($jd2), $targetAngle);
            if ($f0 === 0.0) {
                return $jd1;
            }
            if ($f0 === 0.0 || ($f0 < 0 && $f1 > 0) || ($f0 > 0 && $f1 < 0)) {
                break;
            }
            $jd1 = $jd2;
            $f0 = $f1;
        }

        $low = min($jd1, $jd2);
        $high = max($jd1, $jd2);
        $fLow = $this->signedDiff($angleFn($low), $targetAngle);
        $fHigh = $this->signedDiff($angleFn($high), $targetAngle);

        for ($i = 0; $i < 80; $i++) {
            $mid = ($low + $high) / 2.0;
            $fMid = $this->signedDiff($angleFn($mid), $targetAngle);
            if ($fMid === 0.0) {
                return $mid;
            }
            if (($fLow < 0 && $fMid > 0) || ($fLow > 0 && $fMid < 0)) {
                $high = $mid;
                $fHigh = $fMid;
            } else {
                $low = $mid;
                $fLow = $fMid;
            }
        }

        return ($low + $high) / 2.0;
    }

    private function signedDiff(float $angle, float $target): float
    {
        $diff = AstroCore::normalize($angle - $target);
        if ($diff > 180.0) {
            $diff -= 360.0;
        }
        return $diff;
    }

    private function setAyanamsa(string $ayanamsa): void
    {
        $mode = match (strtoupper($ayanamsa)) {
            'LAHIRI' => SwissEphFFI::SE_SIDM_LAHIRI,
            'RAMAN' => SwissEphFFI::SE_SIDM_RAMAN,
            'KRISHNAMURTI' => SwissEphFFI::SE_SIDM_KRISHNAMURTI,
            default => SwissEphFFI::SE_SIDM_LAHIRI,
        };

        $this->sweph->swe_set_sid_mode($mode, 0.0, 0.0);
    }

    private function getSunMoonLongitudes(array $birth): array
    {
        $jd = $this->toJulianDayUTC($birth);

        $flags = SwissEphFFI::SEFLG_SWIEPH | SwissEphFFI::SEFLG_SIDEREAL;

        $sun = $this->calcBody($jd, SwissEphFFI::SE_SUN, $flags);
        $moon = $this->calcBody($jd, SwissEphFFI::SE_MOON, $flags);

        return [
            'Sun' => $sun,
            'Moon' => $moon,
        ];
    }

    private function calcBody(float $jd, int $planet, int $flags): float
    {
        $xx = $this->sweph->getFFI()->new('double[6]');
        $serr = $this->sweph->getFFI()->new('char[256]');

        $this->sweph->swe_calc_ut($jd, $planet, $flags, $xx, $serr);

        return $this->normalize($xx[0]);
    }

    private function toJulianDayUTC(array $birth): float
    {
        $local = CarbonImmutable::create(
            (int) $birth['year'],
            (int) $birth['month'],
            (int) $birth['day'],
            (int) $birth['hour'],
            (int) $birth['minute'],
            (int) $birth['second'],
            $birth['timezone']
        );

        $utc = $local->setTimezone('UTC');

        $hour = (int) $utc->format('H')
            + ((int) $utc->format('i')) / 60.0
            + ((int) $utc->format('s')) / 3600.0;

        return $this->sweph->swe_julday(
            $utc->year,
            $utc->month,
            $utc->day,
            $hour,
            SwissEphFFI::SE_GREG_CAL
        );
    }

    private function calculateIshtkaal(CarbonImmutable $sunrise, array $birth, string $tz): string
    {
        $dt = CarbonImmutable::create(
            (int) $birth['year'],
            (int) $birth['month'],
            (int) $birth['day'],
            (int) $birth['hour'],
            (int) $birth['minute'],
            (int) $birth['second'],
            $tz
        );

        $relSunrise = $sunrise;
        if ($dt->lessThan($sunrise)) {
            $relSunrise = $sunrise->subDay();
        }

        $sec = (int) abs((float) $dt->diffInSeconds($relSunrise, false));

        $gh = (int) floor($sec / 1440);
        $pl = (int) floor(($sec % 1440) / 24);
        $vp = (int) floor((($sec % 1440) % 24) / 0.4);

        return sprintf('%02d:%02d:%02d', $gh, $pl, $vp);
    }

    private function normalize(float $value): float
    {
        $val = fmod($value, 360.0);
        if ($val < 0) {
            $val += 360.0;
        }
        return $val;
    }

}
