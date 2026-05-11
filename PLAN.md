# Panchang Core Refactoring Plan

This document records the current architectural refactoring plan for reducing the large service classes without changing public output semantics.

The primary goal is not to redesign the package. The goal is to preserve the existing calculation behavior, JSON structure, precision, localization, scripts, tests, and generated outputs while moving focused rule systems into smaller auditable classes.

## Current Monolithic Files

### `src/Panchanga/PanchangService.php`

`PanchangService` is the main public orchestration layer, but it also contains low-level astronomical interval math, special yoga rule systems, vaasa and shoola lookups, dosha windows, festival snapshot assembly, Ekadashi observance logic, and several transition helpers.

This class currently mixes these responsibilities:

- Public API orchestration:
  - `getDayDetails()`
  - `getFestivalSnapshot()`
  - year/month calendar assembly helpers
- Astronomical crossing and interval tracking:
  - tithi intervals
  - nakshatra intervals
  - 28-nakshatra Abhijit-inclusive intervals
  - nakshatra pada intervals
  - Sun nakshatra intervals
  - Moon sign transitions
  - Moon longitude range windows
  - generic angle crossing search
- Special yoga systems:
  - Sarvartha Siddhi
  - Amrit Siddhi
  - Ravi Yoga
  - Ravi Pushya
  - Guru Pushya
  - Dwipushkar
  - Tripushkar
  - Ganda Mula
  - Vinchhudo
  - Aadal
  - Vidaal
  - Jwalamukhi
  - Anandadi Yoga
  - Amritadi Yoga
  - Maitreya Yoga
  - Gajachchhaya Yoga
- Direction and vaasa systems:
  - Disha Shool
  - Nakshatra Shool
  - Rahu Vaasa
  - Chandra Vaasa
  - Shiva Vaasa
  - Agni Vaasa
  - Yogini Vaasa
- Dosha and negative-window systems:
  - Panchak
  - Bhadra / Vishti periods
  - Varjyam-related live windows
- Vrata and fasting support:
  - Ekadashi observance
  - Parana payloads
  - Pradosha-related overlap handling

### `src/Panchanga/MuhurtaService.php`

`MuhurtaService` is smaller, but it still mixes several independent time-division systems:

- Hora
- Chogadiya
- Abhijit Muhurta
- Brahma Muhurta
- Dur Muhurta
- Nishita Muhurta
- Vijaya Muhurta
- Godhuli Muhurta
- Sandhya
- Prahara
- Daylight fivefold division
- Rahu Kaal, Gulika, Yamaganda
- Varjyam
- Amrita Kaal
- Pradosha Kaal
- Gowri Panchangam
- Lagna table generation

## Refactoring Principles

- Preserve all public array keys unless a versioned breaking change is explicitly planned.
- No public API change unless separately approved and versioned.
- Preserve raw numeric precision. Do not introduce rounding, normalization, tolerance bands, or higher-level interpretation during extraction.
- Every algorithm, formula, and rule table must move losslessly. Only intentional structural changes for reorganization/refactoring/restructuring are allowed.
- Move code mechanically first, then improve internals only after behavior is locked by tests.
- Keep `PanchangService` as the public orchestrator until a deliberate public API redesign is planned.
- Prefer a few domain calculators first. Avoid creating one tiny class per yoga until the extracted domain proves stable.
- Every extracted calculator must have regression coverage before or during extraction.
- Generated JSON changes must be intentional and reviewed separately from structural refactors.
- If a refactor changes data dependencies used by scripts or generators, update the relevant scripts in the same change set.

## Phase 1: Extract Astronomical Math And Intervals

This is the safest first step because many higher-level systems depend on these utilities.

Target classes:

- `src/Astronomy/Math/TransitEngine.php`
- `src/Astronomy/Math/IntervalTracker.php`

Candidate methods to move:

