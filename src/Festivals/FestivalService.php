<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Festivals;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Core\Enums\Paksha;
use JayeshMepani\PanchangCore\Core\Localization;
use LogicException;

/**
 * Hindu Festival Calculation Service
 * Calculates all major Hindu/Sanatan festivals based on Tithi, Nakshatra, and special rules.
 *
 * CRITICAL: This service requires PanchangService for actual tithi calculations
 * Do NOT use standalone - always use through PanchangService
 *
 * Registry: {@see FestivalCatalog}. Presentation: {@see FestivalPayloadPresentation}.
 * Matchers/helpers: {@see FestivalSpecialMatchers}, {@see FestivalMonthTithiSupport},
 * {@see FestivalDerivedObservances}, {@see FestivalRelativeDaySupport}.
 *
 * @see docs/FESTIVAL_REFACTOR_PARITY_CONTRACT.md
 */
class FestivalService
{
    use FestivalPayloadPresentation;
    use FestivalSpecialMatchers;
    use FestivalMonthTithiSupport;
    use FestivalDerivedObservances;
    use FestivalRelativeDaySupport;

    /**
     * BC aliases — registry lives in {@see FestivalCatalog} (structure-only split).
     * Existing `FestivalService::FESTIVALS` / `::MONTHS` / `::TITHI_VRATAS` references keep working.
     */
    public const TITHI_VRATAS = FestivalCatalog::TITHI_VRATAS;

    public const FESTIVALS = FestivalCatalog::FESTIVALS;

    public const MONTHS = FestivalCatalog::MONTHS;

    public function __construct(
        private readonly FestivalRuleEngine $ruleEngine
    ) {
    }

    public static function getFestivalCount(): int
    {
        return FestivalCatalog::getFestivalCount();
    }

    public static function getCatalogFestivalCount(): int
    {
        return FestivalCatalog::getCatalogFestivalCount();
    }

    public static function getCatalogVratCount(): int
    {
        return FestivalCatalog::getCatalogVratCount();
    }

