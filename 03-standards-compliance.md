# Modern Web Standards — Master Reference (2026)

> **Date:** March 2026 | **Scope:** HTML, CSS, JavaScript, Accessibility (WCAG 2.2), SEO, Performance, Media, Structured Data.  
> This document is a comprehensive, self-contained reference.

---

## Table of Contents

1. [Philosophy & Mental Model](#1-philosophy--mental-model)
2. [Foundational Standards & Compliance](#2-foundational-standards--compliance)
   - 2.1 WCAG 2.2 — Principles, Levels, Key Criteria
   - 2.2 Core Web Vitals (CWV)
   - 2.3 EN 301 549 / AS EN 301 549
   - 2.4 The Upcoming WCAG 3.0
3. [Obsolete & Deprecated HTML Elements](#3-obsolete--deprecated-html-elements)
4. [Obsolete & Deprecated HTML Attributes](#4-obsolete--deprecated-html-attributes)
5. [Semantic HTML & Document Landmarks](#5-semantic-html--document-landmarks)
   - 5.1 Sectioning Elements & Implicit ARIA Roles
   - 5.2 Text Content Elements
   - 5.3 Interactive Elements — Button vs. Link
   - 5.4 Lists — `ul`, `ol`, `dl`
   - 5.5 Form Elements Best Practices
   - 5.6 Tables — Accessible Structure
6. [ARIA — Roles, States, Properties, Patterns](#6-aria--roles-states-properties-patterns)
   - 6.1 The Rules of ARIA
   - 6.2 Landmark Roles Table
   - 6.3 Key ARIA States & Properties Reference
   - 6.4 Naming Hierarchy (accessible names)
   - 6.5 Common ARIA Patterns (Code)
7. [Accessibility Deep Dive](#7-accessibility-deep-dive)
   - 7.1 Color Contrast — WCAG 2.x Formula & Thresholds
   - 7.2 APCA — Advanced Perceptual Contrast Algorithm
   - 7.3 Keyboard & Focus Management
   - 7.4 Skip Links & Bypass Blocks
   - 7.5 Motion Preferences (`prefers-reduced-motion`)
   - 7.6 Live Regions & Dynamic announcements
   - 7.7 Target Size (WCAG 2.2 SC 2.5.8)
8. [SEO & `<head>` Complete Reference](#8-seo--head-complete-reference)
   - 8.1 Essential Meta Tags
   - 8.2 Open Graph Protocol
   - 8.3 Twitter / X Cards
   - 8.4 Canonicalization & Robots Directives
   - 8.5 Hreflang for Internationalization
   - 8.6 Favicons & PWA Manifest
9. [JSON-LD Structured Data](#9-json-ld-structured-data)
   - 9.1 Why JSON-LD
   - 9.2 Organization
   - 9.3 Article / NewsArticle
   - 9.4 BreadcrumbList
   - 9.5 FAQPage
   - 9.6 Product with AggregateRating
   - 9.7 LocalBusiness
   - 9.8 WebPage
10. [CSS — Obsolete Patterns & Modern Replacements](#10-css--obsolete-patterns--modern-replacements)
    - 10.1 Layout Evolution (Float → Flexbox → Grid)
    - 10.2 Vendor Prefixes
    - 10.3 CSS-in-JS Runtime vs. Zero-Runtime
    - 10.4 Modern CSS Selectors & Features
    - 10.5 CSS Accessibility Best Practices
    - 10.6 Media Queries — Modern Discrete Features
    - 10.7 CSS Stability Levels (WD → CR → REC)
11. [JavaScript — Deprecated Patterns & Modern Replacements](#11-javascript--deprecated-patterns--modern-replacements)
    - 11.1 Deprecated Global Functions & APIs
    - 11.2 Asynchronous Patterns
    - 11.3 Memory Management — WeakRef & FinalizationRegistry
    - 11.4 The Temporal API
    - 11.5 Keyboard & DOM Events
    - 11.6 Code Splitting & Dynamic Import
    - 11.7 Web Workers & Main Thread Relief
    - 11.8 Scheduler API & INP Optimization
12. [Media Optimization — Images](#12-media-optimization--images)
    - 12.1 Format Hierarchy 2026
    - 12.2 The `<picture>` Element — Format Fallbacks & Art Direction
    - 12.3 `srcset` & `sizes` Explained
    - 12.4 Critical Image Attributes
    - 12.5 GIF Replacement with Video
13. [Video Codecs & Containers](#13-video-codecs--containers)
14. [Resource Hints & Critical Path](#14-resource-hints--critical-path)
    - 14.1 Resource Hints Reference Table
    - 14.2 Script Loading Strategies
    - 14.3 Critical CSS & Font Optimization
    - 14.4 `content-visibility` & `contain-intrinsic-size`
    - 14.5 Priority Hints — `fetchpriority`
15. [Observer APIs](#15-observer-apis)
    - 15.1 IntersectionObserver
    - 15.2 ResizeObserver
    - 15.3 MutationObserver
    - 15.4 PerformanceObserver
16. [Core Web Vitals — Optimization Strategies](#16-core-web-vitals--optimization-strategies)
    - 16.1 LCP — Largest Contentful Paint
    - 16.2 INP — Interaction to Next Paint
    - 16.3 CLS — Cumulative Layout Shift
17. [Network, Delivery & Caching Layer](#17-network-delivery--caching-layer)
18. [Testing, Auditing & CI/CD Integration](#18-testing-auditing--cicd-integration)
19. [Complete Modern HTML Reference Template](#19-complete-modern-html-reference-template)
20. [Master Pre-Launch Checklist](#20-master-pre-launch-checklist)

**Addendum — Additional Topics (March 2026)**

21. [Additional WCAG 2.2 Success Criteria](#21-additional-wcag-22-success-criteria)
    - 21.1 SC 2.5.7 — Dragging Movements (AA)
    - 21.2 SC 2.4.13 — Focus Appearance (AAA)
    - 21.3 SC 3.3.9 — Accessible Authentication Enhanced (AAA)
    - 21.4 SC 4.1.1 Parsing — Removed
22. [The `<dialog>` Element — Native Modal & Non-Modal Dialogs](#22-the-dialog-element--native-modal--non-modal-dialogs)
    - 22.1 Modal Dialog (`.showModal()`)
    - 22.2 Non-Modal Dialog (`.show()`)
    - 22.3 Styling the Dialog
    - 22.4 `<dialog>` vs. Custom Modal
23. [The Popover API — Native Overlays Without JS](#23-the-popover-api--native-overlays-without-js)
    - 23.1 Basic Popover (Declarative)
    - 23.2 Popover Types
    - 23.3 Programmatic Control
    - 23.4 Popover Actions
    - 23.5 Accessibility with Popovers
    - 23.6 `<dialog>` vs. Popover — When to Use Which
24. [`<details>` & `<summary>` — Native Disclosure & Exclusive Accordions](#24-details--summary--native-disclosure-widgets--exclusive-accordions)
    - 24.1 Basic Disclosure Widget
    - 24.2 Exclusive Accordion (`name` Attribute)
    - 24.3 Styling
    - 24.4 FAQ Structured Data Integration
25. [E-E-A-T — Content Quality Signals for SEO](#25-e-e-a-t--content-quality-signals-for-seo)
    - 25.1 The Four Pillars
    - 25.2 YMYL (Your Money or Your Life)
    - 25.3 Technical Implementation
    - 25.4 JSON-LD for E-E-A-T (Author & Organization Schema)
26. [Search Overviews & SGE — Impact on SEO Strategy](#26-search-overviews--sge--impact-on-seo-strategy)
    - 26.1 How Search Overviews Change SEO
    - 26.2 Optimizing for Search Overviews
    - 26.3 Content Freshness
27. [XML Sitemap Best Practices & IndexNow Protocol](#27-xml-sitemap-best-practices--indexnow-protocol)
    - 27.1 XML Sitemap Requirements
    - 27.2 Sitemap Index File
    - 27.3 `robots.txt` Best Practices
    - 27.4 IndexNow Protocol
28. [European Accessibility Act (EAA) — 2025 Enforcement](#28-european-accessibility-act-eaa--2025-enforcement)
    - 28.1 Technical Standard
    - 28.2 Key Differences from ADA
    - 28.3 Practical Compliance Checklist
29. [Emerging Web Platform APIs (2025–2026)](#29-emerging-web-platform-apis-20252026)
    - 29.1 View Transitions API
    - 29.2 CSS Scroll-Driven Animations
    - 29.3 CSS Anchor Positioning
    - 29.4 The `<search>` Element
    - 29.5 The `inert` Attribute
    - 29.6 `autocomplete` — Complete Token Reference
30. [Additional Structured Data Types](#30-additional-structured-data-types)
    - 30.1 HowTo Schema
    - 30.2 Event Schema
    - 30.3 VideoObject Schema
    - 30.4 SiteNavigationElement Schema
31. [Master Pre-Launch Checklist](#31-master-pre-launch-checklist)

---

## 1. Philosophy & Mental Model

Modern front-end engineering is built on **three aligned stacks** that must be kept in sync at all times:

```
Modern Web
├── SEO & Semantics
│   ├── Semantic HTML (section, article, nav, main…)
│   ├── Meta tags & JSON-LD
│   └── Core Web Vitals (ranking signals)
├── Accessibility
│   ├── WCAG 2.2 contrast & color
│   ├── ARIA (only when native HTML is insufficient)
│   └── Keyboard & focus management
└── Performance
    ├── Resource hints (preload / prefetch / preconnect)
    ├── Lazy loading & responsive images
    ├── Modern codecs (WebP / AVIF / AV1 / VP9)
    └── Observer APIs (replace polling/scroll-listeners)
```

**The Separation of Concerns Rule (absolute):**
- **HTML** → structure and semantic meaning only.
- **CSS** → presentation, layout, and visual appearance only.
- **JavaScript** → behavior, interactivity, and progressive enhancement only.

**Guiding Principles:**
1. **HTML First.** Use native elements before reaching for ARIA or JS. Native elements carry built-in roles, keyboard events, and focus management at zero cost.
2. **No ARIA is better than Bad ARIA.** WebAIM studies show pages using ARIA incorrectly average **41% more accessibility errors** than pages without it.
3. **Standards over convention.** Features marked *obsolete*, *deprecated*, or *non-conforming* by WHATWG, W3C, or platform docs must be actively removed — not tolerated for legacy reasons.
4. **Measure real users.** Lab data (Lighthouse) is not a substitute for field data (CrUX / PageSpeed Insights 75th percentile). Core Web Vitals assessments are based on the **75th percentile of field data**.
5. **Progressive enhancement.** Core content must function without JavaScript; JS enhances the experience.
6. **Inclusive by default.** Accessibility, performance, and SEO are interconnected, not separate concerns. A faster site is more accessible; semantic HTML directly helps both screen readers and search crawlers.

---

## 2. Foundational Standards & Compliance

### 2.1 WCAG 2.2 — Principles, Levels, Key Criteria

WCAG (Web Content Accessibility Guidelines) is the internationally recognized legal benchmark for web accessibility, developed by the W3C Web Accessibility Initiative. The current required standard is **WCAG 2.2 (finalized October 2023)**, and it is the legal target under ADA (USA), EAA (Europe), and analogous legislation globally.

**The four core principles — POUR:**

| Principle | Meaning |
|-----------|---------|
| **Perceivable** | Information and UI must be presentable in ways users can perceive — text alternatives, adaptable content, sufficient contrast |
| **Operable** | All functionality must be operable by keyboard; no seizure-inducing content; enough time; bypass mechanisms |
| **Understandable** | Text must be readable and predictable; error recovery must be guided |
| **Robust** | Content must be interpretable by a wide range of user agents and assistive technologies using well-adopted standards |

**Conformance Levels:**

| Level | Description | Practical Requirement |
|-------|-------------|----------------------|
| **A** | Minimum — addresses the most severe barriers | Legal floor |
| **AA** | Addresses common barriers; the **legal target** for most organizations | 4.5:1 text contrast, captions for video, etc. |
| **AAA** | Enhanced — highest level; not achievable for all content types | Aim where feasible |

**Key NEW criteria in WCAG 2.2 (vs. 2.1):**

| SC | Name | What it Requires |
|----|------|-----------------|
| 2.4.11 (AA) | Focus Not Obscured | Focused component must not be entirely hidden by author-created content (e.g., sticky headers, cookie banners) |
| 2.4.12 (AAA) | Focus Not Obscured (Enhanced) | Focused component must be fully visible |
| 2.5.8 (AA) | Target Size (Minimum) | Touch targets must be at least **24×24 CSS pixels** (with offset exceptions) |
| 3.2.6 (A) | Consistent Help | Help mechanisms appear in consistent locations across pages |
| 3.3.7 (A) | Redundant Entry | Info submitted before need not be re-entered in the same session |
| 3.3.8 (AA) | Accessible Authentication | Authentication does not rely on cognitive function tests unless an alternative is offered |

**Practical advice:** Target **WCAG 2.2 AA** as your minimum baseline. Adopt AAA success criteria (clear link purposes, explicit section headings, live captions) wherever feasible.

---

### 2.2 Core Web Vitals (CWV)

Google's Core Web Vitals are quantitative, real-user metrics that directly impact search engine rankings. As of **March 2024**, INP officially replaced FID as the third metric.

| Metric | Full Name | Measures | **Good** | **Needs Improvement** | **Poor** |
|--------|-----------|----------|----------|-----------------------|---------|
| **LCP** | Largest Contentful Paint | Loading performance — time until the largest visible element loads | ≤ 2.5s | 2.5s – 4.0s | > 4.0s |
| **INP** | Interaction to Next Paint | Responsiveness — worst-case interaction latency across the full page lifecycle | ≤ 200ms | 200ms – 500ms | > 500ms |
| **CLS** | Cumulative Layout Shift | Visual stability — sum of unexpected layout shift scores | ≤ 0.1 | 0.1 – 0.25 | > 0.25 |

**Assessment rule:** A page/origin passes CWV only when the **75th percentile** of field data is "Good" for **all three metrics simultaneously**.

**Focus areas per metric:**
- **LCP:** Server response times (TTFB), render-blocking resources, image optimization, no lazy-loading on above-fold hero images.
- **INP:** Long main-thread tasks (>50ms), large JS bundles, unoptimized event handlers, third-party scripts.
- **CLS:** Images/videos without explicit dimensions, dynamically injected banners/ads above existing content, FOUT from web fonts.

---

### 2.3 EN 301 549 / AS EN 301 549

Beyond WCAG, the **EN 301 549** standard (Europe) and **AS EN 301 549** (Australia) mandate functional accessibility for all ICT products — covering web, non-web documents, software, and hardware. These standards use WCAG 2.1/2.2 as their core web accessibility reference. Products must be independently usable by users with visual, auditory, motor, and cognitive impairments.

---

### 2.4 The Upcoming WCAG 3.0

A major Working Draft for WCAG 3.0 was published in September 2025. It is not expected to be finalized until 2028–2030, but its direction matters for planning:

| Change | Details |
|--------|---------|
| **Expanded Scope** | Beyond web content → AR/VR, mobile apps, IoT, digital documents |
| **Scoring Model** | Replaces binary Pass/Fail (A/AA/AAA) with a holistic tiered conformance: **Bronze, Silver, Gold** |
| **APCA Contrast** | Replaces WCAG 2.x mathematical contrast ratio with the Advanced Perceptual Contrast Algorithm (APCA), which accounts for font size, weight, and polarity |
| **Testing** | More emphasis on user testing with people with disabilities, not just automated checks |

> **For now:** Ensure WCAG 2.2 AA compliance. **Also** evaluate designs with APCA tools as a design quality check, since WCAG 2.x math can produce misleading results (e.g., orange-on-white "passes" 4.5:1 but is perceptually difficult to read).

---

## 3. Obsolete & Deprecated HTML Elements

The WHATWG HTML Living Standard categorizes legacy features into two types:
- **Non-conforming / Entirely Obsolete** — must not be used under any circumstances.
- **Obsolete but Conforming** — browsers still render them, but they trigger warnings in conformance checkers and must not be used in new authoring.

The core principle: **HTML describes what content *is*; CSS handles how it *looks*.** Presentational elements violate this principle, confuse assistive technologies, and produce inconsistent rendering.

### Entirely Obsolete Elements (Never Use)

| Element | Legacy Purpose | Modern Replacement | Impact if Used |
|---------|---------------|-------------------|----------------|
| `<font>`, `<basefont>` | Font family, size, color | CSS: `font-family`, `font-size`, `color` | No semantics; breaks AT; unmaintainable |
| `<center>` | Center text/content | CSS: `text-align: center` or `margin: auto` | Purely presentational; confuses structure |
| `<big>` | Larger text | CSS: `font-size: larger` | Presentational only |
| `<strike>` | Strikethrough text | `<del>` for deleted content; CSS `text-decoration: line-through` for style | `<del>` carries semantic meaning; `<strike>` doesn't |
| `<tt>` | Teletype / monospace | `<code>`, `<kbd>`, `<samp>`, or CSS `font-family: monospace` | Presentational; no semantic value |
| `<frame>`, `<frameset>`, `<noframes>` | Frame layouts | `<iframe>` for embeds; CSS Grid/Flexbox for layout | Severe security risks; halts SEO crawling; no mobile support |
| `<marquee>` | Scrolling text | CSS `@keyframes` animation | Accessibility nightmare; performance drain; not usable by screen readers |
| `<blink>` | Blinking text | CSS `animation` (use extremely sparingly) | Accessibility violation; causes vestibular disorder issues |
| `<applet>` | Java applets | `<embed>` or `<object>` | Java applet technology entirely obsolete |
| `<acronym>` | Acronyms | `<abbr title="...">` | `<abbr>` is the universally accepted standard |
| `<bgsound>` | Background audio | `<audio>` element | IE-only proprietary; poor UX |
| `<keygen>` | Key generation for forms | Web Cryptography API | Removed from HTML spec |
| `<dir>` | Directory list | `<ul>` | Replaced by standard list elements |
| `<menu>` (as toolbar) | Context menus / toolbars | `<ul>` with ARIA menu roles | Toolbar usage removed; use proper ARIA patterns |
| `<listing>`, `<xmp>` | Preformatted text | `<pre><code>` | Non-standard parser behaviors |
| `<plaintext>` | Plain text rendering | `<pre>` | Breaks HTML parser entirely |
| `<isindex>` | Single-field search | `<form><input type="search">` | Ancient, deprecated |
| `<nextid>` | NeXT-specific IDs | N/A (server-side IDs) | NeXT-specific, never standardized |
| `<noembed>` | Fallback for embeds | Fallback content inside `<object>` | Non-standard |
| `<rb>`, `<rtc>` | Ruby base/text container | `<ruby><rt>` | Removed from HTML spec |
| `<spacer>` | Layout spacing | CSS `margin` / `padding` | Netscape proprietary |
| `<nobr>` | Prevent line wrapping | CSS `white-space: nowrap` | Purely presentational |

### Obsolete but Conforming (Discouraged — Trigger Validator Warnings)

| Element / Attribute | Issue | Modern Alternative |
|--------------------|-------|-------------------|
| `<b>` (as bold styling) | Use only for "bring attention" without importance; not just boldness | `<strong>` for importance; CSS `font-weight` for style |
| `<i>` (as italic styling) | Overloaded; should only mark idiomatic/technical/foreign text | `<em>` for stress emphasis; `<i>` for technical terms |
| `<u>` (as underline) | Confuses users expecting hyperlinks | CSS `text-decoration: underline`; `<u>` only for unarticulated annotations |
| `<small>` (as visual size) | Valid for legal/fine print semantics; not for general size reduction | CSS `font-size` for visual sizing |
| `<s>` | Valid for no-longer-accurate text; not just strikethrough styling | CSS `text-decoration: line-through` for pure styling |
| `<hgroup>` | Originally grouped headings; deemed redundant and obsoleted | Place subheading in `<p>` immediately following the main heading |

### Obsolete but Semantically Refined (Special Cases)

- **`<b>`** — In HTML5, `<b>` was redefined as "bring attention to" text (like a keyword or product name) — **not** simply bold text. Bold styling with no semantic meaning → use CSS only.
- **`<i>`** — Redefined for idiomatic text, technical terms, foreign phrases, or thoughts. For stress emphasis that changes the meaning of a sentence → use `<em>`.
- **`<small>`** — Redefined for "side comments" like fine print, legal disclaimers. Not for general font-size reduction.

---

## 4. Obsolete & Deprecated HTML Attributes

Many HTML4 attributes that controlled presentation or had specialized scripting behaviors are explicitly marked obsolete. They belong in CSS, not HTML.

### Styling & Presentational Attributes (All Element Types)

| Attribute | On Element(s) | Modern Replacement |
|-----------|--------------|-------------------|
| `align` | `<div>`, `<p>`, `<table>`, `<td>`, `<th>`, `<caption>`, etc. | CSS: `text-align`, `display: flex` with `justify-content` |
| `valign` | `<td>`, `<th>` | CSS: `vertical-align` |
| `bgcolor` | `<body>`, `<table>`, `<tr>`, `<td>`, `<th>` | CSS: `background-color` |
| `background` | `<body>`, `<table>` | CSS: `background-image` |
| `border` | `<img>`, `<table>` | CSS: `border` |
| `cellpadding` | `<table>` | CSS: `padding` on cells |
| `cellspacing` | `<table>` | CSS: `border-spacing` |
| `hspace` / `vspace` | `<img>`, `<object>` | CSS: `margin` |
| `width` / `height` (on non-media elements) | `<table>`, `<td>`, `<hr>`, etc. | CSS; **EXCEPTION:** always retain `width`/`height` on `<img>` for CLS prevention |
| `noshade` | `<hr>` | CSS styling |
| `nowrap` | `<td>` | CSS: `white-space: nowrap` |
| `color` | `<hr>`, `<font>` | CSS: `color` |
| `nohref` | `<area>` | N/A — just omit `href` |

### Scripting & Linking Attributes

| Attribute | On Element | Modern Replacement | Notes |
|-----------|-----------|-------------------|-------|
| `type="text/javascript"` | `<script>` | Omit entirely | JS is the default; this attribute is now unnecessary |
| `language="JavaScript"` | `<script>` | Omit entirely | Script MIME type is determined by `type` or HTTP `Content-Type` |
| `charset` | `<script>` | Omit — UTF-8 inherits from document; set via `<meta charset="utf-8">` | |
| `type="text/css"` | `<style>`, `<link>` | Omit entirely | CSS is the default |
| `name` | `<a>` | Use `id` attribute on the target element | `id` is the universal standard for fragment identifiers |
| `rev` | `<link>`, `<a>` | Use `rel` with the opposite term | |
| `charset` | `<link>`, `<a>` | HTTP `Content-Type` header | |
| `coords` / `shape` | `<a>` | Use `<area>` in `<map>` image maps | |
| `scheme` | `<meta>` | Omit; use machine-readable formats | |
| `longdesc` | `<img>`, `<iframe>` | `aria-describedby` pointing to a nearby description element, or a visible link near the image | |
| `manifest` | `<html>` | Service Workers + Cache API | AppCache was deprecated entirely |
| `accept` | `<form>` | Move `accept` attribute to `<input type="file">` | |
| `usemap` | Non-image elements | N/A | Only valid on `<img>`, `<object>` |

### Table-Specific Obsolete Attributes

| Attribute | Replacement |
|-----------|-------------|
| `axis` | Use `scope="col"` or `scope="row"` on `<th>` |
| `summary` on `<table>` | Use `<caption>` element; or `aria-describedby` pointing to a description |
| `frame` / `rules` on `<table>` | CSS borders |
| `abbr` on `<td>` | Move content to `<th>` with proper `scope`; provide visible abbreviations |

### Before vs. After Comparison

```html
<!-- ❌ OLD / OBSOLETE -->
<script type="text/javascript" language="JavaScript"></script>
<img src="photo.jpg" border="0" align="left" hspace="10">
<table cellpadding="5" bgcolor="#ffffff">
  <td nowrap valign="top">
<a name="section5"></a>

<!-- ✅ MODERN -->
<script src="app.js" defer></script>
<img src="photo.jpg" style="float:left; margin-right:10px"
     width="300" height="200" alt="Descriptive alt text">
<table style="background:#ffffff">
  <td style="padding:5px; white-space:nowrap; vertical-align:top">
<h2 id="section5">Section Title</h2>
```

---

## 5. Semantic HTML & Document Landmarks

Using the correct semantic element gives you **implicit ARIA roles, built-in keyboard behavior, and accessible focus management — all for free.** This is why "HTML First" is rule #1.

### 5.1 Sectioning Elements & Implicit ARIA Roles

| Element | Implicit ARIA Role | Landmark Context | When to Use | ID/Labelling |
|---------|-------------------|-----------------|-------------|-------------|
| `<header>` | `banner` (only when direct child of `<body>`) | Yes — page header | Site header containing logo, branding, main nav. If placed inside `<article>` or `<section>`, it's just a local header — **not** a landmark | Label not needed if unique; use `aria-label` if multiple `<header>` elements exist |
| `<nav>` | `navigation` | Yes | Major navigation blocks: main menu, breadcrumbs, table of contents. **Not** every group of links — only significant navigation | **Required** `aria-label` (e.g., `"Main"`, `"Breadcrumbs"`, `"Footer"`) when multiple `<nav>` elements exist on same page |
| `<main>` | `main` | Yes | The primary, dominant content of the page. **Only ONE visible `<main>` per page**. Must not be nested inside `<article>`, `<aside>`, `<nav>`, `<header>`, or `<footer>` | Give it an `id` (e.g., `id="content"`) to serve as the skip-link target |
| `<footer>` | `contentinfo` (only when direct child of `<body>`) | Yes — page footer | Footer of nearest sectioning ancestor. Can appear inside `<article>` as a local footer | Use `aria-label` if multiple footers |
| `<aside>` | `complementary` | Yes | Content tangentially related to the surrounding content — sidebars, pull quotes, related-article lists, ads. NOT for unrelated wrappers | `aria-labelledby` or `aria-label` when multiple asides exist |
| `<article>` | `article` | No | A **self-contained, independently distributable, or syndicatable** piece of content — blog posts, news articles, forum threads, product cards, comments, widgets. Ask: "Could this stand alone in an RSS feed?" | Should contain a heading; can have its own `<header>`/`<footer>` |
| `<section>` | `region` (only if it has an accessible name) | Only when named | A **thematic grouping** of content that would appear in a page's table of contents. NOT a generic container — use `<div>` for that | **Should** have a heading (`<h2>`–`<h6>`); use `aria-labelledby="heading-id"` or `aria-label` |
| `<div>` | None | No | Generic block container with no meaning. Use only when no semantic element is appropriate | N/A unless you add an explicit ARIA role |
| `<span>` | None | No | Generic inline container with no meaning | N/A |
| `<form>` | `form` (if it has an accessible name) | Yes when named | Any HTML form | Add `aria-labelledby` or `aria-label` to make it a form landmark |
| `<search>` (HTML5.3) | `search` | Yes | Identifies a search component — wraps the search form | `aria-label="Search"` recommended |
| `<figure>` | `figure` | No | Media wrapper — pairs with `<figcaption>` for captions | Pair with `<figcaption>`; role `caption` applies |
| `<address>` | N/A | No | Contact information for the **nearest `<article>` or `<body>` ancestor** — not for postal addresses in general | N/A |

**Critical rules for sectioning:**
- Every visible piece of content must be inside a landmark so screen reader users can navigate to all parts of the page.
- `<section>` without an accessible name does **not** become a `region` landmark — skip it if you can't name it, or use `<div>` instead.
- Never nest `<main>` inside other landmarks.
- `<header>` and `<footer>` only become landmarks (`banner` / `contentinfo`) when they are direct children of `<body>` — not when nested inside sectioning elements.

---

### 5.2 Text Content Elements

| Element | Correct Meaning | Common Misuse to Avoid |
|---------|----------------|------------------------|
| `<h1>`–`<h6>` | Section headings in **strict hierarchical order**. One `<h1>` per page (usually). Never skip levels just for visual size | Using headings for visual styling, not document hierarchy |
| `<p>` | A paragraph of text | Using `<br><br>` to create fake paragraphs |
| `<strong>` | **Strong importance**, seriousness, or urgency — not just bold | Using `<b>` when importance is intended |
| `<em>` | **Stress emphasis** that changes meaning of the sentence | Using `<i>` when emphasis is intended |
| `<b>` | **Bring attention** to text without implying extra importance (key terms, product names, lead sentence) | Using it as a generic bold wrapper |
| `<i>` | **Idiomatic text**, technical terms, foreign phrases, thoughts, ship names | Using for generic italics |
| `<mark>` | **Highlighted/searched text** for reference purposes | Using for general design highlights without semantic meaning |
| `<time>` | Dates, times, durations — **always include `datetime` attribute** in machine-readable format | Writing dates as plain text without semantic value |
| `<abbr>` | Abbreviations — include `title` for the full expansion | Not providing the expansion in `title`; still using `<acronym>` |
| `<cite>` | The **title of a creative work** being referenced (book, film, article) | Using for person names (it's for work titles only) |
| `<q>` | **Short inline quotations** (browser adds quotation marks via CSS) | Manually inserting `"quotes"` that aren't semantically quotations |
| `<blockquote>` | **Extended quotations**; use `cite` attribute for source URL | Using for indented layout (not a quotation) |
| `<code>` | Computer code fragments | Using `<pre>` alone without `<code>` inside it |
| `<kbd>` | **Keyboard input** | Using `<code>` for keyboard shortcuts |
| `<samp>` | **Program / computer output** | Confusion with `<code>` |
| `<var>` | **Variable** in math or programming context | — |
| `<del>` / `<ins>` | **Deleted / inserted** content in revision-tracked documents | Still using `<strike>` (obsolete) |
| `<sup>` / `<sub>` | **Superscript / subscript** — footnotes, exponents, chemical formulas | Using for purely visual position without semantic reason |
| `<dfn>` | The **term being defined** at its first occurrence | — |
| `<small>` | **Side comments** — fine print, legal disclaimers, copyright notices | Using for general font-size reduction |

---

### 5.3 Interactive Elements — Button vs. Link

**Golden Rule:** Use `<a href>` to go somewhere. Use `<button>` to do something.

| Element | Use For | Default `type` | Key Behaviour | Accessibility |
|---------|---------|---------------|---------------|--------------|
| `<a href="...">` | Navigate to URL, open file in new tab, in-page anchor, download | N/A | Enter key activates; middle-click opens new tab; right-click → "Open in new tab" | Announces "link" role to screen readers |
| `<button>` | Submit form, toggle UI, trigger JS action, open modal | `type="submit"` (default inside `<form>`) | Enter **and** Space key activate | Announces "button" role; always add `type="button"` outside forms to prevent accidental submission |
| `<input type="submit">` | Form submission | submit | Good compatibility | Prefer `<button type="submit">` for richer HTML content inside label |
| `<summary>` | Visible toggle for `<details>` disclosure widget | N/A | Native toggle without JS | Native keyboard accessible |
| `<select>` | Choose from list of options | N/A | Native keyboard navigation | Avoid replacing with custom `<div>` selects unless absolutely necessary — complex keyboard implementation required |

**Never do this:**
```html
<!-- ❌ WRONG — div/span as button -->
<div onclick="doThing()" class="btn">Click me</div>
<span onclick="doThing()">Click me</span>

<!-- ❌ WRONG — link as button -->
<a href="#" onclick="openModal()">Open Modal</a>

<!-- ✅ CORRECT -->
<button type="button" onclick="doThing()">Click me</button>
<button type="button" onclick="openModal()">Open Modal</button>
<a href="/products">View Products</a>
```

If you absolutely must use a non-button element as an interactive control, you **must** add: `role="button"`, `tabindex="0"`, Enter/Space `keydown` event handlers, and appropriate `aria-*` state attributes — but this defeats the purpose when a native `<button>` exists.

---

### 5.4 Lists — `<ul>`, `<ol>`, `<dl>`

```html
<!-- Unordered: when order doesn't matter -->
<ul>
  <li>Apples</li>
  <li>Bananas</li>
</ul>

<!-- Ordered: steps, rankings, sequences where order matters -->
<ol>
  <li>Preheat oven to 180°C</li>
  <li>Mix ingredients</li>
</ol>

<!-- Description list: term-definition pairs, glossaries, metadata, key-value displays -->
<dl>
  <dt>LCP</dt>
  <dd>Largest Contentful Paint — measures loading performance</dd>
  <dt>INP</dt>
  <dd>Interaction to Next Paint — measures responsiveness</dd>
</dl>

<!-- Navigation lists: semantic list inside <nav> -->
<nav aria-label="Main navigation">
  <ul>
    <li><a href="/" aria-current="page">Home</a></li>
    <li><a href="/about">About</a></li>
  </ul>
</nav>
```

**Important:** Screen readers announce the number of list items. Use `<ul>` / `<ol>` / `<li>` strictly for **list data** — not for layout purposes. If CSS resets remove bullet points (e.g., `list-style: none`), some screen readers (notably Safari/VoiceOver) may stop recognizing the element as a list; add `role="list"` to restore semantics if needed.

---

### 5.5 Form Elements Best Practices

Every form control must have an accessible label. The preferred approaches, in order of preference:

1. **Native `<label for="id">`** — clicking the label focuses the input (increases touch target); programmatically associates label with control
2. **`aria-labelledby`** — references the ID of another visible element
3. **`aria-label`** — provides an invisible string label (use when no visible text is available)

```html
<!-- ✅ CORRECT: Native label with for/id linkage -->
<label for="email">Email address</label>
<input type="email" id="email" name="email"
       required
       aria-required="true"
       aria-describedby="email-hint email-error"
       autocomplete="email">
<span id="email-hint">We'll never share your email.</span>
<span id="email-error" role="alert" hidden>
  Please enter a valid email address.
</span>

<!-- ✅ CORRECT: Group related fields -->
<fieldset>
  <legend>Shipping address</legend>
  <label for="street">Street</label>
  <input type="text" id="street" autocomplete="street-address">
  <label for="city">City</label>
  <input type="text" id="city" autocomplete="address-level2">
</fieldset>
```

**Form best practices:**
- Use `autocomplete` attributes with correct token values — helps users with cognitive disabilities and reduces form completion friction.
- Use HTML5 native validation (`required`, `pattern`, `min`, `max`) augmented by `aria-invalid="true"` when validation fails.
- Error messages must be programmatically associated with their inputs using `aria-describedby` or `aria-errormessage`.
- Use `<button type="submit">` not `<input type="submit">` — allows rich HTML inside the button label.

---

### 5.6 Tables — Accessible Structure

Tables are **only for tabular data** — never for visual layout.

```html
<table>
  <caption>Monthly Revenue by Product</caption>
  <thead>
    <tr>
      <th scope="col">Product</th>
      <th scope="col">Q1 Revenue</th>
      <th scope="col">Q2 Revenue</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <th scope="row">Widget A</th>
      <td>$12,000</td>
      <td>$14,500</td>
    </tr>
  </tbody>
  <tfoot>
    <tr>
      <th scope="row">Total</th>
      <td>$12,000</td>
      <td>$14,500</td>
    </tr>
  </tfoot>
</table>
```

**Required accessible table structure:**
- `<caption>` — describes the table purpose (equivalent to `<h*>` for the table)
- `<thead>`, `<tbody>`, `<tfoot>` — semantic table sections
- `<th scope="col">` for column headers; `<th scope="row">` for row headers
- For complex tables: use `id` on `<th>` and `headers="id1 id2"` on `<td>` to explicitly associate cells with multiple headers
- Never use `border`, `cellpadding`, `cellspacing`, `bgcolor` — use CSS

---

## 6. ARIA — Roles, States, Properties, Patterns

ARIA (Accessible Rich Internet Applications) is a W3C specification that adds supplementary semantics to HTML for assistive technologies. It is **supplemental to, not a replacement for**, native HTML semantics.

### 6.1 The Rules of ARIA

**Rule 1 — HTML First (most important):** If a native HTML element or attribute has the semantics and behavior you require already built in, use it instead of re-purposing another element and adding ARIA.

**Rule 2 — Don't Change Native Semantics:** Do not change the semantics of a native element unless you have no choice. Never do `<button role="heading">` or `<h2 role="button">`.

**Rule 3 — Keyboard Operability:** All interactive ARIA controls must be keyboard operable. Adding `role="button"` to a `<div>` means you must also add `tabindex="0"` and keydown handlers for Enter and Space.

**Rule 4 — No `aria-hidden` on Focusable Elements:** Do not use `role="presentation"` or `aria-hidden="true"` on elements that are focusable. A hidden-but-focusable element creates a "focus black hole."

**Rule 5 — All Interactive Elements Need an Accessible Name:** Every interactive element must have an accessible name via `aria-label`, `aria-labelledby`, or visible text content.

> **"No ARIA is better than bad ARIA."** — W3C ARIA Authoring Practices Guide (APG)

---

### 6.2 Landmark Roles Table

| HTML Element | Implicit ARIA Role | When Explicit Role Is Needed | Labelling Strategy |
|--------------|-------------------|-----------------------------|-------------------|
| `<header>` (in `<body>`) | `banner` | Only for IE11/old AT: `role="banner"` | Optional `aria-label` |
| `<nav>` | `navigation` | Old AT: `role="navigation"` | **Required** if multiple navs: `aria-label="Primary"`, `aria-label="Footer"` etc. |
| `<main>` | `main` | IE11: `role="main"` | Not needed — unique per page |
| `<footer>` (in `<body>`) | `contentinfo` | IE11: `role="contentinfo"` | Optional `aria-label` |
| `<aside>` | `complementary` | IE11: `role="complementary"` | **Required** if multiple: `aria-label` or `aria-labelledby` |
| `<section>` (with accessible name) | `region` | Rarely needed | **Required:** `aria-labelledby="heading-id"` |
| `<form>` (with accessible name) | `form` | Add `aria-labelledby` to make it a form landmark | `aria-label` or `aria-labelledby` |
| Search form | — | Add `role="search"` to search `<form>` or use `<search>` element | `aria-label="Search"` |
| `<div>` | None | Add role when needed: `role="dialog"`, `role="tabpanel"`, etc. | Always add `aria-label` or `aria-labelledby` |

---

### 6.3 Key ARIA States & Properties Reference

| Attribute | Purpose | Values |
|-----------|---------|--------|
| `aria-label` | Provides accessible name when no visible label exists | String |
| `aria-labelledby` | References ID(s) of element(s) that label this element — **highest naming precedence** | Space-separated ID list |
| `aria-describedby` | References ID of element providing supplementary description (read after name/role) | Space-separated ID list |
| `aria-hidden="true"` | Removes element from accessibility tree (decorative icons, duplicated text) | `true` / `false` |
| `aria-expanded` | State of expandable widgets (accordions, dropdowns, tree nodes) | `true` / `false` |
| `aria-controls` | References element that this control expands or controls | ID string |
| `aria-owns` | Defines parent/child relationship for AT when elements aren't natively nested in DOM | ID list |
| `aria-current` | Marks current item in a set (navigation, steps, breadcrumbs) | `page` / `step` / `location` / `date` / `time` / `true` |
| `aria-live` | Makes dynamic region announce changes to screen readers | `polite` / `assertive` / `off` |
| `aria-atomic` | Whether to announce entire live region or just changed portion | `true` / `false` |
| `aria-busy` | Element is loading or updating | `true` / `false` |
| `aria-required` | Marks form field as required (prefer native HTML `required` first) | `true` / `false` |
| `aria-invalid` | Marks a form field as invalid with optional reason | `true` / `false` / `grammar` / `spelling` |
| `aria-errormessage` | References ID of element containing error message text | ID string |
| `aria-disabled` | Element is perceivable but not operable | `true` / `false` |
| `aria-selected` | Current selection state in tab list, tree, listbox, grid | `true` / `false` |
| `aria-checked` | Checkbox / radio / switch state | `true` / `false` / `mixed` |
| `aria-pressed` | Toggle button state | `true` / `false` / `mixed` |
| `aria-valuenow` / `aria-valuemin` / `aria-valuemax` | Range widget values (sliders, progress bars, spinners) | Number |
| `aria-valuetext` | Human-readable version of current range value | String |
| `aria-haspopup` | Indicates the element will open a popup when activated | `true` / `menu` / `listbox` / `tree` / `grid` / `dialog` |
| `aria-modal` | Marks a dialog as modal (restricts AT navigation to dialog content) | `true` / `false` |
| `aria-multiselectable` | Grid/listbox allows multiple selections | `true` / `false` |
| `aria-sort` | Column sort direction in a sortable grid/table | `ascending` / `descending` / `none` / `other` |
| `aria-level` | Heading level for elements with `role="heading"` | Number 1–6 |
| `aria-setsize` / `aria-posinset` | Total items in set and position within set (for virtual/windowed lists) | Number |

---

### 6.4 Naming Hierarchy (Accessible Names)

The browser calculates an accessible name using this precedence order (highest → lowest):

1. `aria-labelledby` — concatenates the text content of all referenced elements (even hidden ones)
2. `aria-label` — direct string attribute
3. Native HTML labeling — `<label for>` for form controls; `alt` for images; `<caption>` for tables; `<legend>` for fieldsets; `<title>` for SVG
4. `title` attribute — last resort (also creates a tooltip; poor screen-reader support pattern)

**Accessible description** (announced after name and role) uses `aria-describedby` → references ID of element containing the description.

```html
<!-- aria-labelledby allows combining multiple element texts -->
<section aria-labelledby="news-heading">
  <h2 id="news-heading">Latest News</h2>
  <!-- Content -->
</section>

<!-- aria-label when no visible text -->
<nav aria-label="Main navigation">
  <!-- nav links -->
</nav>

<!-- aria-describedby for supplementary info -->
<input type="password" id="pwd"
       aria-describedby="pwd-requirements">
<p id="pwd-requirements">
  Must be 8+ characters with at least one number and one uppercase letter.
</p>
```

**Critical failure pattern to avoid:** Broken ID references (`aria-describedby="help"` but no element has `id="help"`). This produces "broken ARIA reference" errors in accessibility auditing tools.

---

### 6.5 Common ARIA Patterns (Code)

```html
<!-- 1. Accessible Navigation with current page indicator -->
<nav aria-label="Main navigation">
  <ul>
    <li><a href="/" aria-current="page">Home</a></li>
    <li><a href="/about">About</a></li>
    <li><a href="/contact">Contact</a></li>
  </ul>
</nav>

<!-- 2. Accessible Disclosure / Accordion -->
<button aria-expanded="false" aria-controls="panel-1" id="btn-1"
        type="button">
  Section Title
</button>
<div id="panel-1" role="region" aria-labelledby="btn-1" hidden>
  Content here
</div>
<!-- JS: toggle aria-expanded and hidden attribute together -->

<!-- 3. Accessible Modal Dialog -->
<div role="dialog" aria-modal="true"
     aria-labelledby="dialog-title"
     aria-describedby="dialog-desc">
  <h2 id="dialog-title">Confirm Delete</h2>
  <p id="dialog-desc">This action cannot be undone. Are you sure?</p>
  <button autofocus>Confirm</button>
  <button>Cancel</button>
</div>

<!-- 4. Live Region for dynamic updates -->
<div role="status" aria-live="polite" aria-atomic="true">
  <!-- Inject success/info messages here -->
</div>
<div role="alert" aria-live="assertive">
  <!-- Inject urgent error messages here -->
</div>
<!-- Note: MDN warns that mixing role="alert" with aria-live="assertive"
     on the same element can cause double-speaking in VoiceOver on iOS.
     Prefer using role="alert" alone for assertive regions. -->

<!-- 5. Custom Toggle Button with ARIA state -->
<button type="button"
        aria-expanded="false"
        aria-controls="mobile-menu"
        id="menu-toggle">
  Menu
</button>
<div id="mobile-menu" hidden>
  <!-- Mobile navigation -->
</div>
<script>
  const btn = document.getElementById('menu-toggle');
  btn.addEventListener('click', () => {
    const expanded = btn.getAttribute('aria-expanded') === 'true';
    btn.setAttribute('aria-expanded', String(!expanded));
    document.getElementById('mobile-menu').hidden = expanded;
  });
</script>

<!-- 6. Icon-only button (must have accessible name) -->
<button type="button" aria-label="Close dialog">
  <svg aria-hidden="true" focusable="false" width="24" height="24">
    <!-- SVG path -->
  </svg>
</button>

<!-- 7. Custom progress bar -->
<div role="progressbar"
     aria-valuenow="75"
     aria-valuemin="0"
     aria-valuemax="100"
     aria-valuetext="75% complete">
  <div style="width: 75%; background: #4f8ef7; height: 8px;"></div>
</div>

<!-- 8. Skip link — MUST be first focusable element in <body> -->
<a href="#main-content" class="skip-link">Skip to main content</a>
<!-- CSS: .skip-link { position: absolute; transform: translateY(-100%); }
          .skip-link:focus { transform: translateY(0); } -->
<main id="main-content">
  <h1>Page title</h1>
  <!-- content -->
</main>
```

---

## 7. Accessibility Deep Dive

### 7.1 Color Contrast — WCAG 2.x Formula & Thresholds

**The Formula:**

```
Contrast Ratio = (L1 + 0.05) / (L2 + 0.05)
```

Where:
- `L1` = relative luminance of the **lighter** of the two colors
- `L2` = relative luminance of the **darker** of the two colors
- Relative luminance: `L = 0.2126R + 0.7152G + 0.0722B`
- RGB values are **linearized**: if sRGB ≤ 0.04045 → divide by 12.92; else → `((sRGB + 0.055) / 1.055) ^ 2.4`

**WCAG 2.2 Contrast Thresholds:**

| Content Type | AA (Target Standard) | AAA (Enhanced) |
|-------------|---------------------|----------------|
| **Normal text** (< 18pt regular or < 14pt bold) | **4.5:1** | **7:1** |
| **Large text** (≥ 18pt / 24px regular, or ≥ 14pt / 18.67px bold) | **3:1** | **4.5:1** |
| **Non-text UI** (icons, input borders, focus rings, chart elements needed to understand the content) | **3:1** | — |
| **Focus indicators** (WCAG 2.2 SC 2.4.11) | **3:1** against adjacent colors | — |
| Incidental text (inactive UI, decorative) | Exempt | Exempt |
| Logotypes | Exempt | Exempt |
| Placeholder text in inputs | **4.5:1** (NOT exempt) | — |

**Maximum contrast:** 21:1 (black on white / white on black).

**Calculating specific values:**
- White `#ffffff`: L = 1.0
- Black `#000000`: L = 0.0
- Ratio = (1 + 0.05) / (0 + 0.05) = **21:1**

**Recommended tools:**
- WebAIM Contrast Checker: webaim.org/resources/contrastchecker/
- TPGi Colour Contrast Analyser (CCA) — desktop app
- Browser DevTools accessibility panel
- Lighthouse → Accessibility audit

**Best practices beyond the formula:**
- Do **not** rely on color alone to convey information (e.g., "Red = Error"). Always pair color with an icon, pattern, or descriptive text — required by WCAG SC 1.4.1.
- When placing text over background images or gradients: use semi-transparent overlays or text-shadow to guarantee readability in worst-case scenarios:

```css
.hero-text {
  color: white;
  text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
  /* Or: overlay the image with darkening gradient */
  background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4));
}
```

- Test in both light and dark mode.
- Test on gray/muted displays (simulating low-quality monitors and aging screens).

---

### 7.2 APCA — Advanced Perceptual Contrast Algorithm

APCA is the contrast algorithm being developed for WCAG 3.0. Unlike the WCAG 2.x formula, APCA is **directional** (foreground and background order matters) and **context-sensitive** (accounts for font size, weight, and polarity — dark-on-light vs. light-on-dark).

Scores are expressed as `Lc` (Lightness Contrast Value) on a scale of 0–106:

| APCA Lc Value | Minimum Appropriate Use |
|---------------|------------------------|
| **Lc 90+** | Preferred for body text in columns (14px/400 weight or larger) |
| **Lc 75** | Minimum for body text columns (18px/400 or larger) |
| **Lc 60** | Minimum for regular-weight text at comfortable reading sizes (24px+) |
| **Lc 45** | Minimum for large bold text or non-text UI elements |
| **Lc 30** | Incidental text, placeholder, or supplementary text |
| **Lc 15** | Near-invisibility for many users — avoid |

**Why it matters now:** WCAG 2.x math has known flaws — orange-on-white "passes" 4.5:1 but is perceptually difficult; certain dark-mode combinations "fail" but are comfortable. While WCAG 2.2 is the legal standard, using APCA as a design quality check is recommended. Evaluate with the Accessible Perceptual Contrast Calculator at myndex.com/APCA/.

---

### 7.3 Keyboard & Focus Management

**WCAG Success Criteria:**

| SC | Level | Requirement |
|----|-------|-------------|
| 2.1.1 No Keyboard Trap | A | All functionality operable via keyboard |
| 2.1.2 No Keyboard Trap | A | User can always navigate away from any component |
| 2.4.3 Focus Order | A | Focus order preserves meaning and operability |
| 2.4.7 Focus Visible | AA | Any keyboard-operable UI must have visible focus |
| 2.4.11 Focus Not Obscured | AA (New in 2.2) | Focused component must not be entirely hidden by sticky overlays |
| 2.4.12 Focus Not Obscured (Enhanced) | AAA (New in 2.2) | Focused component must be fully visible |

**`tabindex` rules:**
- `tabindex="0"` — makes non-interactive element keyboard focusable (use sparingly; prefer native interactive elements)
- `tabindex="-1"` — removes from natural tab order but can receive focus programmatically (useful for modal focus management, skip-link targets)
- `tabindex > 0` — **avoid entirely**. Creates artificial tab order that diverges from DOM order, causing confusion

**Focus management patterns:**
- **DOM order = visual order.** Never use CSS (flexbox `order`, grid placement, `position: absolute`) to create visual order that conflicts with source order — screen readers and keyboard users navigate DOM order.
- **Modal focus trap:** When a modal opens, move focus to first focusable element inside it (`autofocus` or `dialog.showModal()`). Trap Tab/Shift+Tab within the modal. When closed, return focus to the trigger element.
- **Dynamic content:** When content is dynamically injected (e.g., search results), move focus to the results container or first result so keyboard/screen reader users know the update happened.
- **Never use `outline: none` globally on `:focus`.** Instead, use `:focus-visible` to show high-contrast outlines only for keyboard users while keeping the UI clean for mouse users:

```css
/* ✅ CORRECT: keyboard-only focus styles */
:focus-visible {
  outline: 3px solid #4f8ef7;
  outline-offset: 2px;
}

/* ❌ WRONG: removes focus for everyone */
:focus { outline: none; }
```

---

### 7.4 Skip Links & Bypass Blocks

WCAG SC 2.4.1 (Level A) requires a mechanism for bypassing repeated navigation blocks. Skip links are the primary technique.

**Implementation:**

```html
<!-- First element in <body> -->
<a href="#main-content" class="skip-link">Skip to main content</a>

<header>
  <!-- extensive site navigation -->
</header>

<main id="main-content" tabindex="-1">
  <h1>Page title</h1>
  <!-- content -->
</main>
```

```css
.skip-link {
  position: absolute;
  left: -9999px;
  top: auto;
  width: 1px;
  height: 1px;
  overflow: hidden;
}

.skip-link:focus {
  position: fixed;
  top: 0;
  left: 0;
  width: auto;
  height: auto;
  overflow: visible;
  padding: 12px 20px;
  background: #000;
  color: #fff;
  font-size: 1rem;
  z-index: 9999;
  text-decoration: none;
}
```

The `tabindex="-1"` on `<main>` ensures the element receives focus when the skip link is activated (some browsers require this for non-interactive elements to accept programmatic focus).

---

### 7.5 Motion Preferences (`prefers-reduced-motion`)

The `prefers-reduced-motion` CSS media feature detects whether the user has enabled "Reduce Motion" at the operating system level. This is critical for users with vestibular disorders, photosensitive epilepsy, and other motion sensitivities.

```css
/* Default: animations enabled */
.animated-element {
  transition: transform 0.3s ease;
  animation: slide-in 0.5s ease;
}

/* Respect user's reduced-motion preference */
@media (prefers-reduced-motion: reduce) {
  .animated-element {
    transition: none;
    animation: none;
  }

  /* Alternative: keep essential state changes but remove motion */
  .animated-element {
    transition: opacity 0.1s;
    animation: none;
  }
}
```

**Important nuance:** The intent is to reduce or remove **non-essential motion**, not disable every animation. Removing all transitions/animations can itself harm usability by removing essential affordances (e.g., disclosure of state changes). Use judgment: keep opacity changes and instant state transitions; remove parallax, auto-playing carousels, and large movement-based animations.

---

### 7.6 Live Regions & Dynamic Announcements

Modern single-page apps frequently update content after page load. Screen reader users need to be informed about important updates.

| ARIA Attribute/Role | Urgency | When to Use |
|--------------------|---------|------------|
| `aria-live="polite"` | Low — waits for current speech to finish | Success messages, search results loading, status updates, chat messages |
| `aria-live="assertive"` | High — interrupts immediately | Critical errors, urgent security alerts |
| `role="status"` | Implicit `aria-live="polite"` | General status messages |
| `role="alert"` | Implicit `aria-live="assertive"` | Error messages, important warnings |
| `aria-atomic="true"` | Read entire region when any part changes | Countdown timers, status summaries |
| `aria-relevant` | Control what types of changes trigger announcements | `additions`, `removals`, `text`, `all` |

**Implementation pattern:**

```html
<!-- Polite status region (pre-existing in DOM, inject text via JS) -->
<div role="status" aria-live="polite" aria-atomic="true" id="status-msg">
  <!-- JS injects: "Item added to cart." -->
</div>

<!-- Error alert region -->
<div role="alert" id="error-region">
  <!-- JS injects: "Error: Please correct 3 fields." -->
</div>
```

**Critical implementation note:** Live regions must **exist in the DOM before** content is injected into them. Dynamically created live regions often don't work reliably in screen readers. Create the container on page load (empty), then inject messages via JavaScript.

---

### 7.7 Target Size (WCAG 2.2 SC 2.5.8)

**AA requirement (new in WCAG 2.2):** Touch targets must be at least **24×24 CSS pixels**, unless:
- An exception applies (spacing, inline text, essential, unavoidable)
- The target is spaced such that a 24×24px circle centered on the target doesn't intersect another target or that circle

**Historical best practice (WCAG AAA / iOS HIG / Android Material Design):** 44×44 CSS pixels minimum for all interactive elements. This remains the recommended target size for optimal usability, even though 24×24px is the AA floor.

```css
/* Ensuring minimum target size while keeping visual appearance smaller */
.small-icon-button {
  min-width: 44px;
  min-height: 44px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

/* Or use transparent padding to expand clickable area */
.icon-link {
  padding: 10px;
}
```

---

## 8. SEO & `<head>` Complete Reference

### 8.1 Essential Meta Tags

The `<head>` must be optimized for search crawlers, social sharing, and mobile rendering. Order matters: `<meta charset>` must appear within the first 1024 bytes of the document.

```html
<!DOCTYPE html>
<html lang="en"> <!-- lang is critical for AT and SEO -->
<head>
  <!-- 1. Character encoding: MUST be within first 1024 bytes -->
  <meta charset="UTF-8">

  <!-- 2. Viewport: mandatory for mobile-first rendering -->
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- 3. Title: unique per page; 50-60 characters; most important SEO element -->
  <title>Page Title · Brand Name</title>

  <!-- 4. Meta description: 150-160 chars; affects CTR in search results -->
  <!-- Google truncates as needed based on device/query; no hard limit -->
  <meta name="description" content="Compelling, benefit-driven page summary.">

  <!-- 5. Canonical: prevent duplicate content; signal preferred URL -->
  <link rel="canonical" href="https://example.com/this-page/">

  <!-- 6. Robots: control crawling and indexing -->
  <meta name="robots" content="index, follow">
  <!-- Options: noindex, nofollow, noarchive, nosnippet, noimageindex -->
  <!-- Also available as X-Robots-Tag HTTP header for non-HTML resources -->

  <!-- 7. Color scheme hints (for browser UI chrome) -->
  <meta name="color-scheme" content="light dark">
  <meta name="theme-color" content="#0a0b0f" media="(prefers-color-scheme: dark)">
  <meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
</head>
```

**Meta tags to AVOID:**
- `<meta name="keywords">` — Google has not used this for over a decade; pure noise.
- `<meta http-equiv="refresh">` — Use server-side 301 redirects or JS `window.location` instead; this causes UX issues and accessibility failures.
- `<meta name="author">` — No significant SEO value in 2026.

---

### 8.2 Open Graph Protocol

Open Graph (OGP) is the dominant cross-platform metadata standard for social previews on Facebook, LinkedIn, WhatsApp, Slack, iMessage, and more.

```html
<!-- Open Graph — include on all shareable pages -->
<meta property="og:type" content="website">
<!-- og:type values: website, article, product, book, music.song, video.movie -->
<meta property="og:title" content="Page Title (can differ from <title>)">
<meta property="og:description" content="Description for social sharing...">
<meta property="og:url" content="https://example.com/this-page/">
<meta property="og:image" content="https://example.com/og-image.jpg">
<!-- Image: recommended 1200×630px; minimum 600×315px; use JPG or PNG -->
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="Description of the image for screen readers">
<meta property="og:site_name" content="Your Site Name">
<meta property="og:locale" content="en_US">

<!-- For articles specifically -->
<meta property="article:published_time" content="2026-03-01T00:00:00Z">
<meta property="article:modified_time" content="2026-03-02T00:00:00Z">
<meta property="article:author" content="https://example.com/author/jane-doe">
<meta property="article:section" content="Technology">
<meta property="article:tag" content="Web Standards">
```

---

### 8.3 Twitter / X Cards

```html
<meta name="twitter:card" content="summary_large_image">
<!-- Card types: summary, summary_large_image, app, player -->
<meta name="twitter:title" content="Page Title">
<meta name="twitter:description" content="Description for Twitter/X preview...">
<meta name="twitter:image" content="https://example.com/twitter-card.jpg">
<meta name="twitter:image:alt" content="Alt text for the card image">
<meta name="twitter:site" content="@YourOrganizationHandle">
<meta name="twitter:creator" content="@AuthorHandle">
```

Note: Twitter falls back to Open Graph tags if Twitter-specific tags are absent. However, providing both ensures optimal rendering across platforms.

---

### 8.4 Canonicalization & Robots Directives

**Canonical URL** tells search engines which URL is the "representative" among duplicates (e.g., HTTP vs HTTPS, www vs non-www, trailing slash, URL parameters):

```html
<link rel="canonical" href="https://www.example.com/preferred-url/">
```

**Robots directive hierarchy** (strongest wins):
1. `X-Robots-Tag` HTTP header (applies to all content types including PDFs, images)
2. `<meta name="robots">` (HTML only)
3. `robots.txt` (site-wide; less granular)

**Common robots values:**

| Value | Effect |
|-------|--------|
| `index` | Allow indexing (default) |
| `noindex` | Do not include this page in search results |
| `follow` | Follow links on this page (default) |
| `nofollow` | Do not follow links on this page |
| `noarchive` | Do not show a cached copy |
| `nosnippet` | Do not show text/video snippets in results |
| `noimageindex` | Do not index images on this page |
| `max-snippet:N` | Limit snippet to N characters |
| `max-image-preview:standard` | `none`, `standard`, `large` |

---

### 8.5 Hreflang for Internationalization

`hreflang` helps search engines understand localized variations and serve the correct version to users based on language/region.

```html
<!-- In <head> of every localized version -->
<link rel="alternate" hreflang="en" href="https://example.com/en/">
<link rel="alternate" hreflang="en-US" href="https://example.com/en-us/">
<link rel="alternate" hreflang="fr" href="https://example.com/fr/">
<link rel="alternate" hreflang="de" href="https://example.com/de/">
<!-- x-default: the fallback for users not matched to any specific locale -->
<link rel="alternate" hreflang="x-default" href="https://example.com/">
```

**Notes:**
- Google uses its own language detection algorithms in addition to hreflang — but hreflang provides critical relationship signals especially across localized pages.
- All language variations must reference each other (self-referential), including the default.
- Can also be implemented via XML sitemaps for large sites.

---

### 8.6 Favicons & PWA Manifest

```html
<!-- Favicon: modern approach -->
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<!-- SVG favicons support dark mode via CSS inside SVG -->
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16.png">
<!-- Apple touch icon: used when saved to iOS home screen -->
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
<!-- PWA Manifest -->
<link rel="manifest" href="/site.webmanifest">
```

**robots.txt and sitemap.xml:** Must be present at root of domain for proper SEO crawling:
- `https://example.com/robots.txt`
- `https://example.com/sitemap.xml`

---

## 9. JSON-LD Structured Data

### 9.1 Why JSON-LD

Google supports three formats for structured data: JSON-LD (preferred), Microdata, and RDFa. **JSON-LD is Google's recommended format** because:
- It lives in a `<script>` block, separate from the visual HTML — easier to maintain
- Does not require modifying the DOM structure
- Can be injected server-side without touching templates

JSON-LD uses the **Schema.org vocabulary** and enables **Rich Results** in search (star ratings, FAQ accordions, recipe cards, event information, breadcrumb trails, product prices). Rich results consistently yield **25–82% higher click-through rates** than plain blue links.

**Validate all structured data** using: [Rich Results Test](https://search.google.com/test/rich-results) and [Schema.org Validator](https://validator.schema.org/).

---

### 9.2 Organization

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Organization",
  "name": "Example Company",
  "url": "https://example.com/",
  "logo": {
    "@type": "ImageObject",
    "url": "https://example.com/logo.png",
    "width": 600,
    "height": 60
  },
  "contactPoint": {
    "@type": "ContactPoint",
    "telephone": "+1-555-555-5555",
    "contactType": "customer service",
    "contactOption": "TollFree",
    "availableLanguage": ["English", "French"]
  },
  "sameAs": [
    "https://twitter.com/yourcompany",
    "https://linkedin.com/company/yourcompany",
    "https://facebook.com/yourcompany"
  ]
}
</script>
```

---

### 9.3 Article / NewsArticle

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "NewsArticle",
  "headline": "Article Title (under 110 characters)",
  "description": "Concise article description.",
  "image": [
    "https://example.com/article-image-1x1.jpg",
    "https://example.com/article-image-4x3.jpg",
    "https://example.com/article-image-16x9.jpg"
  ],
  "datePublished": "2026-03-01T08:00:00+05:30",
  "dateModified": "2026-03-02T12:00:00+05:30",
  "author": {
    "@type": "Person",
    "name": "Author Name",
    "url": "https://example.com/author/author-name"
  },
  "publisher": {
    "@type": "Organization",
    "name": "Publisher Name",
    "logo": {
      "@type": "ImageObject",
      "url": "https://example.com/logo.png"
    }
  }
}
</script>
```

---

### 9.4 BreadcrumbList

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    {
      "@type": "ListItem",
      "position": 1,
      "name": "Home",
      "item": "https://example.com/"
    },
    {
      "@type": "ListItem",
      "position": 2,
      "name": "Category",
      "item": "https://example.com/category/"
    },
    {
      "@type": "ListItem",
      "position": 3,
      "name": "Current Page"
      /* Last item: "item" property is optional when it's the current page */
    }
  ]
}
</script>
```

---

### 9.5 FAQPage

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "What is your return policy?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "We offer 30-day returns on all unused products in original packaging."
      }
    },
    {
      "@type": "Question",
      "name": "Do you offer free shipping?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Free shipping on all orders over $50 within the continental US."
      }
    }
  ]
}
</script>
```

---

### 9.6 Product with AggregateRating

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Product",
  "name": "Product Name",
  "image": [
    "https://example.com/product-front.jpg",
    "https://example.com/product-side.jpg"
  ],
  "description": "Detailed product description.",
  "sku": "SKU-12345",
  "brand": {
    "@type": "Brand",
    "name": "Brand Name"
  },
  "offers": {
    "@type": "Offer",
    "url": "https://example.com/product",
    "priceCurrency": "USD",
    "price": "99.99",
    "priceValidUntil": "2026-12-31",
    "availability": "https://schema.org/InStock",
    "itemCondition": "https://schema.org/NewCondition"
  },
  "aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": "4.8",
    "reviewCount": "248",
    "bestRating": "5",
    "worstRating": "1"
  }
}
</script>
```

---

### 9.7 LocalBusiness

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "LocalBusiness",
  "name": "Business Name",
  "image": "https://example.com/storefront.jpg",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "123 Main St",
    "addressLocality": "Anytown",
    "addressRegion": "CA",
    "postalCode": "90210",
    "addressCountry": "US"
  },
  "geo": {
    "@type": "GeoCoordinates",
    "latitude": 34.0522,
    "longitude": -118.2437
  },
  "url": "https://example.com/",
  "telephone": "+1-555-555-5555",
  "openingHoursSpecification": [
    {
      "@type": "OpeningHoursSpecification",
      "dayOfWeek": ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"],
      "opens": "09:00",
      "closes": "18:00"
    }
  ]
}
</script>
```

---

### 9.8 WebPage

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "WebPage",
  "name": "Modern Web Page Example",
  "description": "A page demonstrating modern web standards.",
  "url": "https://example.com/this-page/",
  "publisher": {
    "@type": "Organization",
    "name": "Example Company"
  }
}
</script>
```

---

## 10. CSS — Obsolete Patterns & Modern Replacements

### 10.1 Layout Evolution (Float → Flexbox → Grid)

CSS floats (and the associated clearfix hacks) were the dominant layout mechanism from ~2003–2015. They are replaced by purpose-built layout modules. **Float is not "deprecated" — it remains valid for floating text around images — but it must never be used for page layout.**

| Old Pattern | Issue | Modern Replacement |
|-------------|-------|-------------------|
| Float-based columns | Requires clearfix; fragile; doesn't support equal heights; not semantic | CSS Flexbox or Grid |
| Clearfix hack (`:after { content:""; display:table; clear:both; }`) | Workaround for a workaround | CSS Grid / Flexbox |
| `display: table` / `display: table-cell` | Tables for layout purposes | Flexbox / Grid / logical properties |
| Absolute positioning for column layout | Removes content from normal document flow; breaks reflow | Grid |
| `margin: 0 auto` with fixed widths only | Limited; doesn't adapt | `max-inline-size: 1200px; margin-inline: auto` |

**Flexbox — use when:**
- Distributing items along a **single axis** (either horizontal row or vertical column)
- Unknown or flexible item sizes need to grow/shrink
- Alignment of items within a container in one dimension

**CSS Grid — use when:**
- **Two-dimensional layout** (rows AND columns simultaneously)
- Creating page-level layout (header / sidebar / main / footer)
- Precise placement of items within a grid system

```css
/* ❌ OLD: Float columns with clearfix */
.container::after { content: ''; display: table; clear: both; }
.col { float: left; width: 33.33%; }

/* ✅ MODERN: CSS Grid for page layout */
.page-layout {
  display: grid;
  grid-template-columns: 240px 1fr 200px;
  grid-template-rows: auto 1fr auto;
  grid-template-areas:
    "header header header"
    "sidebar main aside"
    "footer footer footer";
  min-block-size: 100dvh;
  gap: 1.5rem;
}

.page-header { grid-area: header; }
.main-content { grid-area: main; }
.sidebar { grid-area: sidebar; }
.aside { grid-area: aside; }
.page-footer { grid-area: footer; }

/* ✅ MODERN: Responsive grid without media queries */
.card-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 1.5rem;
}

/* ✅ MODERN: Flexbox for a navigation bar */
.nav-list {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem 1rem;
  list-style: none;
  padding: 0;
  margin: 0;
  align-items: center;
}
```

---

### 10.2 Vendor Prefixes

Vendor prefixes (`-webkit-`, `-moz-`, `-ms-`, `-o-`) were used during the experimental phase of CSS features. Most modern browsers no longer require them for standard properties, but **some legacy prefixes remain relevant** while others are long obsolete.

**Fully obsolete — remove unconditionally:**

| Old (Prefixed) | Modern (No Prefix) |
|---------------|-------------------|
| `-webkit-box-shadow` | `box-shadow` |
| `-webkit-border-radius` | `border-radius` |
| `-webkit-transform` | `transform` |
| `-webkit-transition` | `transition` |
| `-webkit-animation` | `animation` |
| `-moz-border-radius` | `border-radius` |
| `-ms-flexbox` | `display: flex` |
| `-ms-grid` | `display: grid` |
| `-o-transform` | `transform` |

**Still occasionally needed (use autoprefixer; verify by target browser list):**

| Property | Status |
|----------|--------|
| `-webkit-appearance: none` | Still needed for some form elements in older Safari |
| `-webkit-text-size-adjust: 100%` | Prevents iOS Safari from auto-adjusting font sizes in landscape |
| `-webkit-font-smoothing: antialiased` | macOS Chrome/Safari font rendering (non-standard) |

**Best practice:** Use **Autoprefixer** (PostCSS plugin) with a `.browserslistrc` config that targets your actual users — do not manually manage prefixes. Run it as part of your CSS build pipeline.

---

### 10.3 CSS-in-JS Runtime vs. Zero-Runtime

**Runtime CSS-in-JS** (styled-components, Emotion in standard mode):
- Injects styles into DOM at runtime using JavaScript
- Adds JS bundle weight (~13–27kB gzipped per library)
- Causes FOUC (Flash of Unstyled Content) on initial load
- Increases hydration cost in SSR apps
- Cannot be preloaded/cached independently of JS

**Zero-Runtime CSS-in-JS / CSS Modules alternatives:**
- **CSS Modules** — scoped class names, compiled to plain CSS at build time; zero runtime cost
- **Linaria** — extracts CSS into static `.css` files at build time; styled-components syntax
- **Vanilla Extract** — TypeScript CSS-in-JS that outputs static CSS files
- **Pandacss / StyleX (Meta)** — build-time CSS generation from design tokens
- **Native CSS Custom Properties (`--var`) + CSS Cascade Layers** — achieve theming without JS

**Recommendation:** For new projects, default to CSS Modules or plain CSS with custom properties and container queries. CSS-in-JS runtime solutions are increasingly replaced by static extraction pipelines.

---

### 10.4 Modern CSS Selectors & Features

```css
/* :is() — matches elements against a selector list (reduces repetition) */
:is(h1, h2, h3, h4, h5, h6) a { color: inherit; }

/* :where() — same as :is() but specificity is always 0 */
:where(header, footer, nav) ul { list-style: none; }

/* :has() — "parent selector" — matches ancestor when descendant matches */
.card:has(img) { padding-block-start: 0; }
form:has(input:invalid) .submit-btn { opacity: 0.5; }

/* :not() with complex selector list */
a:not([href^="http"], [href^="mailto"]) { /* internal links only */ }

/* Container Queries — component-level responsive design */
.card-container {
  container-type: inline-size;
  container-name: card;
}

@container card (min-width: 400px) {
  .card-body { display: grid; grid-template-columns: 1fr 1fr; }
}

/* Logical Properties — writing-mode aware layout */
.element {
  margin-inline: auto;          /* horizontal margin (LTR: left/right) */
  padding-block: 1rem;          /* vertical padding (LTR: top/bottom) */
  border-inline-start: 2px solid; /* left border in LTR, right in RTL */
  inset-inline-start: 0;        /* replaces left: 0 in LTR */
  inline-size: 300px;           /* width in horizontal writing mode */
  block-size: auto;             /* height in horizontal writing mode */
}

/* CSS Custom Properties (CSS Variables) */
:root {
  --color-brand: hsl(220 80% 55%);
  --spacing-md: 1rem;
  --radius-md: 0.5rem;
  --font-base: 'Inter', system-ui, sans-serif;
}

@media (prefers-color-scheme: dark) {
  :root {
    --color-brand: hsl(220 80% 70%);
  }
}

/* CSS Cascade Layers — control specificity explicitly */
@layer reset, base, components, utilities, overrides;

@layer base {
  body { font-family: var(--font-base); }
}

@layer components {
  .btn { display: inline-flex; padding: 0.5rem 1rem; }
}

/* aspect-ratio — reserve space before media loads (prevents CLS) */
.video-wrapper {
  aspect-ratio: 16 / 9;
  overflow: hidden;
}

/* clamp() — fluid typography between bounds */
body {
  font-size: clamp(1rem, 0.875rem + 0.625vw, 1.25rem);
}

/* scroll-behavior */
@media (prefers-reduced-motion: no-preference) {
  html { scroll-behavior: smooth; }
}
```

---

### 10.5 CSS Accessibility Best Practices

```css
/* Focus indicators — visible for keyboard users, hidden for mouse */
:focus-visible {
  outline: 3px solid var(--color-brand);
  outline-offset: 3px;
  border-radius: var(--radius-sm, 2px);
}

/* Remove outline ONLY for mouse users via :focus (not :focus-visible) */
:focus:not(:focus-visible) {
  outline: none;
}

/* Visually hidden — for screen-reader-only text */
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0,0,0,0);
  white-space: nowrap;
  border-width: 0;
}

/* .sr-only.focusable — for skip links that appear on focus */
.sr-only.focusable:focus {
  position: static;
  width: auto;
  height: auto;
  overflow: visible;
  clip: auto;
  white-space: normal;
}

/* High contrast mode support (Windows forced colors) */
@media (forced-colors: active) {
  .custom-checkbox {
    forced-color-adjust: none;
    /* Or: let Windows manage colors for native-like consistency */
  }
}

/* Responsive typography: use rem (respects user's base font size) */
body { font-size: 1rem; } /* 16px by default — do NOT set px directly */
h1   { font-size: 2rem; }
/* Never: body { font-size: 12px; } — overrides user preferences */
```

---

### 10.6 Media Queries — Modern Discrete Features

```css
/* Color scheme */
@media (prefers-color-scheme: dark) { /* dark mode */ }
@media (prefers-color-scheme: light) { /* light mode */ }

/* Motion */
@media (prefers-reduced-motion: reduce) { /* disable animations */ }
@media (prefers-reduced-motion: no-preference) { /* animations OK */ }

/* Transparency */
@media (prefers-reduced-transparency: reduce) { /* avoid glassmorphism */ }

/* Contrast */
@media (prefers-contrast: more) { /* high contrast mode */ }
@media (prefers-contrast: less) { /* reduce contrast */ }

/* Pointer capabilities */
@media (pointer: coarse) { /* touchscreen: enlarge targets */ }
@media (pointer: fine) { /* mouse: smaller targets acceptable */ }
@media (hover: none) { /* no hover: avoid hover-only interactions */ }
@media (any-hover: hover) { /* at least one pointing device has hover */ }

/* Display mode (for PWAs) */
@media (display-mode: standalone) { /* running as installed app */ }
@media (display-mode: browser) { /* running in browser tab */ }

/* Print */
@media print {
  .no-print { display: none; }
  a[href]::after { content: " (" attr(href) ")"; } /* Show URLs */
}
```

---

### 10.7 CSS Stability Levels

Understanding W3C status helps gauge how confidently features can be used:

| Status | Abbreviation | Meaning |
|--------|-------------|---------|
| Working Draft | WD | In active development; may change significantly |
| Candidate Recommendation | CR | Stable enough; browser implementations being gathered |
| Proposed Recommendation | PR | Nearly final |
| W3C Recommendation | REC | **Stable standard** — safe for production |

**Always verify browser support** via [caniuse.com](https://caniuse.com/) and test against your `browserslist` targets before using WD-stage features in production.

---

## 11. JavaScript — Deprecated Patterns & Modern Replacements

### 11.1 Deprecated Global Functions & APIs

| Deprecated | Modern Replacement | Reason |
|-----------|-------------------|--------|
| `escape()` | `encodeURIComponent()` or `encodeURI()` | `escape()` does not handle non-ASCII properly; only works for ASCII |
| `unescape()` | `decodeURIComponent()` or `decodeURI()` | Same reason as above |
| `eval()` | Specific alternatives (e.g., JSON.parse for JSON) | Security (arbitrary code execution), performance (JIT can't optimize), maintainability |
| `document.write()` | DOM APIs: `createElement`, `innerHTML`, `insertAdjacentHTML` | Overwrites entire page after load; blocks rendering; breaks SSR; removed from some browser contexts (subframes) |
| `window.execScript()` | `eval()` (itself deprecated) → eliminate entirely | IE-only; modern engines removed support |
| `showModalDialog()` / `showModelessDialog()` | Custom modal dialogs with `<dialog>` element | Removed from Chrome 37+; IE-only |
| `navigator.appName`, `navigator.appVersion` | Feature detection (`typeof`, CSS `@supports`) | Returns misleading/identical values across all browsers |
| `with` statement | Explicit variable references | Ambiguous scope; disabled in strict mode |
| `arguments.caller` / `Function.caller` | Avoid; use explicit parameter passing | Strict mode disallows; prevents JIT optimization |
| `DOMSubtreeModified` event | `MutationObserver` | Deprecated mutation events fire synchronously (severe performance impact) |
| `hashchange` + `pushState` for routing | History API (`pushState`, `replaceState`, `popstate`) | Direct state manipulation without hash patterns |
| `setInterval` for polling | `MutationObserver`, `IntersectionObserver`, Server-Sent Events, WebSocket | Polling wastes CPU; observers are event-driven |

---

### 11.2 Asynchronous Patterns

**Evolution:** Callbacks → Promises → async/await

```javascript
// ❌ OLD: Callback Hell / Pyramid of Doom
loadUser(id, function(user) {
  loadPosts(user.id, function(posts) {
    loadComments(posts[0].id, function(comments) {
      // Nested 4+ levels deep → unmaintainable
    }, handleError);
  }, handleError);
}, handleError);

// ❌ MEDIOCRE: Promise chaining (better, but still verbose)
loadUser(id)
  .then(user => loadPosts(user.id))
  .then(posts => loadComments(posts[0].id))
  .then(comments => displayComments(comments))
  .catch(handleError);

// ✅ MODERN: async/await — reads like synchronous code
async function loadUserContent(id) {
  try {
    const user = await loadUser(id);
    const posts = await loadPosts(user.id);
    const comments = await loadComments(posts[0].id);
    displayComments(comments);
  } catch (error) {
    handleError(error);
  }
}

// ✅ MODERN: Parallel async operations (don't await sequentially when independent)
async function loadAll(userId) {
  const [user, settings, notifications] = await Promise.all([
    fetchUser(userId),
    fetchSettings(userId),
    fetchNotifications(userId)
  ]);
  return { user, settings, notifications };
}

// ✅ MODERN: Promise.allSettled — when some can fail
const results = await Promise.allSettled([fetch('/api/a'), fetch('/api/b')]);
results.forEach(result => {
  if (result.status === 'fulfilled') console.log(result.value);
  else console.warn('Failed:', result.reason);
});

// ✅ MODERN: Promise.race for timeouts
const controller = new AbortController();
const timeout = setTimeout(() => controller.abort(), 5000);
try {
  const response = await fetch('/api/data', { signal: controller.signal });
} finally {
  clearTimeout(timeout);
}
```

---

### 11.3 Memory Management — WeakRef & FinalizationRegistry

Classic closures and event listeners can prevent garbage collection. ES2021 introduced `WeakRef` and `FinalizationRegistry` for memory-conscious referencing.

```javascript
// WeakRef: holds a weak reference that doesn't prevent GC
class CacheWithWeakRef {
  #cache = new Map();

  set(key, value) {
    this.#cache.set(key, new WeakRef(value));
  }

  get(key) {
    const ref = this.#cache.get(key);
    if (!ref) return undefined;
    const value = ref.deref(); // Returns value or undefined if GC'd
    if (!value) this.#cache.delete(key); // Cleanup stale entry
    return value;
  }
}

// FinalizationRegistry: run cleanup when object is GC'd
const registry = new FinalizationRegistry((heldValue) => {
  console.log(`Object with key ${heldValue} was garbage collected`);
  // Cleanup resources associated with heldValue
});

let target = { data: 'something expensive' };
registry.register(target, 'my-resource-key');
target = null; // When GC collects original target, registry callback fires
```

---

### 11.4 The Temporal API

The `Date` object is notorious for its design flaws: it uses 0-indexed months, mutates in place, has no timezone support, and its parsing behavior is inconsistent across browsers. The **Temporal API** (TC39 Stage 3, shipping in browsers) provides a complete, immutable replacement.

```javascript
// ❌ OLD: JavaScript Date — infamous problems
const date = new Date(2026, 0, 15); // Month is 0-indexed! January = 0
const epoch = Date.now(); // OK for timestamps, confusing for dates
const isoString = new Date().toISOString(); // Converts to UTC silently
new Date('2026-03-01') // UTC midnight; new Date('03/01/2026') → local midnight
// — different results depending on format!

// ✅ NEW: Temporal API
const today = Temporal.Now.plainDateISO();            // 2026-03-01
const dateTime = Temporal.Now.plainDateTimeISO();     // 2026-03-01T09:30:00
const withTZ = Temporal.Now.zonedDateTimeISO();       // 2026-03-01T09:30:00+05:30[Asia/Kolkata]

// Specific date — months are 1-indexed (January = 1)
const meeting = Temporal.PlainDate.from({ year: 2026, month: 3, day: 15 });

// Duration arithmetic
const inTwoWeeks = today.add({ weeks: 2 });
const duration = Temporal.PlainDate
  .from('2026-01-01')
  .until(today); // Temporal.Duration
console.log(duration.days); // Number of days since Jan 1

// Timezone-aware datetime
const zonedDT = Temporal.ZonedDateTime.from('2026-03-15T09:00:00[America/New_York]');
const inKolkata = zonedDT.withTimeZone('Asia/Kolkata');

// Immutable — all operations return new instances
const tomorrow = today.add({ days: 1 }); // today is unchanged
```

---

### 11.5 Keyboard & DOM Events

```javascript
// ❌ DEPRECATED: keyCode and which
document.addEventListener('keydown', (e) => {
  if (e.keyCode === 27) closeModal(); // keyCode is deprecated
  if (e.which === 27) closeModal();   // which is deprecated
});

// ✅ MODERN: key property
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') closeModal();
  if (e.key === 'Enter') confirmAction();
  if (e.key === 'ArrowDown') navigateDown();
});

// ❌ DEPRECATED: document.createEvent + initEvent
const event = document.createEvent('Event');
event.initEvent('myevent', true, true);
element.dispatchEvent(event);

// ✅ MODERN: CustomEvent constructor
element.dispatchEvent(new CustomEvent('myevent', {
  bubbles: true,
  cancelable: true,
  detail: { userId: 123, action: 'click' }
}));

// ❌ DEPRECATED: addEventlistener with third arg as boolean (for capture)
el.addEventListener('click', handler, true); // Legit, but prefer object form

// ✅ MODERN: addEventListener with options object
el.addEventListener('click', handler, {
  capture: false,   // Bubble phase (default)
  once: true,       // Auto-removes after first call
  passive: true,    // Will not call preventDefault(); allows scroll optimization
  signal: controller.signal  // AbortController — for cleanup
});

// ❌ DEPRECATED: Event listener cleanup with exact function reference
const handler = () => {};
el.addEventListener('click', handler);
el.removeEventListener('click', handler); // Must keep reference

// ✅ MODERN: AbortController for event cleanup
const controller = new AbortController();
el.addEventListener('click', handler, { signal: controller.signal });
el.addEventListener('keydown', otherHandler, { signal: controller.signal });
// Remove all at once:
controller.abort();
```

---

### 11.6 Code Splitting & Dynamic Import

```javascript
// Static import (always loaded, at module parse time)
import { utilityFunction } from './utils.js';

// Dynamic import (loaded only when needed — code splitting)
// 1. Load heavy components conditionally
async function loadChart() {
  if (!document.querySelector('[data-chart]')) return;
  const { initChart } = await import('./chart.js');
  initChart();
}

// 2. Route-based code splitting (SPA)
const routes = {
  '/dashboard': () => import('./pages/Dashboard.js'),
  '/settings': () => import('./pages/Settings.js'),
};

async function navigate(path) {
  const module = await routes[path]?.();
  module?.default?.render(document.getElementById('app'));
}

// 3. Conditional loading based on features
if ('showPicker' in HTMLInputElement.prototype) {
  const { ColorPickerEnhancer } = await import('./color-picker-enhanced.js');
  new ColorPickerEnhancer();
}
```

---

### 11.7 Web Workers & Main Thread Relief

Web Workers run JavaScript in a background thread, completely separate from the main thread, enabling heavy computation without blocking the UI.

```javascript
// worker.js
self.addEventListener('message', (e) => {
  const { data, type } = e.data;
  if (type === 'PROCESS') {
    const result = heavyComputation(data); // Runs off main thread
    self.postMessage({ type: 'RESULT', result });
  }
});

function heavyComputation(data) {
  // Sort, filter, transform large arrays, crypto, image manipulation
  return data.map(item => item * 2).filter(n => n > 100);
}

// main.js
const worker = new Worker('/worker.js', { type: 'module' });

worker.postMessage({ type: 'PROCESS', data: largeArray });

worker.addEventListener('message', (e) => {
  if (e.data.type === 'RESULT') {
    displayResult(e.data.result);
    worker.terminate(); // Cleanup when done
  }
});

// transferable objects (zero-copy data transfer)
const buffer = new ArrayBuffer(1024 * 1024); // 1MB
worker.postMessage({ buffer }, [buffer]); // Transfer ownership, not copy
```

---

### 11.8 Scheduler API & INP Optimization

Long tasks (>50ms) block the main thread and cause high INP. Break them up:

```javascript
// ✅ scheduler.yield() — yields to browser between chunks
async function processLargeDataset(items) {
  const CHUNK_SIZE = 50;
  for (let i = 0; i < items.length; i++) {
    processItem(items[i]);
    if (i % CHUNK_SIZE === 0) {
      // Yield: allows browser to handle events, paint, etc.
      await scheduler.yield?.() ??
            new Promise(resolve => setTimeout(resolve, 0));
    }
  }
}

// ✅ requestIdleCallback — run non-critical work during browser idle
requestIdleCallback((deadline) => {
  while (deadline.timeRemaining() > 0 && tasks.length > 0) {
    const task = tasks.shift();
    task.run();
  }
  if (tasks.length > 0) requestIdleCallback(arguments.callee);
}, { timeout: 2000 }); // timeout: fallback if browser never idles

// ✅ requestAnimationFrame — synchronize with browser paint cycles
function animateFrame(timestamp) {
  updateAnimation(timestamp);
  requestAnimationFrame(animateFrame);
}
requestAnimationFrame(animateFrame);

// Note: Never use setInterval for animations — not synced to display refresh
// Note: Never use setTimeout(fn, 0) for animations — off-phase with paint
```

---

## 12. Media Optimization — Images

### 12.1 Format Hierarchy 2026

Image format selection is one of the highest-impact performance decisions. The hierarchy is driven by compression efficiency (smaller bytes = faster load = better LCP), breadth of browser support, and HDR/wide-gamut capabilities.

| Format | Compression Type | Typical Size vs. JPEG | Browser Support | Best For | Encoder |
|--------|-----------------|----------------------|----------------|---------|---------|
| **AVIF** | AV1 (lossy + lossless) | **40–60% smaller** than JPEG for equivalent quality | Chrome 85+, Firefox 93+, Safari 16+, Edge 121+ | Hero images, product photos, portraits — any high-quality image | Squoosh, ImageMagick 7.1+, libavif, sharp (Node), Cloudinary |
| **WebP** | Hybrid (lossy + lossless) | **25–40% smaller** than JPEG | All modern browsers; IE11 excluded | All images where AVIF isn't supported; good universal fallback | cwebp CLI, Squoosh, ImageMagick, Cloudinary |
| **JPEG XL** | Advanced lossless/lossy | **Up to 60% smaller** vs. JPEG; better than AVIF in some cases | Chrome paused support in 2022 (re-evaluating); Firefox 90+ (flag) | Future-proof; not yet production-ready for broad support | libjxl, Squoosh |
| **SVG** | Vector (XML) | N/A — vector, infinitely scalable | Universal | Logos, icons, illustrations, diagrams, charts — anything that needs to scale |  |
| **PNG** | Lossless | Larger than JPEG/AVIF | Universal | Transparency, UI screenshots, pixel-accurate graphics |  |
| **JPEG/JPG** | Lossy DCT | Baseline | Universal | **Fallback only** — always serve AVIF/WebP first |  |
| **GIF** | Lossless (but palette-limited) | Much larger than WebM video for animation | Universal | **Deprecated for animations** — replace with `<video autoplay muted loop>` |  |

**Decision flowchart:**
```
Does the image have animation? → YES → Replace with <video> (see §12.5)
                               → NO  → Does it need transparency and is complex/photographic?
                                          → YES → WebP (lossless) or PNG as fallback
                                          → NO  → Is it a logo/icon/simple graphic?
                                                    → YES → SVG (preferred), PNG (if raster)
                                                    → NO  → AVIF → WebP → JPEG (fallback stack)
```

**Target quality settings (AVIF/WebP):**
- **AVIF:** Quality 60–75 for photos (out of 100), quality 85+ for text-heavy/sharp-edge images
- **WebP:** Quality 75–85 for photos (out of 100)
- Always run automated quality comparisons with DSSIM or Butteraugli tools to verify no visible quality degradation

---

### 12.2 The `<picture>` Element — Format Fallbacks & Art Direction

The `<picture>` element is the native HTML mechanism for:
1. **Format fallbacks** — offer AVIF first, WebP second, JPEG as universal fallback
2. **Art direction** — serve visually different crops/framing at different viewport sizes (not just scaled versions)

**Browser processing behavior:** The browser reads `<source>` elements top to bottom and uses the **first one it supports**. The `<img>` inside `<picture>` is the fallback for older browsers and also provides the `alt`, `width`, `height`, `loading`, `decoding`, and `fetchpriority` attributes.

```html
<!-- Format fallbacks: AVIF → WebP → JPEG -->
<picture>
  <source type="image/avif"
          srcset="hero-400.avif 400w, hero-800.avif 800w, hero-1200.avif 1200w"
          sizes="(max-width: 600px) 100vw, (max-width: 1024px) 80vw, 1200px">
  <source type="image/webp"
          srcset="hero-400.webp 400w, hero-800.webp 800w, hero-1200.webp 1200w"
          sizes="(max-width: 600px) 100vw, (max-width: 1024px) 80vw, 1200px">
  <img src="hero-1200.jpg"
       srcset="hero-400.jpg 400w, hero-800.jpg 800w, hero-1200.jpg 1200w"
       sizes="(max-width: 600px) 100vw, (max-width: 1024px) 80vw, 1200px"
       alt="Descriptive alt text for the hero image"
       width="1200"
       height="630"
       loading="eager"
       fetchpriority="high"
       decoding="async">
</picture>

<!-- Art direction: different crop at mobile vs desktop -->
<picture>
  <!-- Mobile: portrait crop (shows face / focal content close in) -->
  <source media="(max-width: 600px)"
          type="image/avif"
          srcset="product-portrait-360.avif 360w, product-portrait-720.avif 720w">
  <source media="(max-width: 600px)"
          type="image/webp"
          srcset="product-portrait-360.webp 360w, product-portrait-720.webp 720w">
  <!-- Desktop: wide landscape shot showing context -->
  <source type="image/avif"
          srcset="product-landscape-800.avif 800w, product-landscape-1600.avif 1600w">
  <source type="image/webp"
          srcset="product-landscape-800.webp 800w, product-landscape-1600.webp 1600w">
  <img src="product-landscape-1600.jpg"
       alt="[Product Name] shown in a kitchen setting"
       width="1600" height="900"
       loading="lazy"
       decoding="async">
</picture>
```

---

### 12.3 `srcset` & `sizes` Explained

**`srcset`** — tells the browser about the image candidates and their widths (in pixels):
```html
srcset="image-400.jpg 400w, image-800.jpg 800w, image-1200.jpg 1200w"
```
- `400w` = this source is 400 CSS pixels wide when displayed at 1x
- `1x`, `2x`, `3x` (pixel density descriptors) — use for fixed-size images like logos/icons; use `w` descriptors for responsive images

**`sizes`** — tells the browser **how wide the image will be displayed** at different viewport widths (so it can choose the correct `srcset` candidate before downloading):
```html
sizes="(max-width: 480px) 100vw,
       (max-width: 768px) 80vw,
       (min-width: 1200px) 600px,
       50vw"
```
- Conditions are evaluated left-to-right; first matching condition wins
- The last value is the default (no media condition)
- Units allowed: `vw`, `px`, `em`, `calc()`; percentage (`%`) is **not** allowed

**How the browser selects a candidate:**
1. Evaluate `sizes` to determine layout width for the current viewport (e.g., 100vw)
2. Multiply by device pixel ratio (DPR, e.g., 2.0 for Retina)
3. Select the `srcset` candidate closest to (and ≥) that effective width

**When `srcset`/`sizes` is not needed:**
- SVG (scales infinitely)
- Small, fixed-size UI images (icon at 24×24px — use `2x` density descriptor if needed)
- CSS `background-image` (use `image-set()` function instead)

```css
/* CSS background-image with format selection */
.hero {
  background-image: image-set(
    url("hero.avif") type("image/avif"),
    url("hero.webp") type("image/webp"),
    url("hero.jpg")  type("image/jpeg")
  );
}
```

---

### 12.4 Critical Image Attributes

Every `<img>` element must have ALL of the following:

| Attribute | Required? | Value | Purpose |
|-----------|-----------|-------|---------|
| `src` | Always | Path or URL | Source URL |
| `alt` | Always | Descriptive string, or `""` for purely decorative | Accessibility; also shown if image fails to load |
| `width` | Always (for web images) | Integer CSS pixels | Prevents CLS; browser reserves space before image loads |
| `height` | Always (for web images) | Integer CSS pixels | Used with `width` to calculate `aspect-ratio` intrinsically |
| `loading` | On all below-fold images | `lazy` / `eager` (default) | LCP: `eager`; all below-fold: `lazy` |
| `decoding` | Always | `async` (recommended) / `sync` / `auto` | `async` decodes image off main thread, preventing jank |
| `fetchpriority` | On LCP/hero image only | `high` / `low` / `auto` | Signals priority to browser's preload scanner |

**`alt` attribute rules:**
- **Informative images:** describe what the image conveys (not just "image of..." — start with the content description)
- **Functional images (links/buttons):** describe the action/destination
- **Decorative images:** `alt=""` (empty string, not omitted) — AT skips it entirely
- **Complex images (charts, diagrams):** brief `alt` + extended description via `aria-describedby` or visible caption
- **Images of text:** the exact text in the image
- **Never:** `alt="image"`, `alt="photo"`, `alt="graphic"`, or the filename

```html
<!-- ✅ LCP Hero image — set up for optimal performance -->
<img src="hero.jpg"
     srcset="hero-400.jpg 400w, hero-800.jpg 800w, hero-1200.jpg 1200w"
     sizes="100vw"
     alt="A panoramic view of the Himalayan mountain range at sunrise"
     width="1200"
     height="630"
     loading="eager"
     fetchpriority="high"
     decoding="async">

<!-- ✅ Below-fold content image -->
<img src="team.jpg"
     alt="The 12-person engineering team at our office in Ahmedabad"
     width="800"
     height="450"
     loading="lazy"
     decoding="async">

<!-- ✅ Decorative divider or background graphic -->
<img src="wave-divider.svg" alt="" width="1440" height="80" aria-hidden="true">

<!-- ✅ Icon inside button — icon is decorative, button has visible text -->
<button type="button">
  <svg aria-hidden="true" focusable="false" width="16" height="16"><!-- ... --></svg>
  Download PDF
</button>

<!-- ✅ Icon-only button — must provide accessible name -->
<button type="button" aria-label="Download PDF">
  <svg aria-hidden="true" focusable="false" width="24" height="24"><!-- ... --></svg>
</button>
```

**Responsive images with intrinsic aspect ratios (CSS):**
```css
/* Modern approach: let browser use native aspect-ratio from width/height attrs */
img {
  max-width: 100%;
  height: auto; /* Combined with explicit width/height attrs, preserves ratio */
}

/* Or use aspect-ratio directly (for cases without explicit dimensions) */
.thumbnail {
  aspect-ratio: 4 / 3;
  object-fit: cover;
  width: 100%;
}
```

---

### 12.5 GIF Replacement with Video

Animated GIFs are massive, non-accessible, and visually poor:
- A 5-second animation: GIF ≈ 1.5MB vs. WebM ≈ 150–250KB (80–90% smaller)
- GIFs cannot be paused and respect no motion preferences without JS
- GIFs have no audio track management, no keyboard controls

**Replace animated GIFs with `<video>`:**

```html
<!-- ✅ Replacing an animated GIF with <video> -->
<!-- The key: autoplay muted loop playsinline -->
<!-- Decorative animation (aria-hidden hides from AT) -->
<video autoplay muted loop playsinline
       width="600" height="400"
       aria-hidden="true"
       style="display: block; max-width: 100%; height: auto;">
  <source src="animation.av1.webm" type="video/webm; codecs=av1">
  <source src="animation.vp9.webm" type="video/webm; codecs=vp9">
  <source src="animation.mp4" type="video/mp4">
</video>

<!-- ✅ If the animation is informative, provide an accessible label -->
<video autoplay muted loop playsinline
       aria-label="Step-by-step demonstration of drag-and-drop feature"
       width="640" height="360">
  <source src="demo.av1.webm" type="video/webm; codecs=av1">
  <source src="demo.mp4" type="video/mp4">
</video>
```

**Attributes explained:**
- `autoplay` — required for GIF-like behavior; modern browsers require `muted` for autoplay to work
- `muted` — required for autoplay; no sound recorded in GIF-replacement videos
- `loop` — replays continuously like a GIF
- `playsinline` — prevents iOS Safari from going fullscreen automatically
- `aria-hidden="true"` — if purely decorative

**`prefers-reduced-motion` with video:**
```css
@media (prefers-reduced-motion: reduce) {
  video[autoplay] {
    animation: none; /* doesn't directly pause video but sets expectation */
  }
}
```
```javascript
// Pause autoplay videos for users who prefer reduced motion
const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
if (reduceMotion.matches) {
  document.querySelectorAll('video[autoplay]').forEach(v => v.pause());
}
reduceMotion.addEventListener('change', (e) => {
  document.querySelectorAll('video[autoplay]').forEach(v => {
    e.matches ? v.pause() : v.play();
  });
});
```

---

## 13. Video Codecs & Containers

### 13.1 Codec Comparison Table

| Codec | Container | vs. H.264 | Browser Support | Use Case |
|-------|-----------|-----------|----------------|---------|
| **H.264 (AVC)** | MP4 | Baseline | Universal (all browsers, all devices) | **Always include as final fallback** — guaranteed compatibility |
| **H.265 (HEVC)** | MP4 | ~40–50% smaller | Safari 11+, Edge partial, Chrome limited | 4K/HDR Apple ecosystem content; NOT royalty-free |
| **VP9** | WebM | ~30–50% smaller | Chrome, Firefox, Edge, limited Safari | YouTube standard; good open-source option; no royalties |
| **AV1** | WebM or MP4 | ~50% smaller | Chrome 85+, Firefox 93+, Edge 121+; hardware decode growing | Best compression; royalty-free; future-proof; **slow to encode** |

**Recommended codec stack (use `<source>` for fallback ordering):**

```html
<video width="1280" height="720"
       controls
       preload="metadata"
       poster="thumbnail.jpg"
       aria-label="Product feature walkthrough — 3 minutes">

  <!-- Best: AV1 in WebM -->
  <source src="video.av1.webm" type="video/webm; codecs=av1">

  <!-- Good fallback: VP9 in WebM -->
  <source src="video.vp9.webm" type="video/webm; codecs=vp9">

  <!-- Universal fallback: H.264 in MP4 -->
  <source src="video.mp4" type="video/mp4">

  <!-- Fallback text for browsers without video support (rare) -->
  <p>Your browser doesn't support HTML5 video.
     <a href="video.mp4" download>Download the video (MP4)</a>
  </p>

</video>
```

### 13.2 Video Performance Best Practices

| Attribute | Value | Effect |
|-----------|-------|--------|
| `preload` | `none` | Browser fetches nothing until user clicks play — best for pages with many videos |
| `preload` | `metadata` | Only fetches metadata (duration, dimensions, first frame) — **recommended default for non-autoplay** |
| `preload` | `auto` | Browser decides how much to download — risky on mobile data |
| `poster` | URL of thumbnail | Shows a static frame before playback — prevents "black screen" flash; required for good UX |
| `controls` | Boolean | Shows native browser controls — accessible by default; keyboard operable |
| `autoplay` | Boolean | Only works with `muted` in modern browsers; use only for background decorative videos |
| `playsinline` | Boolean | Required for iOS Safari to allow inline (non-fullscreen) playback |
| `disablepictureinpicture` | Boolean | Hides PiP button (use only when legally or UX-required) |

### 13.3 Accessibility for Videos

- **Captions/Subtitles:** Required (WCAG SC 1.2.2) for all prerecorded video with audio — use `<track kind="captions">` or third-party caption solutions
- **Audio descriptions:** Required (WCAG SC 1.2.5) if video conveys information not in the audio track
- **Transcript:** Strongly recommended for all video; required at AAA level (WCAG SC 1.2.8)

```html
<video controls width="1280" height="720" poster="poster.jpg">
  <source src="video.av1.webm" type="video/webm; codecs=av1">
  <source src="video.mp4" type="video/mp4">

  <!-- Captions (for deaf/hard-of-hearing; same language as audio) -->
  <track kind="captions"
         src="captions-en.vtt"
         srclang="en"
         label="English"
         default>

  <!-- Subtitles (translation — different language) -->
  <track kind="subtitles"
         src="subtitles-fr.vtt"
         srclang="fr"
         label="French">

  <!-- Audio descriptions (for blind/visually impaired) -->
  <track kind="descriptions"
         src="audio-desc-en.vtt"
         srclang="en"
         label="English Audio Description">

  <!-- Chapters (for navigation in longer videos) -->
  <track kind="chapters"
         src="chapters-en.vtt"
         srclang="en"
         label="Chapters">
</video>
```

**WebVTT (`.vtt`) caption file format:**
```
WEBVTT

00:00:01.000 --> 00:00:04.000
Welcome to the product demonstration.

00:00:05.500 --> 00:00:09.000
Today we'll explore the three key features of our dashboard.
```

---

## 14. Resource Hints & Critical Path

### 14.1 The Critical Rendering Path

The browser follows this sequence before pixels are displayed:
```
Fetch HTML → Parse HTML → Discover Resources → Fetch CSS/JS
→ Parse CSS (build CSSOM) → Execute blocking JS
→ Combine DOM + CSSOM → Render Tree → Layout → Paint → Composite
```

Any **render-blocking resource** delays this pipeline. Every millisecond of delay after 2.5s risks a failing LCP score.

| Resource Type | Default Behavior | Optimization |
|--------------|-----------------|-------------|
| `<link rel="stylesheet">` | **Render-blocking** — browser pauses paint until CSS is parsed | Inline critical CSS; load non-critical CSS asynchronously |
| `<script>` in `<head>` without attributes | **Parser-blocking AND render-blocking** | Always add `defer` or `async` |
| `<script defer>` | Downloads in parallel; executes in **source order** after HTML is fully parsed, before DOMContentLoaded | Best for most scripts; safe dependency ordering |
| `<script async>` | Downloads in parallel; executes **immediately** when downloaded (no order guarantee) | Use for independent 3rd-party scripts (analytics, chat widgets) |
| `<script type="module">` | **Implicitly deferred** — behaves like `defer`; also enables ES Modules | Use for all modern JS modules |
| Web fonts | Can cause FOIT (3s invisible text) or FOUT (flash of unstyled text) | `font-display: swap`; preconnect to font CDN; self-host when possible |
| Images | Non-blocking; consumes bandwidth | Lazy load below-fold; compress; modern formats; explicit dimensions |

---

### 14.2 Resource Hints Reference Table

Resource hints allow the browser to make early connections or downloads for resources it would otherwise discover late.

| Hint | What It Does | Priority | When to Use | Key Caution |
|------|-------------|----------|------------|------------|
| `dns-prefetch` | Resolves DNS for a cross-origin domain (no TCP/TLS) | Very Low | For domains you'll connect to later; as fallback for `preconnect` | Nearly free cost; useful for CDN domains, analytics |
| `preconnect` | DNS + TCP + TLS handshake for cross-origin | Medium | Critical 3rd-party resources needed soon: font CDN, API origin, image CDN | Can reduce latency by 100–500ms; use only 2–3 per page; Chrome closes unused connections after 10s; each has ~3KB TLS overhead |
| `preload` | Downloads resource at **high priority** without executing it | High | Font files, LCP image, critical JS needed by other scripts — resources the browser discovers too late | **NOT a hint — a mandatory directive.** Unused preloads show DevTools warning and waste bandwidth. Limit to 2–3 per page |
| `prefetch` | Downloads resource at **low priority** for likely future navigation | Low (idle) | Resources needed on the next likely page in user's journey | Safari support limited; can waste data if user never navigates there |
| `modulepreload` | Preloads a JS module AND all its static imports | High | Critical ES modules | Chrome-only preference; module-graph aware |
| `prerender` (Speculation Rules) | Pre-renders the next page in a hidden tab | Background | When highly confident of user's next action (e.g., step 2 of checkout) | Very heavy — full page render; use Speculation Rules API for eagerness control |

**Correct implementation:**

```html
<head>
  <!-- dns-prefetch: free DNS for domains used later -->
  <link rel="dns-prefetch" href="https://analytics.example.com">
  <link rel="dns-prefetch" href="https://cdn.fonts.example.com">

  <!-- preconnect: critical 3rd-party origins needed immediately -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <!-- crossorigin required for CORS origins (font files) -->
  <!-- Fallback dns-prefetch for older browsers -->
  <link rel="dns-prefetch" href="https://fonts.gstatic.com">

  <!-- preload: critical font (always crossorigin for fonts) -->
  <link rel="preload"
        href="/fonts/Inter-Variable.woff2"
        as="font"
        type="font/woff2"
        crossorigin>

  <!-- preload: LCP image (responsive srcset-aware) -->
  <link rel="preload"
        as="image"
        imagesrcset="hero-400.avif 400w, hero-800.avif 800w, hero-1200.avif 1200w"
        imagesizes="(max-width: 600px) 100vw, (max-width: 1024px) 80vw, 1200px"
        fetchpriority="high">

  <!-- preload: critical CSS -->
  <link rel="preload" href="/styles/critical.css" as="style">

  <!-- preload: critical JS module -->
  <link rel="preload" href="/js/app.js" as="script">

  <!-- prefetch: next-page resources (low priority) -->
  <link rel="prefetch" href="/products" as="document">
</head>

<!-- Speculation Rules API (Chrome 108+) for declarative prerendering -->
<script type="speculationrules">
{
  "prerender": [
    {
      "source": "document",
      "where": { "href_matches": "/checkout/*" },
      "eagerness": "moderate"
    }
  ],
  "prefetch": [
    {
      "source": "document",
      "where": {
        "and": [
          { "href_matches": "/*" },
          { "not": { "href_matches": "/admin/*" } }
        ]
      },
      "eagerness": "conservative"
    }
  ]
}
</script>
```

---

### 14.3 Script Loading Strategies

```html
<!-- ❌ WORST: Synchronous script in <head> — parser-blocking + render-blocking -->
<head>
  <script src="app.js"></script>
</head>

<!-- ✅ CORRECT: defer — downloads concurrently; executes in order after HTML parsed -->
<head>
  <script src="app.js" defer></script>
  <script src="ui.js" defer></script>
  <!-- Both execute in < source order, after HTML is fully parsed -->
  <!-- DOMContentLoaded fires after all defer scripts execute -->
</head>

<!-- ✅ CORRECT: async — for independent 3rd-party scripts (no DOM dependency) -->
<head>
  <script src="https://analytics.acme.com/track.js" async></script>
  <!-- Executes as soon as downloaded; order not guaranteed -->
</head>

<!-- ✅ CORRECT: type="module" — implicitly deferred; supports static imports -->
<script type="module" src="/js/main.js"></script>
<!-- Note: modules are always deferred; adding defer is redundant -->

<!-- Critical CSS inline + async load for non-critical styles -->
<style>
  /* Inline only above-the-fold styles */
  body { margin: 0; font-family: system-ui, sans-serif; }
  .hero { min-height: 80vh; }
  .header { position: sticky; top: 0; }
</style>
<link rel="preload" href="/styles/full.css" as="style"
      onload="this.onload=null;this.rel='stylesheet'">
<noscript>
  <link rel="stylesheet" href="/styles/full.css">
</noscript>
```

**`defer` vs. `async` — when to use which:**

| Scenario | Use |
|----------|-----|
| Main app logic that depends on DOM or other scripts | `defer` |
| UI framework initialization (React, Vue, etc.) | `defer` |
| Analytics, chat widgets, ads (independent, no DOM dependency) | `async` |
| ES Modules via bundler | `type="module"` (implicit defer) |
| Critical polyfills needed before app code | Inline in `<head>` (blocking — unavoidable) |

---

### 14.4 Critical CSS & Font Optimization

**Font performance:**

```css
/* Self-hosted variable font — best performance */
@font-face {
  font-family: 'Inter';
  src: url('/fonts/Inter.var.woff2') format('woff2');
  font-weight: 100 900;      /* Variable font weight range */
  font-style: normal;
  font-display: swap;        /* Show fallback immediately; swap when loaded */
  unicode-range: U+0000-00FF, U+0131, U+0152-0153; /* Subset to needed chars */
}

/* font-display values: */
/* auto     : browser default (usually block) */
/* block    : 3s invisible text (FOIT), then swap — avoid */
/* swap     : fallback immediately, swap when ready — recommended */
/* fallback : 100ms blank, 3s swap window, then stays with fallback forever */
/* optional : 100ms blank; if font not cached, abandons and uses fallback */
/*            Best for non-essential decorative fonts */
```

**`content-visibility` & `contain-intrinsic-size`:**

```css
/* Skip rendering of off-screen sections until they're near the viewport */
/* Can improve initial render time by 20-40% on long pages */
.below-fold-section {
  content-visibility: auto;
  /* Estimate the section height to prevent scroll jumps */
  contain-intrinsic-size: 0 800px;
  /* Or use auto keyword (Chrome 112+, remembers actual size) */
  contain-intrinsic-size: auto 800px;
}

/* containment — help browser optimize paint and layout calculations */
.isolated-widget {
  contain: layout;    /* No child can affect layout outside this element */
  contain: paint;     /* No child painted outside this element */
  contain: strict;    /* size + layout + style + paint */
  contain: content;   /* layout + style + paint (excludes size) */
}
```

---

### 14.5 Priority Hints — `fetchpriority`

The `fetchpriority` attribute gives the browser explicit signals about resource importance, overriding its heuristic-based priority queuing.

| Value | Effect |
|-------|--------|
| `high` | Fetch more aggressively; deprioritize other resources | 
| `low` | Fetch with reduced priority; yield to more important resources |
| `auto` | Default — browser decides based on resource type and context |

**Where to use:**

```html
<!-- LCP image: boost priority so browser fetches before other images -->
<img src="hero.jpg" fetchpriority="high" loading="eager" ...>

<!-- Below-fold images: explicitly deprioritize -->
<img src="gallery-thumb.jpg" fetchpriority="low" loading="lazy" ...>

<!-- Non-critical 3rd-party script before DOMContentLoaded -->
<script src="analytics.js" fetchpriority="low" async></script>

<!-- Critical API call in JS -->
fetch('/api/critical-data', { priority: 'high' });

<!-- Non-critical prefetch request -->
fetch('/api/recommendations', { priority: 'low' });
```

---

## 15. Observer APIs

The Observer APIs replace inefficient legacy patterns (polling, scroll/resize event listeners) with efficient, callback-driven, browser-native mechanisms that fire asynchronously off the main thread (where possible).

### 15.1 IntersectionObserver

**Replaces:** `scroll` event listeners with `getBoundingClientRect()` polling (catastrophically expensive — called 60 times/second, forces layout/reflow on every call).

**Use cases:** Lazy loading, infinite scroll, scroll-triggered animations, ad impression tracking, auto-pause off-screen videos.

```javascript
// Complete IntersectionObserver API reference
const observer = new IntersectionObserver(
  (entries, observer) => {
    entries.forEach(entry => {
      // entry.isIntersecting    — boolean: is any part in viewport?
      // entry.intersectionRatio — 0.0–1.0: fraction of element visible
      // entry.boundingClientRect — element size/position
      // entry.intersectionRect  — visible portion's rect
      // entry.rootBounds        — root (viewport) dimensions
      // entry.target            — the observed DOM element
      // entry.time              — DOMHighResTimeStamp of observation

      if (entry.isIntersecting) {
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target); // Stop observing; one-time animation
      }
    });
  },
  {
    root: null,              // null = browser viewport; or any scrollable element
    rootMargin: '0px 0px -100px 0px', // Shrink hitting area by 100px from bottom
    threshold: [0, 0.25, 0.5, 0.75, 1.0] // Fire at each of these ratios
  }
);

// Observe multiple elements
document.querySelectorAll('.animate-on-scroll').forEach(el => {
  observer.observe(el);
});

// Instance methods
observer.observe(element);     // Start watching an element
observer.unobserve(element);   // Stop watching one element
observer.disconnect();         // Stop all observations
observer.takeRecords();        // Consume pending (undelivered) entries

// ✅ PRACTICAL: Lazy load images via IntersectionObserver (JS fallback for no-native-lazy)
const imageObserver = new IntersectionObserver(
  (entries, obs) => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      const img = entry.target;
      img.src = img.dataset.src;
      if (img.dataset.srcset) img.srcset = img.dataset.srcset;
      img.removeAttribute('data-src');
      img.removeAttribute('data-srcset');
      obs.unobserve(img);
    });
  },
  { rootMargin: '200px 0px' } // Start loading 200px before entering viewport
);

document.querySelectorAll('img[data-src]').forEach(img => {
  imageObserver.observe(img);
});

// ✅ PRACTICAL: Trigger dynamic module import when section scrolls into view
const sectionObserver = new IntersectionObserver(
  (entries) => {
    entries.forEach(async entry => {
      if (!entry.isIntersecting) return;
      const { default: module } = await import('./heavy-component.js');
      module.init(entry.target);
      sectionObserver.unobserve(entry.target);
    });
  },
  { threshold: 0.1 }
);

document.querySelectorAll('[data-lazy-component]').forEach(el => {
  sectionObserver.observe(el);
});
```

---

### 15.2 ResizeObserver

**Replaces:** `window.resize` event listeners (which fire for every viewport resize, not per-element, and require manual element-size calculation).

**Use cases:** Responsive components (container queries polyfill), chart/canvas resizing, dynamic text fitting, sidebar layout switching.

```javascript
const ro = new ResizeObserver(entries => {
  for (const entry of entries) {
    // entry.target            — observed element
    // entry.contentRect       — { width, height, top, left, ... } (content box)
    // entry.borderBoxSize     — array of { inlineSize, blockSize } (border box)
    // entry.contentBoxSize    — array of { inlineSize, blockSize } (content box)
    // entry.devicePixelContentBoxSize — physical pixel size (for canvas)

    const { width, height } = entry.contentRect;
    console.log(`Element is now ${width}px × ${height}px`);

    // Example: set CSS custom property for use in CSS
    entry.target.style.setProperty('--component-width', `${width}px`);

    // Example: switch component layout at breakpoints
    if (width < 400) {
      entry.target.classList.add('compact');
    } else {
      entry.target.classList.remove('compact');
    }
  }
});

// Start observing
ro.observe(element);
ro.observe(element, { box: 'border-box' }); // Observe border-box size

// Stop observing
ro.unobserve(element);
ro.disconnect(); // Stop all observations

// ✅ PRACTICAL: Resize canvas to match element's device pixel size
const canvasObserver = new ResizeObserver(entries => {
  for (const entry of entries) {
    const [{ inlineSize: width, blockSize: height }] =
      entry.devicePixelContentBoxSize ??
      entry.contentBoxSize;
    const canvas = entry.target;
    canvas.width = Math.round(width * devicePixelRatio);
    canvas.height = Math.round(height * devicePixelRatio);
    // Redraw...
  }
});
canvasObserver.observe(canvas, { box: 'device-pixel-content-box' });
```

---

### 15.3 MutationObserver

**Replaces:** Deprecated `DOMSubtreeModified`, `DOMNodeInserted`, `DOMNodeRemoved` events (synchronous — caused severe performance bottlenecks); `setInterval` polling for DOM changes.

**Use cases:** Watching third-party DOM modifications, detecting class/attribute changes, content security monitoring, implementing undo/redo, custom component lifecycle hooks.

```javascript
const mo = new MutationObserver((mutationList, observer) => {
  mutationList.forEach(mutation => {
    switch (mutation.type) {
      case 'childList':
        // mutation.addedNodes   — NodeList of added nodes
        // mutation.removedNodes — NodeList of removed nodes
        // mutation.previousSibling / nextSibling — sibling context
        console.log('DOM tree changed:', mutation.addedNodes.length, 'added');
        break;
      case 'attributes':
        // mutation.attributeName — which attribute changed
        // mutation.oldValue      — previous value (if attributeOldValue: true)
        console.log(`Attribute "${mutation.attributeName}" changed on`, mutation.target);
        break;
      case 'characterData':
        // mutation.oldValue — previous text content (if characterDataOldValue: true)
        console.log('Text changed from:', mutation.oldValue);
        break;
    }
  });
});

mo.observe(targetElement, {
  childList: true,          // Watch for added/removed children
  subtree: true,            // Apply to all descendants recursively
  attributes: true,         // Watch for attribute changes
  attributeFilter: ['class', 'aria-expanded'], // Only watch specific attributes
  attributeOldValue: true,  // Record previous attribute value
  characterData: true,      // Watch text node content changes
  characterDataOldValue: true // Record previous text
});

mo.disconnect();            // Stop all observations
const pending = mo.takeRecords(); // Flush any queued-but-undelivered mutations
```

---

### 15.4 PerformanceObserver

**Purpose:** Measure real-user performance metrics programmatically — enables Real User Monitoring (RUM). Lab tools (Lighthouse) measure simulated environments; `PerformanceObserver` captures what real users experience.

```javascript
// ✅ Observe Core Web Vitals in real-time
// LCP — Largest Contentful Paint
const lcpObserver = new PerformanceObserver(list => {
  const entries = list.getEntries();
  const lastEntry = entries[entries.length - 1]; // Last is the most relevant
  console.log('LCP:', lastEntry.startTime, 'ms');
  // Send to analytics: sendMetric('LCP', lastEntry.startTime);
});
lcpObserver.observe({ type: 'largest-contentful-paint', buffered: true });

// CLS — Cumulative Layout Shift
let clsScore = 0;
let clsSessionValue = 0;
let clsSessionEntries = [];

const clsObserver = new PerformanceObserver(list => {
  list.getEntries().forEach(entry => {
    // Only count shifts not caused by user input
    if (!entry.hadRecentInput) {
      const firstEntry = clsSessionEntries[0];
      const lastEntry = clsSessionEntries[clsSessionEntries.length - 1];

      // Group shifts within 1 second of each other into sessions (max 5s)
      if (clsSessionEntries.length === 0 ||
          entry.startTime - lastEntry.startTime < 1000 &&
          entry.startTime - firstEntry.startTime < 5000) {
        clsSessionValue += entry.value;
        clsSessionEntries.push(entry);
      } else {
        clsSessionValue = entry.value;
        clsSessionEntries = [entry];
      }
      clsScore = Math.max(clsScore, clsSessionValue);
    }
  });
});
clsObserver.observe({ type: 'layout-shift', buffered: true });

// INP — Interaction to Next Paint (Chromium 96+)
let maxInteractionDuration = 0;
const inpObserver = new PerformanceObserver(list => {
  list.getEntries().forEach(entry => {
    if (entry.interactionId && entry.duration > maxInteractionDuration) {
      maxInteractionDuration = entry.duration;
    }
  });
});
inpObserver.observe({ type: 'event', buffered: true, durationThreshold: 40 });

// Resource timing
new PerformanceObserver(list => {
  list.getEntries().forEach(entry => {
    // entry.name           — resource URL
    // entry.entryType      — 'resource'
    // entry.startTime      — when browser started fetching
    // entry.duration       — total fetch time
    // entry.transferSize   — bytes transferred (0 if cached)
    // entry.encodedBodySize / decodedBodySize
    if (entry.duration > 500) {
      console.warn('Slow resource:', entry.name, entry.duration + 'ms');
    }
  });
}).observe({ entryTypes: ['resource'] });

// Long tasks (>50ms) — main thread blockers
new PerformanceObserver(list => {
  list.getEntries().forEach(entry => {
    console.warn('Long task:', entry.duration + 'ms', 'at', entry.startTime);
  });
}).observe({ entryTypes: ['longtask'] });

// Navigation timing
new PerformanceObserver(list => {
  list.getEntries().forEach(entry => {
    const ttfb = entry.responseStart - entry.requestStart;
    const domLoad = entry.domContentLoadedEventEnd - entry.startTime;
    console.log('TTFB:', ttfb, 'DOMContentLoaded:', domLoad);
  });
}).observe({ entryTypes: ['navigation'] });

// User timing (custom marks/measures)
performance.mark('my-feature-start');
// ... do work ...
performance.mark('my-feature-end');
performance.measure('my-feature', 'my-feature-start', 'my-feature-end');
new PerformanceObserver(list => {
  list.getEntries().forEach(e => console.log(e.name, e.duration + 'ms'));
}).observe({ entryTypes: ['mark', 'measure'] });
```

---

## 16. Core Web Vitals — Optimization Strategies

### 16.1 LCP — Largest Contentful Paint

**What it measures:** The render time of the **largest visible content element** in the viewport — typically the hero image, an above-fold `<h1>`, or a large block of text. Google wants this ≤ 2.5s for a "Good" rating.

**What qualifies as the LCP element:**
- `<img>` elements
- `<image>` inside SVG
- `<video>` poster images
- Block-level elements with background images (less efficient)
- Block-level text elements (`<h1>`, `<p>`, `<div>` with large text)

**Note:** `<svg>` elements themselves are not LCP candidates (only `<image>` inside SVG is).

**Root Causes of Poor LCP:**
1. Slow server response (TTFB > 800ms)
2. Render-blocking resources (CSS or synchronous JS in `<head>`)
3. Slow resource load times (large/uncompressed images, no CDN)
4. Client-side rendering (page skeleton rendered first, then content)
5. LCP image loaded lazily (counterproductive — it's above-fold)

**Full Optimization Checklist for LCP:**

```html
<!-- Step 1: Mark the LCP image as high priority -->
<img src="hero.avif"
     fetchpriority="high"
     loading="eager"
     decoding="async"
     width="1200" height="630"
     alt="...">

<!-- Step 2: Preload LCP image (for responsive srcset) -->
<link rel="preload"
      as="image"
      imagesrcset="hero-400.avif 400w, hero-800.avif 800w, hero-1200.avif 1200w"
      imagesizes="(max-width: 600px) 100vw, 1200px"
      fetchpriority="high">

<!-- Step 3: Preconnect to image CDN if on external domain -->
<link rel="preconnect" href="https://images.cdn.example.com">
```

```apache
# Step 4: Improve TTFB — server-side optimizations
# Enable HTTP/2 or HTTP/3 (QUIC)
# Enable Brotli/gzip compression
# Set aggressive caching for static assets
Cache-Control: public, max-age=31536000, immutable  (for versioned assets)
Cache-Control: no-cache  (for HTML — always revalidate)

# Step 5: Use CDN edge caching
# Deploy origin close to users; CDN serves from PoP nearest the user
```

```css
/* Step 6: Avoid CSS background-image for LCP element
   CSS background images are discovered late (after CSS parse, not HTML parse) */

/* ❌ LCP element should NOT be a CSS background */
.hero { background-image: url('hero.jpg'); } /* Discovered late */

/* ✅ Use <img> for LCP — discovered during HTML parsing */
/* Preload scanner finds <img> in initial HTML bytes */
```

**Server-Side Strategy for LCP:**
- **SSR / SSG** — render initial HTML on the server so content is in the first byte of HTML (vs. SPA that renders fully client-side after JS executes)
- **Streaming HTML** — use HTTP chunked transfer encoding to stream `<head>` and above-fold content before backend processing completes
- **Stale-while-revalidate** caching — serve cached HTML instantly while re-generating in background

---

### 16.2 INP — Interaction to Next Paint

**What it measures:** The **95th percentile** interaction latency across ALL interactions during a page visit — clicks, taps, keyboard input. Specifically: time from user input → next visual frame. Target: ≤ 200ms.

**Why INP replaced FID:**
- FID (First Input Delay) only measured the **first** user interaction on the page
- INP measures **every** interaction: open a menu, click a button, type in a field, submit a form
- A slow animation or heavy click handler on ANY interaction hurts INP

**Anatomy of an interaction:**

```
User click  →  [Input Delay]  →  Event Handler Runs  →  [Presentation Delay]  →  Next Paint
                ↑                       ↑                         ↑
         Main thread busy?      JS doing too much?      Layout/style recalc?
```

**Root Causes of High INP:**
1. **Long tasks** on the main thread at the time of interaction (other JS running)
2. **Heavy event handlers** doing synchronous work (DOM queries, network, computation)
3. **Large React/framework re-renders** triggered by state updates
4. **Third-party scripts** blocking the main thread
5. **Layout thrashing** — interleaved reads and writes to DOM layout properties

**Optimization Strategies:**

```javascript
// 1. Break up long tasks — yield to browser between chunks
async function processLargeDataset(items) {
  const CHUNK = 50;
  for (let i = 0; i < items.length; i++) {
    processItem(items[i]);
    if (i % CHUNK === 0 && i !== 0) {
      // scheduler.yield() is preferred; setTimeout(0) as fallback
      await (scheduler.yield?.() ?? new Promise(r => setTimeout(r, 0)));
    }
  }
}

// 2. Keep event handlers lean — defer heavy work
document.querySelector('#btn').addEventListener('click', async (e) => {
  // This runs synchronously in the interaction window — keep minimal
  showLoadingIndicator(); // UI feedback: instant visual response

  // Yield to allow the browser to paint the loading indicator
  await scheduler.yield?.() ?? new Promise(r => setTimeout(r, 0));

  // Now do the heavy work in a yielded context
  const result = await heavyOperation();
  displayResult(result);
  hideLoadingIndicator();
});

// 3. Layout thrashing — batch reads, then batch writes
// ❌ Thrashing: alternating reads and writes force multiple reflows
elements.forEach(el => {
  const h = el.offsetHeight;          // READ: forces layout
  el.style.height = h * 2 + 'px';    // WRITE: invalidates layout
  const w = el.offsetWidth;           // READ: forces layout AGAIN
  el.style.width = w * 2 + 'px';     // WRITE
});

// ✅ Batch: all reads first, then all writes
const heights = elements.map(el => el.offsetHeight);  // READ batch
const widths = elements.map(el => el.offsetWidth);    // READ batch
elements.forEach((el, i) => {
  el.style.height = heights[i] * 2 + 'px';            // WRITE batch
  el.style.width = widths[i] * 2 + 'px';             // WRITE batch
});

// 4. Move CPU work off main thread
const worker = new Worker('/js/worker.js', { type: 'module' });
document.querySelector('#search').addEventListener('input', e => {
  worker.postMessage({ query: e.target.value, data: largeDataset });
});
worker.addEventListener('message', e => { displayResults(e.data); });

// 5. requestAnimationFrame for visual updates
function updateUI(data) {
  requestAnimationFrame(() => {
    // DOM writes inside rAF are batched with the browser's paint cycle
    element.textContent = data.text;
    element.style.opacity = '1';
  });
}
```

---

### 16.3 CLS — Cumulative Layout Shift

**What it measures:** The **sum of layout shift scores** for all unexpected layout shifts that occur across the entire page lifespan. A layout shift occurs when a visible element changes position between frames without user input. Target: ≤ 0.1.

**Layout shift score formula:**
```
shift score = impact fraction × distance fraction
```
- **Impact fraction** — fraction of the viewport affected by the unstable element's movement
- **Distance fraction** — largest distance any unstable element moved / viewport dimension

**Common CLS causes and fixes:**

| Cause | Fix |
|-------|-----|
| Images without `width`/`height` attributes | Always add `width` and `height` on `<img>`, `<video>`, `<iframe>` |
| Web fonts causing FOUT (text reflow when font loads) | `font-display: swap` + well-matched fallback fonts; use `size-adjust`, `ascent-override`, `descent-override` CSS descriptors to match metrics |
| Dynamically injected banners/ads above existing content | Reserve space with `min-height` before content loads |
| Embeds without reserved space | Use `aspect-ratio` CSS or padding-top hack |
| CSS animations with `top`, `left`, `width`, `height`, `margin` | Use `transform: translate()` and `opacity` instead — composite-only, doesn't trigger layout |
| Slow-loading custom fonts | Preload critical fonts; subset to needed characters |
| Server-side rendering mismatch (hydration shift) | Ensure SSR HTML matches client-side rendered HTML exactly |

```html
<!-- ✅ Always set dimensions on every media element -->
<img src="avatar.jpg" alt="User avatar" width="64" height="64">
<video src="video.mp4" width="1280" height="720"></video>
<iframe src="embed.html" width="560" height="315" title="..."></iframe>
```

```css
/* ✅ Prevent CLS from images using CSS aspect-ratio */
img {
  width: 100%;
  height: auto; /* Combined with HTML width/height attributes, preserves ratio */
}

/* ✅ Reserve space for dynamically loaded ads/banners */
.ad-container {
  min-height: 90px;     /* Standard banner height */
  contain: strict;      /* Prevent ad content from affecting layout outside */
}

/* ✅ Reserve space for embeds with known aspect ratios */
.video-wrapper {
  aspect-ratio: 16 / 9;
  width: 100%;
  overflow: hidden;
  background: #000;     /* Show placeholder background while loading */
}

/* ✅ Use transform for animations — doesn't trigger layout */
.slide-in {
  transform: translateX(-100%);
  transition: transform 0.3s ease;
}
.slide-in.is-visible {
  transform: translateX(0);
}

/* ❌ Layout-triggering animations — cause CLS */
.bad-animation {
  left: -100%;    /* Triggers layout: position recalculation */
  width: 0;       /* Triggers layout: width recalculation */
  margin-left: -100px; /* Triggers layout AND may shift siblings */
}

/* ✅ CSS font metric override descriptors — match fallback font to web font */
@font-face {
  font-family: 'Inter';
  src: url('/fonts/Inter.woff2') format('woff2');
  font-display: swap;
  /* Tweak fallback font metrics to minimize FOUT layout shift */
  size-adjust: 100%;
  ascent-override: 90%;
  descent-override: 22%;
  line-gap-override: 0%;
}
```

---

## 17. Network, Delivery & Caching Layer

Performance is not only about bytes of HTML/CSS/JS — it also encompasses how efficiently those bytes travel from server to browser. The network layer is the foundation on which all other optimizations rest.

### 17.1 HTTP Protocol Evolution

| Protocol | Key Features | Status |
|----------|-------------|--------|
| **HTTP/1.1** | Sequential requests; head-of-line blocking; text-based headers; one request per TCP connection | Legacy — still supported, avoid if alternatives available |
| **HTTP/2** | Multiplexed streams (multiple requests per connection); header compression (HPACK); server push; binary framing | **Standard** — use for all HTTPS sites |
| **HTTP/3** (QUIC) | Built on UDP; inherent multiplexing without HOL blocking; faster connection establishment; improved packet loss recovery | **Cutting-edge** — ~30% of web traffic; enables faster loading on mobile/lossy networks |

**Impact of HTTP/2 vs HTTP/1.1:**
- With HTTP/1.1, browsers open 6 parallel connections per origin; resources queue. Domain sharding (splitting resources across fake subdomains) was a workaround.
- With HTTP/2, **domain sharding is harmful** — it creates multiple connections unnecessarily. All resources should come from the same origin.
- HTTP/2 makes the "bundle everything" webpack approach less critical — many small modules can load in parallel over one multiplexed connection.

---

### 17.2 Compression

| Algorithm | Compression | Speed | Browser Support | Recommendation |
|-----------|------------|-------|----------------|----------------|
| **Brotli** (br) | **Best** — 15–25% better than gzip | Slower to encode; fast to decode | All modern browsers | Enable for all text assets (HTML, CSS, JS, JSON, SVG, fonts) |
| **gzip** | Good | Fast | Universal | Use as fallback when Brotli not negotiated |
| **zstd** | Very good | Very fast | Chrome 123+ (growing) | Emerging; not yet universal |

**Server configuration (Nginx example):**
```nginx
# Enable Brotli (requires ngx_brotli module)
brotli on;
brotli_comp_level 6;
brotli_types text/html text/css application/javascript
             application/json image/svg+xml font/woff2;

# Gzip fallback
gzip on;
gzip_comp_level 6;
gzip_vary on;
gzip_types text/html text/css application/javascript
           application/json image/svg+xml;
```

---

### 17.3 Caching Strategy

Effective caching eliminates network round-trips — the fastest requests are those never made.

**Cache-Control directives:**

| Directive | Meaning |
|-----------|---------|
| `public` | Response can be cached by any cache (CDN, browser, proxy) |
| `private` | Only browser can cache (not CDN) — for user-specific responses |
| `max-age=N` | Cache valid for N seconds |
| `s-maxage=N` | CDN max age (overrides max-age for CDNs) |
| `immutable` | Content will never change — skip revalidation for `max-age` period |
| `no-cache` | Must revalidate with server before using cache (but can use stale while revalidating) |
| `no-store` | Never cache (sensitive data) |
| `stale-while-revalidate=N` | Serve stale content for N seconds while revalidating in background |
| `must-revalidate` | Never serve stale content past max-age |

**Caching strategy by asset type:**

```apache
# HTML — always revalidate (new deploys should take effect immediately)
Cache-Control: no-cache
# or: Cache-Control: public, max-age=0, must-revalidate

# Versioned/hashed static assets (CSS, JS, images with content hash in filename)
# e.g.: app.a1b2c3d4.min.js — content hash = immutable for 1 year
Cache-Control: public, max-age=31536000, immutable

# Non-versioned images (might update)
Cache-Control: public, max-age=86400, stale-while-revalidate=604800

# API responses (varies)
Cache-Control: private, max-age=0, no-store  # user-specific, never cache

# Fonts (rarely change; cache aggressively)
Cache-Control: public, max-age=31536000, immutable
```

---

### 17.4 Content Delivery Networks (CDN)

CDN edge nodes are geographically distributed servers that cache and serve content from locations near the user, dramatically reducing TTFB (Time to First Byte).

**Key benefits:**
- Geographic distribution: 50ms latency in San Francisco vs. 300ms from the origin in London
- Absorbtion of DDoS traffic
- Automatic TLS termination at the edge
- Edge-side rendering and personalization
- Automatic image format negotiation (some CDNs serve AVIF/WebP automatically)

**When to use a CDN:**
- All production websites with a global or national user base
- Any static asset delivery (CSS, JS, images, fonts, video)
- Any public API with regular traffic patterns

---

### 17.5 Service Workers & Offline Strategy

Service workers are JavaScript that run as a background proxy between the browser and the network, enabling:
- Offline support (serve from cache when network unavailable)
- Background sync (defer uploads until connectivity restored)
- Push notifications
- Granular cache management

```javascript
// sw.js — Service Worker
const CACHE_NAME = 'site-v2';
const PRECACHE_ASSETS = [
  '/',
  '/styles/critical.css',
  '/js/app.js',
  '/images/logo.svg',
  '/offline.html'
];

// Install: precache critical assets
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(PRECACHE_ASSETS))
  );
  self.skipWaiting(); // Activate new SW immediately
});

// Activate: clean up old caches
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(names =>
      Promise.all(names
        .filter(name => name !== CACHE_NAME)
        .map(name => caches.delete(name))
      )
    )
  );
  self.clients.claim(); // Take control of all open tabs immediately
});

// Fetch: network-first for HTML, cache-first for assets
self.addEventListener('fetch', event => {
  const { request } = event;

  // HTML: network-first with offline fallback
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(() => caches.match('/offline.html'))
    );
    return;
  }

  // Static assets: cache-first
  event.respondWith(
    caches.match(request).then(cached => {
      if (cached) return cached;
      return fetch(request).then(response => {
        const clone = response.clone();
        caches.open(CACHE_NAME).then(cache => cache.put(request, clone));
        return response;
      });
    })
  );
});

// Register service worker (in main.js)
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js', { scope: '/' })
      .then(reg => console.log('SW registered:', reg.scope))
      .catch(err => console.error('SW registration failed:', err));
  });
}
```

---

## 18. Testing, Auditing & CI/CD Integration

### 18.1 Automated Testing Tools

Performance, accessibility, and SEO must be tested — not assumed. The following tools are essential:

| Tool | Type | What It Tests | Usage |
|------|------|--------------|-------|
| **Lighthouse** | Lab | Performance (CWV), Accessibility (A11y), SEO, Best Practices, PWA | Chrome DevTools, CLI, CI/CD |
| **PageSpeed Insights** | Lab + Field | CWV field data (CrUX), lab scores | https://pagespeed.web.dev/ |
| **Chrome CrUX Dashboard** | Field | Real-user CWV data by origin | Looker Studio + BigQuery |
| **axe DevTools** | A11y | WCAG 2.0/2.1/2.2 violations | Browser extension, npm package for CI |
| **WAVE** | A11y | Visual accessibility overlay | Browser extension |
| **Deque Attest** | A11y | Enterprise CI/CD accessibility testing | CI/CD, Jira, PDF reports |
| **W3C HTML Validator** | HTML | Markup validity per WHATWG spec | validator.w3.org, CLI (v.Nu) |
| **W3C Nu HTML Checker** | HTML | Same as W3C but better for HTML5 | validator.nu |
| **Stylelint** | CSS | Lint for CSS quality and deprecated features | npm, CLI, editor integrations |
| **ESLint** | JS | JS code quality, deprecated patterns | npm, CI/CD |
| **WebPageTest** | Lab | Waterfall, filmstrip, TTFB, CWV, multi-location | webpagetest.org, API |
| **Google Search Console** | Field | Indexing, CWV field data, crawl errors, structured data | Google property |
| **Rich Results Test** | Structured Data | Validates JSON-LD eligibility for rich results | search.google.com/test/rich-results |
| **Schema.org Validator** | Structured Data | General schema.org compliance | validator.schema.org |
| **WCAG Color Contrast Checker** | A11y | WCAG 2.x contrast ratios | webaim.org/resources/contrastchecker/ |
| **APCA Contrast Calculator** | A11y | WCAG 3.0 / APCA contrast | myndex.com/APCA/ |
| **TPGi Colour Contrast Analyser** | A11y | Desktop tool; eyedrop any screen color | developer.paciellogroup.com |
| **Squoosh** | Media | Compare image formats, quality settings | squoosh.app |
| **Playwright / Cypress** | E2E | Automated browser tests with A11y + performance checks | Local, CI/CD |

---

### 18.2 Manual Accessibility Testing

Automated tools catch approximately **30–40% of WCAG violations**. Manual testing is required for the rest:

| Test Method | How to Perform | What It Catches |
|-------------|---------------|----------------|
| **Keyboard-only navigation** | Unplug mouse; navigate with Tab, Shift+Tab, Enter, Space, Arrow keys | Focus traps, wrong tab order, missing keyboard handlers, invisible focus indicators |
| **Screen Reader — VoiceOver (macOS/iOS)** | macOS: Cmd+F5; browse with arrow keys and VoiceOver modifiers | Accessible names, semantic structure, dynamic announcements |
| **Screen Reader — NVDA (Windows)** | Free tool; Firefox recommended pairing | Form labels, headings structure, ARIA patterns |
| **Screen Reader — JAWS (Windows)** | Industry standard; requires license | Complex data tables, PDF accessibility, enterprise apps |
| **Browser Zoom to 200%** | Increase browser font size to 200% (not page zoom) | Font scaling, relative unit usage, layout overflow |
| **Contrast check** | Use TPGi CCA to eyedrop actual rendered colors | Rendered gradient/image overlaps, dynamic states (:hover, :focus) |
| **High Contrast Mode** | Windows: Settings > Accessibility > High Contrast | CSS `forced-colors: active` handling |
| **Content Structure check** | Use Headings Map browser extension | Heading hierarchy, skipped levels, duplicate H1 |
| **Responsive/mobile test** | Chrome DevTools device mode; actual iOS/Android devices | Touch target size, mobile nav, pinch-zoom not disabled |

---

### 18.3 Lighthouse CI Integration

```yaml
# .github/workflows/lighthouse-ci.yml
name: Lighthouse CI

on: [push, pull_request]

jobs:
  lighthouse:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Run Lighthouse CI
        uses: treosh/lighthouse-ci-action@v12
        with:
          urls: |
            https://staging.example.com/
            https://staging.example.com/products
          budgetPath: ./lighthouse-budget.json
          uploadArtifacts: true
          temporaryPublicStorage: true

# lighthouse-budget.json
```

```json
[
  {
    "path": "/*",
    "timings": [
      { "metric": "interactive", "budget": 5000 },
      { "metric": "first-contentful-paint", "budget": 2000 },
      { "metric": "largest-contentful-paint", "budget": 2500 }
    ],
    "audits": [
      { "id": "uses-lcp-image-automatically-added-to-head", "ok": true },
      { "id": "unused-javascript", "budget": 0 }
    ],
    "categories": [
      { "id": "performance", "minScore": 0.9 },
      { "id": "accessibility", "minScore": 0.95 },
      { "id": "seo", "minScore": 0.95 }
    ]
  }
]
```

---

## 19. Complete Modern HTML Reference Template

This is a best-practice HTML template embodying every standard discussed in this document. It is annotated for self-explanatory reference.

```html
<!DOCTYPE html>
<!--
  ✅ DOCTYPE declaration: must be first, no spaces before it.
  html5 DOCTYPE is the only valid form for modern documents.
  Case-insensitive but conventionally written lowercase.
-->
<html lang="en">
<!--
  ✅ lang attribute on <html>:
  - Required by WCAG SC 3.1.1 (Level A)
  - Used by screen readers to select correct voice/pronunciation engine
  - Used by search engines for language detection
  - Use BCP 47 language tags: "en", "en-US", "en-GB", "hi", "gu", "fr", "de"
  - For pages with significant multilingual content, use lang on inner elements
-->

<head>
  <!--
    ✅ Character encoding: MUST appear within first 1024 bytes of HTML.
    UTF-8 is the only acceptable encoding for new documents.
    Instructs browser how to interpret the bytes before any text is parsed.
  -->
  <meta charset="UTF-8">

  <!--
    ✅ Viewport meta: mandatory for correct rendering on mobile devices.
    width=device-width: use the device's screen width (not 980px virtual width)
    initial-scale=1: 1:1 pixel ratio; do NOT add maximum-scale=1 (breaks accessibility zoom)
    Do NOT add user-scalable=no (violates WCAG SC 1.4.4; removes zoom for low-vision users)
  -->
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!--
    ✅ Title: most important on-page SEO element.
    Target: 50–60 characters (Google truncates at ~580px width, ~60 chars avg)
    Format: Primary Keyword: Secondary | Brand Name
    Must be unique per page — generic or duplicate titles harm SEO and UX.
  -->
  <title>Modern Web Standards Reference 2026 · WebTeam</title>

  <!--
    ✅ Meta description:
    Target: 150–160 characters (Google may show up to 920px / 160 chars)
    Purpose: appears as the gray text below the blue link in search results
    Impact: does NOT directly affect rankings, but affects CTR significantly
    Must be unique per page.
  -->
  <meta name="description"
    content="Comprehensive 2026 reference for modern HTML, CSS, JS, WCAG accessibility, Core Web Vitals, image optimization, and SEO best practices.">

  <!--
    ✅ Canonical URL: prevents duplicate content issues.
    Always self-referencing on the canonical version of the page.
    Use absolute URL including protocol and trailing slash (if applicable).
    Critical for: multi-domain mirrors, HTTP/HTTPS, www/non-www, query parameters.
  -->
  <link rel="canonical" href="https://example.com/reference/">

  <!--
    ✅ Robots meta:
    Default (index, follow) is assumed even without this tag.
    Use explicitly for pages you want to exclude or restrict.
    Also available as X-Robots-Tag HTTP header (for non-HTML like PDFs).
  -->
  <meta name="robots" content="index, follow">

  <!--====== COLOR SCHEME ======-->
  <!--
    ✅ color-scheme: tells the browser to render native UI (scrollbars, inputs)
    in the preferred scheme. Works with CSS prefers-color-scheme.
  -->
  <meta name="color-scheme" content="light dark">

  <!--
    ✅ theme-color: sets the browser toolbar/address bar color on mobile
    Android Chrome, PWA, and Samsung Internet respect this.
    Multiple meta theme-color tags with media queries are supported (Chrome 93+).
  -->
  <meta name="theme-color" content="#0f172a"
        media="(prefers-color-scheme: dark)">
  <meta name="theme-color" content="#ffffff"
        media="(prefers-color-scheme: light)">

  <!--====== OPEN GRAPH (Social Sharing) ======-->
  <!--
    ✅ Open Graph tags: control appearance in Facebook, LinkedIn, WhatsApp,
    Slack, iMessage, and most social sharing previews globally.
    og:image recommended: 1200×630px, JPEG or PNG, < 8MB
    og:title: can differ from <title> — more conversational/editorial
  -->
  <meta property="og:type" content="website">
  <meta property="og:url" content="https://example.com/reference/">
  <meta property="og:title"
        content="The Ultimate Modern Web Standards Reference for 2026">
  <meta property="og:description"
        content="Deep-dive guide: WCAG 2.2, Core Web Vitals, AVIF/WebP, JSON-LD, and every deprecated HTML/CSS/JS pattern to avoid.">
  <meta property="og:image"
        content="https://example.com/og-images/reference-1200x630.jpg">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">
  <meta property="og:image:alt"
        content="Modern web standards document with code examples on dark background">
  <meta property="og:site_name" content="WebTeam">
  <meta property="og:locale" content="en_US">

  <!--====== TWITTER / X CARD ======-->
  <!--
    ✅ Twitter/X Cards:
    summary_large_image: shows a large image above 2 lines of description
    Falls back to og: tags if twitter-specific tags not found.
    twitter:image:alt: important for visually impaired Twitter users.
  -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:site" content="@WebTeam">
  <meta name="twitter:creator" content="@AuthorHandle">
  <meta name="twitter:title"
        content="The Ultimate Modern Web Standards Reference for 2026">
  <meta name="twitter:description"
        content="WCAG 2.2, Core Web Vitals, AVIF, JSON-LD — every modern web standard in one document.">
  <meta name="twitter:image"
        content="https://example.com/og-images/reference-1200x630.jpg">
  <meta name="twitter:image:alt"
        content="Modern web standards document with code examples">

  <!--====== FAVICONS ======-->
  <!--
    ✅ Modern favicon stack:
    SVG: scales to any size; supports dark mode via embedded CSS
    PNG 32x32: fallback for browsers not supporting SVG favicon
    PNG 16x16: very old browsers
    Apple touch icon: used when saved to iOS/iPadOS home screen (180x180px)
  -->
  <link rel="icon" type="image/svg+xml" href="/favicon.svg">
  <link rel="icon" type="image/png" sizes="32x32" href="/icons/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/icons/favicon-16.png">
  <link rel="apple-touch-icon" sizes="180x180"
        href="/icons/apple-touch-icon.png">
  <!--
    ✅ Web App Manifest: links to JSON file that enables PWA features —
    install prompt, splash screen, theme color, display mode.
  -->
  <link rel="manifest" href="/site.webmanifest">

  <!--====== HREFLANG (for multilingual sites only) ======-->
  <!--
    ✅ hreflang: helps Google serve the correct locale version of a page.
    Every language variant must reference all other variants and itself.
    x-default: fallback for users not matched to any specific locale.
    Omit this entire block for single-language, single-region sites.
  -->
  <link rel="alternate" hreflang="en" href="https://example.com/reference/">
  <link rel="alternate" hreflang="hi"
        href="https://example.com/hi/reference/">
  <link rel="alternate" hreflang="x-default"
        href="https://example.com/reference/">

  <!--====== RESOURCE HINTS ======-->
  <!--
    ✅ preconnect: establish DNS+TCP+TLS before browser knows it needs it.
    crossorigin: required for CORS resources (fonts, images from different origin).
    Limit to 2–3 per page — each unused connection wastes ~3KB TLS overhead.
  -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <!-- dns-prefetch as fallback for browsers not supporting preconnect -->
  <link rel="dns-prefetch" href="https://fonts.gstatic.com">

  <!--
    ✅ preload: mandatory fetch of a critical resource.
    Use for: LCP image, critical font, key JS file.
    as= attribute required for correct prioritization and Content-Type matching.
    crossorigin required for fonts (even from same origin — CORS fetch mode).
  -->
  <link rel="preload"
        href="/fonts/Inter-Variable.woff2"
        as="font"
        type="font/woff2"
        crossorigin>
  <!-- LCP image preload with responsive srcset support -->
  <link rel="preload"
        as="image"
        imagesrcset="
          /images/hero-480.avif 480w,
          /images/hero-768.avif 768w,
          /images/hero-1200.avif 1200w
        "
        imagesizes="(max-width: 600px) 100vw, 1200px"
        fetchpriority="high">

  <!--====== CRITICAL INLINE CSS ======-->
  <!--
    ✅ Critical CSS: inline the styles needed to render above-the-fold content.
    Eliminates render-blocking CSS request for first paint.
    Everything below-the-fold loads from external stylesheet (async).
    Keep inline CSS small — only what's visible without scrolling.
  -->
  <style>
    /* Critical CSS — above-the-fold only */
    *, *::before, *::after { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: 'Inter', system-ui, -apple-system, sans-serif;
      font-size: 1rem;
      line-height: 1.6;
      color: #1a1a2e;
      background: #ffffff;
    }
    @media (prefers-color-scheme: dark) {
      body { color: #e2e8f0; background: #0f172a; }
    }
    .skip-link {
      position: absolute;
      left: -9999px;
      z-index: 9999;
      padding: 0.75rem 1.5rem;
      background: #000;
      color: #fff;
      text-decoration: none;
      font-weight: 600;
    }
    .skip-link:focus {
      left: 0;
      top: 0;
    }
    :focus-visible {
      outline: 3px solid #4f8ef7;
      outline-offset: 3px;
    }
    :focus:not(:focus-visible) {
      outline: none;
    }
    .hero {
      min-height: 60svh;
      display: grid;
      place-items: center;
    }
  </style>

  <!--
    ✅ Non-critical CSS loaded asynchronously:
    rel="preload" + onload trick converts preloaded file to stylesheet.
    <noscript> fallback for JavaScript-disabled environments.
  -->
  <link rel="preload" href="/styles/main.css" as="style"
        onload="this.onload=null;this.rel='stylesheet'">
  <noscript><link rel="stylesheet" href="/styles/main.css"></noscript>

  <!--====== STRUCTURED DATA (JSON-LD) ======-->
  <!--
    ✅ JSON-LD: lives in <head> or anywhere in <body>.
    Does not affect visual rendering. Parsed by Google, Bing, Yandex, Pinterest.
    Enables Rich Results: star ratings, FAQs, breadcrumbs, events, recipes.
    Use only schema types that genuinely match the page content.
  -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@graph": [
      {
        "@type": "WebPage",
        "@id": "https://example.com/reference/#webpage",
        "url": "https://example.com/reference/",
        "name": "Modern Web Standards Reference 2026",
        "description": "Comprehensive guide to WCAG 2.2, Core Web Vitals, AVIF, JSON-LD.",
        "publisher": { "@id": "https://example.com/#organization" }
      },
      {
        "@type": "Organization",
        "@id": "https://example.com/#organization",
        "name": "WebTeam",
        "url": "https://example.com/",
        "logo": {
          "@type": "ImageObject",
          "url": "https://example.com/logo.svg"
        }
      },
      {
        "@type": "BreadcrumbList",
        "itemListElement": [
          { "@type": "ListItem", "position": 1,
            "name": "Home", "item": "https://example.com/" },
          { "@type": "ListItem", "position": 2,
            "name": "Reference", "item": "https://example.com/reference/" }
        ]
      }
    ]
  }
  </script>

  <!--====== DEFERRED JS (in <head> is fine with defer) ======-->
  <!--
    ✅ Scripts with defer: downloaded in parallel; executes in source order
    after HTML is fully parsed. Safe to put in <head> — does NOT block parsing.
    type="module" is implicitly deferred.
    Add type="module" for ES module entry points.
  -->
  <script src="/js/app.js" defer></script>
  <script type="module" src="/js/modules/main.js"></script>
  <!--
    ✅ async: for independent 3rd-party scripts with no DOM dependency.
    Order of execution not guaranteed.
  -->
  <script src="https://analytics.example.com/track.js" async></script>

</head>

<body>

  <!--====== SKIP LINK ======-->
  <!--
    ✅ Skip link: MUST be the very first focusable element in <body>.
    WCAG SC 2.4.1 (Level A) — bypass blocks.
    Screen reader and keyboard users can skip repetitive navigation to main content.
    href must match id of <main> element.
  -->
  <a href="#main-content" class="skip-link">
    Skip to main content
  </a>

  <!--====== SITE HEADER ======-->
  <!--
    ✅ <header> as direct child of <body>: implicit ARIA role = "banner".
    One <header role="banner"> per page.
    Contains: logo, site name, primary navigation, site-wide search.
    Set a sticky/fixed header? Ensure focused elements are never fully obscured
    by it (WCAG 2.2 SC 2.4.11).
  -->
  <header>
    <a href="/" aria-label="WebTeam — Return to homepage">
      <img src="/logo.svg" alt="WebTeam" width="120" height="40">
    </a>

    <!--
      ✅ <nav>: implicit ARIA role = "navigation".
      aria-label: required when multiple <nav> elements exist per page.
      List-based navigation: <ul><li><a> — standard pattern.
      aria-current="page": marks the currently active page link.
    -->
    <nav aria-label="Main navigation">
      <ul>
        <li><a href="/" aria-current="page">Home</a></li>
        <li><a href="/reference/">Reference</a></li>
        <li><a href="/blog/">Blog</a></li>
        <li><a href="/about/">About</a></li>
      </ul>
    </nav>

    <!--
      ✅ Mobile nav toggle:
      aria-expanded: reflects state of the menu (open/closed).
      aria-controls: links button to the menu element it controls.
      type="button": prevents default form submission behavior.
    -->
    <button type="button"
            aria-expanded="false"
            aria-controls="mobile-menu"
            id="nav-toggle"
            class="nav-toggle">
      <span class="sr-only">Toggle navigation menu</span>
      <svg aria-hidden="true" focusable="false" width="24" height="24"
           viewBox="0 0 24 24">
        <path d="M3 6h18M3 12h18M3 18h18" stroke="currentColor"
              stroke-width="2" stroke-linecap="round"/>
      </svg>
    </button>

    <div id="mobile-menu" hidden role="dialog" aria-label="Mobile navigation">
      <!-- Mobile navigation links -->
    </div>

  </header>

  <!--====== PAGE CONTENT WRAPPER ======-->
  <!--
    ✅ <main>: implicit ARIA role = "main".
    One visible <main> per page.
    id="main-content" matches the skip link href="#main-content".
    tabindex="-1": allows programmatic focus from skip link without disrupting tab order.
  -->
  <main id="main-content" tabindex="-1">

    <!--====== HERO SECTION ======-->
    <!--
      ✅ <section>: thematic grouping.
      aria-labelledby: gives <section> an accessible name via its heading.
      This makes it a "region" landmark for screen-reader navigation.
    -->
    <section aria-labelledby="hero-heading" class="hero">
      <div class="container">
        <!--
          ✅ <h1>: one per page; the primary topic of the document.
          Should contain the primary keyword naturally.
          id must match the aria-labelledby on parent <section>.
        -->
        <h1 id="hero-heading">Modern Web Standards Reference 2026</h1>
        <p>The comprehensive guide to HTML semantics, WCAG 2.2, Core Web Vitals, and production-ready performance optimization.</p>
        <a href="#reference-content" class="btn btn-primary">Explore the Reference</a>
      </div>

      <!--
        ✅ LCP hero image:
        - loading="eager": above-fold; never lazy-load the LCP element
        - fetchpriority="high": signals browser preload scanner
        - decoding="async": decode off main thread (no visual delay)
        - Explicit width + height: browser reserves space before image loads (prevents CLS)
        - Matched <link rel="preload"> in <head> for earliest possible load
      -->
      <picture>
        <source type="image/avif"
                srcset="/images/hero-480.avif 480w,
                        /images/hero-768.avif 768w,
                        /images/hero-1200.avif 1200w"
                sizes="(max-width: 600px) 100vw, 1200px">
        <source type="image/webp"
                srcset="/images/hero-480.webp 480w,
                        /images/hero-768.webp 768w,
                        /images/hero-1200.webp 1200w"
                sizes="(max-width: 600px) 100vw, 1200px">
        <img src="/images/hero-1200.jpg"
             srcset="/images/hero-480.jpg 480w,
                     /images/hero-768.jpg 768w,
                     /images/hero-1200.jpg 1200w"
             sizes="(max-width: 600px) 100vw, 1200px"
             alt="Panoramic view of a modern code editor with web standards documentation"
             width="1200"
             height="630"
             loading="eager"
             fetchpriority="high"
             decoding="async">
      </picture>
    </section>

    <!--====== ARTICLE CONTENT ======-->
    <!--
      ✅ <article>: self-contained, independently distributable content.
      Can contain its own: <header>, <footer>, <section>, <h2>–<h6>.
      Each article on a listing page should have a heading.
      Can be nested (e.g., comments inside blog post article).
    -->
    <article id="reference-content" aria-labelledby="reference-heading">
      <header>
        <h2 id="reference-heading">HTML Foundations</h2>
        <p>Published
          <!--
            ✅ <time> element: machine-readable date.
            datetime attribute: ISO 8601 format.
            Content: human-readable alternative.
          -->
          <time datetime="2026-03-01">March 1, 2026</time>
        </p>
      </header>

      <!--
        ✅ Content sections:
        Each section has a heading for AT navigation and document outline.
        Headings: h1 is page-level; h2 within article; h3 within section; etc.
      -->
      <section aria-labelledby="semantics-heading">
        <h3 id="semantics-heading">Semantic HTML</h3>
        <p>
          Semantic HTML communicates the <strong>meaning</strong> of content to browsers, search engines, and assistive technologies simultaneously. Use elements for their defined purpose, not their default visual appearance.
        </p>

        <!--
          ✅ Accessible figure with caption.
          <figure>: associates an image with its caption.
          <figcaption>: programmatically associated caption; AT reads this.
        -->
        <figure>
          <picture>
            <source type="image/avif"
                    srcset="/images/diagram.avif 800w"
                    sizes="(max-width: 800px) 100vw, 800px">
            <img src="/images/diagram.jpg"
                 alt="HTML document structure diagram showing body, header, main, footer hierarchy"
                 width="800"
                 height="450"
                 loading="lazy"
                 decoding="async">
          </picture>
          <figcaption>
            Figure 1: The semantic sectioning hierarchy of a modern HTML page.
          </figcaption>
        </figure>

        <!--
          ✅ Code example with semantic markup.
          <pre>: preserves whitespace and line breaks (block pre-formatted).
          <code>: marks computer code (inline or within <pre>).
          Combined <pre><code>: the standard for multi-line code blocks.
          aria-label on <pre> to give code block an accessible name.
        -->
        <pre aria-label="Example: Semantic navigation markup">
<code>&lt;nav aria-label="Main navigation"&gt;
  &lt;ul&gt;
    &lt;li&gt;&lt;a href="/" aria-current="page"&gt;Home&lt;/a&gt;&lt;/li&gt;
    &lt;li&gt;&lt;a href="/about"&gt;About&lt;/a&gt;&lt;/li&gt;
  &lt;/ul&gt;
&lt;/nav&gt;
</code></pre>
      </section>

      <!--====== FORM EXAMPLE ======-->
      <section aria-labelledby="contact-heading">
        <h3 id="contact-heading">Contact Form</h3>

        <!--
          ✅ <form> with novalidate: disable native browser UI in favor of
          custom accessible error presentation.
          aria-labelledby: makes form a landmark (must have accessible name).
        -->
        <form novalidate aria-labelledby="contact-heading"
              action="/api/contact" method="post">

          <!--
            ✅ <fieldset> + <legend>: group related fields programmatically.
            Screen readers announce legend text before each field in the group.
            Use for: address groups, radio/checkbox groups, date components.
          -->
          <fieldset>
            <legend>Your Information</legend>

            <!--
              ✅ Form field with:
              - Native <label for="id"> (highest priority for accessible names)
              - autocomplete: assists users with cognitive disabilities
              - aria-required: supplements the native required attribute for AT
              - aria-describedby: associates hint text with the field
              - aria-invalid: set to "true" by JS when field fails validation
              - aria-errormessage: references the error message element
            -->
            <div class="form-group">
              <label for="full-name">
                Full name
                <span aria-hidden="true">*</span>
                <span class="sr-only">(required)</span>
              </label>
              <input type="text"
                     id="full-name"
                     name="full-name"
                     required
                     aria-required="true"
                     aria-describedby="name-hint"
                     aria-invalid="false"
                     autocomplete="name"
                     spellcheck="false">
              <span id="name-hint" class="field-hint">
                Enter your first and last name as they appear on official documents.
              </span>
              <span role="alert" id="name-error" class="field-error" hidden>
                <!-- JS injects: "Please enter your full name." when invalid -->
              </span>
            </div>

            <div class="form-group">
              <label for="email-addr">Email address <span aria-hidden="true">*</span></label>
              <input type="email"
                     id="email-addr"
                     name="email"
                     required
                     aria-required="true"
                     aria-describedby="email-hint"
                     autocomplete="email"
                     inputmode="email">
              <span id="email-hint" class="field-hint">
                We'll use this only to respond to your message.
              </span>
            </div>
          </fieldset>

          <!--
            ✅ Radio group: use <fieldset> + <legend> for radio buttons.
            Each <input type="radio"> has its own <label>.
            aria-required on <fieldset> signals required group selection.
          -->
          <fieldset aria-required="true">
            <legend>Inquiry type <span aria-hidden="true">*</span></legend>
            <label>
              <input type="radio" name="inquiry" value="general" required>
              General inquiry
            </label>
            <label>
              <input type="radio" name="inquiry" value="support">
              Technical support
            </label>
            <label>
              <input type="radio" name="inquiry" value="partnership">
              Partnership
            </label>
          </fieldset>

          <div class="form-group">
            <label for="message">Message <span aria-hidden="true">*</span></label>
            <textarea id="message"
                      name="message"
                      required
                      aria-required="true"
                      rows="5"
                      autocomplete="off"></textarea>
          </div>

          <!--
            ✅ Submit with <button> (not <input type="submit">):
            Allows HTML content inside button label.
            type="submit" is explicit (good practice).
          -->
          <button type="submit" class="btn btn-primary">
            Send message
          </button>

        </form>
      </section>

    </article>

    <!--====== ASIDE (Sidebar) ======-->
    <!--
      ✅ <aside>: complementary content; tangentially related to main content.
      aria-label: required here since <aside> may not have a visible heading
      that uniquely identifies it; or use aria-labelledby if heading exists.
      Complements but isn't essential to understanding the main article.
    -->
    <aside aria-labelledby="related-heading">
      <h2 id="related-heading">Related Resources</h2>
      <nav aria-label="Related articles">
        <ul>
          <li><a href="/wcag-explained/">WCAG 2.2 in Plain English</a></li>
          <li><a href="/cwv-guide/">Core Web Vitals Optimization Guide</a></li>
          <li><a href="/avif-guide/">AVIF vs WebP: When to Use Each</a></li>
        </ul>
      </nav>
    </aside>

    <!--====== LIVE REGIONS ======-->
    <!--
      ✅ ARIA live regions: announce dynamic content changes to screen readers.
      Must exist in DOM before content is injected into them.
      role="status" = aria-live="polite" (waits for current speech to finish)
      role="alert"  = aria-live="assertive" (interrupts immediately)
    -->
    <div role="status" aria-live="polite" aria-atomic="true"
         id="status-announcer" class="sr-only">
      <!-- JS injects messages: "Form submitted successfully." -->
    </div>
    <div role="alert" id="error-announcer" class="sr-only">
      <!-- JS injects urgent errors: "Session expired. Please log in again." -->
    </div>

  </main>

  <!--====== SITE FOOTER ======-->
  <!--
    ✅ <footer> as direct child of <body>: implicit ARIA role = "contentinfo".
    One footer per page at the body level.
    Contains: copyright, legal links, secondary navigation, social links.
  -->
  <footer>
    <nav aria-label="Footer navigation">
      <ul>
        <li><a href="/privacy/">Privacy Policy</a></li>
        <li><a href="/terms/">Terms of Service</a></li>
        <li><a href="/accessibility/">Accessibility</a></li>
        <li><a href="/sitemap.xml">Sitemap</a></li>
        <li><a href="/contact/">Contact</a></li>
      </ul>
    </nav>

    <p>
      <small>
        &copy; <time datetime="2026">2026</time> WebTeam.
        All content is licensed under
        <a href="https://creativecommons.org/licenses/by/4.0/"
           rel="license"
           target="_blank"
           rel="noopener noreferrer">CC BY 4.0</a>.
      </small>
    </p>
  </footer>

</body>
</html>
```

---

## 20. Master Pre-Launch Checklist

Use this checklist before every production deployment. Each item is drawn from the specific knowledge in this document — not duplicate, but a practical application summary.

---

### ✅ 20.1 HTML Structure & Semantics

- [ ] `<!DOCTYPE html>` is the very first line — no whitespace before it
- [ ] `<html lang="...">` has the correct BCP 47 language tag (e.g., `en`, `en-US`, `hi`, `gu`, `fr`)
- [ ] `<meta charset="UTF-8">` appears within the first 1024 bytes of the document
- [ ] `<meta name="viewport" content="width=device-width, initial-scale=1">` is present — no `user-scalable=no`, no `maximum-scale=1`
- [ ] `<title>` is unique per page and 50–60 characters
- [ ] `<meta name="description">` is unique per page and 150–160 characters
- [ ] One `<h1>` per page — matches the primary topic; contains the primary keyword naturally
- [ ] Heading hierarchy is sequential (h1 → h2 → h3) — no levels skipped (e.g., never h1 → h3)
- [ ] One `<main>` per page — not nested inside `<article>`, `<aside>`, `<nav>`, or `<footer>`
- [ ] `<main>` has `id="main-content"` or equivalent for the skip link target
- [ ] `<header>` (page-level) and `<footer>` (page-level) are direct children of `<body>` to activate landmark roles
- [ ] Multiple `<nav>` elements — each has a unique `aria-label` (`"Main navigation"`, `"Footer navigation"`, `"Breadcrumbs"`)
- [ ] `<section>` elements have `aria-labelledby` pointing to their heading (otherwise use `<div>`)
- [ ] `<aside>` has `aria-labelledby` when content has a heading, or `aria-label` when it doesn't
- [ ] **No obsolete elements:** `<font>`, `<center>`, `<marquee>`, `<blink>`, `<frame>`, `<frameset>`, `<acronym>`, `<big>`, `<strike>`, `<tt>`, `<applet>`, `<keygen>`
- [ ] **No obsolete attributes:** `align`, `bgcolor`, `border` on `<img>`, `cellpadding`, `cellspacing`, `hspace`, `vspace`, `type="text/javascript"`, `language="JavaScript"`, `name` on `<a>` (use `id` on target)
- [ ] `<b>` used only for "bring attention" semantics (keywords, first sentences) — not as a general bold wrapper
- [ ] `<i>` used only for idiomatic text, technical terms, or foreign phrases — not general italics
- [ ] `<em>` used for stress emphasis that changes sentence meaning — not decorative italics
- [ ] `<strong>` used for important/urgent text — not decorative bold
- [ ] All `<table>` elements used only for tabular data — never for layout
- [ ] Tables have `<caption>`, `<thead>`, `<tbody>`, `<th scope="col/row">` attributes
- [ ] Forms: every control has an associated `<label for="id">` (or `aria-label` / `aria-labelledby`)
- [ ] `<fieldset>` + `<legend>` used for related control groups (radio buttons, checkboxes, multi-field sections)
- [ ] `autocomplete` attributes on all personal-data form fields
- [ ] `<a href="...">` used only for navigation; `<button>` used for all actions/triggers

---

### ✅ 20.2 Images & Media

- [ ] Every `<img>` has an `alt` attribute — descriptive for informative images, empty (`alt=""`) for decorative
- [ ] Every `<img>` has explicit `width` and `height` attributes (integers, in CSS pixels)
- [ ] LCP / hero image: `loading="eager"` (not `lazy`), `fetchpriority="high"`, preloaded in `<head>` with `<link rel="preload" as="image">`
- [ ] All below-fold images: `loading="lazy"` and `decoding="async"`
- [ ] Images served in AVIF (first) → WebP (second) → JPEG/PNG (fallback) using `<picture>` + `<source type="image/avif">` + `<source type="image/webp">`
- [ ] Responsive images use `srcset` with `w` descriptors and accurate `sizes` attribute
- [ ] SVG icons inside `<button>` or standalone: `aria-hidden="true" focusable="false"` (unless the SVG carries meaningful content)
- [ ] Icon-only buttons have `aria-label` on the `<button>` element
- [ ] Animated GIFs replaced with `<video autoplay muted loop playsinline>` (AV1 → VP9 → MP4 fallback)
- [ ] Decorative autoplay videos: `aria-hidden="true"`; informative autoplay videos: `aria-label` describing content
- [ ] `<video>` with audio: `<track kind="captions">` present (WCAG SC 1.2.2)
- [ ] `<video>` poster image provided to prevent black-screen flash
- [ ] `<video>` does not use `autoplay` without `muted` (modern browsers block unmuted autoplay)
- [ ] All media resized to appropriate display dimensions — no serving 2000px images at 400px display width

---

### ✅ 20.3 Accessibility (WCAG 2.2 AA)

- [ ] Skip link is the **first focusable element** in `<body>`, visible on focus, linked to `<main>`
- [ ] All interactive elements (links, buttons, inputs, selects, custom controls) reachable by keyboard Tab
- [ ] No keyboard traps — Tab/Shift+Tab always allows navigation away from any element
- [ ] Focus order follows visual/logical reading order (DOM order = visual order)
- [ ] `:focus-visible` focus indicators are highly visible (≥ 3:1 contrast against adjacent colors)
- [ ] `outline: none` on `:focus` is never used globally (use `:focus:not(:focus-visible)` if needed)
- [ ] Normal text contrast ratio ≥ **4.5:1** (WCAG AA)
- [ ] Large text (≥ 18pt regular or ≥ 14pt bold) contrast ratio ≥ **3:1**
- [ ] Non-text UI (icons, input borders, focus outlines) contrast ratio ≥ **3:1**
- [ ] Placeholder text in inputs meets 4.5:1 contrast (not exempt from WCAG)
- [ ] No information conveyed by color alone — color always paired with text label, icon, or pattern (WCAG SC 1.4.1)
- [ ] ARIA used only when no native HTML element provides the required semantics
- [ ] No broken ARIA ID references (`aria-labelledby`, `aria-describedby`, `aria-errormessage` all point to existing IDs)
- [ ] `aria-hidden="true"` not applied to any focusable element
- [ ] `tabindex > 0` never used
- [ ] Interactive custom widgets have complete keyboard behavior (e.g., arrow-key navigation for menus, Escape to dismiss dialogs)
- [ ] Modal dialogs: focus trapped inside while open; focus returned to trigger element on close; `aria-modal="true"` set
- [ ] Dynamic content changes (search results, notifications, form errors) announced via `aria-live` regions
- [ ] `aria-live` regions exist in DOM before messages are injected (not created dynamically)
- [ ] Form errors are programmatically associated with their fields (`aria-describedby`/`aria-errormessage`)
- [ ] `aria-invalid="true"` set on invalid form fields
- [ ] Touch targets are ≥ 24×24 CSS pixels (WCAG 2.2 SC 2.5.8 AA); aim for 44×44px (best practice)
- [ ] `prefers-reduced-motion: reduce` honored — no auto-playing motion, parallax, content carousels
- [ ] No text represented as images (violates WCAG SC 1.4.5)
- [ ] Page tested with keyboard-only navigation (Tab, Shift+Tab, Enter, Space, Arrow keys, Escape)
- [ ] Page tested with at least one screen reader (NVDA+Firefox, VoiceOver+Safari)

---

### ✅ 20.4 SEO & Structured Data

- [ ] `<link rel="canonical">` on every indexable page (absolute URL)
- [ ] `<meta name="robots">` — check no page is accidentally `noindex`-ed
- [ ] Open Graph: `og:title`, `og:description`, `og:url`, `og:image` (1200×630px), `og:image:alt`, `og:type`, `og:site_name` on all shareable pages
- [ ] Twitter card: `twitter:card`, `twitter:title`, `twitter:description`, `twitter:image`, `twitter:image:alt`
- [ ] JSON-LD structured data appropriate for page content type (WebPage, Article, Product, FAQPage, BreadcrumbList, LocalBusiness as applicable)
- [ ] JSON-LD validated via Google's Rich Results Test (no critical errors)
- [ ] BreadcrumbList schema on all non-home pages
- [ ] `robots.txt` present at root domain
- [ ] `sitemap.xml` present at root domain and submitted to Google Search Console
- [ ] Page indexed in Google Search Console (no coverage errors)
- [ ] Hreflang implemented correctly for all locales (self-referential, x-default defined) — only if multilingual

---

### ✅ 20.5 Performance (Core Web Vitals)

- [ ] **LCP ≤ 2.5s** — verified with PageSpeed Insights field data (75th percentile)
- [ ] **INP ≤ 200ms** — verified with PageSpeed Insights field data (75th percentile)  
- [ ] **CLS ≤ 0.1** — verified with PageSpeed Insights field data (75th percentile)
- [ ] LCP image: preloaded, `fetchpriority="high"`, not lazy-loaded, not behind CSS background-image
- [ ] No synchronous `<script>` in `<head>` (all scripts use `defer`, `async`, or `type="module"`)
- [ ] Critical CSS inlined in `<style>`; non-critical CSS loaded asynchronously via preload trick
- [ ] Fonts preloaded with `<link rel="preload" as="font" crossorigin>`
- [ ] Web fonts use `font-display: swap` (or `optional` for non-essential decorative fonts)
- [ ] `preconnect` to critical third-party origins (font CDN, API CDN) — max 2–3
- [ ] `dns-prefetch` as fallback for `preconnect` on older browsers
- [ ] Unused preloads eliminated (check DevTools Network tab for "Preload used" warnings)
- [ ] All images served in compressed AVIF/WebP with JPEG fallback
- [ ] All `<img>` elements have explicit `width` + `height` attributes (prevents CLS)
- [ ] CSS animations use only `transform` and `opacity` (never `top`, `left`, `width`, `height`, `margin` in animations)
- [ ] `content-visibility: auto` applied to long off-screen sections (with `contain-intrinsic-size`)
- [ ] Event handlers are lean — heavy work deferred with `scheduler.yield()` or moved to Web Workers
- [ ] Main-thread tasks > 50ms identified and broken up (check Lighthouse "Avoid long main-thread tasks")
- [ ] Third-party scripts audited — unnecessary ones removed; remaining ones loaded with `async` or deferred
- [ ] HTTP/2 or HTTP/3 enabled on the server
- [ ] Brotli compression enabled; gzip as fallback
- [ ] Versioned static assets (JS, CSS, images) have `Cache-Control: max-age=31536000, immutable`
- [ ] HTML pages have `Cache-Control: no-cache` (always revalidate)
- [ ] CDN deployed for static assets and (ideally) HTML

---

### ✅ 20.6 CSS Quality

- [ ] No float-based column layouts (use CSS Grid or Flexbox)
- [ ] No `@import` in production CSS (use bundler/build tools instead)
- [ ] No unnecessary vendor prefixes for modern CSS properties — use Autoprefixer with correct browerslist target
- [ ] `box-sizing: border-box` applied globally (e.g., `*, *::before, *::after { box-sizing: border-box; }`)
- [ ] Font sizes use `rem` or `em` (respects user's browser font size preferences) — never `px` for base font
- [ ] No `!important` abuse (legitimate uses: utility-class overrides and reset CSS only)
- [ ] Logical properties used where applicable (`margin-inline`, `padding-block`, `inset-inline-start`)
- [ ] Container queries used for component-level responsiveness where `@media` creates coupling issues
- [ ] CSS Custom Properties (`--var`) used for theming, color system, spacing scale, typography scale
- [ ] `@media (forced-colors: active)` tested (Windows High Contrast Mode)
- [ ] Print stylesheet or `@media print` rules present if pages need to be printable

---

### ✅ 20.7 JavaScript Quality

- [ ] No `document.write()` — replaced with DOM API methods
- [ ] No `escape()` / `unescape()` — replaced with `encodeURIComponent()` / `decodeURIComponent()`
- [ ] No `eval()` with user-controlled input
- [ ] Scroll event listeners polling for element positioning replaced with `IntersectionObserver`
- [ ] `window.resize` for element-size calculations replaced with `ResizeObserver`
- [ ] DOM polling (intervals checking for element existence) replaced with `MutationObserver`
- [ ] Callback-based async patterns replaced with `async/await`
- [ ] Event listeners cleaned up (removed when component unmounts or page navigates away) — use `AbortController`
- [ ] `keyCode` and `which` replaced with `event.key`
- [ ] `Date` based date manipulation in complex calendar/scheduling code replaced with Temporal API (where available)
- [ ] Large data processing (sorting arrays >10k items, image manipulation) moved to Web Workers
- [ ] Dynamic imports used for heavy modules loaded conditionally or on user action
- [ ] No memory leaks — long-lived closures, detached DOM nodes with listeners, and forgotten `setInterval` calls audited

---

### ✅ 20.8 Security Headers (Beyond Scope but Strongly Related)

- [ ] `Content-Security-Policy` (CSP) header configured — prevents XSS
- [ ] `Strict-Transport-Security` (HSTS) header configured — forces HTTPS
- [ ] `X-Content-Type-Options: nosniff` — prevents MIME-type sniffing
- [ ] `X-Frame-Options: DENY` or `SAMEORIGIN` — prevents clickjacking
- [ ] `Referrer-Policy: strict-origin-when-cross-origin` — limits referer leakage
- [ ] `Permissions-Policy` header configured — restricts browser API access not used by the site
- [ ] All external links: `rel="noopener noreferrer"` on `target="_blank"` links

---

### ✅ 20.9 Final Validation & Testing Tools

Run all of these before marking any page as production-ready:

| Tool | URL / Command | What to Check |
|------|--------------|---------------|
| **W3C HTML Validator** | validator.w3.org | No errors (warnings acceptable with explanation) |
| **Lighthouse** (DevTools) | F12 → Lighthouse tab | Performance ≥ 90, Accessibility ≥ 95, SEO ≥ 95 |
| **PageSpeed Insights (Field)** | pagespeed.web.dev | CWV field data: LCP ≤ 2.5s, INP ≤ 200ms, CLS ≤ 0.1 |
| **axe DevTools** | Browser extension | 0 critical/serious violations |
| **WAVE** | wave.webaim.org | 0 errors |
| **Google Rich Results Test** | search.google.com/test/rich-results | All structured data eligible |
| **WebPageTest** | webpagetest.org | Waterfall analysis; TTFB; Start Render; LCP |
| **WebAIM Contrast Checker** | webaim.org/resources/contrastchecker/ | All color pairs pass thresholds |
| **Keyboard navigation** | Manual — unplug mouse | All interactive elements reachable and operable |
| **Screen reader** | NVDA + Firefox (Windows); VoiceOver + Safari (macOS/iOS) | Document structure, form labels, dynamic content |

---

## Document Metadata

| Field | Value |
|-------|-------|
| **Last Updated** | March 2026 |
| **Based on** | WHATWG HTML Living Standard, W3C WCAG 2.2, WAI-ARIA 1.2, MDN Web Docs, web.dev, schema.org, RFC 9110–9114 (HTTP), TC39 Temporal Proposal, APCA/BWMA, Google CWV documentation, W3C CSS Grid Level 2, W3C CSS Cascade Level 5 |
| **Scope** | HTML5 (WHATWG Living Standard), CSS3+, ES2022+, WCAG 2.2 AA, Core Web Vitals (LCP/INP/CLS), Schema.org, HTTP/3 (QUIC) |
| **Review schedule** | Revisit when WCAG 3.0 reaches Candidate Recommendation status (~2028+); revisit for JPEG XL browser support status; revisit for Temporal API Baseline status |

---

# Addendum — Additional Topics (2026)

The following sections cover additional topics verified through current (2025–2026) standards documentation.

---

## 21. Additional WCAG 2.2 Success Criteria (Missing from Original Reports)

The original sections covered SC 2.4.11, 2.4.12, 2.5.8, 3.2.6, 3.3.7, and 3.3.8. The following WCAG 2.2 criteria were omitted:

### 21.1 SC 2.5.7 — Dragging Movements (Level AA)

**Requirement:** All functionality that uses dragging movements (drag-and-drop) must be operable via a single-pointer alternative (click/tap, or keyboard) — unless dragging is essential.

**Why it matters:** Users with motor impairments, tremors, or who use head pointers / eye-tracking cannot perform drag operations. Touch screen users with limited dexterity also struggle with drag gestures.

**Compliant implementation:**

```html
<!-- ✅ Sortable list with both drag AND button-based reordering -->
<ul role="listbox" aria-label="Priority tasks (reorderable)">
  <li role="option" draggable="true" aria-grabbed="false">
    <span>Task A</span>
    <button type="button" aria-label="Move Task A up"
            onclick="moveUp(this)">↑</button>
    <button type="button" aria-label="Move Task A down"
            onclick="moveDown(this)">↓</button>
  </li>
  <li role="option" draggable="true" aria-grabbed="false">
    <span>Task B</span>
    <button type="button" aria-label="Move Task B up">↑</button>
    <button type="button" aria-label="Move Task B down">↓</button>
  </li>
</ul>

<!-- ✅ File upload: drag-and-drop zone WITH a standard file input -->
<div class="dropzone" role="region" aria-label="Drop files here to upload">
  <p>Drag files here, or:</p>
  <label for="file-upload" class="btn">Choose files</label>
  <input type="file" id="file-upload" multiple accept=".pdf,.jpg,.png">
</div>
```

**Key rule:** If a user can drag-and-drop to accomplish something, they must *also* be able to accomplish the same thing with click/tap actions or keyboard.

---

### 21.2 SC 2.4.13 — Focus Appearance (Level AAA)

**Requirement:** When a UI component receives keyboard focus, the focus indicator must:
- Have an area at least as large as a **2 CSS pixel thick perimeter** of the unfocused component
- Have a contrast ratio of **3:1** between the focused and unfocused states
- Have a contrast ratio of **3:1** against adjacent non-focus-indicator colors

**Practical implementation:**

```css
/* ✅ WCAG 2.2 SC 2.4.13 compliant focus indicator */
:focus-visible {
  outline: 3px solid #1a73e8;     /* 3px > 2px minimum perimeter */
  outline-offset: 3px;            /* Offset prevents overlap with element border */
  border-radius: 3px;             /* Match component's border-radius */
  /* Ensure #1a73e8 has ≥ 3:1 contrast against adjacent background colors */
}

/* ✅ For dark backgrounds */
.dark-section :focus-visible {
  outline-color: #8ab4f8;         /* Lighter blue for dark backgrounds */
}

/* ✅ High-contrast focus for links within text */
a:focus-visible {
  outline: 3px solid #1a73e8;
  outline-offset: 2px;
  background-color: #e8f0fe;      /* Additional visual cue */
  border-radius: 2px;
}
```

**Note:** SC 2.4.13 is AAA-level, so it's aspirational not mandatory, but best-practice designs should follow it. The related SC 2.4.11 (Focus Not Obscured, Level AA) **is** mandatory — focused elements must not be entirely covered by sticky headers, cookie banners, or other positioned content.

---

### 21.3 SC 3.3.9 — Accessible Authentication (Enhanced) (Level AAA)

**Requirement (beyond AA SC 3.3.8):** Authentication must not require **any** cognitive function test — no object recognition, no personal content recognition, no puzzles. Only exceptions: objects provided by the site (not the user's personal items) and passkeys/WebAuthn.

**Practical guidance:**
- Passwords with password managers are compliant (paste is allowed, autocomplete works)
- Passkeys / WebAuthn biometric login is ideal — fully compliant
- CAPTCHA alternatives: invisible reCAPTCHA, hCaptcha accessibility mode, Turnstile
- SMS OTP with `autocomplete="one-time-code"` is compliant (user doesn't need to memorize)

```html
<!-- ✅ Login form optimized for accessible authentication -->
<form action="/auth/login" method="post" aria-label="Sign in">
  <label for="email">Email</label>
  <input type="email" id="email" name="email"
         autocomplete="username"
         required aria-required="true">

  <label for="password">Password</label>
  <input type="password" id="password" name="password"
         autocomplete="current-password"
         required aria-required="true">

  <!-- Allow paste for password managers -->
  <!-- NEVER add onpaste="return false" -->

  <button type="submit">Sign in</button>

  <!-- Passkey alternative (WebAuthn) -->
  <button type="button" onclick="startPasskeyAuth()">
    Sign in with Passkey
  </button>
</form>

<!-- ✅ OTP field with autocomplete for SMS codes -->
<label for="otp">Verification code (sent to your phone)</label>
<input type="text" id="otp" name="otp"
       autocomplete="one-time-code"
       inputmode="numeric"
       pattern="[0-9]{6}"
       maxlength="6"
       aria-describedby="otp-hint">
<span id="otp-hint">Enter the 6-digit code from your SMS.</span>
```

---

### 21.4 WCAG 2.2 SC 4.1.1 Parsing — Removed

**Important change:** WCAG 2.2 **removed** Success Criterion 4.1.1 (Parsing) entirely. This is because:
- Modern browsers are now highly resilient to parsing errors — they follow the HTML5 parsing algorithm, which defines error recovery behavior for all malformed markup
- The criterion was effectively obsolete — browsers handle duplicate IDs, unclosed tags, and nesting errors consistently
- However: clean, valid HTML is still strongly recommended for maintainability, AT compatibility, and professional quality
- Use the W3C Nu HTML Checker (validator.nu) as a quality tool, not a compliance requirement

---

## 22. The `<dialog>` Element — Native Modal & Non-Modal Dialogs

The `<dialog>` element is a **native HTML element** for building modal and non-modal dialogs with built-in accessibility features. It eliminates the need for custom modal JavaScript in most cases. **Baseline: all modern browsers (Chrome 37+, Firefox 98+, Safari 15.4+, Edge 79+).**

### 22.1 Modal Dialog (`.showModal()`)

```html
<!-- The dialog element — hidden by default -->
<dialog id="confirm-dialog" aria-labelledby="dlg-title" aria-describedby="dlg-desc">
  <h2 id="dlg-title">Confirm Deletion</h2>
  <p id="dlg-desc">This action cannot be undone. Are you sure you want to delete this item?</p>

  <!-- form method="dialog" auto-closes the dialog on submit -->
  <form method="dialog">
    <button type="submit" value="cancel">Cancel</button>
    <button type="submit" value="confirm" autofocus>Delete</button>
    <!-- autofocus: first focused element when dialog opens -->
  </form>
</dialog>

<!-- Trigger button -->
<button type="button" id="open-dialog">Delete Item</button>

<script>
  const dialog = document.getElementById('confirm-dialog');
  const openBtn = document.getElementById('open-dialog');

  openBtn.addEventListener('click', () => {
    dialog.showModal();
    // showModal():
    //  - Adds ::backdrop pseudo-element (dimmed overlay)
    //  - Traps focus inside the dialog (Tab cycles within)
    //  - Makes rest of page inert (AT cannot navigate outside)
    //  - Adds role="dialog" and aria-modal="true" implicitly
    //  - ESC key closes the dialog by default
  });

  dialog.addEventListener('close', () => {
    // dialog.returnValue contains the value of the submit button used
    if (dialog.returnValue === 'confirm') {
      performDeletion();
    }
    // Focus automatically returns to the element that opened the dialog
  });

  // Optional: close on backdrop click
  dialog.addEventListener('click', (e) => {
    const rect = dialog.getBoundingClientRect();
    if (e.clientX < rect.left || e.clientX > rect.right ||
        e.clientY < rect.top || e.clientY > rect.bottom) {
      dialog.close('cancel');
    }
  });
</script>
```

### 22.2 Non-Modal Dialog (`.show()`)

```javascript
dialog.show();
// show():
//  - Opens dialog but does NOT trap focus
//  - Does NOT add ::backdrop
//  - Rest of page remains interactive
//  - User CAN Tab out of the dialog
//  - Use for: tooltips, toolbars, inspection panels
```

### 22.3 Styling the Dialog

```css
/* Base dialog styles */
dialog {
  border: none;
  border-radius: 12px;
  padding: 2rem;
  max-width: min(90vw, 500px);
  box-shadow: 0 24px 80px rgba(0, 0, 0, 0.3);
}

/* The backdrop overlay (only with showModal) */
dialog::backdrop {
  background: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
}

/* Opening/closing animation (Chrome 120+, Safari 17.5+) */
dialog {
  opacity: 0;
  transform: translateY(-20px) scale(0.95);
  transition: opacity 0.3s ease, transform 0.3s ease,
              display 0.3s ease allow-discrete,
              overlay 0.3s ease allow-discrete;
}

dialog[open] {
  opacity: 1;
  transform: translateY(0) scale(1);
}

@starting-style {
  dialog[open] {
    opacity: 0;
    transform: translateY(-20px) scale(0.95);
  }
}
```

### 22.4 `<dialog>` vs. Custom Modal — Why Native Wins

| Feature | `<dialog>` (native) | Custom `<div role="dialog">` |
|---------|---------------------|------------------------------|
| Focus trapping | Automatic with `showModal()` | Must implement manually (many libraries get it wrong) |
| `inert` on background | Automatic — AT cannot navigate behind modal | Must add `inert` attribute to all siblings manually |
| ESC to close | Built-in browser behavior | Must implement `keydown` listener |
| Accessible role | Implicit `role="dialog"` | Must add `role="dialog"` manually |
| `::backdrop` | Automatic with `showModal()` | Must create backdrop element manually |
| Focus return on close | Automatic | Must track and restore manually |
| Screen reader announcement | Announced automatically as dialog | Depends on correct ARIA implementation |

**Always prefer `<dialog>` for modals.** Use custom ARIA dialogs only when you need behavior the native element cannot provide (extremely rare in 2026).

---

## 23. The Popover API — Native Overlays Without JS

The Popover API provides a **declarative, JavaScript-free mechanism** for creating overlay UI — tooltips, dropdown menus, teaching tips, notification toasts, and action sheets. **Baseline Widely Available: April 2025 (Chrome 114+, Firefox 125+, Safari 17+, Edge 114+).**

### 23.1 Basic Popover (Declarative — Zero JS)

```html
<!-- The trigger button: popovertarget links to the popover's id -->
<button popovertarget="my-popover" type="button">
  Show Info
</button>

<!-- The popover content: hidden by default -->
<div id="my-popover" popover>
  <p>This overlay appears on top of all other content.</p>
  <p>Click outside or press ESC to dismiss.</p>
</div>

<!--
  Default popover behavior (popover="auto"):
  - Appears on TOP layer (above all z-index — no stacking context issues)
  - Light dismissal: click outside or press ESC closes it
  - Only ONE auto popover can be open at a time (others close automatically)
  - No backdrop, no focus trapping (unlike <dialog>)
-->
```

### 23.2 Popover Types

| Attribute | Behavior |
|-----------|----------|
| `popover` or `popover="auto"` | Light dismissal (ESC / click outside); only one `auto` popover open at a time |
| `popover="manual"` | Must be dismissed explicitly (via JS or button); multiple can coexist |
| `popover="hint"` | (Chrome 133+) Designed for hover-triggered hints/tooltips; even lighter weight |

### 23.3 Programmatic Control

```javascript
const pop = document.getElementById('my-popover');

pop.showPopover();    // Open
pop.hidePopover();    // Close
pop.togglePopover();  // Toggle

// Listen for open/close events
pop.addEventListener('toggle', (e) => {
  console.log(e.newState); // 'open' or 'closed'
  console.log(e.oldState);
});

// beforetoggle: can be used to prevent opening
pop.addEventListener('beforetoggle', (e) => {
  if (someCondition) e.preventDefault(); // block the toggle
});
```

### 23.4 Popover Actions (Show / Hide / Toggle)

```html
<!-- Toggle button (default) -->
<button popovertarget="menu" type="button">Toggle Menu</button>

<!-- Explicit show-only button -->
<button popovertarget="menu" popovertargetaction="show" type="button">
  Open Menu
</button>

<!-- Explicit hide-only button (can be inside the popover) -->
<div id="menu" popover>
  <p>Menu content here</p>
  <button popovertarget="menu" popovertargetaction="hide" type="button">
    Close
  </button>
</div>
```

### 23.5 Accessibility with Popovers

**Critical:** The Popover API provides the top-layer rendering and light dismiss, but it does **not** automatically provide ARIA semantics. You must add accessibility manually:

```html
<!-- ✅ Popover as a menu — add proper ARIA -->
<button popovertarget="action-menu"
        aria-expanded="false"
        aria-haspopup="menu"
        type="button"
        id="menu-btn">
  Actions
</button>
<div id="action-menu" popover role="menu" aria-label="Actions">
  <button role="menuitem" tabindex="-1">Edit</button>
  <button role="menuitem" tabindex="-1">Duplicate</button>
  <button role="menuitem" tabindex="-1">Delete</button>
</div>

<script>
  // Update aria-expanded when popover toggles
  const menu = document.getElementById('action-menu');
  const btn = document.getElementById('menu-btn');
  menu.addEventListener('toggle', (e) => {
    btn.setAttribute('aria-expanded', e.newState === 'open');
    if (e.newState === 'open') {
      // Focus first menuitem
      menu.querySelector('[role="menuitem"]').focus();
    }
  });
</script>

<!-- ✅ Popover as a tooltip (non-interactive) -->
<button aria-describedby="tip" type="button">
  Hover for info
</button>
<div id="tip" popover="hint" role="tooltip">
  This feature requires a Pro subscription.
</div>
```

### 23.6 `<dialog>` vs. Popover — When to Use Which

| Scenario | Use |
|----------|-----|
| Confirmation prompt requiring user decision | `<dialog>` with `showModal()` |
| Alert / critical error requiring acknowledgment | `<dialog>` with `showModal()` |
| Dropdown menu / action sheet | Popover API |
| Tooltip / hint text | Popover API (`popover="hint"`) |
| Non-blocking notification toast | Popover API (`popover="manual"`) |
| Date picker (complex embedded widget) | `<dialog>` (non-modal) or Popover depending on complexity |
| Cookie consent banner | Popover API (`popover="manual"`) — stays until explicitly dismissed |

---

## 24. `<details>` & `<summary>` — Native Disclosure Widgets & Exclusive Accordions

### 24.1 Basic Disclosure Widget

The `<details>` and `<summary>` elements create a native disclosure (expand/collapse) widget with zero JavaScript. Screen readers announce it as an interactive control, and keyboard users can toggle with Enter/Space.

```html
<!-- ✅ Native disclosure widget -->
<details>
  <summary>What is your refund policy?</summary>
  <p>We offer a 30-day money-back guarantee on all purchases.
     Contact support@example.com with your order number.</p>
</details>

<!-- ✅ Open by default -->
<details open>
  <summary>System Requirements</summary>
  <ul>
    <li>macOS 12+ or Windows 10+</li>
    <li>4 GB RAM minimum</li>
    <li>500 MB disk space</li>
  </ul>
</details>
```

### 24.2 Exclusive Accordion (HTML `name` Attribute — 2024+)

The `name` attribute on `<details>` elements creates **exclusive accordion behavior** — when one `<details>` with a given `name` opens, all others with the same `name` automatically close. **No JavaScript required.**

**Browser support:** Chrome 120+, Safari 17.2+, Firefox 130+, Edge 120+.

```html
<!-- ✅ Exclusive accordion — only one panel open at a time -->
<details name="faq">
  <summary>How do I create an account?</summary>
  <p>Click "Sign Up" in the top-right corner and follow the registration steps.</p>
</details>

<details name="faq">
  <summary>Can I change my username?</summary>
  <p>Yes, go to Settings → Profile → Edit Username. Changes take effect immediately.</p>
</details>

<details name="faq" open>
  <summary>How do I cancel my subscription?</summary>
  <p>Navigate to Settings → Billing → Cancel Subscription.
     Your access continues until the end of the current billing period.</p>
</details>

<!-- All three share name="faq" — opening one closes the others -->
```

### 24.3 Styling `<details>` & `<summary>`

```css
/* Custom disclosure icon */
summary {
  cursor: pointer;
  font-weight: 600;
  padding: 0.75rem 1rem;
  list-style: none; /* Remove default triangle marker */
}
summary::-webkit-details-marker { display: none; } /* Safari */

/* Custom marker with CSS */
summary::before {
  content: '▶';
  display: inline-block;
  margin-right: 0.5rem;
  transition: transform 0.2s ease;
}
details[open] > summary::before {
  transform: rotate(90deg);
}

/* Animate content reveal (limited — height animation is complex) */
details {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  margin-bottom: 0.5rem;
  overflow: hidden;
}

/* Content area padding */
details > :not(summary) {
  padding: 0 1rem 1rem;
}

/* Focus styles for keyboard navigation */
summary:focus-visible {
  outline: 3px solid #4f8ef7;
  outline-offset: 2px;
}
```

### 24.4 `<details>` for FAQ Structured Data

When combined with JSON-LD FAQPage schema, `<details>` elements make excellent FAQ sections that qualify for Google's rich results:

```html
<section aria-labelledby="faq-heading">
  <h2 id="faq-heading">Frequently Asked Questions</h2>

  <details name="faq">
    <summary>What formats do you accept?</summary>
    <p>We accept AVIF, WebP, PNG, and JPEG image formats.</p>
  </details>

  <details name="faq">
    <summary>Do you offer a free trial?</summary>
    <p>Yes, all plans include a 14-day free trial with full feature access.</p>
  </details>
</section>

<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "What formats do you accept?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "We accept AVIF, WebP, PNG, and JPEG image formats."
      }
    },
    {
      "@type": "Question",
      "name": "Do you offer a free trial?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Yes, all plans include a 14-day free trial with full feature access."
      }
    }
  ]
}
</script>
```

---

## 25. E-E-A-T — Content Quality Signals for SEO

E-E-A-T (Experience, Expertise, Authoritativeness, Trustworthiness) is Google's quality framework used by human quality raters and algorithmic signals to evaluate content credibility. As of 2025, E-E-A-T is **not** a direct ranking factor — it's a framework that influences how Google's algorithms assess content quality. However, aligning with E-E-A-T principles correlates strongly with higher rankings.

### 25.1 The Four Pillars

| Pillar | Definition | How to Demonstrate |
|--------|-----------|-------------------|
| **Experience** (added 2022) | First-hand, real-world experience with the topic | Include personal case studies, original photography, video demonstrations, real results/data, "I tested this" narratives |
| **Expertise** | Knowledge and skill in the subject area | Author bios with credentials, certifications, professional profiles (LinkedIn), detailed technical explanations |
| **Authoritativeness** | Recognition as a go-to source in the field | Backlinks from authoritative sites, industry mentions, media coverage, organizational credentials, awards |
| **Trustworthiness** | Accuracy, transparency, and reliability | HTTPS, clear contact info, privacy policy, editorial guidelines, fact-checking, citations, disclosure of affiliations |

### 25.2 YMYL (Your Money or Your Life)

E-E-A-T is particularly critical for **YMYL** (Your Money or Your Life) topics — content that could impact a person's health, financial stability, safety, or well-being. Examples:
- Medical/health information
- Financial advice (investing, taxes, loans)
- Legal information
- News and current events
- Shopping/e-commerce (transactions)
- Government/civic information

YMYL pages are held to the **highest E-E-A-T standards**. Low E-E-A-T on a YMYL page can result in severe ranking penalties.

### 25.3 Technical Implementation for E-E-A-T

```html
<!-- ✅ Author information in HTML (structured for both users and crawlers) -->
<article>
  <header>
    <h1>Complete Guide to Core Web Vitals in 2026</h1>
    <div class="byline">
      <img src="/authors/jane-smith.jpg" alt="Jane Smith" width="48" height="48">
      <div>
        <a href="/authors/jane-smith/" rel="author">Jane Smith</a>
        <p>Senior Web Performance Engineer · 12 years experience ·
           <a href="https://www.linkedin.com/in/janesmith" rel="noopener">LinkedIn</a></p>
      </div>
      <time datetime="2026-01-15">January 15, 2026</time>
      <span>Last updated: <time datetime="2026-02-28">February 28, 2026</time></span>
    </div>
  </header>

  <!-- Article content with citations -->
  <p>According to the
    <a href="https://web.dev/articles/vitals" rel="noopener">web.dev documentation</a>,
    LCP measures...</p>

  <!-- Reviewer / fact-checker (enhanced E-E-A-T signal) -->
  <footer>
    <p>Reviewed by <a href="/authors/dr-web/">Dr. Web Expert</a>,
       PhD in Computer Science, Google Developer Expert.</p>
    <p>Editorial policy: <a href="/editorial-policy/">Our editorial standards</a></p>
  </footer>
