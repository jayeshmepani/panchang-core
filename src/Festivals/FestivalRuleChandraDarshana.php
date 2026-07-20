<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Festivals;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Core\Localization;

/**
 * Monthly Chandra Darshana first-crescent resolver.
 *
 * Source boundary:
 *  - strict source-only mode does not itself declare a universal monthly date;
 *  - production calendar mode explicitly selects the earliest post-Amavasya local evening
 *    satisfying the engine's modern proxy for the traditional 12-bhaga indication;
 *  - the Dvitiya Aparahna condition is retained only as a contextual nibandha
 *    visibility indication, not as a universal monthly date command.
 */
trait FestivalRuleChandraDarshana
{
    private const float CHANDRA_DARSHANA_12_BHAGA_PROXY_DEGREES = 12.0;

    private const int CHANDRA_DARSHANA_MAX_POST_AMAVASYA_EVENINGS = 8;

    private function resolveChandraDarshanaFestival(
        string $festivalName,
        array $rule,
        CarbonImmutable $date,
        array $today,
        array $tomorrow,
        ?callable $fetchHistoricalSnapshot = null
    ): ?array {
        unset($tomorrow);

        if ($this->transitEngine === null || $fetchHistoricalSnapshot === null) {
            return null;
        }

        $season = $this->chandraDarshanaSeasonForDate($date, $today, $fetchHistoricalSnapshot);
        if ($season === null) {
            return null;
        }

        $selected = $this->selectOperationalChandraDarshanaCandidate($season, $date, $fetchHistoricalSnapshot);
        if ($selected === null || (string) $selected['date'] !== $date->toDateString()) {
            return null;
        }

        return $this->buildChandraDarshanaResult($festivalName, $rule, $selected);
    }

    /** @return array{amavasya_end_jd: float, anchor_date: string}|null */
    private function chandraDarshanaSeasonForDate(CarbonImmutable $date, array $today, callable $fetchHistoricalSnapshot): ?array
    {
        $todayCtx = (array) ($today['Resolution_Context'] ?? []);
        $todaySunset = (float) ($todayCtx['sunset_jd'] ?? 0.0);
        if ($todaySunset <= 0.0) {
            return null;
        }

        $seasons = [];
        for ($d = $date->subDays(self::CHANDRA_DARSHANA_MAX_POST_AMAVASYA_EVENINGS); $d->lessThanOrEqualTo($date); $d = $d->addDay()) {
            $details = $d->isSameDay($date) ? $today : $fetchHistoricalSnapshot($d);
            $ctx = (array) ($details['Resolution_Context'] ?? []);
            if ($ctx === []) {
                continue;
            }

            $abs = (int) ($ctx['tithi_index_abs'] ?? 0);
            if ($abs === 30) {
                $endJd = (float) ($ctx['tithi_end_jd'] ?? 0.0);
                if ($endJd > 0.0 && $endJd <= $todaySunset + 1e-9) {
                    $seasons[sprintf('%.5F', $endJd)] = [
                        'amavasya_end_jd' => $endJd,
                        'anchor_date' => $d->toDateString(),
                    ];
                }
            }

            if ($abs === 1) {
                $endJd = (float) ($ctx['tithi_start_jd'] ?? 0.0);
                $sunriseJd = (float) ($ctx['sunrise_jd'] ?? 0.0);
                if ($endJd > 0.0 && $endJd <= $todaySunset + 1e-9) {
                    $anchor = $sunriseJd > 0.0 && $endJd < $sunriseJd
                        ? $d->subDay()->toDateString()
                        : $d->toDateString();
                    $seasons[sprintf('%.5F', $endJd)] = [
                        'amavasya_end_jd' => $endJd,
                        'anchor_date' => $anchor,
                    ];
                }
            }
        }

        if ($seasons === []) {
            return null;
        }

        ksort($seasons);
        $values = array_values($seasons);

        return $values[array_key_last($values)];
    }

