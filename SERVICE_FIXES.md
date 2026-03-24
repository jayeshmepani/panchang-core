# Service File Fixes Required

## Issue: Laravel `config()` Function

All service files use Laravel's `config()` helper which doesn't work in standalone mode.

### Current Code (Laravel-dependent)
```php
$ephePath = config('panchang.ephe_path');
$ayanamsa = config('panchang.ayanamsa', 'LAHIRI');
```

### Fixed Code (Standalone-compatible)
```php
// Option 1: Constructor injection
public function __construct(
    private SwissEphFFI $sweph,
    private string $ephePath = '',
    private string $ayanamsa = 'LAHIRI',
) {}

// Option 2: Static configuration
private static string $ephePath = '';
private static string $ayanamsa = 'LAHIRI';

public static function configure(string $ephePath = '', string $ayanamsa = 'LAHIRI'): void
{
    self::$ephePath = $ephePath;
    self::$ayanamsa = $ayanamsa;
}
```

---

## Files to Fix

### Astronomy Services (3 files)

**All have namespace correct, need config() fix:**
- ✅ `src/Astronomy/AstronomyService.php` - Namespace OK
- ✅ `src/Astronomy/SunService.php` - Namespace OK
- ✅ `src/Astronomy/EclipseService.php` - Namespace OK

### Panchanga Services (4 files)

**Need namespace + config() fix:**
- 🔴 `src/Panchanga/PanchangaEngine.php`
- 🔴 `src/Panchanga/PanchangService.php`
- 🔴 `src/Panchanga/MuhurtaService.php`
- 🔴 `src/Panchanga/KalaNirnayaEngine.php`

### Festival Services (4 files)

**Need namespace + config() fix:**
- 🔴 `src/Festivals/FestivalService.php`
- 🔴 `src/Festivals/FestivalRuleEngine.php`
- 🔴 `src/Festivals/FestivalFamilyOrchestrator.php`
- 🔴 `src/Festivals/Utils/BhadraEngine.php`

---

## Fix Strategy

### Step 1: Add Configuration Method

Add to each service that uses `config()`:

```php
/**
 * Configure service (optional, for standalone usage)
 * 
 * @param string $ephePath Ephemeris path (empty for default)
 * @param string $ayanamsa Ayanamsa mode ('LAHIRI', 'RAMAN', 'KRISHNAMURTI')
 */
public static function configure(
    string $ephePath = '',
    string $ayanamsa = 'LAHIRI'
): void {
    self::$ephePath = $ephePath;
    self::$ayanamsa = $ayanamsa;
}
```

### Step 2: Replace config() Calls

```php
// Replace this:
$ephePath = config('panchang.ephe_path');

// With this:
$ephePath = self::$ephePath ?: getenv('PANCHANG_EPHE_PATH') ?: '';
```

### Step 3: Add Fallback Logic

```php
// Replace this:
$ayanamsa = config('panchang.ayanamsa', 'LAHIRI');

// With this:
$ayanamsa = self::$ayanamsa 
    ?: getenv('PANCHANG_AYANAMSA') 
    ?: 'LAHIRI';
```

---

## Implementation Order

1. **Fix AstronomyService** (used by all other services)
2. **Fix PanchangService** (main entry point)
3. **Fix FestivalRuleEngine** (complex logic)
4. **Fix remaining services**

---

## Testing After Fix

```bash
# Test standalone usage
php -r "
require 'vendor/autoload.php';
use JayeshMepani\PanchangCore\Panchanga\PanchangService;
PanchangService::configure('', 'LAHIRI');
// ... test functionality
"
```
