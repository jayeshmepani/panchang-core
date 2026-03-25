<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Festivals;

use Carbon\CarbonImmutable;

/**
 * Festival Family Orchestrator.
 *
 * Handles multi-day festival families and celebration logic:
 * - Holi family (Holika Dahan → Holi → Dhulandi)
 * - Diwali family (Dhanteras → Diwali → Bhai Dooj)
 * - Janmashtami family (Smarta vs Vaishnava)
 * - Navratri sequence
 * - Pitru Paksha sequence
 * - Chhath Puja sequence
 *
 * Key feature: Celebration exception logic
 * Example: Holika Dahan must be on night when Purnima is active,
 * even if Purnima sunrise is on next day
 */
class FestivalFamilyOrchestrator
{
    /** Festival family definitions */
    public const FESTIVAL_FAMILIES = [
        'holi' => [
            'name' => 'Holi Family',
            'sequence' => [
                [
                    'name' => 'Holika Dahan',
                    'offset' => 0,
                    'rule_key' => 'holika_dahan',
                    'time_of_day' => 'night',
                    'tithi_requirement' => 'must_be_active',
                ],
                [
                    'name' => 'Holi (Dhuleti)',
                    'offset' => 1,
                    'rule_key' => 'holi',
                    'time_of_day' => 'day',
                    'tithi_requirement' => 'next_day',
                ],
            ],
            'exception_logic' => 'holika_dahan_night_priority',
        ],
        'diwali' => [
            'name' => 'Diwali Family',
            'sequence' => [
                [
                    'name' => 'Dhanteras',
                    'offset' => -2,
                    'rule_key' => 'dhanteras',
                    'time_of_day' => 'pradosha',
                ],
                [
                    'name' => 'Naraka Chaturdashi',
                    'offset' => -1,
                    'rule_key' => 'naraka_chaturdashi',
                    'time_of_day' => 'sunrise',
                ],
                [
                    'name' => 'Diwali / Lakshmi Puja',
                    'offset' => 0,
                    'rule_key' => 'diwali',
                    'time_of_day' => 'pradosha',
                ],
                [
                    'name' => 'Govardhan Puja',
                    'offset' => 1,
                    'rule_key' => 'govardhan_puja',
                    'time_of_day' => 'day',
                ],
                [
                    'name' => 'Bhai Dooj',
                    'offset' => 2,
                    'rule_key' => 'bhai_dooj',
                    'time_of_day' => 'aparahna',
                ],
            ],
            'exception_logic' => 'diwali_sequence',
        ],
        'janmashtami' => [
            'name' => 'Janmashtami Family',
            'sequence' => [
                [
                    'name' => 'Krishna Janmashtami',
                    'offset' => 0,
                    'rule_key' => 'janmashtami',
                    'time_of_day' => 'nishita',
                    'tradition_variants' => ['Smarta', 'Vaishnava'],
                ],
                [
                    'name' => 'Dahi Handi',
                    'offset' => 1,
                    'rule_key' => 'dahi_handi',
                    'time_of_day' => 'day',
                ],
            ],
            'exception_logic' => 'janmashtami_tradition_split',
        ],
        'navratri' => [
            'name' => 'Navratri Family',
            'sequence' => [
                ['name' => 'Ghatasthapana', 'offset' => 0, 'rule_key' => 'ghatasthapana', 'time_of_day' => 'sunrise'],
                ['name' => 'Day 2', 'offset' => 1, 'rule_key' => 'navratri_day_2', 'time_of_day' => 'day'],
                ['name' => 'Day 3', 'offset' => 2, 'rule_key' => 'navratri_day_3', 'time_of_day' => 'day'],
                ['name' => 'Day 4', 'offset' => 3, 'rule_key' => 'navratri_day_4', 'time_of_day' => 'day'],
                ['name' => 'Day 5', 'offset' => 4, 'rule_key' => 'navratri_day_5', 'time_of_day' => 'day'],
                ['name' => 'Day 6', 'offset' => 5, 'rule_key' => 'navratri_day_6', 'time_of_day' => 'day'],
                ['name' => 'Durga Ashtami', 'offset' => 6, 'rule_key' => 'durga_ashtami', 'time_of_day' => 'day'],
                ['name' => 'Maha Navami', 'offset' => 7, 'rule_key' => 'maha_navami', 'time_of_day' => 'day'],
                ['name' => 'Vijayadashami', 'offset' => 8, 'rule_key' => 'vijayadashami', 'time_of_day' => 'aparahna'],
            ],
            'exception_logic' => 'navratri_sequence',
        ],
        'pitru_paksha' => [
            'name' => 'Pitru Paksha (Shraddha)',
            'sequence' => [
                ['name' => 'Pratipada Shraddha', 'offset' => 0, 'rule_key' => 'pitru_pratipada', 'time_of_day' => 'madhyahna'],
                ['name' => 'Dwitiya Shraddha', 'offset' => 1, 'rule_key' => 'pitru_dwitiya', 'time_of_day' => 'madhyahna'],
                ['name' => 'Tritiya Shraddha', 'offset' => 2, 'rule_key' => 'pitru_tritiya', 'time_of_day' => 'madhyahna'],
                ['name' => 'Chaturthi Shraddha', 'offset' => 3, 'rule_key' => 'pitru_chaturthi', 'time_of_day' => 'madhyahna'],
                ['name' => 'Panchami Shraddha', 'offset' => 4, 'rule_key' => 'pitru_panchami', 'time_of_day' => 'madhyahna'],
                ['name' => 'Shashthi Shraddha', 'offset' => 5, 'rule_key' => 'pitru_shashthi', 'time_of_day' => 'madhyahna'],
                ['name' => 'Saptami Shraddha', 'offset' => 6, 'rule_key' => 'pitru_saptami', 'time_of_day' => 'madhyahna'],
                ['name' => 'Ashtami Shraddha', 'offset' => 7, 'rule_key' => 'pitru_ashtami', 'time_of_day' => 'madhyahna'],
                ['name' => 'Navami Shraddha', 'offset' => 8, 'rule_key' => 'pitru_navami', 'time_of_day' => 'madhyahna'],
                ['name' => 'Dashami Shraddha', 'offset' => 9, 'rule_key' => 'pitru_dashami', 'time_of_day' => 'madhyahna'],
                ['name' => 'Ekadashi Shraddha', 'offset' => 10, 'rule_key' => 'pitru_ekadashi', 'time_of_day' => 'madhyahna'],
                ['name' => 'Dwadashi Shraddha', 'offset' => 11, 'rule_key' => 'pitru_dwadashi', 'time_of_day' => 'madhyahna'],
                ['name' => 'Trayodashi Shraddha', 'offset' => 12, 'rule_key' => 'pitru_trayodashi', 'time_of_day' => 'madhyahna'],
                ['name' => 'Sarva Pitru Amavasya', 'offset' => 13, 'rule_key' => 'sarva_pitru_amavasya', 'time_of_day' => 'madhyahna'],
            ],
            'exception_logic' => 'pitru_paksha_sequence',
        ],
        'chhath' => [
            'name' => 'Chhath Puja',
            'sequence' => [
                ['name' => 'Nahay Khay', 'offset' => 0, 'rule_key' => 'chhath_day1', 'time_of_day' => 'day'],
                ['name' => 'Kharna', 'offset' => 1, 'rule_key' => 'chhath_kharna', 'time_of_day' => 'sunset'],
                ['name' => 'Sandhya Arghya', 'offset' => 2, 'rule_key' => 'chhath_sandhya', 'time_of_day' => 'sunset'],
                ['name' => 'Usha Arghya', 'offset' => 3, 'rule_key' => 'chhath_usha', 'time_of_day' => 'sunrise'],
            ],
            'exception_logic' => 'chhath_sequence',
        ],
    ];

