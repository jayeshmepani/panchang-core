<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Muhurta\Planetary;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Core\Enums\Hora;
use JayeshMepani\PanchangCore\Core\Enums\Vara;

/** Hora Calculator - Handles planetary hour divisions. */
class HoraCalculator
{
    public function calculateHora(
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise,
        CarbonImmutable $current,
        int $varaIdx
    ): array {
        $seq = Hora::getSequence(Vara::from($varaIdx));
        $isDay = ($current >= $sunrise) && ($current < $sunset);

        if ($isDay) {
            $durationTotal = $sunset->getTimestamp() - $sunrise->getTimestamp();
            $elapsed = $current->getTimestamp() - $sunrise->getTimestamp();
            $horaDuration = $durationTotal / 12.0;
            $horaIdx = (int) floor($elapsed / $horaDuration);
            $baseOffset = 0;
        } else {
            $durationTotal = $nextSunrise->getTimestamp() - $sunset->getTimestamp();
            $elapsed = $current->getTimestamp() - $sunset->getTimestamp();
            $horaDuration = $durationTotal / 12.0;
            $horaIdx = (int) floor($elapsed / $horaDuration);
            $baseOffset = 12;
        }

        if ($horaIdx >= 12) {
            $horaIdx = 11;
        }

        $currentHora = $seq[($baseOffset + $horaIdx) % 24];

        return [
            'hora_number' => $baseOffset + $horaIdx + 1,
            'is_day_hora' => $isDay,
            'ruler' => $currentHora->getName(),
            'hora_duration_seconds' => $horaDuration,
            'hora_duration_minutes' => AstroCore::formatDuration($horaDuration / 60.0),
        ];
    }

    public function calculateHoraTable(
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise,
        int $varaIdx
    ): array {
        $seq = Hora::getSequence(Vara::from($varaIdx));
        $dayDuration = ($sunset->getTimestamp() - $sunrise->getTimestamp()) / 12.0;
        $nightDuration = ($nextSunrise->getTimestamp() - $sunset->getTimestamp()) / 12.0;

        $rows = [];
        for ($i = 0; $i < 24; $i++) {
            $isDayHora = $i < 12;
            $duration = $isDayHora ? $dayDuration : $nightDuration;
            if ($isDayHora) {
                $start = $this->addFloatSeconds($sunrise, $i * $duration);
            } else {
                $start = $this->addFloatSeconds($sunset, ($i - 12) * $duration);
            }

            $rows[] = $this->buildTimedRow($start, $duration, [
                'hora_number' => $i + 1,
                'is_day_hora' => $isDayHora,
                'ruler' => $seq[$i]->getName(),
            ]);
        }

        return $rows;
    }

    private function addFloatSeconds(CarbonImmutable $dt, float $seconds): CarbonImmutable
    {
        $whole = (int) floor($seconds);
        $fraction = $seconds - $whole;

        return $dt->addSeconds($whole)->addMicroseconds((int) ($fraction * 1_000_000));
    }

    private function buildTimedRow(CarbonImmutable $start, float $duration, array $payload): array
    {
        $end = $this->addFloatSeconds($start, $duration);

        return $payload + [
            'start' => AstroCore::formatTime($start),
            'end' => AstroCore::formatTime($end),
            'start_iso' => AstroCore::formatDateTime($start),
            'end_iso' => AstroCore::formatDateTime($end),
            'duration_seconds' => $duration,
        ];
    }
}