    /**
     * Get festivals for a specific date using actual panchang data
     * This is the PRIMARY method - uses real tithi from PanchangService.
     *
     * @return array<int, array<string, mixed>>
     */
    public function resolveFestivalsForDate(
        CarbonImmutable $date,
        array $todayDetails,
        array $tomorrowDetails,
        ?array $yesterdayDetails = null,
        ?callable $fetchHistoricalSnapshot = null,
        bool $includeExtraWinners = false,
        string $selection = 'all',
        ?string $tradition = null
    ): array
    {
        $festivals = [];
        $festivalMeta = [];
        $addedFestivalKeys = [];
        $tithi = $todayDetails['Tithi'] ?? null;

        if (!$tithi) {
            return [];
        }

        $tithiNum = (int) ($tithi['index'] ?? 0);
        $paksha = $tithi['paksha'] ?? 'Shukla';

        foreach ($this->expandFestivalRules() as $name => $rules) {
            if (!$this->shouldIncludeFestivalRules($rules, $selection)) {
                continue;
            }

            if ((string) ($rules['resolution_alias_of'] ?? '') !== '') {
                continue;
            }

            $calendar = $todayDetails['Hindu_Calendar'] ?? [];
            $isAdhika = (bool) ($calendar['Is_Adhika'] ?? false);
            $isKshaya = (bool) ($calendar['Is_Kshaya'] ?? false);
            $type = (string) ($rules['type'] ?? 'tithi');

            // Adhika/Nija filtering logic for lunar (tithi) observances.
            // Default behavior is Nija-only unless explicitly marked otherwise.
            $adhikaAllowed = (bool) ($rules['allow_adhika'] ?? false) || (bool) ($rules['allows_adhika'] ?? false);
            $adhikaOnly = (bool) ($rules['adhika_only'] ?? false);

            if ($type === 'tithi') {
                if ($isAdhika && !$adhikaAllowed && !$adhikaOnly) {
                    continue; // regular tithi observances suppressed in Adhika month
                }

                if (!$isAdhika && $adhikaOnly) {
                    continue; // Adhika-only festival cannot occur in Nija month
                }
            }

            $isClassical = self::usesClassicalResolver($rules);
            $isNakshatra = (bool) ($rules['nakshatra_only'] ?? false);

            // Check Hindu month match for tithi-based festivals (respect configured calendar type)
            if ($this->monthRuleExcluded($rules, (array) ($todayDetails['Hindu_Calendar'] ?? []))) {
                continue;
            }

            if (isset($rules['require_ayana']) && !$this->calendarAyanaMatches((array) ($todayDetails['Hindu_Calendar'] ?? []), (string) $rules['require_ayana'])) {
                continue;
            }

            if (isset($rules['require_sayana_ritu']) && !$this->calendarSayanaRituMatches((array) ($todayDetails['Hindu_Calendar'] ?? []), (string) $rules['require_sayana_ritu'])) {
                continue;
            }

            if (isset($rules['sun_sign']) && !$this->sunSignRuleMatches((int) $rules['sun_sign'], $todayDetails)) {
                continue;
            }

            if ($this->hasMonthRule($rules)) {
                $monthOk = $this->isKrishnaAmavasyaRule($rules)
                    ? $this->namedAmavasyaAttributedMonthMatches($rules, $todayDetails, $tomorrowDetails)
                    : ($this->monthRuleMatches($rules, (array) ($todayDetails['Hindu_Calendar'] ?? []))
                        || $this->canResolveAcrossMonthBoundary(
                            $rules,
                            (array) ($tomorrowDetails['Hindu_Calendar'] ?? []),
                            $isClassical,
                            $todayDetails
                        ));
                if (!$monthOk) {
                    continue; // Skip this festival for this month
                }
            }

            if ($isClassical) {
                $resolved = $this->ruleEngine->resolveMajorFestival($name, $rules, $date, $todayDetails, $tomorrowDetails, $fetchHistoricalSnapshot);
                if ($resolved !== null
                    && $resolved['observance_date'] === $date->toDateString()
                    && !$this->previousDayAlreadyWonSameTargetInterval($name, $rules, $date, $todayDetails, $yesterdayDetails, $resolved)
                    && !$this->rejectResolvedFestivalForDay($rules, $todayDetails)
                    && !$this->rejectDeepotsavGovardhanWithoutPrecedingDiwali($rules, $date, $fetchHistoricalSnapshot)
                    && !isset($addedFestivalKeys[$name])) {
                    $festivals[] = $this->buildFestivalPayload($name, $rules, $resolved);
                    $festivalMeta[] = [
                        'raw_name' => $name,
                        'adhika_only' => $adhikaOnly,
                        'is_ekadashi' => str_contains($name, 'Ekadashi'),
                    ];
                    $addedFestivalKeys[$name] = true;
                } elseif ($yesterdayDetails !== null && !(bool) ($rules['prefer_growth_before_score'] ?? false) && !isset($addedFestivalKeys[$name])) {
                    // Back-fill festivals whose resolved observance date is today but
                    // whose tithi decision was derived from yesterday->today.
                    $resolvedYesterday = $this->ruleEngine->resolveMajorFestival($name, $rules, $date->subDay(), $yesterdayDetails, $todayDetails, $fetchHistoricalSnapshot);
                    if ($resolvedYesterday !== null
                        && $resolvedYesterday['observance_date'] === $date->toDateString()
                        && !$this->rejectResolvedFestivalForDay($rules, $todayDetails)
                        && !$this->rejectDeepotsavGovardhanWithoutPrecedingDiwali($rules, $date, $fetchHistoricalSnapshot)) {
                        $festivals[] = $this->buildFestivalPayload($name, $rules, $resolvedYesterday);
                        $festivalMeta[] = [
                            'raw_name' => $name,
                            'adhika_only' => $adhikaOnly,
                            'is_ekadashi' => str_contains($name, 'Ekadashi'),
                        ];
                        $addedFestivalKeys[$name] = true;
                    }
                }

                if ($yesterdayDetails !== null
                    && !isset($addedFestivalKeys[$name])
                    && $this->matchesShiftedVaishnavaEkadashiName($rules, $todayDetails, $yesterdayDetails)) {
                    $resolvedShifted = [
                        'festival_name' => $name,
                        'required_tithi' => (int) ($rules['tithi'] ?? 11),
                        'paksha' => (string) ($rules['paksha'] ?? ($yesterdayDetails['Tithi']['paksha'] ?? 'Shukla')),
                        'calendar_type' => (string) ($todayDetails['Hindu_Calendar']['Calendar_Type'] ?? 'amanta'),
                        'karmakala_type' => (string) ($rules['karmakala_type'] ?? 'arunodaya'),
                        'standard_date' => $date->subDay()->toDateString(),
                        'observance_date' => $date->toDateString(),
                        'observance_note' => Localization::translate('String', 'observance_note_shifted_to_dwadashi_satsangijivan'),
                        'decision' => [
                            'winning_reason' => 'vaishnava_satsangijivan_shifted_named_ekadashi',
                            'ekadashi_selection' => (array) ($yesterdayDetails['Ekadashi_Observance']['ekadashi_vaishnava'] ?? []),
                        ],
                    ];
                    $festivals[] = $this->buildFestivalPayload($name, $rules, $resolvedShifted);
                    $festivalMeta[] = [
                        'raw_name' => $name,
                        'adhika_only' => $adhikaOnly,
                        'is_ekadashi' => str_contains($name, 'Ekadashi'),
                    ];
                    $addedFestivalKeys[$name] = true;
                }
            } elseif ($isNakshatra) {
                // Handle nakshatra-based festivals
                $resolved = $this->ruleEngine->resolveNakshatraBasedFestival($name, $rules, $date, $todayDetails, $tomorrowDetails);
                if ($resolved !== null && $resolved['observance_date'] === $date->toDateString() && !isset($addedFestivalKeys[$name])) {
                    $festivals[] = $this->buildFestivalPayload($name, $rules, $resolved);
                    $festivalMeta[] = [
                        'raw_name' => $name,
                        'adhika_only' => $adhikaOnly,
                        'is_ekadashi' => str_contains($name, 'Ekadashi'),
                    ];
                    $addedFestivalKeys[$name] = true;
                } elseif ($yesterdayDetails !== null && !isset($addedFestivalKeys[$name])) {
                    $resolvedYesterday = $this->ruleEngine->resolveNakshatraBasedFestival($name, $rules, $date->subDay(), $yesterdayDetails, $todayDetails);
                    if ($resolvedYesterday !== null && $resolvedYesterday['observance_date'] === $date->toDateString()) {
                        $festivals[] = $this->buildFestivalPayload($name, $rules, $resolvedYesterday);
                        $festivalMeta[] = [
                            'raw_name' => $name,
                            'adhika_only' => $adhikaOnly,
                            'is_ekadashi' => str_contains($name, 'Ekadashi'),
                        ];
                        $addedFestivalKeys[$name] = true;
                    }
                }
            } elseif ($type === 'solar_sankranti') {
                if ($this->shouldEmitSolarSankrantiToday($rules, $todayDetails, $yesterdayDetails)
                    && !isset($addedFestivalKeys[$name])) {
                    $festivals[] = $this->buildFestivalPayload($name, $rules);
                    $festivalMeta[] = [
                        'raw_name' => $name,
                        'adhika_only' => $adhikaOnly,
                        'is_ekadashi' => str_contains($name, 'Ekadashi'),
                    ];
                    $addedFestivalKeys[$name] = true;
                }
            } elseif (in_array($type, ['nth_weekday_in_month', 'weekday_in_month'], true)) {
                if ($this->matchesWeekdayInLunarMonth($date, $rules, $todayDetails, $fetchHistoricalSnapshot)
                    && !isset($addedFestivalKeys[$name])) {
                    $festivals[] = $this->buildFestivalPayload($name, $rules);
                    $festivalMeta[] = [
                        'raw_name' => $name,
                        'adhika_only' => $adhikaOnly,
                        'is_ekadashi' => str_contains($name, 'Ekadashi'),
                    ];
                    $addedFestivalKeys[$name] = true;
                }
            } elseif ($type === 'anvadhan') {
                if ($this->matchesAnvadhanRule($date, $todayDetails, $tomorrowDetails, $yesterdayDetails, $fetchHistoricalSnapshot)
                    && !isset($addedFestivalKeys[$name])) {
                    $festivals[] = $this->buildFestivalPayload($name, $rules);
                    $festivalMeta[] = [
                        'raw_name' => $name,
                        'adhika_only' => $adhikaOnly,
                        'is_ekadashi' => str_contains($name, 'Ekadashi'),
                    ];
                    $addedFestivalKeys[$name] = true;
                }
            } elseif ($type === 'day_after_anvadhan') {
                $dayBeforeYesterday = $fetchHistoricalSnapshot === null ? null : $fetchHistoricalSnapshot($date->subDays(2));
                $dayAfterToday = $fetchHistoricalSnapshot === null ? null : $fetchHistoricalSnapshot($date->addDay());
                if ($yesterdayDetails !== null
                    && (is_array($dayBeforeYesterday) || $dayBeforeYesterday === null)
                    && (is_array($dayAfterToday) || $dayAfterToday === null)
                    && $this->matchesAnvadhanRule(
                        $date->subDay(),
                        $yesterdayDetails,
                        $todayDetails,
                        is_array($dayBeforeYesterday) ? $dayBeforeYesterday : null,
                        static fn (CarbonImmutable $lookupDate): ?array => $lookupDate->isSameDay($date->addDay())
                            ? (is_array($dayAfterToday) ? $dayAfterToday : null)
                            : null
                    )
                    && !isset($addedFestivalKeys[$name])) {
                    $festivals[] = $this->buildFestivalPayload($name, $rules);
                    $festivalMeta[] = [
                        'raw_name' => $name,
                        'adhika_only' => $adhikaOnly,
                        'is_ekadashi' => str_contains($name, 'Ekadashi'),
                    ];
                    $addedFestivalKeys[$name] = true;
                }
            } elseif ($type === 'brahma_savarni_manvadi') {
                if ($this->matchesBrahmaSavarniManvadiRule($todayDetails)
                    && !isset($addedFestivalKeys[$name])) {
                    $festivals[] = $this->buildFestivalPayload($name, $rules);
                    $festivalMeta[] = [
                        'raw_name' => $name,
                        'adhika_only' => $adhikaOnly,
                        'is_ekadashi' => str_contains($name, 'Ekadashi'),
                    ];
                    $addedFestivalKeys[$name] = true;
                }
            } elseif ($type === 'sheetala_ashtami') {
                if ($this->matchesSheetalaAshtamiRule($todayDetails, $tomorrowDetails, $yesterdayDetails, $rules)
                    && !isset($addedFestivalKeys[$name])) {
                    $festivals[] = $this->buildFestivalPayload($name, $rules);
                    $festivalMeta[] = [
                        'raw_name' => $name,
                        'adhika_only' => $adhikaOnly,
                        'is_ekadashi' => str_contains($name, 'Ekadashi'),
                    ];
                    $addedFestivalKeys[$name] = true;
                }
            } elseif ($type === 'attukal_pongal') {
                if ($this->matchesAttukalPongalRule($todayDetails, $tomorrowDetails)
                    && !isset($addedFestivalKeys[$name])) {
                    $festivals[] = $this->buildFestivalPayload($name, $rules);
                    $festivalMeta[] = [
                        'raw_name' => $name,
                        'adhika_only' => $adhikaOnly,
                        'is_ekadashi' => str_contains($name, 'Ekadashi'),
                    ];
                    $addedFestivalKeys[$name] = true;
                }
            } elseif ($type === 'chapchar_kut') {
                if ($this->matchesChapcharKutRule($date, $todayDetails, $fetchHistoricalSnapshot)
                    && !isset($addedFestivalKeys[$name])) {
                    $festivals[] = $this->buildFestivalPayload($name, $rules);
                    $festivalMeta[] = [
                        'raw_name' => $name,
                        'adhika_only' => $adhikaOnly,
                        'is_ekadashi' => str_contains($name, 'Ekadashi'),
                    ];
                    $addedFestivalKeys[$name] = true;
                }
            } elseif ($type === 'bhanu_saptami') {
                if ($this->matchesBhanuSaptamiRule($date, $todayDetails, $tomorrowDetails)
                    && !isset($addedFestivalKeys[$name])) {
                    $festivals[] = $this->buildFestivalPayload($name, $rules);
                    $festivalMeta[] = [
                        'raw_name' => $name,
                        'adhika_only' => $adhikaOnly,
                        'is_ekadashi' => str_contains($name, 'Ekadashi'),
                    ];
                    $addedFestivalKeys[$name] = true;
                }
            } elseif ($type === 'kalashtami') {
                if ($this->matchesKalashtamiRule($todayDetails, $tomorrowDetails, $yesterdayDetails)
                    && !isset($addedFestivalKeys[$name])) {
                    $festivals[] = $this->buildFestivalPayload($name, $rules);
                    $festivalMeta[] = [
                        'raw_name' => $name,
                        'adhika_only' => $adhikaOnly,
                        'is_ekadashi' => str_contains($name, 'Ekadashi'),
                    ];
                    $addedFestivalKeys[$name] = true;
                }
            } elseif ($this->matchesFestivalRules($date, $rules, $tithiNum, $paksha, $todayDetails)) {
                $festivals[] = $this->buildFestivalPayload($name, $rules);
                $festivalMeta[] = [
                    'raw_name' => $name,
                    'adhika_only' => $adhikaOnly,
                    'is_ekadashi' => str_contains($name, 'Ekadashi'),
                ];
                $addedFestivalKeys[$name] = true;
            }
        }

        $this->appendDerivedVaishnavaObservances(
            $date,
            $todayDetails,
            $tomorrowDetails,
            $yesterdayDetails,
            $festivals,
            $festivalMeta,
            $addedFestivalKeys,
            $selection
        );

        // During Adhika Maas, if special Adhika Ekadashi(s) are present on a date,
        // suppress regular Ekadashi labels for that same date to avoid double tagging.
        if ((bool) (($todayDetails['Hindu_Calendar']['Is_Adhika'] ?? false)) && $festivals !== []) {
            $hasAdhikaOnlyEkadashi = false;
            foreach ($festivalMeta as $meta) {
                if ((bool) ($meta['is_ekadashi'] ?? false) && (bool) ($meta['adhika_only'] ?? false)) {
                    $hasAdhikaOnlyEkadashi = true;
                    break;
                }
            }

            if ($hasAdhikaOnlyEkadashi) {
                $filteredFestivals = [];
                $filteredMeta = [];
                foreach ($festivals as $idx => $festival) {
                    $meta = $festivalMeta[$idx] ?? ['is_ekadashi' => false, 'adhika_only' => false];
                    $rawName = (string) ($meta['raw_name'] ?? '');
                    $isEkadashi = (bool) ($meta['is_ekadashi'] ?? false);
                    $isAdhikaOnly = (bool) ($meta['adhika_only'] ?? false);
                    if (!$isEkadashi || $isAdhikaOnly || $rawName === 'ISKCON Ekadashi') {
                        $filteredFestivals[] = $festival;
                        $filteredMeta[] = $meta;
                    }
                }

                $festivals = $filteredFestivals;
                $festivalMeta = $filteredMeta;
            }
        }

        // Resolve day_after festivals (e.g. Holi after Holika Dahan)
        // These require checking if the parent festival was observed on a previous date
        $dayAfterFestivals = $this->resolveDayAfterFestivals(
            $date,
            $todayDetails,
            $tomorrowDetails,
            $yesterdayDetails,
            $fetchHistoricalSnapshot,
            $addedFestivalKeys,
            $selection
        );
        foreach ($dayAfterFestivals as $item) {
            $festivals[] = $item['festival'];
            $festivalMeta[] = $item['meta'];
            $addedFestivalKeys[] = $item['key'];
        }

        // Post-processing: Deduplicate same-date entries based on aliases / name_key.
        // Festival (non-fasting) and vrat (fasting) identities must both survive even when
        // one lists the other as a public alias (e.g. Vaishakha Purnima ↔ Chitra Pournami).
        $mergedFestivals = [];
        $keysToRemove = [];

        foreach ($festivals as $index => $fest) {
            $name = (string) ($fest['name'] ?? '');
            $key = (string) ($fest['name_key'] ?? $name);
            $aliases = $fest['aliases'] ?? [];
            $fasting = (bool) ($fest['fasting'] ?? false);

            foreach ($festivals as $otherIndex => $other) {
                if ($index === $otherIndex) {
                    continue;
                }

                $otherName = (string) ($other['name'] ?? '');
                $otherKey = (string) ($other['name_key'] ?? $otherName);
                $otherAliases = $other['aliases'] ?? [];
                $otherFasting = (bool) ($other['fasting'] ?? false);

                // Never collapse festival vs vrat — unique identity sets must stay complete.
                if ($fasting !== $otherFasting) {
                    continue;
                }

                // Same canonical identity twice on one day → drop duplicate payload.
                if ($key !== '' && $key === $otherKey) {
                    if ($index > $otherIndex) {
                        $keysToRemove[$index] = true;
                    }

                    continue;
                }

                $aliasHit = in_array($otherName, $aliases, true)
                    || in_array($otherKey, $aliases, true)
                    || in_array($name, $otherAliases, true)
                    || in_array($key, $otherAliases, true);

                if (!$aliasHit) {
                    continue;
                }

                // Prefer month-named Sankashti (Lambodara, Dwijapriya, …) over generic family root.
                if ($key === 'Sankashti Chaturthi' && str_contains($otherKey, 'Sankashti') && $otherKey !== $key) {
                    $keysToRemove[$index] = true;
                    continue;
                }

                if ($otherKey === 'Sankashti Chaturthi' && str_contains($key, 'Sankashti') && $key !== $otherKey) {
                    $keysToRemove[$otherIndex] = true;
                    continue;
                }

                // Prefer the entry whose name_key matches its own identity (canonical) over a pure alias label.
                $selfIsCanonical = $key === $name || ($key !== '' && !in_array($key, $otherAliases, true));
                $otherIsCanonical = $otherKey === $otherName || ($otherKey !== '' && !in_array($otherKey, $aliases, true));

                if ($selfIsCanonical && !$otherIsCanonical) {
                    $keysToRemove[$otherIndex] = true;
                } elseif (!$selfIsCanonical && $otherIsCanonical) {
                    $keysToRemove[$index] = true;
                } elseif (count($aliases) < count($otherAliases)) {
                    // Mutual aliases: keep the more specific (fewer aliases) entry.
                    $keysToRemove[$otherIndex] = true;
                } elseif (count($otherAliases) < count($aliases)) {
                    $keysToRemove[$index] = true;
                } elseif ($index > $otherIndex) {
                    $keysToRemove[$index] = true;
                }
            }
        }

        foreach ($festivals as $index => $fest) {
            if (!isset($keysToRemove[$index])) {
                $mergedFestivals[] = $fest;
            }
        }

        $hasVinayaka = false;
        foreach ($mergedFestivals as $fest) {
            $festKey = (string) ($fest['name_key'] ?? $fest['resolution']['festival_name'] ?? $fest['name'] ?? '');
            if ($festKey === 'Vinayaka Chaturthi') {
                $hasVinayaka = true;
                break;
            }
        }

        if ($hasVinayaka) {
            return array_values(array_filter(
                $mergedFestivals,
                static fn (array $fest): bool => (string) ($fest['name_key'] ?? $fest['resolution']['festival_name'] ?? $fest['name'] ?? '') !== 'Vinayaki Chaturthi'
            ));
        }

        return $mergedFestivals;
    }

