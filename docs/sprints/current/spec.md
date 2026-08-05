# Sprint: gallery-row-static

## Purpose

Deliver the gallery row as a complete, correct, **zero-JavaScript** predefined row: 30 unique tiles server-rendered once each into one fluid dense grid, every media tile square-cropped through the ImagerX contract, every routable tile a real anchor. A Craft admin changes `row.*` fields or a tile's type/size in the fixture and the output changes with no code edit.

The sprint boundary is deliberate: **this sprint ships no JS at all.** Everything a crawler, an answer engine, or a keyboard user can reach is built here, so "works without JavaScript" is structural rather than a test to remember. Sprint 2 adds canvas padding, inertia and motion playback strictly as enhancement on top.

## Architecture Reference

[pending/gallery-row.md](../../architecture/gallery-row.md) — contracts, semantics and validation rules are defined there and referenced, not duplicated here.

## Scope

**Capabilities touched:**
- Tile Grid & Layout Engine: grid contract, two-column floor, both layout modes, dense packing, pinned welcome tile
- Tile Rendering & Routing: all three tile types, dual sibling anchors, Build icon, `live: false` handling, brand-token text tiles
- Row Data Layer & Craft Handoff Shape: fixture, validation, Craft shim, Craft-native template idioms
- Local Preview Harness & Asset Pipeline: preview command, media prep, derivative generation, budget report

**Not included (Sprint 2):** canvas padding repeats, scroll inertia, motion playback/concurrency, windowing. Motion tiles render in this sprint as poster-bearing `<video>` elements with correct markup and no playback logic.

**Not included at all (Phase 2/3):** shuffle scheduling, tag UI, schema.org, live Craft connection.

## Breaking Changes

None — greenfield.

## Success Criteria

- [ ] With JS disabled, view-source contains all 30 unique tiles: every media tile's alt text, the text tile's heading/body/CTA label, and a real `href` on every routable tile
- [ ] Served document has zero duplicate DOM ids; `data-tile-id` appears exactly once per unique tile
- [ ] No routable tile is a `<button>` or click-handled `<div>`; `live: false` destinations render non-interactive and are not announced as links
- [ ] Primary and Build-icon links are sibling anchors, never nested, with distinct accessible labels
- [x] Every unique routable tile is keyboard-reachable in **document** order with a visible focus ring (reworded from "visual order" — decided 5 Aug 2026, see README)
- [ ] Corner icon appears only on image/motion tiles with a live `buildLink`; never on the text tile
- [ ] At 320px the grid shows exactly two columns, never one, at every `tileGap` value tested; widening reflows fluidly with no breakpoint jump
- [ ] At 320px the welcome tile spans full grid width with nothing hidden or shrunk
- [ ] Every media tile renders square regardless of source ratio, including the four ≥2:1 files, with no letterboxing, distortion or upscaling
- [ ] Switching `layoutMode` to `masonry` staggers rows while 2x2 tiles still render 2x2, on the same grid
- [ ] Changing `layoutMode`, `tileGap`, `cardTarget`, a tile's `size` or a tile's `type` in the fixture changes output with no code edit
- [ ] Both modes leave no empty cells; the welcome tile occupies cell 1 under dense packing
- [ ] No text-tile option accepts a free-form colour, size or spacing value
- [ ] A single documented command previews the row full-screen with no nav, footer or sibling rows, from a clean checkout
- [ ] The row renders identically with placeholder blocks above and below it
- [ ] Initial-viewport weight and request count reported as numbers
- [ ] Undersized and format-mismatched assets reported, never silently upscaled

## Contracts

Defined in the architecture doc § Interface Contracts. This sprint implements:

