<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Tests;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Traits\CliBootstrap;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('slow')]
final class EclipseBhujVisibilityRegressionTest extends TestCase
{
    private const float LAT = 23.2472446;

    private const float LON = 69.668339;

    private const string TZ = 'Asia/Kolkata';

    /** @var array<string, array{sutak_start:string, relaxed_start:string, sutak_end:string}> */
    private const array EXPECTED_SUTAK_TIMES = [
        '2018-01-31|Lunar|Total' => [
            'sutak_start' => '31/01/2018 10:18:00 AM',
            'relaxed_start' => '31/01/2018 03:51:00 PM',
            'sutak_end' => '31/01/2018 08:41:00 PM',
        ],
        '2018-07-28|Lunar|Total' => [
            'sutak_start' => '27/07/2018 12:58:00 PM',
            'relaxed_start' => '27/07/2018 07:36:00 PM',
            'sutak_end' => '28/07/2018 03:50:00 AM',
        ],
        '2019-07-17|Lunar|Partial' => [
            'sutak_start' => '16/07/2019 04:19:00 PM',
            'relaxed_start' => '16/07/2019 10:19:00 PM',
            'sutak_end' => '17/07/2019 04:30:00 AM',
        ],
        '2019-12-26|Solar|Partial' => [
            'sutak_start' => '25/12/2019 06:13:00 PM',
            'relaxed_start' => '26/12/2019 04:11:00 AM',
            'sutak_end' => '26/12/2019 10:45:00 AM',
        ],
        '2020-06-21|Solar|Partial' => [
            'sutak_start' => '20/06/2020 10:17:00 PM',
            'relaxed_start' => '21/06/2020 06:06:00 AM',
            'sutak_end' => '21/06/2020 01:22:00 PM',
        ],
        '2022-10-25|Solar|Partial' => [
            'sutak_start' => '25/10/2022 03:44:00 AM',
            'relaxed_start' => '25/10/2022 12:35:00 PM',
            'sutak_end' => '25/10/2022 06:18:00 PM',
        ],
        '2022-11-08|Lunar|Partial' => [
            'sutak_start' => '08/11/2022 09:48:00 AM',
            'relaxed_start' => '08/11/2022 03:22:00 PM',
            'sutak_end' => '08/11/2022 06:18:00 PM',
        ],
        '2023-10-29|Lunar|Partial' => [
            'sutak_start' => '28/10/2023 03:25:00 PM',
            'relaxed_start' => '28/10/2023 09:26:00 PM',
            'sutak_end' => '29/10/2023 02:22:00 AM',
        ],
        '2025-09-07|Lunar|Total' => [
            'sutak_start' => '07/09/2025 12:49:00 PM',
            'relaxed_start' => '07/09/2025 07:03:00 PM',
            'sutak_end' => '08/09/2025 01:26:00 AM',
        ],
    ];

    public function testBhujVisibleEclipseSetMatchesVerifiedNineBetween2018And2025(): void
    {
        CliBootstrap::init(dirname(__DIR__));
        $service = CliBootstrap::makeEclipseService();

        $all = [];
        for ($year = 2018; $year <= 2025; $year++) {
            $events = $service->getEclipsesForYear($year, self::LAT, self::LON, self::TZ);
            foreach ($events as $event) {
                $all[] = $event;
            }
        }

        $visible = array_values(array_filter($all, static fn (array $event): bool => (bool) ($event['visibility']['visible'] ?? false)));
        $sutakApplicable = array_values(array_filter($all, static fn (array $event): bool => (bool) ($event['sutak']['applicable'] ?? false)));

        self::assertCount(9, $visible, 'Expected exactly 9 visible eclipses in Bhuj from 2018-01-01 to 2025-12-31.');
        self::assertCount(9, $sutakApplicable, 'Expected sutak applicable only for those 9 visible eclipses.');

        $visibleKeySet = [];
        foreach ($visible as $event) {
            $visibleKeySet[$this->eventKey($event)] = true;
        }

        $expectedVisibleKeys = [
            '2018-01-31|Lunar|Total',
            '2018-07-28|Lunar|Total',
            '2019-07-17|Lunar|Partial',
            '2019-12-26|Solar|Partial',
            '2020-06-21|Solar|Partial',
            '2022-10-25|Solar|Partial',
            '2022-11-08|Lunar|Partial',
            '2023-10-29|Lunar|Partial',
            '2025-09-07|Lunar|Total',
        ];

        sort($expectedVisibleKeys);
        $actualVisibleKeys = array_keys($visibleKeySet);
        sort($actualVisibleKeys);

        self::assertSame($expectedVisibleKeys, $actualVisibleKeys, 'Visible eclipse set mismatch against Eclipse.txt baseline.');

        foreach ($all as $event) {
            $this->assertChronologicalContacts($event);
            $this->assertVisibilitySemantics($event);
            $this->assertSutakBaseline($event);
        }
    }

