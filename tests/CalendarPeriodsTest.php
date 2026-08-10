<?php

namespace JayeshMepani\PanchangCore\Tests;

use JayeshMepani\PanchangCore\Panchanga\PanchangService;
use JayeshMepani\PanchangCore\PanchangServiceProvider;
use Orchestra\Testbench\TestCase;
use Throwable;

final class CalendarPeriodsTest extends TestCase
{
    public function test_calendar_period_windows_provide_both_astronomical_and_civil_observance_dates(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);
        $res = $service->getCalendarPeriodWindowsRange(
            2026,
            3,
            2027,
            11,
            23.2472446,
            69.668339,
            'Asia/Kolkata',
            [],
            0.0
        );

        // ── Key presence ───────────────────────────────────────────────────────
        $this->assertArrayHasKey('gujarati_samvat_windows', $res);
        $this->assertArrayHasKey('samvatsara_brihaspati_windows', $res);
        $this->assertArrayHasKey('kali_samvat_windows', $res);
        // Renamed from saka_samvat_windows
        $this->assertArrayHasKey('shalivahana_saka_samvat_windows', $res);

        // ── Gujarati Samvat 2082 (Pingala) ─────────────────────────────────────
        // Astronomical: 21 Oct 2025 17:55 (after sunrise) → civil 22 Oct 2025 (BAPS / Drik)
        $g2082 = array_values(array_filter($res['gujarati_samvat_windows'], fn(array $w): bool => ($w['year'] ?? null) === 2082))[0];
        $this->assertSame('Pingala', $g2082['name']);
        $this->assertSame('21/10/2025 05:55:04 PM', $g2082['astronomical_start_iso']);
        $this->assertSame('2025-10-22', $g2082['civil_start_date']);
        // calendar_effective_end_date is inclusive last civil day (day before 2083 starts)
        $this->assertSame('2026-11-09', $g2082['calendar_effective_end_date']);
        $this->assertSame('kartika_shukla_pratipada_at_sunrise', $g2082['assignment_rule']);

        // ── Gujarati Samvat 2083 (Kalayukti) ──────────────────────────────────
        // Astronomical: 09 Nov 2026 12:32 (after sunrise) → civil 10 Nov 2026 (BAPS)
        $g2083 = array_values(array_filter($res['gujarati_samvat_windows'], fn(array $w): bool => ($w['year'] ?? null) === 2083))[0];
        $this->assertSame('Kalayukti', $g2083['name']);
        $this->assertSame('09/11/2026 12:32:00 PM', $g2083['astronomical_start_iso']);
        $this->assertSame('2026-11-10', $g2083['civil_start_date']);

        // ── Samvatsara Gujarati 2083 mirrors Gujarati Samvat ──────────────────
        $sg2083 = array_values(array_filter($res['samvatsara_gujarati_windows'], fn(array $w): bool => ($w['year'] ?? null) === 2083))[0];
        $this->assertSame('Kalayukti', $sg2083['name']);

        // ── Śālivāhana Śaka lunisolar 1948 (Parabhava / Ugadi 2026) ───────────
        // Astronomical: 19 Mar 2026 06:53 (before sunrise) → civil 19 Mar 2026 (Drik)
        $saka1948 = array_values(array_filter($res['shalivahana_saka_samvat_windows'], fn(array $w): bool => ($w['year'] ?? null) === 1948))[0];
        $this->assertSame('Parabhava', $saka1948['name']);
        $this->assertSame('2026-03-19', $saka1948['civil_start_date']);
        $this->assertSame('chaitra_shukla_pratipada_at_sunrise', $saka1948['assignment_rule']);

        // ── Kali Samvat is a pure linear era count ────────────────────────────
        $kali5127 = array_values(array_filter($res['kali_samvat_windows'], fn(array $w): bool => ($w['year'] ?? null) === 5127))[0];
        $this->assertSame('5127', $kali5127['name']);
        $this->assertSame(5127, $kali5127['year']);
        $this->assertSame(5127, $kali5127['era_year']);

        // ── Bārhaspatya mean-sign Samvatsara: continuous transit — no civil midnight fields ──
        $brihaspati = $res['samvatsara_brihaspati_windows'][0] ?? null;
        $this->assertNotNull($brihaspati);
        $this->assertSame('continuous_barhaspatya_mean_transit', $brihaspati['assignment_rule']);
        $this->assertArrayHasKey('astronomical_start_iso', $brihaspati);
        $this->assertArrayNotHasKey('civil_start_date', $brihaspati);
        $this->assertArrayNotHasKey('civil_start_iso', $brihaspati);
        $this->assertArrayNotHasKey('civil_end_date', $brihaspati);
        $this->assertArrayNotHasKey('civil_end_iso', $brihaspati);
        $this->assertSame('classical_ss', $brihaspati['brihaspati_model'] ?? null);

        // Modern ephemeris comparison is opt-in (not in the empty-fields default set).
        // Wiring regression: MODEL_MODERN_EPHEMERIS must reach getModernInfoFromJd().
        // If kernels are incomplete the modern path still fails distinctly (not silent classical).
        try {
            $both = $service->getCalendarPeriodWindowsRange(
                2026,
                3,
                2027,
                11,
                23.2472446,
                69.668339,
                'Asia/Kolkata',
                [
                    'samvatsara_brihaspati',
                    'samvatsara_brihaspati_modern',
                ],
                0.0
            );
        } catch (Throwable $throwable) {
            $message = $throwable->getMessage();
            $this->assertTrue(
                str_contains($message, 'Jupiter')
                    || str_contains($message, 'modern')
                    || str_contains($message, 'ephemeris')
                    || str_contains($message, 'Astronomy'),
                'Modern path must be selected; unexpected error: ' . $message
            );

            return;
        }

        $this->assertArrayHasKey('samvatsara_brihaspati_windows', $both);
        $this->assertArrayHasKey('samvatsara_brihaspati_modern_windows', $both);

        $modern = $both['samvatsara_brihaspati_modern_windows'][0] ?? null;
        $this->assertNotNull($modern);
        $this->assertSame(
            'continuous_barhaspatya_modern_jupiter_ingress',
            $modern['assignment_rule']
        );
        $this->assertSame('modern_ephemeris', $modern['brihaspati_model'] ?? null);
        $this->assertSame('modern_ephemeris', $modern['brihaspati_model_family'] ?? null);
        $this->assertStringContainsString(
            'modern_ephemeris',
            (string) ($modern['brihaspati_model_variant'] ?? '')
        );
        $this->assertStringContainsString(
            'jupiter',
            strtolower((string) ($modern['brihaspati_timing_basis'] ?? ''))
        );
        $this->assertArrayHasKey('brihaspati_name_phase_source', $modern);

        $classicalStarts = array_map(
            static fn(array $w): string => (string) ($w['astronomical_start_iso'] ?? $w['start_iso'] ?? ''),
            $both['samvatsara_brihaspati_windows']
        );
        $modernStarts = array_map(
            static fn(array $w): string => (string) ($w['astronomical_start_iso'] ?? $w['start_iso'] ?? ''),
            $both['samvatsara_brihaspati_modern_windows']
        );
        $this->assertNotSame(
            $classicalStarts,
            $modernStarts,
            'Modern Brihaspati windows must not be identical to classical (model was likely discarded).'
        );
    }

    protected function getPackageProviders($app): array
    {
        return [PanchangServiceProvider::class];
    }
}
