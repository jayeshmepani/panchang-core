<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Muhurta\Regional;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Core\Enums\VimshottariDasha;
use JayeshMepani\PanchangCore\Core\Localization;

/** Gowri Panchangam Calculator - Handles South Indian regional calculations. */
class GowriPanchangamCalculator
{
    private const array WEEKDAY_PLANET_ORDER = ['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn'];

    public function calculateGowriPanchangam(
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise,
        int $varaIdx
    ): array {
        $dayDuration = ($sunset->getTimestamp() - $sunrise->getTimestamp()) / 8.0;
        $nightDuration = ($nextSunrise->getTimestamp() - $sunset->getTimestamp()) / 8.0;

        $dayRows = [];
        $labels = [
            0 => ['Uthi', 'Amirdha', 'Rogam', 'Laabam', 'Dhanam', 'Sugam', 'Soram', 'Visham'],
            1 => ['Amirdha', 'Visham', 'Rogam', 'Laabam', 'Dhanam', 'Sugam', 'Soram', 'Uthi'],
            2 => ['Rogam', 'Laabam', 'Dhanam', 'Sugam', 'Soram', 'Uthi', 'Visham', 'Amirdha'],
            3 => ['Laabam', 'Dhanam', 'Sugam', 'Soram', 'Visham', 'Uthi', 'Amirdha', 'Rogam'],
            4 => ['Dhanam', 'Sugam', 'Soram', 'Uthi', 'Amirdha', 'Visham', 'Rogam', 'Laabam'],
            5 => ['Sugam', 'Soram', 'Uthi', 'Visham', 'Amirdha', 'Rogam', 'Laabam', 'Dhanam'],
            6 => ['Soram', 'Uthi', 'Visham', 'Amirdha', 'Rogam', 'Laabam', 'Dhanam', 'Sugam'],
        ][$varaIdx] ?? ['Uthi', 'Amirdha', 'Rogam', 'Laabam', 'Dhanam', 'Sugam', 'Soram', 'Visham'];

        $gowriLabelsEn = [
            'Amirdha' => ['quality' => 'best', 'is_auspicious' => true],
            'Dhanam' => ['quality' => 'wealth', 'is_auspicious' => true],
            'Uthi' => ['quality' => 'good', 'is_auspicious' => true],
            'Laabam' => ['quality' => 'gain', 'is_auspicious' => true],
            'Sugam' => ['quality' => 'good', 'is_auspicious' => true],
            'Rogam' => ['quality' => 'evil', 'is_auspicious' => false],
            'Soram' => ['quality' => 'bad', 'is_auspicious' => false],
            'Visham' => ['quality' => 'bad', 'is_auspicious' => false],
        ];

        foreach ($labels as $idx => $lbl) {
            $start = $this->addFloatSeconds($sunrise, $idx * $dayDuration);
            $dayRows[] = $this->buildTimedRow($start, $dayDuration, [
                'division' => $idx + 1,
                'label' => Localization::translate('Gowri', $lbl),
                'quality' => Localization::translate('GowriQuality', $gowriLabelsEn[$lbl]['quality']),
                'is_auspicious' => $gowriLabelsEn[$lbl]['is_auspicious'],
                'is_day' => true,
            ]);
        }

        $nightRows = [];
        $labels = [
            0 => ['Dhanam', 'Sugam', 'Soram', 'Visham', 'Uthi', 'Amirdha', 'Rogam', 'Laabam'],
            1 => ['Sugam', 'Soram', 'Uthi', 'Amirdha', 'Visham', 'Rogam', 'Laabam', 'Dhanam'],
            2 => ['Soram', 'Uthi', 'Visham', 'Amirdha', 'Rogam', 'Laabam', 'Dhanam', 'Sugam'],
            3 => ['Uthi', 'Amirdha', 'Rogam', 'Laabam', 'Dhanam', 'Sugam', 'Soram', 'Visham'],
            4 => ['Amirdha', 'Visham', 'Rogam', 'Laabam', 'Dhanam', 'Sugam', 'Soram', 'Uthi'],
            5 => ['Rogam', 'Laabam', 'Dhanam', 'Sugam', 'Soram', 'Uthi', 'Visham', 'Amirdha'],
            6 => ['Laabam', 'Dhanam', 'Sugam', 'Soram', 'Uthi', 'Visham', 'Amirdha', 'Soram'],
        ][$varaIdx] ?? ['Dhanam', 'Sugam', 'Soram', 'Visham', 'Uthi', 'Amirdha', 'Rogam', 'Laabam'];

        foreach ($labels as $idx => $lbl) {
            $start = $this->addFloatSeconds($sunset, $idx * $nightDuration);
            $nightRows[] = $this->buildTimedRow($start, $nightDuration, [
                'division' => $idx + 1,
                'label' => Localization::translate('Gowri', $lbl),
                'quality' => Localization::translate('GowriQuality', $gowriLabelsEn[$lbl]['quality']),
                'is_auspicious' => $gowriLabelsEn[$lbl]['is_auspicious'],
                'is_day' => false,
            ]);
        }

        return [
            'source' => Localization::translate('Source', 'Published Gowri/Pambu table convention'),
            'day' => $dayRows,
            'night' => $nightRows,
            'auspicious_labels' => array_map(fn (string $l): string => Localization::translate('Gowri', $l), ['Amirdha', 'Dhanam', 'Uthi', 'Laabam', 'Sugam']),
            'inauspicious_labels' => array_map(fn (string $l): string => Localization::translate('Gowri', $l), ['Rogam', 'Soram', 'Visham']),
        ];
    }

