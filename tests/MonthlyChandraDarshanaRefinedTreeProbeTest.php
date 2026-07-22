<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Tests;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Astronomy\Math\TransitEngine;
use JayeshMepani\PanchangCore\Panchanga\PanchangService;
use JayeshMepani\PanchangCore\PanchangServiceProvider;
use Orchestra\Testbench\TestCase;
use Override;
use PHPUnit\Framework\Attributes\Group;

/**
 * EXPERIMENTAL probe — does NOT change production Chandra Darshana resolution.
 *
 * Implements the refined monthly Chandra Darśana decision tree as test-side logic:
 *  - Strict source-only mode → MONTHLY_DATE_TEXTUALLY_UNDETERMINED (no date list)
 *  - Operational calendar mode → earliest post-Amāvasyā evening that is
 *      APPLICATION_CRESCENT_CANDIDATE_WITH_NIBANDHA_INDICATION, else APPLICATION_CRESCENT_CANDIDATE
 *    provenance must remain application_definition_first_visible_crescent
 *
 * Scope: Bhuj, civil range 2025-10-01 … 2027-03-31.
 *
 * Forbidden imports (asserted):
 *  - 9-muhūrta Sud1/Sud2 package rule as śāstric monthly selector
 *  - 38-minute lag / 7°–9° elongation / 0.8% illumination as “Sūrya Siddhānta”
 *  - Govardhana pūrvaviddhā / Amāvasyā fallback as monthly date logic
 */
#[Group('slow')]
class MonthlyChandraDarshanaRefinedTreeProbeTest extends TestCase
{
    private const float LAT = 23.2472446;

    private const float LON = 69.668339;

    private const string TZ = 'Asia/Kolkata';

    private const string RANGE_START = '2025-10-01';

    private const string RANGE_END = '2027-03-31';

    /** Sūrya Siddhānta 10.1 modern 12-bhaga proxy threshold (degrees). */
    private const float PROXY_12_BHAGA_SEPARATION_DEG = 12.0;

    public function test_strict_source_only_mode_never_selects_monthly_date(): void
    {
        $result = $this->strictSourceOnlyMonthlyResult();

        self::assertSame('MONTHLY_DATE_TEXTUALLY_UNDETERMINED', $result['result']);
        self::assertNull($result['selected_date']);
        self::assertSame(
            'no_explicit_monthly_scriptural_date_command_in_seven_sources',
            $result['reason_key'],
        );
        self::assertFalse($result['imports_govardhan_nine_muhurta']);
        self::assertFalse($result['imports_package_sthula_sud1_sud2']);
    }

    public function test_operational_mode_yields_dates_oct2025_to_mar2027_with_provenance(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);

        $seasons = $this->discoverPostAmavasyaSeasons($service);
        self::assertCount(18, $seasons, 'Expected every post-Amāvasyā season in the Oct 2025-Mar 2027 window.');

        $operational = [];
        $package = $this->packageChandraDarshanaDates($service);

        foreach ($seasons as $season) {
            $row = $this->evaluateSeasonOperational($service, $season, $package);
            $operational[] = $row;

            self::assertContains(
                $row['strict_result'],
                ['MONTHLY_DATE_TEXTUALLY_UNDETERMINED'],
            );
            self::assertSame(
                'application_definition_first_visible_crescent',
                $row['date_selection_basis'],
            );
            self::assertSame(
                'modern_ecliptic_longitude_proxy_for_surya_siddhanta_12_bhaga_indication',
                $row['astronomical_basis'],
            );
            self::assertSame(
                'directed_moon_minus_sun_ecliptic_longitude_separation_at_local_sunset_checked_against_12_degree_proxy_threshold',
                $row['astronomical_computation_basis'],
            );
            self::assertFalse($row['claims_full_surya_siddhanta_chapter_10_recomputation']);
            self::assertSame(
                'nibandha_tithi_visibility_indication',
                $row['tithi_corroboration_basis'],
            );
            self::assertSame(
                'satsangijivan_childhood_samskara_analogy_only',
                $row['pradosha_basis'],
            );

            // Must not claim explicit śāstric monthly rule.
            self::assertNotSame('explicit_monthly_scriptural_rule', $row['date_selection_basis']);

            if ($row['operational_selected_date'] !== null) {
                self::assertContains(
                    $row['operational_classification'],
                    [
                        'APPLICATION_CRESCENT_CANDIDATE_WITH_NIBANDHA_INDICATION',
                        'APPLICATION_CRESCENT_CANDIDATE',
                    ],
                );
            }
        }

        $selected = array_values(array_filter(
            array_map(
                static fn (array $r): ?string => $r['operational_selected_date'],
                $operational,
            ),
            static fn (?string $d): bool => $d !== null,
        ));

        self::assertNotEmpty($selected, 'Operational mode should find at least some first-crescent evenings.');
        self::assertCount(18, $selected, 'Operational mode should resolve one first-crescent candidate per season.');

