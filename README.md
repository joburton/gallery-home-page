# SABIN Gallery Row

A self-contained Craft **predefined row** that renders SABIN's gallery — a densely packed canvas of image, motion and text tiles — as crawlable, server-rendered HTML.

Phase 1 builds and prototypes it standalone against a mock JSON fixture. Phase 3 swaps the fixture for Craft's own element queries, with the templates surviving intact.

---

## Preview the gallery

```bash
bin/preview
```

Then open <http://localhost:8080>. That is the single documented command; it installs PHP dependencies, prepares generated media, builds the stylesheet and serves the row full-screen with no navigation, footer or sibling rows.

| Option | Effect |
|--------|--------|
| `bin/preview --port 3000` | Serve on a different port |
| `bin/preview --with-siblings` | Add placeholder blocks above and below the row. **Nothing about the gallery should change** — this is the row-independence check |
| `bin/preview --build-only` | Build the stylesheet and exit |

### Driving the prototype from the URL

Harness-only overrides, so the fixture doesn't need editing between reloads. **None of these ship** — in production every one comes from Craft, and there is no visitor-facing control for any of them.

| Query | Example |
|-------|---------|
| `?layout=` | `?layout=masonry` |
| `?gap=` | `?gap=16` (gutter in px) |
| `?card=` | `?card=3in` (target 1×1 cell size) |
| `?theme=` | `?theme=dark` |
| `?depth=` | `?depth=3` (min canvas depth in screens) |
| `?inertia=off` | Disable scroll inertia |
| `?speed=` `?feel=` | `?speed=fast&feel=overshoot` |

---

## First-time setup

Two tools, both installed outside this folder. The project lives in a cloud-synced directory, so nothing large is installed into it.

```bash
# PHP + Composer
brew install php composer

# Tailwind standalone CLI (no Node toolchain — deliberately)
mkdir -p ~/.local/bin
curl -sL -o ~/.local/bin/tailwindcss \
  https://github.com/tailwindlabs/tailwindcss/releases/latest/download/tailwindcss-macos-arm64
chmod +x ~/.local/bin/tailwindcss

# ffmpeg — only needed to regenerate the motion tile's transcoded loop
brew install ffmpeg
```

`bin/preview` finds the Tailwind binary on `PATH`, in `~/.local/bin`, or via `TAILWIND_BIN=/path/to/tailwindcss`. Composer dependencies install automatically on first run.

Verified with PHP 8.5.9, Composer 2.10.2, Twig 3.28.0, Tailwind 4.3.3, ffmpeg 8.1.2.

---

## Layout

```
templates/                        ← DELIVERED to Foster
  _rows/gallery-row.twig             the row: single root, reads row.*
  _components/includes/
    gallery-tile-media.twig          image + motion tiles, dual sibling anchors
    gallery-tile-text.twig           text tiles, brand tokens only
    gallery-build-icon.twig          corner Build link + inline sliders SVG
  _macros/color.twig                 LOCAL STAND-IN — replaced by Foster's at handoff
src/css/gallery.css               ← DELIVERED: brand tokens, grid, tile styles
src/js/                           ← DELIVERED: progressive enhancement only
  gallery.js                         entry point; reads settings from data attributes
  gallery-canvas.js                  inert padding repeats to minCanvasDepth
  gallery-inertia.js                 momentum, immediate retarget, native fallback
  gallery-motion.js                  onscreen-only playback, concurrency cap
data/gallery.fixture.json         ← the mock data source (Craft replaces this)
docs/craft-field-definitions.md   ← DELIVERED: the fields Craft needs

harness/                          ← THROWAWAY, never delivered
  render.php  router.php  harness.twig
  craft-shim/                        emulates the Craft API the templates call
bin/preview  bin/prepare-media     ← tooling
tests/                             ← verification
dist/                              ← generated, gitignored
```

### How the templates run outside Craft

The row is authored against Craft's own API — `craft.imagerx.transformImage`, `asset.alt`, `asset.focalPoint`, `eagerly().one()`, the shared colour macro — and `harness/craft-shim/` emulates exactly that surface locally, backed by GD. The template never learns it isn't running in Craft, which is what makes the Phase 3 handoff a data-source swap rather than a rewrite.

The alternative — a local `gallery_transform()` helper — would be simpler today and something Foster has to unpick later.

---

## Verification

```bash
php tests/html/structure.php                      # 32 structural assertions (no JS)
php docs/sprints/current/demos/phase1-shim.php    # shim + all 14 validation rules
node tests/browser/sprint2.js                     # 32 behavioural checks in real Chrome
```

