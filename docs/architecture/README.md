# Architecture

SABIN's gallery, delivered as a self-contained Craft predefined row: Twig templates rendered today from a mock JSON fixture through a throwaway PHP harness, and in Phase 3 from Craft's own element queries with the templates unchanged.

---

## Data Flow

```
┌──────────────┐   ┌──────────────┐   ┌──────────────────┐   ┌──────────────┐
│ fixture.json │──▶│ FixtureLoader│──▶│ gallery-row.twig │──▶│ served HTML  │
│ row + tiles  │   │ validate,    │   │ + tile includes  │   │ each unique  │
└──────────────┘   │ hydrate to   │   │ + colour macro   │   │ tile exactly │
       ▲           │ HarnessAsset │   │ + ImagerX crops  │   │ once         │
       │           └──────────────┘   └──────────────────┘   └──────┬───────┘
       │                    ▲                                       │
       │                    │ craft-shim emulates the Craft         │ progressive
       │                    │ surface the template touches          │ enhancement
       │                                                            ▼
       │ Phase 3: Craft element queries      ┌────────────────────────────────┐
       └─ replace the fixture; the           │ canvas.js  inert padding       │
          templates do not change            │ inertia.js momentum or nothing │
                                             │ motion.js  onscreen + capped   │
                                             └────────────────────────────────┘
```

The load-bearing split: **the server renders truth, the client renders padding.** Everything a crawler or keyboard user can reach exists in the served HTML.

---

## Reading Order

### Always Load First

1. **[CLAUDE.md](../../CLAUDE.md)** — Principles and invariants
2. **This file** — Architecture overview
3. **[Instructions for Gallery.md](../../Instructions%20for%20Gallery.md)** — Authoritative spec and acceptance criteria

### By Task

| Working On | Load These |
|------------|------------|
| Any template work | [pending/gallery-row.md](pending/gallery-row.md) → [predefined row reference.twig](../../predefined%20row%20reference.twig) |
| Grid, layout modes, responsive floor | [pending/gallery-row.md](pending/gallery-row.md) § Semantics → § Interface Contracts (CSS custom-property contract) |
| Tile routing, links, Build icon | [pending/gallery-row.md](pending/gallery-row.md) § Semantics (link rendering) |
| Canvas depth, repeats | [pending/gallery-row.md](pending/gallery-row.md) § Semantics (canvas depth) |
| Scroll inertia | [pending/gallery-row.md](pending/gallery-row.md) § Semantics (scroll inertia) |
| Motion tiles, performance budget | [pending/gallery-row.md](pending/gallery-row.md) § Semantics (motion, media transforms) |
| Fixture shape, Craft fields | [pending/gallery-row.md](pending/gallery-row.md) § Configuration → § Validation Rules |
| Phase boundaries, what's out of scope | [project_brief.md](../../project_brief.md) → [pending/gallery-row.md](pending/gallery-row.md) § What Doesn't Change |

### Independent References

- [PROCESS.md](PROCESS.md) — Development process
- [../CAPABILITIES.md](../CAPABILITIES.md) — Capability overview and status

---

## Core Concepts

| Concept | One-liner | Doc |
|---------|-----------|-----|
| Predefined row | The deliverable is a reusable content block, not a page — it assumes nothing above or below it | [pending/gallery-row.md](pending/gallery-row.md) |
| Craft shim | Local PHP emulation of the Craft/ImagerX surface, so templates stay Craft-native today | [pending/gallery-row.md](pending/gallery-row.md) |
| Server truth / client padding | Unique tiles server-rendered once; canvas padding cloned client-side and inert | [pending/gallery-row.md](pending/gallery-row.md) |
| One layout engine | Swiss and masonry are the same dense grid; only row spans vary | [pending/gallery-row.md](pending/gallery-row.md) |
| Gutter single source of truth | One custom property feeds both `gap` and the two-column-floor `minmax()` | [pending/gallery-row.md](pending/gallery-row.md) |
| Dual tile destinations | Primary whole-tile anchor plus an optional sibling Build-icon anchor, never nested | [pending/gallery-row.md](pending/gallery-row.md) |

---

## Invariants

Non-negotiable rules (see [CLAUDE.md](../../CLAUDE.md) for the full list):

| Invariant | Meaning |
|-----------|---------|
| Server-rendered content | Every unique tile's alt text, copy, CTA label and `href` are in the served HTML without JS |
| Real anchors only | No routable tile is a `<button>` or click-handled `<div>`; the two tile links are siblings |
| No duplicate DOM ids | Each unique tile appears once; client-generated repeats are inert, `aria-hidden`, untagged |
| One layout engine | Both modes are the same dense-packed grid; two columns minimum at any width |
| Settings live on the row | Every gallery setting reads off `row.*`, Craft-driven, never visitor-facing |
| Inertia degrades to native | Off or reduced-motion leaves no scroll interception in place at all |
| Brand tokens only | No free-form colour, size, or spacing value anywhere |

---

## Implementation Status

| Component | Status | Location |
|-----------|--------|----------|
| Row template + tile includes | Complete | `templates/` |
| Grid + brand tokens (CSS) | Complete | `src/css/gallery.css` |
| Craft shim + fixture loader | Complete | `harness/craft-shim/` |
| Fixture (32 tiles) | Complete | `data/gallery.fixture.json` |
| Preview command | Complete | `bin/preview` |
| Media preparation (MP4 transcode) | Complete | `bin/prepare-media` |
| Structural verification | Complete — 32 assertions | `tests/html/structure.php` |
| Canvas padding repeats (JS) | Complete | `src/js/gallery-canvas.js` |
| Scroll inertia (JS) | Complete | `src/js/gallery-inertia.js` |
| Motion playback + cap (JS) | Complete | `src/js/gallery-motion.js` |
| Browser behaviour suite | Complete — 32 checks | `tests/browser/sprint2.js` |

Sprint 1 shipped **no JavaScript at all**, so "works without JS" is structural rather than a test to remember. Sprint 2 adds the enhancement layer on top without changing any server-rendered output.

Toolchain: PHP 8.5.9, Composer 2.10.2, Twig 3.28.0, Tailwind standalone 4.3.3, ffmpeg 8.1.2.

---

## Document Index

| Topic | Document |
|-------|----------|
| Development process | [PROCESS.md](PROCESS.md) |
| Gallery row design | [pending/gallery-row.md](pending/gallery-row.md) |
| Capabilities and status | [../CAPABILITIES.md](../CAPABILITIES.md) |
| Doc template | [TEMPLATE.md](TEMPLATE.md) |

---

*This document is the entry point. Details live in linked docs and code.*
