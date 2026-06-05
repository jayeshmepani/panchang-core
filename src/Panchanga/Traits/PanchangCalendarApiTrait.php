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
use JayeshMepani\PanchangCore\Core\Enums\Vara;
use JayeshMepani\PanchangCore\Core\Localization;
use JayeshMepani\PanchangCore\Festivals\FestivalService;
use JayeshMepani\PanchangCore\Panchanga\ElectionalEvaluator;

trait PanchangCalendarApiTrait
{
    public function getFestivalYearCalendar(
        int $year,
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0,
        ?CarbonImmutable $calculationAt = null,
        CalendarType|string $calendarType = CalendarType::Amanta
    ): array {
        return $this->buildFestivalYearCalendar($year, $lat, $lon, $tz, $elevation, $calculationAt, $calendarType, 'all');
    }

    public function getFestivalYearCalendarOnlyFestivals(
        int $year,
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0,
        ?CarbonImmutable $calculationAt = null,
        CalendarType|string $calendarType = CalendarType::Amanta
    ): array {
        return $this->buildFestivalYearCalendar($year, $lat, $lon, $tz, $elevation, $calculationAt, $calendarType, 'festivals');
    }

    public function getVratYearCalendar(
        int $year,
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0,
        ?CarbonImmutable $calculationAt = null,
        CalendarType|string $calendarType = CalendarType::Amanta
    ): array {
        return $this->buildFestivalYearCalendar($year, $lat, $lon, $tz, $elevation, $calculationAt, $calendarType, 'vrats');
    }

    /**
     * Get a month-wise calendar summary of Panchang details.
     * Ideal for grid-based calendar views.
     *
     * @return array<string, array<string, mixed>> Indexed by YYYY-MM-DD
     */
    public function getMonthCalendar(
        int $year,
        int $month,
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0,
        array $options = [],
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

        $start = CarbonImmutable::create($year, $month, 1, 0, 0, 0, $tz);
        $daysInMonth = $start->daysInMonth;

        $festivalScope = (string) ($options['festival_scope'] ?? 'year');
        if ($festivalScope === 'month') {
            $festivalsByDate = $this->getFestivalCalendarForDateRange(
                start: $start,
                end: $start->addDays($daysInMonth - 1),
                lat: $lat,
                lon: $lon,
                tz: $tz,
                elevation: $elevation,
                calculationAt: $calculationAt,
                calendarType: $calendarType,
            );
        } else {
            // Use the same year-level festival orchestration/consolidation pipeline
            // that powers scripts/panchang_festivals.php so month view never diverges.
            $yearFestivalCalendar = $this->getFestivalYearCalendar(
                year: $year,
                lat: $lat,
                lon: $lon,
                tz: $tz,
                elevation: $elevation,
                calculationAt: $calculationAt,
                calendarType: $calendarType,
            );
            $festivalsByDate = $yearFestivalCalendar['by_date'];
        }

        $snapshots = [];
        for ($i = -1; $i <= $daysInMonth; $i++) {
            $date = $start->addDays($i);
            $snapshots[$i] = $this->getFestivalSnapshot($date, $lat, $lon, $tz, $elevation, $calculationAt, $calendarType, false);
        }

        $calendar = [];
        for ($i = 0; $i < $daysInMonth; $i++) {
            $date = $start->addDays($i);
            $todaySnapshot = $snapshots[$i];

            $dateKey = $date->toDateString();
            $festivals = $festivalsByDate[$dateKey] ?? [];
            $dailyObservances = $this->festivalService->getDailyObservances($todaySnapshot);

            $sankranti = null;
            if ($todaySnapshot['Resolution_Context']['sankranti_rashi'] !== null) {
                $rashiIdx = $todaySnapshot['Resolution_Context']['sankranti_rashi'];
                $sankranti = Rasi::from($rashiIdx)->getName() . ' ' . Localization::translate('Common', 'Sankranti');
            }

            $tithiDisplay = $this->buildMonthCalendarTithiDisplay($todaySnapshot);

            $calendar[$date->toDateString()] = [
                'date' => $date->toDateString(),
                'day' => (int) $date->format('d'),
                'tithi' => $todaySnapshot['Tithi'],
                'tithi_display' => $tithiDisplay,
                'tithi_windows' => $todaySnapshot['Tithi_Windows'] ?? [],
                'nakshatra' => $todaySnapshot['Nakshatra'],
                'nakshatra_windows' => $todaySnapshot['Nakshatra_Windows'] ?? [],
                'nakshatra_padas' => $todaySnapshot['Nakshatra_Padas'] ?? [],
                'yoga' => $todaySnapshot['Yoga'],
                'yoga_windows' => $todaySnapshot['Yoga_Windows'] ?? [],
                'karana' => $todaySnapshot['Karana'],
                'karana_windows' => $todaySnapshot['Karana_Windows'] ?? [],
                'vara' => $todaySnapshot['Vara'],
                'sun_sign' => $todaySnapshot['Sun_Sign'],
                'moon_sign' => $todaySnapshot['Moon_Sign'],
                'moon_phase' => $todaySnapshot['Moon_Phase'] ?? null,
                'sunrise' => $todaySnapshot['Sunrise'],
                'sunset' => $todaySnapshot['Sunset'],
                'moonrise' => $todaySnapshot['Moonrise'],
                'moonset' => $todaySnapshot['Moonset'],
                'moonrise_date' => $todaySnapshot['Moonrise_Date'],
                'moonset_date' => $todaySnapshot['Moonset_Date'],
                'moonrise_iso' => $todaySnapshot['Moonrise_ISO'],
                'moonset_iso' => $todaySnapshot['Moonset_ISO'],
                'moonset_day_relation' => $todaySnapshot['Moonset_Day_Relation'],
                'moon_visibility' => [
                    'starts_at' => $todaySnapshot['Moonrise'],
                    'starts_on' => $todaySnapshot['Moonrise_Date'],
                    'starts_iso' => $todaySnapshot['Moonrise_ISO'],
                    'ends_at' => $todaySnapshot['Moonset'],
                    'ends_on' => $todaySnapshot['Moonset_Date'],
                    'ends_iso' => $todaySnapshot['Moonset_ISO'],
                    'ends_day_relation' => $todaySnapshot['Moonset_Day_Relation'],
                ],
                'hindu_calendar' => $todaySnapshot['Hindu_Calendar'],
                'festivals' => $festivals,
                'daily_observances' => $dailyObservances,
                'sankranti' => $sankranti,
            ];
        }

        return $calendar;
    }

