<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Festivals;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Core\Localization;

/**
 * Classical festival day resolver façade.
 *
 * Public API stays here; implementation lives in focused traits:
 * {@see FestivalRuleTruthTables}, {@see FestivalRuleCandidates},
 * {@see FestivalRuleChandraDarshana}, {@see FestivalRuleNakshatra},
 * {@see FestivalRuleCoreSupport}. Numeric thresholds: {@see FestivalRuleConstants}.
 * Shared pure helpers: {@see Support\FestivalShared}.
 *
 * @see docs/FESTIVAL_MODULE_ARCHITECTURE.md
 * @see docs/FESTIVAL_REFACTOR_PARITY_CONTRACT.md
 */
class FestivalRuleEngine
{
    use FestivalRuleCoreSupport;
    use FestivalRuleCandidates;
    use FestivalRuleTruthTables;
    use FestivalRuleChandraDarshana;
    use FestivalRuleNakshatra;

    /** Resolve major Hindu/Sanatan observance day by karmakala precedence and tithi continuity. */
    public function resolveMajorFestival(
        string $festivalName,
        array $rule,
        CarbonImmutable $date,
        array $today,
        array $tomorrow
    ): ?array {
        $this->assertValidFestivalRule($festivalName, $rule);
        if (!$this->isExecutableResolverProfile($rule)) {
            return null;
        }

        // Named Ashwina Amavasya under purnimanta is the prior (Mahalaya) Amavasya and must
        // use sunrise attribution (e.g. 2026-10-10). Deepavali/Chopda/Lakshmi always keep
        // amanta Ashwina aparahna civil-day logic under both calendars (e.g. 2026-11-08).
        $calendarTypeEarly = strtolower((string) (
            $today['Hindu_Calendar']['Calendar_Type']
            ?? AstroCore::getConfig('panchang.defaults.calendar_type', 'amanta')
        ));
        if (
            $calendarTypeEarly === 'purnimanta'
            && (bool) ($rule['darsha_amavasya_aparahna_table'] ?? false)
            && !$this->isDeepavaliCivilDayLocked($festivalName, $rule)
            && $this->normalizeMonthName((string) ($rule['month_purnimanta'] ?? $rule['month_amanta'] ?? '')) === 'ashvina'
        ) {
            $rule = array_merge($rule, [
                'darsha_amavasya_aparahna_table' => false,
                'karmakala_type' => 'sunrise',
                'require_sunrise_vyapini' => true,
                'strict_karmakala' => true,
            ]);
        }

        $ctxToday = (array) ($today['Resolution_Context'] ?? []);
        $ctxTomorrow = (array) ($tomorrow['Resolution_Context'] ?? []);
        if ($ctxToday === [] || $ctxTomorrow === []) {
            return null;
        }

        // Check if this is a nakshatra-only based festival (no tithi requirement)
        if (isset($rule['nakshatra_only']) && $rule['nakshatra_only']) {
            return $this->resolveNakshatraFestival($festivalName, $rule, $date, $today, $tomorrow);
        }

        // Satsangi Jeevan 4.60 Phuldolotsava: sunrise Uttara Phalguni across Purnima/Pratipada.
        if ($this->isPhuldolotsavaRule($rule)) {
            return $this->resolvePhuldolotsavaFestival($festivalName, $rule, $date, $today, $tomorrow);
        }

        if ((bool) ($rule['chandra_darshana_visibility'] ?? false)) {
            return $this->resolveChandraDarshanaFestival($festivalName, $rule, $date, $today, $tomorrow);
        }

        $rulePaksha = $this->resolveRulePaksha($rule, (array) ($today['Hindu_Calendar'] ?? []), $currentPaksha = (string) ($today['Tithi']['paksha'] ?? 'Shukla'));

        // Handle 'Both' paksha (bi-monthly recurring festivals like Pradosh Vrat) or arrays
        if ($rulePaksha === 'Both') {
            $paksha = $currentPaksha;
        } elseif (is_array($rulePaksha)) {
            $paksha = in_array($currentPaksha, $rulePaksha, true) ? $currentPaksha : (string) ($rulePaksha[0] ?? 'Shukla');
        } else {
            $paksha = $rulePaksha;
        }

        $configuredTithi = (int) ($rule['tithi'] ?? 0);
        $ruleTithiOptions = array_values(array_unique(array_map(
            static fn ($value): int => (int) $value,
            array_filter((array) ($rule['tithi_options'] ?? []), static fn ($value): bool => (int) $value > 0)
        )));
        $requiredTithis = array_values(array_unique(array_filter(
            array_merge($configuredTithi > 0 ? [$configuredTithi] : [], $ruleTithiOptions),
            static fn (int $value): bool => $value > 0
        )));

        if ($requiredTithis === []) {
            return null;
        }

        $requiredTithi = $configuredTithi > 0 ? $configuredTithi : $requiredTithis[0];
        $todayAbs = (int) ($ctxToday['tithi_index_abs'] ?? 0);
        $tomorrowAbs = (int) ($ctxTomorrow['tithi_index_abs'] ?? 0);
        $targetIntervals = $this->deriveTargetIntervals($requiredTithis, $paksha, $todayAbs, $tomorrowAbs, $ctxToday, $ctxTomorrow);
        if ($targetIntervals === []) {
            return null;
        }

        $preferHigherTithi = (bool) ($rule['prefer_higher_tithi_option'] ?? false);
        if ($preferHigherTithi) {
            usort(
                $targetIntervals,
                static fn (array $left, array $right): int => $right['tithi'] <=> $left['tithi'],
            );
        }

        $karmakalaType = $this->normalizeKarmakalaType((string) ($rule['karmakala_type'] ?? 'sunrise'));
        $vriddhiPreference = (string) ($rule['vriddhi_preference'] ?? ($karmakalaType === 'sunrise' ? 'first' : 'last'));
        $kshayaPreference = (string) ($rule['kshaya_preference'] ?? 'first');
        $strictKarmakala = (bool) ($rule['strict_karmakala'] ?? ($karmakalaType !== 'sunrise'));
        $preferFirstKarmakala = (bool) ($rule['prefer_first_karmakala'] ?? false);
        $preferGrowthBeforeScore = (bool) ($rule['prefer_growth_before_score'] ?? false);
        $preferNakshatra = (bool) ($rule['prefer_nakshatra'] ?? false);
        $requiredWeekday = $rule['weekday'] ?? null;
        $bestResolution = null;

        foreach ($targetIntervals as $intervalSpec) {
            $targetInterval = $intervalSpec['interval'];
            $candidateRequiredTithi = $intervalSpec['tithi'];

            $tithiAtSunriseToday = $this->isTargetAtPoint((float) $ctxToday['sunrise_jd'], $targetInterval);
            $tithiAtSunriseTomorrow = $this->isTargetAtPoint((float) $ctxTomorrow['sunrise_jd'], $targetInterval);
            $vriddhi = $tithiAtSunriseToday && $tithiAtSunriseTomorrow;
            $kshaya = !$tithiAtSunriseToday && !$tithiAtSunriseTomorrow;

            $candidates = [
                $this->buildCandidate($date, $today, $targetInterval, $karmakalaType, 0, $rule),
                $this->buildCandidate($date->addDay(), $tomorrow, $targetInterval, $karmakalaType, 1, $rule),
            ];
            $specialWinner = $this->resolveSpecialFestivalCandidate($date, $rule, $candidates, $today, $targetInterval);
            $exclusiveTruthTable = $this->usesExclusiveTruthTable($rule);
            $forceEkadashiKshayaNextDay = $kshaya && $kshayaPreference === 'last';
            if ($specialWinner === null && $exclusiveTruthTable && !$forceEkadashiKshayaNextDay) {
                continue;
            }

            if ($specialWinner !== null) {
                $winner = $specialWinner;
            } elseif ($forceEkadashiKshayaNextDay) {
                $winner = $candidates[1];
                $winner['reason'] = 'kshaya_next_day';
                $winner['score'] = max((int) ($winner['score'] ?? 0), 1100);
            } else {
                $eligible = array_values(array_filter($candidates, static fn (array $candidate): bool => $candidate['target_during_observance']));

                if ($eligible === []) {
                    continue;
                }

                $filtered = $eligible;
                if ($strictKarmakala) {
                    $atKarmakala = array_values(array_filter($filtered, static fn (array $candidate): bool => $candidate['target_at_karmakala']));
                    if ($atKarmakala === [] && (bool) ($rule['require_karmakala_match'] ?? false)) {
                        continue;
                    }

                    if ($atKarmakala !== []) {
                        $filtered = $atKarmakala;
                    }
                }

                $forbiddenPrevTithiKarmakala = $rule['forbid_previous_tithi_at'] ?? null;
                if (is_string($forbiddenPrevTithiKarmakala) && $forbiddenPrevTithiKarmakala !== '') {
                    $withoutForbiddenCarry = array_values(array_filter($filtered, static fn (array $candidate): bool => !$candidate['prev_tithi_at_forbidden_karmakala']));
                    if ($withoutForbiddenCarry !== []) {
                        $filtered = $withoutForbiddenCarry;
                    }
                }

                $requiredPrevTithiKarmakala = $rule['require_previous_tithi_at'] ?? null;
                if (is_string($requiredPrevTithiKarmakala) && $requiredPrevTithiKarmakala !== '') {
                    $withRequiredCarry = array_values(array_filter($filtered, static fn (array $candidate): bool => $candidate['prev_tithi_at_required_point']));
                    if ($withRequiredCarry === []) {
                        continue;
                    }

                    $filtered = $withRequiredCarry;
                }

                $matchingWeekday = array_values(array_filter($filtered, static fn (array $candidate): bool => $candidate['weekday_matches']));
                if ($matchingWeekday !== []) {
                    $filtered = $matchingWeekday;
                } elseif ($requiredWeekday !== null) {
                    continue;
                }

                if ($preferNakshatra) {
                    $matchingNakshatra = array_values(array_filter($filtered, static fn (array $candidate): bool => $candidate['nakshatra_matches']));
                    if ($matchingNakshatra !== []) {
                        $filtered = $matchingNakshatra;
                    }
                }

                $filtered = array_values(array_filter($filtered, static fn (array $candidate): bool => !$candidate['rule_rejected']));
                if ($filtered === []) {
                    continue;
                }

                usort(
                    $filtered,
                    fn (array $left, array $right): int => $this->compareCandidates($left, $right, $vriddhi, $kshaya, $vriddhiPreference, $kshayaPreference, $preferFirstKarmakala, $preferGrowthBeforeScore)
                );
                $winner = $filtered[0];
            }

            $resolution = [
                'winner' => $winner,
                'candidates' => $candidates,
                'target_interval' => $targetInterval,
                'required_tithi' => $candidateRequiredTithi,
                'tithi_at_sunrise_today' => $tithiAtSunriseToday,
                'tithi_at_sunrise_tomorrow' => $tithiAtSunriseTomorrow,
                'vriddhi' => $vriddhi,
                'kshaya' => $kshaya,
                'score' => (int) ($winner['score'] ?? 0),
            ];

            $preferHigherTithi = (bool) ($rule['prefer_higher_tithi_option'] ?? false);
            if ($preferHigherTithi) {
                $bestResolution = $resolution;
                break;
            }

            if ($bestResolution === null || $this->compareResolvedFestivalOutcome($resolution, $bestResolution, $preferHigherTithi) < 0) {
                $bestResolution = $resolution;
            }
        }

        if ($bestResolution === null) {
            return null;
        }

        $winner = $bestResolution['winner'];
        $candidates = $bestResolution['candidates'];
        $targetInterval = $bestResolution['target_interval'];
        $requiredTithi = $bestResolution['required_tithi'];
        $tithiAtSunriseToday = $bestResolution['tithi_at_sunrise_today'];
        $tithiAtSunriseTomorrow = $bestResolution['tithi_at_sunrise_tomorrow'];
        $vriddhi = $bestResolution['vriddhi'];
        $kshaya = $bestResolution['kshaya'];

        $observanceNote = null;
        $todayStr = $date->toDateString();
        $tomorrowStr = $date->addDay()->toDateString();
        $standardDate = $winner['date']; // Default
        $localizedPaksha = $this->localizedPaksha($paksha);
        $localizedKarmakala = $this->localizedKarmakala($karmakalaType);

        if ($winner['day_offset'] === 0 && !$tithiAtSunriseToday && $tithiAtSunriseTomorrow) {
            $standardDate = $tomorrowStr;
            $observanceNote = Localization::translate('String', 'observance_note_sunrise_shift_today') !== 'observance_note_sunrise_shift_today'
                ? sprintf(Localization::translate('String', 'observance_note_sunrise_shift_today'), $localizedPaksha, $requiredTithi, $tomorrowStr, $localizedKarmakala, $todayStr)
                : sprintf('Exception: Standard %s Tithi %d falls on %s at sunrise, but due to tradition/ritual requiring %s presence, it is celebrated on %s.', $localizedPaksha, $requiredTithi, $tomorrowStr, $localizedKarmakala, $todayStr);
        } elseif ($winner['day_offset'] === 1 && $tithiAtSunriseToday && !$tithiAtSunriseTomorrow) {
            $standardDate = $todayStr;
            $observanceNote = Localization::translate('String', 'observance_note_sunrise_shift_tomorrow') !== 'observance_note_sunrise_shift_tomorrow'
                ? sprintf(Localization::translate('String', 'observance_note_sunrise_shift_tomorrow'), $localizedPaksha, $requiredTithi, $todayStr, $localizedKarmakala, $tomorrowStr)
                : sprintf('Exception: Standard %s Tithi %d falls on %s at sunrise, but due to tradition/ritual requiring %s presence, observance shifts to %s.', $localizedPaksha, $requiredTithi, $todayStr, $localizedKarmakala, $tomorrowStr);
        } elseif ($kshaya) {
            $standardDate = $todayStr; // Kshaya tithi generally aligns with the day it starts
            if ($winner['date'] !== $standardDate) {
                $observanceNote = Localization::translate('String', 'observance_note_kshaya') !== 'observance_note_kshaya'
                    ? sprintf(Localization::translate('String', 'observance_note_kshaya'), $localizedPaksha, $requiredTithi, $winner['date'], $localizedKarmakala)
                    : sprintf('Exception: %s Tithi %d is a Kshaya Tithi (skips sunrise). Observance shifts to %s due to %s rules.', $localizedPaksha, $requiredTithi, $winner['date'], $localizedKarmakala);
            }
        } elseif ($vriddhi) {
            $standardDate = $todayStr; // Vriddhi default first day
            if ($winner['date'] !== $standardDate) {
                $observanceNote = Localization::translate('String', 'observance_note_vriddhi') !== 'observance_note_vriddhi'
                    ? sprintf(Localization::translate('String', 'observance_note_vriddhi'), $localizedPaksha, $requiredTithi, $winner['date'], $localizedKarmakala)
                    : sprintf('Exception: %s Tithi %d is a Vriddhi Tithi (spans two sunrises). Observance shifts to %s due to %s rules.', $localizedPaksha, $requiredTithi, $winner['date'], $localizedKarmakala);
            }
        }

        return [
            'festival_name' => $festivalName,
            'required_tithi' => $requiredTithi,
            'paksha' => $paksha,
            'calendar_type' => strtolower((string) ($today['Hindu_Calendar']['Calendar_Type'] ?? AstroCore::getConfig('panchang.defaults.calendar_type', 'amanta'))),
            'karmakala_type' => $karmakalaType,
            'tithi_at_karmakala_today' => $candidates[0]['target_at_karmakala'],
            'tithi_at_karmakala_tomorrow' => $candidates[1]['target_at_karmakala'],
            'tithi_coverage_seconds_today' => $candidates[0]['target_window_overlap_seconds'],
            'tithi_coverage_seconds_tomorrow' => $candidates[1]['target_window_overlap_seconds'],
            'tithi_at_sunrise_today' => $tithiAtSunriseToday,
            'tithi_at_sunrise_tomorrow' => $tithiAtSunriseTomorrow,
            'is_tithi_vriddhi' => $vriddhi,
            'is_tithi_kshaya' => $kshaya,
            'target_tithi_start_jd' => $targetInterval['start_jd'],
            'target_tithi_end_jd' => $targetInterval['end_jd'],
            'standard_date' => $standardDate,
            'observance_date' => $winner['date'],
            'observance_note' => $observanceNote,
            'decision' => [
                'strict_karmakala' => $strictKarmakala,
                'require_karmakala_match' => $rule['require_karmakala_match'] ?? null,
                'vriddhi_preference' => $vriddhiPreference,
                'kshaya_preference' => $kshayaPreference,
                'prefer_first_karmakala' => $preferFirstKarmakala,
                'prefer_growth_before_score' => $preferGrowthBeforeScore,
                'preferred_nakshatra' => $rule['nakshatra'] ?? null,
                'winning_reason' => $winner['reason'],
                'winning_score' => $winner['score'],
                'winning_window_overlap_seconds' => $winner['target_window_overlap_seconds'],
                'winning_window_coverage_ratio' => $winner['target_window_coverage_ratio'],
                'bhadra_decision' => $winner['bhadra_decision'],
                'rule_rejection_reason' => $winner['rule_rejection_reason'],
                'raksha_bandhan_selection' => $winner['raksha_bandhan_selection'] ?? null,
                'ekadashi_selection' => $winner['ekadashi_selection'] ?? null,
                'sankashti_selection' => $winner['sankashti_selection'] ?? null,
            ],
        ];
    }

