<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Panchanga;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Astronomy\AstronomyService;
use JayeshMepani\PanchangCore\Astronomy\SunService;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Core\Enums\CalendarType;
use JayeshMepani\PanchangCore\Core\Enums\Masa;
use JayeshMepani\PanchangCore\Core\Enums\Nakshatra;
use JayeshMepani\PanchangCore\Core\Enums\Rasi;
use JayeshMepani\PanchangCore\Core\Enums\Tithi;
use JayeshMepani\PanchangCore\Core\Enums\Vara;
use JayeshMepani\PanchangCore\Core\Localization;
use JayeshMepani\PanchangCore\Festivals\FestivalService;
use JayeshMepani\PanchangCore\Festivals\Utils\BhadraEngine;
use RuntimeException;
use SwissEph\FFI\SwissEphFFI;
use Throwable;

/**
 * Panchang Service.
 *
 * Main service for calculating Vedic Panchanga details:
 * - Tithi, Vara, Nakṣatra, Yoga, Karaṇa
 * - Muhūrta calculations
 * - Sunrise/sunset based calculations
 */
class PanchangService
{
    /** @var array<int, string> */
    private const array YEARLY_SINGLE_OBSERVANCE_FESTIVALS = [
        'Ganga Dussehra',
    ];

    private const array SARVARTHA_SIDDHI_RULES = [
        0 => [3, 7, 11, 12, 20, 25],
        1 => [3, 4, 7, 16, 21],
        2 => [0, 2, 8, 12, 25],
        3 => [2, 3, 4, 12, 16, 24, 25],
        4 => [6, 7, 16, 26],
        5 => [0, 6, 16, 21, 26],
        6 => [3, 14, 21],
    ];

    private const array AMRIT_SIDDHI_RULES = [
        0 => [12],
        1 => [4],
        2 => [0],
        3 => [16],
        4 => [7],
        5 => [26],
        6 => [3],
    ];

    /** @var list<int> */
    private const array DWI_PUSHKARA_NAKSHATRAS = [4, 13, 22];

    /** @var list<int> */
    private const array TRI_PUSHKARA_NAKSHATRAS = [2, 6, 10, 15, 20, 24];

    /** @var list<int> */
    private const array PUSHKARA_TITHIS = [2, 7, 12];

    /** @var list<int> */
    private const array PUSHKARA_WEEKDAYS = [0, 2, 6];

    /** @var list<int> */
    private const array RAVI_YOGA_DISTANCES = [4, 6, 9, 10, 13, 20];

    /** @var list<int> */
    private const array AADAL_COUNTS = [2, 7, 9, 14, 16, 21, 23, 28];

    /** @var list<int> */
    private const array VIDAAL_COUNTS = [3, 6, 10, 13, 17, 20, 24, 27];

    private const array JWALAMUKHI_RULES = [
        1 => [18], // Pratipada + Moola
        5 => [1],  // Panchami + Bharani
        8 => [2],  // Ashtami + Krittika
        9 => [3],  // Navami + Rohini
        10 => [8], // Dashami + Ashlesha
    ];

