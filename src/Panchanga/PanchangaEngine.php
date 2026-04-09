<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Panchanga;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Astronomy\AstronomyService;
use JayeshMepani\PanchangCore\Astronomy\SunService;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Core\Enums\Karana;
use JayeshMepani\PanchangCore\Core\Enums\Masa;
use JayeshMepani\PanchangCore\Core\Enums\Nakshatra;
use JayeshMepani\PanchangCore\Core\Enums\Ritu;
use JayeshMepani\PanchangCore\Core\Enums\Samvatsara;
use JayeshMepani\PanchangCore\Core\Enums\Tithi;
use JayeshMepani\PanchangCore\Core\Enums\Vara;
use JayeshMepani\PanchangCore\Core\Enums\Yoga;
use JayeshMepani\PanchangCore\Core\Localization;

class PanchangaEngine
{
    public function getNakshatraInfo(float $longitude): array
    {
        $nakshatra = Nakshatra::fromLongitude($longitude);

        return [
            $nakshatra->getName(),
            Nakshatra::getPada($longitude),
            $nakshatra->getRulingPlanet(),
        ];
    }

    public function getKarana(float $sunLon, float $moonLon): array
    {
        $tAngle = AstroCore::normalize($moonLon - $sunLon);
        $num = min(60, (int) floor($tAngle / 6.0) + 1);

        $tithiIndex = (int) floor($tAngle / 12.0) + 1;
        $fraction = fmod($tAngle, 12.0) / 12.0;
        $karana = Karana::fromTithi($tithiIndex, $fraction);

        return [$karana->getName(), $num];
    }

    public function calculateTithi(float $sunLon, float $moonLon): array
    {
        $tithi = Tithi::fromLongitudes($sunLon, $moonLon);
        return [
            'index' => $tithi->value,
            'name' => $tithi->getName(),
            'paksha' => $tithi->getPaksha()->getName(),
            'fraction_left' => Tithi::getFractionRemaining($sunLon, $moonLon),
        ];
    }

    public function calculateYoga(float $sunLon, float $moonLon): array
    {
        $yoga = Yoga::fromLongitudes($sunLon, $moonLon);
        return [
            'index' => $yoga->value + 1, // original code used 1-based index
            'name' => $yoga->getName(),
        ];
    }

    public function calculateVara(array $birth, SunService $sunService): array
    {
        [$sunrise,] = $sunService->getSunriseSunset($birth);
        $dt = $sunService->getBirthDatetime($birth);

        $actual = $dt;
        if ($dt < $sunrise) {
            $actual = $dt->subDay();
        }

        $vIdx = ((int) $actual->format('N')) % 7;
        $vara = Vara::from($vIdx);
        return [
            'index' => $vara->value,
            'name' => $vara->getName(),
        ];
    }

    public function getAyana(float $sunLon): string
    {
        return ($sunLon >= 90.0 && $sunLon < 270.0) 
            ? Localization::translate('Ayana', 1) 
            : Localization::translate('Ayana', 0);
    }

    public function getRitu(float $sunLon): string
    {
        $sIdx = (int) floor($sunLon / 30.0);
        return Ritu::fromMonth($sIdx)->getName();
    }

    public function getSamvat(int $year, int $month): array
    {
        $adj = $month >= 4 ? 0 : -1;

        return [
            'Vikram_Samvat' => $year + 57 + $adj,
            'Saka_Samvat' => $year - 78 + $adj,
        ];
    }

    public function getSamvatsara(int $vikramSamvat): string
    {
        $idx = (($vikramSamvat - 135 + 11) % 60 + 60) % 60;
        return Samvatsara::from($idx)->getName();
    }

    public function getHinduMonth(float $sunLon, float $moonLon, string $paksha = 'Shukla'): array
    {
        $base = ((int) floor($sunLon / 30.0) + 1) % 12;
        $am = $paksha === 'Shukla' ? $base : ($base - 1 + 12) % 12;

        return [
            'Amanta' => Masa::from($am)->getName(),
            'Purnimanta' => Masa::from($base)->getName(),
            'Amanta_Index' => $am,
            'Purnimanta_Index' => $base,
        ];
    }

    public function getKaliSamvat(int $vikramSamvat): int
    {
        return $vikramSamvat + 3044;
    }

    public function getGujaratiSamvat(int $vikramSamvat, int $monthIndex): int
    {
        return $monthIndex < 7 ? $vikramSamvat - 1 : $vikramSamvat;
    }

    public function getSamvatsaraNorth(int $vikramSamvat): string
    {
        $idx = (($vikramSamvat + 9) % 60 + 60) % 60;
        return Samvatsara::from($idx)->getName();
    }

