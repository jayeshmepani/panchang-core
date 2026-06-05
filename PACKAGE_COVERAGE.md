# Panchang Core Package Coverage

This document explains, in a user-facing way, what the package covers across its full calculation surface. The goal is to make the package scope easy to understand without needing to inspect code, scripts, or raw JSON output structures.

The package is not limited to a simple daily panchang. It covers core panchanga elements, Hindu calendar layers, muhurta and karmakala windows, electional screening, festival and vrata resolution, multi-day observance families, and eclipse data with sutak logic.

## What This Package Is Built To Cover

At a high level, the package covers:

- daily Vedic panchanga
- astronomical day markers tied to a real location and timezone
- traditional Hindu calendar layers
- muhurta, hora, choghadiya, lagna, and other time quality windows
- electional filters and classical accept/reject checks
- yearly and daily festival resolution
- vrata and observance logic
- eclipse calendars and eclipse observance support
- localization-ready structured output
- compact yearly festival and vrata JSON generation in by-date form

It supports both:

- day-level usage, such as “give me today’s complete panchang”
- calendar-level usage, such as “give me a month calendar”, “give me a year of festivals”, or “give me eclipse data for multiple years”

It also separates multiple usage styles that are often mixed together in smaller libraries:

- plain daily lookup
- current-moment evaluation
- month calendar rendering
- yearly festival aggregation
- multi-year eclipse aggregation
- transit-only muhurta judgement

For yearly observance exports, the package supports:

- combined yearly observance output
- festival-only yearly output
- vrat-only yearly output
- selective runtime access to both `by_date` and flat forms
- compact CLI-generated yearly JSON in `by_date` form
- compact vrat JSON that extracts repeated weekday vrats into a shared recurring block

## Daily Panchanga Coverage

The package covers the five foundational panchanga limbs in a full operational way, not just by name.

### Tithi

The package covers:

- current tithi
- explicit sunrise tithi
- explicit current/input-time tithi
- tithi number and tithi name
- paksha attached to the tithi
- normalized and absolute tithi context
- fraction remaining in the current tithi
- tithi start time
- tithi end time
- tithi-aware observance decisions where festival timing depends on sunrise, pradosha, nishita, or other karmakala conditions

This means the package does not just label the tithi. It also tracks the timing interval needed for vrata and festival observance logic.

### Vara

The package covers:

- weekday index
- weekday name
- vara sequence context
- use of vara in muhurta evaluation
- vara use in dagdha yoga and other electional checks

### Nakshatra

The package covers:

- current nakshatra
- explicit sunrise nakshatra
- explicit current/input-time nakshatra
- nakshatra number and name
- nakshatra pada
- nakshatra lord
- nakshatra end time
- nakshatra use in festival determination
- nakshatra use in varjyam calculation
- nakshatra-specific observance handling where the rule depends on a specific star rather than only tithi

### Yoga

The package covers:

- current yoga
- explicit current/input-time yoga
- yoga number and yoga name
- yoga end time
- yoga participation in muhurta and electional screening

### Karana

The package covers:

- current karana
- explicit sunrise karana
- explicit current/input-time karana
- karana number and karana name
- karana end time
- vishti karana identification
- bhadra-related logic where vishti/bhadra status matters for muhurta and rejection handling

### Panchanga Transition Windows & Timeline Modeling

Rather than only providing the Panchanga values active at sunrise or at a specific input time, the package calculates and exposes full transition timelines for each limb. This allows callers to map the exact start and end boundaries of each limb across the Panchang day:

- **Tithi Windows**: Full start, end, and duration tracking for all overlapping Tithis (`Tithi_Windows`).
- **Nakshatra Windows & Padas**: Complete boundary intervals (`Nakshatra_Windows`) and the active quarters/divisions (`Nakshatra_Padas`).
- **Yoga Windows**: Consecutive Yoga transitions across the civil day (`Yoga_Windows`).
- **Karana Windows**: Precision timelines for the half-Tithi periods (`Karana_Windows`).
- **Yoga & Karana Interval Collectors**: Dynamic lists generated via `collectYogaIntervals()` and `collectKaranaIntervals()`.
- **Bounded Backtracking**: Refined reverse-angle solving to locate boundary transitions with maximum mathematical precision, avoiding clamp resets or infinite loops.

