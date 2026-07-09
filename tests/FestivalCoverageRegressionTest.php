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
class FestivalCoverageRegressionTest extends TestCase
{
    public function test_new_vrat_families_and_derived_observances_are_present(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);

        foreach ([
            '2026-01-03' => 'Shri Satyanarayana Vrat',
            '2026-01-05' => 'Somwar Vrat',
            '2026-01-14' => 'ISKCON Ekadashi',
            '2026-03-26' => 'Ashoka Ashtami Vrat',
            '2026-05-17' => 'Purushottam Maas Begins',
            '2026-05-27' => 'Mahadwadashi',
            '2026-06-15' => 'Purushottam Maas Ends',
            '2026-07-25' => 'Chaturmasa Begins',
            '2026-11-21' => 'Chaturmasa Ends',
        ] as $date => $expectedFestival) {
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

    public function test_lossless_paraviddha_resolvers_emit_expected_2026_dates(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);

        $calendar = $service->getFestivalYearCalendar(
            2026,
            23.2472446,
            69.668339,
            'Asia/Kolkata',
            0.0,
            null,
            'amanta',
        );

        $flat = $calendar['flat'] ?? [];

        // Each of these observances is driven by a dedicated, textually-literal paraviddha
        // resolver added for lossless fidelity to the master Nirnay document:
        //  - Treta Yuga Diwas / Akshaya Tritiya: purvahna both-days 3-muhurta chooser (ref 385-389)
        //  - Radha Ashtami: madhyahna saptami-vedha rejection (ref 919-938)
        //  - Swaminarayan Varaha Jayanti: madhyahna tritiya-vedha rejection (ref 588-604)
        //  - Anant Chaturdashi: purvahna 2-muhurta post-sunrise chooser (ref 984-999)
        $expected = [
            'Treta Yuga Diwas' => ['2026-04-19', 'akshaya_tritiya_both_purvahna_below_3_muhurta_purva_day1'],
            'Radha Ashtami' => ['2026-09-18', 'madhyahna_shuddha_purva_vedha_free_day1'],
            'Swaminarayan Varaha Jayanti' => ['2026-08-16', 'madhyahna_shuddha_purva_vedha_free_day1'],
            'Anant Chaturdashi' => ['2026-09-25', 'anant_chaturdashi_2_muhurta_day1'],
        ];

        $found = [];
        foreach ($flat as $entry) {
            $festival = $entry['festival'] ?? [];
            $name = (string) ($festival['resolution']['festival_name'] ?? $festival['name'] ?? '');
            $aliases = array_map(strval(...), (array) ($festival['aliases'] ?? []));
            foreach (array_keys($expected) as $expectedName) {
                if ($name !== $expectedName && !in_array($expectedName, $aliases, true)) {
                    continue;
                }

                $found[$expectedName] = [
                    (string) ($entry['date'] ?? ''),
                    (string) ($festival['resolution']['decision']['winning_reason'] ?? ''),
                ];
            }
        }

        foreach ($expected as $name => [$date, $reason]) {
            self::assertArrayHasKey($name, $found, $name . ' must be emitted in 2026');
            self::assertSame($date, $found[$name][0], $name . ' observance date');
            self::assertSame($reason, $found[$name][1], $name . ' winning reason');
        }
    }

    public function test_rohini_vrat_is_present_in_year_calendar(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);

        $calendar = $service->getFestivalYearCalendar(
            2026,
            23.2472446,
            69.668339,
            'Asia/Kolkata',
            0.0,
            null,
            'amanta',
        );

        $flat = $calendar['flat'] ?? [];
        $rohiniDates = [];
        foreach ($flat as $entry) {
            $festival = $entry['festival'] ?? [];
            $name = (string) ($festival['resolution']['festival_name'] ?? $festival['name'] ?? '');
            if ($name === 'Rohini Vrat') {
                $rohiniDates[] = (string) ($entry['date'] ?? '');
            }
        }

