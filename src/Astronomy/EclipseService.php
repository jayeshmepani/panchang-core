<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Astronomy;

use Carbon\CarbonImmutable;
use FFI\CData;
use JayeshMepani\PanchangCore\Astronomy\Concerns\ConfiguresEphemeris;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Core\Localization;
use JmeEph\FFI\JmeEphFFI;

class EclipseService
{
    use ConfiguresEphemeris;

    private const float NIRNAY_LUNAR_ECLIPSE_MINIMUM_MAGNITUDE = 1.0 / 16.0;

    private const float NIRNAY_SOLAR_ECLIPSE_MINIMUM_MAGNITUDE = 1.0 / 12.0;

    /** Half-ghati ritual duration floor: "less than" 12 minutes is not observed; exactly 12 minutes is valid. */
    private const float NIRNAY_MINIMUM_VISIBLE_DURATION_DAYS = 12.0 / 1440.0;

    /**
     * JME-native lunar tret indices from jpl-ephemeris `src/events.c`
     * (`jme_lun_eclipse_when` / `jme_lun_eclipse_when_loc`). Not Swiss Ephemeris.
     *
     * Global when:
     *   tret[0]=maximum, [1]=maximum,
     *   [2]/[3]=penumbral begin/end, [4]/[5]=partial (umbral) begin/end,
     *   [6]/[7]=total begin/end.
     * when_loc additionally clips phase contacts to the Moon-above-horizon window and sets:
     *   tret[8]=local visibility start, tret[9]=local visibility end.
     */
    private const int JME_LUNAR_TRET_PENUMBRAL_BEGIN = 2;

    private const int JME_LUNAR_TRET_PENUMBRAL_END = 3;

    private const int JME_LUNAR_TRET_PARTIAL_BEGIN = 4;

    private const int JME_LUNAR_TRET_PARTIAL_END = 5;

    private const int JME_LUNAR_TRET_TOTAL_BEGIN = 6;

    private const int JME_LUNAR_TRET_TOTAL_END = 7;

    private const int JME_LUNAR_TRET_LOCAL_VISIBILITY_START = 8;

    private const int JME_LUNAR_TRET_LOCAL_VISIBILITY_END = 9;

    /**
     * JME-native local attr[8] visibility marker (`JME_ECLIPSE_VISIBLE` or 0).
     * Set in `jme_lun_eclipse_when_loc` / solar how/when_loc. Magnitude is attr[0].
     */
    private const int JME_LOCAL_ATTR_VISIBILITY = 8;

    /**
     * JME-native solar attr from `jme_sol_eclipse_how` / `jme_sol_eclipse_when_loc`
     * (jpl-ephemeris `src/events.c`). Not Swiss Ephemeris.
     *
     *   attr[0] = magnitude = (sun_r + moon_r - sep) / (2 * sun_r)
     *   attr[1] = 1.0 (total) | (moon_r/sun_r)^2 (annular/hybrid) | same as [0] (partial)
     *   attr[2] = centre separation (degrees)
     *   attr[3] = Sun diameter (arcseconds) = 2 * sun_r * 3600
     *   attr[4] = Moon diameter (arcseconds) = 2 * moon_r * 3600
     *   attr[5] = sun altitude (deg)
     *   attr[6] = moon altitude (deg)
     *   attr[7] = central flag (sep <= |sun_r - moon_r|)
     *   attr[8] = JME_ECLIPSE_VISIBLE
     *
     * Obscuration (area fraction) is not a native field — derive via circle overlap
     * from attr[2..4]. That matches timeanddate-style “% of solar disc covered”.
     */
    private const int JME_SOLAR_ATTR_CENTRE_SEPARATION_DEG = 2;

    private const int JME_SOLAR_ATTR_SUN_DIAMETER_ARCSEC = 3;

    private const int JME_SOLAR_ATTR_MOON_DIAMETER_ARCSEC = 4;

    private readonly SunService $sunService;

    public function __construct(private JmeEphFFI $jme, ?SunService $sunService = null)
    {
        $this->initializeEphemerisPath($this->jme);
        $this->sunService = $sunService ?? new SunService($jme);
    }

    /**
     * Configure service (optional, for standalone usage).
     *
     * @param string $ephePath Ephemeris path (empty for default)
     */
    public static function configure(string $ephePath = ''): void
    {
        self::setEphemerisPath($ephePath);
    }

    public function getEclipsesForYear(int $year, float $lat, float $lon, string $tz): array
    {
        $start = $this->jme->jme_julian_day($year, 1, 1, 0.0, JmeEphFFI::JME_CALENDAR_GREGORIAN);
        $end = $this->jme->jme_julian_day($year + 1, 1, 1, 0.0, JmeEphFFI::JME_CALENDAR_GREGORIAN);

        return $this->getEclipsesForJdRange($start, $end, $lat, $lon, $tz);
    }

    public function getEclipsesForDateRange(CarbonImmutable $start, CarbonImmutable $endExclusive, float $lat, float $lon, string $tz): array
    {
        $startUtc = $start->setTimezone('UTC');
        $endUtc = $endExclusive->setTimezone('UTC');

        $startJd = $this->jme->jme_julian_day(
            $startUtc->year,
            $startUtc->month,
            $startUtc->day,
            ((int) $startUtc->format('H')) + ((int) $startUtc->format('i')) / 60.0 + (((int) $startUtc->format('s')) + ((int) $startUtc->format('u') / 1_000_000)) / 3600.0,
            JmeEphFFI::JME_CALENDAR_GREGORIAN
        );
        $endJd = $this->jme->jme_julian_day(
            $endUtc->year,
            $endUtc->month,
            $endUtc->day,
            ((int) $endUtc->format('H')) + ((int) $endUtc->format('i')) / 60.0 + (((int) $endUtc->format('s')) + ((int) $endUtc->format('u') / 1_000_000)) / 3600.0,
            JmeEphFFI::JME_CALENDAR_GREGORIAN
        );

        return $this->getEclipsesForJdRange($startJd, $endJd, $lat, $lon, $tz);
    }

    private function getEclipsesForJdRange(float $start, float $end, float $lat, float $lon, string $tz): array
    {
        if ($end <= $start) {
            return [];
        }

        $events = [];
        $seen = [];

        foreach (['lunar', 'solar'] as $series) {
            $cursor = $start - 1e-6;
            $maxIterations = 2000;
            $iteration = 0;

            while ($cursor < $end && $iteration < $maxIterations) {
                $iteration++;
                $pick = $series === 'lunar'
                    ? $this->nextLunarEclipse($cursor, $lat, $lon, $tz)
                    : $this->nextSolarEclipse($cursor, $lat, $lon, $tz);

                if (!is_array($pick)) {
                    $cursor += 1.0;
                    continue;
                }

                if (($pick['jd'] ?? $end + 1.0) >= $end) {
                    break;
                }

                if ((float) $pick['jd'] <= $cursor) {
                    $cursor += 1.0;
                    continue;
                }

                $hash = strtolower((string) $pick['type']) . ':' . number_format((float) $pick['jd'], 6, '.', '');
                if (!isset($seen[$hash])) {
                    $events[] = $pick;
                    $seen[$hash] = true;
                }

                $cursor = (float) $pick['jd'] + 0.01;
            }
        }

        usort($events, static fn (array $a, array $b): int => $a['jd'] <=> $b['jd']);

        return $events;
    }

    private function nextLunarEclipse(float $startJd, float $lat, float $lon, string $tz): ?array
    {
        $tret = $this->jme->getFFI()->new('double[10]');
        $serr = $this->jme->getFFI()->new('char[256]');

        $retFlag = $this->jme->jme_lun_eclipse_when(
            $startJd,
            JmeEphFFI::JME_CALC_HIGH_PRECISION,
            0,
            $tret,
            0,
            $serr
        );

        if ($retFlag <= 0 || $tret[0] <= $startJd) {
            return null;
        }

        return $this->buildLunarEvent($tret, $retFlag, $lat, $lon, $tz);
    }

    private function nextSolarEclipse(float $startJd, float $lat, float $lon, string $tz): ?array
    {
        $tret = $this->jme->getFFI()->new('double[10]');
        $serr = $this->jme->getFFI()->new('char[256]');

        $retFlag = $this->jme->jme_sol_eclipse_when_glob(
            $startJd,
            JmeEphFFI::JME_CALC_HIGH_PRECISION,
            0,
            $tret,
            0,
            $serr
        );

        if ($retFlag <= 0 || $tret[0] <= $startJd) {
            return null;
        }

        return $this->buildSolarEvent($tret, $retFlag, $lat, $lon, $tz);
    }

