<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Festivals;

use Carbon\CarbonImmutable;

use function chandra_yallop_directed_waxing;
use function chandra_yallop_evaluate_evening;
use function chandra_yallop_passes;

/**
 * Monthly Chandra Darshana hybrid resolver (production source of truth).
 *
 * Spec: docs/Chandra_Darshana.md. Related CLI scripts may lag this trait.
 *
 * - Yallop TN69 q-criterion, default minimum category B;
 * - Danjon guard enabled by default;
 * - SS 10.1 directed waxing ecliptic separation >= 12 degrees at local sunset;
 * - Scan up to 6 post-Amavasya local evenings;
 * - Reject sunrise tithis outside Shukla Pratipada/Dvitiya;
 * - Dharma Sindhu Pratipada early exception: Dvitiya covers either the full
 *   3 daylight muhurtas of Aparahna or the full 6 night muhurtas after sunset
 *   (Sthula-darsana window; internal key still uses "pradosha");
 * - Dvitiya-at-sunrise remains the default path after a failed Pratipada
 *   early exception;
 * - Nirnayamrita kshaya-Pratipada: Day-1 deferral via Amavasya→Dvitiya
 *   sunrise-transition pattern (does not force Day-2 acceptance).
 */
trait FestivalRuleChandraDarshana
{
    private const int CHANDRA_DARSHANA_MAX_POST_AMAVASYA_EVENINGS = 6;

    private const float CHANDRA_DARSHANA_SS10_1_ECLIPTIC_MIN_DEG = 12.0;

    private const float CHANDRA_DARSHANA_DHARMA_SINDHU_APARAHNA_MIN_MUHURTAS = 3.0;

    private const float CHANDRA_DARSHANA_DHARMA_SINDHU_PRADOSHA_MIN_MUHURTAS = 6.0;

    private const string CHANDRA_DARSHANA_YALLOP_MIN_CATEGORY = 'B';

    private const bool CHANDRA_DARSHANA_APPLY_DANJON_GUARD = true;

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
            $day = $this->chandraDarshanaDayBundle($d, $details);
            if ($day === null) {
                continue;
            }

            $abs = (int) $day['tithi_index_abs'];
            if ($abs === 30 && (float) $day['tithi_end_jd'] > 0.0 && (float) $day['tithi_end_jd'] <= $todaySunset + 1e-9) {
                $seasons[sprintf('%.5F', (float) $day['tithi_end_jd'])] = [
                    'amavasya_end_jd' => (float) $day['tithi_end_jd'],
                    'anchor_date' => $d->toDateString(),
                ];
            }

