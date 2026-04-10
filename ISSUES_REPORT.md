# Panchang Core — Issues Report

Generated: April 10, 2026
Scope: Festival resolution, month detection, and output quality issues

---

## Executive Summary

| Category                         | Issues Found         | Severity    |
| -------------------------------- | -------------------- | ----------- |
| **Adhika/Kshaya Maas Detection** | 2 critical bugs      | 🔴 Critical |
| **Solar Sankranti Detection**    | 1 critical bug       | 🔴 Critical |
| **Festival Resolution Logic**    | 2 high-severity bugs | 🔴 High     |
| **Output Structure**             | 0 issues             | ✅ OK       |

**Root cause of all missing festivals:** The Hindu month detection algorithm incorrectly identifies multiple months as Adhika (leap months) in 2026, when only Adhika Jyeshtha (May 17 – June 15, 2026) should be the leap month. This cascades into every month-based festival failing to resolve.

---

## Issue 1: Hindu Month Detection — False Adhika Maas Identification

**Severity:** 🔴 Critical  
**Component:** Hindu month calculation

### Problem

The algorithm that determines the Hindu Amanta (lunar) month incorrectly flags **multiple months as Adhika (leap months)** in 2026. It compares the sun's sidereal position at the previous and next Amavasya (new moon) to decide whether a month is Adhika, but this approach misses actual sign crossings that happen **between** those two endpoints.

### Correct Behavior

In 2026, there should be **exactly ONE** Adhika month:

- **Adhika Jyeshtha:** May 17 – June 15, 2026 (30 days)

### What Actually Happens

The algorithm falsely identifies **three additional months** as Adhika:

| Date Range      | Algorithm Says         | Should Be                |
| --------------- | ---------------------- | ------------------------ |
| Mar 19 – Apr 13 | Phalguna **(Adhika)**  | Chaitra (normal)         |
| Apr 14 – May 16 | Chaitra **(Adhika)**   | Vaishakha (normal)       |
| May 17 – Jun 15 | Vaishakha **(Adhika)** | **Jyeshtha (Adhika)** ✅ |
| Jun 16+         | Jyeshtha (normal)      | Ashadha (normal)         |

### Impact

Every festival that requires a specific Hindu month name to match fails during the falsely-flagged Adhika months. In 2026, **16 festivals are completely missing** from the output.

---

## Issue 2: Purnimanta Month During Adhika — Incorrect Suffix

**Severity:** 🔴 Critical  
**Component:** Hindu month calculation

### Problem

When the algorithm detects an Adhika month, it appends "(Adhika)" to both the Amanta and Purnimanta month names. However, the Purnimanta month should follow its own paksha-based calculation (Amanta month during Shukla Paksha, Amanta+1 during Krishna Paksha). The "(Adhika)" suffix should only appear on the month that is actually the leap month in the Purnimanta system, not blanket-applied to both.

### Impact

Festivals that use `month_purnimanta` matching may fail to resolve during Adhika months because the month name doesn't match what the festival rule expects.

---

## Issue 3: Solar Sankranti Festival Detection

**Severity:** 🔴 Critical  
**Component:** Sankranti detection in festival snapshot

### Problem

The system detects Solar Sankranti (sun entering a new zodiac sign) by comparing the sun's position at the start and end of a civil day (midnight to midnight). This only catches Sankranti events that happen to cross a sign boundary within that specific midnight-to-midnight window.

Since the sun moves approximately 1° per day and stays in each sign for ~30 days, the sign-crossing moment can happen at any time during a day. The midnight-to-midnight check misses most Sankranti events because the sun is already in the new sign at the start of the day (having crossed the boundary the previous day or earlier).

### Evidence — 2026 Sankranti Detection

