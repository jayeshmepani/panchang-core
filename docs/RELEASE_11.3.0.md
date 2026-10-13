# Panchang Core 11.3.0 Release Notes

**Release Date:** October 2026  
**Focus:** Full-Day Panchaka Rahita Timeline & Classical Panchaka Classification

---

## Overview

Panchang Core version **11.3.0** introduces a comprehensive, full-day **Panchaka Rahita** timeline for finding auspicious and inauspicious times throughout the day, fully harmonized with classical Vedic astrology guidelines from the revered text *Muhurta Chintamani*.

---

## Key Highlights

### 1. Full-Day Panchaka Rahita Timeline
- **Continuous Daily Timeline:** Instead of calculating Panchaka only for a single fixed moment, the system now provides a continuous timeline spanning from local sunrise to the next day's sunrise.
- **Natural Boundary Detection:** The timeline automatically divides the day whenever a new **Tithi** begins, the Moon moves into a new **Nakshatra**, or a new **Lagna (Ascendant)** rises on the eastern horizon.
- **Clear Guidance for Every Period:** Each segment of the day clearly indicates whether the period is favorable for auspicious beginnings or whether caution is advised.

### 2. Classical Nine-Fold Panchaka Classification
In Vedic astrology, Panchaka Rahita is calculated by summing the indices of the Day, Tithi, Nakshatra, and Lagna, and dividing by 9:

$$\text{Remainder} = (\text{Weekday} + \text{Tithi} + \text{Nakshatra} + \text{Lagna}) \pmod 9$$

Version 11.3.0 standardizes these classifications according to the classical *Muhurta Chintamani* canons:

- **Remainder 0 — Nish-Panchaka (Auspicious / Defect-free):**  
  Excellent for all auspicious undertakings, new ventures, ceremonies, and travel.
- **Remainder 1 — Mrityu Panchaka (Inauspicious):**  
  Represents heightened risk; avoidable for crucial beginnings and major celebrations.
- **Remainder 2 — Agni Panchaka (Inauspicious):**  
  Associated with the fire element; avoidable for housewarming, construction, or vehicle purchases.
- **Remainders 3, 5, and 7 — Shubha Panchaka (Auspicious):**  
  Favorable and peaceful periods suitable for everyday good deeds, celebrations, and rituals.
- **Remainder 4 — Raja Panchaka (Inauspicious):**  
  Unfavorable for matters relating to authorities, legal agreements, and public affairs.
- **Remainder 6 — Chora Panchaka (Inauspicious):**  
  Associated with risk of loss or deception; avoidable for financial investments and commerce.
- **Remainder 8 — Roga Panchaka (Inauspicious):**  
  Associated with fatigue, illness, and obstacles; best reserved for quiet, routine tasks.

### 3. Sunrise Lagna Precision & Boundary Smoothing
- **Accurate Day-Start Calculations:** The rising sign at the exact moment of solar sunrise is reliably screened to ensure the start of the day is always accurate.
- **Seamless Interval Transitions:** Minute fluctuations at exact astronomical boundaries are smoothly unified to provide a clean, dependable schedule for daily electional guidance.

---

## Summary
Version 11.3.0 brings classical certainty and continuous full-day visibility to Panchaka determinations, helping users choose the best times for important events and daily life.
