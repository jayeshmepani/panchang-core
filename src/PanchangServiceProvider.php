<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore;

use Illuminate\Support\ServiceProvider;
use JayeshMepani\PanchangCore\Astronomy\AstronomyService;
use JayeshMepani\PanchangCore\Astronomy\EclipseService;
use JayeshMepani\PanchangCore\Astronomy\Math\IntervalTracker;
use JayeshMepani\PanchangCore\Astronomy\Math\TransitEngine;
use JayeshMepani\PanchangCore\Astronomy\SunService;
use JayeshMepani\PanchangCore\Core\AstroCore;
use JayeshMepani\PanchangCore\Festivals\FestivalFamilyOrchestrator;
use JayeshMepani\PanchangCore\Festivals\FestivalRuleEngine;
use JayeshMepani\PanchangCore\Festivals\FestivalService;
use JayeshMepani\PanchangCore\Festivals\Utils\BhadraEngine;
use JayeshMepani\PanchangCore\Muhurta\Classical\DailyPeriodsCalculator;
use JayeshMepani\PanchangCore\Muhurta\Classical\InauspiciousPeriodsCalculator;
use JayeshMepani\PanchangCore\Muhurta\Lagna\LagnaTableCalculator;
use JayeshMepani\PanchangCore\Muhurta\Planetary\ChogadiyaCalculator;
use JayeshMepani\PanchangCore\Muhurta\Planetary\HoraCalculator;
use JayeshMepani\PanchangCore\Muhurta\Regional\GowriPanchangamCalculator;
use JayeshMepani\PanchangCore\Panchanga\Doshas\BhadraCalculator;
use JayeshMepani\PanchangCore\Panchanga\Doshas\PanchakCalculator;
use JayeshMepani\PanchangCore\Panchanga\Doshas\VarjyamWindowCalculator;
use JayeshMepani\PanchangCore\Panchanga\MuhurtaService;
use JayeshMepani\PanchangCore\Panchanga\PanchangaEngine;
use JayeshMepani\PanchangCore\Panchanga\PanchangService;
use JayeshMepani\PanchangCore\Panchanga\Residences\ShoolaCalculator;
use JayeshMepani\PanchangCore\Panchanga\Residences\VaasaCalculator;
use JayeshMepani\PanchangCore\Panchanga\Vrata\EkadashiParanaCalculator;
use JayeshMepani\PanchangCore\Panchanga\Yogas\SpecialYogaCalculator;
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
        $this->app->singleton(SwissEphFFI::class, function ($app): SwissEphFFI {
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
        $this->app->singleton(AstronomyService::class, fn ($app): AstronomyService => new AstronomyService($app->make(SwissEphFFI::class)));

        $this->app->singleton(SunService::class, fn ($app): SunService => new SunService($app->make(SwissEphFFI::class)));

        $this->app->singleton(EclipseService::class, fn ($app): EclipseService => new EclipseService($app->make(SwissEphFFI::class)));

        $this->app->singleton(TransitEngine::class, fn ($app): TransitEngine => new TransitEngine($app->make(SwissEphFFI::class)));

        $this->app->singleton(IntervalTracker::class, fn ($app): IntervalTracker => new IntervalTracker(
            $app->make(TransitEngine::class),
            $app->make(SunService::class)
        ));

        // Panchanga layer
        $this->app->singleton(PanchangaEngine::class);

        $this->app->singleton(VaasaCalculator::class, fn ($app): VaasaCalculator => new VaasaCalculator($app->make(SunService::class)));

        $this->app->singleton(ShoolaCalculator::class, fn ($app): ShoolaCalculator => new ShoolaCalculator($app->make(SunService::class)));

        $this->app->singleton(SpecialYogaCalculator::class, fn ($app): SpecialYogaCalculator => new SpecialYogaCalculator(
            $app->make(SunService::class),
            $app->make(IntervalTracker::class)
        ));

        $this->app->singleton(PanchakCalculator::class, fn ($app): PanchakCalculator => new PanchakCalculator($app->make(IntervalTracker::class)));

        $this->app->singleton(BhadraCalculator::class, fn ($app): BhadraCalculator => new BhadraCalculator(
            $app->make(TransitEngine::class),
            $app->make(BhadraEngine::class)
        ));

        $this->app->singleton(VarjyamWindowCalculator::class, fn ($app): VarjyamWindowCalculator => new VarjyamWindowCalculator($app->make(TransitEngine::class)));

        $this->app->singleton(EkadashiParanaCalculator::class, fn ($app): EkadashiParanaCalculator => new EkadashiParanaCalculator(
            $app->make(TransitEngine::class),
            $app->make(SunService::class)
        ));

        // Muhurta layer
        $this->app->singleton(HoraCalculator::class);
        $this->app->singleton(ChogadiyaCalculator::class);
        $this->app->singleton(DailyPeriodsCalculator::class);
        $this->app->singleton(InauspiciousPeriodsCalculator::class);
        $this->app->singleton(GowriPanchangamCalculator::class);
        $this->app->singleton(LagnaTableCalculator::class);

        $this->app->singleton(MuhurtaService::class, fn ($app): MuhurtaService => new MuhurtaService(
            $app->make(HoraCalculator::class),
            $app->make(ChogadiyaCalculator::class),
            $app->make(DailyPeriodsCalculator::class),
            $app->make(InauspiciousPeriodsCalculator::class),
            $app->make(GowriPanchangamCalculator::class),
            $app->make(LagnaTableCalculator::class)
        ));

        $this->app->singleton(FestivalRuleEngine::class);

        $this->app->singleton(FestivalFamilyOrchestrator::class);

        $this->app->singleton(FestivalService::class, fn ($app): FestivalService => new FestivalService(
            $app->make(FestivalRuleEngine::class)
        ));

        // Main Panchang service
        $this->app->singleton(PanchangService::class, fn ($app): PanchangService => new PanchangService(
            $app->make(SwissEphFFI::class),
            $app->make(SunService::class),
            $app->make(AstronomyService::class),
            $app->make(PanchangaEngine::class),
            $app->make(MuhurtaService::class),
            $app->make(FestivalService::class),
            $app->make(BhadraEngine::class),
            $app->make(TransitEngine::class),
            $app->make(IntervalTracker::class),
            $app->make(VaasaCalculator::class),
            $app->make(ShoolaCalculator::class),
            $app->make(SpecialYogaCalculator::class),
            $app->make(PanchakCalculator::class),
            $app->make(BhadraCalculator::class),
            $app->make(VarjyamWindowCalculator::class),
            $app->make(EkadashiParanaCalculator::class)
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
