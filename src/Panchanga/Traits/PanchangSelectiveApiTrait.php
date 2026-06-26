<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Panchanga\Traits;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Core\Enums\CalendarType;
use JayeshMepani\PanchangCore\Core\Enums\Nakshatra;
use JayeshMepani\PanchangCore\Core\Enums\Rasi;
use JayeshMepani\PanchangCore\Core\Enums\Tithi;
use JayeshMepani\PanchangCore\Core\Localization;
use JayeshMepani\PanchangCore\Panchanga\ElectionalEvaluator;
use JayeshMepani\PanchangCore\Panchanga\KalaNirnayaEngine;

trait PanchangSelectiveApiTrait
{
    /**
     * Calculate only the requested day-detail sections.
     *
     * Section names use the same keys as getDayDetails(), for example:
     * - Basic_Details
     * - Panchanga
     * - Special_Yogas
     * - Muhurta_Full_Day
     * - Abhijit_Muhurta
     * - Dharma_Sindhu
     *
     * @param array<int, string> $sections
     *
     * @return array<string, mixed>
     */
    public function getSelectedDetails(
        CarbonImmutable $date,
        float $lat,
        float $lon,
        string $tz,
        array $sections,
        float $elevation = 0.0,
        ?CarbonImmutable $calculationAt = null,
        CalendarType|string $calendarType = CalendarType::Amanta,
        array $options = []
    ): array {
        if (is_string($calendarType)) {
            $calendarType = match (strtolower($calendarType)) {
                'purnimanta', 'purnimant' => CalendarType::Purnimanta,
                default => CalendarType::Amanta,
            };
        }

        $requested = [];
        foreach ($sections as $section) {
            $requested[$this->normalizeSelectedSectionName($section)] = true;
        }

        if ($requested === []) {
            return [];
        }

        $defaultConfig = function_exists('config') ? config('panchang.defaults', []) : [];
        if (!is_array($defaultConfig)) {
            $defaultConfig = [];
        }

        $ctx = [];
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

        $ensureSun = function () use (&$ctx, $birthBase, $date, $tz, $lat, $lon, $elevation): void {
            if (isset($ctx['sun'])) {
                return;
            }

            [$sunrise, $sunset] = $this->sunService->getSunriseSunset($birthBase);
            $nextDay = $date->addDay();
            $nextBirth = [
                ...$birthBase,
                'year' => $nextDay->year,
                'month' => $nextDay->month,
                'day' => $nextDay->day,
            ];
            [$nextSunrise] = $this->sunService->getSunriseSunset($nextBirth);

            $previousForSunset = $sunrise->subDay();
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
            [, $previousSunset] = $this->sunService->getSunriseSunset($previousBirth);

            $ctx['sun'] = [
                'sunrise' => $sunrise,
                'sunset' => $sunset,
                'next_sunrise' => $nextSunrise,
                'previous_sunset' => $previousSunset,
                'next_birth' => $nextBirth,
            ];
        };

        $ensureTimeContext = function () use (&$ctx, $ensureSun, $birthBase, $date, $tz, $lat, $lon, $elevation, $calculationAt): void {
            if (isset($ctx['time'])) {
                return;
            }

            $ensureSun();
            $sunrise = $ctx['sun']['sunrise'];
            $at = $calculationAt instanceof CarbonImmutable ? $calculationAt->setTimezone($tz) : $sunrise;
            $relSunrise = $sunrise;
            if ($at->lessThan($sunrise)) {
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

            $previousForSunrise = $relSunrise->subDay();
            $previousSunriseBirth = [
                'year' => $previousForSunrise->year,
                'month' => $previousForSunrise->month,
                'day' => $previousForSunrise->day,
                'hour' => 0,
                'minute' => 0,
                'second' => 0,
                'timezone' => $tz,
                'latitude' => $lat,
                'longitude' => $lon,
                'elevation' => $elevation,
            ];
            [$previousSunrise] = $this->sunService->getSunriseSunset($previousSunriseBirth);

            $birthAt = [
                ...$birthBase,
                'hour' => (int) $at->format('H'),
                'minute' => (int) $at->format('i'),
                'second' => (int) $at->format('s'),
            ];
            $sunriseBirth = [
                ...$birthBase,
                'year' => (int) $relSunrise->format('Y'),
                'month' => (int) $relSunrise->format('m'),
                'day' => (int) $relSunrise->format('d'),
                'hour' => (int) $relSunrise->format('H'),
                'minute' => (int) $relSunrise->format('i'),
                'second' => (int) $relSunrise->format('s'),
            ];

            $ctx['time'] = [
                'calculation_at' => $at,
                'birth_at' => $birthAt,
                'rel_sunrise' => $relSunrise,
                'previous_sunrise' => $previousSunrise,
                'sunrise_birth' => $sunriseBirth,
            ];
        };

        $ensureLongitudes = function () use (&$ctx, $ensureTimeContext): void {
            if (isset($ctx['longitudes'])) {
                return;
            }

            $ensureTimeContext();
            $sunMoon = $this->getSunMoonLongitudes($ctx['time']['sunrise_birth']);
            $currentSunMoon = $this->getSunMoonLongitudes($ctx['time']['birth_at']);

            $ctx['longitudes'] = [
                'sun' => (float) $sunMoon['Sun'],
                'moon' => (float) $sunMoon['Moon'],
                'current_sun' => (float) $currentSunMoon['Sun'],
                'current_moon' => (float) $currentSunMoon['Moon'],
            ];
        };

        $ensurePanchanga = function () use (&$ctx, $ensureLongitudes): void {
            if (isset($ctx['panchanga'])) {
                return;
            }

            $ensureLongitudes();
            $sunLon = $ctx['longitudes']['sun'];
            $moonLon = $ctx['longitudes']['moon'];
            $currentSunLon = $ctx['longitudes']['current_sun'];
            $currentMoonLon = $ctx['longitudes']['current_moon'];

            [$nakName, $nakPada, $nakLord] = $this->panchanga->getNakshatraInfo($moonLon);
            [$currentNakName, $currentNakPada, $currentNakLord] = $this->panchanga->getNakshatraInfo($currentMoonLon);
            [$karanaName, $karanaIdx] = $this->panchanga->getKarana($sunLon, $moonLon);
            [$currentKaranaName, $currentKaranaIdx] = $this->panchanga->getKarana($currentSunLon, $currentMoonLon);

            $ctx['panchanga'] = [
                'tithi' => $this->panchanga->calculateTithi($sunLon, $moonLon),
                'current_tithi' => $this->panchanga->calculateTithi($currentSunLon, $currentMoonLon),
                'vara' => $this->panchanga->calculateVara($ctx['time']['birth_at'], $this->sunService),
                'nakshatra' => ['name' => $nakName, 'pada' => $nakPada, 'lord' => $nakLord],
                'current_nakshatra' => ['name' => $currentNakName, 'pada' => $currentNakPada, 'lord' => $currentNakLord],
                'nak_index' => (int) floor(($moonLon * 60.0) / 800.0),
                'current_nak_index' => (int) floor(($currentMoonLon * 60.0) / 800.0),
                'yoga' => $this->panchanga->calculateYoga($sunLon, $moonLon),
                'current_yoga' => $this->panchanga->calculateYoga($currentSunLon, $currentMoonLon),
                'karana' => ['name' => $karanaName, 'index' => $karanaIdx],
                'current_karana' => ['name' => $currentKaranaName, 'index' => $currentKaranaIdx],
            ];
        };

        $ensureJds = function () use (&$ctx, $ensureSun, $ensureTimeContext, $tz): void {
            if (isset($ctx['jds'])) {
                return;
            }

            $ensureSun();
            $ensureTimeContext();
            $ctx['jds'] = [
                'sunrise' => $this->toJulianDayFromCarbon($ctx['time']['rel_sunrise'], $tz),
                'previous_sunrise' => $this->toJulianDayFromCarbon($ctx['time']['previous_sunrise'], $tz),
                'sunset' => $this->toJulianDayFromCarbon($ctx['sun']['sunset'], $tz),
                'next_sunrise' => $this->toJulianDayFromCarbon($ctx['sun']['next_sunrise'], $tz),
                'calculation_at' => $this->toJulianDayFromCarbon($ctx['time']['calculation_at'], $tz),
            ];
        };

        $ensureCrossings = function () use (&$ctx, $ensurePanchanga, $ensureJds): void {
            if (isset($ctx['crossings'])) {
                return;
            }

            $ensurePanchanga();
            $ensureJds();
            $tithiNum = (int) $ctx['panchanga']['tithi']['index'];
            $nakIdx = $ctx['panchanga']['nak_index'];
            $yogaIdx = (int) $ctx['panchanga']['yoga']['index'];
            $karanaIdx = (int) $ctx['panchanga']['karana']['index'];
            $currentTithiNum = (int) $ctx['panchanga']['current_tithi']['index'];

            $ctx['crossings'] = [
                'tithi_start_jd' => $this->findAngleCrossing($ctx['jds']['sunrise'], ($tithiNum - 1) * 12.0, -1, fn (float $jd): float => $this->getMoonSunAngle($jd)),
                'tithi_end_jd' => $this->findAngleCrossing($ctx['jds']['sunrise'], $tithiNum * 12.0, 1, fn (float $jd): float => $this->getMoonSunAngle($jd)),
                'nakshatra_start_jd' => $this->findAngleCrossing($ctx['jds']['sunrise'], $nakIdx * (360.0 / 27.0), -1, fn (float $jd): float => $this->getMoonLongitude($jd)),
                'nakshatra_end_jd' => $this->findAngleCrossing($ctx['jds']['sunrise'], ($nakIdx + 1) * (360.0 / 27.0), 1, fn (float $jd): float => $this->getMoonLongitude($jd)),
                'yoga_end_jd' => $this->findAngleCrossing($ctx['jds']['sunrise'], $yogaIdx * (360.0 / 27.0), 1, fn (float $jd): float => $this->getSunMoonSum($jd)),
                'karana_end_jd' => $this->findAngleCrossing($ctx['jds']['sunrise'], $karanaIdx * 6.0, 1, fn (float $jd): float => $this->getMoonSunAngle($jd)),
                'current_tithi_start_jd' => $this->findAngleCrossing($ctx['jds']['calculation_at'], ($currentTithiNum - 1) * 12.0, -1, fn (float $jd): float => $this->getMoonSunAngle($jd)),
                'current_tithi_end_jd' => $this->findAngleCrossing($ctx['jds']['calculation_at'], $currentTithiNum * 12.0, 1, fn (float $jd): float => $this->getMoonSunAngle($jd)),
            ];
        };

        $ensureAyanamsa = function () use (&$ctx, $ensureTimeContext, $tz): void {
            if (isset($ctx['ayanamsa'])) {
                return;
            }

            $ensureTimeContext();
            $jd = $this->toJulianDayFromCarbon($ctx['time']['calculation_at'], $tz);
            $ctx['ayanamsa'] = [
                'jd' => $jd,
                'degree' => $this->astronomy->getAyanamsa($jd),
            ];
        };

        $ensureHinduMonth = function () use (&$ctx, $ensureJds): void {
            if (isset($ctx['hindu_month'])) {
                return;
            }

            $ensureJds();
            $ctx['hindu_month'] = $this->getTrueHinduMonth($ctx['jds']['sunrise']);
        };

        $ensureSankranti = function () use (&$ctx, $ensureJds, $date, $tz, $lat, $lon): void {
            if (isset($ctx['sankranti'])) {
                return;
            }

            $ensureJds();
            $civilStart = $date->startOfDay();
            $civilEnd = $civilStart->addDay();
            $jdCivilStart = $this->toJulianDayFromCarbon($civilStart, $tz);
            $jdCivilEnd = $this->toJulianDayFromCarbon($civilEnd, $tz);
            $civilStartSign = (int) floor($this->getSunLongitude($jdCivilStart) / 30.0) % 12;
            $civilEndSign = (int) floor($this->getSunLongitude($jdCivilEnd) / 30.0) % 12;

            $punyaKaal = null;
            $sankrantiRashi = null;
            if ($civilStartSign !== $civilEndSign) {
                $nextSign = ($civilStartSign + 1) % 12;
                $sankrantiJd = $this->findAngleCrossing($jdCivilStart, $nextSign * 30.0, 1, fn (float $jd): float => $this->getSunLongitude($jd));
                if ($sankrantiJd >= $jdCivilStart && $sankrantiJd < $jdCivilEnd) {
                    $sankrantiRashi = $nextSign;
                    $kalaEngine = new KalaNirnayaEngine($lat, $lon);
                    $nameMap = ['Mesha', 'Vrishabha', 'Mithuna', 'Karka', 'Simha', 'Kanya', 'Tula', 'Vrischika', 'Dhanu', 'Makara', 'Kumbha', 'Meena'];
                    $punyaKaal = $kalaEngine->calculatePunyaKaal($nameMap[$nextSign], $sankrantiJd, $ctx['jds']['sunrise'], $ctx['jds']['sunset'], $ctx['jds']['next_sunrise']);
                    $punyaKaal['sankranti_name'] = Rasi::from($nextSign)->getName();
                }
            }

            $ctx['sankranti'] = [
                'rashi' => $sankrantiRashi,
                'punya_kaal' => $punyaKaal,
            ];
        };

        $ensureLagnaTable = function () use (&$ctx, $ensureTimeContext, $ensureLongitudes, $ensureAyanamsa, $lat, $lon): void {
            if (isset($ctx['lagna_table'])) {
                return;
            }

            $ensureTimeContext();
            $ensureLongitudes();
            $ensureAyanamsa();
            $ctx['lagna_table'] = $this->muhurta->calculateLagnaTable(
                $ctx['time']['rel_sunrise'],
                $ctx['sun']['sunset'],
                $ctx['sun']['next_sunrise'],
                $ctx['longitudes']['sun'],
                $ctx['ayanamsa']['degree'],
                $lat,
                $lon,
                $this->jme
            );
        };

        $ensurePeriods = function () use (&$ctx, $ensureTimeContext): void {
            if (isset($ctx['periods'])) {
                return;
            }

            $ensureTimeContext();
            $ctx['periods'] = [
                'pradosha' => $this->calculatePradoshaKaal(
                    $ctx['sun']['sunset'],
                    $this->toJulianDayFromCarbon($ctx['sun']['sunset'], (string) $ctx['time']['birth_at']['timezone']),
                    (string) $ctx['time']['birth_at']['timezone']
                ),
                'nishitha' => $this->muhurta->calculateNishitaMuhurta($ctx['sun']['sunset'], $ctx['sun']['next_sunrise']),
            ];
        };

        $ensureBasic = function () use (
            &$ctx,
            $ensureSun,
            $ensureTimeContext,
            $ensureLongitudes,
            $ensurePanchanga,
            $ensureJds,
            $ensureCrossings,
            $ensureAyanamsa,
            $ensureHinduMonth,
            $ensureSankranti,
            $ensurePeriods,
            $birthBase,
            $date,
            $lat,
            $lon,
            $tz,
            $elevation,
            $calendarType,
            $options,
            $defaultConfig
        ): array {
            if (isset($ctx['basic'])) {
                return $ctx['basic'];
            }

            $ensureSun();
            $ensureTimeContext();
            $ensureLongitudes();
            $ensurePanchanga();
            $ensureJds();
            $ensureCrossings();
            $ensureAyanamsa();
            $ensureHinduMonth();
            $ensureSankranti();
            $ensurePeriods();

            [$moonrise, $moonset] = $this->sunService->getMoonriseMoonset($birthBase);
            $twilight = $this->sunService->getTwilightTimes($birthBase);
            $solarTransits = $this->sunService->getSolarTransits($birthBase);
            $solarTransitsNext = $this->sunService->getSolarTransits($ctx['sun']['next_birth']);
            $civilDayStart = $date->setTime(0, 0, 0);
            $civilDayEnd = $civilDayStart->addDay();
            $samvat = $this->panchanga->getSamvat($date->year, $date->month);
            $vikram = $samvat['Vikram_Samvat'];
            $saka = $samvat['Saka_Samvat'];
            $hinduMonth = $ctx['hindu_month'];
            $tithi = $ctx['panchanga']['tithi'];
            $currentTithi = $ctx['panchanga']['current_tithi'];
            $vara = $ctx['panchanga']['vara'];
            $sunLon = $ctx['longitudes']['sun'];
            $moonLon = $ctx['longitudes']['moon'];
            $currentSunLon = $ctx['longitudes']['current_sun'];
            $currentMoonLon = $ctx['longitudes']['current_moon'];
            $relSunrise = $ctx['time']['rel_sunrise'];
            $sunset = $ctx['sun']['sunset'];
            $nextSunrise = $ctx['sun']['next_sunrise'];
            $calculationAt = $ctx['time']['calculation_at'];
            $moonriseJd = $moonrise instanceof CarbonImmutable ? $this->toJulianDayFromCarbon($moonrise, $tz) : null;

            $todaySnapshot = [
                'Tithi' => $tithi,
                'Nakshatra' => ['name' => $ctx['panchanga']['nakshatra']['name']],
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
                    'sunrise_jd' => $ctx['jds']['sunrise'],
                    'previous_sunrise_jd' => $ctx['jds']['previous_sunrise'],
                    'sunset_jd' => $ctx['jds']['sunset'],
                    'next_sunrise_jd' => $ctx['jds']['next_sunrise'],
                    'tithi_start_jd' => $ctx['crossings']['tithi_start_jd'],
                    'tithi_end_jd' => $ctx['crossings']['tithi_end_jd'],
                    'prev_tithi_end_jd' => $ctx['crossings']['tithi_start_jd'],
                    'tithi_index_abs' => (int) $tithi['index'],
                    'tithi_index_phase' => (int) $tithi['index'] > 15 ? (int) $tithi['index'] - 15 : (int) $tithi['index'],
                    'paksha' => (string) ($tithi['paksha'] ?? ''),
                    'sunrise_iso' => AstroCore::formatDateTime($relSunrise),
                    'previous_sunrise_iso' => AstroCore::formatDateTime($ctx['time']['previous_sunrise']),
                    'sunset_iso' => AstroCore::formatDateTime($sunset),
                    'next_sunrise_iso' => AstroCore::formatDateTime($nextSunrise),
                    'sankranti_rashi' => $ctx['sankranti']['rashi'],
                ],
            ];

            $nextDay = $date->addDay();
            $tomorrowSnapshot = $this->getFestivalSnapshot($nextDay, $lat, $lon, $tz, $elevation, null, $calendarType, false);
            $yesterdaySnapshot = $this->getFestivalSnapshot($date->subDay(), $lat, $lon, $tz, $elevation, null, $calendarType, false);
            $festivals = $this->festivalService->resolveFestivalsForDate(
                $date,
                $todaySnapshot,
                $tomorrowSnapshot,
                $yesterdaySnapshot,
                fn (CarbonImmutable $historicalDate): array => $this->getFestivalSnapshot($historicalDate, $lat, $lon, $tz, $elevation, null, $calendarType, false)
            );
            $festivals = $this->retainFestivalsForDate($festivals, $date->toDateString());

            $panchanga = [
                'Tithi' => $tithi,
                'Tithi_At_Sunrise' => $tithi,
                'Current_Tithi_At_Input_Now' => $currentTithi,
                'Vara' => $vara,
                'Nakshatra' => $ctx['panchanga']['nakshatra'],
                'Nakshatra_At_Sunrise' => $ctx['panchanga']['nakshatra'],
                'Current_Nakshatra_At_Input_Now' => $ctx['panchanga']['current_nakshatra'],
                'Yoga' => $ctx['panchanga']['yoga'],
                'Current_Yoga_At_Input_Now' => $ctx['panchanga']['current_yoga'],
                'Karana' => $ctx['panchanga']['karana'],
                'Karana_At_Sunrise' => $ctx['panchanga']['karana'],
                'Current_Karana_At_Input_Now' => $ctx['panchanga']['current_karana'],
                'Sunrise' => [
                    'jd' => $ctx['jds']['sunrise'],
                    'iso' => AstroCore::formatDateTime($relSunrise),
                    'display' => AstroCore::formatTime($relSunrise),
                    'timestamp' => $relSunrise->getTimestamp(),
                ],
                'Sunset' => [
                    'jd' => $ctx['jds']['sunset'],
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
                'Ishtkaal' => $this->calculateIshtkaal($relSunrise, $ctx['time']['birth_at'], $tz),
                'Ishtkaal_iso' => AstroCore::formatDateTime($calculationAt),
                'sun_sunrise_lon' => AstroCore::formatAngle($sunLon),
                'moon_sunrise_lon' => AstroCore::formatAngle($moonLon),
                'sunrise_hm' => [(int) $relSunrise->format('H'), (int) $relSunrise->format('i')],
                'sunrise_dt' => AstroCore::formatDateTime($relSunrise),
                'tithi_start_dt' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($ctx['crossings']['tithi_start_jd'], $tz)),
                'tithi_end_dt' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($ctx['crossings']['tithi_end_jd'], $tz)),
                'nakshatra_end_dt' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($ctx['crossings']['nakshatra_end_jd'], $tz)),
                'yoga_end_dt' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($ctx['crossings']['yoga_end_jd'], $tz)),
                'karana_end_dt' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($ctx['crossings']['karana_end_jd'], $tz)),
            ];

            $ctx['basic'] = [
                'Display_Settings' => [
                    'measurement_system' => $options['measurement_system'] ?? $defaultConfig['measurement_system'] ?? 'indian_metric',
                    'date_time_format' => $options['date_time_format'] ?? $defaultConfig['date_time_format'] ?? 'indian_12h',
                    'time_notation' => $options['time_notation'] ?? $defaultConfig['time_notation'] ?? '12h',
                    'coordinate_format' => $options['coordinate_format'] ?? $defaultConfig['coordinate_format'] ?? 'decimal',
                    'angle_unit' => $options['angle_unit'] ?? $defaultConfig['angle_unit'] ?? 'degree',
                    'duration_format' => $options['duration_format'] ?? $defaultConfig['duration_format'] ?? 'mixed',
                    'timezone' => $tz,
                ],
                'Tithi' => $tithi,
                'Tithi_At_Sunrise' => $tithi,
                'Current_Tithi_At_Input_Now' => $currentTithi,
                'Vara' => $vara,
                'Nakshatra' => $ctx['panchanga']['nakshatra'],
                'Nakshatra_At_Sunrise' => $ctx['panchanga']['nakshatra'],
                'Current_Nakshatra_At_Input_Now' => $ctx['panchanga']['current_nakshatra'],
                'Yoga' => $ctx['panchanga']['yoga'],
                'Current_Yoga_At_Input_Now' => $ctx['panchanga']['current_yoga'],
                'Karana' => $ctx['panchanga']['karana'],
                'Karana_At_Sunrise' => $ctx['panchanga']['karana'],
                'Current_Karana_At_Input_Now' => $ctx['panchanga']['current_karana'],
                'Is_Vishti_Karana' => $this->panchanga->isVishtiKarana($sunLon, $moonLon),
                'Sunrise' => AstroCore::formatTime($relSunrise),
                'Sunset' => AstroCore::formatTime($sunset),
                'Ishtkaal' => $panchanga['Ishtkaal'],
                'Ishtkaal_iso' => AstroCore::formatDateTime($calculationAt),
                'sun_sunrise_lon' => AstroCore::formatAngle($sunLon),
                'moon_sunrise_lon' => AstroCore::formatAngle($moonLon),
                'sunrise_hm' => [(int) $relSunrise->format('H'), (int) $relSunrise->format('i')],
                'sunrise_dt' => AstroCore::formatDateTime($relSunrise),
                'Ayanamsa' => 'LAHIRI (Chitra Paksha)',
                'Ayanamsa_Degree' => AstroCore::formatAngle($ctx['ayanamsa']['degree']),
                'Ayanamsa_At' => AstroCore::formatDateTime($calculationAt),
                'Ayanamsa_JD' => $ctx['ayanamsa']['jd'],
                'Day_Types' => [
                    'civil_day_start' => AstroCore::formatDateTime($civilDayStart),
                    'civil_day_end' => AstroCore::formatDateTime($civilDayEnd),
                    'civil_day_length_seconds' => $civilDayEnd->getTimestamp() - $civilDayStart->getTimestamp(),
                    'mean_solar_day_seconds' => 86400.0,
                    'apparent_solar_day_seconds' => $solarTransitsNext['solar_noon']->getTimestamp() - $solarTransits['solar_noon']->getTimestamp(),
                    'apparent_solar_noon' => AstroCore::formatDateTime($solarTransits['solar_noon']),
                    'solar_midnight' => AstroCore::formatDateTime($solarTransits['solar_midnight']),
                ],
                'Twilight' => [
                    'civil' => ['dawn' => AstroCore::formatDateTime($twilight['civil']['dawn']), 'dusk' => AstroCore::formatDateTime($twilight['civil']['dusk'])],
                    'nautical' => ['dawn' => AstroCore::formatDateTime($twilight['nautical']['dawn']), 'dusk' => AstroCore::formatDateTime($twilight['nautical']['dusk'])],
                    'astronomical' => ['dawn' => AstroCore::formatDateTime($twilight['astronomical']['dawn']), 'dusk' => AstroCore::formatDateTime($twilight['astronomical']['dusk'])],
                ],
                'Panchanga' => $panchanga,
                'Hindu_Calendar' => [
                    'Ayana' => $this->panchanga->getAyana($sunLon),
                    'Ritu' => $this->panchanga->getRitu($sunLon),
                    'Vikram_Samvat' => $vikram,
                    'Gujarati_Samvat' => $this->panchanga->getGujaratiSamvat($vikram, $hinduMonth['Amanta_Index']),
                    'Saka_Samvat' => $saka,
                    'Kali_Samvat' => $this->panchanga->getKaliSamvat($vikram),
                    'Samvatsara' => $this->panchanga->getSamvatsara($vikram),
                    'Samvatsara_North' => $this->panchanga->getSamvatsaraNorth($vikram),
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
                    'Sun_Sign' => Rasi::from(AstroCore::getSign($currentSunLon))->getName(),
                    'Moon_Sign' => Rasi::from(AstroCore::getSign($currentMoonLon))->getName(),
                ],
                'Moon_Phase_At_Sunrise' => $this->buildMoonPhase($sunLon, $moonLon),
                'Current_Moon_Phase_At_Input_Now' => $this->buildMoonPhase($currentSunLon, $currentMoonLon),
                'Nitya_Yoga_Observations' => ElectionalEvaluator::calculateNityaYogaObservations((int) $ctx['panchanga']['current_yoga']['index'], (string) $ctx['panchanga']['current_yoga']['name']),
                'Vara_Tithi_Doshas' => ElectionalEvaluator::calculateVaraTithiDoshas((int) $ctx['panchanga']['vara']['index'], (int) $ctx['panchanga']['current_tithi']['index']),
                'Tithi_Observance_Analysis' => [
                    'rule_system' => 'smarta_udaya_tithi_structural_analysis',
                    'is_festival_specific_engine' => false,
                    'festival_specific_override_required' => true,
                    'current_at_input_now' => [
                        ...$this->buildTithiObservanceAnalysis(new KalaNirnayaEngine($lat, $lon), (int) $ctx['panchanga']['current_tithi']['index'], $ctx['crossings']['current_tithi_start_jd'], $ctx['crossings']['current_tithi_end_jd'], $ctx['jds']['sunrise'], $ctx['jds']['next_sunrise'], $ctx['crossings']['current_tithi_start_jd'], $tz),
                        'calculated_for' => 'input_now',
                        'input_now_iso' => AstroCore::formatDateTime($ctx['time']['calculation_at']),
                    ],
                    'at_sunrise' => [
                        ...$this->buildTithiObservanceAnalysis(new KalaNirnayaEngine($lat, $lon), (int) $ctx['panchanga']['tithi']['index'], $ctx['crossings']['tithi_start_jd'], $ctx['crossings']['tithi_end_jd'], $ctx['jds']['sunrise'], $ctx['jds']['next_sunrise'], $ctx['crossings']['tithi_start_jd'], $tz),
                        'calculated_for' => 'sunrise',
                        'sunrise_iso' => AstroCore::formatDateTime($ctx['time']['rel_sunrise']),
                    ],
                ],
                'Yatra_Screening' => [
                    'current_at_input_now' => [
                        ...$this->calculateYatraScreening((int) $ctx['panchanga']['current_tithi']['index'], (int) $ctx['panchanga']['vara']['index'], (int) $ctx['panchanga']['current_nak_index'], AstroCore::getSign($this->astronomy->getAscendant($ctx['time']['birth_at']))),
                        'calculated_for' => 'input_now',
                        'input_now_iso' => AstroCore::formatDateTime($ctx['time']['calculation_at']),
                    ],
                    'at_sunrise' => [
                        ...$this->calculateYatraScreening((int) $ctx['panchanga']['tithi']['index'], (int) $ctx['panchanga']['vara']['index'], (int) $ctx['panchanga']['nak_index'], AstroCore::getSign($this->astronomy->getAscendant([
                            'year' => (int) $ctx['time']['rel_sunrise']->format('Y'),
                            'month' => (int) $ctx['time']['rel_sunrise']->format('m'),
                            'day' => (int) $ctx['time']['rel_sunrise']->format('d'),
                            'hour' => (int) $ctx['time']['rel_sunrise']->format('H'),
                            'minute' => (int) $ctx['time']['rel_sunrise']->format('i'),
                            'second' => (int) $ctx['time']['rel_sunrise']->format('s'),
                            'timezone' => $tz,
                            'latitude' => $lat,
                            'longitude' => $lon,
                            'elevation' => $elevation,
                        ]))),
                        'calculated_for' => 'sunrise',
                        'sunrise_iso' => AstroCore::formatDateTime($ctx['time']['rel_sunrise']),
                    ],
                ],
                'Vrata_Parana' => [
                    'rule_system' => 'supported_non_ekadashi_vrata_parana_profiles',
                    'is_complete_system' => false,
                    'supported_families' => $this->supportedVrataParanaFamilies(),
                    'current_at_input_now' => $this->buildVrataParanaProfile((int) $ctx['panchanga']['current_tithi']['index'], (string) ($ctx['panchanga']['current_tithi']['paksha'] ?? ''), $ctx['crossings']['current_tithi_start_jd'], $ctx['crossings']['current_tithi_end_jd'], $ctx['jds']['sunrise'], $ctx['jds']['sunset'], $ctx['jds']['next_sunrise'], $moonriseJd, $ctx['periods']['pradosha'], $ctx['periods']['nishitha'], $tz),
                    'at_sunrise' => $this->buildVrataParanaProfile((int) $ctx['panchanga']['tithi']['index'], (string) ($ctx['panchanga']['tithi']['paksha'] ?? ''), $ctx['crossings']['tithi_start_jd'], $ctx['crossings']['tithi_end_jd'], $ctx['jds']['sunrise'], $ctx['jds']['sunset'], $ctx['jds']['next_sunrise'], $moonriseJd, $ctx['periods']['pradosha'], $ctx['periods']['nishitha'], $tz),
                ],
                'Day_Night_Measures' => $this->buildDayNightMeasures($relSunrise, $sunset, $ctx['sun']['next_sunrise']),
                'Festivals' => $festivals,
                'Daily_Observances' => $this->festivalService->getDailyObservances($todaySnapshot),
            ];

            return $ctx['basic'];
        };

        $buildShivaVaasa = function () use (&$ctx, $ensurePanchanga, $ensureCrossings, $ensureTimeContext, $tz): array {
            $ensurePanchanga(); $ensureCrossings(); $ensureTimeContext();
            $atSunrise = $this->calculateShivaVaasa((int) $ctx['panchanga']['tithi']['index'], $ctx['crossings']['tithi_end_jd'], $tz);

            return [
                ...$this->calculateShivaVaasa((int) $ctx['panchanga']['current_tithi']['index'], $ctx['crossings']['current_tithi_end_jd'], $tz),
                'calculated_for' => 'input_now',
                'input_now_iso' => AstroCore::formatDateTime($ctx['time']['calculation_at']),
                'at_sunrise' => [
                    ...$atSunrise,
                    'calculated_for' => 'sunrise',
                    'sunrise_iso' => AstroCore::formatDateTime($ctx['time']['rel_sunrise']),
                ],
            ];
        };

        $buildAgniVaasa = function () use (&$ctx, $ensurePanchanga, $ensureCrossings, $ensureTimeContext, $tz): array {
            $ensurePanchanga(); $ensureCrossings(); $ensureTimeContext();
            $atSunrise = $this->calculateAgniVaasa((int) $ctx['panchanga']['tithi']['index'], (int) $ctx['panchanga']['vara']['index'], $ctx['crossings']['tithi_end_jd'], $tz);

            return [
                ...$this->calculateAgniVaasa((int) $ctx['panchanga']['current_tithi']['index'], (int) $ctx['panchanga']['vara']['index'], $ctx['crossings']['current_tithi_end_jd'], $tz),
                'calculated_for' => 'input_now',
                'input_now_iso' => AstroCore::formatDateTime($ctx['time']['calculation_at']),
                'at_sunrise' => [
                    ...$atSunrise,
                    'calculated_for' => 'sunrise',
                    'sunrise_iso' => AstroCore::formatDateTime($ctx['time']['rel_sunrise']),
                ],
            ];
        };

        $buildYoginiVaasa = function () use (&$ctx, $ensurePanchanga, $ensureTimeContext): array {
            $ensurePanchanga(); $ensureTimeContext();
            $atSunrise = $this->calculateYoginiVaasa((int) $ctx['panchanga']['tithi']['index']);

            return [
                ...$this->calculateYoginiVaasa((int) $ctx['panchanga']['current_tithi']['index']),
                'calculated_for' => 'input_now',
                'input_now_iso' => AstroCore::formatDateTime($ctx['time']['calculation_at']),
                'at_sunrise' => [
                    ...$atSunrise,
                    'calculated_for' => 'sunrise',
                    'sunrise_iso' => AstroCore::formatDateTime($ctx['time']['rel_sunrise']),
                ],
            ];
        };

        $result = [];
        foreach (array_keys($requested) as $requestedSection) {
            if ($requestedSection === 'Basic_Details') {
                $result['Basic_Details'] = $ensureBasic();
                continue;
            }

            $result[$requestedSection] = match ($requestedSection) {
                'Panchanga' => $ensureBasic()['Panchanga'],
                'Special_Yogas' => (function () use (&$ctx, $ensureJds, $ensurePanchanga, $date, $tz): array {
                    $ensureJds(); $ensurePanchanga();
                    return $this->calculateSpecialYogas($date, $ctx['jds']['sunrise'], $ctx['jds']['next_sunrise'], (int) $ctx['panchanga']['tithi']['index'], (int) $ctx['panchanga']['vara']['index'], $tz);
                })(),
                'Anandadi_Yoga' => (function () use (&$ctx, $ensureJds, $ensurePanchanga, $tz): array {
                    $ensureJds(); $ensurePanchanga();
                    return $this->calculateAnandadiYoga($ctx['jds']['sunrise'], $ctx['jds']['next_sunrise'], (int) $ctx['panchanga']['vara']['index'], $tz, $ctx['jds']['calculation_at']);
                })(),
                'Amritadi_Yoga' => (function () use (&$ctx, $ensureJds, $ensurePanchanga, $tz): array {
                    $ensureJds(); $ensurePanchanga();
                    return $this->calculateAmritadiYoga($ctx['jds']['sunrise'], $ctx['jds']['next_sunrise'], (int) $ctx['panchanga']['vara']['index'], $tz, $ctx['jds']['calculation_at']);
                })(),
                'Nitya_Yoga_Observations' => (function () use (&$ctx, $ensurePanchanga): array {
                    $ensurePanchanga();
                    return ElectionalEvaluator::calculateNityaYogaObservations((int) $ctx['panchanga']['current_yoga']['index'], (string) $ctx['panchanga']['current_yoga']['name']);
                })(),
                'Panchak' => (function () use (&$ctx, $ensureJds, $tz): array {
                    $ensureJds();
                    return $this->calculatePanchak($ctx['jds']['sunrise'], $ctx['jds']['next_sunrise'], $tz);
                })(),
                'Maitreya_Yoga' => (function () use (&$ctx, $ensureJds, $ensurePanchanga, $ensureLagnaTable, $tz): array {
                    $ensureJds(); $ensurePanchanga(); $ensureLagnaTable();
                    return $this->calculateMaitreyaYoga($ctx['jds']['sunrise'], $ctx['jds']['next_sunrise'], (int) $ctx['panchanga']['vara']['index'], $ctx['lagna_table'], $tz);
                })(),
                'Gajachchhaya_Yoga' => (function () use (&$ctx, $ensureJds, $ensureHinduMonth, $tz): array {
                    $ensureJds(); $ensureHinduMonth();
                    return $this->calculateGajachchhayaYoga($ctx['jds']['sunrise'], $ctx['jds']['next_sunrise'], $ctx['hindu_month'], $tz);
                })(),
                'Nakshatra_Shool' => (function () use (&$ctx, $ensureJds, $tz): array {
                    $ensureJds();
                    return $this->calculateNakshatraShool($ctx['jds']['sunrise'], $ctx['jds']['next_sunrise'], $tz);
                })(),
                'Disha_Shool' => (function () use (&$ctx, $ensurePanchanga): array {
                    $ensurePanchanga();
                    return $this->calculateDishaShool((int) $ctx['panchanga']['vara']['index']);
                })(),
                'Yatra_Screening' => (function () use (&$ctx, $ensurePanchanga, $ensureTimeContext, $lat, $lon, $tz, $elevation): array {
                    $ensurePanchanga(); $ensureTimeContext();
                    return [
                        'current_at_input_now' => [
                            ...$this->calculateYatraScreening((int) $ctx['panchanga']['current_tithi']['index'], (int) $ctx['panchanga']['vara']['index'], (int) $ctx['panchanga']['current_nak_index'], AstroCore::getSign($this->astronomy->getAscendant($ctx['time']['birth_at']))),
                            'calculated_for' => 'input_now',
                            'input_now_iso' => AstroCore::formatDateTime($ctx['time']['calculation_at']),
                        ],
                        'at_sunrise' => [
                            ...$this->calculateYatraScreening((int) $ctx['panchanga']['tithi']['index'], (int) $ctx['panchanga']['vara']['index'], (int) $ctx['panchanga']['nak_index'], AstroCore::getSign($this->astronomy->getAscendant([
                                'year' => (int) $ctx['time']['rel_sunrise']->format('Y'),
                                'month' => (int) $ctx['time']['rel_sunrise']->format('m'),
                                'day' => (int) $ctx['time']['rel_sunrise']->format('d'),
                                'hour' => (int) $ctx['time']['rel_sunrise']->format('H'),
                                'minute' => (int) $ctx['time']['rel_sunrise']->format('i'),
                                'second' => (int) $ctx['time']['rel_sunrise']->format('s'),
                                'timezone' => $tz,
                                'latitude' => $lat,
                                'longitude' => $lon,
                                'elevation' => $elevation,
                            ]))),
                            'calculated_for' => 'sunrise',
                            'sunrise_iso' => AstroCore::formatDateTime($ctx['time']['rel_sunrise']),
                        ],
                    ];
                })(),
                'Rahu_Vaasa' => (function () use (&$ctx, $ensurePanchanga): array {
                    $ensurePanchanga();
                    return $this->calculateRahuVaasa((int) $ctx['panchanga']['vara']['index']);
                })(),
                'Chandra_Vaasa' => (function () use (&$ctx, $ensureJds, $ensureLongitudes, $tz): array {
                    $ensureJds(); $ensureLongitudes();
                    return $this->calculateChandraVaasa($ctx['jds']['sunrise'], $ctx['jds']['next_sunrise'], $tz, $ctx['longitudes']['current_moon'], $ctx['jds']['calculation_at']);
                })(),
                'Shiva_Vaasa' => $buildShivaVaasa(),
                'Agni_Vaasa' => $buildAgniVaasa(),
                'Yogini_Vaasa' => $buildYoginiVaasa(),
                'Panchaka_Rahita' => (function () use (&$ctx, $ensurePanchanga, $ensureLongitudes, $ensureTimeContext): array {
                    $ensurePanchanga(); $ensureLongitudes(); $ensureTimeContext();
                    $ascLon = $this->astronomy->getAscendant($ctx['time']['birth_at']);
                    $ascSign = AstroCore::getSign($ascLon);
                    $atSunrise = $this->panchanga->calculatePanchakaRahita(
                        (int) $ctx['panchanga']['tithi']['index'],
                        (int) $ctx['panchanga']['vara']['index'] + 1,
                        $ctx['panchanga']['nak_index'] + 1,
                        $ascSign + 1
                    );
                    $runtime = ElectionalEvaluator::calculatePanchakaDosha((int) $ctx['panchanga']['current_tithi']['index'], (int) $ctx['panchanga']['vara']['index'], $ctx['panchanga']['current_nak_index'] + 1, AstroCore::getSign($ascLon) + 1);

                    return [
                        ...$runtime,
                        'is_auspicious' => !$runtime['has_dosha'],
                        'calculated_for' => 'input_now',
                        'input_now_iso' => AstroCore::formatDateTime($ctx['time']['calculation_at']),
                        'at_sunrise' => [
                            ...$atSunrise,
                            'calculated_for' => 'sunrise',
                            'sunrise_iso' => AstroCore::formatDateTime($ctx['time']['rel_sunrise']),
                            'tithi' => (int) $ctx['panchanga']['tithi']['index'],
                            'tithi_name' => Tithi::from((int) $ctx['panchanga']['tithi']['index'])->getName(),
                            'nakshatra' => $ctx['panchanga']['nak_index'] + 1,
                            'nakshatra_name' => Nakshatra::from($ctx['panchanga']['nak_index'] % 27)->getName(),
                        ],
                    ];
                })(),
                'Vara_Tithi_Doshas' => (function () use (&$ctx, $ensurePanchanga): array {
                    $ensurePanchanga();
                    return ElectionalEvaluator::calculateVaraTithiDoshas((int) $ctx['panchanga']['vara']['index'], (int) $ctx['panchanga']['current_tithi']['index']);
                })(),
                'Tithi_Observance_Analysis' => (function () use (&$ctx, $ensureCrossings, $ensureJds, $ensurePanchanga, $ensureTimeContext, $lat, $lon, $tz): array {
                    $ensureCrossings(); $ensureJds(); $ensurePanchanga(); $ensureTimeContext();
                    $kalaEngine = new KalaNirnayaEngine($lat, $lon);

                    return [
                        'rule_system' => 'smarta_udaya_tithi_structural_analysis',
                        'is_festival_specific_engine' => false,
                        'festival_specific_override_required' => true,
                        'current_at_input_now' => [
                            ...$this->buildTithiObservanceAnalysis($kalaEngine, (int) $ctx['panchanga']['current_tithi']['index'], $ctx['crossings']['current_tithi_start_jd'], $ctx['crossings']['current_tithi_end_jd'], $ctx['jds']['sunrise'], $ctx['jds']['next_sunrise'], $ctx['crossings']['current_tithi_start_jd'], $tz),
                            'calculated_for' => 'input_now',
                            'input_now_iso' => AstroCore::formatDateTime($ctx['time']['calculation_at']),
                        ],
                        'at_sunrise' => [
                            ...$this->buildTithiObservanceAnalysis($kalaEngine, (int) $ctx['panchanga']['tithi']['index'], $ctx['crossings']['tithi_start_jd'], $ctx['crossings']['tithi_end_jd'], $ctx['jds']['sunrise'], $ctx['jds']['next_sunrise'], $ctx['crossings']['tithi_start_jd'], $tz),
                            'calculated_for' => 'sunrise',
                            'sunrise_iso' => AstroCore::formatDateTime($ctx['time']['rel_sunrise']),
                        ],
                    ];
                })(),
                'Vrata_Parana' => (function () use (&$ctx, $ensureCrossings, $ensureJds, $ensurePanchanga, $ensureTimeContext, $ensurePeriods, $ensureBasic, $tz): array {
                    $ensureCrossings(); $ensureJds(); $ensurePanchanga(); $ensureTimeContext(); $ensurePeriods(); $ensureBasic();
                    return [
                        'rule_system' => 'supported_non_ekadashi_vrata_parana_profiles',
                        'is_complete_system' => false,
                        'supported_families' => $this->supportedVrataParanaFamilies(),
                        'current_at_input_now' => $this->buildVrataParanaProfile((int) $ctx['panchanga']['current_tithi']['index'], (string) ($ctx['panchanga']['current_tithi']['paksha'] ?? ''), $ctx['crossings']['current_tithi_start_jd'], $ctx['crossings']['current_tithi_end_jd'], $ctx['jds']['sunrise'], $ctx['jds']['sunset'], $ctx['jds']['next_sunrise'], $ctx['basic']['Panchanga']['Moonrise']['jd'], $ctx['periods']['pradosha'], $ctx['periods']['nishitha'], $tz),
                        'at_sunrise' => $this->buildVrataParanaProfile((int) $ctx['panchanga']['tithi']['index'], (string) ($ctx['panchanga']['tithi']['paksha'] ?? ''), $ctx['crossings']['tithi_start_jd'], $ctx['crossings']['tithi_end_jd'], $ctx['jds']['sunrise'], $ctx['jds']['sunset'], $ctx['jds']['next_sunrise'], $ctx['basic']['Panchanga']['Moonrise']['jd'], $ctx['periods']['pradosha'], $ctx['periods']['nishitha'], $tz),
                    ];
                })(),
                'Hora_Full_Day' => (function () use (&$ctx, $ensureTimeContext, $ensurePanchanga): array {
                    $ensureTimeContext(); $ensurePanchanga();
                    return $this->muhurta->calculateHoraTable($ctx['time']['rel_sunrise'], $ctx['sun']['sunset'], $ctx['sun']['next_sunrise'], (int) $ctx['panchanga']['vara']['index']);
                })(),
                'Chogadiya_Full_Day' => (function () use (&$ctx, $ensureTimeContext, $ensurePanchanga): array {
                    $ensureTimeContext(); $ensurePanchanga();
                    return $this->muhurta->calculateChogadiyaTable($ctx['time']['rel_sunrise'], $ctx['sun']['sunset'], $ctx['sun']['next_sunrise'], (int) $ctx['panchanga']['vara']['index']);
                })(),
                'Muhurta_Full_Day' => (function () use (&$ctx, $ensureTimeContext): array {
                    $ensureTimeContext();
                    return $this->muhurta->calculateMuhurtaTable($ctx['time']['rel_sunrise'], $ctx['sun']['sunset'], $ctx['sun']['next_sunrise']);
                })(),
                'Lagna_Full_Day' => (function () use (&$ctx, $ensureLagnaTable): array {
                    $ensureLagnaTable();
                    return $ctx['lagna_table'];
                })(),
                'Rahu_Kaal_Gulika_Yamaganda' => (function () use (&$ctx, $ensureTimeContext, $ensurePanchanga): array {
                    $ensureTimeContext(); $ensurePanchanga();
                    return $this->muhurta->calculateBadTimes($ctx['time']['rel_sunrise'], $ctx['sun']['sunset'], (int) $ctx['panchanga']['vara']['index']);
                })(),
                'Abhijit_Muhurta' => (function () use (&$ctx, $ensureTimeContext): array {
                    $ensureTimeContext();
                    return $this->muhurta->calculateAbhijitMuhurta($ctx['time']['rel_sunrise'], $ctx['sun']['sunset']);
                })(),
                'Prahara_Full_Day' => (function () use (&$ctx, $ensureTimeContext): array {
                    $ensureTimeContext();
                    return $this->muhurta->calculatePrahara($ctx['time']['rel_sunrise'], $ctx['sun']['sunset'], $ctx['sun']['next_sunrise']);
                })(),
                'Daylight_Fivefold_Division' => (function () use (&$ctx, $ensureTimeContext): array {
                    $ensureTimeContext();
                    return $this->muhurta->calculateDaylightFivefoldDivision($ctx['time']['rel_sunrise'], $ctx['sun']['sunset']);
                })(),
                'Brahma_Muhurta' => (function () use (&$ctx, $ensureTimeContext): array {
                    $ensureTimeContext();
                    return $this->muhurta->calculateBrahmaMuhurta($ctx['sun']['previous_sunset'], $ctx['time']['rel_sunrise']);
                })(),
                'Dur_Muhurta_Full_Day' => (function () use (&$ctx, $ensureTimeContext, $ensurePanchanga): array {
                    $ensureTimeContext(); $ensurePanchanga();
                    return $this->muhurta->calculateDurMuhurta($ctx['time']['rel_sunrise'], $ctx['sun']['sunset'], $ctx['sun']['next_sunrise'], (int) $ctx['panchanga']['vara']['index']);
                })(),
                'Nishita_Muhurta' => (function () use (&$ctx, $ensureTimeContext): array {
                    $ensureTimeContext();
                    return $this->muhurta->calculateNishitaMuhurta($ctx['sun']['sunset'], $ctx['sun']['next_sunrise']);
                })(),
                'Vijaya_Muhurta' => (function () use (&$ctx, $ensureTimeContext): array {
                    $ensureTimeContext();
                    return $this->muhurta->calculateVijayaMuhurta($ctx['time']['rel_sunrise'], $ctx['sun']['sunset']);
                })(),
                'Godhuli_Muhurta' => (function () use (&$ctx, $ensureTimeContext): array {
                    $ensureTimeContext();
                    return $this->muhurta->calculateGodhuliMuhurta($ctx['sun']['sunset'], $ctx['sun']['next_sunrise']);
                })(),
                'Sandhya' => (function () use (&$ctx, $ensureTimeContext, $birthBase): array {
                    $ensureTimeContext();
                    $sandhya = $this->muhurta->calculateSandhya($ctx['time']['rel_sunrise'], $ctx['sun']['sunset'], $ctx['sun']['next_sunrise'], $this->sunService->getSolarTransits($birthBase)['solar_noon']);
                    return [
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
                    ];
                })(),
                'Day_Night_Measures' => (function () use (&$ctx, $ensureTimeContext): array {
                    $ensureTimeContext();
                    return $this->buildDayNightMeasures($ctx['time']['rel_sunrise'], $ctx['sun']['sunset'], $ctx['sun']['next_sunrise']);
                })(),
                'Gowri_Panchangam' => (function () use (&$ctx, $ensureTimeContext, $ensurePanchanga): array {
                    $ensureTimeContext(); $ensurePanchanga();
                    return $this->muhurta->calculateGowriPanchangam($ctx['time']['rel_sunrise'], $ctx['sun']['sunset'], $ctx['sun']['next_sunrise'], (int) $ctx['panchanga']['vara']['index']);
                })(),
                'Kala_Vela' => (function () use (&$ctx, $ensureTimeContext, $ensurePanchanga): array {
                    $ensureTimeContext(); $ensurePanchanga();
                    return $this->muhurta->calculateKalaVela($ctx['time']['rel_sunrise'], $ctx['sun']['sunset'], $ctx['sun']['next_sunrise'], (int) $ctx['panchanga']['vara']['index']);
                })(),
                'Karmakala_Windows' => $this->buildSelectedKarmakalaWindows($ctx, $ensureTimeContext),
                'Varjyam' => (function () use (&$ctx, $ensureTimeContext, $ensureJds, $ensurePanchanga, $ensureCrossings): array {
                    $ensureTimeContext(); $ensureJds(); $ensurePanchanga(); $ensureCrossings();
                    return $this->buildVarjyamPayload($this->calculateVarjyamWindows($ctx['time']['rel_sunrise'], $ctx['sun']['sunset'], $ctx['sun']['next_sunrise'], $ctx['jds']['sunrise'], $ctx['jds']['next_sunrise'], $ctx['panchanga']['nak_index'], $ctx['crossings']['nakshatra_start_jd'], $ctx['crossings']['nakshatra_end_jd']));
                })(),
                'Nakshatra_Tyajya' => (function () use (&$ctx, $ensureTimeContext, $ensureJds, $ensurePanchanga, $ensureCrossings): array {
                    $ensureTimeContext(); $ensureJds(); $ensurePanchanga(); $ensureCrossings();
                    return $this->buildNakshatraTyajyaPayload($this->buildVarjyamPayload($this->calculateVarjyamWindows($ctx['time']['rel_sunrise'], $ctx['sun']['sunset'], $ctx['sun']['next_sunrise'], $ctx['jds']['sunrise'], $ctx['jds']['next_sunrise'], $ctx['panchanga']['nak_index'], $ctx['crossings']['nakshatra_start_jd'], $ctx['crossings']['nakshatra_end_jd'])));
                })(),
                'Amrita_Kaal' => (function () use (&$ctx, $ensureTimeContext, $ensureJds, $ensurePanchanga, $ensureCrossings): array {
                    $ensureTimeContext(); $ensureJds(); $ensurePanchanga(); $ensureCrossings();
                    return $this->buildAmritaKaalPayload($this->calculateAmritaKaalWindows($ctx['time']['rel_sunrise'], $ctx['jds']['sunrise'], $ctx['jds']['next_sunrise'], $ctx['panchanga']['nak_index'], $ctx['crossings']['nakshatra_start_jd'], $ctx['crossings']['nakshatra_end_jd']));
                })(),
                'Pradosha_Kaal' => (function () use (&$ctx, $ensureTimeContext, $ensureJds, $tz): array {
                    $ensureTimeContext(); $ensureJds();
                    return $this->calculatePradoshaKaal($ctx['sun']['sunset'], $ctx['jds']['sunset'], $tz);
                })(),
                'Bhadra' => (function () use (&$ctx, $ensureJds, $ensurePanchanga): array {
                    $ensureJds(); $ensurePanchanga();
                    return $this->findBhadraPeriods($ctx['jds']['sunrise'], $ctx['jds']['next_sunrise'], (int) $ctx['panchanga']['tithi']['index'], (string) ($ctx['panchanga']['tithi']['paksha'] ?? ''));
                })(),
                'Dharma_Sindhu' => (function () use (&$ctx, $ensureSankranti, $ensurePanchanga, $ensureCrossings, $ensureJds, $ensureHinduMonth, $ensureTimeContext, $tz, $lat, $lon, $buildShivaVaasa, $buildAgniVaasa, $buildYoginiVaasa): array {
                    $ensureSankranti(); $ensurePanchanga(); $ensureCrossings(); $ensureJds(); $ensureHinduMonth(); $ensureTimeContext();
                    $tithiNum = (int) $ctx['panchanga']['tithi']['index'];
                    $month = (string) ($ctx['hindu_month']['Month_Amanta_En'] ?? $ctx['hindu_month']['Month_Amanta'] ?? '');
                    $paksha = (string) ($ctx['panchanga']['tithi']['paksha'] ?? '');
                    $kalaEngine = new KalaNirnayaEngine($lat, $lon);
                    return array_filter([
                        'Punya_Kaal' => $ctx['sankranti']['punya_kaal'],
                        'Ekadashi_Observance' => $this->buildEkadashiObservance($tithiNum, $ctx['crossings']['tithi_start_jd'], $ctx['crossings']['tithi_end_jd'], $ctx['jds']['sunrise'], $ctx['jds']['sunset'], $ctx['jds']['next_sunrise'], $tz, $lat, $lon, $ctx['jds']['previous_sunrise'], $month, $paksha),
                        'Tithi_Observance_Analysis' => [
                            'rule_system' => 'smarta_udaya_tithi_structural_analysis',
                            'is_festival_specific_engine' => false,
                            'festival_specific_override_required' => true,
                            'current_at_input_now' => [
                                ...$this->buildTithiObservanceAnalysis($kalaEngine, (int) $ctx['panchanga']['current_tithi']['index'], $ctx['crossings']['current_tithi_start_jd'], $ctx['crossings']['current_tithi_end_jd'], $ctx['jds']['sunrise'], $ctx['jds']['next_sunrise'], $ctx['crossings']['current_tithi_start_jd'], $tz),
                                'calculated_for' => 'input_now',
                                'input_now_iso' => AstroCore::formatDateTime($ctx['time']['calculation_at']),
                            ],
                            'at_sunrise' => [
                                ...$this->buildTithiObservanceAnalysis($kalaEngine, (int) $ctx['panchanga']['tithi']['index'], $ctx['crossings']['tithi_start_jd'], $ctx['crossings']['tithi_end_jd'], $ctx['jds']['sunrise'], $ctx['jds']['next_sunrise'], $ctx['crossings']['tithi_start_jd'], $tz),
                                'calculated_for' => 'sunrise',
                                'sunrise_iso' => AstroCore::formatDateTime($ctx['time']['rel_sunrise']),
                            ],
                        ],
                        'Shiva_Vaasa' => $buildShivaVaasa(),
                        'Agni_Vaasa' => $buildAgniVaasa(),
                        'Yogini_Vaasa' => $buildYoginiVaasa(),
                    ], static fn (?array $v): bool => $v !== null);
                })(),
                default => throw new InvalidArgumentException('Unknown Panchang selected section: ' . $requestedSection),
            };
        }

        return $this->annotateTimeOnlyFieldsWithDateTime($result, $ctx['time']['rel_sunrise'] ?? $ctx['sun']['sunrise'] ?? $date->setTime(0, 0), $tz);
    }

    public function getBasicDetails(
        CarbonImmutable $date,
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0,
        ?CarbonImmutable $calculationAt = null,
        CalendarType|string $calendarType = CalendarType::Amanta,
        array $options = []
    ): array {
        return $this->getSelectedSectionValue($date, $lat, $lon, $tz, 'Basic_Details', $elevation, $calculationAt, $calendarType, $options);
    }

    public function getPanchanga(
        CarbonImmutable $date,
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0,
        ?CarbonImmutable $calculationAt = null,
        CalendarType|string $calendarType = CalendarType::Amanta,
        array $options = []
    ): array {
        return $this->getSelectedSectionValue($date, $lat, $lon, $tz, 'Panchanga', $elevation, $calculationAt, $calendarType, $options);
    }

    public function getSpecialYogas(
        CarbonImmutable $date,
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0,
        ?CarbonImmutable $calculationAt = null,
        CalendarType|string $calendarType = CalendarType::Amanta,
        array $options = []
    ): array {
        return $this->getSelectedSectionValue($date, $lat, $lon, $tz, 'Special_Yogas', $elevation, $calculationAt, $calendarType, $options);
    }

    public function getMuhurtaFullDay(
        CarbonImmutable $date,
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0,
        ?CarbonImmutable $calculationAt = null,
        CalendarType|string $calendarType = CalendarType::Amanta,
        array $options = []
    ): array {
        return $this->getSelectedSectionValue($date, $lat, $lon, $tz, 'Muhurta_Full_Day', $elevation, $calculationAt, $calendarType, $options);
    }

    public function getAbhijitMuhurta(
        CarbonImmutable $date,
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0,
        ?CarbonImmutable $calculationAt = null,
        CalendarType|string $calendarType = CalendarType::Amanta,
        array $options = []
    ): array {
        return $this->getSelectedSectionValue($date, $lat, $lon, $tz, 'Abhijit_Muhurta', $elevation, $calculationAt, $calendarType, $options);
    }

    public function getVarjyam(
        CarbonImmutable $date,
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0,
        ?CarbonImmutable $calculationAt = null,
        CalendarType|string $calendarType = CalendarType::Amanta,
        array $options = []
    ): array {
        return $this->getSelectedSectionValue($date, $lat, $lon, $tz, 'Varjyam', $elevation, $calculationAt, $calendarType, $options);
    }

    public function getDharmaSindhu(
        CarbonImmutable $date,
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0,
        ?CarbonImmutable $calculationAt = null,
        CalendarType|string $calendarType = CalendarType::Amanta,
        array $options = []
    ): array {
        return $this->getSelectedSectionValue($date, $lat, $lon, $tz, 'Dharma_Sindhu', $elevation, $calculationAt, $calendarType, $options);
    }

    public function getSection(
        CarbonImmutable $date,
        float $lat,
        float $lon,
        string $tz,
        string $section,
        float $elevation = 0.0,
        ?CarbonImmutable $calculationAt = null,
        CalendarType|string $calendarType = CalendarType::Amanta,
        array $options = []
    ): array {
        return $this->getSelectedSectionValue($date, $lat, $lon, $tz, $section, $elevation, $calculationAt, $calendarType, $options);
    }

    public function getTithi(
        CarbonImmutable $date,
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0,
        ?CarbonImmutable $calculationAt = null,
        CalendarType|string $calendarType = CalendarType::Amanta,
        array $options = []
    ): array {
        return $this->getPanchanga($date, $lat, $lon, $tz, $elevation, $calculationAt, $calendarType, $options)['Tithi'];
    }

    public function getCurrentTithi(
        CarbonImmutable $date,
        float $lat,
        float $lon,
        string $tz,
        ?CarbonImmutable $calculationAt = null,
        float $elevation = 0.0,
        CalendarType|string $calendarType = CalendarType::Amanta,
        array $options = []
    ): array {
        return $this->getPanchanga($date, $lat, $lon, $tz, $elevation, $calculationAt, $calendarType, $options)['Current_Tithi_At_Input_Now'];
    }

    public function getNakshatra(
        CarbonImmutable $date,
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0,
        ?CarbonImmutable $calculationAt = null,
        CalendarType|string $calendarType = CalendarType::Amanta,
        array $options = []
    ): array {
        return $this->getPanchanga($date, $lat, $lon, $tz, $elevation, $calculationAt, $calendarType, $options)['Nakshatra'];
    }

    public function getCurrentNakshatra(
        CarbonImmutable $date,
        float $lat,
        float $lon,
        string $tz,
        ?CarbonImmutable $calculationAt = null,
        float $elevation = 0.0,
        CalendarType|string $calendarType = CalendarType::Amanta,
        array $options = []
    ): array {
        return $this->getPanchanga($date, $lat, $lon, $tz, $elevation, $calculationAt, $calendarType, $options)['Current_Nakshatra_At_Input_Now'];
    }

    public function getYoga(
        CarbonImmutable $date,
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0,
        ?CarbonImmutable $calculationAt = null,
        CalendarType|string $calendarType = CalendarType::Amanta,
        array $options = []
    ): array {
        return $this->getPanchanga($date, $lat, $lon, $tz, $elevation, $calculationAt, $calendarType, $options)['Yoga'];
    }

    public function getCurrentYoga(
        CarbonImmutable $date,
        float $lat,
        float $lon,
        string $tz,
        ?CarbonImmutable $calculationAt = null,
        float $elevation = 0.0,
        CalendarType|string $calendarType = CalendarType::Amanta,
        array $options = []
    ): array {
        return $this->getPanchanga($date, $lat, $lon, $tz, $elevation, $calculationAt, $calendarType, $options)['Current_Yoga_At_Input_Now'];
    }

    public function getKarana(
        CarbonImmutable $date,
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0,
        ?CarbonImmutable $calculationAt = null,
        CalendarType|string $calendarType = CalendarType::Amanta,
        array $options = []
    ): array {
        return $this->getPanchanga($date, $lat, $lon, $tz, $elevation, $calculationAt, $calendarType, $options)['Karana'];
    }

    public function getCurrentKarana(
        CarbonImmutable $date,
        float $lat,
        float $lon,
        string $tz,
        ?CarbonImmutable $calculationAt = null,
        float $elevation = 0.0,
        CalendarType|string $calendarType = CalendarType::Amanta,
        array $options = []
    ): array {
        return $this->getPanchanga($date, $lat, $lon, $tz, $elevation, $calculationAt, $calendarType, $options)['Current_Karana_At_Input_Now'];
    }

    public function getVara(
        CarbonImmutable $date,
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0,
        ?CarbonImmutable $calculationAt = null,
        CalendarType|string $calendarType = CalendarType::Amanta,
        array $options = []
    ): array {
        return $this->getPanchanga($date, $lat, $lon, $tz, $elevation, $calculationAt, $calendarType, $options)['Vara'];
    }

    /**
     * Return specific dot-path fields without returning their whole sections.
     *
     * @param array<int, string> $fields
     *
     * @return array<string, mixed>
     */
    public function getFields(
        CarbonImmutable $date,
        float $lat,
        float $lon,
        string $tz,
        array $fields,
        float $elevation = 0.0,
        ?CarbonImmutable $calculationAt = null,
        CalendarType|string $calendarType = CalendarType::Amanta,
        array $options = []
    ): array {
        $sectionNames = [];
        foreach ($fields as $field) {
            $sectionNames[$this->sectionForFieldPath($field)] = true;
        }

        $selected = $this->getSelectedDetails($date, $lat, $lon, $tz, array_keys($sectionNames), $elevation, $calculationAt, $calendarType, $options);
        $result = [];

        foreach ($fields as $field) {
            $section = $this->sectionForFieldPath($field);
            $path = $this->pathWithinSelectedSection($field, $section);
            $source = $section === 'Basic_Details' ? $selected['Basic_Details'] : $selected[$section];
            $value = $this->valueAtDotPath($source, $path);
            $this->setValueAtDotPath($result, $field, $value);
        }

        return $result;
    }

    private function getSelectedSectionValue(
        CarbonImmutable $date,
        float $lat,
        float $lon,
        string $tz,
        string $section,
        float $elevation,
        ?CarbonImmutable $calculationAt,
        CalendarType|string $calendarType,
        array $options
    ): array {
        $normalizedSection = $this->normalizeSelectedSectionName($section);
        $selected = $this->getSelectedDetails($date, $lat, $lon, $tz, [$normalizedSection], $elevation, $calculationAt, $calendarType, $options);

        return $selected[$normalizedSection];
    }

    /** @param array<string, mixed> $ctx */
    private function buildSelectedKarmakalaWindows(array &$ctx, callable $ensureTimeContext): array
    {
        $ensureTimeContext();
        $daylightFivefold = $this->muhurta->calculateDaylightFivefoldDivision($ctx['time']['rel_sunrise'], $ctx['sun']['sunset']);
        $englishNames = ['pratah', 'sangava', 'madhyahna', 'aparahna', 'sayahna'];
        $daylightFivefoldByName = [];
        foreach ($daylightFivefold as $index => $division) {
            if (is_array($division) && isset($englishNames[$index])) {
                $daylightFivefoldByName[$englishNames[$index]] = $division;
            }
        }

        return [
            'sunrise' => [
                'label' => Localization::translate('String', 'Sunrise'),
                'type' => 'instant',
                'time' => AstroCore::formatTime($ctx['time']['rel_sunrise']),
                'time_iso' => AstroCore::formatDateTime($ctx['time']['rel_sunrise']),
            ],
            'pratah' => $daylightFivefoldByName['pratah'] ?? null,
            'sangava' => $daylightFivefoldByName['sangava'] ?? null,
            'madhyahna' => $daylightFivefoldByName['madhyahna'] ?? null,
            'aparahna' => $daylightFivefoldByName['aparahna'] ?? null,
            'sayahna' => $daylightFivefoldByName['sayahna'] ?? null,
            'pradosha' => $this->calculatePradoshaKaal(
                $ctx['sun']['sunset'],
                $this->toJulianDayFromCarbon($ctx['sun']['sunset'], (string) $ctx['time']['birth_at']['timezone']),
                (string) $ctx['time']['birth_at']['timezone']
            ),
            'nishitha' => $this->muhurta->calculateNishitaMuhurta($ctx['sun']['sunset'], $ctx['sun']['next_sunrise']),
            'brahma_muhurta' => $this->muhurta->calculateBrahmaMuhurta($ctx['sun']['previous_sunset'], $ctx['time']['rel_sunrise']),
            'abhijit' => $this->muhurta->calculateAbhijitMuhurta($ctx['time']['rel_sunrise'], $ctx['sun']['sunset']),
            'vijaya' => $this->muhurta->calculateVijayaMuhurta($ctx['time']['rel_sunrise'], $ctx['sun']['sunset']),
            'godhuli' => $this->muhurta->calculateGodhuliMuhurta($ctx['sun']['sunset'], $ctx['sun']['next_sunrise']),
        ];
    }

    private function sectionForFieldPath(string $field): string
    {
        $firstSegment = strtok($field, '.');
        $root = $this->normalizeSelectedSectionName($firstSegment !== false ? $firstSegment : $field);

        return match ($root) {
            'Panchanga',
            'Special_Yogas',
            'Anandadi_Yoga',
            'Amritadi_Yoga',
            'Nitya_Yoga_Observations',
            'Panchak',
            'Maitreya_Yoga',
            'Gajachchhaya_Yoga',
            'Nakshatra_Shool',
            'Disha_Shool',
            'Yatra_Screening',
            'Rahu_Vaasa',
            'Chandra_Vaasa',
            'Shiva_Vaasa',
            'Agni_Vaasa',
            'Yogini_Vaasa',
            'Panchaka_Rahita',
            'Vara_Tithi_Doshas',
            'Tithi_Observance_Analysis',
            'Vrata_Parana',
            'Hora_Full_Day',
            'Chogadiya_Full_Day',
            'Muhurta_Full_Day',
            'Lagna_Full_Day',
            'Rahu_Kaal_Gulika_Yamaganda',
            'Abhijit_Muhurta',
            'Prahara_Full_Day',
            'Daylight_Fivefold_Division',
            'Brahma_Muhurta',
            'Dur_Muhurta_Full_Day',
            'Nishita_Muhurta',
            'Vijaya_Muhurta',
            'Godhuli_Muhurta',
            'Sandhya',
            'Day_Night_Measures',
            'Gowri_Panchangam',
            'Kala_Vela',
            'Karmakala_Windows',
            'Varjyam',
            'Nakshatra_Tyajya',
            'Amrita_Kaal',
            'Pradosha_Kaal',
            'Bhadra',
            'Dharma_Sindhu' => $root,
            default => 'Basic_Details',
        };
    }

    private function pathWithinSelectedSection(string $field, string $section): string
    {
        if ($section === 'Basic_Details') {
            return $field;
        }

        $prefix = $section . '.';

        return str_starts_with($field, $prefix) ? substr($field, strlen($prefix)) : $field;
    }

    private function valueAtDotPath(array $source, string $path): mixed
    {
        $value = $source;
        foreach (explode('.', $path) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                throw new InvalidArgumentException('Unknown Panchang field path: ' . $path);
            }

            $value = $value[$part];
        }

        return $value;
    }

    private function setValueAtDotPath(array &$target, string $path, mixed $value): void
    {
        $cursor = &$target;
        foreach (explode('.', $path) as $part) {
            if (!isset($cursor[$part]) || !is_array($cursor[$part])) {
                $cursor[$part] = [];
            }

            $cursor = &$cursor[$part];
        }

        $cursor = $value;
    }

    private function normalizeSelectedSectionName(string $section): string
    {
        $trimmed = trim($section);
        $aliases = [
            'basic' => 'Basic_Details',
            'basic_details' => 'Basic_Details',
            'all_basic_details' => 'Basic_Details',
            'panchang' => 'Panchanga',
            'panchanga' => 'Panchanga',
            'panchang_details' => 'Panchanga',
        ];

        $key = strtolower(str_replace([' ', '-'], '_', $trimmed));

        return $aliases[$key] ?? $trimmed;
    }
}