## Astronomical Day Coverage

The package covers the daily astronomical anchors required for panchanga, festival timing, and muhurta.

### Solar Anchors

The package covers:

- sunrise
- sunset
- solar noon
- solar midnight
- civil day boundaries
- mean solar day length
- apparent solar day length

This matters because many traditional calculations are not based on clock midnight. They are based on sunrise, sunset, night division, solar midpoint, or a derived fraction of day or night. The package carries these anchors into downstream calculations instead of treating them as isolated informational fields.

These are used not only for display, but also for dividing the day into hora, choghadiya, muhurta, prahara, and other classical segments.

### Lunar Anchors

The package covers:

- moonrise
- moonset
- moon sign at the evaluated day context
- visual moon phase at sunrise
- visual moon phase at the evaluated current moment
- lunar age for visual moon-phase output

### Moon Phase Coverage

The package separately covers the visual phase of the Moon, rather than forcing clients to infer it from Tithi.

This includes:

- new moon
- waxing crescent
- first quarter
- waxing gibbous
- full moon
- waning gibbous
- last quarter
- waning crescent
- visibility-oriented descriptions for each phase
- illumination range and illumination percentage
- synodic age

This is important because Tithi and visual moon phase are related but not identical concepts. The package now exposes visual phase as its own first-class output for day and month rendering.

### Twilight Coverage

The package covers:

- civil dawn and civil dusk
- nautical dawn and nautical dusk
- astronomical dawn and astronomical dusk

This gives the package support for day-boundary awareness beyond only sunrise and sunset.

### Additional Daily Time Anchors

The package covers:

- ishtkaal
- ayanamsa at the evaluation moment
- sun longitude at sunrise context
- moon longitude at sunrise context

These values support both descriptive output and deeper calculations such as tithi, yoga, karana, lagna, and electional screening.

## Hindu Calendar Coverage

The package covers both the astronomical and calendrical layers that users usually expect from a traditional Hindu calendar engine.

### Month Systems

The package covers:

- amanta month naming
- purnimanta month naming
- switching between amanta and purnimanta calendar representations
- month index tracking for both systems
- dynamic calendar-type-aware output

This is important because many festival dates and month labels differ depending on whether the consumer expects amanta or purnimanta tradition.

### Paksha

The package covers:

- shukla paksha
- krishna paksha
- paksha-aware tithi normalization
- paksha-aware festival and vrata rules

### Seasonal And Year Layers

The package covers:

- ayana
- ritu
- vikram samvat
- gujarati samvat
- saka samvat
- kali samvat
- samvatsara
- north Indian samvatsara naming

### Month Anomaly Detection

The package covers:

- adhika month detection
- kshaya month detection
- festival and observance decisions that must account for adhika or kshaya month behavior

## Muhurta And Time Window Coverage

This is one of the largest parts of the package. It covers both “what is active right now” style output and full-day tables.

### Hora Coverage

The package covers:

- current hora
- hora number
- day hora versus night hora
- hora ruler
- hora duration
- full-day hora table for all 24 horas

This means the package can tell a user both “which hora is active right now” and “what is the entire hora sequence for the whole day and night”.

### Choghadiya Coverage

The package covers:

- current choghadiya
- day or night mode
- choghadiya division number
- choghadiya name
- auspicious versus inauspicious classification
- full-day choghadiya table

The output distinguishes day and night choghadiya rather than flattening them into a single generic table.

### Muhurta Table Coverage

The package covers:

- full day muhurta sequence
- day muhurta sequence
- night muhurta sequence
- named muhurta rows

Operationally, this gives coverage for the full 30-muhurta model:

- 15 day muhurtas
- 15 night muhurtas

This makes the package suitable for both snapshot output and timetable-style calendar rendering.

### Classical Karmakala Windows

The package covers:

- Rahu Kaal
- Gulika Kaal
- Yamaganda
- Abhijit Muhurta
- Brahma Muhurta
- Dur Muhurta
- Nishita Muhurta
- Vijaya Muhurta
- Godhuli Muhurta
- Pradosha Kaal
- Varjyam
- Amrita Kaal

These are not just names. The package also exposes timing windows, durations, availability, auspiciousness state, and active/inactive status depending on the current moment.

