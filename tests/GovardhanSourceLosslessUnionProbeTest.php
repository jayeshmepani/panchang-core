<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Tests;

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Panchanga\PanchangService;
use JayeshMepani\PanchangCore\PanchangServiceProvider;
use Orchestra\Testbench\TestCase;
use Override;
use PHPUnit\Framework\Attributes\Group;

/**
 * EXPERIMENTAL / DOCUMENTARY probe — does NOT change production festival resolvers.
 *
 * Implements the "canonical source-lossless union" decision tree for
 * Govardhana / Gokriḍana / Annakūṭa as pure test-side logic, using live
 * ephemeris facts from PanchangService.
 *
 * Scope: civil dates from 2025-10-01 through 2027-03-31 (Bhuj).
 *
 * Intent:
 *  - Prove the multi-source tree can be executed on real astronomy.
 *  - Report CONSENSUS_DATE / SOURCE_CONFLICT / TEXTUALLY_UNDETERMINED.
 *  - Compare consensus (if any) to the package's current single-winner Govardhan date.
 *  - Explicitly mark generic monthly Chandra Darshana as TEXTUALLY_UNDETERMINED
 *    under the seven-tradition union (no complete Sud 1/Sud 2 rule in those texts).
 */
#[Group('slow')]
class GovardhanSourceLosslessUnionProbeTest extends TestCase
{
    private const float LAT = 23.2472446;

    private const float LON = 69.668339;

    private const string TZ = 'Asia/Kolkata';

    private const string RANGE_START = '2025-10-01';

    private const string RANGE_END = '2027-03-31';

    /** Current package export anchors for comparison (Bhuj). */
    private const array PACKAGE_GOVARDHAN_DATES = [
        '2025-10-22',
        '2026-11-09',
    ];

    public function test_source_lossless_union_for_govardhan_seasons_oct2025_to_mar2027(): void
    {
        /** @var PanchangService $service */
        $service = $this->app->make(PanchangService::class);

        $seasons = $this->discoverKartikaPratipadaSeasons($service);
        self::assertNotEmpty($seasons, 'Expected at least one Kartika Śukla Pratipada season in range.');

        $report = [];
        $consensusCount = 0;
        $conflictCount = 0;
        $undeterminedCount = 0;

        foreach ($seasons as $season) {
            $result = $this->runGovardhanSourceUnion($season);
            $report[] = $result;

            match ($result['result']) {
                'CONSENSUS_DATE' => $consensusCount++,
                'SOURCE_CONFLICT' => $conflictCount++,
                default => $undeterminedCount++,
            };

            // Structural integrity of the lossless contract.
            self::assertArrayHasKey('source_verdicts', $result);
            self::assertArrayHasKey('shared_facts', $result);
            self::assertArrayHasKey('surya_siddhanta', $result);
            self::assertNull($result['surya_siddhanta']['ritual_date_vote']);
            self::assertContains(
                $result['result'],
                ['CONSENSUS_DATE', 'SOURCE_CONFLICT', 'TEXTUALLY_UNDETERMINED'],
            );

            // Shared temporal facts must use real intervals, not sunrise-tithi alone.
            $facts = $result['shared_facts'];
            self::assertGreaterThan(0.0, (float) $facts['pratipada']['start_jd']);
            self::assertGreaterThan((float) $facts['pratipada']['start_jd'], (float) $facts['pratipada']['end_jd'] - 1.0);
            self::assertArrayHasKey('moon_risk_aparahna_3', $facts);
            self::assertArrayHasKey('moon_risk_to_sunset_6', $facts);
            self::assertIsBool($facts['moon_risk_aparahna_3']);
            self::assertIsBool($facts['moon_risk_to_sunset_6']);
        }

        // Range coverage: Diwali seasons 2025 and 2026 fall inside Oct 2025–Mar 2027.
        $seasonDates = array_map(
            static fn (array $r): string => (string) ($r['shared_facts']['d1']['date'] ?? ''),
            $report,
        );
        self::assertTrue(
            $this->reportCoversYearMonths($report, 2025, [10, 11])
            || $this->reportCoversYearMonths($report, 2025, [10]),
            'Expected a 2025 Kartika Pratipada season in Oct/Nov. Report: ' . json_encode($seasonDates),
        );
        self::assertTrue(
            $this->reportCoversYearMonths($report, 2026, [10, 11]),
            'Expected a 2026 Kartika Pratipada season in Oct/Nov. Report: ' . json_encode($seasonDates),
        );

        // Human-readable summary for CI logs (not a soft assertion failure).
        fwrite(STDOUT, "\n=== Govardhan source-lossless union probe (Bhuj, " . self::RANGE_START . ' … ' . self::RANGE_END . ") ===\n");
        foreach ($report as $row) {
            $facts = $row['shared_facts'];
            fwrite(STDOUT, sprintf(
                "Season D1=%s D2=%s | result=%s consensus=%s | moon_risk_3=%s moon_risk_6=%s | package_govardhan≈%s\n  verdicts=%s\n",
                $facts['d1']['date'],
                $facts['d2']['date'] ?? 'null',
                $row['result'],
                $row['consensus_date'] ?? 'null',
                (bool) $facts['moon_risk_aparahna_3'] ? 'Y' : 'N',
                (bool) $facts['moon_risk_to_sunset_6'] ? 'Y' : 'N',
                $row['package_govardhan_date'] ?? 'n/a',
                json_encode($row['source_verdicts'], JSON_UNESCAPED_SLASHES),
            ));
        }

        fwrite(STDOUT, sprintf(
            "Totals: seasons=%d consensus=%d conflict=%d undetermined=%d\n",
            count($report),
            $consensusCount,
            $conflictCount,
            $undeterminedCount,
        ));

        // Soft documentation: if the union yields consensus, it should land on one of the
        // package's known export dates when that season is one of the verified anchors.
        foreach ($report as $row) {
            if ($row['result'] !== 'CONSENSUS_DATE') {
                continue;
            }

            $pkg = $row['package_govardhan_date'] ?? null;
            if ($pkg === null) {
                continue;
            }

            self::assertSame(
                $pkg,
                $row['consensus_date'],
                'When package has a verified Govardhan date for this season and the multi-source '
                . 'union reaches consensus, the dates should match. If this fails, production and '
                . 'source-union disagree — investigate before changing production code.',
            );
        }
    }

