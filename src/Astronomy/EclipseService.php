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

    public function __construct(private JmeEphFFI $jme)
    {
        $this->initializeEphemerisPath($this->jme);
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

        $events = [];
        $seen = [];
        $cursor = $start - 1e-6;
        $maxIterations = 2000;
        $iteration = 0;

        while ($cursor < $end && $iteration < $maxIterations) {
            $iteration++;
            $nextLunar = $this->nextLunarEclipse($cursor, $lat, $lon, $tz);
            $nextSolar = $this->nextSolarEclipse($cursor, $lat, $lon, $tz);

            $candidates = array_values(array_filter([$nextLunar, $nextSolar], is_array(...)));
            if ($candidates === []) {
                // JME *_when functions can report "no eclipse near supplied date"
                // for a given lunation. Advance cursor and continue scanning.
                $cursor += 14.0;
                continue;
            }

            usort($candidates, static fn (array $a, array $b): int => $a['jd'] <=> $b['jd']);
            $pick = $candidates[0];

            if (($pick['jd'] ?? $end + 1.0) >= $end) {
                break;
            }

            if ((float) $pick['jd'] <= $cursor) {
                // Defensive progression guard against non-advancing candidates.
                $cursor += 0.5;
                continue;
            }

            $hash = strtolower((string) $pick['type']) . ':' . number_format((float) $pick['jd'], 6, '.', '');
            if (!isset($seen[$hash])) {
                $events[] = $pick;
                $seen[$hash] = true;
            }

            $cursor = (float) $pick['jd'] + 0.01;
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

        return $this->buildLunarEvent((float) $tret[0], $retFlag, $lat, $lon, $tz);
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

        return $this->buildSolarEvent((float) $tret[0], $retFlag, $lat, $lon, $tz);
    }

    private function buildLunarEvent(float $jdMax, int $retFlag, float $lat, float $lon, string $tz): array
    {
        $geo = $this->newGeoPos($lat, $lon);
        $serr = $this->jme->getFFI()->new('char[256]');

        $attr = $this->jme->getFFI()->new('double[40]');
        $retHow = $this->jme->jme_lun_eclipse_how($jdMax, JmeEphFFI::JME_CALC_HIGH_PRECISION, $geo, $attr, $serr);

        $tretLoc = $this->jme->getFFI()->new('double[10]');
        $attrLoc = $this->jme->getFFI()->new('double[20]');
        $retLoc = $this->jme->jme_lun_eclipse_when_loc($jdMax - 1.0, JmeEphFFI::JME_CALC_HIGH_PRECISION, $geo, $tretLoc, $attrLoc, 0, $serr);
        $contactsFromSameEvent = $retLoc > 0 && $tretLoc[0] > 0 && abs((float) $tretLoc[0] - $jdMax) < 0.5;
        $contacts = [
            'penumbral_begin_jd' => $contactsFromSameEvent && $tretLoc[2] > 0 ? (float) $tretLoc[2] : null,
            'partial_begin_jd' => $contactsFromSameEvent && $tretLoc[4] > 0 ? (float) $tretLoc[4] : null,
            'total_begin_jd' => $contactsFromSameEvent && $tretLoc[6] > 0 ? (float) $tretLoc[6] : null,
            'maximum_jd' => $jdMax,
            'total_end_jd' => $contactsFromSameEvent && $tretLoc[7] > 0 ? (float) $tretLoc[7] : null,
            'partial_end_jd' => $contactsFromSameEvent && $tretLoc[5] > 0 ? (float) $tretLoc[5] : null,
            'penumbral_end_jd' => $contactsFromSameEvent && $tretLoc[3] > 0 ? (float) $tretLoc[3] : null,
        ];

        $hasLocalPartialWindow = $contacts['partial_begin_jd'] !== null
            && $contacts['partial_end_jd'] !== null
            && $contacts['partial_end_jd'] > $contacts['partial_begin_jd'];
        $hasLocalTotalWindow = $contacts['total_begin_jd'] !== null
            && $contacts['total_end_jd'] !== null
            && $contacts['total_end_jd'] > $contacts['total_begin_jd'];

        $type = 'Penumbral';
        if ($hasLocalTotalWindow) {
            $type = 'Total';
        } elseif ($hasLocalPartialWindow) {
            $type = 'Partial';
        }

        $dt = $this->jdToCarbon($jdMax, $tz);

        $astroVisible = (int) $attrLoc[8] === JmeEphFFI::JME_ECLIPSE_VISIBLE;
        $hasRitualPhase = $hasLocalPartialWindow || $hasLocalTotalWindow;
        $hasLocalContacts = $hasLocalPartialWindow;
        $isVisible = $astroVisible && $contactsFromSameEvent && $hasRitualPhase && $hasLocalContacts;
        $sutakStartAnchor = $contacts['partial_begin_jd'];
        $sutakEndAnchor = $contacts['partial_end_jd'];

        return [
            'type' => Localization::translate('String', 'Lunar'),
            'eclipse_type' => Localization::translate('Eclipse', $type),
            'date' => $dt->toDateString(),
            'datetime' => AstroCore::formatDateTime($dt),
            'jd' => $jdMax,
            'magnitudes' => [
                'umbral' => (float) $attr[0],
                'penumbral' => (float) $attr[1],
            ],
            'contacts' => $this->formatContactTimes($contacts, $tz),
            'durations' => [
                'penumbral_seconds' => $this->durationSeconds($contacts['penumbral_begin_jd'], $contacts['penumbral_end_jd']),
                'partial_seconds' => $this->durationSeconds($contacts['partial_begin_jd'], $contacts['partial_end_jd']),
                'total_seconds' => $this->durationSeconds($contacts['total_begin_jd'], $contacts['total_end_jd']),
            ],
            'visibility' => [
                'visible' => $isVisible,
                'astronomical_visible' => $astroVisible,
                'retflag' => $retHow,
            ],
            'sutak' => $this->sutak($sutakStartAnchor, $sutakEndAnchor, 9.0, $tz, $isVisible),
            'retflag' => $retFlag,
        ];
    }

    private function buildSolarEvent(float $jdMax, int $retFlag, float $lat, float $lon, string $tz): array
    {
        $geo = $this->newGeoPos($lat, $lon);
        $serr = $this->jme->getFFI()->new('char[256]');

        $attr = $this->jme->getFFI()->new('double[40]');
        $retHow = $this->jme->jme_sol_eclipse_how($jdMax, JmeEphFFI::JME_CALC_HIGH_PRECISION, $geo, $attr, $serr);

        $tretLoc = $this->jme->getFFI()->new('double[10]');
        $attrLoc = $this->jme->getFFI()->new('double[20]');
        $retLoc = $this->jme->jme_sol_eclipse_when_loc($jdMax - 1.0, JmeEphFFI::JME_CALC_HIGH_PRECISION, $geo, $tretLoc, $attrLoc, 0, $serr);
        $contactsFromSameEvent = $retLoc > 0 && $tretLoc[0] > 0 && abs((float) $tretLoc[0] - $jdMax) < 0.5;

        $eventTypeCode = $contactsFromSameEvent ? $retLoc : $retFlag;

        $type = 'Partial';
        if ($eventTypeCode === JmeEphFFI::JME_ECLIPSE_SOLAR_TOTAL) {
            $type = 'Total';
        } elseif ($eventTypeCode === JmeEphFFI::JME_ECLIPSE_SOLAR_ANNULAR) {
            $type = 'Annular';
        } elseif ($eventTypeCode === JmeEphFFI::JME_ECLIPSE_SOLAR_HYBRID) {
            $type = 'Annular-Total';
        } elseif ($eventTypeCode === JmeEphFFI::JME_ECLIPSE_SOLAR_PARTIAL) {
            $type = 'Partial';
        }

        $contacts = [
            'first_contact_jd' => $contactsFromSameEvent && $tretLoc[2] > 0 ? (float) $tretLoc[2] : null,
            'second_contact_jd' => $contactsFromSameEvent && $tretLoc[4] > 0 ? (float) $tretLoc[4] : null,
            'maximum_jd' => $jdMax,
            'third_contact_jd' => $contactsFromSameEvent && $tretLoc[5] > 0 ? (float) $tretLoc[5] : null,
            'fourth_contact_jd' => $contactsFromSameEvent && $tretLoc[3] > 0 ? (float) $tretLoc[3] : null,
            'sunrise_jd' => null,
            'sunset_jd' => null,
        ];

        $dt = $this->jdToCarbon($jdMax, $tz);

        $astroVisible = (int) $attrLoc[8] === JmeEphFFI::JME_ECLIPSE_VISIBLE;
        $hasAnyVisibleContact = $contacts['first_contact_jd'] !== null
            || $contacts['second_contact_jd'] !== null
            || $contacts['third_contact_jd'] !== null
            || $contacts['fourth_contact_jd'] !== null;
        $hasVisibleDiskMagnitude = max((float) $attr[0], (float) $attrLoc[0]) > 0.0;
        $isVisible = $astroVisible && $contactsFromSameEvent && $hasAnyVisibleContact && $hasVisibleDiskMagnitude;
        $sutakStartAnchor = $contacts['first_contact_jd'] ?? $contacts['sunrise_jd'];
        $sutakEndAnchor = $contacts['fourth_contact_jd'] ?? $contacts['sunset_jd'];

        return [
            'type' => Localization::translate('String', 'Solar'),
            'eclipse_type' => Localization::translate('Eclipse', $type),
            'date' => $dt->toDateString(),
            'datetime' => AstroCore::formatDateTime($dt),
            'jd' => $jdMax,
            'magnitudes' => [
                'eclipse' => (float) $attr[0],
                'obscuration' => (float) $attr[2],
            ],
            'contacts' => $this->formatContactTimes($contacts, $tz),
            'durations' => [
                'partial_seconds' => $this->durationSeconds($contacts['first_contact_jd'], $contacts['fourth_contact_jd']),
                'total_seconds' => $this->durationSeconds($contacts['second_contact_jd'], $contacts['third_contact_jd']),
            ],
            'visibility' => [
                'visible' => $isVisible,
                'astronomical_visible' => $astroVisible,
                'retflag' => $retHow,
            ],
            'sutak' => $this->sutak($sutakStartAnchor, $sutakEndAnchor, 12.0, $tz, $isVisible),
            'retflag' => $retFlag,
        ];
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

    private function durationSeconds(?float $fromJd, ?float $toJd): float
    {
        if ($fromJd === null || $toJd === null || $toJd < $fromJd) {
            return 0.0;
        }

        return ($toJd - $fromJd) * 86400.0;
    }

    private function sutak(?float $eclipseStartJd, ?float $eclipseEndJd, float $hoursBefore, string $tz, bool $isVisible): array
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

        $startJd = $eclipseStartJd - ($hoursBefore / 24.0);
        $relaxedStartJd = $eclipseStartJd - (3.0 / 24.0);

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
            'duration_hours' => $hoursBefore,
        ];
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
}