    private function buildLunarEvent(CData $globalTret, int $retFlag, float $lat, float $lon, string $tz): array
    {
        $geo = $this->newGeoPos($lat, $lon);
        $serr = $this->jme->getFFI()->new('char[256]');

        $attr = $this->jme->getFFI()->new('double[40]');
        $jdMax = (float) $globalTret[0];
        $retHow = $this->jme->jme_lun_eclipse_how($jdMax, JmeEphFFI::JME_CALC_HIGH_PRECISION, $geo, $attr, $serr);

        $tretLoc = $this->jme->getFFI()->new('double[10]');
        $attrLoc = $this->jme->getFFI()->new('double[20]');
        $retLoc = $this->jme->jme_lun_eclipse_when_loc($jdMax - 1.0, JmeEphFFI::JME_CALC_HIGH_PRECISION, $geo, $tretLoc, $attrLoc, 0, $serr);
        $contactsFromSameEvent = $retLoc > 0 && $tretLoc[0] > 0 && abs((float) $tretLoc[0] - $jdMax) < 0.5;
        $globalContacts = [
            'penumbral_begin_jd' => $globalTret[self::JME_LUNAR_TRET_PENUMBRAL_BEGIN] > 0 ? (float) $globalTret[self::JME_LUNAR_TRET_PENUMBRAL_BEGIN] : null,
            'partial_begin_jd' => $globalTret[self::JME_LUNAR_TRET_PARTIAL_BEGIN] > 0 ? (float) $globalTret[self::JME_LUNAR_TRET_PARTIAL_BEGIN] : null,
            'total_begin_jd' => $globalTret[self::JME_LUNAR_TRET_TOTAL_BEGIN] > 0 ? (float) $globalTret[self::JME_LUNAR_TRET_TOTAL_BEGIN] : null,
            'maximum_jd' => $jdMax,
            'total_end_jd' => $globalTret[self::JME_LUNAR_TRET_TOTAL_END] > 0 ? (float) $globalTret[self::JME_LUNAR_TRET_TOTAL_END] : null,
            'partial_end_jd' => $globalTret[self::JME_LUNAR_TRET_PARTIAL_END] > 0 ? (float) $globalTret[self::JME_LUNAR_TRET_PARTIAL_END] : null,
            'penumbral_end_jd' => $globalTret[self::JME_LUNAR_TRET_PENUMBRAL_END] > 0 ? (float) $globalTret[self::JME_LUNAR_TRET_PENUMBRAL_END] : null,
        ];
        $localContacts = [
            'penumbral_begin_jd' => $contactsFromSameEvent && $tretLoc[self::JME_LUNAR_TRET_PENUMBRAL_BEGIN] > 0 ? (float) $tretLoc[self::JME_LUNAR_TRET_PENUMBRAL_BEGIN] : null,
            'partial_begin_jd' => $contactsFromSameEvent && $tretLoc[self::JME_LUNAR_TRET_PARTIAL_BEGIN] > 0 ? (float) $tretLoc[self::JME_LUNAR_TRET_PARTIAL_BEGIN] : null,
            'total_begin_jd' => $contactsFromSameEvent && $tretLoc[self::JME_LUNAR_TRET_TOTAL_BEGIN] > 0 ? (float) $tretLoc[self::JME_LUNAR_TRET_TOTAL_BEGIN] : null,
            'total_end_jd' => $contactsFromSameEvent && $tretLoc[self::JME_LUNAR_TRET_TOTAL_END] > 0 ? (float) $tretLoc[self::JME_LUNAR_TRET_TOTAL_END] : null,
            'partial_end_jd' => $contactsFromSameEvent && $tretLoc[self::JME_LUNAR_TRET_PARTIAL_END] > 0 ? (float) $tretLoc[self::JME_LUNAR_TRET_PARTIAL_END] : null,
            'penumbral_end_jd' => $contactsFromSameEvent && $tretLoc[self::JME_LUNAR_TRET_PENUMBRAL_END] > 0 ? (float) $tretLoc[self::JME_LUNAR_TRET_PENUMBRAL_END] : null,
        ];
        $visibilityStartJd = $contactsFromSameEvent && $tretLoc[self::JME_LUNAR_TRET_LOCAL_VISIBILITY_START] > 0
            ? (float) $tretLoc[self::JME_LUNAR_TRET_LOCAL_VISIBILITY_START]
            : null;
        $visibilityEndJd = $contactsFromSameEvent && $tretLoc[self::JME_LUNAR_TRET_LOCAL_VISIBILITY_END] > 0
            ? (float) $tretLoc[self::JME_LUNAR_TRET_LOCAL_VISIBILITY_END]
            : null;

        // Astronomical umbral phase uses global contacts (local partial_end can be truncated at moonset).
        $astronomicalPartialBeginJd = $globalContacts['partial_begin_jd'];
        $astronomicalPartialEndJd = $globalContacts['partial_end_jd'];
        $astronomicalMokshaJd = $astronomicalPartialEndJd;

        $hasLocalPartialWindow = $localContacts['partial_begin_jd'] !== null
            && $localContacts['partial_end_jd'] !== null
            && $localContacts['partial_end_jd'] > $localContacts['partial_begin_jd'];
        $hasLocalTotalWindow = $localContacts['total_begin_jd'] !== null
            && $localContacts['total_end_jd'] !== null
            && $localContacts['total_end_jd'] > $localContacts['total_begin_jd'];

        $globalType = $this->lunarTypeFromCode($retFlag);
        $localType = null;
        if ($hasLocalTotalWindow) {
            $localType = 'Total';
        } elseif ($hasLocalPartialWindow) {
            $localType = 'Partial';
        } elseif ($contactsFromSameEvent && $visibilityStartJd !== null && $visibilityEndJd !== null) {
            $localType = 'Penumbral';
        }

        $dt = $this->jdToCarbon($jdMax, $tz);

        $hasRitualPhase = $hasLocalPartialWindow || $hasLocalTotalWindow;
        $hasNativeVisibilityWindow = $visibilityStartJd !== null && $visibilityEndJd !== null && $visibilityEndJd > $visibilityStartJd;
        // JME-native: attrLoc[8] is visibility enum (JME_ECLIPSE_VISIBLE), attr[0] is umbral magnitude.
        $jmeVisibilityFlag = $contactsFromSameEvent
            && (int) $attrLoc[self::JME_LOCAL_ATTR_VISIBILITY] === JmeEphFFI::JME_ECLIPSE_VISIBLE;
        $astroVisible = $contactsFromSameEvent && ($jmeVisibilityFlag || $hasNativeVisibilityWindow || $hasRitualPhase);
        $meetsRitualMagnitude = (float) $attr[0] >= self::NIRNAY_LUNAR_ECLIPSE_MINIMUM_MAGNITUDE;

        // Naked-eye local visibility = umbral phase while the Moon is actually above the horizon.
        // Prefer SunService moonrise/moonset (literal sky horizon). JME when_loc tret[8]/[9]
        // is only a fallback if rise/set does not land inside the umbral phase.
        $sunHorizon = $this->resolveLunarHorizonEventsNear(
            $astronomicalPartialBeginJd ?? $jdMax,
            $astronomicalPartialEndJd ?? $jdMax,
            $lat,
            $lon,
            $tz
        );
        // Also search moonrise/moonset that may fall just outside partial but still
        // clip the visible window (e.g. moonset during partial is the critical case).
        $sunHorizonWide = $this->resolveLunarHorizonEventsNear(
            ($astronomicalPartialBeginJd ?? $jdMax) - 0.5,
            ($astronomicalPartialEndJd ?? $jdMax) + 0.5,
            $lat,
            $lon,
            $tz
        );
        $ritualMoonriseJd = $this->resolveRitualLunarHorizonJd(
            $astronomicalPartialBeginJd,
            $astronomicalPartialEndJd,
            $visibilityStartJd,
            $sunHorizon['moonrise_jd'] ?? $sunHorizonWide['moonrise_jd']
        );
        $ritualMoonsetJd = $this->resolveRitualLunarHorizonJd(
            $astronomicalPartialBeginJd,
            $astronomicalPartialEndJd,
            $visibilityEndJd,
            $sunHorizon['moonset_jd'] ?? $sunHorizonWide['moonset_jd']
        );

        // Local ritual window = umbral (partial) ∩ same literal horizon markers.
        $ritualVisibleStartJd = $this->maxJd($astronomicalPartialBeginJd, $ritualMoonriseJd ?? $visibilityStartJd);
        $ritualVisibleEndJd = $this->minJd($astronomicalPartialEndJd, $ritualMoonsetJd ?? $visibilityEndJd);
        $hasValidRitualIntersection = $ritualVisibleStartJd !== null
            && $ritualVisibleEndJd !== null
            && $ritualVisibleEndJd > $ritualVisibleStartJd;
        if (!$hasValidRitualIntersection) {
            $ritualVisibleStartJd = null;
            $ritualVisibleEndJd = null;
        }

        $visibleDuration = $hasValidRitualIntersection ? $ritualVisibleEndJd - $ritualVisibleStartJd : 0.0;
        $meetsDurationThreshold = $visibleDuration >= self::NIRNAY_MINIMUM_VISIBLE_DURATION_DAYS;

        // Sequential enum codes — use equality, never bitwise flags.
        $isPenumbralOnly = $this->isLunarPenumbralOnly($retHow);

        $isVisible = $contactsFromSameEvent
            && $astroVisible
            && $hasRitualPhase
            && $hasValidRitualIntersection
            && $meetsRitualMagnitude
            && $meetsDurationThreshold
            && !$isPenumbralOnly;

        $ritualReasonKey = $this->resolveRitualReasonKey(
            $isVisible,
            $contactsFromSameEvent && $astroVisible,
            $isPenumbralOnly,
            $hasRitualPhase,
            $hasValidRitualIntersection,
            $meetsRitualMagnitude,
            $meetsDurationThreshold
        );

        // Vedh/sutak always from sparsha (partial begin), never from grastodaya moonrise.
        // Ends at astronomical moksha (partial end), even if moonset cut local visibility earlier.
        $sutakStartAnchor = $astronomicalPartialBeginJd ?? $ritualVisibleStartJd;
        $sutakEndAnchor = $astronomicalMokshaJd ?? $ritualVisibleEndJd;

        $ritualBoundary = $this->buildRitualBoundaryPayload(
            'Lunar',
            $ritualVisibleStartJd,
            $ritualVisibleEndJd,
            $lat,
            $lon,
            $tz,
            $isVisible,
            $astronomicalPartialBeginJd,
            $astronomicalPartialEndJd,
            $ritualMoonriseJd,
            $ritualMoonsetJd
        );
        $sutakPraharCount = $this->resolveSutakPraharCount('Lunar', $ritualBoundary);
        $isGrastAst = (bool) ($ritualBoundary['grast_ast'] ?? false);
        $isGrastUday = (bool) ($ritualBoundary['grast_uday'] ?? false);
        $sutakPayload = $this->sutak(
            $sutakStartAnchor,
            $sutakEndAnchor,
            $sutakPraharCount,
            $lat,
            $lon,
            $tz,
            $isVisible,
            $isGrastAst ? 'lunar' : null
        );
        $horizonEvents = $this->buildHorizonEventsPayload(
            'Lunar',
            $isVisible,
            $isGrastUday,
            $isGrastAst,
            $astronomicalPartialBeginJd,
            $astronomicalMokshaJd,
            $ritualVisibleStartJd,
            $ritualVisibleEndJd,
            $ritualMoonriseJd,
            $ritualMoonsetJd,
            null,
            null,
            $tz
        );

        // Local penumbral sky window: geometric penumbral ∩ moon above horizon
        // (not JME tret[8]/[9], which can clip a few minutes early).
        $penumbralHorizon = $this->resolveLunarHorizonEventsNear(
            $globalContacts['penumbral_begin_jd'] ?? ($astronomicalPartialBeginJd ?? $jdMax),
            $globalContacts['penumbral_end_jd'] ?? ($astronomicalPartialEndJd ?? $jdMax),
            $lat,
            $lon,
            $tz
        );
        $localPenumbralMoonriseJd = $penumbralHorizon['moonrise_jd'] ?? $ritualMoonriseJd;
        $localPenumbralMoonsetJd = $penumbralHorizon['moonset_jd'] ?? $ritualMoonsetJd;
        $localPenumbralStartJd = $this->maxJd(
            $globalContacts['penumbral_begin_jd'],
            $localPenumbralMoonriseJd
        );
        $localPenumbralEndJd = $this->minJd(
            $globalContacts['penumbral_end_jd'],
            $localPenumbralMoonsetJd
        );
        $hasLocalPenumbralWindow = $localPenumbralStartJd !== null
            && $localPenumbralEndJd !== null
            && $localPenumbralEndJd > $localPenumbralStartJd;
        $showPenumbralWindow = ($isVisible || $astroVisible) && $hasLocalPenumbralWindow;

        return [
            'type' => Localization::translate('String', 'Lunar'),
            'eclipse_type' => Localization::translate('Eclipse', $globalType),
            'global_eclipse_type' => Localization::translate('Eclipse', $globalType),
            'local_eclipse_type' => $localType !== null ? Localization::translate('Eclipse', $localType) : null,
            'date' => $dt->toDateString(),
            'datetime' => AstroCore::formatDateTime($dt),
            'jd' => $jdMax,
            'magnitudes' => [
                'umbral' => (float) $attr[0],
                'penumbral' => (float) $attr[1],
                'ritual_minimum' => self::NIRNAY_LUNAR_ECLIPSE_MINIMUM_MAGNITUDE,
                'meets_ritual_minimum' => $meetsRitualMagnitude,
            ],
            'contacts' => $this->formatContactTimes($globalContacts, $tz),
            'durations' => [
                'penumbral_seconds' => $this->durationSeconds($globalContacts['penumbral_begin_jd'], $globalContacts['penumbral_end_jd']),
                'partial_seconds' => $this->durationSeconds($globalContacts['partial_begin_jd'], $globalContacts['partial_end_jd']),
                'total_seconds' => $this->durationSeconds($globalContacts['total_begin_jd'], $globalContacts['total_end_jd']),
            ],
            // Geometric local umbral visibility (horizon + magnitude rules) — not guaranteed sky weather.
            'literally_visible' => $this->buildLiterallyVisiblePayload(
                $isVisible,
                $ritualVisibleStartJd,
                $ritualVisibleEndJd,
                $tz,
                $horizonEvents
            ),
            // Sun/Moon rise-set that fall through the eclipse (grastodaya / grastasta).
            'horizon_events' => $horizonEvents,
            // Five ritual timings: standard sutak, relaxed sutak, sparsha, madhya, moksha.
            'ritual' => $this->buildRitualTimelinePayload(
                $isVisible,
                $astronomicalPartialBeginJd,
                $jdMax,
                $astronomicalMokshaJd,
                $sutakPayload,
                $tz
            ),
            'visibility' => [
                'visible' => $isVisible,
                'above_local_horizon' => $isVisible,
                'literally_visible_in_sky' => $isVisible,
                'potentially_visible_to_unaided_eye' => $isVisible,
                'astronomically_visible' => $astroVisible,
                'ritually_applicable' => $isVisible,
                'ritually_visible_by_horizon_and_magnitude_rules' => $isVisible,
                'below_ritual_magnitude' => $astroVisible && !$meetsRitualMagnitude,
                'below_ritual_duration' => $astroVisible && $hasValidRitualIntersection && !$meetsDurationThreshold,
                'meets_ritual_magnitude' => $meetsRitualMagnitude,
                'ritual_magnitude_minimum' => self::NIRNAY_LUNAR_ECLIPSE_MINIMUM_MAGNITUDE,
                'ritual_reason_key' => $ritualReasonKey,
                'ritual_reason' => Localization::translate('String', $ritualReasonKey),
                'local_eclipse_type' => $localType !== null ? Localization::translate('Eclipse', $localType) : null,
                'retflag' => $retHow,
                'window' => $this->formatVisibilityWindow($isVisible ? $ritualVisibleStartJd : null, $isVisible ? $ritualVisibleEndJd : null, $tz),
                'penumbral_window' => $this->formatVisibilityWindow(
                    $showPenumbralWindow ? $localPenumbralStartJd : null,
                    $showPenumbralWindow ? $localPenumbralEndJd : null,
                    $tz
                ),
            ],
            'sutak' => $sutakPayload,
            'ritual_boundary' => $ritualBoundary,
            'punya_kaal' => $this->buildPunyaKaalPayload(
                $isVisible,
                $ritualVisibleStartJd,
                $ritualVisibleEndJd,
                (bool) ($ritualBoundary['grast_uday'] ?? false),
                $tz
            ),
            'post_eclipse_ritual' => $this->buildPostEclipseRitualPayload(
                $ritualVisibleEndJd,
                'Lunar',
                $lat,
                $lon,
                $tz,
                $isVisible,
                $ritualBoundary,
                $astronomicalMokshaJd
            ),
            'retflag' => $retFlag,
        ];
    }