    public function test_monthly_chandra_darshana_is_textually_undetermined_under_union(): void
    {
        // Per the research brief: the seven traditions do not supply a complete
        // monthly Sud 1 / Sud 2 Chandra Darshana date rule; do not import Govardhan 9-muhūrta.
        $result = $this->runMonthlyChandraDarshanaUnion();

        self::assertSame('TEXTUALLY_UNDETERMINED', $result['result']);
        self::assertSame('no_complete_monthly_sud1_sud2_rule_in_seven_traditions', $result['reason_key']);
        self::assertFalse($result['imports_govardhan_nine_muhurta']);
        self::assertSame('satsangijivan_1_24_5_is_samskara_not_monthly', $result['childhood_saṃskāra_note']);
    }

    public function test_union_aggregator_is_strict_and_lossless(): void
    {
        // Pure unit checks of the aggregator (no ephemeris).
        self::assertSame(
            'CONSENSUS_DATE',
            $this->aggregateSourceVerdicts([
                'satsangijivan' => 'PREFER_D1',
                'skanda_purana' => 'ACCEPT_D1',
                'nirnaya_sindhu' => 'ACCEPT_D1',
                'nirnayamrita' => 'PREFER_D1',
                'hari_bhakti_vilasa' => 'PREFER_D1',
                'surya_siddhanta' => 'DIAGNOSTIC_ONLY',
            ])['result'],
        );

        self::assertSame(
            'SOURCE_CONFLICT',
            $this->aggregateSourceVerdicts([
                'satsangijivan' => 'PREFER_D1',
                'skanda_purana' => 'ACCEPT_D1',
                'nirnaya_sindhu' => 'ACCEPT_D2',
                'surya_siddhanta' => 'DIAGNOSTIC_ONLY',
            ])['result'],
        );

        self::assertSame(
            'SOURCE_CONFLICT',
            $this->aggregateSourceVerdicts([
                'satsangijivan' => 'PREFER_D1',
                'skanda_purana' => 'REJECT_D1',
                'nirnaya_sindhu' => 'ACCEPT_D1',
            ])['result'],
        );

        self::assertSame(
            'TEXTUALLY_UNDETERMINED',
            $this->aggregateSourceVerdicts([
                'surya_siddhanta' => 'DIAGNOSTIC_ONLY',
                'vrddha_satatapa' => 'DIAGNOSTIC_ONLY',
            ])['result'],
        );
    }

