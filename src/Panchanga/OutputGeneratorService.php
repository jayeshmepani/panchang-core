<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Panchanga;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use JayeshMepani\PanchangCore\Astronomy\EclipseService;
use JayeshMepani\PanchangCore\Core\Enums\CalendarType;
use JayeshMepani\PanchangCore\Core\Localization;

/**
 * Output Generator Service.
 *
 * Centralizes all output array building for festivals, eclipses, today's panchang,
 * and combined output. CLI scripts should call these methods and write the result to files.
 *
 * This keeps scripts thin — they only handle:
 * - Bootstrap (config, container, service instantiation)
 * - CLI argument parsing
 * - File I/O (json_encode + file_put_contents)
 */
class OutputGeneratorService
{
    public function __construct(
        private readonly PanchangService $panchangService,
        private readonly EclipseService $eclipseService,
    ) {
    }

    /**
     * Generate only selected festival output branches.
     *
     * @param array<int, string> $sections
     *
     * @return array<string, mixed>
     */
    public function generateFestivalsSelected(
        int $year,
        float $lat,
        float $lon,
        string $tz,
        array $sections,
        float $elevation = 0.0,
        CalendarType|string $calendarType = CalendarType::Amanta,
    ): array {
        if (is_string($calendarType)) {
            $calendarType = match (strtolower($calendarType)) {
                'purnimanta', 'purnimant' => CalendarType::Purnimanta,
                default => CalendarType::Amanta,
            };
        }

        $festivalCalendar = $this->panchangService->getFestivalYearCalendar(
            year: $year,
            lat: $lat,
            lon: $lon,
            tz: $tz,
            elevation: $elevation,
            calculationAt: null,
            calendarType: $calendarType,
        );

        return $this->selectFestivalCalendarSections($festivalCalendar, $sections);
    }

    public function generateFestivalByDate(
        int $year,
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0,
        CalendarType|string $calendarType = CalendarType::Amanta,
    ): array {
        return $this->generateFestivalsSelected($year, $lat, $lon, $tz, ['by_date'], $elevation, $calendarType);
    }

    /**
     * Generate only selected festival output branches for an exact month range.
     *
     * @param array<int, string> $sections
     *
     * @return array<string, mixed>
     */
    public function generateFestivalsRangeSelected(
        int $fromYear,
        int $fromMonth,
        int $toYear,
        int $toMonth,
        float $lat,
        float $lon,
        string $tz,
        array $sections,
        float $elevation = 0.0,
        CalendarType|string $calendarType = CalendarType::Amanta,
    ): array {
        if (is_string($calendarType)) {
            $calendarType = match (strtolower($calendarType)) {
                'purnimanta', 'purnimant' => CalendarType::Purnimanta,
                default => CalendarType::Amanta,
            };
        }

        $festivalCalendar = $this->panchangService->getFestivalRangeCalendar(
            fromYear: $fromYear,
            fromMonth: $fromMonth,
            toYear: $toYear,
            toMonth: $toMonth,
            lat: $lat,
            lon: $lon,
            tz: $tz,
            elevation: $elevation,
            calculationAt: null,
            calendarType: $calendarType,
        );

        return $this->selectFestivalCalendarSections($festivalCalendar, $sections);
    }

    public function generateFestivalFlat(
        int $year,
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0,
        CalendarType|string $calendarType = CalendarType::Amanta,
    ): array {
        return $this->generateFestivalsSelected($year, $lat, $lon, $tz, ['flat'], $elevation, $calendarType);
    }

    /**
     * Generate only selected non-vrat festival output branches.
     *
     * @param array<int, string> $sections
     *
     * @return array<string, mixed>
     */
    public function generateFestivalsOnlySelected(
        int $year,
        float $lat,
        float $lon,
        string $tz,
        array $sections,
        float $elevation = 0.0,
        CalendarType|string $calendarType = CalendarType::Amanta,
    ): array {
        if (is_string($calendarType)) {
            $calendarType = match (strtolower($calendarType)) {
                'purnimanta', 'purnimant' => CalendarType::Purnimanta,
                default => CalendarType::Amanta,
            };
        }

        $festivalCalendar = $this->panchangService->getFestivalYearCalendarOnlyFestivals(
            year: $year,
            lat: $lat,
            lon: $lon,
            tz: $tz,
            elevation: $elevation,
            calculationAt: null,
            calendarType: $calendarType,
        );

        return $this->selectFestivalCalendarSections($festivalCalendar, $sections);
    }

