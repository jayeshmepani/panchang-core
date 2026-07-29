<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Festivals;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Core\Enums\Masa;
use JayeshMepani\PanchangCore\Festivals\Support\FestivalShared;

/**
 * Month/tithi/paksha/amavasya helpers used by festival rule matching.
 *
 * Structure-only split from FestivalService. Algorithms unchanged.
 *
 * @see docs/FESTIVAL_REFACTOR_PARITY_CONTRACT.md
 */
trait FestivalMonthTithiSupport
{
    private function tithiPaksha(array $details): ?string
    {
        $ctxPaksha = $details['Resolution_Context']['paksha'] ?? null;
        if (is_string($ctxPaksha) && $ctxPaksha !== '') {
            return $ctxPaksha;
        }

        $tithiPaksha = $details['Tithi']['paksha'] ?? null;
        return is_string($tithiPaksha) && $tithiPaksha !== '' ? $tithiPaksha : null;
    }

    private function tithiPhase(array $details): ?int
    {
        $phase = $details['Resolution_Context']['tithi_index_phase'] ?? null;
        if (is_numeric($phase)) {
            return (int) $phase;
        }

        $index = $details['Resolution_Context']['tithi_index_abs'] ?? ($details['Tithi']['index'] ?? null);
        if (!is_numeric($index)) {
            return null;
        }

        $index = (int) $index;
        return (($index - 1) % 15) + 1;
    }

    private function tithiEndJd(array $details): ?float
    {
        $endJd = $details['Resolution_Context']['tithi_end_jd'] ?? null;

        return is_numeric($endJd) ? (float) $endJd : null;
    }

    private function isAdhikaDay(array $details): bool
    {
        return (bool) ($details['Hindu_Calendar']['Is_Adhika'] ?? false);
    }

    private function sunSignRuleMatches(int $requiredSunSign, array $details): bool
    {
        $sunSign = $details['Sun_Sign_Index'] ?? null;
        if (is_numeric($sunSign)) {
            return (int) $sunSign === $requiredSunSign;
        }

        $sunLongitude = $details['sun_sunrise_lon'] ?? null;
        if (!is_numeric($sunLongitude)) {
            return false;
        }

        $normalizedLongitude = fmod((float) $sunLongitude, 360.0);
        if ($normalizedLongitude < 0.0) {
            $normalizedLongitude += 360.0;
        }

        return (int) floor($normalizedLongitude / 30.0) === $requiredSunSign;
    }

    /**
     * Score how closely a civil day matches Purnima for Tamil Arudra-style rules.
     * Higher is better: exact Shukla 15 > Shukla 14 eve > day-after-Purnima.
     */
    private function purnimaAffinityScore(array $details, array $rules): int
    {
        $tithi = (array) ($details['Tithi'] ?? []);
        $paksha = (string) ($tithi['paksha'] ?? '');
        $index = (int) ($tithi['index'] ?? 0);
        $phase = $index > 15 ? $index - 15 : $index;

        if ($paksha === 'Shukla' && $phase === 15) {
            return 100;
        }

        if ($paksha === 'Shukla' && $phase === 14 && (bool) ($rules['allow_shukla_chaturdashi_purnima_eve'] ?? false)) {
            return 90;
        }

        // Day after Purnima (Krishna Pratipada): absolute 16 or phase 1 in Krishna.
        if ($paksha === 'Krishna' && $phase === 1) {
            return 50;
        }

        if ($paksha === 'Krishna' && $phase === 2) {
            return 40;
        }

        return 10;
    }

    private function snapshotHasRequiredNakshatraAtSunrise(array $details, string $requiredNakshatra): bool
    {
        $name = (string) (($details['Nakshatra']['name'] ?? $details['Nakshatra_At_Sunrise']['name'] ?? ''));
        if ($name === '') {
            return false;
        }

        return strcasecmp($name, $requiredNakshatra) === 0;
    }