</article>
```

### 25.4 JSON-LD for E-E-A-T (Author & Organization Schema)

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Article",
  "headline": "Complete Guide to Core Web Vitals in 2026",
  "datePublished": "2026-01-15",
  "dateModified": "2026-02-28",
  "author": {
    "@type": "Person",
    "name": "Jane Smith",
    "url": "https://example.com/authors/jane-smith/",
    "jobTitle": "Senior Web Performance Engineer",
    "sameAs": [
      "https://www.linkedin.com/in/janesmith",
      "https://twitter.com/janesmith",
      "https://github.com/janesmith"
    ],
    "worksFor": {
      "@type": "Organization",
      "name": "WebTeam",
      "url": "https://example.com/"
    }
  },
  "publisher": {
    "@type": "Organization",
    "name": "WebTeam",
    "url": "https://example.com/",
    "logo": {
      "@type": "ImageObject",
      "url": "https://example.com/logo.svg"
    }
  },
  "reviewedBy": {
    "@type": "Person",
    "name": "Dr. Web Expert",
    "credential": "PhD in Computer Science"
  }
}
</script>
```

---

## 26. Search Overviews & SGE — Impact on SEO Strategy

Google's Search Overviews (formerly Search Generative Experience / SGE) directly answer user queries at the top of search results using automatically generated summaries, fundamentally changing SEO dynamics as of 2025–2026.

