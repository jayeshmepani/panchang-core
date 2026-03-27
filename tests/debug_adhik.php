<?php

require __DIR__ . '/../vendor/autoload.php';

use JayeshMepani\PanchangCore\Astronomy\AstronomyService;
use JayeshMepani\PanchangCore\Astronomy\SunService;
use JayeshMepani\PanchangCore\Festivals\FestivalRuleEngine;
use JayeshMepani\PanchangCore\Festivals\Utils\BhadraEngine;
use JayeshMepani\PanchangCore\Panchanga\MuhurtaService;
use JayeshMepani\PanchangCore\Panchanga\PanchangaEngine;
use JayeshMepani\PanchangCore\Panchanga\PanchangService;
use SwissEph\FFI\SwissEphFFI;

$sweph = new SwissEphFFI;
$ruleEngine = new FestivalRuleEngine;
$panchangService = new PanchangService(
    $sweph,
    new SunService($sweph),
    new AstronomyService($sweph),
    new PanchangaEngine,
    new MuhurtaService,
    $festivalService,
    new BhadraEngine,
);

// Check Jan 29, 2026 noon JD
$jd = 2461070.5;

// Manual extraction of logic from PanchangService
$refl = new ReflectionClass($panchangService);
$method = $refl->getMethod('getTrueHinduMonth');
$method->setAccessible(true);
$res = $method->invoke($panchangService, $jd);

echo "Date JD: $jd" . PHP_EOL;
print_r($res);

// Debug the boundaries
$pAm = $refl->getMethod('findAngleCrossing');
$pAm->setAccessible(true);
$angleFn = function (float $t) use ($panchangService, $refl) {
    $m = $refl->getMethod('getMoonSunAngle');
    $m->setAccessible(true);
    return $m->invoke($panchangService, $t);
};

$prev = $pAm->invoke($panchangService, $jd, 0.0, -1, $angleFn);
$next = $pAm->invoke($panchangService, $jd, 0.0, 1, $angleFn);

echo "Prev Amavasya JD: $prev" . PHP_EOL;
echo "Next Amavasya JD: $next" . PHP_EOL;

$getSun = $refl->getMethod('getSunLongitude');
$getSun->setAccessible(true);
$s0 = $getSun->invoke($panchangService, $prev);
$s1 = $getSun->invoke($panchangService, $next);

echo "Sun at Prev: $s0 (Sign: " . floor($s0 / 30) . ')' . PHP_EOL;
echo "Sun at Next: $s1 (Sign: " . floor($s1 / 30) . ')' . PHP_EOL;