    #[Override]
    protected function getPackageProviders($app): array
    {
        return [PanchangServiceProvider::class];
    }

    // -------------------------------------------------------------------------
    // Season discovery (ephemeris-backed)
    // -------------------------------------------------------------------------

    /** @return list<array<string, mixed>> */
    private function discoverKartikaPratipadaSeasons(PanchangService $service): array
    {
        $start = CarbonImmutable::parse(self::RANGE_START, self::TZ);
        $end = CarbonImmutable::parse(self::RANGE_END, self::TZ);

        // Diwali / Kartika Pratipada falls in Oct–Nov for this latitude in 2025–2026.
        // Still scan full range in case of edge seasons, but day-details only near candidates.
        $candidateSeeds = [];
        for ($d = $start; $d->lessThanOrEqualTo($end); $d = $d->addDay()) {
            // Cheap month filter: only Oct–Dec to catch Kartika; skip other months for runtime.
            if (!in_array($d->month, [10, 11, 12], true)) {
                continue;
            }

            $candidateSeeds[] = $d;
        }

        $pratipadaIntervals = [];
        $dayCache = [];

        foreach ($candidateSeeds as $date) {
            $day = $this->dayBundle($service, $date, $dayCache);
            $abs = (int) ($day['tithi_index_abs'] ?? 0);
            $monthEn = strtolower((string) ($day['month_amanta_en'] ?? $day['month_amanta'] ?? ''));

            // Kartika or late Ashwin Amavasya / Kartika Pratipada window.
            // Kartika Śukla Pratipada only (Govardhan / Gokriḍana / Annakūṭa cluster).
            // Do not pull ordinary Ashwin Śukla Pratipada mid-season.
            $isKartika = str_contains($monthEn, 'kartik');

            if (!$isKartika) {
                continue;
            }

            if ($abs === 1) {
                $startJd = (float) $day['tithi_start_jd'];
                $endJd = (float) $day['tithi_end_jd'];
                if ($startJd > 0.0 && $endJd > $startJd) {
                    $key = sprintf('%.6F:%.6F', $startJd, $endJd);
                    $pratipadaIntervals[$key] = [
                        'start_jd' => $startJd,
                        'end_jd' => $endJd,
                        'seed_date' => $date->toDateString(),
                        'month_amanta_en' => $day['month_amanta_en'],
                    ];
                }
            }
        }

        $seasons = [];
        foreach ($pratipadaIntervals as $interval) {
            $d1d2 = $this->buildD1D2ForPratipada($service, $interval, $dayCache);
            if ($d1d2 === null) {
                continue;
            }

            // Keep only Diwali-cluster seasons: package Deepavali / Govardhan nearby, or Oct–Nov Kartika.
            $d1Date = (string) $d1d2['d1']['date'];
            $d2Date = is_array($d1d2['d2']) ? (string) $d1d2['d2']['date'] : null;
            $nearPackage = $this->packageGovardhanNearSeason($d1Date, $d2Date) !== null;
            $festivalHit = $this->seasonTouchesDeepavaliCluster($d1d2['d1'], $d1d2['d2']);
            $monthOk = in_array(CarbonImmutable::parse($d1Date)->month, [10, 11], true);
            if (!$nearPackage && !$festivalHit && !$monthOk) {
                continue;
            }

            // Deduplicate by D1 date.
            $seasons[$d1Date] = [
                'pratipada' => [
                    'start_jd' => $interval['start_jd'],
                    'end_jd' => $interval['end_jd'],
                ],
                'dvitiya' => $d1d2['dvitiya'],
                'd1' => $d1d2['d1'],
                'd2' => $d1d2['d2'],
            ];
        }

        ksort($seasons);

        return array_values($seasons);
    }

