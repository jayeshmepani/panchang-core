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

Currently, the package outputs **326 unique festival identities** and **90 unique vrat identities** for the generated yearly Panchang contract. These identity totals are distinct from dated occurrence counts; repeated observances such as monthly vrats are counted once per canonical identity.
- Localization: user-facing outputs support `en`, `hi`, `gu`.
- Calendar conventions: both `amanta` and `purnimanta` are supported.
- Muhurta devata enum source classification: [MUHURTA_TEXT_SOURCES.md](MUHURTA_TEXT_SOURCES.md).

---

## Current Engine Convention Matrix

This section records the actual calculation conventions currently used by the engine. It is intended to make the package defaults explicit for maintainers, client review, and future rule changes.

| Area | Current Setting | Where Implemented | Notes |
|------|-----------------|-------------------|-------|
| Astronomical engine | JME native ephemeris through FFI; default mode is `jpl` from `PANCHANG_JME_MODE`, with supported modes `auto`, `jpl`, `moshier`, and `vsop_elp_meeus` | `config/panchang.php`, `PanchangServiceProvider`, `CliBootstrap` | `jpl` is the package default when configured kernels are available. Standalone and Laravel bootstraps both configure JME before service use. |
| Ayanamsa | `JME_SIDEREAL_LAHIRI` / Lahiri (Chitra Paksha) | `AstronomyService::setAyanamsa()`, `PanchangServiceProvider`, `CliBootstrap` | The package does **not** use dynamic `True Citra` for Panchanga limb calculations. |
| Panchanga longitudes | Geocentric sidereal Sun/Moon longitudes using `JME_CALC_HIGH_PRECISION \| JME_CALC_SIDEREAL` | `AstronomyService`, `TransitEngine`, `PanchangAstronomyHelpersTrait` | No `JME_CALC_TOPOCENTRIC` flag is used for Tithi, Nakshatra, Yoga, Karana, Moon sign, or transition searches. |
| Moon for Panchanga limbs | Geocentric Moon | `AstronomyService`, `TransitEngine`, `PanchangAstronomyHelpersTrait` | This follows the general Panchanga convention. Topocentric parallax is not applied to Nakshatra/Tithi limb calculation. |
| Moonrise/moonset | Topocentric rise/set for the observer location and elevation | `SunService::getMoonriseMoonset()` | Output is a lunar visibility interval that starts with a moonrise inside the civil date. If there is no moonrise inside that civil day, both `moonrise` and `moonset` are returned as `null`, even if an independent civil-day moonset occurs from a previous-day moonrise. |
| Sunrise/sunset definition | Visible upper limb with standard atmospheric refraction | `SunService::getSunriseSunset()` | In JME rise/set, the package passes no disc-center, disc-bottom, no-refraction, or Hindu-rising flag for Panchanga sunrise/sunset. This means visible upper limb is used. |
| Atmospheric model for rise/set | Standard pressure `1013.25 hPa`, temperature `15 C` | `SunService::runRiseTransit()` | Refraction is enabled because `JME_RISE_NO_REFRACTION` is not passed. |
| Solar/lunar transits and twilight | Topocentric rise/transit at supplied latitude, longitude, and elevation | `SunService` | Uses the same standard pressure/temperature for rise/transit calculations. |
| Panchang day boundary | Sunrise to next sunrise | `PanchangService`, `PanchangCalendarApiTrait`, `KalaNirnayaEngine` | Top-level daily Tithi/Karana preserve sunrise semantics for backward compatibility; runtime values are separately exposed as current-at-input fields. |
| Festival default tradition | `Smarta` by default; supported traditions include `Smarta` and `Vaishnava` | `config/panchang.php`, `FestivalFamilyOrchestrator`, `KalaNirnayaEngine` | Vaishnava/ISKCON variants are resolved where festival metadata and rule handlers support them. |
| Lunar month representation | `amanta` default, with `purnimanta` supported | `config/panchang.php`, `PanchangaEngine`, calendar APIs | This is representation-level month naming; core Tithi/Nakshatra/Yoga/Karana arithmetic is unchanged. |
| Gujarati-style month conventions | Amanta/Gujarati Samvat presentation is supported; explicit kshaya-masa edge handling should be reviewed separately where required | `config/panchang.php`, `PanchangaEngine`, calendar APIs | Gujarati-style Amanta month naming is presentation-level; kshaya-masa handling is a separate calendar edge case from ordinary amanta/purnimanta display. |
| Vaishnava Ekadashi Dashami-vedha | 55 ghaṭikā threshold from previous sunrise | `KalaNirnayaEngine::determineEkadashi()` | Common 4-ghaṭikā arunodaya is documented separately but is not the active Nirnay vedha threshold. |
| Hari Vasara | First quarter of Dvadashi | `EkadashiParanaCalculator` | Parana starts after Hari Vasara unless restricted further by Nakshatra-pada rules. |
| Ekadashi parana restrictions | Gujarati/Nirnay month-paksha scope: Ashadha Shukla Anuradha P1, Bhadrapada Shukla Shravana P2-P3, Kartika Shukla Revati P4 | `EkadashiParanaCalculator::buildParanaPayload()` | The payload exposes restricted windows, allowed parana windows, short-Dwadashi classification, and symbolic-water emergency allowance. Calls without month/paksha context retain the old global Nirnay fallback. |
| Ekadashi parana source refinements | Break within Dwadashi unless Dwadashi expires before sunrise; Harivasara is the first quarter of Dwadashi; tight-overlap cases can expose symbolic-water parana metadata | `EkadashiParanaCalculator::buildParanaPayload()` | Source basis includes Drik Panchang Ekadashi/ISKCON parana pages, Swaminarayan Satsangi Jeevan parana restrictions, and Hari Bhakti Vilasa / Vaishnava practice. |
| Vaishnava Ekadashi case labels | Candidate payload metadata can distinguish scenario names such as Viddha, Kshaya, Unmillani, Trisparsha, and Mahadvadashi variants where implemented | `KalaNirnayaEngine`, `EkadashiParanaCalculator` | Source basis includes Shikshapatri 81, Vitthalnathji vrata-nirnaya compliance, Kamakoti Dharma Sindhu Vaishnava/Smarta distinctions, and Drik Panchang's published Mahadvadashi profiles. |
| Festival karmakala resolution | Window overlap, not single-point-only, for Pradosha/Nishitha/Madhyahna/Aparahna/Sangava/Arunodaya | `FestivalRuleEngine` | Pradosha is sunset to 6 ghati after sunset for festival decisions; resolution metadata exposes overlap seconds. |
| Rama Navami / Shri Ram Jayanti | Madhyahna-vyapini Chaitra Shukla Navami is the classical target for Rama-birth observance | `FestivalRuleEngine`, `FestivalService::FESTIVALS` | Source basis: Valmiki Ramayana Bala Kanda 1.18.8, Dharma Sindhu Madhyahna Navami instruction, and Drik Panchang Rama Navami published timing. Swaminarayan Jayanti is intentionally documented separately because that sectarian day resolution is not the same rule. |
| Dhanteras / Dhantrayodashi | Krishna Trayodashi overlapping Pradosha is the puja-date target | `FestivalRuleEngine`, `FestivalService::FESTIVALS` | Source basis: Dharma Sindhu-style Pradosha Trayodashi rule and Drik Panchang Dhantrayodashi puja timing. |
| Diwali / Lakshmi Puja | Amavasya overlapping Pradosha/night is the Lakshmi Puja target | `FestivalRuleEngine`, `FestivalService::FESTIVALS` | Source basis: Dharma Sindhu-style Amavasya night/Pradosha rule and Drik Panchang Diwali puja muhurta. |
| Holika Bhadra and eclipse handling | Reject Bhadra Mukha/Madhya overlap, prefer clear or Bhadra Puchha windows, apply the lunar-eclipse exception path, and require Purnima in Pradosha | `FestivalRuleEngine`, `BhadraCalculator`, `BhadraEngine` | Active when festival snapshots include Bhadra period or lunar-eclipse data; branch-level PHPUnit coverage locks the Gujarati decision paths. |
| Ganesh Chaturthi special Madhyahna preference | Prefer full Madhyahna Chaturthi coverage over partial overlap | `FestivalRuleEngine`, `FestivalService::FESTIVALS` | Encoded as `prefer_full_karmakala_coverage` plus Gujarati special-case metadata. |
| Deepotsav observance sequence | Date-wise rules for Vagh Baras, Dhanteras, Narak Chaturdashi Abhyanga Snan, Kali Chaudas, Diwali/Kali Puja, Govardhan/Annakut, and Bhai Beej | `FestivalService::FESTIVALS`, `FestivalRuleEngine` | Entries expose `deepotsav_sequence`; Naraka Chaturdashi Abhyanga Snan and Govardhan Puja are marked `location_sensitive` because published city almanacs can differ by one civil date. |
| Pausha Purnima Vrat | Pausha Shukla Purnima at sunrise for the labeled vrat date | `FestivalService::FESTIVALS`, `FestivalRuleEngine` | This follows the Drik-style labeled Purnima vrat day. The previous sunset/tithi-start convention is not used for this label. |
| Janmashtami truth table | Jayanti Yoga first, Saptami-viddha rejection to day2, Rohini tie-breaks, and Nishitha fallback | `FestivalRuleEngine`, `FestivalService::FESTIVALS` | Enabled by `janmashtami_truth_table`, with Rohini and Monday/Wednesday priority. |
| Mahashivaratri Nishitha coverage | Nishitha-only day selection, full-vs-full second-day preference, full-over-Ekadesha, Ekadesha, and partial fallback branches | `FestivalRuleEngine`, `FestivalService::FESTIVALS` | Enabled by `mahashivaratri_truth_table` and `ekadesha_coverage_allowed`; historical edge-date regression tests can still be added where exact source expectations are available. |
| Vijayadashami | Dedicated Vijaya-Kaal window with Shravana tie-breaks and kshaya fallback | `FestivalService::FESTIVALS`, `FestivalRuleEngine` | Encoded as `karmakala_type = vijaya_kaal`, `vijaya_kaal_primary = true`, `fallback_support = aparahna`, and `vijayadashami_truth_table = true`. |
| Govatsa Dwadashi | Pradosha Dwadashi day1/day2 table with second-day preference when both days qualify | `FestivalRuleEngine`, `FestivalService::FESTIVALS` | Enabled by `govatsa_truth_table` and `govatsa_equal_pradosha_preference = second_day`. |
| Mahavir Jayanti | Chaitra Shukla Trayodashi at exact sunrise | `FestivalService::FESTIVALS`, `FestivalRuleEngine` | The earlier Monday/Pushya rejection was removed after public-almanac verification for 2026. |
| Swaminarayan Jayanti (Hari-Nom) | Chaitra Shukla Navami by sunrise-vyapini day selection; birth remembrance remains night-centered | `FestivalService::FESTIVALS`, `FestivalRuleEngine` | Promoted from `Satsangi Jeevan` / Swaminarayan tradition evidence: the text anchors the avatara on Chaitra Sud 9 and describes the manifestation in the night, so this package uses sunrise-vyapini Navami for day resolution rather than Ram-Navami-style Madhyahna resolution. |
| Samavedi Shravani | Hasta Nakshatra during Aparahna, consolidated to one annual output row | `FestivalRuleEngine::resolveNakshatraFestival()`, `PanchangService::consolidateYearlySingleObservanceFestivals()` | Nakshatra-only resolver checks `Nakshatra_Windows`; yearly output keeps the best annual Samaveda Upakarma observance. |
| Samavedi Shravani source family | Bhadrapada/Hasta priority with regional procedure variants | `FestivalRuleEngine::resolveNakshatraFestival()` | Source basis includes Yajnavalkya Smriti 1.142 references as quoted in modern summaries, Samskaaram.com procedure notes, and Samskara-ratnamala Upakarma material. |
| Phuldolotsava | Swaminarayan/BAPS Phalguna Purnima sunrise tithi observance | `FestivalService::FESTIVALS`, `FestivalRuleEngine` | Marked `sect_specific`; contemporary BAPS/Swaminarayan public calendars often present Fuldol/Pushpadolotsav on Fagun Vad 1 after Holi, while Satsangi Jivan preserves a Phalguni-at-sunrise/Nar-Narayan rationale. Treat these as selectable profile conventions, not one universal rule. |
| Chandra Darshana | First visible waxing crescent after Amavasya, after local sunset and before moonset, usually in Shukla Pratipada/Dwitiya context | `FestivalRuleEngine`, `FestivalService::FESTIVALS`, `PanchangCalendarApiTrait` | Core observance follows Panchang practice and Dharma/Nirnaya Sindhu-style tithi-vrata nirnaya for tithi context. Numeric lag/elongation/illumination checks are explicitly modern heuristics, not classical textual rules. |
| Arunodaya length | Default 4 ghati, configurable 4-5 ghati at Ekadashi decision call site | `KalaNirnayaEngine::determineEkadashi()` | The default preserves current output; returned metadata exposes active arunodaya ghati/minute values. |
| Eclipse search | JME high-precision global eclipse search, then local-location visibility/contact evaluation | `EclipseService` | Solar and lunar eclipse outputs separate global contacts from local visibility where applicable. |
| Eclipse ritual visibility | Local visibility plus ritual magnitude minimum | `EclipseService` | `visible`, `sutak.applicable`, and ritual windows require local visibility and the configured ritual magnitude threshold. |
| Lunar eclipse ritual minimum | Umbral magnitude `>= 1/16` (`0.0625`) | `EclipseService::NIRNAY_LUNAR_ECLIPSE_MINIMUM_MAGNITUDE` | Astronomical magnitude is still reported when the ritual threshold is not met. |
| Solar eclipse ritual minimum | Eclipse/local disk magnitude `>= 1/12` (`0.083333...`) | `EclipseService::NIRNAY_SOLAR_ECLIPSE_MINIMUM_MAGNITUDE` | Astronomical/local magnitude is still reported when the ritual threshold is not met. |
| Eclipse sutak | Lunar: 3 prahar lookback; Solar: 4 prahar lookback | `EclipseService::sutak()` | Prahar boundaries are dynamic, using local sunrise/sunset day and night divisions. Sutak is not applicable when the eclipse is not ritually visible at the location. Outputs also expose Grast Uday/Grast Ast classification, telescope-only status, and post-eclipse bath/fresh-food guidance. |
| Eclipse edge classifications | Grastodaya/Grastasta handling, short local visibility checks, and penumbral/mandya eclipse filtering are rule branches to preserve where enabled | `EclipseService` | Source basis combines grahana-sutak tradition, local-visibility almanac practice, astronomical eclipse classification, and the half-ghadi / 12-minute short-visibility convention where that profile is enabled. |
| Prahar calculation | Variable prahar: day length divided by 4 and night length divided by 4 | `MuhurtaService`, `EclipseService` | Used for daily Prahar outputs and sutak boundary anchoring. |
| Brahma Muhurta | Dynamic night-muhurta convention by default | `PanchangService`, muhurta calculators | Previous sunset to sunrise is divided into 15 night muhurtas; Brahma Muhurta is the penultimate pre-sunrise night muhurta. |
| Output precision | Raw calculation floats are preserved internally; display formatting is configurable | `config/panchang.php`, `AstroCore` | Formatting settings affect representation, not the underlying calculation values. |

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

