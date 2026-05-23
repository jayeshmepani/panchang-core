<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Support;

final class DebugTrace
{
    public static function enabled(): bool
    {
        $value = $_ENV['PANCHANG_DEBUG'] ?? $_SERVER['PANCHANG_DEBUG'] ?? getenv('PANCHANG_DEBUG');
        if ($value === false) {
            $value = '';
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on', 'debug'], true);
    }

    public static function log(string $scope, string $message, array $context = []): void
    {
        if (! self::enabled()) {
            return;
        }

        $line = '[' . date('c') . '] [' . $scope . '] ' . $message;
        if ($context !== []) {
            $json = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($json !== false) {
                $line .= ' ' . $json;
            }
        }

        $line .= PHP_EOL;
        $file = $_ENV['PANCHANG_DEBUG_FILE'] ?? $_SERVER['PANCHANG_DEBUG_FILE'] ?? getenv('PANCHANG_DEBUG_FILE');
        if ($file === false) {
            $file = '';
        }

        if (is_string($file) && $file !== '') {
            file_put_contents($file, $line, FILE_APPEND);
            return;
        }

        fwrite(STDERR, $line);
    }
}
