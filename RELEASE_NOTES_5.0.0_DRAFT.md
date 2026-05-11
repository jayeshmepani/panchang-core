# Release Notes Draft: `4.1.1` -> `5.0.0`

This document summarizes the actual code and package changes between tag `4.1.1` and the planned `5.0.0` release state at the current latest commit.

It is based on source comparison, test additions, script changes, and generated output changes, not commit-message summaries.

## Recommended Version

Suggested next version: **`5.0.0`**

### Why `5.0.0`

- This release adds substantial new user-visible functionality.
- The output surface of `getDayDetails()`, `getFestivalSnapshot()`, and month calendar generation is significantly expanded.
- The internal architecture was heavily reorganized across multiple new service classes and traits.
- `MuhurtaService` is no longer directly constructible the old way without dependencies, which is a real breaking change for direct consumers even though container/bootstrap wiring was updated.

### If You Intentionally Ignore Constructor-Level BC

If you explicitly do **not** consider direct service instantiation part of the supported public contract, then **`4.2.0`** is the aggressive alternative.

Still, the safer recommendation is **`5.0.0`**.

## Summary

This release is not just a refactor.

It includes:

- major `PanchangService` and `MuhurtaService` decomposition
- new yoga systems
- new shoola systems
- new vaasa systems
- moonrise/moonset civil-date visibility fix
- new interval and transit infrastructure
- expanded day-detail and snapshot outputs
- stronger regression coverage
- CLI/bootstrap and docs updates
- one confirmed accidental regression found during review and fixed before release

## User-Visible Additions

### 1. Special Yogas Added

New daily/snapshot outputs now expose structured yoga payloads for:

- Sarvartha Siddhi Yoga
- Amrit Siddhi Yoga
- Ravi Yoga
- Ravi Pushya Yoga
- Guru Pushya Yoga
- Dwipushkar Yoga
- Tripushkar Yoga
- Ganda Mula
- Vinchhudo
- Aadal
- Vidaal
- Jwalamukhi
- Anandadi Yoga
- Amritadi Yoga
- Maitreya Yoga
- Gajachchhaya Yoga
- Panchak

Main implementation areas:

- [src/Panchanga/Yogas/SpecialYogaCalculator.php](/home/shreesoftech/projects/panchang-core/src/Panchanga/Yogas/SpecialYogaCalculator.php)
- [src/Astronomy/Math/IntervalTracker.php](/home/shreesoftech/projects/panchang-core/src/Astronomy/Math/IntervalTracker.php)
- [src/Astronomy/Math/TransitEngine.php](/home/shreesoftech/projects/panchang-core/src/Astronomy/Math/TransitEngine.php)

### 2. Shoola / Direction Systems Added

New outputs now cover:

- Disha Shool
- Nakshatra Shool

Main implementation:

- [src/Panchanga/Residences/ShoolaCalculator.php](/home/shreesoftech/projects/panchang-core/src/Panchanga/Residences/ShoolaCalculator.php)

### 3. Vaasa Systems Added

New outputs now cover:

- Rahu Vaasa
- Chandra Vaasa
- Shiva Vaasa
- Agni Vaasa
- Yogini Vaasa

Main implementation:

- [src/Panchanga/Residences/VaasaCalculator.php](/home/shreesoftech/projects/panchang-core/src/Panchanga/Residences/VaasaCalculator.php)

### 4. Ekadashi Observance / Parana Output Added

New structured Ekadashi observance payloads now appear in daily outputs and festival snapshots.

Main implementation:

- [src/Panchanga/Vrata/EkadashiParanaCalculator.php](/home/shreesoftech/projects/panchang-core/src/Panchanga/Vrata/EkadashiParanaCalculator.php)
- [src/Panchanga/KalaNirnayaEngine.php](/home/shreesoftech/projects/panchang-core/src/Panchanga/KalaNirnayaEngine.php)

## Moonrise / Moonset Logic Change

Moonrise/moonset handling was changed so month-calendar and snapshot output represent the **visibility interval that starts on the civil date**.

Effects:

- `Moonrise` and `Moonset` can now be `null` for dates with no same-day visibility interval start
- snapshot outputs now expose:
  - `Moonrise_Date`
  - `Moonset_Date`
  - `Moonrise_ISO`
  - `Moonset_ISO`
  - `Moonset_Day_Relation`
- month calendar output now exposes:
  - `moonrise_date`
  - `moonset_date`
  - `moonrise_iso`
  - `moonset_iso`
  - `moonset_day_relation`
  - `moon_visibility`

Main implementation:

