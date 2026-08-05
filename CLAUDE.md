# SABIN Gallery Row — Phase 1

**A self-contained Craft predefined row that renders SABIN's gallery — a pseudo-infinite, densely packed canvas of image, motion and text tiles — as crawlable, server-rendered HTML.**

The gallery becomes SABIN's new homepage. It is built and prototyped standalone but delivered as a *predefined row*: a reusable content block Craft can place and reorder among other rows. It is not a page. Phase 1 is a ~30-tile, partial-functionality milestone rendered through a local PHP + Twig harness against a mock JSON fixture; Phase 3 swaps that fixture for Craft's own element queries with the templates surviving intact.

```
fixture.json ──▶ PHP harness ──▶ Twig row template ──▶ static HTML + Tailwind CSS
(shaped like a      (throwaway,       (the ONLY                   │
 Craft GraphQL       never            deliverable,                ▼
 response)           delivered)       + field defs)         Alpine/vanilla JS
                                                            enhances only:
     ▲                                                      ├─ inert padding repeats
     └─ Phase 3: Craft element queries replace ──┘           ├─ scroll inertia
        the fixture; templates unchanged                     └─ motion play/pause
```

**Audiences, in priority order:** crawlers and LLM answer engines (a first-order concern, not an afterthought) → site visitors → Craft admins who administer content → Foster Commerce, who integrate the row in Phase 3.

## LLM-Driven Project

This project uses LLM-assisted development. All documents are optimized for LLM context efficiency while maintaining comprehensive coverage.

**Source of truth:** `PROJECT-STATE.md` orients; this file holds the invariants; `docs/architecture/gallery-row.md` holds the design; `docs/craft-field-definitions.md` is the Foster handoff contract. `Instructions for Gallery.md` is the *superseded* original brief — useful for why a decision was made, not current on what exists. `project_brief.md` gives wider multi-phase context. `predefined row reference.twig` is Foster's production row — the conventions to follow, not a file to edit. Requirement language in the spec is deliberate: **"must"** marks a hard invariant (never trade it away for convenience — flag it instead); **"default"** marks a starting value that *must be exposed as configuration*, never hardcoded; only text under *"Open to Claude's judgment"* is genuinely open. If something looks missing or contradictory, raise it rather than guessing.

---

## Core Principles

These principles are **non-negotiable** and guide all decisions.

### 1. Crawlable Content Is The Product

SEO and LLM-search readability are first-order design constraints. Any content carrying meaning — media alt text, text-tile heading/body/CTA — must be real, semantic HTML present in the served document. Never a screenshot or an image of text. JS may enhance; it must never be the only path by which tile content reaches the DOM.

**Examples:**
- With JS disabled, view-source contains every unique tile's alt text, copy, and a real `href`.
- Every routable tile is a real `<a href="...">`.

**What this prohibits:**
- `<button>` or a click-handled `<div>` for any routable tile. Build 2.0 uses `<button type="button">` for its cards; this is the specific place NOT to copy it, since doing so would make the gallery's entire routing invisible to search and AI crawlers.
- Client-side-only tile rendering, hydration-gated content, or JS-injected hrefs.

### 2. Everything Configurable, By Craft Admins Only

Every gallery setting is a field on the `row` variable, Craft-driven, and read as data. Anything the spec marks "default" is a tunable value, not a constant. Simultaneously: **no setting is ever visitor-facing.** Tile designation, layout mode, inertia, canvas depth, gutter and target card size are pure data reads — Alpine must never expose a control that lets a visitor change them.

**What this prohibits:**
- Hardcoded canvas depth, gutter, target card size, motion cap, or layout mode.
- Any gallery setting stored outside the row.
- Visitor-facing toggles, filters, or layout switchers of any kind.

### 3. Correctness Is Non-Negotiable, And Measured

Performance and accessibility are requirements, not optimisations — this is the most performance-sensitive page on the site, and Core Web Vitals feed straight back into Principle 1. Targets are reported as numbers, never asserted as "looks fine": mobile Lighthouse Performance ≥ 85, Accessibility ≥ 95, SEO 100, LCP < 2.5s throttled, plus an agreed initial-viewport weight and request budget.

### 4. Flag, Never Silently Paper Over

When an input is wrong or a requirement can't be met, surface it. The spec repeatedly demands this: flag undersized assets for re-export rather than upscaling them; flag missing webfont licences rather than substituting silently; flag a conflicting requirement rather than working around it. For visitors, degrade safely — a destination marked `live: false` renders as a non-interactive element, not a dead link.

### 5. No Future Scaffolding

Don't write code for functionality that doesn't exist yet. Add it when the feature is implemented, not before.

**What this prohibits:**
- Loops that iterate but do nothing, method stubs, parameters accepted but ignored, `TODO:` placeholder code.
- Phase 2/3 work: shuffle scheduling, tag filtering or tag UI, schema.org markup, live Craft wiring.

