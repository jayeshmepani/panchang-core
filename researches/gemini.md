Your heuristic numbers ($10^\circ$ elongation and $39\text{ minutes}$ moonset lag) are not random—they are the direct physical result of **Earth's rotation speed** ($1^\circ = 4\text{ minutes}$).

In ancient Siddhāntic astronomy and modern spherical astronomy, there are **exact baked constants** and **rigorous formulas** that define this exact relationship.

---

## 1. Why $10^\circ$ and $39\text{ minutes}$ match (The Physical Connection)

The Earth rotates $360^\circ$ in 24 hours, which means:

$$1^\circ \text{ of celestial rotation} = 4 \text{ minutes of time}$$

When the Moon is at an altitude difference (**Arc of Vision** or $ARCV$) of $\approx 9.5^\circ \text{ to } 10^\circ$ above the horizon at sunset, the time it takes to set is:

$$\text{Lag Time} \approx \frac{ARCV}{\text{Apparent Speed}} \approx 4 \text{ min/degree} \times 9.5^\circ \approx 38.0 - 39.5 \text{ minutes}$$

---

## 2. The Exact Baked Constants in Texts

In classical Indian texts, visibility is defined by baked constants called **Kālāṁśa** (degrees of ascensional time).

* **Sūrya Siddhānta (10.1):** Baked constant $= \mathbf{12^\circ}$ of time $= \mathbf{720\text{ asu}} = \mathbf{48\text{ minutes}}$.
* **Siddhānta Śiro maṇi (Bhāskara II):** Uses **$10^\circ \text{ to } 12^\circ$** depending on lunar latitude and atmospheric clarity.
* **Modern Indian Drik / Astronomical Standards:** **$10.5^\circ$ Arc of Light** ($ARCL$) or **$9.0^\circ - 10.0^\circ$ Arc of Vision** ($ARCV$).

---

## 3. The Exact Siddhāntic Calculation Pipeline

The *Sūrya Siddhānta* does not leave visibility to longitude alone. It computes the **exact Moonset Lag ($\Delta T$)** using a 3-step geometric pipeline:

### Step 1: Celestial Latitude Correction (*Dṛkkarma*)

First, correct the Moon's ecliptic longitude ($\lambda_M$) for its latitude ($\beta$) and observer's latitude ($\phi$):

$$\Delta \lambda_{\text{ayan}} = -\tan(\epsilon) \cdot \sin(\lambda_M) \cdot \beta \quad \text{(Obliquity Correction)}$$

$$\Delta \lambda_{\text{akṣa}} = \tan(\phi) \cdot \sin(\text{Rāśi}) \cdot \beta \quad \text{(Observer Latitude Correction)}$$

$$\lambda_M' = \lambda_M + \Delta \lambda_{\text{ayan}} + \Delta \lambda_{\text{akṣa}}$$

### Step 2: Oblique Ascension Difference (*Lagnāntarāsavaḥ*)

Convert the corrected longitudes of Sun ($\lambda_S$) and Moon ($\lambda_M'$) into oblique ascension ($\alpha_{\text{oblique}}$) for local latitude $\phi$:

$$\Delta \alpha = \alpha_{\text{oblique}}(\lambda_M') - \alpha_{\text{oblique}}(\lambda_S) \quad \text{(measured in } \textit{asu} \text{)}$$

### Step 3: Exact Visibility Test

Compare $\Delta \alpha$ against the baked constant ($720\text{ asu} = 48\text{ min}$):

$$\text{Crescent Visible} \iff \Delta \alpha \ge 720 \text{ asu } (48\text{ min})$$

---

## 4. Modern Spherical Astronomy Exact Formulas

In modern positional astronomy (e.g., Yallop / Maunder criteria used by HM Nautical Almanac Office), the exact threshold is computed using two primary variables:

### A. True Great-Circle Elongation ($ARCL$ - Arc of Light)

The exact 3D angular separation between the centers of the Sun and Moon:

$$\cos(ARCL) = \cos(\lambda_M - \lambda_S) \cdot \cos(\beta)$$

> **Baked Physical Limit (Danjon Limit):** If $ARCL < 7.0^\circ$, crescent visibility is physically impossible because lunar mountain shadows block all reflected sunlight toward Earth.

### B. Altitude Difference at Sunset ($ARCV$ - Arc of Vision)

The true vertical angular separation between the Moon and Sun at sunset:

$$ARCV = h_M - h_S$$

where $h_S = -0.833^\circ$ (standard solar disk dip at sunset) and $h_M$ is calculated via spherical trigonometry:

$$\sin(h_M) = \sin(\phi)\sin(\delta_M) + \cos(\phi)\cos(\delta_M)\cos(H_M)$$

### C. Exact Lag Time Formula ($\Delta T_{\text{lag}}$)

The exact moonset lag time in minutes from sunset to moonset:

$$\Delta T_{\text{lag}} = \frac{H_{\text{moonset}} - H_{\text{sunset}}}{15^\circ/\text{hour}} \times 60$$

Where $H$ is the local hour angle of each body when crossing the horizon ($h = -0.833^\circ$ for Sun, $h = -0.566^\circ + \text{parallax}$ for Moon).

---

## Summary Comparison

| Parameter | Your Heuristic | Sūrya Siddhānta Exact | Modern Exact (Drik / Yallop) |
| --- | --- | --- | --- |
| **Primary Threshold Constant** | $10^\circ$ Elongation | $12^\circ$ Time Arc ($720\text{ asu}$) | $10.5^\circ\text{ } ARCL$ / $9.0^\circ\text{ } ARCV$ |
| **Minimum Lag Time** | $39\text{ min}$ | $48\text{ min}$ ($12 \times 4\text{ min}$) | $38 - 42\text{ min}$ (depends on latitude) |
| **Correction Method** | None (Static) | *Dṛkkarma* ($\beta$ & $\phi$ adjusted) | Topocentric refraction + Parallax |