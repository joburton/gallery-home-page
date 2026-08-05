# Gallery Row

**Status:** Design draft — awaiting review

**Scope:** Phase 1 only (~30 tiles, partial functionality). Covers all six capabilities in [CAPABILITIES.md](../../CAPABILITIES.md).

---

## Problem

SABIN's new homepage centrepiece is a deep, scrollable canvas of square tiles. It has to satisfy two audiences that normally pull in opposite directions:

1. **Crawlers and LLM answer engines** need every tile's alt text, copy, CTA and `href` in the served HTML, each unique tile appearing exactly once.
2. **Visitors** need roughly fifteen screens of densely packed canvas with momentum scrolling and onscreen-only motion playback.

Naively satisfying (2) breaks (1). Fifteen screens of 2in tiles is several hundred media elements; padding the canvas server-side would repeat the same thirty alt texts and thirty images many times over, producing duplicate DOM ids, a duplicate-content problem, and a huge avoidable DOM on the most performance-sensitive page on the site.

There is a second, structural problem. The deliverable is a **Craft predefined row**, and Foster integrates it in Phase 3 by swapping the data source — not by rewriting templates. But Craft is not wired up yet, so the template must be authored against Craft's own API (`craft.imagerx.transformImage`, `row.image.eagerly().one()`, asset `.alt` and focal points, the shared colour macro) while rendering today from a local JSON fixture. Any layer of local indirection introduced for convenience becomes something Foster has to unpick:

```twig
{# What Phase 1 must NOT produce — a local abstraction Foster has to strip out #}
{% set src = gallery_transform(tile.media, 384) %}
<img src="{{ src }}" class="{{ tile.theme == 'light' ? 'bg-[#F4F4F4]' : 'bg-[#303030]' }}">

{# What Phase 1 must produce — Craft-native, unchanged at handoff #}
{% set transformed = craft.imagerx.transformImage(asset, [{width: 384}, {width: 768}], {mode: 'crop', ratio: 1/1}) %}
<img src="{{ transformed[0].url }}" srcset="{{ craft.imagerx.srcset(transformed) }}" class="{{ color.bgColor(row.theme) }}">
```

Third, the layout has non-obvious coupling. The two-column floor at 320px depends on the gutter appearing inside the `minmax()` expression *and* as the grid `gap`. If those two values are allowed to diverge, changing `row.tileGap` silently collapses the grid to one column at narrow widths with no obvious cause.

## Solution

Render the row as **Craft-native Twig against a shim**. The row template is written exactly as Foster will receive it; the throwaway harness supplies a small PHP emulation of the Craft surface the template touches (an `Asset`-like media object, `craft.imagerx.transformImage`/`srcset` backed by GD, and a local stand-in for the colour macro). The template never knows it isn't running in Craft, so the Phase 3 handoff is a data-source swap rather than a rewrite.

Split responsibility strictly at the crawlability line: **the server renders truth, the client renders padding.** Twig emits each unique tile exactly once inside one dense CSS Grid. A vanilla JS module then clones tiles to pad the canvas to `row.minCanvasDepth` and to complete the final row, stripping identity from every clone and marking it `inert` + `aria-hidden`. Nothing a crawler or keyboard user can reach was created by JS.

```
DELIVERED TO FOSTER                                    THROWAWAY (harness/)
┌────────────────────────────────────────┐             ┌──────────────────────┐
│ templates/_rows/gallery-row.twig       │             │ index.php            │
│   single <section> root, reads row.*   │◀── renders ─│ fixture → row var    │
│ templates/_components/includes/*.twig  │             │                      │
│ templates/_macros/color.twig (Foster's)│             │ craft-shim/          │
│ src/css/gallery.css                    │             │  Asset (url/alt/     │
│ src/js/gallery-{canvas,inertia,motion} │             │   focalPoint/w/h)    │
│ docs/craft-field-definitions.md        │             │  ImagerX (GD crop,   │
└────────────────────────────────────────┘             │   webp, srcset)      │
                 │                                     │  color macro stub    │
                 │                                     └──────────────────────┘
                 ▼
   ┌─────────────────────────────────────────────────────────────┐
   │ SERVED HTML — 30 unique tiles, once each, no JS required    │
   │   • real <a href> per routable tile, siblings never nested  │
   │   • data-tile-id on canonical instance only                 │
   │   • alt text, headings, body, CTA labels all present        │
   └──────────────────────────┬──────────────────────────────────┘
                              ▼ progressive enhancement only
   ┌─────────────────────────────────────────────────────────────┐
   │ gallery-canvas.js   clone → strip id → inert + aria-hidden  │
   │ gallery-inertia.js  attaches ONLY if enabled && !reduced     │
   │ gallery-motion.js   IO play/pause, cap = maxConcurrentMotion │
   └─────────────────────────────────────────────────────────────┘
```