The browser suite needs Chrome running with remote debugging and the preview server up;
see the header comment in that file. It drives real wheel and key events and reads real
state — momentum, retargeting, motion slot counts — rather than mocking any of it.

`tests/html/structure.php` asserts the spec's acceptance criteria against the served HTML with **no JavaScript executed** — the condition a crawler sees: every unique tile present exactly once, no duplicate ids, no `<button>` routing, no nested anchors, no dead hrefs, distinct accessible labels, alt text present, and every gallery setting traced back to a `row.*` field.

### Measured results

Mobile Lighthouse, simulated throttling, at the full 15-screen canvas with all JavaScript running:

| Metric | Target | Measured |
|--------|--------|----------|
| Performance | ≥ 85 | **98** |
| Accessibility | ≥ 95 | **100** |
| SEO | 100 | **100** |
| Best Practices | — | **100** |
| LCP | < 2.5 s | **2.3 s** |
| CLS | — | **0** |
| Total Blocking Time | — | **0 ms** |

Initial-viewport budget, cache disabled. Reported as the cost of the tiles actually
inside the first viewport, because that figure is reproducible:

| Profile | First-viewport budget | Tiles in fold | Browser also prefetched |
|---------|----------------------|---------------|-------------------------|
| Mobile 390×844 | **141.8 KB** (identical across 3 runs) | 4 tiles, 3 images | all 31 unique images → 544 KB |
| Desktop 1440×900 | **204.2 KB** | 25 tiles, 24 images | all 31 unique images → 261 KB |

Fixed cost: document 25.9 KB, CSS 19.5 KB, JS 23.0 KB across 4 modules.

**A caution on the prefetch column.** How many images the browser *opportunistically*
fetches is not a stable property of this row — Chrome sizes its lazy-loading threshold
from its own connection estimate. An earlier figure of 267.6 KB / 17 requests quoted here
did not reproduce once the canvas reached full depth. The first-viewport budget is the
number to hold to; treat the prefetch total as an upper bound.

**Page weight is bounded by tile count, not canvas depth** — the point of parking clone
media:

| `minCanvasDepth` | Tiles in grid | Canvas | Images fetched | Image bytes |
|---|---|---|---|---|
| 2 | 32 | 6.8 screens | 15 | 228,762 |
| 10 | 50 | 10.2 screens | 19 | 280,424 |
| 15 | 71 | 15.3 screens | 31 | 488,796 |
| 25 | **120** | 25.3 screens | **31** | **488,796** |

At depth 25 the grid holds 120 tiles and still fetches 31 images: 89 clones cost zero
bytes. The ceiling is the number of unique tiles, and it does not move.

Grid, measured in Chrome: exactly 2 columns at 320px at **every** gutter value from
0–64px; column count reflows 2→3→5→7→9 with no breakpoint jump and no horizontal
overflow; 1×1 and 2×2 tiles square at every width; a 2×2 is exactly two columns plus one
gutter; bottom edge flush to 0px across all columns.

Build icon contrast, sampled from composited screenshot pixels over the two real
photographs it sits on: **4.46:1 and 6.62:1** at a 55% chip, since raised to 72%. The bar
for a graphical object is 3:1.

---

## Known issues and open questions

### Flagged, needing a decision

1. **Webfonts are not present.** No files for Neue Montreal or Messina Modern, and licences are unconfirmed. Per the brief this is flagged rather than silently substituted — the row currently renders in the stated fallbacks (Helvetica / Times New Roman). Dropping licensed files in and extending the two `--font-sabin-*` stacks in `src/css/gallery.css` is the only change needed.

2. **Sabin orange cannot carry small text accessibly.** Against `#F15C24`, white measures 3.34:1 and dark-gray 3.96:1 — both fail WCAG AA for normal text (4.5:1). Both pass the large-text bar (3.0:1, which applies from 18.66px bold). The primary CTA therefore uses dark-gray at that size floor (`--gallery-cta-min-size`), which keeps the orange button and reaches Accessibility 100. Worth knowing brand-wide: an orange fill cannot carry a small white label.

3. **Foster's conventions are only half visible.** The supplied reference shows `copy-250`/`copy-400` and `color.bgColor()`/`textColor()`, but not the full type scale, the palette token names the macro accepts, valid `row.theme` values, or their Tailwind major version. Ours are confined to `src/css/gallery.css` and `harness/craft-shim/Tokens.php` so reconciliation is two files — but it is worth one email to Michael.

