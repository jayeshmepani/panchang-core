# Traditional Source Map - Panchang Core

## Purpose

This document is a source-integrity map for the package.

It is **not** a manuscript-critical proof document, and it does **not** claim that every package rule is directly established from a single Sanskrit passage. The codebase mixes:

- **Direct Panchang/Jyotisha conventions** that are widely standard
- **Package rule mappings** attributed to traditional muhurta literature
- **Regional or published almanac conventions**
- **Modern secondary references**
- **A few helpers that are explicitly heuristic or legacy**

To keep this document stable, it uses **file/function-level references** instead of fragile line-number tables.

This document covers **traditional source attributions**.  
Some package components are architectural helpers and are intentionally **not** tied to traditional texts:
- `OutputGeneratorService` (output assembly wrapper around package services)
- `CliBootstrap` (standalone bootstrap helper for env/config/container wiring)

Current package state (v4.x baseline):
- Festival catalog: `FestivalService::FESTIVALS` currently contains **230** top-level entries.
- Localization: user-facing outputs support `en`, `hi`, `gu`.
- Calendar conventions: both `amanta` and `purnimanta` are supported.

---

## Confidence Tiers

### Tier 1: Direct or Standard Panchang Conventions

These are standard astronomical or calendrical conventions that are broadly stable across traditional Panchang computation.

| File | Functions/Constants | Source Attribution |
|------|-------------------|-------------------|
| `ClassicalTimeConstants.php` | `GHATIKA_IN_MINUTES = 24`<br>`PALA_IN_SECONDS = 24`<br>`MUHURTA_IN_MINUTES = 48`<br>`DEGREES_PER_NAKSHATRA = 360/27`<br>`DEGREES_PER_TITHI = 12` | **Traditional convention:** Sūrya Siddhānta-style astronomy and long-standing almanac convention |
| `Tithi.php` | `fromLongitudes()`<br>`getFractionRemaining()` | **Traditional convention:** 30 lunar days, each 12° Moon-Sun separation (Sūrya Siddhānta 1.29) |
| `Nakshatra.php` | `fromLongitude()`<br>`getPada()` | **Traditional convention:** 27 nakṣatras, each 13°20' (Sūrya Siddhānta 8.1) |
| `Yoga.php` | `fromLongitudes()` | **Traditional convention:** 27 yogas, each 13°20' Sun-Moon sum (Sūrya Siddhānta 3.1-3) |
| `Karana.php` | `fromTithi()` | **Traditional convention:** 11 karanas, each 6° Moon-Sun separation (Muhūrta Chintāmaṇi Chapter 2) |

---

### Tier 2: Package Rule Mappings Attributed to Traditional Muhurta Literature

These parts of the package are **rulebooks encoded by the package**. They may be inspired by or attributed to texts such as Muhūrta Chintāmaṇi, Bṛhat Saṃhitā, Muhūrta Mārtaṇḍa, Māyamata, Vaikhānasa Āgama, Aśvalāyana Gṛhya Sūtra, and related traditions.

They should be read as **package mappings**, not as proof that every table entry has been independently verified against a primary-edition Sanskrit text.

| File | Functions/Constants | Source Attribution |
|------|-------------------|-------------------|
| `ElectionalRuleBook.php` | `UNIVERSAL_BAD_TITHIS`<br>`VARA_TITHI_YOGAS` | **Package attribution:** Muhurta Chintamani, Brihat Samhita, Mayamata, Vaikhanasa Agama, Ashvalayana Grihya Sutra |
| `ElectionalEvaluator.php` | `calculatePanchakaDosha()`<br>`calculateDagdhaTithi()`<br>`calculateDagdhaYoga()`<br>`calculateRiktaTithi()`<br>`calculateAbhijitCancellation()`<br>`generateRejectionReport()` | **Package attribution:** Muhurta Chintamani, Brihat Samhita, Gargiya Jyotisha |
| `PanchangService.php` | `getElectionalSnapshot()`<br>`getDailyMuhurtaEvaluation()` | **Package attribution:** Muhurta Chintamani, Muhurta Martanda |

---

### Tier 3: Festival-Resolution Logic with Traditional Basis

These components implement festival and observance resolution where the package draws on traditional kala-nirṇaya style reasoning and fast/observance practice.

