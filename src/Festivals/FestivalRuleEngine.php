<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Festivals;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Core\Constants\ClassicalTimeConstants;
use JayeshMepani\PanchangCore\Core\Localization;
use JayeshMepani\PanchangCore\Panchanga\KalaNirnayaEngine;
use LogicException;

class FestivalRuleEngine
{
    private const float RAKSHA_BANDHAN_UDAYA_PURNIMA_THRESHOLD_MUHURTAS = 3.0;

    // Govardhan/Annakut "Sthula Chandra Darshana" threshold: if Kartika Shukla Pratipada
    // persists at least 9 muhurtas past sunrise the day is treated as free of Sthula
    // Chandra Darshana; otherwise the observance shifts to the previous (Amavasya-viddha) day.
    private const float GOVARDHAN_STHULA_CHANDRA_DARSHANA_MUHURTAS = 9.0;

    private const float CHANDRA_DARSHANA_CRESCENT_MIN_LAG_MINUTES = 38.0;

    private const float CHANDRA_DARSHANA_CRESCENT_MIN_ELONGATION_DEGREES = 9.0;

    private const float CHANDRA_DARSHANA_CRESCENT_HARD_ELONGATION_FLOOR_DEGREES = 7.0;

    private const float CHANDRA_DARSHANA_CRESCENT_MIN_ILLUMINATION_PERCENT = 0.8;

    // Nag Panchami (Shravana Krishna Panchami) is paraviddha: the reference keeps the Panchami
    // pierced by the Shashthi that spans at least 6 daytime ghadis past sunrise, and only
    // shifts the observance based on the same 6-ghadi Chaturthi vedha threshold.
    private const float NAG_PANCHAMI_SHASHTHI_VEDHA_GHADI = 6.0;

    // Durgashtami / Bhavani Pragatya (Chaitra Shukla Ashtami and its monthly derivative) is
    // paraviddha (navami-viddha): the reference takes the Ashtami spanning at least 3 muhurtas
    // past sunrise, otherwise it falls back to the Saptami-viddha previous day.
    private const float DURGASHTAMI_PARAVIDDHA_MUHURTAS = 3.0;

    // Akshaya Tritiya (Vaishakha Shukla Tritiya) is purvahna (forenoon) vyapini. When the
    // Tritiya pervades the purvahna on both civil days the reference shifts the observance to
    // the second day only if the second day's Tritiya spans at least 3 muhurtas past sunrise;
    // otherwise it stays on the first (purva) day.
    private const float AKSHAYA_TRITIYA_PURVAHNA_MUHURTAS = 3.0;

    // Anant Chaturdashi (Bhadrapada Shukla Chaturdashi) is a post-sunrise paraviddha: the
    // reference takes the Chaturdashi spanning at least 2 muhurtas past sunrise, otherwise the
    // observance falls back to the previous day. Purvahna is the primary kala.
    private const float ANANT_CHATURDASHI_PARAVIDDHA_MUHURTAS = 2.0;

    // Chaitra/Ashvina Navaratri Pratipada starts on Pratipada when it lasts at least one
    // dinamana-muhurta after sunrise; when below one muhurta or kshaya, the start falls
    // back to the Amavasya-viddha previous day.
    private const float NAVRATRI_PRATIPADA_MIN_MUHURTAS = 1.0;

    // Durva Ashtami (Dharo Atham) is purvaviddha with a sunset-side check: if Ashtami
    // begins at least three dinamana-muhurtas before sunset on the Saptami day, take
    // that first day; vriddhi/kshaya also keep the first day.
    private const float DURVA_ASHTAMI_PURVAVIDDHA_MUHURTAS = 3.0;

    // Vratni Purnima uses a daytime ghadi threshold derived from local dinamana.
    private const float PURNIMA_VRAT_CHATURDASHI_THRESHOLD_GHADIS = 18.0;

    private const array SUPPORTED_KARMAKALA_TYPES = [
        'abhijit',
        'aparahna',
        'arunodaya',
        'chandra_darshana_visibility',
        'daytime',
        'madhyahna',
        'moonrise',
        'nishitha',
        'pradosha',
        'prathama_ratri',
        'pratah_first_third',
        'pratah_kal',
        'purvahna',
        'sangava',
        'sayankala',
        'sunrise',
        'sunset',
        'tithi_boundary',
        'vijaya_kaal',
    ];

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
        $this->assertValidFestivalRule($festivalName, $rule);
        if (!$this->isExecutableResolverProfile($rule)) {
            return null;
        }