    public static function usesClassicalResolver(array $rules): bool
    {
        $resolver = (string) ($rules['resolver'] ?? '');

        // Engine-backed resolvers (generic classical + named special decision tables).
        return in_array($resolver, ['classical', 'phuldolotsava'], true);
    }

    /** Daily Sanatan observances from tithi-based vrata prescriptions. */
    public function getDailyObservances(array $panchangDetails): array
    {
        $out = [];
        $tithi = $panchangDetails['Tithi'] ?? [];
        $idx = (int) ($tithi['index'] ?? 0);
        $paksha = (string) ($tithi['paksha'] ?? '');

        if ($idx > 15) {
            $idx -= 15;
        }

        if (isset(self::TITHI_VRATAS[$idx])) {
            $rule = self::TITHI_VRATAS[$idx];
            $benefit = $rule['benefit'] ?? '';
            if ($idx === 15) {
                $benefit = $paksha === 'Shukla' ? $rule['purnima_benefit'] : $rule['amavasya_benefit'];
            }

            $out[] = [
                'name' => Localization::translate('Vrata', $rule['vrata']),
                'deity' => Localization::translate('Deity', $rule['deity']),
                'benefit' => Localization::translate('Benefit', $benefit),
                'paksha' => $paksha,
                'paksha_name' => Paksha::{$paksha}->getName(),
            ];
        }

        return $out;
    }