For the 30 named Muhurta devata enum specifically, see [MUHURTA_TEXT_SOURCES.md](MUHURTA_TEXT_SOURCES.md) for source classification and exact ślokas.

| File | Functions/Constants | Source Attribution |
|------|-------------------|-------------------|
| `Core/Enums/Muhurta.php` | 30 named Muhurta devata sequence:<br>- 15 day muhurtas beginning Rudra, Ahi, Mitra<br>- 15 night muhurtas beginning Isha, Ajapada, Ahirbudhnya | **Source-primary attribution:** Nārada Saṃhitā 9.1-5, Muhūrta-adhyāya; Kāśyapa as quoted in Vṛddha Vasiṣṭha Saṃhitā, Muhūrtādhyāya. Taittirīya Brāhmaṇa 3.10 is treated only as an older qualitative time-segment tradition, not the direct source for this named list. Detailed classification and exact ślokas: [MUHURTA_TEXT_SOURCES.md](MUHURTA_TEXT_SOURCES.md). |
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
| `PanchangService.php` | Timed Bhadra windows<br>Timed Varjyam windows<br>Timed Amrita Kaal windows<br>Timed Pradosha window<br>Daily Karmakala output assembly<br>Sunrise/current Panchanga state separation for Tithi, Nakshatra, Yoga, and Karana | **Published Panchang convention:** JME native ephemeris-based live calculation |
| `SpecialYogaCalculator.php` | `calculateSpecialYogas()`<br>Structured outputs for:<br>- Sarvartha Siddhi<br>- Amrit Siddhi<br>- Ravi Yoga<br>- Ravi Pushya<br>- Guru Pushya<br>- Dwipushkar<br>- Tripushkar<br>- Ganda Mula<br>- Vinchhudo<br>- Aadal<br>- Vidaal<br>- Jwalamukhi | **Package attribution:** Muhurta Chintamani, Muhurta Martanda, and regional/published Panchang tables. These are encoded as package rule mappings, not source-critical primary-text proofs for every table cell. |
| `SpecialYogaCalculator.php` | `calculateAnandadiYoga()` | **Package attribution:** Sripati/Jyotisha Ratnamala 28-nakshatra tradition, including the package's Abhijit-inclusive counting model (`rule_system = sripati_jyotisha_ratnamala_28_nakshatra`). The `current` window is selected for the calculation instant. |
| `SpecialYogaCalculator.php` | `calculateAmritadiYoga()` | **Package attribution:** Classical Amritadi weekday-nakshatra table as used in published Panchang literature (`rule_system = amritadi_yoga_27_nakshatra_7_weekday`). The `current` window is selected for the calculation instant. |
| `SpecialYogaCalculator.php` | `calculateMaitreyaYoga()` | **Package attribution:** Published muhurta/panchang combinational rule for debt-repayment muhurta using weekday + nakshatra + lagna overlap (`rule_system = weekday_nakshatra_lagna_debt_repayment`) |
| `SpecialYogaCalculator.php` | `calculateGajachchhayaYoga()` | **Package attribution:** Muhurta/Panchang tradition for Gajachchhaya variants using Tithi + Sun-nakshatra + Moon-nakshatra conditions; implemented as a package-known variant set, not as a claim of one single universal classical formula |
| `PanchakCalculator.php` | `calculatePanchak()` | **Published Panchang convention:** Moon in Dhanishtha pada 3 through Revati (`rule_system = moon_dhanishta_pada_3_to_revati`) |
| `ShoolaCalculator.php` | `calculateDishaShool()` | **Published Panchang convention:** common weekday-direction travel table used in general muhurta/almanac practice |
| `ShoolaCalculator.php` | `calculateNakshatraShool()` | **Published Panchang convention:** travel-direction table keyed by nakshatra (`source_family = popular_travel_muhurta_panchang_table`) |
| `VaasaCalculator.php` | `calculateRahuVaasa()`<br>`calculateChandraVaasa()`<br>`calculateShivaVaasa()`<br>`calculateAgniVaasa()`<br>`calculateYoginiVaasa()` | **Package attribution:** Muhurta Chintamani plus published Panchang/Nivas-Shool style tables. `Chandra_Vaasa` is exposed as Moon-rashi directional residence (`rule_system = moon_rashi_direction_4_direction`) and preserves the older nakshatra-pada abode windows under `nakshatra_pada_vaasa`. Nakshatra-pada current selection uses the calculation instant. Shiva/Agni/Yogini Vaasa are calculated for the input-time Tithi and preserve sunrise values under `at_sunrise`. |
| `EkadashiParanaCalculator.php` | `buildEkadashiObservance()`<br>`buildParanaPayload()` | **Traditional convention:** Nirṇaya Sindhu / Hari Bhakti Vilāsa style Ekadashi-parana handling via the package's Kala Nirnaya workflow |