- [src/Astronomy/SunService.php](/home/shreesoftech/projects/panchang-core/src/Astronomy/SunService.php)
- [src/Panchanga/PanchangService.php](/home/shreesoftech/projects/panchang-core/src/Panchanga/PanchangService.php)
- [src/Panchanga/Traits/PanchangCalendarApiTrait.php](/home/shreesoftech/projects/panchang-core/src/Panchanga/Traits/PanchangCalendarApiTrait.php)

## Daily / Snapshot Output Expansion

### `getDayDetails()` now includes new top-level areas

- `Special_Yogas`
- `Anandadi_Yoga`
- `Amritadi_Yoga`
- `Panchak`
- `Maitreya_Yoga`
- `Gajachchhaya_Yoga`
- `Nakshatra_Shool`
- `Disha_Shool`
- `Rahu_Vaasa`
- `Chandra_Vaasa`
- `Shiva_Vaasa`
- `Agni_Vaasa`
- `Yogini_Vaasa`
- `Ekadashi_Observance`
- `Transitions`

### `Dharma_Sindhu` now also includes

- `Ekadashi_Observance`
- `Shiva_Vaasa`
- `Agni_Vaasa`
- `Yogini_Vaasa`

### `getFestivalSnapshot()` now includes

- yoga payloads
- shoola payloads
- vaasa payloads
- `Ekadashi_Observance`
- moon visibility date context

Main implementation:

- [src/Panchanga/PanchangService.php](/home/shreesoftech/projects/panchang-core/src/Panchanga/PanchangService.php)
- [src/Panchanga/Traits/PanchangMuhurtaYogaDelegatesTrait.php](/home/shreesoftech/projects/panchang-core/src/Panchanga/Traits/PanchangMuhurtaYogaDelegatesTrait.php)
- [src/Panchanga/Traits/PanchangRuntimeEvaluationTrait.php](/home/shreesoftech/projects/panchang-core/src/Panchanga/Traits/PanchangRuntimeEvaluationTrait.php)

## Major Internal Refactor

### `PanchangService` was split across focused traits/calculators

New extracted layers:

- astronomy helpers
- birth/month helpers
- calendar API helpers
- runtime evaluation helpers
- muhurta/yoga delegate helpers

Files:

- [src/Panchanga/Traits/PanchangAstronomyHelpersTrait.php](/home/shreesoftech/projects/panchang-core/src/Panchanga/Traits/PanchangAstronomyHelpersTrait.php)
- [src/Panchanga/Traits/PanchangBirthMonthHelpersTrait.php](/home/shreesoftech/projects/panchang-core/src/Panchanga/Traits/PanchangBirthMonthHelpersTrait.php)
- [src/Panchanga/Traits/PanchangCalendarApiTrait.php](/home/shreesoftech/projects/panchang-core/src/Panchanga/Traits/PanchangCalendarApiTrait.php)
- [src/Panchanga/Traits/PanchangMuhurtaYogaDelegatesTrait.php](/home/shreesoftech/projects/panchang-core/src/Panchanga/Traits/PanchangMuhurtaYogaDelegatesTrait.php)
- [src/Panchanga/Traits/PanchangRuntimeEvaluationTrait.php](/home/shreesoftech/projects/panchang-core/src/Panchanga/Traits/PanchangRuntimeEvaluationTrait.php)

### `MuhurtaService` was converted into an orchestrator

The old monolithic implementation was split into:

- [src/Muhurta/Planetary/HoraCalculator.php](/home/shreesoftech/projects/panchang-core/src/Muhurta/Planetary/HoraCalculator.php)
- [src/Muhurta/Planetary/ChogadiyaCalculator.php](/home/shreesoftech/projects/panchang-core/src/Muhurta/Planetary/ChogadiyaCalculator.php)
- [src/Muhurta/Classical/DailyPeriodsCalculator.php](/home/shreesoftech/projects/panchang-core/src/Muhurta/Classical/DailyPeriodsCalculator.php)
- [src/Muhurta/Classical/InauspiciousPeriodsCalculator.php](/home/shreesoftech/projects/panchang-core/src/Muhurta/Classical/InauspiciousPeriodsCalculator.php)
- [src/Muhurta/Regional/GowriPanchangamCalculator.php](/home/shreesoftech/projects/panchang-core/src/Muhurta/Regional/GowriPanchangamCalculator.php)
- [src/Muhurta/Lagna/LagnaTableCalculator.php](/home/shreesoftech/projects/panchang-core/src/Muhurta/Lagna/LagnaTableCalculator.php)

### New interval/transit infrastructure