    /**
     * Year-wide aggregation is intentionally blocked at this layer.
     * Use date-wise festival computation through PanchangService.
     */
    public function getFestivalsForYear(int $year, string $pakshaSystem = 'Amanta'): array
    {
        throw new LogicException('Year-wide festival calculation is intentionally disabled in FestivalService. Use date-wise calculation via PanchangService.');
    }

    /**
     * Expand merged tradition-aware festival definitions into effective runtime rules.
     *
     * This allows the registry to keep one canonical root entry while still emitting
     * distinct observance variants such as Smarta vs Uddhav/Swaminarayan.
     *
     * @return array<string, array<string, mixed>>
     */
    private function expandFestivalRules(): array
    {
        $expanded = [];

        foreach (self::FESTIVALS as $name => $rules) {
            $traditions = $rules['traditions'] ?? null;
            if (!is_array($traditions)) {
                $expanded[$name] = $rules;
                continue;
            }

            $baseRules = $rules;
            unset($baseRules['traditions']);

            foreach ($traditions as $traditionKey => $traditionRules) {
                if (!is_array($traditionRules)) {
                    continue;
                }

                $variantName = $traditionRules['variant_name'];
                $variantAliases = array_map(
                    static fn (mixed $alias): string => (string) $alias,
                    (array) ($traditionRules['aliases'] ?? [])
                );

                $effectiveTraditionRules = $traditionRules;
                unset($effectiveTraditionRules['variant_name'], $effectiveTraditionRules['aliases']);

                $effectiveRules = array_replace($baseRules, $effectiveTraditionRules);
                if ($variantAliases !== []) {
                    $effectiveRules['aliases'] = array_values(array_unique($variantAliases));
                }

                $effectiveRules['merged_tradition_key'] = (string) $traditionKey;
                // Distinct tradition variants keep distinct identity keys (e.g. Rama Navami Smarta vs Vaishnava
                // can fall on adjacent civil days). Opt-in collapse only via share_parent_identity.
                if ((bool) ($traditionRules['share_parent_identity'] ?? false)) {
                    $effectiveRules['identity_key'] = $name;
                    $effectiveRules['display_name'] = $variantName;
                }

                $expanded[$variantName] = $effectiveRules;
            }
        }

        return $expanded;
    }

