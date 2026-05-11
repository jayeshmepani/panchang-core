<?php

declare(strict_types=1);
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('src'));
$counts = [];
foreach ($files as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $lines = count(file($file->getPathname()));
        $counts[$file->getPathname()] = $lines;
    }
}
arsort($counts);
foreach ($counts as $path => $lines) {
    echo "$lines\t$path\n";
}