| File | Functions | Source Attribution |
|------|-----------|-------------------|
| `FestivalService.php` | `resolveFestivalsForDate()`<br>`buildFestivalPayload()`<br>`FestivalService::FESTIVALS` catalog metadata | **Package convention:** date-wise festival resolution with localized display fields and machine-stable calculation-basis metadata |
| `PanchangService.php` | `getFestivalYearCalendar()` | **Package convention:** location-aware year aggregation, `day_after` festival orchestration, and adjacent duplicate consolidation |
| `KalaNirnayaEngine.php` | Festival `karmakala_type` handling:<br>- `madhyahna`<br>- `nishitha`<br>- `pradosha`<br>- `aparahna`<br>- `sunrise`<br>Vaishnava Ekadashi handling | **Traditional convention:** Nirṇaya Sindhu, Muhūrta Chintāmaṇi, Hari Bhakti Vilāsa |
| `BhadraEngine.php` | Bhadra/Vishti subdivision helpers | **Package attribution:** Muhūrta Chintāmaṇi, Nirṇaya Sindhu, Ernst Wilhelm's Classical Muhurta |
| `PanchangService.php` | Timed Bhadra windows<br>Timed Varjyam windows<br>Timed Amrita Kaal window<br>Timed Pradosha window<br>Daily Karmakala output assembly | **Published Panchang convention:** Swiss Ephemeris-based live calculation |

---

### Tier 4: Published Panchang and Regional-Practice Conventions

These features are useful and intentionally included, but they are best described as **published Panchang conventions** or **regional almanac systems**, not universally fixed classical formulas.

| File | Functions | Source Attribution |
|------|-----------|-------------------|
| `MuhurtaService.php` | `calculateHora()`<br>`calculateHoraTable()`<br>`calculateChogadiya()`<br>`calculateChogadiyaTable()`<br>`calculateBadTimes()`<br>`calculateDaylightFivefoldDivision()`<br>`calculateNishitaMuhurta()`<br>`calculateVijayaMuhurta()`<br>`calculateGodhuliMuhurta()`<br>`calculateSandhya()`<br>`calculateGowriPanchangam()`<br>`calculateKalaVela()`<br>`calculatePrahara()`<br>`calculateBrahmaMuhurta()`<br>`calculateDurMuhurta()`<br>`calculateVarjyam()`<br>`calculateAmritaKaal()`<br>`calculatePradoshaKaal()`<br>`calculateLagnaTable()` | **Published Panchang convention:** Varies by tradition and regional practice |
| `PanchangService.php` | Daily output blocks:<br>- `Hora`<br>- `Chogadiya`<br>- `Hora_Full_Day`<br>- `Chogadiya_Full_Day`<br>- `Muhurta_Full_Day`<br>- `Rahu_Kaal_Gulika_Yamaganda`<br>- `Abhijit_Muhurta`<br>- `Prahara_Full_Day`<br>- `Daylight_Fivefold_Division`<br>- `Brahma_Muhurta`<br>- `Dur_Muhurta_Full_Day`<br>- `Nishita_Muhurta`<br>- `Vijaya_Muhurta`<br>- `Godhuli_Muhurta`<br>- `Sandhya`<br>- `Gowri_Panchangam`<br>- `Kala_Vela`<br>- `Karmakala_Windows`<br>- `Varjyam`<br>- `Amrita_Kaal`<br>- `Pradosha_Kaal`<br>- `Lagna_Full_Day` | **Published Panchang convention:** Implementation follows published almanac patterns |

**Notes:**
- `Gowri_Panchangam` is implemented as a Gowri-style 8-part day/night table based on the package's chosen published-table convention (Tamil Gowri/Pambu Panchangam)
- `Kala_Vela` is based on a secondary-source rule pattern attributed to Saravali-style descriptions, not a source-critical Sanskrit edition
- `Godhuli_Muhurta` and `Sandhya` are tradition-sensitive and may vary by school
- `Brahma Muhurta` timing follows Ayurvedic sources (Aṣṭāṅga Hṛdaya, Charaka Saṃhitā, Manusmṛti)
- `Varjyam`, `Amrita_Kaal`, and `Pradosha_Kaal` in `PanchangService` are live-timed Panchang-day outputs and should be preferred over legacy scalar helpers when available
- `Lagna_Full_Day` is a calculated ascendant-sign timing table for the day; it is not a natal/person-specific reading
- Festival output metadata intentionally separates canonical rule values from localized companion labels. For example, `calculation_basis.month.value` remains the machine-stable Sanskrit month key for the configured calendar type, while `calculation_basis.month.name` is localized for the active locale