    /**
     * Tamil Arudra (flowchart): one winner per continuous solar Dhanu window.
     * Collapse consecutive Ardra sunrises (vriddhi), then keep highest Purnima affinity.
     *
     * @param callable(CarbonImmutable): array<string, mixed>|null $fetchHistoricalSnapshot
     */
    private function shouldSuppressNakshatraForBetterSunSignCandidate(
        array $rules,
        CarbonImmutable $date,
        array $todayDetails,
        ?callable $fetchHistoricalSnapshot
    ): bool {
        if (!(bool) ($rules['prefer_best_purnima_affinity_in_sun_sign'] ?? false)) {
            return false;
        }

        if ($fetchHistoricalSnapshot === null || !isset($rules['sun_sign'])) {
            return false;
        }

        $requiredSunSign = (int) $rules['sun_sign'];
        if (!$this->sunSignRuleMatches($requiredSunSign, $todayDetails)) {
            return false;
        }

        $requiredNakshatra = (string) ($rules['nakshatra'] ?? '');
        if ($requiredNakshatra === '') {
            return false;
        }

        /** @var array<string, array{date: string, score: int}> $candidates */
        $candidates = [];

        // Walk backward/forward while Sun stays in the required sign (Margazhi/Dhanu ~30d).
        foreach ([-1, 1] as $direction) {
            for ($offset = $direction === -1 ? 0 : 1; $offset <= 40; $offset++) {
                $dayOffset = $direction === -1 ? -$offset : $offset;
                $candidateDate = $date->addDays($dayOffset);
                /** @var array<string, mixed> $snapshot */
                $snapshot = $dayOffset === 0
                    ? $todayDetails
                    : $fetchHistoricalSnapshot($candidateDate);
                if ($snapshot === []) {
                    break;
                }

                if (!$this->sunSignRuleMatches($requiredSunSign, $snapshot)) {
                    if ($dayOffset === 0) {
                        return false;
                    }

                    break;
                }

                if (!$this->snapshotHasRequiredNakshatraAtSunrise($snapshot, $requiredNakshatra)) {
                    if ($dayOffset === 0) {
                        // Today is not a sunrise-nakshatra candidate; nothing to suppress.
                        return false;
                    }

                    continue;
                }

                $ymd = $candidateDate->toDateString();
                $candidates[$ymd] = [
                    'date' => $ymd,
                    'score' => $this->purnimaAffinityScore($snapshot, $rules),
                ];
            }
        }

        if ($candidates === []) {
            return false;
        }

        $collapsed = $this->collapseConsecutiveNakshatraCandidateDays(
            $candidates,
            (string) ($rules['vriddhi_preference'] ?? '')
        );

        $bestDate = null;
        $bestScore = PHP_INT_MIN;
        foreach ($collapsed as $ymd => $row) {
            $score = $row['score'];
            if ($score > $bestScore || ($score === $bestScore && ($bestDate === null || $ymd > $bestDate))) {
                $bestScore = $score;
                $bestDate = $ymd;
            }
        }

        return $bestDate !== null && $bestDate !== $date->toDateString();
    }

    /**
     * Collapse consecutive civil-day runs (vriddhi) to first/last per preference.
     *
     * @param array<string, array{date: string, score: int}> $candidates
     *
     * @return array<string, array{date: string, score: int}>
     */
    private function collapseConsecutiveNakshatraCandidateDays(array $candidates, string $vriddhiPreference): array
    {
        if ($candidates === []) {
            return [];
        }

        ksort($candidates);
        $dates = array_keys($candidates);
        $collapsed = [];
        /** @var list<string> $run */
        $run = [];

        foreach ($dates as $ymd) {
            if ($run !== []) {
                $prev = $run[array_key_last($run)];
                $prevNext = CarbonImmutable::parse($prev)->addDay()->toDateString();
                if ($prevNext !== $ymd) {
                    $pick = $vriddhiPreference === 'last' ? $run[array_key_last($run)] : $run[0];
                    $collapsed[$pick] = $candidates[$pick];
                    $run = [];
                }
            }

            $run[] = $ymd;
        }

        // $dates is non-empty when called, so $run always has at least one day.
        $pick = $vriddhiPreference === 'last' ? $run[array_key_last($run)] : $run[0];
        $collapsed[$pick] = $candidates[$pick];

        return $collapsed;
    }

