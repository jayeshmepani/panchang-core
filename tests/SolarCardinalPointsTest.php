<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Tests;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Astronomy\AstronomyService;
use JayeshMepani\PanchangCore\PanchangServiceProvider;
use JmeEph\FFI\JmeEphFFI;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The apparent tropical Sun reaches 0, 90, 180 and 270 degrees at the
 * published 2026 equinox and solstice instants (USNO, rounded to the minute)
 * when computed with the engine's apparent-position flags.
 */
final class SolarCardinalPointsTest extends TestCase
{
    /** @return iterable<string, array{float, string}> */
    public static function cardinalPoints(): iterable
    {
        yield 'March equinox' => [0.0, '2026-03-20 14:46'];
        yield 'June solstice' => [90.0, '2026-06-21 08:24'];
        yield 'September equinox' => [180.0, '2026-09-23 00:05'];
        yield 'December solstice' => [270.0, '2026-12-21 20:50'];
    }

    #[DataProvider('cardinalPoints')]
    public function testCrossingMatchesPublishedInstant(float $target, string $publishedUtc): void
    {
        /** @var AstronomyService $astronomy */
        $astronomy = $this->app->make(AstronomyService::class);
        $published = CarbonImmutable::parse($publishedUtc, 'UTC');
        $publishedJd = $published->getTimestamp() / 86400.0 + 2440587.5;

        $tropicalApparent = AstronomyService::jmeSiderealApparentFlags() & ~JmeEphFFI::JME_CALC_SIDEREAL;
        $offset = fn(float $jd): float => $this->signedDiff(
            $astronomy->calcBodyLongitudeAtJd($jd, JmeEphFFI::JME_BODY_SUN, $tropicalApparent),
            $target,
        );

        $lo = $publishedJd - 0.5;
        $hi = $publishedJd + 0.5;
        self::assertLessThan(0.0, $offset($lo));
        self::assertGreaterThan(0.0, $offset($hi));
        for ($i = 0; $i < 60; $i++) {
            $mid = ($lo + $hi) / 2.0;
            if ($offset($mid) < 0.0) {
                $lo = $mid;
            } else {
                $hi = $mid;
            }
        }

        $crossingSeconds = ($lo - 2440587.5) * 86400.0;
        self::assertLessThan(60.0, abs($crossingSeconds - $published->getTimestamp()), $publishedUtc);
    }

    protected function getPackageProviders($app): array
    {
        return [PanchangServiceProvider::class];
    }

    private function signedDiff(float $angle, float $target): float
    {
        return fmod($angle - $target + 540.0, 360.0) - 180.0;
    }
}