### 26.1 How Search Overviews Change SEO

| Traditional Search | Search Overviews Era |
|-------------------|------------------|
| Users click blue links to find answers | Search generates a direct answer; may not click through at all |
| Position 1 gets ~30% of clicks | Search Overview occupies top real estate; organic results pushed below |
| Keyword matching drives visibility | Semantic understanding and content quality drive citation |
| Volume of content matters | Depth, authority, and uniqueness matter more |

### 26.2 Optimizing for Search Overviews

**Content structure strategies:**
1. **Direct Q&A format** — use clear headings that match user queries, with concise answers immediately following
2. **Structured data** — JSON-LD helps search systems understand and parse content as machine-readable trust signals
3. **First-hand experience** — search systems prefer original research, case studies, and unique data over rehashed content
4. **Concise, scannable formatting** — bulleted lists, tables, definition lists, and numbered steps are more likely to be cited
5. **Schema markup** — FAQPage, HowTo, and Article schemas increase chances of being pulled into search summaries

**Technical optimizations:**

```html
<!-- ✅ Content structured for search citation -->
<article>
  <h2>What is Interaction to Next Paint (INP)?</h2>
  <!-- Direct answer in first paragraph — search engines prefer frontloaded answers -->
  <p><strong>INP (Interaction to Next Paint)</strong> measures the worst-case
     latency of all user interactions on a page. A good INP score is
     <strong>200 milliseconds or less</strong>. It replaced FID as a
     Core Web Vital in March 2024.</p>

  <!-- Followed by deeper detail for users who want more -->
  <h3>How INP Is Calculated</h3>
  <p>INP reports the 95th percentile interaction duration across all
     clicks, taps, and keyboard inputs during a page visit...</p>
</article>
```