    private function buildSolarEvent(CData $globalTret, int $retFlag, float $lat, float $lon, string $tz): array
    {
        $geo = $this->newGeoPos($lat, $lon);
        $serr = $this->jme->getFFI()->new('char[256]');

        $attr = $this->jme->getFFI()->new('double[40]');
        $jdMax = (float) $globalTret[0];

        $tretLoc = $this->jme->getFFI()->new('double[10]');
        $attrLoc = $this->jme->getFFI()->new('double[20]');
        $retLoc = $this->jme->jme_sol_eclipse_when_loc($jdMax - 1.0, JmeEphFFI::JME_CALC_HIGH_PRECISION, $geo, $tretLoc, $attrLoc, 0, $serr);
        $contactsFromSameEvent = $retLoc > 0 && $tretLoc[0] > 0 && abs((float) $tretLoc[0] - $jdMax) < 0.5;
        $localMaximumJd = $contactsFromSameEvent ? (float) $tretLoc[0] : $jdMax;
        $retHow = $this->jme->jme_sol_eclipse_how($localMaximumJd, JmeEphFFI::JME_CALC_HIGH_PRECISION, $geo, $attr, $serr);

        $globalType = $this->solarTypeFromCode($retFlag);
        $localType = $contactsFromSameEvent ? $this->solarTypeFromCode($retLoc) : null;

        $globalContacts = [
            'first_contact_jd' => $globalTret[2] > 0 ? (float) $globalTret[2] : null,
            'second_contact_jd' => $globalTret[4] > 0 ? (float) $globalTret[4] : null,
            'maximum_jd' => $jdMax,
            'third_contact_jd' => $globalTret[5] > 0 ? (float) $globalTret[5] : null,
            'fourth_contact_jd' => $globalTret[3] > 0 ? (float) $globalTret[3] : null,
            'sunrise_jd' => null,
            'sunset_jd' => null,
        ];
        $localContacts = [
            'first_contact_jd' => $contactsFromSameEvent && $tretLoc[2] > 0 ? (float) $tretLoc[2] : null,
            'second_contact_jd' => $contactsFromSameEvent && $tretLoc[4] > 0 ? (float) $tretLoc[4] : null,
            'maximum_jd' => $contactsFromSameEvent ? $localMaximumJd : null,
            'third_contact_jd' => $contactsFromSameEvent && $tretLoc[5] > 0 ? (float) $tretLoc[5] : null,
            'fourth_contact_jd' => $contactsFromSameEvent && $tretLoc[3] > 0 ? (float) $tretLoc[3] : null,
            'sunrise_jd' => null,
            'sunset_jd' => null,
        ];

        $dt = $this->jdToCarbon($localMaximumJd, $tz);
        [$localSunrise, $localSunset] = $this->sunriseSunsetForDate($dt->startOfDay(), $lat, $lon, $tz);
        $localSunriseJd = $this->carbonToJd($localSunrise);
        $localSunsetJd = $this->carbonToJd($localSunset);

        // JME-native solar: attrLoc[8]=VISIBLE, attrLoc[0]=magnitude (diameter fraction).
        $jmeVisibilityFlag = $contactsFromSameEvent
            && (int) $attrLoc[self::JME_LOCAL_ATTR_VISIBILITY] === JmeEphFFI::JME_ECLIPSE_VISIBLE;
        $localDiskMagnitude = (float) $attrLoc[0];
        $hasVisibleDiskMagnitude = $localDiskMagnitude > 0.0;
        $meetsRitualMagnitude = $localDiskMagnitude >= self::NIRNAY_SOLAR_ECLIPSE_MINIMUM_MAGNITUDE;
        // JME sol when_loc: tret[2]/[3]=outer contacts, [4]/[5]=inner if total/annular/hybrid.
        // Clip the visible window to sun-above-horizon (sunrise/sunset).
        $visibilityWindowStartJd = $this->maxJd($localContacts['first_contact_jd'], $localSunriseJd);
        $visibilityWindowEndJd = $this->minJd($localContacts['fourth_contact_jd'], $localSunsetJd);
        $hasVisibleWindow = $visibilityWindowStartJd !== null
            && $visibilityWindowEndJd !== null
            && $visibilityWindowEndJd > $visibilityWindowStartJd;
        if (!$hasVisibleWindow) {
            $visibilityWindowStartJd = null;
            $visibilityWindowEndJd = null;
        }

        $astroVisible = $contactsFromSameEvent && ($jmeVisibilityFlag || ($hasVisibleDiskMagnitude && $hasVisibleWindow));

        $visibleDuration = $hasVisibleWindow ? $visibilityWindowEndJd - $visibilityWindowStartJd : 0.0;
        $meetsDurationThreshold = $visibleDuration >= self::NIRNAY_MINIMUM_VISIBLE_DURATION_DAYS;

        $isVisible = $contactsFromSameEvent
            && $astroVisible
            && $hasVisibleDiskMagnitude
            && $hasVisibleWindow
            && $meetsRitualMagnitude
            && $meetsDurationThreshold;

        $ritualReasonKey = $this->resolveRitualReasonKey(
            $isVisible,
            $contactsFromSameEvent && $astroVisible,
            false,
            $hasVisibleDiskMagnitude,
            $hasVisibleWindow,
            $meetsRitualMagnitude,
            $meetsDurationThreshold
        );

        // Vedh always from sparsha (first contact), even for grastodaya — never from sunrise.
        // Sutak ends at moksha (fourth contact), not merely local sunset if sun sets while eclipsed.
        $sutakStartAnchor = $localContacts['first_contact_jd'] ?? $visibilityWindowStartJd;
        $sutakEndAnchor = $localContacts['fourth_contact_jd'] ?? $visibilityWindowEndJd;

        // JME-native (events.c): attr[2]=sep deg, attr[3]/[4]=diameters arcsec. Derive area obscuration.
        $centreSeparationDeg = (float) $attr[self::JME_SOLAR_ATTR_CENTRE_SEPARATION_DEG];
        $sunRadiusDeg = (float) $attr[self::JME_SOLAR_ATTR_SUN_DIAMETER_ARCSEC] / 7200.0;
        $moonRadiusDeg = (float) $attr[self::JME_SOLAR_ATTR_MOON_DIAMETER_ARCSEC] / 7200.0;
        $obscuration = $this->calculateSolarObscuration($centreSeparationDeg, $sunRadiusDeg, $moonRadiusDeg);

        $ritualBoundary = $this->buildRitualBoundaryPayload(
            'Solar',
            $visibilityWindowStartJd,
            $visibilityWindowEndJd,
            $lat,
            $lon,
            $tz,
            $isVisible,
            $localContacts['first_contact_jd'],
            $localContacts['fourth_contact_jd']
        );
        $isGrastAst = (bool) ($ritualBoundary['grast_ast'] ?? false);
        $isGrastUday = (bool) ($ritualBoundary['grast_uday'] ?? false);
        $sutakPayload = $this->sutak(
            $sutakStartAnchor,
            $sutakEndAnchor,
            4,
            $lat,
            $lon,
            $tz,
            $isVisible,
            $isGrastAst ? 'solar' : null
        );

        // Sunrise/sunset during sparsha→moksha (grastodaya / grastasta for solar).
        $solarHorizon = $this->resolveSolarHorizonEventsNear(
            $localContacts['first_contact_jd'] ?? $localMaximumJd,
            $localContacts['fourth_contact_jd'] ?? $localMaximumJd,
            $lat,
            $lon,
            $tz
        );
        $horizonEvents = $this->buildHorizonEventsPayload(
            'Solar',
            $isVisible,
            $isGrastUday,
            $isGrastAst,
            $localContacts['first_contact_jd'],
            $localContacts['fourth_contact_jd'],
            $visibilityWindowStartJd,
            $visibilityWindowEndJd,
            null,
            null,
            $solarHorizon['sunrise_jd'],
            $solarHorizon['sunset_jd'],
            $tz
        );

        return [
            'type' => Localization::translate('String', 'Solar'),
            'eclipse_type' => Localization::translate('Eclipse', $globalType),
            'global_eclipse_type' => Localization::translate('Eclipse', $globalType),
            'local_eclipse_type' => $localType !== null ? Localization::translate('Eclipse', $localType) : null,
            'date' => $dt->toDateString(),
            'datetime' => AstroCore::formatDateTime($dt),
            'jd' => $localMaximumJd,
            'magnitudes' => [
                'eclipse' => (float) $attr[0],
                'local_eclipse' => $localDiskMagnitude,
                'obscuration' => $obscuration,
                'ritual_minimum' => self::NIRNAY_SOLAR_ECLIPSE_MINIMUM_MAGNITUDE,
                'meets_ritual_minimum' => $meetsRitualMagnitude,
            ],
            'contacts' => $this->formatContactTimes($localContacts, $tz),
            'global_contacts' => $this->formatContactTimes($globalContacts, $tz),
            'durations' => [
                'partial_seconds' => $this->durationSeconds($visibilityWindowStartJd, $visibilityWindowEndJd),
                'total_seconds' => $this->durationSeconds($localContacts['second_contact_jd'], $localContacts['third_contact_jd']),
            ],
            // Literal sky: Sun above horizon during eclipse contacts (not the same as sparsha/moksha).
            'literally_visible' => $this->buildLiterallyVisiblePayload(
                $isVisible,
                $visibilityWindowStartJd,
                $visibilityWindowEndJd,
                $tz,
                $horizonEvents
            ),
            // Sun/Moon rise-set that fall through the eclipse (grastodaya / grastasta).
            'horizon_events' => $horizonEvents,
            // Five ritual timings: standard sutak, relaxed sutak, sparsha, madhya, moksha.
            'ritual' => $this->buildRitualTimelinePayload(
                $isVisible,
                $localContacts['first_contact_jd'],
                $localMaximumJd,
                $localContacts['fourth_contact_jd'],
                $sutakPayload,
                $tz
            ),
            'visibility' => [
                'visible' => $isVisible,
                'above_local_horizon' => $isVisible,
                'literally_visible_in_sky' => $isVisible,
                'potentially_visible_to_unaided_eye' => $isVisible,
                'astronomically_visible' => $astroVisible,
                'ritually_applicable' => $isVisible,
                'ritually_visible_by_horizon_and_magnitude_rules' => $isVisible,
                'below_ritual_magnitude' => $astroVisible && !$meetsRitualMagnitude,
                'below_ritual_duration' => $astroVisible && $hasVisibleWindow && !$meetsDurationThreshold,
                'meets_ritual_magnitude' => $meetsRitualMagnitude,
                'ritual_magnitude_minimum' => self::NIRNAY_SOLAR_ECLIPSE_MINIMUM_MAGNITUDE,
                'ritual_reason_key' => $ritualReasonKey,
                'ritual_reason' => Localization::translate('String', $ritualReasonKey),
                'local_eclipse_type' => $localType !== null ? Localization::translate('Eclipse', $localType) : null,
                'retflag' => $retHow,
                'window' => $this->formatVisibilityWindow(
                    $isVisible ? $visibilityWindowStartJd : null,
                    $isVisible ? $visibilityWindowEndJd : null,
                    $tz
                ),
            ],
            'sutak' => $sutakPayload,
            'ritual_boundary' => $ritualBoundary,
            'punya_kaal' => $this->buildPunyaKaalPayload(
                $isVisible,
                $visibilityWindowStartJd,
                $visibilityWindowEndJd,
                (bool) ($ritualBoundary['grast_uday'] ?? false),
                $tz
            ),
            'post_eclipse_ritual' => $this->buildPostEclipseRitualPayload(
                $visibilityWindowEndJd,
                'Solar',
                $lat,
                $lon,
                $tz,
                $isVisible,
                $ritualBoundary,
                $localContacts['fourth_contact_jd']
            ),
            'retflag' => $retFlag,
        ];
    }

