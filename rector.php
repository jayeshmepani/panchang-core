<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/src', __DIR__ . '/config', __DIR__ . '/tests'])

    ->withSkip([
        __DIR__ . '/vendor',
        __DIR__ . '/node_modules',
        __DIR__ . '/storage',
        __DIR__ . '/docs',
        __DIR__ . '/misc',
        __DIR__ . '/coverage',
        __DIR__ . '/build',
        __DIR__ . '/bootstrap/cache',
        __DIR__ . '/tests/Fixtures',
        __DIR__ . '/tests/fixtures',
    ])

    ->withPhpSets()

    /*
     * SAFE ONLY.
     *
     * Avoid aggressive semantic rewrites.
     */
    ->withPreparedSets(
        deadCode: false,
        codeQuality: false,
        codingStyle: true,
        typeDeclarations: true,
        privatization: false,
        naming: false,
        instanceOf: false,
        earlyReturn: false,
    )

    ->withParallel()

    ->withImportNames(
        importNames: true,
        removeUnusedImports: true,
    );
