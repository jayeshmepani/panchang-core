<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Traits;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use JayeshMepani\PanchangCore\Astronomy\AstronomyService;
use JayeshMepani\PanchangCore\Astronomy\EclipseService;
use JayeshMepani\PanchangCore\Astronomy\Math\IntervalTracker;
use JayeshMepani\PanchangCore\Astronomy\Math\TransitEngine;
use JayeshMepani\PanchangCore\Astronomy\SunService;
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
use JayeshMepani\PanchangCore\Panchanga\OutputGeneratorService;
use JayeshMepani\PanchangCore\Panchanga\PanchangaEngine;
use JayeshMepani\PanchangCore\Panchanga\PanchangService;
use JayeshMepani\PanchangCore\Panchanga\Residences\ShoolaCalculator;
use JayeshMepani\PanchangCore\Panchanga\Residences\VaasaCalculator;
use JayeshMepani\PanchangCore\Panchanga\Vrata\EkadashiParanaCalculator;
use JayeshMepani\PanchangCore\Panchanga\Yogas\SpecialYogaCalculator;
use JayeshMepani\PanchangCore\Support\DebugTrace;
use JmeEph\FFI\JmeEphFFI;

/**
 * CLI Bootstrap trait for standalone PHP scripts.
 *
 * Usage in scripts:
 *   require __DIR__ . '/vendor/autoload.php';
 *   CliBootstrap::init(__DIR__);
 *   $panchangService = CliBootstrap::makePanchangService();
 *
 * This sets up env(), config(), and the DI container so the package works
 * without a full Laravel framework.
 */
final class CliBootstrap
{
    /**
     * Set up the standalone environment. Call this ONCE at the top of a script.
     *
     * @param string $baseDir Directory containing vendor/autoload.php and config/
     */
    public static function init(string $baseDir): void
    {
        DebugTrace::log('cli.init', 'bootstrapping CLI environment', ['base_dir' => $baseDir]);
        self::defineEnvHelper();
        self::defineConfigHelper($baseDir);
        self::setupContainer($baseDir);
    }

    /**
     * Convenience: create a fully wired PanchangService for standalone usage.
     *
     * Call CliBootstrap::init($baseDir) first.
     */
    public static function makePanchangService(): PanchangService
    {
        $jme = new JmeEphFFI;
        self::configureJme($jme);
        $sunService = new SunService($jme);
        $transitEngine = new TransitEngine($jme);
        $intervalTracker = new IntervalTracker($transitEngine, $sunService);
        $bhadraEngine = new BhadraEngine;

        $muhurtaService = new MuhurtaService(
            new HoraCalculator,
            new ChogadiyaCalculator,
            new DailyPeriodsCalculator,
            new InauspiciousPeriodsCalculator,
            new GowriPanchangamCalculator,
            new LagnaTableCalculator
        );

        $ruleEngine = new FestivalRuleEngine;
        $festivalService = new FestivalService($ruleEngine);

        return new PanchangService(
            $jme,
            $sunService,
            new AstronomyService($jme),
            new PanchangaEngine,
            $muhurtaService,
            $festivalService,
            $bhadraEngine,
            $transitEngine,
            $intervalTracker,
            new VaasaCalculator($sunService),
            new ShoolaCalculator($sunService),
            new SpecialYogaCalculator($sunService, $intervalTracker),
            new PanchakCalculator($intervalTracker),
            new BhadraCalculator($transitEngine, $bhadraEngine),
            new VarjyamWindowCalculator($transitEngine),
            new EkadashiParanaCalculator($transitEngine, $sunService)
        );
    }

    /** Convenience: create an EclipseService. */
    public static function makeEclipseService(): EclipseService
    {
        $jme = new JmeEphFFI;
        self::configureJme($jme);
        return new EclipseService($jme);
    }

    /** Convenience: create an OutputGeneratorService. */
    public static function makeOutputGenerator(
        PanchangService $panchang,
    ): OutputGeneratorService {
        return new OutputGeneratorService(
            $panchang,
            self::makeEclipseService(),
        );
    }

    /** Define env() helper if not already defined. */
    private static function defineEnvHelper(): void
    {
        if (function_exists('env')) {
            return;
        }

        // phpcs:disable
        eval('
        function env(string $key, mixed $default = null): mixed {
            $value = $_ENV[$key] ?? $_SERVER[$key] ?? false;
            if ($value === false) { return $default; }
            if (is_string($value)) {
                $v = trim($value);
                $l = strtolower($v);
                if ($l === "true" || $l === "(true)") { return true; }
                if ($l === "false" || $l === "(false)") { return false; }
                if ($l === "null" || $l === "(null)") { return null; }
                if ($l === "empty" || $l === "(empty)") { return ""; }
                return $v;
            }
            return $value;
        }
        ');
        // phpcs:enable
    }

    /** Define config() helper if not already defined. */
    private static function defineConfigHelper(string $baseDir): void
    {
        if (function_exists('config')) {
            return;
        }

        // phpcs:disable
        eval('
        function config(array|string|null $key = null, mixed $default = null): mixed {
            static $store = null;
            if ($store === null) {
                $configPath = "' . addslashes($baseDir) . '/config/panchang.php";
                $store = ["panchang" => file_exists($configPath) ? require $configPath : []];
            }
            if ($key === null) { return $store; }
            if (is_array($key)) { return true; }
            $segments = explode(".", $key);
            $value = $store;
            foreach ($segments as $s) {
                if (!is_array($value) || !array_key_exists($s, $value)) { return $default; }
                $value = $value[$s];
            }
            return $value;
        }
        ');
        // phpcs:enable
    }

    /** Set up Illuminate Container if available. */
    private static function setupContainer(string $baseDir): void
    {
        if (!class_exists(Container::class)) {
            return;
        }

        if (!class_exists(Repository::class)) {
            return;
        }

        $container = new Container;
        $repo = new Repository(['panchang' => require $baseDir . '/config/panchang.php']);
        $container->instance('config', $repo);
        Container::setInstance($container);
    }

    private static function configureJme(JmeEphFFI $jme): void
    {
        $ephePath = config('panchang.ephe_path', $_ENV['PANCHANG_EPHE_PATH'] ?? '');
        if (is_string($ephePath) && $ephePath !== '' && file_exists($ephePath)) {
            $jme->jme_set_ephemeris_path($ephePath);
        }

        $jme->jme_set_sidereal_mode(JmeEphFFI::JME_SIDEREAL_LAHIRI, 0.0, 0.0);
        $engineMode = strtoupper((string) config('panchang.jme_settings.mode', 'auto'));
        $nativeEngine = match ($engineMode) {
            'JPL' => 'JPL',
            'MOSHIER' => 'MOSHIER',
            'VSOP_ELP_MEEUS', 'VSOP87', 'VSOP+ELP+MEEUS' => 'VSOP_ELP_MEEUS',
            'ANALYTICAL' => 'ANALYTICAL',
            default => 'AUTO',
        };
        $jme->jme_set_astro_models('ENGINE=' . $nativeEngine, 0);
        DebugTrace::log('cli.jme', 'configured JME runtime', [
            'engine' => $nativeEngine,
            'ephe_path' => is_string($ephePath) ? $ephePath : '',
        ]);
    }
}
