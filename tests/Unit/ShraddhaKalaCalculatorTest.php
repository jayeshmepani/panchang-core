<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Tests\Unit;

use JayeshMepani\PanchangCore\Shraddha\ShraddhaKalaCalculator;
use PHPUnit\Framework\TestCase;

final class ShraddhaKalaCalculatorTest extends TestCase
{
    public function test_classical_daylight_windows_use_fifteen_equal_muhurtas(): void
    {
        $calculator = new ShraddhaKalaCalculator;
        $details = [
            'Resolution_Context' => [
                'sunrise_jd' => 100.0,
                'sunset_jd' => 100.5,
            ],
        ];

        $muhurta = 0.5 / 15.0;
        self::assertEqualsWithDelta(100.0 + 7 * $muhurta, $calculator->kutapa($details)['start_jd'], 1e-12);
        self::assertEqualsWithDelta(100.0 + 8 * $muhurta, $calculator->rauhina($details)['start_jd'], 1e-12);
        self::assertEqualsWithDelta(100.0 + 9 * $muhurta, $calculator->aparahna($details)['start_jd'], 1e-12);
        self::assertEqualsWithDelta(100.0 + 12 * $muhurta, $calculator->shraddhaMuhurtaPanchaka($details)['end_jd'], 1e-12);
    }
}
