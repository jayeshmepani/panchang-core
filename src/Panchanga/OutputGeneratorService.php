<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Panchanga;

use Carbon\CarbonImmutable;
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
                'by_date' => $festivalCalendar['by_date'],
                'flat' => $festivalCalendar['flat'],
            ],
        ];
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
}
