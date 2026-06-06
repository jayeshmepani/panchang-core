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
        ];

        if ($phaseTithi === 11) {
            $dvadashiEndAngle = ($tithiNumber + 1) * 12.0;
            $dvadashiEndJd = $this->transitEngine->findAngleCrossing(
                $tithiEndJd + 0.000001,
                $dvadashiEndAngle,
                1,
                fn (float $jd): float => $this->transitEngine->getMoonSunAngle($jd)
            );
            $payload['parana'] = $this->buildParanaPayload($tithiEndJd, $dvadashiEndJd, $nextSunriseJd, $tz, true, $monthAmanta, $paksha);
        } else {
            $payload['parana'] = $this->buildParanaPayload($tithiStartJd, $tithiEndJd, $sunriseJd, $tz, false, $monthAmanta, $paksha);
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
        ?string $paksha = null
    ): array {
        $hariVasaraEndJd = $dvadashiStartJd + (($dvadashiEndJd - $dvadashiStartJd) / 4.0);
        $paranaStartJd = max($sunriseJd, $hariVasaraEndJd);
        $dvadashiDurationGhatikas = (($dvadashiEndJd - $dvadashiStartJd) * 1440.0) / KalaNirnayaEngine::GHATI_IN_MINUTES;
        $shortDvadashiRule = $this->shortDvadashiRule($dvadashiDurationGhatikas, $sunriseJd, $dvadashiEndJd);
        $restrictedWindows = $this->collectParanaRestrictedWindows($paranaStartJd, $dvadashiEndJd, $tz, $monthAmanta, $paksha);
        $allowedWindows = $this->subtractRestrictedWindows($paranaStartJd, $dvadashiEndJd, $restrictedWindows, $tz);
        $available = $allowedWindows !== [];
        $firstAllowed = $allowedWindows[0] ?? null;
        $lastAllowed = $allowedWindows !== [] ? $allowedWindows[array_key_last($allowedWindows)] : null;

        return [
            'hari_vasara_start' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($dvadashiStartJd, $tz)),
            'hari_vasara_end' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($hariVasaraEndJd, $tz)),
            'hari_vasara_start_jd' => $dvadashiStartJd,
            'hari_vasara_end_jd' => $hariVasaraEndJd,
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
            'short_dvadashi_rule' => $shortDvadashiRule,
            'symbolic_water_parana_allowed' => $shortDvadashiRule['symbolic_water_parana_allowed'],
            'nirnay_restricted_nakshatra_padas' => self::NIRNAY_PARANA_RESTRICTED_NAKSHATRA_PADAS,
            'nirnay_restricted_nakshatra_scope' => $this->restrictedNakshatraScope($monthAmanta, $paksha),
            'restricted_windows' => $restrictedWindows,
            'parana_windows' => $allowedWindows,
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

        return $payload;
    }
}
