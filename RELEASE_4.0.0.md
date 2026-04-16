# Panchang Core v4.0.0

> **Major release since v3.0.0** — Localization, Dual Calendar Support, Festival Engine Hardening, and Architectural Refinements.

---

## Highlights

This release introduces a **complete multi-language localization system** (English, Hindi, Gujarati), **native dual calendar support** (Amanta & Purnimanta), a significantly expanded and hardened **festival rule engine** (163 → 231 entries), enriched **structured output objects** for solar/lunar events, and deep **enum-level refactoring** for correctness and consistency. Documentation, generated outputs, and CLI tooling have all been substantially upgraded.

---

## 🚀 New Features

### Localization System
- **Multi-language support**: Complete localization for English (`en`), Hindi (`hi`), and Gujarati (`gu`) across all panchang elements.
- **New `Localization` class**: Centralized translation service covering:
  - Nakshatras (27), Varas (7), Tithis (30), Pakshas (2), Masas (12), Ritus (6), Samvatsaras (60), Rashis (12), Yogas (27), Karanas (11), Muhurtas (30), Choghadiyas (7), Horas (7), Planets (9)
  - Festival names, descriptions, deity names, aliases, and reason labels
  - String constants and error messages
- Filled missing translations for user-facing fields to reduce mixed-language leaks in localized outputs.
- Canonical machine identifiers (e.g. `*_key`, enum-like IDs) are preserved intentionally for API stability.

### Calendar Type Support
- **New `CalendarType` enum**: Type-safe enum for `Amanta` and `Purnimanta` calendar systems.
- **Dual calendar output**: Panchanga payload now includes both `Month_Amanta` / `Month_Amanta_En` and `Month_Purnimanta` / `Month_Purnimanta_En` fields.
- **`Calendar_Type` field**: Added to both Hindu Calendar and Panchanga output objects.
- **Updated config defaults**: Default locale changed to `en`; default calendar type changed to `amanta`.
- Added clarifying comments for calendar type options in `config/panchang.php`.

### Festival Engine Enhancements
- **Expanded festival registry**: Grew from **163 to 231** entries with aliases, descriptions, and enriched rule definitions.
- **Nakshatra-based festival resolution**: Support for festivals like Onam and Thai Poosam that depend on nakshatra rather than tithi.
- **Improved kshaya/vriddhi handling**: New `kshaya_preference` and `prefer_first_karmakala` options for edge-case date resolution.
- **`PanchangService::getFestivalYearCalendar()`**: Now accepts a `calendarType` parameter for calendar-aware yearly orchestration.
- **Yearly single-observance filtering**: Prevents duplicate festival entries for applicable festivals.
- **Metadata normalization**: Festival payloads standardized for both machine and UI consumption.

### Structured Output Objects
- `Sunrise`, `Sunset`, `Moonrise`, `Moonset` in the Panchanga payload now return **structured objects** with fields: `jd` (Julian Day), `iso`, `display`, `timestamp` — replacing flat strings.
- **Improved Sankranti detection**: Now spans the full civil day (midnight-to-midnight) instead of sunrise-to-sunrise, preventing near-sunrise misses.

### New CLI Scripts
- `scripts/panchang_today.php` → writes `today_panchang.json`
- `scripts/panchang_festivals.php <year>` → writes `festivals_<year>.json`
- `scripts/panchang_eclipses.php <from> <to>` → writes `eclipses_<from>_<to>.json`
- `scripts/panchang_month_output.php <year> <month>` → stdout JSON
- `scripts/panchang_raw_output.php` → stdout combined JSON

### New `OutputGeneratorService`
- Centralized service for generating JSON outputs: festivals, eclipses, today, month, and raw panchang data.

### New `CliBootstrap` Trait
- Streamlines CLI script setup and service dependency injection.

---

## 🐛 Bug Fixes

### Festival Resolution
- Fixed date resolution for **kshaya (skipped) tithis**.
- Fixed **vriddhi tithi** handling with proper preference options.
- Fixed **nakshatra-based festival resolution** (e.g., Onam, Thai Poosam).
- Added **month constraint filtering** for nakshatra-based festivals.

### Sankranti Detection
- Fixed edge cases where Sankrantis occurring near sunrise were silently missed.
- Detection window now spans the full civil day.

### PHP 8.3+ Compatibility
- Fixed environment variable reading by transitioning from `getenv()` to `$_ENV`.

### Test Integrity
- Fixed assertions for the newly nested `Sunrise` object structure.
- Fixed Tithi name assertion — now correctly returns the full form (e.g., `"Shukla Pratipada"` instead of `"Pratipada"`).

---

## ⚡ Improvements / Refactors

### Enum Localization
All core enums now delegate `getName()` to the `Localization` service:
`Nakshatra`, `Vara`, `Tithi`, `Paksha`, `Masa`, `Ritu`, `Samvatsara`, `Rasi`, `Yoga`, `Karana`, `Muhurta`, `Choghadiya`, `Hora`, `VimshottariDasha`
— signature: `getName(?string $locale = null)`

### `Paksha` Enum Refactor
- Changed from string enum to **int enum** (`Shukla = 0`, `Krishna = 1`).
- Added helper methods: `opposite()`, `isShukla()`, `isKrishna()`.
- Added `getRawName()` for programmatic use.

### `Muhurta` Enum Expansion
- Expanded from **15 to 30 cases** (15 day + 15 night muhurtas).
- Added `getDaySequence()` and `getNightSequence()` static methods.

### `Hora` Enum Refactor
- Replaced `fromTime()` with `getSequence(Vara $vara)` returning the full 24-hour sequence.
- Added `toPlanetIndex()` for Localization mapping.