    /**
     * Generate only selected non-vrat festival output branches for an exact month range.
     *
     * @param array<int, string> $sections
     *
     * @return array<string, mixed>
     */
    public function generateFestivalsOnlyRangeSelected(
        int $fromYear,
        int $fromMonth,
        int $toYear,
        int $toMonth,
        float $lat,
        float $lon,
        string $tz,
        array $sections,
        float $elevation = 0.0,
        CalendarType|string $calendarType = CalendarType::Amanta,
    ): array {
        if (is_string($calendarType)) {
            $calendarType = match (strtolower($calendarType)) {
                'purnimanta', 'purnimant' => CalendarType::Purnimanta,
                default => CalendarType::Amanta,
            };
        }

        $festivalCalendar = $this->panchangService->getFestivalRangeCalendarOnlyFestivals(
            fromYear: $fromYear,
            fromMonth: $fromMonth,
            toYear: $toYear,
            toMonth: $toMonth,
            lat: $lat,
            lon: $lon,
            tz: $tz,
            elevation: $elevation,
            calculationAt: null,
            calendarType: $calendarType,
        );

        return $this->selectFestivalCalendarSections($festivalCalendar, $sections);
    }

    /**
     * Generate only selected vrat output branches.
     *
     * @param array<int, string> $sections
     *
     * @return array<string, mixed>
     */
    public function generateVratsSelected(
        int $year,
        float $lat,
        float $lon,
        string $tz,
        array $sections,
        float $elevation = 0.0,
        CalendarType|string $calendarType = CalendarType::Amanta,
    ): array {
        if (is_string($calendarType)) {
            $calendarType = match (strtolower($calendarType)) {
                'purnimanta', 'purnimant' => CalendarType::Purnimanta,
                default => CalendarType::Amanta,
            };
        }

        $vratCalendar = $this->panchangService->getVratYearCalendar(
            year: $year,
            lat: $lat,
            lon: $lon,
            tz: $tz,
            elevation: $elevation,
            calculationAt: null,
            calendarType: $calendarType,
        );

        return $this->selectFestivalCalendarSections($vratCalendar, $sections);
    }

    /**
     * Generate only selected vrat output branches for an exact month range.
     *
     * @param array<int, string> $sections
     *
     * @return array<string, mixed>
     */
    public function generateVratsRangeSelected(
        int $fromYear,
        int $fromMonth,
        int $toYear,
        int $toMonth,
        float $lat,
        float $lon,
        string $tz,
        array $sections,
        float $elevation = 0.0,
        CalendarType|string $calendarType = CalendarType::Amanta,
    ): array {
        if (is_string($calendarType)) {
            $calendarType = match (strtolower($calendarType)) {
                'purnimanta', 'purnimant' => CalendarType::Purnimanta,
                default => CalendarType::Amanta,
            };
        }

        $vratCalendar = $this->panchangService->getVratRangeCalendar(
            fromYear: $fromYear,
            fromMonth: $fromMonth,
            toYear: $toYear,
            toMonth: $toMonth,
            lat: $lat,
            lon: $lon,
            tz: $tz,
            elevation: $elevation,
            calculationAt: null,
            calendarType: $calendarType,
        );

        return $this->selectFestivalCalendarSections($vratCalendar, $sections);
    }

    public function generateFestivalOnlyByDate(
        int $year,
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0,
        CalendarType|string $calendarType = CalendarType::Amanta,
    ): array {
        return $this->generateFestivalsOnlySelected($year, $lat, $lon, $tz, ['by_date'], $elevation, $calendarType);
    }

    public function generateFestivalOnlyFlat(
        int $year,
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0,
        CalendarType|string $calendarType = CalendarType::Amanta,
    ): array {
        return $this->generateFestivalsOnlySelected($year, $lat, $lon, $tz, ['flat'], $elevation, $calendarType);
    }

    public function generateVratByDate(
        int $year,
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0,
        CalendarType|string $calendarType = CalendarType::Amanta,
    ): array {
        return $this->generateVratsSelected($year, $lat, $lon, $tz, ['by_date'], $elevation, $calendarType);
    }

    public function generateVratFlat(
        int $year,
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0,
        CalendarType|string $calendarType = CalendarType::Amanta,
    ): array {
        return $this->generateVratsSelected($year, $lat, $lon, $tz, ['flat'], $elevation, $calendarType);
    }