    private function intervalCoversFullKarmakala(float $startJd, float $endJd, array $details, string $karmakalaType): bool
    {
        $window = $this->karmakalaWindowJdFromDetails($details, $karmakalaType);
        if ($window === null) {
            return false;
        }

        return $startJd <= $window['start_jd'] && $endJd >= $window['end_jd'];
    }

    /** @return array{start_jd: float, end_jd: float}|null */
    private function karmakalaWindowJdFromDetails(array $details, string $karmakalaType): ?array
    {
        $ctx = (array) ($details['Resolution_Context'] ?? []);
        $sunriseJd = $ctx['sunrise_jd'] ?? null;
        $sunsetJd = $ctx['sunset_jd'] ?? null;
        $nextSunriseJd = $ctx['next_sunrise_jd'] ?? null;
        if (!is_numeric($sunriseJd) || !is_numeric($sunsetJd) || !is_numeric($nextSunriseJd)) {
            return null;
        }

        $sunrise = (float) $sunriseJd;
        $sunset = (float) $sunsetJd;
        $nextSunrise = (float) $nextSunriseJd;
        $dayDuration = $sunset - $sunrise;
        $nightDuration = $nextSunrise - $sunset;

        [$windowStart, $windowEnd] = match ($karmakalaType) {
            'madhyahna' => [
                $sunrise + ($dayDuration * 2.0 / 5.0),
                $sunrise + ($dayDuration * 3.0 / 5.0),
            ],
            'aparahna' => [
                $sunrise + ($dayDuration * 3.0 / 5.0),
                $sunrise + ($dayDuration * 4.0 / 5.0),
            ],
            'pradosha' => [
                $sunset,
                $sunset + (3.0 * ($nightDuration / 15.0)),
            ],
            default => [$sunrise, $sunrise],
        };

        return ['start_jd' => $windowStart, 'end_jd' => $windowEnd];
    }

    /**
     * Normalize Sanskrit month names for robust matching across ASCII and diacritic forms.
     * Implementation: {@see Support\FestivalShared::normalizeMonthName()}.
     */
    private function normalizeMonthName(string $month): string
    {
        return FestivalShared::normalizeMonthName($month);
    }

    private function isKrishnaAmavasyaRule(array $rules): bool
    {
        $paksha = strtolower((string) ($rules['paksha'] ?? ''));
        $tithi = (int) (is_array($rules['tithi'] ?? null) ? ($rules['tithi'][0] ?? 0) : ($rules['tithi'] ?? 0));

        return $paksha === 'krishna' && $tithi === 15;
    }

    private function isAmavasyaAtSunrise(array $details): bool
    {
        $tithi = (array) ($details['Tithi'] ?? []);
        $idx = (int) ($tithi['index'] ?? $tithi['Number'] ?? 0);
        $paksha = strtolower((string) ($tithi['paksha'] ?? $tithi['Paksha'] ?? ''));

        return $idx === 30 || ($idx === 15 && $paksha === 'krishna');
    }

    /** Civil day hosts Amavasya when it is present at sunrise, begins later today, or ends overnight into Shukla Pratipada. */
    private function dayCarriesAmavasya(array $todayDetails, array $tomorrowDetails): bool
    {
        if ($this->isAmavasyaAtSunrise($todayDetails) || $this->isAmavasyaAtSunrise($tomorrowDetails)) {
            return true;
        }

        $tom = (array) ($tomorrowDetails['Tithi'] ?? []);
        $idx = (int) ($tom['index'] ?? $tom['Number'] ?? 0);
        $paksha = strtolower((string) ($tom['paksha'] ?? $tom['Paksha'] ?? ''));

        return $idx === 1 && $paksha === 'shukla';
    }

    /**
     * Named Krishna-Amavasya month label — one civil date per named month.
     * Always attribute from THIS civil day's Hindu month (amanta name, or dynamic
     * Krishna purnimanta from today's Amanta_Index). Never look ahead to tomorrow's
     * Pratipada — that dual-labeled Chaitra (2026-03-18+04-17) and dropped Phalguna.
     */
    private function namedAmavasyaAttributedMonthMatches(
        array $rules,
        array $todayDetails,
        array $tomorrowDetails
    ): bool {
        if (!$this->isKrishnaAmavasyaRule($rules)) {
            return false;
        }

        if (!$this->dayCarriesAmavasya($todayDetails, $tomorrowDetails)) {
            return false;
        }

        return $this->monthRuleMatches($rules, (array) ($todayDetails['Hindu_Calendar'] ?? []));
    }

