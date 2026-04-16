## Title

v4.0.0 - Localization Hardening, Festival Engine Refinements, and Documentation Sync

## Overview

This release strengthens festival-date correctness, hardens localization coverage across `en`/`hi`/`gu`, and continues the package-first architecture shift where orchestration logic lives in core services and scripts act as wrappers.

It also aligns documentation and generated outputs with current package behavior for both calendar conventions (`amanta`, `purnimanta`).

## Highlights

- Festival registry verified and normalized:
  - `FestivalService::FESTIVALS` currently verified at **231** entries (231 unique top-level keys).
- Localization hardening across festival payloads:
  - Filled missing translations for user-facing fields (`name`, `description`, `deity`, aliases, reason labels).
  - Reduced mixed-language leaks in generated localized outputs.
  - Preserved canonical machine identifiers where intentional (`*_key`, enum-like IDs).
- Package-centric festival orchestration:
  - Year-level festival generation remains package-owned via `PanchangService`.
  - Scripts remain thin wrappers over package services.
- Festival engine refinements:
  - Improved edge-case handling for tithi-based resolution paths.
  - Better consistency for `amanta` vs `purnimanta` date behavior.
  - Metadata normalization for machine + UI consumption.
- Output quality and generation reliability:
  - Regenerated output matrices for 3 locales x 2 calendar types.
  - Standardized script outputs and validated JSON structure.

## Festival API and Architecture

- Package-level year festival API is the intended integration path:
  - `PanchangService::getFestivalYearCalendar(...)`
- Responsibility split:
  - `FestivalService`: festival catalog + rule-level payload construction.
  - `PanchangService`: date orchestration, calendar context, yearly aggregation.
- Script behavior (wrapper-oriented):
  - `scripts/panchang_today.php` -> writes `today_panchang.json`
  - `scripts/panchang_festivals.php <year>` -> writes `festivals_<year>.json`
  - `scripts/panchang_eclipses.php <from> <to>` -> writes `eclipses_<from>_<to>.json`
  - `scripts/panchang_month_output.php <year> <month>` -> stdout JSON
  - `scripts/panchang_raw_output.php` -> stdout combined JSON

## Documentation Sync

Updated and aligned documentation:

- `README.md`
- `docs/index.html`
- `docs/TRADITIONAL_TEXT_SOURCES.md`

Coverage now reflects:

- localization support (`en`, `hi`, `gu`)
- calendar-type support (`amanta`, `purnimanta`)
- package helpers and script usage patterns
- enriched festival payload structure
- machine-key vs display-field conventions

## Validation

- Regenerated outputs for:
  - locales: `en`, `hi`, `gu`
  - calendar types: `amanta`, `purnimanta`
- Verified output file completeness for expected script artifacts.
- JSON parse validation completed on generated files.

## Notes

- Some English/canonical fields intentionally remain for API stability:
  - `*_key`
  - selected enum/value identifiers
  - explicit `english_name` fields
- Localized counterparts are provided for display use-cases.

## Upgrade Guidance

If your UI renders festival JSON directly:

- Prefer localized display fields:
  - `name`, `description`, `deity`, localized `*_name` fields
- Treat canonical keys as stable integration identifiers:
  - `festival_name_key`, `winning_reason_key`, enum/value keys

