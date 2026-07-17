<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Festivals;

use Carbon\CarbonImmutable;

/**
 * Chandra Darshana classical Sthula and modern crescent assessment.
 *
 * Structure-only split from FestivalRuleEngine. Algorithms unchanged.
 *
 * @see docs/FESTIVAL_REFACTOR_PARITY_CONTRACT.md
 */
trait FestivalRuleChandraDarshana
{
    private function resolveChandraDarshanaFestival(
        string $festivalName,
        array $rule,
        CarbonImmutable $date,
        array $today,
        array $tomorrow
    ): ?array {
        // The classical rule is anchored on the processed civil day itself (its sunrise tithi
        // plus the preceding/following sunrise), so it is evaluated once per date with the real
        // next-day snapshot. A previous "next day" fallback with a null next snapshot truncated
        // the Pratipada interval at the next sunrise and spuriously triggered the kshaya branch.
        $todayCandidate = $this->buildChandraDarshanaCandidate($date, $today, $tomorrow, 0, $rule);
        if ($todayCandidate !== null) {
            return $this->buildChandraDarshanaResult($festivalName, $rule, $todayCandidate);
        }

        return null;
    }

    /**
     * Classical "Sud 1 or Sud 2" Chandra Darshana determination via the Sthula Chandra
     * Darshana 9-muhurta rule. The gross first crescent is placed
     * on Shukla Pratipada (Sud 1) when Pratipada is "short" (< 9 muhurtas past sunrise), and on
     * Shukla Dwitiya (Sud 2) when Pratipada is "long" (>= 9 muhurtas, no Sthula darshana on Sud
     * 1). The physical sunset->moonset window is retained only as a lunar-visibility gate.
     */
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

        $ctx = (array) ($details['Resolution_Context'] ?? []);
        $sunriseJd = (float) ($ctx['sunrise_jd'] ?? 0.0);
        $sunsetJd = (float) ($ctx['sunset_jd'] ?? 0.0);
        $prevSunriseJd = (float) ($ctx['previous_sunrise_jd'] ?? 0.0);
        $nextSunriseJd = (float) ($ctx['next_sunrise_jd'] ?? 0.0);
        $currentAbs = (int) ($ctx['tithi_index_abs'] ?? 0);
        if ($sunriseJd <= 0.0 || $sunsetJd <= $sunriseJd) {
            return null;
        }

        $muhurtaSeconds = (($sunsetJd - $sunriseJd) * 86400.0) / 15.0;
        $thresholdSeconds = FestivalRuleConstants::GOVARDHAN_STHULA_CHANDRA_DARSHANA_MUHURTAS * $muhurtaSeconds;
        if ($thresholdSeconds <= 0.0) {
            return null;
        }

        $targetTithi = null;
        $assessment = null;