    /** Match month rule against active calendar type (amanta/purnimanta). */
    private function monthRuleMatches(array $rules, array $calendar): bool
    {
        $amanta = $this->normalizeMonthName((string) ($calendar['Month_Amanta_En'] ?? $calendar['Month_Amanta'] ?? ''));
        $dynamicPurnimanta = $this->getDynamicPurnimantaName($rules, $calendar);
        $purnimanta = $this->normalizeMonthName($dynamicPurnimanta);
        $ruleAmanta = isset($rules['month_amanta']) ? $this->normalizeMonthName((string) $rules['month_amanta']) : '';
        $rulePurnimanta = isset($rules['month_purnimanta']) ? $this->normalizeMonthName((string) $rules['month_purnimanta']) : '';
        $calendarType = strtolower((string) ($calendar['Calendar_Type'] ?? AstroCore::getConfig('panchang.defaults.calendar_type', 'amanta')));

        if ((bool) ($rules['force_amanta_month'] ?? false)) {
            if ($ruleAmanta !== '') {
                return $ruleAmanta === $amanta;
            }

            $allowedAmanta = array_values(array_filter(array_map(
                fn ($month): string => $this->normalizeMonthName((string) $month),
                (array) ($rules['allowed_months_amanta'] ?? [])
            ), fn (string $value): bool => $value !== ''));

            return $allowedAmanta !== [] && $amanta !== '' && in_array($amanta, $allowedAmanta, true);
        }

        $allowedAmanta = array_values(array_filter(array_map(
            fn ($month): string => $this->normalizeMonthName((string) $month),
            (array) ($rules['allowed_months_amanta'] ?? [])
        ), fn (string $value): bool => $value !== ''));
        $allowedPurnimanta = array_values(array_filter(array_map(
            fn ($month): string => $this->normalizeMonthName((string) $month),
            (array) ($rules['allowed_months_purnimanta'] ?? [])
        ), fn (string $value): bool => $value !== ''));

        if ((string) ($rules['family'] ?? '') === 'phuldolotsava' && $allowedAmanta !== []) {
            return $amanta !== '' && in_array($amanta, $allowedAmanta, true);
        }

        if ($calendarType === 'purnimanta') {
            if ($allowedPurnimanta !== []) {
                return $purnimanta !== '' && in_array($purnimanta, $allowedPurnimanta, true);
            }

            if ($rulePurnimanta !== '') {
                return $rulePurnimanta === $purnimanta;
            }

            if ($allowedAmanta !== []) {
                return $amanta !== '' && in_array($amanta, $allowedAmanta, true);
            }

            if ($ruleAmanta !== '') {
                return $ruleAmanta === $amanta;
            }

            return true;
        }

        // Default: amanta
        if ($allowedAmanta !== []) {
            return $amanta !== '' && in_array($amanta, $allowedAmanta, true);
        }

        if ($ruleAmanta !== '') {
            return $ruleAmanta === $amanta;
        }

        if ($allowedPurnimanta !== []) {
            return $purnimanta !== '' && in_array($purnimanta, $allowedPurnimanta, true);
        }

        if ($rulePurnimanta !== '') {
            return $rulePurnimanta === $purnimanta;
        }

        return true;
    }

    private function hasMonthRule(array $rules): bool
    {
        return isset($rules['month_amanta'])
            || isset($rules['month_purnimanta'])
            || isset($rules['allowed_months_amanta'])
            || isset($rules['allowed_months_purnimanta']);
    }

