# Panchang Core

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jayeshmepani/panchang-core.svg?style=flat-square)](https://packagist.org/packages/jayeshmepani/panchang-core)
[![Total Downloads](https://img.shields.io/packagist/dt/jayeshmepani/panchang-core.svg?style=flat-square)](https://packagist.org/packages/jayeshmepani/panchang-core)
[![PHP Version Require](https://img.shields.io/packagist/php-v/jayeshmepani/panchang-core?style=flat-square)](https://packagist.org/packages/jayeshmepani/panchang-core)
[![License: AGPL-3.0](https://img.shields.io/badge/license-AGPL--3.0-blue.svg?style=flat-square)](https://www.gnu.org/licenses/agpl-3.0.html)

**Authentic Vedic Panchanga calculation engine with Swiss Ephemeris precision** for PHP 8.3+.

This package provides high-precision calculations for Vedic Panchanga elements (Tithi, Vara, Nakṣatra, Yoga, Karaṇa), Muhūrta, Chogadiya, Hora, Karmakala windows, and 163 festival definitions with tradition/region profiles.

## 🎯 Unique Value Proposition

Key characteristics:
- ✅ Uses **Swiss Ephemeris FFI** for maximum astronomical precision
- ✅ Implements **classical Indian algorithms** from authentic texts
- ✅ Uses **IEEE 754 double precision** throughout the calculation pipeline
- ✅ Supports **163 festival definitions** with tradition/region resolution
- ✅ Works **standalone** (no Laravel required)

## Features

- **Complete Panchanga**: Tithi, Vara, Nakṣatra, Yoga, Karaṇa with precise fractions
- **163 festival definitions**: Holikā Dahan, Rāma Navamī, Kṛṣṇa Janmāṣṭamī, Dīpāvalī, Navaratri, Ekādaśī, Swaminarayan Jayantis, etc.
- **Festival Families**: Multi-day celebrations (Holi, Diwali, Navaratri) with proper orchestration
- **Muhūrta Calculations**: Abhijit, Brahma Muhūrta, Rahu Kāla, Gulika, Yamaganda, Dur Muhūrta
- **Time Determination**: Chogadiya, Hora, Prahara, Lagna table, Bhadra/Vishti Karana detection with classical Mukha/Puchha subdivision
- **Karmakala Outputs**: Daylight fivefold division, Sandhya blocks, Nishita, Vijaya, Godhuli, Gowri Panchangam, Kala Vela, Pradosha, Varjyam, Amrita Kaal
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
- **FFI Extension** (for Swiss Ephemeris)

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
use JayeshMepani\PanchangCore\Festivals\FestivalFamilyOrchestrator;
use JayeshMepani\PanchangCore\Festivals\Utils\BhadraEngine;
use SwissEph\FFI\SwissEphFFI;
use Carbon\CarbonImmutable;

// Initialize services
$sweph = new SwissEphFFI();
$ruleEngine = new FestivalRuleEngine();
$orchestrator = new FestivalFamilyOrchestrator();
$panchang = new PanchangService(
    $sweph,
    new SunService($sweph),
    new AstronomyService($sweph),
    new PanchangaEngine(),
    new MuhurtaService(),
    new FestivalService($ruleEngine, $orchestrator),
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
    'ayanamsa' => env('PANCHANG_AYANAMSA', 'LAHIRI'),
    'defaults' => [
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
  - `Daylight_Fivefold_Division`
  - `Nishita_Muhurta`
  - `Vijaya_Muhurta`
  - `Godhuli_Muhurta`
  - `Sandhya`
  - `Gowri_Panchangam`
  - `Kala_Vela`
  - `Karmakala_Windows`

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

### Raw JSON Exporter

```bash
php panchang_raw_output.php > output.json
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

### Muhurta APIs

`PanchangService` also exposes higher-level electional helpers:

- `getElectionalSnapshot()`
- `getDailyMuhurtaEvaluation()`

### Festival Catalog Notes

- The canonical source of truth is `FestivalService::FESTIVALS`.
- The current verified catalog contains `163` festival definitions after merging true alias/variant duplicates.
- Festivals that legitimately share the same tithi or civil date remain separate entries when they represent different observances.

### Standalone Configuration

```php
use JayeshMepani\PanchangCore\Panchanga\PanchangService;

// Configure before instantiation
PanchangService::configure(
    ephePath: '/path/to/ephe',
    ayanamsaMode: 'LAHIRI'
);
```

## Supported Festival Definitions (163)

### Solar-Based (Saṅkrānti)
- Makara Saṅkrānti (Jan 14)
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
| **Karmakala** | Daylight fivefold division, Sandhya, Nishita, Vijaya, Godhuli, Gowri Panchangam, Kala Vela | ✅ Complete |
| **Festivals** | 163 festival definitions | ✅ Complete |
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
- **Exporter Script**: [`panchang_raw_output.php`](panchang_raw_output.php)

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
