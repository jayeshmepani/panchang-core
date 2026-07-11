<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use JayeshMepani\PanchangCore\Festivals\FestivalService;

$data = [
    'tithi_vratas' => FestivalService::TITHI_VRATAS,
    'festivals' => FestivalService::FESTIVALS,
];

file_put_contents(__DIR__ . '/festivals_dump.json', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "Dumped to scripts/festivals_dump.json\n";