---

### Verified Nirnay Decisions Promoted to Engine Rules

These rules have been promoted from general attribution into explicit engine decisions. They are still tradition-profile decisions, not claims that every Hindu almanac school resolves the rule identically.

| Rule Area | Implemented In | Engine Decision | Source Basis |
|-----------|----------------|-----------------|--------------|
| Vaishnava Ekadashi Dashami-vedha | `KalaNirnayaEngine::determineEkadashi()` | Dashami piercing beyond the 55th ghaṭikā from the previous sunrise marks the Ekadashi as viddha for Vaishnava/Nirnay handling. The engine stores the threshold as `DASHAMI_VEDHA_THRESHOLD_GHATIKAS_FROM_PREVIOUS_SUNRISE = 55`. | Swaminarayan `Satsangi Jeevan` Ekadashi decision passage, consistent with Nirnay-style Vaishnava observance handling. Common 4-ghaṭikā arunodaya remains documented separately but is not the active Nirnay vedha threshold. |
| Ekadashi Parana Nakshatra-Pada Restrictions | `EkadashiParanaCalculator::buildParanaPayload()` | Parana windows exclude Anuradha pada 1, Shravana padas 2-3, and Revati pada 4. The payload exposes both `restricted_windows` and allowed `parana_windows`. | Swaminarayan `Satsangi Jeevan` parana restriction passage, interpreted as specific nakshatra-pada blocked intervals. |
| Grahana Ritual Magnitude Thresholds | `EclipseService` | Ritual visibility/sutak requires lunar umbral magnitude at least `1/16` and solar eclipse magnitude at least `1/12`. Astronomical magnitude is still reported even when the ritual threshold is not met. | Brahmagupta/Khaṇḍakhādyaka-style eclipse magnitude rule as preserved in astronomical source summaries; aligned with the package's Nirnay-rule configuration. |

