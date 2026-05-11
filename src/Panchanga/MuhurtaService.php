<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Panchanga;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Muhurta\Classical\DailyPeriodsCalculator;
use JayeshMepani\PanchangCore\Muhurta\Classical\InauspiciousPeriodsCalculator;
use JayeshMepani\PanchangCore\Muhurta\Lagna\LagnaTableCalculator;
use JayeshMepani\PanchangCore\Muhurta\Planetary\ChogadiyaCalculator;
use JayeshMepani\PanchangCore\Muhurta\Planetary\HoraCalculator;
use JayeshMepani\PanchangCore\Muhurta\Regional\GowriPanchangamCalculator;
use SwissEph\FFI\SwissEphFFI;

/** Muhurta Service - Orchestrator for various time-based astrological systems. */
class MuhurtaService
{
    public function __construct(
        private readonly HoraCalculator $horaCalculator,
        private readonly ChogadiyaCalculator $chogadiyaCalculator,
        private readonly DailyPeriodsCalculator $dailyPeriodsCalculator,
        private readonly InauspiciousPeriodsCalculator $inauspiciousPeriodsCalculator,
        private readonly GowriPanchangamCalculator $gowriPanchangamCalculator,
        private readonly LagnaTableCalculator $lagnaTableCalculator
    ) {
    }

    public function calculateHora(
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise,
        CarbonImmutable $current,
        int $varaIdx
    ): array {
        return $this->horaCalculator->calculateHora($sunrise, $sunset, $nextSunrise, $current, $varaIdx);
    }

    public function calculateChogadiya(
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise,
        CarbonImmutable $current,
        int $varaIdx
    ): array {
        return $this->chogadiyaCalculator->calculateChogadiya($sunrise, $sunset, $nextSunrise, $current, $varaIdx);
    }

    public function calculateBadTimes(CarbonImmutable $sunrise, CarbonImmutable $sunset, int $varaIdx): array
    {
        return $this->inauspiciousPeriodsCalculator->calculateBadTimes($sunrise, $sunset, $varaIdx);
    }

    public function calculateAbhijitMuhurta(CarbonImmutable $sunrise, CarbonImmutable $sunset): array
    {
        return $this->dailyPeriodsCalculator->calculateAbhijitMuhurta($sunrise, $sunset);
    }

    public function calculateHoraTable(
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise,
        int $varaIdx
    ): array {
        return $this->horaCalculator->calculateHoraTable($sunrise, $sunset, $nextSunrise, $varaIdx);
    }

    public function calculateChogadiyaTable(
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise,
        int $varaIdx
    ): array {
        return $this->chogadiyaCalculator->calculateChogadiyaTable($sunrise, $sunset, $nextSunrise, $varaIdx);
    }

    public function calculateMuhurtaTable(
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise
    ): array {
        return $this->dailyPeriodsCalculator->calculateMuhurtaTable($sunrise, $sunset, $nextSunrise);
    }

    public function calculateDaylightFivefoldDivision(
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset
    ): array {
        return $this->dailyPeriodsCalculator->calculateDaylightFivefoldDivision($sunrise, $sunset);
    }

    public function calculateNishitaMuhurta(
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise
    ): array {
        return $this->dailyPeriodsCalculator->calculateNishitaMuhurta($sunset, $nextSunrise);
    }

    public function calculateVijayaMuhurta(
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset
    ): array {
        return $this->dailyPeriodsCalculator->calculateVijayaMuhurta($sunrise, $sunset);
    }

    public function calculateGodhuliMuhurta(
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise
    ): array {
        return $this->dailyPeriodsCalculator->calculateGodhuliMuhurta($sunset, $nextSunrise);
    }

    public function calculateSandhya(
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise,
        CarbonImmutable $solarNoon
    ): array {
        return $this->dailyPeriodsCalculator->calculateSandhya($sunrise, $sunset, $nextSunrise, $solarNoon);
    }

    public function calculateGowriPanchangam(
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise,
        int $varaIdx
    ): array {
        return $this->gowriPanchangamCalculator->calculateGowriPanchangam($sunrise, $sunset, $nextSunrise, $varaIdx);
    }

    public function calculateKalaVela(
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise,
        int $varaIdx
    ): array {
        return $this->gowriPanchangamCalculator->calculateKalaVela($sunrise, $sunset, $nextSunrise, $varaIdx);
    }

    public function calculatePrahara(
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise
    ): array {
        return $this->dailyPeriodsCalculator->calculatePrahara($sunrise, $sunset, $nextSunrise);
    }

    public function calculateBrahmaMuhurta(CarbonImmutable $sunrise): array
    {
        return $this->dailyPeriodsCalculator->calculateBrahmaMuhurta($sunrise);
    }

    public function calculateDurMuhurta(
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise,
        int $varaIdx
    ): array {
        return $this->dailyPeriodsCalculator->calculateDurMuhurta($sunrise, $sunset, $nextSunrise, $varaIdx);
    }

    public function calculateVarjyam(
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise,
        int $nakshatraIndex,
        float $nakshatraStartJd,
        float $nakshatraEndJd
    ): array {
        return $this->inauspiciousPeriodsCalculator->calculateVarjyam($nakshatraIndex, $nakshatraStartJd, $nakshatraEndJd, $sunrise);
    }

    public function calculateAmritaKaal(CarbonImmutable $sunrise, array $varjyam): array
    {
        return $this->inauspiciousPeriodsCalculator->calculateAmritaKaal($sunrise, $varjyam);
    }

    public function calculatePradoshaKaal(CarbonImmutable $sunset, int $tithiNum): array
    {
        return $this->inauspiciousPeriodsCalculator->calculatePradoshaKaal($sunset, $tithiNum);
    }

    public function calculateLagna(
        CarbonImmutable $current,
        CarbonImmutable $sunrise,
        float $sunriseSunLongitude,
        float $ayanamsaDeg,
        float $lat,
        float $lon,
        SwissEphFFI $sweph
    ): array {
        return $this->lagnaTableCalculator->calculateLagna($current, $ayanamsaDeg, $lat, $lon, $sweph);
    }

    public function calculateLagnaTable(
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise,
        float $sunriseSunLongitude,
        float $ayanamsaDeg,
        float $lat,
        float $lon,
        SwissEphFFI $sweph
    ): array {
        return $this->lagnaTableCalculator->calculateLagnaTable($sunrise, $sunset, $ayanamsaDeg, $lat, $lon, $sweph);
    }
}