    private function rejectResolvedFestivalForDay(array $rules, array $todayDetails): bool
    {
        $tradition = function_exists('config') ? config('panchang.festivals.default_tradition', 'Smarta') : 'Smarta';
        $isVaishnavaMode = strcasecmp((string) $tradition, 'Vaishnava') === 0;

        $isEkadashiRule = ((int) ($rules['tithi'] ?? 0) === 11) && ((bool) ($rules['fasting'] ?? false) || (bool) ($rules['ekadashi_nirnay_table'] ?? false));
        $todayTithi = (int) ($todayDetails['Tithi']['index'] ?? 0);

        if ($isVaishnavaMode && $isEkadashiRule && $todayTithi === 11) {
            $vaishnava = (array) (($todayDetails['Ekadashi_Observance']['ekadashi_vaishnava'] ?? []));
            if ((string) ($vaishnava['fasting_day'] ?? '') !== 'Today') {
                return true;
            }
        }

        if ((bool) ($rules['require_vaishnava_ekadashi_today'] ?? false)) {
            $vaishnava = (array) (($todayDetails['Ekadashi_Observance']['ekadashi_vaishnava'] ?? []));
            if ((string) ($vaishnava['fasting_day'] ?? '') === 'Today') {
                return false;
            }

            $parana = $todayDetails['Ekadashi_Observance']['parana'] ?? null;

            return $parana === null || $parana === [] || (int) ($todayDetails['Ekadashi_Observance']['phase_tithi_number'] ?? 0) !== 12;
        }

        return false;
    }