**What this permits:**
- Carrying `tags` through the data layer unused, because the fixture shape is itself the Phase 3 contract.
- Generality the spec explicitly mandates: arbitrary `cols x rows` spans, a swappable Build icon, changeable tile type.

### 6. The Template Shape Is The Contract

Greenfield everywhere else — breaking changes are fine, no compatibility shims, no deprecation paths. The one thing that must not churn is the row template's shape and its `row.*` field surface, because that is what Foster integrates in Phase 3. Substituting a JavaScript templating language or a static-site generator for Twig would turn that handoff into a rewrite.

### 7. Row-Shaped, Never Page-Shaped

The row is self-contained under a single root element and assumes nothing about what sits above or below it. A standalone page invites page-level CSS, assumptions about owning the viewport, and styles that only work because nothing else is present — exactly what breaks on injection day. A row that renders correctly in a bare harness renders correctly on the real homepage, because it was never allowed to depend on its surroundings.

### 8. Follow Foster's Conventions, Don't Invent Parallel Ones

Theming through the shared colour macro; named typography classes from the existing scale; ImagerX (`craft.imagerx.transformImage` / `craft.imagerx.srcset`) for all transforms and srcset; small UI pieces as component includes. Deviating creates a second set of conventions on the same site.

### 9. Deliberately Light Dependencies

The project lives in a cloud-synced folder, where large dependency trees cause sync thrashing and intermittent build failures. Tailwind standalone CLI (not Node/PostCSS), Alpine vendored as a single file (not installed), Composer for Twig only.

---

## Documentation Structure

| Document | Purpose | When to Load |
|----------|---------|--------------|
| `CLAUDE.md` | Principles, invariants, navigation | Always |
| `Instructions for Gallery.md` | Authoritative Phase 1 spec + acceptance criteria | Any build decision |
| `project_brief.md` | Multi-phase context, phase boundaries | Scope questions |
| `predefined row reference.twig` | Foster's row conventions | Any template work |
| `docs/CAPABILITIES.md` | What the system does | Feature overview |
| `docs/architecture/*.md` | Design + rationale | Implementation, design questions |
| `docs/sprints/` | Sprint specs and status | Sprint work |

**Loading strategy:** See `docs/architecture/README.md` for document index and reading order.

---

## Key Invariants

Always true in this codebase:

| Invariant | Meaning |
|-----------|---------|
| Server-rendered content | Every unique tile's alt text, copy, CTA label and `href` are in the served HTML without JS |
| Real anchors only | No routable tile is a `<button>` or click-handled `<div>` |
| Sibling, never nested anchors | Primary tile link (`absolute inset-0`) and corner Build icon are siblings, each with its own distinct accessible label |
| No duplicate DOM ids | Each unique tile appears once in the document; only that canonical instance carries `data-tile-id` |
| Inert padding repeats | Client-generated repeats have no `data-tile-id`, are `aria-hidden="true"`, and are outside the tab order — but stay clickable by pointer |
| Keyboard order is document order | Every unique routable tile is keyboard-reachable in **document** order with a visible focus ring. Dense packing means this cannot also match visual order; decided 5 Aug 2026 |
| Dead links aren't links | `live: false` destinations render non-interactive — not announced as links, not tabbable, no placeholder navigation |
| Icon only where it leads somewhere | Corner icon renders only on image/motion tiles with a live `buildLink`; never on a text tile |
| One layout engine | Both Swiss and masonry are the same dense-packed CSS Grid; the modes differ only in whether row spans vary |
| Two-column floor | Exactly two columns at 320px, never one; column count reflows fluidly with no breakpoint jumps |
| One gutter source of truth | A single CSS custom property drives both the grid `gap` and the `minmax()` two-column-floor expression |
| Settings live on the row | Every gallery setting reads off `row.*`; the same template renders a different fixture with no code edit |
| Square tiles, never upscaled | Every media tile crops 1:1 via ImagerX honouring focal point; undersized assets render native and get flagged |
| Reduced motion is absolute | `prefers-reduced-motion: reduce` ⇒ no momentum scroll, no tile transitions, no motion autoplay |
| Inertia degrades to native | Disabled or reduced-motion leaves *no* scroll interception — keyboard, scrollbar drag and find-in-page behave as on a plain page |
| Scroll input always wins | New scroll input retargets mid-animation immediately — never queued, never swallowed until the glide settles |
| Motion is muted MP4 | Never true GIF; always a poster; plays only onscreen; never exceeds `row.maxConcurrentMotion` |
| Brand tokens only | No free-form colour, size, or spacing value is accepted anywhere |
| Spans are generic | `size` is `cols x rows`; code must not assume 1x1 and 2x2 are the only valid values |

---

## DO NOT

