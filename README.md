# Panchang Core

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jayeshmepani/panchang-core.svg?style=flat-square)](https://packagist.org/packages/jayeshmepani/panchang-core)
[![Total Downloads](https://img.shields.io/packagist/dt/jayeshmepani/panchang-core.svg?style=flat-square)](https://packagist.org/packages/jayeshmepani/panchang-core)
[![PHP Version Require](https://img.shields.io/packagist/php-v/jayeshmepani/panchang-core?style=flat-square)](https://packagist.org/packages/jayeshmepani/panchang-core)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE)

High-precision Hindu Panchang calculation engine for PHP 8.3+, powered by the JPL Moshier Ephemeris FFI wrapper.

It calculates Panchanga limbs, festivals, Muhurta windows, Karmakala timings, Chogadiya, Hora, Lagna tables, direction/Vaasa checks, eclipse visibility, and localized JSON outputs.

## Install

```bash
composer require jayeshmepani/panchang-core
```

Requirements:

- PHP 8.3+
- PHP FFI extension enabled
- `jayeshmepani/jpl-moshier-ephemeris-php` 2.0.0 or later in the 2.x series
- `nesbot/carbon`

Enable FFI in `php.ini`:

```ini
extension=ffi
ffi.enable=1
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

## Documentation

Full documentation, including output semantics, configuration, Bārhaspatya Saṃvatsara models, Ṣaṇṇavati Śrāddha, CLI exporters, and release notes: [docs/index.html](docs/index.html)

- Coverage matrix: [PACKAGE_COVERAGE.md](PACKAGE_COVERAGE.md)
- Traditional source attribution: [docs/TRADITIONAL_TEXT_SOURCES.md](docs/TRADITIONAL_TEXT_SOURCES.md)
- Muhurta text source classification: [docs/MUHURTA_TEXT_SOURCES.md](docs/MUHURTA_TEXT_SOURCES.md)
- Festival and vrat identity catalog: [docs/FESTIVAL_VRAT_IDENTITIES.md](docs/FESTIVAL_VRAT_IDENTITIES.md)

## License

MIT. See [LICENSE](LICENSE).

The dependency [jpl-moshier-ephemeris-php](https://github.com/jayeshmepani/jpl-moshier-ephemeris-php) ships native runtime binaries under `MIT AND CeCILL-B` (CALCEPH); if you redistribute those binaries, keep that package's `LICENSE`, `LICENSES/` and `THIRD_PARTY_NOTICES.md`.

## Credits

Built by [Jayesh Mepani (Jayesh Patel)](https://github.com/jayeshmepani). Astronomy runs on [JPL Moshier Ephemeris (JME)](https://github.com/jayeshmepani/jpl-ephemeris); full third-party credits: [JME THIRD_PARTY_NOTICES.md](https://github.com/jayeshmepani/jpl-ephemeris/blob/main/THIRD_PARTY_NOTICES.md).