        fwrite(STDOUT, "\n=== Monthly Chandra Darśana refined tree probe (Bhuj) ===\n");
        fwrite(STDOUT, "Strict mode: every season → MONTHLY_DATE_TEXTUALLY_UNDETERMINED (no date list)\n");
        fwrite(STDOUT, "Operational mode (application definition: earliest application candidate):\n");
        foreach ($operational as $row) {
            fwrite(STDOUT, sprintf(
                "  after Amāvasyā≈%s → operational=%s [%s] | package_cd=%s | tithi_proxy=%s | elong=%.2f° lag_min=%s\n",
                $row['amavasya_anchor_date'],
                $row['operational_selected_date'] ?? 'NONE',
                $row['operational_classification'] ?? 'n/a',
                $row['package_cd_date'] ?? 'n/a',
                $row['tithi_proxy_on_selected'] === null ? 'n/a' : ((bool) $row['tithi_proxy_on_selected'] ? 'Y' : 'N'),
                $row['selected_elongation_deg'] ?? -1.0,
                $row['selected_lag_minutes'] === null ? 'n/a' : sprintf('%.1f', $row['selected_lag_minutes']),
            ));
        }

        fwrite(STDOUT, sprintf(
            "Totals: seasons=%d operational_dates=%d package_cd_in_range=%d\n",
            count($operational),
            count($selected),
            count($package),
        ));

        // Diff summary: operational vs package (informative; package uses different rule).
        $pkgOnly = array_values(array_diff($package, $selected));
        $opOnly = array_values(array_diff($selected, $package));
        $both = array_values(array_intersect($selected, $package));
        fwrite(STDOUT, sprintf(
            "Overlap with package CD: shared=%d package_only=%d operational_only=%d\n",
            count($both),
            count($pkgOnly),
            count($opOnly),
        ));
        if ($pkgOnly !== []) {
            fwrite(STDOUT, '  package_only: ' . implode(', ', $pkgOnly) . "\n");
        }

