<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Panchanga\Vrata;

use JayeshMepani\PanchangCore\Astronomy\Math\IntervalTracker;
use JayeshMepani\PanchangCore\Astronomy\Math\TransitEngine;
use JayeshMepani\PanchangCore\Astronomy\SunService;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Core\Localization;
use JayeshMepani\PanchangCore\Panchanga\KalaNirnayaEngine;

/** Ekadashi Parana Calculator - Handles fasting observance logic. */
class EkadashiParanaCalculator
{
    private const array NIRNAY_PARANA_RESTRICTED_NAKSHATRA_PADAS = [
        'Anuradha' => [1],
        'Shravana' => [2, 3],
        'Revati' => [4],
    ];

    private readonly IntervalTracker $intervalTracker;

    public function __construct(
        private readonly TransitEngine $transitEngine,
        private readonly SunService $sunService
    ) {
        $this->intervalTracker = new IntervalTracker($this->transitEngine, $this->sunService);
    }

    public function buildEkadashiObservance(
        int $tithiNumber,
        float $tithiStartJd,
        float $tithiEndJd,
        float $sunriseJd,
        float $sunsetJd,
        float $nextSunriseJd,
        string $tz,
        float $lat,
        float $lon,
        ?float $previousSunriseJd = null,
        ?string $monthAmanta = null,
        ?string $paksha = null
    ): ?array {
        $phaseTithi = (($tithiNumber - 1) % 15) + 1;
        if (!in_array($phaseTithi, [11, 12], true)) {
            return null;
        }

        $kalaEngine = new KalaNirnayaEngine($lat, $lon);
        $report = $kalaEngine->generateKalaNirnayaReport(
            $tithiNumber,
            $tithiStartJd,
            $tithiEndJd,
            $tithiStartJd,
            $sunriseJd,
            $sunsetJd,
            $nextSunriseJd,
            $previousSunriseJd
        );

        $payload = [
            'phase_tithi_number' => $phaseTithi,
            'phase_tithi_name' => Localization::translate('String', $phaseTithi === 11 ? 'Ekadashi' : 'Dwadashi'),
            'phase_tithi_name_key' => $phaseTithi === 11 ? 'Ekadashi' : 'Dwadashi',
            'viddha_tithi_analysis' => $report['viddha_tithi_analysis'] ?? null,
            'ekadashi_smarta' => $report['ekadashi_smarta'] ?? null,
            'ekadashi_vaishnava' => $report['ekadashi_vaishnava'] ?? null,
            'fasting_guidance' => $this->buildFastingGuidance($phaseTithi, $monthAmanta, $paksha),
        ];

        if ($phaseTithi === 11) {
            $dvadashiEndAngle = ($tithiNumber + 1) * 12.0;
            $dvadashiEndJd = $this->transitEngine->findAngleCrossing(
                $tithiEndJd + 0.000001,
                $dvadashiEndAngle,
                1,
                fn (float $jd): float => $this->transitEngine->getMoonSunAngle($jd)
            );
            $paranaSunsetJd = $this->calculateSunsetForJdDate($nextSunriseJd, $tz, $lat, $lon);
            $payload['parana'] = $this->buildParanaPayload($tithiEndJd, $dvadashiEndJd, $nextSunriseJd, $tz, true, $monthAmanta, $paksha, $paranaSunsetJd);
        } else {
            $payload['parana'] = $this->buildParanaPayload($tithiStartJd, $tithiEndJd, $sunriseJd, $tz, false, $monthAmanta, $paksha, $sunsetJd);
        }

        $payload = $this->localizeEkadashiObservancePayload($payload);

        return $payload;
    }

