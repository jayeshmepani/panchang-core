<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Tests;

use JayeshMepani\PanchangCore\Astronomy\SunService;
use JmeEph\FFI\JmeEphFFI;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class PanchangaAstronomyConventionTest extends TestCase
{
    public function testPanchangaSunriseUsesVisibleUpperLimbWithStandardRefraction(): void
    {
        $sunService = new ReflectionClass(SunService::class);

        $riseSetFlags = $sunService->getConstant('PANCHANGA_VISIBLE_UPPER_LIMB_FLAGS');
        $pressure = $sunService->getConstant('STANDARD_ATMOSPHERIC_PRESSURE_HPA');
        $temperature = $sunService->getConstant('STANDARD_ATMOSPHERIC_TEMPERATURE_C');

        $this->assertSame(0, $riseSetFlags);
        $this->assertSame(1013.25, $pressure);
        $this->assertSame(15.0, $temperature);
        $this->assertSame(0, $riseSetFlags & JmeEphFFI::JME_RISE_DISC_CENTER);
        $this->assertSame(0, $riseSetFlags & JmeEphFFI::JME_RISE_DISC_BOTTOM);
        $this->assertSame(0, $riseSetFlags & JmeEphFFI::JME_RISE_HINDU_RISING);
        $this->assertSame(0, $riseSetFlags & JmeEphFFI::JME_RISE_NO_REFRACTION);
    }

    public function testPanchangaCoreLongitudesStayGeocentricSidereal(): void
    {
        $sourceFiles = [
            dirname(__DIR__) . '/src/Panchanga/Traits/PanchangAstronomyHelpersTrait.php',
            dirname(__DIR__) . '/src/Panchanga/Traits/PanchangBirthMonthHelpersTrait.php',
            dirname(__DIR__) . '/src/Astronomy/Math/TransitEngine.php',
        ];

        foreach ($sourceFiles as $sourceFile) {
            $source = file_get_contents($sourceFile);

            $this->assertIsString($source);
            $this->assertStringContainsString('JME_CALC_SIDEREAL', $source);
            $this->assertStringNotContainsString('JME_CALC_TOPOCENTRIC', $source);
        }
    }
}
