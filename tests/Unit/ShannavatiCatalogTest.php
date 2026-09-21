<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Tests\Unit;

use JayeshMepani\PanchangCore\Shraddha\ShannavatiCatalog;
use PHPUnit\Framework\TestCase;

final class ShannavatiCatalogTest extends TestCase
{
    public function test_nominal_taxonomy_is_exactly_ninety_six(): void
    {
        self::assertSame(96, ShannavatiCatalog::nominalCount());
        self::assertSame(10, count(ShannavatiCatalog::NOMINAL_COUNTS));
        self::assertSame(14, count(ShannavatiCatalog::MANVADI_DHARMA_SINDHU));
        self::assertSame(4, count(ShannavatiCatalog::YUGADI_DHARMA_SINDHU));
        self::assertSame(5, count(ShannavatiCatalog::ASHTAKA_MONTHS));
        self::assertSame(12, count(ShannavatiCatalog::SANKRANTIS));
        self::assertSame(17, ShannavatiCatalog::NITYA_YOGA_WINDOW_INDICES['vyatipata']);
        self::assertSame(27, ShannavatiCatalog::NITYA_YOGA_WINDOW_INDICES['vaidhriti']);
        self::assertCount(96, ShannavatiCatalog::nominalCanonicalIds());
        self::assertCount(96, array_unique(ShannavatiCatalog::nominalCanonicalIds()));
    }
}