        if ($currentAbs === 1) {
            // Pratipada is udaya-vyapini (day P). Sthula darshana is present (CD today, Sud 1)
            // only when Pratipada is short: it does not persist 9 muhurtas past sunrise.
            $pratipadaInterval = $this->deriveSnapshotTithiInterval(1, 'Shukla', $details, $nextDetails);
            if ($pratipadaInterval !== null) {
                $postSunriseSeconds = max(0.0, ($pratipadaInterval['end_jd'] - $sunriseJd) * 86400.0);
                $dwitiyaActiveAtSunset = ($pratipadaInterval['end_jd'] < $sunsetJd);

                $classicalPassed = ($postSunriseSeconds < $thresholdSeconds || $dwitiyaActiveAtSunset);

                if ($classicalPassed) {
                    $targetTithi = $dwitiyaActiveAtSunset ? 2 : 1;
                    $reason = $dwitiyaActiveAtSunset
                        ? 'chandra_darshana_dwitiya_fallback_at_local_sunset'
                        : 'chandra_darshana_sud1_short_pratipada_sthula_present';
                    $assessment = $this->chandraDarshanaSthulaAssessment($targetTithi, $postSunriseSeconds, $muhurtaSeconds, $reason, $details, $rule);
                }
            }
        } elseif ($currentAbs === 2) {
            // Dwitiya is udaya-vyapini. CD is here (Sud 2) only when the preceding Pratipada was
            // long (>= 9 muhurtas past its sunrise) AND did not start before the preceding sunset.
            $prevPratipadaEndJd = (float) ($ctx['tithi_start_jd'] ?? 0.0);
            if ($prevSunriseJd > 0.0 && $prevPratipadaEndJd > $prevSunriseJd) {
                $postSunriseSeconds = max(0.0, ($prevPratipadaEndJd - $prevSunriseJd) * 86400.0);

                if ($postSunriseSeconds >= $thresholdSeconds) {
                    $targetTithi = 2;
                    $assessment = $this->chandraDarshanaSthulaAssessment(2, $postSunriseSeconds, $muhurtaSeconds, 'chandra_darshana_sud2_long_pratipada_no_sthula_on_sud1', $details, $rule);
                }
            }
        } elseif ($currentAbs === 30 && !(bool) ($rule['adhika_only'] ?? false)) {
            // Kshaya Pratipada: Amavasya is udaya-vyapini and Pratipada is wholly contained in
            // the day (never touches a sunrise). Being short, Sthula darshana is present, so CD
            // stays on this Amavasya-viddha (Sud 1) day.
            // Skip for adhika-only Chandra Darshana: the kshaya Pratipada after Adhika Amavasya
            // belongs to the following nija month, not a second Adhika observance.
            $pratipadaInterval = $this->deriveSnapshotTithiInterval(1, 'Shukla', $details, $nextDetails);
            if (
                $pratipadaInterval !== null
                && $nextSunriseJd > 0.0
                && $pratipadaInterval['start_jd'] < $nextSunriseJd
                && $pratipadaInterval['end_jd'] <= $nextSunriseJd
            ) {
                $postSunriseSeconds = max(0.0, ($pratipadaInterval['end_jd'] - $sunriseJd) * 86400.0);

                $targetTithi = 1;
                $assessment = $this->chandraDarshanaSthulaAssessment(1, $postSunriseSeconds, $muhurtaSeconds, 'chandra_darshana_sud1_kshaya_pratipada_sthula_present', $details, $rule);
            }
        }

        if ($targetTithi === null || $assessment === null) {
            return null;
        }

        $targetInterval = $this->deriveSnapshotTithiInterval($targetTithi, 'Shukla', $details, $nextDetails);
        if ($targetInterval === null) {
            return null;
        }

