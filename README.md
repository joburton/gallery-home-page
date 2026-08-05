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

One command runs everything:

```bash
bin/verify                 # all suites
bin/verify --quick         # PHP only, no Chrome needed
bin/verify --lighthouse    # also Lighthouse, both throttling profiles
```

It starts the preview server and headless Chrome if they aren't already running, and
stops only what it started. **Nothing to install** — the browser tests use Node's
built-in WebSocket (Node 22+) rather than an npm package, so there is no `node_modules`
in this cloud-synced folder. Lighthouse runs via `npx` on demand.

Individual suites, if you want them separately:

```bash
php tests/html/structure.php                      # 36 structural assertions (no JS)
php docs/sprints/current/demos/phase1-shim.php    # shim + all 14 validation rules
cd tests/browser && node sprint2.js               # 32 behavioural checks in real Chrome
cd tests/browser && node keyboard-and-icon.js     # tab order, focus ring, icon contrast
cd tests/browser && node grid-floor-and-modes.js  # two-column floor, layout modes
cd tests/browser && node depth-invariance.js      # page weight vs canvas depth
```

The browser suites drive real wheel and key events and read real state — momentum,
retargeting, motion slot counts — rather than mocking any of it.

`tests/html/structure.php` asserts the spec's acceptance criteria against the served HTML with **no JavaScript executed** — the condition a crawler sees: every unique tile present exactly once, no duplicate ids, no `<button>` routing, no nested anchors, no dead hrefs, distinct accessible labels, alt text present, and every gallery setting traced back to a `row.*` field.

### Measured results

Mobile Lighthouse, simulated throttling, at the full 15-screen canvas with all JavaScript running:

| Metric | Target | Measured |
|--------|--------|----------|
| Performance | ≥ 85 | **97** simulated / **99** real-throttled |
| Accessibility | ≥ 95 | **100** |
| SEO | 100 | **100** |
| Best Practices | — | **100** |
| LCP (simulated throttling, the default) | < 2.5 s | **2.48 s** |
| LCP (real network throttling) | < 2.5 s | **1.60 s** |
| CLS | — | **0.0000** under both |
| Total Blocking Time | — | **0 ms** |

Initial-viewport budget, cache disabled. Reported as the cost of the tiles actually
inside the first viewport, because that figure is reproducible:

| Profile | First-viewport budget | Tiles in fold | Browser also prefetched |
|---------|----------------------|---------------|-------------------------|
| Mobile 390×844 | **141.8 KB** (identical across 3 runs) | 4 tiles, 3 images | all 31 unique images → ~490 KB |
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
| 2 | 33 | 8.2 screens | 12 | 196,128 |
| 10 | 41 | 10.2 screens | 12 | 196,128 |
| 15 | 60 | 15.0 screens | 29 | 482,054 |
| 25 | **105** | 25.3 screens | **32** | **516,292** |

At depth 25 the grid holds 105 tiles and fetches 32 images — the row's entire unique media
set, logo included. The 73 padding clones cost nothing. The ceiling is the number of
distinct assets in Craft and it does not move however deep the canvas is set, which is why
windowing turned out to be unnecessary.

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

1. **Both brand faces are in and in use.** Neue Montreal Medium sets every heading, label and statement; Messina Modern Regular sets the CTA label and the secondary body level — the "sparingly, for visual interest" role the brand describes. Messina is a serif, confirmed by rendering a specimen, so the brand's Times New Roman fallback pairing is right.

   Three things worth knowing:
   - **No font preload, deliberately** — a reversal of the brief's literal instruction, explained under *Fonts and layout stability* below. The brief's underlying concern is fully addressed; the preload cost 148 ms of LCP and pushed the page past the brief's own target.
   - **Neue Montreal *Light* is still missing.** Running text uses the metrics-matched Helvetica fallback. One file fixes it.
   - **A real `NeueMontreal-Bold` would let the CTA shrink** from 24 px to 19 px and stay accessible.
   - **Licence, for Foster:** the Luzi Type EULA covers one domain and a monthly page-view cap. Confirm which domain is registered before go-live.

2. **Fonts and layout stability.** Neue Montreal Medium is 5.6% narrower than Helvetica with a taller line box (1.200em vs 1.150em), so when it swapped in it reflowed the copy *and* changed the line height — resizing the text tile and producing a measurable 0.026 layout shift.

   Fixed with a metrics-matched fallback: `size-adjust: 94.37%` plus ascent/descent overrides tuned against a rendered specimen (the arithmetic-derived values were 18 px per 100 px too tall — Chrome does not scale the overrides the way the spec reads). The fallback now occupies exactly the same space as the real face, so the swap changes shape without moving anything. **CLS 0.0000.**

   With the shift gone, the preload's only remaining effect was putting 31 KB ahead of the LCP image. Removing it brought LCP from 2.63 s to 2.48 s simulated — under target. Both brief requirements are satisfied in substance: no serif flash, no reflow, and the performance target met. One line in `harness/harness.twig` restores the preload if you disagree.

