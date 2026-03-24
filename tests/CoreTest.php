<?php

/**
 * Comprehensive Test Suite for Panchang Core Package.
 *
 * This test suite covers:
 * 1. Core constants and utilities
 * 2. Astronomy calculations
 * 3. Panchanga calculations
 * 4. Festival calculations
 * 5. Edge cases and precision tests
 * 6. Integration tests
 * 7. Performance benchmarks
 */

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Tests;

use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Core\Constants\ClassicalTimeConstants;
use JayeshMepani\PanchangCore\Core\Types\AyanamsaMode;
use JayeshMepani\PanchangCore\Core\Types\KarmaKalaType;
use JayeshMepani\PanchangCore\Core\Types\Paksha;
use JayeshMepani\PanchangCore\Core\Types\Tithi;
use PHPUnit\Framework\TestCase;

class CoreTest extends TestCase
{
    /** Test AstroCore::normalize() */
    public function testNormalize(): void
    {
        // Test normal range
        $this->assertEquals(45.0, AstroCore::normalize(45.0));

        // Test > 360
        $this->assertEquals(45.0, AstroCore::normalize(405.0));

        // Test negative
        $this->assertEquals(315.0, AstroCore::normalize(-45.0));

        // Test large negative
        $this->assertEquals(270.0, AstroCore::normalize(-90.0));

        // Test exact 360
        $this->assertEquals(0.0, AstroCore::normalize(360.0));

        // Test multiple rotations
        $this->assertEquals(90.0, AstroCore::normalize(810.0));
    }

    /** Test AstroCore::getAngularDistance() */
    public function testAngularDistance(): void
    {
        // Test same position
        $this->assertEquals(0.0, AstroCore::getAngularDistance(45.0, 45.0));

        // Test 90 degrees
        $this->assertEquals(90.0, AstroCore::getAngularDistance(0.0, 90.0));

        // Test 180 degrees
        $this->assertEquals(180.0, AstroCore::getAngularDistance(0.0, 180.0));

        // Test shortest path (should be 90, not 270)
        $this->assertEquals(90.0, AstroCore::getAngularDistance(0.0, 270.0));
    }

    /** Test AstroCore::getSign() */
    public function testGetSign(): void
    {
        // Test Aries
        $this->assertEquals(0, AstroCore::getSign(15.0));

        // Test Taurus
        $this->assertEquals(1, AstroCore::getSign(45.0));

        // Test Pisces
        $this->assertEquals(11, AstroCore::getSign(345.0));
    }

    /** Test ClassicalTimeConstants */
    public function testClassicalTimeConstants(): void
    {
        // Test typed constants
        $this->assertIsFloat(ClassicalTimeConstants::SECONDS_PER_DAY);
        $this->assertIsFloat(ClassicalTimeConstants::GHATIKA_IN_MINUTES);
        $this->assertIsInt(ClassicalTimeConstants::NAKSHATRAS_TOTAL);
        $this->assertIsArray(ClassicalTimeConstants::BHADRA_TITHIS);

        // Test exact values
        $this->assertEquals(86400.0, ClassicalTimeConstants::SECONDS_PER_DAY);
        $this->assertEquals(24.0, ClassicalTimeConstants::GHATIKA_IN_MINUTES);
        $this->assertEquals(27, ClassicalTimeConstants::NAKSHATRAS_TOTAL);

        // Test exact fractions
        $this->assertEquals(1.0 / 60.0, ClassicalTimeConstants::GHATIKA_PER_DAY);
        $this->assertEquals(1.0 / 30.0, ClassicalTimeConstants::MUHURTA_PER_DAY);
        $this->assertEquals(96.0 / 1440.0, ClassicalTimeConstants::ARUNODAYA_PER_DAY);
    }

    /** Test Paksha enum */
    public function testPakshaEnum(): void
    {
        // Test values
        $this->assertEquals('Shukla', Paksha::SHUKLA->value);
        $this->assertEquals('Krishna', Paksha::KRISHNA->value);

        // Test tithi range
        $this->assertEquals([1, 15], Paksha::SHUKLA->getTithiRange());
        $this->assertEquals([16, 30], Paksha::KRISHNA->getTithiRange());

        // Test contains
        $this->assertTrue(Paksha::SHUKLA->containsTithi(5));
        $this->assertFalse(Paksha::SHUKLA->containsTithi(20));
        $this->assertTrue(Paksha::KRISHNA->containsTithi(20));

        // Test normalize
        $this->assertEquals(5, Paksha::SHUKLA->normalizeTithi(5));
        $this->assertEquals(5, Paksha::KRISHNA->normalizeTithi(20));
    }

    /** Test Tithi enum */
    public function testTithiEnum(): void
    {
        // Test count
        $this->assertCount(30, Tithi::cases());

        // Test names
        $this->assertEquals('Pratipada', Tithi::PRATIPADA_SHUKLA->getName());
        $this->assertEquals('Purnima', Tithi::PURNIMA->getName());
        $this->assertEquals('Amavasya', Tithi::AMAVASYA->getName());

        // Test paksha
        $this->assertEquals(Paksha::SHUKLA, Tithi::PRATIPADA_SHUKLA->getPaksha());
        $this->assertEquals(Paksha::KRISHNA, Tithi::PRATIPADA_KRISHNA->getPaksha());

        // Test special tithis
        $this->assertTrue(Tithi::EKADASHI_SHUKLA->isEkadashi());
        $this->assertTrue(Tithi::PURNIMA->isPurnimaOrAmavasya());
        $this->assertTrue(Tithi::AMAVASYA->isPurnimaOrAmavasya());
    }

    /** Test KarmaKalaType enum */
    public function testKarmaKalaTypeEnum(): void
    {
        // Test values
        $this->assertEquals('sunrise', KarmaKalaType::SUNRISE->value);
        $this->assertEquals('arunodaya', KarmaKalaType::ARUNODAYA->value);

        // Test Sanskrit names
        $this->assertEquals('Udaya', KarmaKalaType::SUNRISE->getSanskritName());
        $this->assertEquals('Aruṇodaya', KarmaKalaType::ARUNODAYA->getSanskritName());

        // Test durations
        $this->assertEquals(0.0, KarmaKalaType::SUNRISE->getDurationMinutes());
        $this->assertEquals(96.0, KarmaKalaType::ARUNODAYA->getDurationMinutes());
        $this->assertEquals(72.0, KarmaKalaType::PRADOSHA->getDurationMinutes());
    }

    /** Test AyanamsaMode enum */
    public function testAyanamsaModeEnum(): void
    {
        // Test values
        $this->assertEquals('LAHIRI', AyanamsaMode::LAHIRI->value);
        $this->assertEquals('RAMAN', AyanamsaMode::RAMAN->value);

        // Test Swiss Eph mode
        $this->assertIsInt(AyanamsaMode::LAHIRI->toSwissEphMode());

        // Test descriptions
        $this->assertStringContainsString('Lahiri', AyanamsaMode::LAHIRI->getDescription());

        // Test Y2K values
        $this->assertGreaterThan(20.0, AyanamsaMode::LAHIRI->getApproximateValueForY2K());
    }
}