- Write code for features that don't exist yet (Principle #5)
- Use React or any component framework — Build 2.0 is React; this must not become a second frontend paradigm on the site
- Substitute a JS templating language or static-site generator for Twig (Principle #6)
- Copy Build 2.0's `<button>` cards, its code, or its component structure — it is a *visual* reference for grid proportions, tile sizing and spacing only
- Hardcode any value the spec marks "default" (Principle #2)
- Serve tile repetition as duplicated HTML — repeating 30 tiles across 15 screens is both a duplicate-content problem and a large avoidable DOM
- Nest `<a>` elements — invalid HTML that browsers restructure unpredictably
- Hover-reveal the Build icon only — touch devices have no hover, so it must be permanently visible (reduced opacity is fine)
- Rely on imagery happening to be dark behind the corner icon — it needs its own contrast treatment (scrim, shadow, or blurred chip)
- Build a second, column-based masonry engine alongside the auto-fill grid
- Build a bespoke image pipeline instead of ImagerX, or hardcoded colour classes instead of the colour macro
- Rename the source media in `images/` — the descriptive names carry provenance, so assign `contentType` per tile in the fixture instead
- Build tag filtering, tag UI, footer behaviour, navigation, page chrome, drag/reorder, or shuffle scheduling
- Add a wide or panoramic tile size to accommodate wide source images — crop them square; swap any image that doesn't read well cropped
- Install a Node/PostCSS toolchain or npm-install Alpine (Principle #9)

---

## Anti-Patterns

Concrete patterns to avoid and detect during review.

### Over-Engineering

| Pattern | Example | Fix |
|---------|---------|-----|
| One-use abstractions | A macro or include with a single call site | Inline it |
| Premature helpers | A Twig function or filter used once | Inline it |
| Hypothetical features | Designing for "what if" beyond mandated generality | Build for current needs |
| Second engine | Column-based masonry beside the auto-fill grid | One dense grid, varying row spans |
| Reinvented pipeline | Custom transform/srcset code | ImagerX per the reference |

### Dead Code

| Pattern | Detection |
|---------|-----------|
| Unused Twig macros/includes | Grep for the include path |
| Unreachable branches | `{% if false %}` or impossible conditions |
| Commented-out markup | Delete it (git has history) |
| Orphaned Tailwind classes | Not present in any template Tailwind scans |

### Accessibility & SEO Anti-Patterns

| Pattern | Why It's Bad | Fix |
|---------|--------------|-----|
| `<button>`/`<div>` routing | Crawlers follow hrefs, they don't click | Real `<a href>` |
| Nested anchors | Invalid HTML, unpredictable restructuring | Sibling anchors, stacked |
| `href="#"` for `live: false` | Dead interactive element, announced as a link | Non-interactive element |
| Two links, one label | Screen reader hears "link, link" | Distinct labels per link |
| Duplicated tile markup | Duplicate ids and duplicate content | Unique once; repeats client-side and inert |
| Alt text as decoration | Answer engines lean on it heavily | Descriptive alt on every media tile |
| Shrinking type to fit | Text tile illegible at the two-column floor | Full-width short block below threshold |

### Layout & Media Anti-Patterns

| Pattern | Why It's Bad | Fix |
|---------|--------------|-----|
| Diverging gap values | A gutter change silently collapses the grid to one column | One custom property feeds `gap` and `minmax()` |
| Tailwind `sm:`/`md:`/`lg:` column sets | Discrete jumps instead of fluid reflow | `repeat(auto-fill, minmax(min(card-target, calc((100% - gap)/2)), 1fr))` |
| Assuming 1x1/2x2 only | A future `2x3` becomes an architecture change | Parse `cols x rows` generically |
| Letting packing place the welcome tile | Dense flow moves it off the first cell | Pin it explicitly |
| Ragged final row | The canvas already repeats tiles, so it can always complete the row | Pull another tile |
| Upscaling small assets | 220px swatches look soft on a 2in HiDPI tile | Render native, flag for re-export |
| Assuming opaque media | At least one asset is RGBA | Tiles sit on a defined background |
| True GIF encoding | One GIF can outweigh every other asset on the page | Muted MP4 |

---

## Code Navigation

- This is a PHP/Twig + Tailwind + Alpine project — no Python, no Node build step.
- Use Grep/Glob to locate templates, macros, includes and fixture fields; the templates are small and shallow.
- For "where is this row field read?", Grep the field name (e.g. `minCanvasDepth`) across the templates directory — every setting should have exactly one read site plus its CSS custom-property binding.

---

## Related Documents

| Document | Purpose |
|----------|---------|
| [Instructions for Gallery.md](Instructions%20for%20Gallery.md) | Authoritative spec, acceptance criteria, fixture shape |
| [project_brief.md](project_brief.md) | Multi-phase context and phase boundaries |
| [predefined row reference.twig](predefined%20row%20reference.twig) | Foster's row conventions |
| [docs/CAPABILITIES.md](docs/CAPABILITIES.md) | What the system does |
| [docs/architecture/](docs/architecture/) | Design rationale and constraints |
| [docs/sprints/](docs/sprints/) | Sprint specifications |
