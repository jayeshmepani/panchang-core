# Chandra Darshana Decision Tree

This document describes the production resolver for `Chandra Darshana` and
`Adhika Chandra Darshana`, implemented in
`src/Festivals/FestivalRuleChandraDarshana.php`.

The resolver is a source-sensitive monthly first-crescent model. It does not use
the older monthly "Sud 1 if Pratipada is short, otherwise Sud 2" selector, and
it does not use modern 38-minute / 7-degree / 9-degree / 0.8% crescent
thresholds.

## Source Boundary

The critical limitation is part of the algorithm:

> None of the source traditions used here explicitly gives a complete universal
> monthly rule saying "observe Chandra Darshana on Shukla Pratipada in condition
> X, otherwise on Dvitiya."

Production therefore keeps two layers separate:

| Layer | Result |
|---|---|
| Strict source-only | `MONTHLY_DATE_TEXTUALLY_UNDETERMINED` |
| Operational calendar | Select the earliest post-Amavasya local evening that passes the engine's modern proxy for the traditional 12-bhaga first-crescent indication |

The public date is an application definition, not a claim that the sources state
a universal monthly date-selection command.

## Sanskrit Witnesses

### Surya Siddhanta 10.1-10.5

The astronomical layer is based on the traditional 12-bhaga crescent indication,
but production does not claim to recompute the entire Siddhantic chapter 10
procedure.

```text
उदयास्तविधिः प्राग्वत् कर्तव्यः शीतगोरपि ।
भागैर्द्वादशभिः पश्चाद्दृश्यः प्राग्यात्यदृश्यताम् ॥ १०.१ ॥

रवीन्द्वोः षड्भयुतयोः प्राग्वल्लग्नान्तरासवः ।
एकराशौ रवीन्द्वोश्च कार्या विवरलिप्तिकाः ॥ १०.२ ॥

तन्नाडिकाहते भुक्ती रवीन्द्वोः षष्टिभाजिते ।
तत्फलान्वितयोर्भूयः कर्तव्या विवरासवः ॥ १०.३ ॥

एवं यावत् स्थिरीभूता रवीन्द्वोरन्तरासवः ।
तैः प्राणैरस्तमेतीन्दुः शुक्लेऽर्कास्तमयात् परम् ॥ १०.४ ॥

भगणार्धं रवेर्दत्त्वा कार्यास्तद्विवरासवः ।
तैः प्राणैः कृष्णपक्षे तु शीतांशुरुदयं व्रजेत् ॥ १०.५ ॥
```

Production records the engine quantity as:

```text
astronomical_basis = modern_proxy_for_surya_siddhanta_12_bhaga_rule
modern_proxy_for_surya_siddhanta_12_bhaga_rule = true
modern_longitudinal_separation_at_local_sunset_degrees = ...
surya_siddhanta_longitudinal_separation_degrees = null
claims_full_surya_siddhanta_chapter_10_recomputation = false
```

Local sunset is the application evaluation epoch for first-crescent visibility:

```text
application_evaluation_epoch = local_sunset
evaluation_epoch_is_explicitly_commanded_by_surya_siddhanta_10_1 = false
```

Moonset lag and illuminated fraction are diagnostic metadata only. They are not
used as rejection thresholds.

### Nirnayamrita, Smriti-vacana, and Variant Attribution

The tithi visibility indication is a contextual nibandha rule, not a universal
monthly command.

Nirnayamrita prose decision:

```text
प्रतिपद्यपराह्णिकत्रिमुहूर्तव्यापिन्यां द्वितीयायां
चन्द्रदर्शनं सम्भाव्यते ।
```

Transmitted metrical rule, commonly connected with Anvadhana/Darsha ritual:

```text
द्वितीया त्रिमुहूर्ता चेत् प्रतिपद्यापराह्णिकी ।
अन्वाधानं चतुर्दश्यां परतः सोमदर्शनात् ॥
```