            if ($abs === 1 && (float) $day['tithi_start_jd'] > 0.0 && (float) $day['tithi_start_jd'] <= $todaySunset + 1e-9) {
                $anchor = (float) $day['tithi_start_jd'] < (float) $day['sunrise_jd']
                    ? $d->subDay()->toDateString()
                    : $d->toDateString();
                $seasons[sprintf('%.5F', (float) $day['tithi_start_jd'])] = [
                    'amavasya_end_jd' => (float) $day['tithi_start_jd'],
                    'anchor_date' => $anchor,
                ];
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
            $day = $this->chandraDarshanaDayBundle($date, $details);
            if ($day === null) {
                continue;
            }

            $prevDetails = $fetchHistoricalSnapshot($date->subDay());
            $nextDetails = $fetchHistoricalSnapshot($date->addDay());
            $nextNextDetails = $fetchHistoricalSnapshot($date->addDays(2));
            $prevDay = $this->chandraDarshanaDayBundle($date->subDay(), $prevDetails);
            $nextDay = $this->chandraDarshanaDayBundle($date->addDay(), $nextDetails);
            $nextNextDay = $this->chandraDarshanaDayBundle($date->addDays(2), $nextNextDetails);
            if ($prevDay === null || $nextDay === null || $nextNextDay === null) {
                continue;
            }

            $candidate = $this->evaluateChandraDarshanaEvening(
                $date,
                $day,
                $prevDay,
                $nextDay,
                $nextNextDay,
                $season['amavasya_end_jd'],
                $details
            );
            if ((bool) ($candidate['operational_candidate'] ?? false)) {
                return $candidate;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function chandraDarshanaDayBundle(CarbonImmutable $date, array $details): ?array
    {
        $ctx = (array) ($details['Resolution_Context'] ?? []);
        if ($ctx === []) {
            return null;
        }

        return [
            'date' => $date->toDateString(),
            'details' => $details,
            'tithi_index_abs' => (int) ($ctx['tithi_index_abs'] ?? 0),
            'tithi_start_jd' => (float) ($ctx['tithi_start_jd'] ?? 0.0),
            'tithi_end_jd' => (float) ($ctx['tithi_end_jd'] ?? 0.0),
            'sunrise_jd' => (float) ($ctx['sunrise_jd'] ?? 0.0),
            'sunset_jd' => (float) ($ctx['sunset_jd'] ?? 0.0),
            'next_sunrise_jd' => (float) ($ctx['next_sunrise_jd'] ?? 0.0),
            'moonrise_jd' => $this->extractJd($details['Moonrise_JD'] ?? ($details['Moonrise'] ?? null)),
            'moonset_jd' => $this->extractJd($details['Moonset_JD'] ?? ($details['Moonset'] ?? null)),
            'snapshot_elong_deg' => $this->normalizeDegrees((float) ($ctx['moon_sun_elongation_at_sunset_degrees'] ?? 0.0)),
            'illumination_percent' => (float) ($ctx['moon_illumination_at_sunset_percent'] ?? 0.0),
            'observer_latitude' => (float) ($ctx['observer_latitude'] ?? $details['Observer']['latitude'] ?? 0.0),
            'observer_longitude' => (float) ($ctx['observer_longitude'] ?? $details['Observer']['longitude'] ?? 0.0),
            'observer_elevation_m' => (float) ($ctx['observer_elevation_m'] ?? $details['Observer']['elevation_m'] ?? $details['Observer']['elevation'] ?? 0.0),
        ];
    }

    /**
     * @param array<string, mixed> $day
     * @param array<string, mixed> $prevDay
     * @param array<string, mixed> $nextDay
     * @param array<string, mixed> $details
     *
     * @return array<string, mixed>
     */
    private function evaluateChandraDarshanaEvening(
        CarbonImmutable $date,
        array $day,
        array $prevDay,
        array $nextDay,
        array $nextNextDay,
        float $amavasyaEndJd,
        array $details
    ): array {
        $sunrise = (float) $day['sunrise_jd'];
        $sunset = (float) $day['sunset_jd'];
        $nextSunrise = (float) $day['next_sunrise_jd'];
        $moonset = $day['moonset_jd'];

        if ($sunrise <= 0.0 || $sunset <= $sunrise || $nextSunrise <= $sunset) {
            return $this->chandraDarshanaRejectedCandidate($date, $day, 'REJECTED_MALFORMED_CANDIDATE', 'Required sunrise/sunset context is malformed.', null);
        }

        if ($sunset <= $amavasyaEndJd) {
            return $this->chandraDarshanaRejectedCandidate($date, $day, 'REJECTED_BEFORE_CONJUNCTION', 'Sunset occurred before Amavasya end.', null);
        }

        if ($moonset === null || !is_finite((float) $moonset)) {
            return $this->chandraDarshanaRejectedCandidate($date, $day, 'REJECTED_MOONSET_UNAVAILABLE', 'Moonset JD unavailable.', null);
        }

        $moonsetF = (float) $moonset;
        $lagDays = $moonsetF - $sunset;
        if ($lagDays <= 0.0) {
            return $this->chandraDarshanaRejectedCandidate($date, $day, 'REJECTED_NO_POSITIVE_LAG', 'Moon sets before or simultaneously with sunset.', null);
        }

        $yallop = $this->chandraDarshanaYallopMetrics($details, $sunset, $moonsetF, (float) $day['observer_latitude'], (float) $day['observer_longitude'], (float) $day['observer_elevation_m']);
        if (!(bool) ($yallop['ok'] ?? false)) {
            return $this->chandraDarshanaRejectedCandidate(
                $date,
                $day,
                'REJECTED_MODERN_YALLOP_COMPUTE_FAILED',
                (string) ($yallop['status'] ?? 'Yallop calculation failed'),
                $yallop
            );
        }

        if (($yallop['q'] ?? null) === null || !is_finite((float) $yallop['q'])) {
            return $this->chandraDarshanaRejectedCandidate(
                $date,
                $day,
                'REJECTED_MODERN_YALLOP_NO_Q',
                'Modern Yallop calculation did not return a finite q value.',
                $yallop
            );
        }

        $q = (float) $yallop['q'];
        $belowDanjon = (bool) ($yallop['danjon_guard_condition_met'] ?? false);
        // CHANDRA_DARSHANA_APPLY_DANJON_GUARD is currently always enabled.
        $rejectDanjon = $belowDanjon;
        $passesModernYallop = $this->chandraDarshanaYallopPasses(
            $q,
            self::CHANDRA_DARSHANA_YALLOP_MIN_CATEGORY,
            $rejectDanjon
        ) && (($yallop['is_waxing'] ?? false) === true);

        $sunsetWaxing = $this->chandraDarshanaDirectedWaxingAtSunset($sunset);
        if ($sunsetWaxing['ok'] !== true) {
            return $this->chandraDarshanaRejectedCandidate(
                $date,
                $day,
                'REJECTED_SS10_1_ECLIPTIC_COMPUTE_FAILED',
                'Unable to compute directed Moon-minus-Sun ecliptic separation at local sunset.',
                $yallop
            );
        }

        $waxingSepDeg = $sunsetWaxing['directed_sep_deg'];
        $ss10Passed = $sunsetWaxing['is_waxing']
            && $waxingSepDeg >= self::CHANDRA_DARSHANA_SS10_1_ECLIPTIC_MIN_DEG;

        $classicalGates = $this->evaluateChandraDarshanaClassicalHybridGates(
            $date,
            $day,
            $prevDay,
            $nextDay,
            $nextNextDay
        );
        $ds3AparahnaPassed = (bool) $classicalGates['dharma_sindhu']['ds_3_muhurta_aparahna_passed'];
        $ds6PradoshaPassed = (bool) $classicalGates['dharma_sindhu']['ds_6_muhurta_pradosha_passed'];
        $isKsayaPratipada = (bool) $classicalGates['nirnayamrita']['is_ksaya_pratipada'];
        $tithiAtSunrise = (int) $day['tithi_index_abs'];

        $metrics = array_merge([
            'date' => (string) $day['date'],
            'tithi_index_abs' => $tithiAtSunrise,
            'modern_yallop' => $yallop,
            'ss10_1' => [
                'waxing_ecliptic_separation_deg' => round($waxingSepDeg, 4),
                'threshold_deg' => self::CHANDRA_DARSHANA_SS10_1_ECLIPTIC_MIN_DEG,
                'passed' => $ss10Passed,
                'is_waxing_at_sunset' => $sunsetWaxing['is_waxing'],
                'sun_ecliptic_longitude_deg' => $sunsetWaxing['sun_lon_deg'],
                'moon_ecliptic_longitude_deg' => $sunsetWaxing['moon_lon_deg'],
                'calculation_time_jd' => $sunset,
                'calculation_basis' => 'directed_moon_minus_sun_ecliptic_longitude_at_local_sunset',
            ],
            'dharma_sindhu' => $classicalGates['dharma_sindhu'],
            'nirnayamrita' => $classicalGates['nirnayamrita'],
        ], $yallop);

        if (!$passesModernYallop) {
            return $this->chandraDarshanaRejectedCandidate(
                $date,
                $day,
                $rejectDanjon ? 'REJECTED_DANJON_GUARD' : 'REJECTED_MODERN_YALLOP_Q_BELOW_THRESHOLD',
                sprintf('Modern Yallop q=%.4f (cat %s) failed threshold or Danjon guard.', $q, $yallop['q_category'] ?? '?'),
                $metrics
            );
        }

        if (!$ss10Passed) {
            return $this->chandraDarshanaRejectedCandidate(
                $date,
                $day,
                'REJECTED_SS10_1_BELOW_12_DEG',
                sprintf('SS 10.1 failed: Waxing ecliptic separation %.2f deg < 12.0 deg floor.', $waxingSepDeg),
                $metrics
            );
        }

        if ($isKsayaPratipada) {
            return $this->chandraDarshanaRejectedCandidate(
                $date,
                $day,
                'DEFERRED_NIRNAYAMRITA_KSAYA_PRATIPADA',
                'Nirnayamrita mandate: Kshaya Pratipada detected on Day 1; observation deferred to Day 2.',
                $metrics
            );
        }

        if (!in_array($tithiAtSunrise, [1, 2], true)) {
            return $this->chandraDarshanaRejectedCandidate(
                $date,
                $day,
                'REJECTED_OUTSIDE_PRATIPADA_DVITIYA_FIELD',
                sprintf(
                    'Candidate sunrise tithi %d is outside the permitted Shukla Pratipada/Dvitiya observance field.',
                    $tithiAtSunrise
                ),
                $metrics
            );
        }

        $pratipadaEarlyExceptionPassed = $ds3AparahnaPassed || $ds6PradoshaPassed;
        if ($tithiAtSunrise === 1 && !$pratipadaEarlyExceptionPassed) {
            return $this->chandraDarshanaRejectedCandidate(
                $date,
                $day,
                'DEFERRED_PRATIPADA_EARLY_EXCEPTION_NOT_MET',
                'Pratipada early Chandra Darshana exception not met; continue to the Dvitiya civil-day default path.',
                $metrics
            );
        }

        $statusCode = $tithiAtSunrise === 1
            ? 'SUCCESS_PRATIPADA_EARLY_EXCEPTION'
            : ($pratipadaEarlyExceptionPassed
                ? 'SUCCESS_DVITIYA_DEFAULT_WITH_DHARMA_SINDHU_CORROBORATION'
                : 'SUCCESS_DVITIYA_DEFAULT_MODERN_SS10_ONLY');

        return $this->chandraDarshanaAcceptedCandidate($date, $day, $statusCode, sprintf(
            'Hybrid Engine Success: Modern Yallop q=%.4f (cat %s) + SS 10.1 (%.2f deg >= 12 deg). Tithi at sunrise=%d. Pratipada early-exception/Dvitiya corroboration: Aparahna=%s, Pradosha=%s.',
            $q,
            $yallop['q_category'] ?? '?',
            $waxingSepDeg,
            $tithiAtSunrise,
            $ds3AparahnaPassed ? 'YES' : 'NO',
            $ds6PradoshaPassed ? 'YES' : 'NO'
        ), $metrics);
    }

    /** @return array<string, mixed> */
    private function chandraDarshanaYallopMetrics(
        array $details,
        float $sunsetJd,
        float $moonsetJd,
        float $lat,
        float $lon,
        float $elevationM
    ): array {
        $ctx = (array) ($details['Resolution_Context'] ?? []);
        if (isset($ctx['chandra_yallop']) && is_array($ctx['chandra_yallop'])) {
            return $ctx['chandra_yallop'];
        }

        if (!function_exists('chandra_yallop_evaluate_evening')) {
            require_once dirname(__DIR__, 2) . '/scripts/lib/chandra_yallop.php';
        }

        return chandra_yallop_evaluate_evening($this->transitEngine->jme(), $sunsetJd, $moonsetJd, $lat, $lon, $elevationM);
    }

    private function chandraDarshanaYallopPasses(float $q, string $minCategory, bool $danjonRejected): bool
    {
        if (!function_exists('chandra_yallop_passes')) {
            require_once dirname(__DIR__, 2) . '/scripts/lib/chandra_yallop.php';
        }

        return chandra_yallop_passes($q, $minCategory, $danjonRejected);
    }

    /** @return array{directed_sep_deg: float, is_waxing: bool, sun_lon_deg: float, moon_lon_deg: float, ok: bool} */
    private function chandraDarshanaDirectedWaxingAtSunset(float $sunsetJd): array
    {
        if (!function_exists('chandra_yallop_directed_waxing')) {
            require_once dirname(__DIR__, 2) . '/scripts/lib/chandra_yallop.php';
        }

        return chandra_yallop_directed_waxing($this->transitEngine->jme(), $sunsetJd);
    }

    /**
     * @param array<string, mixed> $day
     * @param array<string, mixed> $nextDay
     *
     * @return array{start_jd: float, end_jd: float}
     */
    private function chandraDarshanaDvitiyaInterval(
        array $day,
        array $nextDay,
        array $nextNextDay
    ): array {
        $absTithi = (int) $day['tithi_index_abs'];

        if ($absTithi === 2) {
            return [
                'start_jd' => (float) $day['tithi_start_jd'],
                'end_jd' => (float) $day['tithi_end_jd'],
            ];
        }

        if ($absTithi === 1) {
            $dvitiyaStartJd = (float) $day['tithi_end_jd'];
            $nextAbs = (int) $nextDay['tithi_index_abs'];

            if ($nextAbs === 2) {
                return [
                    'start_jd' => $dvitiyaStartJd,
                    'end_jd' => (float) $nextDay['tithi_end_jd'],
                ];
            }

            if ($nextAbs === 1 && (int) $nextNextDay['tithi_index_abs'] === 2) {
                return [
                    'start_jd' => (float) $nextDay['tithi_end_jd'],
                    'end_jd' => (float) $nextNextDay['tithi_end_jd'],
                ];
            }

            return ['start_jd' => 0.0, 'end_jd' => 0.0];
        }

        if ($absTithi === 30) {
            $nextAbs = (int) $nextDay['tithi_index_abs'];

            if ($nextAbs === 2) {
                return [
                    'start_jd' => (float) $nextDay['tithi_start_jd'],
                    'end_jd' => (float) $nextDay['tithi_end_jd'],
                ];
            }

            if ($nextAbs === 1 && (int) $nextNextDay['tithi_index_abs'] === 2) {
                return [
                    'start_jd' => (float) $nextDay['tithi_end_jd'],
                    'end_jd' => (float) $nextNextDay['tithi_end_jd'],
                ];
            }
        }

        return ['start_jd' => 0.0, 'end_jd' => 0.0];
    }

    /**
     * @param array<string, mixed> $day
     * @param array<string, mixed> $prevDay
     * @param array<string, mixed> $nextDay
     *
     * @return array<string, array<string, mixed>>
     */
    private function evaluateChandraDarshanaClassicalHybridGates(
        CarbonImmutable $date,
        array $day,
        array $prevDay,
        array $nextDay,
        array $nextNextDay
    ): array {
        unset($date, $prevDay);

        $sunrise = (float) $day['sunrise_jd'];
        $sunset = (float) $day['sunset_jd'];
        $nextSunrise = (float) $day['next_sunrise_jd'];

        $daylightDuration = $sunset - $sunrise;
        $dayMuhurta = $daylightDuration > 0.0 ? $daylightDuration / 15.0 : 0.0;
        $nightDuration = $nextSunrise - $sunset;
        $nightMuhurta = $nightDuration > 0.0 ? $nightDuration / 15.0 : 0.0;

        $aparahnaStart = $sunrise + (9.0 * $dayMuhurta);
        $aparahnaEnd = $sunrise + (12.0 * $dayMuhurta);

        $dvitiya = $this->chandraDarshanaDvitiyaInterval($day, $nextDay, $nextNextDay);
        $dvitiyaStartJd = $dvitiya['start_jd'];
        $dvitiyaEndJd = $dvitiya['end_jd'];

        $aparahnaOverlapJd = 0.0;
        if ($dvitiyaStartJd > 0.0 && $dvitiyaEndJd > $dvitiyaStartJd) {
            $aparahnaOverlapJd = max(0.0, min($aparahnaEnd, $dvitiyaEndJd) - max($aparahnaStart, $dvitiyaStartJd));
        }

        $aparahnaMuhurtas = $dayMuhurta > 0.0 ? $aparahnaOverlapJd / $dayMuhurta : 0.0;
        $ds3MuhurtaAparahnaPassed = $aparahnaMuhurtas >= self::CHANDRA_DARSHANA_DHARMA_SINDHU_APARAHNA_MIN_MUHURTAS - 1e-6;

        $pradoshaStart = $sunset;
        $pradoshaEnd = $sunset + (6.0 * $nightMuhurta);
        $pradoshaOverlapJd = 0.0;
        if ($dvitiyaStartJd > 0.0 && $dvitiyaEndJd > $dvitiyaStartJd) {
            $pradoshaOverlapJd = max(0.0, min($pradoshaEnd, $dvitiyaEndJd) - max($pradoshaStart, $dvitiyaStartJd));
        }

        $pradoshaMuhurtas = $nightMuhurta > 0.0 ? $pradoshaOverlapJd / $nightMuhurta : 0.0;
        $ds6MuhurtaPradoshaPassed = $pradoshaMuhurtas >= self::CHANDRA_DARSHANA_DHARMA_SINDHU_PRADOSHA_MIN_MUHURTAS - 1e-6;

        $absTithi = (int) $day['tithi_index_abs'];
        $nextAbsTithi = (int) $nextDay['tithi_index_abs'];
        $isKsayaPratipada = $absTithi === 30
            && $nextAbsTithi === 2
            && (float) $day['tithi_end_jd'] > $sunrise
            && (float) $day['tithi_end_jd'] < $nextSunrise;

        return [
            'dharma_sindhu' => [
                'dvitiya_start_jd' => $dvitiyaStartJd,
                'dvitiya_end_jd' => $dvitiyaEndJd,
                'dvitiya_interval_available' => $dvitiyaStartJd > 0.0 && $dvitiyaEndJd > $dvitiyaStartJd,
                'aparahna_start_jd' => $aparahnaStart,
                'aparahna_end_jd' => $aparahnaEnd,
                'aparahna_dvitiya_muhurtas' => round($aparahnaMuhurtas, 4),
                'ds_3_muhurta_aparahna_passed' => $ds3MuhurtaAparahnaPassed,
                'pradosha_start_jd' => $pradoshaStart,
                'pradosha_end_jd' => $pradoshaEnd,
                'pradosha_dvitiya_muhurtas' => round($pradoshaMuhurtas, 4),
                'ds_6_muhurta_pradosha_passed' => $ds6MuhurtaPradoshaPassed,
            ],
            'nirnayamrita' => [
                'is_ksaya_pratipada' => $isKsayaPratipada,
                'day1_sunrise_tithi_abs' => $absTithi,
                'day2_sunrise_tithi_abs' => $nextAbsTithi,
                'day2_deferral_enforced' => $isKsayaPratipada,
            ],
        ];
    }

    /** @param array<string, mixed> $day */
    private function chandraDarshanaRejectedCandidate(CarbonImmutable $date, array $day, string $statusCode, string $reason, ?array $metrics): array
    {
        return $this->chandraDarshanaCandidate($date, $day, false, $statusCode, $reason, $metrics);
    }

    /** @param array<string, mixed> $day */
    private function chandraDarshanaAcceptedCandidate(CarbonImmutable $date, array $day, string $statusCode, string $reason, array $metrics): array
    {
        return $this->chandraDarshanaCandidate($date, $day, true, $statusCode, $reason, $metrics);
    }

    /** @param array<string, mixed> $day */
    private function chandraDarshanaCandidate(CarbonImmutable $date, array $day, bool $accepted, string $statusCode, string $reason, ?array $metrics): array
    {
        $sunrise = (float) $day['sunrise_jd'];
        $sunset = (float) $day['sunset_jd'];
        $moonset = is_numeric($day['moonset_jd']) ? (float) $day['moonset_jd'] : $sunset;
        $metricsArray = $metrics ?? [];
        $dharmaSindhu = is_array($metricsArray['dharma_sindhu'] ?? null)
            ? $metricsArray['dharma_sindhu']
            : [];
        $dvitiyaStartJd = (float) ($dharmaSindhu['dvitiya_start_jd'] ?? 0.0);
        $dvitiyaEndJd = (float) ($dharmaSindhu['dvitiya_end_jd'] ?? 0.0);
        $hasExactDvitiyaInterval = $dvitiyaStartJd > 0.0 && $dvitiyaEndJd > $dvitiyaStartJd;

        $targetTithi = $accepted && $hasExactDvitiyaInterval
            ? 2
            : (int) $day['tithi_index_abs'];
        $targetInterval = $accepted && $hasExactDvitiyaInterval
            ? ['start_jd' => $dvitiyaStartJd, 'end_jd' => $dvitiyaEndJd]
            : [
                'start_jd' => (float) $day['tithi_start_jd'],
                'end_jd' => (float) $day['tithi_end_jd'],
            ];
        $visibilityWindow = [
            'start_jd' => $sunset,
            'end_jd' => max($sunset, $moonset),
        ];
        $moonVisibilitySeconds = max(0.0, ($visibilityWindow['end_jd'] - $visibilityWindow['start_jd']) * 86400.0);
        $targetWindowOverlapSeconds = $this->intervalOverlapSeconds($targetInterval, $visibilityWindow);
        $daylightOverlapSeconds = $this->intervalOverlapSeconds($targetInterval, ['start_jd' => $sunrise, 'end_jd' => $sunset]);
        $classification = $statusCode;
        $reasonKey = $this->chandraDarshanaReasonForStatus($statusCode);

        return [
            'date' => $date->toDateString(),
            'day_offset' => 0,
            'required_tithi' => $targetTithi,
            'target_interval' => $targetInterval,
            'visibility_window' => $visibilityWindow,
            'target_at_sunrise' => $this->isTargetAtPoint($sunrise, $targetInterval),
            'target_at_karmakala' => $targetWindowOverlapSeconds > 0.0,
            'target_window_overlap_seconds' => $targetWindowOverlapSeconds,
            'target_daylight_overlap_seconds' => $daylightOverlapSeconds,
            'moon_visibility_seconds' => $moonVisibilitySeconds,
            'classification' => $classification,
            'operational_candidate' => $accepted,
            'reason' => $reasonKey,
            'visibility_assessment' => [
                'model' => 'chandra_darshana_hybrid_engine',
                'description' => 'chandra_darshana_hybrid_engine_description',
                'operational_candidate' => $accepted,
                'actual_observation' => 'UNKNOWN',
                'classification' => $classification,
                'summary_classification' => $classification,
                'status_code' => $statusCode,
                // Public reason is a localization key; keep English diagnostic prose under reason_detail.
                'reason' => $reasonKey,
                'reason_detail' => $reason,
                'selection_mode' => $accepted ? 'HYBRID_MODERN_PLUS_CLASSICAL' : null,
                'strict_source_only_result' => 'MONTHLY_DATE_TEXTUALLY_UNDETERMINED',
                'date_selection_basis' => 'hybrid_scripture_modern_first_crescent',
                'date_selection_is_explicit_monthly_scriptural_rule' => false,
                'gates_included' => [
                    'modern_layer' => 'chandra_darshana_gate_modern_yallop_tn69',
                    'danjon_guard' => self::CHANDRA_DARSHANA_APPLY_DANJON_GUARD,
                    'ss10_1' => 'chandra_darshana_gate_ss10_1',
                    'dharma_sindhu_aparahna' => 'chandra_darshana_gate_dharma_sindhu_aparahna',
                    'dharma_sindhu_pradosha' => 'chandra_darshana_gate_dharma_sindhu_pradosha',
                    'nirnayamrita' => 'chandra_darshana_gate_nirnayamrita_kshaya',
                ],
                'metrics' => $metricsArray,
                'modern_yallop' => is_array($metricsArray['modern_yallop'] ?? null) ? $metricsArray['modern_yallop'] : $metricsArray,
                'ss10_1' => $metricsArray['ss10_1'] ?? null,
                'dharma_sindhu' => $metricsArray['dharma_sindhu'] ?? null,
                'nirnayamrita' => $metricsArray['nirnayamrita'] ?? null,
                'moonset_lag_seconds' => $moonVisibilitySeconds,
                'moonset_lag_minutes' => $moonVisibilitySeconds / 60.0,
                'illumination_percent' => (float) $day['illumination_percent'],
                'observer' => [
                    'latitude' => (float) $day['observer_latitude'],
                    'longitude' => (float) $day['observer_longitude'],
                    'elevation_m' => (float) $day['observer_elevation_m'],
                ],
            ],
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
            'karmakala_type' => (string) ($rule['karmakala_type'] ?? 'chandra_darshana_visibility'),
            'tithi_at_karmakala_today' => $overlapSeconds > 0.0,
            'tithi_at_karmakala_tomorrow' => false,
            'tithi_coverage_seconds_today' => $overlapSeconds,
            'tithi_coverage_seconds_tomorrow' => 0.0,
            'tithi_at_sunrise_today' => (bool) $winner['target_at_sunrise'],
            'tithi_at_sunrise_tomorrow' => false,
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

    private function chandraDarshanaReasonForStatus(string $statusCode): string
    {
        return match ($statusCode) {
            'SUCCESS_PRATIPADA_EARLY_EXCEPTION' => 'chandra_darshana_success_pratipada_early_exception',
            'SUCCESS_DVITIYA_DEFAULT_WITH_DHARMA_SINDHU_CORROBORATION' => 'chandra_darshana_success_dvitiya_with_dharma_sindhu_corroboration',
            'SUCCESS_DVITIYA_DEFAULT_MODERN_SS10_ONLY' => 'chandra_darshana_success_dvitiya_modern_ss10_only',
            'SUCCESS_HYBRID_RESOLVED_MODERN_SS10_ONLY' => 'chandra_darshana_success_hybrid_modern_ss10_only',
            default => 'chandra_darshana_hybrid_candidate_rejected',
        };
    }

    private function normalizeDegrees(float $degrees): float
    {
        $d = fmod($degrees, 360.0);
        if ($d < 0.0) {
            $d += 360.0;
        }

        return $d;
    }

    private function moonVisibleAfterSunset(array $details): bool
    {
        $ctx = (array) ($details['Resolution_Context'] ?? []);
        $sunsetJd = (float) ($ctx['sunset_jd'] ?? 0.0);
        $panchanga = (array) ($details['Panchanga'] ?? []);
        $moonsetJd = $this->extractJd($details['Moonset_JD'] ?? ($details['Moonset'] ?? ($panchanga['Moonset'] ?? null)));

        return $sunsetJd > 0.0
            && $moonsetJd !== null
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