Where relevant, the package also distinguishes between:

- the base window itself
- the effective usable window
- whether the required tithi or condition actually overlaps the window
- sunrise-based Panchanga state and input-time/current Panchanga state
- dynamic night-muhurta Brahma Muhurta and the preserved fixed 48-minute convention
- daylight-midpoint Abhijit output, named separately from apparent solar noon

### Additional Time Divisions

The package covers:

- prahara
- daylight fivefold division
- sandhya windows
- gowri panchangam
- kala vela

The package therefore covers both commonly requested panchang windows and several traditional sub-divisions that many simplified APIs leave out.

### Lagna Coverage

The package covers:

- current lagna
- nirayana lagna longitude
- sayana lagna longitude
- sign index and sign name
- degree within the sign
- ayanamsa applied to the lagna
- full-day lagna table
- partial lagna intervals that overlap the sunrise-to-next-sunrise Panchang day

This means the package supports both instant lagna lookup and full-day lagna progression output.

The full-day lagna coverage is especially useful for:

- lagna selection by time
- calendar views that need sign ingress blocks
- electional use where sign changes across the day matter

## Electional And Screening Coverage

The package does not stop at listing windows. It also covers decision-oriented muhurta screening based on classical rejection and acceptance logic.

### Special Yoga Coverage

The package covers day-level special yoga signals useful for muhurta screening and panchang presentation:

- Sarvartha Siddhi Yoga
- Amrit Siddhi Yoga
- Ravi Yoga
- Ravi Pushya Yoga
- Guru Pushya Yoga
- Dwipushkar Yoga
- Tripushkar Yoga
- Ganda Mula
- Vinchhudo
- Aadal Yoga
- Vidaal Yoga
- Jwalamukhi Yoga
- Anandadi Yoga, including the 28-nakshatra Abhijit variant
- Amritadi Yoga, covering the full 27 nakshatra by 7 weekday table
- Panchak, from Dhanishta pada 3 through Revati
- Maitreya Yoga, using strict weekday, nakshatra, and lagna overlap
- Gajachchhaya Yoga, including the known tithi, Sun-nakshatra, Moon-nakshatra, and Pitru Paksha variants

These outputs are exposed as structured daily signals rather than loose display text, so callers can inspect the rule key, active status, contributing tithi/nakshatra/weekday context, and timing windows where applicable.

### Direction And Vaasa Coverage

The package covers classical direction and vaasa-style day checks:

- Disha Shool
- Nakshatra Shool
- Rahu Vaasa
- Chandra Vaasa
- Shiva Vaasa
- Agni Vaasa
- Yogini Vaasa

These are general panchang/electional checks, not natal/person-specific predictions. They are intended for calendar, almanac, and muhurta-screening use cases.

### Panchaka And Core Suitability Checks

The package covers:

- panchaka rahita
- panchaka dosha evaluation
- auspicious versus inauspicious classification for panchaka remainder logic

This is important because the package does not only expose raw components. It also provides interpretive readiness checks built on those components.

### Dagdha And Rikta Filters

The package covers:

- dagdha tithi checks
- dagdha yoga checks
- rikta tithi checks

These are exposed as interpretive screening results, including whether the relevant dosha is active and what the decision layer says about it.

The output also carries descriptive verdict language such as whether the dosha is absent, active, mild, or blocking.

### Bhadra And Vishti Handling

The package covers:

- vishti karana detection
- bhadra window detection
- bhadra active/inactive state
- bhadra availability checks
- bhadra suitability for muhurta rejection logic

This is one of the places where the package connects raw panchanga state to actual “can this be used” style output.

### Varjyam And Amrita Kaal Screening

The package covers:

- varjyam window generation
- active varjyam status
- multiple varjyam windows where applicable
- amrita kaal availability
- amrita kaal active/inactive status
- independent Amrita ghati-offset calculation, not a derived "Varjyam end" shortcut
- shared Nakshatra-period window payloads with ghati offset, duration, full window, visible window, and partial-window flags

The presence of multi-window support matters because some days need more than a single simplified interval.

### Abhijit Evaluation

The package covers:

- abhijit muhurta timing
- whether the current moment is inside abhijit
- abhijit cancellation/override style evaluation
- identification of which doshas are considered cancellable in that context

