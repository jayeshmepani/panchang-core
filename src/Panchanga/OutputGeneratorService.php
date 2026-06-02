<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Panchanga;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use JayeshMepani\PanchangCore\Astronomy\EclipseService;
use JayeshMepani\PanchangCore\Core\Enums\CalendarType;
use JayeshMepani\PanchangCore\Core\Localization;
use JayeshMepani\PanchangCore\Festivals\FestivalService;

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

        $result = [];
        foreach ($sections as $section) {
            $normalized = $this->normalizeFestivalSection($section);
            $result[$normalized] = match ($normalized) {
                'by_date' => $festivalCalendar['by_date'],
                'flat' => $festivalCalendar['flat'],
                'festival_day_count' => $festivalCalendar['festival_day_count'],
                'festival_entry_count' => $festivalCalendar['festival_entry_count'],
                default => throw new InvalidArgumentException('Unknown festival output section: ' . $section),
            };
        }

        return $result;
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
                'total_festivals' => FestivalService::getFestivalCount(),
                'by_date' => $festivalCalendar['by_date'],
                'flat' => $festivalCalendar['flat'],
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
        $festivalOutput = $this->generateFestivals($festivalYear, $lat, $lon, $tz, $elevation, $calendarType);
        $output['festivals_' . $festivalYear] = [
            'title' => sprintf(Localization::translate('String', 'Festivals %d - All festivals for the entire year'), $festivalYear),
            ...$festivalOutput['festivals'],
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
            default => $section,
        };
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
}