### Additional Source Rule Families

These source families are useful for attribution and future rule-hardening, but they should not be read as a claim that every branch is fully implemented in this package unless an active component is named above.

| Rule Area | Implementation Status | Source Basis |
|-----------|-----------------------|-----------------------|
| Grahana sutak in yama/prahara units | Implemented through dynamic prahar-based sutak windows where eclipse visibility qualifies | Sutak verse tradition for 4 yamas before solar eclipse and 3 yamas before lunar eclipse; Śrīmad Bhāgavatam 3.11.10 for four yamas in day/night; Bhāskara commentary to Āryabhaṭīya for day/night quarter definition of yama. |
| Grahana local edge cases | Partly implemented / profile-sensitive | Grastodaya and Grastasta classification, local-visible contact windows, penumbral/mandya lunar eclipse exclusion, and a half-ghadi / 12-minute short-visibility cutoff where that convention is enabled. |
| Ekadashi parana timing | Implemented with Nirnay/Satsangi restrictions and dynamic windows | Drik Panchang guidance that parana should occur within Dwadashi unless Dwadashi expires before sunrise; Harivasara as the first quarter of Dwadashi; Vaishnava/ISKCON parana pages for symbolic water-only parana in tight overlaps. |
| Vaishnava Ekadashi naming | Candidate metadata / profile refinement | Shikshapatri 81, Vitthalnathji vrata-nirnaya compliance, Kamakoti Dharma Sindhu Vaishnava/Smarta distinction, and Drik Panchang's eight Mahadvadashi profile names. |
| Rama Navami / Shri Ram Jayanti | Source basis for Madhyahna-vyapini rule | Vālmīki Rāmāyaṇa 1.18.8, Dharma Sindhu Madhyahna-vyapini Navami rule, and Drik Panchang Rama Navami timing. |
| Dhanteras and Lakshmi Puja | Source basis for Pradosha/night-window rules | Dharma Sindhu-style Trayodashi-in-Pradosha and Amavasya-in-night/Pradosha rules; Drik Panchang Dhantrayodashi and Diwali Puja timing pages. |
| Mahashivaratri | Source basis for Nishitha-vyapini Chaturdashi rule | Dharma Sindhu OCR preserving Nishitha selector and Drik Panchang Nishita Kaal / Chaturdashi timing. |
| Vamana Jayanti | Source basis for Dwadashi-Abhijit-Shravana priority | Śrīmad Bhāgavatam 8.18.5: Vamanadeva's appearance on Shravana Dwadashi in Abhijit Muhurta. |
| Narasimha Jayanti | Source basis for evening / Pradosha Chaturdashi | Dharma Sindhu-style Vaishakha Shukla Chaturdashi evening rule, Drik Panchang Narasimha Jayanti timing, and ISKCON public observance notes. |
| Govardhan Puja / Annakuta | Source basis for worship and offerings metadata | Śrīmad Bhāgavatam 10.24.25-26 and ISKCON public Govardhan Puja / Annakuta observance summaries. |
| Phuldolotsava profile split | Source basis for tradition-profile variants | BAPS and Swaminarayan.org public Fuldol/Pushpadolotsav pages for Fagun Vad 1 presentation; Satsangi Jivan volume 3 for Nar-Narayan / Phalguni-at-sunrise rationale. |
| Samavedi Shravani / Upakarma | Source basis for Hasta-priority rule family | Yājñavalkya Smṛti 1.142 as quoted in modern summaries, Samskaaram.com Sāma Veda Upākarmā procedure, and Saṃskāra-ratnamālā Upakarma material. |

