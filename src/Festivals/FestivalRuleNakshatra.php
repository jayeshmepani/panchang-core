<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Festivals;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Festivals\Support\FestivalShared;

/**
 * Nakshatra-based festival interval and window helpers.
 *
 * Structure-only split from FestivalRuleEngine. Algorithms unchanged.
 *
 * @see docs/FESTIVAL_REFACTOR_PARITY_CONTRACT.md
 */
trait FestivalRuleNakshatra
{
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
        $observeNakshatraStartCivilDay = (bool) ($rule['observe_nakshatra_start_civil_day'] ?? false);
        $observeNakshatraEntryCivilDate = (bool) ($rule['observe_nakshatra_entry_civil_date'] ?? false);
        $observeKshayaNakshatraEntryDay = (bool) ($rule['observe_kshaya_nakshatra_entry_day'] ?? false);
        $todayNakshatraStartMatch = $observeNakshatraStartCivilDay && $this->nakshatraStartsInFestivalDay($today, $requiredNakshatra);
        $tomorrowNakshatraStartMatch = $observeNakshatraStartCivilDay && $this->nakshatraStartsInFestivalDay($tomorrow, $requiredNakshatra);
        $todayNakshatraEntryMatch = $observeNakshatraEntryCivilDate && $this->nakshatraStartInFestivalDayJd($today, $requiredNakshatra) !== null;
        $tomorrowNakshatraEntryMatch = $observeNakshatraEntryCivilDate && $this->nakshatraStartInFestivalDayJd($tomorrow, $requiredNakshatra) !== null;
        $todayKshayaNakshatraMatch = $observeKshayaNakshatraEntryDay && $this->nakshatraIsKshayaInFestivalDay($today, $requiredNakshatra);
        $tomorrowKshayaNakshatraMatch = $observeKshayaNakshatraEntryDay && $this->nakshatraIsKshayaInFestivalDay($tomorrow, $requiredNakshatra);

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

        $nakshatraTodayMatch = $nakshatraTodayMatch || $todayNakshatraStartMatch;
        $nakshatraTomorrowMatch = $nakshatraTomorrowMatch || $tomorrowNakshatraStartMatch;
        $nakshatraTodayMatch = $nakshatraTodayMatch || $todayNakshatraEntryMatch;
        $nakshatraTomorrowMatch = $nakshatraTomorrowMatch || $tomorrowNakshatraEntryMatch;
        $nakshatraTodayMatch = $nakshatraTodayMatch || $todayKshayaNakshatraMatch;
        $nakshatraTomorrowMatch = $nakshatraTomorrowMatch || $tomorrowKshayaNakshatraMatch;

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

        if ($observeNakshatraEntryCivilDate) {
            $todayEntryDate = $this->nakshatraEntryCivilDate($today, $requiredNakshatra, $date, $rule);
            if ($nakshatraTodayMatch && $todayEntryDate instanceof CarbonImmutable) {
                return $this->buildNakshatraResult($festivalName, $rule, $todayEntryDate, $karmakalaType, $requiredNakshatra, 'nakshatra_entry_civil_date');
            }

            $tomorrowEntryDate = $this->nakshatraEntryCivilDate($tomorrow, $requiredNakshatra, $date->addDay(), $rule);
            if ($nakshatraTomorrowMatch && $tomorrowEntryDate instanceof CarbonImmutable) {
                return $this->buildNakshatraResult($festivalName, $rule, $tomorrowEntryDate, $karmakalaType, $requiredNakshatra, 'nakshatra_entry_civil_date');
            }

            return null;
        }

        if ($observeKshayaNakshatraEntryDay) {
            if ($todayKshayaNakshatraMatch) {
                return $this->buildNakshatraResult($festivalName, $rule, $date, $karmakalaType, $requiredNakshatra, 'kshaya_nakshatra_entry_day');
            }

            if ($tomorrowKshayaNakshatraMatch) {
                return $this->buildNakshatraResult($festivalName, $rule, $date->addDay(), $karmakalaType, $requiredNakshatra, 'kshaya_nakshatra_entry_day');
            }
        }