### 26.3 Content Freshness

Content freshness is now a significant ranking signal, especially for:
- Technology topics (frameworks, APIs, browser support)
- News and current events
- Regulatory/legal information
- Product reviews and comparisons

**Best practices:**
- Display clear `datePublished` and `dateModified` metadata (both visible and in JSON-LD)
- Regularly audit and update existing content with current information
- Remove or redirect genuinely outdated content that could mislead readers
- Never fake `dateModified` without making real content changes — Google can detect this

---

## 27. XML Sitemap Best Practices & IndexNow Protocol

### 27.1 XML Sitemap Requirements

```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>https://example.com/</loc>
    <lastmod>2026-03-01</lastmod>
    <changefreq>weekly</changefreq>    <!-- Advisory; Google largely ignores -->
    <priority>1.0</priority>           <!-- Advisory; Google largely ignores -->
  </url>
  <url>
    <loc>https://example.com/products/</loc>
    <lastmod>2026-02-28</lastmod>
  </url>
</urlset>
```

**Rules:**
- Include **only canonical, indexable URLs** (return 200, not blocked by robots.txt, not `noindex`)
- Maximum **50,000 URLs** per sitemap file, maximum **50 MB** uncompressed
- Use a **sitemap index file** for larger sites (references multiple sub-sitemaps)
- `<lastmod>` must be accurate — update ONLY when content meaningfully changes
- Declare sitemap in `robots.txt`: `Sitemap: https://example.com/sitemap.xml`
- Submit to Google Search Console and Bing Webmaster Tools
- `<changefreq>` and `<priority>` are effectively ignored by Google — include them only for other search engines

