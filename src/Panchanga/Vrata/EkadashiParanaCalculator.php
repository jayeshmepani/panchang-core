<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Panchanga\Vrata;

use JayeshMepani\PanchangCore\Astronomy\Math\TransitEngine;
use JayeshMepani\PanchangCore\Astronomy\SunService;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Core\Localization;
use JayeshMepani\PanchangCore\Panchanga\KalaNirnayaEngine;

/** Ekadashi Parana Calculator - Handles fasting observance logic. */
class EkadashiParanaCalculator
{
    public function __construct(
        private readonly TransitEngine $transitEngine,
        private readonly SunService $sunService
    ) {
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
        float $lon
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
            $nextSunriseJd
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
            $payload['parana'] = $this->buildParanaPayload($tithiEndJd, $dvadashiEndJd, $nextSunriseJd, $tz, true);
        } else {
            $payload['parana'] = $this->buildParanaPayload($tithiStartJd, $tithiEndJd, $sunriseJd, $tz, false);
        }

        $payload = $this->localizeEkadashiObservancePayload($payload);

        return $payload;
    }

    public function buildParanaPayload(
        float $dvadashiStartJd,
        float $dvadashiEndJd,
        float $sunriseJd,
        string $tz,
        bool $startsTomorrow
    ): array {
        $hariVasaraEndJd = $dvadashiStartJd + (($dvadashiEndJd - $dvadashiStartJd) / 4.0);
        $paranaStartJd = max($sunriseJd, $hariVasaraEndJd);
        $available = $paranaStartJd < $dvadashiEndJd;

        return [
            'hari_vasara_start' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($dvadashiStartJd, $tz)),
            'hari_vasara_end' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($hariVasaraEndJd, $tz)),
            'hari_vasara_start_jd' => $dvadashiStartJd,
            'hari_vasara_end_jd' => $hariVasaraEndJd,
            'parana_day' => Localization::translate('String', $startsTomorrow ? 'Next Day' : 'Today'),
            'parana_day_key' => $startsTomorrow ? 'next_day' : 'today',
            'parana_available' => $available,
            'parana_start' => $available ? AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($paranaStartJd, $tz)) : null,
            'parana_end' => $available ? AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($dvadashiEndJd, $tz)) : null,
            'parana_start_jd' => $available ? $paranaStartJd : null,
            'parana_end_jd' => $available ? $dvadashiEndJd : null,
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