    public function buildParanaPayload(
        float $dvadashiStartJd,
        float $dvadashiEndJd,
        float $sunriseJd,
        string $tz,
        bool $startsTomorrow,
        ?string $monthAmanta = null,
        ?string $paksha = null,
        ?float $paranaDaySunsetJd = null
    ): array {
        $hariVasaraEndJd = $dvadashiStartJd + (($dvadashiEndJd - $dvadashiStartJd) / 4.0);
        $paranaStartJd = max($sunriseJd, $hariVasaraEndJd);
        $dayDurationJd = $paranaDaySunsetJd !== null && $paranaDaySunsetJd > $sunriseJd
            ? $paranaDaySunsetJd - $sunriseJd
            : 0.5;
        $dvadashiDurationGhatikas = (($dvadashiEndJd - $dvadashiStartJd) * 1440.0) / KalaNirnayaEngine::GHATI_IN_MINUTES;
        $shortDvadashiRule = $this->shortDvadashiRule($dvadashiDurationGhatikas, $sunriseJd, $dvadashiEndJd);
        $restrictedWindows = $this->collectParanaRestrictedWindows($paranaStartJd, $dvadashiEndJd, $tz, $monthAmanta, $paksha);
        $daytimePreferenceRule = $this->buildDaytimePreferenceRule($sunriseJd, $dvadashiEndJd, $paranaStartJd, $dvadashiEndJd, $dayDurationJd);
        $resolvedWindows = $this->resolveParanaWindows(
            $paranaStartJd,
            $dvadashiEndJd,
            $restrictedWindows,
            $daytimePreferenceRule['preferred_end_jd'] ?? null,
            $tz
        );
        $restrictedWindows = $resolvedWindows['restricted_windows'];
        $allowedWindows = $resolvedWindows['allowed_windows'];
        $paranaBasis = $this->classifyParanaBasis($this->activeRestrictedNakshatraPadas($monthAmanta, $paksha), $restrictedWindows);
        $preferredWindows = $this->applyPreferredWindowCap($allowedWindows, $daytimePreferenceRule['preferred_end_jd'] ?? null, $tz);
        $available = $allowedWindows !== [];
        $firstAllowed = $allowedWindows[0] ?? null;
        $lastAllowed = $allowedWindows !== [] ? $allowedWindows[array_key_last($allowedWindows)] : null;
        $firstPreferred = $preferredWindows[0] ?? null;
        $lastPreferred = $preferredWindows !== [] ? $preferredWindows[array_key_last($preferredWindows)] : null;

        return [
            'hari_vasara_start' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($dvadashiStartJd, $tz)),
            'hari_vasara_end' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($hariVasaraEndJd, $tz)),
            'hari_vasara_start_jd' => $dvadashiStartJd,
            'hari_vasara_end_jd' => $hariVasaraEndJd,
            'hari_vasara_classification_key' => $paranaBasis['basis_key'],
            'hari_vasara_classification' => $paranaBasis['basis_label'],
            'has_nakshatra_restrictions' => $paranaBasis['has_nakshatra_restrictions'],
            'parana_day' => Localization::translate('String', $startsTomorrow ? 'Next Day' : 'Today'),
            'parana_day_key' => $startsTomorrow ? 'next_day' : 'today',
            'parana_available' => $available,
            'parana_start' => $firstAllowed !== null ? AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($firstAllowed['start_jd'], $tz)) : null,
            'parana_end' => $lastAllowed !== null ? AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($lastAllowed['end_jd'], $tz)) : null,
            'parana_start_jd' => $firstAllowed['start_jd'] ?? null,
            'parana_end_jd' => $lastAllowed['end_jd'] ?? null,
            'raw_parana_start_jd' => $paranaStartJd,
            'raw_parana_end_jd' => $dvadashiEndJd,
            'dvadashi_duration_ghatikas' => $dvadashiDurationGhatikas,
            'fixed_ghati_minutes' => KalaNirnayaEngine::GHATI_IN_MINUTES,
            'ghati_basis' => 'fixed_elapsed_time_unit',
            'parana_day_dinamana_minutes' => $dayDurationJd * 1440.0,
            'short_dvadashi_rule' => $shortDvadashiRule,
            'symbolic_water_parana_allowed' => $shortDvadashiRule['symbolic_water_parana_allowed'],
            'daytime_preference_rule' => $daytimePreferenceRule,
            'preferred_parana_start' => $firstPreferred !== null ? AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($firstPreferred['start_jd'], $tz)) : null,
            'preferred_parana_end' => $lastPreferred !== null ? AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($lastPreferred['end_jd'], $tz)) : null,
            'preferred_parana_start_jd' => $firstPreferred['start_jd'] ?? null,
            'preferred_parana_end_jd' => $lastPreferred['end_jd'] ?? null,
            'nirnay_restricted_nakshatra_padas' => self::NIRNAY_PARANA_RESTRICTED_NAKSHATRA_PADAS,
            'nirnay_restricted_nakshatra_scope' => $this->restrictedNakshatraScope($monthAmanta, $paksha),
            'restricted_windows' => $restrictedWindows,
            'parana_windows' => $allowedWindows,
            'preferred_parana_windows' => $preferredWindows,
        ];
    }

    /** @return list<array{nakshatra:string, pada:int, start_jd:float, end_jd:float, start:string, end:string}> */
    private function collectParanaRestrictedWindows(float $startJd, float $endJd, string $tz, ?string $monthAmanta, ?string $paksha): array
    {
        if ($endJd <= $startJd) {
            return [];
        }

        $activeRestrictions = $this->activeRestrictedNakshatraPadas($monthAmanta, $paksha);
        if ($activeRestrictions === []) {
            return [];
        }

        $blocked = [];
        foreach ($this->intervalTracker->collectNakshatraPadaIntervals($startJd, $endJd) as $interval) {
            $nakshatra = (string) ($interval['nakshatra'] ?? '');
            $pada = (int) ($interval['pada'] ?? 0);
            if (!in_array($pada, $activeRestrictions[$nakshatra] ?? [], true)) {
                continue;
            }

            $restrictedStartJd = max($startJd, (float) $interval['start_jd']);
            $restrictedEndJd = min($endJd, (float) $interval['end_jd']);
            if ($restrictedEndJd <= $restrictedStartJd) {
                continue;
            }

            $blocked[] = [
                'nakshatra' => $nakshatra,
                'pada' => $pada,
                'start_jd' => $restrictedStartJd,
                'end_jd' => $restrictedEndJd,
                'start' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($restrictedStartJd, $tz)),
                'end' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($restrictedEndJd, $tz)),
            ];
        }

        return $blocked;
    }

    /**
     * @param list<array{nakshatra?:string, pada?:int, start_jd:float, end_jd:float, start?:string, end?:string}> $restrictedWindows
     *
     * @return list<array{nakshatra?:string, pada?:int, start_jd:float, end_jd:float, start?:string, end?:string}>
     */
    private function filterRestrictedWindowsAtParanaStart(float $paranaStartJd, array $restrictedWindows): array
    {
        foreach ($restrictedWindows as $window) {
            if ($window['start_jd'] <= $paranaStartJd && $window['end_jd'] > $paranaStartJd) {
                return [$window];
            }
        }

        return [];
    }

    /**
     * @param list<array{nakshatra?:string, pada?:int, start_jd:float, end_jd:float, start?:string, end?:string}> $restrictedWindows
     *
     * @return array{
     *   restricted_windows:list<array{nakshatra?:string, pada?:int, start_jd:float, end_jd:float, start?:string, end?:string}>,
     *   allowed_windows:list<array{start_jd:float, end_jd:float, start:string, end:string}>
     * }
     */
    private function resolveParanaWindows(
        float $rawParanaStartJd,
        float $rawParanaEndJd,
        array $restrictedWindows,
        ?float $preferredEndJd,
        string $tz
    ): array {
        $activeRestrictedWindows = $this->filterRestrictedWindowsAtParanaStart($rawParanaStartJd, $restrictedWindows);
        $windowStartJd = $rawParanaStartJd;
        if ($activeRestrictedWindows !== []) {
            $windowStartJd = max($windowStartJd, $activeRestrictedWindows[0]['end_jd']);
        }

        $windowEndJd = $preferredEndJd !== null
            ? min($rawParanaEndJd, $preferredEndJd)
            : $rawParanaEndJd;

        if ($windowStartJd >= $windowEndJd) {
            if ($windowStartJd < $rawParanaEndJd) {
                $windowEndJd = $rawParanaEndJd;
            } else {
                $windowStartJd = $rawParanaStartJd;
                $windowEndJd = $rawParanaEndJd;
                $activeRestrictedWindows = [];
            }
        }

        $allowedWindows = $windowEndJd > $windowStartJd
            ? [$this->buildParanaWindow($windowStartJd, $windowEndJd, $tz)]
            : [];

        return [
            'restricted_windows' => $activeRestrictedWindows,
            'allowed_windows' => $allowedWindows,
        ];
    }

    /** @return array<string, list<int>> */
    private function activeRestrictedNakshatraPadas(?string $monthAmanta, ?string $paksha): array
    {
        if ($monthAmanta === null || $paksha === null) {
            return self::NIRNAY_PARANA_RESTRICTED_NAKSHATRA_PADAS;
        }

        if ($paksha !== 'Shukla') {
            return [];
        }

        return match ($this->normalizeMonthName($monthAmanta)) {
            'ashadha' => ['Anuradha' => [1]],
            'bhadrapada', 'bhadarva' => ['Shravana' => [2, 3]],
            'kartika', 'kartik' => ['Revati' => [4]],
            default => [],
        };
    }

    /** @return array{profile:string, month_amanta:?string, paksha:?string} */
    private function restrictedNakshatraScope(?string $monthAmanta, ?string $paksha): array
    {
        return [
            'profile' => $monthAmanta === null || $paksha === null ? 'global_nirnay_fallback' : 'gujarati_month_paksha_specific',
            'month_amanta' => $monthAmanta,
            'paksha' => $paksha,
        ];
    }

    /** @return array{profile:string, guidance_keys:list<string>, guidance:list<string>, source_refs:list<string>} */
    private function buildFastingGuidance(int $phaseTithi, ?string $monthAmanta, ?string $paksha): array
    {
        $guidanceKeys = [
            'satsangi_ekadashi_standard_fast_guidance',
            'satsangi_ekadashi_unable_allowance_guidance',
        ];

        if ($phaseTithi === 11 && $paksha === 'Shukla' && $this->normalizeMonthName((string) $monthAmanta) === 'kartika') {
            $guidanceKeys[] = 'satsangi_prabodhini_strict_fast_guidance';
        }

        return [
            'profile' => 'satsangi_jeevan_ekadashi',
            'guidance_keys' => $guidanceKeys,
            'guidance' => array_map(
                static fn (string $key): string => Localization::translate('String', $key),
                $guidanceKeys
            ),
            'source_refs' => ['Satsangi Jeevan 3.32.84-87', 'Satsangi Jeevan 3.32.160-175'],
        ];
    }

    /**
     * @param array<string, list<int>> $activeRestrictions
     * @param list<array{nakshatra:string, pada:int, start_jd?:float, end_jd?:float, start?:string, end?:string}> $restrictedWindows
     *
     * @return array{basis_key:string, basis_label:string, has_nakshatra_restrictions:bool}
     */
    private function classifyParanaBasis(array $activeRestrictions, array $restrictedWindows): array
    {
        $hasRestrictions = $activeRestrictions !== [] && $restrictedWindows !== [];

        return [
            'basis_key' => $hasRestrictions ? 'harivasara_nakshatra_restricted' : 'tithyavasara',
            'basis_label' => $hasRestrictions ? 'Harivasara' : 'Tithyavasara',
            'has_nakshatra_restrictions' => $hasRestrictions,
        ];
    }

    /** @return array{category:string, must_break_before_dvadashi_end:bool, symbolic_water_parana_allowed:bool} */
    private function shortDvadashiRule(float $dvadashiDurationGhatikas, float $sunriseJd, float $dvadashiEndJd): array
    {
        $category = match (true) {
            $dvadashiDurationGhatikas <= 1.0 => 'ati_alpa_dvadashi',
            $dvadashiDurationGhatikas < 6.0 => 'short_dvadashi',
            default => 'normal_dvadashi',
        };

        return [
            'category' => $category,
            'must_break_before_dvadashi_end' => $dvadashiEndJd > $sunriseJd,
            'symbolic_water_parana_allowed' => $category === 'ati_alpa_dvadashi',
        ];
    }

    /**
     * @return array{
     *   rule_key:string,
     *   applies:bool,
     *   preferred_end_jd:?float,
     *   preferred_duration_ghatikas:?float
     * }
     */
    private function buildDaytimePreferenceRule(float $sunriseJd, float $dvadashiEndJd, float $rawParanaStartJd, ?float $firstAllowedEndJd, float $dayDurationJd): array
    {
        $madhyahnaJd = $sunriseJd + ($dayDurationJd / 2.0);
        $dynamicGhatiMinutes = ($dayDurationJd * 1440.0) / 30.0;
        $preferredEndJd = $sunriseJd + ($dayDurationJd / 5.0);
        $applies = $dvadashiEndJd > $madhyahnaJd && $preferredEndJd > $rawParanaStartJd;

        if ($firstAllowedEndJd !== null) {
            $applies = $applies && $firstAllowedEndJd > $rawParanaStartJd;
        }

        return [
            'rule_key' => $applies ? 'pratah_kala_first_six_ghatis' : 'standard_dvadashi_parana',
            'applies' => $applies,
            'preferred_end_jd' => $applies ? $preferredEndJd : null,
            'preferred_duration_ghatikas' => $applies ? 6.0 : null,
            'dynamic_ghati_minutes' => $applies ? $dynamicGhatiMinutes : null,
            'madhyahna_basis' => 'dynamic_dinamana_midpoint',
            'preferred_duration_basis' => 'dynamic_dinamana_30_ghati_day',
        ];
    }

    private function calculateSunsetForJdDate(float $jd, string $tz, float $lat, float $lon): float
    {
        $date = $this->sunService->jdToCarbonPublic($jd, $tz);
        [, $sunset] = $this->sunService->getSunriseSunset([
            'year' => $date->year,
            'month' => $date->month,
            'day' => $date->day,
            'hour' => 12,
            'minute' => 0,
            'second' => 0,
            'latitude' => $lat,
            'longitude' => $lon,
            'timezone' => $tz,
            'elevation' => 0.0,
        ]);

        return AstroCore::toJulianDay($sunset);
    }

    private function normalizeMonthName(string $month): string
    {
        $month = preg_replace('/\s*\(.*?\)\s*/', '', trim($month)) ?? trim($month);
        $month = strtr($month, [
            'Ā' => 'A', 'ā' => 'a',
            'Ś' => 'Sh', 'ś' => 'sh',
            'ṣ' => 'sh', 'Ṣ' => 'Sh',
        ]);

        return strtolower((string) preg_replace('/[^A-Za-z]/', '', $month));
    }

    /**
     * @param list<array{start_jd:float, end_jd:float}> $restrictedWindows
     *
     * @return list<array{start_jd:float, end_jd:float, start:string, end:string}>
     */
    private function subtractRestrictedWindows(float $startJd, float $endJd, array $restrictedWindows, string $tz): array
    {
        if ($endJd <= $startJd) {
            return [];
        }

        $allowed = [];
        $cursor = $startJd;
        foreach ($restrictedWindows as $window) {
            $blockedStart = max($startJd, $window['start_jd']);
            $blockedEnd = min($endJd, $window['end_jd']);
            if ($blockedStart > $cursor) {
                $allowed[] = $this->buildParanaWindow($cursor, $blockedStart, $tz);
            }

            $cursor = max($cursor, $blockedEnd);
        }

        if ($cursor < $endJd) {
            $allowed[] = $this->buildParanaWindow($cursor, $endJd, $tz);
        }

        return $allowed;
    }

    /** @return array{start_jd:float, end_jd:float, start:string, end:string} */
    private function buildParanaWindow(float $startJd, float $endJd, string $tz): array
    {
        return [
            'start_jd' => $startJd,
            'end_jd' => $endJd,
            'start' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($startJd, $tz)),
            'end' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($endJd, $tz)),
        ];
    }

    /**
     * @param list<array{start_jd:float, end_jd:float, start:string, end:string}> $allowedWindows
     *
     * @return list<array{start_jd:float, end_jd:float, start:string, end:string}>
     */
    private function applyPreferredWindowCap(array $allowedWindows, ?float $preferredEndJd, string $tz): array
    {
        if ($preferredEndJd === null) {
            return [];
        }

        $preferred = [];
        foreach ($allowedWindows as $window) {
            $startJd = $window['start_jd'];
            $endJd = min($window['end_jd'], $preferredEndJd);
            if ($endJd <= $startJd) {
                continue;
            }

            $preferred[] = $this->buildParanaWindow($startJd, $endJd, $tz);
        }

        return $preferred;
    }

    private function localizeEkadashiObservancePayload(array $payload): array
    {
        if (isset($payload['viddha_tithi_analysis']) && is_array($payload['viddha_tithi_analysis'])) {
            $payload['viddha_tithi_analysis']['status_label'] = Localization::translate(
                'String',
                (string) ($payload['viddha_tithi_analysis']['status'] ?? '')
            );
            $payload['viddha_tithi_analysis']['tithi_name_label'] = Localization::translate(
                'String',
                (string) ($payload['viddha_tithi_analysis']['tithi_name'] ?? '')
            );
        }

        foreach (['ekadashi_smarta', 'ekadashi_vaishnava'] as $key) {
            if (!isset($payload[$key]) || !is_array($payload[$key])) {
                continue;
            }

            $payload[$key]['tradition_label'] = Localization::translate('String', (string) ($payload[$key]['tradition'] ?? ''));
            $payload[$key]['status_label'] = Localization::translate('String', (string) ($payload[$key]['status'] ?? ''));
            $payload[$key]['fasting_day_label'] = Localization::translate('String', (string) ($payload[$key]['fasting_day'] ?? ''));
        }

        if (isset($payload['parana']) && is_array($payload['parana'])) {
            $payload['parana']['hari_vasara_classification_label'] = Localization::translate(
                'String',
                (string) ($payload['parana']['hari_vasara_classification'] ?? '')
            );

            if (isset($payload['parana']['daytime_preference_rule']) && is_array($payload['parana']['daytime_preference_rule'])) {
                $payload['parana']['daytime_preference_rule']['rule_label'] = Localization::translate(
                    'String',
                    (string) ($payload['parana']['daytime_preference_rule']['rule_key'] ?? '')
                );
            }
        }

        return $payload;
    }
}