One CSS custom property per coupled value (`--gallery-gap`, `--gallery-card-target`) written once into the row's inline `style` from `row.*`, then consumed by both the `gap` and the `minmax()` floor — making the gutter's single source of truth structural rather than a convention someone has to remember.

---

## Semantics

### Primary destination (whole-tile link)

Driven by `contentType`, never by filename or folder.

| `contentType` | Primary destination |
|---------------|---------------------|
| `in-situ`, `mid-zoom`, `detail`, `sketch` | Product page for the thing shown |
| `swatch` | Swatches page |
| `factory` | About page |

### Link rendering

| Condition | Result |
|-----------|--------|
| `destination.live: true` | `<a>` stretched `absolute inset-0`, own `aria-label` ("View {name}") |
| `destination.live: false` | Non-interactive element — no `<a>`, no `href`, not tabbable, not announced as a link. Media and alt text still render |
| `buildLink` present and `live: true` | Second **sibling** `<a>` in the bottom-right corner, above the primary in stacking order, own `aria-label` ("Configure {name} in Build") |
| `buildLink` null, or `live: false` | No icon element rendered at all |
| Tile type is `text` | Never a Build icon, regardless of data |
| `text.linkBehavior: cta-only` (default) | Only the CTA is routable; tile body is not a link |
| `text.linkBehavior: whole-tile` | Entire tile is one `<a>`; no separate CTA anchor (avoids nesting) |
| External destination | `rel="noopener noreferrer"` applied |

Nested anchors are structurally impossible in this design: the primary link and the icon are siblings under the tile root, and `whole-tile` text tiles render the CTA as a non-anchor visual element inside the single tile anchor.

### Grid and layout modes

| Condition | Result |
|-----------|--------|
| Any viewport width | `grid-auto-flow: dense`; column count from `auto-fill`; exactly 2 columns minimum |
| Tile `size: "NxM"` | `grid-column: span N; grid-row: span M` |
| `layoutMode: swiss` | Row span always equals the tile's declared M — uniform squares, rows align |
| `layoutMode: masonry` | Row span varies deterministically per tile, so rows stagger; declared column span is preserved, so 2x2 still renders 2x2 |
| Welcome tile (first tile, `type: text`) | Pinned to cell 1 explicitly, not left to dense packing |
| Dense packing leaves a hole | A later smaller tile backfills it; strict source order is not guaranteed (accepted, except the pinned welcome tile) |
| Final row incomplete | Canvas appends further repeats until the row is full — never ragged |

Masonry's varying row span is derived from a stable function of the tile's index so the layout is deterministic across renders rather than random per page load.

**Known constraint (flagged for review):** tile aspect is held by `aspect-ratio: N / M`. Because a tile's width is `N·c + (N−1)·g` and its height `M·c + (M−1)·g`, the ratio is *exact* whenever `N == M` — which covers every size this milestone uses (1x1, 2x2) at any gutter value. A future non-square span such as `2x3` renders with a deviation on the order of the gutter (4px default). Adding `2x3` therefore remains a pure data change as required; it simply won't be pixel-exact. Alternatives that are exact for all spans require a fixed row track, which would break the fluid `auto-fill` reflow.

### Canvas depth and repeats

| Condition | Result |
|-----------|--------|
| Unique tiles reach `minCanvasDepth` | No repeats generated |
| Unique tiles fall short | Tiles cloned in order until depth ≥ `minCanvasDepth × viewport height`, then until the final row is complete |
| Any clone | `data-tile-id` removed, any `id` removed, `aria-hidden="true"`, `inert` on the wrapper, all media `loading="lazy"` |
| JS disabled | Unique tiles render and are fully usable; canvas is simply shallower |
| Viewport resized | Depth re-evaluated; clones added or trimmed |
| Initial-viewport budget missed by lazy-loading alone | Window/recycle offscreen tiles rather than shipping hundreds of elements |

`inert` removes clones from the tab order and from the accessibility tree; `aria-hidden` is applied as well because the spec names it explicitly.

