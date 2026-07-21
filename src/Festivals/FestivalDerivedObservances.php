<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Festivals;

use Carbon\CarbonImmutable;

/**
 * Derived Vaishnava/Mahadwadashi/Adhika observances appended after primary resolution.
 *
 * Structure-only split from FestivalService. Algorithms unchanged.
 *
 * @see docs/FESTIVAL_REFACTOR_PARITY_CONTRACT.md
 */
trait FestivalDerivedObservances
{
    /**
     * @param array<int, array<string, mixed>> $festivals
     * @param array<int, array<string, mixed>> $festivalMeta
     * @param array<string, bool> $addedFestivalKeys
     */
    private function appendDerivedVaishnavaObservances(
        CarbonImmutable $date,
        array $todayDetails,
        array $tomorrowDetails,
        ?array $yesterdayDetails,
        array &$festivals,
        array &$festivalMeta,
        array &$addedFestivalKeys,
        string $selection = 'all'
    ): void {
        $todayVaishnava = (array) (($todayDetails['Ekadashi_Observance']['ekadashi_vaishnava'] ?? []));
        $yesterdayVaishnava = is_array($yesterdayDetails)
            ? (array) (($yesterdayDetails['Ekadashi_Observance']['ekadashi_vaishnava'] ?? []))
            : [];

        if (($todayVaishnava['fasting_day'] ?? null) === 'Today') {
            $rules = [
                'type' => 'derived_vaishnava_ekadashi',
                'description' => 'Vaishnava / ISKCON Ekadashi fasting day observed with devotion to Lord Vishnu',
                'deity' => 'Vishnu',
                'fasting' => true,
                'aliases' => ['Vaishnava Ekadashi'],
            ];
            if ($this->shouldIncludeFestivalRules($rules, $selection)) {
                $this->appendDerivedFestival(
                    name: 'ISKCON Ekadashi',
                    rules: $rules,
                    observanceDate: $date->toDateString(),
                    reason: 'vaishnava_ekadashi_today',
                    festivals: $festivals,
                    festivalMeta: $festivalMeta,
                    addedFestivalKeys: $addedFestivalKeys,
                );
            }
        }

        if (($yesterdayVaishnava['fasting_day'] ?? null) === 'Tomorrow_Mahadvadashi') {
            $status = (string) ($yesterdayVaishnava['status'] ?? 'Mahadvadashi');
            $mahadvadashi = $this->mahadvadashiSubtypeForVaishnavaDecision($yesterdayVaishnava);
            $rules = [
                'type' => 'derived_mahadvadashi',
                'description' => $mahadvadashi['description'],
                'deity' => 'Vishnu',
                'fasting' => true,
                'aliases' => $mahadvadashi['aliases'],
            ];
            if ($this->shouldIncludeFestivalRules($rules, $selection)) {
                $this->appendDerivedFestival(
                    name: 'Mahadwadashi',
                    rules: $rules,
                    observanceDate: $date->toDateString(),
                    reason: $status,
                    festivals: $festivals,
                    festivalMeta: $festivalMeta,
                    addedFestivalKeys: $addedFestivalKeys,
                );
            }
        }

        if (
            ($yesterdayVaishnava['fasting_day'] ?? null) === 'Tomorrow'
            && ($yesterdayVaishnava['case_key'] ?? null) === 'vaishnava_satsangijivan_ekadashi_vriddhi_second_day'
        ) {
            $rules = [
                'type' => 'derived_mahadvadashi',
                'description' => 'Unmilini Mahadwadashi occurs when Ekadashi extends to a second sunrise and Dwadashi begins on the selected fasting day.',
                'deity' => 'Vishnu',
                'fasting' => true,
                'aliases' => ['Unmilini Mahadwadashi', 'Vaishnava Mahadwadashi'],
            ];
            if ($this->shouldIncludeFestivalRules($rules, $selection)) {
                $this->appendDerivedFestival(
                    name: 'Mahadwadashi',
                    rules: $rules,
                    observanceDate: $date->toDateString(),
                    reason: 'Unmilini_Mahadwadashi',
                    festivals: $festivals,
                    festivalMeta: $festivalMeta,
                    addedFestivalKeys: $addedFestivalKeys,
                );
            }
        }

        if (
            ($todayVaishnava['fasting_day'] ?? null) === 'Today'
            && ($todayVaishnava['case_key'] ?? null) === 'vaishnava_trisparsha_dwadashi_kshaya'
        ) {
            $rules = [
                'type' => 'derived_mahadvadashi',
                'description' => 'Trisparsha Mahadwadashi occurs when Ekadashi, a lost Dwadashi and Trayodashi meet within the same sunrise-to-sunrise day.',
                'deity' => 'Vishnu',
                'fasting' => true,
                'aliases' => ['Trisparsha Mahadwadashi', 'Vaishnava Mahadwadashi'],
            ];
            if ($this->shouldIncludeFestivalRules($rules, $selection)) {
                $this->appendDerivedFestival(
                    name: 'Mahadwadashi',
                    rules: $rules,
                    observanceDate: $date->toDateString(),
                    reason: 'Trisparsha_Mahadvadashi',
                    festivals: $festivals,
                    festivalMeta: $festivalMeta,
                    addedFestivalKeys: $addedFestivalKeys,
                );
            }
        }

        if ($this->isVijayaMahadvadashi($todayDetails)) {
            $rules = [
                'type' => 'derived_mahadvadashi',
                'description' => 'Vijaya Mahadwadashi occurs when Shukla Dwadashi coincides with Shravana nakshatra.',
                'deity' => 'Vishnu',
                'fasting' => true,
                'aliases' => ['Vijaya Mahadwadashi', 'Vaishnava Mahadwadashi'],
            ];
            if ($this->shouldIncludeFestivalRules($rules, $selection)) {
                $this->appendDerivedFestival(
                    name: 'Mahadwadashi',
                    rules: $rules,
                    observanceDate: $date->toDateString(),
                    reason: 'Vijaya_Mahadvadashi',
                    festivals: $festivals,
                    festivalMeta: $festivalMeta,
                    addedFestivalKeys: $addedFestivalKeys,
                );
            }
        }

        foreach ($festivalMeta as $idx => $meta) {
            if (isset($addedFestivalKeys['ISKCON Ekadashi'])) {
                break;
            }

            if (!(bool) ($meta['is_ekadashi'] ?? false)) {
                continue;
            }

            $festival = $festivals[$idx] ?? null;
            if (!is_array($festival)) {
                continue;
            }

            $resolution = (array) ($festival['resolution'] ?? []);
            $ekadashiSelection = (array) ($resolution['decision']['ekadashi_selection'] ?? []);
            $fastingDay = (string) ($ekadashiSelection['fasting_day'] ?? '');
            $observanceDate = (string) ($resolution['observance_date'] ?? '');
            if ($observanceDate !== $date->toDateString()) {
                continue;
            }

            $iskconToday = $fastingDay === 'Today' || str_starts_with($fastingDay, 'Tomorrow');
            if (!$iskconToday) {
                continue;
            }

            $rules = [
                'type' => 'derived_vaishnava_ekadashi',
                'description' => 'Vaishnava / ISKCON Ekadashi fasting day observed with devotion to Lord Vishnu',
                'deity' => 'Vishnu',
                'fasting' => true,
                'aliases' => ['Vaishnava Ekadashi'],
            ];
            if ($this->shouldIncludeFestivalRules($rules, $selection)) {
                $this->appendDerivedFestival(
                    name: 'ISKCON Ekadashi',
                    rules: $rules,
                    observanceDate: $date->toDateString(),
                    reason: 'vaishnava_ekadashi_from_named_ekadashi_selection',
                    festivals: $festivals,
                    festivalMeta: $festivalMeta,
                    addedFestivalKeys: $addedFestivalKeys,
                );
            }
        }

        if (!isset($addedFestivalKeys['ISKCON Ekadashi'])) {
            foreach ($festivalMeta as $idx => $meta) {
                $rawName = (string) ($meta['raw_name'] ?? '');
                if (in_array($rawName, ['', 'ISKCON Ekadashi', 'Mahadwadashi'], true)) {
                    continue;
                }

                if (!(bool) ($meta['is_ekadashi'] ?? false)) {
                    continue;
                }

                $festival = $festivals[$idx] ?? null;
                if (!is_array($festival)) {
                    continue;
                }

                $resolution = (array) ($festival['resolution'] ?? []);
                if ((string) ($resolution['observance_date'] ?? '') !== $date->toDateString()) {
                    continue;
                }

                $winningReason = (string) (
                    $festival['rules_applied']['winning_reason_key']
                    ?? $festival['rules_applied']['winning_reason']
                    ?? $resolution['decision']['winning_reason']
                    ?? ''
                );
                if ($winningReason === 'target_tithi_entry_before_madhyahna') {
                    $rules = [
                        'type' => 'derived_vaishnava_ekadashi',
                        'description' => 'Vaishnava / ISKCON Ekadashi fasting day observed with devotion to Lord Vishnu',
                        'deity' => 'Vishnu',
                        'fasting' => true,
                        'aliases' => ['Vaishnava Ekadashi'],
                    ];
                    if ($this->shouldIncludeFestivalRules($rules, $selection)) {
                        $this->appendDerivedFestival(
                            name: 'ISKCON Ekadashi',
                            rules: $rules,
                            observanceDate: $date->addDay()->toDateString(),
                            reason: 'vaishnava_ekadashi_from_entry_day_named_ekadashi',
                            festivals: $festivals,
                            festivalMeta: $festivalMeta,
                            addedFestivalKeys: $addedFestivalKeys,
                        );
                    }

                    continue;
                }

                $rules = [
                    'type' => 'derived_vaishnava_ekadashi',
                    'description' => 'Vaishnava / ISKCON Ekadashi fasting day observed with devotion to Lord Vishnu',
                    'deity' => 'Vishnu',
                    'fasting' => true,
                    'aliases' => ['Vaishnava Ekadashi'],
                ];
                if ($this->shouldIncludeFestivalRules($rules, $selection)) {
                    $this->appendDerivedFestival(
                        name: 'ISKCON Ekadashi',
                        rules: $rules,
                        observanceDate: $date->toDateString(),
                        reason: 'vaishnava_ekadashi_from_named_ekadashi',
                        festivals: $festivals,
                        festivalMeta: $festivalMeta,
                        addedFestivalKeys: $addedFestivalKeys,
                    );
                }

                break;
            }
        }

        $isAdhikaToday = (bool) (($todayDetails['Hindu_Calendar']['Is_Adhika'] ?? false));
        $isAdhikaYesterday = is_array($yesterdayDetails)
            ? (bool) (($yesterdayDetails['Hindu_Calendar']['Is_Adhika'] ?? false))
            : false;
        $isAdhikaTomorrow = (bool) (($tomorrowDetails['Hindu_Calendar']['Is_Adhika'] ?? false));

        if ($isAdhikaToday && !$isAdhikaYesterday) {
            $rules = [
                'type' => 'derived_adhika_month_boundary',
                'description' => 'Beginning of Purushottam Maas, the intercalary lunar month dedicated to Lord Vishnu',
                'deity' => 'Vishnu',
            ];
            if ($this->shouldIncludeFestivalRules($rules, $selection)) {
                $this->appendDerivedFestival(
                    name: 'Purushottam Maas Begins',
                    rules: $rules,
                    observanceDate: $date->toDateString(),
                    reason: 'adhika_month_begin',
                    festivals: $festivals,
                    festivalMeta: $festivalMeta,
                    addedFestivalKeys: $addedFestivalKeys,
                );
            }
        }

        if ($isAdhikaToday && !$isAdhikaTomorrow) {
            $rules = [
                'type' => 'derived_adhika_month_boundary',
                'description' => 'Conclusion of Purushottam Maas, the intercalary lunar month dedicated to Lord Vishnu',
                'deity' => 'Vishnu',
            ];
            if ($this->shouldIncludeFestivalRules($rules, $selection)) {
                $this->appendDerivedFestival(
                    name: 'Purushottam Maas Ends',
                    rules: $rules,
                    observanceDate: $date->toDateString(),
                    reason: 'adhika_month_end',
                    festivals: $festivals,
                    festivalMeta: $festivalMeta,
                    addedFestivalKeys: $addedFestivalKeys,
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $vaishnavaDecision
     * @return array{aliases: array<int, string>, description: string}
     */
    private function mahadvadashiSubtypeForVaishnavaDecision(array $vaishnavaDecision): array
    {
        $caseKey = (string) ($vaishnavaDecision['case_key'] ?? '');
        $status = (string) ($vaishnavaDecision['status'] ?? '');

        $subtype = match ($caseKey) {
            'vaishnava_satsangijivan_dwadashi_vriddhi_mahadvadashi' => 'Vanjuli Mahadwadashi',
            'vaishnava_kshaya_mahadvadashi', 'vaishnava_unmillani_mahadvadashi' => 'Unmilini Mahadwadashi',
            'vaishnava_satsangijivan_ekadashi_vriddhi_dwadashi_kshaya', 'vaishnava_pakshavarddhini_mahadvadashi' => 'Pakshavarddhini Mahadwadashi',
            'vaishnava_satsangijivan_dashami_kshaya_mahadvadashi' => 'Vijaya Mahadwadashi',
            'vaishnava_dashami_55_ghati_vedha', 'vaishnava_trisparsha_dwadashi_kshaya' => 'Trisparsha Mahadwadashi',
            default => match ($status) {
                'Dvadashi_Vriddhi_Mahadvadashi' => 'Vanjuli Mahadwadashi',
                'Kshaya_Ekadashi', 'Unmillani_Mahadvadashi' => 'Unmilini Mahadwadashi',
                'Vriddhi_Ekadashi_Dvadashi_Kshaya', 'Pakshavarddhini_Mahadvadashi' => 'Pakshavarddhini Mahadwadashi',
                'Dashami_Kshaya' => 'Vijaya Mahadwadashi',
                'Viddha_Ekadashi', 'Trisparsha_Mahadvadashi' => 'Trisparsha Mahadwadashi',
                default => null,
            },
        };

        $aliases = ['Vaishnava Mahadwadashi'];
        if ($subtype !== null) {
            array_unshift($aliases, $subtype);
        }

        return [
            'aliases' => $aliases,
            'description' => $subtype !== null
                ? $subtype . ' fasting day observed in the Vaishnava Ekadashi tradition'
                : 'Mahadwadashi fasting day observed in the Vaishnava Ekadashi tradition',
        ];
    }

    /** @param array<string, mixed> $todayDetails */
    private function isVijayaMahadvadashi(array $todayDetails): bool
    {
        $tithiIndex = (int) ($todayDetails['Ekadashi_Observance']['phase_tithi_number'] ?? $todayDetails['Tithi']['index'] ?? 0);
        $phaseTithi = (($tithiIndex - 1) % 15) + 1;
        $paksha = (string) ($todayDetails['Tithi']['paksha'] ?? '');
        $nakshatra = (string) ($todayDetails['Nakshatra']['name'] ?? '');

        return $phaseTithi === 12 && $paksha === 'Shukla' && $nakshatra === 'Shravana';
    }

    /**
     * @param array<int, array<string, mixed>> $festivals
     * @param array<int, array<string, mixed>> $festivalMeta
     * @param array<string, bool> $addedFestivalKeys
     */
    private function appendDerivedFestival(
        string $name,
        array $rules,
        string $observanceDate,
        string $reason,
        array &$festivals,
        array &$festivalMeta,
        array &$addedFestivalKeys
    ): void {
        if (isset($addedFestivalKeys[$name])) {
            return;
        }

        $resolved = [
            'festival_name' => $name,
            'standard_date' => $observanceDate,
            'observance_date' => $observanceDate,
            'observance_note' => null,
            'decision' => [
                'winning_reason' => $reason,
                'winning_score' => 1000,
            ],
        ];

        $festivals[] = $this->buildFestivalPayload($name, $rules, $resolved);
        $festivalMeta[] = [
            'raw_name' => $name,
            'adhika_only' => false,
            'is_ekadashi' => str_contains($name, 'Ekadashi'),
        ];
        $addedFestivalKeys[$name] = true;
    }
}
