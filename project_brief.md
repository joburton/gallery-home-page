# GALLERY HOME PAGE AND WEBSITE — PROJECT BRIEF

## OVERVIEW

This brief scopes some updates for the SABIN website, starting with the Gallery, which becomes the new homepage. The design touches several parts of the site.

## SCOPE

### Architecture

Developed standalone so it can be prototyped and judged before going near the live site, but delivered as a predefined row — a self-contained, reusable content block Craft can place and reorder among other rows, matching the pattern the SABIN site already uses. Confirmed with Foster as the required output shape. A minimal local harness page displays the row on its own during development, with no navigation, footer or sibling rows around it, so it can be seen and interacted with exactly as a prototype; the harness is throwaway and is never delivered. Building it row-shaped from the outset is what makes the prototype trustworthy, since a standalone page invites dependencies on page-level styling and on owning the whole viewport — precisely the things that break on injection. The style will be adapted from Build 2.0's existing gallery layout (currently built in React) and ported into Tailwind and Alpine. This is a reference for grid proportions, sizing, and spacing only, not code or component structure — confirmed by direct inspection, Build 2.0 uses a fluid CSS Grid (auto-fill with a configurable target card size) rather than fixed breakpoints, mixing 1x1 and 2x2 tiles the same way this gallery will. Templates are authored in Twig — the same templating language Craft uses — so Phase 3 integration becomes a matter of swapping the mock JSON data source for Craft's own queries rather than rebuilding the templates. Phase 1 renders them through a minimal local PHP + Twig harness.

### Container

A modular tile grid. The base unit is a 1x1 tile; larger tiles span 2x2 units. Most tiles will be image tiles. Some tiles will be text cells and contain buttons pointing to other pages. The grid is generalized to support arbitrary multi-cell spans, not just 1x1/2x2 (though that is what this milestone uses). Three tile types make up the gallery: image, motion, and text — each tile's type is set, and changeable later, in Craft. Motion tiles are short silent loops, covering what would otherwise have been split into separate video and GIF tiles; they are encoded as muted MP4 rather than true GIF, which would be far too heavy at tile scale. They autoplay muted and looping while onscreen, pause when scrolled away, and are capped so that adding many of them cannot sink the page. Text tiles are configurable per tile but constrained to brand tokens rather than arbitrary values: background from the palette, spacing and type from defined scales, and a CTA button from two or three predefined styles — flexible without allowing off-brand output. They may carry an optional embedded motion clip, and may link out either from the CTA alone or as a whole tile. At narrow widths a text tile spans the full grid width instead of staying square, so the welcome message stays legible on mobile. The gallery's overall layout can toggle between a Swiss grid and a masonry mode, set in Craft, not by visitors. Both run on the same CSS Grid and both pack densely, as Build 2.0 already does, so smaller tiles automatically backfill the holes larger ones leave and no empty cells sit in the layout; the tradeoff is that strict tile order is approximate, with the top-left welcome tile pinned explicitly. What separates the two modes is whether row spans vary: masonry varies them so rows stagger and stop aligning, rather than introducing a second column-based layout engine, which keeps 2x2 and larger tiles working normally. The gutter between tiles is itself editable in Craft as a pixel value, defaulting to 4 to match Build 2.0.

The container will be a pseudo infinite canvas (up/down, not left right – stays in the margins of the website breakpoint). Keep possibility open to become truly infinite. The infinite canvas would loop the images infinitely but stay static in the breakpoint screen. The pseudo infinite canvas is just very long - it also reuses images if there aren’t enough to fill the container length. The screen also stays put and scrolling reveals more content below. But if you scroll enough, beyond the length of the container you eventually reach other content blocks on the home page. The container will subtly scroll down to give a little inertia and motion and not just have a static grid of tiles. The canvas's minimum depth is an editable value in Craft, not a fixed constant — with fewer tiles it may need to be shallower than the roughly 15-screen default used for this milestone. The scroll inertia effect is also Craft-toggleable, with two settings exposed in Craft — speed (slow, medium, fast) and feel (smooth, glide, overshoot) — so it can be turned off or retuned without a rebuild if it does not feel right. With inertia off, or when a visitor's system requests reduced motion, scrolling falls back to fully native behaviour.

### Content

Roughly 35% are in-situ shots (product-in-a-space, the most zoomed-out type). The thirty images are supplied and in the project folder. They are mostly not square — a mix of 4:3, 3:2, 16:9 and a few panoramic — so tiles are cropped to square on render, centred by default, with Craft focal points used where the subject sits off-centre. Tile sizes stay 1x1 and 2x2; no wide tile size is added for the panoramic shots, and any image that does not read well cropped square gets swapped rather than accommodated. The small material swatch files will need re-exporting larger.

