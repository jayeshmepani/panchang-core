<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Panchanga;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Astronomy\AstronomyService;
use JayeshMepani\PanchangCore\Astronomy\SunService;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Core\Constants\AstrologyConstants;
use RuntimeException;

class PanchangaEngine
{
    public function getNakshatraInfo(float $longitude): array
    {
        $totalMinutes = $longitude * 60.0;
        $nakIdx = (int) floor($totalMinutes / 800.0);
        $nakshatraNames = $this->requiredConstantArray('NAKSHATRA_NAMES');
        $vimshottari = $this->requiredConstantArray('VIMSHOTTARI_ORDER');

        return [
            (string) $this->requiredArrayValue($nakshatraNames, $nakIdx % 27, 'nakshatra name'),
            (int) floor(fmod($totalMinutes, 800.0) / 200.0) + 1,
            (string) $this->requiredArrayValue($vimshottari, $nakIdx % 9, 'nakshatra lord'),
        ];
    }

    public function getKarana(float $sunLon, float $moonLon): array
    {
        $tAngle = AstroCore::normalize($moonLon - $sunLon);
        $num = min(60, (int) floor($tAngle / 6.0) + 1);

        $sthira = AstrologyConstants::get('STHIRA_KARANAS');
        $chara = AstrologyConstants::get('CHARA_KARANAS');

        $name = $sthira[$num] ?? $chara[($num - 2) % 7];

        return [$name, $num];
    }

    public function calculateTithi(float $sunLon, float $moonLon): array
    {
        $diff = AstroCore::normalize($moonLon - $sunLon);
        $num = (int) floor($diff / 12.0) + 1;

        $tithiNames = $this->requiredConstantArray('TITHI_NAMES');
        return [
            'index' => $num,
            'name' => (string) $this->requiredArrayValue($tithiNames, $num - 1, 'tithi name'),
            'paksha' => $num <= 15 ? 'Shukla' : 'Krishna',
            'fraction_left' => AstroCore::r9(1.0 - (fmod($diff, 12.0) / 12.0)),
        ];
    }

    public function calculateYoga(float $sunLon, float $moonLon): array
    {
        $idx = (int) floor(AstroCore::normalize($sunLon + $moonLon) / 13.3333333333);
        $idx = min(26, $idx);

        $yogaNames = $this->requiredConstantArray('YOGA_NAMES');
        return [
            'index' => $idx + 1,
            'name' => (string) $this->requiredArrayValue($yogaNames, $idx, 'yoga name'),
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

        $vIdx = ((int) $actual->format('N')) % 7; // Python weekday: Mon=0..Sun=6; Carbon N: Mon=1..Sun=7

        $varaNames = $this->requiredConstantArray('VARA_NAMES');
        return [
            'index' => $vIdx,
            'name' => (string) $this->requiredArrayValue($varaNames, $vIdx, 'vara name'),
        ];
    }

    public function getAyana(float $sunLon): string
    {
        return ($sunLon >= 90.0 && $sunLon < 270.0) ? 'Dakshinayana' : 'Uttarayana';
    }

    public function getRitu(float $sunLon): string
    {
        $sIdx = (int) floor($sunLon / 30.0);
        $mapping = $this->requiredConstantArray('RITU_MONTH_MAPPING');
        $ritus = $this->requiredConstantArray('RITU_NAMES');
        $idx = $mapping[$sIdx] ?? 0;

        return (string) $this->requiredArrayValue($ritus, (int) $idx, 'ritu name');
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
        $names = $this->requiredConstantArray('SAMVATSARA_NAMES');
        $idx = (($vikramSamvat - 135 + 11) % 60 + 60) % 60;
        return (string) $this->requiredArrayValue($names, $idx, 'samvatsara name');
    }

    public function getHinduMonth(float $sunLon, float $moonLon, string $paksha = 'Shukla'): array
    {
        $base = ((int) floor($sunLon / 30.0) + 1) % 12;
        $am = $paksha === 'Shukla' ? $base : ($base - 1 + 12) % 12;

        $months = $this->requiredConstantArray('HINDU_MONTHS');

        return [
            'Amanta' => (string) $this->requiredArrayValue($months, $am, 'amanta month'),
            'Purnimanta' => (string) $this->requiredArrayValue($months, $base, 'purnimanta month'),
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
        $names = $this->requiredConstantArray('SAMVATSARA_NAMES');
        $idx = (($vikramSamvat + 9) % 60 + 60) % 60;
        return (string) $this->requiredArrayValue($names, $idx, 'north samvatsara name');
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
            'kunda_longitude' => AstroCore::r9($kundaVal),
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
            'Sunrise' => $relSunrise->format('H:i:s'),
            'Sunset' => $sunset->format('H:i:s'),
            'Ishtkaal' => sprintf('%02d:%02d:%02d', $gh, $pl, $vp),
            'sun_sunrise_lon' => $sunAtSunrise,
            'moon_sunrise_lon' => $moonAtSunrise,
            'sunrise_hm' => [(int) $relSunrise->format('H'), (int) $relSunrise->format('i')],
            'sunrise_dt' => $relSunrise,
        ];
    }

    public function isVishtiKarana(float $sunLon, float $moonLon): bool
    {
        [, $idx] = $this->getKarana($sunLon, $moonLon);
        return $idx === 7 || $idx === 8;
    }

    private function requiredConstantArray(string $key): array
    {
        $value = AstrologyConstants::get($key);
        if (!is_array($value)) {
            throw new RuntimeException("Missing or invalid constant array: {$key}");
        }
        return $value;
    }

    private function requiredArrayValue(array $arr, int $index, string $label): mixed
    {
        if (!array_key_exists($index, $arr)) {
            throw new RuntimeException("Missing {$label} for index {$index}");
        }
        return $arr[$index];
    }
}