    /**
     * Generate festivals output array for a given year.
     *
     * @return array{festivals: array<string, mixed>}
     */
    public function generateFestivals(
        int $year,
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0,
        CalendarType|string $calendarType = CalendarType::Amanta,
    ): array {
        // Normalize calendar_type from string to enum
        if (is_string($calendarType)) {
            $calendarType = match (strtolower($calendarType)) {
                'purnimanta', 'purnimant' => CalendarType::Purnimanta,
                default => CalendarType::Amanta,
            };
        }

        $festivalCalendar = $this->panchangService->getFestivalYearCalendar(
            year: $year,
            lat: $lat,
            lon: $lon,
            tz: $tz,
            elevation: $elevation,
            calculationAt: null,
            calendarType: $calendarType,
        );

        return [
            'festivals' => [
                'title' => sprintf(Localization::translate('String', 'Festivals %d - All festivals for the entire year'), $year),
                'year' => $year,
                'calendar_type' => $calendarType->value,
                'festival_day_count' => $festivalCalendar['festival_day_count'],
                'festival_entry_count' => $festivalCalendar['festival_entry_count'],
                'total_festivals' => $this->countUniqueIdentities($festivalCalendar['by_date'], false),
                'total_vrats' => $this->countUniqueIdentities($festivalCalendar['by_date'], true),
                'by_date' => $festivalCalendar['by_date'],
                'flat' => $festivalCalendar['flat'],
            ],
        ];
    }

    /**
     * Generate non-vrat festival output array for a given year.
     *
     * @return array{festivals: array<string, mixed>}
     */
    public function generateFestivalsOnly(
        int $year,
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0,
        CalendarType|string $calendarType = CalendarType::Amanta,
    ): array {
        if (is_string($calendarType)) {
            $calendarType = match (strtolower($calendarType)) {
                'purnimanta', 'purnimant' => CalendarType::Purnimanta,
                default => CalendarType::Amanta,
            };
        }

        $festivalCalendar = $this->panchangService->getFestivalYearCalendarOnlyFestivals(
            year: $year,
            lat: $lat,
            lon: $lon,
            tz: $tz,
            elevation: $elevation,
            calculationAt: null,
            calendarType: $calendarType,
        );

        return [
            'festivals' => [
                'title' => sprintf(Localization::translate('String', 'Festivals %d - Named festivals excluding vrat observances'), $year),
                'year' => $year,
                'calendar_type' => $calendarType->value,
                'festival_day_count' => $festivalCalendar['festival_day_count'],
                'festival_entry_count' => $festivalCalendar['festival_entry_count'],
                'total_festivals' => $this->countUniqueIdentities($festivalCalendar['by_date']),
                'by_date' => $festivalCalendar['by_date'],
                'flat' => $festivalCalendar['flat'],
            ],
        ];
    }

    /**
     * Generate vrat-only output array for a given year.
     *
     * @return array{vrats: array<string, mixed>}
     */
    public function generateVrats(
        int $year,
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0,
        CalendarType|string $calendarType = CalendarType::Amanta,
    ): array {
        if (is_string($calendarType)) {
            $calendarType = match (strtolower($calendarType)) {
                'purnimanta', 'purnimant' => CalendarType::Purnimanta,
                default => CalendarType::Amanta,
            };
        }

        $vratCalendar = $this->panchangService->getVratYearCalendar(
            year: $year,
            lat: $lat,
            lon: $lon,
            tz: $tz,
            elevation: $elevation,
            calculationAt: null,
            calendarType: $calendarType,
        );

        return [
            'vrats' => [
                'title' => sprintf(Localization::translate('String', 'Vrats %d - All vrat observances for the entire year'), $year),
                'year' => $year,
                'calendar_type' => $calendarType->value,
                'vrat_day_count' => $vratCalendar['festival_day_count'],
                'vrat_entry_count' => $vratCalendar['festival_entry_count'],
                'total_vrats' => $this->countUniqueIdentities($vratCalendar['by_date']),
                'by_date' => $vratCalendar['by_date'],
                'flat' => $vratCalendar['flat'],
            ],
        ];
    }

    /**
     * Generate compact vrat-only output with weekday-recurring vrats extracted out of by-date storage.
     *
     * @return array{vrats: array<string, mixed>}
     */
    public function generateVratsByDateCompact(
        int $year,
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0,
        CalendarType|string $calendarType = CalendarType::Amanta,
    ): array {
        if (is_string($calendarType)) {
            $calendarType = match (strtolower($calendarType)) {
                'purnimanta', 'purnimant' => CalendarType::Purnimanta,
                default => CalendarType::Amanta,
            };
        }

        $vratCalendar = $this->generateVratsSelected(
            $year,
            $lat,
            $lon,
            $tz,
            ['by_date', 'festival_day_count', 'festival_entry_count'],
            $elevation,
            $calendarType,
        );

        $compacted = $this->extractRecurringWeekdayVrats($vratCalendar['by_date']);

        return [
            'vrats' => [
                'title' => sprintf(Localization::translate('String', 'Vrats %d - All vrat observances for the entire year'), $year),
                'year' => $year,
                'calendar_type' => $calendarType->value,
                'vrat_day_count' => $compacted['dated_day_count'],
                'vrat_entry_count' => $compacted['dated_entry_count'],
                'total_vrats' => $this->countUniqueIdentities($compacted['by_date'], null, $compacted['recurring_weekday_vrats']),
                'recurring_weekday_vrats' => $compacted['recurring_weekday_vrats'],
                'by_date' => $compacted['by_date'],
            ],
        ];
    }

