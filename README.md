# Panchang Core

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jayeshmepani/panchang-core.svg?style=flat-square)](https://packagist.org/packages/jayeshmepani/panchang-core)
[![Total Downloads](https://img.shields.io/packagist/dt/jayeshmepani/panchang-core.svg?style=flat-square)](https://packagist.org/packages/jayeshmepani/panchang-core)
[![PHP Version Require](https://img.shields.io/packagist/php-v/jayeshmepani/panchang-core?style=flat-square)](https://packagist.org/packages/jayeshmepani/panchang-core)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE)

High-precision Hindu Panchang calculation engine for PHP 8.3+, powered by the JPL Moshier Ephemeris FFI wrapper.

It calculates Panchanga limbs, festivals, Muhurta windows, Karmakala timings, Chogadiya, Hora, Lagna tables, direction/Vaasa checks, eclipse visibility, and localized JSON outputs.

## Highlights

- Panchanga: Tithi, Vara, Nakshatra, Yoga, Karana
- Muhurta and Karmakala: Abhijit, Brahma Muhurta, Dur Muhurta, Nishita, Vijaya, Godhuli, Pradosha, Varjyam, Amrita Kaal
- Daily tables: Chogadiya, Hora, Prahara, 30 Muhurtas, Lagna intervals
- Muhurta devata sequence: Rudra-Ahi-Mitra day/night model aligned with Nārada Saṃhitā 9.1-5 and Kāśyapa/Vṛddha Vasiṣṭha attribution
- Festival engine: 336 unique festival identities and 126 unique vrat identities with tradition and regional handling
- Vaasa and direction checks: Disha Shool, Rahu Vaasa, Chandra Vaasa, Shiva Vaasa, Agni Vaasa, Yogini Vaasa
- Panchak rule output: Dhanishta pada 3 through Revati with entry-weekday subtype labels for Roga, Raja, Agni, Chora, Mrityu, and Shubha Panchaka
- Locales: English, Hindi, Gujarati
- Calendar types: Amanta and Purnimanta
- Works standalone or inside Laravel

## Install

```bash
composer require jayeshmepani/panchang-core
```

Requirements:

- PHP 8.3+
- PHP FFI extension enabled
- `jayeshmepani/jpl-moshier-ephemeris-php`
- `nesbot/carbon`

Enable FFI in `php.ini`:

```ini
extension=ffi
ffi.enable=1
```

For CLI verification:

```bash
php -r "echo extension_loaded('ffi') ? 'FFI loaded\n' : 'FFI not loaded\n';"
```

## Quick Usage

```php
<?php

require 'vendor/autoload.php';

use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Traits\CliBootstrap;

CliBootstrap::init(__DIR__);

$panchang = CliBootstrap::makePanchangService();

$details = $panchang->getDayDetails(
    date: CarbonImmutable::parse('2026-05-29'),
    lat: 23.2472446,
    lon: 69.668339,
    tz: 'Asia/Kolkata'
);

echo $details['Current_Tithi_At_Input_Now']['name'] . PHP_EOL;
echo $details['Tithi_At_Sunrise']['name'] . PHP_EOL;
echo $details['Current_Karana_At_Input_Now']['name'] . PHP_EOL;
echo $details['Nakshatra']['name'] . PHP_EOL;
```

Laravel facade usage:

```php
use Carbon\CarbonImmutable;
use JayeshMepani\PanchangCore\Facades\Panchang;

$details = Panchang::getDayDetails(
    date: CarbonImmutable::parse('2026-05-29'),
    lat: 23.2472446,
    lon: 69.668339,
    tz: 'Asia/Kolkata'
);

$festivals = $details['Festivals'];
```

## Important Output Semantics

- `Tithi` and `Karana` are sunrise-based compatibility fields.
- Use `Current_Tithi_At_Input_Now`, `Current_Nakshatra_At_Input_Now`, `Current_Yoga_At_Input_Now`, and `Current_Karana_At_Input_Now` for runtime/current values.
- Use `Tithi_At_Sunrise`, `Nakshatra_At_Sunrise`, and `Karana_At_Sunrise` when sunrise semantics are required explicitly.
- `Brahma_Muhurta` uses the dynamic night-muhurta convention by default: previous sunset to sunrise divided into 15 night Muhurtas.
- The fixed 48-minute Brahma Muhurta convention is preserved under `Brahma_Muhurta.fixed_48_minute_convention`.

## Bārhaspatya Saṃvatsara (Mean Jupiter Year) Models

`jayeshmepani/panchang-core` supports four distinct mathematical models for the traditional 60-name **Bārhaspatya Saṃvatsara** (Brihaspati Samvatsara) cycle. The package does **not** lock users to a single model: it uses a **smart default with opt-in Strategy selection**.

| Model Key | Status | Family | Calculation basis | Recommended use |
| :--- | :--- | :--- | :--- | :--- |
| `classical_ss` **(default)** | `canonical` | `traditional_barhaspatya` | Sewell–Dīkṣit / Sūrya-Siddhānta with bīja (Article 59) | Standard panchanga, academic baseline, zero ephemeris overhead |
| `makaranda` | `experimental` | `traditional_mean_jupiter` | Makaranda 1478 bīja mean-Jupiter (research projection) | Research; often closer to published Drik *intraday* times — **not** Drik’s formula |
| `grahalaghava` | `experimental` | `traditional_mean_jupiter` | Grahalāghava (Gaṇeśa Daivajña, 1520) mean-Jupiter research projection | Regional Western/Central historical comparison |
| `modern_ephemeris` | `astronomical_comparator` | `modern_ephemeris` | Prograde geocentric sidereal Jupiter rāśi ingress (JPL when configured) | Astrology / gochara / physical transit comparison |

### Why `classical_ss` is the package default

1. **Zero external dependencies** for this cycle — pure PHP mean-motion math (no JPL kernel required for the name).
2. **Source-defined Bārhaspatya rule** aligned with Sewell & Dīkṣit, *The Indian Calendar* (1896), Article 59.
3. **Stable calendar-date alignment** with published media series on the *civil date* of transitions for most of the modern test span — without conflating traditional reckoning with physical Jupiter ingress.

**Do not** change the package default to Makaranda, Graha-lāghava, or JPL merely because one series is closer to Drik Panchang over a subset of years. Drik remains an external validation reference, not the source of package constants. Projection models keep the **classical 60-name phase** and only retime boundaries.

### Decision matrix

| Application | Recommended model |
| :--- | :--- |
| Standard panchanga / general apps | `classical_ss` (default) |
| Closer published-media *intraday* research | `makaranda` (opt-in, experimental) |
| Western/Central historical mean-Jupiter research | `grahalaghava` (opt-in, experimental) |
| Birth charts / physical Guru gochara | `modern_ephemeris` (opt-in; needs AstronomyService) |

### Configuration (Laravel)

```php
// config/panchang.php
return [
    'defaults' => [
        // Keep classical_ss unless you knowingly opt into another strategy.
        'brihaspati_samvatsara_model' => env(
            'PANCHANG_BRIHASPATI_SAMVATSARA_MODEL',
            'classical_ss'
        ),
    ],
];
```

### Calendar period field keys

| Field | Meaning |
| :--- | :--- |
| `samvatsara_brihaspati` | Configured / package default strategy |
| `samvatsara_brihaspati_classical` | Explicit `classical_ss` |
| `samvatsara_brihaspati_makaranda` | Explicit Makaranda |
| `samvatsara_brihaspati_grahalaghava` | Explicit Grahalāghava |
| `samvatsara_brihaspati_modern` | Explicit modern ephemeris |

Windows carry `brihaspati_model`, `brihaspati_model_status`, `brihaspati_model_family`, and `brihaspati_model_variant` so consumers can see authority, not only the timing curve.

### Example usage

```php
use JayeshMepani\PanchangCore\Astronomy\BrihaspatiSamvatsaraService;

$service = new BrihaspatiSamvatsaraService($astronomyService); // astronomy only required for modern_ephemeris

// 1. Package default (classical_ss unless config overrides)
$default = $service->getBrihaspatiSamvatsaraInfo($date);

// 2. Explicit canonical classical
$classical = $service->getBrihaspatiSamvatsaraInfo(
    $date,
    BrihaspatiSamvatsaraService::MODEL_CLASSICAL_SS
);

// 3. Experimental Makaranda research model
$makaranda = $service->getBrihaspatiSamvatsaraInfo(
    $date,
    BrihaspatiSamvatsaraService::MODEL_MAKARANDA
);

// 4. Physical ephemeris comparator (JPL geocentric sidereal ingress)
$modern = $service->getBrihaspatiSamvatsaraInfo(
    $date,
    BrihaspatiSamvatsaraService::MODEL_MODERN_EPHEMERIS
);

// Catalogue: status, family, recommended_use, requires_astronomy_service
$catalogue = BrihaspatiSamvatsaraService::supportedModels();
```
- `Amrita_Kaal` is calculated independently from nakshatra-specific Amrita ghati offsets, not from Varjyam.
- `Lagna_Full_Day` includes partial intervals that overlap the sunrise-to-next-sunrise Panchang day.
- `Chandra_Vaasa` uses Moon-rashi direction as the primary field and preserves the older nakshatra-pada Vaasa under `nakshatra_pada_vaasa`.
- Nakshatra-derived current windows such as Anandadi Yoga, Amritadi Yoga, and nakshatra-pada Chandra Vaasa are selected from the calculation time, not blindly from the first sunrise window.
- Eclipse output separates global classification from local visibility classification with `global_eclipse_type` and `local_eclipse_type`.
- `Day_Types.apparent_solar_noon` is the astronomical solar transit.
- `Abhijit_Muhurta.daylight_midpoint` is the sunrise-to-sunset midpoint used for Abhijit calculation.
- Proportional periods such as Hora, Choghadiya, daytime/nighttime Muhurtas, Prahara, the fivefold daytime divisions, Vijaya, Nishitha, Godhuli, Pratah Sandhya, and Sayahna Sandhya use actual local dinamana or ratrimana where their rule depends on day or night length.
- Arunodaya, Pradosha, and Madhyahna Sandhya use fixed ghati offsets from actual local sunrise, sunset, or solar noon; the ghati itself remains 24 elapsed minutes.
- Generated `today`, `month`, and `raw` JSON include `calendar_period_windows` with sidereal and Sayana ayana/ritu windows alongside samvat, samvatsara, and lunar-month windows.
- Daily detail payloads in `today` and `raw` output include dual ayana/ṛtu systems — **Nirayana (sidereal / constellation-based)** as `Ayana`/`Ritu` and explicit `Nirayana_*`, plus **Sayana (tropical / seasonal)** as `Sayana_Ayana`/`Sayana_Ritu` — with locale-stable `*_Key` fields and system labels, plus `Mahadiksha_Guidance` where the selected output profile includes day details.

## CLI Exporters

```bash
php scripts/panchang_today.php
php scripts/panchang_month_output.php 2026 5
php scripts/panchang_festivals.php 2026
php scripts/panchang_eclipses.php 2026 2032
php scripts/panchang_raw_output.php 2026 2026 2032
```

Notes:

- Scripts write into `scripts/output/{calendar_type}/{locale}/`.
- `panchang_today.php` writes `today.json` and prints status text.
- `panchang_month_output.php` writes `month_YYYY_MM.json`. Without arguments, it generates the current month.
- `panchang_festivals.php` writes `festivals_YYYY.json`, `festivals_only_YYYY.json`, or `vrats_YYYY.json`.
- `panchang_eclipses.php` writes `eclipses_YYYY_YYYY.json`.
- `panchang_raw_output.php` writes `raw_output_YYYY_YYYY.json`.
- `panchang_month_output.php` and `panchang_raw_output.php` still emit JSON to stdout when piped or redirected.
- Use `PANCHANG_LOCALE=en|hi|gu` and `PANCHANG_CALENDAR_TYPE=amanta|purnimanta` for variants.

## Documentation

- Full HTML documentation: [docs/index.html](docs/index.html)
- Coverage matrix: [PACKAGE_COVERAGE.md](PACKAGE_COVERAGE.md)
- Traditional source attribution: [docs/TRADITIONAL_TEXT_SOURCES.md](docs/TRADITIONAL_TEXT_SOURCES.md)
- Muhurta text source classification: [docs/MUHURTA_TEXT_SOURCES.md](docs/MUHURTA_TEXT_SOURCES.md)
- Festival and vrat identity catalog: [docs/FESTIVAL_VRAT_IDENTITIES.md](docs/FESTIVAL_VRAT_IDENTITIES.md)

## Development

```bash
composer install
composer test
composer phpstan
composer lint:check
```

## License

MIT. See [LICENSE](LICENSE).

## Credits

Built by [Jayesh Mepani](https://github.com/jayeshmepani).

## Ṣaṇṇavati Śrāddha (96-fold canonical system)

The daily Panchanga payload now includes `Shannavati_Shraddha`, resolved through a dedicated source-aware Śrāddha layer. The default profile is `dharma_sindhu` and can be changed with `PANCHANG_SHANNAVATI_PROFILE`.

The canonical nominal taxonomy is 96 memberships: 12 Amāvāsyā, 4 Yugādi, 14 Manvādi, 12 Saṅkrānti, 12 Vaidhṛti, 12 Vyatīpāta, 15 Mahālaya, 5 Aṣṭakā, 5 Anvaṣṭakā, and 5 Pūrvēdhyu. Runtime occurrences are intentionally **not forced to exactly 96 physical dates**; adhika-māsa, tithi-vṛddhi/kṣaya, overlaps, and qualifying nitya-yoga manifestations are preserved.

The resolver reuses the existing tithi, Saṅkrānti, and Yoga interval engines, adds Kutapa/Rauhiṇa/Śrāddha-Aparāhṇa daylight windows, supports Amānta/Pūrṇimānta month normalization, and permits multiple Ṣaṇṇavati memberships on one civil date.