4. **Product, swatch and About URLs.** Phase 1 does not create those pages, so every `destination` pointing at a product is `live: false` and renders non-interactive by design. Swatch and factory tiles point at `/swatches` and `/about` as live. Real URLs flip these to `true` with no code change.

5. **`2in` is 192 CSS pixels, not a physical inch** — CSS defines `1in` as exactly `96px`. Kept as `2in` to mirror Build 2.0's own `--card-target` literally. `row.cardTarget` is a fixture field, so retuning is a data change.

### Assets needing re-export

The build reports these on every preview and never upscales them:

| Asset | Issue |
|-------|-------|
| `cable-stainless-steel.png` | **Actually AVIF**, and 220×220 |
| `cord-green.jpg` | **Actually AVIF**, and 220×220 |
| `thread-8470.jpg` | **Actually AVIF**, and 220×220 |
| `sabin-quad-diffuse-medical-ostap-min-5.jpeg` | 1200×360 → 360px square crop |
| `taper-pendant-sabin-lighting-gulf-min-04.jpeg` | 1200×360 → 360px square crop |

A 2in tile needs 384px at 2× density, so all five land soft. The three mis-extensioned files matter beyond size: an image pipeline that trusts the extension will mishandle them.

Separately, the nine 1200×675 (16:9) assets crop to 675px square — fine at 1×1, but short of the 768px a **2×2** needs. The fixture reserves 2×2 for higher-resolution sources.

### Unresolved: tab order cannot match visual order

Two "must" requirements in the brief are mutually exclusive, and this needs your call.

- Dense packing is required, and the brief accepts its consequence: "a tile further down the list may move up to fill a hole. That is accepted."
- Keyboard access is required "in an order matching the visual layout."

Dense packing fills holes with *later* tiles, so a later DOM element appears earlier
visually. Tab order follows DOM order. The divergence is therefore inherent, not a bug:
measured at 1440px, tabbing reaches all 10 anchors with a visible ring and Enter
activates, but `tile-029` sits at y=348 and is reached *after* `tile-023` at y=698.

Three options:

1. **Accept it** (current). DOM order stays the crawlable, stable truth. Divergence is
   small and proportional to the number of 2×2 tiles.
2. **Reorder the DOM after layout** to match the packed result. Would need re-running on
   every resize, since column count changes the packing — and it makes reading order for
   screen readers depend on viewport width.
3. **Drop dense packing** for `grid-auto-flow: row`. Guarantees order, but leaves visible
   holes, which the brief explicitly rules out.

Recommendation: option 1, with the criterion reworded to "every unique routable tile is
reachable in DOM order with a visible focus indicator."

### Still open

- **Windowing/recycling is not implemented** and now looks unnecessary: weight plateaus at
  the unique-tile count regardless of depth (table above). Worth revisiting only if the
  tile library grows past a few hundred.
- **No Alpine.js.** The brief permits it for click-routing and scroll behaviour, but every
  routable tile is a real anchor, so click-routing needs no JS, and the scroll/motion work
  is a poor fit for a declarative component layer. Vendoring it unused would be the future
  scaffolding the project principles rule out. It is one file if a later phase needs it.
- Masonry's 2×2 tiles measure 409×404 rather than perfectly square, because implicit row
  height is a third of the card target. Within 1.3%; tunable.

---

## Documentation

| Document | Purpose |
|----------|---------|
| [CLAUDE.md](CLAUDE.md) | Principles and hard invariants |
| [Instructions for Gallery.md](Instructions%20for%20Gallery.md) | Authoritative spec |
| [project_brief.md](project_brief.md) | Multi-phase context |
| [docs/architecture/pending/gallery-row.md](docs/architecture/pending/gallery-row.md) | Design, semantics, contracts |
| [docs/sprints/current/spec.md](docs/sprints/current/spec.md) | Current sprint |
| [docs/craft-field-definitions.md](docs/craft-field-definitions.md) | Field definitions for Craft |

### Development workflow

This project uses the [leos_claude_starter](https://github.com/leogodin217/leos_claude_starter) Claude Code workflow. See `CUSTOMIZATION.md` for the framework's own documentation.

```
/get-started       →  Configure project (run once)          [done]
/arch-design       →  Design interfaces, architecture docs   [done]
/create-sprint     →  Plan implementation phases             [done]
/implement-sprint  →  Execute the plan                       [Sprint 1 complete]
/review-sprint     →  QA and verify
/verify-sprint     →  Final verification
/audit-docs        →  Keep documentation accurate
```

Sprint 1 (zero-JS foundation) and Sprint 2 (enhancement layer) are both complete.