    private function eventKey(array $event): string
    {
        return sprintf(
            '%s|%s|%s',
            (string) ($event['date'] ?? ''),
            (string) ($event['type'] ?? ''),
            (string) ($event['eclipse_type'] ?? '')
        );
    }

    /** @param array<string, mixed> $event */
    private function assertChronologicalContacts(array $event): void
    {
        foreach (['contacts', 'global_contacts'] as $contactGroup) {
            if (!isset($event[$contactGroup]) || !is_array($event[$contactGroup])) {
                continue;
            }

            $this->assertOrderedJdFields(
                $event[$contactGroup],
                $this->eventKey($event) . ' ' . $contactGroup
            );
        }

        $window = (array) ($event['visibility']['window'] ?? []);
        if ($window !== []) {
            $this->assertOrderedJdFields($window, $this->eventKey($event) . ' visibility.window');
        }
    }

    /** @param array<string, mixed> $values */
    private function assertOrderedJdFields(array $values, string $label): void
    {
        $previous = null;
        foreach ($values as $key => $value) {
            if (!str_ends_with($key, '_jd') || $value === null) {
                continue;
            }

            if (is_array($value)) {
                $this->assertArrayHasKey('jd', $value, $label . ' ' . $key);
                $value = $value['jd'];
            }

            $this->assertIsFloat($value, $label . ' ' . $key);
            if ($previous !== null) {
                $this->assertGreaterThanOrEqual($previous, $value, $label . ' ' . $key);
            }

            $previous = $value;
        }
    }

    /** @param array<string, mixed> $event */
    private function assertVisibilitySemantics(array $event): void
    {
        $window = (array) ($event['visibility']['window'] ?? []);
        $sutak = (array) ($event['sutak'] ?? []);
        $contacts = (array) ($event['contacts'] ?? []);

        if (($event['type'] ?? null) === 'Lunar' && ($event['visibility']['visible'] ?? false) === true) {
            $partialEnd = $this->extractJd($contacts['partial_end_jd'] ?? null);
            $windowEnd = $window['end_jd'] ?? null;

            $this->assertIsFloat($windowEnd, $this->eventKey($event) . ' lunar visibility.window.end_jd');
            $this->assertIsFloat($partialEnd, $this->eventKey($event) . ' lunar contacts.partial_end_jd');
            $this->assertLessThanOrEqual($partialEnd + 1e-6, $windowEnd, $this->eventKey($event) . ' lunar visibility window should not extend beyond partial end');
            $this->assertEqualsWithDelta($windowEnd, $sutak['end_jd'] ?? null, 1e-6, $this->eventKey($event) . ' lunar sutak end should equal visibility window end');
        }

        if (($event['type'] ?? null) === 'Solar' && ($event['visibility']['visible'] ?? false) === true) {
            $contactMaximum = $contacts['maximum_jd']['time'] ?? null;
            $this->assertSame($event['datetime'] ?? null, $contactMaximum, $this->eventKey($event) . ' solar datetime should match contacts.maximum_jd');
            $this->assertEqualsWithDelta(
                $this->extractJd($contacts['maximum_jd'] ?? null),
                $this->extractJd($event['jd'] ?? null),
                1e-9,
                $this->eventKey($event) . ' solar jd should match contacts.maximum_jd'
            );
        }
    }

    /** @param array<string, mixed> $event */
    private function assertSutakBaseline(array $event): void
    {
        $key = $this->eventKey($event);
        $expected = self::EXPECTED_SUTAK_TIMES[$key] ?? null;
        if ($expected === null) {
            return;
        }

        $sutak = (array) ($event['sutak'] ?? []);
        $this->assertTimeClose($sutak['start'] ?? null, $expected['sutak_start'], 120, $key . ' sutak start');
        $this->assertTimeClose($sutak['relaxed_start'] ?? null, $expected['relaxed_start'], 120, $key . ' relaxed sutak start');
        $this->assertTimeClose($sutak['end'] ?? null, $expected['sutak_end'], 300, $key . ' sutak end');
    }

    private function assertTimeClose(mixed $actual, string $expected, int $deltaSeconds, string $label): void
    {
        $this->assertIsString($actual, $label);

        $actualTime = CarbonImmutable::createFromFormat('d/m/Y h:i:s A', $actual, self::TZ);
        $expectedTime = CarbonImmutable::createFromFormat('d/m/Y h:i:s A', $expected, self::TZ);

        $this->assertNotFalse($actualTime, $label . ' actual parse');
        $this->assertNotFalse($expectedTime, $label . ' expected parse');
        $this->assertLessThanOrEqual(
            $deltaSeconds,
            abs($actualTime->diffInSeconds($expectedTime, false)),
            $label
        );
    }

    private function extractJd(mixed $value): ?float
    {
        if (is_float($value)) {
            return $value;
        }

        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            $value = $value['jd'] ?? null;
        }

        return is_float($value) ? $value : null;
    }
}
