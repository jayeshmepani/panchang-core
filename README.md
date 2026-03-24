# Panchang Core - Vedic Astrology Engine

**Authentic Vedic Panchanga calculation engine with Swiss Ephemeris precision**

[![PHP Version Require](https://img.shields.io/packagist/php-v/jayeshmepani/panchang-core?style=flat-square)](https://packagist.org/packages/jayeshmepani/panchang-core)
[![License](https://img.shields.io/packagist/l/jayeshmepani/panchang-core.svg?style=flat-square)](https://packagist.org/packages/jayeshmepani/panchang-core)

---

## Requirements

- **PHP 8.3+** (uses match expressions, readonly classes, typed constants, enums)
- **FFI Extension** (`extension=ffi` and `ffi.enable=1` in php.ini)
- **Swiss Ephemeris FFI** (`jayeshmepani/swiss-ephemeris-ffi`)

## Installation

```bash
composer require jayeshmepani/panchang-core
```

**Dependencies:**
- `jayeshmepani/swiss-ephemeris-ffi` ^1.0 - Swiss Ephemeris PHP FFI wrapper
- `nesbot/carbon` ^3.0 - Date/time library

## Quick Start

### Laravel Usage

```php
use JayeshMepani\PanchangCore\Facades\Panchang;
use Carbon\CarbonImmutable;

$details = Panchang::getDayDetails(
    date: CarbonImmutable::parse('2026-03-24'),
    lat: 23.2472446,
    lon: 69.668339,
    tz: 'Asia/Kolkata'
);

echo $details['Tithi']['name'];      // e.g., "Shukla Shashthi"
echo $details['Nakshatra']['name'];  // e.g., "Rohini"
echo $details['Yoga']['name'];       // e.g., "Siddha"
```

### Standalone Usage

```php
use JayeshMepani\PanchangCore\Panchanga\PanchangService;
use JayeshMepani\PanchangCore\Astronomy\AstronomyService;
use JayeshMepani\PanchangCore\Astronomy\SunService;
use JayeshMepani\PanchangCore\Panchanga\PanchangaEngine;
use JayeshMepani\PanchangCore\Panchanga\MuhurtaService;
use SwissEph\FFI\SwissEphFFI;

$sweph = new SwissEphFFI();
$panchangService = new PanchangService(
    $sweph,
    new SunService($sweph),
    new AstronomyService($sweph),
    new PanchangaEngine(),
    new MuhurtaService()
);

$details = $panchangService->getDayDetails(
    date: CarbonImmutable::parse('2026-03-24'),
    lat: 23.2472446,
    lon: 69.668339,
    tz: 'Asia/Kolkata'
);
```

## Features

### Core Calculations

- ✅ **Tithi** - Lunar day (1-30) with precise fraction
- ✅ **Vara** - Weekday (Sunday-Saturday)
- ✅ **Nakṣatra** - Lunar mansion (27 nakṣatras) with pāda
- ✅ **Yoga** - Auspicious/inauspicious period (27 yogas)
- ✅ **Karaṇa** - Half lunar day (11 karaṇas)
- ✅ **Muhūrta** - Auspicious time periods

### Advanced Features

- ✅ **50+ Hindu Festivals** - With tradition/region profiles
- ✅ **Festival Families** - Multi-day celebrations (Holi, Diwali, Navaratri)
- ✅ **Eclipse Calculations** - Solar and lunar eclipse predictions
- ✅ **Bhadra Detection** - Inauspicious period avoidance
- ✅ **Karmakala Resolution** - Sacred time period calculations

### Classical Accuracy

- ✅ **Sūrya Siddhānta** - Astronomical basis (4th-5th century CE)
- ✅ **Muhūrta Chintāmaṇi** - Festival timing rules (15th century)
- ✅ **Nirṇaya Sindhu** - Dharmashastra rules (16th century)
- ✅ **Dharma Sindhu** - Regional variations (1790-1791 CE)

## Package Structure

```
panchang-core/
├── src/
│   ├── Core/                    # Core constants and types
│   │   ├── Constants/
│   │   │   ├── AstrologyConstants.php
│   │   │   └── ClassicalTimeConstants.php  (PHP 8.3 typed)
│   │   └── Types/
│   │       ├── Paksha.php                  (Enum)
│   │       ├── KarmaKalaType.php           (Enum)
│   │       ├── Tithi.php                   (Enum)
│   │       └── AyanamsaMode.php            (Enum)
│   ├── Astronomy/               # Swiss Ephemeris wrapper
│   │   ├── AstronomyService.php
│   │   ├── SunService.php
│   │   └── EclipseService.php
│   ├── Panchanga/               # Core calculations
│   │   ├── PanchangaEngine.php
│   │   ├── PanchangService.php
│   │   ├── MuhurtaService.php
│   │   └── KalaNirnayaEngine.php
│   ├── Festivals/               # Festival engine
│   │   ├── FestivalService.php
│   │   ├── FestivalRuleEngine.php
│   │   ├── FestivalFamilyOrchestrator.php
│   │   └── Utils/
│   │       └── BhadraEngine.php
│   └── PanchangServiceProvider.php
├── config/
│   └── panchang.php
└── tests/
```

## PHP 8.3 Features Used

### Typed Constants

```php
final readonly class ClassicalTimeConstants
{
    public const float GHATIKA_IN_MINUTES = 24.0;
    public const float ARUNODAYA_PER_DAY = 96.0 / 1440.0;
    public const int NAKSHATRAS_TOTAL = 27;
    public const array BHADRA_TITHIS = [6, 8, 10, 12, 14];
}
```

### Enum Types

```php
enum Paksha: string
{
    case SHUKLA = 'Shukla';
    case KRISHNA = 'Krishna';
    
    public function getTithiRange(): array { ... }
}

enum KarmaKalaType: string
{
    case SUNRISE = 'sunrise';
    case PRADOSHA = 'pradosha';
    case NISHITA = 'nishita';
    
    public function getSanskritName(): string { ... }
}
```

### Match Expressions

```php
$mode = match (strtoupper($ayanamsa)) {
    'LAHIRI' => SwissEphFFI::SE_SIDM_LAHIRI,
    'RAMAN' => SwissEphFFI::SE_SIDM_RAMAN,
    'KRISHNAMURTI' => SwissEphFFI::SE_SIDM_KRISHNAMURTI,
    default => SwissEphFFI::SE_SIDM_LAHIRI,
};
```

### Constructor Property Promotion

```php
class PanchangService
{
    public function __construct(
        private SwissEphFFI $sweph,
        private SunService $sunService,
        private AstronomyService $astronomy,
        private PanchangaEngine $panchanga,
        private MuhurtaService $muhurta,
    ) {}
}
```

## Precision Guarantee

- **IEEE 754 Double Precision** - 53-bit significand
- **No Intermediate Rounding** - All calculations lossless
- **Binary Search Convergence** - 80 iterations (1e-24 JD precision)
- **Exact Fractions** - 1/60, 1/30 (not decimal approximations)
- **Zero Tolerance** - JD_EPSILON = 1.0e-12

## Configuration

### Laravel

Publish config file:
```bash
php artisan vendor:publish --provider="JayeshMepani\PanchangCore\PanchangServiceProvider"
```

### Config Options

```php
// config/panchang.php
return [
    'ephe_path' => env('PANCHANG_EPHE_PATH'),
    'ayanamsa' => env('PANCHANG_AYANAMSA', 'LAHIRI'),
    'defaults' => [
        'measurement_system' => 'indian_metric',
        'date_time_format' => 'indian_12h',
        'time_notation' => '12h',
    ],
    'festivals' => [
        'default_tradition' => 'Smarta',
        'default_region' => 'North',
    ],
];
```

## Festival Engine

### Supported Festivals (50+)

**Solar-Based:**
- Makara Saṅkrānti (Jan 14)
- Viṣṇu Pūjā (Sep 17)

**Tithi-Based:**
- Vasanta Pañcamī - Māgha Śukla Pañcamī
- Mahā Śivarātri - Māgha Kṛṣṇa Chaturdaśī
- Holikā Dahan - Phālguna Śukla Pūrṇimā
- Rāma Navamī - Chaitra Śukla Navamī
- Kṛṣṇa Janmāṣṭamī - Bhādrapada Kṛṣṇa Aṣṭamī
- Gaṇeśa Chaturthī - Bhādrapada Śukla Chaturthī
- Dīpāvalī - Kārttika Kṛṣṇa Amāvāsyā

**Recurring:**
- Ekādaśī (twice monthly)
- Pradoṣa (twice monthly)

### Festival Families

Multi-day celebrations with proper orchestration:

**Holi Family:**
- Holikā Dahan (night, Pūrṇimā active)
- Holī/Dhuleti (next day)

**Dīwālī Family:**
- Dhanteras (Trayodaśī, Pradoṣa)
- Naraka Chaturdaśī (Chaturdaśī, sunrise)
- Lakṣmī Pūjā (Amāvāsyā, Pradoṣa)
- Govardhan Pūjā (next day)
- Bhai Dūj (Dvitīyā, Aparāhṇa)

**Janmāṣṭamī (Tradition-Specific):**
- Smarta: Aṣṭamī at sunrise
- Vaishnava/ISKCON: Aṣṭamī + Niśīta + Rohiṇī

### Exception Logic

**Example: Holikā Dahan 2026**

```
Scenario:
Pūrṇimā: March 2nd 17:56 → March 3rd 17:07

Night of March 2nd: Pūrṇimā ACTIVE ✓
Night of March 3rd: Pratipadā ACTIVE ✗

Classical Rule (Dharma Sindhu 2.3.15):
"Holikā must be performed when Pūrṇimā is present during Pradoṣa.
If Pūrṇimā spans two nights, observe on night when Pūrṇimā is active."

Result:
🔥 Holikā Dahan: Monday, March 2, 2026
🎨 Dhuleti: Tuesday, March 3, 2026
```

## Tradition & Region Profiles

### Supported Traditions

- **Smarta** - North Indian Brahmin tradition
- **Vaishnava** - Gaudiya Vaishnava/ISKCON standards

### Supported Regions

- **North** - Pūrṇimānta calendar
- **South** - Amānta calendar
- **Bengal** - Bengali traditions
- **Maharashtra** - Marathi traditions
- **Tamil** - Tamil traditions

## Testing

### Run Tests

```bash
composer test
```

### Code Quality

```bash
composer phpstan
composer format
```

## License

MIT License. See [LICENSE](LICENSE) for details.

## Credits

- **Jayesh Patel** - Package author
- **Swiss Ephemeris** - Astronomical calculations (Astrodienst AG)
- **Classical Texts** - Sūrya Siddhānta, Muhūrta Chintāmaṇi, Nirṇaya Sindhu

## Support

- **Issues**: [GitHub Issues](https://github.com/jayeshmepani/panchang-core/issues)
- **Documentation**: [Wiki](https://github.com/jayeshmepani/panchang-core/wiki)
- **Email**: jayeshmepani777@gmail.com

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history and breaking changes.