---

### Pushtimarg / Vaishnava Sectarian Nirnaya Sources

These sources are relevant where the package models, or may later model, sect-specific festival scheduling, tithi conflict resolution, vrata conduct, and temple/seva calendar practice. They should be treated as tradition-profile sources, not universal Hindu Panchang rules.

| Component | Relevant Logic | Source Attribution |
|-----------|----------------|-------------------|
| `FestivalRuleEngine`, `FestivalService::FESTIVALS`, future `KalaNirnayaEngine` tradition profiles | Tithi conflict resolution, festival date shifting, and utsava timing where a Pushtimarg profile applies | **Utsava-Nirnaya** by Shri Vitthalnathji / Gusainji; Pushtimarg festival and tithi-vedha nirnaya framework |
| `FestivalRuleEngine`, vrata and grahana handling | Vrata conduct and eclipse-related timing in sectarian practice | **Bhakti-Hamsa** by Shri Vitthalnathji |
| `FestivalService::FESTIVALS`, seasonal or seva metadata | Seasonal seva, shringar, bhog, and utsava practice where metadata is tradition-specific | **Seva-Shlokah Shringar-Rasamandanam** by Shri Vitthalnathji |
| Nirnaya decision philosophy | Resolution of contradictions in religious duties | **Tattvartha-Dipa-Nibandha - Sarva-Nirnaya Prakarana** by Vallabhacharya |
| Operational yearly calendar practice | Living annual application of Pushtimarg nirnaya rules | **Utsav Tippani / Varsha Tippani** from Nathdwara / lineage calendar practice |

