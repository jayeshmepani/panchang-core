<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Festivals;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Core\Constants\ClassicalTimeConstants;
use LogicException;

/**
 * Candidate construction, scoring, karmakala windows, and Bhadra decision helpers.
 *
 * Structure-only split from FestivalRuleEngine. Algorithms unchanged.
 *
 * @see docs/FESTIVAL_REFACTOR_PARITY_CONTRACT.md
 */
trait FestivalRuleCandidates
{
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
            $targetStart = (float) $ctxToday['tithi_end_jd'];
            $targetEnd = $this->firstBoundaryAfter($targetStart, [
                $ctxTomorrow['prev_tithi_end_jd'] ?? null,
                $ctxTomorrow['tithi_start_jd'] ?? null,
                $ctxTomorrow['tithi_end_jd'] ?? null,
            ]);
            if ($targetEnd === null) {
                return null;
            }

            return [
                'start_jd' => $targetStart,
                'end_jd' => $targetEnd,
            ];
        }

        return null;
    }

    /** @param list<mixed> $boundaries */
    private function firstBoundaryAfter(float $startJd, array $boundaries): ?float
    {
        $valid = [];
        foreach ($boundaries as $boundary) {
            if (!is_numeric($boundary)) {
                continue;
            }

            $boundaryJd = (float) $boundary;
            if ($boundaryJd > $startJd) {
                $valid[] = $boundaryJd;
            }
        }

        if ($valid === []) {
            return null;
        }

        return min($valid);
    }

    /** @return list<array{tithi:int, interval:array{start_jd:float, end_jd:float}}> */
    private function deriveTargetIntervals(array $requiredTithis, string $paksha, int $todayAbs, int $tomorrowAbs, array $ctxToday, array $ctxTomorrow): array
    {
        $intervals = [];

        foreach ($requiredTithis as $tithi) {
            $targetAbs = $paksha === 'Krishna' ? (15 + $tithi) : $tithi;
            $interval = $this->deriveTargetInterval($targetAbs, $todayAbs, $tomorrowAbs, $ctxToday, $ctxTomorrow);
            if ($interval === null) {
                continue;
            }

            $intervals[] = [
                'tithi' => $tithi,
                'interval' => $interval,
            ];
        }

        return $intervals;
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
        $panchanga = (array) ($details['Panchanga'] ?? []);
        $moonriseJd = $this->extractJd($details['Moonrise_JD'] ?? ($details['Moonrise'] ?? ($panchanga['Moonrise'] ?? null)));
        if ($moonriseJd !== null) {
            $ctx['moonrise_jd'] = $moonriseJd;
        }

        $sunriseJd = (float) ($ctx['sunrise_jd'] ?? 0.0);
        $nextSunriseJd = (float) ($ctx['next_sunrise_jd'] ?? 0.0);
        $sunsetJd = (float) ($ctx['sunset_jd'] ?? 0.0);
        $karmakalaAvailable = $karmakalaType !== 'moonrise' || $moonriseJd !== null;
        if ($karmakalaAvailable) {
            $karmakalaJd = $this->karmakalaJd($karmakalaType, $ctx);
            $karmakalaWindow = $this->karmakalaWindowJd($karmakalaType, $ctx);
        } else {
            $karmakalaJd = -1.0;
            $karmakalaWindow = ['start_jd' => -1.0, 'end_jd' => -1.0];
        }

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
            : ($karmakalaAvailable && $this->isTargetAtPoint($karmakalaJd, $targetInterval));
        $targetAtKarmakala = $targetAtKarmakalaPoint || ($karmakalaAvailable && $targetWindowOverlapSeconds > 0.0 && !(bool) ($rule['require_sunrise_vyapini'] ?? false));
        $targetDuringObservance = $karmakalaAvailable
            ? ($targetInterval['start_jd'] < $nextSunriseJd && $targetInterval['end_jd'] > $sunriseJd)
            : false;
        $forbiddenPrevTithiAt = $rule['forbid_previous_tithi_at'] ?? null;
        $forbiddenPrevTithiJd = is_string($forbiddenPrevTithiAt) && $forbiddenPrevTithiAt !== ''
            ? $this->karmakalaJd($forbiddenPrevTithiAt, $ctx)
            : null;
        $prevTithiAtForbiddenPoint = is_float($forbiddenPrevTithiJd) && $prevTithiEndJd > $forbiddenPrevTithiJd;
        $requiredPrevTithiAt = $rule['require_previous_tithi_at'] ?? null;
        $requiredPrevTithiJd = is_string($requiredPrevTithiAt) && $requiredPrevTithiAt !== ''
            ? $this->karmakalaJd($requiredPrevTithiAt, $ctx)
            : null;
        $prevTithiAtRequiredPoint = is_float($requiredPrevTithiJd) && $prevTithiEndJd > $requiredPrevTithiJd;

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
            'prev_tithi_at_sunrise' => $prevTithiEndJd > $sunriseJd,
            'prev_tithi_at_forbidden_karmakala' => $prevTithiAtForbiddenPoint,
            'prev_tithi_at_required_point' => $prevTithiAtRequiredPoint,
            'target_window_start_jd' => $karmakalaWindow['start_jd'],
            'target_window_end_jd' => $karmakalaWindow['end_jd'],
            'sunrise_jd' => $sunriseJd,
            'sunset_jd' => $sunsetJd,
            'next_sunrise_jd' => $nextSunriseJd,
            'previous_sunrise_jd' => (float) ($ctx['previous_sunrise_jd'] ?? ($sunriseJd - max(0.0, $nextSunriseJd - $sunriseJd))),
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

        // Bahukala-purva / prefer-first: when both candidate days carry the target in the
        // observance kala, take the earlier day even if the later day scores higher on
        // sunrise/overlap secondary factors (Kali Chaudas sangava → 2026-11-07 not 11-08).
        if ($preferFirstKarmakala && $left['target_at_karmakala'] && $right['target_at_karmakala']) {
            return $left['day_offset'] <=> $right['day_offset'];
        }

        if ($left['score'] !== $right['score']) {
            return $right['score'] <=> $left['score'];
        }

        if ($vriddhi) {
            if ($vriddhiPreference === 'last') {
                return $right['day_offset'] <=> $left['day_offset'];
            }

            return $left['day_offset'] <=> $right['day_offset'];
        }

        if ($kshaya) {
            return $this->compareKshayaPreference($left, $right, $kshayaPreference);
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
            return $this->compareKshayaPreference($left, $right, $kshayaPreference);
        }

        return 0;
    }

    /**
     * Kshaya tithi touches neither sunrise. Prefer:
     * - merged_host_day / merged_day / host_day: civil day hosting the skipped tithi interval
     * - last: later candidate day
     * - first (default): earlier candidate day
     */
    private function compareKshayaPreference(array $left, array $right, string $kshayaPreference): int
    {
        $preference = strtolower(trim($kshayaPreference));

        if (in_array($preference, ['merged_host_day', 'merged_day', 'host_day', 'merged'], true)) {
            if (($left['target_during_observance'] ?? false) !== ($right['target_during_observance'] ?? false)) {
                return ((int) ($right['target_during_observance'] ?? false)) <=> ((int) ($left['target_during_observance'] ?? false));
            }

            $leftOverlap = (float) ($left['target_window_overlap_seconds'] ?? $left['winning_window_overlap_seconds'] ?? 0.0);
            $rightOverlap = (float) ($right['target_window_overlap_seconds'] ?? $right['winning_window_overlap_seconds'] ?? 0.0);
            if ($leftOverlap !== $rightOverlap) {
                return $rightOverlap <=> $leftOverlap;
            }

            // Stable fallback: earlier civil day of the host pair.
            return $left['day_offset'] <=> $right['day_offset'];
        }

        if ($preference === 'last') {
            return $right['day_offset'] <=> $left['day_offset'];
        }

        return $left['day_offset'] <=> $right['day_offset'];
    }

    private function compareResolvedFestivalOutcome(array $left, array $right, bool $preferHigherTithi = false): int
    {
        if ($left['score'] !== $right['score']) {
            return $right['score'] <=> $left['score'];
        }

        if ($left['required_tithi'] !== $right['required_tithi']) {
            return $preferHigherTithi
                ? ($right['required_tithi'] <=> $left['required_tithi'])
                : ($left['required_tithi'] <=> $right['required_tithi']);
        }

        return $left['winner']['day_offset'] <=> $right['winner']['day_offset'];
    }

    private function resolveSpecialFestivalCandidate(
        CarbonImmutable $date,
        array $rule,
        array $candidates,
        array $today,
        array $targetInterval
    ): ?array {
        if (isset($rule['tithi_boundary_rule'])) {
            return $this->resolveTithiBoundaryTruthTable($date, $rule, $candidates, $targetInterval);
        }

        if ((bool) ($rule['holika_lunar_eclipse_exception'] ?? false)) {
            return $this->resolveHolikaLunarEclipseException($candidates, $today);
        }

        if ((bool) ($rule['janmashtami_truth_table'] ?? false)) {
            return $this->resolveJanmashtamiTruthTable($candidates, $today, $targetInterval);
        }

        if ((bool) ($rule['masik_janmashtami_truth_table'] ?? false)) {
            return $this->resolveMasikJanmashtamiTruthTable($candidates);
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

        $isEkadashiRule = ((int) ($rule['tithi'] ?? 0) === 11)
            && ((bool) ($rule['fasting'] ?? false)
                || (bool) ($rule['ekadashi_nirnay_table'] ?? false)
                || (bool) ($rule['require_vaishnava_ekadashi_today'] ?? false));

        // Vaishnava default_tradition: never let madhyahna entry prefer override
        // Vaishnava Ekadashi nirnay (named must match ISKCON fasting day).
        $tradition = function_exists('config') ? config('panchang.festivals.default_tradition', 'Vaishnava') : 'Vaishnava';
        $isVaishnavaMode = strcasecmp((string) $tradition, 'Vaishnava') === 0;
        if ((bool) ($rule['prefer_tithi_entry_before_madhyahna'] ?? false)
            && (!$isVaishnavaMode || !$isEkadashiRule)) {
            $winner = $this->resolveTithiEntryBeforeMadhyahna($candidates);
            if ($winner !== null) {
                return $winner;
            }
        }

        if ((bool) ($rule['ekadashi_nirnay_table'] ?? false)
            || (bool) ($rule['require_vaishnava_ekadashi_today'] ?? false)
            || $isEkadashiRule) {
            return $this->resolveEkadashiNirnayTruthTable($candidates, $targetInterval);
        }

        if ((bool) ($rule['purnima_vrat_18_ghadi_rule'] ?? false)) {
            return $this->resolvePurnimaVratTruthTable($candidates);
        }

        if ((bool) ($rule['pradosh_truth_table'] ?? false) || $this->isPradoshRule($rule)) {
            return $this->resolvePradoshTruthTable($candidates);
        }

        if ((bool) ($rule['sankashti_truth_table'] ?? false) || $this->isSankashtiRule($rule)) {
            return $this->resolveSankashtiTruthTable($candidates, $rule);
        }

        if ((bool) ($rule['vinayaki_chaturthi_truth_table'] ?? false)) {
            return $this->resolveVinayakiChaturthiTruthTable($candidates);
        }

        if ((bool) ($rule['narasimha_jayanti_truth_table'] ?? false)) {
            return $this->resolveNarasimhaJayantiTruthTable($candidates);
        }

        if ((bool) ($rule['raksha_bandhan_truth_table'] ?? false)) {
            return $this->resolveRakshaBandhanTruthTable($candidates, $targetInterval);
        }

        if ((bool) ($rule['govardhan_annakut_truth_table'] ?? false)) {
            return $this->resolveGovardhanAnnakutTruthTable($candidates, $targetInterval);
        }

        if ((bool) ($rule['nag_panchami_paraviddha_table'] ?? false)) {
            return $this->resolveNagPanchamiTruthTable($candidates, $targetInterval);
        }

        if ((bool) ($rule['durgashtami_paraviddha_table'] ?? false)) {
            return $this->resolveDurgashtamiTruthTable($candidates, $targetInterval);
        }

        if ((bool) ($rule['akshaya_tritiya_purvahna_table'] ?? false)) {
            return $this->resolveAkshayaTritiyaTruthTable($candidates, $targetInterval);
        }

        if ((bool) ($rule['anant_chaturdashi_paraviddha_table'] ?? false)) {
            return $this->resolveAnantChaturdashiTruthTable($candidates, $targetInterval);
        }

        if ((bool) ($rule['navratri_pratipada_table'] ?? false)) {
            return $this->resolveNavratriPratipadaTruthTable($candidates, $targetInterval);
        }

        if ((bool) ($rule['durva_ashtami_purvaviddha_table'] ?? false)) {
            return $this->resolveDurvaAshtamiTruthTable($candidates, $targetInterval);
        }

        if ((bool) ($rule['lalita_panchami_aparahna_table'] ?? false)) {
            return $this->resolveFirstFallbackKarmakalaTruthTable($candidates, 'lalita_panchami_aparahna');
        }

        if ((bool) ($rule['akshaya_navami_purvahna_table'] ?? false)) {
            return $this->resolveFirstFallbackKarmakalaTruthTable($candidates, 'akshaya_navami_purvahna');
        }

        if ((bool) ($rule['naraka_chaturdashi_abhyanga_table'] ?? false)) {
            return $this->resolveNarakaChaturdashiAbhyangaTruthTable($candidates);
        }

        if ((bool) ($rule['darsha_amavasya_aparahna_table'] ?? false)) {
            return $this->resolveDarshaAmavasyaTruthTable($candidates);
        }

        if ((bool) ($rule['gauri_tritiya_parayuta_table'] ?? false)) {
            return $this->resolveGauriTritiyaTruthTable($candidates);
        }

        if ((bool) ($rule['madhyahna_purvatithi_vedha_rejection'] ?? false)) {
            $resolutionPolicy = (array) ($rule['resolution_policy'] ?? []);

            return $this->resolveMadhyahnaPurvaTithiVedhaTable(
                $candidates,
                (string) ($rule['vriddhi_preference'] ?? 'first'),
                (bool) ($resolutionPolicy['both_days_prefer_navami_yuta'] ?? false),
            );
        }

        if ((bool) ($rule['panchami_viddha_allowed'] ?? false)) {
            return $this->resolveSkandaSashtiTruthTable($candidates);
        }

        if ((bool) ($rule['ashtami_viddha_rejection'] ?? false)) {
            return $this->resolveRamNavamiTruthTable($candidates, $today, $targetInterval);
        }

        if ((bool) ($rule['trayodashi_viddha_rejection'] ?? false) || (bool) ($rule['previous_tithi_viddha_rejection'] ?? false)) {
            return $this->resolveGenericViddhaRejectionTable(
                $candidates,
                (bool) ($rule['kshaya_accept_previous_tithi_vedha'] ?? false),
                (string) ($rule['vriddhi_preference'] ?? 'last'),
                (string) ($rule['kshaya_preference'] ?? 'first')
            );
        }

        return null;
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
        $type = $this->normalizeKarmakalaType($type);
        $sunrise = (float) $ctx['sunrise_jd'];
        $sunset = (float) $ctx['sunset_jd'];
        $nextSunrise = (float) $ctx['next_sunrise_jd'];
        $moonrise = isset($ctx['moonrise_jd']) ? (float) $ctx['moonrise_jd'] : null;
        $dayDuration = $sunset - $sunrise;
        $nightDuration = $nextSunrise - $sunset;
        $dayMuhurta = $dayDuration / 15.0;
        $nightMuhurta = $nightDuration / 15.0;
        $fixedGhati = ClassicalTimeConstants::GHATIKA_IN_MINUTES / 1440.0;

        return match ($type) {
            'sunrise' => ['start_jd' => $sunrise, 'end_jd' => $sunrise],
            'arunodaya' => ['start_jd' => $sunrise - (2.0 * $nightMuhurta), 'end_jd' => $sunrise],
            'purvahna' => ['start_jd' => $sunrise, 'end_jd' => $sunrise + ($dayDuration / 2.0)],
            'pratah_kal' => ['start_jd' => $sunrise, 'end_jd' => $sunrise + ($dayDuration / 5.0)],
            'pratah_first_third' => ['start_jd' => $sunrise, 'end_jd' => $sunrise + ($dayDuration / 15.0)],
            'sangava' => ['start_jd' => $sunrise + ($dayDuration / 5.0), 'end_jd' => $sunrise + ($dayDuration * 2.0 / 5.0)],
            'madhyahna' => ['start_jd' => $sunrise + ($dayDuration * 2.0 / 5.0), 'end_jd' => $sunrise + ($dayDuration * 3.0 / 5.0)],
            'daytime' => ['start_jd' => $sunrise, 'end_jd' => $sunset],
            'abhijit' => ['start_jd' => $sunrise + (7.0 * $dayMuhurta), 'end_jd' => $sunrise + (8.0 * $dayMuhurta)],
            'aparahna' => ['start_jd' => $sunrise + ($dayDuration * 3.0 / 5.0), 'end_jd' => $sunrise + ($dayDuration * 4.0 / 5.0)],
            'vijaya_kaal' => ['start_jd' => $sunrise + (10.0 * $dayMuhurta), 'end_jd' => $sunrise + (11.0 * $dayMuhurta)],
            'sayankala' => ['start_jd' => $sunrise + ($dayDuration * 4.0 / 5.0), 'end_jd' => $sunset],
            'sunset' => [
                'start_jd' => $sunset - (ClassicalTimeConstants::SAYAM_SANDHYA_BEFORE_SUNSET_GHATIKAS * $fixedGhati),
                'end_jd' => $sunset + (ClassicalTimeConstants::SAYAM_SANDHYA_AFTER_SUNSET_GHATIKAS * $fixedGhati),
            ],
            'prathama_ratri' => ['start_jd' => $sunset, 'end_jd' => $sunset + ($nightDuration / 2.0)],
            'ratri' => ['start_jd' => $sunset + (3.0 * $nightMuhurta), 'end_jd' => $sunset + (7.0 * $nightMuhurta)],
            'nishitha' => [
                'start_jd' => $sunset + (7.0 * $nightMuhurta),
                'end_jd' => $sunset + (8.0 * $nightMuhurta),
            ],
            'usha' => ['start_jd' => $sunset + (8.0 * $nightMuhurta), 'end_jd' => $sunset + (13.0 * $nightMuhurta)],
            'moonrise' => $moonrise !== null
                ? ['start_jd' => $moonrise, 'end_jd' => $moonrise]
                : throw new LogicException('Moonrise karmakala requested but moonrise_jd is unavailable in festival context.'),
            'pradosha' => ['start_jd' => $sunset, 'end_jd' => $sunset + (3.0 * $nightMuhurta)],
            'tithi_boundary' => ['start_jd' => $sunrise, 'end_jd' => $nextSunrise],
            default => throw new LogicException(sprintf("Unknown karmakala_type '%s' in festival resolver.", $type)),
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

        // For Govardhan, the dedicated selection path handles the tithi-based Chandra Darshana risk.

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

    /** Deepavali/Chopda/Lakshmi must always use amanta Ashwina aparahna civil day under both calendars. */
    private function isDeepavaliCivilDayLocked(string $festivalName, array $rule): bool
    {
        if ((bool) ($rule['lock_deepavali_civil_day'] ?? false)) {
            return true;
        }

        $lockedNames = [
            'Chopda Pujan',
            'Lakshmi Puja (Deepavali)',
            'Lakshmi Puja',
            'Diwali',
            'Deepavali',
        ];

        if (in_array($festivalName, $lockedNames, true)) {
            return true;
        }

        foreach ((array) ($rule['aliases'] ?? []) as $alias) {
            if (in_array((string) $alias, $lockedNames, true)) {
                return true;
            }
        }

        return false;
    }

}
