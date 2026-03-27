<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Tests;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Panchanga\PanchangService;
use JayeshMepani\PanchangCore\PanchangServiceProvider;
use Orchestra\Testbench\TestCase;

class ConfigurationFormattingTest extends TestCase
{
    public function testFormattingOutputsWithDifferentConfigurations(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);
        $date = CarbonImmutable::parse('2026-03-24 12:00:00', 'Asia/Kolkata');

        // --- Test Default configs (12h, indian_12h, mixed duration, degree angles) ---
        config(['panchang.defaults.time_notation' => '12h']);
        config(['panchang.defaults.date_time_format' => 'indian_12h']);
        config(['panchang.defaults.duration_format' => 'mixed']);
        config(['panchang.defaults.angle_unit' => 'degree']);
        config(['panchang.defaults.coordinate_format' => 'decimal']);

        $details = $service->getDayDetails(
            date: $date,
            lat: 23.2472446,
            lon: 69.668339,
            tz: 'Asia/Kolkata'
        );

        $this->assertStringContainsString('AM', $details['Sunrise']); // 12h check
        $this->assertStringContainsString('AM', $details['Panchanga']['Sunrise']); // 12h check
        $this->assertIsFloat($details['sun_sunrise_lon']); // degree check
        $this->assertStringContainsString('m', (string) $details['Hora']['hora_duration_minutes']); // duration format mixed check

        // --- Test Alternative configs (24h, iso8601, minutes duration, dms angles) ---
        config(['panchang.defaults.time_notation' => '24h']);
        config(['panchang.defaults.date_time_format' => 'iso8601']);
        config(['panchang.defaults.duration_format' => 'minutes']);
        config(['panchang.defaults.angle_unit' => 'dms']);
        config(['panchang.defaults.coordinate_format' => 'dms']);

        $detailsAlt = $service->getDayDetails(
            date: $date,
            lat: 23.2472446,
            lon: 69.668339,
            tz: 'Asia/Kolkata'
        );

        $this->assertStringNotContainsString('AM', $detailsAlt['Sunrise']); // 24h check
        $this->assertStringNotContainsString('PM', $detailsAlt['Sunrise']); // 24h check
        $this->assertStringContainsString('°', (string)$detailsAlt['sun_sunrise_lon']); // dms angle check
        $this->assertIsFloat($detailsAlt['Hora']['hora_duration_minutes']); // duration minutes check (should be float)

        // Verify ISO8601
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $detailsAlt['sunrise_dt']);

        // Verify alternative duration formats
        config(['panchang.defaults.duration_format' => 'hours']);
        $detailsHours = $service->getDayDetails(
            date: $date,
            lat: 23.2472446,
            lon: 69.668339,
            tz: 'Asia/Kolkata'
        );
        // Expect a smaller float since it's hours instead of minutes
        $this->assertIsFloat($detailsHours['Hora']['hora_duration_minutes']);
        $this->assertTrue($detailsHours['Hora']['hora_duration_minutes'] < 2.0); // An hora is ~1 hour

        // --- Test Number Precision ---
        config(['panchang.defaults.angle_unit' => 'degree']); // Reset to degree to test float precision
        $detailsPrecise2 = $service->getDayDetails(
            date: $date,
            lat: 23.2472446,
            lon: 69.668339,
            tz: 'Asia/Kolkata'
        );

        $detailsPrecise16 = $service->getDayDetails(
            date: $date,
            lat: 23.2472446,
            lon: 69.668339,
            tz: 'Asia/Kolkata'
        );

        $sunLon2 = $detailsPrecise2['sun_sunrise_lon'];
        $sunLon16 = $detailsPrecise16['sun_sunrise_lon'];

        // Assert different strings due to precision
        $this->assertNotSame((string) $sunLon2, (string) $sunLon16);

        // Assert the lengths are different, meaning precision is affecting the output.
        $this->assertLessThan(strlen((string) $sunLon16), strlen((string) $sunLon2));

        // Ensure that length of string after decimal is at most 2 for the precision=2 config
        $decimalPart2 = explode('.', (string) $sunLon2)[1] ?? '';
        $this->assertLessThanOrEqual(2, strlen($decimalPart2));
    }
    protected function getPackageProviders($app): array
    {
        return [
            PanchangServiceProvider::class,
        ];
    }
}