    private function matchesShiftedVaishnavaEkadashiName(array $rules, array $todayDetails, array $yesterdayDetails): bool
    {
        $isEkadashiRule = ((int) ($rules['tithi'] ?? 0) === 11) && ((bool) ($rules['fasting'] ?? false) || (bool) ($rules['ekadashi_nirnay_table'] ?? false) || (bool) ($rules['require_vaishnava_ekadashi_today'] ?? false));
        if (!$isEkadashiRule) {
            return false;
        }

        $yesterdayVaishnava = (array) (($yesterdayDetails['Ekadashi_Observance']['ekadashi_vaishnava'] ?? []));
        if ((string) ($yesterdayVaishnava['fasting_day'] ?? '') !== 'Tomorrow_Mahadvadashi') {
            return false;
        }

        $todayPhaseTithi = (int) (($todayDetails['Ekadashi_Observance']['phase_tithi_number'] ?? $todayDetails['Tithi']['index'] ?? 0));
        $yesterdayPhaseTithi = (int) (($yesterdayDetails['Ekadashi_Observance']['phase_tithi_number'] ?? $yesterdayDetails['Tithi']['index'] ?? 0));
        if ($todayPhaseTithi !== 12 || $yesterdayPhaseTithi !== 11) {
            return false;
        }

        $yesterdayPaksha = (string) ($yesterdayDetails['Tithi']['paksha'] ?? '');
        if (isset($rules['paksha']) || isset($rules['paksha_amanta']) || isset($rules['paksha_purnimanta'])) {
            $rulePaksha = $this->resolveRulePakshaForCalendar($rules, (array) ($yesterdayDetails['Hindu_Calendar'] ?? []), 'Shukla');
            $rulePakshas = $rulePaksha === 'Both' ? ['Shukla', 'Krishna'] : (is_array($rulePaksha) ? $rulePaksha : [$rulePaksha]);
            if (!in_array($yesterdayPaksha, $rulePakshas, true)) {
                return false;
            }
        }

        if ($this->hasMonthRule($rules)
            && !$this->monthRuleMatches($rules, (array) ($yesterdayDetails['Hindu_Calendar'] ?? []))
            && !$this->monthRuleMatches($rules, (array) ($todayDetails['Hindu_Calendar'] ?? []))) {
            return false;
        }

        if ((bool) ($rules['adhika_only'] ?? false)) {
            $todayAdhika = (bool) ($todayDetails['Hindu_Calendar']['Is_Adhika'] ?? false);
            $yesterdayAdhika = (bool) ($yesterdayDetails['Hindu_Calendar']['Is_Adhika'] ?? false);

            return $todayAdhika || $yesterdayAdhika;
        }

        return true;
    }