### `Karana` Enum Fixes
- Corrected typo: `Kimstughna` → `Kintughna`.
- Normalized index values from 1–11 to **0–10**.
- Added `getFromLongitudes()` for direct longitude-based calculation.

### `Masa` Enum Changes
- Added `fromSunLongitude()` and `fromAmantaIndex()` static methods.
- Removed `getEnglishApproximation()` and `getRulingNakshatra()`.

### `Ritu` Enum Changes
- Added `fromSunLongitude()` static method.
- Removed `getEnglishName()` and `getMonths()`.

### `Yoga` Enum Fixes
- Corrected typos: `Vishkambha` → `Vishkumbh`, `Aindra` → `Indra`.
- Added `getFractionRemaining()` static method.

### `Nakshatra` Enum
- `getRulingPlanet()` now delegates to `VimshottariDasha::fromNakshatra()` for consistency.

### `Choghadiya` Refinement
- Corrected day and night sequences for all weekdays.

### `MuhurtaService` Refactor
- Refactored `calculateBrahmaMuhurta()` to reference `nextSunrise` instead of `sunrise` for proper accuracy.
- Added weekday parameter to `calculateDurMuhurta()`.

### `FestivalRuleEngine`
- Significant expansions: ~199 insertions of new festival rules and resolution logic.

### `FestivalService`
- Major expansion: ~1,008 insertions of additional festival definitions and processing logic.

### `PanchangService`
- Core logic updates: ~390 insertions/deletions across calendar-type awareness and orchestration.

### `ElectionalEvaluator`
- Significant enhancements: ~305 insertions/deletions for improved electional astrology calculations.

### `EclipseService`
- Added localization for eclipse type and visibility reason messages.

---

## 💥 Breaking Changes

### API Method Signatures
- **All enum `getName()` methods**: Now accept an optional `?string $locale` parameter.
- **`PanchangService::getDayDetails()`**: Now requires a `CalendarType|string $calendarType` parameter.
- **`PanchangService::getFestivalSnapshot()`**: Now requires a `CalendarType` parameter.

### Output Structure Changes
- **`Sunrise`, `Sunset`, `Moonrise`, `Moonset`**: Changed from flat strings to nested objects. Use the `display` key to retrieve the previous string value.

### Enum Value Changes
| Enum | Change |
|------|--------|
| `Paksha` | Values changed from strings (`'Shukla'`, `'Krishna'`) to integers (`0`, `1`) |
| `Karana` | Index values changed from 1–11 to 0–10 |
| `Muhurta` | Expanded from 15 to 30 cases |

### Removed Methods
| Method | Replacement |
|--------|-------------|
| `Masa::getEnglishApproximation()` | — |
| `Masa::getRulingNakshatra()` | — |
| `Ritu::getEnglishName()` | Use `getName('en')` via Localization |
| `Ritu::getMonths()` | — |

### Config Defaults Changed
| Setting | Old Default | New Default |
|---------|-------------|-------------|
| Locale | `hi` | `en` |
| Calendar Type | `purnimanta` | `amanta` |

### Removed Test Artifacts
- `tests/output.json` (12,135 lines of generated legacy data)
- `tests/panchang_raw_output.php` (247 lines)

---

## 📦 Other Changes

### Dependency Updates
- **PHP 8.3+** has been the minimum requirement since v1.0.0. The `$_ENV` usage (replacing `getenv()`) aligns internal implementation with this long-standing constraint.
- **PHP FFI Extension**: Mandatory requirement for Swiss Ephemeris astronomical calculations (foundational to the library since v1.0.0; now formally documented). Must be enabled in `php.ini` (`ffi.enable=1`).
- **Ephemeris Data Files**: Required for high-precision calculations. Download instructions added to `README.md` and Documentation.
- `composer.json` updated (minor version bump); rector script scope expanded for broader code processing.

### Documentation Updates
- `README.md`: Thoroughly updated with localization usage, dual-calendar examples, script usage patterns, and enriched festival payload structure.
- `docs/index.html`: Refreshed to reflect current feature coverage.
- `docs/TRADITIONAL_TEXT_SOURCES.md`: Refined and synced.
- Coverage now documents: machine-key vs display-field conventions, package helpers, and localized vs canonical field guidance.

### Regenerated Output Files
All output matrices regenerated for 3 locales × 2 calendar types:
- Festivals for 2026 (all locales)
- Eclipses 2026–2032 (all locales)
- Monthly output for April 2026 (all locales)
- Raw output 2026–2032 (all locales)
- Today files (all locales)

---

## ⬆️ Upgrade Guidance

**If your code calls enum `getName()`**: No change required — the `$locale` parameter is optional and defaults to the configured locale.

**If your code reads `PanchangService::getDayDetails()` or `getFestivalSnapshot()`**: Pass a `CalendarType` value (e.g., `CalendarType::Amanta`) as the new required parameter.

**If your UI reads Sunrise/Sunset/Moonrise/Moonset**: These are now objects. Update your reads to use the `display` field for the previous string value:
```php
// Before
$sunrise = $panchanga['Sunrise']; // "06:12:34"

// After
$sunrise = $panchanga['Sunrise']['display']; // "06:12:34"
```

**If your code checks `Paksha` values**: Update comparisons from strings (`'Shukla'`) to integers (`0` for Shukla, `1` for Krishna), or use the new helper methods (`isShukla()`, `isKrishna()`).

**For festival JSON consumers**: Prefer localized display fields (`name`, `description`, `deity`) for UI rendering. Treat `*_key` fields as stable canonical identifiers.

---

## Full Changelog

https://github.com/jayeshmepani/panchang-core/compare/2154de7e861b3a9b4a0c86f032aa59adbbe3b700..f985128f11cf81c0944348d99aec9941a52f34aa