| Sankranti Festival                     | Detected in Output? | Correct Date 2026 |
| -------------------------------------- | ------------------- | ----------------- |
| Mesha Sankranti (Baisakhi/Puthandu)    | ✅ Detected         | Apr 14            |
| Kumbha Sankranti                       | ✅ Detected         | Feb 13            |
| Meena Sankranti                        | ✅ Detected         | Mar 15            |
| **Mithuna Sankranti**                  | ❌ **MISSING**      | Jun 15            |
| **Karka Sankranti**                    | ❌ **MISSING**      | Jul 16            |
| **Simha Sankranti**                    | ❌ **MISSING**      | Aug 17            |
| **Kanya Sankranti (Vishwakarma Puja)** | ❌ **MISSING**      | Sep 17            |
| **Tula Sankranti**                     | ❌ **MISSING**      | Oct 18            |
| **Vrischika Sankranti**                | ❌ **MISSING**      | Nov 16            |
| **Dhanu Sankranti**                    | ❌ **MISSING**      | Dec 16            |
| **Makara Sankranti (Pongal)**          | ❌ **MISSING**      | Jan 14            |
| **Vishu**                              | ❌ **MISSING**      | Apr 14            |

**Result:** 10 out of 13 Sankranti festivals are missing from the output.

---

## Issue 4: Festival Filtering During Adhika Maas — Overly Aggressive

**Severity:** 🔴 High  
**Component:** Festival resolution logic

### Problem

The festival resolution logic treats the Adhika month as a complete "festival blackout period." During an Adhika month, ALL standard festivals are skipped unless they have an explicit `allow_adhika` or `adhika_only` flag.

This is too aggressive. During Adhika Jyeshtha 2026, festivals that depend on **Nakshatra + Tithi** (like Guru Purnima) should still resolve because they don't require a specific month name to match. Only festivals with explicit month requirements should be filtered.

### Impact

Festivals that should resolve during Adhika months are being skipped even when their core conditions (tithi, nakshatra, paksha) are met.

---

## Issue 5: Holi Festival — Multi-Parent Resolution

**Severity:** 🔴 High  
**Component:** Festival resolution (day-after dependency)

### Problem

Holi is defined as a "day after" festival that depends on Holika Dahan. In 2026, Holika Dahan resolves on **two consecutive dates** (March 2 and March 3) due to vriddhi/kshaya tithi spanning. The system should create Holi entries for **both** March 3 and March 4.

Currently, only one Holi entry appears in the output.

### Impact

Holi is missing from the output entirely when the parent festival (Holika Dahan) has a multi-day resolution.

---

## The 16 Missing Festivals in 2026 Output