### Functionality

Every tile is clickable and routes to a page suited to its content type. Media tiles carry two independent destinations rather than one: clicking the image goes to the relevant page on the website — for in-situ, mid-zoom, detail and sketch tiles, the product page for the thing shown; swatch tiles go to the swatches page and factory tiles to the About page. A small icon in the tile's bottom-right corner is the separate link through to that product's composition in Build. Sending someone straight from a photograph into a specification tool is too abrupt: most people want to see the product first, and only some go on to configure it. The icon defaults to a sliders glyph, is replaceable with an uploaded mark in Craft, and only appears on tiles that actually have a Build link. Text tiles never carry it, since a text tile is not a product and has nothing to configure. Tiles are modular (replaceable, addable, removable), can be marked static (excluded from shuffle, but still scrolling normally, not pinned in place) or left in the periodic shuffle rotation (cadence still TBD).

## TEAM

  - Jo and Wes: design and build, including code development via Claude Code.

  - Carson: consultant

  - Vicky: sourcing and building Rhino models

  - Foster Commerce (Michael): website agency, backend integration once the build is ready.

## PHASE 0: QUICK WEBSITE FIXES

Non-gallery, top-level website cleanup. Quick things to do immediately that would improve the website. Jo handles this directly, in parallel with Phase 1, without Foster.

  - Hide the gallery page completely. (Home page to become the gallery)

  - Rename the current catalog page make it linkable but not public. (Agent portal) Hide first content blocks and go straight to the products. Updates those pack shots to images of multiples.

## PHASE 1: BUILD THE GALLERY

A predefined row built and prototyped locally, not yet live on the website. First milestone: roughly thirty tiles, partial functionality.

  - Container: Layout and style fleshed out — Swiss grid live, masonry mode built but not necessarily turned on. Pseudo-infinite scroll working, canvas depth editable in Craft. Fully responsive, matching Build 2.0's confirmed fluid auto-fill grid mechanism (not a fixed breakpoint set).

  - Roughly thirty tiles loaded in — mostly image tiles, at least one motion tile, plus the welcome text tile — sourced from the images/ folder in this project directory.

  - Top-left welcome text tile is by default designated static (excluded from the periodic shuffle, not pinned in place — it still scrolls normally). The rest are by default designated dynamic. Designation is managed in Craft, not a visitor-facing toggle.

  - Swatch tiles link to the swatches page; factory tiles link live to the About page.

  - Two pre-selected in-situ office renders have live Build links, so only those two show the corner icon. Every other tile renders without one.

  - All other Build-intended tiles are dummy links, not yet functional.

## PHASE 2: FULL BUILD CONNECTION AND SCALE

  - Add many more images beyond the initial ones. Include abstract images.

  - Footer: out of scope for the gallery entirely. It is its own predefined row, managed in Craft, so the gallery canvas simply ends and whatever row follows it takes over. No footer behaviour is built into the gallery.

  - Vicky sources or builds Rhino models and matches compositions for all images, original and new.

  - Jo and Wes wire the matched compositions into Build so every image's corner icon links live.

  - Periodic auto-shuffle built (cadence still TBD — daily, weekly, or monthly). For example, if an article on a product comes out, we will want to skew the amount of images regarding said product. Use tags as a method of control for skewing content inherited from Build 2.0

## PHASE 3: GO LIVE AND CLEANUP

  - Foster handoff: integrate the finished build into the live sabin.design backend.

  - New home page shows the gallery but the label has been dropped

  - Former download page now becomes /catalog page. See migration checklist below.

  - Other miscellaneous design improvements.

## OPEN QUESTIONS

  - Embedded arrays — resolved: the data layer is shaped like a Craft GraphQL response and loaded from a local mock/fixture file for Phase 1, so no rebuild is needed once Craft is connected.

  - Different camera pack shots.

## NON-GOALS (OUT OF SCOPE)

  - Mess up SEO by migrating pages and dropping others

## MIGRATION CHECKLIST

1.  Audit before you touch anything. Pull Google Search Console data for both /catalog and /downloads — impressions, clicks, ranking queries, and referring backlinks (via Ahrefs/Semrush/GSC links report). Whichever URL has more authority/backlinks is the one that survives.