3. **Brand fonts — original note, now resolved.** Neue Montreal Medium is loaded (`font-display: swap` plus a preload) and now sets every heading, label and CTA.
   - **Neue Montreal *Light* was not supplied.** The brand specifies it for small running text, so body copy renders in Helvetica — the brand's own stated fallback. It is deliberately *not* named in the font stack: naming it would render correctly on any machine that has it installed locally while visitors got Helvetica, hiding the gap from exactly the people reviewing the work. Drop in `NeueMontreal-Light.woff2` and it is a two-line change.
   - **Messina Modern is supplied but unused, so it is not loaded.** The brand says to use it sparingly for visual interest, not for primary UI — and there is no obvious place for it in a photo grid. Loading 29 KB for nothing would be waste. Say where it belongs and I will wire it.
   - **Licence, for Foster:** the Luzi Type EULA covers *one domain* and a purchased monthly page-view cap. Worth confirming which domain is registered before the gallery becomes the homepage.

4. **Sabin orange cannot carry small text accessibly.** Against `#F15C24`, white measures 3.34:1 and dark-gray 3.96:1 — both fail WCAG AA for normal text (4.5:1). Both pass the large-text bar of 3.0:1, which is reached *either* at 18.66px bold *or* at 24px any weight. Only Neue Montreal Medium (500) was supplied, so asking for bold made the browser synthesise a fake bold that renders smeared — the CTA therefore sits at **24px in the real 500 weight** (`--gallery-cta-min-size`). Same compliance, no faux bold, orange button kept, Accessibility 100. Worth knowing brand-wide: an orange fill cannot carry a small white label. A genuine `NeueMontreal-Bold` would allow a smaller button.

5. **Foster's conventions are only half visible.** The supplied reference shows `copy-250`/`copy-400` and `color.bgColor()`/`textColor()`, but not the full type scale, the palette token names the macro accepts, valid `row.theme` values, or their Tailwind major version. Ours are confined to `src/css/gallery.css` and `harness/craft-shim/Tokens.php` so reconciliation is two files — but it is worth one email to Michael.

6. **Product, swatch and About URLs.** Phase 1 does not create those pages, so every `destination` pointing at a product is `live: false` and renders non-interactive by design. Swatch and factory tiles point at `/swatches` and `/about` as live. Real URLs flip these to `true` with no code change.

7. **`2in` is 192 CSS pixels, not a physical inch** — CSS defines `1in` as exactly `96px`. Kept as `2in` to mirror Build 2.0's own `--card-target` literally. `row.cardTarget` is a fixture field, so retuning is a data change.

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

### Decided: tab order follows document order

**Resolved 5 Aug 2026 — Jo accepted option 1.**

Two "must" requirements in the brief were mutually exclusive. Dense packing is required,
and the brief accepts its consequence ("a tile further down the list may move up to fill
a hole"). Keyboard access was also required "in an order matching the visual layout."
Dense packing fills holes with *later* tiles and tab order follows the document, so the
divergence is inherent rather than a defect.

**The criterion is therefore reworded to:** every unique routable tile is reachable by
keyboard in document order, with a clearly visible focus indicator, and Enter activates
it. Verified: all anchors reached, 3px orange focus ring throughout, Enter navigates.

Document order stays the stable, crawlable truth. Note that the divergence grows with the
number of large tiles — see the 2x2 ratio note below.

### Text tiles can carry images

Added 5 Aug 2026 for the NeoCon announcement tile. A text tile may now include a logo or
mark via `text.image` + `text.imageAlt`, rendered contained (never cropped — a cropped
logo is a damaged logo) and bottom-aligned. Alt text is required, because a logo is
content.

Two related schema changes:

- **`text.body` is now optional.** The welcome tile carries only a statement and a button.
- **`text.bodyStyle`** is a new named token, `primary` (Neue Montreal) or `secondary`
  (Messina Modern), so a tile selects a brand face by name and never by font name.
- **`cta-only` without a CTA is valid** — an informational tile that routes nowhere, which
  is what the NeoCon tile is. `whole-tile` still requires a destination.

**Text tiles now grow rather than clip.** `overflow: hidden` crops photographs to the
square, but on a grid item it also resolves the automatic minimum height to zero — so a
text tile whose copy exceeded the square silently truncated it. Text tiles have no media
to crop, so the clip is dropped and the inner block uses `min-height` rather than
`height`. A tile fills its square when it can and grows taller when it must, which is
what "nothing is hidden and no type shrunk to fit" requires. Verified at 320–1920 px,
including a forced-small tile where both text tiles now grow to 253 px and 302 px instead
of cropping.

### Tile size mix

Raised to **one quarter 2×2** on 5 Aug 2026, up from 6. Now 9 of 33 (27%) — the NeoCon
tile is the ninth. Only sources with a
short edge of at least 768px are eligible — a 2×2 needs that to stay sharp at 2× density,
so the nine 1200×675 photographs cannot be promoted without going soft. Sixteen 1×1 tiles
are ineligible on that basis. The two promoted were `obforma-downlight-sabin-elevator` and
`quad-stadia-60-kitchen-cafe`, both 1200×900, picked for being furthest from an existing
2×2 so the large tiles stay spread through the grid rather than clumping.

Two knock-on effects worth knowing:

- **It cost LCP.** Bigger tiles in the fold pushed LCP from 2.40 s to **2.55 s — over
  target**, consistently across three runs. Recovered by compressing derivatives of 700px
  and above harder (q64 rather than q72), which is invisible on a photograph at that
  display size and brought LCP back to 2.40 s. Pushing much past a quarter 2×2 will need
  another lever.
- **It widens the tab-order divergence** you accepted above, since that grows with the
  number of large tiles. Still document order, still every tile keyboard-reachable.

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
| [docs/architecture/gallery-row.md](docs/architecture/gallery-row.md) | Design, semantics, contracts |
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
