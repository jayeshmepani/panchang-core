<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Festivals;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Core\Localization;
use LogicException;

/**
 * Day-after parents, Deepotsav guards, weekday-in-month and solar sankranti emit helpers.
 *
 * Structure-only split from FestivalService. Algorithms unchanged.
 *
 * @see docs/FESTIVAL_REFACTOR_PARITY_CONTRACT.md
 */
trait FestivalRelativeDaySupport
{
    /**
     * Govardhan / Annakut belong to the Deepotsav window around Deepavali.
     * Reject stray Kartika Pratipada resolutions that lack a same-day or preceding Lakshmi Puja civil day.
     */
    private function rejectDeepotsavGovardhanWithoutPrecedingDiwali(
        array $rules,
        CarbonImmutable $date,
        ?callable $fetchHistoricalSnapshot
    ): bool {
        if ($fetchHistoricalSnapshot === null
            || (string) ($rules['deepotsav_sequence'] ?? '') !== 'govardhan_annakut') {
            return false;
        }

        $lookbackDays = (int) ($rules['deepotsav_preceding_diwali_days'] ?? 4);

        for ($offset = 0; $offset <= $lookbackDays; $offset++) {
            $priorDate = $date->subDays($offset);
            $priorDetails = $fetchHistoricalSnapshot($priorDate);
            $priorTomorrowDetails = $fetchHistoricalSnapshot($priorDate->addDay());
            if (!is_array($priorDetails) || !is_array($priorTomorrowDetails)) {
                continue;
            }

            $priorCalendar = (array) ($priorDetails['Hindu_Calendar'] ?? []);
            $priorTomorrowCalendar = (array) ($priorTomorrowDetails['Hindu_Calendar'] ?? []);

            foreach (self::FESTIVALS as $diwaliName => $diwaliRules) {
                if (!(bool) ($diwaliRules['lock_deepavali_civil_day'] ?? false)) {
                    continue;
                }

                if ($this->monthRuleExcluded($diwaliRules, $priorCalendar)) {
                    continue;
                }

                $monthOk = $this->monthRuleMatches($diwaliRules, $priorCalendar)
                    || $this->canResolveAcrossMonthBoundary(
                        $diwaliRules,
                        $priorTomorrowCalendar,
                        self::usesClassicalResolver($diwaliRules),
                        $priorDetails
                    );
                if (!$monthOk) {
                    continue;
                }

                $resolved = $this->ruleEngine->resolveMajorFestival(
                    $diwaliName,
                    $diwaliRules,
                    $priorDate,
                    $priorDetails,
                    $priorTomorrowDetails
                );
                if ($resolved !== null
                    && (string) ($resolved['observance_date'] ?? '') === $priorDate->toDateString()) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Suppress a second adjacent emit when yesterday already locked the same
     * target-tithi interval (Pradosh truth-table; Kali Chaudas bahukala-purva).
     *
     * Classical resolve(..., today, tomorrow) on day-2 alone can re-select day-2
     * with a higher score even though day-1+day-2 already chose day-1.
     */
    private function previousDayAlreadyWonSameTargetInterval(
        string $name,
        array $rules,
        CarbonImmutable $date,
        array $todayDetails,
        ?array $yesterdayDetails,
        array $resolved
    ): bool {
        if ($yesterdayDetails === null) {
            return false;
        }

        $gatePradosh = $this->isPradoshRuleMetadata($rules);
        $gatePreferFirst = (bool) ($rules['prefer_first_karmakala'] ?? false)
            || ((string) ($rules['vriddhi_preference'] ?? '') === 'first'
                && (bool) ($rules['prefer_growth_before_score'] ?? false));
        $gateDiwali = (bool) ($rules['diwali_truth_table'] ?? false)
            || (bool) ($rules['darsha_amavasya_aparahna_table'] ?? false);
        if (!$gatePradosh && !$gatePreferFirst && !$gateDiwali) {
            return false;
        }

        $previousResolved = $this->ruleEngine->resolveMajorFestival($name, $rules, $date->subDay(), $yesterdayDetails, $todayDetails);
        if ($previousResolved === null || ($previousResolved['observance_date'] ?? null) !== $date->subDay()->toDateString()) {
            return false;
        }

        $currentStart = $resolved['target_tithi_start_jd'] ?? null;
        $currentEnd = $resolved['target_tithi_end_jd'] ?? null;
        $previousStart = $previousResolved['target_tithi_start_jd'] ?? null;
        $previousEnd = $previousResolved['target_tithi_end_jd'] ?? null;
        if (!is_numeric($currentStart) || !is_numeric($currentEnd) || !is_numeric($previousStart) || !is_numeric($previousEnd)) {
            return false;
        }

        return abs((float) $currentStart - (float) $previousStart) < 0.0000001
            && abs((float) $currentEnd - (float) $previousEnd) < 0.0000001;
    }

    /**
     * Resolve day_after festivals by checking if the parent festival was observed
     * on previous dates (today, yesterday, or up to 3 days back).
     *
     * Returns array of ['festival' => ..., 'meta' => ..., 'key' => ...] entries.
     */
    private function resolveDayAfterFestivals(
        CarbonImmutable $date,
        array $todayDetails,
        array $tomorrowDetails,
        ?array $yesterdayDetails,
        ?callable $fetchHistoricalSnapshot,
        array $addedFestivalKeys,
        string $selection = 'all'
    ): array {
        $results = [];

        foreach ($this->expandFestivalRules() as $name => $rules) {
            if ((string) ($rules['type'] ?? '') !== 'day_after') {
                continue;
            }

            if (!$this->shouldIncludeFestivalRules($rules, $selection)) {
                continue;
            }

            $parentName = (string) ($rules['parent_festival'] ?? '');
            $daysAfter = (int) ($rules['days_after'] ?? 1);

            if ($parentName === '') {
                continue;
            }

            $expectedParentDate = $daysAfter === 0 ? $date : $date->subDays($daysAfter);
            $parentFound = false;
            $parentObservanceDate = null;

            if ($daysAfter === 0) {
                $parentFound = $this->resolvedFestivalKeysContainParent($addedFestivalKeys, $parentName)
                    || $this->snapshotContainsFestivalName($todayDetails, $parentName)
                    || $this->parentObservanceMatchesDate(
                        $parentName,
                        $date,
                        $todayDetails,
                        $tomorrowDetails,
                        $yesterdayDetails,
                        $fetchHistoricalSnapshot,
                        true
                    ) !== null;
                $parentObservanceDate = $date->toDateString();
            } else {
                $parentObservanceDate = $this->parentObservanceMatchesDate(
                    $parentName,
                    $expectedParentDate,
                    $todayDetails,
                    $tomorrowDetails,
                    $yesterdayDetails,
                    $fetchHistoricalSnapshot,
                    true
                );
                $parentFound = $parentObservanceDate === $expectedParentDate->toDateString();
            }

            if (!$parentFound) {
                continue;
            }

            $key = 'day_after:' . $name . ':' . $date->toDateString();
            if (in_array($key, $addedFestivalKeys, true)) {
                continue;
            }

            $parentLabel = Localization::translate('Festival', $parentName);
            $observanceNote = $daysAfter === 0
                ? sprintf(
                    Localization::translate('String', 'observance_note_same_day_parent'),
                    $parentLabel
                )
                : sprintf(
                    Localization::translate('String', 'observance_note_day_after'),
                    $daysAfter,
                    $parentLabel
                );

            $resolved = [
                'festival_name' => $name,
                'standard_date' => $date->toDateString(),
                'observance_date' => $date->toDateString(),
                'observance_note' => $observanceNote,
                'decision' => [
                    'winning_reason' => $daysAfter === 0 ? 'same_day_linked_parent_festival' : 'day_after_parent_festival',
                    'parent_festival' => $parentName,
                    'parent_observance_date' => $parentObservanceDate ?? $expectedParentDate->toDateString(),
                    'days_after' => $daysAfter,
                    'winning_score' => 1000,
                ],
            ];

            $festival = $this->buildFestivalPayload($name, $rules, $resolved);
            $results[] = [
                'festival' => $festival,
                'meta' => ['winning_score' => 1000, 'is_day_after' => true],
                'key' => $key,
            ];
        }

        return $results;
    }

    /** Resolve parent festival observance on a candidate civil date (uses resolver output, not udaya tithi). */
    private function parentObservanceMatchesDate(
        string $parentName,
        CarbonImmutable $candidateDate,
        array $todayDetails,
        array $tomorrowDetails,
        ?array $yesterdayDetails,
        ?callable $fetchHistoricalSnapshot,
        bool $rejectWeakerAdjacentDuplicate = true
    ): ?string {
        if ($fetchHistoricalSnapshot === null) {
            return null;
        }

        $parentRules = self::FESTIVALS[$parentName] ?? null;
        if ($parentRules === null) {
            return null;
        }

        $parentSnapshot = $fetchHistoricalSnapshot($candidateDate);
        $parentTomorrow = $fetchHistoricalSnapshot($candidateDate->addDay());
        $parentYesterday = $fetchHistoricalSnapshot($candidateDate->subDay());
        $parentCalendar = (array) ($parentSnapshot['Hindu_Calendar'] ?? []);

        if (!$this->monthRuleMatches($parentRules, $parentCalendar)) {
            return null;
        }

        if (self::usesClassicalResolver($parentRules) || (bool) ($parentRules['nakshatra_only'] ?? false)) {
            $resolved = self::usesClassicalResolver($parentRules)
                ? $this->ruleEngine->resolveMajorFestival($parentName, $parentRules, $candidateDate, $parentSnapshot, $parentTomorrow)
                : $this->ruleEngine->resolveNakshatraBasedFestival($parentName, $parentRules, $candidateDate, $parentSnapshot, $parentTomorrow);

            if ($resolved !== null
                && ($resolved['observance_date'] ?? null) === $candidateDate->toDateString()
                && (!$rejectWeakerAdjacentDuplicate || !$this->previousDayHasStrongerParentResolution($parentName, $parentRules, $candidateDate, $parentYesterday, $parentSnapshot, $resolved))) {
                return $candidateDate->toDateString();
            }

            $resolvedYesterday = self::usesClassicalResolver($parentRules)
                ? $this->ruleEngine->resolveMajorFestival($parentName, $parentRules, $candidateDate->subDay(), $parentYesterday, $parentSnapshot)
                : $this->ruleEngine->resolveNakshatraBasedFestival($parentName, $parentRules, $candidateDate->subDay(), $parentYesterday, $parentSnapshot);

            if ($resolvedYesterday !== null && ($resolvedYesterday['observance_date'] ?? null) === $candidateDate->toDateString()) {
                return $candidateDate->toDateString();
            }

            return null;
        }

        $parentType = (string) ($parentRules['type'] ?? 'tithi');
        if ($parentType === 'solar_sankranti') {
            if ($this->shouldEmitSolarSankrantiToday($parentRules, $parentSnapshot, $parentYesterday)) {
                return $candidateDate->toDateString();
            }

            return null;
        }

        $tithi = $parentSnapshot['Tithi'] ?? null;
        if ($tithi === null) {
            return null;
        }

        $absoluteTithi = (int) ($tithi['index'] ?? 0);
        $paksha = (string) ($tithi['paksha'] ?? 'Shukla');
        $relativeTithi = $absoluteTithi > 15 ? $absoluteTithi - 15 : $absoluteTithi;
        $rulePaksha = $parentRules['paksha'] ?? null;
        $tithiForMatching = $rulePaksha !== null ? $relativeTithi : $absoluteTithi;

        if ($this->matchesFestivalRules($candidateDate, $parentRules, $tithiForMatching, $paksha, $parentSnapshot)) {
            return $candidateDate->toDateString();
        }

        return null;
    }

    private function shouldEmitSolarSankrantiToday(
        array $rules,
        array $todayDetails,
        ?array $yesterdayDetails
    ): bool {
        $targetRashi = (int) ($rules['rashi'] ?? -1);
        if ($targetRashi < 0) {
            return false;
        }

        $afterSunsetRule = (bool) ($rules['after_sunset_next_day_punya_rule'] ?? false);
        $todayRashi = $todayDetails['Resolution_Context']['sankranti_rashi'] ?? null;
        $todayMatches = $todayRashi !== null && (int) $todayRashi === $targetRashi;

        if (!$afterSunsetRule) {
            return $todayMatches;
        }

        if ($todayMatches && $this->isSankrantiAfterSunset($todayDetails)) {
            return false;
        }

        if ($yesterdayDetails !== null) {
            $yesterdayRashi = $yesterdayDetails['Resolution_Context']['sankranti_rashi'] ?? null;
            if ($yesterdayRashi !== null
                && (int) $yesterdayRashi === $targetRashi
                && $this->isSankrantiAfterSunset($yesterdayDetails)) {
                return true;
            }
        }

        return $todayMatches && !$this->isSankrantiAfterSunset($todayDetails);
    }

    private function isSankrantiAfterSunset(array $details): bool
    {
        $ctx = (array) ($details['Resolution_Context'] ?? []);
        $sankrantiJd = $ctx['sankranti_jd'] ?? null;
        $sunsetJd = $ctx['sunset_jd'] ?? null;

        if (is_numeric($sankrantiJd) && is_numeric($sunsetJd)) {
            return (float) $sankrantiJd > (float) $sunsetJd + 1e-9;
        }

        return false;
    }

    private function matchesWeekdayInLunarMonth(
        CarbonImmutable $date,
        array $rules,
        array $todayDetails,
        ?callable $fetchHistoricalSnapshot
    ): bool {
        if (!isset($rules['weekday']) || $date->dayOfWeek !== (int) $rules['weekday']) {
            return false;
        }

        $calendar = (array) ($todayDetails['Hindu_Calendar'] ?? []);
        if (!$this->monthRuleMatches($rules, $calendar)) {
            return false;
        }

        if (($rules['type'] ?? '') === 'weekday_in_month') {
            return true;
        }

        if (!isset($rules['nth']) || $fetchHistoricalSnapshot === null) {
            return false;
        }

        $targetNth = (int) $rules['nth'];
        $weekday = (int) $rules['weekday'];
        $count = 0;

        for ($offset = 0; $offset < 45; $offset++) {
            $scanDate = $date->subDays($offset);
            $snapshot = $offset === 0 ? $todayDetails : $fetchHistoricalSnapshot($scanDate);
            $scanCalendar = (array) ($snapshot['Hindu_Calendar'] ?? []);

            if (!$this->monthRuleMatches($rules, $scanCalendar)) {
                break;
            }

            if ($scanDate->dayOfWeek === $weekday) {
                ++$count;
            }
        }

        return $count === $targetNth;
    }

    private function shouldIncludeFestivalRules(array $rules, string $selection): bool
    {
        $normalized = strtolower($selection);
        $isVrat = (bool) ($rules['fasting'] ?? false);

        return match ($normalized) {
            'all' => true,
            'vrats' => $isVrat,
            'festivals' => !$isVrat,
            default => throw new LogicException('Unknown festival selection: ' . $selection),
        };
    }

    private function previousDayHasStrongerParentResolution(
        string $parentName,
        array $parentRules,
        CarbonImmutable $candidateDate,
        array $parentYesterday,
        array $parentSnapshot,
        array $resolved
    ): bool {
        $currentReason = (string) (
            $resolved['decision']['winning_reason_key']
            ?? $resolved['decision']['winning_reason']
            ?? ''
        );
        $previousResolved = self::usesClassicalResolver($parentRules)
            ? $this->ruleEngine->resolveMajorFestival($parentName, $parentRules, $candidateDate->subDay(), $parentYesterday, $parentSnapshot)
            : ((bool) ($parentRules['nakshatra_only'] ?? false)
                ? $this->ruleEngine->resolveNakshatraBasedFestival($parentName, $parentRules, $candidateDate->subDay(), $parentYesterday, $parentSnapshot)
                : null);

        if ($previousResolved === null || ($previousResolved['observance_date'] ?? null) !== $candidateDate->subDay()->toDateString()) {
            return false;
        }

        $previousReason = (string) (
            $previousResolved['decision']['winning_reason_key']
            ?? $previousResolved['decision']['winning_reason']
            ?? ''
        );
        $currentScore = (int) ($resolved['decision']['winning_score'] ?? -1);
        $previousScore = (int) ($previousResolved['decision']['winning_score'] ?? -1);

        if ($previousScore > $currentScore && $previousScore >= 0) {
            return true;
        }

        return in_array($currentReason, ['target_at_sunrise', 'target tithi at sunrise'], true)
            && in_array($previousReason, ['target_at_karmakala', 'target tithi at karmakala', 'target_covers_full_karmakala'], true);
    }

    /** Collect canonical + alias + tradition-variant names for a parent festival key. */
    private function parentFestivalNameSet(string $parentName): array
    {
        $names = [$parentName];
        foreach ((array) (self::FESTIVALS[$parentName]['aliases'] ?? []) as $alias) {
            $names[] = (string) $alias;
        }

        foreach ((array) (self::FESTIVALS[$parentName]['traditions'] ?? []) as $tradition) {
            if (!is_array($tradition)) {
                continue;
            }

            $variant = (string) ($tradition['variant_name'] ?? '');
            if ($variant !== '') {
                $names[] = $variant;
            }

            foreach ((array) ($tradition['aliases'] ?? []) as $alias) {
                $names[] = (string) $alias;
            }
        }

        return array_values(array_unique(array_filter($names, static fn (string $value): bool => $value !== '')));
    }

    /** True when the current day pass already resolved the parent festival. */
    private function resolvedFestivalKeysContainParent(array $addedFestivalKeys, string $parentName): bool
    {
        foreach ($this->parentFestivalNameSet($parentName) as $name) {
            if (isset($addedFestivalKeys[$name]) || in_array($name, $addedFestivalKeys, true)) {
                return true;
            }
        }

        return false;
    }

    /** True when the day snapshot already lists the parent festival (or one of its aliases). */
    private function snapshotContainsFestivalName(array $snapshot, string $parentName): bool
    {
        $names = $this->parentFestivalNameSet($parentName);

        foreach ((array) ($snapshot['Festivals'] ?? []) as $festival) {
            if (!is_array($festival)) {
                continue;
            }

            $festivalName = (string) ($festival['name'] ?? $festival['resolution']['festival_name'] ?? '');
            if ($festivalName !== '' && in_array($festivalName, $names, true)) {
                return true;
            }

            foreach ((array) ($festival['aliases'] ?? []) as $alias) {
                if (in_array((string) $alias, $names, true)) {
                    return true;
                }
            }
        }

        return false;
    }
}