### Transit-Oriented Muhurta Evaluation

The package covers:

- transit-only muhurta suitability snapshot
- verdict-style acceptance or rejection output
- severity-based screening data
- rejection report style reasoning
- current-time suitability framing for the configured location and date-time

This means the package does not only dump a bag of windows. It can also produce an opinionated evaluation layer describing whether the current moment passes or fails the bundled screening checks.

### Classical Rulebook Style Areas

The package covers:

- dharma sindhu style punya kaal handling for sankranti
- viddha tithi style decision handling
- karmakala-based observance decisions
- ekadashi determination logic
- kunda lagna support
- transit moorthy style evaluation

In practice, this lets the package answer questions such as:

- whether a vrata should follow sunrise logic or a stricter ritual window
- whether a Sankranti-related observance should use a punya kaal interval
- whether a nominally matching tithi is rejected because the required ritual condition is absent

## Festival Coverage

Festival support is one of the largest and most detailed areas of the package.

### Overall Festival Scope

The package covers:

- yearly festival calendar generation
- daily festival snapshots
- month calendar festival embedding
- daily observances in addition to named festivals
- 391 packaged festival definitions
- multi-rule observance resolution
- location-aware and timezone-aware output

The package output also tracks yearly scale information such as:

- total festival count
- number of calendar days carrying at least one festival
- number of festival entries when a day contains more than one observance

The package also supports separate yearly output paths for:

- combined festival + vrat observance output
- festival-only output
- vrat-only output

### Festival Resolution Dimensions

The package covers festival resolution across:

- tithi-based rules
- nakshatra-based rules
- solar sankranti-based rules
- sunrise-based observance logic
- pradosha-based observance logic
- nishita-based observance logic
- strict karmakala rules
- non-strict karmakala rules
- vriddhi and kshaya tithi decision handling
- relative-day observance logic
- adhika month aware rules
- kshaya month aware rules

This is a major part of the package value. It does not assume every observance can be placed by a simple tithi-name match alone.

### Festival Metadata Surface

Festival payloads cover:

- festival name
- localized festival name
- description
- deity
- fasting marker
- region list
- aliases
- observance notes
- calculation basis

### Vrat Coverage Highlights

The package covers a broad recurring-vrata surface, not only one-off named festivals.

This includes:

- monthly Ekadashi observances
- Vaishnava / ISKCON Ekadashi observance surfacing
- Mahadwadashi surfacing when Vaishnava fasting shifts to Dwadashi
- monthly Pradosh Vrat observances
- monthly Sankashti Chaturthi observances
- monthly Vinayaka Chaturthi observances
- monthly Purnima Vrat observances
- monthly Amavasya observances
- monthly Masik Shivaratri observances
- monthly Masik Krishna Janmashtami observances
- monthly Kalashtami observances
- monthly Durgashtami observances
- monthly Chandra Darshana observances
- Rohini Vrat
- Shri Satyanarayana Vrat
- Varalakshmi Vratam
- Jivitputrika Vrat
- Ashoka Ashtami Vrat
- Asha Dashami Vrat
- Durva Ashtami Vrat
- Skanda Sashti observances
- Shravana Somwar observances
- Mangala Gauri observances
- weekday vrata families for Sunday through Saturday

The package also covers longer observance periods and special vrata contexts such as:

- Purushottam Maas begin/end observances
- Chaturmasa begin/end observances
- Adhika Maas-only vrata handling
- Vaishnava fast-day shifting and derived observances
- resolution metadata
- winning reason
- winning score
- rule application summary

This means the package covers not only “which festival is on this date” but also “why it was placed on this date”.

That explanation layer is especially useful when:

- the same nominal tithi touches multiple civil dates
- a strict karmakala rule changes the final observance date
- a festival is shifted forward or backward by rule
- a day inherits a related observance from a parent festival

### Festival Alias & Deduplication Engine

The festival resolution pipeline features a built-in alias and deduplication mechanism:

- **Regional Aliases**: Major festivals support regional variants (e.g., Makara Sankranti maps to Uttarayan, Pongal, Khichdi, Til Sankranti, Maghi, Sakraat, etc.; Vinayaka Chaturthi maps to Ganesha Jayanti).
- **Deduplication Logic**: A post-processing step automatically purges redundant entries if a festival's name appears within another festival's alias list.
- **Specific vs. Generic Priority**: For mutually aliased festivals (e.g., a generic `Pradosh Vrat` vs a specific `Guru Pradosh Vrat`), the deduplication logic preserves the more specific weekday-based variant by evaluating alias array lengths.
- **Canonical-Event-Centered Modeling**: Standalone historical festival definitions (such as Hanuman Jayanti, Buddha Purnima, Ganesh Visarjan) have been deprecated and restructured under their canonical parent events (e.g., Shani Jayanti and Vat Savitri Vrat are merged under Vaishakha Amavasya rules).

## Festival Families And Multi-Day Sequences

The package covers festival families where observance is not a single isolated event.

### Multi-Day Family Support

The package covers:

- holi family
- diwali family
- navaratri family
- gupt navaratri family
- janmashtami family handling
- parent-child festival relationships
- day-after observances
- multi-day chain orchestration

This means the package can handle festival families where one event is not enough and where later observances depend on the earlier one having been placed first.

### Examples Of Family Style Coverage

The package includes support for patterns such as:

- Holika Dahan followed by Dhuleti
- Diwali sequence handling across Dhanteras, Naraka Chaturdashi, Lakshmi Puja, Govardhan Puja, and Bhai Duj
- Navaratri day-by-day progression
- Janmashtami tradition-sensitive handling
- Gupt Navaratri day progression and parana-style closeout handling

The generated outputs also show explicit sequence-style coverage for festivals that span multiple named days rather than just one summary label.

## Festival Categories Explicitly Represented

The package covers many categories of observances, including but not limited to:

- ekadashi observances
- sankashti chaturthi observances
- pradosh vrat observances
- masik shivaratri observances
- purnima observances
- amavasya observances
- sankranti observances
- jayanti observances
- navaratri observances
- gupt navaratri observances
- solar new year related observances
- swaminarayan and BAPS observances present in the packaged rules
- regional observances present in the packaged rules
- fasting-oriented observances
- deity-centered observances
- month-specific vrata and parva style observances

From the packaged outputs, the festival set includes recurring and major observances such as:

- ekadashi cycles
- sankranti cycles
- pradosh cycles
- sankashti cycles
- maha shivaratri
- masik shivaratri
- rama navami
- krishna janmashtami
- ganesha chaturthi
- holika dahan
- diwali family observances
- sharad purnima
- guru nanak jayanti
- dattatreya jayanti
- regional solar new year and sankranti-linked observances

The packaged rule set also includes:

- pan-Indian observances
- region-tagged observances
- tradition-tagged observances
- Swaminarayan and BAPS-specific observances present in the packaged definitions
- recurring monthly vrata cycles
- annual major parva and jayanti observances

## Calendar Output Coverage

The package covers more than one output shape.

### Today Snapshot

The package covers a full “today” style output with:

- panchanga
- astronomy markers
- muhurta windows
- festival snapshot
- observance snapshot
- electional screening
- current-time suitability framing

This is the most comprehensive single-day output surface in the package.

### Month Calendar Output

The package covers month calendar output with:

- one entry per day
- daily panchanga summary
- compact Tithi display for calendar cells
- kshaya/skip-Tithi metadata where relevant
- tithi transition windows
- nakshatra transition windows
- nakshatra pada transition windows
- yoga transition windows
- karana transition windows
- sunrise and sunset
- moonrise and moonset
- moonrise and moonset event dates
- moonrise and moonset date-time strings
- explicit moonset same-day/next-day relation
- grouped moon visibility interval metadata
- month-level visual moon phase output
- festival list for the date
- daily observances
- sankranti markers where relevant

This makes the package usable for calendar-grid style applications where the consumer wants one compact structured entry per date.

The month-calendar output is not limited to a simple sunrise snapshot. It now also supports richer day-cell rendering where a client needs to show:

- a compact Tithi label such as `30/1`
- whether a skipped Tithi occurred on that date
- which Nakshatra, Yoga, or Karana changed during the day
- what visual moon phase should be shown in the calendar cell

The moon visibility metadata is intentionally date-qualified. Some lunar visibility intervals start on one civil date and end after midnight on the next civil date, so a time-only `moonset` value can be visually earlier than `moonrise`. The month output therefore carries both compact display fields and explicit date-aware fields.

