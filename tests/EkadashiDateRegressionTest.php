<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Tests;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Panchanga\PanchangService;
use JayeshMepani\PanchangCore\PanchangServiceProvider;
use Orchestra\Testbench\TestCase;
use Override;
use PHPUnit\Framework\Attributes\Group;

#[Group('slow')]
class EkadashiDateRegressionTest extends TestCase
{
    public function test_un_gujarat_ekadashi_regression_dates(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);

        foreach ([
            ['2026-05-27', 'Padmini Ekadashi'],
            ['2026-05-27', 'Mahadwadashi'],
            ['2026-08-23', 'Shravana Putrada Ekadashi'],
            ['2026-11-21', 'Devutthana (Prabodhini) Ekadashi'],
        ] as [$date, $expectedFestival]) {
            $details = $service->getDayDetails(
                CarbonImmutable::parse($date, 'Asia/Kolkata'),
                23.2472446,
                69.668339,
                'Asia/Kolkata',
                0.0,
                null,
                'amanta',
            );

            $festivalNames = array_map(
                static fn (array $festival): string => (string) ($festival['resolution']['festival_name'] ?? $festival['name'] ?? ''),
                $details['Festivals'] ?? []
            );

            $this->assertContains($expectedFestival, $festivalNames, $expectedFestival . ' should be present on ' . $date);
        }
    }

    public function test_padmini_ekadashi_is_not_emitted_on_previous_kshaya_day(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);

        $details = $service->getDayDetails(
            CarbonImmutable::parse('2026-05-26', 'Asia/Kolkata'),
            23.2472446,
            69.668339,
            'Asia/Kolkata',
            0.0,
            null,
            'amanta',
        );

        $festivalNames = array_map(
            static fn (array $festival): string => (string) ($festival['resolution']['festival_name'] ?? $festival['name'] ?? ''),
            $details['Festivals'] ?? []
        );

        $this->assertNotContains('Padmini Ekadashi', $festivalNames, 'Padmini Ekadashi should not be emitted on the previous kshaya day.');
    }

    public function test_padmini_ekadashi_does_not_shift_past_second_vriddhi_sunrise(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);

        $details = $service->getDayDetails(
            CarbonImmutable::parse('2026-05-28', 'Asia/Kolkata'),
            23.2472446,
            69.668339,
            'Asia/Kolkata',
            0.0,
            null,
            'amanta',
        );

        $festivalNames = array_map(
            static fn (array $festival): string => (string) ($festival['resolution']['festival_name'] ?? $festival['name'] ?? ''),
            $details['Festivals'] ?? []
        );

        $this->assertNotContains('Padmini Ekadashi', $festivalNames, 'Padmini Ekadashi must stay on the second Ekadashi sunrise and not shift to Dwadashi.');
    }

    public function test_ekadashi_vriddhi_day_carries_unmilini_mahadwadashi_alias(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);

        $details = $service->getDayDetails(
            CarbonImmutable::parse('2026-05-27', 'Asia/Kolkata'),
            23.2472446,
            69.668339,
            'Asia/Kolkata',
            0.0,
            null,
            'amanta',
        );

        $mahadvadashi = null;
        foreach (($details['Festivals'] ?? []) as $festival) {
            $name = (string) ($festival['resolution']['festival_name'] ?? $festival['name'] ?? '');
            if ($name === 'Mahadwadashi') {
                $mahadvadashi = $festival;
                break;
            }
        }

        self::assertIsArray($mahadvadashi, 'Mahadwadashi should be emitted on 2026-05-27.');
        self::assertContains('Unmilini Mahadwadashi', (array) ($mahadvadashi['aliases'] ?? []));
    }

    public function test_dynamic_mahadwadashi_subtypes_from_mahadwadashi_reference_dates(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);

        foreach ([
            '2025-06-07' => 'Vanjuli Mahadwadashi',
            '2025-11-02' => 'Trisparsha Mahadwadashi',
            '2026-05-27' => 'Unmilini Mahadwadashi',
            '2027-06-15' => 'Pakshavarddhini Mahadwadashi',
            '2027-09-12' => 'Vijaya Mahadwadashi',
        ] as $date => $expectedAlias) {
            $details = $service->getDayDetails(
                CarbonImmutable::parse($date, 'Asia/Kolkata'),
                23.2472446,
                69.668339,
                'Asia/Kolkata',
                0.0,
                null,
                'amanta',
            );

            $aliases = [];
            foreach (($details['Festivals'] ?? []) as $festival) {
                $name = (string) ($festival['resolution']['festival_name'] ?? $festival['name'] ?? '');
                if ($name === 'Mahadwadashi') {
                    $aliases = array_merge($aliases, (array) ($festival['aliases'] ?? []));
                }
            }

            self::assertContains($expectedAlias, $aliases, $expectedAlias . ' should be emitted dynamically on ' . $date);
        }
    }

    public function test_mahadwadashi_payload_is_localized_for_gujarati_and_hindi(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);

        foreach ([
            'gu' => 'ઉન્મીલની મહાદ્વાદશી',
            'hi' => 'उन्मीलनी महाद्वादशी',
        ] as $locale => $expectedAlias) {
            config(['panchang.defaults.locale' => $locale]);

            $details = $service->getDayDetails(
                CarbonImmutable::parse('2026-05-27', 'Asia/Kolkata'),
                23.2472446,
                69.668339,
                'Asia/Kolkata',
                0.0,
                null,
                'amanta',
            );

            $mahadvadashi = null;
            foreach (($details['Festivals'] ?? []) as $festival) {
                if (($festival['name_key'] ?? null) === 'Mahadwadashi') {
                    $mahadvadashi = $festival;
                    break;
                }
            }

            self::assertIsArray($mahadvadashi, 'Localized Mahadwadashi should be emitted for ' . $locale);
            self::assertContains($expectedAlias, (array) ($mahadvadashi['aliases'] ?? []));
            self::assertNotContains('Unmilini Mahadwadashi', (array) ($mahadvadashi['aliases'] ?? []));
            self::assertNotSame(
                'Unmilini Mahadwadashi fasting day observed in the Vaishnava Ekadashi tradition',
                $mahadvadashi['description'] ?? null
            );
        }
    }

    #[Override]
    protected function getPackageProviders($app): array
    {
        return [PanchangServiceProvider::class];
    }
}