Some later witnesses transmit a variant with `अग्न्याधानं` instead of
`अन्वाधानं`, and attribution varies between Vriddha-Satatapa and Baudhayana in
later nibandha literature. Production therefore labels this layer as:

```text
tithi_corroboration_basis = nibandha_tithi_visibility_indication
tithi_indication_original_context = darsa_anvadhana_and_govardhana_adjudication
tithi_indication_monthly_use = application_level_analogy
```

The indication is one-way:

```text
Dvitiya covers the full Aparahna interval -> traditional possibility of Moon sighting is indicated
```

It does not imply:

```text
Dvitiya does not cover Aparahna -> Moon sighting is impossible
```

### Viramitrodaya Parallel Witness

Viramitrodaya is relevant as a later parallel interpretive witness because it
uses substantially the same verse in a ritual timing discussion. Its online
transmission may contain OCR/textual damage, so the normalized form is documented
as a witness, not as an independently settled original:

```text
अपराह्णसन्धौ तावत्परेद्युश्चन्द्रदर्शनाभावे सन्धिदिनेऽन्वाधानं प्रातर्यागः ।

द्वितीया त्रिमुहूर्ता चेत् प्रतिपद्यापराह्णिकी ।
अन्वाधानं चतुर्दश्यां परतः सोमदर्शनात् ॥
```

The attribution apparatus is therefore:

```text
Nirnayamrita witness      -> Vriddha-Satatapa attribution
Viramitrodaya witness     -> Baudhayana attribution
Production source status  -> later nibandha/smriti-vacana transmission
```

This witness supports the possibility-of-Moon-sighting inference, but still does
not create a universal monthly Chandra Darshana date command.

### Dharma Sindhu Time Divisions

The fivefold daylight division used for Aparahna is:

```text
तत्र दिनं पञ्चधा विभज्य प्रथमभागः प्रातःकालो ज्ञेयः,
द्वितीयः सङ्गवः, तृतीयो मध्याह्नः, चतुर्थो भागोऽपराह्णः,
पञ्चमः सायाह्नः ।
```

The Pradosha definition used for overlap metadata is:

```text
सूर्यास्तोत्तरं त्रिमुहूर्तं प्रदोषः ।
```

Production uses a dynamic night-muhurta convention for this overlap:

```text
pradosha_muhurta_basis = dynamic_ratrimana_muhurta
```

Pradosha overlap is never a rejection rule for monthly Chandra Darshana.

### Shabdakalpadruma / Hari-bhakti-vilasa Transmission

The lexicographical / Vaishnava transmission preserves the same inference. This
is useful as a documentary witness, but it is not counted as three independent
ancient authorities:

```text
तदुदयसम्भावनञ्च निर्णयामृते निर्णीतम् ।
प्रतिपद्यापराह्णिकत्रिमुहूर्त्तव्यापिन्यां द्वितीयायां चन्द्रदर्शनं सम्भाव्यते ।

तदुक्तमग्न्याधानविषये वृद्धशातातपेन ।

द्वितीया त्रिमुहूर्त्ता चेत् प्रतिपद्यापराह्णिकी ।
अग्न्याधानं चतुर्द्दश्यां परतः सोमदर्शनात् ॥

अपराह्णश्च पञ्चधाविभक्तस्याह्नश्चतुर्थो भागः ।

ततश्च यत्र प्रतिपदि षण्मुहूर्त्तव्यापिनी द्वितीया तत्र चन्द्रदर्शनसम्भावनम् ।
```

Production treats `अग्न्याधानं` here as a variant against the stronger
`अन्वाधानं` ritual context.

### Skanda Purana Govardhana Witness

Skanda Purana gives direct authority for the Govardhana / Gokrida moon-visibility
concern:

```text
बलिपूजां विधायैवं पश्चाद्गोक्रीडनं चरेत् ।
गवां क्रीडादिने यत्र रात्रौ दृश्येत चन्द्रमाः ।
सोमो राजा पशून्हन्ति सुरभीपूजकांस्तथा ॥
```