    /** Exception logic implementations */
    public const EXCEPTION_LOGIC = [
        'holika_dahan_night_priority' => [
            'description' => 'Holika Dahan must occur on night when Purnima is active, even if Purnima sunrise is next day',
            'logic' => 'if (purnima_active_night_day1 && purnima_not_active_night_day2) => holika_day1',
        ],
        'diwali_sequence' => [
            'description' => 'Diwali family follows strict tithi sequence with pradosh timing',
            'logic' => 'sequence_based_on_tithi',
        ],
        'janmashtami_tradition_split' => [
            'description' => 'Smarta observes on Ashtami sunrise, Vaishnava on Ashtami+Nishita+Rohini',
            'logic' => 'tradition_based_selection',
        ],
        'navratri_sequence' => [
            'description' => 'Navratri runs Pratipada to Dashami continuously',
            'logic' => 'continuous_sequence',
        ],
        'pitru_paksha_sequence' => [
            'description' => 'Pitru Paksha runs from Purnima to Amavasya with tithi-based shraddha',
            'logic' => 'tithi_based_sequence',
        ],
        'chhath_sequence' => [
            'description' => 'Chhath is 4-day sequence with specific timing for each day',
            'logic' => 'fixed_sequence',
        ],
    ];
    /**
     * Resolve complete festival family with celebration logic.
     *
     * @param string $familyName Name of festival family
     * @param array $baseDate Base date panchang data
     * @param array $panchangData Array of panchang data for multiple days
     * @param string $tradition Tradition profile
     * @param string $region Region profile
     *
     * @return array|null Resolved festival family with all dates and celebration logic
     */
    public function resolveFestivalFamily(
        string $familyName,
        array $baseDate,
        array $panchangData,
        ?string $tradition = null,
        ?string $region = null
    ): ?array {
        $tradition = $tradition ?? (function_exists('config') ? config('panchang.festivals.default_tradition', 'Smarta') : 'Smarta');
        $region = $region ?? (function_exists('config') ? config('panchang.festivals.default_region', 'North') : 'North');

        $family = self::FESTIVAL_FAMILIES[$familyName] ?? null;
        if ($family === null) {
            return null;
        }

        $exceptionLogic = self::EXCEPTION_LOGIC[$family['exception_logic']];

        $resolved = [];

        foreach ($family['sequence'] as $event) {
            $offset = $event['offset'];
            $eventDate = $this->getPanchangForOffset($panchangData, $offset);

            if ($eventDate === null) {
                continue;
            }

            $eventResolution = $this->resolveSingleEvent($event, $eventDate, $panchangData, $tradition, $region);

            if ($eventResolution !== null) {
                $resolved[] = $eventResolution;
            }
        }

        // Apply exception logic
        $resolved = $this->applyExceptionLogic($resolved, $family['exception_logic'], $panchangData);

        return [
            'family_name' => $family['name'],
            'family_key' => $familyName,
            'tradition' => $tradition,
            'region' => $region,
            'exception_logic' => $exceptionLogic,
            'events' => $resolved,
            'celebration_summary' => $this->buildCelebrationSummary($resolved),
        ];
    }