    /**
     * Generate only selected eclipse output branches.
     *
     * @param array<int, string> $sections
     *
     * @return array<string, mixed>
     */
    public function generateEclipsesSelected(
        int $startYear,
        int $endYear,
        float $lat,
        float $lon,
        string $tz,
        array $sections,
    ): array {
        $eclipsesByYear = [];
        $eclipsesFlat = [];

        for ($year = $startYear; $year <= $endYear; $year++) {
            $events = $this->eclipseService->getEclipsesForYear($year, $lat, $lon, $tz);
            $eclipsesByYear[(string) $year] = $events;

            foreach ($events as $event) {
                $eclipsesFlat[] = $event;
            }
        }

        $result = [];
        foreach ($sections as $section) {
            $normalized = $this->normalizeEclipseSection($section);
            $result[$normalized] = match ($normalized) {
                'by_year' => $eclipsesByYear,
                'flat' => $eclipsesFlat,
                'total_eclipse_count' => count($eclipsesFlat),
                default => throw new InvalidArgumentException('Unknown eclipse output section: ' . $section),
            };
        }

        return $result;
    }

    /**
     * Generate only selected eclipse output branches for an exact month range.
     *
     * @param array<int, string> $sections
     *
     * @return array<string, mixed>
     */
    public function generateEclipsesRangeSelected(
        int $fromYear,
        int $fromMonth,
        int $toYear,
        int $toMonth,
        float $lat,
        float $lon,
        string $tz,
        array $sections,
    ): array {
        [$start, $endExclusive] = $this->resolveMonthRangeBounds($fromYear, $fromMonth, $toYear, $toMonth, $tz);
        $events = $this->eclipseService->getEclipsesForDateRange($start, $endExclusive, $lat, $lon, $tz);

        $eclipsesByYear = [];
        foreach ($events as $event) {
            $year = substr((string) ($event['date'] ?? ''), 0, 4);
            if ($year === '') {
                continue;
            }

            $eclipsesByYear[$year] ??= [];
            $eclipsesByYear[$year][] = $event;
        }

        $result = [];
        foreach ($sections as $section) {
            $normalized = $this->normalizeEclipseSection($section);
            $result[$normalized] = match ($normalized) {
                'by_year' => $eclipsesByYear,
                'flat' => $events,
                'total_eclipse_count' => count($events),
                default => throw new InvalidArgumentException('Unknown eclipse output section: ' . $section),
            };
        }

        return $result;
    }

    public function generateEclipseByYear(
        int $startYear,
        int $endYear,
        float $lat,
        float $lon,
        string $tz,
    ): array {
        return $this->generateEclipsesSelected($startYear, $endYear, $lat, $lon, $tz, ['by_year']);
    }

    public function generateEclipseFlat(
        int $startYear,
        int $endYear,
        float $lat,
        float $lon,
        string $tz,
    ): array {
        return $this->generateEclipsesSelected($startYear, $endYear, $lat, $lon, $tz, ['flat']);
    }

    /**
     * Generate eclipses output array for a year range.
     *
     * @return array{eclipses: array<string, mixed>}
     */
    public function generateEclipses(
        int $startYear,
        int $endYear,
        float $lat,
        float $lon,
        string $tz,
    ): array {
        $eclipsesByYear = [];
        $eclipsesFlat = [];

        for ($year = $startYear; $year <= $endYear; $year++) {
            $events = $this->eclipseService->getEclipsesForYear($year, $lat, $lon, $tz);
            $eclipsesByYear[(string) $year] = $events;

            foreach ($events as $event) {
                $eclipsesFlat[] = $event;
            }
        }

        return [
            'eclipses' => [
                'title' => sprintf(
                    Localization::translate('String', 'Eclipses %d-%d - All eclipses for %d years'),
                    $startYear,
                    $endYear,
                    $endYear - $startYear + 1
                ),
                'from_year' => $startYear,
                'to_year' => $endYear,
                'total_eclipse_count' => count($eclipsesFlat),
                'by_year' => $eclipsesByYear,
                'flat' => $eclipsesFlat,
            ],
        ];
    }

