<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Festivals;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Core\Constants\ClassicalTimeConstants;
use JayeshMepani\PanchangCore\Core\Localization;

class FestivalRuleEngine
{
    private const float RAKSHA_BANDHAN_UDAYA_PURNIMA_THRESHOLD_MUHURTAS = 3.0;

    private const array NAKSHATRA_NUMBERS = [
        'Ashwini' => 1,
        'Bharani' => 2,
        'Krittika' => 3,
        'Rohini' => 4,
        'Mrigashira' => 5,
        'Ardra' => 6,
        'Punarvasu' => 7,
        'Pushya' => 8,
        'Ashlesha' => 9,
        'Magha' => 10,
        'Purva Phalguni' => 11,
        'Uttara Phalguni' => 12,
        'Hasta' => 13,
        'Chitra' => 14,
        'Swati' => 15,
        'Vishakha' => 16,
        'Anuradha' => 17,
        'Jyeshtha' => 18,
        'Mula' => 19,
        'Purva Ashadha' => 20,
        'Uttara Ashadha' => 21,
        'Shravana' => 22,
        'Dhanishta' => 23,
        'Shatabhisha' => 24,
        'Purva Bhadrapada' => 25,
        'Uttara Bhadrapada' => 26,
        'Revati' => 27,
    ];