- `findAngleCrossing()`
- `signedDiff()`
- `collectTithiIntervals()`
- `collectNakshatraIntervals()`
- `collectNakshatra28Intervals()`
- `collectNakshatraPadaIntervals()`
- `collectSunNakshatraIntervals()`
- `collectMoonSignTransitions()`
- `collectMoonLongitudeRangeWindows()`
- `getTithiIntervalAtJd()` if it is only used as interval infrastructure
- low-level longitude/sign boundary helpers that are not part of the public service API

Expected result:

- `PanchangService` asks the interval layer for exact windows.
- Special yogas, shoola, vaasa, and dosha calculators reuse one shared interval source.
- Angle-crossing behavior remains centralized and easier to test.

## Phase 2: Extract Vaasa And Shoola Systems

These are table/rule based and have clear boundaries, so they are a good second extraction.

Target classes:

- `src/Panchanga/Residences/VaasaCalculator.php`
- `src/Panchanga/Residences/ShoolaCalculator.php`

Move to `VaasaCalculator`:

- `calculateShivaVaasa()`
- `calculateAgniVaasa()`
- `calculateChandraVaasa()`
- `calculateRahuVaasa()`
- `calculateYoginiVaasa()`
- related constants:
  - `SHIVA_VAASA_METHOD1`
  - `SHIVA_VAASA_METHOD2`
  - `SHIVA_VAASA_LABELS`
  - `SHIVA_VAASA_EFFECTS`
  - `AGNI_VAASA_LABELS`
  - `AGNI_VAASA_EFFECTS`
  - `YOGINI_VAASA_MAP`
  - `CHANDRA_VAASA_PADA_ABODES`
  - `CHANDRA_VAASA_PADA_QUALITY`

Move to `ShoolaCalculator`:

- `calculateDishaShool()`
- `calculateNakshatraShool()`
- `nakshatraShoolDirection()`
- related constants:
  - `DIRECTION_LABELS`
  - `NAKSHATRA_SHOOL_DIRECTIONS`

Expected result:

- Direction and residence outputs are isolated from astronomical and festival orchestration.
- Rule tables become easier to audit and compare with traditional sources.

## Phase 3: Extract Special Yoga Systems

Start with one domain-level calculator instead of many tiny calculators.

Target class:

- `src/Panchanga/Yogas/SpecialYogaCalculator.php`

Candidate methods and constants:

- `calculateSpecialYogas()`
- `calculateAnandadiYoga()`
- `calculateAmritadiYoga()`
- `calculateMaitreyaYoga()`
- `calculateGajachchhayaYoga()`
- `matchRaviYogaWindows()`
- `matchJwalamukhiWindows()`
- `matchPushkaraWindows()`
- `matchAadalVidaalWindows()`
- `matchGajachchhayaWindows()`
- `anyVariantPresent()`
- constants for Sarvartha Siddhi, Amrit Siddhi, Pushkara, Ravi Yoga, Aadal, Vidaal, Jwalamukhi, Anandadi, and Amritadi tables

Possible later split, only if needed:

- `AnandadiYogaCalculator`
- `AmritadiYogaCalculator`
- `GajachchhayaYogaCalculator`
- `CombinatorialYogaCalculator`

Expected result:

- Yoga rule systems are auditable as one package domain.
- `PanchangService` only asks for daily yoga payloads.

## Phase 4: Extract Dosha And Window Systems

Target classes:

- `src/Panchanga/Doshas/PanchakCalculator.php`
- `src/Panchanga/Doshas/BhadraCalculator.php`
- `src/Panchanga/Doshas/VarjyamWindowCalculator.php`

Candidate methods:

- `calculatePanchak()`
- `findBhadraPeriods()`
- `calculateVarjyamWindows()`
- related Bhadra and negative-window helpers

Expected result:

- Negative windows become independently testable.
- Bhadra/Vishti logic stops living inside the top-level panchang orchestrator.

## Phase 5: Extract Vrata And Parana Logic

Target class:

- `src/Panchanga/Vrata/EkadashiParanaCalculator.php`

Candidate methods:

- `buildEkadashiObservance()`
- `buildParanaPayload()`
- Ekadashi/Parana helper logic currently embedded in `PanchangService`

Expected result:

- Fasting observance logic is separated from base panchang assembly.
- Festival and vrata behavior can evolve without expanding `PanchangService`.

## Phase 6: Split `MuhurtaService`

This should happen after `PanchangService` has been reduced, because many current outputs depend on MuhurtaService as a stable dependency.

Candidate classes:

- `src/Muhurta/Planetary/HoraCalculator.php`
- `src/Muhurta/Planetary/ChogadiyaCalculator.php`
- `src/Muhurta/Classical/DailyPeriodsCalculator.php`
- `src/Muhurta/Classical/InauspiciousPeriodsCalculator.php`
- `src/Muhurta/Regional/GowriPanchangamCalculator.php`
- `src/Muhurta/Lagna/LagnaTableCalculator.php`

Suggested grouping:

- `HoraCalculator`: hora and full-day hora table.
- `ChogadiyaCalculator`: day/night chogadiya and full chogadiya table.
- `DailyPeriodsCalculator`: Abhijit, Brahma, Dur, Nishita, Vijaya, Godhuli, Sandhya, Prahara, daylight fivefold division.
- `InauspiciousPeriodsCalculator`: Rahu Kaal, Gulika, Yamaganda, Varjyam, Amrita Kaal, Pradosha Kaal if kept in the same screening domain.
- `GowriPanchangamCalculator`: Gowri Panchangam only.
- `LagnaTableCalculator`: lagna table generation.

## Public Orchestrator Target

After extraction, `PanchangService` should mainly:

- normalize request date/location/timezone inputs;
- calculate sunrise, sunset, next sunrise, and base longitudes;
- request interval windows from the interval layer;
- request yoga/dosha/vaasa/shoola/vrata payloads from focused calculators;
- assemble the existing public output array.

It should not:

- own low-level crossing math;
- own large rule tables;
- directly implement every yoga and vaasa;
- contain festival scoring or consolidation internals;
- contain regional muhurta time-division formulas.

## Current Feature Gap

`Kaal_Vaasa` remains intentionally unimplemented until a reliable complete rule table/source is confirmed.

Do not add `Kaal_Vaasa` from partial snippets. If implemented later, it should include:

- explicit `rule_system`;
- source-family metadata;
- complete table or formula;
- tests for all rule branches;
- localization for user-facing labels;
- generated output updates.

## Verification Required After Each Extraction

Run at minimum:

```bash
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=2G
vendor/bin/phpunit
```

For changes that affect exported JSON, also regenerate and inspect the relevant files under `scripts/output`.
For changes that affect CLI generation or release packaging, update the scripts under `scripts/` so they continue to produce identical outputs from the new file layout.

## Documentation Update Rule

If any refactor, reorganization, or restructuring changes anything visible to users or maintainers, update the corresponding documentation in the same change set. Treat the HTML docs and markdown docs as part of the public contract, not as optional commentary.

This includes:

- markdown files such as `README.md`, `PACKAGE_COVERAGE.md`, `docs/TRADITIONAL_TEXT_SOURCES.md`, and any other package notes or reference files
- HTML documentation such as `docs/index.html`
- usage snippets, method examples, constructor examples, parameter examples, and return-shape examples
- public API descriptions, output payload explanations, feature coverage tables, and generated output references
- any script documentation or generator instructions that become stale after the code move

The HTML documentation is especially important because it shows end-user usage patterns. If the refactor changes any of the following, the HTML doc must be updated:

- class names
- constructor arguments
- method names
- parameter order or parameter types
- return structure
- example usage flow
- any snippet that references moved code

If the refactor is only an internal move and all of the above remain identical, the snippets do not need to change.

The rule is simple:

- if the public API, output shape, coverage list, or example usage changes, update the docs immediately
- if the refactor is internal only and every visible contract remains identical, doc updates are optional
- if in doubt, prefer updating the docs rather than leaving stale instructions behind