    public function calculatePanchakaRahita(int $tithiNum, int $varaNum, int $nakNum, int $lagnaNum): array
    {
        $total = $tithiNum + $varaNum + $nakNum + $lagnaNum;
        $r = $total % 9;

        $doshas = [
            1 => 'Mrityu Panchaka',
            2 => 'Agni Panchaka',
            4 => 'Raja Panchaka',
            6 => 'Chora Panchaka',
            7 => 'Roga Panchaka',
        ];

        if (isset($doshas[$r])) {
            $name = $doshas[$r];
            $isGood = false;
        } elseif ($r === 0 || $r === 8) {
            $name = 'Nish-Panchaka';
            $isGood = true;
        } else {
            $name = 'Shubha Panchaka';
            $isGood = true;
        }

        return [
            'sum' => $total,
            'remainder' => $r,
            'panchaka_name' => $name,
            'is_auspicious' => $isGood,
        ];
    }

    public function calculateKundaLagna(float $ascLon): array
    {
        $kundaVal = fmod($ascLon * 81.0, 360.0);
        if ($kundaVal < 0) {
            $kundaVal += 360.0;
        }

        [$nakName, $pada, $lord] = $this->getNakshatraInfo($kundaVal);

        return [
            'kunda_longitude' => $kundaVal,
            'nakshatra' => $nakName,
            'pada' => $pada,
            'lord' => $lord,
            'formula' => '(Asc * 81) % 360',
        ];
    }

    public function getPanchanga(
        float $sunLon,
        float $moonLon,
        array $birth,
        SunService $sunService,
        AstronomyService $astronomy
    ): array {
        $tithi = $this->calculateTithi($sunLon, $moonLon);
        $vara = $this->calculateVara($birth, $sunService);
        $yoga = $this->calculateYoga($sunLon, $moonLon);
        [$karanaName, $karanaIdx] = $this->getKarana($sunLon, $moonLon);
        [$nakName, $nakPada, $nakLord] = $this->getNakshatraInfo($moonLon);

        [$sunrise, $sunset] = $sunService->getSunriseSunset($birth);
        $dt = $sunService->getBirthDatetime($birth);
        $relSunrise = $sunrise;
        if ($dt->lessThan($sunrise)) {
            $prev = CarbonImmutable::create($dt->year, $dt->month, $dt->day, 0, 0, 0, $birth['timezone'])->subDay();
            $prevBirth = [
                'year' => $prev->year,
                'month' => $prev->month,
                'day' => $prev->day,
                'hour' => 0,
                'minute' => 0,
                'second' => 0,
                'timezone' => $birth['timezone'],
                'latitude' => $birth['latitude'],
                'longitude' => $birth['longitude'],
            ];
            [$relSunrise,] = $sunService->getSunriseSunset($prevBirth);
        }

        $sec = $dt->diffInSeconds($relSunrise, false);
        $sec = abs($sec);
        $gh = (int) floor($sec / 1440.0);
        $pl = (int) floor(fmod($sec, 1440.0) / 24.0);
        $vp = (int) floor(fmod(fmod($sec, 1440.0), 24.0) / 0.4);

        $srBirth = [
            'year' => (int) $relSunrise->format('Y'),
            'month' => (int) $relSunrise->format('m'),
            'day' => (int) $relSunrise->format('d'),
            'hour' => (int) $relSunrise->format('H'),
            'minute' => (int) $relSunrise->format('i'),
            'second' => (int) $relSunrise->format('s'),
            'timezone' => $birth['timezone'],
            'latitude' => $birth['latitude'],
            'longitude' => $birth['longitude'],
        ];
        $srPlanets = $astronomy->getPlanets($srBirth);
        $sunAtSunrise = $srPlanets['Sun'] ?? 0.0;
        $moonAtSunrise = $srPlanets['Moon'] ?? 0.0;

        return [
            'Tithi' => $tithi,
            'Vara' => $vara,
            'Nakshatra' => ['name' => $nakName, 'pada' => $nakPada, 'lord' => $nakLord],
            'Yoga' => $yoga,
            'Karana' => ['name' => $karanaName, 'index' => $karanaIdx],
            'Sunrise' => AstroCore::formatTime($relSunrise),
            'Sunset' => AstroCore::formatTime($sunset),
            'Ishtkaal' => sprintf('%02d:%02d:%02d', $gh, $pl, $vp),
            'sun_sunrise_lon' => AstroCore::formatAngle($sunAtSunrise),
            'moon_sunrise_lon' => AstroCore::formatAngle($moonAtSunrise),
            'sunrise_hm' => [(int) $relSunrise->format('H'), (int) $relSunrise->format('i')],
            'sunrise_dt' => $relSunrise,
        ];
    }

    public function isVishtiKarana(float $sunLon, float $moonLon): bool
    {
        [, $idx] = $this->getKarana($sunLon, $moonLon);
        return in_array($idx, [8, 15, 22, 29, 36, 43, 50, 57], true); // Vishti/Bhadra occurs in these specific 1-60 indices
    }
}
