<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Shraddha;

use InvalidArgumentException;

/**
 * Classical daylight windows used by pitru rites.
 *
 * Daylight is divided into fifteen equal muhurtas. The eighth is Kutapa,
 * the ninth Rauhina, and the usual fivefold-day Aparahna is muhurtas 10-12.
 * The five-muhurta shraddha span is therefore Kutapa through the end of
 * Aparahna (muhurtas 8-12).
 */
final class ShraddhaKalaCalculator
{
    /** @return array{start_jd:float,end_jd:float} */
    public function kutapa(array $details): array
    {
        [$sunrise, , $dayMuhurta] = $this->dayParts($details);

        return [
            'start_jd' => $sunrise + (7.0 * $dayMuhurta),
            'end_jd' => $sunrise + (8.0 * $dayMuhurta),
        ];
    }

    /** @return array{start_jd:float,end_jd:float} */
    public function rauhina(array $details): array
    {
        [$sunrise, , $dayMuhurta] = $this->dayParts($details);

        return [
            'start_jd' => $sunrise + (8.0 * $dayMuhurta),
            'end_jd' => $sunrise + (9.0 * $dayMuhurta),
        ];
    }

    /** Fivefold-day Aparahna = muhurtas 10-12. */
    /** @return array{start_jd:float,end_jd:float} */
    public function aparahna(array $details): array
    {
        [$sunrise, , $dayMuhurta] = $this->dayParts($details);

        return [
            'start_jd' => $sunrise + (9.0 * $dayMuhurta),
            'end_jd' => $sunrise + (12.0 * $dayMuhurta),
        ];
    }

    /** Kutapa through end of Aparahna = muhurtas 8-12. */
    /** @return array{start_jd:float,end_jd:float} */
    public function shraddhaMuhurtaPanchaka(array $details): array
    {
        [$sunrise, , $dayMuhurta] = $this->dayParts($details);

        return [
            'start_jd' => $sunrise + (7.0 * $dayMuhurta),
            'end_jd' => $sunrise + (12.0 * $dayMuhurta),
        ];
    }

    /** @return array{0:float,1:float,2:float} sunrise, sunset, one day-muhurta */
    private function dayParts(array $details): array
    {
        $ctx = (array) ($details['Resolution_Context'] ?? []);
        $sunrise = $ctx['sunrise_jd'] ?? null;
        $sunset = $ctx['sunset_jd'] ?? null;

        if (!is_numeric($sunrise) || !is_numeric($sunset) || (float) $sunset <= (float) $sunrise) {
            throw new InvalidArgumentException('Shraddha kala requires valid sunrise_jd and sunset_jd.');
        }

        $sunrise = (float) $sunrise;
        $sunset = (float) $sunset;

        return [$sunrise, $sunset, ($sunset - $sunrise) / 15.0];
    }
}