These festivals are **defined in the code** (182 total) but **do not appear** in the 2026 festivals output. All 16 are missing due to the Adhika Maas detection bug (Issue #1) and the Sankranti detection bug (Issue #3).

| #   | Festival (English Name)                               | Festival (Hindi Name in Output) | Type                         | Why Missing                                                     |
| --- | ----------------------------------------------------- | ------------------------------- | ---------------------------- | --------------------------------------------------------------- |
| 1   | **Mithuna Sankranti**                                 | मिथुन संक्रांति                 | Solar Sankranti              | Sankranti detection misses the sun entering Gemini sign         |
| 2   | **Cheti Chand**                                       | चेती चन्द                       | Tithi + Month (Chaitra)      | Adhika Maas bug — falls during falsely-flagged Adhika Phalguna  |
| 3   | **Chaitra (Vasant) Navaratri Day 1**                  | चैत्र (वसंत) नवरात्रि दिन 1     | Tithi + Month (Chaitra)      | Adhika Maas bug — Shukla Pratipada falls during Adhika Phalguna |
| 4   | **Chaitra (Vasant) Navaratri Day 2**                  | चैत्र (वसंत) नवरात्रि दिन 2     | Tithi + Month (Chaitra)      | Adhika Maas bug — Shukla Dwitiya falls during Adhika Phalguna   |
| 5   | **Ashadha Gupt Navaratri Day 4 (Bhuvaneshwari Puja)** | आषाढ़ गुप्त नवरात्रि दिन 4      | Tithi + Month (Ashadha)      | Month mismatch due to Adhika cascade                            |
| 6   | **Ugadi / Gudi Padwa**                                | उगादी / गुड़ी पाडवा             | Tithi + Month (Chaitra)      | Adhika Maas bug — requires Chaitra Shukla Pratipada             |
| 7   | **Yogini Ekadashi**                                   | योगिनी एकादशी                   | Tithi + Month (Jyeshtha)     | Month mismatch due to Adhika cascade                            |
| 8   | **Goga Pancham / Goga Panchami**                      | गोगा पंचम                       | Tithi + Month (Shravana)     | Month mismatch due to Adhika cascade                            |
| 9   | **Shravana Somvar (Monday Fasting)**                  | श्रावण सोमवार                   | Weekday + Month (Shravana)   | Month mismatch due to Adhika cascade                            |
| 10  | **Devutthana (Prabodhini) Ekadashi**                  | देवउत्थान एकादशी                | Tithi + Month (Kartika)      | Month mismatch due to Adhika cascade                            |
| 11  | **Dattatreya Jayanti**                                | दत्तात्रेय जयंती                | Tithi + Month (Margashirsha) | Month mismatch due to Adhika cascade                            |
| 12  | **Saphala Ekadashi**                                  | सफला एकादशी                     | Tithi + Month (Margashirsha) | Month mismatch due to Adhika cascade                            |
| 13  | **Pausha Putrada Ekadashi**                           | पौष पुत्रदा एकादशी              | Tithi + Month (Pausha)       | Month mismatch due to Adhika cascade                            |
| 14  | **Thai Poosam**                                       | थाई पूसम                        | Nakshatra + Month (Magha)    | Month mismatch due to Adhika cascade                            |
| 15  | **Onam (Thiruvonam)**                                 | ओणम (तिरुवोणम)                  | Nakshatra + Month (Shravana) | Month mismatch due to Adhika cascade                            |
| 16  | **Holi**                                              | होली                            | Day-after (Holika Dahan + 1) | Parent festival multi-day resolution bug                        |

---

## Festival Output Summary (2026)

| Metric                                                 | Value   |
| ------------------------------------------------------ | ------- |
| Festivals defined in code                              | **182** |
| Festivals with translations                            | **187** |
| Unique festivals appearing in output                   | **166** |
| Festivals missing from output                          | **16**  |
| Total festival entries (including multi-day)           | **174** |
| Festival days (unique dates with at least 1 festival)  | **138** |
| Festivals appearing on multiple dates (vriddhi/kshaya) | **22**  |

---

## Fix Priority

| Priority | Issue                                  | Effort |
| -------- | -------------------------------------- | ------ |
| **P0**   | #1 — Hindu month Adhika Maas detection | Medium |
| **P0**   | #3 — Solar Sankranti detection         | Medium |
| **P1**   | #2 — Purnimanta during Adhika          | Low    |
| **P1**   | #4 — Festival filtering during Adhika  | Low    |
| **P1**   | #5 — Holi multi-parent resolution      | Low    |

---

## Additional Observations

### Month Cache Performance

The month calculation cache is limited to 3 entries. For year-long festival scanning (365 daily calls), this causes frequent cache misses and unnecessary recalculation. Consider increasing to 30+ entries.

### Kshaya Maas (Deleted Month) Handling

The code detects Kshaya Maas (a month that is skipped entirely because the sun crosses two signs between consecutive Amavasyas) but has no special festival handling for it. During a Kshaya month, all festivals for that month would be silently skipped.

### Eclipse Service

The Eclipse Service works correctly. Eclipses 2026-2032 output is accurate with no issues found.

### Output File Structure

The three output scripts (`panchang_today.php`, `panchang_festivals.php`, `panchang_eclipses.php`) produce consistent and well-structured JSON. The `meta` block includes a `type` field for easy identification. No issues found.

# Hindu Lunar Calendar 2026 — Verified

**Vikram Samvat 2082–2083 · Parabhava Samvatsara · 13-month year (Adhik Jyeshtha)**

> All dates IST. Minor ±1 day variation possible by city due to sunrise-based tithi calculation.
> Sources: Prokerala, Drik Panchang, Rudhvi.in, SmartPuja, AmitRay.com

---

## Calendar Table

| Month                                            | Purnimanta — North India _(starts after Purnima)_ | Amanta — South & West India _(starts after Amavasya)_ | Purnima Date (IST)              |
| ------------------------------------------------ | ------------------------------------------------- | ----------------------------------------------------- | ------------------------------- |
| **Pausha** _(carry-over from 2025)_              | 5 Dec 2025 – 3 Jan 2026                           | 20 Dec 2025 – 18 Jan 2026                             | 3 Jan 2026                      |
| **Magha**                                        | 4 Jan – 1 Feb                                     | 19 Jan – 17 Feb                                       | 1 Feb                           |
| **Phalguna**                                     | 2 Feb – 3 Mar                                     | 18 Feb – 18 Mar                                       | 3 Mar _(Holi)_                  |
| **Chaitra**                                      | 4 Mar – 1 Apr                                     | 19 Mar – 17 Apr                                       | 2 Apr                           |
| **Vaishakha**                                    | 2 Apr – 1 May                                     | 18 Apr – 16 May                                       | 1 May _(Buddha Purnima)_        |
| **Adhik Jyeshtha** 🔶 _(Purushottam / Mal Maas)_ | **2 May – 30 May**                                | **17 May – 15 Jun**                                   | 31 May _(Adhik Purnima)_        |
| **Nija Jyeshtha** 🟢 _(Regular / true Jyeshtha)_ | **31 May – 29 Jun**                               | **16 Jun – 14 Jul**                                   | 29 Jun _(Vat Purnima)_          |
| **Ashadha**                                      | 30 Jun – 29 Jul                                   | 15 Jul – 12 Aug                                       | 29 Jul _(Guru Purnima)_         |
| **Shravana**                                     | 30 Jul – 28 Aug                                   | 13 Aug – 10 Sep                                       | 27–28 Aug _(Raksha Bandhan)_    |
| **Bhadrapada**                                   | 29 Aug – 26 Sep                                   | 11 Sep – 10 Oct                                       | 26 Sep                          |
| **Ashwin**                                       | 27 Sep – 26 Oct                                   | 11 Oct – 8 Nov                                        | 25–26 Oct _(Sharad Purnima)_    |
| **Kartika**                                      | 27 Oct – 24 Nov                                   | 9 Nov – 8 Dec                                         | 24 Nov _(Kartik Purnima)_       |
| **Margashirsha**                                 | 25 Nov – 23 Dec                                   | 9 Dec – 7 Jan 2027                                    | 23 Dec _(Margashirsha Purnima)_ |
| **Pausha** _(into 2027)_                         | 24 Dec – Jan 2027                                 | 8 Jan 2027 onwards                                    | Jan 2027                        |

---

## Key Notes

### System differences

- **Purnimanta** (North India — UP, Bihar, Rajasthan, MP, Punjab etc.): month begins the day after Purnima (full moon). Krishna Paksha comes first, then Shukla Paksha.
- **Amanta** (South & West India — Gujarat, Maharashtra, Karnataka, TN, AP, Kerala etc.): month begins the day after Amavasya (new moon). Shukla Paksha comes first, then Krishna Paksha.

### Why 13 months in 2026?

The lunar year (~354 days) is ~11 days shorter than the solar year (~365 days). An intercalary month (**Adhik Maas**) is added roughly every 32.5 months to realign them. In 2026, this extra month falls in **Jyeshtha**, resulting in
two Jyeshtha months — Adhik (extra) followed by Nija (regular).

### Adhik Jyeshtha — the critical split

|                       | Purnimanta | Amanta     |
| --------------------- | ---------- | ---------- |
| Adhik Jyeshtha starts | **2 May**  | **17 May** |
| Adhik Jyeshtha ends   | **30 May** | **15 Jun** |
| Nija Jyeshtha starts  | **31 May** | **16 Jun** |
| Nija Jyeshtha ends    | **29 Jun** | **14 Jul** |

The Adhik Purnima falls on **31 May 2026**. The Nija Jyeshtha Purnima (Vat Purnima / Snana Yatra) falls on **29 Jun 2026**.

### All 13 Purnimas of 2026

1. 3 Jan — Pausha Purnima
2. 1 Feb — Magha Purnima
3. 3 Mar — Phalguna Purnima (Holi)
4. 2 Apr — Chaitra Purnima
5. 1 May — Vaishakha Purnima (Buddha Purnima)
6. **31 May — Adhik Jyeshtha Purnima** _(Adhik Maas)_
7. 29 Jun — Nija Jyeshtha Purnima (Vat Purnima)
8. 29 Jul — Ashadha Purnima (Guru Purnima)
9. 27–28 Aug — Shravana Purnima (Raksha Bandhan)
10. 26 Sep — Bhadrapada Purnima
11. 25–26 Oct — Ashwin Purnima (Sharad Purnima)
12. 24 Nov — Kartika Purnima
13. 23 Dec — Margashirsha Purnima
