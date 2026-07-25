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
class FestivalDateCorrectionsTest extends TestCase
{
    private const float LAT = 23.2472446;

    private const float LON = 69.668339;

    private const string TZ = 'Asia/Kolkata';

    public function test_verified_festival_dates_oct_2025_to_mar_2027(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);

        $cases = [
            ['purnimanta', '2026-07-19', 'Bonalu (Ashadha Sunday)'],
            ['purnimanta', '2026-07-26', 'Bonalu (Ashadha Sunday)'],
            ['purnimanta', '2026-08-02', 'Bonalu (Ashadha Sunday)'],
            ['purnimanta', '2026-08-09', 'Bonalu (Ashadha Sunday)'],
        ];

        foreach (['amanta', 'purnimanta'] as $calendar) {
            $cases[] = [$calendar, '2025-10-20', 'Chopda Pujan'];
            $cases[] = [$calendar, '2025-10-20', 'Lakshmi Puja (Deepavali)'];
            $cases[] = [$calendar, '2025-10-22', 'Bali Pratipada'];
            $cases[] = [$calendar, '2025-10-22', 'Govardhan Puja'];
            $cases[] = [$calendar, '2025-11-02', 'Devutthana (Prabodhini) Ekadashi'];
            $cases[] = [$calendar, '2025-11-17', 'Mandala Pooja Begins'];
            $cases[] = [$calendar, '2025-12-04', 'Karthigai Deepam'];
            $cases[] = [$calendar, '2025-12-27', 'Mandala Pooja'];
            $cases[] = [$calendar, '2026-01-03', 'Arudra Darshan'];
            $cases[] = [$calendar, '2026-01-13', 'Bhogi Pandigai'];
            $cases[] = [$calendar, '2026-01-13', 'Lohri'];
            $cases[] = [$calendar, '2026-01-14', 'Ganga Sagar Mela'];
            $cases[] = [$calendar, '2026-01-14', 'Magh Bihu'];
            $cases[] = [$calendar, '2026-01-14', 'Makara Sankranti (Pongal)'];
            $cases[] = [$calendar, '2026-01-14', 'Makaravilakku'];
            $cases[] = [$calendar, '2026-01-15', 'Mattu Pongal'];
            $cases[] = [$calendar, '2026-01-15', 'Vasi Uttarayan'];
            $cases[] = [$calendar, '2026-03-03', 'Attukal Pongal'];
            $cases[] = [$calendar, '2026-03-04', 'Dhuleti'];
            $cases[] = [$calendar, '2026-03-13', 'Chapchar Kut'];
            $cases[] = [$calendar, '2026-04-19', 'Chandan Yatra Begins'];
            $cases[] = [$calendar, '2026-04-19', 'Parashurama Jayanti'];
            $cases[] = [$calendar, '2026-04-26', 'Thrissur Pooram'];
            $cases[] = [$calendar, '2026-05-27', 'ISKCON Ekadashi'];
            $cases[] = [$calendar, '2026-06-11', 'ISKCON Ekadashi'];
            $cases[] = [$calendar, '2026-07-11', 'ISKCON Ekadashi'];
            $cases[] = [$calendar, '2026-08-18', 'First Mangala Gauri Vrat'];
            $cases[] = [$calendar, '2026-08-27', 'Yajurveda Upakarma'];
            $cases[] = [$calendar, '2026-08-28', 'Gayatri Japam'];
            $cases[] = [$calendar, '2026-08-25', 'Second Mangala Gauri Vrat'];
            $cases[] = [$calendar, '2026-09-01', 'Third Mangala Gauri Vrat'];
            $cases[] = [$calendar, '2026-09-08', 'Fourth Mangala Gauri Vrat'];
            $cases[] = [$calendar, '2026-09-14', 'Vinayaka Chaturthi'];
            $cases[] = [$calendar, '2026-11-08', 'Chopda Pujan'];
            $cases[] = [$calendar, '2026-11-08', 'Lakshmi Puja (Deepavali)'];
            $cases[] = [$calendar, '2026-11-09', 'Bali Pratipada'];
            $cases[] = [$calendar, '2026-11-09', 'Govardhan Puja'];
            $cases[] = [$calendar, '2026-11-17', 'Mandala Pooja Begins'];
            $cases[] = [$calendar, '2026-11-21', 'Devutthana (Prabodhini) Ekadashi'];
            $cases[] = [$calendar, '2026-11-24', 'Karthigai Deepam'];
            $cases[] = [$calendar, '2026-12-24', 'Arudra Darshan'];
            $cases[] = [$calendar, '2026-12-27', 'Mandala Pooja'];
            $cases[] = [$calendar, '2027-01-14', 'Bhogi Pandigai'];
            $cases[] = [$calendar, '2027-01-14', 'Lohri'];
            $cases[] = [$calendar, '2027-01-15', 'Ganga Sagar Mela'];
            $cases[] = [$calendar, '2027-01-15', 'Magh Bihu'];
            $cases[] = [$calendar, '2027-01-15', 'Makara Sankranti (Pongal)'];
            $cases[] = [$calendar, '2027-01-15', 'Makaravilakku'];
            $cases[] = [$calendar, '2027-01-16', 'Mattu Pongal'];
            $cases[] = [$calendar, '2027-01-16', 'Vasi Uttarayan'];
            $cases[] = [$calendar, '2027-03-22', 'Dhuleti'];
            $cases[] = [$calendar, '2027-10-30', 'Bali Pratipada'];
            $cases[] = [$calendar, '2027-10-30', 'Govardhan Puja'];
        }

