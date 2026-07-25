Yes. Your current:

```
elongation >= 10°
AND
moonset lag >= 39 minutes
```

is a **heuristic fit**. It is basically a tuned classifier. It has no direct astronomical law behind the numbers.

The exact values are **not fixed constants** because lunar visibility is not a 2-variable problem. The real threshold is a function:

[
Visibility = f(\text{Sun altitude}, \text{Moon altitude}, \text{elongation}, \text{illumination}, \text{lunar latitude}, \text{azimuth separation}, \text{observer latitude}, \text{atmosphere}, \text{optical extinction})
]

So there is no universal:

```
10.000° = visible
39.000 min = visible
```

constant.

---

## 1. Exact replacement for elongation ≥ 10°

The scientific parameter is not simple elongation.

It is usually:

### ARCL (Arc of Light)

Angular separation between Sun and Moon:

[
ARCL = \arccos(
\sin\delta_s\sin\delta_m+
\cos\delta_s\cos\delta_m\cos(\alpha_m-\alpha_s)
)
]

where:

* (\alpha) = right ascension
* (\delta) = declination

This is the real sky separation.

Your current:

```
moon_sun_elongation_at_sunset_degrees
```

is already close, but it is not the full visibility parameter.

---

A classical modern criterion:

Danjon limit:

[
ARCL \approx 7^\circ
]

Below this:

```
impossible
```

because the illuminated crescent is physically too thin.

But practical naked-eye visibility:

```
ARCL ≈ 9°–12°
```

depends on conditions.

So your 10° is not wrong, but it is a midpoint.

---

## 2. Exact replacement for lag ≥39 minutes

The 39 minutes is also a heuristic.

The actual parameter is:

### ARCV (Arc of Vision)

Vertical separation between Moon and Sun after sunset:

[
ARCV = h_{moon} - h_{sun}
]

where:

* (h_{moon}) = Moon altitude
* (h_{sun}) = Sun altitude

At the best observation time:

[
h_{sun} \approx -4^\circ \text{ to } -6^\circ
]

The Moon altitude determines visibility.

---

The classic Yallop model uses:

[
q =
\frac{ARCV - (11.8371 - 6.3226W + 0.7319W^2 -0.1018W^3)}
{10}
]

where:

[
W = \frac{ARCL - 11.8371}{10}
]

This gives visibility class.

It does not use "minutes after sunset".

---

## 3. Converting your 39 minutes into astronomy

Your lag:

```
moonset - sunset
```

is only a time proxy.

The conversion is:

[
lag =
\frac{ARCV}{15^\circ/hour}
]

approximately:

Earth rotates:

[
15^\circ = 60 minutes
]

therefore:

[
1^\circ = 4 minutes
]

So:

39 minutes corresponds approximately:

[
\frac{39}{4}=9.75^\circ
]

Meaning:

```
lag 39 minutes ≈ 9.75° vertical separation
```

But only near the equator and favorable declination.

At Bhuj:

latitude:

[
23.25^\circ N
]

the conversion changes.

---

## 4. What your 10° + 39 min is actually approximating

Your rule:

```
elong >=10°
lag >=39min
```

is approximately:

```
ARCL >= 10°
AND
ARCV >= ~10°
```

which is very close to the practical crescent visibility boundary.

So your fitted numbers are not random. They accidentally approximate:

```
Yallop / Danjon style visibility geometry
```

---

## 5. Exact formula you should implement instead

Replace:

```php
elongation >= 10
lag_minutes >= 39
```

with:

### Step 1

Compute:

```
ARCL
```

(true Sun-Moon angular separation)

Formula:

[
ARCL =
\arccos(
\sin\delta_s\sin\delta_m+
\cos\delta_s\cos\delta_m\cos(\Delta RA)
)
]

---

### Step 2

Find best observation time.

Not sunset.

Search:

```
sun altitude = -4° to -6°
```

typically:

```
sun altitude = -4°
```

after sunset.

---

### Step 3

Compute:

```
ARCV
```

[
ARCV = h_moon-h_sun
]

---

### Step 4

Compute Yallop q:

[
W=\frac{ARCL-11.8371}{10}
]

[
q=
\frac{
ARCV-(11.8371-6.3226W+0.7319W^2-0.1018W^3)
}{10}
]

Decision:

| q                | visibility                    |
| ---------------- | ----------------------------- |
| >0.216           | easily visible                |
| -0.014 to 0.216  | visible under good conditions |
| -0.160 to -0.014 | optical aid                   |
| < -0.160         | impossible                    |

---

## 6. For your engine

Your current data already has:

* Moon longitude
* Sun longitude
* Moon latitude
* Moonrise/moonset
* illumination

Missing:

```
topocentric Moon altitude
topocentric Sun altitude
ARCV
ARCL at best time
DAZ
```

Once these are available, remove:

```
10°
39 min
```

completely.

---

## Final answer

The "exact" replacements are:

| Current heuristic | Exact astronomical quantity |
| ----------------- | --------------------------- |
| elongation ≥10°   | ARCL calculation            |
| lag ≥39 min       | ARCV calculation            |
| combined rule     | Yallop q visibility model   |

So:

```
10° and 39 minutes
```

are not constants from astronomy.

They are a simplified approximation of:

```
ARCL ≈ 10°
ARCV ≈ 10°
```

A proper engine should calculate ARCL + ARCV + q dynamically for every month and location.