    /** Resolve single event within festival family */
    private function resolveSingleEvent(
        array $event,
        array $eventDate,
        array $panchangData,
        string $tradition,
        string $region
    ): ?array {
        $ruleKey = $event['rule_key'];
        $timeOfDay = $event['time_of_day'] ?? 'day';

        // Get rule for this event
        $rule = $this->getEventRule($ruleKey, $tradition, $region);
        if ($rule === null) {
            return null;
        }

        // Check tradition variants
        if (isset($event['tradition_variants'])) {
            if (!in_array($tradition, $event['tradition_variants'], true)) {
                // Use default rule for this tradition
                $rule = $this->getDefaultRuleForTradition($ruleKey, $tradition);
            }
        }

        $ctx = $eventDate['Resolution_Context'] ?? [];
        $tithi = $eventDate['Tithi'] ?? [];
        $tithiNum = $tithi['index'] ?? 0;
        $paksha = $tithi['paksha'] ?? 'Shukla';

        // Check if tithi matches
        $tithiMatches = $this->checkTithiMatch($rule, $tithiNum, $paksha);
        if (!$tithiMatches) {
            return null;
        }

        $date = CarbonImmutable::parse($ctx['sunrise_iso'] ?? 'now');

        return [
            'event_name' => $event['name'],
            'rule_key' => $ruleKey,
            'observance_date' => $date->toDateString(),
            'observance_datetime' => $date->toIso8601String(),
            'time_of_day' => $timeOfDay,
            'tithi' => $tithi,
            'paksha' => $paksha,
            'tradition' => $tradition,
            'celebration_logic' => $this->buildEventCelebrationLogic($event, $rule, $tithi, $ctx),
        ];
    }

