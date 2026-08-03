<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Panchanga;

use Carbon\CarbonImmutable;
use DateTimeInterface;
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
        $angle = AstroCore::normalize($moonLon - $sunLon);
        return $this->getKaranaAtAngle($angle);
    }

    public function getKaranaAtAngle(float $angle): array
    {
        $angle = AstroCore::normalize($angle);
        $num = min(60, (int) floor($angle / 6.0) + 1);

        $tithiIndex = (int) floor($angle / 12.0) + 1;
        $fraction = fmod($angle, 12.0) / 12.0;
        $karana = Karana::fromTithi($tithiIndex, $fraction);

        return [$karana->getName(), $num];
    }

    public function calculateTithi(float $sunLon, float $moonLon): array
    {
        return $this->calculateTithiAtAngle(AstroCore::normalize($moonLon - $sunLon));
    }

    public function calculateTithiAtAngle(float $angle): array
    {
        $tithi = Tithi::fromAngle($angle);
        return [
            'index' => $tithi->value,
            'name' => $tithi->getName(),
            'paksha' => $tithi->getPaksha()->getRawName(),
            'paksha_name' => $tithi->getPaksha()->getName(),
            'fraction_left' => Tithi::getFractionRemainingAtAngle($angle),
        ];
    }

    public function calculateYoga(float $sunLon, float $moonLon): array
    {
        return $this->calculateYogaAtAngle(AstroCore::normalize($sunLon + $moonLon));
    }

    public function calculateYogaAtAngle(float $angle): array
    {
        $yoga = Yoga::fromAngle($angle);
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
        // Compare using integer timestamps to avoid sub-second precision issues
        // when birth time equals or is very close to sunrise time.
        if ($dt->timestamp < $sunrise->timestamp) {
            $actual = $dt->subDay();
        }

        $vIdx = ((int) $actual->format('N')) % 7;
        $vara = Vara::from($vIdx);
        return [
            'index' => $vara->value,
            'name' => $vara->getName(),
        ];
    }

    /**
     * Stable machine key for solar ayana from ecliptic longitude.
     *
     * Uttarayana: Sun in Capricorn→Gemini sector (0°–90° and 270°–360°).
     * Dakshinayana: Sun in Cancer→Sagittarius sector (90°–270°).
     *
     * Works for both nirayana (sidereal) and sayana (tropical) longitudes.
     */
    public function getAyanaKey(float $sunLon): string
    {
        $normalized = fmod($sunLon, 360.0);
        if ($normalized < 0.0) {
            $normalized += 360.0;
        }

        return ($normalized >= 90.0 && $normalized < 270.0)
            ? 'Dakshinayana'
            : 'Uttarayana';
    }

    /** Localized ayana display name (Uttarayana / Dakshinayana). */
    public function getAyana(float $sunLon): string
    {
        return $this->getAyanaKey($sunLon) === 'Dakshinayana'
            ? Localization::translate('Ayana', 1)
            : Localization::translate('Ayana', 0);
    }

    /** Stable machine key for ṛtu (Vasanta…Shishira) from solar longitude. */
    public function getRituKey(float $sunLon): string
    {
        return Ritu::fromSunLongitude($sunLon)->name;
    }

    /** Localized ṛtu display name. */
    public function getRitu(float $sunLon): string
    {
        return Ritu::fromSunLongitude($sunLon)->getName();
    }

    /**
     * Nirayana (sidereal / constellation-based) and Sayana (tropical / seasonal)
     * ayana + ṛtu payload block for Hindu_Calendar.
     *
     * BC short keys Ayana / Ritu remain the nirayana values.
     * Explicit Nirayana_* / Sayana_* keys make the dual systems unambiguous.
     * *_Key fields are locale-stable machine identifiers for matching/rules.
     *
     * @return array{
     *   Ayana:string,
     *   Ritu:string,
     *   Ayana_Key:string,
     *   Ritu_Key:string,
     *   Nirayana_Ayana:string,
     *   Nirayana_Ritu:string,
     *   Nirayana_Ayana_Key:string,
     *   Nirayana_Ritu_Key:string,
     *   Sayana_Ayana:string,
     *   Sayana_Ritu:string,
     *   Sayana_Ayana_Key:string,
     *   Sayana_Ritu_Key:string,
     *   Ayana_System:string,
     *   Ritu_System:string,
     *   Sayana_Ayana_System:string,
     *   Sayana_Ritu_System:string
     * }
     */
    public function buildAyanaRituCalendarFields(float $nirayanaSunLon, float $sayanaSunLon): array
    {
        $nirayanaAyanaKey = $this->getAyanaKey($nirayanaSunLon);
        $nirayanaRituKey = $this->getRituKey($nirayanaSunLon);
        $sayanaAyanaKey = $this->getAyanaKey($sayanaSunLon);
        $sayanaRituKey = $this->getRituKey($sayanaSunLon);

        $nirayanaAyana = $this->getAyana($nirayanaSunLon);
        $nirayanaRitu = $this->getRitu($nirayanaSunLon);
        $sayanaAyana = $this->getAyana($sayanaSunLon);
        $sayanaRitu = $this->getRitu($sayanaSunLon);

        $nirayanaSystem = Localization::translate('String', 'Nirayana (Sidereal)');
        $sayanaSystem = Localization::translate('String', 'Sayana (Tropical)');

        return [
            // BC short keys = nirayana (sidereal / constellation-based)
            'Ayana' => $nirayanaAyana,
            'Ritu' => $nirayanaRitu,
            'Ayana_Key' => $nirayanaAyanaKey,
            'Ritu_Key' => $nirayanaRituKey,

            // Explicit nirayana naming
            'Nirayana_Ayana' => $nirayanaAyana,
            'Nirayana_Ritu' => $nirayanaRitu,
            'Nirayana_Ayana_Key' => $nirayanaAyanaKey,
            'Nirayana_Ritu_Key' => $nirayanaRituKey,

            // Sayana (tropical / seasonal)
            'Sayana_Ayana' => $sayanaAyana,
            'Sayana_Ritu' => $sayanaRitu,
            'Sayana_Ayana_Key' => $sayanaAyanaKey,
            'Sayana_Ritu_Key' => $sayanaRituKey,

            // System labels (localized)
            'Ayana_System' => $nirayanaSystem,
            'Ritu_System' => $nirayanaSystem,
            'Sayana_Ayana_System' => $sayanaSystem,
            'Sayana_Ritu_System' => $sayanaSystem,
        ];
    }

    public function getSamvat(int $year, int $month): array
    {
        $adj = $month >= 4 ? 0 : -1;

        return [
            'Vikram_Samvat' => $year + 57 + $adj,
            'Saka_Samvat' => $year - 78 + $adj,
        ];
    }

    /**
     * Southern continuous 60-name Saṃvatsara (Shaka / Ugadi-linked).
     *
     * Advances exactly one name per traditional southern year. For Vikram 2083 /
     * Shaka 1948 this is Parabhava (#40). Prefer {@see getSamvatsaraSouth()} in
     * new code; this method remains the canonical bare-name API used by festival
     * rules and older clients.
     */
    public function getSamvatsara(int $vikramSamvat): string
    {
        return $this->getSamvatsaraSouth($vikramSamvat);
    }

    /**
     * Southern continuous 60-name cycle (same as {@see getSamvatsara()}).
     * Explicit regional alias for Shaka / Telugu-Kannada / Tamil-style year names.
     */
    public function getSamvatsaraSouth(int $vikramSamvat): string
    {
        // Equivalent to (Saka + 11) % 60 because Vikram − Saka = 135.
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

    /**
     * Northern Chaitradi Vikram era-linked Saṃvatsara name.
     *
     * Fixed mapping from the Vikram year number (no mid-year mean-Jovian shift).
     * For Vikram 2083 this is Siddharthi (#53).
     */
    public function getSamvatsaraNorth(int $vikramSamvat): string
    {
        $idx = (($vikramSamvat + 9) % 60 + 60) % 60;
        return Samvatsara::from($idx)->getName();
    }

    /**
     * Gujarati Kartika-New-Year Saṃvatsara name from Gujarati Vikram year.
     *
     * For Gujarati Samvat 2082 (until Bestu Varash / Kartak Sud Padvo) this is
     * Pingala (#51); Gujarati 2083 becomes Kalayukti.
     */
    public function getSamvatsaraGujarati(int $gujaratiSamvat): string
    {
        $idx = (($gujaratiSamvat + 8) % 60 + 60) % 60;
        return Samvatsara::from($idx)->getName();
    }

    /**
     * Mean-Bṛhaspati (northern mean-Jovian) Saṃvatsara name for a civil date.
     *
     * Distinct from the Chaitradi Vikram era mapping and from Jupiter's true
     * sidereal rāśi ingress. Approximate transition day-of-year is 21 April
     * (md = 421), matching common panchanga listings (e.g. 2026-04-21
     * Siddharthi → Raudri).
     *
     * Epoch: Gregorian year Y after 21 April maps to index (Y − 1973) mod 60
     * (2026 → Raudri, 2025 → Siddharthi).
     */
    public function getSamvatsaraBrihaspati(DateTimeInterface $date): string
    {
        $year = (int) $date->format('Y');
        $monthDay = (int) $date->format('md');
        // Mean-Jovian year boundary ≈ 21 April (not true Guru gochar).
        $effectiveYear = $monthDay >= 421 ? $year : $year - 1;
        $idx = (($effectiveYear - 1973) % 60 + 60) % 60;
        return Samvatsara::from($idx)->getName();
    }

    /**
     * Full regional Saṃvatsara block for Hindu_Calendar payloads.
     *
     * @return array{
     *   Samvatsara: string,
     *   Samvatsara_South: string,
     *   Samvatsara_South_Prefix: string,
     *   Samvatsara_North: string,
     *   Samvatsara_Brihaspati: string,
     *   Samvatsara_North_Display: string,
     *   Samvatsara_Gujarati: string,
     *   Samvatsara_Systems: array<string, array<string, mixed>>
     * }
     */
    public function buildSamvatsaraCalendarFields(
        int $vikramSamvat,
        int $sakaSamvat,
        int $gujaratiSamvat,
        ?DateTimeInterface $date = null
    ): array {
        $south = $this->getSamvatsaraSouth($vikramSamvat);
        $north = $this->getSamvatsaraNorth($vikramSamvat);
        $gujarati = $this->getSamvatsaraGujarati($gujaratiSamvat);
        $brihaspati = $date instanceof DateTimeInterface
            ? $this->getSamvatsaraBrihaspati($date)
            : $this->getSamvatsaraNorth($vikramSamvat);
        $northDisplay = $north === $brihaspati
            ? $north
            : $north . ' / ' . $brihaspati;

        return [
            // Bare southern name kept for festival matchers and older clients.
            'Samvatsara' => $south,
            'Samvatsara_South' => $south,
            'Samvatsara_South_Prefix' => 'South',
            // Chaitradi Vikram era-linked northern name.
            'Samvatsara_North' => $north,
            // Mean-Bṛhaspati / mean-Jovian northern name (date-aware when possible).
            'Samvatsara_Brihaspati' => $brihaspati,
            // Drik-style dual label when era-linked and mean-Jovian differ.
            'Samvatsara_North_Display' => $northDisplay,
            'Samvatsara_Gujarati' => $gujarati,
            'Samvatsara_Systems' => [
                'south_shaka' => [
                    'region' => 'South',
                    'era' => 'Shaka',
                    'era_year' => $sakaSamvat,
                    'name' => $south,
                    'system' => 'continuous_60',
                    'note' => 'Southern continuous 60-name cycle (Ugadi / Shaka-linked traditional panchanga). Not an official National Civil Calendar year-name.',
                ],
                'north_vikram' => [
                    'region' => 'North',
                    'era' => 'Vikram_Chaitradi',
                    'era_year' => $vikramSamvat,
                    'name' => $north,
                    'system' => 'vikram_era_linked_60',
                    'note' => 'Chaitradi Vikram era-linked 60-name mapping (e.g. VS 2083 → Siddharthi).',
                ],
                'north_brihaspati' => [
                    'region' => 'North',
                    'era' => 'Mean_Brihaspati',
                    'era_year' => null,
                    'name' => $brihaspati,
                    'system' => 'mean_jovian_60',
                    'note' => 'Mean-Bṛhaspati (mean-Jovian) name; mid-year transition ≈ 21 April. Distinct from true Guru rāśi transit.',
                ],
                'gujarati_vikram' => [
                    'region' => 'Gujarat',
                    'era' => 'Gujarati_Vikram',
                    'era_year' => $gujaratiSamvat,
                    'name' => $gujarati,
                    'system' => 'gujarati_kartika_60',
                    'note' => 'Gujarati Kartika New Year year-name (e.g. Gujarati 2082 → Pingala until Bestu Varash).',
                ],
            ],
        ];
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
            'panchaka_name' => Localization::translate('Panchaka', $name),
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