    /** Reject rules explicitly excluded for the active lunar month. */
    private function monthRuleExcluded(array $rules, array $calendar): bool
    {
        $amanta = $this->normalizeMonthName((string) ($calendar['Month_Amanta_En'] ?? $calendar['Month_Amanta'] ?? ''));
        $dynamicPurnimanta = $this->getDynamicPurnimantaName($rules, $calendar);
        $purnimanta = $this->normalizeMonthName($dynamicPurnimanta);
        $calendarType = strtolower((string) ($calendar['Calendar_Type'] ?? AstroCore::getConfig('panchang.defaults.calendar_type', 'amanta')));

        $excludedAmanta = array_map(fn ($month): string => $this->normalizeMonthName((string) $month), (array) ($rules['excluded_months_amanta'] ?? []));
        $excludedPurnimanta = array_map(fn ($month): string => $this->normalizeMonthName((string) $month), (array) ($rules['excluded_months_purnimanta'] ?? []));

        if ($calendarType === 'purnimanta' && $excludedPurnimanta !== []) {
            return in_array($purnimanta, $excludedPurnimanta, true);
        }

        if ($excludedAmanta !== []) {
            return in_array($amanta, $excludedAmanta, true);
        }

        return $excludedPurnimanta !== [] && in_array($purnimanta, $excludedPurnimanta, true);
    }

    /** Allow evening/night observances whose correct karmakala falls before the named-month sunrise.
     *
     * For Krishna Amavasya (tithi 15): never look ahead to tomorrow's month
     * (one named month-Amavasya date; avoids Chaitra dual-label / Phalguna miss).
     */
    private function canResolveAcrossMonthBoundary(array $rules, array $tomorrowCalendar, bool $isClassical, array $todayDetails = []): bool
    {
        if (!$isClassical || $tomorrowCalendar === []) {
            return false;
        }

        $karmakalaType = (string) ($rules['karmakala_type'] ?? 'sunrise');
        if (in_array($karmakalaType, ['sunrise', 'arunodaya'], true)) {
            return false;
        }

        $paksha = strtolower((string) ($rules['paksha'] ?? ''));
        $tithi = (int) (is_array($rules['tithi'] ?? null) ? ($rules['tithi'][0] ?? 0) : ($rules['tithi'] ?? 0));
        if ($paksha === 'krishna' && $tithi === 15) {
            return false;
        }

        return $this->monthRuleMatches($rules, $tomorrowCalendar);
    }

    /**
     * Resolve paksha constraint for a rule under the active calendar system.
     * Implementation: {@see Support\FestivalShared::resolveRulePaksha()}.
     */
    private function resolveRulePakshaForCalendar(array $rules, array $calendar, string $fallbackPaksha = 'Shukla'): array|string
    {
        return FestivalShared::resolveRulePaksha($rules, $calendar, $fallbackPaksha);
    }

    /**
     * Dynamically determine the expected Purnimanta month name based on the festival rule's paksha.
     * This fixes edge cases where the daily snapshot's Purnimanta month (from a Krishna sunrise)
     * mismatches a Shukla festival occurring later that same day.
     */
    private function getDynamicPurnimantaName(array $rules, array $calendar): string
    {
        $basePurnimanta = (string) ($calendar['Month_Purnimanta_En'] ?? $calendar['Month_Purnimanta'] ?? '');

        $resolvedRulePaksha = $this->resolveRulePakshaForCalendar($rules, $calendar, '');
        if ($resolvedRulePaksha !== '' && isset($calendar['Amanta_Index'])) {
            $rulePakshas = is_array($resolvedRulePaksha) ? $resolvedRulePaksha : [$resolvedRulePaksha];
            if (count($rulePakshas) === 1) {
                $rulePaksha = $rulePakshas[0];
                $amantaIdx = (int) $calendar['Amanta_Index'];
                // In Purnimanta, Shukla paksha takes the Amanta month name; Krishna paksha takes the next month.
                $purnimantaIdx = ($rulePaksha === 'Shukla') ? $amantaIdx : ($amantaIdx + 1) % 12;
                $purnimantaDynamic = Masa::from($purnimantaIdx)->getName('en');

                if ((bool) ($calendar['Is_Adhika'] ?? false) && $rulePaksha === 'Shukla') {
                    $purnimantaDynamic .= ' (Adhika)';
                }

                return $purnimantaDynamic;
            }
        }

        return $basePurnimanta;
    }
}