- `HarnessAsset` — Craft Asset surface (`getUrl`, `getWidth`, `getHeight`, `getFocalPoint`, `hasAlpha`, `eagerly`, `one`)
- `HarnessImagerX` — `transformImage`, `srcset`, `undersizedReport`
- `FixtureLoader` — load, validate (all 13 fail-fast rules), hydrate to `HarnessAsset`
- `render_harness` — fixture → complete HTML document, with optional sibling placeholder blocks
- CSS custom-property contract — `--gallery-gap`, `--gallery-card-target`, `--gallery-min-depth`
- Twig: `gallery-row.twig`, `gallery-tile-media.twig`, `gallery-tile-text.twig`, `gallery-build-icon.twig`

**No code default may supply a row field.** Every gallery setting comes from the fixture; a missing one is a validation error, never a hardcoded fallback (CLAUDE.md Principle #2).

---

## Phases

### Phase 1: Craft shim + fixture loader

**Delivers:** A minimal 3-tile fixture rendering through a Craft-native Twig template. Proves the central architectural bet — that the template can use `craft.imagerx.*`, asset `.alt`/`.focalPoint`, `eagerly().one()` and the colour macro while running outside Craft.

**Demo:** `demos/phase1-shim.php` renders 3 tiles (image, motion, text) and prints the resulting HTML plus the validation report.

**Files:**
| Action | File |
|--------|------|
| Create | `harness/craft-shim/HarnessAsset.php` |
| Create | `harness/craft-shim/HarnessTransform.php` |
| Create | `harness/craft-shim/HarnessImagerX.php` |
| Create | `harness/craft-shim/FixtureLoader.php` |
| Create | `harness/render.php` |
| Create | `harness/harness.twig` |
| Create | `templates/_macros/color.twig` |
| Create | `data/gallery.fixture.json` (3 tiles, grown in Phase 5) |

**Tests:**
- Duplicate tile id → `FixtureValidationException` naming the id, before any render
- `size: "2"` / `"2x"` / `"0x1"` → rejected with the offending value
- Media tile with empty `alt` → rejected
- Motion tile without `poster` → rejected
- Text tile carrying a `buildLink` → rejected
- Unknown `contentType` → rejected naming the value
- Unknown enum on `layoutMode`/`theme`/`speed`/`feel` → rejected
- Missing `tileGap` → rejected (no code default substituted)
- `eagerly().one()` chain returns the asset itself, so template idioms resolve
- `getFocalPoint()` returns centre when unset, the fixture value when set

**Acceptance criteria:**
- [ ] Template contains no local abstraction over ImagerX or the colour macro
- [ ] All 13 validation rules fire with the documented messages
- [ ] Tests pass

---

### Phase 2: Media pipeline

**Delivers:** Square 1:1 derivatives via the ImagerX contract with focal-point cropping, width-descriptor srcset, no upscaling, plus the transcoded motion asset and the asset report.

**Demo:** `demos/phase2-media.php` transforms one panoramic, one square, one undersized and one alpha-carrying asset; prints derivative paths, dimensions, srcset strings and the undersized/mismatch report.

**Files:**
| Action | File |
|--------|------|
| Modify | `harness/craft-shim/HarnessImagerX.php` (GD crop + WebP write + cache) |
| Create | `bin/prepare-media` |
| Create | `dist/transforms/` (gitignored cache) |
| Modify | `.gitignore` |

**Implementation notes:**
- Transcode `FACTORY-SOUND_CUTTING-MACHINE.mp4` (1920×1080, 2.4s, 20Mbps, no audio stream) to a square, muted, web-weight loop with `-an`, centre-cropped 1080×1080, faststart, plus a poster still extracted from frame 1.
- Never upscale: a requested width above native yields native size, recorded in `undersizedReport()`.
- Three source files are AVIF with `.png`/`.jpg` extensions — detect by content, not extension, and report the mismatch.

**Tests:**
- 3.57:1 panoramic → exactly square output, no letterbox, no distortion
- Focal point `{x: 0.8, y: 0.3}` → crop window shifts accordingly vs centre
- 220px source at requested 384px → output 220px, listed in the undersized report
- 1200×360 source → square crop 360px, listed in the undersized report
- AVIF-with-wrong-extension → decoded correctly, reported as mismatched
- Alpha-carrying source → `hasAlpha()` true, tile gets a defined background
- `srcset()` → ascending width descriptors matching the derivatives
- Transcoded MP4 → square, no audio stream, materially smaller than 6MB

**Acceptance criteria:**
- [ ] Every one of the 30 stills produces a square derivative
- [ ] No derivative exceeds its source's native dimensions
- [ ] Report lists exactly the 5 undersized and 3 mismatched assets
- [ ] Tests pass

---

### Phase 3: Grid engine + brand tokens

**Delivers:** The one dense CSS Grid, brand tokens as `@theme` properties, named type scale, both layout modes, the two-column floor driven from a single gutter property.

**Demo:** `demos/phase3-grid.html` renders the grid at 320/375/768/1440px with `tileGap` at 0/4/16px and both layout modes, with column count and computed gap annotated.

**Files:**
| Action | File |
|--------|------|
| Create | `src/css/gallery.css` |
| Create | `templates/_rows/gallery-row.twig` (root + grid, tiles stubbed) |
| Create | `bin/preview` |

**Implementation notes:**
- `--gallery-gap` and `--gallery-card-target` written once into the root's inline style from `row.*`; consumed by both `gap` and the `minmax()` floor.
- Text-tile full-width threshold uses a **container query** on the grid, not a viewport media query, so the row stays independent of page width.
- Masonry varies row span as a deterministic function of tile index — same layout every render.

**Tests:**
- 320px, `tileGap` ∈ {0, 4, 16} → exactly 2 columns in every case
- Column count increases monotonically with width, no discrete jump
- `cardTarget` change → column count changes with no code edit
- Swiss → all row spans equal declared M; masonry → spans vary, column spans preserved
- 2x2 tile renders 2 columns × 2 rows in both modes
- Dense packing leaves no empty cells; welcome tile occupies cell 1
- Root element carries no page-level or reset CSS

**Acceptance criteria:**
- [ ] Gutter has exactly one source of truth, verified by changing it and re-checking the floor
- [ ] No `sm:`/`md:`/`lg:` column utilities anywhere
- [ ] Tests pass

---

### Phase 4: Tile rendering + routing

**Delivers:** All three tile types fully rendered — dual sibling anchors, Build icon with contrast treatment and touch visibility, `live: false` as non-interactive, brand-token text tile.

**Demo:** `demos/phase4-tiles.php` renders a matrix: live/dead primary × live/absent buildLink × image/motion/text, with the accessibility tree annotated per tile.

**Files:**
| Action | File |
|--------|------|
| Create | `templates/_components/includes/gallery-tile-media.twig` |
| Create | `templates/_components/includes/gallery-tile-text.twig` |
| Create | `templates/_components/includes/gallery-build-icon.twig` |
| Modify | `templates/_rows/gallery-row.twig` (wire includes) |
| Modify | `src/css/gallery.css` (token classes, focus-visible, icon chip) |

**Implementation notes:**
- Primary anchor `absolute inset-0`; icon a sibling anchor above it in stacking order. The two are never nested — a `whole-tile` text tile renders its CTA as a non-anchor visual inside the single tile anchor.
- Icon needs its own contrast treatment (scrim/shadow/blurred chip) and is permanently visible under `@media (hover: none)`.
- Text-tile tokens resolve through maps; an unknown token is a validation error, not a passthrough.

**Tests:**
- `destination.live: false` → no `<a>`, no `href`, not tabbable, alt text still present
- `buildLink.live: false` or null → no icon element at all
- Text tile with `buildLink` in fixture → validation error (Phase 1 rule)
- Primary and icon anchors are siblings (assert neither is a descendant of the other)
- Each anchor has a distinct non-empty accessible label
- `cta-only` → tile body not a link; `whole-tile` → one anchor, CTA not an anchor
- Free-form hex in `text.background` → validation error
- Focus-visible ring present and visible on both anchors
- Icon legible over a light source and a dark source
- Alpha-carrying media → tile has a defined background colour

**Acceptance criteria:**
- [ ] Exactly two tiles render a Build icon (the two pre-selected renders)
- [ ] No nested anchors anywhere in the document
- [ ] Tests pass

---

### Phase 5: Full fixture + verification

**Delivers:** All 30 tiles, the documented preview command, README, Craft field definitions, and the measured budget report.

**Demo:** `bin/preview` — the real thing, full-screen, plus `--with-siblings` proving row independence.

**Files:**
| Action | File |
|--------|------|
| Modify | `data/gallery.fixture.json` (3 → 30 tiles) |
| Create | `docs/craft-field-definitions.md` |
| Create | `tests/acceptance/*.md` (5 files per architecture doc) |
| Modify | `README.md` |

**Tests:**
- All 30 tiles present in view-source with JS disabled
- Zero duplicate ids across the full document
- Row renders identically with placeholder blocks above and below
- Preview command works from a clean checkout
- Initial-viewport weight and request count measured and recorded
- Lighthouse mobile run recorded (Performance/Accessibility/SEO/LCP as numbers)
- Tab order matches visual order with a visible focus ring throughout

**Acceptance criteria:**
- [ ] 30 tiles, mostly image, ≥1 motion, 1 welcome text tile
- [ ] Every number reported as a number, not asserted
- [ ] Tests pass

---

## Out of Scope

- Any JavaScript whatsoever — including the padding repeats, even though the final-row-completeness criterion depends on them. Sprint 1 renders unique tiles only; a possibly-ragged final row is expected and correct here.
- Motion playback, pausing, and concurrency capping — markup only this sprint.
- Windowing/recycling — a Sprint 2 fallback if the budget can't be met by lazy-loading.
- Alpine.js — nothing in this sprint needs it.
- Replacing `templates/_macros/color.twig` with Foster's real macro (handoff-time swap).

---

## Test Strategy

| Type | Scope | Location |
|------|-------|----------|
| Unit (PHP) | Validation rules, shim behaviour, transforms, srcset, no-upscale | `tests/shim/` |
| Structural (HTML assertions) | Anchors, ids, labels, tab order, alt text presence | `tests/html/` |
| Visual/layout | Column counts, gutter floor, layout modes, square crops | `tests/layout/` |
| Acceptance | The spec's own criteria, as recorded checks | `tests/acceptance/` |

Tests live in the directory matching the code under test. No sprint-named test files.

---

## Risks

| Risk | Mitigation |
|------|------------|
| Shim drifts from real ImagerX semantics, breaking Phase 3 handoff | Keep the shim's public surface to exactly what the template calls; document each emulated method against ImagerX's documented behaviour |
| GD's crop quality differs from ImagerX's | Only crop geometry and no-upscale behaviour are contractual; quality is Foster's pipeline in production |
| `2in` = 192 CSS px may not be the intent | Assumption stated in the architecture doc; `cardTarget` is a fixture field, so retuning is a data change |
| Foster's type-scale and token names unknown | All names confined to `gallery.css` + token maps so a rename is one file |
| Webfonts unlicensed | Font-loading strategy built with a documented swap point; flagged, not silently substituted |
| Non-square spans not pixel-exact | Documented constraint; milestone uses only square spans where it's exact |

---

## Definition of Done

- [ ] All 5 phases complete
- [ ] All tests pass
- [ ] Reviewed (/review-sprint)
- [ ] CAPABILITIES.md statuses updated; architecture doc pruned of anything consumed
- [ ] No principle violations (checked against CLAUDE.md DO NOT list)
- [ ] Zero JavaScript shipped in this sprint
