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
- Festival engine: 324 unique festival identities and 98 unique vrat identities with tradition and regional handling
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
- `Amrita_Kaal` is calculated independently from nakshatra-specific Amrita ghati offsets, not from Varjyam.
- `Lagna_Full_Day` includes partial intervals that overlap the sunrise-to-next-sunrise Panchang day.
- `Chandra_Vaasa` uses Moon-rashi direction as the primary field and preserves the older nakshatra-pada Vaasa under `nakshatra_pada_vaasa`.
- Nakshatra-derived current windows such as Anandadi Yoga, Amritadi Yoga, and nakshatra-pada Chandra Vaasa are selected from the calculation time, not blindly from the first sunrise window.
- Eclipse output separates global classification from local visibility classification with `global_eclipse_type` and `local_eclipse_type`.
- `Day_Types.apparent_solar_noon` is the astronomical solar transit.
- `Abhijit_Muhurta.daylight_midpoint` is the sunrise-to-sunset midpoint used for Abhijit calculation.
- Proportional periods such as Hora, Choghadiya, daytime/nighttime Muhurtas, Prahara, the fivefold daytime divisions, Vijaya, Nishitha, Godhuli, Pratah Sandhya, and Sayahna Sandhya use actual local dinamana or ratrimana where their rule depends on day or night length.
- Arunodaya, Pradosha, and Madhyahna Sandhya use fixed ghati offsets from actual local sunrise, sunset, or solar noon; the ghati itself remains 24 elapsed minutes.

## CLI Exporters

```bash
php scripts/panchang_today.php
php scripts/panchang_month_output.php 2026 5 > month_2026_05.json
php scripts/panchang_festivals.php 2026
php scripts/panchang_eclipses.php 2026 2032
```

Notes:

- `panchang_today.php` writes `today_panchang.json` and prints status text.
- `panchang_month_output.php` prints JSON to stdout. Without arguments, it generates the current month.
- `panchang_festivals.php` writes `festivals_YYYY.json`.
- `panchang_eclipses.php` writes `eclipses_YYYY_YYYY.json`.
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