    /** Resolve nakshatra-based festival (e.g., Onam, Thai Poosam) - public wrapper. */
    public function resolveNakshatraBasedFestival(
        string $festivalName,
        array $rule,
        CarbonImmutable $date,
        array $today,
        array $tomorrow
    ): ?array {
        return $this->resolveNakshatraFestival($festivalName, $rule, $date, $today, $tomorrow);
    }

    /** Adhik/Kshaya maas tagging from amanta month progression. */
    public function annotateMonthAnomalies(array $dateToDetails): array
    {
        ksort($dateToDetails);
        $monthStartDates = [];
        foreach ($dateToDetails as $date => $details) {
            $tithi = (array) ($details['Tithi'] ?? []);
            $idx = (int) ($tithi['index'] ?? 0);
            $paksha = (string) ($tithi['paksha'] ?? '');
            if ($idx === 1 && $paksha === 'Shukla') {
                $month = (string) (($details['Hindu_Calendar']['Month_Amanta'] ?? ''));
                if ($month !== '') {
                    $monthStartDates[] = ['date' => $date, 'month' => $month];
                }
            }
        }

        $tagsByDate = [];
        $monthOrder = array_keys(FestivalService::MONTHS);
        $monthIndex = array_flip($monthOrder);
        $counter = count($monthStartDates);

        for ($i = 1; $i < $counter; $i++) {
            $prev = $monthStartDates[$i - 1];
            $cur = $monthStartDates[$i];
            $prevIdx = $monthIndex[$prev['month']] ?? null;
            $curIdx = $monthIndex[$cur['month']] ?? null;
            if ($prevIdx === null || $curIdx === null) {
                continue;
            }

            if ($prev['month'] === $cur['month']) {
                $tagsByDate[$cur['date']] = ['month_status' => 'Adhik Maas', 'month_name' => $cur['month']];
                continue;
            }

            $expected = ($prevIdx + 1) % 12;
            if ($curIdx !== $expected) {
                $missing = $monthOrder[$expected];
                $tagsByDate[$cur['date']] = ['month_status' => 'Kshaya Maas Transition', 'missing_month' => $missing];
            }
        }

        return $tagsByDate;
    }
}
