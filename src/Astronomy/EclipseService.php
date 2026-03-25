<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Astronomy;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Core\AstroCore;
use SwissEph\FFI\SwissEphFFI;

class EclipseService
{
    private static string $ephePath = '';

    public function __construct(private SwissEphFFI $sweph)
    {
        $ephePath = self::$ephePath ?: (function_exists('config') ? config('panchang.ephe_path', getenv('PANCHANG_EPHE_PATH') ?: '') : (getenv('PANCHANG_EPHE_PATH') ?: ''));
        if (is_string($ephePath) && $ephePath !== '' && file_exists($ephePath)) {
            $this->sweph->swe_set_ephe_path($ephePath);
        }
    }

    /**
     * Configure service (optional, for standalone usage).
     *
     * @param string $ephePath Ephemeris path (empty for default)
     * @param string $ayanamsaMode Ayanamsa mode ('LAHIRI', 'RAMAN', 'KRISHNAMURTI')
     */
    public static function configure(string $ephePath = '', string $ayanamsaMode = 'LAHIRI'): void
    {
        self::$ephePath = $ephePath;
    }

    public function getEclipsesForYear(int $year, float $lat, float $lon, string $tz): array
    {
        $start = $this->sweph->swe_julday($year, 1, 1, 0.0, SwissEphFFI::SE_GREG_CAL);
        $end = $this->sweph->swe_julday($year + 1, 1, 1, 0.0, SwissEphFFI::SE_GREG_CAL);

        $events = [];
        $seen = [];
        $cursor = $start - 1e-6;

        while ($cursor < $end) {
            $nextLunar = $this->nextLunarEclipse($cursor, $lat, $lon, $tz);
            $nextSolar = $this->nextSolarEclipse($cursor, $lat, $lon, $tz);

            $candidates = array_values(array_filter([$nextLunar, $nextSolar], static fn ($v) => is_array($v)));
            if ($candidates === []) {
                break;
            }

            usort($candidates, static fn (array $a, array $b): int => $a['jd'] <=> $b['jd']);
            $pick = $candidates[0];

            if (($pick['jd'] ?? $end + 1.0) >= $end) {
                break;
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
        $tret = $this->sweph->getFFI()->new('double[10]');
        $serr = $this->sweph->getFFI()->new('char[256]');

        $retFlag = $this->sweph->swe_lun_eclipse_when(
            $startJd,
            SwissEphFFI::SEFLG_SWIEPH,
            SwissEphFFI::SE_ECL_ALLTYPES_LUNAR,
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
        $tret = $this->sweph->getFFI()->new('double[10]');
        $serr = $this->sweph->getFFI()->new('char[256]');

        $retFlag = $this->sweph->swe_sol_eclipse_when_glob(
            $startJd,
            SwissEphFFI::SEFLG_SWIEPH,
            SwissEphFFI::SE_ECL_ALLTYPES_SOLAR,
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
        $serr = $this->sweph->getFFI()->new('char[256]');

        $attr = $this->sweph->getFFI()->new('double[40]');
        $retHow = $this->sweph->swe_lun_eclipse_how($jdMax, SwissEphFFI::SEFLG_SWIEPH, $geo, $attr, $serr);

        $tretLoc = $this->sweph->getFFI()->new('double[10]');
        $attrLoc = $this->sweph->getFFI()->new('double[20]');
        $retLoc = $this->sweph->swe_lun_eclipse_when_loc($jdMax - 1.0, SwissEphFFI::SEFLG_SWIEPH, $geo, $tretLoc, $attrLoc, 0, $serr);
        $contactsFromSameEvent = $retLoc > 0 && $tretLoc[0] > 0 && abs((float) $tretLoc[0] - $jdMax) < 0.5;

        $type = 'Penumbral';
        if (($retFlag & SwissEphFFI::SE_ECL_TOTAL) !== 0) {
            $type = 'Total';
        } elseif (($retFlag & SwissEphFFI::SE_ECL_PARTIAL) !== 0) {
            $type = 'Partial';
        }

        $contacts = [
            'penumbral_begin_jd' => $contactsFromSameEvent && $tretLoc[6] > 0 ? (float) $tretLoc[6] : null,
            'partial_begin_jd' => $contactsFromSameEvent && $tretLoc[2] > 0 ? (float) $tretLoc[2] : null,
            'total_begin_jd' => $contactsFromSameEvent && $tretLoc[4] > 0 ? (float) $tretLoc[4] : null,
            'maximum_jd' => (float) $jdMax,
            'total_end_jd' => $contactsFromSameEvent && $tretLoc[5] > 0 ? (float) $tretLoc[5] : null,
            'partial_end_jd' => $contactsFromSameEvent && $tretLoc[3] > 0 ? (float) $tretLoc[3] : null,
            'penumbral_end_jd' => $contactsFromSameEvent && $tretLoc[7] > 0 ? (float) $tretLoc[7] : null,
        ];

        $dt = $this->jdToCarbon($jdMax, $tz);

        $astroVisible = $retHow > 0 && (($retHow & SwissEphFFI::SE_ECL_VISIBLE) !== 0);
        $hasRitualPhase = (($retFlag & SwissEphFFI::SE_ECL_PARTIAL) !== 0) || (($retFlag & SwissEphFFI::SE_ECL_TOTAL) !== 0);
        $hasLocalContacts = $contacts['partial_begin_jd'] !== null && $contacts['partial_end_jd'] !== null;
        $isVisible = $astroVisible && $contactsFromSameEvent && $hasRitualPhase && $hasLocalContacts;
        $sutakStartAnchor = $contacts['partial_begin_jd'];
        $sutakEndAnchor = $contacts['partial_end_jd'];

        return [
            'type' => 'Lunar',
            'eclipse_type' => $type,
            'date' => $dt->toDateString(),
            'datetime' => AstroCore::formatDateTime($dt),
            'jd' => AstroCore::r9($jdMax),
            'magnitudes' => [
                'umbral' => AstroCore::r9((float) $attr[0]),
                'penumbral' => AstroCore::r9((float) $attr[1]),
            ],
            'contacts' => $this->formatContactTimes($contacts, $tz),
            'durations' => [
                'penumbral_seconds' => AstroCore::r9($this->durationSeconds($contacts['penumbral_begin_jd'], $contacts['penumbral_end_jd'])),
                'partial_seconds' => AstroCore::r9($this->durationSeconds($contacts['partial_begin_jd'], $contacts['partial_end_jd'])),
                'total_seconds' => AstroCore::r9($this->durationSeconds($contacts['total_begin_jd'], $contacts['total_end_jd'])),
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
        $serr = $this->sweph->getFFI()->new('char[256]');

        $attr = $this->sweph->getFFI()->new('double[40]');
        $retHow = $this->sweph->swe_sol_eclipse_how($jdMax, SwissEphFFI::SEFLG_SWIEPH, $geo, $attr, $serr);

        $tretLoc = $this->sweph->getFFI()->new('double[10]');
        $attrLoc = $this->sweph->getFFI()->new('double[20]');
        $retLoc = $this->sweph->swe_sol_eclipse_when_loc($jdMax - 1.0, SwissEphFFI::SEFLG_SWIEPH, $geo, $tretLoc, $attrLoc, 0, $serr);
        $contactsFromSameEvent = $retLoc > 0 && $tretLoc[0] > 0 && abs((float) $tretLoc[0] - $jdMax) < 0.5;

        $type = 'Partial';
        if (($retFlag & SwissEphFFI::SE_ECL_TOTAL) !== 0) {
            $type = 'Total';
        } elseif (($retFlag & SwissEphFFI::SE_ECL_ANNULAR) !== 0) {
            $type = 'Annular';
        } elseif (($retFlag & SwissEphFFI::SE_ECL_ANNULAR_TOTAL) !== 0) {
            $type = 'Annular-Total';
        }

        $contacts = [
            'first_contact_jd' => $contactsFromSameEvent && $tretLoc[1] > 0 ? (float) $tretLoc[1] : null,
            'second_contact_jd' => $contactsFromSameEvent && $tretLoc[2] > 0 ? (float) $tretLoc[2] : null,
            'maximum_jd' => (float) $jdMax,
            'third_contact_jd' => $contactsFromSameEvent && $tretLoc[3] > 0 ? (float) $tretLoc[3] : null,
            'fourth_contact_jd' => $contactsFromSameEvent && $tretLoc[4] > 0 ? (float) $tretLoc[4] : null,
            'sunrise_jd' => $contactsFromSameEvent && $tretLoc[5] > 0 ? (float) $tretLoc[5] : null,
            'sunset_jd' => $contactsFromSameEvent && $tretLoc[6] > 0 ? (float) $tretLoc[6] : null,
        ];

        $dt = $this->jdToCarbon($jdMax, $tz);

        $astroVisible = $retHow > 0 && (($retHow & SwissEphFFI::SE_ECL_VISIBLE) !== 0);
        $hasLocalContacts = $contacts['first_contact_jd'] !== null && $contacts['fourth_contact_jd'] !== null;
        $hasVisibleDiskMagnitude = (float) $attr[0] > 0.0;
        $isVisible = $astroVisible && $contactsFromSameEvent && $hasLocalContacts && $hasVisibleDiskMagnitude;
        $sutakStartAnchor = $contacts['first_contact_jd'];
        $sutakEndAnchor = $contacts['fourth_contact_jd'];

        return [
            'type' => 'Solar',
            'eclipse_type' => $type,
            'date' => $dt->toDateString(),
            'datetime' => AstroCore::formatDateTime($dt),
            'jd' => AstroCore::r9($jdMax),
            'magnitudes' => [
                'eclipse' => AstroCore::r9((float) $attr[0]),
                'obscuration' => AstroCore::r9((float) $attr[2]),
            ],
            'contacts' => $this->formatContactTimes($contacts, $tz),
            'durations' => [
                'partial_seconds' => AstroCore::r9($this->durationSeconds($contacts['first_contact_jd'], $contacts['fourth_contact_jd'])),
                'total_seconds' => AstroCore::r9($this->durationSeconds($contacts['second_contact_jd'], $contacts['third_contact_jd'])),
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
                'reason' => 'eclipse_not_visible_at_location',
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

    private function newGeoPos(float $lat, float $lon)
    {
        $geo = $this->sweph->getFFI()->new('double[3]');
        $geo[0] = $lon;
        $geo[1] = $lat;
        $geo[2] = 0.0;
        return $geo;
    }

    private function jdToCarbon(float $jd, string $tz): CarbonImmutable
    {
        $y = $this->sweph->getFFI()->new('int[1]');
        $m = $this->sweph->getFFI()->new('int[1]');
        $d = $this->sweph->getFFI()->new('int[1]');
        $h = $this->sweph->getFFI()->new('int[1]');
        $i = $this->sweph->getFFI()->new('int[1]');
        $s = $this->sweph->getFFI()->new('double[1]');

        $this->sweph->swe_jdut1_to_utc($jd, SwissEphFFI::SE_GREG_CAL, $y, $m, $d, $h, $i, $s);

        $sec = (int) floor($s[0]);
        $micros = (int) floor(($s[0] - $sec) * 1_000_000.0);

        return CarbonImmutable::create((int) $y[0], (int) $m[0], (int) $d[0], (int) $h[0], (int) $i[0], $sec, 'UTC')
            ->addMicroseconds($micros)
            ->setTimezone($tz);
    }
}
