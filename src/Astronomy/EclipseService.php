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
            'penumbral_begin_jd' => $globalTret[2] > 0 ? (float) $globalTret[2] : null,
            'partial_begin_jd' => $globalTret[4] > 0 ? (float) $globalTret[4] : null,
            'total_begin_jd' => $globalTret[6] > 0 ? (float) $globalTret[6] : null,
            'maximum_jd' => $jdMax,
            'total_end_jd' => $globalTret[7] > 0 ? (float) $globalTret[7] : null,
            'partial_end_jd' => $globalTret[5] > 0 ? (float) $globalTret[5] : null,
            'penumbral_end_jd' => $globalTret[3] > 0 ? (float) $globalTret[3] : null,
        ];
        $localContacts = [
            'penumbral_begin_jd' => $contactsFromSameEvent && $tretLoc[2] > 0 ? (float) $tretLoc[2] : null,
            'partial_begin_jd' => $contactsFromSameEvent && $tretLoc[4] > 0 ? (float) $tretLoc[4] : null,
            'total_begin_jd' => $contactsFromSameEvent && $tretLoc[6] > 0 ? (float) $tretLoc[6] : null,
            'total_end_jd' => $contactsFromSameEvent && $tretLoc[7] > 0 ? (float) $tretLoc[7] : null,
            'partial_end_jd' => $contactsFromSameEvent && $tretLoc[5] > 0 ? (float) $tretLoc[5] : null,
            'penumbral_end_jd' => $contactsFromSameEvent && $tretLoc[3] > 0 ? (float) $tretLoc[3] : null,
        ];
        $visibilityStartJd = $retLoc > 0 && $tretLoc[8] > 0 ? (float) $tretLoc[8] : null;
        $visibilityEndJd = $retLoc > 0 && $tretLoc[9] > 0 ? (float) $tretLoc[9] : null;

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
        $hasLocalContacts = $hasNativeVisibilityWindow || $hasRitualPhase;
        $astroVisible = (int) $attrLoc[8] === JmeEphFFI::JME_ECLIPSE_VISIBLE || $hasRitualPhase;
        $meetsRitualMagnitude = (float) $attr[0] >= self::NIRNAY_LUNAR_ECLIPSE_MINIMUM_MAGNITUDE;

        $ritualVisibleStartJd = $this->maxJd($visibilityStartJd, $localContacts['partial_begin_jd']);
        $ritualVisibleEndJd = $this->minJd($visibilityEndJd, $localContacts['partial_end_jd']);
        if ($ritualVisibleStartJd === null || $ritualVisibleEndJd === null || $ritualVisibleEndJd <= $ritualVisibleStartJd) {
            $ritualVisibleStartJd = $localContacts['partial_begin_jd'];
            $ritualVisibleEndJd = $localContacts['partial_end_jd'];
        }

        $visibleDuration = $ritualVisibleStartJd !== null && $ritualVisibleEndJd !== null ? $ritualVisibleEndJd - $ritualVisibleStartJd : 0.0;
        $meetsDurationThreshold = $visibleDuration > (12.0 / 1440.0);

        $isPenumbralOnly = ($retHow & $this->lunarPenumbralFlag()) !== 0
            && ($retHow & JmeEphFFI::JME_ECLIPSE_LUNAR_PARTIAL) === 0
            && ($retHow & JmeEphFFI::JME_ECLIPSE_LUNAR_TOTAL) === 0;

        $isVisible = $contactsFromSameEvent && $astroVisible && $hasRitualPhase && $hasLocalContacts && $meetsRitualMagnitude && $meetsDurationThreshold && !$isPenumbralOnly;

        $sutakStartAnchor = $ritualVisibleStartJd;
        $sutakEndAnchor = $ritualVisibleEndJd;

        $ritualBoundary = $this->buildRitualBoundaryPayload('Lunar', $ritualVisibleStartJd, $ritualVisibleEndJd, $lat, $lon, $tz, $isVisible);

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
            'visibility' => [
                'visible' => $isVisible,
                'astronomical_visible' => $astroVisible,
                'unaided_eye_ritual_visible' => $isVisible,
                'telescope_only' => $astroVisible && !$isVisible,
                'meets_ritual_magnitude' => $meetsRitualMagnitude,
                'ritual_magnitude_minimum' => self::NIRNAY_LUNAR_ECLIPSE_MINIMUM_MAGNITUDE,
                'local_eclipse_type' => $localType !== null ? Localization::translate('Eclipse', $localType) : null,
                'retflag' => $retHow,
                'window' => $this->formatVisibilityWindow($isVisible ? $ritualVisibleStartJd : null, $isVisible ? $ritualVisibleEndJd : null, $tz),
                'penumbral_window' => $this->formatVisibilityWindow($isVisible ? $visibilityStartJd : null, $isVisible ? $visibilityEndJd : null, $tz),
            ],
            'sutak' => $this->sutak($sutakStartAnchor, $sutakEndAnchor, 3, $lat, $lon, $tz, $isVisible),
            'ritual_boundary' => $ritualBoundary,
            'post_eclipse_ritual' => $this->buildPostEclipseRitualPayload($ritualVisibleEndJd, $tz, $isVisible),
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

        $astroVisible = (int) $attrLoc[8] === JmeEphFFI::JME_ECLIPSE_VISIBLE;
        $diskMagnitude = max((float) $attr[0], (float) $attrLoc[0]);
        $hasVisibleDiskMagnitude = $diskMagnitude > 0.0;
        $meetsRitualMagnitude = $diskMagnitude >= self::NIRNAY_SOLAR_ECLIPSE_MINIMUM_MAGNITUDE;
        // JME local solar search currently exposes local contact times but not
        // separate rise/set truncation markers, so the visible window must be
        // derived from ordered local outer contacts.
        $visibilityWindowStartJd = $localContacts['first_contact_jd'] ?? $localContacts['sunrise_jd'];
        $visibilityWindowEndJd = $localContacts['fourth_contact_jd'] ?? $localContacts['sunset_jd'];
        $hasVisibleWindow = $visibilityWindowStartJd !== null
            && $visibilityWindowEndJd !== null
            && $visibilityWindowEndJd > $visibilityWindowStartJd;

        $visibleDuration = $hasVisibleWindow ? $visibilityWindowEndJd - $visibilityWindowStartJd : 0.0;
        $meetsDurationThreshold = $visibleDuration > (12.0 / 1440.0);

        $isVisible = $contactsFromSameEvent && $astroVisible && $hasVisibleDiskMagnitude && $hasVisibleWindow && $meetsRitualMagnitude && $meetsDurationThreshold;
        $sutakStartAnchor = $visibilityWindowStartJd;
        $sutakEndAnchor = $visibilityWindowEndJd;

        $sep = (float) $attr[2];

        // attr[3] and attr[4] are assumed apparent diameters in arcseconds.
        // /3600 converts arcseconds to degrees, /2 converts diameter to radius.
        $sunR = (float) $attr[3] / 7200.0;
        $moonR = (float) $attr[4] / 7200.0;

        $obscuration = $this->calculateSolarObscuration($sep, $sunR, $moonR);

        $ritualBoundary = $this->buildRitualBoundaryPayload('Solar', $visibilityWindowStartJd, $visibilityWindowEndJd, $lat, $lon, $tz, $isVisible);

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
                'local_eclipse' => (float) $attrLoc[0],
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
            'visibility' => [
                'visible' => $isVisible,
                'astronomical_visible' => $astroVisible,
                'unaided_eye_ritual_visible' => $isVisible,
                'telescope_only' => $astroVisible && !$isVisible,
                'meets_ritual_magnitude' => $meetsRitualMagnitude,
                'ritual_magnitude_minimum' => self::NIRNAY_SOLAR_ECLIPSE_MINIMUM_MAGNITUDE,
                'local_eclipse_type' => $localType !== null ? Localization::translate('Eclipse', $localType) : null,
                'retflag' => $retHow,
                'window' => $this->formatVisibilityWindow(
                    $isVisible ? $visibilityWindowStartJd : null,
                    $isVisible ? $visibilityWindowEndJd : null,
                    $tz
                ),
            ],
            'sutak' => $this->sutak($sutakStartAnchor, $sutakEndAnchor, 4, $lat, $lon, $tz, $isVisible),
            'ritual_boundary' => $ritualBoundary,
            'post_eclipse_ritual' => $this->buildPostEclipseRitualPayload($visibilityWindowEndJd, $tz, $isVisible),
            'retflag' => $retFlag,
        ];
    }

    private function lunarTypeFromCode(int $code): string
    {
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

    private function durationSeconds(?float $fromJd, ?float $toJd): float
    {
        if ($fromJd === null || $toJd === null || $toJd < $fromJd) {
            return 0.0;
        }

        return ($toJd - $fromJd) * 86400.0;
    }

    private function sutak(?float $eclipseStartJd, ?float $eclipseEndJd, int $praharsBefore, float $lat, float $lon, string $tz, bool $isVisible): array
    {
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

        return [
            'applicable' => true,
            'start_jd' => $startJd,
            'end_jd' => $eclipseEndJd,
            'start' => AstroCore::formatDateTime($this->jdToCarbon($startJd, $tz)),
            'end' => AstroCore::formatDateTime($this->jdToCarbon($eclipseEndJd, $tz)),
            'relaxed_start_jd' => $relaxedStartJd,
            'relaxed_end_jd' => $eclipseEndJd,
            'relaxed_start' => AstroCore::formatDateTime($this->jdToCarbon($relaxedStartJd, $tz)),
            'relaxed_end' => AstroCore::formatDateTime($this->jdToCarbon($eclipseEndJd, $tz)),
            'duration_hours' => ($eclipseStartJd - $startJd) * 24.0,
        ];
    }

    private function buildRitualBoundaryPayload(string $eclipseKind, ?float $visibleStartJd, ?float $visibleEndJd, float $lat, float $lon, string $tz, bool $isVisible): array
    {
        if (!$isVisible || $visibleStartJd === null || $visibleEndJd === null) {
            return [
                'type' => 'not_applicable',
                'grast_uday' => false,
                'grast_ast' => false,
                'rule' => 'no_ritual_boundary_rule_without_local_ritual_visibility',
            ];
        }

        $start = $this->jdToCarbon($visibleStartJd, $tz);
        $end = $this->jdToCarbon($visibleEndJd, $tz);
        [$startSunrise, $startSunset] = $this->sunriseSunsetForDate($start->startOfDay(), $lat, $lon, $tz);
        [$endSunrise, $endSunset] = $this->sunriseSunsetForDate($end->startOfDay(), $lat, $lon, $tz);

        // Scriptural Interval Logic: Body is 'already eclipsed' during rise/set
        // Grastodaya: Sparsha (start) < Sunrise < Moksha (end)
        // Grastasta: Sparsha (start) < Sunset < Moksha (end)
        $grastUday = ($visibleStartJd < $this->carbonToJd($startSunrise)) && ($visibleEndJd > $this->carbonToJd($startSunrise));
        $grastAst = ($visibleStartJd < $this->carbonToJd($endSunset)) && ($visibleEndJd > $this->carbonToJd($endSunset));

        return [
            'type' => match (true) {
                $grastUday && $grastAst => 'grast_uday_and_grast_ast',
                $grastUday => 'grast_uday',
                $grastAst => 'grast_ast',
                default => 'ordinary_visible_eclipse',
            },
            'scriptural_instructions' => match (true) {
                $grastUday && $eclipseKind === 'Lunar' => 'Lunar Grastodaya: 4-prahar vedha applies. Do not eat on the previous day.',
                $grastUday && $eclipseKind === 'Solar' => 'Solar Grastodaya: stop food from previous night. After eclipse release, bathe and see the pure sun disc before eating.',
                $grastAst && $eclipseKind === 'Lunar' => 'Lunar Grastasta: Eat only when moon rises again in the evening.',
                $grastAst && $eclipseKind === 'Solar' => 'Solar Grastasta: do not eat that day or following night. Next day after sunrise, bathe, see pure sun disc, then eat.',
                default => sprintf('Ordinary %s Eclipse: Observe standard %d-prahara Sutak.', $eclipseKind, $eclipseKind === 'Lunar' ? 3 : 4),
            },
            'grast_uday' => $grastUday,
            'grast_ast' => $grastAst,
            'visible_start_jd' => $visibleStartJd,
            'visible_start' => AstroCore::formatDateTime($start),
            'visible_end_jd' => $visibleEndJd,
            'visible_end' => AstroCore::formatDateTime($end),
            'sunrise_jd' => $this->carbonToJd($grastUday ? $startSunrise : $endSunrise),
            'sunset_jd' => $this->carbonToJd($grastAst ? $endSunset : $startSunset),
            'rule' => 'visible_at_sunrise_or_sunset_boundary',
        ];
    }

    private function buildPostEclipseRitualPayload(?float $visibleEndJd, string $tz, bool $isVisible): array
    {
        if (!$isVisible || $visibleEndJd === null) {
            return [
                'applicable' => false,
                'snana_required' => false,
                'fresh_food_after_eclipse' => false,
            ];
        }

        return [
            'applicable' => true,
            'snana_required' => true,
            'fresh_food_after_eclipse' => true,
            'starts_after_jd' => $visibleEndJd,
            'starts_after' => AstroCore::formatDateTime($this->jdToCarbon($visibleEndJd, $tz)),
        ];
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
        $containingPraharDurationJd = $this->carbonToJd($boundaries[$containingIndex + 1])
            - $this->carbonToJd($boundaries[$containingIndex]);

        return [
            'start_jd' => $startBoundaryIndex >= 0 ? $this->carbonToJd($boundaries[$startBoundaryIndex]) : null,
            'relaxed_start_jd' => $eclipseStartJd - $containingPraharDurationJd,
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
            // Moon fully covers Sun or more: total/annular-central case.
            if ($moonR >= $sunR) {
                return 1.0;
            }

            // Moon is fully inside Sun disc: annular-style visible dark area.
            return min(1.0, ($moonR * $moonR) / ($sunR * $sunR));
        }

        // Partial overlap area of two circles
        $x1 = (($sep * $sep) + ($sunR * $sunR) - ($moonR * $moonR)) / (2.0 * $sep * $sunR);
        $x2 = (($sep * $sep) + ($moonR * $moonR) - ($sunR * $sunR)) / (2.0 * $sep * $moonR);

        // Prevent acos domain errors from tiny floating point noise.
        $x1 = max(-1.0, min(1.0, $x1));
        $x2 = max(-1.0, min(1.0, $x2));

        $part1 = $sunR * $sunR * acos($x1);
        $part2 = $moonR * $moonR * acos($x2);

        $radicand = (-$sep + $sunR + $moonR) *
                    ($sep + $sunR - $moonR) *
                    ($sep - $sunR + $moonR) *
                    ($sep + $sunR + $moonR);

        $part3 = 0.5 * sqrt(max(0.0, $radicand));

        $overlapArea = $part1 + $part2 - $part3;
        $sunArea = M_PI * $sunR * $sunR;

        return max(0.0, min(1.0, $overlapArea / $sunArea));
    }
}