---

### Tier 5: Explicitly Legacy or Heuristic Helpers

These are present in the codebase but are **not** represented as fully verified primary-source logic.

| File | Functions | Notes |
|------|-----------|-------|
| `ElectionalEvaluator.php` | `calculateTransitMoorthy()` | **Legacy heuristic:** Unverified moorthy classifier |
| `ElectionalEvaluator.php` | `calculateBhadra()` legacy moon-sign heuristic | **Legacy heuristic:** Prefer live-timed alternative in PanchangService |
| `ElectionalEvaluator.php` | `calculateAmritaKaal()` legacy Choghadiya-based helper | **Legacy heuristic:** Prefer authoritative Amrita calculation |
| `ElectionalEvaluator.php` | `calculateVarjyam()` legacy Visha Ghati helper | **Legacy heuristic:** Prefer KP System-based calculation |

**Where the package has a more authoritative live-timed alternative, that alternative should be preferred over these helpers.**

---

## Non-Text Architectural Helpers (Out of Scope for Source Attribution)

These components are implementation utilities and should not be interpreted as traditional-rule sources:

| File | Functions | Notes |
|------|-----------|-------|
| `OutputGeneratorService.php` | `generateFestivals()`<br>`generateEclipses()`<br>`generateTodayPanchang()`<br>`generateAll()` | Output composition layer around `PanchangService` / `EclipseService` |
| `CliBootstrap.php` | `init()`<br>`makePanchangService()`<br>`makeEclipseService()`<br>`makeOutputGenerator()` | Standalone bootstrapping utility for scripts |

---

## Complete List of All Traditional Texts Referenced

This list is descriptive. Inclusion here means the package references or attributes logic to that source or tradition somewhere in code or docs. It does **not** mean every usage has been independently verified against a critical Sanskrit edition.

### Primary Astronomical Texts
1. Sūrya Siddhānta

### Muhurta Texts
2. Muhūrta Chintāmaṇi
3. Muhūrta Mārtaṇḍa
4. Kālaprakāśikā
5. Nirṇaya Sindhu
6. Ernst Wilhelm's Classical Muhurta

### Dharmashastra Texts
7. Manusmṛti

### Ayurveda Texts
8. Aṣṭāṅga Hṛdaya
9. Charaka Saṃhitā

### Jyotisha Texts
10. Bṛhat Saṃhitā
11. Bṛhat Jātaka
12. Nārada Saṃhitā
13. Sarāvalī
14. Gargiya Jyotisha

### Puranic Texts
15. Śrīmad Bhāgavata Purāṇa

### Regional/Almanac Conventions
16. Tamil Gowri Panchangam
17. Pambu Panchangam

### Vāstu Texts
18. Māyamata

### Āgama Texts
19. Vaikhānasa Āgama

### Gṛhya Sūtra Texts
20. Aśvalāyana Gṛhya Sūtra

### Vaishnava Texts
21. Hari Bhakti Vilāsa

### Modern Systems
22. KP System (Krishnamurti Paddhati)

### Living Traditions
23. Sandhyāvandanam Tradition

---

## What This Document Intentionally Does Not Claim

- ❌ It does **not** claim that every package rule is 1:1 proven from a single primary text
- ❌ It does **not** claim that all regional or sectarian variants are covered
- ❌ It does **not** claim that modern almanac conventions are identical across traditions
- ❌ It does **not** use brittle line-number references that become false after normal code changes
- ❌ It does **not** claim independent verification against critical Sanskrit editions

---

## Recommended Interpretation for Maintainers

When adding or modifying source attributions in code, use these prefixes:

| Prefix | When to Use | Example |
|--------|-------------|---------|
| `Traditional convention:` | Stable Panchang arithmetic or long-standing calendrical units | `Traditional convention: Sūrya Siddhānta 1.29` |
| `Package attribution:` | Package encodes a rulebook or mapping from traditional literature | `Package attribution: Muhurta Chintamani Chapter 4` |
| `Published Panchang convention:` | Daily-window systems where practice varies by region | `Published Panchang convention: Tamil Gowri table` |
| `Legacy` or `heuristic` | Helper is not the preferred authoritative path | `Legacy heuristic: prefer live-timed alternative` |