For Chandra Darshana specifically, these Pushtimarg sources are relevant only to tithi-conflict or observance-scheduling profile decisions. No numeric crescent-visibility thresholds are attributed to them.

---

### Tier 4: Published Panchang and Regional-Practice Conventions

These features are useful and intentionally included, but they are best described as **published Panchang conventions** or **regional almanac systems**, not universally fixed classical formulas.

| File | Functions | Source Attribution |
|------|-----------|-------------------|
| `MuhurtaService.php` | `calculateHora()`<br>`calculateHoraTable()`<br>`calculateChogadiya()`<br>`calculateChogadiyaTable()`<br>`calculateBadTimes()`<br>`calculateDaylightFivefoldDivision()`<br>`calculateNishitaMuhurta()`<br>`calculateVijayaMuhurta()`<br>`calculateGodhuliMuhurta()`<br>`calculateSandhya()`<br>`calculateGowriPanchangam()`<br>`calculateKalaVela()`<br>`calculatePrahara()`<br>`calculateBrahmaMuhurta()`<br>`calculateDurMuhurta()`<br>`calculateVarjyam()`<br>`calculateAmritaKaal()`<br>`calculateNakshatraPeriodWindows()`<br>`calculatePradoshaKaal()`<br>`calculateLagnaTable()` | **Published Panchang convention:** Varies by tradition and regional practice |
| `MuhurtaService.php` + extracted calculators | Daily output blocks:<br>- `Hora`<br>- `Chogadiya`<br>- `Hora_Full_Day`<br>- `Chogadiya_Full_Day`<br>- `Muhurta_Full_Day`<br>- `Rahu_Kaal_Gulika_Yamaganda`<br>- `Abhijit_Muhurta`<br>- `Prahara_Full_Day`<br>- `Daylight_Fivefold_Division`<br>- `Brahma_Muhurta`<br>- `Dur_Muhurta_Full_Day`<br>- `Nishita_Muhurta`<br>- `Vijaya_Muhurta`<br>- `Godhuli_Muhurta`<br>- `Sandhya`<br>- `Gowri_Panchangam`<br>- `Kala_Vela`<br>- `Karmakala_Windows`<br>- `Varjyam`<br>- `Amrita_Kaal`<br>- `Pradosha_Kaal`<br>- `Lagna_Full_Day` | **Published Panchang convention:** Implementation follows published almanac patterns across `DailyPeriodsCalculator`, `InauspiciousPeriodsCalculator`, `HoraCalculator`, `ChogadiyaCalculator`, `GowriPanchangamCalculator`, and `LagnaTableCalculator` |