The package also supports exact month-range selective month output. A consumer can request a range such as October 2026 through August 2028 and the package will iterate only those months instead of generating extra months outside the requested window.

### Year Festival Output

The package covers:

- full-year festival generation
- by-date festival grouping
- yearly counts
- total festival counts
- rule-rich observance metadata

This is designed for users who need a ready-to-consume festival calendar rather than manually looping through each day and resolving observances themselves.

The package now also supports exact month-range selective festival and vrat output. That means a consumer can request a bounded window such as October 2026 through August 2028 and calculate only that requested span instead of computing whole surrounding years and slicing afterward.

### Raw Long-Range Output

The package also covers long-range raw output generation for batch inspection and dataset-style use.

For eclipse data, the package supports both year-range generation and exact month-range selective generation. The month-range path uses direct date-range eclipse searches so the result window is bounded to the requested span instead of being built from full-year output and filtered later.

That makes it suitable not only for end-user display but also for:

- QA and auditing
- manual rule verification
- downstream transformation into other app formats
- research-style inspection of date behavior across long spans

## Eclipse Coverage

The package covers both solar and lunar eclipses, with a practical observance-oriented payload.

### Core Eclipse Data

The package covers:

- solar eclipses
- lunar eclipses
- eclipse type classification
- global eclipse type classification
- local eclipse type classification for the configured location
- date and datetime of maximum eclipse
- Julian day of maximum eclipse
- magnitude data
- contact timing data
- duration data

For visible and non-visible eclipses alike, the payload still preserves the classification and event structure rather than collapsing them into a simple yes/no list.

Solar eclipse payloads separate global catalog type from local visible type. For example, a globally total or annular eclipse can still be locally partial at the configured coordinates.

### Visibility Coverage

The package covers:

- local visibility
- astronomical visibility
- visibility flags
- visibility-aware output for the configured location

This matters because eclipse observance and sutak logic depend on local visibility rather than only on the existence of a global eclipse.

### Sutak Coverage

The package covers:

- sutak applicability
- reason when sutak does not apply
- sutak start and end windows
- relaxed sutak windows
- duration information for sutak logic

### Multi-Year Eclipse Output

The package covers:

- multi-year eclipse calendar generation
- by-year grouping
- total eclipse count over the selected range

This allows the package to serve both as a daily-use panchang engine and as a longer-range eclipse calendar provider.

## Localization And Presentation Coverage

The package covers presentation-aware output rather than bare numbers alone.

### Language Coverage

The package covers:

- English output
- Hindi output
- Gujarati output

### Localized Value Surface

The package covers:

- localized panchanga names
- localized festival names
- localized month names
- localized paksha names
- natural English-facing labels where a true English equivalent exists
- localized calendar labels
- localized special-yoga labels and effects
- localized disha/vaasa labels and effects
- Moon-rashi directional Chandra Vaasa with the older nakshatra-pada Vaasa preserved separately
- human-readable time and duration rendering

The output is therefore usable in both:

- structured machine consumption
- end-user interfaces that need readable labels and rendered durations

### Units And Display Context

The package covers:

- angle-aware values
- duration-aware values
- Julian day values where needed
- date-time formatted strings
- machine-friendly fields alongside display-friendly fields

This dual style matters because the package often exposes both:

- raw or structurally precise values for downstream logic
- ready-to-display values for user interfaces and exports

## In Practical Terms

If someone asks what this package covers, the shortest accurate answer is:

It covers the full daily panchanga, location-aware astronomical timings, both amanta and purnimanta Hindu calendar layers, a deep set of muhurta and karmakala windows, special-yoga and vaasa/direction checks, electional screening logic, a large yearly festival and vrata engine with 391 packaged definitions, multi-day festival family handling, and solar/lunar eclipse output with sutak support.

If someone asks what kind of use cases it supports, the package is suitable for:

- daily panchang applications
- calendar and almanac generation
- mobile and web month-calendar rendering with compact day-cell metadata
- lunar phase display for widgets, calendars, and daily cards
- vrata and festival date lookup
- muhurta display and filtering
- regional and tradition-aware observance presentation
- eclipse and sutak calendars
- structured API or JSON export style output