        foreach ($cases as [$calendar, $date, $expected]) {
            $details = $service->getDayDetails(
                CarbonImmutable::parse($date, self::TZ),
                self::LAT,
                self::LON,
                self::TZ,
                0.0,
                null,
                $calendar,
            );

            $names = array_map(
                static fn (array $festival): string => (string) ($festival['resolution']['festival_name'] ?? $festival['name'] ?? ''),
                $details['Festivals'] ?? []
            );

            self::assertContains($expected, $names, $expected . ' on ' . $date . ' (' . $calendar . ')');
        }
    }

    public function test_spurious_festival_dates_are_suppressed(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);

        $reject = [
            ['amanta', '2027-03-23', 'Dhuleti'],
            ['purnimanta', '2026-10-10', 'Chopda Pujan'],
            ['amanta', '2026-01-27', 'Attukal Pongal'],
            ['amanta', '2026-02-23', 'Attukal Pongal'],
            ['purnimanta', '2026-01-27', 'Attukal Pongal'],
            ['purnimanta', '2026-02-23', 'Attukal Pongal'],
            ['amanta', '2026-03-03', 'Dhuleti'],
            ['purnimanta', '2026-03-03', 'Dhuleti'],
            ['amanta', '2027-02-13', 'Attukal Pongal'],
            ['amanta', '2027-03-13', 'Attukal Pongal'],
            ['purnimanta', '2027-02-13', 'Attukal Pongal'],
            ['purnimanta', '2027-03-13', 'Attukal Pongal'],
            ['amanta', '2027-01-21', 'Arudra Darshan'],
            ['purnimanta', '2027-01-21', 'Arudra Darshan'],
            ['amanta', '2025-11-20', 'Bali Pratipada'],
            ['amanta', '2025-11-20', 'Govardhan Puja'],
            ['purnimanta', '2025-11-20', 'Bali Pratipada'],
            ['purnimanta', '2025-11-20', 'Govardhan Puja'],
            ['amanta', '2025-11-05', 'Karthigai Deepam'],
            ['purnimanta', '2025-11-05', 'Karthigai Deepam'],
            ['amanta', '2026-09-01', 'First Mangala Gauri Vrat'],
            ['amanta', '2026-09-08', 'Second Mangala Gauri Vrat'],
            ['amanta', '2026-08-18', 'Third Mangala Gauri Vrat'],
            ['amanta', '2026-08-25', 'Fourth Mangala Gauri Vrat'],
        ];

        foreach ($reject as [$calendar, $date, $unexpected]) {
            $details = $service->getDayDetails(
                CarbonImmutable::parse($date, self::TZ),
                self::LAT,
                self::LON,
                self::TZ,
                0.0,
                null,
                $calendar,
            );

            $names = array_map(
                static fn (array $festival): string => (string) ($festival['resolution']['festival_name'] ?? $festival['name'] ?? ''),
                $details['Festivals'] ?? []
            );

            self::assertNotContains($unexpected, $names, $unexpected . ' must not appear on ' . $date . ' (' . $calendar . ')');
        }
    }

    public function test_verified_corrections_survive_year_calendar_exports(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);

        foreach (['amanta', 'purnimanta'] as $calendar) {
            $festival2025 = $service->getFestivalRangeCalendarOnlyFestivals(
                2025,
                10,
                2025,
                11,
                self::LAT,
                self::LON,
                self::TZ,
                0.0,
                null,
                $calendar,
            );
            $festival2026 = $service->getFestivalRangeCalendarOnlyFestivals(
                2026,
                4,
                2026,
                8,
                self::LAT,
                self::LON,
                self::TZ,
                0.0,
                null,
                $calendar,
            );
            $vrat2026 = $service->getVratRangeCalendar(
                2026,
                8,
                2026,
                9,
                self::LAT,
                self::LON,
                self::TZ,
                0.0,
                null,
                $calendar,
            );
            $festival2027Deepotsav = $service->getFestivalRangeCalendarOnlyFestivals(
                2027,
                10,
                2027,
                10,
                self::LAT,
                self::LON,
                self::TZ,
                0.0,
                null,
                $calendar,
            );

            self::assertSame(
                ['2025-10-22'],
                $this->datesForName($festival2025, 'Bali Pratipada'),
                sprintf('Bali Pratipada 2025 export (%s)', $calendar)
            );
            self::assertSame(
                ['2025-10-22'],
                $this->datesForName($festival2025, 'Govardhan Puja'),
                sprintf('Govardhan Puja 2025 export (%s)', $calendar)
            );
            self::assertSame(
                ['2027-10-30'],
                $this->datesForName(
                    $festival2027Deepotsav,
                    'Bali Pratipada',
                ),
                sprintf('Bali Pratipada 2027 export (%s)', $calendar)
            );
            self::assertSame(
                ['2027-10-30'],
                $this->datesForName(
                    $festival2027Deepotsav,
                    'Govardhan Puja',
                ),
                sprintf('Govardhan Puja 2027 export (%s)', $calendar)
            );
            self::assertSame(
                ['2026-08-28'],
                $this->datesForName($festival2026, 'Gayatri Japam'),
                sprintf('Gayatri Japam export (%s)', $calendar)
            );
            self::assertSame(
                ['2026-04-19'],
                $this->datesForName($festival2026, 'Chandan Yatra Begins'),
                sprintf('Chandan Yatra Begins export (%s)', $calendar)
            );
            self::assertSame(
                ['2026-04-19'],
                $this->datesForName($festival2026, 'Parashurama Jayanti'),
                sprintf('Parashurama Jayanti export (%s)', $calendar)
            );
            self::assertSame(
                ['2026-08-18'],
                $this->datesForName($vrat2026, 'First Mangala Gauri Vrat'),
                sprintf('First Mangala Gauri Vrat export (%s)', $calendar)
            );
            self::assertSame(
                ['2026-08-25'],
                $this->datesForName($vrat2026, 'Second Mangala Gauri Vrat'),
                sprintf('Second Mangala Gauri Vrat export (%s)', $calendar)
            );
            self::assertSame(
                ['2026-09-01'],
                $this->datesForName($vrat2026, 'Third Mangala Gauri Vrat'),
                sprintf('Third Mangala Gauri Vrat export (%s)', $calendar)
            );
            self::assertSame(
                ['2026-09-08'],
                $this->datesForName($vrat2026, 'Fourth Mangala Gauri Vrat'),
                sprintf('Fourth Mangala Gauri Vrat export (%s)', $calendar)
            );
        }

        self::assertSame(
            ['2026-08-17', '2026-08-24', '2026-08-31', '2026-09-07'],
            $this->datesForName(
                $service->getVratRangeCalendar(2026, 8, 2026, 9, self::LAT, self::LON, self::TZ, 0.0, null, 'amanta'),
                'Shravana Somvar (Monday Fasting)'
            ),
            'Amanta Shravana Somvar export'
        );
        self::assertSame(
            ['2026-08-03', '2026-08-10', '2026-08-17', '2026-08-24'],
            $this->datesForName(
                $service->getVratRangeCalendar(2026, 8, 2026, 9, self::LAT, self::LON, self::TZ, 0.0, null, 'purnimanta'),
                'Shravana Somvar (Monday Fasting)'
            ),
            'Purnimanta Shravana Somvar export'
        );
    }

    public function test_oct_2025_mar_2027_bhuj_audit_window_exports_latest_verified_dates(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);

        $expectedFestivals = [
            'Anvadhan' => [
                '2025-10-06', '2025-10-21', '2025-11-05', '2025-11-20', '2025-12-04', '2025-12-19',
                '2026-01-03', '2026-01-18', '2026-02-01', '2026-02-17', '2026-03-03', '2026-03-18',
                '2026-04-01', '2026-04-17', '2026-05-01', '2026-05-16', '2026-05-31', '2026-06-14',
                '2026-06-29', '2026-07-14', '2026-07-29', '2026-08-12', '2026-08-27', '2026-09-10',
                '2026-09-26', '2026-10-10', '2026-10-25', '2026-11-09', '2026-11-24', '2026-12-08',
                '2026-12-23', '2027-01-07', '2027-01-22', '2027-02-06', '2027-02-20', '2027-03-07',
                '2027-03-21',
            ],
            'Ishti' => [
                '2025-10-07', '2025-10-22', '2025-11-06', '2025-11-21', '2025-12-05', '2025-12-20',
                '2026-01-04', '2026-01-19', '2026-02-02', '2026-02-18', '2026-03-04', '2026-03-19',
                '2026-04-02', '2026-04-18', '2026-05-02', '2026-05-17', '2026-06-01', '2026-06-15',
                '2026-06-30', '2026-07-15', '2026-07-30', '2026-08-13', '2026-08-28', '2026-09-11',
                '2026-09-27', '2026-10-11', '2026-10-26', '2026-11-10', '2026-11-25', '2026-12-09',
                '2026-12-24', '2027-01-08', '2027-01-23', '2027-02-07', '2027-02-21', '2027-03-08',
                '2027-03-22',
            ],
            'Nand Mahotsav' => ['2026-09-05'],
            'Thai Poosam' => ['2026-02-01', '2027-01-22'],
            'Dayanand Saraswati Jayanti' => ['2026-03-13', '2027-03-02'],
            'Brahma Savarni Manvadi' => ['2025-12-30', '2027-02-13'],
            'Yashoda Jayanti' => ['2026-03-09', '2027-02-26'],
            'Shabari Jayanti' => ['2026-03-10', '2027-02-27'],
            'Janaki Jayanti' => ['2026-03-11', '2027-02-28'],
            'Sheetala Ashtami' => ['2026-03-11', '2027-03-29'],
            'Attukal Pongal' => ['2026-03-03', '2027-02-22'],
            'Chapchar Kut' => ['2026-03-13', '2027-03-05'],
        ];

        $expectedVrats = [
            'ISKCON Ekadashi' => [
                '2025-10-03', '2025-10-17', '2025-11-02', '2025-11-15', '2025-12-01', '2025-12-15',
                '2026-01-14', '2026-01-29', '2026-02-13', '2026-02-27', '2026-03-15',
                '2026-03-29', '2026-04-13', '2026-04-27', '2026-05-13', '2026-05-27', '2026-06-11',
                '2026-06-25', '2026-07-11', '2026-07-25', '2026-08-09', '2026-08-23', '2026-09-07',
                '2026-09-22', '2026-10-06', '2026-10-22', '2026-11-05', '2026-11-21', '2026-12-04',
                '2026-12-20', '2027-01-03', '2027-01-19', '2027-02-02', '2027-02-17', '2027-03-04',
                '2027-03-19',
            ],
            'Thai Pusam' => ['2026-02-01', '2027-01-22'],
            'Dwijapriya Sankashti Chaturthi' => ['2026-02-05', '2027-02-24'],
            'Bhanu Saptami' => ['2026-01-25', '2026-06-21', '2026-10-18', '2027-03-14'],
            'Kalashtami' => [
                '2025-10-14', '2025-11-12', '2025-12-12', '2026-01-11', '2026-02-09', '2026-03-11',
                '2026-04-10', '2026-05-10', '2026-07-08', '2026-08-06', '2026-09-04', '2026-10-03',
                '2026-11-02', '2026-12-01', '2026-12-31', '2027-01-29', '2027-02-28', '2027-03-29',
            ],
            'Chandra Darshana' => [
                '2025-10-23', '2025-11-21', '2025-12-21', '2026-01-20', '2026-02-18', '2026-03-20',
                '2026-04-18', '2026-06-16', '2026-07-15', '2026-08-14', '2026-09-12', '2026-10-12',
                '2026-11-10', '2026-12-10', '2027-01-09', '2027-02-08', '2027-03-09',
            ],
            'Masik Karthigai' => [
                '2025-10-10', '2025-11-06', '2025-12-04', '2025-12-31', '2026-01-27', '2026-02-23',
                '2026-03-23', '2026-04-19', '2026-05-16', '2026-06-13', '2026-07-10', '2026-08-07',
                '2026-09-03', '2026-09-30', '2026-10-27', '2026-11-24', '2026-12-21', '2027-01-18',
                '2027-02-14', '2027-03-13',
            ],
            'Rohini Vrat' => [
                '2025-10-11', '2025-11-07', '2025-12-05', '2026-01-01', '2026-01-28', '2026-02-25',
                '2026-03-24', '2026-04-20', '2026-05-18', '2026-06-14', '2026-07-12', '2026-08-08',
                '2026-09-04', '2026-10-01', '2026-10-29', '2026-11-25', '2026-12-23', '2027-01-19',
                '2027-02-15', '2027-03-15',
            ],
        ];

        foreach (['amanta', 'purnimanta'] as $calendar) {
            $festivals = $service->getFestivalRangeCalendarOnlyFestivals(2025, 10, 2027, 3, self::LAT, self::LON, self::TZ, 0.0, null, $calendar);
            $vrats = $service->getVratRangeCalendar(2025, 10, 2027, 3, self::LAT, self::LON, self::TZ, 0.0, null, $calendar);

            foreach ($expectedFestivals as $name => $dates) {
                self::assertSame($dates, $this->datesForName($festivals, $name), sprintf('%s festival export (%s)', $name, $calendar));
            }

            foreach ($expectedVrats as $name => $dates) {
                self::assertSame($dates, $this->datesForName($vrats, $name), sprintf('%s vrat export (%s)', $name, $calendar));
            }
        }

        $purnimantaVrats = $service->getVratRangeCalendar(2027, 1, 2027, 1, self::LAT, self::LON, self::TZ, 0.0, null, 'purnimanta');
        self::assertSame(['2027-01-19'], $this->datesForName($purnimantaVrats, 'Pausha Putrada Ekadashi'));
    }

    public function test_chandra_darshana_uses_source_sensitive_first_crescent_algorithm(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);

        $vrats = $service->getVratRangeCalendar(2027, 2, 2027, 2, self::LAT, self::LON, self::TZ, 0.0, null, 'amanta');
        self::assertSame(['2027-02-08'], $this->datesForName($vrats, 'Chandra Darshana'));

        $entry = null;
        foreach ((array) ($vrats['by_date']['2027-02-08'] ?? []) as $festival) {
            if (($festival['name_key'] ?? $festival['name'] ?? '') === 'Chandra Darshana') {
                $entry = $festival;
                break;
            }
        }

        self::assertIsArray($entry);
        self::assertSame(2, $entry['resolution']['required_tithi'] ?? null);
        self::assertSame(
            'chandra_darshana_application_crescent_candidate',
            $entry['resolution']['decision']['winning_reason_key'] ?? null
        );
        self::assertSame(
            'application_definition_first_visible_crescent',
            $entry['resolution']['decision']['visibility_assessment']['date_selection_basis'] ?? null
        );
        self::assertSame(
            'modern_ecliptic_longitude_proxy_for_surya_siddhanta_12_bhaga_indication',
            $entry['resolution']['decision']['visibility_assessment']['astronomical_basis'] ?? null
        );
        self::assertSame(
            'UNKNOWN',
            $entry['resolution']['decision']['visibility_assessment']['actual_observation'] ?? null
        );
        self::assertFalse(
            $entry['resolution']['decision']['visibility_assessment']['forbidden_modern_thresholds_applied'] ?? true
        );
    }

    #[Override]
    protected function getPackageProviders($app): array
    {
        return [PanchangServiceProvider::class];
    }

    /**
     * @param array<string, mixed> $calendar
     *
     * @return array<int, string>
     */
    private function datesForName(array $calendar, string $name): array
    {
        $dates = [];

        foreach ((array) ($calendar['by_date'] ?? []) as $date => $entries) {
            foreach ((array) $entries as $entry) {
                $entryName = (string) (
                    $entry['name_key']
                    ?? $entry['name']
                    ?? $entry['resolution']['festival_name_key']
                    ?? $entry['resolution']['festival_name']
                    ?? ''
                );

                if ($entryName === $name) {
                    $dates[] = (string) $date;
                }
            }
        }

        $dates = array_values(array_unique($dates));
        sort($dates);

        return $dates;
    }
}
