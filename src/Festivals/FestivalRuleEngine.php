<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Festivals;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Core\Localization;

class FestivalRuleEngine
{
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
        $preferNakshatra = (bool) ($rule['prefer_nakshatra'] ?? false);

        $tithiAtSunriseToday = $this->isTargetAtPoint((float) $ctxToday['sunrise_jd'], $targetInterval);
        $tithiAtSunriseTomorrow = $this->isTargetAtPoint((float) $ctxTomorrow['sunrise_jd'], $targetInterval);
        $vriddhi = $tithiAtSunriseToday && $tithiAtSunriseTomorrow;
        $kshaya = !$tithiAtSunriseToday && !$tithiAtSunriseTomorrow;

        $candidates = [
            $this->buildCandidate($date, $today, $targetInterval, $karmakalaType, 0, $rule),
            $this->buildCandidate($date->addDay(), $tomorrow, $targetInterval, $karmakalaType, 1, $rule),
        ];
        $forceEkadashiKshayaNextDay = $kshaya && $kshayaPreference === 'last';

        if ($forceEkadashiKshayaNextDay) {
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
                if ($atKarmakala !== []) {
                    $filtered = $atKarmakala;
                }
            }

            $matchingWeekday = array_values(array_filter($filtered, static fn (array $candidate): bool => $candidate['weekday_matches']));
            if ($matchingWeekday !== []) {
                $filtered = $matchingWeekday;
            }

            if ($preferNakshatra) {
                $matchingNakshatra = array_values(array_filter($filtered, static fn (array $candidate): bool => $candidate['nakshatra_matches']));
                if ($matchingNakshatra !== []) {
                    $filtered = $matchingNakshatra;
                }
            }

            usort(
                $filtered,
                fn (array $left, array $right): int => $this->compareCandidates($left, $right, $vriddhi, $kshaya, $vriddhiPreference, $kshayaPreference, $preferFirstKarmakala)
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
                : "Exception: Standard {$localizedPaksha} Tithi {$requiredTithi} falls on {$tomorrowStr} at sunrise, but due to tradition/ritual requiring {$localizedKarmakala} presence, it is celebrated on {$todayStr}.";
        } elseif ($winner['day_offset'] === 1 && $tithiAtSunriseToday && !$tithiAtSunriseTomorrow) {
            $standardDate = $todayStr;
            $observanceNote = Localization::translate('String', 'observance_note_sunrise_shift_tomorrow') !== 'observance_note_sunrise_shift_tomorrow'
                ? sprintf(Localization::translate('String', 'observance_note_sunrise_shift_tomorrow'), $localizedPaksha, $requiredTithi, $todayStr, $localizedKarmakala, $tomorrowStr)
                : "Exception: Standard {$localizedPaksha} Tithi {$requiredTithi} falls on {$todayStr} at sunrise, but due to tradition/ritual requiring {$localizedKarmakala} presence, observance shifts to {$tomorrowStr}.";
        } elseif ($kshaya) {
            $standardDate = $todayStr; // Kshaya tithi generally aligns with the day it starts
            if ($winner['date'] !== $standardDate) {
                $observanceNote = Localization::translate('String', 'observance_note_kshaya') !== 'observance_note_kshaya'
                    ? sprintf(Localization::translate('String', 'observance_note_kshaya'), $localizedPaksha, $requiredTithi, $winner['date'], $localizedKarmakala)
                    : "Exception: {$localizedPaksha} Tithi {$requiredTithi} is a Kshaya Tithi (skips sunrise). Observance shifts to {$winner['date']} due to {$localizedKarmakala} rules.";
            }
        } elseif ($vriddhi) {
            $standardDate = $todayStr; // Vriddhi default first day
            if ($winner['date'] !== $standardDate) {
                $observanceNote = Localization::translate('String', 'observance_note_vriddhi') !== 'observance_note_vriddhi'
                    ? sprintf(Localization::translate('String', 'observance_note_vriddhi'), $localizedPaksha, $requiredTithi, $winner['date'], $localizedKarmakala)
                    : "Exception: {$localizedPaksha} Tithi {$requiredTithi} is a Vriddhi Tithi (spans two sunrises). Observance shifts to {$winner['date']} due to {$localizedKarmakala} rules.";
            }
        }

