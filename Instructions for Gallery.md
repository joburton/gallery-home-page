# GALLERY — PHASE 1 BUILD BRIEF (FOR CLAUDE CODE)

## How to read this brief

Requirement language in this document is deliberate. "Must" marks a hard invariant — do not trade it away for convenience, and flag it rather than silently working around it. "Default" marks a starting value that is expected to be tuned later, so it must be exposed as configuration rather than hardcoded. Anything under an "Open to Claude's judgment" heading is genuinely open. Everything else has been decided; if something appears to be missing or contradictory, raise it rather than guessing.

Before building, distill the hard invariants from this brief into CLAUDE.md so they survive long sessions: no React; all meaningful content in crawlable server-rendered HTML; real links, not buttons; no visitor-facing controls for designation or layout; brand tokens only. Those are the constraints that must never drift.

## Scope note

This document covers only the Phase 1 gallery build — developed standalone, but delivered as a predefined row (see What is being delivered below). It intentionally excludes:

- Phase 0 site cleanup (agent portal rename, catalog page changes)
- Phase 2 (full Build connection, periodic auto-shuffle — cadence TBD, tag-based skewing)
- Phase 3 (Foster handoff, go-live, Catalog/Downloads merge)

Those are separate workstreams with their own instructions.

## Guiding principles

SEO and LLM-search optimization (how AI answer engines and crawlers read this page) are first-order concerns for every decision below, not an afterthought:

- Any content that carries meaning — the welcome text tile, its CTA, media alt text — must be real, semantic HTML. Never a screenshot or image of text.
- Tile markup (media with alt text, text-tile copy, CTAs) must exist in the page's actual HTML — rendered server-side or at build time. Alpine.js and vanilla JS may enhance interactivity and scroll behavior, but must not be the only way tile content reaches the DOM. This matters even before Craft is wired up, because it sets the rendering pattern Craft will plug into.
- Every routable tile must be a real anchor (`<a href="...">`), never a `<button>` or a click-handler on a `<div>`. Crawlers and LLM crawlers follow hrefs; they do not click buttons. Note that Build 2.0 uses `<button type="button">` for its cards — this is a specific place NOT to copy it, since doing so would make the gallery's entire routing invisible to search and AI crawlers and defeat this principle.
- Full structured data/schema.org markup is bundled with the later Catalog/SEO migration workstream (Phase 3) and is out of scope here — but nothing built in this phase should require re-rendering the tiles to add it later.

## Development process

