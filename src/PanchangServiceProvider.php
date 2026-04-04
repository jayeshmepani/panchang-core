<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore;

use Illuminate\Support\ServiceProvider;
use JayeshMepani\PanchangCore\Astronomy\AstronomyService;
use JayeshMepani\PanchangCore\Astronomy\EclipseService;
use JayeshMepani\PanchangCore\Astronomy\SunService;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Festivals\FestivalFamilyOrchestrator;
use JayeshMepani\PanchangCore\Festivals\FestivalRuleEngine;
use JayeshMepani\PanchangCore\Festivals\FestivalService;
use JayeshMepani\PanchangCore\Festivals\Utils\BhadraEngine;
use JayeshMepani\PanchangCore\Panchanga\MuhurtaService;
use JayeshMepani\PanchangCore\Panchanga\PanchangaEngine;
use JayeshMepani\PanchangCore\Panchanga\PanchangService;
use Override;
use SwissEph\FFI\SwissEphFFI;

/**
 * Panchang Core Service Provider.
 *
 * Registers all core services for Vedic Panchanga calculations.
 * For Laravel integration, this provider auto-registers all services.
 * For standalone usage, instantiate services manually.
 */
class PanchangServiceProvider extends ServiceProvider
{
    /** Register any application services. */
    #[Override]
    public function register(): void
    {
        // Core services (no dependencies)
        $this->app->singleton(AstroCore::class);

        // Swiss Ephemeris FFI
        $this->app->singleton(SwissEphFFI::class, function ($app) {
            $sweph = new SwissEphFFI;

            // Configure ephemeris path from config
            $ephePath = config('panchang.ephe_path');
            if (is_string($ephePath) && $ephePath !== '' && file_exists($ephePath)) {
                $sweph->swe_set_ephe_path($ephePath);
            }

            // For any authentic Hindu Panchanga, Lahiri is the only absolute standard.
            $sweph->swe_set_sid_mode(SwissEphFFI::SE_SIDM_LAHIRI, 0.0, 0.0);

            return $sweph;
        });

        // Astronomy layer
        $this->app->singleton(AstronomyService::class, fn ($app) => new AstronomyService($app->make(SwissEphFFI::class)));

        $this->app->singleton(SunService::class, fn ($app) => new SunService($app->make(SwissEphFFI::class)));

        $this->app->singleton(EclipseService::class, fn ($app) => new EclipseService($app->make(SwissEphFFI::class)));

        // Panchanga layer
        $this->app->singleton(PanchangaEngine::class);

        $this->app->singleton(MuhurtaService::class);

        $this->app->singleton(FestivalRuleEngine::class);

        $this->app->singleton(FestivalFamilyOrchestrator::class);

        $this->app->singleton(FestivalService::class, fn ($app) => new FestivalService(
            $app->make(FestivalRuleEngine::class)
        ));

        // Main Panchang service
        $this->app->singleton(PanchangService::class, fn ($app) => new PanchangService(
            $app->make(SwissEphFFI::class),
            $app->make(SunService::class),
            $app->make(AstronomyService::class),
            $app->make(PanchangaEngine::class),
            $app->make(MuhurtaService::class),
            $app->make(FestivalService::class),
            $app->make(BhadraEngine::class)
        ));

        $this->app->singleton(BhadraEngine::class);
    }

    /** Bootstrap any application services. */
    public function boot(): void
    {
        // Publish config if Laravel
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/panchang.php' => config_path('panchang.php'),
            ], 'panchang-config');
        }
    }

}