### Scroll inertia

Operates on **window scroll**, not an inner scroller — the row must not own the viewport, and on the real homepage momentum must carry into the rows below it.

| Condition | Result |
|-----------|--------|
| `enabled: true` and no reduced-motion preference | Wheel input intercepted; rAF loop eases current → target scroll position |
| New wheel input mid-animation | Target is updated immediately and additively; the animation retargets on the next frame. Never queued, never swallowed |
| `enabled: false` | No listeners attached at all — not a shortened animation. Native scroll, keyboard, scrollbar drag and find-in-page untouched |
| `prefers-reduced-motion: reduce` | Same as disabled: nothing attached |
| Preference changes at runtime | Listener state re-evaluated and detached/attached accordingly |
| `speed` | `slow` / `medium` (default) / `fast` — scales the per-frame approach rate |
| `feel` | `smooth` (restrained deceleration, default) / `glide` (carries further) / `overshoot` (slight rubber-band settle) |
| Keyboard, scrollbar, touch, find-in-page | Never intercepted in any mode — only wheel input is |

**Decision: a small vanilla module, not Lenis.** The two hard requirements — immediate mid-animation retargeting, and *genuinely* leaving no interception in place when off — are properties we must be able to guarantee and test directly. A library that wraps scroll typically owns it for the page's lifetime, which is the failure mode the spec calls out. A rAF lerp toward a mutable target is a few dozen lines, adds no dependency to a sync-sensitive folder, and makes retargeting the natural behaviour rather than something to configure around.

### Motion tiles

| Condition | Result |
|-----------|--------|
| Rendered markup | `<video muted loop playsinline preload="none" poster="...">` with `width`/`height` — never a GIF |
| `playback: autoplay`, onscreen, slot free | Plays |
| Scrolled out of view | Paused via IntersectionObserver; slot released |
| Onscreen but `maxConcurrentMotion` slots full | Holds its poster frame until a slot frees |
| `playback: click` | Poster until activated; never autoplays |
| `prefers-reduced-motion: reduce` | Never autoplays under any setting; poster only |
| Before load | Poster frame shown — a tile is never blank |

Slots are granted to the most-visible candidates so the cap degrades predictably rather than by DOM order.

### Media transforms

| Condition | Result |
|-----------|--------|
| Every media tile | Cropped 1:1 via ImagerX, `mode: 'crop'`, `ratio: 1/1` |
| Asset has a focal point | Crop honours it |
| No focal point | Centre crop, accepted even where it discards much of a panoramic frame |
| Source shorter than the requested derivative | Rendered at native size, never upscaled, and reported |
| Asset carries alpha | Tile sits on a defined background colour rather than assuming opacity |
| Below the initial viewport | `loading="lazy"`; first screen or two eager |
| Every tile | `width`/`height` or `aspect-ratio` present so the grid never reflows as media loads |

### Text tiles

| Condition | Result |
|-----------|--------|
| Grid container narrower than the legibility threshold | Tile spans full grid width (`grid-column: 1 / -1`) as a short, wide block |
| At or above the threshold | Tile keeps its declared square span |
| Either case | Nothing hidden, no type shrunk to fit |
| Any styling field | Resolved from a named token map; an unknown token is a fixture error, not a passthrough |