This belongs to Govardhana / Gokrida. It is not imported into the generic
monthly Chandra Darshana selector. Govardhana / Annakut keeps its own separate
Sthula Chandra Darshana 9-muhurta truth-table logic.

### Optional Surya Siddhanta Astronomy Apparatus

These verses are relevant only if a future implementation reproduces a fuller
Siddhantic astronomy layer. The current production resolver does not use them as
religious date-selection rules.

Lunar latitude / viksepa:

```text
स्वपातोनाद्ग्रहाज्जीवा शीघ्राद्भृगुजसौम्ययोः ।
विक्षेपघ्न्यन्त्यकर्णाप्ता विक्षेपस्त्रिज्यया विधोः ॥ २.५७ ॥
```

Drkkarma passage, with a documented textual concern around 7.9:

```text
लब्धं प्राच्यां ऋणं सौम्याद्विक्षेपात् पश्चिमे धनम् ।
दक्षिणे प्राक्कपाले स्वं पश्चिमे तु विपर्ययः ॥ ७.९ ॥

नक्षत्रग्रहयोगेषु ग्रहास्तोदयसाधने ।
शृङ्गोन्नतौ तु चन्द्रस्य दृक्कर्मादाविदं स्मृतम् ॥ ७.११ ॥
```

Some digital witnesses read a damaged form near the end of 7.9. Documentation
must not quote that line as critically settled without an apparatus.

## Authenticated Corpus Summary

| Status | Source | Production role |
|---|---|---|
| Include | Surya Siddhanta 10.1-10.5 | Traditional crescent visibility / moon-setting witness; implemented only as a modern 12-degree proxy |
| Include as prose | Nirnayamrita | Dvitiya full-Aparahna Moon-sighting possibility |
| Include with apparatus | Smriti-vacana | `द्वितीया त्रिमुहूर्ता... अन्वाधानं...` ritual inference |
| Mention disputed attribution | Nirnayamrita / Viramitrodaya | Vriddha-Satatapa versus Baudhayana witness labels |
| Include as definition | Dharma Sindhu | Fivefold daytime division and Pradosha interval |
| Include | Skanda Purana 2.4.10.59-61 | Direct Govardhana / Gokrida moon-visibility authority |
| Secondary witness | Hari-bhakti-vilasa / Shabdakalpadruma / Vachaspatyam | Transmission of the prose and smriti-vacana material |
| Optional appendix | Surya Siddhanta 2.57 and 7.9-7.11 | Astronomy apparatus only; not observance selector |

## Catalog Rules

| Festival | Production flags |
|---|---|
| `Chandra Darshana` | `chandra_darshana_visibility: true`, `karmakala_type: chandra_darshana_visibility`, `chandra_darshana_visibility_model: source_sensitive_monthly_chandra_darshana_first_crescent`, `chandra_darshana_visibility_basis: application_definition_first_visible_crescent` |
| `Adhika Chandra Darshana` | Same resolver, plus `adhika_only: true` |

There is no monthly `chandra_darshana_sthula_muhurta_threshold` catalog field.

## Season Reconstruction

The resolver needs historical snapshots. If either the transit engine or the
historical snapshot callback is unavailable, monthly Chandra Darshana returns no
candidate instead of falling back to the retired rule.

For each civil date, production searches back up to eight post-Amavasya evenings
and reconstructs the current lunation from:

- an Amavasya snapshot with `tithi_index_abs == 30` and `tithi_end_jd`, or
- a Pratipada snapshot with `tithi_index_abs == 1` and `tithi_start_jd`.

The latest Amavasya end before the current sunset is used as the season anchor.

## Evening Evaluation

Each candidate evening after Amavasya is evaluated chronologically.

## Production Decision Tree

This tree is a lossless map of the production resolver. It is written in plain
terms, but each branch corresponds to code in
`FestivalRuleChandraDarshana::resolveChandraDarshanaFestival()`,
`chandraDarshanaSeasonForDate()`, `selectOperationalChandraDarshanaCandidate()`,
and `evaluateChandraDarshanaEvening()`.

