# Panchang Core Gap TODO

This file tracks the audited gap list against the current `panchang-core` package.

Status rules used here:

- `[x]` completed and publicly exposed in a real package surface
- open item means still partial or missing

This file keeps the broader audit list, not only the narrowed current implementation tranche.

## Partially Missing

- [x] **Panchak weekday-type classification**
  - Active Panchak windows exist.
  - Public Panchak output now includes weekday-based subtype output tied to Panchak start.

- [x] **Tithi dosha family broader public exposure**
  - Previously only narrower public checks were easy to see.
  - Completed public daily exposure now includes:
    - `mrityu`
    - `dagdha`
    - `visha`
    - `hutashana`
    - `krakacha`
    - `samvarta`
  - Public surface:
    - `Vara_Tithi_Doshas`

- [x] **Vyatipata / Vaidhriti dedicated public handling**
  - Previously these existed in rule tables but were not exposed as a dedicated public daily flag payload.
  - Completed public daily exposure now includes:
    - `Vyatipata`
    - `Vaidhriti`
    - `is_kranti_dosha`
    - prohibition-oriented flagging
  - Public surface:
    - `Nitya_Yoga_Observations`

- [x] **Viddha / Parana generalized family support**
  - Completed:
    - generic `Tithi_Observance_Analysis`
    - `Shuddha / Viddha / Kshaya`
    - `Vriddhi` detection across two sunrises
    - strong Ekadashi Parana coverage
    - supported non-Ekadashi `Vrata_Parana` family output

- [x] **Travel / Yatra screening**
  - Current coverage includes:
    - `Disha_Shool`
    - `Nakshatra_Shool`
    - multiple `Vaasa` outputs
  - Public coverage now also includes:
    - full travel-direction grid
    - urgent-travel exception guidance
    - `Yatra_Screening`

## Completed From The Original Missing List

- [x] **Nakshatra Tyajya**
  - Publicly surfaced as the explicit Varjyam-equivalent Nakshatra Thyajya window.

- [x] **Generalized non-Ekadashi Parana_Time endpoint / family for vratas**
  - Publicly surfaced as supported `Vrata_Parana` families.

- [x] **Explicit Dinamana / Ratrimana named public payload in traditional units**
  - Completed public exposure now includes:
    - `Dinamana`
    - `Ratrimana`
    - `seconds`
    - `minutes`
    - `hours`
    - `ghati`
    - `pala`
  - Public surface:
    - `Day_Night_Measures`

## Additional Completed Public Surfaces From This Audit Cycle

- [x] `Vara_Tithi_Doshas`
- [x] `Nitya_Yoga_Observations`
- [x] `Day_Night_Measures`
- [x] `Tithi_Observance_Analysis`
- [x] `Yatra_Screening`
- [x] `Nakshatra_Tyajya`
- [x] `Vrata_Parana`

## Excluded By Current Scope

- [x] **Planetary avastha-style first-class daily section**
  - Removed from the public daily Panchang surface per current scope.

- [x] **Transit Moorthy broader Saṃhitā expansion**
  - Removed from the public daily Panchang surface because it relies on natal / Janma-reference logic and does not fit the package's non-natal policy.

- [x] **Graha Yuti**
  - excluded

- [x] **Graha Yuddha**
  - excluded

- [x] **Rohini Shakata Bheda**
  - excluded

- [x] **Nakshatra Svabhava classification payloads**
  - excluded

- [x] **Krishi Panchang**
  - excluded

- [x] **Vastu Shanti / Bhumi Pujan / Griha Pravesh-specific dosha engine**
  - excluded

- [x] **Panchak Shanti**
  - excluded as remedial

## Notes

- Items are checked only when the package exposes a real public output or stable reusable engine for them.
- Internal constants or raw astronomy support alone do not count as completed.
