<?php

declare(strict_types=1);

function panchang_script_locale(): string
{
    return (string) config('panchang.defaults.locale', 'en');
}

function panchang_script_calendar_type(): string
{
    return (string) config('panchang.defaults.calendar_type', 'amanta');
}

function panchang_script_output_dir(string $baseDir, ?string $calendarType = null, ?string $locale = null): string
{
    $resolvedCalendarType = $calendarType ?? panchang_script_calendar_type();
    $resolvedLocale = $locale ?? panchang_script_locale();

    $path = $baseDir
        . DIRECTORY_SEPARATOR . 'scripts'
        . DIRECTORY_SEPARATOR . 'output'
        . DIRECTORY_SEPARATOR . $resolvedCalendarType
        . DIRECTORY_SEPARATOR . $resolvedLocale;

    if (! is_dir($path)) {
        mkdir($path, 0777, true);
    }

    return $path;
}

/**
 * @param array<string, mixed> $payload
 */
function panchang_script_encode_json(array $payload): string
{
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('JSON encoding failed: ' . json_last_error_msg());
    }

    return $json . PHP_EOL;
}

/**
 * @param array<string, mixed> $payload
 */
function panchang_script_write_json(string $path, array $payload): string
{
    $json = panchang_script_encode_json($payload);
    file_put_contents($path, $json);

    return $json;
}

function panchang_stdout_is_interactive(): bool
{
    return function_exists('stream_isatty') && stream_isatty(STDOUT);
}