        $overlapSeconds = $this->intervalOverlapSeconds($targetInterval, $visibilityWindow);
        $targetAtSunrise = $this->isTargetAtPoint($sunriseJd, $targetInterval);
        $targetDaylightOverlapSeconds = $sunsetJd > $sunriseJd
            ? $this->intervalOverlapSeconds($targetInterval, ['start_jd' => $sunriseJd, 'end_jd' => $sunsetJd])
            : 0.0;

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
            'visibility_assessment' => $assessment,
            'reason' => (string) $assessment['reason'],
        ];
    }

    /** @return array<string, array<string, bool|float|int|string>|bool|float|int|string> */
    private function chandraDarshanaSthulaAssessment(
        int $targetTithi,
        float $pratipadaPostSunriseSeconds,
        float $muhurtaSeconds,
        string $reason,
        array $details,
        array $rule
    ): array {
        return [
            'model' => 'classical_sthula_chandra_darshana_9_muhurta',
            'visible' => true,
            'evening_tithi' => $targetTithi === 1 ? 'shukla_pratipada' : 'shukla_dwitiya',
            'pratipada_post_sunrise_seconds' => $pratipadaPostSunriseSeconds,
            'pratipada_post_sunrise_minutes' => $pratipadaPostSunriseSeconds / 60.0,
            'pratipada_post_sunrise_muhurtas' => $muhurtaSeconds > 0.0 ? $pratipadaPostSunriseSeconds / $muhurtaSeconds : 0.0,
            'sthula_threshold_muhurtas' => FestivalRuleConstants::GOVARDHAN_STHULA_CHANDRA_DARSHANA_MUHURTAS,
            'sthula_threshold_seconds' => FestivalRuleConstants::GOVARDHAN_STHULA_CHANDRA_DARSHANA_MUHURTAS * $muhurtaSeconds,
            'day_muhurta_seconds' => $muhurtaSeconds,
            'reason' => $reason,
            'basis' => 'classical_textual_rule_sthula_chandra_darshana',
            'modern_visibility' => $this->buildModernCrescentVisibilityAssessment($details, $rule),
        ];
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

    private function buildModernCrescentVisibilityAssessment(array $details, array $rule): array
    {
        $ctx = (array) ($details['Resolution_Context'] ?? []);
        $sunsetJd = (float) ($ctx['sunset_jd'] ?? 0.0);
        $moonsetJd = $this->extractJd($details['Moonset_JD'] ?? ($details['Moonset'] ?? null));

        $minLag = (float) ($rule['chandra_darshana_visibility_min_lag_minutes'] ?? FestivalRuleConstants::CHANDRA_DARSHANA_CRESCENT_MIN_LAG_MINUTES);
        $minElongation = (float) ($rule['chandra_darshana_visibility_min_elongation_degrees'] ?? FestivalRuleConstants::CHANDRA_DARSHANA_CRESCENT_MIN_ELONGATION_DEGREES);
        $hardFloor = (float) ($rule['chandra_darshana_visibility_hard_elongation_floor_degrees'] ?? FestivalRuleConstants::CHANDRA_DARSHANA_CRESCENT_HARD_ELONGATION_FLOOR_DEGREES);
        $minIllumination = (float) ($rule['chandra_darshana_visibility_min_illumination_percent'] ?? FestivalRuleConstants::CHANDRA_DARSHANA_CRESCENT_MIN_ILLUMINATION_PERCENT);

        if ($sunsetJd <= 0.0 || $moonsetJd === null || $moonsetJd <= $sunsetJd) {
            return [
                'model' => 'simplified_modern_crescent_visibility',
                'visible' => false,
                'lag_minutes' => 0.0,
                'elongation_degrees' => 0.0,
                'illumination_percent' => 0.0,
                'min_lag_minutes' => $minLag,
                'min_elongation_degrees' => $minElongation,
                'hard_elongation_floor_degrees' => $hardFloor,
                'min_illumination_percent' => $minIllumination,
                'passes_lag' => false,
                'passes_elongation' => false,
                'passes_hard_elongation_floor' => false,
                'passes_illumination' => false,
                'basis' => 'modern_astronomical_heuristic_not_classical',
            ];
        }

        $lagMinutes = ($moonsetJd - $sunsetJd) * 1440.0;
        $elongation = (float) ($ctx['moon_sun_elongation_at_sunset_degrees'] ?? 0.0);
        $illumination = (float) ($ctx['moon_illumination_at_sunset_percent'] ?? 0.0);

        $passesLag = ($lagMinutes >= $minLag);
        $passesHardElongationFloor = ($elongation >= $hardFloor);
        $passesElongation = ($elongation >= $minElongation);
        $passesIllumination = ($illumination >= $minIllumination);

        $visible = $passesLag && $passesHardElongationFloor && ($passesElongation || $passesIllumination);

        return [
            'model' => 'simplified_modern_crescent_visibility',
            'visible' => $visible,
            'lag_minutes' => $lagMinutes,
            'elongation_degrees' => $elongation,
            'illumination_percent' => $illumination,
            'min_lag_minutes' => $minLag,
            'min_elongation_degrees' => $minElongation,
            'hard_elongation_floor_degrees' => $hardFloor,
            'min_illumination_percent' => $minIllumination,
            'passes_lag' => $passesLag,
            'passes_elongation' => $passesElongation,
            'passes_hard_elongation_floor' => $passesHardElongationFloor,
            'passes_illumination' => $passesIllumination,
            'basis' => 'modern_astronomical_heuristic_not_classical',
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

        $panchanga = (array) ($details['Panchanga'] ?? []);
        $moonriseJd = $this->extractJd($details['Moonrise_JD'] ?? ($details['Moonrise'] ?? ($panchanga['Moonrise'] ?? null)));
        $moonsetJd = $this->extractJd($details['Moonset_JD'] ?? ($details['Moonset'] ?? ($panchanga['Moonset'] ?? null)));
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

}