    /**
     * @param array{amavasya_end_jd: float, anchor_date: string} $season
     *
     * @return array<string, mixed>|null
     */
    private function selectOperationalChandraDarshanaCandidate(array $season, CarbonImmutable $currentDate, callable $fetchHistoricalSnapshot): ?array
    {
        $anchor = CarbonImmutable::parse($season['anchor_date'], $currentDate->timezone);

        for ($i = 0; $i < self::CHANDRA_DARSHANA_MAX_POST_AMAVASYA_EVENINGS; $i++) {
            $date = $anchor->addDays($i);
            if ($date->greaterThan($currentDate)) {
                return null;
            }

            $details = $fetchHistoricalSnapshot($date);
            $ctx = (array) ($details['Resolution_Context'] ?? []);
            if ((float) ($ctx['sunset_jd'] ?? 0.0) + 1e-9 < $season['amavasya_end_jd']) {
                continue;
            }

            $candidate = $this->evaluateChandraDarshanaEvening($date, $details);
            if ($candidate === null) {
                continue;
            }

            if ((bool) ($candidate['operational_candidate'] ?? false)) {
                return $candidate;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function evaluateChandraDarshanaEvening(CarbonImmutable $date, array $details): ?array
    {
        $ctx = (array) ($details['Resolution_Context'] ?? []);
        $sunrise = (float) ($ctx['sunrise_jd'] ?? 0.0);
        $sunset = (float) ($ctx['sunset_jd'] ?? 0.0);
        $nextSunrise = (float) ($ctx['next_sunrise_jd'] ?? 0.0);
        $moonrise = $this->extractJd($details['Moonrise_JD'] ?? ($details['Moonrise'] ?? null));
        $moonset = $this->extractJd($details['Moonset_JD'] ?? ($details['Moonset'] ?? null));

        if ($sunrise <= 0.0 || $sunset <= $sunrise || $nextSunrise <= $sunset) {
            return null;
        }

        $hasWindow = $moonrise !== null
            && $moonset !== null
            && $moonrise < $sunset
            && $sunset < $moonset;
        $visibilityWindow = $hasWindow
            ? ['start_jd' => $sunset, 'end_jd' => $moonset]
            : null;

        $elongation = $this->smallestArcDegrees((float) ($ctx['moon_sun_elongation_at_sunset_degrees'] ?? 0.0));
        $twelveBhagaProxyPassed = $elongation >= self::CHANDRA_DARSHANA_12_BHAGA_PROXY_DEGREES;
        $proxy = $this->computeChandraDarshanaTithiProxy($details);

        $classification = $this->classifyChandraDarshanaEvening([
            'has_post_sunset_horizon_window' => $hasWindow,
            'twelve_bhaga_proxy_passed' => $twelveBhagaProxyPassed,
            'tithi_proxy_aparahna_3' => $proxy['aparahna_3'],
            'tithi_proxy_applicable' => $proxy['applicable'],
        ]);
        $operationalCandidate = $hasWindow && $twelveBhagaProxyPassed;

        $nightMuhurtaSeconds = (($nextSunrise - $sunset) * 86400.0) / 15.0;
        $pradoshaEnd = $sunset + (3.0 * $nightMuhurtaSeconds) / 86400.0;
        $visibilityDuringPradosha = $hasWindow
            && min($moonset, $pradoshaEnd) > $sunset;

        $targetTithi = (int) ($ctx['tithi_index_abs'] ?? 0);
        if ($targetTithi !== 1 && $targetTithi !== 2) {
            $targetTithi = $proxy['applicable'] ? 1 : 2;
        }

        $targetInterval = $this->deriveSnapshotTithiInterval($targetTithi, 'Shukla', $details, null);
        if ($targetInterval === null) {
            $targetInterval = [
                'start_jd' => (float) ($ctx['tithi_start_jd'] ?? $sunset),
                'end_jd' => (float) ($ctx['tithi_end_jd'] ?? $sunset),
            ];
        }

        $moonVisibilitySeconds = $visibilityWindow === null
            ? 0.0
            : max(0.0, ($visibilityWindow['end_jd'] - $visibilityWindow['start_jd']) * 86400.0);
        $targetWindowOverlapSeconds = $visibilityWindow === null
            ? 0.0
            : $this->intervalOverlapSeconds($targetInterval, $visibilityWindow);
        $daylightOverlapSeconds = $this->intervalOverlapSeconds($targetInterval, ['start_jd' => $sunrise, 'end_jd' => $sunset]);

        return [
            'date' => $date->toDateString(),
            'day_offset' => 0,
            'required_tithi' => $targetTithi,
            'target_interval' => $targetInterval,
            'visibility_window' => $visibilityWindow ?? ['start_jd' => $sunset, 'end_jd' => $sunset],
            'target_at_sunrise' => $this->isTargetAtPoint($sunrise, $targetInterval),
            'target_at_karmakala' => $targetWindowOverlapSeconds > 0.0,
            'target_window_overlap_seconds' => $targetWindowOverlapSeconds,
            'target_daylight_overlap_seconds' => $daylightOverlapSeconds,
            'moon_visibility_seconds' => $moonVisibilitySeconds,
            'classification' => $classification,
            'operational_candidate' => $operationalCandidate,
            'reason' => $this->chandraDarshanaReasonForClassification($classification),
            'visibility_assessment' => [
                'model' => 'source_sensitive_monthly_chandra_darshana_first_crescent',
                'operational_candidate' => $operationalCandidate,
                'geometrically_supported_by_engine_proxy' => $operationalCandidate,
                'actual_observation' => 'UNKNOWN',
                'classification' => $classification,
                'summary_classification' => $classification,
                'strict_source_only_result' => 'MONTHLY_DATE_TEXTUALLY_UNDETERMINED',
                'strict_source_only_reason_key' => 'no_explicit_monthly_scriptural_date_command_in_seven_sources',
                'date_selection_basis' => 'application_definition_first_visible_crescent',
                'date_selection_is_explicit_monthly_scriptural_rule' => false,
                'astronomical_basis' => 'modern_proxy_for_surya_siddhanta_12_bhaga_rule',
                'astronomical_computation_basis' => 'engine_longitudinal_separation_at_application_epoch_checked_against_12_degree_proxy_threshold',
                'application_evaluation_epoch' => 'local_sunset',
                'evaluation_epoch_is_explicitly_commanded_by_surya_siddhanta_10_1' => false,
                'modern_proxy_for_surya_siddhanta_12_bhaga_rule' => true,
                'claims_full_surya_siddhanta_chapter_10_recomputation' => false,
                'tithi_corroboration_basis' => 'nibandha_tithi_visibility_indication',
                'tithi_indication_original_context' => 'darsa_anvadhana_and_govardhana_adjudication',
                'tithi_indication_monthly_use' => 'application_level_analogy',
                'pradosha_basis' => 'satsangijivan_childhood_samskara_analogy_only',
                'pradosha_muhurta_basis' => 'dynamic_ratrimana_muhurta',
                'modern_longitudinal_separation_degrees' => $elongation,
                'modern_longitudinal_separation_at_local_sunset_degrees' => $elongation,
                'surya_siddhanta_longitudinal_separation_degrees' => null,
                'proxy_threshold_degrees' => self::CHANDRA_DARSHANA_12_BHAGA_PROXY_DEGREES,
                'moonset_lag_seconds' => $moonVisibilitySeconds,
                'moonset_lag_minutes' => $moonVisibilitySeconds / 60.0,
                'illumination_percent' => (float) ($ctx['moon_illumination_at_sunset_percent'] ?? 0.0),
                'horizon' => [
                    'status' => $hasWindow ? 'POST_SUNSET_HORIZON_WINDOW' : 'NO_POST_SUNSET_HORIZON_WINDOW',
                    'method' => 'rise_set_window_proxy',
                    'requires_moonrise_before_sunset' => true,
                    'requires_moonset_after_sunset' => true,
                    'note' => Localization::translate(
                        'String',
                        'Rise/set proxy; not an apparent upper-limb altitude and next-set search.',
                    ),
                ],
                'surya_siddhanta_visibility' => [
                    'status' => $twelveBhagaProxyPassed ? 'TWELVE_BHAGA_PROXY_PASSED' : 'TWELVE_BHAGA_PROXY_NOT_PASSED',
                    'method' => 'modern_longitudinal_separation_proxy',
                    'threshold_degrees' => self::CHANDRA_DARSHANA_12_BHAGA_PROXY_DEGREES,
                    'claims_exact_siddhantic_recomputation' => false,
                ],
                'nibandha_tithi_indication' => [
                    'status' => $proxy['aparahna_3'] ? 'FULL_APARAHNA_INDICATION_PRESENT' : 'FULL_APARAHNA_INDICATION_NOT_ESTABLISHED',
                    'applicable' => $proxy['applicable'],
                    'original_context' => 'darsa_anvadhana_and_govardhana_adjudication',
                    'monthly_use' => 'application_level_analogy',
                ],
                'stronger_six_muhurta_indication' => [
                    'status' => $proxy['to_sunset_6'] ? 'SIX_MUHURTA_INTERVAL_COVERED' : 'SIX_MUHURTA_INTERVAL_NOT_COVERED',
                    'requires_dvitiya_start_at_or_before_aparahna_start' => true,
                    'requires_dvitiya_end_at_or_after_sunset' => true,
                ],
                'pradosha' => [
                    'status' => $visibilityDuringPradosha ? 'PRADOSHA_OVERLAP_PRESENT' : 'NO_PRADOSHA_OVERLAP',
                    'basis' => 'satsangijivan_childhood_samskara_analogy_only',
                    'muhurta_basis' => 'dynamic_ratrimana_muhurta',
                    'used_as_rejection_rule' => false,
                ],
                'monthly_observance' => [
                    'strict_source_only_status' => 'MONTHLY_DATE_TEXTUALLY_UNDETERMINED',
                    'application_status' => $operationalCandidate ? 'APPLICATION_FIRST_CRESCENT_CANDIDATE' : 'NOT_SELECTED_BY_APPLICATION_MODEL',
                    'date_selection_basis' => 'application_definition_first_visible_crescent',
                    'date_selection_is_explicit_monthly_scriptural_rule' => false,
                ],
                'has_post_sunset_horizon_window' => $hasWindow,
                'tithi_proxy_applicable' => $proxy['applicable'],
                'dvitiya_covers_full_aparahna_3_muhurtas' => $proxy['aparahna_3'],
                'dvitiya_covers_aparahna_through_sunset_6_muhurtas' => $proxy['to_sunset_6'],
                'pratipada_post_sunrise_muhurtas' => $proxy['pratipada_post_sunrise_muhurtas'],
                'dvitiya_start_jd' => $proxy['dvitiya_start_jd'],
                'dvitiya_end_jd' => $proxy['dvitiya_end_jd'],
                'visibility_during_pradosha' => $visibilityDuringPradosha,
                'forbidden_modern_thresholds_applied' => false,
                'reason' => $this->chandraDarshanaReasonForClassification($classification),
                'basis' => 'application_definition_first_visible_crescent',
            ],
        ];
    }

    /** @param array<string, bool> $flags */
    private function classifyChandraDarshanaEvening(array $flags): string
    {
        if ($flags['has_post_sunset_horizon_window']
            && $flags['twelve_bhaga_proxy_passed']
            && $flags['tithi_proxy_applicable']
            && $flags['tithi_proxy_aparahna_3']) {
            return 'APPLICATION_CRESCENT_CANDIDATE_WITH_NIBANDHA_INDICATION';
        }

        if ($flags['has_post_sunset_horizon_window'] && $flags['twelve_bhaga_proxy_passed']) {
            return 'APPLICATION_CRESCENT_CANDIDATE';
        }

        if ($flags['tithi_proxy_applicable'] && $flags['tithi_proxy_aparahna_3']) {
            return 'NIBANDHA_TITHI_INDICATION_ASTRONOMICAL_PROXY_DIVERGENCE';
        }

        if (!$flags['has_post_sunset_horizon_window']) {
            return 'NO_POST_SUNSET_HORIZON_WINDOW';
        }

        return 'TWELVE_BHAGA_PROXY_NOT_PASSED';
    }

    /**
     * @return array{
     *   applicable: bool,
     *   aparahna_3: bool,
     *   to_sunset_6: bool,
     *   pratipada_post_sunrise_muhurtas: ?float,
     *   dvitiya_start_jd: ?float,
     *   dvitiya_end_jd: ?float
     * }
     */
    private function computeChandraDarshanaTithiProxy(array $details): array
    {
        $ctx = (array) ($details['Resolution_Context'] ?? []);
        $abs = (int) ($ctx['tithi_index_abs'] ?? 0);
        $sunrise = (float) ($ctx['sunrise_jd'] ?? 0.0);
        $sunset = (float) ($ctx['sunset_jd'] ?? 0.0);
        $dayMuhurtaSeconds = $sunset > $sunrise ? (($sunset - $sunrise) * 86400.0) / 15.0 : 0.0;

        if ($dayMuhurtaSeconds <= 0.0 || $abs !== 1 || $this->transitEngine === null) {
            return [
                'applicable' => false,
                'aparahna_3' => false,
                'to_sunset_6' => false,
                'pratipada_post_sunrise_muhurtas' => null,
                'dvitiya_start_jd' => null,
                'dvitiya_end_jd' => null,
            ];
        }

        $aparahnaStart = $sunrise + (9.0 * $dayMuhurtaSeconds) / 86400.0;
        $aparahnaEnd = $sunrise + (12.0 * $dayMuhurtaSeconds) / 86400.0;
        $pratipadaEnd = (float) ($ctx['tithi_end_jd'] ?? 0.0);
        if ($pratipadaEnd <= 0.0) {
            return [
                'applicable' => false,
                'aparahna_3' => false,
                'to_sunset_6' => false,
                'pratipada_post_sunrise_muhurtas' => null,
                'dvitiya_start_jd' => null,
                'dvitiya_end_jd' => null,
            ];
        }

        $dvitiyaStart = $pratipadaEnd;
        $transitEngine = $this->transitEngine;
        $dvitiyaEnd = $transitEngine->findAngleCrossing(
            $dvitiyaStart + 1e-5,
            24.0,
            1,
            static fn (float $jd): float => $transitEngine->getMoonSunAngle($jd),
        );

        if ($dvitiyaEnd <= $dvitiyaStart) {
            return [
                'applicable' => true,
                'aparahna_3' => false,
                'to_sunset_6' => false,
                'pratipada_post_sunrise_muhurtas' => null,
                'dvitiya_start_jd' => $dvitiyaStart,
                'dvitiya_end_jd' => null,
            ];
        }

        return [
            'applicable' => true,
            'aparahna_3' => $dvitiyaStart <= $aparahnaStart + 1e-9 && $dvitiyaEnd >= $aparahnaEnd - 1e-9,
            'to_sunset_6' => $dvitiyaStart <= $aparahnaStart + 1e-9 && $dvitiyaEnd >= $sunset - 1e-9,
            'pratipada_post_sunrise_muhurtas' => max(0.0, ($pratipadaEnd - $sunrise) * 86400.0) / $dayMuhurtaSeconds,
            'dvitiya_start_jd' => $dvitiyaStart,
            'dvitiya_end_jd' => $dvitiyaEnd,
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
                'winning_score' => 1500 + min(240, (int) floor($moonVisibilitySeconds / 60.0)),
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

    private function chandraDarshanaReasonForClassification(string $classification): string
    {
        return match ($classification) {
            'APPLICATION_CRESCENT_CANDIDATE_WITH_NIBANDHA_INDICATION' => 'chandra_darshana_application_crescent_candidate_with_nibandha_indication',
            'APPLICATION_CRESCENT_CANDIDATE' => 'chandra_darshana_application_crescent_candidate',
            'NIBANDHA_TITHI_INDICATION_ASTRONOMICAL_PROXY_DIVERGENCE' => 'chandra_darshana_nibandha_tithi_indication_astronomical_proxy_divergence',
            'NO_POST_SUNSET_HORIZON_WINDOW' => 'chandra_darshana_no_post_sunset_horizon_window',
            default => 'chandra_darshana_twelve_bhaga_proxy_not_passed',
        };
    }

    private function smallestArcDegrees(float $degrees): float
    {
        $d = fmod($degrees, 360.0);
        if ($d < 0.0) {
            $d += 360.0;
        }

        return min($d, 360.0 - $d);
    }

    private function moonVisibleAfterSunset(array $details): bool
    {
        $ctx = (array) ($details['Resolution_Context'] ?? []);
        $sunsetJd = (float) ($ctx['sunset_jd'] ?? 0.0);
        $panchanga = (array) ($details['Panchanga'] ?? []);
        $moonriseJd = $this->extractJd($details['Moonrise_JD'] ?? ($details['Moonrise'] ?? ($panchanga['Moonrise'] ?? null)));
        $moonsetJd = $this->extractJd($details['Moonset_JD'] ?? ($details['Moonset'] ?? ($panchanga['Moonset'] ?? null)));

        return $sunsetJd > 0.0
            && $moonriseJd !== null
            && $moonsetJd !== null
            && $moonriseJd < $sunsetJd
            && $sunsetJd < $moonsetJd;
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