Reusable low-level astronomy was extracted into:

- [src/Astronomy/Math/TransitEngine.php](/home/shreesoftech/projects/panchang-core/src/Astronomy/Math/TransitEngine.php)
- [src/Astronomy/Math/IntervalTracker.php](/home/shreesoftech/projects/panchang-core/src/Astronomy/Math/IntervalTracker.php)

### New dosha/window calculators

- [src/Panchanga/Doshas/BhadraCalculator.php](/home/shreesoftech/projects/panchang-core/src/Panchanga/Doshas/BhadraCalculator.php)
- [src/Panchanga/Doshas/PanchakCalculator.php](/home/shreesoftech/projects/panchang-core/src/Panchanga/Doshas/PanchakCalculator.php)
- [src/Panchanga/Doshas/VarjyamWindowCalculator.php](/home/shreesoftech/projects/panchang-core/src/Panchanga/Doshas/VarjyamWindowCalculator.php)

## Dependency Injection / Bootstrap Changes

Container wiring was expanded to register the new extracted services and calculators.

Updated:

- [src/PanchangServiceProvider.php](/home/shreesoftech/projects/panchang-core/src/PanchangServiceProvider.php)
- [src/Traits/CliBootstrap.php](/home/shreesoftech/projects/panchang-core/src/Traits/CliBootstrap.php)

Standalone usage examples were also updated to prefer `CliBootstrap::init()` plus `CliBootstrap::makePanchangService()`.

## Package / Tooling Changes

### `composer.json`

Development tooling was tightened/upgraded:

- `orchestra/testbench` narrowed to `^10.0`
- `phpunit/phpunit` raised to `^11.5`
- `phpstan/phpstan` raised to `^2.1`
- `rector/rector` raised to `^2.4`
- `quality` script now includes `@rector:dry`
- `process-timeout` added to Composer config

### Documentation

Updated:

- [README.md](/home/shreesoftech/projects/panchang-core/README.md)
- [docs/index.html](/home/shreesoftech/projects/panchang-core/docs/index.html)

New repo docs/support files added during the refactor:

- [PACKAGE_COVERAGE.md](/home/shreesoftech/projects/panchang-core/PACKAGE_COVERAGE.md)
- [PLAN.md](/home/shreesoftech/projects/panchang-core/PLAN.md)
- [count_lines.php](/home/shreesoftech/projects/panchang-core/count_lines.php)

## CLI / Script Changes

### `scripts/panchang_month_output.php`

Behavior changed slightly:

- default is now the **current month in the configured timezone**
- not raw `date('Y')` / `date('m')` from server-local PHP defaults

Updated:

- [scripts/panchang_month_output.php](/home/shreesoftech/projects/panchang-core/scripts/panchang_month_output.php)

## Tests Added / Updated

New regression coverage was added for:

- special yoga windows
- vara day-boundary regression
- Krishna Ekadashi report handling
- Ekadashi observance payload exposure
- added daily indicators
- moonrise/moonset visibility interval behavior
- Aadal/Vidaal examples

Files:

- [tests/SpecialYogaRegressionTest.php](/home/shreesoftech/projects/panchang-core/tests/SpecialYogaRegressionTest.php)
- [tests/MonthCalendarTest.php](/home/shreesoftech/projects/panchang-core/tests/MonthCalendarTest.php)

## Confirmed Regression Found During Comparison

One real accidental regression was found while comparing `4.1.1` to `HEAD`:

- `getDayDetails()` was resolving tomorrow’s festival snapshot without forwarding the caller’s selected `calendarType`
- this could mix `purnimanta` and `amanta` contexts during festival resolution near month boundaries

Status:

- fixed in current `HEAD`
- regression coverage added in `tests/MonthCalendarTest.php`

Affected file:

- [src/Panchanga/PanchangService.php](/home/shreesoftech/projects/panchang-core/src/Panchanga/PanchangService.php)

## Generated Output Changes

Generated JSON changed across:

- today output
- raw output
- month output
- festivals output
- eclipses output
- both `amanta` and `purnimanta`
- all bundled locales

Most generated diffs are expected because of:

- new yoga/shoola/vaasa data
- moon visibility logic changes
- month output expansion
- current-date regeneration

## Release Positioning

Suggested release title:

**`v5.0.0 — Yoga, Shoola, Vaasa & Moon Visibility Expansion`**

Short release framing:

- major new daily/snapshot data coverage
- civil-date-aware moon visibility output
- deep service decomposition and DI cleanup
- added regression coverage
- one accidental `calendarType` regression fixed before release
