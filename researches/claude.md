Short version: **there is no hidden "exact" 9.8° or 10.2° waiting to be found** — every published visibility criterion, including the ones professional groups use, is itself a curve fit to a few hundred observation records, not a derived physical constant. Your 10°/39min pair is doing the same job as those fits, just cruder. Here's the actual landscape, tiered:

**Why elongation & lag aren't independent in the first place**

Lag (moonset − sunset) and elongation are both proxies for one underlying geometric quantity: how far below the horizon the Sun is, and how high the Moon sits, at the moment you'd actually look. That's exactly the computation your Surya Siddhanta passage was doing with lagnāntara-asu — converting an ecliptic-longitude gap into a horizon-based time-lag via oblique ascension. So a flat "elong ≥10 AND lag ≥39" is really a rough 2D stand-in for a single continuous surface, and that's precisely why it works okay across 7 years but has no principled cutoff — you're fitting a box around a curve.

**The parameter the literature actually uses: ARCV + W**

The professionally-used replacement (Yallop, HMNAO, 1997) drops "elongation" and "lag" as primary variables entirely (Ilyas 1983 and later Ahmad et al. 2020 explicitly argue lag time is only suitable for explaining visibility to laymen and isn't a proper visibility-criterion variable) and instead uses:

- **ARCV** — arc of vision: altitude of Moon's center minus altitude of Sun's center (i.e. depression of the Sun), evaluated at a defined "best time" Tb = Ts + (4/9)·Lag — note lag still appears, but only to *locate the best moment*, not as the visibility variable itself.
- **W** — topocentric crescent width in arcminutes, a function of the Moon's topocentric semi-diameter and elongation.

Yallop fit a cubic to 295 historical sightings giving ARCV = 11.8371 − 6.3226W + 0.7319W² − 0.1018W³, then defined q = (ARCV − that polynomial) / 10 as the test statistic, with bands:

- q > +0.216 → easily visible naked-eye
- −0.014 < q ≤ +0.216 → visible under perfect conditions
- −0.160 < q ≤ −0.014 → optical aid helps find it, then naked-eye possible
- −0.232 < q ≤ −0.160 → needs optical aid
- −0.293 < q ≤ −0.232 → not visible even with telescope
- q ≤ −0.293 → beyond the **Danjon limit** (~7°) — this is the one genuinely physics-grounded floor in the whole field, tied to lunar-limb photometry, not a fit

**This is not "the" answer either — it's just a better-fit version of your problem**

Odeh (2004, 737 records) refit the same polynomial with a different offset; Qureshi (2010/2012) refit again with entirely different coefficients; Hoffman (2003) revised Yallop's own bands. These disagree with each other by amounts comparable to your 10° vs. 9.8° vs. 10.2° uncertainty. So if you're hoping there's a citable "true" constant hiding under your heuristic — there isn't; the entire field is contested curve-fitting, and every fit is anchored to a specific dataset (mostly northern-latitude, mostly telescope-and-eye combined observations, not naked-eye Chandra Darshan sightings from Indian latitudes specifically).

**What I'd actually recommend for panchang-core**

1. Since you already have ephemeris access via Swiss-Ephemeris-PHP, compute ARCV and W directly at best-time rather than proxying through elongation+lag — that's the one upgrade that's unambiguously more rigorous than your current heuristic, tiered T0/T1 rather than pure heuristic.
2. Report Yallop's *bands* (A–F) rather than a binary yes/no — it's honest about the fact that visibility near the margin is genuinely graded, not a hard cutoff.
3. Keep your elong≥10°/lag≥39min heuristic as a fast pre-filter (cheap to compute, and your empirical match against 2023–29 suggests it's a reasonable coarse proxy), but gate the final Chandra Darshan classification on the ARCV/W q-test, and log both so you can audit divergences.
4. Tag the Danjon ~7° figure as your hard T0 floor (nothing is visible below it, this part *is* physically grounded) and treat everything between ~7–12° as the genuinely contested zone where different criteria disagree — which is honest, since that's also where your real-world sighting reports will most disagree.

Want me to sketch the actual W/ARCV computation (semi-diameter, topocentric correction, best-time formula) as PHP so you can slot it into panchang-core alongside your Chandra Darshan T0–T4 framework?