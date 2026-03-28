<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Panchanga;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Astronomy\AstronomyService;
use JayeshMepani\PanchangCore\Astronomy\SunService;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Core\Enums\Masa;
use JayeshMepani\PanchangCore\Core\Enums\Nakshatra;
use JayeshMepani\PanchangCore\Core\Enums\Rasi;
use JayeshMepani\PanchangCore\Core\Enums\Tithi;
use JayeshMepani\PanchangCore\Core\Enums\Vara;
use JayeshMepani\PanchangCore\Festivals\FestivalService;
use JayeshMepani\PanchangCore\Festivals\Utils\BhadraEngine;
use RuntimeException;
use SwissEph\FFI\SwissEphFFI;
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
    private static string $ephePath = '';
    private static string $ayanamsa = 'LAHIRI';
    private array $monthCache = [];

    public function __construct(
        private SwissEphFFI $sweph,
        private SunService $sunService,
        private AstronomyService $astronomy,
        private PanchangaEngine $panchanga,
        private MuhurtaService $muhurta,
        private FestivalService $festivalService,
        private BhadraEngine $bhadraEngine,
    ) {
        $ephePath = self::$ephePath ?: (function_exists('config') ? config('panchang.ephe_path', getenv('PANCHANG_EPHE_PATH') ?: '') : (getenv('PANCHANG_EPHE_PATH') ?: ''));
        if (is_string($ephePath) && $ephePath !== '' && file_exists($ephePath)) {
            $this->sweph->swe_set_ephe_path($ephePath);
        }

        $this->setAyanamsa(self::$ayanamsa ?: (function_exists('config') ? config('panchang.ayanamsa', getenv('PANCHANG_AYANAMSA') ?: 'LAHIRI') : (getenv('PANCHANG_AYANAMSA') ?: 'LAHIRI')));
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
        $tithiStartJd = $this->findAngleCrossing($jdSunrise, $tithiStartAngle, -1, fn (float $jd) => $this->getMoonSunAngle($jd));
        $tithiEndJd = $this->findAngleCrossing($jdSunrise, $tithiEndAngle, 1, fn (float $jd) => $this->getMoonSunAngle($jd));

        $nakEndAngle = ($nakIdx + 1) * (360.0 / 27.0);
        $nakEndJd = $this->findAngleCrossing($jdSunrise, $nakEndAngle, 1, fn (float $jd) => $this->getMoonLongitude($jd));
        $nakStartAngle = $nakIdx * (360.0 / 27.0);
        $nakStartJd = $this->findAngleCrossing($jdSunrise, $nakStartAngle, -1, fn (float $jd) => $this->getMoonLongitude($jd));

        // Varjyam (Tyajyam) can occur once or twice between sunrise and next sunrise.
        $varjyamWindows = $this->calculateVarjyamWindows($relSunrise, $sunset, $nextSunrise, $jdSunrise, $jdNextSunrise, $nakIdx, $nakStartJd, $nakEndJd);
        $varjyam = $this->buildVarjyamPayload($varjyamWindows);
        $amritaKaal = $this->muhurta->calculateAmritaKaal($relSunrise, $varjyam);

        // Pradosha Kaal: first 1/5th of night, auspicious only when Trayodashi overlaps it.
        $pradoshaKaal = $this->calculatePradoshaKaal($sunset, $nextSunrise, $jdSunset, $jdNextSunrise, $tz);

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

        $todaySnapshot = [
            'Tithi' => $tithi,
            'Nakshatra' => [
                'name' => $nakName,
            ],
            'Hindu_Calendar' => [
                'Month_Amanta' => $hinduMonth['Month_Amanta'],
                'Month_Purnimanta' => $hinduMonth['Month_Purnimanta'],
                'Is_Adhika' => $hinduMonth['Is_Adhika'],
                'Is_Kshaya' => $hinduMonth['Is_Kshaya'],
                'Amanta_Index' => $hinduMonth['Amanta_Index'],
                'Purnimanta_Index' => $hinduMonth['Purnimanta_Index'],
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
                'sankranti_rashi' => ($currentSign !== $nextSunriseSign) ? (($currentSign + 1) % 12) : null,
            ],
        ];

        $tomorrowSnapshot = $this->getFestivalSnapshot($nextDay, $lat, $lon, $tz, $elevation);
        $festivals = $this->festivalService->resolveFestivalsForDate($date, $todaySnapshot, $tomorrowSnapshot);
        $dailyObservances = $this->festivalService->getDailyObservances($todaySnapshot);
        $daylightFivefoldByName = [];
        foreach ($daylightFivefold as $division) {
            if (is_array($division) && isset($division['name']) && is_string($division['name'])) {
                $daylightFivefoldByName[strtolower($division['name'])] = $division;
            }
        }
        $karmakalaWindows = [
            'sunrise' => [
                'label' => 'Sunrise',
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
            'Ayanamsa' => function_exists('config') ? config('panchang.ayanamsa', self::$ayanamsa) : self::$ayanamsa,
            'Ayanamsa_Degree' => AstroCore::formatAngle($ayanamsaDeg),
            'Ayanamsa_At' => AstroCore::formatDateTime($ayanamsaAt),
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
                'Sunrise' => AstroCore::formatTime($relSunrise),
                'Sunset' => AstroCore::formatTime($sunset),
                'Moonrise' => AstroCore::formatTime($moonrise),
                'Moonset' => AstroCore::formatTime($moonset),
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
                'Month_Purnimanta' => $hinduMonth['Month_Purnimanta'],
                'Is_Adhika' => $hinduMonth['Is_Adhika'],
                'Is_Kshaya' => $hinduMonth['Is_Kshaya'],
                'Amanta_Index' => $hinduMonth['Amanta_Index'],
                'Purnimanta_Index' => $hinduMonth['Purnimanta_Index'],
                'Calendar_Type' => 'Purnimanta / Amavasyant (Calculated)',
            ],
            'Chart_Auxiliary' => [
                'Sun_Sign' => Rasi::from($sunSign)->getName(),
                'Moon_Sign' => Rasi::from($moonSign)->getName(),
            ],
            'Festivals' => $festivals,
            'Daily_Observances' => $dailyObservances,
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
            'Sandhya' => $sandhya,
            'Gowri_Panchangam' => $gowriPanchangam,
            'Kala_Vela' => $kalaVela,
            'Karmakala_Windows' => $karmakalaWindows,
            'Varjyam' => $varjyam,
            'Amrita_Kaal' => $amritaKaal,
            'Pradosha_Kaal' => $pradoshaKaal,
            'Lagna' => $lagna,
            'Lagna_Full_Day' => $lagnaTable,

            'Bhadra' => $this->findBhadraPeriods($jdSunrise, $jdNextSunrise, $tithiNum, (string) ($tithi['paksha'] ?? '')),

            'Dharma_Sindhu' => array_filter([
                'Punya_Kaal' => $punyaKaal,
            ], static fn ($v) => $v !== null),
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

        $jdSunrise = $this->toJulianDayFromCarbon($sunrise, $tz);
        $hinduMonth = $this->getTrueHinduMonth($jdSunrise);
        $jdSunset = $this->toJulianDayFromCarbon($sunset, $tz);
        $jdNextSunrise = $this->toJulianDayFromCarbon($nextSunrise, $tz);

        $tithiNum = (int) ($tithi['index'] ?? 0);
        $tithiStartAngle = ($tithiNum - 1) * 12.0;
        $tithiEndAngle = $tithiNum * 12.0;
        $tithiStartJd = $this->findAngleCrossing($jdSunrise, $tithiStartAngle, -1, fn (float $jd) => $this->getMoonSunAngle($jd));
        $tithiEndJd = $this->findAngleCrossing($jdSunrise, $tithiEndAngle, 1, fn (float $jd) => $this->getMoonSunAngle($jd));
        $prevTithiEndJd = $tithiStartJd;

        $sunLonNextSunrise = $this->getSunLongitude($jdNextSunrise);
        $currentSign = (int) floor($sunLon / 30.0);
        $nextSunriseSign = (int) floor($sunLonNextSunrise / 30.0);
        $sankrantiRashi = null;
        if ($currentSign !== $nextSunriseSign) {
            $sankrantiRashi = ($currentSign + 1) % 12;
        }

        return [
            'Tithi' => $tithi,
            'Vara' => $vara,
            'Nakshatra' => [
                'name' => $nakName,
                'pada' => $nakPada,
                'lord' => $nakLord,
            ],
            'Hindu_Calendar' => [
                'Month_Amanta' => $hinduMonth['Month_Amanta'],
                'Month_Purnimanta' => $hinduMonth['Month_Purnimanta'],
                'Is_Adhika' => $hinduMonth['Is_Adhika'],
                'Is_Kshaya' => $hinduMonth['Is_Kshaya'],
                'Amanta_Index' => $hinduMonth['Amanta_Index'],
                'Purnimanta_Index' => $hinduMonth['Purnimanta_Index'],
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
                'sunrise_iso' => AstroCore::formatDateTime($sunrise),
                'sunset_iso' => AstroCore::formatDateTime($sunset),
                'next_sunrise_iso' => AstroCore::formatDateTime($nextSunrise),
                'sankranti_rashi' => $sankrantiRashi,
            ],
            'Bhadra' => $this->findBhadraPeriods($jdSunrise, $jdNextSunrise, $tithiNum, (string) $tithi['paksha']),
        ];
    }

    public function getElectionalSnapshot(
        CarbonImmutable $date,
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0,
        array $options = []
    ): array {
        $dayDetails = $this->getDayDetails($date, $lat, $lon, $tz, $elevation, null, $options);
        $sunrise = $this->parseDisplayDateTime((string) $dayDetails['sunrise_dt'], $tz);
        [$sunset] = [$this->resolveTimeStringToDateTime((string) $dayDetails['Sunset'], $sunrise, $tz)];

        $sunriseBirth = $this->buildBirthArray($sunrise, $lat, $lon, $tz, $elevation);
        $sunMoon = $this->getSunMoonLongitudes($sunriseBirth);
        $moonLongitude = (float) $sunMoon['Moon'];
        $nakIndex = (int) floor($moonLongitude / (360.0 / 27.0)) + 1;
        $moonSign = (int) floor($moonLongitude / 30.0);
        $ascLongitude = $this->astronomy->getAscendant($sunriseBirth);
        $planetaryStates = $this->astronomy->getPlanetaryStates($sunriseBirth);
        $planets = [];
        foreach ($planetaryStates as $planet => $state) {
            if (isset($state['lon'])) {
                $planets[$planet] = (float) $state['lon'];
            }
        }

        return [
            'day_details' => $dayDetails,
            'activity_profiles' => array_keys(ElectionalRuleBook::ACTIVITY_PROFILES),
            'transit_moorthy' => ElectionalEvaluator::calculateTransitMoorthy((string) ($dayDetails['Nakshatra']['name'] ?? '')),
            'mahadoshas' => ElectionalEvaluator::evaluateMahadoshas($planets, $ascLongitude),
            'planetary_states' => $planetaryStates,
            'sunrise_context' => [
                'sunrise_iso' => AstroCore::formatDateTime($sunrise),
                'sunset_iso' => $sunset instanceof CarbonImmutable ? AstroCore::formatDateTime($sunset) : null,
                'nakshatra_index' => $nakIndex,
                'moon_sign_index' => $moonSign,
                'lagna_sign' => Rasi::from(AstroCore::getSign($ascLongitude))->getName(),
                'lagna_degree_in_sign' => fmod(AstroCore::normalize($ascLongitude), 30.0),
                'lagna_kakshya_lord' => ElectionalEvaluator::getKakshyaLord(fmod(AstroCore::normalize($ascLongitude), 30.0)),
            ],
        ];
    }

    /**
     * Compute complete daily Muhurta evaluation package from canonical day details.
     * This centralizes ElectionalEvaluator input extraction so consumer scripts stay thin.
     */
    public function getDailyMuhurtaEvaluation(
        CarbonImmutable $date,
        float $lat,
        float $lon,
        string $tz,
        string $activityKey = 'general_auspicious',
        ?CarbonImmutable $currentAt = null,
        float $elevation = 0.0,
        array $options = []
    ): array {
        $at = $currentAt instanceof CarbonImmutable ? $currentAt->setTimezone($tz) : CarbonImmutable::now($tz);
        $dayDetails = $this->getDayDetails($date, $lat, $lon, $tz, $elevation, $at, $options);
        $sunriseDt = $this->parseDisplayDateTime((string) ($dayDetails['sunrise_dt'] ?? ''), $tz);
        $currentBirth = $this->buildBirthArray($at, $lat, $lon, $tz, $elevation);
        $sunMoon = $this->getSunMoonLongitudes($currentBirth);
        $moonLongitude = (float) ($sunMoon['Moon'] ?? 0.0);
        $moonSignIdx = ((int) floor($moonLongitude / 30.0)) % 12;
        $nakshatraIdxCurrent = ((int) floor($moonLongitude / (360.0 / 27.0))) % 27;
        $currentTithi = $this->panchanga->calculateTithi((float) ($sunMoon['Sun'] ?? 0.0), $moonLongitude);
        $currentAscLongitude = $this->astronomy->getAscendant($currentBirth);

        $tithiNumber = max(1, (int) ($currentTithi['index'] ?? ($dayDetails['Tithi']['index'] ?? 1)));
        $varaNumber = ((int) ($this->panchanga->calculateVara($currentBirth, $this->sunService)['index'] ?? ($dayDetails['Vara']['index'] ?? 0))) % 7;
        $nakshatraNumber = max(1, $nakshatraIdxCurrent + 1);
        $lagnaNumber = (AstroCore::getSign($currentAscLongitude) % 12) + 1;
        $isKrishnaPaksha = strcasecmp((string) ($currentTithi['paksha'] ?? ($dayDetails['Tithi']['paksha'] ?? '')), 'Krishna') === 0;
        $tithiEnum = Tithi::from($tithiNumber);
        $varaEnum = Vara::from($varaNumber);
        $nakshatraEnum = Nakshatra::from($nakshatraNumber - 1);
        $lagnaEnum = Rasi::from($lagnaNumber - 1);
        $moonSignEnum = Rasi::from($moonSignIdx);

        $sunsetDt = $this->resolveTimeStringToDateTime((string) ($dayDetails['Sunset'] ?? ''), $sunriseDt, $tz) ?? $sunriseDt;
        $nextSunriseIso = (string) (($dayDetails['Resolution_Context']['next_sunrise_iso'] ?? $dayDetails['next_sunrise_iso'] ?? ''));
        $nextSunriseDt = $nextSunriseIso !== ''
            ? $this->parseDisplayDateTime($nextSunriseIso, $tz)
            : $sunriseDt->addDay();
        $midnight = $sunriseDt->startOfDay();

        $sunrise = $this->toDecimalHoursFromBase($sunriseDt, $midnight);
        $sunset = $this->toDecimalHoursFromBase($sunsetDt, $midnight);
        $nextSunrise = $this->toDecimalHoursFromBase($nextSunriseDt, $midnight);
        $currentTime = $this->toDecimalHoursFromBase($at, $midnight);

        $evaluationResults = [
            'panchaka_dosha' => ElectionalEvaluator::calculatePanchakaDosha($tithiNumber, $varaNumber + 1, $nakshatraNumber, $lagnaNumber),
            'dagdha_tithi' => ElectionalEvaluator::calculateDagdhaTithi($tithiNumber, $moonSignIdx),
            'dagdha_yoga' => ElectionalEvaluator::calculateDagdhaYoga($varaNumber, $tithiNumber),
            'bhadra' => $this->evaluateCurrentBhadra($at, (array) ($dayDetails['Bhadra'] ?? [])),
            'rikta_tithi' => ElectionalEvaluator::calculateRiktaTithi($tithiNumber, $isKrishnaPaksha),
            'varjyam' => $this->evaluateCurrentVarjyam($at, (array) ($dayDetails['Varjyam'] ?? []), $tz),
            'amrita_kaal' => $this->evaluateCurrentNamedWindow(
                $at,
                (array) ($dayDetails['Amrita_Kaal'] ?? []),
                'amrita_kaal_start',
                'amrita_kaal_end',
                'Amrita Kaal',
                'Classical Panchanga Calculation Texts'
            ),
            'abhijit_cancellation' => $this->evaluateCurrentAbhijit($at, (array) ($dayDetails['Abhijit_Muhurta'] ?? []), $varaNumber),
        ];

        $rejectionReport = ElectionalEvaluator::generateRejectionReport($evaluationResults, $activityKey);

        return [
            'title' => 'Complete Muhurta Evaluation - Centralized package output',
            'activity_key' => $activityKey,
            'input_now' => AstroCore::formatDateTime($at),
            'date' => $date->toDateString(),
            'input_parameters' => [
                'tithi_number' => $tithiNumber,
                'tithi_name' => $tithiEnum->getName(),
                'tithi_number_base' => 1,
                'vara_number' => $varaNumber,
                'vara_name' => $varaEnum->getEnglishName(),
                'vara_index_base' => 0,
                'vara_sequence_number' => $varaNumber + 1,
                'vara_sequence_number_base' => 1,
                'nakshatra_number' => $nakshatraNumber,
                'nakshatra_name' => $nakshatraEnum->getName(),
                'nakshatra_number_base' => 1,
                'lagna_number' => $lagnaNumber,
                'lagna_name' => $lagnaEnum->getName(),
                'lagna_number_base' => 1,
                'moon_sign_idx' => $moonSignIdx,
                'moon_sign_name' => $moonSignEnum->getEnglishName(),
                'moon_sign_index_base' => 0,
                'moon_sign_number' => $moonSignIdx + 1,
                'moon_sign_number_base' => 1,
                'is_krishna_paksha' => $isKrishnaPaksha,
                'sunrise' => AstroCore::formatTime($sunriseDt),
                'sunrise_iso' => AstroCore::formatDateTime($sunriseDt),
                'sunrise_decimal_hours' => $sunrise,
                'sunset' => AstroCore::formatTime($sunsetDt),
                'sunset_iso' => AstroCore::formatDateTime($sunsetDt),
                'sunset_decimal_hours' => $sunset,
                'next_sunrise' => AstroCore::formatTime($nextSunriseDt),
                'next_sunrise_iso' => AstroCore::formatDateTime($nextSunriseDt),
                'next_sunrise_decimal_hours' => $nextSunrise,
                'current_time' => AstroCore::formatTime($at),
                'current_time_iso' => AstroCore::formatDateTime($at),
                'current_time_decimal_hours' => $currentTime,
            ],
            'evaluation_results' => $evaluationResults,
            'rejection_report' => $rejectionReport,
        ];
    }

    public function getActivityMuhurtas(
        CarbonImmutable $date,
        float $lat,
        float $lon,
        string $tz,
        string $activity,
        float $elevation = 0.0,
        array $options = []
    ): array {
        $profile = ElectionalRuleBook::getProfile($activity);
        $dayDetails = $this->getDayDetails($date, $lat, $lon, $tz, $elevation, null, $options);
        $sunrise = $this->parseDisplayDateTime((string) $dayDetails['sunrise_dt'], $tz);
        $nextDay = $date->addDay();
        [$nextSunrise] = $this->sunService->getSunriseSunset([
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
        ]);
        $sunset = $this->resolveTimeStringToDateTime((string) $dayDetails['Sunset'], $sunrise, $tz) ?? $sunrise;

        $boundaries = [
            $sunrise->getTimestamp(),
            $nextSunrise->getTimestamp(),
        ];

        foreach ($this->collectIsoBoundariesFromPayload($dayDetails) as $boundary) {
            if ($boundary >= $sunrise && $boundary <= $nextSunrise) {
                $boundaries[] = $boundary->getTimestamp();
            }
        }

        foreach ($this->collectElectionalTransitions($sunrise, $nextSunrise, $lat, $lon, $tz, $elevation) as $boundary) {
            if ($boundary >= $sunrise && $boundary <= $nextSunrise) {
                $boundaries[] = $boundary->getTimestamp();
            }
        }

        $boundaries = array_values(array_unique($boundaries));
        sort($boundaries, SORT_NUMERIC);

        $candidates = [];
        for ($i = 0; $i < count($boundaries) - 1; $i++) {
            $startTs = (int) $boundaries[$i];
            $endTs = (int) $boundaries[$i + 1];
            if ($endTs <= $startTs) {
                continue;
            }

            $start = CarbonImmutable::createFromTimestamp($startTs, $tz);
            $end = CarbonImmutable::createFromTimestamp($endTs, $tz);
            $midpointTs = $startTs + (($endTs - $startTs) / 2.0);
            $midpoint = CarbonImmutable::createFromTimestamp($midpointTs, $tz);

            $factors = $this->buildIntervalFactors(
                $midpoint,
                $start,
                $end,
                $sunrise,
                $sunset,
                $nextSunrise,
                $lat,
                $lon,
                $tz,
                $elevation,
                $dayDetails,
                $profile,
                $endTs - $startTs
            );

            $decision = ElectionalEvaluator::evaluateActivityProfile($profile, $factors);
            if (!$decision['accepted']) {
                continue;
            }

            $candidates[] = [
                'start' => AstroCore::formatTime($start),
                'end' => AstroCore::formatTime($end),
                'start_iso' => AstroCore::formatDateTime($start),
                'end_iso' => AstroCore::formatDateTime($end),
                'duration_seconds' => $endTs - $startTs,
                'score' => $decision['score'],
                'bonuses' => $decision['bonuses'],
                'factors' => $factors,
            ];
        }

        usort(
            $candidates,
            static fn (array $left, array $right): int => ($right['score'] <=> $left['score']) ?: strcmp((string) $left['start_iso'], (string) $right['start_iso'])
        );

        return [
            'activity' => $profile['activity_key'],
            'label' => $profile['label'] ?? $activity,
            'sources' => $profile['sources'] ?? [],
            'date' => $date->toDateString(),
            'candidate_count' => count($candidates),
            'candidates' => $candidates,
            'day_details' => $dayDetails,
        ];
    }

    public function getVivahaMuhurtas(
        CarbonImmutable $date,
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0,
        array $options = []
    ): array {
        return $this->getActivityMuhurtas($date, $lat, $lon, $tz, 'vivaha', $elevation, $options);
    }

    public function getGrihaPraveshaMuhurtas(
        CarbonImmutable $date,
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0,
        array $options = []
    ): array {
        return $this->getActivityMuhurtas($date, $lat, $lon, $tz, 'griha_pravesha', $elevation, $options);
    }

    private function toDecimalHoursFromBase(CarbonImmutable $dt, CarbonImmutable $base): float
    {
        return ($dt->getTimestamp() - $base->getTimestamp()) / 3600.0;
    }

    private function evaluateCurrentVarjyam(CarbonImmutable $at, array $varjyam, string $tz): array
    {
        $windows = $varjyam['windows'] ?? [];
        if (!is_array($windows)) {
            $windows = [];
        }

        $activeWindow = null;
        foreach ($windows as $window) {
            if (!is_array($window)) {
                continue;
            }

            $startJd = $window['window_start_jd'] ?? null;
            $endJd = $window['window_end_jd'] ?? null;
            if ((!is_float($startJd) && !is_int($startJd)) || (!is_float($endJd) && !is_int($endJd))) {
                continue;
            }

            $from = $this->sunService->jdToCarbonPublic((float) $startJd, $tz);
            $to = $this->sunService->jdToCarbonPublic((float) $endJd, $tz);
            if ($at >= $from && $at < $to) {
                $activeWindow = $window;
                break;
            }
        }

        return [
            'source' => 'Classical Panchanga Calculation Texts',
            'window_count' => (int) ($varjyam['window_count'] ?? count($windows)),
            'has_dosha' => $activeWindow !== null,
            'is_active' => $activeWindow !== null,
            'severity' => $activeWindow !== null ? 'high' : 'none',
            'active_window' => $activeWindow,
            'description' => $activeWindow !== null
                ? 'Varjyam is active now; avoid auspicious work in this window.'
                : 'Varjyam is not active at the evaluation time.',
            'blocked_activities' => $activeWindow !== null ? ['all_auspicious_work', 'marriage', 'griha_pravesh', 'new_ventures', 'important_work'] : [],
        ];
    }

    private function evaluateCurrentNamedWindow(
        CarbonImmutable $at,
        array $window,
        string $startKey,
        string $endKey,
        string $label,
        string $source
    ): array {
        $from = $this->resolveNamedWindowBoundary($window, $startKey, $at->timezoneName);
        $to = $this->resolveNamedWindowBoundary($window, $endKey, $at->timezoneName);
        $isActive = $from !== null && $to !== null && $at >= $from && $at < $to;

        return [
            'source' => $source,
            'label' => $label,
            'is_active' => $isActive,
            'is_auspicious' => $isActive,
            'start' => $from ? AstroCore::formatTime($from) : null,
            'end' => $to ? AstroCore::formatTime($to) : null,
            'start_iso' => $from ? AstroCore::formatDateTime($from) : null,
            'end_iso' => $to ? AstroCore::formatDateTime($to) : null,
            'description' => $isActive ? $label . ' is active now.' : $label . ' is not active at the evaluation time.',
            'enhanced_activities' => $isActive ? ['all_auspicious_work', 'marriage', 'griha_pravesh', 'new_ventures', 'important_work', 'spiritual_practices'] : [],
            'has_dosha' => false,
            'severity' => 'none',
        ];
    }

    private function evaluateCurrentAbhijit(CarbonImmutable $at, array $abhijit, int $varaNumber): array
    {
        $base = $this->evaluateCurrentNamedWindow(
            $at,
            $abhijit,
            'abhijit_start',
            'abhijit_end',
            'Abhijit Muhurta',
            'Muhurta Chintamani / Muhurta Martanda / Classical Dosha Nivarana Texts'
        );
        $isWednesday = $varaNumber === 3;

        $base['vara_number'] = $varaNumber;
        $base['vara_name'] = Vara::from($varaNumber)->getEnglishName();
        $base['is_wednesday'] = $isWednesday;
        $base['has_cancellation_power'] = $base['is_active'] && !$isWednesday;
        $base['cancellable_doshas'] = [
            'rikta_tithi' => true,
            'nakshatra_dosha' => true,
            'yoga_dosha' => true,
            'karana_dosha' => true,
            'minor_graha_dosha' => true,
            'varjyam' => false,
            'grahan' => false,
        ];
        $base['description'] = $isWednesday
            ? 'Abhijit on Wednesday has restricted cancellation use.'
            : ($base['is_active'] ? 'Abhijit Muhurta is active now and can cancel many minor doshas.' : 'Abhijit Muhurta is not active at the evaluation time.');

        return $base;
    }

    private function evaluateCurrentBhadra(CarbonImmutable $at, array $bhadraPeriods): array
    {
        $active = null;
        $activePart = null;

        foreach ($bhadraPeriods as $period) {
            if (!is_array($period)) {
                continue;
            }

            $periodStart = isset($period['start_time_iso']) ? $this->parseDisplayDateTime((string) $period['start_time_iso'], $at->timezoneName) : null;
            $periodEnd = isset($period['end_time_iso']) ? $this->parseDisplayDateTime((string) $period['end_time_iso'], $at->timezoneName) : null;
            if ($periodStart === null || $periodEnd === null || $at < $periodStart || $at >= $periodEnd) {
                continue;
            }

            $active = $period;
            foreach (['mukha', 'madhya', 'puchha'] as $partKey) {
                $part = (array) (($period['parts'] ?? [])[$partKey] ?? []);
                $partStart = isset($part['start_time_iso']) ? $this->parseDisplayDateTime((string) $part['start_time_iso'], $at->timezoneName) : null;
                $partEnd = isset($part['end_time_iso']) ? $this->parseDisplayDateTime((string) $part['end_time_iso'], $at->timezoneName) : null;
                if ($partStart !== null && $partEnd !== null && $at >= $partStart && $at < $partEnd) {
                    $activePart = $partKey;
                    break;
                }
            }
            break;
        }

        $hasDosha = $activePart === 'mukha' || $activePart === 'madhya';
        $severity = $activePart === 'mukha' ? 'critical' : ($activePart === 'madhya' ? 'high' : 'none');

        return [
            'source' => 'Muhurta Martanda / Bhadra (Vishti Karana) window from Panchang day calculation',
            'is_active' => $active !== null,
            'active_part' => $activePart,
            'active_period' => $active,
            'has_dosha' => $hasDosha,
            'severity' => $severity,
            'is_auspicious' => !$hasDosha,
            'blocked_activities' => $hasDosha ? ['all_auspicious_work', 'marriage', 'griha_pravesh', 'new_ventures'] : [],
            'description' => $active === null
                ? 'Bhadra is not active at the evaluation time.'
                : ($hasDosha ? 'Bhadra is active in a blocked portion now.' : 'Only Bhadra Puchha is active now; this portion is relatively safe.'),
        ];
    }

    private function resolveNamedWindowBoundary(array $window, string $key, string $tz): ?CarbonImmutable
    {
        $isoKey = $key . '_iso';
        if (isset($window[$isoKey]) && is_string($window[$isoKey]) && $window[$isoKey] !== '') {
            return $this->parseDisplayDateTime($window[$isoKey], $tz);
        }

        if (isset($window[$key]) && is_string($window[$key]) && $window[$key] !== '') {
            return $this->parseDisplayDateTime($window[$key], $tz);
        }

        return null;
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

    private function findBhadraPeriods(float $jdStart, float $jdEnd, int $sunriseTithi, string $paksha): array
    {
        $vishtiKaranas = [8, 15, 22, 29, 36, 43, 50, 57];
        $bhadraPeriods = [];

        // Check the range for any Karana transition into Vishti
        // A day usually has 2 Karanas. We check at start, and find transitions.
        $currentJd = $jdStart;
        while ($currentJd < $jdEnd) {
            $angle = $this->getMoonSunAngle($currentJd);
            $karanaNum = (int) floor($angle / 6.0) + 1;

            if (in_array($karanaNum, $vishtiKaranas, true)) {
                $vStartAngle = ($karanaNum - 1) * 6.0;
                $vEndAngle = $karanaNum * 6.0;

                $vStartJd = $this->findAngleCrossing($currentJd, $vStartAngle, -1, fn ($jd) => $this->getMoonSunAngle($jd));
                $vEndJd = $this->findAngleCrossing($currentJd, $vEndAngle, 1, fn ($jd) => $this->getMoonSunAngle($jd));

                // Constrain to the day
                $actualStart = max($jdStart, $vStartJd);
                $actualEnd = min($jdEnd, $vEndJd);

                if ($actualStart < $actualEnd) {
                    $moonRasi = (int) floor($this->getMoonLongitude($actualStart) / 30.0);
                    $bhadraPeriods[] = $this->bhadraEngine->calculateBhadra(
                        $jdStart,
                        $vStartJd,
                        $vEndJd,
                        $moonRasi,
                        $sunriseTithi,
                        $paksha
                    );
                }

                $currentJd = $vEndJd + 0.01; // Move past this Karana
            } else {
                // Find next Karana crossing
                $nextKaranaAngle = ceil(($angle + 0.0001) / 6.0) * 6.0;
                $nextKaranaJd = $this->findAngleCrossing($currentJd, $nextKaranaAngle, 1, fn ($jd) => $this->getMoonSunAngle($jd));
                $currentJd = $nextKaranaJd + 0.001;
            }
        }

        return $bhadraPeriods;
    }

    private function getSunMoonSum(float $jd): float
    {
        $flags = SwissEphFFI::SEFLG_SWIEPH | SwissEphFFI::SEFLG_SIDEREAL;
        $sun = $this->calcBodyAtJd($jd, SwissEphFFI::SE_SUN, $flags);
        $moon = $this->calcBodyAtJd($jd, SwissEphFFI::SE_MOON, $flags);
        return AstroCore::normalize($sun + $moon);
    }

    /**
     * Add deterministic *_iso companions for time-only fields in payload.
     * Rule: times earlier than sunrise belong to the next civil date in Panchang-day context.
     */
    private function annotateTimeOnlyFieldsWithDateTime(array $payload, CarbonImmutable $sunrise, string $tz): array
    {
        $annotate = function (array $node) use (&$annotate, $sunrise, $tz): array {
            foreach ($node as $key => $value) {
                if (is_array($value)) {
                    $node[$key] = $annotate($value);
                    continue;
                }

                if (!is_string($value) || !$this->isTimeOnlyString($value)) {
                    continue;
                }

                $isoKey = $key . '_iso';
                if (array_key_exists($isoKey, $node)) {
                    continue;
                }

                $dt = $this->resolveTimeStringToDateTime($value, $sunrise, $tz);
                if ($dt !== null) {
                    $node[$isoKey] = AstroCore::formatDateTime($dt);
                }
            }

            return $node;
        };

        return $annotate($payload);
    }

    private function isTimeOnlyString(string $value): bool
    {
        $v = trim($value);
        if ($v === '') {
            return false;
        }

        // Skip values that already include a date marker.
        if (str_contains($v, '/') || str_contains($v, '-') || str_contains($v, ',')) {
            return false;
        }

        $v = rtrim($v, '*');

        return (bool) preg_match('/^\d{1,2}:\d{2}(:\d{2})?\s?(AM|PM)?$/i', $v);
    }

    private function resolveTimeStringToDateTime(string $timeString, CarbonImmutable $sunrise, string $tz): ?CarbonImmutable
    {
        $raw = trim(rtrim($timeString, '*'));
        $formats = ['h:i:s A', 'h:i A', 'H:i:s', 'H:i'];

        foreach ($formats as $format) {
            try {
                $parsed = CarbonImmutable::createFromFormat($format, $raw, $tz);
            } catch (Throwable $e) {
                $parsed = false;
            }

            if ($parsed === false) {
                continue;
            }

            $dt = $sunrise->setTime((int) $parsed->format('H'), (int) $parsed->format('i'), (int) $parsed->format('s'));
            $secondsDeltaFromSunrise = abs($dt->diffInSeconds($sunrise, false));
            if ($dt->lessThan($sunrise) && $secondsDeltaFromSunrise >= 60) {
                $dt = $dt->addDay();
            }

            return $dt;
        }

        return null;
    }

    /**
     * Calculate all Varjyam windows that overlap this Panchang day (sunrise -> next sunrise).
     *
     * @return array<int, array<string, mixed>>
     */
    private function calculateVarjyamWindows(
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise,
        float $jdSunrise,
        float $jdNextSunrise,
        int $nakIdxAtSunrise,
        float $nakStartJdAtSunrise,
        float $nakEndJdAtSunrise
    ): array {
        $windows = [];
        $nakshatraSpan = 360.0 / 27.0;

        $currentNakIdx = $nakIdxAtSunrise;
        $currentNakStartJd = $nakStartJdAtSunrise;
        $currentNakEndJd = $nakEndJdAtSunrise;

        for ($i = 0; $i < 4; $i++) {
            if ($currentNakStartJd >= $jdNextSunrise) {
                break;
            }

            $window = $this->muhurta->calculateVarjyam(
                $sunrise,
                $sunset,
                $nextSunrise,
                $currentNakIdx,
                $currentNakStartJd,
                $currentNakEndJd
            );

            $windowStartJd = $window['nakshatra_start_jd'] + (($window['tyajya_ghati_start'] / 60.0) * ($window['nakshatra_end_jd'] - $window['nakshatra_start_jd']));
            $windowEndJd = $windowStartJd + (($window['tyajya_ghati_end'] - $window['tyajya_ghati_start']) / 60.0) * ($window['nakshatra_end_jd'] - $window['nakshatra_start_jd']);

            if ($windowEndJd > $jdSunrise && $windowStartJd < $jdNextSunrise) {
                $window['window_start_jd'] = $windowStartJd;
                $window['window_end_jd'] = $windowEndJd;
                $windows[] = $window;
            }

            $currentNakIdx = ($currentNakIdx + 1) % 27;
            $currentNakStartJd = $currentNakEndJd;
            $targetAngle = (($currentNakIdx + 1) % 27) * $nakshatraSpan;
            $currentNakEndJd = $this->findAngleCrossing(
                $currentNakStartJd + 1e-6,
                $targetAngle,
                1,
                fn (float $jd): float => $this->getMoonLongitude($jd)
            );

            if ($currentNakEndJd <= $currentNakStartJd) {
                break;
            }
        }

        usort(
            $windows,
            static fn (array $a, array $b): int => ((float) ($a['window_start_jd'] ?? 0.0)) <=> ((float) ($b['window_start_jd'] ?? 0.0))
        );

        return $windows;
    }

    /** Backward-compatible Varjyam payload with multi-window support. */
    private function buildVarjyamPayload(array $windows): array
    {
        if ($windows === []) {
            return [
                'is_available' => false,
                'window_count' => 0,
                'windows' => [],
            ];
        }

        $primary = $windows[0];

        return [
            ...$primary,
            'is_available' => true,
            'window_count' => count($windows),
            'windows' => $windows,
        ];
    }

    /** Calculate Pradosha Kaal using first 1/5th of night and Trayodashi overlap logic. */
    private function calculatePradoshaKaal(
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise,
        float $jdSunset,
        float $jdNextSunrise,
        string $tz
    ): array {
        $nightDurationJd = $jdNextSunrise - $jdSunset;
        $pradoshaEndJd = $jdSunset + ($nightDurationJd / 5.0);

        $trayodashiOverlaps = [];
        $cursor = $jdSunset + 1e-7;

        for ($i = 0; $i < 6 && $cursor < $pradoshaEndJd; $i++) {
            $interval = $this->getTithiIntervalAtJd($cursor);
            $tithiIndex = (int) ($interval['index'] ?? 0);
            $tithiPhase = $tithiIndex > 15 ? $tithiIndex - 15 : $tithiIndex;

            $overlapStartJd = max((float) $interval['start_jd'], $jdSunset);
            $overlapEndJd = min((float) $interval['end_jd'], $pradoshaEndJd);

            if ($tithiPhase === 13 && $overlapEndJd > $overlapStartJd) {
                $trayodashiOverlaps[] = [
                    'start_jd' => $overlapStartJd,
                    'end_jd' => $overlapEndJd,
                    'start' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($overlapStartJd, $tz)),
                    'end' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($overlapEndJd, $tz)),
                    'duration_minutes' => ($overlapEndJd - $overlapStartJd) * 1440.0,
                ];
            }

            $nextCursor = max((float) $interval['end_jd'] + 1e-6, $cursor + 1e-5);
            if ($nextCursor <= $cursor) {
                break;
            }
            $cursor = $nextCursor;
        }

        $trayodashiDurationMinutes =
            array_reduce(
                $trayodashiOverlaps,
                static fn (float $carry, array $row): float => $carry + (float) ($row['duration_minutes'] ?? 0.0),
                0.0
            );

        $hasTrayodashiOverlap = $trayodashiOverlaps !== [];
        $basePradoshaDurationMinutes = ($pradoshaEndJd - $jdSunset) * 1440.0;
        $effectiveStartJd = $hasTrayodashiOverlap
            ? (float) min(array_column($trayodashiOverlaps, 'start_jd'))
            : $jdSunset;
        $effectiveEndJd = $hasTrayodashiOverlap
            ? (float) max(array_column($trayodashiOverlaps, 'end_jd'))
            : $pradoshaEndJd;
        $effectiveDurationMinutes = ($effectiveEndJd - $effectiveStartJd) * 1440.0;

        $baseStart = $sunset;
        $baseEnd = $this->sunService->jdToCarbonPublic($pradoshaEndJd, $tz);
        $effectiveStart = $this->sunService->jdToCarbonPublic($effectiveStartJd, $tz);
        $effectiveEnd = $this->sunService->jdToCarbonPublic($effectiveEndJd, $tz);

        return [
            'pradosha_start' => AstroCore::formatTime($effectiveStart),
            'pradosha_end' => AstroCore::formatTime($effectiveEnd),
            'pradosha_start_iso' => AstroCore::formatDateTime($effectiveStart),
            'pradosha_end_iso' => AstroCore::formatDateTime($effectiveEnd),
            'sunset' => AstroCore::formatTime($sunset),
            'duration_minutes' => $effectiveDurationMinutes,
            'base_pradosha_start' => AstroCore::formatTime($baseStart),
            'base_pradosha_end' => AstroCore::formatTime($baseEnd),
            'base_pradosha_start_iso' => AstroCore::formatDateTime($baseStart),
            'base_pradosha_end_iso' => AstroCore::formatDateTime($baseEnd),
            'base_duration_minutes' => $basePradoshaDurationMinutes,
            'is_trayodashi' => $hasTrayodashiOverlap,
            'is_auspicious' => $hasTrayodashiOverlap,
            'trayodashi_overlap_minutes' => $trayodashiDurationMinutes,
            'trayodashi_overlaps' => $trayodashiOverlaps,
            'significance' => $hasTrayodashiOverlap
                ? 'Trayodashi overlaps Pradosha Kaal; this is Pradosh-observance eligible.'
                : 'No Trayodashi overlap in Pradosha Kaal for this day.',
        ];
    }

    /**
     * Return precise tithi interval (start/end JD) containing given JD.
     *
     * @return array{index:int,start_jd:float,end_jd:float}
     */
    private function getTithiIntervalAtJd(float $jd): array
    {
        $angle = $this->getMoonSunAngle($jd);
        $tithiIndex = (int) floor($angle / 12.0) + 1; // 1..30

        $startAngle = (($tithiIndex - 1) % 30) * 12.0;
        $endAngle = ($tithiIndex % 30) * 12.0;

        $startJd = $this->findAngleCrossing($jd, $startAngle, -1, fn (float $probe): float => $this->getMoonSunAngle($probe));
        $endJd = $this->findAngleCrossing($jd, $endAngle, 1, fn (float $probe): float => $this->getMoonSunAngle($probe));

        return [
            'index' => $tithiIndex,
            'start_jd' => $startJd,
            'end_jd' => $endJd,
        ];
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
        $step = 0.25 * $direction;
        $maxSteps = 100;
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
            if ($f0 === 0.0 || (abs($f1 - $f0) < 180.0 && (($f0 < 0 && $f1 > 0) || ($f0 > 0 && $f1 < 0)))) {
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
        $jd = $this->toJulianDayFromCarbon(
            CarbonImmutable::create(
                (int) $birth['year'],
                (int) $birth['month'],
                (int) $birth['day'],
                (int) $birth['hour'],
                (int) $birth['minute'],
                (int) $birth['second'],
                $birth['timezone']
            ),
            $birth['timezone']
        );

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
        return AstroCore::normalize($value);
    }

    /**
     * Calculate True Hindu Month using exact solar transits (Sankranti)
     * between exact Sun-Moon conjunctions (Amavasya).
     *
     * Lossless algorithm as per Siddhantic tradition.
     */
    private function getTrueHinduMonth(float $jd): array
    {
        /*
        foreach ($this->monthCache as $cache) {
            if ($jd >= $cache['start'] && $jd < $cache['end']) {
                return $cache['data'];
            }
        }
        */

        $prevAmavasya = $this->findAngleCrossing($jd, 0.0, -1, fn (float $t) => $this->getMoonSunAngle($t));
        $nextAmavasya = $this->findAngleCrossing($jd, 0.0, 1, fn (float $t) => $this->getMoonSunAngle($t));

        $sunPrev = $this->getSunLongitude($prevAmavasya);
        $sunNext = $this->getSunLongitude($nextAmavasya);

        $signPrev = (int) floor($sunPrev / 30.0);
        $signNext = (int) floor($sunNext / 30.0);

        // Count how many signs the Sun crossed.
        $diff = ($signNext - $signPrev + 12) % 12;

        $isAdhika = ($diff === 0);
        $isKshaya = ($diff >= 2);

        $amantaIdx = $signNext;
        if ($isAdhika || $isKshaya) {
            $amantaIdx = $signPrev;
        }

        $amantaName = Masa::fromIndex($amantaIdx)->getName();
        if ($isAdhika) {
            $amantaName .= ' (Adhika)';
        } elseif ($isKshaya) {
            $amantaName .= ' (Kśaya)';
        }

        // Purnimanta month is determined by the Amanta month and current paksha.
        $moonSunAngle = $this->getMoonSunAngle($jd);
        $paksha = ($moonSunAngle < 180.0) ? 'Shukla' : 'Krishna';

        $purnimantaIdx = ($paksha === 'Shukla') ? $amantaIdx : ($amantaIdx + 1) % 12;
        $purnimantaName = Masa::fromIndex($purnimantaIdx)->getName();
        if ($isAdhika) {
            $purnimantaName .= ' (Adhika)';
        }

        $data = [
            'Month_Amanta' => $amantaName,
            'Month_Purnimanta' => $purnimantaName,
            'Amanta_Index' => $amantaIdx,
            'Purnimanta_Index' => $purnimantaIdx,
            'Is_Adhika' => $isAdhika,
            'Is_Kshaya' => $isKshaya,
        ];

        $this->monthCache[] = [
            'start' => $prevAmavasya,
            'end' => $nextAmavasya,
            'data' => $data,
        ];

        // Keep cache manageable (LRU-ish)
        if (count($this->monthCache) > 3) {
            array_shift($this->monthCache);
        }

        return $data;
    }

    private function buildBirthArray(
        CarbonImmutable $dt,
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0
    ): array {
        return [
            'year' => (int) $dt->format('Y'),
            'month' => (int) $dt->format('m'),
            'day' => (int) $dt->format('d'),
            'hour' => (int) $dt->format('H'),
            'minute' => (int) $dt->format('i'),
            'second' => (int) $dt->format('s'),
            'timezone' => $tz,
            'latitude' => $lat,
            'longitude' => $lon,
            'elevation' => $elevation,
        ];
    }

    private function parseDisplayDateTime(string $value, string $tz): CarbonImmutable
    {
        $formats = ['d/m/Y h:i:s A', 'd/m/Y h:i A', 'Y-m-d H:i:s', 'Y-m-d\\TH:i:sP', 'Y-m-d H:i'];
        foreach ($formats as $format) {
            try {
                $parsed = CarbonImmutable::createFromFormat($format, trim($value), $tz);
            } catch (Throwable) {
                $parsed = false;
            }

            if ($parsed instanceof CarbonImmutable) {
                return $parsed;
            }
        }

        return CarbonImmutable::parse($value, $tz);
    }

    /** @return array<int, CarbonImmutable> */
    private function collectIsoBoundariesFromPayload(array $payload): array
    {
        $boundaries = [];

        $walker = function (mixed $node) use (&$walker, &$boundaries): void {
            if (!is_array($node)) {
                return;
            }

            foreach ($node as $key => $value) {
                if (is_array($value)) {
                    $walker($value);
                    continue;
                }

                if (!is_string($value)) {
                    continue;
                }

                if (is_string($key) && str_ends_with($key, '_iso')) {
                    try {
                        $boundaries[] = CarbonImmutable::parse($value);
                    } catch (Throwable) {
                    }
                }
            }
        };

        $walker($payload);

        return $boundaries;
    }

    /** @return array<int, CarbonImmutable> */
    private function collectElectionalTransitions(
        CarbonImmutable $sunrise,
        CarbonImmutable $nextSunrise,
        float $lat,
        float $lon,
        string $tz,
        float $elevation
    ): array {
        $jdStart = $this->toJulianDayFromCarbon($sunrise, $tz);
        $jdEnd = $this->toJulianDayFromCarbon($nextSunrise, $tz);

        $transitions = [];
        foreach ($this->collectAngleTransitions($jdStart, $jdEnd, 12.0, fn (float $jd): float => $this->getMoonSunAngle($jd), 4) as $jd) {
            $transitions[] = $this->sunService->jdToCarbonPublic($jd, $tz);
        }
        foreach ($this->collectAngleTransitions($jdStart, $jdEnd, 6.0, fn (float $jd): float => $this->getMoonSunAngle($jd), 8) as $jd) {
            $transitions[] = $this->sunService->jdToCarbonPublic($jd, $tz);
        }
        foreach ($this->collectAngleTransitions($jdStart, $jdEnd, 360.0 / 27.0, fn (float $jd): float => $this->getMoonLongitude($jd), 4) as $jd) {
            $transitions[] = $this->sunService->jdToCarbonPublic($jd, $tz);
        }
        foreach ($this->collectAngleTransitions($jdStart, $jdEnd, 360.0 / 27.0, fn (float $jd): float => $this->getSunMoonSum($jd), 4) as $jd) {
            $transitions[] = $this->sunService->jdToCarbonPublic($jd, $tz);
        }
        foreach ($this->collectAngleTransitions($jdStart, $jdEnd, 30.0, fn (float $jd): float => $this->getMoonLongitude($jd), 2) as $jd) {
            $transitions[] = $this->sunService->jdToCarbonPublic($jd, $tz);
        }
        foreach ($this->collectAscendantTransitions($jdStart, $jdEnd, 30.0 / 9.0, $lat, $lon, $this->astronomy->getAyanamsa($jdStart), 12) as $jd) {
            $transitions[] = $this->sunService->jdToCarbonPublic($jd, $tz);
        }

        return $transitions;
    }

    /** @return array<int, float> */
    private function collectAscendantTransitions(
        float $jdStart,
        float $jdEnd,
        float $stepDegrees,
        float $lat,
        float $lon,
        float $ayanamsaDeg,
        int $maxTransitions
    ): array {
        $out = [];
        $cursor = $jdStart;

        for ($i = 0; $i < $maxTransitions; $i++) {
            $current = $this->getAscendantSiderealAtJd($cursor, $lat, $lon, $ayanamsaDeg);
            $target = fmod((floor(($current + 1.0e-7) / $stepDegrees) + 1.0) * $stepDegrees, 360.0);
            $crossing = $this->findAngleCrossing(
                $cursor + 1.0e-6,
                $target,
                1,
                fn (float $jd): float => $this->getAscendantSiderealAtJd($jd, $lat, $lon, $ayanamsaDeg)
            );
            if ($crossing <= $cursor || $crossing >= $jdEnd) {
                break;
            }

            $out[] = $crossing;
            $cursor = $crossing + 1.0e-6;
        }

        return $out;
    }

    /** @return array<int, float> */
    private function collectAngleTransitions(
        float $jdStart,
        float $jdEnd,
        float $stepDegrees,
        callable $angleFn,
        int $maxTransitions
    ): array {
        $out = [];
        $cursor = $jdStart;

        for ($i = 0; $i < $maxTransitions; $i++) {
            $current = AstroCore::normalize($angleFn($cursor));
            $target = fmod((floor(($current + 1.0e-7) / $stepDegrees) + 1.0) * $stepDegrees, 360.0);
            $crossing = $this->findAngleCrossing($cursor + 1.0e-6, $target, 1, $angleFn);
            if ($crossing <= $cursor || $crossing >= $jdEnd) {
                break;
            }

            $out[] = $crossing;
            $cursor = $crossing + 1.0e-6;
        }

        return $out;
    }

    private function buildIntervalFactors(
        CarbonImmutable $midpoint,
        CarbonImmutable $start,
        CarbonImmutable $end,
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise,
        float $lat,
        float $lon,
        string $tz,
        float $elevation,
        array $dayDetails,
        array $profile,
        int $durationSeconds
    ): array {
        $birth = $this->buildBirthArray($midpoint, $lat, $lon, $tz, $elevation);
        $sunMoon = $this->getSunMoonLongitudes($birth);
        $moonLongitude = (float) $sunMoon['Moon'];
        $tithi = $this->panchanga->calculateTithi((float) $sunMoon['Sun'], $moonLongitude);
        $yoga = $this->panchanga->calculateYoga((float) $sunMoon['Sun'], $moonLongitude);
        [$karanaName, $karanaIdx] = $this->panchanga->getKarana((float) $sunMoon['Sun'], $moonLongitude);
        [$nakName] = $this->panchanga->getNakshatraInfo($moonLongitude);
        $nakIdx = (int) floor($moonLongitude / (360.0 / 27.0)) + 1;
        $moonSign = (int) floor($moonLongitude / 30.0);
        $vara = $this->panchanga->calculateVara($birth, $this->sunService);
        $hora = $this->muhurta->calculateHora($sunrise, $sunset, $nextSunrise, $midpoint, (int) $vara['index']);
        $chogadiya = $this->muhurta->calculateChogadiya($sunrise, $sunset, $nextSunrise, $midpoint, (int) $vara['index']);
        $ascLongitude = $this->astronomy->getAscendant($birth);
        $lagnaSign = Rasi::from(AstroCore::getSign($ascLongitude))->getName();
        $lagnaDegreeInSign = fmod(AstroCore::normalize($ascLongitude), 30.0);
        $planetaryStates = $this->astronomy->getPlanetaryStates($birth);
        $planets = [];
        foreach ($planetaryStates as $planet => $state) {
            if (isset($state['lon'])) {
                $planets[$planet] = (float) $state['lon'];
            }
        }
        $mahadoshas = ElectionalEvaluator::evaluateMahadoshas($planets, $ascLongitude);
        $planetHouses = [];
        $planetNavamsaHouses = [];
        foreach ($planets as $planet => $longitude) {
            $planetHouses[$planet] = AstroCore::getHouseNumFromLagna(AstroCore::getSign((float) $longitude), AstroCore::getSign($ascLongitude));
            $planetNavamsaHouses[$planet] = AstroCore::getHouseNumFromLagna($this->getNavamsaSignIndex((float) $longitude), $this->getNavamsaSignIndex($ascLongitude));
        }
        $lagnaNavamsaSignIdx = $this->getNavamsaSignIndex($ascLongitude);
        $lagnaSignIdx = AstroCore::getSign($ascLongitude);
        $lagnaLord = $this->getSignLordPlanet($lagnaSignIdx);
        $lagnaLordHouse = $planetHouses[$lagnaLord] ?? null;
        $lagnaNavamsaLord = $this->getSignLordPlanet($lagnaNavamsaSignIdx);
        $lagnaNavamsaLordHouse = $planetHouses[$lagnaNavamsaLord] ?? null;
        $moonSunAngle = AstroCore::normalize($moonLongitude - (float) $sunMoon['Sun']);
        $tithiPhase = (int) $tithi['index'] > 15 ? (int) $tithi['index'] - 15 : (int) $tithi['index'];

        return [
            'midpoint_iso' => AstroCore::formatDateTime($midpoint),
            'duration_seconds' => $durationSeconds,
            'vara_index' => (int) $vara['index'],
            'tithi_index_abs' => (int) $tithi['index'],
            'tithi_index_phase' => $tithiPhase,
            'tithi_name' => (string) $tithi['name'],
            'paksha' => (string) ($tithi['paksha'] ?? ''),
            'is_adhika_month' => (bool) ($dayDetails['Hindu_Calendar']['Is_Adhika'] ?? false),
            'hindu_month_amanta' => $this->getPlainMonthName((string) ($dayDetails['Hindu_Calendar']['Month_Amanta'] ?? '')),
            'nakshatra_index' => $nakIdx,
            'nakshatra_name' => $nakName,
            'yoga_index' => (int) $yoga['index'],
            'yoga_name' => (string) $yoga['name'],
            'karana_index' => $karanaIdx,
            'karana_name' => $karanaName,
            'moon_sign_index' => $moonSign,
            'moon_sign_name' => Rasi::from($moonSign)->getName(),
            'weekday_name' => (string) ($vara['name'] ?? ''),
            'moon_house_from_lagna' => AstroCore::getHouseNumFromLagna($moonSign, AstroCore::getSign($ascLongitude)),
            'lagna_sign_name' => $lagnaSign,
            'lagna_degree_in_sign' => $lagnaDegreeInSign,
            'lagna_kakshya_lord' => ElectionalEvaluator::getKakshyaLord($lagnaDegreeInSign),
            'lagna_lord_planet' => $lagnaLord,
            'lagna_lord_house' => $lagnaLordHouse,
            'lagna_navamsa_lord_planet' => $lagnaNavamsaLord,
            'lagna_navamsa_lord_house' => $lagnaNavamsaLordHouse,
            'lagna_navamsa_sign_name' => Rasi::from($lagnaNavamsaSignIdx)->getName(),
            'lagna_navamsa_mode' => $this->getNavamsaMode($lagnaNavamsaSignIdx),
            'hora_ruler' => (string) ($hora['ruler'] ?? ''),
            'chogadiya_name' => (string) ($chogadiya['name'] ?? ''),
            'chogadiya_is_auspicious' => (bool) ($chogadiya['is_auspicious'] ?? false),
            'suunya_rasi' => ElectionalEvaluator::calculateSuunyaRasi($tithiPhase, $lagnaSign),
            'gandanta' => ElectionalEvaluator::calculateGandanta($lagnaSign, $lagnaDegreeInSign, $moonSunAngle),
            'transit_moorthy' => ElectionalEvaluator::calculateTransitMoorthy($nakName),
            'mahadoshas' => $mahadoshas,
            'vara_tithi_yogas' => ElectionalEvaluator::evaluateVaraTithiYogas((int) $vara['index'], $tithiPhase),
            'planetary_states' => $planetaryStates,
            'planet_houses' => $planetHouses,
            'planet_navamsa_houses' => $planetNavamsaHouses,
            'lagna_shuddhi' => $this->evaluateLagnaShuddhi($profile['activity_key'] ?? 'general_auspicious', $planetHouses, AstroCore::getHouseNumFromLagna($moonSign, AstroCore::getSign($ascLongitude)), $this->getNavamsaMode($lagnaNavamsaSignIdx)),
            'window_flags' => [
                'rahu_kaal' => $this->intervalOverlapsNamedWindow($start, $end, (array) ($dayDetails['Rahu_Kaal_Gulika_Yamaganda']['Rahu_Kaal'] ?? []), 'start', 'end'),
                'yamaganda' => $this->intervalOverlapsNamedWindow($start, $end, (array) ($dayDetails['Rahu_Kaal_Gulika_Yamaganda']['Yamaganda'] ?? []), 'start', 'end'),
                'varjyam' => $this->intervalOverlapsVarjyam($start, $end, (array) ($dayDetails['Varjyam'] ?? []), $tz),
                'dur_muhurta' => $this->intervalOverlapsTable($start, $end, (array) ($dayDetails['Dur_Muhurta_Full_Day'] ?? [])),
                'bhadra_blocked' => $this->intervalOverlapsBhadra($start, $end, (array) ($dayDetails['Bhadra'] ?? [])),
                'abhijit' => $this->intervalOverlapsNamedWindow($start, $end, (array) ($dayDetails['Abhijit_Muhurta'] ?? []), 'abhijit_start', 'abhijit_end'),
                'amrita_kaal' => $this->intervalOverlapsNamedWindow($start, $end, (array) ($dayDetails['Amrita_Kaal'] ?? []), 'amrita_kaal_start', 'amrita_kaal_end'),
                'brahma_muhurta' => $this->intervalOverlapsNamedWindow($start, $end, (array) ($dayDetails['Brahma_Muhurta'] ?? []), 'brahma_muhurta_start', 'brahma_muhurta_end'),
                'pradosha' => $this->intervalOverlapsNamedWindow($start, $end, (array) ($dayDetails['Pradosha_Kaal'] ?? []), 'pradosha_start', 'pradosha_end'),
                'vishti_karana' => strcasecmp($karanaName, 'Vishti') === 0,
            ],
        ];
    }

    private function evaluateLagnaShuddhi(string $activityKey, array $planetHouses, int $moonHouseFromLagna, string $navamsaMode): array
    {
        $malefics = ['Sun', 'Mars', 'Saturn', 'Rahu', 'Ketu'];
        $lagnaMalefics = [];
        $seventhMalefics = [];

        foreach ($malefics as $planet) {
            $house = $planetHouses[$planet] ?? null;
            if ($house === 1) {
                $lagnaMalefics[] = $planet;
            }
            if ($house === 7) {
                $seventhMalefics[] = $planet;
            }
        }

        return [
            'activity' => $activityKey,
            'is_clean' => $lagnaMalefics === [] && $seventhMalefics === [] && $moonHouseFromLagna !== 8,
            'lagna_malefics' => $lagnaMalefics,
            'seventh_malefics' => $seventhMalefics,
            'moon_house_from_lagna' => $moonHouseFromLagna,
            'is_ashtama_chandra_from_lagna' => $moonHouseFromLagna === 8,
            'navamsa_mode' => $navamsaMode,
        ];
    }

    private function intervalOverlapsNamedWindow(
        CarbonImmutable $start,
        CarbonImmutable $end,
        array $window,
        string $startKey,
        string $endKey
    ): bool {
        $startIso = $window[$startKey . '_iso'] ?? null;
        $endIso = $window[$endKey . '_iso'] ?? null;
        if (!is_string($startIso) || !is_string($endIso)) {
            return false;
        }

        $from = $this->parseDisplayDateTime($startIso, $start->getTimezone()->getName());
        $to = $this->parseDisplayDateTime($endIso, $end->getTimezone()->getName());

        return $start < $to && $end > $from;
    }

    private function intervalOverlapsTable(CarbonImmutable $start, CarbonImmutable $end, array $rows): bool
    {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            if ($this->intervalOverlapsNamedWindow($start, $end, $row, 'start', 'end')) {
                return true;
            }
        }

        return false;
    }

    private function intervalOverlapsVarjyam(CarbonImmutable $start, CarbonImmutable $end, array $varjyam, string $tz): bool
    {
        $windows = $varjyam['windows'] ?? [];
        if (!is_array($windows)) {
            return false;
        }

        foreach ($windows as $window) {
            if (!is_array($window)) {
                continue;
            }

            $startJd = $window['window_start_jd'] ?? null;
            $endJd = $window['window_end_jd'] ?? null;
            if (!is_float($startJd) && !is_int($startJd)) {
                continue;
            }
            if (!is_float($endJd) && !is_int($endJd)) {
                continue;
            }

            $from = $this->sunService->jdToCarbonPublic((float) $startJd, $tz);
            $to = $this->sunService->jdToCarbonPublic((float) $endJd, $tz);
            if ($start < $to && $end > $from) {
                return true;
            }
        }

        return false;
    }

    private function intervalOverlapsBhadra(CarbonImmutable $start, CarbonImmutable $end, array $bhadra): bool
    {
        foreach ($bhadra as $period) {
            if (!is_array($period)) {
                continue;
            }

            $parts = (array) ($period['parts'] ?? []);
            foreach (['mukha', 'madhya'] as $blockedPart) {
                $part = (array) ($parts[$blockedPart] ?? []);
                if ($this->intervalOverlapsNamedWindow($start, $end, $part, 'start_time', 'end_time')) {
                    return true;
                }
            }
        }

        return false;
    }

    private function getNavamsaSignIndex(float $nirayanaLongitude): int
    {
        $normalized = AstroCore::normalize($nirayanaLongitude);
        $signIndex = (int) floor($normalized / 30.0);
        $degreesInSign = fmod($normalized, 30.0);
        $navamsaWithinSign = (int) floor($degreesInSign / (30.0 / 9.0));

        $movable = [0, 3, 6, 9];
        $fixed = [1, 4, 7, 10];

        if (in_array($signIndex, $movable, true)) {
            return ($signIndex + $navamsaWithinSign) % 12;
        }
        if (in_array($signIndex, $fixed, true)) {
            return ($signIndex + 8 + $navamsaWithinSign) % 12;
        }

        return ($signIndex + 4 + $navamsaWithinSign) % 12;
    }

    private function getNavamsaMode(int $navamsaSignIndex): string
    {
        return match ($navamsaSignIndex) {
            2, 5, 6, 10 => 'biped',
            3, 7, 11 => 'watery',
            default => 'quadruped',
        };
    }

    private function getPlainMonthName(string $value): string
    {
        $plain = preg_replace('/\s*\(.+$/u', '', trim($value));
        return is_string($plain) ? $plain : trim($value);
    }

    private function getSignLordPlanet(int $signIndex): string
    {
        return match ($signIndex) {
            0, 7 => 'Mars',
            1, 6 => 'Venus',
            2, 5 => 'Mercury',
            3 => 'Moon',
            4 => 'Sun',
            8, 11 => 'Jupiter',
            9, 10 => 'Saturn',
            default => 'Sun',
        };
    }

    private function getAscendantSiderealAtJd(float $jd, float $lat, float $lon, float $ayanamsaDeg): float
    {
        $cusps = $this->sweph->getFFI()->new('double[13]');
        $ascmc = $this->sweph->getFFI()->new('double[10]');
        $retFlag = $this->sweph->swe_houses($jd, $lat, $lon, ord('P'), $cusps, $ascmc);
        if ($retFlag < 0) {
            throw new RuntimeException('Swiss Ephemeris failed while calculating ascendant transitions.');
        }

        return AstroCore::normalize($ascmc[0] - $ayanamsaDeg);
    }
}