```mermaid
flowchart TD
    A["Request Chandra Darshana for this civil date"] --> B{"Transit engine and historical snapshot callback available?"}
    B -- "No" --> Z0["Return no festival for this date"]
    B -- "Yes" --> C["Search back up to 8 days for the latest Amavasya end before today's sunset"]

    C --> C1{"Find Amavasya-at-sunrise day with tithi_end_jd?"}
    C1 -- "Yes" --> C2["Record that Amavasya end as a possible season anchor"]
    C1 -- "No" --> C3["Also check Pratipada-at-sunrise days"]
    C2 --> C3
    C3 --> C4{"Find Pratipada-at-sunrise day with tithi_start_jd?"}
    C4 -- "Yes" --> C5["Record tithi_start_jd as Amavasya end; if before sunrise, anchor previous civil date"]
    C4 -- "No" --> C6["No additional anchor from this day"]
    C5 --> C7["Choose the latest recorded Amavasya end"]
    C6 --> C7
    C7 --> C8{"Any season anchor found?"}
    C8 -- "No" --> Z0
    C8 -- "Yes" --> D["From the season anchor, evaluate evenings in chronological order, up to 8 evenings"]

    D --> E{"Is evening after Amavasya end?"}
    E -- "No" --> DNext["Try next evening"]
    E -- "Yes" --> F{"Sunrise, sunset, next sunrise valid?"}
    F -- "No" --> DNext
    F -- "Yes" --> G["Build independent evidence record"]

    G --> H{"Horizon proxy passes? moonrise < sunset < moonset"}
    H -- "Yes" --> H1["horizon.status = POST_SUNSET_HORIZON_WINDOW; window = sunset to moonset"]
    H -- "No" --> H2["horizon.status = NO_POST_SUNSET_HORIZON_WINDOW"]

    G --> I{"Modern 12-bhaga proxy passes? normalized engine Sun-Moon separation at local sunset >= 12 degrees"}
    I -- "Yes" --> I1["surya_siddhanta_visibility.status = TWELVE_BHAGA_PROXY_PASSED"]
    I -- "No" --> I2["surya_siddhanta_visibility.status = TWELVE_BHAGA_PROXY_NOT_PASSED"]

    G --> J{"Pratipada active at sunrise?"}
    J -- "No" --> J1["nibandha_tithi_indication.applicable = false"]
    J -- "Yes" --> K["Compute dynamic daylight muhurtas: Aparahna = sunrise + 9/15 day to sunrise + 12/15 day"]
    K --> L{"Dvitiya starts by Aparahna start and ends after Aparahna end?"}
    L -- "Yes" --> L1["nibandha_tithi_indication.status = FULL_APARAHNA_INDICATION_PRESENT"]
    L -- "No" --> L2["nibandha_tithi_indication.status = FULL_APARAHNA_INDICATION_NOT_ESTABLISHED"]
    K --> M{"Dvitiya starts by Aparahna start and ends after sunset?"}
    M -- "Yes" --> M1["stronger_six_muhurta_indication.status = SIX_MUHURTA_INTERVAL_COVERED"]
    M -- "No" --> M2["stronger_six_muhurta_indication.status = SIX_MUHURTA_INTERVAL_NOT_COVERED"]

    G --> N{"Moon horizon window overlaps dynamic Pradosha?"}
    N -- "Yes" --> N1["pradosha.status = PRADOSHA_OVERLAP_PRESENT; metadata only"]
    N -- "No" --> N2["pradosha.status = NO_PRADOSHA_OVERLAP; not a rejection"]

    H1 --> O{"Horizon passed AND 12-bhaga proxy passed?"}
    H2 --> O
    I1 --> O
    I2 --> O
    O -- "Yes" --> P["operational_candidate = true; monthly_observance.application_status = APPLICATION_FIRST_CRESCENT_CANDIDATE"]
    O -- "No" --> Q["operational_candidate = false; keep evidence record but do not select this evening"]

    P --> R{"Was this selected evening the requested civil date?"}
    R -- "Yes" --> S["Return Chandra Darshana / Adhika Chandra Darshana result"]
    R -- "No" --> Z0
    Q --> DNext
    DNext --> D

    S --> T["Always attach strict_source_only_result = MONTHLY_DATE_TEXTUALLY_UNDETERMINED"]
    T --> U["actual_observation = UNKNOWN; no 38-min, 7-degree, 9-degree, or 0.8% threshold applied"]
```