        if ($observeNakshatraStartCivilDay) {
            if ($nakshatraTodayMatch && $todayNakshatraStartMatch) {
                return $this->buildNakshatraResult($festivalName, $rule, $date, $karmakalaType, $requiredNakshatra, 'nakshatra_start_civil_day');
            }

            if ($nakshatraTomorrowMatch && $tomorrowNakshatraStartMatch) {
                return $this->buildNakshatraResult($festivalName, $rule, $date->addDay(), $karmakalaType, $requiredNakshatra, 'nakshatra_start_civil_day');
            }

            return null;
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
        $purnimaRequiredMonths = array_values(array_filter(array_map(
            fn ($month): string => $this->normalizeMonthName((string) $month),
            (array) ($rule['purnima_required_months_amanta'] ?? [])
        ), fn (string $value): bool => $value !== ''));
        $amantaMonth = $this->normalizeMonthName((string) (($today['Hindu_Calendar']['Month_Amanta_En'] ?? $today['Hindu_Calendar']['Month_Amanta'] ?? '')));
        if ($purnimaRequiredMonths !== [] && $amantaMonth !== '' && in_array($amantaMonth, $purnimaRequiredMonths, true)) {
            $requiresPurnima = true;
        }

        if ($requiresPurnima) {
            $tithiToday = (array) ($today['Tithi'] ?? []);
            $tithiTomorrow = (array) ($tomorrow['Tithi'] ?? []);
            $pakshaToday = (string) ($tithiToday['paksha'] ?? '');
            $tithiIndexToday = (int) ($tithiToday['index'] ?? 0);
            $pakshaTomorrow = (string) ($tithiTomorrow['paksha'] ?? '');
            $tithiIndexTomorrow = (int) ($tithiTomorrow['index'] ?? 0);

            $allowPurnimaEve = (bool) ($rule['allow_shukla_chaturdashi_purnima_eve'] ?? false);
            $isPurnimaToday = ($pakshaToday === 'Shukla' && $tithiIndexToday === 15)
                || ($allowPurnimaEve && $pakshaToday === 'Shukla' && $tithiIndexToday === 14);
            $isPurnimaTomorrow = ($pakshaTomorrow === 'Shukla' && $tithiIndexTomorrow === 15)
                || ($allowPurnimaEve && $pakshaTomorrow === 'Shukla' && $tithiIndexTomorrow === 14);

            // Both nakshatra AND purnima must match
            if ($nakshatraTodayMatch && $isPurnimaToday) {
                return $this->buildNakshatraResult($festivalName, $rule, $date, $karmakalaType, $requiredNakshatra, 'nakshatra_and_purnima_match', $todayNakshatraWindow);
            }

            if ($nakshatraTomorrowMatch && $isPurnimaTomorrow) {
                return $this->buildNakshatraResult($festivalName, $rule, $date->addDay(), $karmakalaType, $requiredNakshatra, 'nakshatra_and_purnima_match', $tomorrowNakshatraWindow);
            }

            return null;
        }

        // Vriddhi (two sunrises): prefer last day when rule asks for it (Arudra Darshan).
        $vriddhiPreference = (string) ($rule['vriddhi_preference'] ?? '');
        if ($nakshatraTodayMatch && $nakshatraTomorrowMatch && $vriddhiPreference === 'last') {
            $nakshatraTodayMatch = false;
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

    private function nakshatraStartsInFestivalDay(array $details, string $requiredNakshatra): bool
    {
        return $this->nakshatraStartInFestivalDayJd($details, $requiredNakshatra) !== null;
    }

    private function nakshatraEntryCivilDate(array $details, string $requiredNakshatra, CarbonImmutable $festivalDay, array $rule): ?CarbonImmutable
    {
        $ctx = (array) ($details['Resolution_Context'] ?? []);
        if (!isset($ctx['sunrise_jd'], $ctx['sunset_jd'])) {
            return null;
        }

        $start = $this->nakshatraStartInFestivalDayJd($details, $requiredNakshatra);
        if ($start === null) {
            return null;
        }

        $minBeforeSunsetMuhurtas = max(0.0, (float) ($rule['nakshatra_entry_min_before_sunset_muhurtas'] ?? 0.0));
        $dayMuhurtaJd = (((float) $ctx['sunset_jd']) - ((float) $ctx['sunrise_jd'])) / 15.0;
        $sameDayCutoff = ((float) $ctx['sunset_jd']) - ($dayMuhurtaJd * $minBeforeSunsetMuhurtas);

        return $start < $sameDayCutoff - 1e-9 ? $festivalDay : $festivalDay->addDay();
    }

    private function nakshatraIsKshayaInFestivalDay(array $details, string $requiredNakshatra): bool
    {
        $ctx = (array) ($details['Resolution_Context'] ?? []);
        if (!isset($ctx['sunrise_jd'], $ctx['next_sunrise_jd'])) {
            return false;
        }

        $sunrise = (float) $ctx['sunrise_jd'];
        $nextSunrise = (float) $ctx['next_sunrise_jd'];
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
            if ($start !== null && $end !== null && $start >= $sunrise - 1e-9 && $end < $nextSunrise - 1e-9) {
                return true;
            }
        }

        return false;
    }

    private function nakshatraStartInFestivalDayJd(array $details, string $requiredNakshatra): ?float
    {
        $ctx = (array) ($details['Resolution_Context'] ?? []);
        if (!isset($ctx['sunrise_jd'], $ctx['next_sunrise_jd'])) {
            return null;
        }

        $sunrise = (float) $ctx['sunrise_jd'];
        $nextSunrise = (float) $ctx['next_sunrise_jd'];
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
            if ($start !== null && $start >= $sunrise - 1e-9 && $start < $nextSunrise - 1e-9) {
                return $start;
            }
        }

        return null;
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

    /** Resolve canonical nakshatra number (1..27) from a localized/english label. */
    private function resolveNakshatraNumber(string $label): ?int
    {
        return FestivalShared::resolveNakshatraNumber($label);
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

}
