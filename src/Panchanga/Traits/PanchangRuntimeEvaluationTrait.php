<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Panchanga\Traits;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Core\Localization;

trait PanchangRuntimeEvaluationTrait
{
    private function evaluateCurrentBhadra(CarbonImmutable $at, array $bhadraPeriods): array
    {
        $active = null;
        $activePart = null;

        foreach ($bhadraPeriods as $period) {
            if (!is_array($period)) {
                continue;
            }

            $periodStart = isset($period['start_time_iso']) ? $this->parseDisplayDateTime((string) $period['start_time_iso'], $at->timezoneName) : null;
            $periodEnd = isset($period['end_time_iso']) ? $this->parseDisplayDateTime((string) $period['end_time_iso'], $at->timezoneName) : null;
            if (!$periodStart instanceof CarbonImmutable || !$periodEnd instanceof CarbonImmutable || $at < $periodStart || $at >= $periodEnd) {
                continue;
            }

            $active = $period;
            foreach (['mukha', 'madhya', 'puchha'] as $partKey) {
                $part = (array) (($period['parts'] ?? [])[$partKey] ?? []);
                $partStart = isset($part['start_time_iso']) ? $this->parseDisplayDateTime((string) $part['start_time_iso'], $at->timezoneName) : null;
                $partEnd = isset($part['end_time_iso']) ? $this->parseDisplayDateTime((string) $part['end_time_iso'], $at->timezoneName) : null;
                if ($partStart instanceof CarbonImmutable && $partEnd instanceof CarbonImmutable && $at >= $partStart && $at < $partEnd) {
                    $activePart = $partKey;
                    break;
                }
            }

            break;
        }

        $hasDosha = $activePart === 'mukha' || $activePart === 'madhya';
        $severity = $activePart === 'mukha' ? 'critical' : ($activePart === 'madhya' ? 'high' : 'none');

        return [
            'source' => Localization::translate('Source', 'Muhurta Martanda / Bhadra (Vishti Karana) window from Panchang day calculation'),
            'is_active' => $active !== null,
            'active_part' => $activePart,
            'active_period' => $active,
            'has_dosha' => $hasDosha,
            'severity' => $severity,
            'is_auspicious' => !$hasDosha,
            'description' => $active === null
                ? Localization::translate('MuhurtaDesc', 'Bhadra not active')
                : ($hasDosha ? Localization::translate('MuhurtaDesc', 'Bhadra active blocked') : Localization::translate('MuhurtaDesc', 'Bhadra puchha active')),
        ];
    }

    private function evaluateCurrentVarjyam(CarbonImmutable $at, array $varjyam, string $tz): array
    {
        $windows = [];
        if (isset($varjyam['windows']) && is_array($varjyam['windows']) && $varjyam['windows'] !== []) {
            $windows = $varjyam['windows'];
        } elseif ($varjyam !== []) {
            $windows = [$varjyam];
        }

        $activeWindow = null;
        foreach ($windows as $window) {
            if (!is_array($window)) {
                continue;
            }

            $start = null;
            $end = null;
            if (isset($window['window_start_jd'], $window['window_end_jd'])) {
                $start = $this->sunService->jdToCarbonPublic((float) $window['window_start_jd'], $tz);
                $end = $this->sunService->jdToCarbonPublic((float) $window['window_end_jd'], $tz);
                $window['window_start_iso'] ??= AstroCore::formatDateTime($start);
                $window['window_end_iso'] ??= AstroCore::formatDateTime($end);
            } else {
                $start = $this->resolveNamedWindowBoundary($window, 'varjyam_start', $tz);
                $end = $this->resolveNamedWindowBoundary($window, 'varjyam_end', $tz);
            }

            if ($start instanceof CarbonImmutable && $end instanceof CarbonImmutable && $at >= $start && $at < $end) {
                $activeWindow = $window;
                break;
            }
        }

        $isActive = $activeWindow !== null;

        return [
            'source' => Localization::translate('Source', 'Varjyam (Tyajyam) window from Panchang day calculation'),
            'is_active' => $isActive,
            'is_available' => $windows !== [],
            'period_is_auspicious' => false,
            'is_current_time_safe' => !$isActive,
            'is_currently_blocking' => $isActive,
            'has_dosha' => $isActive,
            'active_window' => $activeWindow,
            'window_count' => count($windows),
            'severity' => $isActive ? 'high' : 'none',
            'is_auspicious' => false,
            'description' => $isActive ? Localization::translate('MuhurtaDesc', 'Varjyam active') : Localization::translate('MuhurtaDesc', 'Varjyam not active'),
        ];
    }

    private function evaluateCurrentNamedWindow(
        CarbonImmutable $at,
        array $window,
        string $startKey,
        string $endKey,
        string $label,
        string $source
    ): array {
        $start = $this->resolveNamedWindowBoundary($window, $startKey, $at->timezoneName);
        $end = $this->resolveNamedWindowBoundary($window, $endKey, $at->timezoneName);

        if (!$start instanceof CarbonImmutable || !$end instanceof CarbonImmutable) {
            return [
                'source' => $source,
                'label' => $label,
                'is_active' => false,
                'is_available' => false,
                'period_is_auspicious' => true,
                'is_currently_auspicious' => false,
                'is_auspicious' => false,
                'description' => $label . ' ' . Localization::translate('MuhurtaDesc', 'Named window not available'),
            ];
        }

        $isActive = $at >= $start && $at < $end;

        return [
            'source' => $source,
            'label' => $label,
            'is_active' => $isActive,
            'is_available' => true,
            'period_is_auspicious' => true,
            'is_currently_auspicious' => $isActive,
            'is_auspicious' => true,
            'window' => [
                'start_iso' => AstroCore::formatDateTime($start),
                'end_iso' => AstroCore::formatDateTime($end),
            ],
            'description' => $isActive
                ? $label . ' ' . Localization::translate('MuhurtaDesc', 'Named window active')
                : $label . ' ' . Localization::translate('MuhurtaDesc', 'Named window not active'),
        ];
    }

    private function toDecimalHoursFromBase(CarbonImmutable $dt, CarbonImmutable $base): float
    {
        return ($dt->getTimestamp() - $base->getTimestamp()) / 3600.0;
    }

    private function resolveNamedWindowBoundary(array $window, string $key, string $tz): ?CarbonImmutable
    {
        $isoKey = $key . '_iso';
        if (isset($window[$isoKey]) && is_string($window[$isoKey]) && $window[$isoKey] !== '') {
            return $this->parseDisplayDateTime($window[$isoKey], $tz);
        }

        if (isset($window[$key]) && is_string($window[$key]) && $window[$key] !== '') {
            return $this->parseDisplayDateTime($window[$key], $tz);
        }

        return null;
    }
}