        $this->assertNotEmpty($rohiniDates, 'Rohini Vrat should be emitted in the annual festival calendar');
        $this->assertContains('2026-01-01', $rohiniDates, 'Known Rohini Vrat date should be present');
    }

    public function test_monthly_hari_jayanti_emits_outside_chaitra_but_not_on_annual_chaitra_jayanti_day(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);

        $vaishakhaDetails = $service->getDayDetails(
            CarbonImmutable::parse('2026-04-25', 'Asia/Kolkata'),
            23.2472446,
            69.668339,
            'Asia/Kolkata',
            0.0,
            null,
            'amanta',
        );

        $vaishakhaNames = array_map(
            static fn (array $festival): string => (string) ($festival['resolution']['festival_name'] ?? $festival['name'] ?? ''),
            $vaishakhaDetails['Festivals'] ?? []
        );

        $chaitraDetails = $service->getDayDetails(
            CarbonImmutable::parse('2026-03-27', 'Asia/Kolkata'),
            23.2472446,
            69.668339,
            'Asia/Kolkata',
            0.0,
            null,
            'amanta',
        );

        $chaitraNames = array_map(
            static fn (array $festival): string => (string) ($festival['resolution']['festival_name'] ?? $festival['name'] ?? ''),
            $chaitraDetails['Festivals'] ?? []
        );

        $this->assertContains('Hari Jayanti', $vaishakhaNames, 'Monthly Hari Jayanti should emit outside Chaitra on the verified Vaishakha Sud 9 date (2026-04-25).');
        $this->assertContains('Swaminarayan Jayanti (Hari-Nom)', $chaitraNames, 'Annual Chaitra Hari-Nom should stay on the annual Chaitra observance date (2026-03-27).');
        $this->assertNotContains('Hari Jayanti', $chaitraNames, 'Monthly Hari Jayanti must not duplicate the annual Chaitra Hari-Nom observance.');
    }

    public function test_gujarat_2026_festival_dates_match_verified_public_baselines(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);

        $calendar = $service->getFestivalYearCalendar(
            2026,
            23.2472446,
            69.668339,
            'Asia/Kolkata',
            0.0,
            null,
            'amanta',
        );

        $datesByName = [];
        foreach (($calendar['flat'] ?? []) as $entry) {
            $festival = (array) ($entry['festival'] ?? []);
            $name = (string) ($festival['name_key'] ?? $festival['name'] ?? $festival['resolution']['festival_name'] ?? '');
            if ($name === '') {
                continue;
            }

            $datesByName[$name][] = (string) ($entry['date'] ?? '');
        }

        $this->assertContains('2026-11-10', $datesByName['Govardhan Puja'] ?? [], 'Govardhan Puja should be emitted with the Kartika Shukla Pratipada observance.');
        $this->assertNotContains('2026-12-08', $datesByName['Govardhan Puja'] ?? [], 'Govardhan Puja must not leak into the next lunar month.');
        $this->assertSame(['2026-07-23'], array_values(array_unique($datesByName['Ashadha Gupt Navaratri Day 9'] ?? [])));
        $this->assertSame(['2026-03-04'], array_values(array_unique($datesByName['Phuldolotsava'] ?? [])));
        $this->assertSame(['2026-03-02'], array_values(array_unique($datesByName['Holika Dahan'] ?? [])));
        $this->assertSame(['2026-03-31'], array_values(array_unique($datesByName['Mahavir Jayanti'] ?? [])));
        $this->assertSame(['2026-09-12'], array_values(array_unique($datesByName['Samaveda Upakarma'] ?? [])));
        $this->assertSame(['2026-02-15'], array_values(array_unique($datesByName['Maha Shivaratri'] ?? [])));
        $this->assertNotContains('2026-02-16', array_values(array_unique($datesByName['Masik Shivaratri'] ?? [])));
        $this->assertSame(['2026-10-20'], array_values(array_unique($datesByName['Dussehra'] ?? [])));
        $this->assertContains('2026-09-14', array_values(array_unique($datesByName['Vinayaka Chaturthi'] ?? [])));
        $this->assertSame(['2026-03-06'], array_values(array_unique($datesByName['Bhalachandra Sankashti Chaturthi'] ?? [])));
        $this->assertSame(['2026-12-26'], array_values(array_unique($datesByName['Akhuratha Sankashti Chaturthi'] ?? [])));
        $this->assertContains('2026-04-28', array_values(array_unique($datesByName['Bhauma Pradosh Vrat'] ?? [])));
        $this->assertNotContains('2026-03-17', array_values(array_unique($datesByName['Bhauma Pradosh Vrat'] ?? [])));
        $this->assertNotContains('2026-12-22', array_values(array_unique($datesByName['Bhauma Pradosh Vrat'] ?? [])));
        $this->assertContains('2026-05-28', array_values(array_unique($datesByName['Guru Pradosh Vrat'] ?? [])));
        $this->assertArrayNotHasKey('Ganesh Chaturthi', $datesByName);
        $this->assertArrayNotHasKey('Siddhivinayaka Chaturthi', $datesByName);
    }

    public function test_calculation_basis_only_emits_applicable_truth_table_flags(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);

        $calendar = $service->getFestivalYearCalendar(
            2026,
            23.2472446,
            69.668339,
            'Asia/Kolkata',
            0.0,
            null,
            'amanta',
        );

        $byName = [];
        foreach (($calendar['flat'] ?? []) as $entry) {
            $festival = (array) ($entry['festival'] ?? []);
            $name = (string) ($festival['name_key'] ?? $festival['name'] ?? $festival['resolution']['festival_name'] ?? '');
            if ($name !== '') {
                $byName[$name] = $festival;
            }
        }

        $paushaPurnimaBasis = (array) ($byName['Pausha Purnima']['calculation_basis'] ?? []);
        self::assertArrayNotHasKey('ekadashi_nirnay_table', $paushaPurnimaBasis);
        self::assertArrayNotHasKey('pradosh_truth_table', $paushaPurnimaBasis);
        self::assertArrayNotHasKey('sankashti_truth_table', $paushaPurnimaBasis);

        self::assertTrue($byName['Bhalachandra Sankashti Chaturthi']['calculation_basis']['sankashti_truth_table'] ?? false);
        self::assertTrue($byName['Bhauma Pradosh Vrat']['calculation_basis']['pradosh_truth_table'] ?? false);
    }

    public function test_purnima_vrat_and_civil_festival_labels_are_split(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);

        $calendar = $service->getFestivalYearCalendar(
            2026,
            23.2472446,
            69.668339,
            'Asia/Kolkata',
            0.0,
            null,
            'amanta',
        );

        $datesByName = [];
        foreach (($calendar['flat'] ?? []) as $entry) {
            $festival = (array) ($entry['festival'] ?? []);
            $name = (string) ($festival['resolution']['festival_name'] ?? $festival['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $datesByName[$name][] = (string) ($entry['date'] ?? '');
        }

        self::assertSame(['2026-01-03'], array_values(array_unique($datesByName['Pausha Purnima Vrat'] ?? [])));
        self::assertSame(['2026-01-03'], array_values(array_unique($datesByName['Pausha Purnima'] ?? [])));
        self::assertSame(['2026-04-01'], array_values(array_unique($datesByName['Chaitra Purnima Vrat'] ?? [])));
        self::assertSame(['2026-04-02'], array_values(array_unique($datesByName['Chaitra Purnima'] ?? [])));
        self::assertSame(['2026-07-29'], array_values(array_unique($datesByName['Ashadha Purnima Vrat'] ?? [])));
        self::assertSame(['2026-07-29'], array_values(array_unique($datesByName['Ashadha Purnima'] ?? [])));
    }

    public function test_nag_panchami_uses_calendar_specific_shravana_dates(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);

        $datesByCalendar = [];
        foreach (['amanta', 'purnimanta'] as $calendarType) {
            $calendar = $service->getFestivalYearCalendar(
                2026,
                23.2472446,
                69.668339,
                'Asia/Kolkata',
                0.0,
                null,
                $calendarType,
            );

            foreach (($calendar['flat'] ?? []) as $entry) {
                $festival = (array) ($entry['festival'] ?? []);
                $name = (string) ($festival['resolution']['festival_name'] ?? $festival['name'] ?? '');
                if ($name === 'Nag Panchami') {
                    $datesByCalendar[$calendarType][] = (string) ($entry['date'] ?? '');
                }
            }
        }

        self::assertSame(['2026-09-01'], array_values(array_unique($datesByCalendar['amanta'] ?? [])));
        self::assertSame(['2026-08-17'], array_values(array_unique($datesByCalendar['purnimanta'] ?? [])));
    }

    #[Override]
    protected function getPackageProviders($app): array
    {
        return [PanchangServiceProvider::class];
    }
}
