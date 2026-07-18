<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Festivals;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Festivals\Support\FestivalShared;
use JayeshMepani\PanchangCore\Panchanga\KalaNirnayaEngine;

/**
 * Classical truth-table resolvers and special-day classifiers.
 *
 * Structure-only split from FestivalRuleEngine. Algorithms unchanged.
 *
 * @see docs/FESTIVAL_REFACTOR_PARITY_CONTRACT.md
 */
trait FestivalRuleTruthTables
{
    private function usesExclusiveTruthTable(array $rule): bool
    {
        foreach (['janmashtami_truth_table', 'masik_janmashtami_truth_table', 'vijayadashami_truth_table', 'govatsa_truth_table', 'mahashivaratri_truth_table', 'diwali_truth_table', 'ekadashi_nirnay_table', 'purnima_vrat_18_ghadi_rule', 'pradosh_truth_table', 'sankashti_truth_table', 'vinayaki_chaturthi_truth_table', 'narasimha_jayanti_truth_table', 'raksha_bandhan_truth_table', 'govardhan_annakut_truth_table', 'nag_panchami_paraviddha_table', 'durgashtami_paraviddha_table', 'akshaya_tritiya_purvahna_table', 'anant_chaturdashi_paraviddha_table', 'navratri_pratipada_table', 'durva_ashtami_purvaviddha_table', 'lalita_panchami_aparahna_table', 'akshaya_navami_purvahna_table', 'naraka_chaturdashi_abhyanga_table', 'darsha_amavasya_aparahna_table', 'gauri_tritiya_parayuta_table', 'madhyahna_purvatithi_vedha_rejection', 'panchami_viddha_allowed', 'ashtami_viddha_rejection', 'trayodashi_viddha_rejection', 'previous_tithi_viddha_rejection', 'tithi_boundary_rule'] as $flag) {
            if (($rule[$flag] ?? false) === true) {
                return true;
            }
        }

        return $this->isEkadashiNirnayRule($rule) || $this->isPradoshRule($rule) || $this->isSankashtiRule($rule);
    }

    private function isEkadashiNirnayRule(array $rule): bool
    {
        return FestivalShared::isEkadashiNirnayRule($rule);
    }

    private function isPradoshRule(array $rule): bool
    {
        return FestivalShared::isPradoshRule($rule);
    }

    private function isSankashtiRule(array $rule): bool
    {
        return FestivalShared::isSankashtiRule($rule);
    }

    private function isPhuldolotsavaRule(array $rule): bool
    {
        return (string) ($rule['resolver'] ?? '') === 'phuldolotsava'
            || (string) ($rule['family'] ?? '') === 'phuldolotsava'
            || (string) ($rule['dual_day_rule'] ?? '') === 'if_purnima_and_pratipada_both_have_sunrise_uttara_phalguni_choose_purnima';
    }

    /**
     * Satsangi Jeevan 4.60 Phuldolotsava:
     * - Phalguna boundary around Shukla Purnima → Krishna Pratipada
     * - mandatory Uttara Phalguni at sunrise on the selected civil day
     * - if both Purnima and Pratipada have sunrise Uttara Phalguni, choose Purnima
     * - if only one candidate has it, choose that day
     * - if neither has it, fall back to the main Pratipada candidate (Padwa)
     */
    private function resolvePhuldolotsavaFestival(
        string $festivalName,
        array $rule,
        CarbonImmutable $date,
        array $today,
        array $tomorrow
    ): ?array {
        $requiredNakshatra = (string) ($rule['nakshatra'] ?? 'Uttara Phalguni');
        $karmakalaType = $this->normalizeKarmakalaType((string) ($rule['karmakala_type'] ?? 'sunrise'));
        $requireSunriseNakshatra = (bool) ($rule['require_sunrise_nakshatra'] ?? true);

        $daySpecs = [
            [
                'details' => $today,
                'date' => $date,
                'offset' => 0,
            ],
            [
                'details' => $tomorrow,
                'date' => $date->addDay(),
                'offset' => 1,
            ],
        ];

        $purnimaDay = null;
        $pratipadaDay = null;

        foreach ($daySpecs as $spec) {
            $role = $this->phuldolotsavaCandidateRole($spec['details'], $rule);
            if ($role === null) {
                continue;
            }

            $hasSunriseNakshatra = $this->hasSunriseNakshatra($spec['details'], $requiredNakshatra);
            if ($requireSunriseNakshatra && !$hasSunriseNakshatra) {
                // Keep the day as a fallback candidate, but mark nakshatra miss.
            }

            $entry = [
                'date' => $spec['date']->toDateString(),
                'day_offset' => $spec['offset'],
                'role' => $role,
                'has_sunrise_nakshatra' => $hasSunriseNakshatra,
                'paksha' => $role === 'purnima' ? 'Shukla' : 'Krishna',
                'tithi' => $role === 'purnima' ? 15 : 1,
            ];

            if ($role === 'purnima') {
                $purnimaDay = $entry;
            } else {
                $pratipadaDay = $entry;
            }
        }

        if ($purnimaDay === null && $pratipadaDay === null) {
            return null;
        }

        $winner = null;
        $reason = null;

        $purnimaMatch = is_array($purnimaDay) && $purnimaDay['has_sunrise_nakshatra'];
        $pratipadaMatch = is_array($pratipadaDay) && $pratipadaDay['has_sunrise_nakshatra'];

        if ($purnimaMatch && $pratipadaMatch) {
            $winner = $purnimaDay;
            $reason = 'phuldolotsava_both_have_sunrise_uttara_phalguni_choose_purnima';
        } elseif ($purnimaMatch) {
            $winner = $purnimaDay;
            $reason = 'phuldolotsava_purnima_sunrise_uttara_phalguni';
        } elseif ($pratipadaMatch) {
            $winner = $pratipadaDay;
            $reason = 'phuldolotsava_pratipada_sunrise_uttara_phalguni';
        } elseif (is_array($pratipadaDay) && is_array($purnimaDay)) {
            // Neither candidate has the sunrise nakshatra: main Padwa/Pratipada day.
            $winner = $pratipadaDay;
            $reason = 'phuldolotsava_fallback_pratipada_without_sunrise_uttara_phalguni';
        } else {
            // Only one side of the Purnima/Pratipada pair is in this today/tomorrow window.
            // Defer to the pair window so the fallback does not duplicate on the next day.
            return null;
        }

        return [
            'festival_name' => $festivalName,
            'required_tithi' => $winner['tithi'],
            'paksha' => $winner['paksha'],
            'calendar_type' => strtolower((string) ($today['Hindu_Calendar']['Calendar_Type'] ?? AstroCore::getConfig('panchang.defaults.calendar_type', 'amanta'))),
            'karmakala_type' => $karmakalaType,
            'tithi_at_karmakala_today' => $purnimaMatch || ($pratipadaDay !== null && $pratipadaDay['day_offset'] === 0 && $pratipadaMatch),
            'tithi_at_karmakala_tomorrow' => ($purnimaDay !== null && $purnimaDay['day_offset'] === 1 && $purnimaMatch)
                || ($pratipadaDay !== null && $pratipadaDay['day_offset'] === 1 && $pratipadaMatch),
            'tithi_coverage_seconds_today' => 0.0,
            'tithi_coverage_seconds_tomorrow' => 0.0,
            'tithi_at_sunrise_today' => $this->phuldolotsavaCandidateRole($today, $rule) !== null,
            'tithi_at_sunrise_tomorrow' => $this->phuldolotsavaCandidateRole($tomorrow, $rule) !== null,
            'is_tithi_vriddhi' => false,
            'is_tithi_kshaya' => false,
            'target_tithi_start_jd' => null,
            'target_tithi_end_jd' => null,
            'standard_date' => $winner['date'],
            'observance_date' => $winner['date'],
            'observance_note' => null,
            'decision' => [
                'strict_karmakala' => (bool) ($rule['strict_karmakala'] ?? true),
                'require_sunrise_nakshatra' => $requireSunriseNakshatra,
                'preferred_nakshatra' => $requiredNakshatra,
                'dual_day_rule' => $rule['dual_day_rule'] ?? 'if_purnima_and_pratipada_both_have_sunrise_uttara_phalguni_choose_purnima',
                'purnima_has_sunrise_nakshatra' => $purnimaMatch,
                'pratipada_has_sunrise_nakshatra' => $pratipadaMatch,
                'winning_role' => $winner['role'],
                'winning_reason' => $reason,
                'winning_score' => $purnimaMatch || $pratipadaMatch ? 1100 : 700,
            ],
        ];
    }

    /** @return 'pratipada'|'purnima'|null */
    private function phuldolotsavaCandidateRole(array $details, array $rule): ?string
    {
        if (!$this->phuldolotsavaMonthAllowed($details, $rule)) {
            return null;
        }

        $tithi = (array) ($details['Tithi'] ?? []);
        $paksha = (string) ($tithi['paksha'] ?? '');
        $index = (int) ($tithi['index'] ?? 0);
        if ($index > 15) {
            $index -= 15;
        }

        // Prefer sunrise tithi when present (civil-day classification).
        $sunriseTithi = (array) ($details['Tithi_At_Sunrise'] ?? []);
        if ($sunriseTithi !== []) {
            $sunrisePaksha = (string) ($sunriseTithi['paksha'] ?? $paksha);
            $sunriseIndex = (int) ($sunriseTithi['index'] ?? $index);
            if ($sunriseIndex > 15) {
                $sunriseIndex -= 15;
            }

            $paksha = $sunrisePaksha;
            $index = $sunriseIndex;
        }

        if ($paksha === 'Shukla' && $index === 15) {
            return 'purnima';
        }

        if ($paksha === 'Krishna' && $index === 1) {
            return 'pratipada';
        }

        return null;
    }