    /**
     * Check if date matches festival rules using ACTUAL panchang data
     * NO PLACEHOLDERS - uses real tithi, nakshatra from PanchangService.
     */
    private function matchesFestivalRules(
        CarbonImmutable $date,
        array $rules,
        int $tithiNum,
        string $paksha,
        array $panchangDetails
    ): bool {
        $type = (string) ($rules['type'] ?? 'tithi');

        // Dependent festivals (e.g. Holi after Holika Dahan) are resolved by orchestration layer.
        if (in_array($type, ['day_after', 'anvadhan', 'day_after_anvadhan', 'brahma_savarni_manvadi', 'sheetala_ashtami', 'attukal_pongal', 'chapchar_kut', 'bhanu_saptami', 'kalashtami', 'solar_sankranti'], true)) {
            return false;
        }

        // Handle Adhika Masa rules: only allow tithi-based festivals in Adhika Masa if explicitly allowed.
        if ($type === 'tithi') {
            $isAdhika = (bool) ($panchangDetails['Hindu_Calendar']['Is_Adhika'] ?? false);
            $adhikaOnly = (bool) ($rules['adhika_only'] ?? false);
            $allowsAdhika = ($rules['allows_adhika'] ?? false) || ($rules['allow_adhika'] ?? false);

            if ($isAdhika && !$adhikaOnly && !$allowsAdhika) {
                return false; // Regular lunar festivals are blocked in Adhika Masa
            }

            if (!$isAdhika && $adhikaOnly) {
                return false; // Adhika-only lunar festivals shouldn't appear in Nija Masa
            }
        }

        // Check tithi match
        if (isset($rules['tithi'])) {
            $ruleTithis = is_array($rules['tithi']) ? $rules['tithi'] : [$rules['tithi']];
            $matchedTithi = false;
            foreach ($ruleTithis as $rTithi) {
                if ($tithiNum === $rTithi) {
                    $matchedTithi = true;
                    break;
                }

                // If paksha is Both, check Krishna equivalent
                $rulePaksha = $this->resolveRulePakshaForCalendar($rules, (array) ($panchangDetails['Hindu_Calendar'] ?? []), 'Shukla');
                if ($rulePaksha === 'Both' && $tithiNum === ($rTithi + 15)) {
                    $matchedTithi = true;
                    break;
                }
            }

            if (!$matchedTithi) {
                return false;
            }
        }

        // Check paksha match
        if (isset($rules['paksha']) || isset($rules['paksha_amanta']) || isset($rules['paksha_purnimanta'])) {
            $rulePaksha = $this->resolveRulePakshaForCalendar($rules, (array) ($panchangDetails['Hindu_Calendar'] ?? []), 'Shukla');
            $rulePakshas = $rulePaksha === 'Both' ? ['Shukla', 'Krishna'] : (is_array($rulePaksha) ? $rulePaksha : [$rulePaksha]);
            if (!in_array($paksha, $rulePakshas, true)) {
                return false;
            }
        }

        // Check weekday match
        if (isset($rules['weekday']) && $date->dayOfWeek !== $rules['weekday']) {
            return false;
        }

        if (isset($rules['require_ayana']) && !$this->calendarAyanaMatches((array) ($panchangDetails['Hindu_Calendar'] ?? []), (string) $rules['require_ayana'])) {
            return false;
        }

        if (isset($rules['require_sayana_ritu']) && !$this->calendarSayanaRituMatches((array) ($panchangDetails['Hindu_Calendar'] ?? []), (string) $rules['require_sayana_ritu'])) {
            return false;
        }

        if (isset($rules['sun_sign']) && !$this->sunSignRuleMatches((int) $rules['sun_sign'], $panchangDetails)) {
            return false;
        }

        // Check Hindu month match for tithi-based rules
        if ($this->hasMonthRule($rules)
            && !$this->monthRuleMatches($rules, (array) ($panchangDetails['Hindu_Calendar'] ?? []))) {
            return false;
        }

        // Check fixed Gregorian dates
        if (in_array((string) ($rules['type'] ?? ''), ['fixed_date', 'solar'], true) && isset($rules['month'], $rules['day']) && ((int) $rules['month'] !== $date->month || (int) $rules['day'] !== $date->day)) {
            return false;
        }

        // solar_sankranti and weekday-in-lunar-month types are resolved in resolveFestivalsForDate.
        if (($rules['type'] ?? '') === 'solar_sankranti' || in_array((string) ($rules['type'] ?? ''), ['nth_weekday_in_month', 'weekday_in_month'], true)) {
            return false;
        }

        // Check nakshatra match (if specified)
        if (isset($rules['nakshatra'], $panchangDetails['Nakshatra']['name'])
            && !$this->nakshatraRuleMatches((string) $rules['nakshatra'], (array) $panchangDetails['Nakshatra'])) {
            return false;
        }

        // weekday_in_month / nth_weekday_in_month handled in resolveFestivalsForDate.
        return !in_array((string) ($rules['type'] ?? ''), ['weekday_in_month', 'nth_weekday_in_month'], true);
    }