    /**
     * Get selected month-calendar component groups per date.
     *
     * Examples:
     * - tithi => tithi, tithi_display, tithi_windows
     * - nakshatra => nakshatra, nakshatra_windows, nakshatra_padas
     * - festivals => festivals, daily_observances, sankranti
     *
     * @param array<int, string> $fields
     *
     * @return array<string, array<string, mixed>> Indexed by YYYY-MM-DD
     */
    public function getMonthFields(
        int $year,
        int $month,
        float $lat,
        float $lon,
        string $tz,
        array $fields,
        float $elevation = 0.0,
        array $options = [],
        ?CarbonImmutable $calculationAt = null,
        CalendarType|string $calendarType = CalendarType::Amanta
    ): array {
        if (is_string($calendarType)) {
            $calendarType = match (strtolower($calendarType)) {
                'purnimanta', 'purnimant' => CalendarType::Purnimanta,
                default => CalendarType::Amanta,
            };
        }

        $groups = [];
        foreach ($fields as $field) {
            $group = $this->normalizeMonthFieldGroup($field);
            $this->assertKnownMonthFieldGroup($group);
            $groups[$group] = true;
        }

        if ($groups === []) {
            return [];
        }

        $start = CarbonImmutable::create($year, $month, 1, 0, 0, 0, $tz);
        $daysInMonth = $start->daysInMonth;
        $needsFestivals = isset($groups['festivals']);
        $festivalsByDate = [];

        if ($needsFestivals) {
            $festivalScope = (string) ($options['festival_scope'] ?? 'year');
            if ($festivalScope === 'month') {
                $festivalsByDate = $this->getFestivalCalendarForDateRange(
                    start: $start,
                    end: $start->addDays($daysInMonth - 1),
                    lat: $lat,
                    lon: $lon,
                    tz: $tz,
                    elevation: $elevation,
                    calculationAt: $calculationAt,
                    calendarType: $calendarType,
                );
            } else {
                $yearFestivalCalendar = $this->getFestivalYearCalendar(
                    year: $year,
                    lat: $lat,
                    lon: $lon,
                    tz: $tz,
                    elevation: $elevation,
                    calculationAt: $calculationAt,
                    calendarType: $calendarType,
                );
                $festivalsByDate = $yearFestivalCalendar['by_date'];
            }
        }

        $calendar = [];
        for ($i = 0; $i < $daysInMonth; $i++) {
            $date = $start->addDays($i);
            $dateKey = $date->toDateString();
            $snapshot = $this->getFestivalSnapshot($date, $lat, $lon, $tz, $elevation, $calculationAt, $calendarType, false);

            $day = [
                'date' => $dateKey,
                'day' => (int) $date->format('d'),
            ];

            $day += $this->buildSelectedMonthCalendarFields($groups, $snapshot, $festivalsByDate[$dateKey] ?? []);

            $calendar[$dateKey] = $day;
        }

        return $calendar;
    }