**Notes:**
- `Gowri_Panchangam` is implemented as a Gowri-style 8-part day/night table based on the package's chosen published-table convention (Tamil Gowri/Pambu Panchangam)
- `Kala_Vela` is based on a secondary-source rule pattern attributed to Saravali-style descriptions, not a source-critical Sanskrit edition
- `Godhuli_Muhurta` and `Sandhya` are tradition-sensitive and may vary by school
- `Brahma Muhurta` uses the published Panchang dynamic night-muhurta convention by default: previous sunset to sunrise divided into 15 night muhurtas, with Brahma Muhurta as the penultimate pre-sunrise night muhurta. The fixed 48-minute convention is still exposed as a named nested convention.
- `Varjyam`, `Amrita_Kaal`, and `Pradosha_Kaal` in `PanchangService` are live-timed Panchang-day outputs and should be preferred over legacy scalar helpers when available. `Amrita_Kaal` is calculated from nakshatra-specific Amrita ghati offsets and is not derived from Varjyam.
- `Lagna_Full_Day` is a calculated ascendant-sign timing table for the day; it includes partial intervals that overlap the sunrise-to-next-sunrise Panchang day and is not a natal/person-specific reading
- Top-level `Tithi` and `Karana` retain sunrise semantics for backwards compatibility. Runtime values are exposed explicitly as `Current_Tithi_At_Input_Now` and `Current_Karana_At_Input_Now`.
- Festival output metadata intentionally separates canonical rule values from localized companion labels. For example, `calculation_basis.month.value` remains the machine-stable Sanskrit month key for the configured calendar type, while `calculation_basis.month.name` is localized for the active locale
- Special-yoga and vaasa outputs are package rule mappings used for general panchang and muhurta screening. They are not natal/person-specific interpretations
- Month calendar moonrise/moonset output is date-qualified because a lunar visibility interval can begin on one civil date and end after midnight on the next civil date

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

## Complete List of All Traditional Sources Referenced

This list is descriptive. Inclusion here means the package references or attributes logic to that source, source family, tradition, or published convention somewhere in code or docs. It does **not** mean every usage has been independently verified against a critical Sanskrit edition.

Some entries below are exact text names. Others are intentionally labeled as source families or living/published traditions because the package uses them that way in code metadata.

### Primary Astronomical Texts
1. Sūrya Siddhānta
2. Brahmagupta / Khaṇḍakhādyaka eclipse-magnitude tradition
3. Classical astronomy texts
4. Classical Panchanga Calculation Texts
5. Āryabhaṭīya and Bhāskara commentary tradition for yama/prahara definitions