    /** Resolve major Hindu/Sanatan observance day by karmakala precedence and tithi continuity. */
    public function resolveMajorFestival(
        string $festivalName,
        array $rule,
        CarbonImmutable $date,
        array $today,
        array $tomorrow
    ): ?array {
        $ctxToday = (array) ($today['Resolution_Context'] ?? []);
        $ctxTomorrow = (array) ($tomorrow['Resolution_Context'] ?? []);
        if ($ctxToday === [] || $ctxTomorrow === []) {
            return null;
        }

        // Check if this is a nakshatra-only based festival (no tithi requirement)
        if (isset($rule['nakshatra_only']) && $rule['nakshatra_only']) {
            return $this->resolveNakshatraFestival($festivalName, $rule, $date, $today, $tomorrow);
        }

        if ((bool) ($rule['chandra_darshana_visibility'] ?? false)) {
            return $this->resolveChandraDarshanaFestival($festivalName, $rule, $date, $today, $tomorrow);
        }

        $rulePaksha = $rule['paksha'] ?? 'Shukla';
        $currentPaksha = (string) ($today['Tithi']['paksha'] ?? 'Shukla');

        // Handle 'Both' paksha (bi-monthly recurring festivals like Pradosh Vrat) or arrays
        if ($rulePaksha === 'Both') {
            $paksha = $currentPaksha;
        } elseif (is_array($rulePaksha)) {
            $paksha = in_array($currentPaksha, $rulePaksha, true) ? $currentPaksha : (string) ($rulePaksha[0] ?? 'Shukla');
        } else {
            $paksha = (string) $rulePaksha;
        }

        $requiredTithi = (int) ($rule['tithi'] ?? 0);
        if ($requiredTithi <= 0) {
            return null;
        }

        $targetAbs = $paksha === 'Krishna' ? (15 + $requiredTithi) : $requiredTithi;
        $todayAbs = (int) ($ctxToday['tithi_index_abs'] ?? 0);
        $tomorrowAbs = (int) ($ctxTomorrow['tithi_index_abs'] ?? 0);

        $targetInterval = $this->deriveTargetInterval($targetAbs, $todayAbs, $tomorrowAbs, $ctxToday, $ctxTomorrow);
        if ($targetInterval === null) {
            return null;
        }

        $karmakalaType = (string) ($rule['karmakala_type'] ?? 'sunrise');
        $vriddhiPreference = (string) ($rule['vriddhi_preference'] ?? ($karmakalaType === 'sunrise' ? 'first' : 'last'));
        $kshayaPreference = (string) ($rule['kshaya_preference'] ?? 'first');
        $strictKarmakala = (bool) ($rule['strict_karmakala'] ?? ($karmakalaType !== 'sunrise'));
        $preferFirstKarmakala = (bool) ($rule['prefer_first_karmakala'] ?? false);
        $preferGrowthBeforeScore = (bool) ($rule['prefer_growth_before_score'] ?? false);
        $preferNakshatra = (bool) ($rule['prefer_nakshatra'] ?? false);
        $requiredWeekday = $rule['weekday'] ?? null;

        $tithiAtSunriseToday = $this->isTargetAtPoint((float) $ctxToday['sunrise_jd'], $targetInterval);
        $tithiAtSunriseTomorrow = $this->isTargetAtPoint((float) $ctxTomorrow['sunrise_jd'], $targetInterval);
        $vriddhi = $tithiAtSunriseToday && $tithiAtSunriseTomorrow;
        $kshaya = !$tithiAtSunriseToday && !$tithiAtSunriseTomorrow;

        $candidates = [
            $this->buildCandidate($date, $today, $targetInterval, $karmakalaType, 0, $rule),
            $this->buildCandidate($date->addDay(), $tomorrow, $targetInterval, $karmakalaType, 1, $rule),
        ];
        $specialWinner = $this->resolveSpecialFestivalCandidate($rule, $candidates, $today, $targetInterval);
        $exclusiveTruthTable = $this->usesExclusiveTruthTable($rule);
        if ($specialWinner === null && $exclusiveTruthTable) {
            return null;
        }

        $forceEkadashiKshayaNextDay = $kshaya && $kshayaPreference === 'last';

        if ($specialWinner !== null) {
            $winner = $specialWinner;
        } elseif ($forceEkadashiKshayaNextDay) {
            $winner = $candidates[1];
            $winner['reason'] = 'kshaya_next_day';
            $winner['score'] = max((int) ($winner['score'] ?? 0), 1100);
        } else {
            $eligible = array_values(array_filter($candidates, static fn (array $candidate): bool => $candidate['target_during_observance']));

            if ($eligible === []) {
                return null;
            }

            $filtered = $eligible;
            if ($strictKarmakala) {
                $atKarmakala = array_values(array_filter($filtered, static fn (array $candidate): bool => $candidate['target_at_karmakala']));
                if ($atKarmakala === [] && (bool) ($rule['require_karmakala_match'] ?? false)) {
                    return null;
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

            $matchingWeekday = array_values(array_filter($filtered, static fn (array $candidate): bool => $candidate['weekday_matches']));
            if ($matchingWeekday !== []) {
                $filtered = $matchingWeekday;
            } elseif ($requiredWeekday !== null) {
                // If a specific weekday was required but not met by any eligible candidate, this festival does not occur
                return null;
            }

            if ($preferNakshatra) {
                $matchingNakshatra = array_values(array_filter($filtered, static fn (array $candidate): bool => $candidate['nakshatra_matches']));
                if ($matchingNakshatra !== []) {
                    $filtered = $matchingNakshatra;
                }
            }

            $filtered = array_values(array_filter($filtered, static fn (array $candidate): bool => !$candidate['rule_rejected']));
            if ($filtered === []) {
                return null;
            }

            usort(
                $filtered,
                fn (array $left, array $right): int => $this->compareCandidates($left, $right, $vriddhi, $kshaya, $vriddhiPreference, $kshayaPreference, $preferFirstKarmakala, $preferGrowthBeforeScore)
            );
            $winner = $filtered[0];
        }

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
                'preferred_nakshatra' => $rule['nakshatra'] ?? null,
                'winning_reason' => $winner['reason'],
                'winning_score' => $winner['score'],
                'winning_window_overlap_seconds' => $winner['target_window_overlap_seconds'],
                'winning_window_coverage_ratio' => $winner['target_window_coverage_ratio'],
                'bhadra_decision' => $winner['bhadra_decision'],
                'rule_rejection_reason' => $winner['rule_rejection_reason'],
                'raksha_bandhan_selection' => $winner['raksha_bandhan_selection'] ?? null,
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

    private function resolveChandraDarshanaFestival(
        string $festivalName,
        array $rule,
        CarbonImmutable $date,
        array $today,
        array $tomorrow
    ): ?array {
        $todayCandidate = $this->buildChandraDarshanaCandidate($date, $today, $tomorrow, 0, $rule);
        if ($todayCandidate !== null) {
            return $this->buildChandraDarshanaResult($festivalName, $rule, $todayCandidate);
        }

        $tomorrowCandidate = $this->buildChandraDarshanaCandidate($date->addDay(), $tomorrow, null, 1, $rule);
        if ($tomorrowCandidate !== null) {
            return $this->buildChandraDarshanaResult($festivalName, $rule, $tomorrowCandidate);
        }

        return null;
    }

    private function buildChandraDarshanaCandidate(
        CarbonImmutable $date,
        array $details,
        ?array $nextDetails,
        int $dayOffset,
        array $rule
    ): ?array {
        $visibilityWindow = $this->chandraDarshanaVisibilityWindow($details);
        if ($visibilityWindow === null) {
            return null;
        }

        $moonVisibilitySeconds = max(0.0, ($visibilityWindow['end_jd'] - $visibilityWindow['start_jd']) * 86400.0);
        $visibilityAssessment = $this->assessChandraDarshanaVisibility($details, $moonVisibilitySeconds, $rule);
        if (!(bool) $visibilityAssessment['visible']) {
            return null;
        }

        foreach ([1, 2] as $targetTithi) {
            $targetInterval = $this->deriveSnapshotTithiInterval($targetTithi, 'Shukla', $details, $nextDetails);
            if ($targetInterval === null) {
                continue;
            }

            $overlapSeconds = $this->intervalOverlapSeconds($targetInterval, $visibilityWindow);
            $ctx = (array) ($details['Resolution_Context'] ?? []);
            $sunriseJd = (float) ($ctx['sunrise_jd'] ?? 0.0);
            $sunsetJd = (float) ($ctx['sunset_jd'] ?? 0.0);
            $targetAtSunrise = $sunriseJd > 0.0 && $this->isTargetAtPoint($sunriseJd, $targetInterval);
            $targetDaylightOverlapSeconds = ($sunriseJd > 0.0 && $sunsetJd > $sunriseJd)
                ? $this->intervalOverlapSeconds($targetInterval, ['start_jd' => $sunriseJd, 'end_jd' => $sunsetJd])
                : 0.0;

            if ($overlapSeconds <= 0.0 && $targetDaylightOverlapSeconds <= 0.0) {
                continue;
            }

            return [
                'date' => $date->toDateString(),
                'day_offset' => $dayOffset,
                'required_tithi' => $targetTithi,
                'target_interval' => $targetInterval,
                'visibility_window' => $visibilityWindow,
                'target_at_sunrise' => $targetAtSunrise,
                'target_at_karmakala' => $overlapSeconds > 0.0,
                'target_window_overlap_seconds' => $overlapSeconds,
                'target_daylight_overlap_seconds' => $targetDaylightOverlapSeconds,
                'moon_visibility_seconds' => $moonVisibilitySeconds,
                'visibility_assessment' => $visibilityAssessment,
                'reason' => $targetTithi === 1
                    ? 'chandra_darshana_pratipada_visible_after_sunset'
                    : 'chandra_darshana_dwitiya_visible_after_sunset',
            ];
        }

        return null;
    }

    private function buildChandraDarshanaResult(string $festivalName, array $rule, array $winner): array
    {
        $targetInterval = (array) $winner['target_interval'];
        $visibilityWindow = (array) $winner['visibility_window'];
        $overlapSeconds = (float) $winner['target_window_overlap_seconds'];
        $daylightOverlapSeconds = (float) ($winner['target_daylight_overlap_seconds'] ?? 0.0);
        $moonVisibilitySeconds = (float) $winner['moon_visibility_seconds'];

        return [
            'festival_name' => $festivalName,
            'required_tithi' => (int) $winner['required_tithi'],
            'paksha' => 'Shukla',
            'karmakala_type' => (string) ($rule['karmakala_type'] ?? 'moonrise'),
            'tithi_at_karmakala_today' => $winner['day_offset'] === 0 && $overlapSeconds > 0.0,
            'tithi_at_karmakala_tomorrow' => $winner['day_offset'] === 1 && $overlapSeconds > 0.0,
            'tithi_coverage_seconds_today' => $winner['day_offset'] === 0 ? $overlapSeconds : 0.0,
            'tithi_coverage_seconds_tomorrow' => $winner['day_offset'] === 1 ? $overlapSeconds : 0.0,
            'tithi_at_sunrise_today' => $winner['day_offset'] === 0 && (bool) $winner['target_at_sunrise'],
            'tithi_at_sunrise_tomorrow' => $winner['day_offset'] === 1 && (bool) $winner['target_at_sunrise'],
            'is_tithi_vriddhi' => false,
            'is_tithi_kshaya' => false,
            'target_tithi_start_jd' => (float) $targetInterval['start_jd'],
            'target_tithi_end_jd' => (float) $targetInterval['end_jd'],
            'standard_date' => (string) $winner['date'],
            'observance_date' => (string) $winner['date'],
            'observance_note' => null,
            'decision' => [
                'strict_karmakala' => true,
                'require_karmakala_match' => true,
                'vriddhi_preference' => null,
                'kshaya_preference' => null,
                'preferred_nakshatra' => null,
                'winning_reason' => (string) $winner['reason'],
                'winning_score' => 1500 + min(240, (int) floor($overlapSeconds / 60.0)),
                'winning_window_overlap_seconds' => $overlapSeconds,
                'winning_window_coverage_ratio' => $moonVisibilitySeconds > 0.0 ? min(1.0, $overlapSeconds / $moonVisibilitySeconds) : 0.0,
                'target_tithi_daylight_overlap_seconds' => $daylightOverlapSeconds,
                'moon_visibility_start_jd' => (float) $visibilityWindow['start_jd'],
                'moon_visibility_end_jd' => (float) $visibilityWindow['end_jd'],
                'moon_visibility_seconds' => $moonVisibilitySeconds,
                'visibility_assessment' => $winner['visibility_assessment'] ?? [],
                'bhadra_decision' => [
                    'applicable' => false,
                    'rejected' => false,
                    'preferred' => false,
                    'reason' => null,
                ],
                'rule_rejection_reason' => null,
            ],
        ];
    }

    /** @return array<string, bool|float|string> */
    private function assessChandraDarshanaVisibility(array $details, float $moonVisibilitySeconds, array $rule): array
    {
        $ctx = (array) ($details['Resolution_Context'] ?? []);
        $elongation = (float) ($ctx['moon_sun_elongation_at_sunset_degrees'] ?? 0.0);
        $illuminationPercent = (float) ($ctx['moon_illumination_at_sunset_percent'] ?? 0.0);
        $lagMinutes = $moonVisibilitySeconds / 60.0;

        $minLagMinutes = (float) ($rule['chandra_darshana_visibility_min_lag_minutes'] ?? 38.0);
        $minElongationDegrees = (float) ($rule['chandra_darshana_visibility_min_elongation_degrees'] ?? 9.0);
        $hardElongationFloorDegrees = (float) ($rule['chandra_darshana_visibility_hard_elongation_floor_degrees'] ?? 7.0);
        $minIlluminationPercent = (float) ($rule['chandra_darshana_visibility_min_illumination_percent'] ?? 0.8);

        $passesHardElongationFloor = $elongation >= $hardElongationFloorDegrees;
        $passesLag = $lagMinutes >= $minLagMinutes;
        $passesElongation = $elongation >= $minElongationDegrees;
        $passesIllumination = $illuminationPercent >= $minIlluminationPercent;
        $visible = $passesHardElongationFloor && $passesLag && ($passesElongation || $passesIllumination);

        return [
            'model' => 'simplified_modern_crescent_visibility',
            'visible' => $visible,
            'lag_minutes' => $lagMinutes,
            'elongation_degrees' => $elongation,
            'illumination_percent' => $illuminationPercent,
            'min_lag_minutes' => $minLagMinutes,
            'min_elongation_degrees' => $minElongationDegrees,
            'hard_elongation_floor_degrees' => $hardElongationFloorDegrees,
            'min_illumination_percent' => $minIlluminationPercent,
            'passes_lag' => $passesLag,
            'passes_elongation' => $passesElongation,
            'passes_hard_elongation_floor' => $passesHardElongationFloor,
            'passes_illumination' => $passesIllumination,
            'basis' => 'modern_astronomical_heuristic_not_classical',
        ];
    }

    private function localizedPaksha(string $paksha): string
    {
        return match ($paksha) {
            'Shukla' => Localization::translate('String', 'Shukla Paksha (waxing)'),
            'Krishna' => Localization::translate('String', 'Krishna Paksha (waning)'),
            default => $paksha,
        };
    }

    private function localizedKarmakala(string $karmakalaType): string
    {
        return Localization::translate('String', $karmakalaType);
    }

    /** Resolve nakshatra-based festival (e.g., Onam, Thai Poosam). */
    private function resolveNakshatraFestival(
        string $festivalName,
        array $rule,
        CarbonImmutable $date,
        array $today,
        array $tomorrow
    ): ?array {
        $ctxToday = (array) ($today['Resolution_Context'] ?? []);
        $ctxTomorrow = (array) ($tomorrow['Resolution_Context'] ?? []);
        if ($ctxToday === [] || $ctxTomorrow === []) {
            return null;
        }

        $requiredNakshatra = (string) ($rule['nakshatra'] ?? '');
        if ($requiredNakshatra === '') {
            return null;
        }

        $requiredNakshatraNumber = $this->resolveNakshatraNumber($requiredNakshatra);

        $karmakalaType = (string) ($rule['karmakala_type'] ?? 'sunrise');
        $todayNakshatraNumber = $this->resolveSnapshotNakshatraNumber((array) ($today['Nakshatra'] ?? []));
        $tomorrowNakshatraNumber = $this->resolveSnapshotNakshatraNumber((array) ($tomorrow['Nakshatra'] ?? []));
        $todayNakshatraWindow = $this->nakshatraWindowOverlapSeconds($today, $requiredNakshatra, $karmakalaType);
        $tomorrowNakshatraWindow = $this->nakshatraWindowOverlapSeconds($tomorrow, $requiredNakshatra, $karmakalaType);
        $requireNakshatraWindow = (bool) ($rule['require_nakshatra_window'] ?? false);

        if ($requiredNakshatraNumber !== null && $todayNakshatraNumber !== null && $tomorrowNakshatraNumber !== null) {
            $nakshatraTodayMatch = $todayNakshatraNumber === $requiredNakshatraNumber;
            $nakshatraTomorrowMatch = $tomorrowNakshatraNumber === $requiredNakshatraNumber;
        } else {
            $nakshatraToday = (string) ($today['Nakshatra']['name'] ?? '');
            $nakshatraTomorrow = (string) ($tomorrow['Nakshatra']['name'] ?? '');
            $nakshatraTodayMatch = strcasecmp($requiredNakshatra, $nakshatraToday) === 0;
            $nakshatraTomorrowMatch = strcasecmp($requiredNakshatra, $nakshatraTomorrow) === 0;
        }

        if ($requireNakshatraWindow) {
            $nakshatraTodayMatch = $todayNakshatraWindow > 0.0;
            $nakshatraTomorrowMatch = $tomorrowNakshatraWindow > 0.0;
        } else {
            $nakshatraTodayMatch = $nakshatraTodayMatch || $todayNakshatraWindow > 0.0;
            $nakshatraTomorrowMatch = $nakshatraTomorrowMatch || $tomorrowNakshatraWindow > 0.0;
        }

        // If nakshatra doesn't match today or tomorrow, skip
        if (!$nakshatraTodayMatch && !$nakshatraTomorrowMatch) {
            return null;
        }

        // Check month constraint if specified (e.g., Onam in Shravana/Bhadrapada, Thai Poosam in Pausha/Magha)
        $allowedMonths = (array) ($rule['allowed_months_amanta'] ?? []);
        if ($allowedMonths !== []) {
            $calendarToday = (array) ($today['Hindu_Calendar'] ?? []);
            $calendarTomorrow = (array) ($tomorrow['Hindu_Calendar'] ?? []);
            $monthToday = (string) ($calendarToday['Month_Amanta_En'] ?? $calendarToday['Month_Amanta'] ?? '');
            $monthTomorrow = (string) ($calendarTomorrow['Month_Amanta_En'] ?? $calendarTomorrow['Month_Amanta'] ?? '');
            $monthTodayNorm = $this->normalizeMonthName($monthToday);
            $monthTomorrowNorm = $this->normalizeMonthName($monthTomorrow);
            $allowedMonthsNorm = array_map(fn ($m): string => $this->normalizeMonthName((string) $m), $allowedMonths);
            $monthTodayMatch = in_array($monthTodayNorm, $allowedMonthsNorm, true);
            $monthTomorrowMatch = in_array($monthTomorrowNorm, $allowedMonthsNorm, true);

            // If nakshatra matches but month doesn't for that day, exclude that day
            if ($nakshatraTodayMatch && !$monthTodayMatch) {
                $nakshatraTodayMatch = false;
            }

            if ($nakshatraTomorrowMatch && !$monthTomorrowMatch) {
                $nakshatraTomorrowMatch = false;
            }

            // If neither day matches after month filtering, skip
            if (!$nakshatraTodayMatch && !$nakshatraTomorrowMatch) {
                return null;
            }
        }

        $allowedSunSigns = isset($rule['sun_sign']) ? [(int) $rule['sun_sign']] : array_map(intval(...), (array) ($rule['allowed_sun_signs'] ?? []));
        if ($allowedSunSigns !== []) {
            $sunSignToday = $today['Sun_Sign_Index'] ?? null;
            $sunSignTomorrow = $tomorrow['Sun_Sign_Index'] ?? null;
            $sunTodayMatch = is_int($sunSignToday) && in_array($sunSignToday, $allowedSunSigns, true);
            $sunTomorrowMatch = is_int($sunSignTomorrow) && in_array($sunSignTomorrow, $allowedSunSigns, true);

            if ($nakshatraTodayMatch && !$sunTodayMatch) {
                $nakshatraTodayMatch = false;
            }

            if ($nakshatraTomorrowMatch && !$sunTomorrowMatch) {
                $nakshatraTomorrowMatch = false;
            }

            if (!$nakshatraTodayMatch && !$nakshatraTomorrowMatch) {
                return null;
            }
        }

        // Check if purnima is also required (e.g., Thai Poosam = Pushya + Purnima)
        $requiresPurnima = (bool) ($rule['requires_purnima'] ?? false);
        if ($requiresPurnima) {
            $tithiToday = (array) ($today['Tithi'] ?? []);
            $tithiTomorrow = (array) ($tomorrow['Tithi'] ?? []);
            $pakshaToday = (string) ($tithiToday['paksha'] ?? '');
            $tithiIndexToday = (int) ($tithiToday['index'] ?? 0);
            $pakshaTomorrow = (string) ($tithiTomorrow['paksha'] ?? '');
            $tithiIndexTomorrow = (int) ($tithiTomorrow['index'] ?? 0);

            $isPurnimaToday = ($pakshaToday === 'Shukla' && $tithiIndexToday === 15);
            $isPurnimaTomorrow = ($pakshaTomorrow === 'Shukla' && $tithiIndexTomorrow === 15);

            // Both nakshatra AND purnima must match
            if ($nakshatraTodayMatch && $isPurnimaToday) {
                return $this->buildNakshatraResult($festivalName, $rule, $date, $karmakalaType, $requiredNakshatra, 'nakshatra_and_purnima_match', $todayNakshatraWindow);
            }

            if ($nakshatraTomorrowMatch && $isPurnimaTomorrow) {
                return $this->buildNakshatraResult($festivalName, $rule, $date->addDay(), $karmakalaType, $requiredNakshatra, 'nakshatra_and_purnima_match', $tomorrowNakshatraWindow);
            }

            return null;
        }

        // Simple nakshatra-only match (at least one match is guaranteed here due to early returns above)
        if ($nakshatraTodayMatch) {
            return $this->buildNakshatraResult(
                $festivalName,
                $rule,
                $date,
                $karmakalaType,
                $requiredNakshatra,
                $todayNakshatraWindow > 0.0 ? 'nakshatra_overlaps_karmakala_window' : 'nakshatra_match',
                $todayNakshatraWindow
            );
        }

        // $nakshatraTomorrowMatch must be true at this point
        return $this->buildNakshatraResult(
            $festivalName,
            $rule,
            $date->addDay(),
            $karmakalaType,
            $requiredNakshatra,
            $tomorrowNakshatraWindow > 0.0 ? 'nakshatra_overlaps_karmakala_window' : 'nakshatra_match',
            $tomorrowNakshatraWindow
        );
    }

    /** Build nakshatra-based festival result. */
    private function buildNakshatraResult(
        string $festivalName,
        array $rule,
        CarbonImmutable $observanceDate,
        string $karmakalaType,
        string $nakshatraName,
        string $reason,
        float $nakshatraWindowOverlapSeconds = 0.0
    ): array {
        return [
            'festival_name' => $festivalName,
            'required_nakshatra' => $nakshatraName,
            'karmakala_type' => $karmakalaType,
            'standard_date' => $observanceDate->toDateString(),
            'observance_date' => $observanceDate->toDateString(),
            'observance_note' => null,
            'decision' => [
                'nakshatra_based' => true,
                'nakshatra_name' => $nakshatraName,
                'winning_reason' => $reason,
                'winning_nakshatra_window_overlap_seconds' => $nakshatraWindowOverlapSeconds,
            ],
        ];
    }

    private function nakshatraWindowOverlapSeconds(array $details, string $requiredNakshatra, string $karmakalaType): float
    {
        $ctx = (array) ($details['Resolution_Context'] ?? []);
        if ($ctx === []) {
            return 0.0;
        }

        $window = $this->karmakalaWindowJd($karmakalaType, $ctx);
        $maxOverlap = 0.0;
        $requiredNakshatraNumber = $this->resolveNakshatraNumber($requiredNakshatra);
        foreach ((array) ($details['Nakshatra_Windows'] ?? []) as $interval) {
            if (!is_array($interval)) {
                continue;
            }

            $name = (string) ($interval['name'] ?? $interval['nakshatra'] ?? '');
            $intervalNakshatraNumber = $this->resolveNakshatraNumber($name);
            if ($name === ''
                || ($requiredNakshatraNumber !== null && $intervalNakshatraNumber !== null && $requiredNakshatraNumber !== $intervalNakshatraNumber)
                || (($requiredNakshatraNumber === null || $intervalNakshatraNumber === null) && strcasecmp($name, $requiredNakshatra) !== 0)) {
                continue;
            }

            $start = $this->extractJd($interval['start_jd'] ?? ($interval['start']['jd'] ?? null));
            $end = $this->extractJd($interval['end_jd'] ?? ($interval['end']['jd'] ?? null));
            if ($start === null || $end === null || $end <= $start) {
                continue;
            }

            $maxOverlap = max($maxOverlap, $this->intervalOverlapSeconds(['start_jd' => $start, 'end_jd' => $end], $window));
        }

        return $maxOverlap;
    }

    private function deriveTargetInterval(int $targetAbs, int $todayAbs, int $tomorrowAbs, array $ctxToday, array $ctxTomorrow): ?array
    {
        if ($todayAbs === $targetAbs) {
            return [
                'start_jd' => (float) $ctxToday['tithi_start_jd'],
                'end_jd' => (float) $ctxToday['tithi_end_jd'],
            ];
        }

        if ($tomorrowAbs === $targetAbs) {
            return [
                'start_jd' => (float) $ctxTomorrow['tithi_start_jd'],
                'end_jd' => (float) $ctxTomorrow['tithi_end_jd'],
            ];
        }

        // Handle transition (e.g., 30 -> 1)
        $todayPlusOne = ($todayAbs % 30) + 1;
        if ($todayPlusOne === $targetAbs) {
            $targetEnd = (float) ($ctxTomorrow['prev_tithi_end_jd'] ?? $ctxTomorrow['tithi_start_jd'] ?? $ctxTomorrow['tithi_end_jd']);
            return [
                'start_jd' => (float) $ctxToday['tithi_end_jd'],
                'end_jd' => $targetEnd,
            ];
        }

        return null;
    }

    private function buildCandidate(
        CarbonImmutable $date,
        array $details,
        array $targetInterval,
        string $karmakalaType,
        int $dayOffset,
        array $rule
    ): array {
        $ctx = (array) ($details['Resolution_Context'] ?? []);
        $karmakalaJd = $this->karmakalaJd($karmakalaType, $ctx);
        $karmakalaWindow = $this->karmakalaWindowJd($karmakalaType, $ctx);
        $sunriseJd = (float) ($ctx['sunrise_jd'] ?? 0.0);
        $nextSunriseJd = (float) ($ctx['next_sunrise_jd'] ?? 0.0);
        $sunsetJd = (float) ($ctx['sunset_jd'] ?? 0.0);
        $dinamanaSeconds = max(0.0, ($sunsetJd - $sunriseJd) * 86400.0);
        $ratrimanaSeconds = max(0.0, ($nextSunriseJd - $sunsetJd) * 86400.0);
        $prevTithiEndJd = (float) ($ctx['prev_tithi_end_jd'] ?? 0.0);
        $nakshatraName = (string) ($details['Nakshatra']['name'] ?? '');
        $requiredNakshatra = (string) ($rule['nakshatra'] ?? '');
        $requiredWeekday = $rule['weekday'] ?? null;

        $targetWindowOverlapSeconds = $this->intervalOverlapSeconds($targetInterval, $karmakalaWindow);
        $targetAtSunrise = $this->isTargetAtPoint($sunriseJd, $targetInterval);
        $targetAtKarmakalaPoint = (bool) ($rule['require_sunrise_vyapini'] ?? false)
            ? $targetAtSunrise
            : $this->isTargetAtPoint($karmakalaJd, $targetInterval);
        $targetAtKarmakala = $targetAtKarmakalaPoint || ($targetWindowOverlapSeconds > 0.0 && !(bool) ($rule['require_sunrise_vyapini'] ?? false));
        $targetDuringObservance = $targetInterval['start_jd'] < $nextSunriseJd && $targetInterval['end_jd'] > $sunriseJd;
        $forbiddenPrevTithiAt = $rule['forbid_previous_tithi_at'] ?? null;
        $forbiddenPrevTithiJd = is_string($forbiddenPrevTithiAt) && $forbiddenPrevTithiAt !== ''
            ? $this->karmakalaJd($forbiddenPrevTithiAt, $ctx)
            : null;
        $prevTithiAtForbiddenPoint = is_float($forbiddenPrevTithiJd) && $prevTithiEndJd > $forbiddenPrevTithiJd;

        $score = 0;
        $reason = 'target_during_observance';

        if ($targetAtKarmakala) {
            $score += 1000;
            $reason = 'target_at_karmakala';
            $score += min(240, (int) floor($targetWindowOverlapSeconds / 60.0));
        } elseif ($targetAtSunrise) {
            $score += 700;
            $reason = 'target_at_sunrise';
        } elseif ($targetDuringObservance) {
            $score += 300;
        }

        if ((bool) ($rule['prefer_growth_before_score'] ?? false)) {
            if (($rule['vriddhi_preference'] ?? null) === 'last') {
                $score += $dayOffset * 500;
            } elseif (($rule['vriddhi_preference'] ?? null) === 'first') {
                $score -= $dayOffset * 500;
            }
        }

        $karmakalaWindowDurationSeconds = max(0.0, ($karmakalaWindow['end_jd'] - $karmakalaWindow['start_jd']) * 86400.0);
        $targetWindowCoverageRatio = $karmakalaWindowDurationSeconds > 0.0
            ? min(1.0, $targetWindowOverlapSeconds / $karmakalaWindowDurationSeconds)
            : 0.0;
        if ((bool) ($rule['prefer_full_karmakala_coverage'] ?? false) && $targetWindowCoverageRatio >= 0.999) {
            $score += 300;
            $reason = 'target_covers_full_karmakala';
        }

        $bhadraDecision = $this->bhadraDecision($details, $karmakalaWindow, $rule);
        if ($bhadraDecision['rejected']) {
            $score -= 10_000;
        } elseif ($bhadraDecision['preferred']) {
            $score += 180;
            $reason = 'bhadra_puchha_or_clear_pradosha';
        }

        if ($requiredWeekday !== null && (int) $requiredWeekday === $date->dayOfWeek) {
            $score += 150;
        }

        $preferWeekdays = array_map(intval(...), (array) ($rule['prefer_weekdays'] ?? []));
        $preferredWeekdayMatches = $preferWeekdays !== [] && in_array($date->dayOfWeek, $preferWeekdays, true);
        if ($preferredWeekdayMatches) {
            $score += 100;
        }

        if ($requiredNakshatra !== '' && strcasecmp($requiredNakshatra, $nakshatraName) === 0) {
            $score += 125;
        }

        $ruleRejectionReason = $this->ruleRejectionReason($date, $details, $rule)
            ?? ($bhadraDecision['rejected'] ? $bhadraDecision['reason'] : null);
        if ($ruleRejectionReason !== null) {
            $score -= 10_000;
        }

        if ($prevTithiEndJd > $karmakalaJd) {
            $score -= 50;
        }

        return [
            'date' => $date->toDateString(),
            'day_offset' => $dayOffset,
            'target_at_karmakala' => $targetAtKarmakala,
            'target_at_sunrise' => $targetAtSunrise,
            'target_during_observance' => $targetDuringObservance,
            'weekday_matches' => $requiredWeekday === null || (int) $requiredWeekday === $date->dayOfWeek,
            'preferred_weekday_matches' => $preferredWeekdayMatches,
            'nakshatra_matches' => $requiredNakshatra !== '' && strcasecmp($requiredNakshatra, $nakshatraName) === 0,
            'prev_tithi_at_karmakala' => $prevTithiEndJd > $karmakalaJd,
            'prev_tithi_at_forbidden_karmakala' => $prevTithiAtForbiddenPoint,
            'target_window_start_jd' => $karmakalaWindow['start_jd'],
            'target_window_end_jd' => $karmakalaWindow['end_jd'],
            'sunrise_jd' => $sunriseJd,
            'sunset_jd' => $sunsetJd,
            'next_sunrise_jd' => $nextSunriseJd,
            'dinamana_seconds' => $dinamanaSeconds,
            'ratrimana_seconds' => $ratrimanaSeconds,
            'day_muhurta_seconds' => $dinamanaSeconds / 15.0,
            'night_muhurta_seconds' => $ratrimanaSeconds / 15.0,
            // These are *not* classical fixed ghaṭīs (24 min each).
            // They are equal normalized divisions of the actual day/night length.
            // (dinamana ÷ 30 and ratrimana ÷ 30). Renamed to avoid confusion with true ghaṭī.
            'day_normalized_division_seconds' => $dinamanaSeconds / 30.0,
            'night_normalized_division_seconds' => $ratrimanaSeconds / 30.0,
            'target_interval_start_jd' => $targetInterval['start_jd'],
            'target_interval_end_jd' => $targetInterval['end_jd'],
            'target_window_overlap_seconds' => $targetWindowOverlapSeconds,
            'target_window_duration_seconds' => $karmakalaWindowDurationSeconds,
            'target_window_coverage_ratio' => $targetWindowCoverageRatio,
            'bhadra_decision' => $bhadraDecision,
            'rule_rejected' => $ruleRejectionReason !== null,
            'rule_rejection_reason' => $ruleRejectionReason,
            'score' => $score,
            'reason' => $reason,
        ];
    }

    private function compareCandidates(
        array $left,
        array $right,
        bool $vriddhi,
        bool $kshaya,
        string $vriddhiPreference,
        string $kshayaPreference,
        bool $preferFirstKarmakala = false,
        bool $preferGrowthBeforeScore = false
    ): int
    {
        if ($preferGrowthBeforeScore) {
            $growthDecision = $this->compareGrowthPreference($left, $right, $vriddhi, $kshaya, $vriddhiPreference, $kshayaPreference);
            if ($growthDecision !== 0) {
                return $growthDecision;
            }
        }

        if ($left['score'] !== $right['score']) {
            return $right['score'] <=> $left['score'];
        }

        if ($preferFirstKarmakala && $left['target_at_karmakala'] && $right['target_at_karmakala']) {
            return $left['day_offset'] <=> $right['day_offset'];
        }

        if ($vriddhi) {
            if ($vriddhiPreference === 'last') {
                return $right['day_offset'] <=> $left['day_offset'];
            }

            return $left['day_offset'] <=> $right['day_offset'];
        }

        if ($kshaya) {
            if ($kshayaPreference === 'last') {
                return $right['day_offset'] <=> $left['day_offset'];
            }

            return $left['day_offset'] <=> $right['day_offset'];
        }

        if ($left['target_at_karmakala'] !== $right['target_at_karmakala']) {
            return $right['target_at_karmakala'] <=> $left['target_at_karmakala'];
        }

        if ($left['target_at_sunrise'] !== $right['target_at_sunrise']) {
            return $right['target_at_sunrise'] <=> $left['target_at_sunrise'];
        }

        return $left['day_offset'] <=> $right['day_offset'];
    }

    private function compareGrowthPreference(
        array $left,
        array $right,
        bool $vriddhi,
        bool $kshaya,
        string $vriddhiPreference,
        string $kshayaPreference
    ): int {
        if ($vriddhi) {
            if ($vriddhiPreference === 'last') {
                return $right['day_offset'] <=> $left['day_offset'];
            }

            return $left['day_offset'] <=> $right['day_offset'];
        }

        if ($kshaya) {
            if ($kshayaPreference === 'last') {
                return $right['day_offset'] <=> $left['day_offset'];
            }

            return $left['day_offset'] <=> $right['day_offset'];
        }

        return 0;
    }

    private function resolveSpecialFestivalCandidate(
        array $rule,
        array $candidates,
        array $today,
        array $targetInterval
    ): ?array {
        if ((bool) ($rule['holika_lunar_eclipse_exception'] ?? false)) {
            return $this->resolveHolikaLunarEclipseException($candidates, $today);
        }

        if ((bool) ($rule['janmashtami_truth_table'] ?? false)) {
            return $this->resolveJanmashtamiTruthTable($candidates, $today, $targetInterval);
        }

        if ((bool) ($rule['vijayadashami_truth_table'] ?? false)) {
            return $this->resolveVijayadashamiTruthTable($candidates);
        }

        if ((bool) ($rule['govatsa_truth_table'] ?? false)) {
            return $this->resolveGovatsaTruthTable($candidates);
        }

        if ((bool) ($rule['mahashivaratri_truth_table'] ?? false)) {
            return $this->resolveMahashivaratriTruthTable($candidates);
        }

        if ((bool) ($rule['diwali_truth_table'] ?? false)) {
            return $this->resolveDiwaliTruthTable($candidates);
        }

        if ((bool) ($rule['raksha_bandhan_truth_table'] ?? false)) {
            return $this->resolveRakshaBandhanTruthTable($candidates, $targetInterval);
        }

        if ((bool) ($rule['ashtami_viddha_rejection'] ?? false)) {
            return $this->resolveRamNavamiTruthTable($candidates, $today, $targetInterval);
        }

        return null;
    }

    private function usesExclusiveTruthTable(array $rule): bool
    {
        foreach (['janmashtami_truth_table', 'vijayadashami_truth_table', 'govatsa_truth_table', 'mahashivaratri_truth_table', 'diwali_truth_table', 'raksha_bandhan_truth_table', 'ashtami_viddha_rejection'] as $flag) {
            if ((bool) ($rule[$flag] ?? false)) {
                return true;
            }
        }

        return false;
    }

    private function resolveHolikaLunarEclipseException(array $candidates, array $today): ?array
    {
        if (!$this->lunarEclipseOnDay($today)) {
            return null;
        }

        if ($candidates[1]['target_at_karmakala'] && !$candidates[1]['rule_rejected']) {
            return $this->markSpecialWinner($candidates[1], 'holika_lunar_eclipse_shift_to_second_pradosha');
        }

        if ($candidates[0]['target_during_observance'] && !$candidates[0]['rule_rejected']) {
            return $this->markSpecialWinner($candidates[0], 'holika_lunar_eclipse_perform_on_first_night');
        }

        return null;
    }

    private function resolveJanmashtamiTruthTable(array $candidates, array $today, array $targetInterval): ?array
    {
        $day1 = $candidates[0];
        $day2 = $candidates[1];
        $jayantiDay1 = $day1['target_at_karmakala'] && $day1['nakshatra_matches'] && $day1['preferred_weekday_matches'];
        $jayantiDay2 = $day2['target_at_karmakala'] && $day2['nakshatra_matches'] && $day2['preferred_weekday_matches'];

        if ($jayantiDay1) {
            return $this->markSpecialWinner($day1, 'janmashtami_jayanti_yoga_day1');
        }

        if ($jayantiDay2) {
            return $this->markSpecialWinner($day2, 'janmashtami_jayanti_yoga_day2');
        }

        if ($this->previousTithiActiveAtPoint($today, $targetInterval, 'sunset')) {
            return $this->markSpecialWinner($day2, 'janmashtami_saptami_viddha_choose_day2');
        }

        if ($day1['nakshatra_matches'] && !$day2['nakshatra_matches']) {
            return $this->markSpecialWinner($day1, 'janmashtami_shuddha_rohini_day1');
        }

        if (!$day1['nakshatra_matches'] && $day2['nakshatra_matches']) {
            return $this->markSpecialWinner($day2, 'janmashtami_shuddha_rohini_day2');
        }

        if ($day1['nakshatra_matches']) {
            return $this->markSpecialWinner($day2, 'janmashtami_rohini_both_days_choose_day2');
        }

        if ($day1['target_at_karmakala'] && !$day2['target_at_karmakala']) {
            return $this->markSpecialWinner($day1, 'janmashtami_nishitha_only_day1');
        }

        if ($day2['target_during_observance']) {
            return $this->markSpecialWinner($day2, $day2['target_at_karmakala'] ? 'janmashtami_nishitha_day2_or_both' : 'janmashtami_no_rohini_default_day2');
        }

        return $day1['target_during_observance']
            ? $this->markSpecialWinner($day1, 'janmashtami_no_rohini_fallback_day1')
            : null;
    }

    private function resolveVijayadashamiTruthTable(array $candidates): ?array
    {
        $day1 = $candidates[0];
        $day2 = $candidates[1];
        if ($day1['target_at_karmakala'] && !$day2['target_at_karmakala']) {
            return $this->markSpecialWinner($day1, 'vijayadashami_vijaya_kaal_only_day1');
        }

        if (!$day1['target_at_karmakala'] && $day2['target_at_karmakala']) {
            return $this->markSpecialWinner($day2, 'vijayadashami_vijaya_kaal_only_day2');
        }

        if ($day1['target_at_karmakala']) {
            if ($day1['nakshatra_matches']) {
                return $this->markSpecialWinner($day1, 'vijayadashami_both_vijaya_kaal_shravana_day1');
            }

            if ($day2['nakshatra_matches']) {
                return $this->markSpecialWinner($day2, 'vijayadashami_both_vijaya_kaal_shravana_day2');
            }

            return $this->markSpecialWinner($day1, 'vijayadashami_both_vijaya_kaal_table_day1');
        }

        if ($day1['target_during_observance'] && $day1['target_interval_end_jd'] <= $day1['target_window_start_jd']) {
            return null;
        }

        if ($day1['target_during_observance'] && $day1['nakshatra_matches']) {
            return $this->markSpecialWinner($day1, 'vijayadashami_kshaya_shravana_day1');
        }

        if ($day2['target_during_observance'] && $day2['nakshatra_matches']) {
            return $this->markSpecialWinner($day2, 'vijayadashami_kshaya_shravana_day2');
        }

        if ($day2['target_during_observance']) {
            return $this->markSpecialWinner($day2, 'vijayadashami_kshaya_default_day2');
        }

        return $day1['target_during_observance']
            ? $this->markSpecialWinner($day1, 'vijayadashami_kshaya_fallback_day1')
            : null;
    }

    private function resolveGovatsaTruthTable(array $candidates): ?array
    {
        $day1 = $candidates[0];
        $day2 = $candidates[1];
        if ($day1['target_at_karmakala'] && !$day2['target_at_karmakala']) {
            return $this->markSpecialWinner($day1, 'govatsa_pradosha_only_day1');
        }

        if (!$day1['target_at_karmakala'] && $day2['target_at_karmakala']) {
            return $this->markSpecialWinner($day2, 'govatsa_pradosha_only_day2');
        }

        if ($day1['target_at_karmakala']) {
            return $this->markSpecialWinner($day2, 'govatsa_equal_pradosha_choose_day2');
        }

        return null;
    }

    private function resolveMahashivaratriTruthTable(array $candidates): ?array
    {
        $day1 = $candidates[0];
        $day2 = $candidates[1];

        $day1Full = $day1['target_window_coverage_ratio'] >= 0.999;
        $day2Full = $day2['target_window_coverage_ratio'] >= 0.999;

        if ($day1Full && !$day2Full) {
            return $this->markSpecialWinner($day1, 'mahashivaratri_day1_full_over_day2_partial');
        }

        if (!$day1Full && $day2Full) {
            return $this->markSpecialWinner($day2, 'mahashivaratri_day2_full_over_day1_partial');
        }

        if ($day1Full) {
            return $this->markSpecialWinner($day2, 'mahashivaratri_both_full_nishitha_choose_day2');
        }

        if ($day1['target_window_overlap_seconds'] > $day2['target_window_overlap_seconds'] && $day1['target_window_overlap_seconds'] > 0) {
            return $this->markSpecialWinner($day1, 'mahashivaratri_day1_longer_overlap');
        }

        if ($day2['target_window_overlap_seconds'] > $day1['target_window_overlap_seconds'] && $day2['target_window_overlap_seconds'] > 0) {
            return $this->markSpecialWinner($day2, 'mahashivaratri_day2_longer_overlap');
        }

        if ($day1['target_at_karmakala'] && !$day2['target_at_karmakala']) {
            return $this->markSpecialWinner($day1, 'mahashivaratri_nishitha_only_day1');
        }

        if (!$day1['target_at_karmakala'] && $day2['target_at_karmakala']) {
            return $this->markSpecialWinner($day2, 'mahashivaratri_nishitha_only_day2');
        }

        return null;
    }

    private function resolveRakshaBandhanTruthTable(array $candidates, array $targetInterval): ?array
    {
        $day1 = $candidates[0];
        $day2 = $candidates[1];
        $nextSunriseJd = (float) ($day2['sunrise_jd'] ?? 0.0);
        $thresholdSeconds = self::RAKSHA_BANDHAN_UDAYA_PURNIMA_THRESHOLD_MUHURTAS * (float) ($day2['day_muhurta_seconds'] ?? 0.0);
        $postSunrisePurnimaSeconds = $this->isTargetAtPoint($nextSunriseJd, $targetInterval)
            ? max(0.0, ($targetInterval['end_jd'] - $nextSunriseJd) * 86400.0)
            : 0.0;
        $useUdayaPurnima = $thresholdSeconds > 0.0 && $postSunrisePurnimaSeconds >= $thresholdSeconds;
        $winner = $useUdayaPurnima ? $day2 : $day1;
        if (!$winner['target_during_observance']) {
            return null;
        }

        $winner['reason'] = $useUdayaPurnima
            ? 'raksha_bandhan_udaya_purnima_3_muhurta'
            : 'raksha_bandhan_previous_day_fallback';
        $winner['score'] = max((int) ($winner['score'] ?? 0), 20_000);
        $winner['raksha_bandhan_selection'] = [
            'selection_rule' => $useUdayaPurnima ? 'UDAYA_PURNIMA_3_MUHURTA' : 'PREVIOUS_DAY_FALLBACK',
            'previous_day_fallback_selected' => !$useUdayaPurnima,
            'post_sunrise_purnima_seconds' => $postSunrisePurnimaSeconds,
            'post_sunrise_purnima_minutes' => $postSunrisePurnimaSeconds / 60.0,
            'minimum_post_sunrise_purnima_muhurtas' => self::RAKSHA_BANDHAN_UDAYA_PURNIMA_THRESHOLD_MUHURTAS,
            'minimum_post_sunrise_purnima_seconds' => $thresholdSeconds,
            'minimum_post_sunrise_purnima_minutes' => $thresholdSeconds / 60.0,
            'day_muhurta_seconds' => (float) ($day2['day_muhurta_seconds'] ?? 0.0),
            'day_muhurta_minutes' => (float) ($day2['day_muhurta_seconds'] ?? 0.0) / 60.0,
            'dinamana_seconds' => (float) ($day2['dinamana_seconds'] ?? 0.0),
            'basis' => 'dynamic_dinamana_day_muhurta',
            'tradition_profiles' => ['STRICT_CURRENT_TITHI', 'ASSIGNED_FESTIVAL_DAY'],
            'instant_restrictions' => ['eclipse_restriction_if_enabled', 'bhadra_prohibited'],
        ];

        return $winner;
    }

    private function markSpecialWinner(array $candidate, string $reason): array
    {
        $candidate['reason'] = $reason;
        $candidate['score'] = max((int) ($candidate['score'] ?? 0), 20_000);

        return $candidate;
    }

    private function isTargetAtPoint(float $jd, array $targetInterval): bool
    {
        return $targetInterval['start_jd'] <= $jd && $targetInterval['end_jd'] > $jd;
    }

    private function previousTithiActiveAtPoint(array $details, array $targetInterval, string $pointType): bool
    {
        $ctx = (array) ($details['Resolution_Context'] ?? []);
        if ($ctx === []) {
            return false;
        }

        $pointJd = $this->karmakalaPointJd($pointType, $ctx);

        return $targetInterval['start_jd'] > $pointJd;
    }

    private function karmakalaPointJd(string $type, array $ctx): float
    {
        return match ($type) {
            'sunrise' => (float) $ctx['sunrise_jd'],
            'sunset' => (float) $ctx['sunset_jd'],
            default => $this->karmakalaJd($type, $ctx),
        };
    }

    private function karmakalaJd(string $type, array $ctx): float
    {
        $window = $this->karmakalaWindowJd($type, $ctx);

        return ($window['start_jd'] + $window['end_jd']) / 2.0;
    }

    /** @return array{start_jd:float, end_jd:float} */
    private function karmakalaWindowJd(string $type, array $ctx): array
    {
        $sunrise = (float) $ctx['sunrise_jd'];
        $sunset = (float) $ctx['sunset_jd'];
        $nextSunrise = (float) $ctx['next_sunrise_jd'];
        $dayDuration = $sunset - $sunrise;
        $nightDuration = $nextSunrise - $sunset;
        $dayMuhurta = $dayDuration / 15.0;
        $nightMuhurta = $nightDuration / 15.0;
        $fixedGhati = ClassicalTimeConstants::GHATIKA_IN_MINUTES / 1440.0;

        return match ($type) {
            'sunrise' => ['start_jd' => $sunrise, 'end_jd' => $sunrise],
            'arunodaya' => ['start_jd' => $sunrise - (4.0 * $fixedGhati), 'end_jd' => $sunrise],
            'pratah_kal' => ['start_jd' => $sunrise, 'end_jd' => $sunrise + ($dayDuration / 5.0)],
            'sangava' => ['start_jd' => $sunrise + ($dayDuration / 5.0), 'end_jd' => $sunrise + ($dayDuration * 2.0 / 5.0)],
            'madhyahna' => ['start_jd' => $sunrise + ($dayDuration * 2.0 / 5.0), 'end_jd' => $sunrise + ($dayDuration * 3.0 / 5.0)],
            'abhijit' => ['start_jd' => $sunrise + (7.0 * $dayMuhurta), 'end_jd' => $sunrise + (8.0 * $dayMuhurta)],
            'aparahna' => ['start_jd' => $sunrise + ($dayDuration * 3.0 / 5.0), 'end_jd' => $sunrise + ($dayDuration * 4.0 / 5.0)],
            'vijaya_kaal' => ['start_jd' => $sunrise + (10.0 * $dayMuhurta), 'end_jd' => $sunrise + (11.0 * $dayMuhurta)],
            'sayankala' => ['start_jd' => $sunrise + ($dayDuration * 4.0 / 5.0), 'end_jd' => $sunset],
            'sunset' => [
                'start_jd' => $sunset - (ClassicalTimeConstants::SAYAM_SANDHYA_BEFORE_SUNSET_GHATIKAS * $fixedGhati),
                'end_jd' => $sunset + (ClassicalTimeConstants::SAYAM_SANDHYA_AFTER_SUNSET_GHATIKAS * $fixedGhati),
            ],
            'nishitha' => [
                'start_jd' => $sunset + ($nightDuration / 2.0) - ($nightMuhurta / 2.0),
                'end_jd' => $sunset + ($nightDuration / 2.0) + ($nightMuhurta / 2.0),
            ],
            'pradosha' => ['start_jd' => $sunset, 'end_jd' => $sunset + (ClassicalTimeConstants::PRADOSHA_GHATIKAS * $fixedGhati)],
            default => ['start_jd' => $sunrise, 'end_jd' => $sunrise + ($dayDuration / 5.0)],
        };
    }

    /** @param array{start_jd:float, end_jd:float} $targetInterval @param array{start_jd:float, end_jd:float} $window */
    private function intervalOverlapSeconds(array $targetInterval, array $window): float
    {
        $start = max($targetInterval['start_jd'], $window['start_jd']);
        $end = min($targetInterval['end_jd'], $window['end_jd']);

        return max(0.0, ($end - $start) * 86400.0);
    }

    private function ruleRejectionReason(CarbonImmutable $date, array $details, array $rule): ?string
    {
        $rejectWeekdayNakshatra = (array) ($rule['reject_weekday_nakshatra'] ?? []);
        if ($rejectWeekdayNakshatra !== []) {
            $weekday = $rejectWeekdayNakshatra['weekday'] ?? null;
            $nakshatra = (string) ($rejectWeekdayNakshatra['nakshatra'] ?? '');
            $currentNakshatra = (string) ($details['Nakshatra']['name'] ?? '');
            if ($weekday !== null && (int) $weekday === $date->dayOfWeek && strcasecmp($nakshatra, $currentNakshatra) === 0) {
                return 'rejected_by_weekday_nakshatra_exception';
            }
        }

        $chandradarshanMode = (string) ($rule['chandradarshan_nishedh_mode'] ?? 'strict');
        if ((bool) ($rule['chandradarshan_nishedh'] ?? false) && $chandradarshanMode === 'strict' && $this->moonVisibleAfterSunset($details)) {
            return 'rejected_by_chandradarshan_nishedh';
        }

        return null;
    }

    /** @param array{start_jd:float, end_jd:float} $karmakalaWindow */
    private function bhadraDecision(array $details, array $karmakalaWindow, array $rule): array
    {
        if (!(bool) ($rule['avoid_bhadra_mukha'] ?? false)) {
            return [
                'applicable' => false,
                'rejected' => false,
                'preferred' => false,
                'reason' => null,
            ];
        }

        $bhadraPeriods = array_values(array_filter((array) ($details['Bhadra'] ?? []), is_array(...)));
        if ($bhadraPeriods === []) {
            return [
                'applicable' => true,
                'rejected' => false,
                'preferred' => true,
                'reason' => 'no_bhadra_in_window',
            ];
        }

        $puchhaOverlap = false;
        foreach ($bhadraPeriods as $period) {
            $parts = (array) ($period['parts'] ?? []);
            foreach (['mukha', 'madhya'] as $blockedPart) {
                $part = (array) ($parts[$blockedPart] ?? []);
                if ($this->bhadraPartOverlapsWindow($part, $period, $karmakalaWindow)) {
                    return [
                        'applicable' => true,
                        'rejected' => true,
                        'preferred' => false,
                        'reason' => 'rejected_by_bhadra_' . $blockedPart,
                    ];
                }
            }

            $puchha = (array) ($parts['puchha'] ?? []);
            if ($this->bhadraPartOverlapsWindow($puchha, $period, $karmakalaWindow)) {
                $puchhaOverlap = true;
            }
        }

        return [
            'applicable' => true,
            'rejected' => false,
            'preferred' => $puchhaOverlap,
            'reason' => $puchhaOverlap ? 'preferred_bhadra_puchha' : 'bhadra_clear_for_karmakala',
        ];
    }

    /** @param array{start_jd:float, end_jd:float} $karmakalaWindow */
    private function bhadraPartOverlapsWindow(array $part, array $period, array $karmakalaWindow): bool
    {
        $periodStart = (float) ($period['start_jd'] ?? 0.0);
        $partStart = $this->extractBhadraPartBoundary($part['start_jd'] ?? null, $part['start_time'] ?? null, $periodStart);
        $partEnd = $this->extractBhadraPartBoundary($part['end_jd'] ?? null, $part['end_time'] ?? null, $periodStart);
        if ($partStart === null || $partEnd === null || $partEnd <= $partStart) {
            return false;
        }

        return min($partEnd, $karmakalaWindow['end_jd']) > max($partStart, $karmakalaWindow['start_jd']);
    }

    private function extractBhadraPartBoundary(mixed $jd, mixed $relativeTime, float $periodStartJd): ?float
    {
        if (is_numeric($jd)) {
            return (float) $jd;
        }

        if (!is_string($relativeTime) || !preg_match('/^-?\d{2}:\d{2}:\d{2}$/', $relativeTime)) {
            return null;
        }

        [$hours, $minutes, $seconds] = array_map(intval(...), explode(':', ltrim($relativeTime, '-')));
        $sign = str_starts_with($relativeTime, '-') ? -1.0 : 1.0;

        return $periodStartJd + ($sign * (($hours * 3600) + ($minutes * 60) + $seconds) / 86400.0);
    }

    /** @return array{start_jd:float, end_jd:float}|null */
    private function chandraDarshanaVisibilityWindow(array $details): ?array
    {
        $ctx = (array) ($details['Resolution_Context'] ?? []);
        $sunsetJd = (float) ($ctx['sunset_jd'] ?? 0.0);
        $nextSunriseJd = (float) ($ctx['next_sunrise_jd'] ?? 0.0);
        $moonsetJd = $this->extractJd($details['Moonset_JD'] ?? ($details['Moonset'] ?? null));

        if ($sunsetJd <= 0.0 || $nextSunriseJd <= $sunsetJd || $moonsetJd === null || $moonsetJd <= $sunsetJd) {
            return null;
        }

        $endJd = min($moonsetJd, $nextSunriseJd);
        if ($endJd <= $sunsetJd) {
            return null;
        }

        return [
            'start_jd' => $sunsetJd,
            'end_jd' => $endJd,
        ];
    }

    /** @return array{start_jd:float, end_jd:float}|null */
    private function deriveSnapshotTithiInterval(int $targetTithi, string $paksha, array $details, ?array $nextDetails): ?array
    {
        $targetAbs = $paksha === 'Krishna' ? 15 + $targetTithi : $targetTithi;
        $ctx = (array) ($details['Resolution_Context'] ?? []);
        if ($ctx === []) {
            return null;
        }

        $currentAbs = (int) ($ctx['tithi_index_abs'] ?? 0);
        if ($currentAbs === $targetAbs) {
            return [
                'start_jd' => (float) $ctx['tithi_start_jd'],
                'end_jd' => (float) $ctx['tithi_end_jd'],
            ];
        }

        $nextAbs = ($currentAbs % 30) + 1;
        if ($nextAbs !== $targetAbs) {
            return null;
        }

        $startJd = (float) ($ctx['tithi_end_jd'] ?? 0.0);
        if ($startJd <= 0.0) {
            return null;
        }

        $nextCtx = (array) ($nextDetails['Resolution_Context'] ?? []);
        $endJd = $nextCtx !== [] && (int) ($nextCtx['tithi_index_abs'] ?? 0) === $targetAbs
            ? (float) ($nextCtx['tithi_end_jd'] ?? 0.0)
            : (float) ($ctx['next_sunrise_jd'] ?? 0.0);

        if ($endJd <= $startJd) {
            return null;
        }

        return [
            'start_jd' => $startJd,
            'end_jd' => $endJd,
        ];
    }

    private function moonVisibleAfterSunset(array $details): bool
    {
        $ctx = (array) ($details['Resolution_Context'] ?? []);
        $sunsetJd = (float) ($ctx['sunset_jd'] ?? 0.0);
        $nextSunriseJd = (float) ($ctx['next_sunrise_jd'] ?? 0.0);
        if ($sunsetJd <= 0.0 || $nextSunriseJd <= $sunsetJd) {
            return false;
        }

        $moonriseJd = $this->extractJd($details['Moonrise_JD'] ?? ($details['Moonrise'] ?? null));
        $moonsetJd = $this->extractJd($details['Moonset_JD'] ?? ($details['Moonset'] ?? null));
        if ($moonriseJd === null) {
            return false;
        }

        return $moonriseJd < $nextSunriseJd && ($moonsetJd === null || $moonsetJd > $sunsetJd);
    }

    private function lunarEclipseOnDay(array $details): bool
    {
        if ((bool) ($details['Lunar_Eclipse'] ?? $details['lunar_eclipse'] ?? false)) {
            return true;
        }

        foreach (['Eclipse', 'Eclipses', 'eclipse', 'eclipses'] as $key) {
            foreach (array_values((array) ($details[$key] ?? [])) as $event) {
                if (!is_array($event)) {
                    continue;
                }

                $type = strtolower((string) ($event['type'] ?? $event['grahan_type'] ?? $event['eclipse_type'] ?? ''));
                $visible = (bool) ($event['visible'] ?? $event['meets_ritual_minimum'] ?? $event['sutak'] ?? true);
                if (str_contains($type, 'lunar') && $visible) {
                    return true;
                }
            }
        }

        return false;
    }

    private function extractJd(mixed $value): ?float
    {
        if (is_array($value)) {
            $value = $value['jd'] ?? null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    /** Normalize month name for comparison (strips diacritics, non-alpha, lowercases). */
    private function normalizeMonthName(string $month): string
    {
        $month = trim($month);
        if ($month === '') {
            return '';
        }

        // Strip parenthetical suffixes like "(Adhika)", "(Kshaya)"
        $month = preg_replace('/\s*\(.*?\)\s*/', '', $month) ?? $month;

        $transliterated = strtr($month, [
            'Ā' => 'A', 'ā' => 'a',
            'Ī' => 'I', 'ī' => 'i',
            'Ū' => 'U', 'ū' => 'u',
            'Ṛ' => 'Ri', 'ṛ' => 'ri',
            'Ṝ' => 'Ri', 'ṝ' => 'ri',
            'Ḷ' => 'Li', 'ḷ' => 'li',
            'Ḍ' => 'D', 'ḍ' => 'd',
            'Ṭ' => 'T', 'ṭ' => 't',
            'Ṅ' => 'N', 'ṅ' => 'n',
            'Ñ' => 'N', 'ñ' => 'n',
            'Ṇ' => 'N', 'ṇ' => 'n',
            'Ś' => 'Sh', 'ś' => 'sh',
            'Ṣ' => 'Sh', 'ṣ' => 'sh',
            'Ḥ' => 'H', 'ḥ' => 'h',
            'ṁ' => 'm', 'ṃ' => 'm',
        ]);

        $asciiOnly = preg_replace('/[^A-Za-z]/', '', $transliterated) ?? '';

        return strtolower($asciiOnly);
    }

    /** Resolve canonical nakshatra number (1..27) from a localized/english label. */
    private function resolveNakshatraNumber(string $label): ?int
    {
        $labelNorm = $this->normalizeLabel($label);
        if ($labelNorm === '') {
            return null;
        }

        foreach (self::NAKSHATRA_NUMBERS as $name => $number) {
            if ($this->normalizeLabel($name) === $labelNorm) {
                return $number;
            }
        }

        foreach (['en', 'hi', 'gu'] as $locale) {
            for ($idx = 0; $idx < 27; $idx++) {
                $translated = Localization::translate('Nakshatra', $idx, $locale);
                if ($this->normalizeLabel($translated) === $labelNorm) {
                    return $idx + 1;
                }
            }
        }

        return null;
    }

    /** Resolve nakshatra number from festival snapshot payload. */
    private function resolveSnapshotNakshatraNumber(array $nakshatra): ?int
    {
        $explicitNumber = (int) ($nakshatra['number'] ?? 0);
        if ($explicitNumber >= 1 && $explicitNumber <= 27) {
            return $explicitNumber;
        }

        $explicitIndex = (int) ($nakshatra['index'] ?? -1);
        if ($explicitIndex >= 0 && $explicitIndex <= 26) {
            return $explicitIndex + 1;
        }

        $name = (string) ($nakshatra['name'] ?? '');

        return $this->resolveNakshatraNumber($name);
    }

    /** Normalize free-text labels (ASCII/Unicode) for robust equality checks. */
    private function normalizeLabel(string $label): string
    {
        $label = trim($label);
        if ($label === '') {
            return '';
        }

        $label = preg_replace('/\s*\(.*?\)\s*/u', '', $label) ?? $label;
        $label = function_exists('mb_strtolower') ? mb_strtolower($label, 'UTF-8') : strtolower($label);

        // Keep letters across all scripts (Latin + Indic) and remove separators/punctuation.
        return preg_replace('/[^\p{L}]+/u', '', $label) ?? '';
    }

    private function resolveDiwaliTruthTable(array $candidates): array
    {
        $day1 = $candidates[0];
        $day2 = $candidates[1];

        $d1Vyapti = $day1['target_at_karmakala'];
        $d2Vyapti = $day2['target_at_karmakala'];

        if ($d1Vyapti && !$d2Vyapti) {
            return $this->markSpecialWinner($day1, 'diwali_d1_only_pradosha');
        }

        if (!$d1Vyapti && $d2Vyapti) {
            return $this->markSpecialWinner($day2, 'diwali_d2_only_pradosha');
        }

        if ($d1Vyapti) {
            return $this->markSpecialWinner($day2, 'diwali_both_pradosha_d2');
        }

        return $this->markSpecialWinner($day1, 'diwali_both_avyapti_d1');
    }

    private function resolveRamNavamiTruthTable(array $candidates, array $today, array $targetInterval): array
    {
        $day1 = $candidates[0];
        $day2 = $candidates[1];

        $day1Vyapti = $day1['target_window_overlap_seconds'] > 0;
        $day2Vyapti = $day2['target_window_overlap_seconds'] > 0;

        if ($day1Vyapti && $day2Vyapti) {
            if ($this->previousTithiActiveAtPoint($today, $targetInterval, 'sunrise')) {
                return $this->markSpecialWinner($day2, 'ram_navami_vriddhi_ashtami_viddha_reject_day1');
            }

            return $this->markSpecialWinner($day1, 'ram_navami_vriddhi_choose_day1');
        }

        if (!$day1Vyapti && !$day2Vyapti) {
            return $this->markSpecialWinner($day1, 'ram_navami_kshaya_choose_combined_day');
        }

        return $day1Vyapti ? $this->markSpecialWinner($day1, 'ram_navami_shuddha_day1') : $this->markSpecialWinner($day2, 'ram_navami_shuddha_day2');
    }
}
