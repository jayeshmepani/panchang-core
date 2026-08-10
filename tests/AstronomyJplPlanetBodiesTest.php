<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Tests;

use JayeshMepani\PanchangCore\Astronomy\AstronomyService;
use JayeshMepani\PanchangCore\Astronomy\BrihaspatiSamvatsaraService;
use JayeshMepani\PanchangCore\PanchangServiceProvider;
use JmeEph\FFI\JmeEphFFI;
use Orchestra\Testbench\TestCase;

/**
 * Regression: outer planets must use DE/JPL barycenter body IDs
 * (astrology/jme_compat.py + jpl-ephemeris NAIF mapping). No Moshier fallback.
 */
final class AstronomyJplPlanetBodiesTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [PanchangServiceProvider::class];
    }

    public function test_jme_planet_body_ids_match_astrology_barycenter_convention(): void
    {
        $ids = AstronomyService::jmePlanetBodyIds();

        $this->assertSame(JmeEphFFI::JME_BODY_SUN, $ids['Sun']);
        $this->assertSame(JmeEphFFI::JME_BODY_MOON, $ids['Moon']);
        $this->assertSame(JmeEphFFI::JME_BODY_MERCURY, $ids['Mercury']);
        $this->assertSame(JmeEphFFI::JME_BODY_VENUS, $ids['Venus']);
        $this->assertSame(JmeEphFFI::JME_BODY_MEAN_NODE, $ids['Rahu']);

        // Critical: barycenters, not planet-centers.
        $this->assertSame(JmeEphFFI::JME_BODY_MARS_BARYCENTER, $ids['Mars']);
        $this->assertSame(JmeEphFFI::JME_BODY_JUPITER_BARYCENTER, $ids['Jupiter']);
        $this->assertSame(JmeEphFFI::JME_BODY_SATURN_BARYCENTER, $ids['Saturn']);

        $this->assertNotSame(JmeEphFFI::JME_BODY_MARS, $ids['Mars']);
        $this->assertNotSame(JmeEphFFI::JME_BODY_JUPITER, $ids['Jupiter']);
        $this->assertNotSame(JmeEphFFI::JME_BODY_SATURN, $ids['Saturn']);
    }

    public function test_jpl_mode_returns_nonzero_outer_planet_longitudes(): void
    {
        $this->assertSame(
            'jpl',
            strtolower((string) config('panchang.jme_settings.mode'))
        );

        /** @var AstronomyService $astro */
        $astro = $this->app->make(AstronomyService::class);
        $planets = $astro->getPlanets([
            'year' => 2026,
            'month' => 4,
            'day' => 21,
            'hour' => 12,
            'minute' => 0,
            'second' => 0,
            'timezone' => 'UTC',
            'latitude' => 0.0,
            'longitude' => 0.0,
            'elevation' => 0.0,
        ]);

        foreach (['Sun', 'Moon', 'Mercury', 'Venus', 'Mars', 'Jupiter', 'Saturn', 'Rahu'] as $name) {
            $this->assertArrayHasKey($name, $planets);
            $this->assertIsFloat($planets[$name]);
            $this->assertTrue(
                is_finite($planets[$name]),
                $name . ' longitude must be finite under JPL'
            );
        }

        // The historical silent-failure mode left outer planets at exact 0°.
        $this->assertNotEquals(0.0, $planets['Mars']);
        $this->assertNotEquals(0.0, $planets['Jupiter']);
        $this->assertNotEquals(0.0, $planets['Saturn']);
    }

    public function test_modern_brihaspati_uses_jpl_jupiter_barycenter_timing(): void
    {
        /** @var AstronomyService $astro */
        $astro = $this->app->make(AstronomyService::class);
        $service = new BrihaspatiSamvatsaraService($astro);

        $info = $service->getBrihaspatiSamvatsaraInfo(
            \Carbon\CarbonImmutable::create(2026, 8, 3, 12, 0, 0, 'Asia/Kolkata'),
            BrihaspatiSamvatsaraService::MODEL_MODERN_EPHEMERIS
        );

        $this->assertSame(BrihaspatiSamvatsaraService::MODEL_MODERN_EPHEMERIS, $info['model']);
        $this->assertStringContainsString('jupiter', strtolower((string) $info['timing_basis']));
        $this->assertNotEmpty($info['name']);
    }
}