        // Named Ashwina Amavasya under purnimanta is the prior (Mahalaya) Amavasya and must
        // use sunrise attribution (e.g. 2026-10-10). Aparahna/Deepavali civil-day logic
        // applies only under amanta (e.g. 2026-11-08). Chopda/Lakshmi stay on their own rules.
        $calendarTypeEarly = strtolower((string) (
            $today['Hindu_Calendar']['Calendar_Type']
            ?? AstroCore::getConfig('panchang.defaults.calendar_type', 'amanta')
        ));
        if (
            $calendarTypeEarly === 'purnimanta'
            && (bool) ($rule['darsha_amavasya_aparahna_table'] ?? false)
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
            if ($specialWinner === null && $exclusiveTruthTable) {
                continue;
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

    private function resolveRulePaksha(array $rule, array $calendar, string $fallbackPaksha = 'Shukla'): array|string
    {
        $calendarType = strtolower((string) ($calendar['Calendar_Type'] ?? AstroCore::getConfig('panchang.defaults.calendar_type', 'amanta')));
        if ($calendarType === 'purnimanta' && isset($rule['paksha_purnimanta'])) {
            return $rule['paksha_purnimanta'];
        }

        if ($calendarType !== 'purnimanta' && isset($rule['paksha_amanta'])) {
            return $rule['paksha_amanta'];
        }

        return $rule['paksha'] ?? $fallbackPaksha;
    }

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
        $thresholdSeconds = self::GOVARDHAN_STHULA_CHANDRA_DARSHANA_MUHURTAS * $muhurtaSeconds;
        if ($thresholdSeconds <= 0.0) {
            return null;
        }

        $targetTithi = null;
        $assessment = null;

        $visibilityAffectsSelection = (bool) ($rule['chandra_darshana_visibility_affects_selection'] ?? false);
        $crescentVisibleToday = $this->isYoungCrescentVisibleAtSunset($details, $rule);

        if ($currentAbs === 1) {
            // Pratipada is udaya-vyapini (day P). Sthula darshana is present (CD today, Sud 1)
            // only when Pratipada is short: it does not persist 9 muhurtas past sunrise.
            $pratipadaInterval = $this->deriveSnapshotTithiInterval(1, 'Shukla', $details, $nextDetails);
            if ($pratipadaInterval !== null) {
                $postSunriseSeconds = max(0.0, ($pratipadaInterval['end_jd'] - $sunriseJd) * 86400.0);
                $dwitiyaActiveAtSunset = ($pratipadaInterval['end_jd'] < $sunsetJd);

                $classicalPassed = ($postSunriseSeconds < $thresholdSeconds || $dwitiyaActiveAtSunset);

                if ($visibilityAffectsSelection) {
                    // Under modern visibility selection, we must confirm crescent is visible today.
                    if ($crescentVisibleToday) {
                        $targetTithi = $dwitiyaActiveAtSunset ? 2 : 1;
                        $reason = $dwitiyaActiveAtSunset
                            ? 'chandra_darshana_dwitiya_fallback_at_local_sunset'
                            : ($classicalPassed
                                ? 'chandra_darshana_sud1_short_pratipada_sthula_present'
                                : 'chandra_darshana_sud1_crescent_visible_at_sunset');
                        $assessment = $this->chandraDarshanaSthulaAssessment($targetTithi, $postSunriseSeconds, $muhurtaSeconds, $reason, $details, $rule);
                    }
                } elseif ($classicalPassed) {
                    // Classical selection.
                    $targetTithi = $dwitiyaActiveAtSunset ? 2 : 1;
                    $reason = $dwitiyaActiveAtSunset
                        ? 'chandra_darshana_dwitiya_fallback_at_local_sunset'
                        : 'chandra_darshana_sud1_short_pratipada_sthula_present';
                    $assessment = $this->chandraDarshanaSthulaAssessment($targetTithi, $postSunriseSeconds, $muhurtaSeconds, $reason, $details, $rule);
                } elseif ($crescentVisibleToday) {
                    // Long Pratipada normally defers Sthula darshana to Sud 2, but when the young
                    // crescent is astronomically sightable at sunset the observance stays on Sud 1.
                    $targetTithi = 1;
                    $assessment = $this->chandraDarshanaSthulaAssessment(1, $postSunriseSeconds, $muhurtaSeconds, 'chandra_darshana_sud1_crescent_visible_at_sunset', $details, $rule);
                }
            }
        } elseif ($currentAbs === 2) {
            // Dwitiya is udaya-vyapini. CD is here (Sud 2) only when the preceding Pratipada was
            // long (>= 9 muhurtas past its sunrise) AND did not start before the preceding sunset.
            $prevPratipadaEndJd = (float) ($ctx['tithi_start_jd'] ?? 0.0);
            if ($prevSunriseJd > 0.0 && $prevPratipadaEndJd > $prevSunriseJd) {
                $postSunriseSeconds = max(0.0, ($prevPratipadaEndJd - $prevSunriseJd) * 86400.0);
                $prevSunsetJd = $prevSunriseJd + ($sunsetJd - $sunriseJd);
                $dwitiyaStartedBeforePrevSunset = ($prevSunsetJd > 0.0 && $prevPratipadaEndJd < $prevSunsetJd);

                if ($visibilityAffectsSelection) {
                    // Resolve today if crescent is visible today and was NOT visible yesterday.
                    if ($crescentVisibleToday) {
                        $crescentVisibleYesterday = $this->isYoungCrescentVisibleAtYesterdaySunset($details, $rule);
                        if (!$crescentVisibleYesterday) {
                            $targetTithi = 2;
                            $reason = 'chandra_darshana_sud2_long_pratipada_no_sthula_on_sud1';
                            $assessment = $this->chandraDarshanaSthulaAssessment(2, $postSunriseSeconds, $muhurtaSeconds, $reason, $details, $rule);
                        }
                    }
                } elseif ($postSunriseSeconds >= $thresholdSeconds && !$dwitiyaStartedBeforePrevSunset) {
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

                if ($visibilityAffectsSelection) {
                    if ($crescentVisibleToday) {
                        $targetTithi = 1;
                        $assessment = $this->chandraDarshanaSthulaAssessment(1, $postSunriseSeconds, $muhurtaSeconds, 'chandra_darshana_sud1_kshaya_pratipada_sthula_present', $details, $rule);
                    }
                } else {
                    $targetTithi = 1;
                    $assessment = $this->chandraDarshanaSthulaAssessment(1, $postSunriseSeconds, $muhurtaSeconds, 'chandra_darshana_sud1_kshaya_pratipada_sthula_present', $details, $rule);
                }
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
            'sthula_threshold_muhurtas' => self::GOVARDHAN_STHULA_CHANDRA_DARSHANA_MUHURTAS,
            'sthula_threshold_seconds' => self::GOVARDHAN_STHULA_CHANDRA_DARSHANA_MUHURTAS * $muhurtaSeconds,
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
        return Localization::translate('String', $this->normalizeKarmakalaType($karmakalaType));
    }

    private function normalizeKarmakalaType(string $type): string
    {
        return match ($type) {
            'nishita', 'midnight' => 'nishitha',
            'sayahna' => 'sayankala',
            'pratah' => 'pratah_kal',
            default => $type,
        };
    }

    private function assertValidFestivalRule(string $name, array $rule): void
    {
        $kala = $this->normalizeKarmakalaType((string) ($rule['karmakala_type'] ?? 'sunrise'));
        if (!in_array($kala, self::SUPPORTED_KARMAKALA_TYPES, true)) {
            throw new LogicException(sprintf("Unknown karmakala_type '%s' for %s", $kala, $name));
        }

        foreach (['vriddhi_preference', 'kshaya_preference'] as $field) {
            if (isset($rule[$field]) && !in_array($rule[$field], ['first', 'last'], true)) {
                throw new LogicException(sprintf('Invalid %s for %s', $field, $name));
            }
        }
    }

    private function isExecutableResolverProfile(array $rule): bool
    {
        $resolverCompatibility = (string) ($rule['resolver_compatibility'] ?? 'full');
        if ($resolverCompatibility === '' || $resolverCompatibility === 'full') {
            return true;
        }

        return (bool) ($rule['allow_partial_resolver_execution'] ?? false);
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

        $this->assertValidFestivalRule($festivalName, $rule);
        if (!$this->isExecutableResolverProfile($rule)) {
            return null;
        }

        $requiredNakshatra = (string) ($rule['nakshatra'] ?? '');
        if ($requiredNakshatra === '') {
            return null;
        }

        $requiredNakshatraNumber = $this->resolveNakshatraNumber($requiredNakshatra);

        $karmakalaType = $this->normalizeKarmakalaType((string) ($rule['karmakala_type'] ?? 'sunrise'));
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

        $requiredPakshas = $this->requiredPakshasForNakshatraRule($rule);
        if ($requiredPakshas !== []) {
            $pakshaToday = (string) ($today['Tithi']['paksha'] ?? '');
            $pakshaTomorrow = (string) ($tomorrow['Tithi']['paksha'] ?? '');

            if ($nakshatraTodayMatch && !in_array($pakshaToday, $requiredPakshas, true)) {
                $nakshatraTodayMatch = false;
            }

            if ($nakshatraTomorrowMatch && !in_array($pakshaTomorrow, $requiredPakshas, true)) {
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

    /** @return list<string> */
    private function requiredPakshasForNakshatraRule(array $rule): array
    {
        $pakshas = [];

        foreach (['paksha', 'paksha_amanta', 'paksha_purnimanta'] as $key) {
            if (!isset($rule[$key])) {
                continue;
            }

            foreach ((array) $rule[$key] as $value) {
                $value = (string) $value;
                if ($value !== '' && $value !== 'Both') {
                    $pakshas[] = $value;
                }
            }
        }

        foreach ((array) ($rule['allowed_pakshas'] ?? []) as $value) {
            $value = (string) $value;
            if ($value !== '' && $value !== 'Both') {
                $pakshas[] = $value;
            }
        }

        return array_values(array_unique($pakshas));
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

        if ((bool) ($rule['ekadashi_nirnay_table'] ?? false) || (bool) ($rule['require_vaishnava_ekadashi_today'] ?? false)) {
            return $this->resolveEkadashiNirnayTruthTable($candidates, $targetInterval);
        }

        if ((bool) ($rule['purnima_vrat_18_ghadi_rule'] ?? false)) {
            return $this->resolvePurnimaVratTruthTable($candidates);
        }

        if ((bool) ($rule['pradosh_truth_table'] ?? false) || $this->isPradoshRule($rule)) {
            return $this->resolvePradoshTruthTable($candidates);
        }

        if ((bool) ($rule['sankashti_truth_table'] ?? false) || $this->isSankashtiRule($rule)) {
            return $this->resolveSankashtiTruthTable($candidates);
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

    private function usesExclusiveTruthTable(array $rule): bool
    {
        foreach (['janmashtami_truth_table', 'masik_janmashtami_truth_table', 'vijayadashami_truth_table', 'govatsa_truth_table', 'mahashivaratri_truth_table', 'diwali_truth_table', 'ekadashi_nirnay_table', 'purnima_vrat_18_ghadi_rule', 'pradosh_truth_table', 'sankashti_truth_table', 'vinayaki_chaturthi_truth_table', 'narasimha_jayanti_truth_table', 'raksha_bandhan_truth_table', 'govardhan_annakut_truth_table', 'nag_panchami_paraviddha_table', 'durgashtami_paraviddha_table', 'akshaya_tritiya_purvahna_table', 'anant_chaturdashi_paraviddha_table', 'navratri_pratipada_table', 'durva_ashtami_purvaviddha_table', 'lalita_panchami_aparahna_table', 'akshaya_navami_purvahna_table', 'naraka_chaturdashi_abhyanga_table', 'darsha_amavasya_aparahna_table', 'gauri_tritiya_parayuta_table', 'madhyahna_purvatithi_vedha_rejection', 'panchami_viddha_allowed', 'ashtami_viddha_rejection', 'trayodashi_viddha_rejection', 'previous_tithi_viddha_rejection', 'tithi_boundary_rule'] as $flag) {
            if ((bool) ($rule[$flag] ?? false)) {
                return true;
            }
        }

        return $this->isEkadashiNirnayRule($rule) || $this->isPradoshRule($rule) || $this->isSankashtiRule($rule);
    }

    private function isEkadashiNirnayRule(array $rule): bool
    {
        return (int) ($rule['tithi'] ?? 0) === 11
            && ((bool) ($rule['ekadashi_nirnay_table'] ?? false) || (bool) ($rule['require_vaishnava_ekadashi_today'] ?? false));
    }

    private function isPradoshRule(array $rule): bool
    {
        return (int) ($rule['tithi'] ?? 0) === 13
            && $this->normalizeKarmakalaType((string) ($rule['karmakala_type'] ?? '')) === 'pradosha'
            && (bool) ($rule['fasting'] ?? false);
    }

    private function isSankashtiRule(array $rule): bool
    {
        return (int) ($rule['tithi'] ?? 0) === 4
            && (string) ($rule['paksha'] ?? '') === 'Krishna'
            && $this->normalizeKarmakalaType((string) ($rule['karmakala_type'] ?? '')) === 'moonrise'
            && str_contains(strtolower((string) ($rule['description'] ?? '')), 'sankashti');
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
        } elseif (is_array($pratipadaDay)) {
            // Neither candidate has the sunrise nakshatra: main Padwa/Pratipada day.
            $winner = $pratipadaDay;
            $reason = 'phuldolotsava_fallback_pratipada_without_sunrise_uttara_phalguni';
        } else {
            // Only Purnima is in the today/tomorrow window and it lacks sunrise Uttara Phalguni.
            // Defer so the Pratipada civil day can decide via its own pair.
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
        $thresholdSeconds = self::GOVARDHAN_STHULA_CHANDRA_DARSHANA_MUHURTAS * $dayMuhurtaSeconds;
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
        $day1SixGhadiJd = $this->dynamicDayGhatiThresholdJd($day1, self::NAG_PANCHAMI_SHASHTHI_VEDHA_GHADI);
        $day2SixGhadiJd = $this->dynamicDayGhatiThresholdJd($day2, self::NAG_PANCHAMI_SHASHTHI_VEDHA_GHADI);
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
            $thresholdSeconds = self::DURGASHTAMI_PARAVIDDHA_MUHURTAS * (float) ($day2['day_muhurta_seconds'] ?? 0.0);
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
            $thresholdSeconds = self::DURGASHTAMI_PARAVIDDHA_MUHURTAS * (float) ($day1['day_muhurta_seconds'] ?? 0.0);
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
                $thresholdSeconds = self::AKSHAYA_TRITIYA_PURVAHNA_MUHURTAS * (float) ($day2['day_muhurta_seconds'] ?? 0.0);
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
            $thresholdSeconds = self::ANANT_CHATURDASHI_PARAVIDDHA_MUHURTAS * (float) ($day2['day_muhurta_seconds'] ?? 0.0);
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
            $thresholdSeconds = self::ANANT_CHATURDASHI_PARAVIDDHA_MUHURTAS * (float) ($day1['day_muhurta_seconds'] ?? 0.0);
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
            $thresholdSeconds = self::NAVRATRI_PRATIPADA_MIN_MUHURTAS * (float) ($day2['day_muhurta_seconds'] ?? 0.0);
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
            $thresholdSeconds = self::NAVRATRI_PRATIPADA_MIN_MUHURTAS * (float) ($day1['day_muhurta_seconds'] ?? 0.0);
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
            $thresholdSeconds = self::DURVA_ASHTAMI_PURVAVIDDHA_MUHURTAS * (float) ($day1['day_muhurta_seconds'] ?? 0.0);
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
            if ($day1ShuddhaVyapti && !$day2ShuddhaVyapti) { // @phpstan-ignore booleanNot.alwaysTrue
                return $this->markSpecialWinner($day1, 'madhyahna_shuddha_purva_vedha_free_day1');
            }

            return $this->markSpecialWinner($day2, 'madhyahna_shuddha_navami_yuta_day2');
        }

        // Navami-yukta observance on the standalone shuddha udaya day (para day of the pair).
        if ($bothDaysPreferNavamiYuta && $day1AtSunrise && !$day2AtSunrise && $day1ShuddhaVyapti && !$day1Viddha) { // @phpstan-ignore booleanNot.alwaysTrue
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
        $thresholdJd = $this->dynamicDayGhatiThresholdJd($day1, self::PURNIMA_VRAT_CHATURDASHI_THRESHOLD_GHADIS);

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
            'nishitha' => [
                'start_jd' => $sunset + ($nightDuration / 2.0) - ($nightMuhurta / 2.0),
                'end_jd' => $sunset + ($nightDuration / 2.0) + ($nightMuhurta / 2.0),
            ],
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

    private function buildModernCrescentVisibilityAssessment(array $details, array $rule): array
    {
        $ctx = (array) ($details['Resolution_Context'] ?? []);
        $sunsetJd = (float) ($ctx['sunset_jd'] ?? 0.0);
        $moonsetJd = $this->extractJd($details['Moonset_JD'] ?? ($details['Moonset'] ?? null));

        $minLag = (float) ($rule['chandra_darshana_visibility_min_lag_minutes'] ?? self::CHANDRA_DARSHANA_CRESCENT_MIN_LAG_MINUTES);
        $minElongation = (float) ($rule['chandra_darshana_visibility_min_elongation_degrees'] ?? self::CHANDRA_DARSHANA_CRESCENT_MIN_ELONGATION_DEGREES);
        $hardFloor = (float) ($rule['chandra_darshana_visibility_hard_elongation_floor_degrees'] ?? self::CHANDRA_DARSHANA_CRESCENT_HARD_ELONGATION_FLOOR_DEGREES);
        $minIllumination = (float) ($rule['chandra_darshana_visibility_min_illumination_percent'] ?? self::CHANDRA_DARSHANA_CRESCENT_MIN_ILLUMINATION_PERCENT);

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

    private function isYoungCrescentVisibleAtYesterdaySunset(array $details, array $rule): bool
    {
        $ctx = (array) ($details['Resolution_Context'] ?? []);
        $sunsetJd = (float) ($ctx['prev_sunset_jd'] ?? 0.0);
        $moonsetJd = (float) ($ctx['prev_moonset_jd'] ?? 0.0);

        $minLag = (float) ($rule['chandra_darshana_visibility_min_lag_minutes'] ?? self::CHANDRA_DARSHANA_CRESCENT_MIN_LAG_MINUTES);
        $minElongation = (float) ($rule['chandra_darshana_visibility_min_elongation_degrees'] ?? self::CHANDRA_DARSHANA_CRESCENT_MIN_ELONGATION_DEGREES);
        $hardFloor = (float) ($rule['chandra_darshana_visibility_hard_elongation_floor_degrees'] ?? self::CHANDRA_DARSHANA_CRESCENT_HARD_ELONGATION_FLOOR_DEGREES);
        $minIllumination = (float) ($rule['chandra_darshana_visibility_min_illumination_percent'] ?? self::CHANDRA_DARSHANA_CRESCENT_MIN_ILLUMINATION_PERCENT);

        if ($sunsetJd <= 0.0 || $moonsetJd <= $sunsetJd) {
            return false;
        }

        $lagMinutes = ($moonsetJd - $sunsetJd) * 1440.0;
        $elongation = (float) ($ctx['prev_moon_sun_elongation_at_sunset_degrees'] ?? 0.0);
        $illumination = (float) ($ctx['prev_moon_illumination_at_sunset_percent'] ?? 0.0);

        $passesLag = ($lagMinutes >= $minLag);
        $passesHardElongationFloor = ($elongation >= $hardFloor);
        $passesElongation = ($elongation >= $minElongation);
        $passesIllumination = ($illumination >= $minIllumination);

        return $passesLag && $passesHardElongationFloor && ($passesElongation || $passesIllumination);
    }

    private function isYoungCrescentVisibleAtSunset(array $details, array $rule): bool
    {
        return $this->buildModernCrescentVisibilityAssessment($details, $rule)['visible'];
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

        $normalized = strtolower($asciiOnly);

        return match ($normalized) {
            'ashwin', 'ashwina' => 'ashvina',
            default => $normalized,
        };
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