2.  Pick one surviving URL (likely /catalog/, since "catalog" typically has more inherent search intent than "downloads"). Rebuild it in the Downloads page's *layout* (as you planned), but keep it living at the stronger URL.

3.  301 redirect the deprecated URL to the surviving one. Never let it 404 or just vanish — a 301 passes along the accumulated link equity and tells Google "this content moved," not "this content is gone."

4.  Make every thumbnail clickable to its PDP, as you planned. This is also good SEO on its own — it adds internal links to your product pages, which is a ranking signal you're currently *not* getting from the Downloads page today.

5.  Rewrite on-page SEO for the merged page so you don't lose either keyword's relevance:
    
      - Title tag: something like *"Product Catalog & Downloads | Sabin"*
    
      - Meta description mentioning both browsing products and downloading specs/manuals/datasheets
    
      - A short 100–150 word intro paragraph above the grid, naturally using both "catalog" and "downloads"
    
      - H1/H2s that reflect both intents

6.  Update every internal link pointing to the old URL — main nav, footer, breadcrumbs, sitemap.xml — so they all point to the new consolidated page. (You can even keep two nav labels — "Catalog" and "Downloads" — both pointing to the same URL, which preserves user mental models without needing two pages.)

7.  Resubmit your sitemap in GSC/Bing Webmaster Tools after launch, and monitor the redirected URL's coverage status for a few weeks to confirm Google has processed the move.

8.  Don't hard-delete the old page/redirect for at least 12 months — keep the 301 live indefinitely as a safety net.

## FUTURE PROOFING FOR LLM SEARCHES

AI answer engines (Google AI Overviews, Perplexity, ChatGPT browsing, Claude, etc.) and their crawlers (GPTBot, ClaudeBot, PerplexityBot, Google-Extended) favor exactly the kind of consolidation you're describing, provided the technical execution is clean:

  - One authoritative page beats two competing ones. LLMs build an internal "map" of your site's entities (products, categories, docs). A single well-structured catalog+downloads page is easier for a model to parse and cite correctly than two overlapping ones.

  - Structured data (JSON-LD) matters more than keyword-stuffing now. Add schema.org/ItemList on the merged listing page, Product schema on each PDP, and DigitalDocument/CreativeWork schema linking each product to its downloadable assets (datasheet, manual, CAD file). This gives both classic search and AI crawlers an explicit, unambiguous relationship between product ↔ downloads, even before a user clicks anything.

  - Descriptive alt text on every thumbnail (product name + short descriptor) — LLMs and image-aware crawlers lean on this heavily since they often can't reliably interpret packshots visually at scale.

  - Server-rendered, non-JS-gated content. If your product grid or download links currently render via client-side JS only, make sure the HTML is crawlable without executing scripts — many AI crawlers don't render JS.

  - Consider adding an llms.txt file at your root — an emerging (not yet universally adopted) convention, similar to robots.txt, that gives AI crawlers a curated markdown index of your key pages. Treat this as a low-cost, forward-looking addition rather than a proven necessity — the standard is still evolving.

  - Keep product names/SKUs consistent everywhere (catalog, downloads, PDP, schema) — entity consistency is what lets LLMs confidently associate a product with its resources when someone asks an AI assistant "where can I download the manual for X."

## Notes for Claude instructions

Use [leos_claude_starter](https://github.com/leogodin217/leos_claude_starter) as the working process for this build. It's a Claude Code workflow (architect, sprint, implement, review), not a frontend tool. An earlier draft of this brief linked to an unrelated leo-blog-starter repo; that was the wrong link and does not apply.

The administration of the gallery's content must work in Craft and be as simple as possible: adding or removing tiles, setting each tile's type (image, motion, or text) and size, its static/dynamic designation, the gallery's layout style (Swiss grid vs. masonry), the scroll inertia effect (on/off, speed, feel), and the canvas's minimum depth. Use the tagging convention Build 2.0 is using. In the same way, the administration of image routing to Build 2.0 destinations must not be fussy, and should be managed in Craft. These same SEO/LLM-search principles from the migration checklist above apply to the Phase 1 gallery build itself, starting now, not just to the later Catalog/Downloads migration: gallery content (including the new text tiles) must be real, crawlable HTML, server-rendered rather than client-side-only, with descriptive alt text on every image and motion tile. The Phase 1 build lives in this same project folder on the local machine, with source media in its images/ subfolder and no remote repository yet. A fuller specification — including acceptance criteria, performance and accessibility requirements, and the mock data shape — is in Instructions for Gallery.