    /**
     * Generate only selected month output branches.
     *
     * @param array<int, string> $sections
     * @param array<int, string> $calendarFields
     *
     * @return array<string, mixed>
     */
    public function generateMonthSelected(
        int $year,
        int $month,
        float $lat,
        float $lon,
        string $tz,
        array $sections,
        array $calendarFields,
        float $elevation = 0.0,
        array $options = [],
        ?CarbonImmutable $calculationAt = null,
        CalendarType|string $calendarType = CalendarType::Amanta,
    ): array {
        if (is_string($calendarType)) {
            $calendarType = match (strtolower($calendarType)) {
                'purnimanta', 'purnimant' => CalendarType::Purnimanta,
                default => CalendarType::Amanta,
            };
        }

        $result = [];
        foreach ($sections as $section) {
            $normalized = $this->normalizeMonthSection($section);
            $result[$normalized] = match ($normalized) {
                'meta' => $this->buildMonthOutputMeta($year, $month, $lat, $lon, $tz, $calendarType),
                'calendar' => $this->panchangService->getMonthFields(
                    year: $year,
                    month: $month,
                    lat: $lat,
                    lon: $lon,
                    tz: $tz,
                    fields: $calendarFields,
                    elevation: $elevation,
                    options: $options,
                    calculationAt: $calculationAt,
                    calendarType: $calendarType,
                ),
                default => throw new InvalidArgumentException('Unknown month output section: ' . $section),
            };
        }

        return $result;
    }

    /**
     * @param array<int, string> $calendarFields
     *
     * @return array{calendar: array<string, array<string, mixed>>}
     */
    public function generateMonthCalendarFields(
        int $year,
        int $month,
        float $lat,
        float $lon,
        string $tz,
        array $calendarFields,
        float $elevation = 0.0,
        array $options = [],
        ?CarbonImmutable $calculationAt = null,
        CalendarType|string $calendarType = CalendarType::Amanta,
    ): array {
        return $this->generateMonthSelected(
            $year,
            $month,
            $lat,
            $lon,
            $tz,
            ['calendar'],
            $calendarFields,
            $elevation,
            $options,
            $calculationAt,
            $calendarType
        );
    }

    /**
     * Generate selected month output branches for an exact month range.
     *
     * @param array<int, string> $sections
     * @param array<int, string> $calendarFields
     *
     * @return array<string, mixed>
     */
    public function generateMonthRangeSelected(
        int $fromYear,
        int $fromMonth,
        int $toYear,
        int $toMonth,
        float $lat,
        float $lon,
        string $tz,
        array $sections,
        array $calendarFields,
        float $elevation = 0.0,
        array $options = [],
        ?CarbonImmutable $calculationAt = null,
        CalendarType|string $calendarType = CalendarType::Amanta,
    ): array {
        if (is_string($calendarType)) {
            $calendarType = match (strtolower($calendarType)) {
                'purnimanta', 'purnimant' => CalendarType::Purnimanta,
                default => CalendarType::Amanta,
            };
        }

        [$start, $endInclusive] = $this->resolveMonthRangeBounds($fromYear, $fromMonth, $toYear, $toMonth, $tz, true);
        $result = [];

        foreach ($sections as $section) {
            $normalized = $this->normalizeMonthRangeSection($section);
            $result[$normalized] = match ($normalized) {
                'meta' => $this->buildMonthRangeOutputMeta($start, $endInclusive, $lat, $lon, $tz, $calendarType),
                'months' => $this->panchangService->getMonthRangeFields(
                    fromYear: $fromYear,
                    fromMonth: $fromMonth,
                    toYear: $toYear,
                    toMonth: $toMonth,
                    lat: $lat,
                    lon: $lon,
                    tz: $tz,
                    fields: $calendarFields,
                    elevation: $elevation,
                    options: $options,
                    calculationAt: $calculationAt,
                    calendarType: $calendarType,
                ),
                default => throw new InvalidArgumentException('Unknown month range output section: ' . $section),
            };
        }

        return $result;
    }

    /**
     * @param array<int, string> $calendarFields
     *
     * @return array{months: array<string, array<string, array<string, mixed>>>}
     */
    public function generateMonthRangeCalendarFields(
        int $fromYear,
        int $fromMonth,
        int $toYear,
        int $toMonth,
        float $lat,
        float $lon,
        string $tz,
        array $calendarFields,
        float $elevation = 0.0,
        array $options = [],
        ?CarbonImmutable $calculationAt = null,
        CalendarType|string $calendarType = CalendarType::Amanta,
    ): array {
        return $this->generateMonthRangeSelected(
            $fromYear,
            $fromMonth,
            $toYear,
            $toMonth,
            $lat,
            $lon,
            $tz,
            ['months'],
            $calendarFields,
            $elevation,
            $options,
            $calculationAt,
            $calendarType
        );
    }