    private function lunarTypeFromCode(int $code): string
    {
        // JME return codes are sequential enums (JME_ECLIPSE_LUNAR_*), not bit flags.
        if ($code === JmeEphFFI::JME_ECLIPSE_LUNAR_TOTAL) {
            return 'Total';
        }

        if ($code === JmeEphFFI::JME_ECLIPSE_LUNAR_PARTIAL) {
            return 'Partial';
        }

        return 'Penumbral';
    }

    private function lunarPenumbralFlag(): int
    {
        return defined(JmeEphFFI::class . '::JME_ECLIPSE_LUNAR_PENUMBRAL')
            ? JmeEphFFI::JME_ECLIPSE_LUNAR_PENUMBRAL
            : JmeEphFFI::JME_ECLIPSE_PENUMBRAL_BEGIN;
    }

    private function isLunarPenumbralOnly(int $code): bool
    {
        return $code === $this->lunarPenumbralFlag();
    }

    private function solarTypeFromCode(int $code): string
    {
        return match ($code) {
            JmeEphFFI::JME_ECLIPSE_SOLAR_TOTAL => 'Total',
            JmeEphFFI::JME_ECLIPSE_SOLAR_ANNULAR => 'Annular',
            JmeEphFFI::JME_ECLIPSE_SOLAR_HYBRID => 'Hybrid',
            default => 'Partial',
        };
    }

    private function formatContactTimes(array $contacts, string $tz): array
    {
        $out = [];
        foreach ($contacts as $k => $v) {
            if ($v === null) {
                $out[$k] = null;
                continue;
            }

            $out[$k] = [
                'jd' => $v,
                'time' => AstroCore::formatDateTime($this->jdToCarbon((float) $v, $tz)),
            ];
        }

        return $out;
    }

    private function formatVisibilityWindow(?float $startJd, ?float $endJd, string $tz): array
    {
        return [
            'start_jd' => $startJd,
            'start' => $startJd !== null ? AstroCore::formatDateTime($this->jdToCarbon($startJd, $tz)) : null,
            'end_jd' => $endJd,
            'end' => $endJd !== null ? AstroCore::formatDateTime($this->jdToCarbon($endJd, $tz)) : null,
        ];
    }

    /** @return array{jd: float, time: string}|null */
    private function formatInstant(?float $jd, string $tz): ?array
    {
        if ($jd === null) {
            return null;
        }

        return [
            'jd' => $jd,
            'time' => AstroCore::formatDateTime($this->jdToCarbon($jd, $tz)),
        ];
    }