### 27.2 Sitemap Index File (Large Sites)

```xml
<?xml version="1.0" encoding="UTF-8"?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <sitemap>
    <loc>https://example.com/sitemap-pages.xml</loc>
    <lastmod>2026-03-01</lastmod>
  </sitemap>
  <sitemap>
    <loc>https://example.com/sitemap-products.xml</loc>
    <lastmod>2026-02-28</lastmod>
  </sitemap>
  <sitemap>
    <loc>https://example.com/sitemap-blog.xml</loc>
    <lastmod>2026-02-25</lastmod>
  </sitemap>
</sitemapindex>
```

### 27.3 `robots.txt` Best Practices

```
# /robots.txt
User-agent: *
Disallow: /admin/
Disallow: /api/
Disallow: /tmp/
Disallow: /search?          # Internal search results pages
Disallow: /*?sort=           # Parameterized duplicate pages
Disallow: /*?filter=         # Parameterized duplicate pages

# Allow critical resources for rendering
Allow: /styles/
Allow: /js/
Allow: /images/

# Sitemap location
Sitemap: https://example.com/sitemap.xml

# Crawl-delay (respected by Bing, Yandex; ignored by Google)
User-agent: Bingbot
Crawl-delay: 5
```

**Notes:**
- `robots.txt` blocks crawling, NOT indexing. A page can still appear in search results if linked from elsewhere. Use `<meta name="robots" content="noindex">` to prevent indexing.
- Never block CSS/JS files in `robots.txt` — Google needs them to render and understand page layout.