Use the leos_claude_starter framework (https://github.com/leogodin217/leos_claude_starter) as the working process for this build — provided as a zip in this folder. It's a Claude Code workflow (architect → sprint → implement → review), not a frontend tool; it doesn't conflict with the stack decisions below.

- Run /get-started once to configure the project, and record this brief's hard invariants in CLAUDE.md as described above.
- Use /arch-design for this gallery feature, then /create-sprint to break Phase 1 into sprints, /implement-sprint to execute, and /review-sprint plus /verify-sprint to check the work.
- Verify against the Acceptance criteria section below — those are the concrete checks /eval-sprint and /verify-sprint should test, rather than a subjective read of whether the build "looks right."

## What is being delivered

The deliverable is a predefined row — a self-contained, reusable content block that Craft can place and reorder among other rows on a page, matching the pattern SABIN's site already uses. It is not a standalone page, and it must not assume anything about what sits above or below it. Confirmed with Foster (the website agency) as the required output shape.

Alongside it, build a minimal local harness page whose only job is to display the row for prototyping: it pulls in the row template with the mock data and nothing else — no navigation, no footer, no sibling rows. Run the preview command and the gallery fills the browser so it can be scrolled, have its layout mode flipped, its inertia toggled and its tiles clicked, exactly as a prototype should. The harness is throwaway and is never delivered; Foster receives only the row template and its field definitions.

Building row-shaped from the start is what makes the prototype trustworthy. A standalone page invites accidental dependencies — page-level CSS, assumptions about owning the full viewport, styles that only work because nothing else is present — and those are exactly what break on injection day. A row that renders correctly inside a bare harness will render correctly on the real homepage, because it was never allowed to depend on its surroundings.

- One exception worth knowing: page-wide scroll inertia cannot be fully judged from the harness, because the harness contains only the gallery. On the real homepage the momentum will also affect rows below it. This is a reason to keep inertia Craft-toggleable, not a reason to avoid building row-shaped.

## Build setup

Templates are authored in Twig — the same templating language Craft uses. For Phase 1, Claude Code sets up a minimal local PHP + Twig harness that reads the mock JSON fixture and renders real HTML to disk or via a local dev server. When Foster integrates in Phase 3, the JSON source is swapped for Craft's own element queries and the templates themselves should survive largely intact. This is the single most important structural decision in this brief: do not substitute a JavaScript templating language or static-site generator, because that turns the Phase 3 handoff into a rewrite.

Follow the conventions in the supplied predefined row reference (the Twig sample from Foster, in this folder) rather than inventing parallel ones. Specifically: a single root element for the row; fields read off a row variable (row.heading, row.theme, and so on); theming via the existing colour macro rather than hardcoded classes; named typography classes from the existing scale; image transforms and srcset via ImagerX (craft.imagerx.transformImage and craft.imagerx.srcset) rather than a bespoke image pipeline; and small pieces of UI such as the Build icon as includes under the existing component-includes directory.

- Keep dependencies deliberately light, because the project lives in a cloud-synced folder and large dependency trees cause sync thrashing and intermittent build failures. Use Tailwind's standalone CLI binary rather than a Node/PostCSS toolchain, vendor Alpine.js as a single file rather than installing it, and use Composer only for Twig itself.
- The project lives in this same folder (alongside this brief) on the local machine. There is no remote repository for now. Because Claude Code will make many automated edits, initialising a local git repository in place is recommended so changes can be rolled back; this can be added at any time without relocating anything.
- Provide a single documented command to preview the gallery locally, recorded in the README, and keep it working for the life of the project.
- Browser support: current versions of Chrome, Edge, Firefox and Safari, plus current iOS Safari and Chrome on Android. No Internet Explorer, and no support obligations for browsers older than the previous major release.
- Source media lives in the images/ folder in this project directory. The supplied files keep their original descriptive names (for example sabin-may-2025-factory-16.jpeg, tri-sketch-sabin-lighting.jpeg, ribbon-webbing-swatches-...jpg) rather than following a coined convention — the names carry provenance, so do not rename them. Because contentType is therefore not derivable from the filename, assign it per tile in the fixture.

## Tech stack — explicit

The rest of the SABIN site runs on Tailwind CSS + Alpine.js, so the gallery should stay in that same stack for long-term integration and so its content can eventually be administered through Craft CMS like the rest of the site. Build 2.0 (the product configurator) is a separate React application — the gallery links out to it per-tile but does not share its codebase or component model.

### Use

- Twig for all templating (see Build setup above)
- Tailwind CSS for all styling, via the standalone CLI
- Alpine.js for interactivity — click-routing and scroll-related behavior only. Tile designation, layout mode, and inertia settings are pure data reads from Craft-sourced config; Alpine must never expose a visitor-facing control to change them.
- A lightweight vanilla-JS approach for the scroll/inertia behavior (e.g. a small momentum-scroll technique, or a minimal library like Lenis) — evaluate what's needed once the container behavior is built; don't reach for anything heavier than necessary

### Do not use

- React, or any component framework — Build 2.0 (the configurator) is React; this page needs to match the rest of the site's Tailwind + Alpine stack instead, and should not become a second frontend paradigm on this site

### Reference only (visual/style — not code)

Build 2.0's existing gallery may be used to match grid proportions, tile sizing, and spacing — same overall design language, including its fluid auto-fill grid mechanism and its 1x1/2x2 tile spans (confirmed by direct inspection — see Container/layout below; Build 2.0 mixes 1x1 and 2x2 cards using grid-column/row spans on the same auto-fill track grid, the same approach this build should use). This does not extend to its markup (see the anchor requirement above), nor to interaction patterns like hover states, which are open for this build to define. Do not port its code or component structure. Build 2.0's tag filter bar is also out of scope here — do not build gallery filtering in this phase.

## Content & data architecture

Content (media, text tiles, tags, and each tile's routing destination) must ultimately be manageable in Craft CMS, using the same tagging convention Build 2.0 already uses. Craft is not wired up yet for this standalone build, but build as if it is: shape the data layer to match what a Craft GraphQL query would plausibly return, and load it from a local mock/fixture file rather than hardcoding tile data inline. This avoids a rebuild when Craft is connected later.

Once Craft is connected, it should be the single place to administer: which tiles exist (add/remove), each tile's type (image, motion, or text — changeable at any time), each tile's size/span, each tile's static/dynamic designation, the gallery's layout mode, the scroll inertia effect (on/off, speed, feel), and the canvas's minimum depth. None of these are visitor-facing controls, now or later.

Each tile needs a stable, unique identifier so a Craft admin can unambiguously select which tile they're editing, resizing, or deleting. Build 2.0 does this with a data-design-number attribute on each card (exposed as its aria-label and title too) — since our tiles aren't individual product designs, we don't copy that exact scheme, but keep the same underlying id field already in the mock data (tile-001, tile-002, ...) as the stable identifier, and expose it as a real DOM attribute (e.g. data-tile-id) the way Build 2.0 exposes its own. Because tiles repeat to fill the canvas (see Container/layout), only the first, canonical instance of a tile carries this id — repeated instances must not, so the document never contains duplicate ids.

### Tile types

There are three tile types: image, motion, and text. A tile's type is chosen — and changeable later — in Craft, not fixed at creation.

- Image and motion tiles share the same shape: a media source, a poster/still where applicable, alt text, contentType, tags, a primary destination, and an optional secondary Build link. They route identically. Use one media field for both rather than separate image and video fields, so switching a tile's type is a data change and not a schema change.
- Motion tiles are short silent loops, encoded as muted MP4. "Motion" deliberately covers what would previously have been split into video and GIF tiles — one type, one encoding, playback behaviour set per tile. Do not use true GIF encoding: a single GIF can outweigh every other asset on the page, which works directly against the performance requirements below. Craft may accept a .gif upload but must convert it.
- Text tiles are structurally different — a flexible content block rather than a single media element. See below.

### Media, aspect ratios and cropping

Rendered tiles are always square, but the source images are not. Of the thirty supplied assets only four are square: the rest are portrait, 4:3, 3:2, 16:9, and four that are 2:1 or wider, up to 3.57:1. This is normal and matches how Build 2.0 works — it crops to a 1:1 ratio via ImagerX rather than requiring square originals.

- Crop every media tile to 1:1 through ImagerX, following the supplied row reference. Centre is the default crop position, and centre-cropping is accepted even for the panoramic files where it will discard much of the frame — if a particular image does not read well cropped, it gets swapped for a different one rather than the grid accommodating it.
- Honour Craft's per-asset focal point where one is set, so the crop keeps the actual subject. This matters because centring will otherwise cut the fixture out of some room photographs — the subject is frequently not in the middle of the frame. Focal points are the intended mechanism for correcting that, and should be set in Craft for any image whose subject sits off-centre.
- Tile sizes remain 1x1 and 2x2 only. No wide or panoramic tile size is introduced to accommodate the wide source images; they are cropped square like everything else.

### Tile links and the Build icon

A media tile has two possible destinations, not one. Clicking the image itself goes to the relevant page on the website — for most tiles, the product page for the thing shown. A separate small icon in the bottom-right corner is what sends a visitor to that product's composition in Build. This exists because dropping someone straight from a photograph into a specification tool is too abrupt a jump: most people want to look at the product first, and only some of them want to start configuring it.

- The primary destination is driven by contentType, as before: in-situ, mid-zoom, detail and sketch tiles go to the product page for what they show; swatch tiles go to the swatches page; factory tiles go to the About page. This is the whole-tile click.
- The Build link is a separate, optional field per tile. When a tile has one, a small icon renders in the bottom-right corner as a second, independent link. When it doesn't, no icon renders at all — an icon that goes nowhere advertises a feature that isn't there and creates a dead interactive element, which the dummy-tile rule below already forbids.
- These are two sibling anchors, not nested ones — nested `<a>` elements are invalid HTML and browsers will restructure them unpredictably. Follow the pattern already used in SABIN's predefined rows: the primary link is an anchor stretched over the tile with absolute inset-0, and the icon is a second absolutely-positioned anchor above it in the corner, higher in the stacking order. Clicking the icon reaches the icon's link; clicking anywhere else on the tile reaches the primary link.
- Each of the two links needs its own distinct accessible label — something like "View [product name]" and "Configure [product name] in Build" — or a keyboard and screen-reader user hears only "link, link" with no way to tell them apart.
- The icon defaults to a sliders glyph (the horizontal-lines-with-dots mark familiar from filter and settings UI), which reads as "adjust this" without the commerce connotation of a cart. It must be replaceable later with an uploaded mark, managed in Craft. Implement the default as an inline SVG following the existing component-include convention rather than a bitmap, so it inherits colour from the theme and stays crisp.
- Because the icon sits over unpredictable photography, it needs its own contrast treatment — a subtle scrim, shadow, or blurred chip behind it — so it stays legible on both light and dark images. It must not rely on the image happening to be dark in that corner.
- On touch devices there is no hover, so the icon must be permanently visible (at reduced opacity if desired) rather than hover-revealed only. Hover-revealing it on desktop is fine, but touch users must never have to discover it by guessing.
- Text tiles never carry the Build icon. This is not only about visual clutter: the icon means "configure this product," and a text tile is not a product, so there is nothing for it to configure. Text tiles link via their CTA or whole-tile behaviour only, as described below.

### Text tiles

Text tiles are configurable per tile, but constrained to brand tokens rather than arbitrary values. This keeps them flexible without allowing off-brand output, and is markedly simpler to model in Craft. Widening this later is easy; reining it in is not.

- Background: a named choice from the brand palette (or a named pattern/image), not an arbitrary hex value.
- Padding/margins: a named step from a small scale, not arbitrary pixel values.
- Typography: a named level from a defined type scale, not free font/size/weight selection.
- CTA button: configurable label and destination, with style chosen from two or three predefined named styles (e.g. primary, secondary, quiet) rather than arbitrary colour and shape.
- Optional embedded motion media within the tile, subject to the same encoding and playback rules as motion tiles.
- Link behaviour is per tile: text.linkBehavior "cta-only" (default — only the CTA button is routable) or "whole-tile" (the entire tile is a single link, for tiles that point out to another site).
- At narrow widths a text tile must span the full grid width rather than staying square. At the two-column floor a 1x1 tile is roughly half the viewport and square, which cannot hold a heading, body copy and a button legibly — so below a threshold the tile becomes a short, wide block instead. Nothing is hidden and no type is shrunk to fit.

### Designation and shuffle

The top-left tile is a text tile carrying the welcome message and is static by default; all other tiles default to dynamic regardless of type. Static only means excluded from the periodic shuffle — it does not mean pinned in place; a static tile still scrolls with the rest of the canvas like any other tile.

Periodic auto-shuffle logic itself (cadence TBD — daily, weekly, or monthly; not yet decided) is Phase 2 scope, but build the data/render layer so a tile's designation can change without a rebuild — don't bake final tile positions into markup as if designation were fixed at build time. Phase 2 should be able to add a shuffle scheduler on top of this, at whatever cadence is eventually chosen, without restructuring it.

Suggested shape for the mock data (adjust field names to taste, but keep this general shape). It deliberately mirrors how Craft will deliver the row: the gallery's settings are fields on the row itself, and the tiles are a nested field within it — not a free-standing config object. This milestone is roughly 30 tiles — mostly image tiles, at least one motion tile, plus the welcome text tile; generate the full set of entries in the fixture rather than only the three shown here:

```json
{
  "row": {
    "type": "galleryRow",
    "theme": "light",
    "layoutMode": "swiss",
    "minCanvasDepth": 15,
    "tileGap": 4,
    "maxConcurrentMotion": 4,
    "buildIcon": null,
    "scrollInertia": {
      "enabled": true,
      "speed": "medium",
      "feel": "smooth"
    },
    "tiles": [
    {
      "id": "tile-000",
      "type": "text",
      "size": "1x1",
      "designation": "static",
      "text": {
        "heading": "Sabin",
        "headingLevel": "display",
        "body": "Lighting, considered.",
        "background": "light-gray",
        "padding": "md",
        "linkBehavior": "cta-only",
        "media": null,
        "cta": {
          "label": "Explore the collection",
          "url": "/build",
          "style": "primary"
        }
      }
    },
    {
      "id": "tile-001",
      "type": "image",
      "media": "/images/1916_BorenAve-011-edited.jpeg",
      "alt": "Sabin linear pendants above an open-plan office",
      "size": "2x2",
      "contentType": "in-situ",
      "tags": ["pendant", "office", "warm"],
      "destination": {
        "type": "product",
        "url": "/products/linear-pendant",
        "live": true
      },
      "buildLink": {
        "url": "https://www.gallery.sabin.build/4/",
        "live": true
      },
      "designation": "dynamic"
    },
    {
      "id": "tile-014",
      "type": "motion",
      "media": "/images/taper-showroom-loop.mp4",
      "poster": "/images/taper-showroom-sabin-june-2026-min-5.jpeg",
      "alt": "Slow pan across a brushed brass pendant shade",
      "size": "1x1",
      "contentType": "detail",
      "playback": "autoplay",
      "tags": ["pendant", "brass"],
      "destination": {
        "type": "product",
        "url": "/products/brass-pendant",
        "live": true
      },
      "buildLink": null,
      "designation": "dynamic"
    }
    ]
  }
}
```

Every field on the row is Craft-driven and never visitor-facing. row.theme follows the existing site convention and feeds the colour macro. row.tileGap is the gutter between tiles in pixels, defaulting to 4. row.buildIcon is null by default, meaning the built-in sliders SVG is used; setting it to an uploaded asset overrides the icon globally. size uses a generic cols x rows format (e.g. "1x1", "2x2", "2x3") — this milestone only uses 1x1 and 2x2, but the code must not assume those are the only valid values.

destination is the primary, whole-tile link and its type follows from contentType ("product", "swatches", "about"). buildLink is the separate, optional secondary link behind the corner icon; when it is null or not live, no icon renders. Text tiles have no buildLink at all and route via text.cta.url or the whole tile, per text.linkBehavior. playback is per tile ("autoplay" or "click"). The live flags distinguish wired-up destinations from placeholders for this milestone. tags are carried through the data layer but unused in Phase 1 — tag-based skewing is Phase 2, so do not build filtering or tag UI now.

## Container / layout

### Fixed

- Grid system must support arbitrary multi-cell spans, not just 1x1/2x2 — this milestone only uses those two, but adding a size like 2x3 later must be a data change, not an architecture change.
- Vertical scroll only (not left/right); canvas stays within the site's normal breakpoint margins horizontally.
- Canvas depth is data-driven: row.minCanvasDepth sets the minimum depth in screens (defaults to about 15 for this milestone), and must be an editable value, not a hardcoded constant — with fewer tiles early on, 15 may feel too deep, so this needs to be tunable independently of tile count. The canvas also grows automatically as more tiles are added in the CMS. Not true infinite scroll — the canvas is simply long enough, and reuses/repeats tiles if there aren't enough to reach the minimum depth.
- Tile repetition must not be served as duplicated HTML. The server-rendered document contains each unique tile exactly once — that is what crawlers, and the SEO/LLM principles above, depend on. The repeats that pad the canvas to its minimum depth are generated client-side and must be inert: no data-tile-id, aria-hidden="true", and removed from the tab order. Repeating thirty tiles across fifteen screens of markup would mean the same alt text and the same media duplicated many times over in the served HTML, which is both a duplicate-content problem and a large avoidable DOM.
- No drag-and-drop, no manual reordering by visitors.
- The footer is out of scope entirely — it is a separate predefined row managed in Craft, not part of the gallery. The gallery canvas simply ends, and whatever row sits below it follows. Do not build footer behaviour of any kind into the canvas.
- Columns reflow fluidly with viewport width — no discrete breakpoint jumps in column count. Confirmed by inspecting Build 2.0 directly: it uses a CSS Grid with grid-template-columns: repeat(auto-fill, minmax(min(target-card-size, 50%), 1fr)) and a 4px gap. The minmax(...,50%) term guarantees a floor of exactly 2 columns at any viewport width — verified down to very narrow widths, it never collapses to 1 column. Match this mechanism, not a Tailwind sm/md/lg/xl breakpoint set. Multi-cell tiles (2x2, future 2x3) apply grid-column/grid-row spans on top of this same auto-generated track grid — confirmed this is exactly how Build 2.0 mixes its own 1x1 and 2x2 cards.
- Target card size must match Build 2.0's actual rendered size (2in per 1x1 cell, confirmed by inspection) rather than being left open — but expose it as a single configurable value (a CSS custom property or config field, mirroring Build 2.0's own --card-target approach), not a hardcoded constant, so it can be retuned later without a rebuild.
- The gutter between tiles must be editable in Craft, not a fixed constant. row.tileGap is a numeric value in pixels, defaulting to 4 to match Build 2.0, so the spacing can be tuned by eye against real imagery without a rebuild.
- The gutter must have a single source of truth, because it appears in two places: as the grid's gap, and inside the minmax(min(target-card-size, calc((100% - gap) / 2))) expression that guarantees the two-column floor. Drive both from one CSS custom property. If they are allowed to diverge, changing the gutter silently breaks the two-column floor and the grid collapses to a single column at narrow widths with no obvious cause.

### Layout modes

Two modes, both built on the same CSS Grid — there must be only one layout engine. row.layoutMode selects between them and is Craft-driven, never visitor-facing.

- Both modes use dense packing (grid-auto-flow: dense), confirmed by inspection to be what Build 2.0 already does. When a 2x2 tile leaves a hole, a later 1x1 backfills it automatically, so the grid stays tight and no empty cells are left sitting in the layout. The consequence is that strict tile order is not guaranteed — a tile further down the list may move up to fill a hole. That is accepted, with one exception: the top-left welcome tile must be pinned to the first cell explicitly rather than left to the packing algorithm.
- Swiss (default): uniform tiles on the packed grid — every tile is a square 1x1 or 2x2, so rows align and the result reads as strict and orderly.
- Masonry: the same packed grid, but tiles take varying row spans so rows stop aligning and columns end up staggered — the visual character of classic masonry without introducing a second, column-based layout system that would fight the auto-fill grid. 2x2 and future larger tiles continue to work normally, which a true column implementation could not offer. The difference between the two modes is whether row spans vary, not whether packing is dense.
- The final row must not ship ragged. Because the canvas already repeats tiles to reach its minimum depth, it can always pull another tile to complete the last row rather than ending mid-row.

### Scroll inertia

- Scroll inertia must be Craft-toggleable (row.scrollInertia.enabled) — not hardcoded on. If it's built in and never made configurable, there's no way to turn it off after the fact if it doesn't feel right.
- Two independent settings, both exposed in Craft. Speed: slow, medium, fast. Feel: smooth (restrained deceleration), glide (carries further), overshoot (a slight rubber-band settle). Defaults are medium and smooth.
- New scroll input must immediately retarget the scroll mid-animation. A visitor scrolling again while momentum is still decaying must have that input take effect at once — never queued, never swallowed until the current glide settles. This is the single detail that separates momentum scrolling that feels good from momentum scrolling that feels broken and laggy, so treat it as a hard requirement rather than a polish item. The visitor must always feel able to scroll on top of the decaying motion and stay in control.
- Inertia must degrade to genuinely native scrolling. Momentum-scroll techniques typically intercept and replace native scroll, which breaks keyboard scrolling, scrollbar dragging, browser find-in-page, and mobile browser chrome behaviour. With row.scrollInertia.enabled false — or when the visitor's OS requests reduced motion — the page must fall back to plain native scroll with no interception left in place, not merely a shorter animation.

### Open to Claude's judgment

- Specific inertia implementation technique — a small momentum-scroll approach or a minimal library like Lenis are reasonable starting points; evaluate what's actually needed, don't reach for anything heavier than necessary. The technique is open; that it's toggleable, has the two settings above, and degrades to native scroll is not.
- Hover, focus-visible and transition treatments for tiles, within the brand's restraint. Build 2.0's hover behaviour is explicitly not the reference here.

## Functionality

Every routable tile is a real anchor. Media tiles have two independent destinations — the tile itself, and the corner icon — as described under Tile links and the Build icon above:

- Clicking an image or motion tile goes to its primary destination, which follows from contentType: in-situ, mid-zoom, detail and sketch tiles go to the product page for what they show; swatch tiles go to the swatches page; factory tiles go to the About page.
- Clicking the corner icon goes to that product's composition in Build. The icon only exists on tiles that have a live buildLink; tiles without one show no icon.
- Text tiles route per text.linkBehavior — either the CTA button only (default) or the whole tile as a single link — and never carry a corner icon. External destinations should open with appropriate rel attributes.

For this milestone (roughly 30 tiles):

- Primary destinations point to real pages on the existing website wherever those pages exist; Phase 1 does not create product, swatch or About pages.
- Only two pre-selected in-situ office render tiles have live Build links, so only those two show the corner icon. Every other tile renders with no icon at all.
- Any destination marked live: false does nothing on click — no indicator, no placeholder navigation. Because it does nothing, it must not be announced to assistive technology as a link and must not sit in the tab order; render it as a non-interactive element rather than an anchor with a dead href.
- To evaluate how the icon reads across the whole grid before real Build links exist, the fixture may temporarily mark additional tiles as having live Build links pointing at a placeholder URL. That is a preview aid only and must not ship.

## Performance & accessibility

A fifteen-screen canvas of 2in tiles is potentially several hundred media elements on the single most performance-sensitive page on the site, and Core Web Vitals feed directly back into the SEO goal stated at the top of this brief. Treat the following as requirements, not optimisations:

- Media below the initial viewport must be lazy-loaded (loading="lazy" or an equivalent observer-driven approach), with only the first screen or two eager. Provide width/height or aspect-ratio on every tile so the grid never reflows as media loads.
- Serve modern formats (WebP or AVIF for stills, muted MP4 for motion) with appropriate fallbacks, and responsive srcset/sizes so a 2in tile never downloads a full-resolution render. Rendered tiles are always square, which makes correct sizing straightforward.
- Never upscale a source asset to fill a tile. Some supplied files are small — the material and swatch images are only 220 pixels square, which is below what a 2in tile needs on a high-density display and will look soft. Render them at their native size rather than stretching them, and flag them for re-export rather than silently upscaling. At least one asset is RGBA and may carry transparency, so tiles must sit on a defined background rather than assuming media is fully opaque.
- Set an explicit page-weight and request budget for the initial viewport and hold to it; report the numbers as part of sprint verification. If the pseudo-infinite canvas cannot meet the budget by lazy-loading alone, windowing/recycling offscreen tiles is preferable to shipping hundreds of elements.
- Motion tiles default to autoplay, muted and looping, but only while onscreen — pause them via an intersection observer when they scroll out of view, and never autoplay at all under reduced-motion. Every motion tile carries a poster/first frame so a tile is never blank while loading.
- Cap the number of motion tiles playing simultaneously (row.maxConcurrentMotion, default 4) so that adding many motion tiles in Craft cannot sink the page. Beyond the cap, tiles hold their poster frame until a slot frees up.
- Honour prefers-reduced-motion: reduce for scroll inertia, tile transitions, and motion tile playback. This is a hard requirement, not a nicety — momentum scrolling is a known trigger for motion sensitivity.
- A keyboard-only visitor must be able to reach and activate every unique routable tile, in an order matching the visual layout, with a clearly visible focus indicator. Padding repeats must be outside the tab order entirely so tabbing through the gallery does not take hundreds of presses.
- Alt text is content, not decoration: every image and motion tile carries a descriptive alt/label, since crawlers and answer engines lean on it heavily. Text tiles need no alt — their copy is already real text.
- Load the two brand webfonts with an explicit strategy (font-display and preloading of the primary face) so the homepage does not render its headline in Times New Roman before swapping. If web licences for Neue Montreal or Messina Modern are not in place, flag it rather than substituting silently.

## Visual / brand tokens

### Typography

- Primary: Neue Montreal (Medium, uppercase, for large headings/labels; Light for small running text)
- Secondary: Messina Modern (Regular, title case; used sparingly to add visual interest, not for primary UI)
- Fallback if either is unavailable: Helvetica (sans) / Times New Roman (serif)

### Color

| Swatch | Hex |
| --- | --- |
| Orange (primary) | `F15C24` |
| Yellow (secondary) | `FFC15E` |
| Dark Gray | `303030` |
| White | `FFFFFF` |
| Light Gray | `E5E4DC` |
| Off White | `F4F4F4` |

For any color beyond this core palette, refer to SABIN_BRAND_GUIDELINES_SPREADS_min.pdf (in this folder) as the source of truth.

Keep it concise and uncrowded — Sabin's brand voice is "warm, concise, precise." Avoid decorative or cluttered UI choices. Text-tile options must be expressed as named tokens drawn from this palette and type scale, never as free-form values.

## Acceptance criteria

These are the concrete checks for /eval-sprint and /verify-sprint. Each is verifiable without judgment:

- With JavaScript disabled, view-source contains every unique tile: each media tile's alt text, each text tile's heading, body and CTA label, and a real href on every routable tile.
- The served document contains no duplicate DOM ids. Client-generated padding repeats carry no data-tile-id, are aria-hidden, and are absent from the tab order.
- No routable tile is a `<button>` or a click-handled `<div>`. Destinations marked live: false are non-interactive and are not announced as links.
- A tile's primary link and its corner Build icon are sibling anchors, never nested. Clicking the icon reaches the Build link; clicking anywhere else on the tile reaches the primary destination. Each has its own distinct accessible label.
- The corner icon appears only on image and motion tiles that have a live buildLink. No text tile renders one, and no tile without a live buildLink renders one.
- The corner icon is legible over both light and dark imagery, and is visible without hover on touch devices.
- Tabbing from the top of the gallery reaches every unique routable tile in visual order with a visible focus ring, and Enter activates it. Where a tile has both links, both are reachable and distinguishable by their labels.
- Scrolling again while momentum is still decaying takes effect immediately rather than being queued until the glide settles.
- With prefers-reduced-motion: reduce, there is no momentum scrolling, no tile transition animation, and no motion tile autoplay.
- With row.scrollInertia.enabled false, scrolling is natively handled — keyboard scroll, scrollbar drag and browser find-in-page all behave exactly as on a plain page. Each speed and feel combination produces a perceptibly different result.
- Changing row.layoutMode, row.minCanvasDepth, row.scrollInertia, row.maxConcurrentMotion, a tile's size, or a tile's type in the fixture changes the rendered output with no code edit.
- Switching row.layoutMode to masonry produces visibly staggered, non-aligned rows while 2x2 tiles still render at 2x2, using the same grid — no second layout engine is introduced.
- Motion tiles play only while onscreen, pause when scrolled away, hold a poster frame before playing, and never exceed row.maxConcurrentMotion simultaneously.
- At a 320px viewport the grid shows exactly two columns and never one; widening the viewport reflows column count fluidly with no visible breakpoint jump.
- At a 320px viewport the welcome text tile spans the full grid width, and its heading, body and CTA are all legible with no hidden or shrunken content.
- No text-tile option accepts a free-form colour, size or spacing value — each maps to a named brand token.
- Mobile Lighthouse on the initial viewport: Performance ≥ 85, Accessibility ≥ 95, SEO 100, with LCP under 2.5s on a throttled mobile profile. Report the actual numbers.
- Initial-viewport page weight and request count are within the agreed budget, reported as a number rather than asserted.
- A single documented command previews the gallery locally and works from a clean checkout, showing the row full-screen with no navigation, footer or sibling rows around it.
- The row template renders correctly when dropped into a page containing other content above and below it, with no page-level CSS, no assumption of owning the full viewport, and no reference to navigation, footer or sibling rows. Verify by adding placeholder blocks before and after it in the harness — nothing about the gallery's appearance or behaviour should change.
- Every media tile renders as a square regardless of its source aspect ratio, including the four panoramic files, with no letterboxing, distortion or stretched upscaling. Where a Craft focal point is set, the crop respects it.
- Changing row.tileGap changes the gutter between tiles, and the two-column floor at 320px still holds at every gutter value tested \u2014 confirming the gap and the minmax expression are driven from one source.
- Both layout modes leave no empty cells sitting in the grid, and the final row is complete rather than ragged.
- The top-left welcome tile occupies the first cell regardless of dense packing.
- The row reads all its settings from row fields, so the same template renders correctly from a different fixture with no code edit. No gallery setting is stored outside the row.
- Image transforms and srcset come from ImagerX following the supplied reference, not a bespoke image pipeline, and theming goes through the existing colour macro rather than hardcoded colour classes.

## Explicit non-goals for this build

No React, no JavaScript templating or static-site generator in place of Twig, no drag/reorder, no live Craft connection yet, no periodic auto-shuffle logic yet (though the data layer is shuffle-ready, cadence still TBD), no visitor-facing controls for designation, layout mode or inertia, no tag filtering or tag UI, no footer behaviour of any kind, no navigation or page chrome, no standalone-page architecture, no true GIF encoding, no schema.org markup yet, no full 300+ image library — this is the roughly 30-tile, partial-functionality milestone only.