    /**
     * Naked-eye sky visibility only — independent of sparsha / madhya / moksha.
     *
     * @param array<string, mixed>|null $horizonEvents
     *
     * @return array<string, mixed>
     */
    private function buildLiterallyVisiblePayload(
        bool $inSky,
        ?float $startJd,
        ?float $endJd,
        string $tz,
        ?array $horizonEvents = null
    ): array {
        $hasWindow = $inSky && $startJd !== null && $endJd !== null && $endJd > $startJd;

        $payload = [
            'in_sky' => $hasWindow,
            'above_local_horizon' => $hasWindow,
            'potentially_visible_to_unaided_eye' => $hasWindow,
            'meaning_key' => 'literally_visible_geometric_above_horizon',
            'meaning' => Localization::translate('String', 'literally_visible_geometric_above_horizon'),
            'window' => $this->formatVisibilityWindow(
                $hasWindow ? $startJd : null,
                $hasWindow ? $endJd : null,
                $tz
            ),
            'duration_seconds' => $hasWindow ? $this->durationSeconds($startJd, $endJd) : 0.0,
        ];

        if (is_array($horizonEvents) && ($horizonEvents['has_rise_or_set_through_eclipse'] ?? false) === true) {
            $payload['bounded_by_horizon_events'] = $horizonEvents['events'] ?? [];
        }

        return $payload;
    }

    /**
     * Sun/Moon rise or set that occurs during sparsha→moksha (grastodaya / grastasta).
     *
     * @return array<string, mixed>
     */
    private function buildHorizonEventsPayload(
        string $eclipseKind,
        bool $isVisible,
        bool $grastUday,
        bool $grastAst,
        ?float $sparshaJd,
        ?float $mokshaJd,
        ?float $literalVisibleStartJd,
        ?float $literalVisibleEndJd,
        ?float $moonriseJd,
        ?float $moonsetJd,
        ?float $sunriseJd,
        ?float $sunsetJd,
        string $tz
    ): array {
        $events = [];

        $add = static function (
            array &$events,
            string $type,
            ?float $jd,
            string $role,
            ?float $literalStart,
            ?float $literalEnd,
            string $tz,
            callable $formatInstant
        ): void {
            if ($jd === null) {
                return;
            }

            $instant = $formatInstant($jd, $tz);
            if ($instant === null) {
                return;
            }

            $clipsStart = $literalStart !== null && abs($jd - $literalStart) < (30.0 / 86400.0);
            $clipsEnd = $literalEnd !== null && abs($jd - $literalEnd) < (30.0 / 86400.0);

            $events[] = array_merge($instant, [
                'type' => $type,
                'role' => $role,
                'during_eclipse' => true,
                'clips_literal_visibility_start' => $clipsStart,
                'clips_literal_visibility_end' => $clipsEnd,
            ]);
        };

        $format = fn (?float $jd, string $zone): ?array => $this->formatInstant($jd, $zone);

        if ($eclipseKind === 'Lunar') {
            if ($grastUday) {
                $add($events, 'moonrise', $moonriseJd, 'grast_uday', $literalVisibleStartJd, $literalVisibleEndJd, $tz, $format);
            }

            if ($grastAst) {
                $add($events, 'moonset', $moonsetJd, 'grast_ast', $literalVisibleStartJd, $literalVisibleEndJd, $tz, $format);
            }

            // Still expose mid-phase rise/set even if grast flags false (defensive).
            if (!$grastUday && $moonriseJd !== null && $sparshaJd !== null && $mokshaJd !== null
                && $sparshaJd < $moonriseJd && $moonriseJd < $mokshaJd) {
                $add($events, 'moonrise', $moonriseJd, 'during_eclipse', $literalVisibleStartJd, $literalVisibleEndJd, $tz, $format);
            }

            if (!$grastAst && $moonsetJd !== null && $sparshaJd !== null && $mokshaJd !== null
                && $sparshaJd < $moonsetJd && $moonsetJd < $mokshaJd) {
                $add($events, 'moonset', $moonsetJd, 'during_eclipse', $literalVisibleStartJd, $literalVisibleEndJd, $tz, $format);
            }
        } else {
            if ($grastUday) {
                $add($events, 'sunrise', $sunriseJd, 'grast_uday', $literalVisibleStartJd, $literalVisibleEndJd, $tz, $format);
            }

            if ($grastAst) {
                $add($events, 'sunset', $sunsetJd, 'grast_ast', $literalVisibleStartJd, $literalVisibleEndJd, $tz, $format);
            }

            if (!$grastUday && $sunriseJd !== null && $sparshaJd !== null && $mokshaJd !== null
                && $sparshaJd < $sunriseJd && $sunriseJd < $mokshaJd) {
                $add($events, 'sunrise', $sunriseJd, 'during_eclipse', $literalVisibleStartJd, $literalVisibleEndJd, $tz, $format);
            }

            if (!$grastAst && $sunsetJd !== null && $sparshaJd !== null && $mokshaJd !== null
                && $sparshaJd < $sunsetJd && $sunsetJd < $mokshaJd) {
                $add($events, 'sunset', $sunsetJd, 'during_eclipse', $literalVisibleStartJd, $literalVisibleEndJd, $tz, $format);
            }
        }

        $byType = [
            'moonrise' => null,
            'moonset' => null,
            'sunrise' => null,
            'sunset' => null,
        ];
        foreach ($events as $event) {
            $byType[(string) $event['type']] = $event;
        }

        return [
            'has_rise_or_set_through_eclipse' => $events !== [],
            'grast_uday' => $grastUday,
            'grast_ast' => $grastAst,
            'eclipse_kind' => $eclipseKind,
            // Flat convenience fields (null when that rise/set is not through this eclipse).
            'moonrise' => $byType['moonrise'],
            'moonset' => $byType['moonset'],
            'sunrise' => $byType['sunrise'],
            'sunset' => $byType['sunset'],
            // Ordered list of all rise/set events through the eclipse.
            'events' => $events,
        ];
    }

    /**
     * The five ritual applicability timings (distinct from literally_visible):
     * standard sutak, relaxed sutak, sparsha, madhya (ग्रहण मध्य), moksha.
     *
     * @param array<string, mixed> $sutakPayload
     *
     * @return array<string, mixed>
     */
    private function buildRitualTimelinePayload(
        bool $applicable,
        ?float $sparshaJd,
        ?float $madhyaJd,
        ?float $mokshaJd,
        array $sutakPayload,
        string $tz
    ): array {
        if (!$applicable || $sparshaJd === null || $mokshaJd === null) {
            return [
                'applicable' => false,
                'sparsha' => null,
                'madhya' => null,
                'moksha' => null,
                'sutak' => null,
                'relaxed_sutak' => null,
            ];
        }

        return [
            'applicable' => true,
            // Sparsha: partial/umbral begin. Vedh counted from here. Do not snana/eat-fresh yet.
            'sparsha' => array_merge($this->formatInstant($sparshaJd, $tz) ?? [], [
                'meaning_key' => 'ritual_sparsha',
                'meaning' => Localization::translate('String', 'ritual_sparsha'),
            ]),
            // Madhya (ग्रहण मध्य): maximum / mid-eclipse.
            'madhya' => array_merge($this->formatInstant($madhyaJd, $tz) ?? [], [
                'meaning_key' => 'ritual_madhya',
                'meaning' => Localization::translate('String', 'ritual_madhya'),
            ]),
            // Moksha: partial/umbral end. Snana + fresh food after this (see post_eclipse for grast food rules).
            'moksha' => array_merge($this->formatInstant($mokshaJd, $tz) ?? [], [
                'meaning_key' => 'ritual_moksha',
                'meaning' => Localization::translate('String', 'ritual_moksha'),
            ]),
            // Standard sutak: N prahars before sparsha → ends at moksha.
            'sutak' => [
                'start_jd' => $sutakPayload['start_jd'] ?? null,
                'start' => $sutakPayload['start'] ?? null,
                'end_jd' => $sutakPayload['end_jd'] ?? $mokshaJd,
                'end' => $sutakPayload['end'] ?? null,
                'prahars_before' => $sutakPayload['standard_prahars_before'] ?? null,
                'meaning_key' => 'ritual_standard_sutak',
                'meaning' => Localization::translate('String', 'ritual_standard_sutak'),
                'end_scope_key' => $sutakPayload['end_scope_key'] ?? null,
                'end_scope' => $sutakPayload['end_scope'] ?? null,
            ],
            // Relaxed sutak: 1 prahar before sparsha → ends at moksha (children, elderly, sick).
            'relaxed_sutak' => [
                'start_jd' => $sutakPayload['relaxed_start_jd'] ?? null,
                'start' => $sutakPayload['relaxed_start'] ?? null,
                'end_jd' => $sutakPayload['relaxed_end_jd'] ?? $mokshaJd,
                'end' => $sutakPayload['relaxed_end'] ?? null,
                'prahars_before' => $sutakPayload['relaxed_prahars_before'] ?? 1,
                'meaning_key' => 'ritual_relaxed_sutak',
                'meaning' => Localization::translate('String', 'ritual_relaxed_sutak'),
            ],
        ];
    }

    private function durationSeconds(?float $fromJd, ?float $toJd): float
    {
        if ($fromJd === null || $toJd === null || $toJd < $fromJd) {
            return 0.0;
        }

        return ($toJd - $fromJd) * 86400.0;
    }