    /**
     * Generate only selected today output branches.
     *
     * @param array<int, string> $sections
     * @param array<int, string> $detailSections
     *
     * @return array<string, mixed>
     */
    public function generateTodaySelected(
        float $lat,
        float $lon,
        string $tz,
        array $sections,
        array $detailSections = ['Basic_Details'],
        float $elevation = 0.0,
        CalendarType|string $calendarType = CalendarType::Amanta,
    ): array {
        if (is_string($calendarType)) {
            $calendarType = match (strtolower($calendarType)) {
                'purnimanta', 'purnimant' => CalendarType::Purnimanta,
                default => CalendarType::Amanta,
            };
        }

        $now = CarbonImmutable::now($tz);
        $todayDate = $now->startOfDay();
        $result = [];

        foreach ($sections as $section) {
            $normalized = $this->normalizeTodaySection($section);
            $result[$normalized] = match ($normalized) {
                'todays_complete_details' => [
                    'title' => Localization::translate('String', "Today's Complete Details - Every single data point from the package"),
                    'input_now' => $now->toIso8601String(),
                    'date' => $todayDate->toDateString(),
                    'details' => $this->panchangService->getSelectedDetails(
                        date: $todayDate,
                        lat: $lat,
                        lon: $lon,
                        tz: $tz,
                        sections: $detailSections,
                        elevation: $elevation,
                        calculationAt: $now,
                        calendarType: $calendarType,
                    ),
                ],
                'muhurta_evaluation' => array_merge(
                    [
                        'scope' => 'transit_only',
                        'notes' => [
                            'No natal or person-specific inputs are used.',
                            'Evaluation is derived only from current Panchang and transit state for the configured location/time.',
                        ],
                    ],
                    $this->panchangService->getDailyMuhurtaEvaluation(
                        date: $todayDate,
                        lat: $lat,
                        lon: $lon,
                        tz: $tz,
                        currentAt: $now,
                        elevation: $elevation,
                    )
                ),
                default => throw new InvalidArgumentException('Unknown today output section: ' . $section),
            };
        }

        return $result;
    }

    /**
     * Generate today's panchang output array.
     *
     * @return array{todays_complete_details: array<string, mixed>, muhurta_evaluation: array<string, mixed>}
     */
    public function generateTodayPanchang(
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0,
        CalendarType|string $calendarType = CalendarType::Amanta,
    ): array {
        // Normalize calendar_type
        if (is_string($calendarType)) {
            $calendarType = match (strtolower($calendarType)) {
                'purnimanta', 'purnimant' => CalendarType::Purnimanta,
                default => CalendarType::Amanta,
            };
        }

        $now = CarbonImmutable::now($tz);
        $todayDate = $now->startOfDay();

        // Calculate today's panchang at CURRENT time (not sunrise)
        // so tithi/nakshatra/yoga/karana reflect what's happening RIGHT NOW
        $todayDetails = $this->panchangService->getDayDetails(
            date: $todayDate,
            lat: $lat,
            lon: $lon,
            tz: $tz,
            elevation: $elevation,
            calculationAt: $now,
            calendarType: $calendarType,
        );

        $dailyMuhurtaEvaluation = $this->panchangService->getDailyMuhurtaEvaluation(
            date: $todayDate,
            lat: $lat,
            lon: $lon,
            tz: $tz,
            currentAt: $now,
            elevation: $elevation,
        );

        return [
            'todays_complete_details' => [
                'title' => Localization::translate('String', "Today's Complete Details - Every single data point from the package"),
                'input_now' => $now->toIso8601String(),
                'date' => $todayDate->toDateString(),
                'details' => $todayDetails,
            ],
            'muhurta_evaluation' => array_merge(
                [
                    'scope' => 'transit_only',
                    'notes' => [
                        'No natal or person-specific inputs are used.',
                        'Evaluation is derived only from current Panchang and transit state for the configured location/time.',
                    ],
                ],
                $dailyMuhurtaEvaluation
            ),
        ];
    }

