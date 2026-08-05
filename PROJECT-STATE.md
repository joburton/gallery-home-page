# Project State — SABIN Gallery Row

**Read this first.** It is the orientation document: what this project is, what has been
built, which documents are authoritative, and what is still open. Last updated 5 Aug 2026.

---

## What this is

A **Craft CMS predefined row** that renders SABIN's gallery — a deep, densely packed canvas
of square image, motion and text tiles — as crawlable, server-rendered HTML. The gallery
becomes SABIN's new homepage.

It is **not a page**. It is a self-contained, reusable content block that Craft places and
reorders among other rows, matching the pattern the SABIN site already uses. It is
developed standalone so it can be prototyped and judged before going near the live site,
but it must never assume anything about what sits above or below it.

```
data/gallery.fixture.json ──▶ PHP harness ──▶ Twig row template ──▶ HTML + Tailwind CSS
(shaped like Craft's         (throwaway,      (THE deliverable,              │
 own response)                emulates         + field defs)                 ▼
                              Craft's API)                            Alpine / vanilla JS
    ▲                                                                 enhances only:
    └── Phase 3: Foster swaps the fixture for Craft's                  · inert padding repeats
        element queries. The templates do not change.                  · scroll inertia
                                                                       · motion play/pause
```

The templates are authored against Craft's real API (`craft.imagerx.transformImage`,
`row.tiles`, asset focal points, the shared colour macro). The harness fakes those calls
locally. **The template never learns it isn't running in Craft**, which is what makes the
Phase 3 handoff a data-source swap rather than a rewrite.

---

## Current state — Phase 1 build is complete

Sprint `gallery-row-static`, all five phases complete. 32 structural assertions passing,
14 validation rules firing before render.

| Built | Where |
|---|---|
| Row template + components | `templates/_rows/`, `templates/_components/` |
| Craft API shim | `harness/craft-shim/` |
| Grid, inertia, motion JS | `src/js/` |
| Brand tokens + type scale | `src/css/gallery.css` |
| 33-tile fixture | `data/gallery.fixture.json` |
| Preview + media pipeline | `bin/preview`, `bin/prepare-media` |
| Tests | `tests/html/`, `tests/browser/` |

Fixture composition: 33 tiles — 30 image, 1 motion, 2 text. Nine are 2x2. Two carry Build
links.

### Run it

```bash
composer install     # first time only
bin/preview          # then open http://localhost:8080
```

`bin/preview --with-siblings` adds placeholder blocks above and below the row. **Nothing
about the gallery should change** — that is the row-independence check.

Drive the prototype from the URL without editing the fixture:
`?layout=masonry` `?gap=16` `?card=3in` `?theme=dark` `?depth=3` `?inertia=off`
`?speed=fast&feel=overshoot`. None of these ship — in production every one comes from
Craft, and there is no visitor-facing control for any of them.

---

## Which document is authoritative

Read in this order. **Where they disagree, the higher entry wins.**

| Document | Role |
|---|---|
| `CLAUDE.md` | Principles and hard invariants. Never violate these. |
| `docs/architecture/gallery-row.md` | The design, and the reasoning behind it. |
| `docs/craft-field-definitions.md` | The Phase 3 handoff contract for Foster. |
| `docs/sprints/current/` | What was built, and what was verified. |
| `predefined row reference.twig` | Foster's production row — conventions to follow, never a file to edit. |
| `project_brief.md` | Multi-phase context: Phases 0–3, team, scope boundaries. |
| `Instructions for Gallery.md` | **Superseded.** The original brief. Useful for *why* a decision was made; not current on *what* is built. |

The code is the specification for anything already built. Documentation records rationale
and contracts; it does not duplicate the implementation.

---

## Hard invariants

The full set is in `CLAUDE.md`. The ones most easily broken by accident:

- **Crawlable content is the product.** Every unique tile's alt text, copy, CTA label and
  `href` are in the served HTML without JS. JS may enhance; it must never be the only path
  by which tile content reaches the DOM.
- **Real anchors only.** No routable tile is a `<button>` or a click-handled `<div>`.
  Build 2.0 uses `<button>` for its cards — this is the one place not to copy it.
- **The two tile links are siblings, never nested.** Whole-tile link plus corner Build
  icon, each with its own distinct accessible label.
- **No duplicate DOM ids.** Each unique tile appears once; client-generated padding
  repeats are inert, `aria-hidden`, and outside the tab order.
- **One layout engine.** Swiss and masonry are the same dense-packed grid; the modes
  differ only in whether row spans vary. Two columns minimum at any viewport width.
- **Settings live on the row.** Every gallery setting reads off `row.*`, is Craft-driven,
  and is never visitor-facing.
- **Reduced motion is absolute.** No momentum scroll, no tile transitions, no motion
  autoplay under `prefers-reduced-motion`.
- **Brand tokens only.** No free-form colour, size or spacing value is accepted anywhere.

---

## Confirmed with Foster Commerce

- Tailwind **^4.0.0** — matches what is installed here (4.3.3)
- **GSAP ^3.13.0** is already installed site-wide and available to use
- One row type uses sticky positioning, constrained within its context
- The site header is `position: fixed` to the viewport
- Repo read access is being arranged

---

## Open — needs a decision or an action

### Content

1. **Neue Montreal Light is missing.** Only Medium was supplied. The brief specifies Light
   for small running text, and asking for a heavier weight with only Medium present made
   the browser synthesise a fake bold. Chase the Light weight from the foundry.
2. **Messina Modern Light and LightItalic are present but unreferenced.** Wire them up or
   confirm they are not needed.
3. **Messina Modern licence** (Luzi Type) covers a single domain with a monthly page-view
   cap. Check that cap against expected homepage traffic before go-live.
4. **Sixteen source images have a short edge under 768px**, which is the minimum for a 2x2
   tile. Worst offenders: three material swatches at 220px, and two panoramas that yield
   only 360px once square-cropped. Re-export from originals.
5. **Three files are AVIF with the wrong extension** — `cable-stainless-steel.png`,
   `cord-green.jpg`, `thread-8470.jpg`. Browsers cope; Craft and ImagerX may not.
6. **The motion source is still 5.8MB at 20Mbps.** It needs transcoding to a square, muted,
   web-weight loop before it can ship. `bin/prepare-media` is the place for it.

### Decisions

7. **The two live Build links do not line up.** The brief assumed two in-situ office
   renders. The Rhino compositions folder holds *Layer Rectangle Downlight* and *Obforma
   Downlight* — clouds and an elevator — while the actual office render is
   `linear-aero-rendering-ostap-office-array-WARMER`. Confirm which tiles link to which
   compositions.
8. **No code default backs any row field.** A missing field is a validation error rather
   than a silent fallback, so that no gallery setting can live outside the row. This means
   every row field needs its default set at the field level in Craft — flag this to Foster
   so it does not surprise them during setup.
9. **Focal points are a recurring content step.** Any uploaded image whose subject sits
   off-centre needs its focal point set in Craft, or the square crop may cut the subject
   out.

---

## Out of scope for Phase 1

No React. No JavaScript templating or static-site generator in place of Twig. No
drag/reorder. No live Craft connection yet. No periodic auto-shuffle logic — the data layer
is shuffle-ready but the cadence is still undecided. No visitor-facing controls for
designation, layout mode or inertia. No tag filtering or tag UI. No footer behaviour of any
kind. No true GIF encoding. No schema.org markup yet. No full 300+ image library.

Phase 2 adds the full Build connection, periodic auto-shuffle and tag-based skewing.
Phase 3 is the Foster handoff, go-live, and the Catalog/Downloads merge.
