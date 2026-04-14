<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Traits;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use JayeshMepani\PanchangCore\Astronomy\AstronomyService;
use JayeshMepani\PanchangCore\Astronomy\EclipseService;
use JayeshMepani\PanchangCore\Astronomy\SunService;
use JayeshMepani\PanchangCore\Festivals\FestivalRuleEngine;
use JayeshMepani\PanchangCore\Festivals\FestivalService;
use JayeshMepani\PanchangCore\Festivals\Utils\BhadraEngine;
use JayeshMepani\PanchangCore\Panchanga\MuhurtaService;
use JayeshMepani\PanchangCore\Panchanga\OutputGeneratorService;
use JayeshMepani\PanchangCore\Panchanga\PanchangaEngine;
use JayeshMepani\PanchangCore\Panchanga\PanchangService;
use SwissEph\FFI\SwissEphFFI;

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
        $sweph = new SwissEphFFI;
        $ruleEngine = new FestivalRuleEngine;
        $festivalService = new FestivalService($ruleEngine);

        return new PanchangService(
            $sweph,
            new SunService($sweph),
            new AstronomyService($sweph),
            new PanchangaEngine,
            new MuhurtaService,
            $festivalService,
            new BhadraEngine,
        );
    }

    /** Convenience: create an EclipseService. */
    public static function makeEclipseService(): EclipseService
    {
        return new EclipseService(new SwissEphFFI);
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
}
