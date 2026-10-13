<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Astronomy;

use FFI;
use JmeEph\FFI\JmeEphFFI;
use RuntimeException;

/**
 * Checked JME startup: True Citra sidereal mode, and the engine that actually
 * computes the Sun and the Moon.
 */
final class JmeRuntime
{
    /** @return array{sun: int, moon: int} */
    public static function assertSunMoonEngine(JmeEphFFI $jme, string $nativeEngine): array
    {
        $expected = match ($nativeEngine) {
            'JPL' => JmeEphFFI::JME_ENGINE_ID_JPL,
            'MOSHIER' => JmeEphFFI::JME_ENGINE_ID_MOSHIER,
            'VSOP_ELP_MEEUS' => JmeEphFFI::JME_ENGINE_ID_VSOP87_ELP82B,
            'AUTO' => null,
            default => throw new RuntimeException('Unknown JME engine mode: '.$nativeEngine),
        };

        $ffi = $jme->getFFI();
        $engineId = $ffi->new('int');
        $substituted = $ffi->new('int');
        $error = $ffi->new('char[256]');
        $resolved = [];

        foreach (['sun' => JmeEphFFI::JME_BODY_SUN, 'moon' => JmeEphFFI::JME_BODY_MOON] as $name => $body) {
            $rc = $jme->jme_calc_resolve_engine(2451545.0, $body, FFI::addr($engineId), FFI::addr($substituted), $error);
            if ($rc !== JmeEphFFI::JME_OK) {
                throw new RuntimeException(sprintf('Unable to resolve the %s engine: %s', $name, FFI::string($error)));
            }

            $id = $engineId->cdata;
            if ($expected !== null && $id !== $expected) {
                throw new RuntimeException(sprintf(
                    'Configured JME engine %s expects id %d, but the %s resolves to id %d.',
                    $nativeEngine,
                    $expected,
                    $name,
                    $id,
                ));
            }

            if ($substituted->cdata !== 0) {
                throw new RuntimeException(sprintf(
                    'JME substituted a different center for the %s (engine id %d).',
                    $name,
                    $id,
                ));
            }

            $resolved[$name] = $id;
        }

        return $resolved;
    }

    public static function setTrueCitra(JmeEphFFI $jme): void
    {
        $error = $jme->getFFI()->new('char[256]');
        $rc = $jme->jme_set_sidereal_mode_checked(JmeEphFFI::JME_SIDEREAL_TRUE_CITRA, 0.0, 0.0, $error);
        if ($rc !== JmeEphFFI::JME_OK) {
            throw new RuntimeException(FFI::string($error));
        }
    }

    public static function nativeEngineName(string $mode): string
    {
        return match (strtoupper($mode)) {
            'JPL' => 'JPL',
            'MOSHIER' => 'MOSHIER',
            'VSOP_ELP_MEEUS', 'ANALYTICAL' => 'VSOP_ELP_MEEUS',
            'AUTO' => 'AUTO',
            default => throw new RuntimeException('Unknown JME engine mode: '.$mode),
        };
    }
}
