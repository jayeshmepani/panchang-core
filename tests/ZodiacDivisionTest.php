<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Tests;

use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Core\Enums\Nakshatra;
use JayeshMepani\PanchangCore\Core\Enums\Rasi;
use JayeshMepani\PanchangCore\Core\ZodiacDivision;
use PHPUnit\Framework\TestCase;

final class ZodiacDivisionTest extends TestCase
{
    public function test_exact_boundaries_belong_to_the_next_division(): void
    {
        $this->assertSame(1, ZodiacDivision::rashiIndex(30.0));
        $this->assertSame(0, ZodiacDivision::rashiIndex(29.999999999999996));
        $this->assertSame(11, ZodiacDivision::rashiIndex(359.99999999999994));
        $this->assertSame(0, ZodiacDivision::rashiIndex(360.0));
        $this->assertSame(11, ZodiacDivision::rashiIndex(-30.0));
        $this->assertSame(11, ZodiacDivision::rashiIndex(-1.0e-300));
        $this->assertSame(3, ZodiacDivision::nakshatraIndex(40.0));
        $this->assertSame(1, ZodiacDivision::nakshatraIndex(360.0 / 27.0));
        $this->assertSame(26, ZodiacDivision::nakshatraIndex(359.99999999999994));
        $this->assertSame(2, ZodiacDivision::nakshatraPada(30.0));
        $this->assertSame(4, ZodiacDivision::nakshatraPada(10.0));
        $this->assertSame(1, ZodiacDivision::nakshatraPada(40.0));
    }

    public function test_enums_and_core_use_the_exact_division(): void
    {
        $this->assertSame(1, AstroCore::getSign(30.0));
        $this->assertSame(Rasi::from(1), Rasi::fromLongitude(30.0));
        $this->assertSame(Nakshatra::from(3), Nakshatra::fromLongitude(40.0));
        $this->assertSame(Nakshatra::from(26), Nakshatra::fromLongitude(359.99999999999994));
        $this->assertSame(2, Nakshatra::getPada(30.0));
    }
}
