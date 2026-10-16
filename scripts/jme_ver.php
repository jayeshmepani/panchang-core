<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use JmeEph\FFI\JmeEphFFI;

$jme = new JmeEphFFI();
$buffer = $jme->getFFI()->new('char[256]');
$jme->jme_version($buffer, 256);

echo FFI::string($buffer), PHP_EOL;