    public function calculateKalaVela(
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise,
        int $varaIdx
    ): array {
        $dayDuration = ($sunset->getTimestamp() - $sunrise->getTimestamp()) / 8.0;
        $nightDuration = ($nextSunrise->getTimestamp() - $sunset->getTimestamp()) / 8.0;

        $dayPortions = [];
        for ($i = 0; $i < 8; $i++) {
            $start = $this->addFloatSeconds($sunrise, $i * $dayDuration);
            $planet = $i < 7 ? self::WEEKDAY_PLANET_ORDER[($varaIdx + $i) % 7] : null;
            $dayPortions[] = $this->buildTimedRow($start, $dayDuration, [
                'division' => $i + 1,
                'planetary_lord' => $planet !== null ? VimshottariDasha::from($this->getPlanetIndex($planet))->getName() : null,
                'is_optional_eighth_portion' => $i === 7,
                'planetary_lord_en' => $planet,
            ]);
        }

        $nightPortions = [];
        for ($i = 0; $i < 8; $i++) {
            $start = $this->addFloatSeconds($sunset, $i * $nightDuration);
            $planet = $i < 7 ? self::WEEKDAY_PLANET_ORDER[($varaIdx + 4 + $i) % 7] : null;
            $nightPortions[] = $this->buildTimedRow($start, $nightDuration, [
                'division' => $i + 1,
                'planetary_lord' => $planet !== null ? VimshottariDasha::from($this->getPlanetIndex($planet))->getName() : null,
                'is_optional_eighth_portion' => $i === 7,
                'planetary_lord_en' => $planet,
            ]);
        }

        return [
            'named_kala_velas' => [
                'kala' => $this->extractKalaVelaWindows('Sun', $dayPortions, $nightPortions),
                'mrityu' => $this->extractKalaVelaWindows('Mars', $dayPortions, $nightPortions),
                'ardhaprahara' => $this->extractKalaVelaWindows('Mercury', $dayPortions, $nightPortions),
                'yamaghantaka' => $this->extractKalaVelaWindows('Jupiter', $dayPortions, $nightPortions),
                'gulika' => $this->extractKalaVelaWindows('Saturn', $dayPortions, $nightPortions),
            ],
            'day' => $dayPortions,
            'night' => $nightPortions,
        ];
    }

    private function extractKalaVelaWindows(string $planet, array $dayPortions, array $nightPortions): array
    {
        $matches = [];
        foreach (array_merge($dayPortions, $nightPortions) as $portion) {
            if (($portion['planetary_lord_en'] ?? null) !== $planet) {
                continue;
            }

            $matches[] = [
                'division' => $portion['division'],
                'start' => $portion['start'],
                'end' => $portion['end'],
                'start_iso' => $portion['start_iso'],
                'end_iso' => $portion['end_iso'],
            ];
        }

        return $matches;
    }

    private function getPlanetIndex(string $name): int
    {
        return match ($name) {
            'Sun' => 0, 'Moon' => 1, 'Mars' => 2, 'Rahu' => 3, 'Jupiter' => 4, 'Saturn' => 5, 'Mercury' => 6, 'Ketu' => 7, 'Venus' => 8,
            default => 0
        };
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