        return [
            'festival_name' => $festivalName,
            'required_tithi' => $requiredTithi,
            'paksha' => $paksha,
            'karmakala_type' => $karmakalaType,
            'tithi_at_karmakala_today' => $candidates[0]['target_at_karmakala'],
            'tithi_at_karmakala_tomorrow' => $candidates[1]['target_at_karmakala'],
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
                'vriddhi_preference' => $vriddhiPreference,
                'kshaya_preference' => $kshayaPreference,
                'preferred_nakshatra' => $rule['nakshatra'] ?? null,
                'winning_reason' => $winner['reason'],
                'winning_score' => $winner['score'],
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

        if ($requiredNakshatraNumber !== null && $todayNakshatraNumber !== null && $tomorrowNakshatraNumber !== null) {
            $nakshatraTodayMatch = $todayNakshatraNumber === $requiredNakshatraNumber;
            $nakshatraTomorrowMatch = $tomorrowNakshatraNumber === $requiredNakshatraNumber;
        } else {
            $nakshatraToday = (string) ($today['Nakshatra']['name'] ?? '');
            $nakshatraTomorrow = (string) ($tomorrow['Nakshatra']['name'] ?? '');
            $nakshatraTodayMatch = strcasecmp($requiredNakshatra, $nakshatraToday) === 0;
            $nakshatraTomorrowMatch = strcasecmp($requiredNakshatra, $nakshatraTomorrow) === 0;
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
            $allowedMonthsNorm = array_map(fn ($m) => $this->normalizeMonthName((string) $m), $allowedMonths);
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

        $allowedSunSigns = isset($rule['sun_sign']) ? [(int) $rule['sun_sign']] : array_map('intval', (array) ($rule['allowed_sun_signs'] ?? []));
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
                return $this->buildNakshatraResult($festivalName, $rule, $date, $karmakalaType, $requiredNakshatra, 'nakshatra_and_purnima_match');
            }
            if ($nakshatraTomorrowMatch && $isPurnimaTomorrow) {
                return $this->buildNakshatraResult($festivalName, $rule, $date->addDay(), $karmakalaType, $requiredNakshatra, 'nakshatra_and_purnima_match');
            }
            return null;
        }

        // Simple nakshatra-only match (at least one match is guaranteed here due to early returns above)
        if ($nakshatraTodayMatch) {
            return $this->buildNakshatraResult($festivalName, $rule, $date, $karmakalaType, $requiredNakshatra, 'nakshatra_match');
        }

        // $nakshatraTomorrowMatch must be true at this point
        return $this->buildNakshatraResult($festivalName, $rule, $date->addDay(), $karmakalaType, $requiredNakshatra, 'nakshatra_match');
    }

    /** Build nakshatra-based festival result. */
    private function buildNakshatraResult(
        string $festivalName,
        array $rule,
        CarbonImmutable $observanceDate,
        string $karmakalaType,
        string $nakshatraName,
        string $reason
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
            ],
        ];
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
        $sunriseJd = (float) ($ctx['sunrise_jd'] ?? 0.0);
        $nextSunriseJd = (float) ($ctx['next_sunrise_jd'] ?? 0.0);
        $prevTithiEndJd = (float) ($ctx['prev_tithi_end_jd'] ?? 0.0);
        $nakshatraName = (string) ($details['Nakshatra']['name'] ?? '');
        $requiredNakshatra = (string) ($rule['nakshatra'] ?? '');
        $requiredWeekday = $rule['weekday'] ?? null;

        $targetAtKarmakala = $this->isTargetAtPoint($karmakalaJd, $targetInterval);
        $targetAtSunrise = $this->isTargetAtPoint($sunriseJd, $targetInterval);
        $targetDuringObservance = $targetInterval['start_jd'] < $nextSunriseJd && $targetInterval['end_jd'] > $sunriseJd;

        $score = 0;
        $reason = 'target_during_observance';

        if ($targetAtKarmakala) {
            $score += 1000;
            $reason = 'target_at_karmakala';
        } elseif ($targetAtSunrise) {
            $score += 700;
            $reason = 'target_at_sunrise';
        } elseif ($targetDuringObservance) {
            $score += 300;
        }

        if ($requiredWeekday !== null && (int) $requiredWeekday === $date->dayOfWeek) {
            $score += 150;
        }

        if ($requiredNakshatra !== '' && strcasecmp($requiredNakshatra, $nakshatraName) === 0) {
            $score += 125;
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
            'nakshatra_matches' => $requiredNakshatra !== '' && strcasecmp($requiredNakshatra, $nakshatraName) === 0,
            'prev_tithi_at_karmakala' => $prevTithiEndJd > $karmakalaJd,
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
        bool $preferFirstKarmakala = false
    ): int
    {
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

    private function isTargetAtPoint(float $jd, array $targetInterval): bool
    {
        return $targetInterval['start_jd'] <= $jd && $targetInterval['end_jd'] > $jd;
    }

    private function karmakalaJd(string $type, array $ctx): float
    {
        $sunrise = (float) $ctx['sunrise_jd'];
        $sunset = (float) $ctx['sunset_jd'];
        $nextSunrise = (float) $ctx['next_sunrise_jd'];
        $dayDuration = $sunset - $sunrise;

        return match ($type) {
            'madhyahna' => $sunrise + ($dayDuration / 2.0),
            'aparahna' => $sunrise + ($dayDuration * 3.0 / 4.0),
            'nishitha' => $sunset + (($nextSunrise - $sunset) / 2.0),
            'pradosha' => $sunset + (3.0 / 24.0),
            default => $sunrise,
        };
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
}
