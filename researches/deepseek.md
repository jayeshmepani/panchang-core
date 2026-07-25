Based on the research document you shared and standard astronomical literature, your heuristic of **elongation ≥ 10° AND lag ≥ 39 minutes** is a practical simplification. Here's the breakdown of what the "exact" numbers actually are and why there is no single universal value.

---

## 1. The "Exact" Elongation (ARCL) – It's Not a Single Number

The minimum elongation for naked-eye crescent visibility has been debated for decades. Different astronomers have proposed different limits based on observations:

| Astronomer / Criterion | Minimum Elongation (ARCL) | Notes |
|------------------------|---------------------------|-------|
| **Danjon Limit** | **7°** (theoretical) | Classic limit; crescent cannot be seen below this |
| **Fatoohi** | **7.5°** | Revised Danjon limit based on 52 observations |
| **Schaefer** | **7°** | |
| **Yallop / Ilyas** | **10° – 10.5°** | |
| **Maunder** | **11°** | |
| **Fotheringham** | **12°** | |
| **Odeh** | **6.4°** (recorded observation) | One exceptional observation |
| **McNally** | **5°** | |

Your **10°** heuristic aligns with Yallop and Ilyas — a **conservative, practical threshold** for reliable naked-eye sighting.

### Why no single "exact" value?

Elongation alone doesn't determine visibility. The **actual arc of light (ARCL)** depends on:
- **Relative altitude (ARCV)** – Moon's height above horizon at sunset
- **Relative azimuth (DAZ)** – horizontal separation between Sun and Moon

The exact relationship is:

> **cos(ARCL) = cos(ARCV) × cos(DAZ)**

So ARCL is derived from ARCV and DAZ — not a fixed constant.

### The Danjon Limit in Practice

The theoretical Danjon limit is **7°** apparent elongation. However:
- Allowing **1° for horizontal parallax**, ARCL ≈ **8°**
- The **minimum recorded naked-eye ARCL** in major datasets is **8.82°**
- Even with optical aid, the minimum recorded is **8.10°**

So while **7°** is the theoretical minimum, **~9°** is the practical naked-eye limit, and **10°** is a safe, reliable threshold.

---

## 2. The "Exact" Lag Time – Also Not a Single Number

Your **39 minutes** heuristic is in the right range, but here's what the literature says:

| Observation / Criterion | Lag Time | Notes |
|-------------------------|----------|-------|
| **Shortest recorded with telescope** | **21 min** | |
| **Shortest recorded naked-eye** | **32 min** | Observation 137 |
| **General average** | **40 – 45 min** | Earth takes this long to "catch up" to the Moon |
| **Lag cutoff for easy sightings** | **45 min** | Sightings above this are "too easy" |

### Why no single "exact" value?

Lag time (LAG) is the **interval between sunset and moonset**. It depends on:
- **Sun-Moon elongation**
- **Observer's latitude**
- **Season / declination** of both bodies

Even with the same elongation, lag time varies by location.

### Relationship between Lag and ARCV

There is a **linear relationship** between LAG and ARCV (Moon's altitude at sunset). Longer lag = higher altitude = darker sky = easier sighting.

---

## 3. The "Exact" Formula from Sūrya Siddhānta

Your research document already contains the classical method. From **Sūrya Siddhānta 10.1-10.5**:

> **"After twelve bhāga, Moon becomes visible"**

- **12 bhāga** ≈ **12°** elongation (Fotheringham's limit)
- The text describes an **iterative calculation** of the difference between Sun and Moon positions (lagnāntarāsavah)
- Moon sets **after sunset** in the bright fortnight
- In the dark fortnight, Moon **rises** at a calculated interval

The Sūrya Siddhānta method involves:
1. Finding true Sun and Moon positions
2. Computing the difference (elongation)
3. Determining if the Moon sets after sunset (for waxing phase)

This is a **geometric algorithm**, not a simple threshold.

---

## 4. Modern Algorithmic Approach

For a **programmatic implementation**, here's the standard approach:

### Inputs (per location & date):
- **Sun position** (RA, Dec, altitude at sunset)
- **Moon position** (RA, Dec, altitude at sunset)
- **Observer latitude & longitude**

### Calculations:
1. **ARCL** (Arc of Light / Elongation):
   - Angular separation between Sun and Moon centers
   - Formula: `cos(ARCL) = cos(ARCV) × cos(DAZ)`

2. **ARCV** (Arc of Vision / Relative Altitude):
   - Moon's altitude minus Sun's altitude at sunset

3. **DAZ** (Difference in Azimuth):
   - Horizontal separation between Sun and Moon

4. **LAG** (Lag Time):
   - Moonset time minus Sunset time

### Visibility Criteria (choose one):
| Criterion | Condition |
|-----------|-----------|
| **Yallop (q-criterion)** | q > -0.160 (easy), q > -0.232 (difficult) |
| **Odeh** | ARCL ≥ 6.4° AND ARCV ≥ 4° |
| **Simple** | ARCL ≥ 10° AND LAG ≥ 39 min *(your heuristic)* |

---

## 5. Summary: What to Tell Him

| Your Heuristic | Scientific Basis | "Exact" Equivalent |
|----------------|------------------|-------------------|
| **Elongation ≥ 10°** | Yallop / Ilyas limit | ARCL ≥ 10° (safe naked-eye threshold); theoretical minimum is 7° (Danjon) but practical limit is ~9° |
| **Lag ≥ 39 minutes** | Average observed minimum | LAG depends on latitude, season, and ARCL; 32 min is shortest recorded naked-eye; 40-45 min is typical |

### Recommended Response:

> *"Our heuristic of 10° elongation and 39 minutes lag is a practical simplification based on standard lunar visibility criteria (Yallop/Ilyas limit for elongation, and observed minimum lag times). However, there is no single 'exact' value — elongation (ARCL) is derived from ARCV and DAZ using the formula cos(ARCL) = cos(ARCV) × cos(DAZ), and lag time depends on latitude, season, and the Moon's declination. The theoretical minimum (Danjon limit) is 7°, but the practical naked-eye minimum is ~9°, making 10° a safe threshold. For lag, the shortest recorded naked-eye sighting is 32 minutes, with typical values ranging from 40-45 minutes. The Sūrya Siddhānta uses an iterative geometric method rather than fixed thresholds."*