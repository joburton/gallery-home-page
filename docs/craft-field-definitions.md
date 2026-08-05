# Craft Field Definitions — Gallery Row

**For Foster Commerce.** These are the fields the `galleryRow` predefined row expects. The templates in `templates/` read only from a `row` variable with this shape, so wiring Craft up is a matter of making `row` deliver these — the templates themselves should not need changing.

`data/gallery.fixture.json` is a complete worked example of everything below.

---

## What the templates call

The row is authored against Craft's own API, so these must resolve as they normally do:

| Call | Used for |
|------|----------|
| `craft.imagerx.transformImage(asset, [{width: n}, …], {mode: 'crop', ratio: 1/1})` | Every media tile's square derivatives |
| `craft.imagerx.srcset(transforms)` | The `srcset` attribute |
| `asset.url`, `asset.width`, `asset.height` | `src`, `width`, `height` |
| `asset.alt` | Read from the tile's own `alt` field, not the asset — see note below |
| `asset.focalPoint` | Honoured by the crop |
| `_macros/color` → `bgColor(theme)`, `textColor(theme)` | Row theming |

**Delete `templates/_macros/color.twig`** and let the site's real macro resolve. Ours is a local stand-in so the row renders in the prototyping harness; the row imports it exactly as the supplied reference row does.

**Alt text lives on the tile, not the asset.** The same asset can appear in different contexts, and the brief treats alt text as content. If you prefer to source it from the asset, `tile.alt` is the single read site.

### Two conventions worth preserving

1. **The type-scale and palette token names are ours, not yours.** We could not see your full scale, so `src/css/gallery.css` defines `copy-100/250/400/600` and `--color-sabin-*` following the pattern visible in the reference row. Renaming them to match your existing scale touches two files: that stylesheet and `harness/craft-shim/Tokens.php`.

2. **Two things about the webfont.** `src/css/gallery.css` references `/fonts/NeueMontreal-Medium.woff2` root-relative — change that one prefix if fonts are served elsewhere on the domain. The page must also carry the matching preload:

   ```html
   <link rel="preload" href="/fonts/NeueMontreal-Medium.woff2" as="font" type="font/woff2" crossorigin>
   ```

   **We deliberately do NOT preload.** The stylesheet declares a metrics-matched fallback (`size-adjust` plus ascent/descent overrides, tuned against a rendered specimen) so the pre-swap render occupies exactly the same space as the real face — no serif flash and no layout shift, measured at CLS 0.0000. With the shift eliminated, preloading only put 31 KB ahead of the LCP image and cost 148 ms. Re-measure before adding one.

   Both faces are referenced root-relative from `/fonts/`. Note the Messina Modern licence (Luzi Type) covers a single domain with a monthly page-view cap.

3. **`--gallery-cta-min-size` is a contrast floor, not a style choice.** Sabin orange is 3.96:1 against dark-gray and 3.34:1 against white — both fail WCAG AA for normal text. The CTA label is pinned at 24px, which reaches the large-text bracket on size alone, so the orange button stays compliant at the real 500 weight. (Asking for 700 with only a Medium face supplied made the browser synthesise a fake bold.) Reducing the size silently breaks accessibility.

---

## Row fields

| Field | Type | Required | Default | Notes |
|-------|------|----------|---------|-------|
| `theme` | dropdown | Yes | `light` | `light` \| `dark`. Feeds the colour macro |
| `layoutMode` | dropdown | Yes | `swiss` | `swiss` \| `masonry` |
| `minCanvasDepth` | number | Yes | `15` | Minimum canvas depth **in screens** |
| `tileGap` | number | Yes | `4` | Gutter in px. Single source of truth for both the grid `gap` and the two-column floor |
| `cardTarget` | text (CSS length) | Yes | `2in` | Target 1×1 cell size. `2in` = 192 CSS px exactly |
| `maxConcurrentMotion` | number | Yes | `4` | Motion tiles allowed to play at once |
| `buildIcon` | asset (single) | No | `null` | `null` uses the built-in sliders SVG; an asset overrides it globally |
| `scrollInertia.enabled` | lightswitch | Yes | `true` | |
| `scrollInertia.speed` | dropdown | Yes | `medium` | `slow` \| `medium` \| `fast` |
| `scrollInertia.feel` | dropdown | Yes | `smooth` | `smooth` \| `glide` \| `overshoot` |
| `tiles` | matrix / nested entries | Yes | — | The tiles, in order |

**No code default backs any of these.** A missing field is a validation error rather than a silent fallback, because a code-side default would put a gallery setting outside the row. Set defaults at the field level in Craft.

**None of these are visitor-facing**, now or later. There is no control anywhere in the row that lets a visitor change a layout mode, a designation, or an inertia setting.

---

## Tile fields

One block type would work, with fields shown/hidden by `type`. Image and motion deliberately share a single `media` field so switching a tile's type is a data change, not a schema change.