    public function getElectionalSnapshot(
        CarbonImmutable $date,
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0,
        array $options = []
    ): array {
        $dayDetails = $this->getSelectedDetails($date, $lat, $lon, $tz, ['Basic_Details'], $elevation, null, CalendarType::Amanta, $options)['Basic_Details'];
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
            'transit_moorthy' => ElectionalEvaluator::calculateTransitMoorthy((string) ($dayDetails['Nakshatra']['name'] ?? '')),
            'planetary_states' => $planetaryStates,
            'sunrise_context' => [
                'sunrise_iso' => AstroCore::formatDateTime($sunrise),
                'sunset_iso' => $sunset instanceof CarbonImmutable ? AstroCore::formatDateTime($sunset) : null,
                'nakshatra_index' => $nakIndex,
                'moon_sign_index' => $moonSign,
                'lagna_sign' => Rasi::from(AstroCore::getSign($ascLongitude))->getName(),
                'lagna_degree_in_sign' => fmod(AstroCore::normalize($ascLongitude), 30.0),
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
        ?CarbonImmutable $currentAt = null,
        float $elevation = 0.0,
        array $options = []
    ): array {
        $at = $currentAt instanceof CarbonImmutable ? $currentAt->setTimezone($tz) : CarbonImmutable::now($tz);
        $selectedDetails = $this->getSelectedDetails(
            $date,
            $lat,
            $lon,
            $tz,
            ['Basic_Details', 'Bhadra', 'Varjyam', 'Amrita_Kaal'],
            $elevation,
            $at,
            CalendarType::Amanta,
            $options
        );
        $dayDetails = [
            ...$selectedDetails['Basic_Details'],
            'Bhadra' => $selectedDetails['Bhadra'],
            'Varjyam' => $selectedDetails['Varjyam'],
            'Amrita_Kaal' => $selectedDetails['Amrita_Kaal'],
        ];
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
            'panchaka_dosha' => ElectionalEvaluator::calculatePanchakaDosha($tithiNumber, $varaNumber, $nakshatraNumber, $lagnaNumber),
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
                Localization::translate('String', 'Amrita Kaal'),
                Localization::translate('Source', 'Classical Panchanga Calculation Texts')
            ),
            'abhijit_cancellation' => ElectionalEvaluator::calculateAbhijitCancellation($sunrise, $sunset, $varaNumber, $currentTime),
        ];

        $rejectionReport = ElectionalEvaluator::generateRejectionReport($evaluationResults);

