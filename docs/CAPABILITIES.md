# SABIN Gallery Row Capabilities

**Purpose:** High-level overview of what the system does.

**Audience:** Users, LLM planners, architects

---

## Overview

The gallery row renders SABIN's new homepage centrepiece: a deep, densely packed canvas of square tiles — product photography, short silent motion loops, and brand text blocks — that a visitor scrolls through with optional momentum. Every tile is a real link into the site, and a subset carry a second link into Build, SABIN's product configurator.

It ships as a Craft *predefined row*, not a page, so Craft can place and reorder it among other rows. Phase 1 builds and prototypes it standalone against a mock JSON fixture shaped like a Craft GraphQL response; Phase 3 swaps the fixture for Craft queries with the templates intact.

```
┌──────────────┐   ┌──────────────┐   ┌──────────────────┐   ┌──────────────┐
│ fixture.json │──▶│ PHP harness  │──▶│ Twig row         │──▶│ served HTML  │
│ row + tiles  │   │ (throwaway)  │   │ template + macros│   │ unique tiles │
└──────────────┘   └──────────────┘   └──────────────────┘   │ once, no JS  │
       ▲                                                     │ required     │
       │ Phase 3: Craft element queries                      └──────┬───────┘
       │ replace the fixture, templates unchanged                   │
                                                                    ▼
                                                    ┌───────────────────────────┐
                                                    │ Alpine + vanilla JS       │
                                                    │ enhancement only:         │
                                                    │ • inert padding repeats   │
                                                    │ • scroll inertia          │
                                                    │ • motion play/pause + cap │
                                                    └───────────────────────────┘
```

---

## Tile Grid & Layout Engine

A single fluid CSS Grid that reflows column count continuously with viewport width — no discrete breakpoint jumps — with a hard floor of exactly two columns at any width. Tiles occupy arbitrary `cols x rows` spans on that same auto-generated track grid, packed densely so smaller tiles backfill the holes larger ones leave.

**Key features:**
- `repeat(auto-fill, minmax(min(card-target, calc((100% - gap)/2)), 1fr))`, matching Build 2.0's confirmed mechanism
- Configurable target card size (default 2in per 1x1 cell) and gutter (`row.tileGap`, default 4px), driven from single CSS custom properties
- Two Craft-selected modes on one engine: Swiss (uniform squares) and masonry (varying row spans) — `row.layoutMode`
- Dense packing with the welcome tile explicitly pinned to the first cell; no empty cells left in the layout
- Completing the final row depends on the canvas padding repeats, so it lands in Sprint 2 — until then a ragged last row is expected

**Status:** Complete (Sprint 1) | **Architecture:** [gallery-row.md](architecture/pending/gallery-row.md)

---

## Tile Rendering & Routing

Three tile types — image, motion, text — rendered from one media field so a Craft admin can switch a tile's type as a data change, not a schema change. Media tiles carry two independent destinations as sibling anchors: the whole tile routes by `contentType`, and an optional corner icon routes into Build.

**Key features:**
- Primary link stretched `absolute inset-0`; Build icon a second absolutely-positioned sibling anchor above it, each with its own distinct accessible label
- `contentType` drives the primary destination: in-situ / mid-zoom / detail / sketch → product page, swatch → swatches page, factory → About page
- Corner icon (default inline sliders SVG, overridable via `row.buildIcon`) only on media tiles with a live `buildLink`; never on text tiles
- `live: false` destinations render non-interactive — not announced as links, not in the tab order
- Text tiles constrained to brand tokens (named background, padding step, type level, CTA style) with `cta-only` or `whole-tile` link behaviour, going full grid width at narrow viewports

**Status:** Complete (Sprint 1) | **Architecture:** [gallery-row.md](architecture/pending/gallery-row.md)

---

## Pseudo-Infinite Canvas

The canvas is long rather than truly infinite: deep enough to scroll through, padded by repeating tiles when there aren't enough unique ones to reach the configured minimum depth, and it grows automatically as tiles are added in Craft.

**Key features:**
- `row.minCanvasDepth` in screens (default ~15), editable independently of tile count
- The served document contains each unique tile exactly once; padding repeats are generated client-side
- Repeats carry no `data-tile-id`, are `aria-hidden`, and are out of the tab order — but stay clickable by pointer, since on a deep canvas they are most of what a visitor clicks
- Clone media is parked until it nears the viewport, so page weight is bounded by the unique-tile count rather than by canvas depth
- Vertical scroll only, staying within the site's normal breakpoint margins

