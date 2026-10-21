<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Tests;

use JayeshMepani\PanchangCore\Muhurta\Classical\InauspiciousPeriodsCalculator;
use JayeshMepani\PanchangCore\Panchanga\ElectionalEvaluator;
use JayeshMepani\PanchangCore\PanchangServiceProvider;
use Orchestra\Testbench\TestCase;

/**
 * Visha Ghati and Varjyam (Nakshatra Tyajya) are the same 4-ghati window, so
 * the electional evaluator reports the Varjyam table's starting ghatis.
 */
final class ElectionalVarjyamTableTest extends TestCase
{
    public function testElectionalVishaGhatiUsesTheVarjyamTable(): void
    {
        for ($number = 1; $number <= 27; $number++) {
            $result = ElectionalEvaluator::calculateVarjyam($number, 0.0, 0.0);
            $offsets = InauspiciousPeriodsCalculator::NAKSHATRA_AMRITA_VARJYAM_OFFSETS[$number - 1]['varjyam'];

            self::assertSame($offsets, $result['visha_ghati_start_constants'], (string) $number);
            self::assertSame($offsets[0], $result['visha_ghati_start_constant'], (string) $number);
        }
    }

    public function testStartingGhatisOfPreviouslyMismatchedNakshatras(): void
    {
        self::assertSame([24], ElectionalEvaluator::calculateVarjyam(2, 0.0, 0.0)['visha_ghati_start_constants']);
        self::assertSame([18], ElectionalEvaluator::calculateVarjyam(12, 0.0, 0.0)['visha_ghati_start_constants']);
        self::assertSame([20, 56], ElectionalEvaluator::calculateVarjyam(19, 0.0, 0.0)['visha_ghati_start_constants']);
        self::assertSame([24], ElectionalEvaluator::calculateVarjyam(20, 0.0, 0.0)['visha_ghati_start_constants']);
        self::assertSame([24], ElectionalEvaluator::calculateVarjyam(26, 0.0, 0.0)['visha_ghati_start_constants']);
    }

    protected function getPackageProviders($app): array
    {
        return [PanchangServiceProvider::class];
    }
}