    /** @var list<int> */
    private const array ABHIJIT_ORDER = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 27, 21, 22, 23, 24, 25, 26];

    /** @var list<string> */
    private const array ANANDADI_YOGA_NAMES = [
        'Ananda',
        'Kaladanda',
        'Dhumra',
        'Dhata/Prajapati',
        'Saumya',
        'Dhvanksha',
        'Dhvaja',
        'Srivatsa',
        'Vajra',
        'Mudgara',
        'Chhatra',
        'Mitra',
        'Manasa',
        'Padma',
        'Lumbaka',
        'Utpata',
        'Mrityu',
        'Kana',
        'Siddhi',
        'Shubha',
        'Amrita',
        'Mushala',
        'Gada',
        'Matanga',
        'Rakshasa',
        'Chara',
        'Sthira',
        'Vardhamana',
    ];

    /** @var list<string> */
    private const array ANANDADI_YOGA_EFFECTS = [
        'Siddhi',
        'Mrityu',
        'Asukha',
        'Saubhagya',
        'Bahu Sukha',
        'Dhanakshaya',
        'Saubhagya',
        'Saukhyasampatti',
        'Loss / Kshaya',
        'Lakshmikshaya',
        'Rajasanmana',
        'Pushti',
        'Saubhagya',
        'Dhanagama',
        'Dhanakshaya',
        'Prananasha',
        'Mrityu',
        'Klesha',
        'Karyasiddhi',
        'Kalyana',
        'Rajasanmana',
        'Dhanakshaya',
        'Bhaya',
        'Kulavriddhi',
        'Mahakashta',
        'Karyasiddhi',
        'Griharambha',
        'Vivaha',
    ];

    /** @var array<int, int> */
    private const array ANANDADI_WEEKDAY_START_28_INDEX = [
        0 => 0,  // Sunday: Ashwini
        1 => 4,  // Monday: Mrigashira
        2 => 8,  // Tuesday: Ashlesha
        3 => 12, // Wednesday: Hasta
        4 => 16, // Thursday: Anuradha
        5 => 20, // Friday: Uttara Ashadha
        6 => 24, // Saturday: Shatabhisha
    ];

    /** @var array<int, array<int, string>> */
    private const array AMRITADI_YOGA_TABLE = [
        0 => ['Siddha', 'Siddha', 'Siddha', 'Marana', 'Amrita', 'Amrita', 'Siddha'],
        1 => ['Prabalarishta', 'Siddha', 'Siddha', 'Siddha', 'Siddha', 'Siddha', 'Siddha'],
        2 => ['Siddha', 'Marana', 'Siddha', 'Amrita', 'Marana', 'Siddha', 'Siddha'],
        3 => ['Siddha', 'Amrita', 'Amrita', 'Siddha', 'Marana', 'Marana', 'Amrita'],
        4 => ['Siddha', 'Siddha', 'Siddha', 'Siddha', 'Marana', 'Siddha', 'Siddha'],
        5 => ['Siddha', 'Siddha', 'Marana', 'Siddha', 'Marana', 'Siddha', 'Siddha'],
        6 => ['Siddha', 'Amrita', 'Siddha', 'Siddha', 'Amrita', 'Siddha', 'Siddha'],
        7 => ['Siddha', 'Siddha', 'Siddha', 'Siddha', 'Siddha', 'Marana', 'Siddha'],
        8 => ['Siddha', 'Siddha', 'Siddha', 'Siddha', 'Siddha', 'Marana', 'Marana'],
        9 => ['Marana', 'Marana', 'Siddha', 'Siddha', 'Amrita', 'Marana', 'Amrita'],
        10 => ['Siddha', 'Siddha', 'Siddha', 'Amrita', 'Siddha', 'Siddha', 'Siddha'],
        11 => ['Amrita', 'Siddha', 'Amrita', 'Amrita', 'Marana', 'Siddha', 'Marana'],
        12 => ['Siddha', 'Siddha', 'Siddha', 'Marana', 'Siddha', 'Amrita', 'Marana'],
        13 => ['Siddha', 'Prabalarishta', 'Siddha', 'Siddha', 'Siddha', 'Siddha', 'Marana'],
        14 => ['Siddha', 'Amrita', 'Siddha', 'Siddha', 'Amrita', 'Siddha', 'Siddha'],
        15 => ['Marana', 'Marana', 'Marana', 'Siddha', 'Amrita', 'Siddha', 'Siddha'],
        16 => ['Marana', 'Siddha', 'Siddha', 'Amrita', 'Siddha', 'Siddha', 'Siddha'],
        17 => ['Marana', 'Siddha', 'Marana', 'Siddha', 'Prabalarishta', 'Marana', 'Siddha'],
        18 => ['Amrita', 'Siddha', 'Amrita', 'Marana', 'Siddha', 'Amrita', 'Siddha'],
        19 => ['Siddha', 'Marana', 'Siddha', 'Amrita', 'Siddha', 'Prabalarishta', 'Siddha'],
        20 => ['Amrita', 'Marana', 'Prabalarishta', 'Amrita', 'Siddha', 'Siddha', 'Siddha'],
        21 => ['Amrita', 'Amrita', 'Siddha', 'Siddha', 'Siddha', 'Marana', 'Siddha'],
        22 => ['Marana', 'Siddha', 'Siddha', 'Prabalarishta', 'Siddha', 'Siddha', 'Siddha'],
        23 => ['Siddha', 'Siddha', 'Marana', 'Siddha', 'Marana', 'Siddha', 'Amrita'],
        24 => ['Siddha', 'Siddha', 'Marana', 'Amrita', 'Siddha', 'Siddha', 'Marana'],
        25 => ['Amrita', 'Siddha', 'Amrita', 'Siddha', 'Siddha', 'Siddha', 'Siddha'],
        26 => ['Amrita', 'Siddha', 'Siddha', 'Marana', 'Siddha', 'Siddha', 'Prabalarishta'],
    ];

    /** @var array<int, string> */
    private const array CHANDRA_VAASA_PADA_ABODES = [
        1 => 'Deva',
        2 => 'Nara',
        3 => 'Pashava',
        4 => 'Rakshasa',
    ];

    /** @var array<int, string> */
    private const array CHANDRA_VAASA_PADA_QUALITY = [
        1 => 'Auspicious',
        2 => 'Neutral',
        3 => 'Mixed',
        4 => 'Inauspicious',
    ];

    /** @var array<string, list<int>> */
    private const array NAKSHATRA_SHOOL_DIRECTIONS = [
        'East' => [18, 21, 17],
        'West' => [3, 7],
        'North' => [12, 11],
        'South' => [24, 0, 5, 22],
    ];

    private const array SHIVA_VAASA_METHOD1 = [
        1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7,
        8 => 1, 9 => 2, 10 => 3, 11 => 4, 12 => 5, 13 => 6, 14 => 7,
        15 => 1, 16 => 1, 17 => 2, 18 => 3, 19 => 4, 20 => 5, 21 => 6, 22 => 7,
        23 => 1, 24 => 2, 25 => 3, 26 => 4, 27 => 5, 28 => 6, 29 => 7, 30 => 2,
    ];

    private const array SHIVA_VAASA_METHOD2 = [
        0 => 1, 1 => 5, 2 => 2, 3 => 6, 4 => 3, 5 => 7, 6 => 4,
    ];

    private const array SHIVA_VAASA_LABELS = [
        1 => 'In Cemetery',
        2 => 'With Gowri',
        3 => 'In Assembly',
        4 => 'At Work / Play',
        5 => 'At Kailash',
        6 => 'Mounted on Vrishabha (Nandi)',
        7 => 'At Dinner / Meditation',
    ];

    private const array SHIVA_VAASA_EFFECTS = [
        1 => 'Death / severe inauspiciousness',
        2 => 'Happiness and wealth',
        3 => 'Grief',
        4 => 'Difficulty',
        5 => 'Happiness',
        6 => 'Success',
        7 => 'Trouble / peeda',
    ];

    private const array AGNI_VAASA_LABELS = [
        1 => 'Prithvi (Earth)',
        2 => 'Akaasha (Space)',
        3 => 'Paathaala (Nadir)',
    ];

    private const array AGNI_VAASA_EFFECTS = [
        1 => 'Bestows comfort',
        2 => 'Life threatening / highly adverse',
        3 => 'Destroys wealth',
    ];

    private const array YOGINI_VAASA_MAP = [0, 3, 7, 5, 1, 2, 5, 6, 0, 3, 7, 5, 1, 2, 5, 0, 3, 7, 5, 1, 2, 5, 6, 0, 3, 7, 5, 1, 2, 6];

    private const array DIRECTION_LABELS = [
        0 => 'East',
        1 => 'South',
        2 => 'West',
        3 => 'North',
        4 => 'South-West',
        5 => 'North-West',
        6 => 'North-East',
        7 => 'South-East',
    ];

    private static string $ephePath = '';

    private array $monthCache = [];

    public function __construct(
        private readonly SwissEphFFI $sweph,
        private readonly SunService $sunService,
        private readonly AstronomyService $astronomy,
        private readonly PanchangaEngine $panchanga,
        private readonly MuhurtaService $muhurta,
        private readonly FestivalService $festivalService,
        private readonly BhadraEngine $bhadraEngine,
    ) {
        $envEphePath = ($_ENV['PANCHANG_EPHE_PATH'] ?? false);
        $envEphePath = is_string($envEphePath) ? $envEphePath : '';

        $configEphePath = function_exists('config') ? config('panchang.ephe_path', $envEphePath) : $envEphePath;
        $ephePath = self::$ephePath !== '' ? self::$ephePath : (is_string($configEphePath) ? $configEphePath : '');
        if ($ephePath !== '' && file_exists($ephePath)) {
            $this->sweph->swe_set_ephe_path($ephePath);
        }

        // Enforce Lahiri globally for all Panchang calculations, including lightweight snapshots.
        $this->sweph->swe_set_sid_mode(SwissEphFFI::SE_SIDM_LAHIRI, 0.0, 0.0);
    }

    public static function configure(string $ephePath = ''): void
    {
        self::$ephePath = $ephePath;
    }

    public function getDayDetails(
        CarbonImmutable $date,
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0,
        ?CarbonImmutable $calculationAt = null,
        CalendarType|string $calendarType = CalendarType::Amanta,
        array $options = []
    ): array
    {
        // Normalize calendar_type from string to enum
        if (is_string($calendarType)) {
            $calendarType = match (strtolower($calendarType)) {
                'purnimanta', 'purnimant' => CalendarType::Purnimanta,
                default => CalendarType::Amanta,
            };
        }

        $birthBase = [
            'year' => $date->year,
            'month' => $date->month,
            'day' => $date->day,
            'hour' => 0,
            'minute' => 0,
            'second' => 0,
            'timezone' => $tz,
            'latitude' => $lat,
            'longitude' => $lon,
            'elevation' => $elevation,
        ];

        [$sunrise, $sunset] = $this->sunService->getSunriseSunset($birthBase);

        $calculationAt ??= $sunrise;
        $birthAt = $birthBase;
        $birthAt['hour'] = (int) $calculationAt->format('H');
        $birthAt['minute'] = (int) $calculationAt->format('i');
        $birthAt['second'] = (int) $calculationAt->format('s');

        $relSunrise = $sunrise;
        if ($calculationAt->lessThan($sunrise)) {
            $prev = $date->subDay();
            $prevBirth = [
                'year' => $prev->year,
                'month' => $prev->month,
                'day' => $prev->day,
                'hour' => 0,
                'minute' => 0,
                'second' => 0,
                'timezone' => $tz,
                'latitude' => $lat,
                'longitude' => $lon,
                'elevation' => $elevation,
            ];
            [$relSunrise] = $this->sunService->getSunriseSunset($prevBirth);
        }

        $sunriseBirth = $birthBase;
        $sunriseBirth['year'] = (int) $relSunrise->format('Y');
        $sunriseBirth['month'] = (int) $relSunrise->format('m');
        $sunriseBirth['day'] = (int) $relSunrise->format('d');
        $sunriseBirth['hour'] = (int) $relSunrise->format('H');
        $sunriseBirth['minute'] = (int) $relSunrise->format('i');
        $sunriseBirth['second'] = (int) $relSunrise->format('s');

        $sunMoon = $this->getSunMoonLongitudes($sunriseBirth);
        $sunLon = $sunMoon['Sun'];
        $moonLon = $sunMoon['Moon'];

        $ayanamsaBirth = $birthAt;
        $ayanamsaJd = $this->astronomy->toJulianDayUtc($ayanamsaBirth);
        $ayanamsaDeg = $this->astronomy->getAyanamsa($ayanamsaJd);

        $tithi = $this->panchanga->calculateTithi($sunLon, $moonLon);
        $yoga = $this->panchanga->calculateYoga($sunLon, $moonLon);
        [$karanaName, $karanaIdx] = $this->panchanga->getKarana($sunLon, $moonLon);
        [$nakName, $nakPada, $nakLord] = $this->panchanga->getNakshatraInfo($moonLon);
        $nakIdx = (int) floor(($moonLon * 60.0) / 800.0);
        $vara = $this->panchanga->calculateVara($birthAt, $this->sunService);

        $isth = $this->calculateIshtkaal($relSunrise, $birthAt, $tz);

        $ascLon = $this->astronomy->getAscendant($birthAt);
        $ascSign = AstroCore::getSign($ascLon);
        $moonSign = AstroCore::getSign($moonLon);
        $sunSign = AstroCore::getSign($sunLon);

        $panchaka = $this->panchanga->calculatePanchakaRahita(
            (int) $tithi['index'],
            (int) $vara['index'] + 1,
            $nakIdx + 1,
            $ascSign + 1
        );

        $nextDay = $date->addDay();
        $nextBirth = [
            'year' => $nextDay->year,
            'month' => $nextDay->month,
            'day' => $nextDay->day,
            'hour' => 0,
            'minute' => 0,
            'second' => 0,
            'timezone' => $tz,
            'latitude' => $lat,
            'longitude' => $lon,
            'elevation' => $elevation,
        ];
        [$nextSunrise] = $this->sunService->getSunriseSunset($nextBirth);

        $twilight = $this->sunService->getTwilightTimes($birthBase);
        $solarTransits = $this->sunService->getSolarTransits($birthBase);
        $solarTransitsNext = $this->sunService->getSolarTransits($nextBirth);

        $apparentSolarDaySeconds = $solarTransitsNext['solar_noon']->getTimestamp() - $solarTransits['solar_noon']->getTimestamp();
        $civilDayStart = $date->setTime(0, 0, 0);
        $civilDayEnd = $civilDayStart->addDay();
        $civilDaySeconds = $civilDayEnd->getTimestamp() - $civilDayStart->getTimestamp();

        $hora = $this->muhurta->calculateHora($relSunrise, $sunset, $nextSunrise, $calculationAt, $vara['index']);
        $chogadiya = $this->muhurta->calculateChogadiya($relSunrise, $sunset, $nextSunrise, $calculationAt, $vara['index']);
        $horaTable = $this->muhurta->calculateHoraTable($relSunrise, $sunset, $nextSunrise, $vara['index']);
        $chogadiyaTable = $this->muhurta->calculateChogadiyaTable($relSunrise, $sunset, $nextSunrise, $vara['index']);
        $muhurtaTable = $this->muhurta->calculateMuhurtaTable($relSunrise, $sunset, $nextSunrise);
        $badTimes = $this->muhurta->calculateBadTimes($relSunrise, $sunset, $vara['index']);

        $abhijit = $this->muhurta->calculateAbhijitMuhurta($relSunrise, $sunset);

        // New calculations: Prahara, Brahma Muhurta, Dur Muhurta
        $praharaTable = $this->muhurta->calculatePrahara($relSunrise, $sunset, $nextSunrise);
        $brahmaMuhurta = $this->muhurta->calculateBrahmaMuhurta($nextSunrise);
        $durMuhurtaTable = $this->muhurta->calculateDurMuhurta($relSunrise, $sunset, $nextSunrise, (int) $vara['index']);
        $daylightFivefold = $this->muhurta->calculateDaylightFivefoldDivision($relSunrise, $sunset);
        $nishitaMuhurta = $this->muhurta->calculateNishitaMuhurta($sunset, $nextSunrise);
        $vijayaMuhurta = $this->muhurta->calculateVijayaMuhurta($relSunrise, $sunset);
        $godhuliMuhurta = $this->muhurta->calculateGodhuliMuhurta($sunset, $nextSunrise);
        $sandhya = $this->muhurta->calculateSandhya($relSunrise, $sunset, $nextSunrise, $solarTransits['solar_noon']);
        $gowriPanchangam = $this->muhurta->calculateGowriPanchangam($relSunrise, $sunset, $nextSunrise, (int) $vara['index']);
        $kalaVela = $this->muhurta->calculateKalaVela($relSunrise, $sunset, $nextSunrise, (int) $vara['index']);

        // Lagna table for full day (Whole Sign system - exact sign entry times)
        $lagnaTable = $this->muhurta->calculateLagnaTable(
            $relSunrise,
            $sunset,
            $nextSunrise,
            $sunMoon['Sun'],
            $ayanamsaDeg,
            $lat,
            $lon,
            $this->sweph
        );

        $jdSunrise = $this->toJulianDayFromCarbon($relSunrise, $tz);
        $jdSunset = $this->toJulianDayFromCarbon($sunset, $tz);
        $jdNextSunrise = $this->toJulianDayFromCarbon($nextSunrise, $tz);

        $tithiNum = (int) $tithi['index'];
        $tithiStartAngle = ($tithiNum - 1) * 12.0;
        $tithiEndAngle = $tithiNum * 12.0;
        $tithiStartJd = $this->findAngleCrossing($jdSunrise, $tithiStartAngle, -1, fn (float $jd): float => $this->getMoonSunAngle($jd));
        $tithiEndJd = $this->findAngleCrossing($jdSunrise, $tithiEndAngle, 1, fn (float $jd): float => $this->getMoonSunAngle($jd));

        $nakEndAngle = ($nakIdx + 1) * (360.0 / 27.0);
        $nakEndJd = $this->findAngleCrossing($jdSunrise, $nakEndAngle, 1, fn (float $jd): float => $this->getMoonLongitude($jd));
        $nakStartAngle = $nakIdx * (360.0 / 27.0);
        $nakStartJd = $this->findAngleCrossing($jdSunrise, $nakStartAngle, -1, fn (float $jd): float => $this->getMoonLongitude($jd));

        // Varjyam (Tyajyam) can occur once or twice between sunrise and next sunrise.
        $varjyamWindows = $this->calculateVarjyamWindows($relSunrise, $sunset, $nextSunrise, $jdSunrise, $jdNextSunrise, $nakIdx, $nakStartJd, $nakEndJd);
        $varjyam = $this->buildVarjyamPayload($varjyamWindows);
        $amritaKaal = $this->muhurta->calculateAmritaKaal($relSunrise, $varjyam);

        // Pradosha Kaal: first 1/5th of night, auspicious only when Trayodashi overlaps it.
        $pradoshaKaal = $this->calculatePradoshaKaal($sunset, $nextSunrise, $jdSunset, $jdNextSunrise, $tz);

        // Lagna calculation
        $lagna = $this->muhurta->calculateLagna(
            $calculationAt,
            $relSunrise,
            $sunMoon['Sun'],
            $ayanamsaDeg,
            $lat,
            $lon,
            $this->sweph
        );

        $yogaIdx = (int) $yoga['index'];
        $yogaEndAngle = $yogaIdx * (360.0 / 27.0);
        $yogaEndJd = $this->findAngleCrossing($jdSunrise, $yogaEndAngle, 1, fn (float $jd): float => $this->getSunMoonSum($jd));

        $karanaEndAngle = $karanaIdx * 6.0;
        $karanaEndJd = $this->findAngleCrossing($jdSunrise, $karanaEndAngle, 1, fn (float $jd): float => $this->getMoonSunAngle($jd));

        $kalaEngine = new KalaNirnayaEngine($lat, $lon);
        $hinduMonth = $this->getTrueHinduMonth($jdSunrise);

        $sankrantiNameMap = [
            0 => 'Mesha',
            1 => 'Vrishabha',
            2 => 'Mithuna',
            3 => 'Karka',
            4 => 'Simha',
            5 => 'Kanya',
            6 => 'Tula',
            7 => 'Vrischika',
            8 => 'Dhanu',
            9 => 'Makara',
            10 => 'Kumbha',
            11 => 'Meena',
        ];

        // Check Sankranti across the civil day (midnight to midnight) for reliable detection.
        // Sunrise-to-sunrise can miss Sankrantis that occur very close to sunrise.
        $civilStart = $date->startOfDay();
        $civilEnd = $civilStart->addDay();
        $jdCivilStart = $this->toJulianDayFromCarbon($civilStart, $tz);
        $jdCivilEnd = $this->toJulianDayFromCarbon($civilEnd, $tz);
        $sunLonCivilStart = $this->getSunLongitude($jdCivilStart);
        $sunLonCivilEnd = $this->getSunLongitude($jdCivilEnd);
        $civilStartSign = (int) floor($sunLonCivilStart / 30.0) % 12;
        $civilEndSign = (int) floor($sunLonCivilEnd / 30.0) % 12;

        $punyaKaal = null;
        $sankrantiRashi = null;
        if ($civilStartSign !== $civilEndSign) {
            // Sun crossed a sign boundary during this civil day
            $nextSign = ($civilStartSign + 1) % 12;
            $targetAngle = $nextSign * 30.0;
            // Search from civil start to find exact Sankranti moment
            $sankrantiJd = $this->findAngleCrossing($jdCivilStart, $targetAngle, 1, fn (float $jd): float => $this->getSunLongitude($jd));
            if ($sankrantiJd >= $jdCivilStart && $sankrantiJd < $jdCivilEnd) {
                $sankrantiName = $sankrantiNameMap[$nextSign];
                $sankrantiRashi = $nextSign;
                $punyaKaal = $kalaEngine->calculatePunyaKaal($sankrantiName, $sankrantiJd, $jdSunrise, $jdSunset);
            }
        }

        $samvat = $this->panchanga->getSamvat($date->year, $date->month);
        $vikram = $samvat['Vikram_Samvat'];
        $saka = $samvat['Saka_Samvat'];
        $kali = $this->panchanga->getKaliSamvat($vikram);
        $gujarati = $this->panchanga->getGujaratiSamvat($vikram, $hinduMonth['Amanta_Index']);
        $samvatsara = $this->panchanga->getSamvatsara($vikram);
        $samvatsaraNorth = $this->panchanga->getSamvatsaraNorth($vikram);

        [$moonrise, $moonset] = $this->sunService->getMoonriseMoonset($birthBase);

        $todaySnapshot = [
            'Tithi' => $tithi,
            'Nakshatra' => [
                'name' => $nakName,
            ],
            'Hindu_Calendar' => [
                'Month_Amanta' => $hinduMonth['Month_Amanta'],
                'Month_Amanta_En' => $hinduMonth['Month_Amanta_En'],
                'Month_Purnimanta' => $hinduMonth['Month_Purnimanta'],
                'Month_Purnimanta_En' => $hinduMonth['Month_Purnimanta_En'],
                'Is_Adhika' => $hinduMonth['Is_Adhika'],
                'Is_Kshaya' => $hinduMonth['Is_Kshaya'],
                'Amanta_Index' => $hinduMonth['Amanta_Index'],
                'Purnimanta_Index' => $hinduMonth['Purnimanta_Index'],
                'Calendar_Type' => $calendarType->value,
            ],
            'Resolution_Context' => [
                'sunrise_jd' => $jdSunrise,
                'sunset_jd' => $jdSunset,
                'next_sunrise_jd' => $jdNextSunrise,
                'tithi_start_jd' => $tithiStartJd,
                'tithi_end_jd' => $tithiEndJd,
                'prev_tithi_end_jd' => $tithiStartJd,
                'tithi_index_abs' => $tithiNum,
                'tithi_index_phase' => $tithiNum > 15 ? $tithiNum - 15 : $tithiNum,
                'paksha' => (string) ($tithi['paksha'] ?? ''),
                'sunrise_iso' => AstroCore::formatDateTime($relSunrise),
                'sunset_iso' => AstroCore::formatDateTime($sunset),
                'next_sunrise_iso' => AstroCore::formatDateTime($nextSunrise),
                'sankranti_rashi' => $sankrantiRashi,
            ],
        ];

        $tomorrowSnapshot = $this->getFestivalSnapshot($nextDay, $lat, $lon, $tz, $elevation);
        $festivals = $this->festivalService->resolveFestivalsForDate($date, $todaySnapshot, $tomorrowSnapshot);
        $festivals = $this->retainFestivalsForDate($festivals, $date->toDateString());

        $dailyObservances = $this->festivalService->getDailyObservances($todaySnapshot);
        $specialYogas = $this->calculateSpecialYogas($date, $jdSunrise, $jdNextSunrise, $tithiNum, (int) $vara['index'], $tz);
        $anandadiYoga = $this->calculateAnandadiYoga($jdSunrise, $jdNextSunrise, (int) $vara['index'], $tz);
        $amritadiYoga = $this->calculateAmritadiYoga($jdSunrise, $jdNextSunrise, (int) $vara['index'], $tz);
        $panchak = $this->calculatePanchak($jdSunrise, $jdNextSunrise, $tz);
        $maitreyaYoga = $this->calculateMaitreyaYoga($jdSunrise, $jdNextSunrise, (int) $vara['index'], $lagnaTable, $tz);
        $gajachchhayaYoga = $this->calculateGajachchhayaYoga($jdSunrise, $jdNextSunrise, $hinduMonth, $tz);
        $nakshatraShool = $this->calculateNakshatraShool($jdSunrise, $jdNextSunrise, $tz);
        $dishaShool = $this->calculateDishaShool((int) $vara['index']);
        $rahuVaasa = $this->calculateRahuVaasa((int) $vara['index']);
        $chandraVaasa = $this->calculateChandraVaasa($jdSunrise, $jdNextSunrise, $tz);
        $shivaVaasa = $this->calculateShivaVaasa($tithiNum, $tithiEndJd, $tz);
        $agniVaasa = $this->calculateAgniVaasa($tithiNum, (int) $vara['index'], $tithiEndJd, $tz);
        $yoginiVaasa = $this->calculateYoginiVaasa($tithiNum);
        $transitionSignals = $this->buildTransitionSignals(
            $jdSunrise,
            $jdNextSunrise,
            $sunLon,
            $moonLon,
            $tithiNum,
            $nakIdx,
            $yogaIdx,
            $karanaIdx,
            $tz,
            $sankrantiRashi
        );
        $ekadashiObservance = $this->buildEkadashiObservance(
            $tithiNum,
            $tithiStartJd,
            $tithiEndJd,
            $jdSunrise,
            $jdSunset,
            $jdNextSunrise,
            $tz,
            $lat,
            $lon
        );

        // Build fivefold lookup by English name (lowercase) since translated names may differ
        $englishNames = ['pratah', 'sangava', 'madhyahna', 'aparahna', 'sayahna'];
        $daylightFivefoldByName = [];
        foreach ($daylightFivefold as $index => $division) {
            if (is_array($division) && isset($englishNames[$index])) {
                $daylightFivefoldByName[$englishNames[$index]] = $division;
            }
        }

        $karmakalaWindows = [
            'sunrise' => [
                'label' => Localization::translate('String', 'Sunrise'),
                'type' => 'instant',
                'time' => AstroCore::formatTime($relSunrise),
                'time_iso' => AstroCore::formatDateTime($relSunrise),
            ],
            'pratah' => $daylightFivefoldByName['pratah'] ?? null,
            'sangava' => $daylightFivefoldByName['sangava'] ?? null,
            'madhyahna' => $daylightFivefoldByName['madhyahna'] ?? null,
            'aparahna' => $daylightFivefoldByName['aparahna'] ?? null,
            'sayahna' => $daylightFivefoldByName['sayahna'] ?? null,
            'pradosha' => $pradoshaKaal,
            'nishitha' => $nishitaMuhurta,
            'brahma_muhurta' => $brahmaMuhurta,
            'abhijit' => $abhijit,
            'vijaya' => $vijayaMuhurta,
            'godhuli' => $godhuliMuhurta,
        ];

        $defaultConfig = function_exists('config') ? config('panchang.defaults', []) : [];
        if (!is_array($defaultConfig)) {
            $defaultConfig = [];
        }

        $payload = [
            'Display_Settings' => [
                'measurement_system' => $options['measurement_system'] ?? $defaultConfig['measurement_system'] ?? 'indian_metric',
                'date_time_format' => $options['date_time_format'] ?? $defaultConfig['date_time_format'] ?? 'indian_12h',
                'time_notation' => $options['time_notation'] ?? $defaultConfig['time_notation'] ?? '12h',
                'coordinate_format' => $options['coordinate_format'] ?? $defaultConfig['coordinate_format'] ?? 'decimal',
                'angle_unit' => $options['angle_unit'] ?? $defaultConfig['angle_unit'] ?? 'degree',
                'duration_format' => $options['duration_format'] ?? $defaultConfig['duration_format'] ?? 'mixed',
                'timezone' => $tz,
            ],
            'Units' => [
                'Ayanamsa_Degree' => 'degree',
                'Ayanamsa_JD' => 'julian_day',
                'sun_sunrise_lon' => 'degree',
                'moon_sunrise_lon' => 'degree',
                'Hora.hora_duration_seconds' => 'second',
                'Hora.hora_duration_minutes' => 'minute',
                'Chogadiya.division_duration_minutes' => 'minute',
                'Rahu_Kaal_Gulika_Yamaganda.Rahu_Kaal.duration_min' => 'minute',
                'Rahu_Kaal_Gulika_Yamaganda.Gulika.duration_min' => 'minute',
                'Rahu_Kaal_Gulika_Yamaganda.Yamaganda.duration_min' => 'minute',
                'Abhijit_Muhurta.muhurta_duration_minutes' => 'minute',
                'Day_Types.civil_day_length_seconds' => 'second',
                'Day_Types.mean_solar_day_seconds' => 'second',
                'Day_Types.apparent_solar_day_seconds' => 'second',
                'Dharma_Sindhu.Punya_Kaal.duration_minutes' => 'minute',
                'Dharma_Sindhu.Punya_Kaal.duration_ghatikas' => 'ghatika',
                'Dharma_Sindhu.Punya_Kaal.sankranti_jd' => 'julian_day',
                'Dharma_Sindhu.Punya_Kaal.punya_kaal_start_jd' => 'julian_day',
                'Dharma_Sindhu.Punya_Kaal.punya_kaal_end_jd' => 'julian_day',
                'Prahara.duration_seconds' => 'second',
                'Prahara.duration_hours' => 'hour',
                'Daylight_Fivefold_Division.duration_seconds' => 'second',
                'Daylight_Fivefold_Division.duration_hours' => 'hour',
                'Brahma_Muhurta.duration_minutes' => 'minute',
                'Brahma_Muhurta.duration_seconds' => 'second',
                'Dur_Muhurta.duration_seconds' => 'second',
                'Nishita_Muhurta.muhurta_duration_seconds' => 'second',
                'Nishita_Muhurta.muhurta_duration_minutes' => 'minute',
                'Vijaya_Muhurta.muhurta_duration_seconds' => 'second',
                'Vijaya_Muhurta.muhurta_duration_minutes' => 'minute',
                'Godhuli_Muhurta.duration_seconds' => 'second',
                'Godhuli_Muhurta.duration_minutes' => 'minute',
                'Sandhya.Pratah_Sandhya.duration_seconds' => 'second',
                'Sandhya.Pratah_Sandhya.duration_minutes' => 'minute',
                'Sandhya.Madhyahna_Sandhya.duration_seconds' => 'second',
                'Sandhya.Madhyahna_Sandhya.duration_minutes' => 'minute',
                'Sandhya.Sayahna_Sandhya.duration_seconds' => 'second',
                'Sandhya.Sayahna_Sandhya.duration_minutes' => 'minute',
                'Gowri_Panchangam.division_duration_seconds' => 'second',
                'Gowri_Panchangam.division_duration_minutes' => 'minute',
                'Kala_Vela.division_duration_seconds' => 'second',
                'Kala_Vela.division_duration_minutes' => 'minute',
                'Varjyam.duration_minutes' => 'minute',
                'Amrita_Kaal.duration_minutes' => 'minute',
                'Pradosha_Kaal.duration_minutes' => 'minute',
                'Lagna.lagna_longitude_nirayana' => 'degree',
                'Lagna.lagna_longitude_sayana' => 'degree',
                'Lagna.degree_in_sign' => 'degree',
                'Lagna.ayanamsa_applied' => 'degree',
            ],
            'Tithi' => $tithi,
            'Vara' => $vara,
            'Nakshatra' => [
                'name' => $nakName,
                'pada' => $nakPada,
                'lord' => $nakLord,
            ],
            'Yoga' => $yoga,
            'Karana' => ['name' => $karanaName, 'index' => $karanaIdx],
            'Is_Vishti_Karana' => $this->panchanga->isVishtiKarana($sunLon, $moonLon),
            'Sunrise' => AstroCore::formatTime($relSunrise),
            'Sunset' => AstroCore::formatTime($sunset),
            'Ishtkaal' => $isth,
            'sun_sunrise_lon' => AstroCore::formatAngle($sunLon),
            'moon_sunrise_lon' => AstroCore::formatAngle($moonLon),
            'sunrise_hm' => [(int) $relSunrise->format('H'), (int) $relSunrise->format('i')],
            'sunrise_dt' => AstroCore::formatDateTime($relSunrise),
            'Ayanamsa' => 'LAHIRI (Chitra Paksha)',
            'Ayanamsa_Degree' => AstroCore::formatAngle($ayanamsaDeg),
            'Ayanamsa_At' => AstroCore::formatDateTime($calculationAt),
            'Ayanamsa_JD' => $ayanamsaJd,
            'Day_Types' => [
                'civil_day_start' => AstroCore::formatDateTime($civilDayStart),
                'civil_day_end' => AstroCore::formatDateTime($civilDayEnd),
                'civil_day_length_seconds' => $civilDaySeconds,
                'mean_solar_day_seconds' => 86400.0,
                'apparent_solar_day_seconds' => $apparentSolarDaySeconds,
                'solar_noon' => AstroCore::formatDateTime($solarTransits['solar_noon']),
                'solar_midnight' => AstroCore::formatDateTime($solarTransits['solar_midnight']),
            ],
            'Twilight' => [
                'civil' => [
                    'dawn' => AstroCore::formatDateTime($twilight['civil']['dawn']),
                    'dusk' => AstroCore::formatDateTime($twilight['civil']['dusk']),
                ],
                'nautical' => [
                    'dawn' => AstroCore::formatDateTime($twilight['nautical']['dawn']),
                    'dusk' => AstroCore::formatDateTime($twilight['nautical']['dusk']),
                ],
                'astronomical' => [
                    'dawn' => AstroCore::formatDateTime($twilight['astronomical']['dawn']),
                    'dusk' => AstroCore::formatDateTime($twilight['astronomical']['dusk']),
                ],
            ],
            'Panchanga' => [
                'Tithi' => $tithi,
                'Vara' => $vara,
                'Nakshatra' => [
                    'name' => $nakName,
                    'pada' => $nakPada,
                    'lord' => $nakLord,
                ],
                'Yoga' => $yoga,
                'Karana' => ['name' => $karanaName, 'index' => $karanaIdx],
                'Sunrise' => [
                    'jd' => $jdSunrise,
                    'iso' => AstroCore::formatDateTime($relSunrise),
                    'display' => AstroCore::formatTime($relSunrise),
                    'timestamp' => $relSunrise->getTimestamp(),
                ],
                'Sunset' => [
                    'jd' => $jdSunset,
                    'iso' => AstroCore::formatDateTime($sunset),
                    'display' => AstroCore::formatTime($sunset),
                    'timestamp' => $sunset->getTimestamp(),
                ],
                'Moonrise' => [
                    'jd' => $moonrise instanceof CarbonImmutable ? $this->toJulianDayFromCarbon($moonrise, $tz) : null,
                    'iso' => $moonrise instanceof CarbonImmutable ? AstroCore::formatDateTime($moonrise) : null,
                    'display' => $moonrise instanceof CarbonImmutable ? AstroCore::formatTime($moonrise) : null,
                    'timestamp' => $moonrise instanceof CarbonImmutable ? $moonrise->getTimestamp() : null,
                ],
                'Moonset' => [
                    'jd' => $moonset instanceof CarbonImmutable ? $this->toJulianDayFromCarbon($moonset, $tz) : null,
                    'iso' => $moonset instanceof CarbonImmutable ? AstroCore::formatDateTime($moonset) : null,
                    'display' => $moonset instanceof CarbonImmutable ? AstroCore::formatTime($moonset) : null,
                    'timestamp' => $moonset instanceof CarbonImmutable ? $moonset->getTimestamp() : null,
                ],
                'Ishtkaal' => $isth,
                'sun_sunrise_lon' => AstroCore::formatAngle($sunLon),
                'moon_sunrise_lon' => AstroCore::formatAngle($moonLon),
                'sunrise_hm' => [(int) $relSunrise->format('H'), (int) $relSunrise->format('i')],
                'sunrise_dt' => AstroCore::formatDateTime($relSunrise),
                'tithi_start_dt' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($tithiStartJd, $tz)),
                'tithi_end_dt' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($tithiEndJd, $tz)),
                'nakshatra_end_dt' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($nakEndJd, $tz)),
                'yoga_end_dt' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($yogaEndJd, $tz)),
                'karana_end_dt' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($karanaEndJd, $tz)),
            ],
            'Hindu_Calendar' => [
                'Ayana' => $this->panchanga->getAyana($sunLon),
                'Ritu' => $this->panchanga->getRitu($sunLon),
                'Vikram_Samvat' => $vikram,
                'Gujarati_Samvat' => $gujarati,
                'Saka_Samvat' => $saka,
                'Kali_Samvat' => $kali,
                'Samvatsara' => $samvatsara,
                'Samvatsara_North' => $samvatsaraNorth,
                'Month_Amanta' => $hinduMonth['Month_Amanta'],
                'Month_Amanta_En' => $hinduMonth['Month_Amanta_En'],
                'Month_Purnimanta' => $hinduMonth['Month_Purnimanta'],
                'Month_Purnimanta_En' => $hinduMonth['Month_Purnimanta_En'],
                'Is_Adhika' => $hinduMonth['Is_Adhika'],
                'Is_Kshaya' => $hinduMonth['Is_Kshaya'],
                'Amanta_Index' => $hinduMonth['Amanta_Index'],
                'Purnimanta_Index' => $hinduMonth['Purnimanta_Index'],
                'Calendar_Type' => $calendarType->value,
            ],
            'Chart_Auxiliary' => [
                'Sun_Sign' => Rasi::from($sunSign)->getName(),
                'Moon_Sign' => Rasi::from($moonSign)->getName(),
            ],
            'Festivals' => $festivals,
            'Daily_Observances' => $dailyObservances,
            'Special_Yogas' => $specialYogas,
            'Anandadi_Yoga' => $anandadiYoga,
            'Amritadi_Yoga' => $amritadiYoga,
            'Panchak' => $panchak,
            'Maitreya_Yoga' => $maitreyaYoga,
            'Gajachchhaya_Yoga' => $gajachchhayaYoga,
            'Nakshatra_Shool' => $nakshatraShool,
            'Disha_Shool' => $dishaShool,
            'Rahu_Vaasa' => $rahuVaasa,
            'Chandra_Vaasa' => $chandraVaasa,
            'Shiva_Vaasa' => $shivaVaasa,
            'Agni_Vaasa' => $agniVaasa,
            'Yogini_Vaasa' => $yoginiVaasa,
            'Ekadashi_Observance' => $ekadashiObservance,
            'Transitions' => $transitionSignals,
            'Panchaka_Rahita' => $panchaka,
            'Hora' => $hora,
            'Chogadiya' => $chogadiya,
            'Chogadiya_Duration' => [
                'day_each_seconds' => ($sunset->getTimestamp() - $relSunrise->getTimestamp()) / 8.0,
                'night_each_seconds' => ($nextSunrise->getTimestamp() - $sunset->getTimestamp()) / 8.0,
            ],
            'Hora_Full_Day' => $horaTable,
            'Chogadiya_Full_Day' => $chogadiyaTable,
            'Muhurta_Full_Day' => $muhurtaTable,
            'Rahu_Kaal_Gulika_Yamaganda' => $badTimes,
            'Abhijit_Muhurta' => $abhijit,

            'Prahara_Full_Day' => $praharaTable,
            'Daylight_Fivefold_Division' => $daylightFivefold,
            'Brahma_Muhurta' => $brahmaMuhurta,
            'Dur_Muhurta_Full_Day' => $durMuhurtaTable,
            'Nishita_Muhurta' => $nishitaMuhurta,
            'Vijaya_Muhurta' => $vijayaMuhurta,
            'Godhuli_Muhurta' => $godhuliMuhurta,
            'Sandhya' => [
                'pratah_sandhya' => [
                    ...$sandhya['pratah_sandhya'],
                    'duration_human' => AstroCore::formatDuration($sandhya['pratah_sandhya']['duration_seconds'] / 60.0),
                ],
                'madhyahna_sandhya' => [
                    ...$sandhya['madhyahna_sandhya'],
                    'duration_human' => AstroCore::formatDuration($sandhya['madhyahna_sandhya']['duration_seconds'] / 60.0),
                ],
                'sayahna_sandhya' => [
                    ...$sandhya['sayahna_sandhya'],
                    'duration_human' => AstroCore::formatDuration($sandhya['sayahna_sandhya']['duration_seconds'] / 60.0),
                ],
            ],
            'Gowri_Panchangam' => $gowriPanchangam,
            'Kala_Vela' => [
                'named_kala_velas' => $kalaVela['named_kala_velas'],
                'day' => $kalaVela['day'],
                'night' => $kalaVela['night'],
            ],
            'Karmakala_Windows' => $karmakalaWindows,
            'Varjyam' => $varjyam,
            'Amrita_Kaal' => $amritaKaal,
            'Pradosha_Kaal' => $pradoshaKaal,
            'Lagna' => $lagna,
            'Lagna_Full_Day' => $lagnaTable,

            'Bhadra' => $this->findBhadraPeriods($jdSunrise, $jdNextSunrise, $tithiNum, (string) ($tithi['paksha'] ?? '')),

            'Dharma_Sindhu' => array_filter([
                'Punya_Kaal' => $punyaKaal,
                'Ekadashi_Observance' => $ekadashiObservance,
                'Shiva_Vaasa' => $shivaVaasa,
                'Agni_Vaasa' => $agniVaasa,
                'Yogini_Vaasa' => $yoginiVaasa,
            ], static fn (?array $v): bool => $v !== null),
        ];

        return $this->annotateTimeOnlyFieldsWithDateTime($payload, $relSunrise, $tz);
    }

    /**
     * Lightweight daily snapshot for festival resolution.
     * Avoids heavy muhurta/tithi-boundary computations to keep yearly festival listing stable.
     */
    public function getFestivalSnapshot(
        CarbonImmutable $date,
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0,
        ?CarbonImmutable $calculationAt = null,
        CalendarType|string $calendarType = CalendarType::Amanta
    ): array {
        // Normalize calendar_type from string to enum
        if (is_string($calendarType)) {
            $calendarType = match (strtolower($calendarType)) {
                'purnimanta', 'purnimant' => CalendarType::Purnimanta,
                default => CalendarType::Amanta,
            };
        }

        $birthBase = [
            'year' => $date->year,
            'month' => $date->month,
            'day' => $date->day,
            'hour' => 0,
            'minute' => 0,
            'second' => 0,
            'timezone' => $tz,
            'latitude' => $lat,
            'longitude' => $lon,
            'elevation' => $elevation,
        ];

        [$sunrise, $sunset] = $this->sunService->getSunriseSunset($birthBase);
        [$moonrise, $moonset] = $this->sunService->getMoonriseMoonset($birthBase);
        $nextDay = $date->addDay();
        [$nextSunrise] = $this->sunService->getSunriseSunset([
            ...$birthBase,
            'year' => $nextDay->year,
            'month' => $nextDay->month,
            'day' => $nextDay->day,
        ]);

        // Use fixed calculation reference (sunrise) for deterministic Ayanamsa
        $ayanamsaRef = $calculationAt ?? $sunrise;
        $ayanamsaJd = $this->toJulianDayFromCarbon($ayanamsaRef, $tz);
        $ayanamsaDeg = $this->astronomy->getAyanamsa($ayanamsaJd);

        $jdSunrise = $this->toJulianDayFromCarbon($sunrise, $tz);
        $jdSunset = $this->toJulianDayFromCarbon($sunset, $tz);
        $jdNextSunrise = $this->toJulianDayFromCarbon($nextSunrise, $tz);
        $sunriseBirth = [
            ...$birthBase,
            'year' => (int) $sunrise->format('Y'),
            'month' => (int) $sunrise->format('m'),
            'day' => (int) $sunrise->format('d'),
            'hour' => (int) $sunrise->format('H'),
            'minute' => (int) $sunrise->format('i'),
            'second' => (int) $sunrise->format('s'),
        ];

        $sunMoon = $this->getSunMoonLongitudes($sunriseBirth);
        $sunLon = $sunMoon['Sun'];
        $moonLon = $sunMoon['Moon'];

        $tithi = $this->panchanga->calculateTithi($sunLon, $moonLon);
        $yoga = $this->panchanga->calculateYoga($sunLon, $moonLon);
        [$karanaName, $karanaIdx] = $this->panchanga->getKarana($sunLon, $moonLon);
        [$nakName, $nakPada, $nakLord] = $this->panchanga->getNakshatraInfo($moonLon);
        // Use sunrise timestamp for snapshot weekday to avoid pre-sunrise rollback
        // from 00:00 civil time into previous day's vara.
        $vara = $this->panchanga->calculateVara($sunriseBirth, $this->sunService);

        $hinduMonth = $this->getTrueHinduMonth($jdSunrise);

        $tithiNum = (int) ($tithi['index'] ?? 0);
        $tithiStartAngle = ($tithiNum - 1) * 12.0;
        $tithiEndAngle = $tithiNum * 12.0;
        $tithiStartJd = $this->findAngleCrossing($jdSunrise, $tithiStartAngle, -1, fn (float $jd): float => $this->getMoonSunAngle($jd));
        $tithiEndJd = $this->findAngleCrossing($jdSunrise, $tithiEndAngle, 1, fn (float $jd): float => $this->getMoonSunAngle($jd));
        $prevTithiEndJd = $tithiStartJd;

        // Sankranti date should map to the civil date on which ingress occurs.
        // Using sunrise->next-sunrise can backshift pre-sunrise ingress to previous date.
        $civilStart = $date->startOfDay();
        $civilEnd = $civilStart->addDay();
        $jdCivilStart = $this->toJulianDayFromCarbon($civilStart, $tz);
        $jdCivilEnd = $this->toJulianDayFromCarbon($civilEnd, $tz);
        $sunLonCivilStart = $this->getSunLongitude($jdCivilStart);
        $sunLonCivilEnd = $this->getSunLongitude($jdCivilEnd);
        $currentSign = (int) floor($sunLonCivilStart / 30.0);
        $nextSunriseSign = (int) floor($sunLonCivilEnd / 30.0);
        $sankrantiRashi = null;
        if ($currentSign !== $nextSunriseSign) {
            $sankrantiRashi = ($currentSign + 1) % 12;
        }

        $lagnaTable = $this->muhurta->calculateLagnaTable(
            $sunrise,
            $sunset,
            $nextSunrise,
            $sunLon,
            $ayanamsaDeg,
            $lat,
            $lon,
            $this->sweph
        );
        $specialYogas = $this->calculateSpecialYogas($date, $jdSunrise, $jdNextSunrise, $tithiNum, (int) $vara['index'], $tz);
        $anandadiYoga = $this->calculateAnandadiYoga($jdSunrise, $jdNextSunrise, (int) $vara['index'], $tz);
        $amritadiYoga = $this->calculateAmritadiYoga($jdSunrise, $jdNextSunrise, (int) $vara['index'], $tz);
        $panchak = $this->calculatePanchak($jdSunrise, $jdNextSunrise, $tz);
        $maitreyaYoga = $this->calculateMaitreyaYoga($jdSunrise, $jdNextSunrise, (int) $vara['index'], $lagnaTable, $tz);
        $gajachchhayaYoga = $this->calculateGajachchhayaYoga($jdSunrise, $jdNextSunrise, $hinduMonth, $tz);
        $nakshatraShool = $this->calculateNakshatraShool($jdSunrise, $jdNextSunrise, $tz);
        $dishaShool = $this->calculateDishaShool((int) $vara['index']);
        $rahuVaasa = $this->calculateRahuVaasa((int) $vara['index']);
        $chandraVaasa = $this->calculateChandraVaasa($jdSunrise, $jdNextSunrise, $tz);
        $shivaVaasa = $this->calculateShivaVaasa($tithiNum, $tithiEndJd, $tz);
        $agniVaasa = $this->calculateAgniVaasa($tithiNum, (int) $vara['index'], $tithiEndJd, $tz);
        $yoginiVaasa = $this->calculateYoginiVaasa($tithiNum);
        $ekadashiObservance = $this->buildEkadashiObservance(
            $tithiNum,
            $tithiStartJd,
            $tithiEndJd,
            $jdSunrise,
            $jdSunset,
            $jdNextSunrise,
            $tz,
            $lat,
            $lon
        );

        return [
            'Tithi' => $tithi,
            'Vara' => $vara,
            'Nakshatra' => [
                'name' => $nakName,
                'pada' => $nakPada,
                'lord' => $nakLord,
            ],
            'Yoga' => $yoga,
            'Karana' => ['name' => $karanaName, 'index' => $karanaIdx],
            'Sun_Sign' => Rasi::from(AstroCore::getSign($sunLon))->getName(),
            'Sun_Sign_Index' => AstroCore::getSign($sunLon),
            'Moon_Sign' => Rasi::from(AstroCore::getSign($moonLon))->getName(),
            'Moon_Sign_Index' => AstroCore::getSign($moonLon),
            'Hindu_Calendar' => [
                'Month_Amanta' => $hinduMonth['Month_Amanta'],
                'Month_Amanta_En' => $hinduMonth['Month_Amanta_En'],
                'Month_Purnimanta' => $hinduMonth['Month_Purnimanta'],
                'Month_Purnimanta_En' => $hinduMonth['Month_Purnimanta_En'],
                'Is_Adhika' => $hinduMonth['Is_Adhika'],
                'Is_Kshaya' => $hinduMonth['Is_Kshaya'],
                'Amanta_Index' => $hinduMonth['Amanta_Index'],
                'Purnimanta_Index' => $hinduMonth['Purnimanta_Index'],
                'Calendar_Type' => $calendarType->value,
            ],
            'Sunrise' => AstroCore::formatTime($sunrise),
            'Sunset' => AstroCore::formatTime($sunset),
            'Moonrise' => $moonrise instanceof CarbonImmutable ? AstroCore::formatTime($moonrise) : null,
            'Moonset' => $moonset instanceof CarbonImmutable ? AstroCore::formatTime($moonset) : null,
            'Moonrise_Date' => $moonrise instanceof CarbonImmutable ? $moonrise->toDateString() : null,
            'Moonset_Date' => $moonset instanceof CarbonImmutable ? $moonset->toDateString() : null,
            'Moonrise_ISO' => $moonrise instanceof CarbonImmutable ? AstroCore::formatDateTime($moonrise) : null,
            'Moonset_ISO' => $moonset instanceof CarbonImmutable ? AstroCore::formatDateTime($moonset) : null,
            'Moonset_Day_Relation' => $this->moonsetDayRelation($date, $moonset),
            'Special_Yogas' => $specialYogas,
            'Anandadi_Yoga' => $anandadiYoga,
            'Amritadi_Yoga' => $amritadiYoga,
            'Panchak' => $panchak,
            'Maitreya_Yoga' => $maitreyaYoga,
            'Gajachchhaya_Yoga' => $gajachchhayaYoga,
            'Nakshatra_Shool' => $nakshatraShool,
            'Disha_Shool' => $dishaShool,
            'Rahu_Vaasa' => $rahuVaasa,
            'Chandra_Vaasa' => $chandraVaasa,
            'Shiva_Vaasa' => $shivaVaasa,
            'Agni_Vaasa' => $agniVaasa,
            'Yogini_Vaasa' => $yoginiVaasa,
            'Ekadashi_Observance' => $ekadashiObservance,
            'Resolution_Context' => [
                'sunrise_jd' => $jdSunrise,
                'sunset_jd' => $jdSunset,
                'next_sunrise_jd' => $jdNextSunrise,
                'tithi_start_jd' => $tithiStartJd,
                'tithi_end_jd' => $tithiEndJd,
                'prev_tithi_end_jd' => $prevTithiEndJd,
                'tithi_index_abs' => $tithiNum,
                'tithi_index_phase' => $tithiNum > 15 ? $tithiNum - 15 : $tithiNum,
                'paksha' => (string) ($tithi['paksha'] ?? ''),
                'sunrise_iso' => AstroCore::formatDateTime($sunrise),
                'sunset_iso' => AstroCore::formatDateTime($sunset),
                'next_sunrise_iso' => AstroCore::formatDateTime($nextSunrise),
                'sankranti_rashi' => $sankrantiRashi,
            ],
            'Bhadra' => $this->findBhadraPeriods($jdSunrise, $jdNextSunrise, $tithiNum, (string) $tithi['paksha']),
        ];
    }

    /**
     * Build a date-indexed festival calendar for a full Gregorian year.
     *
     * This is the package-level orchestration used by CLI/export scripts. It
     * keeps relative-day festivals and adjacent duplicate consolidation out of
     * consumer code.
     *
     * @return array{
     *     year:int,
     *     festival_day_count:int,
     *     festival_entry_count:int,
     *     by_date:array<string, array<int, array<string, mixed>>>,
     *     flat:array<int, array{date:string, festival:array<string, mixed>}>
     * }
     */
    public function getFestivalYearCalendar(
        int $year,
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0,
        ?CarbonImmutable $calculationAt = null,
        CalendarType|string $calendarType = CalendarType::Amanta
    ): array {
        /** @var array<string, array<int, array<string, mixed>>> $festivalsByDate */
        $festivalsByDate = [];
        /** @var array<int, array{date:string, festival:array<string, mixed>}> $festivalFlat */
        $festivalFlat = [];

        $start = CarbonImmutable::create($year, 1, 1, 0, 0, 0, $tz);
        $end = CarbonImmutable::create($year, 12, 31, 0, 0, 0, $tz);

        for ($date = $start; $date->lessThanOrEqualTo($end); $date = $date->addDay()) {
            $todaySnapshot = $this->getFestivalSnapshot($date, $lat, $lon, $tz, $elevation, $calculationAt, $calendarType);
            $tomorrowSnapshot = $this->getFestivalSnapshot($date->addDay(), $lat, $lon, $tz, $elevation, $calculationAt, $calendarType);
            $yesterdaySnapshot = $this->getFestivalSnapshot($date->subDay(), $lat, $lon, $tz, $elevation, $calculationAt, $calendarType);
            $festivals = $this->festivalService->resolveFestivalsForDate($date, $todaySnapshot, $tomorrowSnapshot, $yesterdaySnapshot);

            if ($festivals === []) {
                continue;
            }

            foreach ($festivals as $festival) {
                $dateKey = $this->festivalObservanceDate($festival, $date->toDateString());
                $festivalsByDate[$dateKey] ??= [];
                $festivalsByDate[$dateKey][] = $festival;
                $festivalFlat[] = [
                    'date' => $dateKey,
                    'festival' => $festival,
                ];
            }
        }

        $this->appendRelativeDayFestivals($year, $tz, $festivalsByDate, $festivalFlat);

        $festivalFlat = $this->consolidateAdjacentFestivalsByWinningScore($festivalFlat, $tz);
        $festivalFlat = $this->consolidateYearlySingleObservanceFestivals($festivalFlat);

        $festivalsByDate = [];
        foreach ($festivalFlat as $entry) {
            $dateKey = $entry['date'];
            $festivalsByDate[$dateKey] ??= [];
            $festivalsByDate[$dateKey][] = $entry['festival'];
        }

        ksort($festivalsByDate);

        return [
            'year' => $year,
            'festival_day_count' => count($festivalsByDate),
            'festival_entry_count' => count($festivalFlat),
            'by_date' => $festivalsByDate,
            'flat' => $festivalFlat,
        ];
    }

    /**
     * Get a month-wise calendar summary of Panchang details.
     * Ideal for grid-based calendar views.
     *
     * @return array<string, array<string, mixed>> Indexed by YYYY-MM-DD
     */
    public function getMonthCalendar(
        int $year,
        int $month,
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0,
        array $options = [],
        ?CarbonImmutable $calculationAt = null,
        CalendarType|string $calendarType = CalendarType::Amanta
    ): array {
        // Normalize calendar_type from string to enum
        if (is_string($calendarType)) {
            $calendarType = match (strtolower($calendarType)) {
                'purnimanta', 'purnimant' => CalendarType::Purnimanta,
                default => CalendarType::Amanta,
            };
        }

        $start = CarbonImmutable::create($year, $month, 1, 0, 0, 0, $tz);
        $daysInMonth = $start->daysInMonth;

        // Use the same year-level festival orchestration/consolidation pipeline
        // that powers scripts/panchang_festivals.php so month view never diverges.
        $yearFestivalCalendar = $this->getFestivalYearCalendar(
            year: $year,
            lat: $lat,
            lon: $lon,
            tz: $tz,
            elevation: $elevation,
            calculationAt: $calculationAt,
            calendarType: $calendarType,
        );
        $festivalsByDate = $yearFestivalCalendar['by_date'];

        $snapshots = [];
        for ($i = -1; $i <= $daysInMonth; $i++) {
            $date = $start->addDays($i);
            $snapshots[$i] = $this->getFestivalSnapshot($date, $lat, $lon, $tz, $elevation, $calculationAt, $calendarType);
        }

        $calendar = [];
        for ($i = 0; $i < $daysInMonth; $i++) {
            $date = $start->addDays($i);
            $todaySnapshot = $snapshots[$i];

            $dateKey = $date->toDateString();
            $festivals = $festivalsByDate[$dateKey] ?? [];
            $dailyObservances = $this->festivalService->getDailyObservances($todaySnapshot);

            $sankranti = null;
            if ($todaySnapshot['Resolution_Context']['sankranti_rashi'] !== null) {
                $rashiIdx = $todaySnapshot['Resolution_Context']['sankranti_rashi'];
                $sankranti = Rasi::from($rashiIdx)->getName() . ' ' . Localization::translate('Common', 'Sankranti');
            }

            $calendar[$date->toDateString()] = [
                'date' => $date->toDateString(),
                'day' => (int) $date->format('d'),
                'tithi' => $todaySnapshot['Tithi'],
                'nakshatra' => $todaySnapshot['Nakshatra'],
                'yoga' => $todaySnapshot['Yoga'],
                'karana' => $todaySnapshot['Karana'],
                'vara' => $todaySnapshot['Vara'],
                'sun_sign' => $todaySnapshot['Sun_Sign'],
                'moon_sign' => $todaySnapshot['Moon_Sign'],
                'sunrise' => $todaySnapshot['Sunrise'],
                'sunset' => $todaySnapshot['Sunset'],
                'moonrise' => $todaySnapshot['Moonrise'],
                'moonset' => $todaySnapshot['Moonset'],
                'moonrise_date' => $todaySnapshot['Moonrise_Date'],
                'moonset_date' => $todaySnapshot['Moonset_Date'],
                'moonrise_iso' => $todaySnapshot['Moonrise_ISO'],
                'moonset_iso' => $todaySnapshot['Moonset_ISO'],
                'moonset_day_relation' => $todaySnapshot['Moonset_Day_Relation'],
                'moon_visibility' => [
                    'starts_at' => $todaySnapshot['Moonrise'],
                    'starts_on' => $todaySnapshot['Moonrise_Date'],
                    'starts_iso' => $todaySnapshot['Moonrise_ISO'],
                    'ends_at' => $todaySnapshot['Moonset'],
                    'ends_on' => $todaySnapshot['Moonset_Date'],
                    'ends_iso' => $todaySnapshot['Moonset_ISO'],
                    'ends_day_relation' => $todaySnapshot['Moonset_Day_Relation'],
                ],
                'hindu_calendar' => $todaySnapshot['Hindu_Calendar'],
                'festivals' => $festivals,
                'daily_observances' => $dailyObservances,
                'sankranti' => $sankranti,
            ];
        }

        return $calendar;
    }

    public function getElectionalSnapshot(
        CarbonImmutable $date,
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0,
        array $options = []
    ): array {
        $dayDetails = $this->getDayDetails($date, $lat, $lon, $tz, $elevation, null, CalendarType::Amanta, $options);
        $sunrise = $this->parseDisplayDateTime((string) $dayDetails['sunrise_dt'], $tz);
        [$sunset] = [$this->resolveTimeStringToDateTime((string) $dayDetails['Sunset'], $sunrise, $tz)];

        $sunriseBirth = $this->buildBirthArray($sunrise, $lat, $lon, $tz, $elevation);
        $sunMoon = $this->getSunMoonLongitudes($sunriseBirth);
        $moonLongitude = (float) $sunMoon['Moon'];
        $nakIndex = (int) floor($moonLongitude / (360.0 / 27.0)) + 1;
        $moonSign = (int) floor($moonLongitude / 30.0);
        $ascLongitude = $this->astronomy->getAscendant($sunriseBirth);
        $planetaryStates = $this->astronomy->getPlanetaryStates($sunriseBirth);
        $planets = [];
        foreach ($planetaryStates as $planet => $state) {
            if (isset($state['lon'])) {
                $planets[$planet] = (float) $state['lon'];
            }
        }

        return [
            'day_details' => $dayDetails,
            'transit_moorthy' => ElectionalEvaluator::calculateTransitMoorthy((string) ($dayDetails['Nakshatra']['name'] ?? '')),
            'planetary_states' => $planetaryStates,
            'sunrise_context' => [
                'sunrise_iso' => AstroCore::formatDateTime($sunrise),
                'sunset_iso' => $sunset instanceof CarbonImmutable ? AstroCore::formatDateTime($sunset) : null,
                'nakshatra_index' => $nakIndex,
                'moon_sign_index' => $moonSign,
                'lagna_sign' => Rasi::from(AstroCore::getSign($ascLongitude))->getName(),
                'lagna_degree_in_sign' => fmod(AstroCore::normalize($ascLongitude), 30.0),
            ],
        ];
    }

    /**
     * Compute complete daily Muhurta evaluation package from canonical day details.
     * This centralizes ElectionalEvaluator input extraction so consumer scripts stay thin.
     */
    public function getDailyMuhurtaEvaluation(
        CarbonImmutable $date,
        float $lat,
        float $lon,
        string $tz,
        ?CarbonImmutable $currentAt = null,
        float $elevation = 0.0,
        array $options = []
    ): array {
        $at = $currentAt instanceof CarbonImmutable ? $currentAt->setTimezone($tz) : CarbonImmutable::now($tz);
        $dayDetails = $this->getDayDetails($date, $lat, $lon, $tz, $elevation, $at, CalendarType::Amanta, $options);
        $sunriseDt = $this->parseDisplayDateTime((string) ($dayDetails['sunrise_dt'] ?? ''), $tz);
        $currentBirth = $this->buildBirthArray($at, $lat, $lon, $tz, $elevation);
        $sunMoon = $this->getSunMoonLongitudes($currentBirth);
        $moonLongitude = (float) ($sunMoon['Moon'] ?? 0.0);
        $moonSignIdx = ((int) floor($moonLongitude / 30.0)) % 12;
        $nakshatraIdxCurrent = ((int) floor($moonLongitude / (360.0 / 27.0))) % 27;
        $currentTithi = $this->panchanga->calculateTithi((float) ($sunMoon['Sun'] ?? 0.0), $moonLongitude);
        $currentAscLongitude = $this->astronomy->getAscendant($currentBirth);

        $tithiNumber = max(1, (int) ($currentTithi['index'] ?? ($dayDetails['Tithi']['index'] ?? 1)));
        $varaNumber = ((int) ($this->panchanga->calculateVara($currentBirth, $this->sunService)['index'] ?? ($dayDetails['Vara']['index'] ?? 0))) % 7;
        $nakshatraNumber = max(1, $nakshatraIdxCurrent + 1);
        $lagnaNumber = (AstroCore::getSign($currentAscLongitude) % 12) + 1;
        $isKrishnaPaksha = strcasecmp((string) ($currentTithi['paksha'] ?? ($dayDetails['Tithi']['paksha'] ?? '')), 'Krishna') === 0;
        $tithiEnum = Tithi::from($tithiNumber);
        $varaEnum = Vara::from($varaNumber);
        $nakshatraEnum = Nakshatra::from($nakshatraNumber - 1);
        $lagnaEnum = Rasi::from($lagnaNumber - 1);
        $moonSignEnum = Rasi::from($moonSignIdx);

        $sunsetDt = $this->resolveTimeStringToDateTime((string) ($dayDetails['Sunset'] ?? ''), $sunriseDt, $tz) ?? $sunriseDt;
        $nextSunriseIso = (string) (($dayDetails['Resolution_Context']['next_sunrise_iso'] ?? $dayDetails['next_sunrise_iso'] ?? ''));
        $nextSunriseDt = $nextSunriseIso !== ''
            ? $this->parseDisplayDateTime($nextSunriseIso, $tz)
            : $sunriseDt->addDay();
        $midnight = $sunriseDt->startOfDay();

        $sunrise = $this->toDecimalHoursFromBase($sunriseDt, $midnight);
        $sunset = $this->toDecimalHoursFromBase($sunsetDt, $midnight);
        $nextSunrise = $this->toDecimalHoursFromBase($nextSunriseDt, $midnight);
        $currentTime = $this->toDecimalHoursFromBase($at, $midnight);

        $evaluationResults = [
            'panchaka_dosha' => ElectionalEvaluator::calculatePanchakaDosha($tithiNumber, $varaNumber + 1, $nakshatraNumber, $lagnaNumber),
            'dagdha_tithi' => ElectionalEvaluator::calculateDagdhaTithi($tithiNumber, $moonSignIdx),
            'dagdha_yoga' => ElectionalEvaluator::calculateDagdhaYoga($varaNumber, $tithiNumber),
            'bhadra' => $this->evaluateCurrentBhadra($at, (array) ($dayDetails['Bhadra'] ?? [])),
            'rikta_tithi' => ElectionalEvaluator::calculateRiktaTithi($tithiNumber, $isKrishnaPaksha),
            'varjyam' => $this->evaluateCurrentVarjyam($at, (array) ($dayDetails['Varjyam'] ?? []), $tz),
            'amrita_kaal' => $this->evaluateCurrentNamedWindow(
                $at,
                (array) ($dayDetails['Amrita_Kaal'] ?? []),
                'amrita_kaal_start',
                'amrita_kaal_end',
                Localization::translate('String', 'Amrita Kaal'),
                Localization::translate('Source', 'Classical Panchanga Calculation Texts')
            ),
            'abhijit_cancellation' => ElectionalEvaluator::calculateAbhijitCancellation($sunrise, $sunset, $varaNumber, $currentTime),
        ];

        $rejectionReport = ElectionalEvaluator::generateRejectionReport($evaluationResults);

        return [
            'title' => Localization::translate('String', 'Complete Muhurta Evaluation - Centralized package output'),
            'input_now' => AstroCore::formatDateTime($at),
            'date' => $date->toDateString(),
            'input_parameters' => [
                'tithi_number' => $tithiNumber,
                'tithi_name' => $tithiEnum->getName(),
                'tithi_number_base' => 1,
                'vara_number' => $varaNumber,
                'vara_name' => $varaEnum->getName(),
                'vara_index_base' => 0,
                'vara_sequence_number' => $varaNumber + 1,
                'vara_sequence_number_base' => 1,
                'nakshatra_number' => $nakshatraNumber,
                'nakshatra_name' => $nakshatraEnum->getName(),
                'nakshatra_number_base' => 1,
                'lagna_number' => $lagnaNumber,
                'lagna_name' => $lagnaEnum->getName(),
                'lagna_number_base' => 1,
                'moon_sign_idx' => $moonSignIdx,
                'moon_sign_name' => $moonSignEnum->getName(),
                'moon_sign_index_base' => 0,
                'moon_sign_number' => $moonSignIdx + 1,
                'moon_sign_number_base' => 1,
                'is_krishna_paksha' => $isKrishnaPaksha,
                'sunrise' => AstroCore::formatTime($sunriseDt),
                'sunrise_iso' => AstroCore::formatDateTime($sunriseDt),
                'sunrise_decimal_hours' => $sunrise,
                'sunset' => AstroCore::formatTime($sunsetDt),
                'sunset_iso' => AstroCore::formatDateTime($sunsetDt),
                'sunset_decimal_hours' => $sunset,
                'next_sunrise' => AstroCore::formatTime($nextSunriseDt),
                'next_sunrise_iso' => AstroCore::formatDateTime($nextSunriseDt),
                'next_sunrise_decimal_hours' => $nextSunrise,
                'current_time' => AstroCore::formatTime($at),
                'current_time_iso' => AstroCore::formatDateTime($at),
                'current_time_decimal_hours' => $currentTime,
            ],
            'evaluation_results' => $evaluationResults,
            'rejection_report' => $rejectionReport,
        ];
    }

    private function moonsetDayRelation(CarbonImmutable $date, ?CarbonImmutable $moonset): ?string
    {
        if (!$moonset instanceof CarbonImmutable) {
            return null;
        }

        return $moonset->isSameDay($date) ? 'same_day' : 'next_day';
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $festivalsByDate
     * @param array<int, array{date:string, festival:array<string, mixed>}> $festivalFlat
     */
    private function appendRelativeDayFestivals(
        int $year,
        string $tz,
        array &$festivalsByDate,
        array &$festivalFlat
    ): void {
        foreach (FestivalService::FESTIVALS as $festName => $rules) {
            if ((string) ($rules['type'] ?? '') !== 'day_after') {
                continue;
            }

            $parentName = (string) ($rules['parent_festival'] ?? '');
            $daysAfter = (int) ($rules['days_after'] ?? 1);
            if ($parentName === '') {
                continue;
            }

            $parentDisplayName = Localization::translate('Festival', $parentName);
            foreach ($festivalsByDate as $observanceDate => $observedFestivals) {
                foreach ($observedFestivals as $observedFestival) {
                    $rawObservedName = (string) ($observedFestival['resolution']['festival_name'] ?? '');
                    $displayObservedName = (string) ($observedFestival['name'] ?? '');
                    if ($rawObservedName !== $parentName && $displayObservedName !== $parentDisplayName) {
                        continue;
                    }

                    $relativeDate = CarbonImmutable::parse($observanceDate, $tz)->addDays($daysAfter);
                    if ($relativeDate->year !== $year) {
                        continue;
                    }

                    $relativeDateKey = $relativeDate->toDateString();
                    $observanceNoteTemplate = Localization::translate('String', 'observance_note_day_after');
                    $observanceNote = $observanceNoteTemplate !== 'observance_note_day_after'
                        ? sprintf($observanceNoteTemplate, $daysAfter, $parentDisplayName)
                        : 'Observed ' . $daysAfter . ' day(s) after ' . $parentDisplayName;

                    $festival = $this->festivalService->buildFestivalPayload($festName, $rules, [
                        'festival_name' => $festName,
                        'standard_date' => $relativeDateKey,
                        'observance_date' => $relativeDateKey,
                        'observance_note' => $observanceNote,
                        'decision' => [
                            'winning_reason' => 'day_after_parent_festival',
                            'parent_festival' => $parentName,
                            'parent_observance_date' => $observanceDate,
                            'days_after' => $daysAfter,
                            'winning_score' => 1000,
                        ],
                    ]);

                    $festivalsByDate[$relativeDateKey] ??= [];
                    $festivalsByDate[$relativeDateKey][] = $festival;
                    $festivalFlat[] = [
                        'date' => $relativeDateKey,
                        'festival' => $festival,
                    ];
                }
            }
        }
    }

    /**
     * For adjacent duplicate observances of the same festival, keep the
     * strongest rule-engine decision.
     *
     * @param array<int, array{date:string, festival:array<string, mixed>}> $festivalFlat
     *
     * @return array<int, array{date:string, festival:array<string, mixed>}>
     */
    private function consolidateAdjacentFestivalsByWinningScore(array $festivalFlat, string $tz): array
    {
        $grouped = [];
        foreach ($festivalFlat as $idx => $entry) {
            $name = (string) ($entry['festival']['resolution']['festival_name'] ?? $entry['festival']['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $grouped[$name][] = ['idx' => $idx, 'entry' => $entry];
        }

        $remove = [];
        foreach ($grouped as $items) {
            usort($items, static fn (array $a, array $b): int => strcmp($a['entry']['date'], $b['entry']['date']));

            $clusters = [];
            $current = [];
            $previousDate = null;

            foreach ($items as $item) {
                $date = CarbonImmutable::parse($item['entry']['date'], $tz);
                if (!$previousDate instanceof CarbonImmutable || $previousDate->diffInDays($date) <= 1) {
                    $current[] = $item;
                } else {
                    $clusters[] = $current;
                    $current = [$item];
                }

                $previousDate = $date;
            }

            $clusters[] = $current;

            foreach ($clusters as $cluster) {
                if (count($cluster) <= 1) {
                    continue;
                }

                $best = null;
                foreach ($cluster as $candidate) {
                    $festival = $candidate['entry']['festival'];
                    $rules = (array) ($festival['rules_applied'] ?? []);
                    $score = (int) ($rules['winning_score'] ?? -1);
                    $reason = (string) ($rules['winning_reason'] ?? '');
                    $date = $candidate['entry']['date'];
                    $vriddhiPreference = (string) ($rules['vriddhi_preference'] ?? $festival['resolution']['decision']['vriddhi_preference'] ?? '');

                    if ($score < 0) {
                        continue;
                    }

                    if ($best === null || $this->isStrongerFestivalDecision($score, $reason, $date, $vriddhiPreference, $best)) {
                        $best = [
                            'idx' => $candidate['idx'],
                            'score' => $score,
                            'reason' => $reason,
                            'date' => $date,
                            'vriddhi_preference' => $vriddhiPreference,
                        ];
                    }
                }

                if ($best === null) {
                    continue;
                }

                foreach ($cluster as $candidate) {
                    if ($candidate['idx'] !== $best['idx']) {
                        $remove[$candidate['idx']] = true;
                    }
                }
            }
        }

        if ($remove === []) {
            return $festivalFlat;
        }

        $filtered = [];
        foreach ($festivalFlat as $idx => $entry) {
            if (!isset($remove[$idx])) {
                $filtered[] = $entry;
            }
        }

        return $filtered;
    }

    /**
     * Keep a single best observance per year for configured festival names.
     * Useful for Adhika/Nija duplicate resolutions when observances are not adjacent dates.
     *
     * @param array<int, array{date:string, festival:array<string, mixed>}> $festivalFlat
     *
     * @return array<int, array{date:string, festival:array<string, mixed>}>
     */
    private function consolidateYearlySingleObservanceFestivals(array $festivalFlat): array
    {
        if ($festivalFlat === []) {
            return $festivalFlat;
        }

        $targets = array_flip(self::YEARLY_SINGLE_OBSERVANCE_FESTIVALS);
        $grouped = [];
        foreach ($festivalFlat as $idx => $entry) {
            $name = (string) ($entry['festival']['resolution']['festival_name'] ?? $entry['festival']['name'] ?? '');
            if ($name === '' || !isset($targets[$name])) {
                continue;
            }

            $grouped[$name][] = ['idx' => $idx, 'entry' => $entry];
        }

        $remove = [];
        foreach ($grouped as $items) {
            if (count($items) <= 1) {
                continue;
            }

            $best = null;
            foreach ($items as $candidate) {
                $festival = $candidate['entry']['festival'];
                $rules = (array) ($festival['rules_applied'] ?? []);
                $score = (int) ($rules['winning_score'] ?? -1);
                $reason = (string) ($rules['winning_reason'] ?? '');
                $date = $candidate['entry']['date'];

                $reasonRank = match ($reason) {
                    'target_at_karmakala' => 2,
                    'target_during_observance' => 1,
                    default => 0,
                };

                if ($best === null
                    || $score > $best['score']
                    || ($score === $best['score'] && $reasonRank > $best['reason_rank'])
                    || ($score === $best['score'] && $reasonRank === $best['reason_rank'] && strcmp($date, $best['date']) < 0)) {
                    $best = [
                        'idx' => $candidate['idx'],
                        'score' => $score,
                        'reason_rank' => $reasonRank,
                        'date' => $date,
                    ];
                }
            }

            foreach ($items as $candidate) {
                if ($candidate['idx'] !== $best['idx']) {
                    $remove[$candidate['idx']] = true;
                }
            }
        }

        if ($remove === []) {
            return $festivalFlat;
        }

        $filtered = [];
        foreach ($festivalFlat as $idx => $entry) {
            if (!isset($remove[$idx])) {
                $filtered[] = $entry;
            }
        }

        return $filtered;
    }

    /** @param array{score:int, reason:string, date:string, vriddhi_preference:string} $best */
    private function isStrongerFestivalDecision(int $score, string $reason, string $date, string $vriddhiPreference, array $best): bool
    {
        $reasonRank = static fn (string $r): int => match ($r) {
            'target_at_karmakala' => 2,
            'target_during_observance' => 1,
            default => 0,
        };

        $sameScore = $score === $best['score'];
        $sameReason = $reasonRank($reason) === $reasonRank($best['reason']);
        $sameVriddhiPreference = $vriddhiPreference !== ''
            && $vriddhiPreference === $best['vriddhi_preference'];

        if ($sameScore && $sameReason && $sameVriddhiPreference) {
            if ($vriddhiPreference === 'first') {
                return strcmp($date, $best['date']) < 0;
            }

            if ($vriddhiPreference === 'last') {
                return strcmp($date, $best['date']) > 0;
            }
        }

        return $score > $best['score']
            || ($score === $best['score'] && $reasonRank($reason) > $reasonRank($best['reason']))
            || ($score === $best['score']
                && $reasonRank($reason) === $reasonRank($best['reason'])
                && $vriddhiPreference === ''
                && strcmp($date, $best['date']) > 0);
    }

    /** @param array<string, mixed> $festival */
    private function festivalObservanceDate(array $festival, string $fallbackDate): string
    {
        $observanceDate = (string) ($festival['resolution']['observance_date'] ?? '');
        if ($observanceDate !== '') {
            return $observanceDate;
        }

        return $fallbackDate;
    }

    /**
     * @param array<int, array<string, mixed>> $festivals
     *
     * @return array<int, array<string, mixed>>
     */
    private function retainFestivalsForDate(array $festivals, string $dateKey): array
    {
        return array_values(array_filter(
            $festivals,
            fn (array $festival): bool => $this->festivalObservanceDate($festival, $dateKey) === $dateKey
        ));
    }

    private function evaluateCurrentBhadra(CarbonImmutable $at, array $bhadraPeriods): array
    {
        $active = null;
        $activePart = null;

        foreach ($bhadraPeriods as $period) {
            if (!is_array($period)) {
                continue;
            }

            $periodStart = isset($period['start_time_iso']) ? $this->parseDisplayDateTime((string) $period['start_time_iso'], $at->timezoneName) : null;
            $periodEnd = isset($period['end_time_iso']) ? $this->parseDisplayDateTime((string) $period['end_time_iso'], $at->timezoneName) : null;
            if (!$periodStart instanceof CarbonImmutable || !$periodEnd instanceof CarbonImmutable || $at < $periodStart || $at >= $periodEnd) {
                continue;
            }

            $active = $period;
            foreach (['mukha', 'madhya', 'puchha'] as $partKey) {
                $part = (array) (($period['parts'] ?? [])[$partKey] ?? []);
                $partStart = isset($part['start_time_iso']) ? $this->parseDisplayDateTime((string) $part['start_time_iso'], $at->timezoneName) : null;
                $partEnd = isset($part['end_time_iso']) ? $this->parseDisplayDateTime((string) $part['end_time_iso'], $at->timezoneName) : null;
                if ($partStart instanceof CarbonImmutable && $partEnd instanceof CarbonImmutable && $at >= $partStart && $at < $partEnd) {
                    $activePart = $partKey;
                    break;
                }
            }

            break;
        }

        $hasDosha = $activePart === 'mukha' || $activePart === 'madhya';
        $severity = $activePart === 'mukha' ? 'critical' : ($activePart === 'madhya' ? 'high' : 'none');

        return [
            'source' => Localization::translate('Source', 'Muhurta Martanda / Bhadra (Vishti Karana) window from Panchang day calculation'),
            'is_active' => $active !== null,
            'active_part' => $activePart,
            'active_period' => $active,
            'has_dosha' => $hasDosha,
            'severity' => $severity,
            'is_auspicious' => !$hasDosha,
            'description' => $active === null
                ? Localization::translate('MuhurtaDesc', 'Bhadra not active')
                : ($hasDosha ? Localization::translate('MuhurtaDesc', 'Bhadra active blocked') : Localization::translate('MuhurtaDesc', 'Bhadra puchha active')),
        ];
    }

    private function evaluateCurrentVarjyam(CarbonImmutable $at, array $varjyam, string $tz): array
    {
        $windows = [];
        if (isset($varjyam['windows']) && is_array($varjyam['windows']) && $varjyam['windows'] !== []) {
            $windows = $varjyam['windows'];
        } elseif ($varjyam !== []) {
            $windows = [$varjyam];
        }

        $activeWindow = null;
        foreach ($windows as $window) {
            if (!is_array($window)) {
                continue;
            }

            $start = null;
            $end = null;
            if (isset($window['window_start_jd'], $window['window_end_jd'])) {
                $start = $this->sunService->jdToCarbonPublic((float) $window['window_start_jd'], $tz);
                $end = $this->sunService->jdToCarbonPublic((float) $window['window_end_jd'], $tz);
                $window['window_start_iso'] ??= AstroCore::formatDateTime($start);
                $window['window_end_iso'] ??= AstroCore::formatDateTime($end);
            } else {
                $start = $this->resolveNamedWindowBoundary($window, 'varjyam_start', $tz);
                $end = $this->resolveNamedWindowBoundary($window, 'varjyam_end', $tz);
            }

            if ($start instanceof CarbonImmutable && $end instanceof CarbonImmutable && $at >= $start && $at < $end) {
                $activeWindow = $window;
                break;
            }
        }

        $isActive = $activeWindow !== null;

        return [
            'source' => Localization::translate('Source', 'Varjyam (Tyajyam) window from Panchang day calculation'),
            'is_active' => $isActive,
            'active_window' => $activeWindow,
            'window_count' => count($windows),
            'severity' => $isActive ? 'high' : 'none',
            'is_auspicious' => !$isActive,
            'description' => $isActive ? Localization::translate('MuhurtaDesc', 'Varjyam active') : Localization::translate('MuhurtaDesc', 'Varjyam not active'),
        ];
    }

    private function evaluateCurrentNamedWindow(
        CarbonImmutable $at,
        array $window,
        string $startKey,
        string $endKey,
        string $label,
        string $source
    ): array {
        $start = $this->resolveNamedWindowBoundary($window, $startKey, $at->timezoneName);
        $end = $this->resolveNamedWindowBoundary($window, $endKey, $at->timezoneName);

        if (!$start instanceof CarbonImmutable || !$end instanceof CarbonImmutable) {
            return [
                'source' => $source,
                'label' => $label,
                'is_active' => false,
                'is_available' => false,
                'is_auspicious' => false,
                'description' => $label . ' ' . Localization::translate('MuhurtaDesc', 'Named window not available'),
            ];
        }

        $isActive = $at >= $start && $at < $end;

        return [
            'source' => $source,
            'label' => $label,
            'is_active' => $isActive,
            'is_available' => true,
            'is_auspicious' => $isActive,
            'window' => [
                'start_iso' => AstroCore::formatDateTime($start),
                'end_iso' => AstroCore::formatDateTime($end),
            ],
            'description' => $isActive
                ? $label . ' ' . Localization::translate('MuhurtaDesc', 'Named window active')
                : $label . ' ' . Localization::translate('MuhurtaDesc', 'Named window not active'),
        ];
    }

    private function toDecimalHoursFromBase(CarbonImmutable $dt, CarbonImmutable $base): float
    {
        return ($dt->getTimestamp() - $base->getTimestamp()) / 3600.0;
    }

    private function resolveNamedWindowBoundary(array $window, string $key, string $tz): ?CarbonImmutable
    {
        $isoKey = $key . '_iso';
        if (isset($window[$isoKey]) && is_string($window[$isoKey]) && $window[$isoKey] !== '') {
            return $this->parseDisplayDateTime($window[$isoKey], $tz);
        }

        if (isset($window[$key]) && is_string($window[$key]) && $window[$key] !== '') {
            return $this->parseDisplayDateTime($window[$key], $tz);
        }

        return null;
    }

    private function toJulianDayFromCarbon(CarbonImmutable $dt, string $tz): float
    {
        return $this->astronomy->toJulianDayUtc([
            'year' => (int) $dt->format('Y'),
            'month' => (int) $dt->format('m'),
            'day' => (int) $dt->format('d'),
            'hour' => (int) $dt->format('H'),
            'minute' => (int) $dt->format('i'),
            'second' => (int) $dt->format('s'),
            'timezone' => $tz,
        ]);
    }

    private function getMoonSunAngle(float $jd): float
    {
        $flags = SwissEphFFI::SEFLG_SWIEPH | SwissEphFFI::SEFLG_SIDEREAL;
        $sun = $this->calcBodyAtJd($jd, SwissEphFFI::SE_SUN, $flags);
        $moon = $this->calcBodyAtJd($jd, SwissEphFFI::SE_MOON, $flags);
        return AstroCore::normalize($moon - $sun);
    }

    private function getSunLongitude(float $jd): float
    {
        $flags = SwissEphFFI::SEFLG_SWIEPH | SwissEphFFI::SEFLG_SIDEREAL;
        return $this->calcBodyAtJd($jd, SwissEphFFI::SE_SUN, $flags);
    }

    private function getMoonLongitude(float $jd): float
    {
        $flags = SwissEphFFI::SEFLG_SWIEPH | SwissEphFFI::SEFLG_SIDEREAL;
        return $this->calcBodyAtJd($jd, SwissEphFFI::SE_MOON, $flags);
    }

    private function findBhadraPeriods(float $jdStart, float $jdEnd, int $sunriseTithi, string $paksha): array
    {
        $vishtiKaranas = [8, 15, 22, 29, 36, 43, 50, 57];
        $bhadraPeriods = [];

        // Check the range for any Karana transition into Vishti
        // A day usually has 2 Karanas. We check at start, and find transitions.
        $currentJd = $jdStart;
        while ($currentJd < $jdEnd) {
            $angle = $this->getMoonSunAngle($currentJd);
            $karanaNum = (int) floor($angle / 6.0) + 1;

            if (in_array($karanaNum, $vishtiKaranas, true)) {
                $vStartAngle = ($karanaNum - 1) * 6.0;
                $vEndAngle = $karanaNum * 6.0;

                $vStartJd = $this->findAngleCrossing($currentJd, $vStartAngle, -1, fn (float $jd): float => $this->getMoonSunAngle($jd));
                $vEndJd = $this->findAngleCrossing($currentJd, $vEndAngle, 1, fn (float $jd): float => $this->getMoonSunAngle($jd));

                // Constrain to the day
                $actualStart = max($jdStart, $vStartJd);
                $actualEnd = min($jdEnd, $vEndJd);

                if ($actualStart < $actualEnd) {
                    $moonRasi = (int) floor($this->getMoonLongitude($actualStart) / 30.0);
                    $bhadraPeriods[] = $this->bhadraEngine->calculateBhadra(
                        $jdStart,
                        $vStartJd,
                        $vEndJd,
                        $moonRasi,
                        $sunriseTithi,
                        $paksha
                    );
                }

                $currentJd = $vEndJd + 0.01; // Move past this Karana
            } else {
                // Find next Karana crossing
                $nextKaranaAngle = ceil(($angle + 0.0001) / 6.0) * 6.0;
                $nextKaranaJd = $this->findAngleCrossing($currentJd, $nextKaranaAngle, 1, fn (float $jd): float => $this->getMoonSunAngle($jd));
                $currentJd = $nextKaranaJd + 0.001;
            }
        }

        return $bhadraPeriods;
    }

    private function getSunMoonSum(float $jd): float
    {
        $flags = SwissEphFFI::SEFLG_SWIEPH | SwissEphFFI::SEFLG_SIDEREAL;
        $sun = $this->calcBodyAtJd($jd, SwissEphFFI::SE_SUN, $flags);
        $moon = $this->calcBodyAtJd($jd, SwissEphFFI::SE_MOON, $flags);
        return AstroCore::normalize($sun + $moon);
    }

    /**
     * Add deterministic *_iso companions for time-only fields in payload.
     * Rule: times earlier than sunrise belong to the next civil date in Panchang-day context.
     */
    private function annotateTimeOnlyFieldsWithDateTime(array $payload, CarbonImmutable $sunrise, string $tz): array
    {
        $annotate = function (array $node) use (&$annotate, $sunrise, $tz): array {
            $lastResolvedDt = null;
            foreach ($node as $key => $value) {
                if (is_array($value)) {
                    $node[$key] = $annotate($value);
                    // Reset context for next array/object at same level?
                    // Usually ranges are in the same object.
                    continue;
                }

                if (!is_string($value) || !$this->isTimeOnlyString($value)) {
                    // If it's an ISO string, track it as last resolved
                    if (is_string($value) && str_contains($value, 'T') && str_contains($value, ':')) {
                        try {
                            $lastResolvedDt = CarbonImmutable::parse($value, $tz);
                        } catch (Throwable) {}
                    }

                    continue;
                }

                $isoKey = $key . '_iso';
                if (array_key_exists($isoKey, $node)) {
                    try {
                        $lastResolvedDt = CarbonImmutable::parse((string)$node[$isoKey], $tz);
                    } catch (Throwable) {}

                    continue;
                }

                $dt = $this->resolveTimeStringToDateTime($value, $sunrise, $tz, $lastResolvedDt);
                if ($dt instanceof CarbonImmutable) {
                    $node[$isoKey] = AstroCore::formatDateTime($dt);
                    $lastResolvedDt = $dt;
                }
            }

            return $node;
        };

        return $annotate($payload);
    }

    private function isTimeOnlyString(string $value): bool
    {
        $v = trim($value);
        if ($v === '') {
            return false;
        }

        // Skip values that already include a date marker.
        if (str_contains($v, '/') || str_contains($v, '-') || str_contains($v, ',')) {
            return false;
        }

        $v = rtrim($v, '*');

        return (bool) preg_match('/^\d{1,2}:\d{2}(:\d{2})?\s?(AM|PM)?$/i', $v);
    }

    private function resolveTimeStringToDateTime(string $timeString, CarbonImmutable $sunrise, string $tz, ?CarbonImmutable $baseTime = null): ?CarbonImmutable
    {
        $raw = trim(rtrim($timeString, '*'));
        $formats = ['h:i:s A', 'h:i A', 'H:i:s', 'H:i'];

        foreach ($formats as $format) {
            try {
                $parsed = CarbonImmutable::createFromFormat($format, $raw, $tz);
            } catch (Throwable) {
                $parsed = false;
            }

            if ($parsed === false) {
                continue;
            }

            $reference = $baseTime ?? $sunrise;
            $dt = $reference->setTime((int) $parsed->format('H'), (int) $parsed->format('i'), (int) $parsed->format('s'));

            // If we have a base time (like a start time), ensure this time is after it
            if ($baseTime instanceof CarbonImmutable) {
                if ($dt->lessThan($baseTime)) {
                    $dt = $dt->addDay();
                }
            } else {
                // Original logic relative to sunrise
                $secondsDeltaFromSunrise = abs($dt->diffInSeconds($sunrise, false));
                if ($dt->lessThan($sunrise) && $secondsDeltaFromSunrise >= 60) {
                    $dt = $dt->addDay();
                }
            }

            return $dt;
        }

        return null;
    }

    /**
     * Calculate all Varjyam windows that overlap this Panchang day (sunrise -> next sunrise).
     *
     * @return array<int, array<string, mixed>>
     */
    private function calculateVarjyamWindows(
        CarbonImmutable $sunrise,
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise,
        float $jdSunrise,
        float $jdNextSunrise,
        int $nakIdxAtSunrise,
        float $nakStartJdAtSunrise,
        float $nakEndJdAtSunrise
    ): array {
        $windows = [];
        $nakshatraSpan = 360.0 / 27.0;

        $currentNakIdx = $nakIdxAtSunrise;
        $currentNakStartJd = $nakStartJdAtSunrise;
        $currentNakEndJd = $nakEndJdAtSunrise;

        for ($i = 0; $i < 4; $i++) {
            if ($currentNakStartJd >= $jdNextSunrise) {
                break;
            }

            $window = $this->muhurta->calculateVarjyam(
                $sunrise,
                $sunset,
                $nextSunrise,
                $currentNakIdx,
                $currentNakStartJd,
                $currentNakEndJd
            );

            $windowStartJd = $window['nakshatra_start_jd'] + (($window['tyajya_ghati_start'] / 60.0) * ($window['nakshatra_end_jd'] - $window['nakshatra_start_jd']));
            $windowEndJd = $windowStartJd + (($window['tyajya_ghati_end'] - $window['tyajya_ghati_start']) / 60.0) * ($window['nakshatra_end_jd'] - $window['nakshatra_start_jd']);

            if ($windowEndJd > $jdSunrise && $windowStartJd < $jdNextSunrise) {
                $window['window_start_jd'] = $windowStartJd;
                $window['window_end_jd'] = $windowEndJd;
                $windows[] = $window;
            }

            $currentNakIdx = ($currentNakIdx + 1) % 27;
            $currentNakStartJd = $currentNakEndJd;
            $targetAngle = (($currentNakIdx + 1) % 27) * $nakshatraSpan;
            $currentNakEndJd = $this->findAngleCrossing(
                $currentNakStartJd + 1e-6,
                $targetAngle,
                1,
                fn (float $jd): float => $this->getMoonLongitude($jd)
            );

            if ($currentNakEndJd <= $currentNakStartJd) {
                break;
            }
        }

        usort(
            $windows,
            static fn (array $a, array $b): int => $a['window_start_jd'] <=> $b['window_start_jd']
        );

        return $windows;
    }

    /** Backward-compatible Varjyam payload with multi-window support. */
    private function buildVarjyamPayload(array $windows): array
    {
        if ($windows === []) {
            return [
                'is_available' => false,
                'window_count' => 0,
                'windows' => [],
            ];
        }

        $primary = $windows[0];

        return [
            ...$primary,
            'is_available' => true,
            'window_count' => count($windows),
            'windows' => $windows,
        ];
    }

    /** Calculate Pradosha Kaal using first 1/5th of night and Trayodashi overlap logic. */
    private function calculatePradoshaKaal(
        CarbonImmutable $sunset,
        CarbonImmutable $nextSunrise,
        float $jdSunset,
        float $jdNextSunrise,
        string $tz
    ): array {
        $nightDurationJd = $jdNextSunrise - $jdSunset;
        $pradoshaEndJd = $jdSunset + ($nightDurationJd / 5.0);

        $trayodashiOverlaps = [];
        $cursor = $jdSunset + 1e-7;

        for ($i = 0; $i < 6 && $cursor < $pradoshaEndJd; $i++) {
            $interval = $this->getTithiIntervalAtJd($cursor);
            $tithiIndex = $interval['index'];
            $tithiPhase = $tithiIndex > 15 ? $tithiIndex - 15 : $tithiIndex;

            $overlapStartJd = max($interval['start_jd'], $jdSunset);
            $overlapEndJd = min($interval['end_jd'], $pradoshaEndJd);

            if ($tithiPhase === 13 && $overlapEndJd > $overlapStartJd) {
                $trayodashiOverlaps[] = [
                    'start_jd' => $overlapStartJd,
                    'end_jd' => $overlapEndJd,
                    'start' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($overlapStartJd, $tz)),
                    'end' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($overlapEndJd, $tz)),
                    'duration_minutes' => ($overlapEndJd - $overlapStartJd) * 1440.0,
                ];
            }

            $nextCursor = max($interval['end_jd'] + 1e-6, $cursor + 1e-5);
            if ($nextCursor <= $cursor) {
                break;
            }

            $cursor = $nextCursor;
        }

        $trayodashiDurationMinutes =
            array_reduce(
                $trayodashiOverlaps,
                static fn (float $carry, array $row): float => $carry + $row['duration_minutes'],
                0.0
            );

        $hasTrayodashiOverlap = $trayodashiOverlaps !== [];
        $basePradoshaDurationMinutes = ($pradoshaEndJd - $jdSunset) * 1440.0;
        $effectiveStartJd = $hasTrayodashiOverlap
            ? min(array_column($trayodashiOverlaps, 'start_jd'))
            : $jdSunset;
        $effectiveEndJd = $hasTrayodashiOverlap
            ? max(array_column($trayodashiOverlaps, 'end_jd'))
            : $pradoshaEndJd;
        $effectiveDurationMinutes = ($effectiveEndJd - $effectiveStartJd) * 1440.0;

        $baseStart = $sunset;
        $baseEnd = $this->sunService->jdToCarbonPublic($pradoshaEndJd, $tz);
        $effectiveStart = $this->sunService->jdToCarbonPublic($effectiveStartJd, $tz);
        $effectiveEnd = $this->sunService->jdToCarbonPublic($effectiveEndJd, $tz);

        return [
            'pradosha_start' => AstroCore::formatTime($effectiveStart),
            'pradosha_end' => AstroCore::formatTime($effectiveEnd),
            'pradosha_start_iso' => AstroCore::formatDateTime($effectiveStart),
            'pradosha_end_iso' => AstroCore::formatDateTime($effectiveEnd),
            'sunset' => AstroCore::formatTime($sunset),
            'duration_minutes' => $effectiveDurationMinutes,
            'base_pradosha_start' => AstroCore::formatTime($baseStart),
            'base_pradosha_end' => AstroCore::formatTime($baseEnd),
            'base_pradosha_start_iso' => AstroCore::formatDateTime($baseStart),
            'base_pradosha_end_iso' => AstroCore::formatDateTime($baseEnd),
            'base_duration_minutes' => $basePradoshaDurationMinutes,
            'is_trayodashi' => $hasTrayodashiOverlap,
            'is_auspicious' => $hasTrayodashiOverlap,
            'trayodashi_overlap_minutes' => $trayodashiDurationMinutes,
            'trayodashi_overlaps' => $trayodashiOverlaps,
            'significance' => $hasTrayodashiOverlap
                ? Localization::translate('String', 'Trayodashi overlaps Pradosha Kaal; this is Pradosh-observance eligible.')
                : Localization::translate('String', 'No Trayodashi overlap in Pradosha Kaal for this day.'),
        ];
    }

    private function calculateSpecialYogas(
        CarbonImmutable $date,
        float $jdStart,
        float $jdEnd,
        int $sunriseTithi,
        int $weekdayIndex,
        string $tz
    ): array {
        $nakshatraIntervals = $this->collectNakshatraIntervals($jdStart, $jdEnd);
        $tithiIntervals = $this->collectTithiIntervals($jdStart, $jdEnd);
        $sunNakshatraIntervals = $this->collectSunNakshatraIntervals($jdStart, $jdEnd);

        $payload = [
            'sarvartha_siddhi' => $this->buildSpecialYogaPayload(
                'Sarvartha Siddhi Yoga',
                'weekday_nakshatra',
                $this->matchWeekdayNakshatraWindows($nakshatraIntervals, $weekdayIndex, self::SARVARTHA_SIDDHI_RULES, $tz)
            ),
            'amrit_siddhi' => $this->buildSpecialYogaPayload(
                'Amrit Siddhi Yoga',
                'weekday_nakshatra',
                $this->matchWeekdayNakshatraWindows($nakshatraIntervals, $weekdayIndex, self::AMRIT_SIDDHI_RULES, $tz)
            ),
            'ravi_pushya' => $this->buildSpecialYogaPayload(
                'Ravi Pushya Yoga',
                'weekday_nakshatra',
                $weekdayIndex === 0 ? $this->filterNakshatraWindows($nakshatraIntervals, [7], $tz) : []
            ),
            'guru_pushya' => $this->buildSpecialYogaPayload(
                'Guru Pushya Yoga',
                'weekday_nakshatra',
                $weekdayIndex === 4 ? $this->filterNakshatraWindows($nakshatraIntervals, [7], $tz) : []
            ),
            'dwipushkar' => $this->buildSpecialYogaPayload(
                'Dwipushkar Yoga',
                'weekday_tithi_nakshatra',
                $this->matchPushkaraWindows(
                    $tithiIntervals,
                    $nakshatraIntervals,
                    $weekdayIndex,
                    self::PUSHKARA_TITHIS,
                    self::DWI_PUSHKARA_NAKSHATRAS,
                    $tz
                )
            ),
            'tripushkar' => $this->buildSpecialYogaPayload(
                'Tripushkar Yoga',
                'weekday_tithi_nakshatra',
                $this->matchPushkaraWindows(
                    $tithiIntervals,
                    $nakshatraIntervals,
                    $weekdayIndex,
                    self::PUSHKARA_TITHIS,
                    self::TRI_PUSHKARA_NAKSHATRAS,
                    $tz
                )
            ),
            'ganda_mula' => $this->buildSpecialYogaPayload(
                'Ganda Mula',
                'nakshatra',
                $this->filterNakshatraWindows($nakshatraIntervals, [0, 8, 9, 17, 18, 26], $tz)
            ),
            'vinchhudo' => $this->buildSpecialYogaPayload(
                'Vinchhudo',
                'moon_sign',
                $this->collectMoonSignWindows($jdStart, $jdEnd, 7, $tz)
            ),
            'ravi_yoga' => $this->buildSpecialYogaPayload(
                'Ravi Yoga',
                'sun_moon_nakshatra_distance',
                $this->matchRaviYogaWindows($nakshatraIntervals, $sunNakshatraIntervals, $tz)
            ),
            'aadal' => $this->buildSpecialYogaPayload(
                'Aadal Yoga',
                'sun_moon_nakshatra_count_with_abhijit',
                $this->matchAadalVidaalWindows($nakshatraIntervals, $sunNakshatraIntervals, self::AADAL_COUNTS, $tz, 'Aadal')
            ),
            'vidaal' => $this->buildSpecialYogaPayload(
                'Vidaal Yoga',
                'sun_moon_nakshatra_count_with_abhijit',
                $this->matchAadalVidaalWindows($nakshatraIntervals, $sunNakshatraIntervals, self::VIDAAL_COUNTS, $tz, 'Vidaal')
            ),
            'jwalamukhi' => $this->buildSpecialYogaPayload(
                'Jwalamukhi Yoga',
                'tithi_nakshatra',
                $this->matchJwalamukhiWindows($tithiIntervals, $nakshatraIntervals, $tz)
            ),
        ];

        $payload['summary'] = [
            'active_yoga_keys' => array_keys(array_filter(
                $payload,
                static fn (array $node): bool => (($node['is_present'] ?? false) === true)
            )),
            'sunrise_tithi' => Tithi::from($sunriseTithi)->getName(),
            'weekday' => Vara::from($weekdayIndex)->getName(),
            'date' => $date->toDateString(),
        ];

        return $payload;
    }

    private function calculateAnandadiYoga(float $jdStart, float $jdEnd, int $weekdayIndex, string $tz): array
    {
        $windows = [];
        $startIndex = self::ANANDADI_WEEKDAY_START_28_INDEX[$weekdayIndex] ?? 0;

        foreach ($this->collectNakshatra28Intervals($jdStart, $jdEnd) as $interval) {
            $yogaIndex = ((int) $interval['order_index'] - $startIndex + 28) % 28;
            $name = self::ANANDADI_YOGA_NAMES[$yogaIndex];
            $effect = self::ANANDADI_YOGA_EFFECTS[$yogaIndex];

            $windows[] = [
                'index' => $yogaIndex + 1,
                'name' => Localization::translate('String', $name),
                'name_key' => $name,
                'effect' => Localization::translate('String', $effect),
                'effect_key' => $effect,
                'nakshatra' => $interval['name'],
                'nakshatra_order_index' => $interval['order_index'],
                'start_jd' => $interval['start_jd'],
                'end_jd' => $interval['end_jd'],
                'start' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($interval['start_jd'], $tz)),
                'end' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($interval['end_jd'], $tz)),
            ];
        }

        return [
            'rule_system' => 'sripati_jyotisha_ratnamala_28_nakshatra',
            'is_complete_system' => true,
            'system_size' => 28,
            'weekday' => Vara::from($weekdayIndex)->getName(),
            'weekday_index' => $weekdayIndex,
            'weekday_start_nakshatra' => $this->nakshatra28Name($startIndex),
            'current' => $windows[0] ?? null,
            'window_count' => count($windows),
            'windows' => $windows,
        ];
    }

    private function calculateAmritadiYoga(float $jdStart, float $jdEnd, int $weekdayIndex, string $tz): array
    {
        $windows = [];

        foreach ($this->collectNakshatraIntervals($jdStart, $jdEnd) as $interval) {
            $classification = self::AMRITADI_YOGA_TABLE[(int) $interval['index']][$weekdayIndex] ?? 'Siddha';
            $windows[] = [
                'classification' => Localization::translate('String', $classification),
                'classification_key' => $classification,
                'is_auspicious' => in_array($classification, ['Amrita', 'Siddha'], true),
                'nakshatra' => $interval['name'],
                'nakshatra_index' => $interval['index'],
                'weekday' => Vara::from($weekdayIndex)->getName(),
                'start_jd' => $interval['start_jd'],
                'end_jd' => $interval['end_jd'],
                'start' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($interval['start_jd'], $tz)),
                'end' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($interval['end_jd'], $tz)),
            ];
        }

        return [
            'rule_system' => 'amritadi_yoga_27_nakshatra_7_weekday',
            'is_complete_system' => true,
            'system_size' => 189,
            'weekday' => Vara::from($weekdayIndex)->getName(),
            'weekday_index' => $weekdayIndex,
            'current' => $windows[0] ?? null,
            'window_count' => count($windows),
            'windows' => $windows,
            'classifications' => ['Amrita', 'Siddha', 'Marana', 'Prabalarishta'],
        ];
    }

    private function calculatePanchak(float $jdStart, float $jdEnd, string $tz): array
    {
        $windows = $this->collectMoonLongitudeRangeWindows($jdStart, $jdEnd, 300.0, 360.0, $tz);

        foreach ($windows as &$window) {
            $window['range'] = 'Dhanishta Pada 3 through Revati';
            $window['range_key'] = 'dhanishta_pada_3_to_revati';
        }
        unset($window);

        return [
            'rule_system' => 'moon_dhanishta_pada_3_to_revati',
            'is_complete_system' => true,
            'moon_longitude_start' => 300.0,
            'moon_longitude_end' => 360.0,
            'is_present' => $windows !== [],
            'window_count' => count($windows),
            'windows' => $windows,
        ];
    }

    private function calculateMaitreyaYoga(
        float $jdStart,
        float $jdEnd,
        int $weekdayIndex,
        array $lagnaTable,
        string $tz
    ): array {
        $windows = [];
        $rules = [
            [
                'weekday_index' => 2,
                'nakshatra_index' => 0,
                'lagna_sign_index' => 0,
                'combination' => 'Tuesday + Ashwini + Mesha Lagna',
            ],
            [
                'weekday_index' => 2,
                'nakshatra_index' => 16,
                'lagna_sign_index' => 7,
                'combination' => 'Tuesday + Anuradha + Vrischika Lagna',
            ],
        ];

        if ($weekdayIndex !== 2) {
            return [
                'rule_system' => 'weekday_nakshatra_lagna_debt_repayment',
                'is_complete_system' => true,
                'is_present' => false,
                'window_count' => 0,
                'windows' => [],
                'rules' => $rules,
            ];
        }

        $nakshatraIntervals = $this->collectNakshatraIntervals($jdStart, $jdEnd);
        foreach ($rules as $rule) {
            foreach ($nakshatraIntervals as $nakshatraInterval) {
                if ($nakshatraInterval['index'] !== $rule['nakshatra_index']) {
                    continue;
                }

                foreach ($lagnaTable as $lagnaWindow) {
                    if (($lagnaWindow['sign_index'] ?? null) !== $rule['lagna_sign_index']) {
                        continue;
                    }

                    $startJd = max((float) $nakshatraInterval['start_jd'], (float) $lagnaWindow['start_jd']);
                    $endJd = min((float) $nakshatraInterval['end_jd'], (float) $lagnaWindow['end_jd']);
                    if ($startJd >= $endJd) {
                        continue;
                    }

                    $windows[] = [
                        'combination' => $rule['combination'],
                        'weekday' => Vara::from($weekdayIndex)->getName(),
                        'nakshatra' => $nakshatraInterval['name'],
                        'lagna' => Rasi::from($rule['lagna_sign_index'])->getName(),
                        'start_jd' => $startJd,
                        'end_jd' => $endJd,
                        'start' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($startJd, $tz)),
                        'end' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($endJd, $tz)),
                    ];
                }
            }
        }

        return [
            'rule_system' => 'weekday_nakshatra_lagna_debt_repayment',
            'is_complete_system' => true,
            'is_present' => $windows !== [],
            'window_count' => count($windows),
            'windows' => $windows,
            'rules' => $rules,
        ];
    }

    private function calculateGajachchhayaYoga(float $jdStart, float $jdEnd, array $hinduMonth, string $tz): array
    {
        $variants = [
            'trayodashi_hasta_magha' => [
                'rule_system' => 'trayodashi_tithi_sun_hasta_moon_magha',
                'description' => 'Trayodashi tithi with Sun in Hasta and Moon in Magha',
                'requires_bhadrapada_pitru_paksha' => false,
                'tithi_phases' => [13],
                'require_krishna_paksha' => false,
            ],
            'amavasya_hasta_magha' => [
                'rule_system' => 'amavasya_tithi_sun_hasta_moon_magha',
                'description' => 'Amavasya tithi with Sun in Hasta and Moon in Magha',
                'requires_bhadrapada_pitru_paksha' => false,
                'tithi_phases' => [15],
                'require_krishna_paksha' => true,
            ],
            'pitru_paksha_bhadrapada_krishna_trayodashi' => [
                'rule_system' => 'bhadrapada_krishna_trayodashi_sun_hasta_moon_magha',
                'description' => 'Bhadrapada Krishna Trayodashi / Pitru Paksha with Sun in Hasta and Moon in Magha',
                'requires_bhadrapada_pitru_paksha' => true,
                'tithi_phases' => [13],
                'require_krishna_paksha' => true,
            ],
        ];

        $tithiIntervals = $this->collectTithiIntervals($jdStart, $jdEnd);
        $moonNakshatraIntervals = $this->collectNakshatraIntervals($jdStart, $jdEnd);
        $sunNakshatraIntervals = $this->collectSunNakshatraIntervals($jdStart, $jdEnd);
        $monthContext = [
            'amanta' => (string) ($hinduMonth['Month_Amanta_En'] ?? $hinduMonth['Month_Amanta'] ?? ''),
            'purnimanta' => (string) ($hinduMonth['Month_Purnimanta_En'] ?? $hinduMonth['Month_Purnimanta'] ?? ''),
        ];

        $variantPayload = [];
        foreach ($variants as $key => $variant) {
            $monthQualified = !$variant['requires_bhadrapada_pitru_paksha']
                || $monthContext['amanta'] === 'Bhadrapada'
                || $monthContext['purnimanta'] === 'Bhadrapada';

            $windows = $monthQualified
                ? $this->matchGajachchhayaWindows(
                    $tithiIntervals,
                    $moonNakshatraIntervals,
                    $sunNakshatraIntervals,
                    $variant['tithi_phases'],
                    $variant['require_krishna_paksha'],
                    $tz
                )
                : [];

            $variantPayload[$key] = [
                'rule_system' => $variant['rule_system'],
                'description' => $variant['description'],
                'requires_bhadrapada_pitru_paksha' => $variant['requires_bhadrapada_pitru_paksha'],
                'month_qualified' => $monthQualified,
                'is_present' => $windows !== [],
                'window_count' => count($windows),
                'windows' => $windows,
            ];
        }

        return [
            'is_complete_known_variant_set' => true,
            'variant_count' => count($variantPayload),
            'month_context' => $monthContext,
            'is_present' => $this->anyVariantPresent($variantPayload),
            'variants' => $variantPayload,
        ];
    }

    private function anyVariantPresent(array $variants): bool
    {
        foreach ($variants as $variant) {
            if (($variant['is_present'] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<int> $targetTithiPhases
     *
     * @return array<int, array<string, mixed>>
     */
    private function matchGajachchhayaWindows(
        array $tithiIntervals,
        array $moonNakshatraIntervals,
        array $sunNakshatraIntervals,
        array $targetTithiPhases,
        bool $requireKrishnaPaksha,
        string $tz
    ): array {
        $windows = [];

        foreach ($tithiIntervals as $tithiInterval) {
            $phaseIndex = (int) $tithiInterval['phase_index'];
            $tithiIndex = (int) $tithiInterval['index'];
            if (!in_array($phaseIndex, $targetTithiPhases, true)) {
                continue;
            }

            $isKrishna = $tithiIndex > 15;
            if ($requireKrishnaPaksha && !$isKrishna) {
                continue;
            }

            foreach ($moonNakshatraIntervals as $moonInterval) {
                if ((int) $moonInterval['index'] !== 9) {
                    continue;
                }

                foreach ($sunNakshatraIntervals as $sunInterval) {
                    if ((int) $sunInterval['index'] !== 12) {
                        continue;
                    }

                    $startJd = max((float) $tithiInterval['start_jd'], (float) $moonInterval['start_jd'], (float) $sunInterval['start_jd']);
                    $endJd = min((float) $tithiInterval['end_jd'], (float) $moonInterval['end_jd'], (float) $sunInterval['end_jd']);
                    if ($startJd >= $endJd) {
                        continue;
                    }

                    $windows[] = [
                        'start_jd' => $startJd,
                        'end_jd' => $endJd,
                        'start' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($startJd, $tz)),
                        'end' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($endJd, $tz)),
                        'tithi' => $tithiInterval['name'],
                        'paksha' => $isKrishna ? 'Krishna' : 'Shukla',
                        'moon_nakshatra' => $moonInterval['name'],
                        'sun_nakshatra' => $sunInterval['name'],
                    ];
                }
            }
        }

        return $windows;
    }

    private function calculateNakshatraShool(float $jdStart, float $jdEnd, string $tz): array
    {
        $windows = [];
        $nakshatraIntervals = $this->collectNakshatraIntervals($jdStart, $jdEnd);

        foreach ($nakshatraIntervals as $interval) {
            $direction = $this->nakshatraShoolDirection((int) $interval['index']);
            if ($direction === null) {
                continue;
            }

            $windows[] = [
                'direction' => Localization::translate('String', $direction),
                'direction_key' => $direction,
                'nakshatra' => $interval['name'],
                'start_jd' => $interval['start_jd'],
                'end_jd' => $interval['end_jd'],
                'start' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic((float) $interval['start_jd'], $tz)),
                'end' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic((float) $interval['end_jd'], $tz)),
            ];
        }

        return [
            'rule_system' => 'simple_travel_nakshatra_direction_table',
            'source_family' => 'popular_travel_muhurta_panchang_table',
            'is_complete_system' => true,
            'is_present' => $windows !== [],
            'current' => $windows[0] ?? null,
            'window_count' => count($windows),
            'windows' => $windows,
        ];
    }

    private function nakshatraShoolDirection(int $nakshatraIndex): ?string
    {
        foreach (self::NAKSHATRA_SHOOL_DIRECTIONS as $direction => $nakshatras) {
            if (in_array($nakshatraIndex, $nakshatras, true)) {
                return $direction;
            }
        }

        return null;
    }

    private function calculateDishaShool(int $weekdayIndex): array
    {
        $directionMap = [
            0 => 'West',
            1 => 'East',
            2 => 'North',
            3 => 'North',
            4 => 'South',
            5 => 'West',
            6 => 'East',
        ];
        $direction = $directionMap[$weekdayIndex] ?? 'None';
        $hasDishaShool = $direction !== 'None';

        return [
            'weekday' => Vara::from($weekdayIndex)->getName(),
            'direction_to_avoid' => Localization::translate('String', $direction),
            'direction_to_avoid_key' => $direction,
            'has_disha_shool' => $hasDishaShool,
            'is_safe_for_all_directions' => !$hasDishaShool,
            'guidance' => $hasDishaShool
                ? Localization::translate('String', 'Avoid travel in the indicated direction if possible.')
                : Localization::translate('String', 'No Disha Shool for this weekday.'),
            'remedies' => $hasDishaShool ? [
                Localization::translate('String', 'Consume or carry jaggery, curd, or barley before travel.'),
                Localization::translate('String', 'Begin travel in an auspicious muhurta if direction cannot be changed.'),
                Localization::translate('String', 'Offer prayer to Ganesha or Ishta Devata before departure.'),
            ] : [],
        ];
    }

    private function calculateRahuVaasa(int $weekdayIndex): array
    {
        $directionMap = [
            0 => 'South-West',
            1 => 'East',
            2 => 'North',
            3 => 'North-West',
            4 => 'South-East',
            5 => 'West',
            6 => 'East',
        ];
        $direction = $directionMap[$weekdayIndex] ?? 'None';

        return [
            'rule_system' => 'weekday_rahu_direction_7_day',
            'is_complete_system' => true,
            'weekday' => Vara::from($weekdayIndex)->getName(),
            'direction' => Localization::translate('String', $direction),
            'direction_key' => $direction,
            'guidance' => Localization::translate('String', 'Rahu is considered to reside in the indicated direction for this weekday.'),
        ];
    }

    private function calculateChandraVaasa(float $jdStart, float $jdEnd, string $tz): array
    {
        $windows = [];
        $padaIntervals = $this->collectNakshatraPadaIntervals($jdStart, $jdEnd);

        foreach ($padaIntervals as $interval) {
            $pada = (int) $interval['pada'];
            $abode = self::CHANDRA_VAASA_PADA_ABODES[$pada];
            $quality = self::CHANDRA_VAASA_PADA_QUALITY[$pada];

            $windows[] = [
                'abode' => Localization::translate('String', $abode),
                'abode_key' => $abode,
                'quality' => Localization::translate('String', $quality),
                'quality_key' => $quality,
                'nakshatra' => $interval['nakshatra'],
                'nakshatra_index' => $interval['nakshatra_index'],
                'pada' => $pada,
                'start_jd' => $interval['start_jd'],
                'end_jd' => $interval['end_jd'],
                'start' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic((float) $interval['start_jd'], $tz)),
                'end' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic((float) $interval['end_jd'], $tz)),
            ];
        }

        return [
            'rule_system' => 'nakshatra_pada_abode_4_part',
            'source_family' => 'modern_nivas_shool_panchang_style',
            'is_complete_system' => true,
            'current' => $windows[0] ?? null,
            'window_count' => count($windows),
            'windows' => $windows,
        ];
    }

    private function calculateShivaVaasa(int $tithiNumber, float $tithiEndJd, string $tz): array
    {
        $method1Index = self::SHIVA_VAASA_METHOD1[$tithiNumber] ?? 1;
        $method2Index = self::SHIVA_VAASA_METHOD2[($tithiNumber * 2 + 5) % 7] ?? 1;

        return [
            'tithi_number' => $tithiNumber,
            'tithi_name' => Tithi::from($tithiNumber)->getName(),
            'valid_until' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($tithiEndJd, $tz)),
            'valid_until_jd' => $tithiEndJd,
            'method_1' => [
                'index' => $method1Index,
                'state' => Localization::translate('String', self::SHIVA_VAASA_LABELS[$method1Index]),
                'state_key' => self::SHIVA_VAASA_LABELS[$method1Index],
                'effect' => Localization::translate('String', self::SHIVA_VAASA_EFFECTS[$method1Index]),
                'effect_key' => self::SHIVA_VAASA_EFFECTS[$method1Index],
            ],
            'method_2' => [
                'index' => $method2Index,
                'state' => Localization::translate('String', self::SHIVA_VAASA_LABELS[$method2Index]),
                'state_key' => self::SHIVA_VAASA_LABELS[$method2Index],
                'effect' => Localization::translate('String', self::SHIVA_VAASA_EFFECTS[$method2Index]),
                'effect_key' => self::SHIVA_VAASA_EFFECTS[$method2Index],
            ],
        ];
    }

    private function calculateAgniVaasa(int $tithiNumber, int $weekdayIndex, float $tithiEndJd, string $tz): array
    {
        $index = [1, 2, 3, 1][($tithiNumber + 1 + ($weekdayIndex + 1)) % 4];

        return [
            'tithi_number' => $tithiNumber,
            'tithi_name' => Tithi::from($tithiNumber)->getName(),
            'weekday' => Vara::from($weekdayIndex)->getName(),
            'index' => $index,
            'state' => Localization::translate('String', self::AGNI_VAASA_LABELS[$index]),
            'state_key' => self::AGNI_VAASA_LABELS[$index],
            'effect' => Localization::translate('String', self::AGNI_VAASA_EFFECTS[$index]),
            'effect_key' => self::AGNI_VAASA_EFFECTS[$index],
            'valid_until' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($tithiEndJd, $tz)),
            'valid_until_jd' => $tithiEndJd,
        ];
    }

    private function calculateYoginiVaasa(int $tithiNumber): array
    {
        $directionIndex = self::YOGINI_VAASA_MAP[$tithiNumber - 1] ?? 0;

        return [
            'tithi_number' => $tithiNumber,
            'tithi_name' => Tithi::from($tithiNumber)->getName(),
            'direction_index' => $directionIndex,
            'direction' => Localization::translate('String', self::DIRECTION_LABELS[$directionIndex]),
            'direction_key' => self::DIRECTION_LABELS[$directionIndex],
        ];
    }

    private function buildTransitionSignals(
        float $jdStart,
        float $jdEnd,
        float $sunLongitude,
        float $moonLongitude,
        int $tithiNumber,
        int $nakshatraIndex,
        int $yogaIndex,
        int $karanaIndex,
        string $tz,
        ?int $sankrantiRashi
    ): array {
        $tithiIntervals = $this->collectTithiIntervals($jdStart, $jdEnd);
        $nakshatraIntervals = $this->collectNakshatraIntervals($jdStart, $jdEnd);

        return [
            'tithi' => [
                'current' => Tithi::from($tithiNumber)->getName(),
                'windows' => array_map(fn (array $interval): array => $this->formatTransitionWindow($interval, 'tithi', $tz), $tithiIntervals),
            ],
            'nakshatra' => [
                'current' => Nakshatra::from($nakshatraIndex % 27)->getName(),
                'windows' => array_map(fn (array $interval): array => $this->formatTransitionWindow($interval, 'nakshatra', $tz), $nakshatraIntervals),
            ],
            'yoga' => [
                'current' => Localization::translate('Yoga', max(0, $yogaIndex - 1)),
            ],
            'karana' => [
                'current' => Localization::translate('Karana', $this->normalizeKaranaLocalizationIndex($karanaIndex)),
            ],
            'moon_sign' => [
                'current' => Rasi::from(AstroCore::getSign($moonLongitude))->getName(),
                'transitions' => $this->collectMoonSignTransitions($jdStart, $jdEnd, $tz),
            ],
            'sun_sign' => [
                'current' => Rasi::from(AstroCore::getSign($sunLongitude))->getName(),
                'sankranti_today' => $sankrantiRashi !== null,
                'next_sign' => $sankrantiRashi !== null ? Rasi::from($sankrantiRashi)->getName() : null,
            ],
        ];
    }

    private function buildEkadashiObservance(
        int $tithiNumber,
        float $tithiStartJd,
        float $tithiEndJd,
        float $sunriseJd,
        float $sunsetJd,
        float $nextSunriseJd,
        string $tz,
        float $lat,
        float $lon
    ): ?array {
        $phaseTithi = (($tithiNumber - 1) % 15) + 1;
        if (!in_array($phaseTithi, [11, 12], true)) {
            return null;
        }

        $kalaEngine = new KalaNirnayaEngine($lat, $lon);
        $report = $kalaEngine->generateKalaNirnayaReport(
            $tithiNumber,
            $tithiStartJd,
            $tithiEndJd,
            $tithiStartJd,
            $sunriseJd,
            $sunsetJd,
            $nextSunriseJd
        );

        $payload = [
            'phase_tithi_number' => $phaseTithi,
            'phase_tithi_name' => Localization::translate('String', $phaseTithi === 11 ? 'Ekadashi' : 'Dwadashi'),
            'phase_tithi_name_key' => $phaseTithi === 11 ? 'Ekadashi' : 'Dwadashi',
            'viddha_tithi_analysis' => $report['viddha_tithi_analysis'] ?? null,
            'ekadashi_smarta' => $report['ekadashi_smarta'] ?? null,
            'ekadashi_vaishnava' => $report['ekadashi_vaishnava'] ?? null,
        ];

        if ($phaseTithi === 11) {
            $dvadashiEndAngle = ($tithiNumber + 1) * 12.0;
            $dvadashiEndJd = $this->findAngleCrossing(
                $tithiEndJd + 0.000001,
                $dvadashiEndAngle,
                1,
                fn (float $jd): float => $this->getMoonSunAngle($jd)
            );
            $payload['parana'] = $this->buildParanaPayload($tithiEndJd, $dvadashiEndJd, $nextSunriseJd, $tz, true);
        } else {
            $payload['parana'] = $this->buildParanaPayload($tithiStartJd, $tithiEndJd, $sunriseJd, $tz, false);
        }

        $payload = $this->localizeEkadashiObservancePayload($payload);

        return $payload;
    }

    private function buildParanaPayload(
        float $dvadashiStartJd,
        float $dvadashiEndJd,
        float $sunriseJd,
        string $tz,
        bool $startsTomorrow
    ): array {
        $hariVasaraEndJd = $dvadashiStartJd + (($dvadashiEndJd - $dvadashiStartJd) / 4.0);
        $paranaStartJd = max($sunriseJd, $hariVasaraEndJd);
        $available = $paranaStartJd < $dvadashiEndJd;

        return [
            'hari_vasara_start' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($dvadashiStartJd, $tz)),
            'hari_vasara_end' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($hariVasaraEndJd, $tz)),
            'hari_vasara_start_jd' => $dvadashiStartJd,
            'hari_vasara_end_jd' => $hariVasaraEndJd,
            'parana_day' => Localization::translate('String', $startsTomorrow ? 'Next Day' : 'Today'),
            'parana_day_key' => $startsTomorrow ? 'next_day' : 'today',
            'parana_available' => $available,
            'parana_start' => $available ? AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($paranaStartJd, $tz)) : null,
            'parana_end' => $available ? AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($dvadashiEndJd, $tz)) : null,
            'parana_start_jd' => $available ? $paranaStartJd : null,
            'parana_end_jd' => $available ? $dvadashiEndJd : null,
        ];
    }

    private function localizeEkadashiObservancePayload(array $payload): array
    {
        if (isset($payload['viddha_tithi_analysis']) && is_array($payload['viddha_tithi_analysis'])) {
            $payload['viddha_tithi_analysis']['status_label'] = Localization::translate(
                'String',
                (string) ($payload['viddha_tithi_analysis']['status'] ?? '')
            );
            $payload['viddha_tithi_analysis']['tithi_name_label'] = Localization::translate(
                'String',
                (string) ($payload['viddha_tithi_analysis']['tithi_name'] ?? '')
            );
        }

        foreach (['ekadashi_smarta', 'ekadashi_vaishnava'] as $key) {
            if (!isset($payload[$key]) || !is_array($payload[$key])) {
                continue;
            }

            $payload[$key]['tradition_label'] = Localization::translate('String', (string) ($payload[$key]['tradition'] ?? ''));
            $payload[$key]['status_label'] = Localization::translate('String', (string) ($payload[$key]['status'] ?? ''));
            $payload[$key]['fasting_day_label'] = Localization::translate('String', (string) ($payload[$key]['fasting_day'] ?? ''));
        }

        return $payload;
    }

    private function collectNakshatraIntervals(float $jdStart, float $jdEnd): array
    {
        $intervals = [];
        $cursor = $jdStart;

        for ($guard = 0; $guard < 4 && $cursor < $jdEnd; $guard++) {
            $moonLongitude = $this->getMoonLongitude($cursor + 0.000001);
            $nakshatraIndex = ((int) floor($moonLongitude / (360.0 / 27.0))) % 27;
            $targetAngle = ($nakshatraIndex + 1) * (360.0 / 27.0);
            $endJd = $this->findAngleCrossing(
                $cursor,
                $targetAngle,
                1,
                fn (float $jd): float => $this->getMoonLongitude($jd)
            );

            $intervals[] = [
                'index' => $nakshatraIndex,
                'name' => Nakshatra::from($nakshatraIndex)->getName(),
                'start_jd' => $cursor,
                'end_jd' => min($endJd, $jdEnd),
            ];

            $cursor = min($endJd, $jdEnd) + 0.000001;
        }

        return $intervals;
    }

    private function collectNakshatra28Intervals(float $jdStart, float $jdEnd): array
    {
        $intervals = [];
        $cursor = $jdStart;

        for ($guard = 0; $guard < 5 && $cursor < $jdEnd; $guard++) {
            $moonLongitude = $this->getMoonLongitude($cursor + 0.000001);
            $orderIndex = $this->nakshatra28IndexFromLongitude($moonLongitude);
            $targetAngle = $this->nextNakshatra28BoundaryAfter($moonLongitude);
            $endJd = $this->findAngleCrossing(
                $cursor,
                $targetAngle,
                1,
                fn (float $jd): float => $this->getMoonLongitude($jd)
            );
            $segmentEnd = min($endJd, $jdEnd);

            if ($segmentEnd <= $cursor) {
                break;
            }

            $intervals[] = [
                'order_index' => $orderIndex,
                'name' => $this->nakshatra28Name($orderIndex),
                'start_jd' => $cursor,
                'end_jd' => $segmentEnd,
            ];

            $cursor = $segmentEnd + 0.000001;
        }

        return $intervals;
    }

    private function collectNakshatraPadaIntervals(float $jdStart, float $jdEnd): array
    {
        $intervals = [];
        $cursor = $jdStart;

        for ($guard = 0; $guard < 12 && $cursor < $jdEnd; $guard++) {
            $moonLongitude = AstroCore::normalize($this->getMoonLongitude($cursor + 0.000001));
            $nakshatraIndex = (int) floor($moonLongitude / 13.333333333333334);
            $nakshatraStart = $nakshatraIndex * 13.333333333333334;
            $pada = (int) floor(($moonLongitude - $nakshatraStart) / 3.3333333333333335) + 1;
            $pada = max(1, min(4, $pada));
            $targetAngle = $nakshatraStart + ($pada * 3.3333333333333335);
            if ($targetAngle >= 360.0) {
                $targetAngle = 360.0;
            }

            $endJd = $this->findAngleCrossing(
                $cursor,
                $targetAngle,
                1,
                fn (float $jd): float => $this->getMoonLongitude($jd)
            );
            $segmentEnd = min($endJd, $jdEnd);

            if ($segmentEnd <= $cursor) {
                break;
            }

            $intervals[] = [
                'nakshatra_index' => $nakshatraIndex,
                'nakshatra' => Nakshatra::from($nakshatraIndex)->getName(),
                'pada' => $pada,
                'start_jd' => $cursor,
                'end_jd' => $segmentEnd,
            ];

            $cursor = $segmentEnd + 0.000001;
        }

        return $intervals;
    }

    private function nakshatra28IndexFromLongitude(float $longitude): int
    {
        $normalized = AstroCore::normalize($longitude);
        $boundaries = $this->nakshatra28Boundaries();

        for ($i = 0; $i < 28; $i++) {
            $start = $boundaries[$i];
            $end = $boundaries[$i + 1];
            if ($normalized >= $start && $normalized < $end) {
                return $i;
            }
        }

        return 27;
    }

    private function nextNakshatra28BoundaryAfter(float $longitude): float
    {
        $normalized = AstroCore::normalize($longitude);
        foreach ($this->nakshatra28Boundaries() as $boundary) {
            if ($boundary > $normalized + 1e-9) {
                return $boundary;
            }
        }

        return 360.0;
    }

    /** @return list<float> */
    private function nakshatra28Boundaries(): array
    {
        return [
            0.0,
            13.333333333333334,
            26.666666666666668,
            40.0,
            53.333333333333336,
            66.66666666666667,
            80.0,
            93.33333333333334,
            106.66666666666667,
            120.0,
            133.33333333333334,
            146.66666666666669,
            160.0,
            173.33333333333334,
            186.66666666666669,
            200.0,
            213.33333333333334,
            226.66666666666669,
            240.0,
            253.33333333333334,
            266.6666666666667,
            276.6666666666667,
            280.8888888888889,
            293.33333333333337,
            306.6666666666667,
            320.0,
            333.33333333333337,
            346.6666666666667,
            360.0,
        ];
    }

    private function nakshatra28Name(int $orderIndex): string
    {
        return match ($orderIndex) {
            21 => 'Abhijit',
            22 => Nakshatra::Shravana->getName(),
            23 => Nakshatra::Dhanishta->getName(),
            24 => Nakshatra::Shatabhisha->getName(),
            25 => Nakshatra::PurvaBhadrapada->getName(),
            26 => Nakshatra::UttaraBhadrapada->getName(),
            27 => Nakshatra::Revati->getName(),
            default => Nakshatra::from($orderIndex)->getName(),
        };
    }

    private function collectTithiIntervals(float $jdStart, float $jdEnd): array
    {
        $intervals = [];
        $cursor = $jdStart;

        for ($guard = 0; $guard < 4 && $cursor < $jdEnd; $guard++) {
            $interval = $this->getTithiIntervalAtJd($cursor + 0.000001);
            $index = $interval['index'];
            $intervals[] = [
                'index' => $index,
                'phase_index' => (($index - 1) % 15) + 1,
                'name' => Tithi::from($index)->getName(),
                'start_jd' => max($interval['start_jd'], $cursor),
                'end_jd' => min($interval['end_jd'], $jdEnd),
            ];

            $cursor = min($interval['end_jd'], $jdEnd) + 0.000001;
        }

        return $intervals;
    }

    private function collectSunNakshatraIntervals(float $jdStart, float $jdEnd): array
    {
        $intervals = [];
        $cursor = $jdStart;

        for ($guard = 0; $guard < 2 && $cursor < $jdEnd; $guard++) {
            $sunLongitude = $this->getSunLongitude($cursor + 0.000001);
            $nakshatraIndex = ((int) floor($sunLongitude / (360.0 / 27.0))) % 27;
            $targetAngle = ($nakshatraIndex + 1) * (360.0 / 27.0);
            $endJd = $this->findAngleCrossing(
                $cursor,
                $targetAngle,
                1,
                fn (float $jd): float => $this->getSunLongitude($jd)
            );

            $intervals[] = [
                'index' => $nakshatraIndex,
                'name' => Nakshatra::from($nakshatraIndex)->getName(),
                'start_jd' => $cursor,
                'end_jd' => min($endJd, $jdEnd),
            ];

            $cursor = min($endJd, $jdEnd) + 0.000001;
        }

        return $intervals;
    }

    private function matchWeekdayNakshatraWindows(array $nakshatraIntervals, int $weekdayIndex, array $rules, string $tz): array
    {
        return $this->filterNakshatraWindows($nakshatraIntervals, $rules[$weekdayIndex] ?? [], $tz);
    }

    private function filterNakshatraWindows(array $nakshatraIntervals, array $targetIndexes, string $tz): array
    {
        $windows = [];
        foreach ($nakshatraIntervals as $interval) {
            if (!in_array($interval['index'], $targetIndexes, true)) {
                continue;
            }

            $windows[] = [
                'start_jd' => $interval['start_jd'],
                'end_jd' => $interval['end_jd'],
                'start' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($interval['start_jd'], $tz)),
                'end' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($interval['end_jd'], $tz)),
                'nakshatra' => $interval['name'],
            ];
        }

        return $windows;
    }

    private function matchPushkaraWindows(
        array $tithiIntervals,
        array $nakshatraIntervals,
        int $weekdayIndex,
        array $targetTithis,
        array $targetNakshatras,
        string $tz
    ): array {
        if (!in_array($weekdayIndex, self::PUSHKARA_WEEKDAYS, true)) {
            return [];
        }

        $windows = [];
        foreach ($tithiIntervals as $tithiInterval) {
            if (!in_array($tithiInterval['phase_index'], $targetTithis, true)) {
                continue;
            }

            foreach ($nakshatraIntervals as $nakshatraInterval) {
                if (!in_array($nakshatraInterval['index'], $targetNakshatras, true)) {
                    continue;
                }

                $startJd = max($tithiInterval['start_jd'], $nakshatraInterval['start_jd']);
                $endJd = min($tithiInterval['end_jd'], $nakshatraInterval['end_jd']);
                if ($startJd >= $endJd) {
                    continue;
                }

                $windows[] = [
                    'start_jd' => $startJd,
                    'end_jd' => $endJd,
                    'start' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($startJd, $tz)),
                    'end' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($endJd, $tz)),
                    'tithi' => $tithiInterval['name'],
                    'nakshatra' => $nakshatraInterval['name'],
                ];
            }
        }

        return $windows;
    }

    private function matchRaviYogaWindows(array $moonNakshatraIntervals, array $sunNakshatraIntervals, string $tz): array
    {
        $windows = [];
        foreach ($moonNakshatraIntervals as $moonInterval) {
            foreach ($sunNakshatraIntervals as $sunInterval) {
                $startJd = max($moonInterval['start_jd'], $sunInterval['start_jd']);
                $endJd = min($moonInterval['end_jd'], $sunInterval['end_jd']);
                if ($startJd >= $endJd) {
                    continue;
                }

                $distance = (($moonInterval['index'] - $sunInterval['index'] + 27) % 27) + 1;
                if (!in_array($distance, self::RAVI_YOGA_DISTANCES, true)) {
                    continue;
                }

                $windows[] = [
                    'start_jd' => $startJd,
                    'end_jd' => $endJd,
                    'start' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($startJd, $tz)),
                    'end' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($endJd, $tz)),
                    'nakshatra' => $moonInterval['name'],
                    'sun_nakshatra' => $sunInterval['name'],
                    'distance_from_sun_nakshatra' => $distance,
                ];
            }
        }

        return $windows;
    }

    private function matchAadalVidaalWindows(
        array $moonNakshatraIntervals,
        array $sunNakshatraIntervals,
        array $targetCounts,
        string $tz,
        string $label
    ): array {
        $windows = [];
        foreach ($moonNakshatraIntervals as $moonInterval) {
            foreach ($sunNakshatraIntervals as $sunInterval) {
                $startJd = max($moonInterval['start_jd'], $sunInterval['start_jd']);
                $endJd = min($moonInterval['end_jd'], $sunInterval['end_jd']);
                if ($startJd >= $endJd) {
                    continue;
                }

                $count = $this->countNakshatrasWithAbhijit($sunInterval['index'], $moonInterval['index']);
                if (!in_array($count, $targetCounts, true)) {
                    continue;
                }

                $windows[] = [
                    'start_jd' => $startJd,
                    'end_jd' => $endJd,
                    'start' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($startJd, $tz)),
                    'end' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($endJd, $tz)),
                    'nakshatra' => $moonInterval['name'],
                    'sun_nakshatra' => $sunInterval['name'],
                    'count_with_abhijit' => $count,
                    'classification' => $label,
                ];
            }
        }

        return $windows;
    }

    private function matchJwalamukhiWindows(array $tithiIntervals, array $nakshatraIntervals, string $tz): array
    {
        $windows = [];
        foreach ($tithiIntervals as $tithiInterval) {
            $targetNakshatras = self::JWALAMUKHI_RULES[(int) $tithiInterval['phase_index']] ?? null;
            if ($targetNakshatras === null) {
                continue;
            }

            foreach ($nakshatraIntervals as $nakshatraInterval) {
                if (!in_array($nakshatraInterval['index'], $targetNakshatras, true)) {
                    continue;
                }

                $startJd = max($tithiInterval['start_jd'], $nakshatraInterval['start_jd']);
                $endJd = min($tithiInterval['end_jd'], $nakshatraInterval['end_jd']);
                if ($startJd >= $endJd) {
                    continue;
                }

                $windows[] = [
                    'start_jd' => $startJd,
                    'end_jd' => $endJd,
                    'start' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($startJd, $tz)),
                    'end' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($endJd, $tz)),
                    'tithi' => $tithiInterval['name'],
                    'nakshatra' => $nakshatraInterval['name'],
                ];
            }
        }

        return $windows;
    }

    private function buildSpecialYogaPayload(string $name, string $basis, array $windows): array
    {
        return [
            'name' => Localization::translate('String', $name),
            'name_key' => $name,
            'basis' => $basis,
            'is_present' => $windows !== [],
            'window_count' => count($windows),
            'windows' => $windows,
        ];
    }

    private function collectMoonSignTransitions(float $jdStart, float $jdEnd, string $tz): array
    {
        $currentMoonSign = AstroCore::getSign($this->getMoonLongitude($jdStart + 0.000001));
        $targetAngle = ($currentMoonSign + 1) * 30.0;
        $transitionJd = $this->findAngleCrossing(
            $jdStart,
            $targetAngle,
            1,
            fn (float $jd): float => $this->getMoonLongitude($jd)
        );

        if ($transitionJd >= $jdEnd) {
            return [];
        }

        return [[
            'from' => Rasi::from($currentMoonSign)->getName(),
            'to' => Rasi::from(($currentMoonSign + 1) % 12)->getName(),
            'at_jd' => $transitionJd,
            'at_iso' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($transitionJd, $tz)),
        ]];
    }

    private function collectMoonSignWindows(float $jdStart, float $jdEnd, int $targetSignIndex, string $tz): array
    {
        $windows = [];
        $cursor = $jdStart;

        for ($guard = 0; $guard < 3 && $cursor < $jdEnd; $guard++) {
            $moonLongitude = $this->getMoonLongitude($cursor + 0.000001);
            $signIndex = AstroCore::getSign($moonLongitude);
            $targetAngle = (($signIndex + 1) % 12) * 30.0;
            $endJd = $this->findAngleCrossing(
                $cursor,
                $targetAngle,
                1,
                fn (float $jd): float => $this->getMoonLongitude($jd)
            );

            $segmentEnd = min($endJd, $jdEnd);
            if ($signIndex === $targetSignIndex && $segmentEnd > $cursor) {
                $windows[] = [
                    'start_jd' => $cursor,
                    'end_jd' => $segmentEnd,
                    'start' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($cursor, $tz)),
                    'end' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($segmentEnd, $tz)),
                    'moon_sign' => Rasi::from($signIndex)->getName(),
                ];
            }

            $cursor = $segmentEnd + 0.000001;
        }

        return $windows;
    }

    private function collectMoonLongitudeRangeWindows(
        float $jdStart,
        float $jdEnd,
        float $rangeStart,
        float $rangeEnd,
        string $tz
    ): array {
        $windows = [];
        $cursor = $jdStart;

        for ($guard = 0; $guard < 4 && $cursor < $jdEnd; $guard++) {
            $moonLongitude = AstroCore::normalize($this->getMoonLongitude($cursor + 0.000001));

            if ($moonLongitude >= $rangeStart && $moonLongitude < $rangeEnd) {
                $endJd = $this->findAngleCrossing(
                    $cursor,
                    $rangeEnd,
                    1,
                    fn (float $jd): float => $this->getMoonLongitude($jd)
                );
                $segmentEnd = min($endJd, $jdEnd);
                $windows[] = [
                    'start_jd' => $cursor,
                    'end_jd' => $segmentEnd,
                    'start' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($cursor, $tz)),
                    'end' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic($segmentEnd, $tz)),
                ];
                $cursor = $segmentEnd + 0.000001;

                continue;
            }

            $target = $moonLongitude < $rangeStart ? $rangeStart : 360.0;
            $nextJd = $this->findAngleCrossing(
                $cursor,
                $target,
                1,
                fn (float $jd): float => $this->getMoonLongitude($jd)
            );
            if ($nextJd <= $cursor || $nextJd >= $jdEnd) {
                break;
            }

            $cursor = $nextJd + 0.000001;
        }

        return $windows;
    }

    private function formatTransitionWindow(array $interval, string $type, string $tz): array
    {
        return [
            'name' => (string) ($interval['name'] ?? ''),
            'type' => $type,
            'start_jd' => $interval['start_jd'],
            'end_jd' => $interval['end_jd'],
            'start_iso' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic((float) $interval['start_jd'], $tz)),
            'end_iso' => AstroCore::formatDateTime($this->sunService->jdToCarbonPublic((float) $interval['end_jd'], $tz)),
        ];
    }

    private function normalizeKaranaLocalizationIndex(int $karanaIndex): int
    {
        return match ($karanaIndex) {
            1 => 0,
            2 => 1,
            3 => 2,
            4 => 3,
            5 => 4,
            6 => 5,
            7 => 6,
            57 => 7,
            58 => 8,
            59 => 9,
            60 => 10,
            default => 0,
        };
    }

    /**
     * Return precise tithi interval (start/end JD) containing given JD.
     *
     * @return array{index:int,start_jd:float,end_jd:float}
     */
    private function getTithiIntervalAtJd(float $jd): array
    {
        $angle = $this->getMoonSunAngle($jd);
        $tithiIndex = (int) floor($angle / 12.0) + 1; // 1..30

        $startAngle = (($tithiIndex - 1) % 30) * 12.0;
        $endAngle = ($tithiIndex % 30) * 12.0;

        $startJd = $this->findAngleCrossing($jd, $startAngle, -1, fn (float $probe): float => $this->getMoonSunAngle($probe));
        $endJd = $this->findAngleCrossing($jd, $endAngle, 1, fn (float $probe): float => $this->getMoonSunAngle($probe));

        return [
            'index' => $tithiIndex,
            'start_jd' => $startJd,
            'end_jd' => $endJd,
        ];
    }

    private function calcBodyAtJd(float $jd, int $planet, int $flags): float
    {
        $xx = $this->sweph->getFFI()->new('double[6]');
        $serr = $this->sweph->getFFI()->new('char[256]');
        $this->sweph->swe_calc_ut($jd, $planet, $flags, $xx, $serr);
        return AstroCore::normalize($xx[0]);
    }

    private function findAngleCrossing(float $jd0, float $targetAngle, int $direction, callable $angleFn): float
    {
        $step = 0.25 * $direction;
        // 0.25 day step with 100 steps only scans 25 days, which is insufficient
        // for month-boundary searches (e.g. next Amavasya ~29.5 days away).
        $maxSteps = 500;
        $jd1 = $jd0;
        $f0 = $this->signedDiff($angleFn($jd1), $targetAngle);
        $jd2 = $jd1;
        $f1 = $f0;

        for ($i = 0; $i < $maxSteps; $i++) {
            $jd2 = $jd1 + $step;
            $f1 = $this->signedDiff($angleFn($jd2), $targetAngle);
            if ($f0 === 0.0) {
                return $jd1;
            }

            if ($f0 === 0.0 || (abs($f1 - $f0) < 180.0 && (($f0 < 0 && $f1 > 0) || ($f0 > 0 && $f1 < 0)))) {
                break;
            }

            $jd1 = $jd2;
            $f0 = $f1;
        }

        $low = min($jd1, $jd2);
        $high = max($jd1, $jd2);
        $fLow = $this->signedDiff($angleFn($low), $targetAngle);
        $fHigh = $this->signedDiff($angleFn($high), $targetAngle);

        for ($i = 0; $i < 80; $i++) {
            $mid = ($low + $high) / 2.0;
            $fMid = $this->signedDiff($angleFn($mid), $targetAngle);
            if ($fMid === 0.0) {
                return $mid;
            }

            if (($fLow < 0 && $fMid > 0) || ($fLow > 0 && $fMid < 0)) {
                $high = $mid;
                $fHigh = $fMid;
            } else {
                $low = $mid;
                $fLow = $fMid;
            }
        }

        return ($low + $high) / 2.0;
    }

    private function signedDiff(float $angle, float $target): float
    {
        $diff = AstroCore::normalize($angle - $target);
        if ($diff > 180.0) {
            $diff -= 360.0;
        }

        return $diff;
    }

    private function countNakshatrasWithAbhijit(int $sunNakshatraIndex, int $moonNakshatraIndex): int
    {
        $startIndex = array_search($sunNakshatraIndex, self::ABHIJIT_ORDER, true);
        $endIndex = array_search($moonNakshatraIndex, self::ABHIJIT_ORDER, true);
        if ($startIndex === false || $endIndex === false) {
            throw new RuntimeException('Invalid nakshatra index for Abhijit count.');
        }

        if ($startIndex <= $endIndex) {
            $count = ($endIndex - $startIndex + 1) % count(self::ABHIJIT_ORDER);
            if ($count === 0) {
                return count(self::ABHIJIT_ORDER);
            }

            return $count;
        }

        return count(self::ABHIJIT_ORDER) - $startIndex + $endIndex + 1;
    }

    private function getSunMoonLongitudes(array $birth): array
    {
        $jd = $this->toJulianDayFromCarbon(
            CarbonImmutable::create(
                (int) $birth['year'],
                (int) $birth['month'],
                (int) $birth['day'],
                (int) $birth['hour'],
                (int) $birth['minute'],
                (int) $birth['second'],
                $birth['timezone']
            ),
            $birth['timezone']
        );

        $flags = SwissEphFFI::SEFLG_SWIEPH | SwissEphFFI::SEFLG_SIDEREAL;

        $sun = $this->calcBody($jd, SwissEphFFI::SE_SUN, $flags);
        $moon = $this->calcBody($jd, SwissEphFFI::SE_MOON, $flags);

        return [
            'Sun' => $sun,
            'Moon' => $moon,
        ];
    }

    private function calcBody(float $jd, int $planet, int $flags): float
    {
        $xx = $this->sweph->getFFI()->new('double[6]');
        $serr = $this->sweph->getFFI()->new('char[256]');

        $this->sweph->swe_calc_ut($jd, $planet, $flags, $xx, $serr);

        return $this->normalize($xx[0]);
    }

    private function calculateIshtkaal(CarbonImmutable $sunrise, array $birth, string $tz): string
    {
        $dt = CarbonImmutable::create(
            (int) $birth['year'],
            (int) $birth['month'],
            (int) $birth['day'],
            (int) $birth['hour'],
            (int) $birth['minute'],
            (int) $birth['second'],
            $tz
        );

        $relSunrise = $sunrise;
        if ($dt->lessThan($sunrise)) {
            $relSunrise = $sunrise->subDay();
        }

        $sec = (int) abs($dt->diffInSeconds($relSunrise, false));

        $gh = (int) floor($sec / 1440);
        $pl = (int) floor(($sec % 1440) / 24);
        $vp = (int) floor((($sec % 1440) % 24) / 0.4);

        return sprintf('%02d:%02d:%02d', $gh, $pl, $vp);
    }

    private function normalize(float $value): float
    {
        return AstroCore::normalize($value);
    }

    /**
     * Calculate True Hindu Month using exact solar transits (Sankranti)
     * between exact Sun-Moon conjunctions (Amavasya).
     *
     * Correct algorithm per Calendrical Calculations (Reingold & Dershowitz):
     * 1. The Amanta month runs from one Amavasya (new moon) to the next.
     * 2. The month NAME is determined by which solar Sankranti (sun sign
     *    crossing) occurs DURING the lunar month — i.e., the sun's sign
     *    at the ENDING Amavasya (next new moon).
     * 3. Adhika Maas: NO Sankranti occurs between two consecutive Amavasyas
     *    (sun stays in the same sign) → the second month is a leap month.
     * 4. Kshaya Maas: TWO Sankrantis occur between two consecutive Amavasyas
     *    (sun jumps 2+ signs) → the intermediate month is skipped entirely.
     *
     * Lossless algorithm as per Siddhantic tradition.
     */
    private function getTrueHinduMonth(float $jd): array
    {
        // Find the Amavasya that STARTS the current lunar month (most recent new moon)
        $startAmavasya = $this->findAngleCrossing($jd, 0.0, -1, fn (float $t): float => $this->getMoonSunAngle($t));
        // Find the Amavasya that ENDS the current lunar month (next new moon)
        // Start from slightly after the start Amavasya to ensure we find the NEXT one
        $endAmavasya = $this->findAngleCrossing($startAmavasya + 1.0, 0.0, 1, fn (float $t): float => $this->getMoonSunAngle($t));

        // Sun's sidereal longitude at both Amavasyas
        $sunAtStart = $this->getSunLongitude($startAmavasya);
        $sunAtEnd = $this->getSunLongitude($endAmavasya);

        $signAtStart = (int) floor($sunAtStart / 30.0) % 12;
        $signAtEnd = (int) floor($sunAtEnd / 30.0) % 12;

        // Count sign crossings between the two Amavasyas
        // The sun moves ~1°/day, so in ~29.5 days it moves ~29.5° ≈ 1 sign.
        // Normal month: 1 crossing. Adhika: 0 crossings. Kshaya: 2+ crossings.
        $signCrossings = ($signAtEnd - $signAtStart + 12) % 12;

        $isAdhika = ($signCrossings === 0);
        $isKshaya = ($signCrossings >= 2);

        // Month name = sun's sign at the ENDING Amavasya
        // (the sign the sun entered during this lunar month)
        $amantaIdx = $signAtEnd;

        // For Adhika: the sun didn't enter a new sign, so this month repeats
        // the previous month's name. The Adhika month takes the name of the
        // sign the sun WILL enter (the NEXT sign after signAtStart).
        if ($isAdhika) {
            $amantaIdx = ($signAtStart + 1) % 12;
        }

        $adhikaStr = ' (' . Localization::translate('Common', 'Adhika') . ')';
        $kshayaStr = ' (' . Localization::translate('Common', 'Kshaya') . ')';

        $amantaName = Masa::from($amantaIdx)->getName();
        $amantaNameEn = Masa::from($amantaIdx)->getName('en');
        if ($isAdhika) {
            $amantaName .= $adhikaStr;
            $amantaNameEn .= ' (Adhika)';
        } elseif ($isKshaya) {
            $amantaName .= $kshayaStr;
            $amantaNameEn .= ' (Kshaya)';
        }

        // Purnimanta month: Amanta month during Shukla Paksha, (Amanta+1) during Krishna Paksha
        $moonSunAngle = $this->getMoonSunAngle($jd);
        $paksha = ($moonSunAngle < 180.0) ? 'Shukla' : 'Krishna';

        $purnimantaIdx = ($paksha === 'Shukla') ? $amantaIdx : ($amantaIdx + 1) % 12;
        $purnimantaName = Masa::from($purnimantaIdx)->getName();
        $purnimantaNameEn = Masa::from($purnimantaIdx)->getName('en');
        // Purnimanta gets Adhika suffix when we're in Shukla Paksha of an Adhika month
        if ($isAdhika && $paksha === 'Shukla') {
            $purnimantaName .= $adhikaStr;
            $purnimantaNameEn .= ' (Adhika)';
        }

        $data = [
            'Month_Amanta' => $amantaName,
            'Month_Amanta_En' => $amantaNameEn,
            'Month_Purnimanta' => $purnimantaName,
            'Month_Purnimanta_En' => $purnimantaNameEn,
            'Amanta_Index' => $amantaIdx,
            'Purnimanta_Index' => $purnimantaIdx,
            'Is_Adhika' => $isAdhika,
            'Is_Kshaya' => $isKshaya,
        ];

        $this->monthCache[] = [
            'start' => $startAmavasya,
            'end' => $endAmavasya,
            'data' => $data,
        ];

        // Keep cache manageable (LRU-ish)
        if (count($this->monthCache) > 3) {
            array_shift($this->monthCache);
        }

        return $data;
    }

    private function buildBirthArray(
        CarbonImmutable $dt,
        float $lat,
        float $lon,
        string $tz,
        float $elevation = 0.0
    ): array {
        return [
            'year' => (int) $dt->format('Y'),
            'month' => (int) $dt->format('m'),
            'day' => (int) $dt->format('d'),
            'hour' => (int) $dt->format('H'),
            'minute' => (int) $dt->format('i'),
            'second' => (int) $dt->format('s'),
            'timezone' => $tz,
            'latitude' => $lat,
            'longitude' => $lon,
            'elevation' => $elevation,
        ];
    }

    private function parseDisplayDateTime(string $value, string $tz): CarbonImmutable
    {
        $formats = ['d/m/Y h:i:s A', 'd/m/Y h:i A', 'Y-m-d H:i:s', 'Y-m-d\\TH:i:sP', 'Y-m-d H:i'];
        foreach ($formats as $format) {
            try {
                $parsed = CarbonImmutable::createFromFormat($format, trim($value), $tz);
            } catch (Throwable) {
                $parsed = false;
            }

            if ($parsed instanceof CarbonImmutable) {
                return $parsed;
            }
        }

        return CarbonImmutable::parse($value, $tz);
    }

}