### 27.4 IndexNow Protocol

IndexNow is an open-source protocol that allows instant notification to search engines when content changes — a "push" model vs. the traditional "pull" crawling model.

| Feature | IndexNow | Traditional Crawling |
|---------|----------|---------------------|
| Discovery speed | Near-instant (seconds to minutes) | Hours to weeks |
| Server load | Reduced (fewer unnecessary crawls) | Higher (crawlers revisit on schedule) |
| Supported engines | Bing, Yandex, Seznam, Naver | All (including Google) |
| **Google participation** | **No — Google does not support IndexNow** | Yes |

**Best practice 2026:** Use **both** XML sitemaps (for Google and universal coverage) **and** IndexNow (for instant indexing on Bing/Yandex). They are complementary, not alternatives.

**Implementation:**
```bash
# Submit a URL change via IndexNow API
curl "https://api.indexnow.org/indexnow?url=https://example.com/blog/new-post/&key=YOUR_API_KEY"
```

---

## 28. European Accessibility Act (EAA) — 2025 Enforcement

The **European Accessibility Act (EAA)**, which took effect on **June 28, 2025**, is the most significant accessibility regulation since the ADA. It mandates accessibility for products and services sold in the EU, including:

| Scope | Requirements |
|-------|-------------|
| **E-commerce websites and apps** | Must be accessible to people with disabilities |
| **Banking and financial services** | Online platforms must meet accessibility standards |
| **E-books and digital reading** | Must offer accessible formats |
| **Transport services** | Booking and ticketing websites must be accessible |
| **Telecommunications** | Digital communication tools must be accessible |
| **Consumer hardware** | Computers, smartphones, self-service terminals |