    /**
     * Match require_ayana against nirayana ayana (sidereal) keys/names.
     * Accepts stable keys (Uttarayana/Dakshinayana) and legacy English/localized labels.
     */
    private function calendarAyanaMatches(array $calendar, string $required): bool
    {
        $required = trim($required);
        if ($required === '') {
            return true;
        }

        $candidates = array_filter([
            (string) ($calendar['Ayana_Key'] ?? ''),
            (string) ($calendar['Nirayana_Ayana_Key'] ?? ''),
            (string) ($calendar['Ayana'] ?? ''),
            (string) ($calendar['Nirayana_Ayana'] ?? ''),
        ], static fn (string $v): bool => $v !== '');

        return $this->ayanaLabelMatches($required, $candidates);
    }

    /**
     * Match require_sayana_ritu against tropical (sayana) ṛtu keys/names.
     * Accepts stable keys (Vasanta…) and English glosses (Spring…).
     */
    private function calendarSayanaRituMatches(array $calendar, string $required): bool
    {
        $required = trim($required);
        if ($required === '') {
            return true;
        }

        $candidates = array_filter([
            (string) ($calendar['Sayana_Ritu_Key'] ?? ''),
            (string) ($calendar['Sayana_Ritu'] ?? ''),
        ], static fn (string $v): bool => $v !== '');

        return $this->rituLabelMatches($required, $candidates);
    }

    /** @param list<string> $candidates */
    private function ayanaLabelMatches(string $required, array $candidates): bool
    {
        $requiredNorm = $this->normalizeAyanaRituToken($required);
        $aliases = match (true) {
            str_contains($requiredNorm, 'uttar') || str_contains($requiredNorm, 'north') => [
                'uttarayana', 'northwardcourse', 'uttarayananorthwardcourse',
            ],
            str_contains($requiredNorm, 'dakshin') || str_contains($requiredNorm, 'south') => [
                'dakshinayana', 'southwardcourse', 'dakshinayanasouthwardcourse',
            ],
            default => [$requiredNorm],
        };

        foreach ($candidates as $candidate) {
            $candNorm = $this->normalizeAyanaRituToken($candidate);
            if ($candNorm === $requiredNorm || in_array($candNorm, $aliases, true)) {
                return true;
            }

            foreach ($aliases as $alias) {
                if ($alias !== '' && str_contains($candNorm, $alias)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param list<string> $candidates */
    private function rituLabelMatches(string $required, array $candidates): bool
    {
        $requiredNorm = $this->normalizeAyanaRituToken($required);
        $map = [
            'vasanta' => ['vasanta', 'spring', 'vasantaspring'],
            'grishma' => ['grishma', 'summer', 'grishmasummer'],
            'varsha' => ['varsha', 'monsoon', 'varshamonsoon', 'rainy'],
            'sharad' => ['sharad', 'autumn', 'sharadautumn', 'fall'],
            'hemanta' => ['hemanta', 'prewinter', 'hemantaprewinter'],
            'shishira' => ['shishira', 'winter', 'shishirawinter'],
        ];

        $aliases = [$requiredNorm];
        foreach ($map as $group) {
            foreach ($group as $token) {
                if ($requiredNorm === $token || str_contains($requiredNorm, $token)) {
                    $aliases = array_values(array_unique(array_merge($aliases, $group)));
                    break 2;
                }
            }
        }

        foreach ($candidates as $candidate) {
            $candNorm = $this->normalizeAyanaRituToken($candidate);
            if ($candNorm === $requiredNorm) {
                return true;
            }

            foreach ($aliases as $alias) {
                if ($alias !== '' && ($candNorm === $alias || str_contains($candNorm, $alias))) {
                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeAyanaRituToken(string $value): string
    {
        $value = strtolower(trim($value));
        $value = strtr($value, [
            'ā' => 'a', 'ī' => 'i', 'ū' => 'u', 'ṛ' => 'ri',
            'ś' => 'sh', 'ṣ' => 'sh', 'ñ' => 'n', 'ṅ' => 'n', 'ṇ' => 'n',
            '(' => ' ', ')' => ' ', '-' => ' ', '_' => ' ', '/' => ' ',
        ]);
        $value = preg_replace('/\s+/', '', $value) ?? $value;

        return $value;
    }

}