    /**
     * Generate complete combined output (festivals + eclipses + today).
     *
     * @return array<string, mixed>
     */
    public function generateAll(
        int $festivalYear,
        int $eclipseStartYear,
        int $eclipseEndYear,
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0,
        CalendarType|string $calendarType = CalendarType::Amanta,
    ): array {
        // Normalize calendar_type
        if (is_string($calendarType)) {
            $calendarType = match (strtolower($calendarType)) {
                'purnimanta', 'purnimant' => CalendarType::Purnimanta,
                default => CalendarType::Amanta,
            };
        }

        $output = [];

        // Festivals
        $festivalOutput = $this->generateFestivalsSelected(
            $festivalYear,
            $lat,
            $lon,
            $tz,
            ['by_date', 'festival_day_count', 'festival_entry_count'],
            $elevation,
            $calendarType,
        );
        $output['festivals_' . $festivalYear] = [
            'title' => sprintf(Localization::translate('String', 'Festivals %d - All festivals for the entire year'), $festivalYear),
            'year' => $festivalYear,
            'calendar_type' => $calendarType->value,
            'festival_day_count' => $festivalOutput['festival_day_count'],
            'festival_entry_count' => $festivalOutput['festival_entry_count'],
            'total_festivals' => $this->countUniqueIdentities($festivalOutput['by_date'], false),
            'total_vrats' => $this->countUniqueIdentities($festivalOutput['by_date'], true),
            'by_date' => $festivalOutput['by_date'],
        ];

        // Eclipses
        $eclipseOutput = $this->generateEclipses($eclipseStartYear, $eclipseEndYear, $lat, $lon, $tz);
        $output['eclipses_' . $eclipseStartYear . '_' . $eclipseEndYear] = [
            'title' => sprintf(
                Localization::translate('String', 'Eclipses %d-%d - All eclipses for %d years'),
                $eclipseStartYear,
                $eclipseEndYear,
                $eclipseEndYear - $eclipseStartYear + 1
            ),
            ...$eclipseOutput['eclipses'],
        ];

        // Today
        $todayOutput = $this->generateTodayPanchang($lat, $lon, $tz, $elevation, $calendarType);
        $output['todays_complete_details'] = $todayOutput['todays_complete_details'];
        $output['muhurta_evaluation'] = $todayOutput['muhurta_evaluation'];

        return $output;
    }

    private function normalizeFestivalSection(string $section): string
    {
        $key = strtolower(trim($section));

        return match ($key) {
            'by_date', 'date', 'dates' => 'by_date',
            'flat' => 'flat',
            'festival_day_count', 'day_count' => 'festival_day_count',
            'festival_entry_count', 'entry_count' => 'festival_entry_count',
            'total_festivals', 'unique_festival_count', 'festival_identity_count' => 'total_festivals',
            'total_vrats', 'unique_vrat_count', 'vrat_identity_count' => 'total_vrats',
            default => $section,
        };
    }

    /**
     * @param array{year:int,festival_day_count:int,festival_entry_count:int,by_date:array<string, array<int, array<string, mixed>>>,flat:array<int, array{date:string,festival:array<string,mixed>}>} $festivalCalendar
     * @param array<int, string> $sections
     *
     * @return array<string, mixed>
     */
    private function selectFestivalCalendarSections(array $festivalCalendar, array $sections): array
    {
        $result = [];
        foreach ($sections as $section) {
            $normalized = $this->normalizeFestivalSection($section);
            $result[$normalized] = match ($normalized) {
                'by_date' => $festivalCalendar['by_date'],
                'flat' => $festivalCalendar['flat'],
                'festival_day_count' => $festivalCalendar['festival_day_count'],
                'festival_entry_count' => $festivalCalendar['festival_entry_count'],
                'total_festivals' => $this->countUniqueIdentities($festivalCalendar['by_date'], false),
                'total_vrats' => $this->countUniqueIdentities($festivalCalendar['by_date'], true),
                default => throw new InvalidArgumentException('Unknown festival output section: ' . $section),
            };
        }

        return $result;
    }

    /**
     * Count distinct observance identities, not dated occurrences.
     *
     * For example, a monthly or seasonal vrat that occurs four times in a year
     * is counted as one identity when all occurrences share the same display name.
     *
     * @param array<string, array<int, array<string, mixed>>> $byDate
     * @param array<int, array<string, mixed>> $extraEntries
     */
    private function countUniqueIdentities(array $byDate, ?bool $fasting = null, array $extraEntries = []): int
    {
        $identities = [];

        foreach ($byDate as $entries) {
            foreach ($entries as $entry) {
                if ($fasting !== null && (($entry['fasting'] ?? null) !== $fasting)) {
                    continue;
                }

                $key = $this->observanceIdentityKey($entry);
                if ($key !== '') {
                    $identities[$key] = true;
                }
            }
        }

        foreach ($extraEntries as $entry) {
            if ($fasting !== null && (($entry['fasting'] ?? null) !== $fasting)) {
                continue;
            }

            $key = $this->observanceIdentityKey($entry);
            if ($key !== '') {
                $identities[$key] = true;
            }
        }

        return count($identities);
    }