    /** @param string|null $grastAstKind 'lunar'|'solar'|null — when set, document that end is moksha for snana/homa only */
    private function sutak(
        ?float $eclipseStartJd,
        ?float $eclipseEndJd,
        int $praharsBefore,
        float $lat,
        float $lon,
        string $tz,
        bool $isVisible,
        ?string $grastAstKind = null
    ): array {
        if (!$isVisible || $eclipseStartJd === null || $eclipseEndJd === null) {
            return [
                'applicable' => false,
                'reason' => Localization::translate('String', 'eclipse_not_visible_at_location'),
                'reason_key' => 'eclipse_not_visible_at_location',
                'start_jd' => null,
                'end_jd' => null,
                'start' => null,
                'end' => null,
                'relaxed_start_jd' => null,
                'relaxed_end_jd' => null,
                'relaxed_start' => null,
                'relaxed_end' => null,
                'duration_hours' => 0.0,
            ];
        }

        // Sutak is counted backward in praharas from the local eclipse sparsha.
        // Prahara boundaries are resolved from the actual day/night spans around the event.
        $anchors = $this->resolveSutakAnchors($eclipseStartJd, $lat, $lon, $tz, $praharsBefore);
        $startJd = $anchors['start_jd'] ?? null;
        $relaxedStartJd = $anchors['relaxed_start_jd'] ?? null;

        if ($startJd === null || $relaxedStartJd === null) {
            return [
                'applicable' => false,
                'reason' => 'Unable to resolve local prahara boundaries for sutak.',
                'reason_key' => 'sutak_boundary_resolution_failed',
                'start_jd' => null,
                'end_jd' => null,
                'start' => null,
                'end' => null,
                'relaxed_start_jd' => null,
                'relaxed_end_jd' => null,
                'relaxed_start' => null,
                'relaxed_end' => null,
                'duration_hours' => 0.0,
            ];
        }

        $endScopeKey = match ($grastAstKind) {
            'lunar' => 'snana_homa_after_moksha_food_after_next_moonrise',
            'solar' => 'snana_homa_after_next_sunrise_food_after_pure_sun',
            default => 'snana_homa_and_food_after_moksha',
        };

        return [
            'applicable' => true,
            'standard_prahars_before' => $praharsBefore,
            'relaxed_prahars_before' => 1,
            // Sparsha = partial begin (lunar) / first contact (solar). Never penumbral, never grast rise.
            'sparsha_jd' => $eclipseStartJd,
            'sparsha' => AstroCore::formatDateTime($this->jdToCarbon($eclipseStartJd, $tz)),
            'start_jd' => $startJd,
            'end_jd' => $eclipseEndJd,
            'start' => AstroCore::formatDateTime($this->jdToCarbon($startJd, $tz)),
            'end' => AstroCore::formatDateTime($this->jdToCarbon($eclipseEndJd, $tz)),
            // end = moksha / ritual-impurity release for snana-homa; food may remain restricted after (grastasta).
            'end_scope_key' => $endScopeKey,
            'end_scope' => Localization::translate('String', $endScopeKey),
            'profile_key' => 'standard_sutak',
            'profile_name' => Localization::translate('String', 'standard_sutak'),
            'relaxed_start_jd' => $relaxedStartJd,
            'relaxed_end_jd' => $eclipseEndJd,
            'relaxed_start' => AstroCore::formatDateTime($this->jdToCarbon($relaxedStartJd, $tz)),
            'relaxed_end' => AstroCore::formatDateTime($this->jdToCarbon($eclipseEndJd, $tz)),
            'relaxed_profile_key' => 'sutak_for_children_elderly_sick',
            'relaxed_profile_name' => Localization::translate('String', 'sutak_for_children_elderly_sick'),
            'duration_hours' => ($eclipseStartJd - $startJd) * 24.0,
        ];
    }

    /** Ritual non-observance reasons (ગ્રહલાઘવ / સિદ્ધાંતશિરોમણી / અડધી ઘડી). */
    private function resolveRitualReasonKey(
        bool $isVisible,
        bool $astroVisible,
        bool $isPenumbralOnly,
        bool $hasRitualPhase,
        bool $hasValidIntersection,
        bool $meetsMagnitude,
        bool $meetsDuration
    ): string {
        if ($isVisible) {
            return 'ritually_applicable_visible_umbral';
        }

        if ($isPenumbralOnly) {
            return 'penumbral_not_ritually_observed';
        }

        if (!$astroVisible || !$hasValidIntersection || !$hasRitualPhase) {
            return 'eclipse_not_visible_at_location';
        }

        if (!$meetsMagnitude) {
            return 'angulalp_below_ritual_magnitude';
        }

        if (!$meetsDuration) {
            return 'visible_duration_below_half_ghati';
        }

        return 'eclipse_not_visible_at_location';
    }

    /**
     * Punya kaal while the eclipse is locally visible (eye / shastra window).
     * Grastodaya: no punya before rise — window already starts at rise/visibility start.
     */
    private function buildPunyaKaalPayload(
        bool $isVisible,
        ?float $visibleStartJd,
        ?float $visibleEndJd,
        bool $isGrastUday,
        string $tz
    ): array {
        if (!$isVisible || $visibleStartJd === null || $visibleEndJd === null) {
            return [
                'applicable' => false,
                'start_jd' => null,
                'end_jd' => null,
                'start' => null,
                'end' => null,
                'rule_key' => 'no_punya_without_local_ritual_visibility',
            ];
        }

        return [
            'applicable' => true,
            'start_jd' => $visibleStartJd,
            'end_jd' => $visibleEndJd,
            'start' => AstroCore::formatDateTime($this->jdToCarbon($visibleStartJd, $tz)),
            'end' => AstroCore::formatDateTime($this->jdToCarbon($visibleEndJd, $tz)),
            'rule_key' => $isGrastUday
                ? 'punya_from_grast_udaya_to_visible_end'
                : 'punya_while_locally_visible',
            'note' => $isGrastUday
                ? Localization::translate('String', 'punya_not_before_grast_udaya')
                : Localization::translate('String', 'punya_while_eclipse_visible'),
        ];
    }

    /**
     * @param float|null $phaseBeginJd astronomical sparsha (partial/first contact)
     * @param float|null $phaseEndJd astronomical moksha (partial/fourth contact)
     * @param float|null $moonriseJd lunar only: moonrise during umbral phase candidate
     * @param float|null $moonsetJd lunar only: moonset during umbral phase candidate
     */
    private function buildRitualBoundaryPayload(
        string $eclipseKind,
        ?float $visibleStartJd,
        ?float $visibleEndJd,
        float $lat,
        float $lon,
        string $tz,
        bool $isVisible,
        ?float $phaseBeginJd = null,
        ?float $phaseEndJd = null,
        ?float $moonriseJd = null,
        ?float $moonsetJd = null
    ): array {
        if (!$isVisible || $visibleStartJd === null || $visibleEndJd === null) {
            return [
                'type' => 'not_applicable',
                'type_key' => 'not_applicable',
                'type_name' => Localization::translate('String', 'not_applicable'),
                'grast_uday' => false,
                'grast_ast' => false,
                'rule' => 'no_ritual_boundary_rule_without_local_ritual_visibility',
            ];
        }

        $start = $this->jdToCarbon($visibleStartJd, $tz);
        $end = $this->jdToCarbon($visibleEndJd, $tz);
        [$startSunrise, $startSunset] = $this->sunriseSunsetForDate($start->startOfDay(), $lat, $lon, $tz);
        [$endSunrise, $endSunset] = $this->sunriseSunsetForDate($end->startOfDay(), $lat, $lon, $tz);

        $sparshaJd = $phaseBeginJd ?? $visibleStartJd;
        $mokshaJd = $phaseEndJd ?? $visibleEndJd;

        if ($eclipseKind === 'Lunar') {
            // Lunar grast uses Moonrise/Moonset vs umbral (partial) contacts — not sunrise/sunset.
            $grastUday = $moonriseJd !== null
                && $sparshaJd < $moonriseJd
                && $moonriseJd < $mokshaJd;
            $grastAst = $moonsetJd !== null
                && $sparshaJd < $moonsetJd
                && $moonsetJd < $mokshaJd;
            $rule = 'lunar_grast_uses_moonrise_moonset_vs_partial_phase';
        } else {
            // Solar grast: body already eclipsed at sunrise/sunset.
            $startSunriseJd = $this->carbonToJd($startSunrise);
            $endSunsetJd = $this->carbonToJd($endSunset);
            $grastUday = $sparshaJd < $startSunriseJd && $startSunriseJd < $mokshaJd;
            $grastAst = $sparshaJd < $endSunsetJd && $endSunsetJd < $mokshaJd;
            $rule = 'solar_grast_uses_sunrise_sunset_vs_eclipse_phase';
        }

        $instructionKey = match (true) {
            $grastUday && $eclipseKind === 'Lunar' => 'lunar_grastodaya',
            $grastUday && $eclipseKind === 'Solar' => 'solar_grastodaya',
            $grastAst && $eclipseKind === 'Lunar' => 'lunar_grastasta',
            $grastAst && $eclipseKind === 'Solar' => 'solar_grastasta',
            default => 'ordinary_visible',
        };
        $isChudamaniYoga = $this->isChudamaniYoga($eclipseKind, $visibleStartJd, $tz);

        $boundaryType = match (true) {
            $grastUday && $grastAst => 'grast_uday_and_grast_ast',
            $grastUday => 'grast_uday',
            $grastAst => 'grast_ast',
            default => 'ordinary_visible_eclipse',
        };

        $payload = [
            'type' => $boundaryType,
            'type_key' => $boundaryType,
            'type_name' => Localization::translate('String', $boundaryType),
            'instruction_key' => $instructionKey,
            'scriptural_instructions' => Localization::translate('EclipseInstructions', $instructionKey),
            'grast_uday' => $grastUday,
            'grast_ast' => $grastAst,
            'is_chudamani_yoga' => $isChudamaniYoga,
            'visible_start_jd' => $visibleStartJd,
            'visible_start' => AstroCore::formatDateTime($start),
            'visible_end_jd' => $visibleEndJd,
            'visible_end' => AstroCore::formatDateTime($end),
            'sunrise_jd' => $this->carbonToJd($grastUday && $eclipseKind === 'Solar' ? $startSunrise : $endSunrise),
            'sunset_jd' => $this->carbonToJd($grastAst && $eclipseKind === 'Solar' ? $endSunset : $startSunset),
            'rule' => $rule,
        ];

        if ($eclipseKind === 'Lunar') {
            $payload['moonrise_jd'] = $moonriseJd;
            $payload['moonrise'] = $moonriseJd !== null
                ? AstroCore::formatDateTime($this->jdToCarbon($moonriseJd, $tz))
                : null;
            $payload['moonset_jd'] = $moonsetJd;
            $payload['moonset'] = $moonsetJd !== null
                ? AstroCore::formatDateTime($this->jdToCarbon($moonsetJd, $tz))
                : null;
            $payload['astronomical_sparsha_jd'] = $sparshaJd;
            $payload['astronomical_sparsha'] = AstroCore::formatDateTime($this->jdToCarbon($sparshaJd, $tz));
            $payload['astronomical_moksha_jd'] = $mokshaJd;
            $payload['astronomical_moksha'] = AstroCore::formatDateTime($this->jdToCarbon($mokshaJd, $tz));
        } else {
            // Explicit solar rise/set through eclipse when grast applies.
            if ($grastUday) {
                $payload['sunrise_during_eclipse_jd'] = $this->carbonToJd($startSunrise);
                $payload['sunrise_during_eclipse'] = AstroCore::formatDateTime($startSunrise);
            }

            if ($grastAst) {
                $payload['sunset_during_eclipse_jd'] = $this->carbonToJd($endSunset);
                $payload['sunset_during_eclipse'] = AstroCore::formatDateTime($endSunset);
            }

            $payload['astronomical_sparsha_jd'] = $sparshaJd;
            $payload['astronomical_sparsha'] = AstroCore::formatDateTime($this->jdToCarbon($sparshaJd, $tz));
            $payload['astronomical_moksha_jd'] = $mokshaJd;
            $payload['astronomical_moksha'] = AstroCore::formatDateTime($this->jdToCarbon($mokshaJd, $tz));
        }

        return $payload;
    }