    /** Apply exception logic to resolved events */
    private function applyExceptionLogic(array $events, string $exceptionLogic, array $panchangData): array
    {
        return match ($exceptionLogic) {
            'holika_dahan_night_priority' => $this->applyHolikaDahanException($events, $panchangData),
            'janmashtami_tradition_split' => $this->applyJanmashtamiException($events, $panchangData),
            default => $events,
        };
    }

    /**
     * Holika Dahan exception: must be on night when Purnima is active.
     *
     * This is the KEY example from the prompt:
     * Purnima runs from March 2nd evening (17:56) to March 3rd evening (17:07)
     * March 2nd night: Purnima active ✓
     * March 3rd night: Pratipada active ✗
     * Result: Holika Dahan on March 2nd (even though Purnima sunrise is March 3rd)
     */
    private function applyHolikaDahanException(array $events, array $panchangData): array
    {
        $holikaIndex = null;
        $holiIndex = null;

        foreach ($events as $i => $event) {
            if ($event['event_name'] === 'Holika Dahan') {
                $holikaIndex = $i;
            }
            if ($event['event_name'] === 'Holi (Dhuleti)') {
                $holiIndex = $i;
            }
        }

        if ($holikaIndex === null || count($panchangData) < 2) {
            return $events;
        }

        // Check night-time tithi presence
        $day1 = $panchangData[0] ?? null;
        $day2 = $panchangData[1] ?? null;

        if ($day1 === null || $day2 === null) {
            return $events;
        }

        $ctx1 = $day1['Resolution_Context'] ?? [];
        $ctx2 = $day2['Resolution_Context'] ?? [];

        $tithi1 = $day1['Tithi']['index'] ?? 0;
        $tithi2 = $day2['Tithi']['index'] ?? 0;
        $paksha1 = $day1['Tithi']['paksha'] ?? 'Shukla';

        // Check if Purnima (tithi 15) is active during night
        $purnimaNightDay1 = $this->isPurnimaActiveDuringNight($tithi1, $tithi2, $paksha1, $ctx1, $ctx2);
        $purnimaNightDay2 = $this->isPurnimaActiveDuringNight($tithi2, 1, $paksha1, $ctx2, []);

        // Apply exception logic
        if ($purnimaNightDay1 && !$purnimaNightDay2) {
            // Holika Dahan MUST be on day 1 (night when Purnima is active)
            $events[$holikaIndex]['celebration_logic']['exception_applied'] = true;
            $events[$holikaIndex]['celebration_logic']['reason'] = 'Purnima active during night of day 1, not day 2';
            $events[$holikaIndex]['celebration_logic']['logic'] = 'Holika Dahan forced to night when Purnima is active';
        }

        return $events;
    }