| Field | Type | Applies to | Notes |
|-------|------|-----------|-------|
| `id` | text | all | **Stable and unique.** Rendered as `data-tile-id` so an admin can identify a tile unambiguously. Never reuse |
| `type` | dropdown | all | `image` \| `motion` \| `text`. Changeable at any time |
| `size` | text | all | `NxM`, e.g. `1x1`, `2x2`. Generic — `2x3` needs no code change beyond one CSS rule |
| `designation` | dropdown | all | `static` \| `dynamic`. `static` only means excluded from the future shuffle; it still scrolls normally |
| `media` | asset (single) | image, motion | Image for `image`; **video (muted MP4) for `motion`** |
| `poster` | asset (single) | motion | **Required.** First frame, so a tile is never blank |
| `alt` | text | image, motion | Required, descriptive. Treated as content |
| `subject` | text | image, motion | Short product/subject name for accessible labels: "View {subject}", "Configure {subject} in Build". Falls back to `alt` — usually the linked product's title |
| `contentType` | dropdown | image, motion | Drives the primary destination (table below). Not derivable from the filename |
| `playback` | dropdown | motion | `autoplay` \| `click` |
| `tags` | tags | image, motion | Carried through; unused until Phase 2 tag skewing |
| `focalPoint` | (on the asset) | image, motion | Set in Craft for any image whose subject is off-centre — otherwise the square crop cuts it out |
| `destination` | link + lightswitch | image, motion | `{ type, url, live }` — the whole-tile link |
| `buildLink` | link + lightswitch | image, motion | `{ url, live }` or null — the corner icon's link |

### Text tile fields

Every styling value is a **named token**. Free-form colour, size and spacing values are rejected — that is what keeps text tiles on-brand.

| Field | Type | Options |
|-------|------|---------|
| `text.heading` | text | Required |
| `text.body` | text | **Optional** — a tile may carry only a heading and a button |
| `text.headingLevel` | dropdown | `display` \| `title` \| `subtitle` |
| `text.background` | dropdown | `orange` \| `yellow` \| `dark-gray` \| `white` \| `light-gray` \| `off-white` |
| `text.padding` | dropdown | `sm` \| `md` \| `lg` |
| `text.linkBehavior` | dropdown | `cta-only` (default) \| `whole-tile` |
| `text.cta` | link + lightswitch | Optional under `cta-only` (an informational tile routes nowhere); **required** under `whole-tile`. `style` is `primary` \| `secondary` \| `quiet` |
| `text.media` | asset (single) | Optional embedded **motion**, same rules as a motion tile |
| `text.image` | asset (single) | Optional **still** graphic — a logo or mark. Rendered contained and bottom-aligned, never cropped |
| `text.imageAlt` | text | **Required when `text.image` is set.** A logo is content; a reader who cannot see it still needs to know whose mark it is |
| `text.bodyStyle` | dropdown | `primary` (Neue Montreal) \| `secondary` (Messina Modern). Selects a brand face by name, never a font name |

**Roughly a quarter of tiles are `2x2`** by design (8 of 32). A `2x2` needs a source with a short edge of at least 768px, or it renders soft — the build reports any that fall short.

**A text tile wants at least `2x2`.** A 1×1 text tile is roughly one card-target across, which cannot hold a heading, body and button comfortably. A guard tightens padding so nothing is clipped, but 2×2 is the intended minimum. At narrow widths any text tile automatically spans the full grid width as a short, wide block.

**Text tiles must not offer a `buildLink` field at all.** The corner icon means "configure this product", and a text tile is not a product.

---

## Routing

`contentType` determines the whole-tile destination. There is no per-tile override, so routing stays consistent as tiles are added.

| `contentType` | Primary destination |
|---------------|---------------------|
| `in-situ`, `mid-zoom`, `detail`, `sketch` | The product page for the thing shown |
| `swatch` | The swatches page |
| `factory` | The About page |

### The `live` flag

`live: false` renders the tile as **non-interactive** — no anchor, no href, absent from the tab order, not announced as a link. The media and its alt text still render, so the tile remains real content for crawlers. This is how placeholder destinations are represented; there is deliberately no dead `href="#"` anywhere.

### The corner Build icon

A second, **sibling** anchor — never nested inside the primary link, since nested anchors are invalid HTML. It renders only when `buildLink` is present and `live`. Each link carries its own distinct accessible label so a screen-reader user hears "View Block" and "Configure Block in Build" rather than "link, link".

---

## Invariants Craft must not break

These are the constraints the row depends on. Full list in `CLAUDE.md`.

1. **Each unique tile appears in the served HTML exactly once**, with `data-tile-id` on that instance only. The repeats that pad the canvas to `minCanvasDepth` are generated client-side and are inert (`aria-hidden`, `inert`, no id). Server-rendering the repeats would produce duplicate ids and duplicate content.
2. **Every routable tile is a real `<a href>`** — never a `<button>` or a click-handled `<div>`. Build 2.0 uses `<button>` for its cards; that is specifically not copied here, because it would make the gallery's routing invisible to search and AI crawlers.
3. **All tile content is server-rendered.** JavaScript only adds canvas padding, scroll inertia and motion playback.
4. **The row is self-contained** under a single root element, with no page-level CSS and no assumption of owning the viewport.
5. **Every gallery setting reads off `row.*`.** Nothing is stored elsewhere, which is what lets the same template render a different data source unchanged.
