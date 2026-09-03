# Amāvāsyā / Pūrṇimā Naming Taxonomy

Structured naming basis for special New-Moon and Full-Moon observances in
`panchang-core`, parallel to the **Pradosh weekday classifier** pattern
(`weekday_classifier_after_resolution`).

Machine-readable companion: [`amavasya_purnima_naming_taxonomy.json`](./amavasya_purnima_naming_taxonomy.json).

## Naming layers

```
Tithi (Amāvāsyā / Pūrṇimā)
        │
   ┌────┼──────────────┬──────────────────┐
   │    │              │                  │
  Vāra Māsa        Festival/Event     Nakṣatra /
 (weekday) (month)   (scripture)      combination
```

| Layer | Key | Applies to | Engine behavior |
|---|---|---|---|
| **Vāra** | `vaar` | Amāvāsyā only (formal) | Emit-time weekday classifier on generic `Amavasya` |
| **Māsa** | `masa` | Both | Month-named catalog keys (`Magha Amavasya`, `Kartika Purnima`, …) |
| **Festival / event** | `festival_event` | Both | Dedicated catalog keys (`Mahalaya Amavasya`, `Guru Purnima`, …) |
| **Nakṣatra / combo** | `nakshatra_combo` | Rare / regional | Catalog flags such as `requires_purnima` + nakṣatra rules |

**Important:** Formal pan-Indian **weekday names are Amāvāsyā-specific**.
Pūrṇimā identity comes from māsa / festival / deity — not from weekday.

---

## 1. Amāvāsyā — vāra (weekday)

Only three weekdays have widely standardized ritual frameworks:

| Identity | Weekday (Carbon) | Planet | Formal status | Primary associations |
|---|---|---:|---|---|
| **Somavati Amavasya** | Monday (`1`) | Moon / Soma | Canonical | Shiva–Parvati, marital longevity, holy baths, pitru tarpan |
| **Bhaumavati Amavasya** | Tuesday (`2`) | Mars / Bhauma | Canonical | Hanuman, Mangal remedies, debt relief (ṛṇa-mukti) |
| **Shani Amavasya** / Shanichari | Saturday (`6`) | Saturn / Shani | Canonical | Shani remedies, Sade Sati / Dhaiyā, discipline, black-item dāna |

Other weekdays (Sun / Wed / Thu / Fri) have **no** genuine pan-Indian Amavasya
name in this package — do not invent Ravi/Budh/Guru/Shukra Amavasya identities.

### Emit rules (`FestivalPayloadPresentation`)

| Catalog key | Mon / Tue / Sat | Other weekdays |
|---|---|---|
| `Amavasya` (generic) | `name_key` → Somavati / Bhaumavati / Shani; aliases include **Amavasya** | stays `Amavasya` with **no** weekday aliases |
| `Darsha Amavasya`, month-named `* Amavasya` (Magha, Vaishakha, …) | identity unchanged; alias **Amavasya** only — **never** Somavati/Bhaumavati/Shani | same |

**Direction (critical):**
- Broad **Amavasya** = invisible-Moon tithi ending the waning phase.
- **Darsha Amavasya** = technical aparahna / evening–night subset for pitru rites; **not always** the same civil identity as broad Amavasya.
- Māsa-named Amavasya (Magha/Mauni/…) **can** alias to Amavasya (named → generic).
- Not every Amavasya is specially named (generic must not always list Somavati/Shani).
- Do **not** put weekday names onto māsa-/Darsha-named rows (e.g. Magha must not alias Somavati).

---

## 2. Amāvāsyā — māsa / festival (catalog keys already present)

| Identity | Basis | Notes |
|---|---|---|
| Chaitra Amavasya | māsa | New-year / purification period in many regions |
| Vaishakha Amavasya | māsa | Hosts **Shani Jayanti**, **Vat Savitri Vrat** (Amanta) |
| Jyeshtha Amavasya | māsa | Month-tagged |
| Ashadha Amavasya | māsa | Chaturmas preparation / pitru rites |
| Shravana Amavasya | māsa + season | Aliases: **Hariyali Amavasya**, Aadi Amavasai (regional) |
| Bhadrapada Amavasya | māsa + ritual | Aliases: Pithori / Kushagrahani |
| Mahalaya Amavasya | festival_event | Sarva Pitru; closes Pitru Pakṣa |
| Ashwina Amavasya | māsa + festival | Deepavali / Lakṣmī Pūjā civil-day path |
| Kartika Amavasya | māsa | Month-tagged |
| Margashirsha Amavasya | māsa | Month-tagged |
| Pausha / Thai Amavasai | māsa + regional | Tamil Thai ancestor rites |
| Magha Amavasya | māsa + vow | Alias **Mauni Amavasya** |
| Phalguna Amavasya | māsa | Year-end / Holi-adjacent cycle |
| Darsha Amavasya | ritual purpose | Monthly pitru / aparahna table |
| Adhika Darsha Amavasya | adhika + ritual | Intercalary Darsha |

---

## 3. Pūrṇimā — māsa / festival (no vāra elevation)

Pūrṇimā never receives a weekday `name_key` classifier.

| Identity | Basis | Highlights |
|---|---|---|
| Chaitra Purnima | māsa / deity | Hanuman Jayanti (regional) |
| Vaishakha Purnima | festival_event | Buddha Purnima / Chitra Pournami |
| Jyeshtha Purnima | festival_event | Vat Purnima |
| Ashadha Purnima | festival_event | **Guru Purnima** |
| Shravana Purnima | festival_event | Raksha Bandhan / Narali Purnima |
| Bhadrapada Purnima | māsa | Pitru Pakṣa start (some traditions) |
| Ashwin / Sharad Purnima | season + festival | Kojagari / Kaumudi |
| Kartika Purnima | festival_event | Dev Deepavali / Tripuri |
| Margashirsha Purnima | deity | Dattatreya Jayanti |
| Pausha Purnima | deity / māsa | Shākambharī / Magha snān begins |
| Magha Purnima | māsa | Snān / dāna |
| Phalguna Purnima | festival_event | Holikā Dahan / Holi |
| Vratni Purnima | ritual rule | Generic monthly Purnima vrat (18-ghadi Chaturdashi) |

---

## 4. Ranking notes (traditional emphasis, not engine priority)

**Amāvāsyā:** Mahalaya → Somavati → Shani → Mauni → Kartik/Deepavali Amavasya  
**Pūrṇimā:** Guru → Kartik → Magha → Sharad → Vaishakha (Buddha)

Engine priority remains catalog resolution / karmakala tables; this taxonomy
only classifies **public naming**.

---

## 5. Implementation map

| Concern | Location |
|---|---|
| Weekday classifier | `FestivalPayloadPresentation::buildFestivalPayload()` |
| Catalog flags | `Amavasya`, `Darsha Amavasya` → `weekday_classifier_after_resolution` |
| Catalog totals | `FestivalCatalog::getCatalogFestivalCount()` expands Amavasya weekday identities |
| Pradosh analogue | `Pradosh Vrat` seven-weekday expansion (unchanged) |
