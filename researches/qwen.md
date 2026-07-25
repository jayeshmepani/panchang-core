# The Exact Formulas Behind "10° and 39 min"

## The Hard Truth First

**There is no single pair of universal constants.** The values 10° and 39 min are not baked into the sky. They are **location-dependent, coordinate-dependent, and atmosphere-dependent** empirical thresholds. What *is* exact is the **geometric machinery** that produces elongation and lag, and what *is* published is the **empirical visibility boundary** fitted to centuries of observations.

Your 10° + 39 min works for 2023–29 because you've accidentally landed on a good **local approximation** of that boundary. Below is the full pipeline that replaces the guess.

---

## Part 1 — The Exact Geometric Calculations

### 1A. Topocentric Elongation (not geocentric)

The Moon's geocentric position can differ from its topocentric position by **up to ~1°** because of lunar parallax (~57′). This alone can shift your "10°" to "11°" or "9°".

```
Step 1: Geocentric positions
    Sun:  α☉, δ☉   (right ascension, declination)
    Moon: α☽, δ☽

Step 2: Topocentric correction for Moon
    π☽ = arcsin(R⊕ / Δ)          # horizontal parallax (~0.95°)
    Δα = −π☽ · cos φ · sin H☽ / cos δ☽
    Δδ = −π☽ · (sin φ · cos δ☽ − cos φ · sin δ☽ · cos H☽)

    α☽_topo = α☽ + Δα
    δ☽_topo = δ☽ + Δδ

    (Sun's parallax is 8.8″ → negligible)

Step 3: Great-circle elongation
    cos E = sin δ☉ sin δ☽_topo
          + cos δ☉ cos δ☽_topo cos(α☉ − α☽_topo)

    E = arccos(cos E)
```

> **This is the "exact elongation."** It is a great-circle arc, not an ecliptic longitude difference. For the Moon near the ecliptic the two differ by at most ~0.5°, but it matters at the threshold.

### 1B. True Lag (moonset − sunset)

The lag is not a simple conversion of elongation into time. It depends on the **angle the ecliptic makes with the horizon** at your latitude and the time of year.

```
Sunset:
    h₀☉ = −0.8333°          # −34′ refraction − 16′ semi-diameter
    cos H☉ = (sin h₀☉ − sin φ sin δ☉) / (cos φ cos δ☉)
    t_set☉ = 12h − H☉/15° + EoT + λ/15°

Moonset:
    h₀☽ = −0.8333° + π☽     # add lunar parallax (~+0.95°)
           ≈ +0.117°         # Moon's center is ABOVE geometric horizon
                             # when its upper limb touches the horizon
    cos H☽ = (sin h₀☽ − sin φ sin δ☽_topo) / (cos φ cos δ☽_topo)
    t_set☽ = 12h − H☽/15° + EoT_moon + λ/15°

lag = t_set☽ − t_set☉       # in minutes
```

> **This is the "exact lag."** Note that the Moon's `h₀` is *positive* (+7′) because parallax (57′) exceeds refraction + semi-diameter (50′). Getting this wrong shifts your lag by ~4 minutes.

### 1C. Arc of Vision (ARCV)

This is the parameter most modern criteria actually use, not raw elongation:

```
At the instant of sunset, compute the Moon's altitude:

    sin h☽ = sin φ sin δ☽_topo + cos φ cos δ☽_topo cos H☽_at_sunset

    ARCV = h☽ − h☉_at_sunset
         = h☽ − (−0.8333°)
         = h☽ + 0.8333°
```

ARCV is what connects elongation and lag into a single observable quantity. **For a given elongation, ARCV varies with latitude, season, and ecliptic tilt.** That is why no fixed (E, lag) pair is universal.

---

## Part 2 — The Published Visibility Criteria (the "baked constants")

### 2A. Yallop's Criterion (1997) — the modern standard

Used by HM Nautical Almanac Office, adopted by most modern crescent-visibility software.

**Crescent width** (in arc-seconds):

```
w = 0.27245 × (1 − cos E) × 3600 / (1 − 0.9983 × sin E)
```

For E = 10°: w ≈ 14.1″
For E = 12°: w ≈ 20.3″

**Yallop's q parameter:**

```
q = (ARCV − (11.8371 − 6.3226 w + 0.7319 w² − 0.1018 w³)) / 10
```

**Visibility categories:**

| q range | Visibility |
|---|---|
| q > +0.216 | Easily visible to naked eye |
| +0.216 > q > −0.014 | Visible under perfect conditions |
| −0.014 > q > −0.160 | Optical aid may be needed |
| −0.160 > q > −0.232 | Not visible to naked eye |
| −0.232 > q > −0.293 | Visible only with telescope |
| q < −0.293 | Not visible at all |

> **The "naked-eye limit" is q ≈ −0.160.** This is the closest thing to a "baked constant." It is not a fixed elongation or a fixed lag — it is a **curve** in the (E, lag) plane that shifts with latitude and season.

### 2B. Fotheringham–Maunder Criterion (Babylonian)

The classical boundary, fitted to Babylonian observations (~32.5°N):

```
Minimum elongation:  E_min ≈ 12°
Minimum lag:         lag_min ≈ 48 min   (at Babylon's latitude)
```

The boundary between these two limits is approximately parabolic:

```
lag ≈ 48 + k × (E − 12)²     for E > 12°
```

At lower latitudes (closer to the equator), the ecliptic is steeper to the horizon, so the same elongation produces a **longer lag**. At higher latitudes, the ecliptic is shallower, so the same elongation gives a **shorter lag**. This is why your 39 min differs from Babylon's 48 min.