### 28.1 Technical Standard

The EAA references **EN 301 549** as the harmonized standard, which in turn maps to WCAG 2.1 AA (and is expected to update to WCAG 2.2 AA). **Practically, building to WCAG 2.2 AA ensures EAA compliance for web content.**

### 28.2 Key Differences from ADA

| Aspect | ADA (USA) | EAA (EU) |
|--------|-----------|----------|
| Scope | Websites of "places of public accommodation" | Products and services sold in the EU market (broader scope) |
| Standard referenced | None explicitly (courts apply WCAG 2.0/2.1 AA) | EN 301 549 → WCAG 2.1/2.2 AA explicitly |
| Enforcement | Private lawsuits (reactive) | Proactive market surveillance by EU member state authorities |
| Penalties | Compensatory/punitive damages from courts | Fines, product bans, market withdrawal — set by member states |
| Microenterprises | Not exempted | Exempted if < 10 employees AND < €2M annual turnover |

### 28.3 Practical Compliance Checklist

- [ ] WCAG 2.2 AA conformance on all public-facing web pages
- [ ] Accessibility statement published (required by EAA and EN 301 549)
- [ ] Feedback mechanism for users to report accessibility barriers
- [ ] Regular accessibility audits (automated + manual with assistive technologies)
- [ ] Staff training on accessibility requirements
- [ ] Accessibility integrated into design and development processes (not retrofitted)