In simple words: the database date is the first evening after Amavasya where the
Moon remains above the horizon after sunset and the engine's modern 12-degree
proxy passes. The Dvitiya Aparahna rule and Pradosha overlap travel with the
result as evidence, but neither can replace the first passing evening.

### Horizon Window

The current production horizon gate is a rise/set proxy:

```text
moonrise < sunset < moonset
```

The window is:

```text
sunset -> moonset
```

This is recorded as `method = rise_set_window_proxy`. It is not yet an apparent
upper-limb altitude and next-set search. The field proves a post-sunset horizon
window, not actual naked-eye observation.

### 12-Bhaga Proxy

Production computes:

```text
modern_longitudinal_separation_at_local_sunset_degrees
```

from `moon_sun_elongation_at_sunset_degrees`, normalized to the smallest arc.

```text
separation < 12.0  -> TWELVE_BHAGA_PROXY_NOT_PASSED
separation >= 12.0 -> TWELVE_BHAGA_PROXY_PASSED
```

This is intentionally named as a modern proxy. It must not be presented as a
full Surya Siddhanta chapter 10 recomputation.

### Dvitiya Aparahna Indication

The tithi indication is checked only when Pratipada is active at sunrise.

Production divides local daylight dynamically:

```text
daylight = sunset - sunrise
day_muhurta = daylight / 15
aparahna_start = sunrise + 9 * day_muhurta
aparahna_end   = sunrise + 12 * day_muhurta
```

The literal three-Aparahna-muhurta condition is:

```text
dvitiya_start <= aparahna_start
AND
dvitiya_end   >= aparahna_end
```

The stronger six-muhurta explanatory condition is stored independently:

```text
dvitiya_start <= aparahna_start
AND
dvitiya_end   >= sunset
```

Production computes the actual Dvitiya end with the transit engine. It does not
infer the full indication from only `Pratipada <= 9 muhurtas`; that shortcut is
only the first half of the interval test and would be lossy in Dvitiya-kshaya
cases.

## Orthogonal Evidence Fields

Each evening stores independent evidence blocks. These facts can overlap and
must not be treated as a single mutually exclusive truth source:

```json
{
  "horizon": {
    "status": "POST_SUNSET_HORIZON_WINDOW",
    "method": "rise_set_window_proxy"
  },
  "surya_siddhanta_visibility": {
    "status": "TWELVE_BHAGA_PROXY_PASSED",
    "method": "modern_longitudinal_separation_proxy",
    "claims_exact_siddhantic_recomputation": false
  },
  "nibandha_tithi_indication": {
    "status": "FULL_APARAHNA_INDICATION_NOT_ESTABLISHED",
    "original_context": "darsa_anvadhana_and_govardhana_adjudication",
    "monthly_use": "application_level_analogy"
  },
  "stronger_six_muhurta_indication": {
    "status": "SIX_MUHURTA_INTERVAL_NOT_COVERED"
  },
  "pradosha": {
    "status": "PRADOSHA_OVERLAP_PRESENT",
    "used_as_rejection_rule": false
  },
  "monthly_observance": {
    "strict_source_only_status": "MONTHLY_DATE_TEXTUALLY_UNDETERMINED",
    "application_status": "APPLICATION_FIRST_CRESCENT_CANDIDATE"
  }
}
```

The top-level `classification` / `summary_classification` remains only a summary
label for display and testing:

| Summary classification | Meaning |
|---|---|
| `APPLICATION_CRESCENT_CANDIDATE_WITH_NIBANDHA_INDICATION` | Horizon window exists, the 12-degree proxy passes, and Dvitiya covers the full three Aparahna muhurtas |
| `APPLICATION_CRESCENT_CANDIDATE` | Horizon window exists and the 12-degree proxy passes; tithi indication is false, unavailable, or inapplicable |
| `NIBANDHA_TITHI_INDICATION_ASTRONOMICAL_PROXY_DIVERGENCE` | Dvitiya covers the full Aparahna interval, but the horizon/proxy branch does not pass |
| `NO_POST_SUNSET_HORIZON_WINDOW` | `moonrise < sunset < moonset` is false |
| `TWELVE_BHAGA_PROXY_NOT_PASSED` | Horizon window exists, but the 12-degree proxy does not pass |

The operational selector accepts the first chronological evening where:

```text
horizon.status = POST_SUNSET_HORIZON_WINDOW
AND
surya_siddhanta_visibility.status = TWELVE_BHAGA_PROXY_PASSED
```

The Dvitiya indication is attached as metadata. It never displaces an earlier
astronomical-proxy candidate.

## Output Fields

The decision payload includes:

```json
{
  "visibility_assessment": {
    "model": "source_sensitive_monthly_chandra_darshana_first_crescent",
    "operational_candidate": true,
    "geometrically_supported_by_engine_proxy": true,
    "actual_observation": "UNKNOWN",
    "classification": "APPLICATION_CRESCENT_CANDIDATE",
    "summary_classification": "APPLICATION_CRESCENT_CANDIDATE",
    "strict_source_only_result": "MONTHLY_DATE_TEXTUALLY_UNDETERMINED",
    "date_selection_basis": "application_definition_first_visible_crescent",
    "astronomical_basis": "modern_proxy_for_surya_siddhanta_12_bhaga_rule",
    "astronomical_computation_basis": "engine_longitudinal_separation_at_application_epoch_checked_against_12_degree_proxy_threshold",
    "application_evaluation_epoch": "local_sunset",
    "modern_proxy_for_surya_siddhanta_12_bhaga_rule": true,
    "claims_full_surya_siddhanta_chapter_10_recomputation": false,
    "tithi_corroboration_basis": "nibandha_tithi_visibility_indication",
    "tithi_indication_original_context": "darsa_anvadhana_and_govardhana_adjudication",
    "tithi_indication_monthly_use": "application_level_analogy",
    "pradosha_basis": "satsangijivan_childhood_samskara_analogy_only",
    "pradosha_muhurta_basis": "dynamic_ratrimana_muhurta",
    "modern_longitudinal_separation_at_local_sunset_degrees": 12.0,
    "surya_siddhanta_longitudinal_separation_degrees": null,
    "proxy_threshold_degrees": 12.0,
    "moonset_lag_seconds": 0.0,
    "moonset_lag_minutes": 0.0,
    "illumination_percent": 0.0,
    "horizon": {},
    "surya_siddhanta_visibility": {},
    "nibandha_tithi_indication": {},
    "stronger_six_muhurta_indication": {},
    "pradosha": {},
    "monthly_observance": {},
    "forbidden_modern_thresholds_applied": false,
    "reason": "chandra_darshana_application_crescent_candidate",
    "basis": "application_definition_first_visible_crescent"
  }
}
```

## Forbidden Monthly Branches

The production monthly resolver must not contain:

- `Pratipada < 9 muhurtas -> Sud 1` / `Pratipada >= 9 muhurtas -> Sud 2`,
- kshaya-Pratipada Amavasya-day fallback,
- separate Adhika-month date-selection branch,
- one-ghati-after-sunset rule,
- 38-minute lag threshold,
- 7-degree or 9-degree elongation threshold,
- 0.8% illumination threshold,
- Dvitiya-at-sunset selector,
- requirement that the selected tithi overlap the full Moon-viewing window,
- Govardhana Moon-warning logic.

Those rules either belong to other festival truth tables or are modern diagnostic
ideas that are not part of this monthly Chandra Darshana resolver.