    private function buildPostEclipseRitualPayload(
        ?float $visibleEndJd,
        string $eclipseKind,
        float $lat,
        float $lon,
        string $tz,
        bool $isVisible,
        array $ritualBoundary = [],
        ?float $astronomicalMokshaJd = null
    ): array {
        if (!$isVisible || $visibleEndJd === null) {
            return [
                'applicable' => false,
                'snana_required' => false,
                'fresh_food_after_eclipse' => false,
                'boundary_type' => null,
                'ritual_completion_requirement_key' => null,
                'ritual_completion_requirement' => null,
            ];
        }

        $mokshaJd = $astronomicalMokshaJd ?? $visibleEndJd;
        $isGrastAst = (bool) ($ritualBoundary['grast_ast'] ?? false);
        $isGrastUday = (bool) ($ritualBoundary['grast_uday'] ?? false);
        $boundaryType = (string) ($ritualBoundary['type'] ?? 'ordinary_visible_eclipse');

        $snanaAfterJd = $mokshaJd;
        $foodAfterJd = $mokshaJd;
        $completionRequirementKey = 'snana_after_moksha_then_fresh_food';
        $foodAllowedAfterKey = 'astronomical_moksha';

        if ($isGrastAst) {
            if ($eclipseKind === 'Solar') {
                // Solar grastasta (S.J. 5.19.74-75): next day after sunrise — snana, pure sun darshan, then food.
                $nextSunriseJd = $this->nextSunriseAfter($visibleEndJd, $lat, $lon, $tz);
                $snanaAfterJd = $nextSunriseJd;
                $foodAfterJd = $nextSunriseJd;
                $completionRequirementKey = 'after_next_sunrise_bathe_see_pure_sun_disc_then_eat';
                $foodAllowedAfterKey = 'next_local_sunrise';
            } else {
                // Lunar grastasta (S.J. 5.19.76): snana/homa after jyotish moksha; food after next moonrise.
                $foodAfterJd = $this->nextMoonriseAfter($visibleEndJd, $lat, $lon, $tz) ?? $mokshaJd;
                $completionRequirementKey = 'after_moonrise_again_then_eat';
                $foodAllowedAfterKey = 'next_local_moonrise';
            }
        } elseif ($isGrastUday && $eclipseKind === 'Solar') {
            // Solar grastodaya: after moksha, bathe and see pure sun disc before eating.
            $completionRequirementKey = 'see_pure_sun_disc_after_moksha_then_eat';
        } elseif ($isGrastUday && $eclipseKind === 'Lunar') {
            // Lunar grastodaya: 4-prahar vedh (handled in sutak); food after moksha.
            $completionRequirementKey = 'snana_after_moksha_then_fresh_food';
        }

        // No generic starts_after: snana/homa and food may begin at different instants (grastasta).
        return [
            'applicable' => true,
            'snana_required' => true,
            'fresh_food_after_eclipse' => true,
            'boundary_type' => $boundaryType,
            'local_visible_end_jd' => $visibleEndJd,
            'local_visible_end' => AstroCore::formatDateTime($this->jdToCarbon($visibleEndJd, $tz)),
            'astronomical_moksha_jd' => $mokshaJd,
            'astronomical_moksha' => AstroCore::formatDateTime($this->jdToCarbon($mokshaJd, $tz)),
            'snana_homa_after_jd' => $snanaAfterJd,
            'snana_homa_after' => AstroCore::formatDateTime($this->jdToCarbon($snanaAfterJd, $tz)),
            'food_allowed_after_jd' => $foodAfterJd,
            'food_allowed_after' => AstroCore::formatDateTime($this->jdToCarbon($foodAfterJd, $tz)),
            'food_allowed_after_key' => $foodAllowedAfterKey,
            'ritual_completion_requirement_key' => $completionRequirementKey,
            'ritual_completion_requirement' => Localization::translate('String', $completionRequirementKey),
        ];
    }

    /** @param array<string, mixed> $ritualBoundary */
    private function resolveSutakPraharCount(string $eclipseKind, array $ritualBoundary): int
    {
        if ($eclipseKind === 'Solar') {
            return 4;
        }

        return (bool) ($ritualBoundary['grast_uday'] ?? false) ? 4 : 3;
    }

    private function isChudamaniYoga(string $eclipseKind, float $referenceJd, string $tz): bool
    {
        $weekday = $this->jdToCarbon($referenceJd, $tz)->dayOfWeek;

        return match ($eclipseKind) {
            'Solar' => $weekday === CarbonImmutable::SUNDAY,
            'Lunar' => $weekday === CarbonImmutable::MONDAY,
            default => false,
        };
    }

    private function nextSunriseAfter(float $jd, float $lat, float $lon, string $tz): float
    {
        $time = $this->jdToCarbon($jd, $tz);
        [$todaySunrise] = $this->sunriseSunsetForDate($time->startOfDay(), $lat, $lon, $tz);
        if ($todaySunrise->greaterThan($time)) {
            return $this->carbonToJd($todaySunrise);
        }

        [$nextSunrise] = $this->sunriseSunsetForDate($time->startOfDay()->addDay(), $lat, $lon, $tz);

        return $this->carbonToJd($nextSunrise);
    }

    private function nextMoonriseAfter(float $jd, float $lat, float $lon, string $tz): ?float
    {
        $time = $this->jdToCarbon($jd, $tz);

        foreach ([0, 1, 2] as $offsetDays) {
            [$moonrise] = $this->sunService->getMoonriseMoonset([
                'year' => $time->addDays($offsetDays)->year,
                'month' => $time->addDays($offsetDays)->month,
                'day' => $time->addDays($offsetDays)->day,
                'hour' => 0,
                'minute' => 0,
                'second' => 0,
                'timezone' => $tz,
                'latitude' => $lat,
                'longitude' => $lon,
                'elevation' => 0.0,
            ]);

            if ($moonrise instanceof CarbonImmutable && $moonrise->greaterThan($time)) {
                return $this->carbonToJd($moonrise);
            }
        }

        return null;
    }

    /**
     * Find sunrise/sunset that fall strictly inside sparsha→moksha, if any.
     *
     * @return array{sunrise_jd:?float, sunset_jd:?float}
     */
    private function resolveSolarHorizonEventsNear(
        float $phaseBeginJd,
        float $phaseEndJd,
        float $lat,
        float $lon,
        string $tz
    ): array {
        $center = $this->jdToCarbon(($phaseBeginJd + $phaseEndJd) / 2.0, $tz)->startOfDay();
        $sunriseJd = null;
        $sunsetJd = null;

        foreach ([-1, 0, 1] as $offsetDays) {
            $day = $center->addDays($offsetDays);
            [$sunrise, $sunset] = $this->sunriseSunsetForDate($day, $lat, $lon, $tz);
            $sr = $this->carbonToJd($sunrise);
            $ss = $this->carbonToJd($sunset);
            if ($phaseBeginJd < $sr && $sr < $phaseEndJd) {
                $sunriseJd = $sr;
            }

            if ($phaseBeginJd < $ss && $ss < $phaseEndJd) {
                $sunsetJd = $ss;
            }
        }

        return [
            'sunrise_jd' => $sunriseJd,
            'sunset_jd' => $sunsetJd,
        ];
    }

    /**
     * Find moonrise/moonset that fall inside the umbral (partial) phase, if any (SunService).
     *
     * @return array{moonrise_jd:?float, moonset_jd:?float}
     */
    private function resolveLunarHorizonEventsNear(
        float $phaseBeginJd,
        float $phaseEndJd,
        float $lat,
        float $lon,
        string $tz
    ): array {
        $center = $this->jdToCarbon(($phaseBeginJd + $phaseEndJd) / 2.0, $tz)->startOfDay();
        $moonriseJd = null;
        $moonsetJd = null;

        foreach ([-1, 0, 1] as $offsetDays) {
            $day = $center->addDays($offsetDays);
            [$moonrise, $moonset] = $this->sunService->getMoonriseMoonset([
                'year' => $day->year,
                'month' => $day->month,
                'day' => $day->day,
                'hour' => 0,
                'minute' => 0,
                'second' => 0,
                'timezone' => $tz,
                'latitude' => $lat,
                'longitude' => $lon,
                'elevation' => 0.0,
            ]);

            if ($moonrise instanceof CarbonImmutable) {
                $jd = $this->carbonToJd($moonrise);
                if ($phaseBeginJd < $jd && $jd < $phaseEndJd) {
                    $moonriseJd = $jd;
                }
            }

            if ($moonset instanceof CarbonImmutable) {
                $jd = $this->carbonToJd($moonset);
                if ($phaseBeginJd < $jd && $jd < $phaseEndJd) {
                    $moonsetJd = $jd;
                }
            }
        }

        return [
            'moonrise_jd' => $moonriseJd,
            'moonset_jd' => $moonsetJd,
        ];
    }