---

## 29. Emerging Web Platform APIs (2025–2026)

### 29.1 View Transitions API

The View Transitions API provides native, hardware-accelerated animated transitions between UI states (within a page) or between pages (cross-document). It replaces the need for complex JS animation libraries for page transitions.

**Same-document transitions (SPA):** Baseline Newly Available 2025 (Chrome 111+, Safari 18+, Firefox 144+).

```javascript
// Same-document view transition (SPA-style)
document.querySelector('#nav-link').addEventListener('click', async () => {
  // Check for browser support
  if (!document.startViewTransition) {
    // Fallback: just update the DOM directly
    updateContent();
    return;
  }

  // Start the transition — browser snapshots old state → new state
  const transition = document.startViewTransition(() => {
    updateContent(); // Your function to swap DOM content
  });

  // Optionally wait for the transition to finish
  await transition.finished;
});
```

**CSS controls for view transitions:**

```css
/* Default: the entire page cross-fades (old → new) */
::view-transition-old(root) {
  animation: 300ms ease-out fade-out;
}
::view-transition-new(root) {
  animation: 300ms ease-in fade-in;
}

/* Named view transition: a specific element animates independently */
.hero-image {
  view-transition-name: hero;
}

::view-transition-old(hero) {
  animation: 300ms ease-out slide-out-left;
}
::view-transition-new(hero) {
  animation: 300ms ease-in slide-in-right;
}

/* Respect reduced motion preference */
@media (prefers-reduced-motion: reduce) {
  ::view-transition-group(*),
  ::view-transition-old(*),
  ::view-transition-new(*) {
    animation: none !important;
  }
}
```

**Cross-document transitions (MPA — multi-page apps):**

```css
/* Opt in to cross-document view transitions (Chrome 126+) */
@view-transition {
  navigation: auto;
}
```

Both the source and destination pages must include this CSS rule. The browser automatically handles the snapshot and animation when navigating between pages.

---

### 29.2 CSS Scroll-Driven Animations

Scroll-driven animations synchronize animation progress with scroll position — replacing heavy JS scroll listeners with pure CSS. **Baseline: Chrome 115+, Edge 115+, Safari 26+ (partial Firefox support behind flag).**

```css
/* ✅ Reading progress indicator driven by scroll */
.reading-progress {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 4px;
  background: #4f8ef7;
  transform-origin: left;
  transform: scaleX(0);

  /* Bind to page scroll progress */
  animation: grow-progress linear;
  animation-timeline: scroll(root);  /* root = page scroll */
}

@keyframes grow-progress {
  from { transform: scaleX(0); }
  to   { transform: scaleX(1); }
}

/* ✅ Reveal elements as they enter viewport */
.reveal-on-scroll {
  opacity: 0;
  transform: translateY(30px);

  animation: reveal linear both;
  animation-timeline: view();       /* element's visibility in viewport */
  animation-range: entry 0% entry 100%;  /* animate during entry phase */
}

@keyframes reveal {
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* ✅ Parallax effect — purely CSS */
.parallax-bg {
  animation: parallax linear;
  animation-timeline: scroll(root);
}

@keyframes parallax {
  from { transform: translateY(0); }
  to   { transform: translateY(-100px); }
}

/* ✅ Respect reduced motion */
@media (prefers-reduced-motion: reduce) {
  .reveal-on-scroll,
  .parallax-bg,
  .reading-progress {
    animation: none !important;
    opacity: 1 !important;
    transform: none !important;
  }
}
```

**Key CSS functions:**
- `scroll()` — binds to a scrollable element's scroll progress (0% at top, 100% at bottom)
- `view()` — binds to an element's visibility within a scrollport (replaces IntersectionObserver for scroll-linked animations)
- `animation-range` — controls which portion of the timeline the animation occupies (e.g., only during entry, exit, or a specific portion)

---

### 29.3 CSS Anchor Positioning

CSS Anchor Positioning (Chrome 125+, Edge 125+) allows elements to be positioned relative to another element ("anchor") — perfect for tooltips, dropdowns, and context menus that need to follow their trigger element.

```css
/* Define an anchor */
.trigger-button {
  anchor-name: --my-anchor;
}

/* Position relative to the anchor */
.tooltip {
  position: fixed;
  position-anchor: --my-anchor;

  /* Place above the anchor, centered horizontally */
  bottom: anchor(top);
  left: anchor(center);
  translate: -50% -8px;

  /* Auto-fallback if tooltip goes off-screen */
  position-try-fallbacks: flip-block, flip-inline;
}
```

---

### 29.4 The `<search>` Element (HTML)

The `<search>` element (Baseline 2023: Chrome 118+, Firefox 118+, Safari 17+) provides a **semantic wrapper for search functionality**. It carries the implicit ARIA role of `search`, replacing the need for `<form role="search">`.

```html
<!-- ✅ Modern: using <search> -->
<search aria-label="Site search">
  <form action="/search" method="get">
    <label for="q">Search</label>
    <input type="search" id="q" name="q"
           placeholder="Search articles..."
           autocomplete="off">
    <button type="submit">Search</button>
  </form>
</search>

<!-- ❌ Legacy pattern (still valid, but less semantic) -->
<form role="search" action="/search" method="get" aria-label="Site search">
  <!-- ... -->
</form>
```

---

### 29.5 The `inert` Attribute

The `inert` attribute (Baseline 2023: all modern browsers) makes an entire DOM subtree **non-interactive and invisible to assistive technologies**. It replaces complex manual implementations of "disable everything behind a modal."

```html
<!-- ✅ When a modal is open, make everything behind it inert -->
<div id="page-content" inert>
  <header>...</header>
  <main>...</main>
  <footer>...</footer>
</div>

<dialog id="modal" open>
  <!-- Dialog content -->
</dialog>

<!-- Note: <dialog>.showModal() does this automatically.
     Use inert manually only for custom modal implementations. -->
```

```javascript
// Toggle inert programmatically
const content = document.getElementById('page-content');

function openCustomModal() {
  content.inert = true;   // Entire page behind modal becomes inert
  modal.hidden = false;
  modal.querySelector('[autofocus]').focus();
}

function closeCustomModal() {
  content.inert = false;  // Restore interaction
  modal.hidden = true;
  triggerButton.focus();   // Return focus
}
```

**`inert` effects:**
- Element and all descendants are removed from the tab order
- Element and all descendants are hidden from the accessibility tree
- Click/touch events are ignored
- Text selection is prevented
- Visually indicated (browsers apply a slight dimming by default)

---

### 29.6 The `autocomplete` Attribute — Complete Token Reference

The `autocomplete` attribute is critical for both accessibility (WCAG SC 1.3.5 — Identify Input Purpose) and user experience. Browsers use these tokens to auto-fill form fields and to match password manager entries.

| Token | Purpose | Input Type |
|-------|---------|-----------|
| `name` | Full name | `text` |
| `given-name` | First name | `text` |
| `family-name` | Last name | `text` |
| `additional-name` | Middle name | `text` |
| `honorific-prefix` | Mr., Ms., Dr. | `text` |
| `nickname` | Display name / username | `text` |
| `username` | Login username / email | `text`, `email` |
| `new-password` | New password (registration, reset) | `password` |
| `current-password` | Existing password (login) | `password` |
| `one-time-code` | SMS/TOTP verification code | `text` |
| `email` | Email address | `email` |
| `tel` | Phone number (full, with country code) | `tel` |
| `tel-country-code` | Country code only | `text` |
| `tel-national` | National phone number (without country code) | `tel` |
| `street-address` | Full street address (multi-line) | `textarea` |
| `address-line1` | Address line 1 | `text` |
| `address-line2` | Address line 2 | `text` |
| `address-level1` | State / Province / Region | `text` |
| `address-level2` | City / Town | `text` |
| `postal-code` | ZIP / Postal code | `text` |
| `country` | Country code (ISO 3166) | `text` |
| `country-name` | Country name (human-readable) | `text` |
| `cc-name` | Cardholder name | `text` |
| `cc-number` | Credit card number | `text` |
| `cc-exp` | Card expiration (MM/YY or MM/YYYY) | `text` |
| `cc-exp-month` | Expiration month | `text` |
| `cc-exp-year` | Expiration year | `text` |
| `cc-csc` | Card security code (CVV/CVC) | `text` |
| `cc-type` | Card type (Visa, MasterCard, etc.) | `text` |
| `bday` | Birthday (full date) | `date` |
| `bday-day` | Birthday day | `text` |
| `bday-month` | Birthday month | `text` |
| `bday-year` | Birthday year | `text` |
| `sex` | Gender | `text` |
| `organization` | Company / organization name | `text` |
| `organization-title` | Job title | `text` |
| `language` | Preferred language | `text` |
| `url` | Personal website / profile URL | `url` |
| `photo` | Profile photo URL | `url` |
| `webauthn` | WebAuthn credential (passkey login) | — |

**Section prefixes:** Prepend `shipping` or `billing` to differentiate address types:
```html
<input type="text" autocomplete="shipping street-address">
<input type="text" autocomplete="billing postal-code">
```

---

## 30. Additional Structured Data Types (Missing from Original Reports)

### 30.1 HowTo Schema

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "HowTo",
  "name": "How to Optimize Images for Core Web Vitals",
  "description": "Step-by-step guide to converting images to AVIF/WebP and implementing responsive loading.",
  "totalTime": "PT15M",
  "estimatedCost": {
    "@type": "MonetaryAmount",
    "currency": "USD",
    "value": "0"
  },
  "step": [
    {
      "@type": "HowToStep",
      "position": 1,
      "name": "Audit Current Images",
      "text": "Run Lighthouse and identify images that are not in modern formats or are oversized.",
      "url": "https://example.com/guide/#step1"
    },
    {
      "@type": "HowToStep",
      "position": 2,
      "name": "Convert to AVIF and WebP",
      "text": "Use Squoosh or Sharp to convert all images to AVIF (primary) and WebP (fallback).",
      "url": "https://example.com/guide/#step2"
    },
    {
      "@type": "HowToStep",
      "position": 3,
      "name": "Implement <picture> Fallbacks",
      "text": "Wrap each image in a <picture> element with <source> tags for AVIF and WebP, with the original format as the <img> fallback.",
      "url": "https://example.com/guide/#step3"
    }
  ]
}
</script>
```

### 30.2 Event Schema

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Event",
  "name": "WebPerf Summit 2026",
  "description": "Annual conference on web performance optimization.",
  "startDate": "2026-06-15T09:00:00+05:30",
  "endDate": "2026-06-17T18:00:00+05:30",
  "eventAttendanceMode": "https://schema.org/MixedEventAttendanceMode",
  "eventStatus": "https://schema.org/EventScheduled",
  "location": [
    {
      "@type": "Place",
      "name": "GMDC Convention Centre",
      "address": {
        "@type": "PostalAddress",
        "streetAddress": "SG Highway",
        "addressLocality": "Ahmedabad",
        "addressRegion": "Gujarat",
        "postalCode": "380015",
        "addressCountry": "IN"
      }
    },
    {
      "@type": "VirtualLocation",
      "url": "https://example.com/events/webperf-2026/livestream"
    }
  ],
  "organizer": {
    "@type": "Organization",
    "name": "WebTeam",
    "url": "https://example.com/"
  },
  "offers": {
    "@type": "Offer",
    "price": "0",
    "priceCurrency": "INR",
    "url": "https://example.com/events/webperf-2026/register",
    "availability": "https://schema.org/InStock",
    "validFrom": "2026-01-01"
  },
  "image": "https://example.com/events/webperf-2026-banner.jpg"
}
</script>
```

### 30.3 VideoObject Schema

For pages with embedded videos — enables video rich results and video carousels in Google search:

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "VideoObject",
  "name": "How to Pass Core Web Vitals — Complete Guide",
  "description": "Step-by-step tutorial on optimizing LCP, INP, and CLS for Google search rankings.",
  "thumbnailUrl": "https://example.com/videos/cwv-guide-thumbnail.jpg",
  "uploadDate": "2026-02-01",
  "duration": "PT12M30S",
  "contentUrl": "https://example.com/videos/cwv-guide.mp4",
  "embedUrl": "https://www.youtube.com/embed/abc123"
}
</script>
```

### 30.4 SiteNavigationElement Schema

Helps search engines understand your site's primary navigation structure:

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "SiteNavigationElement",
  "name": "Main Navigation",
  "url": [
    "https://example.com/",
    "https://example.com/products/",
    "https://example.com/blog/",
    "https://example.com/about/",
    "https://example.com/contact/"
  ]
}
</script>
```

---

## 31. Updated Master Pre-Launch Checklist — Addendum Items

Add these items to existing checklist sections:

### ✅ 31.1 HTML Structure (Additional Items)

- [ ] `<dialog>` used for all modal dialogs (not custom `<div>` modals) — ensure `showModal()` for modal behavior
- [ ] `<details name="...">` used for exclusive accordions where appropriate (FAQ, settings panels)
- [ ] `<search>` element wraps all search forms (or fallback `<form role="search">`)
- [ ] `inert` attribute used on page content behind custom overlays (not needed if using `<dialog>.showModal()`)
- [ ] Popover API used for tooltips, dropdown menus, and non-modal overlays (replaces custom JS z-index management)

### ✅ 31.2 Accessibility (Additional Items)

- [ ] Drag-and-drop functionality has a single-pointer (click/tap) or keyboard alternative (WCAG 2.5.7)
- [ ] Focus indicators have ≥ 2 CSS pixel perimeter area and ≥ 3:1 contrast (WCAG SC 2.4.13, AAA target)
- [ ] Authentication flows do not require cognitive function tests; passkeys/WebAuthn offered as alternative (WCAG SC 3.3.8/3.3.9)
- [ ] `autocomplete` attributes on ALL personal-data form fields match correct WHATWG tokens
- [ ] Popover elements have appropriate ARIA roles (`role="menu"`, `role="tooltip"`, etc.) and trigger buttons have `aria-expanded`
- [ ] EAA compliance verified (if serving EU customers): accessibility statement published, feedback mechanism available

### ✅ 31.3 SEO (Additional Items)

- [ ] Author information visible on content pages (byline, credentials, links to author profile)
- [ ] E-E-A-T signals present: author schema (JSON-LD `Person`), `reviewedBy`, editorial policy link
- [ ] Content structured for Search Overviews: direct answers in first paragraph, clear heading hierarchy matching user queries
- [ ] `sitemap.xml` submitted in Google Search Console; only canonical indexable URLs included
- [ ] `robots.txt` reviewed — CSS/JS not blocked; internal search/filter URLs blocked to prevent crawl waste
- [ ] IndexNow implemented for Bing/Yandex instant indexing (alongside XML sitemap)
- [ ] Content freshness: `datePublished` and `dateModified` accurate in both visible HTML and JSON-LD

### ✅ 31.4 Performance (Additional Items)

- [ ] View Transitions API used for SPA page transitions (with `prefers-reduced-motion` fallback)
- [ ] CSS scroll-driven animations used instead of JS scroll listeners for parallax / reveal effects
- [ ] `content-visibility: auto` with `contain-intrinsic-size` on long off-screen sections
- [ ] CSS Anchor Positioning used for tooltips/dropdowns instead of JS position calculation libraries (where browser support allows)

---

## Document Metadata (Updated)

| Field | Value |
|-------|-------|
| **Last Updated** | March 2026 |
| **Based on** | WHATWG HTML Living Standard, W3C WCAG 2.2, WAI-ARIA 1.2, MDN Web Docs, web.dev, schema.org, RFC 9110–9114, TC39 Temporal Proposal, APCA/BWMA, Google CWV documentation, W3C CSS Scroll-Driven Animations, View Transitions API, Popover API, EAA (Directive 2019/882), EN 301 549 v3.2.1 |
| **Research sources** | W3C WCAG 2.2 Recommendation, Chrome Platform Status, web.dev, MDN, Google Search Central, Schema.org, EU EAA documentation |
| **Scope** | HTML5, CSS3+, ES2022+, WCAG 2.2 AA, Core Web Vitals, Schema.org, HTTP/3, View Transitions API, Popover API, Scroll-Driven Animations, CSS Anchor Positioning, E-E-A-T |
| **Sections** | 31 total sections |
| **Review schedule** | Revisit for WCAG 3.0 (est. 2028+); JPEG XL browser support; Temporal API baseline; CSS Anchor Positioning cross-browser support; EAA enforcement outcomes |