    /** @param array<string, mixed> $entry */
    private function observanceIdentityKey(array $entry): string
    {
        return trim((string) ($entry['name_key'] ?? $entry['name'] ?? ''));
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $byDate
     *
     * @return array{recurring_weekday_vrats: array<int, array<string, mixed>>, by_date: array<string, array<int, array<string, mixed>>>, dated_day_count: int, dated_entry_count: int}
     */
    private function extractRecurringWeekdayVrats(array $byDate): array
    {
        $recurringWeekdayVrats = [];
        $datedEntryCount = 0;

        foreach ($byDate as $date => $entries) {
            $datedEntries = [];

            foreach ($entries as $index => $entry) {
                $basis = $entry['calculation_basis'] ?? null;

                if (is_array($basis) && ($basis['type'] ?? null) === 'weekday') {
                    $key = (string) ($entry['name'] ?? ('weekday_' . $index));
                    $recurringWeekdayVrats[$key] ??= $entry;
                    continue;
                }

                $datedEntries[] = $entry;
            }

            if ($datedEntries === []) {
                unset($byDate[$date]);
                continue;
            }

            $byDate[$date] = $datedEntries;
            $datedEntryCount += count($datedEntries);
        }

        uasort($recurringWeekdayVrats, static function (array $left, array $right): int {
            $leftWeekday = (int) ($left['calculation_basis']['weekday']['number'] ?? PHP_INT_MAX);
            $rightWeekday = (int) ($right['calculation_basis']['weekday']['number'] ?? PHP_INT_MAX);

            if ($leftWeekday !== $rightWeekday) {
                return $leftWeekday <=> $rightWeekday;
            }

            return strcmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
        });

        return [
            'recurring_weekday_vrats' => array_values($recurringWeekdayVrats),
            'by_date' => $byDate,
            'dated_day_count' => count($byDate),
            'dated_entry_count' => $datedEntryCount,
        ];
    }

    private function normalizeEclipseSection(string $section): string
    {
        $key = strtolower(trim($section));

        return match ($key) {
            'by_year', 'year', 'years' => 'by_year',
            'flat' => 'flat',
            'total_eclipse_count', 'count' => 'total_eclipse_count',
            default => $section,
        };
    }

    private function normalizeTodaySection(string $section): string
    {
        $key = strtolower(trim($section));

        return match ($key) {
            'today', 'details', 'todays_complete_details' => 'todays_complete_details',
            'muhurta', 'muhurta_evaluation', 'evaluation' => 'muhurta_evaluation',
            default => $section,
        };
    }

    private function normalizeMonthSection(string $section): string
    {
        $key = strtolower(trim($section));

        return match ($key) {
            'meta', 'metadata' => 'meta',
            'calendar', 'month', 'fields' => 'calendar',
            default => $section,
        };
    }

    private function normalizeMonthRangeSection(string $section): string
    {
        $key = strtolower(trim($section));

        return match ($key) {
            'meta', 'metadata' => 'meta',
            'months', 'calendar', 'range', 'fields' => 'months',
            default => $section,
        };
    }

    private function buildMonthOutputMeta(
        int $year,
        int $month,
        float $lat,
        float $lon,
        string $tz,
        CalendarType $calendarType
    ): array {
        return [
            'generated_at' => date('c'),
            'year' => $year,
            'month' => $month,
            'calendar_type' => $calendarType->value,
            'location' => [
                'latitude' => $lat,
                'longitude' => $lon,
                'timezone' => $tz,
            ],
        ];
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function resolveMonthRangeBounds(
        int $fromYear,
        int $fromMonth,
        int $toYear,
        int $toMonth,
        string $tz,
        bool $endInclusive = false
    ): array {
        $start = CarbonImmutable::create($fromYear, $fromMonth, 1, 0, 0, 0, $tz)->startOfDay();
        $endBase = CarbonImmutable::create($toYear, $toMonth, 1, 0, 0, 0, $tz);
        $end = $endInclusive ? $endBase->endOfMonth()->startOfDay() : $endBase->addMonth()->startOfDay();

        if ($end->lessThan($start) || (!$endInclusive && $end->equalTo($start))) {
            throw new InvalidArgumentException('Month range end must be greater than or equal to start.');
        }

        return [$start, $end];
    }

    private function buildMonthRangeOutputMeta(
        CarbonImmutable $start,
        CarbonImmutable $endInclusive,
        float $lat,
        float $lon,
        string $tz,
        CalendarType $calendarType
    ): array {
        return [
            'generated_at' => date('c'),
            'from_year' => $start->year,
            'from_month' => $start->month,
            'to_year' => $endInclusive->year,
            'to_month' => $endInclusive->month,
            'calendar_type' => $calendarType->value,
            'location' => [
                'latitude' => $lat,
                'longitude' => $lon,
                'timezone' => $tz,
            ],
        ];
    }
}
