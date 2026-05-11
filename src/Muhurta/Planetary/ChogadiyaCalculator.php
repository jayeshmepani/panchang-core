<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Muhurta\Planetary;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Core\Enums\Choghadiya;
use JayeshMepani\PanchangCore\Core\Enums\Vara;
use JayeshMepani\PanchangCore\Core\Localization;

/** Chogadiya Calculator - Handles 1/8th day divisions. */
class ChogadiyaCalculator
{
    public function calculateChogadiya(
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise,
        CarbonImmutable $current,
        int $varaIdx
    ): array {
        $vara = Vara::from($varaIdx);
        $isDay = ($current >= $sunrise) && ($current < $sunset);

        if ($isDay) {
            $durationTotal = $sunset->getTimestamp() - $sunrise->getTimestamp();
            $elapsed = $current->getTimestamp() - $sunrise->getTimestamp();
        } else {
            $durationTotal = ($nextSunrise->getTimestamp() - $sunset->getTimestamp());
            $elapsed = $current->getTimestamp() - $sunset->getTimestamp();
        }

        $divDuration = $durationTotal / 8.0;
        $divIdx = (int) floor($elapsed / $divDuration);
        if ($divIdx >= 8) {
            $divIdx = 7;
        }

        $pattern = $isDay ? Choghadiya::getDaySequence($vara) : Choghadiya::getNightSequence($vara);
        $choghadiya = $pattern[$divIdx];

        return [
            'mode' => $isDay ? Localization::translate('String', 'Day') : Localization::translate('String', 'Night'),
            'division' => $divIdx + 1,
            'name' => $choghadiya->getName(),
            'is_auspicious' => $choghadiya->isAuspicious(),
            'division_duration_minutes' => AstroCore::formatDuration($divDuration / 60.0),
        ];
    }

    public function calculateChogadiyaTable(
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise,
        int $varaIdx
    ): array {
        $vara = Vara::from($varaIdx);
        $rows = [];
        $dayDuration = ($sunset->getTimestamp() - $sunrise->getTimestamp()) / 8.0;
        $nightDuration = ($nextSunrise->getTimestamp() - $sunset->getTimestamp()) / 8.0;
        $dayPattern = Choghadiya::getDaySequence($vara);
        $nightPattern = Choghadiya::getNightSequence($vara);

        for ($i = 0; $i < 8; $i++) {
            $start = $this->addFloatSeconds($sunrise, $i * $dayDuration);
            $choghadiya = $dayPattern[$i];

            $rows[] = $this->buildTimedRow($start, $dayDuration, [
                'mode' => Localization::translate('String', 'Day'),
                'division' => $i + 1,
                'name' => $choghadiya->getName(),
                'is_auspicious' => $choghadiya->isAuspicious(),
            ]);
        }

        for ($i = 0; $i < 8; $i++) {
            $start = $this->addFloatSeconds($sunset, $i * $nightDuration);
            $choghadiya = $nightPattern[$i];

            $rows[] = $this->buildTimedRow($start, $nightDuration, [
                'mode' => Localization::translate('String', 'Night'),
                'division' => $i + 1,
                'name' => $choghadiya->getName(),
                'is_auspicious' => $choghadiya->isAuspicious(),
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
