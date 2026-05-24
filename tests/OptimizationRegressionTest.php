<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Tests;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Astronomy\Math\TransitEngine;
use JayeshMepani\PanchangCore\Core\Enums\CalendarType;
use JayeshMepani\PanchangCore\Panchanga\PanchangService;
use JayeshMepani\PanchangCore\PanchangServiceProvider;
use JmeEph\FFI\JmeEphFFI;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Group;
use ReflectionClass;

#[Group('slow')]
class OptimizationRegressionTest extends TestCase
{
    public function testRepeatedDayDetailsRemainIdentical(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);

        $date = CarbonImmutable::create(2026, 5, 23, 0, 0, 0, 'Asia/Kolkata');
        $args = [
            'date' => $date,
            'lat' => 23.2472446,
            'lon' => 69.668339,
            'tz' => 'Asia/Kolkata',
            'elevation' => 0.0,
            'calculationAt' => null,
            'calendarType' => CalendarType::Amanta,
            'options' => [],
        ];

        $first = $service->getDayDetails(...$args);
        $second = $service->getDayDetails(...$args);

        $this->assertSame(
            json_encode($first, JSON_THROW_ON_ERROR),
            json_encode($second, JSON_THROW_ON_ERROR)
        );
    }

    public function testTransitEngineBodyLongitudeCacheIsBounded(): void
    {
        /** @var JmeEphFFI $jme */
        $jme = $this->app->make(JmeEphFFI::class);
        $engine = new TransitEngine($jme, bodyLongitudeCacheMax: 20, bodyLongitudeCacheTrimTo: 10);

        for ($i = 0; $i < 21; $i++) {
            $jd = 2461183.0 + ($i / 100000.0);
            $engine->calcBodyAtJd($jd, JmeEphFFI::JME_BODY_SUN, JmeEphFFI::JME_CALC_HIGH_PRECISION | JmeEphFFI::JME_CALC_SIDEREAL);
        }

        $cacheProperty = (new ReflectionClass($engine))->getProperty('bodyLongitudeCache');
        /** @var array<string, float> $cache */
        $cache = $cacheProperty->getValue($engine);

        $this->assertLessThanOrEqual(20, count($cache));
        $this->assertGreaterThanOrEqual(10, count($cache));
    }

    protected function getPackageProviders($app): array
    {
        return [PanchangServiceProvider::class];
    }
}
