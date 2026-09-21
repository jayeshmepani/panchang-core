<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Shraddha;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Festivals\FestivalRuleEngine;
use JayeshMepani\PanchangCore\Festivals\Support\FestivalShared;

/**
 * Resolve Ṣaṇṇavati Śrāddha memberships for one civil date.
 *
 * The resolver intentionally separates the canonical 96-count taxonomy from
 * physical yearly manifestations. It does not truncate repeated Vyatipata,
 * Vaidhriti, or adhika-masa manifestations to make a Gregorian/lunisolar year
 * contain exactly 96 emitted rows.
 */
final readonly class ShannavatiResolver
{
    public function __construct(
        private FestivalRuleEngine $festivalRuleEngine,
        private ShraddhaKalaCalculator $shraddhaKala = new ShraddhaKalaCalculator,
    ) {}

    /**
     * @param array<int,array<string,mixed>> $resolvedFestivals
     *
     * @return array<string,mixed>
     */
    public function resolveForDate(
        CarbonImmutable $date,
        array $today,
        array $tomorrow,
        ?array $yesterday = null,
        ?callable $fetchHistoricalSnapshot = null,
        ShannavatiTraditionProfile $profile = ShannavatiTraditionProfile::DharmaSindhu,
        array $resolvedFestivals = [],
    ): array {
        $memberships = [];

        $amavasyaResolution = $this->matchTithiRuleOnDate(
            $date,
            $today,
            $tomorrow,
            $yesterday,
            $fetchHistoricalSnapshot,
            [
                'paksha' => 'Krishna',
                'tithi' => 15,
                'karmakala' => 'aparahna',
                'darsha_amavasya_aparahna_table' => true,
            ],
            'Shannavati Amavasya',
        );
        if ($amavasyaResolution !== null) {
            $month = $this->activeMonthName($today, preferAmanta: true);
            $monthKey = FestivalShared::normalizeMonthName($month);
            if ($monthKey !== '') {
                $memberships[] = $this->membership(
                    id: 'shannavati.amavasya.' . $monthKey,
                    category: ShannavatiCategory::Amavasya,
                    label: sprintf('%s Amavasya Shraddha', $month),
                    resolution: $amavasyaResolution,
                    today: $today,
                    profile: $profile,
                    extra: [
                        'canonical_month' => $month,
                        'month_kind' => (bool) (($today['Hindu_Calendar']['Is_Adhika'] ?? false)) ? 'adhika' : 'nija',
                    ],
                );
            }
        }

        foreach (ShannavatiCatalog::YUGADI_DHARMA_SINDHU as $rule) {
            $resolution = $this->matchTithiRuleOnDate(
                $date,
                $today,
                $tomorrow,
                $yesterday,
                $fetchHistoricalSnapshot,
                $rule,
                (string) $rule['label'],
            );
            if ($resolution !== null) {
                $memberships[] = $this->membership(
                    id: (string) $rule['id'],
                    category: ShannavatiCategory::Yugadi,
                    label: (string) $rule['label'],
                    resolution: $resolution,
                    today: $today,
                    profile: $profile,
                    extra: ['aliases' => array_values((array) ($rule['aliases'] ?? []))],
                );
            }
        }

        foreach (ShannavatiCatalog::manvadiRules($profile) as $rule) {
            $rule['karmakala'] = (($rule['paksha'] ?? 'Shukla') === 'Krishna') ? 'aparahna' : 'purvahna';
            $resolution = $this->matchTithiRuleOnDate(
                $date,
                $today,
                $tomorrow,
                $yesterday,
                $fetchHistoricalSnapshot,
                $rule,
                (string) ($rule['label'] ?? 'Manvadi Shraddha'),
            );
            if ($resolution !== null) {
                $memberships[] = $this->membership(
                    id: (string) $rule['id'],
                    category: ShannavatiCategory::Manvadi,
                    label: (string) ($rule['label'] ?? 'Manvadi Shraddha'),
                    resolution: $resolution,
                    today: $today,
                    profile: $profile,
                    extra: ['aliases' => array_values((array) ($rule['aliases'] ?? []))],
                );
            }
        }

        $sankrantiRashi = $this->resolvedSankrantiRashi($resolvedFestivals);
        if ($sankrantiRashi !== null && isset(ShannavatiCatalog::SANKRANTIS[$sankrantiRashi])) {
            $sankranti = ShannavatiCatalog::SANKRANTIS[$sankrantiRashi];
            $memberships[] = [
                'id' => 'shannavati.sankranti.' . $sankranti['key'],
                'category' => ShannavatiCategory::Sankranti->value,
                'label' => $sankranti['name'] . ' Shraddha',
                'date' => $date->toDateString(),
                'source_profile' => $profile->value,
                'nimitta' => [
                    'type' => 'solar_sankranti',
                    'rashi_index' => $sankrantiRashi,
                    'sankranti_jd' => $today['Resolution_Context']['sankranti_jd'] ?? $yesterday['Resolution_Context']['sankranti_jd'] ?? null,
                ],
                'shraddha_kala' => $this->shraddhaKalaPayload($today),
            ];
        }

        foreach ([
            ['index' => ShannavatiCatalog::NITYA_YOGA_WINDOW_INDICES['vyatipata'], 'name' => 'Vyatipata', 'category' => ShannavatiCategory::Vyatipata],
            ['index' => ShannavatiCatalog::NITYA_YOGA_WINDOW_INDICES['vaidhriti'], 'name' => 'Vaidhriti', 'category' => ShannavatiCategory::Vaidhriti],
        ] as $yogaRule) {
            $yoga = $this->resolveYogaOnDate(
                targetIndex: $yogaRule['index'],
                date: $date,
                today: $today,
                tomorrow: $tomorrow,
                yesterday: $yesterday,
            );
            if ($yoga !== null) {
                $category = $yogaRule['category'];
                $memberships[] = [
                    'id' => 'shannavati.' . $category->value . '.' . str_replace('-', '', $date->toDateString()),
                    'category' => $category->value,
                    'label' => $yogaRule['name'] . ' Shraddha',
                    'date' => $date->toDateString(),
                    'source_profile' => $profile->value,
                    'nominal_category_count' => ShannavatiCatalog::NOMINAL_COUNTS[$category->value],
                    'nimitta' => [
                        'type' => 'nitya_yoga',
                        'yoga_index' => $yogaRule['index'],
                        'yoga_name' => $yogaRule['name'],
                        'start_jd' => $yoga['start_jd'],
                        'end_jd' => $yoga['end_jd'],
                        'aparahna_overlap_seconds' => $yoga['today_overlap_seconds'],
                    ],
                    'shraddha_kala' => $this->shraddhaKalaPayload($today),
                ];
            }
        }

        for ($tithi = 1; $tithi <= 15; ++$tithi) {
            $rule = [
                'id' => sprintf('shannavati.mahalaya.%02d', $tithi),
                'label' => sprintf('Mahalaya %s Shraddha', $this->tithiLabel($tithi)),
                'month_amanta' => 'Bhadrapada',
                'month_purnimanta' => 'Ashvina',
                'paksha' => 'Krishna',
                'tithi' => $tithi,
                'karmakala' => 'aparahna',
            ];
            if ($tithi === 15) {
                $rule['darsha_amavasya_aparahna_table'] = true;
            }

            $resolution = $this->matchTithiRuleOnDate(
                $date,
                $today,
                $tomorrow,
                $yesterday,
                $fetchHistoricalSnapshot,
                $rule,
                (string) $rule['label'],
            );
            if ($resolution !== null) {
                $memberships[] = $this->membership(
                    id: (string) $rule['id'],
                    category: ShannavatiCategory::Mahalaya,
                    label: (string) $rule['label'],
                    resolution: $resolution,
                    today: $today,
                    profile: $profile,
                    extra: ['tithi' => $tithi],
                );
            }
        }

        foreach (ShannavatiCatalog::ASHTAKA_MONTHS as $monthRule) {
            $ashtakaRule = [
                ...$monthRule,
                'paksha' => 'Krishna',
                'tithi' => 8,
                'karmakala' => 'aparahna',
            ];

            $ashtakaDate = null;
            foreach ([$date->subDay(), $date, $date->addDay()] as $candidateAshtakaDate) {
                $candidateToday = $this->snapshotForDate($candidateAshtakaDate, $date, $today, $tomorrow, $yesterday, $fetchHistoricalSnapshot);
                $candidateTomorrow = $this->snapshotForDate($candidateAshtakaDate->addDay(), $date, $today, $tomorrow, $yesterday, $fetchHistoricalSnapshot);
                $candidateYesterday = $this->snapshotForDate($candidateAshtakaDate->subDay(), $date, $today, $tomorrow, $yesterday, $fetchHistoricalSnapshot);
                if ($candidateToday === null || $candidateTomorrow === null) {
                    continue;
                }

                $resolution = $this->matchTithiRuleOnDate(
                    $candidateAshtakaDate,
                    $candidateToday,
                    $candidateTomorrow,
                    $candidateYesterday,
                    $fetchHistoricalSnapshot,
                    $ashtakaRule,
                    $monthRule['name'] . ' Ashtaka Shraddha',
                );
                if ($resolution !== null) {
                    // A long Ashtami can be visible in the Aparahna window of
                    // two adjacent civil-day pairings.  The source rule still
                    // defines one Ashtaka anchor, followed by one Anvashtaka;
                    // do not rediscover the same anchor from its second day.
                    if ($this->hasEarlierAshtakaAnchor(
                        $candidateAshtakaDate,
                        $candidateYesterday,
                        $candidateToday,
                        $fetchHistoricalSnapshot,
                        $ashtakaRule,
                        $monthRule['name'] . ' Ashtaka Shraddha',
                    )) {
                        continue;
                    }

                    $ashtakaDate = $candidateAshtakaDate;
                    break;
                }
            }

            if ($ashtakaDate === null) {
                continue;
            }

            $delta = null;
            $role = null;
            if ($ashtakaDate->isSameDay($date->addDay())) {
                $delta = 1;
                $role = ['category' => ShannavatiCategory::Purvedyu, 'suffix' => 'purvedyu', 'label' => 'Purvedyu'];
            } elseif ($ashtakaDate->isSameDay($date)) {
                $delta = 0;
                $role = ['category' => ShannavatiCategory::Ashtaka, 'suffix' => 'ashtaka', 'label' => 'Ashtaka'];
            } elseif ($ashtakaDate->isSameDay($date->subDay())) {
                $delta = -1;
                $role = ['category' => ShannavatiCategory::Anvashtaka, 'suffix' => 'anvashtaka', 'label' => 'Anvashtaka'];
            }

            if ($role === null) {
                continue;
            }

            /** @var ShannavatiCategory $roleCategory */
            $roleCategory = $role['category'];
            $memberships[] = [
                'id' => sprintf('shannavati.%s.%s', $role['suffix'], $monthRule['key']),
                'category' => $roleCategory->value,
                'label' => sprintf('%s %s Shraddha', $monthRule['name'], $role['label']),
                'date' => $date->toDateString(),
                'source_profile' => $profile->value,
                'anchor_ashtaka_date' => $ashtakaDate->toDateString(),
                'nimitta' => [
                    'type' => $role['suffix'] === 'ashtaka' ? 'krishna_ashtami_aparahna' : 'relative_to_resolved_ashtaka',
                    'month_amanta' => $monthRule['month_amanta'],
                    'month_purnimanta' => $monthRule['month_purnimanta'],
                    'relative_days' => $delta === 1 ? -1 : ($delta === -1 ? 1 : 0),
                ],
                'shraddha_kala' => $this->shraddhaKalaPayload($today),
            ];
        }

        usort($memberships, static function (array $left, array $right): int {
            $categoryOrder = array_flip(array_keys(ShannavatiCatalog::NOMINAL_COUNTS));
            $leftCategory = (string) ($left['category'] ?? '');
            $rightCategory = (string) ($right['category'] ?? '');
            $cmp = ($categoryOrder[$leftCategory] ?? 999) <=> ($categoryOrder[$rightCategory] ?? 999);

            return $cmp !== 0 ? $cmp : strcmp((string) ($left['id'] ?? ''), (string) ($right['id'] ?? ''));
        });

        return [
            'is_shannavati_day' => $memberships !== [],
            'source_profile' => $profile->value,
            'canonical_nominal_count' => ShannavatiCatalog::nominalCount(),
            'canonical_category_counts' => ShannavatiCatalog::NOMINAL_COUNTS,
            'membership_count_today' => count($memberships),
            'memberships' => $memberships,
            'counting_note' => '96 is the canonical nominal taxonomy; runtime manifestations are not truncated to 96 physical dates.',
        ];
    }

    /** @return array<string,mixed>|null */
    private function matchTithiRuleOnDate(
        CarbonImmutable $date,
        array $today,
        array $tomorrow,
        ?array $yesterday,
        ?callable $fetchHistoricalSnapshot,
        array $rule,
        string $name,
    ): ?array {
        if (!$this->monthMatches($rule, $today)) {
            return null;
        }

        $karmakala = (string) ($rule['karmakala'] ?? (($rule['paksha'] ?? '') === 'Krishna' ? 'aparahna' : 'purvahna'));
        $engineRule = [
            'type' => 'tithi',
            'resolver' => 'classical',
            'paksha' => (string) ($rule['paksha'] ?? 'Shukla'),
            'tithi' => (int) ($rule['tithi'] ?? 0),
            'karmakala_type' => $karmakala,
            'strict_karmakala' => true,

            'vriddhi_preference' => 'first',
            'kshaya_preference' => 'merged_host_day',
        ];
        if ((bool) ($rule['darsha_amavasya_aparahna_table'] ?? false)) {
            $engineRule['darsha_amavasya_aparahna_table'] = true;
        }

        $resolved = $this->festivalRuleEngine->resolveMajorFestival($name, $engineRule, $date, $today, $tomorrow, $fetchHistoricalSnapshot);
        if ($resolved !== null && ($resolved['observance_date'] ?? null) === $date->toDateString()) {
            return $resolved;
        }

        if ($yesterday === null) {
            return null;
        }

        $resolvedYesterday = $this->festivalRuleEngine->resolveMajorFestival(
            $name,
            $engineRule,
            $date->subDay(),
            $yesterday,
            $today,
            $fetchHistoricalSnapshot,
        );

        return $resolvedYesterday !== null && ($resolvedYesterday['observance_date'] ?? null) === $date->toDateString()
            ? $resolvedYesterday
            : null;
    }

    private function monthMatches(array $rule, array $details): bool
    {
        if (!isset($rule['month_amanta']) && !isset($rule['month_purnimanta'])) {
            return true;
        }

        $calendar = (array) ($details['Hindu_Calendar'] ?? []);
        $calendarType = strtolower((string) ($calendar['Calendar_Type'] ?? 'amanta'));
        if ($calendarType === 'purnimanta') {
            $actual = (string) ($calendar['Month_Purnimanta_En'] ?? $calendar['Month_Purnimanta'] ?? '');
            $expected = (string) ($rule['month_purnimanta'] ?? $rule['month_amanta'] ?? '');
        } else {
            $actual = (string) ($calendar['Month_Amanta_En'] ?? $calendar['Month_Amanta'] ?? '');
            $expected = (string) ($rule['month_amanta'] ?? $rule['month_purnimanta'] ?? '');
        }

        return FestivalShared::normalizeMonthName($actual) === FestivalShared::normalizeMonthName($expected);
    }

    private function activeMonthName(array $details, bool $preferAmanta = false): string
    {
        $calendar = (array) ($details['Hindu_Calendar'] ?? []);
        if ($preferAmanta) {
            return (string) ($calendar['Month_Amanta_En'] ?? $calendar['Month_Amanta'] ?? '');
        }

        return strtolower((string) ($calendar['Calendar_Type'] ?? 'amanta')) === 'purnimanta'
            ? (string) ($calendar['Month_Purnimanta_En'] ?? $calendar['Month_Purnimanta'] ?? '')
            : (string) ($calendar['Month_Amanta_En'] ?? $calendar['Month_Amanta'] ?? '');
    }

    /** @return array<string,mixed> */
    private function membership(
        string $id,
        ShannavatiCategory $category,
        string $label,
        array $resolution,
        array $today,
        ShannavatiTraditionProfile $profile,
        array $extra = [],
    ): array {
        return [
            'id' => $id,
            'category' => $category->value,
            'label' => $label,
            'date' => (string) ($resolution['observance_date'] ?? ''),
            'source_profile' => $profile->value,
            'nimitta' => [
                'type' => 'tithi',
                'paksha' => $resolution['paksha'] ?? null,
                'tithi' => $resolution['required_tithi'] ?? null,
                'target_start_jd' => $resolution['target_tithi_start_jd'] ?? null,
                'target_end_jd' => $resolution['target_tithi_end_jd'] ?? null,
                'karmakala_type' => $resolution['karmakala_type'] ?? null,
                'winning_overlap_seconds' => $resolution['decision']['winning_window_overlap_seconds'] ?? null,
            ],
            'shraddha_kala' => $this->shraddhaKalaPayload($today),
            ...$extra,
        ];
    }

    /** @return array<string,mixed> */
    private function shraddhaKalaPayload(array $details): array
    {
        return [
            'kutapa' => $this->shraddhaKala->kutapa($details),
            'rauhina' => $this->shraddhaKala->rauhina($details),
            'aparahna' => $this->shraddhaKala->aparahna($details),
            'kutapa_to_aparahna_end' => $this->shraddhaKala->shraddhaMuhurtaPanchaka($details),
        ];
    }

    /** @return array{start_jd:float,end_jd:float,today_overlap_seconds:float}|null */
    private function resolveYogaOnDate(int $targetIndex, CarbonImmutable $date, array $today, array $tomorrow, ?array $yesterday): ?array
    {
        $todayAparahna = $this->shraddhaKala->aparahna($today);
        $yesterdayAparahna = $yesterday !== null ? $this->shraddhaKala->aparahna($yesterday) : null;
        $tomorrowAparahna = $this->shraddhaKala->aparahna($tomorrow);

        foreach ((array) ($today['Yoga_Windows'] ?? []) as $window) {
            if ((int) ($window['index'] ?? -1) !== $targetIndex) {
                continue;
            }

            $start = (float) ($window['start_jd'] ?? 0.0);
            $end = (float) ($window['end_jd'] ?? 0.0);
            if ($end <= $start) {
                continue;
            }

            $todayOverlap = $this->overlapSeconds($start, $end, $todayAparahna);
            if ($todayOverlap <= 0.0) {
                continue;
            }

            $yesterdayOverlap = $yesterdayAparahna === null ? 0.0 : $this->overlapSeconds($start, $end, $yesterdayAparahna);
            $tomorrowOverlap = $this->overlapSeconds($start, $end, $tomorrowAparahna);

            // Aparahna-vyapti is primary. For the rare case that the same long yoga
            // touches two Aparahnas, prefer the day with the greater overlap; ties go
            // to the earlier civil day for deterministic behavior.
            if ($yesterdayOverlap >= $todayOverlap - 1e-6) {
                continue;
            }

            if ($tomorrowOverlap > $todayOverlap + 1e-6) {
                continue;
            }

            return [
                'start_jd' => $start,
                'end_jd' => $end,
                'today_overlap_seconds' => $todayOverlap,
            ];
        }

        return null;
    }

    private function hasEarlierAshtakaAnchor(
        CarbonImmutable $candidateDate,
        ?array $candidateYesterday,
        array $candidateToday,
        ?callable $fetchHistoricalSnapshot,
        array $rule,
        string $name,
    ): bool {
        if ($candidateYesterday === null || $fetchHistoricalSnapshot === null) {
            return false;
        }

        $previousDate = $candidateDate->subDay();
        $previousPrevious = $fetchHistoricalSnapshot($previousDate->subDay());
        if (!is_array($previousPrevious) || $previousPrevious === []) {
            return false;
        }

        $previousResolution = $this->matchTithiRuleOnDate(
            $previousDate,
            $candidateYesterday,
            $candidateToday,
            $previousPrevious,
            $fetchHistoricalSnapshot,
            $rule,
            $name,
        );

        return $previousResolution !== null
            && ($previousResolution['observance_date'] ?? null) === $previousDate->toDateString();
    }

    /** @param array{start_jd:float,end_jd:float} $window */
    private function overlapSeconds(float $start, float $end, array $window): float
    {
        return max(0.0, (min($end, $window['end_jd']) - max($start, $window['start_jd'])) * 86400.0);
    }

    /** @param array<int,array<string,mixed>> $festivals */
    private function resolvedSankrantiRashi(array $festivals): ?int
    {
        foreach ($festivals as $festival) {
            $basis = (array) ($festival['calculation_basis'] ?? []);
            if (($basis['type'] ?? null) !== 'solar_sankranti') {
                continue;
            }

            $rashi = (array) ($basis['solar_rashi'] ?? []);
            if (isset($rashi['index']) && is_numeric($rashi['index'])) {
                $index = (int) $rashi['index'];
                if (isset(ShannavatiCatalog::SANKRANTIS[$index])) {
                    return $index;
                }
            }
        }

        return null;
    }

    private function snapshotForDate(
        CarbonImmutable $target,
        CarbonImmutable $date,
        array $today,
        array $tomorrow,
        ?array $yesterday,
        ?callable $fetchHistoricalSnapshot,
    ): ?array {
        if ($target->isSameDay($date)) {
            return $today;
        }

        if ($target->isSameDay($date->addDay())) {
            return $tomorrow;
        }

        if ($target->isSameDay($date->subDay())) {
            return $yesterday;
        }

        if ($fetchHistoricalSnapshot === null) {
            return null;
        }

        $snapshot = $fetchHistoricalSnapshot($target);

        return is_array($snapshot) ? $snapshot : null;
    }

    private function tithiLabel(int $tithi): string
    {
        return match ($tithi) {
            1 => 'Pratipada',
            2 => 'Dwitiya',
            3 => 'Tritiya',
            4 => 'Chaturthi',
            5 => 'Panchami',
            6 => 'Shashthi',
            7 => 'Saptami',
            8 => 'Ashtami',
            9 => 'Navami',
            10 => 'Dashami',
            11 => 'Ekadashi',
            12 => 'Dwadashi',
            13 => 'Trayodashi',
            14 => 'Chaturdashi',
            15 => 'Amavasya',
            default => 'Tithi ' . $tithi,
        };
    }
}