        return [
            'title' => Localization::translate('String', 'Complete Muhurta Evaluation - Centralized package output'),
            'input_now' => AstroCore::formatDateTime($at),
            'date' => $date->toDateString(),
            'input_parameters' => [
                'tithi_number' => $tithiNumber,
                'tithi_name' => $tithiEnum->getName(),
                'tithi_number_base' => 1,
                'vara_number' => $varaNumber,
                'vara_name' => $varaEnum->getName(),
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
                'moon_sign_name' => $moonSignEnum->getName(),
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

    private function buildFestivalYearCalendar(
        int $year,
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0,
        ?CarbonImmutable $calculationAt = null,
        CalendarType|string $calendarType = CalendarType::Amanta,
        string $selection = 'all'
    ): array {
        if (is_string($calendarType)) {
            $calendarType = match (strtolower($calendarType)) {
                'purnimanta', 'purnimant' => CalendarType::Purnimanta,
                default => CalendarType::Amanta,
            };
        }

        /** @var array<string, array<int, array<string, mixed>>> $festivalsByDate */
        $festivalsByDate = [];
        /** @var array<int, array{date:string, festival:array<string, mixed>}> $festivalFlat */
        $festivalFlat = [];

        $start = CarbonImmutable::create($year, 1, 1, 0, 0, 0, $tz);
        $end = CarbonImmutable::create($year, 12, 31, 0, 0, 0, $tz);

        for ($date = $start; $date->lessThanOrEqualTo($end); $date = $date->addDay()) {
            $todaySnapshot = $this->getFestivalSnapshot($date, $lat, $lon, $tz, $elevation, $calculationAt, $calendarType, false);
            $tomorrowSnapshot = $this->getFestivalSnapshot($date->addDay(), $lat, $lon, $tz, $elevation, $calculationAt, $calendarType, false);
            $yesterdaySnapshot = $this->getFestivalSnapshot($date->subDay(), $lat, $lon, $tz, $elevation, $calculationAt, $calendarType, false);
            $festivals = $this->festivalService->resolveFestivalsForDate($date, $todaySnapshot, $tomorrowSnapshot, $yesterdaySnapshot, null, false, $selection);

            if ($festivals === []) {
                continue;
            }

            foreach ($festivals as $festival) {
                $dateKey = $this->festivalObservanceDate($festival, $date->toDateString());
                $festivalsByDate[$dateKey] ??= [];
                $festivalsByDate[$dateKey][] = $festival;
                $festivalFlat[] = [
                    'date' => $dateKey,
                    'festival' => $festival,
                ];
            }
        }

        $this->appendRelativeDayFestivals($year, $tz, $festivalsByDate, $festivalFlat, $selection);

        $festivalFlat = $this->consolidateAdjacentFestivalsByWinningScore($festivalFlat, $tz);
        $festivalFlat = $this->consolidateYearlySingleObservanceFestivals($festivalFlat);

        $festivalsByDate = [];
        foreach ($festivalFlat as $entry) {
            $dateKey = $entry['date'];
            $festivalsByDate[$dateKey] ??= [];
            $festivalsByDate[$dateKey][] = $entry['festival'];
        }

        ksort($festivalsByDate);

        return [
            'year' => $year,
            'festival_day_count' => count($festivalsByDate),
            'festival_entry_count' => count($festivalFlat),
            'by_date' => $festivalsByDate,
            'flat' => $festivalFlat,
        ];
    }

    /**
     * Build a calendar-cell-oriented Tithi summary.
     *
     * The sunrise tithi remains in `tithi`. This helper adds:
     * - compact display like `30/1`
     * - explicit multiple-tithi metadata
     * - explicit kshaya-tithi metadata when a tithi begins and ends between two sunrises
     *
     * @param array<string, mixed> $todaySnapshot
     *
     * @return array<string, mixed>
     */
    private function buildMonthCalendarTithiDisplay(array $todaySnapshot): array
    {
        $sunriseTithi = $todaySnapshot['Tithi'] ?? [];
        $windows = $todaySnapshot['Tithi_Windows'] ?? [];
        $sunriseJd = (float) ($todaySnapshot['Resolution_Context']['sunrise_jd'] ?? 0.0);
        $nextSunriseJd = (float) ($todaySnapshot['Resolution_Context']['next_sunrise_jd'] ?? 0.0);

        $sunriseIndex = (int) ($sunriseTithi['index'] ?? 0);
        $indexes = [$sunriseIndex];
        $phaseIndexes = [(($sunriseIndex - 1) % 15) + 1];
        $names = [(string) ($sunriseTithi['name'] ?? '')];
        $kshayaTithi = null;

        foreach ($windows as $window) {
            $windowIndex = (int) ($window['index'] ?? 0);
            if ($windowIndex === 0 || $windowIndex === $sunriseIndex) {
                continue;
            }

            $windowStartJd = (float) ($window['start_jd'] ?? 0.0);
            $windowEndJd = (float) ($window['end_jd'] ?? 0.0);
            if ($windowStartJd <= $sunriseJd) {
                continue;
            }

            if ($windowEndJd < $nextSunriseJd) {
                $paksha = $windowIndex > 15 ? 'Krishna' : 'Shukla';
                $indexes[] = $windowIndex;
                $phaseIndexes[] = (int) (($window['phase_index'] ?? ((($windowIndex - 1) % 15) + 1)));
                $names[] = (string) ($window['name'] ?? '');
                $kshayaTithi = [
                    'index' => $windowIndex,
                    'phase_index' => (int) (($window['phase_index'] ?? ((($windowIndex - 1) % 15) + 1))),
                    'name' => (string) ($window['name'] ?? ''),
                    'paksha' => $paksha,
                    'paksha_name' => Localization::translate(
                        'String',
                        $paksha === 'Shukla' ? 'Shukla Paksha (waxing)' : 'Krishna Paksha (waning)'
                    ),
                    'start_jd' => $windowStartJd,
                    'end_jd' => $windowEndJd,
                    'start_iso' => $window['start_iso'] ?? null,
                    'end_iso' => $window['end_iso'] ?? null,
                ];
            }
        }

        $displayParts = array_map(static fn (int $index): string => (string) $index, $indexes);

        return [
            'display' => implode('/', $displayParts),
            'indexes' => $indexes,
            'phase_indexes' => $phaseIndexes,
            'names' => $names,
            'has_multiple_tithis' => count($indexes) > 1,
            'has_kshaya_tithi' => $kshayaTithi !== null,
            'kshaya_tithi' => $kshayaTithi,
        ];
    }

    /**
     * @param array<string, bool> $groups
     * @param array<string, mixed> $snapshot
     * @param array<int, array<string, mixed>> $festivals
     *
     * @return array<string, mixed>
     */
    private function buildSelectedMonthCalendarFields(array $groups, array $snapshot, array $festivals): array
    {
        $fields = [];

        if (isset($groups['tithi'])) {
            $fields['tithi'] = $snapshot['Tithi'];
            $fields['tithi_display'] = $this->buildMonthCalendarTithiDisplay($snapshot);
            $fields['tithi_windows'] = $snapshot['Tithi_Windows'] ?? [];
        }

        if (isset($groups['nakshatra'])) {
            $fields['nakshatra'] = $snapshot['Nakshatra'];
            $fields['nakshatra_windows'] = $snapshot['Nakshatra_Windows'] ?? [];
            $fields['nakshatra_padas'] = $snapshot['Nakshatra_Padas'] ?? [];
        }

        if (isset($groups['yoga'])) {
            $fields['yoga'] = $snapshot['Yoga'];
            $fields['yoga_windows'] = $snapshot['Yoga_Windows'] ?? [];
        }

        if (isset($groups['karana'])) {
            $fields['karana'] = $snapshot['Karana'];
            $fields['karana_windows'] = $snapshot['Karana_Windows'] ?? [];
        }

        if (isset($groups['vara'])) {
            $fields['vara'] = $snapshot['Vara'];
        }

        if (isset($groups['sun'])) {
            $fields['sun_sign'] = $snapshot['Sun_Sign'];
        }

        if (isset($groups['moon'])) {
            $fields['moon_sign'] = $snapshot['Moon_Sign'];
            $fields['moon_phase'] = $snapshot['Moon_Phase'] ?? null;
        }

        if (isset($groups['sun'])) {
            $fields['sunrise'] = $snapshot['Sunrise'];
            $fields['sunset'] = $snapshot['Sunset'];
        }

        if (isset($groups['moon'])) {
            $fields['moonrise'] = $snapshot['Moonrise'];
            $fields['moonset'] = $snapshot['Moonset'];
            $fields['moonrise_date'] = $snapshot['Moonrise_Date'];
            $fields['moonset_date'] = $snapshot['Moonset_Date'];
            $fields['moonrise_iso'] = $snapshot['Moonrise_ISO'];
            $fields['moonset_iso'] = $snapshot['Moonset_ISO'];
            $fields['moonset_day_relation'] = $snapshot['Moonset_Day_Relation'];
            $fields['moon_visibility'] = [
                'starts_at' => $snapshot['Moonrise'],
                'starts_on' => $snapshot['Moonrise_Date'],
                'starts_iso' => $snapshot['Moonrise_ISO'],
                'ends_at' => $snapshot['Moonset'],
                'ends_on' => $snapshot['Moonset_Date'],
                'ends_iso' => $snapshot['Moonset_ISO'],
                'ends_day_relation' => $snapshot['Moonset_Day_Relation'],
            ];
        }

        if (isset($groups['hindu_calendar'])) {
            $fields['hindu_calendar'] = $snapshot['Hindu_Calendar'];
        }

        if (isset($groups['festivals'])) {
            $sankranti = null;
            if ($snapshot['Resolution_Context']['sankranti_rashi'] !== null) {
                $rashiIdx = $snapshot['Resolution_Context']['sankranti_rashi'];
                $sankranti = Rasi::from($rashiIdx)->getName() . ' ' . Localization::translate('Common', 'Sankranti');
            }

            $fields['festivals'] = $festivals;
            $fields['daily_observances'] = $this->festivalService->getDailyObservances($snapshot);
            $fields['sankranti'] = $sankranti;
        }

        return $fields;
    }

    private function normalizeMonthFieldGroup(string $field): string
    {
        $key = strtolower(str_replace([' ', '-'], '_', trim($field)));

        return match ($key) {
            'tithi', 'tithi_display', 'tithi_windows' => 'tithi',
            'nakshatra', 'nakshatra_windows', 'nakshatra_padas' => 'nakshatra',
            'yoga', 'yoga_windows' => 'yoga',
            'karana', 'karana_windows' => 'karana',
            'vara', 'weekday' => 'vara',
            'sun', 'solar', 'sunrise', 'sunset', 'sun_sign' => 'sun',
            'moon', 'lunar', 'moonrise', 'moonset', 'moon_sign', 'moon_phase', 'moon_visibility' => 'moon',
            'calendar', 'hindu_calendar', 'month' => 'hindu_calendar',
            'festival', 'festivals', 'daily_observances', 'sankranti' => 'festivals',
            default => $field,
        };
    }

    private function assertKnownMonthFieldGroup(string $group): void
    {
        match ($group) {
            'tithi',
            'nakshatra',
            'yoga',
            'karana',
            'vara',
            'sun',
            'moon',
            'hindu_calendar',
            'festivals' => true,
            default => throw new InvalidArgumentException('Unknown month field group: ' . $group),
        };
    }

    private function moonsetDayRelation(CarbonImmutable $date, ?CarbonImmutable $moonset): ?string
    {
        if (!$moonset instanceof CarbonImmutable) {
            return null;
        }

        return $moonset->isSameDay($date) ? 'same_day' : 'next_day';
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $festivalsByDate
     * @param array<int, array{date:string, festival:array<string, mixed>}> $festivalFlat
     */
    private function appendRelativeDayFestivals(
        int $year,
        string $tz,
        array &$festivalsByDate,
        array &$festivalFlat,
        string $selection = 'all'
    ): void {
        foreach (FestivalService::FESTIVALS as $festName => $rules) {
            if ((string) ($rules['type'] ?? '') !== 'day_after') {
                continue;
            }

            $isVrat = (bool) ($rules['fasting'] ?? false);
            if (($selection === 'vrats' && !$isVrat) || ($selection === 'festivals' && $isVrat)) {
                continue;
            }

            $parentName = (string) ($rules['parent_festival'] ?? '');
            $daysAfter = (int) ($rules['days_after'] ?? 1);
            if ($parentName === '') {
                continue;
            }

            $parentDisplayName = Localization::translate('Festival', $parentName);
            foreach ($festivalsByDate as $observanceDate => $observedFestivals) {
                foreach ($observedFestivals as $observedFestival) {
                    $rawObservedName = (string) ($observedFestival['resolution']['festival_name'] ?? '');
                    $displayObservedName = (string) ($observedFestival['name'] ?? '');
                    if ($rawObservedName !== $parentName && $displayObservedName !== $parentDisplayName) {
                        continue;
                    }

                    $relativeDate = CarbonImmutable::parse($observanceDate, $tz)->addDays($daysAfter);
                    if ($relativeDate->year !== $year) {
                        continue;
                    }

                    $relativeDateKey = $relativeDate->toDateString();
                    $observanceNoteTemplate = Localization::translate('String', 'observance_note_day_after');
                    $observanceNote = $observanceNoteTemplate !== 'observance_note_day_after'
                        ? sprintf($observanceNoteTemplate, $daysAfter, $parentDisplayName)
                        : 'Observed ' . $daysAfter . ' day(s) after ' . $parentDisplayName;

                    $festival = $this->festivalService->buildFestivalPayload($festName, $rules, [
                        'festival_name' => $festName,
                        'standard_date' => $relativeDateKey,
                        'observance_date' => $relativeDateKey,
                        'observance_note' => $observanceNote,
                        'decision' => [
                            'winning_reason' => 'day_after_parent_festival',
                            'parent_festival' => $parentName,
                            'parent_observance_date' => $observanceDate,
                            'days_after' => $daysAfter,
                            'winning_score' => 1000,
                        ],
                    ]);

                    $festivalsByDate[$relativeDateKey] ??= [];
                    $festivalsByDate[$relativeDateKey][] = $festival;
                    $festivalFlat[] = [
                        'date' => $relativeDateKey,
                        'festival' => $festival,
                    ];
                }
            }
        }
    }

    /**
     * For adjacent duplicate observances of the same festival, keep the
     * strongest rule-engine decision.
     *
     * @param array<int, array{date:string, festival:array<string, mixed>}> $festivalFlat
     *
     * @return array<int, array{date:string, festival:array<string, mixed>}>
     */
    private function consolidateAdjacentFestivalsByWinningScore(array $festivalFlat, string $tz): array
    {
        $grouped = [];
        foreach ($festivalFlat as $idx => $entry) {
            $name = (string) ($entry['festival']['resolution']['festival_name'] ?? $entry['festival']['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $grouped[$name][] = ['idx' => $idx, 'entry' => $entry];
        }

        $remove = [];
        foreach ($grouped as $items) {
            usort($items, static fn (array $a, array $b): int => strcmp($a['entry']['date'], $b['entry']['date']));

            $clusters = [];
            $current = [];
            $previousDate = null;

            foreach ($items as $item) {
                $date = CarbonImmutable::parse($item['entry']['date'], $tz);
                if (!$previousDate instanceof CarbonImmutable || $previousDate->diffInDays($date) <= 1) {
                    $current[] = $item;
                } else {
                    $clusters[] = $current;
                    $current = [$item];
                }

                $previousDate = $date;
            }

            $clusters[] = $current;

            foreach ($clusters as $cluster) {
                if (count($cluster) <= 1) {
                    continue;
                }

                $best = null;
                foreach ($cluster as $candidate) {
                    $festival = $candidate['entry']['festival'];
                    $rules = (array) ($festival['rules_applied'] ?? []);
                    $score = (int) ($rules['winning_score'] ?? -1);
                    $reason = (string) ($rules['winning_reason'] ?? '');
                    $date = $candidate['entry']['date'];
                    $vriddhiPreference = (string) ($rules['vriddhi_preference'] ?? $festival['resolution']['decision']['vriddhi_preference'] ?? '');

                    if ($score < 0) {
                        continue;
                    }

                    if ($best === null || $this->isStrongerFestivalDecision($score, $reason, $date, $vriddhiPreference, $best)) {
                        $best = [
                            'idx' => $candidate['idx'],
                            'score' => $score,
                            'reason' => $reason,
                            'date' => $date,
                            'vriddhi_preference' => $vriddhiPreference,
                        ];
                    }
                }

                if ($best === null) {
                    continue;
                }

                foreach ($cluster as $candidate) {
                    if ($candidate['idx'] !== $best['idx']) {
                        $remove[$candidate['idx']] = true;
                    }
                }
            }
        }

        if ($remove === []) {
            return $festivalFlat;
        }

        $filtered = [];
        foreach ($festivalFlat as $idx => $entry) {
            if (!isset($remove[$idx])) {
                $filtered[] = $entry;
            }
        }

        return $filtered;
    }

    /**
     * Keep a single best observance per year for configured festival names.
     * Useful for Adhika/Nija duplicate resolutions when observances are not adjacent dates.
     *
     * @param array<int, array{date:string, festival:array<string, mixed>}> $festivalFlat
     *
     * @return array<int, array{date:string, festival:array<string, mixed>}>
     */
    private function consolidateYearlySingleObservanceFestivals(array $festivalFlat): array
    {
        if ($festivalFlat === []) {
            return $festivalFlat;
        }

        $targets = array_flip(self::YEARLY_SINGLE_OBSERVANCE_FESTIVALS);
        $grouped = [];
        foreach ($festivalFlat as $idx => $entry) {
            $name = (string) ($entry['festival']['resolution']['festival_name'] ?? $entry['festival']['name'] ?? '');
            if ($name === '' || !isset($targets[$name])) {
                continue;
            }

            $grouped[$name][] = ['idx' => $idx, 'entry' => $entry];
        }

        $remove = [];
        foreach ($grouped as $items) {
            if (count($items) <= 1) {
                continue;
            }

            $best = null;
            foreach ($items as $candidate) {
                $festival = $candidate['entry']['festival'];
                $rules = (array) ($festival['rules_applied'] ?? []);
                $score = (int) ($rules['winning_score'] ?? -1);
                $reason = (string) ($rules['winning_reason'] ?? '');
                $date = $candidate['entry']['date'];

                $reasonRank = match ($reason) {
                    'target_at_karmakala' => 2,
                    'target_during_observance' => 1,
                    default => 0,
                };

                if ($best === null
                    || $score > $best['score']
                    || ($score === $best['score'] && $reasonRank > $best['reason_rank'])
                    || ($score === $best['score'] && $reasonRank === $best['reason_rank'] && strcmp($date, $best['date']) < 0)) {
                    $best = [
                        'idx' => $candidate['idx'],
                        'score' => $score,
                        'reason_rank' => $reasonRank,
                        'date' => $date,
                    ];
                }
            }

            foreach ($items as $candidate) {
                if ($candidate['idx'] !== $best['idx']) {
                    $remove[$candidate['idx']] = true;
                }
            }
        }

        if ($remove === []) {
            return $festivalFlat;
        }

        $filtered = [];
        foreach ($festivalFlat as $idx => $entry) {
            if (!isset($remove[$idx])) {
                $filtered[] = $entry;
            }
        }

        return $filtered;
    }

    /** @param array{score:int, reason:string, date:string, vriddhi_preference:string} $best */
    private function isStrongerFestivalDecision(int $score, string $reason, string $date, string $vriddhiPreference, array $best): bool
    {
        $reasonRank = static fn (string $r): int => match ($r) {
            'target_at_karmakala' => 2,
            'target_during_observance' => 1,
            default => 0,
        };

        $sameScore = $score === $best['score'];
        $sameReason = $reasonRank($reason) === $reasonRank($best['reason']);
        $sameVriddhiPreference = $vriddhiPreference !== ''
            && $vriddhiPreference === $best['vriddhi_preference'];

        if ($sameScore && $sameReason && $sameVriddhiPreference) {
            if ($vriddhiPreference === 'first') {
                return strcmp($date, $best['date']) < 0;
            }

            if ($vriddhiPreference === 'last') {
                return strcmp($date, $best['date']) > 0;
            }
        }

        return $score > $best['score']
            || ($score === $best['score'] && $reasonRank($reason) > $reasonRank($best['reason']))
            || ($score === $best['score']
                && $reasonRank($reason) === $reasonRank($best['reason'])
                && $vriddhiPreference === ''
                && strcmp($date, $best['date']) > 0);
    }

    /** @param array<string, mixed> $festival */
    private function festivalObservanceDate(array $festival, string $fallbackDate): string
    {
        $observanceDate = (string) ($festival['resolution']['observance_date'] ?? '');
        if ($observanceDate !== '') {
            return $observanceDate;
        }

        return $fallbackDate;
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function getFestivalCalendarForDateRange(
        CarbonImmutable $start,
        CarbonImmutable $end,
        float $lat,
        float $lon,
        string $tz,
        float $elevation,
        ?CarbonImmutable $calculationAt,
        CalendarType $calendarType
    ): array {
        /** @var array<string, array<int, array<string, mixed>>> $festivalsByDate */
        $festivalsByDate = [];
        /** @var array<int, array{date:string, festival:array<string, mixed>}> $festivalFlat */
        $festivalFlat = [];

        $rangeStart = $start->subDays(3);
        $rangeEnd = $end->addDays(3);

        for ($date = $rangeStart; $date->lessThanOrEqualTo($rangeEnd); $date = $date->addDay()) {
            $todaySnapshot = $this->getFestivalSnapshot($date, $lat, $lon, $tz, $elevation, $calculationAt, $calendarType, false);
            $tomorrowSnapshot = $this->getFestivalSnapshot($date->addDay(), $lat, $lon, $tz, $elevation, $calculationAt, $calendarType, false);
            $yesterdaySnapshot = $this->getFestivalSnapshot($date->subDay(), $lat, $lon, $tz, $elevation, $calculationAt, $calendarType, false);
            $festivals = $this->festivalService->resolveFestivalsForDate($date, $todaySnapshot, $tomorrowSnapshot, $yesterdaySnapshot);

            foreach ($festivals as $festival) {
                $dateKey = $this->festivalObservanceDate($festival, $date->toDateString());
                $festivalsByDate[$dateKey] ??= [];
                $festivalsByDate[$dateKey][] = $festival;
                $festivalFlat[] = [
                    'date' => $dateKey,
                    'festival' => $festival,
                ];
            }
        }

        $this->appendRelativeDayFestivals($start->year, $tz, $festivalsByDate, $festivalFlat);

        $festivalFlat = $this->consolidateAdjacentFestivalsByWinningScore($festivalFlat, $tz);
        $festivalFlat = $this->consolidateYearlySingleObservanceFestivals($festivalFlat);

        $filteredByDate = [];
        foreach ($festivalFlat as $entry) {
            $dateKey = $entry['date'];
            if ($dateKey < $start->toDateString() || $dateKey > $end->toDateString()) {
                continue;
            }

            $filteredByDate[$dateKey] ??= [];
            $filteredByDate[$dateKey][] = $entry['festival'];
        }

        ksort($filteredByDate);

        return $filteredByDate;
    }

    /**
     * @param array<int, array<string, mixed>> $festivals
     *
     * @return array<int, array<string, mixed>>
     */
    private function retainFestivalsForDate(array $festivals, string $dateKey): array
    {
        return array_values(array_filter(
            $festivals,
            fn (array $festival): bool => $this->festivalObservanceDate($festival, $dateKey) === $dateKey
        ));
    }
}