### 2C. Sūrya Siddhānta's 12 bhāga

As established in the earlier discussion:

```
SS threshold:  Δλ_ecliptic ≥ 12°
```

This is an **ecliptic longitude** threshold, not a great-circle elongation. For the Moon at max latitude (β ≈ 5°):

```
E_great_circle ≈ √((12° × cos 5°)² + 5°²) ≈ 12.5°
```

So SS's 12° longitude ≈ 12–12.5° true elongation. Your 10° is **below** the SS threshold, which means you are likely using geocentric coordinates (which underestimate elongation by ~1°) or your location/atmosphere allows slightly earlier detection.

---

## Part 3 — Why Your 10° and 39 min Work (and Where They'll Break)

### Why they work for 2023–29

| Factor | Effect |
|---|---|
| Geocentric elongation (no parallax correction) | Underestimates E by ~0.5°–1° → your "10°" ≈ 10.5°–11° topocentric |
| Your observer latitude | Determines the ecliptic–horizon angle, which sets the E↔lag mapping |
| 7-year window | May not include extreme ecliptic-tilt cases (e.g., high-latitude winter crescents) |
| Clean atmospheric assumptions | Implicit extinction coefficient baked into the threshold |

### Where they will break

- **High-latitude observers** (φ > 40°): the ecliptic is shallow, so a 10° elongation may give only 25 min of lag → your 39 min cutoff will falsely reject visible crescents.
- **Equatorial observers** (φ ≈ 0°): the ecliptic is steep, so 10° elongation gives ~50+ min of lag → your 39 min cutoff is too loose.
- **Borderline months**: when the crescent is near the q = −0.160 boundary, a 0.5° error in elongation flips the prediction.

---

## Part 4 — The Replacement Pipeline

Replace your two heuristics with this:

```python
def crescent_visible(date, lat, lon):
    # 1. Topocentric positions (use a proper ephemeris: VSOP87 + ELP2000)
    sun  = topocentric_sun(date, lat, lon)
    moon = topocentric_moon(date, lat, lon)   # includes parallax

    # 2. Great-circle elongation
    E = angular_separation(sun, moon)          # in degrees

    # 3. Sunset and moonset (with refraction, SD, parallax)
    t_sunset = sunset_time(date, lat, lon, body='sun')
    t_moonset = sunset_time(date, lat, lon, body='moon')
    lag_min = (t_moonset - t_sunset) * 1440    # in minutes

    # 4. ARCV at sunset
    h_moon_at_sunset = altitude(moon, t_sunset, lat, lon)
    ARCV = h_moon_at_sunset + 0.8333           # degrees

    # 5. Crescent width
    w = 0.27245 * (1 - cos(radians(E))) * 3600 \
        / (1 - 0.9983 * sin(radians(E)))       # arc-seconds

    # 6. Yallop q
    q = (ARCV - (11.8371 - 6.3226*w + 0.7319*w**2 - 0.1018*w**3)) / 10

    # 7. Decision
    if q > -0.160:
        return True, q, E, lag_min
    else:
        return False, q, E, lag_min
```

### The "exact constants" baked into this pipeline

| Constant | Value | Source |
|---|---|---|
| Solar refraction at horizon | 34′ | Standard atmosphere |
| Solar semi-diameter | 16′ | Mean value |
| Lunar horizontal parallax | 54′–61′ (mean 57′) | Computed from Δ |
| Lunar semi-diameter | 14.7′–16.8′ (mean 16′) | Computed from Δ |
| Yallop polynomial coefficients | 11.8371, 6.3226, 0.7319, 0.1018 | Yallop 1997, fitted to 295 observations |
| Naked-eye q limit | −0.160 | Yallop 1997 |
| SS ecliptic longitude threshold | 12° | Sūrya Siddhānta 10.1 |
| Fotheringham–Maunder E_min | ~12° | Babylonian records |
| Fotheringham–Maunder lag_min | ~48 min at 32.5°N | Babylonian records |

---

## Part 5 — Reverse-Engineering Your 10° and 39 min

If you want to know what your (10°, 39 min) corresponds to in Yallop's system at your location:

```
Given:  E = 10°,  lag = 39 min,  φ = your latitude

1. Compute ARCV from E, lag, φ, and the ecliptic angle
2. Compute w from E
3. Compute q

Typical result at φ ≈ 20°–25°N:
    ARCV ≈ 8°–9°
    w ≈ 14″
    q ≈ −0.10 to −0.05

→ This sits in Yallop's "visible under perfect conditions" band
  (−0.014 > q > −0.160), which is exactly the borderline
  first-crescent region.
```

So your heuristic is **implicitly approximating q ≈ −0.1** at your latitude. It works because the 2023–29 first-crescent cases you tested happen to cluster near that q value. But it is a **two-parameter slice through a three-parameter surface** (E, ARCV, w), and it will drift when the ecliptic geometry changes.

---

## Bottom Line

| What you have | What it actually is | What to replace it with |
|---|---|---|
| `E ≥ 10°` | Geocentric ecliptic-longitude proxy, ~1° below SS's 12° | Topocentric great-circle elongation |
| `lag ≥ 39 min` | Location-dependent time proxy for ARCV | True moonset − sunset with parallax + refraction |
| Both together | Implicit approximation to Yallop q ≈ −0.1 at your latitude | Compute q explicitly from ARCV and crescent width |

The **only baked constant** that is location-independent is **Yallop's q = −0.160** (naked-eye limit). Everything else — the 10°, the 39 min, the SS's 12°, Babylon's 48 min — is a **local projection** of that q-boundary onto the (E, lag) plane at a particular latitude and ecliptic tilt.