    /**
     * Single horizon marker for lunar grast + naked-eye local visibility.
     *
     * Prefer SunService moonrise/moonset (literal sky). JME when_loc tret[8]/[9]
     * is fallback only — it can clip a few minutes early vs actual moonset.
     */
    private function resolveRitualLunarHorizonJd(
        ?float $partialBeginJd,
        ?float $partialEndJd,
        ?float $jmeVisibilityBoundJd,
        ?float $sunServiceHorizonJd
    ): ?float {
        if ($partialBeginJd === null || $partialEndJd === null) {
            return $sunServiceHorizonJd ?? $jmeVisibilityBoundJd;
        }

        // Literal sky: moonrise/moonset during umbral phase (grastodaya / grastasta).
        if ($sunServiceHorizonJd !== null
            && $partialBeginJd < $sunServiceHorizonJd
            && $sunServiceHorizonJd < $partialEndJd
        ) {
            return $sunServiceHorizonJd;
        }

        // Fallback: JME local visibility bound if it marks a mid-phase horizon event.
        if ($jmeVisibilityBoundJd !== null
            && $partialBeginJd < $jmeVisibilityBoundJd
            && $jmeVisibilityBoundJd < $partialEndJd
        ) {
            return $jmeVisibilityBoundJd;
        }

        return null;
    }

    /** @return array{start_jd:?float, relaxed_start_jd:?float} */
    private function resolveSutakAnchors(float $eclipseStartJd, float $lat, float $lon, string $tz, int $praharsBefore): array
    {
        $eventStart = $this->jdToCarbon($eclipseStartJd, $tz);
        $boundaries = $this->buildPraharBoundaries($eventStart, $lat, $lon, $tz);
        $count = count($boundaries);
        if ($count < 2) {
            return ['start_jd' => null, 'relaxed_start_jd' => null];
        }

        $eventTs = $eventStart->getTimestamp();
        foreach ($boundaries as $boundary) {
            $boundaryTs = $boundary->getTimestamp();
            if (abs($boundaryTs - $eventTs) <= 120) {
                $eventTs = $boundaryTs;
                break;
            }
        }

        $containingIndex = null;
        for ($i = 0; $i < $count - 1; $i++) {
            $segmentStartTs = $boundaries[$i]->getTimestamp();
            $segmentEndTs = $boundaries[$i + 1]->getTimestamp();
            if ($eventTs >= $segmentStartTs && $eventTs < $segmentEndTs) {
                $containingIndex = $i;
                break;
            }
        }

        if ($containingIndex === null && $eventTs === $boundaries[$count - 1]->getTimestamp()) {
            $containingIndex = $count - 2;
        }

        if ($containingIndex === null) {
            return ['start_jd' => null, 'relaxed_start_jd' => null];
        }

        $startBoundaryIndex = $containingIndex - $praharsBefore;
        $segmentStart = $boundaries[$containingIndex];
        $segmentEnd = $boundaries[$containingIndex + 1];
        $segmentDurationSeconds = max(
            0.0,
            ($segmentEnd->getTimestamp() - $segmentStart->getTimestamp())
            + (((int) $segmentEnd->format('u')) - ((int) $segmentStart->format('u'))) / 1_000_000
        );
        $relaxedStart = $segmentDurationSeconds > 0.0
            ? $this->addFloatSeconds($eventStart, -$segmentDurationSeconds)
            : null;

        return [
            'start_jd' => $startBoundaryIndex >= 0 ? $this->carbonToJd($boundaries[$startBoundaryIndex]) : null,
            'relaxed_start_jd' => $relaxedStart instanceof CarbonImmutable ? $this->carbonToJd($relaxedStart) : null,
        ];
    }

    /** @return list<CarbonImmutable> */
    private function buildPraharBoundaries(CarbonImmutable $eventStart, float $lat, float $lon, string $tz): array
    {
        $currentDay = $eventStart->startOfDay();
        $previousDay = $currentDay->subDay();
        $nextDay = $currentDay->addDay();

        [$previousSunrise, $previousSunset] = $this->sunriseSunsetForDate($previousDay, $lat, $lon, $tz);
        [$currentSunrise, $currentSunset] = $this->sunriseSunsetForDate($currentDay, $lat, $lon, $tz);
        [$nextSunrise] = $this->sunriseSunsetForDate($nextDay, $lat, $lon, $tz);

        return [
            ...$this->praharaSegmentBoundaries($previousSunrise, $previousSunset, 4),
            ...$this->praharaSegmentBoundaries($previousSunset, $currentSunrise, 4),
            ...$this->praharaSegmentBoundaries($currentSunrise, $currentSunset, 4),
            ...$this->praharaSegmentBoundaries($currentSunset, $nextSunrise, 4),
            $nextSunrise,
        ];
    }

    /** @return list<CarbonImmutable> */
    private function praharaSegmentBoundaries(CarbonImmutable $start, CarbonImmutable $end, int $parts): array
    {
        $durationSeconds = ($end->getTimestamp() - $start->getTimestamp())
            + (((int) $end->format('u')) - ((int) $start->format('u'))) / 1_000_000;
        $stepSeconds = $durationSeconds / $parts;
        $boundaries = [];

        for ($i = 0; $i < $parts; $i++) {
            $boundaries[] = $this->addFloatSeconds($start, $i * $stepSeconds);
        }

        return $boundaries;
    }

    private function addFloatSeconds(CarbonImmutable $time, float $seconds): CarbonImmutable
    {
        return $time->addMicroseconds((int) round($seconds * 1_000_000));
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function sunriseSunsetForDate(CarbonImmutable $date, float $lat, float $lon, string $tz): array
    {
        return $this->sunService->getSunriseSunset([
            'year' => $date->year,
            'month' => $date->month,
            'day' => $date->day,
            'hour' => 0,
            'minute' => 0,
            'second' => 0,
            'timezone' => $tz,
            'latitude' => $lat,
            'longitude' => $lon,
            'elevation' => 0.0,
        ]);
    }

    private function carbonToJd(CarbonImmutable $time): float
    {
        $utc = $time->setTimezone('UTC');
        $hourDecimal = (int) $utc->format('H')
            + ((int) $utc->format('i')) / 60.0
            + (((int) $utc->format('s')) + ((int) $utc->format('u') / 1_000_000)) / 3600.0;

        return $this->jme->jme_julian_day(
            $utc->year,
            $utc->month,
            $utc->day,
            $hourDecimal,
            JmeEphFFI::JME_CALENDAR_GREGORIAN
        );
    }

    private function maxJd(?float $a, ?float $b): ?float
    {
        if ($a === null) {
            return $b;
        }

        if ($b === null) {
            return $a;
        }

        return max($a, $b);
    }

    private function minJd(?float $a, ?float $b): ?float
    {
        if ($a === null) {
            return $b;
        }

        if ($b === null) {
            return $a;
        }

        return min($a, $b);
    }

    /** @phpstan-ignore return.unusedType */
    private function newGeoPos(float $lat, float $lon): object
    {
        /** @var CData $geo */
        $geo = $this->jme->getFFI()->new('double[3]');
        $geo[0] = $lon;
        $geo[1] = $lat;
        $geo[2] = 0.0;
        return $geo;
    }

    private function jdToCarbon(float $jd, string $tz): CarbonImmutable
    {
        $y = $this->jme->getFFI()->new('int[1]');
        $m = $this->jme->getFFI()->new('int[1]');
        $d = $this->jme->getFFI()->new('int[1]');
        $h = $this->jme->getFFI()->new('int[1]');
        $i = $this->jme->getFFI()->new('int[1]');
        $s = $this->jme->getFFI()->new('double[1]');

        $this->jme->jme_jd_to_utc($jd, JmeEphFFI::JME_CALENDAR_GREGORIAN, $y, $m, $d, $h, $i, $s);

        $sec = (int) floor($s[0]);
        $micros = (int) floor(($s[0] - $sec) * 1_000_000.0);

        return CarbonImmutable::create((int) $y[0], (int) $m[0], (int) $d[0], (int) $h[0], (int) $i[0], $sec, 'UTC')
            ->addMicroseconds($micros)
            ->setTimezone($tz);
    }

    /**
     * Fraction of the solar disc area covered by the lunar disc (obscuration).
     *
     * @param float $sep angular separation of centres (degrees)
     * @param float $sunR solar radius (degrees)
     * @param float $moonR lunar radius (degrees)
     */
    private function calculateSolarObscuration(float $sep, float $sunR, float $moonR): float
    {
        if ($sunR <= 0.0 || $moonR <= 0.0) {
            return 0.0;
        }

        // No overlap
        if ($sep >= ($sunR + $moonR)) {
            return 0.0;
        }

        // One disc fully inside the other
        if ($sep <= abs($sunR - $moonR)) {
            if ($moonR >= $sunR) {
                return 1.0;
            }

            return min(1.0, ($moonR * $moonR) / ($sunR * $sunR));
        }

        // Partial overlap area of two circles
        $x1 = (($sep * $sep) + ($sunR * $sunR) - ($moonR * $moonR)) / (2.0 * $sep * $sunR);
        $x2 = (($sep * $sep) + ($moonR * $moonR) - ($sunR * $sunR)) / (2.0 * $sep * $moonR);

        $x1 = max(-1.0, min(1.0, $x1));
        $x2 = max(-1.0, min(1.0, $x2));

        $part1 = $sunR * $sunR * acos($x1);
        $part2 = $moonR * $moonR * acos($x2);

        $radicand = (-$sep + $sunR + $moonR)
            * ($sep + $sunR - $moonR)
            * ($sep - $sunR + $moonR)
            * ($sep + $sunR + $moonR);

        $part3 = 0.5 * sqrt(max(0.0, $radicand));
        $overlapArea = $part1 + $part2 - $part3;
        $sunArea = M_PI * $sunR * $sunR;

        return max(0.0, min(1.0, $overlapArea / $sunArea));
    }
}