    /**
     * @param array{start_jd: float, end_jd: float, seed_date: string, month_amanta_en?: string} $interval
     * @param array<string, array<string, mixed>> $dayCache
     *
     * @return array{d1: array<string, mixed>, d2: ?array<string, mixed>, dvitiya: array{start_jd: float, end_jd: float}}|null
     */
    private function buildD1D2ForPratipada(PanchangService $service, array $interval, array &$dayCache): ?array
    {
        $seed = CarbonImmutable::parse($interval['seed_date'], self::TZ);
        $windowStart = $seed->subDays(2);
        $windowEnd = $seed->addDays(3);

        $overlapping = [];
        for ($d = $windowStart; $d->lessThanOrEqualTo($windowEnd); $d = $d->addDay()) {
            $day = $this->dayBundle($service, $d, $dayCache);
            $civilStart = (float) $day['sunrise_jd'];
            $civilEnd = (float) $day['next_sunrise_jd'];
            if ($this->intervalsOverlap($interval['start_jd'], $interval['end_jd'], $civilStart, $civilEnd)) {
                $overlapping[] = $day;
            }
        }

        if ($overlapping === []) {
            return null;
        }

        usort(
            $overlapping,
            static fn (array $a, array $b): int => ((float) $a['sunrise_jd']) <=> ((float) $b['sunrise_jd']),
        );

        $d1 = $overlapping[0];
        $d2 = $overlapping[1] ?? null;

        // Dvitīyā interval: from end of Pratipada; length approximated from next day tithi if abs=2.
        $dvitiyaStart = $interval['end_jd'];
        $dvitiyaEnd = $dvitiyaStart + (12.0 / 360.0); // fallback ~1 tithi of mean motion; refined below
        if (is_array($d2) && (int) $d2['tithi_index_abs'] === 2) {
            $dvitiyaStart = (float) $d2['tithi_start_jd'];
            $dvitiyaEnd = (float) $d2['tithi_end_jd'];
        } else {
            // Look one day after last overlapping day.
            $after = CarbonImmutable::parse((string) ($d2['date'] ?? $d1['date']), self::TZ)->addDay();
            $dayAfter = $this->dayBundle($service, $after, $dayCache);
            if ((int) $dayAfter['tithi_index_abs'] === 2) {
                $dvitiyaStart = (float) $dayAfter['tithi_start_jd'];
                $dvitiyaEnd = (float) $dayAfter['tithi_end_jd'];
            } elseif ((int) $d1['tithi_index_abs'] === 1) {
                // Pratipada day: next tithi starts at pratipada end.
                $dvitiyaStart = $interval['end_jd'];
                // Use next sunrise day's tithi end if it is Dwitiya.
                $next = CarbonImmutable::parse((string) $d1['date'], self::TZ)->addDay();
                $n = $this->dayBundle($service, $next, $dayCache);
                if ((int) $n['tithi_index_abs'] === 2) {
                    $dvitiyaEnd = (float) $n['tithi_end_jd'];
                } else {
                    $dvitiyaEnd = $dvitiyaStart + max(0.2, ((float) $n['tithi_end_jd'] - (float) $n['tithi_start_jd']));
                }
            }
        }

        return [
            'd1' => $d1,
            'd2' => $d2,
            'dvitiya' => [
                'start_jd' => $dvitiyaStart,
                'end_jd' => $dvitiyaEnd,
            ],
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $dayCache
     *
     * @return array<string, mixed>
     */
    private function dayBundle(PanchangService $service, CarbonImmutable $date, array &$dayCache): array
    {
        $key = $date->toDateString();
        if (isset($dayCache[$key])) {
            return $dayCache[$key];
        }

        $details = $service->getDayDetails($date, self::LAT, self::LON, self::TZ, 0.0, null, 'amanta');
        $ctx = (array) ($details['Resolution_Context'] ?? []);
        $hc = (array) ($details['Hindu_Calendar'] ?? []);
        $sunriseJd = (float) ($ctx['sunrise_jd'] ?? 0.0);
        $sunsetJd = (float) ($ctx['sunset_jd'] ?? 0.0);
        $nextSunriseJd = (float) ($ctx['next_sunrise_jd'] ?? 0.0);
        $dayMuhurtaSeconds = $sunsetJd > $sunriseJd
            ? (($sunsetJd - $sunriseJd) * 86400.0) / 15.0
            : 0.0;

        $bundle = [
            'date' => $key,
            'tithi_index_abs' => (int) ($ctx['tithi_index_abs'] ?? 0),
            'tithi_start_jd' => (float) ($ctx['tithi_start_jd'] ?? 0.0),
            'tithi_end_jd' => (float) ($ctx['tithi_end_jd'] ?? 0.0),
            'sunrise_jd' => $sunriseJd,
            'sunset_jd' => $sunsetJd,
            'next_sunrise_jd' => $nextSunriseJd,
            'previous_sunrise_jd' => (float) ($ctx['previous_sunrise_jd'] ?? 0.0),
            'day_muhurta_seconds' => $dayMuhurtaSeconds,
            'month_amanta' => (string) ($hc['Month_Amanta'] ?? ''),
            'month_amanta_en' => (string) ($hc['Month_Amanta_En'] ?? $hc['Month_Amanta'] ?? ''),
            'is_adhika' => (bool) ($hc['Is_Adhika'] ?? false),
            'moonset_jd' => $this->extractJd($details['Moonset_JD'] ?? ($details['Moonset'] ?? null)),
            'moon_sun_elongation_at_sunset_degrees' => (float) ($ctx['moon_sun_elongation_at_sunset_degrees'] ?? 0.0),
            'moon_illumination_at_sunset_percent' => (float) ($ctx['moon_illumination_at_sunset_percent'] ?? 0.0),
            'festivals' => array_values(array_map(
                static fn (array $f): string => (string) ($f['name_key'] ?? $f['name'] ?? ''),
                (array) ($details['Festivals'] ?? []),
            )),
        ];

        // Fivefold daytime boundaries (equal 3-muhūrta blocks of dinamāna).
        $bundle['aparahna_start_jd'] = $sunriseJd + (9.0 * $dayMuhurtaSeconds) / 86400.0;
        $bundle['aparahna_end_jd'] = $sunriseJd + (12.0 * $dayMuhurtaSeconds) / 86400.0;
        $bundle['sayahna_start_jd'] = $bundle['aparahna_end_jd'];
        $bundle['sayahna_end_jd'] = $sunsetJd;

        return $dayCache[$key] = $bundle;
    }

    // -------------------------------------------------------------------------
    // Multi-source Govardhan union
    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $season
     *
     * @return array<string, mixed>
     */
    private function runGovardhanSourceUnion(array $season): array
    {
        $d1 = (array) $season['d1'];
        $d2 = $season['d2'] !== null ? (array) $season['d2'] : null;
        $pratipada = (array) $season['pratipada'];
        $dvitiya = (array) $season['dvitiya'];

        $p1Sayahna = $this->intervalCoversPoint($pratipada['start_jd'], $pratipada['end_jd'], (float) $d1['sunset_jd']);
        $p2Sayahna = is_array($d2)
            ? $this->intervalCoversPoint($pratipada['start_jd'], $pratipada['end_jd'], (float) $d2['sunset_jd'])
            : false;

        $d1IsPurvaviddha = (int) $d1['tithi_index_abs'] === 30
            || ((int) $d1['tithi_index_abs'] === 1 && (float) $pratipada['start_jd'] > (float) $d1['sunrise_jd']);
        // Darśa-connected: Amāvasyā at/near sunrise on D1 or Pratipada begins after Amāvasyā on D1.
        $d1DarshaConnected = (int) $d1['tithi_index_abs'] === 30
            || ((int) $d1['tithi_index_abs'] === 1 && (float) $pratipada['start_jd'] >= (float) $d1['previous_sunrise_jd']);

        $d2DwitiyaConnected = is_array($d2) && (
            (int) $d2['tithi_index_abs'] === 2
            || ((int) $d2['tithi_index_abs'] === 1 && (float) $pratipada['end_jd'] < (float) $d2['next_sunrise_jd'])
        );

        // Moon-risk forms evaluated on the Pratipada civil day that hosts Aparāhna of interest.
        // Evaluate on D1 evening context first; also compute for D2 if present.
        $riskDay = is_array($d2) ? $d2 : $d1;
        $moonRisk3 = $this->moonRiskAparahna3($dvitiya, $riskDay);
        $moonRisk6 = $this->moonRiskToSunset6($dvitiya, $riskDay);
        // Nirṇaya Sindhu "Moon risk on D2": Dvitīyā covers Aparāhna of D2 (or D1's later evening).
        $moonRiskOnD2 = is_array($d2) ? $this->moonRiskAparahna3($dvitiya, $d2) : $moonRisk3;

        $sharedFacts = [
            'd1' => [
                'date' => $d1['date'],
                'tithi_index_abs' => $d1['tithi_index_abs'],
                'sunrise_jd' => $d1['sunrise_jd'],
                'sunset_jd' => $d1['sunset_jd'],
            ],
            'd2' => is_array($d2) ? [
                'date' => $d2['date'],
                'tithi_index_abs' => $d2['tithi_index_abs'],
                'sunrise_jd' => $d2['sunrise_jd'],
                'sunset_jd' => $d2['sunset_jd'],
            ] : null,
            'pratipada' => $pratipada,
            'dvitiya' => $dvitiya,
            'p1_sayahna' => $p1Sayahna,
            'p2_sayahna' => $p2Sayahna,
            'd1_is_purvaviddha' => $d1IsPurvaviddha,
            'd1_darsha_connected' => $d1DarshaConnected,
            'd2_dwitiya_connected' => $d2DwitiyaConnected,
            'moon_risk_aparahna_3' => $moonRisk3,
            'moon_risk_to_sunset_6' => $moonRisk6,
            'moon_risk_on_d2' => $moonRiskOnD2,
            'derived_pratipada_ends_by_aparahna_start_d2' => is_array($d2)
                ? ((float) $pratipada['end_jd'] <= (float) $d2['aparahna_start_jd'] + 1e-9)
                : false,
        ];

        $verdicts = [
            'satsangijivan' => $this->moduleSatsangijivan($sharedFacts),
            'skanda_purana' => $this->moduleSkanda($sharedFacts),
            'nirnayamrita' => $this->moduleNirnayamrita($sharedFacts),
            'vrddha_satatapa' => $moonRisk3 ? 'DIAGNOSTIC_ONLY' : 'DIAGNOSTIC_ONLY',
            'hari_bhakti_vilasa' => $this->moduleHariBhaktiVilasa($sharedFacts),
            'nirnaya_sindhu' => $this->moduleNirnayaSindhu($sharedFacts),
            'surya_siddhanta' => 'DIAGNOSTIC_ONLY',
        ];

        $agg = $this->aggregateSourceVerdicts($verdicts);
        $packageDate = $this->packageGovardhanNearSeason($d1['date'], is_array($d2) ? (string) $d2['date'] : null);

        return [
            'result' => $agg['result'],
            'consensus_date' => $agg['consensus_day'] === 'D1'
                ? $d1['date']
                : ($agg['consensus_day'] === 'D2' && is_array($d2) ? $d2['date'] : null),
            'consensus_day' => $agg['consensus_day'],
            'source_verdicts' => $verdicts,
            'shared_facts' => $sharedFacts,
            'surya_siddhanta' => $this->moduleSuryaSiddhantaDiagnostic($d1),
            'package_govardhan_date' => $packageDate,
            'scriptural_moon_warning' => true,
            'evidence_notes' => [
                'vrddha_satatapa' => 'later_transmitted_quotation_not_independent_ms',
                'nirnayamrita' => 'transmitted_attribution_not_page_verified_here',
                'hari_bhakti_vilasa' => 'later_witness_to_hbv_16_for_moon_risk_branch',
            ],
        ];
    }

    /** @param array<string, mixed> $f */
    private function moduleSatsangijivan(array $f): string
    {
        $p1 = (bool) $f['p1_sayahna'];
        $p2 = (bool) $f['p2_sayahna'];
        $purva = (bool) $f['d1_is_purvaviddha'] || (bool) $f['d1_darsha_connected'];

        if ($p1 && $purva) {
            return 'PREFER_D1';
        }

        if ($p2) {
            return 'SAYAHNA_ELIGIBLE_D2'; // not labelled beneficial in the verse
        }

        if ($p1) {
            return 'PREFER_D1';
        }

        return 'NO_VERDICT';
    }

    /** @param array<string, mixed> $f */
    private function moduleSkanda(array $f): string
    {
        if ((bool) $f['d1_darsha_connected'] || (bool) $f['d1_is_purvaviddha'] || (bool) $f['p1_sayahna']) {
            // Gokriḍana on Darśa-joined Pratipada; condemn paraviddhā/D2 for the main festival.
            if ((bool) $f['d2_dwitiya_connected'] && (bool) $f['p2_sayahna'] && !(bool) $f['p1_sayahna']) {
                return 'ACCEPT_D2'; // sole residual candidate — still tagged carefully by aggregator notes
            }

            return 'ACCEPT_D1';
        }

        if ((bool) $f['p2_sayahna']) {
            return 'REJECT_D2'; // paraviddhā path condemned when Darśa day exists conceptually
        }

        return 'NO_VERDICT';
    }

    /** @param array<string, mixed> $f */
    private function moduleNirnayamrita(array $f): string
    {
        // Diagnostic moon possibility + Amāvasyā-mixed cow-worship association → prefer D1 when risk on later day.
        if ((bool) $f['moon_risk_on_d2'] || (bool) $f['moon_risk_aparahna_3']) {
            return 'PREFER_D1';
        }

        if ((bool) $f['d1_darsha_connected']) {
            return 'PREFER_D1';
        }

        return 'DIAGNOSTIC_ONLY';
    }

    /** @param array<string, mixed> $f */
    private function moduleHariBhaktiVilasa(array $f): string
    {
        // Transmitted preference: moon risk ⇒ D1 else D2 (evidence_grade: later witness).
        if ((bool) $f['moon_risk_aparahna_3'] || (bool) $f['moon_risk_on_d2']) {
            return 'PREFER_D1';
        }

        if ($f['d2'] !== null && (bool) $f['p2_sayahna']) {
            return 'PREFER_D2';
        }

        if ((bool) $f['p1_sayahna']) {
            return 'PREFER_D1';
        }

        return 'NO_VERDICT';
    }

    /** @param array<string, mixed> $f */
    private function moduleNirnayaSindhu(array $f): string
    {
        $p1 = (bool) $f['p1_sayahna'];
        $p2 = (bool) $f['p2_sayahna'];
        $moonD2 = (bool) $f['moon_risk_on_d2'];

        if ($p1 && $p2) {
            return 'ACCEPT_D2';
        }

        if ($p1 && $moonD2) {
            return 'ACCEPT_D1';
        }

        // After dual-Sāyāhna branch, $p1 alone implies !$p2.
        if ($p1) {
            return 'ACCEPT_D1';
        }

        // After $p1 branch, $p2 alone implies !$p1.
        if ($p2) {
            return 'ACCEPT_D2';
        }

        // Neither Sāyāhna → D1 (Bali on earlier night; later day moon prohibition tradition).
        return 'ACCEPT_D1';
    }

    /**
     * @param array<string, mixed> $day
     *
     * @return array<string, mixed>
     */
    private function moduleSuryaSiddhantaDiagnostic(array $day): array
    {
        $elongation = (float) ($day['moon_sun_elongation_at_sunset_degrees'] ?? 0.0);
        $illumination = (float) ($day['moon_illumination_at_sunset_percent'] ?? 0.0);
        $sunsetJd = (float) ($day['sunset_jd'] ?? 0.0);
        $moonsetJd = $day['moonset_jd'] !== null ? (float) $day['moonset_jd'] : null;
        $lagMinutes = ($moonsetJd !== null && $moonsetJd > $sunsetJd)
            ? ($moonsetJd - $sunsetJd) * 1440.0
            : null;

        return [
            'ritual_date_vote' => null,
            'astronomical_visibility_assessment' => [
                'gross_12_degree_condition' => $elongation >= 12.0,
                'elongation_degrees_at_sunset' => $elongation,
                'illuminated_percent_at_sunset' => $illumination,
                'moonset_after_sunset_minutes' => $lagMinutes,
                'note' => 'Astronomical assessment only — no automatic festival-date verdict',
            ],
        ];
    }

    /**
     * @param array<string, string> $verdicts
     *
     * @return array{result: string, consensus_day: ?string, date_votes: list<string>, rejections: list<string>}
     */
    private function aggregateSourceVerdicts(array $verdicts): array
    {
        $dateVotes = [];
        $rejections = [];

        foreach ($verdicts as $source => $verdict) {
            $v = strtoupper(trim($verdict));
            if (in_array($v, ['DIAGNOSTIC_ONLY', 'NO_VERDICT', 'NO_ELIGIBLE_DAY_FROM_THIS_VERSE'], true)) {
                continue;
            }

            if (str_starts_with($v, 'REJECT_')) {
                $rejections[] = substr($v, strlen('REJECT_'));
                continue;
            }

            if (str_starts_with($v, 'ACCEPT_') || str_starts_with($v, 'PREFER_')) {
                $day = substr($v, strrpos($v, '_') + 1);
                if (in_array($day, ['D1', 'D2'], true)) {
                    $dateVotes[] = $day;
                }

                continue;
            }

            // SAYAHNA_ELIGIBLE_D2 is a weak, non-beneficial eligibility — treat as soft D2 vote.
            if ($v === 'SAYAHNA_ELIGIBLE_D2') {
                $dateVotes[] = 'D2';
            }
        }

        $uniqueVotes = array_values(array_unique($dateVotes));
        $uniqueRejections = array_values(array_unique($rejections));

        if ($uniqueVotes === []) {
            return [
                'result' => 'TEXTUALLY_UNDETERMINED',
                'consensus_day' => null,
                'date_votes' => $dateVotes,
                'rejections' => $uniqueRejections,
            ];
        }

        if (count($uniqueVotes) > 1) {
            return [
                'result' => 'SOURCE_CONFLICT',
                'consensus_day' => null,
                'date_votes' => $dateVotes,
                'rejections' => $uniqueRejections,
            ];
        }

        $day = $uniqueVotes[0];
        if (in_array($day, $uniqueRejections, true)) {
            return [
                'result' => 'SOURCE_CONFLICT',
                'consensus_day' => null,
                'date_votes' => $dateVotes,
                'rejections' => $uniqueRejections,
            ];
        }

        return [
            'result' => 'CONSENSUS_DATE',
            'consensus_day' => $day,
            'date_votes' => $dateVotes,
            'rejections' => $uniqueRejections,
        ];
    }

    /** @return array<string, mixed> */
    private function runMonthlyChandraDarshanaUnion(): array
    {
        return [
            'result' => 'TEXTUALLY_UNDETERMINED',
            'reason_key' => 'no_complete_monthly_sud1_sud2_rule_in_seven_traditions',
            'imports_govardhan_nine_muhurta' => false,
            'childhood_saṃskāra_note' => 'satsangijivan_1_24_5_is_samskara_not_monthly',
            'surya_siddhanta_role' => 'visibility_diagnostic_only',
        ];
    }

    // -------------------------------------------------------------------------
    // Temporal predicates
    // -------------------------------------------------------------------------

    /**
     * @param array{start_jd: float, end_jd: float} $dvitiya
     * @param array<string, mixed> $day
     */
    private function moonRiskAparahna3(array $dvitiya, array $day): bool
    {
        $a0 = (float) $day['aparahna_start_jd'];
        $a1 = (float) $day['aparahna_end_jd'];

        return $dvitiya['start_jd'] <= $a0 + 1e-9
            && $dvitiya['end_jd'] >= $a1 - 1e-9;
    }

    /**
     * @param array{start_jd: float, end_jd: float} $dvitiya
     * @param array<string, mixed> $day
     */
    private function moonRiskToSunset6(array $dvitiya, array $day): bool
    {
        $a0 = (float) $day['aparahna_start_jd'];
        $sunset = (float) $day['sunset_jd'];

        return $dvitiya['start_jd'] <= $a0 + 1e-9
            && $dvitiya['end_jd'] >= $sunset - 1e-9;
    }

    private function intervalCoversPoint(float $startJd, float $endJd, float $pointJd): bool
    {
        return $pointJd > 0.0 && $startJd < $pointJd && $endJd > $pointJd;
    }

    private function intervalsOverlap(float $a0, float $a1, float $b0, float $b1): bool
    {
        return $a0 < $b1 && $b0 < $a1;
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

    private function packageGovardhanNearSeason(string $d1, ?string $d2): ?string
    {
        foreach (self::PACKAGE_GOVARDHAN_DATES as $pkg) {
            if ($pkg === $d1 || $pkg === $d2) {
                return $pkg;
            }

            // Within 2 days of either candidate.
            foreach (array_filter([$d1, $d2], static fn (?string $c): bool => $c !== null) as $cand) {
                $delta = abs(CarbonImmutable::parse($pkg)->diffInDays(CarbonImmutable::parse($cand)));
                if ($delta <= 2) {
                    return $pkg;
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $d1
     * @param array<string, mixed>|null $d2
     */
    private function seasonTouchesDeepavaliCluster(array $d1, ?array $d2): bool
    {
        $needles = [
            'Govardhan Puja',
            'Bali Pratipada',
            'Lakshmi Puja (Deepavali)',
            'Chopda Pujan',
            'Bestu Varas',
        ];
        foreach (array_filter([$d1, $d2], static fn (?array $day): bool => $day !== null) as $day) {
            foreach ((array) ($day['festivals'] ?? []) as $name) {
                if (in_array((string) $name, $needles, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $report
     * @param list<int> $months
     */
    private function reportCoversYearMonths(array $report, int $year, array $months): bool
    {
        foreach ($report as $row) {
            $d1 = (string) ($row['shared_facts']['d1']['date'] ?? '');
            if ($d1 === '') {
                continue;
            }

            $dt = CarbonImmutable::parse($d1);
            if ($dt->year === $year && in_array($dt->month, $months, true)) {
                return true;
            }
        }

        return false;
    }
}
