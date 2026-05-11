<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use JayeshMepani\PanchangCore\Traits\CliBootstrap;

CliBootstrap::init(dirname(__DIR__));
$panchangService = CliBootstrap::makePanchangService();

// Check Jan 29, 2026 noon JD
$jd = 2461070.5;

// Manual extraction of logic from PanchangService
$refl = new ReflectionClass($panchangService);
$method = $refl->getMethod('getTrueHinduMonth');
$res = $method->invoke($panchangService, $jd);

echo 'Date JD: ' . $jd . PHP_EOL;
print_r($res);

$transitEngine = $refl->getProperty('transitEngine')->getValue($panchangService);
$transitEngineRefl = new ReflectionClass($transitEngine);
$pAm = $transitEngineRefl->getMethod('findAngleCrossing');
$angleFn = fn (float $t): float => $transitEngine->getMoonSunAngle($t);

$prev = $pAm->invoke($transitEngine, $jd, 0.0, -1, $angleFn);
$next = $pAm->invoke($transitEngine, $jd, 0.0, 1, $angleFn);

echo 'Prev Amavasya JD: ' . $prev . PHP_EOL;
echo 'Next Amavasya JD: ' . $next . PHP_EOL;

$s0 = $transitEngine->getSunLongitude($prev);
$s1 = $transitEngine->getSunLongitude($next);

echo sprintf('Sun at Prev: %s (Sign: ', $s0) . floor($s0 / 30) . ')' . PHP_EOL;
echo sprintf('Sun at Next: %s (Sign: ', $s1) . floor($s1 / 30) . ')' . PHP_EOL;