        if ($opOnly !== []) {
            fwrite(STDOUT, '  operational_only: ' . implode(', ', $opOnly) . "\n");
        }
    }

    public function test_probe_if_all_contextual_witnesses_are_used_as_hard_gates(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);

        $seasons = $this->discoverPostAmavasyaSeasons($service);
        self::assertCount(18, $seasons, 'Expected every post-Amavasya season in the Oct 2025-Mar 2027 window.');

        $rows = [];
        foreach ($seasons as $season) {
            $evenings = $this->scanEveningsAfterAmavasya($service, $season, 8);
            $selected = null;

            foreach ($evenings as $eve) {
                $allContextualGatesPass = $eve['has_post_sunset_horizon_window'] === true
                    && $eve['twelve_bhaga_proxy_passed'] === true
                    && $eve['tithi_proxy_applicable'] === true
                    && $eve['dvitiya_covers_full_aparahna_3_muhurtas'] === true
                    && $eve['dvitiya_covers_aparahna_through_sunset_6_muhurtas'] === true
                    && $eve['visibility_during_pradosha'] === true;

                if ($allContextualGatesPass) {
                    $selected = $eve;
                    break;
                }
            }

            $rows[] = [
                'amavasya_anchor_date' => $season['anchor_date'],
                'selected_date' => $selected['date'] ?? null,
                'selected_classification' => $selected['classification'] ?? null,
                'trail' => array_map(
                    static fn (array $e): array => [
                        'date' => $e['date'],
                        'tithi_proxy_applicable' => $e['tithi_proxy_applicable'],
                        'horizon' => $e['has_post_sunset_horizon_window'],
                        'twelve_bhaga' => $e['twelve_bhaga_proxy_passed'],
                        'aparahna_3' => $e['dvitiya_covers_full_aparahna_3_muhurtas'],
                        'six_muhurta' => $e['dvitiya_covers_aparahna_through_sunset_6_muhurtas'],
                        'pradosha' => $e['visibility_during_pradosha'],
                        'classification' => $e['classification'],
                    ],
                    $evenings,
                ),
            ];
        }

        $selectedDates = array_values(array_filter(
            array_map(
                static fn (array $r): ?string => $r['selected_date'],
                $rows,
            ),
            static fn (?string $date): bool => $date !== null,
        ));

        fwrite(STDOUT, "\n=== Strict all-contextual-witness gates probe (Bhuj, Oct 2025-Mar 2027) ===\n");
        fwrite(STDOUT, "Hard gates used: Pratipada/Dvitiya scope + post-sunset horizon + 12-degree proxy + Dvitiya full Aparahna + Dvitiya Aparahna-to-sunset + Pradosha overlap.\n");
        fwrite(STDOUT, "Important: strict source-only mode remains no-date; this is only a what-if operational probe.\n");
        foreach ($rows as $row) {
            fwrite(STDOUT, sprintf(
                "  after Amavasya≈%s → strict_all_gates=%s [%s]\n",
                $row['amavasya_anchor_date'],
                $row['selected_date'] ?? 'NONE',
                $row['selected_classification'] ?? 'n/a',
            ));
        }

        fwrite(STDOUT, sprintf(
            "Totals: seasons=%d strict_all_gates_dates=%d\n",
            count($rows),
            count($selectedDates),
        ));

        if ($selectedDates !== []) {
            fwrite(STDOUT, 'Dates: ' . implode(', ', $selectedDates) . "\n");
        }

        self::assertCount(18, $rows);
    }

    public function test_probe_strict_contextual_gate_failure_reasons(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);

        $seasons = $this->discoverPostAmavasyaSeasons($service);
        self::assertCount(18, $seasons, 'Expected every post-Amavasya season in the Oct 2025-Mar 2027 window.');

        $gateLabels = [
            'has_post_sunset_horizon_window' => 'post-sunset Moon window',
            'twelve_bhaga_proxy_passed' => '12-degree proxy',
            'dvitiya_covers_full_aparahna_3_muhurtas' => 'Dvitiya full Aparahna 3-muhurta',
            'dvitiya_covers_aparahna_through_sunset_6_muhurtas' => 'Dvitiya Aparahna-to-sunset 6-muhurta',
            'visibility_during_pradosha' => 'Pradosha overlap',
        ];
        $failureCounts = array_fill_keys(array_keys($gateLabels), 0);
        $candidateCounts = array_fill_keys(array_keys($gateLabels), 0);
        $seasonRows = [];

        foreach ($seasons as $season) {
            $evenings = array_values(array_filter(
                $this->scanEveningsAfterAmavasya($service, $season, 8),
                static fn (array $eve): bool => in_array((int) ($eve['tithi_index_abs'] ?? 0), [1, 2], true),
            ));

            $seasonPasses = false;
            $seasonFailures = [];
            foreach (array_keys($gateLabels) as $gate) {
                $seasonFailures[$gate] = true;
            }

            foreach ($evenings as $eve) {
                $allPass = true;
                foreach (array_keys($gateLabels) as $gate) {
                    if ((bool) ($eve[$gate] ?? false)) {
                        $seasonFailures[$gate] = false;
                    } else {
                        $allPass = false;
                    }
                }

                if ($allPass) {
                    $seasonPasses = true;
                }
            }

            foreach (array_keys($gateLabels) as $gate) {
                foreach ($evenings as $eve) {
                    if ((bool) ($eve[$gate] ?? false)) {
                        $candidateCounts[$gate]++;
                    }
                }

                if ($seasonFailures[$gate]) {
                    $failureCounts[$gate]++;
                }
            }

            $seasonRows[] = [
                'anchor' => $season['anchor_date'],
                'passes' => $seasonPasses,
                'failing_gates' => array_keys(array_filter($seasonFailures)),
                'candidate_trail' => array_map(
                    static fn (array $eve): string => sprintf(
                        '%s(tithi=%d horizon=%s 12deg=%s ap3=%s six=%s pradosha=%s)',
                        $eve['date'],
                        (int) ($eve['tithi_index_abs'] ?? 0),
                        (bool) $eve['has_post_sunset_horizon_window'] ? 'Y' : 'N',
                        (bool) $eve['twelve_bhaga_proxy_passed'] ? 'Y' : 'N',
                        (bool) $eve['dvitiya_covers_full_aparahna_3_muhurtas'] ? 'Y' : 'N',
                        (bool) $eve['dvitiya_covers_aparahna_through_sunset_6_muhurtas'] ? 'Y' : 'N',
                        (bool) $eve['visibility_during_pradosha'] ? 'Y' : 'N',
                    ),
                    $evenings,
                ),
            ];
        }

        fwrite(STDOUT, "\n=== Strict contextual gate failure analysis (Bhuj, Oct 2025-Mar 2027) ===\n");
        fwrite(STDOUT, "Per-gate candidate pass counts across Pratipada/Dvitiya evenings:\n");
        foreach ($gateLabels as $gate => $label) {
            fwrite(STDOUT, sprintf(
                "  %-45s candidates_pass=%2d | seasons_with_no_passing_candidate=%2d\n",
                $label,
                $candidateCounts[$gate],
                $failureCounts[$gate],
            ));
        }

        fwrite(STDOUT, "Per-season failing gates when all contextual gates are required:\n");
        foreach ($seasonRows as $row) {
            fwrite(STDOUT, sprintf(
                "  after Amavasya≈%s → %s | failing=%s\n",
                $row['anchor'],
                $row['passes'] ? 'PASS' : 'NONE',
                $row['failing_gates'] === [] ? 'none' : implode(', ', $row['failing_gates']),
            ));
            fwrite(STDOUT, '    ' . implode(' ; ', $row['candidate_trail']) . "\n");
        }

        self::assertCount(18, $seasonRows);
    }

    public function test_probe_aparahna_three_vs_six_muhurta_gate_impact(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);

        $seasons = $this->discoverPostAmavasyaSeasons($service);
        self::assertCount(18, $seasons, 'Expected every post-Amavasya season in the Oct 2025-Mar 2027 window.');

        $baseDates = [];
        $threeMuhurtaDates = [];
        $sixMuhurtaDates = [];
        $threeOnlyDates = [];
        $sixOnlyDates = [];

        foreach ($seasons as $season) {
            $evenings = array_values(array_filter(
                $this->scanEveningsAfterAmavasya($service, $season, 8),
                static fn (array $eve): bool => in_array((int) ($eve['tithi_index_abs'] ?? 0), [1, 2], true),
            ));

            $base = $this->firstEveningPassing($evenings, static fn (array $eve): bool => (bool) $eve['has_post_sunset_horizon_window']
                && (bool) $eve['twelve_bhaga_proxy_passed']
                && (bool) $eve['visibility_during_pradosha']);
            $three = $this->firstEveningPassing($evenings, static fn (array $eve): bool => (bool) $eve['has_post_sunset_horizon_window']
                && (bool) $eve['twelve_bhaga_proxy_passed']
                && (bool) $eve['visibility_during_pradosha']
                && (bool) $eve['dvitiya_covers_full_aparahna_3_muhurtas']);
            $six = $this->firstEveningPassing($evenings, static fn (array $eve): bool => (bool) $eve['has_post_sunset_horizon_window']
                && (bool) $eve['twelve_bhaga_proxy_passed']
                && (bool) $eve['visibility_during_pradosha']
                && (bool) $eve['dvitiya_covers_aparahna_through_sunset_6_muhurtas']);
            $threeOnly = $this->firstEveningPassing($evenings, static fn (array $eve): bool => (bool) $eve['dvitiya_covers_full_aparahna_3_muhurtas']);
            $sixOnly = $this->firstEveningPassing($evenings, static fn (array $eve): bool => (bool) $eve['dvitiya_covers_aparahna_through_sunset_6_muhurtas']);

            if ($base !== null) {
                $baseDates[] = $base['date'];
            }

            if ($three !== null) {
                $threeMuhurtaDates[] = $three['date'];
            }

            if ($six !== null) {
                $sixMuhurtaDates[] = $six['date'];
            }

            if ($threeOnly !== null) {
                $threeOnlyDates[] = $threeOnly['date'];
            }

            if ($sixOnly !== null) {
                $sixOnlyDates[] = $sixOnly['date'];
            }
        }

        fwrite(STDOUT, "\n=== Dvitiya Aparahna 3-muhurta vs 6-muhurta gate impact (Bhuj, Oct 2025-Mar 2027) ===\n");
        fwrite(STDOUT, sprintf(
            "Base gates only (scope + horizon + 12-degree + Pradosha): %d dates\n",
            count($baseDates),
        ));
        fwrite(STDOUT, sprintf(
            "Add 3-muhurta Aparahna gate: %d dates | cancelled=%d\n",
            count($threeMuhurtaDates),
            count($baseDates) - count($threeMuhurtaDates),
        ));
        fwrite(STDOUT, sprintf(
            "Add 6-muhurta Aparahna-to-sunset gate: %d dates | cancelled=%d\n",
            count($sixMuhurtaDates),
            count($baseDates) - count($sixMuhurtaDates),
        ));
        fwrite(STDOUT, sprintf(
            "3-muhurta-only seasons=%d dates=%s\n",
            count($threeOnlyDates),
            $threeOnlyDates === [] ? 'NONE' : implode(', ', $threeOnlyDates),
        ));
        fwrite(STDOUT, sprintf(
            "6-muhurta-only seasons=%d dates=%s\n",
            count($sixOnlyDates),
            $sixOnlyDates === [] ? 'NONE' : implode(', ', $sixOnlyDates),
        ));

        self::assertCount(18, $baseDates);
    }

    public function test_operational_mode_against_provided_drikpanchang_dates_apr2024_to_feb2028(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);

        $expected = [
            '2024-04-09',
            '2024-05-09',
            '2024-06-07',
            '2024-07-07',
            '2024-08-05',
            '2024-09-04',
            '2024-10-04',
            '2024-11-03',
            '2024-12-02',
            '2025-01-01',
            '2025-01-30',
            '2025-03-01',
            '2025-03-30',
            '2025-04-28',
            '2025-05-28',
            '2025-06-26',
            '2025-07-25',
            '2025-08-24',
            '2025-09-23',
            '2025-10-23',
            '2025-11-22',
            '2025-12-21',
            '2026-01-20',
            '2026-02-18',
            '2026-03-20',
            '2026-04-18',
            '2026-05-17',
            '2026-06-16',
            '2026-07-15',
            '2026-08-14',
            '2026-09-12',
            '2026-10-12',
            '2026-11-11',
            '2026-12-10',
            '2027-01-09',
            '2027-02-08',
            '2027-03-09',
            '2027-04-08',
            '2027-05-07',
            '2027-06-05',
            '2027-07-05',
            '2027-08-03',
            '2027-09-02',
            '2027-10-01',
            '2027-10-31',
            '2027-11-29',
            '2027-12-29',
            '2028-01-28',
            '2028-02-26',
        ];

        $seasons = $this->discoverPostAmavasyaSeasons($service, '2024-04-01', '2028-02-29');
        $operational = [];
        foreach ($seasons as $season) {
            $row = $this->evaluateSeasonOperational($service, $season, [], '2028-02-29');
            if ($row['operational_selected_date'] !== null) {
                $operational[] = $row['operational_selected_date'];
            }
        }

        $operational = array_values(array_unique($operational));
        sort($operational);

        $shared = array_values(array_intersect($expected, $operational));
        $expectedOnly = array_values(array_diff($expected, $operational));
        $probeOnly = array_values(array_diff($operational, $expected));

        fwrite(STDOUT, "\n=== Provided DrikPanchang Chandra Darśana list vs refined probe (Bhuj) ===\n");
        fwrite(STDOUT, sprintf(
            "expected=%d probe=%d shared=%d expected_only=%d probe_only=%d\n",
            count($expected),
            count($operational),
            count($shared),
            count($expectedOnly),
            count($probeOnly),
        ));
        if ($expectedOnly !== []) {
            fwrite(STDOUT, '  expected_only: ' . implode(', ', $expectedOnly) . "\n");
        }

        if ($probeOnly !== []) {
            fwrite(STDOUT, '  probe_only: ' . implode(', ', $probeOnly) . "\n");
        }

        self::assertCount(49, $expected);
    }

    public function test_classification_helpers_match_spec(): void
    {
        // Horizon + 12° + Aparāhna-3 → corroborated.
        self::assertSame(
            'APPLICATION_CRESCENT_CANDIDATE_WITH_NIBANDHA_INDICATION',
            $this->classifyEvening([
                'has_post_sunset_horizon_window' => true,
                'twelve_bhaga_proxy_passed' => true,
                'tithi_proxy_aparahna_3' => true,
                'tithi_proxy_applicable' => true,
            ]),
        );

        // Horizon + 12°, no tithi proxy → astronomical only.
        self::assertSame(
            'APPLICATION_CRESCENT_CANDIDATE',
            $this->classifyEvening([
                'has_post_sunset_horizon_window' => true,
                'twelve_bhaga_proxy_passed' => true,
                'tithi_proxy_aparahna_3' => false,
                'tithi_proxy_applicable' => true,
            ]),
        );

        // Proxy true but SS invisible → divergence.
        self::assertSame(
            'NIBANDHA_TITHI_INDICATION_ASTRONOMICAL_PROXY_DIVERGENCE',
            $this->classifyEvening([
                'has_post_sunset_horizon_window' => true,
                'twelve_bhaga_proxy_passed' => false,
                'tithi_proxy_aparahna_3' => true,
                'tithi_proxy_applicable' => true,
            ]),
        );

        self::assertSame(
            'TWELVE_BHAGA_PROXY_NOT_PASSED',
            $this->classifyEvening([
                'has_post_sunset_horizon_window' => true,
                'twelve_bhaga_proxy_passed' => false,
                'tithi_proxy_aparahna_3' => false,
                'tithi_proxy_applicable' => false,
            ]),
        );

        self::assertSame(
            'NO_POST_SUNSET_HORIZON_WINDOW',
            $this->classifyEvening([
                'has_post_sunset_horizon_window' => false,
                'twelve_bhaga_proxy_passed' => true,
                'tithi_proxy_aparahna_3' => false,
                'tithi_proxy_applicable' => false,
            ]),
        );
    }

    #[Override]
    protected function getPackageProviders($app): array
    {
        return [PanchangServiceProvider::class];
    }

    /**
     * @param list<array<string, mixed>> $evenings
     * @param callable(array<string, mixed>): bool $predicate
     *
     * @return array<string, mixed>|null
     */
    private function firstEveningPassing(array $evenings, callable $predicate): ?array
    {
        foreach ($evenings as $evening) {
            if ($predicate($evening)) {
                return $evening;
            }
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Strict / operational modes
    // -------------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function strictSourceOnlyMonthlyResult(): array
    {
        return [
            'result' => 'MONTHLY_DATE_TEXTUALLY_UNDETERMINED',
            'selected_date' => null,
            'reason_key' => 'no_explicit_monthly_scriptural_date_command_in_seven_sources',
            'imports_govardhan_nine_muhurta' => false,
            'imports_package_sthula_sud1_sud2' => false,
            'note' => 'Sūrya Siddhānta + tithi proxy may classify evenings; they do not authorize a date pick.',
        ];
    }

    /**
     * @param array{amavasya_end_jd: float, anchor_date: string} $season
     * @param list<string> $packageDates
     *
     * @return array<string, mixed>
     */
    private function evaluateSeasonOperational(
        PanchangService $service,
        array $season,
        array $packageDates = [],
        string $rangeEnd = self::RANGE_END,
    ): array {
        $strict = $this->strictSourceOnlyMonthlyResult();
        $evenings = $this->scanEveningsAfterAmavasya($service, $season, 8, $rangeEnd);

        // §11 operational calendar mode — single chronological pass:
        // if CORROBORATED → select; else if ASTRONOMICAL → select; else next evening.
        $selected = null;
        foreach ($evenings as $eve) {
            $cls = $eve['classification'];
            if ($cls === 'APPLICATION_CRESCENT_CANDIDATE_WITH_NIBANDHA_INDICATION'
                || $cls === 'APPLICATION_CRESCENT_CANDIDATE') {
                $selected = $eve;
                break;
            }
        }

        $packageNear = null;
        foreach ($packageDates as $pd) {
            $delta = abs(CarbonImmutable::parse($pd)->diffInDays(
                CarbonImmutable::parse($season['anchor_date']),
            ));
            if ($delta <= 5) {
                $packageNear = $pd;
                break;
            }
        }

        return [
            'amavasya_anchor_date' => $season['anchor_date'],
            'strict_result' => $strict['result'],
            'operational_selected_date' => $selected['date'] ?? null,
            'operational_classification' => $selected['classification'] ?? null,
            'tithi_proxy_on_selected' => $selected['tithi_proxy_aparahna_3'] ?? null,
            'selected_elongation_deg' => $selected['modern_directed_moon_sun_longitude_separation_at_local_sunset_degrees'] ?? null,
            'selected_lag_minutes' => $selected['lag_minutes'] ?? null,
            'pradosha_overlap_on_selected' => $selected['visibility_during_pradosha'] ?? null,
            'evening_trail' => array_map(
                static fn (array $e): array => [
                    'date' => $e['date'],
                    'classification' => $e['classification'],
                    'elong' => $e['modern_directed_moon_sun_longitude_separation_at_local_sunset_degrees'],
                    'proxy3' => $e['tithi_proxy_aparahna_3'],
                ],
                $evenings,
            ),
            'package_cd_date' => $packageNear,
            'date_selection_basis' => 'application_definition_first_visible_crescent',
            'astronomical_basis' => 'modern_ecliptic_longitude_proxy_for_surya_siddhanta_12_bhaga_indication',
            'astronomical_computation_basis' => 'directed_moon_minus_sun_ecliptic_longitude_separation_at_local_sunset_checked_against_12_degree_proxy_threshold',
            'claims_full_surya_siddhanta_chapter_10_recomputation' => false,
            'tithi_corroboration_basis' => 'nibandha_tithi_visibility_indication',
            'pradosha_basis' => 'satsangijivan_childhood_samskara_analogy_only',
        ];
    }

    // -------------------------------------------------------------------------
    // Season discovery & evening evaluation
    // -------------------------------------------------------------------------

    /** @return list<array{amavasya_end_jd: float, anchor_date: string}> */
    private function discoverPostAmavasyaSeasons(
        PanchangService $service,
        string $rangeStart = self::RANGE_START,
        string $rangeEnd = self::RANGE_END,
    ): array {
        $start = CarbonImmutable::parse($rangeStart, self::TZ);
        $end = CarbonImmutable::parse($rangeEnd, self::TZ);
        $seasons = [];
        $cache = [];

        for ($d = $start; $d->lessThanOrEqualTo($end); $d = $d->addDay()) {
            $day = $this->dayBundle($service, $d, $cache);
            $abs = (int) $day['tithi_index_abs'];

            // Seed A: Amāvasyā at sunrise → Amāvasyā ends at tithi_end_jd.
            if ($abs === 30) {
                $endJd = (float) $day['tithi_end_jd'];
                if ($endJd > 0.0) {
                    $key = sprintf('%.5F', $endJd);
                    $seasons[$key] = [
                        'amavasya_end_jd' => $endJd,
                        'anchor_date' => $d->toDateString(),
                    ];
                }
            }

            // Seed B: Pratipada at sunrise → Amāvasyā ended at tithi_start_jd (covers kṣaya /
            // Amāvasyā never udaya-vyāpinī cases that Seed A misses).
            if ($abs === 1) {
                $endJd = (float) $day['tithi_start_jd'];
                if ($endJd > 0.0) {
                    $key = sprintf('%.5F', $endJd);
                    // Anchor on previous civil day if Amāvasyā ended before today's sunrise.
                    $anchor = $endJd < (float) $day['sunrise_jd']
                        ? $d->subDay()->toDateString()
                        : $d->toDateString();
                    if ($anchor >= $rangeStart) {
                        $seasons[$key] = [
                            'amavasya_end_jd' => $endJd,
                            'anchor_date' => $anchor,
                        ];
                    }
                }
            }
        }

        ksort($seasons);

        return array_values($seasons);
    }

    /**
     * @param array{amavasya_end_jd: float, anchor_date: string} $season
     *
     * @return list<array<string, mixed>>
     */
    private function scanEveningsAfterAmavasya(
        PanchangService $service,
        array $season,
        int $maxEvenings,
        string $rangeEnd = self::RANGE_END,
    ): array {
        $cache = [];
        $anchor = CarbonImmutable::parse($season['anchor_date'], self::TZ);
        $out = [];

        // Evaluate anchor evening and following evenings (Amāvasyā may end mid-day or later).
        for ($i = 0; $i < $maxEvenings; $i++) {
            $date = $anchor->addDays($i);
            if ($date->toDateString() > $rangeEnd) {
                break;
            }

            $day = $this->dayBundle($service, $date, $cache);
            // Skip evenings wholly before Amāvasyā ends.
            if ((float) $day['sunset_jd'] + 1e-9 < $season['amavasya_end_jd']) {
                continue;
            }

            $out[] = $this->evaluateEvening($service, $day, $cache);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $day
     * @param array<string, array<string, mixed>> $cache
     *
     * @return array<string, mixed>
     */
    private function evaluateEvening(PanchangService $service, array $day, array &$cache): array
    {
        $sunset = (float) $day['sunset_jd'];
        $moonrise = is_float($day['moonrise_jd']) || is_int($day['moonrise_jd'])
            ? (float) $day['moonrise_jd']
            : null;
        $moonset = is_float($day['moonset_jd']) || is_int($day['moonset_jd'])
            ? (float) $day['moonset_jd']
            : null;
        $elong = (float) $day['directed_longitude_separation_deg'];

        // §9 Pradoṣa: record overlap as supportive Satsangijivan analogy only — never reject.
        // Approximate local pradoṣa as the first three rātri-muhūrtas after sunset.
        $pradoshaEnd = $sunset + (3.0 * ((float) $day['night_muhurta_seconds'])) / 86400.0;

        $hasWindow = false;
        $lagMinutes = null;
        $visibilityDuringPradosha = false;
        if ($moonrise !== null && $moonset !== null && $moonrise < $sunset && $sunset < $moonset) {
            $hasWindow = true;
            $lagMinutes = ($moonset - $sunset) * 1440.0;
            $visibilityDuringPradosha = $moonset > $sunset
                && min($moonset, $pradoshaEnd) > $sunset;
        }

        $twelveBhagaProxy = $elong >= self::PROXY_12_BHAGA_SEPARATION_DEG && $elong < 180.0;

        // Tithi proxy only when Pratipada is udaya-vyāpinī and Dvitīyā follows before/at sunset path.
        $proxy = $this->computeTithiProxy($service, $day, $cache);

        $flags = [
            'has_post_sunset_horizon_window' => $hasWindow,
            'twelve_bhaga_proxy_passed' => $twelveBhagaProxy,
            'tithi_proxy_aparahna_3' => $proxy['aparahna_3'],
            'tithi_proxy_applicable' => $proxy['applicable'],
        ];

        $classification = $this->classifyEvening($flags);

        return [
            'date' => $day['date'],
            'tithi_index_abs' => (int) $day['tithi_index_abs'],
            'classification' => $classification,
            'has_post_sunset_horizon_window' => $hasWindow,
            'twelve_bhaga_proxy_passed' => $twelveBhagaProxy,
            'modern_directed_moon_sun_longitude_separation_at_local_sunset_degrees' => $elong,
            'modern_topocentric_elongation_degrees' => null,
            'lag_minutes' => $lagMinutes,
            'illumination_percent' => (float) $day['illumination_percent'],
            'tithi_proxy_applicable' => $proxy['applicable'],
            'tithi_proxy_aparahna_3' => $proxy['aparahna_3'],
            'tithi_proxy_to_sunset_6' => $proxy['to_sunset_6'],
            'dvitiya_covers_full_aparahna_3_muhurtas' => $proxy['aparahna_3'],
            'dvitiya_covers_aparahna_through_sunset_6_muhurtas' => $proxy['to_sunset_6'],
            'pratipada_post_sunrise_muhurtas' => $proxy['pratipada_post_sunrise_muhurtas'],
            'dvitiya_start_jd' => $proxy['dvitiya_start_jd'],
            'dvitiya_end_jd' => $proxy['dvitiya_end_jd'],
            'visibility_during_pradosha' => $visibilityDuringPradosha,
            'forbidden_modern_thresholds_applied' => false,
        ];
    }

    /**
     * @param array{
     *   has_post_sunset_horizon_window: bool,
     *   twelve_bhaga_proxy_passed: bool,
     *   tithi_proxy_aparahna_3: bool,
     *   tithi_proxy_applicable: bool
     * } $f
     */
    private function classifyEvening(array $f): string
    {
        // §10 A: strongest combined support.
        if ($f['has_post_sunset_horizon_window']
            && $f['twelve_bhaga_proxy_passed']
            && $f['tithi_proxy_applicable']
            && $f['tithi_proxy_aparahna_3']) {
            return 'APPLICATION_CRESCENT_CANDIDATE_WITH_NIBANDHA_INDICATION';
        }

        // §10 B: astronomical only (proxy false/unavailable/inapplicable is OK).
        if ($f['has_post_sunset_horizon_window'] && $f['twelve_bhaga_proxy_passed']) {
            return 'APPLICATION_CRESCENT_CANDIDATE';
        }

        // §10 C: tithi proxy true but astronomy already failed A/B (elong < 12° or no window).
        if ($f['tithi_proxy_applicable'] && $f['tithi_proxy_aparahna_3']) {
            return 'NIBANDHA_TITHI_INDICATION_ASTRONOMICAL_PROXY_DIVERGENCE';
        }

        // §10 E before D when there is simply no post-sunset window.
        if (!$f['has_post_sunset_horizon_window']) {
            return 'NO_POST_SUNSET_HORIZON_WINDOW';
        }

        // §10 D: window exists but 12-bhaga proxy separation < 12°.
        return 'TWELVE_BHAGA_PROXY_NOT_PASSED';
    }

    /**
     * @param array<string, mixed> $day
     * @param array<string, array<string, mixed>> $cache
     *
     * @return array{
     *   applicable: bool,
     *   aparahna_3: bool,
     *   to_sunset_6: bool,
     *   pratipada_post_sunrise_muhurtas: ?float,
     *   dvitiya_start_jd: ?float,
     *   dvitiya_end_jd: ?float
     * }
     */
    private function computeTithiProxy(PanchangService $service, array $day, array &$cache): array
    {
        unset($service, $cache);

        $abs = (int) $day['tithi_index_abs'];
        $sunrise = (float) $day['sunrise_jd'];
        $sunset = (float) $day['sunset_jd'];
        $dayMuhurta = (float) $day['day_muhurta_seconds'];
        if ($dayMuhurta <= 0.0 || $sunset <= $sunrise) {
            return [
                'applicable' => false,
                'aparahna_3' => false,
                'to_sunset_6' => false,
                'pratipada_post_sunrise_muhurtas' => null,
                'dvitiya_start_jd' => null,
                'dvitiya_end_jd' => null,
            ];
        }

        $aparahnaStart = $sunrise + (9.0 * $dayMuhurta) / 86400.0;
        $aparahnaEnd = $sunrise + (12.0 * $dayMuhurta) / 86400.0;

        // Proxy applies only on Pratipada-at-sunrise civil day with following Dvitīyā.
        if ($abs !== 1) {
            return [
                'applicable' => false,
                'aparahna_3' => false,
                'to_sunset_6' => false,
                'pratipada_post_sunrise_muhurtas' => null,
                'dvitiya_start_jd' => null,
                'dvitiya_end_jd' => null,
            ];
        }

        $pratipadaEnd = (float) $day['tithi_end_jd'];
        $dvitiyaStart = $pratipadaEnd;

        /** @var TransitEngine $transit */
        $transit = $this->app->make(TransitEngine::class);
        $dvitiyaEnd = $transit->findAngleCrossing(
            $dvitiyaStart + 1e-5,
            24.0,
            1,
            static fn (float $jd): float => $transit->getMoonSunAngle($jd),
        );

        if ($dvitiyaEnd <= $dvitiyaStart) {
            return [
                'applicable' => true,
                'aparahna_3' => false,
                'to_sunset_6' => false,
                'pratipada_post_sunrise_muhurtas' => null,
                'dvitiya_start_jd' => $dvitiyaStart,
                'dvitiya_end_jd' => null,
            ];
        }

        // Must actually be Dvitīyā covering Aparāhna of THIS Pratipada civil day.
        $proxy3 = $dvitiyaStart <= $aparahnaStart + 1e-9
            && $dvitiyaEnd >= $aparahnaEnd - 1e-9;

        $proxy6 = $dvitiyaStart <= $aparahnaStart + 1e-9
            && $dvitiyaEnd >= $sunset - 1e-9;

        $postSunriseMuh = max(0.0, ($pratipadaEnd - $sunrise) * 86400.0) / $dayMuhurta;

        return [
            'applicable' => true,
            'aparahna_3' => $proxy3,
            'to_sunset_6' => $proxy6,
            'pratipada_post_sunrise_muhurtas' => $postSunriseMuh,
            'dvitiya_start_jd' => $dvitiyaStart,
            'dvitiya_end_jd' => $dvitiyaEnd,
        ];
    }

    // -------------------------------------------------------------------------
    // Day bundle + package comparison
    // -------------------------------------------------------------------------

    /**
     * @param array<string, array<string, mixed>> $cache
     *
     * @return array<string, mixed>
     */
    private function dayBundle(PanchangService $service, CarbonImmutable $date, array &$cache): array
    {
        $key = $date->toDateString();
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        // Festival snapshot carries Moonrise_JD / Moonset_JD and sunset elongation/illumination
        // on Resolution_Context (full getDayDetails nests moon rise under Panchanga only).
        $details = $service->getFestivalSnapshot($date, self::LAT, self::LON, self::TZ, 0.0, null, 'amanta', true);
        $ctx = (array) ($details['Resolution_Context'] ?? []);
        $sunriseJd = (float) ($ctx['sunrise_jd'] ?? 0.0);
        $sunsetJd = (float) ($ctx['sunset_jd'] ?? 0.0);
        $nextSunriseJd = (float) ($ctx['next_sunrise_jd'] ?? 0.0);
        $dayMuhurtaSeconds = $sunsetJd > $sunriseJd
            ? (($sunsetJd - $sunriseJd) * 86400.0) / 15.0
            : 0.0;
        $nightMuhurtaSeconds = $nextSunriseJd > $sunsetJd
            ? (($nextSunriseJd - $sunsetJd) * 86400.0) / 15.0
            : 0.0;

        $moonrise = $this->extractJd($details['Moonrise_JD'] ?? ($details['Moonrise'] ?? null));
        $moonset = $this->extractJd($details['Moonset_JD'] ?? ($details['Moonset'] ?? null));

        return $cache[$key] = [
            'date' => $key,
            'tithi_index_abs' => (int) ($ctx['tithi_index_abs'] ?? 0),
            'tithi_start_jd' => (float) ($ctx['tithi_start_jd'] ?? 0.0),
            'tithi_end_jd' => (float) ($ctx['tithi_end_jd'] ?? 0.0),
            'sunrise_jd' => $sunriseJd,
            'sunset_jd' => $sunsetJd,
            'next_sunrise_jd' => $nextSunriseJd,
            'day_muhurta_seconds' => $dayMuhurtaSeconds,
            'night_muhurta_seconds' => $nightMuhurtaSeconds,
            'moonrise_jd' => $moonrise,
            'moonset_jd' => $moonset,
            // Package field is a 0-360 directed Moon-minus-Sun longitude separation.
            // Not a full Surya Siddhanta computational rewrite.
            'directed_longitude_separation_deg' => $this->normalizeDegrees((float) ($ctx['moon_sun_elongation_at_sunset_degrees'] ?? 0.0)),
            'illumination_percent' => (float) ($ctx['moon_illumination_at_sunset_percent'] ?? 0.0),
        ];
    }

    private function normalizeDegrees(float $degrees): float
    {
        $d = fmod($degrees, 360.0);
        if ($d < 0.0) {
            $d += 360.0;
        }

        return $d;
    }

    /** @return list<string> */
    private function packageChandraDarshanaDates(PanchangService $service): array
    {
        $cal = $service->getVratRangeCalendar(
            2025,
            10,
            2027,
            3,
            self::LAT,
            self::LON,
            self::TZ,
            0.0,
            null,
            'amanta',
        );

        $dates = [];
        foreach ((array) ($cal['by_date'] ?? []) as $date => $entries) {
            foreach ((array) $entries as $entry) {
                $name = (string) ($entry['name_key'] ?? $entry['name'] ?? '');
                if ($name === 'Chandra Darshana' || $name === 'Adhika Chandra Darshana') {
                    $dates[] = (string) $date;
                }
            }
        }

        $dates = array_values(array_unique($dates));
        sort($dates);

        return $dates;
    }

    private function extractJd(mixed $value): ?float
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        if (is_array($value) && isset($value['jd'])) {
            return (float) $value['jd'];
        }

        return null;
    }
}
