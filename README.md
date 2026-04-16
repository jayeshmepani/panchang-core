# Panchang Core

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jayeshmepani/panchang-core.svg?style=flat-square)](https://packagist.org/packages/jayeshmepani/panchang-core)
[![Total Downloads](https://img.shields.io/packagist/dt/jayeshmepani/panchang-core.svg?style=flat-square)](https://packagist.org/packages/jayeshmepani/panchang-core)
[![PHP Version Require](https://img.shields.io/packagist/php-v/jayeshmepani/panchang-core?style=flat-square)](https://packagist.org/packages/jayeshmepani/panchang-core)
[![License: AGPL-3.0](https://img.shields.io/badge/license-AGPL--3.0-blue.svg?style=flat-square)](https://www.gnu.org/licenses/agpl-3.0.html)

**Authentic Vedic Panchanga calculation engine with Swiss Ephemeris precision** for PHP 8.3+.

This package provides high-precision calculations for Vedic Panchanga elements (Tithi, Vara, Nakṣatra, Yoga, Karaṇa), Muhūrta, Chogadiya, Hora, Karmakala windows, and 231 festival definitions with tradition/region profiles.

## 🎯 Unique Value Proposition

Key characteristics:
- ✅ Uses **Swiss Ephemeris FFI** for maximum astronomical precision
- ✅ Implements **classical Indian algorithms** from authentic texts
- ✅ Uses **IEEE 754 double precision** throughout the calculation pipeline
- ✅ Supports **231 festival definitions** with tradition/region resolution
- ✅ Works **standalone** (no Laravel required)

## Features

- **Complete Panchanga**: Tithi, Vara, Nakṣatra, Yoga, Karaṇa with precise fractions
- **231 festival definitions**: Holikā Dahan, Rāma Navamī, Kṛṣṇa Janmāṣṭamī, Dīpāvalī, Navaratri, Ekādaśī, Swaminarayan Jayantis, regional observances, etc.
- **Festival Families**: Multi-day celebrations (Holi, Diwali, Navaratri) with proper orchestration
- **Muhūrta Calculations**: Abhijit, Brahma Muhūrta, Rahu Kāla, Gulika, Yamaganda, Dur Muhūrta
- **Time Determination**: Chogadiya, Hora, Prahara, Lagna table, Bhadra/Vishti Karana detection with classical Mukha/Puchha subdivision
- **Karmakala Outputs**: Rahu Kāla/Gulika/Yamaganda, daylight fivefold division, Prahara, Sandhya blocks, Nishita, Vijaya, Godhuli, Gowri Panchangam, Kala Vela, Pradosha, Varjyam, Amrita Kaal
- **Localization**: English, Hindi, and Gujarati output via `PANCHANG_LOCALE` / `locale`
- **Calendar Type Support**: Amanta and Purnimanta month representation via `PANCHANG_CALENDAR_TYPE` / `calendar_type`
- **Tradition Profiles**: Smarta, Vaishnava, North, South, Bengal, Maharashtra, Tamil
- **Classical Accuracy**: Based on Sūrya Siddhānta, Muhūrta Chintāmaṇi, Nirṇaya Sindhu

## Installation

```bash
composer require jayeshmepani/panchang-core
```

### Requirements

- **PHP 8.3+** (uses typed constants, readonly classes, enums)
- **Swiss Ephemeris FFI** (`jayeshmepani/swiss-ephemeris-ffi`)
- **Carbon** (`nesbot/carbon`)
- **[FFI Extension](#ffi--system-setup)**
- **[Swiss Ephemeris Data Files](#ephemeris-files)**

## ⚙️ FFI & System Setup

The core engine relies on the PHP FFI (Foreign Function Interface) extension to communicate with the Swiss Ephemeris C library. This has been a **foundational architectural requirement** since the first version of the library to ensure maximum astronomical precision.

### 1. Install/Enable FFI Extension

#### Linux (Ubuntu/Debian)
```bash
# Install PHP FFI
sudo apt install php8.3-ffi

# Or for PHP 8.4
sudo apt install php8.4-ffi
```

#### Linux (CentOS/RHEL/Fedora)
```bash
sudo dnf install php-ffi
# or
sudo yum install php-ffi
```

#### macOS
```bash
# PHP from Homebrew includes FFI by default
brew install php@8.3
```

#### Windows
FFI is included with PHP 8.3+ for Windows. You just need to enable it in your `php.ini`.

### 2. Enable in `php.ini`
The FFI extension must be explicitly enabled. Add or uncomment the following in your `php.ini`:

```ini
extension=ffi
ffi.enable=1
```

> [!NOTE]
> `ffi.enable=1` is required for CLI usage. For web server usage (FPM/Apache), you may need to set `ffi.enable=preload` for better security and performance.

### 3. Verify Installation
```bash
php -r "echo extension_loaded('ffi') ? 'FFI loaded\n' : 'FFI not loaded\n';"
php -r "echo ini_get('ffi.enable') ? 'FFI enabled\n' : 'FFI not enabled\n';"
```

## 📂 Ephemeris Files

The package requires `.se1` data files for high-precision astronomical calculations. 

- **Download**: You can download the verified ephemeris files from [Swiss-Ephemeris-PHP Releases](https://github.com/jayeshmepani/Swiss-Ephemeris-PHP/releases/tag/ephe-files).
- **Setup**: Place these files in a directory (e.g., `/path/to/ephe`) and configure the path in your `.env` or `config/panchang.php`:

```bash
PANCHANG_EPHE_PATH=/absolute/path/to/ephe
```

## Usage

### Standalone Usage

```php
<?php
require 'vendor/autoload.php';

use JayeshMepani\PanchangCore\Panchanga\PanchangService;
use JayeshMepani\PanchangCore\Astronomy\AstronomyService;
use JayeshMepani\PanchangCore\Astronomy\SunService;
use JayeshMepani\PanchangCore\Panchanga\PanchangaEngine;
use JayeshMepani\PanchangCore\Panchanga\MuhurtaService;
use JayeshMepani\PanchangCore\Festivals\FestivalService;
use JayeshMepani\PanchangCore\Festivals\FestivalRuleEngine;
use JayeshMepani\PanchangCore\Festivals\Utils\BhadraEngine;
use SwissEph\FFI\SwissEphFFI;
use Carbon\CarbonImmutable;

// Initialize services
$sweph = new SwissEphFFI();
$ruleEngine = new FestivalRuleEngine();
$panchang = new PanchangService(
    $sweph,
    new SunService($sweph),
    new AstronomyService($sweph),
    new PanchangaEngine(),
    new MuhurtaService(),
    new FestivalService($ruleEngine),
    new BhadraEngine()
);

// Calculate panchanga for a specific date/location
$date = CarbonImmutable::parse('2026-03-24');
$details = $panchang->getDayDetails(
    date: $date,
    lat: 23.2472446,
    lon: 69.668339,
    tz: 'Asia/Kolkata'
);

// Access panchanga elements
echo "Tithi: " . $details['Tithi']['name'] . " (" . $details['Tithi']['paksha'] . ")\n";
echo "Nakshatra: " . $details['Nakshatra']['name'] . " (Pada " . $details['Nakshatra']['pada'] . ")\n";
echo "Yoga: " . $details['Yoga']['name'] . "\n";
echo "Karana: " . $details['Karana']['name'] . "\n";
echo "Vara: " . $details['Vara']['name'] . "\n";
```

### Laravel Usage

```php
<?php
use JayeshMepani\PanchangCore\Facades\Panchang;
use Carbon\CarbonImmutable;

// Calculate panchanga
$details = Panchang::getDayDetails(
    date: CarbonImmutable::parse('2026-03-24'),
    lat: 23.2472446,
    lon: 69.668339,
    tz: 'Asia/Kolkata'
);

// Festivals are included in day details
$festivals = $details['Festivals'];

// Full daily Muhurta evaluation (no manual field extraction required)
$muhurta = Panchang::getDailyMuhurtaEvaluation(
    date: CarbonImmutable::parse('2026-03-24'),
    lat: 23.2472446,
    lon: 69.668339,
    tz: 'Asia/Kolkata'
);

// Get month-wise calendar summary (ideal for grid views)
$calendar = Panchang::getMonthCalendar(
    year: 2026,
    month: 4,
    lat: 23.2472446,
    lon: 69.668339,
    tz: 'Asia/Kolkata'
);
```

### Package Helpers (Exact APIs)

`OutputGeneratorService` (for programmatic JSON payload assembly):

```php
generateFestivals(
    int $year,
    float $lat,
    float $lon,
    string $tz,
    float $elevation = 0.0,
    CalendarType|string $calendarType = CalendarType::Amanta,
): array

generateEclipses(
    int $startYear,
    int $endYear,
    float $lat,
    float $lon,
    string $tz,
): array

generateTodayPanchang(
    float $lat,
    float $lon,
    string $tz,
    float $elevation = 0.0,
    CalendarType|string $calendarType = CalendarType::Amanta,
): array

generateAll(
    int $festivalYear,
    int $eclipseStartYear,
    int $eclipseEndYear,
    float $lat,
    float $lon,
    string $tz,
    float $elevation = 0.0,
    CalendarType|string $calendarType = CalendarType::Amanta,
): array
```

`CliBootstrap` (for standalone script bootstrap and service wiring):

```php
CliBootstrap::init(string $baseDir): void
CliBootstrap::makePanchangService(): PanchangService
CliBootstrap::makeEclipseService(): EclipseService
CliBootstrap::makeOutputGenerator(PanchangService $panchang): OutputGeneratorService
```

## Classical Texts & Sources

This package implements algorithms from **authentic Sanskrit texts** with verified formulas.

**Important:** This is a **source-integrity map**, not a manuscript-critical proof. The codebase mixes:
- Direct Panchang/Jyotisha conventions (widely standard)
- Package rule mappings attributed to traditional literature
- Regional or published almanac conventions
- Modern secondary references
- Legacy/heuristic helpers

For complete details, see [docs/TRADITIONAL_TEXT_SOURCES.md](docs/TRADITIONAL_TEXT_SOURCES.md).

### Confidence Tiers

#### Tier 1: Direct or Standard Panchang Conventions

| Source | Implementation |
|--------|---------------|
| **Sūrya Siddhānta 1.29** | Tithi calculation (30 lunar days, 12° each) |
| **Sūrya Siddhānta 8.1** | Nakṣatra calculation (27 lunar mansions, 13°20' each) |
| **Sūrya Siddhānta 3.1-3** | Yoga calculation (27 combinations, 13°20' Sun-Moon sum) |
| **Muhūrta Chintāmaṇi Chapter 2** | Karana calculation (11 half lunar days, 6° each) |
| **Sūrya Siddhānta 1.10-1.11** | Muhūrta, Ghaṭikā, Pala time units |

#### Tier 2: Package Rule Mappings

| Source | Implementation |
|--------|---------------|
| **Muhūrta Chintāmaṇi** | Universal bad tithis, Vara-Tithi Yogas |
| **Bṛhat Saṃhitā** | Muhurta rules, Samvatsara, Ritu |
| **Māyamata** | Vāstu muhurta guidance |
| **Vaikhānasa Āgama** | Āgama-based muhurta guidance |
| **Aśvalāyana Gṛhya Sūtra** | Gṛhya-sūtra muhurta guidance |
| **Muhūrta Mārtaṇḍa** | Advanced muhurta calculations |
| **Gargiya Jyotisha** | Rikta Tithi dosha |

#### Tier 3: Festival-Resolution Logic

| Source | Implementation |
|--------|---------------|
| **Nirṇaya Sindhu** | Festival timing, Bhadra rules |
| **Muhūrta Chintāmaṇi** | Aruṇodaya, Pradoṣa, Ekadashi handling |
| **Hari Bhakti Vilāsa** | Vaishnava Ekadashi rules |

#### Tier 4: Published Panchang Conventions

| Source | Implementation |
|--------|---------------|
| **Tamil Gowri/Pambu Panchangam** | Gowri Panchangam (8-part day/night division) |
| **Sarāvalī** | Kala Vela rules (Rahu, Gulika, Yamaghantaka) |
| **Aṣṭāṅga Hṛdaya** | Brahma Muhurta timing |
| **Charaka Saṃhitā** | Muhurta concepts |
| **Manusmṛti** | Brahma Muhurta for Vedic study |
| **Sandhyāvandanam Tradition** | Sandhya windows (living tradition) |

#### Tier 5: Modern Systems

| Source | Implementation |
|--------|---------------|
| **Swiss Ephemeris** | Planetary longitudes, Ayanāṃśa, Vara |
| **KP System** | Varjyam (Visha Ghati) calculation |
| **Ernst Wilhelm's Classical Muhurta** | Bhadra subdivisions |

### What This Does NOT Claim

- ❌ Every package rule is 1:1 from a single primary text
- ❌ All regional or sectarian variants are covered
- ❌ Modern almanac conventions are identical across traditions
- ❌ Independent verification against critical Sanskrit editions

### Precision Guarantee

All calculations use:

- **IEEE 754 double precision** (53-bit significand)
- **No intermediate rounding** (lossless calculations)
- **Binary search convergence** (80 iterations, 1e-24 JD precision)

## 🛠️ Swiss Ephemeris Technical Specs

The core engine utilizes the Swiss Ephemeris (SwissEph) for maximum astronomical precision.

| Mode | Date Range | Precision | Requirement |
| :--- | :--- | :--- | :--- |
| **High Precision** | 13,201 BCE to 17,191 CE | 0.001 arcsec | `.se1` Data Files |
| **Standard (Moshier)** | 3,000 BCE to 3,000 CE | 0.1 arcsec | Built-in (Automatic) |

### Precision Details
- **Planetary/Solar**: 0.001 arcsec with DE431 files.
- **Lunar**: 3 arcsec (Moshier) / 0.001 arcsec (DE431).
- **Asteroids**: Main asteroids covered 5401 BCE to 5399 CE.

### Data File Structure (`.se1`)
Ephemeris data is split into 600-year files:
- **CE (AD) Dates**: Files prefixed with `sepl_` or `semo_` (e.g., `sepl_18.se1` for 1800-2400 CE).
- **BCE (BC) Dates**: Files prefixed with `seplm` or `semom`.
- **Asteroids**: Files prefixed with `se00` or `se0j`.

## 🪐 Ayanamsa Authority
For any authentic Hindu Panchanga (Tithi, Vara, Nakshatra, Yoga, Karana), **Lahiri (Chitra Paksha)** is the absolute mandatory legal and religious standard in India. 

The `panchang-core` engine is **permanently locked to Lahiri** to ensure 100% calculation integrity. Support for alternative Ayanamsas (Raman, KP, Fagan) has been intentionally removed to prevent "wrong" dates for festivals and Nakshatras.

## Configuration

### Laravel Configuration

Publish the config file:
```bash
php artisan vendor:publish --provider="JayeshMepani\PanchangCore\PanchangServiceProvider" --tag="panchang-config"
```

Edit `config/panchang.php`:
```php
return [
    'ephe_path' => env('PANCHANG_EPHE_PATH', __DIR__ . '/../ephe'),
    'defaults' => [
        'locale' => env('PANCHANG_LOCALE', 'en'), // en, hi, gu
        'calendar_type' => env('PANCHANG_CALENDAR_TYPE', 'amanta'), // amanta, purnimanta
        'measurement_system' => 'indian_metric',
        'date_time_format' => 'indian_12h',
        'time_notation' => '12h',
        'coordinate_format' => 'decimal',
        'angle_unit' => 'degree',
        'duration_format' => 'mixed',
    ],
    'festivals' => [
        'default_tradition' => 'Smarta',
        'default_region' => 'North',
        'supported_traditions' => ['Smarta', 'Vaishnava'],
        'supported_regions' => ['North', 'South', 'Bengal', 'Maharashtra', 'Tamil', 'Gujarat'],
    ],
    'cache' => [
        'enabled' => true,
        'ttl' => 86400,
        'prefix' => 'panchang_',
    ],
];
```

### Time Output Semantics

- All time-like fields now include date-qualified companions as `*_iso` (example: `varjyam_start_iso`).
- Panchang day context is `sunrise -> next sunrise`; times earlier than sunrise are dated to the next civil date.
- `Varjyam` supports multiple windows per day (`window_count`, `windows[]`) and keeps top-level compatibility keys.
- `Pradosha_Kaal` is computed from night-fraction plus Trayodashi overlap and returns both base window and effective overlap window.
- `getDayDetails()` now includes dedicated Karmakala outputs:
  - `Rahu_Kaal_Gulika_Yamaganda`
  - `Abhijit_Muhurta`
  - `Prahara_Full_Day`
  - `Daylight_Fivefold_Division`
  - `Brahma_Muhurta`
  - `Dur_Muhurta_Full_Day`
  - `Nishita_Muhurta`
  - `Vijaya_Muhurta`
  - `Godhuli_Muhurta`
  - `Sandhya`
  - `Gowri_Panchangam`
  - `Kala_Vela`
  - `Karmakala_Windows`
  - `Varjyam`
  - `Amrita_Kaal`
  - `Pradosha_Kaal`

### Hindu Calendar Output

- `getDayDetails()` and `getFestivalSnapshot()` now expose a richer `Hindu_Calendar` block.
- Returned keys include:
  - `Month_Amanta`
  - `Month_Purnimanta`
  - `Is_Adhika`
  - `Is_Kshaya`
  - `Amanta_Index`
  - `Purnimanta_Index`
- `PanchangService` resolves month names using exact amavasya-to-amavasya solar transit logic, so final month output can differ from simpler longitude-only month heuristics.

### Festival Output Metadata

Resolved festival objects include both localized display fields and calculation-basis metadata:

- Display fields: `name`, `description`, `deity`, `fasting`, `regions`, `aliases`
- Rule metadata: `calculation_basis.type`, `basis`, `tithi`, `paksha`, `month`, `solar_rashi`, `nakshatra`, `fixed_date`, `weekday`, `karmakala_type`, `adhika`, `relative_day`
- Resolver metadata: `resolution.standard_date`, `resolution.observance_date`, `resolution.is_tithi_vriddhi`, `resolution.is_tithi_kshaya`, `resolution.target_tithi_start_jd`, `resolution.target_tithi_end_jd`, and `rules_applied`
- Human-facing metadata has localized companion fields such as `basis_name`, `paksha_name`, `month.name`, `karmakala_type_name`, and `winning_reason_name`

The festival `calculation_basis.month` block follows the active calendar type only:

```json
{
  "calendar_type": "amanta",
  "value": "Chaitra",
  "name": "ચૈત્ર"
}
```

For `PANCHANG_CALENDAR_TYPE=purnimanta`, the same field returns the purnimanta rule month only. It does not emit both amanta and purnimanta month names in a single localized output.

### Raw JSON Exporter

Use this when you want the complete all-in-one package output in a single JSON file:

```bash
php scripts/panchang_raw_output.php > output.json
```

This exporter writes five top-level sections in one JSON file:
- `meta`: generation timestamp, location, timezone, and config source
- `festivals_2026`: all festival entries for the full year
- `eclipses_2026_2032`: all eclipse entries for 7 years
- `todays_complete_details`: full `getDayDetails()` payload for Bhuj (`Asia/Kolkata`)
- `muhurta_evaluation`: transit-only `getDailyMuhurtaEvaluation()` payload

Notes:
- `todays_complete_details` is intentionally date-sensitive and changes based on the day the script is run.
- `muhurta_evaluation` is transit-only; no natal/person-specific inputs are used.
- Empty arrays such as `Bhadra: []` or `Dharma_Sindhu: []` are valid outputs when no matching window exists for that Panchang day.
- Saṅkrānti festivals are assigned by civil-date ingress tagging (00:00-24:00 local day), so pre-sunrise ingress stays on the same calendar date.

### Standalone JSON Scripts

The `scripts/` directory contains standalone exporters for common validation and integration workflows:

```bash
php scripts/panchang_today.php
php scripts/panchang_month_output.php 2026 4 > month_2026_04.json
php scripts/panchang_eclipses.php 2026 2032
php scripts/panchang_festivals.php 2026
```

Notes:
- `panchang_today.php` writes `today_panchang.json` automatically and prints status text only. It does not emit raw JSON to stdout and does not accept a custom output filename.
- `panchang_month_output.php` prints JSON to stdout only; choose the output filename with shell redirection, e.g. `> month_2026_04.json`.
- `panchang_eclipses.php` writes `eclipses_YYYY_YYYY.json` automatically and prints status text only. It does not emit raw JSON to stdout and does not accept a custom output filename.
- `panchang_festivals.php` writes `festivals_YYYY.json` automatically and prints status lines only; do not redirect its stdout as if it were raw JSON.
- `panchang_raw_output.php` prints the complete all-in-one JSON to stdout only; choose the output filename with shell redirection, e.g. `> output.json`.
- Use `PANCHANG_LOCALE=en|hi|gu` and `PANCHANG_CALENDAR_TYPE=amanta|purnimanta` to generate localized/calendar-type variants.

To generate into a dedicated directory, redirect only stdout-based scripts and move implicit-output files after generation:

```bash
cd scripts

dir="output/amanta/gu"
mkdir -p "$dir"

PANCHANG_CALENDAR_TYPE=amanta PANCHANG_LOCALE=gu php panchang_today.php > /tmp/today.log
mv -f today_panchang.json "$dir/today.json"

PANCHANG_CALENDAR_TYPE=amanta PANCHANG_LOCALE=gu php panchang_festivals.php 2026 > /tmp/festivals.log
mv -f festivals_2026.json "$dir/festivals_2026.json"

PANCHANG_CALENDAR_TYPE=amanta PANCHANG_LOCALE=gu php panchang_month_output.php 2026 4 > "$dir/month_2026_04.json"

PANCHANG_CALENDAR_TYPE=amanta PANCHANG_LOCALE=gu php panchang_eclipses.php 2026 2032 > /tmp/eclipses.log
mv -f eclipses_2026_2032.json "$dir/eclipses_2026_2032.json"
```

### Muhurta APIs

`PanchangService` also exposes higher-level electional helpers:

- `getElectionalSnapshot()`
- `getDailyMuhurtaEvaluation()`

### Festival Catalog Notes

- The canonical source of truth is `FestivalService::FESTIVALS`.
- The current verified catalog contains `231` festival definitions (`FestivalService::FESTIVALS`).
- Festivals that legitimately share the same tithi or civil date remain separate entries when they represent different observances.
- Use `PanchangService::getFestivalYearCalendar()` for complete year-wide festival output. It performs package-side date iteration, relative `day_after` festival handling, adjacent duplicate consolidation, and yearly single-observance consolidation for configured exceptions.
- `FestivalService::getFestivalsForYear()` is intentionally disabled; `FestivalService` owns the catalog/rule payloads, while `PanchangService` owns location-aware Panchang orchestration.

### Standalone Configuration

```php
use JayeshMepani\PanchangCore\Panchanga\PanchangService;

// Configure before instantiation
PanchangService::configure(
    ephePath: '/path/to/ephe'
);
```

## Supported Festival Definitions (231)

### Solar-Based (Saṅkrānti)
- Makara Saṅkrānti (solar ingress-based; civil date can vary by year/location)
- Viṣṇu Pūjā (Sep 17)

### Tithi-Based (Major)
- Vasanta Pañcamī (Māgha Śukla Pañcamī)
- Mahā Śivarātri (Māgha Kṛṣṇa Chaturdaśī)
- Holikā Dahan (Phālguna Śukla Pūrṇimā)
- Rāma Navamī (Chaitra Śukla Navamī)
- Kṛṣṇa Janmāṣṭamī (Bhādrapada Kṛṣṇa Aṣṭamī)
- Gaṇeśa Chaturthī (Bhādrapada Śukla Chaturthī)
- Dīpāvalī (Kārttika Kṛṣṇa Amāvāsyā)

### Recurring
- Ekādaśī (twice monthly)
- Pradoṣa (twice monthly)
- Saṅkrānti (monthly)

### Festival Families (Multi-Day)
- **Holi**: Holikā Dahan → Dhuleti
- **Diwali**: Dhanteras → Naraka Chaturdaśī → Lakṣmī Pūjā → Govardhan Pūjā → Bhai Dūj
- **Janmāṣṭamī**: Smarta (Aṣṭamī at sunrise) vs Vaishnava (Aṣṭamī + Niśīta + Rohiṇī)

## Testing

```bash
# Run all tests
composer test

# Check code quality
composer quality
```

## Development

```bash
# Clone repository
git clone https://github.com/jayeshmepani/panchang-core.git
cd panchang-core

# Install dependencies
composer install

# Run tests
composer test
```

## 📊 Calculation Coverage

| Category | Elements | Status |
|----------|----------|--------|
| **Panchanga** | Tithi, Vara, Nakṣatra, Yoga, Karaṇa | ✅ Complete |
| **Muhūrta** | 30 Muhūrtas (15 day + 15 night), Abhijit, Brahma, Dur Muhūrta | ✅ Complete |
| **Kāla Nirṇaya** | Chogadiya, Hora, Rahu Kāla, Gulika, Yamaganda, Bhadra | ✅ Complete |
| **Karmakala** | Rahu Kāla/Gulika/Yamaganda, daylight fivefold division, Prahara, Sandhya, Nishita, Vijaya, Godhuli, Gowri Panchangam, Kala Vela, Pradosha, Varjyam, Amrita Kaal | ✅ Complete |
| **Festivals** | 231 festival definitions | ✅ Complete |
| **Traditions** | Smarta, Vaishnava, regional | ✅ Complete |

## Full System Requirements


### Core Requirements

- **PHP**: 8.3 or higher
- **Composer**: For package installation
- **FFI Extension**: Required for Swiss Ephemeris FFI

### Dependencies

- **jayeshmepani/swiss-ephemeris-ffi** ^1.0 - Swiss Ephemeris PHP FFI wrapper
- **nesbot/carbon** ^3.0 - Date/time library

## 📚 Documentation

- [Swiss Ephemeris Programmer's Documentation](https://www.astro.com/swisseph/swephprg.htm)
- [Sūrya Siddhānta Translation](https://archive.org/details/suryasiddhantate00sury)
- [Muhūrta Chintāmaṇi Translation](https://archive.org/details/muhurtachintama00vara)

## 🤝 Contributing

Open an issue or pull request on GitHub for bug reports, source audits, or feature additions.

## 📞 Support

- **Issues**: [GitHub Issues](https://github.com/jayeshmepani/panchang-core/issues)
- **Email**: [jayeshmepani777@gmail.com](mailto:jayeshmepani777@gmail.com)
- **Exporter Script**: [`scripts/panchang_raw_output.php`](scripts/panchang_raw_output.php)

## 💖 Funding

If you find this package helpful, consider sponsoring the development:

[![Sponsor on GitHub](https://img.shields.io/badge/sponsor-%E2%9D%A4-%23EA4AAA?logo=github&style=flat-square)](https://github.com/sponsors/jayeshmepani)

## 📄 License

This repository is licensed under the **GNU Affero General Public License v3.0 (AGPL-3.0)**. 

### Swiss Ephemeris Licensing
This core engine utilizes the **Swiss Ephemeris**, which has a dual-licensing model:
1.  **Open Source**: Licensed under **GNU AGPLv3**. If you use this package in an open-source project, you must also use AGPLv3.
2.  **Commercial**: If you wish to use this package in a closed-source or commercial application, you **MUST** purchase a commercial license from [Astrodienst AG](https://www.astro.com/swisseph/swephprg.htm#licence).

See the [LICENSE](LICENSE) file for the full license text.

## 🙏 Credits

- **[Dieter Koch](https://www.astro.com/swisseph/)** - Swiss Ephemeris C Library Author
- **[Astrodienst AG](https://www.astro.com/)** - Swiss Ephemeris Maintainers
- **Classical Texts**: Sūrya Siddhānta, Muhūrta Chintāmaṇi, Nirṇaya Sindhu, Dharma Sindhu

---

Built for Vedic astrology and astronomy workflows.