The threshold is evaluated with a **container query** on the grid, not a viewport media query, so the row stays independent of the page width around it (Principle #7).

---

## Configuration

Craft-driven, never visitor-facing. The fixture mirrors a plausible Craft GraphQL response: gallery settings are fields on the row, tiles are a nested field within it.

```json
{
  "row": {
    "type": "galleryRow",
    "theme": "light",
    "layoutMode": "swiss",
    "minCanvasDepth": 15,
    "tileGap": 4,
    "cardTarget": "2in",
    "maxConcurrentMotion": 4,
    "buildIcon": null,
    "scrollInertia": { "enabled": true, "speed": "medium", "feel": "smooth" },
    "tiles": [ /* … */ ]
  }
}
```

### Row fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `type` | string | Yes | Row handle — `galleryRow` |
| `theme` | enum | Yes | Feeds the colour macro; follows existing site convention |
| `layoutMode` | `swiss` \| `masonry` | Yes | Selects row-span behaviour on the one grid |
| `minCanvasDepth` | number | Yes | Minimum canvas depth in screens (default 15) |
| `tileGap` | number (px) | Yes | Gutter; single source of truth for `gap` *and* the two-column floor (default 4) |
| `cardTarget` | CSS length | Yes | Target 1x1 cell size (default `2in`, matching Build 2.0) |
| `maxConcurrentMotion` | number | Yes | Simultaneous playing motion tiles (default 4) |
| `buildIcon` | asset \| null | No | `null` uses the built-in sliders SVG; an asset overrides it globally |
| `scrollInertia.enabled` | bool | Yes | Master toggle (default true) |
| `scrollInertia.speed` | `slow` \| `medium` \| `fast` | Yes | Default `medium` |
| `scrollInertia.feel` | `smooth` \| `glide` \| `overshoot` | Yes | Default `smooth` |
| `tiles` | array | Yes | Nested tile field |

### Tile fields

| Field | Type | Applies to | Description |
|-------|------|-----------|-------------|
| `id` | string | all | Stable unique identifier; exposed as `data-tile-id` on the canonical instance only |
| `type` | `image` \| `motion` \| `text` | all | Changeable in Craft at any time |
| `size` | `"NxM"` | all | Generic cols × rows; code must not assume 1x1/2x2 |
| `designation` | `static` \| `dynamic` | all | `static` = excluded from Phase 2 shuffle only; still scrolls normally |
| `media` | asset | image, motion | **One** field for both types, so switching type is a data change not a schema change |
| `poster` | asset | motion | Required — first frame, so a tile is never blank |
| `alt` | string | image, motion | Content, not decoration |
| `contentType` | enum | image, motion | Drives the primary destination; assigned per tile because filenames don't encode it |
| `playback` | `autoplay` \| `click` | motion | Per tile |
| `tags` | string[] | image, motion | Carried through, unused in Phase 1 |
| `destination` | `{type, url, live}` | image, motion | Primary whole-tile link |
| `buildLink` | `{url, live}` \| null | image, motion | Optional secondary link behind the corner icon |
| `text.heading` / `text.body` | string | text | Real text, never an image of text |
| `text.headingLevel` | named type level | text | From the defined scale — no free font/size/weight |
| `text.background` | named palette token | text | No arbitrary hex |
| `text.padding` | named step | text | No arbitrary pixel values |
| `text.linkBehavior` | `cta-only` \| `whole-tile` | text | Default `cta-only` |
| `text.cta` | `{label, url, style}` | text | `style` from `primary` \| `secondary` \| `quiet` |
| `text.media` | asset \| null | text | Optional embedded motion, same encoding/playback rules |

### Brand tokens

Defined once as Tailwind `@theme` custom properties and referenced by name everywhere.

| Token | Hex |
|-------|-----|
| `orange` (primary) | `#F15C24` |
| `yellow` (secondary) | `#FFC15E` |
| `dark-gray` | `#303030` |
| `white` | `#FFFFFF` |
| `light-gray` | `#E5E4DC` |
| `off-white` | `#F4F4F4` |

Anything beyond this core palette comes from `SABIN_BRAND_GUIDELINES_SPREADS_min.pdf`.

---

## Interface Contracts

### CSS custom-property contract

The row's root element carries these, written once from `row.*`. Everything else derives from them.

| Property | Source | Consumed by |
|----------|--------|-------------|
| `--gallery-gap` | `row.tileGap` | grid `gap` **and** the `minmax()` two-column floor |
| `--gallery-card-target` | `row.cardTarget` | the `minmax()` target size |
| `--gallery-min-depth` | `row.minCanvasDepth` | read by `gallery-canvas.js` |

The column contract, which both coupled consumers of `--gallery-gap` read:

```css
grid-template-columns: repeat(auto-fill,
  minmax(min(var(--gallery-card-target), calc((100% - var(--gallery-gap)) / 2)), 1fr));
gap: var(--gallery-gap);
grid-auto-flow: dense;
```

Note `2in` in CSS is exactly 192px by definition, not a physical inch — flagged below.

### Twig template contracts

```twig
{# templates/_rows/gallery-row.twig
   The deliverable. Single root <section>; reads only from `row`; assumes nothing
   about surrounding content; contains no page-level or reset CSS.

   Requires: row (galleryRow) — see Configuration above.
   Emits:    one root element carrying the CSS custom properties and data-layout-mode,
             containing each unique tile exactly once. #}

{# templates/_components/includes/gallery-tile-media.twig
   Renders one image or motion tile: ImagerX-transformed square media, the primary
   anchor (or a non-interactive element when destination.live is false), and the
   corner Build icon when buildLink is live.

   Params: tile (image|motion), row (for theme + buildIcon), eager (bool — first
           screen or two load eagerly), canonical (bool — carries data-tile-id). #}

{# templates/_components/includes/gallery-tile-text.twig
   Renders one text tile from named tokens only. Resolves background, padding,
   type level and CTA style through token maps; an unknown token is an error.

   Params: tile (text), row, canonical (bool). #}

{# templates/_components/includes/gallery-build-icon.twig
   The corner Build link: a sibling anchor with its own accessible label, its own
   contrast treatment, and permanent visibility on touch. Renders row.buildIcon
   when set, otherwise the inline sliders SVG.

   Params: tile, row, label (string — distinct from the primary link's label). #}
```

### Harness shim contracts (throwaway — `harness/`)

```php
final class HarnessAsset
{
    /**
     * Stands in for a Craft Asset, exposing only the surface the row template touches.
     *
     * @param string     $path       Absolute path to the source file in images/.
     * @param string     $alt        Descriptive alt text from the fixture.
     * @param array|null $focalPoint ['x' => float, 'y' => float] in 0..1, or null for centre.
     */
    public function __construct(string $path, string $alt, ?array $focalPoint = null);

    /** Public URL of the source asset, as Craft would report it. */
    public function getUrl(): string;

    /** Intrinsic width in pixels. */
    public function getWidth(): int;

    /** Intrinsic height in pixels. */
    public function getHeight(): int;

    /** Normalised focal point, defaulting to centre when unset. */
    public function getFocalPoint(): array;

    /** True when the source carries an alpha channel, so the tile needs a defined background. */
    public function hasAlpha(): bool;

    /**
     * Emulates Craft's eager-loading chain so the template reads identically in Craft.
     *
     * @return self
     */
    public function eagerly(): self;

    /** @return self */
    public function one(): self;
}
```

```php
final class HarnessImagerX
{
    /**
     * Emulates craft.imagerx.transformImage for the local harness, backed by GD.
     *
     * Crops to the requested ratio honouring the asset's focal point, writes WebP
     * derivatives into the transform cache, and never upscales: a requested width
     * beyond the source's native size yields a derivative at native size, recorded
     * for the undersized-asset report.
     *
     * @param HarnessAsset $asset      Source asset.
     * @param array        $transforms List of ['width' => int] specs.
     * @param array        $options    ['mode' => 'crop', 'ratio' => float].
     *
     * @return HarnessTransform[] One per spec, in the order given.
     *
     * @throws RuntimeException If the source cannot be decoded.
     */
    public function transformImage(HarnessAsset $asset, array $transforms, array $options): array;

    /**
     * Emulates craft.imagerx.srcset — builds a width-descriptor srcset string.
     *
     * @param HarnessTransform[] $transforms Output of transformImage().
     *
     * @return string e.g. "/dist/t/abc-384.webp 384w, /dist/t/abc-768.webp 768w".
     */
    public function srcset(array $transforms): string;

    /**
     * Assets whose native size fell short of a requested derivative width.
     *
     * @return array<string, int> Source filename => largest usable square edge, in pixels.
     */
    public function undersizedReport(): array;
}
```

```php
/**
 * Loads and validates the fixture, then renders the row through Twig.
 *
 * Registers the Craft-shaped globals the row template expects (`craft.imagerx`,
 * the colour macro path) and converts fixture media strings into HarnessAsset
 * instances so the template's Craft idioms resolve unchanged.
 *
 * @param string $fixturePath Path to data/gallery.fixture.json.
 * @param bool   $withSiblings Wrap the row in placeholder blocks above and below,
 *                             proving it depends on nothing around it.
 *
 * @return string Complete HTML document.
 *
 * @throws FixtureValidationException On any violation in Validation Rules below.
 */
function render_harness(string $fixturePath, bool $withSiblings = false): string;
```

### JS module contracts (delivered)

```js
/**
 * Pads the canvas to the configured minimum depth with inert clones.
 *
 * Reads --gallery-min-depth from the row root. Clones existing tiles in order until
 * the canvas reaches the target depth and the final row is complete. Every clone has
 * its data-tile-id and id removed, gets aria-hidden="true", sits under an inert
 * wrapper, and has its media forced to loading="lazy". Re-evaluates on resize.
 *
 * @param {HTMLElement} root The gallery row root element.
 * @returns {{ destroy: () => void }} Removes all clones and listeners.
 */
export function initCanvas(root);

/**
 * Momentum scrolling over window scroll, or nothing at all.
 *
 * Attaches no listeners when disabled or when prefers-reduced-motion is set, leaving
 * native scroll wholly untouched. When active, intercepts wheel input only and eases
 * toward a mutable target; new input updates that target immediately so scrolling
 * retargets mid-animation rather than queueing. Re-evaluates on preference change.
 *
 * @param {HTMLElement} root
 * @param {{enabled: boolean, speed: 'slow'|'medium'|'fast', feel: 'smooth'|'glide'|'overshoot'}} settings
 * @returns {{ destroy: () => void }} Detaches everything, restoring native scroll.
 */
export function initInertia(root, settings);

/**
 * Plays motion tiles only while onscreen, never exceeding the concurrency cap.
 *
 * Grants play slots to the most-visible candidates, pauses tiles as they leave the
 * viewport and releases their slots. Never autoplays under prefers-reduced-motion,
 * and never autoplays a tile whose playback is 'click'.
 *
 * @param {HTMLElement} root
 * @param {number} maxConcurrent From row.maxConcurrentMotion.
 * @returns {{ destroy: () => void }}
 */
export function initMotion(root, maxConcurrent);
```

---

## Validation Rules

### Fixture load-time (fail fast, before any rendering — Principle #4)

| Rule | Checks | Error message |
|------|--------|---------------|
| `UniqueTileIds` | No `id` appears twice | `"Duplicate tile id '{id}' at tiles[{i}]"` |
| `SizeFormat` | `size` matches `NxM`, both ≥ 1 | `"Tile '{id}': size '{size}' is not NxM"` |
| `KnownTileType` | `type` ∈ {image, motion, text} | `"Tile '{id}': unknown type '{type}'"` |
| `MediaPresent` | image/motion tiles have `media`; the file exists | `"Tile '{id}': media '{path}' not found"` |
| `AltPresent` | image/motion tiles have non-empty `alt` | `"Tile '{id}': alt text is required on media tiles"` |
| `PosterPresent` | motion tiles have an existing `poster` | `"Tile '{id}': motion tiles require a poster"` |
| `ContentTypeKnown` | `contentType` maps to a destination rule | `"Tile '{id}': contentType '{ct}' has no destination mapping"` |
| `NoBuildLinkOnText` | text tiles carry no `buildLink` | `"Tile '{id}': text tiles cannot have a buildLink"` |
| `NamedTokensOnly` | every text styling value resolves in its token map | `"Tile '{id}': '{value}' is not a named {field} token"` |
| `CtaWhenCtaOnly` | `linkBehavior: cta-only` ⇒ a `cta` exists | `"Tile '{id}': cta-only requires a cta"` |
| `KnownEnums` | `layoutMode`, `theme`, `speed`, `feel`, `playback`, `designation` | `"row.{field}: '{value}' is not a valid option"` |
| `PositiveNumbers` | `minCanvasDepth`, `tileGap`, `maxConcurrentMotion` ≥ 0 | `"row.{field} must be a non-negative number"` |
| `WelcomeTileFirst` | `tiles[0].type == 'text'` when a static welcome tile is expected | `"tiles[0] must be the welcome text tile"` |

### Reported, not fatal (surfaced in the build output)

| Report | Condition |
|--------|-----------|
| Undersized assets | Native short edge below the largest requested derivative — listed for re-export, never upscaled |
| Extension/format mismatch | File's real format differs from its extension |
| Alpha-carrying assets | Tile must sit on a defined background |
| Initial-viewport budget | Weight and request count, as numbers |

---

## Implementation Impact

Greenfield — everything is a Create. Nothing exists to modify, and there are no import sites to trace.

### Source files — delivered to Foster

| Action | File | Change |
|--------|------|--------|
| Create | `templates/_rows/gallery-row.twig` | The deliverable row: single root, reads `row.*`, one dense grid |
| Create | `templates/_components/includes/gallery-tile-media.twig` | Image/motion tile: square ImagerX media, primary anchor, corner icon |
| Create | `templates/_components/includes/gallery-tile-text.twig` | Text tile from named tokens; full-width below the legibility threshold |
| Create | `templates/_components/includes/gallery-build-icon.twig` | Sibling corner anchor + inline sliders SVG + contrast treatment |
| Create | `src/css/gallery.css` | Tailwind entry: `@source`, `@theme` brand tokens, grid contract, token classes |
| Create | `src/js/gallery-canvas.js` | Inert padding repeats to `minCanvasDepth`, complete final row |
| Create | `src/js/gallery-inertia.js` | Momentum scroll with immediate retarget and true native fallback |
| Create | `src/js/gallery-motion.js` | IntersectionObserver play/pause + concurrency cap |
| Create | `src/js/vendor/alpine.min.js` | Vendored single file, not npm-installed |
| Create | `docs/craft-field-definitions.md` | Field definitions Foster needs alongside the templates |

### Harness files — throwaway, never delivered

| Action | File | Change |
|--------|------|--------|
| Create | `harness/index.php` | Dev-server entry: renders the row, optional sibling placeholder blocks |
| Create | `harness/harness.twig` | Bare wrapper — no navigation, no footer, no sibling rows |
| Create | `harness/craft-shim/HarnessAsset.php` | Craft Asset surface: url, alt, width, height, focalPoint, alpha, `eagerly()`/`one()` |
| Create | `harness/craft-shim/HarnessImagerX.php` | GD-backed `transformImage`/`srcset`, focal-point crop, no upscaling, undersized report |
| Create | `harness/craft-shim/FixtureLoader.php` | Load + validate fixture, hydrate assets, fail fast |
| Create | `templates/_macros/color.twig` | Local stand-in for Foster's colour macro; **replaced by theirs at handoff** |
| Create | `data/gallery.fixture.json` | ~30 tiles: welcome text tile, ≥1 motion tile, rest images |
| Create | `bin/preview` | The single documented command (Tailwind build + PHP dev server) |
| Create | `bin/prepare-media` | One-off transcode of the source MP4 to a square muted web-weight loop |
| Modify | `README.md` | Replace template README with setup + the documented preview command |
| Modify | `.gitignore` | Add `dist/transforms/`, harness output |

### Verification files

| Action | File | Change |
|--------|------|--------|
| Create | `tests/acceptance/no-js.md` | View-source checks: alt text, copy, CTA labels, hrefs, no duplicate ids |
| Create | `tests/acceptance/a11y-keyboard.md` | Tab order, visible focus, distinct labels, repeats excluded |
| Create | `tests/acceptance/layout.md` | 320px two-column floor across gutter values, fluid reflow, no empty cells |
| Create | `tests/acceptance/motion-inertia.md` | Retarget-mid-glide, native fallback, reduced motion, concurrency cap |
| Create | `tests/acceptance/budget.md` | Lighthouse numbers, initial-viewport weight and request count |

**Totals:** 25 files created, 2 modified, 0 import sites (greenfield). 10 of the created files are the actual deliverable; 11 are harness/tooling that is never handed over.

---

## What Doesn't Change

Explicit scope boundaries.

- **Build 2.0** — untouched. The gallery links out to it per-tile; no shared code, no shared component model, no port of its markup, its `<button>` cards, or its hover behaviour.
- **The predefined row reference** — read-only. Its conventions are followed; the file itself is never edited.
- **Source media filenames** — never renamed; the names carry provenance, so `contentType` is assigned per tile in the fixture instead.
- **The rest of the SABIN site** — no navigation, footer, page chrome, or page-level CSS. Phase 0 site cleanup is a separate workstream.
- **Craft itself** — not wired up in this phase. The fixture is the data source; the shim is what makes that invisible to the template.
- **Tile order as a guarantee** — dense packing may reorder tiles. Only the welcome tile is pinned.
- **Phase 2 scope** — no shuffle scheduler, no tag filtering or tag UI, no additional imagery beyond the ~30.
- **Phase 3 scope** — no schema.org/JSON-LD markup, no Catalog/Downloads migration. Nothing here requires re-rendering tiles to add structured data later.
- **The footer** — a separate Craft row. The canvas simply ends; no footer behaviour of any kind is built.

---

## Resolved decisions

| Date | Decision |
|------|----------|
| 5 Aug 2026 | **Tab order follows document order.** Dense packing and "keyboard order matches visual order" are mutually exclusive; the criterion is reworded to document order. Jo accepted option 1. |
| 5 Aug 2026 | **Brand fonts supplied.** Neue Montreal Medium wired with `font-display: swap` plus a preload. Neue Montreal *Light* was not supplied, so running text still falls back to Helvetica. Messina Modern supplied but unused, so not loaded. |
| 5 Aug 2026 | **2x2 tiles raised to one quarter** of the set (8 of 32), limited to sources with a short edge of at least 768px. |
| 5 Aug 2026 | **Welcome tile copy set** to the founding statement, CTA relabelled "Overview booklet". |

## Design decisions to confirm

These could reasonably go the other way, or depend on information I don't have. Nine items; the first four are findings from inspecting the actual assets.

1. **Three files have the wrong extension.** `cable-stainless-steel.png`, `cord-green.jpg` and `thread-8470.jpg` are all **AVIF** images, not PNG/JPEG — confirmed by magic bytes. Craft and ImagerX may or may not cope, and serving them with an extension-derived Content-Type would be wrong. They're also the three 220px files already slated for re-export, so re-exporting fixes both problems at once. This also means the spec's "at least one asset is RGBA" is not the PNG it appears to be; the defined-background rule is kept regardless, since AVIF can carry alpha.

2. **Five assets need re-export, not three.** The spec flags the 220×220 swatches. Inspection shows two more that crop square below a 2in HiDPI tile's needs: `sabin-quad-diffuse-medical-ostap-min-5.jpeg` and `taper-pendant-sabin-lighting-gulf-min-04.jpeg` are both 1200×360, so a square crop yields at most 360×360. Otherwise the set is as described: 30 stills, exactly 4 square, exactly 4 at ≥2:1 (max 3.57:1).

3. **The only motion asset is the factory clip, which routes to About, not a product.** The fixture example in the spec references `/images/taper-showroom-loop.mp4`, which doesn't exist; the supplied clip is `FACTORY-SOUND_CUTTING-MACHINE.mp4`. Its `contentType` is therefore `factory`, so the milestone's one motion tile links to the About page rather than a product page. Confirm that's acceptable, or supply a product-side loop. Its poster would naturally be a factory still rather than the `taper-showroom` still the example names.

4. **Which two tiles get live Build links.** The spec says "two pre-selected in-situ office renders." The `Compositions for Build from images/` folder contains Rhino models for *Layer Rectangle Downlight* and *Obforma Downlight + Piping* — which match `layer-rectangle-downlight-sabin-clouds-min-2.jpeg` (clouds) and `obforma-downlight-sabin-elevator-min-4.jpeg` (an elevator), neither of which is an office render. Meanwhile `linear-aero-rendering-ostap-office-array-WARMER.jpeg` *is* an office render. Please name the two tiles explicitly.

5. **`2in` is 192 CSS pixels, not a physical inch.** CSS defines `1in` as exactly `96px`, so Build 2.0's 2in target card is 192px regardless of the display's real DPI. I've kept `2in` to mirror Build 2.0's own `--card-target` literally, but if the intent was a physical 2 inches this needs a different unit. Assumption: 192px is what's wanted, since it's what Build 2.0 actually renders.

6. **Foster's conventions are only partially visible.** The reference shows `copy-250`/`copy-400` and `color.bgColor()`/`color.textColor()`, but not the full type scale, the palette token names the macro accepts, the valid `row.theme` values, or which Tailwind major version their build uses. I've installed **Tailwind v4.3.3** and will stay on utilities that are stable across v3 and v4, but the type scale and token names should come from Foster rather than be invented — otherwise the handoff carries a rename pass. Worth one email.

7. **Webfont licences are not evidenced in the project.** No font files for Neue Montreal or Messina Modern are present. The spec says to flag rather than substitute silently — flagging. Fallbacks are Helvetica / Times New Roman, which is exactly the Times-New-Roman-flash the spec wants avoided, so this blocks the font-loading requirement.

8. **Non-square spans won't be pixel-exact.** Detailed under Semantics. `1x1` and `2x2` are exact at any gutter; a future `2x3` deviates by roughly the gutter. Adding sizes stays a data change, which is what the spec requires — confirming the aspect tradeoff is acceptable rather than adding a fixed row track that would break fluid reflow.

9. **Which primary destinations are live in Phase 1.** Phase 1 doesn't create product, swatch or About pages, and destinations must point at real pages "wherever those pages exist." I need the real URLs that exist today on sabin.design so the rest can be honestly marked `live: false` and render non-interactive.

---

*Ready for sprint planning once the items above are settled.*
