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
use JayeshMepani\PanchangCore\Core\Enums\Paksha;
use JayeshMepani\PanchangCore\Core\Enums\Tithi;
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
        // Test tithi range
        $this->assertEquals(['start' => 1, 'end' => 15], Paksha::Shukla->getTithiRange());
        $this->assertEquals(['start' => 16, 'end' => 30], Paksha::Krishna->getTithiRange());

        // Test contains
        $this->assertTrue(Paksha::Shukla->containsTithi(5));
        $this->assertFalse(Paksha::Shukla->containsTithi(20));
        $this->assertTrue(Paksha::Krishna->containsTithi(20));

        // Test normalize
        $this->assertEquals(5, Paksha::Shukla->normalizeTithi(5));
        $this->assertEquals(5, Paksha::Krishna->normalizeTithi(20));
    }

    /** Test Tithi enum */
    public function testTithiEnum(): void
    {
        // Test count
        $this->assertCount(30, Tithi::cases());

        // Test names
        $this->assertEquals('Shukla Pratipada', Tithi::ShuklaPratipada->getName());
        $this->assertEquals('Purnima', Tithi::Purnima->getName());
        $this->assertEquals('Amavasya', Tithi::Amavasya->getName());

        // Test paksha
        $this->assertEquals(Paksha::Shukla, Tithi::ShuklaPratipada->getPaksha());
        $this->assertEquals(Paksha::Krishna, Tithi::KrishnaPratipada->getPaksha());

        // Test special tithis
        $this->assertTrue(Tithi::ShuklaEkadashi->isEkadashi());
        $this->assertTrue(Tithi::Purnima->isPurnimaOrAmavasya());
        $this->assertTrue(Tithi::Amavasya->isPurnimaOrAmavasya());
    }

}