### Muhurta Texts
6. Muhūrta Chintāmaṇi
7. Muhūrta Mārtaṇḍa
8. Kālaprakāśikā
9. Nirṇaya Sindhu
10. Dharma Sindhu / Dharma_Sindhu
11. Kamakoti Dharma Sindhu translation / public digest
12. Dharma Sindhu OCR / archive-public text references
13. Ernst Wilhelm's Classical Muhurta
14. Classical Choghadiya texts
15. Classical Yoga texts
16. Visha Ghati constants

### Dharmashastra Texts
17. Manusmṛti
18. Yājñavalkya Smṛti
19. Saṃskāra-ratnamālā

### Ayurveda Texts
20. Aṣṭāṅga Hṛdaya
21. Charaka Saṃhitā

### Jyotisha Texts
22. Bṛhat Saṃhitā
23. Bṛhat Jātaka
24. Nārada Saṃhitā, Muhūrta-adhyāya 9.1-5
25. Kāśyapa quotation in Vṛddha Vasiṣṭha Saṃhitā, Muhūrtādhyāya
26. Sarāvalī
27. Gargiya Jyotisha
28. Jyotisha Ratnamala / Sripati tradition

### Puranic Texts
29. Śrīmad Bhāgavata Purāṇa
30. Agni Purāṇa
31. Śrīmad Bhāgavatam 3.11.10 yama/time-unit reference
32. Śrīmad Bhāgavatam 8.18.5 Vamana Jayanti reference
33. Śrīmad Bhāgavatam 10.24.25-26 Govardhan/Annakuta references
34. Vālmīki Rāmāyaṇa, Bāla Kāṇḍa, Sarga 18

### Devotional and Commentarial Sources
35. Gīta Govinda / Jayadeva tradition

### Regional/Almanac Conventions
36. Tamil Gowri Panchangam
37. Pambu Panchangam
38. Popular travel-muhurta Panchang tables
39. Nivas-Shool / Vaasa style published Panchang tables
40. Drik Nivas-Shool Panchang style
41. Drik Panchang Ekadashi / Harivasara / Parana pages
42. Drik Panchang Mahadvadashi / ISKCON Parana pages
43. Drik Panchang festival timing pages for Rama Navami, Dhantrayodashi, Diwali, Mahashivaratri, Narasimha Jayanti, and Chandra Darshana
44. Modern Nivas-Shool Panchang style
45. Published Gowri/Pambu table convention
46. Published Panchang dynamic night-muhurta convention
47. Observed Panchang convention / tradition-dependent rules

### Vāstu Texts
48. Māyamata

### Āgama Texts
49. Vaikhānasa Āgama

### Gṛhya Sūtra Texts
50. Aśvalāyana Gṛhya Sūtra

### Vaishnava Texts
51. Hari Bhakti Vilāsa
52. Satsangi Jeevan
53. Shikshapatri
54. Utsava-Nirnaya (Shri Vitthalnathji / Gusainji)
55. Bhakti-Hamsa (Shri Vitthalnathji)
56. Seva-Shlokah Shringar-Rasamandanam (Shri Vitthalnathji)
57. Tattvartha-Dipa-Nibandha - Sarva-Nirnaya Prakarana (Vallabhacharya)

### Sectarian and Regional Traditions
58. Smarta tradition
59. Vaishnava tradition
60. Pushtimarg / Vallabha tradition
61. Utsav Tippani / Varsha Tippani (Nathdwara / Pushtimarg lineage)
62. ISKCON / Gaudiya Vaishnava tradition
63. ISKCON Bangalore and ISKCON Mumbai public observance summaries
64. Swaminarayan tradition
65. BAPS Swaminarayan Sanstha public festival references
66. Swaminarayan.org public festival references
67. Bengal / Bengali tradition
68. Odia / Odisha tradition
69. Tamil tradition
70. South Indian / Telugu / Andhra tradition
71. Malayalam / Kerala tradition
72. Nepali tradition
73. Himalayan / Tibetan Buddhist regional observance
74. Kutchi / Gujarat regional observance
75. Samaveda Upakarma / Samavedi Shravani procedure summaries

### Modern Systems
76. KP System (Krishnamurti Paddhati)
77. JME native ephemeris
78. Drik Panchang-style modern published almanac convention
79. Bharat Discovery public grahana-sutak citation
80. Indica Today Upakarma/Utsarjana summary
81. Samskaaram.com Sāma Veda Upākarmā procedure reference

### Living Traditions
82. Sandhyāvandanam Tradition
83. Puri Shankaracharya Swami Nischalananda Saraswati / modern living-authority citation

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