**Status:** Complete (Sprint 2) | **Architecture:** [gallery-row.md](architecture/pending/gallery-row.md)

---

## Scroll Inertia

Optional momentum scrolling that gives the canvas motion rather than a static grid, with the feel tunable in Craft and a genuine native-scroll fallback.

**Key features:**
- `row.scrollInertia.enabled` toggle, plus independent speed (slow/medium/fast) and feel (smooth/glide/overshoot); defaults medium and smooth
- New scroll input retargets mid-animation immediately — never queued behind a decaying glide
- Disabled or under reduced-motion, falls back to plain native scroll with no interception left in place, preserving keyboard scroll, scrollbar drag and find-in-page

**Status:** Complete (Sprint 2) | **Architecture:** [gallery-row.md](architecture/pending/gallery-row.md)

---

## Row Data Layer & Craft Handoff Shape

The row is the unit of configuration: gallery settings are fields on it, tiles are a nested field within it. Nothing is stored outside the row, so the same template renders a different fixture with no code edit — which is exactly what makes the Phase 3 handoff a data-source swap.

**Key features:**
- Fixture mirrors a plausible Craft GraphQL response; `theme`, `layoutMode`, `minCanvasDepth`, `tileGap`, `maxConcurrentMotion`, `buildIcon`, `scrollInertia`, `tiles`
- Stable per-tile `id` exposed as `data-tile-id` on the canonical instance only
- Static/dynamic designation carried as data so Phase 2 can add a shuffle scheduler without restructuring
- `tags` carried through unused in Phase 1 (Phase 2 tag-based skewing)

**Status:** Complete (Sprint 1) | **Architecture:** [gallery-row.md](architecture/pending/gallery-row.md)

---

## Local Preview Harness & Asset Pipeline

A single documented command previews the row full-screen with no navigation, footer or sibling rows. The harness is throwaway and never delivered — Foster receives only the row template and its field definitions.

**Key features:**
- PHP + Twig renders the fixture to HTML; Tailwind standalone CLI builds the stylesheet
- Placeholder blocks can be added above and below the row to prove it depends on nothing around it
- Media prepared to budget: modern formats with responsive srcset, muted MP4 for motion, lazy-loading below the fold, no upscaling of undersized sources
- Works from a clean checkout

**Status:** Complete (Sprint 1) | **Architecture:** [gallery-row.md](architecture/pending/gallery-row.md)

---

## Invariants

Properties that always hold across the system. See [CLAUDE.md](../CLAUDE.md) for the full table and rationale.

| Invariant | Meaning |
|-----------|---------|
| Server-rendered content | Every unique tile's alt text, copy, CTA label and `href` are in the served HTML without JS |
| Real anchors only | No routable tile is a `<button>` or click-handled `<div>`; the two tile links are siblings, never nested |
| No duplicate DOM ids | Each unique tile appears once; client-generated repeats are inert and untagged |
| One layout engine | Both layout modes are the same dense-packed grid; two columns minimum at any width |
| Settings live on the row | Every gallery setting reads off `row.*` and is Craft-driven, never visitor-facing |
| Reduced motion is absolute | No momentum scroll, no tile transitions, no motion autoplay under `prefers-reduced-motion` |
| Brand tokens only | No free-form colour, size, or spacing value is accepted anywhere |

---

## Potential Future Functionality

| Feature | Description |
|---------|-------------|
| Periodic auto-shuffle | Phase 2 — scheduler over the existing static/dynamic designation; cadence still TBD |
| Tag-based content skewing | Phase 2 — weight the mix toward a product using the tags already carried in the data layer |
| Live Build links for all tiles | Phase 2 — Vicky sources/matches Rhino compositions so every corner icon links live |
| Full image library | Phase 2 — beyond the initial ~30, including abstract imagery |
| schema.org / JSON-LD markup | Phase 3 — bundled with the Catalog/SEO migration; tiles must not need re-rendering to add it |
| Truly infinite canvas | Kept possible by the repeat mechanism, not built |

---

*High-level overview. See architecture docs for design details.*
