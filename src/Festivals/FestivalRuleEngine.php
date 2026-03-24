<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Festivals;

use Carbon\CarbonImmutable;

class FestivalRuleEngine
{
    /**
     * Resolve major Hindu/Sanatan observance day by karmakala precedence and tithi continuity.
     */
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

        $paksha = (string) ($rule['paksha'] ?? '');
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
        $strictKarmakala = (bool) ($rule['strict_karmakala'] ?? ($karmakalaType !== 'sunrise'));
        $preferNakshatra = (bool) ($rule['prefer_nakshatra'] ?? false);

        $tithiAtSunriseToday = $this->isTargetAtPoint((float) $ctxToday['sunrise_jd'], $targetInterval);
        $tithiAtSunriseTomorrow = $this->isTargetAtPoint((float) $ctxTomorrow['sunrise_jd'], $targetInterval);
        $vriddhi = $tithiAtSunriseToday && $tithiAtSunriseTomorrow;
        $kshaya = !$tithiAtSunriseToday && !$tithiAtSunriseTomorrow;

        $candidates = [
            $this->buildCandidate($date, $today, $targetInterval, $karmakalaType, 0, $rule),
            $this->buildCandidate($date->addDay(), $tomorrow, $targetInterval, $karmakalaType, 1, $rule),
        ];
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
            fn (array $left, array $right): int => $this->compareCandidates($left, $right, $vriddhi, $vriddhiPreference)
        );
        $winner = $filtered[0] ?? null;
        if ($winner === null) {
            return null;
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
            'observance_date' => $winner['date'],
            'decision' => [
                'strict_karmakala' => $strictKarmakala,
                'vriddhi_preference' => $vriddhiPreference,
                'preferred_nakshatra' => $rule['nakshatra'] ?? null,
                'winning_reason' => $winner['reason'],
                'winning_score' => $winner['score'],
            ],
        ];
    }

    /**
     * Adhik/Kshaya maas tagging from amanta month progression.
     */
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

        for ($i = 1; $i < count($monthStartDates); $i++) {
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
                $missing = $monthOrder[$expected] ?? null;
                if ($missing !== null) {
                    $tagsByDate[$cur['date']] = ['month_status' => 'Kshaya Maas Transition', 'missing_month' => $missing];
                }
            }
        }

        return $tagsByDate;
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

        if ($todayAbs + 1 === $targetAbs) {
            return [
                'start_jd' => (float) $ctxToday['tithi_end_jd'],
                'end_jd' => (float) $ctxTomorrow['tithi_end_jd'],
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

    private function compareCandidates(array $left, array $right, bool $vriddhi, string $vriddhiPreference): int
    {
        if ($left['score'] !== $right['score']) {
            return $right['score'] <=> $left['score'];
        }

        if ($vriddhi) {
            if ($vriddhiPreference === 'last') {
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
}