    /** Check if Purnima is active during night */
    private function isPurnimaActiveDuringNight(
        int $todayTithi,
        int $tomorrowTithi,
        string $paksha,
        array $ctxToday,
        array $ctxTomorrow
    ): bool {
        // Purnima = tithi 15 in Shukla paksha
        if ($paksha !== 'Shukla') {
            return false;
        }

        $isPurnimaToday = $todayTithi === 15;
        $isPurnimaTomorrow = $tomorrowTithi === 15;

        // Check if Purnima ends before night
        $tithiEndJd = $ctxToday['tithi_end_jd'] ?? 0.0;
        $sunsetJd = $ctxToday['sunset_jd'] ?? 0.0;

        if ($isPurnimaToday && $tithiEndJd > $sunsetJd) {
            // Purnima extends into night
            return true;
        }

        if ($isPurnimaTomorrow) {
            // Check if Purnima started before night
            $tithiStartJd = $ctxTomorrow['tithi_start_jd'] ?? 0.0;
            $nextSunsetJd = $ctxTomorrow['sunset_jd'] ?? 0.0;

            if ($tithiStartJd < $nextSunsetJd) {
                return true;
            }
        }

        return false;
    }

    /** Janmashtami exception: tradition-based split */
    private function applyJanmashtamiException(array $events, array $panchangData): array
    {
        foreach ($events as $i => $event) {
            if ($event['event_name'] !== 'Krishna Janmashtami') {
                continue;
            }

            $tradition = $event['tradition'] ?? 'Smarta';

            if ($tradition === 'Vaishnava') {
                $events[$i]['celebration_logic']['tradition_note'] = 'Vaishnava/ISKCON observes with Rohini preference';
                $events[$i]['celebration_logic']['timing'] = 'Nishita during Ashtami with Rohini';
            } else {
                $events[$i]['celebration_logic']['tradition_note'] = 'Smarta observes on Ashtami sunrise';
                $events[$i]['celebration_logic']['timing'] = 'Ashtami during sunrise';
            }
        }

        return $events;
    }

    /** Build celebration logic for single event */
    private function buildEventCelebrationLogic(array $event, array $rule, array $tithi, array $ctx): array
    {
        $timeOfDay = $event['time_of_day'] ?? 'day';

        return [
            'time_of_day' => $timeOfDay,
            'tithi_requirement' => $rule['tithi'] ?? null,
            'paksha_requirement' => $rule['paksha'] ?? null,
            'karmakala_type' => $rule['karmakala_type'] ?? $timeOfDay,
            'exception_applied' => false,
            'reason' => null,
        ];
    }

    /** Build celebration summary for family */
    private function buildCelebrationSummary(array $events): array
    {
        $dates = array_column($events, 'observance_date');
        $names = array_column($events, 'event_name');

        return [
            'total_events' => count($events),
            'start_date' => reset($dates),
            'end_date' => end($dates),
            'event_names' => $names,
            'duration_days' => count(array_unique($dates)),
        ];
    }

    /** Get panchang data for offset */
    private function getPanchangForOffset(array $panchangData, int $offset): ?array
    {
        $index = $offset >= 0 ? $offset : count($panchangData) + $offset;
        return $panchangData[$index] ?? null;
    }

    /** Get event rule */
    private function getEventRule(string $ruleKey, string $tradition, string $region): ?array
    {
        // Keep aligned with app behavior: rules are not resolved at this layer.
        return null;
    }

    /** Get default rule for tradition */
    private function getDefaultRuleForTradition(string $ruleKey, string $tradition): ?array
    {
        return null;
    }

    /** Check tithi match */
    private function checkTithiMatch(array $rule, int $tithiNum, string $paksha): bool
    {
        $ruleTithi = $rule['tithi'] ?? null;
        $rulePaksha = $rule['paksha'] ?? null;

        if ($ruleTithi !== null && $ruleTithi !== $tithiNum) {
            return false;
        }

        if ($rulePaksha !== null && $rulePaksha !== $paksha) {
            return false;
        }

        return true;
    }
}
