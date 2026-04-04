<?php

/**
 * Panchanga Calculations Test Suite.
 *
 * Tests for:
 * - Tithi calculations
 * - Nakshatra calculations
 * - Yoga calculations
 * - Karana calculations
 * - Vara calculations
 * - Muhurta calculations
 */

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Tests;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Astronomy\SunService;
use JayeshMepani\PanchangCore\Core\Enums\Vara;
use JayeshMepani\PanchangCore\Panchanga\PanchangaEngine;
use PHPUnit\Framework\TestCase;

class PanchangaTest extends TestCase
{
    private PanchangaEngine $panchanga;

    protected function setUp(): void
    {
        $this->panchanga = new PanchangaEngine;
    }

    /** Test Tithi calculation */
    public function testCalculateTithi(): void
    {
        // Test case: New Moon (Sun = Moon)
        $tithi = $this->panchanga->calculateTithi(0.0, 0.0);
        $this->assertEquals(1, $tithi['index']);
        $this->assertEquals('Shukla', $tithi['paksha']);

        // Test case: Full Moon (180 degrees apart)
        $tithi = $this->panchanga->calculateTithi(0.0, 180.0);
        $this->assertEquals(16, $tithi['index']);
        $this->assertEquals('Krishna', $tithi['paksha']);

        // Test case: 12 degrees = 2nd tithi
        $tithi = $this->panchanga->calculateTithi(0.0, 12.0);
        $this->assertEquals(2, $tithi['index']);

        // Test case: 359.999 = 30th tithi (Amavasya)
        $tithi = $this->panchanga->calculateTithi(0.0, 359.999);
        $this->assertEquals(30, $tithi['index']);
        $this->assertEquals('Krishna', $tithi['paksha']);
    }

    /** Test Yoga calculation */
    public function testCalculateYoga(): void
    {
        // Test case: Sun + Moon = 0
        $yoga = $this->panchanga->calculateYoga(0.0, 0.0);
        $this->assertEquals(1, $yoga['index']);

        // Test case: Sun + Moon = 360
        $yoga = $this->panchanga->calculateYoga(180.0, 180.0);
        $this->assertEquals(1, $yoga['index']);

        // Test case: Sun + Moon = 13.333 (2nd yoga)
        $yoga = $this->panchanga->calculateYoga(6.667, 6.667);
        $this->assertEquals(2, $yoga['index']);
    }

    /** Test Nakshatra calculation */
    public function testGetNakshatraInfo(): void
    {
        // Test case: Ashwini (0-13.333 degrees)
        [$name, $pada, $lord] = $this->panchanga->getNakshatraInfo(6.0);
        $this->assertEquals('Ashwini', $name);
        $this->assertEquals(2, $pada);

        // Test case: Bharani (13.333-26.667 degrees)
        [$name, $pada, $lord] = $this->panchanga->getNakshatraInfo(20.0);
        $this->assertEquals('Bharani', $name);

        // Test case: Krittika (26.667-40.0 degrees)
        [$name, $pada, $lord] = $this->panchanga->getNakshatraInfo(30.0);
        $this->assertEquals('Krittika', $name);
    }

    /** Test Karana calculation */
    public function testGetKarana(): void
    {
        // Test case: 1st tithi, 1st half
        [$name, $idx] = $this->panchanga->getKarana(0.0, 6.0);
        $this->assertEquals(2, $idx);

        // Test case: 1st tithi, 2nd half
        [$name, $idx] = $this->panchanga->getKarana(0.0, 11.9);
        $this->assertEquals(2, $idx);
    }

    /** Test Vara (weekday) calculation */
    public function testCalculateVara(): void
    {
        $birth = [
            'year' => 2026,
            'month' => 4,
            'day' => 6,
            'hour' => 7,
            'minute' => 0,
            'second' => 0,
            'timezone' => 'Asia/Kolkata',
            'latitude' => 23.2472446,
            'longitude' => 69.668339,
        ];

        $sunrise = CarbonImmutable::create(2026, 4, 6, 6, 0, 0, 'Asia/Kolkata');
        $birthDt = CarbonImmutable::create(2026, 4, 6, 7, 0, 0, 'Asia/Kolkata');

        /** @var \PHPUnit\Framework\MockObject\MockObject&SunService $sunService */
        $sunService = $this->createMock(SunService::class);
        $sunService->method('getSunriseSunset')->willReturn([$sunrise, $sunrise->addHours(12)]);
        $sunService->method('getBirthDatetime')->willReturn($birthDt);

        $vara = $this->panchanga->calculateVara($birth, $sunService);

        $this->assertEquals(1, $vara['index']);
        $this->assertEquals(Vara::from(1)->getName(), $vara['name']);
    }

    /** Test precision of calculations */
    public function testCalculationPrecision(): void
    {
        // Test that calculations maintain precision
        $tithi1 = $this->panchanga->calculateTithi(0.0, 11.999999);
        $tithi2 = $this->panchanga->calculateTithi(0.0, 12.000001);

        // Should be different tithis
        $this->assertNotEquals($tithi1['index'], $tithi2['index']);

        // Test fraction precision
        $this->assertIsFloat($tithi1['fraction_left']);
        $this->assertGreaterThan(0.0, $tithi1['fraction_left']);
        $this->assertLessThan(1.0, $tithi1['fraction_left']);
    }

    /** Test edge cases */
    public function testEdgeCases(): void
    {
        // Test at 0 degrees
        $tithi = $this->panchanga->calculateTithi(0.0, 0.0);
        $this->assertIsArray($tithi);

        // Test at 360 degrees
        $tithi = $this->panchanga->calculateTithi(360.0, 360.0);
        $this->assertIsArray($tithi);

        // Test negative (should normalize)
        $tithi = $this->panchanga->calculateTithi(-10.0, -10.0);
        $this->assertIsArray($tithi);
    }
}