    private function phuldolotsavaMonthAllowed(array $details, array $rule): bool
    {
        $calendar = (array) ($details['Hindu_Calendar'] ?? []);
        $amanta = $this->normalizeMonthName((string) ($calendar['Month_Amanta_En'] ?? $calendar['Month_Amanta'] ?? ''));
        $purnimanta = $this->normalizeMonthName((string) ($calendar['Month_Purnimanta_En'] ?? $calendar['Month_Purnimanta'] ?? ''));
        $calendarType = strtolower((string) ($calendar['Calendar_Type'] ?? AstroCore::getConfig('panchang.defaults.calendar_type', 'amanta')));

        $allowedAmanta = array_map(
            fn ($month): string => $this->normalizeMonthName((string) $month),
            (array) ($rule['allowed_months_amanta'] ?? (isset($rule['month_amanta']) ? [$rule['month_amanta']] : []))
        );
        $allowedPurnimanta = array_map(
            fn ($month): string => $this->normalizeMonthName((string) $month),
            (array) ($rule['allowed_months_purnimanta'] ?? (isset($rule['month_purnimanta']) ? [$rule['month_purnimanta']] : []))
        );

        if ((string) ($rule['family'] ?? '') === 'phuldolotsava' && $allowedAmanta !== []) {
            return $amanta !== '' && in_array($amanta, $allowedAmanta, true);
        }

        if ($calendarType === 'purnimanta') {
            if ($allowedPurnimanta !== []) {
                return $purnimanta !== '' && in_array($purnimanta, $allowedPurnimanta, true);
            }

            if ($allowedAmanta !== []) {
                return $amanta !== '' && in_array($amanta, $allowedAmanta, true);
            }

            return true;
        }

        if ($allowedAmanta !== []) {
            return $amanta !== '' && in_array($amanta, $allowedAmanta, true);
        }

        if ($allowedPurnimanta !== []) {
            return $purnimanta !== '' && in_array($purnimanta, $allowedPurnimanta, true);
        }

        return true;
    }

    private function hasSunriseNakshatra(array $details, string $requiredNakshatra): bool
    {
        $requiredNumber = $this->resolveNakshatraNumber($requiredNakshatra);

        $sunriseNak = (array) ($details['Nakshatra_At_Sunrise'] ?? []);
        if ($sunriseNak !== []) {
            $sunriseNumber = $this->resolveSnapshotNakshatraNumber($sunriseNak);
            if ($requiredNumber !== null && $sunriseNumber !== null) {
                return $requiredNumber === $sunriseNumber;
            }

            $sunriseName = (string) ($sunriseNak['name'] ?? '');
            if ($sunriseName !== '') {
                return strcasecmp($requiredNakshatra, $sunriseName) === 0
                    || ($requiredNumber !== null && $this->resolveNakshatraNumber($sunriseName) === $requiredNumber);
            }
        }

        // Fall back to day nakshatra (often the sunrise nakshatra in festival snapshots).
        $dayNak = (array) ($details['Nakshatra'] ?? []);
        $dayNumber = $this->resolveSnapshotNakshatraNumber($dayNak);
        if ($requiredNumber !== null && $dayNumber !== null) {
            return $requiredNumber === $dayNumber;
        }

        $dayName = (string) ($dayNak['name'] ?? '');
        if ($dayName !== '') {
            return strcasecmp($requiredNakshatra, $dayName) === 0
                || ($requiredNumber !== null && $this->resolveNakshatraNumber($dayName) === $requiredNumber);
        }

        // Optional window-based evidence when present.
        $ctx = (array) ($details['Resolution_Context'] ?? []);
        $sunriseJd = (float) ($ctx['sunrise_jd'] ?? 0.0);
        if ($sunriseJd > 0.0) {
            foreach ((array) ($details['Nakshatra_Windows'] ?? []) as $interval) {
                if (!is_array($interval)) {
                    continue;
                }

                $name = (string) ($interval['name'] ?? $interval['nakshatra'] ?? '');
                $intervalNumber = $this->resolveNakshatraNumber($name);
                $matches = $requiredNumber !== null && $intervalNumber !== null
                    ? $requiredNumber === $intervalNumber
                    : strcasecmp($name, $requiredNakshatra) === 0;
                if (!$matches) {
                    continue;
                }

                $start = $this->extractJd($interval['start_jd'] ?? ($interval['start']['jd'] ?? null));
                $end = $this->extractJd($interval['end_jd'] ?? ($interval['end']['jd'] ?? null));
                if ($start !== null && $end !== null && $sunriseJd >= $start && $sunriseJd < $end) {
                    return true;
                }
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

    private function resolveMasikJanmashtamiTruthTable(array $candidates): ?array
    {
        $day1 = $candidates[0];
        $day2 = $candidates[1];

        if ($day1['target_at_karmakala'] && !$day2['target_at_karmakala']) {
            return $this->markSpecialWinner($day1, 'masik_janmashtami_nishitha_only_day1');
        }

        if (!$day1['target_at_karmakala'] && $day2['target_at_karmakala']) {
            return $this->markSpecialWinner($day2, 'masik_janmashtami_nishitha_only_day2');
        }

        if ($day1['target_at_karmakala']) {
            return $this->markSpecialWinner($day1, 'masik_janmashtami_both_nishitha_choose_day1');
        }

        if ($day1['target_during_observance']) {
            $winner = $this->markSpecialWinner($day1, 'masik_janmashtami_no_nishitha_fallback_day1');
            $winner['score'] = min((int) ($winner['score'] ?? 0), 19_000);

            return $winner;
        }

        if ($day2['target_during_observance']) {
            $winner = $this->markSpecialWinner($day2, 'masik_janmashtami_no_nishitha_fallback_day2');
            $winner['score'] = min((int) ($winner['score'] ?? 0), 19_000);

            return $winner;
        }

        return null;
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
            return $this->markSpecialWinner($day2, 'mahashivaratri_both_full_nishitha_choose_day2_per_ref');
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

        // ekadesha/both main kaal -> day2
        if (($day1['target_window_coverage_ratio'] ?? 0) > 0 && ($day2['target_window_coverage_ratio'] ?? 0) > 0) {
            return $this->markSpecialWinner($day2, 'mahashivaratri_ekadesha_or_both_choose_day2_per_ref');
        }

        return null;
    }

    private function resolveRakshaBandhanTruthTable(array $candidates, array $targetInterval): ?array
    {
        $day1 = $candidates[0];
        $day2 = $candidates[1];
        $nextSunriseJd = (float) ($day2['sunrise_jd'] ?? 0.0);
        $thresholdSeconds = FestivalRuleConstants::RAKSHA_BANDHAN_UDAYA_PURNIMA_THRESHOLD_MUHURTAS * (float) ($day2['day_muhurta_seconds'] ?? 0.0);
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
            'minimum_post_sunrise_purnima_muhurtas' => FestivalRuleConstants::RAKSHA_BANDHAN_UDAYA_PURNIMA_THRESHOLD_MUHURTAS,
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

    /**
     * Govardhan Puja / Annakut / Gokrida / Bali Puja (Satsangi Jeevan 4.58).
     *
     * Kartika Shukla Pratipada for these festivals must:
     *  - be sayahna-vyapini (still present at local sunset), and
     *  - not invite Sthula Chandra Darshana risk (Pratipada lasts ≥ 9 muhurtas after sunrise).
     *
     * If the pure udaya Pratipada day fails either test (e.g. Pratipada ends before sunset so
     * evening is Dwitiya / CD night), use the previous Amavasya-viddha day — matching Bhuj
     * Mandir Nirnay (Bestu Varas stays on sunrise Pratipada separately).
     */
    private function resolveGovardhanAnnakutTruthTable(array $candidates, array $targetInterval): ?array
    {
        $day1 = $candidates[0] ?? null;
        $day2 = $candidates[1] ?? null;

        if (!is_array($day1) || !is_array($day2)) {
            return null;
        }

        // Case 1: Pratipada at day2 sunrise → day2 is udaya-vyapini Pratipada;
        // day1 is the Amavasya-viddha civil day that may hold sayahna Pratipada.
        if ($this->isTargetAtPoint((float) ($day2['sunrise_jd'] ?? 0.0), $targetInterval)) {
            $safe = $this->isGovardhanSafeUdayaPratipadaDay($day2, $targetInterval);
            if ($safe['safe']) {
                return (bool) ($day2['target_during_observance'] ?? false)
                    ? $this->markSpecialWinner($day2, $safe['reason'])
                    : null;
            }

            return (bool) ($day1['target_during_observance'] ?? false)
                ? $this->markSpecialWinner($day1, $safe['reason'])
                : null;
        }

        // Case 2: Pratipada only at day1 sunrise (no day2 pair).
        if ((bool) ($day1['target_at_sunrise'] ?? false)) {
            $safe = $this->isGovardhanSafeUdayaPratipadaDay($day1, $targetInterval);
            if ($safe['safe'] && (bool) ($day1['target_during_observance'] ?? false)) {
                return $this->markSpecialWinner($day1, $safe['reason']);
            }

            return null;
        }

        // Case 3: Kshaya Pratipada — short tithi; keep Amavasya-viddha day1.
        if ((bool) ($day1['target_during_observance'] ?? false)) {
            return $this->markSpecialWinner($day1, 'govardhan_kshaya_pratipada_sthula_chandra_darshana_amavasya_viddha_day');
        }

        return null;
    }

    /**
     * @param array<string, mixed> $day
     * @param array{start_jd?: float|int, end_jd?: float|int} $targetInterval
     *
     * @return array{safe: bool, reason: string}
     */
    private function isGovardhanSafeUdayaPratipadaDay(array $day, array $targetInterval): array
    {
        $sunriseJd = (float) ($day['sunrise_jd'] ?? 0.0);
        $sunsetJd = (float) ($day['sunset_jd'] ?? 0.0);
        $targetStartJd = (float) ($targetInterval['start_jd'] ?? 0.0);
        $targetEndJd = (float) ($targetInterval['end_jd'] ?? 0.0);
        $dayMuhurtaSeconds = (float) ($day['day_muhurta_seconds'] ?? 0.0);
        $thresholdSeconds = FestivalRuleConstants::GOVARDHAN_STHULA_CHANDRA_DARSHANA_MUHURTAS * $dayMuhurtaSeconds;
        $postSunriseSeconds = max(0.0, ($targetEndJd - $sunriseJd) * 86400.0);

        // SJ 4.58: reject if evening is already Dwitiya-linked (Pratipada ends before sunset).
        // Sayahna-vyapini = Pratipada still running at local sunset.
        $sayahnaVyapini = $sunsetJd > 0.0
            && $targetStartJd < $sunsetJd
            && $targetEndJd > $sunsetJd;

        if (!$sayahnaVyapini) {
            return [
                'safe' => false,
                'reason' => 'govardhan_pratipada_not_sayahna_or_dwitiya_evening_use_amavasya_viddha_prev_day',
            ];
        }

        // SJ sthula Chandra Darshana: if Pratipada does not last 9 muhurtas past sunrise,
        // assume CD risk and use previous (Amavasya-viddha) day.
        if ($thresholdSeconds <= 0.0 || $postSunriseSeconds < $thresholdSeconds) {
            return [
                'safe' => false,
                'reason' => 'govardhan_below_9_muhurta_sthula_chandra_darshana_amavasya_viddha_prev_day',
            ];
        }

        return [
            'safe' => true,
            'reason' => 'govardhan_pratipada_9_muhurta_sayahna_no_sthula_chandra_darshana_same_day',
        ];
    }

    /**
     * Convert a classical daytime-ghadi threshold into a JD span using the candidate's local
     * dinamana. Falls back to sunrise/sunset span when precomputed day-muhurta data is absent.
     *
     * @param array<string, mixed> $candidate
     */
    private function dynamicDayGhatiThresholdJd(array $candidate, float $ghatis): float
    {
        $dayMuhurtaSeconds = (float) ($candidate['day_muhurta_seconds'] ?? 0.0);
        if ($dayMuhurtaSeconds > 0.0) {
            $dayGhatiSeconds = $dayMuhurtaSeconds / 2.0;

            return ($ghatis * $dayGhatiSeconds) / 86400.0;
        }

        $sunriseJd = (float) ($candidate['sunrise_jd'] ?? 0.0);
        $sunsetJd = (float) ($candidate['sunset_jd'] ?? $sunriseJd);
        $dayDurationJd = max(0.0, $sunsetJd - $sunriseJd);

        return $ghatis * ($dayDurationJd / 30.0);
    }

    /**
     * Nag Panchami (Shravana Krishna Panchami) paraviddha selection.:
     *  - take the Panchami pierced by the Shashthi that spans >= 6 ghadis past sunrise;
     *  - if the second day's Panchami is under 6 ghadis and the first day is only
     *    Chaturthi-viddha with the Chaturthi under 6 ghadis, keep the first day;
     *  - if the first day's Chaturthi vedha exceeds 6 ghadis, shift to the second day even
     *    when its Panchami is as little as 4 ghadis.
     * Truth table (lines 724-727): vriddhi keeps Vad 5 (first); kshaya keeps Vad 4-5 (first).
     * The 6-ghadi thresholds are scaled from the local daytime length.
     */
    private function resolveNagPanchamiTruthTable(array $candidates, array $targetInterval): ?array
    {
        $day1 = $candidates[0] ?? null;
        $day2 = $candidates[1] ?? null;
        if (!is_array($day1) || !is_array($day2)) {
            return null;
        }

        $startJd = (float) ($targetInterval['start_jd'] ?? 0.0);
        $endJd = (float) ($targetInterval['end_jd'] ?? 0.0);
        $day1Sunrise = (float) ($day1['sunrise_jd'] ?? 0.0);
        $day2Sunrise = (float) ($day2['sunrise_jd'] ?? 0.0);
        $day1SixGhadiJd = $this->dynamicDayGhatiThresholdJd($day1, FestivalRuleConstants::NAG_PANCHAMI_SHASHTHI_VEDHA_GHADI);
        $day2SixGhadiJd = $this->dynamicDayGhatiThresholdJd($day2, FestivalRuleConstants::NAG_PANCHAMI_SHASHTHI_VEDHA_GHADI);
        $day1AtSunrise = (bool) ($day1['target_at_sunrise'] ?? false);
        $day2AtSunrise = (bool) ($day2['target_at_sunrise'] ?? false);

        // Vriddhi: Panchami spans both sunrises -> keep the first day (Vad 5).
        if ($day1AtSunrise && $day2AtSunrise) {
            return (bool) ($day1['target_during_observance'] ?? false)
                ? $this->markSpecialWinner($day1, 'nag_panchami_vriddhi_first_day')
                : null;
        }

        // Main pairing: Panchami is udaya-vyapini on day2; day1 is the Chaturthi-viddha day.
        if ($day2AtSunrise) {
            $panchamiSpanDay2 = max(0.0, $endJd - $day2Sunrise);
            if ($panchamiSpanDay2 >= $day2SixGhadiJd) {
                return (bool) ($day2['target_during_observance'] ?? false)
                    ? $this->markSpecialWinner($day2, 'nag_panchami_shashthi_viddha_6_ghadi_day2')
                    : null;
            }

            // Day2 Panchami is under 6 ghadis: weigh the first day's Chaturthi vedha.
            $chaturthiSpanDay1 = max(0.0, $startJd - $day1Sunrise);
            if ($chaturthiSpanDay1 <= $day1SixGhadiJd) {
                if ((bool) ($day1['target_during_observance'] ?? false)) {
                    return $this->markSpecialWinner($day1, 'nag_panchami_chaturthi_vedha_under_6_ghadi_day1');
                }

                return (bool) ($day2['target_during_observance'] ?? false)
                    ? $this->markSpecialWinner($day2, 'nag_panchami_short_pair_fallback_day2')
                    : null;
            }

            return (bool) ($day2['target_during_observance'] ?? false)
                ? $this->markSpecialWinner($day2, 'nag_panchami_chaturthi_vedha_over_6_ghadi_day2')
                : null;
        }

        // Panchami at day1 sunrise only (day2 is Shashthi): accept day1 when it spans >= 6
        // ghadis, otherwise defer so the previous-day pairing (with this day as "day2") decides.
        if ($day1AtSunrise) {
            $panchamiSpanDay1 = max(0.0, $endJd - $day1Sunrise);
            if ($panchamiSpanDay1 >= $day1SixGhadiJd && (bool) ($day1['target_during_observance'] ?? false)) {
                return $this->markSpecialWinner($day1, 'nag_panchami_shashthi_viddha_6_ghadi_day1');
            }

            return null;
        }

        // Kshaya Panchami (neither sunrise): observe on the Chaturthi-viddha day1 (Vad 4-5).
        if ((bool) ($day1['target_during_observance'] ?? false)) {
            return $this->markSpecialWinner($day1, 'nag_panchami_kshaya_chaturthi_viddha_day1');
        }

        return null;
    }

    /**
     * Durgashtami / Bhavani Pragatya (Shukla Ashtami) paraviddha selection.:
     * take the navami-viddha Ashtami spanning at least 3 muhurtas past sunrise; if the Ashtami
     * is under 3 muhurtas it falls back to the Saptami-viddha previous day. Vriddhi keeps the
     * first day; kshaya keeps the Saptami-yuta Ashtami (first) day. Muhurtas are dinamana-based
     * (day length / 15), matching the Govardhan and Raksha Bandhan muhurta thresholds.
     */
    private function resolveDurgashtamiTruthTable(array $candidates, array $targetInterval): ?array
    {
        $day1 = $candidates[0] ?? null;
        $day2 = $candidates[1] ?? null;
        if (!is_array($day1) || !is_array($day2)) {
            return null;
        }

        $endJd = (float) ($targetInterval['end_jd'] ?? 0.0);
        $day1Sunrise = (float) ($day1['sunrise_jd'] ?? 0.0);
        $day2Sunrise = (float) ($day2['sunrise_jd'] ?? 0.0);
        $day1AtSunrise = (bool) ($day1['target_at_sunrise'] ?? false);
        $day2AtSunrise = (bool) ($day2['target_at_sunrise'] ?? false);

        // Vriddhi: Ashtami at both sunrises -> keep the first day.
        if ($day1AtSunrise && $day2AtSunrise) {
            return (bool) ($day1['target_during_observance'] ?? false)
                ? $this->markSpecialWinner($day1, 'durgashtami_vriddhi_first_day')
                : null;
        }

        // Main pairing: Ashtami udaya-vyapini on day2 (navami-viddha); day1 is Saptami-viddha.
        if ($day2AtSunrise) {
            $thresholdSeconds = FestivalRuleConstants::DURGASHTAMI_PARAVIDDHA_MUHURTAS * (float) ($day2['day_muhurta_seconds'] ?? 0.0);
            $ashtamiSpanDay2Seconds = max(0.0, ($endJd - $day2Sunrise) * 86400.0);
            if ($thresholdSeconds > 0.0 && $ashtamiSpanDay2Seconds >= $thresholdSeconds) {
                return (bool) ($day2['target_during_observance'] ?? false)
                    ? $this->markSpecialWinner($day2, 'durgashtami_navami_viddha_3_muhurta_day2')
                    : null;
            }

            // Ashtami under 3 muhurtas on day2 -> Saptami-viddha first day.
            if ((bool) ($day1['target_during_observance'] ?? false)) {
                return $this->markSpecialWinner($day1, 'durgashtami_below_3_muhurta_saptami_viddha_day1');
            }

            return (bool) ($day2['target_during_observance'] ?? false)
                ? $this->markSpecialWinner($day2, 'durgashtami_short_pair_fallback_day2')
                : null;
        }

        // Ashtami at day1 sunrise only: accept when it spans >= 3 muhurtas, else defer so the
        // previous-day pairing (with this day as "day2") decides.
        if ($day1AtSunrise) {
            $thresholdSeconds = FestivalRuleConstants::DURGASHTAMI_PARAVIDDHA_MUHURTAS * (float) ($day1['day_muhurta_seconds'] ?? 0.0);
            $ashtamiSpanDay1Seconds = max(0.0, ($endJd - $day1Sunrise) * 86400.0);
            if ($thresholdSeconds > 0.0 && $ashtamiSpanDay1Seconds >= $thresholdSeconds && (bool) ($day1['target_during_observance'] ?? false)) {
                return $this->markSpecialWinner($day1, 'durgashtami_navami_viddha_3_muhurta_day1');
            }

            return null;
        }

        // Kshaya Ashtami (neither sunrise): observe on the Saptami-yuta Ashtami day1.
        if ((bool) ($day1['target_during_observance'] ?? false)) {
            return $this->markSpecialWinner($day1, 'durgashtami_kshaya_saptami_yuta_day1');
        }

        return null;
    }

    /**
     * Akshaya Tritiya (Vaishakha Shukla Tritiya) purvahna selection.:
     *  - the Tritiya vyapini in the purvahna (forenoon) is taken;
     *  - when the Tritiya pervades the purvahna on both civil days, the observance shifts to the
     *    second day only if that day's Tritiya spans at least 3 muhurtas past sunrise, otherwise
     *    it stays on the first (purva/Dvitiya-yuta) day;
     *  - vriddhi (Tritiya at both sunrises) keeps the first day; kshaya keeps the first day.
     * Muhurtas are dinamana-based (day length / 15).
     */
    private function resolveAkshayaTritiyaTruthTable(array $candidates, array $targetInterval): ?array
    {
        $day1 = $candidates[0] ?? null;
        $day2 = $candidates[1] ?? null;
        if (!is_array($day1) || !is_array($day2)) {
            return null;
        }

        $endJd = (float) ($targetInterval['end_jd'] ?? 0.0);
        $day2Sunrise = (float) ($day2['sunrise_jd'] ?? 0.0);
        $day1AtSunrise = (bool) ($day1['target_at_sunrise'] ?? false);
        $day2AtSunrise = (bool) ($day2['target_at_sunrise'] ?? false);

        // Vriddhi: Tritiya spans both sunrises -> keep the first (purva) day.
        if ($day1AtSunrise && $day2AtSunrise) {
            return (bool) ($day1['target_during_observance'] ?? false)
                ? $this->markSpecialWinner($day1, 'akshaya_tritiya_vriddhi_first_day')
                : null;
        }

        // Main pairing: shuddha (udaya-vyapini) Tritiya on day2; day1 is the Dvitiya-viddha day.
        if ($day2AtSunrise) {
            $day1PurvahnaVyapti = (float) ($day1['target_window_overlap_seconds'] ?? 0.0) > 0.0;
            if ($day1PurvahnaVyapti) {
                // Tritiya pervades the purvahna on both days -> the 3-muhurta rule decides.
                $thresholdSeconds = FestivalRuleConstants::AKSHAYA_TRITIYA_PURVAHNA_MUHURTAS * (float) ($day2['day_muhurta_seconds'] ?? 0.0);
                $tritiyaSpanDay2Seconds = max(0.0, ($endJd - $day2Sunrise) * 86400.0);
                if ($thresholdSeconds > 0.0 && $tritiyaSpanDay2Seconds >= $thresholdSeconds) {
                    return (bool) ($day2['target_during_observance'] ?? false)
                        ? $this->markSpecialWinner($day2, 'akshaya_tritiya_both_purvahna_day2_3_muhurta')
                        : null;
                }

                return (bool) ($day1['target_during_observance'] ?? false)
                    ? $this->markSpecialWinner($day1, 'akshaya_tritiya_both_purvahna_below_3_muhurta_purva_day1')
                    : null;
            }

            // Only day2 is purvahna-vyapini -> observe day2.
            return (bool) ($day2['target_during_observance'] ?? false)
                ? $this->markSpecialWinner($day2, 'akshaya_tritiya_purvahna_vyapini_day2')
                : null;
        }

        // Tritiya at day1 sunrise only: this shuddha day was already evaluated as day2 of the
        // previous pairing; defer so a single observance date is emitted.
        if ($day1AtSunrise) {
            return null;
        }

        // Kshaya (neither sunrise): observe on the first (Dvitiya-yuta) day.
        return (bool) ($day1['target_during_observance'] ?? false)
            ? $this->markSpecialWinner($day1, 'akshaya_tritiya_kshaya_first_day')
            : null;
    }

    /**
     * Anant Chaturdashi (Bhadrapada Shukla Chaturdashi) paraviddha selection.:
     * take the Chaturdashi spanning at least 2 muhurtas past sunrise; if the Chaturdashi is
     * under 2 muhurtas the observance falls back to the previous day. Vriddhi keeps the first
     * day; kshaya keeps the (single) kshaya day. Purvahna is the primary kala and muhurtas are
     * dinamana-based (day length / 15).
     */
    private function resolveAnantChaturdashiTruthTable(array $candidates, array $targetInterval): ?array
    {
        $day1 = $candidates[0] ?? null;
        $day2 = $candidates[1] ?? null;
        if (!is_array($day1) || !is_array($day2)) {
            return null;
        }

        $endJd = (float) ($targetInterval['end_jd'] ?? 0.0);
        $day1Sunrise = (float) ($day1['sunrise_jd'] ?? 0.0);
        $day2Sunrise = (float) ($day2['sunrise_jd'] ?? 0.0);
        $day1AtSunrise = (bool) ($day1['target_at_sunrise'] ?? false);
        $day2AtSunrise = (bool) ($day2['target_at_sunrise'] ?? false);

        // Vriddhi: Chaturdashi at both sunrises -> keep the first day.
        if ($day1AtSunrise && $day2AtSunrise) {
            return (bool) ($day1['target_during_observance'] ?? false)
                ? $this->markSpecialWinner($day1, 'anant_chaturdashi_vriddhi_first_day')
                : null;
        }

        // Main pairing: Chaturdashi udaya-vyapini on day2; day1 is the previous day.
        if ($day2AtSunrise) {
            $thresholdSeconds = FestivalRuleConstants::ANANT_CHATURDASHI_PARAVIDDHA_MUHURTAS * (float) ($day2['day_muhurta_seconds'] ?? 0.0);
            $spanDay2Seconds = max(0.0, ($endJd - $day2Sunrise) * 86400.0);
            if ($thresholdSeconds > 0.0 && $spanDay2Seconds >= $thresholdSeconds) {
                return (bool) ($day2['target_during_observance'] ?? false)
                    ? $this->markSpecialWinner($day2, 'anant_chaturdashi_2_muhurta_day2')
                    : null;
            }

            // Chaturdashi under 2 muhurtas on day2 -> previous (first) day.
            if ((bool) ($day1['target_during_observance'] ?? false)) {
                return $this->markSpecialWinner($day1, 'anant_chaturdashi_below_2_muhurta_prev_day1');
            }

            return (bool) ($day2['target_during_observance'] ?? false)
                ? $this->markSpecialWinner($day2, 'anant_chaturdashi_short_pair_fallback_day2')
                : null;
        }

        // Chaturdashi at day1 sunrise only: accept when it spans >= 2 muhurtas, else defer so the
        // previous-day pairing (with this day as "day2") decides.
        if ($day1AtSunrise) {
            $thresholdSeconds = FestivalRuleConstants::ANANT_CHATURDASHI_PARAVIDDHA_MUHURTAS * (float) ($day1['day_muhurta_seconds'] ?? 0.0);
            $spanDay1Seconds = max(0.0, ($endJd - $day1Sunrise) * 86400.0);
            if ($thresholdSeconds > 0.0 && $spanDay1Seconds >= $thresholdSeconds && (bool) ($day1['target_during_observance'] ?? false)) {
                return $this->markSpecialWinner($day1, 'anant_chaturdashi_2_muhurta_day1');
            }

            return null;
        }

        // Kshaya (neither sunrise): observe on the first (kshaya) day.
        return (bool) ($day1['target_during_observance'] ?? false)
            ? $this->markSpecialWinner($day1, 'anant_chaturdashi_kshaya_day1')
            : null;
    }

    /**
     * Navaratri Pratipada start table (Chaitra and Ashvina): accept Pratipada when it lasts
     * at least one daytime muhurta after sunrise; if the sunrise span is below one muhurta
     * or the tithi is kshaya, fall back to the Amavasya-viddha previous day.
     */
    private function resolveNavratriPratipadaTruthTable(array $candidates, array $targetInterval): ?array
    {
        $day1 = $candidates[0] ?? null;
        $day2 = $candidates[1] ?? null;
        if (!is_array($day1) || !is_array($day2)) {
            return null;
        }

        $endJd = (float) ($targetInterval['end_jd'] ?? 0.0);
        $day1Sunrise = (float) ($day1['sunrise_jd'] ?? 0.0);
        $day2Sunrise = (float) ($day2['sunrise_jd'] ?? 0.0);
        $day1AtSunrise = (bool) ($day1['target_at_sunrise'] ?? false);
        $day2AtSunrise = (bool) ($day2['target_at_sunrise'] ?? false);

        if ($day1AtSunrise && $day2AtSunrise) {
            return (bool) ($day1['target_during_observance'] ?? false)
                ? $this->markSpecialWinner($day1, 'navratri_pratipada_vriddhi_first_day')
                : null;
        }

        if ($day2AtSunrise) {
            $thresholdSeconds = FestivalRuleConstants::NAVRATRI_PRATIPADA_MIN_MUHURTAS * (float) ($day2['day_muhurta_seconds'] ?? 0.0);
            $spanDay2Seconds = max(0.0, ($endJd - $day2Sunrise) * 86400.0);
            if ($thresholdSeconds > 0.0 && $spanDay2Seconds >= $thresholdSeconds) {
                return (bool) ($day2['target_during_observance'] ?? false)
                    ? $this->markSpecialWinner($day2, 'navratri_pratipada_one_muhurta_day2')
                    : null;
            }

            return (bool) ($day1['target_during_observance'] ?? false)
                ? $this->markSpecialWinner($day1, 'navratri_pratipada_below_one_muhurta_amavasya_viddha_day1')
                : null;
        }

        if ($day1AtSunrise) {
            $thresholdSeconds = FestivalRuleConstants::NAVRATRI_PRATIPADA_MIN_MUHURTAS * (float) ($day1['day_muhurta_seconds'] ?? 0.0);
            $spanDay1Seconds = max(0.0, ($endJd - $day1Sunrise) * 86400.0);
            if ($thresholdSeconds > 0.0 && $spanDay1Seconds >= $thresholdSeconds && (bool) ($day1['target_during_observance'] ?? false)) {
                return $this->markSpecialWinner($day1, 'navratri_pratipada_one_muhurta_day1');
            }

            return null;
        }

        return (bool) ($day1['target_during_observance'] ?? false)
            ? $this->markSpecialWinner($day1, 'navratri_pratipada_kshaya_amavasya_viddha_day1')
            : null;
    }

    /** Durva Ashtami: purvaviddha, sunset-side three-muhurta rule. */
    private function resolveDurvaAshtamiTruthTable(array $candidates, array $targetInterval): ?array
    {
        $day1 = $candidates[0] ?? null;
        $day2 = $candidates[1] ?? null;
        if (!is_array($day1) || !is_array($day2)) {
            return null;
        }

        $startJd = (float) ($targetInterval['start_jd'] ?? 0.0);
        $day1Sunset = (float) ($day1['sunset_jd'] ?? 0.0);
        $day1AtSunrise = (bool) ($day1['target_at_sunrise'] ?? false);
        $day2AtSunrise = (bool) ($day2['target_at_sunrise'] ?? false);

        if ($day1AtSunrise && $day2AtSunrise) {
            return (bool) ($day1['target_during_observance'] ?? false)
                ? $this->markSpecialWinner($day1, 'durva_ashtami_vriddhi_first_day')
                : null;
        }

        if ($day2AtSunrise) {
            $thresholdSeconds = FestivalRuleConstants::DURVA_ASHTAMI_PURVAVIDDHA_MUHURTAS * (float) ($day1['day_muhurta_seconds'] ?? 0.0);
            $leadBeforeSunsetSeconds = max(0.0, ($day1Sunset - $startJd) * 86400.0);
            if ($thresholdSeconds > 0.0 && $leadBeforeSunsetSeconds >= $thresholdSeconds && (bool) ($day1['target_during_observance'] ?? false)) {
                return $this->markSpecialWinner($day1, 'durva_ashtami_purvaviddha_three_muhurta_before_sunset_day1');
            }

            return (bool) ($day2['target_during_observance'] ?? false)
                ? $this->markSpecialWinner($day2, 'durva_ashtami_day2_when_no_three_muhurta_purvaviddha')
                : null;
        }

        if ($day1AtSunrise && (bool) ($day1['target_during_observance'] ?? false)) {
            return $this->markSpecialWinner($day1, 'durva_ashtami_first_sunrise_day');
        }

        return (bool) ($day1['target_during_observance'] ?? false)
            ? $this->markSpecialWinner($day1, 'durva_ashtami_kshaya_first_day')
            : null;
    }

    /**
     * First-fallback karmakala table used by Lalita Panchami and Akshaya Navami: if only one
     * day has the main-kala overlap choose it; if both have overlap, unequal overlap, equal
     * overlap, or neither overlap, keep the first day as prescribed.
     */
    private function resolveFirstFallbackKarmakalaTruthTable(array $candidates, string $reasonPrefix): ?array
    {
        $day1 = $candidates[0] ?? null;
        $day2 = $candidates[1] ?? null;
        if (!is_array($day1) || !is_array($day2)) {
            return null;
        }

        $day1Overlap = (float) ($day1['target_window_overlap_seconds'] ?? 0.0);
        $day2Overlap = (float) ($day2['target_window_overlap_seconds'] ?? 0.0);

        if ($day1Overlap > 0.0 && $day2Overlap <= 0.0 && (bool) ($day1['target_during_observance'] ?? false)) {
            return $this->markSpecialWinner($day1, $reasonPrefix . '_only_first_karmakala');
        }

        if ($day2Overlap > 0.0 && $day1Overlap <= 0.0 && (bool) ($day2['target_during_observance'] ?? false)) {
            return $this->markSpecialWinner($day2, $reasonPrefix . '_only_second_karmakala');
        }

        return (bool) ($day1['target_during_observance'] ?? false)
            ? $this->markSpecialWinner($day1, $reasonPrefix . '_both_or_neither_keep_first')
            : null;
    }

    /**
     * Naraka Chaturdashi Abhyanga Snan primarily follows chandrodaya-vyapini Chaturdashi.
     * When moonrise does not settle the choice, keep the sunrise/ushah-side fallback before
     * falling back to a broad observance-window tie.
     */
    private function resolveNarakaChaturdashiAbhyangaTruthTable(array $candidates): ?array
    {
        $day1 = $candidates[0] ?? null;
        $day2 = $candidates[1] ?? null;
        if (!is_array($day1) || !is_array($day2)) {
            return null;
        }

        $day1AtMoonrise = (bool) ($day1['target_at_karmakala'] ?? false);
        $day2AtMoonrise = (bool) ($day2['target_at_karmakala'] ?? false);

        if ($day1AtMoonrise && !$day2AtMoonrise) {
            return $this->markSpecialWinner($day1, 'naraka_chaturdashi_abhyanga_chandrodaya_day1');
        }

        if (!$day1AtMoonrise && $day2AtMoonrise) {
            return $this->markSpecialWinner($day2, 'naraka_chaturdashi_abhyanga_chandrodaya_day2');
        }

        if ($day1AtMoonrise) {
            return $this->markSpecialWinner($day1, 'naraka_chaturdashi_abhyanga_both_chandrodaya_keep_first');
        }

        if ((bool) ($day1['target_at_sunrise'] ?? false)) {
            return $this->markSpecialWinner($day1, 'naraka_chaturdashi_abhyanga_ushah_sunrise_fallback_day1');
        }

        if ((bool) ($day2['target_at_sunrise'] ?? false)) {
            return $this->markSpecialWinner($day2, 'naraka_chaturdashi_abhyanga_ushah_sunrise_fallback_day2');
        }

        if ((bool) ($day1['target_during_observance'] ?? false)) {
            return $this->markSpecialWinner($day1, 'naraka_chaturdashi_abhyanga_observance_fallback_day1');
        }

        return (bool) ($day2['target_during_observance'] ?? false)
            ? $this->markSpecialWinner($day2, 'naraka_chaturdashi_abhyanga_observance_fallback_day2')
            : null;
    }

    /** Holashtak-style observances are tied to the actual tithi boundary, not sunrise vyapti. */
    private function resolveTithiBoundaryTruthTable(CarbonImmutable $date, array $rule, array $candidates, array $targetInterval): ?array
    {
        $boundaryRule = (string) ($rule['tithi_boundary_rule'] ?? '');
        $boundaryJd = match ($boundaryRule) {
            'start' => $targetInterval['start_jd'],
            'end' => $targetInterval['end_jd'],
            default => null,
        };

        if (!is_float($boundaryJd)) {
            return null;
        }

        $dayStartJd = AstroCore::toJulianDay($date->startOfDay());
        $dayEndJd = AstroCore::toJulianDay($date->addDay()->startOfDay());
        if ($boundaryJd >= $dayStartJd && $boundaryJd < $dayEndJd && isset($candidates[0])) {
            return $this->markSpecialWinner($candidates[0], 'tithi_boundary_' . $boundaryRule . '_same_civil_day');
        }

        $tomorrowEndJd = AstroCore::toJulianDay($date->addDays(2)->startOfDay());
        if ($boundaryJd >= $dayEndJd && $boundaryJd < $tomorrowEndJd && isset($candidates[1])) {
            return $this->markSpecialWinner($candidates[1], 'tithi_boundary_' . $boundaryRule . '_next_civil_day');
        }

        return null;
    }

    /** Gauri Tritiya is parayuta: prefer the Chaturthi-yukta second occurrence when present. */
    private function resolveGauriTritiyaTruthTable(array $candidates): ?array
    {
        $day1 = $candidates[0] ?? null;
        $day2 = $candidates[1] ?? null;
        if (!is_array($day1)) {
            return null;
        }

        if (is_array($day2) && (bool) ($day2['target_during_observance'] ?? false) && !(bool) ($day2['rule_rejected'] ?? false)) {
            return $this->markSpecialWinner($day2, 'gauri_tritiya_parayuta_chaturthi_yukta_day2');
        }

        if ((bool) ($day1['target_during_observance'] ?? false) && !(bool) ($day1['rule_rejected'] ?? false)) {
            return $this->markSpecialWinner($day1, 'gauri_tritiya_kshaya_dvitiya_yukta_day1');
        }

        return null;
    }

    /** Darsha Amavasya aparahna table from the Nirnay reference. */
    private function resolveDarshaAmavasyaTruthTable(array $candidates): ?array
    {
        $day1 = $candidates[0] ?? null;
        $day2 = $candidates[1] ?? null;
        if (!is_array($day1) || !is_array($day2)) {
            return null;
        }

        $day1AtSunrise = (bool) ($day1['target_at_sunrise'] ?? false);
        $day2AtSunrise = (bool) ($day2['target_at_sunrise'] ?? false);

        // Prefer prior day when Amavasya covers that day's aparahna and next sunrise is Amavasya
        // (Bhuj 2026: Jun 14→15, Sep 10→11, Nov 8→9). Night-only prior-day starts keep both on
        // the sunrise Amavasya day (Apr 17, Jul 14).
        $day1Overlap = (float) ($day1['target_window_overlap_seconds'] ?? 0.0);
        $day2Overlap = (float) ($day2['target_window_overlap_seconds'] ?? 0.0);
        if ($day2AtSunrise && !$day1AtSunrise && $day1Overlap > 0.0
            && (bool) ($day1['target_during_observance'] ?? false)) {
            return $this->markSpecialWinner($day1, 'darsha_amavasya_day_before_sunrise_aparahna');
        }

        if ($day1AtSunrise && $day2AtSunrise && (bool) ($day1['target_during_observance'] ?? false)) {
            return $this->markSpecialWinner($day1, 'darsha_amavasya_vriddhi_first_day');
        }

        if ($day1Overlap > 0.0 && $day2Overlap > 0.0) {
            if (abs($day1Overlap - $day2Overlap) < 1.0) {
                return (bool) ($day2['target_during_observance'] ?? false)
                    ? $this->markSpecialWinner($day2, 'darsha_amavasya_equal_aparahna_second_day')
                    : null;
            }

            $winner = $day1Overlap > $day2Overlap ? $day1 : $day2;
            return (bool) ($winner['target_during_observance'] ?? false)
                ? $this->markSpecialWinner($winner, 'darsha_amavasya_longer_aparahna_overlap')
                : null;
        }

        if ($day1Overlap > 0.0 && (bool) ($day1['target_during_observance'] ?? false)) {
            return $this->markSpecialWinner($day1, 'darsha_amavasya_only_first_aparahna');
        }

        if ($day2Overlap > 0.0 && (bool) ($day2['target_during_observance'] ?? false)) {
            return $this->markSpecialWinner($day2, 'darsha_amavasya_only_second_aparahna');
        }

        if (!$day1AtSunrise && !$day2AtSunrise && (bool) ($day1['target_during_observance'] ?? false)) {
            return $this->markSpecialWinner($day1, 'darsha_amavasya_kshaya_first_day');
        }

        return (bool) ($day2['target_during_observance'] ?? false)
            ? $this->markSpecialWinner($day2, 'darsha_amavasya_no_aparahna_second_day')
            : null;
    }

    private function resolveEkadashiNirnayTruthTable(array $candidates, array $targetInterval): ?array
    {
        $day1 = $candidates[0] ?? null;
        $day2 = $candidates[1] ?? null;
        if (!is_array($day1) || !is_array($day2)) {
            return null;
        }

        $kalaEngine = new KalaNirnayaEngine(0.0, 0.0);
        $ekadashiDecision = $kalaEngine->determineEkadashi(
            (float) ($targetInterval['start_jd'] ?? 0.0),
            (float) ($targetInterval['end_jd'] ?? 0.0),
            (float) ($targetInterval['start_jd'] ?? 0.0),
            (float) ($targetInterval['end_jd'] ?? 0.0),
            (float) ($day1['sunrise_jd'] ?? 0.0),
            (float) ($day2['sunrise_jd'] ?? ($day1['next_sunrise_jd'] ?? 0.0)),
            'Vaishnava',
            (float) ($day1['previous_sunrise_jd'] ?? 0.0)
        );

        $fastingDay = (string) ($ekadashiDecision['fasting_day'] ?? '');
        $caseKey = (string) ($ekadashiDecision['case_key'] ?? 'ekadashi_nirnay');
        if (str_starts_with($fastingDay, 'Tomorrow')) {
            $winner = $this->markSpecialWinner($day2, 'ekadashi_' . $caseKey);
            $winner['ekadashi_selection'] = $ekadashiDecision;

            return $winner;
        }

        if ($fastingDay === 'Today' && (bool) ($day1['target_during_observance'] ?? false)) {
            $winner = $this->markSpecialWinner($day1, 'ekadashi_' . $caseKey);
            $winner['ekadashi_selection'] = $ekadashiDecision;

            return $winner;
        }

        return null;
    }

    private function resolveTithiEntryBeforeMadhyahna(array $candidates): ?array
    {
        $day1 = $candidates[0] ?? null;
        if (!is_array($day1)) {
            return null;
        }

        $start = (float) ($day1['target_interval_start_jd'] ?? 0.0);
        $sunrise = (float) ($day1['sunrise_jd'] ?? 0.0);
        $sunset = (float) ($day1['sunset_jd'] ?? 0.0);
        if ($start <= 0.0 || $sunrise <= 0.0 || $sunset <= $sunrise) {
            return null;
        }

        $madhyahna = $sunrise + (($sunset - $sunrise) / 2.0);
        if ($start > $sunrise + 1e-9 && $start < $madhyahna - 1e-9 && (bool) ($day1['target_during_observance'] ?? false)) {
            return $this->markSpecialWinner($day1, 'target_tithi_entry_before_madhyahna');
        }

        return null;
    }

    private function resolvePradoshTruthTable(array $candidates): ?array
    {
        $day1 = $candidates[0] ?? null;
        $day2 = $candidates[1] ?? null;
        if (!is_array($day1) || !is_array($day2)) {
            return null;
        }

        $day1Overlap = (float) ($day1['target_window_overlap_seconds'] ?? 0.0);
        $day2Overlap = (float) ($day2['target_window_overlap_seconds'] ?? 0.0);
        $day1Coverage = (float) ($day1['target_window_coverage_ratio'] ?? 0.0);

        if ($day1Overlap > 0.0 && $day2Overlap > 0.0) {
            if ($day1Coverage >= 0.999 && (bool) ($day1['target_during_observance'] ?? false)) {
                return $this->markSpecialWinner($day1, 'pradosh_first_day_full_pradosha_coverage');
            }

            return (bool) ($day2['target_during_observance'] ?? false)
                ? $this->markSpecialWinner($day2, 'pradosh_both_partial_choose_day2')
                : null;
        }

        if ($day1Overlap > 0.0 && (bool) ($day1['target_during_observance'] ?? false)) {
            return $this->markSpecialWinner($day1, 'pradosh_only_day1_pradosha_vyapini');
        }

        if ($day2Overlap > 0.0 && (bool) ($day2['target_during_observance'] ?? false)) {
            return $this->markSpecialWinner($day2, 'pradosh_only_day2_pradosha_vyapini');
        }

        return null;
    }

    private function resolveSankashtiTruthTable(array $candidates): ?array
    {
        $day1 = $candidates[0] ?? null;
        $day2 = $candidates[1] ?? null;
        if (!is_array($day1) || !is_array($day2)) {
            return null;
        }

        $day1Moonrise = (bool) ($day1['target_at_karmakala'] ?? false);
        $day2Moonrise = (bool) ($day2['target_at_karmakala'] ?? false);
        if ($day1Moonrise && $day2Moonrise && (bool) ($day1['target_during_observance'] ?? false)) {
            return $this->markSankashtiWinner($day1, 'sankashti_both_moonrise_vyapini_tritiya_yuta_day1');
        }

        if ($day1Moonrise && (bool) ($day1['target_during_observance'] ?? false)) {
            return $this->markSankashtiWinner($day1, 'sankashti_only_day1_moonrise_vyapini');
        }

        if ($day2Moonrise && (bool) ($day2['target_during_observance'] ?? false)) {
            return $this->markSankashtiWinner($day2, 'sankashti_only_day2_moonrise_vyapini');
        }

        if (!(bool) ($day1['target_during_observance'] ?? false) && (bool) ($day2['target_during_observance'] ?? false)) {
            return $this->markSankashtiWinner($day2, 'sankashti_neither_moonrise_vyapini_choose_day2');
        }

        if (!$day1Moonrise && !$day2Moonrise && (bool) ($day1['target_at_sunrise'] ?? false)) {
            return $this->markSankashtiWinner($day1, 'sankashti_no_moonrise_vyapini_sunrise_chaturthi_day1');
        }

        if (!$day1Moonrise && !$day2Moonrise && (bool) ($day2['target_at_sunrise'] ?? false)) {
            return $this->markSankashtiWinner($day2, 'sankashti_no_moonrise_vyapini_sunrise_chaturthi_day2');
        }

        return null;
    }

    private function markSankashtiWinner(array $candidate, string $reason): array
    {
        $winner = $this->markSpecialWinner($candidate, $reason);
        $isAngarak = CarbonImmutable::parse((string) $winner['date'])->dayOfWeek === CarbonImmutable::TUESDAY;
        $winner['sankashti_selection'] = [
            'is_angarak_sankashti' => $isAngarak,
            'special_name' => $isAngarak ? 'Angarak Sankashti Chaturthi' : null,
        ];

        return $winner;
    }

    private function resolveNarasimhaJayantiTruthTable(array $candidates): ?array
    {
        $day1 = $candidates[0] ?? null;
        $day2 = $candidates[1] ?? null;
        if (!is_array($day1) || !is_array($day2)) {
            return null;
        }

        $day1Vyapti = ((float) ($day1['target_window_overlap_seconds'] ?? 0.0) > 0.0)
            || (bool) ($day1['target_at_karmakala'] ?? false);
        $day2Vyapti = ((float) ($day2['target_window_overlap_seconds'] ?? 0.0) > 0.0)
            || (bool) ($day2['target_at_karmakala'] ?? false);
        $day1Sunrise = (bool) ($day1['target_at_sunrise'] ?? false);
        $day2Sunrise = (bool) ($day2['target_at_sunrise'] ?? false);
        $isKshaya = !$day1Sunrise && !$day2Sunrise;

        if ($isKshaya) {
            if ($day1Vyapti) {
                return $this->markSpecialWinner($day1, 'narasimha_kshaya_accept_pradosha_day1');
            }

            if ($day2Vyapti) {
                return $this->markSpecialWinner($day2, 'narasimha_kshaya_accept_pradosha_day2');
            }

            return $this->markSpecialWinner($day1, 'narasimha_kshaya_fallback_day1');
        }

        // Prefer the sunrise-vyapini Chaturdashi day when it also carries pradosha.
        if ($day2Vyapti && $day2Sunrise) {
            return $this->markSpecialWinner($day2, 'narasimha_sunrise_chaturdashi_day2');
        }

        if ($day1Vyapti && $day1Sunrise) {
            return $this->markSpecialWinner($day1, 'narasimha_sunrise_chaturdashi_day1');
        }

        if ($day1Vyapti && !$day2Vyapti) {
            return $this->markSpecialWinner($day1, 'narasimha_pradosha_only_day1');
        }

        if ($day2Vyapti && !$day1Vyapti) {
            return $this->markSpecialWinner($day2, 'narasimha_pradosha_only_day2');
        }

        if ($day2Sunrise) {
            return $this->markSpecialWinner($day2, 'narasimha_sunrise_fallback_day2');
        }

        if ($day1Sunrise) {
            return $this->markSpecialWinner($day1, 'narasimha_sunrise_fallback_day1');
        }

        return $this->markSpecialWinner($day2, 'narasimha_fallback_day2');
    }

    private function resolveVinayakiChaturthiTruthTable(array $candidates): ?array
    {
        $day1 = $candidates[0] ?? null;
        $day2 = $candidates[1] ?? null;
        if (!is_array($day1) || !is_array($day2)) {
            return null;
        }

        $day1Full = (float) ($day1['target_window_coverage_ratio'] ?? 0.0) >= 0.999;
        $day2Full = (float) ($day2['target_window_coverage_ratio'] ?? 0.0) >= 0.999;
        if ($day1Full && (bool) ($day1['target_during_observance'] ?? false)) {
            return $this->markSpecialWinner($day1, 'vinayaki_full_madhyahna_day1');
        }

        if ($day2Full && (bool) ($day2['target_during_observance'] ?? false)) {
            return $this->markSpecialWinner($day2, 'vinayaki_full_madhyahna_day2');
        }

        $day1Overlap = (float) ($day1['target_window_overlap_seconds'] ?? 0.0);
        $day2Overlap = (float) ($day2['target_window_overlap_seconds'] ?? 0.0);
        if ($day1Overlap > 0.0 && $day2Overlap > 0.0) {
            $winner = $day1Overlap >= $day2Overlap ? $day1 : $day2;

            return $this->markSpecialWinner($winner, $winner === $day1 ? 'vinayaki_longer_or_equal_madhyahna_day1' : 'vinayaki_longer_madhyahna_day2');
        }

        if ($day1Overlap > 0.0 && (bool) ($day1['target_during_observance'] ?? false)) {
            return $this->markSpecialWinner($day1, 'vinayaki_only_day1_madhyahna');
        }

        if ($day2Overlap > 0.0 && (bool) ($day2['target_during_observance'] ?? false)) {
            return $this->markSpecialWinner($day2, 'vinayaki_only_day2_madhyahna');
        }

        // Neither madhyahna (Chaturthi skips both middays) — prefer sunrise-vyapini day.
        if ((bool) ($day2['target_at_sunrise'] ?? false)) {
            return $this->markSpecialWinner($day2, 'vinayaki_neither_madhyahna_sunrise_day2');
        }

        if ((bool) ($day1['target_at_sunrise'] ?? false)) {
            return $this->markSpecialWinner($day1, 'vinayaki_neither_madhyahna_sunrise_day1');
        }

        return (bool) ($day1['target_during_observance'] ?? false)
            ? $this->markSpecialWinner($day1, 'vinayaki_fallback_day1')
            : ((bool) ($day2['target_during_observance'] ?? false)
                ? $this->markSpecialWinner($day2, 'vinayaki_fallback_day2')
                : null);
    }

    /**
     * Generic madhyahna previous-tithi (vedha) rejection table used by festivals such as
     * Radhashtami (Bhadrapada Shukla Ashtami, saptami-vedha) and the
     * Swaminarayan Varaha Jayanti (Shravana Shukla Chaturthi, tritiya-vedha):
     *  - the previous-tithi-viddha day is always rejected (the vedha "occurs even from a single
     *    pala"), so the shuddha day pervading the madhyahna wins;
     *  - when both days are shuddha and madhyahna-vyapini (vriddhi), preference follows
     *    `$vriddhiPreference` (`first` keeps purva/day1; `last` keeps para/day2 — SJ 4.61 Varaha);
     *  - when the shuddha tithi does not reach the madhyahna on either day the later/panchami-yuta
     *    (shuddha) sunrise day is preferred for `last`, otherwise first is tried first;
     *  - kshaya (only the previous-tithi-viddha purva day carries the tithi) accepts that viddha day.
     */
    private function resolveMadhyahnaPurvaTithiVedhaTable(
        array $candidates,
        string $vriddhiPreference = 'first',
        bool $bothDaysPreferNavamiYuta = false,
    ): ?array {
        $day1 = $candidates[0] ?? null;
        $day2 = $candidates[1] ?? null;
        if (!is_array($day1) || !is_array($day2)) {
            return null;
        }

        $day1Vyapti = (float) ($day1['target_window_overlap_seconds'] ?? 0.0) > 0.0;
        $day2Vyapti = (float) ($day2['target_window_overlap_seconds'] ?? 0.0) > 0.0;
        $day1Viddha = (bool) ($day1['prev_tithi_at_sunrise'] ?? false);
        $day2Viddha = (bool) ($day2['prev_tithi_at_sunrise'] ?? false);
        $day1AtSunrise = (bool) ($day1['target_at_sunrise'] ?? false);
        $day2AtSunrise = (bool) ($day2['target_at_sunrise'] ?? false);
        $day1ShuddhaVyapti = $day1Vyapti && !$day1Viddha && (bool) ($day1['target_during_observance'] ?? false);
        $day2ShuddhaVyapti = $day2Vyapti && !$day2Viddha && (bool) ($day2['target_during_observance'] ?? false);
        $preferLast = $vriddhiPreference === 'last';

        // Both shuddha madhyahna-vyapini: respect configured vriddhi preference.
        if ($day1ShuddhaVyapti && $day2ShuddhaVyapti) {
            if ($bothDaysPreferNavamiYuta) {
                return $this->markSpecialWinner($day2, 'madhyahna_shuddha_navami_yuta_day2');
            }

            return $preferLast
                ? $this->markSpecialWinner($day2, 'madhyahna_shuddha_para_vedha_free_day2')
                : $this->markSpecialWinner($day1, 'madhyahna_shuddha_purva_vedha_free_day1');
        }

        $day1Present = (bool) ($day1['target_during_observance'] ?? false);
        $day2Present = (bool) ($day2['target_during_observance'] ?? false);
        if ($bothDaysPreferNavamiYuta && $day1Present && $day2Present) {
            if ($day1ShuddhaVyapti) {
                return $this->markSpecialWinner($day1, 'madhyahna_shuddha_purva_vedha_free_day1');
            }

            return $this->markSpecialWinner($day2, 'madhyahna_shuddha_navami_yuta_day2');
        }

        // Navami-yukta observance on the standalone shuddha udaya day (para day of the pair).
        if ($bothDaysPreferNavamiYuta && $day1AtSunrise && !$day2AtSunrise && $day1ShuddhaVyapti) {
            return $this->markSpecialWinner($day1, 'madhyahna_shuddha_navami_yuta_day2');
        }

        if ($preferLast) {
            if ($day2ShuddhaVyapti) {
                return $this->markSpecialWinner($day2, 'madhyahna_shuddha_para_vedha_free_day2');
            }

            if ($day1ShuddhaVyapti) {
                return $this->markSpecialWinner($day1, 'madhyahna_shuddha_purva_vedha_free_day1');
            }
        } else {
            if ($day1ShuddhaVyapti) {
                return $this->markSpecialWinner($day1, 'madhyahna_shuddha_purva_vedha_free_day1');
            }

            if ($day2ShuddhaVyapti) {
                return $this->markSpecialWinner($day2, 'madhyahna_shuddha_para_vedha_free_day2');
            }
        }

        // Neither (or only non-vyapini) madhyahna: prefer shuddha sunrise day.
        // SJ Varaha (`last`) and panchami-yuta style fallbacks take the later day first.
        if ($preferLast) {
            if ($day2AtSunrise && !$day2Viddha && (bool) ($day2['target_during_observance'] ?? false)) {
                return $this->markSpecialWinner($day2, 'madhyahna_shuddha_para_yuta_day2');
            }

            if ($day1AtSunrise && !$day1Viddha && (bool) ($day1['target_during_observance'] ?? false)) {
                return $this->markSpecialWinner($day1, 'madhyahna_shuddha_purva_yuta_day1');
            }
        } else {
            if ($day2AtSunrise && !$day2Viddha && (bool) ($day2['target_during_observance'] ?? false)) {
                return $this->markSpecialWinner($day2, 'madhyahna_shuddha_para_yuta_day2');
            }

            if ($day1AtSunrise && !$day1Viddha && (bool) ($day1['target_during_observance'] ?? false)) {
                return $this->markSpecialWinner($day1, 'madhyahna_shuddha_purva_yuta_day1');
            }
        }

        // Kshaya: only the previous-tithi-viddha purva day carries the tithi -> accept it. If
        // instead the later candidate carries a still-viddha tithi (whose shuddha lies on the
        // following day), defer so the shuddha pairing decides.
        return (bool) ($day1['target_during_observance'] ?? false)
            ? $this->markSpecialWinner($day1, 'madhyahna_kshaya_accept_prev_vedha_day1')
            : null;
    }

    private function resolvePurnimaVratTruthTable(array $candidates): ?array
    {
        $day1 = $candidates[0] ?? null;
        $day2 = $candidates[1] ?? null;

        if (!is_array($day1)) {
            return null;
        }

        if (!(bool) ($day1['target_during_observance'] ?? false)) {
            return is_array($day2) && (bool) ($day2['target_during_observance'] ?? false) && !(bool) ($day2['rule_rejected'] ?? false)
                ? $this->markSpecialWinner($day2, 'purnima_vrat_only_day2_available')
                : null;
        }

        $sunriseJd = (float) ($day1['sunrise_jd'] ?? 0.0);
        $contaminationJd = max(0.0, (float) ($day1['target_interval_start_jd'] ?? $sunriseJd) - $sunriseJd);
        $thresholdJd = $this->dynamicDayGhatiThresholdJd($day1, FestivalRuleConstants::PURNIMA_VRAT_CHATURDASHI_THRESHOLD_GHADIS);

        if ($contaminationJd < $thresholdJd) {
            return $this->markSpecialWinner($day1, 'purnima_vrat_chaturdashi_below_18_ghadi_keep_day1');
        }

        if (is_array($day2) && (bool) ($day2['target_during_observance'] ?? false) && !(bool) ($day2['rule_rejected'] ?? false)) {
            return $this->markSpecialWinner($day2, 'purnima_vrat_chaturdashi_at_or_above_18_ghadi_shift_day2');
        }

        return $this->markSpecialWinner($day1, 'purnima_vrat_no_day2_fallback_day1');
    }

    private function resolveSkandaSashtiTruthTable(array $candidates): ?array
    {
        $day1 = $candidates[0];
        $day2 = $candidates[1];

        $day1StartsBeforeSunset = $day1['target_interval_start_jd'] < $day1['sunset_jd'];
        $day1IsPanchamiAtSunriseDay = !$day1['target_at_sunrise'];

        if (
            $day1['target_during_observance']
            && $day1['target_at_karmakala']
            && !$day1['rule_rejected']
            && $day1IsPanchamiAtSunriseDay
            && $day1StartsBeforeSunset
        ) {
            return $this->markSpecialWinner($day1, 'skanda_sashti_panchami_viddha_evening_match');
        }

        if ($day2['target_at_sunrise'] && $day2['target_during_observance'] && !$day2['rule_rejected']) {
            return $this->markSpecialWinner($day2, 'skanda_sashti_shuddha_sunrise_match');
        }

        if ($day2['target_at_karmakala'] && !$day2['rule_rejected']) {
            return $this->markSpecialWinner($day2, 'skanda_sashti_evening_day2_match');
        }

        return $day1['target_during_observance'] && !$day1['rule_rejected']
            ? $this->markSpecialWinner($day1, 'skanda_sashti_fallback_day1')
            : null;
    }

    private function markSpecialWinner(array $candidate, string $reason): array
    {
        $candidate['reason'] = $reason;
        $candidate['score'] = max((int) ($candidate['score'] ?? 0), 20_000);

        return $candidate;
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

    /**
     * Satsangi Jeevan 4.60 (śl. 24–27) Rama Navami:
     * - take Ashtami-vedha-free madhyahna-vyapini Navami;
     * - if madhyahna-vyapini on both days, or not clearly on either day, take the later (para) Navami;
     * - Ashtami-viddha Navami is rejected even if Punarvasu is present;
     * - on kshaya of the pure Navami, the purva-yuk / Ashtami-joined day is accepted.
     */
    private function resolveRamNavamiTruthTable(array $candidates, array $today, array $targetInterval): array
    {
        $day1 = $candidates[0];
        $day2 = $candidates[1];

        $day1Vyapti = ((float) ($day1['target_window_overlap_seconds'] ?? 0.0) > 0.0)
            || (bool) ($day1['target_at_karmakala'] ?? false);
        $day2Vyapti = ((float) ($day2['target_window_overlap_seconds'] ?? 0.0) > 0.0)
            || (bool) ($day2['target_at_karmakala'] ?? false);
        $day1Viddha = (bool) ($day1['prev_tithi_at_sunrise'] ?? false)
            || $this->previousTithiActiveAtPoint($today, $targetInterval, 'sunrise');
        $day2Viddha = (bool) ($day2['prev_tithi_at_sunrise'] ?? false);
        $day1During = (bool) ($day1['target_during_observance'] ?? false);
        $day2During = (bool) ($day2['target_during_observance'] ?? false);

        $day1ShuddhaVyapti = $day1Vyapti && !$day1Viddha && $day1During;
        $day2ShuddhaVyapti = $day2Vyapti && !$day2Viddha && $day2During;

        // Both pure and madhyahna-vyapini → para (second) Navami.
        if ($day1ShuddhaVyapti && $day2ShuddhaVyapti) {
            return $this->markSpecialWinner($day2, 'ram_navami_both_madhyahna_choose_para_day2');
        }

        // Single pure madhyahna-vyapini day.
        if ($day1ShuddhaVyapti) {
            return $this->markSpecialWinner($day1, 'ram_navami_shuddha_madhyahna_day1');
        }

        if ($day2ShuddhaVyapti) {
            return $this->markSpecialWinner($day2, 'ram_navami_shuddha_madhyahna_day2');
        }

        // Ashtami-viddha first day rejected when a pure later day exists.
        if ($day1Viddha && !$day2Viddha && $day2During) {
            return $this->markSpecialWinner($day2, 'ram_navami_ashtami_viddha_reject_choose_para_day2');
        }

        // Neither clearly madhyahna-vyapini → still take the later/pure Navami when available.
        if (!$day1Vyapti && !$day2Vyapti) {
            if (!$day2Viddha && $day2During) {
                return $this->markSpecialWinner($day2, 'ram_navami_neither_madhyahna_choose_para_day2');
            }

            if ($day1During) {
                return $this->markSpecialWinner($day1, 'ram_navami_kshaya_accept_purva_yuk_day1');
            }
        }

        // Only one day carries madhyahna (possibly with residual vedha handling above).
        if ($day1Vyapti && $day1During && !$day2Vyapti) {
            return $this->markSpecialWinner($day1, $day1Viddha
                ? 'ram_navami_kshaya_accept_ashtami_viddha_day1'
                : 'ram_navami_shuddha_madhyahna_day1');
        }

        if ($day2Vyapti && $day2During) {
            return $this->markSpecialWinner($day2, $day2Viddha
                ? 'ram_navami_accept_day2_with_vedha'
                : 'ram_navami_shuddha_madhyahna_day2');
        }

        if ($day2During) {
            return $this->markSpecialWinner($day2, 'ram_navami_fallback_para_day2');
        }

        return $this->markSpecialWinner($day1, 'ram_navami_fallback_purva_day1');
    }

    private function resolveGenericViddhaRejectionTable(
        array $candidates,
        bool $kshayaAcceptViddha,
        string $vriddhiPreference,
        string $kshayaPreference
    ): array {
        $day1 = $candidates[0];
        $day2 = $candidates[1];

        $day1Vyapti = $day1['target_window_overlap_seconds'] > 0;
        $day2Vyapti = $day2['target_window_overlap_seconds'] > 0;

        $day1Viddha = (bool) ($day1['prev_tithi_at_sunrise'] ?? false);
        $day2Viddha = (bool) ($day2['prev_tithi_at_sunrise'] ?? false);

        if ($day1Vyapti && !$day1Viddha) {
            return $this->markSpecialWinner($day1, 'generic_shuddha_day1');
        }

        if ($day2Vyapti && !$day2Viddha) {
            return $this->markSpecialWinner($day2, 'generic_shuddha_day2');
        }

        if ($day1Vyapti && $day2Vyapti) {
            if ($vriddhiPreference === 'first') {
                return $this->markSpecialWinner($day1, 'generic_vriddhi_viddha_first');
            }

            return $this->markSpecialWinner($day2, 'generic_vriddhi_viddha_last');
        }

        if ($day1Vyapti) {
            if ($kshayaAcceptViddha) {
                return $this->markSpecialWinner($day1, 'generic_accept_viddha_day1');
            }
        } elseif ($day2Vyapti) {
            if ($kshayaAcceptViddha) {
                return $this->markSpecialWinner($day2, 'generic_accept_viddha_day2');
            }
        }

        if ($kshayaPreference === 'first') {
            return $this->markSpecialWinner($day1, 'generic_kshaya_fallback_first');
        }

        return $this->markSpecialWinner($day2, 'generic_kshaya_fallback_last');
    }

}
